"""Load machine-level MT5 sync configuration.

Trading accounts are intentionally not configured here. The sync agent detects
the currently logged-in MT5 account and checks it against linked accounts in
the database.
"""

from __future__ import annotations

import json
import os
from dataclasses import dataclass
from pathlib import Path
from typing import Any


ROOT = Path(__file__).resolve().parent


@dataclass(frozen=True)
class SyncConfig:
    user_id: str
    database_provider: str
    terminal_path: str
    auto_discover_terminal: bool
    machine_profile: str
    sync_interval_seconds: int
    reconnect_interval_seconds: int
    sync_mode: str
    realtime_mode: bool
    mock_mt5: bool
    run_once: bool
    log_level: str
    machine_config_path: Path


def load_sync_config() -> SyncConfig:
    _load_env_file()
    machine_config_path = _machine_config_path()
    machine_config = _load_machine_config(machine_config_path)
    _map_new_env_names()
    _warn_ignored_static_account_env()

    user_id = _env("APP_USER_ID") or _env("SYNC_USER_ID")
    if not user_id:
        raise RuntimeError("APP_USER_ID or SYNC_USER_ID is required.")
    os.environ["APP_USER_ID"] = user_id

    database_provider = _env("DATABASE_PROVIDER", "firebase").lower()
    if database_provider not in {"firebase", "supabase", "firebase_or_supabase"}:
        raise RuntimeError(f"Unsupported DATABASE_PROVIDER: {database_provider}")
    if database_provider == "firebase_or_supabase":
        database_provider = "firebase"
    os.environ["DATABASE_PROVIDER"] = database_provider

    terminal_path = _env("MT5_TERMINAL_PATH") or str(
        machine_config.get("terminal_path") or ""
    )
    auto_discover = _env_bool(
        "MT5_AUTO_DISCOVER_TERMINAL",
        _bool_value(machine_config.get("auto_discover_terminal"), True),
    )
    machine_profile = _env("MT5_MACHINE_PROFILE") or str(
        machine_config.get("machine_profile") or "local"
    )
    sync_mode = _env("MT5_SYNC_MODE") or str(
        machine_config.get("sync_mode") or "controlled"
    )
    realtime_mode = _env_bool("REALTIME_MODE", sync_mode.lower() != "scheduled")

    sync_interval = _env_int(
        "SYNC_INTERVAL_SECONDS",
        _env_int("MT5_SYNC_INTERVAL_SECONDS", 300),
    )
    reconnect_interval = _env_int(
        "RECONNECT_INTERVAL_SECONDS",
        _env_int("MT5_RECONNECT_INTERVAL_SECONDS", 10),
    )
    run_once = _env_bool("SYNC_RUN_ONCE", False)
    sync_interval = _controlled_sync_interval(sync_interval, run_once)
    os.environ["SYNC_INTERVAL_SECONDS"] = str(sync_interval)
    os.environ["RECONNECT_INTERVAL_SECONDS"] = str(reconnect_interval)
    os.environ["MT5_SYNC_MACHINE_TYPE"] = machine_profile

    return SyncConfig(
        user_id=user_id,
        database_provider=database_provider,
        terminal_path=terminal_path,
        auto_discover_terminal=auto_discover,
        machine_profile=machine_profile,
        sync_interval_seconds=max(1, sync_interval),
        reconnect_interval_seconds=max(1, reconnect_interval),
        sync_mode=sync_mode,
        realtime_mode=realtime_mode,
        mock_mt5=_env_bool("MOCK_MT5", False),
        run_once=run_once,
        log_level=_env("LOG_LEVEL", "INFO").upper(),
        machine_config_path=machine_config_path,
    )


def _load_env_file() -> None:
    try:
        from dotenv import load_dotenv
    except ImportError:
        print("python-dotenv is not installed. Run install_requirements.bat.")
        return

    env_path = ROOT / ".env"
    load_dotenv(env_path if env_path.exists() else None)


def _machine_config_path() -> Path:
    configured = _env("MT5_MACHINE_CONFIG_PATH") or _env("MACHINE_CONFIG_PATH")
    if configured:
        return Path(configured).expanduser()
    return ROOT / "machine_config.json"


def _load_machine_config(path: Path) -> dict[str, Any]:
    if not path.exists():
        return {}
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except Exception as error:
        raise RuntimeError(f"Unable to read machine config {path}: {error}") from error
    if not isinstance(data, dict):
        raise RuntimeError(f"Machine config {path} must contain a JSON object.")
    return data


def _map_new_env_names() -> None:
    _copy_env_if_missing("SYNC_USER_ID", "APP_USER_ID")
    _copy_env_if_missing("MT5_SYNC_INTERVAL_SECONDS", "SYNC_INTERVAL_SECONDS")
    _copy_env_if_missing("MT5_RECONNECT_INTERVAL_SECONDS", "RECONNECT_INTERVAL_SECONDS")
    _copy_env_if_missing("MT5_MACHINE_PROFILE", "MT5_SYNC_MACHINE_TYPE")


def _copy_env_if_missing(source: str, target: str) -> None:
    if _env(target):
        return
    value = _env(source)
    if value:
        os.environ[target] = value


def _warn_ignored_static_account_env() -> None:
    ignored = [
        name
        for name in ("MT5_EXPECTED_LOGIN", "MT5_EXPECTED_SERVER", "MT5_ALLOWED_ACCOUNTS")
        if _env(name)
    ]
    if ignored:
        print(
            "Ignoring static MT5 account environment values: "
            + ", ".join(ignored)
            + ". Linked accounts are loaded from the database."
        )


def _env(name: str, default: str = "") -> str:
    return os.getenv(name, default).strip()


def _env_bool(name: str, default: bool) -> bool:
    value = _env(name)
    if not value:
        return default
    return value.lower() in {"1", "true", "yes", "on"}


def _env_int(name: str, default: int) -> int:
    try:
        return int(_env(name, str(default)))
    except ValueError:
        return default


def _controlled_sync_interval(value: int, run_once: bool) -> int:
    if run_once or _env_bool("ALLOW_FAST_MT5_SYNC", False):
        return max(1, value)
    if _env_bool("FREE_TIER_SAFE_MODE", True):
        return max(300, value)
    return max(60, value)


def _bool_value(value: Any, default: bool) -> bool:
    if value is None:
        return default
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value != 0
    return str(value).strip().lower() in {"1", "true", "yes", "on"}
