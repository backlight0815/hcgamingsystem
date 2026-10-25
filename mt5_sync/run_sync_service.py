"""Realtime MT5 to database sync service for local Windows or Windows VPS."""

from __future__ import annotations

import os
import sys
import time
from datetime import datetime, timedelta, timezone
from typing import Any

from account_resolver import AllowedMt5Account, load_allowed_accounts, resolve_current_account
from config_loader import SyncConfig, load_sync_config
from connection_manager import Mt5ConnectionManager
from db_adapter import DatabaseAdapter
from normalizers import trade_key
from prop_firm_sync import update_prop_firm_metrics
from realtime_watcher import RealtimeMt5Watcher
from terminal_discovery import existing_terminal_paths

SERVICE_VERSION = "1.5.0"
DEFAULT_SYNC_STALE_AFTER_SECONDS = 600


def env_bool(name: str, default: bool) -> bool:
    value = os.getenv(name, "").strip().lower()
    if not value:
        return default
    return value in {"1", "true", "yes", "on"}


def env_int(name: str, default: int) -> int:
    try:
        return int(os.getenv(name, str(default)))
    except ValueError:
        return default


def recovery_from(db: DatabaseAdapter, user_id: str, login: int, server: str) -> datetime:
    now = datetime.now(timezone.utc)
    last_synced = db.last_synced_time(user_id, login, server)
    overlap_days = max(1, min(env_int("SYNC_HISTORY_OVERLAP_DAYS", 3), 3))
    initial_days = max(1, env_int("SYNC_INITIAL_HISTORY_DAYS", 7))
    return (last_synced - timedelta(days=overlap_days)) if last_synced else now - timedelta(days=initial_days)


def write_state(
    db: DatabaseAdapter,
    user_id: str,
    account: AllowedMt5Account,
    *,
    status: str,
    interval: int,
    realtime_mode: bool,
    **data: Any,
) -> None:
    db.update_sync_state(
        user_id,
        account.login,
        account.server,
        account_key=account.account_key,
        account_number=account.login,
        sync_status=status,
        display_status=data.pop("display_status", _display_status(status)),
        sync_interval_seconds=interval,
        realtime_mode=realtime_mode,
        service_version=SERVICE_VERSION,
        **data,
    )


def state_heartbeat_interval(interval: int) -> int:
    free_tier_safe_mode = env_bool("FREE_TIER_SAFE_MODE", True)
    default_seconds = max(300, interval) if free_tier_safe_mode else max(60, interval * 5)
    return max(60, env_int("SYNC_STATE_HEARTBEAT_INTERVAL_SECONDS", default_seconds))


