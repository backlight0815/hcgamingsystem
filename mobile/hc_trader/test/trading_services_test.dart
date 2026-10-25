import 'package:flutter_test/flutter_test.dart';
import 'package:flutter/widgets.dart';
import 'package:hc_trader/core/localization/app_localizations.dart';
import 'package:hc_trader/core/localization/localized_value.dart';
import 'package:hc_trader/core/security/access_policy.dart';
import 'package:hc_trader/core/security/publishing_authorization.dart';
import 'package:hc_trader/core/storage/local_app_storage.dart';
import 'package:hc_trader/core/security/app_role_permissions.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/access_status.dart';
import 'package:hc_trader/models/announcement.dart';
import 'package:hc_trader/core/security/admin_permissions.dart';
import 'package:hc_trader/features/prop_firm/domain/prop_firm_calculator.dart';
import 'package:hc_trader/models/capital_transaction.dart';
import 'package:hc_trader/models/final_exam_mobile.dart';
import 'package:hc_trader/models/knowledge_centre_item.dart';
import 'package:hc_trader/models/mt5_sync_models.dart';
import 'package:hc_trader/models/prop_firm_challenge.dart';
import 'package:hc_trader/models/trading_data_source.dart';
import 'package:hc_trader/models/trading_journal_entry.dart';
import 'package:hc_trader/models/trading_pair.dart';
import 'package:hc_trader/models/trading_exam_models.dart';
import 'package:hc_trader/models/standalone_models.dart';
import 'package:hc_trader/models/trading_plan_recommendation.dart';
import 'package:hc_trader/models/trading_strategy_recommendation.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/prop_firm_progress_service.dart';
import 'package:hc_trader/services/psychology_score_service.dart';
import 'package:hc_trader/services/push_notification_service.dart';
import 'package:hc_trader/services/risk_calculator_service.dart';
import 'package:hc_trader/services/trading_analytics.dart';
import 'package:hc_trader/services/trading_calculator.dart';
import 'package:hc_trader/services/trading_data_source_service.dart';
import 'package:hc_trader/services/trading_plan_recommendation_service.dart';
import 'package:hc_trader/services/trading_strategy_recommendation_service.dart';
import 'package:hc_trader/shared/text_deduplication.dart';
import 'package:hc_trader/services/trading_report_service.dart';
import 'package:hc_trader/shared/formatters.dart';
import 'package:hc_trader/services/trading_time_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  group('TradingCalculator', () {
    test('calculates pips and signed profit loss like Laravel journal', () {
      const pair = TradingPair(
        id: 'xauusd',
        symbol: 'XAUUSD',
        pipFactor: 0.1,
        pipDecimal: 1,
      );
      final calculator = TradingCalculator();

      final win = calculator.calculate(
        pair: pair,
        entryPrice: 4000,
        exitPrice: 4001,
        lotSize: .01,
        result: 1,
      );
      final loss = calculator.calculate(
        pair: pair,
        entryPrice: 4000,
        exitPrice: 4000.5,
        lotSize: .01,
        result: 2,
      );
      final breakEven = calculator.calculate(
        pair: pair,
        entryPrice: 4000,
        exitPrice: 4001,
        lotSize: .01,
        result: 3,
      );

      expect(win.pips, 10);
      expect(win.profitLoss, 1);
      expect(loss.pips, 5);
      expect(loss.profitLoss, -0.5);
      expect(breakEven.profitLoss, 0);
    });

    test('uses the Gold stop-loss price when Loss is selected', () {
      const pair = TradingPair(
        id: 'xauusd',
        symbol: 'XAUUSD',
        pipFactor: 0.1,
        pipDecimal: 1,
      );
      final calculator = TradingCalculator();
      final exitPrice = calculator.suggestedExitPrice(
        result: 2,
        entryPrice: 4000,
        currentExitPrice: 4010,
        stopLoss: 3995,
        takeProfit: 4020,
      );
      final loss = calculator.calculate(
        pair: pair,
        entryPrice: 4000,
        exitPrice: exitPrice!,
        lotSize: 0.10,
        result: 2,
      );

      expect(exitPrice, 3995);
      expect(loss.pips, 50);
      expect(loss.profitLoss, -50);
    });

    test('outcome buttons retain the exit when their target is missing', () {
      final calculator = TradingCalculator();

      expect(
        calculator.suggestedExitPrice(
          result: 2,
          entryPrice: 4000,
          currentExitPrice: 3997,
        ),
        3997,
      );
    });
  });

  group('TradingTimeService', () {
    test('converts MT5 UTC+3 input into Malaysia time', () {
      final service = TradingTimeService();
      final timeline = service.toMalaysiaTimeline(
        openValue: DateTime(2026, 6, 6, 9),
        closeValue: DateTime(2026, 6, 6, 10),
        sourceMode: TradingTimeService.mt5,
        sourceOffsetMinutes: 180,
      );

      expect(timeline.isValid, isTrue);
      expect(timeline.openDate, DateTime(2026, 6, 6, 14));
      expect(timeline.closeDate, DateTime(2026, 6, 6, 15));
    });

    test('rolls same-date after-midnight closes forward', () {
      final service = TradingTimeService();
      final timeline = service.toMalaysiaTimeline(
        openValue: DateTime(2026, 6, 6, 23, 30),
        closeValue: DateTime(2026, 6, 6, 0, 15),
        sourceMode: TradingTimeService.malaysia,
        sourceOffsetMinutes: 480,
      );

      expect(timeline.closeRolledForward, isTrue);
      expect(timeline.closeDate, DateTime(2026, 6, 7, 0, 15));
      expect(timeline.isValid, isTrue);
    });
  });

  group('Trading data display source', () {
    test('keeps manual and MT5 journal data separated', () {
      final manual = _trade('manual', DateTime(2026, 7, 1), 10);
      final excel = _trade(
        'excel',
        DateTime(2026, 7, 2),
        -5,
      ).copyWith(source: TradingRecordSource.excelImport);
      final mt5 = _trade(
        'mt5',
        DateTime(2026, 7, 3),
        20,
      ).copyWith(source: TradingRecordSource.mt5Sync);

      expect(
        filterTradesForDisplayMode([
          manual,
          excel,
          mt5,
        ], TradingDataDisplayMode.manualOnly).map((trade) => trade.id),
        ['manual', 'excel'],
      );
      expect(
        filterTradesForDisplayMode([
          manual,
          excel,
          mt5,
        ], TradingDataDisplayMode.mt5Only).map((trade) => trade.id),
        ['mt5'],
      );
    });

    test('maps closed MT5 records to MT5-sourced journal entries', () {
      final record = _mt5Record(
        id: 'closed-profit',
        accountKey: 'PUPrime-Demo_700122288',
        recordType: 'deal',
        status: 'closed',
        positionId: 281558192,
        profit: 17.25,
        closeTime: DateTime.utc(2026, 7, 4, 12),
      );
      final journal = journalFromMt5Record(record);

      expect(journal.source, TradingRecordSource.mt5Sync);
      expect(journal.id, contains(record.canonicalLifecycleKey));
      expect(journal.status, 'closed');
      expect(journal.profitLoss, record.netProfit);
      expect(journal.result, 1);
    });

    test('serializes source for backward-compatible journal records', () {
      final oldRecord = TradingJournalEntry.fromJson({
        'id': 'old',
        'user_id': 'u1',
        'open_date': '2026-07-01T08:00:00Z',
        'close_date': '2026-07-01T09:00:00Z',
        'pair': 'XAUUSD',
        'direction': 1,
        'entry_price': 2000,
        'exit_price': 2001,
        'lot_size': 0.1,
        'pips': 10,
        'profit_loss': 25,
        'result': 1,
      });
      final mt5Record = oldRecord.copyWith(source: TradingRecordSource.mt5Sync);

      expect(oldRecord.source, TradingRecordSource.manual);
      expect(mt5Record.toFirestore()['source'], 'mt5_sync');
    });
  });

  group('Client access policy', () {
    test('allows personal public features before approval', () {
      const pending = AppUser(
        uid: 'u1',
        email: 'client@example.com',
        accessStatus: AccessStatus.pendingReview,
        roleId: 750,
      );

      expect(
        ClientAccessPolicy.canAccess(pending, AppFeatureArea.tradingBlog),
        isTrue,
      );
      expect(
        ClientAccessPolicy.canAccess(pending, AppFeatureArea.tradingPlan),
        isTrue,
      );
      expect(
        ClientAccessPolicy.canAccess(pending, AppFeatureArea.profile),
        isTrue,
      );
      expect(
        ClientAccessPolicy.canAccess(pending, AppFeatureArea.dashboard),
        isTrue,
      );
      expect(
        ClientAccessPolicy.canAccess(pending, AppFeatureArea.journal),
        isTrue,
      );
      expect(
        ClientAccessPolicy.canAccess(pending, AppFeatureArea.propFirm),
        isFalse,
      );
    });

    test('approves admins and approved clients', () {
      final approved = AppUser.fromJson({
        'id': 'u2',
        'email': 'approved@example.com',
        'role_id': 750,
        'access_status': 'approved',
      });
      final admin = AppUser.fromJson({
        'id': 'admin',
        'email': 'admin@example.com',
        'role_id': 1,
      });

      expect(approved.hasClientAccess, isTrue);
      expect(
        ClientAccessPolicy.canAccess(approved, AppFeatureArea.journal),
        isTrue,
      );
      expect(admin.accessStatus, AccessStatus.approved);
      expect(ClientAccessPolicy.canAccess(admin, AppFeatureArea.admin), isTrue);
    });
  });

  group('TradingAnalytics', () {
    test('summarizes balance, win rate, and consistency', () {
      final analytics = TradingAnalytics();
      final summary = analytics.summarize(
        capitals: [
          CapitalTransaction(
            id: 'c1',
            userId: 'u1',
            depositDate: DateTime(2026, 6, 1),
            amount: 1000,
            type: 1,
          ),
          CapitalTransaction(
            id: 'c2',
            userId: 'u1',
            depositDate: DateTime(2026, 6, 2),
            amount: -100,
            type: 2,
          ),
        ],
        journals: [
          _trade('t1', DateTime(2026, 6, 3), 120),
          _trade('t2', DateTime(2026, 6, 4), -40),
          _trade('t3', DateTime(2026, 6, 5), 80),
        ],
      );

      expect(summary.totalTrades, 3);
      expect(summary.winRate, 66.67);
      expect(summary.netProfitLoss, 160);
      expect(summary.currentBalance, 1060);
      expect(summary.consistencyPercent, 75);
      expect(summary.consistencyPassed, isFalse);
    });

    test('groups daily profit loss by the 5am trading day reset', () {
      final analytics = TradingAnalytics();
      final summary = analytics.summarize(
        capitals: const [],
        journals: [
          _trade('early', DateTime(2026, 6, 19, 4, 30), -50),
          _trade('reset', DateTime(2026, 6, 19, 5), 120),
          _trade('later', DateTime(2026, 6, 19, 9), 30),
        ],
      );

      expect(summary.dailyProfitLoss, {
        DateTime(2026, 6, 18): -50,
        DateTime(2026, 6, 19): 150,
      });
    });
  });

  group('TradingReportService', () {
    test(
      'builds report rules and blocks withdrawal when best day is pending',
      () {
        final service = TradingReportService();
        final capitals = [
          CapitalTransaction(
            id: 'c1',
            userId: 'u1',
            depositDate: DateTime(2026, 6),
            amount: 1000,
            type: 1,
          ),
        ];
        final journals = [
          _trade('t1', DateTime(2026, 6, 3), 120),
          _trade('t2', DateTime(2026, 6, 4), -40),
          _trade('t3', DateTime(2026, 6, 5), 80),
        ];

        final report = service.build(journals: journals, capitals: capitals);
        final eligibility = service.withdrawalEligibility(
          journals: journals,
          capitals: capitals,
          requestedAmount: 50,
        );

        expect(report.summary.grossProfitRule.passed, isTrue);
        expect(report.summary.bestDayRule.passed, isFalse);
        expect(report.summary.bestDayRule.scorePercent, 75);
        expect(eligibility.eligible, isFalse);
        expect(eligibility.message, contains('40% Best Day Rule pending'));
      },
    );
  });

  group('Standalone mobile services', () {
    test('risk calculator derives lot size and projected reward', () {
      const service = RiskCalculatorService();
      final result = service.calculate(
        accountBalance: 10000,
        riskPercent: 1,
        entryPrice: 1.1000,
        stopLoss: 1.0950,
        takeProfit: 1.1100,
        pipSize: .0001,
      );

      expect(result.riskAmount, 100);
      expect(result.pipDistance, 50);
      expect(result.lotSize, .2);
      expect(result.riskReward, 2);
      expect(result.projectedProfit, 200);
    });

    test('prop-firm tracker detects daily loss failure', () {
      const service = PropFirmProgressService();
      final progress = service.evaluate(
        startingBalance: 10000,
        rule: PropFirmRule.defaults.first,
        trades: [_trade('loss', DateTime(2026, 6, 19, 7), -500)],
      );

      expect(progress.status, 'Failed');
      expect(progress.warnings, contains('Daily loss limit reached.'));
    });

    test('psychology readiness caps frustrated 100/100 below perfect', () {
      const service = PsychologyScoreService();
      final score = service.evaluate(
        PsychologyCheckIn(
          id: 'check',
          createdAt: DateTime(2026, 6, 19),
          emotion: 'Frustrated',
          confidence: 10,
          discipline: 10,
          fomo: false,
          revengeUrge: false,
          overconfidence: false,
        ),
      );

      expect(score.score, 75);
      expect(score.status, 'Acceptable');
      expect(
        score.warnings,
        contains(
          'High discipline helps, but it cannot fully offset negative emotion.',
        ),
      );
      expect(
        score.explanation,
        contains('current mood can still affect trading decision quality'),
      );
    });

    test('psychology readiness handles requested mood cap examples', () {
      const service = PsychologyScoreService();

      PsychologyScore score({
        required String emotion,
        int confidence = 10,
        int discipline = 10,
        bool revenge = false,
      }) {
        return service.evaluate(
          PsychologyCheckIn(
            id: emotion,
            createdAt: DateTime(2026, 6, 19),
            emotion: emotion,
            confidence: confidence,
            discipline: discipline,
            fomo: false,
            revengeUrge: revenge,
            overconfidence: false,
          ),
        );
      }

      expect(score(emotion: 'Great').score, 100);
      expect(score(emotion: 'Calm').score, closeTo(95.5, .01));
      expect(
        service
            .evaluate(
              PsychologyCheckIn(
                id: 'neutral',
                createdAt: DateTime(2026, 6, 19),
                emotion: 'Neutral',
                confidence: 5,
                discipline: 5,
                fomo: false,
                revengeUrge: false,
                overconfidence: false,
              ),
            )
            .score,
        closeTo(54.5, .01),
      );
      expect(score(emotion: 'Frustrated').score, 75);
      expect(score(emotion: 'Frustrated', confidence: 1).score, 45);
      expect(score(emotion: 'Angry').score, 60);
      expect(score(emotion: 'Fearful').score, 65);
      expect(score(emotion: 'Revenge trading mood').score, 45);
      expect(score(emotion: 'Calm', revenge: true).score, 45);
    });

    test('psychology score flags revenge and FOMO risk', () {
      const service = PsychologyScoreService();
      final score = service.evaluate(
        PsychologyCheckIn(
          id: 'check',
          createdAt: DateTime(2026, 6, 19),
          emotion: 'Frustrated',
          confidence: 3,
          discipline: 3,
          fomo: true,
          revengeUrge: true,
          overconfidence: false,
        ),
      );

      expect(score.status, 'Not Ready');
      expect(
        score.warnings,
        contains('Review your trading plan before entering the market.'),
      );
      expect(
        score.warnings,
        contains(
          'Consider reducing lot size or waiting for a clearer mental state.',
        ),
      );
    });
  });

  group('Role permissions', () {
    AppUser user(int roleId, {bool isAdmin = false}) => AppUser(
      uid: 'role-$roleId',
      email: 'role$roleId@example.com',
      roleId: roleId,
      isAdmin: isAdmin,
    );

    test('signal roles can publish signals but not market analysis', () {
      expect(
        PublishingAuthorization(user(201)).canPublishTradingSignal,
        isTrue,
      );
      expect(
        PublishingAuthorization(user(202)).canPublishTradingSignal,
        isTrue,
      );
      expect(
        PublishingAuthorization(user(502)).canPublishTradingSignal,
        isTrue,
      );
      expect(
        PublishingAuthorization(user(201)).canPublishMarketAnalysis,
        isFalse,
      );
    });

    test('market analyst and administrators receive correct workspaces', () {
      expect(
        PublishingAuthorization(user(501)).canPublishMarketAnalysis,
        isTrue,
      );
      expect(
        PublishingAuthorization(user(501)).canPublishTradingSignal,
        isFalse,
      );
      expect(
        PublishingAuthorization(user(1, isAdmin: true)).canPublishTradingSignal,
        isTrue,
      );
      expect(
        PublishingAuthorization(
          user(1, isAdmin: true),
        ).canPublishMarketAnalysis,
        isTrue,
      );
      expect(
        PublishingAuthorization(user(750)).canPublishTradingSignal,
        isFalse,
      );
      expect(
        PublishingAuthorization(user(750)).canPublishMarketAnalysis,
        isFalse,
      );
    });

    test('super administrator has every management permission', () {
      final superAdmin = user(1, isAdmin: true);
      for (final permission in AdminPermission.values) {
        expect(
          superAdmin.hasPermission(permission),
          isTrue,
          reason: permission.key,
        );
      }
    });

    test('sub administrator has admin access except super-admin invite', () {
      final subAdmin = user(2);
      for (final permission in AdminPermission.values) {
        expect(
          subAdmin.hasPermission(permission),
          isTrue,
          reason: permission.key,
        );
      }

      expect(
        RolePermissionMatrix.canInviteRole(2, RolePermissionMatrix.superAdmin),
        isFalse,
      );
      expect(
        RolePermissionMatrix.canInviteRole(2, RolePermissionMatrix.subAdmin),
        isFalse,
      );
      expect(
        RolePermissionMatrix.canInviteRole(
          2,
          RolePermissionMatrix.marketAnalyst,
        ),
        isTrue,
      );
      expect(
        RolePermissionMatrix.canInviteRole(2, RolePermissionMatrix.leader),
        isTrue,
      );
      expect(
        RolePermissionMatrix.canManageExistingUser(
          2,
          RolePermissionMatrix.subAdmin,
        ),
        isFalse,
      );
    });

    test('invite role hierarchy matches referral security rules', () {
      expect(RolePermissionMatrix.canInviteRole(1, 1), isTrue);
      expect(RolePermissionMatrix.canInviteRole(760, 770), isTrue);
      expect(RolePermissionMatrix.canInviteRole(760, 501), isFalse);
      expect(RolePermissionMatrix.canInviteRole(501, 750), isTrue);
      expect(RolePermissionMatrix.canInviteRole(201, 700), isTrue);
      expect(RolePermissionMatrix.canInviteRole(770, 2), isFalse);
      expect(RolePermissionMatrix.canInviteRole(750, 700), isTrue);
      expect(RolePermissionMatrix.canInviteRole(750, 750), isTrue);
      expect(RolePermissionMatrix.canInviteRole(750, 770), isFalse);
      expect(RolePermissionMatrix.isPublicRegistrationRole(750), isTrue);
      expect(RolePermissionMatrix.isPublicRegistrationRole(700), isTrue);
      expect(RolePermissionMatrix.isPublicRegistrationRole(2), isFalse);
    });

    test('market analyst requests deletion without administration access', () {
      final analyst = user(501);
      expect(analyst.hasPermission(AdminPermission.marketAnalysisEdit), isTrue);
      expect(
        analyst.hasPermission(AdminPermission.marketAnalysisRequestDelete),
        isTrue,
      );
      expect(analyst.hasPermission(AdminPermission.featureManage), isFalse);
      expect(
        analyst.hasPermission(AdminPermission.marketAnalysisReviewDelete),
        isFalse,
      );
    });

    test('signal provider cannot administer users or feature toggles', () {
      final provider = user(201);
      expect(provider.hasPermission(AdminPermission.signalCreate), isTrue);
      expect(provider.hasPermission(AdminPermission.userManage), isFalse);
      expect(provider.hasPermission(AdminPermission.featureManage), isFalse);
    });
  });

  test('knowledge centre retains uploaded image and resource URLs', () {
    const item = KnowledgeCentreItemMobile(
      id: 'knowledge-1',
      title: 'Risk plan',
      imageUrl: 'https://storage.example/risk-plan.webp',
      filePath: 'https://storage.example/risk-plan.pdf',
    );

    final stored = item.toFirestore();
    expect(stored['image_url'], item.imageUrl);
    expect(stored['file_path'], item.filePath);
  });

  test('app user retains uploaded profile photo URL', () {
    final user = AppUser.fromJson({
      'id': 'trader-1',
      'email': 'trader@example.com',
      'photo_url': 'https://storage.example/profile.webp',
    });

    expect(user.avatarUrl, 'https://storage.example/profile.webp');
  });

  test('role codes resolve through the centralized readable mapping', () {
    const trader = AppUser(uid: 'u1', email: 'u1@example.com', roleId: 750);
    const analyst = AppUser(uid: 'u2', email: 'u2@example.com', roleId: 501);

    expect(trader.roleName, 'Trader');
    expect(analyst.roleName, 'Market Analyst');
  });

  test('journal serializes trading plan follow snapshot', () {
    final journal = _trade('plan-check', DateTime(2026, 6, 22), 80).copyWith(
      followedTradingPlan: true,
      tradingPlanId: 'plan-1',
      tradingPlanName: 'London XAUUSD',
      tradingPlanCheckedAt: DateTime(2026, 6, 22, 9),
      strategyId: 'strategy-1',
      strategyName: 'H4 support CHOCH',
      planComplianceChecklist: const {
        'followed_trading_plan': true,
        'followed_risk_rules': true,
      },
      strategyComplianceChecklist: const {
        'followed_strategy_rules': true,
        'followed_sl_rule': true,
      },
    );

    final json = journal.toJson();
    expect(json['followed_trading_plan'], isTrue);
    expect(json['trading_plan_id'], 'plan-1');
    expect(json['trading_plan_name'], 'London XAUUSD');
    expect(json['strategy_id'], 'strategy-1');
    expect(
      json['plan_compliance_checklist'],
      containsPair('followed_risk_rules', true),
    );

    final parsed = TradingJournalEntry.fromJson(json);
    expect(parsed.followedTradingPlan, isTrue);
    expect(parsed.tradingPlanId, 'plan-1');
    expect(parsed.tradingPlanName, 'London XAUUSD');
    expect(parsed.tradingPlanCheckedAt, DateTime(2026, 6, 22, 9));
    expect(parsed.strategyId, 'strategy-1');
    expect(parsed.strategyComplianceChecklist['followed_sl_rule'], isTrue);
  });

  test(
    'trading strategy and plan CRUD works in local repository mode',
    () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final repository = TradingRepository(
        firebaseEnabled: false,
        storage: storage,
      );
      final now = DateTime(2026, 7, 9, 12);
      final strategy = TradingStrategyRecord(
        id: 'strategy-1',
        userId: 'u1',
        createdAt: now,
        updatedAt: now,
        strategy: const TradingStrategy(
          name: 'H4 support CHOCH',
          tradingStyle: 'Swing',
          marketCondition: 'Reversal',
          analysisMethods: ['SMC', 'Price Action'],
          majorTimeframe: 'H4',
          internalTimeframe: 'H1',
          entryTimeframe: 'M15',
          entryModelRules: 'Buy H4 support after M15 CHOCH.',
          stopLossRule: 'SL below swing low.',
          takeProfitRule: 'TP at next resistance.',
        ),
      );

      await repository.upsertTradingStrategyRecord(uid: 'u1', record: strategy);

      final strategies = await repository.loadTradingStrategyRecords('u1');
      expect(strategies, hasLength(1));
      expect(strategies.single.strategy.name, 'H4 support CHOCH');

      final plan = TradingPlanRecord(
        id: 'plan-1',
        userId: 'u1',
        createdAt: now,
        updatedAt: now,
        plan: const TradingPlan(
          strategy: 'London risk plan',
          isDefault: true,
          allowedStrategyIds: ['strategy-1'],
          allowedStrategyNames: ['H4 support CHOCH'],
          riskPercent: 1,
          dailyMaxLossPercent: 2,
          maxTradesPerDay: 3,
          maxLossesPerDay: 2,
          maxConsecutiveLosses: 2,
          maxOpenTrades: 1,
          allowedSessions: ['London', 'New York'],
          newsFilterRule: 'No high-impact news entries.',
        ),
      );

      await repository.upsertTradingPlanRecord(uid: 'u1', record: plan);

      final plans = await repository.loadTradingPlanRecords('u1');
      expect(plans, hasLength(1));
      expect(plans.single.plan.isDefault, isTrue);
      expect(plans.single.plan.allowedStrategyIds, contains('strategy-1'));

      await repository.deleteTradingStrategyRecord(
        uid: 'u1',
        strategyId: 'strategy-1',
      );
      expect(await repository.loadTradingStrategyRecords('u1'), isEmpty);
    },
  );

  test('trading plan recommendation engine adapts core scenarios', () {
    const service = TradingPlanRecommendationService();

    final beginnerFomo = service.recommend(
      _questionnaireResponse({
        'experience_length': 'beginner',
        'consistency': 'not_consistent',
        'account_type': 'demo',
        'personality': 'impulsive',
        'loss_reaction': 'keep_looking',
        'biggest_weakness': 'fomo',
        'risk_per_trade': 'not_sure',
        'daily_loss': 'not_sure',
        'weekly_loss': 'not_sure',
        'trades_per_day': 'no_limit',
        'minimum_rr': 'flexible',
        'screen_time': 'thirty_to_one',
        'session': 'london_new_york',
        'opportunity_preference': 'fewer_quality',
        'selected_strategy': 'not_sure_yet',
        'has_strategy': 'still_developing',
      }),
    );

    expect(beginnerFomo.planName, 'Guided FOMO-control trading plan');
    expect(beginnerFomo.selectedTradingStrategy, 'Not sure yet');
    expect(beginnerFomo.riskPercent, lessThanOrEqualTo(.5));
    expect(beginnerFomo.maxTradesPerDay, lessThanOrEqualTo(2));
    expect(beginnerFomo.minimumRiskRewardRatio, 2);
    expect(beginnerFomo.fomoPreventionRules, contains('Do not chase candles.'));
    expect(
      beginnerFomo.noTradeConditions,
      contains('No trade without selected strategy.'),
    );

    final revenge = service.recommend(
      _questionnaireResponse({
        ..._baseRecommendationAnswers,
        'biggest_weakness': 'revenge_trading',
        'loss_reaction': 'increase_lot',
      }),
    );
    expect(
      revenge.revengeTradingPreventionRules,
      contains('Stop after 2 consecutive losses.'),
    );
    expect(revenge.lossRecoveryRules, contains('Never increase lot size'));
    expect(revenge.cooldownAfterLossMinutes, 30);

    final overtrader = service.recommend(
      _questionnaireResponse({
        ..._baseRecommendationAnswers,
        'biggest_weakness': 'overtrading',
        'opportunity_preference': 'more_opportunities',
        'trades_per_day': '5',
      }),
    );
    expect(overtrader.maxTradesPerDay, lessThanOrEqualTo(2));
    expect(
      overtrader.disciplineRules,
      contains('Stop when max trades per day is reached.'),
    );

    final propFirm = service.recommend(
      _questionnaireResponse({
        ..._baseRecommendationAnswers,
        'account_type': 'prop_firm',
        'risk_per_trade': '2',
      }),
    );
    expect(propFirm.planName, 'Guided prop firm protection plan');
    expect(propFirm.riskPercent, lessThanOrEqualTo(.5));
    expect(
      propFirm.propFirmRules,
      contains('No all-in and no overleveraging.'),
    );
    expect(propFirm.tradingObjective, contains('Protect account status'));

    final multiPsychology = service.recommend(
      _questionnaireResponse({
        ..._baseRecommendationAnswers,
        'account_type': 'live_and_prop',
        'personality': 'impulsive',
        'loss_reaction': 'increase_lot',
        'psychology_issues':
            'fomo|revenge_trading|overtrading|moving_stop_loss|ignoring_daily_loss_limit|all_in_behavior',
        'selected_strategy': 'snr|ema|market_structure',
        'risk_per_trade': '2',
        'daily_loss': '5',
        'trades_per_day': '5',
      }),
    );

    expect(
      multiPsychology.selectedTradingStrategies,
      containsAll(['SNR', 'EMA', 'Market Structure']),
    );
    expect(multiPsychology.allowedStrategyNames, contains('EMA'));
    expect(multiPsychology.psychologyIssues, contains('Moving stop loss'));
    expect(multiPsychology.riskPercent, .25);
    expect(multiPsychology.maxDailyLossPercent, lessThanOrEqualTo(2));
    expect(multiPsychology.maxTradesPerDay, 1);
    expect(multiPsychology.cooldownAfterLossMinutes, 45);
    expect(multiPsychology.psychologyRules, contains('FOMO rule'));
    expect(multiPsychology.psychologyRules, contains('Revenge rule'));
    expect(multiPsychology.psychologyRules, contains('Stop-loss rule'));
    expect(multiPsychology.psychologyRules, contains('Capital protection'));
    expect(
      multiPsychology.noTradeConditions,
      contains('maximum-loss-limit breach'),
    );
  });

  test('trading strategy recommendation engine adapts core scenarios', () {
    const service = TradingStrategyRecommendationService();

    final snr = service.recommend(
      _strategyQuestionnaireResponse({
        ..._baseStrategyRecommendationAnswers,
        'technical_concept': 'snr',
        'setup_type': 'snr_rejection',
        'market_condition': 'ranging',
        'trusted_confirmation': 'candle_rejection',
        'stop_loss_method': 'snr_zone',
        'take_profit_method': 'snr_level',
      }),
    );

    expect(snr.strategyName, 'Support and Resistance Rejection Strategy');
    expect(snr.strategyCategory, 'SNR Strategy');
    expect(snr.technicalConcept, contains('Support and resistance zones'));
    expect(
      snr.stopLossPlacementRule,
      contains('outside the support/resistance zone'),
    );
    expect(snr.takeProfitRule, contains('support/resistance level'));
    expect(snr.strategyWarning, contains('middle of a range'));
    expect(
      snr.entryConfirmationRules,
      contains('marked support or resistance zone'),
    );

    final smc = service.recommend(
      _strategyQuestionnaireResponse({
        ..._baseStrategyRecommendationAnswers,
        'technical_concept': 'ict',
        'setup_type': 'order_block_reaction',
        'market_condition': 'reversal',
        'confirmations': 'three',
        'trusted_confirmation': 'choch',
        'direction_timeframe': 'not_sure',
        'entry_timeframe': 'not_sure',
      }),
    );

    expect(smc.strategyCategory, 'SMC / ICT Strategy');
    expect(smc.technicalConcept, contains('liquidity'));
    expect(smc.recommendedDirectionTimeframe, 'H4/H1');
    expect(smc.recommendedEntryTimeframe, 'M15/M5');
    expect(smc.entryConfirmationRules, contains('BOS/CHOCH'));
    expect(smc.strategyWarning, contains('forcing POI entries'));

    final indicator = service.recommend(
      _strategyQuestionnaireResponse({
        ..._baseStrategyRecommendationAnswers,
        'technical_concept': 'macd',
        'setup_type': 'indicator_crossover',
        'market_condition': 'ranging',
        'trusted_confirmation': 'macd_crossover',
      }),
    );
    expect(indicator.strategyName, 'Trend Continuation Indicator Strategy');
    expect(indicator.technicalConcept, contains('MACD momentum'));
    expect(indicator.entryConfirmationRules, contains('MACD crossover'));
    expect(indicator.invalidSetupConditions, contains('choppy'));
    expect(indicator.strategyWarning, contains('choppy range'));

    final trendline = service.recommend(
      _strategyQuestionnaireResponse({
        ..._baseStrategyRecommendationAnswers,
        'technical_concept': 'trendline',
        'setup_type': 'trendline_rejection',
        'trusted_confirmation': 'trendline_reaction',
      }),
    );
    expect(trendline.strategyCategory, 'Trendline Strategy');
    expect(trendline.entryModel, contains('trendline rejection'));
    expect(trendline.entryConfirmationRules, contains('valid touches'));
    expect(trendline.strategyWarning, contains('weak touches'));

    final mixed = service.recommend(
      _strategyQuestionnaireResponse({
        ..._baseStrategyRecommendationAnswers,
        'technical_concept': 'market_structure',
        'strategy_types': 'ema|market_structure|breakout_retest',
        'setup_type': 'breakout_retest',
        'market_condition': 'trending|ranging|high_volatility',
      }),
    );

    expect(mixed.strategyName, 'Mixed Market Multi-Strategy Framework');
    expect(mixed.strategyCategory, 'Hybrid Multi-Strategy Framework');
    expect(
      mixed.selectedStrategyTypes,
      containsAll(['EMA', 'Market Structure', 'Breakout Retest']),
    );
    expect(
      mixed.selectedMarketConditions,
      containsAll([
        'Trending market',
        'Ranging market',
        'High volatility market',
      ]),
    );
    expect(mixed.recommendedStrategySet, contains('Trending Market Strategy'));
    expect(mixed.recommendedStrategySet, contains('Ranging Market Strategy'));
    expect(mixed.recommendedStrategySet, contains('High Volatility Rule'));
    expect(mixed.marketConditionRules, contains('choose the active market'));
    expect(mixed.riskReducedStrategyRules, contains('reduced risk'));
    expect(mixed.analysisMethods, contains('Breakout Retest'));
  });

  test(
    'recommended trading plan metadata serializes with old-plan fallback',
    () {
      const plan = TradingPlan(
        strategy: 'Guided plan',
        planType: 'recommended',
        source: 'plan_questionnaire',
        questionnaireVersion: '2026.07.plan',
        questionnaireAnswers: {'biggest_weakness': 'FOMO'},
        generatedProfileSummary: 'Profile',
        strategyType: 'SNR',
        technicalRules: 'Entry permission rules',
        noTradeConditions: 'No chase entries',
        psychologyRules: 'No revenge trading.',
        improvementNotes: 'Review weekly',
      );

      final restored = TradingPlan.fromJson(plan.toJson());
      expect(restored.isRecommended, isTrue);
      expect(restored.source, 'plan_questionnaire');
      expect(restored.questionnaireAnswers['biggest_weakness'], 'FOMO');
      expect(restored.technicalRules, 'Entry permission rules');
      expect(restored.noTradeConditions, 'No chase entries');

      final oldPlan = TradingPlan.fromJson({'plan_name': 'Legacy plan'});
      expect(oldPlan.planType, 'manual');
      expect(oldPlan.isRecommended, isFalse);
      expect(oldPlan.questionnaireAnswers, isEmpty);
    },
  );

  test(
    'recommended trading strategy metadata serializes with old-strategy fallback',
    () {
      const strategy = TradingStrategy(
        name: 'Guided strategy',
        source: 'strategy_questionnaire',
        strategyType: 'SNR Strategy',
        questionnaireVersion: '2026.07.strategy',
        questionnaireAnswers: {'technical_concept': 'SNR'},
        suitableTraderPersonality: 'Suitable for patient traders.',
        recommendedMarket: 'Ranging market',
        technicalConcept: 'Support and resistance zones',
        selectedStrategyTypes: ['SNR', 'Market Structure'],
        selectedMarketConditions: ['Ranging market', 'Breakout market'],
        majorTimeframe: 'H1/H4',
        entryTimeframe: 'M15/M5',
        entryModelRules: 'Wait for rejection at support or resistance.',
        stopLossRule: 'Place SL outside the SNR zone.',
        takeProfitRule: 'Take profit at next key level.',
        invalidationRule: 'Invalid in the middle of the range.',
        tradeManagementRule: 'Manage only by technical plan.',
        improvementNotes: 'Build 20 screenshots.',
      );

      final restored = TradingStrategy.fromJson(strategy.toJson());
      expect(restored.isRecommended, isTrue);
      expect(restored.source, 'strategy_questionnaire');
      expect(restored.questionnaireAnswers['technical_concept'], 'SNR');
      expect(restored.riskPercent, isNull);
      expect(restored.selectedStrategyTypes, contains('Market Structure'));
      expect(restored.selectedMarketConditions, contains('Breakout market'));
      expect(restored.stopLossRule, 'Place SL outside the SNR zone.');
      expect(restored.tradeManagementRule, 'Manage only by technical plan.');

      final oldStrategy = TradingStrategy.fromJson({
        'name': 'Legacy strategy',
        'trading_style': 'Swing',
      });
      expect(oldStrategy.isRecommended, isFalse);
      expect(oldStrategy.questionnaireAnswers, isEmpty);
      expect(oldStrategy.tradingStyle, 'Swing');
    },
  );

  test('strategy and plan localization keys exist in Chinese', () {
    const l10n = AppLocalizations(Locale('zh'));
    for (final key in const [
      'Trading Strategies',
      'Trading Strategy',
      'Trading Plan',
      'Strategy vs Plan',
      'Trading Strategy: Entry, exit, SL/TP, setup rules. How I enter and exit the market.',
      'Trading Plan: Overall trading rules, risk, psychology, routine, allowed strategies. How I manage myself, risk, accounts, and execution.',
      'Did I follow my trading plan?',
      'Did I follow my selected strategy rules?',
      'Announcements',
      'New announcement',
      'Receive Announcement Notifications',
      'Company updates and notices',
      'Generate Recommended Trading Plan',
      'Generate Trading Plan',
      'Trading Plan Recommendation',
      'Trading Plan Details',
      'Trading Plan Questionnaire',
      'Generate Recommended Trading Strategy',
      'Trading Strategy Recommendation',
      'Trading Strategy Questionnaire',
      'Recommended Trading Strategy',
      'Save Recommended Strategy',
      'Edit Recommended Strategy',
      'Trading Experience',
      'Technical Strategy',
      'Psychology and Discipline',
      'Psychology Notes',
      'Review Answers',
      'Recommended Trading Plan',
      'Save Recommended Plan',
      'Edit Recommended Plan',
      'Trading Plan Saved Successfully',
      'Trading Strategy Saved Successfully',
      'Failed To Save Trading Plan',
      'Failed To Save Trading Strategy',
      'No Trading Plan Found',
      'No Trading Strategy Found',
      'Please Answer Required Questions',
      'Strategy Name',
      'Strategy Category',
      'Suitable Trader Type',
      'Suitable Market Condition',
      'Entry Confirmation Rules',
      'Stop Loss Placement Rule',
      'Take Profit Rule',
      'Recommended Practice Notes',
      'Selected / Recommended Trading Strategy',
      'Account Type',
      'Trading Objective',
      'Trading Session Rule',
      'Entry Permission Rules',
      'Loss Recovery Rules',
      'Weekly Review Rules',
      'Personality-Based Warnings',
      'No-Trade Conditions',
      'Strategy Preference',
      'Strategy Details',
      'Strategy Overview',
      'Timeframe Setup',
      'Entry Model Steps',
      'Risk & Avoid Conditions',
      'Strategy Weakness / Risk Warning',
      'Practice Notes',
      'Strategy Metadata',
      'Linked Trading Plan',
      'No linked trading plan',
      'Questionnaire Generated',
      'No description added',
      'No rule added',
      'Timeframe and Execution',
      'Entry and Confirmation',
      'Exit Method',
      'Trading Routine',
      'Strategy Selection',
      'Guided FOMO-control trading plan',
      'Support and Resistance Rejection Strategy',
      'SMC/ICT Structure Liquidity POI Strategy',
      'Do not chase candles.',
      'strategy_questionnaire',
      'plan_questionnaire',
      'Which strategy types do you want to combine?',
      'Which psychology issues affect you?',
      'Range rejection',
      'News-driven market',
      'Mixed market conditions',
      'Fear of entering trade',
      'Moving stop loss',
      'Increasing lot size after loss',
      'Cannot follow trading plan',
      'All-in behavior',
      'Ignoring daily loss limit',
      'Ignoring maximum loss limit',
      'Guided overtrading-control trading plan',
      'Guided psychology protection trading plan',
      'Mixed Market Multi-Strategy Framework',
      'Hybrid Multi-Strategy Framework',
    ]) {
      expect(l10n.text(key), isNot(key));
    }

    expect(
      l10n.text(
        'Allowed strategies: SNR, EMA, Market Structure. Choose only one active strategy per trade.',
      ),
      contains('允许策略'),
    );
    expect(
      l10n.text('Primary technical method: EMA, Market Structure.'),
      contains('主要技术方法'),
    );
    expect(l10n.text('Account Type: Prop Firm Challenge'), contains('账户类型'));
    expect(
      l10n.text(
        'You are building consistency, so the plan prioritizes simple rules and capital protection.',
      ),
      isNot(
        'You are building consistency, so the plan prioritizes simple rules and capital protection.',
      ),
    );
    expect(l10n.text('SNR, Market Structure'), isNot('SNR, Market Structure'));
    expect(l10n.text('Prop firm account'), isNot(contains('Prop')));
    expect(
      l10n.text('Build consistency first, not fast profit.'),
      isNot('Build consistency first, not fast profit.'),
    );
    expect(
      l10n.text('Risk per trade is fixed before entry.'),
      isNot('Risk per trade is fixed before entry.'),
    );
    for (final generatedLine in const [
      'FOMO rule: a missed entry is not a valid entry; wait for retest or the next setup.',
      'Revenge rule: after a loss, the cooldown starts before any new chart analysis.',
      'Stop-loss rule: never move the stop farther from entry after the trade is live.',
      'Capital protection rule: all-in behavior, oversized risk, and gambling entries close the session.',
      'Trending Market Strategy: trade continuation only when higher-timeframe structure and momentum agree.',
      'Breakout Market Strategy: wait for displacement and retest before entry; avoid chasing the breakout candle.',
    ]) {
      expect(l10n.text(generatedLine), isNot(generatedLine));
    }
  });

  test(
    'announcement model preserves publish metadata for Firestore writes',
    () {
      final publishedAt = DateTime.utc(2026, 7, 9, 6, 30);
      final announcement = Announcement.fromJson({
        'id': 'announcement-1',
        'title': 'Platform update',
        'message': 'New features are live.',
        'shortMessage': 'New features are live.',
        'audience': 'all',
        'status': 'published',
        'createdBy': 'admin-1',
        'createdAt': '2026-07-09T06:00:00.000Z',
        'updatedAt': '2026-07-09T06:10:00.000Z',
        'publishedAt': publishedAt.toIso8601String(),
      });

      expect(announcement.isPublished, isTrue);
      expect(announcement.shortMessage, 'New features are live.');
      expect(announcement.publishedAt, publishedAt);

      final json = announcement.toJson();
      expect(json['created_by'], 'admin-1');
      expect(json['published_at'], publishedAt.toIso8601String());

      final firestore = announcement.toFirestore();
      expect(firestore['title'], 'Platform update');
      expect(firestore['short_message'], 'New features are live.');
      expect(firestore['shortMessage'], 'New features are live.');
      expect(firestore['published_at'], isNotNull);
    },
  );

  test('announcement notification preferences default on and can opt out', () {
    final defaults = NotificationPreferences.fromMap(null);
    expect(defaults.announcements, isTrue);

    final optedOut = NotificationPreferences.fromMap({
      'signals': true,
      'announcements': false,
    });
    expect(optedOut.signals, isTrue);
    expect(optedOut.announcements, isFalse);

    final restored = optedOut.copyWith(announcements: true);
    expect(restored.announcements, isTrue);
    expect(restored.toMap()['announcements'], isTrue);
  });

  test('MT5 account and record models parse sync payloads safely', () {
    final now = DateTime.now();
    final account = Mt5Account.fromJson({
      'user_id': 'u1',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'balance': 9987.36,
      'equity': 9991.89,
      'currency': 'USD',
      'sync_status': 'connected',
      'last_connection_check_at': now.toIso8601String(),
      'last_heartbeat_at': now.toIso8601String(),
    });

    expect(account.accountKey, 'PUPrime-Demo_700122288');
    expect(account.isConnected, isTrue);
    expect(mt5AccountCapitalBalance(account), 9987.36);
    expect(
      formatCurrencyMoney(mt5AccountCapitalBalance(account), 'usd'),
      '9,987.36 USD',
    );

    final staleAccount = Mt5Account.fromJson({
      'user_id': 'u1',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'sync_status': 'connected',
      'display_status': 'Connected',
      'last_connection_check_at': now
          .subtract(const Duration(minutes: 11))
          .toIso8601String(),
      'last_heartbeat_at': now
          .subtract(const Duration(minutes: 11))
          .toIso8601String(),
      'updated_at': now.toIso8601String(),
    });

    expect(staleAccount.isConnected, isFalse);
    expect(mt5AccountCapitalBalance(staleAccount), 0);

    final equityOnlyAccount = Mt5Account.fromJson({
      'user_id': 'u1',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'equity': 1001.25,
    });
    expect(mt5AccountCapitalBalance(equityOnlyAccount), 1001.25);

    final record = Mt5TradeRecord.fromJson({
      'id': 'position_700122288*PUPrime-Demo*281558192',
      'user_id': 'u1',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'symbol': 'BTCUSD',
      'position_id': 281558192,
      'unique_mt5_id': '281558192',
      'trade_type': 'sell',
      'record_type': 'position',
      'status': 'open',
      'lot_size': 0.02,
      'entry_price': 62658.06,
      'current_price': 62431.29,
      'profit': 4.53,
      'commission': 0,
      'swap': 0,
      'trade_key': 'position_700122288*PUPrime-Demo*281558192',
      'record_hash': 'hash-1',
      'last_seen_at': '2026-07-04T12:00:00Z',
      'followed_trading_plan': null,
      'notes': 'Reviewed in app',
    });

    expect(record.isOpen, isTrue);
    expect(record.netProfit, 4.53);
    expect(record.tradeKey, 'position_700122288*PUPrime-Demo*281558192');
    expect(record.recordHash, 'hash-1');
    expect(record.lastSeenAt, isNotNull);
    expect(record.followedTradingPlan, isNull);
    expect(record.toJson()['notes'], 'Reviewed in app');

    final closedDeal = Mt5TradeRecord.fromJson({
      'id': 'deal_700122288*PUPrime-Demo*281558999',
      'user_id': 'u1',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'symbol': 'BTCUSD',
      'order_ticket': 281558100,
      'deal_ticket': 281558999,
      'position_id': 281558192,
      'unique_mt5_id': '281558999',
      'trade_type': 'buy',
      'record_type': 'deal',
      'status': 'closed',
      'lot_size': 0.02,
      'entry_price': 62658.06,
      'close_price': 62620.00,
      'profit': 7.21,
      'trade_key': 'deal_700122288*PUPrime-Demo*281558999',
      'record_hash': 'hash-closed',
      'close_time': '2026-07-04T12:05:00Z',
    });
    final lifecycleRecords = dedupeMt5LifecycleRecords([record, closedDeal]);

    expect(record.canonicalLifecycleKey, closedDeal.canonicalLifecycleKey);
    expect(lifecycleRecords, hasLength(1));
    expect(lifecycleRecords.single.isClosed, isTrue);
    expect(lifecycleRecords.single.recordHash, 'hash-closed');

    final staleLifecycleClosedDeal = Mt5TradeRecord.fromJson({
      'id': 'deal_700122288*PUPrime-Demo*281558999',
      'user_id': 'u1',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'symbol': 'BTCUSD',
      'deal_ticket': 281558999,
      'position_id': 281558192,
      'unique_mt5_id': '281558999',
      'trade_type': 'buy',
      'record_type': 'deal',
      'status': 'closed',
      'profit': 9.25,
      'trade_key': 'deal_700122288*PUPrime-Demo*281558999',
      'lifecycle_key': 'deal_700122288*PUPrime-Demo*281558999',
      'record_hash': 'stale-key-closed',
      'close_time': '2026-07-04T12:06:00Z',
    });
    final repairedLifecycleRecords = dedupeMt5LifecycleRecords([
      record,
      staleLifecycleClosedDeal,
    ]);

    expect(
      staleLifecycleClosedDeal.canonicalLifecycleKey,
      record.canonicalLifecycleKey,
    );
    expect(repairedLifecycleRecords, hasLength(1));
    expect(repairedLifecycleRecords.single.isClosed, isTrue);
    expect(repairedLifecycleRecords.single.profit, 9.25);

    final dealOnlyA = Mt5TradeRecord.fromJson({
      'id': 'deal_700122288*PUPrime-Demo*281559000',
      'user_id': 'u1',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'symbol': 'BTCUSD',
      'deal_ticket': 281559000,
      'position_id': 281559123,
      'unique_mt5_id': '281559000',
      'trade_type': 'buy',
      'record_type': 'deal',
      'status': 'closed',
      'profit': 2.0,
      'close_time': '2026-07-04T12:10:00Z',
    });
    final dealOnlyB = Mt5TradeRecord.fromJson({
      'id': 'deal_700122288*PUPrime-Demo*281559001',
      'user_id': 'u1',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'symbol': 'BTCUSD',
      'deal_ticket': 281559001,
      'position_id': 281559123,
      'unique_mt5_id': '281559001',
      'trade_type': 'sell',
      'record_type': 'deal',
      'status': 'closed',
      'profit': 3.0,
      'close_time': '2026-07-04T12:12:00Z',
    });

    final dealOnlyLifecycle = dedupeMt5LifecycleRecords([dealOnlyA, dealOnlyB]);
    expect(dealOnlyLifecycle, hasLength(1));
    expect(dealOnlyLifecycle.single.recordType, 'position');
    expect(dealOnlyLifecycle.single.profit, 5);

    final syncState = Mt5SyncState.fromJson({
      'user_id': 'u1',
      'account_key': 'PUPrime-Demo_700122288',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'sync_status': 'connected',
      'realtime_mode': true,
      'sync_interval_seconds': 1,
      'last_successful_sync_at': now.toIso8601String(),
      'last_connection_check_at': now.toIso8601String(),
      'last_heartbeat_at': now.toIso8601String(),
      'updated_at': now.toIso8601String(),
      'service_version': '1.1.0',
      'changed_record_count': 1,
      'written_record_count': 1,
      'free_tier_safe_mode': true,
      'sync_floating_profit_updates': true,
      'floating_update_min_interval_seconds': 5,
      'floating_update_min_profit_change': 1.0,
      'floating_update_min_price_change_points': 10,
      'vps_service_status': 'running',
    });

    expect(syncState.isLive, isTrue);
    expect(syncState.labelKey, 'Connected');
    expect(syncState.realtimeMode, isTrue);
    expect(syncState.serviceVersion, '1.1.0');
    expect(syncState.freeTierSafeMode, isTrue);
    expect(syncState.vpsServiceStatus, 'running');

    final staleSyncState = Mt5SyncState.fromJson({
      'user_id': 'u1',
      'account_key': 'PUPrime-Demo_700122288',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'sync_status': 'connected',
      'display_status': 'Connected',
      'last_connection_check_at': now
          .subtract(const Duration(minutes: 3))
          .toIso8601String(),
      'last_heartbeat_at': now
          .subtract(const Duration(minutes: 3))
          .toIso8601String(),
      'updated_at': now.toIso8601String(),
      'sync_stale_after_seconds': 90,
    });

    expect(staleSyncState.isLive, isFalse);
    expect(staleSyncState.labelKey, 'Disconnected');

    final disconnectedSyncState = Mt5SyncState.fromJson({
      'user_id': 'u1',
      'account_key': 'PUPrime-Demo_700122288',
      'mt5_login': 700122288,
      'broker_server': 'PUPrime-Demo',
      'sync_status': 'disconnected',
      'last_connection_check_at': now.toIso8601String(),
      'last_heartbeat_at': now.toIso8601String(),
    });

    expect(disconnectedSyncState.labelKey, 'Unable to update right now');

    final staleCrossMachineState = Mt5SyncState.fromJson({
      'user_id': 'u1',
      'account_key': 'HFMarketsGlobal-Live8_135139957',
      'mt5_login': 135139957,
      'broker_server': 'HFMarketsGlobal-Live8',
      'sync_status': 'account_not_linked',
      'detected_mt5_login': 1084317,
      'detected_broker_server': 'VTMarkets-Demo',
      'last_connection_check_at': now.toIso8601String(),
      'last_heartbeat_at': now.toIso8601String(),
    });

    expect(staleCrossMachineState.labelKey, 'Wrong MT5 account detected');
    expect(
      staleCrossMachineState.publicErrorMessage,
      'Expected 135139957 / HFMarketsGlobal-Live8, but MT5 is logged in to 1084317 / VTMarkets-Demo.',
    );
  });

  test('main MT5 account uses canonical document ID before legacy keys', () {
    final user = AppUser.fromJson({
      'id': 'u1',
      'email': 'trader@example.com',
      'role_id': 750,
      'mainMt5AccountKey': 'legacy-account',
      'main_mt5_account_key': 'legacy-account',
      'mainMt5AccountId': 'canonical-account',
      'main_mt5_account_id': 'canonical-account',
    });

    expect(user.mainMt5AccountKey, 'canonical-account');
  });

  test(
    'local repository requires deliberate main MT5 account selection',
    () async {
      SharedPreferences.setMockInitialValues({});
      final storage = await LocalAppStorage.create();
      final repository = TradingRepository(
        firebaseEnabled: false,
        storage: storage,
      );
      const accountA = Mt5Account(
        id: 'ServerA_700138229',
        userId: 'u1',
        accountKey: 'ServerA_700138229',
        login: 700138229,
        brokerServer: 'ServerA',
        displayName: 'JJ',
        isActive: true,
        balance: 1036.16,
        equity: 1036.16,
        currency: 'USD',
      );
      const accountB = Mt5Account(
        id: 'ServerB_700122288',
        userId: 'u1',
        accountKey: 'ServerB_700122288',
        login: 700122288,
        brokerServer: 'ServerB',
        displayName: 'Charlie',
        isActive: true,
        balance: 5000,
        equity: 5000,
        currency: 'USD',
      );
      await storage.writeList('u1', 'mt5_accounts', [
        accountA.toJson(),
        accountB.toJson(),
      ]);

      expect(await repository.mainMt5AccountFor('u1').first, isNull);

      await repository.setMainMt5Account(
        uid: 'u1',
        accountKey: accountA.accountKey,
      );
      final main = await repository.mainMt5AccountFor('u1').first;
      final stored = storage.readList('u1', 'mt5_accounts');

      expect(main?.accountKey, accountA.accountKey);
      expect(
        stored.where(
          (item) => item['is_primary'] == true || item['isPrimary'] == true,
        ),
        hasLength(1),
      );
      expect(
        stored.firstWhere(
          (item) => item['account_key'] == accountA.accountKey,
        )['isPrimary'],
        isTrue,
      );
    },
  );

  test('Prop Firm calculator evaluates MT5 synced challenge metrics', () {
    const calculator = PropFirmCalculator();
    const accountKey = 'PUPrime-Demo_700122288';
    final now = DateTime.utc(2026, 7, 4, 12);
    final challenge = PropFirmChallenge(
      id: 'challenge-1',
      userId: 'u1',
      mt5AccountKey: accountKey,
      mt5Login: 700122288,
      brokerServer: 'PUPrime-Demo',
      propFirmName: 'Demo Prop',
      challengeName: 'Evaluation',
      startingBalance: 10000,
      phase1ProfitTargetPercent: 8,
      dailyLossLimitPercent: 5,
      maximumLossLimitPercent: 10,
      minimumTradingDaysPhase1: 2,
      challengeStartDate: DateTime.utc(2026, 7, 1),
    );
    const account = Mt5Account(
      id: accountKey,
      userId: 'u1',
      accountKey: accountKey,
      login: 700122288,
      brokerServer: 'PUPrime-Demo',
      balance: 10400,
      equity: 10450,
      currency: 'USD',
      syncStatus: 'connected',
    );
    final records = [
      _mt5Record(
        id: 'deal-1',
        accountKey: accountKey,
        recordType: 'deal',
        status: 'closed',
        positionId: 9001,
        profit: 300,
        closeTime: DateTime.utc(2026, 7, 3, 8),
      ),
      _mt5Record(
        id: 'position-closed-1',
        accountKey: accountKey,
        recordType: 'position',
        status: 'closed',
        positionId: 9001,
        profit: 300,
        closeTime: DateTime.utc(2026, 7, 3, 8),
      ),
      _mt5Record(
        id: 'deal-2',
        accountKey: accountKey,
        recordType: 'deal',
        status: 'closed',
        positionId: 9002,
        profit: 100,
        closeTime: DateTime.utc(2026, 7, 4, 3),
      ),
      _mt5Record(
        id: 'position-open',
        accountKey: accountKey,
        recordType: 'position',
        status: 'open',
        positionId: 9003,
        profit: 50,
        openTime: DateTime.utc(2026, 7, 4, 4),
      ),
    ];

    final metrics = calculator.evaluate(
      challenge: challenge,
      account: account,
      records: records,
      now: now,
    );

    expect(metrics.closedProfitLoss, 400);
    expect(metrics.floatingProfitLoss, 50);
    expect(metrics.accountProfit, 450);
    expect(metrics.profitTargetProgressPercent, 56.25);
    expect(metrics.tradingDaysCount, 2);
    expect(metrics.minimumDaysSatisfied, isTrue);
    expect(metrics.phase1InProgress, isTrue);
    expect(metrics.dailyLossBreach, isFalse);
    expect(metrics.maximumLossBreach, isFalse);
    expect(metrics.maximumLossUsedPercent, 0);
    expect(metrics.riskStatus, 'healthy');
  });

  test('Prop Firm targets exclude deposits and broker balance adjustments', () {
    const calculator = PropFirmCalculator();
    const accountKey = 'PUPrime-Demo_700122288';
    final challenge = PropFirmChallenge(
      id: 'challenge-deposit-proof',
      userId: 'u1',
      mt5AccountKey: accountKey,
      mt5Login: 700122288,
      brokerServer: 'PUPrime-Demo',
      propFirmName: 'Demo Prop',
      challengeName: 'Evaluation',
      startingBalance: 10000,
      phase1ProfitTargetPercent: 8,
      challengeStartDate: DateTime.utc(2026, 7, 1),
    );
    const account = Mt5Account(
      id: accountKey,
      userId: 'u1',
      accountKey: accountKey,
      login: 700122288,
      brokerServer: 'PUPrime-Demo',
      balance: 15400,
      equity: 15450,
      currency: 'USD',
      syncStatus: 'connected',
    );
    final metrics = calculator.evaluate(
      challenge: challenge,
      account: account,
      records: [
        _mt5Record(
          id: 'deposit-5000',
          accountKey: accountKey,
          recordType: 'balance',
          status: 'closed',
          positionId: 0,
          profit: 5000,
          closeTime: DateTime.utc(2026, 7, 2),
        ),
        _mt5Record(
          id: 'trade-profit',
          accountKey: accountKey,
          recordType: 'deal',
          status: 'closed',
          positionId: 100,
          profit: 400,
          closeTime: DateTime.utc(2026, 7, 3),
        ),
        _mt5Record(
          id: 'open-profit',
          accountKey: accountKey,
          recordType: 'position',
          status: 'open',
          positionId: 101,
          profit: 50,
          openTime: DateTime.utc(2026, 7, 4),
        ),
      ],
      now: DateTime.utc(2026, 7, 4, 12),
    );

    expect(metrics.currentBalance, 15400);
    expect(metrics.accountProfit, 450);
    expect(metrics.accountProfitPercent, 4.5);
    expect(metrics.profitTargetProgressPercent, 56.25);
  });

  test(
    'Prop Firm calculator detects maximum loss breach from synced MT5 equity',
    () {
      const calculator = PropFirmCalculator();
      const accountKey = 'PUPrime-Demo_700122288';
      final challenge = PropFirmChallenge(
        id: 'challenge-breach',
        userId: 'u1',
        mt5AccountKey: accountKey,
        mt5Login: 700122288,
        brokerServer: 'PUPrime-Demo',
        propFirmName: 'Demo Prop',
        challengeName: 'Evaluation',
        startingBalance: 10000,
        maximumLossLimitPercent: 10,
        challengeStartDate: DateTime.utc(2026, 7, 1),
      );
      const account = Mt5Account(
        id: accountKey,
        userId: 'u1',
        accountKey: accountKey,
        login: 700122288,
        brokerServer: 'PUPrime-Demo',
        balance: 8900,
        equity: 8900,
        currency: 'USD',
        syncStatus: 'connected',
      );

      final metrics = calculator.evaluate(
        challenge: challenge,
        account: account,
        records: [
          _mt5Record(
            id: 'deal-loss',
            accountKey: accountKey,
            recordType: 'deal',
            status: 'closed',
            positionId: 9100,
            profit: -1100,
            closeTime: DateTime.utc(2026, 7, 4, 6),
          ),
        ],
        now: DateTime.utc(2026, 7, 4, 12),
      );

      expect(metrics.maximumLossBreach, isTrue);
      expect(metrics.currentDrawdownAmount, 1100);
      expect(metrics.maximumLossUsedPercent, 110);
      expect(metrics.failed, isTrue);
      expect(metrics.riskStatus, 'breached');
    },
  );

  test('Prop Firm calculator passes phase 2 with MT5 synced records', () {
    const calculator = PropFirmCalculator();
    const accountKey = 'PUPrime-Demo_700122288';
    final challenge = PropFirmChallenge(
      id: 'challenge-phase-2',
      userId: 'u1',
      mt5AccountKey: accountKey,
      mt5Login: 700122288,
      brokerServer: 'PUPrime-Demo',
      propFirmName: 'Demo Prop',
      challengeName: 'Evaluation',
      currentPhase: 'phase_2',
      startingBalance: 10000,
      phase2ProfitTargetPercent: 5,
      minimumTradingDaysPhase2: 2,
      challengeStartDate: DateTime.utc(2026, 7, 1),
    );
    const account = Mt5Account(
      id: accountKey,
      userId: 'u1',
      accountKey: accountKey,
      login: 700122288,
      brokerServer: 'PUPrime-Demo',
      balance: 10550,
      equity: 10550,
      currency: 'USD',
      syncStatus: 'connected',
    );

    final metrics = calculator.evaluate(
      challenge: challenge,
      account: account,
      records: [
        _mt5Record(
          id: 'phase2-deal-1',
          accountKey: accountKey,
          recordType: 'deal',
          status: 'closed',
          positionId: 9201,
          profit: 300,
          closeTime: DateTime.utc(2026, 7, 2, 9),
        ),
        _mt5Record(
          id: 'phase2-deal-2',
          accountKey: accountKey,
          recordType: 'deal',
          status: 'closed',
          positionId: 9202,
          profit: 250,
          closeTime: DateTime.utc(2026, 7, 3, 9),
        ),
      ],
      now: DateTime.utc(2026, 7, 4, 12),
    );

    expect(metrics.currentPhase, 'phase_2');
    expect(metrics.targetReached, isTrue);
    expect(metrics.minimumDaysSatisfied, isTrue);
    expect(metrics.phase2Passed, isTrue);
    expect(metrics.phase2InProgress, isFalse);
    expect(metrics.riskStatus, 'passed');
  });

  test('Prop Firm calculator reports funded account status safely', () {
    const calculator = PropFirmCalculator();
    const accountKey = 'PUPrime-Demo_700122288';
    final challenge = PropFirmChallenge(
      id: 'challenge-funded',
      userId: 'u1',
      mt5AccountKey: accountKey,
      mt5Login: 700122288,
      brokerServer: 'PUPrime-Demo',
      propFirmName: 'Demo Prop',
      challengeName: 'Funded',
      currentPhase: 'funded',
      startingBalance: 10000,
      fundedStartedAt: DateTime.utc(2026, 7, 5),
    );
    const account = Mt5Account(
      id: accountKey,
      userId: 'u1',
      accountKey: accountKey,
      login: 700122288,
      brokerServer: 'PUPrime-Demo',
      balance: 11200,
      equity: 11200,
      currency: 'USD',
      syncStatus: 'connected',
    );

    final metrics = calculator.evaluate(
      challenge: challenge,
      account: account,
      records: const [],
      now: DateTime.utc(2026, 7, 6, 12),
    );

    expect(metrics.funded, isTrue);
    expect(metrics.phase1InProgress, isFalse);
    expect(metrics.phase2InProgress, isFalse);
    expect(metrics.failed, isFalse);
    expect(metrics.riskStatus, 'passed');
  });

  test('Prop Firm calculator detects daily loss breach from MT5 deals', () {
    const calculator = PropFirmCalculator();
    const accountKey = 'PUPrime-Demo_700122288';
    final challenge = PropFirmChallenge(
      id: 'challenge-daily-breach',
      userId: 'u1',
      mt5AccountKey: accountKey,
      mt5Login: 700122288,
      brokerServer: 'PUPrime-Demo',
      propFirmName: 'Demo Prop',
      challengeName: 'Evaluation',
      startingBalance: 10000,
      dailyLossLimitPercent: 5,
      maximumLossLimitPercent: 10,
      challengeStartDate: DateTime.utc(2026, 7, 1),
    );
    const account = Mt5Account(
      id: accountKey,
      userId: 'u1',
      accountKey: accountKey,
      login: 700122288,
      brokerServer: 'PUPrime-Demo',
      balance: 9400,
      equity: 9400,
      currency: 'USD',
      syncStatus: 'connected',
    );

    final metrics = calculator.evaluate(
      challenge: challenge,
      account: account,
      records: [
        _mt5Record(
          id: 'daily-loss-deal',
          accountKey: accountKey,
          recordType: 'deal',
          status: 'closed',
          positionId: 9301,
          profit: -600,
          closeTime: DateTime.utc(2026, 7, 4, 6),
        ),
      ],
      now: DateTime.utc(2026, 7, 4, 12),
    );

    expect(metrics.dailyLossLimitAmount, 500);
    expect(metrics.dailyLossUsedAmount, 600);
    expect(metrics.dailyLossBreach, isTrue);
    expect(metrics.maximumLossBreach, isFalse);
    expect(metrics.failed, isTrue);
    expect(metrics.riskStatus, 'breached');
  });

  test('English and Simplified Chinese localize core labels and roles', () {
    const english = AppLocalizations(Locale('en'));
    const chinese = AppLocalizations(Locale('zh'));

    expect(english.text('Trading appointments'), 'Trading appointments');
    expect(chinese.text('Trading appointments'), '交易预约');
    expect(english.roleName(750), 'Trader');
    expect(
      chinese.text('Synced MT5 Trades'),
      '\u5df2\u540c\u6b65 MT5 \u4ea4\u6613',
    );
    expect(chinese.text('2-Step Challenge'), '\u4e24\u9636\u6bb5\u6311\u6218');
    expect(chinese.text('Link MT5 Account'), '\u7ed1\u5b9a MT5 \u8d26\u6237');
    expect(chinese.text('Manual Data Only'), '\u4ec5\u624b\u52a8\u6570\u636e');
    expect(
      chinese.text('MT5 Synced Data Only'),
      '\u4ec5 MT5 \u540c\u6b65\u6570\u636e',
    );
    expect(
      chinese.text('Client access'),
      '\u5ba2\u6237\u8bbf\u95ee\u6743\u9650',
    );
    expect(
      chinese.text('Client access required'),
      '\u9700\u8981\u5ba2\u6237\u8bbf\u95ee\u6743\u9650',
    );
    expect(
      chinese.text(
        'Performance data took too long to load. Check your connection and try again.',
      ),
      '\u8868\u73b0\u6570\u636e\u52a0\u8f7d\u65f6\u95f4\u8fc7\u957f\u3002\u8bf7\u68c0\u67e5\u7f51\u7edc\u8fde\u63a5\u540e\u91cd\u8bd5\u3002',
    );
    expect(
      chinese.text('Unable to load trader performance. Please try again.'),
      '\u65e0\u6cd5\u52a0\u8f7d\u4ea4\u6613\u8005\u8868\u73b0\u6570\u636e\uff0c\u8bf7\u91cd\u8bd5\u3002',
    );
    for (final key in const [
      'Cancelled pending order',
      'Expired pending order',
      'Rejected pending order',
      'Excluded because order was never executed',
      'Executed trade',
      'Break-even executed trade',
      'Trade duration unavailable',
      'No valid entry deal',
      'No valid position',
    ]) {
      expect(chinese.text(key), isNot(key));
    }
    expect(chinese.roleName(750), '交易员');
  });

  test('localized Firebase text falls back safely in both languages', () {
    const text = LocalizedText({
      'en': 'Market Structure Basics',
      'zh': '市场结构基础',
    });
    const englishOnly = LocalizedText({'en': 'Risk plan'});
    const chineseOnly = LocalizedText({'zh': '风险计划'});

    expect(text.textForLanguage('zh'), '市场结构基础');
    expect(text.textForLanguage('en'), 'Market Structure Basics');
    expect(englishOnly.textForLanguage('zh'), 'Risk plan');
    expect(chineseOnly.textForLanguage('en'), '风险计划');
    expect(localizedValue('Legacy English', 'zh'), 'Legacy English');
    expect(getLocalizedText({'en': 'Risk plan'}, 'zh'), 'Risk plan');
    expect(getLocalizedText({'zh': '风险计划'}, 'en'), '风险计划');
    expect(getLocalizedText({}, 'zh'), '');
  });

  test('daily exam parser supports admin option maps with i18n sidecars', () {
    final question = TradingExamQuestionMobile.fromJson({
      'id': 'risk-map',
      'question_text': 'What does BOS mean?',
      'question_text_i18n': {'en': 'What does BOS mean?', 'zh': 'BOS 代表什么？'},
      'options': {
        'A': {
          'id': 'A',
          'option_text': 'Break of Structure',
          'option_text_i18n': {'en': 'Break of Structure', 'zh': '结构突破'},
          'is_correct': true,
        },
        'B': {
          'id': 'B',
          'option_text': 'Guaranteed profit',
          'option_text_i18n': {'en': 'Guaranteed profit', 'zh': '保证盈利'},
        },
      },
      'status': 'approved',
    });

    expect(question.options, hasLength(2));
    expect(question.localizedQuestionText('zh'), 'BOS 代表什么？');
    expect(question.options.first.localizedText('zh'), '结构突破');
    expect(question.correctOptionIndex, 0);
  });

  test('final exam falls back to bundled Chinese for known seed content', () {
    final attempt = FinalExamAttemptMobile.fromJson({
      'id': 'attempt-1',
      'status': 'in_progress',
      'total_questions': 1,
      'questions': [
        {
          'attempt_question_id': 'q1',
          'position': 1,
          'category': 'Technique and Analysis Skills',
          'question_type': 'multiple_choice',
          'question': 'What is best practice for Scaling out?',
          'options': {
            'A': 'take partials only by plan',
            'B': 'increase risk without a plan',
            'C': 'ignore setup invalidation',
            'D': 'decide from emotion only',
          },
        },
      ],
    });
    final question = attempt.questions.single;

    expect(question.localizedCategory('zh'), '技术与分析技能');
    expect(question.localizedQuestion('zh'), '分批止盈的最佳做法是什么？');
    expect(question.localizedOption('A', 'zh'), '只按计划分批止盈');
    expect(question.localizedOption('B', 'zh'), '无计划地增加风险');
  });

  test('final exam parser reads migrated i18n sidecars on old attempts', () {
    const zhCategory = '\u65e7\u5206\u7c7b';
    const zhQuestion = '\u65e7\u9898\u76ee';
    const zhOption = '\u65e7\u9009\u9879';
    final attempt = FinalExamAttemptMobile.fromJson({
      'id': 'attempt-2',
      'status': 'in_progress',
      'total_questions': 1,
      'questions': [
        {
          'attempt_question_id': 'q1',
          'position': 1,
          'category': 'Old category',
          'category_i18n': {'en': 'Old category', 'zh': zhCategory},
          'question_type': 'multiple_choice',
          'question': 'Old question',
          'question_i18n': {'en': 'Old question', 'zh': zhQuestion},
          'options': {'A': 'Old option'},
          'options_i18n': {
            'A': {'en': 'Old option', 'zh': zhOption},
          },
        },
      ],
    });
    final question = attempt.questions.single;

    expect(question.localizedCategory('zh'), zhCategory);
    expect(question.localizedQuestion('zh'), zhQuestion);
    expect(question.localizedOption('A', 'zh'), zhOption);
    expect(question.localizedOption('A', 'en'), 'Old option');
  });

  test(
    'final exam translates written-plan snapshot options in Chinese mode',
    () {
      final attempt = FinalExamAttemptMobile.fromJson({
        'id': 'attempt-written-plan',
        'status': 'in_progress',
        'total_questions': 1,
        'questions': [
          {
            'attempt_question_id': 'question-0050',
            'question_id': 'question-0050',
            'position': 6,
            'category': 'Trading Psychology',
            'question_type': 'multiple_choice',
            'question': 'Why should a trader follow a written plan?',
            'options': {
              'A': 'To improve consistency',
              'B': 'To chase losses',
              'C': 'To remove stops',
              'D': 'To increase emotional decisions',
            },
            'answer_key': 'A',
          },
        ],
      });
      final question = attempt.questions.single;

      expect(
        question.localizedQuestion('zh'),
        '\u4ea4\u6613\u8005\u4e3a\u4ec0\u4e48\u5e94\u8be5\u9075\u5faa'
        '\u4e66\u9762\u4ea4\u6613\u8ba1\u5212\uff1f',
      );
      expect(
        question.localizedOption('A', 'zh'),
        '\u63d0\u9ad8\u4e00\u81f4\u6027',
      );
      expect(question.localizedOption('B', 'zh'), '\u8ffd\u56de\u4e8f\u635f');
      expect(question.localizedOption('C', 'zh'), '\u79fb\u9664\u6b62\u635f');
      expect(
        question.localizedOption('D', 'zh'),
        '\u589e\u52a0\u60c5\u7eea\u5316\u51b3\u7b56',
      );
      expect(question.answerKey, 'A');
    },
  );

  test(
    'final exam translates old generated technique questions and answers',
    () {
      final attempt = FinalExamAttemptMobile.fromJson({
        'id': 'attempt-3',
        'status': 'in_progress',
        'total_questions': 2,
        'questions': [
          {
            'attempt_question_id': 'q0',
            'position': 2,
            'category': 'Technique and Analysis Skills',
            'question_type': 'multiple_choice',
            'question': 'What best describes Support?',
            'question_i18n': {
              'en': 'What best describes Support?',
              'zh': 'What best describes Support?',
            },
            'options': {
              'A': 'is an area where buyers reacted',
              'B': 'increase risk without a plan',
              'C': 'ignore setup invalidation',
              'D': 'decide from emotion only',
            },
            'options_i18n': {
              'A': {
                'en': 'is an area where buyers reacted',
                'zh': 'is an area where buyers reacted',
              },
            },
          },
          {
            'attempt_question_id': 'q1',
            'position': 3,
            'category': 'Technique and Analysis Skills',
            'question_type': 'multiple_choice',
            'question': 'What best describes Higher low?',
            'options': {
              'A': 'supports bullish structure',
              'B': 'increase risk without a plan',
              'C': 'ignore setup invalidation',
              'D': 'decide from emotion only',
            },
          },
        ],
      });
      final support = attempt.questions.first;
      final question = attempt.questions.last;

      expect(support.localizedCategory('zh'), '技术与分析技能');
      expect(support.localizedQuestion('zh'), '以下哪项最能描述支撑？');
      expect(support.localizedOption('A', 'zh'), '是买方曾经反应的区域');
      expect(support.localizedOption('B', 'zh'), '无计划地增加风险');
      expect(support.localizedOption('C', 'zh'), '忽视交易设定的失效条件');
      expect(support.localizedOption('D', 'zh'), '只凭情绪决定');
      expect(question.localizedCategory('zh'), '技术与分析技能');
      expect(question.localizedQuestion('zh'), '以下哪项最能描述更高低点？');
      expect(question.localizedOption('A', 'zh'), '支撑看涨结构');
      expect(question.localizedOption('B', 'zh'), '无计划地增加风险');
      expect(question.localizedOption('C', 'zh'), '忽视交易设定的失效条件');
      expect(question.localizedOption('D', 'zh'), '只凭情绪决定');
    },
  );

  test('final exam translates current generated question families', () {
    final attempt = FinalExamAttemptMobile.fromJson({
      'id': 'attempt-4',
      'status': 'in_progress',
      'total_questions': 3,
      'questions': [
        {
          'attempt_question_id': 'foundation',
          'position': 1,
          'category': 'Foundation and Knowledge',
          'subcategory': 'Risk Management',
          'question_type': 'text_mcq',
          'question':
              'Foundation definition: what does Risk Management mean in trading?',
          'options': {
            'A': 'Rules controlling how much capital is exposed to loss.',
            'B': 'A guaranteed signal that the next trade will win.',
          },
        },
        {
          'attempt_question_id': 'chart',
          'position': 2,
          'category': 'Technique and Analysis Skills',
          'subcategory': 'Market Structure',
          'question_type': 'chart_mcq',
          'question':
              'Technique chart case 17: based on the chart, what is the most accurate technical read for Bullish BOS?',
          'options': {
            'A': 'A bullish break of structure is shown.',
            'B': 'No valid information can be taken from the chart.',
          },
        },
        {
          'attempt_question_id': 'psychology',
          'position': 3,
          'category': 'Psychology',
          'subcategory': 'Revenge Trading',
          'question_type': 'chart_scenario_mcq',
          'scenario_text':
              'After two losses, a fast candle appears and the trader wants to recover quickly.',
          'question':
              'Psychology chart case 9: after seeing this chart, what is the best trader behavior?',
          'options': {
            'A': 'Pause and only trade a valid planned setup later.',
            'B':
                'Enter immediately with larger lot to maximize or recover quickly.',
          },
        },
      ],
    });
    final foundation = attempt.questions[0];
    final chart = attempt.questions[1];
    final psychology = attempt.questions[2];

    expect(foundation.localizedQuestion('zh'), '基础定义：风险管理在交易中是什么意思？');
    expect(foundation.localizedOption('A', 'zh'), '控制有多少资金暴露在亏损风险中的规则。');
    expect(
      chart.localizedQuestion('zh'),
      '技术图表案例 17：根据图表，关于看涨结构突破最准确的技术解读是什么？',
    );
    expect(chart.localizedOption('A', 'zh'), '图表显示看涨结构突破。');
    expect(psychology.localizedSubcategory('zh'), '报复性交易');
    expect(
      psychology.localizedScenarioText('zh'),
      '连续两笔亏损后，一根快速K线出现，交易者想快速追回亏损。',
    );
    expect(psychology.localizedQuestion('zh'), '交易心理图表案例 9：看到这张图后，最佳交易行为是什么？');
    expect(psychology.localizedOption('A', 'zh'), '暂停，之后只交易有效且计划内的设定。');
  });

  test('daily examination attempts persist a durable answer review', () {
    const question = TradingExamQuestionMobile(
      id: 'risk-1',
      questionText: 'What is fixed risk?',
      explanation: 'Risk stays consistent across trades.',
      options: [
        TradingExamOptionMobile(id: 'a', optionText: 'Random sizing'),
        TradingExamOptionMobile(
          id: 'b',
          optionText: 'A predefined account percentage',
          isCorrect: true,
        ),
      ],
    );
    final review = TradingExamQuestionResultMobile.fromQuestion(question, 0);
    final attempt = TradingExamAttemptMobile(
      id: 'exam-2026-06-21',
      userId: 'u1',
      examDate: DateTime(2026, 6, 21),
      totalQuestions: 1,
      score: 0,
      answers: const {'risk-1': 0},
      questionResults: [review],
      passingPercentage: 60,
    );

    final stored = attempt.toFirestore();
    final storedReview = (stored['question_results']! as List).single as Map;
    expect(storedReview['selected_index'], 0);
    expect(storedReview['correct_index'], 1);
    expect(storedReview['explanation'], isNotEmpty);
    expect(attempt.wrongAnswers, 1);
    expect(attempt.passed, isFalse);
  });

  test('MT5 balance operations are excluded from trading activity', () {
    const balance = Mt5TradeRecord(
      id: 'balance-1',
      userId: 'u1',
      accountKey: 'a1',
      mt5Login: 123,
      brokerServer: 'Broker-Demo',
      symbol: '',
      tradeType: 'balance',
      recordType: 'deal',
      status: 'closed',
      uniqueMt5Id: '1',
      dealTicket: 1,
      profit: 1000,
    );
    const trade = Mt5TradeRecord(
      id: 'trade-1',
      userId: 'u1',
      accountKey: 'a1',
      mt5Login: 123,
      brokerServer: 'Broker-Demo',
      symbol: 'EURUSD',
      tradeType: 'buy',
      recordType: 'position',
      status: 'closed',
      uniqueMt5Id: '2',
      positionId: 2,
    );
    expect(balance.isTradingActivity, isFalse);
    expect(trade.isTradingActivity, isTrue);
  });

  test(
    'MT5 annotations merge without changing broker-authoritative fields',
    () {
      const record = Mt5TradeRecord(
        id: 'trade-1',
        userId: 'u1',
        accountKey: 'a1',
        mt5Login: 123,
        brokerServer: 'Broker-Demo',
        symbol: 'XAUUSD',
        tradeType: 'sell',
        recordType: 'position',
        status: 'closed',
        uniqueMt5Id: '77',
        positionId: 77,
        lotSize: .2,
        profit: 25,
      );
      final annotation = Mt5JournalAnnotation(
        id: Mt5JournalAnnotation.idForLifecycle(record.canonicalLifecycleKey),
        userId: 'u1',
        accountId: 'a1',
        lifecycleKey: record.canonicalLifecycleKey,
        psychologyNotes: 'Waited for confirmation',
        lessonLearned: 'Keep the same risk',
        customTags: const ['disciplined'],
      );
      final merged = record.withAnnotation(annotation);
      expect(merged.symbol, 'XAUUSD');
      expect(merged.lotSize, .2);
      expect(merged.profit, 25);
      expect(merged.psychologyNotes, 'Waited for confirmation');
      expect(merged.customTags, ['disciplined']);
    },
  );

  test('strategy messages deduplicate across normalized sections', () {
    const warning =
        'Invalid when direction, setup, location and confirmation do not align';
    expect(uniqueNormalizedText([warning, ' $warning. ']), [warning]);
    expect(excludingNormalizedText([warning, 'Risk 1%'], [warning]), [
      'Risk 1%',
    ]);
  });
}

