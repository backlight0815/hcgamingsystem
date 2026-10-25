# Trading Journal repair: engineering and QA report

Investigation: 13–14 October 2026 (Asia/Singapore). Candidate: **1.8.7+1047**.

## Release status

The local Laravel/Firebase/Android integration is repaired and tested with real
HTTP requests and persisted records. Release-signed AAB and debug APK are built.
**Production acceptance is not complete.** The supplied production account
authenticates with Firebase, but the production journal API returns HTTP 403
`EMAIL_VERIFICATION_REQUIRED`. An explicitly authorized verification-email
request returned HTTP 200 on 14 October; the account owner must complete it.
The backend patch is prepared, not deployed. No cPanel session or hosting access
was available to inspect live configuration/logs or apply the patch.

The screenshot's historical transport failure was investigated but could not be
reproduced on the current production route. It must not be described as a proven
DNS, TLS, database or hosting fault. The verified-email rejection is the current
confirmed production blocker, and is separate from that historical symptom.

## Architecture and investigation

- Existing website: Laravel 10 / PHP 8.2, Blade, MySQL and Sanctum. Journal forms
  and API write `trading_journals`; cash flows use `capitals`.
- Existing mobile: Flutter 3.41.7 / Dart 3.11.5, Firebase Auth and Firestore.
  Manual journals remain under `users/{uid}/trading_journals`. The existing
  bridge assigns Laravel sync IDs/revisions and stores cursor/shadow/deletion
  state; no replacement backend or production database was introduced.
- Production URL is `https://www.hctradinghub.com/api/mobile/v1`. `ApiConfig`
  forces this HTTPS URL in release. The “HC Gaming” wording was a legacy error
  label, not evidence of a different host or project.
- Trace: Journal screen sync action → `TradingRepository.synchronizeJournal`
  → `TradingJournalSyncService` → `ApiClient._send` → journal-sync route →
  `AuthenticateTradingJournalSync` → controller/service → ledger and journals.
  In the staged older `api_client.dart`, `_send` and multipart transport catches
  generated “Unable to reach HC Gaming. Check the API URL and your connection.”
  The working tree already contained more specific transport descriptions when
  this repair began. Those earlier descriptions are not credited as new work.
- Desktop and real Android HTTPS requests reached the production changes route
  and received JSON 401 without credentials. Firebase password authentication
  returned 200; authenticated changes/statistics returned JSON 403. This rules
  out a persistent missing route or TLS failure for those tested requests only.
- Production Firebase UID mapping requires a verified email matching exactly one
  website user, then preserves that UID link. This requirement was retained.
  No email verification flag was bypassed and no production trade was edited.
- QA uses the same Laravel application against an isolated SQLite file and the
  official Firebase Auth/Firestore emulators, project `demo-hc-journal`. The
  regular `.env`, MySQL data and Firebase production journals are unchanged.

## Root causes and repairs

| Confirmed issue | Cause and affected component | Correction and evidence |
|---|---|---|
| Current production account cannot link | Unverified Firebase email; authentication middleware returns 403 before journal access | Added visible verification state/action in Journal; authorized verification request sent successfully. Production acceptance remains pending. `production-authenticated.json`. |
| Local Android HTTP development fails | Main network policy disallows cleartext, including the documented emulator URL | Debug-only XML permits 10.0.2.2/loopback; release remains HTTPS-only. Real Android HTTP tests pass. |
| First delete could fail | Missing stored deletion list defaults to an immutable list | Copy before mutation, serialize queue edits, acknowledge only completed operations. Real emulator first deletion and repeated sync pass. |
| Larger journals exceed request budget | One request per upload/delete can exceed the 60/minute route throttle | Send at most 50 operations per batch. Real 61-record upload, retry and deletion pass. |
| Local edits could be overwritten during an upload | Response was applied after the initial snapshot without checking intervening Firestore changes | Transactionally compare current fields with the sent snapshot; retain newer edits and advance the server base only. Real HTTP response delayed by a forwarding client while a Firestore edit occurs; edit survives and uploads next sync. |
| Skipped conflict events could become permanent | Download cursor advanced beyond records not safely applied | Hold checkpoint for replay; validate acknowledgements/cursors, preserve dirty tombstones and aliases, and require transactional identity/revision checks. Existing merge/conflict backend regressions pass; conflict replay under every process-failure timing has not been exhaustively tested. |
| Null prices/P&L could become zero | Transport used presentation-model defaults | New explicit raw-field mapping omits missing numbers and preserves explicit null. Mapping tests and 61 open records with nulls pass. |
| Mobile account selection could be lost | Website integer account IDs and Firestore document IDs share a misleading field name | Store `mobile_trading_account_id` in sync metadata; preserve website account ID separately. Backend and real Android checks pass. |
| Wall-clock dates and zero/null updates were unstable | Eloquent UTC-looking serialization plus loose value comparisons | Preserve the existing journal wall-clock contract, normalize DB dates, distinguish null from zero. Targeted round-trip test passes. This is not a new cross-timezone data migration. |
| Foreign create ID could create another record | Explicit non-owned/missing sync ID fell through the create path | Return not-found; targeted ownership regression passes. |
| Lost create acknowledgement could erase a newer edit | Retry treated an existing mobile ID as unconditional success | Same values return unchanged; different values produce a conflict preserving both copies. Recheck ID under user lock. Regression passes. |
| Unknown realized P&L counted as breakeven | Closed records with null P&L were included in realized counts | Exclude null amounts from realized metrics; explicit zero remains breakeven. Backend tests pass. |
| HTML/auth/provider errors were misleading | JSON decoding could conceal HTTP category; upstream outage treated as expired credentials | Preserve HTTP error category even for HTML, clear unauthorized state, hide raw server errors, classify provider 429/5xx as 503. Flutter/backend regressions pass. |
| First release build failed | `--no-pub` reused generated registration for the development integration-test plugin | Rebuild with normal dependency generation. Signed release builds successfully; no signing configuration change. |

