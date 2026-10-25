"""Realtime MT5 snapshot watcher.

This module keeps the MT5 polling logic separate from the database writer. It
does not use MQL5/EAs; it polls the installed Windows MT5 terminal through the
Python MetaTrader5 package and returns only records that are new or changed.
"""

from __future__ import annotations

import os
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Any

from normalizers import (
    normalize_account_info,
    normalize_deal,
    normalize_deal_lifecycles,
    normalize_order,
    normalize_position,
    record_hash,
    trade_key,
)

FLOATING_FIELDS = {"current_price", "profit", "swap"}
IGNORED_DIFF_FIELDS = {"synced_at", "last_seen_at", "record_hash"}
MEANINGFUL_ACTIVITY_FIELDS = {
    "symbol",
    "order_ticket",
    "deal_ticket",
    "position_id",
    "trade_type",
    "status",
    "lot_size",
    "entry_price",
    "close_price",
    "sl",
    "tp",
    "open_time",
    "close_time",
    "profit",
    "commission",
    "swap",
    "magic_number",
    "comment",
}


@dataclass(frozen=True)
class RealtimePollResult:
    account: dict[str, Any]
    all_records: list[dict[str, Any]]
    changed_records: list[dict[str, Any]]
    activity_records: list[dict[str, Any]]
    removed_live_keys: list[str]
    live_position_keys: set[str]
    live_order_keys: set[str]
    close_hints: dict[str, dict[str, Any]]
    counts: dict[str, int]
    from_date: datetime
    to_date: datetime

    @property
    def changed_count(self) -> int:
        return len(self.changed_records)


