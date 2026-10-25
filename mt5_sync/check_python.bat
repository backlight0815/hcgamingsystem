@echo off
setlocal

echo Checking Python for MT5 sync...
echo.

set PYTHON_FOUND=0

python --version >nul 2>&1
if not errorlevel 1 (
  echo python:
  python --version
  set PYTHON_FOUND=1
) else (
  echo python command not found.
)

echo.
py --version >nul 2>&1
if not errorlevel 1 (
  echo py launcher:
  py --version
  set PYTHON_FOUND=1
) else (
  echo py launcher not found.
)

echo.
python -m pip --version >nul 2>&1
if not errorlevel 1 (
  echo pip via python:
  python -m pip --version
) else (
  py -m pip --version >nul 2>&1
  if not errorlevel 1 (
    echo pip via py:
    py -m pip --version
  ) else (
    echo pip was not found.
  )
)

echo.
if "%PYTHON_FOUND%"=="0" (
  echo Python is not installed or not added to PATH.
  echo Please install Python and tick Add python.exe to PATH.
  exit /b 1
)

echo Python check complete.
exit /b 0
