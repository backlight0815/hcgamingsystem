# PMS 2.8.0 enhancement report

## Architecture and scope

The module is Laravel 10 with Blade, direct `public/backend/assets` CSS and JavaScript, MySQL production storage, a dedicated `project_management_users` identity linked to `users`, project memberships in `pm_project_members`, and private local-disk attachments. The existing `PmWorkItem` primary key and `issue_key` remain. Ticket, invitation, project, release, cycle, profile, and audit tables are reused. There is no tenant/organization foreign key in this PMS; the account detail identifies the application workspace, and project memberships remain the access boundary. The TradingHub and Dealership routes and authentication were not changed by this release.

The working tree already contained unrelated staged and untracked application work. This release changes PMS files in place and leaves that work intact. No production database or server was modified.

## Nine enhancements

| Area | Implementation | Main files |
| --- | --- | --- |
| Priority | Uses the existing P0/P1 = Critical, P2 = High, P3 = Normal, P4 = Low mapping. Ticket headers show classification and status; details and authorized edit forms retain priority. New tickets default to Normal. Lists, boards, cycle and release summaries, and CSV show labels. The v1 API retains its lowercase readable priority and `priority_code` contract. | `app/Support/PmsPriority.php`, `app/Http/Controllers/Pms/TicketController.php`, `resources/views/pms/ticket.blade.php`, `resources/views/pms/partials/ticket-edit.blade.php`, PMS list and board views |
| Invitations | New single-person links may omit email. An explicit `email_restricted` flag controls matching. A project and role are required; old invitations remain restricted by default. Existing hashed 256-bit tokens, encryption for link retrieval, expiry, revocation, single use, and transactional acceptance are retained. | `InvitationController.php`, `PmsInvitationService.php`, `AuthController.php`, `resources/views/pms/invitations.blade.php`, `resources/views/pms/register.blade.php` |
| Attachments | Reused private ticket and project attachment storage, validation, thumbnails, authorized previews/downloads/deletes, the evidence gallery, and ticket activity/audit recording. Creation and later multiple uploads remain supported for all ticket categories. User-facing notifications now use canonical references. | `PmsAttachmentService.php`, `TicketAttachmentController.php`, `PmTicketAttachment.php`, gallery views and scripts |
| Account Management | The Team administration route is now the Account Management directory with search, combined filters, status metrics, a detail profile, registration source, activity, and project assignment controls. Only global Super Admin can edit status or membership here. Inactive and suspended accounts lose PMS API tokens and cannot use PMS; the last active Super Admin is protected. | `TeamController.php`, `resources/views/pms/team*.blade.php`, `resources/views/pms/layout.blade.php`, `public/backend/assets/css/pms-enhancements.css` |
| QAT project assignment | The server requires an authorized project for QAT creation. An invited QA with exactly one current membership gets an auto-selected locked field; direct-registration QA keeps the auto-selected field editable. With multiple memberships, only authorized projects appear. Release and cycle options follow the selected project. Zero-membership QA sees a blocking explanation. Existing non-QAT unassigned intake remains available to authorized roles. | `TicketController.php`, `resources/views/pms/ticket-create.blade.php`, `public/backend/assets/js/pms-delivery.js` |
| Ticket references | Adds unique lowercase `sfreq`, `sfenh`, `sfprd`, `sfqat`, and `sftsk` references. Existing tickets are backfilled in ID order. Creation locks the existing per-type sequence row. Routes generate canonical references; numeric and legacy issue-key lookups remain authorized and redirect to canonical URLs. Search, links, notifications, CSV, and v1 ticket responses include the new reference. Project records are separate from the ticket entity, so `sfprj` is not issued. | `PmsTicketReference.php`, `PmWorkItem.php`, `TicketController.php`, `IssueController.php`, v1 API controller, PMS views |
| Obsolete ticket fields | Removed Target Completion and Target Minutes inputs and details from ticket create/edit/detail pages. Existing `due_date` and `estimate_minutes` columns and API compatibility remain for historical data and scheduling code. | Ticket Blade views |
| Responsive UI | Reused the existing mobile sidebar, responsive form/grid/table CSS, touch-friendly gallery, and mobile ticket layout. Added Account Management cards and filters for narrow screens. | `resources/views/pms/layout.blade.php`, account views, `public/backend/assets/css/pms-enhancements.css` |
| Cycle status | Adds Draft, Internal Testing, QAT, and On Hold to the existing Planned, Active, Closed, and Cancelled lifecycle. A project manager or administrator can use guarded transitions, reasons, version checks, unresolved-ticket confirmation, history, and audit. Ticket status is not changed by the new status action. | `CycleController.php`, `PmCycle.php`, `PmsStatusRegistry.php`, `resources/views/pms/cycle.blade.php` |

