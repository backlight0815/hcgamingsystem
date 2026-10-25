# Trading Signal API Integration

## Architecture

Laravel/MySQL is the single source of truth for trading signals. Website-created and mobile-created signals use the same `trading_signals` rows, lifecycle rules, audit trail, and delivery jobs.

The Flutter app reads, submits, and updates signals through the Laravel API. Firebase remains the mobile identity provider and push transport; it is no longer the signal database. A Firebase ID token is mapped to the matching Laravel user by normalized email. Laravel Sanctum tokens are also accepted, which is useful for server-side clients and automated tests.

Publication fan-out is asynchronous:

1. Laravel commits the signal and audit record.
2. A published event is emitted for server broadcast consumers.
3. Discord and Firebase Cloud Messaging jobs are placed on the `notifications` queue.
4. The Flutter app refreshes on its authenticated SSE stream and on FCM notification delivery.

Discord or FCM failure never rolls back or deletes a valid signal.

## API

Base path: `/api/mobile/v1/signals`

Every request requires `Authorization: Bearer <token>` and `Accept: application/json`. The bearer token may be a Laravel Sanctum token or a Firebase ID token. The corresponding Laravel account must exist and be active.

| Method | Path | Required policy | Input and result |
| --- | --- | --- | --- |
| `GET` | `/` | `viewAny` | Query: `status`, `source`, `symbol`, ISO-8601 `since`, `per_page`; returns newest-first signals and pagination |
| `GET` | `/stream` | `viewAny` | Authenticated SSE change stream; reconnect after each 25-second response |
| `POST` | `/` | `create` | JSON signal body plus `X-Idempotency-Key`; returns `201` signal or replayed `200` signal |
| `GET` | `/{signal}` | `view` | Returns one visible signal |
| `PUT` | `/{signal}` | `update` | Partial JSON body; optional `expected_version`; returns updated signal |
| `POST` | `/{signal}/publish` | `publish` | No body; approves and publishes a draft/pending signal |
| `POST` | `/{signal}/cancel` | `cancel` | Optional JSON `reason`; cancels a non-terminal signal |
| `POST` | `/{signal}/close` | `close` | No body; closes a published signal |
| `POST` | `/{signal}/lifecycle` | owner lifecycle or manage | JSON action, idempotency key, optional version and action fields; returns updated signal |

Successful responses use:

```json
{
  "success": true,
  "message": "Signal submitted for approval.",
  "data": {}
}
```

