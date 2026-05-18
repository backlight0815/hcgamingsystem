<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SignalPerformance;
use App\Models\Community;
use App\Models\User;
use App\Models\TradingSignalDiscord;
use App\Services\DiscordService;
use Illuminate\Support\Collection;
use Carbon\Carbon;
// ✅ Add these two imports for Excel export
use App\Exports\SignalPerformanceExport;
use App\Exports\SignalPerformancesTemplateExport;
use App\Imports\SignalPerformanceImport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;


class SignalPerformanceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
  public function index(Request $request)
{
    $breadcrumbData = [
        ['label' => 'Dashboard', 'url' => route('signal.performance.index')],
        ['label' => 'Signal Performances', 'url' => route('signal.performance.index')],
    ];

    $authUser = auth()->user();
    $canViewAll = $this->canViewAllPerformances();
    $from_date = $request->from_date;
    $to_date   = $request->to_date;

    $communities = Community::where('status', 1)
        ->orderBy('name')
        ->get();
    $providers = $this->signalProviders();

    // 1️⃣ Get filtered performances first
    $performances = $this->getFilteredPerformances($request);

    // 2️⃣ Evaluate summary AFTER performances are retrieved
    $summary = $this->evaluateSignalPerformance($performances);
    $scoreBreakdown = $this->scoreBreakdown($summary);
    $selectedProvider = $canViewAll && $request->filled('user_id')
        ? $providers->firstWhere('id', (int) $request->user_id)
        : $authUser;
    $activeFilters = [
        'from_date' => $from_date,
        'to_date' => $to_date,
        'community_id' => $request->community_id,
        'community_tag' => $request->community_tag,
        'user_id' => $request->user_id,
    ];


// Extract all variables
$totalTrades        = $summary['totalTrades'];
$totalWinTrades     = $summary['totalWinTrades'];
$totalLoseTrades    = $summary['totalLoseTrades'];
$totalPips          = $summary['totalPips'];
$totalProfitPips    = $summary['totalProfitPips'];
$totalLossPips      = $summary['totalLossPips'];
$averageProfit      = $summary['averageProfit'];
$averageLoss        = $summary['averageLoss'];
$winRate            = $summary['winRate'];
$rrRatio            = $summary['rrRatio'];
$profitFactor       = $summary['profitFactor'];
$expectancy         = $summary['expectancy'];
$winRatePoints      = $summary['winRatePoints'];
$rrrPoints          = $summary['rrrPoints'];
$pfPoints           = $summary['pfPoints'];
$expectancyPoints   = $summary['expectancyPoints'];
$score              = $summary['totalScore'];
$grade              = $summary['providerLevel'];
$riskRewardFormatted = $rrRatio . ' : 1';

// Map summary points to the Blade variables you used
$scoreWinRate       = $winRatePoints;
$scoreRR            = $rrrPoints;
$profitFactorPoints = $pfPoints;
$scoreExpectancy    = $expectancyPoints;
    // Reward defaults
    $rewardEligible = false;
    $rewardAmount   = 0;
    $rewardReason   = 'Reward system is currently disabled';

    if (feature_enabled('signal_payout')) {
        $rewardReason = 'Not eligible for reward';

        if ($score >= 60 && !in_array($grade, ['Unqualified Signal Provider'])) {
            $rewardEligible = true;
            $rewardReason   = 'Qualified based on total score';

            $rewardAmount = match ($grade) {
                'Expert Signal Provider'          => 150,
                'Master / Expert Signal Provider' => 150,
                'Senior Signal Provider'          => 100,
                'Junior Signal Provider'          => 50,
                'Intern Signal Provider'          => 20,
                default                             => 0,
            };
        }
    }

  return view('admin.signal_performance.signal_performance', compact(
    'performances',
    'communities',
    'providers',
    'canViewAll',
    'selectedProvider',
    'activeFilters',
    'scoreBreakdown',
    'summary',
    'from_date',
    'to_date',
    'breadcrumbData',
    'score',
    'grade',
    'scoreWinRate',
    'scoreRR',
    'profitFactorPoints',
    'scoreExpectancy',
    'riskRewardFormatted',
    'totalTrades',
    'totalWinTrades',
    'totalLoseTrades',
    'totalPips',
    'totalProfitPips',
    'totalLossPips',
    'averageProfit',
    'averageLoss',
    'winRate',
    'rrRatio',
    'profitFactor',
    'expectancy',
    'winRatePoints',
    'rrrPoints',
    'pfPoints',
    'expectancyPoints'
));
}

