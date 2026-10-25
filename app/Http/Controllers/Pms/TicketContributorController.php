<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsPosition;
use App\Support\PmsTicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketContributorController extends Controller
{
    public function index(Request $request, PmWorkItem $ticket)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 403);
        return response()->json($this->contributors($ticket));
    }

    public function store(Request $request, PmWorkItem $ticket)
    {
        $this->authorizeEdit($request, $ticket);
        $data = $request->validate([
            'assignment_type' => ['required', Rule::in(['developer', 'qa'])],
            'user_ids' => 'required|array|min:1|max:50',
            'user_ids.*' => 'required|integer|distinct|exists:users,id',
        ]);
        $users = User::with('pmsProfile')->whereIn('id', $data['user_ids'])->get();
        if ($users->count() !== count($data['user_ids'])) {
            throw ValidationException::withMessages(['user_ids' => 'Choose valid active project members.']);
        }
        foreach ($users as $user) {
            if (! PmsPosition::eligible($user, $data['assignment_type'], $ticket->project_id)) {
                throw ValidationException::withMessages(['user_ids' => 'Each selected person needs the matching job position and project membership.']);
            }
        }
        $added = DB::transaction(function () use ($request, $ticket, $data): int {
            $rows = array_map(fn ($id) => [
                'issue_id' => $ticket->id, 'user_id' => $id,
                'assignment_type' => $data['assignment_type'], 'assigned_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ], $data['user_ids']);
            $added = DB::table('pm_ticket_contributors')->insertOrIgnore($rows);
            if ($added) $this->audit($ticket, $request->user()->id, 'contributors_assigned', $data);
            return $added;
        });

        return $request->expectsJson() ? response()->json(['added' => $added, 'contributors' => $this->contributors($ticket)])
            : back()->with('success', $added.' contributor(s) assigned.');
    }

    public function destroy(Request $request, PmWorkItem $ticket, int $user)
    {
        $this->authorizeEdit($request, $ticket);
        $data = $request->validate(['assignment_type' => ['required', Rule::in(['developer', 'qa'])]]);
        $removed = DB::transaction(function () use ($request, $ticket, $user, $data): int {
            $removed = DB::table('pm_ticket_contributors')->where('issue_id', $ticket->id)
                ->where('user_id', $user)->where('assignment_type', $data['assignment_type'])->delete();
            if ($removed) $this->audit($ticket, $request->user()->id, 'contributor_removed',
                ['user_id' => $user, 'assignment_type' => $data['assignment_type']]);
            return $removed;
        });

        return $request->expectsJson() ? response()->json(['removed' => (bool) $removed]) : back()->with('success', 'Contributor removed.');
    }

    private function authorizeEdit(Request $request, PmWorkItem $ticket): void
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 403);
        abort_unless($ticket->project_id && $ticket->project
            && (PmsTicketAccess::manage($request->user(), $ticket)
                || PmsAccess::allows($request->user(), $ticket->project, 'edit_ticket')), 403);
    }

    private function contributors(PmWorkItem $ticket)
    {
        return DB::table('pm_ticket_contributors')->join('users', 'users.id', '=', 'pm_ticket_contributors.user_id')
            ->leftJoin('pms_profiles', 'pms_profiles.user_id', '=', 'users.id')
            ->where('issue_id', $ticket->id)->orderBy('users.name')
            ->get(['pm_ticket_contributors.user_id', 'pm_ticket_contributors.assignment_type',
                'users.name', 'pms_profiles.department', 'pms_profiles.job_position']);
    }

    private function audit(PmWorkItem $ticket, int $actor, string $event, array $details): void
    {
        DB::table('pm_issue_history')->insert([
            'issue_id' => $ticket->id, 'actor_id' => $actor, 'event' => $event,
            'changes' => json_encode($details), 'created_at' => now(),
        ]);
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $actor, 'action' => 'ticket.'.$event, 'subject_type' => 'ticket',
            'subject_id' => $ticket->id, 'details' => json_encode($details), 'created_at' => now(),
        ]);
    }
}
