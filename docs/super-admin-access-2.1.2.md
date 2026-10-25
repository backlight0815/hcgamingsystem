# Super Admin access repair — 2.1.2

Date: 2026-10-18.

## Root causes

The application already stores the global administrator in `users` with primary role ID 1. PMS also has a linked `pms_profiles` table and optional `project_management_users` credentials. The PMS guard uses the main `users` provider, but its standalone login looked up a PMS identity by email first. A colliding module identity could therefore reject a valid global administrator password. PMS ticket creation required an active `pms_profiles` row before the administrator check, even though most other PMS services recognized roles 1 and 2 without a profile. This caused Super Admin to reach PMS yet receive an ordinary 403 for ticket creation. No referral code or separate PMS registration is needed for the global account.

The two browser guards (`web` and `pms`) can coexist in one session. PMS protected routes check `pms` first, while general backoffice routes check `web`. This made switching modules inconsistent when different identities were active. In addition, account and role management routes lacked route-level authentication and authorization, and `Gate::define('admin')` called `hasRole('admin')` although `hasRole` expects a pivot role ID. The backoffice sidebar used role IDs directly; PMS UI already used its central `PmsAccess` checks.

Trading and Dealership use the primary `users` identity and do not have the PMS credential collision. Their backoffice routes and navigation still use a mix of local role checks; the global Gate hook covers policies and the repaired account/role routes, while controller-specific checks remain in place. The repository has no company or tenant key on the PMS project tables, so this deployment cannot infer unrelated company scope from PMS records.

## Behavior and security

- `User::isGlobalSuperAdmin()` checks the trusted stored primary role and active status. `PmsAccess` grants that identity all supported ticket categories before profile membership checks. Other PMS membership rules stay in place.
- PMS standalone login checks the global credential first and signs that identity into `web`; ordinary PMS credentials remain in `pms`. A successful browser login signs out the other guard, and logout invalidates both guards. API login continues to issue a scoped PMS Sanctum token.
- `Gate::before` grants the active global administrator normal Gate abilities. Authentication, module toggles, explicit workflow validation, destructive confirmations, and project data checks remain in their existing middleware and services.
- Account and role routes require an authenticated backoffice administrator. Company Admin cannot grant role 1 or edit a role 1 account. The final active Super Admin cannot be demoted, suspended, or deleted through the protected account routes. Account deletion now requires DELETE plus a typed username confirmation.
- `admin_audit_logs` records successful Super Admin browser login/logout, model-based user role changes, role registry changes, and module toggle changes. No credentials or tokens are logged. The Super Admin can inspect the log at `/admin/system-audit` even when all business modules are disabled. Existing PMS project and workflow audit records remain in `pm_audit_logs`.
- Disabled Trading blocks the `/api/mobile/v1` Trading API and trading account administration; disabled Dealership blocks dealership account administration. The global backoffice remains reachable so a Super Admin can reactivate modules.
- The application has no tenant ownership column for PMS projects. Platform Super Admin has deployment-wide PMS scope. Future company-level tenancy must add explicit ownership and query scoping before company administrators can be limited to individual companies.

## Migration and rollback

Run `php artisan migrate --path=database/migrations/2026_10_18_000001_create_admin_audit_logs.php` during deployment. The migration only adds `admin_audit_logs`; it does not modify users, passwords, or PMS profiles. Roll back only this migration when necessary, after exporting any audit records that must be retained. Code rollback to 2.1.1 can leave the audit table in place.

## Validation

`php artisan test --compact` passed 211 tests and 5,728 assertions, with one existing skipped homepage test, in the local SQLite test environment on 2026-10-18. This includes primary and PMS login without a PMS profile, a colliding PMS email, direct PMS pages, module recovery, ticket creation for REQ/ENH/PRD/QAT, direct PMS API access and token revocation, QA restrictions, blocked self elevation, session logout, and audit events. After adding an assertion for audit access with all modules disabled, the focused global access suite passed 5 tests and 91 assertions.

The configured local MySQL server was not listening. A hidden start attempt exited, and the MariaDB error log reported missing InnoDB tablespace files for an old restore-preview schema. No repair or data-file change was attempted. The additive audit migration ran under the SQLite test suite but remains unapplied to the configured MySQL database. Production credential login and live module switching could therefore not be exercised against real account data in this environment.

The current test suite does not simulate browser MFA because no MFA middleware is installed in these Laravel routes. It also does not prove tenant isolation between companies because PMS has no tenant key.

## System log

`config/pms.php` now reports version 2.1.2 with Fix, Enhancement, and New entries dated 2026-10-18. The PMS System Log screen reads those entries from configuration.
