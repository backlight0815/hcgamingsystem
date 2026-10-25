"""Database adapter for MT5 sync.

Firebase is the preferred path because the Flutter app already uses Firebase.
Supabase support is included as a free-tier fallback, but the current app UI
reads Firestore.
"""

from __future__ import annotations

import os
from datetime import datetime, timezone
from typing import Any

from normalizers import account_key, lifecycle_key, trade_key

MT5_UPDATE_FIELDS = {
    "account_key",
    "mt5_login",
    "broker_server",
    "symbol",
    "order_ticket",
    "deal_ticket",
    "position_id",
    "unique_mt5_id",
    "trade_key",
    "lifecycle_key",
    "trade_type",
    "record_type",
    "profit",
    "lot_size",
    "entry_price",
    "close_price",
    "current_price",
    "close_time",
    "open_time",
    "status",
    "commission",
    "swap",
    "magic_number",
    "comment",
    "source",
    "sl",
    "tp",
    "synced_at",
    "last_seen_at",
    "record_hash",
    "raw_mt5_type",
    "raw_mt5_reason",
    "raw_order_state",
    "raw_deal_entry",
    "requested_volume",
    "executed_volume",
    "remaining_volume",
    "has_entry_deal",
    "has_exit_deal",
    "was_triggered",
}

JOURNAL_FIELDS = {
    "mood",
    "confidence",
    "discipline",
    "followed_trading_plan",
    "plan_compliance_checklist",
    "strategy_compliance_checklist",
    "followed_risk_rules",
    "avoided_fomo",
    "followed_session_rules",
    "avoided_restricted_news",
    "stopped_after_max_loss",
    "followed_strategy_rules",
    "followed_sl_rule",
    "followed_tp_rule",
    "followed_invalidation_rule",
    "mistake_reason",
    "notes",
    "screenshot_url",
    "custom_tags",
    "strategy_id",
    "strategy_name",
    "trading_plan_id",
    "trading_plan_name",
}

PRESERVE_WHEN_INCOMING_EMPTY = {
    "symbol",
    "trade_type",
    "lot_size",
    "entry_price",
    "open_time",
    "sl",
    "tp",
    "magic_number",
    "comment",
}


class DatabaseAdapter:
    def __init__(self) -> None:
        provider = os.getenv("DATABASE_PROVIDER", "firebase").strip().lower()
        if provider in {"firebase_or_supabase", ""}:
            provider = "firebase"
        self.provider = provider
        if provider == "firebase":
            self._impl = FirebaseAdapter()
        elif provider == "supabase":
            self._impl = SupabaseAdapter()
        else:
            raise RuntimeError(f"Unsupported DATABASE_PROVIDER: {provider}")

    def upsert_account(self, account: dict[str, Any]) -> None:
        self._impl.upsert_account(account)

    def linked_mt5_accounts(self, user_id: str) -> list[dict[str, Any]]:
        return self._impl.linked_mt5_accounts(user_id)

    def upsert_record(self, record: dict[str, Any]) -> bool:
        return self._impl.upsert_record(record)

    def update_sync_state(self, user_id: str, login: int, server: str, **data: Any) -> None:
        self._impl.update_sync_state(user_id, login, server, **data)

    def sync_state(self, user_id: str, login: int, server: str) -> dict[str, Any]:
        return self._impl.sync_state(user_id, login, server)

    def last_trade_change_version(self, user_id: str, login: int, server: str) -> int:
        state = self.sync_state(user_id, login, server)
        try:
            return int(state.get("last_trade_change_version") or 0)
        except (TypeError, ValueError):
            return 0

    def last_synced_time(self, user_id: str, login: int, server: str) -> datetime | None:
        return self._impl.last_synced_time(user_id, login, server)

    def reconcile_missing_live_records(
        self,
        user_id: str,
        login: int,
        server: str,
        live_position_keys: set[str],
        live_order_keys: set[str],
        close_hints: dict[str, dict[str, Any]],
    ) -> int:
        return self._impl.reconcile_missing_live_records(
            user_id,
            login,
            server,
            live_position_keys,
            live_order_keys,
            close_hints,
        )

    def prop_firm_challenges_for_account(
        self,
        user_id: str,
        account_key_value: str,
    ) -> list[dict[str, Any]]:
        return self._impl.prop_firm_challenges_for_account(user_id, account_key_value)

    def mt5_records_for_account(
        self,
        user_id: str,
        login: int,
        server: str,
        limit: int = 1000,
    ) -> list[dict[str, Any]]:
        return self._impl.mt5_records_for_account(user_id, login, server, limit)

    def repair_mt5_duplicates(
        self,
        user_id: str,
        login: int,
        server: str,
        *,
        dry_run: bool = True,
        limit: int = 2000,
    ) -> dict[str, Any]:
        return self._impl.repair_mt5_duplicates(
            user_id,
            login,
            server,
            dry_run=dry_run,
            limit=limit,
        )

    def current_prop_firm_metrics(
        self,
        user_id: str,
        challenge_id: str,
    ) -> dict[str, Any] | None:
        return self._impl.current_prop_firm_metrics(user_id, challenge_id)

    def upsert_prop_firm_metrics(
        self,
        user_id: str,
        challenge_id: str,
        metrics: dict[str, Any],
        daily_snapshot: dict[str, Any],
        events: list[dict[str, Any]],
        challenge_update: dict[str, Any] | None = None,
    ) -> None:
        self._impl.upsert_prop_firm_metrics(
            user_id,
            challenge_id,
            metrics,
            daily_snapshot,
            events,
            challenge_update,
        )


