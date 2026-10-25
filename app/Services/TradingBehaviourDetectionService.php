<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;

class TradingBehaviourDetectionService
{
    private const IMPORTANT_CONFIDENCE_FIELDS = [
        'trade_plan_id',
        'setup_id',
        'risk_percent',
        'lot_size',
        'stop_loss',
        'take_profit',
        'margin_used_percent',
        'daily_loss_percent',
        'last_trade_result',
        'minutes_since_last_loss',
        'loss_streak',
        'emotion_tag',
        'entry_reason',
    ];

    public function detect($trade, $plan = null, $accountStats = null): array
    {
        $plan = $this->normalizePlan($plan);
        $accountStats = $accountStats ?? [];

        $isPlannedLayer = $this->isValidPlannedLayer($trade, $plan, $accountStats);
        $gamblingScore = $this->calculateGamblingScore($trade, $plan, $accountStats);
        $revengeScore = $this->calculateRevengeScore($trade, $plan, $accountStats);

        if ($isPlannedLayer && ! $this->hasPlannedLayerReductionBlocker($trade, $plan, $accountStats)) {
            $gamblingScore = max(0, $gamblingScore - 25);
            $revengeScore = max(0, $revengeScore - 15);
        }

        $confidenceScore = $this->calculateConfidenceScore($trade, $accountStats);

        if ($confidenceScore < 50 && ! $this->hasStrongEvidence($trade, $plan, $accountStats)) {
            $gamblingScore = min($gamblingScore, 64);
            $revengeScore = min($revengeScore, 64);
        }

        $finalScore = max($gamblingScore, $revengeScore);
        $scores = [
            'gambling_score' => $gamblingScore,
            'revenge_score' => $revengeScore,
            'final_score' => $finalScore,
            'confidence_score' => $confidenceScore,
        ];

        return [
            'gambling_score' => round($gamblingScore, 2),
            'revenge_score' => round($revengeScore, 2),
            'final_score' => round($finalScore, 2),
            'tier' => $this->getTier($finalScore),
            'label' => $this->getLabel($gamblingScore, $revengeScore, $finalScore, $isPlannedLayer, $confidenceScore, $accountStats),
            'reasons' => $this->buildReasons($trade, $plan, $accountStats, $scores, $isPlannedLayer),
            'is_planned_layer' => $isPlannedLayer,
            'confidence_score' => round($confidenceScore, 2),
        ];
    }

