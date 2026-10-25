# MT5 Sync Implementation Report

Created: 2026-07-04 17:12 Asia/Singapore
Updated: 2026-07-04 17:45 Asia/Singapore

## Status

Implemented the MT5 sync foundation using account `700122288` on broker server `PUPrime-Demo`.

No Laravel files were intentionally modified for this MT5 sync implementation.

## Backup Created

- Backup branch: `backup-before-mt5-sync-20260704-1653`
- Development branch: `mt5-sync-feature-20260704-1653`
- Git status snapshot: `backups/status-before-mt5-sync-20260704-1653.txt`
- Binary patch backup: `backups/backup-before-mt5-sync-20260704-1653.patch`
- Clean HEAD project backup zip: `backups/project-backup-before-mt5-sync-20260704-1653.zip`

## Files Added

- `mobile/hc_trader/lib/core/config/mt5_sync_config.dart`
- `mobile/hc_trader/lib/models/mt5_sync_models.dart`
- `mt5_sync/.env.example`
- `mt5_sync/README_MT5_SYNC.md`
- `mt5_sync/requirements.txt`
- `mt5_sync/test_mt5_connection.py`
- `mt5_sync/normalizers.py`
- `mt5_sync/db_adapter.py`
- `mt5_sync/run_sync_service.py`
- `mt5_sync/PROJECT_SAFETY_REPORT.md`
- `mt5_sync/BASELINE_CHECK_REPORT.md`
- `backups/ROLLBACK_MT5_SYNC.md`

## Files Modified

- `.gitignore`
- `mobile/hc_trader/firestore.rules`
- `mobile/hc_trader/lib/core/localization/app_localizations.dart`
- `mobile/hc_trader/lib/features/journal/journal_screen.dart`
- `mobile/hc_trader/lib/repositories/trading_repository.dart`
- `mobile/hc_trader/test/trading_services_test.dart`

## Database Changes

Firestore-only MT5 structure:

- `users/{userId}/mt5_accounts/{accountKey}`
- `users/{userId}/mt5_accounts/{accountKey}/trades/{tradeKey}`
- `users/{userId}/mt5_accounts/{accountKey}/orders/{orderKey}`
- `users/{userId}/mt5_accounts/{accountKey}/sync_state/default`

Account key example:

- `PUPrime-Demo_700122288`

No existing manual trading journal collection was renamed, migrated, or overwritten.

## Security Rule Changes

Added owner/admin access for:

- `users/{userId}/mt5_accounts/{accountId}`
- nested `trades`
- nested `orders`
- nested `sync_state`

Existing access behavior for other modules was left intact.

## Feature Flag Location

- Build defaults: `mobile/hc_trader/lib/core/config/mt5_sync_config.dart`
- User toggle: Trading Journal -> Synced MT5 Trades tab
- Stored preference key: `mt5_sync_settings`

Build-time flags:

- `MT5_SYNC_DEFAULT_ENABLED`
- `MT5_MOCK_DATA`

## How To Enable / Disable MT5 Sync

Enable:

1. Deploy Firestore rules.
2. In Flutter, open Trading Journal -> Synced MT5 Trades.
3. Turn on the MT5 sync toggle.
4. Link MT5 account `700122288` and server `PUPrime-Demo`.
5. Run the VPS service: `python mt5_sync/run_sync_service.py`.

Disable:

1. Turn off the MT5 sync toggle in the app.
2. Stop the VPS Python service.
3. Build with `--dart-define=MT5_SYNC_DEFAULT_ENABLED=false` if disabled-by-default behavior is preferred.

## Test Results

Baseline before implementation:

- `flutter pub get`: passed
- `flutter analyze`: passed
- `flutter test`: passed
- `flutter build apk --debug`: passed on non-piped rerun with exit code `0`

After MT5 implementation:

- `python -m py_compile mt5_sync/test_mt5_connection.py mt5_sync/normalizers.py mt5_sync/db_adapter.py mt5_sync/run_sync_service.py`: passed
- `python mt5_sync/test_mt5_connection.py` with env `MT5_EXPECTED_LOGIN=700122288`, `MT5_EXPECTED_SERVER=PUPrime-Demo`: passed
- `flutter analyze`: passed
- `flutter test`: passed
- `flutter build apk --debug`: passed with exit code `0`

The live MT5 test printed account `700122288`, server `PUPrime-Demo`, balance/equity, one open BTCUSD position, one active XAUUSD.s pending order, recent history deals, and recent history orders.

## Live Sync Setup Completed

Updated on 2026-07-04 18:22 Asia/Singapore:

- `APP_USER_ID` set locally to `Jqnma3OR7uWLO2k3stohyEruzt42`.
- Firestore rules were deployed to project `hcgaming-31d5d`.
- Python dependencies were installed.
- Local Application Default Credentials were created at `C:\Users\backlight\.config\hcgaming\hcgaming-31d5d-application-default-credentials.json`.
- One-cycle sync completed successfully.
- Firestore read-back confirmed:
  - `account_exists=True`
  - `trades_count_sample=6`
  - `orders_count_sample=7`
  - `sync_state_exists=True`
  - `sync_status=connected`

## Known Limitations

- `python-dotenv` is not installed in the current local Python environment. Install with `pip install -r mt5_sync/requirements.txt` before using `.env` files.
- The continuous service requires `APP_USER_ID` and Firebase Admin credentials in `mt5_sync/.env`.
- The Flutter app does not log in to MT5 directly; it only displays data synced by the VPS service.
- Supabase adapter is included as a fallback, but the current Flutter UI reads Firestore because the app is Firebase-based.
- User journal-field editing for MT5 records is displayed/read-safe; full edit forms for synced MT5 journal fields can be added later without changing the sync schema.

## Rollback Instructions

See `backups/ROLLBACK_MT5_SYNC.md`.

Fast disable:

1. Turn off the Flutter MT5 toggle.
2. Stop `run_sync_service.py`.

Code rollback:

1. Remove the files listed in this report.
2. Revert the modified Flutter files and Firestore rules.
3. Re-run `flutter analyze`, `flutter test`, and `flutter build apk --debug`.

## Non-Regression Confirmation

Existing major Flutter functions were not intentionally removed or replaced. The manual Trading Journal add/edit flow remains in the original Trades tab. Login, register, dashboard, trading plan, psychology, trading hub, account, admin-related compilation, localization, and navigation continue to compile and pass the available Flutter checks.
