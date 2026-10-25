# Multiple Account Strategy audit and repair

Date: 2026-07-22  
Application: HC Trader Flutter client  
Feature: Multiple Account Strategy / Funded Account Rotation Planner

## Outcome

The feature now uses a shared `ChangeNotifier` controller backed by a single
repository stream, deterministic domain services, a schema-v3 Firestore data
source, user-scoped security rules, and a local draft cache. The production
permission failure shown in the supplied screenshot was traced to the deployed
ruleset, not to the user's account data: production had no
`multiple_account_strategy` match block and therefore denied every save.

Only the missing feature-specific rule block was added to the production
ruleset. The active Cloud Firestore release changed from ruleset
`460d5b24-7aef-4e98-8f41-9732e69a6ae0` to
`08763118-cf3c-4f49-99fd-c5d5dbf5d053` at
`2026-07-22T01:16:56.619098Z`. The deployed rules now contain the feature paths.
Unrelated local rule changes were not deployed.

The failed portfolio was also confirmed to remain in the local application
cache as a schema-v3 workspace with three accounts. No production portfolio
documents were deleted or rewritten during diagnosis.

## 1. Root causes and fixes

| Problem | Root cause | Main files | Fix and regression coverage |
| --- | --- | --- | --- |
| Production saves returned `permission-denied` | The deployed Firestore rules predated the feature and had no matching user path, so the default deny applied | `firestore.rules`, `functions/test/multiple_account_strategy_rules.test.js` | Added owner-scoped, typed rules and deployed only that block; emulator tests cover owner, other-user, unauthenticated, admin, immutable snapshots, invalid IDs, and invalid types |
| Retry could remain disconnected after a permanent listener error | Retrying the write did not recreate the Firestore snapshot subscription | `prop_firm_planner_controller.dart` | Retry now restarts the repository subscription after a successful commit |
| Screens became stale or disagreed | Each controller could load a one-time local copy and local repositories had no shared broadcast source | repository, snapshot, controller, screen | Added one repository snapshot stream, one screen-owned controller, shared owner-scoped local broadcasts, and Firestore listeners for workspace/accounts/sequence |
| Local state could appear saved when cloud persistence failed | The old flow did not expose a complete cloud commit/sync state | repository, controller, screen | Cloud writes are awaited; local draft is retained on failure; saving, synchronized, pending, offline, failed, last-updated, and manual-retry states are visible |
| Account/session data had no consistent cloud structure | The feature was primarily a local aggregate and lacked typed, UID-scoped account/session persistence | Firestore data source, collection constants, models | Added valid collection/document paths, stable IDs, atomic batch writes, server timestamps, sessions, immutable account snapshots, events, and history |
| Calculation values differed by screen | Derived risk, health, stop, dashboard, and discipline results were mixed with presentation/controller behavior | calculation, health, stop, dashboard, discipline services | Moved deterministic calculations into shared domain services consumed by every surface |
| Profitable P/L and floating P/L could distort loss values | Loss inputs and the selected calculation method were not consistently separated | `prop_firm_calculation_service.dart` | Effective P/L respects realized-only or realized-plus-unrealized rules; loss used is floored at zero; raw breach ratios remain observable while displayed remaining values floor at zero |
| Static and trailing drawdown references were ambiguous | Maximum-loss calculations did not consistently select the configured reference | models, calculation service | Added explicit reference/drawdown methods, initial/equity/high-water/trailing/custom inputs, and safety-buffer calculations |
| Health labels and sequence eligibility were inconsistent | Health and payout decisions were evaluated by more than one code path and did not have one precedence order | health service, sequence service/models | Centralized precedence: breached, critical, protected, paused/unavailable, high risk, caution, healthy; unsafe accounts are excluded with reasons |
| A selected account did not reliably remain first | Ranking could override the user's safe selection or rely on unstable ordering | sequence service/models/controller | Safe selected accounts receive a bounded priority; exclusions always win; remaining ties use stable fields |
| USD 200 used from a USD 500 session limit could display `Stopped` | Stop state was coupled to general lock/status flags rather than one explicit condition engine | stop service, dashboard service, models, screen | A centralized engine evaluates each enabled rule; 40% usage alone is active/warning; every stopped result has a structured persisted reason |
| The screenshot showed `Stopped` and `Session not started` together | The subtitle only checked `sessionActive`, ignoring the persisted lifecycle status | `prop_firm_planner_screen.dart`, localization, regression test | A stopped workspace now says `Session stopped`; ready/completed/cancelled are also explicit; the manual override and reset flows remain available |
| Discipline displayed an unjustified 100/100 | The value did not require meaningful behavioral evidence | discipline and dashboard services | Score is calculated from auditable records; an unused workspace displays `Not enough data` |
| Legacy accounts without IDs could change identity between loads | Missing identifiers did not have a stable migration fallback | models | Schema-v3 migration generates a deterministic FNV-derived legacy ID from stable raw account content |

