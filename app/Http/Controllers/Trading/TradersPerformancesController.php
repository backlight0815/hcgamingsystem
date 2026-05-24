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
use App\Models\TradingJournalBackup; // ✅ Import backup model
use Illuminate\Support\Facades\Schema;
use App\Services\TradingJournalAnalytics;
use App\Services\TradingJournalTimeService;
use Illuminate\Support\Collection;

class TradersPerformancesController extends Controller
{
    /**
     * Show performance statistics for a selected trader.
     */
public function tradersJournals(Request $request)
{
$month = $request->input('month');
$year  = $request->input('year');
$userId = $request->input('user_id');

// Normalize "all" → null
if (is_string($month) && strtolower($month) === 'all') {
    $month = null;
}
if (is_string($year) && strtolower($year) === 'all') {
    $year = null;
}

// ✅ If month selected but year = null ("all"), force current year
if ($month && !$year) {
    $year = now()->year;
}

$selectedMonth    = $month;
$selectedYear     = $year;
$selectedTraderId = $userId;
$selectedTrader   = $selectedTraderId ? User::find($selectedTraderId) : null;
$selectedTimeView = app(TradingJournalTimeService::class)->normalizeMode($request->input('time_view'));
$selectedTimeViewOffset = app(TradingJournalTimeService::class)->normalizeOffset($request->input('mt5_offset_minutes'), $selectedTimeView);
$journalSourceOptions = $this->journalSourceOptions();
$selectedJournalSource = $this->normalizeJournalSource($request->input('journal_source'));

    $traders        = User::where('role_id', 750)->get(); // role_id 750 = Trader
    $breadcrumbData = [['label' => 'Trader Journals', 'url' => route('admin.trader.journals.index')]];

    $currentUser = auth()->user();
    $isAdminView = $currentUser && in_array((int) $currentUser->role_id, [1, 2], true);

    // Defaults
    $journals = collect();              // DISPLAY journals (may be from backup for admin)
    $capitals = collect();
    $totalTrades = $totalProfit = $totalLoss = $growthPercent = $drawdownPercent = $currentBalance = 0;
    $winRate = $averageRRR = $expectancy = $stdDeviation = $totalScore = 0;
    $rating = $winRateGrade = $rrrGrade = $growthGrade = $recoveryGrade = $drawdownGrade = $consistencyGrade = $expectancyGrade = 'N/A';
    $winRatePoints = $rrrPoints = $growthPoints = $recoveryPoints = $drawdownPenalty = $consistencyPoints = $expectancyPoints = 0;
    $totalDeposits = $totalWithdrawals = 0;

    $riskFlags = [];
    $journalSource = $selectedJournalSource;
    $phaseArchiveComparison = [];
    $performanceMeaning = 'N/A';
    $analytics = new TradingJournalAnalytics();
    $traderStyleProfile = $analytics->behavioralRiskProfile(collect(), 0);
    $hedgingProfile = $analytics->hedgingProfile(collect());

    $evaluation = [
        'status'  => 'N/A',
        'message' => 'Prop firm evaluation is not available yet. Please add deposits and trades.',
    ];
    $propFirmStage = $this->resolvePropFirmStage($selectedTrader);

    if ($selectedTrader) {
        // -----------------------------------------------------
        // 1) DISPLAY JOURNALS
        //    Admin can review current journals, archived Phase 1/2 journals,
        //    or a combined view for comparison after phase transitions.
        // -----------------------------------------------------
        $currentJournals = $this->loadCurrentJournals($selectedTrader, $selectedMonth, $selectedYear);
        $archivePhase1Journals = $isAdminView ? $this->loadArchivedJournals($selectedTrader, 1, $selectedMonth, $selectedYear) : collect();
        $archivePhase2Journals = $isAdminView ? $this->loadArchivedJournals($selectedTrader, 2, $selectedMonth, $selectedYear) : collect();
        $archiveAllJournals = $isAdminView ? $this->loadArchivedJournals($selectedTrader, null, $selectedMonth, $selectedYear) : collect();

        $phaseArchiveComparison = $this->buildPhaseArchiveComparison(
            $selectedTrader,
            $currentJournals,
            $archivePhase1Journals,
            $archivePhase2Journals,
            $selectedMonth,
            $selectedYear
        );

        $journals = match ($selectedJournalSource) {
            'archive_phase1' => $archivePhase1Journals,
            'archive_phase2' => $archivePhase2Journals,
            'archive_all' => $archiveAllJournals,
            'all' => $this->sortJournals($currentJournals->merge($archiveAllJournals)),
            default => $currentJournals,
        };

        if ($selectedJournalSource === 'current' && $journals->isEmpty() && $archiveAllJournals->isNotEmpty()) {
            $journals = $archiveAllJournals;
            $journalSource = 'archive_all';
        }

        // -----------------------------------------------------
        // 2) CAPITALS & BALANCES (based on selected trader)
        // -----------------------------------------------------
        $totalDeposits = Capital::where('user_id', $selectedTrader->id)
            ->where('type', 1)->sum('amount');
        $totalWithdrawals = abs(Capital::where('user_id', $selectedTrader->id)
            ->where('type', 2)->sum('amount'));
        $initialCapital = $totalDeposits ?: 0;

        $capitals       = Capital::where('user_id', $selectedTrader->id)->get();
        $netPL          = $journals->sum('profit_loss');             // DISPLAY PnL (may include backup)
        $currentBalance = $initialCapital + $netPL - $totalWithdrawals;
        $totalTrades    = $journals->count();

        // -----------------------------------------------------
        // 3) DISPLAY STATS (ok to use fallback journals)
        // -----------------------------------------------------
        $winTrades  = $journals->where('profit_loss', '>', 0);
        $lossTrades = $journals->where('profit_loss', '<', 0);
        $totalWithoutBreakEven = $winTrades->count() + $lossTrades->count();
        $winRate = $totalWithoutBreakEven > 0
            ? round(($winTrades->count() / $totalWithoutBreakEven) * 100, 2) : 0;

        $totalProfit = $winTrades->sum('profit_loss');
        $totalLoss   = abs($lossTrades->sum('profit_loss'));
        $averageRRR  = ($totalLoss > 0 && $totalProfit > 0) ? round($totalProfit / $totalLoss, 2) : 'N/A';

        $growthPercent = $initialCapital > 0 ? round(($netPL / $initialCapital) * 100, 2) : 0;
        if ($netPL <= 0) $growthPercent = 0;

        $drawdownPercent = $initialCapital > 0
            ? round((abs(min(0, $netPL)) / $initialCapital) * 100, 2) : 0;
        $maxDrawdownAmount = $this->calculateJournalMaxDrawdownAmount($journals);
        $recoveryFactor = $maxDrawdownAmount > 0 ? round($netPL / $maxDrawdownAmount, 2) : ($netPL > 0 ? 'No Drawdown' : 'N/A');
        [$recoveryPoints, $recoveryGrade] = $this->journalRecoveryFactorScore($recoveryFactor, (float) $netPL, (float) $maxDrawdownAmount, $totalTrades > 0);
        $traderStyleProfile = $analytics->behavioralRiskProfile($journals, (float) $initialCapital);
        $hedgingProfile = $analytics->hedgingProfile($journals);

        // Expectancy
        $averageWin      = $winTrades->count()  > 0 ? $winTrades->avg('profit_loss') : 0;
        $averageLoss     = $lossTrades->count() > 0 ? abs($lossTrades->avg('profit_loss')) : 0;
        $winRateDecimal  = $totalTrades > 0 ? $winTrades->count() / $totalTrades : 0;
        $lossRateDecimal = $totalTrades > 0 ? $lossTrades->count() / $totalTrades : 0;
        $expectancy      = round(($winRateDecimal * $averageWin) - ($lossRateDecimal * $averageLoss), 2);
// ---------------- Consistency Evaluation (R-multiple based) ----------------

// -------- Consistency Rule (FundingPips 15% rule) --------

// Group trades by day and sum profits for each day
$dailyPnL = $journals->groupBy(fn ($t) => Carbon::parse($t->close_date)->toDateString())
                     ->map(fn ($day) => (float) $day->sum('profit_loss'));

// Biggest winning day
$biggestWinningDay = $dailyPnL->count() ? max($dailyPnL->toArray()) : 0;

// Current total account profit (sum of all daily PnL)
$currentTotalProfit = $dailyPnL->sum() ?? 0;

// Consistency score (%)
$consistencyPercent = $currentTotalProfit > 0
    ? round(($biggestWinningDay / $currentTotalProfit) * 100, 2)
    : 0;

// Check if consistency target met (<= 15%)
$consistencyPassed = $consistencyPercent <= 15;

// Grade / Status
$consistencyGrade = $consistencyPassed ? '✅ Passed' : '⏳ Pending (Keep Trading)';

        // ----- Grading (DISPLAY) -----
        if ($totalTrades == 0) {
            $winRateGrade = $rrrGrade = $growthGrade = $recoveryGrade = $drawdownGrade = $consistencyGrade = $expectancyGrade = 'N/A';
            $winRatePoints = $rrrPoints = $growthPoints = $recoveryPoints = $drawdownPenalty = $consistencyPoints = $expectancyPoints = 0;
            $totalScore = 0;
            $rating = 'N/A';
        } else {
            // Win Rate points
            [$winRatePoints, $winRateGrade] = match (true) {

   $winRate >= 75 => [30, 'A'],   // was 70 → now 75
    $winRate >= 65 => [25, 'B+'],  // was 60 → now 65
    $winRate >= 60 => [25, 'B'],  // was 60 → now 65
    $winRate >= 55 => [20, 'C+'],   // was 50 → now 55
    $winRate >= 50 => [17, 'C'],   // was 50 → now 55
    $winRate >= 45 => [15, 'D+'],   // was 40 → now 45
    $winRate >= 35 => [10, 'D'],   // was 30 → now 35
    $winRate >  0  => [5,  'E'],   // same as before, >0 to cover beginners
    $winRate < 20 && $winRate >= 1 => [0, 'F'], // still fallback for very low win rate
    default => [0, 'N/A'],
        };
            // RRR points
            if ($totalProfit > 0 && $totalLoss == 0) {
                $averageRRR = 'Perfect';
                [$rrrPoints, $rrrGrade] = [30, 'A+'];
            } elseif (is_numeric($averageRRR)) {
                [$rrrPoints, $rrrGrade] = match (true) {
           $averageRRR >= 5.75 => [30, 'A+'],  // was 5.0
    $averageRRR >= 3.45 => [25, 'A'],   // was 3.0
    $averageRRR >= 2.30 => [20, 'B'],   // was 2.0
    $averageRRR >= 1.73 => [15, 'C'],   // was 1.5
    $averageRRR >= 1.15 => [10, 'D'],   // was 1.0
    $averageRRR > 0    => [5,  'E'],
    default             => [0, 'F'],
                };
            } else {
                [$rrrPoints, $rrrGrade] = [0, 'F'];
            }

            // Growth points
            [$growthPoints, $growthGrade] = match (true) {
    $growthPercent >= 15   => [10, 'A'],  // was 10
    $growthPercent >= 7.5  => [7,  'B'],  // was 5
    $growthPercent >= 4.5  => [5,  'C'],  // was 3
    $growthPercent >= 1.5  => [3,  'D'],  // was 1
    $growthPercent > 0     => [1,  'E'],
    default                => [0,  'F'],
            };

            // Drawdown penalty
            [$drawdownPenalty, $drawdownGrade] = match (true) {
                $drawdownPercent > 90 => [-35, 'F'],
                $drawdownPercent > 80 => [-20, 'F'],
                $drawdownPercent > 60 => [-15, 'F'],
                $drawdownPercent > 40 => [-10, 'F'],
                $drawdownPercent > 30 => [-8, 'E'],
                $drawdownPercent > 20 => [-6, 'D'],
                $drawdownPercent > 10 => [-4, 'C'],
                $drawdownPercent > 5  => [-2, 'B'],
                $drawdownPercent > 2  => [-1, 'A'],
                default               => [0, 'A+'],
            };
            $drawdownPenalty = abs($drawdownPenalty);

            // Expectancy points
            [$expectancyPoints, $expectancyGrade] = match (true) {
 $expectancy >= 40  => [10, 'A+'],  // was 20
    $expectancy >= 20  => [7,  'B'],   // was 10
    $expectancy >= 10  => [5,  'C'],   // was 5
    $expectancy >= 0   => [0,  'N/A'],   // same
    $expectancy >= -10 => [-1, 'F'],   // was -5
    $expectancy >= -20 => [-2, 'F'],   // was -10
    default            => [0, 'N/A'],
            };
// -------- Consistency Score Points -------
 if ($consistencyPercent <= 10) {
     $consistencyPoints = 15; // Full score
    $consistencyGrade  = 'S';
}
elseif ($consistencyPercent <= 15) {
    $consistencyPoints = 12; // Full score
    $consistencyGrade  = 'A';
} elseif ($consistencyPercent <= 20) {
    $consistencyPoints = 10;
    $consistencyGrade  = 'B+';
} elseif ($consistencyPercent <= 25) {

} elseif ($consistencyPercent <= 30) {
    $consistencyPoints = 8;
    $consistencyGrade  = 'B';
} elseif ($consistencyPercent <= 35) {
    $consistencyPoints = 6;
    $consistencyGrade  = 'B-';
} elseif ($consistencyPercent <= 50) {
    $consistencyPoints = 4;
    $consistencyGrade  = 'C';
} elseif ($consistencyPercent <= 65) {
    $consistencyPoints = 2;
    $consistencyGrade  = 'D';
} elseif ($consistencyPercent <= 80) {
        $consistencyPoints = 1;
    $consistencyGrade  = 'E';
}else{
    $consistencyPoints = 0;
    $consistencyGrade  = 'F';
}

            // Final Score
            $totalScore = $winRatePoints + $rrrPoints + $recoveryPoints + $consistencyPoints + $expectancyPoints - $drawdownPenalty;
            $rating = match (true) {
                $totalScore >= 95 => 'S',
                $totalScore >= 90 => 'A+',
                $totalScore >= 85 => 'A',
                $totalScore >= 80 => 'A-',
                $totalScore >= 75 => 'B+',
                $totalScore >= 70 => 'B',
                $totalScore >= 65 => 'B-',
                $totalScore >= 60 => 'C+',
                $totalScore >= 55 => 'C',
                $totalScore >= 50 => 'C-',
                $totalScore >= 45 => 'D',
                $totalScore >= 40 => 'D−',
                $totalScore >= 30 => 'E',
                $totalScore <  30 => 'F',
                default            => 'N/A',
            };
        }
// Extra Meaning Mapping (performance bands)
$performanceMeaning = match (true) {
    $totalScore >= 80 => 'Exceptional: Elite trading performance across the key metrics.',
    $totalScore >= 60 => 'Good: Solid trading foundation with minor areas for improvement.',
    $totalScore >= 40 => 'Average: Adequate performance with clear development opportunities.',
    $totalScore >= 20 => 'Below average: Basic competency with significant improvement required.',
    $totalScore >= 0  => 'Poor: Requires immediate development and closer review.',
    default           => 'N/A',
};


    // --- ⚠️ 3% Risk Check ---
    $riskFlags = [];
    if ($initialCapital > 0 && $journals->isNotEmpty()) {
        foreach ($journals as $trade) {
            // Only consider losing trades for "risk"
            if ($trade->profit_loss < 0) {
                $riskPercent = round((abs($trade->profit_loss) / $initialCapital) * 100, 2);
                if ($riskPercent > 3) {
                    $riskFlags[] = [
                        'trade_id'     => $trade->id,
                        'open_date'    => $trade->open_date,
                        'pair'         => $trade->pair ?? 'N/A',
                        'lot_size'     => $trade->lot_size,
                        'loss_amount'  => $trade->profit_loss,
                        'risk_percent' => $riskPercent,
                        'flag'         => '⚠️ Exceeded 3% Risk',
                    ];
                }
            }
        }
    }

        // -----------------------------------------------------
        // 4) EVALUATION (NEVER uses backup)
        //    - If main table empty => no evaluation
        //    - Admin still shows Phase 2 rules if trader advanced
        // -----------------------------------------------------
        $evalQuery = TradingJournal::where('user_id', $selectedTrader->id);
        if ($selectedMonth && $selectedYear) {
            // Keep filters consistent, if you want evaluation filtered by month/year
            $evalQuery->whereMonth('open_date', $selectedMonth)
                      ->whereYear('open_date', $selectedYear);
        }
        $evalJournals = $evalQuery->get();

        $canEvaluate = $evalJournals->isNotEmpty(); // ONLY evaluate when main journals exist

        // Determine phase for VIEW. Funded accounts must not be displayed as Phase 2 pending.
        $currentPhase = $selectedTrader->prop_firm_phase ?? 1;
        $reviewStatus = (string) ($selectedTrader->prop_firm_review_status ?? 'none');
        $isFundedStage = (int) $currentPhase >= 3
            || (int) ($selectedTrader->funded_status ?? 0) === 1
            || $reviewStatus === 'funded_approved';

        // Phase 2 starting balance (persisted)
        $phase2StartingBalance =
            (Schema::hasColumn('users', 'phase2_start_balance') && $selectedTrader->phase2_start_balance)
                ? (float) $selectedTrader->phase2_start_balance
                : (float) $initialCapital;

        // Build phase rules regardless, so admin can see the correct stage configuration.
        if ($isFundedStage) {
            $phaseRules = [
                'phase'               => 3,
                'starting_balance'    => $phase2StartingBalance,
                'profit_target'       => 0,
                'max_daily_loss'      => (float) ($request->input('max_daily_loss', 5)),
                'max_total_loss'      => (float) ($request->input('max_total_loss', 10)),
                'max_days'            => 0,
                'min_profitable_days' => 0,
            ];
            $evalStartingBalance = $phaseRules['starting_balance'];
        } elseif ($currentPhase == 1) {
            $phaseRules = [
                'phase'               => 1,
                'starting_balance'    => (float) $initialCapital,
                'profit_target'       => (float) ($request->input('profit_target', 10)),
                'max_daily_loss'      => (float) ($request->input('max_daily_loss', 5)),
                'max_total_loss'      => (float) ($request->input('max_total_loss', 10)),
                'max_days'            => (int)   ($request->input('max_days', 15)),
                // 'consistency_limit'   => 750,
                'min_profitable_days' => 3,
            ];
            $evalStartingBalance = $phaseRules['starting_balance'];
        } else {
            $phaseRules = [
                'phase'               => 2,
                'starting_balance'    => $phase2StartingBalance,
                'profit_target'       => (float) ($request->input('profit_target', 5)),
                'max_daily_loss'      => (float) ($request->input('max_daily_loss', 5)),
                'max_total_loss'      => (float) ($request->input('max_total_loss', 10)),
                'max_days'            => (int)   ($request->input('max_days', 30)),
                // 'consistency_limit'   => 750,
                'min_profitable_days' => 3,
            ];
            $evalStartingBalance = $phaseRules['starting_balance'];
        }

        // If we cannot evaluate (no main journals), return a shell with phase/rules and N/A numbers
        if (!$canEvaluate || $evalStartingBalance <= 0) {
            $evaluation = [
                'phase'            => $phaseRules['phase'],
                'rules'            => $phaseRules,
                'starting_balance' => $evalStartingBalance,
                'current_balance'  => 0,
                'net_pnl'          => 0,
                // 'consistency'      => [
                //     'std_deviation' => 0,
                //     'limit'         => $phaseRules['consistency_limit'],
                //     'breached'      => false,
                //     'percent'       => 0,
                //     'grade'         => 'N/A',
                // ],

            'consistency' => [
        'biggest_winning_day' => round($biggestWinningDay, 2),
    'total_profit'        => round($currentTotalProfit, 2),
    'score_percent'       => $consistencyPercent,
    'passed'              => $consistencyPassed,
    'grade'               => $consistencyGrade,
            ],


                'profit_target' => [
                    'target_amount'           => round($evalStartingBalance * ($phaseRules['profit_target'] / 100), 2),
                    'achieved'                => 0,
                    'passed'                  => false,
                    'profit_percent'          => 0,
                    'target_progress_percent' => 0,
                ],
                'max_daily_loss' => [
                    'limit_amount'  => round(-1 * $evalStartingBalance * ($phaseRules['max_daily_loss'] / 100), 2),
                    'worst_day_pnl' => 0,
                    'breached'      => false,
                    'worst_day_pct' => 0,
                ],
                'max_total_loss' => [
                    'limit_amount'     => round(-1 * $evalStartingBalance * ($phaseRules['max_total_loss'] / 100), 2),
                    'overall_pnl'      => 0,
                    'breached'         => false,
                    'overall_loss_pct' => 0,
                ],
                'profitable_day' => $analytics->profitableDayRule(collect(), $phaseRules['min_profitable_days']),
                'time' => [
                    'days_passed' => 0,
                    'max_days'    => (int) $phaseRules['max_days'],
                    'within_time' => false,
                ],
                'status' => $this->resolveEvaluationStatusFromWorkflow($selectedTrader),
            ];
        } else {
            // ----- EVALUATION numbers (MAIN journals only) -----
            $evalNetPL = $evalJournals->sum('profit_loss');
            $evalCurrentBalance = $initialCapital + $evalNetPL - $totalWithdrawals;

            if ($currentPhase == 1) {
                $pnlSum = $evalNetPL;
            } else {
                $pnlSum = $evalCurrentBalance - $phase2StartingBalance;
            }

            $currentEvalBal = $evalStartingBalance + $pnlSum;

            // Profit target
            $targetAmount        = $evalStartingBalance * ($phaseRules['profit_target'] / 100);
            $profitTargetPassed  = $pnlSum >= $targetAmount;
            $profitPercent       = $evalStartingBalance > 0 ? round(($pnlSum / $evalStartingBalance) * 100, 2) : 0;
            $targetProgressPct   = $targetAmount > 0 ? round(($pnlSum / $targetAmount) * 100, 2) : 0;

            // Daily PnL from EVAL journals
            $dailyPnL = $analytics->dailyProfitLoss($evalJournals);
            $worstDayPnL          = $dailyPnL->count() ? min($dailyPnL->toArray()) : 0;
            $maxDailyLossAmount   = -1 * $evalStartingBalance * ($phaseRules['max_daily_loss'] / 100);
            $maxDailyLossBreached = $worstDayPnL < $maxDailyLossAmount;
            $worstDayLossPercent  = $evalStartingBalance > 0 ? round(($worstDayPnL / $evalStartingBalance) * 100, 2) : 0;

            // Overall loss
            $overallLossAmount    = min(0, $pnlSum);
            $maxTotalLossAmount   = -1 * $evalStartingBalance * ($phaseRules['max_total_loss'] / 100);
            $maxTotalLossBreached = $overallLossAmount < $maxTotalLossAmount;
            $overallLossPercent   = $evalStartingBalance > 0 ? round(($overallLossAmount / $evalStartingBalance) * 100, 2) : 0;

            // Profitable days
            $requiredProfitableDays = $phaseRules['min_profitable_days'];
            $profitableDayRule      = $analytics->profitableDayRule($evalJournals, $requiredProfitableDays);

            // // Consistency for EVAL journals
            // $evalProfits = $evalJournals->pluck('profit_loss')->toArray();
            // $evalLots    = $evalJournals->pluck('lot_size')->toArray();
            // $evalNorm    = [];
            // foreach ($evalProfits as $i => $pl) {
            //     $lot = $evalLots[$i] ?? 0;
            //     if ($lot > 0) $evalNorm[] = $pl / $lot;
            // }
            // $evalAvg   = count($evalNorm) > 0 ? array_sum($evalNorm) / count($evalNorm) : 0;
            // $evalVar   = count($evalNorm) > 1
            //     ? array_sum(array_map(fn($v) => pow($v - $evalAvg, 2), $evalNorm)) / (count($evalNorm) - 1)
            //     : 0;
            // $stdDeviationEval = round(sqrt($evalVar), 2);
            // $consistencyLimit = $phaseRules['consistency_limit'];
            // $consistencyBreached = $stdDeviationEval > $consistencyLimit;

            // $consistencyPercentEval = 0;
            // $consistencyGradeEval   = 'N/A';
            // if (count($evalNorm) >= 1 && is_numeric($stdDeviationEval)) {
            //     $maxThreshold = 750;
            //     $consistencyPercentEval = max(0, 100 * ($maxThreshold - $stdDeviationEval) / $maxThreshold);
            //     $consistencyPercentEval = round($consistencyPercentEval, 2);
            //     $consistencyGradeEval = match (true) {
            //         $stdDeviationEval <= 150  => 'A+',
            //         $stdDeviationEval <= 300  => 'A',
            //         $stdDeviationEval <= 450  => 'A-',
            //         $stdDeviationEval <= 600  => 'B',
            //         $stdDeviationEval <= 750  => 'C',
            //         $stdDeviationEval <= 900  => 'D',
            //         $stdDeviationEval <= 1200 => 'E',
            //         default                   => 'F',
            //     };
            // }

            // Time limit (EVAL journals only)
            $firstClose = $evalJournals->min('close_date');
            $lastClose  = $evalJournals->max('close_date');
            $daysPassed = ($firstClose && $lastClose)
                ? \Carbon\Carbon::parse($firstClose)->diffInDays(\Carbon\Carbon::parse($lastClose)) + 1
                : 0;
            $withinTimeLimit = $daysPassed <= (int) $phaseRules['max_days'];

            // Build evaluation
            $evaluation = [
                'phase'            => $phaseRules['phase'],
                'rules'            => $phaseRules,
                'starting_balance' => $evalStartingBalance,
                'current_balance'  => round($currentEvalBal, 2),
                'net_pnl'          => round($pnlSum, 2),

            'consistency' => [
        'biggest_winning_day' => round($biggestWinningDay, 2),
    'total_profit'        => round($currentTotalProfit, 2),
    'score_percent'       => $consistencyPercent,
    'passed'              => $consistencyPassed,
    'grade'               => $consistencyGrade,
            ],

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

                'profitable_day' => $profitableDayRule,

                'time' => [
                    'days_passed' => $daysPassed,
                    'max_days'    => (int) $phaseRules['max_days'],
                    'within_time' => (bool) $withinTimeLimit,
                ],
            ];

            // Status follows the account workflow for funded accounts, otherwise it follows evaluation rules.
            if ($phaseRules['phase'] === 3) {
                $evaluation['status'] = $this->resolveEvaluationStatusFromWorkflow($selectedTrader);
            } elseif ($evaluation['max_total_loss']['breached']) {
                $evaluation['status'] = (string) ($selectedTrader->prop_firm_review_status ?? 'none') === 'total_loss_allowed'
                    ? 'REVIEWED'
                    : 'UNDER_REVIEW';
            } elseif ($evaluation['max_daily_loss']['breached']) {
                $evaluation['status'] = (string) ($selectedTrader->prop_firm_review_status ?? 'none') === 'daily_loss_allowed'
                    ? 'REVIEWED'
                    : 'UNDER_REVIEW';
            } elseif (
                ($evaluation['profit_target']['passed'] ?? false) &&
                ($evaluation['profitable_day']['has_profitable_day'] ?? false) &&
                $withinTimeLimit
            ) {
                $evaluation['status'] = 'PASS';
            } else {
                $evaluation['status'] = 'PENDING';
            }
        }

        $propFirmStage = $this->resolvePropFirmStage($selectedTrader, $evaluation);
    }

    // Some blades use $trader variable; alias for safety
    $trader = $selectedTrader;

    return view('admin.traders_performances.traders_journal_all', compact(
        'breadcrumbData',
        'traders',
        'selectedTraderId',
        'selectedTrader',
        'trader',
        'isAdminView',
        'journals',
        'capitals',
        'totalTrades',
        'selectedMonth',
        'selectedYear',
        'selectedTimeView',
        'selectedTimeViewOffset',
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
        'recoveryPoints',
        'drawdownPenalty',
        'totalScore',
        'winRateGrade',
           // 'consistencyPercent',  // <-- ADD THIS

        'rrrGrade',
        'growthGrade',
        'recoveryGrade',
        'drawdownGrade',
        'consistencyPoints',
        'consistencyGrade',
        'expectancy',
        'expectancyPoints',
        'expectancyGrade',
        'evaluation',
        'riskFlags',
        'traderStyleProfile',
        'hedgingProfile',
        'journalSource',
        'selectedJournalSource',
        'journalSourceOptions',
        'phaseArchiveComparison',
        'performanceMeaning',
        'propFirmStage'

    ));
}

