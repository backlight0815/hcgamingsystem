# Setup MT5 Sync on a Windows VPS

The VPS runs only the MT5 sync agent. Flutter development and Android builds stay on the local development machine.

## Files Needed on VPS

Copy the whole `mt5_sync` folder to the VPS. The folder should contain:

- `requirements.txt`
- `.env.example`
- `machine_config.example.json`
- `setup_vps_python.md`
- `setup_vps_sync.md`
- `check_python.bat`
- `install_requirements.bat`
- `test_connection.bat`
- `vps_diagnostics.bat`
- `vps_quick_start.bat`
- `start_sync.bat`
- `stop_sync.bat`
- `restart_sync.bat`
- `run_sync_service.py`
- `test_mt5_connection.py`
- `connection_manager.py`
- `account_resolver.py`
- `terminal_discovery.py`
- `config_loader.py`
- `db_adapter.py`
- `normalizers.py`
- `realtime_watcher.py`
- `logs\`

Do not copy real secrets into Git. Keep `.env` and service-account JSON files private on the VPS.

## Setup Steps

1. Install MT5 Desktop on the VPS.
2. Open MT5 Desktop and log in to the broker account.
3. Link the same MT5 account number and broker server in the Flutter app.
4. Open CMD in the `mt5_sync` folder.
5. Run:

```bat
check_python.bat
```

6. If Python is missing, follow `setup_vps_python.md`.
7. Install requirements:

```bat
install_requirements.bat
```

8. Copy `.env.example` to `.env`.
9. Fill `.env` with machine/database config:

```text
APP_USER_ID=
DATABASE_PROVIDER=firebase
FIREBASE_SERVICE_ACCOUNT_PATH=
FIREBASE_PROJECT_ID=
MT5_TERMINAL_PATH=
MT5_MACHINE_PROFILE=vps
MT5_AUTO_DISCOVER_TERMINAL=true
MT5_SCAN_ALL_TERMINALS=true
MT5_SYNC_INTERVAL_SECONDS=300
MT5_RECONNECT_INTERVAL_SECONDS=300
MT5_SYNC_STALE_AFTER_SECONDS=600
SYNC_INTERVAL_SECONDS=300
RECONNECT_INTERVAL_SECONDS=300
SYNC_STATE_HEARTBEAT_INTERVAL_SECONDS=300
MT5_SYNC_MODE=controlled
```

Do not put MT5 account number, broker server, or MT5 password in `.env`.

10. Copy `machine_config.example.json` to `machine_config.json`.
11. If needed, set the terminal path in `machine_config.json`.
12. Test:

```bat
test_connection.bat
vps_diagnostics.bat
```

13. Start sync:

```bat
start_sync.bat
```

Or run the combined helper:

```bat
vps_quick_start.bat
```

14. Optional: configure Task Scheduler using `setup_windows_task_scheduler.md`.

## About The VPS IP Address

The VPS IP address and RDP port, for example `103.75.190.182:5633`, are only
used to open Remote Desktop and control the Windows VPS.

Do not put the VPS IP address in Flutter as an MT5 connection setting. Do not
use it as `MT5_TERMINAL_PATH`. The MT5 Python package cannot read a remote MT5
terminal over RDP/IP. The Python sync agent must run inside the VPS Windows
session where MT5 Desktop is open and logged in.

Expected data flow:

```text
MT5 Desktop on VPS -> Python sync agent on the same VPS -> Firestore -> Flutter app
```

For the HFM VPS account, the VPS `.env` should use:

```text
MT5_MACHINE_PROFILE=vps
```

The linked Flutter account should use the hosted/VPS sync location.

## Account Switching

The sync agent reads the current MT5 account from MT5 Desktop. If the account is linked in the Flutter app and active in the database, it syncs to:

```text
users/{userId}/mt5_accounts/{broker_server}_{account_number}
```

If more than one broker terminal is open on the VPS, keep `MT5_SCAN_ALL_TERMINALS=true`. The agent scans discovered `terminal64.exe` paths and syncs each active linked account it detects, such as HFM and PUPrime running side by side.

If an MT5 Desktop account is not linked, the agent does not sync trade data and leaves other linked account statuses unchanged.

## Troubleshooting

- If MT5 is closed, sync will show disconnected and keep retrying.
- If MT5 is logged out, sync will show disconnected and keep retrying.
- If the terminal path is wrong, leave `MT5_TERMINAL_PATH` blank and use `MT5_AUTO_DISCOVER_TERMINAL=true`.
- If the account does not sync, confirm the broker server text in Flutter matches the server shown by MT5.