    public function calculateGamblingScore($trade, $plan, $accountStats): float
    {
        $plan = $this->normalizePlan($plan);
        $score = 0.0;

        if ($this->knownMissing([$trade], ['trade_plan_id'])) {
            $score += 15;
        }

        if (
            $this->knownMissing([$trade], ['setup_id'])
            && ! $this->boolValue($trade, ['standalone_trade', 'is_standalone_trade', 'standalone'], false)
        ) {
            $score += 10;
        }

        if ($this->knownMissing([$trade], ['entry_reason', 'reason_for_entry'])) {
            $score += 10;
        }

        if ($this->knownMissing([$trade], ['strategy_id', 'trading_strategy_id'])) {
            $score += 10;
        }

        if ($this->knownMissing([$trade], ['stop_loss', 'sl'])) {
            $score += 25;
        }

        $riskPercent = $this->currentRiskPercent($trade, $accountStats);
        $maxOrderRisk = (float) $plan['max_order_risk_percent'];

        if ($riskPercent !== null && $maxOrderRisk > 0 && $riskPercent > $maxOrderRisk) {
            $ratio = $riskPercent / $maxOrderRisk;
            $score += match (true) {
                $ratio >= 3 => 30,
                $ratio >= 2 => 20,
                default => 10,
            };
        }

        $setupRisk = $this->totalSetupRiskPercent($trade, $accountStats);
        $maxSetupRisk = (float) $plan['max_setup_risk_percent'];

        if ($setupRisk !== null && $maxSetupRisk > 0 && $setupRisk > $maxSetupRisk) {
            $ratio = $setupRisk / $maxSetupRisk;
            $score += match (true) {
                $ratio >= 3 => 35,
                $ratio >= 2 => 25,
                default => 15,
            };
        }

        $ordersToday = $this->numericValue([$accountStats, $trade], ['orders_today', 'orders_count_today']);
        $maxOrdersPerDay = (float) $plan['max_orders_per_day'];

        if ($ordersToday !== null && $maxOrdersPerDay > 0 && $ordersToday > $maxOrdersPerDay) {
            $score += $ordersToday > ($maxOrdersPerDay * 1.5) ? 25 : 15;
        }

        $marginUsedPercent = $this->marginUsedPercent($trade, $accountStats);
        $safeMarginPercent = (float) $plan['safe_margin_percent'];
        $dangerousMarginPercent = max((float) $plan['dangerous_margin_percent'], $safeMarginPercent);

        if ($marginUsedPercent !== null && $marginUsedPercent > $safeMarginPercent) {
            $score += $marginUsedPercent >= $dangerousMarginPercent ? 25 : 15;
        }

        $riskRewardRatio = $this->numericValue([$trade, $accountStats], ['risk_reward_ratio', 'rr', 'rrr']);

        if ($riskRewardRatio !== null && $riskRewardRatio < (float) $plan['minimum_rr']) {
            $score += 10;
        }

        if (
            $this->knownMissing([$trade], ['take_profit', 'tp'])
            && $this->knownMissing([$trade, $accountStats], ['exit_plan', 'planned_exit'])
        ) {
            $score += 10;
        }

        $symbolsTraded = $this->numericValue([$accountStats], ['symbols_traded_short_time', 'recent_symbol_count']);
        $strategiesSwitched = $this->numericValue([$accountStats], ['strategies_switched_short_time', 'recent_strategy_count']);

        if (
            $this->boolValue($accountStats, ['many_symbols_without_plan', 'random_symbols_without_plan'], false)
            || ($symbolsTraded !== null && $symbolsTraded > (float) $plan['max_symbols_short_window'] && $this->knownMissing([$trade], ['trade_plan_id']))
        ) {
            $score += 10;
        }

        if (
            $this->boolValue($accountStats, ['many_strategies_switched', 'random_strategy_switching'], false)
            || ($strategiesSwitched !== null && $strategiesSwitched > (float) $plan['max_strategies_short_window'])
        ) {
            $score += 10;
        }

        return $this->capScore($score);
    }

    public function calculateRevengeScore($trade, $plan, $accountStats): float
    {
        $plan = $this->normalizePlan($plan);
        $score = 0.0;
        $lastTradeWasLoss = $this->lastTradeWasLoss($accountStats);
        $minutesSinceLastLoss = $this->minutesSinceLastLoss($trade, $accountStats);
        $cooldownMinutes = (float) $plan['cooldown_after_loss_minutes'];

        if ($lastTradeWasLoss) {
            $score += 10;
        }

        if ($lastTradeWasLoss && $minutesSinceLastLoss !== null && $minutesSinceLastLoss < $cooldownMinutes) {
            $score += $minutesSinceLastLoss <= 5 ? 30 : 20;
        }

        $currentRisk = $this->currentRiskPercent($trade, $accountStats);
        $previousRisk = $this->numericValue([$accountStats], [
            'previous_losing_trade_risk_percent',
            'previous_loss_risk_percent',
            'last_loss_risk_percent',
        ]);

        if ($lastTradeWasLoss && $currentRisk !== null && $previousRisk !== null && $previousRisk > 0 && $currentRisk > $previousRisk) {
            $score += $currentRisk >= ($previousRisk * 2) ? 30 : 20;
        }

        $currentLotSize = $this->numericValue([$trade, $accountStats], ['lot_size', 'current_lot_size']);
        $previousLotSize = $this->numericValue([$accountStats], [
            'previous_losing_trade_lot_size',
            'previous_loss_lot_size',
            'last_loss_lot_size',
        ]);

        if ($lastTradeWasLoss && $currentLotSize !== null && $previousLotSize !== null && $previousLotSize > 0 && $currentLotSize > $previousLotSize) {
            $score += 15;
        }

        $sameSymbolAfterLoss = $this->sameSymbolAfterLoss($trade, $accountStats);
        $withinCooldown = $lastTradeWasLoss
            && $minutesSinceLastLoss !== null
            && $minutesSinceLastLoss < $cooldownMinutes;

        if ($withinCooldown && $sameSymbolAfterLoss) {
            $score += 10;
        }

        if ($withinCooldown && $sameSymbolAfterLoss && $this->sameDirectionAfterStopLoss($trade, $accountStats)) {
            $score += 10;
        }

        $lossStreak = (int) ($this->numericValue([$accountStats], ['loss_streak', 'current_loss_streak']) ?? 0);

        if ($lastTradeWasLoss && $lossStreak >= 3) {
            $score += 25;
        } elseif ($lastTradeWasLoss && $lossStreak >= 2) {
            $score += 15;
        }

        if ($this->dailyLossLimitExceeded($trade, $accountStats, $plan)) {
            $score = max($score, 80);
        }

        $score += $this->emotionScore($trade, $accountStats);

        return $this->capScore($score);
    }

