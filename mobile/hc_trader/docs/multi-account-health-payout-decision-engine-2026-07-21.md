# Multi-Account Health, Loss-Limit and Payout Decision Engine

Implementation date: 2026-07-21

## 1. Summary of enhancements

The existing local-first Multi-Account Prop Firm Strategy remains the primary planner and keeps its original workflow and navigation order. The health enhancement remains the sixth workspace, followed by the additive Sequence workspace. Users can still optionally complete a quick or detailed current-situation review, update snapshots, track per-account loss limits and payout dates, and receive one typed, explainable portfolio recommendation.

The enhancement adds:

- versioned current-situation data with schema-1-to-schema-2 migration;
- editable 10% maximum-loss and 5% daily-loss planning defaults;
- static, balance, equity, trailing, end-of-day trailing, and custom drawdown inputs;
- realised-only, realised-plus-unrealised, balance, equity, and custom daily-loss inputs;
- account health, loss-room, payout eligibility, payout protection, and data-completeness assessments;
- portfolio stop, account stop, reduced-risk, switch-account, payout-protection, and careful-requirement decisions;
- account-specific automatic or manual reset handling with reset time, named time zone, and UTC offset;
- payout dates, countdowns, protection buffers, request status, and request history;
- reason-gated status/recommendation overrides and local audit records;
- responsive health cards, risk gauges, comparison table, payout calendar, mobile swipe cards, sticky mobile recommendation, and mobile update sheet;
- English and Simplified Chinese text through the existing localization architecture.

No historical trade import is required to establish the initial situation.

## 2. Files created

- `lib/core/localization/account_health_translations.dart`
- `lib/features/multi_account_prop_firm/domain/account_health_decision_service.dart`
- `lib/features/multi_account_prop_firm/presentation/account_health_ui.dart`
- `test/account_health_decision_service_test.dart`
- `docs/multi-account-health-payout-decision-engine-2026-07-21.md`

## 3. Files modified

- `lib/core/localization/app_localizations.dart`
- `lib/features/multi_account_prop_firm/domain/prop_firm_models.dart`
- `lib/features/multi_account_prop_firm/domain/prop_firm_calculation_service.dart`
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart`
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_dialogs.dart`
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_screen.dart`
- `test/multi_account_prop_firm_test.dart`

The existing public/authenticated entry points, repository, Toolkit tile, theme, and owner-scoped local storage remain in use.

## 4. Updated data models

`PropFirmPortfolio` is now schema version 2 and contains `currentSituationAssessed`. Schema version 1 remains readable and migrates to version 2 with the assessment incomplete. Migration does not block or replace the original planner; the Health workspace offers the review when the user wants to add current values.

Every `PropFirmAccount` now owns an immutable `AccountCurrentSituation` containing:

- program, creation, and activation details;
- firm reset hour/minute, named time zone, UTC offset, and automatic/manual reset method;
- editable percentage and amount limits;
- daily calculation method, daily starting balance/equity, trailing reference, lowest value, realised/unrealised P/L, recorded loss, and planned open risk;
- latest trading day, plan adherence, emotional flag, and user-selected status;
- eligible profit, payout dates, manual/automatic eligibility, consistency state, protection flag, safety buffer, notes, request status, and history;
- per-account health thresholds and update timestamp.

New computed types are `LossLimitSnapshot`, `PayoutEligibilityAssessment`, `AccountHealthAssessment`, and `PortfolioRecommendation`. Recommendation and confidence values are strongly typed enums. Confidence describes data completeness only; it is not an AI prediction.

## 5. Loss-limit formulas

Overall maximum loss:

```text
maximum loss amount = explicit amount, existing configured amount,
                      or starting reference × editable percentage / 100
maximum-loss floor = applicable drawdown reference - maximum loss amount
remaining maximum room = current balance/equity - maximum-loss floor - planned open risk
overall usage % = (maximum loss amount - remaining maximum room)
                  / maximum loss amount × 100
```

Daily loss:

```text
daily loss amount = explicit amount, existing configured amount,
                    or selected daily base × editable percentage / 100
realised/unrealised method used = negative realised P/L
                                  + negative unrealised P/L
