# PMS 2.6.0 implementation report

19 October 2026

## A. Code review findings

- Business Analyst writes in `ProjectController`, `TicketController`, `IssueController`, and the v1 automation API changed published project, REQ, and ENH/EHN records directly. Existing `pm_ticket_reviews` tracked initial requirements review but did not store proposed edits or cover projects and deletion.
- `pms_profiles.job_title` was a separate professional title, but My Profile did not allow editing it. Organizational `job_position_id` was already the correct master data relationship and remains separate from workspace permissions.
- Registration rendered plain department and position lists. A position linked to two departments appeared twice, and the selector did not offer search.
- Lifecycle statuses were free text in delivery, approval, identity, and wiki tables. A direct VARCHAR to integer conversion would reinterpret historical rows and break string API clients. No company or tenant key exists in this PMS; project membership is its current access boundary.

## B. Implemented enhancements

- Business Analyst project and REQ/ENH/EHN updates and deletion requests now create versioned proposals in `pm_change_requests`. The published row remains unchanged until the assigned Project Manager or an authorized administrator approves. Rejecting preserves it. Submission, approval, rejection, and cancellation are audited; ticket history and participant notifications are retained. Pending duplicates, stale versions, self approval, and unauthorized decisions are rejected.
- Added a paginated review page with before and proposed values, decision comments, and history on project and ticket pages. Existing initial requirements review remains a separate workflow.
- My Profile can edit `job_title` without changing organizational position or role. Unrelated edits preserve phone and title. New registrations and invitations populate the title from the selected position if one is not supplied.
- Registration has reusable searchable department and position fields, dependent filtering, native select keyboard behavior, error text, focus styles, and a one column mobile layout. Active options come from master tables, and a position linked to multiple departments appears once. Administrators can already maintain those tables through Organization Settings.
- Added `PmsStatusRegistry`, model dual writes, code aware ticket transition checks, numeric API inputs, a read only `pms:audit-statuses` command, and a dry run by default `pms:backfill-status-codes` repair command. Existing string status columns and API fields remain available for compatibility.

## C. Database changes and status mapping

`2026_10_20_000001_create_pms_change_requests.php` creates the proposal table with subject, revision, original snapshot, proposed changes, lock version, integer review code, reviewer, decision comment, timestamps, and access indexes. `2026_10_20_000002_add_pms_status_codes.php` adds nullable indexed `status_code` columns and backfills recognized values without rewriting any legacy status text. It covers `pm_projects`, `pm_work_items`, `pm_releases`, `pm_cycles`, `pm_global_cycles`, `pm_ticket_reviews`, `pms_profiles`, `project_management_users`, `pms_registrations`, `pm_wiki_pages`, and `pm_wiki_revisions`. Unknown, null, and blank values remain unchanged with a null code for review.

Codes are meaningful only with their domain. The authoritative definitions are in `app/Support/PmsStatusRegistry.php`:

| Domain | Code to legacy status |
| --- | --- |
| Project | 1 Cancelled; 2 Planned; 3 Active; 4 Approved; 5 On Hold; 6 Completed |
| Ticket | 1 Cancelled; 2 Planned; 3 Development In Progress; 4 Open; 5 Deployment; 6 QAT In Progress; 7 QAT Dependency; 8 Reopen; 9 QAT Done; 10 Production Done; 11 In Progress; 12 Blocked; 13 Ready for Internal Testing; 14 Internal Testing; 15 QAT Testing; 16 Completed; 17 Reopened; 18 Code Review; 19 Ready for QAT; 20 New; 21 Triaged; 22 Assigned; 23 Fixed; 24 Ready for Retest; 25 Retesting; 26 Verified; 27 Closed; 28 Duplicate; 29 Rejected; 30 Resolved; 31 Done; 32 Released |
| Release | 1 Cancelled; 2 Planned; 3 Development; 4 Internal Testing; 5 QAT Testing; 6 Stabilization; 7 Ready for Release; 8 On Hold; 9 Approved; 10 Released; 11 Completed |
| Project cycle | 1 Cancelled; 2 Planned; 3 Active; 4 Closed |
| Organization cycle | 1 Cancelled; 2 Upcoming; 3 Active; 4 Completed; 5 Delayed |
| Ticket review | 1 pending; 2 approved; 3 changes_requested; 4 rejected |
| Membership and PMS identity | 1 pending; 2 active; 3 inactive; 4 rejected |
| Registration | 1 pending; 2 approved; 3 inactive; 4 rejected |
| Wiki page and revision | 1 draft; 2 published |
| Change request | 1 pending; 2 approved; 3 rejected; 4 cancelled (`PmsReviewStatus`) |

Priority, QAT resolution, blocker state, deletion, and publication history are distinct concepts and were not folded into ticket lifecycle status. The ticket mapping recognizes legacy capitalization such as `QAT in Progress` as the same code as `QAT In Progress` while preserving stored text.

