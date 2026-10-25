"""Prop Firm 2-step challenge calculator for MT5 synced records."""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from trade_eligibility import build_trade_analytics_dataset


def calculate_prop_firm_metrics(
    challenge: dict[str, Any],
    account: dict[str, Any],
    records: list[dict[str, Any]],
    *,
    now: datetime | None = None,
) -> tuple[dict[str, Any], dict[str, Any]]:
    clock = now or datetime.now(timezone.utc)
    starting_balance = _float(challenge.get("starting_balance"), 10000)
    current_phase = str(challenge.get("current_phase") or "phase_1")
    phase_target_percent = (
        _float(challenge.get("phase_2_profit_target_percent"), 5)
        if current_phase == "phase_2"
        else _float(challenge.get("phase_1_profit_target_percent"), 8)
    )
    minimum_days = (
        _int(challenge.get("minimum_trading_days_phase_2"), 3)
        if current_phase == "phase_2"
        else _int(challenge.get("minimum_trading_days_phase_1"), 3)
    )
    timezone_name = str(challenge.get("timezone_for_daily_reset") or "Asia/Singapore")
    start_date = _dt(challenge.get("challenge_start_date"))

    closed_records = _closed_records(records, start_date)
    open_positions = [
        item
        for item in records
        if item.get("record_type") == "position" and item.get("status") == "open"
    ]

    closed_profit = _round(sum(_net_profit(item) for item in closed_records))
    floating_profit = _round(sum(_net_profit(item) for item in open_positions))
    total_commission = _round(
        sum(_float(item.get("commission")) for item in [*closed_records, *open_positions])
    )
    total_swap = _round(
        sum(_float(item.get("swap")) for item in [*closed_records, *open_positions])
    )
    current_balance = _float(account.get("balance"), starting_balance + closed_profit)
    current_equity = _float(account.get("equity"), current_balance + floating_profit)
    account_profit = _round(current_equity - starting_balance)
    account_profit_percent = _percent(account_profit, starting_balance)

    target_amount = _round(starting_balance * phase_target_percent / 100)
    target_progress = _round(
        100 if target_amount <= 0 else min(max(account_profit / target_amount * 100, 0), 100)
    )
    target_reached = target_amount <= 0 or account_profit >= target_amount
    remaining_target = _round(max(target_amount - account_profit, 0))

    day_key = _reset_day(clock, timezone_name)
    today_closed = _round(
        sum(
            _net_profit(item)
            for item in closed_records
            if _reset_day(_record_time(item, clock), timezone_name) == day_key
        )
    )
    today_floating = floating_profit
    today_total = _round(today_closed + today_floating)
    daily_start_balance = _round(current_balance - today_closed)
    daily_start_equity = _round(current_equity - today_total)
    daily_base = _daily_base(
        str(challenge.get("daily_loss_calculation_type") or "balance_based"),
        daily_start_balance,
        daily_start_equity,
    )
    daily_limit = _round(
        daily_base * _float(challenge.get("daily_loss_limit_percent"), 5) / 100
    )
    daily_used = _round(abs(today_total) if today_total < 0 else 0)
    daily_remaining = _round(max(daily_limit - daily_used, 0))
    daily_used_percent = _percent(daily_used, daily_limit)
    daily_breach = daily_limit > 0 and daily_used >= daily_limit
    daily_warning = not daily_breach and daily_limit > 0 and daily_used >= daily_limit * 0.8

    max_limit = _round(
        starting_balance * _float(challenge.get("maximum_loss_limit_percent"), 10) / 100
    )
    peak = max([starting_balance, *_equity_curve(starting_balance, closed_records), current_equity])
    drawdown_baseline = (
        peak
        if str(challenge.get("maximum_loss_calculation_type"))
        in {"trailing_equity", "trailing_balance"}
        else starting_balance
    )
    minimum_allowed = _round(drawdown_baseline - max_limit)
    drawdown = _round(max(drawdown_baseline - current_equity, 0))
    max_remaining = _round(max(max_limit - drawdown, 0))
    max_used_percent = _percent(drawdown, max_limit)
    max_breach = max_limit > 0 and drawdown >= max_limit
    max_warning = not max_breach and max_limit > 0 and max_remaining <= max_limit * 0.2

    trading_days = len(
        {
            _reset_day(_record_time(item, clock), timezone_name)
            for item in closed_records
        }
    )
    remaining_days = max(minimum_days - trading_days, 0)
    minimum_days_satisfied = trading_days >= minimum_days

    failed = daily_breach or max_breach or challenge.get("status") == "failed"
    phase_1_passed = (
        not failed
        and current_phase == "phase_1"
        and target_reached
        and minimum_days_satisfied
    )
    phase_2_passed = (
        not failed
        and current_phase == "phase_2"
        and target_reached
        and minimum_days_satisfied
    )
    phase_1_in_progress = not failed and current_phase == "phase_1" and not phase_1_passed
    phase_2_in_progress = not failed and current_phase == "phase_2" and not phase_2_passed
    funded = current_phase == "funded"
    risk_status = _risk_status(
        failed=failed,
        passed=phase_1_passed or phase_2_passed or funded,
        daily_warning=daily_warning,
        max_warning=max_warning,
        daily_used_percent=daily_used_percent,
        max_remaining=max_remaining,
        max_limit=max_limit,
    )

    challenge_id = str(challenge.get("challenge_id") or challenge.get("id") or "")
    user_id = str(challenge.get("user_id") or "")
    account_key = str(challenge.get("mt5_account_key") or "")
    metrics = {
        "challenge_id": challenge_id,
        "user_id": user_id,
        "mt5_account_key": account_key,
        "mt5_login": challenge.get("mt5_login") or account.get("mt5_login"),
        "broker_server": challenge.get("broker_server") or account.get("broker_server"),
        "current_phase": current_phase,
        "risk_status": risk_status,
        "starting_balance": _round(starting_balance),
        "current_balance": _round(current_balance),
        "current_equity": _round(current_equity),
        "account_profit": account_profit,
        "account_profit_percent": account_profit_percent,
        "floating_profit_loss": floating_profit,
        "closed_profit_loss": closed_profit,
        "total_commission": total_commission,
        "total_swap": total_swap,
        "phase_profit_target_amount": target_amount,
        "current_profit_amount": account_profit,
        "current_profit_percent": account_profit_percent,
        "remaining_profit_required": remaining_target,
        "profit_target_progress_percent": target_progress,
        "target_reached": target_reached,
        "daily_start_balance": daily_start_balance,
        "daily_start_equity": daily_start_equity,
        "today_closed_profit_loss": today_closed,
        "today_floating_profit_loss": today_floating,
        "today_total_profit_loss": today_total,
        "daily_loss_limit_amount": daily_limit,
        "daily_loss_used_amount": daily_used,
        "daily_loss_remaining_amount": daily_remaining,
        "daily_loss_used_percent": daily_used_percent,
        "daily_loss_breach": daily_breach,
        "daily_loss_warning": daily_warning,
        "maximum_loss_limit_amount": max_limit,
        "minimum_allowed_balance_equity": minimum_allowed,
        "current_drawdown_amount": drawdown,
        "maximum_loss_remaining_amount": max_remaining,
        "maximum_loss_used_percent": max_used_percent,
        "maximum_loss_breach": max_breach,
        "maximum_loss_warning": max_warning,
        "trading_days_count": trading_days,
        "phase_minimum_trading_days_required": minimum_days,
        "remaining_trading_days": remaining_days,
        "minimum_days_satisfied": minimum_days_satisfied,
        "phase_1_in_progress": phase_1_in_progress,
        "phase_1_passed": phase_1_passed,
        "phase_2_in_progress": phase_2_in_progress,
        "phase_2_passed": phase_2_passed,
        "funded": funded,
        "failed": failed,
        "warning": risk_status in {"warning", "near_breach"},
        "updated_at": clock,
    }
    snapshot = {
        "challenge_id": challenge_id,
        "user_id": user_id,
        "mt5_account_key": account_key,
        "date": day_key,
        "timezone": timezone_name,
        "daily_start_balance": daily_start_balance,
        "daily_start_equity": daily_start_equity,
        "daily_closed_profit_loss": today_closed,
        "daily_floating_profit_loss": today_floating,
        "daily_total_profit_loss": today_total,
        "daily_loss_limit_amount": daily_limit,
        "daily_loss_used_amount": daily_used,
        "daily_loss_remaining_amount": daily_remaining,
        "daily_loss_breached": daily_breach,
        "updated_at": clock,
    }
    return metrics, snapshot


