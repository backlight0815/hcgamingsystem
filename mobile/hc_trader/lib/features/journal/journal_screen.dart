import 'dart:async';

import 'package:firebase_auth/firebase_auth.dart';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:file_picker/file_picker.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/config/mt5_sync_config.dart';
import '../../core/localization/app_localizations.dart';
import '../../core/network/api_client.dart';
import '../../core/security/access_policy.dart';
import '../../core/security/permission_service.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/capital_transaction.dart';
import '../../models/journal_attachment.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/trading_journal_entry.dart';
import '../../models/trading_pair.dart';
import '../../models/trading_report.dart';
import '../../models/trading_account.dart';
import '../../models/trading_data_source.dart';
import '../../models/trading_date_filter.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../services/trading_analytics.dart';
import '../../services/trading_data_source_service.dart';
import '../../services/trading_report_pdf_service.dart';
import '../../services/trading_report_service.dart';
import '../../services/trading_date_filter_session.dart';
import '../../shared/empty_state.dart';
import '../../shared/financial_pairing_dropdown.dart';
import '../../shared/formatters.dart';
import '../../shared/metric_tile.dart';
import '../../shared/trading_data_builder.dart';
import '../../shared/trading_data_source_selector.dart';
import '../../shared/trading_date_filter_bar.dart';
import '../access/access_required_view.dart';
import 'journal_form_screen.dart';
import 'mt5_excel_import_screen.dart';
import 'trading_report_preview_screen.dart';
import 'widgets/trading_report_workspace.dart';

List<String> journalFinancialPairingOptions({
  required List<TradingPair> pairs,
  required Iterable<Object?> journalPairings,
  String? selected,
}) {
  final symbols = <String>{};
  for (final pair in pairs) {
    final symbol = normalizeFinancialPairing(pair.symbol);
    if (symbol.isNotEmpty) symbols.add(symbol);
  }
  for (final value in journalPairings) {
    final symbol = normalizeFinancialPairing(value);
    if (symbol.isNotEmpty) symbols.add(symbol);
  }
  final selectedSymbol = normalizeFinancialPairing(selected);
  if (selectedSymbol.isNotEmpty) symbols.add(selectedSymbol);
  return symbols.toList(growable: false)..sort();
}

bool journalMatchesFinancialPairing(Object? value, String? selected) {
  final selectedSymbol = normalizeFinancialPairing(selected);
  return selectedSymbol.isEmpty ||
      normalizeFinancialPairing(value) == selectedSymbol;
}

class JournalScreen extends StatefulWidget {
  const JournalScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.storage,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;

  @override
  State<JournalScreen> createState() => _JournalScreenState();
}

class _JournalScreenState extends State<JournalScreen>
    with SingleTickerProviderStateMixin {
  final _reportService = TradingReportService();
  final _pdfService = TradingReportPdfService();
  late final TabController _tabController;
  late TradingDateFilter _dateFilter;
  var _exporting = false;
  var _importing = false;
  var _syncingJournal = false;
  final _searchController = TextEditingController();
  String? _pairingFilter;
  String? _statusFilter;
  int? _resultFilter;
  var _activeMode = TradingDataDisplayMode.manualOnly;
  var _reportGenerationId = 0;
  String? _exportStage;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    _dateFilter = TradingDateFilterSession.read(
      widget.user.uid,
      timezone: widget.user.timezone,
      timezoneOffsetMinutes: widget.user.timezoneOffsetMinutes,
    );
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) unawaited(_runJournalSync());
    });
  }

  Future<void> _runJournalSync() async {
    if (_syncingJournal) return;
    setState(() => _syncingJournal = true);
    try {
      await widget.repository.synchronizeJournal(widget.user.uid, force: true);
    } on Object {
      // The service keeps the exact recoverable state for the banner.
    } finally {
      if (mounted) setState(() => _syncingJournal = false);
    }
  }

  Future<void> _sendJournalVerification() async {
    final user = FirebaseAuth.instance.currentUser;
    if (user == null) return;
    try {
      await user.reload();
      if (FirebaseAuth.instance.currentUser?.emailVerified == true) {
        await _runJournalSync();
        return;
      }
      await user.sendEmailVerification();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Verification email sent. Open the link, then tap Sync journal.',
          ),
        ),
      );
    } on FirebaseAuthException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error.code == 'too-many-requests'
                ? 'A verification email was recently requested. Check your inbox or try again later.'
                : 'Unable to send the verification email. Please retry.',
          ),
        ),
      );
    }
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (ClientAccessPolicy.isRestricted(widget.user, AppFeatureArea.journal)) {
      return const AccessRequiredView(featureName: 'Journal');
    }
    return StreamBuilder<List<TradingJournalEntry>>(
      stream: widget.repository.journalsFor(widget.user.uid),
      builder: (context, manualSnapshot) {
        if (manualSnapshot.hasError) {
          return Center(child: Text('${manualSnapshot.error}'));
        }
        if (!manualSnapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        return StreamBuilder<List<CapitalTransaction>>(
          stream: widget.repository.capitalsFor(widget.user.uid),
          builder: (context, manualCapitalSnapshot) {
            if (manualCapitalSnapshot.hasError) {
              return Center(child: Text('${manualCapitalSnapshot.error}'));
            }
            if (!manualCapitalSnapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final manualJournals = filterTradesForDisplayMode(
              manualSnapshot.data!,
              TradingDataDisplayMode.manualOnly,
            );
            final manualCapitals = manualCapitalSnapshot.data!;
            return TradingDataBuilder(
              user: widget.user,
              repository: widget.repository,
              builder: (context, journals, capitals, pairs, mode) {
                _activeMode = mode;
                final overallSummary = TradingAnalytics().summarize(
                  journals: manualJournals,
                  capitals: manualCapitals,
                );
                final report = _reportService.build(
                  journals: journals,
                  capitals: capitals,
                  filter: TradingReportFilter(
                    dateFilter: _dateFilter,
                    source: mode == TradingDataDisplayMode.mt5Only
                        ? TradingReportSource.mt5
                        : TradingReportSource.manual,
                    mainMt5AccountId:
                        mode == TradingDataDisplayMode.mt5Only &&
                            journals.isNotEmpty
                        ? journals.first.tradingAccountId ?? ''
                        : '',
                  ),
                );
                final years = _availableYears(journals, capitals);
                final pairingOptions = journalFinancialPairingOptions(
                  pairs: pairs,
                  journalPairings: [
                    ...manualJournals.map((journal) => journal.pair),
                    ...journals.map((journal) => journal.pair),
                  ],
                  selected: _pairingFilter,
                );
                final query = _searchController.text.trim().toLowerCase();
                final manualReport = _reportService.build(
                  journals: manualJournals,
                  capitals: manualCapitals,
                  filter: TradingReportFilter(
                    dateFilter: _dateFilter,
                    source: TradingReportSource.manual,
                    ownerUid: widget.user.uid,
                  ),
                );
                final filteredJournals = manualReport.trades.reversed.where((
                  journal,
                ) {
                  if (!journalMatchesFinancialPairing(
                    journal.pair,
                    _pairingFilter,
                  )) {
                    return false;
                  }
                  if (_statusFilter != null &&
                      journal.status != _statusFilter) {
                    return false;
                  }
                  if (_resultFilter != null &&
                      journal.result != _resultFilter) {
                    return false;
                  }
                  if (query.isEmpty) return true;
                  return journal.pair.toLowerCase().contains(query) ||
                      journal.notes.toLowerCase().contains(query) ||
                      journal.psychologyNotes.toLowerCase().contains(query) ||
                      journal.mistakeTags.any(
                        (tag) => tag.toLowerCase().contains(query),
                      );
                }).toList();
                final pendingSync = widget.storage
                    .readList(widget.user.uid, 'pending_journal_sync')
                    .length;
                final journalSyncStatus = widget.storage.readMap(
                  widget.user.uid,
                  'journal_sync_status',
                );
                final journalSyncStats = widget.storage.readMap(
                  widget.user.uid,
                  'journal_sync_statistics',
                );
                final journalSyncState = widget.storage.readMap(
                  widget.user.uid,
                  'journal_sync_state',
                );

                return NestedScrollView(
                  headerSliverBuilder: (context, innerBoxIsScrolled) => [
                    SliverToBoxAdapter(
                      child: Column(
                        children: [
                          _JournalActionsBar(
                            exporting: _exporting,
                            importing: _importing,
                            mode: mode,
                            onRecordTrade: () => _openForm(
                              context: context,
                              pairs: pairs,
                              availableBalance: overallSummary.currentBalance,
                            ),
                            onDeposit: () => _openCapitalDialog(
                              context: context,
                              journals: journals,
                              capitals: capitals,
                              initialType: 1,
                            ),
                            onWithdraw: () => _openCapitalDialog(
                              context: context,
                              journals: journals,
                              capitals: capitals,
                              initialType: 2,
                            ),
                            onPdfReport:
                                PermissionService(
                                  widget.user,
                                ).can(AppPermission.reportsExportOwn)
                                ? () => _exportReport(journals, capitals)
                                : null,
                            onPerformanceReview: () =>
                                _showPerformanceReview(report),
                            onImport: _importing ? null : _importJournals,
                          ),
                          if (pendingSync > 0)
                            MaterialBanner(
                              content: Text(
                                context.l10n.text(
                                  '{count} offline trade draft(s) waiting to sync.',
                                  {'count': pendingSync},
                                ),
                              ),
                              leading: const Icon(Icons.cloud_off_outlined),
                              actions: [
                                if (journalSyncStatus?['state'] ==
                                    'verification')
                                  TextButton(
                                    onPressed: _syncingJournal
                                        ? null
                                        : _sendJournalVerification,
                                    child: const Text('Verify email'),
                                  ),
                                TextButton(
                                  onPressed: () async {
                                    await widget.repository
                                        .refreshTradingData();
                                    if (mounted) setState(() {});
                                  },
                                  child: Text(context.l10n.text('Sync now')),
                                ),
                              ],
                            ),
                          if (mode == TradingDataDisplayMode.manualOnly &&
                              widget.repository.firebaseEnabled)
                            MaterialBanner(
                              leading: _syncingJournal
                                  ? const CircularProgressIndicator()
                                  : Icon(
                                      journalSyncStatus?['state'] == 'synced'
                                          ? Icons.cloud_done_outlined
                                          : Icons.sync_problem_outlined,
                                    ),
                              content: Text(
                                _syncingJournal
                                    ? 'Synchronizing manual journal…'
                                    : journalSyncStatus?['state'] == 'synced'
                                    ? 'Website journal synced ${journalSyncState?['last_synced_at'] ?? ''}. '
                                          '${journalSyncStats?['total_trades'] ?? 0} trades, '
                                          '${journalSyncStats?['winning_trades'] ?? 0} wins, '
                                          '${journalSyncStats?['losing_trades'] ?? 0} losses, '
                                          'net ${journalSyncStats?['total_net_profit_loss'] ?? '0.00'}.'
                                    : '${journalSyncStatus?['message'] ?? 'Manual journal has not synchronized yet.'}',
                              ),
                              actions: [
                                TextButton(
                                  onPressed: _syncingJournal
                                      ? null
                                      : journalSyncStatus?['state'] == 'auth'
                                      ? () => FirebaseAuth.instance.signOut()
                                      : _runJournalSync,
                                  child: Text(
                                    journalSyncStatus?['state'] == 'auth'
                                        ? 'Sign in again'
                                        : 'Sync journal',
                                  ),
                                ),
                              ],
                            ),
                          TradingDataSourceSelector(
                            user: widget.user,
                            repository: widget.repository,
                            mode: mode,
                          ),
                          const SizedBox(height: 8),
                          _ReportToolbar(
                            filter: _dateFilter,
                            years: years,
                            onChanged: (value) {
                              TradingDateFilterSession.write(
                                widget.user.uid,
                                value,
                              );
                              setState(() => _dateFilter = value);
                            },
                          ),
                          _JournalFilterBar(
                            controller: _searchController,
                            pairingOptions: pairingOptions,
                            pairing: _pairingFilter,
                            status: _statusFilter,
                            result: _resultFilter,
                            visibleCount: filteredJournals.length,
                            onSearch: (_) => setState(() {}),
                            onPairing: (value) =>
                                setState(() => _pairingFilter = value),
                            onStatus: (value) =>
                                setState(() => _statusFilter = value),
                            onResult: (value) =>
                                setState(() => _resultFilter = value),
                            onClear: () => setState(() {
                              _searchController.clear();
                              _pairingFilter = null;
                              _statusFilter = null;
                              _resultFilter = null;
                            }),
                          ),
                        ],
                      ),
                    ),
                    SliverPersistentHeader(
                      pinned: true,
                      delegate: _TabBarHeaderDelegate(
                        TabBar(
                          controller: _tabController,
                          tabs: [
                            Tab(
                              icon: const Icon(Icons.receipt_long_outlined),
                              text: context.l10n.text('Manual Trading Journal'),
                            ),
                            Tab(
                              icon: const Icon(Icons.sync_alt_outlined),
                              text: context.l10n.text(
                                'MT5 Synchronized Journal',
                              ),
                            ),
                            Tab(
                              icon: const Icon(Icons.analytics_outlined),
                              text: context.l10n.text('Reports'),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                  body: TabBarView(
                    controller: _tabController,
                    children: [
                      _TradesTab(
                        journals: filteredJournals,
                        pairs: pairs,
                        onAdd: () => _openForm(
                          context: context,
                          pairs: pairs,
                          availableBalance: overallSummary.currentBalance,
                        ),
                        onEdit: (journal) => _openForm(
                          context: context,
                          pairs: pairs,
                          journal: journal,
                          availableBalance:
                              overallSummary.currentBalance -
                              journal.profitLoss,
                        ),
                        onDelete: (journal) => _confirmDelete(context, journal),
                        onOpen: (journal) => _openTradeDetails(journal),
                        mode: TradingDataDisplayMode.manualOnly,
                      ),
                      PermissionService(
                            widget.user,
                          ).can(AppPermission.mt5SyncOwn)
                          ? _Mt5SyncedTradesTab(
                              user: widget.user,
                              repository: widget.repository,
                              storage: widget.storage,
                              pairingFilter: _pairingFilter,
                            )
                          : AccessRequiredView(
                              featureName: 'MT5 Synchronized Journal',
                              user: widget.user,
                            ),
                      TradingReportWorkspace(
                        report: report,
                        rawRecordCount: journals.length,
                        rawCapitalCount: capitals.length,
                        exporting: _exporting,
                        exportStage: _exportStage,
                        onExport:
                            PermissionService(
                              widget.user,
                            ).can(AppPermission.reportsExportOwn)
                            ? () => _exportReport(journals, capitals)
                            : null,
                      ),
                    ],
                  ),
                );
              },
            );
          },
        );
      },
    );
  }

  Future<void> _openForm({
    required BuildContext context,
    required List<TradingPair> pairs,
    required double availableBalance,
    TradingJournalEntry? journal,
  }) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => JournalFormScreen(
          user: widget.user,
          repository: widget.repository,
          storage: widget.storage,
          pairs: pairs,
          journal: journal,
          availableBalance: availableBalance,
        ),
      ),
    );
    if (mounted) setState(() {});
  }

  Future<void> _openCapitalDialog({
    required BuildContext context,
    required List<TradingJournalEntry> journals,
    required List<CapitalTransaction> capitals,
    required int initialType,
  }) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _JournalCapitalDialog(
        user: widget.user,
        repository: widget.repository,
        journals: journals,
        capitals: capitals,
        initialType: initialType,
      ),
    );
    if (saved == true && context.mounted) {
      AppFeedback.success(
        context,
        initialType == 2 ? 'Withdrawal saved.' : 'Deposit saved.',
      );
    }
  }

  Future<void> _importJournals() async {
    final selected = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['xlsx', 'xls', 'csv'],
      withData: true,
    );
    if (selected == null || selected.files.isEmpty) return;
    if (!mounted) return;
    final file = selected.files.single;
    final extension = file.extension?.trim().toLowerCase() ?? '';
    if (extension == 'xlsx') {
      final bytes = file.bytes;
      if (bytes == null || bytes.isEmpty) {
        if (mounted) {
          _showMessage(
            context.l10n.text('The selected workbook could not be read.'),
          );
        }
        return;
      }
      setState(() => _importing = true);
      try {
        final accounts = await widget.repository.loadMt5Accounts(
          widget.user.uid,
          forceServer: true,
        );
        if (!mounted) return;
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => Mt5ExcelImportScreen(
              bytes: bytes,
              filename: file.name,
              accounts: accounts,
            ),
          ),
        );
      } catch (error) {
        if (mounted) _showMessage(error.toString());
      } finally {
        if (mounted) setState(() => _importing = false);
      }
      return;
    }
    if (extension == 'xls') {
      _showMessage(
        context.l10n.text(
          'Legacy .xls files are not supported. Export the MT5 report as .xlsx.',
        ),
      );
      return;
    }
    final accounts = await widget.repository.loadTradingAccounts(
      widget.user.uid,
    );
    if (!mounted) return;
    String? accountId;
    if (accounts.isNotEmpty) {
      final selection = await showDialog<String>(
        context: context,
        builder: (_) => _ImportAccountDialog(accounts: accounts),
      );
      if (selection == null) return;
      accountId = selection.isEmpty ? null : selection;
    }
    if (!mounted) return;
    setState(() => _importing = true);
    try {
      final result = await widget.repository.importJournals(
        file: ApiUploadFile(
          field: 'file',
          filename: file.name,
          path: file.path,
          bytes: file.bytes,
        ),
        tradingAccountId: accountId,
      );
      if (mounted) {
        _showMessage(
          context.l10n.text(
            'Import complete: {imported} new, {updated} updated, {skipped} skipped.',
            {
              'imported': result['imported'] ?? 0,
              'updated': result['updated'] ?? 0,
              'skipped': result['skipped'] ?? 0,
            },
          ),
        );
      }
    } catch (error) {
      if (mounted) _showMessage(error.toString());
    } finally {
      if (mounted) setState(() => _importing = false);
    }
  }

  Future<void> _openTradeDetails(TradingJournalEntry journal) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) =>
          _TradeDetailsSheet(journal: journal, repository: widget.repository),
    );
    if (mounted) setState(() {});
  }

  Future<void> _showPerformanceReview(TradingReport report) async {
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => _PerformanceReviewSheet(report: report),
    );
  }

  Future<void> _confirmDelete(
    BuildContext context,
    TradingJournalEntry journal,
  ) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Delete trade')),
        content: Text('${journal.pair} ${formatDateTime(journal.closeDate)}'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(context.l10n.text('Delete')),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    await widget.repository.deleteJournal(
      uid: widget.user.uid,
      journalId: journal.id,
    );
  }

  Future<void> _exportReport(
    List<TradingJournalEntry> journals,
    List<CapitalTransaction> capitals,
  ) async {
    final generationId = ++_reportGenerationId;
    setState(() {
      _exporting = true;
      _exportStage = 'Validating report account';
    });
    try {
      final source = _activeMode == TradingDataDisplayMode.mt5Only
          ? TradingReportSource.mt5
          : TradingReportSource.manual;
      final account = source.requiresMainMt5Account
          ? await widget.repository.validateMainMt5AccountForReport(
              widget.user.uid,
            )
          : null;
      if (!mounted || generationId != _reportGenerationId) return;
      _setExportStage(generationId, 'Preparing report filters');
      final confirmed = await _confirmReportGeneration(account: account);
      if (confirmed != true ||
          !mounted ||
          generationId != _reportGenerationId) {
        return;
      }
      final filter = TradingReportFilter(
        dateFilter: _dateFilter,
        source: source,
        ownerUid: widget.user.uid,
        mainMt5AccountId: account?.id ?? '',
        stage: 'overall',
        timezone: 'Asia/Kuala_Lumpur',
        languageCode: Localizations.localeOf(context).languageCode,
      );
      if (kDebugMode) {
        debugPrint(
          '[Trading Report] start generationId=$generationId '
          'uid=${widget.user.uid} mainMt5AccountId=${account?.id ?? '-'} '
          'login=${account?.login ?? '-'} source=${source.apiValue} '
          'stage=${filter.stage} period=${filter.dateFilter.cacheKey} '
          'timezone=${filter.timezone} language=${filter.languageCode}',
        );
      }
      final reportAccount = account == null
          ? null
          : TradingReportAccount(
              id: account.id,
              name: account.label,
              login: account.login.toString(),
              broker: account.brokerServer.split('-').first,
              server: account.brokerServer,
              currency: account.currency,
              balance: mt5AccountCapitalBalance(account),
              equity: account.equity,
            );
      _setExportStage(generationId, 'Calculating report statistics');
      final report = _reportService.build(
        journals: journals,
        capitals: capitals,
        filter: filter,
        traderLabel: widget.user.label,
        account: reportAccount,
        currentBalanceOverride: account == null
            ? null
            : mt5AccountCapitalBalance(account),
      );
      if (kDebugMode) {
        debugPrint(
          '[Trading Report] calculation generationId=$generationId '
          'records=${report.trades.length} '
          'accountId=${report.filter.mainMt5AccountId.isEmpty ? '-' : report.filter.mainMt5AccountId} '
          'score=${report.scoreProfile.score.toStringAsFixed(2)}',
        );
      }
      if (report.trades.isEmpty) {
        _showMessage(
          source == TradingReportSource.mt5
              ? 'No trading records were found for the selected account and period.'
              : 'No trading journal records found for this report.',
        );
        return;
      }
      _setExportStage(generationId, 'Generating charts and localized PDF');
      final bytes = await _pdfService.build(report: report);
      if (!mounted || generationId != _reportGenerationId) return;
      if (account != null) {
        final latestAccount = await widget.repository
            .validateMainMt5AccountForReport(widget.user.uid);
        if (latestAccount.id != account.id) {
          _showMessage(
            'The main MT5 account changed while the report was generating. Please generate it again.',
          );
          return;
        }
      }
      final file = await _pdfService.writeTemporaryFile(
        report: report,
        bytes: bytes,
        currentUid: widget.user.uid,
      );
      if (!mounted || generationId != _reportGenerationId) {
        await _pdfService.deleteTemporaryFile(file.path);
        return;
      }
      _setExportStage(generationId, 'Opening report preview');
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => TradingReportPreviewScreen(
            report: report,
            bytes: bytes,
            temporaryPath: file.path,
            pdfService: _pdfService,
          ),
        ),
      );
      if (mounted) _showMessage('The PDF was generated successfully.');
    } on Mt5MainAccountException catch (error) {
      if (mounted) _showMessage(error.message);
    } catch (error) {
      if (mounted) {
        _showMessage('Unable to generate the PDF report.');
      }
      if (kDebugMode) {
        debugPrint(
          '[Trading Report] generationError generationId=$generationId '
          'uid=${widget.user.uid} error=$error',
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _exporting = false;
          _exportStage = null;
        });
      }
    }
  }

  void _setExportStage(int generationId, String stage) {
    if (!mounted || generationId != _reportGenerationId) return;
    setState(() => _exportStage = stage);
  }

  Future<bool?> _confirmReportGeneration({Mt5Account? account}) {
    return showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(dialogContext.l10n.text('Generate PDF Report')),
        content: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _ReportConfirmationRow(
                label: 'Source',
                value: account == null
                    ? 'Manual Journal'
                    : 'MT5 Main Account - ${account.label} / ${account.login}',
              ),
              if (account != null) ...[
                _ReportConfirmationRow(
                  label: 'Broker / server',
                  value: account.brokerServer,
                ),
              ],
              _ReportConfirmationRow(
                label: 'Period',
                value: tradingDateFilterLabel(context, _dateFilter),
              ),
              const _ReportConfirmationRow(label: 'Stage', value: 'Overall'),
              _ReportConfirmationRow(
                label: 'Report Language',
                value: Localizations.localeOf(context).languageCode == 'zh'
                    ? '简体中文'
                    : 'English',
              ),
              const _ReportConfirmationRow(
                label: 'Timezone',
                value: 'Asia/Kuala_Lumpur',
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(dialogContext.l10n.text('Cancel')),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            icon: const Icon(Icons.picture_as_pdf_outlined),
            label: Text(dialogContext.l10n.text('Generate Report')),
          ),
        ],
      ),
    );
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(context.l10n.text(message))));
  }

  List<int> _availableYears(
    List<TradingJournalEntry> journals,
    List<CapitalTransaction> capitals,
  ) {
    final years = <int>{
      for (final journal in journals) journal.closeDate.year,
      for (final capital in capitals) capital.depositDate.year,
      DateTime.now().year,
    }.toList()..sort((left, right) => right.compareTo(left));

    return years;
  }
}

