# MT5 Realtime Sync Safety Report

Created: 2026-07-04 20:28 Asia/Singapore

## Scope

Enhance the existing MT5 sync feature with near-real-time polling and Flutter live database listeners. Do not use MQL5 EA, Laravel, Cloud Functions, paid Google Cloud services, or destructive schema changes.

## Branches And Backup

- Current branch: `mt5-realtime-sync-20260704-2028`
- Backup branch: `backup-before-mt5-realtime-sync-20260704-2028`
- Status snapshot: `backups/status-before-mt5-realtime-sync-20260704-2028.txt`
- Patch backup: `backups/backup-before-mt5-realtime-sync-20260704-2028.patch`

## Existing Project State

The worktree was already heavily modified before realtime work started. Existing Laravel files are outside the scope of this task and will not be changed.

## Existing Firebase / Supabase Setup

The Flutter app already uses Firebase Auth and Firestore. Supabase is not used by the Flutter app. The Python sync adapter already supports Firebase and Supabase fallback, but the app UI reads Firestore.

Firebase project used by the app:

- `hcgaming-31d5d`

## Existing Trading Journal Structure

Manual journal entries are stored separately under:

- `users/{uid}/trading_journals/{journalId}`

MT5 synced data uses isolated subcollections:

- `users/{uid}/mt5_accounts/{accountKey}`
- `users/{uid}/mt5_accounts/{accountKey}/trades/{tradeKey}`
- `users/{uid}/mt5_accounts/{accountKey}/orders/{orderKey}`
- `users/{uid}/mt5_accounts/{accountKey}/sync_state/default`

Realtime sync must not write to manual journal records or overwrite user journal fields.

## Existing Localization

The app uses manual English-key localization in:

- `mobile/hc_trader/lib/core/localization/app_localizations.dart`

All new text must use `context.l10n.text(...)` with manual Chinese translations.

## Existing Navigation

The app uses `HomeShell` with a Journal tab. Existing MT5 UI is already additive inside Trading Journal -> Synced MT5 Trades. Realtime UI should extend that tab, not replace the manual journal flow.

## Existing Auth/User Model

User data is Firebase-backed through `AuthRepository` and `AppUser`. MT5 sync writes are scoped by `APP_USER_ID` in Python and by the signed-in user UID in Flutter.

## Existing Tests

Flutter tests exist under `mobile/hc_trader/test`. Python realtime unit tests are not yet present; syntax checks and controlled one-cycle sync tests are used locally.

## Files Planned To Modify

- `mt5_sync/realtime_watcher.py`
- `mt5_sync/normalizers.py`
- `mt5_sync/db_adapter.py`
- `mt5_sync/run_sync_service.py`
- `mt5_sync/.env.example`
- `mt5_sync/README_MT5_SYNC.md`
- `mobile/hc_trader/lib/models/mt5_sync_models.dart`
- `mobile/hc_trader/lib/repositories/trading_repository.dart`
- `mobile/hc_trader/lib/features/journal/journal_screen.dart`
- `mobile/hc_trader/lib/core/localization/app_localizations.dart`
- `mobile/hc_trader/test/trading_services_test.dart`

## Risk Areas

- Firestore quota if floating profit updates are written every second.
- Wrong MT5 account/server syncing into the wrong user.
- Overwriting user journal fields on synced MT5 records.
- Too-broad Flutter listeners.

## Risk Controls

- Account validation before sync writes.
- Realtime watcher hashes normalized records and returns only new/changed records.
- Floating profit updates are throttled by env settings.
- Firestore listeners are scoped to selected user/account.
- Manual journal flow remains in the existing Trades tab.
- No Laravel changes.

## Rollback

See `backups/ROLLBACK_MT5_REALTIME_SYNC.md`.
