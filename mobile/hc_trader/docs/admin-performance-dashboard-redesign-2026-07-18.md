# Manage Backend Performances redesign and QA

Date: 2026-07-18  
Scope: Flutter `Manage Backend → Performances` on Android, Windows, and web

## 1. Original UI problems

The former module was a single `FutureBuilder` with a centered progress indicator and a basic vertical card list. It had no bounded query window, timeout, retry-friendly skeleton, dashboard summary, comparison, detail workspace, charts, search, filters, sorting, pagination, or responsive desktop table. A permission or long-running Firestore collection read could therefore leave the page looking permanently busy. The legacy prop-firm review dialog also lived inside the large general admin operations file and exposed the action without the dedicated review permission check.

## 2. Redesigned solution

The replacement is a responsive financial-analytics workspace with:

- professional header, last-updated state, refresh, export, active-filter count, and wide-screen quick period/source selectors;
- eight KPI cards with prior-period comparisons and correct positive/negative treatment for drawdown;
- deterministic improvement, decline, high-drawdown, review, and inactivity insights;
- real aggregate P/L trend and profitability-distribution charts using the existing `fl_chart` dependency;
- top-performer ranking with a calculation description;
- searchable, sortable, selectable, horizontally scrollable, locally paginated desktop table with density settings;
- expandable mobile trader cards instead of a compressed table;
- period, source, result, risk, minimum-trade, minimum-profit, maximum-drawdown, and sort filters;
- a trader detail screen with account/capital facts, report metrics, score explanation, daily trend, monthly/instrument/session breakdowns, and eligible recent trades;
- comparison of two to five selected traders using one explicit period and data-source scope;
- localized Excel export of selected rows, or all currently filtered rows;
- skeleton, empty, permission-denied, refresh, retry, chart-empty, and export-failure states.

## 3. Modified files

- `lib/core/localization/app_localizations.dart`
- `lib/features/admin/admin_overview_screen.dart`
- `lib/features/admin/admin_trading_operations.dart`
- `lib/repositories/trading_repository.dart`
- `firestore.indexes.json`
- `firestore.rules`

## 4. Newly created files

- `lib/features/admin/performance/admin_performance_screen.dart`
- `lib/features/admin/performance/admin_performance_controller.dart`
- `lib/features/admin/performance/admin_performance_models.dart`
- `lib/features/admin/performance/admin_performance_dashboard_widgets.dart`
- `lib/features/admin/performance/admin_performance_chart_widgets.dart`
- `lib/features/admin/performance/admin_performance_table_widgets.dart`
- `lib/features/admin/performance/admin_performance_filter_sheet.dart`
- `lib/features/admin/performance/admin_performance_support_widgets.dart`
- `lib/features/admin/performance/admin_performance_detail_screen.dart`
- `lib/features/admin/performance/admin_performance_comparison_screen.dart`
- `lib/features/admin/performance/admin_performance_review_dialog.dart`
- `lib/features/admin/performance/admin_performance_export_service.dart`
- `test/admin_performance_dashboard_test.dart`
- this document

## 5. Removed obsolete code

No standalone file was deleted. The old spinner/list performance tab and its old prop-firm review dialog were removed from `admin_trading_operations.dart`. The leaderboard's still-used performance card remains intact.

## 6. Responsive behaviour

All decisions use available width through `LayoutBuilder`, `Wrap`, `Expanded`, `ConstrainedBox`, and `CustomScrollView`; there are no device-name checks.

- Under 700 logical pixels: single-column content, wrapping header actions, mobile cards, and a full-height bottom-sheet filter experience.
- 700–1249: multi-column KPI/analytics layouts and the horizontally scrollable performance table.
- 1200 and wider: header period/source quick selectors and balanced multi-column content.
- Content is capped at 1680 logical pixels so ultra-wide layouts do not over-stretch.
- Table density can switch between comfortable and compact.
- Representative automated viewports: 360×800, 393×852, 600×960, 768×1024, 1024×768, 1280×720, 1366×900, 1440×900, 1920×1080, and 2560×1080, plus 1.4× phone text scaling.

