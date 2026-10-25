<?php

namespace App\Services;

class TraderLevelProfileService
{
    public const MIN_STANDARD_SAMPLE_TRADES = 10;

    public function profile(array $summary, array $scoreEvaluationProfile = [], array $traderStyleProfile = [], array $positionProfile = []): array
    {
        $totalTrades = (int) ($summary['total_trades'] ?? 0);
        $hasTrades = $totalTrades > 0;
        $isSmallSample = $hasTrades && $totalTrades < self::MIN_STANDARD_SAMPLE_TRADES;
        $sampleMode = ! $hasTrades ? 'no_data' : ($isSmallSample ? 'small_sample' : 'standard_sample');
        $sampleLabel = match ($sampleMode) {
            'small_sample' => 'Small Sample',
            'standard_sample' => 'Standard Sample',
            default => 'No Data',
        };

        $score = $this->scoreFromProfiles($summary, $scoreEvaluationProfile);
        $rawScore = (float) data_get($scoreEvaluationProfile, 'raw_score', $score);
        $behaviorRisk = (float) data_get($traderStyleProfile, 'final_score', data_get($traderStyleProfile, 'risk_score', 0));
        $drawdownPercent = (float) ($summary['max_drawdown_percent'] ?? 0);
        $maxDailyLossPercent = (float) ($summary['max_daily_loss_percent'] ?? 0);
        $netProfitLoss = (float) ($summary['net_profit_loss'] ?? 0);
        $positionScore = (float) data_get($positionProfile, 'score', 0);
        $controlledSmallSample = $this->controlledSmallSample($summary, $traderStyleProfile);
        $classificationScore = $isSmallSample && $controlledSmallSample
            ? max($score, $rawScore)
            : $score;
        $baseLevel = $hasTrades ? $this->levelFromScore($classificationScore) : 1;

        [$riskCapLevel, $riskCapLabel, $riskCapReason, $forcedLevel] = $this->riskCap(
            $traderStyleProfile,
            $behaviorRisk,
            $maxDailyLossPercent,
            $drawdownPercent,
            $sampleMode
        );

        [$sampleCapLevel, $sampleCapReason] = $this->sampleCap($totalTrades, $sampleMode);
        $performanceCapLevel = 10;
        $capDetails = collect();

        if ($riskCapReason) {
            $capDetails->push([
                'cap_level_number' => $riskCapLevel,
                'label' => 'Behavior / Risk Cap',
                'reason' => $riskCapReason,
            ]);
        }

        if ($sampleCapReason) {
            $capDetails->push([
                'cap_level_number' => $sampleCapLevel,
                'label' => 'Sample Size Cap',
                'reason' => $sampleCapReason,
            ]);
        }

        if ($netProfitLoss < 0 && $hasTrades) {
            $performanceCapLevel = min($performanceCapLevel, 5);
            $capDetails->push([
                'cap_level_number' => 5,
                'label' => 'Profitability Cap',
                'reason' => 'Negative net P/L caps profile progression until the trader recovers above break even.',
                'metric' => 'Net P/L',
                'value' => number_format($netProfitLoss, 2).'u',
            ]);
        }

        if ($drawdownPercent > 20) {
            $performanceCapLevel = min($performanceCapLevel, 4);
            $capDetails->push([
                'cap_level_number' => 4,
                'label' => 'Drawdown Cap',
                'reason' => 'Drawdown above 20% caps the profile at a high-risk level.',
                'metric' => 'Max Drawdown',
                'value' => number_format($drawdownPercent, 2).'%',
            ]);
        } elseif ($drawdownPercent > 10) {
            $performanceCapLevel = min($performanceCapLevel, 6);
            $capDetails->push([
                'cap_level_number' => 6,
                'label' => 'Drawdown Cap',
                'reason' => 'Drawdown above 10% keeps the trader under monitoring.',
                'metric' => 'Max Drawdown',
                'value' => number_format($drawdownPercent, 2).'%',
            ]);
        }

        if ($positionScore > 0 && $positionScore < 45 && $totalTrades >= 5) {
            $performanceCapLevel = min($performanceCapLevel, 6);
            $capDetails->push([
                'cap_level_number' => 6,
                'label' => 'Position Consistency Cap',
                'reason' => 'Position consistency is below 45/100, so the profile is capped at monitoring level even when the performance score is high.',
                'metric' => 'Position Consistency',
                'value' => number_format($positionScore, 2).'/100',
            ]);
        }

        if (! (bool) data_get($summary, 'best_day_rule.passed', true) && $netProfitLoss > 0 && ! $isSmallSample) {
            $performanceCapLevel = min($performanceCapLevel, 8);
            $capDetails->push([
                'cap_level_number' => 8,
                'label' => 'Best-Day Concentration Cap',
                'reason' => 'Best-day consistency is not passed, so professional levels remain capped until profit is spread across more winning days.',
                'metric' => 'Best Day Share',
                'value' => number_format((float) data_get($summary, 'best_day_rule.score_percent', 0), 2).'%',
            ]);
        }

        $capLevel = min($riskCapLevel, $sampleCapLevel, $performanceCapLevel);
        $capReasons = $capDetails
            ->pluck('reason')
            ->filter()
            ->values();

        $levelNumber = min($baseLevel, $capLevel);
        if ($forcedLevel !== null && $hasTrades) {
            $levelNumber = min($forcedLevel, $capLevel);
        }

        $ladder = collect($this->levelLadder());
        $levelRow = $ladder->firstWhere('level_number', $levelNumber) ?: $ladder->first();
        $baseLevelRow = $ladder->firstWhere('level_number', $baseLevel) ?: $levelRow;
        $label = $riskCapLabel ?: $this->labelForMode($levelRow, $sampleMode);
        $description = $riskCapLabel
            ? $riskCapReason
            : $this->descriptionForMode($levelRow, $sampleMode);
        $nextLevel = $ladder->firstWhere('level_number', min(10, $levelNumber + 1));
        $capActive = $hasTrades && ($levelNumber < $baseLevel || $forcedLevel !== null || $capLevel < 10);

        return [
            'level_number' => $levelNumber,
            'level' => 'L'.$levelNumber.' - '.$label,
            'label' => $label,
            'grade' => $this->gradeForLevel($levelNumber),
            'grade_description' => $this->gradeDescription($this->gradeForLevel($levelNumber)),
            'grade_scale' => $this->gradeScale(),
            'description' => $description,
            'tone' => $this->toneForLevel($levelNumber, $behaviorRisk, $riskCapLabel !== null),
            'score' => round($classificationScore, 2),
            'desk_score' => round($score, 2),
            'raw_score' => round($rawScore, 2),
            'base_level_number' => $baseLevel,
            'base_level_label' => $this->labelForMode($baseLevelRow, $sampleMode),
            'cap_level_number' => $capLevel,
            'cap_active' => $capActive,
            'cap_reason' => $capReasons->implode(' '),
            'cap_reasons' => $capReasons,
            'cap_details' => $capDetails->values(),
            'level_explanation' => $this->levelExplanation(
                $baseLevel,
                $this->labelForMode($baseLevelRow, $sampleMode),
                $levelNumber,
                $label,
                $classificationScore,
                $capActive,
                $capDetails
            ),
            'sample_mode' => $sampleMode,
            'sample_label' => $sampleLabel,
            'is_small_sample' => $isSmallSample,
            'minimum_standard_trades' => self::MIN_STANDARD_SAMPLE_TRADES,
            'sample_confidence' => $hasTrades
                ? round(min(100, ($totalTrades / self::MIN_STANDARD_SAMPLE_TRADES) * 100), 2)
                : 0.0,
            'next_level' => $nextLevel ? [
                'level_number' => $nextLevel['level_number'],
                'label' => $this->labelForMode($nextLevel, $sampleMode),
                'requirement' => $this->nextLevelRequirement($levelNumber, $classificationScore, $capActive, $capReasons, $sampleMode),
            ] : null,
            'drivers' => $this->drivers($summary, $traderStyleProfile, $positionProfile),
            'ladder' => $ladder,
            'reward' => round(max(0, $netProfitLoss) * 0.8, 2),
            'highest_reward' => round(max(0, (float) ($summary['largest_win'] ?? 0)) * 0.8, 2),
            'count' => (int) ($summary['profitable_days'] ?? 0),
        ];
    }

