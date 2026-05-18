<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingPositionApplication extends Model
{
    use HasFactory;

    public const ROLE_LEADERSHIP = 760;
    public const ROLE_RECRUITER = 770;

    public const POSITION_LEADERSHIP = 'leadership';
    public const POSITION_RECRUITER = 'recruiter';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'requested_position',
        'requested_role_id',
        'status',
        'first_trade_date',
        'trade_count_snapshot',
        'strategy_summary',
        'trade_history_summary',
        'personality_summary',
        'marketing_plan',
        'client_support_plan',
        'supporting_document_path',
        'reviewed_by',
        'review_note',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'first_trade_date' => 'date',
        'trade_count_snapshot' => 'integer',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isLeadership(): bool
    {
        return $this->requested_position === self::POSITION_LEADERSHIP;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Pending Review',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            default => 'warning',
        };
    }

    public function requestedPositionLabel(): string
    {
        return static::positionLabels()[$this->requested_position] ?? ucfirst($this->requested_position);
    }

    public static function positionLabels(): array
    {
        return [
            self::POSITION_LEADERSHIP => 'Leadership',
            self::POSITION_RECRUITER => 'Recruiter',
        ];
    }

    public static function roleForPosition(string $position): int
    {
        return $position === self::POSITION_LEADERSHIP
            ? self::ROLE_LEADERSHIP
            : self::ROLE_RECRUITER;
    }

    public static function tradingMemberRoles(): array
    {
        return [self::ROLE_LEADERSHIP, self::ROLE_RECRUITER, 750];
    }

    public static function recruiterRoles(): array
    {
        return [self::ROLE_LEADERSHIP, self::ROLE_RECRUITER];
    }

    public static function leaderRoles(): array
    {
        return [self::ROLE_LEADERSHIP];
    }
}
