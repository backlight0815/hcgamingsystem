import 'dart:async';

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/localization/app_localizations.dart';
import '../../../core/security/admin_permissions.dart';
import '../../../models/app_user.dart';
import '../../../repositories/trading_repository.dart';
import '../../../shared/analytics/analytics_widgets.dart';
import '../../../shared/app_feedback.dart';
import '../../../shared/formatters.dart';
import 'admin_performance_controller.dart';
import 'admin_performance_detail_screen.dart';
import 'admin_performance_export_service.dart';
import 'admin_performance_models.dart';
import 'admin_performance_review_dialog.dart';

part 'admin_performance_dashboard_widgets.dart';
part 'admin_performance_chart_widgets.dart';
part 'admin_performance_filter_sheet.dart';
part 'admin_performance_support_widgets.dart';
part 'admin_performance_table_widgets.dart';

const _analyticsNeutral = Color(0xFF667085);

class AdminPerformanceTab extends StatefulWidget {
  const AdminPerformanceTab({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<AdminPerformanceTab> createState() => _AdminPerformanceTabState();
}

class _AdminPerformanceTabState extends State<AdminPerformanceTab> {
  late final AdminPerformanceController _controller;
  final _search = TextEditingController();
  final _export = const AdminPerformanceExportService();

  @override
  void initState() {
    super.initState();
    _controller = AdminPerformanceController(repository: widget.repository);
    if (widget.user.hasPermission(AdminPermission.performanceManage)) {
      unawaited(_controller.load());
    }
  }

  @override
  void dispose() {
    _search.dispose();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.user.hasPermission(AdminPermission.performanceManage)) {
      return const _PerformanceAccessDenied();
    }
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) => LayoutBuilder(
        builder: (context, constraints) {
          final compact = constraints.maxWidth < 700;
          return ColoredBox(
            color: Theme.of(context).colorScheme.surfaceContainerLowest,
            child: RefreshIndicator(
              onRefresh: () => _controller.load(refresh: true),
              child: CustomScrollView(
                key: const Key('admin-performance-dashboard'),
                slivers: [
                  SliverToBoxAdapter(
                    child: Center(
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 1680),
                        child: Padding(
                          padding: EdgeInsets.fromLTRB(
                            compact ? 12 : 24,
                            18,
                            compact ? 12 : 24,
                            8,
                          ),
                          child: _PerformanceHeader(
                            controller: _controller,
                            compact: compact,
                            showQuickFilters: constraints.maxWidth >= 1200,
                            onExport: _exportRows,
                            onFilters: () => _openFilters(compact),
                          ),
                        ),
                      ),
                    ),
                  ),
                  if (_controller.refreshing)
                    const SliverToBoxAdapter(child: LinearProgressIndicator()),
                  SliverToBoxAdapter(
                    child: Center(
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 1680),
                        child: Padding(
                          padding: EdgeInsets.fromLTRB(
                            compact ? 12 : 24,
                            8,
                            compact ? 12 : 24,
                            32,
                          ),
                          child: _body(compact),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _body(bool compact) {
    if (_controller.loading) return const _PerformanceSkeleton();
    if (_controller.error != null && _controller.allRecords.isEmpty) {
      return _PerformanceErrorState(
        error: _controller.error,
        onRetry: _controller.load,
      );
    }
    if (_controller.allRecords.isEmpty) {
      return _PerformanceEmptyState(
        title: 'No trader performance data yet.',
        message:
            'Completed trades will appear after journals or MT5 records are available.',
        onAction: _controller.load,
      );
    }
    final records = _controller.filteredRecords;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _PerformanceSearchBar(
          controller: _search,
          model: _controller,
          compact: compact,
          onFilters: () => _openFilters(compact),
        ),
        const SizedBox(height: 16),
        _KpiGrid(summary: _controller.summary),
        const SizedBox(height: 16),
        _InsightsPanel(insights: _controller.insights),
        const SizedBox(height: 16),
        _AnalyticsGrid(records: records),
        const SizedBox(height: 16),
        _TopPerformers(records: records),
        const SizedBox(height: 16),
        if (records.isEmpty)
          _PerformanceEmptyState(
            title: 'No matching performance records',
            message: 'Adjust or clear the active filters to see more traders.',
            onAction: _controller.clearFilters,
            actionLabel: 'Clear all',
          )
        else if (compact)
          _MobilePerformanceList(
            controller: _controller,
            onOpen: _openDetail,
            onReview: _review,
            canReview: widget.user.hasPermission(
              AdminPermission.propFirmResultReview,
            ),
          )
        else
          _DesktopPerformanceTable(
            controller: _controller,
            onOpen: _openDetail,
            onReview: _review,
            onCompare: _openComparison,
            canReview: widget.user.hasPermission(
              AdminPermission.propFirmResultReview,
            ),
          ),
      ],
    );
  }

  Future<void> _openFilters(bool compact) async {
    final value = await showModalBottomSheet<AdminPerformanceFilter>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      constraints: compact
          ? null
          : const BoxConstraints(maxWidth: 620, maxHeight: 760),
      builder: (context) =>
          _PerformanceFilterSheet(initial: _controller.filter),
    );
    if (value != null) await _controller.setRemoteFilter(value);
  }

  Future<void> _exportRows() async {
    final records = _controller.selectedRecords.isNotEmpty
        ? _controller.selectedRecords
        : _controller.filteredRecords;
    if (records.isEmpty) return;
    _controller.setExporting(true);
    try {
      final saved = await _export.exportExcel(
        records: records,
        labels: {
          'sheet': context.l10n.text('Performances'),
          'save': context.l10n.text('Export performance report'),
          'rank': context.l10n.text('Rank'),
          'trader': context.l10n.text('Trader'),
          'email': context.l10n.text('Email'),
          'status': context.l10n.text('Status'),
          'sources': context.l10n.text('Data source'),
          'netProfit': context.l10n.text('Net profit'),
          'return': context.l10n.text('Return'),
          'winRate': context.l10n.text('Win rate'),
          'trades': context.l10n.text('Trades'),
          'profitFactor': context.l10n.text('Profit factor'),
          'drawdown': context.l10n.text('Maximum drawdown'),
          'score': context.l10n.text('Performance score'),
          'review': context.l10n.text('Review status'),
          'lastTrade': context.l10n.text('Last trade'),
        },
      );
      if (saved && mounted) {
        AppFeedback.success(context, 'Performance report exported.');
      }
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, 'Export failed. Please try again.');
      }
    } finally {
      _controller.setExporting(false);
    }
  }

  Future<void> _review(AdminTraderPerformance record) async {
    final changed = await showAdminPerformanceReviewDialog(
      context,
      repository: widget.repository,
      record: record,
    );
    if (changed) await _controller.load(refresh: true);
  }

  void _openDetail(AdminTraderPerformance record) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => AdminPerformanceDetailScreen(
          user: widget.user,
          repository: widget.repository,
          record: record,
          onReviewChanged: () => _controller.load(refresh: true),
        ),
      ),
    );
  }

  void _openComparison() {
    final selected = _controller.selectedRecords;
    if (selected.length < 2) {
      AppFeedback.error(context, 'Select between 2 and 5 traders to compare.');
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => AdminPerformanceComparisonScreen(
          records: selected.take(5).toList(),
          period: _controller.filter.period,
          dataSource: _controller.filter.dataSource,
        ),
      ),
    );
  }
}