## API verification

Base below is `/api/mobile/v1`. Local HTTP tests used the real app on port 8765;
Firebase authentication traffic used port 9099. Successful responses were checked
against SQLite and Firestore, not merely status codes.

| Endpoint | Method | Observed status | Result/evidence |
|---|---|---|---|
| `/journal-sync/changes` | GET | 200 local; 401 production without token | Paged changes consumed on Android; production TLS route reachable. |
| `/journal-sync/changes?cursor=-1` | GET | 422 local | Invalid cursor rejected. |
| `/journal-sync` | GET | 200 local | Filtered records/identifiers verified. |
| `/journal-sync/{syncId}` | GET | 200 / 404 local | Own live record found; foreign, absent and deleted record hidden. |
| `/journal-sync` | POST | 201 / 200 / 409 local | Create persists once; same retry unchanged; deleted/stale retry conflicts. |
| `/journal-sync/{syncId}` | PATCH | 200 / 409 / 422 local | Update, stale conflict and invalid lot size verified. |
| `/journal-sync/{syncId}` | DELETE | 200 local | Tombstone and no resurrection verified. |
| `/journal-sync/batch` | POST | 200 local | Per-operation results checked; 61 uploads span two batches. Invalid item handling also covered by backend test. |
| `/journal-sync/statistics` | GET | 200 local | Seed 145.00 → seed plus mobile loss 135.00; owner isolation/empty/null tests pass. |
| `/journal-sync/changes`, `/journal-sync/statistics` | GET, valid production Firebase token | 403 | `EMAIL_VERIFICATION_REQUIRED`; no production sync acceptance claimed. |
| `/trading-data` | GET | 200 local | Existing capital retrieval contract; there is no GET `/capitals` route. |
| `/capitals` | POST | 201 / 422 local | Deposit +100, withdrawal -20 persisted; zero deposit rejected. Existing withdrawal policy retained. |
| `/capitals/{id}` | DELETE | 200 local | Disposable QA cash flows cleaned up. |
| Website login/journal forms | GET / POST | 200 / 302 local | CSRF/session login, create/update/delete, persistence and API visibility checked. |
| `/trading-journal/report/pdf` | GET | 200 local | Real 8-page PDF generated and visually inspected; seed instrument and 145.00 present. |

HTTP 500/HTML response handling and provider 503 were deliberately covered by
controlled automated responses, not by causing a production outage. Evidence:
`http-contract.json` (18 checks), `website-smoke.json`, `qa-http.log`,
`backend-sync-final.log`, `android-integration-final.log` under the evidence folder.

## Synchronization and field contract