    public function isValidPlannedLayer($trade, $plan, $accountStats): bool
    {
        $plan = $this->normalizePlan($plan);

        if (! $this->boolValue($trade, ['is_layer', 'layer'], false)) {
            return false;
        }

        if (! $this->boolValue($trade, ['planned_layer', 'is_planned_layer'], false)) {
            return false;
        }

        if ($this->blank($this->firstValue([$trade], ['setup_id']))) {
            return false;
        }

        $orderNumber = $this->numericValue([$trade, $accountStats], ['order_number', 'layer_number', 'setup_order_number']);

        if ($orderNumber === null || $orderNumber > (float) $plan['max_planned_layers']) {
            return false;
        }

        $riskPercent = $this->currentRiskPercent($trade, $accountStats);

        if ($riskPercent === null || $riskPercent > (float) $plan['max_order_risk_percent']) {
            return false;
        }

        $setupRisk = $this->totalSetupRiskPercent($trade, $accountStats);

        if ($setupRisk === null || $setupRisk > (float) $plan['max_setup_risk_percent']) {
            return false;
        }

        if (! $this->hasUsableValue([$trade], ['stop_loss', 'sl'])) {
            return false;
        }

        if (! $this->hasUsableValue([$trade], ['entry_reason', 'reason_for_entry'])) {
            return false;
        }

        if (! $this->hasUsableValue([$trade], ['strategy_id', 'trading_strategy_id'])) {
            return false;
        }

        $marginUsedPercent = $this->marginUsedPercent($trade, $accountStats);

        if ($marginUsedPercent === null || $marginUsedPercent > (float) $plan['safe_margin_percent']) {
            return false;
        }

        if (! $this->directionMatchesSetup($trade, $plan, $accountStats)) {
            return false;
        }

        if (! $this->symbolMatchesSetup($trade, $plan, $accountStats)) {
            return false;
        }

        if ($this->openedDuringCooldownAfterLoss($trade, $plan, $accountStats)) {
            return false;
        }

        $dailyLossPercent = $this->dailyLossPercent($trade, $accountStats);

        if ($dailyLossPercent === null || $dailyLossPercent >= (float) $plan['daily_loss_limit_percent']) {
            return false;
        }

        if ($this->riskIncreasedAfterLoss($trade, $accountStats)) {
            return false;
        }

        return true;
    }

    public function calculateConfidenceScore($trade, $accountStats): float
    {
        $available = 0;

        foreach (self::IMPORTANT_CONFIDENCE_FIELDS as $field) {
            $sources = match ($field) {
                'risk_percent' => [$trade, $accountStats],
                'margin_used_percent',
                'daily_loss_percent',
                'last_trade_result',
                'minutes_since_last_loss',
                'loss_streak' => [$accountStats, $trade],
                default => [$trade, $accountStats],
            };

            $keys = match ($field) {
                'risk_percent' => ['risk_percent', 'order_risk_percent', 'current_risk_percent'],
                'margin_used_percent' => ['margin_used_percent', 'margin_percent', 'used_margin_percent'],
                'daily_loss_percent' => ['daily_loss_percent', 'daily_drawdown_percent'],
                'last_trade_result' => ['last_trade_result', 'last_closed_trade_result', 'previous_trade_result'],
                'minutes_since_last_loss' => ['minutes_since_last_loss', 'minutes_after_last_loss'],
                'loss_streak' => ['loss_streak', 'current_loss_streak'],
                'entry_reason' => ['entry_reason', 'reason_for_entry'],
                'take_profit' => ['take_profit', 'tp'],
                'stop_loss' => ['stop_loss', 'sl'],
                default => [$field],
            };

            if ($this->hasUsableValue($sources, $keys)) {
                $available++;
            }
        }

        return round(($available / count(self::IMPORTANT_CONFIDENCE_FIELDS)) * 100, 2);
    }

