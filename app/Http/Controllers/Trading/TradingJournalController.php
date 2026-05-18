<?php

namespace App\Http\Controllers\Trading;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\TradingPair;
use App\Models\TradingJournal;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TradingJournalExport;
use App\Models\Capital;
use App\Models\User;

use App\Models\FeatureToggle;
use Illuminate\Support\Facades\Schema;
use App\Services\PropFirmEvaluator;
use Illuminate\Support\Facades\Mail; // ← Add this
use App\Mail\PropFirmNotificationMail; // ✅ correct import
use App\Exports\TradesTemplateExport;
use App\Imports\TradesImport;
use App\Exports\AdminTradingJournalExport;
use App\Models\PropFirmEvaluationQuestion;

class TradingJournalController extends Controller
{
    /**
     * Logged-in user's monthly trading journal + Prop Firm evaluation
     */
public function AllTradingJournal(Request $request)
    {
    $feature = FeatureToggle::where('feature_name', 'propfirm')->first();
    $featureEnabled = $feature ? (bool)$feature->enabled : false;


        $userId = Auth::id();
    $currentUser = auth()->user();
    $propFirmLockMessage = $this->propFirmLockMessage($currentUser);
    $pendingEvaluationQuestions = PropFirmEvaluationQuestion::where('user_id', $userId)
        ->where('status', PropFirmEvaluationQuestion::STATUS_OPEN)
        ->latest()
        ->get();

        // Selected month/year (default: current)

  $monthIn = $request->input('month');
    $yearIn  = $request->input('year');

    // Normalize values
    $month = $monthIn === 'all' ? 'all' : (int)($monthIn ?? now()->month);
    $year  = $yearIn  === 'all' ? 'all' : (int)($yearIn  ?? now()->year);

    // Always define these to avoid "Undefined variable"
    $startDate = null;
    $endDate   = null;

    $query = TradingJournal::where('user_id', $userId)
        ->where(function ($tradeQuery) {
            $tradeQuery->where('type', 'trade')->orWhereNull('type');
        });

    if ($month !== 'all' && $year !== 'all') {
        // Specific month in a specific year
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate   = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $query->whereBetween('open_date', [$startDate, $endDate]);

    } elseif ($month === 'all' && $year !== 'all') {
        // Whole year
        $startDate = Carbon::createFromDate($year, 1, 1)->startOfYear();
        $endDate   = Carbon::createFromDate($year, 12, 1)->endOfYear();
        $query->whereYear('open_date', $year);

    } elseif ($month !== 'all' && $year === 'all') {
        // Specific month across all years
        $query->whereMonth('open_date', $month);

        // Optional: derive min/max for display (prevents nulls in your view)
        $min = TradingJournal::where('user_id', $userId)->whereMonth('open_date', $month)->min('open_date');
        $max = TradingJournal::where('user_id', $userId)->whereMonth('open_date', $month)->max('open_date');
        $startDate = $min ? Carbon::parse($min)->startOfDay() : null;
        $endDate   = $max ? Carbon::parse($max)->endOfDay()   : null;

    } else {
        // month = all, year = all → no date filter
        // Optional: derive min/max for display
        $min = TradingJournal::where('user_id', $userId)->min('open_date');
        $max = TradingJournal::where('user_id', $userId)->max('open_date');
        $startDate = $min ? Carbon::parse($min)->startOfDay() : null;
        $endDate   = $max ? Carbon::parse($max)->endOfDay()   : null;
    }

    // If both "all" → no filter applied, show everything

        // Journals for the logged-in user in the selected month
        $journals = $query->orderBy('close_date')->get();

        $totalTrades = $journals->count();

        // Capital (deposits/withdrawals lifetime; adjust if you track by date)
     // Capital (deposits/withdrawals lifetime; adjust if you track by date)
$totalDeposits    = Capital::where('user_id', $userId)->where('type', 1)->sum('amount');
$totalWithdrawals = abs(Capital::where('user_id', $userId)->where('type', 2)->sum('amount'));
$netPL          = $journals->sum('profit_loss');  // Net PnL of all trades
$initialCapital = $totalDeposits;                 // Deposits as starting capital
$currentBalance = $totalDeposits + $netPL - $totalWithdrawals;
 // 🚫 Backend guard: block if feature disabled
    // ======================================================

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
// ---------------- Consistency Evaluation (FundingPips 15% rule) ----------------

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

        if ($totalTrades == 0) {
            $winRateGrade = $rrrGrade = $growthGrade = $drawdownGrade = $consistencyGrade = $expectancyGrade = 'N/A';
            $winRatePoints = $rrrPoints = $growthPoints = $drawdownPenalty = $consistencyPoints = $expectancyPoints = 0;
            $totalScore = 0;
            $rating = 'N/A';
        } else {
            // Win Rate
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
            // RRR
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
    $averageRRR > 0.5 && $averageRRR <= 1.15   => [5,  'E'],
    default             => [0, 'F'],
                };
            } else {
                [$rrrPoints, $rrrGrade] = [0, 'F'];
            }

