# Install Python on Windows

Use this if `check_python.bat` says Python is not installed or not added to PATH.

## Official Installer

1. Open `https://www.python.org/downloads/windows/`.
2. Download the latest stable Windows installer.
3. Run the installer.
4. Tick `Add python.exe to PATH`.
5. Choose `Install Now`.
6. Close and reopen CMD.
7. Run:

```bat
python --version
python -m pip --version
```

## Microsoft Store

If Microsoft Store is available, install Python from the official Python Software Foundation listing. Then reopen CMD and verify:

```bat
python --version
python -m pip --version
```

## winget

If `winget` is available:

```bat
winget install Python.Python.3.14
```

Then reopen CMD and verify:

```bat
python --version
python -m pip --version
```

## If Commands Still Fail

Python is not installed correctly or PATH is not configured. Re-run the installer and tick `Add python.exe to PATH`.
