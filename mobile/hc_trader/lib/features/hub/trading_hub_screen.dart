import 'dart:math' as math;

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/access_policy.dart';
import '../../core/security/app_role_permissions.dart';
import '../../core/security/publishing_authorization.dart';
import '../../models/app_user.dart';
import '../../models/market_analysis_item.dart';
import '../../models/market_analyst_models.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/trader_account_profile.dart';
import '../../models/trading_blog_post.dart';
import '../../models/trading_exam_models.dart';
import '../../models/trading_journal_entry.dart';
import '../../models/trading_position_application_mobile.dart';
import '../../models/trading_recording_class.dart';
import '../../models/trading_resource_item.dart';
import '../../models/trading_signal_item.dart';
import '../../repositories/auth_repository.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../shared/empty_state.dart';
import '../../shared/formatters.dart';
import '../../services/daily_quiz_upload_service.dart';
import 'knowledge_centre_library.dart';
import 'market_insight_screen.dart';
import 'publisher_sheets.dart';
import 'trade_call_detail_screen.dart';
import 'trade_call_statistics_screen.dart';
import '../account/account_screen.dart';
import '../access/access_status_screen.dart';
import '../examination/daily_exam_result_screen.dart';
import '../examination/coursework_screen.dart';

class TradingHubScreen extends StatelessWidget {
  const TradingHubScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.authRepository,
    this.publicOnly = false,
  });

  final AppUser user;
  final TradingRepository repository;
  final AuthRepository authRepository;
  final bool publicOnly;

  @override
  Widget build(BuildContext context) {
    final blogOnly =
        publicOnly ||
        ClientAccessPolicy.isRestricted(user, AppFeatureArea.tradingHubPrivate);
    if (blogOnly) {
      return DefaultTabController(
        length: 1,
        child: Column(
          children: [
            TabBar(
              tabs: [
                Tab(
                  icon: const Icon(Icons.article_outlined),
                  text: context.l10n.text('Blog'),
                ),
              ],
            ),
            Expanded(
              child: _BlogsTab(user: user, repository: repository),
            ),
          ],
        ),
      );
    }

    return DefaultTabController(
      length: 9,
      child: Column(
        children: [
          TabBar(
            isScrollable: true,
            tabs: [
              Tab(
                icon: const Icon(Icons.analytics_outlined),
                text: context.l10n.text('Analysis'),
              ),
              const Tab(icon: Icon(Icons.auto_graph), text: 'AI Insight'),
              Tab(
                icon: const Icon(Icons.school_outlined),
                text: context.l10n.text('Knowledge'),
              ),
              Tab(
                icon: const Icon(Icons.video_library_outlined),
                text: context.l10n.text('Recordings'),
              ),
              Tab(
                icon: const Icon(Icons.article_outlined),
                text: context.l10n.text('Blog'),
              ),
              Tab(
                icon: const Icon(Icons.online_prediction),
                text: context.l10n.text('Signals'),
              ),
              Tab(
                icon: const Icon(Icons.quiz_outlined),
                text: context.l10n.text('Coursework'),
              ),
              Tab(
                icon: const Icon(Icons.verified_outlined),
                text: context.l10n.text('Verification'),
              ),
              Tab(
                icon: const Icon(Icons.video_library_outlined),
                text: context.l10n.text('Resources'),
              ),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: [
                _MarketTab(user: user, repository: repository),
                MarketInsightScreen(repository: repository),
                KnowledgeCentreLibrary(repository: repository),
                _ClassesTab(
                  repository: repository,
                  authRepository: authRepository,
                ),
                _BlogsTab(user: user, repository: repository),
                _SignalsTab(user: user, repository: repository),
                CourseworkScreen(user: user, tradingRepository: repository),
                _PositionsTab(
                  user: user,
                  repository: repository,
                  authRepository: authRepository,
                ),
                _ResourcesTab(
                  repository: repository,
                  authRepository: authRepository,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PublisherCard extends StatelessWidget {
  const _PublisherCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.actionLabel,
    required this.onPressed,
  });

  final IconData icon;
  final String title;
  final String description;
  final String actionLabel;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      color: colors.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            CircleAvatar(
              backgroundColor: colors.primary,
              foregroundColor: colors.onPrimary,
              child: Icon(icon),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(description),
                ],
              ),
            ),
            const SizedBox(width: 10),
            FilledButton(onPressed: onPressed, child: Text(actionLabel)),
          ],
        ),
      ),
    );
  }
}

class _SignalsTab extends StatelessWidget {
  const _SignalsTab({required this.user, required this.repository});

  final AppUser user;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<TradingSignalItem>>(
      stream: repository.tradingSignalsStream(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ErrorState(
            error: snapshot.error,
            onRetry: repository.refreshTradingSignals,
          );
        }

        final signals = snapshot.data ?? const <TradingSignalItem>[];
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }

        final active = signals.where((signal) => !signal.isClosed).length;
        return RefreshIndicator(
          onRefresh: repository.refreshTradingSignals,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
                sliver: SliverToBoxAdapter(
                  child: Column(
                    children: [
                      _SignalCommandHeader(
                        active: active,
                        completed: signals.length - active,
                        onStatistics:
                            user.effectiveRoleIds.any(
                              const {
                                RolePermissionMatrix.superAdmin,
                                RolePermissionMatrix.subAdmin,
                                RolePermissionMatrix.founder,
                                RolePermissionMatrix.signalProvider,
                                RolePermissionMatrix.seniorSignalProvider,
                                RolePermissionMatrix.signalProviderManagement,
                              }.contains,
                            )
                            ? () => Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => TradeCallStatisticsScreen(
                                    user: user,
                                    repository: repository,
                                  ),
                                ),
                              )
                            : null,
                      ),
                      if (PublishingAuthorization(
                        user,
                      ).canPublishTradingSignal) ...[
                        const SizedBox(height: 12),
                        _PublisherCard(
                          icon: Icons.add_alert_outlined,
                          title: context.l10n.text('{role} workspace', {
                            'role': context.l10n.roleName(user.roleId),
                          }),
                          description: context.l10n.text(
                            'Publish a risk-labelled signal to the Trading Hub and notify subscribed traders.',
                          ),
                          actionLabel: context.l10n.text('New signal'),
                          onPressed: () => showSignalPublisher(
                            context,
                            user: user,
                            repository: repository,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              if (signals.isEmpty)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: EmptyState(
                    icon: Icons.online_prediction,
                    title: context.l10n.text('No trading signals yet'),
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                  sliver: SliverGrid(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) => _SignalCard(
                        signal: signals[index],
                        repository: repository,
                      ),
                      childCount: signals.length,
                    ),
                    gridDelegate: SliverGridDelegateWithMaxCrossAxisExtent(
                      maxCrossAxisExtent: 430,
                      mainAxisExtent:
                          MediaQuery.textScalerOf(context).scale(1) > 1.2
                          ? 224
                          : 184,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _SignalCommandHeader extends StatelessWidget {
  const _SignalCommandHeader({
    required this.active,
    required this.completed,
    this.onStatistics,
  });

  final int active;
  final int completed;
  final VoidCallback? onStatistics;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [colors.primaryContainer, colors.tertiaryContainer],
        ),
        borderRadius: BorderRadius.circular(22),
      ),
      child: Wrap(
        spacing: 12,
        runSpacing: 12,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Icon(Icons.radar, size: 38, color: colors.onPrimaryContainer),
          const SizedBox(width: 13),
          SizedBox(
            width: 540,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text('Trading command centre'),
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: colors.onPrimaryContainer,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  context.l10n.text('{active} active · {completed} completed', {
                    'active': active,
                    'completed': completed,
                  }),
                  style: TextStyle(
                    color: colors.onPrimaryContainer.withValues(alpha: .8),
                  ),
                ),
              ],
            ),
          ),
          if (onStatistics != null)
            FilledButton.tonalIcon(
              onPressed: onStatistics,
              icon: const Icon(Icons.query_stats_outlined),
              label: Text(context.l10n.text('View statistics')),
            ),
        ],
      ),
    );
  }
}

class _SignalCard extends StatelessWidget {
  const _SignalCard({required this.signal, required this.repository});

  final TradingSignalItem signal;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    final closed = signal.isClosed;
    final tone = closed ? const Color(0xFF6B7280) : const Color(0xFF17855B);
    final languageCode = context.l10n.locale.languageCode;
    final title = signal.localizedTitle(languageCode).isEmpty
        ? signal.tradingPair
        : signal.localizedTitle(languageCode);

    return Card(
      elevation: 0,
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: () => _showSignal(context),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  _StatusPill(
                    label: context.l10n.text(signal.progressLabel),
                    color: tone,
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                '${signal.tradingPair}  |  ${context.l10n.text(signal.localizedImmediateAction(languageCode))}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _MiniFigure(
                      label: context.l10n.text('Entry'),
                      value: signal.entryPrice,
                    ),
                  ),
                  Expanded(
                    child: _MiniFigure(
                      label: context.l10n.text('SL'),
                      value: signal.stopLoss,
                    ),
                  ),
                  Expanded(
                    child: _MiniFigure(
                      label: context.l10n.text('TPs'),
                      value: signal.targets.length.toString(),
                      alignEnd: true,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showSignal(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) =>
            TradeCallDetailScreen(signal: signal, repository: repository),
      ),
    );
  }
}

class _MarketTab extends StatefulWidget {
  const _MarketTab({required this.user, required this.repository});

  final AppUser user;
  final TradingRepository repository;

  @override
  State<_MarketTab> createState() => _MarketTabState();
}

class _MarketTabState extends State<_MarketTab> {
  final _searchController = TextEditingController();
  var _marketFilter = 'all';
  var _structureFilter = 'all';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<MarketAnalysisItem>>(
      stream: widget.repository.marketAnalysesStream(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error);
        }

        final analyses = snapshot.data ?? const <MarketAnalysisItem>[];
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }

        if (analyses.isEmpty) {
          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (PublishingAuthorization(
                widget.user,
              ).canPublishMarketAnalysis) ...[
                _PublisherCard(
                  icon: Icons.add_chart_outlined,
                  title: '${widget.user.roleName} workspace',
                  description: context.l10n.text(
                    'Publish a structured market outlook and notify subscribed traders.',
                  ),
                  actionLabel: context.l10n.text('New analysis'),
                  onPressed: () => showMarketAnalysisPublisher(
                    context,
                    user: widget.user,
                    repository: widget.repository,
                  ),
                ),
                const SizedBox(height: 14),
              ],
              EmptyState(
                icon: Icons.analytics_outlined,
                title: context.l10n.text('No market analysis yet'),
              ),
            ],
          );
        }

        final filtered = _filteredAnalyses(analyses);
        final markets =
            analyses
                .map((analysis) => analysis.market.trim())
                .where((market) => market.isNotEmpty)
                .toSet()
                .toList()
              ..sort();
        final weeklyCount = analyses
            .where(
              (analysis) =>
                  analysis.analysisDate != null &&
                  analysis.analysisDate!.isAfter(
                    DateTime.now().subtract(const Duration(days: 7)),
                  ),
            )
            .length;
        final latest = filtered.isEmpty ? null : filtered.first;
        final rest = filtered
            .where((analysis) => analysis.id != latest?.id)
            .toList();

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (PublishingAuthorization(
              widget.user,
            ).canPublishMarketAnalysis) ...[
              _PublisherCard(
                icon: Icons.add_chart_outlined,
                title: '${widget.user.roleName} workspace',
                description: context.l10n.text(
                  'Publish a structured market outlook and notify subscribed traders.',
                ),
                actionLabel: context.l10n.text('New analysis'),
                onPressed: () => showMarketAnalysisPublisher(
                  context,
                  user: widget.user,
                  repository: widget.repository,
                ),
              ),
              const SizedBox(height: 14),
            ],
            _HubHeaderPanel(
              eyebrow: context.l10n.text('Market Analyst'),
              title: context.l10n.text('Professional Market Outlooks'),
              description: context.l10n.text(
                'Review analyst bias, key zones, trading plans, and current market structure before execution.',
              ),
              stats: [
                _HeaderStat(
                  label: context.l10n.text('Total Outlooks'),
                  value: '${analyses.length}',
                ),
                _HeaderStat(
                  label: context.l10n.text('Last 7 Days'),
                  value: '$weeklyCount',
                ),
                _HeaderStat(
                  label: context.l10n.text('Markets'),
                  value: '${markets.length}',
                ),
              ],
            ),
            const SizedBox(height: 14),
            _MarketFilterPanel(
              searchController: _searchController,
              markets: markets,
              marketFilter: _marketFilter,
              structureFilter: _structureFilter,
              onSearchChanged: (_) => setState(() {}),
              onMarketChanged: (value) =>
                  setState(() => _marketFilter = value ?? 'all'),
              onStructureChanged: (value) =>
                  setState(() => _structureFilter = value ?? 'all'),
              onClear: () {
                _searchController.clear();
                setState(() {
                  _marketFilter = 'all';
                  _structureFilter = 'all';
                });
              },
            ),
            const SizedBox(height: 14),
            if (filtered.isEmpty)
              EmptyState(
                icon: Icons.analytics_outlined,
                title: context.l10n.text('No outlooks match your filters'),
              )
            else ...[
              _FeaturedMarketOutlook(
                analysis: latest!,
                onTap: () => _openMarketAnalysis(latest, filtered),
              ),
              const SizedBox(height: 14),
              if (rest.isNotEmpty)
                _ResponsiveCardGrid(
                  children: [
                    for (final analysis in rest)
                      _MarketAnalysisCard(
                        analysis: analysis,
                        onTap: () => _openMarketAnalysis(analysis, filtered),
                      ),
                  ],
                ),
            ],
          ],
        );
      },
    );
  }

  List<MarketAnalysisItem> _filteredAnalyses(
    List<MarketAnalysisItem> analyses,
  ) {
    final search = _searchController.text.trim().toLowerCase();
    final languageCode = context.l10n.locale.languageCode;
    return analyses.where((analysis) {
      final matchesSearch =
          search.isEmpty ||
          [
            analysis.localizedTitle(languageCode),
            analysis.market,
            analysis.localizedMarketOverview(languageCode),
            analysis.localizedTrendStructure(languageCode),
            analysis.localizedKeyZones(languageCode),
            analysis.localizedAnalystView(languageCode),
            analysis.localizedStrategy(languageCode),
          ].any((value) => value.toLowerCase().contains(search));
      final matchesMarket =
          _marketFilter == 'all' || analysis.market == _marketFilter;
      final matchesStructure =
          _structureFilter == 'all' ||
          _marketTrendLabel(analysis).toLowerCase() ==
              _structureFilter.toLowerCase();

      return matchesSearch && matchesMarket && matchesStructure;
    }).toList();
  }

  void _openMarketAnalysis(
    MarketAnalysisItem analysis,
    List<MarketAnalysisItem> relatedPool,
  ) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _MarketAnalysisDetailScreen(
          analysis: analysis,
          user: widget.user,
          repository: widget.repository,
          relatedAnalyses: relatedPool
              .where((item) => item.id != analysis.id)
              .toList(),
        ),
      ),
    );
  }
}

