@echo off
setlocal
cd /d "%~dp0"

python --version >nul 2>&1
if not errorlevel 1 (
  python vps_diagnostics.py
  exit /b %errorlevel%
)

py --version >nul 2>&1
if not errorlevel 1 (
  py vps_diagnostics.py
  exit /b %errorlevel%
)

echo Python is not installed or not added to PATH.
echo Please install Python and tick Add python.exe to PATH.
exit /b 1
