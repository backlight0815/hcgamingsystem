# Prop Firm Launch QA Report

Date: 2026-07-10  
Project: HC Trader Flutter mobile app  
Scope: Prop Firm workflow, MT5-synced calculations, recommendation engines, localization, and launch-blocking mobile checks.

## Summary

Automated local QA was expanded for Prop Firm calculations and recommendation logic. Live backend, live MT5 terminal, Cloud Functions, admin review, and Android device smoke tests were not run because this local workspace does not include production credentials, a connected MT5 terminal, or a physical test device.

## Automated QA Completed

- `flutter test test/trading_services_test.dart`: PASS, 50 tests.
- `flutter test`: PASS, 68 tests.
- Prop Firm calculator:
  - MT5 deal/position dedupe for closed records.
  - Phase 1 in-progress metrics.
  - Phase 2 passed metrics.
  - Funded account status.
  - Daily loss breach.
  - Maximum loss breach.
- MT5 data model:
  - Account key parsing.
  - Connected/stale/disconnected sync states.
  - Deal-only and position/deal duplicate handling.
  - Public wrong-account mismatch messaging.
- Recommendation engines:
  - Trading Strategy supports multiple selected strategy types.
  - Trading Strategy supports mixed market conditions.
  - Trading Plan supports multiple selected psychology issues.
  - Trading Plan preserves multiple selected strategies as allowed strategy names.
- Localization:
  - Chinese key coverage for new Strategy and Plan questionnaire labels.
  - Trading Plan Recommendation and Trading Strategy Recommendation generated text now localizes through the existing `AppLocalizations` lookup, including dynamic generated lines such as allowed-strategy summaries, technical-method summaries, account type labels, and multi-strategy rule output.
  - Saved generated recommendation records render in the current app/phone locale where their saved values match known recommendation templates.

## Trading Plan / Strategy Localization Sweep

Date: 2026-07-10  
App version: 1.6.20+1007  
Scope: Trading Plan Recommendation and Trading Strategy Recommendation questionnaire, preview/save flow, saved list/detail rendering, validation/feedback labels, chips, dropdown labels, empty states, generated recommendation body text, and backward compatibility with old saved records.

Result: PASS for local automated/static QA.

- Recommendation save behavior:
  - Unedited generated recommendation fields are saved in canonical English template text so the detail/list screens can localize them later.
  - User-edited custom text is preserved exactly as entered.
  - Questionnaire answer labels are stored canonically instead of storing the currently displayed locale text.
- Display behavior:
  - Generated recommendation preview/edit fields now use the current app/phone locale for generated values. Chinese mode displays Chinese generated recommendation content; English mode displays English generated recommendation content.
  - Trading Plan list/detail now localizes generated plan names, market/timeframe chips, linked strategy names, preview text, detail rows, rule lists, and warning lists at render time.
  - Trading Strategy list/detail now localizes generated strategy names, entry-model previews, objective/body blocks, checklist rows, and warning rows at render time.
  - Strategy detail now includes a dedicated `Risk & Avoid Conditions` section to make risk, psychology, and no-trade constraints easier to scan.
  - Manual-created Trading Plan and Trading Strategy content is not translated; user-entered values are displayed exactly as entered.
- Backward compatibility:
  - Empty or missing legacy fields fall back to localized defaults such as `Not specified`, `No rule added`, and `No warning added`.
  - Old records saved with known English recommendation templates localize in Chinese mode.
  - Old records with custom free-form user text do not crash and remain displayed as entered.
- Localization keys added/covered:
  - `Trading Plan Recommendation`, `Generate Trading Plan`, `Edit Recommended Plan`
  - `Trading Strategy Recommendation`, `Edit Recommended Strategy`
  - `Trading Plan Saved Successfully`, `Trading Strategy Saved Successfully`
  - `Failed To Save Trading Plan`, `Failed To Save Trading Strategy`
  - `No Trading Plan Found`, `No Trading Strategy Found`, `Please Answer Required Questions`
  - `Risk & Avoid Conditions`, `Psychology Notes`, `Linked Trading Plan`, `No linked trading plan`
  - Generated-rule strings for FOMO, revenge trading, stop-loss movement, capital protection, trending/ranging/breakout/reversal/high-volatility strategy rules.

