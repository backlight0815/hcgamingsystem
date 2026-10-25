import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/journal/journal_screen.dart';
import 'package:hc_trader/features/profile/profile_screen.dart';
import 'package:hc_trader/models/trading_pair.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/shared/financial_pairing_dropdown.dart';

void main() {
  test(
    'profile text save continues when the optional photo upload fails',
    () async {
      var profileSaveCalls = 0;
      final outcome = await saveProfileWithOptionalPhoto(
        selectedAvatar: PlatformFile(
          name: 'avatar.png',
          size: 3,
          bytes: Uint8List.fromList(const [1, 2, 3]),
        ),
        upload: (_) async => throw Exception('upload unavailable'),
        save: (photoUrl) async {
          profileSaveCalls += 1;
          expect(photoUrl, isNull);
          return const ProfileUpdateResult(emailVerificationSent: false);
        },
      );

      expect(profileSaveCalls, 1);
      expect(outcome.avatarUploadFailed, isTrue);
    },
  );

  test(
    'financial pairing options normalize, deduplicate and retain legacy',
    () {
      final options = financialPairingOptions(const [
        TradingPair(id: 'gold', symbol: ' XAUUSD ', description: 'Gold'),
        TradingPair(id: 'gold-duplicate', symbol: 'xauusd'),
        TradingPair(id: 'euro', symbol: 'EURUSD'),
      ], legacyValue: ' old pair ');

      expect(options.map((item) => item.symbol), [
        'OLD PAIR',
        'EURUSD',
        'XAUUSD',
      ]);
      expect(options.first.isLegacy, isTrue);
      expect(financialPairingSelection(options, ' xauusd '), 'XAUUSD');
    },
  );

  test('journal pairing filter includes configured and recorded symbols', () {
    final options = journalFinancialPairingOptions(
      pairs: const [
        TradingPair(id: 'gold', symbol: 'xauusd'),
        TradingPair(id: 'euro', symbol: 'EURUSD'),
      ],
      journalPairings: const [' XAUUSD ', 'eurusd.pro', 'GBPUSD'],
      selected: ' legacy.cfd ',
    );

    expect(options, ['EURUSD', 'EURUSD.PRO', 'GBPUSD', 'LEGACY.CFD', 'XAUUSD']);
  });

  test('journal pairing filter performs normalized exact matching', () {
    expect(journalMatchesFinancialPairing(' xauusd ', 'XAUUSD'), isTrue);
    expect(journalMatchesFinancialPairing('XAUUSD.PRO', 'XAUUSD'), isFalse);
    expect(journalMatchesFinancialPairing('EURUSD', null), isTrue);
  });

  testWidgets(
    'financial pairing dropdown validates and saves a stable symbol',
    (tester) async {
      String? selected;
      final formKey = GlobalKey<FormState>();
      await tester.pumpWidget(
        _app(
          Form(
            key: formKey,
            child: FinancialPairingDropdown(
              pairs: TradingPair.defaults,
              value: null,
              onChanged: (value) => selected = value,
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(formKey.currentState!.validate(), isFalse);
      await tester.pump();
      expect(find.text('Financial pairing is required'), findsOneWidget);
      await tester.tap(
        find.byKey(const ValueKey('financial-pairing-dropdown')),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('XAUUSD - Gold').last);
      await tester.pumpAndSettle();

      expect(selected, 'XAUUSD');
      expect(formKey.currentState!.validate(), isTrue);
    },
  );

  testWidgets(
    'unsupported legacy financial pairing is shown without crashing',
    (tester) async {
      final formKey = GlobalKey<FormState>();
      await tester.pumpWidget(
        _app(
          Form(
            key: formKey,
            child: FinancialPairingDropdown(
              pairs: TradingPair.defaults,
              value: 'LEGACY.CFD',
              onChanged: (_) {},
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('LEGACY.CFD (legacy value)'), findsOneWidget);
      expect(formKey.currentState!.validate(), isFalse);
      await tester.pump();
      expect(find.text('Choose a supported financial pairing'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}

Widget _app(Widget home) => MaterialApp(
  supportedLocales: AppLocalizations.supportedLocales,
  localizationsDelegates: const [
    AppLocalizations.delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
  ],
  home: Scaffold(body: home),
);
