# Multiple Account Strategy simplification report

Date: 2026-07-22

## 1. Previous problems

The production module exposed seven planning areas, repeated risk information,
and required the trader to understand internal sequence-planning concepts. The
same account condition could be presented differently by separate services and
screens. Account type, fixed-versus-percentage limits, result projections,
daily reset timezone, and individual session results were not represented
consistently. Cloud permission failures were visible to users, while the result
subcollection did not yet have an owner-scoped production rule.

The former UI remains compiled only as a backwards-compatible implementation
detail; it is no longer reachable through the public planner route.

## 2. New simplified workflow

The public module now contains exactly three areas:

1. Overview
2. Account details
3. Tutorial & FAQ

The trader configures funded or evaluation accounts once, selects or accepts a
recommended starting account, presses **Start Trading**, records TP, SL, or BE
through a compact review flow, and receives the next eligible recommendation.
The result review shows entered and normalized amounts, balance/equity before
and after, and projected health. The confirmed result updates account values,
risk capacity, target/payout progress, status, history, session totals, and the
next sequence in one controller operation and one Firestore batch.

The account cards expose only details, edit, pause/resume, and starting-account
selection. Mobile uses a sticky primary action; tablet, web, and Windows use
responsive grids and an adaptive navigation rail.

## 3. Files created

- `lib/features/multi_account_prop_firm/domain/account_health_score_service.dart`
- `lib/features/multi_account_prop_firm/domain/account_validation_service.dart`
- `lib/features/multi_account_prop_firm/domain/trading_day_service.dart`
- `lib/features/multi_account_prop_firm/presentation/simplified_strategy_ui.dart`
- `test/simplified_multiple_account_strategy_test.dart`
- `tools/deploy_multiple_account_results_rules_patch.js`
- `docs/multiple-account-strategy-simplification-2026-07-22.md`

## 4. Files modified

Core implementation:

- `lib/features/multi_account_prop_firm/domain/prop_firm_models.dart`
- `lib/features/multi_account_prop_firm/domain/account_health_decision_service.dart`
- `lib/features/multi_account_prop_firm/domain/account_sequence_service.dart`
- `lib/features/multi_account_prop_firm/domain/prop_firm_calculation_service.dart`
- `lib/features/multi_account_prop_firm/data/firestore_prop_firm_portfolio_data_source.dart`
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart`
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_screen.dart`
- `lib/core/localization/multi_account_prop_firm_translations.dart`
- `pubspec.yaml`
- `pubspec.lock`
- `firestore.rules`

Verification:

- `functions/test/multiple_account_strategy_rules.test.js`
- `test/account_health_decision_service_test.dart`
- `test/account_sequence_widget_test.dart`
- `test/multi_account_prop_firm_test.dart`
- `test/multiple_account_strategy_regression_test.dart`

## 5. Files removed

None. Existing production data paths and legacy model fields were retained to
avoid destructive migration. Obsolete public screens were disconnected rather
than physically deleted until migrated production data has had a longer
observation period.

## 6. Firestore collections used

The implementation adapts the existing deployed architecture instead of
creating duplicate collections:

```text
users/{uid}/multiple_account_strategy/workspace
users/{uid}/multiple_account_strategy/workspace/accounts/{accountId}
users/{uid}/multiple_account_strategy/workspace/configuration/sequence
users/{uid}/multiple_account_strategy/workspace/sessions/{sessionId}
users/{uid}/multiple_account_strategy/workspace/sessions/{sessionId}/account_snapshots/{accountId}
users/{uid}/multiple_account_strategy/workspace/sessions/{sessionId}/events/{eventId}
users/{uid}/multiple_account_strategy/workspace/sessions/{sessionId}/results/{resultId}
users/{uid}/multiple_account_strategy/workspace/history/{sessionId}
```

Workspace, accounts, sequence, session summary, result documents, and audit
events are committed in one Firestore batch. Result IDs are checked before
submission and again by the controller. The UI reports saving, pending,
offline, failed, and synchronized states and provides retry behavior.

Production Firestore result rules were deployed as ruleset:
`projects/hcgaming-31d5d/rulesets/5c1092e4-eb15-47e3-9866-fb62bd1611a5`.
Only the nested result rule was added to the previously released source.

## 7. Migration performed

The portfolio schema is now version 4. Schema versions 1 through 3 remain
readable. Legacy names for account label/number/capital/balance/equity and limit
values are mapped into the v4 model, with safe defaults for account type and
limit type. Existing account IDs are reused, so migration cannot duplicate
accounts. Firestore reads schedule a merge-based v4 write only when an older
schema or an aggregate-only account layout is detected. Migration failures are
logged without deleting the readable cached portfolio.

## 8. Health-score formula

One `AccountHealthScoreService` supplies Overview, Account Details, preview,
history metadata, and sequencing inputs:

- Remaining maximum-loss capacity: 35 points
- Remaining daily-loss capacity: 35 points
- Equity/drawdown condition: 15 points
- Evaluation-target or funded-payout progress: 10 points
- Data integrity and availability: 5 points
- Recent consecutive-loss penalty: up to 10 points

The score is clamped to 0–100. Bands are Healthy 80–100, Moderate 60–79,
Warning 40–59, Critical 1–39, and Unavailable 0. Paused, archived, breached,
daily-locked, payout-protected, cooling, incomplete, and evaluation-passed
accounts are unavailable. Risk capacities come from the centralized
`AccountLossLimitService`; percentage limits use configured account capital.

## 9. Account-sequencing rules

Hard exclusions cover disabled/paused, archived, breached, daily-locked,
portfolio-locked, cooldown, incomplete critical data, completed evaluation,
manual payout protection, insufficient loss room, trade-count limits, and
conflicting open portfolio exposure.

