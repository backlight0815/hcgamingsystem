# Project Management 2.2.0 development report

Date: 2026-10-18 (Asia/Singapore)  
Module: Project Management  
Environment: local Laravel 10 application, MySQL for the additive migration, isolated test database for automated tests.

## A. Root cause analysis

**REQ creation:** The reported failure did not reproduce with the available account and request details. The existing API path and a new HTML form test both created a REQ before the changes. The available `storage/logs/laravel.log` contains an old unrelated service-controller error, not a REQ insert exception. A real failure-feedback defect was found in the attachment path: JavaScript submitted the form with XHR, followed the Laravel redirect internally, and then navigated away. Validation messages flashed on that redirected response could be lost, making the form appear to fail silently. The form now uses a normal multipart submission, preserves old input and field errors, and disables its submit button after submission. A UUID creation token and a database unique constraint make retries idempotent; the ticket sequence row is locked when assigning a reference. This fixes the confirmed silent-feedback and duplicate-submit paths, but does not establish that the originally reported production failure had the same cause.

**Permissions:** Several write endpoints relied on different role checks. In particular, management delete actions were missing or inconsistent, and project member assignment accepted every PMS role from any project manager. Permission checks now use the existing `PmsAccess` and `PmsTicketAccess` rules on the server; project managers can assign only ordinary project roles, cannot change their own or a higher management membership, and cannot delete resources outside their project. UI actions mirror the server rules.

**Registration and referrals:** PMS already has `project_management_users` identities bridged to `users`; TradingHub and Dealership use shared referral fields. A previous 2.1.1 change allowed referral-free PMS bridge rows on legacy schemas. This release adds PMS-specific invitation, acceptance, and registration referral records. It does not use the other modules' referral IDs. The normal public PMS registration route was hidden when either other public module was enabled. A server-validated invitation now admits registration with any combination of those modules, subject to PMS enablement and its own registration policy. Merely adding an `invite` query parameter grants nothing.

## B. Implementation summary

| Requirement | Delivered change |
| --- | --- |
| 1. REQ | Native form submission with visible validation, preserved input, retry token, unique sequence locking, creation history; historical `EHN` is read as ENH and new requests use `ENH`. |
| 2. Roles and BA review | Scoped ticket, release, and cycle soft delete with typed confirmation and audit history; project soft delete records actor. BA can edit REQ/ENH content, submit, read feedback, revise, and resubmit. A separate manager decision endpoint prohibits self-approval. |
| 3. Timeline | Real cycle and phase data rendered as horizontally scrollable planned and actual bars with day, week, and calendar-month scales, progress, delay, milestones, today marker, dates, and cycle metrics. Phase schedule edits require a reason and record history. |
| 4. Sidebar | Collapsible grouped navigation, active route states, compact desktop mode, mobile drawer, saved preference, and management links shown only to eligible managers. |
| 5. Team | Scoped directory with real counts, search, role/status filters, sort, pagination, member detail and workload data. Existing admin membership actions remain available; role changes now use a transaction. |
| 6. Invitations | Scoped issue, copy, search, revoke, and reissue flows. 256-bit token is looked up by hash; an encrypted copy supports manager-only link retrieval. Expiry, email binding, usage limit, issuer scope, replay, and role checks are enforced. New and existing account acceptance paths are atomic. |
| 7. Module combinations | Valid invitation registration works with TradingHub and Dealership independently on or off. Ordinary public registration retains its prior visibility rule. |

## C. Important files

| File(s) | Purpose |
| --- | --- |
| `app/Http/Controllers/Pms/TicketController.php`, `TicketReviewController.php`, `resources/views/pms/ticket-create.blade.php`, `public/backend/assets/js/pms-delivery.js` | Ticket create, update, review, delete, and form feedback. |
| `app/Http/Controllers/Pms/ProjectController.php`, `ReleaseController.php`, `CycleController.php`, `IssueController.php`, `TicketCommentController.php`, `TicketAttachmentController.php`, `TicketLinkController.php`, `WikiController.php`, `GlobalCycleController.php` | Scoped permissions, safe deletion, and phase editing. |
| `app/Http/Controllers/Pms/InvitationController.php`, `AuthController.php`, `app/Services/PmsInvitationService.php`, `app/Support/PmsModule.php`, `app/Support/PmsAccess.php`, `routes/web.php` | Invitation lifecycle, independent PMS registration, authentication, rate limits, and roles. |
| `app/Http/Controllers/Pms/TeamController.php`, `resources/views/pms/team.blade.php`, `team-member.blade.php`, `invitations.blade.php`, `register.blade.php`, `login.blade.php` | Team and invitation management pages. |
| `resources/views/pms/layout.blade.php`, `partials/cycle-timeline.blade.php`, `cycle.blade.php`, `public/backend/assets/css/pms-management.css`, `public/backend/assets/js/pms-timeline.js` | Navigation, shared styling, responsive timeline and controls. |
| `database/migrations/2026_10_18_000002_add_pms_management_flows.php`, `config/pms.php` | Schema and version 2.2.0 system log. |
| `tests/Feature/PmsManagementFlowsTest.php`, `PmsTicketFormTest.php` | New regression evidence. |

