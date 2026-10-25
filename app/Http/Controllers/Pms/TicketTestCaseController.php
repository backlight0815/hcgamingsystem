<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmTicketAttachment;
use App\Models\PmWorkItem;
use App\Services\PmsAttachmentService;
use App\Services\PmsTestCaseReviewService;
use App\Support\PmsTicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketTestCaseController extends Controller
{
    public function index(Request $request, PmWorkItem $ticket)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 404);
        $files = $ticket->attachments()->where('category', 'test_case')->with('uploader:id,name')
            ->orderByDesc('created_at')->orderByDesc('id')->get()->map(fn ($file) => [
                'id' => $file->id, 'name' => $file->original_name, 'type' => $file->extension,
                'size_bytes' => $file->size_bytes, 'version' => $file->version,
                'uploader' => $file->uploader?->name, 'uploaded_at' => $file->created_at?->toIso8601String(),
                'removed_at' => $file->removed_at, 'download_url' => route('pms.tickets.test-cases.show', [$ticket, $file]),
            ]);
        $review = DB::table('pm_test_case_reviews')->where('issue_id', $ticket->id)->first();
        $events = $review ? DB::table('pm_test_case_review_events')->where('review_id', $review->id)
            ->orderByDesc('id')->get() : collect();
        return response()->json(compact('files', 'review', 'events'));
    }

    public function store(Request $request, PmWorkItem $ticket, PmsAttachmentService $attachments, PmsTestCaseReviewService $reviews)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless(PmsTicketAccess::comment($request->user(), $ticket), 403);
        $request->validate(['test_cases' => 'required|array|min:1|max:'.config('pms.attachments.max_files_per_request', 10),
            'test_cases.*' => 'required|file', 'replaces_attachment_id' => 'nullable|integer']);
        $files = $request->file('test_cases', []);
        $attachments->validateFiles($files, 'test_case');
        $replace = null;
        if ($request->filled('replaces_attachment_id')) {
            abort_unless(count($files) === 1, 422, 'Replace one version at a time.');
            $replace = PmTicketAttachment::where('issue_id', $ticket->id)->where('category', 'test_case')
                ->whereNull('removed_at')->findOrFail($request->integer('replaces_attachment_id'));
        }
        $existing = $ticket->attachments()->where('category', 'test_case');
        if ((clone $existing)->count() + count($files) > (int) config('pms.test_cases.max_files_per_ticket', 100)
            || (clone $existing)->sum('size_bytes') + array_sum(array_map(fn ($file) => $file->getSize(), $files))
                > (int) config('pms.test_cases.max_total_kb', 204800) * 1024) {
            throw ValidationException::withMessages(['test_cases' => 'This ticket has reached its test case storage limit.']);
        }
        $paths = [];
        try {
            DB::transaction(function () use ($ticket, $request, $files, $attachments, $reviews, $replace, &$paths): void {
                $attachments->storeMany($ticket, $request->user(), $files, null, $paths, 'test_case', $replace?->id);
                if ($replace) $replace->forceFill(['removed_at' => now(), 'removed_by' => $request->user()->id])->save();
                $reviews->contentChanged($ticket, $request->user(), $replace ? 'version_replaced' : 'files_uploaded');
                $this->audit($ticket, $request, $replace ? 'ticket.test_case_replaced' : 'ticket.test_case_uploaded',
                    ['count' => count($files), 'replaces_attachment_id' => $replace?->id]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }
        return $request->expectsJson() ? response()->json(['uploaded' => count($files)], 201)
            : back()->with('success', count($files).' test case file(s) uploaded.');
    }

    public function show(Request $request, PmWorkItem $ticket, PmTicketAttachment $attachment, TicketAttachmentController $controller)
    {
        abort_unless((int) $attachment->issue_id === $ticket->id && $attachment->category === 'test_case'
            && PmsTicketAccess::view($request->user(), $ticket), 404);
        return $controller->show($request, $ticket, $attachment);
    }

    public function destroy(Request $request, PmWorkItem $ticket, PmTicketAttachment $attachment, PmsTestCaseReviewService $reviews)
    {
        abort_unless((int) $attachment->issue_id === $ticket->id && $attachment->category === 'test_case'
            && PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless(PmsTicketAccess::comment($request->user(), $ticket), 403);
        abort_if($attachment->removed_at, 409, 'This file was already removed.');
        DB::transaction(function () use ($ticket, $request, $attachment, $reviews): void {
            $attachment->forceFill(['removed_at' => now(), 'removed_by' => $request->user()->id])->save();
            $reviews->contentChanged($ticket, $request->user(), 'file_removed', $attachment->original_name);
            $this->audit($ticket, $request, 'ticket.test_case_removed', ['attachment_id' => $attachment->id]);
        });
        return $request->expectsJson() ? response()->json(['removed' => true]) : back()->with('success', 'Test case removed from current files. Its history is retained.');
    }

    public function assign(Request $request, PmWorkItem $ticket, PmsTestCaseReviewService $reviews)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless(PmsTicketAccess::comment($request->user(), $ticket), 403);
        $data = $request->validate(['test_case_reviewer_id' => 'nullable|integer|exists:users,id']);
        $review = $reviews->assign($ticket, $request->user(), $data['test_case_reviewer_id'] ?? null);
        return $request->expectsJson() ? response()->json($review) : back()->with('success', 'Test case reviewer updated.');
    }

    public function decide(Request $request, PmWorkItem $ticket, PmsTestCaseReviewService $reviews)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 404);
        $data = $request->validate(['action' => ['required', Rule::in(['start', 'approve', 'request_changes', 'submit_revision'])],
            'comment' => 'required_if:action,request_changes|nullable|string|min:10|max:5000']);
        $review = $reviews->decide($ticket, $request->user(), $data['action'], $data['comment'] ?? null);
        return $request->expectsJson() ? response()->json($review) : back()->with('success', 'Test case review updated.');
    }

    private function audit(PmWorkItem $ticket, Request $request, string $action, array $details): void
    {
        DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action,
            'subject_type' => 'issue', 'subject_id' => $ticket->id,
            'details' => json_encode($details + ['reference' => $ticket->reference, 'project_id' => $ticket->project_id]),
            'created_at' => now()]);
    }
}
