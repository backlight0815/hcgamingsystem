@echo off
setlocal
cd /d "%~dp0"

python --version >nul 2>&1
if not errorlevel 1 (
  python -m pip install --upgrade pip
  python -m pip install -r requirements.txt
  exit /b %errorlevel%
)

py --version >nul 2>&1
if not errorlevel 1 (
  py -m pip install --upgrade pip
  py -m pip install -r requirements.txt
  exit /b %errorlevel%
)

echo Python is not installed or not added to PATH.
echo Please install Python and tick Add python.exe to PATH.
exit /b 1
