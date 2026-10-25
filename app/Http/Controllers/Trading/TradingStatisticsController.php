<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\TradingPositionApplication;
use App\Models\User;
use App\Services\TraderLevelProfileService;
use App\Services\TradingJournalAnalytics;
use App\Services\TradingJournalTimeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class TradingStatisticsController extends Controller
{
    private const ACCOUNT_STAGE_PHASE_1 = 'phase1';

    private const ACCOUNT_STAGE_PHASE_2 = 'phase2';

    private const ACCOUNT_STAGE_OVERALL = 'overall';

    private const ACCOUNT_STAGE_FUNDED = 'funded';

    private const MIN_RELIABLE_SCORE_TRADES = 10;

    public function index(Request $request)
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            return redirect()->route('login');
        }

        abort_unless($this->canViewTradingStatistics($currentUser), 403);

        $canViewAll = $this->canSelectTraderStatistics($currentUser);
        $canViewGlobal = $this->canViewGlobalTraderStatistics($currentUser);
        $visibleTraderIds = $this->visibleTraderIdsForStatistics($currentUser);
        $selectedTimeView = app(TradingJournalTimeService::class)->normalizeMode($request->input('time_view'));
        $selectedTimeViewOffset = app(TradingJournalTimeService::class)->normalizeOffset($request->input('mt5_offset_minutes'), $selectedTimeView);

        $source = $request->input('source', 'all');
        if (! in_array($source, ['all', 'current', 'backup'], true)) {
            $source = 'all';
        }

        $month = $this->normalizeDateFilter($request->input('month'), 1, 12);
        $year = $this->normalizeDateFilter($request->input('year'), 2000, (int) now()->year);
        $selectedTraderId = $this->selectedTraderIdForStatistics($request, $currentUser, $visibleTraderIds, $canViewAll, $canViewGlobal);
        $selectedAccountStage = $this->normalizeAccountStageFilter($request->input('account_stage'));

        $currentTrades = collect();
        $backupTrades = collect();

        if (in_array($source, ['all', 'current'], true)) {
            $currentTrades = $this->loadJournalTrades(
                TradingJournal::query(),
                'trading_journals',
                'Current Journal',
                $selectedTraderId,
                $month,
                $year,
                $selectedAccountStage
            );
        }

        if (in_array($source, ['all', 'backup'], true) && Schema::hasTable('trading_journals_backup')) {
            $backupTrades = $this->loadJournalTrades(
                TradingJournalBackup::query(),
                'trading_journals_backup',
                'Backup Journal',
                $selectedTraderId,
                $month,
                $year,
                $selectedAccountStage
            );
        }

        $trades = collect($currentTrades->all())
            ->merge($backupTrades->all())
            ->sortBy(fn ($trade) => $trade['closed_at'] ?? $trade['opened_at'] ?? $trade['created_at'])
            ->values();

        $analytics = new TradingJournalAnalytics();
        $capitalSummary = $this->capitalSummary($selectedTraderId, $month, $year, $selectedAccountStage);
        $summary = $this->buildSummary($trades, $capitalSummary, $analytics);
        $dailyStats = $this->buildDailyStats($trades);
        $monthlyStats = $this->buildMonthlyStats($trades);
        $pairStats = $this->buildPairStats($trades);
        $sourceStats = $this->buildSourceStats($trades);
        $directionStats = $this->buildDirectionStats($trades);
        $ruleMonitor = $this->buildRuleMonitor($summary);
        $weekdayStats = $this->buildWeekdayStats($trades);
        $sessionStats = $this->buildSessionStats($trades, $selectedTimeView, $selectedTimeViewOffset);
        $behavioralProfile = $this->buildBehavioralProfile($summary, $directionStats);
        $hedgingProfile = $analytics->hedgingProfile($trades);
        $positionProfile = $analytics->positionConsistency($trades);
        $durationProfile = $analytics->durationStats($trades);
        $traderStyleProfile = $analytics->behavioralRiskProfile($trades, (float) $capitalSummary['total_deposits']);
        $behaviorWeeklyProfile = $analytics->behaviorWeeklyComparison($trades, (float) $capitalSummary['total_deposits']);
        $scoreEvaluationProfile = $this->buildScoreEvaluationProfile($summary, $traderStyleProfile, $positionProfile);
        $levelProfile = $this->buildLevelProfile($summary, $scoreEvaluationProfile, $traderStyleProfile, $positionProfile);
        $performanceProfile = $this->buildPerformanceProfile($summary, $ruleMonitor);
        $traderResumeProfile = $this->buildTraderResumeProfile(
            $summary,
            $scoreEvaluationProfile,
            $performanceProfile,
            $ruleMonitor,
            $behavioralProfile,
            $levelProfile,
            $hedgingProfile,
            $positionProfile,
            $durationProfile,
            $traderStyleProfile,
            $pairStats,
            $weekdayStats,
            $sessionStats
        );
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
            ? User::whereIn('id', $visibleTraderIds)->orderBy('name')->get(['id', 'name', 'username'])
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
            'scoreEvaluationProfile' => $scoreEvaluationProfile,
            'performanceProfile' => $performanceProfile,
            'weekdayStats' => $weekdayStats,
            'sessionStats' => $sessionStats,
            'behavioralProfile' => $behavioralProfile,
            'levelProfile' => $levelProfile,
            'hedgingProfile' => $hedgingProfile,
            'positionProfile' => $positionProfile,
            'durationProfile' => $durationProfile,
            'traderStyleProfile' => $traderStyleProfile,
            'behaviorWeeklyProfile' => $behaviorWeeklyProfile,
            'traderResumeProfile' => $traderResumeProfile,
            'chartData' => $chartData,
            'traders' => $traders,
            'currentUser' => $currentUser,
            'canViewAll' => $canViewAll,
            'canViewGlobal' => $canViewGlobal,
            'canViewScoreExplanation' => $this->canViewScoreExplanation($currentUser),
            'selectedTraderId' => $selectedTraderId,
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'selectedSource' => $source,
            'selectedAccountStage' => $selectedAccountStage,
            'accountStageOptions' => $this->accountStageOptions(),
            'selectedAccountStageLabel' => $this->accountStageLabel($selectedAccountStage),
            'selectedAccountStageDescription' => $this->accountStageDescription($selectedAccountStage),
            'selectedTimeView' => $selectedTimeView,
            'selectedTimeViewOffset' => $selectedTimeViewOffset,
            'availableYears' => $availableYears,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $currentUser = Auth::user();
        if (! $currentUser) {
            return redirect()->route('login');
        }

        abort_unless($this->canViewTradingStatistics($currentUser), 403);

        $payload = $this->buildReportPayload($request, $currentUser);

        if ($payload['trades']->isEmpty()) {
            return redirect()->back()->with([
                'message' => 'No trading journal records found for the selected report filters.',
                'alert-type' => 'warning',
            ]);
        }

        $pdf = Pdf::loadView('admin.trading_statistics.report_pdf', $payload)
            ->setPaper('a4', 'landscape');

        return $pdf->download($this->reportFilename($payload));
    }

    private function buildReportPayload(Request $request, User $currentUser): array
    {
        $canViewAll = $this->canSelectTraderStatistics($currentUser);
        $canViewGlobal = $this->canViewGlobalTraderStatistics($currentUser);
        $visibleTraderIds = $this->visibleTraderIdsForStatistics($currentUser);
        $selectedTimeView = app(TradingJournalTimeService::class)->normalizeMode($request->input('time_view'));
        $selectedTimeViewOffset = app(TradingJournalTimeService::class)->normalizeOffset($request->input('mt5_offset_minutes'), $selectedTimeView);

        $source = $request->input('source', 'all');
        if (! in_array($source, ['all', 'current', 'backup'], true)) {
            $source = 'all';
        }

        $month = $this->normalizeDateFilter($request->input('month'), 1, 12);
        $year = $this->normalizeDateFilter($request->input('year'), 2000, (int) now()->year);
        $selectedTraderId = $this->selectedTraderIdForStatistics($request, $currentUser, $visibleTraderIds, $canViewAll, $canViewGlobal);
        $selectedAccountStage = $this->normalizeAccountStageFilter($request->input('account_stage'));

        $currentTrades = collect();
        $backupTrades = collect();

        if (in_array($source, ['all', 'current'], true)) {
            $currentTrades = $this->loadJournalTrades(
                TradingJournal::query(),
                'trading_journals',
                'Current Journal',
                $selectedTraderId,
                $month,
                $year,
                $selectedAccountStage
            );
        }

        if (in_array($source, ['all', 'backup'], true) && Schema::hasTable('trading_journals_backup')) {
            $backupTrades = $this->loadJournalTrades(
                TradingJournalBackup::query(),
                'trading_journals_backup',
                'Backup Journal',
                $selectedTraderId,
                $month,
                $year,
                $selectedAccountStage
            );
        }

        $trades = collect($currentTrades->all())
            ->merge($backupTrades->all())
            ->sortBy(fn ($trade) => $trade['closed_at'] ?? $trade['opened_at'] ?? $trade['created_at'])
            ->values();

        $analytics = new TradingJournalAnalytics();
        $capitalSummary = $this->capitalSummary($selectedTraderId, $month, $year, $selectedAccountStage);
        $summary = $this->buildSummary($trades, $capitalSummary, $analytics);
        $dailyStats = $this->buildDailyStats($trades);
        $monthlyStats = $this->buildMonthlyStats($trades);
        $pairStats = $this->buildPairStats($trades);
        $sourceStats = $this->buildSourceStats($trades);
        $directionStats = $this->buildDirectionStats($trades);
        $ruleMonitor = $this->buildRuleMonitor($summary);
        $weekdayStats = $this->buildWeekdayStats($trades);
        $sessionStats = $this->buildSessionStats($trades, $selectedTimeView, $selectedTimeViewOffset);
        $behavioralProfile = $this->buildBehavioralProfile($summary, $directionStats);
        $hedgingProfile = $analytics->hedgingProfile($trades);
        $positionProfile = $analytics->positionConsistency($trades);
        $durationProfile = $analytics->durationStats($trades);
        $traderStyleProfile = $analytics->behavioralRiskProfile($trades, (float) $capitalSummary['total_deposits']);
        $behaviorWeeklyProfile = $analytics->behaviorWeeklyComparison($trades, (float) $capitalSummary['total_deposits']);
        $scoreEvaluationProfile = $this->buildScoreEvaluationProfile($summary, $traderStyleProfile, $positionProfile);
        $levelProfile = $this->buildLevelProfile($summary, $scoreEvaluationProfile, $traderStyleProfile, $positionProfile);
        $performanceProfile = $this->buildPerformanceProfile($summary, $ruleMonitor);
        $traderResumeProfile = $this->buildTraderResumeProfile(
            $summary,
            $scoreEvaluationProfile,
            $performanceProfile,
            $ruleMonitor,
            $behavioralProfile,
            $levelProfile,
            $hedgingProfile,
            $positionProfile,
            $durationProfile,
            $traderStyleProfile,
            $pairStats,
            $weekdayStats,
            $sessionStats
        );
        $recentTrades = $trades
            ->sortByDesc(fn ($trade) => $trade['closed_at'] ?? $trade['opened_at'] ?? $trade['created_at'])
            ->take(25)
            ->values();

        $selectedTrader = $selectedTraderId
            ? User::find($selectedTraderId)
            : null;

        return [
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
            'scoreEvaluationProfile' => $scoreEvaluationProfile,
            'performanceProfile' => $performanceProfile,
            'weekdayStats' => $weekdayStats,
            'sessionStats' => $sessionStats,
            'behavioralProfile' => $behavioralProfile,
            'levelProfile' => $levelProfile,
            'hedgingProfile' => $hedgingProfile,
            'positionProfile' => $positionProfile,
            'durationProfile' => $durationProfile,
            'traderStyleProfile' => $traderStyleProfile,
            'behaviorWeeklyProfile' => $behaviorWeeklyProfile,
            'traderResumeProfile' => $traderResumeProfile,
            'currentUser' => $currentUser,
            'selectedTrader' => $selectedTrader,
            'selectedTraderId' => $selectedTraderId,
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'selectedSource' => $source,
            'selectedAccountStage' => $selectedAccountStage,
            'accountStageOptions' => $this->accountStageOptions(),
            'selectedAccountStageLabel' => $this->accountStageLabel($selectedAccountStage),
            'selectedAccountStageDescription' => $this->accountStageDescription($selectedAccountStage),
            'selectedTimeView' => $selectedTimeView,
            'selectedTimeViewOffset' => $selectedTimeViewOffset,
            'generatedAt' => now(),
            'canViewAll' => $canViewAll,
            'canViewGlobal' => $canViewGlobal,
        ];
    }

    private function reportFilename(array $payload): string
    {
        $trader = $payload['selectedTrader'] ?? null;
        $name = $trader
            ? ($trader->username ?: ($trader->name ?: 'trader'))
            : 'all-traders';

        $period = ($payload['selectedYear'] ?? null)
            ? (string) $payload['selectedYear']
            : 'all-years';

        if (! empty($payload['selectedMonth'])) {
            $period .= '-'.str_pad((string) $payload['selectedMonth'], 2, '0', STR_PAD_LEFT);
        }

        $stage = $this->normalizeAccountStageFilter($payload['selectedAccountStage'] ?? self::ACCOUNT_STAGE_OVERALL);
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower((string) $name));
        $safeStage = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower((string) $stage));

        return 'trading-journal-report-'.trim($safeName, '-').'-'.trim($safeStage, '-').'-'.$period.'.pdf';
    }

    private function canViewGlobalTraderStatistics(?User $user): bool
    {
        return $user && in_array((int) $user->role_id, [1, 2], true);
    }

    private function canSelectTraderStatistics(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->canViewGlobalTraderStatistics($user) || $user->isTradingLeader();
    }

    private function visibleTraderIdsForStatistics(User $user): Collection
    {
        if ($this->canViewGlobalTraderStatistics($user)) {
            return $this->traderIdsFromJournals();
        }

        $ids = collect([(int) $user->id]);

        if ($user->isTradingLeader()) {
            $ids = $ids->merge(
                $user->directTradingDownlines()
                    ->whereIn('role_id', TradingPositionApplication::tradingMemberRoles())
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
            );
        }

        return $ids->filter()->unique()->values();
    }

    private function selectedTraderIdForStatistics(Request $request, User $currentUser, Collection $visibleTraderIds, bool $canSelectTrader, bool $canViewGlobal): ?int
    {
        if (! $canSelectTrader) {
            return (int) $currentUser->id;
        }

        $requestedTraderId = $this->normalizeTraderId($request->input('user_id'));

        if ($requestedTraderId) {
            abort_unless($visibleTraderIds->contains($requestedTraderId), 403);

            return $requestedTraderId;
        }

        if ($canViewGlobal) {
            return null;
        }

        return (int) $currentUser->id;
    }

    private function canViewTradingStatistics(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $roleId = (int) $user->role_id;

        return in_array($roleId, [1, 2, 201, 202, 501, 502], true)
            || in_array($roleId, TradingPositionApplication::tradingMemberRoles(), true);
    }

    private function canViewScoreExplanation(?User $user): bool
    {
        return $this->canViewTradingStatistics($user);
    }

    private function loadJournalTrades(Builder $query, string $table, string $sourceLabel, ?int $userId, ?int $month, ?int $year, string $accountStage): Collection
    {
        $dateColumn = $this->journalDateColumn($table);
        $columns = Schema::getColumnListing($table);

        if (in_array('user_id', $columns, true) && $userId) {
            $query->where('user_id', $userId);
        }

        $query = $this->tradeOnly($query, $table);
        $query = $this->applyAccountStageFilter($query, $table, $accountStage);

        if ($month && $dateColumn) {
            $query->whereMonth($dateColumn, $month);
        }

        if ($year && $dateColumn) {
            $query->whereYear($dateColumn, $year);
        }

        if ($dateColumn) {
            $query->orderBy($dateColumn);
        }

        $mappedTrades = $query->with('user')->get()->map(function ($trade) use ($sourceLabel, $table): array {
            $openedAt = $this->parseDate($trade->open_date ?? $trade->trade_date ?? $trade->created_at);
            $closedAt = $this->parseDate($trade->close_date ?? $trade->trade_date ?? $trade->created_at);
            $stage = $this->accountStageForTrade($trade, $table);

            return [
                'id' => $trade->id,
                'source' => $sourceLabel,
                'account_stage' => $stage['key'],
                'account_stage_label' => $stage['label'],
                'prop_firm_phase' => $stage['phase'],
                'user_id' => $trade->user_id ?? null,
                'trader_name' => optional($trade->user)->name ?? optional($trade->user)->username ?? 'Unknown Trader',
                'opened_at' => $openedAt,
                'closed_at' => $closedAt,
                'time_input_timezone' => $trade->time_input_timezone ?? TradingJournalTimeService::TIMEZONE_MALAYSIA,
                'time_input_offset_minutes' => $trade->time_input_offset_minutes ?? null,
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

        return collect($mappedTrades->all())->values();
    }

    private function applyAccountStageFilter(Builder $query, string $table, string $accountStage): Builder
    {
        $accountStage = $this->normalizeAccountStageFilter($accountStage);

        if ($table === 'trading_journals_backup' && Schema::hasColumn($table, 'prop_firm_phase')) {
            return match ($accountStage) {
                self::ACCOUNT_STAGE_PHASE_1 => $query->where('prop_firm_phase', 1),
                self::ACCOUNT_STAGE_PHASE_2 => $query->where('prop_firm_phase', 2),
                self::ACCOUNT_STAGE_FUNDED => $query->where('prop_firm_phase', 3),
                default => $query->where(function (Builder $query): void {
                    $query->whereIn('prop_firm_phase', [1, 2])
                        ->orWhereNull('prop_firm_phase');
                }),
            };
        }

        if (Schema::hasColumn($table, 'user_id')) {
            $query->whereHas('user', function (Builder $userQuery) use ($accountStage): void {
                $this->applyUserAccountStageFilter($userQuery, $accountStage);
            });
        }

        return $query;
    }

    private function applyUserAccountStageFilter(Builder $query, string $accountStage): void
    {
        $hasPhase = Schema::hasColumn('users', 'prop_firm_phase');

        match ($accountStage) {
            self::ACCOUNT_STAGE_PHASE_1 => $hasPhase
                ? $query->where(function (Builder $query): void {
                    $query->whereNull('prop_firm_phase')->orWhere('prop_firm_phase', '<=', 1);
                })
                : $query,
            self::ACCOUNT_STAGE_PHASE_2 => $hasPhase
                ? $query->where('prop_firm_phase', 2)
                : $query->whereRaw('1 = 0'),
            self::ACCOUNT_STAGE_FUNDED => $this->whereUserIsFunded($query),
            default => $hasPhase
                ? $query->where(function (Builder $query): void {
                    $query->whereNull('prop_firm_phase')->orWhereIn('prop_firm_phase', [1, 2]);
                })
                : $this->whereUserIsNotFunded($query),
        };

        if ($accountStage !== self::ACCOUNT_STAGE_FUNDED) {
            $this->whereUserIsNotFunded($query);
        }
    }

    private function whereUserIsFunded(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $hasCondition = false;

            if (Schema::hasColumn('users', 'prop_firm_phase')) {
                $query->where('prop_firm_phase', '>=', 3);
                $hasCondition = true;
            }

            if (Schema::hasColumn('users', 'funded_status')) {
                $hasCondition
                    ? $query->orWhere('funded_status', 1)
                    : $query->where('funded_status', 1);
                $hasCondition = true;
            }

            if (Schema::hasColumn('users', 'prop_firm_review_status')) {
                $hasCondition
                    ? $query->orWhere('prop_firm_review_status', 'funded_approved')
                    : $query->where('prop_firm_review_status', 'funded_approved');
                $hasCondition = true;
            }

            if (! $hasCondition) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    private function whereUserIsNotFunded(Builder $query): Builder
    {
        if (Schema::hasColumn('users', 'prop_firm_phase')) {
            $query->where(function (Builder $query): void {
                $query->whereNull('prop_firm_phase')->orWhere('prop_firm_phase', '<', 3);
            });
        }

        if (Schema::hasColumn('users', 'funded_status')) {
            $query->where(function (Builder $query): void {
                $query->whereNull('funded_status')->orWhere('funded_status', '!=', 1);
            });
        }

        if (Schema::hasColumn('users', 'prop_firm_review_status')) {
            $query->where(function (Builder $query): void {
                $query->whereNull('prop_firm_review_status')->orWhere('prop_firm_review_status', '!=', 'funded_approved');
            });
        }

        return $query;
    }

    private function accountStageForTrade($trade, string $table): array
    {
        $phase = isset($trade->prop_firm_phase) ? (int) $trade->prop_firm_phase : null;

        if ($table === 'trading_journals_backup' && $phase) {
            return $this->accountStageMeta(match ($phase) {
                1 => self::ACCOUNT_STAGE_PHASE_1,
                2 => self::ACCOUNT_STAGE_PHASE_2,
                3 => self::ACCOUNT_STAGE_FUNDED,
                default => self::ACCOUNT_STAGE_OVERALL,
            }, $phase);
        }

        $user = $trade->user;

        if ($user && $this->userIsFundedStage($user)) {
            return $this->accountStageMeta(self::ACCOUNT_STAGE_FUNDED, 3);
        }

        $currentPhase = $user ? (int) ($user->prop_firm_phase ?? 1) : 1;

        return $this->accountStageMeta($currentPhase === 2 ? self::ACCOUNT_STAGE_PHASE_2 : self::ACCOUNT_STAGE_PHASE_1, $currentPhase);
    }

    private function accountStageMeta(string $stage, ?int $phase = null): array
    {
        $stage = $this->normalizeAccountStageFilter($stage);

        return [
            'key' => $stage,
            'label' => $this->accountStageLabel($stage),
            'phase' => $phase,
        ];
    }

    private function userIsFundedStage(User $user): bool
    {
        return (int) ($user->prop_firm_phase ?? 0) >= 3
            || (int) ($user->funded_status ?? 0) === 1
            || (string) ($user->prop_firm_review_status ?? '') === 'funded_approved';
    }

    private function buildSummary(Collection $trades, array $capitalSummary, TradingJournalAnalytics $analytics): array
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
        $bestDayRule = $analytics->bestDayRule($trades);
        unset($bestDayRule['daily_profit_loss']);
        $grossProfitRule = $analytics->grossProfitRule($trades, (float) $capitalSummary['total_deposits']);
        $durationStats = $analytics->durationStats($trades);
        $biggestWinningDay = $bestDayRule['best_winning_day'];
        $consistencyPercent = $bestDayRule['score_percent'];
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
            'best_day_rule' => $bestDayRule,
            'gross_profit_rule' => $grossProfitRule,
            'biggest_winning_day' => round($biggestWinningDay, 2),
            'longest_win_streak' => $longestWinStreak,
            'longest_loss_streak' => $longestLossStreak,
            'recovery_factor' => $recoveryFactor,
            'payoff_ratio' => $payoffRatio,
            'duration_days' => $durationDays,
            'first_trade_date' => $firstTradeDate,
            'last_trade_date' => $lastTradeDate,
            'average_holding_minutes' => $durationStats['average_minutes'],
            'average_holding_label' => $durationStats['average_label'],
            'median_holding_minutes' => $durationStats['median_minutes'],
            'median_holding_label' => $durationStats['median_label'],
            'max_holding_minutes' => $durationStats['max_minutes'],
            'max_holding_label' => $durationStats['max_label'],
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

    private function buildSessionStats(Collection $trades, string $timeView, int $timeViewOffset): Collection
    {
        $sessions = collect([
            ['name' => 'Asia', 'start' => 0, 'end' => 8],
            ['name' => 'London', 'start' => 8, 'end' => 13],
            ['name' => 'New York', 'start' => 13, 'end' => 22],
            ['name' => 'Late Session', 'start' => 22, 'end' => 24],
        ]);

        return $sessions->map(function (array $session) use ($trades, $timeView, $timeViewOffset): array {
            $sessionTrades = $trades->filter(function (array $trade) use ($session, $timeView, $timeViewOffset): bool {
                if (! $trade['closed_at'] instanceof Carbon) {
                    return false;
                }

                $closedAt = app(TradingJournalTimeService::class)->fromMalaysiaCarbon($trade['closed_at'], $timeView, $timeViewOffset);
                $hour = $closedAt ? (int) $closedAt->format('G') : (int) $trade['closed_at']->format('G');

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

        if (! ($summary['best_day_rule']['passed'] ?? true) && $summary['net_profit_loss'] > 0) {
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

    private function buildLevelProfile(array $summary, array $scoreEvaluationProfile = [], array $traderStyleProfile = [], array $positionProfile = []): array
    {
        return app(TraderLevelProfileService::class)->profile(
            $summary,
            $scoreEvaluationProfile,
            $traderStyleProfile,
            $positionProfile
        );
    }

    private function buildTraderResumeProfile(
        array $summary,
        array $scoreEvaluationProfile,
        array $performanceProfile,
        array $ruleMonitor,
        array $behavioralProfile,
        array $levelProfile,
        array $hedgingProfile,
        array $positionProfile,
        array $durationProfile,
        array $traderStyleProfile,
        Collection $pairStats,
        Collection $weekdayStats,
        Collection $sessionStats
    ): array {
        $totalTrades = (int) ($summary['total_trades'] ?? 0);
        $score = (float) ($scoreEvaluationProfile['score'] ?? 0);
        $scoreIsProvisional = (bool) ($scoreEvaluationProfile['score_is_provisional'] ?? false);
        $sampleStatus = (string) ($scoreEvaluationProfile['sample_size_status'] ?? 'Reliable Sample');
        $sampleNote = (string) ($scoreEvaluationProfile['sample_size_note'] ?? '');
        $behaviorRisk = (float) ($traderStyleProfile['risk_score'] ?? 0);
        $positionScore = (float) ($positionProfile['score'] ?? 0);
        $drawdownPercent = (float) ($summary['max_drawdown_percent'] ?? 0);
        $netProfitLoss = (float) ($summary['net_profit_loss'] ?? 0);
        $expectancy = (float) ($summary['expectancy'] ?? 0);
        $winRate = (float) ($summary['win_rate'] ?? 0);
        $profitFactor = $summary['profit_factor'] ?? 'N/A';
        $rulesBreached = collect($ruleMonitor)->where('status', 'Breached')->count();
        $rulesPassed = collect($ruleMonitor)->where('status', 'Passed')->count();
        $topPair = $pairStats->sortByDesc('profit_loss')->first();
        $weakPair = $pairStats->sortBy('profit_loss')->first();
        $bestSession = $sessionStats->sortByDesc('win_rate')->first();
        $bestWeekday = $weekdayStats
            ->filter(fn (array $day): bool => (int) ($day['trades'] ?? 0) > 0)
            ->sortByDesc('profit_loss')
            ->first();

        if ($totalTrades === 0) {
            return [
                'verdict' => 'No Trading Profile Yet',
                'tone' => 'secondary',
                'technical_skill' => 'Unproven',
                'risk_profile' => 'No live journal evidence',
                'administration_action' => 'Wait for a meaningful sample before grading trader quality.',
                'paragraph' => 'There is not enough trading journal data to describe this trader yet. Once closed trades are added, the report will evaluate technical skill, risk control, behavior patterns, consistency, position sizing, timing, and execution quality.',
                'strengths' => collect(['No objective strengths can be confirmed yet.']),
                'risks' => collect(['No sample size available for risk review.']),
                'coaching_focus' => collect(['Record trades consistently with accurate time, lot size, pair, result, and notes.']),
                'badges' => collect(['No Data']),
            ];
        }

        $technicalSkill = match (true) {
            $scoreIsProvisional && $netProfitLoss >= 0 => 'Provisional / Developing',
            $score >= 85 || ($netProfitLoss > 0 && is_numeric($profitFactor) && (float) $profitFactor >= 2.3 && $expectancy > 0) => 'Advanced',
            $score >= 70 || ($netProfitLoss > 0 && $expectancy > 0 && $winRate >= 50) => 'Strong',
            $score >= 50 || $netProfitLoss > 0 => 'Developing',
            default => 'Weak / Unproven',
        };

        $riskProfile = match (true) {
            $rulesBreached > 0 || $drawdownPercent > 20 || $behaviorRisk >= 70 => 'High Risk',
            $drawdownPercent > 10 || $behaviorRisk >= 40 || $positionScore < 55 => 'Needs Monitoring',
            $drawdownPercent <= 5 && $behaviorRisk < 25 && $positionScore >= 70 => 'Controlled',
            default => 'Moderate',
        };

        [$verdict, $tone] = match (true) {
            $scoreIsProvisional && $netProfitLoss >= 0 && $riskProfile !== 'High Risk' => ['Provisional Controlled Trader', 'primary'],
            $score >= 75 && $netProfitLoss > 0 && $riskProfile === 'Controlled' => ['Good Disciplined Trader', 'success'],
            $score >= 70 && $netProfitLoss > 0 && $behaviorRisk >= 40 => ['Skilled But Risky Trader', 'warning'],
            $score >= 60 && $netProfitLoss > 0 => ['Good Developing Trader', 'success'],
            $score >= 50 && $riskProfile !== 'High Risk' => ['Developing Trader', 'primary'],
            $netProfitLoss > 0 && $riskProfile === 'High Risk' => ['Profitable But Dangerous Trader', 'danger'],
            $technicalSkill === 'Advanced' && $riskProfile === 'High Risk' => ['Technical Skill With Critical Risk', 'danger'],
            $score < 50 && $riskProfile === 'High Risk' => ['High-Risk Underperforming Trader', 'danger'],
            default => ['Weak / Needs Review Trader', 'warning'],
        };

        $strengths = collect();
        if ($scoreIsProvisional && $netProfitLoss >= 0) {
            $strengths->push($sampleStatus.': losses are not being treated as final underperformance because only '.number_format($totalTrades).' trade(s) are available.');
        }
        if ($netProfitLoss > 0) {
            $strengths->push('Positive net P/L of '.number_format($netProfitLoss, 2).'u.');
        }
        if ($winRate >= 55) {
            $strengths->push('Healthy win rate at '.number_format($winRate, 2).'%.');
        }
        if (is_numeric($profitFactor) && (float) $profitFactor >= 1.5) {
            $strengths->push('Favorable profit factor of '.number_format((float) $profitFactor, 2).'.');
        }
        if ($expectancy > 0) {
            $strengths->push('Positive expectancy of '.number_format($expectancy, 2).'u per trade.');
        }
        if (($summary['best_day_rule']['passed'] ?? false)) {
            $strengths->push('Best-day profit concentration is within the 40% consistency limit.');
        }
        if ($positionScore >= 70) {
            $strengths->push('Position sizing is relatively consistent with a '.number_format($positionScore, 2).'/100 score.');
        }
        if ($topPair) {
            $strengths->push('Strongest instrument is '.$topPair['pair'].' with '.number_format((float) $topPair['profit_loss'], 2).'u net P/L.');
        }
        if ($bestSession && (int) ($bestSession['trades'] ?? 0) > 0) {
            $strengths->push('Best session is '.$bestSession['name'].' at '.number_format((float) $bestSession['win_rate'], 2).'% win rate.');
        }

        $risks = collect();
        if ($scoreIsProvisional) {
            $risks->push('Sample size is still small; require more trades before making a final trader-quality judgment.');
        }
        if ($netProfitLoss <= 0) {
            $risks->push('Account is not profitable under the selected filters.');
        }
        if ($drawdownPercent > 10) {
            $risks->push('Drawdown is elevated at '.number_format($drawdownPercent, 2).'%.');
        }
        if ($behaviorRisk >= 40) {
            $risks->push('Behavior risk is on watch at '.number_format($behaviorRisk, 2).'/100.');
        }
        if (($traderStyleProfile['revenge']['detected'] ?? false)) {
            $risks->push(($traderStyleProfile['revenge']['status'] ?? 'Revenge-trading signals').' appeared after losses.');
        }
        if (($traderStyleProfile['gambling']['detected'] ?? false)) {
            $risks->push(($traderStyleProfile['gambling']['status'] ?? 'Gambling-style behavior markers').' were detected.');
        }
        if (($traderStyleProfile['layering']['detected'] ?? false)) {
            $risks->push('Layering/averaging behavior appeared in sample orders.');
        }
        if (($hedgingProfile['detected'] ?? false)) {
            $risks->push('Hedging overlap is present and should be reviewed as insight, not automatic failure.');
        }
        if ($positionScore < 55 && $totalTrades >= 5) {
            $risks->push('Lot sizing is inconsistent and may indicate unstable risk planning.');
        }
        if (! $scoreIsProvisional && ! ($summary['best_day_rule']['passed'] ?? true) && $netProfitLoss > 0) {
            $risks->push('Profit is too concentrated in the best day.');
        }
        if ($weakPair && (float) ($weakPair['profit_loss'] ?? 0) < 0) {
            $risks->push('Weakest instrument is '.$weakPair['pair'].' with '.number_format((float) $weakPair['profit_loss'], 2).'u net P/L.');
        }

        $coachingFocus = collect();
        if ($behaviorRisk >= 40) {
            $coachingFocus->push('Review emotional discipline after losses and reduce reactive entries.');
        }
        if (($traderStyleProfile['layering']['detected'] ?? false)) {
            $coachingFocus->push('Define a clear rule for when layering is allowed, maximum layers, and total exposure limit.');
        }
        if (($traderStyleProfile['gambling']['detected'] ?? false)) {
            $coachingFocus->push('Set daily trade limits and fixed risk-per-trade rules to reduce '.strtolower((string) ($traderStyleProfile['gambling']['status'] ?? 'high-variance execution')).'.');
        }
        if ($positionScore < 70 && ! $scoreIsProvisional) {
            $coachingFocus->push('Standardize lot sizing around a planned risk model.');
        }
        if ($drawdownPercent > 5) {
            $coachingFocus->push('Reduce drawdown pressure before scaling account size.');
        }
        if (! $scoreIsProvisional && ! ($summary['best_day_rule']['passed'] ?? true)) {
            $coachingFocus->push('Build more balanced profit across multiple days instead of depending on one strong day.');
        }
        if ($expectancy <= 0) {
            $coachingFocus->push('Improve setup filtering so average trade expectancy becomes positive.');
        }

        $badges = collect([
            $technicalSkill,
            $riskProfile,
            $traderStyleProfile['style_label'] ?? null,
            $behavioralProfile['label'] ?? null,
            $levelProfile['level'] ?? null,
        ])->filter()->unique()->values();

        $primarySkillSentence = match ($technicalSkill) {
            'Advanced' => 'The trader shows advanced technical ability and can extract profit from the market.',
            'Strong' => 'The trader shows a strong foundation with positive execution quality.',
            'Provisional / Developing' => 'The trader has a small but controlled sample, so the score should be treated as provisional rather than a mature grade.',
            'Developing' => 'The trader has usable trading ability, but the edge is not fully stable yet.',
            default => 'The trader does not yet show enough stable edge under the selected data.',
        };

        $riskSentence = match ($riskProfile) {
            'Controlled' => 'Risk behavior is currently controlled, with acceptable drawdown and limited emotional markers.',
            'Moderate' => 'Risk is moderate and should be monitored as sample size grows.',
            'Needs Monitoring' => 'Risk requires monitoring because behavior, drawdown, or sizing consistency is not fully clean.',
            default => 'Risk is high enough that administration should review this trader before allowing scaling.',
        };

        $personalitySentence = 'Their trading personality reads as '.strtolower((string) ($traderStyleProfile['style_label'] ?? 'unclassified')).', with a '.strtolower((string) ($behavioralProfile['label'] ?? 'balanced')).' directional bias, average holding time of '.($durationProfile['average_label'] ?? 'N/A').', and '.number_format((float) ($summary['trading_days'] ?? 0)).' active trading day(s).';

        $paragraph = $verdict.': '.$primarySkillSentence.' The account score is '
            .number_format($score, 2).'/100 ('.($scoreEvaluationProfile['rating'] ?? 'N/A').'), with '
            .number_format($winRate, 2).'% win rate, '
            .(is_numeric($profitFactor) ? number_format((float) $profitFactor, 2) : (string) $profitFactor)
            .' profit factor, '.number_format($expectancy, 2).'u expectancy, '
            .number_format($drawdownPercent, 2).'% max drawdown, and '
            .number_format($behaviorRisk, 2).'/100 behavior risk. '
            .$personalitySentence.' '.$riskSentence;

        if ($scoreIsProvisional && $sampleNote !== '') {
            $paragraph .= ' '.$sampleNote;
        }

        $administrationAction = match ($tone) {
            'success' => 'Suitable for normal progression, with routine monitoring.',
            'primary' => $scoreIsProvisional ? 'Keep trading under normal risk limits until at least '.self::MIN_RELIABLE_SCORE_TRADES.' trades are available for a reliable grade.' : 'Keep under development review until consistency and sample size improve.',
            'warning' => 'Allow trading with supervision and a clear improvement plan.',
            'danger' => 'Do not scale yet; require risk review, coaching notes, and follow-up evidence.',
            default => 'Collect more data before making an administration decision.',
        };

        return [
            'verdict' => $verdict,
            'tone' => $tone,
            'technical_skill' => $technicalSkill,
            'risk_profile' => $riskProfile,
            'administration_action' => $administrationAction,
            'paragraph' => $paragraph,
            'strengths' => $strengths->isNotEmpty() ? $strengths->take(6)->values() : collect(['No clear strength confirmed yet.']),
            'risks' => $risks->isNotEmpty() ? $risks->take(6)->values() : collect(['No major risk marker detected in the selected records.']),
            'coaching_focus' => $coachingFocus->isNotEmpty() ? $coachingFocus->take(6)->values() : collect(['Maintain current discipline and keep recording enough sample size for review.']),
            'badges' => $badges,
        ];
    }

    private function buildScoreEvaluationProfile(array $summary, array $traderStyleProfile = [], array $positionProfile = []): array
    {
        $totalTrades = (int) ($summary['total_trades'] ?? 0);
        $hasTrades = $totalTrades > 0;
        $bestDayRule = $summary['best_day_rule'] ?? [];
        $currentGeneratedProfit = (float) ($bestDayRule['total_generated_profit'] ?? 0);
        $consistencyPercent = (float) ($bestDayRule['score_percent'] ?? 0);
        $riskRewardDisplay = 'N/A';

        [$winRatePoints, $winRateGrade] = $hasTrades
            ? $this->winRateScore((float) ($summary['win_rate'] ?? 0))
            : [0, 'N/A'];

        [$riskRewardPoints, $riskRewardGrade, $riskRewardDisplay] = $this->riskRewardScore(
            $summary['profit_factor'] ?? 'N/A',
            (float) ($summary['total_profit'] ?? 0),
            (float) ($summary['total_loss'] ?? 0),
            $hasTrades
        );

        [$recoveryPoints, $recoveryGrade, $recoveryDisplay, $recoveryStatus, $recoveryTone] = $this->recoveryFactorScore(
            $summary['recovery_factor'] ?? 'N/A',
            (float) ($summary['net_profit_loss'] ?? 0),
            (float) ($summary['max_drawdown_amount'] ?? 0),
            $hasTrades
        );

        [$consistencyPoints, $consistencyGrade] = $hasTrades
            ? $this->bestDayConsistencyScore($currentGeneratedProfit, $consistencyPercent)
            : [0, 'N/A'];

        [$positionConsistencyPoints, $positionConsistencyGrade, $positionConsistencyStatus, $positionConsistencyTone] = $this->positionConsistencyScore($positionProfile, $hasTrades);

        [$expectancyPoints, $expectancyGrade] = $hasTrades
            ? $this->expectancyScore((float) ($summary['expectancy'] ?? 0))
            : [0, 'N/A'];

        [$drawdownPenalty, $drawdownGrade] = $hasTrades
            ? $this->drawdownPenalty((float) ($summary['overall_loss_percent'] ?? 0))
            : [0, 'N/A'];

        $components = [
            [
                'metric' => 'Win Rate',
                'value' => number_format((float) ($summary['win_rate'] ?? 0), 2).'%',
                'grade' => $winRateGrade,
                'points' => $winRatePoints,
                'max_points' => 30,
                'is_penalty' => false,
                'status' => $winRateGrade === 'N/A' ? 'No Data' : ($winRatePoints >= 20 ? 'Strong' : 'Developing'),
                'tone' => $winRatePoints >= 20 ? 'success' : ($winRatePoints > 0 ? 'warning' : 'secondary'),
                'formula' => 'Winning trades divided by closed winning plus losing trades.',
                'calculation' => ($summary['winning_trades'] ?? 0).' wins / '.(($summary['winning_trades'] ?? 0) + ($summary['losing_trades'] ?? 0)).' closed trades',
            ],
            [
                'metric' => 'Risk Reward',
                'value' => $riskRewardDisplay,
                'grade' => $riskRewardGrade,
                'points' => $riskRewardPoints,
                'max_points' => 30,
                'is_penalty' => false,
                'status' => $riskRewardGrade === 'N/A' ? 'No Data' : ($riskRewardPoints >= 20 ? 'Strong' : 'Developing'),
                'tone' => $riskRewardPoints >= 20 ? 'success' : ($riskRewardPoints > 0 ? 'warning' : 'secondary'),
                'formula' => 'Total winning P/L divided by total losing P/L.',
                'calculation' => number_format((float) ($summary['total_profit'] ?? 0), 2).'u profit / '.number_format((float) ($summary['total_loss'] ?? 0), 2).'u loss',
            ],
            [
                'metric' => 'Recovery Factor',
                'value' => $recoveryDisplay,
                'grade' => $recoveryGrade,
                'points' => $recoveryPoints,
                'max_points' => 15,
                'is_penalty' => false,
                'status' => $recoveryStatus,
                'tone' => $recoveryTone,
                'formula' => 'Net P/L divided by maximum drawdown. Higher means profit was earned with less equity stress.',
                'calculation' => number_format((float) ($summary['net_profit_loss'] ?? 0), 2).'u / '.number_format((float) ($summary['max_drawdown_amount'] ?? 0), 2).'u drawdown',
            ],
            [
                'metric' => 'Best Day Consistency',
                'value' => number_format($consistencyPercent, 2).'%',
                'grade' => $consistencyGrade,
                'points' => $consistencyPoints,
                'max_points' => 10,
                'is_penalty' => false,
                'status' => (string) ($bestDayRule['status'] ?? 'N/A'),
                'tone' => ($bestDayRule['passed'] ?? false) ? 'success' : ($currentGeneratedProfit > 0 ? 'warning' : 'secondary'),
                'formula' => 'Biggest winning day divided by total generated profit. Lower concentration is better.',
                'calculation' => number_format((float) ($bestDayRule['best_winning_day'] ?? 0), 2).'u / '.number_format($currentGeneratedProfit, 2).'u',
            ],
            [
                'metric' => 'Position Consistency',
                'value' => number_format((float) data_get($positionProfile, 'score', 0), 2).'/100',
                'grade' => $positionConsistencyGrade,
                'points' => $positionConsistencyPoints,
                'max_points' => 5,
                'is_penalty' => false,
                'status' => $positionConsistencyStatus,
                'tone' => $positionConsistencyTone,
                'formula' => 'Position consistency score multiplied by 5%. This rewards stable risk sizing without overpowering performance metrics.',
                'calculation' => number_format((float) data_get($positionProfile, 'score', 0), 2).'/100 x 5 pts = '.number_format($positionConsistencyPoints, 2).' pts',
            ],
            [
                'metric' => 'Expectancy',
                'value' => number_format((float) ($summary['expectancy'] ?? 0), 2).'u',
                'grade' => $expectancyGrade,
                'points' => $expectancyPoints,
                'max_points' => 10,
                'is_penalty' => false,
                'status' => (float) ($summary['expectancy'] ?? 0) > 0 ? 'Positive' : ((float) ($summary['expectancy'] ?? 0) < 0 ? 'Negative' : 'Flat'),
                'tone' => (float) ($summary['expectancy'] ?? 0) > 0 ? 'success' : ((float) ($summary['expectancy'] ?? 0) < 0 ? 'danger' : 'secondary'),
                'formula' => '(Win rate x average win) - (loss rate x average loss).',
                'calculation' => number_format((float) ($summary['average_win'] ?? 0), 2).'u avg win / '.number_format((float) ($summary['average_loss'] ?? 0), 2).'u avg loss',
            ],
            [
                'metric' => 'Drawdown Penalty',
                'value' => number_format((float) ($summary['overall_loss_percent'] ?? 0), 2).'%',
                'grade' => $drawdownGrade,
                'points' => -$drawdownPenalty,
                'max_points' => 0,
                'is_penalty' => true,
                'status' => $drawdownPenalty > 0 ? 'Penalty' : 'No Penalty',
                'tone' => $drawdownPenalty > 0 ? 'danger' : 'success',
                'formula' => 'Journal score loss pressure percentage. Penalty points are subtracted from total score.',
                'calculation' => number_format((float) ($summary['overall_loss_percent'] ?? 0), 2).'% current loss pressure',
            ],
        ];

        $baseTotalScore = $hasTrades
            ? round((float) collect($components)->sum(fn (array $component): float => (float) ($component['points'] ?? 0)), 2)
            : 0.0;
        $behaviorScorePenalty = (new TradingJournalAnalytics())->behaviorScorePenalty($traderStyleProfile, $baseTotalScore);
        $behaviorPenaltyPercent = $hasTrades ? (float) data_get($behaviorScorePenalty, 'percent', 0) : 0.0;
        $behaviorPenaltyPoints = $hasTrades ? abs((float) data_get($behaviorScorePenalty, 'points', 0)) : 0.0;

        $components[] = [
            'metric' => 'Behaviour Tier Penalty',
            'value' => number_format($behaviorPenaltyPercent, 0).'%',
            'grade' => data_get($behaviorScorePenalty, 'tier_label', 'Clear'),
            'points' => -$behaviorPenaltyPoints,
            'max_points' => 0,
            'is_penalty' => true,
            'status' => data_get($behaviorScorePenalty, 'status', 'No Behaviour Penalty'),
            'tone' => data_get($behaviorScorePenalty, 'tone', 'success'),
            'formula' => 'Mid revenge/gambling tier subtracts 5% from the base score. High or critical tier subtracts 10%. Low tier has no score penalty.',
            'calculation' => 'Base '.number_format($baseTotalScore, 2).' pts x '.number_format($behaviorPenaltyPercent, 0).'% | '.data_get($behaviorScorePenalty, 'trigger_label', 'Clear or low revenge/gambling tier'),
        ];

        $rawTotalScore = $hasTrades
            ? (float) data_get($behaviorScorePenalty, 'adjusted_score', $baseTotalScore)
            : 0.0;
        $sampleSizeProfile = $this->scoreSampleSizeProfile($summary, $traderStyleProfile, $rawTotalScore, $hasTrades);
        $totalScore = (float) ($sampleSizeProfile['score'] ?? $rawTotalScore);

        if ($hasTrades && (bool) ($sampleSizeProfile['is_provisional'] ?? false)) {
            $sampleAdjustment = round($totalScore - $rawTotalScore, 2);

            $components[] = [
                'metric' => 'Small Sample Adjustment',
                'value' => $totalTrades.' / '.self::MIN_RELIABLE_SCORE_TRADES.' trades',
                'grade' => 'Provisional',
                'points' => $sampleAdjustment,
                'max_points' => 0,
                'is_penalty' => false,
                'is_adjustment' => true,
                'status' => (string) ($sampleSizeProfile['status'] ?? 'Provisional'),
                'tone' => (string) ($sampleSizeProfile['tone'] ?? 'primary'),
                'formula' => 'When sample size is small, raw score is blended with a neutral provisional baseline unless strong risk evidence exists.',
                'calculation' => 'Raw '.number_format($rawTotalScore, 2).' pts -> provisional '.number_format($totalScore, 2).' pts | confidence '.number_format((float) ($sampleSizeProfile['confidence_percent'] ?? 0), 0).'%',
            ];
        }

        return [
            'score' => $totalScore,
            'raw_score' => $rawTotalScore,
            'base_score' => $baseTotalScore,
            'behavior_score_penalty' => $behaviorScorePenalty,
            'rating' => $this->scoreEvaluationRating($totalScore, $hasTrades, $sampleSizeProfile),
            'meaning' => $this->scoreEvaluationMeaning($totalScore, $hasTrades, $sampleSizeProfile),
            'max_positive_points' => 100,
            'formula' => 'Win Rate + Risk Reward + Recovery Factor + Best Day Consistency + Position Consistency + Expectancy - Drawdown Penalty - Behaviour Tier Penalty'.((bool) ($sampleSizeProfile['is_provisional'] ?? false) ? ' + Small Sample Adjustment' : ''),
            'recovery_factor' => $recoveryDisplay,
            'recovery_factor_progress' => $this->clampProgress(is_numeric($summary['recovery_factor'] ?? null) ? ((float) $summary['recovery_factor'] / 3) * 100 : ($recoveryDisplay === 'No Drawdown' ? 100 : 0)),
            'score_is_provisional' => (bool) ($sampleSizeProfile['is_provisional'] ?? false),
            'sample_size_status' => (string) ($sampleSizeProfile['status'] ?? ($hasTrades ? 'Reliable Sample' : 'No Data')),
            'sample_size_tone' => (string) ($sampleSizeProfile['tone'] ?? ($hasTrades ? 'success' : 'secondary')),
            'sample_size_confidence' => (float) ($sampleSizeProfile['confidence_percent'] ?? 0),
            'sample_size_note' => (string) ($sampleSizeProfile['note'] ?? ''),
            'sample_size_formula' => (string) ($sampleSizeProfile['formula'] ?? ''),
            'sample_size_calculation' => (string) ($sampleSizeProfile['calculation'] ?? ''),
            'sample_size_baseline' => (float) ($sampleSizeProfile['baseline_score'] ?? 0),
            'sample_size_raw_weight' => (float) ($sampleSizeProfile['raw_weight_percent'] ?? 0),
            'sample_size_cap' => (float) ($sampleSizeProfile['sample_cap'] ?? 0),
            'sample_size_adjustment' => (float) ($sampleSizeProfile['adjustment'] ?? 0),
            'minimum_reliable_trades' => self::MIN_RELIABLE_SCORE_TRADES,
            'components' => $components,
            'criteria_bands' => $this->scoreCriteriaBands(),
            'grade_ranking' => $this->scoreGradeRanking(),
        ];
    }

    private function scoreSampleSizeProfile(array $summary, array $traderStyleProfile, float $rawScore, bool $hasTrades): array
    {
        $totalTrades = (int) ($summary['total_trades'] ?? 0);
        $confidencePercent = $hasTrades
            ? round(min(100, ($totalTrades / self::MIN_RELIABLE_SCORE_TRADES) * 100), 2)
            : 0.0;

        $baseProfile = [
            'is_provisional' => $hasTrades && $totalTrades < self::MIN_RELIABLE_SCORE_TRADES,
            'score' => round($rawScore, 2),
            'raw_score' => round($rawScore, 2),
            'confidence_percent' => $confidencePercent,
            'status' => $hasTrades ? 'Reliable Sample' : 'No Data',
            'tone' => $hasTrades ? 'success' : 'secondary',
            'note' => $hasTrades
                ? 'Sample size is large enough for the normal score formula.'
                : 'No closed trades are available for score evaluation.',
        ];

        if (! $hasTrades || $totalTrades >= self::MIN_RELIABLE_SCORE_TRADES) {
            return $baseProfile;
        }

        $netProfitLoss = (float) ($summary['net_profit_loss'] ?? 0);
        $totalProfit = (float) ($summary['total_profit'] ?? 0);
        $totalLoss = (float) ($summary['total_loss'] ?? 0);
        $maxDrawdownPercent = (float) ($summary['max_drawdown_percent'] ?? 0);
        $overallLossPercent = (float) ($summary['overall_loss_percent'] ?? 0);
        $maxDailyLossPercent = (float) ($summary['max_daily_loss_percent'] ?? 0);
        $behaviorRisk = (float) data_get($traderStyleProfile, 'final_score', data_get($traderStyleProfile, 'risk_score', 0));
        $profitFactor = $summary['profit_factor'] ?? null;
        $recoveryFactor = $summary['recovery_factor'] ?? null;

        $lossesRecovered = $totalLoss > 0 && $totalProfit >= $totalLoss && $netProfitLoss >= 0;
        $positiveRecoveryQuality = $lossesRecovered
            || (is_numeric($profitFactor) && (float) $profitFactor >= 1.0 && $netProfitLoss >= 0)
            || (is_numeric($recoveryFactor) && (float) $recoveryFactor >= 1.0);
        $controlledRisk = $maxDrawdownPercent <= 5
            && $overallLossPercent <= 2
            && $maxDailyLossPercent < (float) config('trading_detection.daily_loss_limit_percent', 5.0)
            && $behaviorRisk < 45;
        $strongRiskEvidence = $maxDailyLossPercent >= (float) config('trading_detection.daily_loss_limit_percent', 5.0)
            || $maxDrawdownPercent > 10
            || $behaviorRisk >= 65
            || ($netProfitLoss < 0 && $overallLossPercent > 2);

        $baseline = match (true) {
            $strongRiskEvidence => $rawScore,
            $lossesRecovered && $controlledRisk => 68.0,
            $netProfitLoss >= 0 && $controlledRisk && $positiveRecoveryQuality => 64.0,
            $netProfitLoss >= 0 && $behaviorRisk < 45 => 58.0,
            default => 50.0,
        };

        $rawWeight = max(0.25, min(0.85, $totalTrades / self::MIN_RELIABLE_SCORE_TRADES));
        $provisionalScore = $strongRiskEvidence
            ? $rawScore
            : (($rawScore * $rawWeight) + ($baseline * (1 - $rawWeight)));

        if ($lossesRecovered && $controlledRisk && ! $strongRiskEvidence) {
            $provisionalScore = max($provisionalScore, 65.0);
        }

        $sampleCap = $totalTrades <= 3 ? 72.0 : 80.0;
        $provisionalScore = $strongRiskEvidence
            ? $provisionalScore
            : min($sampleCap, max($rawScore, $provisionalScore));
        $finalProvisionalScore = round(max(0, min(100, $provisionalScore)), 2);
        $adjustment = round($finalProvisionalScore - $rawScore, 2);

        $status = match (true) {
            $strongRiskEvidence => 'Provisional - Risk Evidence',
            $lossesRecovered && $controlledRisk => 'Provisional - Controlled Recovery',
            $netProfitLoss >= 0 && $controlledRisk => 'Provisional - Controlled Sample',
            default => 'Provisional - Need More Trades',
        };

        $note = match (true) {
            $strongRiskEvidence => 'Only '.$totalTrades.' trade(s) are available, but strong risk evidence remains active, so the raw score is not lifted.',
            $lossesRecovered && $controlledRisk => 'Only '.$totalTrades.' trade(s) are available. Losses were recovered without high behavior risk, so the report uses a provisional controlled-recovery score instead of treating the raw formula as final.',
            $netProfitLoss >= 0 && $controlledRisk => 'Only '.$totalTrades.' trade(s) are available. The account is non-negative with controlled risk, so the report uses a provisional score until more trades are recorded.',
            default => 'Only '.$totalTrades.' trade(s) are available. Treat this score as provisional until at least '.self::MIN_RELIABLE_SCORE_TRADES.' trades are recorded.',
        };

        return [
            'is_provisional' => true,
            'score' => $finalProvisionalScore,
            'raw_score' => round($rawScore, 2),
            'confidence_percent' => $confidencePercent,
            'status' => $status,
            'tone' => $strongRiskEvidence ? 'warning' : 'primary',
            'note' => $note,
            'losses_recovered' => $lossesRecovered,
            'controlled_risk' => $controlledRisk,
            'strong_risk_evidence' => $strongRiskEvidence,
            'raw_weight_percent' => round($rawWeight * 100, 2),
            'baseline_score' => round($baseline, 2),
            'sample_cap' => $sampleCap,
            'adjustment' => $adjustment,
            'formula' => 'Provisional score = min(sample cap, max(raw score, raw score x sample weight + baseline x remaining weight)).',
            'calculation' => 'min('.number_format($sampleCap, 2).', max('.number_format($rawScore, 2).', '.number_format($rawScore, 2).' x '.number_format($rawWeight * 100, 0).'% + '.number_format($baseline, 2).' x '.number_format((1 - $rawWeight) * 100, 0).'%)) = '.number_format($finalProvisionalScore, 2),
        ];
    }

    private function winRateScore(float $winRate): array
    {
        return match (true) {
            $winRate >= 75 => [30, 'A'],
            $winRate >= 65 => [25, 'B+'],
            $winRate >= 60 => [25, 'B'],
            $winRate >= 55 => [20, 'C+'],
            $winRate >= 50 => [17, 'C'],
            $winRate >= 45 => [15, 'D+'],
            $winRate >= 35 => [10, 'D'],
            $winRate > 0 => [5, 'E'],
            default => [0, 'N/A'],
        };
    }

    private function riskRewardScore($riskReward, float $totalProfit, float $totalLoss, bool $hasTrades): array
    {
        if (! $hasTrades) {
            return [0, 'N/A', 'N/A'];
        }

        if ($totalProfit > 0 && $totalLoss == 0.0) {
            return [30, 'A+', 'Perfect'];
        }

        if (! is_numeric($riskReward)) {
            return [0, 'F', 'N/A'];
        }

        $riskReward = (float) $riskReward;

        [$points, $grade] = match (true) {
            $riskReward >= 5.75 => [30, 'A+'],
            $riskReward >= 3.45 => [25, 'A'],
            $riskReward >= 2.30 => [20, 'B'],
            $riskReward >= 1.73 => [15, 'C'],
            $riskReward >= 1.15 => [10, 'D'],
            $riskReward > 0.50 => [5, 'E'],
            default => [0, 'F'],
        };

        return [$points, $grade, number_format($riskReward, 2)];
    }

    private function recoveryFactorScore($recoveryFactor, float $netProfitLoss, float $maxDrawdownAmount, bool $hasTrades): array
    {
        if (! $hasTrades) {
            return [0, 'N/A', 'N/A', 'No Data', 'secondary'];
        }

        if ($netProfitLoss <= 0) {
            return [0, 'F', is_numeric($recoveryFactor) ? number_format((float) $recoveryFactor, 2) : 'N/A', 'No Recovery', 'danger'];
        }

        if ($maxDrawdownAmount <= 0) {
            return [15, 'A+', 'No Drawdown', 'Elite', 'success'];
        }

        if (! is_numeric($recoveryFactor)) {
            $recoveryFactor = $netProfitLoss / $maxDrawdownAmount;
        }

        $recoveryFactor = (float) $recoveryFactor;

        [$points, $grade] = match (true) {
            $recoveryFactor >= 3.00 => [15, 'A+'],
            $recoveryFactor >= 2.00 => [12, 'A'],
            $recoveryFactor >= 1.50 => [10, 'B'],
            $recoveryFactor >= 1.00 => [7, 'C'],
            $recoveryFactor >= 0.50 => [4, 'D'],
            $recoveryFactor > 0 => [2, 'E'],
            default => [0, 'F'],
        };

        $status = match (true) {
            $points >= 12 => 'Strong',
            $points >= 7 => 'Healthy',
            $points > 0 => 'Developing',
            default => 'Weak',
        };

        $tone = $points >= 10 ? 'success' : ($points > 0 ? 'warning' : 'danger');

        return [$points, $grade, number_format($recoveryFactor, 2), $status, $tone];
    }

    private function bestDayConsistencyScore(float $currentGeneratedProfit, float $consistencyPercent): array
    {
        if ($currentGeneratedProfit <= 0) {
            return [0, 'N/A'];
        }

        return match (true) {
            $consistencyPercent <= 20 => [10, 'S'],
            $consistencyPercent <= 30 => [8, 'A'],
            $consistencyPercent <= TradingJournalAnalytics::BEST_DAY_LIMIT_PERCENT => [6, 'B'],
            $consistencyPercent <= 50 => [4, 'C'],
            $consistencyPercent <= 65 => [2, 'D'],
            default => [0, 'F'],
        };
    }

    private function positionConsistencyScore(array $positionProfile, bool $hasTrades): array
    {
        if (! $hasTrades) {
            return [0.0, 'N/A', 'No Data', 'secondary'];
        }

        $score = max(0, min(100, (float) data_get($positionProfile, 'score', 0)));
        $points = round(($score / 100) * 5, 2);
        $grade = (string) data_get($positionProfile, 'grade', 'N/A');
        $status = (string) data_get($positionProfile, 'status', ($points >= 3.5 ? 'Controlled' : 'Needs Review'));
        $isDynamic = (bool) data_get($positionProfile, 'is_dynamic', false);
        $tone = $score >= 70 && ! $isDynamic
            ? 'success'
            : ($score >= 45 ? 'warning' : 'danger');

        return [$points, $grade, $status, $tone];
    }

    private function expectancyScore(float $expectancy): array
    {
        return match (true) {
            $expectancy >= 40 => [10, 'A+'],
            $expectancy >= 20 => [7, 'B'],
            $expectancy >= 10 => [5, 'C'],
            $expectancy >= 0 => [0, 'N/A'],
            $expectancy >= -10 => [-1, 'F'],
            $expectancy >= -20 => [-2, 'F'],
            default => [0, 'N/A'],
        };
    }

    private function drawdownPenalty(float $drawdownPercent): array
    {
        [$penalty, $grade] = match (true) {
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

        return [abs($penalty), $grade];
    }

    private function scoreEvaluationRating(float $totalScore, bool $hasTrades, array $sampleSizeProfile = []): string
    {
        if (! $hasTrades) {
            return 'N/A';
        }

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
            $totalScore >= 40 => 'D-',
            $totalScore >= 30 => 'E',
            default => 'F',
        };

        return (bool) ($sampleSizeProfile['is_provisional'] ?? false)
            ? 'Provisional '.$rating
            : $rating;
    }

    private function scoreEvaluationMeaning(float $totalScore, bool $hasTrades, array $sampleSizeProfile = []): string
    {
        if (! $hasTrades) {
            return 'No score yet. Add closed trades to activate the scoring model.';
        }

        if ((bool) ($sampleSizeProfile['is_provisional'] ?? false)) {
            return trim((string) ($sampleSizeProfile['note'] ?? 'This score is provisional because the selected sample is small.'))
                .' Raw formula score: '.number_format((float) ($sampleSizeProfile['raw_score'] ?? $totalScore), 2)
                .'/100; provisional display score: '.number_format($totalScore, 2)
                .'/100.';
        }

        return match (true) {
            $totalScore >= 85 => 'Exceptional: elite trading performance across the core score metrics.',
            $totalScore >= 70 => 'Good: strong trading foundation with only minor improvement areas.',
            $totalScore >= 50 => 'Intermediate: adequate performance with clear development opportunities.',
            $totalScore >= 39 => 'Below average: basic competency with significant areas needing improvement.',
            default => 'Poor: requires immediate review and stronger risk control.',
        };
    }

    private function scoreCriteriaBands(): array
    {
        return [
            [
                'metric' => 'Win Rate',
                'max_points' => '30 pts',
                'bands' => '75%+ = 30 A; 65%+ = 25 B+; 60%+ = 25 B; 55%+ = 20 C+; 50%+ = 17 C; 45%+ = 15 D+; 35%+ = 10 D; above 0% = 5 E.',
            ],
            [
                'metric' => 'Risk Reward',
                'max_points' => '30 pts',
                'bands' => 'Perfect or 5.75+ = 30 A+; 3.45+ = 25 A; 2.30+ = 20 B; 1.73+ = 15 C; 1.15+ = 10 D; above 0.50 = 5 E.',
            ],
            [
                'metric' => 'Recovery Factor',
                'max_points' => '15 pts',
                'bands' => 'No drawdown or 3.00+ = 15 A+; 2.00+ = 12 A; 1.50+ = 10 B; 1.00+ = 7 C; 0.50+ = 4 D; positive = 2 E; no recovery = 0 F.',
            ],
            [
                'metric' => 'Best Day Consistency',
                'max_points' => '10 pts',
                'bands' => 'Best day <=20% of generated profit = 10 S; <=30% = 8 A; <=40% = 6 B; <=50% = 4 C; <=65% = 2 D; above 65% = 0 F.',
            ],
            [
                'metric' => 'Position Consistency',
                'max_points' => '5 pts',
                'bands' => 'Position consistency score / 100 x 5 pts. Rewards stable lot sizing, main-lot discipline, and low sizing variation.',
            ],
            [
                'metric' => 'Expectancy',
                'max_points' => '10 pts',
                'bands' => '40+ = 10 A+; 20+ = 7 B; 10+ = 5 C; 0+ = 0 N/A; -10+ = -1 F; -20+ = -2 F.',
            ],
            [
                'metric' => 'Drawdown Penalty',
                'max_points' => 'Penalty',
                'bands' => '>90% = -35 F; >80% = -20 F; >60% = -15 F; >40% = -10 F; >30% = -8 E; >20% = -6 D; >10% = -4 C; >5% = -2 B; >2% = -1 A; otherwise 0 A+.',
            ],
            [
                'metric' => 'Behaviour Tier Penalty',
                'max_points' => 'Penalty',
                'bands' => 'Clear/Low revenge and gambling tiers = 0; any Medium tier = -5% of base score; any High tier = -10% of base score. The highest tier applies once.',
            ],
            [
                'metric' => 'Small Sample Adjustment',
                'max_points' => 'Adjustment',
                'bands' => 'Under 10 trades = provisional. Controlled recovery or non-negative P/L with low behavior risk is blended toward a neutral score; strong risk evidence keeps the raw score.',
            ],
        ];
    }

    private function scoreGradeRanking(): array
    {
        return [
            ['grade' => 'S', 'range' => '95+', 'description' => 'Elite score across win rate, risk reward, recovery quality, consistency, expectancy, and drawdown control.'],
            ['grade' => 'A+', 'range' => '90 - 94.99', 'description' => 'Excellent performance with very strong metric alignment.'],
            ['grade' => 'A', 'range' => '85 - 89.99', 'description' => 'Strong account quality with only small scoring gaps.'],
            ['grade' => 'B', 'range' => '70 - 84.99', 'description' => 'Good trading foundation with a few improvement areas.'],
            ['grade' => 'C', 'range' => '50 - 69.99', 'description' => 'Developing performance. Review weaker components before scaling.'],
            ['grade' => 'D/E', 'range' => '30 - 49.99', 'description' => 'Weak score. Profitability or risk control needs attention.'],
            ['grade' => 'F', 'range' => 'Below 30', 'description' => 'High review priority. Score is not yet aligned with desk requirements.'],
            ['grade' => 'Provisional', 'range' => 'Under 10 trades', 'description' => 'Sample is too small for mature grading. Review raw score and provisional note together.'],
        ];
    }

    private function buildRuleMonitor(array $summary): array
    {
        $maxDailyLossPercent = 5;
        $maxTotalLossPercent = 10;
        $consistencyLimitPercent = TradingJournalAnalytics::BEST_DAY_LIMIT_PERCENT;
        $minProfitableDays = 3;
        $bestDayRule = $summary['best_day_rule'];
        $grossProfitRule = $summary['gross_profit_rule'];
        $recoveryFactor = $summary['recovery_factor'] ?? 'N/A';
        $recoveryDisplay = is_numeric($recoveryFactor) ? number_format((float) $recoveryFactor, 2) : (string) $recoveryFactor;
        $recoveryProgress = is_numeric($recoveryFactor)
            ? $this->clampProgress(((float) $recoveryFactor / 3) * 100)
            : ($recoveryDisplay === 'No Drawdown' ? 100 : 0);
        $recoveryPassed = $recoveryDisplay === 'No Drawdown' || (is_numeric($recoveryFactor) && (float) $recoveryFactor >= 1.5);

        $dailyLossUsage = $maxDailyLossPercent > 0 ? round(($summary['max_daily_loss_percent'] / $maxDailyLossPercent) * 100, 2) : 0;
        $totalLossUsage = $maxTotalLossPercent > 0 ? round(($summary['max_drawdown_percent'] / $maxTotalLossPercent) * 100, 2) : 0;
        $profitableDaysProgress = round(($summary['profitable_days'] / $minProfitableDays) * 100, 2);

        return [
            [
                'key' => 'recovery_factor',
                'title' => 'Recovery Quality',
                'value' => $recoveryDisplay,
                'progress' => $recoveryProgress,
                'status' => $recoveryPassed ? 'Strong' : 'Monitoring',
                'tone' => $recoveryPassed ? 'success' : 'primary',
                'caption' => 'Net P/L per 1u max drawdown',
            ],
            [
                'key' => 'gross_profit_floor',
                'title' => '2% Gross Profit',
                'value' => number_format($grossProfitRule['gross_profit'], 2).'u / '.number_format($grossProfitRule['required_amount'], 2).'u',
                'progress' => $grossProfitRule['minimum_percent'] > 0 ? $this->clampProgress(($grossProfitRule['achieved_percent'] / $grossProfitRule['minimum_percent']) * 100) : 0,
                'status' => $grossProfitRule['passed'] ? 'Passed' : 'Monitoring',
                'tone' => $grossProfitRule['passed'] ? 'success' : 'primary',
                'caption' => 'Minimum '.number_format($grossProfitRule['minimum_percent'], 0).'% gross profit on account balance',
            ],
            [
                'key' => 'daily_loss',
                'title' => 'Max Daily Loss',
                'value' => number_format($summary['max_daily_loss_percent'], 2).'% used',
                'progress' => $this->clampProgress($dailyLossUsage),
                'status' => $summary['max_daily_loss_percent'] > $maxDailyLossPercent ? 'Breached' : 'Within Limit',
                'tone' => $summary['max_daily_loss_percent'] > $maxDailyLossPercent ? 'danger' : 'success',
                'caption' => $maxDailyLossPercent.'% limit',
            ],
            [
                'key' => 'total_loss',
                'title' => 'Max Total Drawdown',
                'value' => number_format($summary['max_drawdown_percent'], 2).'% used',
                'progress' => $this->clampProgress($totalLossUsage),
                'status' => $summary['max_drawdown_percent'] > $maxTotalLossPercent ? 'Breached' : 'Within Limit',
                'tone' => $summary['max_drawdown_percent'] > $maxTotalLossPercent ? 'danger' : 'success',
                'caption' => $maxTotalLossPercent.'% limit',
            ],
            [
                'key' => 'consistency',
                'title' => '40% Best Day Rule',
                'value' => number_format($bestDayRule['score_percent'], 2).'%',
                'progress' => $summary['consistency_percent'] > 0 ? $this->clampProgress(($summary['consistency_percent'] / $consistencyLimitPercent) * 100) : 0,
                'status' => $bestDayRule['passed'] ? 'Passed' : 'Monitoring',
                'tone' => $bestDayRule['passed'] ? 'success' : 'warning',
                'caption' => 'Best day max '.number_format($consistencyLimitPercent, 0).'%; need '.number_format($bestDayRule['additional_profit_needed'], 2).'u more if pending',
            ],
            [
                'key' => 'profitable_days',
                'title' => 'Profitable Days',
                'value' => $summary['profitable_days'].' / '.$minProfitableDays,
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

        if ($rulesPassed >= 5) {
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

    private function capitalSummary(?int $userId, ?int $month, ?int $year, string $accountStage = self::ACCOUNT_STAGE_OVERALL): array
    {
        $accountStage = $this->normalizeAccountStageFilter($accountStage);
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
        $basisLabel = 'Capital transactions';

        if ($userId && in_array($accountStage, [self::ACCOUNT_STAGE_PHASE_2, self::ACCOUNT_STAGE_FUNDED], true) && $deposits > 0) {
            $basisLabel = $accountStage === self::ACCOUNT_STAGE_FUNDED
                ? 'Funded account initial capital'
                : 'Phase 2 initial capital';
        }

        return [
            'total_deposits' => round((float) $deposits, 2),
            'total_withdrawals' => round((float) $withdrawals, 2),
            'net_capital' => round((float) $transactions->sum('amount'), 2),
            'transactions' => $transactions->count(),
            'basis_label' => $basisLabel,
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

    private function normalizeAccountStageFilter($value): string
    {
        $value = strtolower((string) ($value ?: self::ACCOUNT_STAGE_OVERALL));

        return array_key_exists($value, $this->accountStageOptions())
            ? $value
            : self::ACCOUNT_STAGE_OVERALL;
    }

    private function accountStageOptions(): array
    {
        return [
            self::ACCOUNT_STAGE_OVERALL => [
                'label' => 'Overall Evaluation',
                'short_label' => 'Overall',
                'description' => 'Phase 1 + Phase 2 statistics, excluding funded account trades.',
            ],
            self::ACCOUNT_STAGE_PHASE_1 => [
                'label' => 'Phase 1 Statistics',
                'short_label' => 'Phase 1',
                'description' => 'Only Phase 1 current or archived journal records.',
            ],
            self::ACCOUNT_STAGE_PHASE_2 => [
                'label' => 'Phase 2 Statistics',
                'short_label' => 'Phase 2',
                'description' => 'Only Phase 2 current or archived journal records.',
            ],
            self::ACCOUNT_STAGE_FUNDED => [
                'label' => 'Funded Account',
                'short_label' => 'Funded',
                'description' => 'Only funded account journal records.',
            ],
        ];
    }

    private function accountStageLabel(string $stage): string
    {
        return $this->accountStageOptions()[$this->normalizeAccountStageFilter($stage)]['short_label'];
    }

    private function accountStageDescription(string $stage): string
    {
        return $this->accountStageOptions()[$this->normalizeAccountStageFilter($stage)]['description'];
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