class FirebaseAdapter:
    def __init__(self) -> None:
        try:
            import firebase_admin
            from firebase_admin import credentials, firestore
        except ImportError as error:
            raise RuntimeError(
                "firebase-admin is required. Run: pip install -r mt5_sync/requirements.txt"
            ) from error

        if not firebase_admin._apps:
            service_account = os.getenv("FIREBASE_SERVICE_ACCOUNT_PATH", "").strip()
            project_id = os.getenv("FIREBASE_PROJECT_ID", "").strip()
            options = {"projectId": project_id} if project_id else None
            if service_account:
                firebase_admin.initialize_app(
                    credentials.Certificate(service_account),
                    options=options,
                )
            else:
                firebase_admin.initialize_app(options=options)
        self.db = firestore.client()
        self.firestore = firestore

    def _account_ref(self, user_id: str, login: int, server: str):
        key = account_key(server, login)
        return self.db.collection("users").document(user_id).collection("mt5_accounts").document(key)

    def upsert_account(self, account: dict[str, Any]) -> None:
        ref = self._account_ref(account["user_id"], account["mt5_login"], account["broker_server"])
        data = dict(account)
        data["ownerUid"] = account["user_id"]
        data["userId"] = account["user_id"]
        data["mt5AccountId"] = ref.id
        data["mt5_account_id"] = ref.id
        data["account_key"] = ref.id
        data["updated_at"] = _utc_now()
        data["last_synced_time"] = _utc_now()
        data["last_successful_sync_at"] = _utc_now()
        data.setdefault("is_active", True)
        ref.set(data, merge=True)

    def linked_mt5_accounts(self, user_id: str) -> list[dict[str, Any]]:
        snapshots = (
            self.db.collection("users")
            .document(user_id)
            .collection("mt5_accounts")
            .stream()
        )
        accounts: list[dict[str, Any]] = []
        for snapshot in snapshots:
            data = snapshot.to_dict() or {}
            if data.get("is_active") is False:
                continue
            data["id"] = snapshot.id
            accounts.append(data)
        return accounts

    def upsert_record(self, record: dict[str, Any]) -> bool:
        ref = self._account_ref(record["user_id"], record["mt5_login"], record["broker_server"])
        collection_name = "orders" if record["record_type"] == "order" else "trades"
        doc = ref.collection(collection_name).document(trade_key(record))
        existing = doc.get()
        if existing.exists:
            existing_data = existing.to_dict() or {}
            if (
                record.get("record_hash")
                and existing_data.get("record_hash") == record.get("record_hash")
            ):
                _log_sync_decision("skipped unchanged trade", record)
                return False
            update = _mt5_update_payload(existing_data, record)
            update["updated_at"] = _utc_now()
            merged_update = self._merge_lifecycle_duplicates(
                ref,
                collection_name,
                {**existing_data, **update},
                dry_run=False,
            )
            if merged_update != {**existing_data, **update}:
                update = {**merged_update, "updated_at": _utc_now()}
            doc.set(update, merge=True)
            _log_record_update(existing_data, update, record)
            return True
        else:
            data = self._merge_lifecycle_duplicates(ref, collection_name, record, dry_run=False)
            for field in JOURNAL_FIELDS:
                data.setdefault(field, None if field != "custom_tags" else [])
            data["created_at"] = _utc_now()
            data["updated_at"] = _utc_now()
            doc.set(data, merge=True)
            _log_sync_decision("inserted new trade", record)
            return True

    def update_sync_state(self, user_id: str, login: int, server: str, **data: Any) -> None:
        ref = self._account_ref(user_id, login, server)
        payload = {
            "ownerUid": user_id,
            "userId": user_id,
            "user_id": user_id,
            "mt5AccountId": account_key(server, login),
            "mt5_account_id": account_key(server, login),
            "mt5_login": login,
            "broker_server": server,
            "account_key": account_key(server, login),
            "account_number": login,
            "updated_at": _utc_now(),
            "service_version": data.pop("service_version", "1.1.0"),
            **data,
        }
        payload.setdefault("display_status", _display_status(payload.get("sync_status")))
        payload["sync_service_version"] = payload["service_version"]
        ref.collection("sync_state").document("default").set(payload, merge=True)
        account_update = {
            "ownerUid": user_id,
            "userId": user_id,
            "user_id": user_id,
            "mt5AccountId": payload.get("account_key") or account_key(server, login),
            "mt5_account_id": payload.get("account_key") or account_key(server, login),
            "account_key": payload.get("account_key") or account_key(server, login),
            "mt5_login": login,
            "account_number": login,
            "broker_server": server,
            "sync_status": payload.get("sync_status", "unknown"),
            "last_sync_status": payload.get("sync_status", "unknown"),
            "display_status": payload.get("display_status", ""),
            "last_error": payload.get("last_error", ""),
            "updated_at": _utc_now(),
        }
        if payload.get("last_successful_sync_at"):
            account_update["last_successful_sync_at"] = payload["last_successful_sync_at"]
        if payload.get("last_synced_time"):
            account_update["last_synced_time"] = payload["last_synced_time"]
        if payload.get("last_connection_check_at"):
            account_update["last_connection_check_at"] = payload["last_connection_check_at"]
        if payload.get("last_heartbeat_at"):
            account_update["last_heartbeat_at"] = payload["last_heartbeat_at"]
        if payload.get("last_trade_activity_at"):
            account_update["last_trade_activity_at"] = payload["last_trade_activity_at"]
        for field in (
            "detected_mt5_login",
            "detected_broker_server",
            "machine_profile",
            "connection_mode",
            "terminal_auto_discover",
            "service_version",
            "sync_service_version",
        ):
            if field in payload:
                account_update[field] = payload[field]
        ref.set(account_update, merge=True)

    def sync_state(self, user_id: str, login: int, server: str) -> dict[str, Any]:
        snapshot = (
            self._account_ref(user_id, login, server)
            .collection("sync_state")
            .document("default")
            .get()
        )
        if not snapshot.exists:
            return {}
        return snapshot.to_dict() or {}

    def last_synced_time(self, user_id: str, login: int, server: str) -> datetime | None:
        snapshot = (
            self._account_ref(user_id, login, server)
            .collection("sync_state")
            .document("default")
            .get()
        )
        if not snapshot.exists:
            return None
        value = snapshot.to_dict().get("last_synced_time")
        if isinstance(value, datetime):
            return value.astimezone(timezone.utc)
        if isinstance(value, str):
            return datetime.fromisoformat(value.replace("Z", "+00:00"))
        return None

    def reconcile_missing_live_records(
        self,
        user_id: str,
        login: int,
        server: str,
        live_position_keys: set[str],
        live_order_keys: set[str],
        close_hints: dict[str, dict[str, Any]],
    ) -> int:
        ref = self._account_ref(user_id, login, server)
        now = _utc_now()
        writes = 0

        open_positions = ref.collection("trades").where(
            filter=self.firestore.FieldFilter("status", "==", "open")
        )
        for doc in open_positions.stream():
            data = doc.to_dict() or {}
            if data.get("record_type") != "position":
                continue
            key = str(data.get("trade_key") or doc.id)
            if key in live_position_keys:
                continue
            hint = close_hints.get(key, {})
            payload = {
                "status": "closed",
                "lifecycle_key": data.get("lifecycle_key") or lifecycle_key(data),
                "close_time": hint.get("close_time") or now,
                "close_price": hint.get("close_price") or data.get("current_price") or 0,
                "current_price": hint.get("current_price")
                or hint.get("close_price")
                or data.get("current_price")
                or 0,
                "profit": hint.get("profit", data.get("profit", 0)),
                "commission": hint.get("commission", data.get("commission", 0)),
                "swap": hint.get("swap", data.get("swap", 0)),
                "synced_at": now,
                "last_seen_at": now,
                "updated_at": now,
            }
            doc.reference.set(payload, merge=True)
            writes += 1

        pending_orders = ref.collection("orders").where(
            filter=self.firestore.FieldFilter("status", "==", "pending")
        )
        for doc in pending_orders.stream():
            data = doc.to_dict() or {}
            key = str(data.get("trade_key") or doc.id)
            if key in live_order_keys:
                continue
            hint = close_hints.get(key, {})
            payload = {
                "status": hint.get("status") or "cancelled",
                "lifecycle_key": data.get("lifecycle_key") or lifecycle_key(data),
                "close_time": hint.get("close_time") or now,
                "close_price": hint.get("close_price") or data.get("current_price") or 0,
                "current_price": hint.get("current_price")
                or hint.get("close_price")
                or data.get("current_price")
                or 0,
                "synced_at": now,
                "last_seen_at": now,
                "updated_at": now,
            }
            doc.reference.set(payload, merge=True)
            writes += 1

        return writes

    def prop_firm_challenges_for_account(
        self,
        user_id: str,
        account_key_value: str,
    ) -> list[dict[str, Any]]:
        snapshots = (
            self.db.collection("users")
            .document(user_id)
            .collection("prop_firm_challenges")
            .stream()
        )
        challenges: list[dict[str, Any]] = []
        for snapshot in snapshots:
            data = snapshot.to_dict() or {}
            if data.get("mt5_account_key") != account_key_value:
                continue
            if data.get("status") in {"archived", "deleted"}:
                continue
            data["id"] = snapshot.id
            data["challenge_id"] = data.get("challenge_id") or snapshot.id
            challenges.append(data)
        return challenges

    def mt5_records_for_account(
        self,
        user_id: str,
        login: int,
        server: str,
        limit: int = 1000,
    ) -> list[dict[str, Any]]:
        ref = self._account_ref(user_id, login, server)
        records: list[dict[str, Any]] = []
        for collection_name in ("trades", "orders"):
            for snapshot in (
                ref.collection(collection_name)
                .order_by("updated_at", direction=self.firestore.Query.DESCENDING)
                .limit(limit)
                .stream()
            ):
                data = snapshot.to_dict() or {}
                data["id"] = snapshot.id
                records.append(data)
        return records

    def repair_mt5_duplicates(
        self,
        user_id: str,
        login: int,
        server: str,
        *,
        dry_run: bool = True,
        limit: int = 2000,
    ) -> dict[str, Any]:
        ref = self._account_ref(user_id, login, server)
        scanned = 0
        groups: dict[str, list[tuple[str, dict[str, Any]]]] = {}

        for collection_name in ("trades", "orders"):
            for snapshot in ref.collection(collection_name).limit(limit).stream():
                data = snapshot.to_dict() or {}
                data["id"] = snapshot.id
                data["_collection_name"] = collection_name
                key = lifecycle_key(data)
                groups.setdefault(f"{collection_name}:{key}", []).append(
                    (snapshot.id, data),
                )
                scanned += 1

        merged_groups = 0
        archived_duplicates = 0
        for group_key, rows in groups.items():
            if len(rows) < 2:
                continue
            collection_name, target_id = group_key.split(":", 1)
            target_ref = ref.collection(collection_name).document(target_id)
            merged = _merge_duplicate_group(rows, target_id)
            duplicate_ids = [doc_id for doc_id, _ in rows if doc_id != target_id]
            merged_groups += 1
            archived_duplicates += len(duplicate_ids)
            print(
                "[mt5_sync] merged duplicate "
                f"account={account_key(server, login)} lifecycle={target_id} "
                f"duplicates={duplicate_ids} dry_run={dry_run}"
            )
            if dry_run:
                continue
            target_ref.set({**merged, "updated_at": _utc_now()}, merge=True)
            for duplicate_id in duplicate_ids:
                ref.collection(collection_name).document(duplicate_id).delete()

        return {
            "account_key": account_key(server, login),
            "scanned": scanned,
            "merged_groups": merged_groups,
            "archived_duplicates": archived_duplicates,
            "dry_run": dry_run,
        }

    def _merge_lifecycle_duplicates(
        self,
        ref: Any,
        collection_name: str,
        record: dict[str, Any],
        *,
        dry_run: bool,
    ) -> dict[str, Any]:
        target_id = trade_key(record)
        key = lifecycle_key(record)
        rows: list[tuple[str, dict[str, Any]]] = [(target_id, dict(record))]
        try:
            snapshots = (
                ref.collection(collection_name)
                .where(filter=self.firestore.FieldFilter("lifecycle_key", "==", key))
                .limit(20)
                .stream()
            )
            for snapshot in snapshots:
                if snapshot.id == target_id:
                    continue
                data = snapshot.to_dict() or {}
                data["id"] = snapshot.id
                rows.append((snapshot.id, data))
        except Exception as error:
            print(f"[mt5_sync] duplicate lookup skipped lifecycle={key}: {error}")

        if len(rows) == 1:
            return dict(record)

        merged = _merge_duplicate_group(rows, target_id)
        duplicate_ids = [doc_id for doc_id, _ in rows if doc_id != target_id]
        _log_sync_decision(
            f"merged duplicate {duplicate_ids}",
            record,
        )
        if not dry_run:
            ref.collection(collection_name).document(target_id).set(
                {**merged, "updated_at": _utc_now()},
                merge=True,
            )
            for duplicate_id in duplicate_ids:
                ref.collection(collection_name).document(duplicate_id).delete()
        return merged

    def current_prop_firm_metrics(
        self,
        user_id: str,
        challenge_id: str,
    ) -> dict[str, Any] | None:
        snapshot = (
            self.db.collection("users")
            .document(user_id)
            .collection("prop_firm_challenges")
            .document(challenge_id)
            .collection("metrics")
            .document("current")
            .get()
        )
        if not snapshot.exists:
            return None
        return snapshot.to_dict() or {}

    def upsert_prop_firm_metrics(
        self,
        user_id: str,
        challenge_id: str,
        metrics: dict[str, Any],
        daily_snapshot: dict[str, Any],
        events: list[dict[str, Any]],
        challenge_update: dict[str, Any] | None = None,
    ) -> None:
        now = _utc_now()
        challenge_ref = (
            self.db.collection("users")
            .document(user_id)
            .collection("prop_firm_challenges")
            .document(challenge_id)
        )
        metric_payload = dict(metrics)
        metric_payload["updated_at"] = now
        challenge_ref.collection("metrics").document("current").set(
            metric_payload,
            merge=True,
        )

        snapshot_payload = dict(daily_snapshot)
        snapshot_payload.setdefault("created_at", now)
        snapshot_payload["updated_at"] = now
        snapshot_id = str(snapshot_payload.get("date") or now.strftime("%Y%m%d"))
        challenge_ref.collection("daily_snapshots").document(snapshot_id).set(
            snapshot_payload,
            merge=True,
        )

        if challenge_update:
            challenge_ref.set({**challenge_update, "updated_at": now}, merge=True)

        for event in events:
            event_id = str(event["event_id"])
            payload = {**event, "created_at": now, "updated_at": now}
            challenge_ref.collection("events").document(event_id).set(
                payload,
                merge=True,
            )


