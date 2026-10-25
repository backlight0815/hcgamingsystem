"""MT5 desktop connection and linked-account detection test."""

from __future__ import annotations

import os
import sys
from datetime import datetime, timedelta, timezone
from typing import Any, Iterable

from account_resolver import (
    filter_accounts_for_machine,
    load_allowed_accounts,
    resolve_current_account,
)
from config_loader import load_sync_config
from connection_manager import Mt5ConnectionManager
from db_adapter import DatabaseAdapter
from terminal_discovery import existing_terminal_paths


def _namedtuple_to_dict(value: Any) -> dict[str, Any]:
    if hasattr(value, "_asdict"):
        return dict(value._asdict())
    if isinstance(value, dict):
        return value
    return {
        key: getattr(value, key)
        for key in dir(value)
        if not key.startswith("_") and not callable(getattr(value, key))
    }


def _print_records(title: str, records: Iterable[Any] | None) -> None:
    print(f"\n{title}")
    if records is None:
        print("  MT5 returned None.")
        return

    items = list(records)
    if not items:
        print("  None found.")
        return

    for index, item in enumerate(items, start=1):
        data = _namedtuple_to_dict(item)
        compact = {
            key: data.get(key)
            for key in (
                "ticket",
                "order",
                "position_id",
                "symbol",
                "type",
                "volume",
                "price_open",
                "price_current",
                "price",
                "sl",
                "tp",
                "profit",
                "commission",
                "swap",
                "time",
                "time_setup",
                "time_done",
                "comment",
                "reason",
                "magic",
            )
            if key in data
        }
        print(f"  {index}. {compact}")


def main() -> int:
    try:
        config = load_sync_config()
        import MetaTrader5 as mt5
    except ImportError:
        print(
            "MetaTrader5 package is not installed. Install requirements before "
            "running this script: pip install -r mt5_sync/requirements.txt"
        )
        return 2

    try:
        db = DatabaseAdapter()
        user_id = config.user_id
        all_accounts = load_allowed_accounts(db, user_id)
        allowed = filter_accounts_for_machine(all_accounts, config.machine_profile)
        if not allowed:
            if all_accounts:
                raise RuntimeError(
                    "No active linked MT5 accounts are assigned to "
                    f"machine_profile={config.machine_profile}."
                )
            raise RuntimeError("No active linked MT5 accounts found.")
    except Exception as error:
        print(f"Configuration failed: {error}")
        return 2

    print(f"Machine profile: {config.machine_profile}")
    print("Active linked MT5 accounts for this machine:")
    for account in allowed.values():
        profile = account.machine_profile or "any"
        print(f"  - {account.server}:{account.login} profile={profile} ({account.source})")

    manager = Mt5ConnectionManager(
        mt5,
        terminal_path=config.terminal_path,
        terminal_paths=existing_terminal_paths(
            manual_path="",
            auto_discover=config.auto_discover_terminal,
        ),
        auto_discover_terminal=config.auto_discover_terminal,
        timeout_ms=int(os.getenv("MT5_INITIALIZE_TIMEOUT_MS", "60000") or "60000"),
    )

    try:
        connection = manager.ensure_initialized()
        if not connection.connected:
            print(f"MT5 connection failed ({connection.status}): {connection.message}")
            return 1

        account = manager.account_info()
        resolution = resolve_current_account(account, allowed)
        if resolution.current is None:
            print("MT5 account detection failed: logged out or disconnected.")
            return 1

        current = resolution.current
        print("\nCurrent MT5 account:")
        print(f"login: {current.login}")
        print(f"server: {current.server}")
        print(f"name: {current.name}")
        print(f"balance: {current.balance}")
        print(f"equity: {current.equity}")
        print(f"currency: {current.currency}")
        print(f"trade_allowed: {current.trade_allowed}")
        print(f"resolution: {resolution.status}")

        if not resolution.is_linked:
            print("This MT5 account is not linked yet.")
            return 1

        positions = mt5.positions_get()
        if positions is None:
            print(f"positions_get failed: {manager.last_error()}")
        _print_records("Open positions", positions)

        orders = mt5.orders_get()
        if orders is None:
            print(f"orders_get failed: {manager.last_error()}")
        _print_records("Active orders", orders)

        to_date = datetime.now(timezone.utc)
        from_date = to_date - timedelta(days=7)

        deals = mt5.history_deals_get(from_date, to_date)
        if deals is None:
            print(f"history_deals_get failed: {manager.last_error()}")
        _print_records("History deals from last 7 days", deals)

        history_orders = mt5.history_orders_get(from_date, to_date)
        if history_orders is None:
            print(f"history_orders_get failed: {manager.last_error()}")
        _print_records("History orders from last 7 days", history_orders)

        return 0
    except Exception as error:
        print(f"MT5 connection test failed: {error}")
        return 1
    finally:
        manager.shutdown()
        print("\nMT5 shutdown complete.")


if __name__ == "__main__":
    sys.exit(main())
