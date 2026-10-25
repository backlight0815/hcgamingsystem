# MT5 Sync Project Safety Report

Created: 2026-07-04 16:53 Asia/Singapore

## Scope

Implement MT5 sync as an isolated Flutter/Python enhancement. Do not change Laravel application code, routes, migrations, controllers, models, views, or tests. Do not use Laravel as the MT5 backend.

## Current Branch

- Started on: `master`
- Development branch created: `mt5-sync-feature-20260704-1653`
- Backup branch created: `backup-before-mt5-sync-20260704-1653`

## Git Status Summary

The working tree was already heavily dirty before MT5 work started. A full status snapshot is stored at `backups/status-before-mt5-sync-20260704-1653.txt`.

Backup artifacts:

- `backups/backup-before-mt5-sync-20260704-1653.patch`
- `backups/project-backup-before-mt5-sync-20260704-1653.zip`
- `backups/status-before-mt5-sync-20260704-1653.txt`

The dirty state was not committed because it contains broad user-owned Laravel and Flutter work.

## Existing Firebase / Supabase Usage

The Flutter app already uses Firebase:

- `firebase_core`
- `firebase_auth`
- `cloud_firestore`
- `firebase_storage`
- `firebase_messaging`
- `cloud_functions`

No Supabase usage was found in the Flutter app. MT5 sync should therefore use Firestore only and stay within Firebase Spark/free-plan client reads/writes. The new MT5 request explicitly prohibits Firebase Cloud Functions and paid Google Cloud services.

## Existing Trading Journal Structure

Manual journal entries are represented by `TradingJournalEntry` in `mobile/hc_trader/lib/models/trading_journal_entry.dart`.

Current Firestore journal path:

- `users/{uid}/trading_journals/{journalId}`

Current repository stream:

- `TradingRepository.journalsFor(uid)` queries `users/{uid}/trading_journals` ordered by `close_date`.

Current manual save path:

- `TradingRepository.upsertJournal(entry)` writes to `users/{uid}/trading_journals`.

MT5 sync should not overwrite manual journal fields. Synced MT5 data should live in separate MT5 account subcollections unless the user explicitly links or edits journal notes in the app.

## Existing Localization Structure

Localization is custom and manual:

- `AppLocalizations.supportedLocales`: English and Chinese.
- `context.l10n.text('English key')` maps English text to Chinese via `_zh` and `_zhOverrides`.

New MT5 UI text must add manual English-key Chinese translations in `mobile/hc_trader/lib/core/localization/app_localizations.dart`. No translation API should be used.

## Existing Route / Navigation Structure

The app uses `MaterialApp` with an auth gate and a `HomeShell` bottom navigation structure:

- Home
- Journal
- Trading Hub
- Accounts
- More/Profile launched from the app bar

MT5 UI should be added as a small isolated section/tab, preferably inside the existing Journal area or Settings/More, without replacing the manual journal flow.

## Existing User/Auth Model

Auth is Firebase-backed through `AuthRepository` and `AppUser`.

Important user fields:

- `uid`
- `name`
- `email`
- `roleId`
- role/admin helpers

MT5 account data should be scoped by `uid`.

## Existing Tests

Current Flutter tests:

- `mobile/hc_trader/test/auth_error_handling_test.dart`
- `mobile/hc_trader/test/final_exam_localisation_audit_test.dart`
- `mobile/hc_trader/test/final_exam_question_bank_seed_test.dart`
- `mobile/hc_trader/test/knowledge_centre_seed_test.dart`
- `mobile/hc_trader/test/trading_services_test.dart`

Baseline result: all tests passed.

## Existing Build / Analyze Result

See `mt5_sync/BASELINE_CHECK_REPORT.md`.

Summary:

- `flutter pub get`: passed
- `flutter analyze`: passed
- `flutter test`: passed
- `flutter build apk --debug`: passed on non-piped rerun with exit code `0`

## Files Likely To Be Modified

Low/medium risk, isolated additions:

- `mt5_sync/test_mt5_connection.py`
- `mt5_sync/requirements.txt`
- `mt5_sync/.env.example`
- `mt5_sync/README_MT5_SYNC.md`
- `mt5_sync/normalizers.py`
- `mt5_sync/db_adapter.py`
- `mt5_sync/run_sync_service.py`
- Root or MT5 `.gitignore` entries for `.env` and credential JSON files

Medium risk Flutter additions:

- New MT5 models under `mobile/hc_trader/lib/models/`
- New MT5 repository methods in `mobile/hc_trader/lib/repositories/trading_repository.dart`
- New isolated Journal tab/section in `mobile/hc_trader/lib/features/journal/`
- Localization additions in `mobile/hc_trader/lib/core/localization/app_localizations.dart`

High risk areas to avoid:

- Laravel `app/`, `routes/`, `database/`, `resources/`, `tests/`
- Existing auth/register/login flows
- Existing manual journal save/edit/delete behavior
- Existing Firestore rules unless separately reviewed and documented

## Database Plan

Use Firestore only, scoped under each user:

- `users/{userId}/mt5_accounts/{accountKey}`
- `users/{userId}/mt5_accounts/{accountKey}/trades/{tradeKey}`
- `users/{userId}/mt5_accounts/{accountKey}/orders/{orderKey}`
- `users/{userId}/mt5_accounts/{accountKey}/sync_state/default`

No destructive migration is required. No Laravel database table is required.

## Risk Controls

- Feature flag defaults disabled until VPS sync is verified.
- Python validates the logged-in MT5 account before syncing.
- Python writes only MT5-owned fields on resync.
- Flutter account linking only links to synced data; it does not log in to MT5.
- Firestore queries must be scoped by `uid` and account key.
- Avoid broad collection group listeners for MT5 trades.

## Rollback Plan

See `backups/ROLLBACK_MT5_SYNC.md`.