    private function scoreFromProfiles(array $summary, array $scoreEvaluationProfile): float
    {
        if (array_key_exists('score', $scoreEvaluationProfile)) {
            return max(0, min(100, (float) $scoreEvaluationProfile['score']));
        }

        $score = 0.0;
        $growth = (float) ($summary['growth_percent'] ?? 0);
        $drawdown = (float) ($summary['max_drawdown_percent'] ?? 0);
        $winRate = (float) ($summary['win_rate'] ?? 0);
        $profitFactor = $summary['profit_factor'] ?? null;

        $score += $growth >= 10 ? 30 : ($growth >= 5 ? 20 : ($growth > 0 ? 10 : 0));
        $score += $drawdown <= 5 ? 25 : ($drawdown <= 10 ? 15 : 0);
        $score += $winRate >= 60 ? 20 : ($winRate >= 50 ? 12 : 0);
        $score += is_numeric($profitFactor) && (float) $profitFactor >= 1.5 ? 15 : (is_numeric($profitFactor) && (float) $profitFactor >= 1 ? 8 : 0);
        $score += (bool) data_get($summary, 'best_day_rule.passed', false) ? 10 : 0;
        $score += (bool) data_get($summary, 'gross_profit_rule.passed', false) ? 5 : 0;

        return max(0, min(100, $score));
    }