            // Growth (max 5)
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


            // Final Score & Rating
$totalScore = $winRatePoints + $rrrPoints + $growthPoints + $consistencyPoints + $expectancyPoints - $drawdownPenalty;

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
// Extra Meaning Mapping (performance bands - updated ranges)
$performanceMeaning = match (true) {
    $totalScore >= 85 => 'Exceptional: Elite Trading Skills — Outstanding performance across all metrics.',
    $totalScore >= 70 => 'Good: Solid Trading Foundation — Strong performance with minor areas for improvement.',
    $totalScore >= 50 => 'Intermediate: Developing Trader — Adequate performance with clear development opportunities.',
    $totalScore >= 39 => 'Below Average: Need Improvement — Basic competency with significant areas needing attention.',
    $totalScore >= 0  => 'Poor: Requires Significant Development — Fundamental trading skills requiring immediate development.',
    default           => 'N/A',
};

            // Optional post-adjustments (kept from your logic as needed)
            // ...
        }
    // 🚫 Prop Firm Evaluation Block
   // 🚫 Prop Firm Evaluation Block
// ======================================================
if ($featureEnabled) {
    if ($initialCapital > 0 && $journals->count() >= 0) {
        // 📌 Current phase (default Phase 1 if null)
        $currentPhase = $currentUser->prop_firm_phase ?? 1;

        $evaluation['profitable_days'] = [
            'count'    => 0,
            'limit'    => 10,
            'breached' => false
        ];

        // 📌 Load Phase 2 starting balance (persisted from Phase 1 PASS)
        $phase2StartingBalance =
            (Schema::hasColumn('users', 'phase2_start_balance') && $currentUser->phase2_start_balance)
                ? (float) $currentUser->phase2_start_balance
                : (float) $initialCapital;

        // -------- Phase rules --------
        if ($currentPhase == 1) {
            $phaseRules = [
                'phase'               => 1,
                'starting_balance'    => (float) $initialCapital,
                'profit_target'       => (float) ($request->input('profit_target', 10)),  // %
                'max_daily_loss'      => (float) ($request->input('max_daily_loss', 5)), // %
                'max_total_loss'      => (float) ($request->input('max_total_loss', 10)),// %
                'max_days'            => (int)   ($request->input('max_days', 15)),
                // 'consistency_limit'   => 1200,
                'min_profitable_days' => 3,
            ];
            $startingBalance = $phaseRules['starting_balance'];
            $pnlSum          = $netPL; // Phase 1 uses all trades
        } else {
            $phaseRules = [
                'phase'               => 2,
                'starting_balance'    => $phase2StartingBalance,
                'profit_target'       => (float) ($request->input('profit_target', 5)),   // %
                'max_daily_loss'      => (float) ($request->input('max_daily_loss', 5)),  // %
                'max_total_loss'      => (float) ($request->input('max_total_loss', 10)), // %
                'max_days'            => (int)   ($request->input('max_days', 30)),
                // 'consistency_limit'   => 1200,
                'min_profitable_days' => 3,
            ];
            $startingBalance = $phaseRules['starting_balance'];
            $pnlSum          = $currentBalance - $phase2StartingBalance; // Phase 2 uses only Phase 2 trades
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

        // -------- Profitable Day (≥ 0.5% of initial capital) --------
        $profitableDayThreshold = $initialCapital * 0.005; // 0.5%
        $profitableDays = $dailyPnL->filter(fn ($pnl) => $pnl >= $profitableDayThreshold);
        $currentProfitableDays = $profitableDays->count();
        $requiredProfitableDays = $phaseRules['min_profitable_days'] ?? 3;
        $hasProfitableDay = $currentProfitableDays >= $requiredProfitableDays;

        // // -------- Consistency Rule --------
        // $consistencyLimit    = 750; // adjust per your policy
        // $consistencyBreached = $stdDeviation > $consistencyLimit;

        // -------- Time Limit --------
        $firstClose = $journals->min('close_date');
        $lastClose  = $journals->max('close_date');
        $daysPassed = ($firstClose && $lastClose)
            ? Carbon::parse($firstClose)->diffInDays(Carbon::parse($lastClose)) + 1
            : 0;
        $withinTimeLimit = $daysPassed <= (int) $phaseRules['max_days'];

        // -------- Evaluation Result --------
        $evaluation = [
            'phase'            => $phaseRules['phase'],
            'rules'            => $phaseRules,
            'starting_balance' => $startingBalance,
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

            'profitable_day' => [
                'threshold'          => round($profitableDayThreshold, 2),
                'profitable_days'    => $profitableDays->count(),
                'required_days'      => $requiredProfitableDays,
                'has_profitable_day' => (bool) $hasProfitableDay,
                'status_label'       => $hasProfitableDay ? '✅ Achieved' : '⏳ Pending',
            ],
        ];

        // -------- Status --------
        if ($journals->count() === 0 || $startingBalance <= 0) {
            $evaluation['status'] = 'PENDING';

        } elseif ($evaluation['max_daily_loss']['breached'] || $evaluation['max_total_loss']['breached']) {
            $evaluation['status'] = 'FAIL';

            // ✅ Determine the breach reason
            $breachReason = 'Prop Firm evaluation breach';
            if ($evaluation['max_daily_loss']['breached']) {
                $breachReason = 'Daily Loss Limit Breached';
            } elseif ($evaluation['max_total_loss']['breached']) {
                $breachReason = 'Total Loss Limit Breached';
            }

            // ✅ Send FAIL email
            $details = [
                'subject'          => '⚠️ Prop Firm Breach Alert',
                'user_name'        => $currentUser->name,
                'status'           => 'FAIL',
                'account_number'   => $currentUser->username,
                'breach_rule'      => $breachReason,
                'violated_at'      => now()->format('F d, Y H:i'),
                'max_allowed_risk' => $evaluation['max_daily_loss']['limit_amount'],
                'max_open_risk'    => $evaluation['max_daily_loss']['worst_day_pnl'],
                'url'              => url('/login'),
            ];

            try {
                Mail::to($currentUser->email)->send(new PropFirmNotificationMail($details));
            } catch (\Exception $e) {
                // \Log::error('FAIL Mail send failed: ' . $e->getMessage());
            }

            $currentUser->status = 0;
            $currentUser->save();
            auth()->logout();

            return redirect()->route('login')
                ->with('error', '⚠️ Your account has been terminated due to ' . $breachReason . '.');

        } elseif (
            ($evaluation['profit_target']['passed'] ?? false) &&
            ($evaluation['profitable_day']['has_profitable_day'] ?? false)
        ) {
            $evaluation['status'] = 'PASS';

            // ✅ Backup journals
            if ($this->isPropFirmReviewPending($currentUser)) {
                $evaluation['status'] = 'UNDER_REVIEW';
                $evaluation['message'] = 'Your evaluation has passed and is waiting for administration review.';
            } elseif ($currentPhase == 1) {
                // ✅ Phase 1 PASS → Promote to Phase 2
                $passDetails = [
                    'subject'        => '🎉 Phase 1 Completed - Welcome to Phase 2!',
                    'user_name'      => $currentUser->name ?? $currentUser->username,
                    'status'         => 'UNDER_REVIEW',
                    'account_number' => $currentUser->username,
                    'phase'          => 1,
                    'next_phase'     => 2,
                    'url'            => url('/dashboard'),
                ];
                try {
                    Mail::to($currentUser->email)->send(new PropFirmNotificationMail($passDetails));
                } catch (\Exception $e) {
                    // \Log::error('PASS Mail send failed: ' . $e->getMessage());
                }

                if (Schema::hasColumn('users', 'phase2_start_balance')) {
                    $currentUser->phase2_start_balance = $currentEvalBal;
                }
                $currentUser->prop_firm_review_status = 'pending_phase2';
                $currentUser->prop_firm_review_phase = 1;
                $currentUser->prop_firm_trade_locked = true;
                $currentUser->prop_firm_review_note = 'Phase 1 passed. Awaiting administration approval before Phase 2 access.';
                $currentUser->prop_firm_review_requested_at = now();
                $currentUser->prop_firm_review_approved_at = null;
                $currentUser->save();

                $evaluation['status'] = 'UNDER_REVIEW';
                $evaluation['message'] = 'Phase 1 passed. Trading is locked until administration approves Phase 2.';

            } elseif ($currentPhase == 2) {
                // ✅ Phase 2 PASS → Funded
                $fundedDetails = [
                    'subject'        => '🎉 Congratulations! You Are Now a Funded Trader',
                    'user_name'      => $currentUser->name ?? $currentUser->username,
                    'status'         => 'UNDER_REVIEW',
                    'account_number' => $currentUser->username,
                    'phase'          => 2,
                    'url'            => url('/dashboard'),
                ];
                try {
                    Mail::to($currentUser->email)->send(new PropFirmNotificationMail($fundedDetails));
                } catch (\Exception $e) {
                    // \Log::error('FUNDED Mail send failed: ' . $e->getMessage());
                }

    $currentUser->funded_status = 0; // pending
                $currentUser->prop_firm_review_status = 'pending_funded';
                $currentUser->prop_firm_review_phase = 2;
                $currentUser->prop_firm_trade_locked = true;
                $currentUser->prop_firm_review_note = 'Phase 2 passed. Awaiting administration approval before funded account access.';
                $currentUser->prop_firm_review_requested_at = now();
                $currentUser->prop_firm_review_approved_at = null;
                $currentUser->save();

                $evaluation['status'] = 'UNDER_REVIEW';
                $evaluation['message'] = 'Phase 2 passed. Trading is locked until administration approves the funded account.';
            }

        } else {
            $evaluation['status'] = 'PENDING';
        }

    } else {
        $evaluation = [
            'status'  => 'N/A',
            'message' => 'Prop Firm Evaluation is not applicable at this time.'
        ];
    }
} else {
    $evaluation = [
        'status'  => 'DISABLED',
        'message' => 'ℹ️ Prop Firm Evaluation is currently disabled. Trades are still recorded without evaluation.'
    ];
}

        // Breadcrumb
        $breadcrumbData = [['label' => 'Trading Journal', 'url' => route('all.trading.journals')]];

// -------- Return view with all variables --------
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
    'expectancy',
    'expectancyPoints',
    'expectancyGrade',
    'featureEnabled',
    'consistencyPercent',
    'propFirmLockMessage',
    'pendingEvaluationQuestions',
    'evaluation' // <-- pass the whole evaluation array to Blade
));
    }


    // Show form to add a new journal entry
    public function AddTradingJournal()
    {
        if ($message = $this->propFirmLockMessage(auth()->user())) {
            return redirect()->route('all.trading.journals')->with('error', $message);
        }

        $tradingPairs = TradingPair::orderBy('symbol')->get();

        return view('admin.trading_journals.journal_add', compact('tradingPairs'));
    }