## 7. Data-flow changes

`AdminPerformanceController` owns loading, refresh deduplication, filter state, 350 ms search debounce, sort, selection, local pagination, table density, and export state. UI build methods consume typed `AdminTraderPerformance` records rather than calculating financial results.

The repository now issues collection-group reads rather than one query per visible trader. Journal reads are bounded to the selected report period plus the equivalent prior comparison period. Both Firestore `Timestamp` and legacy ISO-string `close_date` records are queried and deduplicated by document path. Source filtering is server-side when the composite index is available and falls back to a date-bounded read plus local source filtering while the index is being deployed. If either a collection-group index or recursive read rule is still being deployed, the repository temporarily performs the same bounded queries against each trader's nested collection. A 30-second timeout converts a stalled request into a retryable error.

Capital transactions remain all-time because starting capital, deposits, withdrawals, and current balance require the full capital ledger.

## 8. Calculation changes

No new production scoring formula was invented. Admin records now reuse `TradingReportService`, the existing tested report/calculation service. It continues to:

- include only eligible closed trade records;
- exclude cancelled, pending, inactive, non-trade, and duplicate records;
- exclude deposits and withdrawals from net trading profit/loss;
- safely calculate win/loss rates, averages, profit factor, payoff ratio, expectancy, growth, drawdown, streaks, best/worst day, and report score;
- return safe labels when profit factor or other values are unavailable.

The former admin ranking formula is preserved for leaderboard compatibility. New KPI previous-period values use the immediately preceding equivalent date range. A higher drawdown is explicitly treated as negative.

## 9. Permission handling

- Module entry and data loading require the existing `AdminPermission.performanceManage` permission.
- Unauthorized widgets do not start the repository request; this is covered by a widget test.
- Prop-firm review controls require the separate `AdminPermission.propFirmResultReview` permission on both overview and detail surfaces.
- Firestore collection-group rules require an authenticated admin for nested `trading_journals` and `capitals` reads.
- Review writes continue through the existing repository method and audit-log mechanism.
- No new permission framework or role assumptions were introduced.

## 10. Localization changes

All new user-facing titles, controls, filters, table labels, metrics, chart copy, empty/error states, review actions, comparison labels, and export headers pass through the existing `AppLocalizations.text` architecture. Simplified Chinese overrides were added without a paid or network translation service. Generic keys that could alter unrelated modules were deliberately avoided. Responsive tests cover Chinese and increased text scaling.

## 11. Backend and Firestore changes

- Added recursive collection-group read rules for nested `trading_journals` and `capitals`, restricted to admins.
- Added a collection-group single-field index for `trading_journals.close_date` and a composite index for `trading_journals(source ASC, close_date ASC)`.
- Bounded journal reads by current and comparison windows.
- Added legacy ISO date compatibility, source-index fallback, safe permission-denied handling, and a 30-second timeout.
- No Cloud Function, aggregation document, or destructive migration was added.

Firebase validation command:

```text
firebase deploy --only firestore:rules,firestore:indexes --dry-run
```

Result: success; rules compiled and the dry run completed. The CLI also reports an existing non-blocking warning that `firebase.json` contains an unknown `flutter` property.

## 12. Required Firestore indexes

Deploy the new `COLLECTION_GROUP` indexes:

```text
Collection group: trading_journals
close_date: ASCENDING/DESCENDING (single-field collection-group scope)

Collection group: trading_journals
source: ASCENDING
close_date: ASCENDING
```

Firestore creates single-field indexes with collection scope by default, not collection-group scope. Until the collection-group indexes finish building, all reports remain operational using the documented bounded per-trader fallback.

## 13. Migration steps

No data migration is required. Deploy the updated rules and indexes:

```text
firebase deploy --only firestore:rules,firestore:indexes
```

Legacy string and current timestamp `close_date` values are both supported. A later background normalization to timestamps is optional, not required for this release.

## 14. New dependencies

None. The implementation reuses `fl_chart`, `excel`, `file_picker`, `intl`, Firebase, and the existing analytics/theme utilities already declared by the application.

## 15. Unit-test results

