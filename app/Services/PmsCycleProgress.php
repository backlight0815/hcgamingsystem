<?php

namespace App\Services;

use App\Models\PmCycle;
use App\Models\PmWorkItem;
use Illuminate\Support\Collection;

/** Current ticket-state progress, separate from the cycle's planned phase percentages. */
final class PmsCycleProgress
{
    private ?Collection $blockedIds = null;
    public const LABELS = ['development' => 'Development', 'testing' => 'Testing / QAT', 'production' => 'Production'];

    public function forCycle(PmCycle $cycle): array
    {
        $cycle->loadMissing('issues');
        $blocked = $this->blockedIds ??= app(PmsBlockers::class)->activeTicketIds()->flip();
        $result = [];
        foreach (self::LABELS as $stage => $label) {
            $applicable = $cycle->issues->filter(fn (PmWorkItem $ticket) => $this->applies($ticket, $stage));
            $done = $applicable->filter(fn (PmWorkItem $ticket) => ! $blocked->has($ticket->id)
                && $this->complete($ticket, $stage))->count();
            $total = $applicable->count();
            $result[$stage] = [
                'label' => $label, 'total' => $total, 'completed' => $done,
                'in_progress' => $applicable->filter(fn (PmWorkItem $ticket) => ! $this->complete($ticket, $stage)
                    && $this->started($ticket, $stage))->count(),
                'awaiting' => $total - $done - $applicable->filter(fn (PmWorkItem $ticket) => ! $this->complete($ticket, $stage)
                    && $this->started($ticket, $stage))->count(),
                'percent' => $total ? (int) round($done * 100 / $total) : null,
            ];
        }
        return $result;
    }

    public function applies(PmWorkItem $ticket, string $stage): bool
    {
        if (in_array($ticket->status, ['Cancelled', 'Duplicate', 'Rejected'], true)) return false;
        if ($ticket->classification_key === 'TSK') return $stage === 'development';
        if ($ticket->classification_key === 'QAT' && ! $ticket->developer_id) return $stage === 'testing';
        return in_array($stage, array_keys(self::LABELS), true);
    }

    public function complete(PmWorkItem $ticket, string $stage): bool
    {
        if (in_array($ticket->status, ['Reopen', 'Reopened', 'On Hold', 'Blocked'], true)) return false;
        return match ($stage) {
            'development' => in_array($ticket->status, ['Ready for QAT', 'QAT in Progress', 'QAT In Progress',
                'QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed'], true),
            'testing' => in_array($ticket->status, ['QAT Done', 'Production Done', 'Completed', 'Verified', 'Closed'], true),
            'production' => in_array($ticket->status, ['Production Done', 'Completed'], true),
            default => false,
        };
    }

    private function started(PmWorkItem $ticket, string $stage): bool
    {
        return match ($stage) {
            'development' => in_array($ticket->status, ['In Progress', 'Development In Progress', 'Code Review', 'Reopen', 'Reopened'], true),
            'testing' => in_array($ticket->status, ['QAT in Progress', 'QAT In Progress', 'Retesting'], true),
            'production' => in_array($ticket->status, ['QAT Done', 'Deployment'], true),
            default => false,
        };
    }
}
