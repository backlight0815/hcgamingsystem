# Personality assessment offline-sync fix 1.6.30

Version: `1.6.30+1017`  
Date: 2026-07-14

## Issue

Opening **Self-Personality Test** could show “The personality assessment could
not be loaded” when the Firestore history read failed because of connectivity,
temporarily unavailable services, or Firestore rules that had not yet been
deployed. The 32-question assessment is bundled with the app, so a cloud-sync
failure should not have blocked it.

## Resolution

- Assessment history now loads local data first and treats remote read failures
  as non-blocking synchronization errors.
- New attempts, answer progress and completed results are committed locally
  before best-effort Firestore synchronization.
- Locally newer/incomplete or completed attempts are reconciled when Firestore
  becomes available again. A completed server result remains authoritative and
  immutable.
- The screen shows one localized non-blocking cloud-sync warning while allowing
  the user to continue.
- Malformed legacy local-cache entries are ignored individually instead of
  blocking the complete assessment history.
- Local device persistence failures remain blocking and visible; they are not
  incorrectly reported as successful saves.

## Verification

- `dart format --output=none --set-exit-if-changed .`: passed.
- `flutter analyze`: no issues.
- `flutter test -r compact`: 233 tests passed.
- `flutter build windows --release`: passed; `hc_trader.exe` generated.
- Regression tests simulate Firestore read/write permission failures, offline
  completion, later local history loading, corrupted local cache and the full
  widget entry flow.
- Signed release AAB:
  `releases/HC-Trader-1.6.30-build-1017-personality-offline-sync-fix.aab`
- Size: 71,221,540 bytes (67.92 MiB)
- SHA-256:
  `DE4719804FAFD0F8FB603E59B8C81B5D6C5D1AF66FA030E1499EEF29D920624A`

For cloud backup, deploy the repository's current Firestore rules/indexes:

```powershell
firebase deploy --only firestore:rules,firestore:indexes --project hcgaming-31d5d
```

The assessment remains usable before or during that deployment because local
progress is now the availability fallback.
