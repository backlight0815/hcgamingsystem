import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/stock_market.dart';
import '../../repositories/stock_repository.dart';

class FinancialReportAdminScreen extends StatelessWidget {
  const FinancialReportAdminScreen({super.key, required this.repository});

  final StockRepository repository;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.text('Financial Daily Reports'))),
    body: AdminFinancialReportsPanel(repository: repository),
  );
}

class AdminFinancialReportsPanel extends StatefulWidget {
  const AdminFinancialReportsPanel({super.key, required this.repository});

  final StockRepository repository;

  @override
  State<AdminFinancialReportsPanel> createState() =>
      _AdminFinancialReportsPanelState();
}

class _AdminFinancialReportsPanelState
    extends State<AdminFinancialReportsPanel> {
  FinancialReportStatus? _filter;
  String _query = '';

  @override
  Widget build(
    BuildContext context,
  ) => StreamBuilder<List<FinancialDailyReport>>(
    stream: widget.repository.adminReports(),
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return _ReportAdminError(
          message: context.l10n.text(
            'Financial report administration is temporarily unavailable.',
          ),
        );
      }
      if (!snapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      final all = snapshot.data!;
      final query = _query.trim().toLowerCase();
      final visible = all.where((report) {
        if (_filter != null && report.status != _filter) return false;
        return query.isEmpty ||
            report.symbol.toLowerCase().contains(query) ||
            report.companyName.toLowerCase().contains(query) ||
            report.title.values.any(
              (value) => value.toLowerCase().contains(query),
            );
      }).toList();
      return Scaffold(
        body: RefreshIndicator(
          onRefresh: () async =>
              Future<void>.delayed(const Duration(milliseconds: 250)),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
            children: [
              Text(
                context.l10n.text('Stock report publishing centre'),
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                context.l10n.text(
                  'Review generated drafts, verify sources, and publish only approved reports.',
                ),
              ),
              const SizedBox(height: 16),
              _ReportSummary(reports: all),
              const SizedBox(height: 16),
              LayoutBuilder(
                builder: (context, constraints) => Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    SizedBox(
                      width: constraints.maxWidth >= 650
                          ? constraints.maxWidth - 230
                          : constraints.maxWidth,
                      child: SearchBar(
                        leading: const Icon(Icons.search),
                        hintText: context.l10n.text(
                          'Search symbol, company or title',
                        ),
                        onChanged: (value) => setState(() => _query = value),
                      ),
                    ),
                    SizedBox(
                      width: constraints.maxWidth >= 650
                          ? 210
                          : constraints.maxWidth,
                      child: DropdownButtonFormField<FinancialReportStatus?>(
                        initialValue: _filter,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Status'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: null,
                            child: Text(context.l10n.text('All statuses')),
                          ),
                          for (final status in FinancialReportStatus.values)
                            DropdownMenuItem(
                              value: status,
                              child: Text(
                                context.l10n.text(_statusLabel(status)),
                              ),
                            ),
                        ],
                        onChanged: (value) => setState(() => _filter = value),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              if (visible.isEmpty)
                _ReportAdminEmpty(
                  message: context.l10n.text(
                    'No reports match the current filters.',
                  ),
                )
              else
                LayoutBuilder(
                  builder: (context, constraints) {
                    final columns = constraints.maxWidth >= 980 ? 2 : 1;
                    if (columns == 1) {
                      return Column(
                        children: [
                          for (final report in visible)
                            _AdminReportCard(
                              report: report,
                              onEdit: () => _edit(report),
                              onStatus: (status) =>
                                  _changeStatus(report, status),
                            ),
                        ],
                      );
                    }
                    return GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: visible.length,
                      gridDelegate:
                          const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            mainAxisExtent: 240,
                            crossAxisSpacing: 12,
                            mainAxisSpacing: 12,
                          ),
                      itemBuilder: (context, index) => _AdminReportCard(
                        report: visible[index],
                        onEdit: () => _edit(visible[index]),
                        onStatus: (status) =>
                            _changeStatus(visible[index], status),
                      ),
                    );
                  },
                ),
            ],
          ),
        ),
        floatingActionButton: FloatingActionButton.extended(
          onPressed: () => _edit(null),
          icon: const Icon(Icons.add),
          label: Text(context.l10n.text('Create report')),
        ),
      );
    },
  );

  Future<void> _edit(FinancialDailyReport? report) async {
    await showDialog<void>(
      context: context,
      builder: (_) =>
          _FinancialReportEditor(repository: widget.repository, report: report),
    );
  }

  Future<void> _changeStatus(
    FinancialDailyReport report,
    FinancialReportStatus status,
  ) async {
    final publishing = status == FinancialReportStatus.published;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          context.l10n.text(
            publishing ? 'Publish this report?' : 'Update report status?',
          ),
        ),
        content: Text(
          context.l10n.text(
            publishing
                ? 'Publishing makes this report visible and can notify subscribed watchlists.'
                : 'This workflow change is recorded in the audit log.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Confirm')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await widget.repository.reviewReport(
        symbol: report.symbol,
        reportId: report.id,
        status: status,
        expectedVersion: report.version,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.l10n.text('Report workflow updated.'))),
      );
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.l10n.text(
              'The report could not be updated. Refresh and try again.',
            ),
          ),
        ),
      );
    }
  }
}

