# Journal, trade-call, personality, and referral hardening (v5)

## Architecture inspected

- Flutter uses feature-oriented screens, repository/service model boundaries,
  local `StatefulWidget` state, a `HomeShell` bottom navigation, and
  `MaterialPageRoute` navigation.
- Authentication is Firebase Authentication. Firestore is the primary mobile
  data store; Firebase Storage is used for chart, journal, content, and
  verification uploads. Trusted mutations and privileged reads use callable
  Cloud Functions in `us-central1`.
- The Laravel mobile API remains available through `ApiConfig`, but the current
  production bootstrap creates the Firebase-backed repositories directly.
- English and Simplified Chinese use `AppLocalizations`. Layouts use `Wrap`,
  `LayoutBuilder`, scroll views, and width breakpoints rather than fixed phone
  dimensions.
- Android has one unflavoured application ID,
  `com.hcgaming.hc_trader`, and a release keystore configured through the
  ignored `android/key.properties` file.

## Data Platform removal

The obsolete Settings-only Data Platform information tile and its translation
were removed. The audit found no route, deep link, permission, flag, backend
endpoint, Firebase collection, analytics event, or exclusive asset. Shared
Firebase and reporting infrastructure was retained. No production data is
deleted; see `data-platform-removal.md`.

## Unified trading date filter

`TradingDateFilter` is the single filter contract for Journal, Statistics,
Performance Ratings, trade-call analytics, and PDF reports. It supports all
time, one date, inclusive date range, month, and year. A local start is
inclusive and the following local midnight is exclusive, so the entire end
date is included. UTC timestamps are converted with the authenticated user's
configured time-zone offset before local calendar grouping. Invalid reversed
ranges are rejected.

The temporary `TradingDateFilterSession` keeps the filter while the user moves
among related screens without permanently storing a short-lived preference.
Data-source and main-MT5-account scope remain part of the report filter.
Manual/Excel records and MT5 records are not silently mixed. Only closed trade
records enter performance calculations; deposits and withdrawals remain in the
capital section and never enter trading P/L. The PDF cache key, filename,
displayed period, trades, and totals use the same report/filter instance as the
screen.

The existing real-time Firestore streams remain the journal source so offline
cache, MT5 de-duplication, and immediate save reflection are preserved. Filtering
is performed before all report calculations. No new composite journal index is
needed by this implementation.

## Trade-call statistics definitions

The Flutter calculation policy is centralized in
`TradeCallStatisticsService`. The dashboard obtains only a narrow trade-call
projection from the role-checked `loadTradeCallStatistics` callable:

- Roles 1, 2, 3, and 502 can view overall/provider comparison data.
- Roles 201 and 202 are locked by the callable to the authenticated UID.
- Other roles, suspended accounts, and cross-provider requests are rejected.
- The callable returns no moderation notes or user-profile data. Results are
  ordered by document ID and loaded through opaque 500-record server pages, so
  larger histories are complete without creating an oversized callable response.

Calculations:

- Win: numeric TP1-TP10 status (2-11), explicit TP/win lifecycle result, or a
  completed call with positive pips.
- Loss: numeric SL status 13, explicit SL/loss lifecycle result, or a completed
  call with negative pips.
- Break even: status 15, explicit BE lifecycle result, `IsBE`, or a completed
  call with zero/unknown pips.
- Rate denominator: wins + losses + break evens only.
- Excluded from rates: draft, pending, active without a final outcome,
  cancelled, rejected, duplicate, invalid, test/deleted, and expired calls that
  never activated.
- Multi-target: one signal document is one call. TP1 through TP10 count as one
  win, never multiple independent wins.
- Planned R:R uses entry, first target, and stop distance. Realized R:R uses the
  stored realized field when present.
- Pip size: Forex 0.0001, JPY 0.01, XAU 0.1, XAG 0.01, and supported
  crypto/index contracts 1. Stored authoritative pips take precedence.
- Holding duration: activated/actual-entry timestamp to closed/result timestamp;
  never-activated calls have no holding duration.
- Date grouping uses the final event timestamp first (closed, updated,
  activated/triggered, then created).

The dashboard includes lifecycle counts, outcomes, R:R, pips, duration,
monthly/weekly and instrument/direction/order/source breakdowns, provider
ranking, recent completed calls, sample notices, loading/empty/error states,
and responsive layouts.

## Personality guidance and privacy

Guidance uses the existing Trading Plan questionnaire answer keys and the newest
saved questionnaire result, with the current local draft as a fallback. It does
not create a new personality taxonomy. Incomplete results generate no guidance.
Every result records the questionnaire version and generation timestamp.

The guidance page offers strengths, risks, suitable styles, risk/discipline/
psychology/plan/strategy/review suggestions, full-result review, retake, and a
localized educational disclaimer. Applying selected guidance requires explicit
confirmation and writes only the user's local
`personality_guidance_draft`. It never updates capital, risk settings, Trading
Plans, or Trading Strategies. Saved questionnaire source documents remain under
the existing owner/admin Firestore rules and are not logged.

## Referral hardening

The referral input is rendered only on signed-out registration. Profile,
Settings, and logged-in account updates contain no referral editor. Registration
continues through the trusted callable that normalizes the code, validates role
eligibility and referrer availability, blocks self/circular relationships, and
creates the authoritative relationship transactionally.

Firestore owner/admin profile writes now preserve both legacy snake-case and
camel-case referral identity fields, including `referrerId`, `uplineId`,
`sponsorId`, `sponsor_id`, `referralCodeUsed`, `referredBy`,
`referralRelationshipId`, and `referral_relationship_id`. Rule emulator tests
cover direct create/update rejection. No existing relationship is migrated or
overwritten.

## Manual regression checklist

1. Sign in with several roles and verify Settings has no Data Platform tile or
   gap; confirm Home, Journal, Toolkit, Accounts, and Manage Backend still open.
2. In Journal apply one date, a range, month, year, and all time. Confirm the
   visible manual list and summary change together and persist when opening
   Statistics and Performance Ratings.
3. Select manual mode and confirm Excel-imported manual history is included but
   MT5 is not. Select the main MT5 account and confirm only that account appears.
4. Generate a PDF for every active period and compare its period, trade count,
   P/L, and win/loss totals with the screen.
5. As a Super Admin open Trading Hub > Signals > View statistics. Exercise
   date/provider/instrument/source/status/direction/order filters and provider
   ranking.
6. As each signal provider confirm the page contains only their UID's calls;
   attempt a cross-provider callable request and expect permission denied.
7. Complete or retake the Trading Plan personality questionnaire, open Toolkit
   > Personality Trading Guidance, review the full result, select suggestions,
   and save the draft. Confirm existing plans/strategies/capital are unchanged.
8. Sign out and confirm registration shows an optional referral code and clear
   invalid-code errors. Sign in and confirm registration completion, Profile,
   and Settings do not show an editable referral field.
9. Save unrelated profile information for a user with an upline and verify the
   authoritative referral fields remain unchanged.
