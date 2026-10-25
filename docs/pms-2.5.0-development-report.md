# PMS 2.5.0 development report

19 October 2026

## Architecture and data

Organization cycles in `pm_global_cycles` are the delivery schedule used by the Development selector, cycle work view, and Release Management. The Development directory retains historical project execution plans in `pm_cycles`; its visible creation and duplication forms were removed. Legacy API creation remains available for compatibility and creates only a project execution plan, not an organization cycle. It can accept an existing `global_cycle_id` after the project has been assigned to that master; assigning a ticket to such a plan also assigns the same master. New organization cycles are created in Cycle Management. Projects and tickets are associated explicitly with the master cycle. A project association does not silently import its tickets.

`2026_10_19_000001_unify_pms_delivery_and_people.php` adds notes and production targets to master cycles, configurable default stage durations, a nullable master cycle link on legacy project cycles, explicit ticket stage links, department and position master tables and relationships, profile and invitation foreign keys, ticket blockers with immutable event history, and ticket contributors. It seeds the six requested departments and job positions. Existing free text profile values are linked only on exact known matches, including the legacy QA Engineer alias. A legacy project cycle is linked to a master cycle only if all its assigned tickets explicitly name the same master cycle. Ambiguous cycles remain unlinked; Organization Settings lists them for administrator review. A reconciliation action requires an existing project association and a written reason, assigns only unassigned tickets, and rejects conflicting ticket cycle links. No cycle names are used for automatic merging. Existing project, ticket, comment, attachment, release, and audit identifiers are preserved.

One workspace is assumed because PMS has no organization or tenant ownership key. Company level isolation requires a separate schema and authorization change before multiple companies share this deployment.

## Cycle and release rules

Cycle ticket progress is `closed visible tickets / all visible tickets`. Stage progress uses the same formula over tickets explicitly assigned to that stage; a stage with no tickets displays 0%. Stage assignment is cleared when a ticket moves to a different master cycle or is removed. Workflow status, stage, and blocker state are separate fields. Stage scheduling and manual phase signoff remain available in the existing master cycle workflow.

Release Management counts visible, distinct tickets. A release is **Blocked** only when one of its tickets has an unresolved manual blocker or unresolved `qat_dependency` link. Failed readiness gates without a blocker report **At Risk**. Manual blockers require a category and reason; resolving requires a note. Reopening and edits are transactionally recorded in blocker history and the PMS audit log. Dependency blockers are derived from the existing relationship table and clear when the linked ticket reaches a completion status. Assigning a ticket from Unassigned Work Items updates `global_cycle_id`, ticket history, and cycle history.

## People and invitations

Departments and job positions are separate database managed entities with an active many to many relationship. Registration and invitation values are checked against that relationship on the server. A job position is an organizational attribute and never grants a workspace role. Administrators can manage the master records and update a member's department and position. Position codes remain fixed after creation so a renamed Developer, QA, or Project Manager position keeps its assignment eligibility. Legacy profiles without a master position ID use the established name rules.

Project members can be added in one request. Tickets retain their single developer and QA owner fields and can have multiple eligible developer or QA contributors. Bulk writes validate every selected active member, run in a transaction, and use uniqueness constraints to prevent duplicates.

New invitations require a recipient email, generate a distinct 256 bit random token per person, store a hash and encrypted copy, allow one use, and use `/pms/invite/{token}`. The earlier query parameter registration link remains recognized for existing invitations. Accepted, expired, and revoked links show distinct errors. Reissued links revoke the old token. Registration applies the invitation's role, department, position, name, and project scope on the server; submitted registration values cannot elevate those grants. Existing account acceptance remains supported. Links are copied from the PMS invitation screen; email delivery is not integrated.

## Added or extended HTTP interfaces

Browser routes use `/backoffice/project-management` unless stated otherwise. Existing PMS JSON routes use `/api/backoffice/pms` and the existing authentication middleware.