| Scenario | Result | Observation |
|---|---|---|
| Website → mobile | PASS in isolated QA | Existing website seed downloaded into real Firestore; Journal UI opened. Website form changes separately verified in sync API. |
| Mobile → website | PASS in isolated QA | Android-created row persisted in Laravel SQLite. An API-created row was also rendered by the authenticated website HTML. |
| Updates both ways | PASS in isolated QA | Mobile edit persisted; website API edit returned to Firestore under the same identity. |
| Duplicate prevention | PASS in isolated QA | Three concurrent sync calls and repeated bulk sync retained exact row counts; create retries checked over HTTP. |
| Deletion | PASS in isolated QA | First queue use, repeated sync, 61 bulk deletions, persisted cleanup and tombstones tested. |
| Conflict safety | PASS tested paths | Same-field stale update gives 409, independent edits merge, lost create acknowledgement conflicts, in-flight mobile edit preserved. |
| Statistics | PASS tested contract | 145.00 with one winning seed, 135.00 after a -10 mobile trade; deleted/open/null and other-user exclusions covered. |
| Production two-way sync | BLOCKED | Verified identity and live backend deployment/acceptance still required. |

Shared fields include status, close handling, time-input metadata, open/close
dates, pair, direction, entry/exit, lot size, pips, net P&L, result, notes and
psychology fields. Mobile-only risk/strategy/plan, fee/swap/commission, source
ticket/currency and account metadata stay in the existing JSON metadata store.
Website-only columns are not cleared by omitted mobile fields. Null and omitted
are distinct. Exact allowlists are in `TradingJournalSyncService.php` and
`journal_sync_mapping.dart`, covered by mapping/omitted-field/account tests.

The sync statistics snapshot is the existing all-time manual-journal contract
unless explicit date parameters are supplied. Existing detailed analytics,
period filters, profit-factor and performance formulas were retained and covered
by the broader regression suites. Comparison requires the same source, account
and date filters. Cash flows are **not** trade profit/loss. The pre-existing
Firestore and Laravel capital stores do not have a two-way cash-flow bridge;
this repair verifies the supported API operations without claiming that bridge.
This matters for capital-dependent comparisons: the QA website has 10,000
starting capital and reports 1.45% growth, while the QA mobile account has no
capital entry and its Performance Review reports 0.00% growth. Both show 145
expectancy and the same trade P&L. Full cross-platform growth/balance parity is
therefore not established; it needs matching capital inputs or a separately
implemented cash-flow synchronization contract.

## Android and case matrix

Actual device: existing AVD `hc_trader_api36`, discovered ID `emulator-5554`,
Google Android 16 / API 36 x86_64. Flutter 3.41.7 / Dart 3.11.5; build 1047.
The application launched, logged in through its real UI against the local
Firebase QA account, navigated to Journal, called Laravel and persisted changes.
The initial emulator had a sign-in screen rather than a signed-in QA session.
The supplied production credentials were used only for the authorized production
authentication check and verification-email request, not inserted into source.

| ID | Status | Executed evidence / boundary |
|---|---|---|
| TC001 launch | PASS | Real Android app launch. |
| TC002 valid login | PASS | Firebase emulator login through app UI; production Firebase login HTTP 200 separately. |
| TC003 Journal | PASS | Real navigation and Record Trade/Performance Review controls. |
| TC004 retrieve | PASS | HTTP changes and actual Firestore document. |
| TC005 website → mobile | PASS | Seed downloaded and persisted. |
| TC006 mobile → website | PASS | Android upload persisted; website rendering of API-created row separately checked. |
| TC007 website update | PASS | Website API → same Firestore record; website form → sync index also checked. |
| TC008 mobile update | PASS | Firestore edit → SQLite field. |
| TC009 multiple records | PASS | 61 records, paging, nulls and cleanup. |
| TC010 repeated sync | PASS | Three concurrent calls to the same sync action and repeated sync, no duplicates; not a physical rapid-tap timing test. |
| TC011 empty dataset | PASS | Backend empty-owner tests and UI empty-data regressions. |
| TC012 optional fields | PASS | Mapper tests and real open records with null fields. |
| TC013 statistics | PARTIAL | Actual 145.00/135.00 trade totals plus backend/analytics assertions pass. Capital-dependent growth differs with the separate capital inputs; full metric parity is not established. |
| TC014 deposit | PASS, supported API | HTTP 201, SQLite +100, retrieved by existing trading-data contract. Mobile cash-flow UI not separately exercised on device. |
| TC015 withdrawal | PASS, supported API | HTTP 201, SQLite -20 after meeting existing withdrawal rules. |
| TC016 expired token | PARTIAL | Real invalid token returns 401 on Android; genuine token expiration over time was not induced. Error mapping tests pass. |
| TC017 disconnect | PASS, SDK networking | Firestore networking disabled; sync reports offline, cached seed remains. No physical radio/DNS outage claimed. |
| TC018 reconnect | PASS | Networking restored, real sync completes. |
| TC019 server error | PASS, automated | Controlled HTML/JSON 5xx and upstream provider outage; no live production fault injection. |
| TC020 relaunch | PASS | Driver retained the app; force-stop/relaunch preserved authenticated user, SharedPreferences state and cached Firestore seed. Performance Review opened. Initial flutter test runner uninstalled the app, so its null-session result was a harness issue. |
| TC021 other owner | PASS | Real HTTP 404 plus backend ownership tests. |
| TC022 platform fields | PASS | Mobile account ID real-device check; omitted/website-only fields backend regressions. |
| TC023 website regression | PASS, tested scope | Full backend suite, actual login/forms/PDF/capital; production regression awaits deployment. |
| TC024 mobile regression | PASS, tested scope | Full Flutter unit/widget suite and Android integration. |
| TC025 MT5 unavailable | PASS, manual scope | Manual journal functions in QA without an MT5 service/account; existing MT5 interface unchanged. |