    private function controlledSmallSample(array $summary, array $traderStyleProfile): bool
    {
        return (float) ($summary['net_profit_loss'] ?? 0) >= 0
            && (float) ($summary['max_drawdown_percent'] ?? 0) <= 5
            && (float) ($summary['max_daily_loss_percent'] ?? 0) < (float) config('trading_detection.daily_loss_limit_percent', 5.0)
            && (float) data_get($traderStyleProfile, 'final_score', data_get($traderStyleProfile, 'risk_score', 0)) < 40;
    }

    private function riskCap(array $traderStyleProfile, float $behaviorRisk, float $maxDailyLossPercent, float $drawdownPercent, string $sampleMode): array
    {
        $gamblingRank = (int) data_get($traderStyleProfile, 'gambling.tier_rank', 0);
        $revengeRank = (int) data_get($traderStyleProfile, 'revenge.tier_rank', 0);
        $gamblingScore = (float) data_get($traderStyleProfile, 'gambling.score', 0);
        $revengeScore = (float) data_get($traderStyleProfile, 'revenge.score', 0);
        $dailyLossLimit = (float) config('trading_detection.daily_loss_limit_percent', 5.0);

        if ($maxDailyLossPercent >= $dailyLossLimit || $behaviorRisk >= 85) {
            return [
                2,
                $sampleMode === 'small_sample' ? 'Small Sample Critical Risk Trader' : 'Critical Risk Trader',
                'Critical behavior or daily loss evidence is active, so higher profile levels are locked.',
                2,
            ];
        }

        if ($gamblingRank >= 3 || $gamblingScore >= 65) {
            return [
                3,
                $sampleMode === 'small_sample' ? 'Small Sample Gambling Trader' : 'Gambling Trader',
                'Gambling-style behavior is the strongest active marker, so the trader is classified as gambling-risk until the behavior score improves.',
                3,
            ];
        }

        if ($revengeRank >= 3 || $revengeScore >= 65) {
            return [
                4,
                $sampleMode === 'small_sample' ? 'Small Sample Revenge Trader' : 'Revenge Trader',
                'Revenge-trading behavior is the strongest active marker, so the trader is classified as revenge-risk until post-loss discipline improves.',
                4,
            ];
        }

        if ($behaviorRisk >= 65 || $drawdownPercent > 20) {
            return [
                4,
                $sampleMode === 'small_sample' ? 'Small Sample High-Risk Trader' : 'High-Risk Trader',
                'High behavior risk or severe drawdown keeps the trader in a high-risk profile.',
                null,
            ];
        }

        if ($gamblingRank >= 2 || $revengeRank >= 2 || $behaviorRisk >= 40) {
            return [
                6,
                null,
                'Medium behavior risk caps the trader at monitoring level until gambling or revenge markers reduce.',
                null,
            ];
        }

        return [10, null, null, null];
    }

