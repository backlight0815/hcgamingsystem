# Trading Signal and Market Analysis Publishing Fix

Date: 2026-07-15  
Client release: `1.7.3+1023`  
Firebase project: `hcgaming-31d5d`

## Outcome

The Flutter client, Firestore rules, Storage rules, notifications, and audit
workflow now use one publisher authorization policy. Super Admin can create,
publish, edit, and manage Trading Signals and Market Analysis without also
holding a specialist role. Signal Providers and Market Analysts retain their
module-specific access, while ordinary and missing-role users remain denied.

The publishing forms default the primary content language from the active app
locale, allow the user to change it, require only that selected language, and
keep the secondary translation optional. No automatic translation service is
used.

## Confirmed root causes

### Trading Signal

The signal document write completed successfully, after which the repository
awaited a client write to `audit_logs`. Firestore intentionally denies all
client audit-log writes. That second `permission-denied` exception propagated
back to the form, so a committed signal appeared to have failed and retrying
could create a duplicate.

The signal form also required both English and Chinese title/risk content.
There was no signal chart upload destination in the managed upload allow-list
or Storage rules.

### Market Analysis

The market analysis flow had the same post-commit client audit failure. Its
form always required English title, overview, and trading plan even when the
active locale was Chinese.

### Role mapping

The canonical production roles are numeric: Super Admin `1`, Sub Admin `2`,
Founder `3`, Signal Provider `201`/`202`/`502`, and Market Analyst `501`.
Firestore already recognized role `1`; a different Super Admin string was not
the cause. The application did, however, have duplicated publisher getters
instead of using the central permission service everywhere.

### Storage

Market-analysis storage existed, but signal-image storage did not. Publisher
authorization and explicit permission overrides were also not mirrored across
Firestore and Storage consistently.

## Authorization behavior

| Actor | Trading Signals | Market Analysis |
| --- | --- | --- |
| Super Admin (`1`) | Publish/manage all | Publish/manage all |
| Founder (`3`) | Manage all | Manage all |
| Sub Admin (`2`) | Only with explicit publisher override | Only with explicit publisher override |
| Signal Provider (`201`, `202`) | Publish/manage own | Denied |
| Signal Provider Management (`502`) | Publish/manage all signals | Denied |
| Market Analyst (`501`) | Denied | Publish/manage own |
| Trader, missing role, suspended user | Denied | Denied |

An explicit deny override takes precedence over every role, including Super
Admin. The repository refreshes the publisher profile directly from the
Firestore server immediately before publishing and verifies that the Firebase
Auth UID matches the requested publisher UID.

## Data model and workflow

Existing scalar fields and collection names remain intact. New writes add:

- `primary_language`: `en` or `zh`.
- `available_languages`: only languages with meaningful entered content.
- Existing `*_i18n` sidecars for localized fields.
- Trading Signal `chart_image_url` when an image is supplied.
- `published_by`, `updated_by`, and server timestamps where applicable.

Trading Signal canonical workflow values are `draft`/`0`, `active`/`1`,
`cancelled`/`12`, and `closed`/`13`. Market Analysis uses `draft`, `scheduled`,
`published`, `archived`, and `cancelled`.

Drafts may be incomplete. Activating a signal requires its pair, BUY/SELL
direction, entry, stop loss, first target, primary-language title, and
primary-language risk disclaimer. Publishing market analysis requires its
instrument, primary title, primary overview, and primary trading plan.

Old documents without localization metadata remain readable. Display uses the
central fallback order of active app language, alternate language, then the
legacy scalar value. No data migration or new Firestore index is required.

If an image uploads but the following document write fails, the client attempts
to delete the uploaded object before presenting the original error. Image
cleanup never replaces or hides the publishing error.

## Security changes

Firestore now:

- Resolves publisher roles and explicit overrides through reusable helpers.
- Requires active, non-suspended user profiles.
- Allows Super Admin to publish both modules without a specialist role.
- Preserves `created_by` on updates and prevents forged actor fields.
- Validates status values, localization metadata, and required published data.
- Continues to deny all direct client writes to `audit_logs`.

Storage now:

- Supports `trading_signals/{userId}/...` and
  `market_analyses/{userId}/...`.
- Allows a specialist to manage only their own path and Super Admin to manage
  module paths.
- Honors explicit publisher overrides with deny precedence.
- Accepts only JPG, PNG, WEBP, or GIF images below 8 MB.

