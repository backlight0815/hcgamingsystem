<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class TradingRiskAlertService
{
    private const NOTIFICATION_TYPE = 'trading_risk_alert';

    private const TIER_RANKS = [
        'normal' => 0,
        'low' => 1,
        'mid' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    public function evaluateAndNotify(User $trader, Collection|array $changedTrades, string $source = 'journal'): ?AppNotification
    {
        if (! (bool) config('trading_detection.risk_alerts.enabled', true)) {
            return null;
        }

        $changedTrades = collect($changedTrades)->filter()->values();
        $changedTradeIds = $changedTrades
            ->map(fn ($trade): int => (int) data_get($trade, 'id'))
            ->filter()
            ->unique()
            ->values();

        if ($changedTradeIds->isEmpty()) {
            return null;
        }

        $currentTrades = $this->loadTraderTrades($trader);

        if ($currentTrades->isEmpty()) {
            return null;
        }

        $previousTrades = $currentTrades
            ->reject(fn ($trade): bool => $changedTradeIds->contains((int) data_get($trade, 'id')))
            ->values();

        $analytics = app(TradingJournalAnalytics::class);
        $accountBalance = $this->accountBalance($trader);
        $previousProfile = $analytics->behavioralRiskProfile($previousTrades, $accountBalance);
        $currentProfile = $analytics->behavioralRiskProfile($currentTrades, $accountBalance);
        $increaseProfile = $this->riskIncreaseProfile($previousProfile, $currentProfile, $changedTrades, $source);

        if (! $this->shouldNotify($increaseProfile) || $this->recentAlertExists($trader)) {
            return null;
        }

        return AppNotificationService::notifyRoles(
            $this->adminRoles(),
            $this->alertTitle($trader, $increaseProfile),
            $this->alertMessage($trader, $increaseProfile),
            $this->reviewUrl($trader),
            self::NOTIFICATION_TYPE
        );
    }

    public function riskIncreaseProfile(array $previousProfile, array $currentProfile, Collection|array $changedTrades = [], string $source = 'journal'): array
    {
        $changedTrades = collect($changedTrades);
        $previousTier = $this->tierValue(data_get($previousProfile, 'tier', 'normal'));
        $currentTier = $this->tierValue(data_get($currentProfile, 'tier', 'normal'));
        $previousScore = $this->profileScore($previousProfile);
        $currentScore = $this->profileScore($currentProfile);
        $previousGamblingScore = (float) data_get($previousProfile, 'gambling.score', data_get($previousProfile, 'gambling_score', 0));
        $currentGamblingScore = (float) data_get($currentProfile, 'gambling.score', data_get($currentProfile, 'gambling_score', 0));
        $previousRevengeScore = (float) data_get($previousProfile, 'revenge.score', data_get($previousProfile, 'revenge_score', 0));
        $currentRevengeScore = (float) data_get($currentProfile, 'revenge.score', data_get($currentProfile, 'revenge_score', 0));
        $gamblingDelta = round($currentGamblingScore - $previousGamblingScore, 2);
        $revengeDelta = round($currentRevengeScore - $previousRevengeScore, 2);
        $primaryBehaviour = $currentRevengeScore > $currentGamblingScore
            ? 'revenge'
            : 'gambling';

        if ($revengeDelta > $gamblingDelta && $revengeDelta > 0) {
            $primaryBehaviour = 'revenge';
        } elseif ($gamblingDelta > 0) {
            $primaryBehaviour = 'gambling';
        }

        return [
            'source' => $source,
            'previous_score' => round($previousScore, 2),
            'current_score' => round($currentScore, 2),
            'score_delta' => round($currentScore - $previousScore, 2),
            'previous_tier' => $previousTier,
            'current_tier' => $currentTier,
            'previous_tier_rank' => $this->tierRank($previousTier),
            'current_tier_rank' => $this->tierRank($currentTier),
            'tier_rank_delta' => $this->tierRank($currentTier) - $this->tierRank($previousTier),
            'previous_gambling_score' => round($previousGamblingScore, 2),
            'current_gambling_score' => round($currentGamblingScore, 2),
            'gambling_delta' => $gamblingDelta,
            'previous_revenge_score' => round($previousRevengeScore, 2),
            'current_revenge_score' => round($currentRevengeScore, 2),
            'revenge_delta' => $revengeDelta,
            'largest_behavior_delta' => max($gamblingDelta, $revengeDelta),
            'primary_behaviour' => $primaryBehaviour,
            'confidence_score' => round((float) data_get($currentProfile, 'confidence_score', 100), 2),
            'label' => (string) data_get($currentProfile, 'label', 'Trading Behaviour Risk'),
            'style_label' => (string) data_get($currentProfile, 'style_label', 'Trading Behaviour Risk'),
            'reasons' => collect(data_get($currentProfile, 'reasons', []))->take(5)->values()->all(),
            'changed_trade_ids' => $changedTrades
                ->map(fn ($trade): int => (int) data_get($trade, 'id'))
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'changed_trade_count' => $changedTrades->count(),
        ];
    }

    public function shouldNotify(array $increaseProfile): bool
    {
        if (! (bool) config('trading_detection.risk_alerts.enabled', true)) {
            return false;
        }

        $currentTier = $this->tierValue($increaseProfile['current_tier'] ?? 'normal');
        $currentRank = (int) ($increaseProfile['current_tier_rank'] ?? $this->tierRank($currentTier));
        $previousRank = (int) ($increaseProfile['previous_tier_rank'] ?? 0);
        $currentScore = (float) ($increaseProfile['current_score'] ?? 0);
        $scoreDelta = (float) ($increaseProfile['score_delta'] ?? 0);
        $tierDelta = (int) ($increaseProfile['tier_rank_delta'] ?? 0);
        $largestBehaviorDelta = (float) ($increaseProfile['largest_behavior_delta'] ?? 0);
        $confidenceScore = (float) ($increaseProfile['confidence_score'] ?? 100);
        $critical = $currentTier === 'critical';
        $strongAlertEvidence = $this->hasStrongAlertEvidence($increaseProfile);

        if (
            ! $critical
            && ! $strongAlertEvidence
            && $confidenceScore < (float) config('trading_detection.risk_alerts.minimum_confidence_score', 50.0)
        ) {
            return false;
        }

        if ($critical && $previousRank < $currentRank) {
            return true;
        }

        if ($currentScore < (float) config('trading_detection.risk_alerts.min_final_score', 65.0)) {
            return false;
        }

        if ($currentRank >= $this->tierRank('high') && $previousRank < $this->tierRank('high')) {
            return true;
        }

        return $scoreDelta >= (float) config('trading_detection.risk_alerts.score_jump_threshold', 20.0)
            || $largestBehaviorDelta >= (float) config('trading_detection.risk_alerts.behavior_score_jump_threshold', 20.0)
            || $tierDelta >= (int) config('trading_detection.risk_alerts.tier_rank_jump_threshold', 1);
    }

    private function loadTraderTrades(User $trader): Collection
    {
        if (! Schema::hasTable('trading_journals')) {
            return collect();
        }

        return TradingJournal::where('user_id', $trader->id)
            ->where(function ($query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            })
            ->orderByRaw('COALESCE(open_date, close_date, created_at) asc')
            ->get();
    }

    private function accountBalance(User $trader): float
    {
        if (! Schema::hasTable('capitals')) {
            return 0.0;
        }

        return (float) Capital::where('user_id', $trader->id)
            ->where('type', 1)
            ->sum('amount');
    }

    private function recentAlertExists(User $trader): bool
    {
        if (! Schema::hasTable('app_notifications')) {
            return false;
        }

        $cooldownMinutes = max(0, (int) config('trading_detection.risk_alerts.cooldown_minutes', 60));

        if ($cooldownMinutes === 0) {
            return false;
        }

        return AppNotification::where('type', self::NOTIFICATION_TYPE)
            ->where('created_at', '>=', now()->subMinutes($cooldownMinutes))
            ->where('message', 'like', '%Trader ID: '.$trader->id.'%')
            ->exists();
    }

    private function alertTitle(User $trader, array $increaseProfile): string
    {
        return sprintf(
            '%s behaviour risk alert: %s',
            ucfirst((string) ($increaseProfile['current_tier'] ?? 'high')),
            $trader->username ?: ($trader->name ?: 'Trader #'.$trader->id)
        );
    }

    private function alertMessage(User $trader, array $increaseProfile): string
    {
        $name = $trader->username ?: ($trader->name ?: 'Trader #'.$trader->id);
        $reasons = collect($increaseProfile['reasons'] ?? [])
            ->filter()
            ->take(3)
            ->implode('; ');

        return sprintf(
            'Trader ID: %d. %s risk increased from %s %.2f/100 to %s %.2f/100 after %d journal trade(s). Primary marker: %s. Gambling %.2f -> %.2f; Revenge %.2f -> %.2f. %s',
            $trader->id,
            $name,
            ucfirst((string) ($increaseProfile['previous_tier'] ?? 'normal')),
            (float) ($increaseProfile['previous_score'] ?? 0),
            ucfirst((string) ($increaseProfile['current_tier'] ?? 'high')),
            (float) ($increaseProfile['current_score'] ?? 0),
            (int) ($increaseProfile['changed_trade_count'] ?? 1),
            ucfirst((string) ($increaseProfile['primary_behaviour'] ?? 'behaviour')),
            (float) ($increaseProfile['previous_gambling_score'] ?? 0),
            (float) ($increaseProfile['current_gambling_score'] ?? 0),
            (float) ($increaseProfile['previous_revenge_score'] ?? 0),
            (float) ($increaseProfile['current_revenge_score'] ?? 0),
            $reasons ? 'Evidence: '.$reasons : 'Review the trading journal for evidence.'
        );
    }

    private function reviewUrl(User $trader): ?string
    {
        if (! Route::has('admin.trader.journals.index')) {
            return null;
        }

        return route('admin.trader.journals.index', [
            'user_id' => $trader->id,
            'journal_source' => 'all',
        ]);
    }

    private function adminRoles(): array
    {
        return collect(config('trading_detection.risk_alerts.admin_roles', [1, 2]))
            ->map(fn ($roleId): int => (int) $roleId)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function hasStrongAlertEvidence(array $increaseProfile): bool
    {
        $reasons = collect($increaseProfile['reasons'] ?? [])
            ->map(fn ($reason): string => strtolower((string) $reason));

        if ($reasons->contains(fn (string $reason): bool => str_contains($reason, 'daily loss limit exceeded'))) {
            return true;
        }

        $openedDuringCooldown = $reasons->contains(fn (string $reason): bool => str_contains($reason, 'cooldown after a loss'));
        $riskIncreased = $reasons->contains(fn (string $reason): bool => str_contains($reason, 'risk increased after previous losing trade'));
        $lotIncreased = $reasons->contains(fn (string $reason): bool => str_contains($reason, 'lot size increased after previous losing trade'));

        return $openedDuringCooldown && ($riskIncreased || $lotIncreased);
    }

    private function profileScore(array $profile): float
    {
        return (float) data_get($profile, 'risk_score', data_get($profile, 'final_score', 0));
    }

    private function tierValue($tier): string
    {
        $tier = strtolower((string) $tier);

        return array_key_exists($tier, self::TIER_RANKS) ? $tier : 'normal';
    }

    private function tierRank(string $tier): int
    {
        return self::TIER_RANKS[$this->tierValue($tier)] ?? 0;
    }
}