    public function getTier($score): string
    {
        $score = max(0, min(100, (float) $score));
        $tiers = config('trading_detection.tiers', []);

        foreach ($tiers as $tier => $range) {
            $min = (float) ($range[0] ?? 0);
            $max = (float) ($range[1] ?? 100);

            if ($score >= $min && $score <= $max) {
                return (string) $tier;
            }
        }

        return 'critical';
    }

    public function getLabel($gamblingScore, $revengeScore, $finalScore, $isPlannedLayer, $confidenceScore, $accountStats): string
    {
        $plan = $this->normalizePlan(null);

        if ($this->dailyLossLimitExceeded(null, $accountStats, $plan)) {
            return 'Critical Risk: Daily Loss Limit Exceeded';
        }

        if ($confidenceScore < 50 && ! $this->hasStrongEvidence(null, $plan, $accountStats)) {
            return 'Low Confidence Detection';
        }

        if ($this->getTier($finalScore) === 'normal' && $isPlannedLayer) {
            return 'Planned Layer / Controlled Risk';
        }

        if ($isPlannedLayer && $finalScore < 45) {
            return 'Planned Layer / Controlled Risk';
        }

        if ($revengeScore > $gamblingScore && $finalScore >= 25) {
            return 'Possible Revenge Trading Behaviour';
        }

        if ($gamblingScore >= $revengeScore && $finalScore >= 25) {
            return 'Possible Gambling Trading Behaviour';
        }

        return 'Normal Trading Behaviour';
    }

    public function buildReasons($trade, $plan, $accountStats, $scores, $isPlannedLayer): array
    {
        $plan = $this->normalizePlan($plan);
        $reasons = [];

        if ($isPlannedLayer) {
            $reasons[] = 'Trade is a valid planned layer within risk limit.';
        }

        if ($this->knownMissing([$trade], ['trade_plan_id']) || (
            $this->knownMissing([$trade], ['setup_id'])
            && ! $this->boolValue($trade, ['standalone_trade', 'is_standalone_trade', 'standalone'], false)
        )) {
            $reasons[] = 'Trade has no clear plan.';
        }

        if ($this->knownMissing([$trade], ['entry_reason', 'reason_for_entry'])) {
            $reasons[] = 'Trade has no entry reason.';
        }

        if ($this->knownMissing([$trade], ['strategy_id', 'trading_strategy_id'])) {
            $reasons[] = 'Trade has no strategy.';
        }

        if ($this->knownMissing([$trade], ['stop_loss', 'sl'])) {
            $reasons[] = 'Trade has no stop loss.';
        } elseif ($this->hasUsableValue([$trade], ['stop_loss', 'sl'])) {
            $reasons[] = 'Trade has valid stop loss.';
        }

        $riskPercent = $this->currentRiskPercent($trade, $accountStats);

        if ($riskPercent !== null && $riskPercent > (float) $plan['max_order_risk_percent']) {
            $reasons[] = 'Order risk exceeded planned risk.';
        }

        $setupRisk = $this->totalSetupRiskPercent($trade, $accountStats);

        if ($setupRisk !== null && $setupRisk > (float) $plan['max_setup_risk_percent']) {
            $reasons[] = 'Total setup risk exceeded trading plan.';
        } elseif ($setupRisk !== null) {
            $reasons[] = 'Total setup risk is within allowed limit.';
        }

        $ordersToday = $this->numericValue([$accountStats, $trade], ['orders_today', 'orders_count_today']);

        if ($ordersToday !== null && $ordersToday > (float) $plan['max_orders_per_day']) {
            $reasons[] = 'Orders today exceeded the trading plan limit.';
        }

        $marginUsedPercent = $this->marginUsedPercent($trade, $accountStats);

        if ($marginUsedPercent !== null && $marginUsedPercent > (float) $plan['safe_margin_percent']) {
            $reasons[] = 'Margin exposure is high.';
        } elseif ($marginUsedPercent !== null) {
            $reasons[] = 'Margin exposure is safe.';
        }

        $riskRewardRatio = $this->numericValue([$trade, $accountStats], ['risk_reward_ratio', 'rr', 'rrr']);

        if ($riskRewardRatio !== null && $riskRewardRatio < (float) $plan['minimum_rr']) {
            $reasons[] = 'Risk reward ratio is below the minimum plan requirement.';
        }

        if (
            $this->knownMissing([$trade], ['take_profit', 'tp'])
            && $this->knownMissing([$trade, $accountStats], ['exit_plan', 'planned_exit'])
        ) {
            $reasons[] = 'Trade has no take profit or exit plan.';
        }

        if ($this->randomSymbolSwitching($trade, $plan, $accountStats)) {
            $reasons[] = 'Many symbols were traded in a short time without a clear plan.';
        }

        if ($this->randomStrategySwitching($plan, $accountStats)) {
            $reasons[] = 'Many strategies were switched in a short time.';
        }

        if ($this->dailyLossLimitExceeded($trade, $accountStats, $plan)) {
            $reasons[] = 'Daily loss limit exceeded.';
        }

        if ($this->openedDuringCooldownAfterLoss($trade, $plan, $accountStats)) {
            $reasons[] = 'New trade opened during cooldown after a loss.';
        }

        if ($this->riskIncreasedAfterLoss($trade, $accountStats)) {
            $reasons[] = 'Risk increased after previous losing trade.';
        }

        if ($this->lotIncreasedAfterLoss($trade, $accountStats)) {
            $reasons[] = 'Lot size increased after previous losing trade.';
        }

        if ($this->sameSymbolAfterLoss($trade, $accountStats) && $this->openedDuringCooldownAfterLoss($trade, $plan, $accountStats)) {
            $reasons[] = 'Same symbol was traded again during post-loss cooldown.';
        }

        $lossStreak = (int) ($this->numericValue([$accountStats], ['loss_streak', 'current_loss_streak']) ?? 0);

        if ($lossStreak >= 2 && $this->lastTradeWasLoss($accountStats)) {
            $reasons[] = 'Loss streak detected.';
        }

        if ($this->emotionScore($trade, $accountStats) > 0) {
            $reasons[] = 'Emotional trading tag detected.';
        }

        if ((float) ($scores['revenge_score'] ?? 0) < 25 && ! $this->hasRevengeEvidence($trade, $plan, $accountStats)) {
            $reasons[] = 'No revenge trading evidence detected.';
        }

        if ((float) ($scores['confidence_score'] ?? 100) < 80) {
            $reasons[] = 'Some trading data is missing, classification confidence is reduced.';
        }

        return $this->uniqueReasons($reasons);
    }

