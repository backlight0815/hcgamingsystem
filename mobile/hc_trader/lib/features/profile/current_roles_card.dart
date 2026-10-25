import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/security/user_roles.dart';

class CurrentRolesCard extends StatelessWidget {
  const CurrentRolesCard({super.key, required this.roles});

  final List<String> roles;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final normalizedRoles = sortUserRoles(roles);

    return Card(
      key: const ValueKey('current-roles-card'),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  Icons.verified_user_outlined,
                  size: 20,
                  color: colors.primary,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    context.l10n.text('Current Roles'),
                    key: const ValueKey('current-roles-title'),
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (normalizedRoles.isEmpty)
              Text(
                context.l10n.text('No role assigned'),
                key: const ValueKey('current-roles-empty'),
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              )
            else
              LayoutBuilder(
                builder: (context, constraints) => Wrap(
                  key: const ValueKey('current-roles-wrap'),
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final role in normalizedRoles)
                      ConstrainedBox(
                        constraints: BoxConstraints(
                          maxWidth: constraints.maxWidth,
                        ),
                        child: Chip(
                          key: ValueKey('role-chip-$role'),
                          avatar: Icon(
                            getRoleIcon(role),
                            size: 18,
                            color: colors.onSecondaryContainer,
                          ),
                          label: Text(
                            context.l10n.roleDisplayName(role),
                            softWrap: true,
                          ),
                          labelStyle: theme.textTheme.labelLarge?.copyWith(
                            color: colors.onSecondaryContainer,
                          ),
                          backgroundColor: colors.secondaryContainer,
                          side: BorderSide(color: colors.outlineVariant),
                          materialTapTargetSize:
                              MaterialTapTargetSize.shrinkWrap,
                          visualDensity: VisualDensity.compact,
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

IconData getRoleIcon(String role) {
  return switch (normalizeUserRole(role)) {
    'super_admin' => Icons.admin_panel_settings,
    'sub_admin' => Icons.manage_accounts,
    'market_analyst' => Icons.analytics_outlined,
    'signal_provider' ||
    'senior_signal_provider' ||
    'signal_provider_management' => Icons.campaign_outlined,
    'financial_department' ||
    'financial_consultant' => Icons.account_balance_wallet_outlined,
    'leader' => Icons.groups_outlined,
    'recruiter' => Icons.person_add_alt_1_outlined,
    'trader' => Icons.show_chart,
    'lead_client' => Icons.person_search_outlined,
    'founder' => Icons.workspace_premium_outlined,
    'admin_assistant' => Icons.support_agent_outlined,
    'evaluator' => Icons.fact_check_outlined,
    _ => Icons.badge_outlined,
  };
}