    private function journalSourceOptions(): array
    {
        return [
            'current' => ['label' => 'Current Phase Journal', 'short_label' => 'Current'],
            'archive_phase1' => ['label' => 'Phase 1 Archive', 'short_label' => 'Phase 1'],
            'archive_phase2' => ['label' => 'Phase 2 Archive', 'short_label' => 'Phase 2'],
            'archive_all' => ['label' => 'All Archived Phases', 'short_label' => 'Archived'],
            'all' => ['label' => 'Current + Archived', 'short_label' => 'Combined'],
        ];
    }

    private function normalizeJournalSource($source): string
    {
        $source = is_string($source) ? $source : 'current';

        return array_key_exists($source, $this->journalSourceOptions()) ? $source : 'current';
    }

    private function loadCurrentJournals(User $trader, $month, $year): Collection
    {
        $query = TradingJournal::where('user_id', $trader->id);
        $this->applyJournalDateFilters($query, $month, $year);

        return $this->tagJournals($query->latest()->get(), 'current', (int) ($trader->prop_firm_phase ?? 1));
    }

    private function loadArchivedJournals(User $trader, ?int $phase, $month, $year): Collection
    {
        if (! Schema::hasTable('trading_journals_backup')) {
            return collect();
        }

        if ($phase !== null && ! Schema::hasColumn('trading_journals_backup', 'prop_firm_phase')) {
            return collect();
        }

        $query = TradingJournalBackup::where('user_id', $trader->id);
        $this->applyJournalDateFilters($query, $month, $year);

        if ($phase !== null) {
            $query->where('prop_firm_phase', $phase);
        }

        $sourceKey = $phase === 1 ? 'archive_phase1' : ($phase === 2 ? 'archive_phase2' : 'archive_all');

        return $this->tagJournals($query->latest()->get(), $sourceKey, $phase);
    }

