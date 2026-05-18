<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingExamAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'trading_exam_attempt_id',
        'trading_exam_question_id',
        'selected_option_id',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TradingExamAttempt::class, 'trading_exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TradingExamQuestion::class, 'trading_exam_question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(TradingExamOption::class, 'selected_option_id');
    }
}
