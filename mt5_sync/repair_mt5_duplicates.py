"""Repair duplicated MT5 lifecycle records for one linked account.

Dry run is the default. Pass --apply only after reviewing the summary.
"""

from __future__ import annotations

import argparse
import json
from typing import Any

from config_loader import load_sync_config
from db_adapter import DatabaseAdapter


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Merge duplicated MT5 trade/order records by lifecycle key.",
    )
    parser.add_argument("--login", type=int, required=True, help="MT5 account login.")
    parser.add_argument("--server", required=True, help="MT5 broker/server name.")
    parser.add_argument(
        "--apply",
        action="store_true",
        help="Write merged records and delete duplicate docs. Omit for dry run.",
    )
    parser.add_argument(
        "--limit",
        type=int,
        default=2000,
        help="Maximum records to scan per trades/orders collection.",
    )
    args = parser.parse_args()

    config = load_sync_config()
    db = DatabaseAdapter()
    result = db.repair_mt5_duplicates(
        config.user_id,
        args.login,
        args.server,
        dry_run=not args.apply,
        limit=args.limit,
    )
    print(json.dumps(_json_ready(result), indent=2, sort_keys=True))
    return 0


def _json_ready(value: Any) -> Any:
    if hasattr(value, "isoformat"):
        return value.isoformat()
    if isinstance(value, dict):
        return {key: _json_ready(item) for key, item in value.items()}
    if isinstance(value, list):
        return [_json_ready(item) for item in value]
    return value


if __name__ == "__main__":
    raise SystemExit(main())
