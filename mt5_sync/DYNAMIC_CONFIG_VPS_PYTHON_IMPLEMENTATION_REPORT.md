# MT5 Dynamic Config / VPS Python Implementation Report

Date: 2026-07-05

## Backup Created

- Backup branch: `backup-before-mt5-dynamic-config-vps-python-20260705-2042`
- Development branch: `mt5-dynamic-config-vps-python-20260705-2042`
- Patch backup: `backups/backup-before-mt5-dynamic-config-vps-python-20260705-2042.patch`
- Untracked inventory: `backups/backup-before-mt5-dynamic-config-vps-python-20260705-2042-untracked-files.txt`
- Rollback guide: `backups/ROLLBACK_MT5_DYNAMIC_CONFIG_VPS_PYTHON.md`
- Baseline report: `mt5_sync/BASELINE_DYNAMIC_CONFIG_VPS_PYTHON_REPORT.md`

## Root Cause

The old sync setup still allowed static account targeting through `.env` values
such as `MT5_EXPECTED_LOGIN`, `MT5_EXPECTED_SERVER`, and
`MT5_ALLOWED_ACCOUNTS`. That made broker/account switching brittle and caused a
different VPS/local MT5 terminal to write incorrect status for unrelated linked
accounts.

During final verification, Firestore showed an older local sync service
`service_version=1.4.1` writing `account_not_linked` because it detected
`VTMarkets-Demo:1084317` while the selected app account was
`HFMarketsGlobal-Live8:135139957`. Local Python sync processes were stopped, and
the new service now scopes linked accounts by machine profile.

## What Changed

- `.env` no longer acts as static MT5 account config.
- Python loads machine/secrets config through `config_loader.py`.
- Optional machine-specific settings can be placed in `machine_config.json`.
- Python detects the current MT5 login/server with `mt5.account_info()`.
- Python loads active linked accounts from the database for `APP_USER_ID`.
- Sync proceeds only when the detected login/server matches an active linked account.
- Local and VPS/hosted accounts are separated by `sync_machine_type` /
  `MT5_MACHINE_PROFILE`.
- One VPS service can scan multiple installed/open MT5 terminals when
  `MT5_SCAN_ALL_TERMINALS=true`, so different brokers such as HFM and PUPrime
  can be detected in the same 5-minute cycle.
- A local agent logged into a different broker no longer marks all linked/VPS
  accounts as `account_not_linked`.
- Flutter manual refresh and load more history use the selected `accountKey`.
- Flutter handles stale cross-machine `account_not_linked` status as disconnected
  when the detected broker/account differs from the selected account.
- Firestore heartbeat/check cadence is set to 5 minutes by default to reduce
  reads/writes on the free tier.

## VPS IP Clarification

The VPS endpoint, for example `103.75.190.182:5633`, is only for Remote Desktop.
It is not used as an MT5 connection target.

Correct flow:

```text
MT5 Desktop on VPS -> Python sync agent on the same VPS -> Firestore -> Flutter app
```

The Python sync agent must run inside the VPS Windows session where MT5 Desktop
is open and logged in. `MT5_TERMINAL_PATH` must be blank for auto discovery or a
local VPS file path to `terminal64.exe`, not the VPS IP address.

## Dynamic Account Detection

1. Initialize MT5 using a configured terminal path, running terminal, or
   discovered terminal path.
2. Read `mt5.account_info()`.
3. Build account key as `{broker_server}_{account_number}`.
4. Load active linked accounts from `users/{userId}/mt5_accounts`.
5. Filter linked accounts to the current machine profile.
6. Sync trades/orders only when the current MT5 account matches.
7. If MT5 is closed/logged out/disconnected, write retry-friendly status and keep
   reconnecting.

## VPS / Local Terminal Paths

- Auto discovery tries the running terminal first, then common local/VPS paths.
- Manual override is still supported through `MT5_TERMINAL_PATH`.
- Multi-terminal scan is enabled with `MT5_SCAN_ALL_TERMINALS=true`.
- `machine_config.json` can hold machine-specific terminal path/profile.
- HFM and other broker terminal names are included in discovery candidates.

## VPS Without Python / VS Code

Added CMD-first deployment assets:

- `check_python.bat`
- `install_requirements.bat`
- `test_connection.bat`
- `vps_diagnostics.bat`
- `vps_quick_start.bat`
- `start_sync.bat`
- `stop_sync.bat`
- `restart_sync.bat`
- `setup_vps_python.md`
- `install_python_windows.md`
- `setup_vps_sync.md`
- `setup_windows_task_scheduler.md`

The VPS does not need VS Code, Flutter SDK, or Android Studio.

## Files Added

- `mt5_sync/config_loader.py`
- `mt5_sync/terminal_discovery.py`
- `mt5_sync/machine_config.example.json`
- `mt5_sync/setup_vps_python.md`
- `mt5_sync/install_python_windows.md`
- `mt5_sync/setup_vps_sync.md`
- `mt5_sync/setup_windows_task_scheduler.md`
- `mt5_sync/check_python.bat`
- `mt5_sync/install_requirements.bat`
- `mt5_sync/test_connection.bat`
- `mt5_sync/vps_diagnostics.bat`
- `mt5_sync/vps_quick_start.bat`
- `mt5_sync/DYNAMIC_CONFIG_VPS_PYTHON_IMPLEMENTATION_REPORT.md`