class _ReportConfirmationRow extends StatelessWidget {
  const _ReportConfirmationRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 132,
            child: Text(
              context.l10n.text(label),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(child: Text(context.l10n.text(value))),
        ],
      ),
    );
  }
}

class _JournalActionsBar extends StatelessWidget {
  const _JournalActionsBar({
    required this.exporting,
    required this.importing,
    required this.mode,
    required this.onRecordTrade,
    required this.onDeposit,
    required this.onWithdraw,
    required this.onPdfReport,
    required this.onPerformanceReview,
    required this.onImport,
  });

  final bool exporting;
  final bool importing;
  final TradingDataDisplayMode mode;
  final VoidCallback onRecordTrade;
  final VoidCallback onDeposit;
  final VoidCallback onWithdraw;
  final VoidCallback? onPdfReport;
  final VoidCallback onPerformanceReview;
  final VoidCallback? onImport;

  @override
  Widget build(BuildContext context) {
    final manualMode = mode == TradingDataDisplayMode.manualOnly;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 2),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final availableWidth = constraints.maxWidth.isFinite
              ? constraints.maxWidth
              : MediaQuery.sizeOf(context).width - 32;
          final compact = availableWidth < 620;
          final narrow = availableWidth < 340;
          final compactButtonWidth = narrow
              ? availableWidth
              : ((availableWidth - 8) / 2)
                    .clamp(0.0, availableWidth)
                    .toDouble();

          return Wrap(
            spacing: 8,
            runSpacing: 8,
            alignment: WrapAlignment.center,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              _JournalActionButton(
                width: compact ? compactButtonWidth : 244,
                onPressed: manualMode ? onRecordTrade : null,
                icon: Icons.add,
                label: 'Record Trade',
              ),
              _JournalActionButton(
                width: compact ? compactButtonWidth : 188,
                onPressed: manualMode ? onDeposit : null,
                icon: Icons.add_circle_outline,
                label: 'Deposit',
                tonal: true,
              ),
              _JournalActionButton(
                width: compact ? compactButtonWidth : 212,
                onPressed: manualMode ? onWithdraw : null,
                icon: Icons.remove_circle_outline,
                label: 'Withdraw',
                tonal: true,
              ),
              _JournalActionButton(
                width: compact ? compactButtonWidth : 224,
                onPressed: exporting ? null : onPdfReport,
                icon: Icons.picture_as_pdf_outlined,
                label: 'PDF Report',
                loading: exporting,
              ),
              _JournalActionButton(
                width: compact ? availableWidth : 320,
                onPressed: onPerformanceReview,
                icon: Icons.fact_check_outlined,
                label: 'Performance Review',
                outlined: true,
              ),
              _JournalActionButton(
                width: compact ? availableWidth : 250,
                onPressed: onImport,
                icon: Icons.upload_file_outlined,
                label: 'Import MT4 / MT5',
                loading: importing,
                outlined: true,
              ),
            ],
          );
        },
      ),
    );
  }
}

class _TabBarHeaderDelegate extends SliverPersistentHeaderDelegate {
  const _TabBarHeaderDelegate(this.tabBar);

  final TabBar tabBar;

  @override
  double get minExtent => tabBar.preferredSize.height;

  @override
  double get maxExtent => tabBar.preferredSize.height;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Material(
      color: Theme.of(context).colorScheme.surface,
      child: tabBar,
    );
  }

  @override
  bool shouldRebuild(covariant _TabBarHeaderDelegate oldDelegate) {
    return oldDelegate.tabBar != tabBar;
  }
}

class _JournalActionButton extends StatelessWidget {
  const _JournalActionButton({
    required this.width,
    required this.onPressed,
    required this.icon,
    required this.label,
    this.tonal = false,
    this.outlined = false,
    this.loading = false,
  });

  final double width;
  final VoidCallback? onPressed;
  final IconData icon;
  final String label;
  final bool tonal;
  final bool outlined;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final child = _ButtonContent(icon: icon, label: label, loading: loading);
    final style = ButtonStyle(
      minimumSize: const WidgetStatePropertyAll(Size(0, 52)),
      padding: const WidgetStatePropertyAll(
        EdgeInsets.symmetric(horizontal: 16),
      ),
      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
    );

    return SizedBox(
      width: width,
      height: 52,
      child: outlined
          ? OutlinedButton(onPressed: onPressed, style: style, child: child)
          : tonal
          ? FilledButton.tonal(onPressed: onPressed, style: style, child: child)
          : FilledButton(onPressed: onPressed, style: style, child: child),
    );
  }
}

class _ButtonContent extends StatelessWidget {
  const _ButtonContent({
    required this.icon,
    required this.label,
    required this.loading,
  });

  final IconData icon;
  final String label;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return FittedBox(
      fit: BoxFit.scaleDown,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (loading)
            const SizedBox.square(
              dimension: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          else
            Icon(icon, size: 22),
          const SizedBox(width: 10),
          Text(
            context.l10n.text(label),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}

class _ReportToolbar extends StatelessWidget {
  const _ReportToolbar({
    required this.filter,
    required this.years,
    required this.onChanged,
  });

  final TradingDateFilter filter;
  final List<int> years;
  final ValueChanged<TradingDateFilter> onChanged;

  @override
  Widget build(BuildContext context) => TradingDateFilterBar(
    filter: filter,
    availableYears: years,
    onChanged: onChanged,
  );
}

class _DropdownSelectedText extends StatelessWidget {
  const _DropdownSelectedText(this.label);

  final String label;

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.centerLeft,
    child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
  );
}

class _JournalFilterBar extends StatelessWidget {
  const _JournalFilterBar({
    required this.controller,
    required this.pairingOptions,
    required this.pairing,
    required this.status,
    required this.result,
    required this.visibleCount,
    required this.onSearch,
    required this.onPairing,
    required this.onStatus,
    required this.onResult,
    required this.onClear,
  });

  final TextEditingController controller;
  final List<String> pairingOptions;
  final String? pairing;
  final String? status;
  final int? result;
  final int visibleCount;
  final ValueChanged<String> onSearch;
  final ValueChanged<String?> onPairing;
  final ValueChanged<String?> onStatus;
  final ValueChanged<int?> onResult;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
      child: Column(
        children: [
          TextField(
            controller: controller,
            onChanged: onSearch,
            decoration: InputDecoration(
              hintText: context.l10n.text(
                'Search pair, notes, psychology, or mistake tags',
              ),
              prefixIcon: const Icon(Icons.search),
              suffixIcon: controller.text.isEmpty
                  ? null
                  : IconButton(
                      onPressed: onClear,
                      icon: const Icon(Icons.close),
                    ),
            ),
          ),
          const SizedBox(height: 8),
          LayoutBuilder(
            builder: (context, constraints) {
              return Align(
                alignment: Alignment.centerLeft,
                child: SizedBox(
                  width: constraints.maxWidth < 420
                      ? constraints.maxWidth
                      : 360,
                  child: KeyedSubtree(
                    key: const ValueKey('journal-pairing-filter'),
                    child: DropdownButtonFormField<String>(
                      key: ValueKey(
                        'journal-pairing-filter-${pairing ?? 'all'}',
                      ),
                      initialValue: pairing,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: context.l10n.text('Financial Pairing'),
                        prefixIcon: const Icon(Icons.currency_exchange),
                      ),
                      items: [
                        DropdownMenuItem<String>(
                          value: null,
                          child: _DropdownSelectedText(
                            context.l10n.text('All financial pairings'),
                          ),
                        ),
                        for (final symbol in pairingOptions)
                          DropdownMenuItem<String>(
                            value: symbol,
                            child: _DropdownSelectedText(symbol),
                          ),
                      ],
                      onChanged: onPairing,
                    ),
                  ),
                ),
              );
            },
          ),
          const SizedBox(height: 8),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                Chip(
                  label: Text(
                    context.l10n.text('{count} shown', {'count': visibleCount}),
                  ),
                ),
                const SizedBox(width: 8),
                for (final item in const <String?, String>{
                  null: 'All statuses',
                  'draft': 'Draft',
                  'open': 'Open',
                  'closed': 'Closed',
                }.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 6),
                    child: ChoiceChip(
                      label: Text(context.l10n.text(item.value)),
                      selected: status == item.key,
                      onSelected: (_) => onStatus(item.key),
                    ),
                  ),
                const SizedBox(width: 4),
                for (final item in const <int?, String>{
                  null: 'All results',
                  1: 'Win',
                  2: 'Loss',
                  3: 'Break even',
                }.entries)
                  Padding(
                    padding: const EdgeInsets.only(right: 6),
                    child: ChoiceChip(
                      label: Text(context.l10n.text(item.value)),
                      selected: result == item.key,
                      onSelected: (_) => onResult(item.key),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _JournalCapitalDialog extends StatefulWidget {
  const _JournalCapitalDialog({
    required this.user,
    required this.repository,
    required this.journals,
    required this.capitals,
    required this.initialType,
  });

  final AppUser user;
  final TradingRepository repository;
  final List<TradingJournalEntry> journals;
  final List<CapitalTransaction> capitals;
  final int initialType;

  @override
  State<_JournalCapitalDialog> createState() => _JournalCapitalDialogState();
}

class _JournalCapitalDialogState extends State<_JournalCapitalDialog> {
  final _formKey = GlobalKey<FormState>();
  final _reportService = TradingReportService();
  final _amountController = TextEditingController();
  final _notesController = TextEditingController();
  late int _type;
  var _date = DateTime.now();
  var _saving = false;

  @override
  void initState() {
    super.initState();
    _type = widget.initialType == 2 ? 2 : 1;
  }

  @override
  void dispose() {
    _amountController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text(_type == 1 ? 'Deposit' : 'Withdraw')),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SegmentedButton<int>(
                segments: [
                  ButtonSegment(
                    value: 1,
                    icon: const Icon(Icons.add_circle_outline),
                    label: Text(context.l10n.text('Deposit')),
                  ),
                  ButtonSegment(
                    value: 2,
                    icon: const Icon(Icons.remove_circle_outline),
                    label: Text(context.l10n.text('Withdraw')),
                  ),
                ],
                selected: {_type},
                onSelectionChanged: (selection) {
                  setState(() => _type = selection.first);
                },
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _amountController,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(
                  labelText: context.l10n.text('Amount'),
                  prefixIcon: const Icon(Icons.payments_outlined),
                ),
                validator: (value) {
                  final amount = double.tryParse(value ?? '');
                  return amount != null && amount > 0
                      ? null
                      : context.l10n.text('Enter amount');
                },
              ),
              const SizedBox(height: 14),
              InkWell(
                borderRadius: BorderRadius.circular(8),
                onTap: _pickDate,
                child: InputDecorator(
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Date'),
                    prefixIcon: const Icon(Icons.event_outlined),
                  ),
                  child: Text(formatDate(_date)),
                ),
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _notesController,
                minLines: 2,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Notes'),
                  alignLabelWithHint: true,
                  prefixIcon: const Icon(Icons.notes_outlined),
                ),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton.icon(
          onPressed: _saving ? null : _save,
          icon: _saving
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.save_outlined),
          label: Text(context.l10n.text('Save')),
        ),
      ],
    );
  }

