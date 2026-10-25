@echo off
setlocal

where nssm >nul 2>&1
if not errorlevel 1 (
  nssm status MT5SyncService >nul 2>&1
  if not errorlevel 1 (
    nssm start MT5SyncService
    exit /b %errorlevel%
  )
)

schtasks /Query /TN MT5SyncService >nul 2>&1
if not errorlevel 1 (
  schtasks /Run /TN MT5SyncService
  exit /b %errorlevel%
)

cd /d "%~dp0"
if not exist "logs" mkdir "logs"

echo Starting MT5 sync in this console. Press Ctrl+C to stop.
echo Logs: %cd%\logs\manual-service.log

python --version >nul 2>&1
if not errorlevel 1 (
  python run_sync_service.py >> logs\manual-service.log 2>&1
  exit /b %errorlevel%
)

py --version >nul 2>&1
if not errorlevel 1 (
  py run_sync_service.py >> logs\manual-service.log 2>&1
  exit /b %errorlevel%
)

echo Python is not installed or not added to PATH.
echo Please install Python and tick Add python.exe to PATH.
exit /b 1
