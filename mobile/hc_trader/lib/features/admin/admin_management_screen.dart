import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/localization/localized_value.dart';
import '../../core/localization/publishing_localization.dart';
import '../../core/network/api_client.dart';
import '../../core/security/admin_permissions.dart';
import '../../core/security/publishing_authorization.dart';
import '../../models/app_user.dart';
import '../../models/firestore_helpers.dart';
import '../../models/knowledge_centre_category.dart';
import '../../models/trading_signal_lifecycle.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../shared/content_language_selector.dart';
import '../../shared/formatters.dart';
import 'admin_personality_questions_screen.dart';
import 'coursework_admin_screen.dart';
import 'trading_signal_management_screen.dart';
import '../appointments/trading_appointments_screen.dart';
import '../hub/publisher_sheets.dart';
import '../hub/publisher_validation.dart';
import '../support/support_tickets_screen.dart';

enum _FieldKind { text, longText, number, select, toggle }

class _AdminField {
  const _AdminField(
    this.key,
    this.label, {
    this.kind = _FieldKind.text,
    this.required = false,
    this.options = const [],
    this.helperText,
  });

  final String key;
  final String label;
  final _FieldKind kind;
  final bool required;
  final List<String> options;
  final String? helperText;
}

class _AdminModule {
  const _AdminModule({
    required this.title,
    required this.subtitle,
    required this.collection,
    required this.icon,
    required this.permission,
    required this.featureKey,
    required this.fields,
  });

  final String title;
  final String subtitle;
  final String collection;
  final IconData icon;
  final AdminPermission permission;
  final String featureKey;
  final List<_AdminField> fields;
}

const _statusFields = <String>['draft', 'published', 'archived'];
const _visibilityFields = <String>[
  'public',
  'client_only',
  'internal',
  'role_based',
];

const _manualBilingualFieldsByCollection = <String, Set<String>>{
  'trading_resources': {'title', 'description'},
  'trading_recordings': {'title', 'description'},
  'knowledge_centres': {'title', 'description'},
  'trading_blogs': {'title', 'content'},
  'trading_news': {'title', 'content', 'category'},
  'trading_signals': {'title', 'analysis'},
  'market_analyses': {
    'title',
    'market_overview',
    'trend_structure',
    'key_zones',
    'analyst_view',
    'trading_plan',
  },
  'trading_exam_questions': {
    'question_text',
    'category',
    'section',
    'chart_description',
    'option_a',
    'option_b',
    'option_c',
    'option_d',
    'explanation',
  },
  'final_exam_questions': {
    'question_text',
    'category',
    'scenario_text',
    'option_a',
    'option_b',
    'option_c',
    'option_d',
    'explanation',
  },
  'prop_firm_models': {
    'prop_firm_name',
    'name',
    'lot_size_rules',
    'consistency_rule',
  },
};

const _adminModules = <_AdminModule>[
  _AdminModule(
    title: 'Marketing Resources',
    subtitle: 'Images, documents, videos and links',
    collection: 'trading_resources',
    icon: Icons.campaign_outlined,
    permission: AdminPermission.marketingView,
    featureKey: 'marketing_resources',
    fields: [
      _AdminField('title', 'Title', required: true),
      _AdminField('slug', 'Slug'),
      _AdminField('description', 'Description', kind: _FieldKind.longText),
      _AdminField(
        'category',
        'Category',
        kind: _FieldKind.select,
        options: KnowledgeCentreCategory.slugs,
      ),
      _AdminField(
        'type',
        'Type',
        kind: _FieldKind.select,
        options: ['image', 'video', 'pdf', 'document', 'link', 'other'],
      ),
      _AdminField('url', 'File or external URL'),
      _AdminField('thumbnail_url', 'Thumbnail URL'),
      _AdminField(
        'publication_status',
        'Status',
        kind: _FieldKind.select,
        options: _statusFields,
      ),
      _AdminField(
        'visibility',
        'Visibility',
        kind: _FieldKind.select,
        options: _visibilityFields,
      ),
    ],
  ),
  _AdminModule(
    title: 'Trading News',
    subtitle: 'Publish and feature market news',
    collection: 'trading_news',
    icon: Icons.newspaper_outlined,
    permission: AdminPermission.newsView,
    featureKey: 'trading_news',
    fields: [
      _AdminField('title', 'Title', required: true),
      _AdminField('slug', 'Slug'),
      _AdminField('category', 'Category'),
      _AdminField(
        'content',
        'Content',
        kind: _FieldKind.longText,
        required: true,
      ),
      _AdminField('source_name', 'Source name'),
      _AdminField('source_url', 'Source URL'),
      _AdminField('thumbnail_url', 'Thumbnail URL'),
      _AdminField('tags', 'Tags (comma separated)'),
      _AdminField(
        'status',
        'Status',
        kind: _FieldKind.select,
        options: _statusFields,
      ),
      _AdminField('is_featured', 'Featured', kind: _FieldKind.toggle),
    ],
  ),
  _AdminModule(
    title: 'Knowledge Centre',
    subtitle: 'Articles, guides and attachments',
    collection: 'knowledge_centres',
    icon: Icons.menu_book_outlined,
    permission: AdminPermission.knowledgeView,
    featureKey: 'knowledge_centre',
    fields: [
      _AdminField('title', 'Title', required: true),
      _AdminField('slug', 'Slug'),
      _AdminField('category', 'Category'),
      _AdminField(
        'description',
        'Content',
        kind: _FieldKind.longText,
        required: true,
      ),
      _AdminField('image_url', 'Thumbnail URL'),
      _AdminField('file_path', 'Attachment URL'),
      _AdminField(
        'status_label',
        'Status',
        kind: _FieldKind.select,
        options: _statusFields,
      ),
      _AdminField(
        'visibility',
        'Visibility',
        kind: _FieldKind.select,
        options: _visibilityFields,
      ),
    ],
  ),
  _AdminModule(
    title: 'Trading Signals',
    subtitle: 'Entries, risk and take-profit plans',
    collection: 'trading_signals',
    icon: Icons.online_prediction_outlined,
    permission: AdminPermission.signalView,
    featureKey: 'trading_signals',
    fields: [
      _AdminField('title', 'Title', required: true),
      _AdminField('trading_pair', 'Pair', required: true),
      _AdminField(
        'immediate_action',
        'Direction',
        kind: _FieldKind.select,
        options: ['BUY', 'SELL'],
      ),
      _AdminField(
        'order_type',
        'Order type',
        kind: _FieldKind.select,
        options: [
          'market_execution',
          'buy_limit',
          'sell_limit',
          'buy_stop',
          'sell_stop',
          'pending_order',
        ],
      ),
      _AdminField('entry_price', 'Entry price', kind: _FieldKind.number),
      _AdminField('stop_loss', 'Stop loss', kind: _FieldKind.number),
      _AdminField('take_profit_1', 'Take profit 1', kind: _FieldKind.number),
      _AdminField('take_profit_2', 'Take profit 2', kind: _FieldKind.number),
      _AdminField('take_profit_3', 'Take profit 3', kind: _FieldKind.number),
      _AdminField(
        'risk_level',
        'Risk level',
        kind: _FieldKind.select,
        options: ['Low', 'Medium', 'High'],
      ),
      _AdminField('timeframe', 'Timeframe'),
      _AdminField('analysis', 'Analysis', kind: _FieldKind.longText),
      _AdminField('chart_image_url', 'Chart image URL'),
      _AdminField(
        'lifecycle_status',
        'Status',
        kind: _FieldKind.select,
        options: ['draft', 'active', 'closed', 'cancelled'],
      ),
    ],
  ),
  _AdminModule(
    title: 'Market Analyst',
    subtitle: 'Analysis content and review workflow',
    collection: 'market_analyses',
    icon: Icons.candlestick_chart_outlined,
    permission: AdminPermission.marketAnalysisView,
    featureKey: 'market_analyst',
    fields: [
      _AdminField('title', 'Title', required: true),
      _AdminField(
        'market',
        'Market Coverage',
        required: true,
        helperText: 'Example: XAUUSD, Forex, Indices, Crypto, US Stocks',
      ),
      _AdminField(
        'market_overview',
        'Market overview',
        kind: _FieldKind.longText,
      ),
      _AdminField(
        'trend_structure',
        'Trend structure',
        kind: _FieldKind.longText,
      ),
      _AdminField('key_zones', 'Key zones', kind: _FieldKind.longText),
      _AdminField('analyst_view', 'Analyst view', kind: _FieldKind.longText),
      _AdminField('trading_plan', 'Trading plan', kind: _FieldKind.longText),
      _AdminField('outlook_image', 'Chart image URL'),
      _AdminField(
        'status',
        'Status',
        kind: _FieldKind.select,
        options: _statusFields,
      ),
    ],
  ),
  _AdminModule(
    title: 'Recording Classes',
    subtitle: 'Video classes and learning materials',
    collection: 'trading_recordings',
    icon: Icons.video_library_outlined,
    permission: AdminPermission.recordingManage,
    featureKey: 'recording_classes',
    fields: [
      _AdminField('title', 'Title', required: true),
      _AdminField('description', 'Description', kind: _FieldKind.longText),
      _AdminField('video_url', 'Video URL'),
      _AdminField('download_url', 'Material URL'),
      _AdminField('thumbnail_url', 'Thumbnail URL'),
      _AdminField('category', 'Category'),
      _AdminField('level', 'Level'),
      _AdminField(
        'visibility',
        'Visibility',
        kind: _FieldKind.select,
        options: _visibilityFields,
      ),
      _AdminField('status', 'Published', kind: _FieldKind.toggle),
    ],
  ),
  _AdminModule(
    title: 'Trading Blog',
    subtitle: 'Posts, categories and tags',
    collection: 'trading_blogs',
    icon: Icons.article_outlined,
    permission: AdminPermission.blogManage,
    featureKey: 'trading_blog',
    fields: [
      _AdminField('title', 'Title', required: true),
      _AdminField('slug', 'Slug'),
      _AdminField(
        'content',
        'Content',
        kind: _FieldKind.longText,
        required: true,
      ),
      _AdminField('thumbnail_url', 'Thumbnail URL'),
      _AdminField('category', 'Category'),
      _AdminField('tags', 'Tags (comma separated)'),
      _AdminField(
        'status',
        'Status',
        kind: _FieldKind.select,
        options: _statusFields,
      ),
      _AdminField(
        'visibility',
        'Visibility',
        kind: _FieldKind.select,
        options: _visibilityFields,
      ),
    ],
  ),
  _AdminModule(
    title: 'Trading Exam Questions',
    subtitle: 'Foundation, technique and psychology',
    collection: 'trading_exam_questions',
    icon: Icons.quiz_outlined,
    permission: AdminPermission.examManage,
    featureKey: 'trading_exams',
    fields: [
      _AdminField(
        'question_text',
        'Question',
        kind: _FieldKind.longText,
        required: true,
      ),
      _AdminField('category', 'Exam type'),
      _AdminField('section', 'Section'),
      _AdminField(
        'question_type',
        'Question type',
        kind: _FieldKind.select,
        options: [
          'multiple_choice',
          'chart_multiple_choice',
          'chart_screenshot_analysis',
        ],
      ),
      _AdminField(
        'chart_image_url',
        'Chart image URL',
        required: true,
        helperText: 'Every published daily quiz must include a chart.',
      ),
      _AdminField(
        'chart_description',
        'Chart context',
        kind: _FieldKind.longText,
      ),
      _AdminField('option_a', 'Option A', required: true),
      _AdminField('option_b', 'Option B', required: true),
      _AdminField('option_c', 'Option C'),
      _AdminField('option_d', 'Option D'),
      _AdminField(
        'correct_answer',
        'Correct answer',
        kind: _FieldKind.select,
        options: ['A', 'B', 'C', 'D'],
      ),
      _AdminField('explanation', 'Explanation', kind: _FieldKind.longText),
      _AdminField(
        'requires_screenshot',
        'Require screenshot',
        kind: _FieldKind.toggle,
      ),
      _AdminField(
        'requires_written_explanation',
        'Require written explanation',
        kind: _FieldKind.toggle,
      ),
      _AdminField(
        'minimum_explanation_characters',
        'Minimum explanation characters',
        kind: _FieldKind.number,
      ),
      _AdminField('version', 'Question version', kind: _FieldKind.number),
      _AdminField(
        'maximum_attempts',
        'Maximum attempts',
        kind: _FieldKind.number,
      ),
      _AdminField(
        'difficulty',
        'Difficulty',
        kind: _FieldKind.select,
        options: ['beginner', 'intermediate', 'advanced'],
      ),
      _AdminField(
        'status',
        'Status',
        kind: _FieldKind.select,
        options: ['approved', 'inactive'],
      ),
    ],
  ),
  _AdminModule(
    title: 'Final Examination',
    subtitle: 'MCQ, chart and psychology scenarios',
    collection: 'final_exam_questions',
    icon: Icons.school_outlined,
    permission: AdminPermission.examManage,
    featureKey: 'trading_exams',
    fields: [
      _AdminField(
        'question_text',
        'Question',
        kind: _FieldKind.longText,
        required: true,
      ),
      _AdminField('category', 'Section', required: true),
      _AdminField(
        'question_type',
        'Question type',
        kind: _FieldKind.select,
        options: ['multiple_choice', 'chart_technique', 'psychology_scenario'],
      ),
      _AdminField('scenario_text', 'Scenario', kind: _FieldKind.longText),
      _AdminField('chart_image_url', 'Chart image URL'),
      _AdminField('option_a', 'Option A', required: true),
      _AdminField('option_b', 'Option B', required: true),
      _AdminField('option_c', 'Option C'),
      _AdminField('option_d', 'Option D'),
      _AdminField(
        'correct_answer',
        'Correct answer',
        kind: _FieldKind.select,
        options: ['A', 'B', 'C', 'D'],
      ),
      _AdminField('explanation', 'Explanation', kind: _FieldKind.longText),
      _AdminField(
        'difficulty',
        'Difficulty',
        kind: _FieldKind.select,
        options: ['beginner', 'intermediate', 'advanced'],
      ),
      _AdminField(
        'status',
        'Status',
        kind: _FieldKind.select,
        options: ['approved', 'inactive'],
      ),
    ],
  ),
  _AdminModule(
    title: 'Prop Firm Config',
    subtitle: 'Models, phases, targets and limits',
    collection: 'prop_firm_models',
    icon: Icons.account_balance_outlined,
    permission: AdminPermission.propFirmConfigManage,
    featureKey: 'prop_firm_config',
    fields: [
      _AdminField('prop_firm_name', 'Prop firm', required: true),
      _AdminField('name', 'Model name', required: true),
      _AdminField('phase_count', 'Phase count', kind: _FieldKind.number),
      _AdminField('profit_target', 'Profit target %', kind: _FieldKind.number),
      _AdminField(
        'daily_drawdown_limit',
        'Daily drawdown %',
        kind: _FieldKind.number,
      ),
      _AdminField(
        'max_drawdown_limit',
        'Maximum drawdown %',
        kind: _FieldKind.number,
      ),
      _AdminField(
        'minimum_trading_days',
        'Minimum trading days',
        kind: _FieldKind.number,
      ),
      _AdminField(
        'maximum_trading_days',
        'Maximum trading days',
        kind: _FieldKind.number,
      ),
      _AdminField(
        'lot_size_rules',
        'Lot size rules',
        kind: _FieldKind.longText,
      ),
      _AdminField(
        'news_trading_allowed',
        'News trading allowed',
        kind: _FieldKind.toggle,
      ),
      _AdminField(
        'weekend_holding_allowed',
        'Weekend holding allowed',
        kind: _FieldKind.toggle,
      ),
      _AdminField(
        'copy_trading_allowed',
        'Copy trading allowed',
        kind: _FieldKind.toggle,
      ),
      _AdminField('ea_allowed', 'EA allowed', kind: _FieldKind.toggle),
      _AdminField(
        'consistency_rule',
        'Consistency rule',
        kind: _FieldKind.longText,
      ),
      _AdminField('status', 'Enabled', kind: _FieldKind.toggle),
    ],
  ),
];