## Manual / Live QA Not Run

- Live Firebase Auth, Firestore writes, and security rules.
- Cloud Function review/notification flow for Prop Firm admin decisions.
- Live MT5 terminal sync, broker account mismatch handling against a real terminal, and real-time refresh latency.
- Android physical device smoke test.
- Phone/emulator visual locale switching across the full recommendation flow.
- Real push notification delivery.
- Production backend load, concurrency, or failover tests.

These require credentials, deployed backend services, a configured MT5 terminal, and/or device/emulator access.

## QA Matrix

| Area | Status | Evidence |
| --- | --- | --- |
| Prop Firm phase 1 calculations | Pass | Automated MT5 calculator test |
| Prop Firm phase 2 pass | Pass | Added automated MT5 calculator test |
| Prop Firm funded status | Pass | Added automated MT5 calculator test |
| Daily loss breach | Pass | Added automated MT5 calculator test |
| Maximum loss breach | Pass | Existing automated MT5 calculator test |
| MT5 dedupe / mixed records | Pass | Automated deal/position dedupe test |
| Manual vs MT5 journal separation | Pass | Automated source separation tests |
| Strategy recommendation multi-select | Pass | Added automated engine test |
| Plan recommendation multi-psychology | Pass | Added automated engine test |
| Plan multiple linked strategies | Pass | Existing model support plus generated-save population |
| Chinese localization coverage | Pass | Automated key existence test |
| Trading Plan Recommendation generated localization | Pass | Automated localization test plus static render/save inspection |
| Trading Strategy Recommendation generated localization | Pass | Automated localization test plus static render/save inspection |
| Saved recommendation backward compatibility | Pass | Render-time localization/fallback review |
| Live backend writes | Not run | Credentials/services not available locally |
| Live MT5 sync | Not run | Terminal/credentials not available locally |
| Admin review workflow | Not run | Requires deployed backend and admin account |
| Android install/smoke test | Not run | Device/emulator not part of this local run |

## Bugs Found And Fixed

- Strategy recommendation only treated market condition as a single answer. Fixed with multi-answer questionnaire support and mixed-market rule output.
- Strategy recommendation lacked multiple selected strategy types. Fixed with multi-select strategy types and saved strategy metadata.
- Trading Plan recommendation only modeled one psychology weakness. Fixed with multi-issue selection and issue-specific plan rules.
- Generated Trading Plan did not populate multiple allowed strategy names. Fixed by saving recommendation-selected strategies into `allowedStrategyNames`.
- Duplicate Chinese localization key for `Fear of losing` was caught by test compilation and removed.
- Recommendation preview screens could save generated Chinese text when the app was in Chinese, which prevented later locale switching from recovering English. Fixed by saving unchanged generated fields as canonical English and keeping only genuinely edited custom text as entered.
- Trading Plan and Trading Strategy list/detail screens still rendered several saved generated blocks raw. Fixed by translating known generated text at render time and by localizing multi-line rule/checklist bodies line-by-line.
- Trading Strategy detail risk/psychology constraints were scattered across other cards. Fixed with a dedicated `Risk & Avoid Conditions` section.

## Known Issues / Residual Risk

- Live MT5 sync, live backend writes, admin review Cloud Function behavior, and real push notifications still need environment QA.
- The QA report does not certify broker-side data accuracy beyond simulated MT5 records.
- Android release bundle was generated locally, but device install, phone/emulator visual locale switching, and Play Console validation still need environment QA.
- Free-form custom English written by users is intentionally not machine-translated; only known app labels and known generated recommendation templates are localized.

## Final Command Suite

- `flutter clean`: PASS with Windows file-lock warnings while removing `build` and `.dart_tool`.
- `flutter pub get`: PASS.
- `flutter analyze`: PASS, no issues found.
- `flutter test`: PASS, 68 tests.
- `flutter build appbundle --release --target-platform android-arm,android-arm64,android-x64`: PASS.

Release artifact:

- `build/app/outputs/bundle/release/app-release.aab`
- `releases/HC-Trader-1.6.20-build-1007-recommendation-localization-all-devices.aab`
- Size: 53.8 MB, 56,443,829 bytes.

## Release Recommendation

Conditional pass for local automated logic. Do not mark production launch complete until live backend, live MT5 sync, admin review, and Android device smoke tests are run in the target environment.

