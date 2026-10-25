<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropFirmModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function phaseRules(): HasMany
    {
        return $this->hasMany(PropFirmPhaseRule::class)->orderBy('sort_order')->orderBy('id');
    }
}
