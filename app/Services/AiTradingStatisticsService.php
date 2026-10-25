<?php

namespace App\Services;

use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AiTradingStatisticsService
{
    public function summaryFor(User $user, string $message, bool $force = false): ?array
    {
        if (! $force && ! $this->looksLikeStatisticsQuestion($message)) {
            return null;
        }

        $includeBehavioralRisk = $this->looksLikeBehaviorQuestion($message);
        [$periodLabel, $startDate, $endDate] = $this->periodFromMessage($message);
        $dateColumn = $this->journalDateColumn();

        $query = TradingJournal::query()
            ->where('user_id', $user->id)
            ->where(function ($tradeQuery): void {
                $tradeQuery->where('type', 'trade')->orWhereNull('type');
            });

        if ($dateColumn && $startDate && $endDate) {
            $query->whereBetween($dateColumn, [$startDate, $endDate]);
        }

        if ($dateColumn) {
            $query->orderBy($dateColumn);
        }

        $trades = $query->get()->map(function (TradingJournal $trade) use ($dateColumn): array {
            $dateValue = $dateColumn ? ($trade->{$dateColumn} ?? null) : null;
            $openedAt = $this->parseDate($trade->open_date ?? $trade->created_at);
            $closedAt = $this->parseDate($trade->close_date ?? $dateValue ?? $trade->open_date ?? $trade->created_at);

            return [
                'id' => $trade->id,
                'source' => 'journal',
                'type' => $trade->type,
                'user_id' => $trade->user_id,
                'open_date' => $openedAt,
                'close_date' => $closedAt,
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
                'pair' => (string) ($trade->pair ?? 'N/A'),
                'direction' => (int) ($trade->direction ?? 0),
                'entry_price' => (float) ($trade->entry_price ?? 0),
                'exit_price' => (float) ($trade->exit_price ?? 0),
                'profit_loss' => (float) ($trade->profit_loss ?? 0),
                'pips' => (float) ($trade->pips ?? 0),
                'lot_size' => (float) ($trade->lot_size ?? 0),
                'result' => (int) ($trade->result ?? 0),
            ];
        });

        $capitalQuery = Capital::query()->where('user_id', $user->id);
        if ($startDate && $endDate && Schema::hasColumn('capitals', 'deposit_date')) {
            $capitalQuery->whereBetween('deposit_date', [$startDate->toDateString(), $endDate->toDateString()]);
        }

        $capitalTransactions = $capitalQuery->get();
        $totalDeposits = round((float) $capitalTransactions->where('type', 1)->sum('amount'), 2);
        $totalWithdrawals = round(abs((float) $capitalTransactions->where('type', 2)->sum('amount')), 2);

        return $this->buildSummary($trades, $totalDeposits, $totalWithdrawals, $periodLabel, $includeBehavioralRisk);
    }

    private function looksLikeStatisticsQuestion(string $message): bool
    {
        $message = Str::lower($message);

        foreach ([
            'statistic', 'statistics', 'stats', 'win rate', 'winrate', 'total trade', 'profit',
            'loss', 'p/l', 'pnl', 'drawdown', 'growth', 'consistency', 'expectancy',
            'risk percentage', 'risk percent', 'risk reward', 'rrr', 'this month', 'last month',
            'performance', 'percentage wrong', 'growth wrong', 'stats not showing',
            'revenge', 'gambling', 'layering', 'layer strategy', 'behavior', 'behaviour',
            'overtrading', 'rapid-fire', 'rapid fire', 'lot spike', 'oversized loss',
            'margin pressure', 'high variance',
        ] as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeBehaviorQuestion(string $message): bool
    {
        $message = Str::lower($message);

        foreach ([
            'revenge', 'gambling', 'layering', 'layer strategy', 'layered', 'averaging',
            'average down', 'average up', 'behavior', 'behaviour', 'risk profile',
            'trading style', 'overtrading', 'rapid-fire', 'rapid fire', 'lot spike',
            'oversized loss', 'margin pressure', 'high variance', 'stacked exposure',
        ] as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function periodFromMessage(string $message): array
    {
        $message = Str::lower($message);
        $now = now();

        if (str_contains($message, 'today')) {
            return ['today', $now->copy()->startOfDay(), $now->copy()->endOfDay()];
        }

        if (str_contains($message, 'last week')) {
            $lastWeek = $now->copy()->subWeek();

            return ['last week', $lastWeek->copy()->startOfWeek(), $lastWeek->copy()->endOfWeek()];
        }

        if (str_contains($message, 'this week')) {
            return ['this week', $now->copy()->startOfWeek(), $now->copy()->endOfWeek()];
        }

        if (str_contains($message, 'last month')) {
            $lastMonth = $now->copy()->subMonthNoOverflow();

            return ['last month', $lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()];
        }

        if (str_contains($message, 'this month') || str_contains($message, 'month')) {
            return ['this month', $now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        if (str_contains($message, 'this year') || str_contains($message, 'year')) {
            return ['this year', $now->copy()->startOfYear(), $now->copy()->endOfYear()];
        }

        return ['all time', null, null];
    }

    private function buildSummary(Collection $trades, float $totalDeposits, float $totalWithdrawals, string $periodLabel, bool $includeBehavioralRisk = false): array
    {
        $totalTrades = $trades->count();
        $winningTrades = $trades->where('profit_loss', '>', 0);
        $losingTrades = $trades->where('profit_loss', '<', 0);
        $breakevenTrades = $trades->where('profit_loss', '=', 0);
        $closedTrades = $winningTrades->count() + $losingTrades->count();

        $totalProfit = round((float) $winningTrades->sum('profit_loss'), 2);
        $totalLoss = round(abs((float) $losingTrades->sum('profit_loss')), 2);
        $netProfitLoss = round((float) $trades->sum('profit_loss'), 2);
        $averageWin = $winningTrades->count() > 0 ? round((float) $winningTrades->avg('profit_loss'), 2) : 0.0;
        $averageLoss = $losingTrades->count() > 0 ? round(abs((float) $losingTrades->avg('profit_loss')), 2) : 0.0;
        $winRate = $closedTrades > 0 ? round(($winningTrades->count() / $closedTrades) * 100, 2) : 0.0;
        $profitFactor = $totalLoss > 0 ? round($totalProfit / $totalLoss, 2) : ($totalProfit > 0 ? 'Perfect' : 'N/A');
        $expectancy = $totalTrades > 0
            ? round((($winningTrades->count() / $totalTrades) * $averageWin) - (($losingTrades->count() / $totalTrades) * $averageLoss), 2)
            : 0.0;

        $drawdown = $this->calculateDrawdown($trades, $totalDeposits);
        $dailyPnl = $this->dailyProfitLoss($trades);
        $worstDay = $dailyPnl->isNotEmpty() ? min(0, (float) $dailyPnl->min()) : 0.0;
        $riskPercent = $totalDeposits > 0 ? round((abs($worstDay) / $totalDeposits) * 100, 2) : 0.0;
        $currentBalance = round($totalDeposits + $netProfitLoss - $totalWithdrawals, 2);

        $analytics = new TradingJournalAnalytics();
        $bestDayRule = $analytics->bestDayRule($trades);

        $summary = [
            'period' => $periodLabel,
            'total_trades' => $totalTrades,
            'completed_trades_for_win_rate' => $closedTrades,
            'wins' => $winningTrades->count(),
            'losses' => $losingTrades->count(),
            'breakeven_trades' => $breakevenTrades->count(),
            'win_rate_percent' => $winRate,
            'total_profit' => $totalProfit,
            'total_loss' => $totalLoss,
            'net_profit_loss' => $netProfitLoss,
            'current_balance' => $currentBalance,
            'total_deposits' => $totalDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'growth_percent' => $totalDeposits > 0 ? round(($netProfitLoss / $totalDeposits) * 100, 2) : 0.0,
            'risk_reward_or_profit_factor' => $profitFactor,
            'max_drawdown_amount' => $drawdown['amount'],
            'max_drawdown_percent' => $drawdown['percent'],
            'consistency_score_percent' => $bestDayRule['score_percent'] ?? 0.0,
            'consistency_status' => $bestDayRule['status'] ?? 'No Profit Yet',
            'expectancy' => $expectancy,
            'average_win' => $averageWin,
            'average_loss' => $averageLoss,
            'risk_percent' => $riskPercent,
            'formulas' => [
                'win_rate' => 'wins / (wins + losses) * 100; breakeven trades are not counted in the denominator.',
                'net_profit_loss' => 'sum of trade profit_loss values.',
                'growth_percent' => 'net_profit_loss / total_deposits * 100 when deposits are greater than zero.',
                'drawdown' => 'largest decline from starting/peak equity based on closed trade profit_loss sequence.',
                'expectancy' => '(win probability * average win) - (loss probability * average loss).',
                'risk_reward_or_profit_factor' => 'total_profit / total_loss when total loss is greater than zero.',
                'behavioral_risk_score' => 'max(gambling_score, revenge_score), capped at 100. Layering is reviewed separately and does not automatically increase gambling or revenge tier.',
                'revenge_detection' => 'looks for loss-chasing evidence such as recent loss, cooldown breach, risk increase after loss, lot increase after loss, same-symbol pressure, loss streak, emotional tag, or daily loss limit breach.',
                'gambling_detection' => 'looks for discipline breaks such as missing plan, missing entry reason, missing stop loss, risk above plan, setup risk above plan, overtrading, dangerous margin exposure, poor RR, or random symbols/strategy switching.',
                'layering_detection' => 'looks for same-pair, same-direction trades opened close together or overlapping. Planned layering is not treated as gambling/revenge when it follows the setup and controlled-risk rules.',
            ],
            'calculation_notes' => [
                'Only the logged-in user data is included.',
                'This is a calculated summary only; no raw trade rows are sent to Gemini.',
                'If growth looks wrong, check deposits, withdrawals, date filters, duplicate/deleted trades, profit/loss values, and decimal inputs.',
            ],
        ];

        if ($includeBehavioralRisk) {
            $accountBalanceForBehavior = max(0, $currentBalance > 0 ? $currentBalance : $totalDeposits);
            $summary['behavioral_risk'] = $this->behavioralRiskSummary(
                $analytics->behavioralRiskProfile($trades, $accountBalanceForBehavior)
            );
            $summary['calculation_notes'][] = 'Behavioral risk is a journal review signal only. It is not a trading signal, diagnosis, or financial advice.';
        }

        return $summary;
    }

    private function behavioralRiskSummary(array $profile): array
    {
        return [
            'style_label' => (string) data_get($profile, 'style_label', 'N/A'),
            'risk_level' => (string) data_get($profile, 'risk_level', 'N/A'),
            'risk_score' => round((float) data_get($profile, 'risk_score', 0), 2),
            'summary' => (string) data_get($profile, 'summary', ''),
            'style_tags' => $this->simpleList(data_get($profile, 'style_tags', [])),
            'revenge' => $this->revengeSummary((array) data_get($profile, 'revenge', [])),
            'gambling' => $this->gamblingSummary((array) data_get($profile, 'gambling', [])),
            'layering' => $this->layeringSummary((array) data_get($profile, 'layering', [])),
        ];
    }

    private function revengeSummary(array $profile): array
    {
        return [
            'detected' => (bool) data_get($profile, 'detected', false),
            'status' => (string) data_get($profile, 'status', 'N/A'),
            'score' => round((float) data_get($profile, 'score', 0), 2),
            'tier_label' => (string) data_get($profile, 'tier_label', 'N/A'),
            'tier_description' => (string) data_get($profile, 'tier_description', ''),
            'event_count' => (int) data_get($profile, 'event_count', 0),
            'quick_reentry_count' => (int) data_get($profile, 'quick_reentry_count', 0),
            'lot_increase_count' => (int) data_get($profile, 'lot_increase_count', 0),
            'loss_streak_reaction_count' => (int) data_get($profile, 'loss_streak_reaction_count', 0),
            'average_delay_label' => (string) data_get($profile, 'average_delay_label', '0m'),
            'affected_pairs' => $this->simpleList(data_get($profile, 'affected_pairs', [])),
            'checks' => $this->checkSummary(data_get($profile, 'checks', [])),
            'top_evidence' => $this->revengeEvidence(data_get($profile, 'examples', [])),
        ];
    }

    private function gamblingSummary(array $profile): array
    {
        return [
            'detected' => (bool) data_get($profile, 'detected', false),
            'status' => (string) data_get($profile, 'status', 'N/A'),
            'score' => round((float) data_get($profile, 'score', 0), 2),
            'tier_label' => (string) data_get($profile, 'tier_label', 'N/A'),
            'tier_description' => (string) data_get($profile, 'tier_description', ''),
            'trade_count' => (int) data_get($profile, 'trade_count', 0),
            'active_days' => (int) data_get($profile, 'active_days', 0),
            'average_trades_per_day' => round((float) data_get($profile, 'average_trades_per_day', 0), 2),
            'max_trades_per_day' => (int) data_get($profile, 'max_trades_per_day', 0),
            'overtrading_days' => (int) data_get($profile, 'overtrading_days', 0),
            'rapid_trade_count' => (int) data_get($profile, 'rapid_trade_count', 0),
            'lot_spike_count' => (int) data_get($profile, 'lot_spike_count', 0),
            'oversized_loss_count' => (int) data_get($profile, 'oversized_loss_count', 0),
            'lot_deviation_event_count' => (int) data_get($profile, 'lot_deviation_event_count', 0),
            'duration_deviation_event_count' => (int) data_get($profile, 'duration_deviation_event_count', 0),
            'layering_exposure_event_count' => (int) data_get($profile, 'layering_exposure_event_count', 0),
            'margin_pressure_event_count' => (int) data_get($profile, 'margin_pressure_event_count', 0),
            'max_margin_percent' => round((float) data_get($profile, 'max_margin_percent', 0), 2),
            'checks' => $this->checkSummary(data_get($profile, 'checks', []), 9),
            'top_evidence' => $this->gamblingEvidence(data_get($profile, 'examples', [])),
        ];
    }

    private function layeringSummary(array $profile): array
    {
        return [
            'detected' => (bool) data_get($profile, 'detected', false),
            'status' => (string) data_get($profile, 'status', 'N/A'),
            'score' => round((float) data_get($profile, 'score', 0), 2),
            'event_count' => (int) data_get($profile, 'event_count', 0),
            'layered_trade_count' => (int) data_get($profile, 'layered_trade_count', 0),
            'layered_trade_percent' => round((float) data_get($profile, 'layered_trade_percent', 0), 2),
            'overlap_count' => (int) data_get($profile, 'overlap_count', 0),
            'fast_layer_count' => (int) data_get($profile, 'fast_layer_count', 0),
            'lot_increase_count' => (int) data_get($profile, 'lot_increase_count', 0),
            'adverse_layer_count' => (int) data_get($profile, 'adverse_layer_count', 0),
            'max_active_layers' => (int) data_get($profile, 'max_active_layers', 0),
            'average_delay_label' => (string) data_get($profile, 'average_delay_label', '0m'),
            'affected_pairs' => $this->simpleList(data_get($profile, 'affected_pairs', [])),
            'checks' => $this->checkSummary(data_get($profile, 'checks', [])),
            'top_evidence' => $this->layeringEvidence(data_get($profile, 'examples', [])),
        ];
    }

    private function checkSummary($checks, int $limit = 8): array
    {
        return collect($this->simpleList($checks, $limit))
            ->map(fn (array $check): array => [
                'name' => (string) ($check['name'] ?? 'Check'),
                'value' => (string) ($check['value'] ?? ''),
                'status' => (string) ($check['status'] ?? ''),
                'description' => (string) ($check['description'] ?? ''),
            ])
            ->values()
            ->all();
    }

    private function revengeEvidence($examples): array
    {
        return collect($this->simpleList($examples, 3))
            ->map(fn (array $event): array => [
                'pair' => (string) ($event['pair'] ?? 'N/A'),
                'delay_after_loss' => (string) ($event['delay_label'] ?? 'N/A'),
                'loss_amount' => round((float) ($event['loss_amount'] ?? 0), 2),
                'previous_lot' => round((float) ($event['previous_lot'] ?? 0), 4),
                'response_lot' => round((float) ($event['response_lot'] ?? 0), 4),
                'lot_ratio' => round((float) ($event['lot_ratio'] ?? 0), 2),
                'same_pair' => (bool) ($event['same_pair'] ?? false),
                'opposite_direction' => (bool) ($event['opposite_direction'] ?? false),
                'signals' => $this->simpleList($event['signals'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function gamblingEvidence($examples): array
    {
        return collect($this->simpleList($examples, 3))
            ->map(fn (array $event): array => [
                'pair' => (string) ($event['pair'] ?? 'N/A'),
                'direction' => (string) ($event['direction'] ?? 'N/A'),
                'lot_size' => round((float) ($event['lot_size'] ?? 0), 4),
                'profit_loss' => round((float) ($event['profit_loss'] ?? 0), 2),
                'score' => round((float) ($event['score'] ?? 0), 2),
                'tier_label' => (string) ($event['tier_label'] ?? 'N/A'),
                'reasons' => $this->simpleList($event['reasons'] ?? []),
                'evidence' => $this->simpleList($event['evidence'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function layeringEvidence($examples): array
    {
        return collect($this->simpleList($examples, 3))
            ->map(fn (array $event): array => [
                'pair' => (string) ($event['pair'] ?? 'N/A'),
                'direction' => (string) ($event['direction'] ?? 'N/A'),
                'delay_between_layers' => (string) ($event['delay_label'] ?? 'N/A'),
                'overlap' => (bool) ($event['overlap'] ?? false),
                'base_lot' => round((float) ($event['base_lot'] ?? 0), 4),
                'layer_lot' => round((float) ($event['layer_lot'] ?? 0), 4),
                'lot_ratio' => round((float) ($event['lot_ratio'] ?? 0), 2),
                'active_layers' => (int) ($event['active_layers'] ?? 0),
                'price_relationship' => (string) ($event['price_relationship'] ?? 'N/A'),
                'is_adverse' => (bool) ($event['is_adverse'] ?? false),
                'signals' => $this->simpleList($event['signals'] ?? []),
            ])
            ->values()
            ->all();
    }

    private function simpleList($value, int $limit = 10): array
    {
        if ($value instanceof Collection) {
            return $value->take($limit)->values()->all();
        }

        if (is_array($value)) {
            return array_slice(array_values($value), 0, $limit);
        }

        return [];
    }

    private function calculateDrawdown(Collection $trades, float $startingBalance): array
    {
        $equity = $startingBalance;
        $peak = $startingBalance;
        $maxDrawdown = 0.0;
        $maxDrawdownPercent = 0.0;

        foreach ($trades as $trade) {
            $equity += (float) ($trade['profit_loss'] ?? 0);
            $peak = max($peak, $equity);
            $drawdown = max(0, $peak - $equity);
            $drawdownPercent = $peak > 0 ? ($drawdown / $peak) * 100 : 0;

            if ($drawdown > $maxDrawdown) {
                $maxDrawdown = $drawdown;
                $maxDrawdownPercent = $drawdownPercent;
            }
        }

        return [
            'amount' => round($maxDrawdown, 2),
            'percent' => round($maxDrawdownPercent, 2),
        ];
    }

    private function dailyProfitLoss(Collection $trades): Collection
    {
        return $trades
            ->filter(fn (array $trade): bool => ($trade['closed_at'] ?? null) instanceof Carbon)
            ->groupBy(fn (array $trade): string => $trade['closed_at']->toDateString())
            ->map(fn (Collection $dayTrades): float => round((float) $dayTrades->sum('profit_loss'), 2));
    }

    private function journalDateColumn(): ?string
    {
        foreach (['close_date', 'open_date', 'trade_date', 'created_at'] as $column) {
            if (Schema::hasColumn('trading_journals', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function parseDate($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
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
}
