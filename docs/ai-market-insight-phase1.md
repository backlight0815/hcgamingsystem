# AI Market Insight — Phase 1 engineering and QA

Release date: 14 October 2026. Phase: 1. Method: **Rule-Based Indicator Analysis**. Market data source: **MOCK** only. This module is an educational simulator; its synthetic prices, signals, directional scores and agreement values are neither live quotes nor advice or win probabilities.

## Architecture

The existing Laravel 10 API is the single source of indicator and signal calculations for the Blade backoffice and Flutter Trading Hub. `MarketDataProvider` abstracts loading; `MockMarketDataProvider` creates and persists versioned candles. `CandleValidator` verifies completed, chronological, positive OHLC candles and timeframe intervals. `TechnicalIndicatorService` computes six structured results. `MarketInsightSignalEngine` normalizes directional weights and assigns a signal, then delegates risk to `RiskContextEvaluator` and the explanation to `InsightExplanationGenerator`. Their Phase 1 implementations are deterministic and can be replaced later without changing the UI contract. `MarketInsightService` validates datasets, handles freshness and deduplicated versioned snapshots, and records execution logs. The controller enforces roles server-side and exposes web and JSON interfaces.

The migration `2026_10_14_000001_create_market_insight_tables.php` adds six namespaced tables for instruments, configurations, datasets, candles, analyses and execution logs. It seeds five configurable instrument definitions and settings v1. It does **not** generate candles during migration. No existing trading journal, account or signal records are changed. Each analysis stores the configuration snapshot, configuration version, engine version, dataset ID/version, price, indicator results, contribution weights and complete explanation. The `(dataset_id, configuration_version, engine_version)` unique key and transaction prevent duplicate results on repeated refresh.

Implementation files: `database/migrations/2026_10_14_000001_create_market_insight_tables.php`; `app/Services/MarketInsight/` (provider, validator, six calculators, signal, risk, explanation, settings, orchestration); `app/Http/Controllers/MarketInsightController.php`; `app/Console/Commands/SeedMarketInsightDemo.php`; `app/Providers/AppServiceProvider.php`; `routes/web.php`; `routes/api.php`; `resources/views/market_insight/{dashboard,management}.blade.php`; `resources/views/admin/body/sidebar.blade.php`; `mobile/hc_trader/lib/{services/market_insight_api_service.dart,features/hub/market_insight_screen.dart,features/hub/trading_hub_screen.dart}`; `mobile/hc_trader/pubspec.yaml`; `mobile/hc_trader/PLAY_STORE_RELEASE.md`; `tests/{Unit/MarketInsightIndicatorTest.php,Feature/MarketInsightApiTest.php}`; `mobile/hc_trader/test/{market_insight_api_service_test.dart,market_insight_screen_test.dart}`; and this document.

## Calculations and defaults

| Indicator | Formula and scoring | Default |
|---|---|---|
| Bollinger Bands | SMA ± multiplier × population standard deviation; %B and relative width. Vote only on completed-candle return inside after a prior close outside the corresponding band. | 20, 2σ, 20 weight |
| EMA | SMA-seeded exponential average, α = 2/(period+1). Compare fast with slow; report slope, separation and last-candle crossover. | 9/21, 30 weight |
| MACD | EMA(fast) − EMA(slow), then SMA-seeded EMA signal; histogram = line − signal. Vote on line above/below signal, not histogram movement. | 12/26/9, 30 weight |
| RSI | Wilder-smoothed gains and losses. Recovery through 30 or reversal through 70 may vote; 55–70 bullish, 30–45 bearish, otherwise neutral. Extreme values alone are warnings. | 14, 30/70, 20 weight |
| ATR | Wilder-smoothed true range with max(high−low, abs(high−previous close), abs(low−previous close)); normalize to close and compare with prior rolling normalized ATR baseline. | 14, baseline 20, ratios .7/1.5, 0 weight |
| ADR | Average of completed daily high−low ranges, even for intraday charts; compare UTC demo session range with ADR. | 14 daily sessions, ratios .7/1.25, 0 weight |

Enabled directional weights are renormalized to 100. Score = Σ(direction × normalized weight), bounded to −100…100. Defaults: ≤−70 STRONG SELL, ≤−25 SELL, <25 NEUTRAL, <70 BUY, otherwise STRONG BUY. Signal strength is absolute score. Weighted agreement is the sum of enabled directional weights agreeing with the score sign. ATR, ADR and conflicting directional votes produce **risk warnings** without changing the score or direction. Disabled indicators appear as disabled and contribute nothing. A missing enabled indicator or stale/invalid dataset yields unavailable rather than a fabricated neutral signal.

## Dataset generation and local use

Run the existing migrations, then open **AI Market Insight Management** as a role 1/2 administrator and generate a symbol/timeframe, scenario and seed. For repeatable CLI fixtures:

