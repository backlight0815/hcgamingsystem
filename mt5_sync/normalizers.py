"""Normalize MT5 account, position, order, and deal records for app sync."""

from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from typing import Any, Iterable

MEANINGFUL_RECORD_HASH_FIELDS = {
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
    "raw_order_state",
    "raw_deal_entry",
    "requested_volume",
    "executed_volume",
    "remaining_volume",
    "has_entry_deal",
    "has_exit_deal",
    "was_triggered",
}


def account_key(broker_server: str, login: int | str) -> str:
    safe_server = str(broker_server).strip().replace(" ", "-")
    for char in "/#?[]":
        safe_server = safe_server.replace(char, "_")
    return f"{safe_server}_{login}"


def normalize_account_info(account: Any, user_id: str) -> dict[str, Any]:
    data = _as_dict(account)
    login = int(data.get("login") or 0)
    server = str(data.get("server") or "")
    key = account_key(server, login)
    now = _utc_now()
    return {
        "ownerUid": user_id,
        "userId": user_id,
        "user_id": user_id,
        "mt5AccountId": key,
        "mt5_account_id": key,
        "account_key": key,
        "mt5_login": login,
        "account_number": login,
        "broker_server": server,
        "name": data.get("name"),
        "balance": _float(data.get("balance")),
        "equity": _float(data.get("equity")),
        "leverage": _int(data.get("leverage")),
        "margin": _float(data.get("margin")),
        "free_margin": _float(data.get("margin_free")),
        "last_known_balance": _float(data.get("balance")),
        "last_known_equity": _float(data.get("equity")),
        "currency": data.get("currency") or "",
        "trade_allowed": data.get("trade_allowed"),
        "terminal_connected": data.get("connected"),
        "sync_status": "connected",
        "last_sync_status": "connected",
        "display_status": "Connected",
        "last_error": "",
        "last_synced_time": now,
        "last_successful_sync_at": now,
        "last_connection_check_at": now,
        "last_heartbeat_at": now,
        "updated_at": now,
    }


def normalize_position(position: Any, user_id: str, login: int, server: str) -> dict[str, Any]:
    data = _as_dict(position)
    position_id = int(data.get("identifier") or data.get("ticket") or 0)
    raw_type = _int(data.get("type"))
    return _base_record(
        user_id=user_id,
        login=login,
        server=server,
        symbol=data.get("symbol"),
        unique_mt5_id=str(position_id),
        record_type="position",
        status="open",
        trade_type=_trade_type(raw_type),
        order_ticket=_nullable_int(data.get("ticket")),
        position_id=position_id,
        lot_size=data.get("volume"),
        entry_price=data.get("price_open"),
        current_price=data.get("price_current"),
        close_price=None,
        sl=data.get("sl"),
        tp=data.get("tp"),
        open_time=data.get("time"),
        close_time=None,
        profit=data.get("profit"),
        commission=0,
        swap=data.get("swap"),
        magic_number=data.get("magic"),
        comment=data.get("comment"),
        source=_source(data.get("reason")),
        raw_mt5_type=raw_type,
        raw_mt5_reason=data.get("reason"),
    )


def normalize_order(order: Any, user_id: str, login: int, server: str, history: bool = False) -> dict[str, Any]:
    data = _as_dict(order)
    ticket = int(data.get("ticket") or data.get("order") or 0)
    raw_type = _int(data.get("type"))
    state = _int(data.get("state"))
    status = _order_status(state, history)
    requested_volume = _float(
        data.get("volume_initial") or data.get("volume") or data.get("volume_current")
    )
    remaining_volume = _float(data.get("volume_current"))
    executed_volume = max(0.0, requested_volume - remaining_volume)
    position_id = _nullable_int(data.get("position_id"))
    return _base_record(
        user_id=user_id,
        login=login,
        server=server,
        symbol=data.get("symbol"),
        unique_mt5_id=str(ticket),
        record_type="order",
        status=status,
        trade_type=_trade_type(raw_type),
        order_ticket=ticket,
        position_id=position_id,
        lot_size=data.get("volume_initial") or data.get("volume_current") or data.get("volume"),
        entry_price=data.get("price_open"),
        current_price=data.get("price_current"),
        close_price=(
            data.get("price_current")
            if status in {"closed", "cancelled", "triggered", "expired"}
            else None
        ),
        sl=data.get("sl"),
        tp=data.get("tp"),
        open_time=data.get("time_setup") or data.get("time"),
        close_time=data.get("time_done") if history else None,
        profit=0,
        commission=0,
        swap=0,
        magic_number=data.get("magic"),
        comment=data.get("comment"),
        source=_source(data.get("reason")),
        raw_mt5_type=raw_type,
        raw_mt5_reason=data.get("reason"),
        raw_order_state=state,
        requested_volume=requested_volume,
        executed_volume=executed_volume,
        remaining_volume=remaining_volume,
        has_entry_deal=False,
        has_exit_deal=False,
        was_triggered=(status == "triggered" and bool(position_id or executed_volume > 0)),
    )


