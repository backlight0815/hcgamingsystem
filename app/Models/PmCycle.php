<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmCycle extends Model
{
    public const ASSIGNABLE_STATUSES = ['Planned', 'Active', 'Internal Testing', 'QAT'];

    use SoftDeletes;
    use \App\Models\Concerns\HasPmsStatusCode;

    public function pmsStatusDomain(): string { return 'cycle'; }

    protected $table = 'pm_cycles';

    protected $guarded = ['id', 'cycle_key', 'cycle_number', 'lock_version', 'baseline_start_date', 'baseline_end_date'];

    protected $casts = [
        'status_code' => 'integer',
        'start_date' => 'date',
        'target_end_date' => 'date',
        'baseline_start_date' => 'date',
        'baseline_end_date' => 'date',
        'forecast_end_date' => 'date',
        'actual_end_date' => 'date',
        'scope_baselined_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(PmProject::class, 'project_id');
    }

    public function globalCycle()
    {
        return $this->belongsTo(PmGlobalCycle::class, 'global_cycle_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function phases()
    {
        return $this->hasMany(PmCyclePhase::class, 'cycle_id')->orderBy('sort_order');
    }

    public function issues()
    {
        return $this->hasMany(PmWorkItem::class, 'cycle_id');
    }

    public function releases()
    {
        return $this->hasMany(PmRelease::class, 'cycle_id');
    }
}