  Future<void> _pickDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2020),
      lastDate: DateTime(2035),
    );
    if (selected != null) {
      setState(() => _date = selected);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() => _saving = true);
    try {
      final amount = double.parse(_amountController.text).abs();
      if (_type == 2) {
        final eligibility = _reportService.withdrawalEligibility(
          journals: widget.journals,
          capitals: widget.capitals,
          requestedAmount: amount,
        );
        if (!eligibility.eligible) {
          _showMessage(eligibility.message);
          return;
        }
      }

      await widget.repository.upsertCapitalTransaction(
        CapitalTransaction(
          id: '',
          userId: widget.user.uid,
          depositDate: _date,
          amount: _type == 2 ? -amount : amount,
          type: _type,
          notes: _notesController.text.trim(),
        ),
      );

      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } catch (error) {
      if (mounted) {
        _showMessage(error.toString());
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(context.l10n.text(message))));
  }
}

class _TradesTab extends StatelessWidget {
  const _TradesTab({
    required this.journals,
    required this.pairs,
    required this.mode,
    required this.onAdd,
    required this.onEdit,
    required this.onDelete,
    required this.onOpen,
  });

  final List<TradingJournalEntry> journals;
  final List<TradingPair> pairs;
  final TradingDataDisplayMode mode;
  final VoidCallback onAdd;
  final ValueChanged<TradingJournalEntry> onEdit;
  final ValueChanged<TradingJournalEntry> onDelete;
  final ValueChanged<TradingJournalEntry> onOpen;

  @override
  Widget build(BuildContext context) {
    final manualMode = mode == TradingDataDisplayMode.manualOnly;
    return Stack(
      children: [
        if (journals.isEmpty)
          EmptyState(
            icon: Icons.receipt_long_outlined,
            title: context.l10n.text(tradingDataEmptyTitle(mode)),
            action: manualMode
                ? FilledButton.icon(
                    onPressed: onAdd,
                    icon: const Icon(Icons.add),
                    label: Text(context.l10n.text('Add Trade')),
                  )
                : null,
          )
        else
          ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
            itemBuilder: (context, index) {
              final journal = journals[index];
              return _JournalCard(
                journal: journal,
                onEdit: manualMode ? () => onEdit(journal) : null,
                onDelete: manualMode ? () => onDelete(journal) : null,
                onOpen: () => onOpen(journal),
              );
            },
            separatorBuilder: (context, index) => const SizedBox(height: 10),
            itemCount: journals.length,
          ),
        if (manualMode)
          Positioned(
            right: 16,
            bottom: 16,
            child: FloatingActionButton.extended(
              onPressed: onAdd,
              icon: const Icon(Icons.add),
              label: Text(context.l10n.text('Trade')),
            ),
          ),
      ],
    );
  }
}

// Retained for compatibility with older deep-link snapshots while the new
// sectioned report workspace is rolled out.
// ignore: unused_element
class _ReportTab extends StatelessWidget {
  const _ReportTab({required this.report});

  final TradingReport report;

  @override
  Widget build(BuildContext context) {
    final summary = report.summary;

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      children: [
        _ReportHeader(report: report),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final count = constraints.maxWidth >= 720 ? 4 : 2;
            return GridView.count(
              crossAxisCount: count,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              childAspectRatio: count == 4 ? 1.55 : 1.08,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              children: [
                MetricTile(
                  label: context.l10n.text('Trades'),
                  value: summary.totalTrades.toString(),
                  icon: Icons.list_alt_outlined,
                  detail:
                      '${summary.winningTrades}W ${summary.losingTrades}L ${summary.breakevenTrades}BE',
                ),
                MetricTile(
                  label: context.l10n.text('Win Rate'),
                  value: formatPercent(summary.winRate),
                  icon: Icons.percent,
                  tone: const Color(0xFF1B5E8C),
                ),
                MetricTile(
                  label: context.l10n.text('Profit Factor'),
                  value: summary.profitFactorLabel,
                  icon: Icons.balance_outlined,
                  tone: const Color(0xFFD99A17),
                ),
                MetricTile(
                  label: context.l10n.text('Expectancy'),
                  value: formatMoney(summary.expectancy),
                  icon: Icons.insights_outlined,
                  tone: const Color(0xFFD99A17),
                ),
                MetricTile(
                  label: context.l10n.text('Drawdown'),
                  value: formatPercent(summary.maxDrawdownPercent),
                  icon: Icons.waterfall_chart,
                  detail: formatMoney(summary.maxDrawdownAmount),
                  tone: const Color(0xFF7C4D1D),
                ),
                MetricTile(
                  label: context.l10n.text('Daily Loss'),
                  value: formatPercent(summary.maxDailyLossPercent),
                  icon: Icons.warning_amber_outlined,
                  detail: formatMoney(summary.maxDailyLossAmount),
                  tone: _riskTone(summary.maxDailyLossPercent, 5),
                ),
                MetricTile(
                  label: context.l10n.text('Best Day'),
                  value: formatMoney(summary.bestDay),
                  icon: Icons.calendar_month_outlined,
                  detail: summary.bestDayRule.bestWinningDayDate == null
                      ? context.l10n.text('N/A')
                      : formatDate(summary.bestDayRule.bestWinningDayDate!),
                ),
                MetricTile(
                  label: context.l10n.text('Holding'),
                  value: summary.averageHoldingLabel,
                  icon: Icons.timer_outlined,
                  detail: context.l10n.text('Median {value}', {
                    'value': summary.medianHoldingLabel,
                  }),
                ),
              ],
            );
          },
        ),
        const SizedBox(height: 12),
        _RuleMonitorCard(rules: report.ruleMonitor),
        const SizedBox(height: 12),
        _DailyStatsCard(stats: report.dailyStats),
        const SizedBox(height: 12),
        _BreakdownCard(
          title: context.l10n.text('Monthly Stats'),
          stats: report.monthlyStats,
        ),
        const SizedBox(height: 12),
        _BreakdownCard(
          title: context.l10n.text('Pair Stats'),
          stats: report.pairStats,
        ),
        const SizedBox(height: 12),
        _BreakdownCard(
          title: context.l10n.text('Direction'),
          stats: report.directionStats,
        ),
        const SizedBox(height: 12),
        _BreakdownCard(
          title: context.l10n.text('Weekday'),
          stats: report.weekdayStats,
        ),
        const SizedBox(height: 12),
        _BreakdownCard(
          title: context.l10n.text('Session'),
          stats: report.sessionStats,
        ),
      ],
    );
  }

  Color _riskTone(double value, double limit) {
    return value > limit ? const Color(0xFFC23B36) : const Color(0xFF17855B);
  }
}

class _PerformanceReviewSheet extends StatelessWidget {
  const _PerformanceReviewSheet({required this.report});

  final TradingReport report;

  @override
  Widget build(BuildContext context) {
    final summary = report.summary;
    final status = _performanceStatus(report);

    return SafeArea(
      child: DraggableScrollableSheet(
        expand: false,
        initialChildSize: .82,
        minChildSize: .45,
        maxChildSize: .94,
        builder: (context, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 24),
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: status.color.withValues(alpha: .14),
                  child: Icon(status.icon, color: status.color),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.text('Performance Review'),
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        context.l10n.text(status.label),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: status.color,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _ReviewSection(
              title: 'Rating Breakdown',
              icon: Icons.bar_chart_outlined,
              metrics: [
                _ReviewMetric('Win Rate', formatPercent(summary.winRate)),
                _ReviewMetric('Profit Factor', summary.profitFactorLabel),
                _ReviewMetric('Growth', formatPercent(summary.growthPercent)),
                _ReviewMetric(
                  'Drawdown',
                  formatPercent(summary.maxDrawdownPercent),
                ),
                _ReviewMetric('Expectancy', formatMoney(summary.expectancy)),
                _ReviewMetric('Payoff Ratio', summary.payoffRatioLabel),
              ],
            ),
            _ReviewSection(
              title: 'Score Calculation',
              icon: Icons.calculate_outlined,
              metrics: [
                for (final rule in report.ruleMonitor)
                  _ReviewMetric(
                    rule.title,
                    '${rule.status} (${rule.progress.toStringAsFixed(0)}%)',
                    rule.caption,
                  ),
              ],
            ),
            _ReviewSection(
              title: 'Position Score',
              icon: Icons.scatter_plot_outlined,
              metrics: [
                _ReviewMetric(
                  'Total Lots',
                  summary.totalLots.toStringAsFixed(2),
                ),
                _ReviewMetric(
                  'Average Lot',
                  summary.averageLot.toStringAsFixed(2),
                ),
                _ReviewMetric('Average Holding', summary.averageHoldingLabel),
                _ReviewMetric('Median Holding', summary.medianHoldingLabel),
                _ReviewMetric('Best Weekday', summary.bestWeekday),
                _ReviewMetric('Best Session', _bestSessionLabel(report)),
              ],
            ),
            _ReviewSection(
              title: 'Behavior Detection',
              icon: Icons.psychology_outlined,
              metrics: [
                _ReviewMetric(
                  'Best Day Concentration',
                  formatPercent(summary.consistencyPercent),
                  summary.bestDayRule.status,
                ),
                _ReviewMetric(
                  'Max Daily Loss',
                  formatPercent(summary.maxDailyLossPercent),
                  formatMoney(summary.maxDailyLossAmount),
                ),
                _ReviewMetric(
                  'Longest Loss Streak',
                  summary.longestLossStreak.toString(),
                ),
                _ReviewMetric(
                  'Longest Win Streak',
                  summary.longestWinStreak.toString(),
                ),
                _ReviewMetric(
                  'Losing Days',
                  '${summary.losingDays}/${summary.tradingDays}',
                ),
              ],
            ),
            _ReviewSection(
              title: 'Prop Firm Evaluation',
              icon: Icons.fact_check_outlined,
              metrics: [
                _ReviewMetric(
                  '2% Gross Profit',
                  summary.grossProfitRule.status,
                  '${formatMoney(summary.grossProfitRule.grossProfit)} / ${formatMoney(summary.grossProfitRule.requiredAmount)}',
                ),
                _ReviewMetric(
                  '40% Best Day Rule',
                  summary.bestDayRule.status,
                  'Need ${formatMoney(summary.bestDayRule.additionalProfitNeeded)} more if pending',
                ),
                _ReviewMetric(
                  'Daily Loss Limit',
                  summary.maxDailyLossPercent > 5 ? 'Breached' : 'Within Limit',
                  '5% limit',
                ),
                _ReviewMetric(
                  'Total Drawdown Limit',
                  summary.maxDrawdownPercent > 10 ? 'Breached' : 'Within Limit',
                  '10% limit',
                ),
                _ReviewMetric('Profitable Days', '${summary.profitableDays}/3'),
              ],
            ),
          ],
        ),
      ),
    );
  }

  ({String label, Color color, IconData icon}) _performanceStatus(
    TradingReport report,
  ) {
    final summary = report.summary;
    if (summary.totalTrades == 0) {
      return (
        label: 'No trading activity found',
        color: const Color(0xFF6B7280),
        icon: Icons.hourglass_empty,
      );
    }

    if (report.ruleMonitor.any((rule) => rule.breached)) {
      return (
        label: 'Risk Breach',
        color: const Color(0xFFC23B36),
        icon: Icons.warning_amber_outlined,
      );
    }

    final passedRules = report.ruleMonitor
        .where((rule) => rule.status == 'Passed')
        .length;
    if (passedRules >= 3 &&
        summary.bestDayRule.passed &&
        summary.grossProfitRule.passed) {
      return (
        label: 'Challenge Ready',
        color: const Color(0xFF17855B),
        icon: Icons.verified_outlined,
      );
    }

    return (
      label: 'Monitoring',
      color: const Color(0xFF1B5E8C),
      icon: Icons.track_changes_outlined,
    );
  }

  String _bestSessionLabel(TradingReport report) {
    final sessions =
        report.sessionStats.where((stat) => stat.trades > 0).toList()
          ..sort((left, right) => right.profitLoss.compareTo(left.profitLoss));

    if (sessions.isEmpty) {
      return 'N/A';
    }

    return '${sessions.first.label} (${formatMoney(sessions.first.profitLoss)})';
  }
}

class _ReviewSection extends StatelessWidget {
  const _ReviewSection({
    required this.title,
    required this.icon,
    required this.metrics,
  });

