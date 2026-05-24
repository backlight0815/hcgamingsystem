<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class TradingJournalAnalytics
{
    public const BEST_DAY_LIMIT_PERCENT = 40.0;
    public const MIN_GROSS_PROFIT_PERCENT = 2.0;
    public const DAILY_LOSS_LIMIT_PERCENT = 5.0;
    public const MAXIMUM_LOSS_LIMIT_PERCENT = 10.0;
    public const LOT_AVERAGE_DEVIATION_MEDIUM_RATIO = 1.5;
    public const LOT_AVERAGE_DEVIATION_HIGH_RATIO = 2.0;
    public const DURATION_DEVIATION_MEDIUM_RATIO = 0.35;
    public const DURATION_DEVIATION_HIGH_RATIO = 0.20;
    public const XAUUSD_LEVERAGE = 30.0;
    public const XAUUSD_CONTRACT_SIZE = 100.0;
    public const MARGIN_PRESSURE_MEDIUM_PERCENT = 35.0;
    public const MARGIN_PRESSURE_HIGH_PERCENT = 60.0;
    public const MARGIN_PRESSURE_EXTREME_PERCENT = 80.0;
    private const LAYERING_WINDOW_MINUTES = 90;

    public function bestDayRule(Collection $trades): array
    {
        $dailyProfitLoss = $this->dailyProfitLoss($trades);
        $bestDay = $dailyProfitLoss
            ->filter(fn (float $profitLoss): bool => $profitLoss > 0)
            ->sortDesc()
            ->first();

        $bestDay = $bestDay !== null ? round((float) $bestDay, 2) : 0.0;
        $bestDayDate = null;

        if ($bestDay > 0) {
            $bestDayDate = $dailyProfitLoss
                ->filter(fn (float $profitLoss): bool => round($profitLoss, 2) === $bestDay)
                ->keys()
                ->first();
        }

        $totalGeneratedProfit = round(max(0, (float) $dailyProfitLoss->sum()), 2);
        $scorePercent = $totalGeneratedProfit > 0
            ? round(($bestDay / $totalGeneratedProfit) * 100, 2)
            : 0.0;

        $requiredTotalProfit = $bestDay > 0
            ? round($bestDay / (self::BEST_DAY_LIMIT_PERCENT / 100), 2)
            : 0.0;

        $additionalProfitNeeded = round(max(0, $requiredTotalProfit - $totalGeneratedProfit), 2);
        $passed = $totalGeneratedProfit > 0 && $scorePercent <= self::BEST_DAY_LIMIT_PERCENT;

        return [
            'limit_percent' => self::BEST_DAY_LIMIT_PERCENT,
            'best_winning_day' => $bestDay,
            'best_winning_day_date' => $bestDayDate,
            'total_generated_profit' => $totalGeneratedProfit,
            'score_percent' => $scorePercent,
            'required_total_profit' => $requiredTotalProfit,
            'additional_profit_needed' => $additionalProfitNeeded,
            'passed' => $passed,
            'status' => $passed ? 'Passed' : ($bestDay > 0 ? 'Needs More Profit' : 'No Profit Yet'),
            'grade' => $this->bestDayGrade($scorePercent, $totalGeneratedProfit),
            'daily_profit_loss' => $dailyProfitLoss,
        ];
    }

    public function grossProfitRule(Collection $trades, float $accountBalance): array
    {
        $accountBalance = round(max(0, $accountBalance), 2);
        $grossProfit = round($this->grossProfit($trades), 2);
        $requiredAmount = round($accountBalance * (self::MIN_GROSS_PROFIT_PERCENT / 100), 2);
        $achievedPercent = $accountBalance > 0
            ? round(($grossProfit / $accountBalance) * 100, 2)
            : 0.0;

        $passed = $accountBalance > 0 && $grossProfit >= $requiredAmount && $grossProfit > 0;

        return [
            'minimum_percent' => self::MIN_GROSS_PROFIT_PERCENT,
            'account_balance' => $accountBalance,
            'gross_profit' => $grossProfit,
            'required_amount' => $requiredAmount,
            'remaining_amount' => round(max(0, $requiredAmount - $grossProfit), 2),
            'achieved_percent' => $achievedPercent,
            'passed' => $passed,
            'status' => $passed ? 'Passed' : 'Needs More Gross Profit',
        ];
    }

    public function hedgingProfile(Collection $trades): array
    {
        $normalizedTrades = $this->normalizedTrades($trades)
            ->filter(fn (array $trade): bool => in_array($trade['direction'], [1, 2], true)
                && $trade['opened_at'] instanceof Carbon
                && $trade['closed_at'] instanceof Carbon
                && $trade['pair'] !== 'N/A')
            ->values();

        $overlaps = collect();
        $hedgedTradeKeys = collect();
        $hedgedTradeMap = collect();

        $normalizedTrades
            ->groupBy(fn (array $trade): string => ($trade['user_id'] ?? 'account') . ':' . $trade['pair'])
            ->each(function (Collection $pairTrades) use (&$overlaps, &$hedgedTradeKeys, &$hedgedTradeMap): void {
                $pairTrades = $pairTrades->sortBy('opened_at')->values();
                $count = $pairTrades->count();

                for ($i = 0; $i < $count; $i++) {
                    $first = $pairTrades[$i];

                    for ($j = $i + 1; $j < $count; $j++) {
                        $second = $pairTrades[$j];

                        if ($second['opened_at']->gte($first['closed_at'])) {
                            break;
                        }

                        if ($first['direction'] === $second['direction']) {
                            continue;
                        }

                        $overlapStart = $first['opened_at']->greaterThan($second['opened_at'])
                            ? $first['opened_at']->copy()
                            : $second['opened_at']->copy();
                        $overlapEnd = $first['closed_at']->lessThan($second['closed_at'])
                            ? $first['closed_at']->copy()
                            : $second['closed_at']->copy();

                        if ($overlapEnd->lte($overlapStart)) {
                            continue;
                        }

                        $overlapMinutes = max(1, $overlapStart->diffInMinutes($overlapEnd));
                        $buy = $first['direction'] === 1 ? $first : $second;
                        $sell = $first['direction'] === 2 ? $first : $second;

                        $overlaps->push([
                            'pair' => $first['pair'],
                            'buy_trade_key' => $buy['key'],
                            'buy_trade_id' => $buy['id'],
                            'buy_trade_label' => $this->tradeLabel($buy),
                            'sell_trade_key' => $sell['key'],
                            'sell_trade_id' => $sell['id'],
                            'sell_trade_label' => $this->tradeLabel($sell),
                            'buy_lot' => $buy['lot_size'],
                            'sell_lot' => $sell['lot_size'],
                            'overlap_minutes' => $overlapMinutes,
                            'overlap_label' => $this->formatMinutes($overlapMinutes),
                            'started_at' => $overlapStart,
                            'ended_at' => $overlapEnd,
                        ]);

                        $hedgedTradeKeys->push($first['key']);
                        $hedgedTradeKeys->push($second['key']);
                        $this->markHedgedTrade($hedgedTradeMap, $first, $second, $overlapMinutes);
                        $this->markHedgedTrade($hedgedTradeMap, $second, $first, $overlapMinutes);
                    }
                }
            });

        $hedgedTradeMap = $hedgedTradeMap->map(function (array $trade): array {
            $counterparts = collect($trade['counterparts'])
                ->unique('key')
                ->values();

            $totalOverlapMinutes = (int) collect($trade['overlaps'])->sum();
            $maxOverlapMinutes = (int) collect($trade['overlaps'])->max();

            return [
                'is_hedging' => true,
                'status' => 'Hedging',
                'pair' => $trade['pair'],
                'overlap_count' => count($trade['overlaps']),
                'counterparts' => $counterparts,
                'counterpart_labels' => $counterparts->pluck('label')->implode(', '),
                'total_overlap_minutes' => $totalOverlapMinutes,
                'total_overlap_label' => $this->formatMinutes($totalOverlapMinutes),
                'max_overlap_minutes' => $maxOverlapMinutes,
                'max_overlap_label' => $this->formatMinutes($maxOverlapMinutes),
            ];
        });

        $hedgedTradeCount = $hedgedTradeKeys->unique()->count();
        $totalTrades = $normalizedTrades->count();
        $hedgedTradePercent = $totalTrades > 0 ? round(($hedgedTradeCount / $totalTrades) * 100, 2) : 0.0;

        return [
            'detected' => $overlaps->isNotEmpty(),
            'status' => $overlaps->isNotEmpty() ? 'Hedging Detected' : 'Clear',
            'total_overlaps' => $overlaps->count(),
            'hedged_trade_count' => $hedgedTradeCount,
            'hedged_trade_percent' => $hedgedTradePercent,
            'affected_pairs' => $overlaps->pluck('pair')->unique()->values(),
            'examples' => $overlaps->sortByDesc('started_at')->take(10)->values(),
            'trade_map' => $hedgedTradeMap,
        ];
    }

    public function positionConsistency(Collection $trades): array
    {
        $lots = $trades
            ->map(fn ($trade): float => (float) $this->value($trade, 'lot_size', 0))
            ->filter(fn (float $lot): bool => $lot > 0)
            ->values();

        $count = $lots->count();
        $average = $count > 0 ? round((float) $lots->avg(), 4) : 0.0;
        $minimum = $count > 0 ? round((float) $lots->min(), 4) : 0.0;
        $maximum = $count > 0 ? round((float) $lots->max(), 4) : 0.0;
        $median = $count > 0 ? round($this->median($lots), 4) : 0.0;
        $stdDeviation = $count > 1 ? round($this->standardDeviation($lots), 4) : 0.0;
        $coefficientOfVariation = $average > 0 ? round(($stdDeviation / $average) * 100, 2) : 0.0;
        $rangeRatio = $minimum > 0 ? round($maximum / $minimum, 2) : 0.0;
        $mode = $this->modeLot($lots);
        $anchorLot = $mode['lot'];
        $anchorCount = $mode['count'];
        $anchorShare = $count > 0 ? round(($anchorCount / $count) * 100, 2) : 0.0;
        $nearTolerance = $anchorLot > 0 ? max(0.02, $anchorLot * 0.35) : 0.0;
        $nearAnchorCount = $anchorLot > 0
            ? $lots->filter(fn (float $lot): bool => abs($lot - $anchorLot) <= $nearTolerance)->count()
            : 0;
        $nearAnchorShare = $count > 0 ? round(($nearAnchorCount / $count) * 100, 2) : 0.0;
        $withinBand = $average > 0
            ? $lots->filter(fn (float $lot): bool => abs($lot - $average) <= ($average * 0.20))->count()
            : 0;
        $withinBandPercent = $count > 0 ? round(($withinBand / $count) * 100, 2) : 0.0;

        $sampleScore = min(10, ($count / 100) * 10);
        $anchorScore = ($anchorShare / 100) * 35;
        $nearAnchorScore = ($nearAnchorShare / 100) * 20;
        $variationScore = max(0, 25 - min(25, $coefficientOfVariation * 0.45));
        $rangeScore = max(0, 10 - min(10, max(0, $rangeRatio - 1) * 3));
        $score = $count > 1
            ? round(max(0, min(100, $sampleScore + $anchorScore + $nearAnchorScore + $variationScore + $rangeScore)), 2)
            : ($count === 1 ? 45.0 : 0.0);
        $isDynamic = $coefficientOfVariation > 35 || $rangeRatio > 2.5;

        $status = match (true) {
            $count === 0 => 'No Position Data',
            $coefficientOfVariation <= 10 && $rangeRatio <= 1.5 => 'Very Consistent',
            $coefficientOfVariation <= 20 && $rangeRatio <= 2.0 => 'Consistent',
            $coefficientOfVariation <= 35 && $rangeRatio <= 2.5 => 'Moderately Dynamic',
            $coefficientOfVariation <= 60 => 'Dynamic',
            default => 'Highly Dynamic',
        };

        return [
            'status' => $status,
            'grade' => $this->scoreGrade($score, $count),
            'description' => $this->positionConsistencyDescription($score, $count, $anchorLot, $anchorShare, $nearAnchorShare),
            'is_dynamic' => $isDynamic,
            'trade_count' => $count,
            'average_lot' => $average,
            'median_lot' => $median,
            'anchor_lot' => $anchorLot,
            'anchor_lot_count' => $anchorCount,
            'anchor_lot_share' => $anchorShare,
            'near_anchor_tolerance' => round($nearTolerance, 4),
            'near_anchor_count' => $nearAnchorCount,
            'near_anchor_share' => $nearAnchorShare,
            'min_lot' => $minimum,
            'max_lot' => $maximum,
            'std_deviation' => $stdDeviation,
            'coefficient_of_variation' => $coefficientOfVariation,
            'range_ratio' => $rangeRatio,
            'within_20_percent_count' => $withinBand,
            'within_20_percent' => $withinBandPercent,
            'score' => $score,
            'score_breakdown' => [
                [
                    'criteria' => 'Sample Depth',
                    'points' => round($sampleScore, 2),
                    'max_points' => 10,
                    'description' => 'Builds toward full credit at 100 trades or more.',
                ],
                [
                    'criteria' => 'Main Lot Adherence',
                    'points' => round($anchorScore, 2),
                    'max_points' => 35,
                    'description' => 'Share of trades using the most common lot size exactly.',
                ],
                [
                    'criteria' => 'Near Main Lot',
                    'points' => round($nearAnchorScore, 2),
                    'max_points' => 20,
                    'description' => 'Trades near the main lot size, allowing small tactical adjustments.',
                ],
                [
                    'criteria' => 'Variation Control',
                    'points' => round($variationScore, 2),
                    'max_points' => 25,
                    'description' => 'Lower lot-size dispersion receives more points.',
                ],
                [
                    'criteria' => 'Range Discipline',
                    'points' => round($rangeScore, 2),
                    'max_points' => 10,
                    'description' => 'Penalizes a wide gap between smallest and largest lot sizes.',
                ],
            ],
            'grade_ranking' => $this->positionGradeRanking(),
        ];
    }

    public function durationStats(Collection $trades): array
    {
        $durations = $trades
            ->map(function ($trade): ?int {
                $openedAt = $this->tradeOpenedAt($trade);
                $closedAt = $this->tradeClosedAt($trade);

                if (! $openedAt instanceof Carbon || ! $closedAt instanceof Carbon || $closedAt->lt($openedAt)) {
                    return null;
                }

                return max(0, $openedAt->diffInMinutes($closedAt));
            })
            ->filter(fn ($duration): bool => $duration !== null)
            ->values();

        $count = $durations->count();
        $average = $count > 0 ? (int) round($durations->avg()) : 0;
        $median = $count > 0 ? (int) round($this->median($durations)) : 0;
        $minimum = $count > 0 ? (int) $durations->min() : 0;
        $maximum = $count > 0 ? (int) $durations->max() : 0;

        return [
            'trade_count' => $count,
            'average_minutes' => $average,
            'median_minutes' => $median,
            'min_minutes' => $minimum,
            'max_minutes' => $maximum,
            'average_label' => $this->formatMinutes($average),
            'median_label' => $this->formatMinutes($median),
            'min_label' => $this->formatMinutes($minimum),
            'max_label' => $this->formatMinutes($maximum),
        ];
    }

    public function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . 'm';
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0 ? $hours . 'h ' . $remainingMinutes . 'm' : $hours . 'h';
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0 ? $days . 'd ' . $remainingHours . 'h' : $days . 'd';
    }

    public function dailyProfitLoss(Collection $trades): Collection
    {
        return $trades
            ->filter(fn ($trade): bool => $this->tradeClosedAt($trade) instanceof Carbon)
            ->groupBy(fn ($trade): string => $this->tradeClosedAt($trade)->toDateString())
            ->map(fn (Collection $dayTrades): float => round((float) $dayTrades->sum(fn ($trade): float => (float) $this->value($trade, 'profit_loss', 0)), 2));
    }

    public function profitableDayRule(Collection $trades, int $requiredDays = 3, float $threshold = 0.0): array
    {
        $requiredDays = max(0, $requiredDays);
        $threshold = round(max(0, $threshold), 2);

        $dailyBreakdown = $trades
            ->filter(fn ($trade): bool => $this->tradeClosedAt($trade) instanceof Carbon)
            ->groupBy(fn ($trade): string => $this->tradeClosedAt($trade)->toDateString())
            ->map(function (Collection $dayTrades, string $date) use ($threshold): array {
                $profitLoss = round((float) $dayTrades->sum(fn ($trade): float => (float) $this->value($trade, 'profit_loss', 0)), 2);

                return [
                    'date' => $date,
                    'profit_loss' => $profitLoss,
                    'trade_count' => $dayTrades->count(),
                    'is_profitable' => $threshold > 0 ? $profitLoss >= $threshold : $profitLoss > 0,
                ];
            })
            ->sortKeys()
            ->values();

        $profitableDays = $dailyBreakdown
            ->filter(fn (array $day): bool => (bool) ($day['is_profitable'] ?? false))
            ->values();
        $hasProfitableDay = $profitableDays->count() >= $requiredDays;

        return [
            'threshold' => $threshold,
            'threshold_label' => $threshold > 0
                ? 'Net daily P/L >= ' . number_format($threshold, 2)
                : 'Net daily P/L > 0 after all closed trades',
            'profitable_days' => $profitableDays->count(),
            'required_days' => $requiredDays,
            'has_profitable_day' => $hasProfitableDay,
            'status_label' => $hasProfitableDay ? 'Achieved' : 'Pending',
            'profitable_dates' => $profitableDays->pluck('date')->values()->all(),
            'daily_breakdown' => $dailyBreakdown->all(),
        ];
    }

    public function grossProfit(Collection $trades): float
    {
        return round((float) $trades->sum(fn ($trade): float => max(0, (float) $this->value($trade, 'profit_loss', 0))), 2);
    }

    public function behavioralRiskProfile(Collection $trades, float $accountBalance = 0): array
    {
        $revenge = $this->revengeTradingProfile($trades);
        $gambling = $this->gamblingBehaviorProfile($trades, $accountBalance);
        $layering = $this->layeringProfile($trades);

        $riskScore = round(min(100, ($revenge['score'] * 0.35) + ($gambling['score'] * 0.40) + ($layering['score'] * 0.25)), 2);
        $tone = $riskScore >= 70 ? 'danger' : ($riskScore >= 40 ? 'warning' : ($trades->isEmpty() ? 'secondary' : 'success'));
        $riskLevel = match (true) {
            $trades->isEmpty() => 'No Data',
            $riskScore >= 70 => 'High Behavioral Risk',
            $riskScore >= 40 => 'Watchlist',
            $riskScore >= 20 => 'Mild Risk',
            default => 'Controlled',
        };

        $styleTags = collect([
            $revenge['detected'] ? ($revenge['tier_label'] ?? 'Low') . '-tier revenge' : null,
            $gambling['detected'] ? ($gambling['tier_label'] ?? 'Low') . '-tier gambling' : null,
            $gambling['overtrading_days'] > 0 ? 'Overtrading bursts' : null,
            $gambling['oversized_loss_count'] > 0 ? 'Oversized losses' : null,
            $gambling['lot_spike_count'] > 0 ? 'Lot-size spikes' : null,
            $gambling['rapid_trade_count'] > 0 ? 'Rapid-fire entries' : null,
            $layering['detected'] ? 'Layering' : null,
            $layering['adverse_layer_count'] > 0 ? 'Averaging into adverse price' : null,
            $layering['max_active_layers'] >= 3 ? 'Stacked exposure' : null,
        ])->filter()->values();

        $styleLabel = match (true) {
            $trades->isEmpty() => 'No Trading Style Yet',
            $layering['score'] >= 70 && ($revenge['score'] >= 55 || $gambling['score'] >= 55) => 'Stacked High-Variance Trader',
            $revenge['score'] >= 65 && $gambling['score'] >= 55 => 'Emotional High-Variance Trader',
            $layering['score'] >= 65 => 'Layering / Averaging Trader',
            $revenge['score'] >= 65 => 'Reactive Revenge Trader',
            $gambling['score'] >= 65 => 'High-Variance Gambling Style',
            $gambling['score'] >= 40 || $revenge['score'] >= 40 || $layering['score'] >= 40 => 'Aggressive Watchlist Trader',
            default => 'Controlled Trader',
        };

        return [
            'style_label' => $styleLabel,
            'risk_level' => $riskLevel,
            'risk_score' => $riskScore,
            'tone' => $tone,
            'summary' => $this->behaviorSummary($styleLabel, $riskScore, $revenge, $gambling, $layering, $styleTags),
            'style_tags' => $styleTags,
            'revenge' => $revenge,
            'gambling' => $gambling,
            'layering' => $layering,
        ];
    }

    public function behaviorScorePenalty(array $behaviorProfile, float $baseScore): array
    {
        $baseScore = round($baseScore, 2);
        $revengeRank = max(0, (int) data_get($behaviorProfile, 'revenge.tier_rank', 0));
        $gamblingRank = max(0, (int) data_get($behaviorProfile, 'gambling.tier_rank', 0));
        $highestRank = max($revengeRank, $gamblingRank);
        $highestTier = $this->behaviorTier($highestRank);
        $percent = $this->behaviorPenaltyPercent($highestRank);
        $points = $baseScore > 0 && $percent > 0
            ? round($baseScore * ($percent / 100), 2)
            : 0.0;
        $adjustedScore = $baseScore > 0
            ? round(max(0, $baseScore - $points), 2)
            : $baseScore;

        $behaviors = collect([
            'revenge' => $this->behaviorPenaltyBehavior('Revenge Behaviour', (array) data_get($behaviorProfile, 'revenge', [])),
            'gambling' => $this->behaviorPenaltyBehavior('Gambling Behaviour', (array) data_get($behaviorProfile, 'gambling', [])),
        ]);

        $triggers = $behaviors
            ->filter(fn (array $behavior): bool => (int) ($behavior['tier_rank'] ?? 0) === $highestRank && $highestRank >= 2)
            ->pluck('status')
            ->values();

        $triggerLabel = $triggers->isNotEmpty()
            ? $triggers->implode(' + ')
            : 'Clear or low revenge/gambling tier';
        $tone = $percent >= 10 ? 'danger' : ($percent > 0 ? 'warning' : 'success');

        return [
            'base_score' => $baseScore,
            'adjusted_score' => $adjustedScore,
            'percent' => $percent,
            'points' => $points,
            'status' => $percent > 0 ? number_format($percent, 0) . '% Behaviour Penalty' : 'No Behaviour Penalty',
            'tone' => $tone,
            'tier' => $highestTier['tier'],
            'tier_rank' => $highestTier['tier_rank'],
            'tier_label' => $highestTier['tier_label'],
            'tier_short' => $highestTier['tier_short'],
            'tier_tone' => $highestTier['tier_tone'],
            'trigger_label' => $triggerLabel,
            'summary' => $this->behaviorPenaltySummary($percent, $triggerLabel),
            'behaviors' => $behaviors->all(),
        ];
    }

    public function behaviorWeeklyComparison(Collection $trades, float $accountBalance = 0, ?Carbon $anchorDate = null): array
    {
        $normalizedTrades = $this->normalizedTrades($trades)
            ->map(function (array $trade): array {
                $trade['behavior_at'] = $trade['closed_at'] instanceof Carbon
                    ? $trade['closed_at']
                    : ($trade['opened_at'] instanceof Carbon ? $trade['opened_at'] : null);

                return $trade;
            })
            ->filter(fn (array $trade): bool => $trade['behavior_at'] instanceof Carbon)
            ->values();

        $latestTrade = $normalizedTrades
            ->sortByDesc(fn (array $trade): int => $trade['behavior_at']->timestamp)
            ->first();

        $anchor = $anchorDate instanceof Carbon
            ? $anchorDate->copy()->endOfDay()
            : (($latestTrade['behavior_at'] ?? null) instanceof Carbon
                ? $latestTrade['behavior_at']->copy()->endOfDay()
                : Carbon::now()->endOfDay());

        $currentStart = $anchor->copy()->subDays(6)->startOfDay();
        $currentEnd = $anchor->copy()->endOfDay();
        $previousStart = $currentStart->copy()->subDays(7)->startOfDay();
        $previousEnd = $currentStart->copy()->subSecond();

        $currentTrades = $this->periodBehaviorTrades($normalizedTrades, $currentStart, $currentEnd);
        $previousTrades = $this->periodBehaviorTrades($normalizedTrades, $previousStart, $previousEnd);

        $currentProfile = $this->behavioralRiskProfile($currentTrades, $accountBalance);
        $previousProfile = $this->behavioralRiskProfile($previousTrades, $accountBalance);

        return [
            'current_period_label' => $this->periodLabel($currentStart, $currentEnd),
            'previous_period_label' => $this->periodLabel($previousStart, $previousEnd),
            'current_trade_count' => $currentTrades->count(),
            'previous_trade_count' => $previousTrades->count(),
            'metrics' => [
                'overall' => $this->behaviorTrendMetric(
                    'Overall Trader Behaviour',
                    $currentProfile,
                    $previousProfile,
                    'risk_score',
                    'risk_level',
                    null,
                    $currentTrades->count(),
                    $previousTrades->count()
                ),
                'revenge' => $this->behaviorTrendMetric(
                    'Revenge Behaviour',
                    $currentProfile,
                    $previousProfile,
                    'revenge.score',
                    'revenge.status',
                    'revenge',
                    $currentTrades->count(),
                    $previousTrades->count()
                ),
                'gambling' => $this->behaviorTrendMetric(
                    'Gambling Behaviour',
                    $currentProfile,
                    $previousProfile,
                    'gambling.score',
                    'gambling.status',
                    'gambling',
                    $currentTrades->count(),
                    $previousTrades->count()
                ),
            ],
        ];
    }

    public function layeringProfile(Collection $trades): array
    {
        $normalizedTrades = $this->normalizedTrades($trades)
            ->filter(fn (array $trade): bool => in_array($trade['direction'], [1, 2], true)
                && $trade['opened_at'] instanceof Carbon
                && $trade['pair'] !== 'N/A')
            ->sortBy(fn (array $trade): string => $trade['opened_at']->format('Y-m-d H:i:s') . ':' . $trade['key'])
            ->values();

        if ($normalizedTrades->count() < 2) {
            return [
                'detected' => false,
                'status' => $normalizedTrades->isEmpty() ? 'No Data' : 'Insufficient Sample',
                'tone' => 'secondary',
                'score' => 0.0,
                'event_count' => 0,
                'layered_trade_count' => 0,
                'layered_trade_percent' => 0.0,
                'overlap_count' => 0,
                'fast_layer_count' => 0,
                'lot_increase_count' => 0,
                'adverse_layer_count' => 0,
                'max_active_layers' => 0,
                'average_delay_minutes' => 0,
                'average_delay_label' => '0m',
                'affected_pairs' => collect(),
                'examples' => collect(),
                'checks' => $this->layeringChecks(0, 0, 0, 0, 0, 0, collect()),
                'trade_map' => collect(),
            ];
        }

        $events = collect();
        $layeredTradeKeys = collect();
        $layeredTradeMap = collect();
        $overlapCount = 0;
        $fastLayerCount = 0;
        $lotIncreaseCount = 0;
        $adverseLayerCount = 0;
        $maxActiveLayers = 0;

        $normalizedTrades
            ->groupBy(fn (array $trade): string => ($trade['user_id'] ?? 'account') . ':' . $trade['pair'] . ':' . $trade['direction'])
            ->each(function (Collection $pairTrades) use (&$events, &$layeredTradeKeys, &$layeredTradeMap, &$overlapCount, &$fastLayerCount, &$lotIncreaseCount, &$adverseLayerCount, &$maxActiveLayers): void {
                $pairTrades = $pairTrades->sortBy('opened_at')->values();
                $count = $pairTrades->count();

                for ($i = 0; $i < $count; $i++) {
                    $first = $pairTrades[$i];

                    for ($j = $i + 1; $j < $count; $j++) {
                        $second = $pairTrades[$j];
                        $delayMinutes = max(0, $first['opened_at']->diffInMinutes($second['opened_at']));
                        $overlaps = $first['closed_at'] instanceof Carbon && $second['opened_at']->lt($first['closed_at']);

                        if (! $overlaps && $delayMinutes > self::LAYERING_WINDOW_MINUTES) {
                            break;
                        }

                        $activeLayers = $this->activeSameDirectionLayerCount($pairTrades, $second['opened_at']);
                        $maxActiveLayers = max($maxActiveLayers, $activeLayers);
                        $lotRatio = (float) $first['lot_size'] > 0
                            ? round((float) $second['lot_size'] / (float) $first['lot_size'], 2)
                            : 0.0;
                        $priceRelationship = $this->layeringPriceRelationship($first, $second);
                        $signals = collect();
                        $eventScore = 0;

                        if ($overlaps) {
                            $signals->push('Same-direction overlap');
                            $eventScore += 22;
                            $overlapCount++;
                        }

                        if ($delayMinutes <= 15) {
                            $signals->push('Immediate layering');
                            $eventScore += 16;
                            $fastLayerCount++;
                        } elseif ($delayMinutes <= 60) {
                            $signals->push('Fast layering');
                            $eventScore += 10;
                            $fastLayerCount++;
                        }

                        if ($lotRatio >= 2.0) {
                            $signals->push('Layer lot doubled or more');
                            $eventScore += 20;
                            $lotIncreaseCount++;
                        } elseif ($lotRatio >= 1.5) {
                            $signals->push('Layer lot increased');
                            $eventScore += 12;
                            $lotIncreaseCount++;
                        }

                        if ($priceRelationship['is_adverse']) {
                            $signals->push($priceRelationship['label']);
                            $eventScore += 18;
                            $adverseLayerCount++;
                        } elseif ($priceRelationship['label'] !== 'No entry price evidence') {
                            $signals->push($priceRelationship['label']);
                            $eventScore += 4;
                        }

                        if ($activeLayers >= 3) {
                            $signals->push($activeLayers . ' active same-direction layers');
                            $eventScore += 18;
                        } elseif ($activeLayers >= 2) {
                            $signals->push('Multiple same-direction layers');
                            $eventScore += 8;
                        }

                        if ($eventScore < 22) {
                            continue;
                        }

                        $event = [
                            'base_trade_key' => $first['key'],
                            'base_trade_label' => $this->tradeLabel($first),
                            'layer_trade_key' => $second['key'],
                            'layer_trade_label' => $this->tradeLabel($second),
                            'pair' => $second['pair'],
                            'direction' => $second['direction'] === 1 ? 'Buy' : 'Sell',
                            'delay_minutes' => $delayMinutes,
                            'delay_label' => $this->formatMinutes($delayMinutes),
                            'overlap' => $overlaps,
                            'base_lot' => $first['lot_size'],
                            'layer_lot' => $second['lot_size'],
                            'lot_ratio' => $lotRatio,
                            'base_entry' => $first['entry_price'],
                            'layer_entry' => $second['entry_price'],
                            'base_profit_loss' => $first['profit_loss'],
                            'layer_profit_loss' => $second['profit_loss'],
                            'active_layers' => $activeLayers,
                            'price_relationship' => $priceRelationship['label'],
                            'is_adverse' => $priceRelationship['is_adverse'],
                            'signals' => $signals,
                            'score' => min(100, $eventScore),
                            'base_opened_at' => $first['opened_at'],
                            'layer_opened_at' => $second['opened_at'],
                        ];

                        $events->push($event);
                        $layeredTradeKeys->push($first['key']);
                        $layeredTradeKeys->push($second['key']);
                        $this->markLayeredTrade($layeredTradeMap, $first, $second, $event);
                        $this->markLayeredTrade($layeredTradeMap, $second, $first, $event);
                    }
                }
            });

        $layeredTradeMap = $layeredTradeMap->map(function (array $trade): array {
            $counterparts = collect($trade['counterparts'])->unique('key')->values();
            $reasons = collect($trade['reasons'])->unique()->values();
            $maxActiveLayers = (int) collect($trade['active_layers'])->max();

            return [
                'is_layering' => true,
                'status' => 'Layering',
                'pair' => $trade['pair'],
                'direction' => $trade['direction'],
                'layer_count' => count($trade['events']),
                'counterparts' => $counterparts,
                'counterpart_labels' => $counterparts->pluck('label')->implode(', '),
                'reasons' => $reasons,
                'reason_labels' => $reasons->implode(', '),
                'max_active_layers' => $maxActiveLayers,
            ];
        });

        $eventCount = $events->count();
        $layeredTradeCount = $layeredTradeKeys->unique()->count();
        $totalTrades = $normalizedTrades->count();
        $layeredTradePercent = $totalTrades > 0 ? round(($layeredTradeCount / $totalTrades) * 100, 2) : 0.0;
        $averageDelay = $eventCount > 0 ? (int) round($events->avg('delay_minutes')) : 0;
        $score = min(100, ($eventCount * 10) + ($overlapCount * 6) + ($fastLayerCount * 4) + ($lotIncreaseCount * 7) + ($adverseLayerCount * 8) + max(0, $maxActiveLayers - 2) * 8);
        $detected = $eventCount > 0;
        $status = match (true) {
            ! $detected => 'Clear',
            $score >= 70 => 'High Layering Risk',
            $score >= 40 => 'Layering Watchlist',
            default => 'Mild Layering Pattern',
        };
        $tone = $score >= 70 ? 'danger' : ($score >= 40 ? 'warning' : ($detected ? 'primary' : 'success'));

        return [
            'detected' => $detected,
            'status' => $status,
            'tone' => $tone,
            'score' => round($score, 2),
            'event_count' => $eventCount,
            'layered_trade_count' => $layeredTradeCount,
            'layered_trade_percent' => $layeredTradePercent,
            'overlap_count' => $overlapCount,
            'fast_layer_count' => $fastLayerCount,
            'lot_increase_count' => $lotIncreaseCount,
            'adverse_layer_count' => $adverseLayerCount,
            'max_active_layers' => $maxActiveLayers,
            'average_delay_minutes' => $averageDelay,
            'average_delay_label' => $this->formatMinutes($averageDelay),
            'affected_pairs' => $events->pluck('pair')->unique()->values(),
            'examples' => $events
                ->sortByDesc(fn (array $event): int => (int) ($event['score'] ?? 0))
                ->take(10)
                ->values(),
            'checks' => $this->layeringChecks($score, $eventCount, $overlapCount, $fastLayerCount, $lotIncreaseCount, $adverseLayerCount, $events),
            'trade_map' => $layeredTradeMap,
        ];
    }

    public function revengeTradingProfile(Collection $trades): array
    {
        $normalizedTrades = $this->normalizedTrades($trades)
            ->filter(fn (array $trade): bool => $trade['opened_at'] instanceof Carbon || $trade['closed_at'] instanceof Carbon)
            ->sortBy(fn (array $trade): string => ($trade['opened_at'] ?? $trade['closed_at'])->format('Y-m-d H:i:s') . ':' . $trade['key'])
            ->values();

        if ($normalizedTrades->count() < 2) {
            $tier = $this->behaviorTier(0);

            return [
                'detected' => false,
                'status' => $normalizedTrades->isEmpty() ? 'No Data' : 'Insufficient Sample',
                'tone' => 'secondary',
                'score' => 0.0,
                'tier' => $tier['tier'],
                'tier_rank' => $tier['tier_rank'],
                'tier_label' => $tier['tier_label'],
                'tier_short' => $tier['tier_short'],
                'tier_tone' => 'secondary',
                'tier_description' => 'At least two timed trades are needed before revenge tiering can be measured.',
                'event_count' => 0,
                'quick_reentry_count' => 0,
                'lot_increase_count' => 0,
                'loss_streak_reaction_count' => 0,
                'average_delay_minutes' => 0,
                'average_delay_label' => '0m',
                'affected_pairs' => collect(),
                'examples' => collect(),
                'checks' => $this->revengeChecks(0, 0, 0, 0, 0, collect()),
                'trade_map' => collect(),
            ];
        }

        $events = collect();
        $revengeTradeMap = collect();
        $quickReentryCount = 0;
        $lotIncreaseCount = 0;
        $lossStreakReactionCount = 0;
        $lossStreak = 0;

        for ($index = 0; $index < $normalizedTrades->count() - 1; $index++) {
            $trade = $normalizedTrades[$index];
            $profitLoss = (float) $trade['profit_loss'];
            $lossStreak = $profitLoss < 0 ? $lossStreak + 1 : 0;

            if ($profitLoss >= 0 || ! $trade['closed_at'] instanceof Carbon) {
                continue;
            }

            $nextTrade = null;
            for ($nextIndex = $index + 1; $nextIndex < $normalizedTrades->count(); $nextIndex++) {
                $candidate = $normalizedTrades[$nextIndex];

                if ((string) ($candidate['user_id'] ?? 'account') !== (string) ($trade['user_id'] ?? 'account')) {
                    continue;
                }

                if (! $candidate['opened_at'] instanceof Carbon) {
                    continue;
                }

                if ($candidate['opened_at']->gte($trade['closed_at'])) {
                    $nextTrade = $candidate;
                    break;
                }
            }

            if (! $nextTrade) {
                continue;
            }

            $delayMinutes = max(0, $trade['closed_at']->diffInMinutes($nextTrade['opened_at']));

            if ($delayMinutes > 180) {
                continue;
            }

            $lotRatio = $trade['lot_size'] > 0
                ? round($nextTrade['lot_size'] / $trade['lot_size'], 2)
                : 0.0;
            $samePair = $trade['pair'] === $nextTrade['pair'];
            $oppositeDirection = in_array($trade['direction'], [1, 2], true)
                && in_array($nextTrade['direction'], [1, 2], true)
                && $trade['direction'] !== $nextTrade['direction'];

            $signals = collect();
            $eventScore = 0;

            if ($delayMinutes <= 15) {
                $signals->push('Immediate re-entry after loss');
                $eventScore += 25;
                $quickReentryCount++;
            } elseif ($delayMinutes <= 60) {
                $signals->push('Fast re-entry after loss');
                $eventScore += 15;
                $quickReentryCount++;
            }

            if ($lotRatio >= 2.0) {
                $signals->push('Lot size doubled or more');
                $eventScore += 25;
                $lotIncreaseCount++;
            } elseif ($lotRatio >= 1.5) {
                $signals->push('Lot size increased after loss');
                $eventScore += 15;
                $lotIncreaseCount++;
            }

            if ($samePair) {
                $signals->push('Same pair re-entry');
                $eventScore += 8;
            }

            if ($oppositeDirection) {
                $signals->push('Direction flip after loss');
                $eventScore += 8;
            }

            if ($lossStreak >= 2) {
                $signals->push($lossStreak . '-loss streak reaction');
                $eventScore += 14;
                $lossStreakReactionCount++;
            }

            if ($eventScore < 15) {
                continue;
            }

            $eventTier = $this->revengeEventTier($eventScore, $delayMinutes, $lotRatio, $samePair, $oppositeDirection, $lossStreak);
            $event = [
                'trigger_trade_key' => $trade['key'],
                'trigger_trade_label' => $this->tradeLabel($trade),
                'response_trade_key' => $nextTrade['key'],
                'response_trade_label' => $this->tradeLabel($nextTrade),
                'pair' => $nextTrade['pair'],
                'delay_minutes' => $delayMinutes,
                'delay_label' => $this->formatMinutes($delayMinutes),
                'loss_amount' => round(abs($profitLoss), 2),
                'response_profit_loss' => round((float) $nextTrade['profit_loss'], 2),
                'previous_lot' => $trade['lot_size'],
                'response_lot' => $nextTrade['lot_size'],
                'lot_ratio' => $lotRatio,
                'same_pair' => $samePair,
                'opposite_direction' => $oppositeDirection,
                'signals' => $signals,
                'score' => min(100, $eventScore),
                'tier' => $eventTier['tier'],
                'tier_rank' => $eventTier['tier_rank'],
                'tier_label' => $eventTier['tier_label'],
                'tier_short' => $eventTier['tier_short'],
                'tier_tone' => $eventTier['tier_tone'],
                'tier_description' => $eventTier['description'],
                'trigger_closed_at' => $trade['closed_at'],
                'response_opened_at' => $nextTrade['opened_at'],
            ];

            $events->push($event);
            $this->markRevengeTrade($revengeTradeMap, $trade, $nextTrade, $event, 'trigger');
            $this->markRevengeTrade($revengeTradeMap, $nextTrade, $trade, $event, 'response');
        }

        $revengeTradeMap = $revengeTradeMap->map(function (array $trade): array {
            $events = collect($trade['events']);
            $tier = $this->behaviorTier((int) $events->max('tier_rank'));
            $reasons = collect($trade['reasons'])->unique()->values();
            $counterparts = collect($trade['counterparts'])->unique('key')->values();

            return [
                'is_revenge' => true,
                'status' => $this->behaviorTierStatus('Revenge', $tier),
                'tier' => $tier['tier'],
                'tier_rank' => $tier['tier_rank'],
                'tier_label' => $tier['tier_label'],
                'tier_short' => $tier['tier_short'],
                'tier_tone' => $tier['tier_tone'],
                'event_count' => $events->count(),
                'pair' => $trade['pair'],
                'role' => $trade['role'],
                'counterparts' => $counterparts,
                'counterpart_labels' => $counterparts->pluck('label')->implode(', '),
                'reasons' => $reasons,
                'reason_labels' => $reasons->implode(', '),
            ];
        });

        $eventCount = $events->count();
        $averageDelay = $eventCount > 0 ? (int) round($events->avg('delay_minutes')) : 0;
        $score = min(100, ($eventCount * 16) + ($quickReentryCount * 6) + ($lotIncreaseCount * 10) + ($lossStreakReactionCount * 8));
        $scoreTierRank = match (true) {
            $eventCount === 0 => 0,
            $score >= 70 => 3,
            $score >= 40 => 2,
            default => 1,
        };
        $tier = $this->behaviorTier(max($scoreTierRank, (int) $events->max('tier_rank')));
        $detected = $tier['tier_rank'] > 0;
        $status = $detected ? $this->behaviorTierStatus('Revenge', $tier) : 'Clear';
        $tone = $detected ? $tier['tier_tone'] : 'success';

        return [
            'detected' => $detected,
            'status' => $status,
            'tone' => $tone,
            'score' => round($score, 2),
            'tier' => $tier['tier'],
            'tier_rank' => $tier['tier_rank'],
            'tier_label' => $tier['tier_label'],
            'tier_short' => $tier['tier_short'],
            'tier_tone' => $tier['tier_tone'],
            'tier_description' => $this->behaviorProfileTierDescription('revenge', $tier, $events),
            'event_count' => $eventCount,
            'quick_reentry_count' => $quickReentryCount,
            'lot_increase_count' => $lotIncreaseCount,
            'loss_streak_reaction_count' => $lossStreakReactionCount,
            'average_delay_minutes' => $averageDelay,
            'average_delay_label' => $this->formatMinutes($averageDelay),
            'affected_pairs' => $events->pluck('pair')->unique()->values(),
            'examples' => $events
                ->sortByDesc(fn (array $event): int => ((int) ($event['tier_rank'] ?? 0) * 1000) + (int) ($event['score'] ?? 0))
                ->take(10)
                ->values(),
            'checks' => $this->revengeChecks($score, $eventCount, $quickReentryCount, $lotIncreaseCount, $lossStreakReactionCount, $events),
            'trade_map' => $revengeTradeMap,
        ];
    }

    public function gamblingBehaviorProfile(Collection $trades, float $accountBalance = 0): array
    {
        $normalizedTrades = $this->normalizedTrades($trades)
            ->filter(fn (array $trade): bool => $trade['opened_at'] instanceof Carbon || $trade['closed_at'] instanceof Carbon)
            ->sortBy(fn (array $trade): string => ($trade['opened_at'] ?? $trade['closed_at'])->format('Y-m-d H:i:s') . ':' . $trade['key'])
            ->values();

        $tradeCount = $normalizedTrades->count();
        $positionProfile = $this->positionConsistency($trades);
        $durationProfile = $this->durationStats($trades);
        $dailyTradeCounts = $normalizedTrades
            ->filter(fn (array $trade): bool => $trade['opened_at'] instanceof Carbon)
            ->groupBy(fn (array $trade): string => $trade['opened_at']->toDateString())
            ->map(fn (Collection $dayTrades): int => $dayTrades->count());
        $activeDays = $dailyTradeCounts->count();
        $averageTradesPerDay = $activeDays > 0 ? round((float) $dailyTradeCounts->avg(), 2) : 0.0;
        $maxTradesPerDay = $activeDays > 0 ? (int) $dailyTradeCounts->max() : 0;
        $overtradingDays = $dailyTradeCounts->filter(fn (int $count): bool => $count >= 8)->count();
        $rapidTradeCount = $this->rapidTradeCount($normalizedTrades);
        $lotSpikeCount = $this->lotSpikeCount($normalizedTrades);
        $oversizedLosses = $this->oversizedLosses($normalizedTrades, $accountBalance);
        $lotDeviationEvents = $this->lotAverageDeviationEvents($normalizedTrades);
        $durationDeviationEvents = $this->durationDeviationEvents($normalizedTrades);
        $layeringExposureEvents = $this->layeringExposureEvents($normalizedTrades);
        $marginPressureEvents = $this->marginPressureEvents($normalizedTrades, $accountBalance);
        $bestDayRule = $this->bestDayRule($trades);
        $gamblingEvents = $this->gamblingBehaviorEvents(
            $normalizedTrades,
            $dailyTradeCounts,
            $oversizedLosses,
            $lotDeviationEvents,
            $durationDeviationEvents,
            $layeringExposureEvents,
            $marginPressureEvents
        );
        $gamblingExamples = $gamblingEvents
            ->sortByDesc(fn (array $event): int => ((int) ($event['tier_rank'] ?? 0) * 1000) + (int) ($event['score'] ?? 0))
            ->take(10)
            ->values();
        $gamblingTradeMap = $gamblingEvents->mapWithKeys(function (array $event): array {
            return [
                $event['trade_key'] => [
                    'is_gambling' => true,
                    'status' => $this->behaviorTierStatus('Gambling', $event),
                    'tier' => $event['tier'],
                    'tier_rank' => $event['tier_rank'] ?? 0,
                    'tier_label' => $event['tier_label'],
                    'tier_short' => $event['tier_short'],
                    'tier_tone' => $event['tier_tone'],
                    'pair' => $event['pair'],
                    'direction' => $event['direction'],
                    'lot_size' => $event['lot_size'],
                    'reasons' => collect($event['reasons'] ?? []),
                    'reason_labels' => collect($event['reasons'] ?? [])->implode(', '),
                    'evidence' => collect($event['evidence'] ?? []),
                    'evidence_labels' => collect($event['evidence'] ?? [])->implode(' '),
                ],
            ];
        });

        $checks = collect();

        $overtradingPoints = min(25, ($overtradingDays * 8) + max(0, $maxTradesPerDay - 8) * 2);
        $checks->push([
            'name' => 'Overtrading Bursts',
            'value' => $overtradingDays . ' day(s), max ' . $maxTradesPerDay . ' trades/day',
            'points' => round($overtradingPoints, 2),
            'max_points' => 25,
            'status' => $overtradingPoints >= 15 ? 'High' : ($overtradingPoints > 0 ? 'Watch' : 'Clear'),
            'tone' => $overtradingPoints >= 15 ? 'danger' : ($overtradingPoints > 0 ? 'warning' : 'success'),
            'description' => 'Flags days with 8 or more trades, which can show impulse trading or forced activity.',
        ]);

        $lotVariationPoints = min(25, max(0, ((float) $positionProfile['coefficient_of_variation'] - 35) * 0.35) + max(0, ((float) $positionProfile['range_ratio'] - 2.5) * 5));
        $checks->push([
            'name' => 'Position Size Instability',
            'value' => number_format((float) $positionProfile['coefficient_of_variation'], 2) . '% CV, ' . number_format((float) $positionProfile['range_ratio'], 2) . 'x range',
            'points' => round($lotVariationPoints, 2),
            'max_points' => 25,
            'status' => $lotVariationPoints >= 15 ? 'High' : ($lotVariationPoints > 0 ? 'Watch' : 'Clear'),
            'tone' => $lotVariationPoints >= 15 ? 'danger' : ($lotVariationPoints > 0 ? 'warning' : 'success'),
            'description' => 'Large swings in lot size can indicate bet-sizing rather than planned risk.',
        ]);

        $maxAverageLotRatio = $lotDeviationEvents->isNotEmpty() ? (float) $lotDeviationEvents->max('average_ratio') : 0.0;
        $highLotDeviationCount = $lotDeviationEvents
            ->filter(fn (array $event): bool => (string) ($event['level'] ?? 'medium') === 'high')
            ->count();
        $lotDeviationPoints = min(20, ($highLotDeviationCount * 10) + max(0, $lotDeviationEvents->count() - $highLotDeviationCount) * 6);
        $checks->push([
            'name' => 'Account Average Lot Deviation',
            'value' => $lotDeviationEvents->count() . ' trade(s), max ' . number_format($maxAverageLotRatio, 2) . 'x average lot',
            'points' => round($lotDeviationPoints, 2),
            'max_points' => 20,
            'status' => $highLotDeviationCount > 0 ? 'High' : ($lotDeviationEvents->isNotEmpty() ? 'Watch' : 'Clear'),
            'tone' => $highLotDeviationCount > 0 ? 'danger' : ($lotDeviationEvents->isNotEmpty() ? 'warning' : 'success'),
            'description' => 'Compares each trade lot size with the account average and median lot size. A large positive deviation can indicate bet-sizing/gambling behavior.',
        ]);

        $oversizedLossPoints = min(20, $oversizedLosses->count() * 7);
        $checks->push([
            'name' => 'Oversized Losses',
            'value' => $accountBalance > 0 ? $oversizedLosses->count() . ' loss(es) above 3% capital' : 'Capital unavailable',
            'points' => round($oversizedLossPoints, 2),
            'max_points' => 20,
            'status' => $oversizedLossPoints >= 14 ? 'High' : ($oversizedLossPoints > 0 ? 'Watch' : 'No Data'),
            'tone' => $oversizedLossPoints >= 14 ? 'danger' : ($oversizedLossPoints > 0 ? 'warning' : 'secondary'),
            'description' => 'Uses 3% of capital as a proxy threshold when capital is available.',
        ]);

        $maxMarginPercent = $marginPressureEvents->isNotEmpty() ? (float) $marginPressureEvents->max('margin_percent') : 0.0;
        $highMarginCount = $marginPressureEvents
            ->filter(fn (array $event): bool => in_array((string) ($event['level'] ?? 'medium'), ['high', 'extreme'], true))
            ->count();
        $marginPressurePoints = min(25, ($highMarginCount * 12) + max(0, $marginPressureEvents->count() - $highMarginCount) * 7);
        $checks->push([
            'name' => 'XAUUSD Margin Pressure',
            'value' => $accountBalance > 0
                ? $marginPressureEvents->count() . ' setup(s), max ' . number_format($maxMarginPercent, 2) . '% margin use'
                : 'Capital unavailable',
            'points' => round($marginPressurePoints, 2),
            'max_points' => 25,
            'status' => $highMarginCount > 0 ? 'High' : ($marginPressureEvents->isNotEmpty() ? 'Watch' : ($accountBalance > 0 ? 'Clear' : 'No Data')),
            'tone' => $highMarginCount > 0 ? 'danger' : ($marginPressureEvents->isNotEmpty() ? 'warning' : ($accountBalance > 0 ? 'success' : 'secondary')),
            'description' => 'For XAUUSD, estimates margin using 1:30 leverage and 100 oz per lot. Large active margin use makes layered setups easier to spot.',
        ]);

        $rapidTradePoints = min(15, $rapidTradeCount * 3);
        $checks->push([
            'name' => 'Rapid-fire Entries',
            'value' => $rapidTradeCount . ' entries within 10 minutes',
            'points' => round($rapidTradePoints, 2),
            'max_points' => 15,
            'status' => $rapidTradePoints >= 9 ? 'High' : ($rapidTradePoints > 0 ? 'Watch' : 'Clear'),
            'tone' => $rapidTradePoints >= 9 ? 'danger' : ($rapidTradePoints > 0 ? 'warning' : 'success'),
            'description' => 'Detects repeated fast entries that may be impulsive when combined with other risk markers.',
        ]);

        $highLayeringCount = $layeringExposureEvents
            ->filter(fn (array $event): bool => in_array((string) ($event['level'] ?? 'low'), ['high', 'extreme'], true))
            ->count();
        $layeringPressurePoints = min(20, ($highLayeringCount * 10) + max(0, $layeringExposureEvents->count() - $highLayeringCount) * 5);
        $checks->push([
            'name' => 'Layered Exposure',
            'value' => $layeringExposureEvents->count() . ' layer setup(s)',
            'points' => round($layeringPressurePoints, 2),
            'max_points' => 20,
            'status' => $highLayeringCount > 0 ? 'High' : ($layeringExposureEvents->isNotEmpty() ? 'Watch' : 'Clear'),
            'tone' => $highLayeringCount > 0 ? 'danger' : ($layeringExposureEvents->isNotEmpty() ? 'warning' : 'success'),
            'description' => 'Flags same-pair, same-direction layering while previous positions are still open. The tier rises when layer count or active lot exposure grows.',
        ]);

        $concentrationPoints = ($bestDayRule['total_generated_profit'] ?? 0) > 0 && ($bestDayRule['score_percent'] ?? 0) > self::BEST_DAY_LIMIT_PERCENT
            ? min(10, (($bestDayRule['score_percent'] - self::BEST_DAY_LIMIT_PERCENT) / 25) * 10)
            : 0;
        $checks->push([
            'name' => 'Profit Concentration',
            'value' => number_format((float) ($bestDayRule['score_percent'] ?? 0), 2) . '% best-day concentration',
            'points' => round($concentrationPoints, 2),
            'max_points' => 10,
            'status' => $concentrationPoints >= 6 ? 'High' : ($concentrationPoints > 0 ? 'Watch' : 'Clear'),
            'tone' => $concentrationPoints >= 6 ? 'danger' : ($concentrationPoints > 0 ? 'warning' : 'success'),
            'description' => 'One oversized winning day carrying the account can indicate inconsistent risk-taking.',
        ]);

        $highDurationDeviationCount = $durationDeviationEvents
            ->filter(fn (array $event): bool => (string) ($event['level'] ?? 'medium') === 'high')
            ->count();
        $durationPoints = min(15, ($highDurationDeviationCount * 8) + max(0, $durationDeviationEvents->count() - $highDurationDeviationCount) * 5);
        $checks->push([
            'name' => 'Duration Deviation',
            'value' => $durationDeviationEvents->count() . ' trade(s), account median ' . $durationProfile['median_label'],
            'points' => round($durationPoints, 2),
            'max_points' => 15,
            'status' => $highDurationDeviationCount > 0 ? 'High' : ($durationDeviationEvents->isNotEmpty() ? 'Watch' : 'Clear'),
            'tone' => $highDurationDeviationCount > 0 ? 'danger' : ($durationDeviationEvents->isNotEmpty() ? 'warning' : 'success'),
            'description' => 'Compares each trade duration with the account median. A much shorter hold can indicate speculative behavior when the account normally holds for hours.',
        ]);

        $score = round(min(100, (float) $checks->sum('points')), 2);
        $scoreTierRank = match (true) {
            $tradeCount === 0 => 0,
            $score >= 70 => 3,
            $score >= 45 => 2,
            $score >= 20 => 1,
            default => 0,
        };
        $tier = $this->behaviorTier(max($scoreTierRank, (int) $gamblingEvents->max('tier_rank')));
        $detected = $tier['tier_rank'] > 0;
        $status = match (true) {
            $tradeCount === 0 => 'No Data',
            $detected => $this->behaviorTierStatus('Gambling', $tier),
            default => 'Controlled',
        };
        $tone = $tradeCount === 0 ? 'secondary' : ($detected ? $tier['tier_tone'] : 'success');

        return [
            'detected' => $detected,
            'status' => $status,
            'tone' => $tone,
            'score' => $score,
            'tier' => $tier['tier'],
            'tier_rank' => $tier['tier_rank'],
            'tier_label' => $tier['tier_label'],
            'tier_short' => $tier['tier_short'],
            'tier_tone' => $tradeCount === 0 ? 'secondary' : $tier['tier_tone'],
            'tier_description' => $this->behaviorProfileTierDescription('gambling', $tier, $gamblingEvents),
            'trade_count' => $tradeCount,
            'active_days' => $activeDays,
            'average_trades_per_day' => $averageTradesPerDay,
            'max_trades_per_day' => $maxTradesPerDay,
            'overtrading_days' => $overtradingDays,
            'rapid_trade_count' => $rapidTradeCount,
            'lot_spike_count' => $lotSpikeCount,
            'oversized_loss_count' => $oversizedLosses->count(),
            'oversized_losses' => $oversizedLosses->take(10)->values(),
            'lot_deviation_event_count' => $lotDeviationEvents->count(),
            'lot_deviation_events' => $lotDeviationEvents->take(10)->values(),
            'duration_deviation_event_count' => $durationDeviationEvents->count(),
            'duration_deviation_events' => $durationDeviationEvents->take(10)->values(),
            'layering_exposure_event_count' => $layeringExposureEvents->count(),
            'layering_exposure_events' => $layeringExposureEvents->take(10)->values(),
            'margin_pressure_event_count' => $marginPressureEvents->count(),
            'max_margin_percent' => round($maxMarginPercent, 2),
            'margin_pressure_events' => $marginPressureEvents->take(10)->values(),
            'examples' => $gamblingExamples,
            'checks' => $checks,
            'trade_map' => $gamblingTradeMap,
        ];
    }

    private function normalizedTrades(Collection $trades): Collection
    {
        return $trades->values()->map(function ($trade, int $index): array {
            $id = $this->value($trade, 'id', $index + 1);
            $source = (string) $this->value($trade, 'source', 'journal');
            $openedAt = $this->tradeOpenedAt($trade);
            $closedAt = $this->tradeClosedAt($trade);

            return [
                'key' => $source . ':' . $id,
                'id' => $id,
                'source' => $source,
                'user_id' => $this->value($trade, 'user_id'),
                'pair' => strtoupper((string) $this->value($trade, 'pair', 'N/A')),
                'direction' => (int) $this->value($trade, 'direction', 0),
                'entry_price' => round((float) $this->value($trade, 'entry_price', 0), 6),
                'exit_price' => round((float) $this->value($trade, 'exit_price', 0), 6),
                'lot_size' => round((float) $this->value($trade, 'lot_size', 0), 4),
                'profit_loss' => round((float) $this->value($trade, 'profit_loss', 0), 2),
                'pips' => round((float) $this->value($trade, 'pips', 0), 2),
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
            ];
        });
    }

    private function behaviorPenaltyBehavior(string $label, array $profile): array
    {
        $tier = $this->behaviorTier((int) ($profile['tier_rank'] ?? 0));

        return [
            'label' => $label,
            'status' => (string) ($profile['status'] ?? $this->behaviorTierStatus(str_replace(' Behaviour', '', $label), $tier)),
            'score' => round((float) ($profile['score'] ?? 0), 2),
            'tier' => $tier['tier'],
            'tier_rank' => $tier['tier_rank'],
            'tier_label' => $tier['tier_label'],
            'tier_short' => $tier['tier_short'],
            'tier_tone' => $tier['tier_tone'],
            'tier_description' => (string) ($profile['tier_description'] ?? 'No tier method is available yet.'),
            'penalty_percent' => $this->behaviorPenaltyPercent($tier['tier_rank']),
        ];
    }

    private function behaviorPenaltyPercent(int $tierRank): float
    {
        return match (true) {
            $tierRank >= 3 => 10.0,
            $tierRank >= 2 => 5.0,
            default => 0.0,
        };
    }

    private function behaviorPenaltySummary(float $percent, string $triggerLabel): string
    {
        if ($percent >= 10) {
            return 'High revenge or gambling tier reached. The total score is reduced by 10% from the base score. Trigger: ' . $triggerLabel . '.';
        }

        if ($percent >= 5) {
            return 'Medium revenge or gambling tier reached. The total score is reduced by 5% from the base score. Trigger: ' . $triggerLabel . '.';
        }

        return 'Clear and low revenge/gambling tiers do not reduce the trading journal score.';
    }

    private function periodBehaviorTrades(Collection $normalizedTrades, Carbon $start, Carbon $end): Collection
    {
        return $normalizedTrades
            ->filter(fn (array $trade): bool => ($trade['behavior_at'] ?? null) instanceof Carbon
                && $trade['behavior_at']->gte($start)
                && $trade['behavior_at']->lte($end))
            ->map(function (array $trade): array {
                unset($trade['behavior_at']);

                return $trade;
            })
            ->values();
    }

    private function periodLabel(Carbon $start, Carbon $end): string
    {
        return $start->format('M j') . ' - ' . $end->format('M j, Y');
    }

    private function behaviorTrendMetric(
        string $label,
        array $currentProfile,
        array $previousProfile,
        string $scorePath,
        string $statusPath,
        ?string $tierPath,
        int $currentTradeCount,
        int $previousTradeCount
    ): array {
        $currentScore = round((float) data_get($currentProfile, $scorePath, 0), 2);
        $previousScore = round((float) data_get($previousProfile, $scorePath, 0), 2);
        $change = round($currentScore - $previousScore, 2);
        $hasCurrent = $currentTradeCount > 0;
        $hasPrevious = $previousTradeCount > 0;

        [$trendLabel, $trendTone, $direction] = match (true) {
            ! $hasCurrent && ! $hasPrevious => ['No Data', 'secondary', 'No data'],
            $hasCurrent && ! $hasPrevious => ['Baseline Week', 'primary', 'Baseline'],
            ! $hasCurrent && $hasPrevious => ['Improved', 'success', 'Decreased'],
            abs($change) <= 0.01 => ['No Change', 'secondary', 'No change'],
            $change < 0 => ['Improved', 'success', 'Decreased'],
            default => ['Needs Attention', 'danger', 'Increased'],
        };

        $changeLabel = ($change > 0 ? '+' : '') . number_format($change, 2) . ' pts';
        $changeAbsLabel = number_format(abs($change), 2) . ' pts';
        $changePercent = $previousScore > 0 ? round(($change / $previousScore) * 100, 2) : null;
        $currentTier = $tierPath ? (string) data_get($currentProfile, $tierPath . '.tier_label', 'N/A') : (string) data_get($currentProfile, 'style_label', 'N/A');
        $previousTier = $tierPath ? (string) data_get($previousProfile, $tierPath . '.tier_label', 'N/A') : (string) data_get($previousProfile, 'style_label', 'N/A');

        return [
            'label' => $label,
            'current_score' => $currentScore,
            'previous_score' => $previousScore,
            'change' => $change,
            'change_label' => $changeLabel,
            'change_abs_label' => $changeAbsLabel,
            'change_percent' => $changePercent,
            'direction' => $direction,
            'trend_label' => $trendLabel,
            'trend_tone' => $trendTone,
            'current_status' => (string) data_get($currentProfile, $statusPath, 'N/A'),
            'previous_status' => (string) data_get($previousProfile, $statusPath, 'N/A'),
            'current_tier' => $currentTier,
            'previous_tier' => $previousTier,
            'current_trade_count' => $currentTradeCount,
            'previous_trade_count' => $previousTradeCount,
            'summary' => $this->behaviorTrendSummary($label, $direction, $changeAbsLabel, $hasPrevious, $hasCurrent),
        ];
    }

    private function behaviorTrendSummary(string $label, string $direction, string $changeLabel, bool $hasPrevious, bool $hasCurrent): string
    {
        if (! $hasPrevious && ! $hasCurrent) {
            return 'No trades were found in either weekly window.';
        }

        if ($hasCurrent && ! $hasPrevious) {
            return 'This is the first weekly baseline for ' . strtolower($label) . '.';
        }

        return $label . ' ' . strtolower($direction) . ' by ' . $changeLabel . ' compared with the previous week.';
    }

    private function behaviorSummary(string $styleLabel, float $riskScore, array $revenge, array $gambling, array $layering, Collection $styleTags): string
    {
        if ($styleLabel === 'No Trading Style Yet') {
            return 'No trading behavior profile is available until journal records are added.';
        }

        if ($riskScore < 20) {
            return 'The trader currently looks controlled: no strong revenge trading or gambling-style risk markers were detected in the selected records.';
        }

        $markers = $styleTags->isNotEmpty()
            ? $styleTags->implode(', ')
            : 'mixed risk markers';

        return 'Detected style: ' . $styleLabel . '. Main markers: ' . $markers . '. Revenge '
            . ($revenge['tier_label'] ?? 'Clear') . ' tier score '
            . number_format((float) $revenge['score'], 2) . '/100, gambling '
            . ($gambling['tier_label'] ?? 'Clear') . ' tier score '
            . number_format((float) $gambling['score'], 2) . '/100, layering score '
            . number_format((float) $layering['score'], 2) . '/100.';
    }

    private function layeringChecks(float $score, int $eventCount, int $overlapCount, int $fastLayerCount, int $lotIncreaseCount, int $adverseLayerCount, Collection $events): Collection
    {
        $maxActiveLayers = $events->isNotEmpty() ? (int) $events->max('active_layers') : 0;

        return collect([
            [
                'name' => 'Same-direction Layering',
                'value' => $eventCount . ' event(s)',
                'status' => $eventCount > 0 ? 'Detected' : 'Clear',
                'tone' => $eventCount > 0 ? 'warning' : 'success',
                'description' => 'Looks for multiple same-pair, same-direction orders opened close together or while exposure overlaps.',
            ],
            [
                'name' => 'Overlapping Exposure',
                'value' => $overlapCount . ' overlap(s)',
                'status' => $overlapCount > 0 ? 'Detected' : 'Clear',
                'tone' => $overlapCount > 0 ? 'warning' : 'success',
                'description' => 'Flags when an additional same-direction trade is opened before the earlier trade is closed.',
            ],
            [
                'name' => 'Fast Layer Additions',
                'value' => $fastLayerCount . ' fast layer(s)',
                'status' => $fastLayerCount > 0 ? 'Detected' : 'Clear',
                'tone' => $fastLayerCount > 0 ? 'warning' : 'success',
                'description' => 'Adds weight when the next same-direction order is opened within 60 minutes, especially within 15 minutes.',
            ],
            [
                'name' => 'Lot Increase On Layer',
                'value' => $lotIncreaseCount . ' lot increase event(s)',
                'status' => $lotIncreaseCount > 0 ? 'Detected' : 'Clear',
                'tone' => $lotIncreaseCount > 0 ? 'danger' : 'success',
                'description' => 'Flags cases where the added layer is 50% larger or more than the previous layer.',
            ],
            [
                'name' => 'Adverse Price Layering',
                'value' => $adverseLayerCount . ' adverse layer(s)',
                'status' => $adverseLayerCount > 0 ? 'Detected' : 'Clear',
                'tone' => $adverseLayerCount > 0 ? 'danger' : 'success',
                'description' => 'For buys, this means adding lower; for sells, adding higher. This can indicate averaging into a losing move.',
            ],
            [
                'name' => 'Maximum Active Layers',
                'value' => $maxActiveLayers . ' active layer(s)',
                'status' => $maxActiveLayers >= 3 ? 'High' : ($maxActiveLayers >= 2 ? 'Watch' : 'Clear'),
                'tone' => $maxActiveLayers >= 3 ? 'danger' : ($maxActiveLayers >= 2 ? 'warning' : 'success'),
                'description' => 'Shows the largest number of same-pair, same-direction trades active at the same time.',
            ],
            [
                'name' => 'Overall Layering Score',
                'value' => number_format($score, 2) . '/100',
                'status' => $score >= 70 ? 'High' : ($score >= 40 ? 'Watch' : ($events->isNotEmpty() ? 'Mild' : 'Clear')),
                'tone' => $score >= 70 ? 'danger' : ($score >= 40 ? 'warning' : ($events->isNotEmpty() ? 'primary' : 'success')),
                'description' => 'Weighted score from overlap, speed, lot increase, adverse price layering, and stacked exposure.',
            ],
        ]);
    }

    private function revengeChecks(float $score, int $eventCount, int $quickReentryCount, int $lotIncreaseCount, int $lossStreakReactionCount, Collection $events): Collection
    {
        return collect([
            [
                'name' => 'Post-loss Re-entry',
                'value' => $eventCount . ' event(s)',
                'status' => $eventCount > 0 ? 'Detected' : 'Clear',
                'tone' => $eventCount > 0 ? 'warning' : 'success',
                'description' => 'Looks for new trades opened within 3 hours after a losing trade.',
            ],
            [
                'name' => 'Fast Reaction',
                'value' => $quickReentryCount . ' fast re-entry event(s)',
                'status' => $quickReentryCount > 0 ? 'Detected' : 'Clear',
                'tone' => $quickReentryCount > 0 ? 'warning' : 'success',
                'description' => 'Flags re-entries within 60 minutes after a losing trade, with stronger weight inside 15 minutes.',
            ],
            [
                'name' => 'Lot Increase After Loss',
                'value' => $lotIncreaseCount . ' lot increase event(s)',
                'status' => $lotIncreaseCount > 0 ? 'Detected' : 'Clear',
                'tone' => $lotIncreaseCount > 0 ? 'danger' : 'success',
                'description' => 'Flags cases where the next trade increases lot size by 50% or more after a loss.',
            ],
            [
                'name' => 'Loss Streak Reaction',
                'value' => $lossStreakReactionCount . ' streak reaction(s)',
                'status' => $lossStreakReactionCount > 0 ? 'Detected' : 'Clear',
                'tone' => $lossStreakReactionCount > 0 ? 'danger' : 'success',
                'description' => 'Flags reactions after two or more consecutive losses.',
            ],
            [
                'name' => 'Overall Revenge Score',
                'value' => number_format($score, 2) . '/100',
                'status' => $score >= 70 ? 'High' : ($score >= 40 ? 'Watch' : ($events->isNotEmpty() ? 'Mild' : 'Clear')),
                'tone' => $score >= 70 ? 'danger' : ($score >= 40 ? 'warning' : ($events->isNotEmpty() ? 'primary' : 'success')),
                'description' => 'Weighted score from fast re-entry, lot increase, same-pair/direction flip, and loss-streak reactions.',
            ],
        ]);
    }

    private function rapidTradeCount(Collection $normalizedTrades): int
    {
        $count = 0;
        $previousByUser = [];

        foreach ($normalizedTrades as $trade) {
            $userKey = (string) ($trade['user_id'] ?? 'account');
            $openedAt = $trade['opened_at'] ?? null;

            if (! $openedAt instanceof Carbon) {
                continue;
            }

            $previous = $previousByUser[$userKey] ?? null;
            if ($previous instanceof Carbon && $previous->diffInMinutes($openedAt) <= 10) {
                $count++;
            }

            $previousByUser[$userKey] = $openedAt;
        }

        return $count;
    }

    private function lotSpikeCount(Collection $normalizedTrades): int
    {
        $count = 0;
        $previousLotByUser = [];
        $recentLotsByUser = [];

        foreach ($normalizedTrades as $trade) {
            $userKey = (string) ($trade['user_id'] ?? 'account');
            $lotSize = (float) ($trade['lot_size'] ?? 0);
            $previousLot = $previousLotByUser[$userKey] ?? null;
            $recentLots = collect($recentLotsByUser[$userKey] ?? []);
            $baselineLot = $this->recentLotBaseline($recentLots);
            $returningToBaseline = $this->isReturningTowardLotBaseline($previousLot, $lotSize, $baselineLot);

            if (
                $previousLot
                && $previousLot > 0
                && $lotSize >= ($previousLot * 2)
                && ! $returningToBaseline
                && $baselineLot !== null
                && $lotSize >= ($baselineLot * 1.25)
            ) {
                $count++;
            }

            if ($lotSize > 0) {
                $previousLotByUser[$userKey] = $lotSize;
                $recentLotsByUser[$userKey] = array_slice(
                    array_merge($recentLotsByUser[$userKey] ?? [], [$lotSize]),
                    -12
                );
            }
        }

        return $count;
    }

    private function recentLotBaseline(Collection $recentLots): ?float
    {
        $lots = $recentLots
            ->filter(fn ($lot): bool => (float) $lot > 0)
            ->values();

        if ($lots->count() < 3) {
            return null;
        }

        return round($this->median($lots->take(-10)->values()), 4);
    }

    private function isReturningTowardLotBaseline(?float $previousLot, float $lotSize, ?float $baselineLot): bool
    {
        if (! $previousLot || $previousLot <= 0 || ! $baselineLot || $baselineLot <= 0 || $lotSize <= 0) {
            return false;
        }

        return $previousLot < ($baselineLot * 0.75)
            && $lotSize <= ($baselineLot * 1.15);
    }

    private function oversizedLosses(Collection $normalizedTrades, float $accountBalance): Collection
    {
        if ($accountBalance <= 0) {
            return collect();
        }

        return $normalizedTrades
            ->filter(fn (array $trade): bool => (float) $trade['profit_loss'] < 0)
            ->map(function (array $trade) use ($accountBalance): array {
                $lossAmount = abs((float) $trade['profit_loss']);

                return [
                    'trade_key' => $trade['key'],
                    'trade_label' => $this->tradeLabel($trade),
                    'pair' => $trade['pair'],
                    'loss_amount' => round($lossAmount, 2),
                    'loss_percent' => round(($lossAmount / $accountBalance) * 100, 2),
                    'lot_size' => $trade['lot_size'],
                    'closed_at' => $trade['closed_at'],
                ];
            })
            ->filter(fn (array $trade): bool => $trade['loss_percent'] > 3)
            ->sortByDesc('loss_percent')
            ->values();
    }

    private function lotAverageDeviationEvents(Collection $normalizedTrades): Collection
    {
        return $normalizedTrades
            ->groupBy(fn (array $trade): string => (string) ($trade['user_id'] ?? 'account'))
            ->flatMap(function (Collection $userTrades): Collection {
                $lotValues = $userTrades
                    ->pluck('lot_size')
                    ->map(fn ($lot): float => (float) $lot)
                    ->filter(fn (float $lot): bool => $lot > 0)
                    ->values();

                if ($lotValues->count() < 4) {
                    return collect();
                }

                $averageLot = round((float) $lotValues->avg(), 4);
                $medianLot = round($this->median($lotValues), 4);

                if ($averageLot <= 0 || $medianLot <= 0) {
                    return collect();
                }

                return $userTrades
                    ->map(function (array $trade) use ($averageLot, $medianLot): ?array {
                        $lotSize = (float) ($trade['lot_size'] ?? 0);

                        if ($lotSize <= 0) {
                            return null;
                        }

                        $averageRatio = round($lotSize / $averageLot, 2);
                        $medianRatio = round($lotSize / $medianLot, 2);
                        $deviationRatio = min($averageRatio, $medianRatio);

                        if ($deviationRatio < self::LOT_AVERAGE_DEVIATION_MEDIUM_RATIO) {
                            return null;
                        }

                        $level = $deviationRatio >= self::LOT_AVERAGE_DEVIATION_HIGH_RATIO ? 'high' : 'medium';
                        $evidence = 'Lot size ' . number_format($lotSize, 4) . ' is '
                            . number_format($averageRatio, 2) . 'x the account average lot ('
                            . number_format($averageLot, 4) . ') and '
                            . number_format($medianRatio, 2) . 'x the median lot ('
                            . number_format($medianLot, 4) . ').';

                        return [
                            'trade_key' => $trade['key'],
                            'trade_label' => $this->tradeLabel($trade),
                            'pair' => $trade['pair'],
                            'reason' => $level === 'high' ? 'High lot deviation' : 'Account-average lot deviation',
                            'evidence' => $evidence,
                            'level' => $level,
                            'lot_size' => $lotSize,
                            'average_lot' => $averageLot,
                            'median_lot' => $medianLot,
                            'average_ratio' => $averageRatio,
                            'median_ratio' => $medianRatio,
                            'score' => $level === 'high' ? 22 : 14,
                            'opened_at' => $trade['opened_at'] ?? null,
                        ];
                    })
                    ->filter()
                    ->values();
            })
            ->sortByDesc('average_ratio')
            ->values();
    }

    private function durationDeviationEvents(Collection $normalizedTrades): Collection
    {
        return $normalizedTrades
            ->groupBy(fn (array $trade): string => (string) ($trade['user_id'] ?? 'account'))
            ->flatMap(function (Collection $userTrades): Collection {
                $durations = $userTrades
                    ->map(function (array $trade): ?int {
                        $openedAt = $trade['opened_at'] ?? null;
                        $closedAt = $trade['closed_at'] ?? null;

                        if (! $openedAt instanceof Carbon || ! $closedAt instanceof Carbon || $closedAt->lt($openedAt)) {
                            return null;
                        }

                        return max(0, $openedAt->diffInMinutes($closedAt));
                    })
                    ->filter(fn ($minutes): bool => $minutes !== null && (int) $minutes > 0)
                    ->values();

                if ($durations->count() < 4) {
                    return collect();
                }

                $medianMinutes = (int) round($this->median($durations));
                $averageMinutes = (int) round((float) $durations->avg());

                if ($medianMinutes < 60) {
                    return collect();
                }

                return $userTrades
                    ->map(function (array $trade) use ($medianMinutes, $averageMinutes): ?array {
                        $openedAt = $trade['opened_at'] ?? null;
                        $closedAt = $trade['closed_at'] ?? null;

                        if (! $openedAt instanceof Carbon || ! $closedAt instanceof Carbon || $closedAt->lt($openedAt)) {
                            return null;
                        }

                        $holdMinutes = max(0, $openedAt->diffInMinutes($closedAt));
                        $durationRatio = $medianMinutes > 0 ? round($holdMinutes / $medianMinutes, 2) : 0.0;

                        if ($durationRatio > self::DURATION_DEVIATION_MEDIUM_RATIO) {
                            return null;
                        }

                        $level = $durationRatio <= self::DURATION_DEVIATION_HIGH_RATIO ? 'high' : 'medium';
                        $evidence = 'Held for ' . $this->formatMinutes($holdMinutes)
                            . ' versus account median ' . $this->formatMinutes($medianMinutes)
                            . ' and average ' . $this->formatMinutes($averageMinutes) . '.';

                        return [
                            'trade_key' => $trade['key'],
                            'trade_label' => $this->tradeLabel($trade),
                            'pair' => $trade['pair'],
                            'reason' => $level === 'high' ? 'High duration deviation' : 'Duration deviation',
                            'evidence' => $evidence,
                            'level' => $level,
                            'hold_minutes' => $holdMinutes,
                            'median_minutes' => $medianMinutes,
                            'average_minutes' => $averageMinutes,
                            'duration_ratio' => $durationRatio,
                            'score' => $level === 'high' ? 18 : 12,
                            'opened_at' => $openedAt,
                        ];
                    })
                    ->filter()
                    ->values();
            })
            ->sortBy('duration_ratio')
            ->values();
    }

    private function layeringExposureEvents(Collection $normalizedTrades): Collection
    {
        if ($normalizedTrades->isEmpty()) {
            return collect();
        }

        $averageLotsByUser = $normalizedTrades
            ->groupBy(fn (array $trade): string => (string) ($trade['user_id'] ?? 'account'))
            ->map(function (Collection $userTrades): float {
                $lots = $userTrades
                    ->pluck('lot_size')
                    ->map(fn ($lot): float => (float) $lot)
                    ->filter(fn (float $lot): bool => $lot > 0);

                return $lots->isNotEmpty() ? round((float) $lots->avg(), 4) : 0.0;
            });

        $timedTrades = $normalizedTrades
            ->filter(fn (array $trade): bool => ($trade['opened_at'] ?? null) instanceof Carbon)
            ->sortBy(fn (array $trade): string => $trade['opened_at']->format('Y-m-d H:i:s') . ':' . $trade['key'])
            ->values();

        return $timedTrades
            ->map(function (array $trade) use ($timedTrades, $averageLotsByUser): ?array {
                $openedAt = $trade['opened_at'] ?? null;

                if (! $openedAt instanceof Carbon) {
                    return null;
                }

                $activeTrades = $this->activeSameDirectionTradesAt($timedTrades, $trade, $openedAt);
                $activeCount = $activeTrades->count();

                if ($activeCount < 2) {
                    return null;
                }

                $activeLot = round((float) $activeTrades->sum('lot_size'), 4);
                $userKey = (string) ($trade['user_id'] ?? 'account');
                $averageLot = (float) ($averageLotsByUser->get($userKey, 0) ?: 0);
                $activeLotRatio = $averageLot > 0 ? round($activeLot / $averageLot, 2) : null;
                $level = match (true) {
                    $activeCount >= 4 || ($activeLotRatio !== null && $activeLotRatio >= 4.0) => 'extreme',
                    $activeCount >= 3 || ($activeLotRatio !== null && $activeLotRatio >= 3.0) => 'high',
                    $activeLotRatio !== null && $activeLotRatio >= 2.0 => 'medium',
                    default => 'low',
                };

                $evidence = $activeCount . ' same-pair, same-direction trade(s) were active with '
                    . number_format($activeLot, 4) . ' total lot(s)'
                    . ($activeLotRatio !== null ? ', about ' . number_format($activeLotRatio, 2) . 'x the account average lot' : '')
                    . '.';

                return [
                    'trade_key' => $trade['key'],
                    'trade_label' => $this->tradeLabel($trade),
                    'pair' => $trade['pair'],
                    'reason' => $level === 'low' ? 'Layered entry' : 'Layered exposure',
                    'evidence' => $evidence,
                    'level' => $level,
                    'active_lot' => $activeLot,
                    'active_trade_count' => $activeCount,
                    'average_lot' => $averageLot > 0 ? $averageLot : null,
                    'active_lot_average_ratio' => $activeLotRatio,
                    'score' => match ($level) {
                        'extreme' => 26,
                        'high' => 22,
                        'medium' => 16,
                        default => 8,
                    },
                    'opened_at' => $openedAt,
                ];
            })
            ->filter()
            ->sortByDesc('active_trade_count')
            ->values();
    }

    private function marginPressureEvents(Collection $normalizedTrades, float $accountBalance): Collection
    {
        if ($accountBalance <= 0 || $normalizedTrades->isEmpty()) {
            return collect();
        }

        $timedTrades = $normalizedTrades
            ->filter(fn (array $trade): bool => ($trade['opened_at'] ?? null) instanceof Carbon && strtoupper((string) ($trade['pair'] ?? '')) === 'XAUUSD')
            ->sortBy(fn (array $trade): string => $trade['opened_at']->format('Y-m-d H:i:s') . ':' . $trade['key'])
            ->values();

        return $timedTrades
            ->map(function (array $trade) use ($timedTrades, $accountBalance): ?array {
                $openedAt = $trade['opened_at'] ?? null;

                if (! $openedAt instanceof Carbon) {
                    return null;
                }

                $activeTrades = $this->activeSameDirectionTradesAt($timedTrades, $trade, $openedAt);
                $activeLot = round((float) $activeTrades->sum('lot_size'), 4);
                $referencePrice = $this->marginReferencePrice($trade, $activeTrades);

                if ($activeLot <= 0 || $referencePrice <= 0) {
                    return null;
                }

                $requiredMargin = round(($activeLot * self::XAUUSD_CONTRACT_SIZE * $referencePrice) / self::XAUUSD_LEVERAGE, 2);
                $marginPercent = round(($requiredMargin / $accountBalance) * 100, 2);
                $level = match (true) {
                    $marginPercent >= self::MARGIN_PRESSURE_EXTREME_PERCENT => 'extreme',
                    $marginPercent >= self::MARGIN_PRESSURE_HIGH_PERCENT => 'high',
                    $marginPercent >= self::MARGIN_PRESSURE_MEDIUM_PERCENT => 'medium',
                    default => 'clear',
                };

                if ($level === 'clear') {
                    return null;
                }

                $evidence = 'XAUUSD active exposure of ' . number_format($activeLot, 4)
                    . ' lot(s) at price ' . number_format($referencePrice, 2)
                    . ' needs about ' . number_format($requiredMargin, 2)
                    . 'u margin at 1:' . number_format(self::XAUUSD_LEVERAGE, 0)
                    . ', equal to ' . number_format($marginPercent, 2) . '% of capital.';

                return [
                    'trade_key' => $trade['key'],
                    'trade_label' => $this->tradeLabel($trade),
                    'pair' => $trade['pair'],
                    'reason' => $level === 'medium' ? 'XAUUSD margin pressure' : 'High XAUUSD margin pressure',
                    'evidence' => $evidence,
                    'level' => $level,
                    'active_lot' => $activeLot,
                    'active_trade_count' => $activeTrades->count(),
                    'reference_price' => $referencePrice,
                    'required_margin' => $requiredMargin,
                    'margin_percent' => $marginPercent,
                    'leverage' => self::XAUUSD_LEVERAGE,
                    'contract_size' => self::XAUUSD_CONTRACT_SIZE,
                    'score' => match ($level) {
                        'extreme' => 30,
                        'high' => 26,
                        default => 18,
                    },
                    'opened_at' => $openedAt,
                ];
            })
            ->filter()
            ->sortByDesc('margin_percent')
            ->values();
    }

    private function activeSameDirectionTradesAt(Collection $timedTrades, array $trade, Carbon $openedAt): Collection
    {
        return $timedTrades
            ->filter(function (array $activeTrade) use ($trade, $openedAt): bool {
                $activeOpenedAt = $activeTrade['opened_at'] ?? null;
                $activeClosedAt = $activeTrade['closed_at'] ?? null;

                return $activeOpenedAt instanceof Carbon
                    && $activeOpenedAt->lte($openedAt)
                    && (! $activeClosedAt instanceof Carbon || $activeClosedAt->gt($openedAt))
                    && (string) ($activeTrade['user_id'] ?? 'account') === (string) ($trade['user_id'] ?? 'account')
                    && (string) ($activeTrade['pair'] ?? 'N/A') === (string) ($trade['pair'] ?? 'N/A')
                    && (int) ($activeTrade['direction'] ?? 0) === (int) ($trade['direction'] ?? 0);
            })
            ->values();
    }

    private function marginReferencePrice(array $trade, Collection $activeTrades): float
    {
        $tradeEntry = (float) ($trade['entry_price'] ?? 0);

        if ($tradeEntry > 0) {
            return round($tradeEntry, 6);
        }

        $activePrices = $activeTrades
            ->map(fn (array $activeTrade): float => (float) ($activeTrade['entry_price'] ?? 0))
            ->filter(fn (float $price): bool => $price > 0)
            ->values();

        if ($activePrices->isNotEmpty()) {
            return round((float) $activePrices->avg(), 6);
        }

        $tradeExit = (float) ($trade['exit_price'] ?? 0);

        return $tradeExit > 0 ? round($tradeExit, 6) : 0.0;
    }

    private function gamblingBehaviorEvents(
        Collection $normalizedTrades,
        Collection $dailyTradeCounts,
        Collection $oversizedLosses,
        Collection $lotDeviationEvents,
        Collection $durationDeviationEvents,
        Collection $layeringExposureEvents,
        Collection $marginPressureEvents
    ): Collection
    {
        $events = collect();

        $pushEvent = function (array $trade, string $reason, string $evidence, array $attributes = []) use (&$events): void {
            $key = (string) ($trade['key'] ?? '');

            if ($key === '') {
                return;
            }

            $current = $events->get($key, [
                'trade_key' => $key,
                'trade_label' => $this->tradeLabel($trade),
                'pair' => $trade['pair'] ?? 'N/A',
                'direction' => ((int) ($trade['direction'] ?? 0)) === 1 ? 'Buy' : (((int) ($trade['direction'] ?? 0)) === 2 ? 'Sell' : 'N/A'),
                'lot_size' => round((float) ($trade['lot_size'] ?? 0), 4),
                'profit_loss' => round((float) ($trade['profit_loss'] ?? 0), 2),
                'pips' => round((float) ($trade['pips'] ?? 0), 2),
                'opened_at' => $trade['opened_at'] ?? null,
                'closed_at' => $trade['closed_at'] ?? null,
                'reasons' => [],
                'evidence' => [],
                'score' => 0,
                'rapid_delay_minutes' => null,
                'previous_lot' => null,
                'baseline_lot' => null,
                'average_lot' => null,
                'median_lot' => null,
                'lot_ratio' => null,
                'baseline_ratio' => null,
                'average_lot_ratio' => null,
                'median_lot_ratio' => null,
                'lot_trend' => 'unknown',
                'returning_to_baseline' => false,
                'hold_minutes' => null,
                'duration_median_minutes' => null,
                'duration_average_minutes' => null,
                'duration_ratio' => null,
                'day_trade_count' => null,
                'loss_percent' => null,
                'active_lot' => null,
                'active_trade_count' => null,
                'layering_level' => null,
                'active_lot_average_ratio' => null,
                'margin_level' => null,
                'margin_percent' => null,
                'required_margin' => null,
            ]);

            $current['reasons'][] = $reason;
            $current['evidence'][] = $evidence;
            $current['score'] += (int) ($attributes['score'] ?? 8);

            foreach ($attributes as $attribute => $value) {
                if ($attribute === 'score') {
                    continue;
                }

                $current[$attribute] = $value;
            }

            $events->put($key, $current);
        };

        $tradesByKey = $normalizedTrades->keyBy('key');

        foreach ($oversizedLosses as $loss) {
            $trade = $tradesByKey->get($loss['trade_key'] ?? '');

            if ($trade) {
                $pushEvent(
                    $trade,
                    'Oversized loss',
                    'Loss was ' . number_format((float) ($loss['loss_amount'] ?? 0), 2) . 'u, about ' . number_format((float) ($loss['loss_percent'] ?? 0), 2) . '% of capital.',
                    [
                        'loss_percent' => (float) ($loss['loss_percent'] ?? 0),
                        'score' => 18,
                    ]
                );
            }
        }

        foreach ($lotDeviationEvents as $lotDeviation) {
            $trade = $tradesByKey->get($lotDeviation['trade_key'] ?? '');

            if ($trade) {
                $pushEvent(
                    $trade,
                    (string) ($lotDeviation['reason'] ?? 'Account-average lot deviation'),
                    (string) ($lotDeviation['evidence'] ?? 'Lot size is meaningfully above the account average.'),
                    [
                        'average_lot' => (float) ($lotDeviation['average_lot'] ?? 0),
                        'median_lot' => (float) ($lotDeviation['median_lot'] ?? 0),
                        'average_lot_ratio' => (float) ($lotDeviation['average_ratio'] ?? 0),
                        'median_lot_ratio' => (float) ($lotDeviation['median_ratio'] ?? 0),
                        'score' => (int) ($lotDeviation['score'] ?? 14),
                    ]
                );
            }
        }

        foreach ($durationDeviationEvents as $durationDeviation) {
            $trade = $tradesByKey->get($durationDeviation['trade_key'] ?? '');

            if ($trade) {
                $pushEvent(
                    $trade,
                    (string) ($durationDeviation['reason'] ?? 'Duration deviation'),
                    (string) ($durationDeviation['evidence'] ?? 'Trade duration is much shorter than the account norm.'),
                    [
                        'hold_minutes' => (int) ($durationDeviation['hold_minutes'] ?? 0),
                        'duration_median_minutes' => (int) ($durationDeviation['median_minutes'] ?? 0),
                        'duration_average_minutes' => (int) ($durationDeviation['average_minutes'] ?? 0),
                        'duration_ratio' => (float) ($durationDeviation['duration_ratio'] ?? 0),
                        'score' => (int) ($durationDeviation['score'] ?? 12),
                    ]
                );
            }
        }

        foreach ($layeringExposureEvents as $layeringExposure) {
            $trade = $tradesByKey->get($layeringExposure['trade_key'] ?? '');

            if ($trade) {
                $pushEvent(
                    $trade,
                    (string) ($layeringExposure['reason'] ?? 'Layered exposure'),
                    (string) ($layeringExposure['evidence'] ?? 'Same-direction exposure was layered while prior positions were still open.'),
                    [
                        'layering_level' => $layeringExposure['level'] ?? null,
                        'active_lot' => (float) ($layeringExposure['active_lot'] ?? 0),
                        'active_trade_count' => (int) ($layeringExposure['active_trade_count'] ?? 1),
                        'average_lot' => (float) ($layeringExposure['average_lot'] ?? 0),
                        'active_lot_average_ratio' => $layeringExposure['active_lot_average_ratio'] ?? null,
                        'score' => (int) ($layeringExposure['score'] ?? 8),
                    ]
                );
            }
        }

        foreach ($marginPressureEvents as $marginPressure) {
            $trade = $tradesByKey->get($marginPressure['trade_key'] ?? '');

            if ($trade) {
                $pushEvent(
                    $trade,
                    (string) ($marginPressure['reason'] ?? 'XAUUSD margin pressure'),
                    (string) ($marginPressure['evidence'] ?? 'Active XAUUSD exposure uses a large share of available margin.'),
                    [
                        'margin_level' => $marginPressure['level'] ?? null,
                        'margin_percent' => (float) ($marginPressure['margin_percent'] ?? 0),
                        'required_margin' => (float) ($marginPressure['required_margin'] ?? 0),
                        'active_lot' => (float) ($marginPressure['active_lot'] ?? 0),
                        'active_trade_count' => (int) ($marginPressure['active_trade_count'] ?? 1),
                        'score' => (int) ($marginPressure['score'] ?? 18),
                    ]
                );
            }
        }

        $previousTradeByUser = [];
        $recentLotsByUser = [];
        foreach ($normalizedTrades as $trade) {
            $userKey = (string) ($trade['user_id'] ?? 'account');
            $lotSize = (float) ($trade['lot_size'] ?? 0);
            $openedAt = $trade['opened_at'] ?? null;
            $closedAt = $trade['closed_at'] ?? null;
            $previousTrade = $previousTradeByUser[$userKey] ?? null;
            $previousLot = is_array($previousTrade) ? (float) ($previousTrade['lot_size'] ?? 0) : null;
            $previousOpenedAt = is_array($previousTrade) ? ($previousTrade['opened_at'] ?? null) : null;
            $recentLots = collect($recentLotsByUser[$userKey] ?? []);
            $baselineLot = $this->recentLotBaseline($recentLots);
            $lotRatio = $previousLot && $previousLot > 0
                ? round($lotSize / $previousLot, 2)
                : null;
            $baselineRatio = $baselineLot && $baselineLot > 0
                ? round($lotSize / $baselineLot, 2)
                : null;
            $lotTrend = $this->lotTrend($lotRatio);
            $delayMinutes = ($openedAt instanceof Carbon && $previousOpenedAt instanceof Carbon)
                ? $previousOpenedAt->diffInMinutes($openedAt)
                : null;
            $holdMinutes = ($openedAt instanceof Carbon && $closedAt instanceof Carbon)
                ? max(0, $openedAt->diffInMinutes($closedAt))
                : null;
            $rapidEntry = $delayMinutes !== null && $delayMinutes <= 10;
            $shortHold = $holdMinutes !== null && $holdMinutes <= 5;
            $returningToBaseline = $this->isReturningTowardLotBaseline($previousLot, $lotSize, $baselineLot);
            $contextualLotEscalation = $previousLot && $previousLot > 0
                && $lotSize >= ($previousLot * 1.5)
                && ! $returningToBaseline
                && (
                    ($baselineLot !== null && $lotSize >= ($baselineLot * 1.25))
                    || ($baselineLot === null && ($rapidEntry || $shortHold))
                );

            if ($contextualLotEscalation) {
                $baselineEvidence = $baselineLot
                    ? ' Recent baseline is about ' . number_format($baselineLot, 4) . ' lots.'
                    : '';
                $pushEvent(
                    $trade,
                    $lotSize >= ($previousLot * 2) ? 'Lot-size spike' : 'Lot-size increase',
                    'Lot increased from ' . number_format((float) $previousLot, 4) . ' to ' . number_format($lotSize, 4) . ' lots.' . $baselineEvidence,
                    [
                        'previous_lot' => $previousLot,
                        'baseline_lot' => $baselineLot,
                        'lot_ratio' => $lotRatio,
                        'baseline_ratio' => $baselineRatio,
                        'lot_trend' => $lotTrend,
                        'score' => $lotRatio >= 2 ? 22 : 14,
                    ]
                );
            }

            if ($delayMinutes !== null && $delayMinutes <= 10) {
                $pushEvent(
                    $trade,
                    'Rapid-fire entry',
                    'Opened ' . $this->formatMinutes($delayMinutes) . ' after the previous trade.',
                    [
                        'rapid_delay_minutes' => $delayMinutes,
                        'previous_lot' => $previousLot,
                        'baseline_lot' => $baselineLot,
                        'lot_ratio' => $lotRatio,
                        'baseline_ratio' => $baselineRatio,
                        'lot_trend' => $lotTrend,
                        'returning_to_baseline' => $returningToBaseline,
                        'score' => 10,
                    ]
                );
            }

            if ($holdMinutes !== null && $holdMinutes <= 5 && ($delayMinutes !== null && $delayMinutes <= 10 || ($lotRatio ?? 0) >= 1.5)) {
                $pushEvent(
                    $trade,
                    'Ultra-short hold',
                    'Trade was held for ' . $this->formatMinutes($holdMinutes) . '.',
                    [
                        'hold_minutes' => $holdMinutes,
                        'score' => 8,
                    ]
                );
            }

            if ($openedAt instanceof Carbon) {
                $previousTradeByUser[$userKey] = $trade;
                if ($lotSize > 0) {
                    $recentLotsByUser[$userKey] = array_slice(
                        array_merge($recentLotsByUser[$userKey] ?? [], [$lotSize]),
                        -12
                    );
                }
            }
        }

        $overtradingDays = $dailyTradeCounts
            ->filter(fn (int $count): bool => $count >= 8)
            ->sortDesc()
            ->keys();

        foreach ($overtradingDays as $day) {
            $dayTradeCount = (int) $dailyTradeCounts->get($day, 0);

            $normalizedTrades
                ->filter(fn (array $trade): bool => ($trade['opened_at'] ?? null) instanceof Carbon && $trade['opened_at']->toDateString() === $day)
                ->sortBy(fn (array $trade): string => $trade['opened_at']->format('Y-m-d H:i:s') . ':' . $trade['key'])
                ->each(function (array $trade) use ($pushEvent, $dayTradeCount, $day): void {
                    $pushEvent(
                        $trade,
                        'Overtrading burst',
                        $dayTradeCount . ' trades were opened on ' . $day . '.',
                        [
                            'day_trade_count' => $dayTradeCount,
                            'score' => 9,
                        ]
                    );
                });
        }

        return $events
            ->map(function (array $event): array {
                $event['reasons'] = collect($event['reasons'])->unique()->values()->all();
                $event['evidence'] = collect($event['evidence'])->unique()->values()->all();

                $tier = $this->gamblingEventTier($event);

                return array_merge($event, [
                    'reason' => collect($event['reasons'])->implode(', '),
                    'evidence_label' => collect($event['evidence'])->implode(' '),
                    'tier' => $tier['tier'],
                    'tier_rank' => $tier['tier_rank'],
                    'tier_label' => $tier['tier_label'],
                    'tier_short' => $tier['tier_short'],
                    'tier_tone' => $tier['tier_tone'],
                    'tier_description' => $tier['description'],
                    'score' => min(100, (int) $event['score']),
                ]);
            })
            ->filter(fn (array $event): bool => (int) ($event['tier_rank'] ?? 0) > 0)
            ->values();
    }

    private function behaviorTier(int $rank): array
    {
        return match (max(0, min(3, $rank))) {
            3 => [
                'tier' => 'high',
                'tier_rank' => 3,
                'tier_label' => 'High',
                'tier_short' => 'H',
                'tier_tone' => 'danger',
            ],
            2 => [
                'tier' => 'medium',
                'tier_rank' => 2,
                'tier_label' => 'Medium',
                'tier_short' => 'M',
                'tier_tone' => 'warning',
            ],
            1 => [
                'tier' => 'low',
                'tier_rank' => 1,
                'tier_label' => 'Low',
                'tier_short' => 'L',
                'tier_tone' => 'primary',
            ],
            default => [
                'tier' => 'clear',
                'tier_rank' => 0,
                'tier_label' => 'Clear',
                'tier_short' => '-',
                'tier_tone' => 'success',
            ],
        };
    }

    private function behaviorTierStatus(string $behavior, array $tier): string
    {
        return ((int) ($tier['tier_rank'] ?? 0)) > 0
            ? ($tier['tier_label'] ?? 'Low') . ' ' . $behavior . ' Tier'
            : 'Clear';
    }

    private function behaviorProfileTierDescription(string $behavior, array $tier, Collection $events): string
    {
        $rank = (int) ($tier['tier_rank'] ?? 0);

        if ($rank === 0) {
            return $behavior === 'gambling'
                ? 'No gambling-style tier is active. Trade pace, lot sizing, loss size, and holding time look controlled in the selected records.'
                : 'No revenge tier is active. The selected records do not show a strong post-loss reaction pattern.';
        }

        if ($behavior === 'gambling') {
            return match ($rank) {
                3 => 'High tier: active exposure uses high XAUUSD margin at 1:30, has aggressive layering, lot size is far above the account average, or aggressive sizing is stacked with rapid entries, short holding, oversized losses, or heavy overtrading.',
                2 => 'Medium tier: more than one gambling-style marker is present, XAUUSD margin is elevated, lot size is meaningfully above the account average, layering is present, or duration is much shorter than the account norm.',
                default => 'Low tier: a mild gambling-style marker is present, commonly rapid entry while lot size stays the same or is reduced.',
            };
        }

        return match ($rank) {
            3 => 'High tier: post-loss re-entry is paired with larger lot size, very fast reaction, loss streak pressure, same-pair re-entry, or direction flip.',
            2 => 'Medium tier: revenge markers are meaningful but not at the strongest level, commonly fast post-loss reaction with lot increase or same-pair pressure.',
            default => 'Low tier: the trader re-entered quickly after a loss, but lot sizing did not clearly escalate.',
        };
    }

    private function revengeEventTier(float $eventScore, int $delayMinutes, float $lotRatio, bool $samePair, bool $oppositeDirection, int $lossStreak): array
    {
        $rank = match (true) {
            ($delayMinutes <= 15 && $lotRatio >= 1.5) || $lotRatio >= 2.0 || ($lossStreak >= 2 && $delayMinutes <= 30 && $lotRatio > 1.0) => 3,
            $eventScore >= 45 || $lotRatio >= 1.5 || ($delayMinutes <= 15 && ($samePair || $oppositeDirection)) || $lossStreak >= 2 => 2,
            default => 1,
        };

        $tier = $this->behaviorTier($rank);
        $description = match ($rank) {
            3 => 'Post-loss reaction is urgent and aggressive, especially because lot size increased or the trade followed loss-streak pressure.',
            2 => 'Post-loss reaction is meaningful, but the evidence is not as stacked as the high tier.',
            default => 'Post-loss reaction is mild, usually a quick re-entry without clear lot-size escalation.',
        };

        return array_merge($tier, ['description' => $description]);
    }

    private function gamblingEventTier(array $event): array
    {
        $rapidDelayMinutes = $event['rapid_delay_minutes'] ?? null;
        $rapid = $rapidDelayMinutes !== null && (int) $rapidDelayMinutes <= 10;
        $lotRatio = ($event['lot_ratio'] ?? null) !== null ? (float) $event['lot_ratio'] : null;
        $baselineRatio = ($event['baseline_ratio'] ?? null) !== null ? (float) $event['baseline_ratio'] : null;
        $returningToBaseline = (bool) ($event['returning_to_baseline'] ?? false);
        $lotIncreased = $lotRatio !== null && $lotRatio > 1.05 && ! $returningToBaseline;
        $lotMeaningfullyIncreased = $lotRatio !== null
            && $lotRatio >= 1.5
            && ! $returningToBaseline
            && ($baselineRatio === null || $baselineRatio >= 1.25 || $rapid);
        $lotSpike = $lotRatio !== null
            && $lotRatio >= 2.0
            && ! $returningToBaseline
            && ($baselineRatio === null || $baselineRatio >= 1.25 || $rapid);
        $sameOrReducedLot = $lotRatio !== null && $lotRatio <= 1.05;
        $shortHold = ($event['hold_minutes'] ?? null) !== null && (int) $event['hold_minutes'] <= 5;
        $overtrading = ($event['day_trade_count'] ?? null) !== null && (int) $event['day_trade_count'] >= 8;
        $oversizedLoss = ($event['loss_percent'] ?? null) !== null && (float) $event['loss_percent'] > 3;
        $activeTradeCount = (int) ($event['active_trade_count'] ?? 1);
        $averageLotRatio = ($event['average_lot_ratio'] ?? null) !== null ? (float) $event['average_lot_ratio'] : null;
        $medianLotRatio = ($event['median_lot_ratio'] ?? null) !== null ? (float) $event['median_lot_ratio'] : null;
        $accountLotRatio = max($averageLotRatio ?? 0.0, $medianLotRatio ?? 0.0);
        $accountLotMediumDeviation = $accountLotRatio >= self::LOT_AVERAGE_DEVIATION_MEDIUM_RATIO;
        $accountLotHighDeviation = $accountLotRatio >= self::LOT_AVERAGE_DEVIATION_HIGH_RATIO;
        $durationRatio = ($event['duration_ratio'] ?? null) !== null ? (float) $event['duration_ratio'] : null;
        $durationMediumDeviation = $durationRatio !== null && $durationRatio > 0 && $durationRatio <= self::DURATION_DEVIATION_MEDIUM_RATIO;
        $durationHighDeviation = $durationRatio !== null && $durationRatio > 0 && $durationRatio <= self::DURATION_DEVIATION_HIGH_RATIO;
        $layeringLevel = (string) ($event['layering_level'] ?? 'clear');
        $layeredExposure = $activeTradeCount >= 2 || in_array($layeringLevel, ['low', 'medium', 'high', 'extreme'], true);
        $layeringMedium = in_array($layeringLevel, ['medium', 'high', 'extreme'], true);
        $layeringHigh = $activeTradeCount >= 3 || in_array($layeringLevel, ['high', 'extreme'], true);
        $marginLevel = (string) ($event['margin_level'] ?? 'clear');
        $marginPercent = ($event['margin_percent'] ?? null) !== null ? (float) $event['margin_percent'] : 0.0;
        $marginMedium = in_array($marginLevel, ['medium', 'high', 'extreme'], true)
            || $marginPercent >= self::MARGIN_PRESSURE_MEDIUM_PERCENT;
        $marginHigh = in_array($marginLevel, ['high', 'extreme'], true)
            || $marginPercent >= self::MARGIN_PRESSURE_HIGH_PERCENT;
        $reasonCount = count($event['reasons'] ?? []);
        $stackedMediumMarkers = $reasonCount >= 2
            && ! ($rapid && $sameOrReducedLot && ! $marginMedium && ! $layeringMedium && ! $accountLotMediumDeviation && ! $durationMediumDeviation && ! $oversizedLoss);

        $rank = match (true) {
            $marginHigh || $layeringHigh || $accountLotHighDeviation || ($durationHighDeviation && ($rapid || $accountLotMediumDeviation || $layeredExposure || $marginMedium)) || ($lotSpike && ($rapid || $shortHold || $overtrading || $oversizedLoss || ($baselineRatio !== null && $baselineRatio >= 1.75))) || ($rapid && $lotMeaningfullyIncreased && ($shortHold || $overtrading)) || ($oversizedLoss && ($rapid || $lotMeaningfullyIncreased)) => 3,
            $marginMedium || $layeringMedium || $accountLotMediumDeviation || $durationMediumDeviation || $lotMeaningfullyIncreased || ($rapid && $lotIncreased) || ($rapid && $shortHold) || ($overtrading && ($rapid || $shortHold)) || $oversizedLoss || $stackedMediumMarkers => 2,
            $layeredExposure || $rapid || $overtrading || $shortHold || $sameOrReducedLot => 1,
            default => 0,
        };

        $tier = $this->behaviorTier($rank);
        $description = match ($rank) {
            3 => 'High gambling tier: the trade uses high XAUUSD margin, has 3+ layers, lot size is far above the account average, or aggressive sizing is stacked with speed/short holding/oversized loss.',
            2 => 'Medium gambling tier: the trade has multiple variance markers, meaningful lot-size increase above the recent/account baseline, moderate XAUUSD margin pressure, layering, or a much shorter hold than the account norm.',
            1 => 'Low gambling tier: the main marker is mild, such as rapid entry with same/reduced lot size or a small layer without large exposure.',
            default => 'No gambling tier.',
        };

        return array_merge($tier, ['description' => $description]);
    }

    private function lotTrend(?float $lotRatio): string
    {
        if ($lotRatio === null || $lotRatio <= 0) {
            return 'unknown';
        }

        if ($lotRatio >= 1.5) {
            return 'increased';
        }

        if ($lotRatio > 1.05) {
            return 'slightly increased';
        }

        if ($lotRatio < 0.95) {
            return 'reduced';
        }

        return 'same';
    }

    private function activeSameDirectionLayerCount(Collection $pairTrades, Carbon $openedAt): int
    {
        return $pairTrades
            ->filter(function (array $trade) use ($openedAt): bool {
                $tradeOpenedAt = $trade['opened_at'] ?? null;
                $tradeClosedAt = $trade['closed_at'] ?? null;

                if (! $tradeOpenedAt instanceof Carbon || $tradeOpenedAt->gt($openedAt)) {
                    return false;
                }

                return ! ($tradeClosedAt instanceof Carbon) || $tradeClosedAt->gt($openedAt);
            })
            ->count();
    }

    private function layeringPriceRelationship(array $first, array $second): array
    {
        $firstEntry = (float) ($first['entry_price'] ?? 0);
        $secondEntry = (float) ($second['entry_price'] ?? 0);
        $direction = (int) ($second['direction'] ?? 0);

        if ($firstEntry <= 0 || $secondEntry <= 0 || ! in_array($direction, [1, 2], true)) {
            return [
                'label' => 'No entry price evidence',
                'is_adverse' => false,
            ];
        }

        $adverse = ($direction === 1 && $secondEntry < $firstEntry)
            || ($direction === 2 && $secondEntry > $firstEntry);

        if ($adverse) {
            return [
                'label' => $direction === 1 ? 'Buy averaged lower' : 'Sell averaged higher',
                'is_adverse' => true,
            ];
        }

        return [
            'label' => $direction === 1 ? 'Buy added higher' : 'Sell added lower',
            'is_adverse' => false,
        ];
    }

    private function markLayeredTrade(Collection &$map, array $trade, array $counterpart, array $event): void
    {
        $current = $map->get($trade['key'], [
            'pair' => $trade['pair'],
            'direction' => $trade['direction'] === 1 ? 'Buy' : 'Sell',
            'events' => [],
            'counterparts' => [],
            'reasons' => [],
            'active_layers' => [],
        ]);

        $current['events'][] = $event;
        $current['counterparts'][] = [
            'key' => $counterpart['key'],
            'id' => $counterpart['id'],
            'label' => $this->tradeLabel($counterpart),
            'lot_size' => $counterpart['lot_size'],
            'opened_at' => $counterpart['opened_at'],
        ];

        foreach (($event['signals'] ?? collect()) as $signal) {
            $current['reasons'][] = $signal;
        }

        $current['active_layers'][] = (int) ($event['active_layers'] ?? 0);

        $map->put($trade['key'], $current);
    }

    private function markRevengeTrade(Collection &$map, array $trade, array $counterpart, array $event, string $role): void
    {
        $current = $map->get($trade['key'], [
            'pair' => $trade['pair'],
            'role' => $role,
            'events' => [],
            'counterparts' => [],
            'reasons' => [],
        ]);

        $current['events'][] = $event;
        $current['counterparts'][] = [
            'key' => $counterpart['key'],
            'id' => $counterpart['id'],
            'label' => $this->tradeLabel($counterpart),
            'lot_size' => $counterpart['lot_size'],
        ];

        foreach (($event['signals'] ?? collect()) as $signal) {
            $current['reasons'][] = $signal;
        }

        $map->put($trade['key'], $current);
    }

    private function markHedgedTrade(Collection &$map, array $trade, array $counterpart, int $overlapMinutes): void
    {
        $current = $map->get($trade['key'], [
            'pair' => $trade['pair'],
            'counterparts' => [],
            'overlaps' => [],
        ]);

        $current['counterparts'][] = [
            'key' => $counterpart['key'],
            'id' => $counterpart['id'],
            'label' => $this->tradeLabel($counterpart),
            'direction' => $counterpart['direction'] === 1 ? 'Buy' : 'Sell',
            'lot_size' => $counterpart['lot_size'],
        ];
        $current['overlaps'][] = $overlapMinutes;

        $map->put($trade['key'], $current);
    }

    private function tradeLabel(array $trade): string
    {
        $source = (string) ($trade['source'] ?? 'journal');
        $id = (string) ($trade['id'] ?? 'N/A');

        if ($source === 'journal') {
            return '#' . $id;
        }

        return $source . ' #' . $id;
    }

    private function bestDayGrade(float $scorePercent, float $totalGeneratedProfit): string
    {
        if ($totalGeneratedProfit <= 0) {
            return 'N/A';
        }

        return match (true) {
            $scorePercent <= 20 => 'A+',
            $scorePercent <= 30 => 'A',
            $scorePercent <= self::BEST_DAY_LIMIT_PERCENT => 'B',
            $scorePercent <= 50 => 'C',
            $scorePercent <= 65 => 'D',
            default => 'F',
        };
    }

    private function scoreGrade(float $score, int $count): string
    {
        if ($count === 0) {
            return 'N/A';
        }

        return match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B',
            $score >= 60 => 'C',
            $score >= 45 => 'D',
            default => 'F',
        };
    }

    private function modeLot(Collection $lots): array
    {
        if ($lots->isEmpty()) {
            return [
                'lot' => 0.0,
                'count' => 0,
            ];
        }

        $mode = $lots
            ->groupBy(fn (float $lot): string => number_format($lot, 4, '.', ''))
            ->map(fn (Collection $group, string $lot): array => [
                'lot' => (float) $lot,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->first();

        return $mode ?: [
            'lot' => 0.0,
            'count' => 0,
        ];
    }

    private function positionConsistencyDescription(float $score, int $count, float $anchorLot, float $anchorShare, float $nearAnchorShare): string
    {
        if ($count === 0) {
            return 'No lot-size data is available yet.';
        }

        if ($score >= 95 && $count >= 100 && $anchorShare >= 98) {
            return 'Elite position discipline: the trader has a deep sample and is consistently using the same core lot size.';
        }

        if ($score >= 85) {
            return 'Strong consistency: most trades stay around the main lot size of ' . number_format($anchorLot, 4) . ' lots, with ' . number_format($nearAnchorShare, 2) . '% near that anchor.';
        }

        if ($score >= 70) {
            return 'Good consistency: the trader has a clear main position size, with ' . number_format($anchorShare, 2) . '% exact anchor usage and some controlled variation.';
        }

        if ($score >= 55) {
            return 'Moderate consistency: position size has a pattern, but the trader still changes size often enough to reduce the score.';
        }

        return 'Low consistency: position size changes are wide or the sample is still too small to confirm a stable sizing habit.';
    }

    private function positionGradeRanking(): array
    {
        return [
            ['grade' => 'A+', 'range' => '90 - 100', 'description' => 'Elite consistency. Full marks are realistic when 100+ trades remain on the same core lot size.'],
            ['grade' => 'A', 'range' => '80 - 89.99', 'description' => 'Very consistent. Minor tactical lot changes only.'],
            ['grade' => 'B', 'range' => '70 - 79.99', 'description' => 'Good consistency with a visible main lot size.'],
            ['grade' => 'C', 'range' => '60 - 69.99', 'description' => 'Acceptable but position sizing is becoming more dynamic.'],
            ['grade' => 'D', 'range' => '45 - 59.99', 'description' => 'Weak consistency. Lot sizing varies often or sample size is limited.'],
            ['grade' => 'F', 'range' => '0 - 44.99', 'description' => 'Highly dynamic sizing or not enough evidence of a consistent lot-size habit.'],
        ];
    }

    private function tradeOpenedAt($trade): ?Carbon
    {
        return $this->parseDate(
            $this->value($trade, 'opened_at')
            ?? $this->value($trade, 'open_date')
            ?? $this->value($trade, 'trade_date')
            ?? $this->value($trade, 'created_at')
        );
    }

    private function tradeClosedAt($trade): ?Carbon
    {
        return $this->parseDate(
            $this->value($trade, 'closed_at')
            ?? $this->value($trade, 'close_date')
            ?? $this->value($trade, 'trade_date')
            ?? $this->value($trade, 'created_at')
        );
    }

    private function parseDate($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function value($trade, string $key, $default = null)
    {
        if (is_array($trade)) {
            return $trade[$key] ?? $default;
        }

        if (is_object($trade)) {
            return $trade->{$key} ?? $default;
        }

        return $default;
    }

    private function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();

        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $sorted[$middle];
        }

        return ((float) $sorted[$middle - 1] + (float) $sorted[$middle]) / 2;
    }

    private function standardDeviation(Collection $values): float
    {
        $count = $values->count();

        if ($count <= 1) {
            return 0.0;
        }

        $mean = (float) $values->avg();
        $variance = $values
            ->map(fn ($value): float => pow((float) $value - $mean, 2))
            ->sum() / $count;

        return sqrt($variance);
    }
}
