# Project Management System 2.0

## Architecture and security boundary

PMS is an isolated Laravel module within the existing application. Version 2.1 uses a dedicated `project_management_users` credential and email table with a one-to-one bridge to `users`, preserving established actor, assignment, comment, and project member foreign keys. New PMS passwords are independent of TradingHub and Dealership credentials. Known legacy PMS profiles are backfilled with their existing password hashes; their shared password remains synchronized when reset through the PMS broker. PMS uses a separate browser guard and password reset broker, plus the existing Sanctum API, CSRF protection, and feature toggle infrastructure.

PMS authorization has three layers:

1. `module_project_management` must be enabled.
2. The identity must be an existing role 1/2 administrator or have an active `pms_profiles` record.
3. Project operations require a row in `pm_project_members` and an appropriate project role. Assignment additionally requires an active PMS profile and current project membership.

Public PMS authentication is available only when TradingHub and Dealership E-commerce are both disabled. In combined mode, unauthenticated PMS landing, login, and registration routes return 404 and the public storefront remains unchanged. Authorized employees continue to use the private back-office workspace. Public registration never accepts a role; it creates a pending profile under the default approval policy.

Ticket creation in version 2.1 is granted to Business Analyst and Project Manager for REQ, ENH, PRD, and QAT; QA for QAT only. Administrators need an assigned creation role or an explicit ticket creation grant. Project membership still controls project visibility and editing. For QAT, QA estimates are held separately from the delivery target and are cleared when the QA assignee changes.

Projects, cycles, and work items live in the `pm_` namespace. Updates use database transactions, row locks for human-readable sequences, foreign keys, append-only history records, and optimistic lock versions. Project archive uses `archived_at`; recoverable deletion uses Laravel soft deletion. These are separate states. Permanent deletion is limited to system administrators, requires the exact project key, cascades only project-owned data, and retains subject-only audit records.

## Implemented in PMS 2.0

### Workplace experience

- Responsive PMS-specific sidebar, global work search, collapsible navigation, theme control, private workspace identity, and dedicated light/dark styling.
- Separate enterprise PMS login, registration, password recovery, membership-status, dashboard, reports, projects, cycles, work items, team access, settings, and audit screens.
- Shared responsive authentication layout and isolated stylesheet for login, registration, password recovery, and membership status, with visible form labels, keyboard focus, accessible password toggles, and readable validation states.
- No public PMS entry in combined module mode.

### Membership and authentication

- PMS profiles with pending, active, inactive, and rejected states.
- Registration policies: approval, open, invitation-only, and disabled. Invitation-only is enforced as closed public registration; token issuance and acceptance remain deferred.
- Administrator provisioning and registration review.
- Sanctum API login, policy-aware registration, password recovery, profile, token revocation, and administrator membership activation endpoints.
- API login issues a token only to an active PMS member or system administrator; pending and ordinary customer identities receive no token.
- Active PMS profile validation for project membership and development/QA assignment.
- Existing customer identities do not become PMS members automatically.

### Development cycles

- Project-scoped, concurrency-safe cycle keys such as `PMS-CYCLE-001`.
- 4, 5, and 6 week templates plus custom windows.
- Calendar-day or configured working-day schedules with weekend and holiday exclusions; template phase lengths follow the configured number of working days per week.
- Development, Internal Testing, QAT + Debugging, and optional Buffer phases.
- Rejection of phase plans that exceed the eligible cycle window.
- Create, update with mandatory schedule-change reason, duplicate, start, close, cancel, and recoverable delete.
- Original schedule baseline, phase actual dates, completion, sign-off, history, and audit events.
- Scope baseline at cycle start and carryover to another cycle without issue duplication.
- Explicit close dispositions: carry forward, backlog, cancel, or blocked.
- Separate schedule, work, and quality progress with a labeled unit basis.
- Explainable On Track, Attention Required, At Risk, Delayed, and Completed health states.
- Evidence-based forecast that returns `Insufficient Data` when throughput is unavailable.

