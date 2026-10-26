# HCTradingHub AI: Phase 2 accuracy and coverage extension

## Incremental gap matrix

| Capability | Existing file/API/table | Current behavior before Phase 2 | Missing behavior | Decision | Verification |
|---|---|---|---|---|---|
| Master prompt, chat, retries, feedback, authorization | `TradingAiPrompt`, `GeminiAssistantController`, `ai_chat_*` | Versioned system instruction, messages saved before provider, retry attempts, scoped feedback | None requiring a second implementation | **SKIPPED — already exists and passes tests** | Existing `TradingAiQualityTest`, `TradingAiTrainingTest`, `TradingHubAssistantTest` |
| Risk arithmetic and live-data boundary | `TradingIdeaRiskCalculator`, `TradingHubAssistantService` | Decimal sizing/R:R/BE, unavailable live quote and chart guards | Hypothetical price-distance R in compound wording | Reused calculator; added `priceDistanceR` | New compound case and existing calculator suite |
| Terminology | FAQ alternatives and `AiKnowledgeBase::normalizeQuestion` | Exact normalized questions, English keyword retrieval | Canonical aliases, ambiguity and explicit non-equivalence | Added code-reviewed `trading_ai_concepts` catalog and resolver; no alias articles | Registry and mixed-language tests |
| Multi-intent and follow-up | Assistant service and user-scoped recent messages | Single-question flow with recent context sent to model | Short deterministic compound and follow-up coverage | Added a lightweight internal planner to existing service | BE/five-position, H1, symbol-switch and prior-SL tests |
| Claim evidence and conflict | `ai_chat_messages.sources`, KB version/provenance | Answer-level citations, expiry checks | Stored claim references; differing active concept revisions | Additive evidence JSON, conservative number gate and conflict abstention | Claim storage, unsupported number, conflict tests |
| Advanced curriculum | 45 owner-specified foundation FAQs | 15 core subjects in three languages | Detailed instrument lessons | 24 canonical English FAQ records, each with five question variants and two counterexamples | Idempotent seed and exact/variant answer tests |
| Ingestion | `AiKnowledgeBaseController`, `ai_knowledge_versions` | Draft/review/publish, URL and owner fields | Permission status, content fingerprint, article-specific review date, duplicate/instruction checks | Extended same KB workflow and one migration | Ingestion and expiry tests |
| Unknown questions and expert review | `ai_unanswered_questions`, feedback admin page | Grouped exact normalized questions and feedback review | Concept/intent/gap grouping, severity and evidence view | Extended same queue and page | Paraphrase and severity tests |
| Chat UI, auth and module boundaries | Existing Blade page and routes | Responsive chat, module checks, no order execution | No new route or tab necessary | **SKIPPED — already exists and passes tests** | Existing feature tests |

## What changed

- `config/trading_ai_concepts.php` is a Git-versioned, reviewable registry. `TradingConceptResolver` detects aliases in mixed English, Chinese and Malay and records related and explicitly non-equivalent concepts. `TradingQuestionPlanner` builds internal `sub_intents`, entities, evidence needs, missing inputs, calculation jobs and safety flags. It answers supported compound questions directly, asks one specific clarification for an ambiguous term, and treats prior user inputs as historical/hypothetical only. Raw plans are never sent to clients.
- The existing assistant uses exact approved FAQ first, then a canonical concept FAQ for a simple alias question. It abstains if two active approved records for the same concept and language have divergent fingerprints. It rejects unsupported generated numeric and unverified price claims. Approved knowledge returns an article/version evidence reference; deterministic price-distance R records its calculator method. The record is saved in `ai_chat_messages.claim_evidence` for admin review and omitted from client history/API payloads.
- `TradingAiAdvancedCurriculumSeeder` adds 24 original educational summaries across forex/metals/CFDs, stocks/ETFs/indices, options, futures, crypto, portfolio/quant and macro topics. Each has five alternate questions and two counterexamples. Primary-source URLs are attribution links, not copied training text. In `local` and `testing` the lessons publish for evaluation; in staging/production they seed as drafts for qualified review and publication through the existing AI Training workflow. Rerunning the seeder leaves edits intact.
- The existing article form now records canonical concept, source owner, permission status, source and retrieval dates, and an article-specific next review date. Exact title/content duplicates and instruction-like text are rejected. Primary-source entries require URL, owner and retrieval date. An expired review date removes an article from active retrieval. Earlier versions remain in the existing version table.
- The unanswered queue keeps every private chat message but groups a recognized single concept by concept, intent category and information gap. Conflicting or unsupported output receives higher triage severity. Admin feedback now displays stored evidence references, source version and model alongside the exact historical answer.