def sync_realtime(
    *,
    db: DatabaseAdapter,
    watcher: RealtimeMt5Watcher,
    mt5: Any | None,
    user_id: str,
    interval: int,
    realtime_mode: bool,
    mock: bool,
    run_once: bool,
    prop_firm_metrics_enabled: bool,
    prop_firm_metrics_interval: int,
    config: SyncConfig,
) -> int:
    reconnect_interval = max(1, config.reconnect_interval_seconds)
    max_reconnect_backoff = max(
        reconnect_interval,
        env_int("MAX_RECONNECT_BACKOFF_SECONDS", 60),
    )
    auto_resume = env_bool("AUTO_RESUME_AFTER_LOGIN", True)
    scan_all_terminals = env_bool("MT5_SCAN_ALL_TERMINALS", True)
    terminal_timeout_ms = env_int("MT5_INITIALIZE_TIMEOUT_MS", 60000)
    discovered_terminal_paths = existing_terminal_paths(
        manual_path="",
        auto_discover=config.auto_discover_terminal,
    )
    manager = None
    if not mock and mt5 is not None and not scan_all_terminals:
        manager = Mt5ConnectionManager(
            mt5,
            terminal_path=config.terminal_path,
            terminal_paths=discovered_terminal_paths,
            auto_discover_terminal=config.auto_discover_terminal,
            timeout_ms=terminal_timeout_ms,
        )
    state_interval = state_heartbeat_interval(interval)
    state_defaults = {
        "free_tier_safe_mode": env_bool("FREE_TIER_SAFE_MODE", True),
        "sync_floating_profit_updates": watcher.sync_floating_profit_updates,
        "floating_update_min_interval_seconds": watcher.floating_update_min_interval_seconds,
        "floating_update_min_profit_change": watcher.floating_update_min_profit_change,
        "floating_update_min_price_change_points": watcher.floating_update_min_price_change_points,
        "sync_state_heartbeat_interval_seconds": state_interval,
        "sync_stale_after_seconds": env_int(
            "MT5_SYNC_STALE_AFTER_SECONDS",
            DEFAULT_SYNC_STALE_AFTER_SECONDS,
        ),
        "connection_mode": config.machine_profile,
        "machine_profile": config.machine_profile,
        "terminal_auto_discover": config.auto_discover_terminal,
    }

    runtime: dict[str, dict[str, Any]] = {}
    active_account_key = ""
    reconnect_delay = reconnect_interval
    last_prop_firm_metrics_at: datetime | None = None

    def runtime_for(account: AllowedMt5Account) -> dict[str, Any]:
        state = runtime.get(account.account_key)
        if state is None:
            state = {
                "history_from": recovery_from(db, user_id, account.login, account.server),
                "first_cycle": True,
                "last_trade_change_version": db.last_trade_change_version(
                    user_id,
                    account.login,
                    account.server,
                ),
                "last_state_write_at": None,
                "last_state_status": "",
            }
            runtime[account.account_key] = state
        return state

    def write_service_state(
        account: AllowedMt5Account,
        status: str,
        *,
        force: bool = False,
        **data: Any,
    ) -> None:
        state = runtime_for(account)
        now = datetime.now(timezone.utc)
        offline_status = status in {
            "account_not_linked",
            "disconnected",
            "logged_out",
            "terminal_closed",
            "error",
        }
        write_interval = state_interval
        due = (
            state["last_state_write_at"] is None
            or status != state["last_state_status"]
            or (now - state["last_state_write_at"]).total_seconds() >= write_interval
        )
        if not (force or due):
            return
        payload = {**state_defaults, **data}
        payload.setdefault("last_connection_check_at", now)
        payload.setdefault("last_heartbeat_at", now)
        payload.setdefault("last_error", "")
        payload.setdefault("last_error_code", "")
        payload.setdefault("last_error_message_internal", "")
        if status in {"disconnected", "logged_out", "terminal_closed", "error"}:
            payload.setdefault("last_error_at", now)
        write_state(
            db,
            user_id,
            account,
            status=status,
            interval=interval,
            realtime_mode=realtime_mode,
            **payload,
        )
        state["last_state_write_at"] = now
        state["last_state_status"] = status

    def mark_accounts(
        accounts: dict[str, AllowedMt5Account],
        status: str,
        *,
        force: bool = False,
        error_code: str = "",
        message: str = "",
        detected_login: int | None = None,
        detected_server: str = "",
    ) -> None:
        for account in accounts.values():
            write_service_state(
                account,
                status,
                force=force,
                last_error_code=error_code,
                last_error_message_internal=message,
                detected_mt5_login=detected_login,
                detected_broker_server=detected_server,
            )

    def accounts_for_machine(
        accounts: dict[str, AllowedMt5Account],
    ) -> dict[str, AllowedMt5Account]:
        machine_profile = _normalize_machine_profile(config.machine_profile)
        return {
            key: account
            for key, account in accounts.items()
            if _account_matches_machine(account, machine_profile)
        }

    def terminal_managers() -> list[tuple[str, Mt5ConnectionManager]]:
        if mt5 is None:
            return []
        if not scan_all_terminals and manager is not None:
            return [("primary_terminal", manager)]

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
                        timeout_ms=terminal_timeout_ms,
                    ),
                )
            )

        if config.terminal_path:
            add("configured_terminal", config.terminal_path, allow_running=False)
        for path in discovered_terminal_paths:
            add(path, path, allow_running=False)
        add("running_terminal", "", allow_running=True)
        return managers

    def process_poll_result(
        account: AllowedMt5Account,
        account_state: dict[str, Any],
        result: Any,
        cycle_started_at: datetime,
    ) -> None:
        nonlocal last_prop_firm_metrics_at, reconnect_delay

        account_was_changed = watcher.account_changed(result.account)
        if account_was_changed:
            db.upsert_account(result.account)

        writes = 0
        activity_keys = {trade_key(record) for record in result.activity_records}
        written_activity_records: list[dict[str, Any]] = []
        for record in result.changed_records:
            if db.upsert_record(record):
                writes += 1
                if trade_key(record) in activity_keys:
                    written_activity_records.append(record)
        watcher.accept_written(result.changed_records)

        missing_writes = 0
        if account_state["first_cycle"] or result.removed_live_keys:
            missing_writes = db.reconcile_missing_live_records(
                user_id,
                account.login,
                account.server,
                result.live_position_keys,
                result.live_order_keys,
                result.close_hints,
            )

        now = datetime.now(timezone.utc)
        activity_marker: dict[str, Any] = {}
        if written_activity_records or missing_writes > 0:
            account_state["last_trade_change_version"] += 1
            activity_record = (
                written_activity_records[-1] if written_activity_records else None
            )
            activity_marker = {
                "last_trade_activity_at": now,
                "last_trade_activity_id": (
                    trade_key(activity_record)
                    if activity_record is not None
                    else (
                        result.removed_live_keys[0]
                        if result.removed_live_keys
                        else f"reconciled_{int(now.timestamp())}"
                    )
                ),
                "last_trade_activity_type": _activity_type(activity_record)
                if activity_record is not None
                else "live_record_reconciled",
                "last_trade_change_version": account_state["last_trade_change_version"],
            }

        metrics_due = (
            prop_firm_metrics_enabled
            and (
                account_was_changed
                or writes > 0
                or missing_writes > 0
                or last_prop_firm_metrics_at is None
                or (now - last_prop_firm_metrics_at).total_seconds()
                >= prop_firm_metrics_interval
            )
        )
        prop_firm_metrics_count = 0
        if metrics_due:
            prop_firm_metrics_count = update_prop_firm_metrics(
                db,
                user_id=user_id,
                login=account.login,
                server=account.server,
                account=result.account,
            )
            last_prop_firm_metrics_at = now

        write_service_state(
            account,
            "connected",
            force=(
                run_once
                or account_state["first_cycle"]
                or account_was_changed
                or writes > 0
                or missing_writes > 0
                or prop_firm_metrics_count > 0
                or bool(result.removed_live_keys)
            ),
            last_sync_started_at=cycle_started_at,
            last_synced_time=now,
            last_successful_sync_at=now,
            last_error="",
            last_error_at=None,
            changed_record_count=result.changed_count,
            written_record_count=writes,
            missing_live_closed_count=missing_writes,
            prop_firm_metrics_count=prop_firm_metrics_count,
            removed_live_count=len(result.removed_live_keys),
            source_counts=result.counts,
            **activity_marker,
        )

        account_state["first_cycle"] = False
        account_state["history_from"] = max(
            account_state["history_from"],
            result.to_date - timedelta(minutes=10),
        )
        reconnect_delay = reconnect_interval
        print(
            f"[{now.isoformat()}] sync ok account={account.account_key} "
            f"changed={result.changed_count} writes={writes} "
            f"activity={len(written_activity_records) + missing_writes} "
            f"missing_closed={missing_writes} "
            f"prop_metrics={prop_firm_metrics_count} "
            f"positions={result.counts['positions']} orders={result.counts['orders']} "
            f"deals={result.counts['deals']} history_orders={result.counts['history_orders']}"
        )

    while True:
        cycle_started = datetime.now(timezone.utc)
        all_linked_accounts = load_allowed_accounts(db, user_id)
        allowed_accounts = accounts_for_machine(all_linked_accounts)
        if not allowed_accounts:
            if all_linked_accounts:
                print(
                    "No active linked MT5 accounts assigned to "
                    f"machine_profile={config.machine_profile}. Waiting before retry."
                )
            else:
                print("No active linked MT5 accounts found. Waiting before retry.")
            if run_once:
                return 2
            time.sleep(reconnect_delay)
            reconnect_delay = min(max_reconnect_backoff, reconnect_delay * 2)
            continue

        try:
            synced_any = False
            matched_account_keys: set[str] = set()
            if mock:
                account = next(iter(allowed_accounts.values()))
                account_state = runtime_for(account)
                result = watcher.mock_poll(
                    user_id=user_id,
                    login=account.login,
                    server=account.server,
                    from_date=account_state["history_from"],
                )
                process_poll_result(account, account_state, result, cycle_started)
                synced_any = True
                matched_account_keys.add(account.account_key)
            else:
                if mt5 is None:
                    raise RuntimeError("MT5 module is not available.")
                terminal_errors: list[str] = []
                for terminal_label, terminal_manager in terminal_managers():
                    try:
                        connection = terminal_manager.ensure_initialized()
                        if not connection.connected:
                            terminal_errors.append(
                                f"{terminal_label}: {connection.status}: {connection.message}"
                            )
                            continue

                        resolution = resolve_current_account(
                            terminal_manager.account_info(),
                            allowed_accounts,
                        )
                        if resolution.status == "logged_out":
                            terminal_errors.append(
                                f"{terminal_label}: logged_out: "
                                f"{resolution.message or terminal_manager.last_error()}"
                            )
                            continue

                        if resolution.status == "connected_unlinked":
                            current = resolution.current
                            print(
                                "Current MT5 account is not linked: "
                                f"{current.server if current else ''}:"
                                f"{current.login if current else ''} "
                                f"terminal={terminal_label}. "
                                "Linked account statuses were left unchanged."
                            )
                            continue

                        if not resolution.is_linked or resolution.current is None:
                            terminal_errors.append(
                                f"{terminal_label}: unexpected resolution {resolution.status}"
                            )
                            continue

                        account = resolution.allowed or AllowedMt5Account(
                            login=resolution.current.login,
                            server=resolution.current.server,
                            account_key=resolution.current.account_key,
                            source="detected",
                        )
                        if account.account_key in matched_account_keys:
                            continue
                        matched_account_keys.add(account.account_key)
                        account_state = runtime_for(account)
                        if account_state["first_cycle"]:
                            print(
                                f"Sync target detected account={account.account_key} "
                                f"terminal={terminal_label}."
                            )

                        write_service_state(
                            account,
                            "syncing",
                            force=run_once or account_state["first_cycle"],
                            last_sync_started_at=cycle_started,
                        )
                        result = watcher.poll(
                            mt5,
                            user_id=user_id,
                            login=account.login,
                            server=account.server,
                            from_date=account_state["history_from"],
                        )
                        process_poll_result(account, account_state, result, cycle_started)
                        synced_any = True
                    finally:
                        if scan_all_terminals:
                            terminal_manager.shutdown()

                unsynced_accounts = {
                    key: account
                    for key, account in allowed_accounts.items()
                    if key not in matched_account_keys
                }
                if synced_any:
                    active_account_key = ",".join(sorted(matched_account_keys))
                    for account in unsynced_accounts.values():
                        write_service_state(
                            account,
                            "disconnected",
                            force=run_once,
                            last_error_code="no_matching_terminal",
                            last_error_message_internal=(
                                "No open MT5 terminal matched this linked account "
                                "during the latest scan."
                            ),
                        )
                else:
                    active_account_key = ""
                    if terminal_errors:
                        print("No linked MT5 terminal synced this cycle:")
                        for error in terminal_errors[:10]:
                            print(f"  - {error}")
                    mark_accounts(
                        allowed_accounts,
                        "disconnected",
                        force=run_once,
                        error_code="no_matching_terminal",
                        message=(
                            "No open MT5 terminal matched active linked accounts "
                            "during the latest scan."
                        ),
                    )
                    if run_once:
                        return 1
                    time.sleep(reconnect_delay)
                    reconnect_delay = min(max_reconnect_backoff, reconnect_delay * 2)
                    continue

            if run_once:
                return 0
        except KeyboardInterrupt:
            print("Sync stopped by user.")
            return 0
        except Exception as error:
            status = _sync_error_status(error)
            message = str(error)
            print(f"Sync cycle failed ({status}): {message}")
            try:
                if active_account_key and active_account_key in allowed_accounts:
                    write_service_state(
                        allowed_accounts[active_account_key],
                        status,
                        force=True,
                        last_error_code="sync_cycle_failed",
                        last_error_message_internal=message,
                    )
                else:
                    mark_accounts(
                        allowed_accounts,
                        status,
                        force=True,
                        error_code="sync_cycle_failed",
                        message=message,
                    )
            except Exception as state_error:
                print(f"Unable to write sync error state: {state_error}")
            if manager is not None:
                manager.shutdown()
            active_account_key = ""
            if run_once:
                return 1

        if not auto_resume and active_account_key == "":
            print("AUTO_RESUME_AFTER_LOGIN=false and no active MT5 account is linked.")
            return 1
        time.sleep(max(interval, reconnect_interval if active_account_key == "" else 1))