### Work classification

- FTR, ENH, and PDI classification with stable keys such as `PMS-FTR-0001`.
- A single locked per-project sequence prevents key reuse and gives deterministic ordering across classifications.
- Classification-specific requirement, enhancement, and production-incident fields.
- Authorized, recorded PDI fast-track exceptions.
- Type remains independent of workflow status; the Phase 1 transition graph and evidence gates remain enforced.
- Legacy API input is mapped to a new classification for backward compatibility; every newly created key uses FTR, ENH, or PDI.

### Delivery tickets, QAT, and evidence

- Global, concurrency-safe ticket keys use separate REQ, ENH, PRD, QAT, and TSK sequences. Existing project-scoped FTR/ENH/PDI tickets keep their original keys and workflow.
- Category-specific required fields cover requirements, enhancements, production issues, development tasks, and reproducible QAT defects. Administrators can add or deactivate QAT defect types.
- QAT can enter an unassigned intake queue. Only its reporter and administrators can see an unassigned ticket; project membership controls visibility after triage.
- Delivery and QAT transitions are recorded in issue history. QAT retests store tester, build, environment, result, remarks, time, and optional evidence; failed retests reopen the original ticket.
- Ticket links support requirements, tasks, blockers, duplicates, causes, and regressions. Blocker links reject circular chains. Release moves require a reason and retain the same ticket key and history.
- Private evidence storage supports images, PDF, plain text, CSV, Office files, ZIP, MP4, and WebM. MIME and content checks reject renamed executables and invalid images, PDFs, and archives. Files are served only through ticket-authorized routes with download-safe response headers.
- Upload limits default to 10 files per request, 10 MiB per image, 25 MiB per PDF, and 50 MiB per other file. The size limits are configurable with `PMS_IMAGE_MAX_KB`, `PMS_PDF_MAX_KB`, and `PMS_OTHER_MAX_KB`.
- Comments render sanitized Markdown, retain edit/delete revisions, and notify only participants or mentioned users who can currently view the ticket. Attachment metadata is retained on removal; its stored file is deleted.
- Ticket activity combines system/status events, comments, and evidence uploads, with All, Comments, Status, and Attachments filters. The separate discussion remains ordered oldest or newest first.
- The filtered ticket list and CSV export use the same permission and filter query. CSV text that could become a spreadsheet formula is escaped.
- My assigned work includes both development and QA assignments, with separate role filters in the ticket list.

### Release management

- Releases belong to a project and cycle, with a version, standard/hotfix type, owner, planned deployment date, notes, and known issues. Scope is linked by ticket identity and locks at Ready for Release.
- Stage progression runs Planned, Development, Internal Testing, QAT Testing, Stabilization, Ready for Release, Approved, Released, and Completed; On Hold and Cancelled are explicit decisions.
- Readiness checks completed requirement/enhancement/task scope, unresolved P0/P1 tickets, QAT verification, development/internal-testing/QAT phase sign-offs, release notes, and approval. A Release Manager or administrator must record a reason for an authorized gate override.
- Approval, deployed build and time, release history, a deployment readiness snapshot, downloadable notes, and a PDF summary provide an audit trail. A scheduled daily command sends permission-checked deployment reminders for dates within three days, once per recipient and day.

### Project lifecycle and analytics

- Archive, restore from archive, typed move to Recently Deleted, restore from trash, and typed permanent purge.
- Deletion impact summary for work items, cycles, and memberships.
- Reporting period and project filters reflected in the URL.
- Drill-down KPIs and persisted aggregates for project status, classification, workflow status, priority, aging, active cycles, and developer workload.
- Separate development and QA workload views, weekly/monthly completed-versus-reopened trends from stored events, and direct release-readiness signals for QAT, dependencies, open critical work, assignment gaps, and deployment evidence.
- Delivery, reopen, and on-time rates include numerator, denominator, reporting period, and calculation basis.
- QA pass rate uses structured QAT verification results. It is unavailable when no verification exists in the reporting period.

