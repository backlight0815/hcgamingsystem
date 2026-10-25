import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/content_management.dart';
import '../../repositories/trading_repository.dart';

typedef AdminContentRecordLoader =
    Future<List<Map<String, dynamic>>> Function(String collection);

class ContentManagementScreen extends StatefulWidget {
  const ContentManagementScreen({
    super.key,
    required this.repository,
    this.loadRecords,
  });

  final TradingRepository repository;
  final AdminContentRecordLoader? loadRecords;

  @override
  State<ContentManagementScreen> createState() =>
      _ContentManagementScreenState();
}

class _ContentManagementScreenState extends State<ContentManagementScreen> {
  late Future<_ContentLoadResult> _future = _load();
  String _query = '';
  ManagedContentType? _type;
  ContentPublicationStatus? _status;
  _ContentSort _sort = _ContentSort.recentlyUpdated;

  Future<_ContentLoadResult> _load() async {
    final loader = widget.loadRecords ?? widget.repository.adminResourceRecords;
    final sources = await Future.wait([
      for (final definition in _contentCollections)
        _loadSource(definition, loader),
    ]);
    return _ContentLoadResult(
      items: [for (final source in sources) ...source.items],
      failures: [
        for (final source in sources)
          if (source.error != null)
            _ContentSourceFailure(
              collection: source.collection,
              error: source.error!,
            ),
      ],
      successfulSourceCount: sources
          .where((source) => source.error == null)
          .length,
    );
  }

  Future<_ContentSourceLoad> _loadSource(
    _ContentCollectionDefinition definition,
    AdminContentRecordLoader loader,
  ) async {
    try {
      final rows = await loader(definition.collection);
      return _ContentSourceLoad(
        collection: definition.collection,
        items: [
          for (final row in rows)
            _ManagedSource(
              collection: definition.collection,
              record: _recordFromRow(definition, row),
              raw: row,
            ),
        ],
      );
    } catch (error) {
      return _ContentSourceLoad(
        collection: definition.collection,
        items: const [],
        error: error,
      );
    }
  }

