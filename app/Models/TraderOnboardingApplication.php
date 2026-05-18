<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraderOnboardingApplication extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED_RESUBMITTABLE = 'rejected_resubmittable';
    public const STATUS_REJECTED_NEW_APPLICATION = 'rejected_new_application';
    public const STATUS_REJECTED_FINAL = 'rejected_final';

    protected $fillable = [
        'user_id',
        'status',
        'is_client',
        'has_deposit',
        'deposit_amount',
        'discord_username',
        'broker_uid',
        'broker_email',
        'document_path',
        'trader_note',
        'reviewed_by',
        'rejection_reason',
        'rejection_note',
        'allow_resubmission',
        'submitted_at',
        'reviewed_at',
    ];

    protected $casts = [
        'is_client' => 'boolean',
        'has_deposit' => 'boolean',
        'deposit_amount' => 'decimal:2',
        'allow_resubmission' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function trader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED_RESUBMITTABLE,
            self::STATUS_REJECTED_NEW_APPLICATION,
            self::STATUS_REJECTED_FINAL,
        ], true);
    }

    public function canResubmit(): bool
    {
        return $this->status === self::STATUS_REJECTED_RESUBMITTABLE && (bool) $this->allow_resubmission;
    }

    public function canStartNewApplication(): bool
    {
        return $this->status === self::STATUS_REJECTED_NEW_APPLICATION && (bool) $this->allow_resubmission;
    }

    public function isHardClosed(): bool
    {
        return $this->status === self::STATUS_REJECTED_FINAL;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_REJECTED_NEW_APPLICATION,
            self::STATUS_REJECTED_FINAL,
        ], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED_RESUBMITTABLE => 'Rejected - Document Resubmission',
            self::STATUS_REJECTED_NEW_APPLICATION => 'Closed - New Application Allowed',
            self::STATUS_REJECTED_FINAL => 'Closed - Contact HC',
            default => 'Pending Review',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED_RESUBMITTABLE => 'warning',
            self::STATUS_REJECTED_NEW_APPLICATION => 'warning',
            self::STATUS_REJECTED_FINAL => 'danger',
            default => 'info',
        };
    }

    public function rejectionReasonLabel(): ?string
    {
        if (! $this->rejection_reason) {
            return null;
        }

        return static::rejectionReasons()[$this->rejection_reason] ?? $this->rejection_reason;
    }

    public static function rejectionReasons(): array
    {
        return [
            'missing_document' => 'Document is missing, unclear, or incomplete',
            'deposit_not_confirmed' => 'Deposit cannot be confirmed',
            'deposit_amount_mismatch' => 'Deposit amount does not match the record',
            'broker_details_mismatch' => 'Broker UID or broker email does not match',
            'discord_not_found' => 'Discord username cannot be verified',
            'client_not_confirmed' => 'Client relationship cannot be confirmed',
            'information_not_aligned' => 'Submitted information is not aligned',
            'final_not_proceeding' => 'Application will not proceed',
            'reopened_by_admin' => 'Application reopened by administration',
        ];
    }
}