Case totals: 25 assessed, 23 passed within stated scope, 2 partial, 0 failed,
0 pending/unexecuted cases. The genuine-expiry subcase remains unexecuted within
TC016. Production acceptance is blocked separately and must not be inferred
from the local case matrix.

Automated execution:

- Final backend suite: **144 passed, 1 skipped, 4,811 assertions**, 36.80 seconds.
  The existing skipped homepage CMS test requires production-imported CMS tables.
- Journal backend targeted suite: **21 passed, 104 assertions**.
- Final Flutter unit/widget suite: **616 passed, 0 failed**.
- Final Flutter analyzer including the recovery driver: **No issues found**,
  22.1 seconds (`flutter-analyze-release.log`).
- Real Android core integration: **1 passed**, 3:06 test runtime, includes bulk
  and in-flight-edit cases. Offline recovery integration: **1 passed**, 28 seconds.
  The retained-app driver then passed recovery again (26 seconds) and actual
  process relaunch/Performance Review (13 seconds). Driver logs include an extra
  framework teardown test; these counts refer to authored scenarios.
- Runtime logs also contain non-blocking Google Play Services broker and demo
  notification-preference permission warnings. Manual journal assertions passed;
  these logs do not establish production push-notification behavior.
- Initial Flutter failures were corrected without disabling assertions: three
  analytics widget tests needed to wait for real compute-isolate completion;
  error expectations were aligned with intentional safe response messages.
- First AAB attempt failed due to stale generated test-plugin registration;
  normal release build subsequently passed.

## Modified files

This work started in an extensively modified working tree. Pre-existing changes
were retained; this list describes this repair, not every repository diff.

| Files | Purpose |
|---|---|
| `app/Http/Middleware/AuthenticateTradingJournalSync.php`, `config/trading_journal.php` | Safe local-only Auth emulator support and provider failure classification. |
| `app/Services/TradingJournalSyncService.php` | ID ownership, metadata, dates, null equality and safe create retries. |
| `app/Http/Controllers/Api/Mobile/V1/TradingJournalSyncController.php` | Realized-statistics null exclusion. |
| `mobile/hc_trader/lib/services/trading_journal_sync_service.dart` | Queue/batch, transaction, replay and conflict protections. |
| `mobile/hc_trader/lib/services/journal_sync_mapping.dart` | Explicit transport mapping and equality. |
| `mobile/hc_trader/lib/core/network/api_client.dart` | Error category, timeout and safe user messages. |
| `mobile/hc_trader/lib/features/journal/journal_screen.dart` | Explicit verification action/status. |
| `mobile/hc_trader/lib/repositories/trading_repository.dart` | Optional Firestore injection for real isolated integration; default unchanged. |
| `mobile/hc_trader/android/app/src/debug/res/xml/network_security_config.xml` | Debug emulator/loopback HTTP only. |
| `mobile/hc_trader/pubspec.yaml`, `pubspec.lock` | 1046 → 1047; SDK integration-test development dependency. |
| `tests/Feature/TradingJournalSyncApiTest.php` | Targeted backend regressions. |
| Flutter `test/journal_api_error_test.dart`, `journal_sync_mapping_test.dart`, `trading_blog_api_service_test.dart` | Response/mapping checks and intentional safe-message expectations. |
| Flutter `test/helpers/pump_analytics.dart`, `analytics_redesign_test.dart`, `performance_module_test.dart` | Wait for compute isolates while retaining original UI assertions. |
| Flutter `integration_test/journal_live_test.dart`, `journal_recovery_test.dart`, `test_driver/journal.dart`, `firebase.journal-qa.json` | Actual Android/Firebase/HTTP scenarios. |
| `tests/Support/journal_qa.py`, `journal_qa_seed.php`, `journal_website_smoke.py`, `journal_http_contract.py`, `journal_production_probe.py` | Isolated test services, real HTTP evidence, memory-only production authentication. |
| Mobile README/release notes, integration docs and release package | Correct architecture/deployment instructions and Fix/Enhance/New release entries. |