    private function sampleCap(int $totalTrades, string $sampleMode): array
    {
        if ($sampleMode !== 'small_sample') {
            return [10, null];
        }

        if ($totalTrades <= 1) {
            return [7, 'Only one trade is available, so the small-sample profile cannot move above promising evidence yet.'];
        }

        if ($totalTrades <= 2) {
            return [8, 'Only two trades are available, so the small-sample profile cannot move above skilled evidence yet.'];
        }

        if ($totalTrades <= 4) {
            return [9, 'Fewer than five trades are available, so the profile can be professional only as a provisional candidate.'];
        }

        return [10, null];
    }

    private function levelFromScore(float $score): int
    {
        return max(1, min(10, (int) floor(max(0, min(100, $score)) / 10) + 1));
    }

    private function levelLadder(): array
    {
        return [
            [
                'level_number' => 1,
                'score_band' => '0 - 9.99',
                'small_sample_label' => 'No Small-Sample Profile',
                'standard_sample_label' => 'No Data / Failed Profile',
                'small_sample_description' => 'There is not enough reliable evidence to describe the trader yet.',
                'standard_sample_description' => 'The account does not show a usable trading edge in the selected records.',
                'tone' => 'secondary',
            ],
            [
                'level_number' => 2,
                'score_band' => '10 - 19.99',
                'small_sample_label' => 'Small Sample Novice Trader',
                'standard_sample_label' => 'Novice Trader',
                'small_sample_description' => 'Early evidence is basic and needs more trades before any skill claim is trusted.',
                'standard_sample_description' => 'The trader is still at beginner quality and needs stronger execution and risk control.',
                'tone' => 'danger',
            ],
            [
                'level_number' => 3,
                'score_band' => '20 - 29.99',
                'small_sample_label' => 'Small Sample Gambling Trader',
                'standard_sample_label' => 'Gambling Trader',
                'small_sample_description' => 'The small sample shows high-variance or gambling-style behavior that must be corrected first.',
                'standard_sample_description' => 'The standard sample shows gambling-style risk, high variance, or uncontrolled execution.',
                'tone' => 'danger',
            ],
            [
                'level_number' => 4,
                'score_band' => '30 - 39.99',
                'small_sample_label' => 'Small Sample Reactive Trader',
                'standard_sample_label' => 'High-Risk Reactive Trader',
                'small_sample_description' => 'The sample has some trade activity, but behavior or drawdown risk is too reactive.',
                'standard_sample_description' => 'The trader may have some skill, but risk behavior is not suitable for scaling.',
                'tone' => 'warning',
            ],
            [
                'level_number' => 5,
                'score_band' => '40 - 49.99',
                'small_sample_label' => 'Small Sample Developing Trader',
                'standard_sample_label' => 'Developing Trader',
                'small_sample_description' => 'The trader is developing, but the sample is too thin or uneven for confidence.',
                'standard_sample_description' => 'The trader has some foundation but still needs better consistency and profitability.',
                'tone' => 'warning',
            ],
            [
                'level_number' => 6,
                'score_band' => '50 - 59.99',
                'small_sample_label' => 'Controlled Small Sample Trader',
                'standard_sample_label' => 'Intermediate Trader',
                'small_sample_description' => 'The sample is controlled enough to keep observing under normal limits.',
                'standard_sample_description' => 'The trader is functional but still needs clearer edge and cleaner execution.',
                'tone' => 'primary',
            ],
            [
                'level_number' => 7,
                'score_band' => '60 - 69.99',
                'small_sample_label' => 'Promising Small Sample Trader',
                'standard_sample_label' => 'Consistent Trader',
                'small_sample_description' => 'The early profile is promising, with controlled risk and positive signs.',
                'standard_sample_description' => 'The trader is becoming consistent and can continue normal evaluation.',
                'tone' => 'primary',
            ],
            [
                'level_number' => 8,
                'score_band' => '70 - 79.99',
                'small_sample_label' => 'Skilled Small Sample Trader',
                'standard_sample_label' => 'Skilled Trader',
                'small_sample_description' => 'The small sample shows skilled behavior, but it is not a mature professional grade yet.',
                'standard_sample_description' => 'The trader shows strong skill and acceptable control across a standard sample.',
                'tone' => 'success',
            ],
            [
                'level_number' => 9,
                'score_band' => '80 - 89.99',
                'small_sample_label' => 'Provisional Professional Trader',
                'standard_sample_label' => 'Professional Trader',
                'small_sample_description' => 'The small sample reads professional, but the report keeps it provisional until sample depth improves.',
                'standard_sample_description' => 'The trader shows professional quality with strong performance and risk control.',
                'tone' => 'success',
            ],
            [
                'level_number' => 10,
                'score_band' => '90 - 100',
                'small_sample_label' => 'Small Sample Professional Candidate',
                'standard_sample_label' => 'Elite Professional Trader',
                'small_sample_description' => 'The current evidence is elite for a small sample and should be validated with more trades.',
                'standard_sample_description' => 'The trader has elite quality across score, behavior, drawdown, and consistency.',
                'tone' => 'success',
            ],
        ];
    }