  Future<void> _refresh() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<_ContentLoadResult>(
    future: _future,
    builder: (context, snapshot) {
      if (snapshot.connectionState != ConnectionState.done) {
        return const _ContentLoading();
      }
      final result = snapshot.data;
      if (snapshot.hasError ||
          result == null ||
          (result.successfulSourceCount == 0 && result.failures.isNotEmpty)) {
        return _ContentState(
          icon: Icons.cloud_off_outlined,
          title: context.l10n.text('Content centre unavailable'),
          message: context.l10n.text(
            'Content could not be loaded. Verify your connection, administrator role, and deployed Firestore rules.',
          ),
          action: FilledButton.tonalIcon(
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
            label: Text(context.l10n.text('Try again')),
          ),
        );
      }
      final all = result.items;
      final query = _query.trim().toLowerCase();
      final visible = all.where((item) {
        final record = item.record;
        if (_type != null && record.type != _type) return false;
        if (_status != null && record.status != _status) return false;
        if (query.isEmpty) return true;
        return record.id.toLowerCase().contains(query) ||
            record.localized.titleEn.toLowerCase().contains(query) ||
            record.localized.titleZh.toLowerCase().contains(query) ||
            record.category.toLowerCase().contains(query) ||
            record.authorName.toLowerCase().contains(query) ||
            record.symbols.any(
              (symbol) => symbol.toLowerCase().contains(query),
            ) ||
            record.tags.any((tag) => tag.toLowerCase().contains(query));
      }).toList();
      _sortRows(visible, _sort);
      return Scaffold(
        body: RefreshIndicator(
          onRefresh: _refresh,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  context.l10n.text(
                                    'Content publishing centre',
                                  ),
                                  style: Theme.of(context)
                                      .textTheme
                                      .headlineSmall
                                      ?.copyWith(fontWeight: FontWeight.w900),
                                ),
                                Text(
                                  context.l10n.text(
                                    'Create, schedule, review and archive bilingual financial content.',
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          FilledButton.icon(
                            onPressed: () => _edit(null),
                            icon: const Icon(Icons.add),
                            label: Text(context.l10n.text('Create content')),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      if (result.failures.isNotEmpty) ...[
                        _PartialContentWarning(
                          failures: result.failures,
                          onRetry: _refresh,
                        ),
                        const SizedBox(height: 12),
                      ],
                      _ContentSummary(items: all),
                      const SizedBox(height: 16),
                      SearchBar(
                        leading: const Icon(Icons.search),
                        hintText: context.l10n.text(
                          'Search title, category, symbol, author or content ID',
                        ),
                        onChanged: (value) => setState(() => _query = value),
                      ),
                      const SizedBox(height: 10),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          _FilterDropdown<ManagedContentType?>(
                            label: context.l10n.text('Content type'),
                            value: _type,
                            items: [
                              DropdownMenuItem(
                                value: null,
                                child: Text(context.l10n.text('All types')),
                              ),
                              for (final type in ManagedContentType.values)
                                DropdownMenuItem(
                                  value: type,
                                  child: Text(
                                    context.l10n.text(_typeLabel(type)),
                                  ),
                                ),
                            ],
                            onChanged: (value) => setState(() => _type = value),
                          ),
                          _FilterDropdown<ContentPublicationStatus?>(
                            label: context.l10n.text('Status'),
                            value: _status,
                            items: [
                              DropdownMenuItem(
                                value: null,
                                child: Text(context.l10n.text('All statuses')),
                              ),
                              for (final status
                                  in ContentPublicationStatus.values)
                                DropdownMenuItem(
                                  value: status,
                                  child: Text(
                                    context.l10n.text(_statusLabel(status)),
                                  ),
                                ),
                            ],
                            onChanged: (value) =>
                                setState(() => _status = value),
                          ),
                          _FilterDropdown<_ContentSort>(
                            label: context.l10n.text('Sort'),
                            value: _sort,
                            items: [
                              for (final sort in _ContentSort.values)
                                DropdownMenuItem(
                                  value: sort,
                                  child: Text(
                                    context.l10n.text(_sortLabel(sort)),
                                  ),
                                ),
                            ],
                            onChanged: (value) {
                              if (value != null) setState(() => _sort = value);
                            },
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              if (visible.isEmpty)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: _ContentState(
                    icon: Icons.inventory_2_outlined,
                    title: context.l10n.text('No content found'),
                    message: context.l10n.text(
                      'Change the filters or create a new content item.',
                    ),
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                  sliver: SliverToBoxAdapter(
                    child: LayoutBuilder(
                      builder: (context, constraints) =>
                          constraints.maxWidth >= 920
                          ? _ContentTable(items: visible, onAction: _action)
                          : Column(
                              children: [
                                for (final item in visible)
                                  _ContentCard(
                                    item: item,
                                    onAction: (action) => _action(item, action),
                                  ),
                              ],
                            ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      );
    },
  );

  Future<void> _action(_ManagedSource item, _ContentAction action) async {
    switch (action) {
      case _ContentAction.preview:
        await _preview(item);
      case _ContentAction.edit:
        await _edit(item);
      case _ContentAction.duplicate:
        await _edit(
          _ManagedSource(
            collection: item.collection,
            record: item.record,
            raw: {...item.raw, 'id': null, 'status': 'draft'},
          ),
          duplicate: true,
        );
      case _ContentAction.publish:
        await _changeStatus(item, ContentPublicationStatus.published);
      case _ContentAction.unpublish:
        await _changeStatus(item, ContentPublicationStatus.unpublished);
      case _ContentAction.archive:
        await _archive(item);
      case _ContentAction.restore:
        await widget.repository.adminRestoreResource(
          collection: item.collection,
          id: item.record.id,
        );
        await _refresh();
    }
  }

  Future<void> _preview(_ManagedSource item) => showDialog<void>(
    context: context,
    builder: (dialogContext) {
      final language = Localizations.localeOf(context).languageCode;
      return AlertDialog(
        title: Text(item.record.localized.localizedTitle(language)),
        content: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 720),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Wrap(
                  spacing: 8,
                  children: [
                    Chip(
                      label: Text(
                        context.l10n.text(_typeLabel(item.record.type)),
                      ),
                    ),
                    Chip(
                      label: Text(
                        context.l10n.text(_statusLabel(item.record.status)),
                      ),
                    ),
                  ],
                ),
                if (item.record.localized
                    .localizedSummary(language)
                    .isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Text(
                    item.record.localized.localizedSummary(language),
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                ],
                const SizedBox(height: 16),
                SelectableText(
                  item.record.localized.localizedContent(language),
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(context.l10n.text('Close')),
          ),
        ],
      );
    },
  );

  Future<void> _edit(_ManagedSource? item, {bool duplicate = false}) async {
    final changed = await showDialog<bool>(
      context: context,
      builder: (_) => _ContentEditor(
        repository: widget.repository,
        item: item,
        duplicate: duplicate,
      ),
    );
    if (changed == true) await _refresh();
  }

  Future<void> _changeStatus(
    _ManagedSource item,
    ContentPublicationStatus status,
  ) async {
    final publishing = status == ContentPublicationStatus.published;
    if (publishing) {
      final errors = item.record.localized.validateForPublish(
        requireContent:
            item.record.type != ManagedContentType.video &&
            item.record.type != ManagedContentType.recording,
      );
      if (errors.isNotEmpty) {
        _message(errors.first);
        return;
      }
    }
    await widget.repository.adminSaveResource(
      collection: item.collection,
      id: item.record.id,
      values: {
        ...item.raw,
        'status': _booleanStatusCollection(item.collection)
            ? publishing
            : status.name,
        'publication_status': status.name,
      },
    );
    await _refresh();
  }

  Future<void> _archive(_ManagedSource item) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Archive content?')),
        content: Text(
          context.l10n.text(
            'The content will be hidden but retained for audit and restoration.',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Archive')),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await widget.repository.adminArchiveResource(
      collection: item.collection,
      id: item.record.id,
    );
    await _refresh();
  }

  void _message(String value) => ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(context.l10n.text(value))));
}

class _ContentSummary extends StatelessWidget {
  const _ContentSummary({required this.items});

  final List<_ManagedSource> items;

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final weekStart = now.subtract(Duration(days: now.weekday - 1));
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        _SummaryTile(
          'Total content',
          items.length,
          Icons.library_books_outlined,
        ),
        _SummaryTile(
          'Published',
          items
              .where(
                (item) =>
                    item.record.status == ContentPublicationStatus.published,
              )
              .length,
          Icons.public,
        ),
        _SummaryTile(
          'Draft',
          items
              .where(
                (item) => item.record.status == ContentPublicationStatus.draft,
              )
              .length,
          Icons.edit_note_outlined,
        ),
        _SummaryTile(
          'Scheduled',
          items
              .where(
                (item) =>
                    item.record.status == ContentPublicationStatus.scheduled,
              )
              .length,
          Icons.schedule_outlined,
        ),
        _SummaryTile(
          'Archived',
          items
              .where(
                (item) =>
                    item.record.status == ContentPublicationStatus.archived,
              )
              .length,
          Icons.archive_outlined,
        ),
        _SummaryTile(
          'Published this week',
          items
              .where(
                (item) => item.record.publishedAt?.isAfter(weekStart) == true,
              )
              .length,
          Icons.trending_up,
        ),
        _SummaryTile(
          'Total views',
          items.fold(0, (total, item) => total + item.record.viewCount),
          Icons.visibility_outlined,
        ),
        _SummaryTile(
          'Total bookmarks',
          items.fold(0, (total, item) => total + item.record.bookmarkCount),
          Icons.bookmark_outline,
        ),
      ],
    );
  }
}

class _PartialContentWarning extends StatelessWidget {
  const _PartialContentWarning({required this.failures, required this.onRetry});

  final List<_ContentSourceFailure> failures;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.errorContainer,
    child: Padding(
      padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.warning_amber_rounded,
            color: Theme.of(context).colorScheme.onErrorContainer,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text('Some content sources are unavailable'),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 3),
                Text(
                  context.l10n.text(
                    'Available content is shown below. Check Firestore rules for: {collections}.',
                    {
                      'collections': failures
                          .map((failure) => failure.collection)
                          .join(', '),
                    },
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: context.l10n.text('Try again'),
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
    ),
  );
}

class _SummaryTile extends StatelessWidget {
  const _SummaryTile(this.label, this.value, this.icon);

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
                  Text(context.l10n.text(label), maxLines: 2),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _ContentTable extends StatelessWidget {
  const _ContentTable({required this.items, required this.onAction});

  final List<_ManagedSource> items;
  final Future<void> Function(_ManagedSource, _ContentAction) onAction;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        columns: [
          DataColumn(label: Text(context.l10n.text('Title'))),
          DataColumn(label: Text(context.l10n.text('Type'))),
          DataColumn(label: Text(context.l10n.text('Language'))),
          DataColumn(label: Text(context.l10n.text('Status'))),
          DataColumn(label: Text(context.l10n.text('Updated'))),
          DataColumn(label: Text(context.l10n.text('Actions'))),
        ],
        rows: [
          for (final item in items)
            DataRow(
              cells: [
                DataCell(
                  SizedBox(
                    width: 260,
                    child: Text(
                      item.record.localized.localizedTitle(
                        Localizations.localeOf(context).languageCode,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ),
                DataCell(Text(context.l10n.text(_typeLabel(item.record.type)))),
                DataCell(Text(_languageLabel(item.record.localized))),
                DataCell(
                  Text(context.l10n.text(_statusLabel(item.record.status))),
                ),
                DataCell(
                  Text(DateFormat.yMMMd().format(item.record.updatedAt)),
                ),
                DataCell(
                  _ContentActionMenu(
                    item: item,
                    onSelected: (action) => onAction(item, action),
                  ),
                ),
              ],
            ),
        ],
      ),
    ),
  );
}

class _ContentCard extends StatelessWidget {
  const _ContentCard({required this.item, required this.onAction});

  final _ManagedSource item;
  final ValueChanged<_ContentAction> onAction;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 10),
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _Thumbnail(record: item.record),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.record.localized.localizedTitle(
                    Localizations.localeOf(context).languageCode,
                  ),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 4),
                Text(
                  '${context.l10n.text(_typeLabel(item.record.type))} · ${item.record.category}',
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  children: [
                    Chip(
                      label: Text(
                        context.l10n.text(_statusLabel(item.record.status)),
                      ),
                    ),
                    Chip(label: Text(_languageLabel(item.record.localized))),
                  ],
                ),
              ],
            ),
          ),
          _ContentActionMenu(item: item, onSelected: onAction),
        ],
      ),
    ),
  );
}

