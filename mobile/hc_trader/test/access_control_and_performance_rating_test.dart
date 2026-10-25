import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/security/access_policy.dart';
import 'package:hc_trader/core/security/app_role_permissions.dart';
import 'package:hc_trader/core/security/permission_service.dart';
import 'package:hc_trader/models/access_status.dart';
import 'package:hc_trader/models/app_user.dart';
import 'package:hc_trader/models/performance_rating.dart';
import 'package:hc_trader/models/standalone_models.dart';
import 'package:hc_trader/models/trading_data_source.dart';
import 'package:hc_trader/models/trading_journal_entry.dart';
import 'package:hc_trader/services/performance_rating_service.dart';

void main() {
  group('central permission service', () {
    test('potential client can use every partial-public feature', () {
      final permissions = PermissionService(_user(role: 700));
      expect(permissions.can(AppPermission.toolkitUse), isTrue);
      expect(permissions.can(AppPermission.journalCreateOwn), isTrue);
      expect(permissions.can(AppPermission.psychologyCreateOwn), isTrue);
      expect(permissions.can(AppPermission.tradingPlanCreateOwn), isTrue);
      expect(permissions.can(AppPermission.tradingStrategyCreateOwn), isTrue);
      expect(permissions.can(AppPermission.statisticsReadOwn), isTrue);
      expect(permissions.can(AppPermission.mt5SyncOwn), isFalse);
      expect(
        ClientAccessPolicy.canAccess(_user(role: 700), AppFeatureArea.journal),
        isTrue,
      );
    });

    test(
      'approved trader receives client capabilities but not administration',
      () {
        final permissions = PermissionService(
          _user(role: 750, access: AccessStatus.approved),
        );
        expect(permissions.can(AppPermission.mt5SyncOwn), isTrue);
        expect(permissions.can(AppPermission.performanceRatingReadOwn), isTrue);
        expect(permissions.can(AppPermission.usersReadAll), isFalse);
        expect(
          permissions.can(
            AppPermission.journalReadOwn,
            resource: const ResourceContext(ownerUserId: 'another-user'),
          ),
          isFalse,
        );
      },
    );

    test('publisher and analyst permissions remain separated', () {
      final provider = PermissionService(
        _user(role: RolePermissionMatrix.signalProvider),
      );
      final analyst = PermissionService(
        _user(role: RolePermissionMatrix.marketAnalyst),
      );
      expect(provider.can(AppPermission.signalsPublishOwn), isTrue);
      expect(provider.can(AppPermission.signalsManageAll), isFalse);
      expect(analyst.can(AppPermission.marketAnalysisPublishOwn), isTrue);
      expect(analyst.can(AppPermission.signalsPublishOwn), isFalse);
    });

    test(
      'market analyst role still follows customer access application SOP',
      () {
        final unapprovedUser = _user(role: RolePermissionMatrix.marketAnalyst);
        final unapproved = PermissionService(unapprovedUser);
        expect(unapproved.can(AppPermission.accessCreateOwn), isTrue);
        expect(unapproved.can(AppPermission.mt5ConnectOwn), isFalse);
        expect(
          ClientAccessPolicy.isRestricted(
            unapprovedUser,
            AppFeatureArea.accounts,
          ),
          isTrue,
        );

        final approvedUser = _user(
          role: RolePermissionMatrix.marketAnalyst,
          access: AccessStatus.approved,
        );
        final approved = PermissionService(approvedUser);
        expect(approved.can(AppPermission.mt5ConnectOwn), isTrue);
        expect(approved.can(AppPermission.mt5SyncOwn), isTrue);
        expect(
          ClientAccessPolicy.isRestricted(
            approvedUser,
            AppFeatureArea.accounts,
          ),
          isFalse,
        );
      },
    );

    test('leader, recruiter, consultant and evaluator are scope limited', () {
      final leader = PermissionService(
        _user(role: 760, teamIds: const {'team-a'}),
      );
      expect(
        leader.can(
          AppPermission.teamReadAssigned,
          resource: const ResourceContext(teamId: 'team-a'),
        ),
        isTrue,
      );
      expect(
        leader.can(
          AppPermission.teamReadAssigned,
          resource: const ResourceContext(teamId: 'team-b'),
        ),
        isFalse,
      );
      final recruiter = PermissionService(
        _user(role: 770, assignedClients: const {'referral-a'}),
      );
      expect(
        recruiter.can(
          AppPermission.usersReadAssigned,
          resource: const ResourceContext(clientUserId: 'referral-a'),
        ),
        isTrue,
      );
      expect(
        PermissionService(
          _user(role: 790, assignedClients: const {'client-a'}),
        ).can(
          AppPermission.clientsReadAssigned,
          resource: const ResourceContext(clientUserId: 'client-b'),
        ),
        isFalse,
      );
      expect(
        PermissionService(_user(role: 800, evaluations: const {'case-a'})).can(
          AppPermission.evaluationsReadAssigned,
          resource: const ResourceContext(evaluationId: 'case-a'),
        ),
        isTrue,
      );
    });

    test(
      'privileged assignment follows admin assistant, founder and super admin policy',
      () {
        final assistant = PermissionService(_user(role: 780));
        final founder = PermissionService(_user(role: 3));
        final superAdmin = PermissionService(_user(role: 1));
        expect(assistant.canAssignRole(1), isFalse);
        expect(founder.canAssignRole(750), isTrue);
        expect(founder.canAssignRole(1), isFalse);
        expect(superAdmin.canAssignRole(1), isTrue);
        expect(superAdmin.can(AppPermission.permissionsManage), isTrue);
      },
    );

    test('explicit deny and suspension take precedence over role grants', () {
      final denied = PermissionService(
        _user(role: 1, overrides: const {AppPermission.usersReadAll: false}),
      );
      expect(denied.can(AppPermission.usersReadAll), isFalse);
      final suspended = _user(
        role: 1,
        access: AccessStatus.suspended,
        suspended: true,
      );
      expect(
        PermissionService(suspended).can(AppPermission.rolesManage),
        isFalse,
      );
      expect(
        ClientAccessPolicy.canAccess(suspended, AppFeatureArea.admin),
        isFalse,
      );
    });

    test('multiple compatible roles are evaluated as a permission union', () {
      final user = AppUser(
        uid: 'user-1',
        email: 'user@example.com',
        roleId: 750,
        roleIds: const {750, 201, 501},
        accessStatus: AccessStatus.approved,
      );
      final permissions = PermissionService(user);
      expect(permissions.can(AppPermission.mt5SyncOwn), isTrue);
      expect(permissions.can(AppPermission.signalsPublishOwn), isTrue);
      expect(permissions.can(AppPermission.marketAnalysisPublishOwn), isTrue);
    });
  });

  group('performance rating engine', () {
    const service = PerformanceRatingService();
    final date = DateTime(2026, 7, 13);

    test(
      'profitable compliant and losing compliant days separate result from discipline',
      () {
        final profitable = _daily(service, date, [
          _trade('win', 100, checklist: const {'stop_loss': true}),
        ]);
        final losing = _daily(service, date, [
          _trade('loss', -30, checklist: const {'stop_loss': true}),
        ]);
        expect(profitable.tradingResultScore, greaterThan(50));
        expect(profitable.tradingDisciplineScore, 100);
        expect(losing.tradingResultScore, lessThan(50));
        expect(losing.tradingDisciplineScore, 100);
      },
    );

    test('profitable excessive-risk day is capped for discipline', () {
      final rating = _daily(service, date, [
        _trade('large-loss', -100),
        _trade('larger-win', 300, minute: 90),
      ]);
      expect(rating.tradingResultScore, greaterThan(50));
      expect(rating.violations, contains('risk_per_trade_exceeded'));
      expect(rating.tradingDisciplineScore, lessThanOrEqualTo(45));
    });

    test(
      'missing stop, daily loss, max risk, overtrading and RR have explainable codes',
      () {
        final missingStop = _daily(service, date, [
          _trade('no-sl', 50, checklist: const {'stop_loss': false}),
        ]);
        expect(missingStop.violations, contains('missing_stop_loss'));
        final loss = _daily(service, date, [_trade('daily-loss', -600)]);
        expect(loss.violations, contains('daily_loss_limit_exceeded'));
        expect(loss.violations, contains('risk_per_trade_exceeded'));
        final overtrading = _daily(
          service,
          date,
          List.generate(
            9,
            (index) => _trade('trade-$index', 2, minute: index * 5),
          ),
        );
        expect(overtrading.violations, contains('maximum_trades_exceeded'));
        final rr = _daily(service, date, [
          _trade('low-rr', 5, checklist: const {'risk_reward_met': false}),
        ]);
        expect(rr.violations, contains('risk_reward_below_plan'));
      },
    );

    test(
      'possible revenge pattern requires timing and position-size evidence',
      () {
        final rating = _daily(service, date, [
          _trade('loss', -10, lot: 1),
          _trade('reentry', 20, minute: 20, lot: 1.5),
        ]);
        expect(rating.violations, contains('possible_revenge_trading'));
      },
    );

    test('psychology missing is unknown while a positive check is scored', () {
      final missing = _daily(service, date, [
        _trade('trade', 10),
      ], psych: const []);
      expect(missing.psychologyScore, isNull);
      expect(missing.missingData, contains('psychology_check_missing'));
      final completed = _daily(service, date, [_trade('trade', 10)]);
      expect(completed.psychologyScore, greaterThan(50));
    });

    test(
      'no trades, cancelled orders, deposits and withdrawals remain neutral and excluded',
      () {
        final noTrades = _daily(service, date, const []);
        expect(noTrades.tradingResultScore, 50);
        expect(noTrades.missingData, contains('no_trades_for_period'));
        final excluded = _daily(service, date, [
          _trade('cancelled', 0, status: 'cancelled'),
          _trade('deposit', 1000, type: 'deposit'),
          _trade('withdrawal', -500, type: 'withdrawal'),
        ]);
        expect(excluded.evaluatedTradeIds, isEmpty);
        expect(excluded.excludedRecordIds, hasLength(3));
        expect(excluded.tradingResultScore, 50);
      },
    );

    test('duplicates and unrelated sources or MT5 accounts are excluded', () {
      final duplicate = _trade('same', 20);
      final manual = _daily(service, date, [
        duplicate,
        duplicate,
        _trade('mt5', 500, source: TradingRecordSource.mt5Sync),
      ]);
      expect(manual.evaluatedTradeIds, ['same']);
      final mt5 = _daily(
        service,
        date,
        [
          _trade(
            'main',
            30,
            source: TradingRecordSource.mt5Sync,
            account: 'main-account',
          ),
          _trade(
            'other',
            300,
            source: TradingRecordSource.mt5Sync,
            account: 'other-account',
          ),
        ],
        source: TradingDataDisplayMode.mt5Only,
        account: 'main-account',
      );
      expect(mt5.evaluatedTradeIds, ['main']);
    });

    test('timezone grouping uses the configured local trading date', () {
      final rating = _daily(service, date, [
        _tradeAt('timezone-trade', 10, DateTime.utc(2026, 7, 12, 17)),
      ]);
      expect(rating.evaluatedTradeIds, ['timezone-trade']);
    });

    test('historical date selects the effective trading-plan version', () {
      final rating = _daily(
        service,
        DateTime(2026, 7, 10),
        [_trade('trade', 10, day: 10)],
        plans: [
          _plan('old-v1', DateTime(2026, 7, 1), end: DateTime(2026, 7, 15)),
          _plan('new-v2', DateTime(2026, 7, 15)),
        ],
      );
      expect(rating.tradingPlanVersionId, 'old-v1');
    });

    test('daily recalculation is idempotent', () {
      final first = _daily(service, date, [_trade('trade', 10)]);
      final second = _daily(service, date, [_trade('trade', 10)]);
      expect(second.toJson(), first.toJson());
    });

    test(
      'weekly Monday-Sunday aggregation handles year boundaries and consistency',
      () {
        final monday = _daily(service, DateTime(2025, 12, 29), [
          _trade('monday', -100, day: 29, month: 12, year: 2025),
        ]);
        final friday = _daily(service, DateTime(2026, 1, 2), [
          _trade('friday', -100, day: 2, month: 1, year: 2026),
        ]);
        final weekly = service.calculateWeekly(
          userId: 'user-1',
          weekDate: DateTime(2026, 1, 2),
          timezone: 'Asia/Singapore',
          dataSource: 'manual',
          dailyRatings: [monday, friday],
        );
        expect(weekly.periodStart, DateTime(2025, 12, 29));
        expect(weekly.periodEnd, DateTime(2026, 1, 5));
        expect(weekly.dailyRatingIds, hasLength(2));
        expect(weekly.consistencyScore, lessThan(100));
      },
    );

    test('one serious high-risk day remains visible in a weekly rating', () {
      final safeDays = List.generate(
        5,
        (index) => _daily(service, DateTime(2026, 7, 13 + index), [
          _trade('safe-$index', 100, day: 13 + index),
        ]),
      );
      final risky = _daily(service, date, [
        _trade('risk', -1000, checklist: const {'stop_loss': false}),
      ]);
      final weekly = service.calculateWeekly(
        userId: 'user-1',
        weekDate: date,
        timezone: 'Asia/Singapore',
        dataSource: 'manual',
        dailyRatings: [risky, ...safeDays],
      );
      expect(weekly.overallScore, lessThanOrEqualTo(risky.overallScore + 25));
      expect(weekly.violations, contains('missing_stop_loss'));
    });

    test(
      'unknown journal fields are not converted into confirmed violations',
      () {
        final rating = _daily(service, date, [
          _trade('unknown', 10, checklist: const {}),
        ]);
        expect(rating.violations, isNot(contains('missing_stop_loss')));
        expect(rating.missingData, contains('stop_loss_data_missing'));
        expect(rating.missingData, contains('plan_checklist_incomplete'));
      },
    );
  });
}

