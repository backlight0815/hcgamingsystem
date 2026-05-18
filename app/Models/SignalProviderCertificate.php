<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalProviderCertificate extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'recipient_name',
        'level',
        'certificate_title',
        'certificate_type',
        'status',
        'certificate_path',
        'discipline_summary',
        'strategy_summary',
        'founder_name',
        'founder_title',
        'issued_by',
        'eligible_at',
        'approved_at',
        'published_at',
        'verification_code',
        'view_count',
        'download_count',
    ];

    protected $casts = [
        'eligible_at' => 'datetime',
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'view_count' => 'integer',
        'download_count' => 'integer',
    ];

    // Relationship to User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // Check if certificate can be downloaded
    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && ! is_null($this->certificate_path);
    }

    public function getRecipientDisplayNameAttribute(): string
    {
        return $this->recipient_name ?: ($this->user?->name ?: $this->user?->username ?: 'Certified Member');
    }

    public function getLevelLabelAttribute(): string
    {
        return self::levels()[$this->level] ?? ucfirst((string) $this->level);
    }

    public function getCertificateTypeLabelAttribute(): string
    {
        return self::certificateTypes()[$this->certificate_type] ?? 'Trading Class Completion';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_REVOKED => 'Revoked',
        ];
    }

    public static function levels(): array
    {
        return [
            'completion' => 'Trading Class Completion',
            'disciplined' => 'Disciplined Trader',
            'strategy' => 'Strategy Evaluation Pass',
            'junior' => 'Junior Signal Provider',
            'senior' => 'Senior Signal Provider',
            'expert' => 'Expert Signal Provider',
            'market_analyst' => 'Market Analyst',
            'administration' => 'Administration',
        ];
    }

    public static function certificateTypes(): array
    {
        return [
            'trading_class_completion' => 'Trading Class Completion',
            'discipline_evaluation' => 'Discipline Evaluation',
            'strategy_showcase' => 'Strategy Showcase',
            'signal_provider_evaluation' => 'Signal Provider Evaluation',
            'market_analyst_evaluation' => 'Market Analyst Evaluation',
            'administration_recognition' => 'Administration Recognition',
        ];
    }

    public static function eligibleRoleIds(): array
    {
        return [1, 2, 201, 202, 501, 750, 760, 770];
    }
}
