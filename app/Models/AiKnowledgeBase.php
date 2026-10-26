<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiKnowledgeBase extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    public const MODULE_TRADING_JOURNAL = 'trading_journal';

    public const MODULE_ONBOARDING = 'onboarding';

    public const MODULE_TRADING_EXAM = 'trading_exam';

    public const MODULE_PROP_FIRM = 'prop_firm';

    public const MODULE_TRADING_STATISTICS = 'trading_statistics';

    public const MODULE_GENERAL = 'general';
    public const MODULE_MARKET_STRUCTURE = 'market_structure';
    public const MODULE_RISK_MANAGEMENT = 'risk_management';
    public const MODULE_TRADE_MANAGEMENT = 'trade_management';
    public const MODULE_TRADING_PSYCHOLOGY = 'trading_psychology';
    public const MODULE_TRADING_STYLES = 'trading_styles';

    protected $fillable = [
        'module',
        'title',
        'content',
        'status',
        'difficulty', 'source', 'language', 'version', 'reviewed_at',
        'entry_type', 'normalized_question', 'alternative_questions', 'alternative_normalized', 'keywords', 'priority',
        'effective_at', 'approved_at', 'published_at', 'last_tested_at',
        'created_by', 'updated_by', 'approved_by',
        'asset_classes', 'source_type', 'source_url', 'content_owner',
        'last_verified_at', 'effective_until', 'superseded_by',
        'canonical_concept_id', 'content_hash', 'license_status', 'source_owner',
        'source_published_at', 'source_retrieved_at', 'review_after_at',
    ];

    protected $casts = [
        'alternative_questions' => 'array', 'alternative_normalized' => 'array', 'keywords' => 'array',
        'effective_at' => 'datetime', 'approved_at' => 'datetime',
        'published_at' => 'datetime', 'last_tested_at' => 'datetime',
        'asset_classes' => 'array', 'last_verified_at' => 'datetime', 'effective_until' => 'datetime',
        'source_published_at' => 'datetime', 'source_retrieved_at' => 'datetime', 'review_after_at' => 'datetime',
    ];

    public static function normalizeQuestion(string $question): string
    {
        $question = mb_strtolower(Str::squish($question));
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $question));
    }

    public static function contentFingerprint(string $content): string
    {
        return hash('sha256', mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', strip_tags($content)))));
    }

    public static function modules(): array
    {
        return [
            self::MODULE_TRADING_JOURNAL => 'Trading Journal',
            self::MODULE_ONBOARDING => 'Onboarding',
            self::MODULE_TRADING_EXAM => 'Trading Exam',
            self::MODULE_PROP_FIRM => 'Prop Firm',
            self::MODULE_TRADING_STATISTICS => 'Trading Statistics',
            self::MODULE_GENERAL => 'General',
            self::MODULE_MARKET_STRUCTURE => 'Market Structure & Levels',
            self::MODULE_RISK_MANAGEMENT => 'Risk Management',
            self::MODULE_TRADE_MANAGEMENT => 'TP, SL & Break-Even',
            self::MODULE_TRADING_PSYCHOLOGY => 'Trading Psychology',
            self::MODULE_TRADING_STYLES => 'Trading Styles',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_REVIEW => 'Pending review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_ARCHIVED => 'Archived',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('superseded_by')->where(function (Builder $review): void {
            $review->whereNull('review_after_at')->orWhere('review_after_at', '>', now());
        })->where(function (Builder $expiry): void {
            $expiry->whereNull('effective_until')->orWhere('effective_until', '>', now());
        })->where(function (Builder $builder): void {
            $builder->where(function (Builder $articles): void {
                $articles->where('entry_type', 'article')
                    ->where(function (Builder $status): void {
                        $status->where('status', self::STATUS_ACTIVE)
                            ->orWhere(function (Builder $approved): void {
                                $approved->where('status', self::STATUS_PUBLISHED)->whereNotNull('approved_at');
                            });
                    });
            })->orWhere(function (Builder $faqs): void {
                $faqs->where('entry_type', 'faq')->where('status', self::STATUS_PUBLISHED)
                    ->whereNotNull('approved_at')
                    ->where(function (Builder $effective): void {
                        $effective->whereNull('effective_at')->orWhere('effective_at', '<=', now());
                    });
            });
        });
    }

    public function getModuleLabelAttribute(): string
    {
        return self::modules()[$this->module] ?? ucfirst(str_replace('_', ' ', (string) $this->module));
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }
}