public function StoreTradingJournal(Request $request)
{
    if ($message = $this->propFirmLockMessage($request->user())) {
        return redirect()->route('all.trading.journals')->with('error', $message);
    }

    $balance = $this->getUserCapitalBalance(Auth::id());

    $validated = $request->validate([
        'open_date'      => 'required|date',
        'close_date'     => 'required|date|after_or_equal:open_date',
        'pair'           => 'required|string|max:255',
        'direction'      => 'required|in:1,2',
        'entry_price'    => 'required|numeric',
        'exit_price'     => 'required|numeric',
        'lot_size'       => 'required|numeric|min:0',
        'pips'           => 'nullable|numeric',
        'profit_loss'    => 'nullable|numeric',
        'result'         => 'nullable|in:1,2,3',
        'notes'          => 'nullable|string',
        'duplicate_count'=> 'nullable|integer|min:1|max:500',
    ], [
        'close_date.after_or_equal' => 'The close trade time must be the same as or after the open trade time.',
    ]);

    $count   = $validated['duplicate_count'] ?? 1;
    $userId  = Auth::id();

    if ($balance <= 0) {
        return redirect()->back()->withInput()->withErrors(['error' => 'You cannot record a trade without any available capital.']);
    }

    $tradingPair = TradingPair::whereRaw('UPPER(symbol) = ?', [strtoupper($validated['pair'])])->first();
    if (!$tradingPair) {
        return redirect()->back()->withInput()->withErrors(['pair' => 'Selected trading pair not found in the system.']);
    }

    $pipFactor = max((float) ($tradingPair->pip_factor ?? 1), 0.00000001);
    $pips = abs($validated['exit_price'] - $validated['entry_price']) / $pipFactor;
    $pips = round($pips, (int) ($tradingPair->pip_decimal ?? 0));
    $profitLoss = $this->calculateJournalProfitLoss($pips, (float) $validated['lot_size'], $validated['result'] ?? null, $validated['profit_loss'] ?? 0);
    $openDate = Carbon::parse($validated['open_date'])->format('Y-m-d H:i:s');
    $closeDate = Carbon::parse($validated['close_date'])->format('Y-m-d H:i:s');

    $skipped = 0;

    for ($i = 0; $i < $count; $i++) {
        $balance += $profitLoss;

        if ($balance < 0) {
            $skipped++;
            break;
        }

        TradingJournal::create([
            'user_id'      => $userId,
            'type'         => 'trade',
            'open_date'    => $openDate,
            'close_date'   => $closeDate,
            'pair'         => $tradingPair->symbol,
            'direction'    => $validated['direction'],
            'entry_price'  => $validated['entry_price'],
            'exit_price'   => $validated['exit_price'],
            'lot_size'     => $validated['lot_size'],
            'pips'         => $pips,
            'profit_loss'  => $profitLoss,
            'result'       => $validated['result'] ?? null,
            'notes'        => $validated['notes'] ?? null,
        ]);
    }

    $message = 'Trade journal added successfully!';
    $alert   = 'success';

    if ($count >= 1 && $skipped > 0) {
        $message .= " However, {$skipped} trade(s) were not recorded because they would result in a negative balance.";
        $alert = 'warning';
    }

    $notification = [
        'message'     => $message,
        'alert-type'  => $alert,
    ];

    return redirect()->route('all.trading.journals')->with($notification);
}