### Reporting formulas

The selected period applies to ticket creation for delivery/reopen/on-time cohorts and to `tested_at` for QA verifications. Project filters and membership scope apply before aggregation. A zero denominator displays `N/A`.

| Metric | Numerator / denominator |
| --- | --- |
| Delivery rate | Non-QAT tickets currently at `Production Done` or `Completed` / non-QAT tickets created in the period, excluding cancelled, duplicate, and rejected tickets. |
| Reopen rate | Tickets with `reopen_count > 0` / tickets created in the period that reached legacy QA or QAT retesting, or have a QA completion/reopen record. |
| On-time delivery | Completed non-QAT tickets whose completion date is on/before the due date / completed non-QAT tickets with a due date. |
| QA pass rate | `Passed` QAT verification records / all `Passed`, `Failed`, and `Blocked` verification records tested in the period. Each retest counts as a separate run. |
| Average defect resolution | Mean hours from ticket creation to the latest QA completion timestamp for QAT tickets currently Verified/Closed and verified in the period. |
| Average QAT retest | Mean hours from the most recent Retesting transition to a passed verification record created in the period. Runs without a recorded Retesting transition are excluded. |
| Release completion rate | Completed releases / non-cancelled releases with a planned deployment date between the period start and today. Future releases are excluded. |

The delivery cards count current ticket category and QAT status, active/completed releases, due dates, and QAT intake. Release readiness is a current-scope check, not a historical performance percentage; deployment history preserves its snapshot at release time.

## Key files

- `database/migrations/2026_10_16_000001_create_project_management_tables.php`
- `database/migrations/2026_10_16_000002_enhance_project_management_2.php`
- `database/migrations/2026_10_16_000003_extend_pms_delivery.php` through `2026_10_16_000006_create_pms_release_reminders.php`
- `app/Models/PmProject.php`, `PmCycle.php`, `PmCyclePhase.php`, `PmWorkItem.php`, `PmsProfile.php`
- `app/Support/PmsAccess.php`, `app/Support/PmsModule.php`
- `app/Services/PmsWorkflow.php`, `app/Services/PmsCycleService.php`
- `app/Services/PmsQatWorkflow.php`, `PmsDeliveryWorkflow.php`, `PmsReleaseService.php`, `PmsAttachmentService.php`
- `app/Http/Controllers/Pms/`
- `resources/views/pms/`, `public/backend/assets/css/pms.css`
- `resources/views/pms/auth-layout.blade.php`, `public/backend/assets/css/pms-auth.css`
- `routes/web.php`, `routes/api.php`
- `tests/Feature/ProjectManagementTest.php`, `tests/Feature/ProjectManagementV2Test.php`, `tests/Feature/PmsDeliveryWorkflowTest.php`

## Administrator workflow

1. Run `php artisan migrate`.
2. Enable Project Management in the existing module settings.
3. Open **Team access** and approve a pending registration or provision an existing identity.
4. Create a project and add only active PMS members with project roles.
5. Create a development cycle from a template or custom dates. Configure the default calendar, weekends, holidays, and health thresholds in **Workspace settings**.
6. Add FTR/ENH/PDI legacy work or create REQ/ENH/PRD/QAT/TSK tickets. QAT reporters may use private unassigned intake; triage assigns the project.
7. Start the cycle to record its scope baseline.
8. Update phase actual dates, progress, and sign-offs. Workflow transitions continue to require assignment, QAT deployment evidence, QA results, and production evidence.
9. Close the cycle only after choosing an explicit disposition for unfinished work.
10. Create a release for the cycle, attach tickets, complete QA verification and phase sign-offs, prepare release notes, then approve and record deployment evidence.
11. Use Archive for retained history and Delete for the recoverable trash flow.

