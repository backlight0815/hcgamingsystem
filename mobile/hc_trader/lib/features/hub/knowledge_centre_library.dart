import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/knowledge_centre_item.dart';
import '../../models/knowledge_centre_category.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/empty_state.dart';

class KnowledgeCentreLibrary extends StatefulWidget {
  const KnowledgeCentreLibrary({super.key, required this.repository});

  final TradingRepository repository;

  @override
  State<KnowledgeCentreLibrary> createState() => _KnowledgeCentreLibraryState();
}

class _KnowledgeCentreLibraryState extends State<KnowledgeCentreLibrary> {
  var _category = 'all';

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<KnowledgeCentreItemMobile>>(
      stream: widget.repository.knowledgeCentreStream(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _KnowledgeErrorState(error: snapshot.error);
        }
        if (!snapshot.hasData) {
          return const _KnowledgeLoadingSkeleton();
        }

        final modules = snapshot.data ?? const <KnowledgeCentreItemMobile>[];
        if (modules.isEmpty) {
          return EmptyState(
            icon: Icons.school_outlined,
            title: context.l10n.text('No Knowledge Centre modules found'),
          );
        }

        final filtered = _category == 'all'
            ? modules
            : modules
                  .where(
                    (item) =>
                        KnowledgeCentreCategory.normalizeSlug(item.category) ==
                        _category,
                  )
                  .toList();
        final imageCount = modules.fold<int>(
          0,
          (sum, item) => sum + item.imageSections.length,
        );

        return ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: filtered.length + 2,
          itemBuilder: (context, index) {
            if (index == 0) {
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _KnowledgeHeader(
                  moduleCount: modules.length,
                  imageCount: imageCount,
                ),
              );
            }
            if (index == 1) {
              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _CategoryFilter(
                  value: _category,
                  onChanged: (value) => setState(() => _category = value),
                ),
              );
            }