def main() -> int:
    try:
        config = load_sync_config()
        user_id = config.user_id
        realtime_mode = config.realtime_mode
        interval = config.sync_interval_seconds
        mock = config.mock_mt5
        run_once = config.run_once
        prop_firm_metrics_enabled = env_bool("PROP_FIRM_METRICS_ENABLED", True)
        prop_firm_metrics_interval = max(1, env_int("PROP_FIRM_METRICS_INTERVAL_SECONDS", 3))
        db = DatabaseAdapter()
        watcher = RealtimeMt5Watcher()
    except Exception as error:
        print(f"Configuration error: {error}")
        return 2

    mt5 = None
    if not mock:
        try:
            import MetaTrader5 as mt5_module
        except ImportError:
            print("MetaTrader5 is not installed. Run: pip install -r mt5_sync/requirements.txt")
            return 2
        mt5 = mt5_module

    try:
        return sync_realtime(
            db=db,
            watcher=watcher,
            mt5=mt5,
            user_id=user_id,
            interval=interval,
            realtime_mode=realtime_mode,
            mock=mock,
            run_once=run_once,
            prop_firm_metrics_enabled=prop_firm_metrics_enabled,
            prop_firm_metrics_interval=prop_firm_metrics_interval,
            config=config,
        )
    finally:
        if mt5 is not None:
            try:
                mt5.shutdown()
            except Exception:
                pass