const _baseRecommendationAnswers = <String, String>{
  'experience_length': 'more_than_three',
  'consistency': 'consistent_disciplined',
  'account_type': 'personal_live',
  'personality': 'analytical',
  'loss_reaction': 'accept_and_stop',
  'biggest_weakness': 'not_following_plan',
  'risk_per_trade': '1',
  'daily_loss': '3',
  'weekly_loss': '8',
  'trades_per_day': '3',
  'minimum_rr': '2',
  'screen_time': 'one_to_three',
  'session': 'london',
  'opportunity_preference': 'balanced',
  'selected_strategy': 'market_structure',
  'has_strategy': 'yes',
};

const _baseStrategyRecommendationAnswers = <String, String>{
  'technical_concept': 'market_structure',
  'setup_type': 'pullback_continuation',
  'market_condition': 'trending',
  'trading_style': 'intraday',
  'direction_timeframe': 'h1',
  'entry_timeframe': 'm15',
  'multi_timeframe': 'yes',
  'confirmations': 'two',
  'trusted_confirmation': 'retest',
  'entry_execution': 'limit_order',
  'take_profit_method': 'fixed_rr',
  'stop_loss_method': 'structure',
};

TradingPlanQuestionnaireResponse _questionnaireResponse(
  Map<String, String> answers,
) {
  return TradingPlanQuestionnaireResponse(answers: answers);
}