class _BlogsTab extends StatefulWidget {
  const _BlogsTab({required this.user, required this.repository});

  final AppUser user;
  final TradingRepository repository;

  @override
  State<_BlogsTab> createState() => _BlogsTabState();
}

class _BlogsTabState extends State<_BlogsTab> {
  final _searchController = TextEditingController();
  var _categoryFilter = 'all';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<TradingBlogPost>>(
      stream: widget.repository.tradingBlogsStream(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ErrorState(
            error: 'Unable to load trading blogs. Please try again.',
            onRetry: widget.repository.refreshTradingBlogs,
          );
        }

        final blogs = snapshot.data ?? const <TradingBlogPost>[];
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }

        final syncError = widget.repository.tradingBlogSyncErrorMessage;
        final usingCachedFallback =
            widget.repository.tradingBlogsUsingCachedFallback;
        if (blogs.isEmpty) {
          return RefreshIndicator(
            onRefresh: widget.repository.refreshTradingBlogs,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              children: [
                if (syncError != null)
                  SizedBox(
                    height: 280,
                    child: _ErrorState(
                      error: syncError,
                      onRetry: widget.repository.refreshTradingBlogs,
                    ),
                  )
                else ...[
                  if (widget.user.canPublishTradingBlog) ...[
                    _PublisherCard(
                      icon: Icons.post_add_outlined,
                      title: '${widget.user.roleName} workspace',
                      description: context.l10n.text(
                        'Create and publish trading blog posts for the Trading Hub.',
                      ),
                      actionLabel: context.l10n.text('New blog post'),
                      onPressed: () => showTradingBlogPublisher(
                        context,
                        user: widget.user,
                        repository: widget.repository,
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],
                  EmptyState(
                    icon: Icons.article_outlined,
                    title: context.l10n.text(
                      'No trading blog posts are available yet.',
                    ),
                  ),
                ],
              ],
            ),
          );
        }

        final filtered = _filteredBlogs(blogs);
        final apiCategoryLabels = widget.repository.tradingBlogCategoryLabels;
        final categoryCounts = widget.repository.tradingBlogCategoryCounts;
        final categories = apiCategoryLabels.isNotEmpty
            ? apiCategoryLabels.keys.toList(growable: false)
            : (blogs
                  .map((blog) => blog.category)
                  .where((category) => category.isNotEmpty)
                  .toSet()
                  .toList()
                ..sort());
        final featured = filtered.isEmpty
            ? null
            : filtered.firstWhere(
                (blog) => blog.isFeatured,
                orElse: () => filtered.first,
              );
        final rest = filtered.where((blog) => blog.id != featured?.id).toList();

        return RefreshIndicator(
          onRefresh: widget.repository.refreshTradingBlogs,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              if (usingCachedFallback || syncError != null) ...[
                _BlogSyncBanner(
                  hasRefreshError: syncError != null,
                  cacheExpired: widget.repository.tradingBlogCacheExpired,
                  onRetry: widget.repository.refreshTradingBlogs,
                ),
                const SizedBox(height: 14),
              ],
              if (widget.user.canPublishTradingBlog) ...[
                _PublisherCard(
                  icon: Icons.post_add_outlined,
                  title: '${widget.user.roleName} workspace',
                  description: context.l10n.text(
                    'Create and publish trading blog posts for the Trading Hub.',
                  ),
                  actionLabel: context.l10n.text('New blog post'),
                  onPressed: () => showTradingBlogPublisher(
                    context,
                    user: widget.user,
                    repository: widget.repository,
                  ),
                ),
                const SizedBox(height: 14),
              ],
              _HubHeaderPanel(
                eyebrow: context.l10n.text('Trading Blog'),
                title: context.l10n.text('Trading Blog'),
                description: context.l10n.text(
                  'Trading sharing, knowledge sharing, psychology, prop firm preparation, and funded trader lessons.',
                ),
              ),
              const SizedBox(height: 14),
              _BlogFilterPanel(
                searchController: _searchController,
                categories: categories,
                activeCategory: _categoryFilter,
                categoryCounts: categoryCounts,
                onSearchChanged: (_) => setState(() {}),
                onCategoryChanged: (category) =>
                    setState(() => _categoryFilter = category),
                labelForCategory: (category) => context.l10n.text(
                  apiCategoryLabels[category] ??
                      _categoryLabel(category, blogs),
                ),
                onClearSearch: () {
                  _searchController.clear();
                  setState(() {});
                },
              ),
              const SizedBox(height: 14),
              if (filtered.isEmpty)
                const EmptyState(
                  icon: Icons.article_outlined,
                  title: 'No blog posts match your filters',
                )
              else ...[
                _FeaturedBlogPost(
                  blog: featured!,
                  onTap: () => _openBlog(featured, filtered),
                ),
                const SizedBox(height: 14),
                if (rest.isNotEmpty)
                  _ResponsiveCardGrid(
                    children: [
                      for (final blog in rest)
                        _TradingBlogCard(
                          blog: blog,
                          onTap: () => _openBlog(blog, filtered),
                        ),
                    ],
                  ),
              ],
            ],
          ),
        );
      },
    );
  }

  List<TradingBlogPost> _filteredBlogs(List<TradingBlogPost> blogs) {
    final search = _searchController.text.trim().toLowerCase();
    return blogs.where((blog) {
      final matchesCategory =
          _categoryFilter == 'all' || blog.category == _categoryFilter;
      final matchesSearch =
          search.isEmpty ||
          [
            blog.title,
            blog.categoryLabel,
            blog.excerpt,
            blog.content,
            ...blog.tags,
          ].any((value) => value.toLowerCase().contains(search));

      return matchesCategory && matchesSearch;
    }).toList();
  }

  String _categoryLabel(String category, List<TradingBlogPost> blogs) {
    for (final blog in blogs) {
      if (blog.category == category) {
        return blog.categoryLabel;
      }
    }

    return category
        .split('_')
        .where((part) => part.isNotEmpty)
        .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
        .join(' ');
  }

  void _openBlog(TradingBlogPost blog, List<TradingBlogPost> relatedPool) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _TradingBlogDetailScreen(
          blog: blog,
          repository: widget.repository,
          relatedBlogs: relatedPool
              .where((item) => item.id != blog.id)
              .take(3)
              .toList(),
        ),
      ),
    );
  }
}

class _HeaderStat {
  const _HeaderStat({required this.label, required this.value});

  final String label;
  final String value;
}

class _HubHeaderPanel extends StatelessWidget {
  const _HubHeaderPanel({
    required this.eyebrow,
    required this.title,
    required this.description,
    this.stats = const [],
  });

  final String eyebrow;
  final String title;
  final String description;
  final List<_HeaderStat> stats;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final wide = constraints.maxWidth >= 760 && stats.isNotEmpty;
            final copy = Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  eyebrow.toUpperCase(),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Theme.of(context).colorScheme.primary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  title,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 5),
                Text(
                  description,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            );

            if (stats.isEmpty) {
              return copy;
            }

            final statStrip = Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                for (final stat in stats)
                  _HeaderStatTile(label: stat.label, value: stat.value),
              ],
            );

            if (wide) {
              return Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  Expanded(child: copy),
                  const SizedBox(width: 18),
                  ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 420),
                    child: statStrip,
                  ),
                ],
              );
            }

            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [copy, const SizedBox(height: 14), statStrip],
            );
          },
        ),
      ),
    );
  }
}

class _HeaderStatTile extends StatelessWidget {
  const _HeaderStatTile({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 126,
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border.all(color: Theme.of(context).dividerColor),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label.toUpperCase(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                value,
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MarketFilterPanel extends StatelessWidget {
  const _MarketFilterPanel({
    required this.searchController,
    required this.markets,
    required this.marketFilter,
    required this.structureFilter,
    required this.onSearchChanged,
    required this.onMarketChanged,
    required this.onStructureChanged,
    required this.onClear,
  });

  final TextEditingController searchController;
  final List<String> markets;
  final String marketFilter;
  final String structureFilter;
  final ValueChanged<String> onSearchChanged;
  final ValueChanged<String?> onMarketChanged;
  final ValueChanged<String?> onStructureChanged;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxWidth < 720;
            final fieldWidth = compact
                ? constraints.maxWidth
                : (constraints.maxWidth - 34) / 3;

            return Wrap(
              spacing: 10,
              runSpacing: 10,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                SizedBox(
                  width: fieldWidth,
                  child: TextField(
                    controller: searchController,
                    onChanged: onSearchChanged,
                    decoration: const InputDecoration(
                      prefixIcon: Icon(Icons.search),
                      labelText: 'Search outlooks',
                    ),
                  ),
                ),
                SizedBox(
                  width: fieldWidth,
                  child: DropdownButtonFormField<String>(
                    initialValue: marketFilter,
                    decoration: InputDecoration(
                      prefixIcon: const Icon(Icons.show_chart),
                      labelText: context.l10n.text('Market'),
                    ),
                    items: [
                      DropdownMenuItem(
                        value: 'all',
                        child: Text(context.l10n.text('All markets')),
                      ),
                      for (final market in markets)
                        DropdownMenuItem(value: market, child: Text(market)),
                    ],
                    onChanged: onMarketChanged,
                  ),
                ),
                SizedBox(
                  width: fieldWidth,
                  child: DropdownButtonFormField<String>(
                    initialValue: structureFilter,
                    decoration: InputDecoration(
                      prefixIcon: const Icon(Icons.stacked_line_chart),
                      labelText: context.l10n.text('Structure'),
                    ),
                    items: [
                      DropdownMenuItem(
                        value: 'all',
                        child: Text(context.l10n.text('All')),
                      ),
                      DropdownMenuItem(
                        value: 'Uptrend',
                        child: Text(context.l10n.text('Uptrend')),
                      ),
                      DropdownMenuItem(
                        value: 'Downtrend',
                        child: Text(context.l10n.text('Downtrend')),
                      ),
                      DropdownMenuItem(
                        value: 'Ranging',
                        child: Text(context.l10n.text('Ranging')),
                      ),
                    ],
                    onChanged: onStructureChanged,
                  ),
                ),
                Tooltip(
                  message: context.l10n.text('Clear filters'),
                  child: IconButton.filledTonal(
                    onPressed: onClear,
                    icon: const Icon(Icons.filter_alt_off),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _FeaturedMarketOutlook extends StatelessWidget {
  const _FeaturedMarketOutlook({required this.analysis, required this.onTap});

  final MarketAnalysisItem analysis;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final wide = constraints.maxWidth >= 760;
          final media = _RemoteImagePanel(
            url: analysis.outlookImage,
            icon: Icons.analytics_outlined,
            label: analysis.market,
            height: wide ? 280 : 190,
          );
          final body = Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  context.l10n.text('Latest Outlook'),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Theme.of(context).colorScheme.primary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  analysis.localizedTitle(languageCode),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 10),
                _OutlookMetaWrap(analysis: analysis),
                const SizedBox(height: 10),
                Text(
                  _previewText(analysis.localizedMarketOverview(languageCode)),
                  maxLines: wide ? 4 : 3,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 16),
                Align(
                  alignment: Alignment.centerLeft,
                  child: FilledButton.icon(
                    onPressed: onTap,
                    icon: const Icon(Icons.visibility_outlined),
                    label: Text(context.l10n.text('Read Outlook')),
                  ),
                ),
              ],
            ),
          );

          if (wide) {
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(width: constraints.maxWidth * .38, child: media),
                Expanded(child: body),
              ],
            );
          }

          return Column(children: [media, body]);
        },
      ),
    );
  }
}

class _MarketAnalysisCard extends StatelessWidget {
  const _MarketAnalysisCard({required this.analysis, required this.onTap});

  final MarketAnalysisItem analysis;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _RemoteImagePanel(
              url: analysis.outlookImage,
              icon: Icons.analytics_outlined,
              label: analysis.market,
              height: 135,
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _OutlookMetaWrap(analysis: analysis, compact: true),
                    const SizedBox(height: 8),
                    Text(
                      analysis.localizedTitle(languageCode),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Expanded(
                      child: Text(
                        _previewText(
                          analysis.localizedMarketOverview(languageCode),
                        ),
                        maxLines: 4,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton.icon(
                        onPressed: onTap,
                        icon: const Icon(Icons.arrow_forward),
                        label: Text(context.l10n.text('View Analysis')),
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
}

class _OutlookMetaWrap extends StatelessWidget {
  const _OutlookMetaWrap({required this.analysis, this.compact = false});

  final MarketAnalysisItem analysis;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        _InfoChip(label: analysis.market),
        _InfoChip(label: _marketTrendLabel(analysis)),
        if (analysis.analysisDate != null && !compact)
          _InfoChip(label: formatDate(analysis.analysisDate!)),
      ],
    );
  }
}

class _BlogSyncBanner extends StatelessWidget {
  const _BlogSyncBanner({
    required this.hasRefreshError,
    required this.cacheExpired,
    required this.onRetry,
  });

  final bool hasRefreshError;
  final bool cacheExpired;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final message = hasRefreshError
        ? cacheExpired
              ? 'Unable to refresh trading blogs. Showing saved posts that may be out of date.'
              : 'Unable to refresh trading blogs. Showing recently saved posts.'
        : 'Showing saved posts while checking for updates.';
    return Card(
      elevation: 0,
      color: colorScheme.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 10, 8, 10),
        child: Row(
          children: [
            Icon(
              Icons.cloud_off_outlined,
              color: colorScheme.onSecondaryContainer,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                context.l10n.text(message),
                style: TextStyle(color: colorScheme.onSecondaryContainer),
              ),
            ),
            TextButton(
              onPressed: () => onRetry(),
              child: Text(context.l10n.text('Retry')),
            ),
          ],
        ),
      ),
    );
  }
}

