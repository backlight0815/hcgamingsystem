<?php

namespace App\Console\Commands;

use App\Models\Community;
use App\Models\TradingPair;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedTradingSignalTestData extends Command
{
    protected $signature = 'trading:seed-signal-test-data
        {--count=100 : Number of trading signals to generate}
        {--months=3 : Number of months to spread generated signals across}
        {--fresh : Delete existing trading signal related data before generating}';

    protected $description = 'Reset and generate trading signal test data for dashboards and reports.';

    private const USD_PER_PIP_PER_001_LOT = 0.10;

    public function handle(): int
    {
        $count = max(1, min(1000, (int) $this->option('count')));
        $months = max(1, min(24, (int) $this->option('months')));

        if (!Schema::hasTable('trading_signals')) {
            $this->error('The trading_signals table does not exist.');

            return self::FAILURE;
        }

        $providers = User::query()
            ->whereIn('role_id', [201, 202])
            ->orderBy('id')
            ->get();

        if ($providers->isEmpty()) {
            $providers = User::query()->whereIn('role_id', [1, 2])->orderBy('id')->get();
        }

        if ($providers->isEmpty()) {
            $providers = User::query()->orderBy('id')->take(1)->get();
        }

        if ($providers->isEmpty()) {
            $this->error('No users found. Create at least one user before generating trading signals.');

            return self::FAILURE;
        }

        $communities = Community::query()->orderBy('id')->get();
        $pairs = $this->loadTradingPairs();
        $reasonIds = Schema::hasTable('trading_reason')
            ? DB::table('trading_reason')->orderBy('id')->pluck('id')->values()
            : collect();

        DB::transaction(function () use ($count, $months, $providers, $communities, $pairs, $reasonIds) {
            if ($this->option('fresh')) {
                $this->deleteExistingSignalData();
            }

            $start = Carbon::today()->subMonths($months)->startOfDay();
            $end = Carbon::today()->endOfDay();
            $spanSeconds = max(1, $start->diffInSeconds($end));
            $now = now();

            for ($index = 1; $index <= $count; $index++) {
                $pair = $pairs[($index - 1) % count($pairs)];
                $provider = $providers[($index - 1) % $providers->count()];
                $community = $communities->isNotEmpty() ? $communities[($index - 1) % $communities->count()] : null;
                $createdAt = $start->copy()->addSeconds((int) floor(($spanSeconds / $count) * ($index - 1)));
                $createdAt->addMinutes(($index * 17) % 360);
                $status = $this->statusForIndex($index);
                $direction = $index % 2 === 0 ? 'Sell' : 'Buy';
                $entry = $this->entryPrice($pair['symbol']);
                $setup = $this->priceSetup($entry, $pair['pip_factor'], $direction);
                $closedPrice = $this->closedPrice($setup, $status, $direction);
                $pipsResult = $this->pipsResult($status, $setup);
                $profitLoss = round($pipsResult * self::USD_PER_PIP_PER_001_LOT, 2);
                $signalCode = sprintf('HC-SIG-%s-%04d', $createdAt->format('ymd'), $index);
                $isDone = $status === 14;
                $isBE = $status === 15;
                $isSetBE = $isBE || ($index % 9 === 0 && in_array($status, range(1, 11), true));
                $triggerTime = $status > 0 ? $createdAt->copy()->addMinutes(15)->toDateTimeString() : null;
                $tradingReasons = $this->pickReasonIds($reasonIds, $index);

                $signalId = DB::table('trading_signals')->insertGetId($this->filterColumns('trading_signals', [
                    'community_id' => $community?->id,
                    'signal_code' => $signalCode,
                    'user_id' => $provider->id,
                    'category' => $this->categoryForIndex($index),
                    'trading_reasons' => $tradingReasons ? json_encode($tradingReasons) : null,
                    'discord_message_id' => 'test-msg-'.$index,
                    'discord_channel_id' => 'test-channel-'.$community?->id,
                    'status' => $status,
                    'pips_result' => $pipsResult,
                    'trading_pair' => $pair['symbol'],
                    'immediate_action' => $direction,
                    'entry_price' => $entry,
                    'stop_loss' => $setup['stop_loss'],
                    'target_1' => $setup['targets'][0],
                    'target_2' => $setup['targets'][1],
                    'target_3' => $setup['targets'][2],
                    'target_4' => $setup['targets'][3],
                    'target_5' => $setup['targets'][4],
                    'target_6' => $setup['targets'][5],
                    'target_7' => $setup['targets'][6],
                    'target_8' => $setup['targets'][7],
                    'target_9' => $setup['targets'][8],
                    'target_10' => $setup['targets'][9],
                    'closed_price' => $closedPrice,
                    'IsDone' => $isDone ? 1 : 0,
                    'IsSetBE' => $isSetBE ? 1 : 0,
                    'IsBE' => $isBE ? 1 : 0,
                    'cancel_reason' => $status === 12 ? 'Test cancellation: invalidated before activation.' : null,
                    'profit_loss' => $profitLoss,
                    'rr_ratio' => $this->rrRatio($status),
                    'performance_status' => $this->performanceStatus($status),
                    'disclaimer' => 'Generated test signal for QA. Please do not use this as a live trading instruction.',
                    'risk_level' => $this->riskLevel($index),
                    'signal_image' => null,
                    'link' => null,
                    'trigger_time' => $triggerTime,
                    'created_at' => $createdAt->toDateTimeString(),
                    'updated_at' => $createdAt->copy()->addHours(2)->toDateTimeString(),
                ]));

                $backupId = null;
                if (Schema::hasTable('trading_signals_backup')) {
                    $backupId = DB::table('trading_signals_backup')->insertGetId($this->filterColumns('trading_signals_backup', [
                        'community_id' => $community?->id,
                        'signal_code' => $signalCode,
                        'trading_pair' => $pair['symbol'],
                        'immediate_action' => $direction,
                        'entry_price' => $entry,
                        'stop_loss' => $setup['stop_loss'],
                        'target_1' => $setup['targets'][0],
                        'target_2' => $setup['targets'][1],
                        'target_3' => $setup['targets'][2],
                        'target_4' => $setup['targets'][3],
                        'target_5' => $setup['targets'][4],
                        'target_6' => $setup['targets'][5],
                        'target_7' => $setup['targets'][6],
                        'target_8' => $setup['targets'][7],
                        'target_9' => $setup['targets'][8],
                        'target_10' => $setup['targets'][9],
                        'disclaimer' => 'Generated test signal for QA. Please do not use this as a live trading instruction.',
                        'risk_level' => $this->riskLevel($index),
                        'signal_image' => null,
                        'link' => null,
                        'trigger_time' => $triggerTime,
                        'status' => $status,
                        'IsDone' => $isDone ? 1 : 0,
                        'IsBE' => $isBE ? 1 : 0,
                        'cancel_reason' => $status === 12 ? 'Test cancellation: invalidated before activation.' : null,
                        'IsSetBE' => $isSetBE ? 1 : 0,
                        'user_id' => $provider->id,
                        'created_at' => $createdAt->toDateTimeString(),
                        'updated_at' => $createdAt->copy()->addHours(2)->toDateTimeString(),
                    ]));
                }

                if (Schema::hasTable('trading_signal_discord') && $community) {
                    DB::table('trading_signal_discord')->insert($this->filterColumns('trading_signal_discord', [
                        'trading_signal_id' => $signalId,
                        'community_id' => $community->id,
                        'category' => $this->categoryForIndex($index),
                        'community' => $community->name,
                        'message_id' => 'test-msg-'.$index,
                        'channel_id' => 'test-channel-'.$community->id,
                        'created_at' => $createdAt->toDateTimeString(),
                        'updated_at' => $createdAt->toDateTimeString(),
                    ]));
                }

                if ($this->shouldCreatePerformance($status)) {
                    $this->insertPerformanceRow('signal_performances', $signalId, $status, $pipsResult, $profitLoss, $createdAt);

                    if ($backupId) {
                        $this->insertPerformanceRow('signal_performances_backup', $backupId, $status, $pipsResult, $profitLoss, $createdAt);
                    }
                }
            }
        });

        $summary = DB::table('trading_signals')
            ->selectRaw('count(*) as total, min(created_at) as first_signal, max(created_at) as last_signal')
            ->first();

        $this->info("Generated {$summary->total} trading signals across {$months} month(s).");
        $this->line("Date range: {$summary->first_signal} to {$summary->last_signal}.");

        return self::SUCCESS;
    }

    private function deleteExistingSignalData(): void
    {
        foreach ([
            'signal_performances',
            'signal_performances_backup',
            'trading_signal_discord',
            'trading_signals_backup',
            'trading_signals',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }
    }

    private function loadTradingPairs(): array
    {
        $pairs = TradingPair::query()
            ->whereIn(DB::raw('UPPER(symbol)'), ['XAUUSD', 'BTCUSD'])
            ->orderBy('symbol')
            ->get(['symbol', 'pip_factor'])
            ->map(fn ($pair) => [
                'symbol' => strtoupper($pair->symbol),
                'pip_factor' => max((float) ($pair->pip_factor ?? 1), 0.00000001),
            ])
            ->values()
            ->all();

        return $pairs ?: [
            ['symbol' => 'XAUUSD', 'pip_factor' => 0.1],
            ['symbol' => 'BTCUSD', 'pip_factor' => 10],
        ];
    }

    private function filterColumns(string $table, array $data): array
    {
        $columns = array_flip(Schema::getColumnListing($table));

        return array_intersect_key($data, $columns);
    }

    private function statusForIndex(int $index): int
    {
        $cycle = [0, 1, 2, 3, 4, 5, 12, 13, 14, 15, 6, 7, 8, 9, 10, 11];

        return $cycle[($index - 1) % count($cycle)];
    }

    private function categoryForIndex(int $index): string
    {
        $categories = ['forex', 'gold', 'crypto', 'indices'];

        return $categories[($index - 1) % count($categories)];
    }

    private function riskLevel(int $index): string
    {
        return ['Low', 'Medium', 'High'][($index - 1) % 3];
    }

    private function entryPrice(string $symbol): float
    {
        if (str_contains($symbol, 'BTC')) {
            return round(mt_rand(6200000, 7200000) / 100, 2);
        }

        if (str_contains($symbol, 'XAU') || str_contains($symbol, 'GOLD')) {
            return round(mt_rand(232000, 245000) / 100, 2);
        }

        return round(mt_rand(10500, 11200) / 10000, 5);
    }

    private function priceSetup(float $entry, float $pipFactor, string $direction): array
    {
        $isBuy = strtolower($direction) === 'buy';
        $stopDistance = 38 * $pipFactor;
        $stopLoss = $isBuy ? $entry - $stopDistance : $entry + $stopDistance;
        $targets = [];

        for ($level = 1; $level <= 10; $level++) {
            $distance = (22 + ($level * 13)) * $pipFactor;
            $targets[] = round($isBuy ? $entry + $distance : $entry - $distance, 5);
        }

        return [
            'stop_loss' => round(max($stopLoss, 0.00001), 5),
            'targets' => $targets,
        ];
    }

    private function closedPrice(array $setup, int $status, string $direction): ?float
    {
        if ($status >= 2 && $status <= 11) {
            return $setup['targets'][$status - 2] ?? null;
        }

        if ($status === 13) {
            return $setup['stop_loss'];
        }

        if ($status === 15) {
            return null;
        }

        return null;
    }

    private function pipsResult(int $status, array $setup): float
    {
        if ($status >= 2 && $status <= 11) {
            return (float) (22 + (($status - 1) * 13));
        }

        if ($status === 13) {
            return -38.0;
        }

        if ($status === 14) {
            return 96.0;
        }

        return 0.0;
    }

    private function rrRatio(int $status): ?float
    {
        if ($status >= 2 && $status <= 11) {
            return round((22 + (($status - 1) * 13)) / 38, 2);
        }

        if ($status === 14) {
            return 2.5;
        }

        if ($status === 13) {
            return -1.0;
        }

        return null;
    }

    private function performanceStatus(int $status): string
    {
        return match (true) {
            $status === 0 => 'pending',
            $status === 1 => 'active',
            $status >= 2 && $status <= 11 => 'take_profit',
            $status === 12 => 'cancelled',
            $status === 13 => 'stop_loss',
            $status === 14 => 'done',
            $status === 15 => 'breakeven',
            default => 'unknown',
        };
    }

    private function shouldCreatePerformance(int $status): bool
    {
        return $status >= 2 && $status <= 15;
    }

    private function insertPerformanceRow(string $table, int $signalId, int $status, float $pips, float $usd, Carbon $createdAt): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->insert($this->filterColumns($table, [
            'signal_id' => $signalId,
            'tp_hit' => $status >= 2 && $status <= 11 ? $status - 1 : null,
            'is_sl' => $status === 13 ? 1 : 0,
            'is_cancelled' => $status === 12 ? 1 : 0,
            'profit_pips' => $pips,
            'profit_usd' => $usd,
            'created_at' => $createdAt->copy()->addHours(2)->toDateTimeString(),
            'updated_at' => $createdAt->copy()->addHours(2)->toDateTimeString(),
        ]));
    }

    private function pickReasonIds($reasonIds, int $index): array
    {
        if ($reasonIds->isEmpty()) {
            return [];
        }

        $first = $reasonIds[($index - 1) % $reasonIds->count()];
        $second = $reasonIds[$index % $reasonIds->count()];

        return array_values(array_unique([$first, $second]));
    }
}
