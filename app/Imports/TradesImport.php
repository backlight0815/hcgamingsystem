<?php

namespace App\Imports;

use App\Models\TradingJournal;
use App\Services\TradingJournalTimeService;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class TradesImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{

        protected $userId;

    // Pass the user ID when instantiating the import
    public function __construct($userId)
    {
        $this->userId = $userId;
    }


    public function model(array $row)
    {
        $entry = $row['entry_price'] ?? 0;
        $exit  = $row['exit_price'] ?? 0;
        $lot   = $row['lot_size'] ?? 0;
        $resultType = $row['result'] ?? null; // 1=Win, 2=Loss, 3=Break Even
        $timeService = app(TradingJournalTimeService::class);
        $timeInputTimezone = $timeService->normalizeMode($row['time_input_timezone'] ?? null);
        $timeInputOffsetMinutes = $timeService->normalizeOffset(
            $row['time_input_offset_minutes'] ?? $row['mt5_offset_minutes'] ?? null,
            $timeInputTimezone
        );

        // Calculate pips (absolute difference * 10 for XAUUSD example)
        $pips = abs($exit - $entry) * 10;

        // Calculate profit/loss
        $profit = $pips * $lot * 10; // same formula as your JS
        if ($resultType== 2) {       // Loss
            $profit = -abs($profit);
        } elseif ($resultType == 1) { // Win
            $profit = abs($profit);
        } else {                       // Break Even
            $profit = 0;
        }

        return new TradingJournal([
            'user_id'     => $this->userId,
            'time_input_timezone' => $timeInputTimezone,
            'time_input_offset_minutes' => $timeInputOffsetMinutes,
            'open_date'   => $timeService->toMalaysiaDatabase($row['open_date'] ?? null, $timeInputTimezone, $timeInputOffsetMinutes),
            'close_date'  => $timeService->toMalaysiaDatabase($row['close_date'] ?? null, $timeInputTimezone, $timeInputOffsetMinutes),
            'pair'        => $row['pair'],
            'direction'   => $row['direction'],
            'entry_price' => $entry,
            'exit_price'  => $exit,
            'lot_size'    => $lot,
            'pips'        => $pips,
            'profit_loss' => $profit,
            'result'      => $resultType,
            'notes'       => $row['notes'] ?? null,
        ]);
    }
}
