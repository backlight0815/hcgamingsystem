# Standalone Flutter Feature Map

Inventory date: 2026-06-19

The Laravel application was reviewed only as the original feature and design
reference. Flutter owns the application experience and Firebase owns mobile
authentication, persistence, authorization rules, live data and file storage.
The app does not call Laravel, render Blade pages, or create Laravel sessions.

## Reference areas reviewed

- Authentication, registration/referral roles, onboarding, profiles, and role gates.
- Trading journals, capital ledger, reports, statistics, behavior detection, risk
  alerts, position applications, and daily examinations.
- Prop-firm phases, phase rules, drawdown limits, best-day consistency, gross-profit
  withdrawal rule, review locks, final examination, and model selection.
- Market analysis, signals, blogs, classes, knowledge, and protected resources.
- Trading-related administration, user counts, approvals, settings, and reports.

Roles remain Firestore-authoritative. Relevant IDs include admins `1/2`, market
analyst `501`, trader `750`, trading leader `760`, and recruiter `770`.

## Native mobile coverage

| Area | Flutter implementation | Server responsibility | Status |
|---|---|---|---|
| Authentication | Firebase email/username login, registration, reset, restore and logout | Firebase Auth + Firestore profile | Native |
| Dashboard | Balance, P/L, trade results, drawdown, consistency, recent activity | Firestore journal/capital streams | Native |
| Journal | Draft/open/closed CRUD, account assignment, attachments, search/filter, spreadsheet import, psychology, capital ledger and reports/PDF | Ownership, validation, file storage, secure persistence | Native |
| Statistics | Metrics, equity/daily charts, monthly calendar, pair/session/weekday breakdowns and rule monitor | Source data | Native |
| Risk calculator | Risk amount, pip distance, lot size, RR, projected P/L; pair-aware pip size | Pair reference data | Native Dart |
| Prop firm | Phase rules, progress, target, drawdown, minimum days and warnings | Firestore model and approvals | Native |
| Psychology | Check-ins, discipline/readiness score, FOMO/revenge/overconfidence warnings, history | Optional journal persistence | Native/local-first |
| Trading plan | Strategy, criteria, risk limits, sessions, pairs, cooldown and notes | None currently | Native/local-first |
| Daily exam | Questions, answers, scoring and history | Firestore questions/attempts | Native |
| Final exam | Start, answer, submit, pass status and prop-firm selection | Firestore questions/attempts/profile | Native |
| Account/profile | Profile/password, onboarding upload, deposits/withdrawals and multi-broker account CRUD | Authorization, storage and approval | Native |
| Trading hub | Analyses, blogs, signals, resources, recordings and knowledge | Visibility/password checks | Native reader |
| Position applications | Eligibility and submission | 30-day/60-trade and role checks | Native |
| Admin | Overview, user search/filter/edit, role/status control, onboarding decisions and position approvals | Admin authorization and final validation | Native |

## Dart business services

- `TradingCalculator` and `TradingTimeService`: pip/P&L and MT5-to-MYT timelines.
- `TradingAnalytics` and `TradingReportService`: balances, rates, expectancy,
  consistency, drawdown, profit factor, withdrawal rules, and reports.
- `RiskCalculatorService`: risk amount, stop/pip distance, position size, RR and
  projected outcomes.
- `PropFirmProgressService`: targets, remaining target, profitable days,
  daily-limit and overall-drawdown state.
- `PsychologyScoreService`: readiness/discipline score and behavioral warnings.

The server recalculates and validates secure writes. Mobile calculations are for
instant UX and are covered by Dart tests.

## Firebase boundary

- Firebase Auth owns credentials and sessions. Passwords never enter Firestore.
- Firestore live streams own every mobile data module and use Firebase UID as the
  ownership boundary.
- Firestore rules prevent members from changing their own role, status or admin
  fields and grant cross-user review access only to roles `1/2`.
- Firebase Storage paths are scoped under `users/{firebaseUid}`.

## Offline/local-first behavior

- Firebase Auth securely restores the user session.
- Shared preferences contain only non-secret device-local data.
- Trading plans, psychology history, prop-firm settings and the last full risk
  calculation survive restarts.
- Firestore provides local persistence and automatically synchronizes pending
  journal, account and capital writes when connectivity returns.

## Remaining parity boundaries

- Dedicated admin question-bank and prop-firm configuration editors remain web
  administration workflows; mobile covers the daily operational review queues.
- Journal search is native client-side search across the synchronized journal.
  Server-side full-text search for very large histories remains future work.
- Strategy/session values are derived in reporting; dedicated reusable strategy
  template CRUD is not yet part of the reviewed Laravel data model.
- Firebase Storage must be initialized in the Firebase Console before document
  and journal attachment uploads can work.
- Physical-device role checks still require representative Firebase accounts.

## Compatibility

Existing Laravel routes, SQL tables, Blade screens and web sessions are not used
by this Firebase-only Flutter build. The Laravel website remains a separate
application and does not need to be deployed for mobile closed testing.