class AdminManagementScreen extends StatefulWidget {
  const AdminManagementScreen({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<AdminManagementScreen> createState() => _AdminManagementScreenState();
}

class _AdminManagementScreenState extends State<AdminManagementScreen> {
  final _search = TextEditingController();
  final _favorites = <String>{};
  final _recent = <String>[];
  String _category = 'All';
  late Future<Map<String, bool>> _features;

  @override
  void initState() {
    super.initState();
    _features = widget.repository.featureConfiguration();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Map<String, bool>>(
      future: _features,
      builder: (context, snapshot) {
        final features = snapshot.data ?? tradingFeatureDefaults;
        final modules = _adminModules
            .where((module) => widget.user.hasPermission(module.permission))
            .toList();
        final items = _workspaceItems(context, modules, features);
        final query = _search.text.trim().toLowerCase();
        final filtered =
            items.where((item) {
              final categoryMatches =
                  _category == 'All' || item.category == _category;
              final searchValue = [
                item.title,
                item.subtitle,
                item.category,
                context.l10n.text(item.title),
                context.l10n.text(item.subtitle),
                context.l10n.text(item.category),
              ].join(' ').toLowerCase();
              final queryMatches = query.isEmpty || searchValue.contains(query);
              return categoryMatches && queryMatches;
            }).toList()..sort((left, right) {
              final leftFavorite = _favorites.contains(left.id) ? 0 : 1;
              final rightFavorite = _favorites.contains(right.id) ? 0 : 1;
              if (leftFavorite != rightFavorite) {
                return leftFavorite.compareTo(rightFavorite);
              }
              return left.title.compareTo(right.title);
            });
        return LayoutBuilder(
          builder: (context, constraints) {
            final desktop = constraints.maxWidth >= 1050;
            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                key: const Key('admin-modules-workspace'),
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 34),
                children: [
                  AdminWorkspaceHeader(
                    user: widget.user,
                    searchController: _search,
                    onSearchChanged: (_) => setState(() {}),
                    onRefresh: _refresh,
                    onCreateSignal:
                        PublishingAuthorization(
                          widget.user,
                        ).canPublishTradingSignal
                        ? () => showSignalPublisher(
                            context,
                            user: widget.user,
                            repository: widget.repository,
                          )
                        : null,
                  ),
                  if (snapshot.connectionState == ConnectionState.waiting) ...[
                    const SizedBox(height: 10),
                    const LinearProgressIndicator(
                      key: Key('admin-workspace-loading'),
                    ),
                  ],
                  if (snapshot.hasError) ...[
                    const SizedBox(height: 10),
                    Card(
                      key: const Key('admin-workspace-error'),
                      color: Theme.of(context).colorScheme.errorContainer,
                      child: ListTile(
                        leading: const Icon(Icons.cloud_off_outlined),
                        title: Text(
                          context.l10n.text(
                            'Feature configuration is temporarily unavailable.',
                          ),
                        ),
                        trailing: IconButton(
                          tooltip: context.l10n.text('Retry'),
                          onPressed: _refresh,
                          icon: const Icon(Icons.refresh),
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 14),
                  SignalOperationsPanel(
                    metrics: widget.repository.signalOperationsMetricsStream(
                      widget.user,
                    ),
                    onOpen: () {
                      final signal = items
                          .where((item) => item.id == 'trading_signals')
                          .firstOrNull;
                      if (signal != null) _openItem(signal);
                    },
                  ),
                  const SizedBox(height: 14),
                  if (_recent.isNotEmpty)
                    _RecentModules(
                      items: [
                        for (final id in _recent)
                          ?items.where((item) => item.id == id).firstOrNull,
                      ],
                      onOpen: _openItem,
                    ),
                  if (_recent.isNotEmpty) const SizedBox(height: 14),
                  if (desktop)
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        SizedBox(
                          width: 210,
                          child: _ModuleCategoryNavigation(
                            selected: _category,
                            items: items,
                            onSelected: (value) =>
                                setState(() => _category = value),
                          ),
                        ),
                        const SizedBox(width: 16),
                        Expanded(child: _moduleGrid(filtered, 3)),
                      ],
                    )
                  else ...[
                    _ModuleCategoryNavigation(
                      selected: _category,
                      items: items,
                      horizontal: true,
                      onSelected: (value) => setState(() => _category = value),
                    ),
                    const SizedBox(height: 12),
                    _moduleGrid(filtered, constraints.maxWidth >= 680 ? 2 : 1),
                  ],
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _moduleGrid(List<_WorkspaceItem> items, int columns) {
    if (items.isEmpty) {
      return const _AdminWorkspaceEmptyState();
    }
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: items.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: columns,
        mainAxisExtent: 156,
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
      ),
      itemBuilder: (context, index) {
        final item = items[index];
        return _AdminModuleTile(
          item: item,
          favorite: _favorites.contains(item.id),
          onFavorite: () => setState(() {
            _favorites.contains(item.id)
                ? _favorites.remove(item.id)
                : _favorites.add(item.id);
          }),
          onOpen: () => _openItem(item),
        );
      },
    );
  }

  List<_WorkspaceItem> _workspaceItems(
    BuildContext context,
    List<_AdminModule> modules,
    Map<String, bool> features,
  ) {
    final items = <_WorkspaceItem>[];
    void add({
      required String id,
      required String title,
      required String subtitle,
      required IconData icon,
      required String category,
      required bool enabled,
      required Widget Function() screen,
    }) {
      items.add(
        _WorkspaceItem(
          id: id,
          title: title,
          subtitle: subtitle,
          icon: icon,
          category: category,
          enabled: enabled,
          open: screen,
        ),
      );
    }

    if (widget.user.hasPermission(AdminPermission.ticketView)) {
      add(
        id: 'support_tickets',
        title: 'Support Tickets',
        subtitle: 'Reply, close and reopen client requests',
        icon: Icons.support_agent_outlined,
        category: 'Operations',
        enabled: features['support_tickets'] ?? true,
        screen: () => SupportTicketsScreen(
          user: widget.user,
          repository: widget.repository,
        ),
      );
    }
    if (widget.user.hasPermission(AdminPermission.appointmentView)) {
      add(
        id: 'appointments',
        title: 'Appointment Slots',
        subtitle: 'Hosts, availability, bookings and approvals',
        icon: Icons.calendar_month_outlined,
        category: 'Operations',
        enabled: features['appointments'] ?? true,
        screen: () => TradingAppointmentsScreen(
          user: widget.user,
          repository: widget.repository,
        ),
      );
    }
    if (widget.user.hasPermission(AdminPermission.journalManage)) {
      add(
        id: 'journals',
        title: 'Trading Journals',
        subtitle: 'Review trader entries and add administration notes',
        icon: Icons.menu_book_outlined,
        category: 'Operations',
        enabled: features['trader_centre'] ?? true,
        screen: () => AdminJournalReviewScreen(repository: widget.repository),
      );
    }
    if (widget.user.hasPermission(AdminPermission.examResultReview) ||
        widget.user.hasPermission(AdminPermission.examManage)) {
      add(
        id: 'coursework',
        title: 'Coursework Management',
        subtitle: 'Themes, assignments, review queue, rubrics and results',
        icon: Icons.assignment_turned_in_outlined,
        category: 'Learning',
        enabled: features['trading_exams'] ?? true,
        screen: () => CourseworkAdminScreen(
          user: widget.user,
          tradingRepository: widget.repository,
        ),
      );
    }
    if (widget.user.hasPermission(AdminPermission.examResultReview)) {
      add(
        id: 'exam_results',
        title: 'Exam Results',
        subtitle: 'Review Daily Exam and Final Examination attempts',
        icon: Icons.fact_check_outlined,
        category: 'Learning',
        enabled: features['trading_exams'] ?? true,
        screen: () => AdminExamResultsScreen(repository: widget.repository),
      );
    }
    if (widget.user.hasPermission(AdminPermission.examManage)) {
      add(
        id: 'personality_questions',
        title: 'Personality Question Sets',
        subtitle: 'Version, preview and publish personality assessments',
        icon: Icons.psychology_alt_outlined,
        category: 'Learning',
        enabled: true,
        screen: () => AdminPersonalityQuestionSetsScreen(user: widget.user),
      );
    }
    for (final module in modules) {
      add(
        id: module.collection,
        title: module.title,
        subtitle: module.subtitle,
        icon: module.icon,
        category: _moduleCategory(module),
        enabled: features[module.featureKey] ?? true,
        screen: () => _AdminResourceScreen(
          user: widget.user,
          repository: widget.repository,
          module: module,
        ),
      );
    }
    return items;
  }

  void _openItem(_WorkspaceItem item) {
    if (!item.enabled) return;
    setState(() {
      _recent.remove(item.id);
      _recent.insert(0, item.id);
      if (_recent.length > 4) _recent.removeLast();
    });
    Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => item.open()));
  }

  Future<void> _refresh() async {
    setState(() => _features = widget.repository.featureConfiguration());
    await _features;
  }
}

class _WorkspaceItem {
  const _WorkspaceItem({
    required this.id,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.category,
    required this.enabled,
    required this.open,
  });

  final String id;
  final String title;
  final String subtitle;
  final IconData icon;
  final String category;
  final bool enabled;
  final Widget Function() open;
}

String _moduleCategory(_AdminModule module) {
  if (const {
    'trading_signals',
    'market_analyses',
  }.contains(module.collection)) {
    return 'Operations';
  }
  if (const {
    'knowledge_centres',
    'trading_recordings',
    'trading_exam_questions',
    'final_exam_questions',
  }.contains(module.collection)) {
    return 'Learning';
  }
  if (const {
    'trading_resources',
    'trading_news',
    'trading_blogs',
  }.contains(module.collection)) {
    return 'Content';
  }
  return 'Governance';
}

class AdminWorkspaceHeader extends StatelessWidget {
  const AdminWorkspaceHeader({
    super.key,
    required this.user,
    required this.searchController,
    required this.onSearchChanged,
    required this.onRefresh,
    required this.onCreateSignal,
  });

  final AppUser user;
  final TextEditingController searchController;
  final ValueChanged<String> onSearchChanged;
  final Future<void> Function() onRefresh;
  final VoidCallback? onCreateSignal;

  @override
  Widget build(BuildContext context) {
    return Card(
      key: const Key('admin-workspace-header'),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final wide = constraints.maxWidth >= 760;
            final title = Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text('Manage Backend'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${user.label} · ${context.l10n.roleName(user.roleId)}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            );
            final actions = Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                IconButton(
                  tooltip: context.l10n.text('Refresh'),
                  onPressed: onRefresh,
                  icon: const Icon(Icons.refresh),
                ),
                if (onCreateSignal != null)
                  FilledButton.icon(
                    onPressed: onCreateSignal,
                    icon: const Icon(Icons.add),
                    label: Text(context.l10n.text('New signal')),
                  ),
              ],
            );
            return Column(
              children: [
                if (wide)
                  Row(
                    children: [
                      Expanded(child: title),
                      actions,
                    ],
                  )
                else ...[
                  title,
                  const SizedBox(height: 10),
                  Align(alignment: Alignment.centerRight, child: actions),
                ],
                const SizedBox(height: 14),
                SearchBar(
                  key: const Key('admin-workspace-search'),
                  controller: searchController,
                  leading: const Icon(Icons.search),
                  hintText: context.l10n.text(
                    'Search modules, workflows or responsibilities',
                  ),
                  onChanged: onSearchChanged,
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class SignalOperationsPanel extends StatelessWidget {
  const SignalOperationsPanel({
    super.key,
    required this.metrics,
    required this.onOpen,
  });

  final Stream<SignalOperationsMetrics> metrics;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<SignalOperationsMetrics>(
      stream: metrics,
      builder: (context, snapshot) {
        final value = snapshot.data ?? const SignalOperationsMetrics.empty();
        return Card(
          key: const Key('signal-operations-panel'),
          color: Theme.of(
            context,
          ).colorScheme.primaryContainer.withValues(alpha: .36),
          child: InkWell(
            borderRadius: BorderRadius.circular(12),
            onTap: onOpen,
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        backgroundColor: Theme.of(context).colorScheme.primary,
                        foregroundColor: Theme.of(
                          context,
                        ).colorScheme.onPrimary,
                        child: const Icon(Icons.online_prediction_outlined),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              context.l10n.text('Signal operations'),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                            Text(
                              context.l10n.text(
                                snapshot.hasError
                                    ? 'Signal metrics are temporarily unavailable.'
                                    : 'Live operational status from Firestore',
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (snapshot.connectionState == ConnectionState.waiting)
                        const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      else
                        const Icon(Icons.arrow_forward),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      _OperationsMetric('Active', value.active),
                      _OperationsMetric('Pending entry', value.pendingEntry),
                      _OperationsMetric(
                        'Recent TP hits',
                        value.recentTargetHits,
                      ),
                      _OperationsMetric('Near expiry', value.nearExpiry),
                      _OperationsMetric('Stale', value.stale),
                      _OperationsMetric(
                        'Completed today',
                        value.completedToday,
                      ),
                      _OperationsMetric('Cancelled', value.cancelled),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

class _OperationsMetric extends StatelessWidget {
  const _OperationsMetric(this.label, this.value);

  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surface.withValues(alpha: .82),
      borderRadius: BorderRadius.circular(12),
    ),
    child: SizedBox(
      width: 128,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '$value',
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            Text(
              context.l10n.text(label),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    ),
  );
}

class _ModuleCategoryNavigation extends StatelessWidget {
  const _ModuleCategoryNavigation({
    required this.selected,
    required this.items,
    required this.onSelected,
    this.horizontal = false,
  });

  final String selected;
  final List<_WorkspaceItem> items;
  final ValueChanged<String> onSelected;
  final bool horizontal;

  @override
  Widget build(BuildContext context) {
    final categories = <String>{
      'All',
      ...items.map((item) => item.category),
    }.toList();
    final children = [
      for (final category in categories)
        Padding(
          padding: EdgeInsets.only(
            right: horizontal ? 8 : 0,
            bottom: horizontal ? 0 : 6,
          ),
          child: horizontal
              ? FilterChip(
                  selected: selected == category,
                  label: Text(context.l10n.text(category)),
                  onSelected: (_) => onSelected(category),
                )
              : ListTile(
                  selected: selected == category,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  leading: Icon(_categoryIcon(category)),
                  title: Text(context.l10n.text(category)),
                  trailing: Text(
                    '${category == 'All' ? items.length : items.where((item) => item.category == category).length}',
                  ),
                  onTap: () => onSelected(category),
                ),
        ),
    ];
    return Card(
      key: const Key('module-category-navigation'),
      child: Padding(
        padding: const EdgeInsets.all(10),
        child: horizontal
            ? SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(children: children),
              )
            : Column(children: children),
      ),
    );
  }
}

class _AdminModuleTile extends StatelessWidget {
  const _AdminModuleTile({
    required this.item,
    required this.favorite,
    required this.onFavorite,
    required this.onOpen,
  });

  final _WorkspaceItem item;
  final bool favorite;
  final VoidCallback onFavorite;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('admin-module-${item.id}'),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: item.enabled ? onOpen : null,
      child: Padding(
        padding: const EdgeInsets.all(15),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  item.icon,
                  color: item.enabled
                      ? Theme.of(context).colorScheme.primary
                      : Theme.of(context).disabledColor,
                ),
                const Spacer(),
                IconButton(
                  visualDensity: VisualDensity.compact,
                  tooltip: context.l10n.text(
                    favorite ? 'Remove favorite' : 'Add favorite',
                  ),
                  onPressed: onFavorite,
                  icon: Icon(
                    favorite ? Icons.star : Icons.star_border,
                    size: 20,
                  ),
                ),
              ],
            ),
            Text(
              context.l10n.text(item.title),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Expanded(
              child: Text(
                context.l10n.text(
                  item.enabled
                      ? item.subtitle
                      : 'Disabled in feature configuration',
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            Row(
              children: [
                Text(
                  context.l10n.text(item.category),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                const Spacer(),
                Icon(
                  item.enabled
                      ? Icons.arrow_forward
                      : Icons.visibility_off_outlined,
                  size: 18,
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _RecentModules extends StatelessWidget {
  const _RecentModules({required this.items, required this.onOpen});

  final List<_WorkspaceItem> items;
  final ValueChanged<_WorkspaceItem> onOpen;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text('Recently opened'),
        style: Theme.of(context).textTheme.titleSmall,
      ),
      const SizedBox(height: 8),
      SizedBox(
        height: 42,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: items.length,
          separatorBuilder: (_, _) => const SizedBox(width: 8),
          itemBuilder: (context, index) {
            final item = items[index];
            return ActionChip(
              avatar: Icon(item.icon, size: 17),
              label: Text(context.l10n.text(item.title)),
              onPressed: item.enabled ? () => onOpen(item) : null,
            );
          },
        ),
      ),
    ],
  );
}

class _AdminWorkspaceEmptyState extends StatelessWidget {
  const _AdminWorkspaceEmptyState();

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        children: [
          const Icon(Icons.search_off_outlined, size: 42),
          const SizedBox(height: 8),
          Text(context.l10n.text('No permitted modules match your search.')),
        ],
      ),
    ),
  );
}

IconData _categoryIcon(String category) => switch (category) {
  'Operations' => Icons.monitor_heart_outlined,
  'Learning' => Icons.school_outlined,
  'Content' => Icons.edit_note_outlined,
  'Governance' => Icons.admin_panel_settings_outlined,
  _ => Icons.grid_view_outlined,
};

class _AdminResourceScreen extends StatefulWidget {
  const _AdminResourceScreen({
    required this.user,
    required this.repository,
    required this.module,
  });

  final AppUser user;
  final TradingRepository repository;
  final _AdminModule module;

  @override
  State<_AdminResourceScreen> createState() => _AdminResourceScreenState();
}

class _AdminResourceScreenState extends State<_AdminResourceScreen> {
  final _search = TextEditingController();
  String _status = 'all';
  int _page = 1;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _reload() => setState(() {});

  bool get _canMutate {
    final authorization = PublishingAuthorization(widget.user);
    return switch (widget.module.collection) {
      'trading_signals' =>
        authorization.canPublishTradingSignal ||
            authorization.canManageTradingSignals,
      'market_analyses' =>
        authorization.canPublishMarketAnalysis ||
            authorization.canManageMarketAnalysis,
      _ => widget.user.isAdmin,
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text(widget.module.title))),
      floatingActionButton: _canMutate
          ? FloatingActionButton.extended(
              onPressed: () => _edit(),
              icon: const Icon(Icons.add),
              label: Text(context.l10n.text('Add')),
            )
          : null,
      body: StreamBuilder<List<Map<String, dynamic>>>(
        stream: widget.repository.adminResourceRecordsStream(
          widget.module.collection,
        ),
        builder: (context, snapshot) {
          if (!snapshot.hasData && !snapshot.hasError) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(error: snapshot.error, onRetry: _reload);
          }
          final canViewAllSignals =
              widget.module.collection == 'trading_signals' &&
              PublishingAuthorization(widget.user).canManageAllTradingSignals;
          final visibleRows = widget.user.isAdmin || canViewAllSignals
              ? snapshot.data!
              : snapshot.data!
                    .where((row) => '${row['created_by']}' == widget.user.uid)
                    .toList();
          final query = _search.text.trim().toLowerCase();
          final filtered = visibleRows.where((row) {
            final matchesQuery =
                query.isEmpty ||
                [
                  row['title'],
                  row['name'],
                  row['question_text'],
                  row['category'],
                ].any((value) => '$value'.toLowerCase().contains(query));
            final value = _statusLabel(row);
            final matchesStatus = _status == 'all' || '$value' == _status;
            return matchesQuery && matchesStatus;
          }).toList();
          const pageSize = 20;
          final lastPage = (filtered.length / pageSize).ceil().clamp(1, 9999);
          if (_page > lastPage) _page = lastPage;
          final rows = filtered.skip((_page - 1) * pageSize).take(pageSize);
          return RefreshIndicator(
            onRefresh: () async => _reload(),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 92),
              children: [
                SearchBar(
                  controller: _search,
                  hintText: context.l10n.text('Search title or category'),
                  leading: const Icon(Icons.search),
                  onChanged: (_) => setState(() => _page = 1),
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  initialValue: _status,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Status'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'all',
                      child: Text(context.l10n.text('All statuses')),
                    ),
                    DropdownMenuItem(
                      value: 'draft',
                      child: Text(context.l10n.text('Draft')),
                    ),
                    DropdownMenuItem(
                      value: 'published',
                      child: Text(context.l10n.text('Published')),
                    ),
                    DropdownMenuItem(
                      value: 'active',
                      child: Text(context.l10n.text('Active')),
                    ),
                    DropdownMenuItem(
                      value: 'approved',
                      child: Text(context.l10n.text('Approved')),
                    ),
                    DropdownMenuItem(
                      value: 'archived',
                      child: Text(context.l10n.text('Archived')),
                    ),
                  ],
                  onChanged: (value) => setState(() {
                    _status = value!;
                    _page = 1;
                  }),
                ),
                const SizedBox(height: 12),
                Text(
                  context.l10n.text('{count} record(s)', {
                    'count': filtered.length,
                  }),
                ),
                if (filtered.isEmpty)
                  const _EmptyState()
                else
                  for (final row in rows)
                    _ResourceCard(
                      row: row,
                      onView: () => _details(row),
                      onEdit: _canMutate ? () => _edit(row) : null,
                      onDelete:
                          _canMutate &&
                              widget.module.collection != 'trading_signals'
                          ? () => _archiveOrRestore(row)
                          : null,
                    ),
                if (lastPage > 1)
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      IconButton(
                        onPressed: _page > 1
                            ? () => setState(() => _page--)
                            : null,
                        icon: const Icon(Icons.chevron_left),
                      ),
                      Text('Page $_page of $lastPage'),
                      IconButton(
                        onPressed: _page < lastPage
                            ? () => setState(() => _page++)
                            : null,
                        icon: const Icon(Icons.chevron_right),
                      ),
                    ],
                  ),
              ],
            ),
          );
        },
      ),
    );
  }

  Future<void> _edit([Map<String, dynamic>? row]) async {
    if (!_canMutate) {
      AppFeedback.error(
        context,
        context.l10n.text(
          'Your account is not permitted to publish this content. Please contact a Super Admin.',
        ),
        operation: 'open publisher',
      );
      return;
    }
    if (widget.module.collection == 'trading_signals') {
      if (row == null) {
        await showSignalPublisher(
          context,
          user: widget.user,
          repository: widget.repository,
        );
      } else {
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => TradingSignalManagementScreen(
              user: widget.user,
              repository: widget.repository,
              signalId: '${row['id']}',
            ),
          ),
        );
      }
      return;
    }
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _ResourceEditor(
        repository: widget.repository,
        module: widget.module,
        row: row,
      ),
    );
    if (saved == true && mounted) {
      AppFeedback.success(
        context,
        row == null ? 'Record created.' : 'Record updated.',
      );
    }
  }

  Future<void> _archiveOrRestore(Map<String, dynamic> row) async {
    if (!_canMutate) {
      AppFeedback.error(
        context,
        context.l10n.text(
          'Your account is not permitted to publish this content. Please contact a Super Admin.',
        ),
        operation: 'modify publisher content',
      );
      return;
    }
    final archived = _statusLabel(row) == 'archived';
    if (archived) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Restore this record?'),
          content: const Text(
            'The record will become available again using its previous status.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: const Text('Restore'),
            ),
          ],
        ),
      );
      if (confirmed != true) return;
      try {
        await widget.repository.adminRestoreResource(
          collection: widget.module.collection,
          id: '${row['id']}',
        );
        if (mounted) AppFeedback.success(context, 'Record restored.');
      } catch (error) {
        if (mounted) {
          AppFeedback.error(context, error, operation: 'restore record');
        }
      }
      return;
    }
    final reason = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          widget.user.isSuperAdmin
              ? 'Archive this record?'
              : 'Request deletion?',
        ),
        content: TextField(
          controller: reason,
          maxLines: 3,
          decoration: const InputDecoration(
            labelText: 'Reason',
            alignLabelWithHint: true,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed != true || reason.text.trim().isEmpty) {
      reason.dispose();
      return;
    }
    try {
      final requiresRequest =
          widget.module.collection == 'market_analyses' &&
          !widget.user.isSuperAdmin;
      if (requiresRequest) {
        await widget.repository.submitDeleteRequest(
          targetType: widget.module.collection,
          targetId: '${row['id']}',
          reason: reason.text,
        );
      } else {
        await widget.repository.adminArchiveResource(
          collection: widget.module.collection,
          id: '${row['id']}',
          reason: reason.text,
        );
      }
      if (mounted) {
        AppFeedback.success(
          context,
          requiresRequest ? 'Delete request submitted.' : 'Record archived.',
        );
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'archive record');
      }
    } finally {
      reason.dispose();
    }
  }

  Future<void> _details(Map<String, dynamic> row) async {
    if (widget.module.collection == 'trading_signals') {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => TradingSignalManagementScreen(
            user: widget.user,
            repository: widget.repository,
            signalId: '${row['id']}',
          ),
        ),
      );
      return;
    }
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: .8,
        minChildSize: .45,
        maxChildSize: .95,
        builder: (_, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
          children: [
            Text(
              _recordTitle(row),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            for (final field in widget.module.fields)
              if ('${row[field.key] ?? ''}'.isNotEmpty)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text(field.label)),
                  subtitle: Text('${row[field.key]}'),
                  trailing: _looksLikeUrl('${row[field.key]}')
                      ? IconButton(
                          icon: const Icon(Icons.open_in_new),
                          onPressed: () => _openUrl('${row[field.key]}'),
                        )
                      : null,
                ),
          ],
        ),
      ),
    );
  }
}

