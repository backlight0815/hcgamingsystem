<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TraderReadinessChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_key',
        'category',
        'title',
        'description',
        'why_it_matters',
        'suggested_action',
        'resource_route',
        'resource_label',
        'sort_order',
        'is_core',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_core' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function progress(): HasMany
    {
        return $this->hasMany(TraderReadinessChecklistProgress::class, 'item_id');
    }
}
