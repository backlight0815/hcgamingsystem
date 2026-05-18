<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraderReadinessChecklistProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
        'completed_at',
        'self_rating',
        'demo_practiced',
        'reflection_note',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'self_rating' => 'integer',
        'demo_practiced' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(TraderReadinessChecklistItem::class, 'item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