## Files Modified

- `mt5_sync/.env.example`
- `mt5_sync/.env`
- `mt5_sync/account_resolver.py`
- `mt5_sync/connection_manager.py`
- `mt5_sync/db_adapter.py`
- `mt5_sync/normalizers.py`
- `mt5_sync/run_sync_service.py`
- `mt5_sync/test_mt5_connection.py`
- `mt5_sync/start_sync.bat`
- `mt5_sync/stop_sync.bat`
- `mt5_sync/restart_sync.bat`
- `mt5_sync/README_MT5_SYNC.md`
- `mobile/hc_trader/lib/models/mt5_sync_models.dart`
- `mobile/hc_trader/lib/repositories/trading_repository.dart`
- `mobile/hc_trader/lib/features/journal/journal_screen.dart`
- `mobile/hc_trader/lib/core/localization/app_localizations.dart`
- `mobile/hc_trader/test/trading_services_test.dart`

## Database Changes

No destructive database migration was added.

Existing Firestore paths remain:

```text
users/{userId}/mt5_accounts/{accountKey}
users/{userId}/mt5_accounts/{accountKey}/trades/{tradeKey}
users/{userId}/mt5_accounts/{accountKey}/orders/{orderKey}
users/{userId}/mt5_accounts/{accountKey}/sync_state/default
```

Account documents now receive status metadata such as:

- `last_known_balance`
- `last_known_equity`
- `last_sync_status`
- `last_successful_sync_at`
- `last_trade_activity_at`
- `machine_profile`
- `detected_mt5_login`
- `detected_broker_server`

## Test Results

- Baseline `flutter pub get`: passed.
- Baseline `flutter analyze`: passed.
- Baseline `flutter test`: passed.
- Baseline `flutter build apk --debug`: passed.
- Python syntax compile for MT5 sync modules: passed.
- `check_python.bat`: passed locally, detected Python 3.14.6 and pip.
- `test_mt5_connection.py`: completed locally; correctly detected current local
  MT5 account as unlinked and refused trade sync.
- One-cycle local sync: correctly left linked account statuses unchanged when
  current local MT5 account was not linked.
- Final `flutter pub get`: passed.
- Final `flutter analyze`: passed.
- Final `flutter test`: passed.
- Final `flutter build apk --debug`: passed.
- Final `flutter build appbundle --release`: passed.

## AAB Build Result

Success.

Output:

```text
mobile/hc_trader/build/app/outputs/bundle/release/app-release.aab
```

Size observed locally: about 52.9 MB.

## Known Limitations

- The MT5 Python package cannot read an MT5 terminal over the VPS IP/RDP port.
  It must run on the same Windows machine as MT5 Desktop.
- With the Firestore-safe 5-minute cadence, newly opened/closed trades can take
  up to 5 minutes to appear unless Manual Refresh is used after the Python agent
  has written new data.
- If multiple MT5 terminals are installed/open on one machine, set
  `MT5_TERMINAL_PATH` to the desired local `terminal64.exe` path.
- The VPS must deploy the updated `mt5_sync` folder and restart the sync agent.
- The VPS `.env` must set `MT5_MACHINE_PROFILE=vps`.
- The linked Flutter account must be assigned to the hosted/VPS sync location.
- Strategy Tester trades are not returned by the MT5 Python API.

## VPS Verification Checklist

On the VPS `103.75.190.182:5633`, open CMD inside the updated `mt5_sync` folder:

```bat
check_python.bat
install_requirements.bat
test_connection.bat
vps_diagnostics.bat
restart_sync.bat
```

The combined helper can be used instead:

```bat
vps_quick_start.bat
```

Expected `test_connection.bat` shape:

```text
Machine profile: vps
Active linked MT5 accounts for this machine:
  - HFMarketsGlobal-Live8:135139957 profile=vps (database)
Current MT5 account:
login: 135139957
server: HFMarketsGlobal-Live8
resolution: connected_linked
```

If it shows another broker/account, close the other terminal or set
`MT5_TERMINAL_PATH` to the HFM VPS `terminal64.exe`.

## Non-Breaking Confirmation

- Manual Trading Journal was not merged with MT5 synced records.
- Excel import behavior was not changed.
- MT5 synced records remain isolated under `mt5_accounts/{accountKey}`.
- No MQL5 EA was added.
- No Laravel backend was added for MT5 sync.
- No paid Firebase or Google Cloud service was added.
- No MT5 password field was added to Flutter.

## Rollback

1. Stop local/VPS MT5 sync processes.
2. Switch to `backup-before-mt5-dynamic-config-vps-python-20260705-2042`.
3. Apply `backups/backup-before-mt5-dynamic-config-vps-python-20260705-2042.patch`
   only after confirming the target branch.
4. Use `backups/ROLLBACK_MT5_DYNAMIC_CONFIG_VPS_PYTHON.md` for full steps.