The new suite covers typed parsing, summaries, deterministic insights, finite percentage helpers, filtering, sorting, pagination, remote period/source scoping, bounded loading/error state, and Excel workbook contents. Existing trading-report tests continue to cover duplicate exclusion, deposits/withdrawals, inactive/cancelled records, zero/division safeguards, data-source isolation, drawdown, expectancy, and score inputs.

Result: passed.

## 16. Widget-test results

The new widget tests cover mobile cards, mobile filters, desktop table, detail screen, comparison screen, Chinese localization, unauthorized access, representative breakpoints, and increased text scaling.

Result: 13 new module tests passed; complete project suite passed all 382 tests.

## 17. Integration-test results

The widget-level integration flows exercise repository → controller → overview rendering, period/source request scoping, detail and comparison screens, export workbook generation, localization, and permission denial. Firestore rules/indexes were validated with a Firebase dry run. A live production Firestore write or review action was intentionally not executed during automated QA.

## 18. Android build result

Command: `flutter build apk --release`  
Result: success  
Artifact: `build/app/outputs/flutter-apk/app-release.apk`  
Size: 99,810,381 bytes (Flutter reported 95.2 MB)

Existing toolchain warnings: minimum SDK 23 will lose Flutter support in a future release; Java source/target 8 and some third-party Android APIs are deprecated. These are not introduced by this module.

## 19. Windows build result

Command: `flutter build windows --release`  
Result: success  
Artifact: `build/windows/x64/runner/Release/hc_trader.exe`

## 20. Chrome web build result

Command: `flutter build web --release`  
Result: success  
Artifact: `build/web`

The normal JavaScript web/Chrome build succeeds. Flutter's optional WebAssembly dry run reports existing incompatibilities in `flutter_secure_storage_web` (`dart:html`/`dart:js_util`) and lints in the third-party `image` package. These do not fail or affect the standard Chrome JavaScript build.

## 21. Features not fully completed

- Export is Excel-compatible only in this admin surface; PDF, CSV, print, and share variants were not duplicated because no equivalent admin report template existed. The trader-facing PDF reporting implementation remains unchanged.
- Recent detail history is deliberately capped at 50 eligible trades and currently supports search, source filtering, and local pagination. Full server-paged history, attachment opening, direction/result/date filters, and a standalone trade-detail route were not added.
- The table supports horizontal scrolling, sorting, selection, pagination, density, and selected-row export, but not sticky headers or a user-configurable column-visibility menu.
- The existing Manage Backend tab architecture does not expose per-tab browser URLs/deep links, so this redesign preserves current tab/back navigation rather than introducing a second routing system.
- Review status/note and audit logging are supported through the existing prop-firm review workflow. Follow-up owner, review date, flag removal, and a separate review-history screen are not present in the existing data model and were not invented.

## 22. Remaining risks and limitations

- Overall KPI accuracy requires reading all active trader user documents and all capital ledgers. Journal history is date-bounded and avoids row-by-row queries, but a very large tenant should eventually use scheduled aggregate documents or a trusted Cloud Function for server-paged trader summaries.
- UI pagination is local because cross-trader aggregate KPI cards need the complete filtered trader summary set. It is not a Firestore cursor over users.
- Firestore's mixed legacy date types require two bounded journal queries until old ISO-string dates are normalized.
- The new composite index and recursive rules must be deployed before the most efficient source-filter path is available.
- Currency is formatted with the application's existing formatter because no reliable per-account currency field is present in the current admin result model; mixed-currency portfolio totals should not be introduced until that data is available.
- The optional WebAssembly target remains blocked by existing third-party dependencies; standard Chrome JavaScript web output is validated.

## Validation command summary

```text
flutter clean
flutter pub get
dart format
flutter analyze              # No issues found
flutter test                 # 382 tests passed
flutter build apk --release  # Success
flutter build windows --release # Success
flutter build web --release  # Success
firebase deploy --only firestore:rules,firestore:indexes --dry-run # Success
```

`flutter clean` returned success but emitted an existing Windows file-lock warning while removing one build directory. Dependency restore and every subsequent analyzer, test, and release build completed successfully.