def normalize_deal(deal: Any, user_id: str, login: int, server: str) -> dict[str, Any]:
    data = _as_dict(deal)
    ticket = int(data.get("ticket") or 0)
    raw_type = _int(data.get("type"))
    raw_entry = _int(data.get("entry"))
    volume = _float(data.get("volume"))
    return _base_record(
        user_id=user_id,
        login=login,
        server=server,
        symbol=data.get("symbol"),
        unique_mt5_id=str(ticket),
        record_type="deal",
        status="closed",
        trade_type=_deal_type(raw_type),
        order_ticket=_nullable_int(data.get("order")),
        deal_ticket=ticket,
        position_id=_nullable_int(data.get("position_id")),
        lot_size=data.get("volume"),
        entry_price=data.get("price"),
        current_price=None,
        close_price=data.get("price"),
        sl=0,
        tp=0,
        open_time=data.get("time"),
        close_time=data.get("time"),
        profit=data.get("profit"),
        commission=data.get("commission"),
        swap=data.get("swap"),
        magic_number=data.get("magic"),
        comment=data.get("comment"),
        source=_source(data.get("reason")),
        raw_mt5_type=raw_type,
        raw_mt5_reason=data.get("reason"),
        raw_deal_entry=raw_entry,
        requested_volume=volume,
        executed_volume=volume,
        remaining_volume=0,
        has_entry_deal=raw_entry in {0, 2},
        has_exit_deal=raw_entry in {1, 2, 3},
        was_triggered=True,
    )


def normalize_deal_lifecycles(
    deals: Iterable[Any],
    user_id: str,
    login: int,
    server: str,
    *,
    live_position_ids: set[int] | None = None,
) -> list[dict[str, Any]]:
    """Collapse MT5 history deals into one lifecycle record per closed position.

    MT5 history returns individual deals, while the journal should show a trade
    lifecycle. A position id is the stable bridge between the open position and
    the eventual close deal, so closed history writes back to the same
    `position_*` document used by live positions.
    """

    live_position_ids = live_position_ids or set()
    standalone: list[dict[str, Any]] = []
    grouped: dict[int, list[dict[str, Any]]] = {}

    for deal in deals:
        record = normalize_deal(deal, user_id, login, server)
        position_id = _nullable_int(record.get("position_id"))
        if not position_id or position_id in live_position_ids:
            standalone.append(record)
            continue
        grouped.setdefault(position_id, []).append(record)

    lifecycle_records = [
        _aggregate_deal_lifecycle(position_id, records)
        for position_id, records in grouped.items()
    ]
    return [*standalone, *lifecycle_records]


def trade_key(record: dict[str, Any]) -> str:
    if record.get("trade_key"):
        return str(record["trade_key"])
    login = record["mt5_login"]
    server = record["broker_server"]
    if record["record_type"] == "position":
        return f"position_{login}*{server}*{record['position_id']}"
    if record["record_type"] == "order":
        return f"order_{login}*{server}*{record['order_ticket']}"
    return f"deal_{login}*{server}*{record['deal_ticket']}"


def lifecycle_key(record: dict[str, Any]) -> str:
    login = record["mt5_login"]
    server = record["broker_server"]
    position_id = record.get("position_id")
    if position_id:
        return f"position_{login}*{server}*{position_id}"
    if record.get("record_type") == "order" and record.get("order_ticket"):
        return f"order_{login}*{server}*{record['order_ticket']}"
    if record.get("lifecycle_key"):
        return str(record["lifecycle_key"])
    return trade_key(record)