## Reproduction

Use the installed PHP/Flutter/Android SDK/JDK and Python with `requests`. No
production password belongs in a script, environment file or command argument.

1. From `mobile/hc_trader`, start `firebase emulators:start --config
   firebase.journal-qa.json --project demo-hc-journal --only auth,firestore`.
2. From repository root run `py -3 tests/Support/journal_qa.py`. It uses only
   `output/journal-repair-20261013/private/journal.sqlite`, ports 8765/8766 and
   disposable emulator identities. The private directory is gitignored.
3. Boot the existing `hc_trader_api36` AVD and discover its ID using `flutter devices`.
4. Run `flutter test integration_test/journal_live_test.dart -d emulator-5554`.
5. For persistence, use `flutter drive --driver=test_driver/journal.dart
   --target=integration_test/journal_recovery_test.dart -d emulator-5554
   --keep-app-running`, force-stop the package, then repeat with
   `--dart-define=QA_RECOVERY_RESUME=true`. `flutter test` uninstalls on completion
   and cannot by itself establish cross-process persistence.
6. Run `py -3 tests/Support/journal_website_smoke.py` and
   `py -3 tests/Support/journal_http_contract.py` against the running QA services.
7. `php artisan test --compact`; mobile `flutter analyze`; `flutter test`;
   `flutter build appbundle --release`; `flutter build apk --debug`.
8. Production read-only probe: `py -3 tests/Support/journal_production_probe.py`.
   Enter credentials interactively. The optional `--send-verification` is an
   account email action and must only be used with explicit owner authorization.

## Artifacts and outstanding acceptance

| Artifact | Path | SHA-256 |
|---|---|---|
| Signed release AAB | `mobile/hc_trader/releases/HC-Trader-1.8.7-build-1047-journal-repair.aab` | `FE688C691EA55C17C1813BE7E41779B08AA67377F574773B708C604A237C7872` |
| Debug APK | `mobile/hc_trader/releases/HC-Trader-1.8.7-build-1047-journal-repair-debug.apk` | `A94BB42806FE627E13660DC1233466CDD9611BDFE3FC57D69E80D2D306BFE000` |
| Backend ZIP, four files | `release_packages/trading_journal_repair_20261014/hcgaming_trading_journal_repair_20261014.zip` | `26E5D8E255784D108551FBEE672FAF360C2342B2EEE9FE52EDE0EE444C967A37` |

AAB: 76,902,177 bytes; APK: 215,368,630 bytes. Existing package
`com.hcgaming.hc_trader`, min SDK 24, target SDK 36 and ARM32/ARM64/x86_64 are
preserved. AAB `jarsigner` reports `jar verified` with self-signed certificate/no
timestamp warnings. Neither artifact was uploaded to Google Play.

Evidence is in [`output/journal-repair-20261013`](../output/journal-repair-20261013).
The initial launch screenshot, runtime logs, sanitized HTTP results, build logs,
PDF and visual contact sheet are retained. Private QA fixture credentials and
database are excluded from the deployment package.

The backend archive's file list/hashes were checked against current source. Its
[deployment and rollback instructions](../release_packages/trading_journal_repair_20261014/DEPLOYMENT.md)
follow the previous merge-only cPanel procedure. Final QA database verification
found only the original seed trade (145 net P&L) and starting capital remaining;
temporary HTTP/Android records were cleaned up.

Before production acceptance: finish email verification, confirm the unique
website account mapping, obtain the authorized hosting session, back up and
compare live files, deploy the small package following its instructions, and
repeat read/write/delete/statistics/isolation using disposable QA records.
Do not mutate the user's existing trading history. cPanel document root, PHP
runtime/extensions, DB schema/migrations, permissions, caches and server logs
remain unverified from hosting itself. The HTTP route checks do not establish
those details. No production deployment or historical screenshot root-cause
closure is claimed.

The capital-dependent metric gap and genuine token-expiry subcase remain open
in addition to production acceptance. The repair is not represented as satisfying
every item in the original definition of done.
