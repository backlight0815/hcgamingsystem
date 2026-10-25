# TradingHub AI training: investigation and validation

Verified in the local development workspace on 2026-10-25. This is not a production deployment report.

## Root cause

The former `GeminiAssistantController::ask` requested a provider answer before inserting the conversation and customer question. When that call threw, the controller returned the generic provider error and skipped persistence. The browser therefore correctly reported that the question was not saved. The former service also called Gemini for questions it could answer from approved knowledge. Its generic exception path erased the provider's useful failure category. The historical error cannot be attributed to an invalid key, quota, timeout, or provider outage from the old logs because the relevant HTTP status and response category were not retained. The configured server key and endpoint were present; no paid live provider call was made during this validation.

The new controller saves the accepted question and request usage in one transaction before asking the provider. A client UUID makes a retry return the same question and answer. Published, approved FAQ text and strong matching knowledge articles answer directly. When generation is needed, provider failures are classified and logged without credentials or provider response bodies. A fallback appends the claim that the question was recorded for review only after the review queue write succeeds. If that queue fails, the reply accurately says the question remains in the conversation.

## Scope and local state

The existing Laravel application, TradingHub middleware, sidebar, AI Knowledge Base, and chat route remain in use. Added FAQ training with draft, review, approval, publication, archiving, alternatives, effective dates, version snapshots, rollback, a grouped unanswered queue, response settings, analytics, exports, and admin preview. The chat has a status indicator, saved conversation history, suggested questions, loading and retry states, and responsive CSS. The existing structure, risk, and break-even tools remain on the page. The AI module build moved from 1 to 2.

Local MySQL has the existing assistant migration in batch 78 and new training migrations `000002` and `000003` in batches 79 and 80. The unrelated PMS migration is pending. Existing Knowledge Base records were retained. The local TradingHub AI switch is enabled. No production data or credentials were changed.

## Test matrix

The results below distinguish API and rendered-view checks from manual browser layout checks.

| ID | Scenario and expected result | Actual result | Status |
| --- | --- | --- | --- |
| TC01 | Save a customer question and reload it | API test saves the user message and reloads conversation messages from the database; page render passed. Browser send/refresh could not be completed. | PASS (API), BLOCKED (browser) |
| TC02 | Approved FAQ answers while provider fails | Hybrid-mode test returned the exact approved answer with a faked 503 provider; no provider request was sent. | PASS |
| TC03 | Unknown question is saved as unanswered | Knowledge-only and provider-failure tests persisted the user message and grouped unanswered record. | PASS |
| TC04 | Admin creates a training question and receives an ID | Store request redirected to the saved record's edit URL; record ID and draft content loaded. | PASS |
| TC05 | Admin update persists after reload | Update test verified the new database content and draft status; edit view loads the saved record. | PASS |
| TC06 | Approved and published FAQ answers customers | Submit, approve, publish sequence returned the stored answer and marked the response source as approved FAQ. | PASS |
| TC07 | Draft answer remains private | Customer request before publication returned an unanswered fallback, without draft content. | PASS |
| TC08 | Approved alternative wording resolves to same answer | Approved “What is BE?” alias returned the canonical Break Even answer. | PASS |
| TC09 | Similar keywords do not produce a wrong authoritative answer | Weak overlap and multiple-position cases returned an unanswered fallback rather than a single-position article. | PASS |
| TC10 | Provider fails after saving; question remains | Faked 503 left the user message in history and labeled the provider failure; a simulated queue failure also left the question saved without a false review claim. | PASS |
| TC11 | Retry does not duplicate saved messages | Repeating the client UUID returned the same question ID, with one user message and one assistant message. | PASS |
| TC12 | Customers cannot edit training or settings | Customer requests to the training index, store, and settings update returned 403. | PASS |
| TC13 | Conversation reloads in chronological order | Two question and answer pairs reloaded in the correct order. | PASS |
| TC14 | Desktop, tablet, and mobile layout works without overlap | Blade compiled and responsive rules were inspected. The Windows browser capture helper timed out before screenshots or interaction checks on all target widths. | BLOCKED |
| TC15 | Knowledge-only mode returns approved FAQ without provider | Test returned the FAQ and asserted that no HTTP request was sent. | PASS |
| TC16 | User and module data remain isolated | Cross-user and cross-module conversation requests returned 404; prior risk and feedback isolation tests passed. | PASS |

## Verification and release checks

- Focused training suite: 14 passed, 96 assertions. Existing assistant suite: 8 passed, 42 assertions.
- Full application suite: 385 passed, 1 skipped, 1 unrelated PMS failure, 8,119 assertions. The failing `ProjectManagementV2Test::test_four_and_six_week_templates_create_valid_phase_schedules` expected `analytics.basis = issue count` but received `development-applicable tickets`; it also failed before this AI work.
- Training routes registered; PHP lint, Blade cache, and chat JavaScript syntax checks passed. The migration status is listed above.
- Manual desktop/tablet/mobile screenshots and interaction checks remain a release gate. The computer-use browser helper timed out; this report does not claim that visual QA passed.
- A live provider request, production deploy, and production migration were not run. Provider availability, quota, and production credentials must be checked during release without exposing the key.

Deploy and rollback steps are in `docs/tradinghub-ai-assistant.md`. Back up the database before deployment or rollback; rollback of `000002` removes training fields and the unanswered queue. The previous source branch can also be restored if a code rollback is needed.
