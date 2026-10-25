"""Canonical MT5 execution eligibility for analytics and prop-firm metrics."""

from __future__ import annotations

from collections import Counter
from dataclasses import dataclass
from typing import Any, Iterable

from normalizers import lifecycle_key


@dataclass(frozen=True)
class TradeAnalyticsDataset:
    executed_closed_trades: list[dict[str, Any]]
    executed_open_trades: list[dict[str, Any]]
    active_pending_orders: list[dict[str, Any]]
    excluded_records: list[dict[str, Any]]
    diagnostics: dict[str, int]


def is_balance_operation(record: dict[str, Any]) -> bool:
    trade_type = _normalize(record.get("trade_type"))
    if trade_type in {
        "balance",
        "credit",
        "bonus",
        "deposit",
        "withdrawal",
        "transfer",
        "correction",
        "commission",
        "fee",
        "swap",
    }:
        return True
    raw_type = _positive_int(record.get("raw_mt5_type"))
    return record.get("record_type") == "deal" and raw_type not in {None, 1}


def has_execution_evidence(record: dict[str, Any]) -> bool:
    trade_type = _normalize(record.get("trade_type"))
    if trade_type not in {"buy", "sell"}:
        return False
    if _number(record.get("executed_volume")) > 0 or record.get("has_entry_deal") is True:
        return True
    record_type = _normalize(record.get("record_type"))
    volume = _number(record.get("lot_size"))
    if record_type == "position":
        return _positive_int(record.get("position_id")) is not None and volume > 0
    if record_type == "deal":
        return _positive_int(record.get("deal_ticket")) is not None and volume > 0
    return False


def exclusion_reason(record: dict[str, Any]) -> str | None:
    if is_balance_operation(record):
        return "balance_operation"
    record_type = _normalize(record.get("record_type"))
    status = _normal_state(record.get("status"), record.get("raw_order_state"))
    if record_type == "order":
        if status == "pending":
            return "active_pending_order"
        if status in {"cancelled", "expired", "rejected"}:
            return f"{status}_pending_without_execution"
        return "order_lifecycle_duplicate"
    if status == "superseded":
        return "superseded_duplicate"
    if not has_execution_evidence(record):
        return "missing_execution_evidence"
    if status not in {"open", "closed", "triggered"}:
        return "unsupported_record_state"
    return None


def build_trade_analytics_dataset(
    records: Iterable[dict[str, Any]],
    *,
    user_id: str | None = None,
    account_key: str | None = None,
) -> TradeAnalyticsDataset:
    raw = list(records)
    scoped = [
        record
        for record in raw
        if (not user_id or str(record.get("user_id") or record.get("ownerUid") or "") == user_id)
        and (not account_key or str(record.get("account_key") or "") == account_key)
    ]
    canonical = _dedupe(scoped)
    closed: list[dict[str, Any]] = []
    opened: list[dict[str, Any]] = []
    pending: list[dict[str, Any]] = []
    excluded: list[dict[str, Any]] = []
    reasons: Counter[str] = Counter()

    for record in canonical:
        reason = exclusion_reason(record)
        if reason is not None:
            reasons[reason] += 1
            excluded.append({"record": record, "reason": reason})
            if reason == "active_pending_order":
                pending.append(record)
            continue
        if _normal_state(record.get("status"), record.get("raw_order_state")) == "open":
            opened.append(record)
        else:
            closed.append(record)

    closed.sort(key=_record_time)
    opened.sort(key=_record_time)
    diagnostics = {
        "raw_records": len(raw),
        "scoped_records": len(scoped),
        "executed_closed_trades": len(closed),
        "executed_open_trades": len(opened),
        "active_pending_orders": len(pending),
        "cancelled_untriggered_orders": reasons["cancelled_pending_without_execution"],
        "expired_untriggered_orders": reasons["expired_pending_without_execution"],
        "rejected_untriggered_orders": reasons["rejected_pending_without_execution"],
        "duplicates_removed": len(scoped) - len(canonical),
        "balance_operations_excluded": reasons["balance_operation"],
        "ambiguous_records_excluded": reasons["missing_execution_evidence"]
        + reasons["unsupported_record_state"],
    }
    return TradeAnalyticsDataset(closed, opened, pending, excluded, diagnostics)


