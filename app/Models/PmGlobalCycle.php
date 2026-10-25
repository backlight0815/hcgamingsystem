<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmGlobalCycle extends Model
{
    use \App\Models\Concerns\HasPmsStatusCode;

    public function pmsStatusDomain(): string { return 'organization_cycle'; }
    protected $table = 'pm_global_cycles';

    protected $guarded = ['id', 'cycle_key', 'baseline_start_date', 'baseline_end_date', 'lock_version'];

    protected $casts = [
        'status_code' => 'integer',
        'start_date' => 'date', 'end_date' => 'date',
        'baseline_start_date' => 'date', 'baseline_end_date' => 'date',
        'archived_at' => 'datetime', 'completed_at' => 'datetime',
        'production_target_date' => 'date',
        'automated' => 'boolean',
    ];

    public function stages()
    {
        return $this->hasMany(PmGlobalCycleStage::class, 'cycle_id')->orderBy('sort_order');
    }

    public function projects()
    {
        return $this->belongsToMany(PmProject::class, 'pm_global_cycle_projects', 'cycle_id', 'project_id')->withTimestamps();
    }

    public function tickets()
    {
        return $this->hasMany(PmWorkItem::class, 'global_cycle_id');
    }

    public function projectPlans()
    {
        return $this->hasMany(PmCycle::class, 'global_cycle_id');
    }

    public function releases()
    {
        return $this->hasMany(PmRelease::class, 'global_cycle_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
