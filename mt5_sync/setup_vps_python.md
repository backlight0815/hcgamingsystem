# Setup Python on a Windows VPS

This VPS only needs MT5 Desktop, Python, the MetaTrader5 Python package, and the MT5 sync agent. It does not need VS Code, Flutter SDK, Android Studio, or the Flutter app source.

## Check Python

Open CMD in the `mt5_sync` folder and run:

```bat
check_python.bat
```

Or manually run:

```bat
python --version
py --version
```

Check pip:

```bat
pip --version
python -m pip --version
py -m pip --version
```

## If Python Is Missing

Install Python for Windows using one of these official options:

- Download the official installer from `https://www.python.org/downloads/windows/`
- Use Microsoft Store if available on the VPS
- Use `winget` if available on the VPS

During installation, tick:

```text
Add python.exe to PATH
```

After installation, open a new CMD window and verify:

```bat
python --version
py --version
```

If both commands fail, Python was not installed correctly or PATH was not configured.

## Install Dependencies

From the `mt5_sync` folder:

```bat
install_requirements.bat
```

Manual command:

```bat
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
```

Fallback if `python` does not work but `py` works:

```bat
py -m pip install --upgrade pip
py -m pip install -r requirements.txt
```

## Important

- Do not install Flutter SDK on the VPS for MT5 sync.
- Do not install Android Studio on the VPS for MT5 sync.
- Do not install VS Code unless you personally want it; it is not required.
- Do not store MT5 passwords in `.env` or in the Flutter app.
