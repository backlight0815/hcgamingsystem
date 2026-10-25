import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import '../../core/localization/app_localizations.dart';
import '../../models/mt5_import_models.dart';
import '../../models/mt5_sync_models.dart';
import '../../services/mt5_import/mt5_import_coordinator.dart';
import '../../services/mt5_import/mt5_rejected_row_exporter.dart';
import '../../shared/app_back_button.dart';
import '../../shared/app_feedback.dart';

class Mt5ExcelImportScreen extends StatefulWidget {
  const Mt5ExcelImportScreen({
    super.key,
    required this.bytes,
    required this.filename,
    required this.accounts,
    this.coordinator,
  });

  final Uint8List bytes;
  final String filename;
  final List<Mt5Account> accounts;
  final Mt5ImportCoordinator? coordinator;

  @override
  State<Mt5ExcelImportScreen> createState() => _Mt5ExcelImportScreenState();
}

class _Mt5ExcelImportScreenState extends State<Mt5ExcelImportScreen> {
  late final Mt5ImportCoordinator _coordinator;
  Mt5ImportPreview? _preview;
  Mt5Account? _selectedAccount;
  Mt5ImportCommitResult? _result;
  Mt5ImportStage _stage = Mt5ImportStage.reading;
  String _error = '';
  var _loadingDuplicates = false;

  @override
  void initState() {
    super.initState();
    _coordinator = widget.coordinator ?? Mt5ImportCoordinator();
    _parse();
  }