## Admin / Main MT5 / Account Deletion Release Sweep

Date: 2026-07-10  
App version: 1.6.21+1008  
Scope: account deletion, admin user creation/deleted-user audit, main MT5 account scoping, journal source separation, Account/Prop Firm/Statistics MT5 data source behavior, announcement UI, Trader Readiness localization, recruitment weekly referral codes, Firestore rules, Cloud Functions, and AAB build.

Result: PASS for local automated/static QA.

- Main MT5 source of truth:
  - Added `users/{uid}.main_mt5_account_key`, `mainMt5AccountKey`, `main_mt5_account_id`, and `mainMt5AccountId`.
  - Added `users/{uid}/mt5_accounts/{accountKey}.is_primary` / `isPrimary`.
  - `TradingDataBuilder` now scopes MT5 mode to the selected main MT5 account only.
  - Journal MT5 account selection updates the same main-account fields.
  - Account capital in MT5 mode reads the main MT5 balance/equity and disables manual capital edits.
  - Prop Firm filters challenges to the main MT5 account and uses that account for metrics.
  - Statistics continues through `TradingDataBuilder`, so MT5 statistics are now main-account-only.
- Manual versus MT5 journal:
  - Data source selector labels are now `Manual Trading Journal` and `MT5 Synchronized Journal`.
  - Manual mode keeps manual journal and Excel-import records only.
  - MT5 mode streams only the selected main MT5 account.
  - MT5-derived journal cards are view-only in the main journal list; manual edit/delete remains manual-only.
- Account deletion:
  - Added callable `deleteCurrentUserAccount`.
  - Backend writes `deletedUsers/{uid}`, removes username/referral relationship/downline references, disables owned referral codes, recursively deletes `users/{uid}` active data/subcollections, deletes the Firebase Auth user, and writes `admin_audit_logs`.
  - Settings has a `DELETE` confirmation flow and signs out after completion.
  - Admin has a `Deleted Users` audit tab.
- Admin user creation:
  - Added callable `adminCreateUser` with role assignment checks.
  - Admin Users tab now has a create-user dialog.
  - Backend creates Firebase Auth user, username reservation, Firestore user profile, normal referral code, and audit log.
- Recruitment:
  - Added callable `ensureWeeklyRecruitmentReferralCode`.
  - Recruitment screen displays weekly and permanent referral codes.
  - Weekly referral codes store `code_type: weekly_recruitment`, `week_key`, and `expires_at`; normal codes remain supported.
- Localization/UI:
  - Trader Readiness seed items now include Chinese `*_i18n` content.
  - Announcement list/detail cards were redesigned.
  - New visible strings were added to `AppLocalizations`.
  - Strategy detail no longer repeats invalid setup warnings inside `Suitable Market Condition`; invalid warnings remain in `Invalid Setup Conditions`.
  - Trading Plan/Strategy detail label rows now stack on narrow mobile layouts.

Firestore rules/security changes:

- `ownerProfileUpdate()` now allows only the new main MT5 profile fields in addition to existing owner-safe fields.
- Added `deletedUsers/{userId}` rule: admin read only, no client writes.
- Direct owner delete of `users/{uid}` is no longer allowed; self-delete must go through the callable.
- Existing MT5 subcollection owner/admin rules remain in place; callable backend writes bypass rules through Admin SDK.

Migration/backfill notes:

- No blocking migration is required. If a user has no stored main MT5 account, the app resolves the first `is_primary` active MT5 account, otherwise the first active MT5 account.
- Recommended optional backfill:
  - For each `users/{uid}/mt5_accounts`, choose the desired active account.
  - Set that account `is_primary: true` and `isPrimary: true`.
  - Set all other active MT5 accounts `is_primary: false` and `isPrimary: false`.
  - Set `users/{uid}.main_mt5_account_key`, `mainMt5AccountKey`, `main_mt5_account_id`, and `mainMt5AccountId` to the selected account key.
- Deploy the updated Cloud Functions before enabling account deletion/admin-create/weekly-code UI in production.
- Publish the updated Firestore rules before relying on mobile main-MT5 writes.
- No new composite Firestore index was required for this release.

Verification:

- `npm run check`: PASS.
- `npm test`: PASS, referral registration tests.
- `flutter clean`: PASS with Windows file-lock warnings while removing `build` and `.dart_tool`.
- `flutter pub get`: PASS.
- `flutter analyze`: PASS, no issues found.
- `flutter test`: PASS, 68 tests.
- `flutter build appbundle --release`: PASS.

Release artifact:

- `build/app/outputs/bundle/release/app-release.aab`
- `releases/HC-Trader-1.6.21-build-1008-admin-mt5-localization-all-devices.aab`
- Size: 53.9 MB, 56,515,742 bytes.

Manual QA still required:

- Deploy and test Cloud Functions in Firebase project.
- Deploy and test Firestore rules.
- Test self-delete with a real user account and confirm Auth deletion plus `deletedUsers` audit.
- Test admin-created user login on a real device.
- Test live MT5 account switching and main-account scoping with real synchronized data.
- Test Play Console upload/device coverage validation.

## Deleted Users Permission Hotfix

Date: 2026-07-10  
App version: 1.6.22+1009  
Issue: Admin Backend > Deleted Users displayed `[cloud_firestore/permission-denied] Missing or insufficient permissions`.

Fix:

- Added callable `adminListDeletedUsers` so the tab loads deleted-user audit records through Cloud Functions with server-side admin validation.
- Kept a direct Firestore fallback for environments where the updated rules are deployed before the function.
- Updated `deletedUsers/{userId}` Firestore rule from `isSuperOrSubAdmin()` to `isAdmin()` so it matches the Admin Backend visibility logic.
- Added localized error text for undeployed backend/rules.

Verification:

- `npm run check`: PASS.
- `npm test`: PASS.
- `flutter analyze`: PASS, no issues found.
- `flutter test`: PASS, 68 tests.
- `flutter build appbundle --release`: PASS.

Release artifact:

- `releases/HC-Trader-1.6.22-build-1009-deleted-users-permission-fix-all-devices.aab`
- Size: 53.9 MB, 56,520,589 bytes.

## MT5 Account Selection Persistence Hotfix

Date: 2026-07-10  
App version: 1.6.23+1010  
Issue: Selecting another MT5 account from Trading Journal > Synced MT5 Trades redirected back to the Trades tab, and returning to MT5 synchronization did not keep the selected MT5 account fixed.

Fix:

- Replaced the journal screen's rebuild-scoped tab controller with a persistent `TabController`, so user/profile refreshes no longer reset the journal tab to Trades.
- Persisted the selected MT5 account key inside local MT5 sync settings.
- Preserved the selected MT5 account across link, load, refresh, and unlink flows without changing manual trading plan, strategy, or journal data.

Verification:

- `dart format lib/features/journal/journal_screen.dart`: PASS.
- `flutter analyze`: PASS, no issues found.
- `flutter test`: PASS, 68 tests.
- `flutter build appbundle --release`: PASS.

Release artifact:

- `build/app/outputs/bundle/release/app-release.aab`
- `releases/HC-Trader-1.6.23-build-1010-mt5-account-selection-fix-all-devices.aab`
- Size: 53.9 MB, 56,525,112 bytes.

## MT5 Capital Balance Hotfix

Date: 2026-07-10  
App version: 1.6.24+1011  
Issue: Home showed the selected MT5 account's synced closed-trade P/L as current capital. For JJ this displayed `44.92u`, while the linked MT5 account balance/equity was `1036.16 USD`.

Fix:

- Home now uses the selected main MT5 account `balance` for current capital in MT5 mode, with `equity` as fallback.
- MT5 money values on Home, Capital, and Account overview now display the account currency, for example `1,036.16 USD`, instead of the manual journal `u` suffix.
- Manual mode still calculates capital from manual deposits, withdrawals, and journal P/L.
- Added unit coverage for MT5 capital balance fallback and currency formatting.

Verification:

- `dart format`: PASS.
- `flutter analyze`: PASS, no issues found.
- `flutter test`: PASS, 68 tests.
- `flutter build appbundle --release`: PASS.

Release artifact:

- `build/app/outputs/bundle/release/app-release.aab`
- `releases/HC-Trader-1.6.24-build-1011-mt5-capital-balance-fix-all-devices.aab`
- Size: 53.9 MB, 56,530,316 bytes.
