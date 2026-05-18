<?php

namespace App\Exports;

use App\Models\SignalPerformance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\TableStyleInfo;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SignalPerformanceExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected Collection $performances;
    protected array $evaluation;
    protected array $filters;
    protected ?int $providerId;
    public int $rowCount = 0; // for styling

    public function __construct(Collection $performances, array $evaluation = [], array $filters = [], ?int $providerId = null)
    {
        $this->performances = $performances;
        $this->evaluation = $evaluation;
        $this->filters = $filters;
        $this->providerId = $providerId;
    }

    /**
     * Return the collection to export
     */
    public function collection()
    {
        // Filter by provider if set
        $data = $this->performances;

        if ($this->providerId) {
            $data = $data->filter(function ($perf) {
                $signalUserId = $perf->signal->user_id ?? ($perf->backupSignal->user_id ?? null);
                return $signalUserId == $this->providerId;
            });
        }

        $this->rowCount = $data->count(); // set row count for styling
        return $data->values(); // reindex
    }

    /**
     * Excel headings
     */
    public function headings(): array
    {
        return [
            'Signal Code',
            'Pair',
            'Action',
            'Result',
            'Progress',
            'Profit Pips',
            'Profit USD',
            'Date',
        ];
    }

    /**
     * Map each row
     */
    public function map($performance): array
    {
        $signal = $performance->signal;

        $statusMap = [
            0  => 'Pending', 1  => 'Active', 2  => 'TP1', 3  => 'TP2', 4  => 'TP3', 5  => 'TP4',
            6  => 'TP5', 7  => 'TP6', 8  => 'TP7', 9  => 'TP8', 10 => 'TP9', 11 => 'TP10',
            12 => 'Cancelled', 13 => 'SL', 14 => 'Done', 15 => 'BE',
        ];

        $result = $statusMap[$signal->status] ?? 'Unknown';
        $progress = ($signal->IsDone ?? 0) == 1 ? 'Done' : 'No Done';

        return [
            $signal->signal_code ?? '-',
            strtoupper($signal->trading_pair ?? '-'),
            $signal->immediate_action ?? '-',
            $result,
            $progress,
            $performance->profit_pips,
            $performance->profit_usd ?? 0,
            $performance->created_at->format('Y-m-d'),
        ];
    }

    /**
     * AfterSheet: add summary statistics
     */
  