## 2. Files created or changed for this repair

### Created

- `lib/features/multi_account_prop_firm/data/firestore_prop_firm_portfolio_data_source.dart` — UID validation, Firestore streams, schema-v3 conversion, atomic workspace saves, migration, sessions, snapshots, events, and history.
- `lib/features/multi_account_prop_firm/data/portfolio_repository_snapshot.dart` — repository value plus synchronization metadata.
- `lib/features/multi_account_prop_firm/domain/session_stop_condition_service.dart` — explicit session lifecycle and stop-reason engine.
- `lib/features/multi_account_prop_firm/domain/discipline_score_service.dart` — evidence-based discipline metrics.
- `lib/features/multi_account_prop_firm/domain/portfolio_dashboard_service.dart` — central dashboard aggregation.
- `test/multiple_account_strategy_regression_test.dart` — calculation, thresholds, stop, migration, reactive sync, status UI, and tablet regressions.
- `functions/test/multiple_account_strategy_rules.test.js` — Firestore emulator authorization/type/immutability tests.
- `docs/multiple-account-strategy-audit-repair-2026-07-22.md` — this handoff.

### Modified

- `lib/features/multi_account_prop_firm/data/prop_firm_portfolio_repository.dart` — cloud/local composition, shared stream, awaited persistence, readable errors, retained draft, export/import validation.
- `lib/features/multi_account_prop_firm/domain/prop_firm_models.dart` — schema v3, complete raw account/risk/payout/session fields, typed lifecycle and stop reasons, symmetric JSON/copy behavior, stable legacy IDs.
- `lib/features/multi_account_prop_firm/domain/prop_firm_calculation_service.dart` — unified effective P/L, loss limits, drawdown references, buffers, exposure, payout, and percentage calculations.
- `lib/features/multi_account_prop_firm/domain/account_health_decision_service.dart` — shared 40/70/85 thresholds, precedence, score breakdown, and payout safety.
- `lib/features/multi_account_prop_firm/domain/account_sequence_models.dart` — persisted evaluations, exclusions, overrides, settings, and sequence state.
- `lib/features/multi_account_prop_firm/domain/account_sequence_service.dart` — deterministic safe ranking and hard exclusions.
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart` — one reactive feature state, guarded persistence, external-update handling, retry/reconnect, session events, and derived service calls.
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_screen.dart` — sync/error bar, exact stop reason and lifecycle, responsive navigation/cards, and shared dashboard/control-room values.
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_dialogs.dart` — validated drafts and safer save behavior.
- `lib/features/multi_account_prop_firm/presentation/account_health_ui.dart` — shared health results and reasons.
- `lib/features/multi_account_prop_firm/presentation/account_sequence_ui.dart` — exclusions/protection/reasons and deterministic sequence state.
- `lib/core/config/firestore_collections.dart` — centralized feature collection/document names.
- `lib/core/localization/multi_account_prop_firm_translations.dart` — English-keyed Chinese feature, sync-error, and lifecycle translations.
- `lib/core/localization/account_health_translations.dart` — health classification/reason translations.
- `lib/core/localization/account_sequence_translations.dart` — sequence/reason translations.
- `lib/features/tools/tools_screen.dart` — passes the authenticated Firebase UID and enables the cloud repository for signed-in users.
- `firestore.rules` — private owner/admin access, field types, stable IDs, and immutable session snapshots.
- `functions/package.json` — isolated Multiple Account Strategy rules-test command.
- `test/multi_account_prop_firm_test.dart` — expanded feature behavior and repository coverage.
- `test/account_health_decision_service_test.dart` — calculation, health, payout, and migration cases.

No file was removed.

## 3. Final Firestore schema

The logical intermediate workspace is implemented as a document so all paths
obey Firestore's collection/document alternation:

```text
users/{uid}/multiple_account_strategy/workspace
  accounts/{accountId}
  configuration/sequence
  sessions/{sessionId}
    account_snapshots/{accountId}
    events/{eventId}
  history/{historyId}
