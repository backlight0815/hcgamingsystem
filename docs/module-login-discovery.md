# PMS login discovery fix — implementation and QA

Date: 2026-10-23. Laravel 10 / PHP 8.2. Local implementation only; no production deployment or production configuration changes.

## Root cause

The previous authentication fix restored independent login routes, but `resources/views/auth/login.blade.php` still filtered its alternate sign-in links through `ModuleAccess::publicVisible()`. PMS defaults to private public visibility, so its link disappeared even with all three modules enabled. This was a remaining coupling between public marketing navigation and authentication discovery. The routes, PMS guard, credential lookup, and membership checks already worked independently and were retained.

The production URL could not be fetched through the available web tool. Diagnosis is based on the actual repository and reproduced local behavior, not a claim that production was inspected or changed.

## Behavior and configuration

The new `module_access_settings.show_login_entry` boolean defaults to true for existing settings rows and for modules without a settings row. Thus enabled PMS is discoverable on sign-in pages by default while remaining private on public homepages. Management can explicitly hide it. Disabled modules never appear in sign-in navigation, even when their stored login visibility is true.

The existing module configuration page now separates:

| Setting | Effect |
|---|---|
| Module enabled | Controls operational routes and access |
| Public visibility | Controls public homepage and main navigation listing |
| Show login entry | Controls links in authentication navigation only |
| Registration policy | Controls onboarding independently of links |
| Membership and role | Controls actual access and actions |

For the reported scenario, set all three modules enabled, PMS public visibility off, PMS Show login entry on, and PMS registration to Invitation only. TradingHub and Dealership login pages then display Project Management / Staff Sign In. PMS pages also show the enabled, discoverable alternate sign-ins. The current module is excluded from the list.

The controller validates login visibility, records it in the existing configuration audit event, and preserves the saved value when older form submissions omit the new field. Changing this setting does not grant memberships, modify accounts, or change passwords, roles, project assignments, or invitations.

## Routing and authentication

No routes or authentication providers were replaced in this follow-up.

| Entry | Destination |
|---|---|
| `/tradinghub/login` | TradingHub credentials and membership validation |
| `/dealership/login` | Dealership credentials and membership validation |
| `/project-management/login` | Redirects to the established PMS login URL |
| `/backoffice/project-management/login` | PMS credential and membership validation |

The existing web/PMS session separation, active-account checks, CSRF protection, throttling, token abilities and resource authorization remain in force. An anonymous numeric ticket deep link redirects to PMS login, returns to that destination after successful sign-in, then follows the existing canonical ticket-reference redirect. Removing project access still denies that ticket. Existing invitation, duplicate-email, password-reset and project permission tests passed in the full suite.

## Interface

The shared Access Another Module component provides distinct module names, Staff Sign In wording, compact identity marks and an arrow. HC Gaming Studio branding remains on the main login pages. Inputs have labels, the password toggle is keyboard accessible and reports its state, and module links have a visible keyboard focus outline. The submit button now uses the native validated submit event and recovers after Back/Forward navigation.

The login view no longer executes or registers the site's service worker. Browser testing exposed that registration starting a very large offline-cache installation on the authentication page. The global service worker and other pages' registration behavior were not changed.

## Exact source file inventory for this follow-up

Modified:

- `app/Support/ModuleAccess.php` — independent login-entry visibility and reusable entry selection.
- `app/Http/Controllers/Config/FeatureToggleController.php` — validation, persistence, compatibility and auditing.
- `resources/views/admin/config/features_config.blade.php` — separate management control and help text.
- `resources/views/auth/login.blade.php` — shared navigation, input labels, password-toggle semantics, validated submit handling, login service-worker registration removal.
- `resources/views/pms/auth-layout.blade.php` — shared navigation stylesheet.
- `resources/views/pms/login.blade.php` — alternate sign-in navigation.
- `tests/Feature/ModuleAuthenticationTest.php` — four additional regression tests.
- `docs/module-authentication.md` — links this follow-up and migration.

