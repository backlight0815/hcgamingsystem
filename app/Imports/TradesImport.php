<?php

namespace App\Imports;

use App\Models\TradingJournal;
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
            'open_date'   => $row['open_date'],
            'close_date'  => $row['close_date'],
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