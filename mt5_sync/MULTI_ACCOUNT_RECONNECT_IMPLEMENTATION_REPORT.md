# MT5 Multi Account Reconnect Implementation Report

Date: 2026-07-05

## Backup Created

- Backup branch: `backup-before-mt5-multi-account-reconnect-20260705-1541`
- Development branch: `mt5-multi-account-reconnect-20260705-1541`
- Patch backup: `backups/backup-before-mt5-multi-account-reconnect-20260705-1541.patch`
- Status inventory: `backups/status-before-mt5-multi-account-reconnect-20260705-1541.txt`
- Untracked inventory: `backups/untracked-before-mt5-multi-account-reconnect-20260705-1541.txt`
- Rollback guide: `backups/ROLLBACK_MT5_MULTI_ACCOUNT_RECONNECT.md`

## Files Added

- `mt5_sync/account_resolver.py`
- `mt5_sync/connection_manager.py`
- `mt5_sync/BASELINE_MULTI_ACCOUNT_RECONNECT_REPORT.md`
- `mt5_sync/MULTI_ACCOUNT_RECONNECT_IMPLEMENTATION_REPORT.md`

## Files Modified

- `mt5_sync/run_sync_service.py`
- `mt5_sync/test_mt5_connection.py`
- `mt5_sync/realtime_watcher.py`
- `mt5_sync/normalizers.py`
- `mt5_sync/db_adapter.py`
- `mt5_sync/.env.example`
- `mt5_sync/README_MT5_SYNC.md`
- `mobile/hc_trader/lib/models/mt5_sync_models.dart`
- `mobile/hc_trader/lib/repositories/trading_repository.dart`
- `mobile/hc_trader/lib/features/journal/journal_screen.dart`
- `mobile/hc_trader/lib/core/localization/app_localizations.dart`

Local-only note: `mt5_sync/.env` was updated on this machine so the local
sync service can detect the currently opened MT5 Desktop. Real secrets and
service account paths are not included in this report.

## Root Cause

The previous sync flow was effectively tied to one expected login/server and a
single initialized MT5 session. After MT5 logout, terminal restart, broker
disconnect, or account switch, the service could keep a stale MT5 connection
state and continue showing offline instead of shutting down the MT5 Python
session, reinitializing, reading `mt5.account_info()`, and switching to the
currently logged-in account.

The app copy also described the sync as VPS-only, which made a valid local MT5
Desktop session look unsupported.

## Reconnect Fix

- `Mt5ConnectionManager` now initializes MT5 with an optional
  `MT5_TERMINAL_PATH`, safely calls `mt5.shutdown()` after failed attempts, and
  returns retry-friendly statuses instead of crashing the service.
- `run_sync_service.py` now detects the current MT5 login/server with
  `mt5.account_info()` during each cycle.
- Logged-out, closed, disconnected, and unlinked-account states update sync
  state and then retry with configurable backoff.
- `AUTO_RESUME_AFTER_LOGIN=true` lets the service resume automatically when MT5
  is logged in again.
- Account switches reset in-memory live state before syncing the new account.

## Multiple Accounts And Brokers

Allowed accounts are loaded from:

- Flutter-linked records under `users/{userId}/mt5_accounts`
- `MT5_ALLOWED_ACCOUNTS`, for example
  `PUPrime-Demo:700106106,OtherBroker-Live:12345678`
- legacy `MT5_EXPECTED_LOGIN` / `MT5_EXPECTED_SERVER` only as fallback

The account key remains `{broker_server}_{account_number}`. This means:

- same broker plus different account number is a different account
- different broker plus same account number is a different account
- unlinked current MT5 accounts are detected but not synced

## VPS And Local PC Handling

The Python MetaTrader5 package still must run on the same Windows machine as
the opened MT5 Desktop terminal. That machine can be either a Windows VPS or a
local Windows PC.

- If `MT5_TERMINAL_PATH` is set, the service tries that terminal path.
- If it is empty, the service tries the currently opened/running terminal via
  default `mt5.initialize()`.
- README examples now show several possible `terminal64.exe` locations without
  assuming one broker install name.

## Offline Recovery

On startup, reconnect, and linked-account switch, the service reloads recovery
state from `sync_state/default`.

- If `last_synced_time` exists, it reads MT5 history from that time minus up to
  3 days.
