# TradingHub AI assistant

## Files changed

- Chat: `app/Http/Controllers/AI/GeminiAssistantController.php`, `app/Services/TradingHubAssistantService.php`, `app/Services/GeminiAssistantService.php`, `resources/views/ai/assistant/index.blade.php`, `routes/web.php`.
- Calculations and settings: `app/Services/MarketStructureEvaluator.php`, `app/Services/TradingIdeaRiskCalculator.php`, `app/Support/TradingAiSettings.php`, `app/Http/Controllers/AI/TradingAiSettingsController.php`, `resources/views/admin/ai_knowledge_base/settings.blade.php`, `config/services.php`, `.env.example`.
- Knowledge and storage: `app/Models/AiKnowledgeBase.php`, `app/Http/Controllers/AI/AiKnowledgeBaseController.php`, `resources/views/admin/ai_knowledge_base/{create,edit,index}.blade.php`, `database/migrations/2026_10_25_000001_extend_trading_ai_assistant.php`, `database/seeders/TradingCoachKnowledgeSeeder.php`, `database/seeders/DatabaseSeeder.php`.
- Integration and lifecycle: `app/Support/TradingSubmodules.php`, `app/Console/Commands/PruneTradingAiData.php`, `app/Console/Kernel.php`.
- Tests: `tests/Feature/TradingHubAssistantTest.php`, `tests/Unit/MarketStructureEvaluatorTest.php`, `tests/Unit/TradingIdeaRiskCalculatorTest.php`.

## Architecture

The authenticated `/ai-assistant` feature belongs to the TradingHub `ai_assistant` submodule. Every endpoint checks TradingHub membership, and the existing web middleware continues to enforce onboarding, module toggles and login. It does not query Dealership or Project Management data.

Flow: validate request and account access → retrieve at most the configured number of active, English TradingHub knowledge articles → optionally read authorized, published Market Analyst outlooks and licensed REAL Market Insight snapshots → run deterministic structure, risk or break-even calculations when structured inputs are supplied → call the configured Gemini model → store the conversation and source labels. Journal-specific questions continue through the established journal context builder, which only exposes the authenticated member's calculated statistics.

The assistant never sends raw database records or user identifiers to Gemini. Knowledge and outlook snippets are bounded. There is no broker order or payment integration.

## Database changes

`2026_10_25_000001_extend_trading_ai_assistant.php` adds review metadata to `ai_knowledge_bases` and creates:

- `ai_knowledge_versions`: prior article content and provenance on edits.
- `ai_chat_conversations`, `ai_chat_messages`: account scoped, paginated history.
- `ai_chat_preferences`: account scoped learning and risk preferences.
- `ai_chat_settings`: administrator strategy and usage settings.
- `ai_chat_usage`: request attempt counts without prompt text.
- `ai_chat_feedback`: member ratings tied to owned assistant messages.

Foreign keys delete dependent messages and feedback when a conversation is deleted. An admin “Unpublish” action retains a knowledge record. The `trading-ai:prune` scheduled command removes conversations and usage older than the configured retention period (90 days by default).

`TradingCoachKnowledgeSeeder` inserts reviewed starter articles only when absent, so later administrator edits are preserved. Its five modules cover market structure, trendlines and levels; risk management; TP, SL and break-even; psychology; and trading styles. Each article has a source, level, language, version and review date. Existing journal knowledge remains intact.

## API and administration

All routes below require web authentication and TradingHub membership. The `ai_assistant` switch blocks them when disabled.

| Method and path | Purpose |
| --- | --- |
| `GET /ai-assistant` | Responsive workspace |
| `POST /ai-assistant/ask` | Answer and persist a message pair; 15 requests/minute |
| `GET, POST /ai-assistant/conversations` | Paginated list/search and New Chat |
| `GET /ai-assistant/conversations/{id}/messages` | Paginated messages, owner only |
| `PATCH, DELETE /ai-assistant/conversations/{id}` | Rename or delete, owner only |
| `GET, PUT /ai-assistant/preferences` | Per-member preferences |
| `POST /ai-assistant/structure` | Deterministic H4/H1/M15 interpretation from user inputs |
| `POST /ai-assistant/risk` | Deterministic idea budget, sizing and R:R evaluation |
| `POST /ai-assistant/break-even` | Nominal and cost-adjusted break-even |
| `POST /ai-assistant/feedback` | Rating for an owned assistant message |
| `GET, PUT /admin/ai-settings` | TradingHub administrator policy, usage and feedback |
| `/admin/ai-knowledge-base/*` | Existing knowledge CRUD, now with provenance and version history |

