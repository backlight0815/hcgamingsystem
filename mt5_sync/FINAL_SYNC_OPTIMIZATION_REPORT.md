# Final: MT5 Sync Optimization

Created: 2026-07-05 Asia/Singapore

## Backup Created

- Backup branch: `backup-before-mt5-sync-optimization-20260705-1025`
- Development branch: `mt5-sync-optimization-20260705-1025`
- Patch backup: `backups/backup-before-mt5-sync-optimization-20260705-1025.patch`
- Rollback guide: `backups/ROLLBACK_MT5_SYNC_OPTIMIZATION.md`
- Baseline report: `mt5_sync/BASELINE_SYNC_OPTIMIZATION_REPORT.md`

The workspace had many uncommitted changes before this work started. They are documented in the backup status inventory and are treated as pre-existing.

## Files Added

- `mt5_sync/BASELINE_SYNC_OPTIMIZATION_REPORT.md`
- `mt5_sync/FINAL_SYNC_OPTIMIZATION_REPORT.md`
- `mt5_sync/setup_windows_task_scheduler.md`
- `mt5_sync/setup_nssm_service.md`
- `mt5_sync/start_sync.bat`
- `mt5_sync/stop_sync.bat`
- `mt5_sync/restart_sync.bat`
- `backups/ROLLBACK_MT5_SYNC_OPTIMIZATION.md`

## Files Modified

- `mt5_sync/.env.example`
- `mt5_sync/README_MT5_SYNC.md`
- `mt5_sync/realtime_watcher.py`
- `mt5_sync/run_sync_service.py`
- `mobile/hc_trader/firestore.indexes.json`
- `mobile/hc_trader/lib/core/config/mt5_sync_config.dart`
- `mobile/hc_trader/lib/core/localization/app_localizations.dart`
- `mobile/hc_trader/lib/features/journal/journal_screen.dart`
- `mobile/hc_trader/lib/models/mt5_sync_models.dart`
- `mobile/hc_trader/lib/repositories/trading_repository.dart`
- `mobile/hc_trader/test/trading_services_test.dart`

## 5-Minute Refresh

No MT5-specific 5-minute timer was found in `mobile/hc_trader/lib` or `mt5_sync`. The MT5 tab's broader recent record stream was replaced with scoped realtime listeners:

- selected user only
- selected MT5 account only
- `sync_state/default`
- open trades limited to 50
- pending orders limited to 50
- recent closed trades limited to 50
- older closed history loads only from the `Load More History` action

Manual Refresh remains available as a user-triggered fallback.

## Database Quota Protection

- Python writes only new or changed MT5 records using stable keys and `record_hash`.
- Floating P/L and current-price writes are throttled by:
  - `FREE_TIER_SAFE_MODE=true`
  - `FLOATING_UPDATE_MIN_INTERVAL_SECONDS=5`
  - `FLOATING_UPDATE_MIN_PROFIT_CHANGE=1.00`
  - `FLOATING_UPDATE_MIN_PRICE_CHANGE_POINTS=10`
- `sync_state/default` is now heartbeat-throttled with `SYNC_STATE_HEARTBEAT_INTERVAL_SECONDS=30` instead of being written every poll cycle.
- Account equity updates are throttled like floating P/L.
- Reconciliation reads for stale open/pending records run on startup or when live MT5 keys disappear, not every cycle.

## VPS Auto-Start Strategy

Two free Windows VPS options are documented:

- Windows Task Scheduler: `mt5_sync/setup_windows_task_scheduler.md`
- NSSM service wrapper: `mt5_sync/setup_nssm_service.md`

Batch helpers:

- `mt5_sync/start_sync.bat`
- `mt5_sync/stop_sync.bat`
- `mt5_sync/restart_sync.bat`

Logs are directed under `mt5_sync/logs/`.

Local Windows use is also supported. The Python sync service must run on the same Windows machine where MT5 Desktop is open; that machine can be a local PC/laptop for testing or a Windows VPS for always-on production sync.

## Offline Recovery

On startup, the service reads `last_synced_time` from the selected account's sync state. It queries from `last_synced_time - 1 to 3 days`, or the last 7 days when sync state does not exist. Stable document keys prevent duplicate positions, orders, and deals.

Wrong MT5 login or broker server now writes `wrong_account` state once and stops the service.

## Feature Flags

Flutter flags:

- `MT5_SYNC_ENABLED`
- `MT5_SYNC_DEFAULT_ENABLED`
- `MT5_REALTIME_LISTENER_ENABLED`
- `MT5_FREE_TIER_SAFE_MODE`
- `MT5_FLOATING_PROFIT_LIVE_UPDATES`
- `MT5_SYNC_STALE_AFTER_SECONDS`

Python flags:

- `FREE_TIER_SAFE_MODE`
- `SYNC_FLOATING_PROFIT_UPDATES`
- `FLOATING_UPDATE_MIN_INTERVAL_SECONDS`
- `FLOATING_UPDATE_MIN_PROFIT_CHANGE`
- `FLOATING_UPDATE_MIN_PRICE_CHANGE_POINTS`
- `SYNC_STATE_HEARTBEAT_INTERVAL_SECONDS`

## Test Results

Baseline:

- `flutter pub get`: passed
- `flutter analyze`: passed
- `flutter test`: passed
- `flutter build apk --debug`: passed

Final:

- `python -m py_compile mt5_sync\normalizers.py mt5_sync\db_adapter.py mt5_sync\realtime_watcher.py mt5_sync\run_sync_service.py mt5_sync\prop_firm_sync.py mt5_sync\prop_firm_calculator.py`: passed
- `flutter analyze`: passed
- `flutter test`: passed
- `flutter build apk --debug`: passed

The Flutter test suite still prints an existing final exam translation fallback diagnostic during a passing test run.

## Known Limitations

- Live MT5 connection tests, wrong-account runtime tests, order placement detection, SL/TP live modification detection, and VPS restart behavior require the actual Windows VPS with MT5 Desktop logged in to `700106106` on `PUPrime-Demo`.
- Firestore composite indexes in `mobile/hc_trader/firestore.indexes.json` must be deployed before the new scoped queries are used against production Firestore.
- No paid Firebase, Google Cloud, MQL5 EA, or cloud automation service was added.

## Rollback

See `backups/ROLLBACK_MT5_SYNC_OPTIMIZATION.md`.

Quick rollback:

1. Stop `MT5SyncService` or the visible Python sync process.
2. Turn off the MT5 sync toggle in Flutter if needed.
3. Switch to `backup-before-mt5-sync-optimization-20260705-1025` or apply the patch backup.
4. Re-run Flutter checks from `mobile/hc_trader`.

## Existing Feature Safety

Manual Trading Journal, Trading Journal add/edit, and Excel import were not intentionally changed or merged with MT5 synced records. MT5 data remains isolated under `users/{userId}/mt5_accounts/{accountKey}` and its nested `trades`, `orders`, and `sync_state` collections.