class _Thumbnail extends StatelessWidget {
  const _Thumbnail({required this.record});

  final ManagedContentRecord record;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(12),
    child: record.thumbnailUrl.isEmpty
        ? Container(
            width: 68,
            height: 68,
            color: Theme.of(context).colorScheme.surfaceContainerHighest,
            child: const Icon(Icons.article_outlined),
          )
        : Image.network(
            record.thumbnailUrl,
            width: 68,
            height: 68,
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => const SizedBox(
              width: 68,
              height: 68,
              child: Icon(Icons.broken_image_outlined),
            ),
          ),
  );
}

class _ContentActionMenu extends StatelessWidget {
  const _ContentActionMenu({required this.item, required this.onSelected});

  final _ManagedSource item;
  final ValueChanged<_ContentAction> onSelected;

  @override
  Widget build(BuildContext context) => PopupMenuButton<_ContentAction>(
    tooltip: context.l10n.text('Content actions'),
    onSelected: onSelected,
    itemBuilder: (_) => [
      PopupMenuItem(
        value: _ContentAction.preview,
        child: Text(context.l10n.text('Preview')),
      ),
      PopupMenuItem(
        value: _ContentAction.edit,
        child: Text(context.l10n.text('Edit')),
      ),
      PopupMenuItem(
        value: _ContentAction.duplicate,
        child: Text(context.l10n.text('Duplicate')),
      ),
      if (item.record.status != ContentPublicationStatus.published)
        PopupMenuItem(
          value: _ContentAction.publish,
          child: Text(context.l10n.text('Publish')),
        )
      else
        PopupMenuItem(
          value: _ContentAction.unpublish,
          child: Text(context.l10n.text('Unpublish')),
        ),
      if (item.record.status == ContentPublicationStatus.archived)
        PopupMenuItem(
          value: _ContentAction.restore,
          child: Text(context.l10n.text('Restore')),
        )
      else
        PopupMenuItem(
          value: _ContentAction.archive,
          child: Text(context.l10n.text('Archive')),
        ),
    ],
  );
}