TradingStrategyQuestionnaireResponse _strategyQuestionnaireResponse(
  Map<String, String> answers,
) {
  return TradingStrategyQuestionnaireResponse(answers: answers);
}

TradingJournalEntry _trade(String id, DateTime date, double profitLoss) {
  return TradingJournalEntry(
    id: id,
    userId: 'u1',
    openDate: date.subtract(const Duration(hours: 1)),
    closeDate: date,
    pair: 'XAUUSD',
    direction: 1,
    entryPrice: 2000,
    exitPrice: 2001,
    lotSize: .1,
    pips: 100,
    profitLoss: profitLoss,
    result: profitLoss > 0 ? 1 : 2,
  );
}

Mt5TradeRecord _mt5Record({
  required String id,
  required String accountKey,
  required String recordType,
  required String status,
  required int positionId,
  required double profit,
  DateTime? openTime,
  DateTime? closeTime,
}) {
  return Mt5TradeRecord(
    id: id,
    userId: 'u1',
    accountKey: accountKey,
    mt5Login: 700122288,
    brokerServer: 'PUPrime-Demo',
    symbol: 'BTCUSD',
    tradeType: 'buy',
    recordType: recordType,
    status: status,
    uniqueMt5Id: id,
    dealTicket: recordType == 'deal' ? id.hashCode.abs() : null,
    positionId: positionId,
    lotSize: 0.1,
    entryPrice: 62000,
    currentPrice: 62100,
    openTime: openTime ?? closeTime?.subtract(const Duration(minutes: 30)),
    closeTime: closeTime,
    profit: profit,
    source: 'mt5_sync',
  );
}