class _BlogFilterPanel extends StatelessWidget {
  const _BlogFilterPanel({
    required this.searchController,
    required this.categories,
    required this.activeCategory,
    required this.categoryCounts,
    required this.onSearchChanged,
    required this.onCategoryChanged,
    required this.labelForCategory,
    required this.onClearSearch,
  });

  final TextEditingController searchController;
  final List<String> categories;
  final String activeCategory;
  final Map<String, int> categoryCounts;
  final ValueChanged<String> onSearchChanged;
  final ValueChanged<String> onCategoryChanged;
  final String Function(String category) labelForCategory;
  final VoidCallback onClearSearch;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      selected: activeCategory == 'all',
                      label: Text(context.l10n.text('All')),
                      onSelected: (_) => onCategoryChanged('all'),
                    ),
                  ),
                  for (final category in categories)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        selected: activeCategory == category,
                        label: Text(
                          '${labelForCategory(category)} (${categoryCounts[category] ?? 0})',
                        ),
                        onSelected: (_) => onCategoryChanged(category),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: searchController,
              onChanged: onSearchChanged,
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search),
                labelText: context.l10n.text('Search trading posts'),
                suffixIcon: searchController.text.trim().isEmpty
                    ? null
                    : IconButton(
                        tooltip: context.l10n.text('Clear search'),
                        onPressed: onClearSearch,
                        icon: const Icon(Icons.close),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FeaturedBlogPost extends StatelessWidget {
  const _FeaturedBlogPost({required this.blog, required this.onTap});

  final TradingBlogPost blog;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: LayoutBuilder(
        builder: (context, constraints) {
          final wide = constraints.maxWidth >= 760;
          final media = _RemoteImagePanel(
            url: blog.coverImage,
            icon: Icons.article_outlined,
            label: context.l10n.text(blog.categoryLabel),
            height: wide ? 270 : 190,
          );
          final body = Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  context.l10n.text(
                    blog.isFeatured ? 'Featured Post' : 'Latest Post',
                  ),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Theme.of(context).colorScheme.primary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  blog.title,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 10),
                _BlogMetaWrap(blog: blog),
                const SizedBox(height: 10),
                Text(
                  _previewText(
                    blog.excerpt.isEmpty ? blog.content : blog.excerpt,
                  ),
                  maxLines: wide ? 4 : 3,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 16),
                Align(
                  alignment: Alignment.centerLeft,
                  child: FilledButton.icon(
                    onPressed: onTap,
                    icon: const Icon(Icons.menu_book_outlined),
                    label: Text(context.l10n.text('Read Featured Post')),
                  ),
                ),
              ],
            ),
          );

          if (wide) {
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(width: constraints.maxWidth * .42, child: media),
                Expanded(child: body),
              ],
            );
          }

          return Column(children: [media, body]);
        },
      ),
    );
  }
}

class _TradingBlogCard extends StatelessWidget {
  const _TradingBlogCard({required this.blog, required this.onTap});

  final TradingBlogPost blog;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _RemoteImagePanel(
              url: blog.coverImage,
              icon: Icons.article_outlined,
              label: context.l10n.text(blog.categoryLabel),
              height: 135,
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _BlogMetaWrap(blog: blog, compact: true),
                    const SizedBox(height: 8),
                    Text(
                      blog.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Expanded(
                      child: Text(
                        _previewText(
                          blog.excerpt.isEmpty ? blog.content : blog.excerpt,
                        ),
                        maxLines: 4,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton.icon(
                        onPressed: onTap,
                        icon: const Icon(Icons.arrow_forward),
                        label: Text(context.l10n.text('Read Post')),
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
}

class _BlogMetaWrap extends StatelessWidget {
  const _BlogMetaWrap({required this.blog, this.compact = false});

  final TradingBlogPost blog;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        _InfoChip(label: context.l10n.text(blog.categoryLabel)),
        _InfoChip(label: '${blog.readingMinutes} min read'),
        if (!compact && blog.publishedAt != null)
          _InfoChip(label: formatDate(blog.publishedAt!)),
        if (!compact) _InfoChip(label: '${blog.views} views'),
      ],
    );
  }
}

class _MarketAnalysisDetailScreen extends StatefulWidget {
  const _MarketAnalysisDetailScreen({
    required this.analysis,
    required this.relatedAnalyses,
    required this.user,
    required this.repository,
  });

  final MarketAnalysisItem analysis;
  final List<MarketAnalysisItem> relatedAnalyses;
  final AppUser user;
  final TradingRepository repository;

  @override
  State<_MarketAnalysisDetailScreen> createState() =>
      _MarketAnalysisDetailScreenState();
}

class _MarketAnalysisDetailScreenState
    extends State<_MarketAnalysisDetailScreen> {
  late Future<MarketAnalystProfile> _profile;
  var _section = 0;
  var _followSaving = false;

  MarketAnalysisItem get analysis => widget.analysis;

  @override
  void initState() {
    super.initState();
    _profile = widget.repository.marketAnalystProfile(
      analystId: analysis.createdBy,
      fallbackName: analysis.authorName,
    );
  }

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    final analystItems = _analystItems();
    final performance = MarketAnalystPerformance.fromAnalyses(analystItems);

    return Scaffold(
      appBar: AppBar(
        title: Text(context.l10n.text('Market Analyst Details')),
        actions: [
          if (PublishingAuthorization(widget.user).canPublishMarketAnalysis)
            IconButton(
              tooltip: context.l10n.text('New analysis'),
              onPressed: () => showMarketAnalysisPublisher(
                context,
                user: widget.user,
                repository: widget.repository,
              ),
              icon: const Icon(Icons.add_chart_outlined),
            ),
        ],
      ),
      body: FutureBuilder<MarketAnalystProfile>(
        future: _profile,
        builder: (context, snapshot) {
          final profile =
              snapshot.data ??
              MarketAnalystProfile(
                userId: analysis.createdBy,
                displayName: analysis.authorName.trim().isEmpty
                    ? context.l10n.text('Market Analyst')
                    : analysis.authorName,
              );
          return ListView(
            padding: const EdgeInsets.fromLTRB(18, 16, 18, 32),
            children: [
              _AnalystHero(
                profile: profile,
                analysis: analysis,
                following: _followingButton(profile),
              ),
              const SizedBox(height: 16),
              SegmentedButton<int>(
                showSelectedIcon: false,
                segments: [
                  ButtonSegment(
                    value: 0,
                    icon: const Icon(Icons.dashboard_outlined),
                    label: Text(context.l10n.text('Overview')),
                  ),
                  ButtonSegment(
                    value: 1,
                    icon: const Icon(Icons.query_stats_outlined),
                    label: Text(context.l10n.text('Track record')),
                  ),
                  ButtonSegment(
                    value: 2,
                    icon: const Icon(Icons.photo_library_outlined),
                    label: Text(context.l10n.text('Outlooks')),
                  ),
                ],
                selected: {_section},
                onSelectionChanged: (value) =>
                    setState(() => _section = value.first),
              ),
              const SizedBox(height: 16),
              if (_section == 0)
                _AnalystOverview(
                  profile: profile,
                  analysis: analysis,
                  languageCode: languageCode,
                  performance: performance,
                )
              else if (_section == 1)
                _AnalystTrackRecord(
                  profile: profile,
                  performance: performance,
                  analyses: analystItems,
                )
              else
                _AnalystOutlookGallery(
                  analyses: analystItems,
                  user: widget.user,
                  repository: widget.repository,
                ),
              const SizedBox(height: 16),
              Card(
                elevation: 0,
                color: Theme.of(context).colorScheme.errorContainer,
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(Icons.warning_amber_rounded),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          context.l10n.text(
                            'Market analysis is educational, not financial advice. Past evaluated outlooks do not guarantee future results. Manage risk independently.',
                          ),
                        ),
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
  }

  List<MarketAnalysisItem> _analystItems() {
    final all = [analysis, ...widget.relatedAnalyses];
    if (analysis.createdBy.isNotEmpty) {
      return all
          .where((item) => item.createdBy == analysis.createdBy)
          .toList(growable: false);
    }
    if (analysis.authorName.isNotEmpty) {
      return all
          .where((item) => item.authorName == analysis.authorName)
          .toList(growable: false);
    }
    return [analysis];
  }

  Widget? _followingButton(MarketAnalystProfile profile) {
    if (profile.userId.isEmpty || profile.userId == widget.user.uid) {
      return null;
    }
    return StreamBuilder<bool>(
      stream: widget.repository.isFollowingMarketAnalyst(
        analystId: profile.userId,
        userId: widget.user.uid,
      ),
      builder: (context, snapshot) {
        final following = snapshot.data == true;
        return FilledButton.tonalIcon(
          onPressed: _followSaving
              ? null
              : () => _toggleFollow(profile.userId, !following),
          icon: _followSaving
              ? const SizedBox.square(
                  dimension: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Icon(
                  following ? Icons.person_remove_outlined : Icons.person_add,
                ),
          label: Text(
            context.l10n.text(following ? 'Following' : 'Follow analyst'),
          ),
        );
      },
    );
  }

  Future<void> _toggleFollow(String analystId, bool follow) async {
    setState(() => _followSaving = true);
    try {
      await widget.repository.toggleMarketAnalystFollow(
        analystId: analystId,
        follow: follow,
      );
    } catch (error) {
      if (mounted) AppFeedback.error(context, error);
    } finally {
      if (mounted) setState(() => _followSaving = false);
    }
  }
}

class _AnalystHero extends StatelessWidget {
  const _AnalystHero({
    required this.profile,
    required this.analysis,
    required this.following,
  });

  final MarketAnalystProfile profile;
  final MarketAnalysisItem analysis;
  final Widget? following;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      elevation: 0,
      color: scheme.primaryContainer.withValues(alpha: .38),
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Wrap(
          spacing: 18,
          runSpacing: 14,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            CircleAvatar(
              radius: 38,
              backgroundImage: profile.avatarUrl.trim().isEmpty
                  ? null
                  : NetworkImage(profile.avatarUrl),
              child: profile.avatarUrl.trim().isEmpty
                  ? Text(
                      profile.displayName.characters.first.toUpperCase(),
                      style: Theme.of(context).textTheme.headlineSmall,
                    )
                  : null,
            ),
            SizedBox(
              width: 560,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 7,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Text(
                        profile.displayName,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      if (profile.verified)
                        Icon(Icons.verified, color: scheme.primary, size: 20),
                    ],
                  ),
                  Text(profile.roleTitle),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _InfoChip(label: analysis.market),
                      _InfoChip(label: _marketTrendLabel(analysis)),
                      if (analysis.timeframe.isNotEmpty)
                        _InfoChip(label: analysis.timeframe),
                      if (profile.followerCount > 0)
                        _InfoChip(
                          label: context.l10n.text('{count} followers', {
                            'count': profile.followerCount,
                          }),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            ?following,
          ],
        ),
      ),
    );
  }
}

class _AnalystOverview extends StatelessWidget {
  const _AnalystOverview({
    required this.profile,
    required this.analysis,
    required this.languageCode,
    required this.performance,
  });

  final MarketAnalystProfile profile;
  final MarketAnalysisItem analysis;
  final String languageCode;
  final MarketAnalystPerformance performance;

  @override
  Widget build(BuildContext context) {
    final sections = <(String, String, bool)>[
      (
        'Market Overview',
        analysis.localizedMarketOverview(languageCode),
        false,
      ),
      (
        'Trend and Structure',
        analysis.localizedTrendStructure(languageCode),
        false,
      ),
      ('Key Zones', analysis.localizedKeyZones(languageCode), false),
      ('Analyst View', analysis.localizedAnalystView(languageCode), false),
      (
        'Strategy / Recommendations',
        analysis.localizedStrategy(languageCode),
        false,
      ),
      (
        'Chart Signals Summary',
        analysis.localizedChartSignals(languageCode),
        false,
      ),
      ('Trading Plan', analysis.localizedTradingPlan(languageCode), true),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final primary = Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _LatestOutlookCard(analysis: analysis),
            const SizedBox(height: 12),
            for (final section in sections)
              if (section.$2.trim().isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _DetailSection(
                    title: context.l10n.text(section.$1),
                    body: section.$2,
                    monospace: section.$3,
                  ),
                ),
          ],
        );
        final sidebar = Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _CurrentBiasCard(analysis: analysis),
            const SizedBox(height: 12),
            _AnalystProfileCard(profile: profile),
            const SizedBox(height: 12),
            _AccuracyCard(performance: performance),
          ],
        );
        if (constraints.maxWidth < 880) {
          return Column(
            children: [sidebar, const SizedBox(height: 12), primary],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(flex: 7, child: primary),
            const SizedBox(width: 14),
            Expanded(flex: 4, child: sidebar),
          ],
        );
      },
    );
  }
}

class _LatestOutlookCard extends StatelessWidget {
  const _LatestOutlookCard({required this.analysis});
  final MarketAnalysisItem analysis;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (analysis.outlookImage.trim().isNotEmpty)
            _RemoteImagePanel(
              url: analysis.outlookImage,
              icon: Icons.candlestick_chart_outlined,
              label: analysis.market,
              height: 300,
            ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text('Latest analysis'),
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: Theme.of(context).colorScheme.primary,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  analysis.localizedTitle(languageCode),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (analysis.analysisDate != null) ...[
                  const SizedBox(height: 6),
                  Text(formatDate(analysis.analysisDate!)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CurrentBiasCard extends StatelessWidget {
  const _CurrentBiasCard({required this.analysis});
  final MarketAnalysisItem analysis;

  @override
  Widget build(BuildContext context) => Card(
    elevation: 0,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.text('Current bias'),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 10),
          Text(
            _marketTrendLabel(analysis),
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              color: Theme.of(context).colorScheme.primary,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          _DetailInfoPanel(
            rows: [
              (context.l10n.text('Market'), analysis.market),
              (context.l10n.text('Trend Strength'), analysis.trendStrength),
              (context.l10n.text('Timeframe'), analysis.timeframe),
              (context.l10n.text('Risk level'), analysis.riskLevel),
            ],
          ),
        ],
      ),
    ),
  );
}

class _AnalystProfileCard extends StatelessWidget {
  const _AnalystProfileCard({required this.profile});
  final MarketAnalystProfile profile;