class _ContentEditor extends StatefulWidget {
  const _ContentEditor({
    required this.repository,
    this.item,
    this.duplicate = false,
  });

  final TradingRepository repository;
  final _ManagedSource? item;
  final bool duplicate;

  @override
  State<_ContentEditor> createState() => _ContentEditorState();
}

class _ContentEditorState extends State<_ContentEditor> {
  final _form = GlobalKey<FormState>();
  late ManagedContentType _type =
      widget.item?.record.type ?? ManagedContentType.tradingArticle;
  late ContentPublicationStatus _status = widget.duplicate
      ? ContentPublicationStatus.draft
      : widget.item?.record.status ?? ContentPublicationStatus.draft;
  late final TextEditingController _titleEn = TextEditingController(
    text: widget.item?.record.localized.titleEn,
  );
  late final TextEditingController _titleZh = TextEditingController(
    text: widget.item?.record.localized.titleZh,
  );
  late final TextEditingController _summaryEn = TextEditingController(
    text: widget.item?.record.localized.summaryEn,
  );
  late final TextEditingController _summaryZh = TextEditingController(
    text: widget.item?.record.localized.summaryZh,
  );
  late final TextEditingController _contentEn = TextEditingController(
    text: widget.item?.record.localized.contentEn,
  );
  late final TextEditingController _contentZh = TextEditingController(
    text: widget.item?.record.localized.contentZh,
  );
  late final TextEditingController _category = TextEditingController(
    text: widget.item?.record.category,
  );
  late final TextEditingController _tags = TextEditingController(
    text: widget.item?.record.tags.join(', '),
  );
  late final TextEditingController _symbols = TextEditingController(
    text: widget.item?.record.symbols.join(', '),
  );
  late final TextEditingController _image = TextEditingController(
    text: widget.item?.record.thumbnailUrl,
  );
  DateTime? _scheduledAt;
  bool _featured = false;
  bool _push = false;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _scheduledAt = widget.item?.record.scheduledAt;
    _featured = widget.item?.record.featured ?? false;
  }

  @override
  void dispose() {
    for (final controller in [
      _titleEn,
      _titleZh,
      _summaryEn,
      _summaryZh,
      _contentEn,
      _contentZh,
      _category,
      _tags,
      _symbols,
      _image,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(
      context.l10n.text(
        widget.item == null || widget.duplicate
            ? 'Create content'
            : 'Edit content',
      ),
    ),
    content: SizedBox(
      width: 820,
      child: Form(
        key: _form,
        child: SingleChildScrollView(
          child: Column(
            children: [
              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<ManagedContentType>(
                      initialValue: _type,
                      decoration: InputDecoration(
                        labelText: context.l10n.text('Content type'),
                      ),
                      items: [
                        for (final type in ManagedContentType.values)
                          DropdownMenuItem(
                            value: type,
                            child: Text(context.l10n.text(_typeLabel(type))),
                          ),
                      ],
                      onChanged: _saving
                          ? null
                          : (value) => setState(() => _type = value ?? _type),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: DropdownButtonFormField<ContentPublicationStatus>(
                      initialValue: _status,
                      decoration: InputDecoration(
                        labelText: context.l10n.text('Status'),
                      ),
                      items: [
                        for (final status in ContentPublicationStatus.values)
                          DropdownMenuItem(
                            value: status,
                            child: Text(
                              context.l10n.text(_statusLabel(status)),
                            ),
                          ),
                      ],
                      onChanged: _saving
                          ? null
                          : (value) =>
                                setState(() => _status = value ?? _status),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              _LocalizedEditor(
                language: context.l10n.text('English'),
                title: _titleEn,
                summary: _summaryEn,
                content: _contentEn,
              ),
              const SizedBox(height: 12),
              _LocalizedEditor(
                language: context.l10n.text('Simplified Chinese'),
                title: _titleZh,
                summary: _summaryZh,
                content: _contentZh,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _category,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Category'),
                ),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _tags,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Tags'),
                  helperText: context.l10n.text('Comma separated'),
                ),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _symbols,
                textCapitalization: TextCapitalization.characters,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Related stock symbols'),
                  helperText: context.l10n.text('Comma separated'),
                ),
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _image,
                keyboardType: TextInputType.url,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Featured image URL'),
                ),
              ),
              if (_status == ContentPublicationStatus.scheduled)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text('Scheduled publication')),
                  subtitle: Text(
                    _scheduledAt == null
                        ? context.l10n.text('Choose date and time')
                        : DateFormat.yMMMd().add_Hm().format(_scheduledAt!),
                  ),
                  trailing: const Icon(Icons.schedule),
                  onTap: _pickSchedule,
                ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Featured content')),
                value: _featured,
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _featured = value),
              ),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(
                  context.l10n.text('Send push notification after publication'),
                ),
                value: _push,
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _push = value),
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
        child: Text(context.l10n.text(_saving ? 'Saving...' : 'Save')),
      ),
    ],
  );

  Future<void> _pickSchedule() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _scheduledAt ?? DateTime.now().add(const Duration(days: 1)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_scheduledAt ?? DateTime.now()),
    );
    if (time == null) return;
    setState(
      () => _scheduledAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      ),
    );
  }

  Future<void> _save() async {
    if (_form.currentState?.validate() != true) return;
    final localized = LocalizedPublishingFields(
      titleEn: _titleEn.text,
      titleZh: _titleZh.text,
      summaryEn: _summaryEn.text,
      summaryZh: _summaryZh.text,
      contentEn: _contentEn.text,
      contentZh: _contentZh.text,
    );
    final errors = _status == ContentPublicationStatus.published
        ? localized.validateForPublish(
            requireContent:
                _type != ManagedContentType.video &&
                _type != ManagedContentType.recording,
          )
        : localized.hasPublishableTitle
        ? const <String>[]
        : const ['A title is required in English or Chinese.'];
    if (errors.isNotEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(context.l10n.text(errors.first))));
      return;
    }
    if (_status == ContentPublicationStatus.scheduled && _scheduledAt == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.l10n.text('Choose a scheduled publication time.'),
          ),
        ),
      );
      return;
    }
    setState(() => _saving = true);
    final definition = _definitionForType(_type);
    final collection = widget.item == null || widget.duplicate
        ? definition.collection
        : widget.item!.collection;
    try {
      await widget.repository.adminSaveResource(
        collection: collection,
        id: widget.duplicate ? null : widget.item?.record.id,
        values: _editorValues(
          collection: collection,
          localized: localized,
          type: _type,
          status: _status,
          category: _category.text,
          tags: _csv(_tags.text),
          symbols: _csv(
            _symbols.text,
          ).map((symbol) => symbol.toUpperCase()).toList(),
          imageUrl: _image.text,
          scheduledAt: _scheduledAt,
          featured: _featured,
          push: _push,
        ),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (_) {
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.text('Unable to save content.'))),
        );
      }
    }
  }
}

