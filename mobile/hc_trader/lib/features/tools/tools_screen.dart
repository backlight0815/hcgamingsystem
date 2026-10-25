import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/access_policy.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/trading_data_source.dart';
import '../../repositories/trading_repository.dart';
import '../../services/toolkit_search_service.dart';
import '../../shared/app_back_button.dart';
import '../prop_firm/prop_firm_screen.dart';
import '../multi_account_prop_firm/presentation/prop_firm_planner_screen.dart';
import '../performance/performance_rating_screen.dart';
import '../psychology/psychology_screen.dart';
import '../psychology/personality_guidance_screen.dart';
import '../risk/risk_calculator_screen.dart';
import 'average_price/presentation/average_price_screen.dart';
import '../statistics/statistics_screen.dart';
import '../trading_plan/trading_plan_screen.dart';
import '../trading_plan/trading_strategy_screen.dart';
import 'toolkit_models.dart';
import 'toolkit_widgets.dart';

class ToolsScreen extends StatefulWidget {
  const ToolsScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.storage,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;

  @override
  State<ToolsScreen> createState() => _ToolsScreenState();
}

class _ToolsScreenState extends State<ToolsScreen> {
  final _search = TextEditingController();
  final _searchService = const ToolkitSearchService();
  ToolkitCategory? _category;
  Timer? _searchDebounce;
  String _query = '';
  var _searchPending = false;
  late ToolkitDashboardSnapshot _snapshot;
  late ToolkitRecentStore _recentStore;
  var _recent = const <ToolkitRecentEntry>[];
  var _openingTool = false;

  @override
  void initState() {
    super.initState();
    _loadLocalState();
  }

