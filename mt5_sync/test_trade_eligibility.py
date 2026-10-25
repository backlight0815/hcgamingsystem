from __future__ import annotations

import unittest
from datetime import datetime, timezone

from normalizers import normalize_deal, normalize_deal_lifecycles, normalize_order
from trade_eligibility import build_trade_analytics_dataset


class TradeEligibilityTests(unittest.TestCase):
    def test_cancelled_expired_rejected_and_pending_orders_are_not_trades(self) -> None:
        records = [
            self._order(1, state=2),
            self._order(2, state=6),
            self._order(3, state=5),
            normalize_order(
                self._order_data(4, state=1), "user-a", 100001, "Broker-Demo"
            ),
        ]
        dataset = build_trade_analytics_dataset(
            records, user_id="user-a", account_key="Broker-Demo_100001"
        )

        self.assertEqual(dataset.executed_closed_trades, [])
        self.assertEqual(dataset.diagnostics["cancelled_untriggered_orders"], 1)
        self.assertEqual(dataset.diagnostics["expired_untriggered_orders"], 1)
        self.assertEqual(dataset.diagnostics["rejected_untriggered_orders"], 1)
        self.assertEqual(dataset.diagnostics["active_pending_orders"], 1)

    def test_triggered_pending_order_is_replaced_by_one_position_lifecycle(self) -> None:
        deals = [
            self._deal(10, position_id=7001, entry=0, profit=0, timestamp=1000),
            self._deal(11, position_id=7001, entry=1, profit=12, timestamp=3700),
        ]
        positions = normalize_deal_lifecycles(
            deals, "user-a", 100001, "Broker-Demo"
        )
        triggered = self._order(7001, state=4, position_id=7001)
        dataset = build_trade_analytics_dataset(
            [triggered, *positions],
            user_id="user-a",
            account_key="Broker-Demo_100001",
        )

        self.assertEqual(len(dataset.executed_closed_trades), 1)
        trade = dataset.executed_closed_trades[0]
        self.assertEqual(trade["record_type"], "position")
        self.assertEqual(trade["profit"], 12)
        self.assertEqual(trade["executed_volume"], 0.4)
        self.assertEqual(
            trade["close_time"] - trade["open_time"],
            datetime.fromtimestamp(3700, tz=timezone.utc)
            - datetime.fromtimestamp(1000, tz=timezone.utc),
        )

    def test_partial_fill_keeps_filled_position_and_excludes_order_remainder(self) -> None:
        partial_order = self._order_data(
            8001,
            state=3,
            position_id=8001,
            volume_initial=1,
            volume_current=0.6,
        )
        order = normalize_order(
            partial_order, "user-a", 100001, "Broker-Demo", history=True
        )
        position = normalize_deal_lifecycles(
            [
                self._deal(
                    20,
                    position_id=8001,
                    entry=0,
                    profit=0,
                    timestamp=1000,
                    volume=0.4,
                ),
                self._deal(
                    21,
                    position_id=8001,
                    entry=1,
                    profit=5,
                    timestamp=2000,
                    volume=0.4,
                ),
            ],
            "user-a",
            100001,
            "Broker-Demo",
        )[0]
        dataset = build_trade_analytics_dataset(
            [order, position],
            user_id="user-a",
            account_key="Broker-Demo_100001",
        )

        self.assertEqual(len(dataset.executed_closed_trades), 1)
        self.assertEqual(dataset.executed_closed_trades[0]["lot_size"], 0.4)
        self.assertAlmostEqual(order["executed_volume"], 0.4)

    def test_balance_deal_is_excluded(self) -> None:
        balance = self._deal(
            30, position_id=0, entry=0, profit=100, timestamp=1000, raw_type=2
        )
        records = normalize_deal_lifecycles(
            [balance], "user-a", 100001, "Broker-Demo"
        )
        dataset = build_trade_analytics_dataset(
            records, user_id="user-a", account_key="Broker-Demo_100001"
        )
        self.assertEqual(dataset.executed_closed_trades, [])
        self.assertEqual(dataset.diagnostics["balance_operations_excluded"], 1)

    def test_legacy_deal_rows_aggregate_into_one_position_lifecycle(self) -> None:
        records = [
            normalize_deal(
                self._deal(40, position_id=9001, entry=0, profit=0, timestamp=1000),
                "user-a",
                100001,
                "Broker-Demo",
            ),
            normalize_deal(
                self._deal(41, position_id=9001, entry=1, profit=12, timestamp=2000),
                "user-a",
                100001,
                "Broker-Demo",
            ),
        ]
        dataset = build_trade_analytics_dataset(
            records, user_id="user-a", account_key="Broker-Demo_100001"
        )

        self.assertEqual(len(dataset.executed_closed_trades), 1)
        trade = dataset.executed_closed_trades[0]
        self.assertEqual(trade["record_type"], "position")
        self.assertEqual(trade["profit"], 12)
        self.assertEqual(trade["lot_size"], 0.4)

    def _order(self, ticket: int, *, state: int, position_id: int = 0):
        return normalize_order(
            self._order_data(ticket, state=state, position_id=position_id),
            "user-a",
            100001,
            "Broker-Demo",
            history=True,
        )

    @staticmethod
    def _order_data(
        ticket: int,
        *,
        state: int,
        position_id: int = 0,
        volume_initial: float = 0.1,
        volume_current: float = 0.1,
    ):
        return {
            "ticket": ticket,
            "type": 2,
            "state": state,
            "position_id": position_id,
            "symbol": "XAUUSD",
            "volume_initial": volume_initial,
            "volume_current": volume_current,
            "price_open": 2300,
            "price_current": 2300,
            "time_setup": 100,
            "time_done": 200,
        }

    @staticmethod
    def _deal(
        ticket: int,
        *,
        position_id: int,
        entry: int,
        profit: float,
        timestamp: int,
        volume: float = 0.4,
        raw_type: int = 0,
    ):
        return {
            "ticket": ticket,
            "order": ticket,
            "position_id": position_id,
            "type": raw_type,
            "entry": entry,
            "symbol": "XAUUSD" if raw_type in {0, 1} else "",
            "volume": volume,
            "price": 2300,
            "time": timestamp,
            "profit": profit,
            "commission": 0,
            "swap": 0,
        }


if __name__ == "__main__":
    unittest.main()
