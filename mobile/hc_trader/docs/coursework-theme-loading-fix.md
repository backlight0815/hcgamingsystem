# Coursework theme loading and assignment selector fix

## Root cause

The visible `0 themes` state was produced by several independent failure modes:

1. Production can legitimately contain no `coursework_themes` documents until
   the validated seed is applied. The application previously presented this in
   the same visual flow as a filtered result.
2. The theme page and assignment tab both read `coursework_themes`, but the
   assignment tab applied a second exact-string `status == "published"`
   filter. Legacy values such as `Published`, `已发布`, or `isPublished: true`
   were therefore hidden.
3. Theme category, difficulty, status, active, and title fields were parsed
   from only part of the historical schema. A display label such as
   `Trading Market Foundations` did not reliably become the canonical
   `market_foundations` ID.
4. The admin stream loaded each theme's question subcollection sequentially.
   With 100 themes this delayed first render and made a question read failure
   look like the entire catalogue was unavailable.
5. The assignment form used a basic dropdown, did not expose stream errors,
   did not exclude inactive/non-assignable themes consistently, and could not
   restore legacy assignment relationships.

## Authoritative collections and relationship

| Purpose | Firestore path | Authoritative relationship |
|---|---|---|
| Public/admin theme metadata | `coursework_themes/{themeId}` | Document ID is `themeId` |
| Public questions | `coursework_themes/{themeId}/questions/{questionId}` | Parent theme ID |
| Private marking data | `coursework_theme_keys/{themeId}` | Same theme ID; never candidate-readable |
| Assignment | `coursework_assignments/{assignmentId}` | `theme_id` stores the theme document ID |
| Assignment marking snapshot | `coursework_assignment_keys/{assignmentId}` | Same assignment ID; server-only |
| Attempt | `coursework_attempts/{attemptId}` | `assignment_id` + `theme_id` snapshots |

Flutter collection names are centralized in
`lib/core/config/firestore_collections.dart`. Widgets do not contain raw
Coursework collection paths.

## Canonical values

New writes use only these machine values:

- Difficulty: `beginner`, `intermediate`, `advanced`
- Publication: `draft`, `published`, `archived`
- Category: one of the ten IDs in `CourseworkTaxonomy.categories`, for example
  `market_foundations`

The parser accepts legacy English casing, older aliases, and Chinese labels,
but immediately exposes the canonical value to filters and writes. Localized
labels remain presentation-only.

## Query and UI behavior

The administrative theme bank uses one unfiltered root snapshot. This avoids
compound-index failures and ensures legacy documents with missing sortable
fields are not excluded. Question subcollections are loaded concurrently;
individual malformed documents are logged by document ID while Firebase
permission/network errors are rethrown to the UI.

`CourseworkThemeFilter` applies canonical category, difficulty, publication,
active, assignment-eligibility, and Unicode search filters in memory. The data
set is currently bounded at 100 managed themes. Pagination should be added
before expanding this bank to thousands of themes.

The assignment tab reuses the same repository stream. Its modal selector is
searchable by title/code, displays canonical metadata, stores only the theme
document ID, and shows archived/missing historical selections as unavailable
instead of crashing. Existing assignments can be opened for editing. Theme
changes are rejected by the callable after an attempt has started.

## Legacy migration

`functions/scripts/migrate_coursework_theme_references.js` is dry-run by
default. It:

- reports legacy theme fields that can be canonicalized;
- resolves assignment relationships by valid ID, legacy ID, unique code, then
  unique title;
- never resolves ambiguous titles;
- never deletes old fields;
- never overwrites a valid canonical `theme_id`;
- creates a JSON backup before any applied writes;
- is idempotent and reports scanned, pending, unresolved, and written counts.

Dry run:

```powershell
npm --prefix functions run migrate:coursework-themes -- --project=hcgaming-31d5d
```

Production apply requires an explicit project confirmation:

```powershell
npm --prefix functions run migrate:coursework-themes -- --project=hcgaming-31d5d --apply --confirm-production=hcgaming-31d5d
```

Do not apply the migration until the dry-run report and backup location are
reviewed. The 100-theme seed is a separate, non-destructive workflow documented
in `firebase/seed/coursework/README.md`.

## Rules and indexes

Firestore rules continue to restrict theme writes to `coursework.manageThemes`
and assignment creation/update to trusted callable functions. Candidates can
read only active published public content and never read marking keys.

No new composite index is required by the admin theme list or selector because
they use the stable root snapshot plus in-memory filtering. Candidate catalogue
indexes remain declared in `firestore.indexes.json` for canonical published and
active queries.

## Verification

Run:

```powershell
flutter analyze
flutter test test/coursework_theme_loading_test.dart
flutter test test/coursework_system_test.dart
node functions/test/coursework_functions.test.js
npm --prefix firebase run test:coursework:emulator
```

Production document counts and the exact `market_foundations + beginner +
published` scenario must be verified after a separately approved seed or
migration run. Local tests never create, overwrite, or delete production data.
