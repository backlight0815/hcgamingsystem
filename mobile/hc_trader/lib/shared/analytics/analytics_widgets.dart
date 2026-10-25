import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';

const analyticsPositive = Color(0xFF087A63);
const analyticsNegative = Color(0xFFB9384F);
const analyticsWarning = Color(0xFFC47A11);
const analyticsInformation = Color(0xFF3568A8);

class AnalyticsPageHeader extends StatelessWidget {
  const AnalyticsPageHeader({
    super.key,
    required this.title,
    required this.subtitle,
    this.eyebrow,
    this.leading,
    this.actions = const [],
  });

  final String title;
  final String subtitle;
  final String? eyebrow;
  final Widget? leading;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget copy() => Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (eyebrow != null) ...[
          Text(
            eyebrow!,
            style: theme.textTheme.labelLarge?.copyWith(
              color: theme.colorScheme.primary,
              fontWeight: FontWeight.w800,
              letterSpacing: .2,
            ),
          ),
          const SizedBox(height: 3),
        ],
        Text(
          title,
          style: theme.textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.w900,
            letterSpacing: -.4,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          subtitle,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 600) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (leading != null) ...[leading!, const SizedBox(width: 12)],
                  Expanded(child: copy()),
                ],
              ),
              if (actions.isNotEmpty) ...[
                const SizedBox(height: 10),
                Wrap(spacing: 6, runSpacing: 6, children: actions),
              ],
            ],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (leading != null) ...[leading!, const SizedBox(width: 12)],
            Expanded(child: copy()),
            if (actions.isNotEmpty) ...[
              const SizedBox(width: 8),
              Flexible(
                child: Wrap(
                  alignment: WrapAlignment.end,
                  spacing: 4,
                  runSpacing: 4,
                  children: actions,
                ),
              ),
            ],
          ],
        );
      },
    );
  }
}

class AnalyticsSurface extends StatelessWidget {
  const AnalyticsSurface({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.color,
    this.borderRadius = 22,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color? color;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: color ?? scheme.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(borderRadius),
        side: BorderSide(color: scheme.outlineVariant.withValues(alpha: .55)),
      ),
      child: Padding(padding: padding, child: child),
    );
  }
}

class AnalyticsSectionHeader extends StatelessWidget {
  const AnalyticsSectionHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.icon,
    this.action,
  });

  final String title;
  final String? subtitle;
  final IconData? icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    Widget copy() => Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w900,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 2),
          Text(
            subtitle!,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ],
    );
    Widget heading() => Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (icon != null) ...[
          Icon(icon, size: 22, color: theme.colorScheme.primary),
          const SizedBox(width: 9),
        ],
        Expanded(child: copy()),
      ],
    );
    return LayoutBuilder(
      builder: (context, constraints) {
        if (action != null && constraints.maxWidth < 520) {
          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              heading(),
              const SizedBox(height: 10),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: action!,
              ),
            ],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 22, color: theme.colorScheme.primary),
              const SizedBox(width: 9),
            ],
            Expanded(child: copy()),
            if (action != null) ...[const SizedBox(width: 8), action!],
          ],
        );
      },
    );
  }
}

class AnalyticsHeroMetric {
  const AnalyticsHeroMetric({
    required this.label,
    required this.value,
    this.icon,
    this.tone,
  });

  final String label;
  final String value;
  final IconData? icon;
  final Color? tone;
}

class PerformanceHero extends StatelessWidget {
  const PerformanceHero({
    super.key,
    required this.label,
    required this.value,
    required this.caption,
    this.statusLabel,
    this.statusIcon,
    this.tone,
    this.metrics = const [],
    this.action,
  });

