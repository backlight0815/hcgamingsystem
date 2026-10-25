# Windows Task Scheduler: MT5SyncService

Use Windows Task Scheduler when you want MT5 sync to start automatically on a local PC or Windows VPS.

## Prerequisites

1. Install MT5 Desktop on the Windows machine that will run sync.
2. Log in to the MT5 Desktop account.
3. Link that account number and broker server in the Flutter app.
4. Install Python using `setup_vps_python.md` if needed.
5. Run:

```bat
check_python.bat
install_requirements.bat
test_connection.bat
```

6. Create `.env` from `.env.example`.
7. Create `machine_config.json` from `machine_config.example.json`.

Do not store MT5 account numbers, broker servers, MT5 passwords, API keys, or keystore passwords in this task.

## Task Scheduler UI Setup

1. Open Task Scheduler.
2. Select `Create Task`.
3. Name: `MT5SyncService`.
4. Trigger:
   - `At startup`, or
   - `At log on` for the Windows user that runs MT5 Desktop.
5. Action:
   - Action: `Start a program`
   - Program/script: `python.exe`
   - Arguments: `run_sync_service.py`
   - Start in: full path to the `mt5_sync` folder

If `python.exe` is not available but `py.exe` works:

- Program/script: `py.exe`
- Arguments: `run_sync_service.py`
- Start in: full path to the `mt5_sync` folder

6. Settings:
   - Allow task to be run on demand.
   - Run task as soon as possible after a scheduled start is missed.
   - If the task fails, restart every 1 minute.
   - Attempt restart at least 3 times.
   - Do not stop the task after a short duration.

7. Security options:
   - Use `Run only when user is logged on` if MT5 Desktop needs the interactive session.
   - Use `Run whether user is logged on or not` only if MT5 Desktop remains accessible to that user session.

## Log Output

Task Scheduler does not redirect logs by default. To save logs, use `cmd.exe`.

Program/script:

```text
cmd.exe
```

Arguments:

```text
/c python run_sync_service.py >> logs\service-out.log 2>> logs\service-error.log
```

Start in:

```text
C:\Path\To\mt5_sync
```

Use `py run_sync_service.py` in the arguments if the VPS uses the Python launcher.

## Manual Control

From CMD in the `mt5_sync` folder:

```bat
start_sync.bat
stop_sync.bat
restart_sync.bat
```

## Reconnect Behavior

- If MT5 Desktop is closed, the sync agent writes disconnected status and keeps retrying.
- If MT5 Desktop is logged out, the sync agent writes disconnected status and keeps retrying.
- If MT5 Desktop is reopened and logged in again, the sync agent reconnects automatically.
- If the logged-in account is linked in Flutter, sync resumes to that account path.
- If the logged-in account is not linked, the agent does not sync trade data.
