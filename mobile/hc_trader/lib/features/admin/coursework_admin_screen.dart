import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';
import '../../models/coursework_models.dart';
import '../../repositories/coursework_repository.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import 'coursework_theme_selector.dart';

class CourseworkAdminScreen extends StatefulWidget {
  const CourseworkAdminScreen({
    super.key,
    required this.user,
    required this.tradingRepository,
  });
  final AppUser user;
  final TradingRepository tradingRepository;

  @override
  State<CourseworkAdminScreen> createState() => _CourseworkAdminScreenState();
}

class _CourseworkAdminScreenState extends State<CourseworkAdminScreen> {
  late final CourseworkRepository repository = CourseworkRepository(
    firebaseEnabled: widget.tradingRepository.firebaseEnabled,
    localStorage: widget.tradingRepository.storage,
  );

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 4,
    child: Scaffold(
      appBar: AppBar(
        title: Text(context.l10n.text('Coursework Management')),
        bottom: TabBar(
          isScrollable: true,
          tabs: [
            Tab(
              icon: const Icon(Icons.dashboard_outlined),
              text: context.l10n.text('Dashboard'),
            ),
            Tab(
              icon: const Icon(Icons.library_books_outlined),
              text: context.l10n.text('Themes'),
            ),
            Tab(
              icon: const Icon(Icons.assignment_ind_outlined),
              text: context.l10n.text('Assignments'),
            ),
            Tab(
              icon: const Icon(Icons.fact_check_outlined),
              text: context.l10n.text('Review Queue'),
            ),
          ],
        ),
      ),
      body: TabBarView(
        children: [
          _CourseworkAdminDashboard(repository: repository),
          _ThemeManagement(repository: repository),
          _AssignmentManagement(repository: repository),
          _ReviewQueue(repository: repository),
        ],
      ),
    ),
  );
}

class _CourseworkAdminDashboard extends StatefulWidget {
  const _CourseworkAdminDashboard({required this.repository});
  final CourseworkRepository repository;
  @override
  State<_CourseworkAdminDashboard> createState() =>
      _CourseworkAdminDashboardState();
}

