"""VPS-side diagnostics for MT5 sync.

Run this inside the Windows VPS/RDP session, not from the local development PC.
The VPS IP address is only for Remote Desktop; MT5 is read from the local
terminal process on that Windows machine.
"""

from __future__ import annotations

import os
import sys

from account_resolver import (
    filter_accounts_for_machine,
    load_allowed_accounts,
    resolve_current_account,
)
from config_loader import load_sync_config
from connection_manager import Mt5ConnectionManager
from db_adapter import DatabaseAdapter
from terminal_discovery import existing_terminal_paths, terminal_path_candidates


def main() -> int:
    try:
        config = load_sync_config()
    except Exception as error:
        print(f"Configuration failed: {error}")
        return 2

    print("VPS diagnostics for MT5 sync")
    print("--------------------------------")
    print("RDP/VPS IP is only for Remote Desktop. It is not used as an MT5 connection target.")
    print(f"machine_profile={config.machine_profile}")
    print(f"database_provider={config.database_provider}")
    print(f"app_user_id={_mask(config.user_id)}")
    print(f"terminal_path={config.terminal_path or '(auto)'}")
    print(f"auto_discover_terminal={config.auto_discover_terminal}")

    try:
        db = DatabaseAdapter()
        all_accounts = load_allowed_accounts(db, config.user_id)
        machine_accounts = filter_accounts_for_machine(all_accounts, config.machine_profile)
    except Exception as error:
        print(f"Database/account lookup failed: {error}")
        return 2

    print("\nLinked accounts for this user:")
    if not all_accounts:
        print("  none")
    for account in all_accounts.values():
        print(
            "  "
            f"{account.server}:{account.login} "
            f"profile={account.machine_profile or 'any'} "
            f"key={account.account_key}"
        )

    print(f"\nAccounts assigned to machine_profile={config.machine_profile}:")
    if not machine_accounts:
        print("  none")
    for account in machine_accounts.values():
        print(f"  {account.server}:{account.login} key={account.account_key}")

    print("\nTerminal path candidates:")
    candidates = terminal_path_candidates(
        manual_path=config.terminal_path,
        auto_discover=config.auto_discover_terminal,
    )
    if not candidates:
        print("  none")
    for path in candidates:
        exists = "exists" if os.path.exists(path) else "missing"
        print(f"  [{exists}] {path}")

    try:
        import MetaTrader5 as mt5
    except ImportError:
        print("\nMetaTrader5 package is not installed. Run install_requirements.bat.")
        return 2

    ready_accounts: set[str] = set()
    connected_terminals = 0
    timeout_ms = int(os.getenv("MT5_INITIALIZE_TIMEOUT_MS", "60000") or "60000")

    for label, manager in _terminal_managers(mt5, config, timeout_ms):
        try:
            connection = manager.ensure_initialized()
            print(
                f"\nTerminal: {label}\n"
                f"  status={connection.status} connected={connection.connected}"
            )
            if connection.message:
                print(f"  message={connection.message}")
            if not connection.connected:
                continue
            connected_terminals += 1

            resolution = resolve_current_account(manager.account_info(), machine_accounts)
            current = resolution.current
            if current is None:
                print("  Current MT5 account: not logged in")
                print(f"  resolution={resolution.status}")
                continue

            print("  Current MT5 account:")
            print(f"    login={current.login}")
            print(f"    server={current.server}")
            print(f"    name={current.name}")
            print(f"    balance={current.balance}")
            print(f"    equity={current.equity}")
            print(f"    currency={current.currency}")
            print(f"  resolution={resolution.status}")

            if resolution.is_linked and resolution.allowed is not None:
                ready_accounts.add(resolution.allowed.account_key)
        finally:
            manager.shutdown()

    missing = set(machine_accounts.keys()) - ready_accounts
    if ready_accounts:
        print("\nREADY accounts detected:")
        for key in sorted(ready_accounts):
            print(f"  {key}")
    if missing:
        print("\nLinked accounts not detected in open terminals:")
        for key in sorted(missing):
            print(f"  {key}")

    if ready_accounts and not missing:
        print("\nREADY: this VPS can sync all linked accounts assigned to this machine.")
        return 0
    if ready_accounts:
        print("\nPARTIAL READY: this VPS can sync some linked accounts.")
        return 0
    if connected_terminals:
        print("\nNOT READY: connected terminals did not match linked accounts for this machine.")
        return 1
    print("\nNOT READY: no MT5 terminal connection succeeded.")
    return 1


def _mask(value: str) -> str:
    if len(value) <= 8:
        return value
    return f"{value[:4]}...{value[-4:]}"


def _terminal_managers(mt5, config, timeout_ms: int) -> list[tuple[str, Mt5ConnectionManager]]:
    managers: list[tuple[str, Mt5ConnectionManager]] = []
    seen: set[str] = set()

    def add(label: str, path: str, *, allow_running: bool) -> None:
        key = path.lower() if path else label
        if key in seen:
            return
        seen.add(key)
        managers.append(
            (
                label,
                Mt5ConnectionManager(
                    mt5,
                    terminal_path=path,
                    terminal_paths=[],
                    auto_discover_terminal=False,
                    allow_running_terminal=allow_running,
                    timeout_ms=timeout_ms,
                ),
            )
        )

    if config.terminal_path:
        add("configured_terminal", config.terminal_path, allow_running=False)
    for path in existing_terminal_paths(
        manual_path="",
        auto_discover=config.auto_discover_terminal,
    ):
        add(path, path, allow_running=False)
    add("running_terminal", "", allow_running=True)
    return managers


if __name__ == "__main__":
    sys.exit(main())