def _activity_type(record: dict[str, Any] | None) -> str:
    if record is None:
        return "trade_changed"
    record_type = str(record.get("record_type") or "")
    status = str(record.get("status") or "")
    if record_type == "order":
        if status in {"cancelled", "expired"}:
            return "order_cancelled"
        if status == "triggered":
            return "order_triggered"
        return "order_changed"
    if status == "closed":
        return "trade_closed"
    if status == "open":
        return "trade_changed"
    return "trade_changed"


def _sync_error_status(error: Exception) -> str:
    text = str(error).lower()
    if (
        "initialize failed" in text
        or "account_info" in text
        or "positions_get failed" in text
        or "orders_get failed" in text
        or "history_deals_get failed" in text
        or "history_orders_get failed" in text
        or "ipc timeout" in text
    ):
        return "disconnected"
    return "error"


def _display_status(status: str) -> str:
    return {
        "connected": "Connected",
        "syncing": "Updating",
        "reconnecting": "Updating",
        "disconnected": "Unable to update right now",
        "logged_out": "Disconnected",
        "terminal_closed": "Disconnected",
        "account_not_linked": "Account not linked",
        "error": "Unable to update right now",
    }.get(status, "Unable to update right now")


def _normalize_machine_profile(value: str) -> str:
    text = str(value or "").strip().lower().replace("-", "_").replace(" ", "_")
    if text in {"hosted", "hosted_device", "server", "remote"}:
        return "vps"
    if text in {"this_device", "pc", "desktop"}:
        return "local"
    return text


def _account_matches_machine(
    account: AllowedMt5Account,
    machine_profile: str,
) -> bool:
    account_profile = _normalize_machine_profile(account.machine_profile)
    if not account_profile or account_profile in {"any", "all"}:
        return True
    if not machine_profile or machine_profile in {"any", "all"}:
        return True
    return account_profile == machine_profile


if __name__ == "__main__":
    sys.exit(main())