            final item = filtered[index - 2];
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: KnowledgeCentreCard(
                item: item,
                moduleNumber: modules.indexOf(item) + 1,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => KnowledgeCentreDetailScreen(
                      modules: modules,
                      initialIndex: modules.indexOf(item),
                    ),
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class KnowledgeCentreCard extends StatelessWidget {
  const KnowledgeCentreCard({
    super.key,
    required this.item,
    required this.moduleNumber,
    required this.onTap,
  });

  final KnowledgeCentreItemMobile item;
  final int moduleNumber;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    final image = item.imageSections.isEmpty ? null : item.imageSections.first;
    return Card(
      elevation: 0,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: SizedBox(
                      width: 108,
                      height: 92,
                      child: KnowledgeImageVisual(
                        assetPath: image?.assetPath ?? item.primaryAssetPath,
                        storagePath: image?.storagePath ?? '',
                        imageUrl: image?.imageUrl ?? item.imageUrl,
                        label: item.localizedTitle(languageCode),
                        altText: image?.localizedAltText(languageCode) ?? '',
                        icon: _categoryIcon(item.category),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Wrap(
                          spacing: 8,
                          runSpacing: 6,
                          children: [
                            KnowledgeCategoryBadge(item: item),
                            _Pill(
                              label: context.l10n.text('Module {number}', {
                                'number': moduleNumber,
                              }),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          item.localizedTitle(languageCode),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          item.localizedShortDescription(languageCode),
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  _Pill(
                    label: context.l10n.text('{count} diagrams', {
                      'count': item.imageSections.length,
                    }),
                  ),
                  _Pill(
                    label: context.l10n.text('{minutes} min read', {
                      'minutes': _readingMinutes(item, languageCode),
                    }),
                  ),
                  FilledButton.icon(
                    onPressed: onTap,
                    icon: const Icon(Icons.menu_book_outlined),
                    label: Text(context.l10n.text('View Lesson')),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class KnowledgeCentreDetailScreen extends StatefulWidget {
  const KnowledgeCentreDetailScreen({
    super.key,
    this.item,
    this.modules = const [],
    this.initialIndex = 0,
  });

  final KnowledgeCentreItemMobile? item;
  final List<KnowledgeCentreItemMobile> modules;
  final int initialIndex;

  @override
  State<KnowledgeCentreDetailScreen> createState() =>
      _KnowledgeCentreDetailScreenState();
}

class _KnowledgeCentreDetailScreenState
    extends State<KnowledgeCentreDetailScreen> {
  late int _index;

  @override
  void initState() {
    super.initState();
    _index = _modules.isEmpty
        ? 0
        : widget.initialIndex.clamp(0, _modules.length - 1).toInt();
  }

  List<KnowledgeCentreItemMobile> get _modules {
    if (widget.modules.isNotEmpty) return widget.modules;
    final item = widget.item;
    return item == null ? const [] : [item];
  }

  KnowledgeCentreItemMobile get _item => _modules[_index];

  @override
  Widget build(BuildContext context) {
    if (_modules.isEmpty) {
      return Scaffold(
        appBar: AppBar(title: Text(context.l10n.text('Knowledge Centre'))),
        body: Center(child: Text(context.l10n.text('Content unavailable'))),
      );
    }

    final languageCode = context.l10n.locale.languageCode;
    final item = _item;
    final heroImage = item.imageSections.isEmpty
        ? null
        : item.imageSections.first;
    final remainingImages = item.imageSections.skip(1).toList(growable: false);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          item.localizedTitle(languageCode),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          KnowledgeCentreHeader(item: item, heroImage: heroImage),
          const SizedBox(height: 14),
          KnowledgeCentreBulletList(
            title: context.l10n.text('Learning Objectives'),
            items: item.localizedLearningObjectives(languageCode),
            icon: Icons.flag_outlined,
          ),
          const SizedBox(height: 14),
          _ArticleContentSection(
            title: context.l10n.text('Beginner Explanation'),
            body: item.localizedBeginnerExplanation(languageCode),
          ),
          const SizedBox(height: 14),
          _ArticleContentSection(
            title: context.l10n.text('Practical Trading Explanation'),
            body: item.localizedPracticalExplanation(languageCode),
          ),
          const SizedBox(height: 14),
          _ArticleContentSection(
            title: context.l10n.text('Lesson content'),
            body: item.localizedFullContent(languageCode),
            showUnavailable: true,
          ),
          for (final image in remainingImages) ...[
            const SizedBox(height: 14),
            KnowledgeCentreImageSection(section: image),
          ],
          const SizedBox(height: 14),
          KnowledgeCentreBulletList(
            title: context.l10n.text('Step-by-Step Guide'),
            items: item.localizedStepByStepGuide(languageCode),
            icon: Icons.route_outlined,
          ),
          const SizedBox(height: 14),
          KnowledgeCentreBulletList(
            title: context.l10n.text('Key Points'),
            items: item.localizedKeyPoints(languageCode),
            icon: Icons.check_circle_outline,
          ),
          const SizedBox(height: 14),
          _ExampleSection(items: item.localizedExamples(languageCode)),
          const SizedBox(height: 14),
          _MistakeSection(items: item.localizedCommonMistakes(languageCode)),
          const SizedBox(height: 14),
          KnowledgeCentreChecklist(
            items: item.localizedChecklist(languageCode),
          ),
          const SizedBox(height: 14),
          KnowledgeCentreBulletList(
            title: context.l10n.text('Related Concepts'),
            items: item.localizedRelatedConcepts(languageCode),
            icon: Icons.link_outlined,
          ),
          const SizedBox(height: 16),
          _LessonNavigation(
            canPrevious: _index > 0,
            canNext: _index < _modules.length - 1,
            onPrevious: () => setState(() => _index--),
            onNext: () => setState(() => _index++),
          ),
        ],
      ),
    );
  }
}

class KnowledgeCentreHeader extends StatelessWidget {
  const KnowledgeCentreHeader({
    super.key,
    required this.item,
    required this.heroImage,
  });

  final KnowledgeCentreItemMobile item;
  final KnowledgeCentreImageSectionMobile? heroImage;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        KnowledgeCategoryBadge(item: item),
        const SizedBox(height: 10),
        Text(
          item.localizedTitle(languageCode),
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        Text(
          item.localizedShortDescription(languageCode),
          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 16),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: AspectRatio(
            aspectRatio: 16 / 9,
            child: KnowledgeImageVisual(
              assetPath: heroImage?.assetPath ?? item.primaryAssetPath,
              storagePath: heroImage?.storagePath ?? '',
              imageUrl: heroImage?.imageUrl ?? item.imageUrl,
              label: item.localizedTitle(languageCode),
              altText: heroImage?.localizedAltText(languageCode) ?? '',
              icon: _categoryIcon(item.category),
            ),
          ),
        ),
        if (heroImage != null &&
            heroImage!.localizedCaption(languageCode).isNotEmpty) ...[
          const SizedBox(height: 8),
          Text(
            heroImage!.localizedCaption(languageCode),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: Theme.of(context).colorScheme.onSurfaceVariant,
              fontStyle: FontStyle.italic,
            ),
          ),
        ],
      ],
    );
  }
}

class KnowledgeCentreImageSection extends StatelessWidget {
  const KnowledgeCentreImageSection({super.key, required this.section});

  final KnowledgeCentreImageSectionMobile section;

  @override
  Widget build(BuildContext context) {
    final languageCode = context.l10n.locale.languageCode;
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              section.localizedTitle(languageCode),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: AspectRatio(
                aspectRatio: 16 / 9,
                child: KnowledgeImageVisual(
                  assetPath: section.assetPath,
                  storagePath: section.storagePath,
                  imageUrl: section.imageUrl,
                  label: section.localizedTitle(languageCode),
                  altText: section.localizedAltText(languageCode),
                  icon: Icons.insert_chart_outlined,
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(section.localizedCaption(languageCode)),
            const SizedBox(height: 6),
            Text(
              section.localizedDescription(languageCode),
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class KnowledgeImageVisual extends StatelessWidget {
  const KnowledgeImageVisual({
    super.key,
    required this.assetPath,
    required this.storagePath,
    required this.imageUrl,
    required this.label,
    required this.altText,
    required this.icon,
  });

  final String assetPath;
  final String storagePath;
  final String imageUrl;
  final String label;
  final String altText;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final remote = _firstValidUrl([storagePath, imageUrl]);
    if (remote != null) {
      return Image.network(
        remote,
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => _ImageFallback(icon: icon),
        loadingBuilder: (context, child, progress) {
          if (progress == null) return child;
          return Stack(
            fit: StackFit.expand,
            children: [
              _ImageFallback(icon: icon),
              const Center(child: CircularProgressIndicator(strokeWidth: 2)),
            ],
          );
        },
      );
    }

    final trimmedAsset = assetPath.trim();
    if (trimmedAsset.isEmpty) {
      return _ImageFallback(icon: icon);
    }

    return FutureBuilder<String>(
      future: DefaultAssetBundle.of(context).loadString(trimmedAsset),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ImageFallback(icon: icon);
        }
        if (!snapshot.hasData) {
          return Stack(
            fit: StackFit.expand,
            children: [
              _ImageFallback(icon: icon),
              const Center(child: CircularProgressIndicator(strokeWidth: 2)),
            ],
          );
        }
        return SvgPicture.string(
          snapshot.data!,
          fit: BoxFit.contain,
          semanticsLabel: altText.isEmpty ? label : altText,
        );
      },
    );
  }
}

class KnowledgeCentreBulletList extends StatelessWidget {
  const KnowledgeCentreBulletList({
    super.key,
    required this.title,
    required this.items,
    required this.icon,
  });

  final String title;
  final List<String> items;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    return _SectionShell(
      title: title,
      child: Column(
        children: [
          for (final item in items)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    icon,
                    size: 20,
                    color: Theme.of(context).colorScheme.primary,
                  ),
                  const SizedBox(width: 10),
                  Expanded(child: Text(item)),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class KnowledgeCentreChecklist extends StatelessWidget {
  const KnowledgeCentreChecklist({super.key, required this.items});

  final List<String> items;

  @override
  Widget build(BuildContext context) {
    return KnowledgeCentreBulletList(
      title: context.l10n.text('Checklist'),
      items: items,
      icon: Icons.task_alt,
    );
  }
}

class KnowledgeCategoryBadge extends StatelessWidget {
  const KnowledgeCategoryBadge({super.key, required this.item});

  final KnowledgeCentreItemMobile item;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: colors.secondaryContainer,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              _categoryIcon(item.category),
              size: 16,
              color: colors.onSecondaryContainer,
            ),
            const SizedBox(width: 6),
            Text(
              item.localizedCategoryLabel(context.l10n.locale.languageCode),
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: colors.onSecondaryContainer,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ArticleContentSection extends StatelessWidget {
  const _ArticleContentSection({
    required this.title,
    required this.body,
    this.showUnavailable = false,
  });

  final String title;
  final String body;
  final bool showUnavailable;

  @override
  Widget build(BuildContext context) {
    if (body.trim().isEmpty && !showUnavailable) {
      return const SizedBox.shrink();
    }
    final paragraphs = _paragraphs(body);
    return _SectionShell(
      title: title,
      child: paragraphs.isEmpty
          ? Text(context.l10n.text('Content unavailable'))
          : Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final paragraph in paragraphs) ...[
                  Text(
                    paragraph,
                    style: Theme.of(
                      context,
                    ).textTheme.bodyLarge?.copyWith(height: 1.48),
                  ),
                  if (paragraph != paragraphs.last) const SizedBox(height: 12),
                ],
              ],
            ),
    );
  }
}

class _ExampleSection extends StatelessWidget {
  const _ExampleSection({required this.items});

  final List<String> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    final colors = Theme.of(context).colorScheme;
    return _SectionShell(
      title: context.l10n.text('Examples'),
      child: Column(
        children: [
          for (final item in items)
            _TintedLine(
              icon: Icons.lightbulb_outline,
              color: colors.tertiary,
              text: item,
            ),
        ],
      ),
    );
  }
}

class _MistakeSection extends StatelessWidget {
  const _MistakeSection({required this.items});

  final List<String> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();
    return _SectionShell(
      title: context.l10n.text('Common Mistakes'),
      child: Column(
        children: [
          for (final item in items)
            _TintedLine(
              icon: Icons.warning_amber_outlined,
              color: Theme.of(context).colorScheme.error,
              text: item,
            ),
        ],
      ),
    );
  }
}

class _TintedLine extends StatelessWidget {
  const _TintedLine({
    required this.icon,
    required this.color,
    required this.text,
  });

  final IconData icon;
  final Color color;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: .18)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: color),
          const SizedBox(width: 10),
          Expanded(child: Text(text)),
        ],
      ),
    );
  }
}