private function calculateJournalProfitLoss(float $pips, float $lotSize, $result, $fallbackProfitLoss = 0): float
{
    $profitLoss = $pips * $lotSize * 10;

    return match ((string) $result) {
        '1' => round(abs($profitLoss), 2),
        '2' => round(-abs($profitLoss), 2),
        '3' => 0.00,
        default => round((float) $fallbackProfitLoss, 2),
    };
}

public function EditTradingJournal($id)
{
    if ($message = $this->propFirmLockMessage(auth()->user())) {
        return redirect()->route('all.trading.journals')->with('error', $message);
    }

    $journal = TradingJournal::where('user_id', Auth::id())->findOrFail($id);
    $tradingPairs = TradingPair::orderBy('symbol')->get();

    return view('admin.trading_journals.journal_edit', compact('journal', 'tradingPairs'));
}

public function UpdateTradingJournal(Request $request, $id)
{
    if ($message = $this->propFirmLockMessage($request->user())) {
        return redirect()->route('all.trading.journals')->with('error', $message);
    }

    $journal = TradingJournal::where('user_id', Auth::id())->findOrFail($id);

    $validated = $request->validate([
        'open_date'      => 'required|date',
        'close_date'     => 'required|date|after_or_equal:open_date',
        'pair'           => 'required|string|max:255',
        'direction'      => 'required|in:1,2',
        'entry_price'    => 'required|numeric',
        'exit_price'     => 'required|numeric',
        'lot_size'       => 'required|numeric|min:0',
        'pips'           => 'nullable|numeric',
        'profit_loss'    => 'nullable|numeric',
        'result'         => 'nullable|in:1,2,3',
        'notes'          => 'nullable|string',
    ], [
        'close_date.after_or_equal' => 'The close trade time must be the same as or after the open trade time.',
    ]);

    $tradingPair = TradingPair::whereRaw('UPPER(symbol) = ?', [strtoupper($validated['pair'])])->first();
    if (!$tradingPair) {
        return redirect()->back()->withInput()->withErrors(['pair' => 'Selected trading pair not found in the system.']);
    }

    $pipFactor = max((float) ($tradingPair->pip_factor ?? 1), 0.00000001);
    $pips = abs($validated['exit_price'] - $validated['entry_price']) / $pipFactor;
    $pips = round($pips, (int) ($tradingPair->pip_decimal ?? 0));
    $profitLoss = $this->calculateJournalProfitLoss($pips, (float) $validated['lot_size'], $validated['result'] ?? null, $validated['profit_loss'] ?? 0);
    $balanceExcludingTrade = $this->getUserCapitalBalance(Auth::id()) - (float) $journal->profit_loss;

    if (($balanceExcludingTrade + $profitLoss) < 0) {
        return redirect()->back()
            ->withInput()
            ->withErrors(['profit_loss' => 'This update would result in a negative account balance.']);
    }

    $journal->update([
        'open_date'    => Carbon::parse($validated['open_date'])->format('Y-m-d H:i:s'),
        'close_date'   => Carbon::parse($validated['close_date'])->format('Y-m-d H:i:s'),
        'pair'         => $tradingPair->symbol,
        'direction'    => $validated['direction'],
        'entry_price'  => $validated['entry_price'],
        'exit_price'   => $validated['exit_price'],
        'lot_size'     => $validated['lot_size'],
        'pips'         => $pips,
        'profit_loss'  => $profitLoss,
        'result'       => $validated['result'] ?? null,
        'notes'        => $validated['notes'] ?? null,
    ]);

    return redirect()->route('all.trading.journals')->with([
        'message' => 'Trade journal updated successfully!',
        'alert-type' => 'success',
    ]);
}