## Provider and market-data configuration

Keep `GEMINI_API_KEY` on the server. Set `GEMINI_MODEL` and comma-separated `GEMINI_ALLOWED_MODELS` in the server environment. Only allowed models appear in administrator settings. `GEMINI_BASE_URL` and `GEMINI_TIMEOUT` retain the existing integration defaults. No key, provider error body, or raw prompt is logged or returned to the browser. Generation is capped at 1,400 output tokens, has a timeout and does not automatically retry a potentially billable request. A daily per-member request limit defaults to 100.

Published Market Analyst reports are selected through `MarketOutlookPublicationService`, including its publication date, active community and history-access rules. A report is historical editorial context, never a live quote. Market Insight is considered only when its module is on, its source is REAL, the current user is allowed to see it under existing distribution settings, and a non-stale saved H4/H1/M15 analysis exists. MOCK data is never presented as live. Missing or stale frames are labeled. No market data is fetched from a provider as a side effect of chat.

The assistant does not interpret uploaded charts and cannot infer structure from an image. The structure check uses clearly labeled user inputs. Position sizing needs verified tick size, tick value in account currency (or conversion), minimum volume and volume step. It returns no size when these are missing.

## Strategy and risk behavior

- H4 is major, H1 internal, and M15 execution structure. An M15 CHOCH alone cannot reverse H1/H4. H1 invalidation triggers reassessment; range middle can return No Trade.
- Default idea risk is 0.5%; the hard ceiling is 1%, and an administrator can reduce it. Already committed risk in related entries consumes the same idea budget.
- The stop must be on the invalidation side of entry. The nearest opposing level is checked before accepting the configured minimum R:R (2 by default); an attractive mathematical TP is never used to bypass a nearer obstacle.
- Position size rounds down to the supplied volume step. Minimum volume that breaches the cap returns No Trade. Cost-adjusted BE accounts for entered costs but is not a guarantee against gaps or slippage.

## Deployment and rollback

Local development verification on 2026-10-25: the new migration is batch 78 in the local MySQL database, and 18 coach articles were seeded. The pre-migration backup is `backups/hcgaming_before_trading_ai_20261025.sql`. The local `tradinghub_ai_assistant` switch was already disabled and was left unchanged; an administrator must enable it through TradingHub Module Management before members can use the page. The local Gemini key is configured. No production release was run.

1. Back up the database and deploy code using the project's normal release process. Confirm the earlier `ai_knowledge_bases` and Market Insight migrations are present.
2. Set the server Gemini key/model/allowlist. Confirm TradingHub and `ai_assistant` module switches and approved member access.
3. Run `php artisan migrate --force` under the deployment procedure. For an isolated migration after dependencies already exist, use `php artisan migrate --path=database/migrations/2026_10_25_000001_extend_trading_ai_assistant.php --force`.
4. Run `php artisan db:seed --class=TradingCoachKnowledgeSeeder --force`. Avoid reseeding the entire database merely for this feature.
5. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` if those are part of the established release process. Ensure the scheduler is running for `trading-ai:prune`.
6. Check desktop/mobile layout, login, one permitted answer, a published outlook, missing live-data handling, risk worksheet and module-off behavior before promoting the release.

To roll back, disable the TradingHub AI Assistant submodule first, back up any conversation data you wish to retain, and run `php artisan migrate:rollback --path=database/migrations/2026_10_25_000001_extend_trading_ai_assistant.php --force`. That migration rollback drops conversation data and new article metadata. Seeded knowledge rows remain in `ai_knowledge_bases`; unpublish them through the admin screen if desired. Restore the prior code and clear/rebuild Laravel caches through the normal release process.

## Known limits

Gemini `generateContent` currently returns complete answers rather than server-streamed tokens. “Stop” aborts the browser request; a provider call already in flight may finish and be billed. The UI displays a loading state and a retry path. Free-text model output is constrained by source/policy instructions, but the deterministic guarantees apply to the structure, risk and BE endpoints. Market Analyst prose is not a complete structured H4/H1/M15 feed. No arbitrary journal rows, Dealership or Project Management data are shared with the model. Browser screenshot verification remains a release check when a browser surface and a test account are available.

The older `/chatbot` keyword endpoint is referenced by a separate commission tutorial and is unchanged to preserve the Dealership boundary. TradingHub's active assistant is `/ai-assistant`.