Errors consistently use:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "symbol": ["The symbol field is required."]
  }
}
```

Expected status codes are `200`/`201`, `401`, `403`, `404`, `409`, `422`, and `429`.

An absent, expired, or unmapped token returns `401`. An authenticated account without the endpoint policy returns `403`. A missing/invisible signal returns `404`; a stale version or conflicting idempotency key returns `409`. Realtime disabled by configuration returns `503` on the stream endpoint.

### Submit a signal

Send a stable `X-Idempotency-Key` for every user action. Retrying the same payload and key returns the original signal with `idempotent_replay: true`. Reusing the key for different signal content returns `409`.

```json
{
  "symbol": "XAUUSD",
  "market": "FOREX",
  "signal_type": "BUY",
  "order_type": "buy_limit",
  "entry_price": "2650.50",
  "entry_price_min": "2649.00",
  "entry_price_max": "2652.00",
  "stop_loss": "2635.00",
  "take_profit_1": "2665.00",
  "take_profit_2": "2680.00",
  "risk_level": "MEDIUM",
  "timeframe": "H1",
  "strategy": "London breakout",
  "description": "Wait for confirmation before entry.",
  "expires_at": "2026-10-08T04:00:00Z"
}
```

Prices accept at most eight decimal places. For BUY/LONG, stop loss must be below entry and targets must increase away from entry. SELL/SHORT uses the inverse ordering. `CLOSE` and `HOLD` do not require prices.

By default mobile submissions become `PENDING`. Set `AUTO_PUBLISH_MOBILE_SIGNAL=true` only if the role policy and operational process permit immediate publication. Auto-publish still requires the authenticated user to have the publish role.

### Lifecycle updates

Lifecycle requests require an `idempotency_key`. Supplying `expected_version` enables optimistic concurrency; stale versions return `409`.

The endpoint supports the Flutter lifecycle vocabulary: publication and detail edits; entry price/range activation; stop-loss, break-even, trailing, risk reduction, and profit-lock changes; partial closes and ordered target hits; close/cancel/expire outcomes; commentary, warning, chart, and analysis records; and manager-only correction, archive, and restore actions. Action availability is checked against the current lifecycle state. Risk-increasing stop changes require the manager correction flow, target hits must normally be sequential, and partial-close percentages cannot exceed the remaining position.

Lifecycle state, current entry/stop/break-even prices, hit targets, current target, remaining percentage, terminal timestamps, archive state, last action, and optimistic version are projected onto the signal row. The full safe action payload is also retained in the immutable audit record. The row is locked while idempotency, version, transition validation, the state change, and its audit record are committed, preventing concurrent retries from applying an action twice.

Example:

```json
{
  "action": "target_hit",
  "target_number": 1,
  "target_price": "2665.00",
  "expected_version": 2,
  "idempotency_key": "device-42-signal-1001-tp1"
}
```

## Permissions and visibility

Role IDs are configured independently for create, publish, and manage actions. Administrators can see all workflow states. Other authenticated users see all publicly visible signals plus their own pending/draft submissions. Ownership and source identity are assigned from the authenticated user; client-supplied user or source fields are ignored.

Website signal creation routes now require authentication and apply the same signal policy. The website list exposes source and Discord delivery state so moderators can distinguish website and mobile submissions.

## Operational flows

Website to mobile:

`Website admin -> authenticated create/publish -> database transaction -> published event/FCM job -> authenticated SSE hint -> Flutter REST refresh -> signal detail`

Mobile to website and Discord:

`Mobile publisher -> Firebase bearer token -> create policy -> validation/idempotency -> database transaction -> pending website row -> admin approval -> Discord queue -> Discord webhook`

Reconnect recovery:

`App start/network recovery -> paginated GET signals -> replace local authoritative snapshot -> persist last-known cache -> update UI`

## Database and migration

Run:

```shell
php artisan migrate --force
```

The migrations add source/workflow metadata, decimal-safe price mirrors, timestamps, delivery state, optimistic versioning, lifecycle projection fields, an audit table, and the `(source_user_id, client_request_id)` idempotency constraint. They backfill existing website rows, including terminal lifecycle state, current price projections, hit targets, and decimal price values. Legacy string price columns stay in place for existing website compatibility.

The migration creates Laravel `jobs` and `failed_jobs` tables only when missing. Its rollback deliberately leaves those shared infrastructure tables intact.

## Runtime configuration

Copy the signal variables from `.env.example`. Important settings are:

- `FIREBASE_WEB_API_KEY`: verifies Firebase ID tokens through Identity Toolkit.
- `FIREBASE_PROJECT_ID`, `FIREBASE_CLIENT_EMAIL`, `FIREBASE_PRIVATE_KEY`: optional FCM HTTP v1 service account credentials.
- `DISCORD_SIGNAL_WEBHOOK_URL`: server-side fallback webhook; never embed this in Flutter.
- `SIGNAL_*_ROLE_IDS`: comma-separated authorization policy roles.
- `SIGNAL_REALTIME_ENABLED`: enables the authenticated SSE endpoint.
- `AUTO_PUBLISH_MOBILE_SIGNAL`: defaults to `false` for moderation.
- `APP_TIMEZONE=UTC`: keeps database/application timestamps consistent; `SIGNAL_DISPLAY_TIMEZONE` controls operational message display.

After environment changes:

```shell
php artisan config:clear
php artisan cache:clear
php artisan queue:work --queue=notifications,default --tries=3
```

Use a process supervisor in production. Keep `QUEUE_CONNECTION=database` or select another durable asynchronous driver. Do not use `sync` for production delivery.

## Flutter configuration

The app supplies the current Firebase ID token to the shared HTTP client, maintains a local last-known public-signal cache, reconnects the SSE stream, and refreshes from REST after realtime events. Private pending submissions are deliberately not written to the cross-session offline cache. Submission IDs are generated once per open publisher sheet so user retries are idempotent.

For a non-default backend URL, build or run with:

```shell
flutter run --dart-define=HC_API_BASE_URL=https://www.hctradinghub.com/api/mobile/v1
```

FCM clients subscribe to the `hc_trading_signals` topic when signal notifications are enabled. Keep `FIREBASE_SIGNAL_TOPIC` aligned with that mobile topic name.

## Verification

Backend integration coverage is in `tests/Feature/TradingSignalApiTest.php`; Flutter transport/model coverage is in `mobile/hc_trader/test/trading_signal_api_service_test.dart`.

Run:

```shell
php artisan test --filter=TradingSignalApiTest
cd mobile/hc_trader
flutter analyze
flutter test
```

Before production rollout, perform one external smoke test using production-equivalent Firebase, Discord, HTTPS, queue worker, and a physical mobile device. Verify mobile submit, website moderation/publish, website-to-mobile refresh, exactly one Discord message, one push notification, and retained signal data while the Discord webhook is intentionally unavailable.

## Rollback

Disable new entry points first with the signal role lists and `SIGNAL_REALTIME_ENABLED=false`, stop the queue worker, and deploy the previous app version. Database rollback removes the signal-specific columns and audit table, so export or retain a database backup first. Shared queue tables remain available. Because the legacy website columns were preserved, a code rollback does not require transforming signal price data.
