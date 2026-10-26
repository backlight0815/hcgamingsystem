# TradingHub AI assistant

Build 3 extends this build 2 design with a versioned master prompt, multilingual curriculum, decimal performance calculations and linked answer retries. See `docs/tradinghub-ai-v3-runbook.md` for the current operator and release checklist.

## Files changed

- Chat: `app/Http/Controllers/AI/GeminiAssistantController.php`, `app/Services/TradingHubAssistantService.php`, `app/Services/GeminiAssistantService.php`, `resources/views/ai/assistant/index.blade.php`, `routes/web.php`.
- Calculations and settings: `app/Services/MarketStructureEvaluator.php`, `app/Services/TradingIdeaRiskCalculator.php`, `app/Support/TradingAiSettings.php`, `app/Http/Controllers/AI/TradingAiSettingsController.php`, `resources/views/admin/ai_knowledge_base/settings.blade.php`, `config/services.php`, `.env.example`.
- Knowledge and storage: `app/Models/AiKnowledgeBase.php`, `app/Http/Controllers/AI/AiKnowledgeBaseController.php`, `resources/views/admin/ai_knowledge_base/{create,edit,index}.blade.php`, `database/migrations/2026_10_25_000001_extend_trading_ai_assistant.php`, `database/seeders/TradingCoachKnowledgeSeeder.php`, `database/seeders/DatabaseSeeder.php`.
- Integration and lifecycle: `app/Support/TradingSubmodules.php`, `app/Console/Commands/PruneTradingAiData.php`, `app/Console/Kernel.php`.
- Tests: `tests/Feature/TradingHubAssistantTest.php`, `tests/Unit/MarketStructureEvaluatorTest.php`, `tests/Unit/TradingIdeaRiskCalculatorTest.php`.

## Architecture

The authenticated `/ai-assistant` feature belongs to the TradingHub `ai_assistant` submodule. Every endpoint checks TradingHub membership, and the existing web middleware continues to enforce onboarding, module toggles and login. It does not query Dealership or Project Management data.

Flow: validate request and account access → create or locate the conversation → save the member question with a client message ID → return an exact published, approved FAQ when available → evaluate reliable published knowledge and authorized data → call the configured provider only when the operation mode allows it → save the answer or controlled fallback with its source and status. Provider failure never rolls back an accepted customer question. Journal-specific questions continue through the established journal context builder only when external generation is enabled.

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

`2026_10_25_000002_add_trading_ai_training_and_question_tracking.php` adds FAQ workflow fields to the existing knowledge table, a version metadata snapshot, TradingHub conversation scoping, message idempotency/status/source/timing fields, separate long-text response settings, and a privacy-redacted unanswered question queue. Existing knowledge articles remain `active`; new FAQ entries are visible only when their status is `published`, they have approval metadata, and their effective date has arrived. This was assistant module build 2; the current build is 3.

`2026_10_25_000003_track_trading_ai_unanswered_status.php` records the original AI result (`unanswered` or `failed`) on grouped review questions.

`TradingCoachKnowledgeSeeder` inserts reviewed starter articles only when absent, so later administrator edits are preserved. Its five modules cover market structure, trendlines and levels; risk management; TP, SL and break-even; psychology; and trading styles. Each article has a source, level, language, version and review date. Existing journal knowledge remains intact.

## API and administration

All routes below require web authentication and TradingHub membership. The `ai_assistant` switch blocks them when disabled.

| Method and path | Purpose |
| --- | --- |
| `GET /ai-assistant` | Responsive workspace |
| `GET /ai-assistant/status` | Safe mode and knowledge availability indicator |
| `POST /ai-assistant/ask` | Persist a question first, then answer or fall back; `client_message_id` prevents retry duplicates; 15 requests/minute |
| `GET, POST /ai-assistant/conversations` | Paginated list/search and New Chat |
| `GET /ai-assistant/conversations/{id}/messages` | Paginated messages, owner only |
| `PATCH, DELETE /ai-assistant/conversations/{id}` | Rename or delete, owner only |
| `GET, PUT /ai-assistant/preferences` | Per-member preferences |
| `POST /ai-assistant/structure` | Deterministic H4/H1/M15 interpretation from user inputs |
| `POST /ai-assistant/risk` | Deterministic idea budget, sizing and R:R evaluation |
| `POST /ai-assistant/break-even` | Nominal and cost-adjusted break-even |
| `POST /ai-assistant/feedback` | Rating for an owned assistant message |
| `GET, PUT /admin/ai-settings` | TradingHub administrator policy, usage and feedback |
| `GET /admin/ai-training` | Training dashboard, FAQ list, unanswered queue, history, testing and analytics |
| `GET, POST /admin/ai-training/create`, `POST /admin/ai-training` | Training form and create action |
| `GET, PUT /admin/ai-training/{entry}/edit`, `PUT /admin/ai-training/{entry}` | Review and edit a training entry |
| `POST /admin/ai-training/{entry}/transition` | Submit, approve, publish or archive |
| `POST /admin/ai-training/{entry}/duplicate`, `POST /admin/ai-training/{entry}/rollback/{version}` | Duplicate and restore as draft |
| `POST /admin/ai-training/test`, `GET /admin/ai-training/export` | Preview and CSV exports |
| `/admin/ai-knowledge-base/*` | Existing knowledge CRUD, now with provenance and version history |

