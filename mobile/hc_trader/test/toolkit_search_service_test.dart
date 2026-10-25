import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/tools/toolkit_models.dart';
import 'package:hc_trader/services/toolkit_search_service.dart';

void main() {
  const service = ToolkitSearchService();
  const english = AppLocalizations(Locale('en'));
  const chinese = AppLocalizations(Locale('zh'));

  group('Trading Toolkit ranked search', () {
    test('normalizes case and whitespace and ranks exact title first', () {
      final results = service.search(
        items: toolkitCatalog,
        localizations: english,
        query: '  RISK   CALCULATOR ',
      );

      expect(results.first.id, 'risk_calculator');
    });

    test('supports prefixes, partial descriptions, categories and aliases', () {
      expect(
        service
            .search(
              items: toolkitCatalog,
              localizations: english,
              query: 'stat',
            )
            .first
            .id,
        'statistics',
      );
      expect(
        service
            .search(
              items: toolkitCatalog,
              localizations: english,
              query: 'projected p/l',
            )
            .map((item) => item.id),
        contains('risk_calculator'),
      );
      expect(
        service
            .search(
              items: toolkitCatalog,
              localizations: english,
              query: 'funded account',
            )
            .map((item) => item.id),
        contains('prop_firm'),
      );
      expect(
        service
            .search(
              items: toolkitCatalog,
              localizations: english,
              query: 'performance and risk',
            )
            .map((item) => item.id),
        containsAll(['statistics', 'performance_ratings']),
      );
    });

    test('matches multi-word terms across different metadata fields', () {
      final results = service.search(
        items: toolkitCatalog,
        localizations: english,
        query: 'psychology readiness',
      );

      expect(results.map((item) => item.id), contains('psychology'));
    });

    test('searches localized Chinese title, keyword and substring text', () {
      expect(
        service
            .search(items: toolkitCatalog, localizations: chinese, query: '仓位')
            .first
            .id,
        'risk_calculator',
      );
      expect(
        service
            .search(
              items: toolkitCatalog,
              localizations: chinese,
              query: '个性测试',
            )
            .map((item) => item.id),
        contains('personality_guidance'),
      );
    });

    test('applies permission and category filters before matching', () {
      final restricted = service.search(
        items: toolkitCatalog,
        localizations: english,
        query: 'performance',
        canAccess: (item) => !item.requiresFullAccess,
      );
      expect(restricted.map((item) => item.id), contains('statistics'));
      expect(
        restricted.map((item) => item.id),
        isNot(contains('performance_ratings')),
      );

      final psychologyOnly = service.search(
        items: toolkitCatalog,
        localizations: english,
        category: ToolkitCategory.psychology,
      );
      expect(
        psychologyOnly.every(
          (item) => item.category == ToolkitCategory.psychology,
        ),
        isTrue,
      );
    });

    test(
      'clearing the query restores all authorized items in source order',
      () {
        final results = service.search(
          items: toolkitCatalog,
          localizations: english,
          query: '   ',
          canAccess: (item) => !item.requiresFullAccess,
        );
        final expected = toolkitCatalog
            .where((item) => !item.requiresFullAccess)
            .map((item) => item.id);

        expect(results.map((item) => item.id), expected);
      },
    );

    test('works identically with merged local and remotely cached items', () {
      const remote = ToolkitItem(
        id: 'remote_volatility_lab',
        titleKey: 'Volatility Lab',
        descriptionKey: 'Compare session volatility',
        searchKeywordsKey: 'volatility session remote',
        actionKey: 'Open',
        icon: Icons.science_outlined,
        category: ToolkitCategory.performance,
        summaryKind: ToolkitSummaryKind.statistics,
      );
      final merged = <ToolkitItem>[...toolkitCatalog, remote];

      expect(
        service
            .search(
              items: merged,
              localizations: english,
              query: 'remote volatility',
            )
            .single
            .id,
        remote.id,
      );
    });
  });
}
