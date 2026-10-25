# HC Trader Coursework seed

This directory contains the deterministic, reviewable source for the HC Trader
Coursework catalogue. It inserts real Firestore documents through the Firebase
Admin SDK; it is not Flutter mock data and it does not call an external AI API.

## Data written

Seed version: `coursework_100_themes_v1`

| Path | Documents |
| --- | ---: |
| `coursework_themes/{CW-001..CW-100}` | 100 |
| `coursework_themes/{themeId}/questions/{Q1..Q5}` | 500 |
| `coursework_theme_keys/{CW-001..CW-100}` | 100 |
| `coursework_theme_keys/{themeId}/questions/{Q1..Q5}` | 500 |
| `assessment_settings/current` | 1 merged settings document |

Every public theme is bilingual, published, active, assignable, worth 100 raw
marks, and uses five questions worth 15/15/20/25/25. The public question files
never contain correct answers. Each Q3 uses 40 deterministic synthetic OHLC
candles and carries `isSyntheticEducationalData: true`. Private keys and
rubrics are stored only under `coursework_theme_keys`.

The maintainable source is:

- `curriculum.js`: the ten categories and 100 authored bilingual topics.
- `generate_coursework_seed.js`: deterministic question/chart/key generation.
- `themes/*.json`: 100 committed public theme bundles.
- `answer_keys/*.keys.json`: 100 committed private key bundles.
- `manifest.json`: expected counts and catalogue hash.

## Install and regenerate

From `mobile/hc_trader`:

```powershell
npm install --prefix firebase
npm --prefix firebase run seed:coursework:generate
npm --prefix firebase run seed:coursework:validate
npm --prefix firebase run test:coursework
```

Regeneration is deterministic. Review all JSON changes and the manifest hash
before changing the seed version or writing to any project.

## Dry run

Dry-run mode validates the source and prints the write plan without initialising
Firebase or making a network connection:

```powershell
npm --prefix firebase run seed:coursework:dry-run -- --project=hcgaming-31d5d
```

Selected ranges are supported:

```powershell
node firebase/seed/coursework/seed_coursework.js --dry-run --project=hcgaming-31d5d --from=CW-001 --to=CW-010
```

## Firebase Emulator

Java 21 is required by the Firestore emulator. On this Windows workstation the
Android JDK is at `C:\Program Files\Android\openjdk\jdk-21.0.8`.

```powershell
$env:JAVA_HOME='C:\Program Files\Android\openjdk\jdk-21.0.8'
$env:PATH="$env:JAVA_HOME\bin;$env:PATH"
firebase emulators:exec --only firestore --project hc-trader-coursework-seed-test "npm --prefix firebase run test:coursework:emulator -- --project=hc-trader-coursework-seed-test"
```

The integration runner seeds twice, confirms the repeat run skips unchanged
documents, verifies 100/500/100/500 counts, checks that public documents do not
leak marking data, and executes the Coursework Firestore Rules tests.

## Production safety

Production has intentionally not been seeded as part of this implementation.
Use Application Default Credentials, Workload Identity, or a Firebase CLI
authenticated environment. Never copy a service-account key into this repo or
into Flutter assets.

Before a production write:

1. Review the generated JSON, validation output, Firestore Rules and indexes.
2. Back up the existing Coursework collections.
3. Confirm the exact Firebase project with `firebase projects:list`.
4. Run dry-run with that explicit project ID.
5. Deploy the reviewed rules, indexes and callable functions.
6. Run the following only in an authorised operations session:

```powershell
npm --prefix firebase run seed:coursework:production -- --project=hcgaming-31d5d --confirm-production
```

The script refuses a production write without all of `--production`, `--write`,
an explicit project ID, and `--confirm-production`. It also refuses production
mode while `FIRESTORE_EMULATOR_HOST` is set.

## Idempotency and admin edits

Each document has `managedBySeed`, `seedVersion`, `version`, and a SHA-256
`contentHash`. Default behavior is:

- create a missing deterministic document;
- skip an unchanged document;
- update a changed document that is still seed-managed;
- protect unrelated documents and documents whose `updatedBy` is not
  `system_seed`;
- overwrite a protected document only when an authorised operator deliberately
  adds `--force`.

The writer uses Firestore `BulkWriter`, retries transient failures, reports
progress, and prints created/updated/skipped/protected/failed totals by document
kind. It never deletes unrelated or manually created themes.

To release changed seed content, update `SEED_VERSION`, regenerate the complete
source, review hashes, rerun every validator/emulator test, and document the
migration. Do not change only the generated JSON files by hand.

## Safe rollback

Rollback only selects documents with both `managedBySeed == true` and the exact
seed version. Production rollback has a second explicit confirmation gate:

```powershell
# Emulator
npm --prefix firebase run rollback:coursework:emulator -- --project=hc-trader-coursework-seed-test

# Production — authorised operations only
npm --prefix firebase run rollback:coursework:production -- --project=hcgaming-31d5d --confirm-production-rollback
```

Range flags (`--from`, `--to`) also apply to rollback. Existing assignments and
attempt snapshots are separate collections and are not deleted by this tool.

## Flutter and permissions verification

The admin page at **Coursework Management > Themes** streams
`coursework_themes`, loads each question subcollection, and shows live search,
category/difficulty/status filters, assignment totals, active state, timestamps,
preview/edit/archive/assign actions, plus loading/error/empty/refresh states.

Candidate catalogue queries must include `status == published` and
`isActive == true`. Normal approved users can read those public documents only.
Roles 1/2/3/501 retain the existing Coursework permission mapping; only
authorised reviewers/managers can read `coursework_theme_keys`. Client writes to
private keys are always denied. Admin SDK writes remain governed by operational
credentials rather than client Rules.

Required composite indexes are declared in `firestore.indexes.json`, including
status/category/difficulty/isActive with `sortOrder`. Deploy them only after
review:

```powershell
firebase deploy --only firestore:rules,firestore:indexes --project hcgaming-31d5d
```
