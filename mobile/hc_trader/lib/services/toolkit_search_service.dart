import '../core/localization/app_localizations.dart';
import '../features/tools/toolkit_models.dart';

/// Normalizes and ranks Trading Toolkit content without coupling search logic
/// to a widget or a particular data source.
class ToolkitSearchService {
  const ToolkitSearchService();

  List<ToolkitItem> search({
    required Iterable<ToolkitItem> items,
    required AppLocalizations localizations,
    String query = '',
    ToolkitCategory? category,
    bool Function(ToolkitItem item)? canAccess,
  }) {
    final normalizedQuery = normalize(query);
    final scored = <_ScoredToolkitItem>[];
    var sourceOrder = 0;

    for (final item in items) {
      final order = sourceOrder++;
      if (canAccess != null && !canAccess(item)) continue;
      if (category != null && item.category != category) continue;
      if (normalizedQuery.isEmpty) {
        scored.add(_ScoredToolkitItem(item, 0, order));
        continue;
      }

      final score = _score(item, localizations, normalizedQuery);
      if (score != null) scored.add(_ScoredToolkitItem(item, score, order));
    }

    scored.sort((left, right) {
      final relevance = left.score.compareTo(right.score);
      return relevance != 0
          ? relevance
          : left.sourceOrder.compareTo(right.sourceOrder);
    });
    return scored.map((entry) => entry.item).toList(growable: false);
  }

  /// Trims, case-folds and collapses all whitespace. Keeping the complete
  /// normalized string enables useful substring matching for Chinese and
  /// other languages where words are not necessarily separated by spaces.
  static String normalize(String value) =>
      value.trim().toLowerCase().replaceAll(RegExp(r'\s+'), ' ');

  int? _score(ToolkitItem item, AppLocalizations localizations, String query) {
    final toolName = normalize(item.id.replaceAll(RegExp(r'[_-]+'), ' '));
    final sourceTitle = normalize(item.titleKey);
    final title = normalize(localizations.text(item.titleKey));
    final category = normalize(localizations.text(item.category.titleKey));
    final categoryFilter = normalize(
      localizations.text(item.category.filterKey),
    );
    final subcategory = normalize(
      item.subcategoryKey == null
          ? ''
          : localizations.text(item.subcategoryKey!),
    );
    final keywords = normalize(localizations.text(item.searchKeywordsKey));
    final tags = normalize(item.tagsKeys.map(localizations.text).join(' '));
    final aliases = normalize(
      item.searchAliasesKeys.map(localizations.text).join(' '),
    );
    final description = normalize(localizations.text(item.descriptionKey));

    final fields = <_SearchField>[
      _SearchField(toolName, 0, 2, 4),
      _SearchField(sourceTitle, 0, 2, 4),
      _SearchField(title, 1, 3, 4),
      _SearchField(category, 5, 5, 5),
      _SearchField(categoryFilter, 5, 5, 5),
      _SearchField(subcategory, 5, 5, 5),
      _SearchField(keywords, 6, 6, 6),
      _SearchField(tags, 6, 6, 6),
      _SearchField(aliases, 6, 6, 6),
      _SearchField(description, 7, 7, 7),
    ];

    final direct = _bestFieldScore(fields, query);
    if (direct != null) return direct * 100;

    final terms = query.split(' ').where((term) => term.isNotEmpty).toList();
    if (terms.length < 2) return null;
    var total = 0;
    var weakest = 0;
    for (final term in terms) {
      final termScore = _bestFieldScore(fields, term);
      if (termScore == null) return null;
      total += termScore;
      if (termScore > weakest) weakest = termScore;
    }
    // Multi-word matches remain below an equivalent direct field match, while
    // their weakest field controls the overall relevance class.
    return weakest * 100 + 20 + total;
  }

  int? _bestFieldScore(List<_SearchField> fields, String query) {
    int? best;
    for (final field in fields) {
      if (field.value.isEmpty) continue;
      final score = field.value == query
          ? field.exactScore
          : field.value.startsWith(query)
          ? field.prefixScore
          : field.value.contains(query)
          ? field.partialScore
          : null;
      if (score != null && (best == null || score < best)) best = score;
    }
    return best;
  }
}

class _SearchField {
  const _SearchField(
    this.value,
    this.exactScore,
    this.prefixScore,
    this.partialScore,
  );

  final String value;
  final int exactScore;
  final int prefixScore;
  final int partialScore;
}

class _ScoredToolkitItem {
  const _ScoredToolkitItem(this.item, this.score, this.sourceOrder);

  final ToolkitItem item;
  final int score;
  final int sourceOrder;
}