## Database changes and safety

1. `2026_10_20_000004_add_pms_invitation_email_mode.php` adds `pms_invitations.email_restricted BOOLEAN NOT NULL DEFAULT TRUE`. Existing email invitations retain matching behavior; new unrestricted links explicitly store false.
2. `2026_10_20_000005_add_pms_ticket_references.php` adds nullable unique `pm_work_items.reference VARCHAR(40)`. The unique index allows MySQL to enforce global uniqueness. The migration reserves already valid `sf...` issue keys, scans tickets in ascending ID order, assigns missing references, and advances the existing `pm_ticket_sequences` counters beyond all allocated references. It preserves IDs, `issue_key`, attachments, relationships, and ticket history. The column stays nullable to make the additive migration safe on an existing table; normal creation paths assign a reference transactionally.
3. `2026_10_20_000006_add_pms_account_phone.php` adds nullable `project_management_users.phone VARCHAR(40)`.

No table or historical field is dropped. On a large production copy, measure the reference backfill and unique-index time before rollout. Do not run `migrate:fresh` or roll back the reference migration after users receive canonical links. Restore the reviewed database backup and matching code release if rollback is required.

## HTTP and API changes

All `/backoffice/project-management` routes use the existing authenticated PMS middleware and CSRF protection for browser forms. JSON callers receive Laravel validation errors with HTTP 422, forbidden operations receive 403, and inaccessible ticket lookups receive 404. Web form success redirects; JSON responses retain existing route-specific shapes rather than introducing a breaking envelope.

| Method and path | Authorization and request | JSON success |
| --- | --- | --- |
| `POST /backoffice/project-management/invitations` | PMS administrator or manager of `project_id`. Required `project_id`, `role`, future `expires_at`, `max_uses=1`; optional `email`, `email_restricted`, `invited_name`, department and position. | 201 `{ "code": "PM-INV-2026-000001", "email": null, "link": ".../pms/invite/<token>" }` |
| `POST /backoffice/project-management/invitations/batch` | Same scope; `members` with emails, role, project, expiry. Each link is one use and email restricted. | 201 `{ "invitations": [...] }` |
| `POST /backoffice/project-management/register` | Valid unconsumed invitation token; `invite`, `name`, `email`, `password`, confirmation. Project and role come from the stored invitation. | Existing registration redirect/session response. |
| `POST /backoffice/project-management/register/accept` | Authenticated PMS user with a valid invitation; `invite`. | 200 `{ "accepted": true }` |
| `GET /backoffice/project-management/team` | PMS administrator or manager scoped to managed projects. Optional `q`, `status`, `department_id`, `job_position_id`, `project_id`, `role`, `sort`; 20 per page. | Existing `profiles`, `metrics`, `memberships`. |
| `GET /backoffice/project-management/team/{profile}` | Same scoped visibility. | Existing profile, projects, metrics, recent tickets. |
| `PUT /backoffice/project-management/team/{profile}` | Global Super Admin; required `status` (`active`, `inactive`, `suspended`, `rejected`); optional `phone`, department, job position, title, workspace role. | 200 updated profile JSON. |
| `POST /backoffice/project-management/team/{profile}/projects` | Global Super Admin; required authorized `project_id`, `role`. | 200 `{ "assigned": true }` |
| `DELETE /backoffice/project-management/team/{profile}/projects/{project}` | Global Super Admin; owner/self safeguards apply. | 200 `{ "removed": true }` |
| `POST /backoffice/project-management/tickets` | Existing category role plus authorized project scope. `classification_key`, `project_id`, category fields, optional release/cycle and attachments. QAT requires project. | Existing 201 ticket JSON now includes `reference`. |
| `GET /backoffice/project-management/tickets/{reference}` | Viewer of that ticket. Numeric and legacy keys redirect only after authorization. | Ticket page or existing JSON representation. |
| `POST /backoffice/project-management/tickets/{reference}/attachments` | Ticket commenter; multipart `attachments[]` within configured size/type limits. | 201 attachment list. |
| `GET /backoffice/project-management/tickets/{reference}/attachments/{attachment}` | Ticket viewer; optional `preview=1`. | Private file stream. |
| `DELETE /backoffice/project-management/tickets/{reference}/attachments/{attachment}` | Ticket manager. | 200 `{ "deleted": true }` |
| `POST /backoffice/project-management/cycles/{cycle}/status` | Manager of cycle project; required `status`, `lock_version`; reason for hold, close, cancel, reopen; `confirm_unresolved=1` to close with unresolved tickets. | 200 updated cycle JSON. |
| `GET /api/v1/project-management/getProjectTickets` and existing ticket update API | Existing PMS bearer scope and project access. | Ticket data adds `ticket_reference` and canonical `ticket_url`; legacy `ticket_key` remains. |