Eligible accounts are ranked deterministically by health, remaining daily room,
remaining maximum-loss room, recent use, drawdown/risk condition, and stable
account order. An SL locks or penalizes the account according to the configured
policy and avoids immediate revenge-style reuse. TP and BE normally rotate and
recalculate. Ties use configured daily room, maximum room, least-recent use,
then stable order. Manual choice is allowed only among eligible accounts.

## 10. Daily-reset behavior

`TradingDayService` uses an IANA timezone plus configured reset hour and minute.
It handles daylight-saving transitions through the timezone database, stores
timestamps as Firestore timestamps/UTC instants, and displays session times in
the configured prop-firm timezone. Daily statistics reset only when the
calculated trading-day key changes. Historical result documents remain intact.
Legacy fixed UTC offsets remain supported.

## 11. State-management approach

The existing `ChangeNotifier` controller and repository pattern is retained.
The controller owns validated portfolio and sequence state. The repository
streams Firestore workspace/account/configuration snapshots into the controller
and mirrors the last validated state locally for offline recovery. Widgets do
not independently calculate risk or health. A result is not presented as saved
until the repository operation returns; failures remain visible and retryable.

## 12. Tests added and updated

New coverage verifies:

- fixed and percentage loss limits, including capital-based percentages;
- funded/evaluation conditional validation and editor fields;
- TP, SL, and BE normalization, NaN rejection, and result projections;
- health bands, evaluation completion, and payout eligibility;
- IANA/DST reset boundaries;
- SL rotation, TP update, duplicate rejection, session completion, and reload;
- exactly three responsive destinations;
- payout-protected exclusion;
- owner-only result rules, ID/session integrity, numeric fields, and immutable
  deletion.

Existing calculation, controller, sequencing, localization, responsive, and
regression tests were updated for schema v4 and the simplified public UI.

## 13. Test results

- `flutter test`: **523 passed**
- Focused simplified and multiple-account regression tests after the sync-state
  recovery fix: **33 passed**
- Firestore emulator rule suite for this module: **passed**
- Production rule patch second run: **no change**, confirming idempotency

## 14. Flutter analyze and build results

- `flutter pub get`: passed
- `flutter analyze`: passed with **0 issues**
- Android debug APK: `build/app/outputs/flutter-apk/app-debug.apk`
- Web release: `build/web`
- Windows release: `build/windows/x64/runner/Release/hc_trader.exe`

## 15. Remaining limitations

- Results are entered manually; there is no direct MT5 or broker connection.
- Screenshot attachment is not included in the compact result UI yet. Notes and
  trade references are supported.
- The web JavaScript release passes, but existing third-party secure-storage
  and image packages are not yet WebAssembly-compatible.
- Android still targets minimum SDK 23; Flutter reports that SDK 24 will become
  the minimum supported level in a future release.
- Legacy planner widgets remain in source, unreachable, for one migration
  observation cycle.

## 16. Recommended future improvements

1. Add a broker/MT5 adapter behind the existing domain interfaces.
2. Add secure screenshot upload and retention controls.
3. Raise Android minimum SDK to 24 after confirming the supported device plan.
4. Replace WebAssembly-incompatible dependencies when stable upgrades are
   available.
5. Remove unreachable legacy planner widgets after production schema-v4
   adoption has been audited.

## 17. Save-status incident resolution

The production workspace behind the reported 10:43 save banner was inspected.
The workspace, configuration, active session, and three immutable account
snapshots were present in Firestore with the expected active-account state, so
the operation did not lose the trader's data. The red banner was a stale
listener/save-state presentation after the remote commit.

The controller now distinguishes a newer unsynchronized local draft from an
already-confirmed remote version. An older live snapshot cannot overwrite a
newer local draft, live-listener failures trigger a read-and-resubscribe
recovery, and the status bar presents confirmed data as "saved; reconnecting"
instead of "failed to save." Genuine unsaved changes retain the red error,
localized detail, and Retry action. Session snapshot persistence also skips
pre-existing immutable snapshot documents during orphan/inconsistent-session
recovery.

## 18. Cooling countdown and recovery scoring

Cooling accounts now keep their calculated financial health score instead of
being forced to zero. Cooling remains a hard execution restriction through
`unavailableReasonKey`, so the account cannot be started until the configured
end time. Once the countdown expires, time-sensitive controller state is
refreshed and the account becomes eligible without a Firestore write.

The overview cards and account-details header display a live countdown with
hours, minutes, seconds, and the exact local end time. A regression scenario
matching the reported values verifies that an account scoring about 62.4 after
yesterday's loss rises to about 72.8 after a USD 208.98 recovery while still
cooling, then rises again when the cooldown availability restriction ends. A
smaller positive recovery such as USD 95 likewise improves the balance,
equity, and remaining maximum-loss components proportionally.

## 19. Professional visual FAQ redesign

The tutorial and FAQ area is now a visual education dashboard rather than a
text-only accordion list. It includes:

- a responsive professional hero and visual learning tags;
- a donut chart showing the health-score weights: maximum-loss room 35,
  daily-loss room 35, equity/drawdown 15, target/payout 10, and data/
  availability 5;
- a segmented score-band scale for Critical, Warning, Moderate, Healthy, and
  Unavailable;
- a worked 62 to 73 to 78 recovery/cooldown journey;
- a four-step configure, start, record, and rotate workflow;
- icon-led FAQ cards with embedded diagrams for health scoring, recovery,
  cooldown eligibility, result processing, and unavailable account states.

All new visual copy has Simplified Chinese coverage. Widget verification covers
390-pixel mobile and 1280-pixel Windows layouts with no rendering exceptions.