class SupabaseAdapter:
    def __init__(self) -> None:
        try:
            from supabase import create_client
        except ImportError as error:
            raise RuntimeError(
                "supabase is required. Run: pip install -r mt5_sync/requirements.txt"
            ) from error

        url = os.getenv("SUPABASE_URL", "").strip()
        key = os.getenv("SUPABASE_SERVICE_ROLE_KEY", "").strip()
        if not url or not key:
            raise RuntimeError("SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY are required.")
        self.client = create_client(url, key)

    def upsert_account(self, account: dict[str, Any]) -> None:
        payload = _json_ready({**account, "updated_at": _utc_now()})
        self.client.table("mt5_accounts").upsert(
            payload,
            on_conflict="user_id,mt5_login,broker_server",
        ).execute()

    def linked_mt5_accounts(self, user_id: str) -> list[dict[str, Any]]:
        result = (
            self.client.table("mt5_accounts")
            .select("*")
            .eq("user_id", user_id)
            .execute()
        )
        rows = result.data or []
        return [dict(row) for row in rows if row.get("is_active") is not False]

    def upsert_record(self, record: dict[str, Any]) -> bool:
        table = "mt5_orders" if record["record_type"] == "order" else "mt5_trades"
        existing = self._record_query(table, record).select("*").limit(1).execute()
        rows = existing.data or []
        if rows:
            if (
                record.get("record_hash")
                and rows[0].get("record_hash") == record.get("record_hash")
            ):
                return False
            payload = _json_ready(
                {
                    key: value
                    for key, value in record.items()
                    if key in MT5_UPDATE_FIELDS and key not in JOURNAL_FIELDS
                }
            )
            payload["updated_at"] = _utc_now().isoformat()
            self._record_query(table, record).update(payload).execute()
            return True
        else:
            payload = dict(record)
            for field in JOURNAL_FIELDS:
                payload.setdefault(field, None if field != "custom_tags" else [])
            payload["created_at"] = _utc_now()
            payload["updated_at"] = _utc_now()
            self.client.table(table).insert(_json_ready(payload)).execute()
            return True

    def update_sync_state(self, user_id: str, login: int, server: str, **data: Any) -> None:
        payload = _json_ready(
            {
                "user_id": user_id,
                "mt5_login": login,
                "broker_server": server,
                "account_key": account_key(server, login),
                "account_number": login,
                "updated_at": _utc_now(),
                "service_version": data.pop("service_version", "1.1.0"),
                **data,
            }
        )
        payload.setdefault("display_status", _display_status(payload.get("sync_status")))
        payload["sync_service_version"] = payload["service_version"]
        self.client.table("mt5_sync_state").upsert(
            payload,
            on_conflict="user_id,mt5_login,broker_server",
        ).execute()

    def sync_state(self, user_id: str, login: int, server: str) -> dict[str, Any]:
        result = (
            self.client.table("mt5_sync_state")
            .select("*")
            .eq("user_id", user_id)
            .eq("mt5_login", login)
            .eq("broker_server", server)
            .limit(1)
            .execute()
        )
        rows = result.data or []
        return dict(rows[0]) if rows else {}

    def last_synced_time(self, user_id: str, login: int, server: str) -> datetime | None:
        result = (
            self.client.table("mt5_sync_state")
            .select("last_synced_time")
            .eq("user_id", user_id)
            .eq("mt5_login", login)
            .eq("broker_server", server)
            .limit(1)
            .execute()
        )
        rows = result.data or []
        if not rows:
            return None
        value = rows[0].get("last_synced_time")
        if not value:
            return None
        return datetime.fromisoformat(str(value).replace("Z", "+00:00"))

    def reconcile_missing_live_records(
        self,
        user_id: str,
        login: int,
        server: str,
        live_position_keys: set[str],
        live_order_keys: set[str],
        close_hints: dict[str, dict[str, Any]],
    ) -> int:
        # Supabase is not used by the Flutter app in this project. Keep the
        # method available so the service can run, but avoid guessing schema
        # details for an optional fallback path.
        return 0

    def prop_firm_challenges_for_account(
        self,
        user_id: str,
        account_key_value: str,
    ) -> list[dict[str, Any]]:
        return []

    def mt5_records_for_account(
        self,
        user_id: str,
        login: int,
        server: str,
        limit: int = 1000,
    ) -> list[dict[str, Any]]:
        return []

    def repair_mt5_duplicates(
        self,
        user_id: str,
        login: int,
        server: str,
        *,
        dry_run: bool = True,
        limit: int = 2000,
    ) -> dict[str, Any]:
        return {
            "account_key": account_key(server, login),
            "scanned": 0,
            "merged_groups": 0,
            "archived_duplicates": 0,
            "dry_run": dry_run,
            "warning": "Supabase duplicate repair is not implemented; Firebase is the app path.",
        }

    def current_prop_firm_metrics(
        self,
        user_id: str,
        challenge_id: str,
    ) -> dict[str, Any] | None:
        return None

    def upsert_prop_firm_metrics(
        self,
        user_id: str,
        challenge_id: str,
        metrics: dict[str, Any],
        daily_snapshot: dict[str, Any],
        events: list[dict[str, Any]],
        challenge_update: dict[str, Any] | None = None,
    ) -> None:
        return None

    def _record_query(self, table: str, record: dict[str, Any]):
        query = (
            self.client.table(table)
            .eq("user_id", record["user_id"])
            .eq("mt5_login", record["mt5_login"])
            .eq("broker_server", record["broker_server"])
        )
        if table == "mt5_orders":
            return query.eq("order_ticket", record["order_ticket"])
        return query.eq("record_type", record["record_type"]).eq(
            "unique_mt5_id",
            record["unique_mt5_id"],
        )