    private function applyJournalDateFilters($query, $month, $year): void
    {
        if ($month && $year) {
            $query->whereMonth('open_date', $month)
                ->whereYear('open_date', $year);
        }
    }

    private function tagJournals(Collection $journals, string $sourceKey, ?int $phase = null): Collection
    {
        return $journals->map(function ($journal) use ($sourceKey, $phase) {
            $archivePhase = $phase ?? ($journal->prop_firm_phase ?? null);
            $sourceLabel = match ($sourceKey) {
                'current' => 'Current Phase',
                'archive_phase1' => 'Phase 1 Archive',
                'archive_phase2' => 'Phase 2 Archive',
                default => $archivePhase ? 'Phase ' . $archivePhase . ' Archive' : 'Archived',
            };

            $journal->setAttribute('journal_source_key', $sourceKey);
            $journal->setAttribute('journal_source_label', $sourceLabel);

            return $journal;
        });
    }

    private function sortJournals(Collection $journals): Collection
    {
        return $journals
            ->sortByDesc(function ($journal): int {
                $date = $journal->close_date ?? $journal->open_date ?? $journal->created_at;

                return $date ? Carbon::parse($date)->timestamp : 0;
            })
            ->values();
    }

    private function buildPhaseArchiveComparison(User $trader, Collection $currentJournals, Collection $phase1Archive, Collection $phase2Archive, $month, $year): array
    {
        $currentPhase = (int) ($trader->prop_firm_phase ?? 1);
        $sources = [
            ['key' => 'archive_phase1', 'label' => 'Phase 1 Archive', 'journals' => $phase1Archive],
            ['key' => 'archive_phase2', 'label' => 'Phase 2 Archive', 'journals' => $phase2Archive],
            ['key' => 'current', 'label' => 'Current Phase ' . $currentPhase, 'journals' => $currentJournals],
        ];

        return collect($sources)->map(function (array $source) use ($trader, $month, $year): array {
            $journals = $source['journals'];
            $sortedJournals = $this->sortJournals($journals);
            $winCount = $journals->where('profit_loss', '>', 0)->count();
            $lossCount = $journals->where('profit_loss', '<', 0)->count();
            $closedCount = $winCount + $lossCount;
            $firstTrade = $sortedJournals->last();
            $lastTrade = $sortedJournals->first();

            return [
                'key' => $source['key'],
                'label' => $source['label'],
                'trade_count' => $journals->count(),
                'net_profit' => round((float) $journals->sum('profit_loss'), 2),
                'win_rate' => $closedCount > 0 ? round(($winCount / $closedCount) * 100, 2) : 0,
                'average_lot' => $journals->count() > 0 ? round((float) $journals->avg('lot_size'), 2) : 0,
                'first_trade_date' => $firstTrade?->open_date,
                'last_trade_date' => $lastTrade?->close_date ?? $lastTrade?->open_date,
                'query' => [
                    'user_id' => $trader->id,
                    'month' => $month ?: null,
                    'year' => $year ?: null,
                    'journal_source' => $source['key'],
                ],
            ];
        })->all();
    }