Example JSON request for an open invite:

```json
{"project_id":12,"role":"QA","expires_at":"2026-11-01 12:00:00","max_uses":1}
```

Example cycle transition:

```json
{"status":"Internal Testing","lock_version":3}
```

Example 422 validation response follows Laravel's current API contract:

```json
{"message":"The project id field is required.","errors":{"project_id":["The project id field is required."]}}
```

For Postman, authenticate with the existing PMS session or configured bearer API token and send `Accept: application/json`; browser routes also require the session CSRF token. For JMeter, capture the login cookie and CSRF token before posting to browser routes. Do not store invitation tokens in shared test logs.

## Verification and limits

The focused PMS suite passed **95 tests and 1,505 assertions** after the QAT/intake form refinement. The full application suite passed **270 tests and 6,663 assertions**, with one existing skip; it ran before the final access guard refinement. After that guard change, `PmsRequestedEnhancementsTest`, `PmsIdentityAndAccessTest`, and `GlobalSuperAdminAccessTest` passed independently, including suspended Super Admin denial and no-profile Super Admin access. `PmsRequestedEnhancementsTest` passed **8 tests and 71 assertions**. Tests use SQLite in test mode and cover invitation replay/scope, reference redirects and authorization, invited and direct QA selection, account suspension/token revocation, cycle status audit, QAT workflow, attachment formats and private previews, and other regressions. PHP lint passed for 66 PMS-related files, JavaScript syntax check passed, and `php artisan view:cache` succeeded.

