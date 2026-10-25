import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/config/firestore_collections.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/admin/coursework_theme_selector.dart';
import 'package:hc_trader/models/coursework_models.dart';

void main() {
  group('Coursework theme Firestore compatibility', () {
    test('uses one authoritative collection path', () {
      expect(FirestoreCollections.courseworkThemes, 'coursework_themes');
      expect(
        FirestoreCollections.courseworkAssignments,
        'coursework_assignments',
      );
    });

    test('parses canonical fields and Firestore timestamps', () {
      final createdAt = DateTime.utc(2026, 7, 1);
      final updatedAt = DateTime.utc(2026, 7, 2);
      final theme = CourseworkTheme.fromMap({
        'id': 'theme-a',
        'code': 'CW-A',
        'title': {'en': 'Market basics', 'zh': '市场基础'},
        'categoryId': 'market_foundations',
        'categoryName': {'en': 'Trading Market Foundations', 'zh': '交易市场基础'},
        'difficulty': 'beginner',
        'status': 'published',
        'isActive': true,
        'createdAt': Timestamp.fromDate(createdAt),
        'updatedAt': Timestamp.fromDate(updatedAt),
      });

      expect(theme.id, 'theme-a');
      expect(theme.difficultyValue, CourseworkDifficulty.beginner);
      expect(theme.publicationStatus, CourseworkPublicationStatus.published);
      expect(theme.createdAt?.toUtc(), createdAt);
      expect(theme.updatedAt?.toUtc(), updatedAt);
      expect(theme.localizedTitle('zh'), '市场基础');
    });

    test('normalizes legacy field names, labels, and enum aliases', () {
      final theme = CourseworkTheme.fromMap({
        'themeId': 'legacy-theme',
        'themeCode': 'LEG-1',
        'name': 'Legacy market theme',
        'category': 'Trading Market Foundations',
        'difficultyLevel': '初级',
        'publicationStatus': '已发布',
        'active': 1,
        'estimated_completion_minutes': '75',
        'updated_at': '2026-07-02T12:30:00Z',
      });

      expect(theme.categoryId, 'market_foundations');
      expect(theme.difficulty, 'beginner');
      expect(theme.status, 'published');
      expect(theme.isActive, isTrue);
      expect(theme.estimatedCompletionMinutes, 75);
      expect(theme.updatedAt, DateTime.parse('2026-07-02T12:30:00Z'));
    });

    test('aliases map to canonical machine values', () {
      for (final alias in ['Beginner', 'beginner', 'basic', '初级']) {
        expect(
          CourseworkDifficulty.fromFirestore(alias).storageValue,
          'beginner',
        );
      }
      for (final alias in ['Published', 'published', '已发布', true]) {
        expect(
          CourseworkPublicationStatus.fromFirestore(alias).storageValue,
          'published',
        );
      }
    });

    test('missing and malformed optional fields do not crash', () {
      expect(
        () => CourseworkTheme.fromMap({
          'id': 'safe-theme',
          'title': null,
          'difficulty': 17,
          'updatedAt': {'unexpected': true},
          'questions': ['not-a-map', null],
        }),
        returnsNormally,
      );
      final theme = CourseworkTheme.fromMap({'id': 'safe-theme'});
      expect(theme.id, 'safe-theme');
      expect(theme.questions, isEmpty);
      expect(theme.difficulty, 'intermediate');
    });
  });

  group('Coursework theme filters', () {
    final themes = [
      _theme(
        id: 'theme-a',
        code: 'CW-001',
        title: 'Trading market foundations',
        titleZh: '交易市场基础',
        categoryId: 'market_foundations',
        difficulty: 'beginner',
        status: 'published',
      ),
      _theme(
        id: 'theme-b',
        code: 'CW-002',
        title: 'Advanced structure',
        titleZh: '高级市场结构',
        categoryId: 'market_structure',
        difficulty: 'advanced',
        status: 'draft',
      ),
    ];

    test('all filters return all authorized themes', () {
      expect(
        CourseworkThemeFilter.apply(
          themes,
          categoryId: 'all',
          difficulty: 'all',
          publicationStatus: 'all',
        ),
        hasLength(2),
      );
    });

    test('canonical category and enum filters work together', () {
      final filtered = CourseworkThemeFilter.apply(
        themes,
        categoryId: 'market_foundations',
        difficulty: 'Beginner',
        publicationStatus: '已发布',
      );
      expect(filtered.map((item) => item.id), ['theme-a']);
    });

    test('search covers code, localized title, and partial text', () {
      expect(
        CourseworkThemeFilter.apply(themes, search: 'CW-001'),
        hasLength(1),
      );
      expect(CourseworkThemeFilter.apply(themes, search: '市场结构'), hasLength(1));
      expect(
        CourseworkThemeFilter.apply(themes, search: 'found'),
        hasLength(1),
      );
    });

    test('assignment eligibility excludes inactive, draft, and archived', () {
      final candidates = [
        ...themes,
        _theme(id: 'inactive', isActive: false),
        _theme(id: 'archived', status: 'archived'),
      ];
      expect(
        CourseworkThemeFilter.apply(
          candidates,
          includeInactive: false,
          assignableOnly: true,
        ).map((item) => item.id),
        ['theme-a'],
      );
    });
  });

  group('legacy assignment relationship resolution', () {
    final themes = [
      _theme(id: 'theme-a', code: 'CW-001', title: 'Market basics'),
      _theme(id: 'theme-b', code: 'CW-002', title: 'Market structure'),
    ];

    test('canonical themeId has priority', () {
      final assignment = CourseworkAssignment.fromMap({
        'id': 'assignment-a',
        'themeId': 'theme-b',
        'themeCode': 'CW-001',
      });
      expect(assignment.resolveThemeId(themes), 'theme-b');
    });

    test('legacy courseworkThemeId and unique code restore selection', () {
      expect(
        CourseworkAssignment.fromMap({
          'courseworkThemeId': 'theme-a',
        }).resolveThemeId(themes),
        'theme-a',
      );
      expect(
        CourseworkAssignment.fromMap({
          'themeCode': 'cw-002',
        }).resolveThemeId(themes),
        'theme-b',
      );
    });

    test('ambiguous title is never resolved automatically', () {
      final duplicates = [
        ...themes,
        _theme(id: 'theme-c', title: 'Market basics'),
      ];
      final assignment = CourseworkAssignment.fromMap({
        'themeName': 'Market basics',
      });
      expect(assignment.resolveThemeId(duplicates), isNull);
    });
  });

  testWidgets('searchable selector displays and returns a stable theme ID', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1200, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    String? selectedId;
    await tester.pumpWidget(
      _app(
        Scaffold(
          body: CourseworkThemeSelector(
            themes: [
              _theme(id: 'theme-a', code: 'CW-001', title: 'Market basics'),
              _theme(id: 'theme-b', code: 'CW-099', title: 'Risk planning'),
            ],
            selectedThemeId: null,
            onSelected: (value) => selectedId = value,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Choose a theme'));
    await tester.pumpAndSettle();
    expect(find.text('Select Coursework theme'), findsOneWidget);
    expect(find.text('2 eligible theme(s)'), findsOneWidget);
    expect(
      find.text('Risk planning'),
      findsOneWidget,
      reason: tester
          .widgetList<Text>(find.byType(Text))
          .map((widget) => widget.data)
          .whereType<String>()
          .join(' | '),
    );
    await tester.enterText(find.byType(SearchBar), 'CW-099');
    tester.testTextInput.hide();
    await tester.pumpAndSettle();
    expect(find.text('Risk planning'), findsOneWidget);
    expect(find.text('Market basics'), findsNothing);
    await tester.tap(find.text('Risk planning'));
    await tester.pumpAndSettle();
    expect(selectedId, 'theme-b');
  });

  testWidgets('unavailable legacy selection is shown without crashing', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(
        Scaffold(
          body: CourseworkThemeSelector(
            themes: [_theme(id: 'theme-a')],
            selectedThemeId: 'archived-theme',
            onSelected: (_) {},
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.text('Previously selected theme unavailable'), findsOneWidget);
    expect(find.text('archived-theme'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

CourseworkTheme _theme({
  required String id,
  String code = 'CW-001',
  String title = 'Published theme',
  String titleZh = '已发布主题',
  String categoryId = 'market_foundations',
  String difficulty = 'beginner',
  String status = 'published',
  bool isActive = true,
  bool isAssignable = true,
}) => CourseworkTheme(
  id: id,
  code: code,
  title: title,
  titleI18n: {'zh': titleZh},
  categoryId: categoryId,
  category: CourseworkTaxonomy.find(categoryId)?.en ?? categoryId,
  categoryI18n: {'zh': CourseworkTaxonomy.find(categoryId)?.zh ?? categoryId},
  difficulty: difficulty,
  status: status,
  isActive: isActive,
  isAssignable: isAssignable,
  questions: const [],
);

Widget _app(Widget home) => MaterialApp(
  locale: const Locale('en'),
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: home,
);