def _base_record(**kwargs: Any) -> dict[str, Any]:
    login = int(kwargs["login"])
    server = str(kwargs["server"])
    key = account_key(server, login)
    now = _utc_now()
    record = {
        "ownerUid": kwargs["user_id"],
        "userId": kwargs["user_id"],
        "user_id": kwargs["user_id"],
        "mt5AccountId": key,
        "mt5_account_id": key,
        "account_key": key,
        "mt5_login": login,
        "broker_server": server,
        "symbol": str(kwargs.get("symbol") or ""),
        "order_ticket": kwargs.get("order_ticket"),
        "deal_ticket": kwargs.get("deal_ticket"),
        "position_id": kwargs.get("position_id"),
        "unique_mt5_id": kwargs["unique_mt5_id"],
        "trade_type": kwargs["trade_type"],
        "record_type": kwargs["record_type"],
        "status": kwargs["status"],
        "lot_size": _float(kwargs.get("lot_size")),
        "entry_price": _float(kwargs.get("entry_price")),
        "close_price": _float(kwargs.get("close_price")),
        "current_price": _float(kwargs.get("current_price")),
        "sl": _float(kwargs.get("sl")),
        "tp": _float(kwargs.get("tp")),
        "open_time": _mt5_time(kwargs.get("open_time")),
        "close_time": _mt5_time(kwargs.get("close_time")),
        "profit": _float(kwargs.get("profit")),
        "commission": _float(kwargs.get("commission")),
        "swap": _float(kwargs.get("swap")),
        "magic_number": _nullable_int(kwargs.get("magic_number")),
        "comment": str(kwargs.get("comment") or ""),
        "source": kwargs.get("source") or "manual_mt5",
        "synced_at": now,
        "last_seen_at": now,
        "raw_mt5_type": _nullable_int(kwargs.get("raw_mt5_type")),
        "raw_mt5_reason": _nullable_int(kwargs.get("raw_mt5_reason")),
        "raw_order_state": _nullable_int(kwargs.get("raw_order_state")),
        "raw_deal_entry": _nullable_int(kwargs.get("raw_deal_entry")),
        "requested_volume": _float(kwargs.get("requested_volume")),
        "executed_volume": _float(kwargs.get("executed_volume")),
        "remaining_volume": _float(kwargs.get("remaining_volume")),
        "has_entry_deal": kwargs.get("has_entry_deal"),
        "has_exit_deal": kwargs.get("has_exit_deal"),
        "was_triggered": kwargs.get("was_triggered"),
    }
    record["trade_key"] = trade_key(record)
    record["lifecycle_key"] = lifecycle_key(record)
    record["record_hash"] = record_hash(record)
    return record


def _aggregate_deal_lifecycle(
    position_id: int,
    records: list[dict[str, Any]],
) -> dict[str, Any]:
    ordered = sorted(
        records,
        key=lambda item: item.get("close_time") or item.get("open_time") or datetime.min.replace(tzinfo=timezone.utc),
    )
    opening = ordered[0]
    closing = ordered[-1]
    entry_records = [record for record in ordered if record.get("has_entry_deal") is True]
    exit_records = [record for record in ordered if record.get("has_exit_deal") is True]
    actual_opening = entry_records[0] if entry_records else opening
    actual_closing = exit_records[-1] if exit_records else closing
    key = (
        f"position_{closing['mt5_login']}*"
        f"{closing['broker_server']}*"
        f"{position_id}"
    )
    realised_volume = sum(
        abs(float(record.get("lot_size") or 0))
        for record in ordered
        if _has_realised_financials(record)
    )
    fallback_volume = max(
        (abs(float(record.get("lot_size") or 0)) for record in ordered),
        default=0,
    )

    aggregated = {
        **closing,
        "record_type": "position",
        "status": "closed",
        "unique_mt5_id": str(position_id),
        "trade_key": key,
        "lifecycle_key": key,
        "trade_type": actual_opening.get("trade_type") or closing.get("trade_type"),
        "position_id": position_id,
        "order_ticket": actual_opening.get("order_ticket") or closing.get("order_ticket"),
        "deal_ticket": actual_closing.get("deal_ticket"),
        "lot_size": realised_volume or fallback_volume,
        "entry_price": actual_opening.get("entry_price") or closing.get("entry_price") or 0,
        "open_time": actual_opening.get("open_time") or opening.get("open_time"),
        "close_price": actual_closing.get("close_price") or actual_closing.get("entry_price") or 0,
        "current_price": actual_closing.get("close_price") or actual_closing.get("current_price") or 0,
        "close_time": actual_closing.get("close_time") or actual_closing.get("open_time"),
        "profit": sum(float(record.get("profit") or 0) for record in ordered),
        "commission": sum(float(record.get("commission") or 0) for record in ordered),
        "swap": sum(float(record.get("swap") or 0) for record in ordered),
        "comment": actual_closing.get("comment") or actual_opening.get("comment") or "",
        "raw_mt5_type": actual_closing.get("raw_mt5_type"),
        "raw_mt5_reason": actual_closing.get("raw_mt5_reason"),
        "raw_deal_entry": actual_closing.get("raw_deal_entry"),
        "requested_volume": sum(
            abs(float(record.get("lot_size") or 0)) for record in entry_records
        ) or fallback_volume,
        "executed_volume": sum(
            abs(float(record.get("lot_size") or 0)) for record in entry_records
        ) or fallback_volume,
        "remaining_volume": 0,
        "has_entry_deal": bool(entry_records),
        "has_exit_deal": bool(exit_records),
        "was_triggered": True,
    }
    aggregated["record_hash"] = record_hash(aggregated)
    return aggregated


