<?php

namespace App\Exports;
use App\Models\Capital; // Ensure you import this
use Illuminate\Support\Facades\Auth;
use App\Models\TradingJournal;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Collection;

class TradingJournalExport implements FromCollection, WithTitle, WithHeadings, WithMapping, WithEvents, ShouldAutoSize, WithStyles
{
    protected $rowCount;


public function collection()
{
    $userId = Auth::id(); // ✅ Get current user
    $data = TradingJournal::where('user_id', $userId)->latest()->get(); // ✅ Filter by user
    $this->rowCount = $data->count(); // ✅ Accurate row count
    return $data;
}

    public function title(): string
    {
        return 'Trading Journal';
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
            'Notes',
        ];
    }

    public function map($journal): array
    {
        return [
            $journal->open_date,
            $journal->close_date,
            strtoupper($journal->pair),
            $journal->direction == 1 ? 'Buy' : ($journal->direction == 2 ? 'Sell' : 'Unknown'),
            $journal->entry_price,
            $journal->exit_price,
            $journal->lot_size,
            $journal->pips,
            $journal->profit_loss,
            match ($journal->result) {
                1 => 'Win',
                2 => 'Loss',
                3 => 'Break Even',
                default => 'N/A'
            },
            $journal->notes,
        ];
    }
