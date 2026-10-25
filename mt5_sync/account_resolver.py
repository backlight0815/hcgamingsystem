"""Resolve the currently logged-in MT5 account against linked accounts."""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Iterable

from normalizers import account_key


@dataclass(frozen=True)
class AllowedMt5Account:
    login: int
    server: str
    account_key: str
    display_name: str = ""
    machine_profile: str = ""
    source: str = "database"


@dataclass(frozen=True)
class Mt5AccountSnapshot:
    login: int
    server: str
    account_key: str
    name: str = ""
    balance: float = 0
    equity: float = 0
    currency: str = ""
    trade_allowed: bool | None = None
    connected: bool | None = None


@dataclass(frozen=True)
class AccountResolution:
    status: str
    current: Mt5AccountSnapshot | None = None
    allowed: AllowedMt5Account | None = None
    message: str = ""

    @property
    def is_linked(self) -> bool:
        return self.status == "connected_linked" and self.current is not None


def load_allowed_accounts(db: Any, user_id: str) -> dict[str, AllowedMt5Account]:
    allowed: dict[str, AllowedMt5Account] = {}

    try:
        db_accounts = db.linked_mt5_accounts(user_id)
    except Exception as error:
        print(f"Unable to read linked MT5 accounts from database: {error}")
        db_accounts = []

    for item in _allowed_accounts_from_records(db_accounts):
        allowed[item.account_key] = item

    return allowed


def filter_accounts_for_machine(
    accounts: dict[str, AllowedMt5Account],
    machine_profile: str,
) -> dict[str, AllowedMt5Account]:
    normalized = normalize_machine_profile(machine_profile)
    return {
        key: account
        for key, account in accounts.items()
        if account_matches_machine(account, normalized)
    }


def resolve_current_account(
    account_info: Any,
    allowed_accounts: dict[str, AllowedMt5Account],
) -> AccountResolution:
    if account_info is None:
        return AccountResolution(status="logged_out", message="MT5 account is not logged in.")

    current = account_snapshot(account_info)
    if current.login <= 0 or current.server.strip() == "":
        return AccountResolution(
            status="logged_out",
            current=current,
            message="MT5 account is not logged in.",
        )

    allowed = allowed_accounts.get(current.account_key)
    if allowed is None:
        allowed = _find_case_insensitive_match(current, allowed_accounts.values())
    if allowed is None:
        return AccountResolution(
            status="connected_unlinked",
            current=current,
            message="Current MT5 account is not linked.",
        )

    return AccountResolution(
        status="connected_linked",
        current=current,
        allowed=allowed,
        message="Current MT5 account is linked.",
    )


def account_snapshot(account_info: Any) -> Mt5AccountSnapshot:
    data = _as_dict(account_info)
    login = _as_int(data.get("login") or data.get("mt5_login"))
    server = str(data.get("server") or data.get("broker_server") or "").strip()
    return Mt5AccountSnapshot(
        login=login,
        server=server,
        account_key=account_key(server, login),
        name=str(data.get("name") or ""),
        balance=_as_float(data.get("balance")),
        equity=_as_float(data.get("equity")),
        currency=str(data.get("currency") or ""),
        trade_allowed=_as_bool(data.get("trade_allowed")),
        connected=_as_bool(data.get("connected")),
    )


def _allowed_accounts_from_records(
    records: Iterable[dict[str, Any]],
) -> list[AllowedMt5Account]:
    accounts: list[AllowedMt5Account] = []
    for record in records:
        if record.get("is_active") is False or record.get("isActive") is False:
            continue
        login = _as_int(record.get("mt5_login") or record.get("account_number") or record.get("login"))
        server = str(record.get("broker_server") or record.get("brokerServer") or "").strip()
        if login <= 0 or not server:
            continue
        accounts.append(
            AllowedMt5Account(
                login=login,
                server=server,
                account_key=str(record.get("account_key") or account_key(server, login)),
                display_name=str(record.get("display_name") or record.get("displayName") or ""),
                machine_profile=str(
                    record.get("sync_machine_type")
                    or record.get("syncMachineType")
                    or record.get("machine_profile")
                    or record.get("machineProfile")
                    or ""
                ),
                source="database",
            )
        )
    return accounts


def _find_case_insensitive_match(
    current: Mt5AccountSnapshot,
    accounts: Iterable[AllowedMt5Account],
) -> AllowedMt5Account | None:
    current_server = current.server.strip().lower()
    for account in accounts:
        if account.login != current.login:
            continue
        if account.server.strip().lower() == current_server:
            return account
    return None


def normalize_machine_profile(value: str) -> str:
    text = str(value or "").strip().lower().replace("-", "_").replace(" ", "_")
    if text in {"hosted", "hosted_device", "server", "remote"}:
        return "vps"
    if text in {"this_device", "pc", "desktop"}:
        return "local"
    return text


def account_matches_machine(
    account: AllowedMt5Account,
    machine_profile: str,
) -> bool:
    account_profile = normalize_machine_profile(account.machine_profile)
    if not account_profile or account_profile in {"any", "all"}:
        return True
    if not machine_profile or machine_profile in {"any", "all"}:
        return True
    return account_profile == machine_profile


def _as_dict(value: Any) -> dict[str, Any]:
    if value is None:
        return {}
    if hasattr(value, "_asdict"):
        return dict(value._asdict())
    if isinstance(value, dict):
        return value
    return {
        key: getattr(value, key)
        for key in dir(value)
        if not key.startswith("_") and not callable(getattr(value, key))
    }


def _as_int(value: Any) -> int:
    try:
        return int(value or 0)
    except (TypeError, ValueError):
        return 0


def _as_float(value: Any) -> float:
    try:
        return float(value or 0)
    except (TypeError, ValueError):
        return 0


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
