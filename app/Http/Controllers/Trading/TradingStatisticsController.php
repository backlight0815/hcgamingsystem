<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class TradingStatisticsController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = Auth::user();
        $canViewAll = $currentUser && in_array((int) $currentUser->role_id, [1, 2], true);

        $source = $request->input('source', 'all');
        if (! in_array($source, ['all', 'current', 'backup'], true)) {
            $source = 'all';
        }

        $month = $this->normalizeDateFilter($request->input('month'), 1, 12);
        $year = $this->normalizeDateFilter($request->input('year'), 2000, (int) now()->year);
        $selectedTraderId = $canViewAll ? $this->normalizeTraderId($request->input('user_id')) : optional($currentUser)->id;

        $currentTrades = collect();
        $backupTrades = collect();

        if (in_array($source, ['all', 'current'], true)) {
            $currentTrades = $this->loadJournalTrades(
                TradingJournal::query(),
                'trading_journals',
                'Current Journal',
                $selectedTraderId,
                $month,
                $year
            );
        }

        if (in_array($source, ['all', 'backup'], true) && Schema::hasTable('trading_journals_backup')) {
            $backupTrades = $this->loadJournalTrades(
                TradingJournalBackup::query(),
                'trading_journals_backup',
                'Backup Journal',
                $selectedTraderId,
                $month,
                $year
            );
        }

        $trades = $currentTrades
            ->merge($backupTrades)
            ->sortBy(fn ($trade) => $trade['closed_at'] ?? $trade['opened_at'] ?? $trade['created_at'])
            ->values();

        $capitalSummary = $this->capitalSummary($selectedTraderId, $month, $year);
        $summary = $this->buildSummary($trades, $capitalSummary);
        $dailyStats = $this->buildDailyStats($trades);
        $monthlyStats = $this->buildMonthlyStats($trades);
        $pairStats = $this->buildPairStats($trades);
        $sourceStats = $this->buildSourceStats($trades);
        $directionStats = $this->buildDirectionStats($trades);
        $ruleMonitor = $this->buildRuleMonitor($summary);
        $performanceProfile = $this->buildPerformanceProfile($summary, $ruleMonitor);
        $weekdayStats = $this->buildWeekdayStats($trades);
        $sessionStats = $this->buildSessionStats($trades);
        $behavioralProfile = $this->buildBehavioralProfile($summary, $directionStats);
        $levelProfile = $this->buildLevelProfile($summary);
        $recentTrades = $trades->sortByDesc(fn ($trade) => $trade['closed_at'] ?? $trade['opened_at'] ?? $trade['created_at'])->take(15)->values();

        $chartData = [
            'labels' => $dailyStats->pluck('date')->values(),
            'daily_pnl' => $dailyStats->pluck('profit_loss')->values(),
            'cumulative_pnl' => $dailyStats->pluck('cumulative_profit_loss')->values(),
            'monthly_labels' => $monthlyStats->pluck('month')->values(),
            'monthly_pnl' => $monthlyStats->pluck('profit_loss')->values(),
            'source_labels' => $sourceStats->pluck('source')->values(),
            'source_pnl' => $sourceStats->pluck('profit_loss')->values(),
            'direction_labels' => $directionStats->pluck('direction')->values(),
            'direction_pnl' => $directionStats->pluck('profit_loss')->values(),
            'weekday_labels' => $weekdayStats->pluck('short_day')->values(),
            'weekday_pnl' => $weekdayStats->pluck('profit_loss')->values(),
            'wins' => $summary['winning_trades'],
            'losses' => $summary['losing_trades'],
            'breakeven' => $summary['breakeven_trades'],
        ];

        $traders = $canViewAll
            ? User::whereIn('id', $this->traderIdsFromJournals())->orderBy('name')->get(['id', 'name', 'username'])
            : collect();

        $availableYears = $this->availableYears($selectedTraderId, $source);
        if ($year && ! $availableYears->contains($year)) {
            $availableYears->push($year);
        }

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Trading Statistics', 'url' => route('all.trading.statistics')],
        ];

        return view('admin.trading_statistics.index', [
            'breadcrumbData' => $breadcrumbData,
            'trades' => $trades,
            'recentTrades' => $recentTrades,
            'summary' => $summary,
            'capitalSummary' => $capitalSummary,
            'dailyStats' => $dailyStats,
            'monthlyStats' => $monthlyStats,
            'pairStats' => $pairStats,
            'sourceStats' => $sourceStats,
            'directionStats' => $directionStats,
            'ruleMonitor' => $ruleMonitor,
            'performanceProfile' => $performanceProfile,
            'weekdayStats' => $weekdayStats,
            'sessionStats' => $sessionStats,
            'behavioralProfile' => $behavioralProfile,
            'levelProfile' => $levelProfile,
            'chartData' => $chartData,
            'traders' => $traders,
            'currentUser' => $currentUser,
            'canViewAll' => $canViewAll,
            'selectedTraderId' => $selectedTraderId,
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'selectedSource' => $source,
            'availableYears' => $availableYears,
        ]);
    }

    private function loadJournalTrades(Builder $query, string $table, string $sourceLabel, ?int $userId, ?int $month, ?int $year): Collection
    {
        $dateColumn = $this->journalDateColumn($table);
        $columns = Schema::getColumnListing($table);

        if (in_array('user_id', $columns, true) && $userId) {
            $query->where('user_id', $userId);
        }

        $query = $this->tradeOnly($query, $table);

        if ($month && $dateColumn) {
            $query->whereMonth($dateColumn, $month);
        }

        if ($year && $dateColumn) {
            $query->whereYear($dateColumn, $year);
        }

        if ($dateColumn) {
            $query->orderBy($dateColumn);
        }

        return $query->with('user')->get()->map(function ($trade) use ($sourceLabel): array {
            $openedAt = $this->parseDate($trade->open_date ?? $trade->trade_date ?? $trade->created_at);
            $closedAt = $this->parseDate($trade->close_date ?? $trade->trade_date ?? $trade->created_at);

            return [
                'id' => $trade->id,
                'source' => $sourceLabel,
                'user_id' => $trade->user_id ?? null,
                'trader_name' => optional($trade->user)->name ?? optional($trade->user)->username ?? 'Unknown Trader',
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
                'created_at' => $this->parseDate($trade->created_at),
                'pair' => strtoupper((string) ($trade->pair ?? 'N/A')),
                'direction' => (int) ($trade->direction ?? 0),
                'entry_price' => (float) ($trade->entry_price ?? 0),
                'exit_price' => (float) ($trade->exit_price ?? 0),
                'lot_size' => (float) ($trade->lot_size ?? 0),
                'pips' => (float) ($trade->pips ?? 0),
                'profit_loss' => (float) ($trade->profit_loss ?? 0),
                'result' => (int) ($trade->result ?? 0),
                'notes' => $trade->notes ?? null,
            ];
        });
    }

    private function buildSummary(Collection $trades, array $capitalSummary): array
    {
        $totalTrades = $trades->count();
        $winningTrades = $trades->where('profit_loss', '>', 0);
        $losingTrades = $trades->where('profit_loss', '<', 0);
        $breakevenTrades = $trades->where('profit_loss', '=', 0);

        $totalProfit = round($winningTrades->sum('profit_loss'), 2);
        $totalLoss = round(abs($losingTrades->sum('profit_loss')), 2);
        $netProfitLoss = round($trades->sum('profit_loss'), 2);
        $closedTradeCount = $winningTrades->count() + $losingTrades->count();

        $averageWin = $winningTrades->count() > 0 ? round($winningTrades->avg('profit_loss'), 2) : 0;
        $averageLoss = $losingTrades->count() > 0 ? round(abs($losingTrades->avg('profit_loss')), 2) : 0;
        $winRate = $closedTradeCount > 0 ? round(($winningTrades->count() / $closedTradeCount) * 100, 2) : 0;
        $lossRate = $closedTradeCount > 0 ? round(($losingTrades->count() / $closedTradeCount) * 100, 2) : 0;
        $profitFactor = $totalLoss > 0 ? round($totalProfit / $totalLoss, 2) : ($totalProfit > 0 ? 'Perfect' : 'N/A');

        $winRateDecimal = $totalTrades > 0 ? $winningTrades->count() / $totalTrades : 0;
        $lossRateDecimal = $totalTrades > 0 ? $losingTrades->count() / $totalTrades : 0;
        $expectancy = round(($winRateDecimal * $averageWin) - ($lossRateDecimal * $averageLoss), 2);

        $drawdown = $this->calculateDrawdown($trades, $capitalSummary['total_deposits']);
        [$longestWinStreak, $longestLossStreak] = $this->calculateStreaks($trades);
        $dailyPnL = $this->dailyPnL($trades);
        $bestDay = $dailyPnL->isNotEmpty() ? round($dailyPnL->max(), 2) : 0;
        $worstDay = $dailyPnL->isNotEmpty() ? round($dailyPnL->min(), 2) : 0;
        $profitableDays = $dailyPnL->filter(fn (float $profitLoss): bool => $profitLoss > 0)->count();
        $losingDays = $dailyPnL->filter(fn (float $profitLoss): bool => $profitLoss < 0)->count();
        $tradingDays = $dailyPnL->count();
        $dailyWinRate = $tradingDays > 0 ? round(($profitableDays / $tradingDays) * 100, 2) : 0;
        $biggestWinningDay = max(0, $bestDay);
        $consistencyPercent = $netProfitLoss > 0 ? round(($biggestWinningDay / $netProfitLoss) * 100, 2) : 0;
        $maxDailyLossAmount = abs(min(0, $worstDay));
        $maxDailyLossPercent = $capitalSummary['total_deposits'] > 0 ? round(($maxDailyLossAmount / $capitalSummary['total_deposits']) * 100, 2) : 0;
        $overallLossPercent = $capitalSummary['total_deposits'] > 0 ? round((abs(min(0, $netProfitLoss)) / $capitalSummary['total_deposits']) * 100, 2) : 0;
        $recoveryFactor = $drawdown['amount'] > 0 ? round($netProfitLoss / $drawdown['amount'], 2) : ($netProfitLoss > 0 ? 'No Drawdown' : 'N/A');
        $payoffRatio = $averageLoss > 0 ? round($averageWin / $averageLoss, 2) : ($averageWin > 0 ? 'Perfect' : 'N/A');
        $firstTradeDate = $trades->pluck('closed_at')->filter()->min();
        $lastTradeDate = $trades->pluck('closed_at')->filter()->max();
        $durationDays = ($firstTradeDate instanceof Carbon && $lastTradeDate instanceof Carbon)
            ? $firstTradeDate->diffInDays($lastTradeDate) + 1
            : 0;
        $holdingMinutes = $trades
            ->filter(fn (array $trade): bool => $trade['opened_at'] instanceof Carbon && $trade['closed_at'] instanceof Carbon)
            ->map(fn (array $trade): int => max(0, $trade['opened_at']->diffInMinutes($trade['closed_at'])));
        $averageHoldingMinutes = $holdingMinutes->isNotEmpty() ? (int) round($holdingMinutes->avg()) : 0;
        $bestDayName = $this->bestWeekday($trades);

        return [
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades->count(),
            'losing_trades' => $losingTrades->count(),
            'breakeven_trades' => $breakevenTrades->count(),
            'win_rate' => $winRate,
            'loss_rate' => $lossRate,
            'total_profit' => $totalProfit,
            'total_loss' => $totalLoss,
            'net_profit_loss' => $netProfitLoss,
            'average_win' => $averageWin,
            'average_loss' => $averageLoss,
            'profit_factor' => $profitFactor,
            'expectancy' => $expectancy,
            'total_pips' => round($trades->sum('pips'), 2),
            'average_pips' => $totalTrades > 0 ? round($trades->avg('pips'), 2) : 0,
            'total_lots' => round($trades->sum('lot_size'), 2),
            'average_lot' => $totalTrades > 0 ? round($trades->avg('lot_size'), 2) : 0,
            'largest_win' => $winningTrades->count() > 0 ? round($winningTrades->max('profit_loss'), 2) : 0,
            'largest_loss' => $losingTrades->count() > 0 ? round($losingTrades->min('profit_loss'), 2) : 0,
            'current_balance' => round($capitalSummary['total_deposits'] + $netProfitLoss - $capitalSummary['total_withdrawals'], 2),
            'growth_percent' => $capitalSummary['total_deposits'] > 0 ? round(($netProfitLoss / $capitalSummary['total_deposits']) * 100, 2) : 0,
            'max_drawdown_amount' => $drawdown['amount'],
            'max_drawdown_percent' => $drawdown['percent'],
            'max_daily_loss_amount' => round($maxDailyLossAmount, 2),
            'max_daily_loss_percent' => $maxDailyLossPercent,
            'overall_loss_percent' => $overallLossPercent,
            'best_day' => $bestDay,
            'worst_day' => $worstDay,
            'profitable_days' => $profitableDays,
            'losing_days' => $losingDays,
            'trading_days' => $tradingDays,
            'daily_win_rate' => $dailyWinRate,
            'consistency_percent' => $consistencyPercent,
            'biggest_winning_day' => round($biggestWinningDay, 2),
            'longest_win_streak' => $longestWinStreak,
            'longest_loss_streak' => $longestLossStreak,
            'recovery_factor' => $recoveryFactor,
            'payoff_ratio' => $payoffRatio,
            'duration_days' => $durationDays,
            'first_trade_date' => $firstTradeDate,
            'last_trade_date' => $lastTradeDate,
            'average_holding_minutes' => $averageHoldingMinutes,
            'best_weekday' => $bestDayName,
            'current_source_trades' => $trades->where('source', 'Current Journal')->count(),
            'backup_source_trades' => $trades->where('source', 'Backup Journal')->count(),
        ];
    }

    private function buildDailyStats(Collection $trades): Collection
    {
        $runningTotal = 0;

        return $trades
            ->filter(fn (array $trade): bool => $trade['closed_at'] instanceof Carbon)
            ->groupBy(fn (array $trade): string => $trade['closed_at']->toDateString())
            ->map(function (Collection $dayTrades, string $date) use (&$runningTotal): array {
                $profitLoss = round($dayTrades->sum('profit_loss'), 2);
                $runningTotal = round($runningTotal + $profitLoss, 2);

                return [
                    'date' => $date,
                    'trades' => $dayTrades->count(),
                    'profit_loss' => $profitLoss,
                    'cumulative_profit_loss' => $runningTotal,
                ];
            })
            ->values();
    }

    private function buildMonthlyStats(Collection $trades): Collection
    {
        return $trades
            ->filter(fn (array $trade): bool => $trade['closed_at'] instanceof Carbon)
            ->groupBy(fn (array $trade): string => $trade['closed_at']->format('Y-m'))
            ->map(function (Collection $monthTrades, string $month): array {
                $wins = $monthTrades->where('profit_loss', '>', 0)->count();
                $losses = $monthTrades->where('profit_loss', '<', 0)->count();
                $closedTrades = $wins + $losses;

                return [
                    'month' => $month,
                    'trades' => $monthTrades->count(),
                    'profit_loss' => round($monthTrades->sum('profit_loss'), 2),
                    'win_rate' => $closedTrades > 0 ? round(($wins / $closedTrades) * 100, 2) : 0,
                ];
            })
            ->values();
    }

    private function buildPairStats(Collection $trades): Collection
    {
        return $trades
            ->groupBy('pair')
            ->map(function (Collection $pairTrades, string $pair): array {
                $wins = $pairTrades->where('profit_loss', '>', 0)->count();
                $losses = $pairTrades->where('profit_loss', '<', 0)->count();
                $closedTrades = $wins + $losses;

                return [
                    'pair' => $pair,
                    'trades' => $pairTrades->count(),
                    'wins' => $wins,
                    'losses' => $losses,
                    'profit_loss' => round($pairTrades->sum('profit_loss'), 2),
                    'pips' => round($pairTrades->sum('pips'), 2),
                    'win_rate' => $closedTrades > 0 ? round(($wins / $closedTrades) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('profit_loss')
            ->values();
    }

    private function buildSourceStats(Collection $trades): Collection
    {
        return $trades
            ->groupBy('source')
            ->map(fn (Collection $sourceTrades, string $source): array => $this->statRow($source, $sourceTrades, 'source'))
            ->values();
    }

    private function buildDirectionStats(Collection $trades): Collection
    {
        return $trades
            ->groupBy('direction')
            ->map(function (Collection $directionTrades, int $direction): array {
                $label = match ($direction) {
                    1 => 'Buy',
                    2 => 'Sell',
                    default => 'N/A',
                };

                return $this->statRow($label, $directionTrades, 'direction');
            })
            ->values();
    }

    private function buildWeekdayStats(Collection $trades): Collection
    {
        $days = collect([
            0 => ['day' => 'Sunday', 'short_day' => 'Sun'],
            1 => ['day' => 'Monday', 'short_day' => 'Mon'],
            2 => ['day' => 'Tuesday', 'short_day' => 'Tue'],
            3 => ['day' => 'Wednesday', 'short_day' => 'Wed'],
            4 => ['day' => 'Thursday', 'short_day' => 'Thu'],
            5 => ['day' => 'Friday', 'short_day' => 'Fri'],
            6 => ['day' => 'Saturday', 'short_day' => 'Sat'],
        ]);

        $grouped = $trades
            ->filter(fn (array $trade): bool => $trade['closed_at'] instanceof Carbon)
            ->groupBy(fn (array $trade): int => (int) $trade['closed_at']->dayOfWeek);

        return $days->map(function (array $day, int $index) use ($grouped): array {
            $dayTrades = $grouped->get($index, collect());
            $wins = $dayTrades->where('profit_loss', '>', 0)->count();
            $losses = $dayTrades->where('profit_loss', '<', 0)->count();
            $closedTrades = $wins + $losses;

            return [
                'day' => $day['day'],
                'short_day' => $day['short_day'],
                'trades' => $dayTrades->count(),
                'wins' => $wins,
                'losses' => $losses,
                'profit_loss' => round($dayTrades->sum('profit_loss'), 2),
                'win_rate' => $closedTrades > 0 ? round(($wins / $closedTrades) * 100, 2) : 0,
            ];
        })->values();
    }

    private function buildSessionStats(Collection $trades): Collection
    {
        $sessions = collect([
            ['name' => 'Asia', 'start' => 0, 'end' => 8],
            ['name' => 'London', 'start' => 8, 'end' => 13],
            ['name' => 'New York', 'start' => 13, 'end' => 22],
            ['name' => 'Late Session', 'start' => 22, 'end' => 24],
        ]);

        return $sessions->map(function (array $session) use ($trades): array {
            $sessionTrades = $trades->filter(function (array $trade) use ($session): bool {
                if (! $trade['closed_at'] instanceof Carbon) {
                    return false;
                }

                $hour = (int) $trade['closed_at']->format('G');

                return $hour >= $session['start'] && $hour < $session['end'];
            });

            $wins = $sessionTrades->where('profit_loss', '>', 0)->count();
            $losses = $sessionTrades->where('profit_loss', '<', 0)->count();
            $closedTrades = $wins + $losses;

            return [
                'name' => $session['name'],
                'trades' => $sessionTrades->count(),
                'wins' => $wins,
                'losses' => $losses,
                'profit_loss' => round($sessionTrades->sum('profit_loss'), 2),
                'win_rate' => $closedTrades > 0 ? round(($wins / $closedTrades) * 100, 2) : 0,
            ];
        });
    }

    private function statRow(string $label, Collection $trades, string $labelKey): array
    {
        $wins = $trades->where('profit_loss', '>', 0)->count();
        $losses = $trades->where('profit_loss', '<', 0)->count();
        $closedTrades = $wins + $losses;

        return [
            $labelKey => $label,
            'trades' => $trades->count(),
            'profit_loss' => round($trades->sum('profit_loss'), 2),
            'pips' => round($trades->sum('pips'), 2),
            'win_rate' => $closedTrades > 0 ? round(($wins / $closedTrades) * 100, 2) : 0,
            'average_profit_loss' => $trades->count() > 0 ? round($trades->avg('profit_loss'), 2) : 0,
        ];
    }

    private function buildBehavioralProfile(array $summary, Collection $directionStats): array
    {
        $buyStats = $directionStats->firstWhere('direction', 'Buy');
        $sellStats = $directionStats->firstWhere('direction', 'Sell');
        $buyTrades = (int) ($buyStats['trades'] ?? 0);
        $sellTrades = (int) ($sellStats['trades'] ?? 0);
        $directionTotal = max(1, $buyTrades + $sellTrades);
        $buyShare = round(($buyTrades / $directionTotal) * 100, 1);
        $sellShare = round(($sellTrades / $directionTotal) * 100, 1);

        $label = 'Balanced';
        if ($buyShare >= 60) {
            $label = 'Long Biased';
        } elseif ($sellShare >= 60) {
            $label = 'Short Biased';
        }

        if ($summary['consistency_percent'] > 50 && $summary['net_profit_loss'] > 0) {
            $label = 'Concentrated';
        }

        return [
            'label' => $label,
            'buy_share' => $buyShare,
            'sell_share' => $sellShare,
            'buy_trades' => $buyTrades,
            'sell_trades' => $sellTrades,
        ];
    }

    private function buildLevelProfile(array $summary): array
    {
        $score = 0;
        $score += $summary['growth_percent'] >= 10 ? 30 : ($summary['growth_percent'] >= 5 ? 20 : ($summary['growth_percent'] > 0 ? 10 : 0));
        $score += $summary['max_drawdown_percent'] <= 5 ? 25 : ($summary['max_drawdown_percent'] <= 10 ? 15 : 0);
        $score += $summary['win_rate'] >= 60 ? 20 : ($summary['win_rate'] >= 50 ? 12 : 0);
        $score += is_numeric($summary['profit_factor']) && $summary['profit_factor'] >= 1.5 ? 15 : (is_numeric($summary['profit_factor']) && $summary['profit_factor'] >= 1 ? 8 : 0);
        $score += $summary['consistency_percent'] > 0 && $summary['consistency_percent'] <= 15 ? 10 : 0;

        $level = match (true) {
            $score >= 85 => 'Elite',
            $score >= 70 => 'Gold',
            $score >= 55 => 'Silver',
            $score >= 35 => 'Bronze',
            default => 'Evaluation',
        };

        return [
            'level' => $level,
            'score' => min(100, $score),
            'reward' => round(max(0, $summary['net_profit_loss']) * 0.8, 2),
            'highest_reward' => round(max(0, $summary['largest_win']) * 0.8, 2),
            'count' => $summary['profitable_days'],
        ];
    }

    private function buildRuleMonitor(array $summary): array
    {
        $startingBalance = max(0, (float) ($summary['current_balance'] - $summary['net_profit_loss']));
        $profitTargetPercent = 10;
        $maxDailyLossPercent = 5;
        $maxTotalLossPercent = 10;
        $consistencyLimitPercent = 15;
        $minProfitableDays = 3;

        $profitTargetAmount = $startingBalance > 0 ? round($startingBalance * ($profitTargetPercent / 100), 2) : 0;
        $profitProgress = $profitTargetAmount > 0 ? round(($summary['net_profit_loss'] / $profitTargetAmount) * 100, 2) : 0;
        $dailyLossUsage = $maxDailyLossPercent > 0 ? round(($summary['max_daily_loss_percent'] / $maxDailyLossPercent) * 100, 2) : 0;
        $totalLossUsage = $maxTotalLossPercent > 0 ? round(($summary['max_drawdown_percent'] / $maxTotalLossPercent) * 100, 2) : 0;
        $profitableDaysProgress = round(($summary['profitable_days'] / $minProfitableDays) * 100, 2);

        return [
            [
                'key' => 'profit_target',
                'title' => 'Profit Target',
                'value' => number_format($summary['net_profit_loss'], 2) . 'u / ' . number_format($profitTargetAmount, 2) . 'u',
                'progress' => $this->clampProgress($profitProgress),
                'status' => $summary['net_profit_loss'] >= $profitTargetAmount && $profitTargetAmount > 0 ? 'Passed' : 'Monitoring',
                'tone' => $summary['net_profit_loss'] >= $profitTargetAmount && $profitTargetAmount > 0 ? 'success' : 'primary',
                'caption' => $profitTargetPercent . '% target',
            ],
            [
                'key' => 'daily_loss',
                'title' => 'Max Daily Loss',
                'value' => number_format($summary['max_daily_loss_percent'], 2) . '% used',
                'progress' => $this->clampProgress($dailyLossUsage),
                'status' => $summary['max_daily_loss_percent'] > $maxDailyLossPercent ? 'Breached' : 'Within Limit',
                'tone' => $summary['max_daily_loss_percent'] > $maxDailyLossPercent ? 'danger' : 'success',
                'caption' => $maxDailyLossPercent . '% limit',
            ],
            [
                'key' => 'total_loss',
                'title' => 'Max Total Drawdown',
                'value' => number_format($summary['max_drawdown_percent'], 2) . '% used',
                'progress' => $this->clampProgress($totalLossUsage),
                'status' => $summary['max_drawdown_percent'] > $maxTotalLossPercent ? 'Breached' : 'Within Limit',
                'tone' => $summary['max_drawdown_percent'] > $maxTotalLossPercent ? 'danger' : 'success',
                'caption' => $maxTotalLossPercent . '% limit',
            ],
            [
                'key' => 'consistency',
                'title' => 'Consistency Rule',
                'value' => number_format($summary['consistency_percent'], 2) . '%',
                'progress' => $summary['consistency_percent'] > 0 ? $this->clampProgress(($summary['consistency_percent'] / $consistencyLimitPercent) * 100) : 0,
                'status' => $summary['net_profit_loss'] > 0 && $summary['consistency_percent'] <= $consistencyLimitPercent ? 'Passed' : 'Monitoring',
                'tone' => $summary['net_profit_loss'] > 0 && $summary['consistency_percent'] <= $consistencyLimitPercent ? 'success' : 'warning',
                'caption' => 'Max ' . $consistencyLimitPercent . '% of total profit from one day',
            ],
            [
                'key' => 'profitable_days',
                'title' => 'Profitable Days',
                'value' => $summary['profitable_days'] . ' / ' . $minProfitableDays,
                'progress' => $this->clampProgress($profitableDaysProgress),
                'status' => $summary['profitable_days'] >= $minProfitableDays ? 'Passed' : 'Monitoring',
                'tone' => $summary['profitable_days'] >= $minProfitableDays ? 'success' : 'primary',
                'caption' => 'Minimum target',
            ],
        ];
    }

    private function buildPerformanceProfile(array $summary, array $ruleMonitor): array
    {
        $hasBreach = collect($ruleMonitor)->contains(fn (array $rule): bool => $rule['status'] === 'Breached');
        $rulesPassed = collect($ruleMonitor)->where('status', 'Passed')->count();

        if ($summary['total_trades'] === 0) {
            return [
                'status' => 'No Data',
                'tone' => 'secondary',
                'headline' => 'No trading activity found',
                'detail' => 'Add journal records to activate the prop firm metrics.',
            ];
        }

        if ($hasBreach) {
            return [
                'status' => 'Risk Breach',
                'tone' => 'danger',
                'headline' => 'Account requires risk review',
                'detail' => 'One or more risk limits have moved outside the configured prop firm threshold.',
            ];
        }

        if ($rulesPassed >= 4) {
            return [
                'status' => 'Challenge Ready',
                'tone' => 'success',
                'headline' => 'Performance is aligned with prop firm rules',
                'detail' => 'Profitability, risk control, and consistency are moving in the right zone.',
            ];
        }

        return [
            'status' => 'Monitoring',
            'tone' => 'primary',
            'headline' => 'Account is under evaluation',
            'detail' => 'Keep building sample size while protecting daily and total drawdown.',
        ];
    }

    private function calculateDrawdown(Collection $trades, float $startingBalance): array
    {
        $equity = $startingBalance;
        $peak = $startingBalance;
        $maxDrawdown = 0;
        $maxDrawdownPercent = 0;

        foreach ($trades as $trade) {
            $equity += (float) $trade['profit_loss'];
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

    private function dailyPnL(Collection $trades): Collection
    {
        return $trades
            ->filter(fn (array $trade): bool => $trade['closed_at'] instanceof Carbon)
            ->groupBy(fn (array $trade): string => $trade['closed_at']->toDateString())
            ->map(fn (Collection $dayTrades): float => round((float) $dayTrades->sum('profit_loss'), 2))
            ->values();
    }

    private function bestWeekday(Collection $trades): string
    {
        $best = $this->buildWeekdayStats($trades)
            ->filter(fn (array $day): bool => $day['trades'] > 0)
            ->sortByDesc('profit_loss')
            ->first();

        return $best['short_day'] ?? 'N/A';
    }

    private function calculateStreaks(Collection $trades): array
    {
        $longestWinStreak = 0;
        $longestLossStreak = 0;
        $currentWinStreak = 0;
        $currentLossStreak = 0;

        foreach ($trades as $trade) {
            if ((float) $trade['profit_loss'] > 0) {
                $currentWinStreak++;
                $currentLossStreak = 0;
            } elseif ((float) $trade['profit_loss'] < 0) {
                $currentLossStreak++;
                $currentWinStreak = 0;
            } else {
                $currentWinStreak = 0;
                $currentLossStreak = 0;
            }

            $longestWinStreak = max($longestWinStreak, $currentWinStreak);
            $longestLossStreak = max($longestLossStreak, $currentLossStreak);
        }

        return [$longestWinStreak, $longestLossStreak];
    }

    private function clampProgress(float $value): float
    {
        return max(0, min(100, round($value, 2)));
    }

    private function capitalSummary(?int $userId, ?int $month, ?int $year): array
    {
        $query = Capital::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($month) {
            $query->whereMonth('deposit_date', $month);
        }

        if ($year) {
            $query->whereYear('deposit_date', $year);
        }

        $transactions = $query->get();
        $deposits = $transactions->where('type', 1)->sum('amount');
        $withdrawals = abs($transactions->where('type', 2)->sum('amount'));

        return [
            'total_deposits' => round((float) $deposits, 2),
            'total_withdrawals' => round((float) $withdrawals, 2),
            'net_capital' => round((float) $transactions->sum('amount'), 2),
            'transactions' => $transactions->count(),
        ];
    }

    private function traderIdsFromJournals(): Collection
    {
        $ids = collect();

        if (Schema::hasTable('trading_journals') && Schema::hasColumn('trading_journals', 'user_id')) {
            $ids = $ids->merge(
                $this->tradeOnly(TradingJournal::query(), 'trading_journals')
                    ->whereNotNull('user_id')
                    ->distinct()
                    ->pluck('user_id')
            );
        }

        if (Schema::hasTable('trading_journals_backup') && Schema::hasColumn('trading_journals_backup', 'user_id')) {
            $ids = $ids->merge(
                $this->tradeOnly(TradingJournalBackup::query(), 'trading_journals_backup')
                    ->whereNotNull('user_id')
                    ->distinct()
                    ->pluck('user_id')
            );
        }

        return $ids->filter()->unique()->values();
    }

    private function availableYears(?int $userId, string $source): Collection
    {
        $years = collect();

        if (in_array($source, ['all', 'current'], true)) {
            $years = $years->merge($this->yearsFromTable('trading_journals', TradingJournal::query(), $userId));
        }

        if (in_array($source, ['all', 'backup'], true) && Schema::hasTable('trading_journals_backup')) {
            $years = $years->merge($this->yearsFromTable('trading_journals_backup', TradingJournalBackup::query(), $userId));
        }

        return $years->filter()->unique()->sortDesc()->values();
    }

    private function yearsFromTable(string $table, Builder $query, ?int $userId): Collection
    {
        $dateColumn = $this->journalDateColumn($table);
        if (! $dateColumn) {
            return collect();
        }

        if ($userId && Schema::hasColumn($table, 'user_id')) {
            $query->where('user_id', $userId);
        }

        $query = $this->tradeOnly($query, $table);

        return $query
            ->selectRaw("YEAR({$dateColumn}) as year")
            ->whereNotNull($dateColumn)
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year);
    }

    private function journalDateColumn(string $table): ?string
    {
        foreach (['close_date', 'open_date', 'trade_date', 'created_at'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function tradeOnly(Builder $query, string $table): Builder
    {
        if (Schema::hasColumn($table, 'type')) {
            $query->where(function (Builder $query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            });
        }

        return $query;
    }

    private function normalizeDateFilter($value, int $min, int $max): ?int
    {
        if ($value === null || $value === '' || strtolower((string) $value) === 'all') {
            return null;
        }

        $value = (int) $value;

        return $value >= $min && $value <= $max ? $value : null;
    }

    private function normalizeTraderId($value): ?int
    {
        if ($value === null || $value === '' || strtolower((string) $value) === 'all') {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function parseDate($value): ?Carbon
    {
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
