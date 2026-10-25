"""Unit tests for MT5 ownership and canonical account identifiers."""

from __future__ import annotations

import unittest

from normalizers import account_key, normalize_account_info, normalize_position
from repair_mt5_account_ownership import _record_patch


class _Record:
    def __init__(self, **values):
        self._values = values

    def _asdict(self):
        return self._values


class MainAccountIdentityTests(unittest.TestCase):
    def test_account_normalization_adds_owner_and_document_id(self):
        account = normalize_account_info(
            _Record(login=10001, server="Broker Demo", balance=1000, equity=990),
            "user-a",
        )
        expected = account_key("Broker Demo", 10001)
        self.assertEqual(account["ownerUid"], "user-a")
        self.assertEqual(account["user_id"], "user-a")
        self.assertEqual(account["mt5AccountId"], expected)
        self.assertEqual(account["account_key"], expected)

    def test_trade_normalization_is_scoped_to_owner_and_account(self):
        position = normalize_position(
            _Record(identifier=7, ticket=7, type=0, symbol="EURUSD"),
            "user-a",
            10001,
            "Broker Demo",
        )
        self.assertEqual(position["ownerUid"], "user-a")
        self.assertEqual(position["mt5AccountId"], account_key("Broker Demo", 10001))

    def test_migration_refuses_conflicting_owner(self):
        self.assertIsNone(
            _record_patch(
                {"user_id": "user-b", "account_key": "Broker_1"},
                "user-a",
                "Broker_1",
            )
        )

    def test_migration_repairs_missing_aliases_idempotently(self):
        first = _record_patch(
            {"user_id": "user-a", "account_key": "Broker_1"},
            "user-a",
            "Broker_1",
        )
        self.assertEqual(first["ownerUid"], "user-a")
        complete = {
            "ownerUid": "user-a",
            "userId": "user-a",
            "user_id": "user-a",
            "mt5AccountId": "Broker_1",
            "mt5_account_id": "Broker_1",
            "account_key": "Broker_1",
        }
        self.assertEqual(_record_patch(complete, "user-a", "Broker_1"), {})


if __name__ == "__main__":
    unittest.main()
