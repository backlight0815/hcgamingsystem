<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmProject;
use App\Models\PmGlobalCycle;
use App\Models\PmRelease;
use App\Models\PmTicketAttachment;
use App\Models\PmWikiPage;
use App\Models\PmsProfile;
use App\Models\User;
use App\Support\PmsAccess;
use App\Support\PmsPosition;
use App\Support\PmsProjectDescription;
use App\Support\PmsWikiAccess;
use App\Services\PmsChangeReviewService;
use App\Services\PmsProjectOwnerService;
use App\Services\PmsProjectPlanningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    private const STATUSES = ['Planned', 'Approved', 'Active', 'On Hold', 'Completed', 'Cancelled'];

    public function index(Request $request)
    {
        $query = PmProject::query()->with(['members:id,name', 'owner:id,name']);
        if (! PmsAccess::administrator($request->user())) {
            $query->whereHas('members', fn ($q) => $q->where('users.id', $request->user()->id));
        }
        if ($request->filled('q')) {
        $query->where(function ($q) use ($request) {
        $q->where('name', 'like', '%'.$request->q.'%')->orWhere('key', 'like', '%'.$request->q.'%')
            ->orWhere('project_reference', 'like', '%'.$request->q.'%');
        });
        }
        if (in_array($request->status, self::STATUSES, true)) {
        $query->where('status', $request->status);
        }
        if ($request->boolean('overdue')) {
        $query->whereNotIn('status', ['Completed', 'Cancelled'])->whereDate('target_date', '<', today());
        }
        if ($request->boolean('trash')) {
            abort_unless(PmsAccess::administrator($request->user()), 403);
            $query->onlyTrashed();
        } elseif ($request->boolean('archived')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }
        $sort = in_array($request->input('sort_by'), ['project_reference', 'name', 'owner', 'status', 'updated_at'], true)
            ? $request->input('sort_by') : 'updated_at';
        $direction = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        if ($sort === 'project_reference') $query->orderBy('id', $direction);
        elseif ($sort === 'owner') $query->orderBy(User::select('name')->whereColumn('users.id', 'pm_projects.owner_id')->limit(1), $direction);
        else $query->orderBy($sort, $direction);
        $projects = $query->orderBy('id', $direction)->paginate(20)->withQueryString();

        $ownerCandidates = !$request->expectsJson() && PmsAccess::administrator($request->user())
            ? app(PmsProjectOwnerService::class)->candidates() : collect();
        $cycleOptions = !$request->expectsJson() && PmsAccess::administrator($request->user())
            ? PmGlobalCycle::whereNull('archived_at')->whereNotIn('status', ['Cancelled', 'Completed'])
                ->orderByDesc('start_date')->get() : collect();
        $releaseOptions = !$request->expectsJson() && PmsAccess::administrator($request->user())
            ? PmRelease::whereNotIn('status', ['Cancelled'])->whereNull('cycle_id')
                ->orderByDesc('planned_deploy_date')->get() : collect();

        return $request->expectsJson() ? response()->json($projects) : view('pms.projects', compact('projects', 'ownerCandidates', 'cycleOptions', 'releaseOptions'));
    }

    public function store(Request $request)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $data = $request->validate([
            'key' => ['required', 'string', 'regex:/^[A-Za-z][A-Za-z0-9]{1,11}$/', 'unique:pm_projects,key'],
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000',
            'objective' => 'nullable|string|max:10000', 'category' => 'nullable|string|max:100',
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'priority' => ['required', Rule::exists('pm_priorities', 'code')->where('active', true)],
            'planned_start_date' => 'nullable|date', 'target_date' => 'nullable|date',
            'related_module' => 'nullable|string|max:40',
            'manager_id' => 'nullable|integer|exists:users,id',
            'owner_id' => 'nullable|integer|exists:users,id',
            'current_global_cycle_id' => 'nullable|integer|exists:pm_global_cycles,id',
            'current_release_id' => 'nullable|integer|exists:pm_releases,id',
        ]);
        app(PmsProjectOwnerService::class)->validate($data['owner_id'] ?? $request->user()->id);
        [$cycle, $release] = app(PmsProjectPlanningService::class)->validateSelection(
            $data['current_global_cycle_id'] ?? null, $data['current_release_id'] ?? null, null, $request->user());
        if (! empty($data['manager_id']) && ! PmsPosition::eligible(User::with('pmsProfile')->find($data['manager_id']), 'manager')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['manager_id' => 'Choose an active PMS manager.']);
        }
        if (isset($data['description'])) $data['description'] = PmsProjectDescription::canonicalize($data['description']);
        if (array_key_exists('status', $data) && $data['status'] === null) unset($data['status']);
        unset($data['current_global_cycle_id'], $data['current_release_id']);
        $project = DB::transaction(function () use ($request, $data, $cycle, $release) {
            $project = PmProject::create(array_merge($data, [
                'key' => strtoupper($data['key']), 'owner_id' => $data['owner_id'] ?? $request->user()->id,
                'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
                'completed_at' => ($data['status'] ?? null) === 'Completed' ? today() : null,
            ]));
            $project->members()->attach($request->user()->id, ['role' => 'Project Administrator']);
            if ((int) $project->owner_id !== (int) $request->user()->id) {
                $project->members()->syncWithoutDetaching([$project->owner_id => ['role' => 'Viewer']]);
            }
            if (! empty($data['manager_id']) && (int) $data['manager_id'] !== $request->user()->id) {
                $project->members()->syncWithoutDetaching([$data['manager_id'] => ['role' => 'Project Manager']]);
            }
            PmsProfile::updateOrCreate(['user_id' => $request->user()->id], [
                'status' => 'active', 'approved_by' => $request->user()->id, 'approved_at' => now(), 'deactivated_at' => null,
            ]);
            $this->audit($request, 'project.created', $project);
            app(PmsProjectPlanningService::class)->apply($project, $cycle, $release, $request->user());

            return $project->fresh();
        });

        return $request->expectsJson() ? response()->json($project, 201) : redirect()->route('pms.projects.show', $project)->with('success', 'Project created.');
    }

    public function show(Request $request, PmProject $project)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'view'), 403);
        $project->load(['owner:id,name', 'currentGlobalCycle', 'currentRelease', 'globalCycles', 'associatedReleases',
            'members:id,name,email', 'cycles' => fn ($query) => $query->latest('start_date')->limit(8),
            'releases' => fn ($query) => $query->latest('planned_deploy_date')->limit(8)]);
        $issues = $project->issues()->with(['developer:id,name', 'qa:id,name'])->latest()->paginate(20);
        $users = PmsAccess::allows($request->user(), $project, 'manage')
            ? User::with('pmsProfile')->where('status', 1)->whereHas('pmsProfile', fn ($query) => $query->where('status', 'active'))->select('id', 'name', 'email')->orderBy('name')->limit(500)->get()
            : collect();
        $ownerCandidates = !$request->expectsJson() && PmsAccess::allows($request->user(), $project, 'edit_project')
            ? app(PmsProjectOwnerService::class)->candidates() : collect();
        $cycleOptions = PmsAccess::manageCycle($request->user(), $project)
            ? PmGlobalCycle::whereNull('archived_at')->whereNotIn('status', ['Cancelled', 'Completed'])
                ->orderByDesc('start_date')->get() : collect();
        $releaseOptions = PmsAccess::manageCycle($request->user(), $project)
            ? PmRelease::with('cycle:id,global_cycle_id')->whereNotIn('status', ['Cancelled'])->where(function ($query) use ($project): void {
                $query->whereNull('cycle_id')->orWhere('project_id', $project->id);
            })->where(function ($query) use ($request, $project): void {
                $query->whereIn('project_id', \App\Support\PmsGlobalAccess::visibleProjectIds($request->user()))
                    ->orWhere('id', $project->current_release_id ?? 0);
            })
                ->orderByDesc('planned_deploy_date')->get() : collect();
        if (!$request->expectsJson() && PmsAccess::allows($request->user(), $project, 'edit_project')
            && $project->owner_id && !$ownerCandidates->contains('id', $project->owner_id)) {
            $currentOwner = User::with('pmsProfile')->find($project->owner_id);
            if ($currentOwner) {
                $currentOwner->setAttribute('is_historical_owner', true);
                $currentOwner->setAttribute('historical_owner_reason', app(PmsProjectOwnerService::class)->prohibited($currentOwner)
                    ? 'ineligible position' : 'inactive');
                $ownerCandidates->prepend($currentOwner);
            }
        }
        $impact = ['issues' => $project->issues()->count(), 'cycles' => $project->cycles()->count(), 'members' => $project->members()->count()];
        $metrics = [
            'tickets' => $project->issues()->count(),
            'open' => $project->issues()->whereIn('status', ['Open', 'Planned', 'Backlog'])->count(),
            'in_progress' => $project->issues()->whereIn('status', ['In Progress', 'Development', 'Internal Testing', 'QAT in Progress', 'Code Review'])->count(),
            'completed' => $project->issues()->whereIn('status', ['Completed', 'Production Done', 'QAT Done'])->count(),
            'overdue' => $project->issues()->whereDate('due_date', '<', today())->whereNotIn('status', ['Completed', 'Production Done', 'Cancelled'])->count(),
            'active_releases' => $project->associatedReleases()->whereNotIn('status', ['Completed', 'Cancelled'])->count(),
            'cycles' => $project->cycles()->count() + $project->globalCycles()->count(),
        ];
        $files = PmTicketAttachment::with(['issue:id,project_id,reference,issue_key,summary', 'uploader:id,name'])
            ->where('category', 'evidence')
            ->whereHas('issue', fn ($query) => $query->where('project_id', $project->id))
            ->latest()->paginate(12, ['*'], 'files_page')->withQueryString();
        $attachmentTargets = $project->issues()->orderByDesc('id')->limit(100)->get(['id', 'reference', 'issue_key', 'summary']);
        $activity = DB::table('pm_audit_logs')->leftJoin('users', 'users.id', '=', 'pm_audit_logs.actor_id')
            ->where('pm_audit_logs.subject_type', 'project')->where('pm_audit_logs.subject_id', $project->id)
            ->orderByDesc('pm_audit_logs.created_at')->limit(30)
            ->get(['pm_audit_logs.action', 'pm_audit_logs.details', 'pm_audit_logs.actor_id',
                'pm_audit_logs.created_at', 'users.name as actor_name']);
        $descriptionHtml = PmsProjectDescription::render($project->description);
        $wikiPages = PmWikiPage::with(['space', 'project'])->where(function ($query) use ($project) {
            $query->where('project_id', $project->id)
                ->orWhere(function ($linked) use ($project) {
                    $linked->whereNull('project_id')->whereIn('id', DB::table('pm_wiki_links')
                        ->where('project_id', $project->id)->select('page_id'));
                });
        })
            ->get()->filter(fn ($page) => PmsWikiAccess::viewPage($request->user(), $page));
        $changeReviews = app(PmsChangeReviewService::class)->history('project', $project->id);

        return $request->expectsJson() ? response()->json($project) : view('pms.project', compact('project', 'issues', 'users', 'ownerCandidates', 'cycleOptions', 'releaseOptions', 'impact', 'wikiPages', 'metrics', 'files', 'activity', 'descriptionHtml', 'attachmentTargets', 'changeReviews'));
    }

    public function previewDescription(Request $request, PmProject $project)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'edit_project'), 403);
        abort_if($project->archived_at, 422, 'Archived projects cannot be edited.');
        $data = $request->validate(['description' => 'nullable|string|max:10000']);

        return response()->json(['html' => PmsProjectDescription::render($data['description'] ?? '')]);
    }

    public function storeAttachments(Request $request, PmProject $project, \App\Services\PmsAttachmentService $attachments)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'view') && ! $project->archived_at, 403);
        $data = $request->validate([
            'ticket_id' => 'nullable|required_without:ticket_key|integer|exists:pm_work_items,id',
            'ticket_key' => 'nullable|required_without:ticket_id|string|max:48',
            'attachments' => 'required|array|min:1', 'attachments.*' => 'required|file',
        ]);
        $ticket = filled($data['ticket_key'] ?? null)
            ? $project->issues()->where(fn ($q) => $q->where('issue_key', strtoupper(trim($data['ticket_key'])))
                ->orWhere('reference', strtolower(trim($data['ticket_key']))))->first()
            : $project->issues()->find($data['ticket_id']);
        if (! $ticket) {
            throw \Illuminate\Validation\ValidationException::withMessages(['ticket_key' => 'Choose a ticket in this project.']);
        }
        abort_unless(\App\Support\PmsTicketAccess::comment($request->user(), $ticket), 403);
        $files = $request->file('attachments', []);
        $attachments->validateFiles($files);
        $paths = [];
        try {
            DB::transaction(function () use ($request, $project, $ticket, $files, $attachments, &$paths): void {
                $attachments->storeMany($ticket, $request->user(), $files, null, $paths);
                $this->audit($request, 'project.attachments_added', $project, ['ticket_id' => $ticket->id, 'count' => count($files)]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($paths);
            throw $exception;
        }

        return $request->expectsJson() ? response()->json(['uploaded' => count($files)], 201)
            : back()->with('success', 'Evidence uploaded.');
    }

    public function update(Request $request, PmProject $project)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'edit_project'), 403);
        abort_if($project->archived_at, 422, 'Archived projects cannot be edited.');
        $data = $request->validate([
            'name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000',
            'objective' => 'nullable|string|max:10000', 'category' => 'nullable|string|max:100',
            'status' => ['required', Rule::in(self::STATUSES)],
            'priority' => ['required', Rule::exists('pm_priorities', 'code')->where('active', true)],
            'planned_start_date' => 'nullable|date', 'target_date' => 'nullable|date',
            'related_module' => 'nullable|string|max:40', 'lock_version' => 'required|integer|min:0',
            'manager_id' => 'nullable|integer|exists:users,id',
            'owner_id' => 'nullable|integer|exists:users,id',
            'current_global_cycle_id' => 'nullable|integer|exists:pm_global_cycles,id',
            'current_release_id' => 'nullable|integer|exists:pm_releases,id',
        ]);
        $planningChanged = array_key_exists('current_global_cycle_id', $data) || array_key_exists('current_release_id', $data);
        $selectedCycle = null;
        $selectedRelease = null;
        if ($planningChanged) {
            abort_unless(PmsAccess::manageCycle($request->user(), $project), 403);
            [$selectedCycle, $selectedRelease] = app(PmsProjectPlanningService::class)->validateSelection(
                array_key_exists('current_global_cycle_id', $data) ? $data['current_global_cycle_id'] : $project->current_global_cycle_id,
                array_key_exists('current_release_id', $data) ? $data['current_release_id'] : $project->current_release_id,
                $project, $request->user());
        }
        $ownerChanged = array_key_exists('owner_id', $data)
            && (int) ($data['owner_id'] ?? 0) !== (int) $project->owner_id;
        if ($ownerChanged) {
            abort_unless(PmsAccess::manageOwner($request->user(), $project), 403);
            app(PmsProjectOwnerService::class)->validate($data['owner_id'] ?? null);
            if (! $data['owner_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['owner_id' => 'Choose a Project Owner.']);
            }
        }
        if (PmsAccess::role($request->user(), $project) === 'Business Analyst') {
            abort_if(array_key_exists('manager_id', $data) && (int) $data['manager_id'] !== (int) $project->manager_id, 403);
            $review = app(PmsChangeReviewService::class)->submit($request, $project, 'update', $data, 'project');
            return $request->expectsJson() ? response()->json(['review_id' => $review->id, 'status' => 'pending'], 202)
                : back()->with('success', 'Project changes submitted for Project Manager review.');
        }
        $managerChanged = array_key_exists('manager_id', $data)
            && (int) ($data['manager_id'] ?? 0) !== (int) ($project->manager_id ?? 0);
        if ($managerChanged && ! PmsAccess::administrator($request->user())) abort(403);
        if ($managerChanged && ! empty($data['manager_id']) && (! PmsPosition::eligible(User::with('pmsProfile')->find($data['manager_id']), 'manager')
            || ! $project->members()->where('users.id', $data['manager_id'])
                ->whereIn('pm_project_members.role', ['Project Manager', 'Project Administrator'])->exists())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['manager_id' => 'Choose an active PMS manager.']);
        }
        if (isset($data['description'])) $data['description'] = PmsProjectDescription::canonicalize($data['description']);
        DB::transaction(function () use ($request, $project, $data, $planningChanged, $selectedCycle, $selectedRelease) {
            $locked = PmProject::lockForUpdate()->findOrFail($project->id);
            if ($locked->lock_version !== (int) $data['lock_version']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['lock_version' => 'Project changed. Refresh and retry.']);
            }
            unset($data['lock_version']);
            unset($data['current_global_cycle_id'], $data['current_release_id']);
            $before = $locked->only(array_keys($data));
            $locked->fill($data);
            $locked->completed_at = $locked->status === 'Completed' ? ($locked->completed_at ?: today()) : null;
            $locked->updated_by = $request->user()->id;
            $locked->lock_version++;
            $locked->save();
            if ($planningChanged) app(PmsProjectPlanningService::class)->apply($locked, $selectedCycle, $selectedRelease, $request->user());
            if (array_key_exists('owner_id', $data) && $data['owner_id']
                && ! $locked->members()->where('users.id', $data['owner_id'])->exists()) {
                $locked->members()->attach($data['owner_id'], ['role' => 'Viewer']);
            }
            $after = $locked->only(array_keys($data));
            $changed = array_values(array_filter(array_keys($data), fn ($field) => (string) ($before[$field] ?? '') !== (string) ($after[$field] ?? '')));
            $ownerLabels = in_array('owner_id', $changed, true) ? ['labels' => [
                'old' => User::find($before['owner_id'] ?? null)?->name ?? 'Unassigned',
                'new' => User::find($after['owner_id'] ?? null)?->name ?? 'Unassigned',
            ]] : [];
            $this->audit($request, 'project.updated', $locked, [
                'changed' => $changed,
                'before' => array_intersect_key($before, array_flip($changed)),
                'after' => array_intersect_key($after, array_flip($changed)),
            ] + $ownerLabels);
        });

        return $request->expectsJson() ? response()->json($project->fresh()) : back()->with('success', 'Project updated.');
    }

    public function updatePlanning(Request $request, PmProject $project)
    {
        abort_unless(PmsAccess::manageCycle($request->user(), $project), 403);
        $data = $request->validate([
            'lock_version' => 'required|integer|min:0',
            'current_global_cycle_id' => 'nullable|integer|exists:pm_global_cycles,id',
            'current_release_id' => 'nullable|integer|exists:pm_releases,id',
        ]);
        [$cycle, $release] = app(PmsProjectPlanningService::class)->validateSelection(
            $data['current_global_cycle_id'] ?? null, $data['current_release_id'] ?? null, $project, $request->user());
        $project = DB::transaction(function () use ($request, $project, $data, $cycle, $release): PmProject {
            $locked = PmProject::lockForUpdate()->findOrFail($project->id);
            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['lock_version' => 'Project changed. Refresh and retry.']);
            }
            if ((int) $locked->current_global_cycle_id !== (int) ($cycle?->id ?? 0)
                || (int) $locked->current_release_id !== (int) ($release?->id ?? 0)) {
                app(PmsProjectPlanningService::class)->apply($locked, $cycle, $release, $request->user());
                $locked->forceFill(['updated_by' => $request->user()->id, 'lock_version' => $locked->lock_version + 1])->save();
            }
            return $locked->fresh();
        });
        return $request->expectsJson() ? response()->json($project) : back()->with('success', 'Project planning links saved. Tickets were not moved.');
    }

    public function archive(Request $request, PmProject $project)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'manage'), 403);
        DB::transaction(function () use ($request, $project) {
        $project->forceFill(['archived_at' => now(), 'archived_by' => $request->user()->id])->save();
        $this->audit($request, 'project.archived', $project);
        });

        return $request->expectsJson() ? response()->json(['archived' => true]) : redirect()->route('pms.projects.index')->with('success', 'Project archived.');
    }

    public function unarchive(Request $request, PmProject $project)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'manage'), 403);
        $project->forceFill(['archived_at' => null, 'archived_by' => null])->save();
        $this->audit($request, 'project.unarchived', $project);

        return $request->expectsJson() ? response()->json($project) : back()->with('success', 'Project restored from archive.');
    }

    public function trash(Request $request, PmProject $project)
    {
        $analyst = PmsAccess::role($request->user(), $project) === 'Business Analyst';
        abort_unless($analyst || PmsAccess::allows($request->user(), $project, 'manage'), 403);
        $data = $request->validate(['confirmation' => ['required', Rule::in([$project->key])]]);
        if ($analyst) {
            $review = app(PmsChangeReviewService::class)->submit($request, $project, 'delete', [], 'project');
            return $request->expectsJson() ? response()->json(['review_id' => $review->id, 'status' => 'pending'], 202)
                : back()->with('success', 'Project deletion submitted for Project Manager review.');
        }
        DB::transaction(function () use ($request, $project, $data) {
            $this->audit($request, 'project.trashed', $project, ['confirmation' => $data['confirmation']]);
            $project->forceFill(['deleted_by' => $request->user()->id])->save();
            $project->delete();
        });

        return $request->expectsJson() ? response()->json(['trashed' => true]) : redirect()->route('pms.projects.index')->with('success', 'Project moved to Recently Deleted.');
    }

    public function restore(Request $request, int $id)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $project = PmProject::onlyTrashed()->findOrFail($id);
        DB::transaction(function () use ($request, $project) {
        $project->restore();
        $this->audit($request, 'project.restored', $project);
        });

        return $request->expectsJson() ? response()->json($project) : redirect()->route('pms.projects.index', $project->archived_at ? ['archived' => 1] : [])->with('success', 'Project restored from Recently Deleted.');
    }

    public function purge(Request $request, int $id)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $project = PmProject::onlyTrashed()->findOrFail($id);
        $data = $request->validate(['confirmation' => ['required', Rule::in([$project->key])]]);
        if (DB::table('pm_time_entries')->where('project_id', $project->id)->exists()
            || DB::table('pm_work_timers')->where('project_id', $project->id)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'confirmation' => 'This project has tracked work. Keep it in Recently Deleted or restore and archive it to preserve time records.',
            ]);
        }
        $attachmentPaths = DB::table('pm_ticket_attachments')->join('pm_work_items', 'pm_work_items.id', '=', 'pm_ticket_attachments.issue_id')
            ->where('pm_work_items.project_id', $project->id)->pluck('pm_ticket_attachments.storage_path')->all();
        DB::transaction(function () use ($request, $project, $data) {
            $this->audit($request, 'project.deleted', $project, ['permanent' => true, 'confirmation' => $data['confirmation']]);
            $project->forceDelete();
        });
        Storage::disk('local')->delete($attachmentPaths);

        return $request->expectsJson() ? response()->json(['deleted' => true]) : redirect()->route('pms.projects.index', ['trash' => 1])->with('success', 'Project permanently deleted.');
    }

    public function addMember(Request $request, PmProject $project)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'manage'), 403);
        $data = $request->validate([
            'user_id' => 'required_without:user_ids|integer|exists:users,id',
            'user_ids' => 'required_without:user_id|array|min:1|max:100',
            'user_ids.*' => 'integer|distinct|exists:users,id',
            'role' => ['required', Rule::in(PmsAccess::assignableProjectRoles($request->user()))],
        ]);
        $ids = array_values(array_unique($data['user_ids'] ?? [$data['user_id']]));
        $members = User::with('pmsProfile')->whereIn('id', $ids)->get();
        abort_unless($members->count() === count($ids), 422);
        foreach ($members as $member) {
            abort_unless((string) $member->status === '1' && (int) $member->role_id !== 700 && PmsAccess::eligible($member), 422,
                'Only active PMS members can be added to projects.');
            if (! PmsAccess::administrator($request->user())) {
                $currentRole = $project->members()->where('users.id', $member->id)->first()?->pivot?->role;
                abort_if($member->id === $request->user()->id || ($currentRole && ! in_array($currentRole, PmsAccess::MANAGER_ASSIGNABLE_ROLES, true)), 403);
            }
        }
        DB::transaction(function () use ($request, $project, $data, $ids) {
            $project->members()->syncWithoutDetaching(array_fill_keys($ids, ['role' => $data['role']]));
            $this->audit($request, 'project.members_set', $project, ['user_ids' => $ids, 'role' => $data['role']]);
        });

        return $request->expectsJson() ? response()->json(['saved' => true, 'count' => count($ids)]) : back()->with('success', count($ids).' member(s) saved.');
    }

    public function removeMember(Request $request, PmProject $project, int $userId)
    {
        abort_unless(PmsAccess::allows($request->user(), $project, 'manage'), 403);
        abort_if((int) $project->owner_id === $userId, 422, 'The project owner cannot be removed.');
        if (! PmsAccess::administrator($request->user())) {
            $currentRole = $project->members()->where('users.id', $userId)->first()?->pivot?->role;
            abort_if($userId === $request->user()->id || ($currentRole && ! in_array($currentRole, PmsAccess::MANAGER_ASSIGNABLE_ROLES, true)), 403);
        }
        DB::transaction(function () use ($request, $project, $userId) {
        $project->members()->detach($userId);
        $this->audit($request, 'project.member_removed', $project, ['user_id' => $userId]);
        });

        return $request->expectsJson() ? response()->json(['removed' => true]) : back()->with('success', 'Member removed.');
    }

    private function audit(Request $request, string $action, PmProject $project, array $details = []): void
    {
        DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'project', 'subject_id' => $project->id, 'details' => json_encode($details), 'created_at' => now()]);
    }
}