  Future<void> _parse() async {
    setState(() {
      _stage = Mt5ImportStage.reading;
      _error = '';
    });
    try {
      final parsed = await _coordinator.parseWorkbook(
        bytes: widget.bytes,
        filename: widget.filename,
      );
      if (!mounted) return;
      final exact = widget.accounts.where(
        (account) => _coordinator.matchAccount(parsed, account).matches,
      );
      final selected = exact.isNotEmpty
          ? exact.first
          : widget.accounts.where((account) => account.isPrimary).firstOrNull ??
                widget.accounts.firstOrNull;
      setState(() {
        _preview = selected == null
            ? parsed.copyWith(
                issues: [
                  ...parsed.issues,
                  const Mt5ValidationIssue(
                    code: 'mt5_connection_required',
                    messageKey:
                        'Connect the matching MT5 account before importing this report.',
                    severity: Mt5ValidationSeverity.blocking,
                  ),
                ],
              )
            : _coordinator.applySelectedAccount(parsed, selected);
        _selectedAccount = selected;
        _stage = Mt5ImportStage.readyForPreview;
      });
      if (selected != null) await _loadDuplicatePreview();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _stage = Mt5ImportStage.failed;
        _error = error.toString();
      });
    }
  }

  Future<void> _selectAccount(Mt5Account? account) async {
    final current = _preview;
    if (account == null || current == null) return;
    setState(() {
      _selectedAccount = account;
      _preview = _coordinator.applySelectedAccount(current, account);
      _error = '';
    });
    await _loadDuplicatePreview();
  }

  Future<void> _loadDuplicatePreview() async {
    final preview = _preview;
    final account = _selectedAccount;
    if (preview == null || account == null || preview.hasBlockingErrors) return;
    setState(() => _loadingDuplicates = true);
    try {
      final updated = await _coordinator.loadDuplicatePreview(
        preview: preview,
        account: account,
      );
      if (mounted) setState(() => _preview = updated);
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _loadingDuplicates = false);
    }
  }

  Future<void> _commit() async {
    final preview = _preview;
    final account = _selectedAccount;
    if (preview == null || account == null || preview.hasBlockingErrors) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Import verified MT5 records?')),
        content: Text(
          context.l10n.text(
            'Only the selected MT5 account will be updated. Deposits and withdrawals remain separate from trading profit.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.l10n.text('Import records')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() {
      _stage = Mt5ImportStage.committing;
      _error = '';
    });
    try {
      final result = await _coordinator.commit(
        preview: preview,
        account: account,
      );
      if (!mounted) return;
      setState(() {
        _result = result;
        _stage = result.status == 'completed_with_warnings'
            ? Mt5ImportStage.completedWithWarnings
            : Mt5ImportStage.completed;
      });
      AppFeedback.success(context, 'MT5 import completed.');
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _stage = Mt5ImportStage.failed;
        _error = error.toString();
      });
    }
  }

  Future<void> _exportRejectedRows() async {
    final preview = _preview;
    if (preview == null || preview.rejectedRows.isEmpty) return;
    try {
      final csv = _coordinator.rejectedRowsCsv(preview);
      final base = widget.filename.replaceFirst(RegExp(r'\.[^.]+$'), '');
      final saved = await saveMt5RejectedRows(
        filename: '${base}_rejected_rows.csv',
        bytes: Uint8List.fromList(utf8.encode(csv)),
      );
      if (saved && mounted) {
        AppFeedback.success(context, 'Rejected-row report exported.');
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final preview = _preview;
    final busy = const {
      Mt5ImportStage.reading,
      Mt5ImportStage.detectingFormat,
      Mt5ImportStage.parsing,
      Mt5ImportStage.validating,
      Mt5ImportStage.reconciling,
      Mt5ImportStage.committing,
    }.contains(_stage);
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('MT5 Desktop Excel Import')),
      ),
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1180),
            child: Column(
              children: [
                if (busy || _loadingDuplicates) const LinearProgressIndicator(),
                _StageHeader(stage: _stage),
                Expanded(
                  child: preview == null
                      ? _LoadingOrError(error: _error, onRetry: _parse)
                      : ListView(
                          keyboardDismissBehavior:
                              ScrollViewKeyboardDismissBehavior.onDrag,
                          padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
                          children: [
                            if (_error.isNotEmpty)
                              _ErrorCard(
                                message: _error,
                                onRetry: _loadDuplicatePreview,
                              ),
                            _FileCard(preview: preview),
                            const SizedBox(height: 12),
                            _AccountCard(
                              preview: preview,
                              accounts: widget.accounts,
                              selected: _selectedAccount,
                              onChanged: busy ? null : _selectAccount,
                            ),
                            const SizedBox(height: 12),
                            _MappingCard(preview: preview),
                            const SizedBox(height: 12),
                            _FinancialCard(preview: preview),
                            const SizedBox(height: 12),
                            _DuplicateCard(
                              preview: preview,
                              loading: _loadingDuplicates,
                            ),
                            const SizedBox(height: 12),
                            _ValidationCard(
                              preview: preview,
                              onExportRejected: preview.rejectedRows.isEmpty
                                  ? null
                                  : _exportRejectedRows,
                            ),
                            if (_result != null) ...[
                              const SizedBox(height: 12),
                              _CompletionCard(result: _result!),
                            ],
                            const SizedBox(height: 20),
                            Semantics(
                              button: true,
                              label: context.l10n.text(
                                'Import verified MT5 records',
                              ),
                              child: FilledButton.icon(
                                onPressed:
                                    busy ||
                                        _loadingDuplicates ||
                                        preview.hasBlockingErrors ||
                                        _selectedAccount == null ||
                                        preview
                                            .duplicateSummary
                                            .sameFileAlreadyImported ||
                                        _result != null
                                    ? null
                                    : _commit,
                                icon: busy
                                    ? const SizedBox.square(
                                        dimension: 18,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : const Icon(Icons.cloud_upload_outlined),
                                label: Text(
                                  context.l10n.text(
                                    preview
                                            .duplicateSummary
                                            .sameFileAlreadyImported
                                        ? 'This file was already imported'
                                        : 'Import verified MT5 records',
                                  ),
                                ),
                              ),
                            ),
                          ],
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

class _StageHeader extends StatelessWidget {
  const _StageHeader({required this.stage});
  final Mt5ImportStage stage;

  @override
  Widget build(BuildContext context) {
    final label = switch (stage) {
      Mt5ImportStage.reading => 'Reading workbook safely',
      Mt5ImportStage.detectingFormat => 'Detecting MT5 report format',
      Mt5ImportStage.parsing ||
      Mt5ImportStage.normalizing => 'Normalizing MT5 records',
      Mt5ImportStage.validating ||
      Mt5ImportStage.reconciling => 'Validating financial totals',
      Mt5ImportStage.readyForPreview => 'Ready for review',
      Mt5ImportStage.committing => 'Committing staged import',
      Mt5ImportStage.completed => 'Import completed',
      Mt5ImportStage.completedWithWarnings => 'Import completed with warnings',
      Mt5ImportStage.failed => 'Import needs attention',
      _ => 'MT5 import',
    };
    return Semantics(
      liveRegion: true,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Icon(
              const {
                    Mt5ImportStage.completed,
                    Mt5ImportStage.completedWithWarnings,
                  }.contains(stage)
                  ? Icons.check_circle_outline
                  : stage == Mt5ImportStage.failed
                  ? Icons.error_outline
                  : Icons.table_view_outlined,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                context.l10n.text(label),
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FileCard extends StatelessWidget {
  const _FileCard({required this.preview});
  final Mt5ImportPreview preview;

  @override
  Widget build(BuildContext context) => _ImportCard(
    title: 'Selected workbook',
    icon: Icons.description_outlined,
    child: Wrap(
      spacing: 24,
      runSpacing: 8,
      children: [
        _LabelValue(label: 'Filename', value: preview.filename),
        _LabelValue(label: 'File size', value: _bytes(preview.fileSize)),
        _LabelValue(label: 'File type', value: 'Excel Open XML (.xlsx)'),
        _LabelValue(
          label: 'Secure file hash',
          value: '${preview.fileHash.substring(0, 16)}…',
        ),
      ],
    ),
  );
}

class _AccountCard extends StatelessWidget {
  const _AccountCard({
    required this.preview,
    required this.accounts,
    required this.selected,
    required this.onChanged,
  });
  final Mt5ImportPreview preview;
  final List<Mt5Account> accounts;
  final Mt5Account? selected;
  final ValueChanged<Mt5Account?>? onChanged;

  @override
  Widget build(BuildContext context) {
    final matching =
        selected != null &&
        preview.metadata.accountNumber == selected!.login.toString() &&
        preview.metadata.brokerServer.trim().toLowerCase() ==
            selected!.brokerServer.trim().toLowerCase();
    return _ImportCard(
      title: 'Detected account',
      icon: Icons.account_balance_outlined,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 24,
            runSpacing: 8,
            children: [
              _LabelValue(
                label: 'Account number',
                value: preview.metadata.accountNumber,
              ),
              _LabelValue(
                label: 'Server',
                value: preview.metadata.brokerServer,
              ),
              _LabelValue(label: 'Currency', value: preview.metadata.currency),
              _LabelValue(
                label: 'Environment',
                value: preview.metadata.environment,
              ),
              _LabelValue(
                label: 'Account mode',
                value: preview.metadata.accountMode,
              ),
            ],
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<Mt5Account>(
            initialValue: selected,
            isExpanded: true,
            onChanged: onChanged,
            decoration: InputDecoration(
              labelText: context.l10n.text('Selected MT5 connection'),
              prefixIcon: const Icon(Icons.link_outlined),
            ),
            items: accounts
                .map(
                  (account) => DropdownMenuItem(
                    value: account,
                    child: Text(
                      '${account.login} · ${account.brokerServer} · ${account.currency}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                )
                .toList(growable: false),
          ),
          const SizedBox(height: 10),
          _StatusLine(
            good: matching,
            text: matching
                ? 'The report matches the selected MT5 connection.'
                : 'The report does not match the selected MT5 connection.',
          ),
        ],
      ),
    );
  }
}

class _MappingCard extends StatelessWidget {
  const _MappingCard({required this.preview});
  final Mt5ImportPreview preview;

  @override
  Widget build(BuildContext context) => _ImportCard(
    title: 'Mapping preview',
    icon: Icons.schema_outlined,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Wrap(
          spacing: 24,
          runSpacing: 8,
          children: [
            _LabelValue(label: 'Parser profile', value: preview.parserProfile),
            _LabelValue(
              label: 'Mapping confidence',
              value: '${(preview.formatConfidence * 100).toStringAsFixed(0)}%',
            ),
            _LabelValue(
              label: 'Worksheets',
              value: preview.sheetNames.join(', '),
            ),
            _LabelValue(
              label: 'Timezone source',
              value: preview.metadata.sourceTimezone.isEmpty
                  ? context.l10n.text('Not provided')
                  : preview.metadata.sourceTimezone,
            ),
          ],
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: preview.detectedSections
              .map(
                (section) => Chip(
                  avatar: const Icon(Icons.check, size: 17),
                  label: Text(context.l10n.text(_sectionLabel(section))),
                ),
              )
              .toList(growable: false),
        ),
      ],
    ),
  );
}

class _FinancialCard extends StatelessWidget {
  const _FinancialCard({required this.preview});
  final Mt5ImportPreview preview;

  @override
  Widget build(BuildContext context) {
    final summary = preview.financialSummary;
    final currency = preview.metadata.currency;
    String money(Mt5Decimal value) =>
        '${value.toDouble().toStringAsFixed(2)} ${currency.isEmpty ? '' : currency}'
            .trim();
    final metrics = <(String, String)>[
      ('Completed trades', '${preview.completedPositions}'),
      ('Open positions', '${preview.openPositions}'),
      ('Filled orders', '${preview.filledOrders}'),
      ('Pending orders', '${preview.pendingOrders}'),
      ('Cancelled / rejected orders', '${preview.inactiveOrders}'),
      ('Deals', '${preview.deals.length}'),
      ('Deposits', money(summary.deposits)),
      ('Withdrawals', money(summary.withdrawals)),
      ('Gross trading profit', money(summary.grossProfit)),
      ('Gross trading loss', money(summary.grossLoss)),
      ('Commission', money(summary.commission)),
      ('Fee', money(summary.fee)),
      ('Swap', money(summary.swap)),
      ('Net trading P/L', money(summary.netTradingPnl)),
      ('Net capital flow', money(summary.netCapitalFlow)),
      ('Source closing balance', money(summary.sourceClosingBalance)),
      ('Calculated closing balance', money(summary.calculatedClosingBalance)),
      ('Reconciliation difference', money(summary.reconciliationDifference)),
    ];
    return _ImportCard(
      title: 'Financial preview',
      icon: Icons.calculate_outlined,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          LayoutBuilder(
            builder: (context, constraints) {
              final width = constraints.maxWidth < 520
                  ? constraints.maxWidth
                  : (constraints.maxWidth - 24) / 3;
              return Wrap(
                spacing: 12,
                runSpacing: 12,
                children: metrics
                    .map(
                      (metric) => SizedBox(
                        width: width,
                        child: _Metric(label: metric.$1, value: metric.$2),
                      ),
                    )
                    .toList(growable: false),
              );
            },
          ),
          const SizedBox(height: 12),
          _StatusLine(
            good: summary.reconciled,
            text: summary.reconciled
                ? 'Closing balance reconciled within currency tolerance.'
                : 'Closing balance reconciliation failed.',
          ),
        ],
      ),
    );
  }
}

class _DuplicateCard extends StatelessWidget {
  const _DuplicateCard({required this.preview, required this.loading});
  final Mt5ImportPreview preview;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final duplicate = preview.duplicateSummary;
    return _ImportCard(
      title: 'Duplicate preview',
      icon: Icons.content_copy_outlined,
      child: loading
          ? const Center(child: CircularProgressIndicator())
          : Wrap(
              spacing: 24,
              runSpacing: 8,
              children: [
                _LabelValue(
                  label: 'New records',
                  value: '${duplicate.newRecords}',
                ),
                _LabelValue(
                  label: 'Existing identical records',
                  value: '${duplicate.existingIdentical}',
                ),
                _LabelValue(
                  label: 'Updated records',
                  value: '${duplicate.updatedRecords}',
                ),
                _LabelValue(
                  label: 'Conflicts',
                  value: '${duplicate.conflicts}',
                ),
                if (duplicate.sameFileAlreadyImported)
                  _LabelValue(
                    label: 'Previous import batch',
                    value: duplicate.previousBatchId,
                  ),
              ],
            ),
    );
  }
}

class _ValidationCard extends StatelessWidget {
  const _ValidationCard({
    required this.preview,
    required this.onExportRejected,
  });
  final Mt5ImportPreview preview;
  final VoidCallback? onExportRejected;

  @override
  Widget build(BuildContext context) {
    final blocking = preview.issues.where((issue) => issue.blocksCommit).length;
    final warnings = preview.issues
        .where((issue) => issue.severity == Mt5ValidationSeverity.warning)
        .length;
    return _ImportCard(
      title: 'Validation preview',
      icon: Icons.fact_check_outlined,
      child: Column(
        children: [
          Wrap(
            spacing: 24,
            runSpacing: 8,
            children: [
              _LabelValue(label: 'Blocking errors', value: '$blocking'),
              _LabelValue(label: 'Warnings', value: '$warnings'),
              _LabelValue(
                label: 'Rejected rows',
                value: '${preview.rejectedRows.length}',
              ),
              _LabelValue(
                label: 'Parsed source rows',
                value: '${preview.parsedRows}',
              ),
              _LabelValue(
                label: 'Ignored layout rows',
                value: '${preview.ignoredRows}',
              ),
            ],
          ),
          if (preview.issues.isNotEmpty) ...[
            const Divider(height: 24),
            for (final issue in preview.issues)
              ListTile(
                dense: true,
                contentPadding: EdgeInsets.zero,
                leading: Icon(
                  issue.blocksCommit
                      ? Icons.error_outline
                      : Icons.warning_amber_outlined,
                  color: issue.blocksCommit
                      ? Theme.of(context).colorScheme.error
                      : Theme.of(context).colorScheme.tertiary,
                ),
                title: Text(context.l10n.text(issue.messageKey)),
                subtitle: issue.reference == null
                    ? null
                    : Text(
                        '${issue.reference!.sheet} · ${context.l10n.text('Row')} ${issue.reference!.row}',
                      ),
              ),
          ],
          if (onExportRejected != null)
            Align(
              alignment: Alignment.centerLeft,
              child: OutlinedButton.icon(
                onPressed: onExportRejected,
                icon: const Icon(Icons.download_outlined),
                label: Text(context.l10n.text('Export rejected-row report')),
              ),
            ),
        ],
      ),
    );
  }
}

class _CompletionCard extends StatelessWidget {
  const _CompletionCard({required this.result});
  final Mt5ImportCommitResult result;

  @override
  Widget build(BuildContext context) => _ImportCard(
    title: 'Import summary',
    icon: Icons.task_alt,
    child: Wrap(
      spacing: 24,
      runSpacing: 8,
      children: [
        _LabelValue(label: 'Import batch ID', value: result.batchId),
        _LabelValue(label: 'Status', value: result.status),
        _LabelValue(label: 'Imported records', value: '${result.imported}'),
        _LabelValue(
          label: 'Existing records reused',
          value: '${result.reused}',
        ),
        _LabelValue(label: 'Updated records', value: '${result.updated}'),
        _LabelValue(label: 'Conflicts', value: '${result.conflicts}'),
        _LabelValue(
          label: 'Downstream recalculation',
          value: result.downstreamRecalculationStatus,
        ),
      ],
    ),
  );
}

class _ImportCard extends StatelessWidget {
  const _ImportCard({
    required this.title,
    required this.icon,
    required this.child,
  });
  final String title;
  final IconData icon;
  final Widget child;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  context.l10n.text(title),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    ),
  );
}

class _LabelValue extends StatelessWidget {
  const _LabelValue({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => ConstrainedBox(
    constraints: const BoxConstraints(minWidth: 130, maxWidth: 380),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text(label),
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 2),
        SelectableText(
          value.isEmpty ? context.l10n.text('Not provided') : value,
        ),
      ],
    ),
  );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.text(label),
            style: Theme.of(context).textTheme.labelMedium,
          ),
          const SizedBox(height: 4),
          Text(value, style: Theme.of(context).textTheme.titleMedium),
        ],
      ),
    ),
  );
}

