<?php

namespace App\Exports;

use App\Models\TradingJournal;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JournalSheet implements FromCollection, WithHeadings
{
    public function collection()
    {
        return TradingJournal::where('user_id', Auth::id())
            ->latest()
            ->get([
                'open_date',
                'close_date',
                'pair',
                'direction',
                'entry_price',
                'exit_price',
                'lot_size',
                'pips',
                'profit_loss',
                'result',
                'notes'
            ]);
    }

    public function headings(): array
    {
        return [
            'Open Date',
            'Close Date',
            'Pair',
            'Direction',
            'Entry Price',
            'Exit Price',
            'Lot Size',
            'Pips',
            'Profit/Loss',
            'Result',
            'Notes'
        ];
    }
}
