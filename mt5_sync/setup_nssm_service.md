# NSSM Windows Service: MT5SyncService

NSSM is a free Windows service wrapper. Use it if you want the Python MT5 sync to run as a Windows service with automatic restart on a local PC or Windows VPS.

## Prerequisites

1. Install MT5 Desktop on the Windows machine that will run the sync.
2. Log in to MT5 account `700122288` on server `PUPrime-Demo`.
3. Install Python dependencies:

```powershell
pip install -r mt5_sync/requirements.txt
```

4. Create `mt5_sync/.env` from `.env.example`.
5. Confirm this command works manually:

```powershell
python mt5_sync/run_sync_service.py
```

The sync service cannot sync if MT5 Desktop is closed, logged out, or logged in to the wrong account.

## Install Service

Open an elevated PowerShell or Command Prompt from the project root.

```powershell
New-Item -ItemType Directory -Force mt5_sync\logs
nssm install MT5SyncService
```

In the NSSM GUI:

- Path: full path to `python.exe`
- Startup directory: project root, for example `C:\xampp\htdocs\hcgaming`
- Arguments: `mt5_sync\run_sync_service.py`
- Startup type: Automatic

On the I/O tab:

- Output: `C:\xampp\htdocs\hcgaming\mt5_sync\logs\service-out.log`
- Error: `C:\xampp\htdocs\hcgaming\mt5_sync\logs\service-error.log`

On the Exit actions tab:

- Restart on failure.
- Restart delay: 60000 ms.

Save, then run:

```powershell
nssm start MT5SyncService
nssm status MT5SyncService
```

## Manual Control

```powershell
nssm stop MT5SyncService
nssm restart MT5SyncService
```

The project batch helpers also try NSSM first when it is installed:

```powershell
mt5_sync\start_sync.bat
mt5_sync\stop_sync.bat
mt5_sync\restart_sync.bat
```

## Recovery Behavior

If the sync machine restarts or Python crashes, NSSM restarts the service. The sync then reads `last_synced_time`, queries an overlapping MT5 history window, and upserts only new or changed records.