def _utc_now() -> datetime:
    return datetime.now(timezone.utc)


def _json_ready(data: dict[str, Any]) -> dict[str, Any]:
    return {
        key: value.isoformat() if isinstance(value, datetime) else value
        for key, value in data.items()
    }


def _mt5_update_payload(
    existing: dict[str, Any],
    incoming: dict[str, Any],
) -> dict[str, Any]:
    payload: dict[str, Any] = {}
    for key, value in incoming.items():
        if key not in MT5_UPDATE_FIELDS or key in JOURNAL_FIELDS:
            continue
        if (
            key in PRESERVE_WHEN_INCOMING_EMPTY
            and _is_empty_mt5_value(value)
            and not _is_empty_mt5_value(existing.get(key))
        ):
            payload[key] = existing.get(key)
            continue
        payload[key] = value
    return payload


def _is_empty_mt5_value(value: Any) -> bool:
    if value is None or value == "":
        return True
    if isinstance(value, (int, float)) and not isinstance(value, bool):
        return float(value) == 0
    return False


def _merge_duplicate_group(
    rows: list[tuple[str, dict[str, Any]]],
    target_id: str,
) -> dict[str, Any]:
    if not rows:
        return {}

    preferred_id, preferred = max(
        rows,
        key=lambda row: _duplicate_rank(row[0], row[1], target_id),
    )
    merged = dict(preferred)
    merged.pop("id", None)
    merged.pop("_collection_name", None)

    key = target_id or lifecycle_key(merged)
    merged["trade_key"] = target_id
    merged["lifecycle_key"] = key
    merged["record_type"] = _record_type_for_key(key, merged.get("record_type"))
    merged["unique_mt5_id"] = str(
        merged.get("position_id")
        or merged.get("order_ticket")
        or merged.get("deal_ticket")
        or target_id
    )
    _merge_mt5_lifecycle_fields(merged, [data for _, data in rows], key)

    archived = []
    for doc_id, data in rows:
        _merge_manual_fields(merged, data)
        if doc_id == target_id and doc_id == preferred_id:
            continue
        archive = {
            "id": doc_id,
            "merged_at": _utc_now(),
            "status": data.get("status"),
            "record_type": data.get("record_type"),
            "trade_key": data.get("trade_key") or doc_id,
            "lifecycle_key": data.get("lifecycle_key") or lifecycle_key(data),
            "profit": data.get("profit"),
            "close_time": data.get("close_time"),
        }
        archived.append(archive)

    existing_archive = merged.get("merged_duplicate_records")
    if isinstance(existing_archive, list):
        archived = [*existing_archive, *archived]
    merged["merged_duplicate_records"] = archived
    merged["merged_duplicate_count"] = len(archived)
    if archived:
        merged["merged_at"] = _utc_now()
    return merged


