# HC Trader mobile administration

## Access

The Flutter app uses the Firebase user profile as its authorization source.
`role_id: 1` is Super Admin, `role_id: 2` is Administrator, `role_id: 501`
is Market Analyst, and roles `201`, `202`, and `502` are signal publishers.
Server-side Firestore and Storage rules enforce the same privileged boundaries;
hiding a menu item is not treated as authorization.

The configured Super Admin test account is:

- Username: `demo_admin_001`
- Email: `demo_admin_001@demo.hc`
- Role: Super Admin (`role_id: 1`)

The password is intentionally not recorded in the repository.

## Administration screens

Open **More > Administration**. The workspace contains:

- Dashboard counts and pending-work indicators
- Account, trader verification, position verification and performance review
- Marketing resources, news, knowledge-centre content, recordings and blogs
- Trading signals and market-analyst publishing
- Trading and final-exam question management plus result review
- Coursework theme bank, assignment, rubric review and 70/30 assessment results
- Versioned Self-Personality Test question sets, bilingual text, scoring
  direction/weight, deactivation, preview and immutable publishing
- Support-ticket replies, assignment, closure and reopening
- Appointment slot creation, editing, overlap validation and deletion
- Journal review and private administrator notes
- Prop-firm configuration and trader-result decisions
- Market-analyst delete-request approval
- Feature configuration and immutable audit-log review

List screens provide search, status filters, 20-row paging, loading, error and
empty states. Destructive actions require confirmation and content is archived
where recovery/auditability matters.

## Feature configuration

Configuration is stored at `app_configuration/modules`. A missing document
uses safe defaults: trading modules are enabled and dealership is disabled.
Only Super Admin can change the document. Firestore rules also block reads and
normal writes for disabled modules.

## Uploads

Managed files are written under `admin_assets/{adminUid}` in Firebase Storage.
Supported files are JPG, PNG, WebP, PDF, DOC, DOCX and MP4. Images are limited
to 8 MB, documents to 10 MB, and video to 100 MB. Storage rules require an
administrator profile and validate type and size.

## Notifications

Firebase Functions notify phone tokens for newly published signals, market
analysis, recordings and knowledge resources; appointment creation/updates;
staff support-ticket updates; delete-request submissions and decisions;
prop-firm reviews; and trading/final-examination results.

## Verification

From `mobile/hc_trader` run:

```text
dart format --output=none --set-exit-if-changed lib test
flutter analyze
flutter test
node --check functions/index.js
flutter build appbundle --release
```

Firebase deployment commands are:

```text
firebase deploy --only firestore:rules,firestore:indexes,storage,functions --project hcgaming-31d5d
```

## Personality question versions

Open **More > Administration > Modules > Personality Question Sets**. A new
draft can be empty or seeded from the validated 32-question bank. Before a
draft can be published, the app verifies 32–48 active questions, 8–12 for each
dimension, unique positive display order, bilingual text, valid scoring
direction and positive weight. Published question sets and their nested
questions are immutable in Firestore; create a new version for any change.

The shipped assessment/scoring contract remains `personality-16-v1`. Publishing
a later configuration stores and locks it for review and coordinated client
activation; it does not reinterpret historical attempts or silently switch an
older client to an incompatible scoring contract.

## Scope and limitations

- This implementation is Firebase-native because the Flutter application has
  Firebase enabled and its Laravel API repository path disabled. No Laravel
  route, controller, migration or trading calculation was changed.
- Deleting a published market-analysis item requires a Super Admin decision;
  the analyst submits a delete request.
- Audit values exclude password/token-like fields and contain safe snapshots,
  actor identity, module/action/target, time and client platform. Firestore
  clients cannot reliably discover the public IP address, so the IP field is
  marked unavailable; authoritative IP logging belongs behind a trusted API.
- Cloud Functions deployment requires the Firebase project billing/runtime
  prerequisites supported by the project.