Read only local inventory before migration: 2 projects (`Planned`); 3 tickets (`Planned` 2, `Development In Progress` 1); 1 release (`Planned`); 2 project cycles (`Active`); 1 organization cycle (`Active`); 1 ticket review (`pending`); 3 profiles (`active`); 3 PMS identities (`active`); 2 registrations (`approved`); 1 wiki page and 1 revision (`published`). All 20 sampled rows map to defined codes; no unknown values were found. The configured local MySQL database subsequently recorded both additive migrations in batch 72 during verification. A post-migration `pms:audit-statuses --strict` passed: all 20 sampled rows have matching codes, with zero records needing review. The migration runner was not identified; a separate test probe confirmed that Laravel feature tests use in-memory SQLite. No remote production environment was contacted. Production backfill counts are not known.

## D. API changes

The v1 paths are under `/api/v1/project-management` and keep their existing envelope. `GET /getProjectInfo` still accepts `project_id` or `project_key`; returned `data.status` remains a string and now also includes `data.status_code` and `data.status_label`. `POST /updateProjectInfo` accepts optional `status_code` on insert or update. BA update or delete returns HTTP 202 with `code=PROJECT_REVIEW_PENDING`, `project_id`, and `review_id`; manager writes retain 200 or 201. `expected_version` guards update and delete. `GET /getProjectTickets` accepts `status_code` as a filter, and ticket items include numeric code and label. `POST /updateTicketInfo` accepts `status_code` for authorized workflow transitions; BA REQ/ENH/EHN edit and delete requests return HTTP 202 with `code=TICKET_REVIEW_PENDING` and `review_id`. Conflicting or invalid codes return 422. Existing string inputs and responses remain supported.

Review APIs use `/api/backoffice/pms/change-reviews`: `GET` lists visible requests with optional `status`; `POST /{review}/decision` accepts `{ "decision": "approved|rejected", "comment": "..." }`; `POST /{review}/cancel` cancels the submitter's pending request. Web equivalents use `/backoffice/project-management/change-reviews`. They require the existing PMS authentication and project authorization. A second decision returns 409.

## E. Verification

| Area | Result | Evidence |
| --- | --- | --- |
| BA review and ticket deletion | PASS | Feature tests for separate proposals, approval, rejection, resubmission, duplicate and stale decisions, and unchanged published records |
| Job Title updates | PASS | Profile and registration tests confirm editable title and preservation on unrelated updates |
| Registration UI | PASS | Blade compilation, JavaScript syntax check, rendered option loading, and department/position registration test; real device interaction remains for manual QA |
| Status migration | PASS locally and in tests | All registry definitions round trip; model writes, dry run and repeatable repair, unknown value preservation, and strict audit failure are tested. Local MySQL strict audit passed for 20 rows; production backfill remains pending. |
| API compatibility | PASS | Existing automation API tests plus numeric status response/input/filter assertions |
| Regression tests | PASS | Full Laravel run after core changes: 259 passed, 1 skipped, 6,509 assertions; final added review test passed in the targeted run |
| Security checks | PASS in feature tests | BA self approval, outsider decisions, missing reviewer, stale version, duplicate proposals, and direct bearer API bypass checks; final targeted review run passed 8 tests and 242 assertions |

## F. Deployment, recovery, and remaining risks

Before production deployment, back up the database and run `php artisan pms:audit-statuses` on a staging copy with production data. Review every unmapped or blank value and its business meaning; add an explicit mapping if appropriate. Run `php artisan migrate --pretend`, then the additive migrations on staging **before activating the new PHP code**, then `php artisan pms:audit-statuses --strict`. If old code wrote recognized statuses during a staged rollout, preview `php artisan pms:backfill-status-codes`, apply with `--apply`, and repeat the strict audit. That repair only fills null codes for known strings; it never changes unknown strings or overwrites a conflicting nonnull code. Compare record counts and status distributions before and after, exercise the PMS dashboards and APIs, and deploy only through the normal release process. The configured local MySQL database has run both migrations; no production environment was contacted. No destructive string column conversion is included.

Rollback of code should retain the additive columns where possible so that new review history is preserved. If schema rollback is required, export `pm_change_requests` first; rolling back its creation migration drops that new history. The legacy string status columns remain intact and can continue serving older code. Do not drop them until all consumers and historical unknown values have been reviewed.

The integer columns are dual written and ticket transition guards use codes, while several existing dashboard SQL filters still read legacy strings. This compatibility period preserves their existing results; the final removal of legacy string logic depends on production data validation and consumer testing. Browser interactions at mobile widths, assistive technology behavior, and a staging copy performance check remain manual release checks. The repository already contained extensive unrelated dirty files; they were left untouched.

## G. System log

`config/pms.php` now records PMS version 2.6.0 entries for review workflow and status/registration enhancements, with prior entries retained.
