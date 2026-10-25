import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/auth/forgot_password_dialog.dart';
import 'package:hc_trader/repositories/auth_repository.dart';

void main() {
  group('password reset email validation', () {
    test('normalizes an address once at the boundary', () {
      expect(
        normalizePasswordResetEmail('  Trader+Course@EXAMPLE.COM  '),
        'trader+course@example.com',
      );
    });

    test('accepts ordinary and plus-addressed email addresses', () {
      expect(validatePasswordResetEmail('trader@example.com'), isNull);
      expect(validatePasswordResetEmail('a.b+desk@sub.example.com'), isNull);
    });

    test('rejects empty, pasted lists, newlines, and malformed domains', () {
      expect(
        validatePasswordResetEmail('   '),
        PasswordResetEmailValidation.empty,
      );
      for (final value in [
        'one@example.com,two@example.com',
        'one@example.com\ntwo@example.com',
        'trader@example',
        'trader@@example.com',
        '.trader@example.com',
        'trader@example..com',
      ]) {
        expect(
          validatePasswordResetEmail(value),
          PasswordResetEmailValidation.invalid,
          reason: value,
        );
      }
    });
  });

  test('maps reset failures without exposing account existence', () {
    expect(isPasswordResetEnumerationSafeCode('user-not-found'), isTrue);
    expect(isPasswordResetEnumerationSafeCode('EMAIL_NOT_FOUND'), isTrue);
    expect(isPasswordResetEnumerationSafeCode('user-disabled'), isTrue);
    expect(
      passwordResetFailureKindForCode('too-many-requests'),
      PasswordResetFailureKind.rateLimited,
    );
    expect(
      passwordResetFailureKindForCode('network-request-failed'),
      PasswordResetFailureKind.network,
    );
    expect(
      passwordResetFailureKindForCode('operation-not-allowed'),
      PasswordResetFailureKind.configuration,
    );
  });

  testWidgets(
    'validates, normalizes, blocks duplicate submission, and throttles resend',
    (tester) async {
      final firstRequest = Completer<void>();
      final submitted = <String>[];
      await _pumpDialog(
        tester,
        cooldown: const Duration(seconds: 1),
        send: (email) {
          submitted.add(email);
          return submitted.length == 1 ? firstRequest.future : Future.value();
        },
      );

      await tester.tap(find.byKey(const Key('password-reset-submit')));
      await tester.pump();
      expect(find.text('Enter your email address.'), findsOneWidget);

      await tester.enterText(
        find.byKey(const Key('password-reset-email')),
        '  Trader+Course@EXAMPLE.COM  ',
      );
      await tester.tap(find.byKey(const Key('password-reset-submit')));
      await tester.pump();
      expect(submitted, ['trader+course@example.com']);

      await tester.tap(
        find.byKey(const Key('password-reset-submit')),
        warnIfMissed: false,
      );
      await tester.pump();
      expect(submitted, hasLength(1));

      firstRequest.complete();
      await tester.pump();
      expect(find.text('Check your inbox'), findsOneWidget);
      expect(
        find.text(
          'If an account exists for this email, a password reset link will be sent shortly.',
        ),
        findsOneWidget,
      );
      expect(find.text('Password reset link sent.'), findsNothing);

      expect(
        tester
            .widget<OutlinedButton>(
              find.byKey(const Key('password-reset-resend')),
            )
            .onPressed,
        isNull,
      );
      await tester.pump(const Duration(seconds: 1));
      await tester.tap(find.byKey(const Key('password-reset-resend')));
      await tester.pump();
      expect(submitted, hasLength(2));

      await tester.pumpWidget(const SizedBox.shrink());
    },
  );

  testWidgets('renders the reset success state in Simplified Chinese', (
    tester,
  ) async {
    await _pumpDialog(
      tester,
      locale: const Locale('zh'),
      cooldown: Duration.zero,
      send: (_) async {},
    );
    await tester.enterText(
      find.byKey(const Key('password-reset-email')),
      'trader@example.com',
    );
    await tester.tap(find.byKey(const Key('password-reset-submit')));
    await tester.pump();

    expect(find.text('请检查您的收件箱'), findsOneWidget);
    expect(find.text('如果该邮箱对应有效账户，密码重置链接将很快发送。'), findsOneWidget);
    expect(find.text('返回登录'), findsOneWidget);
  });

  testWidgets('shows a safe localized network failure', (tester) async {
    await _pumpDialog(
      tester,
      locale: const Locale('zh'),
      send: (_) =>
          throw const PasswordResetFailure(PasswordResetFailureKind.network),
    );
    await tester.enterText(
      find.byKey(const Key('password-reset-email')),
      'trader@example.com',
    );
    await tester.tap(find.byKey(const Key('password-reset-submit')));
    await tester.pump();

    expect(find.text('无法连接 Firebase，请检查网络后重试。'), findsOneWidget);
  });
}

Future<void> _pumpDialog(
  WidgetTester tester, {
  required Future<void> Function(String email) send,
  Locale locale = const Locale('en'),
  Duration cooldown = const Duration(seconds: 45),
}) async {
  await tester.pumpWidget(
    MaterialApp(
      locale: locale,
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: Scaffold(
        body: ForgotPasswordDialog(sendPasswordReset: send, cooldown: cooldown),
      ),
    ),
  );
  await tester.pump();
}
