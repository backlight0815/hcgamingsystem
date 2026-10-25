<?php

namespace App\Services\MarketInsight;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

final class MarketInsightService
{
    public function __construct(private readonly MockMarketDataProvider $mockProvider,
        private readonly TwelveDataMarketDataProvider $realProvider,
        private readonly CandleValidator $validator,
        private readonly TechnicalIndicatorService $indicators,
        private readonly MarketInsightSignalEngine $signals)
    {
        }

    public function regenerate(object $instrument, string $timeframe, string $scenario, int $seed,
        ?CarbonImmutable $anchor, ?int $userId): array
    {
        $started = now();
        try {
            $datasetId = $this->mockProvider->generate($instrument, $timeframe, $scenario, $seed, $anchor);
            $result = $this->analyze($datasetId, $userId);
            $this->log('generate', 'completed', $userId, $datasetId,
                'Generated completed MOCK candles with scenario '.$scenario.'.', $started);

            return $result;
        } catch (Throwable $error) {
            $this->log('generate', 'failed', $userId, null, mb_substr($error->getMessage(), 0, 500), $started);
            throw $error;
        }
    }

    public function analyze(int $datasetId, ?int $userId): array
    {
        $started = now();
        try {
            $record = DB::table('market_insight_datasets')->find($datasetId);
            if (! $record || ! in_array($record->source, ['MOCK', 'REAL'], true)) {
                throw new InvalidArgumentException('Market dataset is unavailable.');
            }
            $source = $record->source;
            $input = $source === 'REAL' ? $this->realProvider->load($datasetId) : $this->mockProvider->load($datasetId);
            $dataset = $input['dataset'];
            $this->validator->validate($input['chart'], $dataset->timeframe, $source);
            $this->validator->validate($input['daily'], 'D1', $source);
            if ($input['chart'] === [] || $input['daily'] === []) {
                throw new InvalidArgumentException('Completed chart and daily candles are required.');
            }
            $latest = end($input['chart']);
            $minutes = MarketInsightSettings::TIMEFRAMES[$dataset->timeframe];
            $age = CarbonImmutable::now('UTC')->getTimestamp() - CarbonImmutable::parse($latest['close_at'], 'UTC')->getTimestamp();
            if ($source === 'REAL' && ($age > $minutes * 60 * (int) config('market_data.analysis_stale_multiplier') || $age < -60)) {
                $this->log('calculate', 'unavailable', $userId, $datasetId,
                    'Verified real candles are stale or in the future.', $started);

                return ['status' => 'unavailable', 'reason' => 'Verified market candles are stale; no current signal is available.',
                    'data_status' => 'STALE', 'is_mock_data' => false, 'source' => 'REAL', 'dataset_id' => $datasetId];
            }
            if ($source === 'MOCK' && ($age > max($minutes * 120, 48 * 3600) || $age < -60)) {
                $this->log('calculate', 'unavailable', $userId, $datasetId,
                    'Dataset timestamp is stale or in the future.', $started);

                return ['status' => 'unavailable', 'reason' => 'The simulated dataset is stale or its timestamp is invalid.',
                    'is_mock_data' => true, 'source' => 'MOCK', 'dataset_id' => $datasetId];
            }
            $configuration = MarketInsightSettings::current();
            $calculated = $this->indicators->calculate($input['chart'], $input['daily'], $configuration['settings']);
            $signal = $this->signals->combine($calculated, $configuration['settings']);
            if ($signal['status'] !== 'available') {
                $this->log('calculate', 'unavailable', $userId, $datasetId, $signal['reason'], $started);

                return [...$signal, 'is_mock_data' => $source === 'MOCK', 'source' => $source, 'dataset_id' => $datasetId,
                    'indicators' => $calculated];
            }
            $instrument = DB::table('market_insight_instruments')->find($dataset->instrument_id);
            $result = [
                'contract_version' => 1, 'engine_version' => MarketInsightSignalEngine::VERSION,
                'dataset_id' => $datasetId, 'dataset_version' => (int) $dataset->version,
                'dataset_revision' => (int) $dataset->revision,
                'configuration_version' => $configuration['version'], 'configuration' => $configuration['settings'],
                'symbol' => $instrument->symbol, 'timeframe' => $dataset->timeframe,
                'source' => $source, 'is_mock_data' => $source === 'MOCK',
                'provider' => $source === 'REAL' ? $dataset->provider : null,
                'scenario' => $dataset->scenario,
                'seed' => (int) $dataset->seed, 'candle_count' => count($input['chart']),
                'daily_session_count' => count($input['daily']),
                'market_data_at' => $latest['close_at'],
                'analysis_candle_at' => $latest['close_at'],
                'session_date' => $latest['session_date'] ?? null,
                'data_status' => $source === 'REAL' ? 'LATEST_AVAILABLE' : 'DEMO',
                'price' => ['open' => $latest['open'], 'high' => $latest['high'], 'low' => $latest['low'], 'close' => $latest['close']],
                'indicators' => $calculated, ...$signal,
            ];

            return DB::transaction(function () use ($dataset, $configuration, $result, $latest, $userId, $started, $source): array {
                DB::table('market_insight_datasets')->where('id', $dataset->id)->lockForUpdate()->first();
                $existing = DB::table('market_insight_analyses')->where('dataset_id', $dataset->id)
                    ->where('configuration_version', $configuration['version'])
                    ->where('engine_version', MarketInsightSignalEngine::VERSION)
                    ->where('dataset_revision', $dataset->revision)
                    ->where('candle_at', $latest['close_at'])->first();
                if ($existing) {
                    $this->log('calculate', 'reused', $userId, $dataset->id, 'Reused analysis '.$existing->id.'.', $started);
                    if ($existing->archived_at !== null) {
                        return ['status' => 'unavailable', 'reason' => 'The saved analysis is archived. An administrator can restore it.',
                            'source' => $source, 'is_mock_data' => $source === 'MOCK', 'dataset_id' => $dataset->id];
                    }

                    return json_decode($existing->result, true, 512, JSON_THROW_ON_ERROR);
                }
                $result['analyzed_at'] = now('UTC')->toIso8601String();
                $id = DB::table('market_insight_analyses')->insertGetId([
                    'dataset_id' => $dataset->id, 'instrument_id' => $dataset->instrument_id,
                    'dataset_revision' => $dataset->revision,
                    'timeframe' => $dataset->timeframe, 'configuration_version' => $configuration['version'],
                    'engine_version' => MarketInsightSignalEngine::VERSION,
                    'candle_at' => $latest['close_at'], 'calculated_at' => now(),
                    'signal' => $result['signal'], 'directional_score' => $result['directional_score'],
                    'risk' => $result['risk'], 'result' => '{}', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $result['id'] = $id;
                DB::table('market_insight_analyses')->where('id', $id)->update(['result' => json_encode($result, JSON_THROW_ON_ERROR)]);
                $this->log('calculate', 'completed', $userId, $dataset->id, 'Saved rule-based analysis '.$id.'.', $started);

                return $result;
            });
        } catch (InvalidArgumentException $error) {
            $this->log('calculate', 'unavailable', $userId, $datasetId, mb_substr($error->getMessage(), 0, 500), $started);

            $source = $source ?? 'MOCK';
            return ['status' => 'unavailable', 'reason' => $source === 'REAL'
                ? 'Verified market candles failed validation.' : 'Saved simulated candles failed validation: '.$error->getMessage(),
                'is_mock_data' => $source === 'MOCK', 'source' => $source, 'dataset_id' => $datasetId];
        } catch (Throwable $error) {
            $this->log('calculate', 'failed', $userId, $datasetId, mb_substr($error->getMessage(), 0, 500), $started);
            throw $error;
        }
    }

    public function latest(object $instrument, string $timeframe): ?array
    {
        $source = $this->source();
        $row = DB::table('market_insight_analyses as a')
            ->join('market_insight_datasets as d', 'd.id', '=', 'a.dataset_id')
            ->where('a.instrument_id', $instrument->id)->where('a.timeframe', $timeframe)
            ->where('d.source', $source)->whereNull('a.archived_at')->select('a.*')->orderByDesc('a.id')->first();
        if (! $row) {
            return null;
        }
        $result = json_decode($row->result, true, 512, JSON_THROW_ON_ERROR);
        $newest = $this->newestDataset($instrument, $timeframe);
        $age = CarbonImmutable::now('UTC')->getTimestamp() - CarbonImmutable::parse($result['market_data_at'], 'UTC')->getTimestamp();
        $limit = $source === 'REAL'
            ? MarketInsightSettings::TIMEFRAMES[$timeframe] * 60 * (int) config('market_data.analysis_stale_multiplier')
            : max(MarketInsightSettings::TIMEFRAMES[$timeframe] * 120, 48 * 3600);
        $newestCandle = $source === 'REAL' && $newest
            ? DB::table('market_insight_candles')->where('dataset_id', $newest->id)->max('close_at') : null;
        if ($newest?->id !== $row->dataset_id || $row->configuration_version !== MarketInsightSettings::current()['version'] ||
            ($newestCandle !== null && $newestCandle !== $row->candle_at) ||
            (int) $newest?->revision !== (int) $row->dataset_revision || $age > $limit || $age < -60) {
            $quote = $source === 'REAL' ? $this->quote($instrument) : null;
            return ['status' => 'unavailable', 'reason' => $source === 'REAL'
                ? 'Market Insight is displaying the last verified analysis in history; the current signal is stale.'
                : 'The most recent simulated dataset has no current analysis, or its candles are stale.',
                'data_status' => $source === 'REAL' && ($quote['status'] ?? null) === 'MARKET_CLOSED' ? 'MARKET_CLOSED' : 'STALE',
                'last_verified_analysis_at' => $result['analyzed_at'] ?? null,
                'source' => $source, 'is_mock_data' => $source === 'MOCK'];
        }

        if ($source === 'REAL') {
            $result['quote'] = $this->quote($instrument);
            $result['data_status'] = ($result['quote']['is_market_open'] ?? null) === false ? 'MARKET_CLOSED' : 'LATEST_AVAILABLE';
        }

        return $result;
    }

    public function history(object $instrument, string $timeframe, bool $includeArchived = false, int $page = 1): array
    {
        $query = DB::table('market_insight_analyses as a')
            ->join('market_insight_datasets as d', 'd.id', '=', 'a.dataset_id')
            ->where('a.instrument_id', $instrument->id)->where('a.timeframe', $timeframe)
            ->where('d.source', $this->source())->select('a.*');
        if (! $includeArchived) {
        $query->whereNull('a.archived_at');
        }

        return $query->orderByDesc('a.id')->offset(($page - 1) * 50)->limit(50)->get()->map(fn ($row) => [
            'id' => $row->id, 'symbol' => $instrument->symbol, 'timeframe' => $timeframe,
            'signal' => $row->signal, 'directional_score' => (float) $row->directional_score,
            'risk' => $row->risk, 'analyzed_at' => $row->calculated_at,
            'configuration_version' => $row->configuration_version,
            'archived_at' => $row->archived_at,
        ])->all();
    }

    public function instrument(string $symbol, bool $includeDisabled = false): object
    {
        $query = DB::table('market_insight_instruments')->where('symbol', strtoupper($symbol));
        if (! $includeDisabled) {
        $query->where('is_enabled', true);
        }

        return $query->first() ?? throw new InvalidArgumentException('Trading symbol is unavailable.');
    }

    public function newestDataset(object $instrument, string $timeframe): ?object
    {
        return DB::table('market_insight_datasets')->where('instrument_id', $instrument->id)
            ->where('timeframe', $timeframe)->where('source', $this->source())->orderByDesc('version')->first();
    }

    public function source(): string
    {
        return config('market_data.mode') === 'REAL' ? 'REAL' : 'MOCK';
    }

    public function quote(object $instrument): ?array
    {
        $row = DB::table('market_insight_quotes')->where('instrument_id', $instrument->id)
            ->where('provider', 'twelvedata')->first();
        if (! $row) {
            return null;
        }
        $age = $row->quote_at === null ? null : CarbonImmutable::parse($row->quote_at, 'UTC')->diffInSeconds(CarbonImmutable::now('UTC'));
        $retrievalAge = CarbonImmutable::parse($row->retrieved_at, 'UTC')->diffInSeconds(CarbonImmutable::now('UTC'));
        $staleAfter = (int) config('market_data.quote_stale_seconds');
        $status = $retrievalAge > $staleAfter ? 'STALE'
            : ($row->is_market_open !== null && ! (bool) $row->is_market_open ? 'MARKET_CLOSED'
                : ($age === null ? 'LATEST_AVAILABLE' : ($age > $staleAfter ? 'STALE' : 'LATEST_AVAILABLE')));

        return ['price' => (string) $row->price, 'currency' => 'USD', 'provider' => 'twelvedata',
            'quote_at' => $row->quote_at, 'retrieved_at' => $row->retrieved_at,
            'is_market_open' => $row->is_market_open === null ? null : (bool) $row->is_market_open,
            'status' => $status, 'details' => json_decode($row->details ?: '{}', true, 512, JSON_THROW_ON_ERROR)];
    }

    private function log(string $type, string $status, ?int $userId, ?int $datasetId,
        string $details, mixed $started): void
    {
        DB::table('market_insight_execution_logs')->insert([
            'type' => $type, 'status' => $status, 'triggered_by' => $userId,
            'dataset_id' => $datasetId, 'details' => $details,
            'started_at' => $started, 'completed_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
