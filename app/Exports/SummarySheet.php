<?php

namespace App\Exports;

use App\Models\TradingJournal;
use App\Models\Capital;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class SummarySheet implements FromArray, WithTitle
{
    public function array(): array
    {
        $userId = Auth::id();
        $journals = TradingJournal::where('user_id', $userId)->latest()->get();
        $totalCapital = Capital::where('user_id', $userId)->sum('amount');
        $netPL = $journals->sum('profit_loss');
        $totalBalance = $totalCapital + $netPL;

        $winTrades = $journals->where('profit_loss', '>', 0);
        $lossTrades = $journals->where('profit_loss', '<', 0);
        $breakevenTrades = $journals->where('profit_loss', '=', 0);

        $totalTrades = $journals->count();
        $totalWithoutBE = $winTrades->count() + $lossTrades->count();
        $winRate = $totalWithoutBE > 0 ? round(($winTrades->count() / $totalWithoutBE) * 100, 2) : 0;

        $totalProfit = $winTrades->sum('profit_loss');
        $totalLoss = abs($lossTrades->sum('profit_loss'));
        $averageRRR = ($totalLoss > 0 && $totalProfit > 0)
            ? round($totalProfit / $totalLoss, 2)
            : 'N/A';

        $growthPercent = $totalCapital > 0 ? round(($netPL / $totalCapital) * 100, 2) : 0;

        $drawdownPercent = ($netPL < 0 && $totalCapital > 0)
            ? round((abs($netPL) / $totalCapital) * 100, 2)
            : 0;

        $averageWin = $winTrades->count() > 0 ? $winTrades->avg('profit_loss') : 0;
        $averageLoss = $lossTrades->count() > 0 ? abs($lossTrades->avg('profit_loss')) : 0;
        $winRateDecimal = $totalTrades > 0 ? $winTrades->count() / $totalTrades : 0;
        $lossRateDecimal = $totalTrades > 0 ? $lossTrades->count() / $totalTrades : 0;

        $expectancy = round(($winRateDecimal * $averageWin) - ($lossRateDecimal * $averageLoss), 2);

        $profitLosses = $journals->pluck('profit_loss')->toArray();
        $avgPL = $totalTrades > 0 ? array_sum($profitLosses) / $totalTrades : 0;

        $variance = $totalTrades > 1 ? array_sum(array_map(
            fn($pl) => pow($pl - $avgPL, 2),
            $profitLosses
        )) / ($totalTrades - 1) : 0;

        $stdDeviation = round(sqrt($variance), 2);

        return [
            ['Performance Summary'],
            ['Total Capital', $totalCapital],
            ['Net Profit/Loss', $netPL],
            ['Total Balance', $totalBalance],
            ['Total Trades', $totalTrades],
            ['Winning Trades', $winTrades->count()],
            ['Losing Trades', $lossTrades->count()],
            ['Breakeven Trades', $breakevenTrades->count()],
            ['Win Rate (%)', $winRate],
            ['Total Profit', $totalProfit],
            ['Total Loss', $totalLoss],
            ['Average RRR', $averageRRR],
            ['Growth (%)', $growthPercent],
            ['Drawdown (%)', $drawdownPercent],
            ['Expectancy', $expectancy],
            ['Consistency (Std Dev)', $stdDeviation],
        ];
    }

    public function title(): string
    {
        return 'Performance Summary';
    }
}