private function canViewAllPerformances(): bool
{
    $user = auth()->user();

    return $user && in_array((int) $user->role_id, [1, 2, 999], true);
}

private function signalProviders()
{
    return User::whereIn('role_id', [201, 202])
        ->orderBy('username')
        ->get();
}

private function scoreBreakdown(array $summary): array
{
    return [
        [
            'name' => 'Win Rate',
            'value' => number_format($summary['winRate'] ?? 0, 2) . '%',
            'points' => (int) ($summary['winRatePoints'] ?? 0),
            'max' => 30,
            'grade' => $summary['winRateGrade'] ?? 'N/A',
            'description' => 'Measures the percentage of profitable signal outcomes. Passing starts at 50%; elite performance begins at 75%.',
        ],
        [
            'name' => 'Risk Reward',
            'value' => number_format($summary['rrRatio'] ?? 0, 2) . ' : 1',
            'points' => (int) ($summary['rrrPoints'] ?? 0),
            'max' => 30,
            'grade' => $summary['rrrGrade'] ?? 'N/A',
            'description' => 'Compares average winning pips against average losing pips. Higher ratios show stronger reward capture per unit of risk.',
        ],
        [
            'name' => 'Profit Factor',
            'value' => number_format($summary['profitFactor'] ?? 0, 2),
            'points' => (int) ($summary['profitFactorPoints'] ?? ($summary['pfPoints'] ?? 0)),
            'max' => 20,
            'grade' => $summary['profitFactorGrade'] ?? 'N/A',
            'description' => 'Measures gross winning pips divided by gross losing pips. Values above 1.00 indicate net-positive signal performance.',
        ],
        [
            'name' => 'Expectancy',
            'value' => number_format($summary['expectancy'] ?? 0, 2),
            'points' => (int) ($summary['expectancyPoints'] ?? 0),
            'max' => 20,
            'grade' => $summary['expectancyGrade'] ?? 'N/A',
            'description' => 'Estimates the quality of the edge by combining win rate and reward-to-risk behavior.',
        ],
    ];
}

    /*
    |--------------------------------------------------------------------------
    | DISCORD DAILY & WEEKLY
    |--------------------------------------------------------------------------
    */
    private function buildDiscordMessage(Collection $performances, Request $request, bool $isWeekly = false): string
    {
        $title = $isWeekly ? "📊 Weekly Signal Performance" : "📊 Daily Signal Performance";
        $dateRange = $isWeekly 
            ? ($request->from_date && $request->to_date ? "{$request->from_date} - {$request->to_date}" : now()->format('d/m/Y'))
            : now()->format('d/m/Y');

        $message = "{$title} ({$dateRange})\n\n";

        foreach ($performances as $perf) {
            $statusEmoji = $perf->profit_pips > 0 ? "✅" : "❌";
            $symbol      = $perf->signal->symbol ?? '-';
            $type        = $perf->signal->type ?? '-';
            $message    .= "🌍 [WA] {$symbol} {$type} NOW {$statusEmoji} {$perf->profit_pips} pips\n";
        }

        $summary = $this->evaluateSignalPerformance($performances);

        $message .= "\n📈 Summary\n";
        $message .= "Total Trades: {$summary['totalTrades']}\n";
        $message .= "Wins: {$summary['totalWinTrades']}\n";
        $message .= "Losses: {$summary['totalLoseTrades']}\n";
        $message .= "Win Rate: {$summary['winRate']}%\n";
        $message .= "Total Pips: {$summary['totalPips']}\n";
        $message .= "RR Ratio: {$summary['rrRatio']} : 1\n";

        return $message;
    }

  public function sendDiscord(Request $request)
{
    $from = $request->from_date;
    $to   = $request->to_date;

    $authUser   = auth()->user();
    $canViewAll = $this->canViewAllPerformances();

    // ----------------------------------------
    // Discord communities filter
    // ----------------------------------------
    $discordCommunities = Community::query()
        ->when($request->community_id, fn($q) => $q->where('id', $request->community_id))
        ->when($request->community_tag, fn($q) => $q->where('community_tag', 'like', "%{$request->community_tag}%"))
        ->whereNotNull('discord_webhook_signal')
        ->get();

    if ($discordCommunities->isEmpty()) {
        return redirect()->route('signal.performance.index')->with([
            'message' => 'No Discord communities found for the selected filters.',
            'alert-type' => 'warning',
        ]);
    }

    // ----------------------------------------
    // Build performances query
    // ----------------------------------------
    $performancesQuery = SignalPerformance::with(['signal.user', 'signal.discordCommunity.community'])
        ->latest()

        // Date filter
        ->when($from && $to, fn($q) => $q->whereDate('created_at', '>=', $from)
                                         ->whereDate('created_at', '<=', $to))

        // Community filter
        ->whereHas('signal.discordCommunity', fn($q) =>
            $q->whereIn('community_id', $discordCommunities->pluck('id')->toArray())
        )

        // -----------------------------
        // Username filter (STRICT)
        // -----------------------------
        ->when($request->filled('user_id'), fn($q) =>
            $q->whereHas('signal', fn($q2) =>
                $q2->where('user_id', $request->user_id)
            )
        )

        // -----------------------------
        // Non-admins can only see their own signals
        // -----------------------------
        ->when(!$canViewAll, fn($q) =>
            $q->whereHas('signal', fn($q2) =>
                $q2->where('user_id', $authUser->id)
            )
        );

    $performances = $performancesQuery->get();

    if ($performances->isEmpty()) {
        return redirect()->route('signal.performance.index')->with([
            'message' => 'No signal performances found for the selected filters.',
            'alert-type' => 'warning',
        ]);
    }

    // ----------------------------------------
    // Discord webhook (take first matched community)
    // ----------------------------------------
    $webhookUrl = $discordCommunities->first()->discord_webhook_signal;

    // ----------------------------------------
    // Build Discord message
    // ----------------------------------------
    $message = "📊 Signal Performance Summary "
             . "(" . date('d/m', strtotime($from)) . " - " . date('d/m', strtotime($to)) . ")\n\n";

    foreach ($performances as $index => $perf) {
        $num      = $index + 1;
        $pair     = $perf->signal->trading_pair ?? '-';
        $action   = strtoupper($perf->signal->immediate_action ?? '-');
        $provider = $perf->signal->user->username ?? 'Unknown';
        $pips     = $perf->profit_pips ?? 0;
        $outcome  = $pips > 0 ? '✅' : ($pips < 0 ? '❌' : '⏳');

        $message .= "{$num}. {$pair} {$action} {$outcome} {$pips} pips ({$provider})\n";
    }

    // ----------------------------------------
    // Statistics
    // ----------------------------------------
    $totalTrades     = $performances->count();
    $totalWinTrades  = $performances->where('profit_pips', '>', 0)->count();
    $totalLoseTrades = $performances->where('profit_pips', '<', 0)->count();
    $winRate         = $totalTrades > 0 ? round(($totalWinTrades / $totalTrades) * 100, 2) : 0;
    $totalPips       = $performances->sum('profit_pips');
    $averageProfit   = $totalWinTrades ? round($performances->where('profit_pips', '>', 0)->avg('profit_pips'), 2) : 0;
    $averageLoss     = $totalLoseTrades ? round(abs($performances->where('profit_pips', '<', 0)->avg('profit_pips')), 2) : 0;
    $rr              = ($averageProfit > 0 && $averageLoss > 0) ? '1:' . round($averageProfit / $averageLoss, 2) : 'N/A';

    $message .= "\n📈 Summary\n";
    $message .= "Total Trades: {$totalTrades}\n";
    $message .= "Wins: {$totalWinTrades}\n";
    $message .= "Losses: {$totalLoseTrades}\n";
    $message .= "Win Rate: {$winRate}%\n";
    $message .= "Total Pips: {$totalPips}\n";
    $message .= "RR Ratio: {$rr}\n";
    $message .= "Avg Profit: {$averageProfit}\n";
    $message .= "Avg Loss: {$averageLoss}\n";

    // ----------------------------------------
    // Send to Discord
    // ----------------------------------------
    DiscordService::send($message, $webhookUrl);

    return redirect()->route('signal.performance.index')->with([
        'message' => 'Signal performance sent to Discord successfully!',
        'alert-type' => 'success',
    ]);
}


    public function sendDiscordWeekly(Request $request)
    {
        $performances = $this->getFilteredPerformances($request);

        if ($performances->isEmpty()) {
            return back()->with('error', 'No performance found for selected filter.');
        }

        $message = $this->buildDiscordMessage($performances, $request, true);

        // Send weekly to all communities with webhook
        $communities = Community::where('status', 1)->get();
        foreach ($communities as $community) {
            if ($community->discord_webhook_weeklys_signal) {
                DiscordService::send($message, $community->discord_webhook_weeklys_signal);
            }
        }

        return back()->with('success', 'Weekly performance sent to Discord.');
    }

    public function submitWeeklyPerformances(Request $request)
    {
        $performances = $this->getFilteredPerformances($request);

        if ($performances->isEmpty()) {
            return redirect()->back()->with([
                'message' => 'No performances found for the selected filters.',
                'alert-type' => 'warning',
            ]);
        }

        $updated = 0;

        if ($this->canViewAllPerformances() && !$request->filled('user_id')) {
            $performances
                ->groupBy(fn ($perf) => $perf->signal->user_id ?? ($perf->backupSignal->user_id ?? null))
                ->filter(fn ($group, $providerId) => !empty($providerId))
                ->each(function ($providerPerformances, $providerId) use (&$updated) {
                    $summary = $this->evaluateSignalPerformance($providerPerformances, (int) $providerId);
                    User::whereKey($providerId)->update(['total_score' => (int) $summary['totalScore']]);
                    $updated++;
                });
        } else {
            $providerId = $this->canViewAllPerformances() ? $request->user_id : auth()->id();
            $summary = $this->evaluateSignalPerformance($performances, $providerId ? (int) $providerId : null);

            if ($providerId) {
                User::whereKey($providerId)->update(['total_score' => (int) $summary['totalScore']]);
                $updated = 1;
            }
        }

        return redirect()->route('signal.performance.index', $request->only([
            'from_date',
            'to_date',
            'community_id',
            'community_tag',
            'user_id',
        ]))->with([
            'message' => "Weekly performance submitted. {$updated} provider score record(s) updated.",
            'alert-type' => 'success',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER LOGIC
    |--------------------------------------------------------------------------
    */
private function getFilteredPerformances(Request $request)
{
    $authUser = auth()->user();
    $canViewAll = $this->canViewAllPerformances();

    return SignalPerformance::with([
        'signal.user',
        'signal.community',
        'backupSignal.user',
        'community',
        'signal.discordCommunity.community'  // ✅ updated relationship
    ])

    // Filter by from_date if provided
    ->when($request->from_date, function ($q) use ($request) {
        $q->whereDate('created_at', '>=', $request->from_date);
    })

    // Filter by to_date if provided
    ->when($request->to_date, function ($q) use ($request) {
        $q->whereDate('created_at', '<=', $request->to_date);
    })

    ->when($request->community_id, function ($q) use ($request) {
        $q->where(function ($query) use ($request) {
            $query->where('community_id', $request->community_id)
                ->orWhereHas('signal', function ($signalQuery) use ($request) {
                    $signalQuery->where('community_id', $request->community_id);
                })
                ->orWhereHas('backupSignal', function ($signalQuery) use ($request) {
                    $signalQuery->where('community_id', $request->community_id);
                })
                ->orWhereHas('signal.discordCommunity', function ($discordQuery) use ($request) {
                    $discordQuery->where('community_id', $request->community_id);
                });
        });
    })

    // Filter by community_tag if provided
    ->when($request->community_tag, function ($q) use ($request) {
        $tag = strtolower($request->community_tag);

        $q->whereHas('signal.discordCommunity.community', function ($query) use ($tag) {
            $query->whereRaw('LOWER(community_tag) = ?', [$tag]);
        });
    })

    // Admins can filter by provider; providers are limited to their own signals.
    ->when($canViewAll && $request->user_id, function ($q) use ($request) {
        $q->where(function ($providerQuery) use ($request) {
            $providerQuery->whereHas('signal', function ($q2) use ($request) {
                $q2->where('user_id', $request->user_id);
            })->orWhereHas('backupSignal', function ($q2) use ($request) {
                $q2->where('user_id', $request->user_id);
            });
        });
    })

    ->when(!$canViewAll && $authUser, function ($q) use ($authUser) {
        $q->where(function ($providerQuery) use ($authUser) {
            $providerQuery->whereHas('signal', function ($q2) use ($authUser) {
                $q2->where('user_id', $authUser->id);
            })->orWhereHas('backupSignal', function ($q2) use ($authUser) {
                $q2->where('user_id', $authUser->id);
            });
        });
    })

    ->latest()

    ->get();
}
/*
    |--------------------------------------------------------------------------
    | PERFORMANCE EVALUATION
    |--------------------------------------------------------------------------
    */
private function evaluateSignalPerformance(Collection $performances, ?int $providerId = null): array
{
    // -----------------------------
    // Filter by provider if needed
    // -----------------------------
    if ($providerId) {
        $performances = $performances->filter(function($perf) use ($providerId) {
            $signalUserId = $perf->signal->user_id ?? ($perf->backupSignal->user_id ?? null);
            return $signalUserId == $providerId;
        });
    }

    // -----------------------------
    // Basic statistics
    // -----------------------------
    $totalTrades     = $performances->count();
    $totalWinTrades  = $performances->where('profit_pips', '>', 0)->count();
    $totalLoseTrades = $performances->where('profit_pips', '<', 0)->count();
    $totalPips       = $performances->sum('profit_pips');

    $averageProfit = $totalWinTrades ? $performances->where('profit_pips', '>', 0)->avg('profit_pips') : 0;
    $averageLoss   = $totalLoseTrades ? abs($performances->where('profit_pips', '<', 0)->avg('profit_pips')) : 0;

    $totalProfitPips = $performances->where('profit_pips', '>', 0)->sum('profit_pips');
    $totalLossPips   = abs($performances->where('profit_pips', '<', 0)->sum('profit_pips'));

    $profitFactor = $totalLossPips > 0 ? $totalProfitPips / $totalLossPips : 0;
    $winRate      = $totalTrades ? ($totalWinTrades / $totalTrades) * 100 : 0;
    $rrRatio      = ($averageProfit > 0 && $averageLoss > 0) ? $averageProfit / $averageLoss : 0;
    $expectancy   = $averageLoss > 0 ? ($averageProfit / $averageLoss) * $winRate : 0;

    // -----------------------------
    // Points & Grades
    // -----------------------------
    // Win Rate Points & Grade
    [$winRatePoints, $winRateGrade] = match(true) {
        $winRate >= 75 => [30, 'A+'],
        $winRate >= 73 => [29, 'A'],
        $winRate >= 71 => [28, 'A'],
        $winRate >= 69 => [27, 'A-'],
        $winRate >= 67 => [26, 'B+'],
        $winRate >= 65 => [25, 'B+'],
        $winRate >= 63 => [24, 'B'],
        $winRate >= 61 => [23, 'B'],
        $winRate >= 59 => [22, 'B'],
        $winRate >= 57 => [21, 'B-'],
        $winRate >= 55 => [20, 'C+'],
        $winRate >= 53 => [19, 'C+'],
        $winRate >= 52 => [18, 'C'],
        $winRate >= 51 => [17, 'C'],
        $winRate >= 50 => [15, 'C-'], // Passing score
        $winRate >= 47 => [14, 'D+'],
        $winRate >= 45 => [13, 'D+'],
        $winRate >= 43 => [12, 'D'],
        $winRate >= 41 => [11, 'D'],
        $winRate >= 39 => [10, 'D'],
        $winRate >= 37 => [9,  'E+'],
        $winRate >= 35 => [8,  'E'],
        $winRate >= 33 => [7,  'E'],
        $winRate >= 31 => [6,  'E'],
        $winRate >= 29 => [5,  'E-'],
        $winRate >= 25 => [4,  'F'],
        $winRate >= 20 => [3,  'F'],
        $winRate >= 15 => [2,  'F'],
        $winRate >= 10 => [1,  'F'],
        $winRate > 0   => [0,  'F'],
        default => [0, 'F'],
    };

    // Risk-Reward Points & Grade
    if ($totalPips > 0 && $averageLoss == 0) {
        [$rrrPoints, $rrrGrade] = [30, 'A+'];
    } else {
        [$rrrPoints, $rrrGrade] = match(true) {
            $rrRatio >= 6.0 => [30, 'A+'],
            $rrRatio >= 5.7 => [29, 'A'],
            $rrRatio >= 5.4 => [28, 'A'],
            $rrRatio >= 5.1 => [27, 'A-'],
            $rrRatio >= 4.8 => [26, 'B+'],
            $rrRatio >= 4.5 => [25, 'B+'],
            $rrRatio >= 4.2 => [24, 'B'],
            $rrRatio >= 3.9 => [23, 'B'],
            $rrRatio >= 3.6 => [22, 'B'],
            $rrRatio >= 3.3 => [21, 'B-'],
            $rrRatio >= 3.0 => [20, 'C+'],
            $rrRatio >= 2.7 => [19, 'C+'],
            $rrRatio >= 2.4 => [18, 'C'],
            $rrRatio >= 2.1 => [17, 'C'],
            $rrRatio >= 1.8 => [16, 'C'],
            $rrRatio >= 1.2 => [15, 'C-'], // Passing mark
            $rrRatio >= 1.1 => [14, 'D+'],
            $rrRatio >= 1.0 => [13, 'D+'],
            $rrRatio >= 0.9 => [12, 'D'],
            $rrRatio >= 0.8 => [11, 'D'],
            $rrRatio >= 0.7 => [10, 'D'],
            $rrRatio >= 0.6 => [9,  'E+'],
            $rrRatio >= 0.5 => [8,  'E'],
            $rrRatio >= 0.4 => [7,  'E'],
            $rrRatio >= 0.3 => [6,  'E'],
            $rrRatio >= 0.2 => [5,  'E-'],
            $rrRatio >  0   => [3,  'F'],
            default => [0, 'F'],
        };
    }

    // Profit Factor Points & Grade
    [$profitFactorPoints, $profitFactorGrade] = match(true) {
        $profitFactor >= 10.0 => [20, 'A+'],
        $profitFactor >= 9.0  => [19, 'A'],
        $profitFactor >= 8.0  => [18, 'A'],
        $profitFactor >= 7.0  => [17, 'A-'],
        $profitFactor >= 6.0  => [16, 'B+'],
        $profitFactor >= 5.0  => [15, 'B+'],
        $profitFactor >= 4.5  => [14, 'B'],
        $profitFactor >= 4.0  => [13, 'B'],
        $profitFactor >= 3.5  => [12, 'B-'],
        $profitFactor >= 3.0  => [11, 'C+'],
        $profitFactor >= 2.7  => [10, 'C'],
        $profitFactor >= 2.4  => [9,  'C'],
        $profitFactor >= 2.1  => [8,  'C-'],
        $profitFactor >= 2.0  => [7,  'D+'],
        $profitFactor >= 1.9  => [6,  'D'],
        $profitFactor >= 1.8  => [5,  'D-'],
        $profitFactor >= 1.5  => [4,  'E+'],
        $profitFactor >= 1.3  => [3,  'E'],
        $profitFactor >= 1.1  => [2,  'D'],
        $profitFactor >  0    => [0, 'F'],
        default => [0, 'F'],
    };

    // Trade Selection Points & Grade
    $goodTrades = $performances->filter(function($trade) {
        $lossPips = $trade->profit_pips < 0 ? abs($trade->profit_pips) : 1;
        return $trade->profit_pips > 0 && ($trade->profit_pips / $lossPips) >= 1;
    })->count();

    $tradeSelectionPercent = $totalTrades ? ($goodTrades / $totalTrades) * 100 : 0;

    $tradeSelectionPoints = match(true) {
        $tradeSelectionPercent >= 90 => 20,
        $tradeSelectionPercent >= 86 => 19,
        $tradeSelectionPercent >= 82 => 18,
        $tradeSelectionPercent >= 78 => 17,
        $tradeSelectionPercent >= 74 => 16,
        $tradeSelectionPercent >= 70 => 15,
        $tradeSelectionPercent >= 66 => 14,
        $tradeSelectionPercent >= 62 => 13,
        $tradeSelectionPercent >= 58 => 12,
        $tradeSelectionPercent >= 54 => 11,
        $tradeSelectionPercent >= 50 => 10,
        $tradeSelectionPercent >= 46 => 9,
        $tradeSelectionPercent >= 42 => 8,
        $tradeSelectionPercent >= 38 => 7,
        $tradeSelectionPercent >= 34 => 6,
        $tradeSelectionPercent >= 30 => 5,
        $tradeSelectionPercent >= 25 => 4,
        $tradeSelectionPercent >= 20 => 3,
        $tradeSelectionPercent >= 15 => 2,
        $tradeSelectionPercent >= 10 => 1,
        default => 0,
    };

    $tradeSelectionGrade = match(true) {
        $tradeSelectionPoints >= 20 => 'A+',
        $tradeSelectionPoints >= 18 => 'A',
        $tradeSelectionPoints >= 15 => 'B',
        $tradeSelectionPoints >= 13 => 'C+',
        $tradeSelectionPoints >= 10 => 'C-',
        $tradeSelectionPoints >= 8  => 'D',
        $tradeSelectionPoints >= 5  => 'E',
        default => 'F',
    };

    // Expectancy Points & Grade
    [$expectancyPoints, $expectancyGrade] = match(true) {
        $expectancy >= 200 => [20, 'A+'],
        $expectancy >= 190 => [19, 'A'],
        $expectancy >= 180 => [18, 'A'],
        $expectancy >= 170 => [17, 'A-'],
        $expectancy >= 160 => [16, 'B+'],
        $expectancy >= 150 => [15, 'B+'],
        $expectancy >= 140 => [14, 'B'],
        $expectancy >= 130 => [13, 'B'],
        $expectancy >= 120 => [12, 'B'],
        $expectancy >= 110 => [11, 'B-'],
        $expectancy >= 100 => [10, 'C+'],
        $expectancy >= 90  => [9,  'C'],
        $expectancy >= 80  => [8,  'C'],
        $expectancy >= 70  => [7,  'C'],
        $expectancy >= 60  => [6,  'C-'],
        $expectancy >= 50  => [5,  'D+'],
        $expectancy >= 40  => [4,  'D'],
        $expectancy >= 30  => [3,  'D'],
        $expectancy >= 20  => [2,  'E'],
        $expectancy >= 10  => [1,  'E'],
        $expectancy >= 0   => [0, 'N/A'],
        default => [0, 'N/A'],
    };

    $totalScore = $winRatePoints + $rrrPoints + $profitFactorPoints + $expectancyPoints;

    // -----------------------------
    // Scenario rules
    // -----------------------------
    $forceIntern = false;

    $minWinRatePoints      = 15;
    $minProfitFactorPoints = 8;
    $minExpectancyPoints   = 10;

    // Scenario 3 – NEVER void
    if ($expectancyPoints < $minExpectancyPoints) {
        $forceIntern = true;
    }
    // Scenario 1 – voidable by PF B+
    elseif ($winRatePoints < $minWinRatePoints) {
        if ($profitFactorPoints < 15) {
            $forceIntern = true;
        }
    }
    // Scenario 2 – voidable by RRR B
    elseif ($profitFactorPoints < $minProfitFactorPoints) {
        if ($rrrPoints < 22) {
            $forceIntern = true;
        }
    }

    // -----------------------------
    // Scenario upgrade: strong score → Junior
    // -----------------------------
    $allowJuniorInsteadOfIntern = false;
    if ($forceIntern && $totalScore >= 73) {
        $allowJuniorInsteadOfIntern = true;
        $forceIntern = false;
    }

    // -----------------------------
    // Final rating
    // -----------------------------
    $result = $this->normalRating(
        $totalScore,
        $forceIntern,
        $allowJuniorInsteadOfIntern
    );

    $finalScore  = $result['score'];
    $finalRating = $result['rating'];

    // ----------------------------------
    // 🔥 CRITERIA-BASED PERFORMANCE MEANING
    // ----------------------------------
    $performanceMeaning =
        "{$finalRating}: Overall evaluation based on this week’s signals. " .
        ucfirst($this->describeGrade($winRateGrade, 'win rate')) . " ({$winRateGrade}), " .
        ucfirst($this->describeGrade($rrrGrade, 'risk-reward ratio')) . " ({$rrrGrade}), " .
        ucfirst($this->describeGrade($profitFactorGrade, 'profit factor')) . " ({$profitFactorGrade}), " .
        ucfirst($this->describeGrade($expectancyGrade, 'expectancy')) . " ({$expectancyGrade}).";

    return [
    'totalScore' => $totalScore,           // total numeric score
    'score' => $totalScore,
        'grade'                 => $finalRating,
            'providerLevel' => $finalRating, // <-- add this

        'totalTrades'           => $totalTrades,
        'totalWinTrades'        => $totalWinTrades,
        'totalLoseTrades'       => $totalLoseTrades,
        'totalPips'             => $totalPips,
            'totalProfitPips' => $totalProfitPips, // ✅ add this
    'totalLossPips' => $totalLossPips,     // ✅ add this
        'profitFactor' => $profitFactor,          // ✅ add this
    'expectancy' => $expectancy,            // ✅ add this
    'pfPoints' => $profitFactorPoints,           // ✅ front-end expects this

        'winRate'               => round($winRate, 2),
        'rrRatio'               => round($rrRatio, 2),
        'averageProfit'         => round($averageProfit, 2),
        'averageLoss'           => round($averageLoss, 2),
        'winRatePoints'         => $winRatePoints,
        'rrrPoints'             => $rrrPoints,
        'tradeSelectionPoints'  => $tradeSelectionPoints,
        'tradeSelectionGrade'   => $tradeSelectionGrade,
        'profitFactorGrade'     => $profitFactorGrade,
        'profitFactorPoints'    => $profitFactorPoints,
        'expectancyPoints'      => $expectancyPoints,
        'winRateGrade'          => $winRateGrade,
        'rrrGrade'              => $rrrGrade,
        'expectancyGrade'       => $expectancyGrade,
        'scoreWinRate'          => $winRatePoints,
        'scoreRR'               => $rrrPoints,
        'scoreSelection'        => $tradeSelectionPoints,
        'scoreExpectancy'       => $expectancyPoints,
        'performanceMeaning'    => $performanceMeaning,
    ];
}

private function normalRating(int $totalScore, bool $forceIntern = false, bool $forceJunior = false): array
{
    // Rule 1: Absolute fail
    if ($totalScore < 50) {
        return [
            'score'  => $totalScore,
            'rating' => 'Not Qualified',
        ];
    }

    // Rule 2: Forced Junior
    if ($forceJunior) {
        return [
            'score'  => max($totalScore, 60),
            'rating' => 'Junior Signal Provider',
        ];
    }

    // Rule 3: Forced Intern
    if ($forceIntern) {
        return [
            'score'  => 50,
            'rating' => 'Intern Signal Provider',
        ];
    }

    // Rule 4: Normal grading
    $rating = match (true) {
        $totalScore >= 85 => 'Expert Signal Provider',
        $totalScore >= 75 => 'Senior Signal Provider',
        $totalScore >= 60 => 'Junior Signal Provider',
        default           => 'Intern Signal Provider',
    };

    return [
        'score'  => $totalScore,
        'rating' => $rating,
    ];
}
private function describeGrade(string $grade, string $criteria): string
{
    return match ($grade) {
        'A+' => "{$criteria} is excellent",
        'A'  => "{$criteria} is very good",
        'A-' => "{$criteria} is good",
        'B+' => "{$criteria} is above average",
        'B'  => "{$criteria} is average",
        'B-' => "{$criteria} is slightly below average",
        'C+' => "{$criteria} is acceptable",
        'C'  => "{$criteria} is fair",
        'C-' => "{$criteria} is borderline",
        'D+' => "{$criteria} is poor",
        'D'  => "{$criteria} is very poor",
        'D-' => "{$criteria} is bad",
        'E+' => "{$criteria} is very bad",
        'E'  => "{$criteria} is extremely bad",
        'E-' => "{$criteria} is failing",
        'F'  => "{$criteria} is failed",
        default => "{$criteria} is unknown",
    };
}
    public function exportExcel(Request $request)
{
    $performances = $this->getFilteredPerformances($request)
                         ->load(['signal.user', 'signal.community']); // <— eager load

    if ($performances->isEmpty()) {
        return redirect()->back()->with([
            'message' => 'No performances found for the selected filters.',
            'alert-type' => 'warning',
        ]);
    }

    $summary = $this->evaluateSignalPerformance($performances);
    $filename = $this->reportFilename($request, 'xlsx');
    $providerId = $this->canViewAllPerformances() ? ($request->user_id ?? null) : auth()->id();

    return Excel::download(
        new SignalPerformanceExport($performances, $summary, $request->all(), $providerId),
        $filename
    );
}

public function exportPdf(Request $request)
{
    $performances = $this->getFilteredPerformances($request);

    if ($performances->isEmpty()) {
        return redirect()->back()->with([
            'message' => 'No performances found for the selected filters.',
            'alert-type' => 'warning',
        ]);
    }

    $summary = $this->evaluateSignalPerformance($performances);
    $providers = $this->signalProviders();
    $selectedProvider = $this->canViewAllPerformances() && $request->filled('user_id')
        ? $providers->firstWhere('id', (int) $request->user_id)
        : auth()->user();

    $pdf = Pdf::loadView('admin.signal_performance.performance_report_pdf', [
        'performances' => $performances,
        'summary' => $summary,
        'scoreBreakdown' => $this->scoreBreakdown($summary),
        'selectedProvider' => $selectedProvider,
        'generatedAt' => now(),
        'filters' => [
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'community_tag' => $request->community_tag,
            'community' => optional(Community::find($request->community_id))->name,
        ],
    ])->setPaper('a4', 'portrait');

    return $pdf->download($this->reportFilename($request, 'pdf'));
}

public function downloadTemplate()
{
    return Excel::download(
        new SignalPerformancesTemplateExport(),
        'Signal_Performance_Import_Template.xlsx'
    );
}

public function importExcel(Request $request)
{
    $request->validate([
        'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        'user_id' => ['nullable', 'integer'],
    ]);

    $providerId = $this->canViewAllPerformances()
        ? $request->user_id
        : auth()->id();

    Excel::import(new SignalPerformanceImport($providerId), $request->file('file'));

    return redirect()->route('signal.performance.index')->with([
        'message' => 'Signal performances imported successfully.',
        'alert-type' => 'success',
    ]);
}

private function reportFilename(Request $request, string $extension): string
{
    $from = $request->from_date ?: 'start';
    $to = $request->to_date ?: now()->format('Y-m-d');

    return "Signal_Performance_Report_{$from}_to_{$to}.{$extension}";
}
}


