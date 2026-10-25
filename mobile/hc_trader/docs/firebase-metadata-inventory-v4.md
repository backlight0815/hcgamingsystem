# Firebase metadata inventory v4

Audit date: 2026-07-13  
Project inspected: `hcgaming-31d5d`  
Execution mode: read-only dry-run

## Outcome

The audit found one field that is safe to stage for removal: `users.role_names`.
It is a derived list of labels for the canonical `role_ids`, has no runtime
reader in the Flutter app, Functions, Firestore rules, Storage rules, tests, or
Laravel source, and was present on 12 of 16 production user documents. The two
writers that could recreate it have been removed from the v3 migration and the
administrator role-update callable.

No production field was deleted during this work.

## Production counts

| Collection | Documents scanned | Candidate documents | Notes |
|---|---:|---:|---|
| `users` | 16 | 12 | `role_names` is the only removal candidate. |
| `referral_codes` | 51 | 0 | Referral ownership, role eligibility, campaign, usage, expiry, and approval metadata remain active. |
| `roles` | 14 | 0 | Role names, permission keys, privilege flags, and migration markers remain active configuration. |
| `trading_exam_questions` | 5 | 0 | Existing questions use the legacy multiple-choice shape; the app supplies a chart-compatible visual fallback. New/updated published questions must use the v4 chart schema. |
| `market_analyses` | 2 | 0 | Existing outlook content remains compatible; new profile, outcome, author, and gallery fields are additive. |

## Classification

### Staged removal

| Collection.field | Evidence | Before/after effect |
|---|---|---|
| `users.role_names` | No reader remains. `role_ids` is authoritative and `role_name` remains the compatibility display label. | Removes duplicated display metadata only; authorization and UI role resolution continue from IDs. |

### Retain: canonical or actively referenced

- `users.role_ids`: canonical multi-role authorization field.
- `users.role_id`: legacy primary-role compatibility field still read by clients,
  Functions, Firestore rules, and Storage rules.
- `users.role_name`: display compatibility field used by administration and
  recruitment views.
- `users.access_status` and `users.accessStatus`: both remain referenced during
  the current compatibility window by clients and rules.
- Referral identity fields (`referrer_id`, `upline_id`, `invited_by`,
  `referred_by_user_id`, role/team/campaign attribution): active security and
  relationship data. They are immutable to clients and may only be set by
  trusted registration/recruitment callables.
- `migration_version` and `migrated_at`: retained because deployed migrations
  use them for idempotence and auditability.
- Referral-code owner/issuer aliases and usage fields: retained because
  deployed registration, weekly-code, repair, and migration paths still read
  multiple schema generations.

### Unknown or externally coupled: retain pending evidence

Fields not listed as candidates are retained when a first-party reader was not
found but an external reporting, Firebase console, or historical integration
dependency cannot be disproved. Absence of a repository reference alone is not
sufficient authority to delete production data.

## Guarded cleanup tool

Tool: `functions/scripts/cleanup_obsolete_metadata_v4.js`

Dry-run inventory (safe and read-only):

```powershell
npm --prefix functions run cleanup:metadata -- --collection=users --limit=5000
```

The apply path is intentionally difficult to invoke accidentally. It requires
an exact project confirmation, a phrase confirmation, an explicit document
limit, and a local backup directory:

```powershell
npm --prefix functions run cleanup:metadata -- --apply `
  --collection=users `
  --limit=100 `
  --confirm-project=hcgaming-31d5d `
  --confirm-production-cleanup=REMOVE_OBSOLETE_METADATA `
  --backup-dir=.firebase-backups/metadata-v4
```

Apply mode writes a restorable JSON backup before the first Firestore write,
commits in bounded batches, reports progress and errors, and records a
checkpoint so a rerun is idempotent. Restore uses the generated backup plus the
same apply confirmations:

```powershell
npm --prefix functions run cleanup:metadata -- --apply `
  --collection=users `
  --limit=100 `
  --confirm-project=hcgaming-31d5d `
  --confirm-production-cleanup=REMOVE_OBSOLETE_METADATA `
  --backup-dir=.firebase-backups/metadata-v4 `
  --restore=<backup-json-path>
```

## Before/after validation checklist

1. Run dry-run and retain its JSON output with the release evidence.
2. Verify `users.role_names` has no new repository reference.
3. Run Functions tests, Firestore rules tests, Flutter analysis, and registration
   role/referral regression tests.
4. Apply to a small explicit limit and verify sign-in, role-gated navigation,
   registration, recruitment, and administrator role updates.
5. Rerun dry-run. Candidate count must decrease by exactly the processed count;
   rerunning apply must produce no new change for completed documents.
6. Restore from backup if any downstream consumer is found.
