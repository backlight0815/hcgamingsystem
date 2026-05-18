<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;

class TradesTemplateExport implements FromArray, WithHeadings
{
    use Exportable;

    /**
     * Provide empty array for template rows
     */
    public function array(): array
    {
        // Empty row to show as example (optional)
        return [
            [
                '2025-08-31 10:00', // open_date
                '2025-08-31 12:00', // close_date
                'XAUUSD',           // pair
                '1',                // direction 1=Buy, 2=Sell
                '1950.00',          // entry_price
                '1945.00',          // stop_loss
                '1960.00',          // take_profit
                '1960.00',          // exit_price
                '0.10',             // lot_size
                '100',              // pips
                '1000',             // profit_loss
                '1',                // result 1=Win, 2=Loss, 3=Break Even
                'Sample trade'      // notes
            ]
        ];
    }

    /**
     * Headings for the template
     */
    public function headings(): array
    {
        return [
            'open_date',
            'close_date',
            'pair',
            'direction',
            'entry_price',
            'stop_loss',
            'take_profit',
            'exit_price',
            'lot_size',
            'pips',
            'profit_loss',
            'result',
            'notes',
        ];
    }
}
