import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/coursework_models.dart';

/// Searchable, document-ID based selector shared by assignment create/edit.
class CourseworkThemeSelector extends StatelessWidget {
  const CourseworkThemeSelector({
    super.key,
    required this.themes,
    required this.selectedThemeId,
    required this.onSelected,
    this.enabled = true,
  });

  final List<CourseworkTheme> themes;
  final String? selectedThemeId;
  final ValueChanged<String> onSelected;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    final selected = themes
        .where((theme) => theme.id == selectedThemeId)
        .firstOrNull;
    final unavailable = selected == null && selectedThemeId?.isNotEmpty == true;
    return Semantics(
      button: true,
      label: context.l10n.text('Select Coursework theme'),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: enabled ? () => _showSelector(context) : null,
        child: InputDecorator(
          isEmpty: selected == null && !unavailable,
          decoration: InputDecoration(
            labelText: context.l10n.text('Published theme'),
            errorText: unavailable
                ? context.l10n.text('Previously selected theme unavailable')
                : null,
            suffixIcon: const Icon(Icons.search),
          ),
          child: selected == null
              ? Text(
                  unavailable
                      ? selectedThemeId!
                      : context.l10n.text('Choose a theme'),
                )
              : _ThemeSummary(theme: selected),
        ),
      ),
    );
  }

  Future<void> _showSelector(BuildContext context) async {
    final selected = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => _ThemeSelectionSheet(
        themes: themes,
        selectedThemeId: selectedThemeId,
      ),
    );
    if (selected != null) onSelected(selected);
  }
}

class _ThemeSelectionSheet extends StatefulWidget {
  const _ThemeSelectionSheet({
    required this.themes,
    required this.selectedThemeId,
  });

  final List<CourseworkTheme> themes;
  final String? selectedThemeId;

  @override
  State<_ThemeSelectionSheet> createState() => _ThemeSelectionSheetState();
}

class _ThemeSelectionSheetState extends State<_ThemeSelectionSheet> {
  final search = TextEditingController();

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final matches = CourseworkThemeFilter.apply(
      widget.themes,
      search: search.text,
      includeInactive: false,
      assignableOnly: true,
    );
    return SafeArea(
      child: FractionallySizedBox(
        heightFactor: .86,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: Text(
                context.l10n.text('Select Coursework theme'),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: SearchBar(
                controller: search,
                hintText: context.l10n.text('Search theme title or code'),
                leading: const Icon(Icons.search),
                onChanged: (_) => setState(() {}),
              ),
            ),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(
                context.l10n.text('{count} eligible theme(s)', {
                  'count': matches.length,
                }),
              ),
            ),
            const SizedBox(height: 4),
            Expanded(
              child: matches.isEmpty
                  ? Center(
                      child: Text(
                        context.l10n.text(
                          'No eligible published themes are available.',
                        ),
                        textAlign: TextAlign.center,
                      ),
                    )
                  : ListView.builder(
                      itemCount: matches.length,
                      itemBuilder: (context, index) {
                        final theme = matches[index];
                        return ListTile(
                          selected: theme.id == widget.selectedThemeId,
                          leading: Icon(
                            theme.id == widget.selectedThemeId
                                ? Icons.check_circle
                                : Icons.menu_book_outlined,
                          ),
                          title: Text(
                            theme.localizedTitle(
                              context.l10n.locale.languageCode,
                            ),
                          ),
                          subtitle: Text(
                            '${theme.code} · ${_categoryLabel(context, theme)} · ${context.l10n.text(theme.difficultyValue.storageValue)} · ${context.l10n.text(theme.publicationStatus.storageValue)}',
                          ),
                          onTap: () => Navigator.pop(context, theme.id),
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

class _ThemeSummary extends StatelessWidget {
  const _ThemeSummary({required this.theme});
  final CourseworkTheme theme;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        theme.localizedTitle(context.l10n.locale.languageCode),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      Text(
        '${theme.code} · ${_categoryLabel(context, theme)} · ${context.l10n.text(theme.difficultyValue.storageValue)}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.bodySmall,
      ),
    ],
  );
}

String _categoryLabel(BuildContext context, CourseworkTheme theme) {
  final category = CourseworkTaxonomy.find(theme.categoryId);
  if (context.l10n.locale.languageCode == 'zh') {
    return theme.categoryI18n['zh'] ?? category?.zh ?? theme.category;
  }
  return theme.category.isNotEmpty
      ? theme.category
      : category?.en ?? theme.categoryId;
}