public function registerEvents(): array
{
    return [
        AfterSheet::class => function (AfterSheet $event) {
            $userId = Auth::id();
            $journals = TradingJournal::where('user_id', $userId)->latest()->get();
                $hasTrade = $journals->count() > 0;
   $sheet = $event->sheet->getDelegate();
                $startRow = $this->rowCount + 3;
                $sheet->setCellValue("A{$startRow}", 'Summary Statistics');

                if (!$hasTrade) {
                    for ($i = 1; $i <= 12; $i++) {
                        $sheet->setCellValue("A" . ($startRow + $i), 'N/A');
                        $sheet->setCellValue("B" . ($startRow + $i), 'N/A');
                        $sheet->setCellValue("C" . ($startRow + $i), 'N/A');
                    }
                    return;
                }
            // ✅ Capital
            $totalDeposits = Capital::where('user_id', $userId)->where('type', 1)->sum('amount');
            $totalWithdrawals = abs(Capital::where('user_id', $userId)->where('type', 2)->sum('amount'));
            $initialCapital = $totalDeposits;
            $netPL = $journals->sum('profit_loss');
            $growthPercent = $initialCapital > 0 ? round(($netPL / $initialCapital) * 100, 2) : 0;
            if ($netPL <= 0) $growthPercent = 0;

            // ✅ Journal stats
            $totalTrades = $journals->count();
            $winTrades = $journals->where('profit_loss', '>', 0);
            $lossTrades = $journals->where('profit_loss', '<', 0);
            $breakevenTrades = $journals->where('profit_loss', '=', 0);

            $totalWithoutBreakEven = $winTrades->count() + $lossTrades->count();
            $winRate = $totalWithoutBreakEven > 0 ? round(($winTrades->count() / $totalWithoutBreakEven) * 100, 2) : 0;

            $totalProfit = $winTrades->sum('profit_loss');
            $totalLoss = abs($lossTrades->sum('profit_loss'));
            $totalGrossProfit = $winTrades->sum('profit_loss');
            $totalGrossLoss = $lossTrades->sum('profit_loss');
            $riskRewardRatio = $totalLoss > 0 ? $totalProfit / $totalLoss : 0;

            $averageRRR = ($totalLoss > 0 && $totalProfit > 0) ? round($totalProfit / $totalLoss, 2) : 'N/A';
            $drawdownPercent = (float) $journals->avg('drawdown_percent') ?? 0;
            $stdDeviation = $this->calculateStandardDeviation($journals);
            $expectancy = $this->calculateExpectancy($journals);

            // --- Grade and Points Calculation ---
            [$winRatePoints, $winRateGrade] = match (true) {
                 $winRate >= 90 => [30, 'A+'],
    $winRate >= 85 => [28, 'A'],
    $winRate >= 80 => [26, 'A-'],
    $winRate >= 75 => [24, 'B+'],
    $winRate >= 70 => [22, 'B'],
    $winRate >= 65 => [20, 'B-'],
    $winRate >= 60 => [18, 'C+'],
    $winRate >= 55 => [16, 'C'],
    $winRate >= 50 => [14, 'C-'],
    $winRate >= 40 => [10, 'D'],
    $winRate >= 20 => [5, 'E'],
            $winRate < 20 && $winRate >= 1 => [0, 'F'],
                default         => [0, 'F'],
            };

        

             // RRR
        if ($totalProfit > 0 && $totalLoss == 0) {
            $averageRRR = 'Perfect';
            [$rrrPoints, $rrrGrade] = [30, 'A+'];
        } elseif (is_numeric($averageRRR)) {
            [$rrrPoints, $rrrGrade] = match (true) {
            $averageRRR >= 6.0 => [30, 'A+'],
        $averageRRR >= 5.5 => [28, 'A'],
        $averageRRR >= 5.0 => [26, 'A-'],
        $averageRRR >= 4.5 => [24, 'B+'],
        $averageRRR >= 4.0 => [22, 'B'],
        $averageRRR >= 3.5 => [20, 'B-'],
        $averageRRR >= 3.0 => [18, 'C+'],
        $averageRRR >= 2.5 => [16, 'C'],
        $averageRRR >= 2.0 => [14, 'C-'],
        $averageRRR >= 1.5 => [10, 'D'],
        $averageRRR >= 1.0 => [6,  'E'],
                default => [0, 'F'],
            };
        } else {
            [$rrrPoints, $rrrGrade] = [0, 'F'];
        }


           // Growth (Max 15 points)
[$growthPoints, $growthGrade] = match (true) {
         $growthPercent >= 15 => [5, 'A'], // Exceptional growth
    $growthPercent >= 10 => [4, 'B'],  // Strong growth
    $growthPercent >= 5  => [3,  'C+'], // Acceptable growth
    $growthPercent >= 3  => [2,  'C'], // Very weak
    $growthPercent >= 2  => [1,  'D'], // Very weak
    $growthPercent >= 1  => [1,  'E'], // Very weak
    default              => [0,  'F'],  // Negative or zero growth
};


        // Drawdown penalty and grade mapping
[$drawdownPenalty, $drawdownGrade] = match (true) {
    $drawdownPercent > 90 => [-35, 'F'],
    $drawdownPercent > 80 => [-20, 'F'],
    $drawdownPercent > 60 => [-15, 'F'],
    $drawdownPercent > 40 => [-10, 'F'],
    $drawdownPercent > 30 => [-8,  'E'],
    $drawdownPercent > 20 => [-6,  'D'],
    $drawdownPercent > 10 => [-4,  'C'],
    $drawdownPercent > 5  => [-2,  'B'],
    $drawdownPercent > 2  => [-1,  'A'],
    default               => [0,   'A+'],
};

// This score is for penalty only, not to be added to total score
$drawdownScore = 0;

// Later: subtract this from total score (convert to positive before subtracting)
$drawdownPenalty = abs($drawdownPenalty);

       
    [$consistencyPoints, $consistencyGrade] = ($totalTrades >= 1 && is_numeric($stdDeviation)) ? match (true) {
         $stdDeviation <= 15   => [25, 'A+'],
         $stdDeviation <= 20  => [20, 'A'],
            $stdDeviation <= 25  => [15, 'A-'],
            $stdDeviation <= 30  => [10, 'B'],
            $stdDeviation <= 35  => [5, 'C'],
            $stdDeviation <= 40  => [2, 'D'],
            $stdDeviation <= 45  => [1, 'E'],
        default              => [0, 'F'],
    } : [0, 'N/A'];

            [$expectancyPoints, $expectancyGrade] = match (true) {
                    $expectancy >= 40 => [10, 'A+'],
                $expectancy >= 35 => [7, 'A'],
                $expectancy >= 30 => [6, 'A−'],
                $expectancy >= 25 => [5, 'B+'],
                $expectancy >= 20 => [4, 'B'],
                $expectancy >= 15 => [3, 'C+'],
                $expectancy >= 10 => [2, 'C'],
                $expectancy >= 5 => [1, 'D'],
                $expectancy >= 0 => [0, 'E'],
                $expectancy >= -5 => [-1, 'F'],
                $expectancy >= -10 => [-2, 'F'],
                default => [0, 'N/A'],
            };

            $totalScore = $winRatePoints + $rrrPoints + $growthPoints + $drawdownPenalty + $consistencyPoints + $expectancyPoints;
            $finalScore = $totalScore;

            $rating = match (true) {
                $finalScore >= 95 => 'A+',
                $finalScore >= 90 => 'A',
                $finalScore >= 85 => 'A−',
                $finalScore >= 80 => 'B+',
                $finalScore >= 75 => 'B',
                $finalScore >= 70 => 'B−',
                $finalScore >= 65 => 'C+',
                $finalScore >= 60 => 'C',
                $finalScore >= 55 => 'C−',
                $finalScore >= 50 => 'D+',
                $finalScore >= 45 => 'D',
                $finalScore >= 40 => 'D−',
                $finalScore >= 30 => 'E',
                default            => 'F',
            };

            $grades = collect([$winRateGrade, $rrrGrade, $growthGrade, $drawdownGrade,$consistencyPoints]);
            $cGrades = $grades->filter(fn($g) => $g === 'C')->count();
            $cPlusExists = $grades->contains('C+');
            $dExists = $grades->contains('D');

            if ($rating === 'A+' && $grades->contains(fn($g) => $g !== 'A+')) $rating = 'A';
            if ($cPlusExists && in_array($rating, ['A+', 'A'])) $rating = 'B+';
            if ($cGrades === 1 && in_array($rating, ['A+', 'A', 'B+'])) $rating = 'B';
            if ($cGrades >= 2 && in_array($rating, ['A+', 'A', 'B+', 'B'])) $rating = 'C+';
            if ($dExists && !in_array($rating, ['C+', 'C', 'C−', 'D+', 'D', 'D−', 'E', 'F'])) $rating = 'C+';

            // ✅ Output to Sheet
            $sheet = $event->sheet->getDelegate();
            $startRow = $this->rowCount + 3;

            $sheet->setCellValue("A{$startRow}", 'Summary Statistics');

            $sheet->setCellValue("A" . ($startRow + 1), 'Total Trades');
            $sheet->setCellValue("B" . ($startRow + 1), $totalTrades);

            $sheet->setCellValue("A" . ($startRow + 2), 'Win Rate (%)');
            $sheet->setCellValue("B" . ($startRow + 2), $winRate);
            $sheet->setCellValue("C" . ($startRow + 2), $winRateGrade);

            $sheet->setCellValue("A" . ($startRow + 3), 'Risk-Reward Ratio');
            $sheet->setCellValue("B" . ($startRow + 3), is_numeric($riskRewardRatio) ? round($riskRewardRatio, 2) : 'N/A');
            $sheet->setCellValue("C" . ($startRow + 3), $rrrGrade);

            $sheet->setCellValue("A" . ($startRow + 4), 'Net Profit / Loss');
            $sheet->setCellValue("B" . ($startRow + 4), $netPL);

            $sheet->setCellValue("A" . ($startRow + 5), 'Gross Profit');
            $sheet->setCellValue("B" . ($startRow + 5), $totalGrossProfit);

            $sheet->setCellValue("A" . ($startRow + 6), 'Gross Loss');
            $sheet->setCellValue("B" . ($startRow + 6), $totalGrossLoss);

            $sheet->setCellValue("A" . ($startRow + 7), 'Growth (%)');
            $sheet->setCellValue("B" . ($startRow + 7), $growthPercent);
            $sheet->setCellValue("C" . ($startRow + 7), $growthGrade);

            $sheet->setCellValue("A" . ($startRow + 8), 'Drawdown (%)');
            $sheet->setCellValue("B" . ($startRow + 8), $drawdownPercent);
            $sheet->setCellValue("C" . ($startRow + 8), $drawdownGrade);

            $sheet->setCellValue("A" . ($startRow + 9), 'Consistency Score (σ)');
            $sheet->setCellValue("B" . ($startRow + 9), round($stdDeviation, 2));
            $sheet->setCellValue("C" . ($startRow + 9), $consistencyGrade);

            $sheet->setCellValue("A" . ($startRow + 10), 'Expectancy');
            $sheet->setCellValue("B" . ($startRow + 10), round($expectancy, 2));
            $sheet->setCellValue("C" . ($startRow + 10), $expectancyGrade);

            $sheet->setCellValue("A" . ($startRow + 11), 'Performance Points');
            $sheet->setCellValue("B" . ($startRow + 11), $totalScore);

            $sheet->setCellValue("A" . ($startRow + 12), 'Grade');
            $sheet->setCellValue("B" . ($startRow + 12), $rating);
        },
    ];
}
private function calculateStandardDeviation(Collection $journals): float
{
    // Step 1: Extract only numeric profit/loss values
    $profits = $journals->pluck('profit_loss')->filter(function ($value) {
        return is_numeric($value);
    })->values(); // Ensure 0-based index

    $count = $profits->count();

    // Step 2: Return 0 if there's no valid data
    if ($count <= 1) {
        return 0;
    }

    // Step 3: Calculate the mean (average)
    $mean = $profits->avg();

    // Step 4: Calculate the variance (use N-1 for sample std deviation)
    $variance = $profits->map(function ($value) use ($mean) {
        return pow($value - $mean, 2);
    })->sum() / ($count - 1);

    // Step 5: Return the standard deviation (square root of variance)
    return round(sqrt($variance), 2);
}

