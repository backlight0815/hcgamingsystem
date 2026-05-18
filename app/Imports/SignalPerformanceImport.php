<?php

namespace App\Imports;

use App\Models\SignalPerformance;
use App\Models\TradingSignal;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Carbon;

class SignalPerformanceImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    protected $userId;

    public function __construct($userId = null)
    {
        $this->userId = $userId; // optional: assign provider/user
    }

    public function model(array $row)
    {
        // Ensure required columns exist
        if (empty($row['pair']) || empty($row['direction']) || !isset($row['entry_price']) || !isset($row['close_date'])) {
            return null;
        }

        $entry = (float) ($row['entry_price']);
        $exit  = (float) ($row['exit_price'] ?? $entry); // fallback exit = entry
        $direction = strtolower($row['direction']);

        // Calculate pips
        $pips = ($direction === 'buy') ? ($exit - $entry) * 10 : ($entry - $exit) * 10;

        // Find related trading signal
        $signalQuery = TradingSignal::where('trading_pair', $row['pair'])
            ->where('immediate_action', strtoupper($row['direction']));

        if ($this->userId) {
            $signalQuery->where('user_id', $this->userId);
        }

        if (!empty($row['open_date'])) {
            $signalQuery->whereDate('created_at', Carbon::parse($row['open_date']));
        }

        $signal = $signalQuery->first();

        if (!$signal) {
            return null; // skip row if signal not found
        }

        // TP hit & SL
        $tpHit = isset($row['tp_hit']) ? (int)$row['tp_hit'] : 0;
        $isSL  = isset($row['is_sl']) ? (bool)$row['is_sl'] : false;

        return new SignalPerformance([
            'signal_id'   => $signal->id,
            'profit_pips' => $pips,
            'tp_hit'      => $tpHit,
            'is_sl'       => $isSL,
            'created_at'  => Carbon::parse($row['close_date']),
        ]);
    }
}
