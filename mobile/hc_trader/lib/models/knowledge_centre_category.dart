class KnowledgeCentreCategory {
  const KnowledgeCentreCategory({
    required this.slug,
    required this.labelKey,
    required this.sortOrder,
  });

  final String slug;
  final String labelKey;
  final int sortOrder;

  static const values = <KnowledgeCentreCategory>[
    KnowledgeCentreCategory(slug: 'theory', labelKey: 'Theory', sortOrder: 10),
    KnowledgeCentreCategory(
      slug: 'technical',
      labelKey: 'Technical',
      sortOrder: 20,
    ),
    KnowledgeCentreCategory(
      slug: 'psychology',
      labelKey: 'Psychology',
      sortOrder: 30,
    ),
    KnowledgeCentreCategory(slug: 'stocks', labelKey: 'Stocks', sortOrder: 40),
    KnowledgeCentreCategory(
      slug: 'futures-trading',
      labelKey: 'Futures Trading',
      sortOrder: 50,
    ),
    KnowledgeCentreCategory(
      slug: 'investment',
      labelKey: 'Investment',
      sortOrder: 60,
    ),
    KnowledgeCentreCategory(
      slug: 'cfd-trading',
      labelKey: 'CFD Trading',
      sortOrder: 70,
    ),
  ];

  static const slugs = <String>[
    'theory',
    'technical',
    'psychology',
    'stocks',
    'futures-trading',
    'investment',
    'cfd-trading',
  ];

  static String normalizeSlug(Object? value) {
    final normalized = (value?.toString() ?? '')
        .trim()
        .toLowerCase()
        .replaceAll(RegExp(r'[\s_]+'), '-')
        .replaceAll(RegExp(r'-+'), '-');
    return switch (normalized) {
      'stock' || 'stocks' => 'stocks',
      'future' ||
      'futures' ||
      'trading-future' ||
      'future-trading' ||
      'futures-trading' => 'futures-trading',
      'investments' || 'investment' => 'investment',
      'cfd' || 'cfd-trading' || 'trading-cfd' => 'cfd-trading',
      _ => normalized,
    };
  }

  static KnowledgeCentreCategory? bySlug(Object? value) {
    final normalized = normalizeSlug(value);
    for (final category in values) {
      if (category.slug == normalized) return category;
    }
    return null;
  }
}
