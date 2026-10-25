# Professional financial platform implementation handoff

Date: 2026-07-16  
Application: HC Trader `1.7.3+1023`  
Firebase project: `hcgaming-31d5d`  
Cloud Functions region: `us-central1`  
Firestore location: `nam5`

This document covers the requested Content, Announcements, Prop Firm Performance, Leaderboard, Feature Configuration, trading-signal notification, Stock Information, and Financial Daily Report work. It is also the manual QA and deployment handoff.

## 1. Existing architecture discovered

- Flutter application using Firebase Authentication, Cloud Firestore, Cloud Functions, Firebase Messaging, and Firebase Storage.
- Stateful Flutter screens consume repository streams and callable functions; application navigation is coordinated by `HomeShell`.
- English and Simplified Chinese are resolved through the existing `AppLocalizations` lookup/fallback layer.
- Firestore is the operational data store. Node 22 Cloud Functions perform privileged writes, notification delivery, scheduled work, ranking, and audit logging.
- Existing roles, access status, permission overrides, publisher identity validation, performance ratings, MT5 data, and trading-signal lifecycle records were retained and extended.
- Existing modules were not replaced. New screens and repositories were integrated through the current More/Admin/Home navigation surfaces.

## 2. Files created

Flutter domain and data access:

- `lib/models/content_management.dart`
- `lib/models/app_notification.dart`
- `lib/models/feature_configuration.dart`
- `lib/models/leaderboard.dart`
- `lib/models/stock_market.dart`
- `lib/repositories/feature_configuration_repository.dart`
- `lib/repositories/leaderboard_repository.dart`
- `lib/repositories/notification_repository.dart`
- `lib/repositories/stock_repository.dart`

Responsive Flutter screens:

- `lib/features/content/content_management_screen.dart`
- `lib/features/announcements/announcement_screens.dart`
- `lib/features/leaderboard/leaderboard_screen.dart`
- `lib/features/notifications/notification_center_screen.dart`
- `lib/features/stocks/stock_information_screen.dart`
- `lib/features/stocks/financial_report_admin_screen.dart`
- `lib/features/admin/feature_configuration_control_center.dart`
- `lib/features/prop_firm/domain/prop_firm_calculator.dart`

Backend and tests:

- `functions/stock_market_functions.js`
- `functions/feature_configuration_functions.js`
- `functions/leaderboard_functions.js`
- `functions/publishing_schedule_functions.js`
- `functions/trading_signal_update_functions.js`
- `functions/test/financial_platform_functions.test.js`
- `test/financial_platform_models_test.dart`

## 3. Files modified

The principal existing files changed for integration are:

- `lib/app/home_shell.dart` — repositories, notification routing, and module navigation.
- `lib/features/more/more_screen.dart` — new user/admin destinations.
- `lib/features/prop_firm/prop_firm_screen.dart` — redesigned performance presentation using the corrected calculator.
- `lib/models/announcement.dart` and `lib/models/app_user.dart` — announcement targeting/metrics and access metadata.
- `lib/repositories/trading_repository.dart` — content/announcement publishing and evaluated feature configuration.
- `lib/services/push_notification_service.dart` — preferences, device registration, foreground display, and route dispatch.
- `lib/core/localization/app_localizations.dart` — English/Chinese labels and financial terminology.
- `functions/index.js` — exports, announcement/content push behavior, localization, and global notification feature control.
- `functions/registration_functions.js` — server-side `user_registrations` emergency control.
- `functions/performance_rating_engine.js` and `functions/performance_rating_functions.js` — trading-only profit and ranking inputs.
- `functions/package.json` — syntax/test coverage for the added functions.
- `firestore.rules` and `firestore.indexes.json` — authorization, privacy, immutable events, workflows, and queries.
- `test/trading_services_test.dart` and backend signal/performance tests — cash-flow exclusion and notification regressions.

The worktree contained substantial pre-existing user changes. No unrelated dirty files were reset, deleted, or reformatted in bulk.

## 4. Data models added or changed