class _LocalizedEditor extends StatelessWidget {
  const _LocalizedEditor({
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
              labelText: context.l10n.text('Short summary'),
            ),
          ),
          const SizedBox(height: 10),
          TextFormField(
            controller: content,
            minLines: 5,
            maxLines: 12,
            decoration: InputDecoration(
              labelText: context.l10n.text('Full content'),
            ),
          ),
        ],
      ),
    ),
  );
}

class _FilterDropdown<T> extends StatelessWidget {
  const _FilterDropdown({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });
  final String label;
  final T value;
  final List<DropdownMenuItem<T>> items;
  final ValueChanged<T?> onChanged;
  @override
  Widget build(BuildContext context) => SizedBox(
    width: 215,
    child: DropdownButtonFormField<T>(
      isExpanded: true,
      initialValue: value,
      decoration: InputDecoration(labelText: label),
      items: items,
      onChanged: onChanged,
    ),
  );
}

class _ContentLoading extends StatelessWidget {
  const _ContentLoading();
  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.all(18),
    children: [
      const LinearProgressIndicator(),
      const SizedBox(height: 16),
      for (var index = 0; index < 4; index++) ...[
        Card(child: SizedBox(height: 88, child: Container())),
        const SizedBox(height: 10),
      ],
    ],
  );
}