class _ResourceCard extends StatelessWidget {
  const _ResourceCard({
    required this.row,
    required this.onView,
    required this.onEdit,
    required this.onDelete,
  });
  final Map<String, dynamic> row;
  final VoidCallback onView;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  @override
  Widget build(BuildContext context) {
    final status = _statusLabel(row);
    final archived = status == 'archived';
    return Card(
      child: ListTile(
        onTap: onView,
        title: Text(
          _recordTitle(row),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Text(
          '${row['category'] ?? row['market'] ?? ''}\n${context.l10n.text(_pretty('$status'))}',
        ),
        isThreeLine: true,
        trailing: PopupMenuButton<String>(
          onSelected: (value) => value == 'view'
              ? onView()
              : value == 'edit'
              ? onEdit?.call()
              : onDelete?.call(),
          itemBuilder: (_) => [
            PopupMenuItem(
              value: 'view',
              child: Text(context.l10n.text('View')),
            ),
            if (onEdit != null)
              PopupMenuItem(
                value: 'edit',
                child: Text(context.l10n.text('Edit')),
              ),
            if (onDelete != null)
              PopupMenuItem(
                value: 'delete',
                child: Text(
                  context.l10n.text(archived ? 'Restore' : 'Archive / delete'),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ResourceEditor extends StatefulWidget {
  const _ResourceEditor({
    required this.repository,
    required this.module,
    this.row,
  });
  final TradingRepository repository;
  final _AdminModule module;
  final Map<String, dynamic>? row;

  @override
  State<_ResourceEditor> createState() => _ResourceEditorState();
}

class _ResourceEditorState extends State<_ResourceEditor> {
  final _formKey = GlobalKey<FormState>();
  final _controllers = <String, TextEditingController>{};
  final _values = <String, Object?>{};
  bool _saving = false;
  bool _uploading = false;
  PublishingLanguage? _primaryLanguage;

  bool get _isPublisherModule => const {
    'trading_signals',
    'market_analyses',
  }.contains(widget.module.collection);

  PublishingLanguage get _resolvedPrimaryLanguage =>
      _primaryLanguage ?? PublishingLanguage.english;

  @override
  void initState() {
    super.initState();
    for (final field in widget.module.fields) {
      final raw = widget.row?[field.key];
      if (field.kind == _FieldKind.toggle) {
        _values[field.key] = raw is bool ? raw : raw != false;
      } else if (field.kind == _FieldKind.select) {
        final value = '$raw';
        _values[field.key] = field.options.contains(value)
            ? value
            : field.options.firstOrNull;
      } else {
        final bilingual = _isManualBilingualField(
          widget.module.collection,
          field.key,
        );
        _controllers[field.key] = TextEditingController(
          text: bilingual
              ? _adminLocalizedField(widget.row, field.key, 'en')
              : raw == null
              ? ''
              : '$raw',
        );
        if (bilingual) {
          _controllers[_zhKey(field.key)] = TextEditingController(
            text: _adminLocalizedField(widget.row, field.key, 'zh'),
          );
        }
      }
    }
    _restoreExamOptions();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_primaryLanguage != null) return;
    final stored = '${widget.row?['primary_language'] ?? ''}'
        .trim()
        .toLowerCase();
    _primaryLanguage = stored.startsWith('zh')
        ? PublishingLanguage.simplifiedChinese
        : stored == 'en'
        ? PublishingLanguage.english
        : publishingLanguageForLocale(AppLocalizations.of(context).locale);
  }

  void _restoreExamOptions() {
    final raw = widget.row?['options'];
    if (raw is Map) {
      for (final key in const ['A', 'B', 'C', 'D']) {
        final field = 'option_${key.toLowerCase()}';
        final english = _adminOptionLocalizedField(widget.row, key, 'en');
        final value = raw[key];
        _controllers[field]?.text = english.isNotEmpty
            ? english
            : value is Map
            ? '${value['text'] ?? value['option_text'] ?? ''}'
            : '${value ?? ''}';
        _controllers[_zhKey('option_${key.toLowerCase()}')]?.text =
            _adminOptionLocalizedField(widget.row, key, 'zh');
      }
    } else if (raw is List) {
      for (var index = 0; index < raw.length && index < 4; index++) {
        final value = raw[index];
        final optionField = 'option_${String.fromCharCode(97 + index)}';
        _controllers[optionField]?.text = value is Map
            ? '${value['text'] ?? value['option_text'] ?? ''}'
            : '$value';
        _controllers[_zhKey(optionField)]?.text = _adminOptionLocalizedField(
          widget.row,
          String.fromCharCode(65 + index),
          'zh',
        );
      }
    }
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(
      widget.row == null
          ? context.l10n.text('Add {module}', {
              'module': context.l10n.text(widget.module.title),
            })
          : context.l10n.text('Edit {module}', {
              'module': context.l10n.text(widget.module.title),
            }),
    ),
    content: SizedBox(
      width: 560,
      child: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (_isPublisherModule) ...[
                ContentLanguageSelector(
                  primaryLanguage: _resolvedPrimaryLanguage,
                  onChanged: (value) =>
                      setState(() => _primaryLanguage = value),
                ),
                const SizedBox(height: 12),
              ],
              for (final field in widget.module.fields) ...[
                _field(field),
                const SizedBox(height: 12),
              ],
              OutlinedButton.icon(
                onPressed: _saving || _uploading ? null : _upload,
                icon: _uploading
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.upload_file_outlined),
                label: Text(context.l10n.text('Upload image, document or MP4')),
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

  Widget _field(_AdminField field) {
    if (field.kind == _FieldKind.toggle) {
      return SwitchListTile.adaptive(
        contentPadding: EdgeInsets.zero,
        title: Text(context.l10n.text(field.label)),
        value: _values[field.key] != false,
        onChanged: (value) => setState(() => _values[field.key] = value),
      );
    }
    if (field.kind == _FieldKind.select) {
      return DropdownButtonFormField<String>(
        initialValue: _values[field.key] as String?,
        decoration: InputDecoration(labelText: context.l10n.text(field.label)),
        items: [
          for (final option in field.options)
            DropdownMenuItem(
              value: option,
              child: Text(context.l10n.text(_pretty(option))),
            ),
        ],
        onChanged: (value) => _values[field.key] = value,
        validator: field.required
            ? (value) => value == null ? context.l10n.text('Required') : null
            : null,
      );
    }
    final bilingual = _isManualBilingualField(
      widget.module.collection,
      field.key,
    );
    if (bilingual) {
      if (_isPublisherModule) {
        final primaryIsChinese =
            _resolvedPrimaryLanguage == PublishingLanguage.simplifiedChinese;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _textField(
              field,
              languageLabel: primaryIsChinese
                  ? 'Simplified Chinese'
                  : 'English',
              chinese: primaryIsChinese,
              primary: true,
            ),
            ExpansionTile(
              tilePadding: EdgeInsets.zero,
              title: Text(
                context.l10n.text('Add optional {language} translation', {
                  'language': context.l10n.text(
                    primaryIsChinese ? 'English' : 'Simplified Chinese',
                  ),
                }),
              ),
              children: [
                _textField(
                  field,
                  languageLabel: primaryIsChinese
                      ? 'English'
                      : 'Simplified Chinese',
                  chinese: !primaryIsChinese,
                ),
              ],
            ),
          ],
        );
      }
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _textField(field, languageLabel: 'English'),
          const SizedBox(height: 10),
          _textField(field, languageLabel: 'Simplified Chinese', chinese: true),
        ],
      );
    }
    return _textField(field);
  }

  Widget _textField(
    _AdminField field, {
    String? languageLabel,
    bool chinese = false,
    bool primary = false,
  }) {
    final controllerKey = chinese ? _zhKey(field.key) : field.key;
    final label = languageLabel == null
        ? context.l10n.text(field.label)
        : '${context.l10n.text(field.label)} (${context.l10n.text(languageLabel)})';
    return TextFormField(
      controller: _controllers[controllerKey],
      minLines: field.kind == _FieldKind.longText ? 3 : 1,
      maxLines: field.kind == _FieldKind.longText ? 8 : 1,
      keyboardType: field.kind == _FieldKind.number
          ? const TextInputType.numberWithOptions(decimal: true)
          : TextInputType.text,
      decoration: InputDecoration(
        labelText: label,
        helperText: chinese
            ? context.l10n.text(
                'Optional reviewed Chinese text. Falls back to English when empty.',
              )
            : field.helperText == null
            ? null
            : context.l10n.text(field.helperText!),
        alignLabelWithHint: field.kind == _FieldKind.longText,
      ),
      validator: (value) =>
          _validateTextField(field, value, chinese: chinese, primary: primary),
    );
  }

  String? _validateTextField(
    _AdminField field,
    String? value, {
    required bool chinese,
    bool primary = false,
  }) {
    final bilingual = _isManualBilingualField(
      widget.module.collection,
      field.key,
    );
    final current = (value ?? '').trim();
    if (!bilingual) {
      return field.required && current.isEmpty
          ? context.l10n.text('Required')
          : null;
    }
    if (_isPublisherModule) {
      if (!primary || !field.required) return null;
      return current.isEmpty
          ? context.l10n.text('{language} content is required.', {
              'language': context.l10n.text(
                chinese ? 'Simplified Chinese' : 'English',
              ),
            })
          : null;
    }
    if (chinese) return null;
    final chineseText = _controllers[_zhKey(field.key)]?.text.trim() ?? '';
    final requiredField = field.required || chineseText.isNotEmpty;
    if (requiredField && current.isEmpty) {
      return context.l10n.text('English content is required.');
    }
    return null;
  }

  Future<void> _upload() async {
    final selected = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'pdf',
        'doc',
        'docx',
        'mp4',
      ],
      withData: true,
    );
    if (selected == null || selected.files.isEmpty) return;
    final file = selected.files.single;
    setState(() => _uploading = true);
    try {
      final uploaded = await widget.repository.uploadManagedFile(
        area: 'admin_assets',
        file: ApiUploadFile(
          field: 'file',
          filename: file.name,
          path: file.path,
          bytes: file.bytes,
        ),
      );
      const preferredKeys = [
        'thumbnail_url',
        'image_url',
        'chart_image_url',
        'outlook_image',
        'file_path',
        'download_url',
        'video_url',
        'url',
      ];
      final key = preferredKeys.firstWhere(
        (candidate) => _controllers.containsKey(candidate),
        orElse: () => '',
      );
      if (key.isNotEmpty) {
        _controllers[key]!.text = '${uploaded['url']}';
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.text('File uploaded.'))),
        );
      }
    } catch (error) {
      if (mounted) _showError(context, error);
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    try {
      final values = <String, Object?>{..._values};
      for (final field in widget.module.fields) {
        final controller = _controllers[field.key];
        if (controller == null) continue;
        final text = controller.text.trim();
        values[field.key] = field.kind == _FieldKind.number
            ? num.tryParse(text)
            : field.key == 'tags'
            ? text
                  .split(',')
                  .map((tag) => tag.trim())
                  .where((tag) => tag.isNotEmpty)
                  .toList()
            : text;
        final zhController = _controllers[_zhKey(field.key)];
        if (zhController != null) {
          values[_zhKey(field.key)] = zhController.text.trim();
        }
      }
      if (_isPublisherModule) {
        values['primary_language'] = _resolvedPrimaryLanguage.code;
      }
      if ('${values['slug'] ?? ''}'.isEmpty) {
        final source = '${values['title'] ?? values['name'] ?? ''}';
        if (source.isNotEmpty) values['slug'] = _slug(source);
      }
      if (widget.module.collection == 'trading_exam_questions' ||
          widget.module.collection == 'final_exam_questions') {
        final correct = '${values['correct_answer'] ?? 'A'}';
        values['options'] = {
          for (final key in const ['A', 'B', 'C', 'D'])
            if ('${values['option_${key.toLowerCase()}'] ?? ''}'.isNotEmpty)
              key: {
                'id': key,
                'option_text': values['option_${key.toLowerCase()}'],
                'text': values['option_${key.toLowerCase()}'],
                'is_correct': correct == key,
              },
        };
        if (widget.module.collection == 'trading_exam_questions') {
          values['question_type'] =
              '${values['question_type'] ?? ''}'.trim().isEmpty
              ? 'multiple_choice'
              : values['question_type'];
          values['version'] = values['version'] is num ? values['version'] : 1;
          values['maximum_attempts'] = values['maximum_attempts'] is num
              ? values['maximum_attempts']
              : 1;
          values['minimum_explanation_characters'] =
              values['minimum_explanation_characters'] is num
              ? values['minimum_explanation_characters']
              : 40;
        }
      }
      switch (widget.module.collection) {
        case 'trading_resources':
          final publication = '${values['publication_status'] ?? 'draft'}';
          values['status'] = publication == 'published';
          break;
        case 'knowledge_centres':
          final publication = '${values['status_label'] ?? 'draft'}';
          values['status'] = publication == 'published';
          values['approval_status'] = publication == 'published'
              ? 'approved'
              : publication;
          break;
        case 'trading_recordings':
          values['approval_status'] = values['status'] == true
              ? 'approved'
              : 'draft';
          break;
        case 'trading_signals':
          final lifecycle = '${values['lifecycle_status'] ?? 'draft'}';
          values['status'] = TradingSignalPublicationStatus.fromValue(
            lifecycle,
          ).legacyCode;
          for (var index = 1; index <= 3; index++) {
            values['target_$index'] = values['take_profit_$index'];
          }
          break;
        case 'trading_blogs':
          values['cover_image'] = values['thumbnail_url'];
          break;
        default:
          break;
      }
      await widget.repository.adminSaveResource(
        collection: widget.module.collection,
        id: widget.row?['id']?.toString(),
        values: values,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showError(context, error);
    }
  }
}

class AdminJournalReviewScreen extends StatefulWidget {
  const AdminJournalReviewScreen({super.key, required this.repository});
  final TradingRepository repository;

  @override
  State<AdminJournalReviewScreen> createState() =>
      _AdminJournalReviewScreenState();
}

class _AdminJournalReviewScreenState extends State<AdminJournalReviewScreen> {
  final _search = TextEditingController();
  String _result = 'all';

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Trading journal management')),
    body: StreamBuilder<List<Map<String, dynamic>>>(
      stream: widget.repository.adminTradingJournalsStream(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ErrorState(
            error: snapshot.error,
            onRetry: () => setState(() {}),
          );
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final query = _search.text.trim().toLowerCase();
        final rows = snapshot.data!.where((row) {
          final user = row['user'] is Map
              ? Map<String, dynamic>.from(row['user'] as Map)
              : const <String, dynamic>{};
          final profit = _number(row['profit_loss'] ?? row['profit']);
          final matchesResult =
              _result == 'all' ||
              (_result == 'win' && profit > 0) ||
              (_result == 'loss' && profit < 0) ||
              (_result == 'breakeven' && profit == 0);
          return matchesResult &&
              (query.isEmpty ||
                  [
                    user['name'],
                    user['email'],
                    row['pair'],
                    row['strategy'],
                  ].any((value) => '$value'.toLowerCase().contains(query)));
        }).toList();
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            SearchBar(
              controller: _search,
              hintText: 'Search trader, pair or strategy',
              leading: const Icon(Icons.search),
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _result,
              decoration: const InputDecoration(labelText: 'Result'),
              items: const [
                DropdownMenuItem(value: 'all', child: Text('All results')),
                DropdownMenuItem(value: 'win', child: Text('Wins')),
                DropdownMenuItem(value: 'loss', child: Text('Losses')),
                DropdownMenuItem(value: 'breakeven', child: Text('Breakeven')),
              ],
              onChanged: (value) => setState(() => _result = value!),
            ),
            const SizedBox(height: 12),
            if (rows.isEmpty) const _EmptyState(),
            for (final row in rows)
              Card(
                child: ListTile(
                  title: Text(
                    '${row['pair'] ?? 'Trade'} - ${_journalUser(row)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  subtitle: Text(
                    'Entry ${row['entry_price'] ?? '-'} - Exit ${row['exit_price'] ?? '-'}\nP/L ${row['profit_loss'] ?? row['profit'] ?? 0}',
                  ),
                  isThreeLine: true,
                  trailing: IconButton(
                    tooltip: 'Administration note',
                    onPressed: () => _note(row),
                    icon: const Icon(Icons.note_add_outlined),
                  ),
                ),
              ),
          ],
        );
      },
    ),
  );