  @override
  void didUpdateWidget(covariant ToolsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.user.uid != widget.user.uid ||
        oldWidget.storage != widget.storage) {
      _loadLocalState();
    }
  }

  void _loadLocalState() {
    _recentStore = ToolkitRecentStore(widget.storage);
    _recent = _recentStore.load(widget.user.uid);
    _snapshot = const ToolkitDashboardService().load(
      widget.storage,
      widget.user.uid,
    );
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final availableTools = _availableTools;
    final filtered = _searchService.search(
      items: availableTools,
      localizations: l10n,
      query: _query,
      category: _category,
    );
    final quickActions = _quickActions(availableTools);
    final recentTools = _recent
        .map(
          (entry) => availableTools
              .where((tool) => tool.id == entry.toolId)
              .firstOrNull,
        )
        .whereType<ToolkitItem>()
        .toList(growable: false);
    final recommendations = _recommendations(availableTools);

    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(l10n.text('Trading Toolkit')),
      ),
      body: LayoutBuilder(
        builder: (context, viewport) {
          final contentWidth = viewport.maxWidth
              .clamp(0, ToolkitLayout.maxContentWidth)
              .toDouble();
          final pagePadding = ToolkitLayout.pagePadding(contentWidth);
          return SingleChildScrollView(
            padding: pagePadding,
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(
                  maxWidth: ToolkitLayout.maxContentWidth,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    ToolkitHero(
                      title: l10n.text('Trading Toolkit'),
                      description: l10n.text(
                        'Prepare, analyze and improve your trading from one focused workspace.',
                      ),
                      searchHint: l10n.text('Search trading tools'),
                      searchTooltip: l10n.text('Search toolkit'),
                      controller: _search,
                      onSearchChanged: _onSearchChanged,
                      summaryLabels: _heroSummaryLabels(
                        l10n,
                        availableTools.length,
                      ),
                    ),
                    const SizedBox(height: 22),
                    ToolkitAnimatedSection(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          ToolkitSectionHeader(
                            title: l10n.text('Quick Actions'),
                            subtitle: l10n.text(
                              'Jump straight into your most-used workflows.',
                            ),
                          ),
                          const SizedBox(height: 10),
                          _quickActionGrid(contentWidth, quickActions, l10n),
                        ],
                      ),
                    ),
                    if (recentTools.isNotEmpty) ...[
                      const SizedBox(height: 22),
                      ToolkitAnimatedSection(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            ToolkitSectionHeader(
                              title: l10n.text('Recently Used'),
                              subtitle: l10n.text(
                                'Continue where you left off.',
                              ),
                            ),
                            const SizedBox(height: 8),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                for (final tool in recentTools)
                                  ToolkitRecentTool(
                                    key: ValueKey('recent-${tool.id}'),
                                    title: l10n.text(tool.titleKey),
                                    icon: tool.icon,
                                    accent: _accentFor(tool),
                                    onTap: _openingTool
                                        ? null
                                        : () => _openTool(tool),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                    if (recommendations.isNotEmpty &&
                        _query.isEmpty &&
                        _category == null) ...[
                      const SizedBox(height: 22),
                      ToolkitAnimatedSection(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            ToolkitSectionHeader(
                              title: l10n.text('Recommended Next'),
                              subtitle: l10n.text(
                                'Suggestions based only on your saved setup.',
                              ),
                            ),
                            const SizedBox(height: 10),
                            _recommendationGrid(
                              contentWidth,
                              recommendations,
                              l10n,
                            ),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 22),
                    ToolkitSectionHeader(
                      title: l10n.text('Explore Tools'),
                      subtitle: _query.isEmpty && _category == null
                          ? l10n.text(
                              'Filter by workflow or search by name and purpose.',
                            )
                          : l10n.text('{count} toolkit results', {
                              'count': filtered.length,
                            }),
                    ),
                    if (_searchPending) ...[
                      const SizedBox(height: 8),
                      Semantics(
                        label: l10n.text('Searching toolkit'),
                        child: const LinearProgressIndicator(minHeight: 2),
                      ),
                    ],
                    const SizedBox(height: 10),
                    _categoryFilters(l10n),
                    const SizedBox(height: 14),
                    AnimatedSwitcher(
                      duration: MediaQuery.disableAnimationsOf(context)
                          ? Duration.zero
                          : const Duration(milliseconds: 180),
                      child: filtered.isEmpty
                          ? ToolkitEmptyState(
                              key: const ValueKey('empty-search'),
                              title: l10n.text('No matching tools'),
                              message: l10n.text(
                                'Try another search term or clear the category filter.',
                              ),
                            )
                          : _categorySections(
                              key: ValueKey('${_category?.name}-$_query'),
                              width: contentWidth,
                              tools: filtered,
                              l10n: l10n,
                            ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }

  void _onSearchChanged(String value) {
    _searchDebounce?.cancel();
    final normalized = ToolkitSearchService.normalize(value);
    if (normalized.isEmpty) {
      setState(() {
        _query = '';
        _searchPending = false;
      });
      return;
    }
    setState(() => _searchPending = true);
    _searchDebounce = Timer(const Duration(milliseconds: 250), () {
      if (!mounted) return;
      setState(() {
        _query = normalized;
        _searchPending = false;
      });
    });
  }

  List<ToolkitItem> get _availableTools {
    final fullAccess = ClientAccessPolicy.hasFullAccess(widget.user);
    return toolkitCatalog
        .where((tool) => !tool.requiresFullAccess || fullAccess)
        .toList(growable: false);
  }

  List<ToolkitItem> _quickActions(List<ToolkitItem> available) {
    const preferred = [
      'risk_calculator',
      'statistics',
      'psychology',
      'trading_plan',
      'trading_strategies',
    ];
    return preferred
        .map((id) => available.where((tool) => tool.id == id).firstOrNull)
        .whereType<ToolkitItem>()
        .take(4)
        .toList(growable: false);
  }

  List<String> _heroSummaryLabels(AppLocalizations l10n, int toolCount) {
    final labels = <String>[
      l10n.text('{count} tools available', {'count': toolCount}),
      l10n.text(
        _snapshot.dataMode == TradingDataDisplayMode.mt5Only
            ? 'Main MT5 account data'
            : 'Manual trading data',
      ),
    ];
    if (_snapshot.riskPercent != null) {
      labels.add(
        l10n.text('Risk setting: {risk}%', {
          'risk': _number(_snapshot.riskPercent!),
        }),
      );
    }
    if (_snapshot.dataMode == TradingDataDisplayMode.mt5Only &&
        widget.user.mainMt5AccountKey.trim().isEmpty) {
      labels.add(l10n.text('Main MT5 account setup required'));
    }
    return labels;
  }

  Widget _quickActionGrid(
    double width,
    List<ToolkitItem> tools,
    AppLocalizations l10n,
  ) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: tools.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: ToolkitLayout.quickActionColumns(width),
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        mainAxisExtent: ToolkitLayout.quickActionExtent(
          MediaQuery.textScalerOf(context),
        ),
      ),
      itemBuilder: (context, index) {
        final tool = tools[index];
        return ToolkitQuickAction(
          key: ValueKey('quick-${tool.id}'),
          label: l10n.text(tool.actionKey),
          supportingText: _quickSupportingText(tool, l10n),
          tooltip: l10n.text('Open {tool}', {'tool': l10n.text(tool.titleKey)}),
          icon: tool.icon,
          accent: _accentFor(tool),
          onTap: _openingTool ? null : () => _openTool(tool),
        );
      },
    );
  }

  String _quickSupportingText(ToolkitItem tool, AppLocalizations l10n) {
    return switch (tool.summaryKind) {
      ToolkitSummaryKind.risk when _snapshot.riskPercent != null => l10n.text(
        '{risk}% saved risk',
        {'risk': _number(_snapshot.riskPercent!)},
      ),
      ToolkitSummaryKind.psychology
          when _snapshot.psychologyCheckedToday &&
              _snapshot.psychologyScore != null =>
        l10n.text('{score}/100 readiness', {
          'score': _snapshot.psychologyScore!.toStringAsFixed(0),
        }),
      ToolkitSummaryKind.plan when _snapshot.hasPlan => _snapshot.planName,
      _ => l10n.text('Ready when you are'),
    };
  }

  Widget _categoryFilters(AppLocalizations l10n) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          ChoiceChip(
            label: Text(l10n.text('All')),
            selected: _category == null,
            onSelected: (_) => setState(() => _category = null),
          ),
          for (final category in ToolkitCategory.values) ...[
            const SizedBox(width: 8),
            ChoiceChip(
              label: Text(l10n.text(category.filterKey)),
              selected: _category == category,
              onSelected: (_) => setState(() => _category = category),
            ),
          ],
        ],
      ),
    );
  }

  Widget _categorySections({
    required Key key,
    required double width,
    required List<ToolkitItem> tools,
    required AppLocalizations l10n,
  }) {
    final categories = ToolkitCategory.values
        .where((category) => tools.any((tool) => tool.category == category))
        .toList(growable: false);
    return Column(
      key: key,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var index = 0; index < categories.length; index++) ...[
          if (index > 0) const SizedBox(height: 22),
          ToolkitSectionHeader(title: l10n.text(categories[index].titleKey)),
          const SizedBox(height: 10),
          _featureGrid(
            width,
            tools
                .where((tool) => tool.category == categories[index])
                .toList(growable: false),
            l10n,
          ),
        ],
      ],
    );
  }

  Widget _featureGrid(
    double width,
    List<ToolkitItem> tools,
    AppLocalizations l10n,
  ) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: tools.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: ToolkitLayout.featureColumns(width),
        crossAxisSpacing: 12,
        mainAxisSpacing: 12,
        mainAxisExtent: ToolkitLayout.featureExtent(
          MediaQuery.textScalerOf(context),
        ),
      ),
      itemBuilder: (context, index) {
        final tool = tools[index];
        final title = l10n.text(tool.titleKey);
        final description = l10n.text(tool.descriptionKey);
        return ToolkitFeatureTile(
          key: ValueKey('tool-${tool.id}'),
          title: title,
          description: description,
          category: l10n.text(tool.category.titleKey),
          actionLabel: l10n.text(tool.actionKey),
          semanticLabel: '$title. $description',
          icon: tool.icon,
          accent: _accentFor(tool),
          summary: _summaryFor(tool, l10n),
          onTap: _openingTool ? null : () => _openTool(tool),
        );
      },
    );
  }

  ToolkitFeatureSummary _summaryFor(ToolkitItem tool, AppLocalizations l10n) {
    switch (tool.summaryKind) {
      case ToolkitSummaryKind.averagePrice:
        return ToolkitFeatureSummary(
          label: l10n.text('Weighted position planning'),
          value: l10n.text('Average entry and break-even'),
          detail: l10n.text('Fees, partial targets and what-if orders'),
        );
      case ToolkitSummaryKind.risk:
        final risk = _snapshot.riskPercent;
        return ToolkitFeatureSummary(
          label: l10n.text('Latest risk setting'),
          value: risk == null
              ? l10n.text('No saved calculation')
              : l10n.text('{risk}% risk', {'risk': _number(risk)}),
          detail: risk == null
              ? l10n.text('Calculate position size before entry')
              : _snapshot.riskSymbol.isEmpty
              ? l10n.text('Saved locally')
              : _snapshot.riskSymbol,
          empty: risk == null,
        );
      case ToolkitSummaryKind.statistics:
        return ToolkitFeatureSummary(
          label: l10n.text('Selected data source'),
          value: l10n.text(_dataModeLabel),
          detail: l10n.text('Open analytics to view real performance'),
        );
      case ToolkitSummaryKind.rating:
        return ToolkitFeatureSummary(
          label: l10n.text('Daily and weekly ratings'),
          value: l10n.text('Explainable performance scores'),
          detail: l10n.text(
            'Result, discipline, psychology and plan compliance',
          ),
        );
      case ToolkitSummaryKind.propFirm:
        final missingMain =
            _snapshot.dataMode == TradingDataDisplayMode.mt5Only &&
            widget.user.mainMt5AccountKey.trim().isEmpty;
        return ToolkitFeatureSummary(
          label: l10n.text(missingMain ? 'Setup required' : 'Tracking source'),
          value: l10n.text(
            missingMain ? 'No selected main MT5 account' : _dataModeLabel,
          ),
          detail: l10n.text(
            missingMain
                ? 'Select a main MT5 account before tracking limits'
                : 'Open dashboard to view challenge progress',
          ),
          empty: missingMain,
        );
      case ToolkitSummaryKind.multiAccountPlanner:
        return ToolkitFeatureSummary(
          label: l10n.text('Local-first portfolio planner'),
          value: l10n.text('One recommended account at a time'),
          detail: l10n.text('Global stops, payout protection and recovery'),
        );
      case ToolkitSummaryKind.psychology:
        final completed =
            _snapshot.psychologyCheckedToday &&
            _snapshot.psychologyScore != null;
        return ToolkitFeatureSummary(
          label: l10n.text("Today's check-in"),
          value: completed
              ? l10n.text('{score}/100 · {status}', {
                  'score': _snapshot.psychologyScore!.toStringAsFixed(0),
                  'status': l10n.text(_snapshot.psychologyStatus),
                })
              : l10n.text('Not completed'),
          detail: l10n.text(
            completed
                ? 'Readiness saved for today'
                : 'Check your readiness before trading',
          ),
          progress: completed ? _snapshot.psychologyScore! / 100 : null,
          empty: !completed,
        );
      case ToolkitSummaryKind.plan:
        return ToolkitFeatureSummary(
          label: l10n.text('Active trading plan'),
          value: _snapshot.hasPlan
              ? _snapshot.planName
              : l10n.text('No active trading plan'),
          detail: _snapshot.hasPlan
              ? l10n.text('Risk {risk}% · Minimum RR {rr}', {
                  'risk': _number(_snapshot.planRiskPercent ?? .5),
                  'rr': _number(_snapshot.planMinimumRiskReward ?? 1),
                })
              : l10n.text('Create a rulebook for consistent execution'),
          empty: !_snapshot.hasPlan,
        );
      case ToolkitSummaryKind.strategy:
        return ToolkitFeatureSummary(
          label: l10n.text('Active trading strategy'),
          value: _snapshot.hasStrategy
              ? _snapshot.strategyName
              : l10n.text('No trading strategy configured'),
          detail: _snapshot.hasStrategy
              ? [
                  _snapshot.strategyStyle,
                  if (_snapshot.strategyRiskReward.isNotEmpty)
                    l10n.text('RR {rr}', {'rr': _snapshot.strategyRiskReward}),
                ].where((value) => value.isNotEmpty).join(' · ')
              : l10n.text('Define entry, exit and invalidation rules'),
          empty: !_snapshot.hasStrategy,
        );
    }
  }

  String get _dataModeLabel =>
      _snapshot.dataMode == TradingDataDisplayMode.mt5Only
      ? 'Main MT5 account data'
      : 'Manual trading data';

  List<_ToolkitRecommendation> _recommendations(List<ToolkitItem> available) {
    final byId = {for (final tool in available) tool.id: tool};
    final recommendations = <_ToolkitRecommendation>[];
    void add(String id, String messageKey) {
      final tool = byId[id];
      if (tool != null) {
        recommendations.add(
          _ToolkitRecommendation(tool: tool, messageKey: messageKey),
        );
      }
    }

    if (_snapshot.riskPercent == null) {
      add(
        'risk_calculator',
        'Set a consistent risk level before your next trade.',
      );
    }
    if (!_snapshot.psychologyCheckedToday) {
      add('psychology', 'Complete today’s readiness check before trading.');
    }
    if (!_snapshot.hasPlan) {
      add(
        'trading_plan',
        'Create a trading plan to organize risk and routines.',
      );
    }
    if (!_snapshot.hasStrategy) {
      add(
        'trading_strategies',
        'Define the setup, entry and exit rules you will execute.',
      );
    }
    return recommendations.take(3).toList(growable: false);
  }

  Widget _recommendationGrid(
    double width,
    List<_ToolkitRecommendation> recommendations,
    AppLocalizations l10n,
  ) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: recommendations.length,
      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: ToolkitLayout.recommendationColumns(width),
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        mainAxisExtent: MediaQuery.textScalerOf(context).scale(1) > 1.3
            ? 174
            : 142,
      ),
      itemBuilder: (context, index) {
        final recommendation = recommendations[index];
        final tool = recommendation.tool;
        return ToolkitRecommendationTile(
          title: l10n.text(tool.titleKey),
          message: l10n.text(recommendation.messageKey),
          actionLabel: l10n.text(tool.actionKey),
          icon: tool.icon,
          onTap: _openingTool ? null : () => _openTool(tool),
        );
      },
    );
  }

  Color _accentFor(ToolkitItem tool) => switch (tool.summaryKind) {
    ToolkitSummaryKind.averagePrice => const Color(0xFF087A63),
    ToolkitSummaryKind.risk => const Color(0xFF17A894),
    ToolkitSummaryKind.statistics => const Color(0xFF3979D6),
    ToolkitSummaryKind.rating => const Color(0xFF5167C9),
    ToolkitSummaryKind.propFirm => const Color(0xFFD19A2A),
    ToolkitSummaryKind.multiAccountPlanner => const Color(0xFF006C67),
    ToolkitSummaryKind.psychology => const Color(0xFF8A68D5),
    ToolkitSummaryKind.plan => const Color(0xFF2D9B65),
    ToolkitSummaryKind.strategy => const Color(0xFFB66B34),
  };

  Future<void> _openTool(ToolkitItem tool) async {
    if (_openingTool) return;
    setState(() => _openingTool = true);
    try {
      final updated = await _recentStore.record(widget.user.uid, tool.id);
      if (mounted) setState(() => _recent = updated);
    } catch (_) {
      // Recent-tool history is optional and must never block navigation.
    }
    if (!mounted) return;
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        settings: RouteSettings(
          name: tool.id == 'average_price'
              ? AveragePriceScreen.routeName
              : null,
        ),
        builder: (_) => _screenFor(tool),
      ),
    );
    if (!mounted) return;
    setState(() {
      _openingTool = false;
      _loadLocalState();
    });
  }

  Widget _screenFor(ToolkitItem tool) => switch (tool.id) {
    'average_price' => AveragePriceScreen(
      user: widget.user,
      storage: widget.storage,
    ),
    'risk_calculator' => RiskCalculatorScreen(
      user: widget.user,
      repository: widget.repository,
      storage: widget.storage,
    ),
    'statistics' => StatisticsScreen(
      user: widget.user,
      repository: widget.repository,
    ),
    'performance_ratings' => PerformanceRatingScreen(
      user: widget.user,
      repository: widget.repository,
    ),
    'prop_firm' => PropFirmScreen(
      user: widget.user,
      repository: widget.repository,
      storage: widget.storage,
    ),
    'multi_account_prop_firm' => PropFirmPlannerScreen(
      storage: widget.storage,
      ownerId: widget.user.uid,
      firebaseEnabled: widget.repository.firebaseEnabled,
    ),
    'psychology' => PsychologyScreen(
      user: widget.user,
      storage: widget.storage,
      repository: widget.repository,
    ),
    'personality_guidance' => PersonalityGuidanceScreen(
      user: widget.user,
      storage: widget.storage,
      repository: widget.repository,
    ),
    'trading_plan' => TradingPlanScreen(
      user: widget.user,
      repository: widget.repository,
      storage: widget.storage,
    ),
    'trading_strategies' => TradingStrategyScreen(
      user: widget.user,
      repository: widget.repository,
      storage: widget.storage,
    ),
    _ => throw StateError('Unsupported toolkit route: ${tool.id}'),
  };

  static String _number(double value) {
    if (value == value.roundToDouble()) return value.toStringAsFixed(0);
    return value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');
  }
}

class _ToolkitRecommendation {
  const _ToolkitRecommendation({required this.tool, required this.messageKey});

  final ToolkitItem tool;
  final String messageKey;
}