- If no sync state exists, it reads the last 7 days.
- Stable trade/order document IDs and `record_hash` prevent duplicate records.
- `last_trade_activity_at` and `last_trade_change_version` are updated only
  when records are new, changed, or reconciled.

## Cross-Account Isolation

All MT5 writes are scoped to:

```text
users/{userId}/mt5_accounts/{accountKey}
users/{userId}/mt5_accounts/{accountKey}/trades/{tradeKey}
users/{userId}/mt5_accounts/{accountKey}/orders/{orderKey}
users/{userId}/mt5_accounts/{accountKey}/sync_state/default
```

The sync loop switches target only after the current MT5 account matches a
linked/allowed `accountKey`. It resets the watcher live-state cache on account
switch so open positions/orders from one account cannot be reconciled against
another account.

## Flutter Changes

- The MT5 tab can add multiple account links.
- Existing links can be edited or soft-unlinked with `is_active=false`.
- The account summary shows product-facing statuses only:
  `Connected`, `Updating`, `Disconnected`, `Account not linked`, and
  `Update temporarily unavailable`.
- A stale `Connected` heartbeat is shown as `Disconnected` instead of keeping
  an old success status on screen.
- The UI supports Local PC and VPS machine labels.
- New MT5 text has manual English/Chinese localization.
- Controlled refresh remains in place: manual refresh updates immediately, and
  scheduled activity checks only reload when a trade activity marker changes.

## Manual Journal And Excel Import Safety

Manual Trading Journal records were not merged with MT5 synced records.
Excel import behavior was not changed.

The Python adapter continues preserving user journal fields on MT5 records:

- mood
- confidence
- discipline
- followed_trading_plan
- mistake_reason
- notes
- screenshot_url
- custom_tags
- strategy_id
- trading_plan_id

## Test Results

Baseline before implementation:

- `flutter pub get`: passed
- `flutter analyze`: passed
- `flutter test`: passed
- `flutter build apk --debug`: passed

Final verification:

- `python -m py_compile ...`: passed
- `python mt5_sync/test_mt5_connection.py`: passed
  - Detected current local MT5 account `PUPrime-Demo:700122288`
  - Resolution: `connected_linked`
  - Found 1 active order and recent history records
- `SYNC_RUN_ONCE=true python mt5_sync/run_sync_service.py`: passed
  - Synced target: `PUPrime-Demo_700122288`
  - Result: `changed=195`, `writes=0`, `activity=0`
  - Duplicate prevention confirmed on the follow-up cycle
- Resolver smoke checks: passed
  - linked current account -> `connected_linked`
  - same login on different broker -> separate linked key
  - same broker with different login -> separate linked key
  - unlinked account -> `connected_unlinked`
  - logged out / no account info -> `logged_out`
- `flutter pub get`: passed
- `flutter analyze`: passed
- `flutter test`: passed
- `flutter build apk --debug`: passed
- `flutter build windows --debug`: passed

Existing test note: `flutter test` still prints the known final-exam fallback
diagnostic while passing. That message existed in the baseline and is not from
this MT5 change.

## Known Limitations

- The Python sync service cannot connect to MT5 Desktop on a different machine.
  MT5 Desktop and the Python service must run on the same Windows PC or VPS.
- I did not forcibly log out, close, or switch your live MT5 terminal during
  testing. Those branches were covered by resolver/connection logic and will be
  exercised naturally when MT5 state changes.
- If multiple MT5 terminals are open and `MT5_TERMINAL_PATH` is empty, the
  MetaTrader5 package chooses the running/default terminal. Set
  `MT5_TERMINAL_PATH` when you need a specific broker terminal.
- Broker server strings should match the MT5 server name exactly. Case-only
  duplicate account records should be cleaned up from Flutter by unlinking the
  unwanted entry.

## Rollback Instructions

Use `backups/ROLLBACK_MT5_MULTI_ACCOUNT_RECONNECT.md`.

Quick rollback options:

1. Stop `python mt5_sync/run_sync_service.py`.
2. Turn off the MT5 sync toggle in Flutter.
3. Switch to `backup-before-mt5-multi-account-reconnect-20260705-1541` to
   inspect the pre-change state.
4. Apply `backups/backup-before-mt5-multi-account-reconnect-20260705-1541.patch`
   only after confirming the target branch.

Do not use destructive reset commands unless the exact rollback target has been
confirmed.
