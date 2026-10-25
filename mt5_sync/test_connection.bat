@echo off
setlocal
cd /d "%~dp0"

python --version >nul 2>&1
if not errorlevel 1 (
  python test_mt5_connection.py
  exit /b %errorlevel%
)

py --version >nul 2>&1
if not errorlevel 1 (
  py test_mt5_connection.py
  exit /b %errorlevel%
)

echo Python is not installed or not added to PATH.
echo Please install Python and tick Add python.exe to PATH.
exit /b 1