def _merge_mt5_lifecycle_fields(
    target: dict[str, Any],
    rows: list[dict[str, Any]],
    lifecycle_id: str,
) -> None:
    """Keep one lifecycle-level MT5 record with the richest open/close data."""

    if not rows:
        return

    opening = _earliest_row(rows, "open_time") or _earliest_row(rows, "close_time")
    closing = _latest_row(
        [row for row in rows if str(row.get("status") or "") in {"closed", "triggered", "expired", "cancelled"}],
        "close_time",
    )
    closed_position = _latest_row(
        [
            row
            for row in rows
            if row.get("record_type") == "position" and str(row.get("status") or "") == "closed"
        ],
        "close_time",
    )
    deal_rows = _distinct_deal_rows(rows)

    if any(str(row.get("status") or "") in {"closed", "triggered", "expired", "cancelled"} for row in rows):
        target["status"] = "closed" if lifecycle_id.startswith("position_") else str(target.get("status") or "closed")
    elif any(str(row.get("status") or "") == "open" for row in rows):
        target["status"] = "open"
    elif any(str(row.get("status") or "") == "pending" for row in rows):
        target["status"] = "pending"

    for field in ("symbol", "trade_type", "magic_number", "comment", "source"):
        value = _first_non_empty(
            [
                opening.get(field) if opening else None,
                target.get(field),
                *(row.get(field) for row in rows),
            ]
        )
        if value is not None:
            target[field] = value

    for field in ("position_id", "order_ticket"):
        value = _first_non_empty(
            [
                opening.get(field) if opening else None,
                target.get(field),
                *(row.get(field) for row in rows),
            ]
        )
        if value is not None:
            target[field] = value

    latest_deal = _latest_row(deal_rows, "close_time")
    if latest_deal and not _is_empty_mt5_value(latest_deal.get("deal_ticket")):
        target["deal_ticket"] = latest_deal.get("deal_ticket")

    if opening:
        for field in ("entry_price", "open_time"):
            if not _is_empty_mt5_value(opening.get(field)):
                target[field] = opening.get(field)

    if closing:
        for field in ("close_price", "current_price", "close_time"):
            if not _is_empty_mt5_value(closing.get(field)):
                target[field] = closing.get(field)

    for field in ("sl", "tp"):
        value = _first_non_empty([target.get(field), *(row.get(field) for row in rows)])
        if value is not None:
            target[field] = value

    financial_source = closed_position
    if financial_source is not None:
        for field in ("profit", "commission", "swap", "lot_size"):
            target[field] = financial_source.get(field, target.get(field, 0))
    elif lifecycle_id.startswith("position_") and deal_rows:
        target["profit"] = sum(float(row.get("profit") or 0) for row in deal_rows)
        target["commission"] = sum(float(row.get("commission") or 0) for row in deal_rows)
        target["swap"] = sum(float(row.get("swap") or 0) for row in deal_rows)
        target["lot_size"] = max(
            (abs(float(row.get("lot_size") or 0)) for row in deal_rows),
            default=float(target.get("lot_size") or 0),
        )
    else:
        best = max(rows, key=lambda row: _duplicate_rank("", row, lifecycle_id))
        for field in ("profit", "commission", "swap", "lot_size"):
            if not _is_empty_mt5_value(best.get(field)):
                target[field] = best.get(field)


