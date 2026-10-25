<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PmProject extends Model
{
    use SoftDeletes;
    use \App\Models\Concerns\HasPmsStatusCode;

    public function pmsStatusDomain(): string { return 'project'; }

    protected $table = 'pm_projects';

    protected $guarded = ['id', 'project_reference'];

    protected static function booted(): void
    {
        static::creating(function (self $project): void {
            $number = \Illuminate\Support\Facades\DB::table('pm_project_reference_numbers')
                ->insertGetId(['created_at' => now()]);
            $project->project_reference = 'PJ'.$number;
        });
        static::created(function (self $project): void {
            \Illuminate\Support\Facades\DB::table('pm_project_reference_numbers')
                ->where('id', (int) substr($project->project_reference, 2))
                ->update(['project_id' => $project->id]);
        });
        static::updating(function (self $project): void {
            if ($project->isDirty('project_reference')) {
                throw new \LogicException('Project references are immutable.');
            }
        });
    }

    protected $casts = [
        'status_code' => 'integer',
        'planned_start_date' => 'date',
        'target_date' => 'date',
        'completed_at' => 'date',
        'archived_at' => 'datetime',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class, 'pm_project_members', 'project_id', 'user_id')->withPivot('role')->withTimestamps();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function issues()
    {
        return $this->hasMany(PmWorkItem::class, 'project_id');
    }

    public function cycles()
    {
        return $this->hasMany(PmCycle::class, 'project_id');
    }

    public function releases()
    {
        return $this->hasMany(PmRelease::class, 'project_id');
    }
}
