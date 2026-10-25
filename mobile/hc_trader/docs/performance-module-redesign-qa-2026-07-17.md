# Performances Module Redesign QA — 17 July 2026

## A. Existing Problems Found

- The previous page was a long, flat analytics feed with weak section hierarchy and limited responsive navigation.
- Data-source selection covered the primary MT5 account only; it did not provide safe aggregation across selected or all MT5 accounts.
- The report service relied on callers for duplicate protection and accepted records whose type or lifecycle was not an eligible closed trade.
- Funding activity was visually close to trading performance and needed clearer separation.
- Previous-period comparisons, consistency, current drawdown, plan/risk compliance, deterministic insights, calendar drill-down, Prop Firm progress, signal performance, and permission-gated rankings were not presented as one coherent workspace.
- Mobile filtering and section navigation were not designed as a unified touch-first experience.
- New performance copy needed complete Simplified Chinese coverage; the legacy localization table also contains older mojibake values outside this module.
- The journal schema has no numeric stop-loss/target risk fields, so it cannot support an honest average risk-to-reward calculation.

## B. Implementation Summary

- Rebuilt Performances as a responsive section workspace: Overview, Growth, Calendar, Breakdown, Risk, Consistency, Psychology, Prop Firm, Signals, and Rankings (permission-dependent).
- Added a mobile filter bottom sheet, desktop toolbar, quick date presets, account/source selection, filter reset, timezone display, and session persistence for the existing date filter.
- Added score hierarchy, primary/secondary metrics, previous-period trend comparisons, accessible outcome summaries, and deterministic insights with calculation explanations.
- Added interactive growth/P&L/drawdown charts without inventing historical equity, funding summaries, a calendar/list day drill-down, sortable breakdowns, a realized drawdown timeline, compliance metrics, and neutral psychology language.
- Connected Prop Firm, signal, and ranking sections to existing repository/services and permissions. Rankings do not show email, Firebase IDs, MT5 credentials, or balances.
- Added all-account MT5 stream aggregation with listener disposal and duplicate suppression.
- Preserved the existing architecture, Material theme, Firestore document paths, permissions, and PDF report service.

## C. Files Changed

Modified:

- `lib/core/localization/app_localizations.dart`
- `lib/features/statistics/statistics_screen.dart`
- `lib/models/trading_report.dart`
- `lib/repositories/trading_repository.dart`
- `lib/services/trading_report_service.dart`
- `lib/shared/trading_data_builder.dart`

Created:

- `lib/features/performance/performance_dashboard_sections.dart`
- `lib/features/performance/performance_external_sections.dart`
- `lib/features/performance/performance_filter_bar.dart`
- `lib/features/statistics/statistics_view_model.dart`
- `test/performance_module_test.dart`
- `docs/performance-module-redesign-qa-2026-07-17.md`

No files were removed.

## D. Calculation Validation

- Eligible performance records are `type == trade` and `status == closed`, after owner, source, account, and date scoping.
- Duplicate records use source/account/trade ID identity. Records without IDs use a conservative exact fingerprint of timestamps, pair, direction, size, prices, and result.
- Deposits and withdrawals remain in `TradingCapitalReport`; they do not enter net trading P/L, gross profit/loss, expectancy, or win rate.
- Break-even records are reported but excluded from the win-rate denominator. Expectancy includes their zero outcome through total-trade probabilities.
- Profit factor is gross profit divided by absolute gross loss and remains unavailable/infinite-safe when there is no gross loss.
- Expectancy is `(win probability × average win) − (loss probability × average loss)`.
- Maximum drawdown is the largest peak-to-trough decline in the realized balance curve. Current MT5 drawdown infers the period opening balance from live balance minus period P/L.
- Recovery factor is net trading P/L divided by maximum realized drawdown and safely handles zero drawdown.
- Stored `profitLoss` is the journal's net trade result; the current mobile journal model has no separate commission or swap fields.
- Average risk-to-reward is deliberately shown as insufficient data. Payoff ratio is separately labeled and is not misrepresented as planned risk-to-reward.

## E. Firebase Changes

- Added repository composition methods for multiple existing MT5 account journal/capital streams.
- No Firestore collection or field was renamed.
- No Firestore index, security-rule, Storage-rule, Cloud Function, or deployed backend change was required.
- Existing owner-scoped queries and role/permission gates remain in force.

## F. Testing Results

Automated command results:

- `flutter pub get`: passed; dependencies resolved. Pub reported 48 newer versions that are incompatible with the current constraints.
- `dart format .`: passed; 235 files checked, 0 changed.
- `flutter analyze`: passed; no issues found.
- `flutter test -r compact`: passed; all 369 tests passed.

Additional platform checks:

- Android debug APK: compiled successfully.
- Web debug bundle: compiled successfully. Flutter reported third-party WebAssembly compatibility warnings for `flutter_secure_storage_web` and `image`; the normal JavaScript web build succeeded.
- Windows x64 debug executable: compiled successfully.
- Responsive widget coverage: 360×800 phone, 800×900 and 1024×900 tablet/desktop widths, English, Simplified Chinese, light theme, and dark theme.
- Interactive browser smoke test: not executed because no browser backend was attached to this Codex session.
- Physical Android, foldable, and hands-on Windows interaction: not executed.

## G. Remaining Risks

- Historical MT5 equity snapshots are not present in the current model. The UI states this and does not fabricate an equity curve.
- Numeric stop-loss, take-profit, commission, swap, asset-class, setup, and signal-provider dimensions are not consistently present in the current mobile journal schema; unsupported metrics are omitted or shown as insufficient data.
- All-account monetary aggregation assumes compatible account currencies. Cross-currency normalization would require an authoritative conversion source and is not fabricated client-side.
- Multi-trader ranking uses the existing permission-protected summary endpoint. A full admin trader drill-down needs a dedicated secure backend endpoint before private trade-level data can be exposed.
- Large-history calculations are linear and daily chart data is aggregated, but thousands-of-trades device profiling and slow-network/Firestore-emulator fault injection were not executed in this session.
- Legacy localization content outside the redesigned module still contains older encoding artifacts.
