import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/localization/publishing_localization.dart';
import 'package:hc_trader/core/security/permission_service.dart';
import 'package:hc_trader/core/security/publishing_authorization.dart';
import 'package:hc_trader/features/hub/publisher_validation.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/market_analysis_item.dart';
import 'package:hc_trader/models/trading_signal_item.dart';
import 'package:hc_trader/shared/content_language_selector.dart';

void main() {
  AppUser user(
    int roleId, {
    Map<String, bool> permissionOverrides = const {},
    bool suspended = false,
  }) => AppUser(
    uid: 'user-$roleId',
    email: 'user$roleId@example.test',
    roleId: roleId,
    permissionOverrides: permissionOverrides,
    isSuspended: suspended,
  );

  group('publisher authorization', () {
    test('Super Admin can publish and manage both modules', () {
      final policy = PublishingAuthorization(user(1));
      expect(policy.canPublishTradingSignal, isTrue);
      expect(policy.canManageTradingSignals, isTrue);
      expect(policy.canPublishMarketAnalysis, isTrue);
      expect(policy.canManageMarketAnalysis, isTrue);
    });

    test('specialist roles are limited to their own module', () {
      expect(
        PublishingAuthorization(user(201)).canPublishTradingSignal,
        isTrue,
      );
      expect(
        PublishingAuthorization(user(501)).canPublishTradingSignal,
        isFalse,
      );
      expect(
        PublishingAuthorization(user(501)).canPublishMarketAnalysis,
        isTrue,
      );
      expect(
        PublishingAuthorization(user(201)).canPublishMarketAnalysis,
        isFalse,
      );
    });

    test('ordinary, missing-role, and suspended users cannot publish', () {
      for (final candidate in [
        user(750),
        user(0),
        user(201, suspended: true),
      ]) {
        final policy = PublishingAuthorization(candidate);
        expect(policy.canPublishTradingSignal, isFalse);
        expect(policy.canPublishMarketAnalysis, isFalse);
      }
    });

    test('Sub Admin requires an explicit publisher permission', () {
      expect(PublishingAuthorization(user(2)).canPublishTradingSignal, isFalse);
      expect(
        PublishingAuthorization(
          user(2, permissionOverrides: {AppPermission.signalsPublishOwn: true}),
        ).canPublishTradingSignal,
        isTrue,
      );
    });

    test('explicit deny takes precedence, including for Super Admin', () {
      final policy = PublishingAuthorization(
        user(
          1,
          permissionOverrides: {
            AppPermission.signalsPublishOwn: false,
            AppPermission.signalsManageAll: false,
          },
        ),
      );
      expect(policy.canPublishTradingSignal, isFalse);
    });

    test('role parser accepts numeric strings and multi-role profiles', () {
      final parsed = AppUser.fromJson({
        'id': 'fresh-server-profile',
        'email': 'admin@example.test',
        'role_id': '750',
        'role_ids': ['750', '1'],
      });
      expect(parsed.effectiveRoleIds, containsAll([750, 1]));
      expect(PublishingAuthorization(parsed).canPublishMarketAnalysis, isTrue);
    });
  });

  group('publisher localization', () {
    test('all Chinese locale variants select Simplified Chinese', () {
      for (final locale in const ['zh', 'zh_CN', 'zh-SG', 'zh_Hans']) {
        expect(
          publishingLanguageForLocale(locale),
          PublishingLanguage.simplifiedChinese,
          reason: locale,
        );
      }
      expect(publishingLanguageForLocale('en_SG'), PublishingLanguage.english);
    });

    test('only the selected primary language is required', () {
      expect(
        hasCompletePrimaryLocalization(
          language: PublishingLanguage.simplifiedChinese,
          requiredFields: [(english: '', chinese: '黄金分析')],
        ),
        isTrue,
      );
      expect(
        hasCompletePrimaryLocalization(
          language: PublishingLanguage.english,
          requiredFields: [(english: '', chinese: '黄金分析')],
        ),
        isFalse,
      );
    });

    test('language metadata reflects the translations actually supplied', () {
      final title = publishingLocalizedMap(english: 'Gold setup', chinese: '');
      final plan = publishingLocalizedMap(
        english: 'Wait for confirmation',
        chinese: '等待确认',
      );
      expect(title, {'en': 'Gold setup'});
      expect(publishingAvailableLanguages([title, plan]), ['en', 'zh']);
    });

    test(
      'legacy and localized signal records remain readable with fallback',
      () {
        final legacy = TradingSignalItem.fromJson({
          'id': 'legacy',
          'title': 'Legacy signal',
          'status': 1,
        });
        expect(legacy.localizedTitle('zh'), 'Legacy signal');

        final localized = TradingSignalItem.fromJson({
          'id': 'localized',
          'signal_title': '黄金做多',
          'signal_title_i18n': {'zh': '黄金做多'},
          'primary_language': 'zh',
          'available_languages': ['zh'],
          'status': 1,
        });
        expect(localized.localizedTitle('en'), '黄金做多');
        expect(localized.primaryLanguage, 'zh');
        expect(localized.availableLanguages, ['zh']);
      },
    );

    test('localized analysis uses the available language as fallback', () {
      final analysis = MarketAnalysisItem.fromJson({
        'id': 'analysis',
        'title': '黄金前景',
        'title_i18n': {'zh': '黄金前景'},
        'market_overview': '震荡上行',
        'trading_plan': '等待回调',
        'primary_language': 'zh',
        'available_languages': ['zh'],
      });
      expect(analysis.localizedTitle('en'), '黄金前景');
      expect(analysis.toFirestore()['primary_language'], 'zh');
    });
  });

  group('trading signal validation and statuses', () {
    test('validates BUY and SELL price relationships', () {
      expect(
        validateTradingSignalPrices(
          action: 'BUY',
          entry: '2300',
          stopLoss: '2290',
          targets: const ['2310', '2320'],
        ),
        isNull,
      );
      expect(
        validateTradingSignalPrices(
          action: 'SELL',
          entry: '2300',
          stopLoss: '2290',
          targets: const ['2280'],
        ),
        TradingSignalValidationIssue.sellStopLoss,
      );
    });

    test('maps canonical lifecycle statuses to legacy numeric values', () {
      expect(TradingSignalPublicationStatus.active.legacyCode, 1);
      expect(TradingSignalPublicationStatus.cancelled.legacyCode, 12);
      expect(TradingSignalPublicationStatus.fromValue('closed').legacyCode, 13);
      expect(MarketAnalysisPublicationStatus.published.value, 'published');
    });
  });

  testWidgets('language selector switches between English and Chinese', (
    tester,
  ) async {
    var selected = PublishingLanguage.english;
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('en'),
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        supportedLocales: AppLocalizations.supportedLocales,
        home: Scaffold(
          body: StatefulBuilder(
            builder: (context, setState) => ContentLanguageSelector(
              primaryLanguage: selected,
              onChanged: (value) => setState(() => selected = value),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Content Language'), findsOneWidget);
    final selector = tester.widget<ContentLanguageSelector>(
      find.byType(ContentLanguageSelector),
    );
    selector.onChanged(PublishingLanguage.simplifiedChinese);
    await tester.pump();
    expect(selected, PublishingLanguage.simplifiedChinese);
  });
}
