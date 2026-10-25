# Dynamic Account Trading Sequence Planner

Implementation date: 2026-07-21

## 1. Summary of the sequence-planning enhancement

The existing Multi-Account Prop Firm Strategy now has an additive seventh workspace, **Sequence**. The original Overview, Portfolio, Control room, Planners, Learn, and Health areas remain in their prior order and retain their existing behavior. Sequence lets the user choose an eligible starting account, ranks the remaining accounts with transparent safety rules, displays conditional result branches and stop conditions, supports audited manual ordering, and saves policies locally for later review.

This is decision support only. It does not connect to or operate any trading account.

## 2. Files created

- `lib/features/multi_account_prop_firm/domain/account_sequence_models.dart`
- `lib/features/multi_account_prop_firm/domain/account_sequence_service.dart`
- `lib/features/multi_account_prop_firm/presentation/account_sequence_ui.dart`
- `lib/core/localization/account_sequence_translations.dart`
- `test/account_sequence_service_test.dart`
- `test/account_sequence_controller_test.dart`
- `test/account_sequence_widget_test.dart`
- `docs/dynamic-account-trading-sequence-planner-2026-07-21.md`

## 3. Files modified

- `lib/features/multi_account_prop_firm/data/prop_firm_portfolio_repository.dart`: added owner-scoped sequence workspace load/save/delete using a separate schema-v1 storage key.
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart`: added sequence state, generation, confirmation, start, skip, stop, result-policy, override, template, reset, import, deletion, and persistence lifecycles.
- `lib/features/multi_account_prop_firm/presentation/prop_firm_planner_screen.dart`: appended Sequence after Health without changing the first six destinations.
- `lib/core/localization/app_localizations.dart`: registered sequence translations in the existing localization fallback chain.
- `docs/multi-account-prop-firm-planner-2026-07-21.md` and `docs/multi-account-health-payout-decision-engine-2026-07-21.md`: clarified the additive seven-destination navigation.

## 4. New models and enums

Added `SequenceMode`, `SequenceAccountRole`, `SequenceStepStatus`, `SequenceTiePreference`, `SequenceAfterTpAction`, `SequenceAfterSlAction`, `SequenceAfterBreakevenAction`, and `DataCompleteness`.

Added serializable models for sequence reasons, score components, account evaluations, sequence steps, exclusions, stop conditions, conditional branches, plans, settings, templates, validation results, and the owner-scoped sequence workspace. Templates persist policies and roles rather than stale daily health results. A `oneAccountOnly` policy supports the one-account-then-stop template.

## 5. Sequence-generation logic

`AccountSequenceService` separates portfolio-stop evaluation, per-account eligibility, health scoring, ranking, tie handling, recovery handling, branch construction, stop-condition construction, validation, and update explanations. Hard exclusions run first. If the requested starting account is eligible it is fixed at position one; otherwise the service chooses the healthiest eligible alternative and explains the replacement. Every regeneration produces a fresh immutable plan from current portfolio data.

## 6. Eligibility rules

Accounts are blocked for portfolio stop, breach, archive, daily lock, portfolio lock, manual pause, active cooldown, invalid critical values, reached trade count, insufficient daily or maximum-loss room, prohibited simultaneous exposure, a session skip, payout protection, or a user-assigned unavailable role. Missing equity is critical for equity-based drawdown accounts. Breached, archived, invalid-data, and portfolio-stop restrictions cannot be overridden. Only a payout-protection-only exclusion can be deliberately overridden, and it requires a recorded reason.

## 7. Health-scoring logic

The score is an explainable 0–100 planning score:

- daily-loss buffer: 30 points;
- maximum-loss buffer: 30 points;
- payout/protection condition: 15 points;
- recent loss streak: 10 points;
- trades completed today: 5 points;
- psychology and cooldown: 10 points.

The UI exposes every component as earned points over maximum points. It also displays normalized loss usage, absolute remaining room, positive factors, risk factors, eligibility, health state, and data completeness. The score is not presented as a profitability prediction.

## 8. Ranking and tie-breaker logic

The default ranks by health score, normalized remaining daily-loss percentage, normalized remaining maximum-loss percentage, recent usage, configured priority, trade count, and a deterministic account ID fallback. Raw currency loss is contextual data, not the primary comparator. Fixed rotation, recovery-last, least-recently-used, payout-protection, healthiest-next, and custom modes are supported.

Within the configurable default five-point tie range, the user can show alternatives or resolve the tie by least recent use, largest daily buffer, largest overall buffer, or custom order. No random rotation is used. Emergency and recovery roles are conservatively placed after ordinary roles unless the user is in explicit custom mode, where validation still applies.

## 9. Payout-protection exclusions

An account is excluded when its account or computed health state is payout protected, payout ready/eligible, waiting for the payout date, or when the user assigns a payout-protected role. The exclusion appears under **Excluded from this sequence** with its exact reason. A permitted override stores the explanation in sequence settings and the existing discipline audit trail.

## 10. Recovery-account handling

Recovery accounts receive a risk factor and a lower psychology/cooldown score. Recovery-last mode and an explicit recovery role place them behind healthy ordinary accounts. Custom validation warns and requires an audit reason when a recovery account is placed before a materially healthier account. No recovery rule raises risk automatically.

## 11. Result-based sequence branching

The visual decision tree shows TP, SL, break-even, daily-target, and portfolio-stop branches. Configurable TP, SL, and break-even actions are persisted and immediately update the branch explanation. Recorded results reuse the existing balance, payout, health, transition, cooldown, and trade-plan logic, then regenerate the sequence. Configured SL lock/cooldown policies are applied; a configured SL portfolio stop locks all remaining usable accounts. TP protection-for-day is enforced through the existing day-resettable account lock. Movement to another account is never automatic.

## 12. Sequence stop rules

The planner displays portfolio daily loss, maximum trades, maximum consecutive losses, emotional risk, repeated invalid setup, no eligible account, payout-protected-only remainder, insufficient remaining planned risk, and manual stop conditions. Existing portfolio calculations remain authoritative. A plan cannot be confirmed when the portfolio is stopped or no eligible step exists. Confirmation is invalidated when fresh account conditions change the order before session start.

## 13. Screens and widgets added

The dedicated Sequence workspace includes:

- date/status/mode/update header;
- explicit system-recommendation action and selectable account cards;
- account balance, current and daily P/L, loss usage/room, payout state, health, score, eligibility, and restriction data;
- desktop timeline and mobile vertical conditional sequence;
- exact exclusion list;
- score-breakdown comparison panels;
- four-part explanation panel;
- conditional decision tree and portfolio stops;
- data-quality card;
- mode, tie, and result-action settings;
- confirmation/session controls, existing trade planner and result recorder, reassessment, structured skip reasons, pause, stop, and emergency lock;
- drag-and-drop custom editor with roles, validation, warnings, and required override reasons;
- built-in quick policies and owner-scoped saved templates.

## 14. Localization keys added

`accountSequenceZh` contains more than 140 sequence-specific Simplified-Chinese entries covering the workspace, status and role labels, score explanations, hard exclusions, validation warnings, branches, stop rules, result actions, skip reasons, payout states, template controls, and the no-automation disclaimer. Placeholder replacement continues to use the existing `AppLocalizations.text` mechanism. English remains the source/fallback language.

## 15. Unit tests added

Service tests cover selected-first preservation, ineligible replacement, healthier and higher-loss ranking, normalized different-size comparison, equal-health alternatives, all configured tie behavior used by the service, payout exclusion, recovery-last, locked/breached exclusion, least recent use, one-account mode, unavailable roles, incomplete and invalid data, one/no eligible account, different currencies, unsafe custom validation, balance-driven order changes, and payout-driven exclusion changes.

Controller tests cover separate local persistence, confirmation recheck, first-account-only activation, TP and SL recalculation, configured SL portfolio stop, audited skip/reset lifecycle, payout override validation/audit, custom roles/order/templates, and confirmation invalidation after an eligibility change.

## 16. Widget and integration tests added

Widget tests exercise the English-to-Chinese sequence localization path, desktop navigation into the additive workspace, selection of Account B as the fixed first step, confirmation and session start, payout-protected exclusion text, custom drag-and-drop editor access, and the seventh mobile destination with its responsive sequence screen. Existing full-suite tests continue to cover the original planner, Health, payout, recovery, Control Room, imports, resets, navigation, and unrelated application modules.

## 17. Test results

- `flutter analyze`: passed with no issues.
- `flutter test`: passed, **486/486** tests.
- Focused sequence service/controller/widget tests: passed, **32/32** tests.
- `flutter build web`: passed; output `build/web`.
- `flutter build windows`: passed; output `build/windows/x64/runner/Release/hc_trader.exe`.

The web build reports the existing Wasm dry-run incompatibilities in `flutter_secure_storage_web` and the third-party `image` package; the normal JavaScript web build succeeds.

## 18. Manual QA results

Responsive widget QA verified a 1280×1050 desktop workspace and a 390×844 mobile workspace without Flutter exceptions. The desktop path selected Account B, confirmed it remained first, started the session, and highlighted it as current. The payout path showed Account C under exclusions and opened the custom editor. The mobile path exposed seven destinations and opened Sequence at index seven. Persistence tests reloaded sequence selection and templates from a new controller instance. The existing running Windows debug application and its local portfolio were not stopped or overwritten; the release build used a separate output path.

## 19. Known limitations

- All account information is user-entered/local; stale inputs produce stale guidance until the next edit, reset, or recalculation.
- The sequence uses the existing portfolio's open-plan safeguards; it cannot inspect live broker exposure.
- Daily-target behavior is conditional on the existing planner data and does not invent a target that the portfolio has not configured.
- Templates and sequence audit state are local to the signed-in owner on the device and are not cloud-synced.
- Standard web builds work, but Wasm remains limited by existing third-party package compatibility.

## 20. Selected-first confirmation

Confirmed: an eligible user-selected starting account is always retained at sequence position one. It is replaced only when current hard eligibility rules block it; the replacement and reason are displayed. This invariant is covered at service, persistence/controller, and widget-workflow levels.

## 21. Unhealthy-account ordering confirmation

Confirmed: after the fixed starting account, healthier eligible accounts rank before accounts with higher normalized daily/overall loss usage, thinner remaining buffers, loss streaks, recovery state, payout risk, cooldown/psychology concerns, or more trades. Hard-restricted accounts are excluded instead of merely ranked later. The full score breakdown and per-step reasons are visible.

## 22. No automatic trading or MT5 connection

Confirmed: this enhancement adds no MT4/MT5 integration, broker connection, credential collection, trade copying, account switching, automatic order placement, or automatic trade execution. Planning trades and recording results continue to use the existing manual planner dialogs.