## Provider and market-data configuration

Keep `GEMINI_API_KEY` on the server. Set `GEMINI_MODEL` and comma-separated `GEMINI_ALLOWED_MODELS` in the server environment. Only allowed models appear in administrator settings. `GEMINI_BASE_URL` and `GEMINI_TIMEOUT` retain the existing integration defaults. No key, provider error body, or raw prompt is logged or returned to the browser. Generation is capped at 1,400 output tokens, has a timeout and does not automatically retry a potentially billable request. A daily per-member request limit defaults to 100.

Administrator modes are Knowledge Base Only, Hybrid AI (default), and Generative AI. Published FAQ wording is returned directly in all modes. Hybrid also returns a knowledge article directly only when title matching is strong; weak keyword overlaps fall through. A provider failure is classified as configuration, model, connection, authentication, rate limit, server, request or empty response, logged without secrets, and presented to members as a reviewable fallback. Provider enablement, assistant name/role, tone, length, response rules and language fallback are configured in the TradingHub AI settings page. Deterministic FAQ retrieval is controlled knowledge lookup, not model training.

Published Market Analyst reports are selected through `MarketOutlookPublicationService`, including its publication date, active community and history-access rules. A report is historical editorial context, never a live quote. Market Insight is considered only when its module is on, its source is REAL, the current user is allowed to see it under existing distribution settings, and a non-stale saved H4/H1/M15 analysis exists. MOCK data is never presented as live. Missing or stale frames are labeled. No market data is fetched from a provider as a side effect of chat.

The assistant does not interpret uploaded charts and cannot infer structure from an image. The structure check uses clearly labeled user inputs. Position sizing needs verified tick size, tick value in account currency (or conversion), minimum volume and volume step. It returns no size when these are missing.

## Strategy and risk behavior

- H4 is major, H1 internal, and M15 execution structure. An M15 CHOCH alone cannot reverse H1/H4. H1 invalidation triggers reassessment; range middle can return No Trade.
- Default idea risk is 0.5%; the hard ceiling is 1%, and an administrator can reduce it. Already committed risk in related entries consumes the same idea budget.
- The stop must be on the invalidation side of entry. The nearest opposing level is checked before accepting the configured minimum R:R (2 by default); an attractive mathematical TP is never used to bypass a nearer obstacle.
- Position size rounds down to the supplied volume step. Minimum volume that breaches the cap returns No Trade. Cost-adjusted BE accounts for entered costs but is not a guarantee against gaps or slippage.

## Deployment and rollback

Local development verification on 2026-10-25: the base assistant migration is batch 78 and the training migrations are batches 79 and 80 in local MySQL; the existing coach articles remain. The pre-assistant backup is `backups/hcgaming_before_trading_ai_20261025.sql`. The unrelated PMS migration remains pending. The local `tradinghub_ai_assistant` switch is enabled and was left unchanged. No production release was run. See `docs/tradinghub-ai-training-validation.md` for the test matrix and remaining release checks.

1. Back up the database and deploy code using the project's normal release process. Confirm the earlier `ai_knowledge_bases` and Market Insight migrations are present.
2. Set the server Gemini key/model/allowlist. Confirm TradingHub and `ai_assistant` module switches and approved member access.
3. Run `php artisan migrate --force` under the deployment procedure. For an isolated deployment after the base assistant migration is already present, apply `2026_10_25_000002_add_trading_ai_training_and_question_tracking.php` and `2026_10_25_000003_track_trading_ai_unanswered_status.php` in order with `php artisan migrate --path=... --force`.
4. Run `php artisan db:seed --class=TradingCoachKnowledgeSeeder --force`. Avoid reseeding the entire database merely for this feature.
5. Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` if those are part of the established release process. Ensure the scheduler is running for `trading-ai:prune`.
6. Check desktop/mobile layout, login, one permitted answer, a published outlook, missing live-data handling, risk worksheet and module-off behavior before promoting the release.

To roll back, disable the TradingHub AI Assistant submodule first and back up AI tables, especially newly created FAQ answers, messages and the unanswered queue. Revert the code before rolling back migrations `000003` and `000002` in that order, using `php artisan migrate:rollback --path=... --force` for each. Rolling back `000002` removes the new training fields and queue, so restore their data from the pre-rollback backup if needed. Do not roll back the base assistant migration merely to revert this enhancement.

## Known limits

Gemini `generateContent` currently returns complete answers rather than server-streamed tokens. “Stop” aborts the browser request; a provider call already in flight may finish and be billed. The UI displays a loading state and a retry path. Free-text model output is constrained by source/policy instructions, but the deterministic guarantees apply to the structure, risk and BE endpoints. Market Analyst prose is not a complete structured H4/H1/M15 feed. No arbitrary journal rows, Dealership or Project Management data are shared with the model. Browser screenshot verification remains a release check when a browser surface and a test account are available.

The older `/chatbot` keyword endpoint is referenced by a separate commission tutorial and is unchanged to preserve the Dealership boundary. TradingHub's active assistant is `/ai-assistant`.
