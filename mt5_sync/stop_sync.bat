@echo off
setlocal

where nssm >nul 2>&1
if not errorlevel 1 (
  nssm status MT5SyncService >nul 2>&1
  if not errorlevel 1 (
    nssm stop MT5SyncService
    exit /b %errorlevel%
  )
)

schtasks /Query /TN MT5SyncService >nul 2>&1
if not errorlevel 1 (
  schtasks /End /TN MT5SyncService
)

powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -match 'run_sync_service.py' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"

echo MT5 sync stop request completed.
exit /b 0
