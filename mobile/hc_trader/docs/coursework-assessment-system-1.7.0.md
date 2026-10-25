# Coursework and Final Examination system — 1.7.0

> Maintenance update: version `1.7.1+1021` corrects the Coursework journey
> header constraints on wide Windows layouts. The previous horizontal flex
> could receive infinite height inside the scroll view and leave the body blank.

## Delivered outcome

HC Trader now presents a two-part professional assessment journey:

1. **Coursework** — one immutable assigned theme, exactly five questions and
   100 raw marks, weighted to 70%.
2. **Final Examination** — the existing quiz remains in place, is explicitly
   labelled as a raw score out of 100, and is weighted to 30%.
3. **Overall result** — `coursework × 0.70 + final examination × 0.30`, with
   configurable weights, pass mark and grade boundaries. The overall result
   remains pending until Coursework review is final.

The legacy Daily Exam remains read-only historical data. New Coursework uses
separate collections and does not overwrite old attempts.

## Candidate experience

- Responsive Coursework overview and assignment player for phones and tablets
- Statuses for assigned, draft/in progress, submitted, under review, revision
  requested, resubmitted, graded, passed and failed
- Five-question navigation with completion, flagging and autosave state
- Local draft recovery plus Firestore version checks for cross-device edits
- Objective theory questions, frozen candlestick charts, structured market
  analysis, and validated order-management demonstrations
- Interactive chart pan, zoom, reset and full-screen controls
- Image evidence upload with type, size, owner, attempt, question and SHA-256
  metadata validation
- Submission checklist, declaration and immutable post-submit lock
- Per-question marks, rubric feedback and Coursework result presentation
- English and Chinese Coursework interface copy

## Coursework contract

Every published theme is validated as one ordered set:

| Question | Type | Marks |
| --- | --- | ---: |
| 1 | Objective theory | 15 |
| 2 | Applied theory | 15 |
| 3 | Chart zone selection | 20 |
| 4 | Self-analysis KBAT | 25 |
| 5 | Practical demonstration | 25 |
| **Total** |  | **100** |

Assignment snapshots are sanitized for candidates. Correct answers, answer-zone
keys and reviewer guidance are stored separately in server-only key documents,
so changing the theme bank later cannot change an existing assignment and a
candidate cannot read marking keys.

## Data model

Primary Firestore paths:

- `coursework_themes/{themeId}/questions/{questionId}` — versioned source bank
- `coursework_theme_keys/{themeId}/questions/{questionId}` — private source marking keys
- `coursework_assignments/{assignmentId}` — immutable candidate-safe snapshot
- `coursework_assignment_keys/{assignmentId}` — server-only marking snapshot
- `coursework_attempts/{attemptId}/answers/{questionId}` — candidate answers
- `coursework_attempts/{attemptId}/attachments/{attachmentId}` — evidence metadata
- `coursework_attempts/{attemptId}/grading/{gradingId}` — review records
- `coursework_attempt_keys/{attemptId}` — server-only attempt marking key
- `coursework_results/{userId}` — finalized Coursework result
- `assessment_results/{userId}` and `history` — combined 70/30 result history
- `assessment_settings/current` — weights, pass mark and grade boundaries
- `assessment_audit_logs/{logId}` — immutable server audit events

Evidence is stored at
`coursework_submissions/{userId}/{attemptId}/{questionId}/{fileName}`.

## Administration and permissions

**More > Administration > Coursework Management** contains dashboard, theme
bank, assignment and review tabs. It supports bilingual theme editing,
validation/preview/duplicate/publish, user assignment, due dates, revision
limits, reviewer queues, rubric marks, feedback and revision requests.

Server rules enforce these explicit permissions:

- `coursework.manageThemes`
- `coursework.assign`
- `coursework.viewAssignedSubmissions`
- `coursework.gradeAssignedSubmissions`
- `coursework.requestRevision`
- `coursework.manageSettings`
- `coursework.viewReports`

Candidates can only read their own assignment/attempt and can only edit allowed
answer fields while the attempt is draft, in progress or revision requested.
Clients cannot assign, grade, update marks, write overall results, read marking
keys or alter audit logs. Published records are not hard-deleted.

## Question bank

`firebase/seed/coursework` contains the current deterministic catalogue: 100
themes, 500 public questions, 100 private key roots and 500 private question
keys. The validator reports ten themes in each category and a 30/45/25
beginner/intermediate/advanced distribution. See
`docs/coursework-100-theme-firestore-seed.md` for the current audit.

The earlier `assets/seed/coursework_themes_100.json` bank is retained only for
historical compatibility and is not the production seeding source. Its previous
category report was:

| Category | Themes |
| --- | ---: |
| Support and Resistance | 12 |
| Market Structure | 12 |
| Trend and Range Conditions | 10 |
| Candlestick and Price Action | 10 |
| Risk Management | 12 |
| Order Management | 12 |
| Stop Loss and Take Profit | 10 |
| Trade Planning | 8 |
| Trading Psychology and Discipline | 8 |
| Multi-Timeframe Analysis | 6 |

All 100 themes have manually prepared English and Chinese learning content and
frozen chart data. Chart fixtures use a captured BTC/USD OHLC response from the
CoinGecko public market-chart endpoint, with source URL, symbol, timeframe and
capture date stored in each chart snapshot. Runtime Coursework never depends on
a live market response and therefore cannot drift after assignment.

