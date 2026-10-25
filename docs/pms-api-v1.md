# PMS automation API v1

The five automation endpoints use the same `pm_projects` and `pm_work_items` rows, Eloquent models, project and ticket controllers, permission checks, workflow services, audit logs, and soft deletion policy as the browser workspace. A successful write is visible in the UI after refresh. There is no separate API data store.

## Authentication

1. `POST /api/backoffice/pms/auth/login` with `{ "email": "...", "password": "...", "device_name": "QA client" }` as an active PMS member.
2. Send `Authorization: Bearer <token>`, `Accept: application/json`, and `Content-Type: application/json` to every v1 endpoint. Browser session cookies alone are rejected by these five endpoints.
3. `DELETE /api/backoffice/pms/auth/token` revokes the current token. Newly issued PMS tokens expire in 30 days. All five routes are limited to 60 requests per minute by the route throttle; administrators still pass the normal authorization checks.

The login route is the existing PMS API login. Store tokens only in a local client environment or secret manager. The exported [Postman collection](../tests/postman/pms-api-v1.postman_collection.json) contains empty credential and token variables. The [OpenAPI file](pms-api-v1.openapi.yaml) defines parameters, response envelopes, and status codes.

## Project operations

`POST /api/v1/project-management/updateProjectInfo` accepts `action=insert|update|delete`.

| Action | Required input | Behavior |
| --- | --- | --- |
| insert | `project_key`, `name`; `priority` defaults to normal | Administrator creates a real project, becomes its owner and Project Administrator. Status defaults to Planned. A supplied valid `status` is saved. The key is normalized to uppercase. |
| update | `project_id` and at least one editable field | Project Manager edits publish immediately. Business Analyst edits return 202 with `review_id`; the published record changes only after approval. `expected_version` can reject a stale client with 409. `project_key` cannot be changed. |
| delete | `project_id` | A Business Analyst receives a 202 review request. A Project Manager or administrator moves the project to Recently Deleted. `expected_version` also applies. |

Editable fields: `name`, `description`, `objective`, `category`, `priority`, `status`, `planned_start_date`, `target_date`, `related_module`, `manager_id`. Priorities accept `low`, `normal`, `high`, `critical` or the actual database codes `P4` through `P1`. Responses include both the readable value and `priority_code`. Status uses the existing values Planned, Approved, Active, On Hold, Completed, and Cancelled. A new `manager_id` must name an active PMS member whose position is Project Manager. On insert, that person gains Project Manager membership. On update, changing the manager is administrator only and the target must already hold a project management membership.

`status_code` is an optional numeric input. Responses keep the existing string `status` and add `status_code` and `status_label`. A conflicting `status` and `status_code` pair returns 422. Project codes are 1 Cancelled, 2 Planned, 3 Active, 4 Approved, 5 On Hold, and 6 Completed. A Business Analyst cannot change the assigned manager.

`GET /api/v1/project-management/getProjectInfo` requires `project_id` or `project_key`. Supplying both means both must identify the same active project; a mismatch returns 404. Permission is checked before project data is returned. A soft deleted project is not returned.

Example:

```json
{"action":"insert","project_key":"PGOS","name":"Personal Goals Operating System","priority":"high","status":"Active"}
```

The insert returns HTTP 201 with `data.project_id`. An update such as `{"action":"update","project_id":101,"name":"Updated via JMeter","priority":"high"}` returns 200, and the next GET returns those persisted values.

## Ticket operations

`GET /api/v1/project-management/getProjectTickets?project_id=<id>` lists accessible tickets in descending ID order. Optional filters are `status`, `status_code`, `priority`, `ticket_type`, `developer_id`, and `qa_id`; `page` and `per_page` paginate results, with `per_page` capped at 100 and defaulting to 20. `data.items` is empty when a visible project has no matching tickets. `data.pagination` reports `page`, `per_page`, `total`, and `last_page`.

`POST /api/v1/project-management/updateTicketInfo` accepts `action=insert|update|delete`. The UI's category specific required fields still apply: a REQ needs description, business justification, and acceptance criteria; ENH needs current/proposed behavior and acceptance criteria; PRD and QAT need their respective incident or testing details. A new ticket starts in its normal category status. For updates, `title` maps to the existing `summary` field, `developer_assignee_id` to `developer_id`, `qa_assignee_id` to `qa_id`, and `resolution` to the QAT resolution workflow. `project_manager_id` assigns an active Project Manager who belongs to the ticket's project; it is separate from Developer, QA, and PIC assignments. The eligible project manager is preselected from the parent project when no explicit manager is supplied. A submitted `status` is processed through the existing transition service; a disallowed stage transition returns 409 with `error=WORKFLOW_STAGE_RESTRICTION` and current and required stages. Invalid fields return 422. `expected_version` is optional and rejects known stale versions with 409. Delete uses the existing Recently Deleted policy. Activity and audit records are written by the shared controllers and models.

For Business Analysts, REQ and ENH/EHN updates and deletions return 202 with `data.review_id`. The original record stays published until a Project Manager approves. Workflow status and resolution cannot be submitted as an analyst detail proposal. Other authorized roles retain their existing paths. A valid `status_code` can be used for a transition; responses keep string `status` and add numeric `status_code` and `status_label`.

