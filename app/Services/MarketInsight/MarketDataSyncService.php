<?php

namespace App\Services\MarketInsight;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class MarketDataSyncService
{
    public function __construct(private readonly TwelveDataMarketDataProvider $provider,
        private readonly MarketInsightService $insights)
    {
    }

    public function sync(?string $timeframe = null, ?int $userId = null, bool $force = false): array
    {
        if (config('market_data.mode') !== 'REAL' || config('market_data.provider') !== 'twelvedata') {
            return ['status' => 'NOT_CONFIGURED', 'message' => 'Real market data mode is not enabled.'];
        }
        if (! config('market_data.api_key')) {
            return ['status' => 'NOT_CONFIGURED', 'message' => 'Market data provider authentication requires configuration.'];
        }
        if ($timeframe !== null && ! isset(TwelveDataMarketDataProvider::INTERVALS[$timeframe])) {
            return ['status' => 'UNSUPPORTED', 'message' => 'Unsupported market timeframe.'];
        }
        $lock = Cache::lock('market-insight:twelvedata:sync', 180);
        if (! $lock->get()) {
            return ['status' => 'BUSY', 'message' => 'A market data synchronization is already running.'];
        }
        $started = now();
        try {
            $instrument = $this->insights->instrument('XAUUSD');
            $savedQuote = DB::table('market_insight_quotes')->where('instrument_id', $instrument->id)
                ->where('provider', 'twelvedata')->first();
            $quote = null;
            if ($force || ! $savedQuote || CarbonImmutable::parse($savedQuote->retrieved_at, 'UTC')
                ->addSeconds((int) config('market_data.quote_refresh_seconds'))->isPast()) {
                $quote = $this->provider->quote();
                DB::table('market_insight_quotes')->updateOrInsert(
                    ['instrument_id' => $instrument->id, 'provider' => 'twelvedata'],
                    ['price' => $quote['price'], 'quote_at' => $quote['quote_at'],
                        'retrieved_at' => now('UTC'), 'is_market_open' => $quote['is_market_open'],
                        'details' => json_encode($quote['details'], JSON_THROW_ON_ERROR),
                        'updated_at' => now(), 'created_at' => now()]
                );
            }
            $frames = $timeframe === null ? ['D1', 'H4', 'H1', 'M30', 'M15']
                : ($timeframe === 'D1' ? ['D1'] : ['D1', $timeframe]);
            $results = [];
            foreach ($frames as $frame) {
                if (($quote['is_market_open'] ?? $savedQuote?->is_market_open) === false ||
                    ($quote['is_market_open'] ?? $savedQuote?->is_market_open) === 0) {
                    continue;
                }
                if (! $force && ! $this->due($instrument, $frame)) {
                    continue;
                }
                $results[$frame] = $this->syncFrame($instrument, $frame, $userId);
            }
            if ($quote === null && $results === []) {
                return ['status' => 'IDLE', 'message' => 'Cached market data is current; no provider request was needed.'];
            }
            $this->status('CONNECTED', 'Provider request succeeded.', true);
            $this->log('sync', 'completed', $userId, null, 'Verified Twelve Data XAU/USD quote and completed candles.', $started);

            return ['status' => 'CONNECTED', 'provider' => 'twelvedata', 'symbol' => 'XAU/USD',
                'quote_at' => $quote['quote_at'] ?? $savedQuote?->quote_at,
                'frames' => $results, 'credits_left' => $this->provider->creditsLeft()];
        } catch (MarketDataException $error) {
            $this->status($error->dataStatus, $error->getMessage(), false);
            $this->log('sync', 'failed', $userId, null, $error->dataStatus.': '.$error->getMessage(), $started);

            return ['status' => $error->dataStatus, 'message' => $error->getMessage()];
        } catch (Throwable) {
            $this->status('ERROR', 'XAUUSD market data is temporarily unavailable.', false);
            $this->log('sync', 'failed', $userId, null, 'Market data synchronization failed.', $started);

            return ['status' => 'ERROR', 'message' => 'XAUUSD market data is temporarily unavailable.'];
        } finally {
            $lock->release();
        }
    }

