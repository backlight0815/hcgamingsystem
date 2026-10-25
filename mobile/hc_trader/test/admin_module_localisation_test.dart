import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_locale_controller.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  test('all admin module cards have Chinese translations', () {
    const localizations = AppLocalizations(Locale('zh'));
    const moduleText = <String>[
      'Trading module management',
      'Create, publish, review and archive business content.',
      'Support Tickets',
      'Reply, close and reopen client requests',
      'Appointment Slots',
      'Hosts, availability, bookings and approvals',
      'Trading Journals',
      'Review trader entries and add administration notes',
      'Exam Results',
      'Review trading and final examination attempts',
      'Disabled in feature configuration',
      'Marketing Resources',
      'Images, documents, videos and links',
      'Trading News',
      'Publish and feature market news',
      'Knowledge Centre',
      'Articles, guides and attachments',
      'Trading Signals',
      'Entries, risk and take-profit plans',
      'Market Analyst',
      'Analysis content and review workflow',
      'Recording Classes',
      'Video classes and learning materials',
      'Trading Blog',
      'Posts, categories and tags',
      'Trading Exam Questions',
      'Foundation, technique and psychology',
      'Final Examination',
      'MCQ, chart and psychology scenarios',
      'Prop Firm Config',
      'Models, phases, targets and limits',
    ];

    for (final english in moduleText) {
      expect(
        localizations.text(english),
        isNot(english),
        reason: 'Missing Chinese translation for "$english"',
      );
    }
  });

  test('fresh install follows a Chinese device locale', () async {
    SharedPreferences.setMockInitialValues({});
    final storage = await LocalAppStorage.create();
    final controller = AppLocaleController.create(
      storage,
      deviceLocales: const [Locale('zh', 'CN')],
    );
    addTearDown(controller.dispose);

    expect(controller.locale, const Locale('zh'));
  });

  test('saved language preference overrides the device locale', () async {
    SharedPreferences.setMockInitialValues({'hc_mobile_locale': 'en'});
    final storage = await LocalAppStorage.create();
    final controller = AppLocaleController.create(
      storage,
      deviceLocales: const [Locale('zh', 'CN')],
    );
    addTearDown(controller.dispose);

    expect(controller.locale, const Locale('en'));
  });
}
