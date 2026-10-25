import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart' show DateFormat, NumberFormat;

import '../../core/localization/app_localizations.dart';
import '../../core/security/admin_permissions.dart';
import '../../models/admin_dashboard_models.dart';
import '../../models/app_user.dart';
import '../../repositories/trading_repository.dart';

class ManageBackendDashboard extends StatefulWidget {
  const ManageBackendDashboard({
    super.key,
    required this.user,
    required this.repository,
    required this.onOpenTab,
  });

  final AppUser user;
  final TradingRepository repository;
  final ValueChanged<int> onOpenTab;

  @override
  State<ManageBackendDashboard> createState() => _ManageBackendDashboardState();
}

class _ManageBackendDashboardState extends State<ManageBackendDashboard> {
  var _range = AdminDashboardRange.last30Days;
  late Future<AdminDashboardSnapshot> _future;
  StreamSubscription<void>? _mutationSubscription;

  @override
  void initState() {
    super.initState();
    _load();
    _mutationSubscription = widget.repository.adminMutations.listen((_) {
      if (mounted) _refresh();
    });
  }

  @override
  void dispose() {
    _mutationSubscription?.cancel();
    super.dispose();
  }

  void _load() {
    _future = widget.repository.loadAdminDashboard(range: _range);
  }

  void _refresh() => setState(_load);

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<AdminDashboardSnapshot>(
      future: _future,
      builder: (context, snapshot) {
        return RefreshIndicator(
          onRefresh: () async {
            _refresh();
            await _future;
          },
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 36),
            children: [
              _DashboardHeader(
                range: _range,
                updatedAt: snapshot.data?.fetchedAt,
                loading: snapshot.connectionState == ConnectionState.waiting,
                onRangeChanged: (value) {
                  if (value == null || value == _range) return;
                  setState(() {
                    _range = value;
                    _load();
                  });
                },
                onRefresh: _refresh,
              ),
              const SizedBox(height: 18),
              if (snapshot.hasError)
                _DashboardError(onRetry: _refresh)
              else if (!snapshot.hasData)
                const SizedBox(
                  height: 360,
                  child: Center(child: CircularProgressIndicator()),
                )
              else
                _DashboardBody(
                  user: widget.user,
                  data: snapshot.data!,
                  onOpenTab: widget.onOpenTab,
                ),
            ],
          ),
        );
      },
    );
  }
}

class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader({
    required this.range,
    required this.updatedAt,
    required this.loading,
    required this.onRangeChanged,
    required this.onRefresh,
  });

  final AdminDashboardRange range;
  final DateTime? updatedAt;
  final bool loading;
  final ValueChanged<AdminDashboardRange?> onRangeChanged;
  final VoidCallback onRefresh;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      elevation: 0,
      color: scheme.surfaceContainerLow,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Wrap(
          spacing: 16,
          runSpacing: 16,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            SizedBox(
              width: 520,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.l10n.text('Manage Backend'),
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    context.l10n.text(
                      'Operational health, approvals, content, and account activity in one place.',
                    ),
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: scheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    updatedAt == null
                        ? context.l10n.text('Loading current data...')
                        : context.l10n.text('Last updated {time}', {
                            'time': DateFormat.yMMMd().add_jm().format(
                              updatedAt!,
                            ),
                          }),
                    style: Theme.of(context).textTheme.labelMedium,
                  ),
                ],
              ),
            ),
            DropdownButton<AdminDashboardRange>(
              value: range,
              onChanged: loading ? null : onRangeChanged,
              items: [
                for (final item in AdminDashboardRange.values)
                  DropdownMenuItem(
                    value: item,
                    child: Text(context.l10n.text(item.label)),
                  ),
              ],
            ),
            FilledButton.tonalIcon(
              onPressed: loading ? null : onRefresh,
              icon: loading
                  ? const SizedBox.square(
                      dimension: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.refresh),
              label: Text(context.l10n.text('Refresh')),
            ),
          ],
        ),
      ),
    );
  }
}