    private function syncFrame(object $instrument, string $timeframe, ?int $userId): array
    {
        $dataset = DB::table('market_insight_datasets')->where('instrument_id', $instrument->id)
            ->where('timeframe', $timeframe)->where('source', 'REAL')->where('provider', 'twelvedata')->first();
        $last = $dataset ? DB::table('market_insight_candles')->where('dataset_id', $dataset->id)->max('open_at') : null;
        $elapsed = $last ? max(0, CarbonImmutable::parse($last, 'UTC')->diffInMinutes(CarbonImmutable::now('UTC'))) : 0;
        $count = $last ? min(5000, max(8, (int) ceil($elapsed / MarketInsightSettings::TIMEFRAMES[$timeframe]) + 5)) : 310;
        $candles = $this->provider->candles($timeframe, $count);
        if ($this->provider->duplicatesDiscarded() > 0) {
            $this->log('validate', 'warning', $userId, $dataset?->id,
                'Provider returned '.$this->provider->duplicatesDiscarded().' duplicate '.$timeframe.' session labels; earlier rows were discarded.', now());
        }
        if (count($candles) < 2) {
            throw new MarketDataException('INSUFFICIENT_HISTORY', 'Historical candle data is insufficient for '.$timeframe.'.');
        }
        $changed = DB::transaction(function () use ($instrument, $timeframe, $candles, $dataset): array {
            DB::table('market_insight_instruments')->where('id', $instrument->id)->lockForUpdate()->first();
            if (! $dataset) {
                $version = (int) DB::table('market_insight_datasets')->where('instrument_id', $instrument->id)
                    ->where('timeframe', $timeframe)->max('version') + 1;
                $id = DB::table('market_insight_datasets')->insertGetId([
                    'instrument_id' => $instrument->id, 'timeframe' => $timeframe,
                    'version' => $version, 'scenario' => 'provider', 'seed' => 0,
                    'anchor_at' => $candles[0]['open_at'], 'source' => 'REAL',
                    'provider' => 'twelvedata', 'revision' => 1,
                    'source_metadata' => json_encode(['symbol' => 'XAU/USD', 'session_timezone' => config('market_data.session_timezone')], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $dataset = DB::table('market_insight_datasets')->find($id);
            }
            $existing = DB::table('market_insight_candles')->where('dataset_id', $dataset->id)
                ->where('timeframe', $timeframe)->whereIn('open_at', array_column($candles, 'open_at'))
                ->get()->keyBy('open_at');
            $rows = [];
            foreach ($candles as $candle) {
                $prior = $existing->get($candle['open_at']);
                if ($prior && collect(['open', 'high', 'low', 'close', 'volume', 'close_at'])
                    ->every(fn ($field) => $field === 'close_at'
                        ? $prior->close_at === $candle['close_at']
                        : ($prior->$field === null && $candle[$field] === null ||
                            ($prior->$field !== null && $candle[$field] !== null &&
                                number_format((float) $prior->$field, $field === 'volume' ? 4 : 8, '.', '') ===
                                number_format((float) $candle[$field], $field === 'volume' ? 4 : 8, '.', ''))))) {
                    continue;
                }
                $rows[] = [
                    'dataset_id' => $dataset->id, 'timeframe' => $timeframe,
                    'open_at' => $candle['open_at'], 'close_at' => $candle['close_at'],
                    'session_date' => $candle['session_date'], 'open' => $candle['open'],
                    'high' => $candle['high'], 'low' => $candle['low'], 'close' => $candle['close'],
                    'volume' => $candle['volume'], 'source' => 'REAL', 'is_complete' => true,
                    'retrieved_at' => now('UTC'),
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('market_insight_candles')->upsert($chunk, ['dataset_id', 'timeframe', 'open_at'],
                    ['close_at', 'session_date', 'open', 'high', 'low', 'close', 'volume', 'is_complete', 'retrieved_at']);
            }
            if ($rows !== [] && $existing->isNotEmpty()) {
                DB::table('market_insight_datasets')->where('id', $dataset->id)
                    ->update(['revision' => DB::raw('revision + 1'), 'updated_at' => now()]);
            } else {
                DB::table('market_insight_datasets')->where('id', $dataset->id)->update(['updated_at' => now()]);
            }

            return ['dataset_id' => $dataset->id, 'written' => count($rows), 'completed_candles' => count($candles)];
        });
        $analysis = $this->insights->analyze($changed['dataset_id'], $userId);

        return [...$changed, 'analysis_status' => $analysis['status'] ?? 'unavailable',
            'analysis_id' => $analysis['id'] ?? null, 'analysis_candle_at' => $analysis['market_data_at'] ?? null];
    }

    private function due(object $instrument, string $timeframe): bool
    {
        $dataset = DB::table('market_insight_datasets')->where('instrument_id', $instrument->id)
            ->where('timeframe', $timeframe)->where('source', 'REAL')->where('provider', 'twelvedata')->first();
        if (! $dataset) {
            return true;
        }
        $lastClose = DB::table('market_insight_candles')->where('dataset_id', $dataset->id)->max('close_at');
        if (! $lastClose) {
            return true;
        }
        $nextDue = CarbonImmutable::parse($lastClose, 'UTC')
            ->addMinutes(MarketInsightSettings::TIMEFRAMES[$timeframe])
            ->addSeconds((int) config('market_data.publish_latency_seconds'));

        return $nextDue->isPast() && CarbonImmutable::parse($dataset->updated_at, config('app.timezone'))
            ->addMinutes(5)->isPast();
    }

    private function status(string $status, string $message, bool $success): void
    {
        $old = DB::table('market_insight_provider_status')->where('provider', 'twelvedata')->first();
        DB::table('market_insight_provider_status')->updateOrInsert(['provider' => 'twelvedata'], [
            'status' => $status, 'message' => $message,
            'last_success_at' => $success ? now('UTC') : $old?->last_success_at,
            'last_failure_at' => $success ? $old?->last_failure_at : now('UTC'),
            'credits_left' => $this->provider->creditsLeft(),
            'created_at' => $old?->created_at ?? now(), 'updated_at' => now(),
        ]);
    }

    private function log(string $type, string $status, ?int $userId, ?int $datasetId, string $details, mixed $started): void
    {
        DB::table('market_insight_execution_logs')->insert([
            'type' => $type, 'status' => $status, 'triggered_by' => $userId,
            'dataset_id' => $datasetId, 'details' => $details, 'started_at' => $started,
            'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