def _record_type_for_key(key: str, fallback: Any) -> str:
    if key.startswith("position_"):
        return "position"
    if key.startswith("order_"):
        return "order"
    if key.startswith("deal_"):
        return "deal"
    return str(fallback or "deal")


def _distinct_deal_rows(rows: list[dict[str, Any]]) -> list[dict[str, Any]]:
    seen: set[str] = set()
    unique: list[dict[str, Any]] = []
    for row in rows:
        if row.get("record_type") != "deal":
            continue
        key = str(row.get("deal_ticket") or row.get("unique_mt5_id") or row.get("trade_key") or id(row))
        if key in seen:
            continue
        seen.add(key)
        unique.append(row)
    return unique


def _earliest_row(rows: list[dict[str, Any]], field: str) -> dict[str, Any] | None:
    candidates = [row for row in rows if not _is_empty_mt5_value(row.get(field))]
    if not candidates:
        return None
    return min(candidates, key=lambda row: _sort_time(row.get(field)))


def _latest_row(rows: list[dict[str, Any]], field: str) -> dict[str, Any] | None:
    candidates = [row for row in rows if not _is_empty_mt5_value(row.get(field))]
    if not candidates:
        return None
    return max(candidates, key=lambda row: _sort_time(row.get(field)))


def _first_non_empty(values: list[Any]) -> Any:
    for value in values:
        if not _is_empty_mt5_value(value):
            return value
    return None