## D. Database changes and rollback

Migration `2026_10_18_000002_add_pms_management_flows.php` was applied to local MySQL with `php artisan migrate --path=database/migrations/2026_10_18_000002_add_pms_management_flows.php --force --no-ansi`. No destructive schema reset or real-data seeder was run.

| Object | Change |
| --- | --- |
| `pm_work_items` | Nullable unique UUID `creation_token`; nullable `deleted_by` user foreign key. |
| `pm_projects`, `pm_cycles`, `pm_releases` | Nullable `deleted_by` user foreign key; existing soft-delete timestamps are used. |
| `pm_ticket_reviews` | Review revisions with unique `(issue_id, revision)`, reviewer, feedback, timestamps, status index and foreign keys. |
| `pms_invitations` | Unique code, unique token hash, encrypted token, optional email/project/referral, role, expiry index, usage counters, revoke metadata, creator foreign key. |
| `pms_invitation_acceptances` | Unique `(invitation_id, user_id)` with foreign keys. |
| `pms_registrations` | Nullable invitation foreign key and PMS-specific referral code. |

The migration has a `down()` method. Rolling it back after users accept invitations would delete the new invitation and review records and remove their columns; export those records before any rollback. The separate pending administrative-audit migration was not included in the path-limited local migration command.

## E. Effective permission matrix

All entries are subject to an active account, PMS enablement and project scope. `Yes` for Project Manager means a project where the user holds that role. `Admin` covers the existing backoffice administrator and global Super Admin path. A profile with the existing `can_create_all_tickets` administrator-granted override can broaden creation rights.

| Operation | Admin | Project Manager | Business Analyst | QA | Developer / other standard role |
| --- | --- | --- | --- | --- | --- |
| Create REQ / ENH / PRD | Yes | Yes | Yes | No | No |
| Create QAT | Yes | Yes | Yes (existing rule) | Yes | No, unless existing override |
| Edit REQ / ENH content | Yes | Yes | Yes | No | No |
| Submit REQ / ENH review | Yes | Yes | Yes | No | No |
| Decide review | Yes | Yes, not own submission | No | No | No |
| Delete ticket / release / cycle / project | Yes | Yes | No | No | No |
| Create invitation | Yes, allowed roles | Yes, ordinary roles in managed project | No | No | No |
| Manage workspace membership | Yes | Project membership only, ordinary roles | No | No | No |

Wiki spaces are workspace-wide rather than project-owned. Page deletion remains administrator-only because a project manager's scope cannot safely be established for a shared page. Permanent project purge also remains administrator-only.

## F. Verification and test results

Commands executed: `php artisan test --stop-on-failure` (full suite), the focused PMS feature tests, `php artisan view:cache`, `npm run build`, and JavaScript/CSS syntax checks. The final full run passed **223 tests, 5,884 assertions, 1 skipped**. The focused PMS run after the final test assertions passed **12 tests, 179 assertions**.

`PASS` below means an automated HTTP, integration, or build assertion actually ran. It does not imply a visual browser check. `NOT RUN` identifies tests that need an interactive browser or true concurrent requests. No `FAIL` or `BLOCKED` result remains in the executed suite.

