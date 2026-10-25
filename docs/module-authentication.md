# Module authentication and deployment

The follow-up [login discovery implementation and QA report](module-login-discovery.md) adds `show_login_entry` independently of public visibility. Enabled modules show a sign-in entry by default; management can hide that entry without disabling the direct login URL. Apply migration `2026_10_23_000002_add_login_entry_visibility_to_module_access_settings.php` after the base module-access migration.

## Root cause

`PmsModule::publicAuthMode()` originally returned true only when both TradingHub and Ecommerce were off. `Pms\AuthController::login()` and `authenticate()` used that public-homepage condition to return 404 unless a caller knew to add `member=1` or an invitation token. The PMS forgot-password page used the same condition, and reset completion sent combined-mode users to the website login. The route always existed; its controller made it unavailable in normal combined operation.

PMS login, forgot password, reset and the PMS home entry now depend only on the PMS operational toggle. `/backoffice/project-management/login` remains the canonical URL. `/project-management/login` redirects to it. The old `?member=1` URL continues to resolve but is no longer required.

## Identity and access model

This is a compatible hybrid of the existing schema. PMS passwords remain in `project_management_users`; PMS membership and status remain in `pms_profiles`, with existing project assignments and roles unchanged. TradingHub and Dealership continue using `users` and their established role IDs. No accounts are merged by email, and no passwords or existing user rows are changed. The legacy global administrator roles retain their established access.

`ModuleAccess` maps the stable keys `tradinghub`, `dealership`, `pms` to operational toggles, login routes and legacy roles. An explicit `module_memberships` row can grant or suspend TradingHub or Dealership access for a primary website account. When no row exists, the account's historical role controls its original module. An explicit row takes precedence over that legacy mapping. PMS memberships continue through the existing invitation and team flow. Super Admin can manage cross-module grants on the module settings page; PMS-only shadow identities must first have a separate primary website account for such a grant to be usable.

An unqualified `auth` route authenticates the `web` guard. PMS routes name `pms,web` explicitly and still enforce `PmsAccess` and project policies. Logging in to another module replaces the browser identity and regenerates the session ID. Both logout routes end the browser session. A member with access to multiple modules signs in to the selected module when switching. API tokens issued by the mobile TradingHub login carry `tradinghub`; PMS tokens carry `pms`. Protected APIs check both current membership and persisted token ability. Legacy tokens with `*` still work only while their owner has the required module membership.

Module disablement returns 404 for its login and workspace routes. A hidden module remains operational for authorized users with its direct login URL. Public visibility controls public navigation and homepage selection only. PMS public registration follows the existing `pms_workspace_settings.registration_policy` regardless of other modules' visibility. Invitation validation, expiry, revocation and project assignment continue through the original invitation service.

## Database changes

Run migration `2026_10_23_000001_create_module_access_settings.php`. It adds `module_access_settings` (unique module key, visibility, registration policy, updater metadata) and `module_memberships` (unique user/module pair, status and assigner, indexed by module/status). Existing toggles and PMS tables remain. Rows are optional: absent settings preserve TradingHub and Dealership public registration/visibility and keep PMS private by default; absent memberships preserve legacy role access. No backfill is required and duplicate email addresses stay separate.

## Deployment and rollback

1. Back up the database and current release. Deploy the code to staging first and run `php artisan migrate --force` with the site's configured MySQL connection. This migration is additive and does not rewrite accounts or sessions.
2. Run `php artisan optimize:clear`, then rebuild configuration and route caches using the hosting environment's normal release commands. The new login routes contain no closures and support `route:cache`.
3. Run `php artisan test --filter=ModuleAuthenticationTest`, then the full suite against the staging test database. Smoke test all eight module toggle combinations, the three direct login URLs, PMS invitation acceptance, PMS reset, API token scope denial, a PMS deep link, and a protected Dealership/TradingHub URL with a PMS-only session.
4. Check the Super Admin module settings screen for visibility, registration policy and audit entries. Change public visibility independently of enablement and confirm a hidden but enabled PMS still signs in.
5. Production rollout should keep a short observation window for login and API errors. No environment-variable change is required. Existing browser sessions use the same guards, but users switching modules will need to sign in again because one browser session owns one identity.

To roll back the release, restore the previous code and clear/rebuild caches. The additive tables can remain without affecting the previous code. If a full schema rollback is required after confirming no new grants are needed, back up the two new tables and run the migration's `down()` in a controlled maintenance window; that removes only the new settings and explicit grants. Do not roll back by deleting users, PMS profiles, invitations or project assignments.

## Known limits

The legacy website has broad role-based controllers outside the three module namespaces. `EnforceBackofficeModules` now checks protected TradingHub and Dealership controllers and known route families, but older generic administrator screens still rely on their existing role checks. A future route-by-route authorization audit should migrate those screens to explicit module middleware. The PMS invitation and project policy tests cover the established project actions; a live MySQL and browser viewport smoke test is still needed before production deployment.