  @override
  Widget build(BuildContext context) => Card(
    elevation: 0,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.text('About the analyst'),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 8),
          Text(
            profile.bio.trim().isEmpty
                ? context.l10n.text('No public biography has been added yet.')
                : profile.bio,
          ),
          if (profile.methodology.trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              context.l10n.text('Methodology'),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            Text(profile.methodology),
          ],
          if (profile.markets.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final market in profile.markets) _InfoChip(label: market),
              ],
            ),
          ],
        ],
      ),
    ),
  );
}

class _AccuracyCard extends StatelessWidget {
  const _AccuracyCard({required this.performance});
  final MarketAnalystPerformance performance;

  @override
  Widget build(BuildContext context) {
    final accuracy = performance.accuracyPercentage;
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Transparent track record'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Text(
              accuracy == null
                  ? context.l10n.text('Not enough evaluated outlooks')
                  : '${accuracy.toStringAsFixed(0)}%',
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              context.l10n
                  .text('{accurate} accurate from {evaluated} evaluated', {
                    'accurate': performance.accurate,
                    'evaluated': performance.evaluated,
                  }),
            ),
            const SizedBox(height: 6),
            Text(
              context.l10n.text(
                'Accuracy excludes open and unevaluated outlooks. Partial outcomes are not counted as accurate.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _AnalystTrackRecord extends StatelessWidget {
  const _AnalystTrackRecord({
    required this.profile,
    required this.performance,
    required this.analyses,
  });

  final MarketAnalystProfile profile;
  final MarketAnalystPerformance performance;
  final List<MarketAnalysisItem> analyses;

  @override
  Widget build(BuildContext context) {
    final ordered = [...analyses]
      ..sort(
        (left, right) => (right.analysisDate ?? DateTime(1900)).compareTo(
          left.analysisDate ?? DateTime(1900),
        ),
      );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _AccuracyCard(performance: performance),
        const SizedBox(height: 12),
        _AnalystProfileCard(profile: profile),
        const SizedBox(height: 12),
        Card(
          elevation: 0,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text('Outlook timeline'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 10),
                for (final item in ordered)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      item.isEvaluated
                          ? item.isAccurateOutcome
                                ? Icons.check_circle_outline
                                : Icons.cancel_outlined
                          : Icons.schedule_outlined,
                    ),
                    title: Text(
                      item.localizedTitle(context.l10n.locale.languageCode),
                    ),
                    subtitle: Text(
                      [
                        item.market,
                        if (item.analysisDate != null)
                          formatDate(item.analysisDate!),
                        if (item.outcomeStatus.isNotEmpty)
                          context.l10n.text(item.outcomeStatus),
                      ].join(' · '),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _AnalystOutlookGallery extends StatelessWidget {
  const _AnalystOutlookGallery({
    required this.analyses,
    required this.user,
    required this.repository,
  });

  final List<MarketAnalysisItem> analyses;
  final AppUser user;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    final images = <(MarketAnalysisItem, String)>[
      for (final analysis in analyses)
        for (final image in analysis.allChartImages) (analysis, image),
    ];
    if (images.isEmpty) {
      return EmptyState(
        icon: Icons.photo_library_outlined,
        title: context.l10n.text('No chart gallery yet'),
      );
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 1000
            ? 3
            : constraints.maxWidth >= 620
            ? 2
            : 1;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: images.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            mainAxisExtent: 300,
          ),
          itemBuilder: (context, index) {
            final entry = images[index];
            return Card(
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () => Navigator.of(context).pushReplacement(
                  MaterialPageRoute<void>(
                    builder: (_) => _MarketAnalysisDetailScreen(
                      analysis: entry.$1,
                      relatedAnalyses: analyses
                          .where((item) => item.id != entry.$1.id)
                          .toList(),
                      user: user,
                      repository: repository,
                    ),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _RemoteImagePanel(
                      url: entry.$2,
                      icon: Icons.analytics_outlined,
                      label: entry.$1.market,
                      height: 220,
                    ),
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: Text(
                        entry.$1.localizedTitle(
                          context.l10n.locale.languageCode,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _TradingBlogDetailScreen extends StatefulWidget {
  const _TradingBlogDetailScreen({
    required this.blog,
    required this.repository,
    required this.relatedBlogs,
  });

  final TradingBlogPost blog;
  final TradingRepository repository;
  final List<TradingBlogPost> relatedBlogs;

  @override
  State<_TradingBlogDetailScreen> createState() =>
      _TradingBlogDetailScreenState();
}

class _TradingBlogDetailScreenState extends State<_TradingBlogDetailScreen> {
  late TradingBlogPost blog = widget.blog;

  @override
  void initState() {
    super.initState();
    _refreshDetail();
  }

  Future<void> _refreshDetail() async {
    try {
      final refreshed = await widget.repository.loadTradingBlogDetail(blog);
      if (mounted) setState(() => blog = refreshed);
    } on Object {
      // The already-loaded list record remains readable while offline.
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Trading Blog'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (blog.coverImage.trim().isNotEmpty) ...[
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: _RemoteImagePanel(
                url: blog.coverImage,
                icon: Icons.article_outlined,
                label: context.l10n.text(blog.categoryLabel),
                height: 260,
              ),
            ),
            const SizedBox(height: 14),
          ],
          _DetailTitleBlock(
            eyebrow: context.l10n.text(blog.categoryLabel),
            title: blog.title,
            subtitle: [
              if (blog.publishedAt != null) formatDate(blog.publishedAt!),
              '${blog.readingMinutes} min read',
              '${blog.views} views',
              blog.authorLabel,
            ].join('  |  '),
          ),
          if (blog.excerpt.trim().isNotEmpty) ...[
            const SizedBox(height: 14),
            _DetailSection(
              title: context.l10n.text('Summary'),
              body: blog.excerpt,
            ),
          ],
          const SizedBox(height: 12),
          _HtmlArticleSection(
            title: context.l10n.text('Article'),
            html: blog.content,
          ),
          if (blog.tags.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [for (final tag in blog.tags) _InfoChip(label: tag)],
            ),
          ],
          if (widget.relatedBlogs.isNotEmpty) ...[
            const SizedBox(height: 14),
            _RelatedPanel(
              title: context.l10n.text('Related Posts'),
              children: [
                for (final item in widget.relatedBlogs)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.article_outlined),
                    title: Text(item.title),
                    subtitle: Text(context.l10n.text(item.categoryLabel)),
                    onTap: () => Navigator.of(context).pushReplacement(
                      MaterialPageRoute<void>(
                        builder: (_) => _TradingBlogDetailScreen(
                          blog: item,
                          repository: widget.repository,
                          relatedBlogs: widget.relatedBlogs
                              .where((related) => related.id != item.id)
                              .toList(),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _HtmlArticleSection extends StatelessWidget {
  const _HtmlArticleSection({required this.title, required this.html});

  final String title;
  final String html;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            SelectionArea(
              child: HtmlWidget(
                html,
                textStyle: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(height: 1.45),
                onTapUrl: (url) async {
                  final uri = Uri.tryParse(url);
                  return uri != null &&
                      const {'http', 'https'}.contains(uri.scheme) &&
                      await launchUrl(
                        uri,
                        mode: LaunchMode.externalApplication,
                      );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ResponsiveCardGrid extends StatelessWidget {
  const _ResponsiveCardGrid({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 620) {
          return Column(
            children: [
              for (final child in children) ...[
                SizedBox(height: 350, child: child),
                if (child != children.last) const SizedBox(height: 10),
              ],
            ],
          );
        }

        final columns = constraints.maxWidth >= 1120 ? 3 : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: children.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            mainAxisExtent: 350,
          ),
          itemBuilder: (context, index) => children[index],
        );
      },
    );
  }
}

class _RemoteImagePanel extends StatelessWidget {
  const _RemoteImagePanel({
    required this.url,
    required this.icon,
    required this.label,
    required this.height,
  });

  final String url;
  final IconData icon;
  final String label;
  final double height;

  @override
  Widget build(BuildContext context) {
    final uri = Uri.tryParse(url.trim());
    final canLoad = uri != null && uri.hasScheme;
    final placeholder = _ImagePlaceholder(
      icon: icon,
      label: label,
      height: height,
    );

    if (!canLoad) {
      return placeholder;
    }

    return SizedBox(
      height: height,
      width: double.infinity,
      child: Image.network(
        url.trim(),
        fit: BoxFit.cover,
        errorBuilder: (context, error, stackTrace) => placeholder,
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) {
            return child;
          }

          return Stack(
            fit: StackFit.expand,
            children: [
              placeholder,
              const Center(child: CircularProgressIndicator()),
            ],
          );
        },
      ),
    );
  }
}

class _ImagePlaceholder extends StatelessWidget {
  const _ImagePlaceholder({
    required this.icon,
    required this.label,
    required this.height,
  });

  final IconData icon;
  final String label;
  final double height;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      width: double.infinity,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surfaceContainerHighest,
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 36),
            const SizedBox(height: 8),
            Text(
              label.isEmpty ? 'HC Trader' : label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(
                context,
              ).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    if (label.trim().isEmpty) {
      return const SizedBox.shrink();
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).dividerColor),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: Theme.of(
          context,
        ).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _DetailTitleBlock extends StatelessWidget {
  const _DetailTitleBlock({
    required this.eyebrow,
    required this.title,
    required this.subtitle,
  });

  final String eyebrow;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              eyebrow.toUpperCase(),
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: Theme.of(context).colorScheme.primary,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            if (subtitle.trim().isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                subtitle,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _DetailInfoPanel extends StatelessWidget {
  const _DetailInfoPanel({required this.rows});

  final List<(String, String)> rows;

  @override
  Widget build(BuildContext context) {
    final visibleRows = rows
        .where((row) => row.$2.trim().isNotEmpty)
        .toList(growable: false);
    if (visibleRows.isEmpty) {
      return const SizedBox.shrink();
    }

    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final row in visibleRows)
              SizedBox(
                width: 190,
                child: _MiniFigure(label: row.$1, value: row.$2),
              ),
          ],
        ),
      ),
    );
  }
}

class _DetailSection extends StatelessWidget {
  const _DetailSection({
    required this.title,
    required this.body,
    this.monospace = false,
  });

  final String title;
  final String body;
  final bool monospace;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            if (monospace)
              DecoratedBox(
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surface,
                  border: Border.all(color: Theme.of(context).dividerColor),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: SelectableText(
                    body,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      fontFamily: 'monospace',
                      height: 1.35,
                    ),
                  ),
                ),
              )
            else
              SelectableText(
                _plainTextFromHtml(body),
                style: Theme.of(
                  context,
                ).textTheme.bodyMedium?.copyWith(height: 1.45),
              ),
          ],
        ),
      ),
    );
  }
}

class _RelatedPanel extends StatelessWidget {
  const _RelatedPanel({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            ...children,
          ],
        ),
      ),
    );
  }
}

String _marketTrendLabel(MarketAnalysisItem analysis) {
  final value = '${analysis.trendStructure} ${analysis.trendStrength}'
      .toLowerCase();

  if (value.contains('uptrend') ||
      value.contains('higher high') ||
      value.contains('bullish')) {
    return 'Uptrend';
  }

  if (value.contains('downtrend') ||
      value.contains('lower high') ||
      value.contains('bearish')) {
    return 'Downtrend';
  }

  if (value.contains('ranging') ||
      value.contains('range') ||
      value.contains('consolidat')) {
    return 'Ranging';
  }

  return 'Market Outlook';
}

String _previewText(String value) {
  final text = _plainTextFromHtml(value).trim();
  return text.isEmpty ? 'No summary provided yet.' : text;
}

String _plainTextFromHtml(String value) {
  return value
      .replaceAll(RegExp(r'<br\s*/?>', caseSensitive: false), '\n')
      .replaceAll(RegExp(r'</p>', caseSensitive: false), '\n\n')
      .replaceAll(RegExp(r'<[^>]+>'), ' ')
      .replaceAll('&nbsp;', ' ')
      .replaceAll('&amp;', '&')
      .replaceAll('&lt;', '<')
      .replaceAll('&gt;', '>')
      .replaceAll('&quot;', '"')
      .replaceAll('&#39;', "'")
      .replaceAll(RegExp(r'[ \t]+'), ' ')
      .replaceAll(RegExp(r'\n\s+'), '\n')
      .trim();
}

class _PositionsTab extends StatelessWidget {
  const _PositionsTab({
    required this.user,
    required this.repository,
    required this.authRepository,
  });

  final AppUser user;
  final TradingRepository repository;
  final AuthRepository authRepository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<Mt5Account?>(
      stream: repository.mainMt5AccountFor(user.uid),
      builder: (context, accountSnapshot) {
        if (accountSnapshot.hasError) {
          return _ErrorState(error: accountSnapshot.error);
        }
        if (accountSnapshot.connectionState == ConnectionState.waiting &&
            !accountSnapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final mainAccount = accountSnapshot.data;
        if (mainAccount == null) {
          return EmptyState(
            icon: Icons.account_balance_wallet_outlined,
            title: context.l10n.text('Select a main MT5 account first'),
            description: context.l10n.text(
              'Position Apply Track Status is calculated only from the selected main MT5 account.',
            ),
            actionLabel: context.l10n.text('Select MT5 account'),
            onAction: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => Scaffold(
                  appBar: AppBar(title: Text(context.l10n.text('Accounts'))),
                  body: AccountScreen(
                    user: user,
                    repository: repository,
                    onViewAccessStatus: () => Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => AccessStatusScreen(
                          user: user,
                          authRepository: authRepository,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          );
        }

        return StreamBuilder<List<TradingJournalEntry>>(
          stream: repository.mt5JournalEntriesFor(
            user.uid,
            accountKey: mainAccount.accountKey,
          ),
          builder: (context, journalSnapshot) {
            if (journalSnapshot.hasError) {
              return _ErrorState(error: journalSnapshot.error);
            }
            if (!journalSnapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final journals = journalSnapshot.data!;

            return StreamBuilder<TraderAccountProfile>(
              stream: repository.accountProfileFor(user.uid),
              builder: (context, profileSnapshot) {
                final profile =
                    profileSnapshot.data ??
                    TraderAccountProfile.empty(user.uid);
                final eligibility = _PositionEligibility.fromJournals(journals);

                return StreamBuilder<List<TradingPositionApplicationMobile>>(
                  stream: repository.positionApplicationsFor(
                    user.uid,
                    mt5AccountId: mainAccount.accountKey,
                  ),
                  builder: (context, snapshot) {
                    if (snapshot.hasError) {
                      return _ErrorState(error: snapshot.error);
                    }

                    final applications =
                        snapshot.data ??
                        const <TradingPositionApplicationMobile>[];
                    if (!snapshot.hasData) {
                      return const Center(child: CircularProgressIndicator());
                    }
                    final hasPendingApplication = applications.any(
                      (application) => application.status == 'pending',
                    );
                    final disabledReason = _positionApplyDisabledReason(
                      context,
                      eligibility: eligibility,
                      profile: profile,
                      hasPendingApplication: hasPendingApplication,
                    );
                    final canApply = disabledReason == null;

                    return Stack(
                      children: [
                        ListView(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                          children: [
                            _VerificationCard(
                              profile: profile,
                              eligibility: eligibility,
                              accountLabel: mainAccount.label,
                            ),
                            if (disabledReason != null) ...[
                              const SizedBox(height: 8),
                              Text(
                                disabledReason,
                                style: Theme.of(context).textTheme.bodySmall
                                    ?.copyWith(
                                      color: Theme.of(
                                        context,
                                      ).colorScheme.onSurfaceVariant,
                                    ),
                              ),
                            ],
                            const SizedBox(height: 14),
                            Text(
                              context.l10n.text('Applications'),
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(fontWeight: FontWeight.w900),
                            ),
                            const SizedBox(height: 10),
                            if (applications.isEmpty)
                              SizedBox(
                                height: 220,
                                child: EmptyState(
                                  icon: Icons.work_outline,
                                  title: context.l10n.text(
                                    'No position applications yet',
                                  ),
                                ),
                              )
                            else
                              for (final application in applications)
                                Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: _PositionCard(
                                    application: application,
                                  ),
                                ),
                          ],
                        ),
                        Positioned(
                          right: 16,
                          bottom: 16,
                          child: FloatingActionButton.extended(
                            onPressed: canApply
                                ? () => _openPositionDialog(
                                    context,
                                    journals.length,
                                    mainAccount,
                                  )
                                : null,
                            icon: const Icon(Icons.add),
                            label: Text(context.l10n.text('Apply')),
                          ),
                        ),
                      ],
                    );
                  },
                );
              },
            );
          },
        );
      },
    );
  }

  Future<void> _openPositionDialog(
    BuildContext context,
    int tradeCount,
    Mt5Account mainAccount,
  ) async {
    final submitted = await showDialog<bool>(
      context: context,
      builder: (_) => _PositionApplicationDialog(
        user: user,
        repository: repository,
        tradeCount: tradeCount,
        mainAccount: mainAccount,
      ),
    );
    if (submitted == true && context.mounted) {
      AppFeedback.success(context, 'Position application submitted.');
    }
  }
}

String? _positionApplyDisabledReason(
  BuildContext context, {
  required _PositionEligibility eligibility,
  required TraderAccountProfile profile,
  required bool hasPendingApplication,
}) {
  if (!profile.isApproved) {
    return context.l10n.text(
      'Complete account onboarding before applying for a position.',
    );
  }
  if (hasPendingApplication) {
    return context.l10n.text(
      'You already have a pending position application.',
    );
  }
  if (!eligibility.hasRequiredTradeAge && !eligibility.hasRequiredTradeCount) {
    return context.l10n.text(
      'Apply after {days} more day(s) and {trades} more trade(s).',
      {
        'days': eligibility.remainingDays,
        'trades': eligibility.remainingTradeCount,
      },
    );
  }
  if (!eligibility.hasRequiredTradeAge) {
    return context.l10n.text('Apply after {days} more day(s).', {
      'days': eligibility.remainingDays,
    });
  }
  if (!eligibility.hasRequiredTradeCount) {
    return context.l10n.text('Apply after {trades} more trade(s).', {
      'trades': eligibility.remainingTradeCount,
    });
  }
  return null;
}

class _PositionEligibility {
  const _PositionEligibility({
    required this.tradeCount,
    required this.requiredTradeCount,
    required this.daysSinceFirstTrade,
    required this.requiredDays,
    required this.hasRequiredTradeCount,
    required this.hasRequiredTradeAge,
    this.firstTradeDate,
  });

  final int tradeCount;
  final int requiredTradeCount;
  final int daysSinceFirstTrade;
  final int requiredDays;
  final bool hasRequiredTradeCount;
  final bool hasRequiredTradeAge;
  final DateTime? firstTradeDate;

  bool get eligible => hasRequiredTradeCount && hasRequiredTradeAge;
  int get remainingTradeCount =>
      (requiredTradeCount - tradeCount).clamp(0, requiredTradeCount);
  int get remainingDays =>
      (requiredDays - daysSinceFirstTrade).clamp(0, requiredDays);

  factory _PositionEligibility.fromJournals(
    List<TradingJournalEntry> journals,
  ) {
    final firstTrade = journals.isEmpty
        ? null
        : journals
              .map((journal) => journal.openDate)
              .reduce((left, right) => left.isBefore(right) ? left : right);
    final today = DateTime.now();
    final firstTradeDate = firstTrade == null
        ? null
        : DateTime(firstTrade.year, firstTrade.month, firstTrade.day);
    final todayDate = DateTime(today.year, today.month, today.day);
    final daysSinceFirstTrade = firstTradeDate == null
        ? 0
        : todayDate.difference(firstTradeDate).inDays;
    const requiredDays = 30;
    const requiredTradeCount = 60;

    return _PositionEligibility(
      tradeCount: journals.length,
      requiredTradeCount: requiredTradeCount,
      daysSinceFirstTrade: daysSinceFirstTrade,
      requiredDays: requiredDays,
      hasRequiredTradeAge: daysSinceFirstTrade >= requiredDays,
      hasRequiredTradeCount: journals.length >= requiredTradeCount,
      firstTradeDate: firstTradeDate,
    );
  }
}

class _VerificationCard extends StatelessWidget {
  const _VerificationCard({
    required this.profile,
    required this.eligibility,
    required this.accountLabel,
  });

  final TraderAccountProfile profile;
  final _PositionEligibility eligibility;
  final String accountLabel;

  @override
  Widget build(BuildContext context) {
    final tone = eligibility.eligible
        ? const Color(0xFF17855B)
        : const Color(0xFFD99A17);

    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: tone.withValues(alpha: .12),
                  child: Icon(Icons.verified_outlined, color: tone),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.text('Position Verification'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        context.l10n.text(
                          eligibility.eligible
                              ? 'Eligible to apply'
                              : 'Verification in progress',
                        ),
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: tone,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _ChecklistRow(
              passed: true,
              title: context.l10n.text('Main MT5 account'),
              subtitle: accountLabel,
            ),
            _ChecklistRow(
              passed: eligibility.hasRequiredTradeAge,
              title: context.l10n.text('First trade age'),
              subtitle: eligibility.firstTradeDate == null
                  ? context.l10n.text('No first trade yet')
                  : context.l10n.text('{current}/{required} days', {
                      'current': eligibility.daysSinceFirstTrade,
                      'required': eligibility.requiredDays,
                    }),
            ),
            _ChecklistRow(
              passed: eligibility.hasRequiredTradeCount,
              title: context.l10n.text('Trade records'),
              subtitle: context.l10n.text('{current}/{required} trades', {
                'current': eligibility.tradeCount,
                'required': eligibility.requiredTradeCount,
              }),
            ),
            _ChecklistRow(
              passed: profile.isApproved,
              title: context.l10n.text('Account onboarding'),
              subtitle:
                  '${context.l10n.text(profile.statusLabel)}  |  ${context.l10n.text('profile')}',
            ),
            if (!eligibility.eligible) ...[
              const SizedBox(height: 10),
              Text(
                context.l10n.text(
                  eligibility.firstTradeDate == null
                      ? 'Record your first trade to start the 30-day verification window.'
                      : 'Remaining: {days} day(s), {trades} trade(s).',
                  {
                    'days': eligibility.remainingDays,
                    'trades': eligibility.remainingTradeCount,
                  },
                ),
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ChecklistRow extends StatelessWidget {
  const _ChecklistRow({
    required this.passed,
    required this.title,
    required this.subtitle,
  });

  final bool passed;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    final color = passed ? const Color(0xFF17855B) : const Color(0xFFD99A17);

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Icon(
            passed ? Icons.check_circle : Icons.radio_button_unchecked,
            color: color,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PositionCard extends StatelessWidget {
  const _PositionCard({required this.application});

  final TradingPositionApplicationMobile application;

  @override
  Widget build(BuildContext context) {
    final tone = switch (application.status) {
      'approved' => const Color(0xFF17855B),
      'rejected' => const Color(0xFFC23B36),
      _ => const Color(0xFFD99A17),
    };

    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text(application.positionLabel),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                _StatusPill(
                  label: context.l10n.text(application.statusLabel),
                  color: tone,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text('{count} trade snapshot', {
                'count': application.tradeCountSnapshot,
              }),
            ),
            if (application.strategySummary.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                application.strategySummary,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _PositionApplicationDialog extends StatefulWidget {
  const _PositionApplicationDialog({
    required this.user,
    required this.repository,
    required this.tradeCount,
    required this.mainAccount,
  });

  final AppUser user;
  final TradingRepository repository;
  final int tradeCount;
  final Mt5Account mainAccount;

  @override
  State<_PositionApplicationDialog> createState() =>
      _PositionApplicationDialogState();
}

class _PositionApplicationDialogState
    extends State<_PositionApplicationDialog> {
  final _formKey = GlobalKey<FormState>();
  final _strategyController = TextEditingController();
  final _historyController = TextEditingController();
  final _personalityController = TextEditingController();
  final _marketingController = TextEditingController();
  final _supportController = TextEditingController();
  var _position = 'recruiter';
  var _saving = false;

  @override
  void dispose() {
    _strategyController.dispose();
    _historyController.dispose();
    _personalityController.dispose();
    _marketingController.dispose();
    _supportController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text('Position Application')),
      content: Form(
        key: _formKey,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(
                    value: 'recruiter',
                    icon: const Icon(Icons.person_search_outlined),
                    label: Text(context.l10n.text('Recruiter')),
                  ),
                  ButtonSegment(
                    value: 'leadership',
                    icon: const Icon(Icons.groups_outlined),
                    label: Text(context.l10n.text('Leader')),
                  ),
                ],
                selected: {_position},
                onSelectionChanged: (selection) {
                  setState(() => _position = selection.first);
                },
              ),
              const SizedBox(height: 12),
              _LongField(
                controller: _strategyController,
                label: context.l10n.text('Strategy Summary'),
              ),
              const SizedBox(height: 12),
              _LongField(
                controller: _historyController,
                label: context.l10n.text('Trade History Summary'),
              ),
              const SizedBox(height: 12),
              _LongField(
                controller: _personalityController,
                label: context.l10n.text('Personality Summary'),
              ),
              const SizedBox(height: 12),
              _LongField(
                controller: _marketingController,
                label: context.l10n.text('Marketing Plan'),
                requiredField: _position == 'recruiter',
              ),
              const SizedBox(height: 12),
              _LongField(
                controller: _supportController,
                label: context.l10n.text('Client Support Plan'),
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
              : const Icon(Icons.send_outlined),
          label: Text(context.l10n.text('Submit')),
        ),
      ],
    );
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() => _saving = true);
    try {
      await widget.repository.submitPositionApplication(
        TradingPositionApplicationMobile(
          id: '',
          userId: widget.user.uid,
          requestedPosition: _position,
          mt5AccountId: widget.mainAccount.accountKey,
          mt5Login: widget.mainAccount.login,
          requestedRoleId: TradingPositionApplicationMobile.roleForPosition(
            _position,
          ),
          tradeCountSnapshot: widget.tradeCount,
          strategySummary: _strategyController.text.trim(),
          tradeHistorySummary: _historyController.text.trim(),
          personalitySummary: _personalityController.text.trim(),
          marketingPlan: _marketingController.text.trim(),
          clientSupportPlan: _supportController.text.trim(),
          submittedAt: DateTime.now(),
        ),
      );

      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'submit application');
      }
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }
}

class _ExamTab extends StatefulWidget {
  const _ExamTab({required this.user, required this.repository});

  final AppUser user;
  final TradingRepository repository;

  @override
  State<_ExamTab> createState() => _ExamTabState();
}

class _ExamTabState extends State<_ExamTab> {
  final _answers = <String, int>{};
  final _explanations = <String, TextEditingController>{};
  final _selectedScreenshots = <String, PlatformFile>{};
  final _uploadedScreenshots = <String, DailyQuizUploadResult>{};
  final _uploadTasks = <String, DailyQuizUploadTask>{};
  final _uploadProgress = <String, double>{};
  final _uploadErrors = <String, String>{};
  var _submitting = false;

  @override
  void dispose() {
    for (final controller in _explanations.values) {
      controller.dispose();
    }
    for (final task in _uploadTasks.values) {
      task.cancel();
    }
    super.dispose();
  }

  TextEditingController _explanationFor(String questionId) =>
      _explanations.putIfAbsent(questionId, TextEditingController.new);

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<TradingExamQuestionMobile>>(
      stream: widget.repository.tradingExamQuestionsStream(),
      builder: (context, questionSnapshot) {
        if (questionSnapshot.hasError) {
          return _ErrorState(error: questionSnapshot.error);
        }

        final questions =
            (questionSnapshot.data ?? const <TradingExamQuestionMobile>[])
                .take(5)
                .toList();
        if (!questionSnapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }

        return StreamBuilder<List<TradingExamAttemptMobile>>(
          stream: widget.repository.tradingExamAttemptsFor(widget.user.uid),
          builder: (context, attemptSnapshot) {
            if (attemptSnapshot.hasError) {
              return _ErrorState(error: attemptSnapshot.error);
            }
            if (!attemptSnapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final attempts =
                attemptSnapshot.data ?? const <TradingExamAttemptMobile>[];
            final latestAttempt = attempts.isEmpty ? null : attempts.first;
            final completedToday =
                latestAttempt != null &&
                _sameDate(latestAttempt.examDate, DateTime.now());

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
              children: [
                _ExamHeader(
                  latestAttempt: latestAttempt,
                  questionCount: questions.length,
                  completedToday: completedToday,
                ),
                const SizedBox(height: 14),
                if (questions.length < 5)
                  SizedBox(
                    height: 260,
                    child: EmptyState(
                      icon: Icons.quiz_outlined,
                      title: context.l10n.text(
                        'At least 5 approved questions are needed',
                      ),
                    ),
                  )
                else if (completedToday)
                  _ExamHistory(attempts: attempts, questions: questions)
                else ...[
                  for (final indexedQuestion in questions.indexed)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _QuestionCard(
                        number: indexedQuestion.$1 + 1,
                        question: indexedQuestion.$2,
                        selectedIndex: _answers[indexedQuestion.$2.id],
                        onSelected: (value) {
                          setState(() {
                            _answers[indexedQuestion.$2.id] = value;
                          });
                        },
                        explanationController: _explanationFor(
                          indexedQuestion.$2.id,
                        ),
                        screenshot: _selectedScreenshots[indexedQuestion.$2.id],
                        uploaded:
                            _uploadedScreenshots[indexedQuestion.$2.id] != null,
                        uploadProgress: _uploadProgress[indexedQuestion.$2.id],
                        uploadError: _uploadErrors[indexedQuestion.$2.id],
                        onExplanationChanged: (_) => setState(() {}),
                        onPickScreenshot: () =>
                            _pickScreenshot(indexedQuestion.$2),
                        onRetryUpload: () =>
                            _uploadScreenshot(indexedQuestion.$2),
                        onCancelUpload: () =>
                            _cancelUpload(indexedQuestion.$2.id),
                      ),
                    ),
                  FilledButton.icon(
                    onPressed: _submitting || !_canSubmit(questions)
                        ? null
                        : () => _submit(questions),
                    icon: _submitting
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.check_circle_outline),
                    label: Text(context.l10n.text('Submit Exam')),
                  ),
                ],
              ],
            );
          },
        );
      },
    );
  }

  Future<void> _submit(List<TradingExamQuestionMobile> questions) async {
    setState(() => _submitting = true);
    try {
      final attempt = await widget.repository.submitTradingExamAttempt(
        uid: widget.user.uid,
        questions: questions,
        answers: _answers,
        responses: {
          for (final question in questions)
            question.id: TradingQuizResponseDraft(
              questionId: question.id,
              questionVersion: question.version,
              selectedIndex: _answers[question.id],
              writtenExplanation: _explanations[question.id]?.text.trim() ?? '',
              screenshotUrl:
                  _uploadedScreenshots[question.id]?.downloadUrl ?? '',
              screenshotStoragePath:
                  _uploadedScreenshots[question.id]?.storagePath ?? '',
            ),
        },
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.l10n.text('Trading exam submitted.'))),
        );
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => DailyExamResultScreen(
              attempt: attempt,
              currentQuestions: questions,
            ),
          ),
        );
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  bool _canSubmit(List<TradingExamQuestionMobile> questions) {
    if (_submitting || _uploadTasks.isNotEmpty) return false;
    for (final question in questions) {
      if (question.questionType.requiresChoice &&
          _answers[question.id] == null) {
        return false;
      }
      if (question.needsWrittenExplanation &&
          (_explanations[question.id]?.text.trim().length ?? 0) <
              question.minimumExplanationCharacters) {
        return false;
      }
      if (question.needsScreenshot &&
          _uploadedScreenshots[question.id] == null) {
        return false;
      }
    }
    return true;
  }

  Future<void> _pickScreenshot(TradingExamQuestionMobile question) async {
    final selected = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp'],
      withData: true,
    );
    if (selected == null || selected.files.isEmpty || !mounted) return;
    final file = selected.files.single;
    if (file.bytes == null) {
      AppFeedback.error(context, 'Unable to read the selected image.');
      return;
    }
    setState(() {
      _selectedScreenshots[question.id] = file;
      _uploadedScreenshots.remove(question.id);
      _uploadErrors.remove(question.id);
    });
    await _uploadScreenshot(question);
  }

  Future<void> _uploadScreenshot(TradingExamQuestionMobile question) async {
    final file = _selectedScreenshots[question.id];
    if (file?.bytes == null || _uploadTasks.containsKey(question.id)) return;
    try {
      final task = widget.repository.startDailyQuizScreenshotUpload(
        uid: widget.user.uid,
        questionId: question.id,
        attemptId: widget.repository.dailyQuizAttemptId(),
        filename: file!.name,
        bytes: file.bytes!,
      );
      setState(() {
        _uploadTasks[question.id] = task;
        _uploadProgress[question.id] = 0;
        _uploadErrors.remove(question.id);
      });
      final subscription = task.progress.listen((progress) {
        if (mounted) {
          setState(() => _uploadProgress[question.id] = progress);
        }
      });
      try {
        final result = await task.result;
        if (mounted) {
          setState(() => _uploadedScreenshots[question.id] = result);
        }
      } finally {
        await subscription.cancel();
      }
    } catch (error) {
      if (mounted) {
        setState(() => _uploadErrors[question.id] = '$error');
      }
    } finally {
      if (mounted) {
        setState(() {
          _uploadTasks.remove(question.id);
          _uploadProgress.remove(question.id);
        });
      }
    }
  }

  Future<void> _cancelUpload(String questionId) async {
    await _uploadTasks[questionId]?.cancel();
    if (mounted) {
      setState(() => _uploadErrors[questionId] = 'Upload cancelled.');
    }
  }

  bool _sameDate(DateTime left, DateTime right) {
    return left.year == right.year &&
        left.month == right.month &&
        left.day == right.day;
  }
}

class _ExamHeader extends StatelessWidget {
  const _ExamHeader({
    required this.latestAttempt,
    required this.questionCount,
    required this.completedToday,
  });

  final TradingExamAttemptMobile? latestAttempt;
  final int questionCount;
  final bool completedToday;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            const CircleAvatar(child: Icon(Icons.quiz_outlined)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.text('Daily Trading Exam'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text(
                    completedToday
                        ? latestAttempt!.status == 'pending_review'
                              ? context.l10n.text('Submitted - Pending review')
                              : '${latestAttempt!.score}/${latestAttempt!.totalQuestions} (${latestAttempt!.percentage}%)'
                        : context.l10n.text('{count} questions ready', {
                            'count': questionCount,
                          }),
                    style: Theme.of(context).textTheme.bodyMedium,
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

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({
    required this.number,
    required this.question,
    required this.selectedIndex,
    required this.onSelected,
    required this.explanationController,
    required this.screenshot,
    required this.uploaded,
    required this.uploadProgress,
    required this.uploadError,
    required this.onExplanationChanged,
    required this.onPickScreenshot,
    required this.onRetryUpload,
    required this.onCancelUpload,
  });

  final int number;
  final TradingExamQuestionMobile question;
  final int? selectedIndex;
  final ValueChanged<int> onSelected;
  final TextEditingController explanationController;
  final PlatformFile? screenshot;
  final bool uploaded;
  final double? uploadProgress;
  final String? uploadError;
  final ValueChanged<String> onExplanationChanged;
  final VoidCallback onPickScreenshot;
  final VoidCallback onRetryUpload;
  final VoidCallback onCancelUpload;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Question {number}  |  {difficulty}', {
                'number': number,
                'difficulty': context.l10n.text(question.difficultyLabel),
              }),
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: Theme.of(context).colorScheme.primary,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              question.localizedQuestionText(languageCode),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            _QuizChartPanel(question: question),
            if (question
                .localizedChartDescription(languageCode)
                .isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                question.localizedChartDescription(languageCode),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            const SizedBox(height: 12),
            if (question.questionType.requiresChoice)
              for (final option in question.options.indexed)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _AnswerChoice(
                    selected: selectedIndex == option.$1,
                    text: option.$2.localizedText(languageCode),
                    onTap: () => onSelected(option.$1),
                  ),
                ),
            if (question.needsWrittenExplanation) ...[
              const SizedBox(height: 4),
              TextFormField(
                controller: explanationController,
                minLines: 4,
                maxLines: 8,
                maxLength: question.maximumExplanationCharacters,
                onChanged: onExplanationChanged,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Written chart analysis'),
                  helperText: context.l10n.text(
                    'Explain your structure, key levels, bias, and risk in at least {count} characters.',
                    {'count': question.minimumExplanationCharacters},
                  ),
                  alignLabelWithHint: true,
                ),
              ),
            ],
            if (question.needsScreenshot) ...[
              const SizedBox(height: 8),
              _QuizScreenshotUpload(
                file: screenshot,
                uploaded: uploaded,
                progress: uploadProgress,
                error: uploadError,
                onPick: onPickScreenshot,
                onRetry: onRetryUpload,
                onCancel: onCancelUpload,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _QuizScreenshotUpload extends StatelessWidget {
  const _QuizScreenshotUpload({
    required this.file,
    required this.uploaded,
    required this.progress,
    required this.error,
    required this.onPick,
    required this.onRetry,
    required this.onCancel,
  });

  final PlatformFile? file;
  final bool uploaded;
  final double? progress;
  final String? error;
  final VoidCallback onPick;
  final VoidCallback onRetry;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).dividerColor),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (file?.bytes != null) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.memory(
                  file!.bytes!,
                  height: 150,
                  fit: BoxFit.cover,
                ),
              ),
              const SizedBox(height: 10),
            ],
            Row(
              children: [
                Icon(
                  uploaded
                      ? Icons.cloud_done_outlined
                      : Icons.add_photo_alternate_outlined,
                  color: uploaded ? Colors.teal : null,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    uploaded
                        ? context.l10n.text('Screenshot uploaded securely')
                        : file?.name ??
                              context.l10n.text('Upload your annotated chart'),
                  ),
                ),
                TextButton(
                  onPressed: progress == null ? onPick : onCancel,
                  child: Text(
                    context.l10n.text(
                      progress == null ? 'Choose image' : 'Cancel',
                    ),
                  ),
                ),
              ],
            ),
            if (progress != null) ...[
              const SizedBox(height: 6),
              LinearProgressIndicator(value: progress),
              const SizedBox(height: 4),
              Text('${(progress! * 100).round()}%'),
            ],
            if (error?.trim().isNotEmpty == true) ...[
              const SizedBox(height: 6),
              Text(error!, style: TextStyle(color: scheme.error)),
              Align(
                alignment: Alignment.centerLeft,
                child: TextButton.icon(
                  onPressed: file?.bytes == null ? onPick : onRetry,
                  icon: const Icon(Icons.refresh),
                  label: Text(context.l10n.text('Retry upload')),
                ),
              ),
            ],
            Text(
              context.l10n.text('JPG, PNG, or WEBP · maximum 8 MB'),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _QuizChartPanel extends StatelessWidget {
  const _QuizChartPanel({required this.question});

  final TradingExamQuestionMobile question;

  @override
  Widget build(BuildContext context) {
    final chart = SizedBox(
      height: 220,
      width: double.infinity,
      child: CustomPaint(
        painter: _QuizCandlestickPainter(
          candles: question.chartCandles.isEmpty
              ? _fallbackCandles(question.id)
              : question.chartCandles,
          gridColor: Theme.of(context).dividerColor,
          risingColor: Colors.teal,
          fallingColor: Theme.of(context).colorScheme.error,
        ),
      ),
    );
    if (question.chartImageUrl.trim().isEmpty) {
      return Semantics(
        label: context.l10n.text('Trading chart for this quiz question'),
        child: chart,
      );
    }
    return ClipRRect(
      borderRadius: BorderRadius.circular(10),
      child: Image.network(
        question.chartImageUrl.trim(),
        height: 220,
        width: double.infinity,
        fit: BoxFit.contain,
        errorBuilder: (_, _, _) => chart,
        loadingBuilder: (context, child, progress) => progress == null
            ? child
            : Stack(
                alignment: Alignment.center,
                children: [chart, const CircularProgressIndicator()],
              ),
      ),
    );
  }

  List<TradingQuizCandle> _fallbackCandles(String seed) {
    final bias = seed.codeUnits.fold<int>(0, (value, item) => value + item) % 7;
    var price = 100.0 + bias;
    return List.generate(18, (index) {
      final movement = ((index * 7 + bias * 3) % 9 - 4) / 2;
      final open = price;
      final close = open + movement;
      price = close;
      return TradingQuizCandle(
        open: open,
        close: close,
        high: math.max(open, close) + 1 + (index % 3) * .3,
        low: math.min(open, close) - 1 - (index % 2) * .4,
      );
    });
  }
}

class _QuizCandlestickPainter extends CustomPainter {
  const _QuizCandlestickPainter({
    required this.candles,
    required this.gridColor,
    required this.risingColor,
    required this.fallingColor,
  });

  final List<TradingQuizCandle> candles;
  final Color gridColor;
  final Color risingColor;
  final Color fallingColor;

  @override
  void paint(Canvas canvas, Size size) {
    if (candles.isEmpty) return;
    final chart = Rect.fromLTWH(8, 8, size.width - 16, size.height - 16);
    final high = candles.map((item) => item.high).reduce(math.max);
    final low = candles.map((item) => item.low).reduce(math.min);
    final range = math.max(.001, high - low);
    final grid = Paint()
      ..color = gridColor.withValues(alpha: .45)
      ..strokeWidth = 1;
    for (var line = 0; line <= 4; line++) {
      final y = chart.top + chart.height * line / 4;
      canvas.drawLine(Offset(chart.left, y), Offset(chart.right, y), grid);
    }
    final slot = chart.width / candles.length;
    final bodyWidth = math.max(2.0, slot * .55);
    double y(double price) =>
        chart.bottom - (price - low) / range * chart.height;
    for (var index = 0; index < candles.length; index++) {
      final candle = candles[index];
      final x = chart.left + slot * (index + .5);
      final color = candle.close >= candle.open ? risingColor : fallingColor;
      final paint = Paint()
        ..color = color
        ..strokeWidth = 1.4;
      canvas.drawLine(
        Offset(x, y(candle.high)),
        Offset(x, y(candle.low)),
        paint,
      );
      final top = math.min(y(candle.open), y(candle.close));
      final bottom = math.max(y(candle.open), y(candle.close));
      canvas.drawRect(
        Rect.fromLTRB(
          x - bodyWidth / 2,
          top,
          x + bodyWidth / 2,
          math.max(top + 1.5, bottom),
        ),
        paint,
      );
    }
  }

  @override
  bool shouldRepaint(covariant _QuizCandlestickPainter oldDelegate) =>
      oldDelegate.candles != candles ||
      oldDelegate.gridColor != gridColor ||
      oldDelegate.risingColor != risingColor ||
      oldDelegate.fallingColor != fallingColor;
}

class _AnswerChoice extends StatelessWidget {
  const _AnswerChoice({
    required this.selected,
    required this.text,
    required this.onTap,
  });

  final bool selected;
  final String text;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected
        ? Theme.of(context).colorScheme.primary
        : Theme.of(context).colorScheme.outline;

    return InkWell(
      borderRadius: BorderRadius.circular(8),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: color),
          color: selected
              ? Theme.of(
                  context,
                ).colorScheme.primaryContainer.withValues(alpha: .35)
              : null,
        ),
        child: Row(
          children: [
            Icon(
              selected ? Icons.check_circle : Icons.radio_button_unchecked,
              color: color,
              size: 20,
            ),
            const SizedBox(width: 10),
            Expanded(child: Text(text)),
          ],
        ),
      ),
    );
  }
}