- Content: typed content kind and publication status; bilingual title/body/summary with at-least-one-language validation and fallback; category, tags, symbol, media, scheduling, featured, notification request, author, engagement, and audit timestamps.
- Announcement: bilingual title/short message/body; type, priority, audience, roles, selected users, prop-firm groups, subscription/access levels, start/end, acknowledgement, dismissal, action/deep link, push request, and delivery/read/acknowledgement/dismissal metrics.
- Notification: category, localized title/body, entity and symbol identifiers, deep link, read/archive state, and event timestamp.
- Feature configuration: mode, visibility, platform, environment, roles, access levels, users, regions, minimum version, deterministic rollout percentage, dependencies, time window, critical flag, and version.
- Stock: search result, quote, freshness/delay, profile, news, calendar, price history, snapshot, watchlist preferences, report source references, and versioned report workflow.
- Leaderboard: category, period, risk-adjusted score, eligibility, verification, badges, tie-aware rank, and privacy mode. New preferences default to private/opted out.
- Prop firm/performance: account profit and return are derived from executed closed trades plus floating P/L. Deposits and withdrawals remain broker cash-flow context and never count as trading profit, targets, or leaderboard return.
- User: prop-firm group IDs and subscription/access level support audience and feature targeting.

## 5. Firestore collections added or changed

- `contents/{contentId}` for generic managed content. Existing content collections remain supported: `trading_blogs`, `trading_news`, `market_analyses`, `knowledge_centres`, `knowledgeCentres`, `trading_recordings`, and `trading_resources`.
- `announcements/{announcementId}` and `announcements/{announcementId}/reads/{uid}`.
- `stocks/{symbol}` with `quotes`, `news`, `priceHistory`, and `reports` subcollections.
- `users/{uid}/watchlist/{symbol}`.
- `leaderboardPeriods/{periodId}/entries/{uid}` and `users/{uid}/preferences/leaderboard`.
- `featureConfigurations/{featureKey}` with `history/{version}`; `featureConfigurationAudit` stores change/rollback events. `app_configuration/modules` remains a backward-compatible mirror.
- `users/{uid}/notificationPreferences/default`, `users/{uid}/notifications/{eventId}`, and `users/{uid}/devices/{deviceId}`. Legacy `notification_tokens` is read during transition.
- `notificationEvents/{eventId}`, `notificationDeliveries/{deliveryId}`, and `scheduledJobs/{jobId}`.
- `trading_signals/{signalId}/events/{updateId}` is an immutable server event mirror; existing `updates` drives notification creation.
- Existing `audit_logs` receives report workflow and content/announcement administrative changes.

## 6. Firestore indexes required

The checked-in `firestore.indexes.json` contains the required additions:

| Collection/group | Fields |
| --- | --- |
| `leaderboardPeriods` | `status ASC`, `ends_at DESC` |
| `performance_ratings` group | `period_type ASC`, `period_start ASC` |
| `performance_ratings` group | `user_id ASC`, `period_type ASC`, `period_start DESC` |
| `reports` | `report_status ASC`, `report_date DESC` |
| `reports` group | `report_status ASC`, `published_at DESC` |
| `reports` group | `report_status ASC`, `updated_at DESC` |
| `notifications` | archived/category/read combinations followed by `created_at DESC` |
| `notificationDeliveries` | `status ASC`, `deliver_after ASC` |
| `announcements` | `status`, `audience`, plus separate role/user/prop-firm-group/access-level array indexes |

Scheduled content queries use a single equality and filter their bounded due set by timestamp in the function, avoiding a separate composite index per legacy content collection.

## 7. Firestore security-rule changes

