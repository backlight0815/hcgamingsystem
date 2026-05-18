<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromArray;

class SignalPerformancesTemplateExport implements FromArray, WithHeadings, WithTitle
{
     public function array(): array
    {
        // Hardcoded example row
        return [
            [
                'pair'        => 'XAUUSD',
                'direction'   => 'buy',
                'entry_price' => '1950.50',
                'exit_price'  => '1960.00',
                'PNL' =>'123u',
                'open_date'   => '2026-02-15',
                'close_date'  => '2026-02-15',
                
            ],
        ];
    }


    public function headings(): array
    {
        return [
            'pair',        // Trading pair e.g., XAU/USD
            'direction',   // buy/sell
            'entry_price', // number
            'exit_price',  // number
            'PNL',
            'open_date',   // Y-m-d
            'close_date',  // Y-m-d
        ];
    }

    public function title(): string
    {
        return 'Signal Performance Template';
    }
}
