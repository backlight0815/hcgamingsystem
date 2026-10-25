<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmGlobalCycleStage extends Model
{
    protected $table = 'pm_global_cycle_stages';

    protected $guarded = ['id'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'completed_at' => 'datetime'];

    public function cycle()
    {
        return $this->belongsTo(PmGlobalCycle::class, 'cycle_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