public function TradingJournalDetails($id)
{
    $journal = TradingJournal::where('user_id', Auth::id())->findOrFail($id);
    $tradingPair = TradingPair::whereRaw('UPPER(symbol) = ?', [strtoupper($journal->pair)])->first();

    return view('admin.trading_journals.journal_view', compact('journal', 'tradingPair'));
}

public function DeleteTradingJournal($id)
{
    if ($message = $this->propFirmLockMessage(auth()->user())) {
        return redirect()->route('all.trading.journals')->with('error', $message);
    }

    $journal = TradingJournal::where('user_id', Auth::id())->findOrFail($id);
    $journal->delete();

    return redirect()->route('all.trading.journals')->with([
        'message' => 'Trade journal deleted successfully!',
        'alert-type' => 'success',
    ]);
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
// ---------------- Consistency Evaluation (R-multiple based) ----------------

// Extract profits and lots
$profitsArray = $journals->pluck('profit_loss')->toArray();
$lotSizes     = $journals->pluck('lot_size')->toArray();
// Optional: If you have SL in pips, replace this with $journals->pluck('sl_pips')->toArray();
$totalTrades  = count($profitsArray);

$rMultiples = [];

// --- Method 1: If you store stop loss (best practice) ---
// foreach ($journals as $j) {
//     if ($j->sl_pips > 0 && $j->lot_size > 0) {
//         $pipValue = 10; // For XAUUSD 1 lot ≈ $10/pip (adjust if needed)
//         $riskAmount = $j->sl_pips * $j->lot_size * $pipValue;
//         $rMultiples[] = $j->profit_loss / $riskAmount;
//     }
// }

// --- Method 2: Fallback if no stop loss stored (estimate risk = avg loss) ---
$losses = array_filter($profitsArray, fn($pl) => $pl < 0);
$avgLoss = count($losses) > 0 ? abs(array_sum($losses) / count($losses)) : 0;

foreach ($profitsArray as $pl) {
    if ($avgLoss > 0) {
        $rMultiples[] = $pl / $avgLoss; // normalize as R-multiple
    }
}

// Calculate StdDev of R-multiples
$avgValue = count($rMultiples) > 0 ? array_sum($rMultiples) / count($rMultiples) : 0;

$variance = count($rMultiples) > 1
    ? array_sum(array_map(fn($val) => pow($val - $avgValue, 2), $rMultiples)) / (count($rMultiples) - 1)
    : 0;

$stdDeviation = round(sqrt($variance), 2);

// ---------------- Grading (based on StdDev of R) ----------------
[$consistencyPoints, $consistencyGrade] = ($totalTrades >= 1 && is_numeric($stdDeviation)) ? match (true) {
    $stdDeviation <= 0.5   => [25, 'A+'], // very consistent execution
    $stdDeviation <= 1.0   => [20, 'A'],
    $stdDeviation <= 1.5   => [15, 'A-'],
    $stdDeviation <= 2.0   => [10, 'B'],
    $stdDeviation <= 2.5   => [5,  'C'],
    $stdDeviation <= 3.0   => [2,  'D'],
    $stdDeviation <= 3.5   => [1,  'E'],
    default                => [0,  'F'],
} : [0, 'N/A'];

// ---------------- Convert to Percentage (0–100%) ----------------
if ($totalTrades >= 1 && is_numeric($stdDeviation)) {
    $maxThreshold = 3.5; // >=3R StdDev = 0%
    $consistencyPercent = max(0, 100 * ($maxThreshold - $stdDeviation) / $maxThreshold);
    $consistencyPercent = round($consistencyPercent, 2);
} else {
    $consistencyPercent = 0;
}

       // ✅ Grading Components
    if ($totalTrades == 0) {
        $winRateGrade = $rrrGrade = $growthGrade = $drawdownGrade = $consistencyGrade = $expectancyGrade = 'N/A';
        $winRatePoints = $rrrPoints = $growthPoints = $drawdownPenalty = $consistencyPoints = $expectancyPoints = 0;
        $totalScore = 0;
        $rating = 'N/A';
    } else {
        // Win Rate
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
     
        // RRR
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

            [$growthPoints, $growthGrade] = match (true) {
$growthPercent >= 15   => [10, 'A'],  // was 10
    $growthPercent >= 7.5  => [7,  'B'],  // was 5
    $growthPercent >= 4.5  => [5,  'C'],  // was 3
    $growthPercent >= 1.5  => [3,  'D'],  // was 1
    $growthPercent > 0     => [1,  'E'],
    default                => [0,  'F'],
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
 $expectancy >= 40  => [10, 'A+'],  // was 20
    $expectancy >= 20  => [7,  'B'],   // was 10
    $expectancy >= 10  => [5,  'C'],   // was 5
    $expectancy >= 0   => [0,  'N/A'],   // same
    $expectancy >= -10 => [-1, 'F'],   // was -5
    $expectancy >= -20 => [-2, 'F'],   // was -10
    default            => [0, 'N/A'],
            };

            $totalScore = $winRatePoints + $rrrPoints + $growthPoints + $consistencyPoints + $expectancyPoints - $drawdownPenalty;
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



public function importTrades(Request $request)
{
    if ($message = $this->propFirmLockMessage($request->user())) {
        return redirect()->route('all.trading.journals')->with('error', $message);
    }

    $request->validate([
        'file' => 'required|mimes:xlsx,xls,csv|max:2048',
    ]);

    try {
        // Convert file to collection for checking empty
        $collection = Excel::toCollection(new TradesImport(Auth::id()), $request->file('file'));

        if ($collection->isEmpty() || $collection[0]->isEmpty()) {
            return back()->with('error', 'The Excel file is empty.');
        }

        // Perform the import and assign current user ID
        Excel::import(new TradesImport(Auth::id()), $request->file('file'));

    } catch (\Exception $e) {
        // Catch any exception and return as error
        return back()->with('error', 'Import failed: ' . $e->getMessage());
    }

    return redirect()->back()->with('success', 'Trades imported successfully.');
}

public function answerPropFirmQuestion(Request $request, PropFirmEvaluationQuestion $question)
{
    abort_unless(auth()->check() && (int) $question->user_id === (int) auth()->id(), 403);

    $data = $request->validate([
        'answer' => ['required', 'string', 'max:5000'],
    ]);

    $question->update([
        'answer' => $data['answer'],
        'status' => PropFirmEvaluationQuestion::STATUS_ANSWERED,
        'answered_at' => now(),
    ]);

    return redirect()
        ->route('all.trading.journals')
        ->with('success', 'Your prop firm evaluation answer has been submitted.');
}

public function DownloadTemplate()
{
    $fileName = 'trading_journal_template.xlsx';
    return Excel::download(new TradesTemplateExport, $fileName);
}

private function isPropFirmReviewPending(?User $user): bool
{
    if (! $user) {
        return false;
    }

    return in_array((string) ($user->prop_firm_review_status ?? 'none'), [
        'pending_phase2',
        'pending_funded',
    ], true);
}

private function propFirmLockMessage(?User $user): ?string
{
    if (! $user || ! (bool) ($user->prop_firm_trade_locked ?? false)) {
        return null;
    }

    $status = (string) ($user->prop_firm_review_status ?? 'none');

    if ($status === 'pending_phase2') {
        return 'Phase 1 has passed and your account is under administration review. Trade recording, editing, deleting, and Excel import are locked until Phase 2 is approved.';
    }

    if ($status === 'pending_funded') {
        return 'Phase 2 has passed and your funded account is under administration review. Trade recording, editing, deleting, and Excel import are locked until the funded account is approved.';
    }

    return 'Your prop firm trading activity is currently locked pending administration review.';
}
}
