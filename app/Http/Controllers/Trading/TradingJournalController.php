<?php

namespace App\Http\Controllers\Trading;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\TradingJournal;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TradingJournalExport;
use App\Models\Capital;
use App\Models\User;
use App\Models\TradingJournalBackup;

use Illuminate\Support\Facades\Schema;
use App\Services\PropFirmEvaluator;

class TradingJournalController extends Controller
{
    /**
     * Logged-in user's monthly trading journal + Prop Firm evaluation
     */
public function AllTradingJournal(Request $request)
    {
        $userId = Auth::id();
    $currentUser = auth()->user();

        // Selected month/year (default: current)
        $month = $request->input('month', now()->month); // 1 to 12
        $year = $request->input('year', now()->year);

        // Month window
        $startDate = Carbon::create($year, $month)->startOfMonth();
        $endDate = Carbon::create($year, $month)->endOfMonth();

        // Journals for the logged-in user in the selected month
        $journals = TradingJournal::where('user_id', $userId)
            ->whereBetween('open_date', [$startDate, $endDate])
            ->orderBy('close_date') // use close_date for evaluation timelines
            ->get();

        $totalTrades = $journals->count();

        // Capital (deposits/withdrawals lifetime; adjust if you track by date)
     // Capital (deposits/withdrawals lifetime; adjust if you track by date)
$totalDeposits    = Capital::where('user_id', $userId)->where('type', 1)->sum('amount');
$totalWithdrawals = abs(Capital::where('user_id', $userId)->where('type', 2)->sum('amount'));
$netPL          = $journals->sum('profit_loss');  // Net PnL of all trades
$initialCapital = $totalDeposits;                 // Deposits as starting capital
$currentBalance = $totalDeposits + $netPL - $totalWithdrawals;

// ---------- Prop Firm Evaluation ----------
if ($initialCapital > 0 && $journals->count() >= 0) {
    // Current phase (default Phase 1 if null)
    $currentPhase = $currentUser->prop_firm_phase ?? 1;

    // Load Phase 2 starting balance (persisted from Phase 1 PASS)
    $phase2StartingBalance =
        (Schema::hasColumn('users', 'phase2_start_balance') && $currentUser->phase2_start_balance)
            ? (float) $currentUser->phase2_start_balance
            : (float) $initialCapital;

    // -------- Phase rules --------
    if ($currentPhase == 1) {
        $phaseRules = [
            'phase'            => 1,
            'starting_balance' => (float) $initialCapital,
            'profit_target'    => (float) ($request->input('profit_target', 10)), // %
            'max_daily_loss'   => (float) ($request->input('max_daily_loss', 5)),  // %
            'max_total_loss'   => (float) ($request->input('max_total_loss', 10)), // %
            'max_days'         => (int)   ($request->input('max_days', 30)),
        ];
        $startingBalance = $phaseRules['starting_balance'];
        $pnlSum          = $netPL; // full journal PnL
    } else {
        $phaseRules = [
            'phase'            => 2,
            'starting_balance' => $phase2StartingBalance,
            'profit_target'    => (float) ($request->input('profit_target', 4)),  // %
            'max_daily_loss'   => (float) ($request->input('max_daily_loss', 5)), // %
            'max_total_loss'   => (float) ($request->input('max_total_loss', 10)), // %
            'max_days'         => (int)   ($request->input('max_days', 60)),
        ];
        $startingBalance = $phaseRules['starting_balance'];
        $pnlSum          = $currentBalance - $phase2StartingBalance; // Phase 2 PnL only
    }

    $currentEvalBal = $startingBalance + $pnlSum;

    // -------- Profit Target --------
    $targetAmount        = $startingBalance * ($phaseRules['profit_target'] / 100);
    $profitTargetPassed  = $pnlSum >= $targetAmount;
    $profitPercent       = $startingBalance > 0 ? round(($pnlSum / $startingBalance) * 100, 2) : 0;
    $targetProgressPct   = $targetAmount > 0 ? round(($pnlSum / $targetAmount) * 100, 2) : 0;

    // -------- Max Daily Loss --------
    $dailyPnL = $journals->groupBy(fn ($t) => Carbon::parse($t->close_date)->toDateString())
        ->map(fn ($day) => (float) $day->sum('profit_loss'));

    $worstDayPnL          = $dailyPnL->count() ? min($dailyPnL->toArray()) : 0;
    $maxDailyLossAmount   = -1 * $startingBalance * ($phaseRules['max_daily_loss'] / 100);
    $maxDailyLossBreached = $worstDayPnL < $maxDailyLossAmount;
    $worstDayLossPercent  = $startingBalance > 0 ? round(($worstDayPnL / $startingBalance) * 100, 2) : 0;

    // -------- Max Total Loss --------
    $overallLossAmount    = min(0, $pnlSum);
    $maxTotalLossAmount   = -1 * $startingBalance * ($phaseRules['max_total_loss'] / 100);
    $maxTotalLossBreached = $overallLossAmount < $maxTotalLossAmount;
    $overallLossPercent   = $startingBalance > 0 ? round(($overallLossAmount / $startingBalance) * 100, 2) : 0;

    // -------- Time Limit --------
    $firstClose = $journals->min('close_date');
    $lastClose  = $journals->max('close_date');
    $daysPassed = ($firstClose && $lastClose)
        ? Carbon::parse($firstClose)->diffInDays(Carbon::parse($lastClose)) + 1
        : 0;
    $withinTimeLimit = $daysPassed <= (int) $phaseRules['max_days'];

    // -------- Evaluation result --------
    $evaluation = [
        'phase'            => $phaseRules['phase'],
        'rules'            => $phaseRules,
        'starting_balance' => $startingBalance,
        'current_balance'  => round($currentEvalBal, 2),
        'net_pnl'          => round($pnlSum, 2),

        'profit_target' => [
            'target_amount'           => round($targetAmount, 2),
            'achieved'                => round($pnlSum, 2),
            'passed'                  => (bool) $profitTargetPassed,
            'profit_percent'          => $profitPercent,
            'target_progress_percent' => $targetProgressPct,
        ],

        'max_daily_loss' => [
            'limit_amount'  => round($maxDailyLossAmount, 2),
            'worst_day_pnl' => round($worstDayPnL, 2),
            'breached'      => (bool) $maxDailyLossBreached,
            'worst_day_pct' => $worstDayLossPercent,
        ],

        'max_total_loss' => [
            'limit_amount'     => round($maxTotalLossAmount, 2),
            'overall_pnl'      => round($overallLossAmount, 2),
            'breached'         => (bool) $maxTotalLossBreached,
            'overall_loss_pct' => $overallLossPercent,
        ],

        'time' => [
            'days_passed' => $daysPassed,
            'max_days'    => (int) $phaseRules['max_days'],
            'within_time' => (bool) $withinTimeLimit,
        ],
    ];

    // -------- Status --------
    if ($journals->count() === 0 || $startingBalance <= 0) {
        $evaluation['status'] = 'PENDING';
    } elseif (
        $evaluation['max_daily_loss']['breached'] ||
        $evaluation['max_total_loss']['breached'] ||
        !$evaluation['time']['within_time']
    ) {
        $evaluation['status'] = 'FAIL';
    } elseif ($evaluation['profit_target']['passed']) {
        $evaluation['status'] = 'PASS';
    } else {
        $evaluation['status'] = 'PENDING';
    }
// -------- Auto Phase Progression --------
if ($evaluation['status'] === 'PASS') {

    // ---------- Phase 1 -> Phase 2 ----------
    if ($currentUser->prop_firm_phase === null || $currentUser->prop_firm_phase == 1) {
        $currentUser->prop_firm_phase = 2;
        $currentUser->save();

        // Backup Phase 1 trades
        $phase1Trades = TradingJournal::where('user_id', $currentUser->id)->get();

        // if ($phase1Trades->count()) {
        //     DB::transaction(function() use ($phase1Trades, $currentUser) {
        //         foreach ($phase1Trades as $trade) {
        //             $data = $trade->toArray();
        //             unset($data['id']); // remove ID to avoid conflict
        //             TradingJournalBackup::create($data);
        //         }
        //         // Delete Phase 1 trades after backup
        //         TradingJournal::where('user_id', $currentUser->id)->delete();
        //     });
        // }

    // ---------- Phase 2 -> Funded ----------
    } elseif ($currentUser->prop_firm_phase == 2) {
        $currentUser->prop_firm_phase = 3;
        $currentUser->save();

        // Backup Phase 2 trades
        $phase2Trades = TradingJournal::where('user_id', $currentUser->id)->get();

        // if ($phase2Trades->count()) {
        //     DB::transaction(function() use ($phase2Trades, $currentUser) {
        //         foreach ($phase2Trades as $trade) {
        //             $data = $trade->toArray();
        //             unset($data['id']); // remove ID to avoid conflict
        //             TradingJournalBackup::create($data);
        //         }
        //         // Delete Phase 2 trades after backup
        //         TradingJournal::where('user_id', $currentUser->id)->delete();
        //     });
        // }
    }
}

    // -------- Suspend if FAIL --------
    if ($evaluation['status'] === 'FAIL') {
        if ($currentUser->status !== 0) {
            $currentUser->status = 0;
            $currentUser->save();

            auth()->logout();

            return redirect()->route('login')
                ->with('error', '⚠️ Your account has been suspended due to Prop Firm evaluation breach.');
        }
    }
} else {
    $evaluation = [
        'status'  => 'N/A',
        'message' => '⚠️ Prop Firm Evaluation not available yet. Please add deposits and trades.'
    ];
}

        // ---------- Your existing stats for rating/grade ----------
        $winTrades       = $journals->where('profit_loss', '>', 0);
        $lossTrades      = $journals->where('profit_loss', '<', 0);
        $breakevenTrades = $journals->where('profit_loss', '=', 0);

        $totalWithoutBreakEven = $winTrades->count() + $lossTrades->count();
        $winRate = $totalWithoutBreakEven > 0 ? round(($winTrades->count() / $totalWithoutBreakEven) * 100, 2) : 0;

        $totalProfit = $winTrades->sum('profit_loss');
        $totalLoss   = abs($lossTrades->sum('profit_loss'));
        $averageRRR  = ($totalLoss > 0 && $totalProfit > 0) ? round($totalProfit / $totalLoss, 2) : 'N/A';

        // Growth (exclude withdrawals)
        $growthPercent = $initialCapital > 0 ? round(($netPL / $initialCapital) * 100, 2) : 0;
        if ($netPL <= 0) $growthPercent = 0;

        // Drawdown
        $drawdownPercent = $initialCapital > 0
            ? round((abs(min(0, $netPL)) / $initialCapital) * 100, 2)
            : 0;

        // Expectancy
        $averageWin      = $winTrades->count() > 0 ? $winTrades->avg('profit_loss') : 0;
        $averageLoss     = $lossTrades->count() > 0 ? abs($lossTrades->avg('profit_loss')) : 0;
        $winRateDecimal  = $totalTrades > 0 ? $winTrades->count() / $totalTrades : 0;
        $lossRateDecimal = $totalTrades > 0 ? $lossTrades->count() / $totalTrades : 0;
        $expectancy      = round(($winRateDecimal * $averageWin) - ($lossRateDecimal * $averageLoss), 2);

        // Consistency (StdDev)
        $profitsArray  = $journals->pluck('profit_loss')->toArray();
        $avgProfitLoss = $totalTrades > 0 ? array_sum($profitsArray) / count($profitsArray) : 0;
        $variance      = $totalTrades > 1
            ? array_sum(array_map(fn($pl) => pow($pl - $avgProfitLoss, 2), $profitsArray)) / ($totalTrades - 1)
            : 0;
        $stdDeviation  = round(sqrt($variance), 2);

        [$consistencyPoints, $consistencyGrade] = ($totalTrades >= 1 && is_numeric($stdDeviation)) ? match (true) {
            $stdDeviation <= 15 => [25, 'A+'],
            $stdDeviation <= 20 => [20, 'A'],
            $stdDeviation <= 25 => [15, 'A-'],
            $stdDeviation <= 30 => [10, 'B'],
            $stdDeviation <= 35 => [5,  'C'],
            $stdDeviation <= 40 => [2,  'D'],
            $stdDeviation <= 45 => [1,  'E'],
            default             => [0,  'F'],
        } : [0, 'N/A'];

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
                $winRate >= 20 => [5,  'E'],
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

            // Growth (max 5)
            [$growthPoints, $growthGrade] = match (true) {
                $growthPercent >= 15 => [5, 'A'],
                $growthPercent >= 10 => [4, 'B'],
                $growthPercent >= 5  => [3, 'C+'],
                $growthPercent >= 3  => [2, 'C'],
                $growthPercent >= 2  => [1, 'D'],
                $growthPercent >= 1  => [1, 'E'],
                default              => [0, 'F'],
            };

            // Drawdown penalty
            [$drawdownPenalty, $drawdownGrade] = match (true) {
                $drawdownPercent > 90 => [-35, 'F'],
                $drawdownPercent > 80 => [-20, 'F'],
                $drawdownPercent > 60 => [-15, 'F'],
                $drawdownPercent > 40 => [-10, 'F'],
                $drawdownPercent > 30 => [-8,  'E'],
                $drawdownPercent > 20 => [-6,  'D'],
                $drawdownPercent > 10 => [-4,  'C'],
                $drawdownPercent > 5  => [-2,  'B'],
                $drawdownPercent > 2  => [-1,  'A'],
                default               => [0,   'A+'],
            };
            $drawdownPenalty = abs($drawdownPenalty);

            // Expectancy
            [$expectancyPoints, $expectancyGrade] = match (true) {
                $expectancy >= 40 => [10, 'A+'],
                $expectancy >= 35 => [7,  'A'],
                $expectancy >= 30 => [6,  'A−'],
                $expectancy >= 25 => [5,  'B+'],
                $expectancy >= 20 => [4,  'B'],
                $expectancy >= 15 => [3,  'C+'],
                $expectancy >= 10 => [2,  'C'],
                $expectancy >= 5  => [1,  'D'],
                $expectancy >= 0  => [0,  'E'],
                $expectancy >= -5 => [-1, 'F'],
                $expectancy >= -10=> [-2, 'F'],
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
                $totalScore <  30 => 'F',
                default            => 'N/A',
            };

            // Optional post-adjustments (kept from your logic as needed)
            // ...
        }

