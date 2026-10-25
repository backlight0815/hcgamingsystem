"""MT5 terminal connection helpers with retry-friendly status results."""

from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Any

from terminal_discovery import existing_terminal_paths


@dataclass(frozen=True)
class Mt5ConnectionResult:
    status: str
    connected: bool
    error_code: str = ""
    message: str = ""


class Mt5ConnectionManager:
    def __init__(
        self,
        mt5: Any,
        *,
        terminal_path: str = "",
        terminal_paths: list[str] | None = None,
        auto_discover_terminal: bool = True,
        allow_running_terminal: bool = True,
        timeout_ms: int = 60000,
    ) -> None:
        self.mt5 = mt5
        self.terminal_path = terminal_path.strip()
        self.terminal_paths = terminal_paths or []
        self.auto_discover_terminal = auto_discover_terminal
        self.allow_running_terminal = allow_running_terminal
        self.timeout_ms = max(10000, timeout_ms)
        self.initialized = False

    def ensure_initialized(self) -> Mt5ConnectionResult:
        if self.initialized:
            health = self.connection_health()
            if health.connected:
                return health
            if health.status == "terminal_closed":
                self.shutdown()
            return health

        attempts: list[tuple[str, dict[str, Any]]] = []
        if self.terminal_path:
            attempts.append(
                (
                    "configured_terminal",
                    {"path": self.terminal_path, "timeout": self.timeout_ms},
                )
            )
        if self.allow_running_terminal:
            attempts.append(("running_terminal", {"timeout": self.timeout_ms}))

        discovered = self.terminal_paths or existing_terminal_paths(
            manual_path="",
            auto_discover=self.auto_discover_terminal,
        )
        for path in discovered:
            if self.terminal_path and path.lower() == self.terminal_path.lower():
                continue
            attempts.append(
                (
                    "discovered_terminal",
                    {"path": path, "timeout": self.timeout_ms},
                )
            )

        errors: list[str] = []
        for label, kwargs in attempts:
            path = str(kwargs.get("path") or "")
            if path and not Path(path).exists():
                errors.append(f"{label}: file not found: {path}")
                continue
            try:
                ok = self.mt5.initialize(**kwargs)
            except Exception as error:
                ok = False
                errors.append(f"{label}: {error}")
            else:
                if ok:
                    self.initialized = True
                    return self.connection_health()
                errors.append(f"{label}: {self.last_error()}")
            self.shutdown()

        message = "; ".join(errors)
        return Mt5ConnectionResult(
            status=_connection_status_from_error(
                message,
                has_path=bool(self.terminal_path or discovered),
            ),
            connected=False,
            error_code="mt5_initialize_failed",
            message=message,
        )

    def account_info(self) -> Any | None:
        return self.mt5.account_info()

    def connection_health(self) -> Mt5ConnectionResult:
        try:
            terminal = self.mt5.terminal_info()
        except Exception as error:
            self.initialized = False
            return Mt5ConnectionResult(
                status="terminal_closed",
                connected=False,
                error_code="mt5_terminal_info_failed",
                message=str(error),
            )

        if terminal is None:
            self.initialized = False
            return Mt5ConnectionResult(
                status="terminal_closed",
                connected=False,
                error_code="mt5_terminal_unavailable",
                message=self.last_error(),
            )

        connected = _as_bool(_value(terminal, "connected"))
        if connected is False:
            return Mt5ConnectionResult(
                status="disconnected",
                connected=False,
                error_code="mt5_broker_disconnected",
                message=self.last_error(),
            )

        return Mt5ConnectionResult(status="connected", connected=True)

    def last_error(self) -> str:
        try:
            return str(self.mt5.last_error())
        except Exception as error:
            return f"Unable to read MT5 last error: {error}"

    def shutdown(self) -> None:
        try:
            self.mt5.shutdown()
        except Exception:
            pass
        self.initialized = False


def _connection_status_from_error(message: str, *, has_path: bool) -> str:
    text = message.lower()
    if has_path and (
        "file not found" in text
        or "cannot find" in text
        or "path" in text and "invalid" in text
    ):
        return "terminal_closed"
    if (
        "ipc timeout" in text
        or "initialize failed" in text
        or "no ipc" in text
        or "terminal" in text and "not found" in text
    ):
        return "terminal_closed"
    return "terminal_closed" if has_path else "disconnected"


def _value(item: Any, name: str) -> Any:
    if hasattr(item, "_asdict"):
        return item._asdict().get(name)
    if isinstance(item, dict):
        return item.get(name)
    return getattr(item, name, None)


def _as_bool(value: Any) -> bool | None:
    if value is None:
        return None
    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value != 0
    text = str(value).strip().lower()
    if text in {"1", "true", "yes", "on"}:
        return True
    if text in {"0", "false", "no", "off"}:
        return False
    return None