  Future<void> _note(Map<String, dynamic> row) async {
    final note = TextEditingController(text: '${row['admin_note'] ?? ''}');
    final save = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: const Text('Administration note'),
        content: TextField(
          controller: note,
          minLines: 3,
          maxLines: 6,
          decoration: const InputDecoration(
            labelText: 'Private review note',
            alignLabelWithHint: true,
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Save'),
          ),
        ],
      ),
    );
    if (save == true) {
      await widget.repository.adminSaveJournalNote(
        documentPath: '${row['document_path']}',
        note: note.text,
      );
      if (mounted) {
        AppFeedback.success(context, 'Administration note saved.');
      }
    }
    note.dispose();
  }
}

class AdminExamResultsScreen extends StatefulWidget {
  const AdminExamResultsScreen({super.key, required this.repository});
  final TradingRepository repository;

  @override
  State<AdminExamResultsScreen> createState() => _AdminExamResultsScreenState();
}

class _AdminExamResultsScreenState extends State<AdminExamResultsScreen> {
  late Future<List<Map<String, dynamic>>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.repository.adminExamAttempts();
  }

  void _reload() => setState(() {
    _future = widget.repository.adminExamAttempts();
  });

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Exam result review')),
    body: FutureBuilder<List<Map<String, dynamic>>>(
      future: _future,
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final rows = snapshot.data!;
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (rows.isEmpty) const _EmptyState(),
            for (final row in rows)
              Card(
                child: ListTile(
                  leading: CircleAvatar(
                    child: Icon(
                      '${row['status']}'.contains('pass')
                          ? Icons.check
                          : Icons.school_outlined,
                    ),
                  ),
                  title: Text('${row['exam_type']} - ${_journalUser(row)}'),
                  subtitle: Text(
                    'Score: ${row['score_percentage'] ?? row['score'] ?? 0}${row['score_percentage'] != null ? '%' : ''}\nStatus: ${_pretty('${row['status'] ?? 'completed'}')}',
                  ),
                  isThreeLine: true,
                  trailing: '${row['status']}' == 'pending_review'
                      ? const Chip(label: Text('Pending review'))
                      : const Icon(Icons.chevron_right),
                  onTap: () async {
                    if ('${row['status']}' == 'pending_review' &&
                        '${row['exam_type']}' == 'Trading Exam') {
                      final reviewed = await _reviewPendingDailyQuiz(
                        context,
                        row,
                        widget.repository,
                      );
                      if (reviewed && mounted) _reload();
                      return;
                    }
                    await _showExamResult(context, row);
                  },
                ),
              ),
          ],
        );
      },
    ),
  );
}