class _ContentState extends StatelessWidget {
  const _ContentState({
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
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 52),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(message, textAlign: TextAlign.center),
          if (action != null) ...[const SizedBox(height: 14), action!],
        ],
      ),
    ),
  );
}

class _ManagedSource {
  const _ManagedSource({
    required this.collection,
    required this.record,
    required this.raw,
  });
  final String collection;
  final ManagedContentRecord record;
  final Map<String, dynamic> raw;
}

class _ContentLoadResult {
  const _ContentLoadResult({
    required this.items,
    required this.failures,
    required this.successfulSourceCount,
  });

  final List<_ManagedSource> items;
  final List<_ContentSourceFailure> failures;
  final int successfulSourceCount;
}

class _ContentSourceLoad {
  const _ContentSourceLoad({
    required this.collection,
    required this.items,
    this.error,
  });

  final String collection;
  final List<_ManagedSource> items;
  final Object? error;
}

class _ContentSourceFailure {
  const _ContentSourceFailure({required this.collection, required this.error});

  final String collection;
  final Object error;
}

class _ContentCollectionDefinition {
  const _ContentCollectionDefinition(
    this.collection,
    this.type, {
    this.summaryKey = 'summary',
    this.contentKey = 'content',
  });
  final String collection;
  final ManagedContentType type;
  final String summaryKey;
  final String contentKey;
}