## Verification

Primary commands:

```text
php artisan test tests/Feature/ProjectManagementTest.php
php artisan test tests/Feature/ProjectManagementV2Test.php
php artisan test tests/Feature/PmsDeliveryWorkflowTest.php
php artisan view:cache
node --check public/backend/assets/js/pms-delivery.js
vendor/bin/pint ...PMS files...
```

The PMS suites cover module gating, customer isolation, project/work CRUD, workflow evidence, API authorization and token revocation, API membership activation, four and six week templates, configurable working weeks, impossible schedules, FTR/PDI and global ticket keys, dashboard counts and trends, archive versus trash, typed purge, PMS-only registration approval, combined-mode privacy, carryover identity, private QAT intake, evidence type/size validation, comment safety, QA retests, release gates/deployment, reminders, and HTML rendering of the main private screens.

The auth pages were rendered in local headless Chrome at 1280px desktop and 390px phone widths. The phone pages reported matching viewport and document widths (390px), and the password visibility and remember-me controls were exercised in the browser.

On 2026-10-17, the delivery suite passed 8 tests and 184 assertions; the two earlier PMS suites passed 6/86 and 10/110. Blade compilation, JavaScript syntax, PHP formatting, and route registration passed. The full application suite passed 186 tests, skipped 1, and failed 1 existing Trading Hub test (5,344 assertions overall): `RealMarketInsightTest::test_verified_real_candles_feed_all_six_indicators_without_an_incomplete_bar` expects `CONNECTED` but receives `IDLE` at line 94. The PMS work does not modify the market insight implementation.

## Remaining scope

- Invitation token creation, delivery, expiry, and acceptance. The invitation-only policy currently disables public registration securely.
- Dedicated PMS reset-password completion page. Password recovery starts in the PMS design and uses the existing secure Laravel reset flow.
- Structured test-case coverage reports beyond QAT verification pass/fail records.
- Dashboard charts for cycle burndown and historical planned-versus-actual cycle comparisons.
- Exact lead-time, cycle-time, and carryover-rate reports with timestamp-complete historical samples. QAT resolution and retest durations are available as defined above.
- Capacity and planned leave configuration. The interface reports unconfigured capacity rather than inventing availability.
- Dedicated test-case management, notification preferences, cross-project link policy, and attachment retention holds.
- Large-release scope pagination and a full interactive Gantt view; the current release board shows stage and schedule progress.
- Automatic retrospectives, repeated QA-loop analytics, release-risk intelligence, and schedule suggestions.
- Load, accessibility, and real-browser responsive verification.

## System log

No application semantic version is defined in the repository. The actual base revision is `d030cdd3`; entries remain unreleased until a deployment version is assigned.

| Version | Implementation date | Category | Module | Summary |
| --- | --- | --- | --- | --- |
| Unreleased (base `d030cdd3`) | 2026-10-16 | New | PMS Cycle Management | Added configurable templates, working calendars, phase schedules, baselines, progress, health, forecast, close disposition, and carryover. |
| Unreleased (base `d030cdd3`) | 2026-10-16 | New | PMS Work Classification | Added FTR, ENH, and PDI fields, immutable typed keys, reporting, and authorized PDI fast track. |
| Unreleased (base `d030cdd3`) | 2026-10-16 | Enhance | PMS Workplace Experience | Added dedicated authentication, membership approval, workspace navigation, cycle screens, reporting filters, and operational charts. |
| Unreleased (base `d030cdd3`) | 2026-10-16 | Fix | PMS Project Lifecycle | Separated archive from recoverable trash, added typed confirmation and restricted permanent purge, and enforced active PMS assignment membership. |
| Unreleased (base `d030cdd3`) | 2026-10-17 | New | PMS Delivery and QAT | Added global ticket types, QAT intake/retests, private evidence, comments, links, filtered CSV, release management, readiness and approvals, reminders, and delivery reporting. |
