<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmCyclePhase extends Model
{
    protected $table = 'pm_cycle_phases';

    protected $guarded = ['id'];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'signed_off' => 'boolean',
        'signed_off_at' => 'datetime',
    ];

    public function cycle()
    {
        return $this->belongsTo(PmCycle::class, 'cycle_id');
    }
}
