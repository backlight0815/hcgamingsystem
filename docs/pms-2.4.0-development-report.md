# Project Management 2.4.0 development report

**Date:** 18 October 2026 (Asia/Singapore)  
**Scope:** Ticket conversation, profiles, assignments, invitations, and automation API  
**Previous release:** 2.3.0

## A. Root cause analysis

- Valid invitation links reached the registered PMS invitation route, but `PmsModule::invitationAllowed()` coupled invitation acceptance to the public registration policy. A workspace that disabled open registration therefore returned a 404 for a valid, private invitation. Invitation access now depends on the PMS module being enabled; token validity, expiry, usage count, email binding, and acceptance authorization are still checked by the invitation service and controller.
- Developer and QA selectors previously relied on broad active-user or project-role lists. A user's project role or administrative role could be mistaken for their actual job position. The new `job_position` field is separate from workspace permissions, and both the selectors and write handlers check explicit Developer or QA Engineer position, active PMS status, and project membership. Existing mismatched assignments are displayed and may be retained unchanged so the migration does not silently remove work ownership.
- Ticket comments, activity, and attachments shared a crowded detail page. Existing audit/activity and private attachment services were usable, but the discussion had no reply relationship. A nullable comment parent key and a tabbed view organize these data without replacing their underlying records.
- External automation had PMS login and browser actions but no five stable, bearer-only endpoints with uniform errors and documented request schemas. The new endpoints invoke the existing project/ticket controllers and workflows against the same tables as the UI.

## B. Files created or modified

| Area | Relevant files |
| --- | --- |
| API and security | `app/Http/Controllers/Api/Pms/V1/ProjectManagementController.php`, `app/Http/Middleware/EnsurePmsApiBearer.php`, `app/Exceptions/Handler.php`, `routes/api.php` |
| Shared browser logic | `app/Http/Controllers/Pms/{ProjectController,TicketController,IssueController,TicketCommentController,TeamController,AuthController,MyProfileController}.php`, `app/Support/{PmsPosition,PmsModule}.php`, `app/Models/PmTicketComment.php`, `routes/web.php`, `config/pms.php` |
| Ticket and profile UI | `resources/views/pms/{ticket,ticket-create,issue,my-profile,team,team-member,layout,invitation-error}.blade.php`, `resources/views/pms/partials/{ticket-edit,ticket-conversation,ticket-comment}.blade.php`, `public/backend/assets/{css/pms-conversation.css,js/pms-conversation.js,js/pms-assignees.js}` |
| Database | `database/migrations/2026_10_18_000004_add_pms_profile_position.php`, `database/migrations/2026_10_18_000005_thread_pms_ticket_comments.php` |
| Test and documentation | `tests/Feature/{PmsAutomationApiTest,PmsProfileConversationTest}.php`, updated PMS regression fixtures, `docs/pms-api-v1.openapi.yaml`, `docs/pms-api-v1.md`, `tests/postman/pms-api-v1.postman_collection.json`, `tests/jmeter/{build_pms_api_plan.php,pms-api-v1.jmx}` |

The working tree already contained extensive unrelated changes before this release. No unrelated file was reverted or staged as part of this work.

## C. Database changes and compatibility

Migration `000004` adds nullable `pms_profiles.job_position` (indexed), `phone`, and private `photo_path`. It backfills only exact, unambiguous legacy title aliases such as Developer, QA, Business Analyst, and Project Manager. Unrecognized titles remain unset for administrator review. Job position does not grant a permission or change an existing project role.