const _contentCollections = [
  _ContentCollectionDefinition('contents', ManagedContentType.tradingArticle),
  _ContentCollectionDefinition('trading_blogs', ManagedContentType.blogPost),
  _ContentCollectionDefinition(
    'trading_news',
    ManagedContentType.financialNews,
  ),
  _ContentCollectionDefinition(
    'market_analyses',
    ManagedContentType.marketAnalysis,
    summaryKey: 'market_overview',
    contentKey: 'trading_plan',
  ),
  _ContentCollectionDefinition(
    'knowledge_centres',
    ManagedContentType.knowledgeCentre,
    summaryKey: 'description',
    contentKey: 'description',
  ),
  _ContentCollectionDefinition(
    'trading_recordings',
    ManagedContentType.recording,
    summaryKey: 'description',
    contentKey: 'description',
  ),
  _ContentCollectionDefinition(
    'trading_resources',
    ManagedContentType.tradingEducation,
    summaryKey: 'description',
    contentKey: 'description',
  ),
];

ManagedContentRecord _recordFromRow(
  _ContentCollectionDefinition definition,
  Map<String, dynamic> row,
) {
  final normalized = Map<String, dynamic>.from(row);
  normalized['content_type'] ??= definition.type.storageValue;
  normalized['summary_i18n'] =
      row['${definition.summaryKey}_i18n'] ?? row[definition.summaryKey];
  normalized['content_i18n'] =
      row['${definition.contentKey}_i18n'] ?? row[definition.contentKey];
  if (_booleanStatusCollection(definition.collection)) {
    normalized['publication_status'] = row['archived'] == true
        ? 'archived'
        : row['status'] == false
        ? 'unpublished'
        : 'published';
  }
  return ManagedContentRecord.fromMap('${row['id'] ?? ''}', normalized);
}

