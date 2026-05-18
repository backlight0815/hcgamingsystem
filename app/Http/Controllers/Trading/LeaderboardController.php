<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
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
        return self::ROLE_LABELS[$roleId] ?? 'Role ' . $roleId;
    }

    private function formatJournalDate(?TradingJournal $journal): ?string
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
