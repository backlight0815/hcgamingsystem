"""Audit and safely normalize MT5 account ownership and canonical ids.

Dry-run is the default. The trusted owner is the parent ``users/{uid}`` path.
Conflicting ownership, conflicting account ids, and duplicate identities inside
one user are ambiguous and are never reassigned automatically.
"""

from __future__ import annotations

import argparse
import json
from collections import Counter, defaultdict
from pathlib import Path
import sys
from typing import Any

sys.path.insert(0, str(Path(__file__).resolve().parent))

from config_loader import load_sync_config
from db_adapter import FirebaseAdapter


def _text(value: Any) -> str:
    return str(value or "").strip()


def _present_values(data: dict[str, Any], keys: tuple[str, ...]) -> set[str]:
    return {_text(data.get(key)) for key in keys if _text(data.get(key))}


def _login(value: Any) -> int | None:
    try:
        parsed = int(value)
    except (TypeError, ValueError):
        return None
    return parsed if parsed > 0 else None


def _record_patch(data: dict[str, Any], uid: str, account_id: str) -> dict[str, Any] | None:
    owners = _present_values(data, ("ownerUid", "user_id", "userId"))
    account_ids = _present_values(
        data,
        ("mt5AccountId", "mt5_account_id", "account_key", "accountKey"),
    )
    if (owners and owners != {uid}) or (account_ids and account_ids != {account_id}):
        return None
    expected = {
        "ownerUid": uid,
        "userId": uid,
        "user_id": uid,
        "mt5AccountId": account_id,
        "mt5_account_id": account_id,
        "account_key": account_id,
    }
    return {key: value for key, value in expected.items() if data.get(key) != value}


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Audit and normalize Firestore MT5 ownership metadata.",
    )
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Apply unambiguous repairs. Omit for a read-only dry run.",
    )
    parser.add_argument(
        "--accounts-only",
        action="store_true",
        help="Skip nested trades, orders, and sync-state documents.",
    )
    args = parser.parse_args()

    load_sync_config()
    adapter = FirebaseAdapter()
    db = adapter.db
    stats: Counter[str] = Counter(
        {
            "scanned": 0,
            "already_valid": 0,
            "repaired": 0,
            "skipped": 0,
            "ambiguous": 0,
            "ambiguous_owner": 0,
            "ambiguous_account_id": 0,
            "ambiguous_duplicate_identity": 0,
            "failed": 0,
            "records_scanned": 0,
            "records_repaired": 0,
            "records_skipped": 0,
            "invalid_main_references": 0,
            "cleared_main_references": 0,
        }
    )

    for user_doc in db.collection("users").stream():
        uid = user_doc.id
        account_docs = list(user_doc.reference.collection("mt5_accounts").stream())
        identities: dict[tuple[int, str], list[str]] = defaultdict(list)
        for account_doc in account_docs:
            data = account_doc.to_dict() or {}
            login = _login(data.get("mt5_login", data.get("login")))
            server = _text(data.get("broker_server", data.get("brokerServer"))).lower()
            if login and server:
                identities[(login, server)].append(account_doc.id)

        valid_account_ids: set[str] = set()
        for account_doc in account_docs:
            stats["scanned"] += 1
            data = account_doc.to_dict() or {}
            login = _login(data.get("mt5_login", data.get("login")))
            server = _text(data.get("broker_server", data.get("brokerServer")))
            owners = _present_values(data, ("ownerUid", "user_id", "userId"))
            ids = _present_values(
                data,
                ("mt5AccountId", "mt5_account_id", "account_key", "accountKey"),
            )
            duplicate = bool(
                login
                and server
                and len(identities[(login, server.lower())]) > 1
            )
            owner_conflict = bool(owners and owners != {uid})
            id_conflict = bool(ids and ids != {account_doc.id})
            if owner_conflict or id_conflict or duplicate:
                stats["ambiguous"] += 1
                if owner_conflict:
                    stats["ambiguous_owner"] += 1
                if id_conflict:
                    stats["ambiguous_account_id"] += 1
                if duplicate:
                    stats["ambiguous_duplicate_identity"] += 1
                continue
            if login is None or not server:
                stats["skipped"] += 1
                continue

            patch = {
                "ownerUid": uid,
                "userId": uid,
                "user_id": uid,
                "mt5AccountId": account_doc.id,
                "mt5_account_id": account_doc.id,
                "account_key": account_doc.id,
                "mt5_login": login,
                "broker_server": server,
            }
            changes = {key: value for key, value in patch.items() if data.get(key) != value}
            try:
                if changes and args.apply:
                    account_doc.reference.set(changes, merge=True)
                if changes:
                    stats["repaired"] += 1
                else:
                    stats["already_valid"] += 1
                valid_account_ids.add(account_doc.id)
            except Exception:
                stats["failed"] += 1
                continue

            if args.accounts_only:
                continue
            for collection_name in ("trades", "orders", "sync_state"):
                for record_doc in account_doc.reference.collection(collection_name).stream():
                    stats["records_scanned"] += 1
                    record_data = record_doc.to_dict() or {}
                    record_changes = _record_patch(record_data, uid, account_doc.id)
                    if record_changes is None:
                        stats["records_skipped"] += 1
                        continue
                    if not record_changes:
                        continue
                    try:
                        if args.apply:
                            record_doc.reference.set(record_changes, merge=True)
                        stats["records_repaired"] += 1
                    except Exception:
                        stats["failed"] += 1

        user_data = user_doc.to_dict() or {}
        main_id = _text(
            user_data.get("mainMt5AccountId")
            or user_data.get("main_mt5_account_id")
            or user_data.get("mainMt5AccountKey")
            or user_data.get("main_mt5_account_key")
        )
        if main_id and main_id not in valid_account_ids:
            stats["invalid_main_references"] += 1
            if args.apply:
                delete = adapter.firestore.DELETE_FIELD
                user_doc.reference.update(
                    {
                        "mainMt5AccountId": delete,
                        "main_mt5_account_id": delete,
                        "mainMt5AccountKey": delete,
                        "main_mt5_account_key": delete,
                        "mainMt5AccountLogin": delete,
                        "main_mt5_account_login": delete,
                    }
                )
                stats["cleared_main_references"] += 1

    result = dict(sorted(stats.items()))
    result["mode"] = "apply" if args.apply else "dry-run"
    print(json.dumps(result, indent=2, sort_keys=True))
    return 0 if stats["failed"] == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())
