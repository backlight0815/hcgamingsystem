# Multiple Account Strategy: per-account daily limits

## Root cause

The legacy `PortfolioRiskPolicy.maximumTradesPerDay` was evaluated against all
results in the portfolio. When the aggregate reached the limit, the stop service
locked the portfolio and every account, even if another account had no trades.
Some eligibility paths also trusted the cached `tradesToday` account value,
which could become stale across devices.

## New behaviour

Each account now owns:

- `daily_trade_limit_enabled` (`bool`, default/fallback enabled)
- `daily_trade_limit` (`int`, 1 through 100)
- `daily_trade_count` and `daily_trade_count_key` as derived caches only
- legacy `max_trades_per_day`, retained for backward compatibility

`AccountDailyTradeLimitService` is the source of truth. It derives the current
count from unique recorded result IDs for the account and configured trading
day. TP, SL, break-even, partial TP/SL, and manual close count. Cancelled, not
triggered, missed, and manual-adjustment records do not count.

Reaching a limit excludes only that account from recommendations, sequence
generation, account selection, and Start. Remaining accounts keep their normal
health-based ranking. When all accounts reach their limits, Start is disabled
and the UI shows the next configured reset.

## Migration and fallback

Existing account documents without the new fields inherit
`risk_policy.maximum_trades_per_day` and `stop_after_maximum_trades`. If neither
value is usable, the account default is 3. The account-level fields are written
the next time the workspace/account is saved. The old portfolio fields remain
readable as migration defaults but no longer stop the live portfolio.

Workspaces previously stopped with `maximumTrades` are normalized to `ready`,
the legacy stop reason is cleared, and `portfolioLocked` account statuses are
released. Other stop reasons and breached, archived, payout, loss, emotional,
and manual protections are not changed.

## Reset and synchronization

All counts use `TradingDayService` with the configured IANA timezone, reset
hour/minute, and fixed-offset fallback. `nextResetAt` uses timezone-aware
calendar construction, including daylight-saving transitions. Firestore is the
authoritative signed-in data source; no local cache is used for cloud starts.

The Start command first acquires `pending_start_claim` in a Firestore
transaction. The transaction re-reads the workspace and account, rejects an
active/competing session, derives the account count from recorded results, and
rejects a reached account. Request IDs make a retry idempotent. Claims expire
after five minutes and are released after the workspace save.

No composite Firestore index is required: the production workspace already
stores the bounded aggregate result records used by the transaction. If result
history is later moved exclusively to a collection query, add an index for
owner/portfolio/account/trading-day/status before removing the aggregate.

## UI

- Account create/edit: Daily Trading Controls, enable switch, integer limit,
  helper copy, and next reset information.
- Account cards/details: icon, current/limit, remaining/reached/disabled text,
  progress indicator, and reset message.
- Overview: available today, limits reached, risk-blocked, and payout-protected
  totals; recommendations continue when another account is eligible.
- English and Simplified Chinese strings use the existing localization catalog.

## Verification

- `flutter analyze`: passed.
- Per-account domain tests: passed (7 tests).
- Simplified workflow, widget, sequence, and regression suites: passed (70
  focused tests including the per-account suite).
- Simultaneous two-device reservation regression: passed; exactly one start.
- Firestore rule emulator suite: passed, including valid distinct limits and
  rejected zero, decimal, over-100, and invalid-enabled values.

## Main modified files

- `domain/account_daily_trade_limit_service.dart`
- `domain/trading_day_service.dart`
- `domain/prop_firm_models.dart`
- `domain/account_sequence_service.dart`
- `domain/account_rotation_service.dart`
- `domain/portfolio_dashboard_service.dart`
- `domain/prop_firm_calculation_service.dart`
- `domain/session_stop_condition_service.dart`
- `data/firestore_prop_firm_portfolio_data_source.dart`
- `data/prop_firm_portfolio_repository.dart`
- `presentation/prop_firm_planner_controller.dart`
- `presentation/simplified_strategy_ui.dart`
- `presentation/prop_firm_planner_dialogs.dart`
- localization catalogs, Firestore rules, and focused tests

## Limitation

The transaction validates the aggregate result history stored on the workspace,
matching the current architecture. Strong protection against a malicious custom
client would additionally require trusted server-side session/result creation
(for example, a callable Cloud Function or App Check-enforced backend).