        // Breadcrumb
        $breadcrumbData = [['label' => 'Trading Journal', 'url' => route('all.trading.journals')]];

        return view('admin.trading_journals.journal_all', compact(
            'breadcrumbData',
            'journals',
            'totalTrades',
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
            'evaluation' // <-- pass the whole evaluation array to Blade
        ));
    }


    // Show form to add a new journal entry
    public function AddTradingJournal()
    {
        return view('admin.trading_journals.journal_add');
    }

   public function StoreTradingJournal(Request $request)
{
$balance = $this->getUserCapitalBalance(Auth::id());

    // ✅ Validate input
    $validated = $request->validate([
        'open_date'      => 'required|date',
        'close_date'     => 'required|date',
        'pair'           => 'required|string|max:255',
        'direction'      => 'required|in:1,2',
        'entry_price'    => 'required|numeric',
        'exit_price'     => 'required|numeric',
        'lot_size'       => 'required|numeric',
        'pips'           => 'required|numeric',
        'profit_loss'    => 'required|numeric',
        'result'         => 'nullable|in:1,2,3',
        'notes'          => 'nullable|string',
        'duplicate_count'=> 'nullable|integer|min:1|max:500', // 👈 New field
    ]);

    $count = $validated['duplicate_count'] ?? 1; // Default to 1 if not present
// Additional validation: Check if profit/loss exceeds available balance
    // 🚫 Check if user has capital
    $balance = $this->getUserCapitalBalance(Auth::id());
    if ($balance <= 0) {
        return redirect()->back()->withErrors(['error' => 'You cannot record a trade without any available capital.']);
    }

    for ($i = 0; $i < $count; $i++) {
        TradingJournal::create([
            'user_id'      => Auth::id(),
            'type'         => 'trade',
            'open_date'    => $validated['open_date'],
            'close_date'   => $validated['close_date'],
            'pair'         => $validated['pair'],
            'direction'    => $validated['direction'],
            'entry_price'  => $validated['entry_price'],
            'exit_price'   => $validated['exit_price'],
            'lot_size'     => $validated['lot_size'],
            'pips'         => $validated['pips'],
            'profit_loss'  => $validated['profit_loss'],
            'result'       => $validated['result'] ?? null,
            'notes'        => $validated['notes'] ?? null,
        ]);
    }

    $notification = [
        'message'     => 'Trade journal added successfully!',
        'alert-type'  => 'success',
    ];

    return redirect()->route('all.trading.journals')->with($notification);
}

