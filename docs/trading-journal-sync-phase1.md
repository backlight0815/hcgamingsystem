# Trading Journal Phase 1 integration

Follow-up: [13–14 October repair and actual Android/HTTP QA report](trading-journal-repair-20261013.md).
The historical results below are retained; current source/build status is in that report.

Date: 11 October 2026. This describes the local implementation. Production deployment and authenticated device verification remain separate release steps.

## Architecture review

- The website uses Laravel 10, MySQL, Sanctum for the existing `/api/mobile/v1` endpoints, and `trading_journals` owned by `users.id`. Website journal forms and existing mobile journal endpoints already create, update, delete and calculate trade results. Existing website analytics also use `TradingJournalAnalytics` and account/prop-firm filters.
- The Flutter application signs in with Firebase Auth and stores manual journal documents at `users/{firebase_uid}/trading_journals/{document_id}` in Firestore. Firestore supplies offline persistence. The mobile `TradingRepository` previously selected Firestore mode for journal CRUD, so the existing Laravel journal CRUD was not used by the release app. No cross-platform journal identity or change cursor existed.
- The previous signal and blog API integrations established `ApiClient` with Firebase ID-token transport and cPanel merge-only ZIP releases. Their routes and controllers are retained.
- A Firebase-only identity is **not** silently provisioned as a Laravel user. Journal access needs a verified Firebase email matching exactly one existing Laravel user, plus a persistent one-to-one Firebase UID mapping. Sanctum tokens also work for existing website/API clients. If a mapping is absent or ambiguous, synchronization stops with HTTP 401 and leaves both stores intact.
- The example account in the brief exists once in the **local** website database and has 32 journal rows. Only counts were read; no real journal record was edited for QA. Its Firebase session and production account mapping were unavailable for an authenticated acceptance test.

## Field mapping

| Category | Website / API | Flutter / Firestore | Rule |
|---|---|---|---|
| Identity | Integer `id`, `user_id` | Firebase UID, Firestore document ID | `sync_id` ledger ID and `mobile_id` join the two; server resolves website `user_id` |
| Common required | `open_date`, `pair`, `direction`, `entry_price`, `lot_size` | Same fields | Required for a new trade; omitted update fields stay unchanged |
| Common close data | `status`, `close_date`, `exit_price`, `pips`, `profit_loss`, `result` | Same fields | Closed trades require complete close data; open trades use nullable close values |
| Common optional | `notes`, psychology fields, mistake tags, close handling, input timezone | Same or nullable | Explicit null clears a nullable field; omission never clears it |
| Website only | `capital`, `trade_date`, `trading_reason_id`, prop-firm IDs | Not exposed in journal form | Preserved in the original row; `trade_date` follows a supplied close date |
| Mobile only | Fees, stop/take profit, setup/session, strategy/plan checks, source tickets, currency | Firestore journal fields | Stored in `mobile_metadata` JSON and returned to mobile; no new website UI fields |
| Mobile attachments | `trading_journal_attachments` is a separate existing API model | Firestore attachment references and Firebase Storage | Existing Firestore references remain intact. Cross-device transfer of Firebase Storage files is **not implemented** by this journal sync release. |
| Calculated | Website `profit_loss` and website analytics | Flutter local reports | The new statistics API reads synchronized website rows; existing detailed app report formulas remain in place. |

The precision migration widens website entry/exit prices and quantities to `DECIMAL(18,8)` and pips to `DECIMAL(18,5)` on MySQL. It does not recalculate historical trade values. `profit_loss` remains the website's existing `DECIMAL(15,2)` realized amount; fees are preserved separately in mobile metadata rather than silently subtracted from historical P&L.

## API contract

All new routes are under `/api/mobile/v1/journal-sync` and require a valid Sanctum token or a verified Firebase ID token mapped to one existing Laravel account. They use the existing `{success,message,data}` envelope.

| Method/path | Purpose |
|---|---|
| `GET /` | Paginated owned records, with symbol, status and date filters |
| `GET /{syncId}` | One owned record; another user's ID returns 404 |
| `POST /` | One idempotent create using `mobile_id` and `fields` |
| `PATCH /{syncId}` | Partial update with `mobile_id`, `base_revision`, `base`, and changed `fields` |
| `DELETE /{syncId}` | Revision-checked tombstone; accepts `mobile_id` and `base_revision` |
| `GET /changes?cursor=0&limit=100` | Server-issued incremental event cursor, up to 200 per page |
| `POST /batch` | Up to 50 independent create/update/delete operations; returns per-item status |
| `GET /statistics?from=...&to=...` | Owned journal totals and realized P&L for the optional open-date range |

Batch operation example:

```json
{
  "operations": [
    {"type":"create","mobile_id":"firestore-doc-123","fields":{"status":"closed","open_date":"2026-10-01T08:00:00","close_date":"2026-10-01T09:00:00","pair":"XAUUSD","direction":1,"entry_price":"2350.50","exit_price":"2365.00","lot_size":"1.00","profit_loss":"145.00","result":1}}
  ]
}
```