| Test | Expected | Actual | Result |
| --- | --- | --- | --- |
| `PmsRequestedEnhancementsTest::test_open_invitation_accepts_own_email_once_and_grants_only_the_selected_project` | Project is required; an email-free link grants only stored scope and cannot replay. | Validation, own-email registration, scope, and replay assertions passed. | Pass |
| `::test_reference_urls_redirect_only_after_authorization_and_priority_is_outside_header` | Stable `sfreq` URL, authorized numeric redirect, no outsider disclosure. | All assertions passed. | Pass |
| `::test_existing_requirement_intake_without_a_project_still_works` | Legacy unassigned REQ intake remains available with Normal priority. | Form and creation assertions passed. | Pass |
| `::test_invited_single_project_locks_selector_and_membership_change_unlocks_it` and `::test_normal_qa_with_one_project_can_edit_selector_and_zero_membership_blocks_creation` | Invitation and direct registration use different locking; unauthorized project is rejected. | All assertions passed. | Pass |
| `::test_suspension_rejects_access_and_reactivation_preserves_account` and `::test_account_status_requires_super_admin_and_protects_the_last_one` | Access and tokens are blocked while suspended; only Super Admin may change status; last admin remains active. | All assertions passed. | Pass |
| `::test_cycle_status_is_audited_and_does_not_change_ticket_status` | Guarded transition, unresolved warning, actor/history display, independent ticket status. | All assertions passed. | Pass |
| `PmsDeliveryWorkflowTest` evidence cases | Images, PDF, Office files, multiple uploads, unauthorized access, and dangerous files follow private storage rules. | Existing feature cases passed in the PMS suite. | Pass |
| `PmsWorkspaceRedesignTest` private image/PDF cases | Authorized thumbnails and previews render while outsiders cannot retrieve files. | Existing feature cases passed in the PMS suite. | Pass |
| Full `php artisan test` | PMS and non-PMS regressions remain green. | 270 pass, one skipped, 6,663 assertions. | Pass |
| Browser viewport and live MySQL concurrency checks | Mobile usability and concurrent numbering hold on production-like infrastructure. | Not run; staging acceptance remains required. | Open |

Automated browser viewport checks were not available in the repository's current test framework. CSS responsiveness and Blade rendering were reviewed in code; the 360px mobile, tablet, desktop, touch, keyboard, upload, and modal checks remain staging acceptance checks. Concurrent ticket creation is protected by a database row lock and a unique index, but a real MySQL concurrency/load test was not run here. The production example URL could not be accessed from this environment, so production UI and data were not inspected.

## Production deployment

1. Create a cPanel file and database backup, including private `storage/app` attachment files. Restore it to staging and record ticket, invitation, account, membership, attachment, and cycle counts.
2. Deploy the reviewed 2.8.0 source and `public/backend/assets` to staging. Confirm its PHP version, Laravel dependencies, MySQL connection, writable `storage` and `bootstrap/cache`, and private local filesystem disk. PMS assets are served directly; this change needs no Vite build.
3. On staging, run `php artisan migrate --pretend`, then `php artisan migrate --force`. Confirm every `pm_work_items` row has a unique nonnull reference, and compare counts and relationships with the backup. Run `php artisan test --filter=Pms` against the staging test database only; never use destructive test refresh against live data.

   ```sql
   SELECT COUNT(*) AS tickets, SUM(reference IS NULL) AS missing_references FROM pm_work_items;
   SELECT reference, COUNT(*) AS copies FROM pm_work_items GROUP BY reference HAVING COUNT(*) > 1;
   SELECT prefix, next_number FROM pm_ticket_sequences ORDER BY prefix;
   ```

4. Exercise a scoped invitation, QAT creation, numeric redirect, attachment preview, suspension, cycle transition, and the 360px/tablet/desktop screens. Verify old TradingHub and Dealership sign-in and main routes.
5. Schedule a production maintenance window. Enable maintenance mode with `php artisan down`, deploy the same reviewed files, run `composer install --no-dev --prefer-dist --optimize-autoloader`, `php artisan migrate --force`, `php artisan optimize:clear`, `php artisan config:cache`, `php artisan view:cache`, then `php artisan up`. Use the hosting PHP binary and release directory in cPanel's terminal or deployment job. Confirm `APP_KEY` and mail/session settings remain unchanged. **Do not run `route:cache` for this release:** the current application has a duplicate `trading.leaderboard.index` route name outside PMS and `route:cache` fails. `php artisan route:clear` succeeds; ordinary uncached routing works. Resolve that duplicate in its owning module before enabling route caching.
6. Run the same read-only data checks and manual smoke paths in production. On failure before opening traffic, restore the matching code and database backup; preserve attachment storage. Do not issue `migrate:rollback` blindly because dropping ticket references would invalidate issued links.

No deployment or production migration was performed as part of this work.