private function getUserCapitalBalance($userId)
{
    // ✅ Capital deposits (type = 1) and withdrawals (type = 2, already negative)
    $capitalBalance = Capital::where('user_id', $userId)->sum('amount');

    // ✅ Trade profits/losses from journal
    $tradingPL = TradingJournal::where('user_id', $userId)
                    ->where('type', 'trade')
                    ->sum('profit_loss');

    return $capitalBalance + $tradingPL;
}
public function tradersJournals(Request $request)
{
$selectedMonth = $request->input('month');
$selectedYear = $request->input('year');
    $selectedTraderId = $request->input('user_id');
    $selectedTrader = $selectedTraderId ? User::find($selectedTraderId) : null;
   if ($selectedTrader) {
    $journalsQuery = TradingJournal::where('user_id', $selectedTrader->id);

    if ($selectedMonth && $selectedYear) {
        $journalsQuery->whereMonth('open_date', $selectedMonth)
                      ->whereYear('open_date', $selectedYear);
    }

    $traders = User::where('role_id', 350)->get(); // role_id 350 = Trader


    // Set breadcrumb
    $breadcrumbData = [['label' => 'Trader Journals', 'url' => route('admin.trader.journals.index')]];

    // Default values
    $journals = collect();
    $capitals = collect();
    $totalTrades = $totalProfit = $totalLoss = $growthPercent = $drawdownPercent = $currentBalance = 0;
    $winRate = $averageRRR = $expectancy = $stdDeviation = $totalScore = 0;
    $rating = $winRateGrade = $rrrGrade = $growthGrade = $drawdownGrade = $consistencyGrade = $expectancyGrade = 'N/A';
    $winRatePoints = $rrrPoints = $growthPoints = $drawdownPenalty = $consistencyPoints = $expectancyPoints = 0;
    $totalDeposits = $totalWithdrawals = 0;

    if ($selectedTrader) {
        $journals = TradingJournal::where('user_id', $selectedTrader->id)->latest()->get();
        $capitals = Capital::where('user_id', $selectedTrader->id)->get();
        $totalTrades = $journals->count();

        $totalDeposits = Capital::where('user_id', $selectedTrader->id)->where('type', 1)->sum('amount');
        $totalWithdrawals = abs(Capital::where('user_id', $selectedTrader->id)->where('type', 2)->sum('amount'));

        $netPL = $journals->sum('profit_loss');
        $initialCapital = $totalDeposits;
        $currentBalance = $initialCapital + $netPL - $totalWithdrawals;

        $winTrades = $journals->where('profit_loss', '>', 0);
        $lossTrades = $journals->where('profit_loss', '<', 0);

        $totalWithoutBreakEven = $winTrades->count() + $lossTrades->count();
        $winRate = $totalWithoutBreakEven > 0 ? round(($winTrades->count() / $totalWithoutBreakEven) * 100, 2) : 0;

        $totalProfit = $winTrades->sum('profit_loss');
        $totalLoss = abs($lossTrades->sum('profit_loss'));
        $averageRRR = ($totalLoss > 0 && $totalProfit > 0) ? round($totalProfit / $totalLoss, 2) : 'N/A';

        $growthPercent = $initialCapital > 0 ? round(($netPL / $initialCapital) * 100, 2) : 0;
        if ($netPL <= 0) $growthPercent = 0;

        $drawdownPercent = $initialCapital > 0
            ? round((abs(min(0, $netPL)) / $initialCapital) * 100, 2)
            : 0;

        // Expectancy
        $averageWin = $winTrades->count() > 0 ? $winTrades->avg('profit_loss') : 0;
        $averageLoss = $lossTrades->count() > 0 ? abs($lossTrades->avg('profit_loss')) : 0;
        $winRateDecimal = $totalTrades > 0 ? $winTrades->count() / $totalTrades : 0;
        $lossRateDecimal = $totalTrades > 0 ? $lossTrades->count() / $totalTrades : 0;
        $expectancy = round(($winRateDecimal * $averageWin) - ($lossRateDecimal * $averageLoss), 2);

        // Consistency
        $profitsArray = $journals->pluck('profit_loss')->toArray();
        $avgProfitLoss = $totalTrades > 0 ? array_sum($profitsArray) / count($profitsArray) : 0;
        $variance = $totalTrades > 1 ? array_sum(array_map(fn($pl) => pow($pl - $avgProfitLoss, 2), $profitsArray)) / ($totalTrades - 1) : 0;
        $stdDeviation = round(sqrt($variance), 2);

        [$consistencyPoints, $consistencyGrade] = ($totalTrades >= 1 && is_numeric($stdDeviation)) ? match (true) {
             $stdDeviation <= 15   => [25, 'A+'],
         $stdDeviation <= 20  => [20, 'A'],
            $stdDeviation <= 25  => [15, 'A-'],
            $stdDeviation <= 30  => [10, 'B'],
            $stdDeviation <= 35  => [5, 'C'],
            $stdDeviation <= 40  => [2, 'D'],
            $stdDeviation <= 45  => [1, 'E'],
            default => [0, 'F'],
        } : [0, 'N/A'];

       // ✅ Grading Components
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

            [$growthPoints, $growthGrade] = match (true) {
         $growthPercent >= 15 => [5, 'A'], // Exceptional growth
    $growthPercent >= 10 => [4, 'B'],  // Strong growth
    $growthPercent >= 5  => [3,  'C+'], // Acceptable growth
    $growthPercent >= 3  => [2,  'C'], // Very weak
    $growthPercent >= 2  => [1,  'D'], // Very weak
    $growthPercent >= 1  => [1,  'E'], // Very weak
    default              => [0,  'F'],  // Negative or zero growth
            };

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

            [$expectancyPoints, $expectancyGrade] = match (true) {
                    $expectancy >= 40 => [10, 'A+'],
                $expectancy >= 35 => [7, 'A'],
                $expectancy >= 30 => [6, 'A−'],
                $expectancy >= 25 => [5, 'B+'],
                $expectancy >= 20 => [4, 'B'],
                $expectancy >= 15 => [3, 'C+'],
                $expectancy >= 10 => [2, 'C'],
                $expectancy >= 5 => [1, 'D'],
                $expectancy >= 0 => [0, 'E'],
                $expectancy >= -5 => [-1, 'F'],
                $expectancy >= -10 => [-2, 'F'],
                default => [0, 'N/A'],
            };

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

            $grades = collect([$winRateGrade, $rrrGrade, $growthGrade, $drawdownGrade,$consistencyPoints]);
            $cGrades = $grades->filter(fn($g) => $g === 'C')->count();
            $cPlusExists = $grades->contains('C+');
            $dExists = $grades->contains('D');

            if ($rating === 'A+' && $grades->contains(fn($g) => $g !== 'A+')) $rating = 'A';
            if ($cPlusExists && in_array($rating, ['A+', 'A'])) $rating = 'B+';
            if ($cGrades === 1 && in_array($rating, ['A+', 'A', 'B+'])) $rating = 'B';
            if ($cGrades >= 2 && in_array($rating, ['A+', 'A', 'B+', 'B'])) $rating = 'C+';
            if ($dExists && !in_array($rating, ['C+', 'C', 'C−', 'D+', 'D', 'D−', 'E', 'F'])) $rating = 'C+';
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
        'expectancyGrade'
    ));
}
}

public function ViewAllTradersPerformance(Request $request)
{
    $query = TradingJournal::query();

    // Apply filters
    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }

    if ($request->filled('month')) {
        $query->whereMonth('open_date', $request->month);
    }

    if ($request->filled('year')) {
        $query->whereYear('open_date', $request->year);
    }

    $traders = User::where('role', 'trader')->get(); // For dropdown
    $journals = $query->latest()->paginate(20);

    return view('admin.traders_performance.index', compact('journals', 'traders'));
}


   // ✅ Trader: Export own journal
public function exportTraderJournal(Request $request)
{
    $month = $request->input('month');
    $year = $request->input('year');

    return Excel::download(
        new TradingJournalExport($month, $year),
        'my_trading_journal.xlsx'
    );
}

// ✅ Admin: Export selected trader’s journal
public function exportTradersPerformance(Request $request)
{
    $userId = $request->input('user_id');
    $month = $request->input('month');
    $year = $request->input('year');

    return Excel::download(
        new AdminTradingJournalExport($userId, $month, $year),
        'traders_performance.xlsx'
    );
}
}
