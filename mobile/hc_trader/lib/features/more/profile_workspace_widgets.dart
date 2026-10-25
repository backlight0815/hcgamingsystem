import 'package:flutter/material.dart';

abstract final class ProfileWorkspaceLayout {
  static const double tablet = 700;
  static const double desktop = 1100;
  static const double maxContentWidth = 1280;
  static const double desktopRailWidth = 320;

  static bool useDesktopRail(double width) => width >= desktop;

  static int quickActionColumns(double width) => width >= tablet ? 4 : 2;

  static int serviceColumns(double width) {
    if (width >= desktop) return 3;
    if (width >= tablet) return 2;
    return 2;
  }

  static double quickActionExtent(TextScaler scaler) =>
      scaler.scale(1) > 1.3 ? 148 : 118;

  static double serviceExtent(TextScaler scaler, {required bool compact}) {
    if (scaler.scale(1) > 1.3) return compact ? 178 : 210;
    return compact ? 160 : 170;
  }

  static EdgeInsets pagePadding(double width) => EdgeInsets.fromLTRB(
    width >= tablet ? 24 : 16,
    14,
    width >= tablet ? 24 : 16,
    32,
  );
}

class ProfileIdentityHeader extends StatelessWidget {
  const ProfileIdentityHeader({
    super.key,
    required this.name,
    required this.email,
    required this.role,
    required this.approvalStatus,
    required this.completionLabel,
    required this.completionProgress,
    required this.completionNextStep,
    required this.completionActionLabel,
    required this.initials,
    required this.editTooltip,
    required this.onEdit,
    required this.onContinueSetup,
    this.avatarUrl = '',
  });

  final String name;
  final String email;
  final String role;
  final String approvalStatus;
  final String completionLabel;
  final double completionProgress;
  final String? completionNextStep;
  final String? completionActionLabel;
  final String initials;
  final String editTooltip;
  final VoidCallback? onEdit;
  final VoidCallback? onContinueSetup;
  final String avatarUrl;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            colors.primaryContainer,
            Color.lerp(colors.primaryContainer, colors.tertiaryContainer, .7)!,
          ],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: colors.primary.withValues(alpha: .18)),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final horizontal = constraints.maxWidth >= 560;
          final avatar = CircleAvatar(
            radius: horizontal ? 32 : 29,
            foregroundImage: avatarUrl.trim().isEmpty
                ? null
                : NetworkImage(avatarUrl),
            child: avatarUrl.trim().isEmpty
                ? Text(
                    initials,
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  )
                : null,
          );
          final details = Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                        color: colors.onPrimaryContainer,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: editTooltip,
                    onPressed: onEdit,
                    icon: const Icon(Icons.edit_outlined),
                    color: colors.onPrimaryContainer,
                  ),
                ],
              ),
              Text(
                email,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: colors.onPrimaryContainer.withValues(alpha: .82),
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  if (role.trim().isNotEmpty)
                    _IdentityBadge(
                      icon: Icons.badge_outlined,
                      label: role,
                      colors: colors,
                    ),
                  _IdentityBadge(
                    icon: Icons.verified_user_outlined,
                    label: approvalStatus,
                    colors: colors,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(99),
                      child: LinearProgressIndicator(
                        value: completionProgress.clamp(0, 1),
                        minHeight: 7,
                        color: colors.onPrimaryContainer,
                        backgroundColor: colors.onPrimaryContainer.withValues(
                          alpha: .14,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Flexible(
                    child: Text(
                      completionLabel,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.labelMedium?.copyWith(
                        color: colors.onPrimaryContainer,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
              if (completionNextStep != null) ...[
                const SizedBox(height: 7),
                Wrap(
                  spacing: 6,
                  runSpacing: 2,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text(
                      completionNextStep!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: colors.onPrimaryContainer.withValues(alpha: .82),
                      ),
                    ),
                    TextButton(
                      key: const ValueKey('profile-continue-setup'),
                      onPressed: onContinueSetup,
                      child: Text(completionActionLabel!),
                    ),
                  ],
                ),
              ],
            ],
          );

          if (!horizontal) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [avatar, const SizedBox(height: 12), details],
            );
          }
          return Row(
            children: [
              avatar,
              const SizedBox(width: 16),
              Expanded(child: details),
            ],
          );
        },
      ),
    );
  }
}

