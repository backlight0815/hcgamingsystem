<?php

namespace App\Services;

use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsTicketAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsTestCaseReviewService
{
    public const STATUSES = ['not_requested', 'pending_review', 'in_review', 'changes_requested', 'approved'];

    public function candidates(PmWorkItem $ticket)
    {
        if (! $ticket->project_id) return collect();
        $ids = DB::table('pm_project_members')->where('project_id', $ticket->project_id)
            ->whereIn('role', ['QA', 'QA Lead', 'QA Engineer', 'Project Manager', 'Project Administrator'])
            ->pluck('user_id')->push($ticket->project->owner_id)->unique();

        return User::query()->with('pmsProfile')->whereIn('id', $ids)->where('status', 1)->orderBy('name')->get()
            ->filter(fn (User $user) => PmsAccess::eligible($user) && PmsTicketAccess::view($user, $ticket))->values();
    }

    public function validateReviewer(PmWorkItem $ticket, ?int $reviewerId): ?User
    {
        if (! $reviewerId) return null;
        $candidate = $this->candidates($ticket)->firstWhere('id', $reviewerId);
        if (! $candidate) throw ValidationException::withMessages(['test_case_reviewer_id' => 'Choose an active reviewer with access to this project.']);
        return $candidate;
    }

    public function assign(PmWorkItem $ticket, User $actor, ?int $reviewerId): object
    {
        $reviewer = $this->validateReviewer($ticket, $reviewerId);
        $review = DB::transaction(function () use ($ticket, $actor, $reviewer): object {
            $row = DB::table('pm_test_case_reviews')->where('issue_id', $ticket->id)->lockForUpdate()->first();
            if ($row && (int) $row->reviewer_id === (int) ($reviewer?->id ?? 0)) return $row;
            $before = $row?->status ?? 'not_requested';
            $version = max(1, (int) ($row?->submission_version ?? 0));
            $status = $reviewer ? 'pending_review' : 'not_requested';
            $values = ['reviewer_id' => $reviewer?->id, 'requested_by' => $actor->id,
                'status' => $status, 'submission_version' => $version, 'approved_by' => null,
                'reviewed_at' => null, 'updated_at' => now()];
            if ($row) {
                DB::table('pm_test_case_reviews')->where('id', $row->id)->update($values);
                $id = $row->id;
            } else {
                $id = DB::table('pm_test_case_reviews')->insertGetId($values + ['issue_id' => $ticket->id, 'created_at' => now()]);
            }
            $this->event($id, $actor, $reviewer ? 'reviewer_assigned' : 'reviewer_cleared', $before, $status, $version);
            PmsTicketActivity::record($ticket->id, 'test_case_review', 'reviewer_id', $row?->reviewer_id, $reviewer?->id,
                ['labels' => ['old' => $row?->reviewer_id ? User::find($row->reviewer_id)?->name : 'None', 'new' => $reviewer?->name ?? 'None']], $actor);
            return DB::table('pm_test_case_reviews')->find($id);
        });
        if ($reviewer && (int) $reviewer->id !== $actor->id) $this->notify($reviewer->id, $actor, $ticket,
            'Test case review assigned', "Review test cases for {$ticket->reference}.");
        return $review;
    }

    public function contentChanged(PmWorkItem $ticket, User $actor, string $action, ?string $comment = null): void
    {
        $row = DB::table('pm_test_case_reviews')->where('issue_id', $ticket->id)->lockForUpdate()->first();
        if (! $row) return;
        $version = (int) $row->submission_version + 1;
        $status = $row->reviewer_id
            ? ($row->status === 'changes_requested' ? 'changes_requested' : 'pending_review') : 'not_requested';
        DB::table('pm_test_case_reviews')->where('id', $row->id)->update([
            'status' => $status, 'submission_version' => $version, 'approved_by' => null,
            'reviewed_at' => null, 'updated_at' => now(),
        ]);
        $this->event($row->id, $actor, $action, $row->status, $status, $version, $comment);
        PmsTicketActivity::record($ticket->id, 'test_case_review', 'status', $row->status, $status,
            ['action' => $action, 'submission_version' => $version], $actor);
    }

    public function decide(PmWorkItem $ticket, User $actor, string $action, ?string $comment): object
    {
        $review = DB::transaction(function () use ($ticket, $actor, $action, $comment): object {
            $row = DB::table('pm_test_case_reviews')->where('issue_id', $ticket->id)->lockForUpdate()->first();
            abort_unless($row && $row->reviewer_id, 409, 'Assign a reviewer first.');
            $isReviewer = (int) $row->reviewer_id === $actor->id && PmsAccess::eligible($actor)
                && PmsTicketAccess::view($actor, $ticket);
            if ($action === 'submit_revision') {
                abort_unless(PmsTicketAccess::comment($actor, $ticket), 403);
                abort_unless($row->status === 'changes_requested', 409);
                $requestedVersion = (int) DB::table('pm_test_case_review_events')->where('review_id', $row->id)
                    ->where('action', 'request_changes')->orderByDesc('id')->value('submission_version');
                abort_unless((int) $row->submission_version > $requestedVersion, 409, 'Upload revised test cases first.');
                $status = 'pending_review';
            } else {
                abort_unless($isReviewer, 403);
                abort_unless(in_array($row->status, ['pending_review', 'in_review'], true), 409);
                if ($action === 'start') $status = 'in_review';
                elseif ($action === 'approve') {
                    abort_if(DB::table('pm_ticket_attachments')->where('issue_id', $ticket->id)
                        ->where('category', 'test_case')->where('uploaded_by', $actor->id)
                        ->whereNull('removed_at')->whereNull('deleted_at')->exists(), 403,
                        'A test case uploader cannot approve their own current files.');
                    abort_unless(DB::table('pm_ticket_attachments')->where('issue_id', $ticket->id)
                        ->where('category', 'test_case')->whereNull('removed_at')->whereNull('deleted_at')->exists(), 409,
                        'Upload a test case before approval.');
                    $status = 'approved';
                } else {
                    abort_unless(filled($comment), 422, 'Actionable comments are required.');
                    $status = 'changes_requested';
                }
            }
            DB::table('pm_test_case_reviews')->where('id', $row->id)->update([
                'status' => $status, 'approved_by' => $status === 'approved' ? $actor->id : null,
                'reviewed_at' => in_array($status, ['approved', 'changes_requested'], true) ? now() : null,
                'updated_at' => now(),
            ]);
            $this->event($row->id, $actor, $action, $row->status, $status, $row->submission_version, $comment);
            PmsTicketActivity::record($ticket->id, 'test_case_review', 'status', $row->status, $status,
                ['comment' => $comment, 'submission_version' => $row->submission_version], $actor);
            return DB::table('pm_test_case_reviews')->find($row->id);
        });
        if ($action === 'submit_revision') $this->notify((int) $review->reviewer_id, $actor, $ticket,
            'Revised test cases submitted', "Updated test cases for {$ticket->reference} are ready for review.");
        elseif (in_array($action, ['approve', 'request_changes'], true)) {
            foreach (array_unique(array_filter([$ticket->qa_id, $ticket->reporter_id])) as $id) {
                $this->notify((int) $id, $actor, $ticket, $action === 'approve' ? 'Test cases approved' : 'Test case changes requested',
                    $comment ?: "Test case review updated for {$ticket->reference}.");
            }
        }
        return $review;
    }

    private function event(int $reviewId, User $actor, string $action, ?string $old, string $new, int $version, ?string $comment = null): void
    {
        DB::table('pm_test_case_review_events')->insert(['review_id' => $reviewId, 'actor_id' => $actor->id,
            'action' => $action, 'previous_status' => $old, 'new_status' => $new,
            'submission_version' => $version, 'comment' => $comment, 'created_at' => now()]);
    }

    private function notify(int $userId, User $actor, PmWorkItem $ticket, string $title, string $message): void
    {
        if ($userId === $actor->id) return;
        try {
            $recipient = User::find($userId);
            if ($recipient && PmsTicketAccess::view($recipient, $ticket)) {
                AppNotificationService::notifyUser($userId, $title, $message,
                    route('pms.tickets.show', $ticket), 'project_management', $actor->id);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
