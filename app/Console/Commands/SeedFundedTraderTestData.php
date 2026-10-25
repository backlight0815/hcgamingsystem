<?php

namespace App\Console\Commands;

use App\Models\Capital;
use App\Models\PropFirmEvaluationQuestion;
use App\Models\TradingJournal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SeedFundedTraderTestData extends Command
{
    protected $signature = 'trading:seed-funded-trader-test-data
        {--password=password : Password assigned to generated trader accounts}
        {--fresh : Delete existing generated funded trader test accounts before recreating them}';

    protected $description = 'Generate prop firm funded trader workflow test accounts, reviews, locks, and evaluation questions.';

    private const MARKER = '[TEST-SEED:FUNDED-TRADER-WORKFLOW]';

    private const REVIEW_PHASE1_PROFIT_TARGET = 'phase1_profit_target_review';

    public function handle(): int
    {
        foreach (['users', 'trading_journals', 'capitals', 'prop_firm_evaluation_questions'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table {$table} does not exist.");

                return self::FAILURE;
            }
        }

        $password = (string) $this->option('password');
        $scenarios = $this->scenarios();

        DB::transaction(function () use ($scenarios, $password) {
            $emails = collect($scenarios)->pluck('email')->all();

            if ($this->option('fresh')) {
                $users = User::withTrashed()->whereIn('email', $emails)->get();

                foreach ($users as $user) {
                    $this->deleteGeneratedDataForUser($user);
                    $user->forceDelete();
                }
            }

            foreach ($scenarios as $index => $scenario) {
                $user = $this->upsertTrader($scenario, $password, $index + 1);
                $this->deleteGeneratedDataForUser($user);
                $this->seedCapital($user, (float) ($scenario['capital'] ?? 10000));
                $this->seedScenarioTrades($user, $scenario);
                $this->seedQuestions($user, $scenario['questions'] ?? []);
            }
        });

        $this->info('Generated funded trader workflow test data.');
        $this->line('Password for all generated traders: '.$password);
        $this->table(
            ['Username', 'Email', 'Scenario', 'Expected Risk'],
            collect($scenarios)->map(fn ($scenario) => [
                $scenario['username'],
                $scenario['email'],
                $scenario['label'],
                $scenario['expected_risk_category'] ?? '-',
            ])->all()
        );

        return self::SUCCESS;
    }

    private function scenarios(): array
    {
        return [
            [
                'label' => 'Phase 1 active evaluation - can trade/import',
                'name' => 'Prop Phase One Active',
                'username' => 'prop_phase1_active',
                'email' => 'prop_phase1_active@propfirm.test',
                'prop_firm_phase' => 1,
                'funded_status' => 0,
                'review_status' => 'none',
                'locked' => false,
                'trade_count' => 8,
                'profit_bias' => 0.8,
            ],
            [
                'label' => 'Phase 1 passed - pending admin approval to Phase 2',
                'name' => 'Prop Pending Phase Two',
                'username' => 'prop_pending_phase2',
                'email' => 'prop_pending_phase2@propfirm.test',
                'prop_firm_phase' => 1,
                'funded_status' => 0,
                'review_status' => 'pending_phase2',
                'review_phase' => 1,
                'locked' => true,
                'review_note' => 'Phase 1 passed. Awaiting administration approval before Phase 2 access.',
                'trade_count' => 12,
                'profit_bias' => 1.35,
            ],
            [
                'label' => 'Phase 2 active evaluation - approved from Phase 1',
                'name' => 'Prop Phase Two Active',
                'username' => 'prop_phase2_active',
                'email' => 'prop_phase2_active@propfirm.test',
                'prop_firm_phase' => 2,
                'funded_status' => 0,
                'review_status' => 'approved_phase2',
                'locked' => false,
                'review_note' => 'Phase 2 approved by administration.',
                'trade_count' => 9,
                'profit_bias' => 1.0,
            ],
            [
                'label' => 'Phase 2 passed - pending funded account approval',
                'name' => 'Prop Pending Funded',
                'username' => 'prop_pending_funded',
                'email' => 'prop_pending_funded@propfirm.test',
                'prop_firm_phase' => 2,
                'funded_status' => 0,
                'review_status' => 'pending_funded',
                'review_phase' => 2,
                'locked' => true,
                'review_note' => 'Phase 2 passed. Awaiting administration approval before funded account access.',
                'trade_count' => 10,
                'profit_bias' => 1.25,
                'questions' => [
                    [
                        'status' => PropFirmEvaluationQuestion::STATUS_ANSWERED,
                        'title' => 'Risk explanation requested',
                        'question' => 'Please explain the increased lot size used during the final evaluation day.',
                        'answer' => 'The position size stayed within the account risk plan because the stop distance was smaller than usual.',
                    ],
                ],
            ],
            [
                'label' => 'Funded account approved',
                'name' => 'Prop Funded Approved',
                'username' => 'prop_funded_approved',
                'email' => 'prop_funded_approved@propfirm.test',
                'prop_firm_phase' => 3,
                'funded_status' => 1,
                'review_status' => 'funded_approved',
                'locked' => false,
                'review_note' => 'Funded account approved by administration.',
                'trade_count' => 14,
                'profit_bias' => 1.1,
            ],
            [
                'label' => 'Funded trader suspended and locked',
                'name' => 'Prop Funded Suspended',
                'username' => 'prop_funded_suspended',
                'email' => 'prop_funded_suspended@propfirm.test',
                'prop_firm_phase' => 3,
                'funded_status' => 0,
                'review_status' => 'suspended',
                'locked' => true,
                'review_note' => 'Funded account suspended for suspicious activity review.',
                'trade_count' => 11,
                'profit_bias' => 0.6,
                'questions' => [
                    [
                        'status' => PropFirmEvaluationQuestion::STATUS_OPEN,
                        'title' => 'Suspicious funded account activity',
                        'question' => 'Please explain why multiple trades were opened within the same minute before a major news event.',
                    ],
                ],
            ],
            [
                'label' => 'Prop firm review rejected',
                'name' => 'Prop Review Rejected',
                'username' => 'prop_review_rejected',
                'email' => 'prop_review_rejected@propfirm.test',
                'prop_firm_phase' => 2,
                'funded_status' => 2,
                'review_status' => 'rejected',
                'locked' => true,
                'review_note' => 'Rejected by administration review due to rule breach.',
                'trade_count' => 7,
                'profit_bias' => -0.4,
            ],
            [
                'label' => 'Question required before review continues',
                'name' => 'Prop Question Required',
                'username' => 'prop_question_required',
                'email' => 'prop_question_required@propfirm.test',
                'prop_firm_phase' => 1,
                'funded_status' => 0,
                'review_status' => 'question_required',
                'locked' => true,
                'review_note' => 'Suspicious trading activity question requires trader response.',
                'trade_count' => 6,
                'profit_bias' => 0.3,
                'questions' => [
                    [
                        'status' => PropFirmEvaluationQuestion::STATUS_OPEN,
                        'title' => 'Phase 1 trading activity clarification',
                        'question' => 'Please explain the trade rationale and risk model for the cluster of XAUUSD entries.',
                    ],
                    [
                        'status' => PropFirmEvaluationQuestion::STATUS_RESOLVED,
                        'title' => 'Earlier evidence request',
                        'question' => 'Please provide the market screenshot for the initial entry.',
                        'answer' => 'Screenshot and reasoning were provided in the journal notes.',
                    ],
                ],
            ],
            [
                'label' => 'Professional high-risk review - daily loss breach with rapid escalation',
                'name' => 'Pro High Risk Review',
                'username' => 'pro_high_risk_review',
                'email' => 'pro_high_risk_review@propfirm.test',
                'expected_risk_category' => 'High Risk',
                'prop_firm_phase' => 1,
                'funded_status' => 0,
                'review_status' => 'daily_loss_review',
                'review_phase' => 1,
                'locked' => false,
                'review_requested' => true,
                'review_note' => 'Daily loss limit breached after rapid same-symbol re-entry and lot escalation. Admin should review first before allowing the evaluation to continue.',
                'capital' => 10000,
                'trade_profile' => 'professional_high_risk',
                'questions' => [
                    [
                        'status' => PropFirmEvaluationQuestion::STATUS_OPEN,
                        'title' => 'Daily loss breach explanation',
                        'question' => 'Please explain why XAUUSD was re-entered within minutes after a losing close and why lot size increased during the drawdown.',
                    ],
                ],
            ],
            [
                'label' => 'Professional high-risk profit-target review - target reached with sizing clarification',
                'name' => 'Pro Profit Target High Risk',
                'username' => 'pro_profit_target_high',
                'email' => 'pro_profit_target_high@propfirm.test',
                'expected_risk_category' => 'High Risk',
                'prop_firm_phase' => 1,
                'funded_status' => 0,
                'review_status' => self::REVIEW_PHASE1_PROFIT_TARGET,
                'review_phase' => 1,
                'locked' => true,
                'review_requested' => true,
                'review_note' => 'Phase 1 profit target reached. Even with mostly controlled behaviour, profit-target reviews are high priority for admin decision.',
                'capital' => 10000,
                'trade_profile' => 'professional_mid_risk',
                'questions' => [
                    [
                        'status' => PropFirmEvaluationQuestion::STATUS_ANSWERED,
                        'title' => 'Final-day position size clarification',
                        'question' => 'Please explain the larger EURUSD position on the final evaluation day and whether the stop distance was reduced.',
                        'answer' => 'The lot size increased because the planned invalidation was tighter than the earlier trades. The monetary risk was kept inside the evaluation rule.',
                    ],
                ],
            ],
            [
                'label' => 'Professional mid-risk review - passed evaluation but has active clarification',
                'name' => 'Pro Mid Risk Watchlist',
                'username' => 'pro_mid_risk_watchlist',
                'email' => 'pro_mid_risk_watchlist@propfirm.test',
                'expected_risk_category' => 'Mid Risk',
                'prop_firm_phase' => 2,
                'funded_status' => 0,
                'review_status' => 'pending_funded',
                'review_phase' => 2,
                'locked' => true,
                'review_requested' => true,
                'review_note' => 'Phase 2 passed, but an active clarification keeps this trader on the mid-risk watchlist before funded approval.',
                'capital' => 10000,
                'trade_profile' => 'professional_mid_risk',
                'questions' => [
                    [
                        'status' => PropFirmEvaluationQuestion::STATUS_OPEN,
                        'title' => 'Risk consistency clarification',
                        'question' => 'Please confirm whether the larger final-day position followed the same monetary risk cap as the earlier evaluation trades.',
                    ],
                ],
            ],
            [
                'label' => 'Professional low-risk pass - clean controlled Phase 1 review',
                'name' => 'Pro Low Risk Pass',
                'username' => 'pro_low_risk_pass',
                'email' => 'pro_low_risk_pass@propfirm.test',
                'expected_risk_category' => 'Low Risk',
                'prop_firm_phase' => 1,
                'funded_status' => 0,
                'review_status' => 'pending_phase2',
                'review_phase' => 1,
                'locked' => true,
                'review_requested' => true,
                'review_note' => 'Phase 1 passed with stable position size, no active questions, and no major behaviour warning. Low-priority admin review.',
                'capital' => 10000,
                'trade_profile' => 'professional_low_risk',
            ],
        ];
    }

    private function upsertTrader(array $scenario, string $password, int $sequence): User
    {
        $user = User::withTrashed()->firstOrNew(['email' => $scenario['email']]);

        if (method_exists($user, 'restore') && $user->trashed()) {
            $user->restore();
        }

        $user->fill([
            'name' => $scenario['name'],
            'username' => $scenario['username'],
            'password' => Hash::make($password),
            'status' => 1,
            'role_id' => 750,
            'prop_firm_phase' => $scenario['prop_firm_phase'],
            'funded_status' => $scenario['funded_status'],
            'prop_firm_review_status' => $scenario['review_status'],
            'prop_firm_review_phase' => $scenario['review_phase'] ?? null,
            'prop_firm_trade_locked' => $scenario['locked'],
            'prop_firm_review_note' => $scenario['review_note'] ?? null,
            'prop_firm_review_requested_at' => ($scenario['locked'] || ($scenario['review_requested'] ?? false)) ? now()->subHours(12 - $sequence) : null,
            'prop_firm_review_approved_at' => in_array($scenario['review_status'], ['approved_phase2', 'funded_approved'], true) ? now()->subDays(1) : null,
            'total_score' => $scenario['funded_status'] === 1 ? 92 : 76,
        ]);

        $user->email_verified_at = now();
        $user->referral_code = $user->referral_code ?: 'PROPTEST'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $user->customer_referral_code = $user->customer_referral_code ?: 'CUSTPROP'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        $user->signal_provider_referral_code = $user->signal_provider_referral_code ?: 'SIGPROP'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);

        if (Schema::hasColumn('users', 'phase2_start_balance')) {
            $user->phase2_start_balance = in_array($scenario['prop_firm_phase'], [2, 3], true) ? 11000 : null;
        }

        $user->save();

        return $user;
    }

    private function deleteGeneratedDataForUser(User $user): void
    {
        PropFirmEvaluationQuestion::where('user_id', $user->id)->delete();
        TradingJournal::where('user_id', $user->id)->where('notes', 'like', '%'.self::MARKER.'%')->delete();
        Capital::where('user_id', $user->id)->where('notes', 'like', '%'.self::MARKER.'%')->delete();
    }

    private function seedCapital(User $user, float $amount): void
    {
        Capital::create([
            'user_id' => $user->id,
            'type' => 1,
            'deposit_date' => Carbon::today()->subDays(45)->toDateString(),
            'amount' => $amount,
            'notes' => self::MARKER.' Prop firm test account capital.',
        ]);
    }

    private function seedScenarioTrades(User $user, array $scenario): void
    {
        if (! empty($scenario['trade_profile'])) {
            $this->seedProfessionalRiskTrades($user, (string) $scenario['trade_profile']);

            return;
        }

        $this->seedTrades($user, (int) ($scenario['trade_count'] ?? 10), (float) ($scenario['profit_bias'] ?? 1.0));
    }

    private function seedProfessionalRiskTrades(User $user, string $profile): void
    {
        $baseDate = Carbon::today()->subDays(14);

        $trades = match ($profile) {
            'professional_high_risk' => [
                ['day' => 0, 'time' => '09:10', 'duration' => 85, 'pair' => 'XAUUSD', 'direction' => 1, 'entry' => 2368.40, 'exit' => 2374.60, 'lot' => 0.20, 'pips' => 62, 'pl' => 124.00, 'result' => 1, 'note' => 'London open continuation setup, partial target reached inside plan.'],
                ['day' => 1, 'time' => '10:05', 'duration' => 110, 'pair' => 'EURUSD', 'direction' => 2, 'entry' => 1.08620, 'exit' => 1.08390, 'lot' => 0.18, 'pips' => 23, 'pl' => 41.40, 'result' => 1, 'note' => 'Planned pullback short after liquidity sweep.'],
                ['day' => 2, 'time' => '09:35', 'duration' => 95, 'pair' => 'XAUUSD', 'direction' => 1, 'entry' => 2371.10, 'exit' => 2367.50, 'lot' => 0.25, 'pips' => 36, 'pl' => -90.00, 'result' => 2, 'note' => 'Valid setup failed; loss accepted.'],
                ['day' => 4, 'time' => '14:00', 'duration' => 35, 'pair' => 'XAUUSD', 'direction' => 1, 'entry' => 2380.20, 'exit' => 2373.70, 'lot' => 0.40, 'pips' => 65, 'pl' => -260.00, 'result' => 2, 'note' => 'New York breakout failed; first daily loss event.'],
                ['day' => 4, 'time' => '14:43', 'duration' => 18, 'pair' => 'XAUUSD', 'direction' => 1, 'entry' => 2375.10, 'exit' => 2366.10, 'lot' => 0.60, 'pips' => 90, 'pl' => -540.00, 'result' => 2, 'note' => 'Re-entered same pair shortly after loss with larger lot.'],
                ['day' => 4, 'time' => '15:06', 'duration' => 16, 'pair' => 'XAUUSD', 'direction' => 2, 'entry' => 2365.80, 'exit' => 2374.30, 'lot' => 0.90, 'pips' => 85, 'pl' => -765.00, 'result' => 2, 'note' => 'Direction flipped during drawdown; daily loss limit breached.'],
                ['day' => 5, 'time' => '09:50', 'duration' => 70, 'pair' => 'EURUSD', 'direction' => 1, 'entry' => 1.08210, 'exit' => 1.08400, 'lot' => 0.18, 'pips' => 19, 'pl' => 34.20, 'result' => 1, 'note' => 'Reduced size after breach, but account remains under admin review.'],
            ],
            'professional_mid_risk' => [
                ['day' => 0, 'time' => '09:20', 'duration' => 120, 'pair' => 'EURUSD', 'direction' => 1, 'entry' => 1.08120, 'exit' => 1.08640, 'lot' => 0.25, 'pips' => 52, 'pl' => 130.00, 'result' => 1, 'note' => 'Trend continuation from London session.'],
                ['day' => 1, 'time' => '10:15', 'duration' => 80, 'pair' => 'GBPUSD', 'direction' => 2, 'entry' => 1.27420, 'exit' => 1.27010, 'lot' => 0.25, 'pips' => 41, 'pl' => 102.50, 'result' => 1, 'note' => 'Planned short from supply zone.'],
                ['day' => 2, 'time' => '09:40', 'duration' => 65, 'pair' => 'EURUSD', 'direction' => 1, 'entry' => 1.08800, 'exit' => 1.08520, 'lot' => 0.25, 'pips' => 28, 'pl' => -70.00, 'result' => 2, 'note' => 'Invalidation reached; loss taken without re-entry.'],
                ['day' => 3, 'time' => '15:30', 'duration' => 100, 'pair' => 'XAUUSD', 'direction' => 2, 'entry' => 2392.40, 'exit' => 2384.90, 'lot' => 0.20, 'pips' => 75, 'pl' => 150.00, 'result' => 1, 'note' => 'Gold rejection setup after US data settled.'],
                ['day' => 5, 'time' => '09:10', 'duration' => 140, 'pair' => 'EURUSD', 'direction' => 1, 'entry' => 1.08330, 'exit' => 1.09060, 'lot' => 0.35, 'pips' => 73, 'pl' => 255.50, 'result' => 1, 'note' => 'Final-day position used larger lot because stop distance was tighter; explanation requested.'],
                ['day' => 6, 'time' => '10:45', 'duration' => 75, 'pair' => 'GBPUSD', 'direction' => 1, 'entry' => 1.26780, 'exit' => 1.27230, 'lot' => 0.25, 'pips' => 45, 'pl' => 112.50, 'result' => 1, 'note' => 'Returned to baseline risk after clarification.'],
                ['day' => 8, 'time' => '11:05', 'duration' => 95, 'pair' => 'XAUUSD', 'direction' => 1, 'entry' => 2381.00, 'exit' => 2389.60, 'lot' => 0.20, 'pips' => 86, 'pl' => 172.00, 'result' => 1, 'note' => 'Controlled gold continuation trade.'],
                ['day' => 9, 'time' => '14:10', 'duration' => 50, 'pair' => 'EURUSD', 'direction' => 2, 'entry' => 1.09150, 'exit' => 1.08900, 'lot' => 0.25, 'pips' => 25, 'pl' => 62.50, 'result' => 1, 'note' => 'Small final scalp to complete target.'],
            ],
            default => [
                ['day' => 0, 'time' => '09:15', 'duration' => 105, 'pair' => 'EURUSD', 'direction' => 1, 'entry' => 1.08040, 'exit' => 1.08480, 'lot' => 0.20, 'pips' => 44, 'pl' => 88.00, 'result' => 1, 'note' => 'Clean London continuation, fixed risk.'],
                ['day' => 1, 'time' => '10:05', 'duration' => 90, 'pair' => 'GBPUSD', 'direction' => 2, 'entry' => 1.27600, 'exit' => 1.27270, 'lot' => 0.20, 'pips' => 33, 'pl' => 66.00, 'result' => 1, 'note' => 'Planned pullback sell with no re-entry.'],
                ['day' => 2, 'time' => '09:55', 'duration' => 70, 'pair' => 'EURUSD', 'direction' => 1, 'entry' => 1.08520, 'exit' => 1.08270, 'lot' => 0.20, 'pips' => 25, 'pl' => -50.00, 'result' => 2, 'note' => 'Routine loss; trader stopped for the day.'],
                ['day' => 4, 'time' => '09:25', 'duration' => 130, 'pair' => 'XAUUSD', 'direction' => 1, 'entry' => 2368.20, 'exit' => 2374.20, 'lot' => 0.12, 'pips' => 60, 'pl' => 72.00, 'result' => 1, 'note' => 'Gold continuation with smaller lot due wider stop.'],
                ['day' => 5, 'time' => '10:30', 'duration' => 85, 'pair' => 'GBPUSD', 'direction' => 1, 'entry' => 1.27100, 'exit' => 1.27520, 'lot' => 0.20, 'pips' => 42, 'pl' => 84.00, 'result' => 1, 'note' => 'Measured trend entry after retest.'],
                ['day' => 6, 'time' => '14:20', 'duration' => 95, 'pair' => 'EURUSD', 'direction' => 2, 'entry' => 1.09010, 'exit' => 1.08660, 'lot' => 0.20, 'pips' => 35, 'pl' => 70.00, 'result' => 1, 'note' => 'US session reversal, planned exit before news.'],
                ['day' => 8, 'time' => '09:45', 'duration' => 80, 'pair' => 'XAUUSD', 'direction' => 2, 'entry' => 2388.50, 'exit' => 2384.10, 'lot' => 0.12, 'pips' => 44, 'pl' => 52.80, 'result' => 1, 'note' => 'Reduced gold size, controlled risk.'],
                ['day' => 9, 'time' => '11:10', 'duration' => 65, 'pair' => 'EURUSD', 'direction' => 1, 'entry' => 1.08750, 'exit' => 1.09010, 'lot' => 0.20, 'pips' => 26, 'pl' => 52.00, 'result' => 1, 'note' => 'Small planned trade to finish target.'],
            ],
        };

        foreach ($trades as $index => $trade) {
            $open = $baseDate->copy()
                ->addDays((int) $trade['day'])
                ->setTimeFromTimeString($trade['time']);

            TradingJournal::create([
                'user_id' => $user->id,
                'type' => 'trade',
                'open_date' => $open->toDateTimeString(),
                'close_date' => $open->copy()->addMinutes((int) $trade['duration'])->toDateTimeString(),
                'pair' => $trade['pair'],
                'direction' => $trade['direction'],
                'entry_price' => $trade['entry'],
                'exit_price' => $trade['exit'],
                'lot_size' => $trade['lot'],
                'pips' => $trade['pips'],
                'profit_loss' => $trade['pl'],
                'result' => $trade['result'],
                'notes' => self::MARKER.' Professional risk sample: '.$trade['note'].' Trade #'.($index + 1).'.',
            ]);
        }
    }

    private function seedTrades(User $user, int $count, float $profitBias): void
    {
        $symbols = ['XAUUSD', 'BTCUSD'];
        $start = Carbon::today()->subDays(20)->setTime(9, 30);

        for ($index = 1; $index <= $count; $index++) {
            $isWin = $profitBias >= 1 ? $index % 4 !== 0 : ($profitBias >= 0 ? $index % 3 === 0 : $index % 4 === 0);
            $result = $isWin ? 1 : 2;
            $symbol = $symbols[($index - 1) % count($symbols)];
            $direction = $index % 2 === 0 ? 2 : 1;
            $entry = $symbol === 'BTCUSD' ? 68000 + ($index * 37) : 2380 + ($index * 1.4);
            $pips = $isWin ? 45 + ($index % 5) * 7 : 20 + ($index % 4) * 5;
            $lot = $symbol === 'BTCUSD' ? 0.01 : 0.03;
            $pipFactor = $symbol === 'BTCUSD' ? 10 : 0.1;
            $delta = $pips * $pipFactor;
            $exit = ($direction === 1) === $isWin ? $entry + $delta : $entry - $delta;
            $profitLoss = round($pips * $lot * 10 * ($isWin ? 1 : -1), 2);
            $open = $start->copy()->addDays((int) floor(($index - 1) / 2))->addMinutes(($index % 2) * 180);

            TradingJournal::create([
                'user_id' => $user->id,
                'type' => 'trade',
                'open_date' => $open->toDateTimeString(),
                'close_date' => $open->copy()->addHours(2)->toDateTimeString(),
                'pair' => $symbol,
                'direction' => $direction,
                'entry_price' => round($entry, 2),
                'exit_price' => round(max($exit, 0.01), 2),
                'lot_size' => $lot,
                'pips' => $pips,
                'profit_loss' => $profitLoss,
                'result' => $result,
                'notes' => self::MARKER.' '.$user->username.' generated workflow trade #'.$index.'.',
            ]);
        }
    }

    private function seedQuestions(User $user, array $questions): void
    {
        foreach ($questions as $index => $question) {
            $status = $question['status'];
            $answered = in_array($status, [
                PropFirmEvaluationQuestion::STATUS_ANSWERED,
                PropFirmEvaluationQuestion::STATUS_RESOLVED,
            ], true);

            PropFirmEvaluationQuestion::create([
                'user_id' => $user->id,
                'asked_by' => $this->adminId(),
                'phase' => $user->prop_firm_phase,
                'status' => $status,
                'title' => $question['title'],
                'question' => $question['question'],
                'answer' => $question['answer'] ?? null,
                'answered_at' => $answered ? now()->subHours(2 + $index) : null,
                'resolved_at' => $status === PropFirmEvaluationQuestion::STATUS_RESOLVED ? now()->subHour() : null,
            ]);
        }
    }

    private function adminId(): ?int
    {
        return User::whereIn('role_id', [1, 2])->orderBy('id')->value('id');
    }
}
