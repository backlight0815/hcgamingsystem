<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\User;
use App\Services\TradingJournalAnalytics;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    private const PROP_STAGE_PHASE_1 = 'phase1';

    private const PROP_STAGE_PHASE_2 = 'phase2';

    private const PROP_STAGE_FUNDED = 'funded';

    private const ROLE_LABELS = [
        1 => 'Admin',
        2 => 'Sub Admin',
        201 => 'Signal Provider',
        202 => 'Senior Signal Provider',
        350 => 'Dealer',
        501 => 'Market Analyst',
        502 => 'Signal Provider Management',
        700 => 'Customer',
        750 => 'Trader',
        760 => 'Leadership',
        770 => 'Recruiter',
    ];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $leaderboard = $this->buildLeaderboard($filters);
        $summary = $this->summary($leaderboard);
        $currentPlacement = $this->currentPlacement($leaderboard);
        $topThree = array_slice($leaderboard, 0, 3);
        $roleOptions = $this->roleOptions($filters);
        $roleBreakdown = $this->roleBreakdown($leaderboard);
        $availableYears = $this->availableYears();
        $monthOptions = $this->monthOptions();
        $viewerContext = $this->viewerContext((int) (Auth::user()->role_id ?? 0));

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Trading Leaderboard', 'url' => route('trading.leaderboard.index')],
        ];

        return view('traders.leaderboard', compact(
            'availableYears',
            'breadcrumbData',
            'currentPlacement',
            'filters',
            'leaderboard',
            'monthOptions',
            'roleBreakdown',
            'roleOptions',
            'summary',
            'topThree',
            'viewerContext'
        ));
    }

    public function filter(Request $request): View
    {
        return $this->index($request);
    }

    public function export(Request $request): RedirectResponse
    {
        return redirect()
            ->route('trading.leaderboard.index', $request->query())
            ->with([
                'message' => 'Leaderboard export is not configured yet. The live ranking is ready to review on this page.',
                'alert-type' => 'info',
            ]);
    }

    public function showTrader($id): View
    {
        $trader = User::findOrFail($id);
        $trades = TradingJournal::where('user_id', $id)
            ->where(function (Builder $query) {
                $query->where('type', 'trade')
                    ->orWhereNull('type');
            })
            ->orderByRaw('COALESCE(close_date, open_date, trade_date, created_at) asc')
            ->get();

        $stats = $this->calculateTraderStats($trader, $trades, $this->capitalSummary(collect([$id])));

        return view('traders.leaderboard_trader_details', [
            'trader' => $trader,
            'trades' => $trades,
            'journals' => $trades,
            'totalTrades' => $stats['total_trades'],
            'winRate' => $stats['win_rate'],
            'totalProfit' => $stats['total_profit'],
            'totalLoss' => $stats['total_loss'],
            'avgRRR' => $stats['avg_rrr'],
            'expectancy' => $stats['expectancy'],
        ]);
    }

    public function propFirm(Request $request): View
    {
        $filters = $this->propFirmFilters($request);
        $stageOptions = $this->propFirmStageOptions();
        $leaderboard = $this->buildPropFirmLeaderboard($filters);
        $summary = $this->propFirmSummary($leaderboard);
        $currentPlacement = $this->currentPlacement($leaderboard);
        $topThree = array_slice($leaderboard, 0, 3);
        $availableYears = $this->propFirmAvailableYears();
        $monthOptions = $this->monthOptions();
        $selectedStage = $filters['stage'];
        $selectedStageMeta = $stageOptions[$selectedStage];

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Prop Firm Leaderboard', 'url' => route('propfirm.leaderboard.index')],
        ];

        return view('traders.prop_firm_leaderboard', compact(
            'availableYears',
            'breadcrumbData',
            'currentPlacement',
            'filters',
            'leaderboard',
            'monthOptions',
            'selectedStage',
            'selectedStageMeta',
            'stageOptions',
            'summary',
            'topThree'
        ));
    }

    private function buildLeaderboard(array $filters): array
    {
        $journals = $this->journalQuery($filters)
            ->with('user')
            ->orderByRaw('COALESCE(close_date, open_date, trade_date, created_at) asc')
            ->get()
            ->filter(fn (TradingJournal $journal): bool => (bool) $journal->user)
            ->groupBy('user_id');

        $capitalSummary = $this->capitalSummary($journals->keys());
        $leaderboard = [];

        foreach ($journals as $userId => $userJournals) {
            $user = $userJournals->first()->user;
            $leaderboard[] = $this->calculateTraderStats($user, $userJournals, $capitalSummary);
        }

        usort($leaderboard, function (array $a, array $b): int {
            return [$b['score'], $b['growth'], $b['win_rate'], $b['total_trades']]
                <=> [$a['score'], $a['growth'], $a['win_rate'], $a['total_trades']];
        });

        foreach ($leaderboard as $index => $item) {
            $leaderboard[$index]['rank'] = $index + 1;
            $leaderboard[$index]['is_current_user'] = (int) $item['user']->id === (int) Auth::id();
        }

        return $leaderboard;
    }

    private function buildPropFirmLeaderboard(array $filters): array
    {
        $journals = $this->loadPropFirmJournals($filters)
            ->filter(fn ($journal): bool => (bool) ($journal->user ?? null))
            ->groupBy('user_id');

        $capitalSummary = $this->capitalSummary($journals->keys());
        $analytics = app(TradingJournalAnalytics::class);
        $leaderboard = [];

        foreach ($journals as $userJournals) {
            $user = $userJournals->first()->user;
            $leaderboard[] = $this->calculatePropFirmTraderStats(
                $user,
                $userJournals,
                $capitalSummary,
                $filters['stage'],
                $analytics
            );
        }

        usort($leaderboard, function (array $a, array $b): int {
            return [
                $b['score'],
                $b['profit_target_progress'],
                $b['net_pl'],
                $b['win_rate'],
                $b['total_trades'],
            ] <=> [
                $a['score'],
                $a['profit_target_progress'],
                $a['net_pl'],
                $a['win_rate'],
                $a['total_trades'],
            ];
        });

        foreach ($leaderboard as $index => $item) {
            $leaderboard[$index]['rank'] = $index + 1;
            $leaderboard[$index]['is_current_user'] = (int) $item['user']->id === (int) Auth::id();
        }

        return $leaderboard;
    }

    private function calculatePropFirmTraderStats(
        User $user,
        Collection $journals,
        array $capitalSummary,
        string $stage,
        TradingJournalAnalytics $analytics
    ): array {
        $stage = $this->normalizePropFirmStage($stage);
        $baseStats = $this->calculateTraderStats($user, $journals, $capitalSummary);
        $startingBalance = $this->propFirmStartingBalance($user, $capitalSummary, $stage);
        $netProfitLoss = (float) $journals->sum('profit_loss');
        $growthPercent = $startingBalance > 0 ? round(($netProfitLoss / $startingBalance) * 100, 2) : 0.0;
        $dailyPnL = $analytics->dailyProfitLoss($journals);
        $worstDayPnL = $dailyPnL->isNotEmpty() ? (float) $dailyPnL->min() : 0.0;
        $dailyLossPercent = $startingBalance > 0 ? round((abs(min(0, $worstDayPnL)) / $startingBalance) * 100, 2) : 0.0;
        $totalLossPercent = $startingBalance > 0 ? round((abs(min(0, $netProfitLoss)) / $startingBalance) * 100, 2) : 0.0;
        $targetPercent = $this->propFirmProfitTargetPercent($stage);
        $targetAmount = $targetPercent > 0 && $startingBalance > 0 ? $startingBalance * ($targetPercent / 100) : 0.0;
        $targetProgress = $targetAmount > 0 ? round(($netProfitLoss / $targetAmount) * 100, 2) : max(0, $growthPercent);
        $profitTargetPassed = $targetAmount > 0 ? $netProfitLoss >= $targetAmount : $netProfitLoss > 0;
        $maxDailyLossBreached = $dailyLossPercent > 5.0;
        $maxTotalLossBreached = $totalLossPercent > 10.0;
        $bestDayRule = $analytics->bestDayRule($journals);
        unset($bestDayRule['daily_profit_loss']);
        $grossProfitRule = $analytics->grossProfitRule($journals, $startingBalance);
        $profitableDayRule = $analytics->profitableDayRule($journals, 3);
        $ruleScoreAdjustment = $this->propFirmRuleScoreAdjustment(
            $stage,
            $profitTargetPassed,
            (bool) data_get($profitableDayRule, 'has_profitable_day', false),
            (bool) data_get($grossProfitRule, 'passed', false),
            (bool) data_get($bestDayRule, 'passed', false),
            $maxDailyLossBreached,
            $maxTotalLossBreached,
            0.0
        );
        $score = max(0, min(100, round((float) $baseStats['score'] + $ruleScoreAdjustment, 2)));

        return array_merge($baseStats, [
            'stage' => $stage,
            'stage_label' => $this->propFirmStageOptions()[$stage]['short_label'],
            'score' => $score,
            'rating' => $this->ratingForScore($score, (int) $baseStats['total_trades']),
            'starting_balance' => round($startingBalance, 2),
            'growth' => $growthPercent,
            'profit_target_percent' => $targetPercent,
            'profit_target_amount' => round($targetAmount, 2),
            'profit_target_progress' => $targetProgress,
            'profit_target_passed' => $profitTargetPassed,
            'daily_loss_percent' => $dailyLossPercent,
            'total_loss_percent' => $totalLossPercent,
            'daily_loss_breached' => $maxDailyLossBreached,
            'total_loss_breached' => $maxTotalLossBreached,
            'best_day_rule' => $bestDayRule,
            'gross_profit_rule' => $grossProfitRule,
            'profitable_day_rule' => $profitableDayRule,
            'behaviour_score' => 0.0,
            'behaviour_tier' => 'Use journal review',
            'rule_score_adjustment' => $ruleScoreAdjustment,
            'rule_status' => $this->propFirmRuleStatus($profitTargetPassed, $maxDailyLossBreached, $maxTotalLossBreached, $stage),
            'last_trade_at' => $this->formatJournalDate($journals->last()),
            'review_status' => (string) ($user->prop_firm_review_status ?? 'none'),
            'funded_status' => (int) ($user->funded_status ?? 0),
        ]);
    }

    private function loadPropFirmJournals(array $filters): Collection
    {
        $stage = $this->normalizePropFirmStage($filters['stage'] ?? self::PROP_STAGE_PHASE_1);
        $journals = collect();
        $currentStageUserIds = $this->currentPropFirmStageUserIds($stage);
        $allPropFirmUserIds = $this->propFirmTraderUserIds();

        $currentQuery = TradingJournal::with('user')
            ->whereIn('user_id', $currentStageUserIds->all());
        $this->applyTradeOnlyFilter($currentQuery);
        $this->applyPropFirmDateFilters($currentQuery, $filters);

        $journals = $journals->merge(
            $this->tagPropFirmJournals($currentQuery->get(), 'journal', $stage)
        );

        if (Schema::hasTable('trading_journals_backup')) {
            $backupQuery = TradingJournalBackup::with('user')
                ->whereIn('user_id', $allPropFirmUserIds->all());
            $this->applyTradeOnlyFilter($backupQuery);
            $this->applyBackupPropFirmStageFilter($backupQuery, $stage);
            $this->applyPropFirmDateFilters($backupQuery, $filters);

            $journals = $journals->merge(
                $this->tagPropFirmJournals($backupQuery->get(), 'backup', $stage)
            );
        }

        return $this->sortPropFirmJournals($journals);
    }

    private function calculateTraderStats(User $user, $journals, array $capitalSummary): array
    {
        $userId = (int) $user->id;
        $totalTrades = $journals->count();
        $capital = $capitalSummary[$userId] ?? ['deposits' => 0.0, 'withdrawals' => 0.0];
        $totalDeposits = (float) $capital['deposits'];
        $totalWithdrawals = abs((float) $capital['withdrawals']);
        $netPL = (float) $journals->sum('profit_loss');
        $currentBalance = $totalDeposits + $netPL - $totalWithdrawals;

        $winTrades = $journals->where('profit_loss', '>', 0);
        $lossTrades = $journals->where('profit_loss', '<', 0);
        $breakevenTrades = $journals->where('profit_loss', '=', 0);
        $closedTrades = $winTrades->count() + $lossTrades->count();

        $winRate = $closedTrades > 0
            ? round(($winTrades->count() / $closedTrades) * 100, 2)
            : 0.0;

        $totalProfit = (float) $winTrades->sum('profit_loss');
        $totalLoss = abs((float) $lossTrades->sum('profit_loss'));
        $averageRRR = $this->averageRrr($totalProfit, $totalLoss);

        $growthPercent = $totalDeposits > 0
            ? round(($netPL / $totalDeposits) * 100, 2)
            : 0.0;

        $drawdownPercent = $this->maxDrawdownPercent($journals, $totalDeposits);
        $expectancy = $this->expectancy($journals, $winTrades, $lossTrades, $totalTrades);
        [$consistencyPercent, $consistencyGrade, $consistencyPoints] = $this->consistency($journals);
        [$score, $rating] = $this->score(
            $totalTrades,
            $winRate,
            $averageRRR,
            $growthPercent,
            $drawdownPercent,
            $expectancy,
            $consistencyPoints
        );

        return [
            'user' => $user,
            'role_id' => (int) ($user->role_id ?? 0),
            'role_label' => $this->roleLabel((int) ($user->role_id ?? 0)),
            'rank' => null,
            'total_trades' => $totalTrades,
            'win_trades' => $winTrades->count(),
            'loss_trades' => $lossTrades->count(),
            'breakeven_trades' => $breakevenTrades->count(),
            'win_rate' => $winRate,
            'avg_rrr' => $averageRRR,
            'growth' => $growthPercent,
            'drawdown' => $drawdownPercent,
            'expectancy' => $expectancy,
            'consistency' => $consistencyGrade,
            'consistency_percent' => $consistencyPercent,
            'score' => $score,
            'rating' => $rating,
            'net_pl' => round($netPL, 2),
            'total_profit' => round($totalProfit, 2),
            'total_loss' => round($totalLoss, 2),
            'deposits' => round($totalDeposits, 2),
            'withdrawals' => round($totalWithdrawals, 2),
            'current_balance' => round($currentBalance, 2),
            'confidence' => $this->confidenceLabel($totalTrades),
            'last_trade_at' => $this->formatJournalDate($journals->last()),
        ];
    }

    private function journalQuery(array $filters): Builder
    {
        return TradingJournal::query()
            ->where(function (Builder $query) {
                $query->where('type', 'trade')
                    ->orWhereNull('type');
            })
            ->when($filters['year'] !== 'all', function (Builder $query) use ($filters) {
                $query->whereYear(DB::raw('COALESCE(close_date, open_date, trade_date, created_at)'), (int) $filters['year']);
            })
            ->when($filters['month'] !== 'all', function (Builder $query) use ($filters) {
                $query->whereMonth(DB::raw('COALESCE(close_date, open_date, trade_date, created_at)'), (int) $filters['month']);
            })
            ->when($filters['role'] !== 'all', function (Builder $query) use ($filters) {
                $query->whereHas('user', function (Builder $userQuery) use ($filters) {
                    $userQuery->where('role_id', (int) $filters['role']);
                });
            });
    }

    private function applyTradeOnlyFilter(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->where('type', 'trade')->orWhereNull('type');
        });
    }

    private function applyPropFirmDateFilters(Builder $query, array $filters): void
    {
        $dateExpression = DB::raw('COALESCE(close_date, open_date, created_at)');

        if (($filters['year'] ?? 'all') !== 'all') {
            $query->whereYear($dateExpression, (int) $filters['year']);
        }

        if (($filters['month'] ?? 'all') !== 'all') {
            $query->whereMonth($dateExpression, (int) $filters['month']);
        }
    }

    private function applyCurrentPropFirmStageFilter(Builder $query, string $stage): void
    {
        match ($this->normalizePropFirmStage($stage)) {
            self::PROP_STAGE_PHASE_1 => $query
                ->where(function (Builder $query): void {
                    $query->whereNull('prop_firm_phase')->orWhere('prop_firm_phase', '<=', 1);
                })
                ->where(function (Builder $query): void {
                    $query->whereNull('funded_status')->orWhere('funded_status', '!=', 1);
                }),
            self::PROP_STAGE_PHASE_2 => $query
                ->where('prop_firm_phase', 2)
                ->where(function (Builder $query): void {
                    $query->whereNull('funded_status')->orWhere('funded_status', '!=', 1);
                }),
            self::PROP_STAGE_FUNDED => $query->where(function (Builder $query): void {
                $query->where('prop_firm_phase', '>=', 3)
                    ->orWhere('funded_status', 1)
                    ->orWhere('prop_firm_review_status', 'funded_approved');
            }),
        };
    }

    private function currentPropFirmStageUserIds(string $stage): Collection
    {
        $query = User::where('role_id', 750);

        match ($this->normalizePropFirmStage($stage)) {
            self::PROP_STAGE_PHASE_1 => $query->where('prop_firm_phase', '<=', 1),
            self::PROP_STAGE_PHASE_2 => $query->where('prop_firm_phase', 2),
            self::PROP_STAGE_FUNDED => $query->where('prop_firm_phase', '>=', 3),
        };

        return $query->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    private function propFirmTraderUserIds(): Collection
    {
        return User::where('role_id', 750)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    private function applyBackupPropFirmStageFilter(Builder $query, string $stage): void
    {
        if (! Schema::hasColumn('trading_journals_backup', 'prop_firm_phase')) {
            if ($stage !== self::PROP_STAGE_PHASE_1) {
                $query->whereRaw('1 = 0');
            }

            return;
        }

        match ($this->normalizePropFirmStage($stage)) {
            self::PROP_STAGE_PHASE_1 => $query->where('prop_firm_phase', 1),
            self::PROP_STAGE_PHASE_2 => $query->where('prop_firm_phase', 2),
            self::PROP_STAGE_FUNDED => $query->where('prop_firm_phase', 3),
        };
    }

    private function tagPropFirmJournals(Collection $journals, string $source, string $stage): Collection
    {
        return $journals->map(function ($journal) use ($source, $stage) {
            $journal->setAttribute('source', $source);
            $journal->setAttribute('prop_firm_leaderboard_stage', $stage);
            $journal->setAttribute('behavior_trade_key', $source.':'.$journal->id);

            return $journal;
        });
    }

    private function sortPropFirmJournals(Collection $journals): Collection
    {
        return $journals
            ->sortBy(function ($journal): int {
                $date = $journal->close_date ?? $journal->open_date ?? $journal->created_at;

                return $date ? Carbon::parse($date)->timestamp : 0;
            })
            ->values();
    }

    private function capitalSummary($userIds): array
    {
        $userIds = collect($userIds)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $summary = [];

        Capital::query()
            ->whereIn('user_id', $userIds)
            ->select('user_id', 'type', DB::raw('SUM(amount) as total'))
            ->groupBy('user_id', 'type')
            ->get()
            ->each(function ($row) use (&$summary): void {
                $userId = (int) $row->user_id;
                $summary[$userId] ??= ['deposits' => 0.0, 'withdrawals' => 0.0];

                if ((int) $row->type === 1) {
                    $summary[$userId]['deposits'] = (float) $row->total;
                }

                if ((int) $row->type === 2) {
                    $summary[$userId]['withdrawals'] = abs((float) $row->total);
                }
            });

        return $summary;
    }

    private function averageRrr(float $totalProfit, float $totalLoss): float|string
    {
        if ($totalProfit > 0 && $totalLoss == 0.0) {
            return 'Perfect';
        }

        return ($totalLoss > 0 && $totalProfit > 0)
            ? round($totalProfit / $totalLoss, 2)
            : 0.0;
    }

    private function maxDrawdownPercent($journals, float $initialCapital): float
    {
        if ($initialCapital <= 0) {
            return 0.0;
        }

        $balance = $initialCapital;
        $peak = max($initialCapital, 1);
        $maxDrawdown = 0.0;

        foreach ($journals as $journal) {
            $balance += (float) $journal->profit_loss;
            $peak = max($peak, $balance);

            if ($peak > 0) {
                $maxDrawdown = max($maxDrawdown, (($peak - $balance) / $peak) * 100);
            }
        }

        return round($maxDrawdown, 2);
    }

    private function expectancy($journals, $winTrades, $lossTrades, int $totalTrades): float
    {
        if ($totalTrades === 0) {
            return 0.0;
        }

        $averageWin = $winTrades->count() > 0 ? (float) $winTrades->avg('profit_loss') : 0.0;
        $averageLoss = $lossTrades->count() > 0 ? abs((float) $lossTrades->avg('profit_loss')) : 0.0;
        $winRateDecimal = $winTrades->count() / $totalTrades;
        $lossRateDecimal = $lossTrades->count() / $totalTrades;

        return round(($winRateDecimal * $averageWin) - ($lossRateDecimal * $averageLoss), 2);
    }

    private function consistency($journals): array
    {
        $profits = $journals->pluck('profit_loss')
            ->map(fn ($value): float => (float) $value)
            ->all();

        $losses = array_filter($profits, fn (float $profitLoss): bool => $profitLoss < 0);
        $averageLoss = count($losses) > 0 ? abs(array_sum($losses) / count($losses)) : 0.0;

        if ($averageLoss <= 0 || count($profits) === 0) {
            return [100.0, 'A+', 25];
        }

        $rMultiples = array_map(fn (float $profitLoss): float => $profitLoss / $averageLoss, $profits);
        $averageR = array_sum($rMultiples) / count($rMultiples);
        $variance = count($rMultiples) > 1
            ? array_sum(array_map(fn (float $value): float => pow($value - $averageR, 2), $rMultiples)) / (count($rMultiples) - 1)
            : 0.0;
        $standardDeviation = round(sqrt($variance), 2);

        [$points, $grade] = match (true) {
            $standardDeviation <= 0.5 => [25, 'A+'],
            $standardDeviation <= 1.0 => [20, 'A'],
            $standardDeviation <= 1.5 => [15, 'A-'],
            $standardDeviation <= 2.0 => [10, 'B'],
            $standardDeviation <= 2.5 => [5, 'C'],
            $standardDeviation <= 3.0 => [2, 'D'],
            $standardDeviation <= 3.5 => [1, 'E'],
            default => [0, 'F'],
        };

        $percent = round(max(0, 100 * (3.5 - $standardDeviation) / 3.5), 2);

        return [$percent, $grade, $points];
    }

    private function score(
        int $totalTrades,
        float $winRate,
        float|string $averageRRR,
        float $growthPercent,
        float $drawdownPercent,
        float $expectancy,
        int $consistencyPoints
    ): array {
        if ($totalTrades === 0) {
            return [0.0, 'N/A'];
        }

        $winRatePoints = match (true) {
            $winRate >= 75 => 30,
            $winRate >= 65 => 25,
            $winRate >= 60 => 25,
            $winRate >= 55 => 20,
            $winRate >= 50 => 17,
            $winRate >= 45 => 15,
            $winRate >= 35 => 10,
            $winRate > 0 => 5,
            default => 0,
        };

        if ($averageRRR === 'Perfect') {
            $rrrPoints = 30;
        } else {
            $rrrPoints = match (true) {
                $averageRRR >= 5.75 => 30,
                $averageRRR >= 3.45 => 25,
                $averageRRR >= 2.30 => 20,
                $averageRRR >= 1.73 => 15,
                $averageRRR >= 1.15 => 10,
                $averageRRR > 0 => 5,
                default => 0,
            };
        }

        $growthPoints = match (true) {
            $growthPercent >= 15 => 10,
            $growthPercent >= 7.5 => 7,
            $growthPercent >= 4.5 => 5,
            $growthPercent >= 1.5 => 3,
            $growthPercent > 0 => 1,
            default => 0,
        };

        $drawdownPenalty = match (true) {
            $drawdownPercent > 90 => -35,
            $drawdownPercent > 80 => -20,
            $drawdownPercent > 60 => -15,
            $drawdownPercent > 40 => -10,
            $drawdownPercent > 30 => -8,
            $drawdownPercent > 20 => -6,
            $drawdownPercent > 10 => -4,
            $drawdownPercent > 5 => -2,
            $drawdownPercent > 2 => -1,
            default => 0,
        };

        $expectancyPoints = match (true) {
            $expectancy >= 40 => 10,
            $expectancy >= 20 => 7,
            $expectancy >= 10 => 5,
            $expectancy >= 0 => 0,
            $expectancy >= -10 => -1,
            $expectancy >= -20 => -2,
            default => 0,
        };

        $score = max(0, min(100, round(
            $winRatePoints + $rrrPoints + $growthPoints + $consistencyPoints + $expectancyPoints + $drawdownPenalty,
            2
        )));

        $rating = match (true) {
            $score >= 95 => 'S',
            $score >= 90 => 'A+',
            $score >= 85 => 'A',
            $score >= 80 => 'A-',
            $score >= 75 => 'B+',
            $score >= 70 => 'B',
            $score >= 65 => 'B-',
            $score >= 60 => 'C+',
            $score >= 55 => 'C',
            $score >= 50 => 'C-',
            $score >= 45 => 'D',
            $score >= 40 => 'D-',
            $score >= 30 => 'E',
            default => 'F',
        };

        return [$score, $rating];
    }

    private function propFirmStartingBalance(User $user, array $capitalSummary, string $stage): float
    {
        $userId = (int) $user->id;
        $capital = $capitalSummary[$userId] ?? ['deposits' => 0.0];
        $deposits = (float) ($capital['deposits'] ?? 0);

        return $deposits;
    }

    private function propFirmProfitTargetPercent(string $stage): float
    {
        return match ($this->normalizePropFirmStage($stage)) {
            self::PROP_STAGE_PHASE_1 => 10.0,
            self::PROP_STAGE_PHASE_2 => 5.0,
            self::PROP_STAGE_FUNDED => 0.0,
        };
    }

    private function propFirmRuleScoreAdjustment(
        string $stage,
        bool $profitTargetPassed,
        bool $profitableDaysPassed,
        bool $grossProfitRulePassed,
        bool $bestDayRulePassed,
        bool $dailyLossBreached,
        bool $totalLossBreached,
        float $behaviourScore
    ): float {
        $stage = $this->normalizePropFirmStage($stage);
        $adjustment = 0.0;

        if ($stage === self::PROP_STAGE_FUNDED) {
            $adjustment += $profitTargetPassed ? 8 : 0;
        } else {
            $adjustment += $profitTargetPassed ? 10 : 0;
            $adjustment += $profitableDaysPassed ? 5 : 0;
            $adjustment += $grossProfitRulePassed ? 5 : 0;
        }

        $adjustment += $bestDayRulePassed ? 3 : -5;

        if ($dailyLossBreached) {
            $adjustment -= 20;
        }

        if ($totalLossBreached) {
            $adjustment -= 25;
        }

        if ($behaviourScore >= 80) {
            $adjustment -= 15;
        } elseif ($behaviourScore >= 65) {
            $adjustment -= 8;
        }

        return round($adjustment, 2);
    }

    private function propFirmRuleStatus(bool $profitTargetPassed, bool $dailyLossBreached, bool $totalLossBreached, string $stage): string
    {
        if ($totalLossBreached) {
            return 'Total loss breach';
        }

        if ($dailyLossBreached) {
            return 'Daily loss breach';
        }

        if ($stage === self::PROP_STAGE_FUNDED) {
            return $profitTargetPassed ? 'Profitable funded account' : 'Funded account active';
        }

        return $profitTargetPassed ? 'Profit target reached' : 'Evaluation in progress';
    }

    private function ratingForScore(float $score, int $totalTrades): string
    {
        if ($totalTrades === 0) {
            return 'N/A';
        }

        return match (true) {
            $score >= 95 => 'S',
            $score >= 90 => 'A+',
            $score >= 85 => 'A',
            $score >= 80 => 'A-',
            $score >= 75 => 'B+',
            $score >= 70 => 'B',
            $score >= 65 => 'B-',
            $score >= 60 => 'C+',
            $score >= 55 => 'C',
            $score >= 50 => 'C-',
            $score >= 45 => 'D',
            $score >= 40 => 'D-',
            $score >= 30 => 'E',
            default => 'F',
        };
    }

    private function filters(Request $request): array
    {
        $role = $request->input('role', 'all');
        $month = $request->input('month', 'all');
        $year = $request->input('year', 'all');

        return [
            'role' => $role === 'all' ? 'all' : (string) (int) $role,
            'month' => $month === 'all' ? 'all' : str_pad((string) max(1, min(12, (int) $month)), 2, '0', STR_PAD_LEFT),
            'year' => $year === 'all' ? 'all' : (string) (int) $year,
        ];
    }

    private function propFirmFilters(Request $request): array
    {
        $month = $request->input('month', 'all');
        $year = $request->input('year', 'all');

        return [
            'stage' => $this->normalizePropFirmStage($request->input('stage')),
            'month' => $month === 'all' ? 'all' : str_pad((string) max(1, min(12, (int) $month)), 2, '0', STR_PAD_LEFT),
            'year' => $year === 'all' ? 'all' : (string) (int) $year,
        ];
    }

    private function normalizePropFirmStage($stage): string
    {
        $stage = strtolower((string) ($stage ?: self::PROP_STAGE_PHASE_1));

        return array_key_exists($stage, $this->propFirmStageOptions())
            ? $stage
            : self::PROP_STAGE_PHASE_1;
    }

    private function propFirmStageOptions(): array
    {
        return [
            self::PROP_STAGE_PHASE_1 => [
                'label' => 'Phase 1 Leaderboard',
                'short_label' => 'Phase 1',
                'description' => 'Ranks prop firm traders by Phase 1 evaluation performance, using current and archived Phase 1 journals.',
                'target' => '10% target',
            ],
            self::PROP_STAGE_PHASE_2 => [
                'label' => 'Phase 2 Leaderboard',
                'short_label' => 'Phase 2',
                'description' => 'Ranks prop firm traders by Phase 2 evaluation performance, using current and archived Phase 2 journals.',
                'target' => '5% target',
            ],
            self::PROP_STAGE_FUNDED => [
                'label' => 'Funded Account Leaderboard',
                'short_label' => 'Funded',
                'description' => 'Ranks approved funded traders by funded account performance and rule health.',
                'target' => 'Live funded performance',
            ],
        ];
    }

    private function availableYears(): array
    {
        return TradingJournal::query()
            ->whereRaw('COALESCE(close_date, open_date, trade_date, created_at) IS NOT NULL')
            ->selectRaw('YEAR(COALESCE(close_date, open_date, trade_date, created_at)) as journal_year')
            ->distinct()
            ->orderByDesc('journal_year')
            ->pluck('journal_year')
            ->filter()
            ->map(fn ($year): int => (int) $year)
            ->values()
            ->all();
    }

    private function propFirmAvailableYears(): array
    {
        $years = collect();

        $years = $years->merge(
            TradingJournal::query()
                ->whereRaw('COALESCE(close_date, open_date, created_at) IS NOT NULL')
                ->selectRaw('YEAR(COALESCE(close_date, open_date, created_at)) as journal_year')
                ->distinct()
                ->pluck('journal_year')
        );

        if (Schema::hasTable('trading_journals_backup')) {
            $years = $years->merge(
                TradingJournalBackup::query()
                    ->whereRaw('COALESCE(close_date, open_date, created_at) IS NOT NULL')
                    ->selectRaw('YEAR(COALESCE(close_date, open_date, created_at)) as journal_year')
                    ->distinct()
                    ->pluck('journal_year')
            );
        }

        return $years
            ->filter()
            ->map(fn ($year): int => (int) $year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    private function monthOptions(): array
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[str_pad((string) $month, 2, '0', STR_PAD_LEFT)] = Carbon::create(2026, $month, 1)->format('F');
        }

        return $months;
    }

    private function roleOptions(array $filters): array
    {
        $roleIds = TradingJournal::query()
            ->join('users', 'users.id', '=', 'trading_journals.user_id')
            ->where(function ($query) {
                $query->where('trading_journals.type', 'trade')
                    ->orWhereNull('trading_journals.type');
            })
            ->when($filters['year'] !== 'all', function ($query) use ($filters) {
                $query->whereYear(DB::raw('COALESCE(trading_journals.close_date, trading_journals.open_date, trading_journals.trade_date, trading_journals.created_at)'), (int) $filters['year']);
            })
            ->when($filters['month'] !== 'all', function ($query) use ($filters) {
                $query->whereMonth(DB::raw('COALESCE(trading_journals.close_date, trading_journals.open_date, trading_journals.trade_date, trading_journals.created_at)'), (int) $filters['month']);
            })
            ->distinct()
            ->pluck('users.role_id')
            ->filter()
            ->map(fn ($roleId): int => (int) $roleId)
            ->sort()
            ->values();

        return $roleIds
            ->mapWithKeys(fn (int $roleId): array => [$roleId => $this->roleLabel($roleId)])
            ->all();
    }

    private function summary(array $leaderboard): array
    {
        $collection = collect($leaderboard);

        return [
            'ranked_members' => $collection->count(),
            'total_trades' => (int) $collection->sum('total_trades'),
            'average_score' => round((float) $collection->avg('score'), 2),
            'average_win_rate' => round((float) $collection->avg('win_rate'), 2),
            'best_score' => round((float) ($collection->first()['score'] ?? 0), 2),
            'top_rating' => $collection->first()['rating'] ?? 'N/A',
        ];
    }

    private function propFirmSummary(array $leaderboard): array
    {
        $collection = collect($leaderboard);

        return [
            'ranked_traders' => $collection->count(),
            'total_trades' => (int) $collection->sum('total_trades'),
            'average_score' => round((float) $collection->avg('score'), 2),
            'average_progress' => round((float) $collection->avg('profit_target_progress'), 2),
            'total_net_pl' => round((float) $collection->sum('net_pl'), 2),
            'rule_breaches' => $collection
                ->filter(fn (array $item): bool => (bool) $item['daily_loss_breached'] || (bool) $item['total_loss_breached'])
                ->count(),
            'top_rating' => $collection->first()['rating'] ?? 'N/A',
            'best_score' => round((float) ($collection->first()['score'] ?? 0), 2),
        ];
    }

    private function currentPlacement(array $leaderboard): ?array
    {
        foreach ($leaderboard as $item) {
            if ((int) $item['user']->id === (int) Auth::id()) {
                return $item;
            }
        }

        return null;
    }

    private function roleBreakdown(array $leaderboard): array
    {
        return collect($leaderboard)
            ->groupBy('role_id')
            ->map(function ($items, $roleId): array {
                return [
                    'role_id' => (int) $roleId,
                    'label' => $this->roleLabel((int) $roleId),
                    'count' => $items->count(),
                    'average_score' => round((float) $items->avg('score'), 2),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
    }

    private function viewerContext(int $roleId): array
    {
        return match ($roleId) {
            1, 2 => [
                'eyebrow' => 'Administration View',
                'headline' => 'Trading Leaderboard',
                'description' => 'Review ranked trading performance across traders, recruiters, and leadership with the active filters applied.',
            ],
            201, 202 => [
                'eyebrow' => 'Signal Provider View',
                'headline' => 'Trading Leaderboard',
                'description' => 'Compare member execution quality, consistency, risk profile, and trade sample size before planning follow-up signals.',
            ],
            501 => [
                'eyebrow' => 'Market Analyst View',
                'headline' => 'Trading Leaderboard',
                'description' => 'Use ranked trader outcomes to understand which market conditions are being executed with stronger discipline.',
            ],
            350, 700 => [
                'eyebrow' => 'Member View',
                'headline' => 'Trading Leaderboard',
                'description' => 'View the trading performance table and understand where ranked members currently stand.',
            ],
            760 => [
                'eyebrow' => 'Leadership View',
                'headline' => 'Trading Leaderboard',
                'description' => 'Track your own standing while monitoring the consistency and progress of trading members.',
            ],
            770 => [
                'eyebrow' => 'Recruiter View',
                'headline' => 'Trading Leaderboard',
                'description' => 'Review ranked trading performance and identify where members may need coaching before live scaling.',
            ],
            750 => [
                'eyebrow' => 'Trader View',
                'headline' => 'Trading Leaderboard',
                'description' => 'See your rank, rating, risk profile, and how your trading journal compares with other ranked members.',
            ],
            default => [
                'eyebrow' => 'Trading View',
                'headline' => 'Trading Leaderboard',
                'description' => 'Review ranked trading performance across the active trading community.',
            ],
        };
    }

    private function confidenceLabel(int $totalTrades): string
    {
        return match (true) {
            $totalTrades >= 50 => 'High confidence',
            $totalTrades >= 30 => 'Reliable sample',
            $totalTrades >= 15 => 'Building sample',
            default => 'Low sample',
        };
    }

    private function roleLabel(int $roleId): string
    {
        return self::ROLE_LABELS[$roleId] ?? 'Role '.$roleId;
    }

    private function formatJournalDate($journal): ?string
    {
        if (! $journal) {
            return null;
        }

        $date = $journal->close_date ?? $journal->open_date ?? $journal->trade_date ?? $journal->created_at;

        if (! $date) {
            return null;
        }

        return $date instanceof Carbon
            ? $date->format('Y-m-d')
            : Carbon::parse($date)->format('Y-m-d');
    }
}
