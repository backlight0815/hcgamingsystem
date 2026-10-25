import 'package:flutter/material.dart';

import '../core/localization/app_localizations.dart';
import '../core/localization/publishing_localization.dart';

class ContentLanguageSelector extends StatelessWidget {
  const ContentLanguageSelector({
    super.key,
    required this.primaryLanguage,
    required this.onChanged,
  });

  final PublishingLanguage primaryLanguage;
  final ValueChanged<PublishingLanguage> onChanged;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.l10n.text('Content Language'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<PublishingLanguage>(
              key: const Key('publisher-primary-language'),
              initialValue: primaryLanguage,
              decoration: InputDecoration(
                labelText: context.l10n.text('Primary language'),
              ),
              items: [
                DropdownMenuItem(
                  value: PublishingLanguage.english,
                  child: Text(context.l10n.text('English')),
                ),
                DropdownMenuItem(
                  value: PublishingLanguage.simplifiedChinese,
                  child: Text(context.l10n.text('Simplified Chinese')),
                ),
              ],
              onChanged: (value) {
                if (value != null) onChanged(value);
              },
            ),
            const SizedBox(height: 6),
            Text(
              context.l10n.text(
                'Only the primary language is required. The translation is optional.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}
