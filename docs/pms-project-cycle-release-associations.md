# PMS project planning associations

## Existing design and reuse

`pm_global_cycles` is the single organization Development Cycle registry. `pm_cycles` remains a project-specific execution plan and does not create another organization cycle. The existing `pm_global_cycle_projects` table already has the many-to-many relationship and unique `(cycle_id, project_id)` key. Project, ticket and release records do not carry company IDs; their scope is the PMS project membership and global administrator model. `PmRelease.project_id` is the owning project and release tickets still follow that project. `pms_profiles.job_position_id` refers to the canonical `pms_job_positions.code`; `Product Owner` and `Project Owner` are distinct.

## Data changes

Migration `2026_10_26_000004_add_pms_project_planning_links.php` adds nullable `pm_projects.current_global_cycle_id` and `current_release_id`. They identify the selected current values, while the existing cycle junction retains valid historical participation. Unambiguous single-cycle associations are backfilled as current. It also adds `pm_project_releases` with unique `(project_id, release_id)`, foreign keys, assignment actor and timestamps. Existing releases are backfilled to their owning project; future release creation writes the same link. Linking a release to a new project does not reparent the release or move tickets.

The project planning service validates available cycles and releases. A release tied to a global cycle requires that same selected Development Cycle. A legacy project-plan release cannot be linked to another project. The actor must have access to the release's owning project to add a new cross-project link. Current associations can be cleared explicitly; historical links stay until an authorized explicit removal. Cycle Management's existing remove action deletes only the junction row and clears a matching current pointer. It refuses removal while the current release requires that cycle.

## API contracts

Existing `POST /api/pms/v1/updateProjectInfo` accepts optional `current_global_cycle_id` and `current_release_id` for `insert` and `update`. Explicit `null` clears the selected value. Existing `GET /api/pms/v1/getProjectInfo` fields remain; additive fields are `current_global_cycle_id`, `current_release_id`, `development_cycle`, `related_cycles`, and `release`. A sample insert body is:

```json
{"action":"insert","project_key":"HCAI","name":"Trading AI Improvement","priority":"normal","current_global_cycle_id":2,"current_release_id":7}
```

The response retains its `PROJECT_CREATED` envelope and adds `data.development_cycle` such as `{"id":2,"key":"CYCLE-002","name":"October delivery","status":"Upcoming"}` and `data.release` such as `{"id":7,"key":"REL-002","name":"AI release","status":"Planned"}`. Invalid combinations return validation errors on `current_release_id` without creating or partly updating a project.

Existing `POST /pms/delivery-cycles/{cycle}/projects` accepts `{"project_ids":[1,3,7]}` and is idempotent. It returns the count newly assigned and the total assigned. Existing `DELETE /pms/delivery-cycles/{cycle}/projects/{project}` removes one relationship. New `GET /pms/delivery-cycles/{cycle}/project-candidates?q=AI&page=1` returns the existing Laravel paginator shape with 25 scoped results per page; results include the project reference, status, selected cycle and whether it is already assigned. Existing `GET /api/pms/v1/getAssignableUsers?assignment_type=project_owner&project_id=1&search=Lee` returns only eligible candidates, preserving the existing `data` list format and 50-result cap.

## Permission matrix

| Action | Super Admin | Project Manager on project | Product Owner with project membership | BA / QA / Developer |
|---|---:|---:|---:|---:|
| Create organization cycle | Yes | Yes, with a PM project | Yes, with a project | No |
| Assign/remove cycle project | Yes | Yes | Yes | No |
| Search cycle candidates | Yes | Scoped projects | Scoped projects | No |
| Edit project planning links | Yes | Yes | Yes | No |
| Assign an eligible Project Owner | Yes | Yes | Yes through the ticket owner action | No |
| Read own project/cycle | Yes | Yes | Yes | As existing project membership permits |

An actor's planning authority never makes them an eligible Project Owner candidate. Candidate positions with codes `PM`, `PJO`, or `PROJECT_OWNER` are excluded, with exact legacy text checks only for profiles without a position ID. Active PMS eligibility and existing administrative rules still apply. Previously assigned ineligible owners remain visible and are labeled for review; unrelated edits preserve the assignment, while new assignment attempts are rejected at submission.

## UI, audit and release behavior

Create Project shows searchable Development Cycle and Release selectors with explicit unassigned choices. Project details display the current links and provide a separate planning editor for authorized managers. Cycle directory shows the Assigned Projects count. The cycle's Projects tab shows current versus historical membership, selected assignments, responsive multi-select, server-side search, pagination, save/cancel, and confirmed per-project removal. Release Management lists associated projects. Existing ticket auto-selection rules, ticket assignments, and separate delivery progress calculations are unchanged. Release ticket movement remains restricted to the release's owning project's workflow.

Cycle/release changes and assignment/removal are recorded with actor, IDs and readable keys in `pm_audit_logs` and the existing cycle history. Project activity displays MYT through `PmsDateTime`. API updates are guarded by `lock_version`; the cycle junction key and `insertOrIgnore` make repeated assignments safe.

## Verification and release checklist

Run `PmsProjectPlanningTest`, `PmsGlobalCycleTest`, `PmsOwnerAndQatAssignmentTest`, `PmsAutomationApiTest`, the broader PMS and AI tests, `php artisan view:cache`, and PHP syntax checks. Confirm the create/edit forms and cycle search at desktop and mobile widths in staging. The local migration was applied with `--path`; the separate pending PMS activity migration was not applied by that command.

For staging, back up the database; deploy code and the migration together; run the additive migration; verify backfilled release links and cycle pointers; test authorized and denied users, a cross-project release link, search pagination, and release/project screens. Verify audit display in MYT and that no ticket moved. Follow the established release procedure before production deployment. Rollback requires exporting selected pointers and cross-project release links; migration `down()` refuses to discard those populated associations.

## Known limits

The current PMS schema has no company/tenant foreign key on projects or cycles, so authorization uses the existing PMS membership scope. A linked release retains one owning project for ticket scope and deployment controls; the new association is for planning visibility and does not grant unrelated users release-management authority. Project Owner position codes beyond the configured canonical set need an explicit reviewed code mapping before use. Search on the create/edit project form uses the currently rendered cycle/release choices; server-side pagination is provided for Cycle Management project search.