    private function labelForMode(array $levelRow, string $sampleMode): string
    {
        if ($sampleMode === 'small_sample') {
            return $levelRow['small_sample_label'];
        }

        return $levelRow['standard_sample_label'];
    }

    private function descriptionForMode(array $levelRow, string $sampleMode): string
    {
        if ($sampleMode === 'small_sample') {
            return $levelRow['small_sample_description'];
        }

        return $levelRow['standard_sample_description'];
    }

    private function toneForLevel(int $levelNumber, float $behaviorRisk, bool $riskOverride): string
    {
        if ($levelNumber <= 1 && ! $riskOverride && $behaviorRisk < 65) {
            return 'secondary';
        }

        if ($riskOverride || $behaviorRisk >= 65 || $levelNumber <= 3) {
            return $levelNumber <= 3 ? 'danger' : 'warning';
        }

        return match (true) {
            $levelNumber >= 8 => 'success',
            $levelNumber >= 6 => 'primary',
            $levelNumber >= 4 => 'warning',
            default => 'secondary',
        };
    }

    private function gradeForLevel(int $levelNumber): string
    {
        return match (true) {
            $levelNumber >= 10 => 'A',
            $levelNumber === 9 => 'B',
            $levelNumber === 8 => 'C',
            $levelNumber === 7 => 'D',
            $levelNumber === 6 => 'E',
            $levelNumber === 5 => 'F',
            default => 'G',
        };
    }

    private function gradeDescription(string $grade): string
    {
        return match ($grade) {
            'A' => 'Elite professional profile.',
            'B' => 'Professional profile.',
            'C' => 'Skilled profile.',
            'D' => 'Consistent profile.',
            'E' => 'Intermediate profile under monitoring.',
            'F' => 'Developing profile.',
            default => 'High-risk, novice, or insufficient profile.',
        };
    }

