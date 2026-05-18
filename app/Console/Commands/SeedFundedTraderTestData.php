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
use Illuminate\Support\Str;

class SeedFundedTraderTestData extends Command
{
    protected $signature = 'trading:seed-funded-trader-test-data
        {--password=password : Password assigned to generated trader accounts}
        {--fresh : Delete existing generated funded trader test accounts before recreating them}';

    protected $description = 'Generate prop firm funded trader workflow test accounts, reviews, locks, and evaluation questions.';

    private const MARKER = '[TEST-SEED:FUNDED-TRADER-WORKFLOW]';

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
                $this->seedTrades($user, (int) ($scenario['trade_count'] ?? 10), (float) ($scenario['profit_bias'] ?? 1.0));
                $this->seedQuestions($user, $scenario['questions'] ?? []);
            }
        });

        $this->info('Generated funded trader workflow test data.');
        $this->line('Password for all generated traders: '.$password);
        $this->table(
            ['Username', 'Email', 'Scenario'],
            collect($scenarios)->map(fn ($scenario) => [
                $scenario['username'],
                $scenario['email'],
                $scenario['label'],
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
            'prop_firm_review_requested_at' => $scenario['locked'] ? now()->subHours(12 - $sequence) : null,
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