class _DashboardBody extends StatelessWidget {
  const _DashboardBody({
    required this.user,
    required this.data,
    required this.onOpenTab,
  });

  final AppUser user;
  final AdminDashboardSnapshot data;
  final ValueChanged<int> onOpenTab;

  @override
  Widget build(BuildContext context) {
    final canReviewUsers = user.hasPermission(AdminPermission.userView);
    final canReviewExam = user.hasPermission(AdminPermission.examResultReview);
    final visiblePending = data.pendingActions
        .where((item) {
          if (item.kind == 'quiz') return canReviewExam;
          return canReviewUsers;
        })
        .toList(growable: false);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _MetricStrip(metrics: data.metrics),
        const SizedBox(height: 18),
        LayoutBuilder(
          builder: (context, constraints) {
            final wide = constraints.maxWidth >= 920;
            final chart = _TrendPanel(points: data.trend);
            final pending = _PendingPanel(
              actions: visiblePending,
              onOpenTab: onOpenTab,
            );
            if (!wide) {
              return Column(
                children: [chart, const SizedBox(height: 16), pending],
              );
            }
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(flex: 7, child: chart),
                const SizedBox(width: 16),
                Expanded(flex: 4, child: pending),
              ],
            );
          },
        ),
        const SizedBox(height: 18),
        LayoutBuilder(
          builder: (context, constraints) {
            final activity = _ActivityTimeline(items: data.activities);
            final quickActions = _QuickActions(
              user: user,
              onOpenTab: onOpenTab,
            );
            if (constraints.maxWidth < 800) {
              return Column(
                children: [quickActions, const SizedBox(height: 16), activity],
              );
            }
            return Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(flex: 3, child: quickActions),
                const SizedBox(width: 16),
                Expanded(flex: 5, child: activity),
              ],
            );
          },
        ),
      ],
    );
  }
}

class _MetricStrip extends StatelessWidget {
  const _MetricStrip({required this.metrics});