    private function normalizePlan($plan): array
    {
        $defaults = [
            'max_order_risk_percent' => 0.5,
            'max_setup_risk_percent' => 1.0,
            'max_orders_per_day' => 8,
            'daily_loss_limit_percent' => 5.0,
            'total_loss_limit_percent' => 10.0,
            'cooldown_after_loss_minutes' => 30,
            'minimum_rr' => 1.0,
            'preferred_rr' => 2.0,
            'safe_margin_percent' => 30.0,
            'dangerous_margin_percent' => 60.0,
            'max_planned_layers' => 3,
            'max_symbols_short_window' => 3,
            'max_strategies_short_window' => 2,
        ];

        $settings = [];

        foreach ($defaults as $key => $default) {
            $settings[$key] = config('trading_detection.'.$key, $default);
            $custom = $this->firstValue([$plan], [$key, 'settings.'.$key, 'rules.'.$key]);

            if (! $this->blank($custom)) {
                $settings[$key] = is_numeric($custom) ? (float) $custom : $custom;
            }
        }

        return $settings;
    }

    private function hasStrongEvidence($trade, array $plan, $accountStats): bool
    {
        if ($this->dailyLossLimitExceeded($trade, $accountStats, $plan)) {
            return true;
        }

        $riskPercent = $this->currentRiskPercent($trade, $accountStats);
        $setupRisk = $this->totalSetupRiskPercent($trade, $accountStats);

        if (
            $riskPercent !== null
            && $riskPercent > (float) $plan['max_order_risk_percent']
            && $this->knownMissing([$trade], ['stop_loss', 'sl'])
        ) {
            return true;
        }

        if ($riskPercent !== null && $riskPercent >= ((float) $plan['max_order_risk_percent'] * 3)) {
            return true;
        }

        if ($setupRisk !== null && $setupRisk >= ((float) $plan['max_setup_risk_percent'] * 3)) {
            return true;
        }

        $minutes = $this->minutesSinceLastLoss($trade, $accountStats);

        return $this->lastTradeWasLoss($accountStats)
            && $minutes !== null
            && $minutes <= 5
            && $this->lotIncreasedAfterLoss($trade, $accountStats);
    }

