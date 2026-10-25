<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PmsActivityPresenter
{
    private const LABELS = [
        'status' => 'ticket status', 'status_code' => 'ticket status',
        'developer_id' => 'Assigned Developer', 'qa_id' => 'Assigned QA', 'qa_user_id' => 'Assigned QA',
        'project_manager_id' => 'Project Manager', 'owner_id' => 'Project Owner',
        'project_owner_id' => 'Project Owner', 'priority' => 'ticket priority',
        'development_started_at' => 'development start date',
        'development_completed_at' => 'development completion date',
        'qa_estimated_completion_at' => 'QA estimated completion date',
        'release_id' => 'Release', 'cycle_id' => 'Development Cycle', 'development_cycle_id' => 'Development Cycle',
        'global_cycle_id' => 'Organization Cycle', 'project_id' => 'Project',
        'assigned_to' => 'Assignee', 'created_by' => 'Creator', 'updated_by' => 'Updated By',
        'due_date' => 'due date', 'created_at' => 'creation date',
    ];

    private const USER_FIELDS = ['developer_id', 'qa_id', 'qa_user_id', 'project_manager_id', 'owner_id',
        'project_owner_id', 'assigned_to', 'created_by', 'updated_by', 'reporter_id'];
    private const DATE_FIELDS = ['development_started_at', 'development_completed_at',
        'qa_estimated_completion_at', 'qa_completed_at', 'qat_deployed_at',
        'production_deployed_at', 'completed_at', 'created_at'];

    public static function lookups(iterable $events): array
    {
        $ids = ['users' => [], 'pm_projects' => [], 'pm_releases' => [],
            'pm_cycles' => [], 'pm_global_cycles' => []];
        foreach ($events as $event) {
            $table = self::lookup($event->field_name)[0] ?? null;
            if (! $table) continue;
            foreach ([$event->old_value, $event->new_value] as $value) {
                if (ctype_digit((string) $value)) $ids[$table][] = (int) $value;
            }
        }
        $columns = ['users' => 'name', 'pm_projects' => 'project_reference',
            'pm_releases' => 'release_key', 'pm_cycles' => 'cycle_key',
            'pm_global_cycles' => 'cycle_key'];
        $result = [];
        foreach ($ids as $table => $values) {
            $result[$table] = $values ? DB::table($table)->whereIn('id', array_unique($values))
                ->pluck($columns[$table], 'id')->all() : [];
        }
        return $result;
    }

    public static function label(?string $field): string
    {
        return self::LABELS[$field] ?? Str::of((string) $field)->replace('_', ' ')->title()->toString();
    }

    public static function description(object $event): string
    {
        $metadata = json_decode($event->metadata ?? '{}', true) ?: [];
        if ($event->event_type === 'created') return 'created this ticket.';
        if ($event->event_type === 'comments') return match ($metadata['action'] ?? '') {
            'added' => 'Comment added.', 'removed' => 'Comment removed.', default => 'Comment updated.',
        };
        if ($event->event_type === 'attachments') return ($metadata['action'] ?? '') === 'added'
            ? 'Evidence attached.' : 'Evidence removed.';
        if ($event->event_type === 'relationships') return 'updated a ticket relationship.';
        $field = $event->field_name;
        if (in_array($field, ['developer_id', 'qa_id', 'qa_user_id'], true)) return 'assigned a '.($field === 'developer_id' ? 'developer.' : 'QA Engineer.');
        if ($field === 'owner_id' || $field === 'project_owner_id') return 'changed the Project Owner.';
        return 'changed the '.lcfirst(self::label($field)).'.';
    }

    public static function value(?string $field, mixed $value, ?array $metadata = null, string $side = '', ?array $lookups = null): string
    {
        if ($side && filled($metadata['labels'][$side] ?? null)) return (string) $metadata['labels'][$side];
        if ($value === null || $value === '') return in_array($field, self::DATE_FIELDS, true) ? 'Not set' : 'Unassigned';
        if (in_array($field, ['status', 'status_code'], true)) {
            return ctype_digit((string) $value)
                ? (PmsStatusRegistry::key('ticket', (int) $value) ?? 'Former status (code '.$value.')') : (string) $value;
        }
        if ($field === 'priority') return in_array((string) $value, ['Low', 'Normal', 'High', 'Critical'], true)
            ? (string) $value : PmsPriority::label((string) $value);
        if (in_array($field, self::USER_FIELDS, true)) {
            $name = $lookups === null ? DB::table('users')->where('id', $value)->value('name')
                : ($lookups['users'][$value] ?? null);
            return $name ?: 'Former user (ID: '.$value.')';
        }
        $lookup = self::lookup($field);
        if ($lookup) return ($lookups === null ? DB::table($lookup[0])->where('id', $value)->value($lookup[1])
            : ($lookups[$lookup[0]][$value] ?? null)) ?: 'Former '.self::label($field).' (ID: '.$value.')';
        if (in_array($field, self::DATE_FIELDS, true)) {
            $raw = (string) $value;
            if (str_starts_with($raw, '"')) $raw = json_decode($raw, true) ?: $raw;
            try {
                return PmsDateTime::format($raw, true);
            } catch (\Throwable) {
                return 'Recorded date unavailable';
            }
        }
        if ($field === 'due_date') return (string) $value; // date-only; no invented clock time
        if (in_array($value, ['0', '1'], true) && in_array($field, ['is_active', 'approved', 'blocked'], true)) return $value === '1' ? 'Yes' : 'No';
        return (string) $value;
    }

    private static function lookup(?string $field): ?array
    {
        if (in_array($field, self::USER_FIELDS, true)) return ['users', 'name'];
        return match ($field) {
            'project_id' => ['pm_projects', 'project_reference'],
            'release_id' => ['pm_releases', 'release_key'],
            'cycle_id', 'development_cycle_id' => ['pm_cycles', 'cycle_key'],
            'global_cycle_id' => ['pm_global_cycles', 'cycle_key'],
            default => null,
        };
    }
}
