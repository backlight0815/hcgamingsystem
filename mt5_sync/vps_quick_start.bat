@echo off
setlocal
cd /d "%~dp0"

echo MT5 Sync VPS quick start
echo ------------------------
echo This must be run inside the Windows VPS/RDP session where MT5 Desktop is open.
echo.

if not exist ".env" (
  echo .env was not found.
  echo Creating .env from .env.example. Fill APP_USER_ID and Firebase settings, then run this again.
  copy ".env.example" ".env" >nul
  exit /b 2
)

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$path='.env';" ^
  "$lines=Get-Content -LiteralPath $path;" ^
  "$pairs=@{'MT5_MACHINE_PROFILE'='vps';'MT5_AUTO_DISCOVER_TERMINAL'='true';'MT5_SCAN_ALL_TERMINALS'='true';'MT5_SYNC_MODE'='controlled';'MT5_SYNC_INTERVAL_SECONDS'='300';'SYNC_INTERVAL_SECONDS'='300';'MT5_RECONNECT_INTERVAL_SECONDS'='300';'RECONNECT_INTERVAL_SECONDS'='300';'MAX_RECONNECT_BACKOFF_SECONDS'='300';'SYNC_STATE_HEARTBEAT_INTERVAL_SECONDS'='300';'MT5_SYNC_STALE_AFTER_SECONDS'='600';'FLOATING_UPDATE_MIN_INTERVAL_SECONDS'='300';'PROP_FIRM_METRICS_INTERVAL_SECONDS'='300'};" ^
  "foreach($key in $pairs.Keys){" ^
  "  $value=$pairs[$key];" ^
  "  $found=$false;" ^
  "  $lines=$lines | ForEach-Object { if($_ -match ('^'+[regex]::Escape($key)+'=')){ $found=$true; $key+'='+$value } else { $_ } };" ^
  "  if(-not $found){ $lines += ($key+'='+$value) }" ^
  "};" ^
  "Set-Content -LiteralPath $path -Value $lines -Encoding UTF8"

echo.
call check_python.bat
if errorlevel 1 exit /b %errorlevel%

echo.
call install_requirements.bat
if errorlevel 1 exit /b %errorlevel%

echo.
call vps_diagnostics.bat
if errorlevel 1 (
  echo.
  echo VPS diagnostics did not report READY.
  echo Confirm MT5 Desktop is open on this VPS and logged into HFMarketsGlobal-Live8 / 135139957.
  echo If another terminal is detected, set MT5_TERMINAL_PATH in .env to the HFM terminal64.exe path.
  exit /b 1
)

echo.
call restart_sync.bat
exit /b %errorlevel%