```

The workspace stores raw portfolio/session policy and lifecycle values, owner
UID, schema version, migration marker, and timestamps. Account documents store
stable IDs and normalized numeric/enum values for identity, balances/equity,
daily/maximum drawdown rules, current P/L and exposure, status, availability,
payout state/protection, notes, and timestamps. No display-formatted currency or
percentage strings are written.

The confirmed session document stores its sequence and risk policy. Its
per-account snapshot stores the raw account, derived evaluation, sequence
position, exclusion reasons, and rule-configuration version. Snapshot updates
and deletes are rejected by rules. Session events use stable discipline-record
IDs. Terminal sessions are summarized under `history` with the exact stop
reason.

All related documents are written in one `WriteBatch`, use server timestamps
for server-controlled update fields, and carry `owner_uid`. Security rules
require the authenticated path owner (or the existing admin role), validate the
document ID and important numeric/schema types, deny cross-user access, and
prevent destructive history changes.

## 4. State synchronization and save behavior

```text
Firestore snapshot streams
        ↓
FirestorePropFirmPortfolioDataSource
        ↓
PropFirmPortfolioRepository snapshot + local draft cache
        ↓
one PropFirmPlannerController (ChangeNotifier)
        ↓
dashboard / accounts / control room / tools / health / sequence
```

The controller subscribes once to the owner-scoped repository. A Firestore
account/workspace/sequence change is decoded into one immutable snapshot, then
recomputes dashboard, health, recommendations, sequence, payout, and session
state before notifying every page. Local-only/guest repository instances share
an owner-keyed broadcast stream, so two controllers also update immediately.

Writes are awaited before success is shown. A Firebase failure is mapped to a
readable message and the validated draft remains in local storage. Firestore
metadata drives pending/offline/synchronized UI, with last-updated time. Failed
writes expose Retry; retry also recreates a Firestore listener that previously
ended with a permanent permission error. Editable drafts use persisted
`updatedAt` state to avoid silently replacing a newer external revision.

## 5. Calculation and decision changes

- Daily and maximum limit reference amounts follow the account's configured method rather than always using account size.
- Effective daily/overall P/L conditionally includes floating P/L.
- `lossUsed = max(0, -effectivePnl)`; profitable days therefore never become negative loss.
- Remaining loss values floor at zero, but raw usage above 100% still creates a breach.
- Static and trailing drawdown references, personal stops, open exposure, combined session loss, and recommended per-trade risk remain distinct values.
- The recommended risk applies the configured safety buffer; the prop-firm's official remaining limit is not presented as suggested trade risk.
- Health boundaries are deterministic: below 40 healthy, 40–<70 caution, 70–<85 high risk, and 85+ critical, subject to explicit precedence.
- Payout approaching/eligible/requested/processing states reduce or exclude risk according to protection settings; an override requires a reason and leaves an auditable warning.
- Sequence hard-excludes breached, critical, protected, paused, unavailable, archived, and manually excluded accounts unless an allowed reasoned override applies. A safe selected account remains first; stable tie-breakers prevent rebuild changes.
- Stop status comes only from the stop-condition engine. Combined realized session loss and manually entered open exposure are evaluated separately. Every stopped state contains a code, title/description keys, timestamp, and actor.
- Dashboard totals are produced by one aggregation service from the latest repository snapshot.
- Discipline is based on meaningful recorded actions and rule compliance; no evidence produces `Not enough data`.

Screenshot regression result:

```text
combined limit:       USD 500
realized loss used:   USD 200
remaining:            USD 300
usage:                40%
default lifecycle:    warning/active, not stopped
```

Enabling stop-after-one-loss for the same inputs produces a stopped lifecycle
and the visible exact reason `Stopped: One-loss stop rule triggered`.

## 6. Migration and compatibility

- `schema_version` is upgraded idempotently to 3 and the workspace records `completed_versions`.
- Existing local data is read first and remains available until a cloud commit succeeds.
- Known numeric strings are safely accepted as numbers; missing known fields receive conservative defaults.
- ISO/date values are converted between model dates and Firestore `Timestamp` values.
- Legacy stopped/locked state receives a structured `legacySafetyLock` reason so a stopped session cannot be reasonless.
- Missing account IDs receive deterministic `legacy-account-<hash>` identifiers and therefore remain stable on later loads.
- Known legacy account/payout/status values are read through typed enum fallbacks, and malformed imports are rejected before write.
- A legacy aggregate or older schema read schedules one schema-v3 save only; completed data does not continually remigrate.
- Unknown Firestore fields survive merge writes at the workspace/account document level. The portable JSON export only guarantees supported schema fields.

## 7. Automated verification

Final commands and results:

| Check | Result |
| --- | --- |
| `dart format` on changed Dart/tests | Passed |
| `flutter analyze` | Passed, no issues |
| `flutter test` | Passed, all 510 tests |
| Focused controller + regression tests | Passed, 33 tests |
| Feature-focused unit/widget suite | Passed, 83 tests |
| Firestore emulator `npm --prefix functions run test:multiple-account:rules` | Passed |
| `flutter build windows --debug` | Passed |
| `flutter build apk --debug` | Passed |
| `flutter build web --debug` | Passed |

The new regression coverage includes profitable/floating P/L, zero and breached
limits, static/trailing references, safety buffers, 39.99/40/69.99/70/84.99/85
health boundaries, precedence, deterministic/excluded sequence entries, the
USD 200/USD 500 case, enabled/disabled stop rules, exact stop reasons, manual
and exposure stops, multi-subscriber repository updates, two-controller sync,
schema migration, stable IDs, serialization, honest discipline state, stopped
control-room semantics, desktop UI, and tablet overflow.

## 8. Manual verification steps

Use an authenticated non-admin test user and avoid real prop-firm credentials.

### Windows

1. Run `flutter run -d windows` and sign in.
2. Open Tools → Multiple Account Strategy. The top bar should show synchronized or a truthful pending/offline state.
3. Open Accounts, edit Account C's realized P/L to move usage above 70%, and save.
4. Without closing the feature, visit Portfolio, Health, Sequence, and Control room. Confirm Account C and all totals/reasons update.
5. Close and reopen the app; confirm the values remain.
6. Enable payout protection for Account A; confirm Protected appears and A is excluded from the normal sequence.
7. Create/confirm a session, record one losing result with one-loss stop disabled, and confirm USD 200 of USD 500 is Warning/Active.
8. Enable one-loss stop, record the result, and confirm the exact stop reason plus `Session stopped`.

### Android phone

1. Run `flutter run -d <phone-id>` or install `build/app/outputs/flutter-apk/app-debug.apk`.
2. Repeat the create/edit/restart flow above.
3. At 360–412 logical pixels wide, confirm single-column cards, reachable actions, vertical/scrollable sequence, and no overflow in English and Chinese.
4. Disable connectivity before editing, confirm the sync state is honest and the draft remains, restore connectivity, then use Retry if the failed state is shown.

### Android tablet

1. Run on a tablet or emulator near 820×1180 logical pixels.
2. Confirm adaptive navigation and two-column cards where space permits.
3. Use long account names and Chinese locale; verify no clipped sequence labels or inaccessible buttons.
4. Repeat the cross-page Account C and payout-protection checks.

### Web / Chrome

1. Run `flutter run -d chrome`.
2. Sign in and repeat the persistence and cross-page update flow.
3. Resize between phone, tablet, and wide desktop widths; confirm navigation changes without overflow.
4. Open a second tab for the same user, edit an account in one tab, and confirm the other tab receives the Firestore snapshot update.
5. Inspect Firestore to confirm normalized account fields and the user-scoped session/snapshot/event/history documents.

For the screenshot currently on screen, click **Retry** in the failed-sync bar.
If that running process predates the controller reconnect patch, close and reopen
the feature once; the production permission rule itself is already live.

## 9. Remaining limitations

- Physical Android phone/tablet interaction was not available in this environment. Android APK compilation and responsive widget tests passed; perform the smoke steps above on release-target devices.
- A signed-in, full Flutter UI workflow against the Firestore emulator is not automated. Repository/controller/widget tests plus emulator security-rule tests cover the layers independently.
- Currency continues to use finite Dart `double` values for backward compatibility. Values are normalized and rounded for display, but a future schema can adopt integer minor units if exact sub-cent accounting becomes necessary.
- Cross-account exposure uses manually entered symbols, direction, setup, and risk. No broker/MT5 feed or automatic correlation market data was added by design.
- The web build reports only existing third-party WebAssembly dry-run compatibility notices; the standard JavaScript web build succeeds.
- Android reports the existing future min-SDK deprecation notice; the current debug APK succeeds.
- Full linguistic review by a native Chinese financial editor was not available. New English keys have Chinese catalog values and the Chinese layouts compile/render in tests.

## 10. Final status

- [x] Cross-screen state fixed
- [x] Firestore persistence fixed
- [x] Calculations fixed
- [x] Sequence logic fixed
- [x] Stop reasons fixed
- [x] Payout protection fixed
- [x] Session history fixed
- [x] Responsive UI verified by builds and widget tests
- [x] Localization keys and layouts verified
- [x] Tests passing (510/510 Flutter; feature rules emulator passed)
- [x] Flutter analyze passing with no issues