class _StatusLine extends StatelessWidget {
  const _StatusLine({required this.good, required this.text});
  final bool good;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(
        good ? Icons.check_circle_outline : Icons.error_outline,
        color: good ? Colors.green : Theme.of(context).colorScheme.error,
      ),
      const SizedBox(width: 8),
      Expanded(child: Text(context.l10n.text(text))),
    ],
  );
}

class _LoadingOrError extends StatelessWidget {
  const _LoadingOrError({required this.error, required this.onRetry});
  final String error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: error.isEmpty
        ? const CircularProgressIndicator()
        : _ErrorCard(message: error, onRetry: onRetry),
  );
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.errorContainer,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          const Icon(Icons.error_outline),
          const SizedBox(width: 12),
          Expanded(child: Text(context.l10n.text(message))),
          TextButton(
            onPressed: onRetry,
            child: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );
}

String _sectionLabel(Mt5ImportSection section) => switch (section) {
  Mt5ImportSection.positions => 'Positions',
  Mt5ImportSection.orders => 'Orders',
  Mt5ImportSection.deals => 'Deals',
  Mt5ImportSection.results => 'Results',
  Mt5ImportSection.metadata => 'Metadata',
  Mt5ImportSection.totals => 'Account totals',
  Mt5ImportSection.unknown => 'Unknown',
};

String _bytes(int value) {
  if (value < 1024) return '$value B';
  if (value < 1024 * 1024) return '${(value / 1024).toStringAsFixed(1)} KB';
  return '${(value / (1024 * 1024)).toStringAsFixed(1)} MB';
}
