<?php

namespace App\Services;

use App\Models\PmRelease;

class PmsReleaseService
{
    public function readiness(PmRelease $release): array
    {
        $release->loadMissing(['cycle.phases', 'globalCycle.stages', 'tickets']);
        $tickets = $release->tickets;
        $scope = $tickets->whereIn('classification_key', ['REQ', 'ENH', 'TSK', 'FTR']);
        $scopeDone = $scope->whereIn('status', ['Production Done', 'Completed'])->count();
        $qat = $tickets->where('classification_key', 'QAT');
        $qatVerified = $qat->whereIn('status', ['QAT Done', 'Production Done', 'Verified', 'Closed'])->count();
        $critical = $tickets->whereIn('priority', ['P0', 'P1'])
            ->whereNotIn('status', ['QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed', 'Cancelled', 'Duplicate', 'Rejected'])->count();
        $phases = $release->cycle?->phases;
        $signed = $release->globalCycle
            ? $release->globalCycle->stages->whereIn('stage_key', ['development', 'internal_testing', 'qat'])->where('completion_percent', 100)->count()
            : ($phases?->whereIn('phase_key', ['development', 'internal_testing', 'qat'])->where('signed_off', true)->count() ?? 0);
        $activeBlockers = $tickets->whereIn('id', app(PmsBlockers::class)->activeTicketIds())->count();
        $checks = [
            ['key' => 'scope', 'label' => 'Required work completed', 'passed' => $scopeDone === $scope->count(), 'detail' => "{$scopeDone} of {$scope->count()} requirement, enhancement, and task items completed"],
            ['key' => 'critical', 'label' => 'Critical defects resolved', 'passed' => $critical === 0, 'detail' => "{$critical} unresolved Critical ticket(s)"],
            ['key' => 'blockers', 'label' => 'Work impediments resolved', 'passed' => $activeBlockers === 0, 'detail' => "{$activeBlockers} ticket(s) have an active blocker or unresolved dependency"],
            ['key' => 'qat', 'label' => 'QAT verification completed', 'passed' => $qatVerified === $qat->count(), 'detail' => "{$qatVerified} of {$qat->count()} QAT tickets verified or closed"],
            ['key' => 'phases', 'label' => 'Delivery phases signed off', 'passed' => $signed === 3, 'detail' => "{$signed} of 3 development, internal testing, and QAT phases signed off"],
            ['key' => 'notes', 'label' => 'Release notes prepared', 'passed' => filled($release->release_notes), 'detail' => filled($release->release_notes) ? 'Notes recorded' : 'Notes are missing'],
            ['key' => 'approval', 'label' => 'Deployment approved', 'passed' => (bool) $release->approved_at, 'detail' => $release->approved_at ? 'Approved '.$release->approved_at->toDateTimeString() : 'Approval is pending'],
        ];
        $failed = collect($checks)->where('passed', false)->pluck('key')->all();
        $state = $activeBlockers ? 'Blocked' : ($failed === [] ? 'Ready' : 'At Risk');

        return [
            'state' => $state, 'checks' => $checks,
            'metrics' => [
                'tickets' => $tickets->count(), 'scope_completed' => $scopeDone, 'scope_total' => $scope->count(),
                'qat_total' => $qat->count(), 'qat_verified' => $qatVerified, 'critical_open' => $critical,
                'open_tickets' => $tickets->whereNotIn('status', ['QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed', 'Cancelled', 'Duplicate', 'Rejected'])->count(),
                'active_blockers' => $activeBlockers,
            ],
        ];
    }

    public function draftNotes(PmRelease $release): string
    {
        $release->loadMissing('tickets');
        $lines = ["Release {$release->version} — {$release->name}", '', 'Delivered work:'];
        foreach ($release->tickets->whereIn('status', ['QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed']) as $ticket) {
            $lines[] = '- '.$ticket->reference.' '.$ticket->summary;
        }
        if (count($lines) === 3) {
            $lines[] = '- No delivered tickets have been recorded.';
        }

        return implode("\n", $lines);
    }
}
