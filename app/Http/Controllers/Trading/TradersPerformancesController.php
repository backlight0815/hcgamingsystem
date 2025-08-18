<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TradingJournal;
use App\Models\Capital;
use App\Exports\AdminTradingJournalExport;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class TradersPerformancesController extends Controller
{
    /**
     * Show performance statistics for a selected trader.
     */
  public function tradersJournals(Request $request)
{
    $selectedMonth = $request->input('month');
    $selectedYear = $request->input('year');
    $selectedTraderId = $request->input('user_id');
    $selectedTrader = $selectedTraderId ? User::find($selectedTraderId) : null;

    $traders = User::where('role_id', 750)->get(); // role_id 750 = Trader
    $breadcrumbData = [['label' => 'Trader Journals', 'url' => route('admin.trader.journals.index')]];

    // Default values
    $journals = collect();
    $capitals = collect();
    $totalTrades = $totalProfit = $totalLoss = $growthPercent = $drawdownPercent = $currentBalance = 0;
    $winRate = $averageRRR = $expectancy = $stdDeviation = $totalScore = 0;
    $rating = $winRateGrade = $rrrGrade = $growthGrade = $drawdownGrade = $consistencyGrade = $expectancyGrade = 'N/A';
    $winRatePoints = $rrrPoints = $growthPoints = $drawdownPenalty = $consistencyPoints = $expectancyPoints = 0;
    $totalDeposits = $totalWithdrawals = 0;
    $evaluation = [];

    if ($selectedTrader) {
        // Fetch journals
        $journalsQuery = TradingJournal::where('user_id', $selectedTrader->id);
        if ($selectedMonth && $selectedYear) {
            $journalsQuery->whereMonth('open_date', $selectedMonth)
                          ->whereYear('open_date', $selectedYear);
        }
        $journals = $journalsQuery->latest()->get();

        // Capital summary
        $totalDeposits = Capital::where('user_id', $selectedTrader->id)
            ->where('type', 1)->sum('amount');
        $totalWithdrawals = abs(Capital::where('user_id', $selectedTrader->id)
            ->where('type', 2)->sum('amount'));

$initialCapital = $totalDeposits ?: 0;
        $capitals = Capital::where('user_id', $selectedTrader->id)->get();

        $netPL = $journals->sum('profit_loss');
        $currentBalance = $initialCapital + $netPL - $totalWithdrawals;

        $totalTrades = $journals->count();

        // Win/Loss trades
        $winTrades = $journals->where('profit_loss', '>', 0);
        $lossTrades = $journals->where('profit_loss', '<', 0);
        $totalWithoutBreakEven = $winTrades->count() + $lossTrades->count();
        $winRate = $totalWithoutBreakEven > 0 ? round(($winTrades->count() / $totalWithoutBreakEven) * 100, 2) : 0;

        $totalProfit = $winTrades->sum('profit_loss');
        $totalLoss = abs($lossTrades->sum('profit_loss'));
        $averageRRR = ($totalLoss > 0 && $totalProfit > 0) ? round($totalProfit / $totalLoss, 2) : 'N/A';

        $growthPercent = $initialCapital > 0 ? round(($netPL / $initialCapital) * 100, 2) : 0;
        if ($netPL <= 0) $growthPercent = 0;

        $drawdownPercent = $initialCapital > 0 ? round((abs(min(0, $netPL)) / $initialCapital) * 100, 2) : 0;

        // Expectancy
        $averageWin = $winTrades->count() > 0 ? $winTrades->avg('profit_loss') : 0;
        $averageLoss = $lossTrades->count() > 0 ? abs($lossTrades->avg('profit_loss')) : 0;
        $winRateDecimal = $totalTrades > 0 ? $winTrades->count() / $totalTrades : 0;
        $lossRateDecimal = $totalTrades > 0 ? $lossTrades->count() / $totalTrades : 0;
        $expectancy = round(($winRateDecimal * $averageWin) - ($lossRateDecimal * $averageLoss), 2);

        // Consistency (standard deviation)
        $profitsArray = $journals->pluck('profit_loss')->toArray();
        $avgProfitLoss = $totalTrades > 0 ? array_sum($profitsArray) / count($profitsArray) : 0;
        $variance = $totalTrades > 1 ? array_sum(array_map(fn($pl) => pow($pl - $avgProfitLoss, 2), $profitsArray)) / ($totalTrades - 1) : 0;
        $stdDeviation = round(sqrt($variance), 2);

        [$consistencyPoints, $consistencyGrade] = ($totalTrades >= 1 && is_numeric($stdDeviation)) ? match (true) {
            $stdDeviation <= 20   => [25, 'A+'],
            $stdDeviation <= 25  => [15, 'A'],
            $stdDeviation <= 30  => [10, 'B'],
            $stdDeviation <= 35  => [5, 'C'],
            $stdDeviation <= 45  => [1, 'D'],
            default => [0, 'F'],
        } : [0, 'N/A'];

        // ===== Grading Components =====
        if ($totalTrades == 0) {
            $winRateGrade = $rrrGrade = $growthGrade = $drawdownGrade = $consistencyGrade = $expectancyGrade = 'N/A';
            $winRatePoints = $rrrPoints = $growthPoints = $drawdownPenalty = $consistencyPoints = $expectancyPoints = 0;
            $totalScore = 0;
            $rating = 'N/A';
        } else {
            // Win Rate
            [$winRatePoints, $winRateGrade] = match (true) {
                $winRate >= 90 => [30, 'A+'],
                $winRate >= 85 => [28, 'A'],
                $winRate >= 80 => [26, 'A-'],
                $winRate >= 75 => [24, 'B+'],
                $winRate >= 70 => [22, 'B'],
                $winRate >= 65 => [20, 'B-'],
                $winRate >= 60 => [18, 'C+'],
                $winRate >= 55 => [16, 'C'],
                $winRate >= 50 => [14, 'C-'],
                $winRate >= 40 => [10, 'D'],
                $winRate >= 20 => [5, 'E'],
                $winRate < 20 && $winRate >= 1 => [0, 'F'],
                default => [0, 'N/A'],
            };

            // RRR
            if ($totalProfit > 0 && $totalLoss == 0) {
                $averageRRR = 'Perfect';
                [$rrrPoints, $rrrGrade] = [30, 'A+'];
            } elseif (is_numeric($averageRRR)) {
                [$rrrPoints, $rrrGrade] = match (true) {
                    $averageRRR >= 6.0 => [30, 'A+'],
                    $averageRRR >= 5.5 => [28, 'A'],
                    $averageRRR >= 5.0 => [26, 'A-'],
                    $averageRRR >= 4.5 => [24, 'B+'],
                    $averageRRR >= 4.0 => [22, 'B'],
                    $averageRRR >= 3.5 => [20, 'B-'],
                    $averageRRR >= 3.0 => [18, 'C+'],
                    $averageRRR >= 2.5 => [16, 'C'],
                    $averageRRR >= 2.0 => [14, 'C-'],
                    $averageRRR >= 1.5 => [10, 'D'],
                    $averageRRR >= 1.0 => [6,  'E'],
                    default => [0, 'F'],
                };
            } else {
                [$rrrPoints, $rrrGrade] = [0, 'F'];
            }

            // Growth
            [$growthPoints, $growthGrade] = match (true) {
                $growthPercent >= 15 => [5, 'A'],
                $growthPercent >= 10 => [4, 'B'],
                $growthPercent >= 5  => [3, 'C+'],
                $growthPercent >= 3  => [2, 'C'],
                $growthPercent >= 2  => [1, 'D'],
                $growthPercent >= 1  => [1, 'E'],
                default              => [0, 'F'],
            };

            // Drawdown Penalty
            [$drawdownPenalty, $drawdownGrade] = match (true) {
                $drawdownPercent > 90 => [-35, 'F'],
                $drawdownPercent > 80 => [-20, 'F'],
                $drawdownPercent > 60 => [-15, 'F'],
                $drawdownPercent > 40 => [-10, 'F'],
                $drawdownPercent > 30 => [-8, 'E'],
                $drawdownPercent > 20 => [-6, 'D'],
                $drawdownPercent > 10 => [-4, 'C'],
                $drawdownPercent > 5 => [-2, 'B'],
                $drawdownPercent > 2 => [-1, 'A'],
                default => [0, 'A+'],
            };
            $drawdownPenalty = abs($drawdownPenalty);

            // Expectancy
            [$expectancyPoints, $expectancyGrade] = match (true) {
                $expectancy >= 40 => [10, 'A+'],
                $expectancy >= 35 => [7, 'A'],
                $expectancy >= 30 => [6, 'A−'],
                $expectancy >= 25 => [5, 'B+'],
                $expectancy >= 20 => [4, 'B'],
                $expectancy >= 15 => [3, 'C+'],
                $expectancy >= 10 => [2, 'C'],
                $expectancy >= 5  => [1, 'D'],
                $expectancy >= 0  => [0, 'E'],
                $expectancy >= -5 => [-1, 'F'],
                $expectancy >= -10 => [-2, 'F'],
                default => [0, 'N/A'],
            };

            // Final Score & Rating
            $totalScore = $winRatePoints + $rrrPoints + $growthPoints + $consistencyPoints + $expectancyPoints - $drawdownPenalty;
            $rating = match (true) {
                $totalScore >= 95 => 'A+',
                $totalScore >= 90 => 'A',
                $totalScore >= 85 => 'A−',
                $totalScore >= 80 => 'B+',
                $totalScore >= 75 => 'B',
                $totalScore >= 70 => 'B−',
                $totalScore >= 65 => 'C+',
                $totalScore >= 60 => 'C',
                $totalScore >= 55 => 'C−',
                $totalScore >= 50 => 'D+',
                $totalScore >= 45 => 'D',
                $totalScore >= 40 => 'D−',
                $totalScore >= 30 => 'E',
                $totalScore < 30 => 'F',
                default => 'N/A',
            };
        }
// ---------- Prop Firm Evaluation ----------
if ($initialCapital > 0 && $journals->count() > 0) {
        // ---------- Prop Firm Evaluation ----------
        $phaseRules = [
            'starting_balance' => (float) $initialCapital,
            'profit_target'    => (float) ($request->input('profit_target', 10)),
            'max_daily_loss'   => (float) ($request->input('max_daily_loss', 5)),
            'max_total_loss'   => (float) ($request->input('max_total_loss', 10)),
            'max_days'         => (int) ($request->input('max_days', 30)),
        ];

        $startingBalance = $initialCapital;
        $currentEvalBal = $startingBalance + $netPL;

        // Profit target
        $targetAmount = $startingBalance * ($phaseRules['profit_target'] / 100);
        $profitTargetPassed = $netPL >= $targetAmount;
        $profitPercent = $startingBalance > 0 ? round(($netPL / $startingBalance) * 100, 2) : 0;
        $targetProgressPct = $startingBalance > 0 ? round(($netPL / $targetAmount) * 100, 2) : 0;

        // Max Daily Loss
        $dailyPnL = $journals->groupBy(fn($t) => Carbon::parse($t->close_date)->toDateString())
                             ->map(fn($day) => (float) $day->sum('profit_loss'));
        $worstDayPnL = $dailyPnL->count() ? min($dailyPnL->toArray()) : 0;
        $maxDailyLossAmount = -1 * $startingBalance * ($phaseRules['max_daily_loss'] / 100);
        $maxDailyLossBreached = $worstDayPnL < $maxDailyLossAmount;
        $worstDayLossPercent = $startingBalance > 0 ? round(($worstDayPnL / $startingBalance) * 100, 2) : 0;

        // Max Total Loss
        $overallLossAmount = min(0, $netPL);
        $maxTotalLossAmount = -1 * $startingBalance * ($phaseRules['max_total_loss'] / 100);
        $maxTotalLossBreached = $overallLossAmount < $maxTotalLossAmount;
        $overallLossPercent = $startingBalance > 0 ? round(($overallLossAmount / $startingBalance) * 100, 2) : 0;

        // Time limit
        $firstClose = $journals->min('close_date');
        $lastClose = $journals->max('close_date');
        $daysPassed = $firstClose && $lastClose ? Carbon::parse($firstClose)->diffInDays(Carbon::parse($lastClose)) + 1 : 0;
        $withinTimeLimit = $daysPassed <= (int) $phaseRules['max_days'];

        $evaluation = [
            'rules' => $phaseRules,
            'starting_balance' => $startingBalance,
            'current_balance'  => round($currentEvalBal, 2),
            'net_pnl'          => round($netPL, 2),
            'profit_target' => [
                'target_amount' => round($targetAmount, 2),
                'achieved' => round($netPL, 2),
                'passed' => (bool) $profitTargetPassed,
                'profit_percent' => $profitPercent,
                'target_progress_percent' => $targetProgressPct,
            ],
            'max_daily_loss' => [
                'limit_amount' => round($maxDailyLossAmount, 2),
                'worst_day_pnl' => round($worstDayPnL, 2),
                'breached' => (bool) $maxDailyLossBreached,
                'worst_day_pct' => $worstDayLossPercent,
            ],
            'max_total_loss' => [
                'limit_amount' => round($maxTotalLossAmount, 2),
                'overall_pnl' => round($overallLossAmount, 2),
                'breached' => (bool) $maxTotalLossBreached,
                'overall_loss_pct' => $overallLossPercent,
            ],
            'time' => [
                'days_passed' => $daysPassed,
                'max_days' => (int) $phaseRules['max_days'],
                'within_time' => (bool) $withinTimeLimit,
            ],
            'status' => (
                $profitTargetPassed &&
                !$maxDailyLossBreached &&
                !$maxTotalLossBreached &&
                $withinTimeLimit
            ) ? 'PASS' : 'FAIL',
        ];}else{
            $evaluation = [
                'status' => 'N/A',
                'message' => '⚠️ Prop Firm Evaluation not available yet. Please add deposits and trades.'
            ];
        }
    }

    return view('admin.traders_performances.traders_journal_all', compact(
        'breadcrumbData',
        'traders',
        'selectedTraderId',
        'selectedTrader',
        'journals',
        'capitals',
        'totalTrades',
        'selectedMonth',
        'selectedYear',
        'winRate',
        'drawdownPercent',
        'totalProfit',
        'totalLoss',
        'averageRRR',
        'totalDeposits',
        'totalWithdrawals',
        'growthPercent',
        'currentBalance',
        'rating',
        'winRatePoints',
        'rrrPoints',
        'growthPoints',
        'drawdownPenalty',
        'totalScore',
        'winRateGrade',
        'rrrGrade',
        'growthGrade',
        'drawdownGrade',
        'consistencyPoints',
        'consistencyGrade',
        'stdDeviation',
        'expectancy',
        'expectancyPoints',
        'expectancyGrade',
        'evaluation'
    ));
}


    /**
     * View all traders performance list (paginated)
     */
public function ViewAllTradersPerformance(Request $request)
{
    $query = TradingJournal::query();

    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }
    if ($request->filled('month')) {
        $query->whereMonth('open_date', $request->month);
    }
    if ($request->filled('year')) {
        $query->whereYear('open_date', $request->year);
    }

    $traders = User::where('role', 'trader')->get();
    $journals = $query->latest()->paginate(20);

    // Pass current filters to view:
    $selectedTraderId = $request->input('user_id');
    $selectedMonth = $request->input('month');
    $selectedYear = $request->input('year');

    return view('admin.traders_performance.index', compact(
        'journals', 'traders',
        'selectedTraderId', 'selectedMonth', 'selectedYear'
    ));
}

    
// ✅ Admin: Export selected trader’s journal
// ✅ Admin: Export selected trader’s journal
public function AdminTradingJournalExport(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'month'   => 'required|integer|min:1|max:12',
        'year'    => 'required|integer|min:2000|max:' . date('Y'),
    ], [
        'user_id.required' => 'Please select a trader.',
        'month.required'   => 'Please select a month.',
        'year.required'    => 'Please select a year.',
    ]);

    $userId = $request->input('user_id');
    $month  = $request->input('month');
    $year   = $request->input('year');

    return Excel::download(
        new AdminTradingJournalExport($userId, $month, $year),
        'traders_performance_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '_' . now()->format('His') . '.xlsx'
    );
}


}
