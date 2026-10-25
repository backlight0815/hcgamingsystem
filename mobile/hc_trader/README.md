# HC Trader Mobile

Flutter client backed by Firebase Authentication and Firestore, with the existing
Laravel API providing manual journal synchronization and selected content feeds.

## Firebase services

- Firebase Authentication: registration, email/username login, password reset,
  email verification, reauthentication and password changes.
- Cloud Firestore: profiles, roles, journals, capital, trading accounts,
  onboarding, content, examinations, applications and administration.
- Firebase Storage: onboarding documents, journal attachments and validated
  administration content uploads.
- Firebase Functions: phone notifications for published content and workflow
  updates.
- Shared preferences: non-sensitive device-local plans, psychology history,
  caches and offline drafts.

The Firebase project is configured by `lib/firebase_options.dart`. The release
API is `https://www.hctradinghub.com/api/mobile/v1`; requests use Firebase ID
tokens. Journal linking requires a verified email matching one website account.
Firestore and Laravel capital transactions remain separate; the journal bridge
does not synchronize cash flows. See [the repair report](../../docs/trading-journal-repair-20261013.md).

## Firestore layout

User-owned records are nested under `users/{firebaseUid}`:

- `trading_journals`
- `capitals`
- `trading_accounts`
- `trader_onboarding/profile`
- `trading_position_applications`
- `trading_exam_attempts`
- `final_exam_attempts`

Shared collections include `trading_pairs`, `market_analyses`,
`trading_signals`, `trading_blogs`, `trading_resources`,
`trading_recordings`, `knowledge_centres`, `trading_exam_questions`,
`final_exam_questions`, `prop_firm_models`, `registration_roles`, `usernames`
and `referral_codes`.

## Firebase deployment

```bash
firebase deploy --only firestore:rules,firestore:indexes --project hcgaming-31d5d
firebase deploy --only storage --project hcgaming-31d5d
firebase deploy --only functions --project hcgaming-31d5d
```

Firebase Storage and the notification functions are deployed in project
`hcgaming-31d5d`.

## Verification and release

```bash
flutter pub get
dart format lib test
flutter analyze
flutter test
flutter build appbundle --release
```

Deploy and verify the matching Laravel journal patch before distributing the
mobile release. Production builds always use the HTTPS endpoint. Debug builds
can use `--dart-define=HC_API_BASE_URL=http://10.0.2.2:8765/api/mobile/v1`;
the debug network policy permits cleartext only to emulator/loopback hosts.

After Android integration tests, run builds with dependency generation enabled
(`flutter build appbundle --release`, without `--no-pub`) so the generated
release plugin registrant excludes development-only test plugins.
