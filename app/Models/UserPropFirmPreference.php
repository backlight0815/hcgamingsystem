<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPropFirmPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'prop_firm_model_id',
        'prop_firm_phase_rule_id',
        'prop_firm_model',
        'selected_at',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function propFirmModel(): BelongsTo
    {
        return $this->belongsTo(PropFirmModel::class);
    }

    public function propFirmPhaseRule(): BelongsTo
    {
        return $this->belongsTo(PropFirmPhaseRule::class);
    }
}