private function calculateExpectancy(Collection $journals): float
{
    $totalTrades = $journals->count();
    if ($totalTrades === 0) {
        return 0;
    }

    $winTrades = $journals->where('result', 1);
    $lossTrades = $journals->where('result', 2);

    $winCount = $winTrades->count();
    $lossCount = $lossTrades->count();

    $winRate = $winCount / $totalTrades;
    $lossRate = $lossCount / $totalTrades;

    $avgWin = $winTrades->avg('profit_loss') ?? 0;
    $avgLoss = abs($lossTrades->avg('profit_loss') ?? 0);

    // Expectancy formula
    $expectancy = ($winRate * $avgWin) - ($lossRate * $avgLoss);

    return round($expectancy, 2);
}

  public function styles(Worksheet $sheet)
{
    $dataStart = 1;
    $dataEnd = $this->rowCount + 1; // Including header
    $summaryStart = $dataEnd + 3;
    $summaryEnd = $summaryStart + 12;

    // 🔲 Border for main journal data
    $sheet->getStyle("A{$dataStart}:K{$dataEnd}")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ]);

    // 🔲 Border for summary section (including grade)
    $sheet->getStyle("A{$summaryStart}:C{$summaryEnd}")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ]);

    // 🔠 Bold header for journal table
    $sheet->getStyle("A1:K1")->getFont()->setBold(true);

    // 🔠 Bold header row for summary section
    $sheet->getStyle("A{$summaryStart}:C{$summaryStart}")->getFont()->setBold(true);
}
}
