# Project Management 2.1 release and deployment

Date: 2026-10-17. Version: 2.1.1.

## Investigation

The live MySQL `users.referral_code` column was `NOT NULL` without a default. PMS registration creates a minimal opaque `users` bridge for existing actor and assignment foreign keys, then stores its real email and password in `project_management_users`. The bridge intentionally has no referral identity, so the insert failed with SQLSTATE 1364. Migration `2026_10_17_000008` makes only `users.referral_code` nullable on legacy schemas, matching the fresh-install users migration. PMS registration still supplies no referral codes or referral links. TradingHub and Dealership registration continue generating referral codes.

Backoffice Module Management already had a global settings route and working toggle cache invalidation, but the PMS shell did not link to that route when TradingHub and Dealership were both off. The settings routes now accept the separate PMS guard or the normal web guard, still require an administrator, and remain available when all three business modules are off. Existing configuration is global; there is no tenant or company module setting model to extend safely.

The previous QAT form had an optional `due_date`, but it displayed delivery planning controls to QA and the backend did not distinguish QA estimates. The project details button passed `project` only as a query default to the global form; the submitted project remained editable. Project-context creation now uses a project route and applies that route's ID and ticket category on the server.

## Changes

- **FIX:** PMS registration and reset use dedicated credentials. PMS identity email may match an unrelated module account. Existing known PMS memberships are backfilled, preserving hashes and actor IDs. No user or ticket is deleted.
- **FIX:** Administrators can open Module Management from the PMS sidebar and re-enable any module after a toggle change. Module cards show updated time and updater where known.
- **FIX:** QA may report QAT without a target completion date or delivery planning fields. Project-context forms and writes use the trusted route project.
- **ENHANCEMENT:** Server-side ticket creation permissions apply to web and API routes, including the legacy issue endpoint. BA and PM can create REQ, ENH, PRD, and QAT; QA can create QAT; administrators need an explicit grant or creation role.
- **ENHANCEMENT:** New QAT tickets start `Open` with null resolution. The eight named business statuses, six optional resolution values, status history, verification gate, and separate assigned-QA estimate are implemented. Changing the QA owner clears that owner's estimate.
- **NEW:** `project_management_users` and `pms_password_reset_tokens`; `pms` session guard and provider.

## Migration and existing data

Apply `php artisan migrate --force` during the normal deployment procedure, after a database backup. Migrations `2026_10_17_000005` through `000007` add the dedicated PMS identity and password reset tables, workspace creation role and explicit grant, QAT resolution and estimate fields, and feature toggle updater. Migration `000008` relaxes only the legacy `users.referral_code` null constraint so PMS actor bridge rows can exist without a referral. Existing referral values and registration behavior are preserved. The user backfill selects only rows already in `pms_profiles`; ambiguous shared accounts are deliberately not copied. An administrator can provision them explicitly through Team Management. Existing ticket and membership foreign keys continue to reference the stable `users` bridge. Existing QAT statuses are mapped to the new workflow with a `status_migration` history event and activity entry. Duplicate and Rejected legacy terminal states remain readable.

Clear Laravel's cached config and routes after deployment if those caches are in use (`php artisan optimize:clear`), then rebuild them by the site's normal procedure. Confirm module toggles, registration policy, mail delivery for PMS reset links, and the queue worker used for notifications. The app uses the existing global feature toggle configuration; no company-specific behavior has been added.

## Verification

`PmsIdentityAndAccessTest` covers independent email/password scope, reset, all eight module combinations, administrator recovery, BA/PM/QA creation, unauthorized API writes, project ID tampering, optional QAT resolution, QA estimates, status verification, and reassignment. Existing PMS delivery, release, registration, comments, attachments, and project tests run as regression checks. The full application run completed with 204 passing tests, one skipped test, and one unrelated failure in `RealMarketInsightTest` (`CONNECTED` expected, `IDLE` returned at line 94). The final focused PMS run passed nine tests and 142 assertions. Blade compilation and PHP syntax checks passed. Browser visual review could not run because the available Windows computer-use native pipe was unavailable in this session; server-rendered screens are covered by feature tests.

For the 2.1.1 registration repair, the focused PMS suite passed nine tests and 145 assertions. After applying migration `000008` to the local MySQL database, `users.referral_code` reported `Null: YES`. A PMS registration insert using the real MySQL connection succeeded; the smoke-test account was rolled back in its outer transaction.