class RealtimeMt5Watcher:
    def __init__(
        self,
        *,
        sync_floating_profit_updates: bool | None = None,
        floating_update_min_interval_seconds: int | None = None,
        floating_update_min_profit_change: float | None = None,
        floating_update_min_price_change_points: float | None = None,
    ) -> None:
        self.free_tier_safe_mode = _env_bool("FREE_TIER_SAFE_MODE", True)
        self.sync_floating_profit_updates = (
            _env_bool("SYNC_FLOATING_PROFIT_UPDATES", True)
            if sync_floating_profit_updates is None
            else sync_floating_profit_updates
        )
        self.floating_update_min_interval_seconds = (
            _env_int(
                "FLOATING_UPDATE_MIN_INTERVAL_SECONDS",
                5 if self.free_tier_safe_mode else 3,
            )
            if floating_update_min_interval_seconds is None
            else floating_update_min_interval_seconds
        )
        self.floating_update_min_profit_change = (
            _env_float(
                "FLOATING_UPDATE_MIN_PROFIT_CHANGE",
                1.00 if self.free_tier_safe_mode else 0.01,
            )
            if floating_update_min_profit_change is None
            else floating_update_min_profit_change
        )
        self.floating_update_min_price_change_points = (
            _env_float(
                "FLOATING_UPDATE_MIN_PRICE_CHANGE_POINTS",
                10 if self.free_tier_safe_mode else 1,
            )
            if floating_update_min_price_change_points is None
            else floating_update_min_price_change_points
        )
        self._written_records: dict[str, dict[str, Any]] = {}
        self._last_write_at: dict[str, datetime] = {}
        self._symbol_points: dict[str, float] = {}
        self._last_live_keys: set[str] = set()
        self._last_live_keys_by_account: dict[str, set[str]] = {}
        self._mock_cycle = 0

    def poll(
        self,
        mt5: Any,
        *,
        user_id: str,
        login: int,
        server: str,
        from_date: datetime,
        to_date: datetime | None = None,
    ) -> RealtimePollResult:
        to_date = to_date or datetime.now(timezone.utc)

        account = mt5.account_info()
        positions = mt5.positions_get()
        orders = mt5.orders_get()
        deals = mt5.history_deals_get(from_date, to_date)
        history_orders = mt5.history_orders_get(from_date, to_date)

        if positions is None:
            raise RuntimeError(f"positions_get failed: {mt5.last_error()}")
        if orders is None:
            raise RuntimeError(f"orders_get failed: {mt5.last_error()}")
        if deals is None:
            raise RuntimeError(f"history_deals_get failed: {mt5.last_error()}")
        if history_orders is None:
            raise RuntimeError(f"history_orders_get failed: {mt5.last_error()}")

        position_records = [
            normalize_position(item, user_id, login, server) for item in positions
        ]
        live_position_ids = {
            int(record["position_id"])
            for record in position_records
            if record.get("position_id")
        }
        records = [
            *position_records,
            *(normalize_order(item, user_id, login, server) for item in orders),
            *normalize_deal_lifecycles(
                deals,
                user_id,
                login,
                server,
                live_position_ids=live_position_ids,
            ),
            *(
                normalize_order(item, user_id, login, server, history=True)
                for item in history_orders
            ),
        ]
        changed_records, activity_records, removed_live_keys = self._diff(
            records,
            price_points=self._price_points(mt5, records),
        )

        return RealtimePollResult(
            account=normalize_account_info(account, user_id),
            all_records=records,
            changed_records=changed_records,
            activity_records=activity_records,
            removed_live_keys=removed_live_keys,
            live_position_keys=_live_keys(records, "position"),
            live_order_keys=_live_keys(records, "order"),
            close_hints=_close_hints(records),
            counts={
                "positions": len(positions),
                "orders": len(orders),
                "deals": len(deals),
                "history_orders": len(history_orders),
            },
            from_date=from_date,
            to_date=to_date,
        )

    def mock_poll(
        self,
        *,
        user_id: str,
        login: int,
        server: str,
        from_date: datetime,
        to_date: datetime | None = None,
    ) -> RealtimePollResult:
        to_date = to_date or datetime.now(timezone.utc)
        self._mock_cycle += 1
        profit = round(4.53 + self._mock_cycle * 0.07, 2)
        price = round(62431.29 + self._mock_cycle * 2.5, 2)
        account = {
            "login": login,
            "server": server,
            "name": "Mock MT5",
            "balance": 10000,
            "equity": round(10000 + profit, 2),
            "currency": "USD",
        }
        position = {
            "ticket": 10001,
            "identifier": 10001,
            "symbol": "BTCUSD",
            "type": 1,
            "volume": 0.02,
            "price_open": 62658.06,
            "price_current": price,
            "sl": 63843.58,
            "tp": 58437.49,
            "profit": profit,
            "swap": 0,
            "time": int((to_date - timedelta(hours=2)).timestamp()),
            "reason": 1,
            "magic": 0,
            "comment": "mock floating update",
        }
        order = {
            "ticket": 10002,
            "symbol": "XAUUSD.s",
            "type": 2,
            "state": 1,
            "volume_initial": 0.01,
            "price_open": 4146,
            "price_current": 4175.18,
            "sl": 4130,
            "tp": 4300,
            "time_setup": int((to_date - timedelta(hours=5)).timestamp()),
            "reason": 1,
            "magic": 0,
        }
        deal = {
            "ticket": 10003,
            "order": 10003,
            "position_id": 10003,
            "symbol": "XAUUSD.s",
            "type": 1,
            "volume": 0.01,
            "price": 4035,
            "profit": -5.94,
            "commission": 0,
            "swap": 0,
            "time": int((to_date - timedelta(days=1)).timestamp()),
            "reason": 1,
            "magic": 0,
        }
        records = [
            normalize_position(position, user_id, login, server),
            normalize_order(order, user_id, login, server),
            normalize_deal(deal, user_id, login, server),
        ]

        if self._mock_cycle >= 4:
            triggered_order = {
                **order,
                "state": 4,
                "time_done": int((to_date - timedelta(minutes=2)).timestamp()),
            }
            records.append(normalize_order(triggered_order, user_id, login, server, history=True))

        changed_records, activity_records, removed_live_keys = self._diff(
            records,
            price_points={record.get("symbol", ""): 0.01 for record in records},
        )
        return RealtimePollResult(
            account=normalize_account_info(account, user_id),
            all_records=records,
            changed_records=changed_records,
            activity_records=activity_records,
            removed_live_keys=removed_live_keys,
            live_position_keys=_live_keys(records, "position"),
            live_order_keys=_live_keys(records, "order"),
            close_hints=_close_hints(records),
            counts={"positions": 1, "orders": 1, "deals": 1, "history_orders": 1 if self._mock_cycle >= 4 else 0},
            from_date=from_date,
            to_date=to_date,
        )

    def account_changed(self, account: dict[str, Any]) -> bool:
        key = f"{account.get('broker_server')}:{account.get('mt5_login')}:account"
        stable_account = {
            field: value
            for field, value in account.items()
            if field
            not in {
                "last_synced_time",
                "last_successful_sync_at",
                "last_connection_check_at",
                "last_heartbeat_at",
                "last_error_at",
                "last_trade_activity_at",
                "last_sync_status",
                "updated_at",
                "last_error",
                "last_error_code",
                "last_error_message_internal",
                "sync_status",
                "display_status",
            }
        }
        new_hash = record_hash(stable_account)
        existing = self._written_records.get(key)
        if existing:
            changed_fields = _changed_fields(existing, stable_account)
            if changed_fields <= {"equity"} and not self._should_write_floating_update(
                key,
                existing,
                stable_account,
                point=1,
                price_field="equity",
            ):
                return False
        if existing and existing.get("record_hash") == new_hash:
            return False
        cached = dict(stable_account)
        cached["record_hash"] = new_hash
        self._written_records[key] = cached
        self._last_write_at[key] = datetime.now(timezone.utc)
        return True

    def accept_written(self, records: list[dict[str, Any]]) -> None:
        now = datetime.now(timezone.utc)
        for record in records:
            key = trade_key(record)
            self._written_records[key] = dict(record)
            self._last_write_at[key] = now

    def reset_live_state(self, account_key: str | None = None) -> None:
        if account_key:
            self._last_live_keys_by_account.pop(account_key, None)
            return
        self._last_live_keys = set()
        self._last_live_keys_by_account.clear()

    def _diff(
        self,
        records: list[dict[str, Any]],
        *,
        price_points: dict[str, float],
    ) -> tuple[list[dict[str, Any]], list[dict[str, Any]], list[str]]:
        changed: list[dict[str, Any]] = []
        activity: list[dict[str, Any]] = []
        current_live_keys: set[str] = set()

        for record in records:
            key = trade_key(record)
            if record["record_type"] in {"position", "order"} and record["status"] in {
                "open",
                "pending",
            }:
                current_live_keys.add(key)

            previous = self._written_records.get(key)
            if previous is None:
                changed.append(record)
                activity.append(record)
                continue

            if previous.get("record_hash") == record.get("record_hash"):
                continue

            changed_fields = _changed_fields(previous, record)
            if changed_fields and changed_fields <= FLOATING_FIELDS:
                if not self._should_write_floating_update(
                    key,
                    previous,
                    record,
                    point=price_points.get(record.get("symbol", ""), 0),
                    price_field="current_price",
                ):
                    continue

            changed.append(record)
            if _meaningful_activity_change(changed_fields):
                activity.append(record)

        account_key_value = str(records[0].get("account_key") or "") if records else ""
        if account_key_value:
            previous_live_keys = self._last_live_keys_by_account.get(
                account_key_value,
                set(),
            )
            removed_live_keys = sorted(previous_live_keys - current_live_keys)
            self._last_live_keys_by_account[account_key_value] = current_live_keys
        else:
            removed_live_keys = sorted(self._last_live_keys - current_live_keys)
            self._last_live_keys = current_live_keys
        return changed, activity, removed_live_keys

    def _should_write_floating_update(
        self,
        key: str,
        previous: dict[str, Any],
        record: dict[str, Any],
        *,
        point: float,
        price_field: str,
    ) -> bool:
        if not self.sync_floating_profit_updates:
            return False

        now = datetime.now(timezone.utc)
        last_write = self._last_write_at.get(key)
        elapsed = (
            self.floating_update_min_interval_seconds
            if last_write is None
            else (now - last_write).total_seconds()
        )
        profit_delta = abs(float(record.get("profit") or 0) - float(previous.get("profit") or 0))
        price_delta = abs(
            float(record.get(price_field) or 0) - float(previous.get(price_field) or 0)
        )
        price_delta_points = price_delta / point if point and point > 0 else 0
        return (
            elapsed >= self.floating_update_min_interval_seconds
            or profit_delta >= self.floating_update_min_profit_change
            or price_delta_points >= self.floating_update_min_price_change_points
        )

    def _price_points(
        self,
        mt5: Any,
        records: list[dict[str, Any]],
    ) -> dict[str, float]:
        symbols = {
            str(record.get("symbol") or "")
            for record in records
            if record.get("current_price") is not None
        }
        points: dict[str, float] = {}
        for symbol in symbols:
            if not symbol:
                continue
            if symbol not in self._symbol_points:
                info = mt5.symbol_info(symbol)
                self._symbol_points[symbol] = float(getattr(info, "point", 0) or 0)
            points[symbol] = self._symbol_points[symbol]
        return points


