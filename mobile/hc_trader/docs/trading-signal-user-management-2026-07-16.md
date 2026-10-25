# Trading Signal Operations and User Management

Date: 2026-07-16  
Firebase project: `hcgaming-31d5d`

## Root cause and repair boundary

The production authoring incident had two independent failure modes. First, a
signal document could commit and then be reported as failed when the client
attempted a second, intentionally forbidden audit-log write. Second, signal
operation authorization treated ownership inconsistently: the repository,
detail screen, and Firestore rules did not resolve all creator, provider, and
assigned-provider fields in the same way. Older documents using legacy owner
fields and signals assigned to a provider could therefore be writable in one
layer but read-only in another.

The existing lifecycle implementation is retained and strengthened. A signal
update is a Firestore transaction that checks the expected version and writes
the signal projection, immutable update event, and immutable audit event
together. Only the projection fields are merged into the signal. Server
timestamps provide the official update and audit times. FCM delivery remains a
separate Firestore-created Cloud Function and cannot roll back that transaction.

## Lifecycle compatibility

`SignalLifecycleStatus.parse` remains the compatibility boundary for current
and legacy status values. Current values are `draft`, `scheduled`, `published`,
`pending_entry`, `active`, `partially_closed`, `break_even`, `trailing`,
`completed`, `stop_loss_hit`, `break_even_hit`, `cancelled`, `expired`, and
`archived`. Aliases such as `pending`, `closed`, `be`, and legacy numeric status
codes are mapped without rewriting existing documents.

Terminal states expose no routine trading actions. A Super Admin must use the
explicit correction/reopen path for a non-standard transition. Target order,
directional SL/TP validation, remaining-position limits, idempotency IDs, and
expected versions are enforced by the shared lifecycle policy and transaction.

## User directory schema

The directory reads one cursor-based page at a time. Search and frequently used
filters use additive normalized fields on `users`:

- `admin_user_search_version`
- `admin_search_terms`
- `name_lower` and `email_lowercase`
- `account_status`, `access_status`, and `verification_status`
- `mt5_status` and `main_mt5_login`
- `has_pending_application` and `has_unresolved_ticket`

Legacy-tolerant converters continue to read camelCase and snake_case fields and
supply safe defaults for missing optional data. No existing field is removed.
The UI does not read or render MT5 passwords, tokens, or secret credentials.

Access review remains one server-owned lifecycle. In addition to approve and
reject, an authorized reviewer can request more information, revoke approved
access, or restore revoked access. These transitions require an audit reason
(except routine approval), synchronize Auth claims asynchronously, and retain
the user's role history and original application record.

## Deployment and migration

1. Deploy `firestore.indexes.json` and wait for every new `users` and
   `admin_audit_logs` index to report Ready.
2. Deploy `firestore.rules`.
3. Deploy `adminUpdateUserAccessControl`, `adminAddUserNote`,
   `syncPendingUserClaims`, and `notifyTradingSignalUpdate` from
   `functions/index.js`.
4. Run a read-only migration preview from the `functions` directory:

   `npm run migrate:admin-user-search`

5. Review `usersScanned`, `usersUpdated`, and `unresolvedTicketUsers`, then
   apply the additive backfill:

   `npm run migrate:admin-user-search -- --apply`

6. Repeat the dry run. `usersUpdated` should be zero unless user data changed
   while the migration was running.
7. Release the Flutter client after indexes, rules, functions, and migration
   are ready.

The script batches at most 400 writes per commit. It does not delete users,
roles, referrals, MT5 records, tickets, or signal data.

## Rollback

- Roll back the Flutter client independently; all new user fields and signal
  lifecycle fields are additive and older clients ignore them.
- Restore the prior Cloud Function revision if necessary. Signal update events
  remain valid; pending/failed notification event documents can be replayed by
  the repaired function later.
- Restore the prior Firestore rules only together with the prior client. Doing
  so removes assigned-provider update support and should be treated as an
  operational regression.
- The user-search backfill needs no data rollback. The additive normalized
  fields can remain unused. If removal is required later, use a separately
  reviewed cleanup migration after all clients have rolled back.
- Composite indexes may remain in place safely. Delete them only after traffic
  confirms no deployed client uses the corresponding queries.

## Manual release QA checklist

- Sign in as Super Admin and update SL, entry, TP, BE, partial close, and final
  status on a test signal; confirm the detail and timeline update without
  reopening the page.
- Sign in as creator and assigned Signal Providers; confirm both can operate
  the applicable signal and cannot operate an unrelated provider's signal.
- Confirm a trader and a suspended staff profile receive a localized,
  non-technical permission error for a direct update attempt.
- Submit the same update twice and submit two stale-version updates from
  separate sessions; confirm one immutable event wins and the stale client is
  prompted to refresh.
- Force FCM failure; confirm the signal and timeline stay committed, the event
  records a failed attempt, and a later retry sends at most once.
- Verify English-only and Chinese-only commentary and details edits.
- Exercise user search for name, email, UID, referral, role, MT5 login, and
  verification reference after migration.
- Combine role, access, verification, MT5, pending-application, ticket, date,
  and provider filters; verify pagination and current sort are visible.
- At mobile, tablet, and desktop widths, verify no overflow, the desktop/tablet
  detail panel collapses, and mobile opens a full-screen user detail view.
- Approve/reject access, request more information, revoke/restore access,
  change an allowed role, attempt prohibited privilege escalation,
  suspend/restore the account, and add a note; verify immutable audit records.
- Confirm normal users cannot list `users`, read administrative notes, or read
  admin audit logs, and confirm no MT5 secrets appear in the admin UI.
