<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiKnowledgeBase extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

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
    ];

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
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
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
