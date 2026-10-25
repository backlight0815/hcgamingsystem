import 'package:flutter/material.dart';

abstract final class ToolkitLayout {
  static const double tablet = 700;
  static const double desktop = 1100;
  static const double maxContentWidth = 1280;

  static int quickActionColumns(double width) {
    if (width >= desktop) return 4;
    if (width >= tablet) return 4;
    return 2;
  }

  static int featureColumns(double width) {
    if (width >= desktop) return 3;
    if (width >= tablet) return 2;
    return 1;
  }

  static int recommendationColumns(double width) => width >= tablet ? 2 : 1;

  static double quickActionExtent(TextScaler scaler) =>
      scaler.scale(1) > 1.3 ? 160 : 134;

  static double featureExtent(TextScaler scaler) =>
      scaler.scale(1) > 1.3 ? 284 : 232;

  static EdgeInsets pagePadding(double width) => EdgeInsets.fromLTRB(
    width >= tablet ? 24 : 16,
    14,
    width >= tablet ? 24 : 16,
    32,
  );
}

class ToolkitHero extends StatelessWidget {
  const ToolkitHero({
    super.key,
    required this.title,
    required this.description,
    required this.searchHint,
    required this.searchTooltip,
    required this.controller,
    required this.onSearchChanged,
    required this.summaryLabels,
  });

  final String title;
  final String description;
  final String searchHint;
  final String searchTooltip;
  final TextEditingController controller;
  final ValueChanged<String> onSearchChanged;
  final List<String> summaryLabels;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return LayoutBuilder(
      builder: (context, constraints) {
        final horizontal = constraints.maxWidth >= ToolkitLayout.tablet;
        final introduction = Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: colors.onPrimaryContainer.withValues(alpha: .1),
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(
                    Icons.candlestick_chart_outlined,
                    color: colors.onPrimaryContainer,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    title,
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                      color: colors.onPrimaryContainer,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              description,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: colors.onPrimaryContainer.withValues(alpha: .82),
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final label in summaryLabels)
                  ToolkitStatusBadge(
                    label: label,
                    foregroundColor: colors.onPrimaryContainer,
                    backgroundColor: colors.onPrimaryContainer.withValues(
                      alpha: .09,
                    ),
                  ),
              ],
            ),
          ],
        );
        final search = Semantics(
          textField: true,
          label: searchHint,
          child: SearchBar(
            controller: controller,
            hintText: searchHint,
            onChanged: onSearchChanged,
            leading: Tooltip(
              message: searchTooltip,
              child: const Icon(Icons.search),
            ),
            trailing: [
              if (controller.text.isNotEmpty)
                IconButton(
                  tooltip: MaterialLocalizations.of(
                    context,
                  ).deleteButtonTooltip,
                  onPressed: () {
                    controller.clear();
                    onSearchChanged('');
                  },
                  icon: const Icon(Icons.close),
                ),
            ],
          ),
        );

        return Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                colors.primaryContainer,
                Color.lerp(colors.primaryContainer, colors.surface, .36)!,
              ],
            ),
            borderRadius: BorderRadius.circular(24),
            border: Border.all(color: colors.primary.withValues(alpha: .18)),
          ),
          child: horizontal
              ? Row(
                  children: [
                    Expanded(flex: 3, child: introduction),
                    const SizedBox(width: 24),
                    Expanded(flex: 2, child: search),
                  ],
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [introduction, const SizedBox(height: 16), search],
                ),
        );
      },
    );
  }
}

class ToolkitSectionHeader extends StatelessWidget {
  const ToolkitSectionHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.end,
    children: [
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            if (subtitle != null) ...[
              const SizedBox(height: 2),
              Text(
                subtitle!,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ],
        ),
      ),
      ?trailing,
    ],
  );
}

class ToolkitQuickAction extends StatelessWidget {
  const ToolkitQuickAction({
    super.key,
    required this.label,
    required this.supportingText,
    required this.tooltip,
    required this.icon,
    required this.accent,
    required this.onTap,
  });

