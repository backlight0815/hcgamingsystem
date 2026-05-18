<?php

namespace App\Exports;

use App\Models\TradingSignal;
use App\Models\SignalPerformance;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Events\AfterSheet;

class SignalPerformanceExport implements FromCollection, WithTitle, WithHeadings, WithMapping, WithEvents, WithStyles, ShouldAutoSize
{
    protected $rowCount;

    public function collection()
    {
        $userId = Auth::id();

        // Fetch user's signal performances
        $data = SignalPerformance::with('signal')
            ->whereHas('signal', fn($q) => $q->where('user_id', $userId))
            ->latest()
            ->get();

        $this->rowCount = $data->count();

        return $data;
    }

    public function title(): string
    {
        return 'Signal Performance';
    }

    public function headings(): array
    {
        return [
            'Signal Code',
            'Pair',
            'Action',
            'Status',
            'Profit Pips',
            'Profit USD',
            'Date',
        ];
    }

    public function map($performance): array
    {
        return [
            $performance->signal->signal_code ?? '-',
            $performance->signal->trading_pair ?? '-',
            $performance->signal->immediate_action ?? '-',
            $performance->is_sl ? 'SL' : ($performance->is_cancelled ? 'Cancelled' : 'Active'),
            $performance->profit_pips,
            $performance->profit_usd ?? 0,
            optional($performance->created_at)->format('Y-m-d'),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $startRow = $this->rowCount + 3;

                // Summary statistics
                $sheet->setCellValue("A{$startRow}", 'Summary Statistics');

                $performances = $this->collection();
                $totalTrades = $performances->count();
                $winTrades = $performances->where('profit_pips', '>', 0)->count();
                $lossTrades = $performances->where('profit_pips', '<', 0)->count();
                $totalPips = $performances->sum('profit_pips');

                $winRate = $totalTrades > 0 ? round(($winTrades / $totalTrades) * 100, 2) : 0;

                $sheet->setCellValue("A" . ($startRow + 1), 'Total Trades');
                $sheet->setCellValue("B" . ($startRow + 1), $totalTrades);

                $sheet->setCellValue("A" . ($startRow + 2), 'Win Rate (%)');
                $sheet->setCellValue("B" . ($startRow + 2), $winRate);

                $sheet->setCellValue("A" . ($startRow + 3), 'Total Pips');
                $sheet->setCellValue("B" . ($startRow + 3), $totalPips);
            }
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $dataStart = 1;
        $dataEnd = $this->rowCount + 1;
        $summaryStart = $dataEnd + 3;
        $summaryEnd = $summaryStart + 3;

        // Border for main table
        $sheet->getStyle("A{$dataStart}:G{$dataEnd}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Border for summary
        $sheet->getStyle("A{$summaryStart}:B{$summaryEnd}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        // Bold headers
        $sheet->getStyle("A1:G1")->getFont()->setBold(true);
        $sheet->getStyle("A{$summaryStart}:A{$summaryStart}")->getFont()->setBold(true);
    }
}
