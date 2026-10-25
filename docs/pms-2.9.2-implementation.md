# PMS 2.9.2 implementation and rollout

## Scope

PMS only: readable activity values, sortable directories and aligned CSV exports, Malaysia timestamps, time tracking and browser session leases, Project Owner assignment from tickets, and immutable PJ references. TradingHub and Dealership routes and their authentication policies are unchanged.

## Database migration

Run `php artisan migrate` after taking the normal database backup. The additive migration `2026_10_24_000001_add_pms_references_and_time_tracking.php` creates:

- `pm_projects.project_reference` (unique, nullable during the backfill);
- `pm_project_reference_numbers` (monotonic allocator, retained after project deletion);
- `pm_time_entries` and `pm_work_timers` (explicit work);
- `pm_workspace_sessions` and `pm_workspace_activity_intervals` (PMS browser session metadata and active workspace intervals);
- `pm_time_entry_adjustments` (audited Super Administrator corrections).

The migration includes soft-deleted projects in ascending internal ID order and gives each an unused `PJ` reference. Existing keys, internal IDs, ticket references, and foreign keys remain untouched. New references come from the allocator's auto-increment primary key. Deleted or purged projects do not release allocator rows, so references are never reused. Gaps after failed transactions are acceptable. The sequence is global because PMS currently uses one shared organization space.

The reference column remains nullable at the database level to support a safe additive migration. The `PmProject` model allocates references on creation and rejects changes on update. Direct SQL inserts that bypass Eloquent need to allocate a reference explicitly or they will create incomplete project records.

Projects with completed time entries or a pending work timer cannot be permanently purged; archiving and recoverable deletion remain available. This protects the foreign-key relationship and retained work history.

## Time and session policy

`PMS_ACTIVE_IDLE_SECONDS=300` pauses measurement and running work timers after five minutes without eligible activity. `PMS_SESSION_IDLE_SECONDS=3600` expires the PMS browser lease after 60 minutes. The browser sends activity only after visible, focused interaction; status polling never renews the lease. The server checks expiry before handling every session-backed PMS request. An expired browser lease logs its reason and pauses active work; it cannot be renewed without signing in. A token-authenticated PMS API request has no browser session and continues to follow the existing separate token policy.

Session activity requests use a one-time nonce in addition to the existing session cookie and CSRF token. Successful activity rotates the nonce. A stale tab fetches the current session state and retries once; replaying an old activity request does not renew the lease.

New timer, time-entry, and session timestamps are stored in UTC and displayed in `Asia/Kuala_Lumpur`. Existing PMS models and audit tables predate this change and use the application's configured timezone; the formatter interprets their timezone without shifting legacy rows. Date-only fields retain dates without adding an invented time. Existing TradingHub and Dealership timestamp storage is not changed.

Set `SESSION_SECURE_COOKIE=true` on HTTPS production deployments. Existing CSRF, HttpOnly, and SameSite cookie protections remain in place. Schedule `pms:prune-session-history` is registered daily and deletes ended session metadata older than `PMS_SESSION_HISTORY_DAYS` (default 365, minimum 30), while preserving time entries and their corrections.

## Rollout checks

1. Back up the database and confirm sufficient capacity for additive tables and existing-project backfill.
2. Deploy code and run `php artisan migrate --force` before serving the new PMS pages.
3. Confirm `APP_TIMEZONE` reflects the timezone used for historical PMS timestamps. Set the PMS idle settings if different from defaults.
4. Verify a project created after migration receives the next PJ reference; an old project retains its key and gets a stable PJ reference.
5. Verify a real PMS browser session expires after idle, and token-authenticated automation still follows its existing policy.
6. Run `php artisan view:cache`, the production asset build, and the PMS regression suite.

Local migration against the configured MySQL database was not run because its service was unavailable in this workspace. The automated suite exercises the migration with SQLite in-memory databases.