class _ExamHistory extends StatelessWidget {
  const _ExamHistory({required this.attempts, required this.questions});

  final List<TradingExamAttemptMobile> attempts;
  final List<TradingExamQuestionMobile> questions;

  @override
  Widget build(BuildContext context) {
    if (attempts.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text('Recent Attempts'),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 10),
        for (final attempt in attempts.take(10))
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Card(
              elevation: 0,
              child: ListTile(
                leading: const Icon(Icons.fact_check_outlined),
                title: Text(formatDate(attempt.examDate)),
                subtitle: Text(
                  attempt.status == 'pending_review'
                      ? context.l10n.text('Pending review')
                      : context.l10n.text('{score}/{total} correct', {
                          'score': attempt.score,
                          'total': attempt.totalQuestions,
                        }),
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => DailyExamResultScreen(
                      attempt: attempt,
                      currentQuestions: questions,
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }
}

class _ClassesTab extends StatelessWidget {
  const _ClassesTab({required this.repository, required this.authRepository});

  final TradingRepository repository;
  final AuthRepository authRepository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<TradingRecordingClassMobile>>(
      stream: repository.tradingRecordingsStream(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error);
        }

        final recordings =
            snapshot.data ?? const <TradingRecordingClassMobile>[];
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }

        if (recordings.isEmpty) {
          return EmptyState(
            icon: Icons.video_library_outlined,
            title: context.l10n.text('No recording classes yet'),
          );
        }

        final materialCount = recordings.fold<int>(
          0,
          (total, recording) => total + recording.materials.length,
        );
        final sourceCount = recordings
            .map((recording) => recording.sourceName.trim())
            .where((source) => source.isNotEmpty)
            .toSet()
            .length;

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _HubHeaderPanel(
              eyebrow: context.l10n.text('Recording Classes'),
              title: context.l10n.text('Recording Classes'),
              description: context.l10n.text(
                'Access approved mentor recordings and class materials after verifying your current login password.',
              ),
              stats: [
                _HeaderStat(
                  label: context.l10n.text('Available Classes'),
                  value: '${recordings.length}',
                ),
                _HeaderStat(
                  label: context.l10n.text('Materials'),
                  value: '$materialCount',
                ),
                _HeaderStat(
                  label: context.l10n.text('Sources'),
                  value: '$sourceCount',
                ),
              ],
            ),
            const SizedBox(height: 14),
            const _RecordingSecurityNotice(),
            const SizedBox(height: 14),
            _ResponsiveCardGrid(
              children: [
                for (final recording in recordings)
                  _RecordingClassCard(
                    recording: recording,
                    onView: () => _openRecording(context, recording),
                    onDownload: () => _downloadRecording(context, recording),
                  ),
              ],
            ),
          ],
        );
      },
    );
  }

  Future<void> _openRecording(
    BuildContext context,
    TradingRecordingClassMobile recording,
  ) async {
    final verified = await _verifyPassword(
      context,
      authRepository,
      reason: context.l10n.text(
        'Enter your login password to view this recording class.',
      ),
    );
    if (!verified || !context.mounted) {
      return;
    }

    final access = await authRepository.accessRecording(recording.id);
    final unlockedRecording = recording.withAccess(access);
    if (!context.mounted) {
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _RecordingDetailScreen(
          recording: unlockedRecording,
          authRepository: authRepository,
        ),
      ),
    );
  }

  Future<void> _downloadRecording(
    BuildContext context,
    TradingRecordingClassMobile recording,
  ) async {
    final verified = await _verifyPassword(
      context,
      authRepository,
      reason: context.l10n.text(
        'Enter your login password to download this recording.',
      ),
    );
    if (!verified || !context.mounted) {
      return;
    }

    final access = await authRepository.accessRecording(recording.id);
    final unlockedRecording = recording.withAccess(access);
    if (!context.mounted) {
      return;
    }

    await _launchExternalUrl(
      context,
      unlockedRecording.effectiveDownloadUrl,
      emptyMessage: context.l10n.text(
        'No recording download URL is available yet.',
      ),
    );
  }
}