  final String title;
  final IconData icon;
  final List<_ReviewMetric> metrics;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionTitle(title: title, icon: icon),
            const SizedBox(height: 10),
            for (final metric in metrics)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 6),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            context.l10n.text(metric.label),
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                          if (metric.detail != null)
                            Text(
                              context.l10n.text(metric.detail!),
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: Theme.of(
                                      context,
                                    ).colorScheme.onSurfaceVariant,
                                  ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Text(
                      metric.value,
                      textAlign: TextAlign.end,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ReviewMetric {
  const _ReviewMetric(this.label, this.value, [this.detail]);

  final String label;
  final String value;
  final String? detail;
}

class _ReportHeader extends StatelessWidget {
  const _ReportHeader({required this.report});

  final TradingReport report;

  @override
  Widget build(BuildContext context) {
    final summary = report.summary;
    final profitTone = summary.netProfitLoss >= 0
        ? const Color(0xFF17855B)
        : const Color(0xFFC23B36);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.primaryContainer,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.text(_periodLabel(report.filter)),
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: Theme.of(context).colorScheme.onPrimaryContainer,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            formatMoney(summary.currentBalance),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.displaySmall?.copyWith(
              color: Theme.of(context).colorScheme.onPrimaryContainer,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Pill(
                icon: Icons.south_west,
                label: formatMoney(report.capital.totalDeposits),
              ),
              _Pill(
                icon: Icons.north_east,
                label: formatMoney(report.capital.totalWithdrawals),
              ),
              _Pill(
                icon: summary.netProfitLoss >= 0
                    ? Icons.trending_up
                    : Icons.trending_down,
                label: formatMoney(summary.netProfitLoss),
                color: profitTone,
              ),
              _Pill(
                icon: summary.bestDayRule.passed
                    ? Icons.verified_outlined
                    : Icons.balance_outlined,
                label: context.l10n.text(summary.bestDayRule.status),
              ),
              _Pill(
                icon: summary.grossProfitRule.passed
                    ? Icons.verified_outlined
                    : Icons.track_changes_outlined,
                label: context.l10n.text(summary.grossProfitRule.status),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _periodLabel(TradingReportFilter filter) {
    final active = filter.effectiveDateFilter;
    if (active.isActive) {
      return switch (active.type) {
        TradingDateFilterType.specificDate => DateFormat.yMMMd().format(
          active.startDate!,
        ),
        TradingDateFilterType.dateRange =>
          '${DateFormat.yMMMd().format(active.startDate!)} - '
              '${DateFormat.yMMMd().format(active.endDate!)}',
        TradingDateFilterType.month => DateFormat.yMMMM().format(
          DateTime(active.year!, active.month!),
        ),
        TradingDateFilterType.year => 'Year ${active.year}',
        TradingDateFilterType.allTime => 'All trading history',
      };
    }
    if (filter.month == null && filter.year == null) {
      return 'All trading history';
    }

    if (filter.month != null && filter.year != null) {
      return DateFormat.yMMMM().format(DateTime(filter.year!, filter.month!));
    }

    if (filter.year != null) {
      return 'Year ${filter.year}';
    }

    return 'Month ${filter.month.toString().padLeft(2, '0')}';
  }
}

class _RuleMonitorCard extends StatelessWidget {
  const _RuleMonitorCard({required this.rules});

  final List<TradingRuleMonitorItem> rules;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionTitle(
              title: context.l10n.text('Rule Monitoring'),
              icon: Icons.rule_outlined,
            ),
            const SizedBox(height: 12),
            for (final rule in rules)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _RuleRow(rule: rule),
              ),
          ],
        ),
      ),
    );
  }
}

class _RuleRow extends StatelessWidget {
  const _RuleRow({required this.rule});

  final TradingRuleMonitorItem rule;

  @override
  Widget build(BuildContext context) {
    final tone = _tone(rule.tone);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                context.l10n.text(rule.title),
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
            _StatusChip(label: context.l10n.text(rule.status), color: tone),
          ],
        ),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LinearProgressIndicator(
            value: (rule.progress / 100).clamp(0.0, 1.0),
            minHeight: 8,
            color: tone,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          '${_localizedRuleValue(context, rule)}  |  ${_localizedRuleCaption(context, rule)}',
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }

  String _localizedRuleValue(
    BuildContext context,
    TradingRuleMonitorItem rule,
  ) {
    if (rule.value.endsWith(' used')) {
      return context.l10n.text('{value} used', {
        'value': rule.value.replaceFirst(RegExp(r'\s+used$'), ''),
      });
    }
    return context.l10n.text(rule.value);
  }

  String _localizedRuleCaption(
    BuildContext context,
    TradingRuleMonitorItem rule,
  ) {
    final caption = rule.caption;
    if (caption.startsWith('Minimum ') &&
        caption.endsWith(' gross profit on account balance')) {
      return context.l10n
          .text('Minimum {percent} gross profit on account balance', {
            'percent': caption
                .replaceFirst('Minimum ', '')
                .replaceFirst(' gross profit on account balance', ''),
          });
    }
    if (caption.startsWith('Best day max ')) {
      final match = RegExp(
        r'^Best day max (.+); need (.+) more if pending$',
      ).firstMatch(caption);
      if (match != null) {
        return context.l10n.text(
          'Best day max {percent}; need {amount} more if pending',
          {'percent': match.group(1), 'amount': match.group(2)},
        );
      }
    }
    return context.l10n.text(caption);
  }

  Color _tone(String tone) {
    return switch (tone) {
      'success' => const Color(0xFF17855B),
      'danger' => const Color(0xFFC23B36),
      'warning' => const Color(0xFFD99A17),
      _ => const Color(0xFF1B5E8C),
    };
  }
}

class _DailyStatsCard extends StatelessWidget {
  const _DailyStatsCard({required this.stats});

  final List<TradingDailyStat> stats;

  @override
  Widget build(BuildContext context) {
    final visibleStats = stats.length > 14
        ? stats.sublist(stats.length - 14)
        : stats;

    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionTitle(title: 'Daily P/L', icon: Icons.bar_chart_outlined),
            const SizedBox(height: 10),
            if (visibleStats.isEmpty)
              SizedBox(
                height: 160,
                child: EmptyState(
                  icon: Icons.bar_chart_outlined,
                  title: context.l10n.text('No daily report data'),
                ),
              )
            else
              for (final stat in visibleStats)
                _CompactRow(
                  title: formatDate(stat.date),
                  subtitle: context.l10n.text('{count} trade(s)', {
                    'count': stat.trades,
                  }),
                  value: formatMoney(stat.profitLoss),
                  detail: context.l10n.text('Cum {value}', {
                    'value': formatMoney(stat.cumulativeProfitLoss),
                  }),
                  tone: stat.profitLoss >= 0
                      ? const Color(0xFF17855B)
                      : const Color(0xFFC23B36),
                ),
          ],
        ),
      ),
    );
  }
}

class _BreakdownCard extends StatelessWidget {
  const _BreakdownCard({required this.title, required this.stats});

  final String title;
  final List<TradingBreakdownStat> stats;

  @override
  Widget build(BuildContext context) {
    final visibleStats = stats.take(12).toList();

    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _SectionTitle(title: title, icon: Icons.table_chart_outlined),
            const SizedBox(height: 10),
            if (visibleStats.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 28),
                child: EmptyState(
                  icon: Icons.table_chart_outlined,
                  title: context.l10n.text('No breakdown data'),
                ),
              )
            else
              for (final stat in visibleStats)
                _CompactRow(
                  title: stat.shortLabel ?? stat.label,
                  subtitle:
                      '${context.l10n.text('{count} trade(s)', {'count': stat.trades})}  |  ${stat.wins}W ${stat.losses}L  |  ${formatPercent(stat.winRate)}',
                  value: formatMoney(stat.profitLoss),
                  detail: '${stat.pips.toStringAsFixed(1)} pips',
                  tone: stat.profitLoss >= 0
                      ? const Color(0xFF17855B)
                      : const Color(0xFFC23B36),
                ),
          ],
        ),
      ),
    );
  }
}

class _CompactRow extends StatelessWidget {
  const _CompactRow({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.detail,
    required this.tone,
  });

  final String title;
  final String subtitle;
  final String value;
  final String detail;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(
                    context,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                value,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: tone,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(detail, style: Theme.of(context).textTheme.labelSmall),
            ],
          ),
        ],
      ),
    );
  }
}

class _JournalCard extends StatelessWidget {
  const _JournalCard({
    required this.journal,
    required this.onEdit,
    required this.onDelete,
    required this.onOpen,
  });

