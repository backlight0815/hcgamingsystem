# Multi-Account Prop Firm Strategy & Payout Planner

Implementation date: 2026-07-21

> Updated by the current-account health enhancement and the additive Dynamic Account Trading Sequence Planner. See `multi-account-health-payout-decision-engine-2026-07-21.md` and `dynamic-account-trading-sequence-planner-2026-07-21.md` for their reports and QA evidence.

## Outcome

The Flutter application now includes a public, local-first **Funded Account Rotation & Payout Planner**. It treats manually entered prop-firm accounts as one discipline-planning portfolio while keeping each account's legal balance, drawdown, payout, and trading rules independent.

The feature does not connect to MT4, MT5, a broker, a prop firm, or a trade-execution service. It does not collect passwords or API keys, provide signals, approve trades, or promise profit, recovery, challenge completion, or payout.

## Existing architecture audit

| Concern | Existing application pattern | Reused by this module |
| --- | --- | --- |
| State | Stateful widgets and focused `ChangeNotifier` controllers | `PropFirmPlannerController` is a feature-scoped `ChangeNotifier` |
| Navigation | `MaterialPageRoute` and an `IndexedStack` shell | Public and Toolkit entry points use `MaterialPageRoute`; module uses adaptive internal navigation |
| Dependency injection | Constructor injection | Storage, owner ID, repository, clock, and domain services are constructor-injected |
| Theme | Central Material 3 light/dark `ThemeData` | Cards, controls, color scheme, typography, and accessibility behavior use the current theme |
| Localization | `AppLocalizations.text(englishKey)` with English and Simplified Chinese | All planner UI, validation, reasons, education, and semantics use the same lookup |
| Firebase | Firebase Auth gate plus repository-based private modules | Planner deliberately makes no Firebase call; public guest use remains local |
| Repositories | Feature repositories with typed models | A versioned local portfolio repository encapsulates persistence and import/export |
| Local storage | `LocalAppStorage` over `SharedPreferences` | Guest and signed-in portfolios use owner-scoped local keys |
| Responsive UI | `LayoutBuilder`, constrained content, grids, cards | Mobile uses bottom navigation; tablet/desktop use NavigationRail and multi-column content |
| Access | Existing application previously showed authentication for signed-out users | Auth screen now exposes one public planner route; all private app features remain protected |

No backend, Firebase schema, Firestore rule, or SQL migration was required.

## Files created

