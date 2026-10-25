<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingJournal extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const CLOSE_HANDLING_MANUAL = 'manual_close';

    public const CLOSE_HANDLING_PRESET = 'follow_pre_set';

    protected $table = 'trading_journals';

    protected $fillable = [
        'type',
        'user_id',
        'trading_account_id',
        'status',
        'close_handling',
        'time_input_timezone',
        'time_input_offset_minutes',
        'prop_firm_model_id',
        'prop_firm_phase_rule_id',
        'open_date',
        'close_date',
        'trade_date',
        'pair',
        'trading_reason_id',
        'direction',
        'entry_price',
        'exit_price',
        'lot_size',
        'pips',
        'profit_loss',
        'result',
        'notes',
        'emotion_before_entry',
        'emotion_during_trade',
        'emotion_after_trade',
        'confidence_level',
        'discipline_rating',
        'mistake_tags',
        'psychology_notes',
    ];

    protected $casts = [
        'open_date' => 'datetime',
        'close_date' => 'datetime',
        'confidence_level' => 'integer',
        'discipline_rating' => 'integer',
        'mistake_tags' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tradingAccount()
    {
        return $this->belongsTo(TradingAccount::class);
    }

    public function attachments()
    {
        return $this->hasMany(TradingJournalAttachment::class);
    }

    public function reason()
    {
        return $this->belongsTo(TradingReason::class, 'trading_reason_id');
    }

    public function propFirmModel()
    {
        return $this->belongsTo(PropFirmModel::class);
    }

    public function propFirmPhaseRule()
    {
        return $this->belongsTo(PropFirmPhaseRule::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED
            || ($this->close_date !== null && $this->result !== null);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_OPEN => 'Open Position',
            self::STATUS_CLOSED => 'Closed',
            default => 'Open Position',
        };
    }

    public function closeHandlingLabel(): string
    {
        return match ($this->close_handling) {
            self::CLOSE_HANDLING_PRESET => 'Follow Pre Set',
            default => 'Manual Close',
        };
    }

    public function psychologySummary(): string
    {
        $parts = collect([
            $this->emotion_before_entry ? 'Before: '.$this->emotion_before_entry : null,
            $this->emotion_during_trade ? 'During: '.$this->emotion_during_trade : null,
            $this->emotion_after_trade ? 'After: '.$this->emotion_after_trade : null,
            $this->discipline_rating ? 'Discipline '.$this->discipline_rating.'/10' : null,
        ])->filter();

        return $parts->isNotEmpty() ? $parts->implode(' | ') : 'No psychology notes';
    }
}