class _LessonNavigation extends StatelessWidget {
  const _LessonNavigation({
    required this.canPrevious,
    required this.canNext,
    required this.onPrevious,
    required this.onNext,
  });

  final bool canPrevious;
  final bool canNext;
  final VoidCallback onPrevious;
  final VoidCallback onNext;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        FilledButton.icon(
          onPressed: () => Navigator.of(context).pop(),
          icon: const Icon(Icons.arrow_back),
          label: Text(context.l10n.text('Back to Knowledge Centre')),
        ),
        const SizedBox(height: 10),
        Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: canPrevious ? onPrevious : null,
                icon: const Icon(Icons.chevron_left),
                label: Text(context.l10n.text('Previous Lesson')),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton.icon(
                onPressed: canNext ? onNext : null,
                icon: const Icon(Icons.chevron_right),
                label: Text(context.l10n.text('Next Lesson')),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _KnowledgeHeader extends StatelessWidget {
  const _KnowledgeHeader({required this.moduleCount, required this.imageCount});

  final int moduleCount;
  final int imageCount;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      elevation: 0,
      color: colors.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: colors.primary,
                  foregroundColor: colors.onPrimary,
                  child: const Icon(Icons.school_outlined),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    context.l10n.text('Trading Knowledge Centre'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'Structured modules for theory, technical analysis, and psychology.',
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _Pill(label: '${context.l10n.text('Modules')}: $moduleCount'),
                _Pill(label: '${context.l10n.text('Images')}: $imageCount'),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CategoryFilter extends StatelessWidget {
  const _CategoryFilter({required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final options = <(String, String)>[
      ('all', context.l10n.text('All')),
      for (final category in KnowledgeCentreCategory.values)
        (category.slug, context.l10n.text(category.labelKey)),
    ];
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final option in options) ...[
            ChoiceChip(
              label: Text(option.$2),
              selected: value == option.$1,
              onSelected: (_) => onChanged(option.$1),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _SectionShell extends StatelessWidget {
  const _SectionShell({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 10),
            child,
          ],
        ),
      ),
    );
  }
}

class _ImageFallback extends StatelessWidget {
  const _ImageFallback({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 34),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10),
            child: Text(
              context.l10n.text('Image unavailable'),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: Theme.of(
                context,
              ).textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    if (label.trim().isEmpty) return const SizedBox.shrink();
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
        child: Text(
          label,
          style: Theme.of(
            context,
          ).textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w900),
        ),
      ),
    );
  }
}

class _KnowledgeLoadingSkeleton extends StatelessWidget {
  const _KnowledgeLoadingSkeleton();

