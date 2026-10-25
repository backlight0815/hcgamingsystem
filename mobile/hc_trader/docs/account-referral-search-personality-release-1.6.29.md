# Account, referral, search and personality release 1.6.29

Release version: `1.6.29+1016`  
Prepared: 2026-07-14 (Asia/Singapore)  
Android package: `com.hcgaming.hc_trader`  
Firebase project: `hcgaming-31d5d`

## 1. Architecture summary

The implementation keeps presentation, deterministic business logic and
privileged operations separate:

- Flutter widgets own interaction states and localized presentation.
- Reusable Dart services own toolkit ranking, referral resolution caching,
  personality scoring/guidance and deletion orchestration.
- Repositories own local/Firestore persistence and Cloud Function calls.
- Firestore and Storage rules enforce ownership and administrative boundaries;
  hiding a control is never treated as authorization.
- Callable Firebase Functions use the authenticated UID as the authority for
  registration, upline lookup and account deletion. User-supplied UIDs are not
  trusted for these operations.
- The existing Laravel application is not a second source of truth for these
  Firebase-native features. Its regression suite was still run to rule out
  integration regressions.

## 2. Implemented features

### Ranked Trading Toolkit search

`ToolkitSearchService` normalizes case/spacing and applies stable ranked
matching across internal title, localized title, prefixes, partial phrases,
category, keywords, tags, aliases and description. Multiword queries may match
across fields; Chinese substring matching is supported. Authorization and
category filters are applied before ranking so restricted tools do not leak
through search results. The UI uses a 250 ms debounce and has pending, count,
clear and no-result states.

### Authoritative upline display and immutable referral assignment

Profile displays a read-only upline row with loading, error/retry, no-upline,
missing-record and unavailable-upline states. The callable reads the
authoritative `referral_relationships/{currentUid}` document and returns only
relationship status plus the active upline's public display name. Private
profile/referral fields are not returned.

Referral entry exists only for a signed-out registration flow. Registration
normalizes and validates the referral code transactionally, rejects
self-referral, checks purpose/role/availability, and creates the relationship
with the new account. The legacy recruit-existing-account callable now rejects
existing-account assignment. Client rules prevent a user from changing
referral identity or writing authoritative relationship documents.

### Retry-safe exhaustive account deletion

The Flutter flow requires explicit `DELETE` confirmation and provider-aware
recent authentication, reports progress, prevents duplicate taps, attempts
notification unregister, invokes the trusted callable, then clears user-scoped
local drafts/caches and secure API credentials only after remote success.

The callable requires an Auth token whose `auth_time` is no older than five
minutes. It uses resumable `account_deletion_jobs`, records only a salted SHA-256
deletion reference, completes idempotent non-PII audit writes, and makes Firebase
Authentication deletion the final operation. A retry resumes incomplete steps;
if the prior completion commit succeeded, it checks whether Auth still exists
before returning.

Deletion/anonymization inventory:

- Firebase Storage prefixes: `users`, `profile_images`, `support_tickets`,
  `market_analyses`, `knowledge_centre`, `community_documents`,
  `recording_materials`, and `admin_assets` for the UID.
- Legacy `quiz_submissions` objects whose path or owner metadata identifies the
  user, plus managed-area objects whose `uploaded_by`/`owner_uid` metadata
  identifies the user.
- The complete `users/{uid}` Firestore tree, including personality attempts,
  journal data, tokens, private records and nested data.
- Usernames, owned/used referral codes, the user's relationship and all
  denormalized downline membership. Downlines keep a hashed relationship
  reference with `upline_unavailable`; they are never reassigned.
- Access requests, MT5 sync jobs, support tickets and subcollections, delete
  requests, recruitments where the user was recruited, analyst follow rows and
  counts, notification rows, direct announcement targeting/read markers,
  analyst profiles and appointment-host roots.
- Shared appointments, slots, recruiter-owned records, published content,
  staff support messages and audit history are retained only where business
  integrity requires it and have user IDs/names/messages/attachments removed
  or replaced by the non-identifying deletion reference.
- Firebase Auth is removed last. User-scoped SharedPreferences and secure API
  token storage are removed on the device after server success; device-wide
  preferences such as language remain.

Minimal retained records are `deletedUsers/{deletionReference}`,
`account_deletion_jobs/{deletionReference}` and the idempotent self-deletion
admin audit row. They contain status/timestamps/schema, processing steps,
source/category and a salted hash—not a raw UID, name, email, answers or files.

