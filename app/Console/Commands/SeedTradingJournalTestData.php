<?php

namespace App\Console\Commands;

use App\Models\Capital;
use App\Models\TradingJournal;
use App\Models\TradingPair;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedTradingJournalTestData extends Command
{
    protected $signature = 'trading:seed-journal-test-data
        {user_id=1 : User ID that should receive the generated journal trades}
        {--count=100 : Number of trade orders to generate}
        {--capital=10000 : Test capital deposit amount. Use 0 to skip}
        {--fresh : Remove previously generated test rows for this user before creating new rows}
        {--seed=20260516 : Random seed for repeatable test data}';

    protected $description = 'Generate realistic trading journal test data for one user.';

    private const MARKER = '[TEST-SEED:TRADING-JOURNAL-100]';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $count = max(1, min(500, (int) $this->option('count')));
        $capital = max(0, (float) $this->option('capital'));
        $seed = (int) $this->option('seed');

        $user = User::find($userId);

        if (!$user) {
            $this->error("User ID {$userId} was not found.");

            return self::FAILURE;
        }

        if (!Schema::hasTable('trading_journals')) {
            $this->error('The trading_journals table does not exist.');

            return self::FAILURE;
        }

        $pairs = TradingPair::query()
            ->whereIn(DB::raw('UPPER(symbol)'), ['XAUUSD', 'BTCUSD'])
            ->get()
            ->keyBy(fn ($pair) => strtoupper($pair->symbol));

        if ($pairs->isEmpty()) {
            $pairs = TradingPair::query()->orderBy('symbol')->take(2)->get()->keyBy(fn ($pair) => strtoupper($pair->symbol));
        }

        if ($pairs->isEmpty()) {
            $this->error('No trading pairs found. Please create at least one trading pair first.');

            return self::FAILURE;
        }

        mt_srand($seed);

        DB::transaction(function () use ($userId, $count, $capital, $pairs) {
            if ($this->option('fresh')) {
                TradingJournal::query()
                    ->where('user_id', $userId)
                    ->where('notes', 'like', '%'.self::MARKER.'%')
                    ->delete();

                if (Schema::hasTable('capitals')) {
                    Capital::query()
                        ->where('user_id', $userId)
                        ->where('notes', 'like', '%'.self::MARKER.'%')
                        ->delete();
                }
            }

            if ($capital > 0 && Schema::hasTable('capitals')) {
                $existingSeedCapital = Capital::query()
                    ->where('user_id', $userId)
                    ->where('notes', 'like', '%'.self::MARKER.'%')
                    ->exists();

                if (!$existingSeedCapital) {
                    Capital::create([
                        'user_id' => $userId,
                        'type' => 1,
                        'deposit_date' => Carbon::today()->subDays(55)->toDateString(),
                        'amount' => $capital,
                        'notes' => self::MARKER.' Initial test capital for generated journal trades.',
                    ]);
                }
            }

            $startDate = Carbon::today()->subDays(49)->setTime(9, 0);
            $rows = [];
            $pairValues = $pairs->values();
            $lotSizes = [0.01, 0.02, 0.03, 0.05];

            for ($index = 1; $index <= $count; $index++) {
                $pair = $pairValues[($index - 1) % $pairValues->count()];
                $symbol = strtoupper($pair->symbol);
                $pipFactor = max((float) ($pair->pip_factor ?? 1), 0.00000001);
                $direction = $index % 2 === 0 ? 2 : 1;
                $result = $this->resolveResult($index);
                $pips = $this->resolvePips($result);
                $lotSize = $lotSizes[($index - 1) % count($lotSizes)];
                $entryPrice = $this->resolveEntryPrice($symbol);
                $exitPrice = $this->resolveExitPrice($entryPrice, $pips, $pipFactor, $direction, $result);
                $profitLoss = $this->resolveProfitLoss($pips, $lotSize, $result);
                $openDate = $startDate->copy()
                    ->addDays((int) floor(($index - 1) / 2))
                    ->addMinutes((($index - 1) % 2) * 210 + mt_rand(0, 35));
                $closeDate = $openDate->copy()->addMinutes(mt_rand(45, 360));

                $rows[] = [
                    'user_id' => $userId,
                    'type' => 'trade',
                    'open_date' => $openDate->toDateTimeString(),
                    'close_date' => $closeDate->toDateTimeString(),
                    'pair' => $pair->symbol,
                    'direction' => $direction,
                    'entry_price' => $entryPrice,
                    'exit_price' => $exitPrice,
                    'lot_size' => $lotSize,
                    'pips' => $pips,
                    'profit_loss' => $profitLoss,
                    'result' => $result,
                    'notes' => sprintf('%s Generated test trade #%03d for journal QA.', self::MARKER, $index),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 50) as $chunk) {
                TradingJournal::insert($chunk);
            }
        });

        $generatedCount = TradingJournal::query()
            ->where('user_id', $userId)
            ->where('notes', 'like', '%'.self::MARKER.'%')
            ->count();

        $this->info("Generated {$count} trading journal trade orders for user #{$userId} ({$user->username}).");
        $this->line("Total generated test trades now found for this user: {$generatedCount}.");

        return self::SUCCESS;
    }

    private function resolveResult(int $index): int
    {
        if ($index % 20 === 0) {
            return 3;
        }

        return in_array($index % 10, [1, 2, 3, 4, 5, 6], true) ? 1 : 2;
    }

    private function resolvePips(int $result): float
    {
        return match ($result) {
            1 => (float) mt_rand(24, 95),
            2 => (float) mt_rand(14, 58),
            default => 0.00,
        };
    }

    private function resolveEntryPrice(string $symbol): float
    {
        if (str_contains($symbol, 'BTC')) {
            return round(mt_rand(6200000, 7200000) / 100, 2);
        }

        if (str_contains($symbol, 'XAU') || str_contains($symbol, 'GOLD')) {
            return round(mt_rand(232000, 245000) / 100, 2);
        }

        if (str_contains($symbol, 'JPY')) {
            return round(mt_rand(14500, 15800) / 100, 3);
        }

        return round(mt_rand(10500, 11200) / 10000, 5);
    }

    private function resolveExitPrice(float $entryPrice, float $pips, float $pipFactor, int $direction, int $result): float
    {
        if ($result === 3) {
            return $entryPrice;
        }

        $delta = $pips * $pipFactor;
        $isBuy = $direction === 1;
        $isWin = $result === 1;
        $exitPrice = $isBuy === $isWin ? $entryPrice + $delta : $entryPrice - $delta;

        return round(max($exitPrice, 0.00001), 5);
    }

    private function resolveProfitLoss(float $pips, float $lotSize, int $result): float
    {
        $amount = round($pips * $lotSize * 10, 2);

        return match ($result) {
            1 => $amount,
            2 => -$amount,
            default => 0.00,
        };
    }
}