Future<bool> _reviewPendingDailyQuiz(
  BuildContext context,
  Map<String, dynamic> row,
  TradingRepository repository,
) async {
  final rawResults = (row['question_results'] as Iterable? ?? const [])
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .where((item) => item['review_status'] == 'pending_review')
      .toList(growable: false);
  if (rawResults.isEmpty) {
    AppFeedback.error(context, 'No written responses require review.');
    return false;
  }
  final scores = <String, double>{
    for (final result in rawResults) '${result['question_id']}': 1,
  };
  final feedback = <String, TextEditingController>{
    for (final result in rawResults)
      '${result['question_id']}': TextEditingController(),
  };
  final note = TextEditingController();
  var saving = false;
  var reviewed = false;
  try {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => ListView(
          padding: EdgeInsets.fromLTRB(
            20,
            0,
            20,
            MediaQuery.viewInsetsOf(context).bottom + 24,
          ),
          children: [
            Text(
              context.l10n.text('Review daily chart analysis'),
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text('${context.l10n.text('Candidate')}: ${_journalUser(row)}'),
            const SizedBox(height: 14),
            for (final result in rawResults) ...[
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        '${result['question_text'] ?? result['question_id']}',
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 8),
                      if ('${result['screenshot_url'] ?? ''}'.trim().isNotEmpty)
                        ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: Image.network(
                            '${result['screenshot_url']}',
                            height: 220,
                            fit: BoxFit.contain,
                            errorBuilder: (_, _, _) => const SizedBox(
                              height: 80,
                              child: Center(
                                child: Icon(Icons.broken_image_outlined),
                              ),
                            ),
                          ),
                        ),
                      const SizedBox(height: 10),
                      SelectableText('${result['written_explanation'] ?? ''}'),
                      const SizedBox(height: 12),
                      Text(
                        context.l10n.text('Reviewer score: {score}%', {
                          'score':
                              ((scores['${result['question_id']}'] ?? 1) * 100)
                                  .round(),
                        }),
                      ),
                      Slider(
                        value: scores['${result['question_id']}'] ?? 1,
                        divisions: 4,
                        label:
                            '${((scores['${result['question_id']}'] ?? 1) * 100).round()}%',
                        onChanged: saving
                            ? null
                            : (value) => setSheetState(
                                () =>
                                    scores['${result['question_id']}'] = value,
                              ),
                      ),
                      TextField(
                        controller: feedback['${result['question_id']}'],
                        minLines: 2,
                        maxLines: 5,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Feedback to candidate'),
                          alignLabelWithHint: true,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 10),
            ],
            TextField(
              controller: note,
              minLines: 2,
              maxLines: 5,
              decoration: InputDecoration(
                labelText: context.l10n.text('Internal review note'),
                alignLabelWithHint: true,
              ),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: saving
                  ? null
                  : () async {
                      setSheetState(() => saving = true);
                      try {
                        await repository.adminReviewDailyQuiz(
                          userId: '${row['user_id']}',
                          attemptId: '${row['id']}',
                          scores: scores,
                          feedback: {
                            for (final entry in feedback.entries)
                              entry.key: entry.value.text,
                          },
                          reviewNote: note.text,
                        );
                        reviewed = true;
                        if (sheetContext.mounted) Navigator.pop(sheetContext);
                      } catch (error) {
                        if (context.mounted) {
                          setSheetState(() => saving = false);
                          AppFeedback.error(context, error);
                        }
                      }
                    },
              icon: saving
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.fact_check_outlined),
              label: Text(context.l10n.text('Complete review')),
            ),
          ],
        ),
      ),
    );
  } finally {
    note.dispose();
    for (final controller in feedback.values) {
      controller.dispose();
    }
  }
  if (reviewed && context.mounted) {
    AppFeedback.success(context, 'Daily quiz review completed.');
  }
  return reviewed;
}

