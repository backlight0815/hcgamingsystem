<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingAppointment extends Model
{
    use HasFactory;

    public const TYPE_SLOT = 'slot';
    public const TYPE_PREFERRED = 'preferred';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'appointment_slot_id',
        'requester_id',
        'host_user_id',
        'reviewed_by',
        'cancelled_by',
        'request_type',
        'subject',
        'request_note',
        'scheduled_start_at',
        'scheduled_end_at',
        'duration_minutes',
        'status',
        'review_note',
        'cancellation_reason',
        'reviewed_at',
        'cancelled_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TradingAppointmentSlot::class, 'appointment_slot_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canCancelByPolicy(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true)
            && $this->scheduled_start_at
            && now()->lt($this->cancellationCutoffAt());
    }

    public function cancellationCutoffAt(): ?\Carbon\Carbon
    {
        return $this->scheduled_start_at
            ? $this->scheduled_start_at->copy()->startOfDay()
            : null;
    }

    public function cancellationDeadlineLabel(): string
    {
        $cutoffAt = $this->cancellationCutoffAt();

        return $cutoffAt
            ? $cutoffAt->copy()->subMinute()->format('Y-m-d H:i')
            : 'not available';
    }

    public function cancellationClosedLabel(): string
    {
        if ($this->isCancelled()) {
            return $this->cancellation_reason ?: 'Cancelled';
        }

        if (! in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true)) {
            return 'Cancellation unavailable';
        }

        return 'Closed after ' . $this->cancellationDeadlineLabel();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Not Approved',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Pending Review',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            default => 'warning',
        };
    }

    public function typeLabel(): string
    {
        return $this->request_type === self::TYPE_SLOT ? 'Available Slot' : 'Preferred Time';
    }
}