  final String label;
  final String value;
  final String caption;
  final String? statusLabel;
  final IconData? statusIcon;
  final Color? tone;
  final List<AnalyticsHeroMetric> metrics;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    final accent = tone ?? scheme.primary;
    return Semantics(
      container: true,
      label: '$label, $value. $caption',
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Color.alphaBlend(
            accent.withValues(
              alpha: theme.brightness == Brightness.dark ? .16 : .09,
            ),
            scheme.surfaceContainerLow,
          ),
          borderRadius: BorderRadius.circular(26),
          border: Border.all(color: accent.withValues(alpha: .24)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    label,
                    style: theme.textTheme.labelLarge?.copyWith(
                      color: scheme.onSurfaceVariant,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (statusLabel != null)
                  _HeroStatus(
                    label: statusLabel!,
                    icon: statusIcon ?? Icons.info_outline,
                    tone: accent,
                  ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.displaySmall?.copyWith(
                fontWeight: FontWeight.w900,
                letterSpacing: -1,
                color: accent,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              caption,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: scheme.onSurfaceVariant,
              ),
            ),
            if (metrics.isNotEmpty) ...[
              const SizedBox(height: 18),
              LayoutBuilder(
                builder: (context, constraints) {
                  final narrow = constraints.maxWidth < 520;
                  return Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      for (final metric in metrics)
                        SizedBox(
                          width: narrow
                              ? math.max(128, (constraints.maxWidth - 10) / 2)
                              : math.max(132, (constraints.maxWidth - 30) / 4),
                          child: _HeroMetric(metric: metric),
                        ),
                    ],
                  );
                },
              ),
            ],
            if (action != null) ...[
              const SizedBox(height: 14),
              Align(alignment: Alignment.centerRight, child: action!),
            ],
          ],
        ),
      ),
    );
  }
}

class _HeroStatus extends StatelessWidget {
  const _HeroStatus({
    required this.label,
    required this.icon,
    required this.tone,
  });

  final String label;
  final IconData icon;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: tone),
          const SizedBox(width: 6),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: tone,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroMetric extends StatelessWidget {
  const _HeroMetric({required this.metric});

  final AnalyticsHeroMetric metric;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            if (metric.icon != null) ...[
              Icon(
                metric.icon,
                size: 15,
                color: metric.tone ?? theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(width: 5),
            ],
            Expanded(
              child: Text(
                metric.label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 3),
        Text(
          metric.value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.titleMedium?.copyWith(
            color: metric.tone,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

class AnalyticsMetricData {
  const AnalyticsMetricData({
    required this.label,
    required this.value,
    required this.icon,
    this.detail,
    this.tone,
    this.comparison,
  });

  final String label;
  final String value;
  final IconData icon;
  final String? detail;
  final Color? tone;
  final String? comparison;
}

class TradingMetricStrip extends StatelessWidget {
  const TradingMetricStrip({super.key, required this.metrics});

  final List<AnalyticsMetricData> metrics;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 620) {
          return SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.only(bottom: 2),
            child: Row(
              children: [
                for (final metric in metrics) ...[
                  SizedBox(
                    width: 158,
                    child: TradingMetricTile(metric: metric),
                  ),
                  if (metric != metrics.last) const SizedBox(width: 10),
                ],
              ],
            ),
          );
        }
        final columns = constraints.maxWidth >= 1040 ? 4 : 3;
        final width = (constraints.maxWidth - ((columns - 1) * 10)) / columns;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final metric in metrics)
              SizedBox(
                width: width,
                child: TradingMetricTile(metric: metric),
              ),
          ],
        );
      },
    );
  }
}

class TradingMetricTile extends StatelessWidget {
  const TradingMetricTile({super.key, required this.metric});

  final AnalyticsMetricData metric;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final tone = metric.tone ?? theme.colorScheme.primary;
    return Semantics(
      container: true,
      label:
          '${metric.label}, ${metric.value}${metric.detail == null ? '' : ', ${metric.detail}'}',
      child: Container(
        constraints: const BoxConstraints(minHeight: 116),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: theme.colorScheme.surfaceContainerLow,
          borderRadius: BorderRadius.circular(18),
          border: Border(
            top: BorderSide(color: tone.withValues(alpha: .75), width: 3),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(metric.icon, color: tone, size: 19),
                const SizedBox(width: 7),
                Expanded(
                  child: Text(
                    metric.label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 11),
            Text(
              metric.value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.titleLarge?.copyWith(
                color: tone,
                fontWeight: FontWeight.w900,
                letterSpacing: -.25,
              ),
            ),
            if (metric.detail != null || metric.comparison != null) ...[
              const SizedBox(height: 5),
              Text(
                metric.comparison ?? metric.detail!,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class InsightBanner extends StatelessWidget {
  const InsightBanner({
    super.key,
    required this.title,
    required this.message,
    this.icon = Icons.lightbulb_outline,
    this.tone = analyticsInformation,
    this.action,
  });

  final String title;
  final String message;
  final IconData icon;
  final Color tone;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: tone.withValues(
          alpha: theme.brightness == Brightness.dark ? .15 : .08,
        ),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: tone),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.titleSmall?.copyWith(
                    color: tone,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(message, style: theme.textTheme.bodyMedium),
              ],
            ),
          ),
          if (action != null) ...[const SizedBox(width: 8), action!],
        ],
      ),
    );
  }
}