Migration `000005` adds nullable `pm_ticket_comments.parent_id`, a self reference with `nullOnDelete`, and an `(issue_id, parent_id, created_at)` index. All prior comments remain top level. Both migrations have rollback methods. Existing tickets, attachments, identities, and project memberships are preserved. A read-only SQL query for historical assignments that do not meet the new position rule is in [the API guide](pms-api-v1.md#migration-and-data-review).

The two migrations were previewed and applied individually to the local MySQL database. An unrelated pending `2026_10_18_000001_create_admin_audit_logs` migration was intentionally left untouched; deployment environments must run their own migration process and review that separate migration.

## D. UI and UX improvements

- Ticket details now have Discussion, Activity, and Attachments tabs. Discussion includes paged root comments, one-level replies, author position and avatar/initials, relative and absolute time, and the existing edit/delete permissions. Activity is populated by stored ticket events. Attachments retain private download/preview/delete routes, gallery thumbnails, and MIME/size validation. The ticket page was opened in the local browser; Discussion and Activity were clicked and rendered after migration. Attachment behavior has framework coverage but was not exercised with a fresh browser upload in this pass.
- My Profile has personal details, private photo upload, separate password change, professional information, project memberships, and My Work counts/list. My Work is built from real assigned tickets in projects the user may view. Self-service requests cannot set job position, role, account status, or memberships. The initial profile layout was reviewed in the browser; the final My Work addition was verified by feature test and Blade compilation.
- Developer and QA controls have separate search and candidate lists with position labels, loading/empty states, and a bound result limit. The web route and external API use the same candidate handler. Team administrators can set a separate job position. New assignments are validated again in the browser write handlers.
- Invalid or expired invitation links render a dedicated unavailable page with HTTP 410 rather than a generic page-not-found result. Generated invitation URLs use the registered route and configured application URL.

## E. API implementation

All five routes use the `/api/v1/project-management` prefix, Sanctum `Authorization: Bearer` with `pms` ability, active PMS access, and a 60 requests/minute throttle. Browser cookies alone do not authorize these routes. New tokens issued by the existing PMS login expire after 30 days.

| API | Method and path | Status |
| --- | --- | --- |
| `updateProjectInfo` | `POST /api/v1/project-management/updateProjectInfo` | Insert, partial update, and soft delete implemented |
| `getProjectInfo` | `GET /api/v1/project-management/getProjectInfo` | Scoped read by ID or key implemented |
| `getProjectTickets` | `GET /api/v1/project-management/getProjectTickets` | Scoped, filtered, paginated list implemented |
| `updateTicketInfo` | `POST /api/v1/project-management/updateTicketInfo` | Insert, update, workflow transition, resolution, and soft delete implemented |
| `getAssignableUsers` | `GET /api/v1/project-management/getAssignableUsers` | Active position and project scoped search implemented |

Project and ticket writes call the browser controllers, including existing validation, permission, transition, QAT resolution, audit, and soft deletion code. `expected_version` supports an optional 409 conflict check. Errors use `success`, `code`, `message`, `data`, `errors`, `request_id`, and `timestamp`; 401, 403, 404, 409, 422, 429, and generic 500 responses are defined. See [OpenAPI](pms-api-v1.openapi.yaml) for the full schemas.

## F. API request and response examples

Obtain a token with the existing `POST /api/backoffice/pms/auth/login` endpoint, then send `Authorization: Bearer <token>` and `Accept: application/json`.

Project insertion (HTTP 201):

```json
{"action":"insert","project_key":"PGOS","name":"Personal Goals Operating System","priority":"high","status":"Active"}
```

The response has `code: "PROJECT_CREATED"`, `data.project_id`, `data.project_key: "PGOS"`, `data.name`, `data.priority: "high"`, `data.priority_code: "P2"`, and `data.status: "Active"`, together with the standard envelope fields. The created ID is read from the response, never hardcoded in the client.

Project update and read:

```json
{"action":"update","project_id":123,"name":"Updated via API","priority":"critical","expected_version":0}
```

`GET /api/v1/project-management/getProjectInfo?project_id=123` returns the persisted `data.name`, `data.priority: "critical"`, and `data.priority_code: "P1"` if the caller can view that project. The illustrative `123` and version `0` must be replaced with values returned by insert/read. A stale version produces HTTP 409 and `code: "CONFLICT"`.

Ticket insertion for a requirement:

```json
{"action":"insert","project_id":123,"ticket_type":"REQ","title":"Verify API flow","description":"Check the integration","business_justification":"QA coverage","acceptance_criteria":"Read reflects the write","priority":"normal"}
```

`getProjectTickets?project_id=123` returns that ticket in `data.items` with `ticket_id`, `ticket_key`, `title`, `status`, `priority`, assignees, and pagination. `getAssignableUsers?assignment_type=qa&project_id=123&search=QA` returns a bounded array containing `user_id`, `full_name`, `job_position`, `display_name`, and `is_active` only for eligible candidates. A position or membership mismatch sent to `updateTicketInfo` returns HTTP 422 with field errors.

## G. Testing results

The final full Laravel suite (`php artisan test --compact --log-junit storage/app/pms-2.4.0-junit.xml`) passed: **239 passed, 1 skipped, 6,100 assertions**. Focused reruns passed: `PmsProfileConversationTest` **4 passed, 35 assertions** and `PmsAutomationApiTest` **4 passed, 40 assertions**.

The feature tests cover real project write/read/delete persistence; ticket write/read/delete; strict assignment; missing/expired bearer credentials; authorization; profile privilege boundaries and password; threaded comments/activity; generated invitation URL opening and acceptance while public registration is disabled; consumed/invalid invitation handling. Existing invitation tests also cover roles, reuse, expiry, revocation, email mismatch, and registration/login flows.

PHP lint of the changed controllers, Blade view compilation, OpenAPI YAML parsing (3.0.3, five paths), Postman JSON parsing, JMeter XML parsing, and scoped `git diff --check` passed. Local browser checks covered a ticket page and its tabs, and the initial profile layout. The full suite includes other application modules to check for regressions.

## H. Postman and JMeter deliverables

- [Postman collection](../tests/postman/pms-api-v1.postman_collection.json): import it, set `base_url`, `pms_email`, and `pms_password` in a local environment, run Login, Project Management, Ticket Management, User Assignment, Negative Testing, then Cleanup. It captures the token and created IDs. The optional forbidden case needs a separate restricted token. No credentials or live token are stored in the collection.
- [JMeter plan](../tests/jmeter/pms-api-v1.jmx): obtain `PMS_API_TOKEN`, then run `jmeter -n -t tests/jmeter/pms-api-v1.jmx -Jhost=localhost -Jport=80 -Jprotocol=http -Jusers=1 -Jramp_up=1 -Jloops=1 -l storage/app/pms-api-results.jtl`. It covers project insert/read/update verification, negative requests, and assignment when suitable test users exist. [The plan generator](../tests/jmeter/build_pms_api_plan.php) can rebuild the JMX. See [API guide](pms-api-v1.md#jmeter) for setup and report options.

## I. Outstanding issues and limits

- Apache JMeter is not installed here, so the JMX was XML-validated but not imported or executed in JMeter. Postman collection syntax was validated, but a Postman runtime run was not available. Browser-based invitation acceptance was not performed with a separate recipient session; feature tests exercised the generated link and acceptance route with persistence.
- Live historical assignments may contain users without a qualifying new position. They remain assigned until reviewed by a project owner or administrator. The read-only audit SQL is in the API guide.
- Production or other QA databases still need migrations `000004` and `000005`, application URL review for outbound invitation links, and an administrator review of unset job positions. The unrelated pending admin-audit migration requires separate consideration.
- The one existing skipped suite test was not changed in this release. No load-test throughput or latency claim is made without a JMeter runtime run.

## J. Security and regression assessment

Sanctum bearer tokens, token expiry, PMS ability, active profile, project visibility, and controller-level action permissions protect the APIs. The API handler does not disclose stack traces or SQL in 500 responses. Profile photos and ticket evidence stay behind authenticated private routes. Profile updates whitelist personal fields; position and system roles are admin-managed. Invitation tokens remain hashed, time limited, and usage limited, and acceptance uses the PMS identity flow. Comments are ticket scoped and editable/deletable according to the existing policy. Tickets and projects remain soft deleted, with prior data and release workflows intact.

