<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmCycle;
use App\Models\PmGlobalCycle;
use App\Models\PmWorkItem;
use App\Support\PmsAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrganizationSettingsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $departments = DB::table('pms_departments')->orderBy('name')->get();
        $positions = DB::table('pms_job_positions')->orderBy('name')->get();
        $links = DB::table('pms_department_positions')->get()->groupBy('department_id');
        $memberCounts = DB::table('pms_profiles')->whereNotNull('department_id')
            ->selectRaw('department_id, count(*) as total')->groupBy('department_id')->pluck('total', 'department_id');
        $legacyPlans = PmCycle::with('project:id,key,name')->whereNull('global_cycle_id')
            ->withCount('issues')->orderByDesc('start_date')->paginate(20, ['*'], 'legacy_page');
        $cycleOptionsByProject = DB::table('pm_global_cycle_projects')
            ->join('pm_global_cycles', 'pm_global_cycles.id', '=', 'pm_global_cycle_projects.cycle_id')
            ->orderByDesc('pm_global_cycles.start_date')
            ->get(['pm_global_cycle_projects.project_id', 'pm_global_cycles.id', 'pm_global_cycles.cycle_key',
                'pm_global_cycles.name', 'pm_global_cycles.status'])->groupBy('project_id');

        return $request->expectsJson() ? response()->json(compact('departments', 'positions', 'links', 'memberCounts', 'legacyPlans', 'cycleOptionsByProject'))
            : view('pms.organization-settings', compact('departments', 'positions', 'links', 'memberCounts', 'legacyPlans', 'cycleOptionsByProject'));
    }

    public function options(Request $request)
    {
        $departments = DB::table('pms_departments')->where('active', true)->orderBy('name')->get(['id', 'code', 'name']);
        $positions = DB::table('pms_job_positions')->join('pms_department_positions', 'pms_department_positions.position_id', '=', 'pms_job_positions.id')
            ->where('pms_job_positions.active', true)->whereIn('pms_department_positions.department_id', $departments->pluck('id'))
            ->orderBy('pms_job_positions.name')->get(['pms_job_positions.id', 'pms_job_positions.name', 'pms_department_positions.department_id']);

        return response()->json(compact('departments', 'positions'));
    }

    public function department(Request $request, ?int $department = null)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('pms_departments', 'code')->ignore($department)],
            'name' => ['required', 'string', 'max:255', Rule::unique('pms_departments', 'name')->ignore($department)],
            'description' => 'nullable|string|max:5000', 'active' => 'required|boolean',
            'position_ids' => 'sometimes|array', 'position_ids.*' => 'integer|distinct|exists:pms_job_positions,id',
        ]);
        $id = DB::transaction(function () use ($request, $data, $department): int {
            $before = $department ? DB::table('pms_departments')->where('id', $department)->first() : null;
            if ($department) abort_unless($before, 404);
            $fields = collect($data)->only(['code', 'name', 'description', 'active'])->all();
            $fields['code'] = strtoupper($fields['code']);
            $fields['updated_at'] = now();
            if ($department) {
                $usedOutsideSelection = DB::table('pms_profiles')->where('department_id', $department)
                    ->whereNotNull('job_position_id')->whereNotIn('job_position_id', $data['position_ids'] ?? [])->exists();
                $invitedOutsideSelection = DB::table('pms_invitations')->where('department_id', $department)
                    ->whereNull('revoked_at')->where('expires_at', '>', now())
                    ->whereColumn('used_count', '<', 'max_uses')
                    ->whereNotNull('job_position_id')->whereNotIn('job_position_id', $data['position_ids'] ?? [])->exists();
                if ($usedOutsideSelection || $invitedOutsideSelection) {
                    throw ValidationException::withMessages(['position_ids' => 'Keep positions assigned to current members or pending invitations, or move and revoke those records first.']);
                }
                DB::table('pms_departments')->where('id', $department)->update($fields);
                if ($before->name !== $fields['name']) {
                    DB::table('pms_profiles')->where('department_id', $department)->update([
                        'department' => $fields['name'], 'updated_at' => now(),
                    ]);
                }
                $id = $department;
            } else {
                $id = DB::table('pms_departments')->insertGetId($fields + ['created_by' => $request->user()->id, 'created_at' => now()]);
            }
            DB::table('pms_department_positions')->where('department_id', $id)->delete();
            foreach ($data['position_ids'] ?? [] as $positionId) {
                DB::table('pms_department_positions')->insert(['department_id' => $id, 'position_id' => $positionId]);
            }
            $this->audit($request, $department ? 'department.updated' : 'department.created', $id, $before, $fields);
            return $id;
        });

        return $request->expectsJson() ? response()->json(DB::table('pms_departments')->find($id), $department ? 200 : 201)
            : back()->with('success', 'Department saved.');
    }

    public function position(Request $request, ?int $position = null)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('pms_job_positions', 'code')->ignore($position)],
            'name' => ['required', 'string', 'max:255', Rule::unique('pms_job_positions', 'name')->ignore($position)],
            'description' => 'nullable|string|max:5000', 'active' => 'required|boolean',
            'department_ids' => 'required|array|min:1', 'department_ids.*' => 'integer|distinct|exists:pms_departments,id',
        ]);
        $id = DB::transaction(function () use ($request, $data, $position): int {
            $before = $position ? DB::table('pms_job_positions')->where('id', $position)->first() : null;
            if ($position) abort_unless($before, 404);
            if ($before && $before->code !== $data['code']) {
                throw ValidationException::withMessages(['code' => 'Position codes are permanent. Update the name or create a new position.']);
            }
            $fields = collect($data)->only(['code', 'name', 'description', 'active'])->all();
            $fields['code'] = strtoupper($fields['code']);
            $fields['updated_at'] = now();
            if ($position) {
                $usedOutsideSelection = DB::table('pms_profiles')->where('job_position_id', $position)
                    ->whereNotNull('department_id')->whereNotIn('department_id', $data['department_ids'])->exists();
                $invitedOutsideSelection = DB::table('pms_invitations')->where('job_position_id', $position)
                    ->whereNull('revoked_at')->where('expires_at', '>', now())
                    ->whereColumn('used_count', '<', 'max_uses')
                    ->whereNotNull('department_id')->whereNotIn('department_id', $data['department_ids'])->exists();
                if ($usedOutsideSelection || $invitedOutsideSelection) {
                    throw ValidationException::withMessages(['department_ids' => 'Keep departments assigned to current members or pending invitations, or move and revoke those records first.']);
                }
                DB::table('pms_job_positions')->where('id', $position)->update($fields);
                if ($before->name !== $fields['name']) {
                    DB::table('pms_profiles')->where('job_position_id', $position)->update([
                        'job_position' => $fields['name'], 'updated_at' => now(),
                    ]);
                }
                $id = $position;
            } else {
                $id = DB::table('pms_job_positions')->insertGetId($fields + ['created_at' => now()]);
            }
            DB::table('pms_department_positions')->where('position_id', $id)->delete();
            foreach ($data['department_ids'] as $departmentId) {
                DB::table('pms_department_positions')->insert(['department_id' => $departmentId, 'position_id' => $id]);
            }
            $this->audit($request, $position ? 'position.updated' : 'position.created', $id, $before, $fields);
            return $id;
        });

        return $request->expectsJson() ? response()->json(DB::table('pms_job_positions')->find($id), $position ? 200 : 201)
            : back()->with('success', 'Job position saved.');
    }

    public function profile(Request $request, int $user)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $data = $request->validate([
            'department_id' => 'required|integer|exists:pms_departments,id',
            'job_position_id' => 'required|integer|exists:pms_job_positions,id',
        ]);
        self::validatePair($data['department_id'], $data['job_position_id']);
        DB::transaction(function () use ($request, $user, $data): void {
            $before = DB::table('pms_profiles')->where('user_id', $user)->lockForUpdate()->first();
            abort_unless($before, 404);
            $department = DB::table('pms_departments')->find($data['department_id']);
            $position = DB::table('pms_job_positions')->find($data['job_position_id']);
            DB::table('pms_profiles')->where('user_id', $user)->update([
                'department_id' => $department->id, 'department' => $department->name,
                'job_position_id' => $position->id, 'job_position' => $position->name, 'updated_at' => now(),
            ]);
            $this->audit($request, 'profile.organization_updated', $user, $before, $data);
        });

        return $request->expectsJson() ? response()->json(['saved' => true]) : back()->with('success', 'Member organization details updated.');
    }

    public function reconcileCycle(Request $request, PmCycle $cycle)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $data = $request->validate([
            'global_cycle_id' => 'required|integer|exists:pm_global_cycles,id',
            'reason' => 'required|string|max:5000',
        ]);
        $master = PmGlobalCycle::findOrFail($data['global_cycle_id']);
        DB::transaction(function () use ($request, $cycle, $master, $data): void {
            $plan = PmCycle::whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ($plan->global_cycle_id) {
                throw ValidationException::withMessages(['global_cycle_id' => 'This project plan is already linked.']);
            }
            if (! DB::table('pm_global_cycle_projects')->where('cycle_id', $master->id)
                ->where('project_id', $plan->project_id)->exists()) {
                throw ValidationException::withMessages(['global_cycle_id' => 'Assign the project to this organization cycle first.']);
            }
            $tickets = PmWorkItem::where('cycle_id', $plan->id)->lockForUpdate()->get();
            if ($tickets->contains(fn ($ticket) => $ticket->global_cycle_id && (int) $ticket->global_cycle_id !== $master->id)) {
                throw ValidationException::withMessages(['global_cycle_id' => 'Some tickets belong to another organization cycle. Review those tickets individually first.']);
            }
            $plan->global_cycle_id = $master->id;
            $plan->save();
            foreach ($tickets as $ticket) {
                if (! $ticket->global_cycle_id) {
                    $ticket->global_cycle_id = $master->id;
                    $ticket->original_global_cycle_id ??= $master->id;
                    $ticket->lock_version++;
                    $ticket->save();
                    DB::table('pm_issue_history')->insert([
                        'issue_id' => $ticket->id, 'actor_id' => $request->user()->id,
                        'event' => 'organization_cycle_reconciled', 'reason' => $data['reason'],
                        'changes' => json_encode(['from_cycle_id' => null, 'to_cycle_id' => $master->id,
                            'project_cycle_id' => $plan->id]), 'created_at' => now(),
                    ]);
                }
            }
            DB::table('pm_cycle_history')->insert([
                'cycle_id' => $plan->id, 'actor_id' => $request->user()->id, 'event' => 'organization_cycle_reconciled',
                'before' => json_encode(['global_cycle_id' => null]),
                'after' => json_encode(['global_cycle_id' => $master->id]), 'reason' => $data['reason'],
                'created_at' => now(),
            ]);
            $this->audit($request, 'cycle.reconciled', $plan->id, null, [
                'global_cycle_id' => $master->id, 'ticket_count' => $tickets->count(), 'reason' => $data['reason'],
            ]);
        });

        return $request->expectsJson() ? response()->json(['linked' => true, 'global_cycle_id' => $master->id])
            : back()->with('success', 'Historical project plan linked to the organization cycle.');
    }

    public static function validatePair(int $departmentId, int $positionId): void
    {
        if (! DB::table('pms_departments')->where('id', $departmentId)->where('active', true)->exists()
            || ! DB::table('pms_job_positions')->where('id', $positionId)->where('active', true)->exists()
            || ! DB::table('pms_department_positions')->where('department_id', $departmentId)->where('position_id', $positionId)->exists()) {
            throw ValidationException::withMessages(['job_position_id' => 'Choose an active position in the selected department.']);
        }
    }

    private function audit(Request $request, string $action, int $id, ?object $before, array $after): void
    {
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $request->user()->id, 'action' => $action,
            'subject_type' => str_contains($action, 'department') ? 'department' : (str_contains($action, 'position') ? 'position' : (str_contains($action, 'cycle') ? 'cycle' : 'profile')),
            'subject_id' => $id, 'details' => json_encode(['before' => $before, 'after' => $after]), 'created_at' => now(),
        ]);
    }
}
