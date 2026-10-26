<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmRelease extends Model
{
    use SoftDeletes;
    use \App\Models\Concerns\HasPmsStatusCode;

    public function pmsStatusDomain(): string { return 'release'; }

    protected $table = 'pm_releases';

    protected $guarded = ['id', 'release_key', 'approved_by', 'approved_at', 'completed_by', 'completed_at'];

    protected $casts = [
        'status_code' => 'integer',
        'planned_start_date' => 'date', 'planned_deploy_date' => 'date', 'freeze_date' => 'date',
        'actual_deployed_at' => 'datetime', 'approved_at' => 'datetime', 'completed_at' => 'datetime',
    ];

    public function project()
    {
    return $this->belongsTo(PmProject::class);
    }

    public function associatedProjects()
    {
        return $this->belongsToMany(PmProject::class, 'pm_project_releases', 'release_id', 'project_id')->withTimestamps();
    }

    public function cycle()
    {
    return $this->belongsTo(PmCycle::class);
    }

    public function globalCycle()
    {
        return $this->belongsTo(PmGlobalCycle::class, 'global_cycle_id');
    }

    public function owner()
    {
    return $this->belongsTo(User::class, 'owner_id');
    }

    public function tickets()
    {
    return $this->hasMany(PmWorkItem::class, 'release_id');
    }
}
