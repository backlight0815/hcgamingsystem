<?php

namespace App\Services;

use App\Models\PmGlobalCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\User;
use App\Support\PmsAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Keeps the project selectors and the existing cycle/release links consistent. */
final class PmsProjectPlanningService
{
    public function validateSelection(?int $cycleId, ?int $releaseId, ?PmProject $project = null, ?User $actor = null): array
    {
        $cycle = $cycleId ? PmGlobalCycle::whereNull('archived_at')->whereNotIn('status', ['Cancelled', 'Completed'])->find($cycleId) : null;
        if ($cycleId && ! $cycle) {
            throw ValidationException::withMessages(['current_global_cycle_id' => 'Choose an available Development Cycle.']);
        }
        $release = $releaseId ? PmRelease::whereNotIn('status', ['Cancelled'])->find($releaseId) : null;
        if ($releaseId && ! $release) {
            throw ValidationException::withMessages(['current_release_id' => 'Choose an available Release.']);
        }
        if ($release && $release->cycle_id && (! $project || (int) $release->project_id !== (int) $project->id)) {
            throw ValidationException::withMessages(['current_release_id' => 'This release uses a project-specific legacy plan and cannot be linked to another project during creation.']);
        }
        if ($release && $actor && ! PmsAccess::allows($actor, $release->project, 'view')
            && ! ($project && $project->associatedReleases()->whereKey($release->id)->exists())) {
            throw ValidationException::withMessages(['current_release_id' => 'The selected Release is outside your accessible projects.']);
        }
        $requiredCycleId = $release?->global_cycle_id ?: $release?->cycle?->global_cycle_id;
        if ($requiredCycleId && (int) $requiredCycleId !== (int) $cycleId) {
            throw ValidationException::withMessages(['current_release_id' => 'Select the release’s Development Cycle or choose another release.']);
        }
        return [$cycle, $release];
    }

    public function apply(PmProject $project, ?PmGlobalCycle $cycle, ?PmRelease $release, User $actor): void
    {
        $oldCycle = $project->current_global_cycle_id;
        $oldRelease = $project->current_release_id;
        if ($cycle) {
            DB::table('pm_global_cycle_projects')->insertOrIgnore([
                'cycle_id' => $cycle->id, 'project_id' => $project->id, 'assigned_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if ($release) {
            DB::table('pm_project_releases')->insertOrIgnore([
                'project_id' => $project->id, 'release_id' => $release->id, 'assigned_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if ((int) $oldCycle !== (int) ($cycle?->id ?? 0) || (int) $oldRelease !== (int) ($release?->id ?? 0)) {
            $project->forceFill(['current_global_cycle_id' => $cycle?->id, 'current_release_id' => $release?->id])->save();
        }
        if ((int) $oldCycle !== (int) ($cycle?->id ?? 0)) {
            $this->audit($project, $actor, 'project.cycle_changed', ['from_cycle_id' => $oldCycle,
                'to_cycle_id' => $cycle?->id, 'from_cycle' => PmGlobalCycle::find($oldCycle)?->cycle_key,
                'to_cycle' => $cycle?->cycle_key]);
            if ($cycle) app(PmsGlobalCycleService::class)->history($cycle, $actor, 'project_assigned',
                ['project_id' => $project->id, 'project_reference' => $project->project_reference, 'current' => true]);
        }
        if ((int) $oldRelease !== (int) ($release?->id ?? 0)) {
            $this->audit($project, $actor, 'project.release_changed', ['from_release_id' => $oldRelease,
                'to_release_id' => $release?->id, 'from_release' => PmRelease::find($oldRelease)?->release_key,
                'to_release' => $release?->release_key]);
        }
    }

    public function audit(PmProject $project, User $actor, string $action, array $details): void
    {
        DB::table('pm_audit_logs')->insert(['actor_id' => $actor->id, 'action' => $action,
            'subject_type' => 'project', 'subject_id' => $project->id,
            'details' => json_encode($details), 'created_at' => now()]);
    }
}