  @override
  Widget build(BuildContext context) {
    final color = Theme.of(context).colorScheme.surfaceContainerHighest;
    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: 5,
      itemBuilder: (context, index) {
        return Container(
          height: index == 0 ? 126 : 142,
          margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(8),
          ),
        );
      },
    );
  }
}

class _KnowledgeErrorState extends StatelessWidget {
  const _KnowledgeErrorState({required this.error});

  final Object? error;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 42),
            const SizedBox(height: 12),
            Text(
              context.l10n.text('Failed to load Knowledge Centre'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Text('$error', textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

List<String> _paragraphs(String body) {
  final direct = body
      .split(RegExp(r'\n\s*\n|\r\n\s*\r\n'))
      .map((item) => item.trim())
      .where((item) => item.isNotEmpty)
      .toList();
  if (direct.length > 1 || body.length < 420) return direct;

  final sentences = body
      .split(RegExp(r'(?<=[.!?。！？])\s+'))
      .map((item) => item.trim())
      .where((item) => item.isNotEmpty)
      .toList();
  if (sentences.length <= 3) return direct;

  final paragraphs = <String>[];
  for (var index = 0; index < sentences.length; index += 3) {
    paragraphs.add(sentences.skip(index).take(3).join(' '));
  }
  return paragraphs;
}

String? _firstValidUrl(List<String> candidates) {
  for (final candidate in candidates) {
    final uri = Uri.tryParse(candidate.trim());
    if (uri != null && uri.hasScheme) return candidate.trim();
  }
  return null;
}

IconData _categoryIcon(String category) {
  return switch (KnowledgeCentreCategory.normalizeSlug(category)) {
    'theory' => Icons.menu_book_outlined,
    'technical' => Icons.candlestick_chart_outlined,
    'psychology' => Icons.psychology_outlined,
    'stocks' => Icons.show_chart,
    'futures-trading' => Icons.timeline,
    'investment' => Icons.savings_outlined,
    'cfd-trading' => Icons.swap_vert_circle_outlined,
    _ => Icons.school_outlined,
  };
}

int _readingMinutes(KnowledgeCentreItemMobile item, String languageCode) {
  final words =
      [
            item.localizedTitle(languageCode),
            item.localizedShortDescription(languageCode),
            ...item.localizedLearningObjectives(languageCode),
            item.localizedBeginnerExplanation(languageCode),
            item.localizedPracticalExplanation(languageCode),
            item.localizedFullContent(languageCode),
            ...item.localizedStepByStepGuide(languageCode),
            ...item.localizedKeyPoints(languageCode),
            ...item.localizedExamples(languageCode),
            ...item.localizedCommonMistakes(languageCode),
            ...item.localizedChecklist(languageCode),
            ...item.localizedRelatedConcepts(languageCode),
          ]
          .join(' ')
          .split(RegExp(r'\s+'))
          .where((word) => word.trim().isNotEmpty)
          .length;
  return (words / 180).ceil().clamp(1, 20);
}
