<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingExamAttempt extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id',
        'exam_date',
        'status',
        'total_questions',
        'score',
        'completed_at',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'completed_at' => 'datetime',
        'total_questions' => 'integer',
        'score' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TradingExamAnswer::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function percentage(): int
    {
        if ($this->total_questions <= 0) {
            return 0;
        }

        return (int) round(($this->score / $this->total_questions) * 100);
    }
}