- Published content is readable only to signed-in/approved audiences while drafts remain administrative. Firestore blocks publication when `content_publishing` is disabled, maintenance, hidden, or read-only. The client and privileged scheduled/callable paths additionally evaluate rollout, targeting, version, and time windows.
- Announcement reads enforce status, time window, audience, role, selected user, prop-firm group, and access-level targeting. Users may write only their own constrained read/acknowledgement/dismissal record.
- Signal updates retain lifecycle authorization; notification event mirrors are immutable and server-written.
- Stock data/reports are authenticated reads; report workflow writes are callable-only. Watchlists, notification preferences, notification read/archive fields, and leaderboard privacy preferences are owner-only and field constrained.
- Leaderboard periods/entries, rate limits, notification event/delivery records, scheduled jobs, feature configuration/history/audits, and report workflow writes cannot be forged by clients.
- Feature writes are callable-only with optimistic version checks and super-admin control of critical features.
- User registration and MT5/signal/content write paths evaluate their corresponding emergency controls.
- The rules and indexes compiled successfully through `firebase deploy --dry-run` on 2026-07-16.

## 8. Cloud Functions added or modified

| Function | Purpose |
| --- | --- |
| `searchStocks` | Authenticated, rate-limited provider/cache search. |
| `getStockSnapshot` | Cached/provider profile, quote, news, calendar, and history retrieval. |
| `saveFinancialDailyReportDraft` | Admin/analyst bilingual draft save with source validation and optimistic versioning. |
| `reviewFinancialDailyReport` | Enforced draft/review/schedule/publish/reject/archive transitions and audit. |
| `notifyFinancialDailyReportPublished` | Per-user localized in-app and device delivery to eligible watchlist subscribers. |
| `generateFinancialDailyReportDrafts` | Hourly exchange-local draft generation with weekend, holiday, duplicate, and feature checks. |
| `updateFeatureConfiguration` | Transactional validation, dependency checks, history, audit, and legacy mirror. |
| `rollbackFeatureConfiguration` | Versioned transactional rollback and audit. |
| `calculateLeaderboardPeriod` | Privileged secure period calculation from canonical performance ratings. |
| `recalculateActiveLeaderboards` | Scheduled active-period refresh, disabled by the leaderboard feature switch. |
| `publishScheduledContent` | Five-minute due publication/expiry sweep honoring content and announcement switches. |
| `notifyTradingSignalUpdate` | Immutable event claim, authorization, localization, preferences, quiet hours, in-app record, delivery logging, and FCM. |
| `retryDeferredSignalNotifications` | Retries quiet-hour and transient deferred signal delivery. |

Existing announcement/content triggers now require an explicit notification request, honor the global push switch, remove invalid device tokens, and record delivery/read/acknowledgement metrics. Registration now checks `user_registrations` inside the Admin-SDK callable so it cannot bypass client rules.

## 9. Notification-event mapping

Signal `update_type` is the stable event key. English and Chinese labels are stored in `UPDATE_LABELS`; unknown non-critical values fall back to the generic signal-update copy.

| Preference | Signal events |
| --- | --- |
| `new_signals` | `published` |
| `entry_activation` | `entry_triggered`, `buy_limit_activated`, `sell_limit_activated`, `buy_stop_activated`, `sell_stop_activated`, `entry_price_updated`, `entry_range_updated` |
| `stop_loss_updates` | `update_stop_loss`, `trailing_stop_loss`, `trailing_stop_updated` |
| `breakeven_updates` | `move_sl_to_be`, `update_break_even`, `trailing_break_even`, `break_even_hit` |
| `take_profit_events` | `target_hit`, `partial_close`, `partial_profit_taken` |
| `signal_cancellation` | `cancelled`, `expired`, `invalidated`, `remove_pending_order` |
| `signal_closure` | `manually_closed`, `closed_in_profit`, `closed_in_loss`, `completed`, `stop_loss_hit` |
| `important_signal_notes` | `commentary`, `important_note`, `provider_update`, `warning`, `correction`, `reopened` |
| master `signals` fallback | `details_updated`, `lock_profit`, `reduce_risk`, `risk_changed`, `symbol_corrected`, `market_commentary`, `chart`, `analysis`, `archived`, `restored`, and future recognized events without a narrower preference |