- `lib/core/localization/multi_account_prop_firm_translations.dart`
- `lib/features/multi_account_prop_firm/data/prop_firm_portfolio_repository.dart`
- `lib/features/multi_account_prop_firm/domain/prop_firm_models.dart`
- `lib/features/multi_account_prop_firm/domain/prop_firm_calculation_service.dart`
- `lib/features/multi_account_prop_firm/domain/account_rotation_service.dart`
- `lib/features/multi_account_prop_firm/domain/scenario_simulation_service.dart`
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart`
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_dialogs.dart`
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_screen.dart`
- `test/multi_account_prop_firm_test.dart`
- `docs/multi-account-prop-firm-planner-2026-07-21.md`

## Files modified

- `lib/app/hc_trader_app.dart`
- `lib/features/auth/auth_screen.dart`
- `lib/features/tools/toolkit_models.dart`
- `lib/features/tools/tools_screen.dart`
- `lib/core/localization/app_localizations.dart`

## Data model and storage

The immutable portfolio aggregate contains:

- schema version and portfolio identity;
- virtual portfolio risk budget and currency;
- strongly typed accounts, account status, priority, and drawdown type;
- portfolio risk policy and rotation strategy;
- planned trades and psychology checks;
- manually recorded results;
- discipline records and typed override reasons;
- account state-transition history;
- editable prop-firm rule presets;
- daily session, emotional stop, lock, and active-account state.

Storage uses an owner-scoped `SharedPreferences` JSON record with schema version `2`; schema version 1 migrates locally without blocking the original planner, and an optional current-situation review is available in Health. `guest` is the public owner key. When a signed-in local owner opens the tool without an existing portfolio, the guest portfolio is copied once into that owner's local storage; IDs are preserved so accounts and trades are not duplicated. Existing signed-in data always wins.

Import is limited to 2 MB and rejects unsupported schema versions, duplicate or empty IDs, orphan trades, invalid or non-finite numbers, invalid risk limits, and invalid profit splits. Export supports formatted JSON and CSV trade history. No credential field exists in the models or exports.

## Calculation formulas

- Planned risk amount: `reference balance × risk percentage / 100`.
- Long reward-to-risk: `(take profit - entry) / (entry - stop loss)`.
- Short reward-to-risk: `(entry - take profit) / (stop loss - entry)`.
- Remaining daily drawdown: `daily limit - realised loss - open planned risk`, floored at zero.
- Portfolio risk remaining: `global daily limit - realised loss - active planned risk`, floored at zero.
- Required gross payout: `max(firm minimum payout, desired gross payout)`.
- Payout progress: `eligible profit / required gross payout`; the display is clamped while surplus remains calculable.
- Estimated net payout: `required gross payout × profit split`.
- Recovery required: `max(0, starting balance - current balance)`.
- Required net R: `recovery required / planned risk`; zero-risk modes avoid division by zero.
- Firm trading-day key: current UTC instant plus configured firm UTC offset, shifted to the previous day when before the configured reset hour.

Every calculation validates non-finite, zero, negative, or directionally invalid inputs. Drawdown output uses the user-selected static, balance, equity, or trailing planning model and never claims to know an unconfigured firm formula.

## Account state transitions

| Event | Previous eligible state | Resulting state | Notes |
| --- | --- | --- | --- |
| Start session | `available` / eligible | `active` | One account is active by default |
| Full/partial profit | `active` | `coolingAfterWin` | Configured win cooldown is applied; rotation evaluates another account |
| Full/partial loss | `active` | `dailyLocked` or `coolingAfterLoss` | Strategy decides lock versus cooldown; streak and global loss update |
| Neutral/cancelled/not triggered | `active` | `available` | Result remains in history |
| Payout threshold reached | any result state | `payoutReady` | When payout protection is enabled |
| Manual pause | eligible | `paused` | Hard recommendation exclusion |
| Emergency planner lock | non-terminal state | `portfolioLocked` | Application workflow only; no external system is affected |
| Global daily/consecutive/trade stop | non-terminal state | `portfolioLocked` | Stops all recommendations |
| New firm trading day/reset | daily/cooldown/portfolio lock | `available` | History remains; open plans close; cooldown is cleared |
| Archive | any account state | `archived` | History remains available |
| Breach | any account state | `breached` | Hard recommendation exclusion |

Terminal and protected states (`archived`, `breached`, `payoutRequested`, `payoutReady`, and `payoutProtection`) are not overwritten by a global planner lock.

## Recommendation rules

The recommendation engine is transparent and rule-based.

Hard exclusions run first:

- breached, archived, paused, daily-locked, portfolio-locked, or payout-requested;
- active win/loss cooldown;
- missing balance or risk configuration;
- account daily trade limit reached;
- configured risk greater than remaining drawdown room;
- protected/payout-ready while payout protection is enabled;
- a different account selected by the one-account-per-session strategy.

Eligible accounts are ranked by remaining maximum drawdown room, account priority, time since last trade, loss/win streak, rotation order, payout proximity, recovery status, and open-plan use. The UI always shows human-readable recommendation and exclusion reasons; no opaque AI or unexplained score is used.

The duplicate-exposure check compares manually entered instrument and direction, combined planned risk, simultaneous-trading policy, and existing open plans. Continuing after a warning requires a typed reason stored in discipline history.

## Screens and routes

Route: `/tools/funded-account-rotation-planner`

Entry points:

1. Signed-in or access-pending users: **Trading Toolkit → Funded Account Rotation & Payout Planner**.
2. Signed-out users: **Use funded account planner as guest** on the authentication screen.

Module areas:

- first-use education and acknowledgement;
- strategy overview and healthy/destructive rotation examples;
- portfolio dashboard, account flow, health, metrics, and account management;
- rotation template builder and editable safeguards;
- daily control room;
- pre-trade decision and psychology assistant;
- manual result recording and post-result rotation;
- conservative recovery planner;
- payout planner and editable firm presets;
- seeded Monte Carlo hypothetical simulator;
- discipline analytics;
- bilingual education centre;
- JSON/CSV export, validated JSON import, session reset, archive, and delete.

## Responsive and accessible layout

- **Small mobile:** the five original bottom-navigation destinations remain in their original order, with Health sixth and Sequence seventh; health cards remain swipeable and the sequence timeline becomes a vertical conditional plan.
- **Tablet:** NavigationRail with two-column financial and educational cards.
- **Desktop/web/Windows:** extended NavigationRail on wide displays, three-column account/metric layouts, constrained 1220 px workspace, keyboard-native Material controls.

Status information uses icons and text as well as color. Risk and payout progress indicators include semantic text summaries. Material controls retain appropriate focus order and touch targets, layouts respect text scaling, and no flashing, confetti, casino styling, or color-only warning is used.

## Localization

The module adds an English-keyed Simplified Chinese catalog with more than 200 entries covering:

- navigation and empty states;
- account, safeguard, pre-trade, result, payout, and recovery forms;
- validation and confirmation dialogs;
- account/result status labels;
- recommendation and exclusion reasons;
- simulator warnings and output labels;
- educational and financial-safety content;
- export/import actions and accessibility summaries;
- public guest entry and Toolkit metadata.

No paid translation API is used.

## Tests

`test/multi_account_prop_firm_test.dart` covers:

- planned-risk and long/short reward-to-risk formulas;
- invalid price relationships and reset-time boundaries;
- portfolio risk, payout, and recovery calculations;
- TP cooldown/rotation and SL lock transitions;
- hard exclusions and explanations;
- duplicate/combined exposure warnings;
- healthy three-account rotation;
- two-loss global stop;
- manual override history;
- editable presets, serialization, validated import, and CSV export;
- guest-to-signed-in local migration without duplicate accounts;
- deterministic simulation and reduced-risk drawdown comparison;
- Simplified Chinese catalog coverage;
- public portfolio creation and wide desktop NavigationRail rendering.

Verification results:

- `flutter analyze`: passed with no issues.
- `flutter test`: all 454 tests passed.
- `flutter build web --debug`: passed; generated `build/web`. The optional Wasm dry run reports pre-existing third-party `flutter_secure_storage_web`/`image` compatibility warnings, while the standard web build succeeds.
- `flutter build windows --debug`: passed; generated `build/windows/x64/runner/Debug/hc_trader.exe`.
- `flutter build apk --debug`: passed; generated `build/app/outputs/flutter-apk/app-debug.apk`. Flutter reports the existing Android minimum SDK 23 future-deprecation warning; the build succeeds.

## Manual QA checklist

- [ ] Open the guest planner from the authentication screen without signing in.
- [ ] Switch between English and Simplified Chinese before and after onboarding.
- [ ] Create 1, 2, 3, and 4 starter accounts; add more accounts manually.
- [ ] Edit balances, equity, drawdown model, limits, payout rules, priority, and state.
- [ ] Confirm no password, login, API key, broker, MT4, or MT5 field appears.
- [ ] Select every rotation template and edit all portfolio safeguards.
- [ ] Start a session and verify only one account is active.
- [ ] Plan valid long and short trades and verify invalid SL/TP relationships are rejected.
- [ ] Create duplicate symbol/direction exposure and verify a typed override is required.
- [ ] Flag FOMO, revenge, frustration, or unacceptable loss and verify the emotional stop workflow.
- [ ] Record TP and confirm cooldown plus account rotation.
- [ ] Record SL and confirm daily lock/cooldown plus remaining global risk.
- [ ] Record two losses and confirm the whole planner portfolio stops.
- [ ] Record a manual override and verify discipline analytics update.
- [ ] Check conservative, standard, and pause recovery modes; confirm no risk doubling language.
- [ ] Edit The5ers and Alpha Capital Group example payout values.
- [ ] Run simulations with several assumptions and confirm hypothetical warnings remain visible.
- [ ] Export JSON and CSV; import valid JSON and reject malformed/duplicate records.
- [ ] Reset today's session and confirm recorded history remains.
- [ ] Archive all accounts and confirm planners show a safe empty state.
- [ ] Resize to phone, tablet, desktop, Chrome, and Windows dimensions; test keyboard and text scaling.
- [ ] Sign in after creating a guest portfolio and confirm the local portfolio is copied once without duplicate accounts.

## Known limitations and deliberate boundaries

- Portfolio data is local-first. This release does not add a Firestore collection or cloud synchronization because the public calculator must not depend on Firebase; signed-in cloud sync can be added later behind existing ownership rules.
- Guest-to-signed-in migration copies local data once on the same device. It is not cross-device sync.
- Screenshot fields store an optional user-entered local reference; this module does not upload attachments.
- Currency totals are not converted. Mixed-currency accounts should be reviewed separately because no FX rate feed is used.
- Duplicate exposure is based on manually entered symbols/directions and does not use live correlation data.
- The simulator is intentionally bounded to 500 seeded paths in the UI, uses user assumptions, and reports rounded frequencies. It is not a forecast.
- Firm presets are editable examples. The5ers USD 150 and Alpha Capital Group USD 100 are seeded initial values, not claims about current permanent rules.

## Safety and compatibility confirmations

- No MT4, MT5, broker, prop-firm login, live signal, or trade-execution connection was added.
- Core onboarding, calculations, portfolio control, payout, recovery, and simulation are accessible to signed-out public users.
- The5ers and Alpha Capital Group payout presets are editable.
- Existing auth, roles, routes, journal, admin, Firebase, MT5, and private Trading Toolkit features were not redesigned or bypassed.
- All implementation changes are isolated to the new feature plus narrow public/Toolkit/localization integration points.
