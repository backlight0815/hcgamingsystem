# Final Realtime MT5 Sync Report

Generated: 2026-07-04 20:45 Asia/Singapore

## Scope Completed

Enhanced the existing MT5 sync path to near realtime without Laravel changes.

Implemented:

- Python MT5 realtime watcher using the `MetaTrader5` Python package.
- Default realtime mode with 1-second polling.
- Startup offline recovery from `last_synced_time - 3 days`, or 7 days for first sync.
- Stable `trade_key`, `record_hash`, and `last_seen_at` on normalized MT5 records.
- Change-only Firestore writes with `record_hash` checks.
- Floating profit/current-price throttles.
- Flutter live listener for `sync_state/default`.
- Flutter journal indicators for Live, Syncing, Offline, Error, Wrong Account, realtime mode, interval, and last updated.
- Mock watcher snapshots for local realtime testing without database writes.

Laravel was not modified for this realtime task.

## Account Configuration

The sync account is dynamic through `mt5_sync/.env`.

Current local setup:

- MT5 login: `700122288`
- Broker server: `PUPrime-Demo`
- Realtime mode: `true`
- Sync interval: `1` second

The account is not hardcoded in the sync logic.

## Key Files

- `mt5_sync/realtime_watcher.py`
- `mt5_sync/run_sync_service.py`
- `mt5_sync/normalizers.py`
- `mt5_sync/db_adapter.py`
- `mt5_sync/.env.example`
- `mt5_sync/README_MT5_SYNC.md`
- `mobile/hc_trader/lib/models/mt5_sync_models.dart`
- `mobile/hc_trader/lib/repositories/trading_repository.dart`
- `mobile/hc_trader/lib/features/journal/journal_screen.dart`
- `mobile/hc_trader/lib/core/localization/app_localizations.dart`
- `mobile/hc_trader/test/trading_services_test.dart`

## Firestore Paths

The realtime sync writes under:

```text
users/{userId}/mt5_accounts/{accountKey}
users/{userId}/mt5_accounts/{accountKey}/trades/{tradeKey}
users/{userId}/mt5_accounts/{accountKey}/orders/{orderKey}
users/{userId}/mt5_accounts/{accountKey}/sync_state/default
```

Manual journal records remain separate and are not overwritten.

## Journal Field Protection

Existing synced MT5 documents preserve app-only journal fields:

- `mood`
- `confidence`
- `discipline`
- `followed_trading_plan`
- `mistake_reason`
- `notes`
- `screenshot_url`
- `custom_tags`
- `strategy_id`
- `trading_plan_id`
- `trading_plan_name`

## Verification

Baseline before realtime edits:

- `flutter pub get`: passed
- `flutter analyze`: passed
- `flutter test`: passed
- `flutter build apk --debug`: passed

Final checks:

- `python -m py_compile mt5_sync\normalizers.py mt5_sync\db_adapter.py mt5_sync\realtime_watcher.py mt5_sync\run_sync_service.py`: passed
- In-memory watcher smoke test: passed (`first_changed=3`, `second_changed=1`)
- `flutter analyze`: passed, no issues found
- `flutter test`: passed, 49 tests
- `flutter build apk --debug`: passed
- Real one-cycle sync before interval update: passed (`changed=8`, `writes=8`)
- Real one-cycle sync after interval update: passed (`changed=8`, `writes=0`)
- Firestore readback: `sync_status=connected`, `realtime_mode=True`, `sync_interval_seconds=1`

Close-status fix verified after live testing:

- MT5 API snapshot: `positions=0`, `orders=1`
- Firestore stale open positions before fix: 4
- Reconciliation run: `missing_closed=4`
- Firestore readback after fix: `open_trades_count=0`
- Continuous service restarted with fixed reconciliation logic

Logs are stored in:

```text
mt5_sync/final_realtime_logs/
```

## How To Run Live

Keep MT5 Desktop open and logged into the configured account, then run:

```powershell
cd C:\xampp\htdocs\hcgaming
python mt5_sync/run_sync_service.py
```

Stop with `Ctrl+C`.

MT5 Mobile can place trades on the same broker account, but the Python package reads from the desktop terminal. For continuous syncing, keep MT5 Desktop running on this PC or a Windows VPS logged into the same account.

## Backup And Rollback

Backup branch:

```text
backup-before-mt5-realtime-sync-20260704-2028
```

Rollback instructions:

```text
backups/ROLLBACK_MT5_REALTIME_SYNC.md
```
