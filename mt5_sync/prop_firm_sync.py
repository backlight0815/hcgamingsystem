"""Update Prop Firm metrics from synced MT5 records."""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any

from normalizers import account_key
from prop_firm_calculator import calculate_prop_firm_metrics


def update_prop_firm_metrics(
    db: Any,
    *,
    user_id: str,
    login: int,
    server: str,
    account: dict[str, Any],
) -> int:
    key = account_key(server, login)
    challenges = db.prop_firm_challenges_for_account(user_id, key)
    if not challenges:
        return 0

    records = db.mt5_records_for_account(user_id, login, server)
    updated = 0
    for challenge in challenges:
        challenge_id = str(challenge.get("challenge_id") or challenge.get("id") or "")
        if not challenge_id:
            continue
        previous = db.current_prop_firm_metrics(user_id, challenge_id)
        metrics, snapshot = calculate_prop_firm_metrics(
            challenge,
            account,
            records,
            now=datetime.now(timezone.utc),
        )
        events = _events_for_transition(challenge, previous, metrics)
        challenge_update = _challenge_update_for_metrics(challenge, metrics)
        db.upsert_prop_firm_metrics(
            user_id,
            challenge_id,
            metrics,
            snapshot,
            events,
            challenge_update,
        )
        updated += 1
    return updated


def _events_for_transition(
    challenge: dict[str, Any],
    previous: dict[str, Any] | None,
    metrics: dict[str, Any],
) -> list[dict[str, Any]]:
    events = []
    challenge_id = str(challenge.get("challenge_id") or challenge.get("id") or "")
    phase = str(metrics.get("current_phase") or "phase_1")
    today = datetime.now(timezone.utc).strftime("%Y%m%d")

    def add(event_type: str, key_part: str, message: str) -> None:
        events.append(
            {
                "event_id": f"{challenge_id}*{event_type}*{key_part}",
                "challenge_id": challenge_id,
                "user_id": challenge.get("user_id"),
                "mt5_account_key": challenge.get("mt5_account_key"),
                "event_type": event_type,
                "phase": phase,
                "message": message,
                "risk_status": metrics.get("risk_status"),
                "created_from": "python_mt5_sync",
            }
        )

    if _became_true(previous, metrics, "target_reached"):
        add("profit_target_reached", phase, "Profit target reached.")
    if _became_true(previous, metrics, "daily_loss_warning"):
        add("daily_loss_warning", today, "Daily loss warning.")
    if _became_true(previous, metrics, "daily_loss_breach"):
        add("daily_loss_breached", today, "Daily loss breached.")
    if _became_true(previous, metrics, "maximum_loss_warning"):
        add("max_loss_warning", today, "Maximum loss warning.")
    if _became_true(previous, metrics, "maximum_loss_breach"):
        add("max_loss_breached", today, "Maximum loss breached.")
    if _became_true(previous, metrics, "phase_1_passed"):
        add("phase_passed", "phase_1", "Phase 1 passed.")
    if _became_true(previous, metrics, "phase_2_passed"):
        add("phase_passed", "phase_2", "Phase 2 passed.")
    if _became_true(previous, metrics, "failed"):
        add("challenge_failed", today, "Challenge failed.")
    return events


def _challenge_update_for_metrics(
    challenge: dict[str, Any],
    metrics: dict[str, Any],
) -> dict[str, Any] | None:
    now = datetime.now(timezone.utc)
    phase = str(challenge.get("current_phase") or "phase_1")
    if metrics.get("failed") and phase != "failed":
        return {"current_phase": "failed", "status": "failed"}
    if metrics.get("phase_1_passed") and phase == "phase_1":
        return {
            "current_phase": "phase_2",
            "status": "active",
            "phase_1_completed_at": now,
            "phase_2_started_at": now,
        }
    if metrics.get("phase_2_passed") and phase == "phase_2":
        return {
            "current_phase": "passed",
            "status": "passed",
            "phase_2_completed_at": now,
        }
    return None


def _became_true(
    previous: dict[str, Any] | None,
    current: dict[str, Any],
    field: str,
) -> bool:
    if not current.get(field):
        return False
    return not (previous or {}).get(field)
