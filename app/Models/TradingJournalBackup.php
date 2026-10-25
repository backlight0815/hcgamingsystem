<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradingJournalBackup extends Model
{
    use HasFactory;

    protected $table = 'trading_journals_backup';

    protected $fillable = [
        'type',
        'user_id',
        'original_journal_id',
        'prop_firm_phase',
        'prop_firm_model_id',
        'prop_firm_phase_rule_id',
        'archive_batch_uuid',
        'archive_reason',
        'archived_at',
        'status',
        'close_handling',
        'time_input_timezone',
        'time_input_offset_minutes',
        'open_date',
        'close_date',
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
        'capital',
    ];

    protected $casts = [
        'open_date' => 'datetime',
        'close_date' => 'datetime',
        'archived_at' => 'datetime',
        'confidence_level' => 'integer',
        'discipline_rating' => 'integer',
        'mistake_tags' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
}