```text
php artisan migrate
php artisan market-insight:seed-demo --symbol=XAUUSD --timeframe=H1 --scenario=rising --seed=41821
php artisan market-insight:seed-demo --all --scenario=sideways --seed=41821
```

`--all` explicitly generates all enabled instrument/timeframe combinations. Supported timeframes: M15, M30, H1, H4, D1. Scenarios: rising, falling, sideways, volatile. Seed and UTC anchor make generation repeatable; default anchor is the current completed timeframe boundary. Regeneration creates a new dataset version and retains old data. Prices never regenerate on ordinary page loads or user refreshes. Weekday and all-day calendar definitions are stored per instrument. All returned candles carry `source: MOCK` and `is_complete: true`.

## Web and API contract

- Web: `GET /market-insight`, `POST /market-insight/refresh`, `GET /market-insight/history/{id}`. Admin: `GET /admin/market-insight`, `POST /admin/market-insight/settings`, `/instruments`, `/instruments/{id}`, `/generate`, `/recalculate`, `/history/{id}/archive`.
- JSON base: `/api/mobile/v1/market-insights`, authenticated with the existing mobile signal middleware (Sanctum or Firebase ID token). Standard endpoints: `GET /instruments`, `/latest?symbol=XAUUSD&timeframe=H1`, `/history?symbol=XAUUSD&timeframe=H1`, `/{id}`, and `POST /refresh` with symbol/timeframe.
- Admin JSON beneath the same base: `GET/PUT /admin/settings`, `POST /admin/instruments`, `PUT /admin/instruments/{id}`, `POST /admin/generate`, `POST /admin/recalculate`, `GET /admin/logs`, `PATCH /admin/history/{id}/archive`.

The success envelope is `{ "success": true, "data": ... }`. Analysis `contract_version` is 1 and includes `engine_version`, `configuration_version`, source/mock flags, symbol/timeframe, data and analysis timestamps, demo price, all six indicators with values/classifications/explanations/configuration, directional contributions, score/strength/agreement, factor lists and counts, risk warnings, and conclusion. A 200 response with `status: empty` means no generated dataset; `status: unavailable` means stale/missing indicator data. Invalid inputs return validation errors; admin routes return 403 to non-admin users. Archived snapshots are hidden from standard users. An administrator can inspect and restore them.

The web dashboard displays the same saved JSON as Flutter and the API. Flutter's AI Insight tab requests this contract; it contains no independent indicator implementation. Standard web access follows the existing web session, account status and existing trader onboarding middleware. The server checks admin role IDs 1/2, including pivot role assignments, for every admin mutation.

## Verification and limits

Focused PHPUnit unit fixtures verify independent Bollinger, EMA, MACD, Wilder RSI, ATR and ADR values, scoring/normalization, risk warnings, invalid candles and missing data. Feature tests cover auth, response shape, persistence, deterministic seeds, stale data, versioned settings/history, archived records, admin web forms and duplicate refresh. Flutter tests verify authenticated API usage, failure propagation and 360-pixel screen rendering. The Laravel suite, Flutter suite and targeted static analysis pass; the signed Android release AAB built successfully. The migration was applied to local MySQL and all 25 default symbol/timeframe combinations were seeded explicitly. Headless Chrome checks of standard and admin views at 1440, 768 and 360 pixels returned HTTP 200, showed the demo notices and had no horizontal page overflow. Disposable local QA users were removed after that check.

The Phase 1 calendar is deliberately a simple UTC demo calendar; real exchange sessions, holidays, licensed market feeds and intraday session aggregation from a provider belong to Phase 2. The synthetic prices have no statistical relationship to market outcomes. Historical signals are retained, but there is no outcome or accuracy measurement. There is no external market-data API, live refresh scheduler, LLM call, alerting or trade execution.

## Future Phase 2 specification — not implemented

Introduce a separately configured real `MarketDataProvider` and provider-specific candle repository/session calendars/timezones. Require explicit source selection and source labels in all persisted datasets and API results; never fall back to MOCK if a real provider fails. Validate provider OHLC history with the existing validator extended for holidays/gaps, provenance, latency and completed-candle cutoffs. Add licensed credentials via environment/secrets, rate-limit and retry policy, cache with source-specific TTL, market-closed states, stale/error UI, scheduled refresh by symbol/timeframe, and idempotent provider candle IDs. Compare the six indicator outputs against reference fixtures on real historical data, test no look-ahead, and run a monitored staging pilot before production deployment.

## Future Phase 3 specification — not implemented

Add a grounded explanation interface that receives the immutable indicator result, source, timestamps and configuration version; store generated text separately so it cannot overwrite deterministic facts. Multi-timeframe alignment and conflict analysis should use completed, source-consistent candle sets. Alerts and watchlists require opt-in and rate controls. Historical signal outcome evaluation/backtesting must document entry/exit assumptions, costs, slippage, survivorship and sample limits before any accuracy or confidence percentage is displayed. No automatic trade execution is in scope.