class PerformanceScoreRing extends StatelessWidget {
  const PerformanceScoreRing({
    super.key,
    required this.score,
    required this.label,
    this.size = 112,
    this.tone,
  });

  final double score;
  final String label;
  final double size;
  final Color? tone;

  @override
  Widget build(BuildContext context) {
    final color = tone ?? _scoreTone(score);
    return Semantics(
      label:
          '$label, ${score.toStringAsFixed(0)} ${context.l10n.text('out of 100')}',
      child: SizedBox.square(
        dimension: size,
        child: CustomPaint(
          painter: _ScoreRingPainter(
            progress: (score / 100).clamp(0, 1),
            color: color,
            trackColor: Theme.of(context).colorScheme.surfaceContainerHighest,
          ),
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  score.toStringAsFixed(0),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: color,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Color _scoreTone(double value) {
    if (value >= 75) return analyticsPositive;
    if (value >= 45) return analyticsWarning;
    return analyticsNegative;
  }
}

class _ScoreRingPainter extends CustomPainter {
  const _ScoreRingPainter({
    required this.progress,
    required this.color,
    required this.trackColor,
  });

  final double progress;
  final Color color;
  final Color trackColor;

  @override
  void paint(Canvas canvas, Size size) {
    final stroke = math.max(7.0, size.shortestSide * .075);
    final rect = Offset.zero & size;
    final arcRect = rect.deflate(stroke / 2);
    final track = Paint()
      ..color = trackColor
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke;
    final value = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(arcRect, -math.pi / 2, math.pi * 2, false, track);
    canvas.drawArc(arcRect, -math.pi / 2, math.pi * 2 * progress, false, value);
  }

  @override
  bool shouldRepaint(covariant _ScoreRingPainter oldDelegate) =>
      progress != oldDelegate.progress ||
      color != oldDelegate.color ||
      trackColor != oldDelegate.trackColor;
}

class AnalyticsProgressRow extends StatelessWidget {
  const AnalyticsProgressRow({
    super.key,
    required this.label,
    required this.valueLabel,
    required this.progress,
    this.tone,
    this.caption,
  });

  final String label;
  final String valueLabel;
  final double progress;
  final Color? tone;
  final String? caption;

  @override
  Widget build(BuildContext context) {
    final color = tone ?? Theme.of(context).colorScheme.primary;
    return Semantics(
      label: '$label, $valueLabel',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  style: Theme.of(
                    context,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(width: 10),
              Text(
                valueLabel,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: color,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          const SizedBox(height: 7),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: progress.clamp(0, 1),
              minHeight: 8,
              color: color,
              backgroundColor: color.withValues(alpha: .12),
            ),
          ),
          if (caption != null) ...[
            const SizedBox(height: 5),
            Text(
              caption!,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class AnalyticsEmptyState extends StatelessWidget {
  const AnalyticsEmptyState({
    super.key,
    required this.title,
    required this.message,
    this.icon = Icons.query_stats_outlined,
    this.action,
  });

  final String title;
  final String message;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return AnalyticsSurface(
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 440),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircleAvatar(
                  radius: 27,
                  backgroundColor: scheme.primaryContainer,
                  child: Icon(icon, color: scheme.onPrimaryContainer),
                ),
                const SizedBox(height: 13),
                Text(
                  title,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  message,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: scheme.onSurfaceVariant,
                  ),
                ),
                if (action != null) ...[const SizedBox(height: 14), action!],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class AnalyticsSkeleton extends StatelessWidget {
  const AnalyticsSkeleton({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.surfaceContainerHighest;
    Widget block(double height, {double? width}) => Container(
      height: height,
      width: width,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(16),
      ),
    );
    return Semantics(
      label: context.l10n.text('Loading analytics'),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            block(24, width: 190),
            const SizedBox(height: 10),
            block(14, width: 280),
            const SizedBox(height: 18),
            block(compact ? 130 : 190),
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(child: block(112)),
                const SizedBox(width: 10),
                Expanded(child: block(112)),
              ],
            ),
            if (!compact) ...[const SizedBox(height: 12), block(240)],
          ],
        ),
      ),
    );
  }
}
