<?php

namespace App\Http\Controllers\Api\Pms\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Pms\ProjectController;
use App\Http\Controllers\Pms\TicketController;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Services\PmsAttachmentService;
use App\Services\PmsDeliveryWorkflow;
use App\Services\PmsQatWorkflow;
use App\Services\PmsWorkflow;
use App\Support\PmsAccess;
use App\Support\PmsPosition;
use App\Support\PmsPriority;
use App\Support\PmsTicketAccess;
use App\Support\PmsStatusRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectManagementController extends Controller
{
    private function ok(Request $request, string $code, string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true, 'code' => $code, 'message' => $message, 'data' => $data,
            'errors' => (object) [], 'request_id' => $request->attributes->get('request_id') ?: (string) \Illuminate\Support\Str::uuid(),
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }

    private function priority(mixed $value): mixed
    {
        return match (strtolower((string) $value)) {
            'low' => 'P4', 'normal', 'medium' => 'P3', 'high' => 'P2', 'critical' => 'P1',
            default => $value,
        };
    }

    private function projectStatus(mixed $value): mixed
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return PmsStatusRegistry::key('project', (int) $value) ?? $value;
        }
        return match (strtolower((string) $value)) {
            'planned' => 'Planned', 'approved' => 'Approved', 'active' => 'Active',
            'on hold' => 'On Hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled',
            default => $value,
        };
    }

    private function statusInput(Request $request, string $domain): ?string
    {
        if (! $request->exists('status_code')) return null;
        $code = filter_var($request->input('status_code'), FILTER_VALIDATE_INT);
        $key = $code !== false ? PmsStatusRegistry::key($domain, $code) : null;
        if ($key === null) throw ValidationException::withMessages(['status_code' => 'Choose a valid status code for this workflow.']);
        if ($request->exists('status') && PmsStatusRegistry::code($domain, $request->input('status')) !== $code) {
            throw ValidationException::withMessages(['status' => 'Status and status_code must identify the same workflow state.']);
        }
        return $key;
    }

    private function projectData(PmProject $project): array
    {
        $project->loadMissing('owner:id,name', 'currentGlobalCycle:id,cycle_key,name,status,start_date,end_date',
            'currentRelease:id,release_key,name,status,global_cycle_id',
            'globalCycles:id,cycle_key,name,status', 'associatedReleases:id,release_key,name,status');
        $manager = $project->manager_id ? User::with('pmsProfile')->find($project->manager_id) : null;
        return [
            'project_id' => $project->id, 'project_key' => $project->key, 'name' => $project->name,
            'description' => $project->description, 'objective' => $project->objective,
            'category' => $project->category, 'priority' => strtolower(PmsPriority::label($project->priority)),
            'priority_code' => $project->priority, 'status' => $project->status,
            'status_code' => $project->status_code, 'status_label' => $project->status,
            'planned_start_date' => $project->planned_start_date?->toDateString(),
            'target_date' => $project->target_date?->toDateString(), 'related_module' => $project->related_module,
            'manager' => $manager ? ['user_id' => $manager->id, 'full_name' => $manager->name,
                'job_position' => $manager->pmsProfile?->job_position] : null,
            'owner' => $project->owner ? ['user_id' => $project->owner->id, 'full_name' => $project->owner->name] : null,
            'current_global_cycle_id' => $project->current_global_cycle_id,
            'current_release_id' => $project->current_release_id,
            'development_cycle' => $project->currentGlobalCycle ? [
                'id' => $project->currentGlobalCycle->id, 'key' => $project->currentGlobalCycle->cycle_key,
                'name' => $project->currentGlobalCycle->name, 'status' => $project->currentGlobalCycle->status] : null,
            'related_cycles' => $project->globalCycles->map(fn ($cycle) => [
                'id' => $cycle->id, 'key' => $cycle->cycle_key, 'name' => $cycle->name,
                'current' => (int) $cycle->id === (int) $project->current_global_cycle_id])->all(),
            'release' => $project->currentRelease ? [
                'id' => $project->currentRelease->id, 'key' => $project->currentRelease->release_key,
                'name' => $project->currentRelease->name, 'status' => $project->currentRelease->status] : null,
            'lock_version' => $project->lock_version,
            'created_at' => $project->created_at?->toIso8601String(), 'updated_at' => $project->updated_at?->toIso8601String(),
        ];
    }

    public function updateProjectInfo(Request $request, ProjectController $projects): JsonResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['insert', 'update', 'delete'])]])['action'];
        $request->headers->set('Accept', 'application/json');
        if ($action === 'insert') {
            $request->merge(['key' => strtoupper((string) $request->input('project_key')), 'priority' => $this->priority($request->input('priority', 'normal'))]);
            if ($status = $this->statusInput($request, 'project')) $request->merge(['status' => $status]);
            if ($request->exists('status')) $request->merge(['status' => $this->projectStatus($request->input('status'))]);
            $projects->store($request);
            $project = PmProject::where('key', strtoupper((string) $request->input('project_key')))->firstOrFail();
            return $this->ok($request, 'PROJECT_CREATED', 'Project created successfully.', $this->projectData($project), 201);
        }

        $data = $request->validate(['project_id' => 'required|integer|min:1', 'expected_version' => 'nullable|integer|min:0']);
        $project = PmProject::findOrFail($data['project_id']);
        if (isset($data['expected_version']) && (int) $data['expected_version'] !== (int) $project->lock_version) abort(409, 'Project changed. Refresh and retry.');
        if ($action === 'delete') {
            $request->merge(['confirmation' => $project->key]);
            $response = $projects->trash($request, $project);
            if ($response->status() === 202) {
                return $this->ok($request, 'PROJECT_REVIEW_PENDING', 'Project deletion awaits Project Manager review.',
                    ['project_id' => $project->id, 'review_id' => $response->getData()->review_id], 202);
            }
            return $this->ok($request, 'PROJECT_DELETED', 'Project moved to Recently Deleted.', ['project_id' => $project->id]);
        }

        if ($status = $this->statusInput($request, 'project')) $request->merge(['status' => $status]);
        $changes = $request->only(['name', 'description', 'objective', 'category', 'priority', 'status',
            'planned_start_date', 'target_date', 'related_module', 'manager_id', 'owner_id',
            'current_global_cycle_id', 'current_release_id']);
        if ($changes === []) throw ValidationException::withMessages(['action' => 'Provide at least one project field to update.']);
        if (array_key_exists('priority', $changes)) $changes['priority'] = $this->priority($changes['priority']);
        if (array_key_exists('status', $changes)) $changes['status'] = $this->projectStatus($changes['status']);
        $existing = $project->only(['name', 'description', 'objective', 'category', 'priority', 'status',
            'related_module', 'manager_id']);
        $existing['planned_start_date'] = $project->planned_start_date?->toDateString();
        $existing['target_date'] = $project->target_date?->toDateString();
        $request->replace(array_merge($existing, $changes, ['lock_version' => $project->lock_version]));
        $response = $projects->update($request, $project);
        if ($response->status() === 202) {
            return $this->ok($request, 'PROJECT_REVIEW_PENDING', 'Project changes await Project Manager review.',
                ['project_id' => $project->id, 'review_id' => $response->getData()->review_id], 202);
        }
        return $this->ok($request, 'PROJECT_UPDATED', 'Project updated successfully.', $this->projectData($project->fresh()));
    }

    public function getProjectInfo(Request $request): JsonResponse
    {
        $data = $request->validate(['project_id' => 'nullable|required_without:project_key|integer|min:1',
            'project_key' => 'nullable|required_without:project_id|string|max:12']);
        $query = PmProject::query();
        if (! empty($data['project_id'])) $query->whereKey($data['project_id']);
        if (! empty($data['project_key'])) $query->where('key', strtoupper($data['project_key']));
        $project = $query->firstOrFail();
        abort_unless(PmsAccess::allows($request->user(), $project, 'view'), 403);
        return $this->ok($request, 'PROJECT_RETRIEVED', 'Project information retrieved successfully.', $this->projectData($project));
    }

    public function getProjectTickets(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => 'required|integer|min:1', 'status' => 'nullable|string|max:40',
            'status_code' => 'nullable|integer|min:1',
            'priority' => 'nullable|string|max:20', 'ticket_type' => 'nullable|string|max:8',
            'developer_id' => 'nullable|integer|min:1', 'qa_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $project = PmProject::findOrFail($data['project_id']);
        abort_unless(PmsAccess::allows($request->user(), $project, 'view'), 403);
        if (isset($data['status_code'])) {
            $this->statusInput($request, 'ticket');
            unset($data['status']);
        }
        $query = $project->issues()->with(['developer:id,name', 'qa:id,name']);
        if (isset($data['status_code'])) $query->where('status_code', $data['status_code']);
        foreach (['status' => 'status', 'ticket_type' => 'classification_key', 'developer_id' => 'developer_id', 'qa_id' => 'qa_id'] as $input => $column) {
            if (isset($data[$input])) $query->where($column, $data[$input]);
        }
        if (isset($data['priority'])) $query->where('priority', $this->priority($data['priority']));
        $page = $query->orderByDesc('id')->paginate($data['per_page'] ?? 20);
        $items = $page->getCollection()->map(fn ($ticket) => $this->ticketData($ticket))->all();
        return $this->ok($request, 'PROJECT_TICKETS_RETRIEVED', 'Project tickets retrieved successfully.', [
            'items' => $items, 'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    private function ticketData(PmWorkItem $ticket): array
    {
        $ticket->loadMissing(['developer:id,name', 'qa:id,name', 'projectManager:id,name', 'project:id,key,name']);
        return [
            'ticket_id' => $ticket->id, 'ticket_key' => $ticket->issue_key,
            'ticket_reference' => $ticket->reference, 'ticket_url' => route('pms.tickets.show', $ticket),
            'title' => $ticket->summary,
            'description' => $ticket->description, 'ticket_type' => $ticket->classification_key,
            'status' => $ticket->status, 'status_code' => $ticket->status_code,
            'status_label' => $ticket->status, 'resolution' => $ticket->qat_resolution,
            'hold_previous_status_code' => $ticket->hold_previous_status_code,
            'hold_reason' => $ticket->hold_reason, 'held_at' => $ticket->held_at,
            'assignee_ids' => DB::table('pm_ticket_contributors')->where('issue_id', $ticket->id)
                ->where('assignment_type', 'pic')->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
            'priority' => strtolower(PmsPriority::label($ticket->priority)), 'priority_code' => $ticket->priority,
            'developer_assignee' => $ticket->developer ? ['user_id' => $ticket->developer->id, 'full_name' => $ticket->developer->name] : null,
            'qa_assignee' => $ticket->qa ? ['user_id' => $ticket->qa->id, 'full_name' => $ticket->qa->name] : null,
            'project_manager' => $ticket->projectManager ? ['user_id' => $ticket->projectManager->id, 'full_name' => $ticket->projectManager->name] : null,
            'project' => $ticket->project ? ['project_id' => $ticket->project->id, 'project_key' => $ticket->project->key,
                'name' => $ticket->project->name] : null,
            'due_date' => $ticket->due_date, 'lock_version' => $ticket->lock_version,
            'created_at' => $ticket->created_at?->toIso8601String(), 'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }

    public function updateTicketInfo(Request $request, TicketController $tickets, PmsAttachmentService $attachments): JsonResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['insert', 'update', 'delete'])]])['action'];
        $request->headers->set('Accept', 'application/json');
        if ($action === 'insert') {
            $request->merge([
                'classification_key' => $request->input('ticket_type'), 'summary' => $request->input('title'),
                'priority' => $this->priority($request->input('priority', 'normal')),
                'developer_id' => $request->input('developer_assignee_id'), 'qa_id' => $request->input('qa_assignee_id'),
            ]);
            $response = $tickets->store($request, $attachments);
            $ticket = PmWorkItem::findOrFail($response->getData()->id);
            return $this->ok($request, 'TICKET_CREATED', 'Ticket created successfully.', $this->ticketData($ticket), $response->status());
        }
        $data = $request->validate(['ticket_id' => 'required|integer|min:1', 'expected_version' => 'nullable|integer|min:0']);
        $ticket = PmWorkItem::findOrFail($data['ticket_id']);
        abort_unless(PmsTicketAccess::view($request->user(), $ticket), 403);
        if (isset($data['expected_version']) && (int) $data['expected_version'] !== (int) $ticket->lock_version) abort(409, 'Ticket changed. Refresh and retry.');
        if ($action === 'delete') {
            $request->merge(['confirmation' => $ticket->issue_key]);
            $response = $tickets->destroy($request, $ticket);
            if ($response->status() === 202) {
                return $this->ok($request, 'TICKET_REVIEW_PENDING', 'Ticket deletion awaits Project Manager review.',
                    ['ticket_id' => $ticket->id, 'review_id' => $response->getData()->review_id], 202);
            }
            return $this->ok($request, 'TICKET_DELETED', 'Ticket moved to Recently Deleted.', ['ticket_id' => $ticket->id]);
        }
        if ($status = $this->statusInput($request, 'ticket')) $request->merge(['status' => $status]);
        $changes = $request->only(['project_id', 'project_manager_id', 'description', 'priority', 'due_date', 'reason', 'release_id', 'cycle_id',
            'business_justification', 'acceptance_criteria', 'current_behavior', 'proposed_behavior', 'severity',
            'defect_type', 'reproduction_steps', 'expected_result', 'actual_result', 'testing_environment']);
        foreach (['title' => 'summary', 'developer_assignee_id' => 'developer_id', 'qa_assignee_id' => 'qa_id'] as $from => $to) {
            if ($request->exists($from)) $changes[$to] = $request->input($from);
        }
        if (isset($changes['priority'])) $changes['priority'] = $this->priority($changes['priority']);
        $status = $request->input('status');
        $resolution = $request->input('resolution');
        $hasResolution = $request->exists('resolution');
        if ($changes === [] && $status === null && ! $hasResolution) throw ValidationException::withMessages(['action' => 'Provide a ticket field to update.']);
        if ($status === 'On Hold' || ($ticket->status === 'On Hold' && $status !== null)) {
            if (array_diff(array_keys($changes), ['reason']) || $hasResolution) {
                throw ValidationException::withMessages(['action' => 'Submit ticket detail changes separately from pause or resume.']);
            }
            $holdData = $request->validate([
                'reason' => 'nullable|string|max:5000', 'blocker_id' => 'nullable|integer',
                'override_unresolved' => 'nullable|boolean',
            ]);
            $result = app(\App\Services\PmsTicketHoldService::class)->process($request, $ticket, [
                'action' => $status === 'On Hold' ? 'hold' : 'resume',
                'status' => $status, 'lock_version' => $ticket->lock_version,
            ] + $holdData);
            return $result['pending']
                ? $this->ok($request, 'TICKET_REVIEW_PENDING', 'Ticket workflow change awaits Project Manager review.',
                    ['ticket_id' => $ticket->id, 'review_id' => $result['review_id']], 202)
                : $this->ok($request, 'TICKET_UPDATED', 'Ticket workflow updated.', $this->ticketData($ticket->fresh()));
        }
        $analyst = $ticket->project && in_array($ticket->classification_key, ['REQ', 'ENH', 'EHN'], true)
            && PmsAccess::role($request->user(), $ticket->project) === 'Business Analyst';
        if ($analyst) {
            abort_if($status !== null || $hasResolution, 403, 'Submit detail changes for review before changing workflow status.');
            if ($changes === []) throw ValidationException::withMessages(['action' => 'Provide ticket details to submit for review.']);
            $request->replace(array_merge($changes, ['lock_version' => $ticket->lock_version]));
            $response = $tickets->update($request, $ticket);
            return $this->ok($request, 'TICKET_REVIEW_PENDING', 'Ticket changes await Project Manager review.',
                ['ticket_id' => $ticket->id, 'review_id' => $response->getData()->review_id], 202);
        }
        DB::transaction(function () use ($request, $tickets, $ticket, $changes, $status, $resolution, $hasResolution): void {
            if ($changes !== []) {
                $request->replace(array_merge($changes, ['lock_version' => $ticket->lock_version]));
                $tickets->update($request, $ticket);
                $ticket->refresh();
            }
            if ($status !== null && $status !== $ticket->status) {
                $request->replace(['status' => $status, 'lock_version' => $ticket->lock_version]);
                $tickets->transition($request, $ticket, app(PmsQatWorkflow::class), app(PmsDeliveryWorkflow::class), app(PmsWorkflow::class));
                $ticket->refresh();
            }
            if ($hasResolution) {
                $request->replace(['qat_resolution' => $resolution, 'lock_version' => $ticket->lock_version]);
                $tickets->updateQatResolution($request, $ticket);
            }
        });
        return $this->ok($request, 'TICKET_UPDATED', 'Ticket updated successfully.', $this->ticketData($ticket->fresh()));
    }

    public function getAssignableUsers(Request $request): JsonResponse
    {
        $data = $request->validate(['assignment_type' => ['required', Rule::in(['developer', 'qa', 'manager', 'project_owner'])],
            'project_id' => 'nullable|integer|min:1', 'search' => 'nullable|string|max:100',
            'department_id' => 'nullable|integer|exists:pms_departments,id',
            'job_position_id' => 'nullable|integer|exists:pms_job_positions,id']);
        $project = isset($data['project_id']) ? PmProject::findOrFail($data['project_id']) : null;
        if ($project) abort_unless(PmsAccess::allows($request->user(), $project, 'view'), 403);
        if ($data['assignment_type'] === 'project_owner') {
            abort_unless($project ? PmsAccess::manageOwner($request->user(), $project)
                : PmsAccess::administrator($request->user()), 403);
            $query = app(\App\Services\PmsProjectOwnerService::class)->candidateQuery();
            if (filled($data['search'] ?? null)) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($data['search'])).'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            }
            $users = $query->orderBy('name')->limit(50)->get()->map(fn ($user) => [
                'user_id' => $user->id, 'full_name' => $user->name,
                'job_position' => $user->pmsProfile?->job_position,
                'display_name' => $user->name, 'is_active' => true,
            ])->all();
            return $this->ok($request, 'ASSIGNABLE_USERS_RETRIEVED', 'Eligible Project Owner candidates retrieved.', $users);
        }
        $query = User::query()->with('pmsProfile')->where('status', 1)
            ->whereHas('pmsProfile', function ($profile) use ($data, $project): void {
                $profile->where('status', 'active');
                if ($data['assignment_type'] !== 'manager') {
                    $profile->where(function ($positions) use ($data, $project): void {
                        $positions->whereIn('job_position_id', DB::table('pms_job_positions')->where('active', true)
                            ->whereIn('code', PmsPosition::ASSIGNABLE_CODES[$data['assignment_type']])->select('id'))
                            ->orWhere(fn ($legacy) => $legacy->whereNull('job_position_id')
                                ->whereIn('job_position', PmsPosition::assignmentPositions($data['assignment_type'])));
                        if ($data['assignment_type'] === 'qa' && $project) {
                            $positions->orWhereIn('user_id', DB::table('pm_project_members')
                                ->where('project_id', $project->id)
                                ->whereIn('role', ['QA', 'QA Lead', 'QA Engineer'])->select('user_id'));
                        }
                    });
                }
            })
            ->whereDoesntHave('projectManagementIdentity', fn ($q) => $q->where('status', '<>', 'active'));
        if ($data['assignment_type'] === 'manager') {
            $query->whereIn('users.id', DB::table('pm_project_members')->where('role', 'Project Manager')
                ->when($project, fn ($members) => $members->where('project_id', $project->id))->select('user_id'));
        }
        if ($project) {
            $query->whereIn('users.id', DB::table('pm_project_members')->where('project_id', $project->id)->select('user_id'));
        } elseif (! PmsAccess::administrator($request->user())) {
            $visible = DB::table('pm_project_members')->where('user_id', $request->user()->id)->select('project_id');
            $query->whereIn('users.id', DB::table('pm_project_members')->whereIn('project_id', $visible)->select('user_id'));
        }
        if (! empty($data['department_id'])) {
            $query->whereHas('pmsProfile', fn ($profile) => $profile->where('department_id', $data['department_id']));
        }
        if (! empty($data['job_position_id'])) {
            $query->whereHas('pmsProfile', fn ($profile) => $profile->where('job_position_id', $data['job_position_id']));
        }
        if (! empty($data['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($data['search'])).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)
                ->orWhereHas('projectManagementIdentity', fn ($identity) => $identity->where('email', 'like', $term))
                ->orWhereHas('pmsProfile', fn ($profile) => $profile->where('job_position', 'like', $term)
                    ->orWhere('department', 'like', $term)));
        }
        $users = $query->orderBy('name')->limit(50)->get()->map(fn ($user) => [
            'user_id' => $user->id, 'full_name' => $user->name, 'job_position' => $user->pmsProfile->job_position,
            'department' => $user->pmsProfile->department,
            'display_name' => $user->name.' - '.($user->pmsProfile->job_position ?: ($data['assignment_type'] === 'manager' ? 'Project Manager' : 'Team member')), 'is_active' => true,
        ])->all();
        return $this->ok($request, 'ASSIGNABLE_USERS_RETRIEVED', 'Assignable users retrieved successfully.', $users);
    }
}
