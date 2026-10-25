<?php

namespace App\Services;

use App\Http\Controllers\Pms\IssueController;
use App\Http\Controllers\Pms\ProjectController;
use App\Http\Controllers\Pms\TicketController;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsReviewStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsChangeReviewService
{
    public function history(string $kind, int $id)
    {
        return DB::table('pm_change_requests as reviews')
            ->join('users as submitters', 'submitters.id', '=', 'reviews.submitted_by')
            ->join('users as reviewers', 'reviewers.id', '=', 'reviews.reviewer_id')
            ->where('reviews.subject_type', $kind)->where('reviews.subject_id', $id)
            ->orderByDesc('reviews.id')->limit(10)
            ->get(['reviews.*', 'submitters.name as submitter_name', 'reviewers.name as reviewer_name']);
    }

    public function submit(Request $request, PmProject|PmWorkItem $subject, string $type, array $changes = [], string $source = 'ticket'): object
    {
        $project = $subject instanceof PmProject ? $subject : $subject->project;
        abort_unless($project && PmsAccess::role($request->user(), $project) === 'Business Analyst', 403);
        abort_unless(in_array($type, ['update', 'delete'], true), 422);
        if ($subject instanceof PmWorkItem) {
            abort_unless(in_array($subject->classification_key, ['REQ', 'ENH', 'EHN'], true), 403);
        }
        $reviewer = $this->reviewer($project, $request->user()->id);
        if (! $reviewer) {
            throw ValidationException::withMessages(['reviewer' => 'Assign an active Project Manager to this project before submitting for review.']);
        }
        $kind = $subject instanceof PmProject ? 'project' : 'ticket';
        $review = DB::transaction(function () use ($request, $subject, $project, $type, $changes, $source, $reviewer, $kind): object {
            $locked = $subject::query()->lockForUpdate()->findOrFail($subject->id);
            if ((int) $locked->lock_version !== (int) $request->input('lock_version', $subject->lock_version)) {
                throw ValidationException::withMessages(['lock_version' => 'This record changed. Refresh and retry.']);
            }
            if (DB::table('pm_change_requests')->where('subject_type', $kind)->where('subject_id', $locked->id)
                ->where('status_code', PmsReviewStatus::PENDING)->exists()) {
                throw ValidationException::withMessages(['review' => 'A review request is already pending for this record.']);
            }
            $revision = (int) DB::table('pm_change_requests')->where('subject_type', $kind)
                ->where('subject_id', $locked->id)->max('revision') + 1;
            unset($changes['lock_version'], $changes['reason'], $changes['confirmation']);
            $snapshot = [];
            if ($type === 'update') {
                foreach ($changes as $field => $value) {
                    $before = $locked->getRawOriginal($field);
                    if (is_array($value)) $before = $before === null ? null : json_decode($before, true);
                    if ($before === $value || (is_scalar($before) && is_scalar($value) && (string) $before === (string) $value)) {
                        unset($changes[$field]);
                        continue;
                    }
                    $snapshot[$field] = $before;
                }
                if (! $changes) throw ValidationException::withMessages(['review' => 'Change at least one field before submitting for review.']);
            } else {
                $snapshot = ['key' => $kind === 'project' ? $locked->key : $locked->issue_key,
                    'name' => $kind === 'project' ? $locked->name : $locked->summary];
            }
            $id = DB::table('pm_change_requests')->insertGetId([
                'subject_type' => $kind, 'subject_id' => $locked->id, 'project_id' => $project->id,
                'request_type' => $type, 'revision' => $revision, 'source' => $source, 'base_lock_version' => $locked->lock_version,
                'original_snapshot' => json_encode($snapshot), 'proposed_changes' => json_encode($changes),
                'status_code' => PmsReviewStatus::PENDING, 'submitted_by' => $request->user()->id,
                'reviewer_id' => $reviewer->id, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($request->user()->id, $kind, $locked->id, 'review.submitted', ['review_id' => $id, 'type' => $type]);
            return DB::table('pm_change_requests')->find($id);
        });
        AppNotificationService::notifyUser($reviewer->id, 'PMS review requested',
            $request->user()->name.' requested '.$type.' review for '.($kind === 'project' ? $subject->key : $subject->reference).'.',
            route('pms.change-reviews.index'), 'project_management', $request->user()->id);
        return $review;
    }

    public function decide(Request $request, int $id, string $decision, string $comment): object
    {
        $review = DB::transaction(function () use ($request, $id, $decision, $comment): object {
            $review = DB::table('pm_change_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($review, 404);
            abort_unless((int) $review->status_code === PmsReviewStatus::PENDING, 409);
            $project = PmProject::findOrFail($review->project_id);
            $administrator = PmsAccess::administrator($request->user());
            abort_unless(PmsAccess::allows($request->user(), $project, 'manage')
                && ($administrator || (int) $review->reviewer_id === $request->user()->id), 403);
            abort_if((int) $review->submitted_by === $request->user()->id, 403);
            $subject = $review->subject_type === 'project'
                ? PmProject::query()->lockForUpdate()->findOrFail($review->subject_id)
                : PmWorkItem::query()->lockForUpdate()->findOrFail($review->subject_id);
            abort_unless((int) ($subject instanceof PmProject ? $subject->id : $subject->project_id) === (int) $review->project_id, 409);
            if ($decision === 'approved') {
                if ((int) $subject->lock_version !== (int) $review->base_lock_version) {
                    throw ValidationException::withMessages(['review' => 'The published record changed after submission. Reject this request and ask for a new revision.']);
                }
                $input = json_decode($review->proposed_changes ?: '{}', true) ?: [];
                $input['lock_version'] = $subject->lock_version;
                if ($subject instanceof PmProject && $review->request_type === 'update') {
                    $input += ['name' => $subject->name, 'status' => $subject->status, 'priority' => $subject->priority];
                }
                if ($review->request_type === 'delete') {
                    $input['confirmation'] = $subject instanceof PmProject ? $subject->key : $subject->issue_key;
                }
                $approval = clone $request;
                $approval->replace($input);
                if ($review->request_type === 'delete') {
                    $subject instanceof PmProject
                        ? app(ProjectController::class)->trash($approval, $subject)
                        : ($review->source === 'issue'
                            ? app(IssueController::class)->destroy($approval, $subject)
                            : app(TicketController::class)->destroy($approval, $subject));
                } elseif ($subject instanceof PmProject) {
                    app(ProjectController::class)->update($approval, $subject);
                } elseif ($review->source === 'pic') {
                    app(\App\Http\Controllers\Pms\TicketPicController::class)->sync($subject, $request->user(),
                        array_map('intval', $input['assignee_ids'] ?? []), (int) $subject->lock_version);
                } elseif (in_array($review->source, ['hold', 'resume'], true)) {
                    app(PmsTicketHoldService::class)->apply($subject, $request->user(), [
                        'action' => $review->source, 'lock_version' => $subject->lock_version,
                        'status' => $input['status'] ?? null,
                        'reason' => $input[$review->source === 'hold' ? 'hold_reason' : 'resume_reason'] ?? null,
                        'blocker_id' => $input['hold_blocker_id'] ?? null,
                        'override_unresolved' => $input['override_unresolved'] ?? false,
                    ]);
                } elseif ($review->source === 'issue') {
                    app(IssueController::class)->update($approval, $subject);
                } else {
                    app(TicketController::class)->update($approval, $subject);
                }
            }
            DB::table('pm_change_requests')->where('id', $id)->update([
                'status_code' => $decision === 'approved' ? PmsReviewStatus::APPROVED : PmsReviewStatus::REJECTED,
                'review_comment' => $comment, 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($request->user()->id, $review->subject_type, $review->subject_id,
                'review.'.$decision, ['review_id' => $id, 'comment' => $comment]);
            return DB::table('pm_change_requests')->find($id);
        });
        AppNotificationService::notifyUser($review->submitted_by, 'PMS review '.($decision === 'approved' ? 'approved' : 'rejected'),
            $comment, route('pms.change-reviews.index'), 'project_management', $request->user()->id);
        return $review;
    }

    public function cancel(Request $request, int $id): object
    {
        return DB::transaction(function () use ($request, $id): object {
            $review = DB::table('pm_change_requests')->where('id', $id)->lockForUpdate()->first();
            abort_unless($review, 404);
            abort_unless((int) $review->submitted_by === $request->user()->id, 403);
            abort_unless((int) $review->status_code === PmsReviewStatus::PENDING, 409);
            DB::table('pm_change_requests')->where('id', $id)->update([
                'status_code' => PmsReviewStatus::CANCELLED, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($request->user()->id, $review->subject_type, $review->subject_id,
                'review.cancelled', ['review_id' => $id]);
            return DB::table('pm_change_requests')->find($id);
        });
    }

    private function reviewer(PmProject $project, int $submitterId): ?User
    {
        $ids = DB::table('pm_project_members')->where('project_id', $project->id)
            ->where('role', 'Project Manager')->orderBy('id')->pluck('user_id');
        if ($project->manager_id) $ids->prepend($project->manager_id);
        foreach ($ids->unique() as $id) {
            if ((int) $id === $submitterId) continue;
            $candidate = User::find($id);
            if ($candidate && PmsAccess::eligible($candidate)
                && PmsAccess::role($candidate, $project) === 'Project Manager') return $candidate;
        }
        return null;
    }

    private function audit(int $actor, string $kind, int $subjectId, string $event, array $details): void
    {
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $actor, 'action' => $event, 'subject_type' => $kind,
            'subject_id' => $subjectId, 'details' => json_encode($details), 'created_at' => now(),
        ]);
        if ($kind === 'ticket') DB::table('pm_issue_history')->insert([
            'issue_id' => $subjectId, 'actor_id' => $actor, 'event' => str_replace('.', '_', $event),
            'changes' => json_encode($details), 'created_at' => now(),
        ]);
    }
}