class _ReportSummary extends StatelessWidget {
  const _ReportSummary({required this.reports});

  final List<FinancialDailyReport> reports;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 10,
    runSpacing: 10,
    children: [
      _SummaryTile(
        label: context.l10n.text('Draft'),
        value: reports
            .where((report) => report.status == FinancialReportStatus.draft)
            .length,
        icon: Icons.edit_note_outlined,
      ),
      _SummaryTile(
        label: context.l10n.text('Under review'),
        value: reports
            .where(
              (report) => report.status == FinancialReportStatus.underReview,
            )
            .length,
        icon: Icons.rate_review_outlined,
      ),
      _SummaryTile(
        label: context.l10n.text('Scheduled'),
        value: reports
            .where((report) => report.status == FinancialReportStatus.scheduled)
            .length,
        icon: Icons.schedule_outlined,
      ),
      _SummaryTile(
        label: context.l10n.text('Published'),
        value: reports
            .where((report) => report.status == FinancialReportStatus.published)
            .length,
        icon: Icons.public,
      ),
    ],
  );
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final int value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 170,
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            Icon(icon),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('$value', style: Theme.of(context).textTheme.titleLarge),
                  Text(label),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _AdminReportCard extends StatelessWidget {
  const _AdminReportCard({
    required this.report,
    required this.onEdit,
    required this.onStatus,
  });

  final FinancialDailyReport report;
  final VoidCallback onEdit;
  final ValueChanged<FinancialReportStatus> onStatus;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Chip(label: Text(report.symbol)),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(DateFormat.yMMMd().format(report.reportDate)),
                ),
                PopupMenuButton<Object>(
                  tooltip: context.l10n.text('Report actions'),
                  onSelected: (value) {
                    if (value == 'edit') return onEdit();
                    onStatus(value as FinancialReportStatus);
                  },
                  itemBuilder: (_) => [
                    PopupMenuItem(
                      value: 'edit',
                      child: Text(context.l10n.text('Edit draft')),
                    ),
                    if (report.status == FinancialReportStatus.draft)
                      PopupMenuItem(
                        value: FinancialReportStatus.underReview,
                        child: Text(context.l10n.text('Submit for review')),
                      ),
                    if (report.status == FinancialReportStatus.underReview ||
                        report.status == FinancialReportStatus.scheduled)
                      PopupMenuItem(
                        value: FinancialReportStatus.published,
                        child: Text(context.l10n.text('Publish')),
                      ),
                    if (report.status == FinancialReportStatus.underReview)
                      PopupMenuItem(
                        value: FinancialReportStatus.rejected,
                        child: Text(context.l10n.text('Reject')),
                      ),
                    if (report.status != FinancialReportStatus.archived)
                      PopupMenuItem(
                        value: FinancialReportStatus.archived,
                        child: Text(context.l10n.text('Archive')),
                      ),
                  ],
                ),
              ],
            ),
            Text(
              report.localizedTitle(language),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Text(
              report.localizedSummary(language),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                Chip(
                  label: Text(context.l10n.text(_statusLabel(report.status))),
                ),
                Chip(
                  avatar: const Icon(Icons.link, size: 15),
                  label: Text('${report.sourceReferences.length}'),
                ),
                Chip(label: Text('v${report.version}')),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _FinancialReportEditor extends StatefulWidget {
  const _FinancialReportEditor({required this.repository, this.report});

  final StockRepository repository;
  final FinancialDailyReport? report;

  @override
  State<_FinancialReportEditor> createState() => _FinancialReportEditorState();
}

class _FinancialReportEditorState extends State<_FinancialReportEditor> {
  final _form = GlobalKey<FormState>();
  late final TextEditingController _symbol;
  late final TextEditingController _company;
  late final TextEditingController _titleEn;
  late final TextEditingController _titleZh;
  late final TextEditingController _summaryEn;
  late final TextEditingController _summaryZh;
  late final TextEditingController _contentEn;
  late final TextEditingController _contentZh;
  late final TextEditingController _sources;
  late DateTime _date;
  var _saving = false;

  @override
  void initState() {
    super.initState();
    final report = widget.report;
    _symbol = TextEditingController(text: report?.symbol ?? '');
    _company = TextEditingController(text: report?.companyName ?? '');
    _titleEn = TextEditingController(text: report?.title['en'] ?? '');
    _titleZh = TextEditingController(text: report?.title['zh'] ?? '');
    _summaryEn = TextEditingController(text: report?.summary['en'] ?? '');
    _summaryZh = TextEditingController(text: report?.summary['zh'] ?? '');
    _contentEn = TextEditingController(text: report?.content['en'] ?? '');
    _contentZh = TextEditingController(text: report?.content['zh'] ?? '');
    _sources = TextEditingController(
      text: report?.sourceReferences.join('\n') ?? '',
    );
    _date = report?.reportDate ?? DateTime.now();
  }

  @override
  void dispose() {
    for (final controller in [
      _symbol,
      _company,
      _titleEn,
      _titleZh,
      _summaryEn,
      _summaryZh,
      _contentEn,
      _contentZh,
      _sources,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(
      context.l10n.text(
        widget.report == null ? 'Create report draft' : 'Edit report draft',
      ),
    ),
    content: SizedBox(
      width: 760,
      child: Form(
        key: _form,
        child: SingleChildScrollView(
          child: Column(
            children: [
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _symbol,
                      enabled: widget.report == null && !_saving,
                      textCapitalization: TextCapitalization.characters,
                      decoration: InputDecoration(
                        labelText: context.l10n.text('Symbol'),
                      ),
                      validator: _required,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    flex: 2,
                    child: TextFormField(
                      controller: _company,
                      decoration: InputDecoration(
                        labelText: context.l10n.text('Company name'),
                      ),
                      validator: _required,
                    ),
                  ),
                ],
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Report date')),
                subtitle: Text(DateFormat.yMMMd().format(_date)),
                trailing: const Icon(Icons.calendar_month_outlined),
                onTap: _saving ? null : _pickDate,
              ),
              _LanguageFields(
                language: context.l10n.text('English'),
                title: _titleEn,
                summary: _summaryEn,
                content: _contentEn,
              ),
              const SizedBox(height: 14),
              _LanguageFields(
                language: context.l10n.text('Simplified Chinese'),
                title: _titleZh,
                summary: _summaryZh,
                content: _contentZh,
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _sources,
                minLines: 3,
                maxLines: 6,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Source references'),
                  helperText: context.l10n.text('One source per line'),
                ),
                validator: _required,
              ),
            ],
          ),
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: _saving ? null : () => Navigator.pop(context),
        child: Text(context.l10n.text('Cancel')),
      ),
      FilledButton(
        onPressed: _saving ? null : _save,
        child: Text(context.l10n.text(_saving ? 'Saving...' : 'Save draft')),
      ),
    ],
  );

  String? _required(String? value) =>
      (value ?? '').trim().isEmpty ? context.l10n.text('Required') : null;

  Future<void> _pickDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (selected != null) setState(() => _date = selected);
  }

  Future<void> _save() async {
    if (_form.currentState?.validate() != true) return;
    if (_titleEn.text.trim().isEmpty && _titleZh.text.trim().isEmpty) {
      _message('Add a title in English or Chinese.');
      return;
    }
    if (_contentEn.text.trim().isEmpty && _contentZh.text.trim().isEmpty) {
      _message('Add content in English or Chinese.');
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.repository.saveReportDraft(
        symbol: _symbol.text,
        companyName: _company.text,
        reportDate: _date,
        titleEn: _titleEn.text,
        titleZh: _titleZh.text,
        summaryEn: _summaryEn.text,
        summaryZh: _summaryZh.text,
        contentEn: _contentEn.text,
        contentZh: _contentZh.text,
        sourceReferences: _sources.text.split(RegExp(r'[\r\n]+')),
        reportId: widget.report?.id,
        expectedVersion: widget.report?.version,
      );
      if (mounted) Navigator.pop(context);
    } catch (_) {
      if (mounted) {
        setState(() => _saving = false);
        _message('Unable to save this report draft.');
      }
    }
  }

  void _message(String value) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(context.l10n.text(value))));
}

