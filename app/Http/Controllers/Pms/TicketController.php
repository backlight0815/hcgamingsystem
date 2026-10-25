<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmWorkItem;
use App\Models\PmWikiPage;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\PmsAttachmentService;
use App\Services\PmsDeliveryWorkflow;
use App\Services\PmsQatWorkflow;
use App\Services\PmsTicketActivity;
use App\Services\PmsTicketNotificationService;
use App\Services\PmsTicketRelationships;
use App\Services\PmsQatAssignmentResolver;
use App\Services\PmsWorkflow;
use App\Support\PmsAccess;
use App\Support\PmsDateTime;
use App\Support\PmsPriority;
use App\Support\PmsPosition;
use App\Support\PmsTicketAccess;
use App\Support\PmsTicketReference;
use App\Support\PmsTicketManager;
use App\Support\PmsWikiAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public const CATEGORIES = [
        'REQ' => 'New Requirement', 'ENH' => 'Enhancement', 'PRD' => 'Production Issue',
        'QAT' => 'Quality Assurance Ticket', 'TSK' => 'Development Task',
    ];

    public const DEFECT_TYPES = [
        'Functional bug', 'UI/UX issue', 'API issue', 'Backend issue', 'Integration issue',
        'Database issue', 'Performance issue', 'Security issue', 'Compatibility issue',
        'Regression issue', 'Environment issue', 'Other testing issue',
    ];

    public static function categoryHeading(?string $category): string
    {
        return array_key_exists((string) $category, self::CATEGORIES) ? $category.' Tickets' : 'All Tickets';
    }

    public function index(Request $request)
    {
        $tickets = $this->sortedTickets($request)->paginate(30)->withQueryString();
        $projects = $this->projects($request);
        $releases = PmRelease::whereIn('project_id', $projects->pluck('id'))->orderByDesc('planned_deploy_date')->get(['id', 'release_key', 'version']);
        $members = DB::table('pm_project_members')->join('users', 'users.id', '=', 'pm_project_members.user_id')
            ->whereIn('pm_project_members.project_id', $projects->pluck('id'))->distinct()->orderBy('users.name')->get(['users.id', 'users.name']);
        $managerFilterOptions = PmsTicketManager::candidates($projects->pluck('id'))->unique('id');
        $ticketHeading = self::categoryHeading($request->input('category'));
        $canRestoreTickets = $request->user()->isGlobalSuperAdmin()
            || DB::table('pm_project_members')->where('user_id', $request->user()->id)->where('role', 'Project Manager')->exists()
            || PmProject::where('owner_id', $request->user()->id)->exists();

        return $request->expectsJson() ? response()->json($tickets) : view('pms.tickets', compact('tickets', 'projects', 'releases', 'members', 'managerFilterOptions', 'ticketHeading', 'canRestoreTickets'));
    }

    private function filteredTickets(Request $request): Builder
    {
        $query = PmWorkItem::with(['project:id,key,name,project_reference', 'release:id,release_key,version', 'cycle:id,cycle_key',
            'developer:id,name', 'qa:id,name', 'reporter:id,name', 'projectManager:id,name', 'testCaseReview.reviewer:id,name'])
            ->where(function ($q): void {
                $q->whereNull('project_id')->orWhereHas('project', fn ($project) => $project->whereNull('archived_at'));
            });
        if (! PmsAccess::administrator($request->user())) {
            $query->where(function ($q) use ($request): void {
                $q->whereIn('project_id', DB::table('pm_project_members')->where('user_id', $request->user()->id)->select('project_id'))
                    ->orWhereIn('project_id', PmProject::where('owner_id', $request->user()->id)->select('id'))
                    ->orWhere(fn ($intake) => $intake->whereNull('project_id')->where('reporter_id', $request->user()->id));
            });
        }
        if ($request->filled('project')) {
            $query->where('project_id', $request->integer('project'));
        }
        if ($request->boolean('unassigned')) {
            $query->whereNull('project_id');
        }
        if ($request->input('assignment') === 'unassigned') {
            $query->whereNull('developer_id')->whereNull('qa_id');
        }
        if (array_key_exists((string) $request->category, self::CATEGORIES)) {
            $query->whereIn('classification_key', $request->category === 'ENH' ? ['ENH', 'EHN'] : [$request->category]);
        }
        if ($request->filled('release')) {
            $query->where('release_id', $request->integer('release'));
        }
        if ($request->filled('assignee')) {
            $query->where('developer_id', $request->integer('assignee'));
        }
        if ($request->filled('project_manager')) {
            $query->where('project_manager_id', $request->integer('project_manager'));
        }
        if ($request->input('mine') === 'development') {
            $query->where('developer_id', $request->user()->id);
        } elseif ($request->input('mine') === 'qa') {
            $query->where('qa_id', $request->user()->id);
        } elseif ($request->input('mine') === 'all') {
            $query->where(fn ($assigned) => $assigned->where('developer_id', $request->user()->id)
                ->orWhere('qa_id', $request->user()->id));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->input('blockers') === 'active') {
            $query->whereIn('id', app(\App\Services\PmsBlockers::class)->activeTicketIds());
        } elseif ($request->input('blockers') === 'overdue') {
            $query->whereIn('id', DB::table('pm_ticket_blockers')->whereNull('resolved_at')
                ->whereDate('expected_resolution_date', '<', today())->select('issue_id'));
        }
        if ($request->boolean('resumed')) {
            $query->whereIn('id', DB::table('pm_issue_history')->where('event', 'resume')->select('issue_id'));
        }
        if ($request->priority === 'high_critical') {
            $query->whereIn('priority', array_merge(PmsPriority::codesFor('High'), PmsPriority::codesFor('Critical')));
        } elseif (in_array($request->priority, ['Low', 'Normal', 'High', 'Critical'], true)) {
            $query->whereIn('priority', PmsPriority::codesFor($request->priority));
        } elseif (in_array($request->priority, ['P0', 'P1', 'P2', 'P3', 'P4'], true)) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(fn ($q) => $q->where('issue_key', 'like', '%'.$term.'%')
                ->orWhere('reference', 'like', '%'.strtolower($term).'%')->orWhere('summary', 'like', '%'.$term.'%')
                ->orWhereHas('project', fn ($project) => $project->where('project_reference', 'like', '%'.$term.'%')));
        }
        foreach (['date_from' => '>=', 'date_to' => '<='] as $field => $operator) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->input($field))) {
                $query->whereDate('created_at', $operator, $request->input($field));
            }
        }
        if (in_array((string) $request->input('updated'), ['7', '30'], true)) {
            $query->where('updated_at', '>=', now()->subDays((int) $request->input('updated')));
        }

        return $query;
    }

    public function export(Request $request)
    {
        $query = $this->sortedTickets($request);
        $safe = static function ($value): string {
            $text = (string) $value;

            return preg_match('/^[\s]*[=+\-@]/u', $text) ? "'".$text : $text;
        };

        return response()->streamDownload(function () use ($query, $safe): void {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Ticket ID', 'Ticket Type', 'Title', 'Description', 'Project', 'Project ID / Code',
                'Priority', 'Status', 'Assignee', 'Created By', 'Created Date', 'Updated Date', 'Updated Time (MYT)',
                'Release', 'Development Cycle', 'Review By', 'Test Case Review Status'], ',', '"', '', "\r\n");
            $query->chunk(200, function ($tickets) use ($stream, $safe): void {
                foreach ($tickets as $ticket) {
                    fputcsv($stream, array_map($safe, [
                        $ticket->reference, $ticket->classification_key === 'EHN' ? 'ENH' : $ticket->classification_key,
                        $ticket->summary, $ticket->description, $ticket->project?->name,
                        $ticket->project?->project_reference, PmsPriority::label($ticket->priority), $ticket->status,
                        $ticket->developer?->name ?? $ticket->qa?->name, $ticket->reporter?->name,
                        PmsDateTime::date($ticket->created_at), PmsDateTime::date($ticket->updated_at),
                        PmsDateTime::time($ticket->updated_at), $ticket->release?->release_key,
                        $ticket->cycle?->cycle_key, $ticket->testCaseReview?->reviewer?->name,
                        $ticket->testCaseReview ? ucwords(str_replace('_', ' ', $ticket->testCaseReview->status)) : 'Not Requested',
                    ]), ',', '"', '', "\r\n");
                }
            });
            fclose($stream);
        }, 'pms_'.strtolower(array_key_exists((string) $request->input('category'), self::CATEGORIES) ? $request->input('category') : 'all').'_tickets_'.now()->timezone(PmsDateTime::ZONE)->format('Y-m-d_Hi').'_MYT.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Export-Scope' => 'all-filtered-results']);
    }

    private function sortedTickets(Request $request): Builder
    {
        $query = $this->filteredTickets($request);
        $sort = (string) $request->input('sort_by', $request->input('sort', 'updated_at'));
        $sort = in_array($sort, ['id', 'reference', 'summary', 'classification_key', 'project',
            'priority', 'status', 'assignee', 'updated_at', 'created_at', 'due_date'], true) ? $sort : 'updated_at';
        $direction = (string) $request->input('sort_order', $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        switch ($sort) {
            case 'id':
            case 'reference':
                $query->orderBy('id', $direction); // Numeric allocation order, not lexical SF...10 before SF...2.
                break;
            case 'project':
                $query->orderBy(PmProject::select('name')->whereColumn('pm_projects.id', 'pm_work_items.project_id')->limit(1), $direction);
                break;
            case 'assignee':
                $query->orderByRaw('COALESCE((SELECT name FROM users WHERE users.id = pm_work_items.developer_id), (SELECT name FROM users WHERE users.id = pm_work_items.qa_id), \'\') '.$direction);
                break;
            case 'priority':
                $query->orderByRaw("CASE pm_work_items.priority WHEN 'P0' THEN 5 WHEN 'P1' THEN 4 WHEN 'P2' THEN 3 WHEN 'P3' THEN 2 WHEN 'P4' THEN 1 ELSE 0 END ".$direction);
                break;
            default:
                $query->orderBy('pm_work_items.'.$sort, $direction);
        }
        return $sort === 'id' || $sort === 'reference' ? $query : $query->orderBy('pm_work_items.id', $direction);
    }

    public function create(Request $request)
    {
        $contextProject = $request->attributes->get('pms_project_context');
        $allowedCategories = collect(self::CATEGORIES)->filter(fn ($label, $key) => PmsAccess::canCreateTicket($request->user(), $key, $contextProject));
        $projects = $this->projects($request)->filter(fn ($project) => $allowedCategories->keys()
            ->contains(fn ($category) => PmsAccess::canCreateTicket($request->user(), $category, $project)))->values();
        abort_if($allowedCategories->isEmpty() && $projects->isNotEmpty(), 403);
        $releases = PmRelease::whereIn('project_id', $projects->pluck('id'))->whereNotIn('status', ['Cancelled', 'Completed'])->orderByDesc('id')->get(['id', 'project_id', 'cycle_id', 'release_key', 'version', 'status']);
        $cycles = PmCycle::whereIn('project_id', $projects->pluck('id'))->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->orderByDesc('id')->get(['id', 'project_id', 'cycle_key', 'name', 'status']);
        $defectTypes = DB::table('pm_defect_types')->where('active', true)->orderBy('name')->pluck('name');
        $members = DB::table('pm_project_members')->join('users', 'users.id', '=', 'pm_project_members.user_id')
            ->join('pms_profiles', 'pms_profiles.user_id', '=', 'users.id')->where('pms_profiles.status', 'active')
            ->leftJoin('pms_job_positions as positions', 'positions.id', '=', 'pms_profiles.job_position_id')
            ->where('users.status', 1)->whereIn('pm_project_members.project_id', $projects->pluck('id'))->orderBy('users.name')
            ->get(['pm_project_members.project_id', 'pm_project_members.role', 'users.id', 'users.name', 'pms_profiles.job_position',
                'pms_profiles.job_position_id', 'positions.code as position_code', 'positions.active as position_active']);

        $qaOnly = $allowedCategories->keys()->all() === ['QAT'];
        $intakeAvailable = $allowedCategories->keys()->contains(fn ($category) => $category !== 'QAT');
        $qatResolver = app(PmsQatAssignmentResolver::class);
        $qatProjects = $qatResolver->eligibleProjects($request->user(), $projects);
        $projectLocked = $qaOnly && $qatProjects->count() === 1;
        $qatAssignments = $qatResolver->forProjects($qatProjects, $releases, $cycles);
        $selectedProjectId = old('project_id', $projects->count() === 1 ? $projects->first()->id : $request->input('project'));
        $qaSelfProjects = $projects->filter(fn ($project) => in_array(PmsAccess::role($request->user(), $project),
            ['QA', 'QA Lead', 'QA Engineer'], true) && PmsPosition::eligible($request->user(), 'qa', $project->id))
            ->pluck('id')->all();

        $creationToken = old('creation_token', (string) Str::uuid());
        $managerCandidates = PmsTicketManager::candidates($projects->pluck('id'));
        $managerDefaults = $projects->mapWithKeys(fn ($project) => [$project->id => $project->manager_id
            && $managerCandidates->contains(fn ($candidate) => (int) $candidate->project_id === $project->id
                && (int) $candidate->id === (int) $project->manager_id) ? $project->manager_id : null]);

        return view('pms.ticket-create', compact('projects', 'releases', 'cycles', 'members', 'defectTypes', 'allowedCategories', 'contextProject', 'qaOnly', 'intakeAvailable', 'creationToken', 'projectLocked', 'selectedProjectId', 'managerCandidates', 'managerDefaults', 'qatAssignments', 'qaSelfProjects'));
    }

    public function createForProject(Request $request, PmProject $project, string $category)
    {
        if ($category === 'EHN') $category = 'ENH';
        abort_unless(isset(self::CATEGORIES[$category]) && PmsAccess::allows($request->user(), $project, 'view'), 404);
        abort_unless(PmsAccess::canCreateTicket($request->user(), $category, $project), 403);
        $request->attributes->set('pms_project_context', $project);
        $request->merge(['category' => $category, 'project' => $project->id]);

        return $this->create($request);
    }

    public function storeForProject(Request $request, PmProject $project, string $category, PmsAttachmentService $attachments)
    {
        if ($category === 'EHN') $category = 'ENH';
        abort_unless(isset(self::CATEGORIES[$category]) && PmsAccess::allows($request->user(), $project, 'view'), 404);
        $request->merge(['project_id' => $project->id, 'classification_key' => $category]);

        return $this->store($request, $attachments);
    }

    public function store(Request $request, PmsAttachmentService $attachments)
    {
        if ($request->input('classification_key') === 'EHN') $request->merge(['classification_key' => 'ENH']);
        if ($request->filled('priority')) $request->merge(['priority' => $this->priorityCode($request->input('priority'))]);
        $data = $request->validate($this->rules(), [
            'required_if' => ':attribute is required.',
            'summary.required' => 'Ticket Title is required.',
            'project_id.required_if' => 'Please select a project.',
            'priority.required' => 'Please select a priority.',
            'developer_id.required_if' => 'Please select an assigned developer.',
        ], [
            'summary' => 'Ticket Title', 'project_id' => 'Project', 'priority' => 'Priority',
            'developer_id' => 'Assigned Developer', 'qa_id' => 'Assigned QA',
            'cycle_id' => 'Development Cycle', 'release_id' => 'Release',
            'description' => 'Description', 'business_justification' => 'Business Justification',
            'acceptance_criteria' => 'Acceptance Criteria', 'current_behavior' => 'Current Behavior',
            'proposed_behavior' => 'Proposed Behavior', 'detected_at' => 'Detected At',
            'business_impact' => 'Business Impact', 'production_environment' => 'Production Environment',
            'actual_result' => 'Actual Result', 'reproduction_steps' => 'Steps to Reproduce',
            'expected_result' => 'Expected Result', 'defect_type' => 'Defect Type',
        ]);
        $category = $data['classification_key'];
        $project = ! empty($data['project_id']) ? PmProject::findOrFail($data['project_id']) : null;
        abort_unless(PmsAccess::canCreateTicket($request->user(), $category, $project), 403);
        $reviewerId = ! empty($data['test_case_reviewer_id']) ? (int) $data['test_case_reviewer_id'] : null;
        unset($data['test_case_reviewer_id']);
        if ($reviewerId) {
            if (! $project) throw ValidationException::withMessages(['test_case_reviewer_id' => 'Select a project before assigning a reviewer.']);
            $candidateTicket = new PmWorkItem();
            $candidateTicket->forceFill(['project_id' => $project->id])->setRelation('project', $project);
            app(\App\Services\PmsTestCaseReviewService::class)->validateReviewer($candidateTicket, $reviewerId);
        }
        if ($project && ! array_key_exists('project_manager_id', $data)) {
            $data['project_manager_id'] = PmsTicketManager::defaultFor($project);
        }
        $qaCreator = false;
        if ($category === 'QAT' && $project) {
            app(PmsQatAssignmentResolver::class)->applyAndValidate($request->user(), $project, $data);
            $qaCreator = in_array(PmsAccess::role($request->user(), $project), ['QA', 'QA Lead', 'QA Engineer'], true)
                && PmsPosition::eligible($request->user(), 'qa', $project->id);
            if ($qaCreator) $data['qa_id'] = $request->user()->id;
            if ($qaCreator && empty($data['developer_id'])) {
                throw ValidationException::withMessages(['developer_id' => 'Please select an assigned developer.']);
            }
        }
        if ($project) {
            abort_unless(PmsAccess::allows($request->user(), $project, 'edit_issue'), 403);
            if ($project->archived_at) {
                throw ValidationException::withMessages(['project_id' => 'Archived projects cannot accept tickets.']);
            }
            $managerOnly = $category === 'QAT'
                ? ($qaCreator ? ['due_date', 'estimate_minutes'] : ['due_date', 'developer_id', 'qa_id', 'estimate_minutes'])
                : ['due_date', 'release_id', 'cycle_id', 'developer_id', 'qa_id', 'estimate_minutes'];
            if (collect($managerOnly)
                ->contains(fn ($field) => filled($data[$field] ?? null)) && ! PmsAccess::allows($request->user(), $project, 'manage')) {
                abort(403);
            }
            if ($request->exists('project_manager_id') && ! PmsAccess::allows($request->user(), $project, 'manage')) {
                abort(403);
            }
        }
        if (! $project && ! empty($data['due_date'])) {
            throw ValidationException::withMessages(['due_date' => 'Assign a project before setting a due date.']);
        }
        $this->syncReleaseCycle($data);
        $this->checkRelationships($project, $data);
        $this->assertReleaseMutable($data['release_id'] ?? null);
        $data['tags'] = $this->normalizeTags($data['tags'] ?? null);
        $linked = [];
        foreach (['linked_requirement_key' => ['REQ', 'FTR'], 'linked_task_key' => ['TSK']] as $field => $types) {
            if (filled($data[$field] ?? null)) {
                $candidate = PmWorkItem::where('issue_key', strtoupper(trim($data[$field])))
                    ->orWhere('reference', strtolower(trim($data[$field])))->first();
                if (! $candidate || ! in_array($candidate->classification_key, $types, true) || ! PmsTicketAccess::view($request->user(), $candidate)) {
                    throw ValidationException::withMessages([$field => 'Choose a visible ticket of the required category.']);
                }
                $linked[$field] = $candidate;
            }
            unset($data[$field]);
        }
        $files = $request->file('attachments', []);
        $attachments->validateFiles($files);
        $caseFiles = $request->file('test_cases', []);
        $attachments->validateFiles($caseFiles, 'test_case');
        unset($data['attachments'], $data['test_cases']);

        $storedPaths = [];
        $createdNow = false;
        try {
            $ticket = DB::transaction(function () use ($request, $attachments, $files, $caseFiles, $reviewerId, $data, $project, $category, $linked, &$storedPaths, &$createdNow): PmWorkItem {
            $sequence = DB::table('pm_ticket_sequences')->where('prefix', $category)->lockForUpdate()->first();
            if (! $sequence) {
                throw ValidationException::withMessages(['classification_key' => 'Ticket numbering is unavailable for this category. Contact an administrator.']);
            }
            if (! empty($data['creation_token'])) {
                $existing = PmWorkItem::where('creation_token', $data['creation_token'])->first();
                if ($existing) {
                    abort_unless((int) $existing->reporter_id === $request->user()->id, 403);
                    return $existing;
                }
            }
            DB::table('pm_ticket_sequences')->where('prefix', $category)->update(['next_number' => $sequence->next_number + 1]);
            $ticket = new PmWorkItem();
            $ticket->forceFill(array_merge($data, [
                'project_id' => $project?->id, 'issue_key' => $category.'-'.str_pad((string) $sequence->next_number, 5, '0', STR_PAD_LEFT),
                'reference' => PmsTicketReference::format($category, (int) $sequence->next_number),
                'display_sequence' => $sequence->next_number, 'type' => self::CATEGORIES[$category],
                'reporter_id' => $request->user()->id, 'status' => $category === 'QAT' ? 'Open' : 'Planned',
                'original_cycle_id' => $data['cycle_id'] ?? null,
                'original_global_cycle_id' => $data['global_cycle_id'] ?? null,
            ]))->save();
            $createdNow = true;
            if ($ticket->project_manager_id) {
                PmsTicketActivity::record($ticket->id, 'assignment', 'project_manager_id', null,
                    $ticket->project_manager_id, actor: $request->user());
                DB::table('pm_issue_history')->insert(['issue_id' => $ticket->id, 'actor_id' => $request->user()->id,
                    'event' => 'project_manager_changed', 'changes' => json_encode([
                        'before' => null, 'after' => $ticket->project_manager_id,
                    ]), 'created_at' => now()]);
                $this->audit($request, $ticket, 'ticket.project_manager_changed', [
                    'from' => null, 'to' => $ticket->project_manager_id,
                ]);
            }
            foreach (['developer_id', 'qa_id'] as $field) {
                if (! $ticket->{$field}) continue;
                PmsTicketActivity::record($ticket->id, 'assignment', $field, null,
                    $ticket->{$field}, actor: $request->user());
                DB::table('pm_issue_history')->insert(['issue_id' => $ticket->id,
                    'actor_id' => $request->user()->id, 'event' => $field.'_changed',
                    'changes' => json_encode(['before' => null, 'after' => $ticket->{$field}]),
                    'created_at' => now()]);
            }
            $attachments->storeMany($ticket, $request->user(), $files, null, $storedPaths);
            $attachments->storeMany($ticket, $request->user(), $caseFiles, null, $storedPaths, 'test_case');
            if ($reviewerId) app(\App\Services\PmsTestCaseReviewService::class)->assign($ticket, $request->user(), $reviewerId);
            foreach ($linked as $field => $candidate) {
                $relation = $field === 'linked_requirement_key' ? 'tests_requirement' : 'relates_to';
                $linkId = DB::table('pm_ticket_links')->insertGetId([
                    'source_issue_id' => $ticket->id, 'target_issue_id' => $candidate->id,
                    'relation' => $relation,
                    'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ([$ticket, $candidate] as $item) {
                    PmsTicketActivity::record($item->id, 'relationships', null, null, null, [
                        'action' => 'added', 'relation' => $relation, 'link_id' => $linkId,
                        'other_ticket_id' => $item->id === $ticket->id ? $candidate->id : $ticket->id,
                    ], $request->user());
                }
            }
            DB::table('pm_issue_history')->insert(['issue_id' => $ticket->id, 'actor_id' => $request->user()->id, 'event' => 'created', 'to_status' => $ticket->status, 'created_at' => now()]);
            $this->audit($request, $ticket, 'ticket.created', ['category' => $category]);

            return $ticket;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        foreach ($createdNow ? ['developer_id', 'qa_id'] : [] as $field) {
            if (! empty($ticket->{$field}) && (int) $ticket->{$field} !== $request->user()->id) {
                AppNotificationService::notifyUser($ticket->{$field}, "Assigned to {$ticket->reference}", $ticket->summary, route('pms.tickets.show', $ticket), 'project_management', $request->user()->id);
            }
        }

        return $request->expectsJson() ? response()->json($ticket->load('attachments'), $createdNow ? 201 : 200) : redirect()->route('pms.tickets.show', $ticket)->with('success', $createdNow ? 'Ticket created.' : 'Ticket already created.');
    }

    public function show(Request $request, PmWorkItem $ticket)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 404);
        if (basename($request->path()) !== $ticket->reference) {
            return redirect()->route('pms.tickets.show', $ticket);
        }
        $ticket->load(['project.owner:id,name', 'release', 'cycle', 'reporter:id,name,username', 'developer:id,name', 'qa:id,name',
            'projectManager:id,name', 'projectManager.pmsProfile:user_id,job_position',
            'attachments.uploader:id,name', 'testCaseReview.reviewer.pmsProfile:id,user_id,job_position,workspace_role',
            'testCaseReview.approver:id,name', 'verifications.tester:id,name']);
        $comments = $ticket->comments()->whereNull('parent_id')
            ->with(['author.pmsProfile', 'attachments', 'replies.author.pmsProfile', 'replies.attachments'])
            ->orderBy('created_at', $request->input('comments') === 'newest' ? 'desc' : 'asc')
            ->orderBy('id', $request->input('comments') === 'newest' ? 'desc' : 'asc')
            ->paginate(20, ['*'], 'comments_page')->withQueryString();
        $history = DB::table('pm_issue_history')->leftJoin('users', 'users.id', '=', 'pm_issue_history.actor_id')
            ->where('issue_id', $ticket->id)->select('pm_issue_history.*', 'users.name as actor_name')
            ->orderByDesc('pm_issue_history.created_at')->orderByDesc('pm_issue_history.id')->limit(200)->get();
        $links = DB::table('pm_ticket_links')->where('source_issue_id', $ticket->id)->orWhere('target_issue_id', $ticket->id)->get();
        $linkedIds = $links->flatMap(fn ($link) => [$link->source_issue_id, $link->target_issue_id])->unique()->reject(fn ($id) => (int) $id === $ticket->id);
        $linkedTickets = PmWorkItem::whereIn('id', $linkedIds)->get()->filter(fn ($candidate) => PmsTicketAccess::view($request->user(), $candidate))->keyBy('id');
        $relatedTicketOptions = $ticket->project_id ? PmWorkItem::where('project_id', $ticket->project_id)
            ->where('id', '<>', $ticket->id)->orderBy('issue_key')->limit(200)->get(['id', 'reference', 'issue_key', 'summary']) : collect();
        $projects = $this->projects($request);
        $members = $ticket->project ? DB::table('pm_project_members')->join('users', 'users.id', '=', 'pm_project_members.user_id')
            ->join('pms_profiles', 'pms_profiles.user_id', '=', 'users.id')
            ->leftJoin('pms_job_positions as positions', 'positions.id', '=', 'pms_profiles.job_position_id')
            ->where('pm_project_members.project_id', $ticket->project_id)->where('pms_profiles.status', 'active')
            ->where('users.status', 1)
            ->when(\Illuminate\Support\Facades\Schema::hasTable('project_management_users'), fn ($query) =>
                $query->whereNotIn('users.id', DB::table('project_management_users')->where('status', '<>', 'active')->select('user_id')))
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'pms_profiles.job_position', 'pms_profiles.job_position_id',
                'positions.code as position_code', 'positions.active as position_active', 'pms_profiles.department']) : collect();
        $managerCandidates = $ticket->project ? PmsTicketManager::candidates([$ticket->project_id]) : collect();
        $ownerCandidates = $ticket->project && PmsAccess::manageOwner($request->user(), $ticket->project)
            ? app(\App\Services\PmsProjectOwnerService::class)->candidates() : collect();
        $releases = $ticket->project?->releases()->whereNotIn('status', ['Cancelled', 'Completed'])->orderByDesc('id')->get() ?? collect();
        $defectTypes = DB::table('pm_defect_types')->where('active', true)->orderBy('name')->pluck('name');
        $activityFilter = in_array($request->input('activity'), ['comments', 'status', 'attachments', 'assignment', 'priority', 'relationships', 'system'], true) ? $request->input('activity') : 'all';
        $activityQuery = DB::table('pm_ticket_activity')->where('issue_id', $ticket->id);
        if ($activityFilter === 'system') {
            $activityQuery->whereNull('actor_id');
        } elseif ($activityFilter === 'status') {
            $activityQuery->where(fn ($query) => $query->where('event_type', 'status')
                ->orWhereIn('field_name', ['status', 'status_code']));
        } elseif ($activityFilter === 'assignment') {
            $activityQuery->where(fn ($query) => $query->where('event_type', 'assignment')
                ->orWhereIn('field_name', ['developer_id', 'qa_id', 'qa_user_id', 'project_manager_id', 'project_owner_id']));
        } elseif ($activityFilter === 'relationships') {
            $activityQuery->whereIn('event_type', ['relationships', 'association']);
        } elseif ($activityFilter !== 'all') {
            $activityQuery->where('event_type', $activityFilter);
        }
        $activityDirection = $request->input('activity_order') === 'oldest' ? 'asc' : 'desc';
        $activity = $activityQuery->orderBy('created_at', $activityDirection)->orderBy('id', $activityDirection)
            ->paginate(25, ['*'], 'activity_page')->withQueryString();
        $activityLookups = \App\Support\PmsActivityPresenter::lookups($activity->getCollection());
        $managerNames = collect();
        $blockedBy = app(PmsTicketRelationships::class)->unresolvedDependencies($ticket->id);
        $wikiLinks = PmWikiPage::with('space')->whereIn('id', DB::table('pm_wiki_links')->where('issue_id', $ticket->id)->select('page_id'))
            ->get()->filter(fn ($page) => PmsWikiAccess::viewPage($request->user(), $page));
        $review = DB::table('pm_ticket_reviews')->where('issue_id', $ticket->id)->orderByDesc('revision')->first();
        $blockers = DB::table('pm_ticket_blockers')->leftJoin('users as reporters', 'reporters.id', '=', 'pm_ticket_blockers.reported_by')
            ->leftJoin('users as owners', 'owners.id', '=', 'pm_ticket_blockers.owner_id')
            ->leftJoin('pm_work_items as related', 'related.id', '=', 'pm_ticket_blockers.related_issue_id')
            ->where('pm_ticket_blockers.issue_id', $ticket->id)
            ->select('pm_ticket_blockers.*', 'reporters.name as reporter_name', 'owners.name as owner_name', 'related.issue_key as related_issue_key')
            ->orderByRaw('CASE WHEN pm_ticket_blockers.resolved_at IS NULL THEN 0 ELSE 1 END')->orderByDesc('pm_ticket_blockers.id')->get();
        $blockerHistory = DB::table('pm_ticket_blocker_history')->leftJoin('users', 'users.id', '=', 'pm_ticket_blocker_history.actor_id')
            ->whereIn('blocker_id', $blockers->pluck('id'))->select('pm_ticket_blocker_history.*', 'users.name as actor_name')
            ->orderByDesc('pm_ticket_blocker_history.id')->get()->groupBy('blocker_id');
        $canManageBlockers = PmsTicketAccess::manage($request->user(), $ticket)
            || $ticket->project && PmsAccess::allows($request->user(), $ticket->project, 'edit_issue')
                && (PmsAccess::role($request->user(), $ticket->project) === 'Business Analyst'
                    || in_array($request->user()->id, array_filter([$ticket->developer_id, $ticket->qa_id]), false));
        $contributors = DB::table('pm_ticket_contributors')->join('users', 'users.id', '=', 'pm_ticket_contributors.user_id')
            ->leftJoin('pms_profiles', 'pms_profiles.user_id', '=', 'users.id')
            ->where('issue_id', $ticket->id)->orderBy('users.name')
            ->get(['pm_ticket_contributors.user_id', 'pm_ticket_contributors.assignment_type',
                'users.name', 'users.status as user_status', 'pms_profiles.status as profile_status',
                'pms_profiles.department', 'pms_profiles.job_position']);
        $canManageContributors = $ticket->project && (PmsTicketAccess::manage($request->user(), $ticket)
            || PmsAccess::allows($request->user(), $ticket->project, 'edit_ticket'));
        $changeReviews = app(\App\Services\PmsChangeReviewService::class)->history('ticket', $ticket->id);
        $canEditQat = $this->canEditQat($request->user(), $ticket);
        $qatEditAssignment = $ticket->classification_key === 'QAT' && $ticket->project
            ? app(PmsQatAssignmentResolver::class)->forProject($ticket->project, $ticket) : null;
        $testCaseFiles = $ticket->attachments->where('category', 'test_case')->sortByDesc('id')->values();
        $testCaseReview = $ticket->testCaseReview;
        $testCaseEvents = $testCaseReview ? DB::table('pm_test_case_review_events as events')
            ->leftJoin('users', 'users.id', '=', 'events.actor_id')->where('events.review_id', $testCaseReview->id)
            ->orderByDesc('events.id')->get(['events.*', 'users.name as actor_name']) : collect();
        $testCaseReviewers = app(\App\Services\PmsTestCaseReviewService::class)->candidates($ticket);

        return $request->expectsJson()
            ? response()->json(compact('ticket', 'comments', 'history', 'links', 'linkedTickets', 'review', 'blockers', 'blockerHistory', 'contributors', 'testCaseFiles', 'testCaseReview', 'testCaseEvents'))
            : view('pms.ticket', compact('ticket', 'comments', 'history', 'links', 'linkedTickets', 'relatedTicketOptions', 'projects', 'members', 'managerCandidates', 'ownerCandidates', 'managerNames', 'releases', 'defectTypes', 'activity', 'activityLookups', 'activityFilter', 'blockedBy', 'wikiLinks', 'review', 'blockers', 'blockerHistory', 'canManageBlockers', 'contributors', 'canManageContributors', 'changeReviews', 'canEditQat', 'qatEditAssignment', 'testCaseFiles', 'testCaseReview', 'testCaseEvents', 'testCaseReviewers'));
    }

    public function assignProjectOwner(Request $request, PmWorkItem $ticket)
    {
        $project = $ticket->project;
        abort_unless($project && PmsAccess::manageOwner($request->user(), $project), 403);
        $data = $request->validate(['owner_id' => 'required|integer|exists:users,id']);
        $owner = app(\App\Services\PmsProjectOwnerService::class)->validate((int) $data['owner_id']);
        DB::transaction(function () use ($request, $project, $ticket, $owner): void {
            $locked = PmProject::lockForUpdate()->findOrFail($project->id);
            $old = $locked->owner_id;
            if ((int) $old === (int) $owner->id) return;
            $oldName = User::find($old)?->name ?? 'Former user (ID: '.$old.')';
            $locked->owner_id = $owner->id;
            $locked->updated_by = $request->user()->id;
            $locked->lock_version++;
            $locked->save();
            if (! $locked->members()->where('users.id', $owner->id)->exists()) {
                $locked->members()->attach($owner->id, ['role' => 'Viewer']);
            }
            DB::table('pm_audit_logs')->insert([
                'actor_id' => $request->user()->id, 'action' => 'project.owner_changed',
                'subject_type' => 'project', 'subject_id' => $locked->id,
                'details' => json_encode(['changed' => ['owner_id'], 'before' => ['owner_id' => $old],
                    'after' => ['owner_id' => $owner->id], 'labels' => ['old' => $oldName, 'new' => $owner->name],
                    'source_ticket_id' => $ticket->id]), 'created_at' => now(),
            ]);
            PmsTicketActivity::record($ticket->id, 'assignment', 'project_owner_id', $old, $owner->id,
                ['project_id' => $locked->id, 'labels' => ['old' => $oldName, 'new' => $owner->name]], $request->user());
        });

        return $request->expectsJson() ? response()->json(['project_reference' => $project->project_reference,
            'owner_id' => $owner->id, 'owner_name' => $owner->name]) : back()->with('success', 'Project Owner updated.');
    }

    public function destroy(Request $request, PmWorkItem $ticket)
    {
        abort_unless(PmsTicketAccess::delete($request->user(), $ticket), 403);
        $request->validate(['confirmation' => ['required', Rule::in([$ticket->reference, $ticket->issue_key])]]);
        DB::transaction(function () use ($request, $ticket): void {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            if ($request->filled('lock_version') && (int) $locked->lock_version !== (int) $request->input('lock_version')) {
                throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and retry.']);
            }
            $locked->deleted_by = $request->user()->id;
            $locked->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $locked->id, 'actor_id' => $request->user()->id,
                'event' => 'deleted', 'reason' => 'Moved to Recently Deleted', 'created_at' => now(),
            ]);
            $this->audit($request, $locked, 'ticket.deleted', ['soft_delete' => true,
                'previous_status' => $locked->status, 'previous_project' => $locked->project?->name]);
            $locked->delete();
        });

        return $request->expectsJson() ? response()->json(['deleted' => true])
            : redirect()->route('pms.tickets.index', ['category' => in_array($ticket->classification_key, ['REQ','ENH','PRD','QAT','TSK'], true) ? $ticket->classification_key : null])
                ->with('success', 'Ticket moved to Recently Deleted.');
    }

    public function deleted(Request $request)
    {
        $query = PmWorkItem::onlyTrashed()->with('project:id,name,project_reference,owner_id,archived_at')
            ->whereHas('project', fn ($project) => $project->whereNull('archived_at'));
        if (! $request->user()->isGlobalSuperAdmin()) {
            $projectIds = DB::table('pm_project_members')->where('user_id', $request->user()->id)
                ->where('role', 'Project Manager')->pluck('project_id');
            $owned = PmProject::where('owner_id', $request->user()->id)->pluck('id');
            $query->whereIn('project_id', $projectIds->concat($owned)->unique());
        }
        $tickets = $query->orderByDesc('deleted_at')->paginate(30);
        return $request->expectsJson() ? response()->json($tickets) : view('pms.tickets-deleted', compact('tickets'));
    }

    public function restore(Request $request, int $id)
    {
        $ticket = PmWorkItem::onlyTrashed()->with('project')->findOrFail($id);
        abort_unless(PmsTicketAccess::delete($request->user(), $ticket), 403);
        DB::transaction(function () use ($request, $ticket): void {
            $ticket->restore();
            $ticket->forceFill(['deleted_by' => null])->save();
            DB::table('pm_issue_history')->insert(['issue_id' => $ticket->id, 'actor_id' => $request->user()->id,
                'event' => 'restored', 'reason' => 'Restored from Recently Deleted', 'created_at' => now()]);
            $this->audit($request, $ticket, 'ticket.restored', ['soft_delete' => false]);
        });
        return $request->expectsJson() ? response()->json(['restored' => true, 'reference' => $ticket->reference])
            : redirect()->route('pms.tickets.show', $ticket)->with('success', 'Ticket restored.');
    }

    public function update(Request $request, PmWorkItem $ticket)
    {
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 404);
        if ($request->exists('priority')) {
            $request->merge(['priority' => $this->priorityCode($request->input('priority'))]);
        }
        $data = $request->validate([
            'lock_version' => 'required|integer|min:0', 'project_id' => 'nullable|integer|exists:pm_projects,id',
            'release_id' => 'nullable|integer|exists:pm_releases,id', 'cycle_id' => 'nullable|integer|exists:pm_cycles,id',
            'developer_id' => 'nullable|integer|exists:users,id', 'qa_id' => 'nullable|integer|exists:users,id',
            'project_manager_id' => 'nullable|integer|exists:users,id',
            'summary' => 'nullable|string|max:255', 'description' => 'nullable|string|max:50000',
            'business_justification' => 'nullable|string|max:20000',
            'current_behavior' => 'nullable|string|max:20000',
            'proposed_behavior' => 'nullable|string|max:20000',
            'acceptance_criteria' => 'nullable|string|max:20000',
            'priority' => ['nullable', Rule::in(array_keys(PmsPriority::OPTIONS))],
            'due_date' => 'nullable|date', 'reason' => 'nullable|string|max:5000',
            'tags' => 'nullable|string|max:500',
              'estimate_minutes' => 'nullable|integer|min:0', 'story_points' => 'nullable|numeric|min:0',
              'severity' => 'nullable|string|max:20', 'business_impact' => 'nullable|string|max:20000',
              'production_environment' => 'nullable|string|max:255', 'detected_at' => 'nullable|date',
              'defect_type' => ['nullable', Rule::exists('pm_defect_types', 'name')->where('active', true)],
              'preconditions' => 'nullable|string|max:20000', 'reproduction_steps' => 'nullable|string|max:50000',
              'expected_result' => 'nullable|string|max:50000', 'actual_result' => 'nullable|string|max:50000',
              'reproducibility' => 'nullable|string|max:50', 'affected_platform' => 'nullable|string|max:100',
              'browser_device' => 'nullable|string|max:160', 'build_number' => 'nullable|string|max:100',
              'testing_environment' => 'nullable|string|max:100', 'test_case_references' => 'nullable|string|max:1000',
        ]);
        $targetProject = array_key_exists('project_id', $data) && $data['project_id'] ? PmProject::findOrFail($data['project_id']) : $ticket->project;
        $qaEditor = $this->canEditQat($request->user(), $ticket);
        if ($ticket->project_id && array_key_exists('project_id', $data) && ! $data['project_id']) {
            throw ValidationException::withMessages(['project_id' => 'A linked ticket cannot be returned to unassigned intake.']);
        }
        if (! $targetProject && (isset($data['release_id']) || isset($data['developer_id']) || isset($data['qa_id']) || isset($data['project_manager_id']))) {
            throw ValidationException::withMessages(['project_id' => 'Assign a project before setting a release or assignee.']);
        }
        if ($ticket->project_id) {
            $analyst = $ticket->project && PmsAccess::role($request->user(), $ticket->project) === 'Business Analyst';
            if ($analyst) {
                abort_if(array_diff(array_keys($data), [
                    'lock_version', 'summary', 'description', 'business_justification', 'current_behavior',
                    'proposed_behavior', 'acceptance_criteria', 'tags', 'reason', 'priority', 'due_date',
                      'release_id', 'cycle_id', 'developer_id', 'qa_id', 'project_manager_id', 'project_id',
                      'estimate_minutes', 'story_points', 'severity', 'business_impact', 'production_environment',
                      'detected_at', 'defect_type', 'preconditions', 'reproduction_steps', 'expected_result',
                      'actual_result', 'reproducibility', 'affected_platform', 'browser_device', 'build_number',
                      'testing_environment', 'test_case_references',
                ]), 403);
            } elseif ($qaEditor && ! PmsTicketAccess::manage($request->user(), $ticket)) {
                foreach (['priority', 'project_manager_id', 'developer_id', 'qa_id', 'story_points'] as $protected) {
                    if (array_key_exists($protected, $data)
                        && (string) ($data[$protected] ?? '') !== (string) ($ticket->{$protected} ?? '')) {
                        abort(403);
                    }
                    unset($data[$protected]);
                }
                abort_if(array_diff(array_keys($data), [
                    'lock_version', 'summary', 'description', 'severity', 'defect_type', 'preconditions',
                    'reproduction_steps', 'expected_result', 'actual_result', 'reproducibility',
                    'testing_environment', 'affected_platform', 'browser_device', 'build_number',
                    'test_case_references', 'release_id', 'cycle_id', 'reason',
                ]), 403);
            } else {
                abort_unless(PmsTicketAccess::manage($request->user(), $ticket), 403);
            }
            if (isset($data['project_id']) && (int) $data['project_id'] !== (int) $ticket->project_id) {
                  abort_unless($targetProject && ! $targetProject->archived_at
                      && PmsAccess::allows($request->user(), $targetProject, 'edit_ticket'), 403);
                  $this->assertReleaseMutable($ticket->release_id);
                if (blank($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Explain moving a ticket to another project.']);
                }
            }
        } elseif ($targetProject) {
            abort_unless(PmsAccess::allows($request->user(), $targetProject, 'edit_issue'), 403);
            if ((! empty($data['developer_id']) || ! empty($data['qa_id'])) && ! PmsAccess::allows($request->user(), $targetProject, 'manage')) {
                abort(403);
            }
        } else {
            abort_unless(PmsAccess::administrator($request->user()) || (int) $ticket->reporter_id === $request->user()->id, 403);
            abort_if(array_diff(array_keys($data), ['lock_version', 'project_id', 'summary', 'description', 'tags', 'reason']), 403);
        }
        if ($ticket->classification_key === 'QAT' && $targetProject) {
            app(PmsQatAssignmentResolver::class)->applyAndValidate($request->user(), $targetProject, $data, $ticket);
        }
        $this->syncReleaseCycle($data);
        $this->checkRelationships($targetProject, $data, $ticket);
        if (array_key_exists('release_id', $data) && (int) $data['release_id'] !== (int) $ticket->release_id) {
            $this->assertReleaseMutable($ticket->release_id);
            $this->assertReleaseMutable($data['release_id']);
        }
        if (array_key_exists('tags', $data)) {
            $data['tags'] = $this->normalizeTags($data['tags']);
        }
        if ($ticket->project && in_array($ticket->classification_key, ['REQ', 'ENH', 'EHN'], true)
            && PmsAccess::role($request->user(), $ticket->project) === 'Business Analyst') {
            $review = app(\App\Services\PmsChangeReviewService::class)->submit($request, $ticket, 'update', $data);
            return $request->expectsJson() ? response()->json(['review_id' => $review->id, 'status' => 'pending'], 202)
                : back()->with('success', 'Ticket changes submitted for Project Manager review.');
        }
        $oldAssignee = $ticket->developer_id;
        $oldRelease = $ticket->release_id;
        DB::transaction(function () use ($request, $ticket, $data, $targetProject): void {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and retry.']);
            }
            unset($data['lock_version']);
            $reason = $data['reason'] ?? null;
            unset($data['reason']);
            if (array_key_exists('release_id', $data) && $locked->release_id && (int) $locked->release_id !== (int) $data['release_id'] && blank($reason)) {
                throw ValidationException::withMessages(['reason' => 'Explain a release move or removal.']);
            }
            if (! $locked->project_id && $targetProject) {
                $data['project_id'] = $targetProject->id;
            }
            if ($locked->project_id && $targetProject && (int) $locked->project_id !== $targetProject->id) {
                $data['release_id'] = null;
                $data['cycle_id'] = null;
                $data['global_cycle_id'] = null;
                $data['global_cycle_stage_id'] = null;
                $data['developer_id'] = null;
                $data['qa_id'] = null;
                if (! array_key_exists('project_manager_id', $data)) $data['project_manager_id'] = null;
            }
            if (array_key_exists('global_cycle_id', $data) && (int) $data['global_cycle_id'] !== (int) $locked->global_cycle_id) {
                $data['global_cycle_stage_id'] = null;
            }
              $before = $locked->only(array_keys($data));
            if ($locked->classification_key === 'QAT' && array_key_exists('qa_id', $data)
                && (int) $data['qa_id'] !== (int) $locked->qa_id) {
                $data['qa_estimated_completion_at'] = null;
                $data['qa_estimated_by'] = null;
            }
            $locked->forceFill($data);
            if (! $locked->original_global_cycle_id && $locked->global_cycle_id) {
                $locked->original_global_cycle_id = $locked->global_cycle_id;
            }
            $locked->lock_version++;
            $locked->save();
            foreach (['developer_id', 'qa_id'] as $field) {
                if ((int) ($before[$field] ?? 0) === (int) ($locked->{$field} ?? 0)) continue;
                PmsTicketActivity::record($locked->id, 'assignment', $field,
                    $before[$field] ?? null, $locked->{$field}, actor: $request->user());
                DB::table('pm_issue_history')->insert(['issue_id' => $locked->id,
                    'actor_id' => $request->user()->id, 'event' => $field.'_changed',
                    'changes' => json_encode(['before' => $before[$field] ?? null,
                        'after' => $locked->{$field}]), 'created_at' => now()]);
            }
            if (array_key_exists('project_manager_id', $data)
                && (int) ($before['project_manager_id'] ?? 0) !== (int) ($locked->project_manager_id ?? 0)) {
                DB::table('pm_issue_history')->insert(['issue_id' => $ticket->id, 'actor_id' => $request->user()->id,
                    'event' => 'project_manager_changed', 'changes' => json_encode([
                        'before' => $before['project_manager_id'] ?? null, 'after' => $locked->project_manager_id,
                    ]), 'created_at' => now()]);
                $this->audit($request, $locked, 'ticket.project_manager_changed', [
                    'from' => $before['project_manager_id'] ?? null, 'to' => $locked->project_manager_id,
                ]);
            }
            DB::table('pm_issue_history')->insert(['issue_id' => $ticket->id, 'actor_id' => $request->user()->id, 'event' => 'updated', 'reason' => $reason, 'changes' => json_encode(['before' => $before, 'after' => $locked->only(array_keys($data))]), 'created_at' => now()]);
            $this->audit($request, $locked, 'ticket.updated', ['fields' => array_keys($data)]);
        });
        $fresh = $ticket->fresh();
        if ($fresh->developer_id && $fresh->developer_id !== $oldAssignee && (int) $fresh->developer_id !== $request->user()->id) {
            AppNotificationService::notifyUser($fresh->developer_id, "Assigned to {$fresh->reference}", $fresh->summary, route('pms.tickets.show', $fresh), 'project_management', $request->user()->id);
        }
        if ($fresh->release_id !== $oldRelease) {
            app(PmsTicketNotificationService::class)->notifyParticipants($fresh, $request->user(),
                "{$fresh->reference} release changed", $fresh->summary);
        }

        return $request->expectsJson() ? response()->json($fresh->load('projectManager:id,name')) : back()->with('success', 'Ticket updated.');
    }

    public function updateQatResolution(Request $request, PmWorkItem $ticket)
    {
        abort_unless($ticket->classification_key === 'QAT' && PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless(PmsTicketAccess::manage($request->user(), $ticket) || PmsTicketAccess::verify($request->user(), $ticket), 403);
        $data = $request->validate([
            'lock_version' => 'required|integer|min:0',
            'qat_resolution' => ['present', 'nullable', Rule::in(PmsQatWorkflow::RESOLUTIONS)],
        ]);
        $fresh = DB::transaction(function () use ($request, $ticket, $data): PmWorkItem {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and retry.']);
            }
            $before = $locked->qat_resolution;
            $locked->qat_resolution = $data['qat_resolution'];
            $locked->lock_version++;
            $locked->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $locked->id, 'actor_id' => $request->user()->id, 'event' => 'resolution_updated',
                'changes' => json_encode(['before' => $before, 'after' => $locked->qat_resolution]), 'created_at' => now(),
            ]);
            $this->audit($request, $locked, 'ticket.resolution_updated', ['before' => $before, 'after' => $locked->qat_resolution]);

            return $locked;
        });

        return $request->expectsJson() ? response()->json($fresh) : back()->with('success', 'QAT resolution updated.');
    }

    public function updateQaEstimate(Request $request, PmWorkItem $ticket)
    {
        abort_unless($ticket->classification_key === 'QAT' && PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless((int) $ticket->qa_id === $request->user()->id && PmsTicketAccess::verify($request->user(), $ticket), 403);
        $data = $request->validate([
            'lock_version' => 'required|integer|min:0', 'qa_estimated_completion_at' => 'present|nullable|date',
        ]);
        $fresh = DB::transaction(function () use ($request, $ticket, $data): PmWorkItem {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            abort_unless((int) $locked->qa_id === $request->user()->id, 403);
            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This ticket changed. Refresh and retry.']);
            }
            $before = $locked->qa_estimated_completion_at?->toIso8601String();
            $locked->qa_estimated_completion_at = filled($data['qa_estimated_completion_at'])
                ? \Illuminate\Support\Carbon::parse($data['qa_estimated_completion_at'], config('app.timezone'))->utc() : null;
            $locked->qa_estimated_by = $locked->qa_estimated_completion_at ? $request->user()->id : null;
            $locked->lock_version++;
            $locked->save();
            DB::table('pm_issue_history')->insert([
                'issue_id' => $locked->id, 'actor_id' => $request->user()->id, 'event' => 'qa_estimate_updated',
                'changes' => json_encode(['before' => $before, 'after' => $locked->qa_estimated_completion_at?->toIso8601String()]),
                'created_at' => now(),
            ]);
            $this->audit($request, $locked, 'ticket.qa_estimate_updated', []);

            return $locked;
        });

        return $request->expectsJson() ? response()->json($fresh) : back()->with('success', 'QA estimate updated.');
    }

    public function transition(Request $request, PmWorkItem $ticket, PmsQatWorkflow $qat, PmsDeliveryWorkflow $delivery, PmsWorkflow $legacy)
    {
        $data = $request->validate(['status' => 'required|string|max:40', 'reason' => 'nullable|string|max:5000', 'lock_version' => 'required|integer|min:0']);
        $updated = $ticket->classification_key === 'QAT'
            ? $qat->transition($ticket, $request->user(), $data['status'], (int) $data['lock_version'], $data['reason'] ?? null)
            : (PmsDeliveryWorkflow::applies($ticket) ? $delivery->transition($ticket, $request->user(), $data) : $legacy->transition($ticket, $request->user(), $data));
        app(PmsTicketNotificationService::class)->notifyParticipants($updated, $request->user(),
            "{$updated->reference} moved to {$updated->status}", $updated->summary);

        return $request->expectsJson() ? response()->json($updated) : back()->with('success', 'Ticket status updated.');
    }

    public function verify(Request $request, PmWorkItem $ticket, PmsQatWorkflow $qat, PmsAttachmentService $attachments)
    {
        $data = $request->validate([
            'lock_version' => 'required|integer|min:0', 'result' => ['required', Rule::in(['Passed', 'Failed', 'Blocked'])],
            'build' => 'nullable|string|max:100', 'environment' => 'nullable|string|max:100',
            'remarks' => 'required|string|max:10000', 'tested_at' => 'nullable|date',
            'regression_result' => ['nullable', Rule::in(['Passed', 'Failed', 'Not Run', 'Not Applicable'])],
            'attachments' => 'nullable|array', 'attachments.*' => 'file',
        ]);
        $files = $request->file('attachments', []);
        $attachments->validateFiles($files);
        unset($data['attachments']);
        $storedPaths = [];
        try {
            $updated = DB::transaction(function () use ($request, $ticket, $qat, $attachments, $files, $data, &$storedPaths) {
                $updated = $qat->verify($ticket, $request->user(), $data);
                $attachments->storeMany($updated, $request->user(), $files, null, $storedPaths);

                return $updated;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        app(PmsTicketNotificationService::class)->notifyParticipants($updated, $request->user(),
            "{$updated->reference} QA result: {$data['result']}", $data['remarks']);

        return $request->expectsJson() ? response()->json($updated->load('verifications')) : back()->with('success', 'QA verification recorded.');
    }

    public function link(Request $request, PmWorkItem $ticket, PmsTicketRelationships $relationships)
    {
        abort_unless(PmsTicketAccess::comment($request->user(), $ticket), 403);
        $data = $request->validate([
            'target_issue_id' => 'nullable|required_without:target_issue_key|integer|exists:pm_work_items,id',
            'target_issue_key' => 'nullable|required_without:target_issue_id|string|max:48',
            'relation' => ['required', Rule::in(['qat_dependency', 'sub_issue', 'related_to', 'blocks', 'relates_to', 'duplicate_of', 'caused_by', 'parent_task', 'tests_requirement', 'regression_of'])],
        ]);
        $target = ! empty($data['target_issue_key'])
            ? PmWorkItem::where('issue_key', strtoupper(trim($data['target_issue_key'])))
                ->orWhere('reference', strtolower(trim($data['target_issue_key'])))->firstOrFail()
            : PmWorkItem::findOrFail($data['target_issue_id']);
        abort_unless(PmsTicketAccess::view($request->user(), $target), 403);
        if ($data['relation'] === 'blocks' && $this->wouldCreateBlockCycle($ticket->id, $target->id)) {
            throw ValidationException::withMessages(['target_issue_id' => 'This link would create a circular blocker chain.']);
        }
        $relationships->add($ticket, $target, $data['relation'], $request->user());
        $this->audit($request, $ticket, 'ticket.linked', ['target_issue_id' => $target->id, 'relation' => $data['relation']]);

        return $request->expectsJson() ? response()->json(['linked' => true]) : back()->with('success', 'Related ticket linked.');
    }

    public function unlink(Request $request, PmWorkItem $ticket, int $link, PmsTicketRelationships $relationships)
    {
        $relationships->remove($ticket, $link, $request->user());
        $this->audit($request, $ticket, 'ticket.unlinked', ['link_id' => $link]);
        return $request->expectsJson() ? response()->json(['removed' => true]) : back()->with('success', 'Relationship removed.');
    }

    private function wouldCreateBlockCycle(int $sourceId, int $targetId): bool
    {
        $seen = [];
        $frontier = [$targetId];
        while ($frontier) {
            $next = [];
            foreach ($frontier as $id) {
                if ($id === $sourceId) {
                    return true;
                }
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                foreach (DB::table('pm_ticket_links')->where('relation', 'blocks')->where('source_issue_id', $id)->pluck('target_issue_id') as $linkedId) {
                    $next[] = (int) $linkedId;
                }
            }
            $frontier = $next;
        }

        return false;
    }

    private function rules(): array
    {
        return [
            'classification_key' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'project_id' => 'required_if:classification_key,QAT|nullable|integer|exists:pm_projects,id', 'release_id' => 'nullable|integer|exists:pm_releases,id',
            'cycle_id' => 'nullable|integer|exists:pm_cycles,id', 'summary' => 'required|string|max:255',
            'description' => 'required_if:classification_key,REQ,TSK|nullable|string|max:50000', 'priority' => ['required', Rule::in(array_keys(PmsPriority::OPTIONS))],
            'severity' => 'nullable|string|max:20', 'developer_id' => 'nullable|integer|exists:users,id',
            'qa_id' => 'nullable|integer|exists:users,id', 'project_manager_id' => 'nullable|integer|exists:users,id', 'due_date' => 'nullable|date',
            'estimate_minutes' => 'nullable|integer|min:0|max:10000000', 'business_justification' => 'required_if:classification_key,REQ|nullable|string|max:20000',
            'acceptance_criteria' => 'required_if:classification_key,REQ,ENH|nullable|string|max:20000',
            'current_behavior' => 'required_if:classification_key,ENH|nullable|string|max:20000',
            'proposed_behavior' => 'required_if:classification_key,ENH|nullable|string|max:20000',
            'detected_at' => 'required_if:classification_key,PRD|nullable|date',
            'business_impact' => 'required_if:classification_key,PRD|nullable|string|max:20000',
            'production_environment' => 'required_if:classification_key,PRD|nullable|string|max:255',
            'defect_type' => ['required_if:classification_key,QAT', Rule::exists('pm_defect_types', 'name')->where('active', true)], 'preconditions' => 'nullable|string|max:20000',
            'reproduction_steps' => 'required_if:classification_key,QAT|nullable|string|max:50000',
            'expected_result' => 'required_if:classification_key,QAT|nullable|string|max:50000',
            'actual_result' => 'required_if:classification_key,PRD,QAT|nullable|string|max:50000', 'reproducibility' => 'nullable|string|max:50',
            'affected_platform' => 'nullable|string|max:100', 'browser_device' => 'nullable|string|max:160',
            'build_number' => 'nullable|string|max:100', 'testing_environment' => 'nullable|string|max:100',
            'test_case_references' => 'nullable|string|max:1000', 'attachments' => 'nullable|array', 'attachments.*' => 'file',
            'test_cases' => 'nullable|array|max:10', 'test_cases.*' => 'file',
            'test_case_reviewer_id' => 'nullable|integer|exists:users,id',
            'linked_requirement_key' => 'nullable|string|max:48', 'linked_task_key' => 'nullable|string|max:48',
            'tags' => 'nullable|string|max:500',
            'creation_token' => 'nullable|uuid',
        ];
    }

    private function checkRelationships(?PmProject $project, array $data, ?PmWorkItem $current = null): void
    {
        if (! $project) {
            if (! empty($data['release_id']) || ! empty($data['cycle_id']) || ! empty($data['developer_id']) || ! empty($data['qa_id']) || ! empty($data['project_manager_id'])) {
                throw ValidationException::withMessages(['project_id' => 'Set a project before linking a release, cycle, or assignee.']);
            }

            return;
        }
        if (! empty($data['release_id']) && ! $project->releases()->whereKey($data['release_id'])->exists()) {
            throw ValidationException::withMessages(['release_id' => 'Choose a release from the selected project.']);
        }
        if (! empty($data['cycle_id']) && ! $project->cycles()->whereKey($data['cycle_id'])->exists()) {
            throw ValidationException::withMessages(['cycle_id' => 'Choose a cycle from the selected project.']);
        }
        if (array_key_exists('project_manager_id', $data) && ! empty($data['project_manager_id'])
            && (! $current || (int) $current->project_manager_id !== (int) $data['project_manager_id']
                || (int) $current->project_id !== $project->id)) {
            PmsTicketManager::validate((int) $data['project_manager_id'], $project);
        }
        foreach (['developer_id', 'qa_id'] as $field) {
            // Historical assignments remain visible and valid until someone changes them.
            if (! array_key_exists($field, $data) || empty($data[$field])
                || $current && (int) $current->{$field} === (int) $data[$field]) continue;
            PmsPosition::validateAssignment((int) $data[$field], $field === 'qa_id' ? 'qa' : 'developer', $project->id);
        }
    }

    private function assertReleaseMutable(?int $releaseId): void
    {
        if ($releaseId && PmRelease::whereKey($releaseId)->whereIn('status', ['Ready for Release', 'Approved', 'Released', 'Completed', 'Cancelled'])->exists()) {
            throw ValidationException::withMessages(['release_id' => 'This release scope is locked.']);
        }
    }

    private function syncReleaseCycle(array &$data): void
    {
        if (empty($data['release_id'])) {
            return;
        }
        $release = PmRelease::findOrFail($data['release_id']);
        if (! empty($data['cycle_id']) && (int) $data['cycle_id'] !== (int) $release->cycle_id) {
            throw ValidationException::withMessages(['cycle_id' => 'The selected cycle must match the release cycle.']);
        }
        $data['cycle_id'] = $release->cycle_id;
        if ($release->global_cycle_id) {
            $data['global_cycle_id'] = $release->global_cycle_id;
        }
    }

    private function normalizeTags(?string $input): array
    {
        return collect(explode(',', $input ?? ''))->map(fn ($tag) => trim($tag))
            ->filter()->unique(fn ($tag) => mb_strtolower($tag))->take(12)
            ->map(fn ($tag) => mb_substr($tag, 0, 40))->values()->all();
    }

    private function priorityCode(mixed $input): mixed
    {
        return match ($input) {
            'Low' => 'P4', 'Normal' => 'P3', 'High' => 'P2', 'Critical' => 'P1',
            default => $input,
        };
    }

    private function projects(Request $request)
    {
        $query = PmProject::whereNull('archived_at');
        if (! PmsAccess::administrator($request->user())) {
            $query->where(fn ($visible) => $visible
                ->whereHas('members', fn ($members) => $members->where('users.id', $request->user()->id))
                ->orWhere('owner_id', $request->user()->id));
        }

        return $query->orderBy('name')->get(['id', 'key', 'name', 'manager_id']);
    }

    private function canEditQat(User $user, PmWorkItem $ticket): bool
    {
        if ($ticket->classification_key !== 'QAT' || ! $ticket->project
            || ! PmsAccess::allows($user, $ticket->project, 'edit_issue')) {
            return false;
        }
        $role = PmsAccess::role($user, $ticket->project);
        return in_array($role, ['QA', 'QA Lead', 'QA Engineer'], true)
            && ($role === 'QA Lead' || (int) $ticket->qa_id === $user->id
                || (int) $ticket->reporter_id === $user->id);
    }

    private function audit(Request $request, PmWorkItem $ticket, string $action, array $details): void
    {
        DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'issue', 'subject_id' => $ticket->id,
            'details' => json_encode($details + ['reference' => $ticket->reference, 'title' => $ticket->summary,
                'project_id' => $ticket->project_id, 'project_reference' => $ticket->project?->project_reference,
                'actor_name' => $request->user()->name]), 'created_at' => now()]);
    }
}