Responses return `created`, `linked`, `updated`, `unchanged`, `deleted`, `conflict`, `reconciliation_required`, `not_found`, or `failed` per item. Single-record conflict endpoints use HTTP 409. A `record` contains `sync_id`, `website_record_id`, `mobile_id`, `revision`, `deleted_at`, existing website columns, and available mobile metadata. Unknown client fields and client-supplied user IDs cannot be mass assigned. A missing optional field is not modified; explicit null is validated before clearing. A same-field concurrent edit returns a conflict, while changes to independent fields merge when the client supplies their base values. One unique closed legacy trade links automatically only if its close values and shared optional fields also match; matching only date, symbol, direction and entry price never links it. Divergent or ambiguous matches return `reconciliation_required` with candidate sync IDs and leave both copies intact. After reviewing the website candidate, a client can explicitly link it through create with its owned `sync_id`, current `base_revision`, `confirm_match: true`, original `mobile_id`, and `fields`. This selects the website's shared field values and retains the supplied mobile-only metadata. The current Flutter UI flags unresolved matches for review; it does not yet offer this explicit selection in the app.

## Sync and statistics behavior

The Laravel journal is the cross-platform authority. A ledger row maps each website journal to an optional Firestore document ID. Eloquent create/update/delete hooks record sequential change events, including website form actions and import/archive paths adjusted in this release. A website delete leaves a tombstone in the ledger so an older mobile create cannot resurrect it. Legacy website rows receive ledger identities lazily when first listed or synchronized; their trade data is untouched.

On login, journal opening, manual refresh, and Firestore reconnection, the app uploads Firestore changes first, then consumes all event pages after its saved cursor. Firestore stays the offline store. A mobile deletion is saved to a per-user durable local queue before the Firestore document is removed; the queue item is removed only after server acknowledgment. Each downloaded page advances the locally stored server cursor after its Firestore writes. Linking emits a new server event; the app removes a prior `website_<sync_id>` Firestore alias after writing the linked mobile document. Conflicts leave the local document intact and mark it for review. There is no periodic poll.

The statistics endpoint counts all owned manual trades, excludes incomplete trades from win/loss/P&L metrics, returns null for undefined win rate and average, and uses decimal arithmetic for realized P&L totals. It returns total/closed/winning/losing/breakeven counts, win rate, total realized profit/loss and average realized P&L. The Flutter Journal displays this authoritative snapshot alongside its existing reports. Detailed website prop-firm scores and the app's MT5 reports are outside this Phase 1 journal statistics contract.

## Verification performed locally

- `php artisan test --compact`: 134 passed, 1 skipped, 4,764 assertions.
- `php artisan test --filter=TradingJournalSyncApiTest`: 12 passed, 67 assertions. Covers initial website download, mobile upload, retry idempotency, owner isolation, verified identity, omitted and explicitly null fields, merge/conflict, exact legacy linking and manual reconciliation, website edit/delete events, tombstone, statistics, partial batch failure and cursor paging.
- `flutter analyze --no-pub`: passed after the sync implementation.
- `flutter test test/trading_journal_sync_model_test.dart`: 2 passed.
- `flutter test test/trading_blog_api_service_test.dart`: passed after the Firebase initialization regression was fixed.
- `flutter build appbundle --release --no-pub`: built the release-signed 1.8.7+1044 Android bundle at `mobile/hc_trader/releases/HC-Trader-1.8.7-build-1044-journal-sync.aab` from the final Flutter source.
- Full `flutter test`: 601 passed, 3 failures in existing analytics/performance widget text expectations (`analytics_redesign_test.dart`, `performance_module_test.dart`). The new journal tests passed. Full-suite green remains outstanding.
- The MySQL precision ALTER was exercised against a temporary structure-only clone of `trading_journals`; all four target types widened as intended. No real records were copied or altered.
- `php artisan migrate --pretend` produced the expected additive ledger DDL and MySQL precision-widening SQL. No production migration was run.

## Production deployment status and procedure

The established release method is a merge-only ZIP with top-level `hcgaming/` extracted from the cPanel home directory into `/home/<cpanel-user>/hcgaming`. The PHP app is separate from `public_html`. Existing `.env`, `vendor`, `storage`, uploads, and production-specific bootstrap files must remain in place. No cPanel session, SSH target or deployment credential was available in this workspace, so **production is not deployed or verified**.

Before release, take a timestamped cPanel database export and an application backup outside `public_html`, verify the live app path/PHP version, confirm `bcmath` and `FIREBASE_WEB_API_KEY`, and compare the packaged routes/model files against production. Extract the ZIP, then run the site's PHP binary from `~/hcgaming`:

```sh
php artisan optimize:clear
php artisan migrate --force
php artisan route:list --path=api/mobile/v1/journal-sync -v
```

Test HTTPS unauthenticated access (must return 401 JSON), then a disposable verified Firebase/Laravel-mapped test account for initial download, upload, partial update, deletion, cursor paging, conflict and statistics. Check website journal listing/CRUD and existing signal/blog APIs. Do not mutate the example user's real trades. Only after backend verification should the signed Android bundle be distributed. For code rollback, restore the backed-up files and clear caches. The ledger tables may remain; the precision migration deliberately has no data-losing `down()`.

## Remaining Phase 1 acceptance gaps

Production deployment, live authenticated bidirectional smoke tests, physical Android verification, and the full Flutter suite's three unrelated widget failures remain open. Firebase Storage attachment transfer across devices and detailed report formula parity are not implemented by this release. No Phase 2 APIs were changed.