AppUser _user({
  required int role,
  AccessStatus access = AccessStatus.unapproved,
  bool suspended = false,
  Set<String> teamIds = const {},
  Set<String> assignedClients = const {},
  Set<String> evaluations = const {},
  Map<String, bool> overrides = const {},
}) => AppUser(
  uid: 'user-1',
  email: 'user@example.com',
  roleId: role,
  roleIds: {role},
  accessStatus: access,
  isSuspended: suspended,
  teamIds: teamIds,
  assignedClientIds: assignedClients,
  assignedEvaluationIds: evaluations,
  permissionOverrides: overrides,
);

PerformanceRating _daily(
  PerformanceRatingService service,
  DateTime date,
  List<TradingJournalEntry> trades, {
  List<TradingPlanRecord>? plans,
  List<PsychologyCheckIn>? psych,
  TradingDataDisplayMode source = TradingDataDisplayMode.manualOnly,
  String? account,
}) => service.calculateDaily(
  userId: 'user-1',
  localDate: date,
  timezoneOffsetMinutes: 480,
  timezone: 'Asia/Singapore',
  dataSource: source,
  records: trades,
  planVersions: plans ?? [_plan('plan-v1', DateTime(2026, 1, 1))],
  psychologyChecks: psych ?? [_psych(date)],
  mainMt5AccountId: account,
);

