# MT5 Refresh/Disconnect Fix Report

Created: 2026-07-05 16:52

## Backup Created

- Backup branch: `backup-before-mt5-refresh-disconnect-fix-20260705-1632`
- Development branch: `mt5-refresh-disconnect-fix-20260705-1632`
- Patch backup: `backups/backup-before-mt5-refresh-disconnect-fix-20260705-1632.patch`
- Untracked inventory: `backups/backup-before-mt5-refresh-disconnect-fix-20260705-1632-untracked-files.txt`
- Rollback file: `backups/ROLLBACK_MT5_REFRESH_DISCONNECT_FIX.md`
- Baseline report: `mt5_sync/BASELINE_REFRESH_DISCONNECT_REPORT.md`

## Root Causes

- Connected status stayed visible because Flutter only reloaded records when the trade activity marker changed. A connection-status change with no trade activity did not refresh the status card, and freshness was inferred from broad cached timestamps instead of `last_connection_check_at` / `last_heartbeat_at`.
- Manual Refresh could miss visible changes because it did not warm/read all MT5 slices first, older paged history could overwrite fresher realtime records with the same key, and historical orders were not included in the controlled refresh set.
- Load More History only queried the `trades` collection, so historical order records could be missed. Pagination also needed stronger account-scoped ordering and dedupe.

## Files Added

- `backups/ROLLBACK_MT5_REFRESH_DISCONNECT_FIX.md`
- `mt5_sync/BASELINE_REFRESH_DISCONNECT_REPORT.md`
- `mt5_sync/REFRESH_DISCONNECT_FIX_REPORT.md`

## Files Modified

- `mt5_sync/run_sync_service.py`
- `mt5_sync/db_adapter.py`
- `mobile/hc_trader/lib/models/mt5_sync_models.dart`
- `mobile/hc_trader/lib/repositories/trading_repository.dart`
- `mobile/hc_trader/lib/features/journal/journal_screen.dart`
- `mobile/hc_trader/lib/core/localization/app_localizations.dart`
- `mobile/hc_trader/test/trading_services_test.dart`

## Database Fields

The sync state now uses/reads these fields:

- `sync_status`, `display_status`, `account_number`, `broker_server`, `updated_at`
- `last_successful_sync_at`, `last_connection_check_at`, `last_heartbeat_at`
- `last_trade_activity_at`, `last_trade_change_version`
- `last_error_code`, `last_error_message_internal`
- `sync_stale_after_seconds`

The MT5 account document mirrors connection/activity timestamps for fallback display.

## How It Works

- Disconnect detection: Python writes closed/logged-out/disconnected/error state even when MT5 is unavailable. Offline-state writes refresh at the reconnect interval, and Flutter treats connected state as stale after 90 seconds without fresh connection/heartbeat timestamps.
- Reconnect: the service continues retrying, reinitializes after closed/logged-out states, resolves the current linked account, and resumes syncing to the selected account key.
- Manual Refresh: reloads account metadata, sync state, open trades, pending orders, recent closed trades, and historical orders immediately. It preserves filters and only shows a button-level spinner.
- Load More History: loads older closed trade/deal records and historical order records under the selected `accountKey`, ordered by `close_time desc`, deduped by stable MT5 keys.
- Multi-account safety: data remains scoped to `users/{userId}/mt5_accounts/{broker_server}_{account_number}` and subcollections `trades`, `orders`, `sync_state/default`.

## Test Results

- Baseline before changes: `flutter pub get`, `flutter analyze`, `flutter test`, and debug APK build all passed.
- Python syntax: `python -m py_compile` passed for MT5 sync modules.
- Flutter after changes:
  - `flutter pub get`: passed
  - `flutter analyze`: passed
  - `flutter test`: passed
- Live read-only MT5 smoke check: passed for `PUPrime-Demo_700122288`; detected BTCUSD 0.19 open position, one active pending order, and recent deals/orders.
- One-shot sync write check: passed with `positions=1`, `orders=1`, `deals=96`, `history_orders=98`, `writes=1`, `activity=1`.
- Not physically exercised in this run: closing/reopening the user's MT5 terminal, switching to an unlinked account, and placing/closing a brand-new post-fix trade. These paths are covered by code changes and model tests, but require manual terminal actions.

## AAB Build

- `flutter build appbundle --release`: passed
- Output: `mobile/hc_trader/build/app/outputs/bundle/release/app-release.aab`
- Size: 55,439,614 bytes

## Known Limitations

- Manual Refresh can only show data after the Python sync has written it to the database.
- Manual Refresh now forces server reads. If the local sync process is stopped, the app can still only infer disconnect after the heartbeat/check timestamp passes the stale threshold.
- The service does not place trades or require an MQL5 EA.
- Existing duplicate linked account casing was observed: `PUPrime-Demo:700122288` and `PuPrime-Demo:700122288`.

## Follow-Up Fix

After manual refresh still showed stale status, the Flutter fetch path was tightened:

- Manual Refresh, page-open MT5 load, account switching, activity checks, and Load More History now request server reads instead of accepting cached Firestore documents.
- `updated_at` is no longer treated as connection freshness proof. Only connection check, heartbeat, or successful sync timestamps can keep a connected state fresh.
- Added regression coverage so a fresh `updated_at` cannot mask stale `last_connection_check_at` / `last_heartbeat_at`.

## Rollback

Use `backups/ROLLBACK_MT5_REFRESH_DISCONNECT_FIX.md`.

## Non-Breaking Confirmation

- Manual Trading Journal records remain separate from MT5 synced records.
- Excel import behavior was not modified.
- No manual journal notes, screenshots, mood, confidence, discipline, trading plan links, or imported trade fields are overwritten by MT5 sync upserts.
- No collections/tables were deleted.
- No paid Firebase/Google Cloud services, Cloud Functions, paid translation APIs, keystore changes, or signing-secret changes were introduced.
