# Coursework 100-theme Firestore implementation audit

Date: 2026-07-15  
Seed version: `coursework_100_themes_v1`

## Existing architecture reused

- Flutter uses strongly typed domain models in `lib/models`, repositories in
  `lib/repositories`, and `StreamBuilder`/`FutureBuilder` state for Coursework.
- The existing collections for themes, assignments, attempts, results and
  settings were retained; legacy Daily Exam data was not changed.
- The existing roles and Coursework permissions were retained: super admin
  (1), sub-admin (2), founder/instructor manager (3), and market analyst/reviewer
  (501).
- Firebase configuration continues to target `hcgaming-31d5d` for production.
  Emulator verification used the isolated project
  `hc-trader-coursework-seed-test`.

## Verified catalogue

- 100 deterministic themes, `CW-001` through `CW-100`.
- 500 public questions, `Q1` through `Q5` per theme.
- 100 private key roots and 500 private question keys.
- Ten curriculum categories with exactly ten themes each.
- Difficulty distribution: 30 beginner, 45 intermediate, 25 advanced.
- Every theme totals 100 raw marks using 15/15/20/25/25.
- Every student-visible title, description, instruction, prompt and option is
  English/Simplified-Chinese bilingual.
- Every Q3 contains 40 valid synthetic educational OHLC candles; no URL is
  stored and no live-data claim is made.
- Every Q4 enforces 150–2,000 characters and a five-part 25-mark rubric.
- Every Q5 enforces 80–1,500 characters, one to three JPEG/PNG/WEBP evidence
  images, and a five-part 25-mark rubric.

## Emulator evidence

The full integration runner completed successfully:

| Run | Themes | Public questions | Private roots | Private questions |
| --- | ---: | ---: | ---: | ---: |
| First run created | 100 | 500 | 100 | 500 |
| Second run skipped unchanged | 100 | 500 | 100 | 500 |
| Failed writes | 0 | 0 | 0 | 0 |

The Rules suite verified published public reads, draft/disabled/private denial,
reviewer key access, authorised admin management, seed metadata immutability,
and denial of all client key writes. Flutter model/widget tests and Cloud
Functions grading tests also pass.

## Deployment boundary

No production seed, rollback, Rules deployment, index deployment, or callable
deployment was executed during this implementation. Production insertion is an
explicit, separately authorised operations step documented in
`firebase/seed/coursework/README.md`.
