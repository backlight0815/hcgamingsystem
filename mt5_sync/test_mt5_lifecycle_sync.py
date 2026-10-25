from __future__ import annotations

import unittest
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from db_adapter import _merge_duplicate_group
from normalizers import lifecycle_key, normalize_deal_lifecycles, normalize_position, trade_key


class Mt5LifecycleSyncTests(unittest.TestCase):
    def test_open_position_uses_position_lifecycle_key(self) -> None:
        record = normalize_position(
            {
                "identifier": 9001,
                "ticket": 7001,
                "symbol": "XAUUSD",
                "type": 0,
                "volume": 0.1,
                "price_open": 2330.5,
                "time": 1_783_000_000,
                "profit": 0,
            },
            "user-a",
            135139957,
            "HFMarketsGlobal-Live8",
        )

        self.assertEqual(
            trade_key(record),
            "position_135139957*HFMarketsGlobal-Live8*9001",
        )
        self.assertEqual(trade_key(record), lifecycle_key(record))
        self.assertEqual(record["status"], "open")

    def test_closed_deals_collapse_to_same_position_lifecycle(self) -> None:
        records = normalize_deal_lifecycles(
            [
                {
                    "ticket": 10,
                    "order": 7001,
                    "position_id": 9001,
                    "symbol": "XAUUSD",
                    "type": 0,
                    "volume": 0.1,
                    "price": 2330.5,
                    "time": 1_783_000_000,
                    "profit": 0,
                    "commission": -0.3,
                    "swap": 0,
                },
                {
                    "ticket": 11,
                    "order": 7002,
                    "position_id": 9001,
                    "symbol": "XAUUSD",
                    "type": 1,
                    "volume": 0.1,
                    "price": 2340.5,
                    "time": 1_783_003_600,
                    "profit": 100,
                    "commission": -0.3,
                    "swap": -0.1,
                },
            ],
            "user-a",
            135139957,
            "HFMarketsGlobal-Live8",
        )

        self.assertEqual(len(records), 1)
        record = records[0]
        self.assertEqual(record["record_type"], "position")
        self.assertEqual(record["status"], "closed")
        self.assertEqual(
            record["trade_key"],
            "position_135139957*HFMarketsGlobal-Live8*9001",
        )
        self.assertEqual(record["profit"], 100)
        self.assertAlmostEqual(record["commission"], -0.6)
        self.assertAlmostEqual(record["swap"], -0.1)

    def test_live_position_deals_are_not_collapsed_while_position_is_open(self) -> None:
        records = normalize_deal_lifecycles(
            [
                {
                    "ticket": 11,
                    "order": 7002,
                    "position_id": 9001,
                    "symbol": "XAUUSD",
                    "type": 1,
                    "volume": 0.1,
                    "price": 2340.5,
                    "time": 1_783_003_600,
                    "profit": 100,
                },
            ],
            "user-a",
            135139957,
            "HFMarketsGlobal-Live8",
            live_position_ids={9001},
        )

        self.assertEqual(len(records), 1)
        self.assertEqual(records[0]["record_type"], "deal")

    def test_same_position_id_on_different_accounts_stays_separate(self) -> None:
        first = normalize_deal_lifecycles(
            [{"ticket": 1, "position_id": 9001, "time": 1, "profit": 5}],
            "user-a",
            111,
            "Broker-A",
        )[0]
        second = normalize_deal_lifecycles(
            [{"ticket": 1, "position_id": 9001, "time": 1, "profit": 5}],
            "user-a",
            222,
            "Broker-A",
        )[0]

        self.assertNotEqual(first["trade_key"], second["trade_key"])

    def test_duplicate_merge_preserves_manual_journal_fields(self) -> None:
        merged = _merge_duplicate_group(
            [
                (
                    "position_135139957*HFMarketsGlobal-Live8*9001",
                    {
                        "user_id": "user-a",
                        "mt5_login": 135139957,
                        "broker_server": "HFMarketsGlobal-Live8",
                        "record_type": "position",
                        "status": "open",
                        "position_id": 9001,
                        "trade_key": "position_135139957*HFMarketsGlobal-Live8*9001",
                        "lifecycle_key": "position_135139957*HFMarketsGlobal-Live8*9001",
                        "profit": 0,
                        "notes": "Waited for confirmation.",
                        "custom_tags": ["discipline"],
                    },
                ),
                (
                    "deal_135139957*HFMarketsGlobal-Live8*11",
                    {
                        "user_id": "user-a",
                        "mt5_login": 135139957,
                        "broker_server": "HFMarketsGlobal-Live8",
                        "record_type": "deal",
                        "status": "closed",
                        "position_id": 9001,
                        "deal_ticket": 11,
                        "trade_key": "deal_135139957*HFMarketsGlobal-Live8*11",
                        "lifecycle_key": "position_135139957*HFMarketsGlobal-Live8*9001",
                        "profit": 100,
                        "custom_tags": ["mt5"],
                    },
                ),
            ],
            "position_135139957*HFMarketsGlobal-Live8*9001",
        )

        self.assertEqual(merged["status"], "closed")
        self.assertEqual(merged["record_type"], "position")
        self.assertEqual(merged["profit"], 100)
        self.assertEqual(merged["notes"], "Waited for confirmation.")
        self.assertEqual(set(merged["custom_tags"]), {"discipline", "mt5"})

    def test_stale_lifecycle_key_is_repaired_from_position_id(self) -> None:
        open_record = {
            "user_id": "user-a",
            "mt5_login": 135139957,
            "broker_server": "HFMarketsGlobal-Live8",
            "record_type": "position",
            "status": "open",
            "position_id": 9001,
            "trade_key": "position_135139957*HFMarketsGlobal-Live8*9001",
            "lifecycle_key": "position_135139957*HFMarketsGlobal-Live8*9001",
            "symbol": "XAUUSD",
            "trade_type": "buy",
            "lot_size": 0.10,
            "entry_price": 2330.50,
            "open_time": "2026-07-09T01:00:00+00:00",
            "sl": 2320.0,
            "tp": 2350.0,
            "profit": 0,
            "notes": "Manual review stays.",
        }
        closed_record = {
            "user_id": "user-a",
            "mt5_login": 135139957,
            "broker_server": "HFMarketsGlobal-Live8",
            "record_type": "deal",
            "status": "closed",
            "position_id": 9001,
            "deal_ticket": 11,
            "trade_key": "deal_135139957*HFMarketsGlobal-Live8*11",
            "lifecycle_key": "deal_135139957*HFMarketsGlobal-Live8*11",
            "symbol": "XAUUSD",
            "trade_type": "sell",
            "lot_size": 0.10,
            "entry_price": 2340.50,
            "close_price": 2340.50,
            "close_time": "2026-07-09T02:00:00+00:00",
            "profit": 100,
            "commission": -0.6,
            "swap": -0.1,
        }

        self.assertEqual(lifecycle_key(closed_record), lifecycle_key(open_record))

        merged = _merge_duplicate_group(
            [
                ("position_135139957*HFMarketsGlobal-Live8*9001", open_record),
                ("deal_135139957*HFMarketsGlobal-Live8*11", closed_record),
            ],
            "position_135139957*HFMarketsGlobal-Live8*9001",
        )

        self.assertEqual(merged["status"], "closed")
        self.assertEqual(merged["record_type"], "position")
        self.assertEqual(merged["entry_price"], 2330.50)
        self.assertEqual(merged["close_price"], 2340.50)
        self.assertEqual(merged["profit"], 100)
        self.assertEqual(merged["commission"], -0.6)
        self.assertEqual(merged["swap"], -0.1)
        self.assertEqual(merged["sl"], 2320.0)
        self.assertEqual(merged["tp"], 2350.0)
        self.assertEqual(merged["notes"], "Manual review stays.")


if __name__ == "__main__":
    unittest.main()
