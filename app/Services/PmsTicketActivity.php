<?php

namespace App\Services;

use App\Models\User;
use App\Support\PmsActivityPresenter;
use Illuminate\Support\Facades\DB;

class PmsTicketActivity
{
    public static function record(int $issueId, string $type, ?string $field = null, mixed $old = null, mixed $new = null, array $metadata = [], ?User $actor = null): void
    {
        $actor ??= auth()->user();
        if (in_array($field, ['status', 'status_code', 'developer_id', 'qa_id', 'qa_user_id',
            'project_manager_id', 'owner_id', 'project_owner_id', 'priority', 'project_id', 'release_id',
            'cycle_id', 'development_cycle_id', 'global_cycle_id', 'development_started_at',
            'development_completed_at', 'qa_estimated_completion_at', 'completed_at'], true)
            && ! isset($metadata['labels']) && ($old !== null || $new !== null)) {
            $metadata['labels'] = [
                'old' => PmsActivityPresenter::value($field, self::value($old)),
                'new' => PmsActivityPresenter::value($field, self::value($new)),
            ];
        }
        DB::table('pm_ticket_activity')->insert([
            'issue_id' => $issueId,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'event_type' => $type,
            'field_name' => $field,
            'old_value' => self::value($old),
            'new_value' => self::value($new),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
        ]);
        DB::table('pm_work_items')->where('id', $issueId)->update(['updated_at' => now()]);
    }

    private static function value(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