    private function calculateJournalMaxDrawdownAmount($journals): float
    {
        $runningProfit = 0.0;
        $peakProfit = 0.0;
        $maxDrawdown = 0.0;

        foreach ($journals->sortBy(fn ($journal) => $journal->close_date ?? $journal->open_date ?? $journal->created_at) as $journal) {
            $runningProfit += (float) ($journal->profit_loss ?? 0);
            $peakProfit = max($peakProfit, $runningProfit);
            $maxDrawdown = max($maxDrawdown, $peakProfit - $runningProfit);
        }

        return round($maxDrawdown, 2);
    }

    private function journalRecoveryFactorScore($recoveryFactor, float $netProfitLoss, float $maxDrawdownAmount, bool $hasTrades): array
    {
        if (! $hasTrades) {
            return [0, 'N/A'];
        }

        if ($netProfitLoss <= 0) {
            return [0, 'F'];
        }

        if ($maxDrawdownAmount <= 0) {
            return [15, 'A+'];
        }

        $recoveryFactor = is_numeric($recoveryFactor)
            ? (float) $recoveryFactor
            : $netProfitLoss / $maxDrawdownAmount;

        return match (true) {
            $recoveryFactor >= 3.00 => [15, 'A+'],
            $recoveryFactor >= 2.00 => [12, 'A'],
            $recoveryFactor >= 1.50 => [10, 'B'],
            $recoveryFactor >= 1.00 => [7, 'C'],
            $recoveryFactor >= 0.50 => [4, 'D'],
            $recoveryFactor > 0 => [2, 'E'],
            default => [0, 'F'],
        };
    }