def _dedupe(records: list[dict[str, Any]]) -> list[dict[str, Any]]:
    grouped: dict[str, list[dict[str, Any]]] = {}
    for record in records:
        grouped.setdefault(lifecycle_key(record), []).append(record)
    result: list[dict[str, Any]] = []
    for group in grouped.values():
        if len(group) > 1 and all(
            _normalize(record.get("record_type")) == "deal" for record in group
        ):
            result.append(_aggregate_deal_lifecycle(group))
            continue
        result.append(max(group, key=_rank))
    return result


def _aggregate_deal_lifecycle(group: list[dict[str, Any]]) -> dict[str, Any]:
    ordered = sorted(group, key=_record_time)
    entries = [
        record
        for record in ordered
        if record.get("has_entry_deal") is True
        or _positive_int(record.get("raw_deal_entry")) in {2}
        or record.get("raw_deal_entry") == 0
    ]
    exits = [
        record
        for record in ordered
        if record.get("has_exit_deal") is True
        or record.get("raw_deal_entry") in {1, 2, 3}
    ]
    opening = entries[0] if entries else ordered[0]
    closing = exits[-1] if exits else ordered[-1]
    has_exit = bool(exits) or any(
        _normal_state(record.get("status")) == "closed" for record in ordered
    )
    entry_volume = sum(
        _number(record.get("executed_volume")) or _number(record.get("lot_size"))
        for record in entries
    )
    executed_volume = entry_volume or max(
        (_number(record.get("lot_size")) for record in ordered), default=0
    )
    position_id = opening.get("position_id") or closing.get("position_id")
    merged = dict(closing)
    merged.update(
        {
            "id": f"position_{position_id or lifecycle_key(opening)}",
            "unique_mt5_id": f"position_{position_id or lifecycle_key(opening)}",
            "record_type": "position",
            "status": "closed" if has_exit else "open",
            "trade_type": opening.get("trade_type"),
            "position_id": position_id,
            "lot_size": executed_volume,
            "executed_volume": executed_volume,
            "remaining_volume": 0,
            "entry_price": _number(opening.get("entry_price"))
            or _number(opening.get("current_price")),
            "close_price": (
                _number(closing.get("close_price"))
                or _number(closing.get("current_price"))
                if has_exit
                else 0
            ),
            "open_time": opening.get("open_time") or opening.get("close_time"),
            "close_time": (
                closing.get("close_time") or closing.get("open_time")
                if has_exit
                else None
            ),
            "profit": sum(_number(record.get("profit")) for record in ordered),
            "commission": sum(
                _number(record.get("commission")) for record in ordered
            ),
            "swap": sum(_number(record.get("swap")) for record in ordered),
            "has_entry_deal": bool(entries) or has_execution_evidence(opening),
            "has_exit_deal": has_exit,
            "was_triggered": True,
        }
    )
    return merged


def _rank(record: dict[str, Any]) -> tuple[int, int, float]:
    status_rank = {"closed": 3, "triggered": 3, "open": 2, "pending": 1}.get(
        _normal_state(record.get("status"), record.get("raw_order_state")), 0
    )
    type_rank = {"position": 3, "deal": 2, "order": 1}.get(
        _normalize(record.get("record_type")), 0
    )
    return (status_rank, type_rank, _record_time(record))


def _record_time(record: dict[str, Any]) -> float:
    value = record.get("close_time") or record.get("open_time")
    if hasattr(value, "timestamp"):
        return float(value.timestamp())
    if isinstance(value, (int, float)):
        return float(value)
    return 0.0


def _normal_state(value: Any, raw_state: Any = None) -> str:
    try:
        state_number = int(raw_state) if raw_state is not None else None
    except (TypeError, ValueError):
        state_number = None
    if state_number is not None:
        mapped = {
            1: "pending",
            2: "cancelled",
            3: "partially_filled",
            4: "triggered",
            5: "rejected",
            6: "expired",
            9: "cancelled",
        }.get(state_number)
        if mapped is not None:
            return mapped
    normalized = _normalize(value)
    if normalized in {"canceled", "deleted", "removed", "unfilled", "not_filled"}:
        return "cancelled"
    if normalized == "invalid":
        return "rejected"
    return normalized


def _normalize(value: Any) -> str:
    return str(value or "").strip().lower().replace("-", "_").replace(" ", "_")


def _number(value: Any) -> float:
    try:
        return float(value or 0)
    except (TypeError, ValueError):
        return 0.0


def _positive_int(value: Any) -> int | None:
    try:
        parsed = int(value or 0)
    except (TypeError, ValueError):
        return None
    return parsed if parsed > 0 else None
