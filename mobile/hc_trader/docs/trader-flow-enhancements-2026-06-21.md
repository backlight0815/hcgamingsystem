# Trader flow enhancements

Date: 21 June 2026  
App release baseline: `1.6.1+10`

## Implemented

- Daily examination submissions now store a durable per-question result snapshot in each user's `trading_exam_attempts` subcollection. The snapshot includes the selected answer, correct answer, correctness, question text, options, difficulty, and explanation.
- The daily result screen opens after submission and remains available from recent attempts after an app restart. The deterministic daily attempt ID and immutable owner rule prevent duplicate submissions.
- Daily examinations are knowledge checks in the existing backend and have no configured pass threshold. Results therefore show `Completed`; the model and screen automatically show pass/fail if a future attempt contains `passing_percentage`.
- Appointment booking now validates authentication, required subject, host, start/end time, and current slot status inside the Firestore transaction. The booking button is disabled while its write is running and the live workspace listener updates both slots and appointments.
- Firestore appointment rules now bind a slot's `booked` transition to the appointment created in the same atomic write, constrain trader-created appointments, and constrain participant cancellation fields.
- English and Simplified Chinese locale state now applies immediately, persists in `SharedPreferences`, and is selectable from both authentication and Settings. Core authentication, dashboard, navigation, daily exam/result, appointment, profile, settings, sign-out, role/status, and common Firebase feedback strings are localized.
- Role display names are centralized in `AppRoles`; profile screens retain the numeric ID internally but display localized role names.
- Sign out provides immediate disabled/loading feedback. Firebase sign-out is no longer blocked by notification token/topic cleanup, and the authentication root replaces protected routes when the auth stream emits the signed-out state.

## Firestore deployment

`firestore.rules` compiles successfully. The changed rules are present locally but were only checked with Firebase CLI `--dry-run`; deploy them before testing trader writes in a release environment:

```powershell
firebase deploy --only firestore:rules
```

No schema migration or composite index is required. Existing daily attempt documents without `question_results` still use current questions as a best-effort review fallback; newly submitted results are self-contained.

## Manual verification checklist

1. Sign in as a staging trader, answer all five daily questions, submit once, and verify totals plus selected/correct answers and explanations.
2. Close and reopen the app, return to Daily Exam, and open the stored result from Recent Attempts. Confirm a second submission is not offered.
3. Book a future active slot and verify it disappears from Available and appears immediately in My appointments. Try a second tap and an expired fixture.
4. Switch to Simplified Chinese from Settings and verify authentication, dashboard, daily exam/result, appointments, profile, settings, and sign-out. Restart, then switch back to English.
5. Verify Profile displays `Trader` / `交易员` instead of `750`.
6. Start sign-out, confirm the spinner appears immediately, and verify Android back cannot reopen the authenticated shell.

Android notification delivery and iOS compilation still require physical/platform-specific environments; neither can be proven by Windows unit tests.
