<?php

namespace App\Console\Commands;

use App\Models\TradingJournal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CleanupMt5JournalDuplicates extends Command
{
    protected $signature = 'trading-journal:cleanup-mt5-duplicates {--user_id= : Limit cleanup to one user id} {--dry-run : Show duplicate rows without deleting them}';

    protected $description = 'Remove duplicate MT5 journal rows that share the same order fingerprint.';

    public function handle(): int
    {
        $query = TradingJournal::query()
            ->where(function ($query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            })
            ->selectRaw('user_id, pair, direction, open_date, close_date, entry_price, exit_price, lot_size, COUNT(*) as duplicate_count')
            ->groupBy('user_id', 'pair', 'direction', 'open_date', 'close_date', 'entry_price', 'exit_price', 'lot_size')
            ->having('duplicate_count', '>', 1);

        if ($this->option('user_id')) {
            $query->where('user_id', (int) $this->option('user_id'));
        }

        $groups = $query->get();
        $deleted = 0;

        $deleted += $this->cleanupRoundedTimeDuplicates();

        foreach ($groups as $group) {
            $rows = TradingJournal::query()
                ->where('user_id', $group->user_id)
                ->where(function ($query): void {
                    $query->where('type', 'trade')->orWhereNull('type');
                })
                ->where('pair', $group->pair)
                ->where('direction', $group->direction)
                ->where('open_date', $group->open_date)
                ->where('close_date', $group->close_date)
                ->where('entry_price', $group->entry_price)
                ->where('exit_price', $group->exit_price)
                ->where('lot_size', $group->lot_size)
                ->orderByRaw("CASE WHEN notes LIKE '%MT5 Position #%' THEN 0 ELSE 1 END")
                ->orderByDesc('profit_loss')
                ->orderBy('id')
                ->get();

            if ($rows->count() <= 1) {
                continue;
            }

            $keep = $rows->first();
            $remove = $rows->slice(1);

            $this->line(sprintf(
                'Keep #%d, remove [%s] for user %s %s %s lot %s open %s',
                $keep->id,
                $remove->pluck('id')->implode(', '),
                $group->user_id,
                $group->pair,
                (string) $group->direction === '1' ? 'buy' : 'sell',
                $group->lot_size,
                $group->open_date
            ));

            if (! $this->option('dry-run')) {
                $deleted += $this->deleteRows($remove);
            }
        }

        $this->info($this->option('dry-run')
            ? 'Dry run complete. No rows were deleted.'
            : $deleted.' duplicate journal row(s) deleted.'
        );

        return self::SUCCESS;
    }

    private function deleteRows(Collection $rows): int
    {
        $rows->each(function ($row): void {
            TradingJournal::find($row->id)?->delete();
        });

        return $rows->count();
    }

    private function cleanupRoundedTimeDuplicates(): int
    {
        $query = TradingJournal::query()
            ->where(function ($query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            })
            ->where('notes', 'like', '%MT5 Position #%')
            ->orderBy('id');

        if ($this->option('user_id')) {
            $query->where('user_id', (int) $this->option('user_id'));
        }

        $deleted = 0;

        foreach ($query->get() as $mt5Row) {
            $duplicates = TradingJournal::query()
                ->where('id', '<>', $mt5Row->id)
                ->where('user_id', $mt5Row->user_id)
                ->where(function ($query): void {
                    $query->where('type', 'trade')->orWhereNull('type');
                })
                ->where(function ($query): void {
                    $query->whereNull('notes')->orWhere('notes', 'not like', '%MT5 Position #%');
                })
                ->where('pair', $mt5Row->pair)
                ->where('direction', $mt5Row->direction)
                ->whereBetween('open_date', $this->dateToleranceWindow((string) $mt5Row->open_date))
                ->whereBetween('close_date', $this->dateToleranceWindow((string) $mt5Row->close_date))
                ->where('entry_price', $mt5Row->entry_price)
                ->where('exit_price', $mt5Row->exit_price)
                ->where('lot_size', $mt5Row->lot_size)
                ->get(['id']);

            if ($duplicates->isEmpty()) {
                continue;
            }

            $this->line(sprintf(
                'Keep MT5 #%d, remove rounded duplicate(s) [%s] for user %s %s open %s',
                $mt5Row->id,
                $duplicates->pluck('id')->implode(', '),
                $mt5Row->user_id,
                $mt5Row->pair,
                $mt5Row->open_date
            ));

            if (! $this->option('dry-run')) {
                $deleted += $this->deleteRows($duplicates);
            }
        }

        return $deleted;
    }

    private function dateToleranceWindow(string $date, int $seconds = 60): array
    {
        $date = Carbon::parse($date);

        return [
            $date->copy()->subSeconds($seconds)->format('Y-m-d H:i:s'),
            $date->copy()->addSeconds($seconds)->format('Y-m-d H:i:s'),
        ];
    }
}