  final String label;
  final String supportingText;
  final String tooltip;
  final IconData icon;
  final Color accent;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Tooltip(
    message: tooltip,
    child: Semantics(
      button: true,
      enabled: onTap != null,
      label: '$label. $supportingText',
      child: _ToolkitInteractiveSurface(
        onTap: onTap,
        borderRadius: 18,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, size: 21, color: accent),
              ),
              const Spacer(),
              Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              Text(
                supportingText,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class ToolkitFeatureSummary {
  const ToolkitFeatureSummary({
    required this.label,
    required this.value,
    this.detail,
    this.progress,
    this.empty = false,
  });

  final String label;
  final String value;
  final String? detail;
  final double? progress;
  final bool empty;
}

class ToolkitFeatureTile extends StatelessWidget {
  const ToolkitFeatureTile({
    super.key,
    required this.title,
    required this.description,
    required this.category,
    required this.actionLabel,
    required this.semanticLabel,
    required this.icon,
    required this.accent,
    required this.summary,
    required this.onTap,
  });

  final String title;
  final String description;
  final String category;
  final String actionLabel;
  final String semanticLabel;
  final IconData icon;
  final Color accent;
  final ToolkitFeatureSummary summary;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    enabled: onTap != null,
    label: semanticLabel,
    child: _ToolkitInteractiveSurface(
      onTap: onTap,
      borderRadius: 20,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: accent.withValues(alpha: .14),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(icon, color: accent),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      Text(
                        category,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: accent,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              description,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 10),
            Expanded(
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: summary.empty
                      ? Theme.of(context).colorScheme.surfaceContainerHighest
                            .withValues(alpha: .45)
                      : accent.withValues(alpha: .08),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      summary.label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelSmall,
                    ),
                    Text(
                      summary.value,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (summary.detail != null)
                      Text(
                        summary.detail!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.labelSmall,
                      ),
                    if (summary.progress != null) ...[
                      const SizedBox(height: 6),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(99),
                        child: LinearProgressIndicator(
                          value: summary.progress!.clamp(0, 1),
                          minHeight: 5,
                          color: accent,
                          backgroundColor: accent.withValues(alpha: .15),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Text(
                  actionLabel,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: accent,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const Spacer(),
                Icon(Icons.arrow_forward, size: 18, color: accent),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class ToolkitRecentTool extends StatelessWidget {
  const ToolkitRecentTool({
    super.key,
    required this.title,
    required this.icon,
    required this.accent,
    required this.onTap,
  });

  final String title;
  final IconData icon;
  final Color accent;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: title,
    child: ActionChip(
      avatar: Icon(icon, size: 18, color: accent),
      label: Text(title),
      onPressed: onTap,
      side: BorderSide(color: accent.withValues(alpha: .22)),
    ),
  );
}

class ToolkitRecommendationTile extends StatelessWidget {
  const ToolkitRecommendationTile({
    super.key,
    required this.title,
    required this.message,
    required this.actionLabel,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String message;
  final String actionLabel;
  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: colors.secondaryContainer.withValues(alpha: .38),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: colors.secondary.withValues(alpha: .2)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: colors.secondary),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 2),
                Text(message, style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 6),
                TextButton(onPressed: onTap, child: Text(actionLabel)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class ToolkitEmptyState extends StatelessWidget {
  const ToolkitEmptyState({
    super.key,
    required this.title,
    required this.message,
  });

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 28),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Theme.of(context).dividerColor),
    ),
    child: Column(
      children: [
        const Icon(Icons.search_off_outlined, size: 38),
        const SizedBox(height: 8),
        Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
        const SizedBox(height: 4),
        Text(message, textAlign: TextAlign.center),
      ],
    ),
  );
}

class ToolkitStatusBadge extends StatelessWidget {
  const ToolkitStatusBadge({
    super.key,
    required this.label,
    this.foregroundColor,
    this.backgroundColor,
  });

  final String label;
  final Color? foregroundColor;
  final Color? backgroundColor;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color:
          backgroundColor ?? Theme.of(context).colorScheme.surfaceContainerHigh,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Text(
      label,
      style: Theme.of(context).textTheme.labelSmall?.copyWith(
        color: foregroundColor,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class ToolkitAnimatedSection extends StatelessWidget {
  const ToolkitAnimatedSection({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) => Opacity(
        opacity: value,
        child: Transform.translate(
          offset: Offset(0, (1 - value) * 8),
          child: child,
        ),
      ),
      child: child,
    );
  }
}

class _ToolkitInteractiveSurface extends StatefulWidget {
  const _ToolkitInteractiveSurface({
    required this.onTap,
    required this.borderRadius,
    required this.child,
  });

  final VoidCallback? onTap;
  final double borderRadius;
  final Widget child;

  @override
  State<_ToolkitInteractiveSurface> createState() =>
      _ToolkitInteractiveSurfaceState();
}

class _ToolkitInteractiveSurfaceState
    extends State<_ToolkitInteractiveSurface> {
  var _hovered = false;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final reduceMotion = MediaQuery.disableAnimationsOf(context);
    return MouseRegion(
      cursor: widget.onTap == null
          ? SystemMouseCursors.basic
          : SystemMouseCursors.click,
      onEnter: (_) => setState(() => _hovered = true),
      onExit: (_) => setState(() => _hovered = false),
      child: AnimatedContainer(
        duration: reduceMotion
            ? Duration.zero
            : const Duration(milliseconds: 140),
        curve: Curves.easeOut,
        decoration: BoxDecoration(
          color: _hovered
              ? colors.surfaceContainerHigh
              : colors.surfaceContainerLow,
          borderRadius: BorderRadius.circular(widget.borderRadius),
          border: Border.all(
            color: _hovered
                ? colors.primary.withValues(alpha: .42)
                : colors.outlineVariant.withValues(alpha: .7),
          ),
          boxShadow: _hovered
              ? [
                  BoxShadow(
                    color: colors.shadow.withValues(alpha: .08),
                    blurRadius: 16,
                    offset: const Offset(0, 5),
                  ),
                ]
              : const [],
        ),
        child: Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(widget.borderRadius),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: widget.onTap,
            borderRadius: BorderRadius.circular(widget.borderRadius),
            focusColor: colors.primary.withValues(alpha: .12),
            child: widget.child,
          ),
        ),
      ),
    );
  }
}