    private function hasPlannedLayerReductionBlocker($trade, array $plan, $accountStats): bool
    {
        return $this->dailyLossLimitExceeded($trade, $accountStats, $plan)
            || $this->knownMissing([$trade], ['stop_loss', 'sl'])
            || $this->setupRiskExceeded($trade, $plan, $accountStats)
            || $this->riskIncreasedAfterLoss($trade, $accountStats)
            || $this->openedDuringCooldownAfterLoss($trade, $plan, $accountStats);
    }

    private function setupRiskExceeded($trade, array $plan, $accountStats): bool
    {
        $setupRisk = $this->totalSetupRiskPercent($trade, $accountStats);

        return $setupRisk !== null && $setupRisk > (float) $plan['max_setup_risk_percent'];
    }

    private function randomSymbolSwitching($trade, array $plan, $accountStats): bool
    {
        $symbolsTraded = $this->numericValue([$accountStats], ['symbols_traded_short_time', 'recent_symbol_count']);

        return $this->boolValue($accountStats, ['many_symbols_without_plan', 'random_symbols_without_plan'], false)
            || ($symbolsTraded !== null && $symbolsTraded > (float) $plan['max_symbols_short_window'] && $this->knownMissing([$trade], ['trade_plan_id']));
    }

    private function randomStrategySwitching(array $plan, $accountStats): bool
    {
        $strategiesSwitched = $this->numericValue([$accountStats], ['strategies_switched_short_time', 'recent_strategy_count']);

        return $this->boolValue($accountStats, ['many_strategies_switched', 'random_strategy_switching'], false)
            || ($strategiesSwitched !== null && $strategiesSwitched > (float) $plan['max_strategies_short_window']);
    }

    private function currentRiskPercent($trade, $accountStats): ?float
    {
        return $this->numericValue([$trade, $accountStats], [
            'risk_percent',
            'order_risk_percent',
            'current_risk_percent',
        ]);
    }

    private function totalSetupRiskPercent($trade, $accountStats): ?float
    {
        return $this->numericValue([$accountStats, $trade], [
            'actual_total_setup_risk_percent',
            'total_setup_risk_percent',
            'setup_risk_percent',
            'current_setup_risk_percent',
        ]);
    }

    private function marginUsedPercent($trade, $accountStats): ?float
    {
        return $this->numericValue([$accountStats, $trade], [
            'margin_used_percent',
            'margin_percent',
            'used_margin_percent',
            'current_margin_used_percent',
        ]);
    }

    private function dailyLossPercent($trade, $accountStats): ?float
    {
        return $this->numericValue([$accountStats, $trade], [
            'daily_loss_percent',
            'daily_drawdown_percent',
            'current_daily_loss_percent',
        ]);
    }

    private function dailyLossLimitExceeded($trade, $accountStats, array $plan): bool
    {
        $dailyLossPercent = $this->dailyLossPercent($trade, $accountStats);

        return $dailyLossPercent !== null && $dailyLossPercent >= (float) $plan['daily_loss_limit_percent'];
    }

    private function openedDuringCooldownAfterLoss($trade, array $plan, $accountStats): bool
    {
        $minutes = $this->minutesSinceLastLoss($trade, $accountStats);

        return $this->lastTradeWasLoss($accountStats)
            && $minutes !== null
            && $minutes < (float) $plan['cooldown_after_loss_minutes'];
    }

    private function riskIncreasedAfterLoss($trade, $accountStats): bool
    {
        if (! $this->lastTradeWasLoss($accountStats)) {
            return false;
        }

        $currentRisk = $this->currentRiskPercent($trade, $accountStats);
        $previousRisk = $this->numericValue([$accountStats], [
            'previous_losing_trade_risk_percent',
            'previous_loss_risk_percent',
            'last_loss_risk_percent',
        ]);

        return $currentRisk !== null
            && $previousRisk !== null
            && $previousRisk > 0
            && $currentRisk > $previousRisk;
    }

    private function lotIncreasedAfterLoss($trade, $accountStats): bool
    {
        if (! $this->lastTradeWasLoss($accountStats)) {
            return false;
        }

        $currentLotSize = $this->numericValue([$trade, $accountStats], ['lot_size', 'current_lot_size']);
        $previousLotSize = $this->numericValue([$accountStats], [
            'previous_losing_trade_lot_size',
            'previous_loss_lot_size',
            'last_loss_lot_size',
        ]);

        return $currentLotSize !== null
            && $previousLotSize !== null
            && $previousLotSize > 0
            && $currentLotSize > $previousLotSize;
    }