public function registerEvents(): array
{
    return [
        AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();

            // -------------------------------
            // Apply table styles
            // -------------------------------
            $this->applyStyles($sheet);

            // -------------------------------
            // Calculate start row for summary
            // -------------------------------
            $startRow = $this->rowCount + 3; // leave 2 rows spacing after data

            // Summary header
            $sheet->setCellValue("A{$startRow}", 'Summary Statistics');

            // If no data, fill N/A
            if ($this->performances->count() === 0) {
                for ($i = 1; $i <= 10; $i++) {
                    $sheet->setCellValue("A" . ($startRow + $i), 'N/A');
                    $sheet->setCellValue("B" . ($startRow + $i), 'N/A');
                }
                return;
            }

            // Evaluate summary statistics
            $summary = $this->evaluateSignalPerformance($this->performances, $this->providerId);

            // Fill summary rows
            $sheet->setCellValue("A" . ($startRow + 1), 'Total Trades');
            $sheet->setCellValue("B" . ($startRow + 1), $summary['totalTrades']);
            $sheet->setCellValue("C" . ($startRow + 1), 'Score');
            $sheet->setCellValue("D" . ($startRow + 1), 'Grade');

            $sheet->setCellValue("A" . ($startRow + 2), 'Winning Trades');
            $sheet->setCellValue("B" . ($startRow + 2), $summary['totalWinTrades']);

            $sheet->setCellValue("A" . ($startRow + 3), 'Losing Trades');
            $sheet->setCellValue("B" . ($startRow + 3), $summary['totalLoseTrades']);

            $sheet->setCellValue("A" . ($startRow + 4), 'Total Pips');
            $sheet->setCellValue("B" . ($startRow + 4), $summary['totalPips']);

            $sheet->setCellValue("A" . ($startRow + 5), 'Win Rate (%)');
            $sheet->setCellValue("B" . ($startRow + 5), $summary['winRate']);
            $sheet->setCellValue("C" . ($startRow + 5), $summary['winRatePoints']);
            $sheet->setCellValue("D" . ($startRow + 5), $summary['winRateGrade']);

            $sheet->setCellValue("A" . ($startRow + 6), 'Risk-Reward Ratio');
            $sheet->setCellValue("B" . ($startRow + 6), $summary['rrRatio']);
            $sheet->setCellValue("C" . ($startRow + 6), $summary['rrrPoints']);
            $sheet->setCellValue("D" . ($startRow + 6), $summary['rrrGrade']);

            $sheet->setCellValue("A" . ($startRow + 7), 'Profit Factor');
            $sheet->setCellValue("B" . ($startRow + 7), $summary['profitFactor']);
            $sheet->setCellValue("C" . ($startRow + 7), $summary['profitFactorPoints']);
            $sheet->setCellValue("D" . ($startRow + 7), $summary['profitFactorGrade']);

            $sheet->setCellValue("A" . ($startRow + 8), 'Expectancy');
            $sheet->setCellValue("B" . ($startRow + 8), $summary['expectancy']);
            $sheet->setCellValue("C" . ($startRow + 8), $summary['expectancyPoints']);
            $sheet->setCellValue("D" . ($startRow + 8), $summary['expectancyGrade']);

            $sheet->setCellValue("A" . ($startRow + 9), 'Total Score');
            $sheet->setCellValue("C" . ($startRow + 9), $summary['score']);
            $sheet->setCellValue("D" . ($startRow + 9), $summary['grade']);

            $sheet->setCellValue("A" . ($startRow + 10), 'Performance Meaning');
            $sheet->setCellValue("B" . ($startRow + 10), $summary['performanceMeaning']);

            // -------------------------------
            // Column B formatting
            // -------------------------------
            // Set column B width to approx 391 pixels (~56 character units)
            $sheet->getColumnDimension('B')->setWidth(56);

            // Wrap text for performance meaning
            $sheet->getStyle("B" . ($startRow + 10))
                  ->getAlignment()
                  ->setWrapText(true)
                  ->setVertical(Alignment::VERTICAL_TOP);

            // Optional: make all summary rows top-aligned
            $sheet->getStyle("A" . ($startRow + 1) . ":C" . ($startRow + 10))
                  ->getAlignment()
                  ->setVertical(Alignment::VERTICAL_TOP);
        },
    ];
}

/**
 * Apply styles to data & summary tables
 */