TradingPlanRecord _plan(String version, DateTime start, {DateTime? end}) =>
    TradingPlanRecord(
      id: 'plan-1',
      versionId: version,
      effectiveFrom: start,
      effectiveTo: end,
      createdAt: start,
      updatedAt: start,
      plan: const TradingPlan(
        strategy: 'Measured plan',
        isActive: true,
        accountCapital: 10000,
        effectiveRiskCapital: 10000,
        riskPercent: .5,
        dailyMaxLossPercent: 5,
        maxTradesPerDay: 8,
        minimumRiskReward: 2,
        cooldownAfterLossMinutes: 30,
        allowedPairs: ['EURUSD'],
      ),
    );

PsychologyCheckIn _psych(DateTime date) => PsychologyCheckIn(
  id: 'psych-${date.toIso8601String()}',
  createdAt: DateTime.utc(date.year, date.month, date.day, 0),
  emotion: 'Calm',
  confidence: 8,
  discipline: 9,
  fomo: false,
  revengeUrge: false,
  overconfidence: false,
);

TradingJournalEntry _trade(
  String id,
  double result, {
  String status = 'closed',
  String type = 'trade',
  int minute = 0,
  double lot = 1,
  int year = 2026,
  int month = 7,
  int day = 13,
  TradingRecordSource source = TradingRecordSource.manual,
  String? account,
  Map<String, bool> checklist = const {'stop_loss': true},
}) => _tradeAt(
  id,
  result,
  DateTime.utc(year, month, day, 1, minute),
  status: status,
  type: type,
  lot: lot,
  source: source,
  account: account,
  checklist: checklist,
);

TradingJournalEntry _tradeAt(
  String id,
  double result,
  DateTime opened, {
  String status = 'closed',
  String type = 'trade',
  double lot = 1,
  TradingRecordSource source = TradingRecordSource.manual,
  String? account,
  Map<String, bool> checklist = const {'stop_loss': true},
}) => TradingJournalEntry(
  id: id,
  userId: 'user-1',
  type: type,
  source: source,
  tradingAccountId: account,
  status: status,
  openDate: opened,
  closeDate: opened.add(const Duration(minutes: 10)),
  pair: 'EURUSD',
  direction: 1,
  entryPrice: 1.1,
  exitPrice: 1.11,
  lotSize: lot,
  pips: 10,
  profitLoss: result,
  result: result > 0
      ? 1
      : result < 0
      ? 2
      : 3,
  followedTradingPlan: true,
  tradingPlanId: 'plan-1',
  planComplianceChecklist: checklist,
  followedSlRule: checklist['stop_loss'] == true,
);