    private function hasRevengeEvidence($trade, array $plan, $accountStats): bool
    {
        return $this->dailyLossLimitExceeded($trade, $accountStats, $plan)
            || $this->openedDuringCooldownAfterLoss($trade, $plan, $accountStats)
            || $this->riskIncreasedAfterLoss($trade, $accountStats)
            || $this->lotIncreasedAfterLoss($trade, $accountStats)
            || ((int) ($this->numericValue([$accountStats], ['loss_streak', 'current_loss_streak']) ?? 0) >= 2 && $this->lastTradeWasLoss($accountStats))
            || $this->emotionScore($trade, $accountStats) > 0;
    }

    private function lastTradeWasLoss($accountStats): bool
    {
        if ($this->boolValue($accountStats, ['last_trade_was_loss', 'previous_trade_was_loss'], false)) {
            return true;
        }

        $profitLoss = $this->numericValue([$accountStats], [
            'last_closed_trade_profit_loss',
            'previous_trade_profit_loss',
            'last_trade_profit_loss',
        ]);

        if ($profitLoss !== null) {
            return $profitLoss < 0;
        }

        $result = $this->firstValue([$accountStats], [
            'last_trade_result',
            'last_closed_trade_result',
            'previous_trade_result',
        ]);

        return $this->isLossResult($result);
    }

    private function isLossResult($result): bool
    {
        if ($this->blank($result)) {
            return false;
        }

        if (is_numeric($result)) {
            return (float) $result < 0 || (int) $result === 2;
        }

        $normalized = strtolower(trim((string) $result));

        return in_array($normalized, ['loss', 'lost', 'losing', 'sl', 'stop_loss', 'stopped'], true);
    }

    private function minutesSinceLastLoss($trade, $accountStats): ?float
    {
        $minutes = $this->numericValue([$accountStats, $trade], [
            'minutes_since_last_loss',
            'minutes_after_last_loss',
        ]);

        if ($minutes !== null) {
            return max(0, $minutes);
        }

        $tradeOpenedAt = $this->dateValue([$trade], ['opened_at', 'open_date', 'trade_date']);
        $lastLossClosedAt = $this->dateValue([$accountStats], [
            'last_loss_closed_at',
            'previous_losing_trade_closed_at',
            'previous_loss_closed_at',
        ]);

        if (! $tradeOpenedAt || ! $lastLossClosedAt || $tradeOpenedAt->lt($lastLossClosedAt)) {
            return null;
        }

        return $lastLossClosedAt->diffInMinutes($tradeOpenedAt);
    }

    private function sameSymbolAfterLoss($trade, $accountStats): bool
    {
        if (! $this->lastTradeWasLoss($accountStats)) {
            return false;
        }

        $currentSymbol = $this->normalizeSymbol($this->firstValue([$trade, $accountStats], ['symbol', 'pair', 'current_symbol']));
        $previousSymbol = $this->normalizeSymbol($this->firstValue([$accountStats], [
            'previous_losing_trade_symbol',
            'previous_loss_symbol',
            'last_loss_symbol',
            'last_trade_symbol',
            'previous_losing_trade_pair',
            'previous_loss_pair',
        ]));

        return $currentSymbol !== '' && $previousSymbol !== '' && $currentSymbol === $previousSymbol;
    }

    private function sameDirectionAfterStopLoss($trade, $accountStats): bool
    {
        if (! $this->previousLossWasStopLoss($accountStats)) {
            return false;
        }

        $currentDirection = $this->normalizeDirection($this->firstValue([$trade, $accountStats], ['direction', 'current_direction']));
        $previousDirection = $this->normalizeDirection($this->firstValue([$accountStats], [
            'previous_losing_trade_direction',
            'previous_loss_direction',
            'last_loss_direction',
            'last_trade_direction',
        ]));

        return $currentDirection !== '' && $previousDirection !== '' && $currentDirection === $previousDirection;
    }

    private function previousLossWasStopLoss($accountStats): bool
    {
        if ($this->boolValue($accountStats, ['previous_losing_trade_was_stop_loss', 'previous_loss_was_stop_loss'], false)) {
            return true;
        }

        $exitReason = strtolower(trim((string) $this->firstValue([$accountStats], [
            'previous_losing_trade_exit_reason',
            'previous_loss_exit_reason',
            'last_loss_exit_reason',
        ], '')));

        return in_array($exitReason, ['sl', 'stop_loss', 'stop loss', 'stopped'], true);
    }