def _has_realised_financials(record: dict[str, Any]) -> bool:
    return any(
        abs(float(record.get(field) or 0)) > 0
        for field in ("profit", "commission", "swap")
    )


def record_hash(record: dict[str, Any]) -> str:
    """Return a stable hash for MT5-owned fields only."""

    fields = (
        MEANINGFUL_RECORD_HASH_FIELDS
        if any(key in record for key in MEANINGFUL_RECORD_HASH_FIELDS)
        else set(record)
        - {"created_at", "updated_at", "synced_at", "last_seen_at", "record_hash"}
    )
    payload = {
        key: _stable_value(value)
        for key, value in sorted(record.items())
        if key in fields
    }
    encoded = json.dumps(payload, sort_keys=True, separators=(",", ":"), ensure_ascii=True)
    return hashlib.sha256(encoded.encode("utf-8")).hexdigest()


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


def _float(value: Any) -> float:
    if value is None or value == "":
        return 0.0
    try:
        return float(value)
    except (TypeError, ValueError):
        return 0.0


def _int(value: Any) -> int:
    if value is None or value == "":
        return 0
    try:
        return int(value)
    except (TypeError, ValueError):
        return 0


def _nullable_int(value: Any) -> int | None:
    if value is None or value == "":
        return None
    return _int(value)


def _mt5_time(value: Any) -> datetime | None:
    if not value:
        return None
    if isinstance(value, datetime):
        return value.astimezone(timezone.utc)
    return datetime.fromtimestamp(int(value), tz=timezone.utc)


def _utc_now() -> datetime:
    return datetime.now(timezone.utc)


def _stable_value(value: Any) -> Any:
    if isinstance(value, datetime):
        return value.astimezone(timezone.utc).isoformat()
    if isinstance(value, float):
        return round(value, 8)
    if isinstance(value, list):
        return [_stable_value(item) for item in value]
    if isinstance(value, dict):
        return {key: _stable_value(value[key]) for key in sorted(value)}
    return value


def _trade_type(raw_type: int) -> str:
    if raw_type in {0, 2, 4, 6}:
        return "buy"
    if raw_type in {1, 3, 5, 7}:
        return "sell"
    return "unknown"


def _deal_type(raw_type: int) -> str:
    if raw_type == 0:
        return "buy"
    if raw_type == 1:
        return "sell"
    if raw_type == 2:
        return "balance"
    if raw_type == 3:
        return "credit"
    return "unknown"


def _order_status(state: int, history: bool) -> str:
    if not history:
        return "pending"
    if state in {3, 4}:
        return "triggered"
    if state == 6:
        return "expired"
    if state == 5:
        return "rejected"
    if state in {2, 9}:
        return "cancelled"
    return "unknown"


def _source(reason: Any) -> str:
    reason_id = _int(reason)
    if reason_id == 0:
        return "desktop_mt5"
    if reason_id == 1:
        return "mobile_mt5"
    if reason_id == 3:
        return "ea_unknown"
    return "manual_mt5"
