<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmCycle;
use App\Models\PmGlobalCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Services\PmsReleaseService;
use App\Services\PmsBlockers;
use App\Services\PmsGlobalCycleService;
use App\Support\PmsAccess;
use App\Support\PmsGlobalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReleaseController extends Controller
{
    public function assignCycle(Request $request, PmsGlobalCycleService $service)
    {
        $data = $request->validate(['cycle_id' => 'required|integer|exists:pm_global_cycles,id']);
        $cycle = PmGlobalCycle::findOrFail($data['cycle_id']);
        return app(GlobalCycleController::class)->assignTicket($request, $cycle, $service);
    }

    public function summary(Request $request, PmRelease $release, PmsReleaseService $service)
    {
        abort_unless(PmsAccess::allows($request->user(), $release->project, 'view'), 403);
        $release->load(['project', 'cycle.phases', 'owner:id,name', 'tickets']);
        $readiness = $service->readiness($release);

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('pms.release-summary-pdf', compact('release', 'readiness'))
            ->setPaper('a4')->download($release->release_key.'-summary.pdf');
    }

    public function notes(Request $request, PmRelease $release)
    {
        abort_unless(PmsAccess::allows($request->user(), $release->project, 'view'), 403);

        return response()->streamDownload(fn () => print($release->release_notes ?? ''),
            $release->release_key.'-notes.txt', ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function index(Request $request)
    {
        $projects = $this->projects($request);
        $query = PmRelease::with(['project:id,key,name', 'cycle:id,cycle_key,name', 'globalCycle:id,cycle_key,name', 'owner:id,name'])->whereIn('project_id', $projects->pluck('id'));
        if ($request->filled('project')) {
            $query->where('project_id', $request->integer('project'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $releases = $query->orderByDesc('planned_deploy_date')->paginate(24)->withQueryString();
        $cycles = PmCycle::whereIn('project_id', $projects->pluck('id'))->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->orderByDesc('start_date')->get(['id', 'project_id', 'cycle_key', 'name', 'start_date', 'target_end_date']);
        $members = DB::table('pm_project_members')->join('users', 'users.id', '=', 'pm_project_members.user_id')
            ->join('pms_profiles', 'pms_profiles.user_id', '=', 'users.id')->where('pms_profiles.status', 'active')
            ->whereIn('pm_project_members.project_id', $projects->pluck('id'))->orderBy('users.name')
            ->get(['pm_project_members.project_id', 'users.id', 'users.name']);
        $visibleIds = PmsGlobalAccess::visibleProjectIds($request->user());
        $globalCycles = PmGlobalCycle::with('projects')->whereNull('archived_at')->orderByDesc('start_date')->get();
        $currentCycle = PmGlobalCycle::withCount(['tickets' => fn ($q) => $q->where(fn ($visible) => $visible->whereIn('project_id', $visibleIds)
            ->orWhere(fn ($intake) => $intake->whereNull('project_id')->where('reporter_id', $request->user()->id)))])
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->orderByDesc('start_date')->first();
        $ticketScope = PmWorkItem::where(fn ($q) => $q->whereIn('project_id', $visibleIds)
            ->orWhere(fn ($intake) => $intake->whereNull('project_id')->where('reporter_id', $request->user()->id)));
        $open = fn () => (clone $ticketScope)->whereNotIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES);
        $overview = [
            'active_projects' => $projects->whereNotIn('status', ['Completed', 'Cancelled'])->count(),
            'open_tickets' => $open()->count(),
            'qat' => (clone $ticketScope)->where('classification_key', 'QAT')->count(),
            'req' => (clone $ticketScope)->where('classification_key', 'REQ')->count(),
            'enh' => (clone $ticketScope)->whereIn('classification_key', ['ENH', 'EHN'])->count(),
            'prd' => (clone $ticketScope)->where('classification_key', 'PRD')->count(),
            'critical' => $open()->whereIn('priority', ['P0', 'P1'])->count(),
            'overdue' => $open()->whereDate('due_date', '<', today())->count(),
            'active_milestones' => PmRelease::whereIn('project_id', $projects->pluck('id'))->whereNotIn('status', ['Released','Completed','Cancelled'])->count(),
        ];
        $byPriority = $open()->selectRaw('priority, count(*) as total')->groupBy('priority')->pluck('total', 'priority');
        $byStatus = $open()->selectRaw('status, count(*) as total')->groupBy('status')->orderByDesc('total')->limit(8)->pluck('total', 'status');
        $unassignedCount = (clone $ticketScope)->whereNull('global_cycle_id')->count();
        $unassignedTickets = (clone $ticketScope)->with('project')->whereNull('global_cycle_id')->orderByDesc('updated_at')
            ->paginate(12, ['id','project_id','reference','issue_key','summary','classification_key','priority','status'], 'unassigned_page')->withQueryString();
        $blockedTicketIds = app(PmsBlockers::class)->activeTicketIds();
        $overview['total_cycles'] = $globalCycles->count();
        $overview['active_cycles'] = $globalCycles->where('status', 'Active')->count();
        $overview['upcoming_cycles'] = $globalCycles->where('status', 'Upcoming')->count();
        $overview['completed_tickets'] = (clone $ticketScope)->whereIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)->count();
        $overview['blocked_tickets'] = (clone $ticketScope)->whereIn('id', $blockedTicketIds)->count();
        $overview['unassigned_tickets'] = $unassignedCount;
        $overview['total_work_items'] = $overview['open_tickets'] + $overview['completed_tickets'];
        $overview['completion_percent'] = $overview['total_work_items']
            ? (int) round(100 * $overview['completed_tickets'] / $overview['total_work_items']) : 0;
        $overview['blocked_percent'] = $overview['total_work_items']
            ? (int) round(100 * $overview['blocked_tickets'] / $overview['total_work_items']) : 0;
        $cycleTicketCounts = (clone $ticketScope)->whereNotNull('global_cycle_id')->selectRaw('global_cycle_id, count(*) as total')
            ->groupBy('global_cycle_id')->pluck('total', 'global_cycle_id');
        $cycleDoneCounts = (clone $ticketScope)->whereNotNull('global_cycle_id')->whereIn('status', PmsGlobalCycleService::CLOSED_TICKET_STATUSES)
            ->selectRaw('global_cycle_id, count(*) as total')->groupBy('global_cycle_id')->pluck('total', 'global_cycle_id');
        $cycleBlockedCounts = (clone $ticketScope)->whereNotNull('global_cycle_id')->whereIn('id', $blockedTicketIds)
            ->selectRaw('global_cycle_id, count(*) as total')->groupBy('global_cycle_id')->pluck('total', 'global_cycle_id');
        $byType = (clone $ticketScope)->selectRaw('classification_key, count(*) as total')->groupBy('classification_key')->pluck('total','classification_key');
        $ageBuckets = [
            '0–7 days' => $open()->whereDate('created_at', '>=', today()->subDays(7))->count(),
            '8–30 days' => $open()->whereDate('created_at', '<', today()->subDays(7))->whereDate('created_at', '>=', today()->subDays(30))->count(),
            'Over 30 days' => $open()->whereDate('created_at', '<', today()->subDays(30))->count(),
        ];

        return $request->expectsJson() ? response()->json(compact('releases', 'overview', 'globalCycles', 'cycleTicketCounts', 'cycleDoneCounts', 'cycleBlockedCounts', 'currentCycle', 'byPriority', 'byStatus', 'byType', 'ageBuckets', 'unassignedCount', 'unassignedTickets'))
            : view('pms.releases', compact('releases', 'projects', 'cycles', 'members', 'globalCycles', 'cycleTicketCounts', 'cycleDoneCounts', 'cycleBlockedCounts', 'currentCycle', 'overview', 'byPriority', 'byStatus', 'byType', 'ageBuckets', 'unassignedCount', 'unassignedTickets'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|integer|exists:pm_projects,id',
            'cycle_id' => 'nullable|required_without:global_cycle_id|integer|exists:pm_cycles,id',
            'global_cycle_id' => 'nullable|required_without:cycle_id|integer|exists:pm_global_cycles,id',
            'name' => 'required|string|max:255', 'version' => 'required|string|max:100',
            'release_type' => ['nullable', Rule::in(['standard', 'hotfix'])],
            'description' => 'nullable|string|max:20000', 'owner_id' => 'nullable|integer|exists:users,id',
            'planned_deploy_date' => 'required|date', 'freeze_date' => 'nullable|date', 'release_notes' => 'nullable|string|max:50000',
        ]);
        $project = PmProject::findOrFail($data['project_id']);
        abort_unless(PmsAccess::allows($request->user(), $project, 'manage_release'), 403);
        abort_if($project->archived_at, 422, 'An archived project cannot accept a release.');
        $cycle = !empty($data['cycle_id']) ? $project->cycles()->findOrFail($data['cycle_id']) : null;
        $globalCycle = !empty($data['global_cycle_id']) ? PmGlobalCycle::findOrFail($data['global_cycle_id']) : null;
        if ($globalCycle && ! $globalCycle->projects()->whereKey($project->id)->exists()) {
            throw ValidationException::withMessages(['global_cycle_id' => 'Assign this project to the organization cycle first.']);
        }
        if ($project->releases()->where('version', $data['version'])->exists()) {
            throw ValidationException::withMessages(['version' => 'This project already has that release version.']);
        }
        $this->checkOwner($project, $data['owner_id'] ?? null);
        $release = DB::transaction(function () use ($request, $project, $cycle, $globalCycle, $data): PmRelease {
            $locked = PmProject::lockForUpdate()->findOrFail($project->id);
            $number = $locked->next_release_number++;
            $locked->save();
            $release = new PmRelease();
            $release->forceFill(array_merge($data, [
                'release_key' => $locked->key.'-REL-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                'release_type' => $data['release_type'] ?? 'standard',
                'planned_start_date' => $cycle?->start_date ?? $globalCycle?->start_date, 'status' => 'Planned', 'created_by' => $request->user()->id,
            ]))->save();
            $this->record($request, $release, 'created', null, 'Planned');

            return $release;
        });

        return $request->expectsJson() ? response()->json($release, 201) : redirect()->route('pms.releases.show', $release)->with('success', 'Release created.');
    }

    public function show(Request $request, PmRelease $release, PmsReleaseService $service)
    {
        abort_unless(PmsAccess::allows($request->user(), $release->project, 'view'), 403);
        $release->load(['project.members:id,name', 'cycle.phases', 'globalCycle.stages', 'owner:id,name', 'tickets.developer:id,name', 'tickets.qa:id,name']);
        $readiness = $service->readiness($release);
        $visibleTickets = $release->tickets->filter(function ($ticket) use ($request): bool {
            return (! $request->filled('category') || $ticket->classification_key === $request->category)
                && (! $request->filled('status') || $ticket->status === $request->status)
                && (! $request->filled('priority') || ($request->priority === 'P1' ? in_array($ticket->priority, ['P0','P1'], true) : $ticket->priority === $request->priority))
                && (! $request->filled('assignee') || (int) $ticket->developer_id === $request->integer('assignee'));
        })->values();
        $history = DB::table('pm_release_history')->leftJoin('users', 'users.id', '=', 'pm_release_history.actor_id')
            ->where('release_id', $release->id)->select('pm_release_history.*', 'users.name as actor_name')->orderByDesc('pm_release_history.id')->limit(100)->get();
        $availableTickets = $release->project->issues()->whereNull('release_id')->orderBy('issue_key')->get(['id', 'reference', 'issue_key', 'summary']);
        $blockedTickets = $release->tickets->whereIn('id', app(PmsBlockers::class)->activeTicketIds());

        return $request->expectsJson() ? response()->json(compact('release', 'readiness', 'history', 'blockedTickets')) : view('pms.release', compact('release', 'readiness', 'history', 'availableTickets', 'visibleTickets', 'blockedTickets'));
    }

    public function destroy(Request $request, PmRelease $release)
    {
        abort_unless(PmsAccess::allows($request->user(), $release->project, 'manage'), 403);
        $request->validate(['confirmation' => ['required', Rule::in([$release->release_key])]]);
        DB::transaction(function () use ($request, $release): void {
            $locked = PmRelease::lockForUpdate()->findOrFail($release->id);
            foreach (PmWorkItem::where('release_id', $locked->id)->lockForUpdate()->get() as $ticket) {
                $ticket->release_id = null;
                $ticket->save();
                DB::table('pm_issue_history')->insert([
                    'issue_id' => $ticket->id, 'actor_id' => $request->user()->id,
                    'event' => 'release_changed', 'reason' => 'Release deleted; ticket returned to unassigned release scope',
                    'changes' => json_encode(['from_release_id' => $locked->id, 'to_release_id' => null]), 'created_at' => now(),
                ]);
            }
            $this->record($request, $locked, 'deleted', $locked->status, null, 'Soft deleted; tickets retained', ['soft_delete' => true]);
            $locked->forceFill(['deleted_by' => $request->user()->id])->save();
            $locked->delete();
        });

        return $request->expectsJson() ? response()->json(['deleted' => true])
            : redirect()->route('pms.releases.index')->with('success', 'Release deleted. Its tickets remain in the project.');
    }

    public function update(Request $request, PmRelease $release)
    {
        abort_unless(PmsAccess::allows($request->user(), $release->project, 'edit_release')
            || PmsAccess::allows($request->user(), $release->project, 'manage_release'), 403);
        abort_if(in_array($release->status, ['Released', 'Completed', 'Cancelled'], true), 422, 'This release is locked.');
        $data = $request->validate([
            'name' => 'sometimes|string|max:255', 'description' => 'nullable|string|max:20000',
            'owner_id' => 'nullable|integer|exists:users,id', 'planned_start_date' => 'nullable|date',
            'planned_deploy_date' => 'sometimes|date',
            'freeze_date' => 'nullable|date', 'release_notes' => 'nullable|string|max:50000',
            'known_issues' => 'nullable|string|max:50000', 'reason' => 'nullable|string|max:5000',
        ]);
        $this->checkOwner($release->project, $data['owner_id'] ?? null);
        if (isset($data['planned_deploy_date']) && $release->planned_deploy_date->toDateString() !== $data['planned_deploy_date'] && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Explain every planned deployment date change.']);
        }
        $reason = $data['reason'] ?? null;
        unset($data['reason']);
        DB::transaction(function () use ($request, $release, $data, $reason): void {
            $before = $release->only(array_keys($data));
            $release->update($data);
            $this->record($request, $release, 'updated', $release->status, $release->status, $reason, ['before' => $before, 'after' => $data]);
        });

        return $request->expectsJson() ? response()->json($release->fresh()) : back()->with('success', 'Release updated.');
    }

    public function draftNotes(Request $request, PmRelease $release, PmsReleaseService $service)
    {
        abort_unless(PmsAccess::allows($request->user(), $release->project, 'edit_release')
            || PmsAccess::allows($request->user(), $release->project, 'manage_release'), 403);
        abort_if(in_array($release->status, ['Released', 'Completed', 'Cancelled'], true), 422);
        $notes = $service->draftNotes($release);
        $release->update(['release_notes' => $notes]);
        $this->record($request, $release, 'notes_generated', $release->status, $release->status);

        return $request->expectsJson() ? response()->json(['release_notes' => $notes]) : back()->with('success', 'Release notes draft generated from completed tickets.');
    }

    public function addTicket(Request $request, PmRelease $release)
    {
        abort_unless(PmsAccess::allows($request->user(), $release->project, 'edit_release')
            || PmsAccess::allows($request->user(), $release->project, 'manage_release'), 403);
        abort_if(in_array($release->status, ['Ready for Release', 'Approved', 'Released', 'Completed', 'Cancelled'], true), 422);
        $data = $request->validate(['ticket_id' => 'required|integer|exists:pm_work_items,id', 'reason' => 'nullable|string|max:5000']);
        $ticket = PmWorkItem::findOrFail($data['ticket_id']);
        abort_unless((int) $ticket->project_id === $release->project_id, 422, 'Ticket and release must belong to the same project.');
        abort_if($ticket->release && in_array($ticket->release->status, ['Ready for Release', 'Approved', 'Released', 'Completed', 'Cancelled'], true), 422, 'The current release scope is locked.');
        if ($ticket->release_id && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Explain why this ticket is moving from its previous release.']);
        }
        if ($release->global_cycle_id && $ticket->global_cycle_id && (int) $ticket->global_cycle_id !== (int) $release->global_cycle_id && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Explain moving this ticket to a different organization cycle.']);
        }
        DB::transaction(function () use ($request, $release, $ticket, $data): void {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless((int) $locked->project_id === $release->project_id, 422);
            $previous = $locked->release_id;
            abort_if($locked->release && in_array($locked->release->status, ['Ready for Release', 'Approved', 'Released', 'Completed', 'Cancelled'], true), 422, 'The current release scope is locked.');
            if ($previous && blank($data['reason'] ?? null)) {
                throw ValidationException::withMessages(['reason' => 'Explain why this ticket is moving from its previous release.']);
            }
            $locked->release_id = $release->id;
            $locked->cycle_id = $release->cycle_id;
            if ($release->global_cycle_id) {
                if ((int) $locked->global_cycle_id !== (int) $release->global_cycle_id) {
                    $locked->global_cycle_stage_id = null;
                }
                $locked->original_global_cycle_id ??= $release->global_cycle_id;
                $locked->global_cycle_id = $release->global_cycle_id;
            }
            $locked->lock_version++;
            $locked->save();
            DB::table('pm_issue_history')->insert(['issue_id' => $ticket->id, 'actor_id' => $request->user()->id, 'event' => 'release_changed', 'reason' => $data['reason'] ?? null, 'changes' => json_encode(['from_release_id' => $previous, 'to_release_id' => $release->id]), 'created_at' => now()]);
            $this->record($request, $release, 'ticket_added', $release->status, $release->status, $data['reason'] ?? null, ['ticket_id' => $ticket->id, 'from_release_id' => $previous]);
        });
        app(\App\Services\PmsTicketNotificationService::class)->notifyParticipants($ticket->fresh(), $request->user(),
            "{$ticket->reference} moved to {$release->release_key}", $ticket->summary);

        return $request->expectsJson() ? response()->json($ticket->fresh()) : back()->with('success', 'Ticket linked to release.');
    }

    public function advance(Request $request, PmRelease $release, PmsReleaseService $service)
    {
        abort_unless(PmsAccess::allows($request->user(), $release->project, 'manage_release'), 403);
        $data = $request->validate(['status' => ['required', Rule::in(['Development', 'Internal Testing', 'QAT Testing', 'Stabilization', 'Ready for Release', 'On Hold', 'Cancelled'])], 'reason' => 'nullable|string|max:5000']);
        $transitions = [
            'Planned' => ['Development', 'On Hold', 'Cancelled'],
            'Development' => ['Internal Testing', 'On Hold', 'Cancelled'],
            'Internal Testing' => ['QAT Testing', 'On Hold', 'Cancelled'],
            'QAT Testing' => ['Stabilization', 'On Hold', 'Cancelled'],
            'Stabilization' => ['Ready for Release', 'On Hold', 'Cancelled'],
            'Ready for Release' => ['Stabilization', 'On Hold', 'Cancelled'],
            'On Hold' => ['Development', 'Internal Testing', 'QAT Testing', 'Stabilization', 'Cancelled'],
        ];
        if (! in_array($data['status'], $transitions[$release->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'This release transition is not allowed.']);
        }
        if (in_array($data['status'], ['On Hold', 'Cancelled'], true) && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => 'Explain this release decision.']);
        }
        if ($data['status'] === 'Ready for Release') {
            $failed = collect($service->readiness($release)['checks'])->filter(fn ($check) => ! $check['passed'] && $check['key'] !== 'approval');
            if ($failed->isNotEmpty()) {
                $authorizedOverride = PmsAccess::administrator($request->user()) || PmsAccess::role($request->user(), $release->project) === 'Release Manager';
                if (! $authorizedOverride || blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['status' => 'Release checks must pass or an authorized Release Manager must document an exception: '.$failed->pluck('label')->implode(', ')]);
                }
            }
        }
        DB::transaction(function () use ($request, $release, $data): void {
            $from = $release->status;
            $release->update(['status' => $data['status']]);
            $this->record($request, $release, 'status_changed', $from, $data['status'], $data['reason'] ?? null);
        });

        return $request->expectsJson() ? response()->json($release->fresh()) : back()->with('success', 'Release status updated.');
    }

    public function approve(Request $request, PmRelease $release, PmsReleaseService $service)
    {
        abort_unless(PmsAccess::administrator($request->user()) || PmsAccess::role($request->user(), $release->project) === 'Release Manager', 403);
        abort_unless($release->status === 'Ready for Release', 422, 'Move the release to Ready for Release first.');
        $data = $request->validate(['override_reason' => 'nullable|string|max:5000']);
        $failed = collect($service->readiness($release)['checks'])->filter(fn ($check) => ! $check['passed'] && $check['key'] !== 'approval');
        if ($failed->isNotEmpty() && blank($data['override_reason'] ?? null)) {
            throw ValidationException::withMessages(['override_reason' => 'Document an authorized exception for each failed release check.']);
        }
        DB::transaction(function () use ($request, $release, $data): void {
            $release->forceFill(['status' => 'Approved', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'risk_acceptance' => $data['override_reason'] ?? null])->save();
            $this->record($request, $release, 'approved', 'Ready for Release', 'Approved', $data['override_reason'] ?? null);
        });

        return $request->expectsJson() ? response()->json($release->fresh()) : back()->with('success', 'Deployment approved.');
    }

    public function deploy(Request $request, PmRelease $release, PmsReleaseService $service)
    {
        abort_unless(PmsAccess::administrator($request->user()) || PmsAccess::role($request->user(), $release->project) === 'Release Manager', 403);
        abort_unless($release->status === 'Approved', 422, 'Approval is required before recording deployment.');
        $data = $request->validate(['deployed_build' => 'required|string|max:255', 'actual_deployed_at' => 'required|date', 'known_issues' => 'nullable|string|max:50000']);
        if (blank($release->release_notes)) {
            throw ValidationException::withMessages(['release_notes' => 'Record release notes before deployment.']);
        }
        $readiness = $service->readiness($release);
        if ($readiness['state'] !== 'Ready' && blank($release->risk_acceptance)) {
            throw ValidationException::withMessages(['status' => 'Release checks changed after approval. Review and approve again.']);
        }
        DB::transaction(function () use ($request, $release, $data, $readiness): void {
            $release->forceFill($data + ['status' => 'Released'])->save();
            $this->record($request, $release, 'deployed', 'Approved', 'Released', null, ['build' => $data['deployed_build'], 'readiness' => $readiness]);
        });

        return $request->expectsJson() ? response()->json($release->fresh()) : back()->with('success', 'Production deployment recorded.');
    }

    public function complete(Request $request, PmRelease $release)
    {
        abort_unless(PmsAccess::administrator($request->user()) || PmsAccess::role($request->user(), $release->project) === 'Release Manager', 403);
        abort_unless($release->status === 'Released', 422);
        DB::transaction(function () use ($request, $release): void {
            $release->forceFill(['status' => 'Completed', 'completed_by' => $request->user()->id, 'completed_at' => now()])->save();
            $this->record($request, $release, 'completed', 'Released', 'Completed');
        });

        return $request->expectsJson() ? response()->json($release->fresh()) : back()->with('success', 'Release completed.');
    }

    private function checkOwner(PmProject $project, ?int $ownerId): void
    {
        if ($ownerId && (! $project->members()->where('users.id', $ownerId)->exists() || ! PmsAccess::eligible(User::find($ownerId)))) {
            throw ValidationException::withMessages(['owner_id' => 'Release owner must be an active PMS project member.']);
        }
    }

    private function projects(Request $request)
    {
        $query = PmProject::whereNull('archived_at');
        if (! PmsAccess::administrator($request->user())) {
            $query->whereHas('members', fn ($q) => $q->where('users.id', $request->user()->id));
        }

        return $query->orderBy('name')->get(['id', 'key', 'name', 'status']);
    }

    private function record(Request $request, PmRelease $release, string $event, ?string $from, ?string $to, ?string $reason = null, array $details = []): void
    {
        DB::table('pm_release_history')->insert([
            'release_id' => $release->id, 'actor_id' => $request->user()->id, 'event' => $event,
            'from_status' => $from, 'to_status' => $to, 'reason' => $reason,
            'details' => json_encode($details), 'created_at' => now(),
        ]);
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $request->user()->id, 'action' => 'release.'.$event, 'subject_type' => 'release', 'subject_id' => $release->id,
            'details' => json_encode($details + ['reason' => $reason]), 'created_at' => now(),
        ]);
    }
}