Validate and preview the idempotent seed without writing:

```text
node tools/validate_coursework_question_bank.js
node tools/seed_coursework_themes.js
```

Apply only after selecting the intended Firebase project credentials:

```text
node tools/seed_coursework_themes.js --apply
```

## Migration

`functions/scripts/migrate_daily_exam_to_coursework_legacy.js` marks old Daily
Exam records as `legacyDailyExam`; it does not convert them into Coursework or
recalculate their scores. The apply path first writes a JSON backup, uses an
idempotent migration version, and produces a count/error report.

```text
# Read-only preview
node functions/scripts/migrate_daily_exam_to_coursework_legacy.js

# Explicit production write, after backup/project review
node functions/scripts/migrate_daily_exam_to_coursework_legacy.js --apply
```

The seed and migration were deliberately left in dry-run state in this
workspace. Deployment or production data mutation requires an explicit project
selection and operator review.

## Deployment order

1. Confirm Firebase project and service-account/CLI identity.
2. Deploy Firestore and Storage rules.
3. Deploy the five Coursework callable functions.
4. Run the question-bank validator, then seed with `--apply`.
5. Preview the legacy migration, archive its report, then apply if approved.
6. Install the release AAB on representative phone and tablet devices.
7. Run the candidate/reviewer acceptance scenarios below with test accounts.

```text
firebase deploy --only firestore:rules,storage --project <project-id>
firebase deploy --only functions:assignCourseworkTheme,functions:startCourseworkAttempt,functions:submitCourseworkAttempt,functions:gradeCourseworkAttempt,functions:recalculateOverallResult --project <project-id>
```

## Verification report

Completed locally on 15 July 2026:

- `flutter analyze` — no issues
- Full Flutter suite — 271 tests passed
- Coursework focused suite — 12 tests passed
- Backend Coursework unit tests — passed
- Firestore emulator security tests — passed
- Owner-filtered Coursework dashboard query tests — passed; unfiltered and
  other-user queries remain denied
- Storage emulator security tests — passed
- Question-bank validation — 100 themes, 500 questions, 100 bilingual themes,
  100 frozen charts, zero issues
- Seed dry run — passed; no Firestore writes
- Android release build — passed

Automated coverage includes the five required scoring cases (100/100, 80/70,
0/100, 100/0 and pending Coursework), decimal/weight validation, malformed order
geometry, NaN/infinite values, question-bank uniqueness/structure, phone chart
layout, Chinese submission review, ownership, submitted-attempt locking,
server-only marking keys, reviewer permissions and upload constraints.

The generated UI captures are:

- [Coursework overview](screenshots/coursework-overview.png)
- [Coursework assignment player](screenshots/coursework-assignment-player.png)
- [Coursework theme management](screenshots/coursework-theme-management.png)

## Release artifact

- Android: `releases/HC-Trader-1.7.1-build-1021-coursework-ui-fix.aab`
- Android size: 72,033,730 bytes
- Android SHA-256:
  `B452FE0D6224D112EC3E49C3F0D989748ECB963444ECEF92CBBD7CBFC259E915`
- Windows: `releases/HC-Trader-Windows-1.7.1-build-1021-coursework-ui-fix.zip`
- Windows size: 70,496,520 bytes
- Windows SHA-256:
  `6401460A3EF8179095D9FD8F0D63991E78C919AB5FDAF264A25D9C7F4CB7B2AF`
- Version: `1.7.1+1021`

## Live acceptance checklist

After backend deployment and seeding, verify on real accounts/devices:

- Super Admin: create/duplicate/publish, assign, filter, export/read reports
- Market Analyst: see only permitted assigned reviews, grade, request revision
- Candidate: start, autosave, terminate/reopen app, restore, switch device,
  upload evidence, submit and confirm lock
- Reviewer: objective marks, rubric/manual marks, feedback, revision/resubmission
- Result: pending before final review and correct 70/30 result after grading
- Chinese locale: overview, player, validation, review and result flow
- Offline/poor network: local recovery, retry state and no duplicate attempts
- Phone/tablet and light/dark: chart gestures, keyboard, overflow and contrast
- Notifications: assignment, due reminder, submission, revision and graded events

These live, deployed-environment checks cannot be completed by a local build
alone. In-app notification documents are created by the backend; delivery as an
OS push also depends on the project's existing notification dispatcher.

## Operational notes

- Large images are rejected above the 8 MB rule limit; this release does not
  silently recompress user evidence.
- PDF evidence is disabled. Storage rules accept JPEG, PNG and WebP only.
- The admin editor preserves seeded chart snapshots. Creating a new chart from
  scratch should use the validated seed/import workflow so source metadata and
  answer-zone keys are reviewed before publishing.
- Firestore rules were deployed to `hcgaming-31d5d` on 15 July 2026 to resolve
  the candidate and administration Coursework permission errors. The current
  ruleset is `84785646-8e17-43bf-a41b-297ed7d1b20d`; its override lookups are
  safe when an administrator has unrelated permission keys.
- Coursework functions, Storage rules, question-bank seeding, migration,
  account assignment and external notifications have not been deployed or run
  from this workspace.
