<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PmsBlockers
{
    public const DEPENDENCY_COMPLETE = ['QAT Done', 'Production Done', 'Verified', 'Closed', 'Resolved', 'Done', 'Completed', 'Released'];

    public function activeTicketIds(): Collection
    {
        $manual = DB::table('pm_ticket_blockers')->whereNull('resolved_at')->pluck('issue_id');
        $dependency = DB::table('pm_ticket_links as links')
            ->join('pm_work_items as target', 'target.id', '=', 'links.target_issue_id')
            ->where('links.relation', 'qat_dependency')->whereNull('target.deleted_at')
            ->whereNotIn('target.status', self::DEPENDENCY_COMPLETE)->pluck('links.source_issue_id');

        return $manual->merge($dependency)->map(fn ($id) => (int) $id)->unique()->values();
    }
}