class _RecordingDetailScreen extends StatelessWidget {
  const _RecordingDetailScreen({
    required this.recording,
    required this.authRepository,
  });

  final TradingRecordingClassMobile recording;
  final AuthRepository authRepository;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Recording Class'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _DetailTitleBlock(
            eyebrow: context.l10n.text('Recording Class'),
            title: recording.localizedTitle(languageCode),
            subtitle: [
              if (recording.sourceName.trim().isNotEmpty) recording.sourceName,
              if (recording.createdAt != null) formatDate(recording.createdAt!),
              context.l10n.text('{count} material(s)', {
                'count': recording.materials.length,
              }),
            ].join('  |  '),
          ),
          const SizedBox(height: 14),
          _DetailInfoPanel(
            rows: [
              (context.l10n.text('Source'), recording.sourceName),
              (
                context.l10n.text('Uploaded'),
                recording.createdAt == null
                    ? ''
                    : formatDate(recording.createdAt!),
              ),
              (context.l10n.text('Materials'), '${recording.materials.length}'),
              (context.l10n.text('Password'), context.l10n.text('Required')),
            ],
          ),
          const SizedBox(height: 14),
          _RecordingAccessPanel(
            onOpen: () => _verifiedLaunch(
              context,
              url: recording.videoUrl,
              reason: context.l10n.text(
                'Enter your login password to open this recording.',
              ),
              emptyMessage: context.l10n.text(
                'No recording website URL is available yet.',
              ),
            ),
            onDownload: () => _verifiedLaunch(
              context,
              url: recording.effectiveDownloadUrl,
              reason: context.l10n.text(
                'Enter your login password to download this recording.',
              ),
              emptyMessage: context.l10n.text(
                'No recording download URL is available yet.',
              ),
            ),
          ),
          if (recording
              .localizedDescription(languageCode)
              .trim()
              .isNotEmpty) ...[
            const SizedBox(height: 14),
            _DetailSection(
              title: context.l10n.text('Description'),
              body: recording.localizedDescription(languageCode),
            ),
          ],
          if (recording.materials.isNotEmpty) ...[
            const SizedBox(height: 14),
            _RelatedPanel(
              title: context.l10n.text('Class Materials'),
              children: [
                for (final material in recording.materials)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.attach_file),
                    title: Text(
                      material.localizedTitle(languageCode).isEmpty
                          ? material.originalFilename
                          : material.localizedTitle(languageCode),
                    ),
                    subtitle: Text(
                      [
                        material.fileSizeLabel,
                        if (material.downloadCount > 0)
                          context.l10n.text('{count} downloads', {
                            'count': material.downloadCount,
                          }),
                      ].join('  |  '),
                    ),
                    trailing: IconButton(
                      tooltip: context.l10n.text('Download material'),
                      icon: const Icon(Icons.download_outlined),
                      onPressed: () => _verifiedLaunch(
                        context,
                        url: material.filePath,
                        reason: context.l10n.text(
                          'Enter your login password to download this material.',
                        ),
                        emptyMessage: context.l10n.text(
                          'No mobile material download URL is available yet.',
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _verifiedLaunch(
    BuildContext context, {
    required String url,
    required String reason,
    required String emptyMessage,
  }) async {
    final verified = await _verifyPassword(
      context,
      authRepository,
      reason: reason,
    );
    if (!verified || !context.mounted) {
      return;
    }

    await _launchExternalUrl(context, url, emptyMessage: emptyMessage);
  }
}

class _RecordingSecurityNotice extends StatelessWidget {
  const _RecordingSecurityNotice();

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              Icons.lock_outline,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.text('Password required'),
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    context.l10n.text(
                      'Enter your current login password before viewing a recording website or downloading class files.',
                    ),
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
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

class _RecordingClassCard extends StatelessWidget {
  const _RecordingClassCard({
    required this.recording,
    required this.onView,
    required this.onDownload,
  });

  final TradingRecordingClassMobile recording;
  final VoidCallback onView;
  final VoidCallback onDownload;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onView,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(child: const Icon(Icons.video_library_outlined)),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          recording.localizedTitle(languageCode),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          recording.sourceName.isEmpty
                              ? context.l10n.text('Recording Class')
                              : recording.sourceName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelMedium
                              ?.copyWith(
                                color: Theme.of(
                                  context,
                                ).colorScheme.onSurfaceVariant,
                              ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Expanded(
                child: Text(
                  _previewText(recording.localizedDescription(languageCode)),
                  maxLines: 5,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _InfoChip(
                    label: context.l10n.text('{count} material(s)', {
                      'count': recording.materials.length,
                    }),
                  ),
                  if (recording.createdAt != null)
                    _InfoChip(label: formatDate(recording.createdAt!)),
                  _InfoChip(label: context.l10n.text('Password required')),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: onView,
                      icon: const Icon(Icons.visibility_outlined),
                      label: Text(context.l10n.text('View')),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onDownload,
                      icon: const Icon(Icons.download_outlined),
                      label: Text(context.l10n.text('Download')),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _RecordingAccessPanel extends StatelessWidget {
  const _RecordingAccessPanel({required this.onOpen, required this.onDownload});

  final VoidCallback onOpen;
  final VoidCallback onDownload;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxWidth < 520;
            final openButton = FilledButton.icon(
              onPressed: onOpen,
              icon: const Icon(Icons.open_in_new),
              label: Text(context.l10n.text('Open Recording Website')),
            );
            final downloadButton = OutlinedButton.icon(
              onPressed: onDownload,
              icon: const Icon(Icons.download_outlined),
              label: Text(context.l10n.text('Download Recording')),
            );

            if (compact) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  openButton,
                  const SizedBox(height: 10),
                  downloadButton,
                ],
              );
            }

            return Row(
              children: [
                Expanded(child: openButton),
                const SizedBox(width: 10),
                Expanded(child: downloadButton),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _PasswordVerificationDialog extends StatefulWidget {
  const _PasswordVerificationDialog({
    required this.authRepository,
    required this.reason,
  });

  final AuthRepository authRepository;
  final String reason;

  @override
  State<_PasswordVerificationDialog> createState() =>
      _PasswordVerificationDialogState();
}

class _PasswordVerificationDialogState
    extends State<_PasswordVerificationDialog> {
  final _formKey = GlobalKey<FormState>();
  final _passwordController = TextEditingController();
  var _saving = false;
  var _obscure = true;
  String? _error;

  @override
  void dispose() {
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text('Verify Identity')),
      content: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.reason),
            const SizedBox(height: 14),
            TextFormField(
              controller: _passwordController,
              enabled: !_saving,
              obscureText: _obscure,
              autofocus: true,
              textInputAction: TextInputAction.done,
              decoration: InputDecoration(
                labelText: context.l10n.text('Login password'),
                prefixIcon: const Icon(Icons.lock_outline),
                suffixIcon: IconButton(
                  tooltip: context.l10n.text(
                    _obscure ? 'Show password' : 'Hide password',
                  ),
                  onPressed: _saving
                      ? null
                      : () => setState(() => _obscure = !_obscure),
                  icon: Icon(
                    _obscure ? Icons.visibility : Icons.visibility_off,
                  ),
                ),
              ),
              validator: (value) => (value ?? '').trim().isEmpty
                  ? context.l10n.text('Password is required')
                  : null,
              onFieldSubmitted: (_) => _submit(),
            ),
            if (_error != null) ...[
              const SizedBox(height: 10),
              Text(
                _error!,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.error,
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton.icon(
          onPressed: _saving ? null : _submit,
          icon: _saving
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.lock_open_outlined),
          label: Text(context.l10n.text('Verify')),
        ),
      ],
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      await widget.authRepository.verifyCurrentPassword(
        password: _passwordController.text,
      );
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = error.toString();
        });
      }
    }
  }
}

Future<bool> _verifyPassword(
  BuildContext context,
  AuthRepository authRepository, {
  required String reason,
}) async {
  final verified = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (context) => _PasswordVerificationDialog(
      authRepository: authRepository,
      reason: reason,
    ),
  );

  return verified == true;
}

Future<void> _launchExternalUrl(
  BuildContext context,
  String url, {
  required String emptyMessage,
}) async {
  final trimmed = url.trim();
  final uri = Uri.tryParse(trimmed);
  if (trimmed.isEmpty || uri == null || !uri.hasScheme) {
    _showMessage(context, emptyMessage);
    return;
  }

  final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
  if (!launched && context.mounted) {
    _showMessage(context, 'Unable to open this link on the device.');
  }
}

void _showMessage(BuildContext context, String message) {
  if (!context.mounted) {
    return;
  }

  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
}

class _ResourcesTab extends StatefulWidget {
  const _ResourcesTab({required this.repository, required this.authRepository});

  final TradingRepository repository;
  final AuthRepository authRepository;

  @override
  State<_ResourcesTab> createState() => _ResourcesTabState();
}

class _ResourcesTabState extends State<_ResourcesTab> {
  final _searchController = TextEditingController();
  var _typeFilter = 'all';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<TradingResourceItem>>(
      stream: widget.repository.tradingResourcesStream(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error);
        }

        final resources = snapshot.data ?? const <TradingResourceItem>[];
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }

        if (resources.isEmpty) {
          return EmptyState(
            icon: Icons.video_library_outlined,
            title: context.l10n.text('No trading resources yet'),
          );
        }

        final filtered = _filteredResources(resources);
        final types =
            resources
                .map((resource) => resource.type.trim())
                .where((type) => type.isNotEmpty)
                .toSet()
                .toList()
              ..sort();
        final typeCounts = <String, int>{};
        for (final resource in resources) {
          typeCounts.update(
            resource.type,
            (value) => value + 1,
            ifAbsent: () => 1,
          );
        }
        final latestDate = resources
            .map((resource) => resource.createdAt)
            .whereType<DateTime>()
            .fold<DateTime?>(
              null,
              (latest, date) =>
                  latest == null || date.isAfter(latest) ? date : latest,
            );

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _HubHeaderPanel(
              eyebrow: context.l10n.text('Resource Library'),
              title: context.l10n.text('Trading Resources'),
              description: context.l10n.text(
                'Open approved class files, marketing packs, knowledge material, and trader support assets from one workspace.',
              ),
              stats: [
                _HeaderStat(
                  label: context.l10n.text('Available Files'),
                  value: '${resources.length}',
                ),
                _HeaderStat(
                  label: context.l10n.text('Types'),
                  value: '${types.length}',
                ),
                _HeaderStat(
                  label: context.l10n.text('Latest'),
                  value: latestDate == null ? '-' : formatDate(latestDate),
                ),
              ],
            ),
            const SizedBox(height: 14),
            const _ResourceSecurityNotice(),
            const SizedBox(height: 14),
            _ResourceFilterPanel(
              searchController: _searchController,
              activeType: _typeFilter,
              types: types,
              typeCounts: typeCounts,
              onSearchChanged: (_) => setState(() {}),
              onTypeChanged: (type) => setState(() => _typeFilter = type),
              onClearSearch: () {
                _searchController.clear();
                setState(() {});
              },
            ),
            const SizedBox(height: 14),
            if (filtered.isEmpty)
              EmptyState(
                icon: Icons.folder_outlined,
                title: context.l10n.text('No resources match your filters'),
              )
            else
              _ResponsiveCardGrid(
                children: [
                  for (final resource in filtered)
                    _ResourceLibraryCard(
                      resource: resource,
                      onDetails: () => _openResourceDetail(resource, filtered),
                      onOpen: () => _openProtectedResource(resource),
                    ),
                ],
              ),
          ],
        );
      },
    );
  }

  List<TradingResourceItem> _filteredResources(
    List<TradingResourceItem> resources,
  ) {
    final search = _searchController.text.trim().toLowerCase();
    final languageCode = context.l10n.locale.languageCode;

    return resources.where((resource) {
      final matchesType = _typeFilter == 'all' || resource.type == _typeFilter;
      final matchesSearch =
          search.isEmpty ||
          [
            resource.localizedTitle(languageCode),
            resource.localizedDescription(languageCode),
            resource.type,
            resource.url,
          ].any((value) => value.toLowerCase().contains(search));

      return matchesType && matchesSearch;
    }).toList();
  }

  void _openResourceDetail(
    TradingResourceItem resource,
    List<TradingResourceItem> relatedPool,
  ) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _ResourceDetailScreen(
          resource: resource,
          relatedResources: relatedPool
              .where((item) => item.id != resource.id)
              .take(3)
              .toList(),
          authRepository: widget.authRepository,
        ),
      ),
    );
  }

  Future<void> _openProtectedResource(TradingResourceItem resource) async {
    final verified = await _verifyPassword(
      context,
      widget.authRepository,
      reason: context.l10n.text(
        'Enter your login password to open this resource.',
      ),
    );
    if (!verified || !mounted) {
      return;
    }

    final url = await widget.authRepository.accessMarketingResource(
      resource.id,
    );
    if (!mounted) {
      return;
    }

    await _launchExternalUrl(
      context,
      url,
      emptyMessage: context.l10n.text('No resource URL is available yet.'),
    );
  }
}

