# AI Market Insight Phase 2 — implementation and release report

Release date: 14 October 2026. Backend: Laravel 10 / PHP 8.2 / MySQL. Website: Blade. Mobile candidate: Flutter 1.8.7+1049. No production deployment or publication was performed.

## Phase 1 compatibility

Phase 1's `MockMarketDataProvider`, six calculators, weighted signal engine, configuration versions, analysis history, authenticated API, admin dashboard, web dashboard, and Flutter Trading Hub screen remain in place. The only Phase 1 defect blocking real data was the candle validator's hardcoded MOCK-only rule. REAL and MOCK datasets, candles, latest responses, histories, and snapshots are now filtered by source. MOCK remains explicitly available for QA. The existing journal, trading signal, and account modules were not changed.

## Real provider and live account verification

The backend uses `https://api.twelvedata.com` with the secret sent only in the `Authorization: apikey ...` HTTP header. The internal `XAUUSD` maps to provider symbol `XAU/USD`, Gold Spot / US Dollar. The configured local credential is in ignored `.env`; it is absent from source, the example environment, API responses, and execution logs.

On 14 October 2026, actual requests succeeded for `/price`, `/quote`, `/time_series` with `15min`, `30min`, `1h`, `4h`, and `1day`, and `/api_usage`. Each timeframe returned 300 requested OHLC rows. `/quote` returned a timestamped quote and `is_market_open`; `/price` returned a positive price but no timestamp. The completed backend sync persisted REAL candles for all five timeframes and available six-indicator consolidated analyses. The `/api_usage` response identified the account as **Basic**, 8 credits per minute and 800 per day. This verifies API access, not permission for external display.

The provider supplied two different D1 rows both labeled `2026-10-04`. The adapter discards the earlier duplicate, records a validation warning, and persists only one completed session per date. Provider H4 bars can start less than four hours apart; when a following bar supplies the actual next boundary, the earlier H4 close boundary is shortened. This preserves chronology and prevents incomplete bars from entering official signals. These provider anomalies merit continued monitoring.