Production should configure a stable high-entropy `DELETION_AUDIT_PEPPER` for
the Functions runtime before deployment. Project ID/default fallback exists to
keep retries deterministic, but the secret pepper is preferred. Rotating it
would change future references and should be handled as an audit-key rotation.

### Self-Personality Test redevelopment

The new assessment is an original 32-question, seven-point Likert instrument.
It has eight questions for each primary dimension (E/I, S/N, T/F and J/P), four
reverse-scored questions per dimension, and a weighted tie-breaker item per
dimension. The flow includes introduction/disclaimer/time estimate, one
required question per page, progress, automatic draft saving, resume, explicit
submission, result history, answer review and retake. Completed attempts remain
immutable; a retake creates a new attempt and changes the active pointer only
after successful completion.

All 16 result types have original summaries and structured strengths,
challenges, decision/planning/learning/communication/stress guidance. Trading
suggestions are optional. Applying selected guidance creates a separately
versioned local draft and never overwrites a Trading Plan or Trading Strategy.

Authorized administrators now have a dedicated content screen to create or
seed question-set versions, add/edit/deactivate questions, manage English and
Simplified Chinese text, dimension/direction/reverse scoring/weight/order/group,
preview the seven-point assessment and publish a validated version. Normal
users cannot access draft configuration. Published documents and nested
questions are immutable; changes require a new version.

The active shipped client scoring contract is deliberately pinned to
`personality-16-v1`. Later admin-published sets are staged and immutable for
review; activation must ship with compatible client scoring/guidance support.
This prevents an administrator from silently changing historical meaning or
making older clients score an unknown version.

## 3. Personality scoring definition

For answer `a` in 1–7, reverse-scored items use `adjusted = 8 - a`; normal items
use `adjusted = a`. For a question of weight `w`:

```text
first-side contribution  = adjusted × w
second-side contribution = (8 - adjusted) × w
first percentage         = firstRaw / (firstRaw + secondRaw) × 100
second percentage        = 100 - first percentage
```

The higher raw side supplies the type letter. Exact raw ties use, in order: the
dimension's weighted tie-breaker signal; the same dimension from the previous
stable completed result; then the published neutral fallback I/S/T/P. A tied
dimension remains visibly labelled balanced even when a deterministic letter
is needed. Preference strength is balanced below 53%, slight from 53% to below
55%, moderate from 55% to below 65%, and strong at 65% or above.

Submission rejects an unknown test version, duplicate question IDs, values
outside 1–7, unknown questions, missing answers and dimensions with insufficient
questions. Results record schema, test/question, answer-scale, scoring and
guidance versions so they can be interpreted later without recalculation.

## 4. Data model, indexes and security

Important collections/fields:

- `referral_relationships/{downlineUid}`: authoritative upline/referrer IDs,
  status, source and timestamps. Flutter cannot write it.
- `users/{uid}/personality_assessments/{attemptId}`: schema/version fields,
  status, answer map, timestamps, optional `retake_of`, final type and dimension
  result. Owners can create/update only their own incomplete attempt; completed
  results cannot be modified or deleted by a normal user.
- `users/{uid}` active fields: `personality_active_attempt_id`,
  `personality_active_version`, `personality_active_updated_at`.
- `personality_question_sets/{version}`: title/status, schema/scoring/guidance/
  answer-scale versions, question count and audit timestamps/actors.
- `personality_question_sets/{version}/questions/{questionId}`: bilingual text,
  dimension, direction, reverse flag, weight, active flag, version, display
  order, randomization group and tie-breaker flag.
- `account_deletion_jobs` and `deletedUsers`: administrator-readable,
  server-write-only deletion state/audit records keyed by a hashed reference.

New collection-group single-field indexes support exhaustive deletion queries
for `downlines.user_id`, `followers.follower_id`,
`notifications.follower_id`, `notifications.user_id`, and `messages.user_id`.

Rules enforce owner-only assessment privacy, signed-in read of published
question sets, admin-only draft/configuration writes, published-version
immutability, server-only deletion records, referral immutability and existing
Storage ownership/type limits. Emulator tests cover normal-user denials,
administrator access and completed/published immutability.

## 5. Migration and deployment

The personality migration is additive and dry-run by default. It labels old
unversioned documents as preserved legacy data; it does not recalculate results
or overwrite answers/types.

```powershell
cd mobile/hc_trader/functions
node scripts/migrate_personality_assessments_v1.js --project=hcgaming-31d5d
node scripts/migrate_personality_assessments_v1.js --project=hcgaming-31d5d --apply --confirm-project=hcgaming-31d5d
```