  final TradingJournalEntry journal;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final positive = journal.profitLoss >= 0;
    final tone = positive ? const Color(0xFF17855B) : const Color(0xFFC23B36);

    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      journal.pair,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  _StatusChip(
                    label: context.l10n.text(journal.resultLabel),
                    color: tone,
                  ),
                  PopupMenuButton<String>(
                    tooltip: context.l10n.text('Trade actions'),
                    onSelected: (value) {
                      if (value == 'view') {
                        onOpen();
                      } else if (value == 'edit') {
                        onEdit?.call();
                      } else if (value == 'delete') {
                        onDelete?.call();
                      }
                    },
                    itemBuilder: (context) => [
                      PopupMenuItem(
                        value: 'view',
                        child: ListTile(
                          leading: const Icon(Icons.visibility_outlined),
                          title: Text(context.l10n.text('View details')),
                        ),
                      ),
                      if (onEdit != null)
                        PopupMenuItem(
                          value: 'edit',
                          child: ListTile(
                            leading: const Icon(Icons.edit_outlined),
                            title: Text(context.l10n.text('Edit')),
                          ),
                        ),
                      if (onDelete != null)
                        PopupMenuItem(
                          value: 'delete',
                          child: ListTile(
                            leading: const Icon(Icons.delete_outline),
                            title: Text(context.l10n.text('Delete')),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                '${context.l10n.text(journal.directionLabel)}  |  ${formatDateTime(journal.closeDate)}',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                '${context.l10n.text('Entry')} ${journal.entryPrice}  |  ${context.l10n.text('Exit')} ${journal.exitPrice}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _JournalFigure(
                      label: context.l10n.text('Pips'),
                      value: journal.pips.toStringAsFixed(1),
                    ),
                  ),
                  Expanded(
                    child: _JournalFigure(
                      label: context.l10n.text('Lot'),
                      value: journal.lotSize.toStringAsFixed(2),
                    ),
                  ),
                  Expanded(
                    child: _JournalFigure(
                      label: context.l10n.text('P/L'),
                      value: formatMoney(journal.profitLoss),
                      color: tone,
                      alignEnd: true,
                    ),
                  ),
                ],
              ),
              if (journal.tradingPlanId?.isNotEmpty == true ||
                  journal.tradingPlanName?.isNotEmpty == true) ...[
                const SizedBox(height: 10),
                Row(
                  children: [
                    Icon(
                      journal.followedTradingPlan
                          ? Icons.check_circle_outline
                          : Icons.radio_button_unchecked,
                      size: 16,
                      color: journal.followedTradingPlan
                          ? const Color(0xFF17855B)
                          : Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        context.l10n.text(
                          journal.followedTradingPlan
                              ? 'Followed Trading Plan'
                              : 'Did Not Follow Trading Plan',
                        ),
                      ),
                    ),
                  ],
                ),
              ],
              if (journal.attachments.isNotEmpty) ...[
                const SizedBox(height: 10),
                Row(
                  children: [
                    const Icon(Icons.attach_file, size: 16),
                    const SizedBox(width: 4),
                    Text(
                      context.l10n.text('{count} attachment(s)', {
                        'count': journal.attachments.length,
                      }),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ImportAccountDialog extends StatefulWidget {
  const _ImportAccountDialog({required this.accounts});

  final List<TradingAccount> accounts;

  @override
  State<_ImportAccountDialog> createState() => _ImportAccountDialogState();
}

class _ImportAccountDialogState extends State<_ImportAccountDialog> {
  late String _selected;

  @override
  void initState() {
    super.initState();
    _selected = widget.accounts.first.id;
    for (final account in widget.accounts) {
      if (account.isPrimary) _selected = account.id;
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text('Assign imported trades')),
      content: DropdownButtonFormField<String>(
        initialValue: _selected,
        isExpanded: true,
        decoration: InputDecoration(
          labelText: context.l10n.text('Trading account'),
        ),
        items: [
          for (final account in widget.accounts)
            DropdownMenuItem(
              value: account.id,
              child: Text('${account.displayName} · ${account.broker}'),
            ),
        ],
        onChanged: (value) => setState(() => _selected = value ?? _selected),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(context.l10n.text('Cancel')),
        ),
        TextButton(
          onPressed: () => Navigator.pop(context, ''),
          child: Text(context.l10n.text('Leave unassigned')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, _selected),
          child: Text(context.l10n.text('Continue')),
        ),
      ],
    );
  }
}

class _TradeDetailsSheet extends StatefulWidget {
  const _TradeDetailsSheet({required this.journal, required this.repository});

  final TradingJournalEntry journal;
  final TradingRepository repository;

  @override
  State<_TradeDetailsSheet> createState() => _TradeDetailsSheetState();
}

class _TradeDetailsSheetState extends State<_TradeDetailsSheet> {
  late List<JournalAttachment> _attachments;
  var _uploading = false;

  @override
  void initState() {
    super.initState();
    _attachments = [...widget.journal.attachments];
  }

  @override
  Widget build(BuildContext context) {
    final journal = widget.journal;
    final tone = journal.profitLoss >= 0
        ? const Color(0xFF17855B)
        : const Color(0xFFC23B36);
    return SafeArea(
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .86,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 32),
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${journal.pair} ${context.l10n.text(journal.directionLabel)}',
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(formatDateTime(journal.closeDate)),
                    ],
                  ),
                ),
                _StatusChip(
                  label: context.l10n.text(journal.resultLabel),
                  color: tone,
                ),
              ],
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    _detailRow(
                      context.l10n.text('Status'),
                      context.l10n.text(journal.status.toUpperCase()),
                    ),
                    _detailRow(
                      context.l10n.text('Entry'),
                      '${journal.entryPrice}',
                    ),
                    _detailRow(
                      context.l10n.text('Exit'),
                      '${journal.exitPrice}',
                    ),
                    _detailRow(
                      context.l10n.text('Lot size'),
                      journal.lotSize.toStringAsFixed(2),
                    ),
                    _detailRow(
                      context.l10n.text('Pips'),
                      journal.pips.toStringAsFixed(1),
                    ),
                    _detailRow(
                      context.l10n.text('Profit / Loss'),
                      formatMoney(journal.profitLoss),
                      color: tone,
                    ),
                    _detailRow(
                      context.l10n.text('Opened'),
                      formatDateTime(journal.openDate),
                    ),
                    _detailRow(
                      context.l10n.text('Closed'),
                      formatDateTime(journal.closeDate),
                    ),
                    if (journal.tradingPlanId?.isNotEmpty == true ||
                        journal.tradingPlanName?.isNotEmpty == true)
                      _detailRow(
                        context.l10n.text('Trading Plan'),
                        context.l10n.text(
                          journal.followedTradingPlan
                              ? 'Followed Trading Plan'
                              : 'Did Not Follow Trading Plan',
                        ),
                      ),
                    if (journal.tradingPlanName?.isNotEmpty == true)
                      _detailRow(
                        context.l10n.text('Select Trading Plan'),
                        journal.tradingPlanName!,
                      ),
                  ],
                ),
              ),
            ),
            if (journal.notes.isNotEmpty ||
                journal.psychologyNotes.isNotEmpty) ...[
              const SizedBox(height: 12),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.text('Review'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      if (journal.notes.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(journal.notes),
                      ],
                      if (journal.psychologyNotes.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        Text(journal.psychologyNotes),
                      ],
                      if (journal.mistakeTags.isNotEmpty) ...[
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 6,
                          children: [
                            for (final tag in journal.mistakeTags)
                              Chip(label: Text(tag)),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
            const SizedBox(height: 12),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            context.l10n.text('Attachments'),
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                        ),
                        FilledButton.tonalIcon(
                          onPressed:
                              _uploading || journal.id.startsWith('pending:')
                              ? null
                              : _upload,
                          icon: _uploading
                              ? const SizedBox.square(
                                  dimension: 16,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.attach_file, size: 18),
                          label: Text(context.l10n.text('Add')),
                        ),
                      ],
                    ),
                    if (_attachments.isEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: Text(
                          context.l10n.text(
                            'No screenshots or documents attached.',
                          ),
                        ),
                      )
                    else
                      for (final attachment in _attachments)
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: Icon(
                            attachment.isImage
                                ? Icons.image_outlined
                                : Icons.picture_as_pdf_outlined,
                          ),
                          title: Text(
                            attachment.originalName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          subtitle: Text(_formatBytes(attachment.sizeBytes)),
                          onTap: attachment.url.isEmpty
                              ? null
                              : () => _open(attachment),
                          trailing: IconButton(
                            tooltip: context.l10n.text('Delete attachment'),
                            onPressed: () => _delete(attachment),
                            icon: const Icon(Icons.delete_outline),
                          ),
                        ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _detailRow(String label, String value, {Color? color}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        Expanded(child: Text(label)),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.end,
            style: TextStyle(fontWeight: FontWeight.w800, color: color),
          ),
        ),
      ],
    ),
  );

  Future<void> _upload() async {
    final selected = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
      withData: true,
    );
    if (selected == null) return;
    final file = selected.files.single;
    setState(() => _uploading = true);
    try {
      final attachment = await widget.repository.uploadJournalAttachment(
        journalId: widget.journal.id,
        file: ApiUploadFile(
          field: 'attachment',
          filename: file.name,
          path: file.path,
          bytes: file.bytes,
        ),
      );
      if (mounted) setState(() => _attachments.add(attachment));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _open(JournalAttachment attachment) async {
    final uri = Uri.tryParse(widget.repository.resolveUrl(attachment.url));
    if (uri == null ||
        !await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.l10n.text('Unable to open this attachment.')),
          ),
        );
      }
    }
  }

  Future<void> _delete(JournalAttachment attachment) async {
    try {
      await widget.repository.deleteJournalAttachment(
        journalId: widget.journal.id,
        attachmentId: attachment.id,
      );
      if (mounted) {
        setState(
          () => _attachments.removeWhere((item) => item.id == attachment.id),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    }
  }

  String _formatBytes(int bytes) {
    if (bytes < 1024) return '$bytes B';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }
}

class _Mt5SyncedTradesTab extends StatefulWidget {
  const _Mt5SyncedTradesTab({
    required this.user,
    required this.repository,
    required this.storage,
    required this.pairingFilter,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;
  final String? pairingFilter;

  @override
  State<_Mt5SyncedTradesTab> createState() => _Mt5SyncedTradesTabState();
}

class _Mt5SyncedTradesTabState extends State<_Mt5SyncedTradesTab> {
  final _symbolController = TextEditingController();
  var _recordFilter = 'all';
  var _profitFilter = 'all';
  String? _selectedAccountKey;
  DateTime? _fromDate;
  DateTime? _toDate;
  final _olderHistory = <Mt5TradeRecord>[];
  DateTime? _oldestLoadedCloseTime;
  List<Mt5Account> _accounts = const [];
  List<Mt5TradeRecord> _records = const [];
  Mt5SyncState? _syncState;
  Timer? _activityTimer;
  DateTime? _lastLoadedTradeActivityAt;
  var _lastLoadedTradeActivityId = '';
  var _lastLoadedTradeChangeVersion = 0;
  var _initialLoading = false;
  var _manualRefreshing = false;
  var _loadingMoreHistory = false;
  var _checkingActivity = false;
  String? _accountLoadError;
  String? _recordsLoadError;
  late bool _enabled;

  @override
  void initState() {
    super.initState();
    final saved = widget.storage.readMap(widget.user.uid, 'mt5_sync_settings');
    _enabled =
        Mt5SyncConfig.mt5SyncEnabled &&
        (saved?['enabled'] is bool
            ? saved!['enabled'] as bool
            : Mt5SyncConfig.defaultEnabled);
    if (_enabled && Mt5SyncConfig.mt5SyncEnabled) {
      scheduleMicrotask(_loadInitial);
    }
  }

  @override
  void dispose() {
    _activityTimer?.cancel();
    _symbolController.dispose();
    super.dispose();
  }

  Future<void> _setEnabled(bool value) async {
    if (!Mt5SyncConfig.mt5SyncEnabled) return;
    if (!value) _activityTimer?.cancel();
    setState(() => _enabled = value);
    await _writeMt5SyncSettings(enabled: value);
    if (value) {
      await _loadInitial();
    }
  }

  Future<void> _writeMt5SyncSettings({
    bool? enabled,
    String? selectedAccountKey,
    bool clearSelectedAccount = false,
  }) async {
    final current =
        widget.storage.readMap(widget.user.uid, 'mt5_sync_settings') ??
        const <String, dynamic>{};
    final next = <String, Object?>{...current, 'enabled': enabled ?? _enabled};
    if (clearSelectedAccount) {
      next.remove('selected_account_key');
      next.remove('selectedAccountKey');
    } else {
      final accountKey = (selectedAccountKey ?? _selectedAccountKey)?.trim();
      if (accountKey != null && accountKey.isNotEmpty) {
        next['selected_account_key'] = accountKey;
        next['selectedAccountKey'] = accountKey;
      }
    }
    await widget.storage.writeMap(widget.user.uid, 'mt5_sync_settings', next);
  }

  Future<void> _openAccountManagement() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => _Mt5AccountManagementScreen(
          user: widget.user,
          repository: widget.repository,
        ),
      ),
    );
    if (!mounted) return;
    _resetHistory();
    await _loadInitial();
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        _Mt5FeatureCard(
          enabled: _enabled,
          globallyEnabled: Mt5SyncConfig.mt5SyncEnabled,
          onChanged: _setEnabled,
          onLinkAccount: _openAccountManagement,
        ),
        const SizedBox(height: 12),
        if (!Mt5SyncConfig.mt5SyncEnabled)
          const _Mt5NoticeCard(
            icon: Icons.sync_disabled_outlined,
            title: 'MT5 sync is disabled.',
            message:
                'Existing manual journal features continue to work normally.',
          )
        else if (!_enabled)
          _Mt5NoticeCard(
            icon: Icons.sync_disabled_outlined,
            title: 'MT5 sync is disabled.',
            message:
                'Existing manual journal features continue to work normally.',
          )
        else
          _buildEnabledContent(context),
      ],
    );
  }

  Widget _buildEnabledContent(BuildContext context) {
    if (_initialLoading && _accounts.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 32),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (_accountLoadError != null) {
      return _Mt5NoticeCard(
        icon: Icons.sync_problem_outlined,
        title: 'Unable to update right now',
        message: 'Use Manual Refresh again in a moment.',
        action: OutlinedButton.icon(
          onPressed: _loadInitial,
          icon: const Icon(Icons.refresh),
          label: Text(context.l10n.text('Manual Refresh')),
        ),
      );
    }
    if (_accounts.isEmpty) {
      return _Mt5NoticeCard(
        icon: Icons.account_balance_outlined,
        title: 'No connected MT5 account was found.',
        message: 'Link an MT5 account to view synced trades.',
        action: FilledButton.icon(
          onPressed: _openAccountManagement,
          icon: const Icon(Icons.manage_accounts_outlined),
          label: Text(context.l10n.text('Manage MT5 Accounts')),
        ),
      );
    }

    final selectedAccount = _selectedAccount();
    if (selectedAccount == null) {
      return _Mt5NoticeCard(
        icon: Icons.radio_button_checked,
        title: 'Select a main MT5 account to continue.',
        message:
            'Multiple MT5 accounts are linked. Choose which account should supply synchronized trading data.',
        action: FilledButton.icon(
          onPressed: _openAccountManagement,
          icon: const Icon(Icons.manage_accounts_outlined),
          label: Text(context.l10n.text('Manage MT5 Accounts')),
        ),
      );
    }
    final records = _filtered(_mergedRecords(_records));
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _Mt5SelectedAccountCard(
          account: selectedAccount,
          syncState: _syncState,
          onManage: _openAccountManagement,
        ),
        if (_syncState?.syncStatus == 'offline' ||
            _syncState?.syncStatus == 'error') ...[
          const SizedBox(height: 12),
          const _Mt5NoticeCard(
            icon: Icons.sync_problem_outlined,
            title: 'Unable to update right now',
            message: 'Use Manual Refresh again in a moment.',
          ),
        ],
        if (_recordsLoadError != null) ...[
          const SizedBox(height: 12),
          const _Mt5NoticeCard(
            icon: Icons.error_outline,
            title: 'Unable to load synced trades.',
            message: 'Use Manual Refresh again in a moment.',
          ),
        ],
        const SizedBox(height: 12),
        _Mt5FiltersCard(
          symbolController: _symbolController,
          recordFilter: _recordFilter,
          profitFilter: _profitFilter,
          fromDate: _fromDate,
          toDate: _toDate,
          onChanged: () => setState(() {}),
          onRecordFilter: (value) => setState(() => _recordFilter = value),
          onProfitFilter: (value) => setState(() => _profitFilter = value),
          onFromDate: () => _pickDate(isFrom: true),
          onToDate: () => _pickDate(isFrom: false),
          onClearDates: () => setState(() {
            _fromDate = null;
            _toDate = null;
          }),
        ),
        const SizedBox(height: 12),
        _Mt5HistoryActions(
          refreshing: _manualRefreshing,
          loadingMore: _loadingMoreHistory,
          onRefresh: () => _manualRefresh(selectedAccount.accountKey),
          onLoadMore: () => _loadMoreHistory(
            selectedAccount.accountKey,
            _oldestClosedTime(_mergedRecords(_records)),
          ),
        ),
        const SizedBox(height: 12),
        if (records.isEmpty)
          const EmptyState(
            icon: Icons.sync_alt_outlined,
            title: 'No synced trades found.',
          )
        else
          for (final record in records) ...[
            _Mt5RecordCard(record: record, onTap: () => _openRecord(record)),
            const SizedBox(height: 10),
          ],
      ],
    );
  }

  Mt5Account? _selectedAccount() {
    if (_selectedAccountKey != null) {
      for (final account in _accounts) {
        if (account.accountKey == _selectedAccountKey) return account;
      }
    }
    return null;
  }

  Future<void> _loadInitial({String? preferredAccountKey}) async {
    if (!Mt5SyncConfig.mt5SyncEnabled || !_enabled) return;
    _activityTimer?.cancel();
    if (mounted) {
      setState(() {
        _initialLoading = _accounts.isEmpty;
        _accountLoadError = null;
        _recordsLoadError = null;
      });
    }
    try {
      final accounts = await widget.repository.loadMt5Accounts(
        widget.user.uid,
        forceServer: true,
      );
      if (!mounted) return;
      if (accounts.isEmpty) {
        setState(() {
          _accounts = const [];
          _records = const [];
          _syncState = null;
          _selectedAccountKey = null;
          _initialLoading = false;
          _rememberActivityMarker(null);
        });
        await _writeMt5SyncSettings(clearSelectedAccount: true);
        return;
      }

      String? selectedKey;
      if (preferredAccountKey != null &&
          accounts.any((item) => item.accountKey == preferredAccountKey)) {
        selectedKey = preferredAccountKey;
      } else {
        final mainAccount = await widget.repository.loadMainMt5Account(
          widget.user.uid,
          forceServer: true,
        );
        selectedKey = mainAccount?.accountKey;
        if (selectedKey == null && accounts.length == 1) {
          selectedKey = accounts.single.accountKey;
          await widget.repository.setMainMt5Account(
            uid: widget.user.uid,
            accountKey: selectedKey,
          );
        }
      }
      if (selectedKey == null ||
          !accounts.any((item) => item.accountKey == selectedKey)) {
        setState(() {
          _accounts = accounts;
          _selectedAccountKey = null;
          _syncState = null;
          _records = const [];
          _initialLoading = false;
          _accountLoadError = null;
          _recordsLoadError = null;
          _rememberActivityMarker(null);
        });
        await _writeMt5SyncSettings(clearSelectedAccount: true);
        return;
      }
      final syncState = await widget.repository.loadMt5SyncState(
        uid: widget.user.uid,
        accountKey: selectedKey,
        forceServer: true,
      );
      final records = await widget.repository
          .loadMt5ControlledRecordsForAccount(
            uid: widget.user.uid,
            accountKey: selectedKey,
            forceServer: true,
          );
      if (!mounted) return;
      setState(() {
        _accounts = accounts;
        _selectedAccountKey = selectedKey;
        _syncState = syncState;
        _records = records;
        _initialLoading = false;
        _accountLoadError = null;
        _recordsLoadError = null;
        _rememberActivityMarker(syncState);
      });
      await _writeMt5SyncSettings(selectedAccountKey: selectedKey);
      _startActivityTimer();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _initialLoading = false;
        _accountLoadError = 'Unable to update right now.';
      });
    }
  }

  Future<void> _loadAccountSnapshot(
    String accountKey, {
    bool refreshAccounts = true,
    Mt5SyncState? knownSyncState,
    bool forceServer = false,
  }) async {
    try {
      final accounts = refreshAccounts
          ? await widget.repository.loadMt5Accounts(
              widget.user.uid,
              forceServer: forceServer,
            )
          : null;
      final syncState =
          knownSyncState ??
          await widget.repository.loadMt5SyncState(
            uid: widget.user.uid,
            accountKey: accountKey,
            forceServer: forceServer,
          );
      final records = await widget.repository
          .loadMt5ControlledRecordsForAccount(
            uid: widget.user.uid,
            accountKey: accountKey,
            forceServer: forceServer,
          );
      if (!mounted || _selectedAccountKey != accountKey) return;
      setState(() {
        if (accounts != null && accounts.isNotEmpty) {
          _accounts = accounts;
        }
        _syncState = syncState;
        _records = records;
        _recordsLoadError = null;
        _rememberActivityMarker(syncState);
      });
      _startActivityTimer();
    } catch (_) {
      if (!mounted || _selectedAccountKey != accountKey) return;
      setState(() => _recordsLoadError = 'Unable to load synced trades.');
    }
  }

  void _startActivityTimer() {
    _activityTimer?.cancel();
    if (!_enabled || !Mt5SyncConfig.mt5SyncEnabled) return;
    final accountKey = _selectedAccountKey;
    if (accountKey == null || accountKey.isEmpty) return;
    final minutes = Mt5SyncConfig.activityCheckIntervalMinutes < 1
        ? 1
        : Mt5SyncConfig.activityCheckIntervalMinutes;
    _activityTimer = Timer.periodic(
      Duration(minutes: minutes),
      (_) => _checkActivityMarker(accountKey),
    );
  }

  Future<void> _checkActivityMarker(String accountKey) async {
    if (_checkingActivity ||
        _manualRefreshing ||
        _initialLoading ||
        !_enabled ||
        accountKey != _selectedAccountKey) {
      return;
    }
    _checkingActivity = true;
    try {
      final nextState = await widget.repository.loadMt5SyncState(
        uid: widget.user.uid,
        accountKey: accountKey,
        forceServer: true,
      );
      if (!mounted || accountKey != _selectedAccountKey) return;
      if (_hasNewActivity(nextState)) {
        await _loadAccountSnapshot(
          accountKey,
          knownSyncState: nextState,
          forceServer: true,
        );
        return;
      }
      setState(() {
        _syncState = nextState;
        _rememberActivityMarker(nextState);
      });
    } catch (_) {
      // Background activity checks stay quiet; manual refresh provides feedback.
    } finally {
      _checkingActivity = false;
    }
  }

  bool _hasNewActivity(Mt5SyncState? nextState) {
    if (nextState == null) return false;
    if (nextState.lastTradeChangeVersion > _lastLoadedTradeChangeVersion) {
      return true;
    }
    final nextActivityAt = nextState.lastTradeActivityAt;
    if (nextActivityAt != null) {
      final currentActivityAt = _lastLoadedTradeActivityAt;
      if (currentActivityAt == null ||
          nextActivityAt.isAfter(currentActivityAt)) {
        return true;
      }
    }
    final nextActivityId = nextState.lastTradeActivityId;
    return nextActivityId.isNotEmpty &&
        nextActivityId != _lastLoadedTradeActivityId;
  }

  void _rememberActivityMarker(Mt5SyncState? state) {
    _lastLoadedTradeActivityAt = state?.lastTradeActivityAt;
    _lastLoadedTradeActivityId = state?.lastTradeActivityId ?? '';
    _lastLoadedTradeChangeVersion = state?.lastTradeChangeVersion ?? 0;
  }

  List<Mt5TradeRecord> _filtered(List<Mt5TradeRecord> records) {
    final symbol = _symbolController.text.trim().toLowerCase();
    return records.where((record) {
      if (!record.isTradingActivity) return false;
      if (!journalMatchesFinancialPairing(
        record.symbol,
        widget.pairingFilter,
      )) {
        return false;
      }
      if (_recordFilter == 'open' && !record.isOpen) return false;
      if (_recordFilter == 'closed' && !record.isClosed) return false;
      if (_recordFilter == 'pending' && !record.isPending) return false;
      if (_profitFilter == 'profit' && record.netProfit <= 0) return false;
      if (_profitFilter == 'loss' && record.netProfit >= 0) return false;
      if (symbol.isNotEmpty && !record.symbol.toLowerCase().contains(symbol)) {
        return false;
      }
      final recordTime = record.closeTime ?? record.openTime;
      if (_fromDate != null && recordTime != null) {
        final start = DateTime(
          _fromDate!.year,
          _fromDate!.month,
          _fromDate!.day,
        );
        if (recordTime.isBefore(start)) return false;
      }
      if (_toDate != null && recordTime != null) {
        final end = DateTime(
          _toDate!.year,
          _toDate!.month,
          _toDate!.day,
          23,
          59,
          59,
        );
        if (recordTime.isAfter(end)) return false;
      }
      return true;
    }).toList();
  }

  List<Mt5TradeRecord> _mergedRecords(List<Mt5TradeRecord> realtimeRecords) {
    return dedupeMt5LifecycleRecords([..._olderHistory, ...realtimeRecords]);
  }

  DateTime? _oldestClosedTime(List<Mt5TradeRecord> records) {
    final closedTimes = records
        .where((record) => record.isClosed)
        .map((record) => record.closeTime ?? record.openTime)
        .whereType<DateTime>()
        .toList();
    if (closedTimes.isEmpty) return null;
    closedTimes.sort();
    return closedTimes.first;
  }

  void _resetHistory() {
    _olderHistory.clear();
    _oldestLoadedCloseTime = null;
  }

  Future<void> _manualRefresh(String accountKey) async {
    if (_manualRefreshing) return;
    final previousRecords = List<Mt5TradeRecord>.from(_records);
    final range = _selectedHistoryRange();
    setState(() => _manualRefreshing = true);
    try {
      await widget.repository.refreshMt5Account(
        uid: widget.user.uid,
        accountKey: accountKey,
        refreshType: range == null
            ? 'manual_refresh'
            : 'selected_history_range',
        requestedDateFrom: range?.from,
        requestedDateTo: range?.to,
        forceServer: true,
      );
      final accounts = await widget.repository.loadMt5Accounts(
        widget.user.uid,
        forceServer: true,
      );
      final syncState = await widget.repository.loadMt5SyncState(
        uid: widget.user.uid,
        accountKey: accountKey,
        forceServer: true,
      );
      final markerChanged = _hasNewActivity(syncState);
      final records = await widget.repository
          .loadMt5ControlledRecordsForAccount(
            uid: widget.user.uid,
            accountKey: accountKey,
            forceServer: true,
          );
      final rangeRecords = range == null
          ? const <Mt5TradeRecord>[]
          : await widget.repository.mt5ClosedHistoryRange(
              uid: widget.user.uid,
              accountKey: accountKey,
              from: range.from,
              to: range.to,
              forceServer: true,
            );
      if (!mounted || _selectedAccountKey != accountKey) return;
      final recordsChanged =
          _recordFingerprint(previousRecords) !=
          _recordFingerprint([...records, ...rangeRecords]);
      setState(() {
        if (accounts.isNotEmpty) {
          _accounts = accounts;
        }
        _syncState = syncState;
        _records = records;
        if (rangeRecords.isNotEmpty) {
          _mergeOlderHistory(rangeRecords);
        }
        _accountLoadError = null;
        _recordsLoadError = null;
        _rememberActivityMarker(syncState);
      });
      _startActivityTimer();
      if (mounted) {
        final statusMessage = syncState?.labelKey ?? '';
        final errorMessage = syncState?.publicErrorMessage ?? '';
        final refreshMessage = recordsChanged || markerChanged
            ? 'Trading Journal updated'
            : errorMessage.isNotEmpty
            ? errorMessage
            : statusMessage == 'Disconnected' ||
                  statusMessage == 'Unable to update right now' ||
                  statusMessage == 'Account not linked' ||
                  statusMessage == 'Wrong MT5 account detected'
            ? statusMessage
            : 'No new activity found yet. Please try again shortly.';
        AppFeedback.success(context, context.l10n.text(refreshMessage));
      }
    } on ApiException catch (error) {
      await _loadAccountSnapshot(accountKey, forceServer: true);
      if (mounted) {
        AppFeedback.error(context, context.l10n.text(error.message));
      }
    } on FirebaseException catch (error, stackTrace) {
      _logMt5RefreshError('manual_refresh', accountKey, error, stackTrace);
      await _loadAccountSnapshot(accountKey, forceServer: true);
      if (mounted) {
        AppFeedback.error(context, error, operation: 'MT5 refresh');
      }
    } catch (error) {
      await _loadAccountSnapshot(accountKey, forceServer: true);
      if (mounted) {
        AppFeedback.error(context, error, operation: 'MT5 refresh');
      }
    } finally {
      if (mounted) setState(() => _manualRefreshing = false);
    }
  }

  Future<void> _loadMoreHistory(
    String accountKey,
    DateTime? realtimeOldestCloseTime,
  ) async {
    if (_loadingMoreHistory) return;
    setState(() => _loadingMoreHistory = true);
    try {
      final cursor =
          (_oldestLoadedCloseTime ?? realtimeOldestCloseTime ?? DateTime.now())
              .subtract(const Duration(seconds: 1));
      final rangeStart = DateTime(cursor.year, cursor.month - 3, cursor.day);
      final rangeEnd = DateTime(
        cursor.year,
        cursor.month,
        cursor.day,
        23,
        59,
        59,
      );
      await widget.repository.refreshMt5HistoryRange(
        uid: widget.user.uid,
        accountKey: accountKey,
        requestedDateFrom: rangeStart,
        requestedDateTo: rangeEnd,
        forceServer: true,
      );
      final page = await widget.repository.mt5ClosedHistoryRange(
        uid: widget.user.uid,
        accountKey: accountKey,
        from: rangeStart,
        to: rangeEnd,
        forceServer: true,
      );
      if (!mounted || _selectedAccountKey != accountKey) return;
      if (page.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.text('No more history.'))),
        );
        return;
      }
      setState(() {
        _mergeOlderHistory(page);
      });
    } on ApiException catch (error) {
      await _loadAccountSnapshot(accountKey, forceServer: true);
      if (mounted) {
        AppFeedback.error(context, context.l10n.text(error.message));
      }
    } on FirebaseException catch (error, stackTrace) {
      _logMt5RefreshError('load_more_history', accountKey, error, stackTrace);
      await _loadAccountSnapshot(accountKey, forceServer: true);
      if (mounted) {
        AppFeedback.error(context, error, operation: 'MT5 history');
      }
    } catch (error) {
      await _loadAccountSnapshot(accountKey, forceServer: true);
      if (mounted) {
        AppFeedback.error(context, error, operation: 'MT5 history');
      }
    } finally {
      if (mounted) setState(() => _loadingMoreHistory = false);
    }
  }

  ({DateTime from, DateTime to})? _selectedHistoryRange() {
    if (_fromDate == null && _toDate == null) return null;
    final first = _fromDate ?? _toDate!;
    final second = _toDate ?? _fromDate!;
    final orderedFrom = first.isAfter(second) ? second : first;
    final orderedTo = first.isAfter(second) ? first : second;
    return (
      from: DateTime(orderedFrom.year, orderedFrom.month, orderedFrom.day),
      to: DateTime(orderedTo.year, orderedTo.month, orderedTo.day, 23, 59, 59),
    );
  }

  void _mergeOlderHistory(Iterable<Mt5TradeRecord> records) {
    final merged = dedupeMt5LifecycleRecords([..._olderHistory, ...records]);
    _olderHistory
      ..clear()
      ..addAll(merged);
    _oldestLoadedCloseTime =
        _oldestClosedTime(_olderHistory) ?? _oldestLoadedCloseTime;
  }

  void _logMt5RefreshError(
    String operation,
    String accountKey,
    FirebaseException error,
    StackTrace stackTrace,
  ) {
    debugPrint(
      '[MT5 Journal][$operation] FirebaseException '
      'code=${error.code} uid=${widget.user.uid} account=$accountKey '
      'path=mt5_sync_jobs message=${error.message ?? ''}',
    );
    debugPrintStack(stackTrace: stackTrace);
  }

  String _recordFingerprint(List<Mt5TradeRecord> records) {
    final parts = records.map((record) {
      final key = record.canonicalLifecycleKey;
      return '$key:${record.recordHash}:${record.status}:'
          '${record.openTime?.toIso8601String() ?? ''}:'
          '${record.closeTime?.toIso8601String() ?? ''}';
    }).toList()..sort();
    return parts.join('|');
  }

  Future<void> _pickDate({required bool isFrom}) async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 1),
      initialDate: isFrom ? (_fromDate ?? now) : (_toDate ?? now),
    );
    if (picked == null) return;
    setState(() {
      if (isFrom) {
        _fromDate = picked;
      } else {
        _toDate = picked;
      }
    });
  }

  Future<void> _openRecord(Mt5TradeRecord record) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _Mt5RecordDetailsSheet(
        record: record,
        user: widget.user,
        repository: widget.repository,
      ),
    );
  }
}

