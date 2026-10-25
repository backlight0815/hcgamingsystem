import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/localization/localized_value.dart';
import 'package:hc_trader/features/hub/knowledge_centre_library.dart';
import 'package:hc_trader/models/knowledge_centre_item.dart';
import 'package:hc_trader/repositories/trading_repository.dart';

void main() {
  test('knowledge centre seed contains exactly 30 localized modules', () {
    final modules = _seedModules();
    final categories = <String, int>{};
    final ids = <String>{};
    var imageCount = 0;

    for (final module in modules) {
      final id = module['knowledgeCenterId'] as String;
      expect(ids.add(id), isTrue, reason: id);
      final category = module['category'] as String;
      categories[category] = (categories[category] ?? 0) + 1;
      expect(module['contentVersion'], 2, reason: id);
      expect(module['qualityStatus'], 'production_ready', reason: id);

      final locales = Map<String, dynamic>.from(module['locales'] as Map);
      expect(locales['en'], isA<Map>());
      expect(locales['zh'], isA<Map>());
      final en = Map<String, dynamic>.from(locales['en'] as Map);
      final zh = Map<String, dynamic>.from(locales['zh'] as Map);
      expect(en['title'], isNotEmpty);
      expect(zh['title'], isNotEmpty);
      expect(_wordCount(en['fullContent']), greaterThanOrEqualTo(900));
      expect(_cjkCount(zh['fullContent']), greaterThanOrEqualTo(650));
      for (final locale in [en, zh]) {
        expect(locale['beginnerExplanation'], isNotEmpty, reason: id);
        expect(locale['practicalExplanation'], isNotEmpty, reason: id);
        expect(
          locale['learningObjectives'],
          hasLength(greaterThanOrEqualTo(3)),
        );
        expect(locale['stepByStepGuide'], hasLength(greaterThanOrEqualTo(4)));
        expect(locale['keyPoints'], hasLength(greaterThanOrEqualTo(5)));
        expect(locale['examples'], hasLength(greaterThanOrEqualTo(3)));
        expect(locale['commonMistakes'], hasLength(greaterThanOrEqualTo(4)));
        expect(locale['checklist'], hasLength(greaterThanOrEqualTo(5)));
        expect(locale['relatedConcepts'], hasLength(greaterThanOrEqualTo(3)));
      }

      final images = module['imageSections'] as List;
      expect(images.length, greaterThanOrEqualTo(2));
      if (category == 'technical') {
        expect(images.length, inInclusiveRange(3, 5), reason: id);
      }
      imageCount += images.length;
      for (final image in images.whereType<Map>()) {
        final assetPath = image['assetPath'] as String;
        final asset = File(assetPath);
        expect(asset.existsSync(), isTrue, reason: assetPath);
        expect(image['concept'], isNotEmpty, reason: assetPath);
        expect(image['qualityStatus'], 'production_ready', reason: assetPath);
        expect(asset.readAsStringSync(), contains('viewBox="0 0 1600 900"'));
        expect(image['locales']['en']['caption'], isNotEmpty);
        expect(image['locales']['zh']['caption'], isNotEmpty);
      }
    }

    expect(modules, hasLength(30));
    expect(categories, {'theory': 9, 'technical': 15, 'psychology': 6});
    expect(imageCount, 106);
  });

  test('SNR module includes required support resistance diagrams', () {
    final snr = _seedModules().singleWhere(
      (module) => module['knowledgeCenterId'] == 'KC_TECHNICAL_001',
    );
    final titles = (snr['imageSections'] as List)
        .whereType<Map>()
        .map((image) => image['locales']['en']['title'].toString())
        .join('\n');

    expect(titles, contains('Support zone'));
    expect(titles, contains('Resistance zone'));
    expect(titles, contains('Support becomes resistance'));
    expect(titles, contains('Resistance becomes support'));
    expect(titles, contains('Body and wick key level zone'));
  });

  test('knowledge centre seed uses required production image concepts', () {
    final conceptsByModule = {
      for (final module in _seedModules())
        module['knowledgeCenterId'] as String: (module['imageSections'] as List)
            .whereType<Map>()
            .map((image) => image['concept'].toString())
            .toSet(),
    };

    expect(conceptsByModule['KC_THEORY_003'], contains('spread_bid_ask'));
    expect(conceptsByModule['KC_TECHNICAL_001'], contains('support_zone'));
    expect(conceptsByModule['KC_TECHNICAL_001'], contains('resistance_zone'));
    expect(conceptsByModule['KC_TECHNICAL_005'], contains('bullish_bos'));
    expect(conceptsByModule['KC_TECHNICAL_005'], contains('bearish_bos'));
    expect(conceptsByModule['KC_TECHNICAL_005'], contains('choch_reversal'));
    expect(conceptsByModule['KC_TECHNICAL_013'], contains('range_high_low'));
  });

  test('knowledge centre model and helper parse localized lesson content', () {
    final raw = _seedModules().first;
    final item = KnowledgeCentreItemMobile.fromJson(raw);
    final zh = getLocalizedKnowledgeCentre(raw, 'zh_Hans');

    expect(item.knowledgeCenterId, 'KC_THEORY_001');
    expect(item.contentVersion, 2);
    expect(item.qualityStatus, 'production_ready');
    expect(item.localizedTitle('en'), raw['locales']['en']['title']);
    expect(item.localizedTitle('zh_CN'), raw['locales']['zh']['title']);
    expect(item.localizedLearningObjectives('en'), isNotEmpty);
    expect(item.localizedBeginnerExplanation('en'), isNotEmpty);
    expect(item.localizedPracticalExplanation('zh_CN'), isNotEmpty);
    expect(item.localizedStepByStepGuide('zh_MY'), isNotEmpty);
    expect(item.localizedRelatedConcepts('en'), isNotEmpty);
    expect(item.imageSections, hasLength(3));
    expect(item.imageSections.first.concept, isNotEmpty);
    expect(item.imageSections.first.qualityStatus, 'production_ready');
    expect(item.localizedKeyPoints('zh_MY'), isNotEmpty);
    expect(zh.title, raw['locales']['zh']['title']);
    expect(zh.imageSections.first.concept, item.imageSections.first.concept);
    expect(zh.imageSections.first.caption, isNotEmpty);
    expect(zh.missingTranslations, isEmpty);
  });

  testWidgets('knowledge centre screen renders English content and SVG', (
    tester,
  ) async {
    final modules = _seedItems();
    final first = modules.first;
    final languageCode = 'en';
    final imageCaption = first.imageSections.first.localizedCaption(
      languageCode,
    );

    await tester.pumpWidget(
      _knowledgeApp(
        locale: const Locale('en'),
        repository: _FakeKnowledgeRepository(modules),
      ),
    );
    await _pumpKnowledge(tester);

    expect(find.text('Trading Knowledge Centre'), findsOneWidget);
    expect(find.text('View Lesson'), findsWidgets);
    expect(find.text(first.localizedTitle(languageCode)), findsWidgets);

    await tester.tap(find.text(first.localizedTitle(languageCode)).last);
    await _pumpKnowledge(tester);

    await tester.scrollUntilVisible(
      find.text(imageCaption),
      320,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text(imageCaption), findsOneWidget);
    expect(find.byType(SvgPicture), findsWidgets);
    await tester.scrollUntilVisible(
      find.text('Learning Objectives'),
      320,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Learning Objectives'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Beginner Explanation'),
      320,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Beginner Explanation'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Practical Trading Explanation'),
      320,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Practical Trading Explanation'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Lesson content'),
      320,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Lesson content'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Step-by-Step Guide'),
      420,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Step-by-Step Guide'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Key Points'),
      420,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Key Points'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Examples'),
      420,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Examples'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Common Mistakes'),
      420,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Common Mistakes'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Checklist'),
      420,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Checklist'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Related Concepts'),
      420,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(find.text('Related Concepts'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('knowledge centre category filter shows technical modules', (
    tester,
  ) async {
    final modules = _seedItems();
    final theoryTitle = modules
        .firstWhere((item) => item.category == 'theory')
        .localizedTitle('en');
    final technicalTitle = modules
        .firstWhere((item) => item.category == 'technical')
        .localizedTitle('en');

    await tester.pumpWidget(
      _knowledgeApp(
        locale: const Locale('en'),
        repository: _FakeKnowledgeRepository(modules),
      ),
    );
    await _pumpKnowledge(tester);

    expect(find.text(theoryTitle), findsWidgets);
    await tester.tap(find.text('Technical'));
    await _pumpKnowledge(tester);

    expect(find.text(theoryTitle), findsNothing);
    expect(find.text(technicalTitle), findsWidgets);
    expect(find.text('View Lesson'), findsWidgets);
  });

  testWidgets('new stable category filters include the required markets', (
    tester,
  ) async {
    final modules = [
      KnowledgeCentreItemMobile.fromJson({
        'id': 'stocks-1',
        'title': 'Stocks lesson',
        'description': 'Stocks content',
        'category': 'Stock',
      }),
      KnowledgeCentreItemMobile.fromJson({
        'id': 'futures-1',
        'title': 'Futures lesson',
        'description': 'Futures content',
        'category': 'Trading Future',
      }),
    ];
    await tester.pumpWidget(
      _knowledgeApp(
        locale: const Locale('en'),
        repository: _FakeKnowledgeRepository(modules),
      ),
    );
    await _pumpKnowledge(tester);

    expect(find.widgetWithText(ChoiceChip, 'Stocks'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Futures Trading'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'Investment'), findsOneWidget);
    expect(find.widgetWithText(ChoiceChip, 'CFD Trading'), findsOneWidget);
    await tester.tap(find.widgetWithText(ChoiceChip, 'Stocks'));
    await _pumpKnowledge(tester);
    expect(find.text('Stocks lesson'), findsWidgets);
    expect(find.text('Futures lesson'), findsNothing);
  });

  testWidgets('knowledge centre missing image shows localized placeholder', (
    tester,
  ) async {
    final raw = Map<String, dynamic>.from(_seedModules().first);
    final images = (raw['imageSections'] as List)
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    images[0] = {
      ...images.first,
      'assetPath': '',
      'storagePath': '',
      'imageUrl': '',
    };
    raw['imageSections'] = images;
    final item = KnowledgeCentreItemMobile.fromJson(raw);

    await tester.pumpWidget(_detailApp(locale: const Locale('en'), item: item));
    await _pumpKnowledge(tester);

    expect(find.text('Image unavailable'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('knowledge centre detail scrolls on small mobile viewport', (
    tester,
  ) async {
    final originalSize = tester.view.physicalSize;
    final originalPixelRatio = tester.view.devicePixelRatio;
    tester.view.physicalSize = const Size(360, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.physicalSize = originalSize;
      tester.view.devicePixelRatio = originalPixelRatio;
    });

    final item = _seedItems().first;
    await tester.pumpWidget(_detailApp(locale: const Locale('en'), item: item));
    await _pumpKnowledge(tester);

    expect(find.text(item.localizedTitle('en')), findsWidgets);
    await tester.drag(find.byType(ListView).last, const Offset(0, -900));
    await _pumpKnowledge(tester);

    expect(tester.takeException(), isNull);
  });

  testWidgets('knowledge centre screen renders Chinese content only', (
    tester,
  ) async {
    final modules = _seedItems();
    final first = modules.first;
    final zhTitle = first.localizedTitle('zh');
    final zhCaption = first.imageSections.first.localizedCaption('zh');

    await tester.pumpWidget(
      _knowledgeApp(
        locale: const Locale('zh'),
        repository: _FakeKnowledgeRepository(modules),
      ),
    );
    await _pumpKnowledge(tester);

    expect(find.text(zhTitle), findsWidgets);
    expect(find.text(first.localizedTitle('en')), findsNothing);

    await tester.tap(find.text(zhTitle).last);
    await _pumpKnowledge(tester);

    expect(find.text(zhTitle), findsWidgets);
    expect(find.text(first.localizedShortDescription('zh')), findsWidgets);
    expect(find.text(zhCaption), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text(
        AppLocalizations(const Locale('zh')).text('Learning Objectives'),
      ),
      320,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);

    expect(
      find.text(
        AppLocalizations(const Locale('zh')).text('Learning Objectives'),
      ),
      findsOneWidget,
    );
    expect(find.text('Learning Objectives'), findsNothing);
    await tester.scrollUntilVisible(
      find.text(AppLocalizations(const Locale('zh')).text('Lesson content')),
      320,
      scrollable: find.byType(Scrollable).last,
    );
    await _pumpKnowledge(tester);
    expect(
      find.text(AppLocalizations(const Locale('zh')).text('Lesson content')),
      findsOneWidget,
    );
    expect(find.text(first.localizedFullContent('en')), findsNothing);
    expect(tester.takeException(), isNull);
  });
}

List<Map<String, dynamic>> _seedModules() {
  final file = File('assets/seed/knowledge_centres_30.json');
  final raw = jsonDecode(file.readAsStringSync()) as List;
  return raw
      .map((item) => Map<String, dynamic>.from(item as Map))
      .toList(growable: false);
}

List<KnowledgeCentreItemMobile> _seedItems() {
  return _seedModules()
      .map(KnowledgeCentreItemMobile.fromJson)
      .toList(growable: false);
}

int _wordCount(Object? value) => (value?.toString() ?? '')
    .trim()
    .split(RegExp(r'\s+'))
    .where((word) => word.trim().isNotEmpty)
    .length;

int _cjkCount(Object? value) =>
    RegExp(r'[\u3400-\u9fff]').allMatches(value?.toString() ?? '').length;

Widget _knowledgeApp({
  required Locale locale,
  required _FakeKnowledgeRepository repository,
}) {
  return MaterialApp(
    locale: locale,
    supportedLocales: AppLocalizations.supportedLocales,
    localizationsDelegates: const [
      AppLocalizations.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    home: Scaffold(body: KnowledgeCentreLibrary(repository: repository)),
  );
}

Widget _detailApp({
  required Locale locale,
  required KnowledgeCentreItemMobile item,
}) {
  return MaterialApp(
    locale: locale,
    supportedLocales: AppLocalizations.supportedLocales,
    localizationsDelegates: const [
      AppLocalizations.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    home: KnowledgeCentreDetailScreen(item: item),
  );
}

Future<void> _pumpKnowledge(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 100));
  await tester.pump(const Duration(milliseconds: 100));
}

class _FakeKnowledgeRepository extends TradingRepository {
  _FakeKnowledgeRepository(this.items) : super(firebaseEnabled: false);

  final List<KnowledgeCentreItemMobile> items;

  @override
  Stream<List<KnowledgeCentreItemMobile>> knowledgeCentreStream() {
    return Stream<List<KnowledgeCentreItemMobile>>.value(items);
  }
}
