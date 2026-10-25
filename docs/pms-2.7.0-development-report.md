# PMS 2.7.0 — Multiple PICs and ticket On Hold

## Architecture and data

The existing `pm_ticket_contributors` table stores PICs with `assignment_type=pic`. Its unique `(issue_id,user_id,assignment_type)` constraint prevents duplicates. Developer and QA owner fields, and their contributor relationships, retain their prior meaning. No contribution percentage is stored or required. An empty PIC set is valid for tickets that can be unassigned.

Migration `2026_10_20_000003_add_pms_ticket_hold_details.php` adds nullable hold metadata to `pm_work_items`: previous status code, reason, linked blocker, actor, and timestamp. It adds a separate blocker status code with 1 Open, 2 In Progress, 3 Resolved, and 4 Cancelled; historical resolved blockers backfill to 3. Ticket On Hold uses code **33**, unused in the ticket status domain. Existing ticket status text remains for old clients, and model writes keep `status_code` aligned. No existing assignment, blocker, or lifecycle row is deleted by this migration.

## Behavior

- `GET /api/backoffice/pms/tickets/{ticket}/pics` returns current PICs and eligible project members. `PUT` replaces the complete set using `assignee_ids` and `lock_version`; `DELETE /{user}` removes one PIC. Web routes at `/backoffice/project-management/tickets/{ticket}/pics` expose the same controller. New members must be active PMS project members. Existing inactive assignments can remain selected until explicitly removed. Updates are transactional, version checked, audited, and shown in the ticket activity feed. Only new PICs receive assignment notices.
- The ticket page presents a searchable checkbox picker with initials, position, department, selected count, clear, Save, Cancel, native keyboard operation, and a mobile layout. Existing Developer and QA owner fields stay separate. Historical Developer and QA contributors remain available for viewing and removal.
- Reporting a blocker offers **Put ticket On Hold** or **Keep current status**. The default keeps the current status. The direct `POST /tickets/{ticket}/hold` route can associate an existing active blocker. Pause records the previous status, actor, timestamp, reason, and linked blocker. `POST /tickets/{ticket}/resume` defaults to the previous valid working status. If that status is no longer valid, a different working status can be selected. Outstanding manual and linked dependency blockers require an explicit override and reason; the blockers are retained.
- PMs and administrators can pause or resume directly. A BA on a REQ/ENH/EHN ticket uses the existing change review queue; the published status stays unchanged until PM approval. Other roles can report blockers within their existing scope but cannot pause or resume the ticket directly. Stale versions, unauthorized transitions, invalid blocker links, duplicate PIC IDs, and out-of-project assignments are rejected.
- Blocker edit retains category, reason, details, related ticket, owner, and expected date. Open and In Progress are editable states; Resolve and Cancel are distinct actions with notes and history. Reopen sets the state back to Open. Blocker state is independent of ticket workflow state.
- The overview and ticket filters distinguish On Hold tickets, tickets with active manual or linked dependency blockers, overdue manual blockers, and tickets with a resume event. Each count is by distinct ticket. Deadline and SLA calculations are unchanged.

## API examples

```json
{"assignee_ids":[5,8,12],"lock_version":3}
```

`PUT /api/backoffice/pms/tickets/123/pics` returns `success`, `message`, and `data` containing `ticket_id`, current `assignee_ids`, expanded `assignees`, and `lock_version`. An approved BA proposal returns HTTP 202 while pending.

```json
{"lock_version":3,"reason":"Waiting for API response","blocker_id":45}
```

`POST /api/backoffice/pms/tickets/123/hold` pauses the ticket. `POST /api/backoffice/pms/tickets/123/resume` accepts `{"lock_version":4,"status":"Planned"}`. If unresolved blockers remain, add `"override_unresolved":true` and a nonempty `reason`. The existing bearer API `updateTicketInfo` accepts ticket `status_code:33` with a reason to request On Hold, and routes resume through the same service. It rejects mixed detail/status changes so review and version checks remain clear.

## Verification and rollout

Focused Laravel feature tests cover PIC set replacement and removal, outsider rejection with unchanged assignments, blocker creation without pause, On Hold code and previous status, unresolved blocker override, distinct blocker states, BA → PM review, and rendered ticket controls. Legacy blocker API shape was preserved for creation without pause. Blade compilation and JavaScript/PHP syntax checks passed. The focused regression runs are recorded in the development session; browser interaction at mobile widths and assistive technology remains a staging check.

The additive migration ran in the configured local MySQL database after a successful SQL preview; feature tests also run it in isolated SQLite. Apply migration 000003 on staging before activating the code, validate existing blocker status counts and ticket status mapping, then use the normal deployment procedure. Older code can continue reading the string ticket status and `resolved_at`. Keep the new columns if reverting application code so hold metadata and blocker state history are preserved. No production environment was changed during this work.