def _changed_fields(previous: dict[str, Any], current: dict[str, Any]) -> set[str]:
    keys = (set(previous) | set(current)) - IGNORED_DIFF_FIELDS
    changed = set()
    for key in keys:
        if previous.get(key) != current.get(key):
            changed.add(key)
    return changed


def _meaningful_activity_change(changed_fields: set[str]) -> bool:
    if not changed_fields:
        return False
    if changed_fields <= FLOATING_FIELDS:
        return False
    return bool(changed_fields & MEANINGFUL_ACTIVITY_FIELDS)


def _live_keys(records: list[dict[str, Any]], record_type: str) -> set[str]:
    live_status = "open" if record_type == "position" else "pending"
    return {
        trade_key(record)
        for record in records
        if record.get("record_type") == record_type and record.get("status") == live_status
    }


def _close_hints(records: list[dict[str, Any]]) -> dict[str, dict[str, Any]]:
    hints: dict[str, dict[str, Any]] = {}

    for record in records:
        if record.get("record_type") == "deal" and record.get("position_id"):
            key = (
                f"position_{record['mt5_login']}*"
                f"{record['broker_server']}*"
                f"{record['position_id']}"
            )
            existing = hints.get(key)
            close_time = record.get("close_time")
            existing_close_time = existing.get("close_time") if existing else None
            is_newer = existing is None or (
                close_time is not None
                and (existing_close_time is None or close_time >= existing_close_time)
            )
            profit = float(record.get("profit") or 0)
            commission = float(record.get("commission") or 0)
            swap = float(record.get("swap") or 0)
            if existing is None:
                hints[key] = {
                    "status": "closed",
                    "close_time": close_time,
                    "close_price": record.get("close_price"),
                    "current_price": record.get("close_price"),
                    "profit": profit,
                    "commission": commission,
                    "swap": swap,
                }
            else:
                existing["profit"] = float(existing.get("profit") or 0) + profit
                existing["commission"] = float(existing.get("commission") or 0) + commission
                existing["swap"] = float(existing.get("swap") or 0) + swap
                if is_newer:
                    existing["close_time"] = close_time
                    existing["close_price"] = record.get("close_price")
                    existing["current_price"] = record.get("close_price")

        if record.get("record_type") == "order" and record.get("status") != "pending":
            hints[trade_key(record)] = {
                "status": record.get("status"),
                "close_time": record.get("close_time"),
                "close_price": record.get("close_price"),
                "current_price": record.get("current_price"),
            }

    return hints


def _env_bool(name: str, default: bool) -> bool:
    value = os.getenv(name, "").strip().lower()
    if not value:
        return default
    return value in {"1", "true", "yes", "on"}


def _env_int(name: str, default: int) -> int:
    try:
        return int(os.getenv(name, str(default)))
    except ValueError:
        return default


def _env_float(name: str, default: float) -> float:
    try:
        return float(os.getenv(name, str(default)))
    except ValueError:
        return default