daily planning usage = applicable actual daily loss + manually entered open risk
remaining daily room = daily loss amount - daily planning usage
daily usage % = daily planning usage / daily loss amount × 100
```

Balance and equity methods derive loss from the configured daily starting value and current balance/equity. Static drawdown uses the starting reference; trailing methods use the entered trailing reference and clearly mark a fallback as estimated. Outputs are floored at zero, invalid percentages fall back conservatively, division by zero is avoided, and missing equity/starting values are disclosed.

Planned open risk is included only in the application's internal safety room. It is not described as a loss already recorded by the prop firm.

## 6. Health-classification rules

Priority is safety first:

1. archived or breached;
2. daily limit/safety threshold, maximum trade count, or manual daily lock;
3. manual pause, portfolio lock, or mandatory cooldown;
4. near daily limit when daily usage is at least the configured high-risk threshold (80% by default) or normal risk no longer fits;
5. near maximum limit on the equivalent overall condition;
6. payout protection, waiting date, or payout eligibility;
7. recovery;
8. high risk from 60% to below 80% or multiple consecutive losses;
9. caution from 30% to below 60%, one recent loss, an emotional warning, or plan non-compliance;
10. payout progress or healthy.

The default thresholds 30/60/80/100 are editable per account. Every status includes numerical reasons, a recommended action, and missing-data notices. The computed status and manually selected status are both shown when they differ.

## 7. Payout-date logic

An account is payout eligible only when all applicable conditions pass:

- eligible profit reaches the minimum payout;
- minimum trading days are complete;
- an enabled consistency rule is confirmed;
- the earliest/next request date has arrived;
- manual eligibility is confirmed when automatic calculation is disabled.

A date alone never produces eligibility. Future dates produce a countdown only after the other core requirements pass. Incomplete profit, trading days, consistency, missing date, or missing manual confirmation are shown as separate requirements.

## 8. Payout-protection logic

Protection is applied when the user enables it, an existing account is in a payout-ready/protection/requested state, or a future-date account has completed requirements and one normal loss could remove payout eligibility. Protected and fully waiting accounts are excluded from account selection. Accounts that still require trading days receive a careful-requirements recommendation and reduced suggested risk; the interface explicitly prohibits artificial trades merely to create trading days.

The personal safety buffer produces:

```text
protection threshold = minimum payout + personal safety buffer
profit above threshold = max(0, eligible profit - protection threshold)
```

The calendar explains that the buffer is personal, not a firm rule, and an expected processing date is not a payout guarantee.

## 9. Continue and stop decision rules

The portfolio engine evaluates breach prevention, daily limits, portfolio stops, emotional risk, payout protection, overall drawdown, duplicate exposure, cooldown, trading days, recovery, and rotation—in that order, with profit opportunity last.

Portfolio stop conditions include the existing portfolio daily loss/trade/loss-streak rules, a manual portfolio lock, emotional risk on any account, and the same losing instrument/direction copied across multiple accounts. Account stop conditions include daily/overall exhaustion, normal risk not fitting, manual pause/lock, mandatory cooldown, trade-count exhaustion, breach, archive, and protected waiting-for-payout state.

Continuation is worded as permission under configured rules, never as a safe or approved trade. Suggested risk is capped by the configured normal risk, remaining daily and overall room, and a reserve. It is reduced at elevated usage or after portfolio loss and can never be increased automatically.

## 10. Account-switching rules

Switching is recommended only when the current account is restricted and another account remains eligible under portfolio rules. A near-limit, locked, cooling, or protected account can therefore yield a healthy replacement.

Switching is blocked when the portfolio stop is active, emotional risk is reported, the same losing idea has damaged multiple accounts, maximum portfolio trade/loss conditions are reached, or no eligible account remains. The interface explains that another account does not reset the trader's emotion or the portfolio's daily result.

## 11. Screens and widgets added

- optional **Current Account Situation Assessment** launched from Health without intercepting the original planner;
- quick and detailed situation editor;
- **Health** remains appended after the five original destinations, and **Sequence** is appended after Health, so the original workflow and Health entry point are unchanged;
- portfolio health summary and typed recommendation/stop-condition panel;
- account health cards with daily and overall gauges, text, numbers, reset indicator, last update, and manual/computed comparison;
- filter controls and six requested sort modes;
- full account comparison table;
- payout calendar with countdown, eligibility requirements, buffer, planned/processing dates, request status, and history count;
- missing-data warnings and device-offset warning;
- manual refresh, per-account reset, status override, and snapshot update actions;
- five built-in educational account-health scenarios;
- mobile swipeable cards, sticky recommendation, and bottom-sheet updater.

Desktop uses the existing NavigationRail and horizontal comparison table. Mobile uses the existing bottom navigation with compact controls. Risk meaning never depends on color alone.

## 12. Localization keys added

`account_health_translations.dart` adds Simplified Chinese coverage for assessment fields, calculation methods, statuses, filters/sorts, gauges, payout calendar/history, override/reset actions, educational scenarios, recommendation types, missing-data warnings, account-health reasons, and portfolio stop/continue reasons. English remains the canonical key and no paid translation service is used.

## 13. Tests added

`account_health_decision_service_test.dart` covers:

- editable 10%/5% defaults and custom amount precedence;
- static, balance, equity, and trailing rules;
- zero, negative, missing, and estimated inputs;
- remaining daily/overall room and planned risk;
- healthy, caution, high-risk, near-maximum, locked, waiting, eligible, and payout-protection states;
- all payout requirements and future-date transition;
- continue, reduced-risk, account stop, switch, and portfolio stop decisions;
- duplicate losing ideas and emotional stops;
- schema migration.

`multi_account_prop_firm_test.dart` additionally covers the non-blocking assessment and immediate/partial-review save path, owner-scoped persistence, reason-gated override audit, manual and time-zone reset, payout preservation, Chinese catalog coverage, the three-account near-limit/healthy/payout-protected workflow, desktop comparison/calendar rendering, and a 390 px mobile swipe/sticky/updater flow with overflow detection.

## 14. Test results

- `flutter analyze`: passed, no issues.
- `flutter test`: all 454 tests passed.
- `flutter build web --debug`: passed; output `build/web`.
- `flutter build windows --debug`: passed; output `build/windows/x64/runner/Debug/hc_trader.exe`.
- `flutter build apk --debug`: passed; output `build/app/outputs/flutter-apk/app-debug.apk`.

The standard web build succeeds. Flutter's optional Wasm dry run continues to report pre-existing third-party `flutter_secure_storage_web` and `image` incompatibility/lint notices. Android continues to report the existing future warning for minimum SDK 23; the APK succeeds.

## 15. Manual QA results

Automated responsive interaction QA completed successfully for:

- immediate transition from portfolio creation into the original desktop NavigationRail workspace;
- optional Chinese situation review and save without reopening every account;
- health navigation, summary, comparison table, and payout calendar;
- near-limit Account A, healthy Account B, and protected Account C selecting Account B;
- 390 px phone layout, PageView cards, sticky recommendation, and mobile updater without overflow;
- reset, override, audit, persistence, payout preservation, and locale lookup.

An in-app browser click-through was attempted against a local Flutter web server, but this session exposed no browser instance. No unrelated browser backend was substituted. Platform compilation and the widget interaction suite provide the recorded QA evidence for this run.

## 16. Known limitations

- All values are manual/local; there is no broker or prop-firm synchronization.
- Named time zones are labels in this local planner. Reset arithmetic uses the explicitly entered UTC offset, so daylight-saving changes must be updated by the user. A device-offset mismatch is shown.
- Currency values are not converted; accounts with different currencies must be reviewed separately.
- Trailing drawdown is only as accurate as the manually entered trailing reference.
- Data completeness can distinguish a missing value from a usable estimate for nullable situation fields; legacy non-null numeric fields such as zero minimum trading days may also legitimately mean “no requirement.”
- Expected processing dates and payout eligibility are planning outputs, not firm confirmations or guarantees.
- Local portfolios do not synchronize across devices.

## 17. Editable default confirmation

Confirmed: maximum loss defaults to 10% and daily loss defaults to 5%. Both percentages and explicit currency amounts are editable per account. The UI repeatedly instructs users to verify each firm's current rules.

## 18. Separate payout-date confirmation

Confirmed: every account can store its own earliest request, next request, planned request, and expected processing dates plus payout time zone, status, notes, buffer, and history.

## 19. Integration boundary confirmation

Confirmed: no MT4, MT5, broker, prop-firm login, API credential, signal, trade execution, or automated trading integration was added.

## 20. Explainability confirmation

Confirmed: recommendations are deterministic, strongly typed, and rule-based. Every result contains reasons, stop conditions, a data-completeness confidence value, restricted account IDs, an optional recommended account, and a maximum suggested risk that never exceeds configured normal risk.
