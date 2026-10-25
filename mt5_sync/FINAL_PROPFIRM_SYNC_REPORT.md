# Final Report: MT5 Prop Firm 2-Step Sync

Created: 2026-07-04 22:00 Asia/Singapore

## Summary

Added an isolated MT5-linked Prop Firm 2-Step challenge module for Flutter and the Python MT5 sync service.

The new Prop Firm dashboard uses MT5 synced accounts, trades, orders, positions, and Python/Flutter calculated metrics only. It does not merge MT5 records into the manual Trading Journal and does not change Excel import behavior.

No Laravel application files were intentionally modified for this feature.

## Backup

- Backup branch: `backup-before-mt5-propfirm-sync-20260704-2124`
- Development branch: `mt5-propfirm-sync-20260704-2124`
- Status snapshot: `backups/status-before-mt5-propfirm-sync-20260704-2124.txt`
- Patch backup: `backups/backup-before-mt5-propfirm-sync-20260704-2124.patch`
- Rollback guide: `backups/ROLLBACK_MT5_PROPFIRM_SYNC.md`
- Baseline report: `mt5_sync/BASELINE_PROPFIRM_CHECK_REPORT.md`

## Files Added

- `mobile/hc_trader/lib/models/prop_firm_challenge.dart`
- `mobile/hc_trader/lib/features/prop_firm/domain/prop_firm_calculator.dart`
- `mt5_sync/prop_firm_calculator.py`
- `mt5_sync/prop_firm_sync.py`
- `mt5_sync/FINAL_PROPFIRM_SYNC_REPORT.md`

## Files Modified

- `mobile/hc_trader/lib/features/prop_firm/prop_firm_screen.dart`
- `mobile/hc_trader/lib/repositories/trading_repository.dart`
- `mobile/hc_trader/lib/core/config/mt5_sync_config.dart`
- `mobile/hc_trader/lib/core/localization/app_localizations.dart`
- `mobile/hc_trader/firestore.rules`
- `mobile/hc_trader/test/trading_services_test.dart`
- `mt5_sync/db_adapter.py`
- `mt5_sync/run_sync_service.py`
- `mt5_sync/.env.example`
- `mt5_sync/.env`
- `mt5_sync/README_MT5_SYNC.md`

## Database Structure

New Prop Firm data is isolated under:

```text
users/{userId}/prop_firm_challenges/{challengeId}
users/{userId}/prop_firm_challenges/{challengeId}/daily_snapshots/{yyyyMMdd}
users/{userId}/prop_firm_challenges/{challengeId}/metrics/current
users/{userId}/prop_firm_challenges/{challengeId}/events/{eventId}
```

MT5 synced records remain under:

```text
users/{userId}/mt5_accounts/{accountKey}
users/{userId}/mt5_accounts/{accountKey}/trades/{tradeKey}
users/{userId}/mt5_accounts/{accountKey}/orders/{orderKey}
users/{userId}/mt5_accounts/{accountKey}/sync_state/default
```

Firestore rules were deployed successfully to project `hcgaming-31d5d` at 2026-07-04 21:58 Asia/Singapore.

## Feature Flags

Flutter:

```dart
Mt5SyncConfig.propFirmMt5SyncEnabled
```

Python:

```env
PROP_FIRM_METRICS_ENABLED=true
PROP_FIRM_METRICS_INTERVAL_SECONDS=3
```

## 2-Step Rules Supported

- Linked MT5 synced account
- Configurable starting balance and currency
- Phase 1 and Phase 2 profit targets
- Daily loss limit
- Maximum loss limit
- Daily loss calculation type: `balance_based`, `equity_based`, `balance_or_equity`
- Maximum loss calculation type: `static_initial_balance`, `trailing_equity`, `trailing_balance`
- Minimum trading days per phase
- Optional max trading days per phase
- Weekend/news trading toggles
- Challenge timezone and start date
- Risk states: `healthy`, `warning`, `near_breach`, `breached`, `passed`

## Python Sync Behavior

`run_sync_service.py` now calls `update_prop_firm_metrics` after MT5 sync cycles when account/record changes are detected or the metrics interval has elapsed.

The Python metrics updater:

- reads active challenges linked to the synced MT5 account
- reads scoped MT5 records for that account only
- calculates account, target, daily loss, maximum loss, trading day, phase, and risk metrics
- writes `metrics/current`
- writes daily snapshots
- writes events only on transitions to avoid duplicate events
- can auto-advance Phase 1 to Phase 2, mark Phase 2 passed, or mark failed

## Verification

Final checks:

```text
flutter pub get: passed
flutter analyze: passed
flutter test: passed
flutter build apk --debug: passed
python -m py_compile: passed
Python calculator smoke: passed
firebase deploy --only firestore:rules: passed
```

Debug APK:

```text
mobile/hc_trader/build/app/outputs/flutter-apk/app-debug.apk
```

Logs:

```text
mt5_sync/final_propfirm_logs/
```

## Tests Added

- MT5 Prop Firm calculator avoids double-counting deal and closed-position records with the same position id.
- Profit target, floating P/L, closed P/L, trading days, phase in-progress, and healthy risk status.
- Maximum loss breach from MT5 equity.
- Chinese localization for the new `2-Step Challenge` label.

## Known Limitations

- Python reads the latest scoped MT5 records for the linked account. Full historical backfill is not implemented in this change.
- Supabase Prop Firm metrics methods are stubs because this app path is using Firestore for live syncing.
- The app does not log in to MT5 mobile or MT5 desktop. MT5 Desktop plus the Python service still must run on a Windows desktop or VPS.
- Prop firm calculations are tracking aids. Users should verify results against the prop firm's official dashboard because firms can define rules differently.

## Non-Breaking Confirmation

- Manual Trading Journal records are not merged with MT5 synced records.
- Excel import records are not merged with MT5 synced records.
- Existing manual journal reports were not intentionally changed.
- Laravel files were not intentionally changed for this feature.
- No paid Firebase or Google Cloud service was added.
- No Firebase Cloud Functions, Cloud Run, Pub/Sub, BigQuery, Firebase Extensions, or paid translation API was used.

## Rollback

Use `backups/ROLLBACK_MT5_PROPFIRM_SYNC.md`.

Fast disable:

1. Stop the Python MT5 sync service.
2. Build Flutter with `PROP_FIRM_MT5_SYNC_ENABLED=false` or set `Mt5SyncConfig.propFirmMt5SyncEnabled` default to false.
3. Keep existing manual journal and Excel import data untouched.