  final List<AdminDashboardMetric> metrics;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 1050
            ? metrics.length
            : constraints.maxWidth >= 620
            ? 3
            : 2;
        final width = (constraints.maxWidth - (columns - 1) * 10) / columns;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final metric in metrics)
              SizedBox(
                width: width,
                child: _MetricCard(metric: metric),
              ),
          ],
        );
      },
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.metric});

  final AdminDashboardMetric metric;

  @override
  Widget build(BuildContext context) {
    final change = metric.changePercentage;
    final positive = (change ?? 0) >= 0;
    final scheme = Theme.of(context).colorScheme;
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text(metric.label),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: scheme.onSurfaceVariant,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              NumberFormat.compact().format(metric.value),
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            if (change != null) ...[
              const SizedBox(height: 4),
              Row(
                children: [
                  Icon(
                    positive ? Icons.trending_up : Icons.trending_down,
                    size: 15,
                    color: positive ? Colors.teal : scheme.error,
                  ),
                  const SizedBox(width: 3),
                  Text(
                    '${change.abs().toStringAsFixed(0)}%',
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: positive ? Colors.teal : scheme.error,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _TrendPanel extends StatelessWidget {
  const _TrendPanel({required this.points});

  final List<AdminDashboardTrendPoint> points;

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
              context.l10n.text('Platform activity'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              context.l10n.text(
                'Registrations, published signals, and market outlooks',
              ),
            ),
            const SizedBox(height: 14),
            const Wrap(
              spacing: 14,
              runSpacing: 6,
              children: [
                _Legend(color: Colors.teal, label: 'Users'),
                _Legend(color: Colors.amber, label: 'Signals'),
                _Legend(color: Colors.blue, label: 'Analyses'),
              ],
            ),
            const SizedBox(height: 12),
            SizedBox(
              height: 230,
              width: double.infinity,
              child: points.isEmpty
                  ? Center(child: Text(context.l10n.text('No activity yet')))
                  : CustomPaint(
                      painter: _TrendPainter(
                        points: points,
                        gridColor: Theme.of(context).dividerColor,
                        labelColor: Theme.of(
                          context,
                        ).colorScheme.onSurfaceVariant,
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  const _Legend({required this.color, required this.label});

  final Color color;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Container(
        width: 9,
        height: 9,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
      const SizedBox(width: 5),
      Text(context.l10n.text(label)),
    ],
  );
}

class _TrendPainter extends CustomPainter {
  _TrendPainter({
    required this.points,
    required this.gridColor,
    required this.labelColor,
  });

  final List<AdminDashboardTrendPoint> points;
  final Color gridColor;
  final Color labelColor;

  @override
  void paint(Canvas canvas, Size size) {
    const left = 30.0;
    const bottom = 26.0;
    const top = 8.0;
    final plot = Rect.fromLTRB(left, top, size.width - 6, size.height - bottom);
    final maxValue = math.max(
      1,
      points.fold<int>(
        0,
        (value, point) => math.max(
          value,
          math.max(point.users, math.max(point.signals, point.analyses)),
        ),
      ),
    );
    final gridPaint = Paint()
      ..color = gridColor.withValues(alpha: .55)
      ..strokeWidth = 1;
    for (var row = 0; row <= 4; row++) {
      final y = plot.top + (plot.height * row / 4);
      canvas.drawLine(Offset(plot.left, y), Offset(plot.right, y), gridPaint);
    }
    _drawSeries(
      canvas,
      plot,
      points.map((item) => item.users).toList(),
      maxValue,
      Colors.teal,
    );
    _drawSeries(
      canvas,
      plot,
      points.map((item) => item.signals).toList(),
      maxValue,
      Colors.amber,
    );
    _drawSeries(
      canvas,
      plot,
      points.map((item) => item.analyses).toList(),
      maxValue,
      Colors.blue,
    );
    final textStyle = TextStyle(color: labelColor, fontSize: 10);
    for (final index in <int>{0, points.length ~/ 2, points.length - 1}) {
      final x = _x(plot, index, points.length);
      final painter = TextPainter(
        text: TextSpan(
          text: DateFormat.MMMd().format(points[index].date),
          style: textStyle,
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      painter.paint(
        canvas,
        Offset(
          (x - painter.width / 2).clamp(0, size.width - painter.width),
          size.height - painter.height,
        ),
      );
    }
  }

  void _drawSeries(
    Canvas canvas,
    Rect plot,
    List<int> values,
    int maxValue,
    Color color,
  ) {
    final path = Path();
    for (var index = 0; index < values.length; index++) {
      final point = Offset(
        _x(plot, index, values.length),
        plot.bottom - (plot.height * values[index] / maxValue),
      );
      if (index == 0) {
        path.moveTo(point.dx, point.dy);
      } else {
        path.lineTo(point.dx, point.dy);
      }
    }
    canvas.drawPath(
      path,
      Paint()
        ..color = color
        ..strokeWidth = 2.4
        ..strokeCap = StrokeCap.round
        ..style = PaintingStyle.stroke,
    );
  }

  double _x(Rect plot, int index, int length) => length <= 1
      ? plot.center.dx
      : plot.left + (plot.width * index / (length - 1));

  @override
  bool shouldRepaint(covariant _TrendPainter oldDelegate) =>
      oldDelegate.points != points ||
      oldDelegate.gridColor != gridColor ||
      oldDelegate.labelColor != labelColor;
}

class _PendingPanel extends StatelessWidget {
  const _PendingPanel({required this.actions, required this.onOpenTab});

  final List<AdminPendingAction> actions;
  final ValueChanged<int> onOpenTab;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Pending actions'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                Badge(label: Text('${actions.length}')),
              ],
            ),
            const SizedBox(height: 10),
            if (actions.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 38),
                child: Center(
                  child: Column(
                    children: [
                      const Icon(Icons.task_alt, size: 34),
                      const SizedBox(height: 8),
                      Text(context.l10n.text('You are all caught up')),
                    ],
                  ),
                ),
              )
            else
              for (final action in actions.take(7))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: CircleAvatar(child: Icon(_pendingIcon(action.kind))),
                  title: Text(
                    context.l10n.text(action.title),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  subtitle: Text(
                    action.subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => onOpenTab(action.targetTab),
                ),
          ],
        ),
      ),
    );
  }

  IconData _pendingIcon(String kind) => switch (kind) {
    'access' => Icons.verified_user_outlined,
    'position' => Icons.badge_outlined,
    'delete' => Icons.delete_outline,
    'quiz' => Icons.fact_check_outlined,
    _ => Icons.pending_actions,
  };
}

class _QuickActions extends StatelessWidget {
  const _QuickActions({required this.user, required this.onOpenTab});

  final AppUser user;
  final ValueChanged<int> onOpenTab;

  @override
  Widget build(BuildContext context) {
    final actions = <(IconData, String, int, AdminPermission)>[
      (Icons.person_add_alt_1, 'Manage users', 2, AdminPermission.userManage),
      (
        Icons.verified_user_outlined,
        'Review access',
        5,
        AdminPermission.userView,
      ),
      (
        Icons.video_library_outlined,
        'Manage content',
        7,
        AdminPermission.examManage,
      ),
      (
        Icons.tune_outlined,
        'Feature settings',
        12,
        AdminPermission.featureManage,
      ),
      (Icons.history_outlined, 'View audit log', 13, AdminPermission.auditView),
    ].where((item) => user.hasPermission(item.$4)).toList();
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.l10n.text('Quick actions'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            for (final action in actions)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: OutlinedButton.icon(
                  onPressed: () => onOpenTab(action.$3),
                  icon: Icon(action.$1),
                  label: Align(
                    alignment: Alignment.centerLeft,
                    child: Text(context.l10n.text(action.$2)),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _ActivityTimeline extends StatelessWidget {
  const _ActivityTimeline({required this.items});

  final List<AdminActivityItem> items;

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
              context.l10n.text('Recent activity'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            if (items.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 30),
                child: Center(
                  child: Text(context.l10n.text('No recent activity')),
                ),
              )
            else
              for (final item in items.take(10))
                _ActivityRow(item: item, isLast: item == items.take(10).last),
          ],
        ),
      ),
    );
  }
}

class _ActivityRow extends StatelessWidget {
  const _ActivityRow({required this.item, required this.isLast});

  final AdminActivityItem item;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 10,
                height: 10,
                decoration: BoxDecoration(
                  color: scheme.primary,
                  shape: BoxShape.circle,
                ),
              ),
              if (!isLast)
                Expanded(
                  child: Container(
                    width: 1,
                    color: Theme.of(context).dividerColor,
                  ),
                ),
            ],
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${item.actor} · ${context.l10n.text(item.action)}',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    [
                      item.module,
                      item.target,
                    ].where((value) => value.isNotEmpty).join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  if (item.createdAt != null)
                    Text(
                      DateFormat.yMMMd().add_jm().format(item.createdAt!),
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: scheme.onSurfaceVariant,
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DashboardError extends StatelessWidget {
  const _DashboardError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Card(
    elevation: 0,
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          Icon(
            Icons.cloud_off_outlined,
            size: 42,
            color: Theme.of(context).colorScheme.error,
          ),
          const SizedBox(height: 10),
          Text(context.l10n.text('Unable to load the backend dashboard.')),
          const SizedBox(height: 4),
          Text(
            context.l10n.text(
              'The dashboard is temporarily unavailable. Check your connection and try again.',
            ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: Text(context.l10n.text('Try again')),
          ),
        ],
      ),
    ),
  );
}
