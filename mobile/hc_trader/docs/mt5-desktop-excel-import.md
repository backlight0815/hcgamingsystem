# MT5 Desktop Excel Import System

## Architecture audit and root cause

The previous journal importer in `TradingRepository.importJournals` treats every workbook as one flat table, joins all worksheets, searches for one `open_date`/`pair` header, converts missing profit to zero, and immediately writes randomly named `trading_journals` documents. It cannot safely understand an MT5 Desktop Trade History Report because that report contains metadata, Positions, Orders, Deals, account totals, and Results sections in one worksheet. It also has no file hash, source-ticket identity, account/server check, staging, reconciliation, duplicate conflict handling, or rejected-row preview.

MT5 live synchronization already supplies the application's canonical MT5 journal path:

- `/users/{uid}/mt5_accounts/{accountId}/trades`
- `/users/{uid}/mt5_accounts/{accountId}/orders`
- account-scoped main-account selection
- `Mt5TradeEligibilityService` for executed-trade eligibility
- `TradingDataBuilder`, `TradingAnalytics`, and `TradingReportService` for dashboards and reports
- `/journal_annotations` for user-owned psychology notes, screenshots, tags, and reviews

The desktop importer therefore extends this architecture. It does not create a second statistics system and never writes imported MT5 rows into the manual journal collection.

## Import flow

1. The client reads `.xlsx` OpenXML parts without executing formulas, macros, external links, or embedded objects.
2. Every nonblank worksheet is inspected.
3. A controlled English/Chinese alias registry detects section markers and header meanings. Repeated Time and Price columns are mapped by occurrence within each section.
4. Values are normalized with fixed-precision `Mt5Decimal`. Tickets remain strings. Blank S/L and T/P remain null.
5. Deals are classified as entries, exits, deposits, withdrawals, credits, costs, or adjustments. Buy/Sell alone never determines entry/exit; the Deal Direction column does.
6. Positions, orders, and deals are linked by source IDs first. A deterministic FIFO fallback is used only when an export omits the position link, and fallback identity is marked with lower confidence.
7. Trading P/L and capital flow are calculated separately. Commission, fee, and swap signs are preserved.
8. The deal ledger, Results section, and closing balance are reconciled within a 0.02 currency-unit tolerance.
9. The preview compares the detected account, server, and currency with the selected MT5 connection and blocks mismatches.
10. A read-only callable checks file- and ticket-level duplicates before confirmation.
11. Confirmed records are uploaded in idempotent chunks to an account-scoped staging batch.
12. A Cloud Function revalidates ownership, account identity, duplicate conflicts, counts, and financial totals; writes canonical documents in bounded batches; and marks the import batch complete last.
13. Client streams and performance functions ignore imported records whose batch is not completed, so an interrupted commit cannot produce partial dashboard or report totals.
14. Completed positions use the existing MT5 journal/statistics/report pipeline. Classified balance transactions use the MT5 capital stream.

## Parser profiles

- `mt5_trade_history_sections_v1`: section-based MT5 Trade History Report with Positions, Orders, Deals, totals, and optional Results.
- `mt5_flat_deal_history_v1`: flat deal-history table with controlled header aliases and trade reconstruction from Deal Direction.

The parser supports UTF-8 and UTF-16 OpenXML parts, cached formula results, Excel serial dates, MT5 date strings, decimal comma/point formats, thousands separators, parentheses and Unicode minus negatives, volume pairs, market-price text, merged-cell layouts, blank cells, and broker symbol suffixes.

Limits are 20 MB compressed, 100 MB expanded, 4,096 archive entries, 100,000 rows, and 256 columns. Low-confidence mappings and missing critical columns block commit.

## Canonical Firestore records

Under `/users/{uid}/mt5_accounts/{accountId}`:

- `import_batches/{batchId}`: source hash, parser version/profile, metadata, validation, financial totals, duplicate summary, state, and completion/recalculation status.
- `import_batches/{batchId}/source_chunks/{chunkId}`: idempotent staging chunks and content hashes.
- `trades/{deterministicId}`: normalized positions used by the existing MT5 journal path.
- `orders/{deterministicId}`: all filled, pending, cancelled, expired, and rejected order states. Orders remain excluded from executed-trade analytics.
- `deals/{deterministicId}`: classified source deals and financial eligibility flags.
- `balance_transactions/{deterministicId}`: deposits, withdrawals, credits, bonuses, and other balance adjustments.
- `journal_annotations/{annotationId}`: unchanged user-owned manual information.

Identifiers are account-scoped string tickets. Legacy numeric aliases are written only when a ticket fits a safe integer; the string ticket remains authoritative.

