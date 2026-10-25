@echo off
setlocal

echo Restarting MT5 sync...
call "%~dp0stop_sync.bat"
timeout /t 3 /nobreak >nul
call "%~dp0start_sync.bat"
