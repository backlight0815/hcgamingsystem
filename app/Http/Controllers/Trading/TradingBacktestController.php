<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\TradingPair;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;

class TradingBacktestController extends Controller
{
    public function index(Request $request)
    {
        [$currentUser, $canViewAll, $selectedTraderId, $traders] = $this->accessContext($request);
        $trades = $this->loadJournalTrades($selectedTraderId);

        return view('admin.trading_backtest.index', $this->viewData(
            $trades,
            'Trading Journal',
            null,
            collect(),
            null,
            $currentUser,
            $canViewAll,
            $selectedTraderId,
            $traders
        ));
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'backtest_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'candle_file' => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480'],
            'pair' => ['nullable', 'string', 'max:20'],
            'user_id' => ['nullable'],
        ]);

        [$currentUser, $canViewAll, $selectedTraderId, $traders] = $this->accessContext($request);
        $pair = strtoupper((string) ($validated['pair'] ?? 'XAUUSD')) ?: 'XAUUSD';
        $file = $request->file('backtest_file');

        try {
            $trades = $this->parseUploadedTrades($file, $pair);
        } catch (\Throwable) {
            return back()
                ->withErrors(['backtest_file' => 'The uploaded backtest file could not be read. Please upload a valid XLSX, XLS, or CSV file.'])
                ->withInput();
        }

        $candles = collect();
        $candleFile = $request->file('candle_file');

        if ($candleFile) {
            try {
                $candles = $this->parseUploadedCandles($candleFile, $pair);
            } catch (\Throwable) {
                return back()
                    ->withErrors(['candle_file' => 'The uploaded candle history file could not be read. Please upload a valid OHLC XLSX, XLS, or CSV file.'])
                    ->withInput();
            }
        }

        return view('admin.trading_backtest.index', $this->viewData(
            $trades,
            'Excel Upload',
            $file?->getClientOriginalName(),
            $candles,
            $candleFile?->getClientOriginalName(),
            $currentUser,
            $canViewAll,
            $selectedTraderId,
            $traders
        ));
    }

    private function accessContext(Request $request): array
    {
        $currentUser = Auth::user();
        $canViewAll = $currentUser && in_array((int) $currentUser->role_id, [1, 2], true);
        $selectedTraderId = $canViewAll ? $this->normalizeTraderId($request->input('user_id')) : optional($currentUser)->id;
        $traders = $canViewAll
            ? User::whereIn('id', $this->journalTraderIds())->orderBy('name')->get(['id', 'name', 'username'])
            : collect();

        return [$currentUser, $canViewAll, $selectedTraderId, $traders];
    }

    private function viewData(
        Collection $trades,
        string $sourceLabel,
        ?string $uploadName,
        Collection $candles,
        ?string $candleName,
        $currentUser,
        bool $canViewAll,
        ?int $selectedTraderId,
        Collection $traders
    ): array {
        $trades = $trades
            ->map(fn (array $trade): array => $this->enrichTrade($trade))
            ->sortBy(fn (array $trade) => $trade['opened_at'] ?? $trade['closed_at'] ?? Carbon::create(1970, 1, 1))
            ->values();

        $summary = $this->buildSummary($trades);
        $chartTrades = $this->buildChartTrades($trades);
        $replayCandles = $this->buildReplayCandles($candles);
        $breadcrumbData = [
            ['label' => 'Trading Journal', 'url' => route('all.trading.journals')],
            ['label' => 'Backtest Lab', 'url' => route('trading.backtest.index')],
        ];

        return [
            'breadcrumbData' => $breadcrumbData,
            'backtestTrades' => $trades,
            'summary' => $summary,
            'chartTrades' => $chartTrades,
            'replayCandles' => $replayCandles,
            'hasRealCandles' => $replayCandles->isNotEmpty(),
            'sourceLabel' => $sourceLabel,
            'uploadName' => $uploadName,
            'candleName' => $candleName,
            'currentUser' => $currentUser,
            'canViewAll' => $canViewAll,
            'selectedTraderId' => $selectedTraderId,
            'traders' => $traders,
            'pair' => 'XAUUSD',
        ];
    }

    private function loadJournalTrades(?int $userId): Collection
    {
        $currentTrades = $this->loadJournalTable(
            TradingJournal::query(),
            'trading_journals',
            'Current Journal',
            $userId
        );

        $backupTrades = Schema::hasTable('trading_journals_backup')
            ? $this->loadJournalTable(TradingJournalBackup::query(), 'trading_journals_backup', 'Backup Journal', $userId)
            : collect();

        return $currentTrades->merge($backupTrades)->take(120)->values();
    }

    private function loadJournalTable($query, string $table, string $source, ?int $userId): Collection
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $columns = Schema::getColumnListing($table);

        if (in_array('user_id', $columns, true) && $userId) {
            $query->where('user_id', $userId);
        }

        if (in_array('type', $columns, true)) {
            $query->where(function ($typeQuery): void {
                $typeQuery->where('type', 'trade')->orWhereNull('type');
            });
        }

        if (in_array('pair', $columns, true)) {
            $query->whereRaw('UPPER(pair) = ?', ['XAUUSD']);
        }

        $orderColumn = in_array('close_date', $columns, true)
            ? 'close_date'
            : (in_array('trade_date', $columns, true) ? 'trade_date' : 'created_at');

        return $query
            ->orderByDesc($orderColumn)
            ->limit(120)
            ->get()
            ->map(fn ($trade): array => $this->journalToTrade($trade, $source));
    }

    private function journalToTrade($trade, string $source): array
    {
        $entry = (float) ($trade->entry_price ?? 0);
        $exit = (float) ($trade->exit_price ?? $entry);
        $direction = (int) ($trade->direction ?? 1);
        $profitLoss = (float) ($trade->profit_loss ?? 0);
        $result = $this->parseResult($trade->result ?? null) ?? $this->inferResult($direction, $entry, $exit, $profitLoss);

        return [
            'source' => $source,
            'opened_at' => $this->parseDateValue($trade->open_date ?? $trade->trade_date ?? $trade->created_at),
            'closed_at' => $this->parseDateValue($trade->close_date ?? $trade->trade_date ?? $trade->created_at),
            'pair' => strtoupper((string) ($trade->pair ?? 'XAUUSD')),
            'direction' => $direction,
            'entry_price' => $entry,
            'exit_price' => $exit,
            'stop_loss' => $result === 2 ? $exit : null,
            'take_profit' => $result === 1 ? $exit : null,
            'lot_size' => (float) ($trade->lot_size ?? 0),
            'pips' => (float) ($trade->pips ?? $this->calculatePips($entry, $exit)),
            'profit_loss' => $profitLoss,
            'result' => $result,
            'notes' => $trade->notes ?? null,
            'tp_inferred' => $result === 1,
            'sl_inferred' => $result === 2,
        ];
    }

    private function parseUploadedTrades(UploadedFile $file, string $pair): Collection
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return collect();
        }

        $headings = array_shift($rows);
        $columns = $this->mapHeadings($headings);
        $trades = collect();

        foreach ($rows as $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $trade = $this->rowToTrade($row, $columns, $pair);

            if ($trade) {
                $trades->push($trade);
            }
        }

        return $trades;
    }

    private function parseUploadedCandles(UploadedFile $file, string $pair): Collection
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return collect();
        }

        $headings = array_shift($rows);
        $columns = $this->mapCandleHeadings($headings);
        $candles = collect();

        foreach ($rows as $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $candle = $this->rowToCandle($row, $columns, $pair);

            if ($candle) {
                $candles->push($candle);
            }
        }

        return $candles
            ->sortBy('time')
            ->values()
            ->take(5000);
    }

    private function rowToTrade(array $row, array $columns, string $targetPair): ?array
    {
        $pair = strtoupper(trim((string) ($this->rowValue($row, $columns, 'pair') ?: $targetPair)));

        if ($pair !== $targetPair) {
            return null;
        }

        $entry = $this->toFloat($this->rowValue($row, $columns, 'entry_price'));
        $exit = $this->toFloat($this->rowValue($row, $columns, 'exit_price'));

        if ($entry === null || $exit === null) {
            return null;
        }

        $direction = $this->parseDirection($this->rowValue($row, $columns, 'direction'));
        $lotSize = $this->toFloat($this->rowValue($row, $columns, 'lot_size')) ?? 0;
        $pips = $this->toFloat($this->rowValue($row, $columns, 'pips')) ?? $this->calculatePips($entry, $exit);
        $profitLoss = $this->toFloat($this->rowValue($row, $columns, 'profit_loss'));
        $result = $this->parseResult($this->rowValue($row, $columns, 'result'));

        if ($result === null) {
            $result = $this->inferResult($direction, $entry, $exit, $profitLoss);
        }

        if ($profitLoss === null) {
            $profitLoss = $pips * $lotSize * 10;
            $profitLoss = $result === 2 ? -abs($profitLoss) : ($result === 1 ? abs($profitLoss) : 0);
        }

        $stopLoss = $this->toFloat($this->rowValue($row, $columns, 'stop_loss'));
        $takeProfit = $this->toFloat($this->rowValue($row, $columns, 'take_profit'));
        $slInferred = false;
        $tpInferred = false;

        if ($stopLoss === null && $result === 2) {
            $stopLoss = $exit;
            $slInferred = true;
        }

        if ($takeProfit === null && $result === 1) {
            $takeProfit = $exit;
            $tpInferred = true;
        }

        return [
            'source' => 'Excel Upload',
            'opened_at' => $this->parseDateValue($this->rowValue($row, $columns, 'open_date')),
            'closed_at' => $this->parseDateValue($this->rowValue($row, $columns, 'close_date')),
            'pair' => $pair,
            'direction' => $direction,
            'entry_price' => $entry,
            'exit_price' => $exit,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'lot_size' => $lotSize,
            'pips' => round($pips, 2),
            'profit_loss' => round($profitLoss, 2),
            'result' => $result,
            'notes' => $this->rowValue($row, $columns, 'notes'),
            'tp_inferred' => $tpInferred,
            'sl_inferred' => $slInferred,
        ];
    }

    private function rowToCandle(array $row, array $columns, string $targetPair): ?array
    {
        $pair = strtoupper(trim((string) ($this->rowValue($row, $columns, 'pair') ?: $targetPair)));

        if ($pair !== $targetPair) {
            return null;
        }

        $time = $this->parseDateValue($this->rowValue($row, $columns, 'time'));
        $open = $this->toFloat($this->rowValue($row, $columns, 'open'));
        $high = $this->toFloat($this->rowValue($row, $columns, 'high'));
        $low = $this->toFloat($this->rowValue($row, $columns, 'low'));
        $close = $this->toFloat($this->rowValue($row, $columns, 'close'));

        if (! $time instanceof Carbon || $open === null || $high === null || $low === null || $close === null) {
            return null;
        }

        return [
            'time' => $time,
            'open' => $open,
            'high' => max($high, $open, $close),
            'low' => min($low, $open, $close),
            'close' => $close,
            'volume' => $this->toFloat($this->rowValue($row, $columns, 'volume')) ?? 0,
        ];
    }

    private function mapHeadings(array $headings): array
    {
        $columns = [];

        foreach ($headings as $column => $heading) {
            $key = $this->canonicalHeading($heading);

            if ($key) {
                $columns[$key] = $column;
            }
        }

        return $columns;
    }

    private function mapCandleHeadings(array $headings): array
    {
        $columns = [];

        foreach ($headings as $column => $heading) {
            $key = $this->canonicalCandleHeading($heading);

            if ($key) {
                $columns[$key] = $column;
            }
        }

        return $columns;
    }

    private function canonicalHeading($heading): ?string
    {
        $key = strtolower(trim((string) $heading));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim((string) $key, '_');

        $aliases = [
            'open_date' => ['open_date', 'open_time', 'opened_at', 'trade_date', 'date', 'entry_time'],
            'close_date' => ['close_date', 'close_time', 'closed_at', 'exit_time'],
            'pair' => ['pair', 'symbol', 'instrument', 'market'],
            'direction' => ['direction', 'side', 'type', 'order_type'],
            'entry_price' => ['entry_price', 'entry', 'open_price'],
            'exit_price' => ['exit_price', 'exit', 'close_price'],
            'stop_loss' => ['stop_loss', 'sl', 'stoploss', 's_l'],
            'take_profit' => ['take_profit', 'tp', 'takeprofit', 't_p', 'tp1'],
            'lot_size' => ['lot_size', 'lot', 'lots', 'volume'],
            'pips' => ['pips', 'pip'],
            'profit_loss' => ['profit_loss', 'p_l', 'pl', 'pnl', 'profit', 'net_profit'],
            'result' => ['result', 'outcome', 'status'],
            'notes' => ['notes', 'note', 'setup', 'comment', 'comments'],
        ];

        foreach ($aliases as $canonical => $values) {
            if (in_array($key, $values, true)) {
                return $canonical;
            }
        }

        return null;
    }

    private function canonicalCandleHeading($heading): ?string
    {
        $key = strtolower(trim((string) $heading));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim((string) $key, '_');

        $aliases = [
            'time' => ['time', 'date', 'datetime', 'date_time', 'timestamp', 'open_time', 'candle_time'],
            'pair' => ['pair', 'symbol', 'instrument', 'market'],
            'open' => ['open', 'o', 'open_price'],
            'high' => ['high', 'h', 'high_price'],
            'low' => ['low', 'l', 'low_price'],
            'close' => ['close', 'c', 'close_price'],
            'volume' => ['volume', 'vol', 'tick_volume'],
        ];

        foreach ($aliases as $canonical => $values) {
            if (in_array($key, $values, true)) {
                return $canonical;
            }
        }

        return null;
    }

    private function rowValue(array $row, array $columns, string $key)
    {
        if (! isset($columns[$key])) {
            return null;
        }

        $value = $row[$columns[$key]] ?? null;

        return is_string($value) ? trim($value) : $value;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function enrichTrade(array $trade): array
    {
        $trade['direction_label'] = ((int) $trade['direction']) === 2 ? 'Sell' : 'Buy';
        $trade['result_label'] = match ((int) $trade['result']) {
            1 => 'Win',
            2 => 'Loss',
            3 => 'Break Even',
            default => 'N/A',
        };
        $trade['risk_reward'] = $this->riskRewardRatio($trade);
        $trade['r_multiple'] = $this->rMultiple($trade);
        $trade['tp_hit'] = $this->tpHit($trade);
        $trade['sl_hit'] = $this->slHit($trade);

        return $trade;
    }

    private function buildSummary(Collection $trades): array
    {
        $totalTrades = $trades->count();
        $wins = $trades->where('profit_loss', '>', 0);
        $losses = $trades->where('profit_loss', '<', 0);
        $breakeven = $trades->where('profit_loss', '=', 0);
        $grossProfit = round($wins->sum('profit_loss'), 2);
        $grossLoss = round(abs($losses->sum('profit_loss')), 2);
        $netProfit = round($trades->sum('profit_loss'), 2);
        $closedTrades = $wins->count() + $losses->count();
        $rMultiples = $trades->pluck('r_multiple')->filter(fn ($value) => is_numeric($value));
        $riskRewards = $trades->pluck('risk_reward')->filter(fn ($value) => is_numeric($value));
        $holdMinutes = $trades
            ->filter(fn (array $trade): bool => $trade['opened_at'] instanceof Carbon && $trade['closed_at'] instanceof Carbon)
            ->map(fn (array $trade): int => max(0, $trade['opened_at']->diffInMinutes($trade['closed_at'])));

        return [
            'total_trades' => $totalTrades,
            'wins' => $wins->count(),
            'losses' => $losses->count(),
            'breakeven' => $breakeven->count(),
            'win_rate' => $closedTrades > 0 ? round(($wins->count() / $closedTrades) * 100, 2) : 0,
            'gross_profit' => $grossProfit,
            'gross_loss' => $grossLoss,
            'net_profit' => $netProfit,
            'profit_factor' => $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : ($grossProfit > 0 ? 'Perfect' : 'N/A'),
            'expectancy' => $totalTrades > 0 ? round($netProfit / $totalTrades, 2) : 0,
            'average_r' => $rMultiples->isNotEmpty() ? round($rMultiples->avg(), 2) : null,
            'average_rr' => $riskRewards->isNotEmpty() ? round($riskRewards->avg(), 2) : null,
            'max_drawdown' => $this->maxDrawdown($trades->pluck('profit_loss')),
            'tp_hits' => $trades->where('tp_hit', true)->count(),
            'sl_hits' => $trades->where('sl_hit', true)->count(),
            'tp_rate' => $totalTrades > 0 ? round(($trades->where('tp_hit', true)->count() / $totalTrades) * 100, 2) : 0,
            'sl_rate' => $totalTrades > 0 ? round(($trades->where('sl_hit', true)->count() / $totalTrades) * 100, 2) : 0,
            'average_hold_minutes' => $holdMinutes->isNotEmpty() ? (int) round($holdMinutes->avg()) : 0,
        ];
    }

    private function buildChartTrades(Collection $trades): Collection
    {
        return $trades->values()->map(function (array $trade, int $index): array {
            $prices = collect([
                $trade['entry_price'],
                $trade['exit_price'],
                $trade['stop_loss'],
                $trade['take_profit'],
            ])->filter(fn ($price) => is_numeric($price))->values();
            $high = (float) $prices->max();
            $low = (float) $prices->min();

            if ($high === $low) {
                $high += 1;
                $low -= 1;
            }

            return [
                'index' => $index,
                'time' => optional($trade['opened_at'])->format('M d H:i') ?? 'Trade ' . ($index + 1),
                'opened_timestamp' => $trade['opened_at'] instanceof Carbon ? $trade['opened_at']->timestamp : null,
                'closed_timestamp' => $trade['closed_at'] instanceof Carbon ? $trade['closed_at']->timestamp : null,
                'source' => $trade['source'],
                'direction' => $trade['direction_label'],
                'result' => $trade['result_label'],
                'entry' => round((float) $trade['entry_price'], 2),
                'exit' => round((float) $trade['exit_price'], 2),
                'tp' => $trade['take_profit'] !== null ? round((float) $trade['take_profit'], 2) : null,
                'sl' => $trade['stop_loss'] !== null ? round((float) $trade['stop_loss'], 2) : null,
                'open' => round((float) $trade['entry_price'], 2),
                'close' => round((float) $trade['exit_price'], 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'profit_loss' => round((float) $trade['profit_loss'], 2),
                'lot_size' => round((float) $trade['lot_size'], 2),
                'pips' => round((float) $trade['pips'], 2),
                'risk_reward' => $trade['risk_reward'],
                'r_multiple' => $trade['r_multiple'],
                'tp_inferred' => (bool) ($trade['tp_inferred'] ?? false),
                'sl_inferred' => (bool) ($trade['sl_inferred'] ?? false),
            ];
        });
    }

    private function buildReplayCandles(Collection $candles): Collection
    {
        return $candles
            ->filter(fn (array $candle): bool => $candle['time'] instanceof Carbon)
            ->sortBy('time')
            ->values()
            ->map(function (array $candle, int $index): array {
                return [
                    'globalIndex' => $index,
                    'time' => $candle['time']->format('M d H:i'),
                    'timestamp' => $candle['time']->timestamp,
                    'open' => round((float) $candle['open'], 2),
                    'high' => round((float) $candle['high'], 2),
                    'low' => round((float) $candle['low'], 2),
                    'close' => round((float) $candle['close'], 2),
                    'volume' => round((float) ($candle['volume'] ?? 0), 2),
                ];
            });
    }

    private function riskRewardRatio(array $trade): ?float
    {
        $entry = (float) $trade['entry_price'];
        $tp = $trade['take_profit'];
        $sl = $trade['stop_loss'];

        if (! is_numeric($tp) || ! is_numeric($sl)) {
            return null;
        }

        $direction = (int) $trade['direction'];
        $risk = $direction === 2 ? ((float) $sl - $entry) : ($entry - (float) $sl);
        $reward = $direction === 2 ? ($entry - (float) $tp) : ((float) $tp - $entry);

        return $risk > 0 && $reward > 0 ? round($reward / $risk, 2) : null;
    }

    private function rMultiple(array $trade): ?float
    {
        $entry = (float) $trade['entry_price'];
        $exit = (float) $trade['exit_price'];
        $sl = $trade['stop_loss'];

        if (! is_numeric($sl)) {
            return null;
        }

        $direction = (int) $trade['direction'];
        $risk = $direction === 2 ? ((float) $sl - $entry) : ($entry - (float) $sl);
        $move = $direction === 2 ? ($entry - $exit) : ($exit - $entry);

        return $risk > 0 ? round($move / $risk, 2) : null;
    }

    private function tpHit(array $trade): bool
    {
        if (! is_numeric($trade['take_profit'])) {
            return false;
        }

        $direction = (int) $trade['direction'];
        $exit = (float) $trade['exit_price'];
        $tp = (float) $trade['take_profit'];

        return $direction === 2 ? $exit <= $tp : $exit >= $tp;
    }

    private function slHit(array $trade): bool
    {
        if (! is_numeric($trade['stop_loss'])) {
            return false;
        }

        $direction = (int) $trade['direction'];
        $exit = (float) $trade['exit_price'];
        $sl = (float) $trade['stop_loss'];

        return $direction === 2 ? $exit >= $sl : $exit <= $sl;
    }

    private function maxDrawdown(Collection $profits): float
    {
        $running = 0;
        $peak = 0;
        $maxDrawdown = 0;

        foreach ($profits as $profit) {
            $running += (float) $profit;
            $peak = max($peak, $running);
            $maxDrawdown = max($maxDrawdown, $peak - $running);
        }

        return round($maxDrawdown, 2);
    }

    private function calculatePips(float $entry, float $exit): float
    {
        $pair = TradingPair::whereRaw('UPPER(symbol) = ?', ['XAUUSD'])->first();
        $pipFactor = (float) ($pair->pip_factor ?? 0.1);

        if ($pipFactor <= 0) {
            $pipFactor = 0.1;
        }

        return round(abs($exit - $entry) / $pipFactor, (int) ($pair->pip_decimal ?? 1));
    }

    private function parseDirection($value): int
    {
        if (is_numeric($value)) {
            return (int) $value === 2 ? 2 : 1;
        }

        $value = strtolower(trim((string) $value));

        if (str_contains($value, 'sell') || str_contains($value, 'short')) {
            return 2;
        }

        return 1;
    }

    private function parseResult($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $numeric = (int) $value;

            return in_array($numeric, [1, 2, 3], true) ? $numeric : null;
        }

        $value = strtolower(trim((string) $value));

        if (str_contains($value, 'win') || str_contains($value, 'profit') || $value === 'w') {
            return 1;
        }

        if (str_contains($value, 'loss') || str_contains($value, 'lose') || $value === 'l') {
            return 2;
        }

        if (str_contains($value, 'break') || str_contains($value, 'be') || str_contains($value, 'flat')) {
            return 3;
        }

        return null;
    }

    private function inferResult(int $direction, float $entry, float $exit, ?float $profitLoss): int
    {
        if ($profitLoss !== null) {
            if ($profitLoss > 0) {
                return 1;
            }

            if ($profitLoss < 0) {
                return 2;
            }
        }

        $move = $direction === 2 ? $entry - $exit : $exit - $entry;

        return $move > 0 ? 1 : ($move < 0 ? 2 : 3);
    }

    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function parseDateValue($value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value) && (float) $value > 25000) {
            return Carbon::instance(SpreadsheetDate::excelToDateTimeObject((float) $value));
        }

        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTraderId($value): ?int
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_INT) ? (int) $value : null;
    }

    private function journalTraderIds(): array
    {
        $ids = collect();

        if (Schema::hasTable('trading_journals') && Schema::hasColumn('trading_journals', 'user_id')) {
            $ids = $ids->merge(TradingJournal::query()->whereNotNull('user_id')->distinct()->pluck('user_id'));
        }

        if (Schema::hasTable('trading_journals_backup') && Schema::hasColumn('trading_journals_backup', 'user_id')) {
            $ids = $ids->merge(TradingJournalBackup::query()->whereNotNull('user_id')->distinct()->pluck('user_id'));
        }

        return $ids->filter()->unique()->values()->all();
    }
}