Future<void> _showExamResult(
  BuildContext context,
  Map<String, dynamic> row,
) async {
  final questions = _examReviewQuestions(row, context.l10n.locale.languageCode);
  await showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    builder: (context) => SafeArea(
      child: ListView(
        shrinkWrap: true,
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 28),
        children: [
          Text(
            '${row['exam_type']} result',
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          ListTile(
            title: const Text('Candidate'),
            subtitle: Text(_journalUser(row)),
          ),
          ListTile(
            title: const Text('Total questions'),
            subtitle: Text('${row['total_questions'] ?? 0}'),
          ),
          ListTile(
            title: const Text('Correct answers'),
            subtitle: Text('${row['correct_answers'] ?? row['score'] ?? 0}'),
          ),
          ListTile(
            title: const Text('Status'),
            subtitle: Text(_pretty('${row['status'] ?? 'completed'}')),
          ),
          if (questions.isEmpty)
            ListTile(
              title: Text(context.l10n.text('Submitted answers')),
              subtitle: Text('${row['answers'] ?? '-'}'),
            )
          else ...[
            const SizedBox(height: 12),
            Text(
              context.l10n.text('Question audit'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            for (final question in questions)
              _ExamAuditQuestionCard(question: question),
          ],
        ],
      ),
    ),
  );
}

class _ExamAuditQuestionCard extends StatelessWidget {
  const _ExamAuditQuestionCard({required this.question});

  final _ExamReviewQuestion question;

  @override
  Widget build(BuildContext context) {
    final selected = question.selectedKey;
    final correct = question.correctKey;
    final isCorrect = question.isCorrect;
    final tone = isCorrect
        ? const Color(0xFF17855B)
        : Theme.of(context).colorScheme.error;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  isCorrect ? Icons.check_circle : Icons.cancel_rounded,
                  color: tone,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    '${question.position}. ${question.questionText}',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            if (question.options.isEmpty)
              Text(context.l10n.text('No answer options available.'))
            else
              for (final entry in question.options.entries)
                _ExamOptionAuditRow(
                  optionKey: entry.key,
                  text: entry.value,
                  selected: selected == entry.key,
                  correct: correct == entry.key,
                ),
            const SizedBox(height: 10),
            _AuditLine(
              label: context.l10n.text('Candidate selected answer'),
              value: _answerLabel(context, selected, question.options),
              color: tone,
            ),
            const SizedBox(height: 6),
            _AuditLine(
              label: context.l10n.text('Correct answer'),
              value: _answerLabel(context, correct, question.options),
              color: const Color(0xFF17855B),
            ),
            const SizedBox(height: 6),
            _AuditLine(
              label: context.l10n.text('Result'),
              value: context.l10n.text(isCorrect ? 'Correct' : 'Wrong'),
              color: tone,
            ),
            if (question.explanation.trim().isNotEmpty) ...[
              const SizedBox(height: 10),
              Text(
                context.l10n.text('Explanation'),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              Text(question.explanation),
            ],
          ],
        ),
      ),
    );
  }

  String _answerLabel(
    BuildContext context,
    String? key,
    Map<String, String> options,
  ) {
    if (key == null || key.isEmpty) return context.l10n.text('Not answered');
    final text = options[key];
    return text == null || text.trim().isEmpty ? key : '$key. $text';
  }
}

