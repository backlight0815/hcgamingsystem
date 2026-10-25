<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\PmsChangeReviewService;
use App\Services\PmsTicketActivity;
use App\Support\PmsAccess;
use App\Support\PmsTicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TicketPicController extends Controller
{
    public function index(Request $request, PmWorkItem $ticket)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 403);
        return response()->json(['success' => true, 'data' => [
            'ticket_id' => $ticket->id, 'assignee_ids' => $this->ids($ticket),
            'assignees' => $this->assignees($ticket), 'eligible_members' => $this->eligibleMembers($ticket),
        ]]);
    }

    public function update(Request $request, PmWorkItem $ticket)
    {
        $this->authorizeEdit($request, $ticket);
        $request->merge(['assignee_ids' => $request->input('assignee_ids', [])]);
        $data = $request->validate([
            'assignee_ids' => 'present|array|max:50',
            'assignee_ids.*' => 'required|integer|distinct|exists:users,id',
            'lock_version' => 'required|integer|min:0',
        ]);
        $ids = array_map('intval', $data['assignee_ids']);
        $this->validateEligible($ticket, $ids);
        if ((int) $data['lock_version'] !== (int) $ticket->lock_version) {
            throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and retry.']);
        }
        $current = $this->ids($ticket);
        sort($ids);
        if ($ids === $current) {
            return $request->expectsJson() ? response()->json(['success' => true,
                'message' => 'Person-in-Charge already up to date',
                'data' => ['ticket_id' => $ticket->id, 'assignee_ids' => $current, 'assignees' => $this->assignees($ticket)]])
                : back()->with('success', 'Person-in-Charge already up to date.');
        }
        if ($ticket->project && in_array($ticket->classification_key, ['REQ', 'ENH', 'EHN'], true)
            && PmsAccess::role($request->user(), $ticket->project) === 'Business Analyst') {
            $review = app(PmsChangeReviewService::class)->submit($request, $ticket, 'update',
                ['assignee_ids' => $ids], 'pic');
            return $request->expectsJson() ? response()->json(['success' => true, 'pending' => true, 'review_id' => $review->id], 202)
                : back()->with('success', 'PIC assignment submitted for Project Manager review.');
        }
        $result = $this->sync($ticket, $request->user(), $ids, (int) $data['lock_version']);
        return $request->expectsJson() ? response()->json(['success' => true,
            'message' => 'Person-in-Charge updated successfully', 'data' => $result])
            : back()->with('success', 'Person-in-Charge updated successfully.');
    }

    public function destroy(Request $request, PmWorkItem $ticket, int $user)
    {
        $this->authorizeEdit($request, $ticket);
        $ids = array_values(array_diff($this->ids($ticket), [$user]));
        if ($ticket->project && in_array($ticket->classification_key, ['REQ', 'ENH', 'EHN'], true)
            && PmsAccess::role($request->user(), $ticket->project) === 'Business Analyst') {
            $review = app(PmsChangeReviewService::class)->submit($request, $ticket, 'update',
                ['assignee_ids' => $ids], 'pic');
            return $request->expectsJson() ? response()->json(['success' => true, 'pending' => true, 'review_id' => $review->id], 202)
                : back()->with('success', 'PIC removal submitted for Project Manager review.');
        }
        $result = $this->sync($ticket, $request->user(), $ids, (int) $ticket->lock_version);
        return $request->expectsJson() ? response()->json(['success' => true, 'data' => $result])
            : back()->with('success', 'PIC removed.');
    }

    public function sync(PmWorkItem $ticket, User $actor, array $ids, int $version): array
    {
        $this->validateEligible($ticket, $ids);
        [$added, $result] = DB::transaction(function () use ($ticket, $actor, $ids, $version): array {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless(PmsTicketAccess::manage($actor, $locked)
                || $locked->project && PmsAccess::allows($actor, $locked->project, 'edit_ticket'), 403);
            if ((int) $locked->lock_version !== $version) {
                throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and retry.']);
            }
            $before = $this->ids($locked);
            $added = array_values(array_diff($ids, $before));
            $removed = array_values(array_diff($before, $ids));
            if ($added || $removed) {
                DB::table('pm_ticket_contributors')->where('issue_id', $locked->id)
                    ->where('assignment_type', 'pic')->whereIn('user_id', $removed)->delete();
                foreach ($added as $id) {
                    DB::table('pm_ticket_contributors')->insert([
                        'issue_id' => $locked->id, 'user_id' => $id, 'assignment_type' => 'pic',
                        'assigned_by' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $locked->lock_version++;
                $locked->save();
                $changes = ['before' => $before, 'after' => $ids, 'added' => $added, 'removed' => $removed];
                DB::table('pm_issue_history')->insert([
                    'issue_id' => $locked->id, 'actor_id' => $actor->id, 'event' => 'pic_assignment',
                    'changes' => json_encode($changes), 'created_at' => now(),
                ]);
                DB::table('pm_audit_logs')->insert([
                    'actor_id' => $actor->id, 'action' => 'ticket.pic_assignment',
                    'subject_type' => 'ticket', 'subject_id' => $locked->id,
                    'details' => json_encode($changes), 'created_at' => now(),
                ]);
                PmsTicketActivity::record($locked->id, 'assignment', 'pic', $before, $ids, $changes, $actor);
            }
            return [$added, ['ticket_id' => $locked->id, 'assignee_ids' => $this->ids($locked),
                'assignees' => $this->assignees($locked), 'lock_version' => $locked->lock_version]];
        });
        foreach ($added as $id) {
            if ($id !== $actor->id) {
                AppNotificationService::notifyUser($id, 'Assigned as PIC to '.$ticket->reference,
                    $ticket->summary, route('pms.tickets.show', $ticket), 'project_management', $actor->id);
            }
        }
        return $result;
    }

    private function authorizeEdit(Request $request, PmWorkItem $ticket): void
    {
        abort_unless($ticket->project && PmsTicketAccess::view($request->user(), $ticket)
            && (PmsTicketAccess::manage($request->user(), $ticket)
                || PmsAccess::allows($request->user(), $ticket->project, 'edit_ticket')), 403);
    }

    private function validateEligible(PmWorkItem $ticket, array $ids): void
    {
        if (! $ticket->project_id) {
            throw ValidationException::withMessages(['assignee_ids' => 'Assign this ticket to a project first.']);
        }
        $valid = $this->eligibleMembers($ticket)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $existing = DB::table('pm_ticket_contributors as pics')
            ->join('pm_project_members as members', function ($join) use ($ticket): void {
                $join->on('members.user_id', '=', 'pics.user_id')->where('members.project_id', '=', $ticket->project_id);
            })
            ->where('pics.issue_id', $ticket->id)->where('pics.assignment_type', 'pic')
            ->pluck('pics.user_id')->map(fn ($id) => (int) $id)->all();
        $valid = array_unique(array_merge($valid, $existing));
        if (count($ids) !== count(array_unique($ids)) || array_diff($ids, $valid)) {
            throw ValidationException::withMessages(['assignee_ids' => 'Choose active members of this ticket’s project.']);
        }
    }

    private function ids(PmWorkItem $ticket): array
    {
        return DB::table('pm_ticket_contributors')->where('issue_id', $ticket->id)
            ->where('assignment_type', 'pic')->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    private function assignees(PmWorkItem $ticket)
    {
        return DB::table('pm_ticket_contributors as pics')->join('users', 'users.id', '=', 'pics.user_id')
            ->leftJoin('pms_profiles as profiles', 'profiles.user_id', '=', 'users.id')
            ->where('pics.issue_id', $ticket->id)->where('pics.assignment_type', 'pic')
            ->orderBy('users.name')->get(['users.id', 'users.name', 'profiles.job_position', 'profiles.department']);
    }

    private function eligibleMembers(PmWorkItem $ticket)
    {
        $query = DB::table('pm_project_members as members')->join('users', 'users.id', '=', 'members.user_id')
            ->join('pms_profiles as profiles', 'profiles.user_id', '=', 'users.id')
            ->where('members.project_id', $ticket->project_id)->where('users.status', 1)
            ->where('profiles.status', 'active');
        if (Schema::hasTable('project_management_users')) {
            $query->whereNotIn('users.id', DB::table('project_management_users')
                ->where('status', '<>', 'active')->select('user_id'));
        }
        return $query->orderBy('users.name')
            ->get(['users.id', 'users.name', 'profiles.job_position', 'profiles.department']);
    }
}
