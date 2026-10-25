<?php

namespace Tests\Unit;

use App\Imports\TradesImport;
use App\Models\TradingPair;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TradesImportTest extends TestCase
{
    public function test_it_maps_mt5_position_rows_into_journal_payloads(): void
    {
        $import = new TestableTradesImport(collect([
            new TradingPair([
                'symbol' => 'XAUUSD',
                'pip_factor' => 0.1,
                'pip_decimal' => 2,
            ]),
        ]));

        $header = ['Time', 'Position', 'Symbol', 'Type', 'Volume', 'Price', 'S / L', 'T / P', 'Time', 'Price', 'Commission', 'Swap', 'Profit'];
        $map = $import->mapMt5Header($header);

        $payload = $import->payloadFromMt5([
            '2026.05.04 05:00:18',
            '377626197',
            'XAUUSD-VIP',
            'buy',
            '0.01',
            '4 616.00',
            '4 586.00',
            '4 645.50',
            '2026.05.04 09:38:54',
            '4 586.00',
            '0.00',
            '-0 000.83',
            '-0 030.00',
        ], $map);

        $this->assertSame('trade', $payload['type']);
        $this->assertSame('closed', $payload['status']);
        $this->assertSame('manual_close', $payload['close_handling']);
        $this->assertSame('2026-05-04 10:00:18', $payload['open_date']);
        $this->assertSame('2026-05-04 14:38:54', $payload['close_date']);
        $this->assertSame('XAUUSD', $payload['pair']);
        $this->assertSame(1, $payload['direction']);
        $this->assertSame(4616.0, $payload['entry_price']);
        $this->assertSame(4586.0, $payload['exit_price']);
        $this->assertSame(0.01, $payload['lot_size']);
        $this->assertSame(300.0, $payload['pips']);
        $this->assertSame(-30.83, $payload['profit_loss']);
        $this->assertSame(2, $payload['result']);
        $this->assertStringContainsString('MT5 Position #377626197', $payload['notes']);
    }

    public function test_it_keeps_template_profit_loss_when_provided(): void
    {
        $import = new TestableTradesImport(collect([
            new TradingPair([
                'symbol' => 'EURUSD',
                'pip_factor' => 0.0001,
                'pip_decimal' => 1,
            ]),
        ]), 'malaysia');

        $header = ['open_date', 'close_date', 'pair', 'direction', 'entry_price', 'exit_price', 'lot_size', 'pips', 'profit_loss', 'result', 'notes'];
        $map = $import->mapTemplateHeader($header);

        $payload = $import->payloadFromTemplate([
            '2026-05-04 10:00:00',
            '2026-05-04 11:00:00',
            'EURUSD',
            'sell',
            '1.1000',
            '1.0950',
            '0.20',
            '',
            '125.50',
            '',
            'Imported test row',
        ], $map);

        $this->assertSame('2026-05-04 10:00:00', $payload['open_date']);
        $this->assertSame('closed', $payload['status']);
        $this->assertSame('manual_close', $payload['close_handling']);
        $this->assertSame('EURUSD', $payload['pair']);
        $this->assertSame(2, $payload['direction']);
        $this->assertSame(50.0, $payload['pips']);
        $this->assertSame(125.50, $payload['profit_loss']);
        $this->assertSame(1, $payload['result']);
        $this->assertSame('Imported test row', $payload['notes']);
    }
}

class TestableTradesImport extends TradesImport
{
    public function __construct(private Collection $pairs, ?string $timeMode = 'mt5')
    {
        parent::__construct(1, $timeMode, 180);
    }

    public function mapMt5Header(array $values): ?array
    {
        return $this->mt5PositionsHeaderMap($values);
    }

    public function mapTemplateHeader(array $values): ?array
    {
        return $this->templateHeaderMap($values);
    }

    public function payloadFromMt5(array $values, array $map): ?array
    {
        return $this->payloadFromMt5Row($values, $map, 8);
    }

    public function payloadFromTemplate(array $values, array $map): ?array
    {
        return $this->payloadFromTemplateRow($values, $map, 2);
    }

    protected function tradingPairs(): Collection
    {
        return $this->pairs;
    }
}
