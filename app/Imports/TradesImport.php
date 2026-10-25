<?php

namespace App\Imports;

use App\Models\TradingJournal;
use App\Models\TradingJournalBackup;
use App\Models\TradingPair;
use App\Services\TradingJournalTimeService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TradesImport implements ToCollection, SkipsEmptyRows
{
    protected int $userId;

    protected string $defaultTimeInputTimezone;

    protected ?int $defaultTimeInputOffsetMinutes;

    protected int $importedCount = 0;

    protected int $updatedCount = 0;

    protected int $deduplicatedCount = 0;

    protected int $skippedCount = 0;

    protected array $skippedRows = [];

    protected ?Collection $tradingPairs = null;

    public function __construct($userId, ?string $defaultTimeInputTimezone = null, $defaultTimeInputOffsetMinutes = null)
    {
        $timeService = app(TradingJournalTimeService::class);

        $this->userId = (int) $userId;
        $this->defaultTimeInputTimezone = $timeService->normalizeMode(
            $defaultTimeInputTimezone ?: TradingJournalTimeService::TIMEZONE_MT5
        );
        $this->defaultTimeInputOffsetMinutes = $timeService->normalizeOffset(
            $defaultTimeInputOffsetMinutes,
            $this->defaultTimeInputTimezone
        );
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $context = $this->detectImportContext($rows);

        if (! $context) {
            $this->skipRow(0, 'No supported heading row was found. Upload the app template or an MT5 trade history report with a Positions table.');

            return;
        }

        foreach ($rows->slice($context['header_row'] + 1) as $rowIndex => $row) {
            $rowNumber = ((int) $rowIndex) + 1;
            $values = $this->rowValues($row);

            if ($this->rowIsEmpty($values)) {
                continue;
            }

            if ($context['format'] === 'mt5' && $this->isMt5SectionBreak($values)) {
                break;
            }

            $payload = $context['format'] === 'mt5'
                ? $this->payloadFromMt5Row($values, $context['map'], $rowNumber)
                : $this->payloadFromTemplateRow($values, $context['map'], $rowNumber);

            if (! $payload) {
                continue;
            }

            $existingMt5Position = $this->findDuplicateMt5Position($payload);

            if ($existingMt5Position) {
                $removed = $this->cleanupTradeFingerprintDuplicates($payload, $existingMt5Position->id);

                if ($removed > 0) {
                    $this->deduplicatedCount += $removed;
                }

                $this->skipRow($rowNumber, 'MT5 position already exists in your journal.');

                continue;
            }

            if ($this->archivedMt5PositionExists($payload)) {
                $this->skipRow($rowNumber, 'MT5 position already exists in an archived phase journal.');

                continue;
            }

            $existingTrade = $this->findDuplicateTradeFingerprint($payload);

            if ($existingTrade) {
                $existingTrade->update($payload);
                $this->updatedCount++;

                continue;
            }

            $journal = TradingJournal::create($payload);
            $this->cleanupTradeFingerprintDuplicates($payload, $journal->id);
            $this->importedCount++;
        }
    }

    public function importedCount(): int
    {
        return $this->importedCount;
    }

    public function updatedCount(): int
    {
        return $this->updatedCount;
    }

    public function persistedCount(): int
    {
        return $this->importedCount + $this->updatedCount;
    }

    public function deduplicatedCount(): int
    {
        return $this->deduplicatedCount;
    }

    public function skippedCount(): int
    {
        return $this->skippedCount;
    }

    public function skippedRows(): array
    {
        return $this->skippedRows;
    }

    public function skippedSummary(int $limit = 5): string
    {
        if ($this->skippedCount === 0) {
            return '';
        }

        $examples = collect($this->skippedRows)
            ->take($limit)
            ->map(function (array $row): string {
                return 'row '.$row['row'].': '.$row['reason'];
            })
            ->implode('; ');

        $summary = $this->skippedCount.' row(s) skipped';

        return $examples
            ? $summary.' ('.$examples.($this->skippedCount > $limit ? '; ...' : '').')'
            : $summary.'.';
    }

    protected function detectImportContext(Collection $rows): ?array
    {
        foreach ($rows as $rowIndex => $row) {
            $values = $this->rowValues($row);
            $map = $this->templateHeaderMap($values);

            if ($map) {
                return [
                    'format' => 'template',
                    'header_row' => (int) $rowIndex,
                    'map' => $map,
                ];
            }
        }

        foreach ($rows as $rowIndex => $row) {
            $values = $this->rowValues($row);
            $map = $this->mt5PositionsHeaderMap($values);

            if ($map) {
                return [
                    'format' => 'mt5',
                    'header_row' => (int) $rowIndex,
                    'map' => $map,
                ];
            }
        }

        return null;
    }

    protected function templateHeaderMap(array $values): ?array
    {
        $lookup = $this->headingLookup($values);

        $hasOpenDate = $this->firstHeadingIndex($lookup, ['open_date', 'open_time']) !== null;
        $hasCloseDate = $this->firstHeadingIndex($lookup, ['close_date', 'close_time']);
        $hasPair = $this->firstHeadingIndex($lookup, ['pair', 'symbol']);

        if (! $hasOpenDate || $hasCloseDate === null || $hasPair === null) {
            return null;
        }

        return [
            'open_date' => $this->firstHeadingIndex($lookup, ['open_date', 'open_time']),
            'close_date' => $this->firstHeadingIndex($lookup, ['close_date', 'close_time']),
            'time_input_timezone' => $this->firstHeadingIndex($lookup, ['time_input_timezone', 'timezone', 'time_zone']),
            'time_input_offset_minutes' => $this->firstHeadingIndex($lookup, ['time_input_offset_minutes', 'mt5_offset_minutes', 'offset_minutes']),
            'pair' => $this->firstHeadingIndex($lookup, ['pair', 'symbol']),
            'direction' => $this->firstHeadingIndex($lookup, ['direction', 'type', 'order_type']),
            'entry_price' => $this->firstHeadingIndex($lookup, ['entry_price', 'open_price', 'price_open']),
            'exit_price' => $this->firstHeadingIndex($lookup, ['exit_price', 'close_price', 'price_close']),
            'lot_size' => $this->firstHeadingIndex($lookup, ['lot_size', 'lots', 'lot', 'volume']),
            'pips' => $this->firstHeadingIndex($lookup, ['pips', 'points']),
            'profit_loss' => $this->firstHeadingIndex($lookup, ['profit_loss', 'pnl', 'p_l', 'profit', 'net_profit']),
            'result' => $this->firstHeadingIndex($lookup, ['result', 'outcome']),
            'notes' => $this->firstHeadingIndex($lookup, ['notes', 'note', 'comment']),
            'status' => $this->firstHeadingIndex($lookup, ['status', 'trade_status']),
            'close_handling' => $this->firstHeadingIndex($lookup, ['close_handling', 'close_method']),
            'trading_reason_id' => $this->firstHeadingIndex($lookup, ['trading_reason_id', 'reason_id']),
            'emotion_before_entry' => $this->firstHeadingIndex($lookup, ['emotion_before_entry', 'emotion_before']),
            'emotion_during_trade' => $this->firstHeadingIndex($lookup, ['emotion_during_trade', 'emotion_during']),
            'emotion_after_trade' => $this->firstHeadingIndex($lookup, ['emotion_after_trade', 'emotion_after']),
            'confidence_level' => $this->firstHeadingIndex($lookup, ['confidence_level', 'confidence']),
            'discipline_rating' => $this->firstHeadingIndex($lookup, ['discipline_rating', 'discipline']),
            'mistake_tags' => $this->firstHeadingIndex($lookup, ['mistake_tags', 'mistakes']),
            'psychology_notes' => $this->firstHeadingIndex($lookup, ['psychology_notes', 'reflection']),
        ];
    }

    protected function mt5PositionsHeaderMap(array $values): ?array
    {
        $normalized = array_map(fn ($value) => $this->normalizeHeading($value), $values);
        $timeIndexes = $this->allHeadingIndexes($normalized, 'time');
        $priceIndexes = $this->allHeadingIndexes($normalized, 'price');
        $positionIndex = array_search('position', $normalized, true);
        $symbolIndex = array_search('symbol', $normalized, true);
        $typeIndex = array_search('type', $normalized, true);
        $volumeIndex = array_search('volume', $normalized, true);
        $profitIndex = array_search('profit', $normalized, true);

        if (
            count($timeIndexes) < 2
            || count($priceIndexes) < 2
            || $positionIndex === false
            || $symbolIndex === false
            || $typeIndex === false
            || $volumeIndex === false
            || $profitIndex === false
        ) {
            return null;
        }

        return [
            'open_date' => $timeIndexes[0],
            'position' => $positionIndex,
            'pair' => $symbolIndex,
            'direction' => $typeIndex,
            'lot_size' => $volumeIndex,
            'entry_price' => $priceIndexes[0],
            'stop_loss' => array_search('s_l', $normalized, true),
            'take_profit' => array_search('t_p', $normalized, true),
            'close_date' => $timeIndexes[1],
            'exit_price' => $priceIndexes[1],
            'commission' => array_search('commission', $normalized, true),
            'swap' => array_search('swap', $normalized, true),
            'profit' => $profitIndex,
        ];
    }

    protected function payloadFromMt5Row(array $values, array $map, int $rowNumber): ?array
    {
        $sourceSymbol = $this->stringValue($this->valueAt($values, $map['pair']));
        $tradingPair = $this->resolveTradingPair($sourceSymbol);

        if (! $tradingPair) {
            $this->skipRow($rowNumber, 'Unknown trading pair "'.($sourceSymbol ?: 'blank').'". Add the pair before importing.');

            return null;
        }

        $direction = $this->normalizeDirection($this->valueAt($values, $map['direction']));
        $entryPrice = $this->parseNumber($this->valueAt($values, $map['entry_price']));
        $exitPrice = $this->parseNumber($this->valueAt($values, $map['exit_price']));
        $lotSize = $this->parseNumber($this->valueAt($values, $map['lot_size']));
        $commission = $this->parseNumber($this->valueAt($values, $map['commission'])) ?? 0.0;
        $swap = $this->parseNumber($this->valueAt($values, $map['swap'])) ?? 0.0;
        $profit = $this->parseNumber($this->valueAt($values, $map['profit']));
        $position = $this->stringValue($this->valueAt($values, $map['position']));
        $openDate = $this->normalizeDateValue($this->valueAt($values, $map['open_date']));
        $closeDate = $this->normalizeDateValue($this->valueAt($values, $map['close_date']));

        if ($direction === null || $entryPrice === null || $exitPrice === null || $lotSize === null || $profit === null || ! $openDate || ! $closeDate) {
            $this->skipRow($rowNumber, 'Required MT5 position fields are incomplete.');

            return null;
        }

        $timeService = app(TradingJournalTimeService::class);
        $timeline = $timeService->toMalaysiaTimeline(
            $openDate,
            $closeDate,
            $this->defaultTimeInputTimezone,
            $this->defaultTimeInputOffsetMinutes
        );

        if (! $timeline['is_valid']) {
            $this->skipRow($rowNumber, 'Close time must be after open time.');

            return null;
        }

        $netProfitLoss = round($profit + $commission + $swap, 2);
        $pips = $this->calculatePips($entryPrice, $exitPrice, $tradingPair);
        $notes = $this->buildMt5Notes($position, $sourceSymbol, [
            'commission' => $commission,
            'swap' => $swap,
            'profit' => $profit,
            'stop_loss' => $this->parseNumber($this->valueAt($values, $map['stop_loss'])),
            'take_profit' => $this->parseNumber($this->valueAt($values, $map['take_profit'])),
        ]);

        return [
            'user_id' => $this->userId,
            'type' => 'trade',
            'status' => TradingJournal::STATUS_CLOSED,
            'close_handling' => TradingJournal::CLOSE_HANDLING_MANUAL,
            'time_input_timezone' => $this->defaultTimeInputTimezone,
            'time_input_offset_minutes' => $this->defaultTimeInputOffsetMinutes,
            'open_date' => $timeline['open_date'],
            'close_date' => $timeline['close_date'],
            'pair' => $tradingPair->symbol,
            'direction' => $direction,
            'entry_price' => $entryPrice,
            'exit_price' => $exitPrice,
            'lot_size' => $lotSize,
            'pips' => $pips,
            'profit_loss' => $netProfitLoss,
            'result' => $this->resultFromProfitLoss($netProfitLoss),
            'notes' => $notes,
        ];
    }

    protected function payloadFromTemplateRow(array $values, array $map, int $rowNumber): ?array
    {
        $sourceSymbol = $this->stringValue($this->valueAt($values, $map['pair']));
        $tradingPair = $this->resolveTradingPair($sourceSymbol);

        if (! $tradingPair) {
            $this->skipRow($rowNumber, 'Unknown trading pair "'.($sourceSymbol ?: 'blank').'". Add the pair before importing.');

            return null;
        }

        $timeService = app(TradingJournalTimeService::class);
        $timeInputTimezone = $timeService->normalizeMode(
            $this->stringValue($this->valueAt($values, $map['time_input_timezone'])) ?: $this->defaultTimeInputTimezone
        );
        $timeInputOffsetMinutes = $timeService->normalizeOffset(
            $this->valueAt($values, $map['time_input_offset_minutes']) ?? $this->defaultTimeInputOffsetMinutes,
            $timeInputTimezone
        );
        $openDate = $this->normalizeDateValue($this->valueAt($values, $map['open_date']));
        $closeDate = $this->normalizeDateValue($this->valueAt($values, $map['close_date']));
        $timeline = $timeService->toMalaysiaTimeline($openDate, $closeDate, $timeInputTimezone, $timeInputOffsetMinutes);

        if (! $timeline['is_valid']) {
            $this->skipRow($rowNumber, 'Close time must be after open time.');

            return null;
        }

        $direction = $this->normalizeDirection($this->valueAt($values, $map['direction']));
        $entryPrice = $this->parseNumber($this->valueAt($values, $map['entry_price']));
        $exitPrice = $this->parseNumber($this->valueAt($values, $map['exit_price']));
        $lotSize = $this->parseNumber($this->valueAt($values, $map['lot_size']));

        if ($direction === null || $entryPrice === null || $exitPrice === null || $lotSize === null) {
            $this->skipRow($rowNumber, 'Required trade fields are incomplete.');

            return null;
        }

        $pips = $this->parseNumber($this->valueAt($values, $map['pips']));
        $pips = $pips !== null ? round($pips, (int) ($tradingPair->pip_decimal ?? 0)) : $this->calculatePips($entryPrice, $exitPrice, $tradingPair);
        $profitLoss = $this->parseNumber($this->valueAt($values, $map['profit_loss']));
        $result = $this->normalizeResult($this->valueAt($values, $map['result']), $profitLoss);

        if ($result === null && $profitLoss === null) {
            $result = $this->resultFromPriceMove($direction, $entryPrice, $exitPrice);
        }

        if ($profitLoss === null) {
            $profitLoss = $this->calculateProfitLoss($pips, $lotSize, $result);
        }

        return [
            'user_id' => $this->userId,
            'type' => 'trade',
            'status' => $this->normalizeStatus($this->valueAt($values, $map['status'])) ?? TradingJournal::STATUS_CLOSED,
            'close_handling' => $this->normalizeCloseHandling($this->valueAt($values, $map['close_handling'])) ?? TradingJournal::CLOSE_HANDLING_MANUAL,
            'time_input_timezone' => $timeInputTimezone,
            'time_input_offset_minutes' => $timeInputOffsetMinutes,
            'open_date' => $timeline['open_date'],
            'close_date' => $timeline['close_date'],
            'pair' => $tradingPair->symbol,
            'direction' => $direction,
            'entry_price' => $entryPrice,
            'exit_price' => $exitPrice,
            'lot_size' => $lotSize,
            'pips' => $pips,
            'profit_loss' => round($profitLoss, 2),
            'result' => $result ?? $this->resultFromProfitLoss($profitLoss),
            'notes' => $this->stringValue($this->valueAt($values, $map['notes'])) ?: null,
            'trading_reason_id' => $this->parseInteger($this->valueAt($values, $map['trading_reason_id'])),
            'emotion_before_entry' => $this->stringValue($this->valueAt($values, $map['emotion_before_entry'])) ?: null,
            'emotion_during_trade' => $this->stringValue($this->valueAt($values, $map['emotion_during_trade'])) ?: null,
            'emotion_after_trade' => $this->stringValue($this->valueAt($values, $map['emotion_after_trade'])) ?: null,
            'confidence_level' => $this->parseInteger($this->valueAt($values, $map['confidence_level'])),
            'discipline_rating' => $this->parseInteger($this->valueAt($values, $map['discipline_rating'])),
            'mistake_tags' => $this->normalizeMistakeTags($this->valueAt($values, $map['mistake_tags'])),
            'psychology_notes' => $this->stringValue($this->valueAt($values, $map['psychology_notes'])) ?: null,
        ];
    }

    protected function rowValues($row): array
    {
        return array_values($row instanceof Collection ? $row->toArray() : (array) $row);
    }

    protected function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->stringValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    protected function headingLookup(array $values): array
    {
        $lookup = [];

        foreach ($values as $index => $value) {
            $heading = $this->normalizeHeading($value);

            if ($heading !== '' && ! array_key_exists($heading, $lookup)) {
                $lookup[$heading] = $index;
            }
        }

        return $lookup;
    }

    protected function normalizeHeading($value): string
    {
        $heading = strtolower(trim((string) $value));
        $heading = str_replace(['/', '\\'], ' ', $heading);
        $heading = preg_replace('/[^a-z0-9]+/', '_', $heading) ?? '';

        return trim($heading, '_');
    }

    protected function firstHeadingIndex(array $lookup, array $aliases): ?int
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $lookup)) {
                return (int) $lookup[$alias];
            }
        }

        return null;
    }

    protected function allHeadingIndexes(array $headings, string $needle): array
    {
        $indexes = [];

        foreach ($headings as $index => $heading) {
            if ($heading === $needle) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    protected function valueAt(array $values, $index)
    {
        if ($index === null || $index === false) {
            return null;
        }

        return $values[(int) $index] ?? null;
    }

    protected function stringValue($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return trim((string) $value);
    }

    protected function parseNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $negative = false;

        if (preg_match('/^\((.*)\)$/', $text, $matches)) {
            $negative = true;
            $text = $matches[1];
        }

        if (! preg_match('/-?\d[\d\s,]*(?:\.\d+)?/', $text, $matches)) {
            return null;
        }

        $number = str_replace(["\xc2\xa0", ' '], '', $matches[0]);

        if (str_contains($number, ',') && str_contains($number, '.')) {
            $number = str_replace(',', '', $number);
        } elseif (str_contains($number, ',') && ! str_contains($number, '.')) {
            $number = str_replace(',', '.', $number);
        }

        $number = preg_replace('/[^0-9.\-]/', '', $number) ?? '';

        if ($number === '' || $number === '-') {
            return null;
        }

        $parsed = (float) $number;

        return $negative ? -abs($parsed) : $parsed;
    }

    protected function parseInteger($value): ?int
    {
        $number = $this->parseNumber($value);

        return $number === null ? null : (int) $number;
    }

    protected function normalizeDateValue($value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value) && (float) $value > 20000) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return preg_replace('/^(\d{4})\.(\d{1,2})\.(\d{1,2})/', '$1-$2-$3', $text) ?: $text;
    }

    protected function normalizeDirection($value): ?int
    {
        $direction = strtolower($this->stringValue($value));

        return match (true) {
            in_array($direction, ['1', 'buy', 'long'], true) => 1,
            in_array($direction, ['2', 'sell', 'short'], true) => 2,
            str_contains($direction, 'buy') => 1,
            str_contains($direction, 'sell') => 2,
            default => null,
        };
    }

    protected function normalizeResult($value, ?float $profitLoss = null): ?int
    {
        $result = strtolower($this->stringValue($value));

        if ($result !== '') {
            return match (true) {
                in_array($result, ['1', 'win', 'profit'], true) => 1,
                in_array($result, ['2', 'loss', 'lose'], true) => 2,
                in_array($result, ['3', 'break_even', 'breakeven', 'break even', 'be'], true) => 3,
                default => null,
            };
        }

        return $profitLoss === null ? null : $this->resultFromProfitLoss($profitLoss);
    }

    protected function normalizeStatus($value): ?string
    {
        $status = strtolower(str_replace([' ', '-'], '_', $this->stringValue($value)));

        return match ($status) {
            TradingJournal::STATUS_DRAFT => TradingJournal::STATUS_DRAFT,
            TradingJournal::STATUS_OPEN, 'open_position' => TradingJournal::STATUS_OPEN,
            TradingJournal::STATUS_CLOSED, 'close', 'completed' => TradingJournal::STATUS_CLOSED,
            default => null,
        };
    }

    protected function normalizeCloseHandling($value): ?string
    {
        $handling = strtolower(str_replace([' ', '-'], '_', $this->stringValue($value)));

        return match ($handling) {
            TradingJournal::CLOSE_HANDLING_PRESET, 'preset', 'pre_set', 'follow_preset' => TradingJournal::CLOSE_HANDLING_PRESET,
            TradingJournal::CLOSE_HANDLING_MANUAL, 'manual' => TradingJournal::CLOSE_HANDLING_MANUAL,
            default => null,
        };
    }

    protected function normalizeMistakeTags($value): ?array
    {
        $text = $this->stringValue($value);

        if ($text === '') {
            return null;
        }

        return collect(preg_split('/[,\n]+/', $text) ?: [])
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function resultFromProfitLoss(float $profitLoss): int
    {
        return match (true) {
            $profitLoss > 0 => 1,
            $profitLoss < 0 => 2,
            default => 3,
        };
    }

    protected function resultFromPriceMove(int $direction, float $entryPrice, float $exitPrice): int
    {
        if ($entryPrice === $exitPrice) {
            return 3;
        }

        return match ($direction) {
            1 => $exitPrice > $entryPrice ? 1 : 2,
            2 => $exitPrice < $entryPrice ? 1 : 2,
            default => 3,
        };
    }

    protected function calculatePips(float $entryPrice, float $exitPrice, TradingPair $tradingPair): float
    {
        $pipFactor = max((float) ($tradingPair->pip_factor ?? 1), 0.00000001);

        return round(abs($exitPrice - $entryPrice) / $pipFactor, (int) ($tradingPair->pip_decimal ?? 0));
    }

    protected function calculateProfitLoss(float $pips, float $lotSize, ?int $result): float
    {
        $profitLoss = $pips * $lotSize * 10;

        return match ((string) $result) {
            '1' => round(abs($profitLoss), 2),
            '2' => round(-abs($profitLoss), 2),
            '3' => 0.00,
            default => round($profitLoss, 2),
        };
    }

    protected function resolveTradingPair(string $symbol): ?TradingPair
    {
        $normalizedSymbol = $this->normalizePairSymbol($symbol);

        if ($normalizedSymbol === '') {
            return null;
        }

        $pairs = $this->tradingPairs();
        $exact = $pairs->first(fn (TradingPair $pair) => $this->normalizePairSymbol($pair->symbol) === $normalizedSymbol);

        if ($exact) {
            return $exact;
        }

        return $pairs
            ->sortByDesc(fn (TradingPair $pair) => strlen($this->normalizePairSymbol($pair->symbol)))
            ->first(function (TradingPair $pair) use ($normalizedSymbol): bool {
                $pairSymbol = $this->normalizePairSymbol($pair->symbol);

                return $pairSymbol !== ''
                    && (str_starts_with($normalizedSymbol, $pairSymbol) || str_starts_with($pairSymbol, $normalizedSymbol));
            });
    }

    protected function tradingPairs(): Collection
    {
        if ($this->tradingPairs === null) {
            $this->tradingPairs = TradingPair::query()->orderBy('symbol')->get();
        }

        return $this->tradingPairs;
    }

    protected function normalizePairSymbol(?string $symbol): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $symbol) ?? '');
    }

    protected function buildMt5Notes(string $position, string $sourceSymbol, array $details): string
    {
        $parts = [
            'Imported from MT5 report',
        ];

        if ($position !== '') {
            $parts[] = 'MT5 Position #'.$position;
        }

        if ($sourceSymbol !== '') {
            $parts[] = 'Source symbol: '.$sourceSymbol;
        }

        foreach ([
            'commission' => 'Commission',
            'swap' => 'Swap',
            'profit' => 'Gross profit',
            'stop_loss' => 'S/L',
            'take_profit' => 'T/P',
        ] as $key => $label) {
            if ($details[$key] !== null) {
                $parts[] = $label.': '.number_format((float) $details[$key], 2, '.', '');
            }
        }

        return implode('; ', $parts);
    }

    protected function findDuplicateMt5Position(array $payload): ?TradingJournal
    {
        $position = $this->mt5PositionFromPayload($payload);

        if ($position === null) {
            return null;
        }

        return TradingJournal::query()
            ->where('user_id', $this->userId)
            ->where('notes', 'like', '%MT5 Position #'.$position.'%')
            ->orderBy('id')
            ->first();
    }

    protected function archivedMt5PositionExists(array $payload): bool
    {
        $position = $this->mt5PositionFromPayload($payload);

        if ($position === null || ! Schema::hasTable('trading_journals_backup')) {
            return false;
        }

        return TradingJournalBackup::query()
            ->where('user_id', $this->userId)
            ->where('notes', 'like', '%MT5 Position #'.$position.'%')
            ->exists();
    }

    protected function mt5PositionFromPayload(array $payload): ?string
    {
        if (! preg_match('/MT5 Position #([^;]+)/', (string) ($payload['notes'] ?? ''), $matches)) {
            return null;
        }

        $position = trim($matches[1]);

        return $position !== '' ? $position : null;
    }

    protected function findDuplicateTradeFingerprint(array $payload): ?TradingJournal
    {
        return $this->tradeFingerprintQuery($payload)
            ->orderByRaw("CASE WHEN notes LIKE '%MT5 Position #%' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    protected function cleanupTradeFingerprintDuplicates(array $payload, int $keepId): int
    {
        $duplicates = $this->tradeFingerprintQuery($payload)
            ->where('id', '<>', $keepId)
            ->orderByRaw("CASE WHEN notes LIKE '%MT5 Position #%' THEN 1 ELSE 0 END")
            ->orderBy('id')
            ->get(['id']);

        if ($duplicates->isEmpty()) {
            return 0;
        }

        $duplicates->each(function ($row): void {
            TradingJournal::find($row->id)?->delete();
        });

        return $duplicates->count();
    }

    protected function tradeFingerprintQuery(array $payload)
    {
        return TradingJournal::query()
            ->where('user_id', $this->userId)
            ->where(function ($query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            })
            ->where('pair', $payload['pair'])
            ->where('direction', $payload['direction'])
            ->whereBetween('open_date', $this->dateToleranceWindow($payload['open_date']))
            ->whereBetween('close_date', $this->dateToleranceWindow($payload['close_date']))
            ->where('entry_price', $payload['entry_price'])
            ->where('exit_price', $payload['exit_price'])
            ->where('lot_size', $payload['lot_size']);
    }

    protected function dateToleranceWindow(string $date, int $seconds = 60): array
    {
        $date = Carbon::parse($date);

        return [
            $date->copy()->subSeconds($seconds)->format('Y-m-d H:i:s'),
            $date->copy()->addSeconds($seconds)->format('Y-m-d H:i:s'),
        ];
    }

    protected function isMt5SectionBreak(array $values): bool
    {
        $firstValue = null;

        foreach ($values as $value) {
            $text = $this->stringValue($value);

            if ($text !== '') {
                $firstValue = strtolower($text);
                break;
            }
        }

        return in_array($firstValue, ['orders', 'deals'], true);
    }

    protected function skipRow(int $rowNumber, string $reason): void
    {
        $this->skippedCount++;
        $this->skippedRows[] = [
            'row' => $rowNumber > 0 ? $rowNumber : 'n/a',
            'reason' => $reason,
        ];
    }
}