def _closed_records(records: list[dict[str, Any]], start_date: datetime | None) -> list[dict[str, Any]]:
    dataset = build_trade_analytics_dataset(records)
    closed = []
    for item in dataset.executed_closed_trades:
        record_time = _record_time(item)
        if start_date is not None and record_time is not None and record_time < start_date:
            continue
        closed.append(item)
    return closed


def _equity_curve(starting_balance: float, records: list[dict[str, Any]]) -> list[float]:
    equity = starting_balance
    curve = [equity]
    for item in sorted(records, key=lambda record: _record_time(record) or datetime.min.replace(tzinfo=timezone.utc)):
        equity += _net_profit(item)
        curve.append(equity)
    return curve


def _record_time(item: dict[str, Any], fallback: datetime | None = None) -> datetime | None:
    return _dt(item.get("close_time")) or _dt(item.get("open_time")) or fallback


def _daily_base(kind: str, balance: float, equity: float) -> float:
    if kind == "equity_based":
        return equity
    if kind == "balance_or_equity":
        return min(balance, equity)
    return balance


def _risk_status(
    *,
    failed: bool,
    passed: bool,
    daily_warning: bool,
    max_warning: bool,
    daily_used_percent: float,
    max_remaining: float,
    max_limit: float,
) -> str:
    if failed:
        return "breached"
    if passed:
        return "passed"
    if daily_used_percent >= 90 or (max_limit > 0 and max_remaining <= max_limit * 0.1):
        return "near_breach"
    if daily_warning or max_warning:
        return "warning"
    return "healthy"