class _CourseworkAdminDashboardState extends State<_CourseworkAdminDashboard> {
  late Future<CourseworkAdminMetrics> future = widget.repository.adminMetrics();
  @override
  Widget build(BuildContext context) => FutureBuilder<CourseworkAdminMetrics>(
    future: future,
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return _AdminError(error: snapshot.error, onRetry: _reload);
      }
      if (!snapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      final data = snapshot.data!;
      final metrics = <(String, String, IconData)>[
        ('Total themes', '${data.totalThemes}', Icons.library_books_outlined),
        ('Published themes', '${data.publishedThemes}', Icons.publish_outlined),
        ('Draft themes', '${data.draftThemes}', Icons.edit_note_outlined),
        (
          'Assigned Coursework',
          '${data.assigned}',
          Icons.assignment_ind_outlined,
        ),
        (
          'In-progress Coursework',
          '${data.inProgress}',
          Icons.pending_actions_outlined,
        ),
        (
          'Awaiting review',
          '${data.awaitingReview}',
          Icons.rate_review_outlined,
        ),
        (
          'Revision requested',
          '${data.revisionRequested}',
          Icons.replay_outlined,
        ),
        (
          'Graded Coursework',
          '${data.graded}',
          Icons.workspace_premium_outlined,
        ),
        ('Overdue Coursework', '${data.overdue}', Icons.event_busy_outlined),
        (
          'Average score',
          data.averageScore.toStringAsFixed(1),
          Icons.analytics_outlined,
        ),
      ];
      return RefreshIndicator(
        onRefresh: () async => _reload(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(18, 18, 18, 32),
          children: [
            Text(
              context.l10n.text('Coursework operational overview'),
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              context.l10n.text(
                'Theme health, candidate progress, review workload, and outcomes.',
              ),
            ),
            const SizedBox(height: 18),
            LayoutBuilder(
              builder: (context, constraints) {
                final columns = constraints.maxWidth >= 950
                    ? 5
                    : constraints.maxWidth >= 600
                    ? 3
                    : 2;
                final width =
                    (constraints.maxWidth - (columns - 1) * 10) / columns;
                return Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    for (final metric in metrics)
                      SizedBox(
                        width: width,
                        child: _AdminMetric(metric: metric),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      );
    },
  );

  void _reload() => setState(() => future = widget.repository.adminMetrics());
}

class _AdminMetric extends StatelessWidget {
  const _AdminMetric({required this.metric});
  final (String, String, IconData) metric;
  @override
  Widget build(BuildContext context) => Card(
    elevation: 0,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(metric.$3),
          const SizedBox(height: 10),
          Text(
            metric.$2,
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          Text(
            context.l10n.text(metric.$1),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    ),
  );
}

class _ThemeManagement extends StatefulWidget {
  const _ThemeManagement({required this.repository});
  final CourseworkRepository repository;
  @override
  State<_ThemeManagement> createState() => _ThemeManagementState();
}

class _ThemeManagementState extends State<_ThemeManagement> {
  final search = TextEditingController();
  late Stream<List<CourseworkTheme>> themeStream;
  late Stream<Map<String, int>> assignmentCountStream;
  var category = 'all';
  var difficulty = 'all';
  var status = 'all';

  @override
  void initState() {
    super.initState();
    themeStream = widget.repository.adminThemes();
    assignmentCountStream = widget.repository.adminAssignmentCounts();
  }

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => StreamBuilder<List<CourseworkTheme>>(
    stream: themeStream,
    builder: (context, snapshot) {
      if (snapshot.hasError) return _AdminError(error: snapshot.error);
      if (!snapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      final all = snapshot.data!;
      final themes = CourseworkThemeFilter.apply(
        all,
        search: search.text,
        categoryId: category,
        difficulty: difficulty,
        publicationStatus: status,
      );
      return StreamBuilder<Map<String, int>>(
        stream: assignmentCountStream,
        builder: (context, countSnapshot) => Scaffold(
          floatingActionButton: FloatingActionButton.extended(
            onPressed: () => _edit(),
            icon: const Icon(Icons.add),
            label: Text(context.l10n.text('New theme')),
          ),
          body: RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 92),
              children: [
                SearchBar(
                  controller: search,
                  hintText: context.l10n.text(
                    'Search title, code, keyword, or objective',
                  ),
                  leading: const Icon(Icons.search),
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    SizedBox(
                      width: 250,
                      child: DropdownButtonFormField<String>(
                        initialValue: category,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Category'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 'all',
                            child: Text(context.l10n.text('All categories')),
                          ),
                          for (final item in CourseworkTaxonomy.categories)
                            DropdownMenuItem(
                              value: item.id,
                              child: Text(
                                context.l10n.locale.languageCode == 'zh'
                                    ? item.zh
                                    : item.en,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => category = value ?? 'all'),
                      ),
                    ),
                    SizedBox(
                      width: 250,
                      child: DropdownButtonFormField<String>(
                        initialValue: difficulty,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Difficulty'),
                        ),
                        items: [
                          for (final item in const [
                            'all',
                            'beginner',
                            'intermediate',
                            'advanced',
                          ])
                            DropdownMenuItem(
                              value: item,
                              child: Text(context.l10n.text(item)),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => difficulty = value ?? 'all'),
                      ),
                    ),
                    SizedBox(
                      width: 250,
                      child: DropdownButtonFormField<String>(
                        initialValue: status,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Status'),
                        ),
                        items: [
                          for (final item in const [
                            'all',
                            'published',
                            'draft',
                            'archived',
                          ])
                            DropdownMenuItem(
                              value: item,
                              child: Text(context.l10n.text(item)),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => status = value ?? 'all'),
                      ),
                    ),
                    OutlinedButton.icon(
                      onPressed: _resetFilters,
                      icon: const Icon(Icons.filter_alt_off_outlined),
                      label: Text(context.l10n.text('Reset filters')),
                    ),
                    IconButton.filledTonal(
                      tooltip: context.l10n.text('Refresh'),
                      onPressed: _reload,
                      icon: const Icon(Icons.refresh),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  context.l10n.text('{count} theme(s)', {
                    'count': themes.length,
                  }),
                ),
                const SizedBox(height: 8),
                if (themes.isEmpty) _emptyThemeState(),
                for (final theme in themes)
                  Card(
                    elevation: 0,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        ListTile(
                          onTap: () => _details(theme),
                          leading: CircleAvatar(
                            child: Text(theme.code.split('-').last),
                          ),
                          title: Text(
                            theme.localizedTitle(
                              context.l10n.locale.languageCode,
                            ),
                            style: const TextStyle(fontWeight: FontWeight.w900),
                          ),
                          subtitle: Text(
                            '${theme.code} · ${context.l10n.locale.languageCode == 'zh' ? (theme.categoryI18n['zh'] ?? theme.category) : theme.category} · ${context.l10n.text(theme.difficultyValue.storageValue)} · ${theme.totalMarks.toStringAsFixed(0)} ${context.l10n.text('marks')}',
                          ),
                          trailing: Wrap(
                            crossAxisAlignment: WrapCrossAlignment.center,
                            children: [
                              PopupMenuButton<String>(
                                onSelected: (value) {
                                  if (value == 'edit') _edit(theme);
                                  if (value == 'duplicate') {
                                    _edit(_duplicate(theme));
                                  }
                                  if (value == 'preview') _details(theme);
                                  if (value == 'assign') {
                                    DefaultTabController.of(
                                      context,
                                    ).animateTo(2);
                                  }
                                  if (value == 'archive') _archive(theme);
                                },
                                itemBuilder: (_) => [
                                  PopupMenuItem(
                                    value: 'preview',
                                    child: Text(context.l10n.text('Preview')),
                                  ),
                                  PopupMenuItem(
                                    value: 'edit',
                                    child: Text(context.l10n.text('Edit')),
                                  ),
                                  PopupMenuItem(
                                    value: 'duplicate',
                                    child: Text(context.l10n.text('Duplicate')),
                                  ),
                                  if (theme.publicationStatus ==
                                          CourseworkPublicationStatus
                                              .published &&
                                      theme.isAssignable)
                                    PopupMenuItem(
                                      value: 'assign',
                                      child: Text(context.l10n.text('Assign')),
                                    ),
                                  if (theme.publicationStatus !=
                                      CourseworkPublicationStatus.archived)
                                    PopupMenuItem(
                                      value: 'archive',
                                      child: Text(context.l10n.text('Archive')),
                                    ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                          child: Wrap(
                            spacing: 14,
                            runSpacing: 7,
                            children: [
                              Chip(
                                label: Text(
                                  context.l10n.text(
                                    theme.publicationStatus.storageValue,
                                  ),
                                ),
                              ),
                              Chip(
                                avatar: Icon(
                                  Icons.circle,
                                  size: 10,
                                  color: theme.isActive
                                      ? Theme.of(context).colorScheme.primary
                                      : Theme.of(context).colorScheme.error,
                                ),
                                label: Text(
                                  context.l10n.text(
                                    theme.isActive ? 'active' : 'inactive',
                                  ),
                                ),
                              ),
                              _themeMetric(
                                Icons.quiz_outlined,
                                context.l10n.text('{count} questions', {
                                  'count': theme.questions.length,
                                }),
                              ),
                              _themeMetric(
                                Icons.schedule_outlined,
                                context.l10n.text('{count} min', {
                                  'count': theme.estimatedCompletionMinutes,
                                }),
                              ),
                              _themeMetric(
                                Icons.assignment_ind_outlined,
                                context.l10n.text('{count} assigned', {
                                  'count': countSnapshot.data?[theme.id] ?? 0,
                                }),
                              ),
                              _themeMetric(
                                Icons.update_outlined,
                                theme.updatedAt == null
                                    ? context.l10n.text('Not recorded')
                                    : DateFormat.yMMMd().format(
                                        theme.updatedAt!.toLocal(),
                                      ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ),
      );
    },
  );

  Widget _themeMetric(IconData icon, String label) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [Icon(icon, size: 17), const SizedBox(width: 5), Text(label)],
    ),
  );

  Widget _emptyThemeState() => Padding(
    padding: const EdgeInsets.symmetric(vertical: 72),
    child: Column(
      children: [
        const Icon(Icons.menu_book_outlined, size: 54),
        const SizedBox(height: 12),
        Text(
          context.l10n.text(
            search.text.isNotEmpty ||
                    category != 'all' ||
                    difficulty != 'all' ||
                    status != 'all'
                ? 'No Coursework themes match these filters.'
                : 'No Coursework themes are available yet.',
          ),
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          alignment: WrapAlignment.center,
          children: [
            if (search.text.isNotEmpty ||
                category != 'all' ||
                difficulty != 'all' ||
                status != 'all')
              OutlinedButton.icon(
                onPressed: _resetFilters,
                icon: const Icon(Icons.filter_alt_off_outlined),
                label: Text(context.l10n.text('Reset filters')),
              ),
            FilledButton.icon(
              onPressed: () => _edit(),
              icon: const Icon(Icons.add),
              label: Text(context.l10n.text('New theme')),
            ),
          ],
        ),
      ],
    ),
  );

  Future<void> _reload() async {
    setState(() {
      themeStream = widget.repository.adminThemes();
      assignmentCountStream = widget.repository.adminAssignmentCounts();
    });
    await Future<void>.delayed(const Duration(milliseconds: 250));
  }

  void _resetFilters() {
    search.clear();
    setState(() {
      category = 'all';
      difficulty = 'all';
      status = 'all';
    });
  }

  Future<void> _edit([CourseworkTheme? theme]) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) =>
            CourseworkThemeEditor(repository: widget.repository, theme: theme),
      ),
    );
  }

  CourseworkTheme _duplicate(CourseworkTheme theme) => CourseworkTheme(
    id: '${theme.id}-copy-${DateTime.now().millisecondsSinceEpoch}',
    code: '${theme.code}-COPY',
    title: '${theme.title} Copy',
    titleI18n: theme.titleI18n,
    shortDescription: theme.shortDescription,
    shortDescriptionI18n: theme.shortDescriptionI18n,
    categoryId: theme.categoryId,
    category: theme.category,
    categoryI18n: theme.categoryI18n,
    difficulty: theme.difficulty,
    learningObjectives: theme.learningObjectives,
    learningObjectivesZh: theme.learningObjectivesZh,
    keywords: theme.keywords,
    searchTokens: theme.searchTokens,
    instructions: theme.instructions,
    instructionsI18n: theme.instructionsI18n,
    totalMarks: theme.totalMarks,
    estimatedCompletionMinutes: theme.estimatedCompletionMinutes,
    status: 'draft',
    isActive: false,
    isAssignable: false,
    sortOrder: theme.sortOrder + 1,
    questions: theme.questions
        .map(
          (question) => CourseworkQuestion.fromMap({
            ...question.toMap(),
            'question_id': '${question.id}-copy',
          }),
        )
        .toList(),
  );

  Future<void> _archive(CourseworkTheme theme) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(context.l10n.text('Archive theme?')),
        content: Text(
          context.l10n.text(
            'This removes the theme from the candidate catalogue and prevents new assignments. Existing assignments are retained.',
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
    try {
      await widget.repository.archiveTheme(theme.id);
      if (mounted) {
        AppFeedback.success(
          context,
          context.l10n.text('Coursework theme archived.'),
        );
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    }
  }

  Future<void> _details(CourseworkTheme theme) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) {
      final language = context.l10n.locale.languageCode;
      return DraggableScrollableSheet(
        expand: false,
        initialChildSize: .82,
        maxChildSize: .95,
        builder: (context, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
          children: [
            Text(
              theme.localizedTitle(language),
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            Text(
              '${theme.code} · ${language == 'zh' ? (theme.categoryI18n['zh'] ?? theme.category) : theme.category} · v${theme.version}',
            ),
            const SizedBox(height: 12),
            for (final question in [
              ...theme.questions,
            ]..sort((a, b) => a.number.compareTo(b.number)))
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: CircleAvatar(child: Text('${question.number}')),
                title: Text(question.localizedQuestion(language)),
                subtitle: Text(
                  '${context.l10n.text(_courseworkQuestionTypeLabel(question.type))} · ${question.marks.toStringAsFixed(0)} ${context.l10n.text('marks')}',
                ),
              ),
            const Divider(),
            Text(
              CourseworkThemeValidator.validate(
                    theme,
                    forPublishing: true,
                  ).isEmpty
                  ? context.l10n.text('Ready to publish')
                  : CourseworkThemeValidator.validate(
                      theme,
                      forPublishing: true,
                    ).join('\n'),
            ),
          ],
        ),
      );
    },
  );
}

class CourseworkThemeEditor extends StatefulWidget {
  const CourseworkThemeEditor({
    super.key,
    required this.repository,
    this.theme,
  });
  final CourseworkRepository repository;
  final CourseworkTheme? theme;
  @override
  State<CourseworkThemeEditor> createState() => _CourseworkThemeEditorState();
}

class _CourseworkThemeEditorState extends State<CourseworkThemeEditor> {
  final form = GlobalKey<FormState>();
  late final code = TextEditingController(text: widget.theme?.code ?? '');
  late final titleEn = TextEditingController(text: widget.theme?.title ?? '');
  late final titleZh = TextEditingController(
    text: widget.theme?.titleI18n['zh'] ?? '',
  );
  late final descriptionEn = TextEditingController(
    text: widget.theme?.shortDescription ?? '',
  );
  late final descriptionZh = TextEditingController(
    text: widget.theme?.shortDescriptionI18n['zh'] ?? '',
  );
  late final objectives = TextEditingController(
    text: widget.theme?.learningObjectives.join('\n') ?? '',
  );
  late final questionEn = List.generate(
    5,
    (index) =>
        TextEditingController(text: _question(index)?.questionText ?? ''),
  );
  late final questionZh = List.generate(
    5,
    (index) => TextEditingController(
      text: _question(index)?.questionTextI18n['zh'] ?? '',
    ),
  );
  late final marks = List.generate(
    5,
    (index) => TextEditingController(
      text: '${_question(index)?.marks ?? const [15, 15, 20, 25, 25][index]}',
    ),
  );
  var category = 'market_foundations';
  var difficulty = 'beginner';
  var saving = false;

  CourseworkQuestion? _question(int index) => widget.theme?.questions
      .where((item) => item.number == index + 1)
      .firstOrNull;

  @override
  void initState() {
    super.initState();
    final existingCategory = widget.theme?.categoryId ?? '';
    if (existingCategory.isNotEmpty) {
      category = existingCategory;
    } else if (widget.theme?.category.isNotEmpty == true) {
      category =
          CourseworkTaxonomy.categories
              .where((item) => item.en == widget.theme!.category)
              .map((item) => item.id)
              .firstOrNull ??
          category;
    }
    difficulty = widget.theme?.difficulty ?? difficulty;
  }

  @override
  void dispose() {
    for (final controller in [
      code,
      titleEn,
      titleZh,
      descriptionEn,
      descriptionZh,
      objectives,
      ...questionEn,
      ...questionZh,
      ...marks,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        context.l10n.text(
          widget.theme == null
              ? 'New Coursework Theme'
              : 'Edit Coursework Theme',
        ),
      ),
    ),
    body: Form(
      key: form,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 14, 18, 36),
        children: [
          Text(
            context.l10n.text('Theme details'),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          _requiredField(code, 'Theme code'),
          const SizedBox(height: 10),
          _requiredField(titleEn, 'Title (English)'),
          const SizedBox(height: 10),
          _requiredField(titleZh, 'Title (Simplified Chinese)'),
          const SizedBox(height: 10),
          TextField(
            controller: descriptionEn,
            decoration: InputDecoration(
              labelText: context.l10n.text('Description (English)'),
            ),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: descriptionZh,
            decoration: InputDecoration(
              labelText: context.l10n.text('Description (Simplified Chinese)'),
            ),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: category,
            isExpanded: true,
            decoration: InputDecoration(
              labelText: context.l10n.text('Category'),
            ),
            items: CourseworkTaxonomy.categories
                .map(
                  (item) => DropdownMenuItem(
                    value: item.id,
                    child: Text(
                      context.l10n.locale.languageCode == 'zh'
                          ? item.zh
                          : item.en,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => category = value ?? category),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: difficulty,
            decoration: InputDecoration(
              labelText: context.l10n.text('Difficulty'),
            ),
            items: const ['beginner', 'intermediate', 'advanced']
                .map(
                  (item) => DropdownMenuItem(
                    value: item,
                    child: Text(context.l10n.text(item)),
                  ),
                )
                .toList(),
            onChanged: (value) =>
                setState(() => difficulty = value ?? difficulty),
          ),
          const SizedBox(height: 10),
          _requiredField(objectives, 'Learning objectives', minLines: 3),
          const Divider(height: 32),
          for (var index = 0; index < 5; index++) ...[
            Text(
              context.l10n.text('Question {number} · {type}', {
                'number': index + 1,
                'type': _fixedTypes[index].storageValue,
              }),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            _requiredField(
              questionEn[index],
              'Question text (English)',
              minLines: 2,
            ),
            const SizedBox(height: 8),
            _requiredField(
              questionZh[index],
              'Question text (Simplified Chinese)',
              minLines: 2,
            ),
            const SizedBox(height: 8),
            _requiredField(marks[index], 'Marks', numeric: true),
            if (index == 2 && (_question(index)?.chartCandles.length ?? 0) < 8)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  context.l10n.text(
                    'Attach verified OHLC/chart data before publishing.',
                  ),
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: 20),
          ],
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              OutlinedButton(
                onPressed: saving ? null : () => _save(false),
                child: Text(context.l10n.text('Save draft')),
              ),
              const SizedBox(width: 10),
              FilledButton.icon(
                onPressed: saving ? null : () => _save(true),
                icon: const Icon(Icons.publish),
                label: Text(context.l10n.text('Publish')),
              ),
            ],
          ),
        ],
      ),
    ),
  );

  Widget _requiredField(
    TextEditingController controller,
    String label, {
    int minLines = 1,
    bool numeric = false,
  }) => TextFormField(
    controller: controller,
    minLines: minLines,
    maxLines: minLines == 1 ? 1 : minLines + 3,
    keyboardType: numeric
        ? const TextInputType.numberWithOptions(decimal: true)
        : TextInputType.text,
    decoration: InputDecoration(labelText: context.l10n.text(label)),
    validator: (value) => value?.trim().isEmpty == true
        ? context.l10n.text('{label} is required', {'label': label})
        : null,
  );

  Future<void> _save(bool publish) async {
    if (form.currentState?.validate() != true) return;
    setState(() => saving = true);
    final id =
        widget.theme?.id ??
        code.text.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]+'), '-');
    final questions = List.generate(5, (index) {
      final existing = _question(index);
      return CourseworkQuestion(
        id: existing?.id ?? '$id-q${index + 1}',
        themeId: id,
        number: index + 1,
        type: _fixedTypes[index],
        title: 'Question ${index + 1}',
        titleI18n: {'zh': '问题 ${index + 1}'},
        questionText: questionEn[index].text.trim(),
        questionTextI18n: {'zh': questionZh[index].text.trim()},
        marks: double.tryParse(marks[index].text) ?? 0,
        answerOptions: existing?.answerOptions ?? const [],
        acceptedAnswers: existing?.acceptedAnswers ?? const [],
        requiresWrittenExplanation:
            existing?.requiresWrittenExplanation ?? index >= 2,
        minimumCharacters:
            existing?.minimumCharacters ??
            (index == 3
                ? 150
                : index == 4
                ? 80
                : 0),
        maximumCharacters:
            existing?.maximumCharacters ??
            (index == 3
                ? 2000
                : index == 4
                ? 1500
                : 0),
        requiresImage: existing?.requiresImage ?? index == 4,
        minimumImages: existing?.minimumImages ?? (index == 4 ? 1 : 0),
        maximumImages: existing?.maximumImages ?? (index == 4 ? 3 : 0),
        allowedFileTypes:
            existing?.allowedFileTypes ??
            (index == 4
                ? const ['image/jpeg', 'image/png', 'image/webp']
                : const []),
        chartAsset: existing?.chartAsset ?? '',
        symbol: existing?.symbol ?? '',
        timeframe: existing?.timeframe ?? '',
        marketDate: existing?.marketDate,
        chartCandles: existing?.chartCandles ?? const [],
        answerZones: existing?.answerZones ?? const [],
        gradingMode:
            existing?.gradingMode ?? (index < 3 ? 'automatic' : 'manual'),
        rubric: existing?.rubric ?? const [],
        version: (existing?.version ?? 0) + 1,
        status: publish ? 'published' : 'draft',
      );
    });
    final theme = CourseworkTheme(
      id: id,
      code: code.text.trim(),
      title: titleEn.text.trim(),
      titleI18n: {'zh': titleZh.text.trim()},
      shortDescription: descriptionEn.text.trim(),
      shortDescriptionI18n: {'zh': descriptionZh.text.trim()},
      categoryId: category,
      category:
          CourseworkTaxonomy.categories
              .where((item) => item.id == category)
              .map((item) => item.en)
              .firstOrNull ??
          category,
      categoryI18n: {
        'zh':
            CourseworkTaxonomy.categories
                .where((item) => item.id == category)
                .map((item) => item.zh)
                .firstOrNull ??
            '',
      },
      difficulty: difficulty,
      learningObjectives: objectives.text
          .split('\n')
          .map((item) => item.trim())
          .where((item) => item.isNotEmpty)
          .toList(),
      instructions:
          widget.theme?.instructions ??
          'Complete all five questions and provide the required evidence.',
      instructionsI18n:
          widget.theme?.instructionsI18n ?? const {'zh': '完成全部五道题并提交所需证据。'},
      totalMarks: questions.fold(0, (sum, item) => sum + item.marks),
      status: publish ? 'published' : 'draft',
      version: (widget.theme?.version ?? 0) + 1,
      questions: questions,
    );
    try {
      await widget.repository.saveTheme(theme, publish: publish);
      if (mounted) {
        AppFeedback.success(
          context,
          publish ? 'Coursework theme published.' : 'Coursework draft saved.',
        );
        Navigator.pop(context);
      }
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }
}

class _AssignmentManagement extends StatefulWidget {
  const _AssignmentManagement({required this.repository});
  final CourseworkRepository repository;
  @override
  State<_AssignmentManagement> createState() => _AssignmentManagementState();
}

class _AssignmentManagementState extends State<_AssignmentManagement> {
  String? themeId;
  String? editingAssignmentId;
  final users = <String>{};
  final instructions = TextEditingController();
  late Stream<List<CourseworkTheme>> themeStream;
  late Stream<List<Map<String, dynamic>>> userStream;
  late Stream<List<CourseworkAssignment>> assignmentStream;
  var dueAt = DateTime.now().add(const Duration(days: 7));
  var allowRevision = true;
  var saving = false;

  @override
  void initState() {
    super.initState();
    _reloadStreams();
  }

  @override
  void dispose() {
    instructions.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => StreamBuilder<List<CourseworkTheme>>(
    stream: themeStream,
    builder: (context, themeSnapshot) {
      if (themeSnapshot.hasError) {
        return _AdminError(error: themeSnapshot.error, onRetry: _reload);
      }
      if (!themeSnapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      return StreamBuilder<List<Map<String, dynamic>>>(
        stream: userStream,
        builder: (context, userSnapshot) {
          if (userSnapshot.hasError) {
            return _AdminError(error: userSnapshot.error, onRetry: _reload);
          }
          if (!userSnapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          return StreamBuilder<List<CourseworkAssignment>>(
            stream: assignmentStream,
            builder: (context, assignmentSnapshot) {
              if (assignmentSnapshot.hasError) {
                return _AdminError(
                  error: assignmentSnapshot.error,
                  onRetry: _reload,
                );
              }
              if (!assignmentSnapshot.hasData) {
                return const Center(child: CircularProgressIndicator());
              }
              return _content(
                allThemes: themeSnapshot.data!,
                candidates: userSnapshot.data!,
                assignments: assignmentSnapshot.data!,
              );
            },
          );
        },
      );
    },
  );

  Widget _content({
    required List<CourseworkTheme> allThemes,
    required List<Map<String, dynamic>> candidates,
    required List<CourseworkAssignment> assignments,
  }) {
    final themes = CourseworkThemeFilter.apply(
      allThemes,
      publicationStatus: CourseworkPublicationStatus.published,
      includeInactive: false,
      assignableOnly: true,
    );
    final hasEligibleSelection = themes.any((item) => item.id == themeId);
    return RefreshIndicator(
      onRefresh: _reload,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(18, 18, 18, 32),
        children: [
          Text(
            context.l10n.text(
              editingAssignmentId == null
                  ? 'Assign Coursework'
                  : 'Edit Coursework Assignment',
            ),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          Text(
            context.l10n.text(
              'Assign one immutable published theme to one or more candidates.',
            ),
          ),
          const SizedBox(height: 16),
          if (themes.isEmpty)
            _AssignmentThemeEmpty(onRefresh: _reload)
          else
            CourseworkThemeSelector(
              themes: themes,
              selectedThemeId: themeId,
              enabled: !saving,
              onSelected: (value) => setState(() => themeId = value),
            ),
          const SizedBox(height: 12),
          Text(
            context.l10n.text('Candidates'),
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Container(
            constraints: const BoxConstraints(maxHeight: 320),
            decoration: BoxDecoration(
              border: Border.all(
                color: Theme.of(context).colorScheme.outlineVariant,
              ),
              borderRadius: BorderRadius.circular(12),
            ),
            child: ListView.builder(
              shrinkWrap: true,
              itemCount: candidates.length,
              itemBuilder: (context, index) {
                final candidate = candidates[index];
                final id = '${candidate['id']}';
                return CheckboxListTile(
                  value: users.contains(id),
                  onChanged: editingAssignmentId != null
                      ? null
                      : (value) => setState(
                          () =>
                              value == true ? users.add(id) : users.remove(id),
                        ),
                  title: Text(
                    '${candidate['display_name'] ?? candidate['name'] ?? candidate['email'] ?? id}',
                  ),
                  subtitle: Text('${candidate['email'] ?? id}'),
                );
              },
            ),
          ),
          const SizedBox(height: 12),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(context.l10n.text('Due date')),
            subtitle: Text(DateFormat.yMMMMd().format(dueAt)),
            trailing: OutlinedButton.icon(
              onPressed: _pickDate,
              icon: const Icon(Icons.event),
              label: Text(context.l10n.text('Change')),
            ),
          ),
          TextField(
            controller: instructions,
            minLines: 3,
            maxLines: 7,
            decoration: InputDecoration(
              labelText: context.l10n.text('Custom assignment instructions'),
            ),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: allowRevision,
            onChanged: (value) => setState(() => allowRevision = value),
            title: Text(context.l10n.text('Allow revision')),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilledButton.icon(
                onPressed: saving || !hasEligibleSelection || users.isEmpty
                    ? null
                    : _saveAssignment,
                icon: saving
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.assignment_turned_in_outlined),
                label: Text(
                  context.l10n.text(
                    editingAssignmentId == null
                        ? 'Assign to {count} candidate(s)'
                        : 'Save assignment changes',
                    {'count': users.length},
                  ),
                ),
              ),
              if (editingAssignmentId != null)
                OutlinedButton(
                  onPressed: saving ? null : _clearEditor,
                  child: Text(context.l10n.text('Cancel editing')),
                ),
            ],
          ),
          const Divider(height: 40),
          Row(
            children: [
              Expanded(
                child: Text(
                  context.l10n.text('Existing assignments'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
              ),
              IconButton(
                tooltip: context.l10n.text('Refresh'),
                onPressed: _reload,
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
          if (assignments.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 24),
              child: Text(
                context.l10n.text('No Coursework assignments yet.'),
                textAlign: TextAlign.center,
              ),
            ),
          for (final assignment in assignments)
            Card(
              elevation: 0,
              child: ListTile(
                leading: const Icon(Icons.assignment_outlined),
                title: Text(
                  assignment.themeSnapshot
                          .localizedTitle(context.l10n.locale.languageCode)
                          .isNotEmpty
                      ? assignment.themeSnapshot.localizedTitle(
                          context.l10n.locale.languageCode,
                        )
                      : assignment.themeTitle.isNotEmpty
                      ? assignment.themeTitle
                      : context.l10n.text(
                          'Previously selected theme unavailable',
                        ),
                ),
                subtitle: Text(
                  '${assignment.themeCode.isNotEmpty ? assignment.themeCode : assignment.themeId} · ${assignment.userId} · ${context.l10n.text(assignment.status.name)}',
                ),
                trailing: IconButton(
                  tooltip: context.l10n.text('Edit'),
                  onPressed: saving
                      ? null
                      : () => _editAssignment(assignment, allThemes),
                  icon: const Icon(Icons.edit_outlined),
                ),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _pickDate() async {
    final selected = await showDatePicker(
      context: context,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      initialDate: dueAt,
    );
    if (selected != null) {
      setState(
        () => dueAt = selected.add(const Duration(hours: 23, minutes: 59)),
      );
    }
  }

  Future<void> _saveAssignment() async {
    setState(() => saving = true);
    try {
      if (editingAssignmentId == null) {
        await widget.repository.assignTheme(
          themeId: themeId!,
          userIds: users.toList(),
          dueAt: dueAt,
          instructions: instructions.text,
          allowRevision: allowRevision,
        );
      } else {
        await widget.repository.updateAssignment(
          assignmentId: editingAssignmentId!,
          themeId: themeId!,
          dueAt: dueAt,
          instructions: instructions.text,
          allowRevision: allowRevision,
        );
      }
      if (mounted) {
        AppFeedback.success(
          context,
          context.l10n.text(
            editingAssignmentId == null
                ? 'Coursework assigned successfully.'
                : 'Coursework assignment updated.',
          ),
        );
      }
      _clearEditor();
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  void _editAssignment(
    CourseworkAssignment assignment,
    List<CourseworkTheme> allThemes,
  ) {
    final resolved = assignment.resolveThemeId(allThemes);
    setState(() {
      editingAssignmentId = assignment.id;
      themeId =
          resolved ?? (assignment.themeId.isEmpty ? null : assignment.themeId);
      users
        ..clear()
        ..add(assignment.userId);
      instructions.text = assignment.customInstructions;
      dueAt = assignment.dueAt ?? DateTime.now().add(const Duration(days: 7));
      allowRevision = assignment.allowRevision;
    });
  }

  void _clearEditor() {
    if (!mounted) return;
    setState(() {
      editingAssignmentId = null;
      themeId = null;
      users.clear();
      instructions.clear();
      dueAt = DateTime.now().add(const Duration(days: 7));
      allowRevision = true;
    });
  }

  void _reloadStreams() {
    themeStream = widget.repository.adminThemes();
    userStream = widget.repository.adminAssignableUsers();
    assignmentStream = widget.repository.adminAssignments();
  }

  Future<void> _reload() async {
    setState(_reloadStreams);
    await Future<void>.delayed(const Duration(milliseconds: 250));
  }
}

class _AssignmentThemeEmpty extends StatelessWidget {
  const _AssignmentThemeEmpty({required this.onRefresh});
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) => Card(
    elevation: 0,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          const Icon(Icons.menu_book_outlined),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              context.l10n.text('No eligible published themes are available.'),
            ),
          ),
          IconButton(
            tooltip: context.l10n.text('Refresh'),
            onPressed: onRefresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
    ),
  );
}

class _ReviewQueue extends StatelessWidget {
  const _ReviewQueue({required this.repository});
  final CourseworkRepository repository;
  @override
  Widget build(BuildContext context) => StreamBuilder<List<CourseworkAttempt>>(
    stream: repository.adminReviewQueue(),
    builder: (context, snapshot) {
      if (snapshot.hasError) return _AdminError(error: snapshot.error);
      if (!snapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      final attempts = snapshot.data!;
      if (attempts.isEmpty) {
        return Center(
          child: Text(
            context.l10n.text('No Coursework submissions are awaiting review.'),
          ),
        );
      }
      return ListView.builder(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
        itemCount: attempts.length,
        itemBuilder: (context, index) {
          final attempt = attempts[index];
          return Card(
            child: ListTile(
              leading: const CircleAvatar(
                child: Icon(Icons.rate_review_outlined),
              ),
              title: Text(
                attempt.themeSnapshot.localizedTitle(
                  context.l10n.locale.languageCode,
                ),
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              subtitle: Text(
                '${attempt.userId} · ${attempt.status.name} · ${attempt.submittedAt == null ? '—' : DateFormat.yMd().add_jm().format(attempt.submittedAt!.toLocal())}',
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => CourseworkGradingScreen(
                    repository: repository,
                    attemptId: attempt.id,
                  ),
                ),
              ),
            ),
          );
        },
      );
    },
  );
}

class CourseworkGradingScreen extends StatefulWidget {
  const CourseworkGradingScreen({
    super.key,
    required this.repository,
    required this.attemptId,
  });
  final CourseworkRepository repository;
  final String attemptId;
  @override
  State<CourseworkGradingScreen> createState() =>
      _CourseworkGradingScreenState();
}

class _CourseworkGradingScreenState extends State<CourseworkGradingScreen> {
  final marks = <String, Map<String, TextEditingController>>{};
  final feedback = <String, TextEditingController>{};
  final overall = TextEditingController();
  var saving = false;
  @override
  void dispose() {
    overall.dispose();
    for (final controller in [
      ...feedback.values,
      ...marks.values.expand((item) => item.values),
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => StreamBuilder<CourseworkAttempt>(
    stream: widget.repository.watchAttempt(widget.attemptId),
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return Scaffold(body: _AdminError(error: snapshot.error));
      }
      if (!snapshot.hasData) {
        return const Scaffold(body: Center(child: CircularProgressIndicator()));
      }
      final attempt = snapshot.data!;
      final language = context.l10n.locale.languageCode;
      final manual = attempt.themeSnapshot.questions
          .where((item) => item.type.requiresManualReview)
          .toList();
      return Scaffold(
        appBar: AppBar(title: Text(context.l10n.text('Grade Coursework'))),
        body: ListView(
          padding: const EdgeInsets.fromLTRB(18, 16, 18, 100),
          children: [
            Text(
              attempt.themeSnapshot.localizedTitle(language),
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            Text(
              '${attempt.userId} · ${attempt.status.name} · automatic ${attempt.automaticScore.toStringAsFixed(1)} marks',
            ),
            const SizedBox(height: 16),
            for (final question in manual)
              _gradingQuestion(context, question, attempt.answers[question.id]),
            TextField(
              controller: overall,
              minLines: 3,
              maxLines: 8,
              decoration: InputDecoration(
                labelText: context.l10n.text('Overall feedback'),
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: saving ? null : () => _requestRevision(attempt),
                    icon: const Icon(Icons.replay),
                    label: Text(context.l10n.text('Request revision')),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: saving ? null : () => _finalise(manual),
                    icon: const Icon(Icons.verified_outlined),
                    label: Text(context.l10n.text('Finalise grade')),
                  ),
                ),
              ],
            ),
          ],
        ),
      );
    },
  );

  Widget _gradingQuestion(
    BuildContext context,
    CourseworkQuestion question,
    CourseworkAnswer? answer,
  ) {
    final criteria = question.rubric.isEmpty
        ? [
            CourseworkRubricCriterion(
              id: 'total',
              title: 'Total mark',
              titleI18n: const {'zh': '总分'},
              maximumMark: question.marks,
            ),
          ]
        : question.rubric;
    final questionMarks = marks.putIfAbsent(question.id, () => {});
    final questionFeedback = feedback.putIfAbsent(
      question.id,
      TextEditingController.new,
    );
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              '${context.l10n.text('Question')} ${question.number} · ${question.marks.toStringAsFixed(0)} ${context.l10n.text('marks')}',
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(question.localizedQuestion(context.l10n.locale.languageCode)),
            const SizedBox(height: 6),
            SelectableText(answer?.textAnswer ?? ''),
            if (answer?.attachments.isNotEmpty == true)
              for (final attachment in answer!.attachments)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.lock_outline),
                  title: Text(attachment.fileName),
                  subtitle: Text(attachment.storagePath),
                ),
            const Divider(),
            for (final criterion in criteria) ...[
              TextFormField(
                controller: questionMarks.putIfAbsent(
                  criterion.id,
                  TextEditingController.new,
                ),
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(
                  labelText:
                      '${criterion.localizedTitle(context.l10n.locale.languageCode)} (0–${criterion.maximumMark})',
                ),
              ),
              const SizedBox(height: 8),
            ],
            TextField(
              controller: questionFeedback,
              minLines: 2,
              maxLines: 5,
              decoration: InputDecoration(
                labelText: context.l10n.text('Question feedback'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _requestRevision(CourseworkAttempt attempt) async {
    if (overall.text.trim().isEmpty) {
      AppFeedback.error(context, 'Enter a clear revision reason.');
      return;
    }
    setState(() => saving = true);
    try {
      await widget.repository.gradeAttempt(
        attemptId: attempt.id,
        rubricMarks: const {},
        feedback: const {},
        overallFeedback: overall.text,
        requestRevision: true,
        reason: overall.text,
      );
      if (mounted) Navigator.pop(context);
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> _finalise(List<CourseworkQuestion> questions) async {
    final resolved = <String, Map<String, double>>{};
    for (final question in questions) {
      final questionMarks = marks[question.id] ?? {};
      final values = <String, double>{};
      for (final entry in questionMarks.entries) {
        final value = double.tryParse(entry.value.text);
        if (value == null) {
          AppFeedback.error(context, 'Complete every rubric mark.');
          return;
        }
        values[entry.key] = value;
      }
      resolved[question.id] = values;
    }
    setState(() => saving = true);
    try {
      await widget.repository.gradeAttempt(
        attemptId: widget.attemptId,
        rubricMarks: resolved,
        feedback: {
          for (final entry in feedback.entries) entry.key: entry.value.text,
        },
        overallFeedback: overall.text,
      );
      if (mounted) Navigator.pop(context);
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }
}

class _AdminError extends StatelessWidget {
  const _AdminError({required this.error, this.onRetry});
  final Object? error;
  final VoidCallback? onRetry;

  String _message(BuildContext context) {
    final code = error is CourseworkRepositoryException
        ? (error! as CourseworkRepositoryException).code
        : null;
    if (code == 'permission-denied') {
      return context.l10n.text(
        'You do not have permission to view Coursework themes.',
      );
    }
    if (code == 'failed-precondition' && '$error'.contains('index')) {
      return context.l10n.text('A Firestore index is required for this query.');
    }
    if (const {
      'unavailable',
      'network-request-failed',
      'deadline-exceeded',
    }.contains(code)) {
      return context.l10n.text(
        'Coursework could not be loaded. Check your connection and retry.',
      );
    }
    return '$error';
  }

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline, size: 42),
          const SizedBox(height: 8),
          Text(_message(context), textAlign: TextAlign.center),
          if (onRetry != null)
            TextButton(
              onPressed: onRetry,
              child: Text(context.l10n.text('Retry')),
            ),
        ],
      ),
    ),
  );
}

const _fixedTypes = [
  CourseworkQuestionType.singleChoice,
  CourseworkQuestionType.singleChoice,
  CourseworkQuestionType.chartZoneSelection,
  CourseworkQuestionType.marketAnalysis,
  CourseworkQuestionType.practicalDemonstration,
];

String _courseworkQuestionTypeLabel(CourseworkQuestionType type) =>
    switch (type) {
      CourseworkQuestionType.singleChoice ||
      CourseworkQuestionType.multipleChoice ||
      CourseworkQuestionType.trueFalse ||
      CourseworkQuestionType.shortAnswer => 'Theory Question',
      CourseworkQuestionType.chartZoneSelection => 'Chart Question',
      CourseworkQuestionType.marketAnalysis => 'Self-Analysis',
      CourseworkQuestionType.practicalDemonstration =>
        'Practical Demonstration',
      CourseworkQuestionType.orderManagementDemonstration => 'Order Management',
    };