## Duplicate and conflict policy

- Batch ID is deterministic from the account-scoped SHA-256 file hash.
- A successfully imported identical file returns its previous batch and creates no records.
- Positions, orders, deals, and balance transactions use account-scoped source tickets and deterministic document IDs.
- Overlapping reports reuse financially identical records, including identical records originally received through live sync.
- Only monotonic lifecycle updates are allowed (`open -> closed`, or pending-order state to a terminal/fill state).
- Incompatible values for an existing ticket block the import and are never silently overwritten.
- A real ticket always wins over a fallback fingerprint.
- Re-import never writes to `journal_annotations`, so psychology notes, screenshots, attachments, tags, lessons, strategy links, and plan reviews remain intact.

## Financial rules

`netTradingPnl = grossPriceProfit + commission + fee + swap`

`closingBalance = openingBalance + netCapitalFlow + otherBalanceAdjustments + netTradingPnl`

Deposits, withdrawals, credits, bonuses, and adjustments are excluded from gross profit/loss, trading net profit, trade count, win rate, expectancy, profit factor, trading-day results, behaviour analysis, and performance scoring. They remain visible in capital/balance calculations.

Pending, cancelled, expired, rejected, and untriggered orders remain order history and cannot become zero-duration completed trades. Closing Sell/Buy deals with Direction `out` close an opposite position instead of opening a new one.

## Security and auditability

Owners and administrators can read the account-scoped import results. Client writes to batches, deals, and balance transactions are denied by Firestore rules; only authenticated callable functions using the Admin SDK commit data. Callable functions validate the authenticated user, active account ownership, source account number, server, currency, file hash, record count, and reconciled financial totals.

Structured logs include batch ID, parser profile, account match, counts, duplicates, reconciliation, commit, and recalculation status. Full workbook content and authentication data are not logged. Every canonical record carries filename, file hash, batch ID, sheet/row trace, parser version, and related source IDs.

## Existing-data repair

`functions/scripts/audit_mt5_balance_misclassification.js` is dry-run by default. It reports confidently identifiable legacy balance rows, affected users/accounts, old trading P/L, corrected P/L, and capital flow. It changes nothing unless both flags are supplied:

```powershell
npm run audit:mt5-balances
node scripts/audit_mt5_balance_misclassification.js --apply --confirm=RECLASSIFY_MT5_BALANCE_RECORDS
```

Review and archive the dry-run output before applying. The repair is idempotent, preserves annotation documents, creates balance-transaction records, and writes an admin audit log. Recalculate the reported affected performance dates during the maintenance window. Do not run `--apply` as part of normal deployment.

## Deployment

From `mobile/hc_trader`:

```powershell
flutter pub get
flutter analyze
flutter test
firebase deploy --only firestore:rules,firestore:indexes
firebase deploy --only functions:previewMt5DesktopImport,functions:beginMt5DesktopImport,functions:uploadMt5DesktopImportChunk,functions:finalizeMt5DesktopImport
```

Deploy the functions and rules before distributing the app build. No destructive database migration is required for new imports. Configure `server_timezone` and `server_timezone_offset_minutes` on MT5 connection documents where known; reports without a source timezone otherwise show a warning and retain `timezone_unresolved` trace metadata.

## Manual QA

1. Select the main MT5 connection for account `700138229` / `PUPrime-Demo` / USD.
2. Open Journal and choose **Import MT4 / MT5**.
3. Select `test/fixtures/mt5/sample.xlsx`.
4. Confirm two completed positions, four filled orders, five deals, one 1,000.00 deposit, zero withdrawals, net trading P/L -24.64, and closing balance 975.36.
5. Confirm no blocking validation error and approve the import.
6. Confirm the MT5 journal shows exactly two Buy trades and no new short trades.
7. Confirm Dashboard, Statistics, Performance, Account, in-app report, and PDF report use -24.64 trading P/L and 975.36 balance.
8. Add a psychology note/tag/screenshot to one imported trade, import the same file again, and confirm zero new records and unchanged annotation fields.
9. Import the file while another MT5 connection is selected and confirm the account mismatch blocks commit.
10. Interrupt a chunked import before finalize, retry, and confirm no partially visible records or duplicates.

## Current format boundaries

Modern `.xlsx` exports are supported. Legacy binary `.xls`, password-encrypted workbooks, corrupt OpenXML packages, mappings below the safe confidence threshold, and reports with incompatible duplicate ticket values are intentionally rejected. The alias registry can be extended with additional verified broker/localized labels without enabling unrestricted fuzzy matching.