    private function gradeScale(): array
    {
        return [
            ['grade' => 'A', 'levels' => 'L10', 'description' => 'Elite professional profile.'],
            ['grade' => 'B', 'levels' => 'L9', 'description' => 'Professional profile.'],
            ['grade' => 'C', 'levels' => 'L8', 'description' => 'Skilled profile.'],
            ['grade' => 'D', 'levels' => 'L7', 'description' => 'Consistent profile.'],
            ['grade' => 'E', 'levels' => 'L6', 'description' => 'Intermediate profile under monitoring.'],
            ['grade' => 'F', 'levels' => 'L5', 'description' => 'Developing profile.'],
            ['grade' => 'G', 'levels' => 'L1 - L4', 'description' => 'High-risk, novice, or insufficient profile.'],
        ];
    }

    private function levelExplanation(int $baseLevel, string $baseLabel, int $finalLevel, string $finalLabel, float $classificationScore, bool $capActive, $capDetails): string
    {
        $baseSentence = 'The classification score of '.number_format($classificationScore, 2)
            .'/100 maps to L'.$baseLevel.' - '.$baseLabel.'.';

        if (! $capActive || collect($capDetails)->isEmpty()) {
            return $baseSentence.' No level cap is active, so the score level and final trader level match.';
        }

        $strictestCap = collect($capDetails)
            ->sortBy('cap_level_number')
            ->first();
        $capSummary = collect($capDetails)
            ->map(function (array $detail): string {
                $metric = isset($detail['metric'], $detail['value'])
                    ? ' ('.$detail['metric'].': '.$detail['value'].')'
                    : '';

                return 'L'.($detail['cap_level_number'] ?? '?').' '.($detail['label'] ?? 'Cap').$metric;
            })
            ->implode('; ');

        return $baseSentence
            .' Final trader level is L'.$finalLevel.' - '.$finalLabel
            .' because active caps are stricter than the score level. The strictest cap is L'
            .($strictestCap['cap_level_number'] ?? $finalLevel)
            .' from '.($strictestCap['label'] ?? 'the active cap')
            .'. Active caps: '.$capSummary.'.';
    }

    private function nextLevelRequirement(int $levelNumber, float $classificationScore, bool $capActive, $capReasons, string $sampleMode): string
    {
        if ($levelNumber >= 10) {
            return $sampleMode === 'small_sample'
                ? 'Keep the behavior clean and build at least '.self::MIN_STANDARD_SAMPLE_TRADES.' trades to convert this into a standard-sample professional grade.'
                : 'Maintain elite score, clean behavior, and controlled drawdown.';
        }

        if ($capActive && collect($capReasons)->isNotEmpty()) {
            return 'Unlock higher levels by clearing the active cap: '.collect($capReasons)->first();
        }

        $nextScore = $levelNumber * 10;

        return 'Reach at least '.number_format($nextScore, 0).'/100 classification score. Current classification score is '.number_format($classificationScore, 2).'/100.';
    }

    private function drivers(array $summary, array $traderStyleProfile, array $positionProfile): array
    {
        return [
            [
                'label' => 'Net P/L',
                'value' => number_format((float) ($summary['net_profit_loss'] ?? 0), 2).'u',
            ],
            [
                'label' => 'Win Rate',
                'value' => number_format((float) ($summary['win_rate'] ?? 0), 2).'%',
            ],
            [
                'label' => 'Profit Factor',
                'value' => is_numeric($summary['profit_factor'] ?? null)
                    ? number_format((float) $summary['profit_factor'], 2)
                    : (string) ($summary['profit_factor'] ?? 'N/A'),
            ],
            [
                'label' => 'Drawdown',
                'value' => number_format((float) ($summary['max_drawdown_percent'] ?? 0), 2).'%',
            ],
            [
                'label' => 'Behavior Risk',
                'value' => number_format((float) data_get($traderStyleProfile, 'final_score', data_get($traderStyleProfile, 'risk_score', 0)), 2).'/100',
            ],
            [
                'label' => 'Position Consistency',
                'value' => number_format((float) data_get($positionProfile, 'score', 0), 2).'/100',
            ],
        ];
    }
}