class _Mt5FeatureCard extends StatelessWidget {
  const _Mt5FeatureCard({
    required this.enabled,
    required this.globallyEnabled,
    required this.onChanged,
    required this.onLinkAccount,
  });

  final bool enabled;
  final bool globallyEnabled;
  final ValueChanged<bool> onChanged;
  final VoidCallback onLinkAccount;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.sync_alt_outlined),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    context.l10n.text('Synced MT5 Trades'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                Switch(
                  value: globallyEnabled && enabled,
                  onChanged: globallyEnabled ? onChanged : null,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'View linked MT5 trades and refresh when you need the latest journal data.',
              ),
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              context.l10n.text(
                'Use Manual Refresh anytime. The list only updates when new MT5 activity is found.',
              ),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              context.l10n.text('Multiple MT5 accounts can be linked.'),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerLeft,
              child: OutlinedButton.icon(
                onPressed: onLinkAccount,
                icon: const Icon(Icons.manage_accounts_outlined),
                label: Text(context.l10n.text('Manage MT5 Accounts')),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Mt5HistoryActions extends StatelessWidget {
  const _Mt5HistoryActions({
    required this.refreshing,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
  });

  final bool refreshing;
  final bool loadingMore;
  final VoidCallback onRefresh;
  final VoidCallback onLoadMore;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: OutlinedButton.icon(
            onPressed: refreshing ? null : onRefresh,
            icon: refreshing
                ? const SizedBox.square(
                    dimension: 16,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh),
            label: Text(context.l10n.text('Manual Refresh')),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: FilledButton.tonalIcon(
            onPressed: loadingMore ? null : onLoadMore,
            icon: loadingMore
                ? const SizedBox.square(
                    dimension: 16,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.history),
            label: Text(context.l10n.text('Load More History')),
          ),
        ),
      ],
    );
  }
}

class _Mt5AccountManagementScreen extends StatefulWidget {
  const _Mt5AccountManagementScreen({
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<_Mt5AccountManagementScreen> createState() =>
      _Mt5AccountManagementScreenState();
}

class _Mt5AccountManagementScreenState
    extends State<_Mt5AccountManagementScreen> {
  List<Mt5Account> _accounts = const [];
  Mt5Account? _selectedAccount;
  Mt5SyncState? _syncState;
  String? _updatingAccountKey;
  Object? _loadError;
  var _loading = true;

  @override
  void initState() {
    super.initState();
    scheduleMicrotask(_load);
  }

  Future<void> _load() async {
    if (mounted) {
      setState(() {
        _loading = true;
        _loadError = null;
      });
    }
    try {
      final accounts = await widget.repository.loadMt5Accounts(
        widget.user.uid,
        forceServer: true,
      );
      final selected = await widget.repository.loadMainMt5Account(
        widget.user.uid,
        forceServer: true,
      );
      final syncState = selected == null
          ? null
          : await widget.repository.loadMt5SyncState(
              uid: widget.user.uid,
              accountKey: selected.accountKey,
              forceServer: true,
            );
      if (!mounted) return;
      setState(() {
        _accounts = accounts;
        _selectedAccount = selected;
        _syncState = syncState;
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = error;
        _loading = false;
      });
    }
  }

  Future<void> _linkAccount() async {
    final linked = await showDialog<Mt5Account>(
      context: context,
      builder: (_) => _Mt5AccountLinkDialog(
        uid: widget.user.uid,
        repository: widget.repository,
      ),
    );
    if (linked == null || !mounted) return;
    try {
      await widget.repository.setMainMt5Account(
        uid: widget.user.uid,
        accountKey: linked.accountKey,
      );
      await _load();
      if (mounted) AppFeedback.success(context, 'MT5 account linked.');
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    }
  }

  Future<void> _editAccount(Mt5Account account) async {
    final updated = await showDialog<Mt5Account>(
      context: context,
      builder: (_) => _Mt5AccountLinkDialog(
        uid: widget.user.uid,
        repository: widget.repository,
        initialAccount: account,
      ),
    );
    if (updated == null || !mounted) return;
    await _load();
    if (mounted) AppFeedback.success(context, 'MT5 account updated.');
  }

  Future<void> _unlinkAccount(Mt5Account account) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Unlink Account')),
        content: Text(
          context.l10n.text('Existing synced records will stay stored.'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(context.l10n.text('Unlink Account')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await widget.repository.unlinkMt5Account(
        uid: widget.user.uid,
        accountKey: account.accountKey,
      );
      await _load();
      if (mounted) AppFeedback.success(context, 'MT5 account unlinked.');
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    }
  }

  Future<void> _setMainAccount(String accountKey) async {
    if (_updatingAccountKey != null ||
        accountKey == _selectedAccount?.accountKey) {
      return;
    }
    setState(() => _updatingAccountKey = accountKey);
    try {
      await widget.repository.setMainMt5Account(
        uid: widget.user.uid,
        accountKey: accountKey,
      );
      await _load();
      if (mounted) {
        AppFeedback.success(context, 'Main account updated successfully.');
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _updatingAccountKey = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('MT5 Account Management'))),
      body: SafeArea(child: _buildBody(context)),
    );
  }

  Widget _buildBody(BuildContext context) {
    if (_loading && _accounts.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_loadError != null && _accounts.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: _Mt5NoticeCard(
            icon: Icons.sync_problem_outlined,
            title: 'Unable to load MT5 accounts.',
            message: 'Use Manual Refresh again in a moment.',
            action: FilledButton.icon(
              onPressed: _load,
              icon: const Icon(Icons.refresh),
              label: Text(context.l10n.text('Try again')),
            ),
          ),
        ),
      );
    }
    if (_accounts.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: _Mt5NoticeCard(
            icon: Icons.account_balance_outlined,
            title: 'No MT5 account linked.',
            message: 'Multiple MT5 accounts can be linked.',
            action: FilledButton.icon(
              onPressed: _linkAccount,
              icon: const Icon(Icons.add),
              label: Text(context.l10n.text('Link MT5 Account')),
            ),
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        children: [
          _Mt5AccountSummaryCard(
            accounts: _accounts,
            selectedKey: _selectedAccount?.accountKey,
            selectedAccount: _selectedAccount,
            syncState: _syncState,
            updatingAccountKey: _updatingAccountKey,
            onChanged: _setMainAccount,
            onLinkAccount: _linkAccount,
            onEditAccount: _editAccount,
            onUnlinkAccount: _unlinkAccount,
          ),
        ],
      ),
    );
  }
}

class _Mt5SelectedAccountCard extends StatelessWidget {
  const _Mt5SelectedAccountCard({
    required this.account,
    required this.syncState,
    required this.onManage,
  });