Audit records are written by Gen 2 Cloud Functions after document mutations,
so clients never receive audit collection write access. Notification functions
use the same localized fallback fields.

## Error and loading behavior

The submit action is disabled while publishing. A successful primary Firestore
commit is reported as success; the expected denial of the obsolete client audit
attempt is debug-logged and cannot convert that commit into a false failure.
Authentication, missing profile/role, permission, validation, upload, and
Firestore errors use safe user-facing messages. Structured technical details
are restricted to debug builds and do not include tokens or full UIDs.

## Changed files

- `lib/core/security/publishing_authorization.dart` — shared publisher policy.
- `lib/models/app_user.dart` — removed duplicated raw publisher getters.
- `lib/core/localization/publishing_localization.dart` — primary-language and
  language metadata helpers.
- `lib/shared/content_language_selector.dart` — reusable language selector.
- `lib/features/hub/publisher_validation.dart` — status enums and signal price
  validation.
- `lib/features/hub/publisher_sheets.dart` — localized forms, validation,
  loading/errors, chart uploads, and failed-write cleanup.
- `lib/features/hub/trading_hub_screen.dart` — shared publisher authorization.
- `lib/features/admin/admin_management_screen.dart` — permission-gated admin
  mutations, localized editor, drafts, and status mapping.
- `lib/models/trading_signal_item.dart` — localization metadata, chart image,
  and legacy fallback parsing.
- `lib/models/market_analysis_item.dart` — localization metadata and legacy
  fallback parsing.
- `lib/repositories/trading_repository.dart` — server-fresh authorization,
  UID verification, safe publishing/audit semantics, localized admin mapping,
  image management, and debug diagnostics.
- `lib/core/localization/app_localizations.dart` — new EN/ZH publisher messages.
- `firestore.rules` — publisher authorization, actor immutability, statuses,
  and publication validation.
- `storage.rules` — secured signal/analysis image paths.
- `functions/publisher_audit_functions.js` — server-owned audit triggers.
- `functions/index.js` — audit exports and localized notifications.
- `functions/package.json` — syntax/test coverage for publisher auditing.
- `functions/test/publisher_audit_functions.test.js` — audit action tests.
- `functions/test/security_rules_v4.test.js` — publisher Firestore matrix.
- `functions/test/storage_rules_v4.test.js` — publisher Storage matrix.
- `test/publishing_authorization_localization_test.dart` — permission,
  localization, status, validation, model, and selector tests.
- `test/trading_services_test.dart` — centralized publisher policy regression.
- `pubspec.yaml` — release `1.7.3+1023`.

## Verification

- `flutter analyze` on all changed Dart surfaces: no issues.
- Full Flutter test suite: 308 passed.
- Focused publisher suite: passed.
- Functions syntax checks: passed.
- Complete Functions unit suite: passed.
- Firestore emulator rules: passed.
- Storage emulator rules: passed.
- Production deploy: Firestore rules, Storage rules, and four selected Gen 2
  functions completed with zero errors.
- Function state check: `auditTradingSignalWrites`,
  `auditMarketAnalysisWrites`, `notifyTradingSignal`, and
  `notifyMarketAnalysis` are ACTIVE.
- Release AAB signature: verified.
- Release AAB ABIs: `armeabi-v7a`, `arm64-v8a`, `x86_64`; minimum Android API
  remains 23.

No production dummy Trading Signal or Market Analysis was created. Real-account
manual QA was not performed because credentials and an interactive production
device session were not supplied.

## Manual QA before store rollout

Use real Super Admin, Signal Provider, Market Analyst, Trader, and unapproved
accounts. In both English and Chinese app locales, verify draft, publish, list,
detail, edit, close/archive/cancel, author/status, chart upload, and notification
behavior. Cover English-only, Chinese-only, bilingual, missing-primary,
unsupported/oversize image, offline/network loss, expired session, permission
denial, and rapid repeated taps.

## Remaining risks

- Existing installed clients still contain the old post-commit audit behavior;
  distribute `1.7.3+1023` before considering the user-facing rollout complete.
- Production notification delivery and real-account UI behavior remain manual
  QA items because the task prohibited dummy production content.
- Publishing on behalf of another provider was not added because the existing
  app has no verified delegation workflow or provider selector. Adding it would
  require an explicit impersonation/delegation data model and audit policy.
- Image deletion is best-effort after a failed Firestore write. A connectivity
  failure during cleanup can leave an orphan object for later operational
  cleanup, but it cannot create a broken published document.