    private function emotionScore($trade, $accountStats): int
    {
        $emotion = strtolower(trim((string) $this->firstValue([$trade, $accountStats], ['emotion_tag', 'emotion', 'mood_tag'], '')));

        return match ($emotion) {
            'revenge', 'angry', 'panic' => 20,
            'fomo', 'greedy', 'stress', 'stressed' => 15,
            'fear', 'frustrated', 'frustration' => 10,
            default => 0,
        };
    }

    private function directionMatchesSetup($trade, array $plan, $accountStats): bool
    {
        $tradeDirection = $this->normalizeDirection($this->firstValue([$trade, $accountStats], ['direction', 'current_direction']));
        $setupDirection = $this->normalizeDirection($this->firstValue([$accountStats, $plan], [
            'setup_direction',
            'planned_direction',
            'direction',
            'setup.direction',
        ]));

        return $tradeDirection !== '' && $setupDirection !== '' && $tradeDirection === $setupDirection;
    }

    private function symbolMatchesSetup($trade, array $plan, $accountStats): bool
    {
        $tradeSymbol = $this->normalizeSymbol($this->firstValue([$trade, $accountStats], ['symbol', 'pair', 'current_symbol']));
        $setupSymbol = $this->normalizeSymbol($this->firstValue([$accountStats, $plan], [
            'setup_symbol',
            'planned_symbol',
            'symbol',
            'pair',
            'setup.symbol',
            'setup.pair',
        ]));

        return $tradeSymbol !== '' && $setupSymbol !== '' && $tradeSymbol === $setupSymbol;
    }

    private function normalizeDirection($direction): string
    {
        if ($this->blank($direction)) {
            return '';
        }

        if (is_numeric($direction)) {
            return (int) $direction === 1 ? 'buy' : ((int) $direction === 2 ? 'sell' : '');
        }

        $normalized = strtolower(trim((string) $direction));

        return match ($normalized) {
            '1', 'buy', 'long' => 'buy',
            '2', 'sell', 'short' => 'sell',
            default => $normalized,
        };
    }

    private function normalizeSymbol($symbol): string
    {
        return strtoupper(trim((string) ($symbol ?? '')));
    }

    private function capScore(float $score): float
    {
        return round(max(0, min(100, $score)), 2);
    }

    private function firstValue(array $sources, array $keys, $default = null)
    {
        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if ($this->hasField($source, $key)) {
                    return data_get($this->sourceToArray($source), $key);
                }
            }
        }

        return $default;
    }

    private function numericValue(array $sources, array $keys): ?float
    {
        $value = $this->firstValue($sources, $keys);

        if ($this->blank($value)) {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([',', '%'], '', trim($value));
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function dateValue(array $sources, array $keys): ?Carbon
    {
        $value = $this->firstValue($sources, $keys);

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($this->blank($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function boolValue($source, array $keys, bool $default): bool
    {
        $value = $this->firstValue([$source], $keys);

        if ($this->blank($value)) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function knownMissing(array $sources, array $keys): bool
    {
        $known = false;

        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if (! $this->hasField($source, $key)) {
                    continue;
                }

                $known = true;
                $value = data_get($this->sourceToArray($source), $key);

                if (! $this->blank($value)) {
                    return false;
                }
            }
        }

        return $known;
    }

    private function hasUsableValue(array $sources, array $keys): bool
    {
        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if (! $this->hasField($source, $key)) {
                    continue;
                }

                if (! $this->blank(data_get($this->sourceToArray($source), $key))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasField($source, string $key): bool
    {
        if ($source === null) {
            return false;
        }

        $array = $this->sourceToArray($source);

        return Arr::has($array, $key);
    }

    private function sourceToArray($source): array
    {
        if ($source === null) {
            return [];
        }

        if (is_array($source)) {
            return $source;
        }

        if (is_object($source)) {
            if (method_exists($source, 'getAttributes')) {
                $attributes = $source->getAttributes();

                if (is_array($attributes)) {
                    return array_merge($attributes, get_object_vars($source));
                }
            }

            return get_object_vars($source);
        }

        return [];
    }

    private function blank($value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && count($value) === 0);
    }

    private function uniqueReasons(array $reasons): array
    {
        $unique = [];

        foreach ($reasons as $reason) {
            if ($reason === '' || in_array($reason, $unique, true)) {
                continue;
            }

            $unique[] = $reason;
        }

        return $unique;
    }
}