| Method and path | Purpose |
| --- | --- |
| `GET /pms/invite/{token}` | Open a personal invitation |
| `GET /organization-options` | List active departments and eligible positions |
| `GET /organization-settings` | Administrator master data |
| `POST, PUT /organization-settings/departments[/{id}]` | Create or edit department and position links |
| `POST, PUT /organization-settings/positions[/{id}]` | Create or edit position and department links |
| `PUT /organization-settings/profiles/{user}` | Set a member's department and position |
| `POST /organization-settings/legacy-cycles/{cycle}/reconcile` | Link a reviewed historical project plan and its unassigned tickets to an existing master cycle |
| `POST /invitations/batch` | Create distinct personal invitations |
| `POST /delivery-cycles/{cycle}/tickets/{ticket}/stage` | Assign or clear an execution stage |
| `POST /releases/assign-cycle` | Assign an unassigned ticket to an existing master cycle |
| `GET, POST /tickets/{ticket}/blockers` | Inspect or create blockers |
| `PUT /tickets/{ticket}/blockers/{id}` | Edit an active blocker |
| `POST /tickets/{ticket}/blockers/{id}/resolve` | Resolve with a required note |
| `POST /tickets/{ticket}/blockers/{id}/reopen` | Reopen with a required reason |
| `GET, POST /tickets/{ticket}/contributors` | Inspect or bulk add contributors |
| `DELETE /tickets/{ticket}/contributors/{user}` | Remove a contributor |

All writes use existing PMS authentication, project visibility, and role checks. Validation errors use Laravel's existing 422 response for JSON requests. The previous versioned automation API is unchanged.

## UI

Development offers a selector for existing master cycles. Cycle Management owns master creation, schedule defaults, and recurrence. Release Management now shows master cycles, counts derived from visible tickets, blocked counts, and a paginated unassigned ticket list. Cycle details display project and ticket links, blocker state, explicit stage assignment, and ticket based progress. Ticket details provide manual blocker controls, dependency sources, history, and contributor assignment.

Project Details has responsive header actions, scrollable tabs, contained media, and ticket cards at narrow widths. The people selector supports name and email search, department and position filters, removable selected chips, clearing, empty states, and a confirmation for multiple people. Organization Settings supports local department and position search and status filters. An isolated rendered project with three real ticket fixtures was inspected in headless Chrome at 320, 375, 768, and 1440 CSS pixels. Overview and ticket tabs had no page level horizontal overflow; narrow header, description, and ticket cards were visually reviewed. Touch input, real devices, discussions with long content, and large attachment galleries still need manual QA.

## Deployment and rollback

Back up the production database, then run `php artisan migrate`, `php artisan optimize:clear`, `php artisan view:cache`, and `npm run build`. No new environment variable is required. PMS loads its additional CSS and JavaScript directly through the existing layout. Review unlinked historical `pm_cycles.global_cycle_id` values in Organization Settings using ticket relationships as evidence; do not link by name alone. Review legacy free text department and job position values that did not match seeded master records.

Rollback of the new migration drops new blocker, contributor, department, and position records and their history. Export those records before database rollback. A code rollback should retain the additive database schema when possible. Existing project and ticket records are not rewritten by migration rollback.

## Verification

- Full Laravel run after the code based eligibility change: 251 passed, 1 skipped, 6,246 assertions. No failures. The later assignee label edit passed the focused enhancement suite and Blade compilation.
- Final focused enhancement run: 12 passed, 146 assertions, including renamed position assignment screens. After the reconciliation and view changes, workspace redesign passed 8 tests/115 assertions, management flows passed 11/163, and global cycles passed 5/74.
- Blade compilation, PHP syntax checks, route registration, and `npm run build` passed.
- Headless Chrome visual inspection used actual 320, 375, 768, and 1440 CSS pixel device metrics for Project Details overview and ticket tabs. Each measured page width stayed within its viewport. The 320 pixel description and ticket cards were inspected below the fold.
- The generated visual QA screenshots and Chrome profiles under `storage/app`, and `pms-enhancements-suite.log` at the project root, remain because automatic approval review rejected their deletion. Temporary visual QA source scripts were removed.

## Remaining scope

The Development page still lists historical project execution plans and its legacy API can create those plans. The release cycle work view has search, ticket pagination, stage, blocker, type, status, priority, project, department, assignee, and date filters, plus common sorts. Project rows are displayed alongside the ticket table but are not part of its paginator. The app has no company tenancy key. Real device and accessibility testing and invitation email delivery remain future work.