class _ExamOptionAuditRow extends StatelessWidget {
  const _ExamOptionAuditRow({
    required this.optionKey,
    required this.text,
    required this.selected,
    required this.correct,
  });

  final String optionKey;
  final String text;
  final bool selected;
  final bool correct;

  @override
  Widget build(BuildContext context) {
    final color = correct
        ? const Color(0xFF17855B)
        : selected
        ? Theme.of(context).colorScheme.error
        : Theme.of(context).colorScheme.outline;
    return Container(
      margin: const EdgeInsets.only(bottom: 6),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: .45)),
        color: color.withValues(alpha: selected || correct ? .08 : .02),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$optionKey.',
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(width: 8),
          Expanded(child: Text(text)),
          if (selected) _SmallAuditBadge(label: context.l10n.text('Selected')),
          if (correct) ...[
            const SizedBox(width: 5),
            _SmallAuditBadge(label: context.l10n.text('Correct')),
          ],
        ],
      ),
    );
  }
}

class _SmallAuditBadge extends StatelessWidget {
  const _SmallAuditBadge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.primaryContainer,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Text(
      label,
      style: Theme.of(
        context,
      ).textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w800),
    ),
  );
}

class _AuditLine extends StatelessWidget {
  const _AuditLine({
    required this.label,
    required this.value,
    required this.color,
  });

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(9),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      borderRadius: BorderRadius.circular(10),
    ),
    child: RichText(
      text: TextSpan(
        style: DefaultTextStyle.of(context).style,
        children: [
          TextSpan(
            text: '$label: ',
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          TextSpan(text: value),
        ],
      ),
    ),
  );
}

class _ExamReviewQuestion {
  const _ExamReviewQuestion({
    required this.position,
    required this.questionText,
    required this.options,
    required this.selectedKey,
    required this.correctKey,
    required this.explanation,
  });

  final int position;
  final String questionText;
  final Map<String, String> options;
  final String? selectedKey;
  final String? correctKey;
  final String explanation;

  bool get isCorrect =>
      selectedKey != null &&
      correctKey != null &&
      selectedKey!.toUpperCase() == correctKey!.toUpperCase();
}

List<_ExamReviewQuestion> _examReviewQuestions(
  Map<String, dynamic> row,
  String languageCode,
) {
  final questionResults = _mapList(row['question_results']);
  if (questionResults.isNotEmpty) {
    return [
      for (final entry in questionResults.indexed)
        _questionFromResult(entry.$2, entry.$1 + 1, languageCode),
    ];
  }

  final questions = _mapList(row['questions']);
  final answers = _answerMap(row['answers']);
  return [
    for (final entry in questions.indexed)
      _questionFromFinalPayload(entry.$2, entry.$1 + 1, answers, languageCode),
  ];
}

_ExamReviewQuestion _questionFromResult(
  Map<String, dynamic> data,
  int fallbackPosition,
  String languageCode,
) {
  final options = _optionsFromList(
    data['options'],
    data['options_i18n'],
    languageCode,
  );
  final selectedIndex = _int(data['selected_index'], fallback: -1);
  final correctIndex = _int(data['correct_index'], fallback: -1);
  return _ExamReviewQuestion(
    position: fallbackPosition,
    questionText: getLocalizedText(
      data['question_text_i18n'] ?? data['question_text'],
      languageCode,
      fallback: data['question_text'],
    ),
    options: options,
    selectedKey: _keyFromIndex(selectedIndex),
    correctKey: _keyFromIndex(correctIndex),
    explanation: getLocalizedText(
      data['explanation_i18n'] ?? data['explanation'],
      languageCode,
      fallback: data['explanation'],
    ),
  );
}

_ExamReviewQuestion _questionFromFinalPayload(
  Map<String, dynamic> data,
  int fallbackPosition,
  Map<String, String> answers,
  String languageCode,
) {
  final id = '${data['attempt_question_id'] ?? data['question_id'] ?? ''}';
  final questionId = '${data['question_id'] ?? ''}';
  final selected = answers[id] ?? answers[questionId];
  return _ExamReviewQuestion(
    position: _int(data['position'], fallback: fallbackPosition),
    questionText: getLocalizedText(
      data['question_i18n'] ??
          data['question'] ??
          data['question_text_i18n'] ??
          data['question_text'],
      languageCode,
      fallback: data['question'] ?? data['question_text'],
    ),
    options: _optionsFromMap(
      data['options'],
      data['options_i18n'],
      languageCode,
    ),
    selectedKey: selected,
    correctKey: _answerKey(data),
    explanation: getLocalizedText(
      data['explanation_i18n'] ?? data['explanation'],
      languageCode,
      fallback: data['explanation'],
    ),
  );
}

List<Map<String, dynamic>> _mapList(Object? raw) => raw is Iterable
    ? raw
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : const [];

Map<String, String> _answerMap(Object? raw) {
  if (raw is! Map) return const {};
  return raw.map((key, value) {
    final normalized = value is num
        ? _keyFromIndex(value.toInt()) ?? value.toString()
        : value.toString().trim().toUpperCase();
    return MapEntry(key.toString(), normalized);
  });
}

Map<String, String> _optionsFromMap(
  Object? raw,
  Object? i18n,
  String languageCode,
) {
  if (raw is List) return _optionsFromList(raw, i18n, languageCode);
  if (raw is! Map) return const {};
  final entries = raw.entries.toList()
    ..sort(
      (left, right) => left.key.toString().compareTo(right.key.toString()),
    );
  return {
    for (final entry in entries)
      entry.key.toString(): getLocalizedText(
        _optionI18nValue(i18n, entry.key.toString()) ??
            (entry.value is Map
                ? (entry.value['text_i18n'] ??
                      entry.value['option_text_i18n'] ??
                      entry.value['text'] ??
                      entry.value['option_text'] ??
                      entry.value)
                : entry.value),
        languageCode,
        fallback: entry.value is Map
            ? entry.value['text'] ?? entry.value['option_text']
            : entry.value,
      ),
  };
}

Map<String, String> _optionsFromList(
  Object? raw,
  Object? i18n,
  String languageCode,
) {
  if (raw is! Iterable) return const {};
  final values = raw.toList();
  return {
    for (var index = 0; index < values.length; index++)
      String.fromCharCode(65 + index): getLocalizedText(
        _optionI18nValue(i18n, String.fromCharCode(65 + index), index) ??
            values[index],
        languageCode,
        fallback: values[index],
      ),
  };
}

Object? _optionI18nValue(Object? i18n, String key, [int? index]) {
  if (i18n is Map) return i18n[key];
  if (i18n is List && index != null && index >= 0 && index < i18n.length) {
    return i18n[index];
  }
  return null;
}

String _answerKey(Map<String, dynamic> data) {
  final direct =
      data['answer_key'] ??
      data['correct_answer'] ??
      data['correct_option'] ??
      data['correct_option_key'];
  if (direct != null && '$direct'.trim().isNotEmpty) {
    return '$direct'.trim().toUpperCase();
  }
  final index = _int(
    data['correct_index'] ??
        data['correctOptionIndex'] ??
        data['correct_option_index'],
    fallback: -1,
  );
  return _keyFromIndex(index) ?? '';
}

String? _keyFromIndex(int index) =>
    index >= 0 && index <= 25 ? String.fromCharCode(65 + index) : null;

