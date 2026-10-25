<?php

namespace App\Http\Controllers;

use App\Services\MarketInsight\MarketInsightService;
use App\Services\MarketInsight\MarketInsightSettings;
use App\Services\MarketInsight\MarketDataSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class MarketInsightController extends Controller
{
    public function __construct(private readonly MarketInsightService $insights,
        private readonly MarketDataSyncService $sync)
    {
    }

    public function dashboard(Request $request)
    {
        $this->readAccess($request);
        $selection = $this->selection($request);
        $analysis = $this->insights->latest($selection['instrument'], $selection['timeframe']);
        $history = $this->insights->history($selection['instrument'], $selection['timeframe']);

        return view('market_insight.dashboard', [
            'instruments' => $this->instruments(), 'timeframes' => MarketInsightSettings::TIMEFRAMES,
            'symbol' => $selection['instrument']->symbol, 'timeframe' => $selection['timeframe'],
            'analysis' => $analysis, 'history' => $history,
            'source' => $this->insights->source(),
            'quote' => $this->insights->source() === 'REAL' ? $this->insights->quote($selection['instrument']) : null,
            'providerStatus' => $this->providerStatus(),
            'candles' => $this->savedCandles($selection['instrument'], $selection['timeframe'], 60),
            'isAdmin' => $this->isAdmin($request),
        ]);
    }

    public function showWeb(Request $request, int $id)
    {
        $this->readAccess($request);
        $query = DB::table('market_insight_analyses')->where('id', $id);
        if (! $this->isAdmin($request)) {
        $query->whereNull('archived_at');
        }
        $row = $query->first();
        abort_unless($row, 404);
        abort_unless(DB::table('market_insight_datasets')->where('id', $row->dataset_id)
            ->where('source', $this->insights->source())->exists(), 404);
        if (! $this->isAdmin($request)) {
            abort_unless(DB::table('market_insight_instruments')->where('id', $row->instrument_id)
                ->where('is_enabled', true)->exists(), 404);
        }
        $analysis = json_decode($row->result, true, 512, JSON_THROW_ON_ERROR);

        return view('market_insight.dashboard', [
            'instruments' => $this->instruments(), 'timeframes' => MarketInsightSettings::TIMEFRAMES,
            'symbol' => $analysis['symbol'], 'timeframe' => $analysis['timeframe'],
            'analysis' => $analysis, 'history' => $this->insights->history(
                $this->insights->instrument($analysis['symbol'], true), $analysis['timeframe']),
            'source' => $this->insights->source(),
            'quote' => $this->insights->source() === 'REAL' ? $this->insights->quote(
                $this->insights->instrument($analysis['symbol'], true)) : null,
            'providerStatus' => $this->providerStatus(),
            'candles' => $this->savedCandles($this->insights->instrument($analysis['symbol'], true), $analysis['timeframe'], 60),
            'isAdmin' => $this->isAdmin($request), 'historical' => true,
        ]);
    }

    public function management(Request $request)
    {
        $this->adminAccess($request);

        return view('market_insight.management', [
            'configuration' => MarketInsightSettings::current(),
            'instruments' => DB::table('market_insight_instruments')->orderBy('symbol')->get(),
            'datasets' => DB::table('market_insight_datasets as d')
                ->join('market_insight_instruments as i', 'i.id', '=', 'd.instrument_id')
                ->select('d.*', 'i.symbol')->orderByDesc('d.id')->limit(50)->get(),
            'logs' => DB::table('market_insight_execution_logs')->orderByDesc('id')->limit(50)->get(),
            'snapshots' => DB::table('market_insight_analyses as a')
                ->join('market_insight_instruments as i', 'i.id', '=', 'a.instrument_id')
                ->select('a.*', 'i.symbol')->orderByDesc('a.id')->limit(50)->get(),
            'timeframes' => MarketInsightSettings::TIMEFRAMES,
            'scenarios' => MarketInsightSettings::SCENARIOS,
            'source' => $this->insights->source(),
            'providerStatus' => $this->providerStatus(),
            'providerConfigured' => (bool) config('market_data.api_key'),
            'distributionAllowed' => (bool) config('market_data.distribution_allowed'),
            'releases' => config('market_insight_releases'),
        ]);
    }

    public function instrumentsApi(Request $request): JsonResponse
    {
        $this->readAccess($request);

        return $this->success(['instruments' => $this->instruments(),
            'timeframes' => array_keys(MarketInsightSettings::TIMEFRAMES),
            'source' => $this->insights->source(), 'is_mock_data' => $this->insights->source() === 'MOCK',
            'provider' => $this->insights->source() === 'REAL' ? 'twelvedata' : null,
            'method' => 'Rule-Based Indicator Analysis']);
    }

    public function latestApi(Request $request): JsonResponse
    {
        $this->readAccess($request);
        $selection = $this->selection($request);
        $analysis = $this->insights->latest($selection['instrument'], $selection['timeframe']);
        $providerStatus = $this->providerStatus();
        $status = $analysis['status'] ?? ($this->insights->source() === 'REAL'
            ? $providerStatus['status'] : 'empty');

        return $this->success(['analysis' => $analysis, 'status' => $status,
            'source' => $this->insights->source(), 'is_mock_data' => $this->insights->source() === 'MOCK',
            'quote' => $this->insights->source() === 'REAL' ? $this->insights->quote($selection['instrument']) : null,
            'provider_status' => $providerStatus]);
    }

    public function historyApi(Request $request): JsonResponse
    {
        $this->readAccess($request);
        $selection = $this->selection($request);
        $data = $request->validate(['page' => ['nullable', 'integer', 'between:1,1000']]);
        $page = (int) ($data['page'] ?? 1);

        return $this->success(['history' => $this->insights->history($selection['instrument'], $selection['timeframe'], false, $page),
            'page' => $page, 'per_page' => 50]);
    }

    public function showApi(Request $request, int $id): JsonResponse
    {
        $this->readAccess($request);
        $query = DB::table('market_insight_analyses')->where('id', $id);
        if (! $this->isAdmin($request)) {
        $query->whereNull('archived_at');
        }
        $row = $query->first();
        abort_unless($row, 404);
        abort_unless(DB::table('market_insight_datasets')->where('id', $row->dataset_id)
            ->where('source', $this->insights->source())->exists(), 404);
        if (! $this->isAdmin($request)) {
            abort_unless(DB::table('market_insight_instruments')->where('id', $row->instrument_id)
                ->where('is_enabled', true)->exists(), 404);
        }

        return $this->success(['analysis' => json_decode($row->result, true, 512, JSON_THROW_ON_ERROR)]);
    }

    public function refresh(Request $request)
    {
        $this->readAccess($request);
        $selection = $this->selection($request);
        $dataset = $this->insights->newestDataset($selection['instrument'], $selection['timeframe']);
        if (! $dataset) {
            return $request->expectsJson() ? $this->success(['analysis' => null, 'status' => 'empty',
                'reason' => $this->insights->source() === 'REAL' ? 'No verified real dataset has been synchronized.' : 'No simulated dataset has been generated.'])
                : back()->with('warning', $this->insights->source() === 'REAL'
                    ? 'No verified real dataset is available. Ask an administrator to synchronize market data.'
                    : 'No simulated dataset is available. Ask an administrator to generate one.');
        }
        $analysis = $this->insights->analyze($dataset->id, $request->user()->id);

        return $request->expectsJson() ? $this->success(['analysis' => $analysis, 'status' => $analysis['status']])
            : redirect()->route('market_insight.dashboard', ['symbol' => $selection['instrument']->symbol,
                'timeframe' => $selection['timeframe']])->with(
                $analysis['status'] === 'available' ? 'success' : 'warning',
                $analysis['status'] === 'available' ? 'Analysis refreshed from saved verified candles.' : $analysis['reason']);
    }

    public function settingsApi(Request $request): JsonResponse
    {
        $this->adminAccess($request);

        return $this->success(MarketInsightSettings::current());
    }

    public function updateSettings(Request $request)
    {
        $this->adminAccess($request);
        $input = $request->validate(['settings' => ['required', 'array']]);
        $configuration = MarketInsightSettings::save($input['settings'], $request->user()->id);

        return $request->expectsJson() ? $this->success($configuration)
            : back()->with('success', 'Configuration version '.$configuration['version'].' saved. Recalculate to apply it to saved data.');
    }

    public function saveInstrument(Request $request, ?int $id = null)
    {
        $this->adminAccess($request);
        $request->merge(['symbol' => strtoupper((string) $request->input('symbol', ''))]);
        $input = $request->validate([
            'symbol' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9]+$/',
                Rule::unique('market_insight_instruments', 'symbol')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(['forex', 'metal', 'crypto', 'index', 'other'])],
            'demo_base_price' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'session_calendar' => ['required', Rule::in(['weekdays', 'all_days'])],
            'is_enabled' => ['required', 'boolean'],
        ]);
        $input['symbol'] = strtoupper($input['symbol']);
        $input['updated_at'] = now();
        if ($id) {
            abort_unless(DB::table('market_insight_instruments')->where('id', $id)->exists(), 404);
            DB::table('market_insight_instruments')->where('id', $id)->update($input);
        } else {
            $input['created_at'] = now();
            $id = DB::table('market_insight_instruments')->insertGetId($input);
        }

        return $request->expectsJson() ? $this->success(['id' => $id, 'symbol' => $input['symbol']])
            : back()->with('success', 'Demo instrument saved.');
    }

    public function generate(Request $request)
    {
        $this->adminAccess($request);
        $input = $request->validate([
            'symbol' => ['required', 'string', 'max:24'],
            'timeframe' => ['required', Rule::in(array_keys(MarketInsightSettings::TIMEFRAMES))],
            'scenario' => ['required', Rule::in(MarketInsightSettings::SCENARIOS)],
            'seed' => ['required', 'integer', 'between:1,2147483646'],
            'anchor_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);
        try {
            $instrument = $this->insights->instrument($input['symbol']);
        } catch (InvalidArgumentException) {
            abort(404, 'Trading symbol is unavailable.');
        }
        $analysis = $this->insights->regenerate($instrument, $input['timeframe'], $input['scenario'],
            (int) $input['seed'], isset($input['anchor_at']) ? CarbonImmutable::parse($input['anchor_at'], 'UTC') : null,
            $request->user()->id);

        return $request->expectsJson() ? $this->success(['analysis' => $analysis], 201)
            : back()->with($analysis['status'] === 'available' ? 'success' : 'warning',
                $analysis['status'] === 'available' ? 'Simulated dataset and analysis saved.' : $analysis['reason']);
    }

    public function recalculate(Request $request)
    {
        $this->adminAccess($request);
        $selection = $this->selection($request);
        $dataset = $this->insights->newestDataset($selection['instrument'], $selection['timeframe']);
        if (! $dataset) {
            return $request->expectsJson() ? $this->failure('No saved dataset for this symbol and timeframe.', 404)
                : back()->with('warning', 'Generate a simulated dataset first.');
        }
        $analysis = $this->insights->analyze($dataset->id, $request->user()->id);

        return $request->expectsJson() ? $this->success(['analysis' => $analysis])
            : back()->with('success', 'Analysis calculation completed or safely reused.');
    }

    public function executionLogs(Request $request): JsonResponse
    {
        $this->adminAccess($request);

        return $this->success(['logs' => DB::table('market_insight_execution_logs')->orderByDesc('id')->limit(100)->get()]);
    }

    public function priceApi(Request $request): JsonResponse
    {
        $this->readAccess($request);
        $instrument = $this->insights->instrument('XAUUSD');

        return $this->success(['symbol' => 'XAUUSD', 'source' => $this->insights->source(),
            'provider' => $this->insights->source() === 'REAL' ? 'twelvedata' : null,
            'quote' => $this->insights->source() === 'REAL' ? $this->insights->quote($instrument) : null,
            'provider_status' => $this->providerStatus()]);
    }

    public function candlesApi(Request $request): JsonResponse
    {
        $this->readAccess($request);
        $selection = $this->selection($request);
        $data = $request->validate(['limit' => ['nullable', 'integer', 'between:1,300']]);

        return $this->success(['symbol' => $selection['instrument']->symbol, 'timeframe' => $selection['timeframe'],
            'source' => $this->insights->source(),
            'candles' => $this->savedCandles($selection['instrument'], $selection['timeframe'], (int) ($data['limit'] ?? 100))]);
    }

    public function statusApi(Request $request): JsonResponse
    {
        $this->readAccess($request);

        return $this->success(['source' => $this->insights->source(), 'provider' => 'twelvedata',
            'configured' => (bool) config('market_data.api_key'), 'status' => $this->providerStatus()]);
    }

    public function providerApi(Request $request): JsonResponse
    {
        $this->adminAccess($request);

        return $this->success(['provider' => config('market_data.provider'), 'mode' => $this->insights->source(),
            'symbol' => config('market_data.symbol'), 'configured' => (bool) config('market_data.api_key'),
            'supported_timeframes' => array_keys(MarketInsightSettings::TIMEFRAMES),
            'quote_refresh_seconds' => config('market_data.quote_refresh_seconds'),
            'auto_refresh' => config('market_data.auto_refresh'),
            'distribution_allowed' => config('market_data.distribution_allowed'),
            'status' => $this->providerStatus()]);
    }

    public function syncData(Request $request)
    {
        $this->adminAccess($request);
        $data = $request->validate(['timeframe' => ['nullable', Rule::in(array_keys(MarketInsightSettings::TIMEFRAMES))]]);
        $result = $this->sync->sync($data['timeframe'] ?? null, $request->user()->id, true);

        return $request->expectsJson() ? $this->success($result, $result['status'] === 'CONNECTED' ? 200 : 503)
            : back()->with($result['status'] === 'CONNECTED' ? 'success' : 'warning',
                $result['status'] === 'CONNECTED' ? 'Verified XAU/USD data synchronized.' : ($result['message'] ?? 'Synchronization unavailable.'));
    }

    public function archive(Request $request, int $id)
    {
        $this->adminAccess($request);
        $data = $request->validate(['archived' => ['required', 'boolean']]);
        abort_unless(DB::table('market_insight_analyses')->where('id', $id)->exists(), 404);
        DB::table('market_insight_analyses')->where('id', $id)->update([
            'archived_at' => $data['archived'] ? now() : null, 'updated_at' => now(),
        ]);

        return $request->expectsJson() ? $this->success(['id' => $id, 'archived' => (bool) $data['archived']])
            : back()->with('success', 'Insight history visibility updated.');
    }

    private function instruments(): array
    {
        $query = DB::table('market_insight_instruments')->where('is_enabled', true);
        if ($this->insights->source() === 'REAL') {
            $query->where('symbol', 'XAUUSD');
        }

        return $query->orderBy('symbol')->get()->all();
    }

    private function selection(Request $request): array
    {
        $data = $request->validate([
            'symbol' => ['nullable', 'string', 'max:24', 'regex:/^[A-Za-z0-9]+$/'],
            'timeframe' => ['nullable', Rule::in(array_keys(MarketInsightSettings::TIMEFRAMES))],
        ]);
        $defaultSymbol = DB::table('market_insight_instruments')->where('symbol', 'XAUUSD')
            ->where('is_enabled', true)->value('symbol')
            ?? DB::table('market_insight_instruments')->where('is_enabled', true)->orderBy('symbol')->value('symbol')
            ?? 'XAUUSD';
        $symbol = strtoupper($data['symbol'] ?? $defaultSymbol);
        if ($this->insights->source() === 'REAL' && $symbol !== 'XAUUSD') {
            abort(404, 'Real market data is available for XAUUSD only.');
        }
        try {
            return ['instrument' => $this->insights->instrument($symbol), 'timeframe' => $data['timeframe'] ?? 'H1'];
        } catch (InvalidArgumentException) {
            abort(404, 'Trading symbol is unavailable.');
        }
    }

    private function readAccess(Request $request): void
    {
        abort_unless($request->user() && (int) $request->user()->status !== 0, 403);
        if ($this->insights->source() === 'REAL' && ! config('market_data.distribution_allowed') &&
            (app()->environment('production') || ! $this->isAdmin($request))) {
            abort(403, 'Real market data display requires provider licensing approval.');
        }
        if (! $this->isAdmin($request)) {
        abort_unless(MarketInsightSettings::current()['settings']['enabled_for_users'], 403);
        }
    }

    private function providerStatus(): array
    {
        if ($this->insights->source() !== 'REAL') {
            return ['status' => 'DEMO'];
        }
        if (! config('market_data.api_key')) {
            return ['status' => 'NOT_CONFIGURED', 'message' => 'Market data provider has not been configured.'];
        }
        $row = DB::table('market_insight_provider_status')->where('provider', 'twelvedata')->first();

        return ['status' => $row?->status ?? 'NOT_SYNCHRONIZED', 'message' => $row?->message,
            'last_success_at' => $row?->last_success_at, 'last_failure_at' => $row?->last_failure_at,
            'credits_left' => $row?->credits_left];
    }

    private function savedCandles(object $instrument, string $timeframe, int $limit): array
    {
        $dataset = $this->insights->newestDataset($instrument, $timeframe);
        if (! $dataset) {
            return [];
        }

        return DB::table('market_insight_candles')->where('dataset_id', $dataset->id)
            ->where('timeframe', $timeframe)->where('source', $this->insights->source())
            ->orderByDesc('open_at')->limit($limit)
            ->get(['open_at', 'close_at', 'session_date', 'open', 'high', 'low', 'close', 'volume'])
            ->reverse()->values()->map(fn ($row) => (array) $row)->all();
    }

    private function adminAccess(Request $request): void
    {
        abort_unless($this->isAdmin($request), 403);
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();

        return $user && (int) $user->status !== 0 && (in_array((int) $user->role_id, [1, 2], true)
            || $user->roles()->whereIn('roles.id', [1, 2])->exists());
    }

    private function success(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    private function failure(string $message, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
