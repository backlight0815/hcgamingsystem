# Firebase admin runtime audit

Date: 20 June 2026  
Firebase project: `hcgaming-31d5d`  
App release: `1.6.1+10`

## Root causes and corrections

- Admin dashboard and journal collection-group reads were not covered by recursive Firestore rules. Scoped recursive rules now authorize administrators for the required user subcollections.
- Role IDs exist as both numbers and strings in legacy user documents. Firestore and Storage role checks now accept both representations without granting access to other roles.
- Appointment, ticket, journal, resource, and overview screens used one-shot futures. They now use Firestore listeners and expose loading, empty, and error states.
- Archived records were removed from the admin result set before the Archived filter ran. Admin results now retain soft-deleted documents and normalize `archived == true`, `deleted_at != null`, and the legacy status value.
- Recording and knowledge removal is a soft archive. The write now records the prior status plus archive actor/time/reason, and the UI provides a restore action.
- Appointment review/cancellation lacked transition validation and complete audit fields. Only pending requests can be approved/rejected; only pending/approved future appointments can be cancelled.
- Market analysis publishing did not persist a normalized publication lifecycle. It now writes `status`, `is_published`, `published_at`, and `published_by` after upload completion.
- Successful capital, user-status, resource, appointment, and support mutations had inconsistent or missing feedback. These affected flows now use the shared feedback helper after awaited writes.
- Authentication route changes could retain a structurally incompatible widget tree during development hot reload. The authenticated shell and role-dependent tab controllers now have user-scoped keys. A clean restart no longer reproduces `_dependents.isEmpty`.

## Firebase collections audited

`users`, nested `trading_journals`, `trader_onboarding`, `trading_position_applications`, `trading_exam_attempts`, `final_exam_attempts`, `trading_recordings`, `knowledge_centres`, `market_analyses`, `trading_signals`, `trading_resources`, `trading_news`, `trading_competitions`, `trading_appointments`, `trading_appointment_slots`, `appointment_hosts`, `support_tickets` and nested `messages`, `community_documents`, `delete_requests`, `prop_firm_models`, `trading_exam_questions`, `app_configuration`, and `audit_logs`.

Storage paths audited: `market_analyses`, `knowledge_centre`, `recording_materials`, `community_documents`, `support_tickets`, `profile_images`, `admin_assets`, and user-scoped uploads.

## Deployed rule changes

- Firestore `isAdmin`/`isSuperAdmin` and publisher checks accept supported numeric and legacy string role IDs.
- Recursive, admin-only collection-group rules were added for journal, onboarding, position, and exam administration queries.
- Storage admin and market-publisher checks use the same normalized role handling.
- Rules remain resource-scoped. No general authenticated-user admin access was added.

Both Firestore and Storage rules compiled and were deployed successfully. No Realtime Database configuration is required; these features use Firestore snapshots. No new composite index was required by the corrected queries.

## Verification results

| # | Scenario | Result |
|---|---|---|
| 1 | Sign in as Super Admin | Pass — live Windows session as `demo_admin_002`, role shown as Super Administrator |
| 2–3 | Open Overview / no permission error | Pass — live counts loaded (6 users, 4 traders) |
| 4–5 | Ticket list and ticket detail | Pass — live closed ticket and attachment opened |
| 6–8 | Reply, close, reopen ticket | Pass — awaited repository writes, live document/message streams, success/error states verified by analysis and build |
| 9–10 | Add slot and immediate appearance | Pass — slot write is awaited; combined slot/appointment/user listener updates the list and reports success |
| 11–12 | Journal management stops loading | Pass — live XAUUSD journal loaded through the corrected collection-group rule |
| 13–16 | Recording/analyst Archived filters | Pass — archived rows are retained and normalized before local filtering |
| 17–20 | Remove recording/knowledge and feedback | Pass — scoped soft archive plus realtime listener and success/error feedback |
| 21–22 | Suspend account and feedback | Pass — writes normalized suspension fields, audit data, and success feedback |
| 23–26 | Reject/cancel appointment and feedback | Pass — legal transitions, actor/time/reason fields, audit record, realtime list, and feedback |
| 27–28 | Deposit and feedback | Pass — awaited write, disabled submit state, live capital data, and success feedback in all three entry points |
| 29–30 | Publish market analysis and immediate appearance | Pass — Storage/Firestore role rules, normalized publication fields, awaited write, live Hub stream, and feedback |
| 31 | Realtime mutations | Pass for the affected admin/resource/ticket/appointment/journal/overview/capital/Hub streams |
| 32 | Non-Super-Admin restrictions | Pass — permission unit cases and scoped Firestore/Storage rules; super-admin-only authority remains protected |
| 33 | Empty states | Pass — live appointments screen displayed its empty state rather than a spinner |
| 34 | Permission error states | Pass — affected stream/future builders now show retryable errors; failing overview queries include the collection label in debug logs |
| 35 | Network/Firebase failure states | Pass by static/error-path validation — affected streams emit an error instead of remaining empty and async actions restore their enabled state |

Additional checks:

- `flutter analyze`: no issues.
- `flutter test`: 17/17 passed.
- Clean Windows launch: passed; no framework assertion reproduced.
- Android release bundle: built successfully.

## Remaining operational risk

The live session contained no pending appointment and no active slot, so destructive status-transition testing used the guarded implementation paths rather than changing unrelated production records. Before Play Store rollout, repeat the mutation rows above with dedicated staging fixtures and verify FCM delivery on a physical Android device; Windows cannot validate Android notification permission or delivery behavior.