class _LanguageFields extends StatelessWidget {
  const _LanguageFields({
    required this.language,
    required this.title,
    required this.summary,
    required this.content,
  });

  final String language;
  final TextEditingController title;
  final TextEditingController summary;
  final TextEditingController content;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(language, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          TextFormField(
            controller: title,
            decoration: InputDecoration(labelText: context.l10n.text('Title')),
          ),
          const SizedBox(height: 10),
          TextFormField(
            controller: summary,
            maxLines: 2,
            decoration: InputDecoration(
              labelText: context.l10n.text('Summary'),
            ),
          ),
          const SizedBox(height: 10),
          TextFormField(
            controller: content,
            minLines: 6,
            maxLines: 14,
            decoration: InputDecoration(
              labelText: context.l10n.text('Report content'),
            ),
          ),
        ],
      ),
    ),
  );
}

class _ReportAdminEmpty extends StatelessWidget {
  const _ReportAdminEmpty({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 60),
    child: Column(
      children: [
        const Icon(Icons.article_outlined, size: 52),
        const SizedBox(height: 12),
        Text(message),
      ],
    ),
  );
}

class _ReportAdminError extends StatelessWidget {
  const _ReportAdminError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Text(message, textAlign: TextAlign.center),
    ),
  );
}

String _statusLabel(FinancialReportStatus status) => switch (status) {
  FinancialReportStatus.underReview => 'Under review',
  _ => '${status.name[0].toUpperCase()}${status.name.substring(1)}',
};
