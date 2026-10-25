<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Imports\TradesImport;
use App\Models\TradingAccount;
use App\Models\TradingJournal;
use App\Services\TradingJournalTimeService;
use App\Services\TradingRiskAlertService;
use App\Services\XlsxRowsReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class JournalImportController extends MobileController
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isTradingMember(), 403);
        abort_if((bool) ($request->user()->prop_firm_trade_locked ?? false), 423, 'Trading activity is locked pending administration review.');
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
            'time_input_timezone' => ['nullable', 'in:malaysia,mt5'],
            'time_input_offset_minutes' => ['nullable', 'integer', 'in:120,180'],
            'trading_account_id' => ['nullable', 'integer'],
        ]);
        $account = null;
        if (! empty($data['trading_account_id'])) {
            $account = TradingAccount::where('user_id', $request->user()->id)->findOrFail($data['trading_account_id']);
        }

        $latestId = (int) (TradingJournal::where('user_id', $request->user()->id)->max('id') ?? 0);
        $import = new TradesImport(
            $request->user()->id,
            $data['time_input_timezone'] ?? TradingJournalTimeService::TIMEZONE_MT5,
            $data['time_input_offset_minutes'] ?? 180
        );

        try {
            $file = $data['file'];
            if (strtolower((string) $file->getClientOriginalExtension()) === 'xlsx') {
                foreach (app(XlsxRowsReader::class)->sheets($file->getRealPath()) as $rows) {
                    if ($rows) {
                        $import->collection(collect($rows));
                    }
                }
            } else {
                Excel::import($import, $file);
            }
        } catch (\Throwable $error) {
            return $this->failure('Import failed. Confirm the file uses the HC template or MT5 closed-position format.', [
                'file' => [$error->getMessage()],
            ], 422);
        }

        $imported = TradingJournal::where('user_id', $request->user()->id)->where('id', '>', $latestId)->get();
        if ($account && $imported->isNotEmpty()) {
            $imported->each(function (TradingJournal $journal) use ($account): void {
                $journal->update(['trading_account_id' => $account->id]);
            });
            $imported = TradingJournal::whereKey($imported->modelKeys())->get();
        }
        if ($imported->isNotEmpty()) {
            app(TradingRiskAlertService::class)->evaluateAndNotify($request->user(), $imported, 'mobile_trade_import');
        }

        return $this->success([
            'imported' => $import->importedCount(),
            'updated' => $import->updatedCount(),
            'deduplicated' => $import->deduplicatedCount(),
            'skipped' => $import->skippedCount(),
            'skipped_rows' => $import->skippedRows(),
        ], $import->persistedCount() > 0 ? 'Journal import completed.' : 'No new trades were imported.');
    }
}