## Before and after examples

| Client question | Earlier behavior | Phase 2 behavior |
|---|---|---|
| “Gold M15 BOS lepas tu set BE macam mana?” | Exact FAQ unlikely; provider needed | Brief Malay structure/BE explanation, no claimed current chart |
| “I bought gold at 4300, five positions, SL 4290; move BE at 4310 and what lot?” | One generic answer or provider fallback | Computes hypothetical 1R, explains optional BE and shared idea risk, refuses lot sizing without broker specs |
| “What is margin?” | Keyword retrieval might guess | Asks which margin meaning is intended |
| “Is BOS a liquidity sweep?” | Could conflate related terms | Explicitly distinguishes confirmed close from a sweep |
| “Why does my crypto last price differ from mark price?” | Broad asset-class article | Approved, sourced venue-specific explanation with no claimed live value |

## Local verification and release

Local backup: `backups/hcgaming_before_ai_phase2_20261026.sql` (excluded from the source commit). Migration `2026_10_26_000003_extend_trading_ai_evidence_and_governance.php` was applied separately, leaving an unrelated pending PMS activity migration untouched. The advanced seeder ran locally; 24 distinct canonical FAQ records were present. The Phase 2 suite passed 14 tests and 307 assertions, including a separate 25-question accuracy fixture, without calling the provider. The existing AI training suite passed after the Phase 2 follow-up fixes. Run repository-wide tests as a release gate and review any unrelated failure separately.

On staging, back up the database, deploy the code, apply migration `000003`, run `TradingAiAdvancedCurriculumSeeder`, review every draft with an appropriate trading/product specialist, then approve and publish through AI Training. Check source licensing, product applicability, review dates, English-only advanced fallback, and at least one client example per asset group. No API route, model credential or new provider configuration is required. Prompt version 3 remains the published system instruction; build number 4 identifies the application extension.

Rollback: turn off the AI submodule if necessary, export concept-tagged articles, claim evidence, unanswered triage and feedback first, then restore the prior code. Migration `000003` deliberately refuses `down()` while article fingerprints, claim evidence or concept triage remain. A reviewed data migration or full database restore from the backup is required before dropping those columns. Do not remove historical messages or versions merely to satisfy rollback.

## Limits and review tasks

This is a deterministic knowledge-and-calculation extension, not a self-hosted language model. Open-ended generation still needs the configured Gemini provider. No live quote, order book, current calendar, chart-image parser, broker-spec adapter or multi-provider comparison was added. A question about two differing live quotes therefore abstains; the system cannot reconcile feeds it does not receive. Current market observations require a future authorized adapter with venue, quote type and timestamps.

Claim evidence is exact for approved FAQ/article references and the added calculation method. Generated prose has a conservative unsupported-number/URL gate and answer-level cited-title matching; it does not yet semantically verify every sentence against a primary source. The concept registry is versioned through Git, not administrator-editable. Advanced lessons are English only; the original 45 core FAQs cover English, Simplified Chinese and Malay. Semantic near-duplicate detection, automatic source-license verification, formal A/B experiments, a broader translated advanced corpus and a qualified human accuracy review remain release work. The test benchmark measures these scoped cases, not universal factual accuracy or advisory compliance.