Use `--limit=N` (1–5000) for a controlled batch. Review dry-run paths and take a
Firestore export before `--apply`. Because the change only adds version/migration
metadata, rollback is normally unnecessary; if policy requires it, restore the
export or remove only fields added by `personality-assessment-v1` after reviewing
the reported paths. Do not delete legacy answers/results.

Deploy backend configuration before distributing the client:

```powershell
cd mobile/hc_trader
firebase deploy --only firestore:rules,firestore:indexes,storage,functions --project hcgaming-31d5d
```

No production deployment or destructive production deletion was performed by
this implementation run. Firebase Admin credentials, the audit pepper and
deployment authorization remain operator responsibilities. The Laravel API had
no schema/controller changes for these Firebase-owned features.

## 6. Verification results

Completed release checks:

- `flutter clean` and `flutter pub get`: passed.
- `dart format --output=none --set-exit-if-changed .`: 172 files, 0 changes.
- `flutter analyze`: no issues.
- `flutter test -r compact`: 230 tests passed.
- Firebase Functions: 13 Node syntax checks passed; 8 backend suites passed
  (referral registration, roles, rating engine, v3 migration, metadata cleanup,
  personality migration, daily quiz and trade-call statistics).
- Firebase emulator: MT5 Firestore rules, security rules v4 and Storage rules v4
  passed.
- Laravel: 69 tests passed with 4,365 assertions, 1 intentional skip and 0
  failures.
- Release AAB: built successfully and `jarsigner -verify` returned `jar
  verified`. The upload certificate is self-signed and untimestamped, which is
  normal for the locally configured Play upload key; protect and back it up.

The Android compiler emitted dependency-level notices about deprecated Java
APIs/Java 8 source compatibility and unchecked operations. They did not fail
the build or originate from the new Dart feature code; track them during future
Android/plugin upgrades.

## 7. Release artifact

- Working bundle: `build/app/outputs/bundle/release/app-release.aab`
- Archived bundle:
  `releases/HC-Trader-1.6.29-build-1016-account-referral-search-personality.aab`
- Size: 71,213,851 bytes (67.91 MiB)
- SHA-256:
  `34943CE68B703E8C98D64CA8A1E0F3DB0A6A25EF138430DF32B3D020B798A9C0`
- Production API: `https://www.hcgaming.my/api/mobile/v1`

## 8. Manual acceptance checklist

Run on at least one narrow phone, standard Android phone and tablet, in English
and Simplified Chinese:

1. Search exact/prefix/partial/category/keyword/multiword/Chinese terms; confirm
   ranking, debounce, clear/no-result states and absence of restricted tools.
2. Open Profile with active, absent, missing and unavailable upline data; verify
   only the public display name appears and retry works.
3. Confirm referral input appears during signed-out registration only. Test a
   valid code, invalid/inactive code and self-referral; confirm logged-in users
   cannot assign or change an upline.
4. Start the personality test, answer partially, force-close, resume, verify all
   32 required questions, submit, inspect four percentages/type/guidance/answer
   summary, create an optional draft, and confirm existing plans/strategies are
   unchanged. Retake and confirm the old dated result remains.
5. As an administrator, seed a draft question version, edit both translations
   and scoring metadata, deactivate/reactivate, preview, test invalid publish
   validation, then publish a valid set. Confirm it becomes locked and a normal
   user cannot access the draft.
6. In a disposable test account containing journal, assessment, referral,
   notification, Storage and shared content data, exercise password and social
   reauthentication paths. Interrupt/retry deletion, then confirm Auth/private
   data/files/local caches are gone, shared records are anonymized, downlines
   show unavailable rather than reassigned, and the retained audit contains no
   raw UID/name/email.
7. Install a Play-generated split APK from the AAB on API 23 and a current API,
   sign in against production configuration, and smoke-test push registration,
   Profile, Toolkit and Settings before rollout.

## 9. Known operational limits

- Deploy rules, indexes and Functions and run the reviewed migration separately;
  the AAB build does not deploy backend resources.
- Production deletion was intentionally not executed. Exhaustiveness must be
  spot-checked with a seeded disposable project/account before production
  rollout and revisited whenever a new user-owned collection or Storage prefix
  is introduced.
- Admin-published personality versions are safely staged but do not replace the
  shipped `personality-16-v1` scoring contract without a compatible app release.
- The signed bundle is ready for Play upload, but Play Console app-content,
  Data safety, privacy/financial declarations and rollout approval remain manual.
