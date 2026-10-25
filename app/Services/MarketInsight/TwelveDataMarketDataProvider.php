<?php

namespace App\Services\MarketInsight;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

final class TwelveDataMarketDataProvider implements MarketDataProvider
{
    public const INTERVALS = ['M15' => '15min', 'M30' => '30min', 'H1' => '1h', 'H4' => '4h', 'D1' => '1day'];

    private ?int $creditsLeft = null;

    private int $duplicatesDiscarded = 0;

    public function __construct(private readonly CandleValidator $validator)
    {
    }

    public function quote(): array
    {
        $data = $this->request('quote', ['symbol' => $this->symbol()]);
        if (($data['symbol'] ?? null) !== $this->symbol() || ! $this->positive($data['close'] ?? null)) {
            throw new MarketDataException('INVALID_DATA', 'XAUUSD market data is temporarily unavailable.');
        }
        $quotedAt = isset($data['last_quote_at']) && is_numeric($data['last_quote_at'])
            ? CarbonImmutable::createFromTimestampUTC((int) $data['last_quote_at']) : null;
        if ($quotedAt?->greaterThan(CarbonImmutable::now('UTC')->addMinute())) {
            throw new MarketDataException('INVALID_DATA', 'Provider quote timestamp is invalid.');
        }

        return [
            'price' => (string) $data['close'],
            'quote_at' => $quotedAt?->toDateTimeString(),
            'is_market_open' => isset($data['is_market_open']) && is_bool($data['is_market_open']) ? $data['is_market_open'] : null,
            'details' => array_filter([
                'currency' => 'USD', 'name' => $data['name'] ?? null,
                'open' => $this->optionalPrice($data['open'] ?? null),
                'high' => $this->optionalPrice($data['high'] ?? null),
                'low' => $this->optionalPrice($data['low'] ?? null),
                'previous_close' => $this->optionalPrice($data['previous_close'] ?? null),
                'change' => is_numeric($data['change'] ?? null) ? (string) $data['change'] : null,
                'percent_change' => is_numeric($data['percent_change'] ?? null) ? (string) $data['percent_change'] : null,
            ], static fn ($value) => $value !== null),
        ];
    }

    public function creditsLeft(): ?int
    {
        return $this->creditsLeft;
    }

    public function duplicatesDiscarded(): int
    {
        return $this->duplicatesDiscarded;
    }

    public function candles(string $timeframe, int $count = 300): array
    {
        $interval = self::INTERVALS[$timeframe] ?? throw new MarketDataException('UNSUPPORTED', 'Unsupported market timeframe.');
        $data = $this->request('time_series', [
            'symbol' => $this->symbol(), 'interval' => $interval,
            'outputsize' => min(5000, max(2, $count)),
            'timezone' => 'UTC', 'order' => 'asc',
        ]);
        if (($data['status'] ?? null) !== 'ok' || ($data['meta']['symbol'] ?? null) !== $this->symbol() ||
            ($data['meta']['interval'] ?? null) !== $interval || ! is_array($data['values'] ?? null)) {
            throw new MarketDataException('INVALID_DATA', 'Provider candle response is invalid.');
        }
        $candles = [];
        $this->duplicatesDiscarded = 0;
        $now = CarbonImmutable::now('UTC');
        $latency = (int) config('market_data.publish_latency_seconds');
        foreach ($data['values'] as $row) {
            if (! is_array($row) || ! isset($row['datetime']) || ! is_string($row['datetime']) ||
                ! preg_match($timeframe === 'D1' ? '/^\d{4}-\d{2}-\d{2}$/' : '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row['datetime'])) {
                throw new MarketDataException('INVALID_DATA', 'Provider candle timestamp is invalid.');
            }
            try {
                $open = $timeframe === 'D1'
                    ? CarbonImmutable::createFromFormat('!Y-m-d', $row['datetime'], config('market_data.session_timezone'))->startOfDay()->utc()
                    : CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $row['datetime'], 'UTC');
                $close = $timeframe === 'D1'
                    ? $open->setTimezone(config('market_data.session_timezone'))->addDay()->startOfDay()->utc()
                    : $open->addMinutes(MarketInsightSettings::TIMEFRAMES[$timeframe]);
            } catch (Throwable) {
                throw new MarketDataException('INVALID_DATA', 'Provider candle timestamp is invalid.');
            }
            if ($close->greaterThan($now->subSeconds($latency))) {
                continue; // The provider may include the currently forming bar.
            }
            $candle = [
                'timeframe' => $timeframe, 'open_at' => $open->toDateTimeString(),
                'close_at' => $close->toDateTimeString(),
                'session_date' => $timeframe === 'D1' ? $row['datetime'] : $open->setTimezone(config('market_data.session_timezone'))->toDateString(),
                'open' => $row['open'] ?? null, 'high' => $row['high'] ?? null,
                'low' => $row['low'] ?? null, 'close' => $row['close'] ?? null,
                'volume' => $row['volume'] ?? null,
                'source' => 'REAL', 'is_complete' => true,
            ];
            if (isset($candles[$candle['open_at']])) {
                $this->duplicatesDiscarded++;
            }
            // A provider correction may repeat the same session label. Keep
            // its final row, and never persist two finalized bars for one open.
            $candles[$candle['open_at']] = $candle;
        }
        $candles = array_values($candles);
        if ($timeframe === 'H4') {
            for ($i = 0; $i < count($candles) - 1; $i++) {
                if ($candles[$i + 1]['open_at'] < $candles[$i]['close_at']) {
                    $candles[$i]['close_at'] = $candles[$i + 1]['open_at'];
                }
            }
        }
        try {
            $this->validator->validate($candles, $timeframe, 'REAL');
        } catch (InvalidArgumentException) {
            throw new MarketDataException('INVALID_DATA', 'Provider OHLC candles failed validation.');
        }

        return $candles;
    }

