# Partial Public Access, RBAC, Referral Mapping, and Performance Ratings

## Architecture

The implementation extends the existing Firebase/Flutter architecture. It does
not add a second authentication system.

- Flutter evaluates navigation and resource intent through `PermissionService`.
- Firestore rules independently enforce ownership, approval gates, immutable
  sensitive fields, and server-only rating/audit writes.
- Cloud Functions are authoritative for role assignment, referral mapping,
  rating calculation, plan-version snapshots, and sensitive audit records.
- Legacy `role_id`, `accessStatus`, and `access_status` fields remain readable.
  New profiles also use `role_ids`, `permission_overrides`, scope IDs, and a
  claims version.
- The migration is additive and idempotent. It does not remove legacy fields.

## Partial public access

Active registered users, including `unapproved`, `notSubmitted`, `pendingReview`,
and rejected users, can use these own-data capabilities:

- Trading Toolkit and Risk/Position-Size Calculator
- Manual Trading Journal and own manual-data statistics
- Daily Psychology Check
- Trading Plan
- Trading Strategy
- Access application submission/status/withdrawal

MT5, prop-firm, approved signals, market analysis, rating history, report export,
administration, other-user data, and privileged management remain approval- or
permission-gated. Suspension, revocation, deletion, an unusable account state,
or an explicit deny overrides role grants.

## Default roles

| Role | ID | Default scope |
| --- | ---: | --- |
| Super Administrator | 1 | Full authority; privileged assignment and security management |
| Sub Administrator | 2 | Non-privileged roles and operational administration |
| Founder | 3 | Organisation oversight; no privileged-role/security bypass |
| Signal Provider | 201 | Own signals |
| Senior Signal Provider | 202 | Own signals |
| Market Analyst | 501 | Own market analysis |
| Signal Provider Management | 502 | Signal management |
| Potential Client | 700 | Partial-public own-data tools |
| Trader | 750 | Partial-public tools plus approved-client capabilities after approval |
| Leader | 760 | Assigned team scope |
| Recruiter | 770 | Own referral/downline scope |
| Admin Assistant | 780 | Restricted operational administration |
| Financial Consultant | 790 | Assigned-client scope |
| Evaluator | 800 | Assigned-evaluation scope |

Users may hold compatible roles in `role_ids`. User-specific boolean overrides
are applied before grants, with deny taking precedence. Only Super Administrator
may assign privileged roles. Flutter's administration dialog supports selecting
multiple roles, while the callable validates every assignment and writes one
audit record.

## Referral mapping

Registration sends only the referral code and requested public registration
category. The callable transaction re-reads and validates the code, referrer,
usage limit, active window, registration type, team, and mapping.

Supported mappings include referrer, upline, leader, recruiter, team,
organisation, campaign, safe default/requested roles, approval requirement, and
access status. Usage is incremented atomically. The relationship and audit log
are written in the same transaction.

Referral registration never directly assigns Super Administrator, Founder,
Sub Administrator, or Admin Assistant. An authorised advanced-role mapping is
stored as the requested role while the new account receives a safe provisional
Potential Client/Trader role pending approval.

## Performance rating algorithm

Algorithm version: `hc-performance-v1.0.0`.

The Laravel website was inspected first. Its existing trader-performance page
calculates an overall historical grade from win rate, gross-profit/gross-loss
ratio, recovery factor, expectancy, drawdown, and profit concentration. It does
not implement daily/weekly plan, psychology, and discipline components or
historical plan versioning. Therefore the requested configurable fallback is
used for the new rating product while retaining Laravel-compatible result
inputs (realised result, win rate, profit factor, drawdown/risk evidence).

Default overall formula:

```text
Trading Result             20%
Trading Discipline         30%
Psychology                 20%
Trading-Plan Compliance    30%
```

If psychology is unavailable, it is stored as null and marked missing; the
other weights are normalised. It is not silently scored as zero. A no-trade day
has a neutral result component and explicit missing-data evidence.

Confirmed serious violations cap discipline at 45 and plan compliance at 55.
Default penalties are configurable in `performance_rating_config/current`:

- no confirmed stop loss: 30
- risk per trade exceeded: 25
- daily loss limit exceeded: 35
- maximum trades exceeded: 15
- evidence-based rapid re-entry/size escalation after a loss: 15

Unknown fields are recorded as missing rather than violated. Revenge-trading
wording is neutral and requires timing plus position-size evidence.

Daily grouping uses the user's timezone offset. Weekly grouping is Monday to
Sunday and averages each daily rating equally. Weekly consistency uses variance
in discipline/compliance, repeated violations, and psychology-check completion.
A serious day also caps the weekly score so it cannot disappear inside an
average.

## Normalisation and sources

The selected data source is authoritative across calculation:

- manual mode uses only manual/import journal records;
- MT5 mode uses only the selected main MT5 account;
- deposits, withdrawals, balance/credit/bonus/transfer records, cancelled or
  never-activated pending orders, drafts, and deleted/expired orders are
  excluded;
- stable source/account/record identifiers deduplicate records.

## Firestore data

New/extended locations:

```text
users/{uid}/psychology_checks/{checkId}
users/{uid}/trading_plan_versions/{versionId}
users/{uid}/performance_ratings/{daily-or-weekly-id}
roles/{roleId}
performance_rating_config/current
admin_audit_logs/{auditId}
```

Clients may read their own ratings but cannot write scores, algorithm versions,
plan snapshots, role mappings, referral usage, or audit records.

## Recalculation

Cloud Function triggers recalculate when manual journals, selected-account MT5
trades, psychology checks, plans, the selected data source, or main MT5 account
change. Writes include a deterministic SHA-256 calculation hash. Manual
recalculation is callable by the owner or an authorised administrator and an
administrator recalculation is audited.

## Migration

Dry run:

```powershell
npm --prefix mobile/hc_trader/functions run migrate:rbac-ratings
```

Apply after reviewing the report:

```powershell
npm --prefix mobile/hc_trader/functions run migrate:rbac-ratings -- --apply
```

The migration maps legacy statuses, adds `role_ids` without removing `role_id`,
preserves referral relationships, fills safe scope defaults, normalises referral
metadata, seeds role definitions, and seeds the rating configuration.

## Deployment order

1. Deploy Firestore indexes and rules.
2. Deploy Cloud Functions.
3. Run and review the migration dry run.
4. Apply the migration.
5. Release the Flutter application.

Until Functions and rules are deployed, production clients will not receive
the authoritative recalculation and new partial-public Firestore permissions.