    private function resolvePropFirmStage(?User $trader, array $evaluation = []): array
    {
        if (! $trader) {
            return [
                'label' => 'Select a trader',
                'phase_label' => 'No account selected',
                'badge_class' => 'secondary',
                'status' => 'N/A',
                'locked' => false,
                'lock_label' => 'N/A',
                'note' => null,
                'evaluation_status' => $evaluation['status'] ?? 'N/A',
            ];
        }

        $reviewStatus = (string) ($trader->prop_firm_review_status ?? 'none');
        $phase = (int) ($trader->prop_firm_phase ?? 1);
        $fundedStatus = (int) ($trader->funded_status ?? 0);

        [$label, $phaseLabel, $badgeClass] = match ($reviewStatus) {
            'pending_phase2' => ['Phase 1 passed - admin review pending', 'Phase 1 review gate', 'warning'],
            'pending_funded' => ['Phase 2 passed - funded approval pending', 'Phase 2 review gate', 'warning'],
            'daily_loss_review' => ['Daily loss breach - admin review', 'Risk review', 'warning'],
            'daily_loss_allowed' => ['Daily loss breach reviewed - active', 'Risk reviewed', 'primary'],
            'daily_loss_banned' => ['Banned after daily loss review', 'Closed review', 'danger'],
            'total_loss_review' => ['Total loss breach - admin review', 'Risk review', 'danger'],
            'total_loss_allowed' => ['Total loss breach reviewed - active', 'Risk reviewed', 'primary'],
            'total_loss_banned' => ['Banned after total loss review', 'Closed review', 'danger'],
            'question_required' => ['Trader response required', 'Evaluation question', 'warning'],
            'suspended' => ['Suspended for review', 'Investigation', 'danger'],
            'rejected' => ['Review rejected', 'Closed review', 'danger'],
            'funded_approved' => ['Funded account approved', 'Funded account', 'success'],
            'approved_phase2' => ['Phase 2 evaluation active', 'Phase 2 evaluation', 'primary'],
            default => $phase >= 3 || $fundedStatus === 1
                ? ['Funded account approved', 'Funded account', 'success']
                : ($phase === 2
                    ? ['Phase 2 evaluation active', 'Phase 2 evaluation', 'primary']
                    : ['Phase 1 evaluation active', 'Phase 1 evaluation', 'secondary']),
        };

        return [
            'label' => $label,
            'phase_label' => $phaseLabel,
            'badge_class' => $badgeClass,
            'status' => $reviewStatus,
            'locked' => (bool) ($trader->prop_firm_trade_locked ?? false),
            'lock_label' => (bool) ($trader->prop_firm_trade_locked ?? false) ? 'Trading locked' : 'Trading enabled',
            'note' => $trader->prop_firm_review_note,
            'evaluation_status' => $evaluation['status'] ?? $this->resolveEvaluationStatusFromWorkflow($trader),
        ];
    }

