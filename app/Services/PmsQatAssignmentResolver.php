<?php

namespace App\Services;

use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PmsQatAssignmentResolver
{
    private const CURRENT_RELEASE = ['Development', 'Internal Testing', 'QAT Testing', 'Stabilization'];

    private const CURRENT_CYCLE = ['Active', 'Internal Testing', 'QAT'];

    private const EDITABLE_RELEASE = ['Planned', 'Development', 'Internal Testing', 'QAT Testing', 'Stabilization', 'On Hold'];

    public function eligibleProjects(User $user, Collection $projects): Collection
    {
        return $projects->filter(fn (PmProject $project) => PmsAccess::canCreateTicket($user, 'QAT', $project))->values();
    }

    public function forProjects(Collection $projects, Collection $releases, Collection $cycles): array
    {
        $result = [];
        foreach ($projects as $project) {
            $projectCycles = $cycles->where('project_id', $project->id)->values();
            $result[$project->id] = $this->fromCandidates(
                $releases->where('project_id', $project->id)->whereIn('status', self::EDITABLE_RELEASE)
                    ->filter(fn (PmRelease $release) => ! $release->cycle_id || $projectCycles->contains('id', $release->cycle_id))->values(),
                $projectCycles
            );
        }

        return $result;
    }

    public function forProject(PmProject $project, ?PmWorkItem $ticket = null): array
    {
        $cycles = PmCycle::where('project_id', $project->id)
            ->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->get(['id', 'project_id', 'status']);
        $releases = PmRelease::where('project_id', $project->id)
            ->whereIn('status', self::EDITABLE_RELEASE)->get(['id', 'project_id', 'cycle_id', 'status'])
            ->filter(fn (PmRelease $release) => ! $release->cycle_id || $cycles->contains('id', $release->cycle_id))->values();

        return $this->fromCandidates($releases, $cycles, $ticket);
    }

    public function applyAndValidate(User $user, PmProject $project, array &$data, ?PmWorkItem $ticket = null): void
    {
        if (! PmsAccess::allows($user, $project, 'edit_issue')) {
            throw ValidationException::withMessages(['project_id' => 'Choose a project assigned to your account.']);
        }
        $state = $this->forProject($project, $ticket);
        foreach (['release_id' => 'release', 'cycle_id' => 'cycle'] as $field => $key) {
            $chosen = $data[$field] ?? ($ticket?->{$field});
            if ($ticket && (int) $chosen === (int) $ticket->{$field}) {
                continue;
            }
            if ($field === 'cycle_id' && ! empty($data['release_id'])
                && (int) $data['release_id'] !== (int) $state['release']['id']) {
                continue;
            }
            $locked = $state[$key]['locked'];
            $expected = $state[$key]['id'];
            if ($locked && $expected && $chosen && (int) $chosen !== $expected
                && ! PmsAccess::allows($user, $project, 'manage')) {
                throw ValidationException::withMessages([$field => 'This '.$key.' is automatically assigned for the selected project.']);
            }
            if (! $chosen && $locked && $expected && ! $ticket) {
                $data[$field] = $expected;
            }
        }
        if (! empty($data['release_id']) && ! PmRelease::whereKey($data['release_id'])
            ->where('project_id', $project->id)->exists()) {
            throw ValidationException::withMessages(['release_id' => 'Choose a release from the selected project.']);
        }
        if (! empty($data['release_id']) && (int) $data['release_id'] !== (int) ($ticket?->release_id ?? 0)
            && ! PmRelease::whereKey($data['release_id'])->where('project_id', $project->id)
                ->whereIn('status', self::EDITABLE_RELEASE)
                ->where(function ($query) use ($project) {
                    $query->whereNull('cycle_id')->orWhereIn('cycle_id', PmCycle::where('project_id', $project->id)
                        ->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->select('id'));
                })->exists()) {
            throw ValidationException::withMessages(['release_id' => 'Choose an active release for the selected project.']);
        }
        if (! empty($data['cycle_id']) && ! PmCycle::whereKey($data['cycle_id'])
            ->where('project_id', $project->id)->exists()) {
            throw ValidationException::withMessages(['cycle_id' => 'Choose a development cycle from the selected project.']);
        }
        if (! empty($data['cycle_id']) && (int) $data['cycle_id'] !== (int) ($ticket?->cycle_id ?? 0)
            && ! PmCycle::whereKey($data['cycle_id'])->where('project_id', $project->id)
                ->whereIn('status', PmCycle::ASSIGNABLE_STATUSES)->exists()) {
            throw ValidationException::withMessages(['cycle_id' => 'Choose an active development cycle for the selected project.']);
        }
        if (! empty($data['release_id']) && empty($data['cycle_id']) && ! $ticket) {
            $data['cycle_id'] = PmRelease::whereKey($data['release_id'])->value('cycle_id');
        }
    }

    private function fromCandidates(Collection $releases, Collection $cycles, ?PmWorkItem $ticket = null): array
    {
        $release = $this->select($releases, self::CURRENT_RELEASE, $ticket?->release_id);
        $cycleCandidates = $cycles;
        if ($release['id']) {
            $selectedRelease = $releases->firstWhere('id', $release['id']);
            if ($selectedRelease?->cycle_id) {
                $cycleCandidates = $cycles->where('id', $selectedRelease->cycle_id)->values();
            }
        }
        $cycle = $this->select($cycleCandidates, self::CURRENT_CYCLE, $ticket?->cycle_id);
        if ($release['reason'] === 'historical') {
            $cycle['locked'] = false;
        }

        return ['release' => $release, 'cycle' => $cycle];
    }

    private function select(Collection $items, array $currentStatuses, ?int $existingId): array
    {
        if ($existingId && $items->contains('id', $existingId)) {
            return ['id' => $existingId, 'locked' => $items->count() === 1, 'reason' => 'existing'];
        }
        if ($existingId) {
            return ['id' => $existingId, 'locked' => false, 'reason' => 'historical'];
        }
        $current = $items->whereIn('status', $currentStatuses);
        if ($current->count() === 1) {
            return ['id' => (int) $current->first()->id, 'locked' => true, 'reason' => 'current'];
        }
        if ($items->count() === 1) {
            return ['id' => (int) $items->first()->id, 'locked' => true, 'reason' => 'unique'];
        }

        return ['id' => null, 'locked' => false, 'reason' => 'choice_required'];
    }
}
