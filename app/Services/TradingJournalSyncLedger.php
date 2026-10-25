<?php

namespace App\Services;

use App\Models\TradingJournal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TradingJournalSyncLedger
{
    public function record(TradingJournal $journal, string $operation): void
    {
        if (! Schema::hasTable('trading_journal_sync_records')) {
            return;
        }

        $row = DB::table('trading_journal_sync_records')
            ->where('journal_id', $journal->id)->lockForUpdate()->first();
        $now = now();
        if ($row) {
            $revision = (int) $row->revision + 1;
            DB::table('trading_journal_sync_records')->where('id', $row->id)->update([
                'revision' => $revision,
                'deleted_at' => $operation === 'delete' ? $now : null,
                'updated_at' => $now,
            ]);
            $syncId = $row->id;
        } else {
            $revision = 1;
            $syncId = DB::table('trading_journal_sync_records')->insertGetId([
                'user_id' => $journal->user_id,
                'journal_id' => $journal->id,
                'revision' => $revision,
                'deleted_at' => $operation === 'delete' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('trading_journal_sync_events')->insert([
            'user_id' => $journal->user_id,
            'sync_record_id' => $syncId,
            'operation' => $operation,
            'source_platform' => request()->is('api/mobile/v1/journal-sync*') ? 'mobile'
                : (app()->runningInConsole() ? 'system' : 'website'),
            'status' => 'applied',
            'revision' => $revision,
            'created_at' => $now,
        ]);
    }

    public function ensureLegacyRows(int $userId): void
    {
        TradingJournal::query()->where('user_id', $userId)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('trading_journal_sync_records as sync')
                    ->whereColumn('sync.journal_id', 'trading_journals.id');
            })
            ->orderBy('id')->chunkById(200, function ($journals): void {
                foreach ($journals as $journal) {
                    $this->record($journal, 'create');
                }
            });
    }
}
