# MT5 Controlled Refresh Implementation Report

Date: 2026-07-05
Branch: `mt5-controlled-refresh-20260705-1144`

## Summary

Implemented controlled Trading Journal refresh for the MT5 tab:

- The Trading Journal MT5 tab no longer uses Firestore realtime streams for sync state or records.
- Initial page load fetches MT5 accounts, one sync-state snapshot, and bounded trade/order snapshots.
- Manual Refresh fetches MT5 state and records immediately and shows `Trading Journal updated`.
- A single page timer checks only `sync_state/default` activity marker fields every `MT5_ACTIVITY_CHECK_INTERVAL_MINUTES` minutes.
- If the activity marker is unchanged, the tab does not call `setState`, does not show a spinner, does not refresh lists, and keeps filters/date range/scroll state stable.
- If the activity marker changes, the tab fetches limited open trades, recent closed trades, and pending orders.
- Customer-facing MT5 journal copy no longer exposes implementation/provider/runtime details.

## Activity Marker Fields

Flutter now reads:

- `last_trade_activity_at`
- `last_trade_activity_id`
- `last_trade_activity_type`
- `last_trade_change_version`
- `updated_at`

Python now writes the activity marker only when records that represent meaningful lifecycle activity are actually written, or when missing live records are reconciled.

Floating-only updates can still be written when thresholds allow, but they do not advance the Trading Journal activity marker.

## Record Hash

Trade/order/deal record hashes are now based on the requested meaningful fields:

- `symbol`
- `order_ticket`
- `deal_ticket`
- `position_id`
- `trade_type`
- `status`
- `lot_size`
- `entry_price`
- `close_price`
- `sl`
- `tp`
- `open_time`
- `close_time`
- `profit`
- `commission`
- `swap`
- `magic_number`
- `comment`

The shared hash helper still supports account snapshots so account balance/equity changes continue to be detected.

## Verification

Passed:

- `dart format`
- `python -m py_compile mt5_sync\normalizers.py mt5_sync\realtime_watcher.py mt5_sync\db_adapter.py mt5_sync\run_sync_service.py`
- `flutter analyze`
- `flutter test`
- `flutter build windows --debug`
- `flutter build apk --debug`

## Local Runtime Note

The live one-cycle sync retry could not attach to the currently open MT5 terminal. MT5 logs show the terminal disconnected and then recorded:

`account 'PUPrime-Demo' - '700122288' has been deleted`

The terminal process is still open, but its window title no longer includes the account number. The account must be logged back into MT5 before the local sync script can read trades again.

