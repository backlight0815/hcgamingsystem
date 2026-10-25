<?php

namespace App\Services;

use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TraderLevelSnapshotService
{
    public function forUser(User $user): array
    {
        $trades = $this->loadTrades($user);
        $capitalSummary = $this->capitalSummary($user);
        $analytics = app(TradingJournalAnalytics::class);
        $summary = $this->summary($trades, $capitalSummary, $analytics);
        $positionProfile = $analytics->positionConsistency($trades);
        $traderStyleProfile = $analytics->behavioralRiskProfile($trades, (float) $capitalSummary['total_deposits']);
        $levelProfile = app(TraderLevelProfileService::class)->profile(
            $summary,
            [],
            $traderStyleProfile,
            $positionProfile
        );

        return [
            'level' => $levelProfile,
            'summary' => $summary,
            'capital' => $capitalSummary,
            'behavior' => $traderStyleProfile,
            'position' => $positionProfile,
        ];
    }

    private function loadTrades(User $user): Collection
    {
        $trades = collect();

        if (Schema::hasTable('trading_journals')) {
            $trades = $trades->merge(
                $this->tradeOnly(TradingJournal::query(), 'trading_journals')
                    ->where('user_id', $user->id)
                    ->get()
                    ->map(fn ($trade): array => $this->mapTrade($trade, 'Current Journal'))
            );
        }

        if (Schema::hasTable('trading_journals_backup')) {
            $trades = $trades->merge(
                $this->tradeOnly(TradingJournalBackup::query(), 'trading_journals_backup')
                    ->where('user_id', $user->id)
                    ->get()
                    ->map(fn ($trade): array => $this->mapTrade($trade, 'Backup Journal'))
            );
        }

        return $trades
            ->sortBy(fn (array $trade) => $trade['closed_at'] ?? $trade['opened_at'] ?? $trade['created_at'])
            ->values();
    }

    private function tradeOnly($query, string $table)
    {
        if (Schema::hasColumn($table, 'type')) {
            $query->where(function ($query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            });
        }

        return $query;
    }

    private function mapTrade($trade, string $source): array
    {
        $openedAt = $this->parseDate($trade->open_date ?? $trade->trade_date ?? $trade->created_at);
        $closedAt = $this->parseDate($trade->close_date ?? $trade->trade_date ?? $trade->created_at);

        return [
            'id' => $trade->id,
            'source' => $source,
            'type' => $trade->type ?? 'trade',
            'user_id' => $trade->user_id ?? null,
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'open_date' => $openedAt,
            'close_date' => $closedAt,
            'created_at' => $this->parseDate($trade->created_at),
            'pair' => (string) ($trade->pair ?? 'N/A'),
            'direction' => (int) ($trade->direction ?? 0),
            'entry_price' => (float) ($trade->entry_price ?? 0),
            'exit_price' => (float) ($trade->exit_price ?? 0),
            'profit_loss' => (float) ($trade->profit_loss ?? 0),
            'pips' => (float) ($trade->pips ?? 0),
            'lot_size' => (float) ($trade->lot_size ?? 0),
            'result' => (int) ($trade->result ?? 0),
        ];
    }

    private function capitalSummary(User $user): array
    {
        if (! Schema::hasTable('capitals')) {
            return [
                'total_deposits' => 0.0,
                'total_withdrawals' => 0.0,
                'net_capital' => 0.0,
                'transactions' => 0,
            ];
        }

        $transactions = Capital::query()
            ->where('user_id', $user->id)
            ->get();

        $deposits = (float) $transactions->where('type', 1)->sum('amount');
        $withdrawals = abs((float) $transactions->where('type', 2)->sum('amount'));

        return [
            'total_deposits' => round($deposits, 2),
            'total_withdrawals' => round($withdrawals, 2),
            'net_capital' => round((float) $transactions->sum('amount'), 2),
            'transactions' => $transactions->count(),
        ];
    }

    private function summary(Collection $trades, array $capitalSummary, TradingJournalAnalytics $analytics): array
    {
        $totalTrades = $trades->count();
        $winningTrades = $trades->where('profit_loss', '>', 0);
        $losingTrades = $trades->where('profit_loss', '<', 0);
        $breakevenTrades = $trades->where('profit_loss', '=', 0);
        $closedTradeCount = $winningTrades->count() + $losingTrades->count();
        $totalProfit = round((float) $winningTrades->sum('profit_loss'), 2);
        $totalLoss = round(abs((float) $losingTrades->sum('profit_loss')), 2);
        $netProfitLoss = round((float) $trades->sum('profit_loss'), 2);
        $averageWin = $winningTrades->count() > 0 ? round((float) $winningTrades->avg('profit_loss'), 2) : 0.0;
        $averageLoss = $losingTrades->count() > 0 ? round(abs((float) $losingTrades->avg('profit_loss')), 2) : 0.0;
        $winRate = $closedTradeCount > 0 ? round(($winningTrades->count() / $closedTradeCount) * 100, 2) : 0.0;
        $profitFactor = $totalLoss > 0 ? round($totalProfit / $totalLoss, 2) : ($totalProfit > 0 ? 'Perfect' : 'N/A');
        $bestDayRule = $analytics->bestDayRule($trades);
        unset($bestDayRule['daily_profit_loss']);
        $grossProfitRule = $analytics->grossProfitRule($trades, (float) $capitalSummary['total_deposits']);
        $dailyPnL = $this->dailyPnL($trades);
        $worstDay = $dailyPnL->isNotEmpty() ? round((float) $dailyPnL->min(), 2) : 0.0;
        $maxDailyLossAmount = abs(min(0, $worstDay));
        $drawdown = $this->calculateDrawdown($trades, (float) $capitalSummary['total_deposits']);
        $winRateDecimal = $totalTrades > 0 ? $winningTrades->count() / $totalTrades : 0.0;
        $lossRateDecimal = $totalTrades > 0 ? $losingTrades->count() / $totalTrades : 0.0;

        return [
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades->count(),
            'losing_trades' => $losingTrades->count(),
            'breakeven_trades' => $breakevenTrades->count(),
            'win_rate' => $winRate,
            'total_profit' => $totalProfit,
            'total_loss' => $totalLoss,
            'net_profit_loss' => $netProfitLoss,
            'average_win' => $averageWin,
            'average_loss' => $averageLoss,
            'profit_factor' => $profitFactor,
            'expectancy' => round(($winRateDecimal * $averageWin) - ($lossRateDecimal * $averageLoss), 2),
            'largest_win' => $winningTrades->count() > 0 ? round((float) $winningTrades->max('profit_loss'), 2) : 0.0,
            'current_balance' => round((float) $capitalSummary['total_deposits'] + $netProfitLoss - (float) $capitalSummary['total_withdrawals'], 2),
            'growth_percent' => (float) $capitalSummary['total_deposits'] > 0 ? round(($netProfitLoss / (float) $capitalSummary['total_deposits']) * 100, 2) : 0.0,
            'max_drawdown_amount' => $drawdown['amount'],
            'max_drawdown_percent' => $drawdown['percent'],
            'max_daily_loss_amount' => round($maxDailyLossAmount, 2),
            'max_daily_loss_percent' => (float) $capitalSummary['total_deposits'] > 0 ? round(($maxDailyLossAmount / (float) $capitalSummary['total_deposits']) * 100, 2) : 0.0,
            'overall_loss_percent' => (float) $capitalSummary['total_deposits'] > 0 ? round((abs(min(0, $netProfitLoss)) / (float) $capitalSummary['total_deposits']) * 100, 2) : 0.0,
            'profitable_days' => $dailyPnL->filter(fn (float $profitLoss): bool => $profitLoss > 0)->count(),
            'best_day_rule' => $bestDayRule,
            'gross_profit_rule' => $grossProfitRule,
        ];
    }

    private function dailyPnL(Collection $trades): Collection
    {
        return $trades
            ->filter(fn (array $trade): bool => ($trade['closed_at'] ?? null) instanceof Carbon)
            ->groupBy(fn (array $trade): string => $trade['closed_at']->toDateString())
            ->map(fn (Collection $dayTrades): float => round((float) $dayTrades->sum('profit_loss'), 2));
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
}