Stock-report publication uses `financial_daily_report_published` and preference `financial_daily_reports`. Announcement publication uses its announcement type plus the master announcement preference. A stable event ID (`signalId_updateId`, or stock symbol/report/version) prevents duplicate in-app or device delivery.

## 10. Deep-link mapping

| Category | Payload/deep link | App destination |
| --- | --- | --- |
| Signal | `hc-trader://signals/{signalId}?event={updateId}` with `category=signals`, `document_id={signalId}` | Authorized `TradeCallDetailScreen`; unavailable signals fall back to the Hub. |
| Announcement | announcement ID with `category=announcements`, `document_id={announcementId}` | Announcement experience; Firestore audience rules remain authoritative. |
| Stock report | `hc-trader://stocks/{symbol}/reports/{reportId}` with symbol/report IDs | Stock detail and its published reports. |
| Stock/watchlist | `hc-trader://stocks/{symbol}` with `category=stock_watchlist` | Stock detail. |

The router trusts category and IDs only as navigation hints. It reloads protected data through repositories/rules and does not grant access from a notification payload.

## 11. Data migrations

No destructive migration is required.

- Existing `app_configuration/modules` values are used when a versioned feature document does not exist. The first feature update synthesizes history version `0` before creating the next version.
- Existing notification token documents remain readable while new device documents are written to `users/{uid}/devices`.
- Existing content collections continue to work; generic new types use `contents`.
- Existing performance records remain readable. New recalculations add `initialCapital`, `net_trading_profit`, `return_percentage`, `max_drawdown_percentage`, and closed-trade identifiers.
- Seed one `stocks/{SYMBOL}` document per supported instrument. At minimum include `symbol`, `company_name`, `exchange`, `currency`, `exchange_timezone`, `provider`, `search_terms`, `report_generation_enabled`, `report_generation_hour`, and any exchange `market_holidays`. Populate cache subcollections only when the external provider is not configured.
- Recalculate performance ratings and leaderboard periods after deployment if production needs historic leaderboard coverage.

## 12. Stock-data-provider setup instructions

The backend uses the `StockMarketDataProvider` abstraction. It selects the configured HTTP adapter only when both provider settings exist; otherwise it uses the Firestore cache provider.

The HTTP provider contract is:

- `GET {BASE_URL}/search?q={query}&limit={limit}` with `Authorization: Bearer {API_KEY}`. Return an array in `items`, `results`, or `data` with symbol, company name, exchange, currency, current price/change, and freshness.
- `GET {BASE_URL}/snapshot?symbol={symbol}` with the same authorization. Return either the object or `{data: object}` containing `quote`, `profile`, `news`, `calendar`, `history`, `provider`, and `source_references`.
- Quote data must provide `as_of`, `freshness` (`realTime`, `delayed`, `endOfDay`, or `unavailable`), and `delay_minutes` when applicable. Never label delayed data as real time.
- The adapter times out after 10 seconds, maps provider 429 responses to resource exhaustion, rate-limits users, caches successful snapshots, and falls back to existing Firestore data when the configured provider is unavailable.

Use a licensed vendor appropriate for the exchanges and redistribution rights required by production. Keep the vendor secret server-side only.

## 13. Required Firebase configuration

- Firebase Authentication and Firestore enabled for `hcgaming-31d5d`.
- Cloud Functions 2nd gen using Node 22 in `us-central1`.
- Cloud Scheduler and Eventarc APIs enabled for scheduled and Firestore-triggered functions.
- Firebase Cloud Messaging configured; Android notification channel is `hc_trader_updates`. Configure APNs credentials before enabling iOS/macOS delivery.
- Deploy `firestore.rules` and `firestore.indexes.json` before exposing the new UI.
- Preserve valid `google-services.json`/platform Firebase options for each shipped target.
- Set feature documents from the control center only after the callable functions and rules are live. Critical switch changes require a super admin.
- Add an app-link/universal-link association later if external web links must launch the application; current FCM routing works from notification data payloads inside supported builds.

## 14. Required environment variables

