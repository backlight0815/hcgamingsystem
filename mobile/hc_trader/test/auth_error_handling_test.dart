import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/auth/auth_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/shared/app_feedback.dart';

void main() {
  test('raw unavailable status is converted to a friendly auth message', () {
    expect(
      AppFeedback.messageFor('UNAVAILABLE'),
      contains('Service temporarily unavailable'),
    );
    expect(
      AppFeedback.messageFor(
        FirebaseException(
          plugin: 'cloud_firestore',
          code: 'unavailable',
          message: 'UNAVAILABLE',
        ),
      ),
      contains('Service temporarily unavailable'),
    );
  });

  test('generic internal errors are never shown verbatim', () {
    expect(
      AppFeedback.messageFor(
        const AuthFailure('An internal error has occurred.'),
      ),
      'Something went wrong. Please try again or contact support.',
    );
  });

  test(
    'raw Firestore permission strings are converted to a friendly message',
    () {
      expect(
        AppFeedback.messageFor(
          '[cloud_firestore/permission-denied] The caller does not have permission to execute the specified operation.',
        ),
        'Your account does not have permission to complete this request.',
      );
    },
  );

  test('referral codes normalize whitespace and full-width characters', () {
    expect(normalizeReferralCode('  ＥＳＴＨＫＹ ９９０８  '), 'esthky9908');
  });

  testWidgets('referral input exists only for signed-out registration', (
    tester,
  ) async {
    await _pumpAuth(
      tester,
      _AuthScreenRepository(),
      initialReferralCode: 'INVITED-01',
    );
    expect(find.text('Referral code (optional)'), findsOneWidget);

    await _pumpAuth(
      tester,
      _AuthScreenRepository(
        current: const AppUser(
          uid: 'existing-user',
          email: 'existing@example.com',
        ),
      ),
      initialReferralCode: 'MUST-NOT-APPLY',
    );
    await tester.tap(find.text('Register'));
    await tester.pumpAndSettle();
    expect(find.text('Referral code (optional)'), findsNothing);
    expect(find.textContaining('MUST-NOT-APPLY'), findsNothing);
  });
}

Future<void> _pumpAuth(
  WidgetTester tester,
  AuthRepository repository, {
  required String initialReferralCode,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: AuthScreen(
        repository: repository,
        firebaseEnabled: true,
        initialReferralCode: initialReferralCode,
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _AuthScreenRepository implements AuthRepository {
  _AuthScreenRepository({this.current});

  final AppUser? current;

  @override
  AppUser? get currentUser => current;

  @override
  Future<List<RegistrationRole>> registrationRoles() async => const [
    RegistrationRole(id: 750, name: 'Trader', description: 'Trader'),
  ];

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
