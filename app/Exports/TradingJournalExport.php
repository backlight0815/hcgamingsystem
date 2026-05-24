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
use Carbon\Carbon;
use App\Services\TradingJournalTimeService;


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
            'Time Source',
            'Open Date (MT5)',
            'Close Date (MT5)',
        ];
    }

    public function map($journal): array
    {
        $timeService = app(TradingJournalTimeService::class);
        $timeInputOffsetMinutes = $journal->time_input_offset_minutes ?? null;

        return [
            $timeService->formatForDisplay($journal->open_date, TradingJournalTimeService::TIMEZONE_MALAYSIA),
            $timeService->formatForDisplay($journal->close_date, TradingJournalTimeService::TIMEZONE_MALAYSIA),
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
            $timeService->shortLabel($journal->time_input_timezone ?? null, $timeInputOffsetMinutes),
            $timeService->formatForDisplay($journal->open_date, TradingJournalTimeService::TIMEZONE_MT5, $timeInputOffsetMinutes),
            $timeService->formatForDisplay($journal->close_date, TradingJournalTimeService::TIMEZONE_MT5, $timeInputOffsetMinutes),
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

     $winRate >= 75 => [30, 'A'],   // was 70 → now 75
    $winRate >= 65 => [25, 'B+'],  // was 60 → now 65
    $winRate >= 60 => [25, 'B'],  // was 60 → now 65
    $winRate >= 55 => [20, 'C+'],   // was 50 → now 55
    $winRate >= 50 => [17, 'C'],   // was 50 → now 55
    $winRate >= 45 => [15, 'D+'],   // was 40 → now 45
    $winRate >= 35 => [10, 'D'],   // was 30 → now 35
    $winRate >  0  => [5,  'E'],   // same as before, >0 to cover beginners
    $winRate < 20 && $winRate >= 1 => [0, 'F'], // still fallback for very low win rate
    default => [0, 'N/A'],
        };
        

             // RRR
        if ($totalProfit > 0 && $totalLoss == 0) {
            $averageRRR = 'Perfect';
            [$rrrPoints, $rrrGrade] = [30, 'A+'];
        } elseif (is_numeric($averageRRR)) {
            [$rrrPoints, $rrrGrade] = match (true) {
            $averageRRR >= 5.75 => [30, 'A+'],  // was 5.0
    $averageRRR >= 3.45 => [25, 'A'],   // was 3.0
    $averageRRR >= 2.30 => [20, 'B'],   // was 2.0
    $averageRRR >= 1.73 => [15, 'C'],   // was 1.5
    $averageRRR >= 1.15 => [10, 'D'],   // was 1.0
    $averageRRR > 0    => [5,  'E'],   
    default             => [0, 'F'],
            };
        } else {
            [$rrrPoints, $rrrGrade] = [0, 'F'];
        }


           // Growth (Max 15 points)
[$growthPoints, $growthGrade] = match (true) {
$growthPercent >= 15   => [10, 'A'],  // was 10
    $growthPercent >= 7.5  => [7,  'B'],  // was 5
    $growthPercent >= 4.5  => [5,  'C'],  // was 3
    $growthPercent >= 1.5  => [3,  'D'],  // was 1
    $growthPercent > 0     => [1,  'E'],
    default                => [0,  'F'],
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

  // ---------------- Consistency Evaluation (R-multiple based) ----------------


// -------- Consistency Rule (FundingPips 15% rule) --------

// Group trades by day and sum profits for each day
$dailyPnL = $journals->groupBy(fn ($t) => Carbon::parse($t->close_date)->toDateString())
                     ->map(fn ($day) => (float) $day->sum('profit_loss'));

// Biggest winning day
$biggestWinningDay = $dailyPnL->count() ? max($dailyPnL->toArray()) : 0;

// Current total account profit (sum of all daily PnL)
$currentTotalProfit = $dailyPnL->sum() ?? 0;

// Consistency score (%)
$consistencyPercent = $currentTotalProfit > 0
    ? round(($biggestWinningDay / $currentTotalProfit) * 100, 2)
    : 0;

// Check if consistency target met (<= 15%)
$consistencyPassed = $consistencyPercent <= 15;

// Grade / Status
$consistencyGrade = $consistencyPassed ? '✅ Passed' : '⏳ Pending (Keep Trading)';

            [$expectancyPoints, $expectancyGrade] = match (true) {
  $expectancy >= 40  => [10, 'A+'],  // was 20
    $expectancy >= 20  => [7,  'B'],   // was 10
    $expectancy >= 10  => [5,  'C'],   // was 5
    $expectancy >= 0   => [0,  'N/A'],   // same
    $expectancy >= -10 => [-1, 'F'],   // was -5
    $expectancy >= -20 => [-2, 'F'],   // was -10
    default            => [0, 'N/A'],
            };


// -------- Consistency Score Points -------
 if ($consistencyPercent <= 10) {
     $consistencyPoints = 15; // Full score
    $consistencyGrade  = 'S';
}
elseif ($consistencyPercent <= 15) {
    $consistencyPoints = 12; // Full score
    $consistencyGrade  = 'A';
} elseif ($consistencyPercent <= 20) {
    $consistencyPoints = 10;
    $consistencyGrade  = 'B+';
} elseif ($consistencyPercent <= 25) {

} elseif ($consistencyPercent <= 30) {
    $consistencyPoints = 8;
    $consistencyGrade  = 'B';
} elseif ($consistencyPercent <= 35) {
    $consistencyPoints = 6;
    $consistencyGrade  = 'B-';
} elseif ($consistencyPercent <= 50) {
    $consistencyPoints = 4;
    $consistencyGrade  = 'C';
} elseif ($consistencyPercent <= 65) {
    $consistencyPoints = 2;
    $consistencyGrade  = 'D';
} elseif ($consistencyPercent <= 80) {
        $consistencyPoints = 1;
    $consistencyGrade  = 'E';
}else{
    $consistencyPoints = 0;
    $consistencyGrade  = 'F';
}

            $totalScore = $winRatePoints + $rrrPoints + $growthPoints + $drawdownPenalty + $consistencyPoints + $expectancyPoints;
            $finalScore = $totalScore;

            $rating = match (true) {
                $finalScore >= 95 => 'S',
                $finalScore >= 90 => 'A+',
                $finalScore >= 85 => 'A',
                $finalScore >= 80 => 'A-',
                $finalScore >= 75 => 'B+',
                $finalScore >= 70 => 'B',
                $finalScore >= 65 => 'B-',
                $finalScore >= 60 => 'C+',
                $finalScore >= 55 => 'C',
                $finalScore >= 50 => 'C-',
                $finalScore >= 45 => 'D',
                $finalScore >= 40 => 'D−',
                $finalScore >= 30 => 'E',
                default            => 'F',
            };

            // $grades = collect([$winRateGrade, $rrrGrade, $growthGrade, $drawdownGrade,$consistencyPoints]);
            // $cGrades = $grades->filter(fn($g) => $g === 'C')->count();
            // $cPlusExists = $grades->contains('C+');
            // $dExists = $grades->contains('D');

            // if ($rating === 'A+' && $grades->contains(fn($g) => $g !== 'A+')) $rating = 'A';
            // if ($cPlusExists && in_array($rating, ['A+', 'A'])) $rating = 'B+';
            // if ($cGrades === 1 && in_array($rating, ['A+', 'A', 'B+'])) $rating = 'B';
            // if ($cGrades >= 2 && in_array($rating, ['A+', 'A', 'B+', 'B'])) $rating = 'C+';
            // if ($dExists && !in_array($rating, ['C+', 'C', 'C−', 'D+', 'D', 'D−', 'E', 'F'])) $rating = 'C+';

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
            $sheet->setCellValue("B" . ($startRow + 9), round($consistencyPercent, 2));
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
    $sheet->getStyle("A{$dataStart}:N{$dataEnd}")->applyFromArray([
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
    $sheet->getStyle("A1:N1")->getFont()->setBold(true);

    // 🔠 Bold header row for summary section
    $sheet->getStyle("A{$summaryStart}:C{$summaryStart}")->getFont()->setBold(true);
}
}