protected function applyStyles(Worksheet $sheet)
{
    $dataStart = 1;                  // Header row
    $dataEnd = $this->rowCount + 1;  // Last row of data
    $summaryStart = $dataEnd + 3;    // Summary header
    $summaryEnd = $summaryStart + 10;

    // -------------------------------
    // Data Table Borders
    // -------------------------------
    $sheet->getStyle("A{$dataStart}:H{$dataEnd}")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ]);

    // Header bold + background
    $sheet->getStyle("A{$dataStart}:H{$dataStart}")->getFont()->setBold(true);
    $sheet->getStyle("A{$dataStart}:H{$dataStart}")->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFDCE6F1'); // Light blue

    // Banded rows
    for ($row = $dataStart + 1; $row <= $dataEnd; $row++) {
        if ($row % 2 == 0) {
            $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF2F2F2'); // Light grey
        }
    }

    // -------------------------------
    // Summary Table Borders
    // -------------------------------
    $sheet->getStyle("A{$summaryStart}:D{$summaryEnd}")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ]);

    // Summary header bold + background
    $sheet->getStyle("A{$summaryStart}:D{$summaryStart}")->getFont()->setBold(true);
    $sheet->getStyle("A{$summaryStart}:D{$summaryStart}")->getFill()->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFE2EFDA'); // Light green

    // -------------------------------
    // Columns auto-size (except B)
    // -------------------------------
    foreach (range('A', 'H') as $col) {
        if ($col !== 'B') {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // Center align all
    $sheet->getStyle("A1:H{$dataEnd}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle("A{$summaryStart}:D{$summaryEnd}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
}
private function describeMetric(string $grade, string $metric): string
{
   return match ($grade) {
    'A+'       => "outstanding {$metric}, excellent performance",
    'A'        => "excellent {$metric}, very strong performance",
    'A-'       => "very good {$metric}, above expectations",
    'B+'       => "good {$metric}, solid performance",
    'B'        => "good {$metric}, acceptable performance",
    'B-'       => "{$metric} slightly below target",
    'C+'       => "{$metric} slightly below expectations",
    'C'        => "average {$metric}, needs improvement",
    'C-'       => "weak {$metric}, underperforming",
    'D+'       => "poor {$metric}, significant improvement needed",
    'D'        => "poor {$metric}, below expectations",
    'E+'       => "very poor {$metric}, performance is lacking",
    'E'        => "very weak {$metric}, substantial improvement needed",
    'E-'       => "extremely weak {$metric}, critical issues",
    'F'        => "failed {$metric}, unacceptable performance",
    default    => "unknown grade for {$metric}",
};
}


private function providerLevel(int $totalScore): string
{
    return match (true) {
        $totalScore >= 85 => 'Master/Expert Signal Provider',
        $totalScore >= 75 => 'Senior Signal Provider',
        $totalScore >= 60 => 'Junior Signal Provider',
        $totalScore > 50  => 'Intern Signal Provider',
        default           => 'Unqualified Signal Provider',
    };
}

private function evaluateSignalPerformance(Collection $performances, ?int $providerId = null): array
{
    // -------------------------
    // Filter by provider (optional)
    // -------------------------
    if ($providerId) {
        $performances = $performances->filter(function ($perf) use ($providerId) {
            $signalUserId = $perf->signal->user_id
                ?? ($perf->backupSignal->user_id ?? null);
            return $signalUserId == $providerId;
        });
    }

    // -------------------------
    // Basic statistics
    // -------------------------
    $totalTrades = $performances->count();
    $totalWinTrades = $performances->where('profit_pips', '>', 0)->count();
    $totalLoseTrades = $performances->where('profit_pips', '<', 0)->count();
    $totalPips = $performances->sum('profit_pips');

    $averageProfit = $totalWinTrades
        ? $performances->where('profit_pips', '>', 0)->avg('profit_pips')
        : 0;

    $averageLoss = $totalLoseTrades
        ? abs($performances->where('profit_pips', '<', 0)->avg('profit_pips'))
        : 0;

        $totalProfitPips = $performances
    ->where('profit_pips', '>', 0)
    ->sum('profit_pips');

$totalLossPips = abs(
    $performances
        ->where('profit_pips', '<', 0)
        ->sum('profit_pips')
);

$profitFactor = $totalLossPips > 0
    ? $totalProfitPips / $totalLossPips
    : 0;


    $winRate = $totalTrades ? ($totalWinTrades / $totalTrades) * 100 : 0;
    $rrRatio = ($averageProfit > 0 && $averageLoss > 0)
        ? $averageProfit / $averageLoss
        : 0;

    $expectancy = $averageLoss > 0
        ? ($averageProfit / $averageLoss) * $winRate
        : 0;

    // -------------------------
    // Win Rate Points & Grade
    // -------------------------
[$winRatePoints, $winRateGrade] = match (true) {
    $winRate >= 75 => [30, 'A+'],
    $winRate >= 73 => [29, 'A'],
    $winRate >= 71 => [28, 'A'],
    $winRate >= 69 => [27, 'A-'],
    $winRate >= 67 => [26, 'B+'],
    $winRate >= 65 => [25, 'B+'],
    $winRate >= 63 => [24, 'B'],
    $winRate >= 61 => [23, 'B'],
    $winRate >= 59 => [22, 'B'],
    $winRate >= 57 => [21, 'B-'],
    $winRate >= 55 => [20, 'C+'],
    $winRate >= 53 => [19, 'C+'],
    $winRate >= 52 => [18, 'C'],
    $winRate >= 51 => [17, 'C'],
    $winRate >= 50 => [15, 'C-'], // ✅ Passing score

    $winRate >= 47 => [14, 'D+'],
    $winRate >= 45 => [13, 'D+'],
    $winRate >= 43 => [12, 'D'],
    $winRate >= 41 => [11, 'D'],
    $winRate >= 39 => [10, 'D'],
    $winRate >= 37 => [9,  'E+'],
    $winRate >= 35 => [8,  'E'],
    $winRate >= 33 => [7,  'E'],
    $winRate >= 31 => [6,  'E'],
    $winRate >= 29 => [5,  'E-'],
    $winRate >= 25 => [4,  'F'],
    $winRate >= 20 => [3,  'F'],
    $winRate >= 15 => [2,  'F'],
    $winRate >= 10 => [1,  'F'],
    $winRate > 0   => [0,  'F'],
    default        => [0,  'F'],
};

// -------------------------
// Risk-Reward Points & Grade
// -------------------------
if ($totalPips > 0 && $averageLoss == 0) {
    [$rrrPoints, $rrrGrade] = [30, 'A+'];
} else {
    [$rrrPoints, $rrrGrade] = match (true) {
        $rrRatio >= 6.0  => [30, 'A+'],
        $rrRatio >= 5.7  => [29, 'A'],
        $rrRatio >= 5.4  => [28, 'A'],
        $rrRatio >= 5.1  => [27, 'A-'],
        $rrRatio >= 4.8  => [26, 'B+'],
        $rrRatio >= 4.5  => [25, 'B+'],
        $rrRatio >= 4.2  => [24, 'B'],
        $rrRatio >= 3.9  => [23, 'B'],
        $rrRatio >= 3.6  => [22, 'B'],
        $rrRatio >= 3.3  => [21, 'B-'],
        $rrRatio >= 3.0  => [20, 'C+'],
        $rrRatio >= 2.7  => [19, 'C+'],
        $rrRatio >= 2.4  => [18, 'C'],
        $rrRatio >= 2.1  => [17, 'C'],
        $rrRatio >= 1.8  => [16, 'C'],
        $rrRatio >= 1.2  => [15, 'C-'], // ✅ Passing mark

        $rrRatio >= 1.1  => [14, 'D+'],
        $rrRatio >= 1.0  => [13, 'D+'],
        $rrRatio >= 0.9  => [12, 'D'],
        $rrRatio >= 0.8  => [11, 'D'],
        $rrRatio >= 0.7  => [10, 'D'],
        $rrRatio >= 0.6  => [9,  'E+'],
        $rrRatio >= 0.5  => [8,  'E'],
        $rrRatio >= 0.4  => [7,  'E'],
        $rrRatio >= 0.3  => [6,  'E'],
        $rrRatio >= 0.2  => [5,  'E-'],
        $rrRatio >  0    => [3,  'F'],
        default          => [0,  'F'],
    };
}

[$profitFactorPoints, $profitFactorGrade] = match(true) {
    // -----------------------------
    // Elite/high PF
    // -----------------------------
    $profitFactor >= 10.0 => [20, 'A+'], 
    $profitFactor >= 9.0  => [19, 'A'],
    $profitFactor >= 8.0  => [18, 'A'],
    $profitFactor >= 7.0  => [17, 'A-'],
    $profitFactor >= 6.0  => [16, 'B+'],
    $profitFactor >= 5.0  => [15, 'B+'],
    $profitFactor >= 4.5  => [14, 'B'],
    $profitFactor >= 4.0  => [13, 'B'],
    $profitFactor >= 3.5  => [12, 'B-'],
    $profitFactor >= 3.0  => [11, 'C+'],

    // -----------------------------
    // Profitable trades (1.1 → 3.0)
    // -----------------------------
    $profitFactor >= 2.7  => [10, 'C'], 
    $profitFactor >= 2.4  => [9, 'C'],
    $profitFactor >= 2.1  => [8, 'C-'],
    $profitFactor >= 2.0  => [7, 'D+'],
    $profitFactor >= 1.9  => [6, 'D'],
    $profitFactor >= 1.8  => [5, 'D-'],
    $profitFactor >= 1.5  => [4, 'E+'],
    $profitFactor >= 1.3  => [3, 'E'],
    $profitFactor >= 1.1  => [2, 'D'], // ✅ Minimum profitable PF

    // -----------------------------
    // Losing trades (PF < 1)
    // -----------------------------
    $profitFactor > 0      => [0, 'F'], // Total loss > total win
    default                => [0, 'F'],
};

    // -------------------------
    // Trade Selection Points & Grade
    // -------------------------
    $goodTrades = $performances->filter(function ($trade) {
        $lossPips = $trade->profit_pips < 0 ? abs($trade->profit_pips) : 1;
        return $trade->profit_pips > 0 && ($trade->profit_pips / $lossPips) >= 1;
    })->count();

    $tradeSelectionPercent = $totalTrades
        ? ($goodTrades / $totalTrades) * 100
        : 0;

 
$tradeSelectionPoints = match (true) {
    $tradeSelectionPercent >= 90 => 20, // ✅ Full score
    $tradeSelectionPercent >= 86 => 19,
    $tradeSelectionPercent >= 82 => 18,
    $tradeSelectionPercent >= 78 => 17,
    $tradeSelectionPercent >= 74 => 16,
    $tradeSelectionPercent >= 70 => 15,
    $tradeSelectionPercent >= 66 => 14,
    $tradeSelectionPercent >= 62 => 13,
    $tradeSelectionPercent >= 58 => 12,
    $tradeSelectionPercent >= 54 => 11,
    $tradeSelectionPercent >= 50 => 10, // ✅ Passing mark

    $tradeSelectionPercent >= 46 => 9,
    $tradeSelectionPercent >= 42 => 8,
    $tradeSelectionPercent >= 38 => 7,
    $tradeSelectionPercent >= 34 => 6,
    $tradeSelectionPercent >= 30 => 5,
    $tradeSelectionPercent >= 25 => 4,
    $tradeSelectionPercent >= 20 => 3,
    $tradeSelectionPercent >= 15 => 2,
    $tradeSelectionPercent >= 10 => 1,
    default                     => 0,
};

    $tradeSelectionGrade = match (true) {
    $tradeSelectionPoints >= 20 => 'A+',
    $tradeSelectionPoints >= 18 => 'A',
    $tradeSelectionPoints >= 15 => 'B',
    $tradeSelectionPoints >= 13 => 'C+',
    $tradeSelectionPoints >= 10 => 'C-',
    $tradeSelectionPoints >= 8  => 'D',
    $tradeSelectionPoints >= 5  => 'E',
        default                     => 'F',
    };

    // -------------------------
    // Expectancy Points & Grade
    // -------------------------
[$expectancyPoints, $expectancyGrade] = match (true) {
    $expectancy >= 200 => [20, 'A+'], // ✅ Max
    $expectancy >= 190 => [19, 'A'],
    $expectancy >= 180 => [18, 'A'],
    $expectancy >= 170 => [17, 'A-'],
    $expectancy >= 160 => [16, 'B+'],
    $expectancy >= 150 => [15, 'B+'],
    $expectancy >= 140 => [14, 'B'],
    $expectancy >= 130 => [13, 'B'],
    $expectancy >= 120 => [12, 'B'],
    $expectancy >= 110 => [11, 'B-'],
    $expectancy >= 100 => [10, 'C+'], // ✅ Passing mark

    $expectancy >= 90  => [9,  'C'],
    $expectancy >= 80  => [8,  'C'],
    $expectancy >= 70  => [7,  'C'],
    $expectancy >= 60  => [6,  'C-'],
    $expectancy >= 50  => [5,  'D+'],
    $expectancy >= 40  => [4,  'D'],
    $expectancy >= 30  => [3,  'D'],
    $expectancy >= 20  => [2,  'E'],
    $expectancy >= 10  => [1,  'E'],
    $expectancy >= 0   => [0,  'N/A'],
    default            => [-5, 'F'], // Negative expectancy
};
    // -------------------------
    // Total Score & Overall Grade
    // -------------------------
    $totalScore =
        $winRatePoints +
        $rrrPoints +
        $profitFactorPoints +
        $expectancyPoints;

    $rating = match (true) {
       // S Tier (85+)
    $totalScore >= 95 => 'S+',
    $totalScore >= 90 => 'S',
    $totalScore >= 85 => 'S-',

    // A Tier (70–84)
    $totalScore >= 80 => 'A',
    $totalScore >= 70 => 'A-',

    // BTier (60-69)
    $totalScore >= 65 => 'B+',
    $totalScore >= 60 => 'B',

    //C Tier (50-59)
        $totalScore >= 55=> 'C',
    $totalScore >= 50 => 'C-',

    // D Tier (46–54)
    $totalScore >= 45 => 'D',
    $totalScore >  40 => 'D-',

    default           => 'F',
    };

    // -------------------------
    // Provider Level
    // -------------------------
    $providerLevel = match (true) {
        $totalScore >= 85 => 'Master / Expert Signal Provider',
        $totalScore >= 70 => 'Senior Signal Provider',
        $totalScore >= 60 => 'Junior Signal Provider',
        $totalScore > 50  => 'Intern Signal ProvideR',
        default           => 'Not Qualify',
    };

    // -------------------------
    // Performance Meaning (Interview + Evaluation)
    // -------------------------
   $providerLevel = $this->providerLevel($totalScore);

$performanceMeaning =
    "{$providerLevel}: Overall evaluation based on this week’s signals. " .
    ucfirst($this->describeMetric($winRateGrade, 'win rate')) . " ({$winRateGrade}), " .
    ucfirst($this->describeMetric($rrrGrade, 'risk-reward ratio')) . " ({$rrrGrade}), " .
    ucfirst($this->describeMetric($profitFactorGrade, 'profit factor')) . " ({$profitFactorGrade}), " .
    ucfirst($this->describeMetric($expectancyGrade, 'expectancy')) . " ({$expectancyGrade}).";
    // -------------------------
    // Excel-Friendly Summary
    // -------------------------
    $excelSummary = [
        'Total Trades'   => $totalTrades,
        'Winning Trades' => $totalWinTrades,
        'Losing Trades'  => $totalLoseTrades,
        'Total Pips'     => round($totalPips, 2),
        'Total Score'    => $totalScore,
        'Overall Grade'  => $rating,
        'Provider Level' => $providerLevel,
    ];

    $excelMetrics = [
        [
            'Metric' => 'Win Rate',
            'Value'  => round($winRate, 2) . '%',
            'Grade'  => $winRateGrade,
            'Points' => $winRatePoints,
        ],
        [
            'Metric' => 'Risk Reward Ratio',
            'Value'  => round($rrRatio, 2),
            'Grade'  => $rrrGrade,
            'Points' => $rrrPoints,
        ],
      [
    'Metric' => 'Profit Factor',
    'Value'  => $profitFactor > 0 ? round($profitFactor, 2) : 0,
    'Grade'  => $profitFactorGrade,
    'Points' => $profitFactorPoints,
],

        [
            'Metric' => 'Expectancy',
            'Value'  => round($expectancy, 2),
            'Grade'  => $expectancyGrade,
            'Points' => $expectancyPoints,
        ],
    ];

    // -------------------------
    // Final return (Excel Ready)
    // -------------------------
return [
    // ---- core summary ----
    'score' => $totalScore,
    'grade' => $rating,
    'providerLevel' => $providerLevel,
    'performanceMeaning' => $performanceMeaning,

    // ---- trades ----
    'totalTrades' => $totalTrades,
    'totalWinTrades' => $totalWinTrades,
    'totalLoseTrades' => $totalLoseTrades,
    'totalPips' => round($totalPips, 2),

    // ---- metrics ----
    'winRate' => round($winRate, 2),
    'rrRatio' => round($rrRatio, 2),
    'tradeSelectionPercent' => round($tradeSelectionPercent, 2),
    'expectancy' => round($expectancy, 2),
    'profitFactor' => round($profitFactor, 2),  // ✅ add this

    // ---- grades ----
    'winRateGrade' => $winRateGrade,
    'rrrGrade' => $rrrGrade,
    'tradeSelectionGrade' => $tradeSelectionGrade,
    'expectancyGrade' => $expectancyGrade,
    'profitFactorGrade' => $profitFactorGrade,  // ✅ add this

    // ---- points ----
    'winRatePoints' => $winRatePoints ?? 0,
    'rrrPoints' => $rrrPoints ?? 0,
    'tradeSelectionPoints' => $tradeSelectionPoints ?? 0,
    'expectancyPoints' => $expectancyPoints ?? 0,
    'profitFactorPoints' => $profitFactorPoints ?? 0, // ✅ add this

    // ---- excel blocks ----
    'excelSummary' => $excelSummary,
    'excelMetrics' => $excelMetrics,
];

}


}