Official provider references: [API documentation](https://twelvedata.com/docs), [XAU/USD instrument](https://twelvedata.com/markets/300755/commodity/xau-usd), [credit usage](https://support.twelvedata.com/en/articles/5713553-control-over-api-usage), [business pricing](https://twelvedata.com/pricing-business), and [commercial usage](https://support.twelvedata.com/en/articles/5332349-commercial-and-personal-usage).

## Data flow and storage

`TwelveDataMarketDataProvider` fetches and validates the quote and completed OHLC bars. `MarketDataSyncService` serializes backend refreshes with a cache lock, stores a shared quote and incrementally upserts unique candles, and calls the existing `MarketInsightService`. That service loads only one source, reuses `TechnicalIndicatorService` and `MarketInsightSignalEngine`, and stores versioned consolidated analysis snapshots. The dataset revision increases when verified real candles change. All frontends read persisted backend results; opening a page never requests Twelve Data.

Migration `2026_10_14_000002_add_real_market_data_support.php` extends datasets with provider, revision and source metadata; extends candles with provider-session date and retrieval time; changes the analysis uniqueness key to include dataset revision and analysis candle; and adds `market_insight_quotes` plus `market_insight_provider_status`. Stored OHLC/quote prices remain decimal columns, while the existing indicator engine calculates floating-point results. Empty provider volume remains null and does not block OHLC indicators.

For intraday intervals, the provider is requested with `timezone=UTC`. Twelve Data ignores that parameter for D1, which uses its local session date. The adapter maps that date through configurable `Australia/Sydney` timezone and daylight-saving transitions; ADR groups intraday bars by that provider-session date and averages completed D1 sessions. The provider response did not include an exchange timezone for XAU/USD, so exact daily-session alignment is an explicit assumption pending confirmation from Twelve Data. The account's market-open flag, rather than a hand-coded weekday calendar, governs closure handling.

## Backend API and clients

Existing authenticated route prefix: `/api/mobile/v1/market-insights`. Source-aware endpoints are `GET /instruments`, `/latest`, `/history`, `/{id}`, `/price`, `/candles`, and `/status`, plus `POST /refresh` for recalculating saved completed candles. Admin-only endpoints include `GET /admin/provider`, `/admin/settings`, `/admin/logs`, `POST /admin/sync`, `/admin/recalculate`, `/admin/generate`, and existing settings/history actions. `GET /candles` validates timeframe and limits output to 300. No endpoint accepts an arbitrary provider URL or returns the credential.

The website displays the persisted real quote, provider, timestamps, status, completed-candle SVG chart, six indicator values, score, signal, factors, and risks. The existing Flutter screen displays the same API analysis and quote with REAL/MOCK source labels, timestamps, status, and all six indicators. Flutter contains no market-data credential or indicator implementation. Old historical snapshots remain marked by their original source; stale current analyses become unavailable while history remains inspectable.

In REAL mode the environment variable `MARKET_DATA_DISTRIBUTION_ALLOWED=false` restricts the live feed to administrator QA on non-production environments. Production real-data read routes reject access while that flag remains false. The Basic account is limited to internal/non-production testing and does not authorize public website, multi-user, or mobile display. Obtain the appropriate Twelve Data business display/redistribution agreement and any instrument-specific approval before enabling the flag. Confirm permitted caching/retention duration and attribution requirements as part of that agreement.

## Refresh, quota and failure behavior

Use `php artisan market-insight:sync --force` once for initial backfill. Thereafter Laravel's scheduler registers `market-insight:sync` every minute only when `MARKET_DATA_MODE=REAL` and `MARKET_DATA_AUTO_REFRESH=true`. The command requests a new quote no more often than every five minutes and checks each timeframe only after a new completed bar plus the configurable publishing latency should exist. Dataset update times are interpreted in Laravel's configured timezone; OHLC boundaries remain UTC. A shared lock prevents duplicate jobs. Repeated page loads read the database. Network/server errors have bounded retries; rate limits, invalid keys, bad JSON/OHLC, missing history, and no configuration return sanitized explicit states. There is no REAL-to-MOCK fallback.

At one credit per request, a continuously open day would use roughly 288 quotes + 96 M15 + 48 M30 + 24 H1 + 6 H4 + 1 D1 = **463 credits/day**, before retries, manual syncs and any extra monitoring calls. That is below the measured 800/day Basic limit but not a promise of uninterrupted access. Manual forced all-timeframe sync requests six endpoints and can exceed the eight-credit minute cap if run near a scheduled refresh. The five-minute quote interval is deliberate despite the original suggested 60-second target.

Status distinguishes `NOT_CONFIGURED`, `CONNECTED`, `IDLE`, `AUTH_ERROR`, `RATE_LIMITED`, `INVALID_DATA`, `INSUFFICIENT_HISTORY`, `STALE`, `MARKET_CLOSED`, and `LATEST_AVAILABLE` as applicable. The UI never calls a quote LIVE merely because an HTTP response succeeded. A stale quote or analysis retains its verified history and loses its current actionable signal.

## Deployment checklist for the site owner

1. Back up production files and database. Deploy the Phase 1 Market Insight code/migration if it is not already present; the previous production `market_insight_instruments` missing-table error indicates that Phase 1's migration may still be absent there.
2. Deploy the Phase 2 PHP, routes, config, views and migration together. Run `php artisan migrate --path=database/migrations/2026_10_14_000001_create_market_insight_tables.php --force` if Phase 1 is pending, then `php artisan migrate --path=database/migrations/2026_10_14_000002_add_real_market_data_support.php --force`. Check `php artisan migrate:status` first. Do not rerun an already recorded migration.
3. In the **server's private `.env`**, set `MARKET_DATA_PROVIDER=twelvedata`, `TWELVE_DATA_API_KEY` to the credential, `MARKET_DATA_SYMBOL=XAU/USD`, `MARKET_DATA_MODE=REAL`, `MARKET_DATA_AUTO_REFRESH=true`, and leave `MARKET_DATA_DISTRIBUTION_ALLOWED=false` until licensing is approved. Then clear/rebuild Laravel config cache using the hosting process.
4. Verify the server's PHP executable and configure one cPanel cron entry to run `artisan schedule:run` every minute. Do not start a permanent queue worker on shared hosting solely for this feature. Run one manual `market-insight:sync --force` and inspect the administrator provider status/logs. Keep the website/mobile display closed under the licensing flag.
5. After written rights, caching, retention and attribution terms are confirmed, enable distribution intentionally, deploy the matching Flutter candidate, and recheck all five intervals, both clients, stale/closed states, and API quota in the actual hosting environment.

## QA and current limits

Local MySQL migration applied; genuine provider requests and full all-timeframe backend synchronization passed. Deterministic feature tests cover missing/invalid keys, 429 responses, strict source isolation, completed bars, duplicate D1 labels, H4 session boundaries, all six indicators, idempotent sync, quote/API/web display, and blocked standard-user access without rights. The full Laravel and Flutter regression suites passed, with one pre-existing Laravel skip. The signed Flutter release candidate is built locally; it is not deployed or published.

Production connectivity, cron operation, client-facing licensing, exact provider D1 timezone/session contract, holidays, and provider delay guarantees remain **blocked by external confirmation or deployment**. No Phase 3 prediction, win-probability claim, alerting, or automatic trading is included.