Ticket On Hold is code 33. `updateTicketInfo` with `action=update`, `ticket_id`, `status_code=33`, and `reason` requests a pause. For an On Hold ticket, a valid working `status` or `status_code` requests resume. If unresolved blockers remain, send `override_unresolved=true` and a reason. BA pause and resume requests on REQ/ENH/EHN tickets return 202 for PM review. Detail edits and pause/resume must be separate requests. Responses include `assignee_ids`, `hold_previous_status_code`, `hold_reason`, and `held_at` alongside the compatible string status.

General PIC assignments use the protected `/api/backoffice/pms/tickets/{ticket}/pics` endpoints: `GET` returns assigned and eligible project members; `PUT` accepts `{"assignee_ids":[5,8,12],"lock_version":3}` and replaces the set; `DELETE /{user}` removes one PIC. These routes require an authenticated PMS user and project access. PICs are independent of `developer_assignee_id` and `qa_assignee_id`.

`GET /api/v1/project-management/getAssignableUsers?assignment_type=developer|qa|manager` returns at most 50 active, eligible users. `project_id` restricts results to that project's members and requires access to it. `search` matches name, email, or job position. Without `project_id`, regular members see candidates only in projects they share. A new developer assignment requires the explicit Developer job position; QA requires QA Engineer. A manager assignment requires the Project Manager project membership role, regardless of job position. All new assignments require an active PMS profile and project membership. Historical assignments with another position remain visible and can be retained unchanged, but a new assignment to that person is rejected. Administrators set positions on the Team member page; members cannot change their own position through My Profile.

The ticket creation and editing screens query the same assignable users handler through `/backoffice/project-management/assignable-users` under session authentication. The five external routes always require a bearer token.

## Response format and errors

Every v1 response has `success`, `code`, `message`, `data`, `errors`, `request_id`, and an ISO 8601 `timestamp`. Error `data` is null and validation errors are keyed by field. Workflow stage conflicts additionally include `error` and `details` with `current_stage` and `required_stage`. The handler returns 401 for missing/expired/revoked tokens, 403 for denied operations, 404 for missing resources, 409 for supplied stale versions or workflow stage conflicts, 422 for field validation failures, and 429 for rate limiting. A 500 response has a generic message and no trace or SQL. Unsupported methods return 405.

## Postman

Import the collection. Set `base_url`, `pms_email`, and `pms_password` locally. Run **Login and capture token**, then **Insert Project**, **Update Project**, and **Get Project Info**. The collection captures `project_id` and checks persisted fields. Run the Ticket Management and User Assignment folders while the project is active. The Negative Testing folder includes missing parameters, invalid priority and action, unauthorized access, missing project, invalid assignment, duplicate project key, and a forbidden case. The forbidden case requires a separate nonadministrator token; `qa_id` and `ticket_id` are required for the invalid assignment case. Run Cleanup's ticket delete before project delete. A new project key is needed on a repeated insert because soft deleted keys remain reserved.

## JMeter

The [JMeter plan](../tests/jmeter/pms-api-v1.jmx) is generated by [build_pms_api_plan.php](../tests/jmeter/build_pms_api_plan.php). It runs insert/read, update/verify, negative, and ticket assignment transactions. Each sampler asserts the expected HTTP status and JSON fields; JSON extractors capture created IDs. Assignment updates run when the QA workspace has at least one active Developer and QA Engineer. The plan uses the existing authenticated project membership endpoint to add them to the newly created test project before assignment. The unauthorized sampler overrides the bearer header with an invalid token.

Example PowerShell setup for a local QA environment:

```powershell
$env:PMS_API_TOKEN = '<token obtained from the PMS login API>'
jmeter -n -t tests/jmeter/pms-api-v1.jmx -Jhost=localhost -Jport=80 -Jprotocol=http -Jusers=1 -Jramp_up=1 -Jloops=1 -l storage/app/pms-api-results.jtl -e -o storage/app/pms-api-html
```

The token is read from the environment and is not stored in the plan. `users`, `ramp_up`, and `loops` control load. The JTL and HTML report expose transaction success rate, HTTP errors, response time percentiles, and throughput. Run load scenarios only against a controlled QA database; each loop creates a new project and ticket. The plan does not purge test data.

## Migration and data review

Migrations `2026_10_18_000004` and `000005` add nullable position/phone/photo and comment parent fields without dropping existing data. The position migration backfills only exact, unambiguous legacy job titles. Existing unknown titles stay unassigned for administrator review. The comment migration leaves all existing comments as top level. Both migrations have rollback methods. `php artisan migrate --pretend` can be used to inspect SQL before deploying, followed by normal `php artisan migrate` in the approved environment.

To find historical assignments whose current position no longer qualifies, query the active data without modifying it:

```sql
SELECT t.id, t.issue_key, 'developer' AS assignment_type, t.developer_id AS user_id, p.job_position
FROM pm_work_items t LEFT JOIN pms_profiles p ON p.user_id = t.developer_id
WHERE t.deleted_at IS NULL AND t.developer_id IS NOT NULL AND (p.job_position IS NULL OR p.job_position <> 'Developer')
UNION ALL
SELECT t.id, t.issue_key, 'qa', t.qa_id, p.job_position
FROM pm_work_items t LEFT JOIN pms_profiles p ON p.user_id = t.qa_id
WHERE t.deleted_at IS NULL AND t.qa_id IS NOT NULL AND (p.job_position IS NULL OR p.job_position <> 'QA Engineer');
```

Review these records with project owners. The release does not rewrite them automatically.