| Case | Result | Evidence / limit |
| --- | --- | --- |
| TC-001 | PASS | HTML form creates BA REQ. |
| TC-002 | PASS | Existing role matrix creates PM REQ. |
| TC-003 | PASS | Required field rejected. |
| TC-004 | PASS | QA REQ POST returns 403. |
| TC-005 | PASS | Same UUID returns existing ticket. |
| TC-006 | PASS | Detail and list show created REQ. |
| TC-007 | PASS | Existing ENH, PRD, QAT creation matrix. |
| TC-008 | PASS | PM ticket soft delete. |
| TC-009 | PASS | Release deletion detaches and preserves ticket. |
| TC-010 | PASS | Cycle deletion detaches active release and preserves project/ticket. |
| TC-011 | PASS | QA ticket DELETE returns 403. |
| TC-012 | PASS | Developer project DELETE returns 403. |
| TC-013 | PASS | BA release DELETE returns 403. |
| TC-014 | PASS | PM outside-project ticket and project DELETE return 403. |
| TC-015 | PASS | Ticket delete writes actor, issue history and audit log. |
| TC-016 | PASS | Existing global Super Admin PMS tests in full suite. |
| TC-017 | PASS | BA submits REQ review. |
| TC-018 | PASS | BA submits ENH review. |
| TC-019 | PASS | Incomplete stored REQ is rejected for review. |
| TC-020 | PASS | Feedback, revision and resubmission saved. |
| TC-021 | PASS | BA decision request returns 403; submitter self-decision denied. |
| TC-022 | PASS | PM issues scoped QA invitation. |
| TC-023 | PASS | Valid invitation page responds 200. |
| TC-024 | PASS | Referral code recorded in PMS registration. |
| TC-025 | PASS | Invalid token responds 404. |
| TC-026 | PASS | Expired token responds 404. |
| TC-027 | PASS | Revoked token responds 404. |
| TC-028 | PASS | Used single-use token responds 404. |
| TC-029 | NOT RUN | Row lock and unique acceptance exist; no true parallel-request stress test ran. |
| TC-030 | PASS | Correct QA project membership created. |
| TC-031 | PASS | New QA cannot delete project. |
| TC-032 | PASS | Posted elevated role is ignored; PM high-role invitation and assignment rejected. |
| TC-033 | PASS | Existing account signs in and accepts without duplicate identity. |
| TC-034 | PASS | PM cannot invite into an unmanaged project. |
| TC-035 | PASS | Both other modules on. |
| TC-036 | PASS | TradingHub on, Dealership off. |
| TC-037 | PASS | TradingHub off, Dealership on. |
| TC-038 | PASS | Both other modules off. |
| TC-039 | PASS | Combined-mode public registration without invitation remains 404. |
| TC-040 | PASS | Expired invitation denied. |
| TC-041 | PASS | Existing invited account signs in; PMS login tests pass. |
| TC-042 | PASS | Existing shared-auth and other-module registration tests pass in full suite. |
| TC-043 | PASS | Cycle detail HTML renders timeline component. |
| TC-044 | PASS | Rendered component carries actual cycle dates and configured phase data. |
| TC-045 | NOT RUN | No browser surface was available for small-width overlap inspection. |
| TC-046 | PASS | Dashboard link has active state in rendered HTML. |
| TC-047 | PASS | QA page omits invitation route and team endpoint returns 403. |
| TC-048 | PASS | Team role, search, and status filters return scoped results. |
| TC-049 | PASS | Project role escalation and self-change requests rejected. |
| TC-050 | NOT RUN | Copy control renders, but clipboard interaction could not be exercised without a browser. |
| TC-051 | PASS | HTML field error and old form input are preserved; JSON validation errors asserted. |
| TC-052 | NOT RUN | Light/dark and desktop/tablet/mobile visual inspection could not run without a browser. |

## G. UI and UX

The cycle screen has a scale switcher, actual-versus-planned bars, clear metrics, phase links and scrollable chart. The sidebar groups overview, project management, delivery, collaboration and administration, and saves its collapsed state. Team pages show real membership, invitation and workload data with filters and member details. Invitations expose issuance, status, copy, revoke and reissue. The registration page displays invitation role/scope and prefilled email/referral data. Ticket and management forms show validation and typed destructive-action confirmation. The new CSS supplies responsive and dark-theme rules, but visual browser verification remains outstanding.

## H. Remaining issues and limitations

1. The original user's exact REQ failure was not reproducible from the supplied details. If it persists, capture the failing request ID, status, response and contemporaneous server exception. The confirmed attachment feedback defect is fixed and protected by form tests.
2. A true race test for simultaneous invitation acceptance and reference generation was not run. Both flows use transactional row locks and uniqueness constraints on MySQL; SQLite feature tests cannot prove lock behavior under contention.
3. The browser automation inventory exposed no browser, and the in-app browser was unavailable. Clipboard behavior, layout at representative widths, and light/dark screenshots were therefore not verified interactively.
4. Phase records have no dedicated phase-owner or dependency relationship. The timeline truthfully displays the cycle owner; it does not invent phase owners or dependencies.
5. PMS invitation registration is implemented on the web route. The existing public API registration route keeps its original public-registration policy and does not accept invitation tokens.
6. Project soft delete retains children for recoverability. Existing administrator-only permanent purge is destructive and should be operated only with a data backup.

## I. System log

`config/pms.php` now records Project Management **2.2.0**, dated **2026-10-18**, with separate **FIX**, **ENHANCEMENT**, and **NEW** entries. The existing System Log page reads this configuration.
