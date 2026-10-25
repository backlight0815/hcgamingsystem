<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmWorkItem extends Model
{
    use SoftDeletes;
    use \App\Models\Concerns\HasPmsStatusCode;

    public function pmsStatusDomain(): string { return 'ticket'; }

    protected $table = 'pm_work_items';

    protected $guarded = ['id', 'issue_key', 'reference', 'project_id', 'status', 'status_code', 'lock_version', 'reopen_count'];

    protected $casts = ['tags' => 'array', 'qa_estimated_completion_at' => 'datetime', 'status_code' => 'integer'];

    private array $activityBefore = [];

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $column = ctype_digit((string) $value) ? 'id' : (str_starts_with(strtolower((string) $value), 'sf') ? 'reference' : 'issue_key');

        return $this->where($column, $column === 'reference' ? strtolower((string) $value) : $value)->first();
    }

    protected static function booted(): void
    {
        static::created(fn (self $ticket) => \App\Services\PmsTicketActivity::record($ticket->id, 'created', null, null, $ticket->reference, ['summary' => $ticket->summary]));
        static::updating(function (self $ticket): void {
            foreach ($ticket->getDirty() as $field => $value) {
                $ticket->activityBefore[$field] = $ticket->getRawOriginal($field);
            }
        });
        static::updated(function (self $ticket): void {
            foreach ($ticket->getChanges() as $field => $new) {
                if (in_array($field, ['updated_at', 'lock_version'], true)) {
                    continue;
                }
                $old = $ticket->activityBefore[$field] ?? null;
                $type = match ($field) {
                    'status', 'status_code' => 'status', 'priority' => 'priority',
                    'developer_id', 'qa_id', 'project_manager_id' => 'assignment',
                    'project_id', 'cycle_id', 'global_cycle_id', 'global_cycle_stage_id', 'release_id' => 'association',
                    default => 'updated',
                };
                \App\Services\PmsTicketActivity::record($ticket->id, $type, $field, $old, $new);
            }
            $ticket->activityBefore = [];
        });
    }

    public function project()
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function cycle()
    {
        return $this->belongsTo(PmCycle::class, 'cycle_id');
    }

    public function globalCycle()
    {
        return $this->belongsTo(PmGlobalCycle::class, 'global_cycle_id');
    }

    public function globalCycleStage()
    {
        return $this->belongsTo(PmGlobalCycleStage::class, 'global_cycle_stage_id');
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    public function projectManager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function qa()
    {
        return $this->belongsTo(User::class, 'qa_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function release()
    {
        return $this->belongsTo(PmRelease::class, 'release_id');
    }

    public function comments()
    {
        return $this->hasMany(PmTicketComment::class, 'issue_id');
    }

    public function attachments()
    {
        return $this->hasMany(PmTicketAttachment::class, 'issue_id');
    }

    public function testCaseReview()
    {
        return $this->hasOne(PmTestCaseReview::class, 'issue_id');
    }

    public function verifications()
    {
        return $this->hasMany(PmQaVerification::class, 'issue_id');
    }
}
