# HC TradingHub and PMS 2.9.0 implementation

## Summary and prior limitations

Market Analyst AI was limited to a separate execution-plan action, repeated form entry, and did not coordinate field drafts. TradingHub feature flags controlled broad modules but did not independently isolate installed submodules. PMS already stored a project owner but offered no owner choice on create or edit. QAT forms required four environment fields unnecessarily, and QA staff had to re-enter project, release, and cycle assignments.

This release adds a reusable structured AI provider and report field registry, 28 TradingHub submodule switches, Project Owner selection, optional QAT environment fields, and server-authoritative QAT assignment resolution. PMS version was advanced from 2.8.0 to 2.9.0. Existing Market Analyst publication, Discord, and approval flows remain manual.

## Architecture and behavior

- The AI provider interface and generation service accept structured schemas. Market Analyst supplies a field registry and validated form context. Full, empty, section, field, and improvement modes use reviewable drafts; nothing is saved or published until the analyst applies suggestions and submits the existing form. Full report generation makes one model request. Requests are limited to six per minute.
- Where Market Insight is enabled in REAL mode and has fresh XAUUSD completed-candle analyses, the report can reuse verified M15, H1, and H4 indicator classifications and exact saved RSI readings. It does not fetch a live quote during generation, use mock data as verified input, or infer executable entry, stop, or target prices from an indicator. Stale analyses are ignored. Without verified input, drafts stay conditional. The old plan action retains its existing contract.
- TradingHub module definitions, parent relationships, route patterns, and default states are centralized in `TradingSubmodules`. Existing installed modules default on; future registered keys default off until reviewed. `trader_centre` disables its children, `trading_signals` disables Signal Provider Desk, and `trading_certificates` disables My Certificates. HTTP middleware returns a dedicated 503 page or JSON `MODULE_DISABLED`. Switches use persisted `feature_toggles` records, audit status changes, filter navigation and public embedded content, and guard relevant scheduled jobs. Disabling a module does not delete its data. Authentication and module-management access remain outside the switch registry.
- PMS reuses `pm_projects.owner_id`. Create defaults to the creator if no owner is selected. Eligible active PMS users may be selected; owners and managers remain distinct. A newly selected owner joins the project as Viewer, without manager authority. An inactive historical owner stays visible on the edit form but cannot be assigned to another project. Business Analyst edits continue through Project Manager review.
- QAT `testing_environment`, `affected_platform`, `browser_device`, and `build_number` accept null on create and edit. The assignment resolver selects and locks one authorized project, and uniquely determined current release/cycle. Multiple valid choices stay selectable. Browser controls include submitted hidden values; backend checks authorization and project/release/cycle relationships. Existing ticket assignments are preserved on edit unless explicitly changed by an authorized user.

## Changed files

AI framework and report:

- `app/Services/Ai/AiProviderInterface.php`
- `app/Services/Ai/GeminiAiProvider.php`
- `app/Services/Ai/AiGenerationService.php`
- `app/Services/Ai/MarketAnalystGenerationSchema.php`
- `app/Services/Ai/MarketAnalystReportGenerator.php`
- `app/Services/Ai/MarketAnalystVerifiedContextProvider.php`
- `app/Services/TradingPlanGenerationService.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Trading/MarketAnalystController.php`
- `resources/views/admin/market_analyst/_form.blade.php`
- `public/backend/assets/js/market-analyst-ai-plan.js`
- `public/backend/assets/js/market-analyst-ai-report.js`

TradingHub isolation:

- `app/Support/TradingSubmodules.php`
- `app/Models/FeatureToggle.php`
- `app/Http/Middleware/EnsureTradingSubmoduleEnabled.php`
- `app/Http/Kernel.php`
- `app/Http/Controllers/Config/FeatureToggleController.php`
- `app/Http/Controllers/Config/FeatureManagementController.php`
- `app/Http/Controllers/FrontendExperienceController.php`
- `app/Http/Controllers/Home/DashboardController.php`
- `app/Console/Kernel.php`
- `app/Console/Commands/SendAppointmentReminders.php`
- `app/Console/Commands/SyncMarketInsight.php`
- `resources/views/admin/config/trading_modules.blade.php`
- `resources/views/errors/module-disabled.blade.php`
- `resources/views/admin/body/sidebar.blade.php`
- `resources/views/experiences/trading/layout.blade.php`
- `resources/views/experiences/trading/home.blade.php`
- `resources/views/experiences/trading/services.blade.php`
- `resources/views/experiences/trading/about.blade.php`
- `resources/views/experiences/combined.blade.php`

PMS and shared routing/version:

- `app/Services/PmsProjectOwnerService.php`
- `app/Services/PmsQatAssignmentResolver.php`
- `app/Http/Controllers/Pms/ProjectController.php`
- `app/Http/Controllers/Pms/TicketController.php`
- `app/Http/Controllers/Api/Pms/V1/ProjectManagementController.php`
- `resources/views/pms/projects.blade.php`
- `resources/views/pms/project.blade.php`
- `resources/views/pms/ticket-create.blade.php`
- `resources/views/pms/ticket-edit.blade.php`
- `public/backend/assets/js/pms-project-owner.js`
- `public/backend/assets/js/pms-delivery.js`
- `routes/web.php`
- `config/pms.php`

Tests:

- `tests/Feature/MarketAnalystAiReportTest.php`
- `tests/Feature/TradingHubModuleManagementTest.php`
- `tests/Feature/FrontendExperienceTest.php`
- `tests/Feature/PmsOwnerAndQatAssignmentTest.php`
- `tests/Feature/PmsRequestedEnhancementsTest.php`
- `tests/Unit/PmsQatAssignmentResolverTest.php`

## Data, routes, and configuration

No migration was added. `feature_toggles`, `pm_projects.owner_id`, and the four nullable QAT columns already exist. The Super Admin page initializes missing module-toggle records with explicit defaults. Module status changes update `updated_by` and write the existing admin audit log.

New routes are `POST /market-analyst/ai-generate-report`, `GET /admin/trading-modules`, and `POST /admin/trading-modules/{key}`. The latter two require a global Super Admin. Existing PMS project create/update and ticket create/update routes now accept the new owner and optional QAT behavior. The existing `PUT /api/backoffice/pms/projects/{project}` and `POST /api/v1/project-management/updateProjectInfo` expose `owner_id` through their established authorization rules. Disabled mapped TradingHub API routes return HTTP 503 with code `MODULE_DISABLED`; disabled web pages render the dedicated 503 view.

`GEMINI_API_KEY` is required for AI requests. Optional settings are `GEMINI_MODEL`, `GEMINI_BASE_URL`, `GEMINI_TIMEOUT`, `GEMINI_RETRY_TIMES`, and `GEMINI_RETRY_SLEEP` (see `config/services.php`). Verified indicator reuse additionally requires the existing `MARKET_DATA_MODE=REAL`, `MARKET_DATA_PROVIDER=twelvedata`, `TWELVE_DATA_API_KEY`, an enabled AI Market Insight module, and fresh saved XAUUSD results. Form-context AI works without the market-data configuration. No queue worker is required for report generation.

## Verification

- Final full Laravel suite: 315 passed, one pre-existing skip, 7,187 assertions. Focused tests after the last route-map and AI validation refinements: 13 passed, 64 assertions. The broader post-integration focused suite also passed 36 tests and 295 assertions across Market Analyst report/plan, Real Market Insight, module management, Project Owner/QAT, and the updated QAT selector expectation.
- Pint passed on 14 new service, registry, middleware, and test files. Node syntax checks and the existing Market Analyst plan browser-state test passed. Blade `view:cache` passed. The Vite frontend build passed earlier; its generated tracked artifacts were restored to avoid unrelated asset changes. `git diff --check` passed for the touched tracked files.
- `php artisan migrate:status` could not connect to local MySQL at `127.0.0.1:3306`. The automated tests used and migrated the test database successfully. No production migration or deployment was run.

## Deployment and limits

1. Deploy the changed application files using the normal release process. Keep existing production data and environment secrets.
2. Set `GEMINI_API_KEY` and any desired optional AI settings. If verified Market Insight should feed drafts, confirm the existing REAL market-data configuration and scheduled sync are operating. The report generator only reads saved fresh analyses.
3. Run the normal dependency/build process for this Laravel/Vite application, then `php artisan optimize:clear`, `php artisan migrate:status`, `php artisan migrate --force` only if the target has pending migrations from its existing release history, and cache configuration/routes/views according to the hosting setup.
4. Sign in as a global Super Admin at `/admin/trading-modules`; review the 28 switches. Confirm Market Analyst, Trading Signals, and Knowledge Centre disable/re-enable behavior in staging. Create a draft report and a QAT ticket using representative assignments before production rollout.

Known limits: verified data is currently limited to the installed XAUUSD Market Insight instrument and its fresh M15/H1/H4 calculations. AI suggestions are drafts and may fail strict validation, in which case the form is untouched. Exact trade execution prices remain conditional because quote and indicator data alone do not validate entry, stop, target, or account risk. Newly introduced TradingHub routes must be added to the central route registry when their module is registered.