    private function resolveEvaluationStatusFromWorkflow(?User $trader): string
    {
        if (! $trader) {
            return 'N/A';
        }

        $reviewStatus = (string) ($trader->prop_firm_review_status ?? 'none');

        return match ($reviewStatus) {
            'funded_approved' => 'APPROVED',
            'pending_phase2', 'pending_funded' => 'UNDER_REVIEW',
            'daily_loss_review' => 'UNDER_REVIEW',
            'daily_loss_allowed' => 'REVIEWED',
            'daily_loss_banned' => 'SUSPENDED',
            'total_loss_review' => 'UNDER_REVIEW',
            'total_loss_allowed' => 'REVIEWED',
            'total_loss_banned' => 'SUSPENDED',
            'question_required' => 'QUESTION_REQUIRED',
            'suspended' => 'SUSPENDED',
            'rejected' => 'REJECTED',
            default => (int) ($trader->prop_firm_phase ?? 1) >= 3 || (int) ($trader->funded_status ?? 0) === 1
                ? 'APPROVED'
                : 'N/A',
        };
    }



    /**
     * View all traders performance list (paginated)
     */
public function ViewAllTradersPerformance(Request $request)
{
    $query = TradingJournal::query();

    $month = $request->input('month');
    $year  = $request->input('year');

    // Normalize "all"
    if (is_string($month) && strtolower($month) === 'all') {
        $month = null;
    }
    if (is_string($year) && strtolower($year) === 'all') {
        $year = null;
    }

    // ✅ If month selected but year = null ("all") → force current year
    if ($month && !$year) {
        $year = now()->year;
    }

    if ($request->filled('user_id')) {
        $query->where('user_id', $request->user_id);
    }
    if ($month) {
        $query->whereMonth('open_date', $month);
    }
    if ($year) {
        $query->whereYear('open_date', $year);
    }

    $traders = User::where('role', 'trader')->get();
    $journals = $query->latest()->paginate(20);

    return view('admin.traders_performance.index', [
        'journals'          => $journals,
        'traders'           => $traders,
        'selectedTraderId'  => $request->input('user_id'),
        'selectedMonth'     => $month,
        'selectedYear'      => $year,
    ]);
}

    
// ✅ Admin: Export selected trader’s journal
// ✅ Admin: Export selected trader’s journal
public function AdminTradingJournalExport(Request $request)
{
    // Normalize possible "all" string from dropdowns
    $monthParam = $request->input('month', null);
    $yearParam  = $request->input('year', null);

    if (is_string($monthParam) && strtolower($monthParam) === 'all') {
        $monthParam = null;
    }
    if (is_string($yearParam) && strtolower($yearParam) === 'all') {
        $yearParam = null;
    }

    // Put normalized values back so validation & later code sees them
    $request->merge(['month' => $monthParam, 'year' => $yearParam]);

    $request->validate([
          'user_id' => 'required|exists:users,id',
        'month'   => 'nullable|integer|min:1|max:12',
        'year'    => 'nullable|integer|min:2000|max:' . date('Y'),
    ], [
        'user_id.required' => 'Please select a trader.',
    ]);

        $userId = (int) $request->input('user_id');
    $month  = $request->input('month'); // null or int
    $year   = $request->input('year');  // null or int
    // 🔑 Get the username (or name field) to add into filename
    $user = User::findOrFail($userId);
    $username = preg_replace('/[^A-Za-z0-9_-]/', '_', $user->name);
    // sanitize: replace spaces/special chars with underscore

    if ($month && $year) {
        $fileName = "{$username}_performance_{$year}_" . str_pad($month, 2, '0', STR_PAD_LEFT);
    } elseif ($year) {
        $fileName = "{$username}_performance_{$year}_all_months";
    } else {
        $fileName = "{$username}_performance_all_years";
    }

    $fileName .= "_" . now()->format('His') . ".xlsx";

    return Excel::download(
        new AdminTradingJournalExport($userId, $month, $year),
        $fileName
    );
}


}