| Setting | Type | Required | Purpose |
| --- | --- | --- | --- |
| `STOCK_DATA_API_KEY` | Firebase Functions secret | Required for HTTP provider | Bearer credential; never ship in Flutter. |
| `STOCK_DATA_PROVIDER_BASE_URL` | Firebase Functions string parameter | Required for HTTP provider | HTTPS provider adapter base URL. |
| `APP_VERSION` | Flutter `--dart-define` | Recommended | Feature minimum-version evaluation; defaults to `1.7.3`. |

Example setup:

```powershell
firebase functions:secrets:set STOCK_DATA_API_KEY
Set-Content -Path functions\.env.hcgaming-31d5d -Value 'STOCK_DATA_PROVIDER_BASE_URL=https://provider-gateway.example.com'
flutter build appbundle --dart-define=APP_VERSION=1.7.3
```

Do not commit the generated environment file or secret value. When no HTTP provider is configured, seed and maintain Firestore stock cache data instead.

## 15. Test results

Executed on 2026-07-16:

- `flutter analyze` — passed, zero issues.
- `flutter test` — passed, 360 tests.
- `flutter build apk --debug --dart-define=APP_VERSION=1.7.3` — passed; generated `build/app/outputs/flutter-apk/app-debug.apk`.
- Node syntax checks for the entry point and all changed financial-platform modules — passed.
- All 16 Node unit/regression groups in `functions/package.json` — passed.
- `firebase deploy --only firestore:rules,firestore:indexes --dry-run --non-interactive` — passed; server rules compiler returned no rule issues and index input was accepted.
- All five checked-in Firestore/Storage rules emulator groups — passed after exposing the Android-bundled JDK 21 through `JAVA_HOME`/`PATH`.
- Added coverage includes bilingual at-least-one-language validation/fallback, financial-report source validation, feature normalization, signal authorization/preferences/quiet hours/deduplication/localization, ranking ties/privacy/risk, and exclusion of deposits from prop-firm/performance profit.

## 16. Remaining limitations

- The HTTP market-data integration is a vendor-neutral adapter. Production remains on Firestore cache until a licensed provider URL and secret are configured.
- The deterministic scheduled report generator creates sourced market summaries and disclosures, not investment recommendations. Richer narrative generation should be added only with an approved source/citation policy and human review.
- Watchlist push currently covers newly published daily reports. Price-threshold, earnings, dividend, and material-movement alert engines are not included.
- FCM device delivery is enabled by the current client service on Android, iOS, and macOS. Web and Windows retain the in-app notification centre but require platform-specific push setup for background device notifications.
- Scheduled sweeps and signal fan-out are deliberately bounded. At substantially larger scale, replace user scans with queue-based fan-out and partitioned jobs.
- Notification report links currently land on the correct stock detail/report area rather than auto-opening a report modal by report ID.
- The Android build warns that Flutter will soon drop support for minimum Android SDK 23. Plan and test a move to minimum SDK 24; it was not changed here because supported-device policy is outside this feature rollout.

## 17. Manual QA checklist

Run on a small phone, large phone/tablet, and wide desktop/web viewport in both English and Chinese.