class _IdentityBadge extends StatelessWidget {
  const _IdentityBadge({
    required this.icon,
    required this.label,
    required this.colors,
  });

  final IconData icon;
  final String label;
  final ColorScheme colors;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
    decoration: BoxDecoration(
      color: colors.onPrimaryContainer.withValues(alpha: .09),
      borderRadius: BorderRadius.circular(999),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: colors.onPrimaryContainer),
        const SizedBox(width: 5),
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: colors.onPrimaryContainer,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    ),
  );
}

class ProfileSectionHeader extends StatelessWidget {
  const ProfileSectionHeader({super.key, required this.title, this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: Theme.of(
          context,
        ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
      ),
      if (subtitle != null)
        Text(
          subtitle!,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
    ],
  );
}

class ProfileStatusStrip extends StatelessWidget {
  const ProfileStatusStrip({super.key, required this.items});

  final List<ProfileStatusData> items;

  @override
  Widget build(BuildContext context) => Wrap(
    spacing: 8,
    runSpacing: 8,
    children: [
      for (final item in items)
        Semantics(
          label: '${item.label}. ${item.value}',
          button: item.onTap != null,
          child: ActionChip(
            avatar: Icon(item.icon, size: 17),
            label: Text('${item.label}: ${item.value}'),
            onPressed: item.onTap,
          ),
        ),
    ],
  );
}

class ProfileStatusData {
  const ProfileStatusData({
    required this.label,
    required this.value,
    required this.icon,
    this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final VoidCallback? onTap;
}

class ProfileQuickAction extends StatelessWidget {
  const ProfileQuickAction({
    super.key,
    required this.label,
    required this.tooltip,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final String tooltip;
  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final accent = Theme.of(context).colorScheme.primary;
    return Tooltip(
      message: tooltip,
      child: Semantics(
        button: true,
        label: label,
        child: _ProfileInteractiveSurface(
          onTap: onTap,
          radius: 17,
          child: Padding(
            padding: const EdgeInsets.all(13),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: accent.withValues(alpha: .13),
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
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class ProfileBentoModule extends StatelessWidget {
  const ProfileBentoModule({
    super.key,
    required this.title,
    required this.description,
    required this.actionLabel,
    required this.icon,
    required this.onTap,
    this.status,
    this.highlighted = false,
  });

  final String title;
  final String description;
  final String actionLabel;
  final String? status;
  final IconData icon;
  final VoidCallback? onTap;
  final bool highlighted;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final accent = highlighted ? colors.tertiary : colors.primary;
    return Semantics(
      button: true,
      label: '$title. $description',
      child: _ProfileInteractiveSurface(
        onTap: onTap,
        radius: 20,
        highlighted: highlighted,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 43,
                    height: 43,
                    decoration: BoxDecoration(
                      color: accent.withValues(alpha: .13),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(icon, color: accent),
                  ),
                  if (status == null)
                    const Spacer()
                  else ...[
                    const SizedBox(width: 8),
                    Expanded(
                      child: Align(
                        alignment: Alignment.centerRight,
                        child: Container(
                          constraints: const BoxConstraints(maxWidth: 150),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 9,
                            vertical: 5,
                          ),
                          decoration: BoxDecoration(
                            color: colors.surfaceContainerHighest,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            status!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              const Spacer(),
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 3),
              Text(
                description,
                maxLines: highlighted ? 2 : 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(
                  context,
                ).textTheme.bodySmall?.copyWith(color: colors.onSurfaceVariant),
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
}

class ProfileSettingsGroup extends StatelessWidget {
  const ProfileSettingsGroup({
    super.key,
    required this.title,
    required this.children,
  });

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
          child: Text(
            title,
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
        ),
        for (var index = 0; index < children.length; index++) ...[
          if (index > 0) const Divider(height: 1, indent: 56),
          children[index],
        ],
      ],
    ),
  );
}

class ProfileSettingsRow extends StatelessWidget {
  const ProfileSettingsRow({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
    this.danger = false,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback? onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? Theme.of(context).colorScheme.error : null;
    return ListTile(
      leading: Icon(icon, color: color),
      title: Text(title, style: TextStyle(color: color)),
      subtitle: Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
      mouseCursor: onTap == null
          ? SystemMouseCursors.basic
          : SystemMouseCursors.click,
    );
  }
}

class ProfileActivityTimeline extends StatelessWidget {
  const ProfileActivityTimeline({
    super.key,
    required this.title,
    required this.detail,
    required this.time,
    required this.icon,
  });

  final String title;
  final String detail;
  final String time;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CircleAvatar(radius: 20, child: Icon(icon, size: 19)),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
              Text(detail),
              const SizedBox(height: 3),
              Text(time, style: Theme.of(context).textTheme.labelSmall),
            ],
          ),
        ),
      ],
    ),
  );
}

class ProfileInlineNotice extends StatelessWidget {
  const ProfileInlineNotice({
    super.key,
    required this.message,
    required this.icon,
  });

  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.errorContainer.withValues(alpha: .5),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      children: [
        Icon(icon, size: 19),
        const SizedBox(width: 9),
        Expanded(child: Text(message)),
      ],
    ),
  );
}

class ProfileAnimatedSection extends StatelessWidget {
  const ProfileAnimatedSection({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.disableAnimationsOf(context)) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: const Duration(milliseconds: 200),
      curve: Curves.easeOutCubic,
      builder: (context, value, child) => Opacity(
        opacity: value,
        child: Transform.translate(
          offset: Offset(0, 7 * (1 - value)),
          child: child,
        ),
      ),
      child: child,
    );
  }
}

class _ProfileInteractiveSurface extends StatefulWidget {
  const _ProfileInteractiveSurface({
    required this.onTap,
    required this.radius,
    required this.child,
    this.highlighted = false,
  });

  final VoidCallback? onTap;
  final double radius;
  final Widget child;
  final bool highlighted;

  @override
  State<_ProfileInteractiveSurface> createState() =>
      _ProfileInteractiveSurfaceState();
}

class _ProfileInteractiveSurfaceState
    extends State<_ProfileInteractiveSurface> {
  var _hovered = false;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return MouseRegion(
      cursor: widget.onTap == null
          ? SystemMouseCursors.basic
          : SystemMouseCursors.click,
      onEnter: (_) => setState(() => _hovered = true),
      onExit: (_) => setState(() => _hovered = false),
      child: AnimatedContainer(
        duration: MediaQuery.disableAnimationsOf(context)
            ? Duration.zero
            : const Duration(milliseconds: 140),
        decoration: BoxDecoration(
          color: widget.highlighted
              ? colors.primaryContainer.withValues(alpha: _hovered ? .75 : .5)
              : _hovered
              ? colors.surfaceContainerHigh
              : colors.surfaceContainerLow,
          borderRadius: BorderRadius.circular(widget.radius),
          border: Border.all(
            color: _hovered
                ? colors.primary.withValues(alpha: .42)
                : colors.outlineVariant,
          ),
          boxShadow: _hovered
              ? [
                  BoxShadow(
                    color: colors.shadow.withValues(alpha: .08),
                    blurRadius: 14,
                    offset: const Offset(0, 4),
                  ),
                ]
              : const [],
        ),
        child: Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(widget.radius),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: widget.onTap,
            borderRadius: BorderRadius.circular(widget.radius),
            focusColor: colors.primary.withValues(alpha: .12),
            child: widget.child,
          ),
        ),
      ),
    );
  }
}