  final Mt5Account account;
  final Mt5SyncState? syncState;
  final VoidCallback onManage;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final status = _customerMt5Status(syncState, account);
    final statusColor = _mt5StatusColor(context, status);
    final lastSync =
        syncState?.lastSuccessfulSyncAt ??
        account.lastSuccessfulSyncAt ??
        account.lastSyncTime;
    final errorMessage =
        syncState?.publicErrorMessage.trim() ?? account.lastError.trim();
    final showError = errorMessage.isNotEmpty && status != 'Connected';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: scheme.primary.withValues(alpha: .14),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    Icons.monitor_heart_outlined,
                    color: scheme.primary,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        account.label,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        '#${account.login}  ·  ${account.brokerServer}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                _StatusChip(
                  label: context.l10n.text(status),
                  color: statusColor,
                ),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 24,
              runSpacing: 12,
              children: [
                _JournalFigure(
                  label: context.l10n.text('Balance'),
                  value: formatCurrencyMoney(
                    mt5AccountCapitalBalance(account),
                    account.currency,
                  ),
                ),
                _JournalFigure(
                  label: context.l10n.text('Equity'),
                  value: account.equity == null
                      ? '-'
                      : formatCurrencyMoney(account.equity!, account.currency),
                ),
                _JournalFigure(
                  label: context.l10n.text('Last sync time'),
                  value: lastSync == null ? '-' : formatDateTime(lastSync),
                ),
              ],
            ),
            if (showError) ...[
              const SizedBox(height: 14),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  color: scheme.errorContainer.withValues(alpha: .5),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    Icon(Icons.error_outline, size: 18, color: scheme.error),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        context.l10n.text(errorMessage),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 14),
            Align(
              alignment: Alignment.centerLeft,
              child: OutlinedButton.icon(
                onPressed: onManage,
                icon: const Icon(Icons.manage_accounts_outlined),
                label: Text(context.l10n.text('Manage MT5 Accounts')),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Mt5AccountSummaryCard extends StatelessWidget {
  const _Mt5AccountSummaryCard({
    required this.accounts,
    required this.selectedKey,
    required this.selectedAccount,
    required this.syncState,
    required this.updatingAccountKey,
    required this.onChanged,
    required this.onLinkAccount,
    required this.onEditAccount,
    required this.onUnlinkAccount,
  });

  final List<Mt5Account> accounts;
  final String? selectedKey;
  final Mt5Account? selectedAccount;
  final Mt5SyncState? syncState;
  final String? updatingAccountKey;
  final ValueChanged<String> onChanged;
  final VoidCallback onLinkAccount;
  final ValueChanged<Mt5Account> onEditAccount;
  final ValueChanged<Mt5Account> onUnlinkAccount;

  @override
  Widget build(BuildContext context) {
    final selected = selectedAccount;
    final state = selected == null ? null : syncState;
    final status = selected == null
        ? 'Select a main MT5 account to continue.'
        : _customerMt5Status(state, selected);
    final tone = selected == null
        ? Theme.of(context).colorScheme.primary
        : _mt5StatusColor(context, status);
    final lastSyncTime = selected == null
        ? null
        : state?.lastSuccessfulSyncAt ??
              selected.lastSuccessfulSyncAt ??
              selected.lastSyncTime;
    final lastUpdated = selected == null
        ? null
        : state?.lastConnectionCheckAt ??
              state?.lastHeartbeatAt ??
              state?.updatedAt ??
              state?.lastSuccessfulSyncAt ??
              selected.lastConnectionCheckAt ??
              selected.lastHeartbeatAt ??
              selected.lastSuccessfulSyncAt ??
              selected.updatedAt;
    final errorMessage =
        state?.publicErrorMessage.trim() ?? selected?.lastError.trim() ?? '';
    final errorCode =
        state?.lastErrorCode.trim() ?? selected?.lastErrorCode.trim() ?? '';
    final showError = errorMessage.isNotEmpty && status != 'Connected';

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: _SectionTitle(
                    title: 'Linked MT5 Accounts',
                    icon: Icons.account_balance,
                  ),
                ),
                FilledButton.tonalIcon(
                  onPressed: onLinkAccount,
                  icon: const Icon(Icons.add, size: 18),
                  label: Text(context.l10n.text('Link MT5 Account')),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'Choose which MT5 account supplies synchronized trading data across the app.',
              ),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 16),
            LayoutBuilder(
              builder: (context, constraints) {
                const gap = 12.0;
                final columns = constraints.maxWidth >= 1120
                    ? 3
                    : constraints.maxWidth >= 680
                    ? 2
                    : 1;
                final cardWidth =
                    (constraints.maxWidth - gap * (columns - 1)) / columns;

                return Wrap(
                  spacing: gap,
                  runSpacing: gap,
                  children: [
                    for (final account in accounts)
                      SizedBox(
                        width: cardWidth,
                        child: _Mt5AccountSelectionRow(
                          account: account,
                          selected: account.accountKey == selectedKey,
                          updating: account.accountKey == updatingAccountKey,
                          disabled: updatingAccountKey != null,
                          onSetMain: () => onChanged(account.accountKey),
                          onEdit: () => onEditAccount(account),
                          onUnlink: () => onUnlinkAccount(account),
                        ),
                      ),
                  ],
                );
              },
            ),
            if (selected != null) ...[
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.primaryContainer.withValues(alpha: .28),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                    color: Theme.of(
                      context,
                    ).colorScheme.primary.withValues(alpha: .22),
                  ),
                ),
                child: Wrap(
                  spacing: 24,
                  runSpacing: 14,
                  children: [
                    _JournalFigure(
                      label: context.l10n.text('Balance'),
                      value: selected.balance == null
                          ? '-'
                          : formatCurrencyMoney(
                              selected.balance!,
                              selected.currency,
                            ),
                    ),
                    _JournalFigure(
                      label: context.l10n.text('Equity'),
                      value: selected.equity == null
                          ? '-'
                          : formatCurrencyMoney(
                              selected.equity!,
                              selected.currency,
                            ),
                    ),
                    _JournalFigure(
                      label: context.l10n.text('Currency'),
                      value: selected.currency.isEmpty
                          ? '-'
                          : selected.currency,
                    ),
                    _JournalFigure(
                      label: context.l10n.text('Sync Location'),
                      value: context.l10n.text(
                        selected.syncMachineType == 'vps'
                            ? 'Hosted device'
                            : 'This device',
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                _StatusChip(label: context.l10n.text(status), color: tone),
                _Pill(
                  icon: Icons.schedule,
                  label:
                      '${context.l10n.text('Last sync time')}: ${lastSyncTime == null ? '-' : formatDateTime(lastSyncTime)}',
                ),
                _Pill(
                  icon: Icons.update,
                  label:
                      '${context.l10n.text('Last updated')}: ${lastUpdated == null ? '-' : formatDateTime(lastUpdated)}',
                ),
              ],
            ),
            if (showError) ...[
              const SizedBox(height: 12),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.errorContainer.withValues(alpha: .45),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.error_outline,
                      color: Theme.of(context).colorScheme.error,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            context.l10n.text('Sync issue'),
                            style: Theme.of(context).textTheme.labelLarge
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                          const SizedBox(height: 4),
                          Text(context.l10n.text(errorMessage)),
                          if (errorCode.isNotEmpty) ...[
                            const SizedBox(height: 4),
                            Text(
                              '${context.l10n.text('Error code')}: $errorCode',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _Mt5AccountSelectionRow extends StatelessWidget {
  const _Mt5AccountSelectionRow({
    required this.account,
    required this.selected,
    required this.updating,
    required this.disabled,
    required this.onSetMain,
    required this.onEdit,
    required this.onUnlink,
  });

  final Mt5Account account;
  final bool selected;
  final bool updating;
  final bool disabled;
  final VoidCallback onSetMain;
  final VoidCallback onEdit;
  final VoidCallback onUnlink;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final status = _customerMt5Status(null, account);
    final statusColor = _mt5StatusColor(context, status);
    final lastSync =
        account.lastSuccessfulSyncAt ??
        account.lastSyncTime ??
        account.updatedAt;

    final hasIssue =
        account.lastError.trim().isNotEmpty &&
        status != 'Connected' &&
        status != 'Updating';

    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      decoration: BoxDecoration(
        color: selected
            ? scheme.primaryContainer.withValues(alpha: .32)
            : scheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: selected
              ? scheme.primary.withValues(alpha: .8)
              : scheme.outlineVariant,
          width: selected ? 1.5 : 1,
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: selected || disabled ? null : onSetMain,
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: selected
                            ? scheme.primary.withValues(alpha: .14)
                            : scheme.surfaceContainerHighest,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        Icons.monitor_heart_outlined,
                        color: selected
                            ? scheme.primary
                            : scheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            account.label,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w900),
                          ),
                          const SizedBox(height: 3),
                          Text(
                            '#${account.login}',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: scheme.onSurfaceVariant,
                                  fontWeight: FontWeight.w700,
                                ),
                          ),
                        ],
                      ),
                    ),
                    PopupMenuButton<String>(
                      tooltip: context.l10n.text('More'),
                      enabled: !disabled,
                      onSelected: (value) {
                        if (value == 'edit') onEdit();
                        if (value == 'unlink') onUnlink();
                      },
                      itemBuilder: (context) => [
                        PopupMenuItem(
                          value: 'edit',
                          child: ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: const Icon(Icons.edit_outlined),
                            title: Text(context.l10n.text('Edit')),
                          ),
                        ),
                        PopupMenuItem(
                          value: 'unlink',
                          child: ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: Icon(
                              Icons.link_off_outlined,
                              color: scheme.error,
                            ),
                            title: Text(
                              context.l10n.text('Unlink Account'),
                              style: TextStyle(color: scheme.error),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    if (selected)
                      _StatusChip(
                        label: context.l10n.text('Main Account'),
                        color: scheme.primary,
                      ),
                    _StatusChip(
                      label: context.l10n.text(status),
                      color: statusColor,
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Divider(height: 1, color: scheme.outlineVariant),
                const SizedBox(height: 12),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final itemWidth = (constraints.maxWidth - 12) / 2;
                    return Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: [
                        SizedBox(
                          width: itemWidth,
                          child: _Mt5AccountDetail(
                            icon: Icons.dns_outlined,
                            label: context.l10n.text('Broker Server'),
                            value: account.brokerServer,
                          ),
                        ),
                        SizedBox(
                          width: itemWidth,
                          child: _Mt5AccountDetail(
                            icon: Icons.payments_outlined,
                            label: context.l10n.text('Currency'),
                            value: account.currency.trim().isEmpty
                                ? '-'
                                : account.currency,
                          ),
                        ),
                        SizedBox(
                          width: constraints.maxWidth,
                          child: _Mt5AccountDetail(
                            icon: Icons.schedule_outlined,
                            label: context.l10n.text('Last sync time'),
                            value: lastSync == null
                                ? '-'
                                : formatDateTime(lastSync),
                          ),
                        ),
                      ],
                    );
                  },
                ),
                if (hasIssue) ...[
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 8,
                    ),
                    decoration: BoxDecoration(
                      color: scheme.errorContainer.withValues(alpha: .5),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.error_outline,
                          size: 18,
                          color: scheme.error,
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            context.l10n.text(account.lastError.trim()),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: selected
                      ? Container(
                          height: 42,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: scheme.primary.withValues(alpha: .12),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(
                                Icons.verified_rounded,
                                size: 18,
                                color: scheme.primary,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                context.l10n.text('Selected for synced data'),
                                style: Theme.of(context).textTheme.labelLarge
                                    ?.copyWith(
                                      color: scheme.primary,
                                      fontWeight: FontWeight.w900,
                                    ),
                              ),
                            ],
                          ),
                        )
                      : FilledButton.tonalIcon(
                          onPressed: disabled ? null : onSetMain,
                          icon: updating
                              ? const SizedBox.square(
                                  dimension: 16,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.check_circle_outline),
                          label: Text(
                            context.l10n.text(
                              updating
                                  ? 'Setting main account...'
                                  : 'Set as Main Account',
                            ),
                          ),
                        ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _Mt5AccountDetail extends StatelessWidget {
  const _Mt5AccountDetail({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 17, color: scheme.onSurfaceVariant),
        const SizedBox(width: 7),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: scheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w800),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

String _customerMt5Status(Mt5SyncState? state, Mt5Account account) {
  if (state != null) return state.labelKey;

  final displayStatus = account.displayStatus.trim();
  final status = account.syncStatus.trim();
  final errorCode = account.lastErrorCode.trim().toUpperCase().replaceAll(
    RegExp(r'[\s-]+'),
    '_',
  );
  if (errorCode == 'MT5_ACTIVE_ACCOUNT_MISMATCH' ||
      status == 'account_mismatch') {
    return 'Wrong MT5 account detected';
  }
  if (status == 'login_failed') return 'Login Failed';
  if (status == 'temporary_error') return 'Temporary Error';
  if (status == 'terminal_closed') return 'MT5 Terminal Closed';
  if (status == 'vps_offline') return 'VPS Offline';
  if ((status == 'connected' || displayStatus == 'Connected') &&
      !account.isConnectionFresh) {
    return 'Disconnected';
  }
  if (status == 'disconnected' || status == 'error') {
    return 'Unable to update right now';
  }
  if (status == 'syncing' || status == 'reconnecting') return 'Updating';
  if (status == 'account_not_linked') return 'Account not linked';
  if (status == 'connected') {
    return account.isConnectionFresh ? 'Connected' : 'Disconnected';
  }
  if (status == 'logged_out' ||
      status == 'terminal_closed' ||
      status == 'offline') {
    return 'Disconnected';
  }
  if (displayStatus.isNotEmpty) {
    if (displayStatus == 'Update temporarily unavailable') {
      return 'Unable to update right now';
    }
    return displayStatus;
  }
  return account.isConnected ? 'Connected' : 'Not connected';
}

Color _mt5StatusColor(BuildContext context, String status) {
  switch (status) {
    case 'Live':
    case 'Connected':
      return Colors.green;
    case 'Syncing':
    case 'Updating':
      return Colors.blue;
    case 'Offline':
    case 'Disconnected':
    case 'Account not linked':
    case 'MT5 Terminal Closed':
    case 'VPS Offline':
      return Colors.orange;
    case 'Wrong Account':
    case 'Wrong MT5 account detected':
    case 'Login Failed':
    case 'Temporary Error':
    case 'Failed':
    case 'Error':
    case 'Unable to update right now':
      return Colors.red;
    case 'Completed':
      return Colors.green;
    default:
      return Theme.of(context).colorScheme.outline;
  }
}

class _Mt5FiltersCard extends StatelessWidget {
  const _Mt5FiltersCard({
    required this.symbolController,
    required this.recordFilter,
    required this.profitFilter,
    required this.fromDate,
    required this.toDate,
    required this.onChanged,
    required this.onRecordFilter,
    required this.onProfitFilter,
    required this.onFromDate,
    required this.onToDate,
    required this.onClearDates,
  });

  final TextEditingController symbolController;
  final String recordFilter;
  final String profitFilter;
  final DateTime? fromDate;
  final DateTime? toDate;
  final VoidCallback onChanged;
  final ValueChanged<String> onRecordFilter;
  final ValueChanged<String> onProfitFilter;
  final VoidCallback onFromDate;
  final VoidCallback onToDate;
  final VoidCallback onClearDates;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            TextField(
              controller: symbolController,
              textCapitalization: TextCapitalization.characters,
              decoration: InputDecoration(
                labelText: context.l10n.text('Symbol filter'),
                prefixIcon: const Icon(Icons.search),
              ),
              onChanged: (_) => onChanged(),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final item in const [
                  ('all', 'All'),
                  ('open', 'Open Trades'),
                  ('closed', 'Closed Trades'),
                  ('pending', 'Pending Orders'),
                ])
                  ChoiceChip(
                    label: Text(context.l10n.text(item.$2)),
                    selected: recordFilter == item.$1,
                    onSelected: (_) => onRecordFilter(item.$1),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              key: ValueKey(profitFilter),
              initialValue: profitFilter,
              decoration: InputDecoration(
                labelText: context.l10n.text('Profit/loss filter'),
              ),
              items: [
                DropdownMenuItem(
                  value: 'all',
                  child: Text(context.l10n.text('All')),
                ),
                DropdownMenuItem(
                  value: 'profit',
                  child: Text(context.l10n.text('Profit')),
                ),
                DropdownMenuItem(
                  value: 'loss',
                  child: Text(context.l10n.text('Loss')),
                ),
              ],
              onChanged: (value) {
                if (value != null) onProfitFilter(value);
              },
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: onFromDate,
                  icon: const Icon(Icons.date_range),
                  label: Text(
                    fromDate == null
                        ? context.l10n.text('From date')
                        : formatDate(fromDate!),
                  ),
                ),
                OutlinedButton.icon(
                  onPressed: onToDate,
                  icon: const Icon(Icons.event_available),
                  label: Text(
                    toDate == null
                        ? context.l10n.text('To date')
                        : formatDate(toDate!),
                  ),
                ),
                if (fromDate != null || toDate != null)
                  IconButton(
                    tooltip: context.l10n.text('Clear date filter'),
                    onPressed: onClearDates,
                    icon: const Icon(Icons.clear),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _Mt5RecordCard extends StatelessWidget {
  const _Mt5RecordCard({required this.record, required this.onTap});

  final Mt5TradeRecord record;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final profitColor = record.netProfit > 0
        ? Colors.green
        : record.netProfit < 0
        ? Colors.red
        : Theme.of(context).colorScheme.onSurfaceVariant;
    final statusColor = record.isOpen
        ? Colors.blue
        : record.isPending
        ? Colors.orange
        : record.isClosed
        ? Colors.green
        : Theme.of(context).colorScheme.outline;
    final lastUpdated = record.lastSeenAt ?? record.syncedAt;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      record.symbol.isEmpty ? '-' : record.symbol,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  _StatusChip(
                    label: context.l10n.text(_titleCase(record.status)),
                    color: statusColor,
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 12,
                runSpacing: 10,
                children: [
                  _JournalFigure(
                    label: context.l10n.text('Buy/Sell'),
                    value: context.l10n.text(_titleCase(record.tradeType)),
                  ),
                  _JournalFigure(
                    label: context.l10n.text('Lot Size'),
                    value: record.lotSize.toStringAsFixed(2),
                  ),
                  _JournalFigure(
                    label: context.l10n.text('Entry Price'),
                    value: record.entryPrice.toStringAsFixed(2),
                  ),
                  _JournalFigure(
                    label: context.l10n.text('Current/Close Price'),
                    value:
                        (record.closePrice == 0
                                ? record.currentPrice
                                : record.closePrice)
                            .toStringAsFixed(2),
                  ),
                  _JournalFigure(
                    label: context.l10n.text('Profit'),
                    value: record.netProfit.toStringAsFixed(2),
                    color: profitColor,
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                '${context.l10n.text('Source')}: ${context.l10n.text('MT5 Synced')}  |  ${record.mt5Login} ${record.brokerServer}',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
              if (lastUpdated != null) ...[
                const SizedBox(height: 4),
                Text(
                  '${context.l10n.text('Last updated')}: ${formatDateTime(lastUpdated)}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Mt5NoticeCard extends StatelessWidget {
  const _Mt5NoticeCard({
    required this.icon,
    required this.title,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 30),
            const SizedBox(height: 12),
            Text(
              context.l10n.text(title),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Text(context.l10n.text(message)),
            if (action != null) ...[const SizedBox(height: 14), action!],
          ],
        ),
      ),
    );
  }
}

class _Mt5AccountLinkDialog extends StatefulWidget {
  const _Mt5AccountLinkDialog({
    required this.uid,
    required this.repository,
    this.initialAccount,
  });

  final String uid;
  final TradingRepository repository;
  final Mt5Account? initialAccount;

  @override
  State<_Mt5AccountLinkDialog> createState() => _Mt5AccountLinkDialogState();
}

class _Mt5AccountLinkDialogState extends State<_Mt5AccountLinkDialog> {
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController();
  final _serverController = TextEditingController();
  final _displayNameController = TextEditingController();
  var _machineType = 'local';
  var _saving = false;

  bool get _editing => widget.initialAccount != null;

  @override
  void initState() {
    super.initState();
    final account = widget.initialAccount;
    if (account != null) {
      _loginController.text = '${account.login}';
      _serverController.text = account.brokerServer;
      _displayNameController.text = account.displayName;
      _machineType = account.syncMachineType == 'vps' ? 'vps' : 'local';
    }
  }

  @override
  void dispose() {
    _loginController.dispose();
    _serverController.dispose();
    _displayNameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        context.l10n.text(_editing ? 'Edit MT5 Account' : 'Add MT5 Account'),
      ),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextFormField(
                controller: _loginController,
                enabled: !_editing,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Account Number'),
                ),
                validator: (value) {
                  final login = int.tryParse(value?.trim() ?? '');
                  if (login == null || login <= 0) {
                    return context.l10n.text(
                      'Enter a valid MT5 account number.',
                    );
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _serverController,
                enabled: !_editing,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Broker Server'),
                ),
                validator: (value) => (value?.trim().isEmpty ?? true)
                    ? context.l10n.text('Broker server is required.')
                    : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _displayNameController,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Display Name'),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _machineType,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Sync Location'),
                ),
                items: [
                  DropdownMenuItem(
                    value: 'local',
                    child: Text(context.l10n.text('This device')),
                  ),
                  DropdownMenuItem(
                    value: 'vps',
                    child: Text(context.l10n.text('Hosted device')),
                  ),
                ],
                onChanged: (value) {
                  if (value != null) setState(() => _machineType = value);
                },
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: _saving
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(context.l10n.text(_editing ? 'Save' : 'Link')),
        ),
      ],
    );
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      final initialAccount = widget.initialAccount;
      final account = initialAccount == null
          ? await widget.repository.linkMt5Account(
              uid: widget.uid,
              login: int.parse(_loginController.text.trim()),
              brokerServer: _serverController.text.trim(),
              displayName: _displayNameController.text.trim(),
              syncMachineType: _machineType,
            )
          : await widget.repository.updateMt5Account(
              uid: widget.uid,
              account: initialAccount,
              displayName: _displayNameController.text.trim(),
              syncMachineType: _machineType,
            );
      if (mounted) Navigator.of(context).pop(account);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.text('$error'))));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _Mt5RecordDetailsSheet extends StatefulWidget {
  const _Mt5RecordDetailsSheet({
    required this.record,
    required this.user,
    required this.repository,
  });

  final Mt5TradeRecord record;
  final AppUser user;
  final TradingRepository repository;

  @override
  State<_Mt5RecordDetailsSheet> createState() => _Mt5RecordDetailsSheetState();
}

class _Mt5RecordDetailsSheetState extends State<_Mt5RecordDetailsSheet> {
  late final Map<String, TextEditingController> _controllers;
  Mt5JournalAnnotation? _annotation;
  var _loading = true;
  var _saving = false;
  var _uploading = false;
  bool? _followedPlan;

  Mt5TradeRecord get record => widget.record;

  @override
  void initState() {
    super.initState();
    _controllers = {
      'mood': TextEditingController(text: record.mood),
      'emotion': TextEditingController(text: record.emotion),
      'psychology': TextEditingController(text: record.psychologyNotes),
      'confidence': TextEditingController(text: record.confidence?.toString()),
      'discipline': TextEditingController(text: record.discipline?.toString()),
      'mistake': TextEditingController(text: record.mistakeReason),
      'lesson': TextEditingController(text: record.lessonLearned),
      'entry': TextEditingController(text: record.entryReason),
      'exit': TextEditingController(text: record.exitReview),
      'strategy': TextEditingController(text: record.strategyId),
      'setup': TextEditingController(text: record.setup),
      'tags': TextEditingController(text: record.customTags.join(', ')),
      'notes': TextEditingController(text: record.notes),
      'screenshot': TextEditingController(text: record.screenshotUrl),
    };
    _followedPlan = record.followedTradingPlan;
    scheduleMicrotask(_loadAnnotation);
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _loadAnnotation() async {
    try {
      final value = await widget.repository.loadMt5JournalAnnotation(
        uid: widget.user.uid,
        accountId: record.accountKey,
        lifecycleKey: record.canonicalLifecycleKey,
      );
      if (!mounted) return;
      _annotation = value;
      if (value != null) {
        _controllers['mood']!.text = value.mood ?? '';
        _controllers['emotion']!.text = value.emotion ?? '';
        _controllers['psychology']!.text = value.psychologyNotes;
        _controllers['confidence']!.text = value.confidence?.toString() ?? '';
        _controllers['discipline']!.text = value.discipline?.toString() ?? '';
        _controllers['mistake']!.text = value.mistakeReason ?? '';
        _controllers['lesson']!.text = value.lessonLearned;
        _controllers['entry']!.text = value.entryReason;
        _controllers['exit']!.text = value.exitReview;
        _controllers['strategy']!.text = value.strategyId ?? '';
        _controllers['setup']!.text = value.setup;
        _controllers['tags']!.text = value.customTags.join(', ');
        _controllers['notes']!.text = value.notes;
        _controllers['screenshot']!.text = value.screenshotUrl ?? '';
        _followedPlan = value.followedTradingPlan;
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Mt5JournalAnnotation _value() => Mt5JournalAnnotation(
    id: Mt5JournalAnnotation.idForLifecycle(record.canonicalLifecycleKey),
    userId: widget.user.uid,
    accountId: record.accountKey,
    lifecycleKey: record.canonicalLifecycleKey,
    tradeId: record.id,
    mood: _emptyToNull(_controllers['mood']!.text),
    emotion: _emptyToNull(_controllers['emotion']!.text),
    psychologyNotes: _controllers['psychology']!.text.trim(),
    confidence: int.tryParse(_controllers['confidence']!.text.trim()),
    discipline: int.tryParse(_controllers['discipline']!.text.trim()),
    followedTradingPlan: _followedPlan,
    mistakeReason: _emptyToNull(_controllers['mistake']!.text),
    lessonLearned: _controllers['lesson']!.text.trim(),
    entryReason: _controllers['entry']!.text.trim(),
    exitReview: _controllers['exit']!.text.trim(),
    strategyId: _emptyToNull(_controllers['strategy']!.text),
    setup: _controllers['setup']!.text.trim(),
    notes: _controllers['notes']!.text.trim(),
    screenshotUrl: _emptyToNull(_controllers['screenshot']!.text),
    attachmentUrls: _annotation?.attachmentUrls ?? const [],
    customTags: _controllers['tags']!.text
        .split(',')
        .map((value) => value.trim())
        .where((value) => value.isNotEmpty)
        .toSet()
        .toList(),
  );

  Future<void> _save() async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      final value = _value();
      await widget.repository.saveMt5JournalAnnotation(value);
      _annotation = value;
      if (mounted) AppFeedback.success(context, 'MT5 journal notes saved.');
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _attach() async {
    final selected = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
      withData: true,
    );
    if (selected == null || selected.files.isEmpty || !mounted) return;
    setState(() => _uploading = true);
    try {
      final file = selected.files.single;
      final value = _value();
      final url = await widget.repository.uploadMt5JournalAttachment(
        annotation: value,
        file: ApiUploadFile(
          field: 'attachment',
          filename: file.name,
          path: file.path,
          bytes: file.bytes,
        ),
      );
      _annotation = Mt5JournalAnnotation.fromJson({
        ...value.toJson(),
        'id': value.id,
        'attachment_urls': [...value.attachmentUrls, url],
      });
      if (mounted) AppFeedback.success(context, 'Attachment uploaded.');
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Widget _field(String key, String label, {int lines = 1}) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: TextField(
      controller: _controllers[key],
      minLines: lines,
      maxLines: lines == 1 ? 1 : lines + 2,
      keyboardType: const {'confidence', 'discipline'}.contains(key)
          ? TextInputType.number
          : TextInputType.multiline,
      decoration: InputDecoration(labelText: context.l10n.text(label)),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(18, 0, 18, bottom + 22),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    '${record.symbol} ${context.l10n.text(_titleCase(record.tradeType))}',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                _StatusChip(
                  label: context.l10n.text(_titleCase(record.status)),
                  color: record.isOpen
                      ? Colors.blue
                      : record.isPending
                      ? Colors.orange
                      : Colors.green,
                ),
              ],
            ),
            const SizedBox(height: 16),
            _SectionTitle(title: 'MT5 Details', icon: Icons.sync_alt_outlined),
            const SizedBox(height: 10),
            _Mt5DetailRow('Account Number', '${record.mt5Login}'),
            _Mt5DetailRow('Broker Server', record.brokerServer),
            _Mt5DetailRow('Record Type', _titleCase(record.recordType)),
            _Mt5DetailRow(
              'Order Ticket',
              record.orderTicket?.toString() ?? '-',
            ),
            _Mt5DetailRow('Deal Ticket', record.dealTicket?.toString() ?? '-'),
            _Mt5DetailRow('Position ID', record.positionId?.toString() ?? '-'),
            _Mt5DetailRow('Lot Size', record.lotSize.toStringAsFixed(2)),
            _Mt5DetailRow('Entry Price', record.entryPrice.toStringAsFixed(2)),
            _Mt5DetailRow(
              'Current/Close Price',
              (record.closePrice == 0 ? record.currentPrice : record.closePrice)
                  .toStringAsFixed(2),
            ),
            _Mt5DetailRow(
              'SL',
              record.sl == 0 ? '-' : record.sl.toStringAsFixed(2),
            ),
            _Mt5DetailRow(
              'TP',
              record.tp == 0 ? '-' : record.tp.toStringAsFixed(2),
            ),
            _Mt5DetailRow('Profit', record.profit.toStringAsFixed(2)),
            _Mt5DetailRow('Commission', record.commission.toStringAsFixed(2)),
            _Mt5DetailRow('Swap', record.swap.toStringAsFixed(2)),
            _Mt5DetailRow(
              'Open Time',
              record.openTime == null ? '-' : formatDateTime(record.openTime!),
            ),
            _Mt5DetailRow(
              'Close Time',
              record.closeTime == null
                  ? '-'
                  : formatDateTime(record.closeTime!),
            ),
            _Mt5DetailRow('Source', 'MT5 Synced'),
            _Mt5DetailRow(
              'Last updated',
              record.lastSeenAt == null
                  ? '-'
                  : formatDateTime(record.lastSeenAt!),
            ),
            _Mt5DetailRow(
              'Last sync time',
              record.syncedAt == null ? '-' : formatDateTime(record.syncedAt!),
            ),
            if (record.comment.isNotEmpty)
              _Mt5DetailRow('Comment', record.comment),
            const SizedBox(height: 18),
            _SectionTitle(title: 'Journal', icon: Icons.edit_note_outlined),
            const SizedBox(height: 10),
            if (_loading)
              const Center(child: CircularProgressIndicator())
            else ...[
              _field('mood', 'Mood'),
              _field('emotion', 'Emotion'),
              _field('psychology', 'Psychology notes', lines: 2),
              Row(
                children: [
                  Expanded(child: _field('confidence', 'Confidence')),
                  const SizedBox(width: 10),
                  Expanded(child: _field('discipline', 'Discipline')),
                ],
              ),
              DropdownButtonFormField<bool?>(
                initialValue: _followedPlan,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Followed Trading Plan'),
                ),
                items: [
                  DropdownMenuItem(
                    value: null,
                    child: Text(context.l10n.text('Not specified')),
                  ),
                  DropdownMenuItem(
                    value: true,
                    child: Text(context.l10n.text('Yes')),
                  ),
                  DropdownMenuItem(
                    value: false,
                    child: Text(context.l10n.text('No')),
                  ),
                ],
                onChanged: (value) => setState(() => _followedPlan = value),
              ),
              const SizedBox(height: 10),
              _field('mistake', 'Mistake reason', lines: 2),
              _field('lesson', 'Lesson learned', lines: 2),
              _field('entry', 'Entry reason', lines: 2),
              _field('exit', 'Exit review', lines: 2),
              _field('strategy', 'Strategy'),
              _field('setup', 'Setup'),
              _field('tags', 'Tags'),
              _field('notes', 'Personal notes', lines: 3),
              _field('screenshot', 'Screenshot URL'),
              Wrap(
                spacing: 10,
                runSpacing: 10,
                children: [
                  FilledButton.icon(
                    onPressed: _saving ? null : _save,
                    icon: _saving
                        ? const SizedBox.square(
                            dimension: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save_outlined),
                    label: Text(context.l10n.text('Save journal notes')),
                  ),
                  OutlinedButton.icon(
                    onPressed: _uploading ? null : _attach,
                    icon: _uploading
                        ? const SizedBox.square(
                            dimension: 16,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.attach_file),
                    label: Text(context.l10n.text('Add attachment')),
                  ),
                ],
              ),
              if ((_annotation?.attachmentUrls.length ?? 0) > 0) ...[
                const SizedBox(height: 8),
                Text(
                  context.l10n.text('{count} attachment(s)', {
                    'count': _annotation!.attachmentUrls.length,
                  }),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }

  String? _emptyToNull(String value) {
    final text = value.trim();
    return text.isEmpty ? null : text;
  }
}

class _Mt5DetailRow extends StatelessWidget {
  const _Mt5DetailRow(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 142,
            child: Text(
              context.l10n.text(label),
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(child: Text(context.l10n.text(value))),
        ],
      ),
    );
  }
}

String _titleCase(String value) {
  if (value.isEmpty) return value;
  return value
      .split(RegExp(r'[_\s]+'))
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.icon});

  final String title;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 20),
        const SizedBox(width: 8),
        Text(
          context.l10n.text(title),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
      ],
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.icon, required this.label, this.color});

  final IconData icon;
  final String label;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final tone = color ?? Theme.of(context).colorScheme.onPrimaryContainer;

    return Container(
      height: 34,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surface.withValues(alpha: .72),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: tone),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: tone,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 30,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
          color: color,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _JournalFigure extends StatelessWidget {
  const _JournalFigure({
    required this.label,
    required this.value,
    this.color,
    this.alignEnd = false,
  });

  final String label;
  final String value;
  final Color? color;
  final bool alignEnd;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            color: color,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    );
  }
}