int _int(Object? value, {required int fallback}) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;

class AdminFeatureConfigurationScreen extends StatefulWidget {
  const AdminFeatureConfigurationScreen({super.key, required this.repository});
  final TradingRepository repository;

  @override
  State<AdminFeatureConfigurationScreen> createState() =>
      _AdminFeatureConfigurationScreenState();
}

class _AdminFeatureConfigurationScreenState
    extends State<AdminFeatureConfigurationScreen> {
  late final Future<Map<String, bool>> _future = widget.repository
      .featureConfiguration();
  Map<String, bool>? _values;
  bool _saving = false;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Feature configuration')),
    body: FutureBuilder<Map<String, bool>>(
      future: _future,
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        _values ??= Map<String, bool>.from(snapshot.data!);
        return ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 90),
          children: [
            const Text(
              'Disabled modules remain visible here but are hidden from normal user navigation.',
            ),
            const SizedBox(height: 10),
            for (final entry in _values!.entries)
              SwitchListTile.adaptive(
                title: Text(_pretty(entry.key)),
                subtitle: entry.key == 'dealership_ecommerce'
                    ? const Text(
                        'Keep disabled when Trading is the primary module unless explicitly required.',
                      )
                    : null,
                value: entry.value,
                onChanged: entry.key == 'trading'
                    ? null
                    : (value) => setState(() => _values![entry.key] = value),
              ),
          ],
        );
      },
    ),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: _saving || _values == null ? null : _save,
      icon: const Icon(Icons.save_outlined),
      label: Text(_saving ? 'Saving...' : 'Save'),
    ),
  );

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await widget.repository.adminSaveFeatureConfiguration(_values!);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Feature configuration saved.')),
        );
      }
    } catch (error) {
      if (mounted) _showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class AdminAuditLogScreen extends StatefulWidget {
  const AdminAuditLogScreen({super.key, required this.repository});
  final TradingRepository repository;

  @override
  State<AdminAuditLogScreen> createState() => _AdminAuditLogScreenState();
}

class _AdminAuditLogScreenState extends State<AdminAuditLogScreen> {
  final _search = TextEditingController();
  late Future<List<Map<String, dynamic>>> _future = _load();
  Future<List<Map<String, dynamic>>> _load() =>
      widget.repository.adminAuditLogs(search: _search.text);

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(context.l10n.text('Audit history'))),
    body: FutureBuilder<List<Map<String, dynamic>>>(
      future: _future,
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            SearchBar(
              controller: _search,
              hintText: context.l10n.text('Search action, module or actor'),
              leading: const Icon(Icons.search),
              onSubmitted: (_) {
                final nextFuture = _load();
                setState(() {
                  _future = nextFuture;
                });
              },
            ),
            const SizedBox(height: 12),
            if (snapshot.data!.isEmpty) const _EmptyState(),
            for (final row in snapshot.data!)
              Card(
                child: ListTile(
                  leading: const CircleAvatar(child: Icon(Icons.history)),
                  title: Text(
                    '${context.l10n.text(_auditActionLabel('${row['action']}'))} - ${context.l10n.text(_auditModuleLabel('${row['module']}'))}',
                  ),
                  subtitle: Text(
                    '${row['actor_name'] ?? row['actor_id']}\n${context.l10n.text(_auditTargetLabel('${row['target_type']}'))} #${row['target_id']}',
                  ),
                  isThreeLine: true,
                  trailing: firestoreDate(row['created_at']) == null
                      ? null
                      : Text(
                          formatDate(firestoreDate(row['created_at'])!),
                          style: Theme.of(context).textTheme.labelSmall,
                        ),
                ),
              ),
          ],
        );
      },
    ),
  );
}

class AdminDeleteRequestsScreen extends StatefulWidget {
  const AdminDeleteRequestsScreen({super.key, required this.repository});
  final TradingRepository repository;
  @override
  State<AdminDeleteRequestsScreen> createState() =>
      _AdminDeleteRequestsScreenState();
}

class _AdminDeleteRequestsScreenState extends State<AdminDeleteRequestsScreen> {
  late Future<List<Map<String, dynamic>>> _future = widget.repository
      .adminDeleteRequests();

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Delete requests')),
    body: FutureBuilder<List<Map<String, dynamic>>>(
      future: _future,
      builder: (context, snapshot) {
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final rows = snapshot.data!;
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (rows.isEmpty) const _EmptyState(),
            for (final row in rows)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _pretty('${row['target_type']}'),
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      Text('${row['reason']}'),
                      Text('Status: ${_pretty('${row['status']}')}'),
                      if (row['status'] == 'pending')
                        Row(
                          mainAxisAlignment: MainAxisAlignment.end,
                          children: [
                            OutlinedButton(
                              onPressed: () => _review('${row['id']}', false),
                              child: const Text('Reject'),
                            ),
                            const SizedBox(width: 8),
                            FilledButton(
                              onPressed: () => _review('${row['id']}', true),
                              child: const Text('Approve'),
                            ),
                          ],
                        ),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    ),
  );

  Future<void> _review(String id, bool approve) async {
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(approve ? 'Approve deletion?' : 'Reject deletion?'),
        content: TextField(
          controller: note,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Review note'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: const Text('Confirm'),
          ),
        ],
      ),
    );
    if (confirmed == true) {
      await widget.repository.adminReviewDeleteRequest(
        requestId: id,
        approve: approve,
        note: note.text,
      );
      if (mounted) {
        final nextFuture = widget.repository.adminDeleteRequests();
        setState(() {
          _future = nextFuture;
        });
      }
    }
    note.dispose();
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 48),
    child: Column(
      children: [
        const Icon(Icons.inbox_outlined, size: 48),
        const SizedBox(height: 10),
        Text(context.l10n.text('No records found.')),
      ],
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.error, required this.onRetry});
  final Object? error;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline, size: 48),
          const SizedBox(height: 10),
          Text(context.l10n.text('$error'), textAlign: TextAlign.center),
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: onRetry,
            child: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );
}

String _recordTitle(Map<String, dynamic> row) {
  for (final key in const ['title', 'name', 'question_text']) {
    final value = _adminLocalizedField(row, key, 'en');
    if (value.trim().isNotEmpty) return value;
  }
  return 'Untitled';
}

bool _isManualBilingualField(String collection, String field) =>
    _manualBilingualFieldsByCollection[collection]?.contains(field) == true;

String _zhKey(String key) => '${key}_zh';

String _adminLocalizedField(
  Map<String, dynamic>? row,
  String key,
  String languageCode,
) {
  if (row == null) return '';
  return getLocalizedText(
    row['${key}_i18n'] ?? row[key],
    languageCode,
    fallback: row[key],
  );
}

String _adminOptionLocalizedField(
  Map<String, dynamic>? row,
  String optionKey,
  String languageCode,
) {
  if (row == null) return '';
  final i18n = row['options_i18n'];
  if (i18n is Map && i18n[optionKey] != null) {
    return getLocalizedText(i18n[optionKey], languageCode);
  }
  final options = row['options'];
  if (options is Map && options[optionKey] != null) {
    final value = options[optionKey];
    if (value is Map) {
      return getLocalizedText(
        value['text_i18n'] ?? value['option_text_i18n'] ?? value,
        languageCode,
        fallback: value['text'] ?? value['option_text'],
      );
    }
    return getLocalizedText(value, languageCode);
  }
  return '';
}

Object _statusLabel(Map<String, dynamic> row) {
  if (row['archived'] == true || row['deleted_at'] != null) return 'archived';
  final explicit =
      row['publication_status'] ??
      row['lifecycle_status'] ??
      row['status_label'];
  if (explicit != null) return explicit;
  final status = row['status'];
  if (status is bool) return status ? 'published' : 'draft';
  if (status is num) {
    return status == 1
        ? 'active'
        : status == 12
        ? 'cancelled'
        : status == 13
        ? 'closed'
        : 'draft';
  }
  return status ?? 'draft';
}

String _pretty(String value) => value
    .replaceAll('_', ' ')
    .split(' ')
    .where((part) => part.isNotEmpty)
    .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
    .join(' ');

String _auditActionLabel(String value) => switch (value.trim().toLowerCase()) {
  'create' => 'Created',
  'update' => 'Updated',
  'delete' => 'Deleted',
  'soft_delete' => 'Archived',
  'archive' => 'Archived',
  'restore' => 'Restored',
  'request_delete' => 'Delete requested',
  'review' => 'Reviewed',
  'reply' => 'Replied',
  'close' => 'Closed',
  'reopen' => 'Reopened',
  'assign' => 'Assigned',
  'cancel' => 'Cancelled',
  _ => _pretty(value),
};

String _auditModuleLabel(String value) => switch (value.trim().toLowerCase()) {
  'trading_signals' => 'Trading Signals',
  'market_analyses' => 'Market Analysis',
  'recording_classes' || 'trading_recordings' => 'Recording classes',
  'knowledge_centres' => 'Knowledge Centre',
  'trading_resources' => 'Marketing Resources',
  'trading_blogs' => 'Trading Blog',
  'trading_news' => 'Trading News',
  'support_tickets' => 'Support tickets',
  'appointments' || 'appointment_slots' => 'Appointments',
  'users' || 'account_management' => 'Users',
  'trading_position_applications' => 'Position applications',
  'client_access' || 'trader_onboarding' => 'Client access',
  'feature_configuration' => 'Features',
  _ => _pretty(value),
};

String _auditTargetLabel(String value) => switch (value.trim().toLowerCase()) {
  'trading_signal' => 'Trading Signal',
  'market_analysis' => 'Market Analysis',
  'trading_recording' => 'Recording class',
  'knowledge_centre' => 'Knowledge Centre',
  'trading_resource' => 'Marketing Resource',
  'trading_blog' => 'Trading Blog',
  'trading_news' => 'Trading News',
  'support_ticket' => 'Support ticket',
  'appointment' || 'appointment_slot' => 'Appointment',
  'user' => 'User',
  'trading_position_application' => 'Position application',
  _ => _pretty(value),
};

String _slug(String value) => value
    .trim()
    .toLowerCase()
    .replaceAll(RegExp(r'[^a-z0-9]+'), '-')
    .replaceAll(RegExp(r'^-+|-+$'), '');
bool _looksLikeUrl(String value) =>
    value.startsWith('https://') || value.startsWith('http://');
Future<void> _openUrl(String value) async {
  final uri = Uri.tryParse(value);
  if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
}

double _number(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

String _journalUser(Map<String, dynamic> row) {
  final user = row['user'];
  if (user is! Map) return '${row['user_id'] ?? 'Unknown trader'}';
  return '${user['name'] ?? user['email'] ?? user['id'] ?? 'Unknown trader'}';
}

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(
    context,
  ).showSnackBar(SnackBar(content: Text(context.l10n.text('$error'))));
}