class _ResourceSecurityNotice extends StatelessWidget {
  const _ResourceSecurityNotice();

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              Icons.verified_user_outlined,
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.text('Password-confirmed access'),
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    context.l10n.text(
                      'Your login password is required before opening an approved resource.',
                    ),
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
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

class _ResourceFilterPanel extends StatelessWidget {
  const _ResourceFilterPanel({
    required this.searchController,
    required this.activeType,
    required this.types,
    required this.typeCounts,
    required this.onSearchChanged,
    required this.onTypeChanged,
    required this.onClearSearch,
  });

  final TextEditingController searchController;
  final String activeType;
  final List<String> types;
  final Map<String, int> typeCounts;
  final ValueChanged<String> onSearchChanged;
  final ValueChanged<String> onTypeChanged;
  final VoidCallback onClearSearch;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      selected: activeType == 'all',
                      label: Text(context.l10n.text('All')),
                      onSelected: (_) => onTypeChanged('all'),
                    ),
                  ),
                  for (final type in types)
                    Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: ChoiceChip(
                        selected: activeType == type,
                        avatar: Icon(_resourceIcon(type), size: 16),
                        label: Text(
                          '${_localizedResourceTypeLabel(context, type)} (${typeCounts[type] ?? 0})',
                        ),
                        onSelected: (_) => onTypeChanged(type),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: searchController,
              onChanged: onSearchChanged,
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search),
                labelText: context.l10n.text('Search resources'),
                suffixIcon: searchController.text.trim().isEmpty
                    ? null
                    : IconButton(
                        tooltip: context.l10n.text('Clear search'),
                        onPressed: onClearSearch,
                        icon: const Icon(Icons.close),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ResourceLibraryCard extends StatelessWidget {
  const _ResourceLibraryCard({
    required this.resource,
    required this.onDetails,
    required this.onOpen,
  });

  final TradingResourceItem resource;
  final VoidCallback onDetails;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final hasUrl = resource.url.trim().isNotEmpty;
    final languageCode = context.l10n.locale.languageCode;

    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onDetails,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CircleAvatar(child: Icon(_resourceIcon(resource.type))),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          resource.localizedTitle(languageCode),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          _localizedResourceTypeLabel(context, resource.type),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelMedium
                              ?.copyWith(
                                color: Theme.of(
                                  context,
                                ).colorScheme.onSurfaceVariant,
                              ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Expanded(
                child: Text(
                  _previewText(resource.localizedDescription(languageCode)),
                  maxLines: 5,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  _InfoChip(
                    label: _localizedResourceTypeLabel(context, resource.type),
                  ),
                  if (resource.createdAt != null)
                    _InfoChip(label: formatDate(resource.createdAt!)),
                  _InfoChip(
                    label: context.l10n.text(
                      hasUrl ? 'Ready to open' : 'URL missing',
                    ),
                  ),
                  _InfoChip(label: context.l10n.text('Password required')),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: FilledButton.icon(
                      onPressed: onDetails,
                      icon: const Icon(Icons.info_outline),
                      label: Text(context.l10n.text('Details')),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onOpen,
                      icon: const Icon(Icons.open_in_new),
                      label: Text(context.l10n.text('Open')),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ResourceDetailScreen extends StatelessWidget {
  const _ResourceDetailScreen({
    required this.resource,
    required this.relatedResources,
    required this.authRepository,
  });

  final TradingResourceItem resource;
  final List<TradingResourceItem> relatedResources;
  final AuthRepository authRepository;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Resource'))),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _DetailTitleBlock(
            eyebrow: _localizedResourceTypeLabel(context, resource.type),
            title: resource.localizedTitle(languageCode),
            subtitle: [
              if (resource.createdAt != null) formatDate(resource.createdAt!),
              context.l10n.text(
                resource.url.trim().isEmpty ? 'URL missing' : 'Ready to open',
              ),
              context.l10n.text('Password required'),
            ].join('  |  '),
          ),
          const SizedBox(height: 14),
          _DetailInfoPanel(
            rows: [
              (
                context.l10n.text('Type'),
                _localizedResourceTypeLabel(context, resource.type),
              ),
              (
                context.l10n.text('Uploaded'),
                resource.createdAt == null
                    ? ''
                    : formatDate(resource.createdAt!),
              ),
              (
                context.l10n.text('Access'),
                context.l10n.text('Password required'),
              ),
              (
                context.l10n.text('Link'),
                resource.url.trim().isEmpty
                    ? ''
                    : context.l10n.text('Available'),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _ResourceAccessPanel(
            onOpen: () => _verifiedOpen(context),
            hasUrl: resource.url.trim().isNotEmpty,
          ),
          if (resource
              .localizedDescription(languageCode)
              .trim()
              .isNotEmpty) ...[
            const SizedBox(height: 14),
            _DetailSection(
              title: context.l10n.text('Description'),
              body: resource.localizedDescription(languageCode),
            ),
          ],
          if (resource.url.trim().isNotEmpty) ...[
            const SizedBox(height: 14),
            _DetailSection(
              title: context.l10n.text('Resource Link'),
              body: resource.url,
            ),
          ],
          if (relatedResources.isNotEmpty) ...[
            const SizedBox(height: 14),
            _RelatedPanel(
              title: context.l10n.text('Related Resources'),
              children: [
                for (final item in relatedResources)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(_resourceIcon(item.type)),
                    title: Text(item.localizedTitle(languageCode)),
                    subtitle: Text(
                      _localizedResourceTypeLabel(context, item.type),
                    ),
                    onTap: () => Navigator.of(context).pushReplacement(
                      MaterialPageRoute<void>(
                        builder: (_) => _ResourceDetailScreen(
                          resource: item,
                          relatedResources: relatedResources
                              .where((related) => related.id != item.id)
                              .toList(),
                          authRepository: authRepository,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _verifiedOpen(BuildContext context) async {
    final verified = await _verifyPassword(
      context,
      authRepository,
      reason: context.l10n.text(
        'Enter your login password to open this resource.',
      ),
    );
    if (!verified || !context.mounted) {
      return;
    }

    final url = await authRepository.accessMarketingResource(resource.id);
    if (!context.mounted) {
      return;
    }

    await _launchExternalUrl(
      context,
      url,
      emptyMessage: context.l10n.text('No resource URL is available yet.'),
    );
  }
}

class _ResourceAccessPanel extends StatelessWidget {
  const _ResourceAccessPanel({required this.onOpen, required this.hasUrl});

  final VoidCallback onOpen;
  final bool hasUrl;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final copy = Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  hasUrl ? Icons.lock_open_outlined : Icons.link_off_outlined,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    hasUrl
                        ? context.l10n.text(
                            'Verify your password to open this resource.',
                          )
                        : context.l10n.text(
                            'This resource does not have a mobile URL yet.',
                          ),
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ),
              ],
            );
            final button = FilledButton.icon(
              onPressed: onOpen,
              icon: const Icon(Icons.open_in_new),
              label: Text(context.l10n.text('Open Resource')),
            );

            if (constraints.maxWidth < 520) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [copy, const SizedBox(height: 12), button],
              );
            }

            return Row(
              children: [
                Expanded(child: copy),
                const SizedBox(width: 12),
                button,
              ],
            );
          },
        ),
      ),
    );
  }
}

IconData _resourceIcon(String type) {
  return switch (type) {
    'recording' => Icons.video_library_outlined,
    'knowledge' => Icons.school_outlined,
    'marketing' => Icons.campaign_outlined,
    'blog' => Icons.article_outlined,
    'signal' => Icons.online_prediction,
    _ => Icons.folder_outlined,
  };
}

String _resourceTypeLabel(String type) {
  final normalized = type.trim();
  if (normalized.isEmpty) {
    return 'Resource';
  }

  return switch (normalized) {
    'recording' => 'Recording',
    'knowledge' => 'Knowledge',
    'marketing' => 'Marketing',
    'blog' => 'Blog',
    'signal' => 'Signal',
    _ =>
      normalized
          .split(RegExp(r'[_\-\s]+'))
          .where((part) => part.isNotEmpty)
          .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
          .join(' '),
  };
}

String _localizedResourceTypeLabel(BuildContext context, String type) {
  return context.l10n.text(_resourceTypeLabel(type));
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: color,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _MiniFigure extends StatelessWidget {
  const _MiniFigure({
    required this.label,
    required this.value,
    this.alignEnd = false,
  });

  final String label;
  final String value;
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
          value.isEmpty ? '-' : value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(
            context,
          ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
        ),
      ],
    );
  }
}

class _LongField extends StatelessWidget {
  const _LongField({
    required this.controller,
    required this.label,
    this.requiredField = true,
  });

  final TextEditingController controller;
  final String label;
  final bool requiredField;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      minLines: 2,
      maxLines: 4,
      decoration: InputDecoration(labelText: label, alignLabelWithHint: true),
      validator: (value) {
        if (!requiredField) {
          return null;
        }

        return (value ?? '').trim().isEmpty
            ? context.l10n.text('Required')
            : null;
      },
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({this.error, this.onRetry});

  final Object? error;
  final Future<void> Function()? onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(error?.toString() ?? 'Unable to load trading hub data.'),
            if (onRetry != null) ...[
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () => onRetry!(),
                icon: const Icon(Icons.refresh),
                label: Text(context.l10n.text('Retry')),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
