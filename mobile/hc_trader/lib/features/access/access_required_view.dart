import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';

class AccessRequiredView extends StatelessWidget {
  const AccessRequiredView({
    super.key,
    required this.featureName,
    this.user,
    this.onViewStatus,
  });

  final String featureName;
  final AppUser? user;
  final VoidCallback? onViewStatus;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(22),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  Icons.lock_outline,
                  size: 52,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(height: 12),
                Text(
                  context.l10n.text('Client access required'),
                  textAlign: TextAlign.center,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                if (user != null) ...[
                  const SizedBox(height: 12),
                  Chip(
                    avatar: const Icon(Icons.info_outline, size: 18),
                    label: Text(
                      context.l10n.text('Current access status: {status}', {
                        'status': context.l10n.text(
                          user!.accessStatus.storageValue,
                        ),
                      }),
                    ),
                  ),
                ],
                if (onViewStatus != null) ...[
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: onViewStatus,
                    icon: const Icon(Icons.verified_user_outlined),
                    label: Text(
                      context.l10n.text(
                        user?.accessStatus.canReapply == true
                            ? 'Apply for Access'
                            : 'View Application Status',
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Text(
                  context.l10n.text(
                    '{feature} is available after admin approves your client access request.',
                    {'feature': context.l10n.text(featureName)},
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