def _reset_day(value: datetime, timezone_name: str) -> str:
    shifted = value.astimezone(timezone.utc).timestamp() + _timezone_offset(timezone_name) * 3600
    return datetime.fromtimestamp(shifted, tz=timezone.utc).strftime("%Y%m%d")


def _timezone_offset(timezone_name: str) -> int:
    return {
        "UTC": 0,
        "Asia/Singapore": 8,
        "Asia/Kuala_Lumpur": 8,
        "America/New_York": -5,
        "Europe/London": 0,
    }.get(timezone_name, 8)


def _net_profit(item: dict[str, Any]) -> float:
    return (
        _float(item.get("profit"))
        + _float(item.get("commission"))
        + _float(item.get("swap"))
    )


def _percent(value: float, base: float) -> float:
    return 0 if base <= 0 else _round(value / base * 100)


def _float(value: Any, fallback: float = 0.0) -> float:
    if value is None or value == "":
        return fallback
    try:
        return float(value)
    except (TypeError, ValueError):
        return fallback


def _int(value: Any, fallback: int = 0) -> int:
    if value is None or value == "":
        return fallback
    try:
        return int(value)
    except (TypeError, ValueError):
        return fallback


def _dt(value: Any) -> datetime | None:
    if value is None or value == "":
        return None
    if isinstance(value, datetime):
        return value.astimezone(timezone.utc)
    if isinstance(value, str):
        return datetime.fromisoformat(value.replace("Z", "+00:00")).astimezone(timezone.utc)
    return None


def _round(value: float) -> float:
    return round(float(value), 2)
