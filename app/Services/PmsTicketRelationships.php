<?php

namespace App\Services;

use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsTicketAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PmsTicketRelationships
{
    public function add(PmWorkItem $source, PmWorkItem $target, string $relation, User $actor): int
    {
        abort_unless(PmsTicketAccess::comment($actor, $source) && PmsTicketAccess::comment($actor, $target), 403);
        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['target_issue_key' => 'A ticket cannot link to itself.']);
        }
        if ($relation === 'related_to' || $relation === 'relates_to') {
            [$source, $target] = $source->id < $target->id ? [$source, $target] : [$target, $source];
            $relation = 'related_to';
        }
        return DB::transaction(function () use ($source, $target, $relation, $actor): int {
            PmWorkItem::whereIn('id', [$source->id, $target->id])->orderBy('id')->lockForUpdate()->get();
            $duplicate = DB::table('pm_ticket_links')->where('source_issue_id', $source->id)->where('target_issue_id', $target->id)
                ->whereIn('relation', $relation === 'related_to' ? ['related_to', 'relates_to'] : [$relation])->exists();
            if ($relation === 'related_to') {
                $duplicate = $duplicate || DB::table('pm_ticket_links')->where('source_issue_id', $target->id)
                    ->where('target_issue_id', $source->id)->whereIn('relation', ['related_to', 'relates_to'])->exists();
            }
            if ($duplicate) {
                throw ValidationException::withMessages(['relation' => 'This relationship already exists.']);
            }
            if ($relation === 'sub_issue') {
                if ($target->parent_id && (int) $target->parent_id !== $source->id) {
                    throw ValidationException::withMessages(['target_issue_key' => 'This ticket already has a parent.']);
                }
                $seen = [];
                for ($cursor = $source; $cursor && !isset($seen[$cursor->id]); $cursor = $cursor->parent_id ? PmWorkItem::find($cursor->parent_id) : null) {
                    if ($cursor->id === $target->id) {
                        throw ValidationException::withMessages(['target_issue_key' => 'This would create a circular sub issue hierarchy.']);
                    }
                    $seen[$cursor->id] = true;
                }
                $target->parent_id = $source->id;
                $target->lock_version++;
                $target->save();
            }
            if ($relation === 'qat_dependency' && $this->reaches($target->id, $source->id, 'qat_dependency')) {
                throw ValidationException::withMessages(['target_issue_key' => 'This would create a circular QAT dependency.']);
            }
            $id = DB::table('pm_ticket_links')->insertGetId([
                'source_issue_id' => $source->id, 'target_issue_id' => $target->id,
                'relation' => $relation, 'created_by' => $actor->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ([$source, $target] as $ticket) {
                PmsTicketActivity::record($ticket->id, 'relationships', null, null, null, [
                    'action' => 'added', 'relation' => $relation, 'link_id' => $id,
                    'other_ticket_id' => $ticket->id === $source->id ? $target->id : $source->id,
                ], $actor);
            }
            return $id;
        });
    }

    public function remove(PmWorkItem $ticket, int $linkId, User $actor): void
    {
        DB::transaction(function () use ($ticket, $linkId, $actor): void {
            $link = DB::table('pm_ticket_links')->where('id', $linkId)->lockForUpdate()->first();
            abort_unless($link && in_array($ticket->id, [(int) $link->source_issue_id, (int) $link->target_issue_id], true), 404);
            $source = PmWorkItem::findOrFail($link->source_issue_id);
            $target = PmWorkItem::findOrFail($link->target_issue_id);
            abort_unless(PmsTicketAccess::manage($actor, $source) && PmsTicketAccess::manage($actor, $target), 403);
            if ($link->relation === 'sub_issue' && (int) $target->parent_id === $source->id) {
                $target->parent_id = null;
                $target->lock_version++;
                $target->save();
            }
            DB::table('pm_ticket_links')->where('id', $linkId)->delete();
            foreach ([$source, $target] as $item) {
                PmsTicketActivity::record($item->id, 'relationships', null, null, null, [
                    'action' => 'removed', 'relation' => $link->relation, 'link_id' => $linkId,
                    'other_ticket_id' => $item->id === $source->id ? $target->id : $source->id,
                ], $actor);
            }
        });
    }

    public function unresolvedDependencies(int $ticketId)
    {
        return PmWorkItem::whereIn('id', DB::table('pm_ticket_links')->where('source_issue_id', $ticketId)
            ->where('relation', 'qat_dependency')->select('target_issue_id'))
            ->whereNotIn('status', ['QAT Done', 'Production Done', 'Verified', 'Closed', 'Resolved', 'Done', 'Completed', 'Released'])
            ->get(['id', 'reference', 'issue_key', 'summary', 'status']);
    }

    private function reaches(int $from, int $destination, string $relation): bool
    {
        $seen = [];
        $queue = [$from];
        while ($queue) {
            $id = array_shift($queue);
            if ($id === $destination) {
                return true;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach (DB::table('pm_ticket_links')->where('source_issue_id', $id)->where('relation', $relation)->pluck('target_issue_id') as $next) {
                $queue[] = (int) $next;
            }
        }
        return false;
    }
}
