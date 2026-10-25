import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/features/multi_account_prop_firm/data/prop_firm_portfolio_repository.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/account_sequence_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/domain/prop_firm_models.dart';
import 'package:hc_trader/features/multi_account_prop_firm/presentation/prop_firm_planner_controller.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('account sequence controller and persistence', () {
    late LocalAppStorage storage;
    late PropFirmPlannerController controller;
    final now = DateTime(2026, 7, 21, 9);

    setUp(() async {
      SharedPreferences.setMockInitialValues({});
      storage = await LocalAppStorage.create();
      controller = PropFirmPlannerController(
        repository: PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'sequence-user',
        ),
        now: () => now,
      );
      await controller.initialize();
      await controller.createStarterPortfolio(virtualRiskBudget: 3000);
      await controller.recalculateSequence();
    });

    tearDown(() => controller.dispose());

    test('eligible user selection stays first and survives reload', () async {
      final selected = controller.portfolio!.activeAccounts[1];
      await controller.selectSequenceStartingAccount(selected.id);
      expect(controller.sequencePlan!.steps.first.accountId, selected.id);

      final reloaded = PropFirmPlannerController(
        repository: PropFirmPortfolioRepository(
          storage: storage,
          ownerId: 'sequence-user',
        ),
        now: () => now,
      );
      await reloaded.initialize();
      expect(
        reloaded.sequenceState.settings.selectedStartingAccountId,
        selected.id,
      );
      expect(reloaded.sequencePlan!.steps.first.accountId, selected.id);
      reloaded.dispose();
    });

    test(
      'confirmation rechecks rules and starts only the first account',
      () async {
        final first = controller.sequencePlan!.steps.first.accountId;
        await controller.confirmSequence();
        expect(controller.sequencePlan!.confirmedAt, isNotNull);

        await controller.startSequenceSession();
        expect(controller.portfolio!.sessionActive, isTrue);
        expect(controller.portfolio!.activeAccountId, first);
        expect(
          controller.sequencePlan!.steps.where(
            (item) => item.status == SequenceStepStatus.active,
          ),
          hasLength(1),
        );
      },
    );

    test(
      'SL result is recorded and recalculates away from locked account',
      () async {
        await controller.confirmSequence();
        await controller.startSequenceSession();
        final first = controller.portfolio!.activeAccountId!;
        await controller.recordResult(
          RecordedTradeResult(
            id: 'sequence-sl',
            accountId: first,
            type: TradeResultType.fullSl,
            amount: -100,
            rMultiple: -1,
            resultTime: now,
          ),
        );

        expect(controller.portfolio!.results.last.id, 'sequence-sl');
        expect(
          controller.sequencePlan!.exclusions.map((item) => item.accountId),
          contains(first),
        );
        expect(controller.sequencePlan!.steps.first.accountId, isNot(first));
        expect(controller.portfolio!.sessionActive, isFalse);
      },
    );

    test('TP enters cooldown and recalculates the next candidate', () async {
      await controller.confirmSequence();
      await controller.startSequenceSession();
      final first = controller.portfolio!.activeAccountId!;
      await controller.recordResult(
        RecordedTradeResult(
          id: 'sequence-tp',
          accountId: first,
          type: TradeResultType.fullTp,
          amount: 100,
          rMultiple: 2,
          resultTime: now,
        ),
      );

      final traded = controller.portfolio!.accounts.firstWhere(
        (item) => item.id == first,
      );
      expect(traded.status, AccountStatus.coolingAfterWin);
      expect(controller.sequencePlan!.steps.first.accountId, isNot(first));
      expect(
        controller.portfolio!.disciplineRecords.last.type,
        'account_sequence_result_policy',
      );
    });

    test('configured SL portfolio stop blocks every later account', () async {
      await controller.updateSequenceSettings(
        controller.sequenceState.settings.copyWith(
          afterSlAction: SequenceAfterSlAction.stopPortfolio,
        ),
      );
      await controller.confirmSequence();
      await controller.startSequenceSession();
      final first = controller.portfolio!.activeAccountId!;
      await controller.recordResult(
        RecordedTradeResult(
          id: 'sequence-stop-sl',
          accountId: first,
          type: TradeResultType.fullSl,
          amount: -100,
          rMultiple: -1,
          resultTime: now,
        ),
      );

      expect(controller.portfolio!.portfolioLocked, isTrue);
      expect(controller.sequencePlan!.portfolioStopped, isTrue);
      expect(controller.sequencePlan!.steps, isEmpty);
    });

    test('skip and reset have an auditable, day-scoped lifecycle', () async {
      final first = controller.sequencePlan!.steps.first.accountId;
      await controller.skipSequenceAccount(
        first,
        'No valid setup this session',
      );
      expect(
        controller.sequenceState.settings.skippedAccountReasons[first],
        'No valid setup this session',
      );
      expect(
        controller.portfolio!.disciplineRecords.last.type,
        'account_sequence_skip',
      );

      await controller.resetToday();
      expect(controller.sequenceState.settings.skippedAccountReasons, isEmpty);
      expect(controller.sequencePlan!.evaluations[first]!.isEligible, isTrue);
    });

    test(
      'payout protection is hard excluded until a reasoned override',
      () async {
        final account = controller.portfolio!.accounts.first;
        await controller.saveAccount(
          account.copyWith(status: AccountStatus.payoutProtection),
        );
        await controller.recalculateSequence();
        expect(
          controller.sequencePlan!.evaluations[account.id]!.exclusionReasons
              .map((item) => item.messageKey),
          contains('sequence.exclusion.payoutProtected'),
        );

        await expectLater(
          controller.overrideSequenceAccount(account.id, 'no'),
          throwsA(isA<FormatException>()),
        );
        await controller.overrideSequenceAccount(
          account.id,
          'Payout was already requested and manually verified.',
        );
        expect(controller.sequencePlan!.steps.first.accountId, account.id);
        expect(
          controller.portfolio!.disciplineRecords.last.type,
          'sequence_account_override',
        );
      },
    );

    test(
      'custom sequences validate, save roles, and persist a template',
      () async {
        final order = controller.sequencePlan!.steps
            .map((item) => item.accountId)
            .toList();
        final roles = {
          order[0]: SequenceAccountRole.starting,
          order[1]: SequenceAccountRole.firstBackup,
        };
        await controller.saveCustomSequence(accountIds: order, roles: roles);
        expect(controller.sequenceState.settings.mode, SequenceMode.custom);
        expect(
          controller.sequenceState.settings.roles[order[1]],
          SequenceAccountRole.firstBackup,
        );

        await controller.saveSequenceTemplate('Safe morning order');
        expect(
          controller.sequenceState.templates.single.name,
          'Safe morning order',
        );
        await controller.updateSequenceSettings(
          controller.sequenceState.settings.copyWith(
            mode: SequenceMode.fixedRotation,
          ),
        );
        await controller.applySequenceTemplate(
          controller.sequenceState.templates.single.id,
        );
        expect(controller.sequenceState.settings.mode, SequenceMode.custom);
      },
    );

    test(
      'changed eligibility invalidates confirmation before session start',
      () async {
        await controller.confirmSequence();
        final first = controller.sequencePlan!.steps.first.accountId;
        final account = controller.portfolio!.accounts.firstWhere(
          (item) => item.id == first,
        );
        await controller.saveAccount(
          account.copyWith(status: AccountStatus.paused),
        );

        await expectLater(
          controller.startSequenceSession(),
          throwsA(isA<StateError>()),
        );
        expect(controller.portfolio!.sessionActive, isFalse);
        expect(controller.sequencePlan!.confirmedAt, isNull);
      },
    );
  });
}
