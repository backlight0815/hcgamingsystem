import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/features/profile/profile_screen.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/referral_relationship.dart';
import 'package:hc_trader/repositories/auth_repository.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/referral_relationship_service.dart';

void main() {
  group('authoritative upline relationship', () {
    test('parses every explicit safe state and exposes only display name', () {
      const cases = {
        'available': UplineRelationshipStatus.available,
        'unavailable': UplineRelationshipStatus.unavailable,
        'missingRecord': UplineRelationshipStatus.missingRecord,
        'conflict': UplineRelationshipStatus.conflict,
        'noUpline': UplineRelationshipStatus.noUpline,
      };

      for (final entry in cases.entries) {
        final relationship = UplineRelationship.fromMap({
          'status': entry.key,
          'displayName': '  Public Name  ',
          'uplineUid': 'must-not-be-modeled',
          'email': 'must-not-be-modeled@example.com',
        });
        expect(relationship.status, entry.value);
        expect(relationship.displayName, 'Public Name');
      }
    });

    test('caches concurrent and repeated reads', () async {
      var reads = 0;
      final pending = Completer<UplineRelationship>();
      final service = ReferralRelationshipService(
        loader: () {
          reads += 1;
          return pending.future;
        },
      );

      final first = service.load();
      final second = service.load();
      expect(identical(first, second), isTrue);
      expect(reads, 1);
      pending.complete(
        const UplineRelationship(
          status: UplineRelationshipStatus.available,
          displayName: 'Upline',
        ),
      );
      expect((await first).displayName, 'Upline');
      expect((await service.load()).displayName, 'Upline');
      expect(reads, 1);
    });

    test(
      'refresh and invalidation resolve the current relationship again',
      () async {
        var reads = 0;
        final service = ReferralRelationshipService(
          loader: () async {
            reads += 1;
            return UplineRelationship(
              status: UplineRelationshipStatus.available,
              displayName: 'Upline $reads',
            );
          },
        );

        expect((await service.load()).displayName, 'Upline 1');
        expect((await service.load(refresh: true)).displayName, 'Upline 2');
        service.invalidate();
        expect((await service.load()).displayName, 'Upline 3');
      },
    );

    testWidgets('Profile renders a read-only public name without identifiers', (
      tester,
    ) async {
      final service = ReferralRelationshipService(
        loader: () async => const UplineRelationship(
          status: UplineRelationshipStatus.available,
          displayName: 'Public Upline',
        ),
      );
      await tester.pumpWidget(
        MaterialApp(
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          home: ProfileScreen(
            user: const AppUser(uid: 'trader-1', email: 'trader@example.com'),
            repository: _ProfileAuthRepository(),
            uploadRepository: TradingRepository(firebaseEnabled: false),
            referralService: service,
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const ValueKey('profile-upline')), findsOneWidget);
      expect(find.text('Public Upline'), findsOneWidget);
      expect(find.textContaining('must-not-be-modeled'), findsNothing);
      expect(
        find.byWidgetPredicate(
          (widget) =>
              widget is TextField && widget.decoration?.labelText == 'Upline',
        ),
        findsNothing,
      );
    });
  });
}

class _ProfileAuthRepository implements AuthRepository {
  @override
  Stream<AppUser?> authStateChanges() => const Stream<AppUser?>.empty();

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}