Created:

- `database/migrations/2026_10_23_000002_add_login_entry_visibility_to_module_access_settings.php` — one additive boolean; down removes only that column.
- `resources/views/auth/partials/module-navigation.blade.php` — shared sign-in navigation.
- `public/backend/assets/css/module-auth.css` — responsive navigation, form and focus styling.
- `docs/module-login-discovery.md` — this report.

QA artifacts under `output/module-login-qa/`: `seed.php`, `verify.mjs`, `browser-results.json`, `full-tests.txt`, `desktop.png`, `tablet.png`, `mobile.png`, `small-mobile.png`, `pms-mobile.png`, and a local `.gitignore`. The seed script refuses to run outside the isolated testing SQLite path. The temporary SQLite file and browser profile are excluded from version control and deployment.

## Executed verification

- Full PHP suite: **329 passed, 1 skipped, 7,499 assertions**, zero final failures. Log: `output/module-login-qa/full-tests.txt`. The existing skipped ExampleTest requires legacy CMS tables from a production import.
- Final focused authentication suite after the login-view adjustment: **13 passed, 310 assertions**. Covers all eight module combinations; independent public/login visibility; disabled and hidden modules; configuration authorization and compatibility; PMS isolation, token scopes, deep links, and denied ticket access.
- Headless Chrome: **1440, 768, 390 and 320 pixel widths**; both alternate links present, correct branding, no horizontal overflow, labels, password toggle state and 3px keyboard focus outline verified. Screenshots were inspected at desktop and mobile sizes.
- Actual local browser journey: TradingHub login → Staff Sign In → PMS login → fake staff authentication → PMS dashboard passed. The same PMS credentials were rejected by both TradingHub and Dealership.
- PHP syntax checks passed for the service, settings controller, new migration and updated test file. Route cache built successfully and was cleared afterward to restore the uncached local state.
- The earlier browser attempt failed during the service-worker asset burst, and the first keyboard check found a legacy CSS outline override. Both were corrected and the final browser run passed.

Unexecuted: live production verification, MySQL migration smoke test, physical Chrome Android, and Safari iOS. Chrome viewport emulation does not establish Safari or physical-device compatibility. Existing broad generic administrator controllers still rely on their established role checks, as documented in the base authentication report.

## Deployment and rollback

1. Back up the release and database. Deploy this source together with the base module-authentication implementation if that release is not already installed. Review pending migrations in staging before applying them. Do not deploy QA scripts, SQLite files, browser profiles or test credentials.
2. With the hosting site's PHP binary, run `php artisan migrate:status`. Apply the new additive migration after `2026_10_23_000001_create_module_access_settings.php`. If the base migration is already installed, the focused command is:

   ```sh
   php artisan migrate --force --path=database/migrations/2026_10_23_000002_add_login_entry_visibility_to_module_access_settings.php
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. No environment variable or identity migration is required. The new column may take a brief schema lock on MySQL; schedule it using the site's normal release window. Existing sessions remain compatible.
4. On staging, verify all enabled login URLs, the three-module scenario, a private PMS with its login entry shown, then hide its entry and confirm direct login still works. Confirm invitation-only PMS has no public registration, staff cannot enter other modules, and ticket deep links enforce project access. Check mobile browsers and configuration audit records.
5. Set each module's Show login entry explicitly in the management panel according to the desired discovery policy. Login discovery defaults on after this migration; public homepage visibility does not change. Deploy to production only after the staging checks.

Rollback: restore the prior code release and refresh caches. Leave the additional boolean column in place; the prior code ignores it. If removing the column is necessary, verify this migration is the last applicable migration and back up settings before invoking its down operation in a controlled window. Do not roll back unrelated migrations or delete identities, profiles, memberships, invitations or project records. The previous code still has the reported discovery defect when PMS public visibility is off.