def _merge_manual_fields(target: dict[str, Any], source: dict[str, Any]) -> None:
    for field in JOURNAL_FIELDS:
        value = source.get(field)
        if field == "custom_tags":
            merged_tags = []
            for item in [*(target.get(field) or []), *(value or [])]:
                if item not in merged_tags:
                    merged_tags.append(item)
            target[field] = merged_tags
            continue
        if _is_empty_journal_value(target.get(field)) and not _is_empty_journal_value(value):
            target[field] = value


def _is_empty_journal_value(value: Any) -> bool:
    if value is None or value == "":
        return True
    if isinstance(value, (list, tuple, set, dict)):
        return len(value) == 0
    return False


def _duplicate_rank(doc_id: str, data: dict[str, Any], target_id: str) -> tuple[int, int, int, datetime]:
    status_rank = {
        "closed": 4,
        "triggered": 3,
        "open": 2,
        "pending": 1,
    }.get(str(data.get("status") or ""), 0)
    type_rank = {
        "position": 3,
        "deal": 2,
        "order": 1,
    }.get(str(data.get("record_type") or ""), 0)
    target_rank = 1 if doc_id == target_id else 0
    recency = _sort_time(
        data.get("close_time")
        or data.get("updated_at")
        or data.get("synced_at")
        or data.get("open_time")
    )
    return (status_rank, type_rank, target_rank, recency)