- [ ] Sign in as super admin, sub admin, market analyst, signal provider, approved trader, pending user, suspended user, and private leaderboard user.
- [ ] Content dashboard: search, filter, sort, preview, create English-only, create Chinese-only, reject both-empty, schedule, publish, archive, and restore every supported content type.
- [ ] Confirm unpublished content cannot be read by a trader and `content_publishing` pauses both direct and scheduled publication.
- [ ] Announcement: create all audience types, role/user/group/access-level targeting, future schedule, expiry, acknowledgement, dismissal, action, localized push, and metric updates.
- [ ] Verify a non-targeted user cannot read an announcement even when given its document ID.
- [ ] Prop firm: test deposit and withdrawal before/after closed trades, floating P/L, daily loss, total drawdown, phase target, phase completion, no trades, and account status.
- [ ] Leaderboard: opt in/out, private identity, minimum eligibility, tie rank, badges, verified source, weekly/monthly/all-time category filters, and disabled feature state.
- [ ] Feature control: preview, expected-version conflict, dependency failure, role/platform/access/region/version targeting, schedule, rollout, read-only, maintenance, emergency disable, audit history, and rollback.
- [ ] Signal: publish and emit every mapped update type. Confirm authorized audience, English/Chinese device copy, per-event preference, quiet-hour deferral, retry, invalid-token cleanup, one in-app item, one delivery record, and correct signal detail navigation.
- [ ] Stock: search by symbol/company, no result, provider failure/cache fallback, real-time/delayed label, chart ranges, profile/news/calendar/source links, add/remove/reorder watchlist, and offline/error/retry states.
- [ ] Financial report: bilingual validation, sources, optimistic conflict, draft/review/reject/schedule/publish/archive transitions, market holiday/weekend, duplicate scheduled run, localized watchlist delivery, disclaimer, and permission denial.
- [ ] Notification centre: unread/read, category filter, archive, preferences, empty/error states, and cold/background/foreground notification opens.
- [ ] Run TalkBack/VoiceOver checks, text scaling, keyboard focus, contrast, tap targets, and landscape overflow checks.
- [ ] Execute the Firebase rule emulator suites in release CI and retain their output with the release evidence.

## 18. Deployment steps

1. Export/backup production Firestore and record current function/rule versions. Confirm the target project with `firebase use`.
2. Configure `STOCK_DATA_API_KEY` and `STOCK_DATA_PROVIDER_BASE_URL`, or seed verified Firestore cache stock data.
3. Run:

   ```powershell
   flutter pub get
   flutter analyze
   flutter test
   Push-Location functions
   npm ci
   npm run check
   npm test
   Pop-Location
   $env:JAVA_HOME='C:\Program Files\Android\openjdk\jdk-21.0.8'
   $env:Path="$env:JAVA_HOME\bin;$env:Path"
   firebase emulators:exec --only firestore,storage "npm --prefix functions run test:rules"
   ```

4. Deploy rules and indexes first:

   ```powershell
   firebase deploy --only firestore:rules,firestore:indexes
   ```

5. Deploy functions. A full functions deploy is safest because existing announcement/content triggers were also changed:

   ```powershell
   firebase deploy --only functions
   ```

6. Seed supported stock metadata and create/verify feature configurations. Begin new features disabled or with a small deterministic rollout; keep `push_notifications` disabled until device QA succeeds.
7. Recalculate required historical performance ratings and leaderboard periods using an authorized administrator.
8. Build the release with the correct version define, perform the checklist on staging, then distribute through the normal Play/App Store pipeline.
9. Monitor `notificationDeliveries`, `notificationEvents`, `scheduledJobs`, function errors/latency, provider rate limits, report audit logs, and feature audit history during rollout.

## 19. Rollback instructions

1. Use the Feature Configuration control center for the fastest safe response:
   - disable `push_notifications` to stop device delivery while retaining records;
   - disable or set `stock_report_publishing`/`content_publishing` to maintenance;
   - disable `trading_signals`, `leaderboard`, `stock_information`, or `announcements` independently;
   - disable `user_registrations` or `mt5_sync` for their emergency paths.
2. Use the version history rollback callable for an individual feature. It uses expected-version conflict protection and writes an audit event.
3. If code rollback is required, redeploy the last known-good Functions source and Flutter artifact. Do not delete new collections; older clients ignore them.
4. Restore the previous `firestore.rules` and `firestore.indexes.json` from version control, dry-run them, then deploy. Never loosen rules merely to restore an old client.
5. Restore Firestore from the pre-deployment export only for verified data corruption. Feature history, audit logs, notification events, and delivery logs should normally be retained for incident review.
6. Rotate `STOCK_DATA_API_KEY` immediately if provider credentials are suspected, and remove/replace the provider base URL to force the safe Firestore-cache fallback.
7. After rollback, confirm user registration, sign-in, existing Hub/Journal/MT5 flows, signal reads, content reads, and notification opt-outs before closing the incident.
