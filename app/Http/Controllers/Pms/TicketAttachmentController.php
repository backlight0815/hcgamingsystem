<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmTicketAttachment;
use App\Models\PmWorkItem;
use App\Services\PmsAttachmentService;
use App\Services\PmsAttachmentThumbnail;
use App\Services\PmsTicketNotificationService;
use App\Support\PmsTicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketAttachmentController extends Controller
{
    public function store(Request $request, PmWorkItem $ticket, PmsAttachmentService $attachments)
    {
        abort_unless(PmsTicketAccess::comment($request->user(), $ticket), 403);
        $request->validate(['attachments' => 'required|array|min:1', 'attachments.*' => 'required|file']);
        $files = $request->file('attachments', []);
        $attachments->validateFiles($files);
        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $ticket, $files, $attachments, &$storedPaths): void {
                $attachments->storeMany($ticket, $request->user(), $files, null, $storedPaths);
                DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'ticket.attachment_added', 'subject_type' => 'issue', 'subject_id' => $ticket->id, 'details' => json_encode(['count' => count($files)]), 'created_at' => now()]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        app(PmsTicketNotificationService::class)->notifyParticipants($ticket, $request->user(),
            "Evidence added to {$ticket->reference}", count($files).' attachment(s) added to '.$ticket->summary);

        return $request->expectsJson() ? response()->json($ticket->attachments()->latest()->limit(count($files))->get(), 201) : back()->with('success', 'Evidence attached.');
    }

    public function show(Request $request, PmWorkItem $ticket, PmTicketAttachment $attachment)
    {
        abort_unless((int) $attachment->issue_id === $ticket->id && PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless(Storage::disk('local')->exists($attachment->storage_path), 404);
        $path = Storage::disk('local')->path($attachment->storage_path);
        $imageTypes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
        $image = ($imageTypes[strtolower($attachment->extension)] ?? null) === $attachment->mime_type && @getimagesize($path);
        $pdf = $attachment->extension === 'pdf' && $attachment->mime_type === 'application/pdf'
            && file_get_contents($path, false, null, 0, 5) === '%PDF-';
        $video = in_array($attachment->extension, ['mp4', 'webm'], true)
            && $attachment->mime_type === 'video/'.$attachment->extension;
        $inline = $request->boolean('preview') && ($image || $pdf || $video);
        $headers = [
            'Content-Type' => $inline ? $attachment->mime_type : 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'",
            'Cache-Control' => 'private, no-store',
        ];
        if ($inline) {
            return response()->file(Storage::disk('local')->path($attachment->storage_path), $headers);
        }

        return Storage::disk('local')->download($attachment->storage_path, $attachment->original_name, $headers);
    }

    public function thumbnail(Request $request, PmWorkItem $ticket, PmTicketAttachment $attachment, PmsAttachmentThumbnail $thumbnails)
    {
        abort_unless((int) $attachment->issue_id === $ticket->id && PmsTicketAccess::view($request->user(), $ticket), 404);
        $path = $thumbnails->path($attachment);
        abort_unless($path, 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'image/webp', 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'self'",
        ]);
    }

    public function destroy(Request $request, PmWorkItem $ticket, PmTicketAttachment $attachment, PmsAttachmentThumbnail $thumbnails)
    {
        abort_unless((int) $attachment->issue_id === $ticket->id && PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_if($attachment->category === 'test_case', 409, 'Use the test case removal action to retain review history.');
        abort_unless(PmsTicketAccess::manage($request->user(), $ticket), 403);
        DB::transaction(function () use ($request, $ticket, $attachment): void {
            $attachment->delete();
            DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => 'ticket.attachment_removed', 'subject_type' => 'issue', 'subject_id' => $ticket->id, 'details' => json_encode(['attachment_id' => $attachment->id]), 'created_at' => now()]);
        });
        Storage::disk('local')->delete($attachment->storage_path);
        $thumbnails->forget($attachment);

        return $request->expectsJson() ? response()->json(['deleted' => true]) : back()->with('success', 'Attachment removed from this ticket.');
    }
}