def _sort_time(value: Any) -> datetime:
    if isinstance(value, datetime):
        return value.astimezone(timezone.utc)
    if isinstance(value, str):
        try:
            return datetime.fromisoformat(value.replace("Z", "+00:00")).astimezone(
                timezone.utc,
            )
        except ValueError:
            return datetime.min.replace(tzinfo=timezone.utc)
    return datetime.min.replace(tzinfo=timezone.utc)


def _log_record_update(
    existing: dict[str, Any],
    update: dict[str, Any],
    record: dict[str, Any],
) -> None:
    if existing.get("status") == "open" and update.get("status") == "closed":
        _log_sync_decision("changed status open -> closed", record)
    if "profit" in update and float(existing.get("profit") or 0) != float(update.get("profit") or 0):
        _log_sync_decision("profit updated", record)
    _log_sync_decision("updated existing trade", record)


def _log_sync_decision(action: str, record: dict[str, Any]) -> None:
    print(
        "[mt5_sync] "
        f"{action} account={record.get('account_key') or account_key(record.get('broker_server', ''), record.get('mt5_login', ''))} "
        f"key={trade_key(record)} lifecycle={lifecycle_key(record)} "
        f"status={record.get('status')} profit={record.get('profit')}"
    )


def _display_status(status: Any) -> str:
    return {
        "connected": "Connected",
        "syncing": "Updating",
        "reconnecting": "Updating",
        "disconnected": "Unable to update right now",
        "logged_out": "Disconnected",
        "terminal_closed": "Disconnected",
        "account_not_linked": "Account not linked",
        "error": "Unable to update right now",
    }.get(str(status or ""), "Unable to update right now")
