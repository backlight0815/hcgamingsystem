<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TradingAppointmentSlot extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_BOOKED = 'booked';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'host_user_id',
        'created_by',
        'start_at',
        'end_at',
        'duration_minutes',
        'location',
        'notes',
        'status',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(TradingAppointment::class, 'appointment_slot_id');
    }

    public function isBookable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->start_at && $this->start_at->isFuture();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_BOOKED => 'Booked',
            self::STATUS_INACTIVE => 'Inactive',
            default => 'Available',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_BOOKED => 'secondary',
            self::STATUS_INACTIVE => 'dark',
            default => 'success',
        };
    }
}
