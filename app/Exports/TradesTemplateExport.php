<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

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
                'closed',            // status: draft, open, closed
                'manual_close',      // close_handling: manual_close or follow_pre_set
                'malaysia',          // time_input_timezone: malaysia or mt5
                '180',               // time_input_offset_minutes: 180=UTC+3, 120=UTC+2 (used when timezone is mt5)
                'XAUUSD',           // pair
                '',                  // trading_reason_id
                '1',                // direction 1=Buy, 2=Sell
                '1950.00',          // entry_price
                '1945.00',          // stop_loss
                '1960.00',          // take_profit
                '1960.00',          // exit_price
                '0.10',             // lot_size
                '100',              // pips
                '1000',             // profit_loss
                '1',                // result 1=Win, 2=Loss, 3=Break Even
                'Sample trade',     // notes
                'Confident',        // emotion_before_entry
                'Focused',          // emotion_during_trade
                'Calm',             // emotion_after_trade
                '8',                // confidence_level
                '9',                // discipline_rating
                '',                 // mistake_tags
                'Followed the setup.', // psychology_notes
            ],
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
            'status',
            'close_handling',
            'time_input_timezone',
            'time_input_offset_minutes',
            'pair',
            'trading_reason_id',
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
            'emotion_before_entry',
            'emotion_during_trade',
            'emotion_after_trade',
            'confidence_level',
            'discipline_rating',
            'mistake_tags',
            'psychology_notes',
        ];
    }
}
