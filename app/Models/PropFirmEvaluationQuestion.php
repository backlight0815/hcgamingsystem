<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropFirmEvaluationQuestion extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'user_id',
        'asked_by',
        'phase',
        'status',
        'title',
        'question',
        'answer',
        'answered_at',
        'resolved_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function trader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }
}