    public function load(int $datasetId): array
    {
        $dataset = DB::table('market_insight_datasets')->find($datasetId);
        if (! $dataset || $dataset->source !== 'REAL' || $dataset->provider !== 'twelvedata') {
            throw new MarketDataException('INVALID_DATA', 'Real market dataset is unavailable.');
        }
        $rows = DB::table('market_insight_candles')->where('dataset_id', $datasetId)
            ->where('source', 'REAL')->orderByDesc('open_at')->limit(350)->get()->reverse();
        $chart = $rows->map(fn ($row) => $this->row($row))->all();
        $dailyDataset = $dataset->timeframe === 'D1' ? $dataset : DB::table('market_insight_datasets')
            ->where('instrument_id', $dataset->instrument_id)->where('timeframe', 'D1')
            ->where('source', 'REAL')->where('provider', 'twelvedata')->first();
        $daily = $dailyDataset ? DB::table('market_insight_candles')->where('dataset_id', $dailyDataset->id)
            ->where('source', 'REAL')->orderByDesc('open_at')->limit(100)->get()->reverse()
            ->map(fn ($row) => $this->row($row))->all() : [];

        return ['dataset' => $dataset, 'chart' => array_values($chart), 'daily' => array_values($daily)];
    }

    private function row(object $row): array
    {
        return [
            'timeframe' => $row->timeframe, 'open_at' => $row->open_at, 'close_at' => $row->close_at,
            'session_date' => $row->session_date,
            'open' => (float) $row->open, 'high' => (float) $row->high,
            'low' => (float) $row->low, 'close' => (float) $row->close,
            'volume' => $row->volume === null ? null : (float) $row->volume,
            'source' => $row->source, 'is_complete' => (bool) $row->is_complete,
        ];
    }

    private function request(string $endpoint, array $query): array
    {
        $key = config('market_data.api_key');
        if (! is_string($key) || trim($key) === '') {
            throw new MarketDataException('NOT_CONFIGURED', 'Market data provider authentication requires configuration.');
        }
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $response = Http::acceptJson()->withHeaders(['Authorization' => 'apikey '.$key])
                    ->timeout((int) config('market_data.request_timeout_seconds'))
                    ->get('https://api.twelvedata.com/'.$endpoint, $query);
            } catch (Throwable) {
                if ($attempt < 2) {
                    usleep(200000 * (2 ** $attempt));

                    continue;
                }
                throw new MarketDataException('ERROR', 'XAUUSD market data is temporarily unavailable.');
            }
            $body = $response->json();
            $code = is_array($body) ? (int) ($body['code'] ?? $response->status()) : $response->status();
            if ($response->successful() && is_array($body) && ($body['status'] ?? null) !== 'error') {
                $credits = $response->header('api-credits-left');
                $this->creditsLeft = is_numeric($credits) ? (int) $credits : null;

                return $body;
            }
            // Minute quota exhaustion is deferred to the next scheduled run;
            // immediate retries cannot refill the provider's credit window.
            if ($response->serverError() && $attempt < 2) {
                usleep(200000 * (2 ** $attempt));

                continue;
            }
            $status = match (true) {
                $code === 401, $response->status() === 401 => 'AUTH_ERROR',
                $code === 429, $response->status() === 429 => 'RATE_LIMITED',
                $code === 403, $response->status() === 403 => 'ENTITLEMENT_REQUIRED',
                default => 'ERROR',
            };
            throw new MarketDataException($status, 'XAUUSD market data is temporarily unavailable ('.$status.').');
        }
        throw new MarketDataException('ERROR', 'XAUUSD market data is temporarily unavailable.');
    }

    private function symbol(): string
    {
        $symbol = config('market_data.symbol');
        if ($symbol !== 'XAU/USD') {
            throw new MarketDataException('NOT_CONFIGURED', 'The configured market symbol must be XAU/USD.');
        }

        return $symbol;
    }

    private function positive(mixed $value): bool
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value > 0;
    }

    private function optionalPrice(mixed $value): ?string
    {
        return $this->positive($value) ? (string) $value : null;
    }
}