_ContentCollectionDefinition _definitionForType(ManagedContentType type) {
  return _contentCollections.firstWhere(
    (item) => item.type == type,
    orElse: () => _contentCollections.first,
  );
}

Map<String, Object?> _editorValues({
  required String collection,
  required LocalizedPublishingFields localized,
  required ManagedContentType type,
  required ContentPublicationStatus status,
  required String category,
  required List<String> tags,
  required List<String> symbols,
  required String imageUrl,
  required DateTime? scheduledAt,
  required bool featured,
  required bool push,
}) {
  final definition = _contentCollections.firstWhere(
    (item) => item.collection == collection,
  );
  return {
    'content_type': type.storageValue,
    if (collection == 'market_analyses')
      'primary_language': localized.titleEn.trim().isEmpty ? 'zh' : 'en',
    if (collection == 'market_analyses')
      'market': category.trim().isEmpty ? 'General market' : category.trim(),
    'title': localized.titleEn,
    'title_zh': localized.titleZh,
    definition.summaryKey: localized.summaryEn,
    '${definition.summaryKey}_zh': localized.summaryZh,
    definition.contentKey: localized.contentEn,
    '${definition.contentKey}_zh': localized.contentZh,
    'category': category.trim(),
    'tags': tags,
    'related_symbols': symbols,
    'image_url': imageUrl.trim(),
    'featured': featured,
    'notification_requested': push,
    'publication_status': status.name,
    'status': _booleanStatusCollection(collection)
        ? status == ContentPublicationStatus.published
        : status.name,
    'scheduled_at': ?scheduledAt,
  };
}

bool _booleanStatusCollection(String collection) =>
    const {'knowledge_centres', 'trading_recordings'}.contains(collection);

enum _ContentAction {
  preview,
  edit,
  duplicate,
  publish,
  unpublish,
  archive,
  restore,
}

enum _ContentSort { recentlyUpdated, newest, oldest, mostViewed }

void _sortRows(List<_ManagedSource> rows, _ContentSort sort) => rows.sort(
  (left, right) => switch (sort) {
    _ContentSort.recentlyUpdated => right.record.updatedAt.compareTo(
      left.record.updatedAt,
    ),
    _ContentSort.newest => right.record.createdAt.compareTo(
      left.record.createdAt,
    ),
    _ContentSort.oldest => left.record.createdAt.compareTo(
      right.record.createdAt,
    ),
    _ContentSort.mostViewed => right.record.viewCount.compareTo(
      left.record.viewCount,
    ),
  },
);

String _languageLabel(LocalizedPublishingFields value) =>
    value.hasEnglish && value.hasChinese
    ? 'EN · 中文'
    : value.hasChinese
    ? '中文'
    : 'EN';
List<String> _csv(String value) => value
    .split(',')
    .map((item) => item.trim())
    .where((item) => item.isNotEmpty)
    .toList();

String _typeLabel(ManagedContentType type) => switch (type) {
  ManagedContentType.tradingArticle => 'Trading article',
  ManagedContentType.financialNews => 'Financial news',
  ManagedContentType.marketAnalysis => 'Market analysis',
  ManagedContentType.knowledgeCentre => 'Knowledge centre',
  ManagedContentType.tradingEducation => 'Trading education',
  ManagedContentType.video => 'Video',
  ManagedContentType.recording => 'Recording',
  ManagedContentType.blogPost => 'Blog post',
  ManagedContentType.financialDailyReport => 'Financial daily report',
  ManagedContentType.disclaimer => 'Disclaimer',
  ManagedContentType.banner => 'Banner',
};
String _statusLabel(ContentPublicationStatus status) =>
    '${status.name[0].toUpperCase()}${status.name.substring(1)}';
String _sortLabel(_ContentSort sort) => switch (sort) {
  _ContentSort.recentlyUpdated => 'Recently updated',
  _ContentSort.newest => 'Newest',
  _ContentSort.oldest => 'Oldest',
  _ContentSort.mostViewed => 'Most viewed',
};
