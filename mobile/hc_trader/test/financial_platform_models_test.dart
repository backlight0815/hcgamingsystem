import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/models/content_management.dart';
import 'package:hc_trader/models/leaderboard.dart';
import 'package:hc_trader/models/stock_market.dart';

void main() {
  group('localized publishing fields', () {
    test('accepts a complete English-only publication', () {
      const fields = LocalizedPublishingFields(
        titleEn: 'Risk management',
        contentEn: 'Verified educational content.',
      );

      expect(fields.validateForPublish(), isEmpty);
      expect(fields.localizedTitle('zh'), 'Risk management');
      expect(fields.toFirestore()['available_languages'], ['en']);
    });

    test('accepts a complete Chinese-only publication with fallback', () {
      const fields = LocalizedPublishingFields(
        titleZh: '风险管理',
        contentZh: '经审核的教育内容。',
      );

      expect(fields.validateForPublish(), isEmpty);
      expect(fields.localizedContent('en'), '经审核的教育内容。');
      expect(fields.toFirestore()['available_languages'], ['zh']);
    });

    test('rejects an empty publication', () {
      expect(
        const LocalizedPublishingFields().validateForPublish(),
        hasLength(2),
      );
    });
  });

  test('financial report requires verified source references', () {
    final report = FinancialDailyReport.fromMap('2026-07-16', {
      'symbol': 'aapl',
      'report_date': '2026-07-16T00:00:00Z',
      'report_status': 'under_review',
      'title_i18n': {'zh': '苹果公司财经日报'},
      'content_i18n': {'zh': '已核实事实与分析。'},
      'data_timestamp': '2026-07-16T08:00:00Z',
      'version': 4,
    });

    expect(report.symbol, 'AAPL');
    expect(report.status, FinancialReportStatus.underReview);
    expect(report.localizedTitle('en'), '苹果公司财经日报');
    expect(report.version, 4);
    expect(
      report.validateForPublish(),
      contains('At least one source reference is required.'),
    );
  });

  test(
    'risk-adjusted leaderboard score enforces eligibility and penalties',
    () {
      final eligible = calculateRiskAdjustedLeaderboardScore(
        returnPercentage: 20,
        maxDrawdownPercentage: 4,
        disciplineScore: 90,
        consistencyScore: 80,
        tradeCount: 20,
        tradingDays: 8,
      );
      final highDrawdown = calculateRiskAdjustedLeaderboardScore(
        returnPercentage: 20,
        maxDrawdownPercentage: 12,
        disciplineScore: 90,
        consistencyScore: 80,
        tradeCount: 20,
        tradingDays: 8,
      );

      expect(eligible, 50);
      expect(highDrawdown, lessThan(eligible));
      expect(
        calculateRiskAdjustedLeaderboardScore(
          returnPercentage: 100,
          maxDrawdownPercentage: 0,
          disciplineScore: 100,
          consistencyScore: 100,
          tradeCount: 2,
          tradingDays: 1,
        ),
        0,
      );
    },
  );

  test('leaderboard privacy defaults to private and opted out', () {
    final privacy = LeaderboardPrivacy.fromMap(null);
    expect(privacy.participate, isFalse);
    expect(privacy.hideRealName, isTrue);
    expect(privacy.hideExactBalance, isTrue);
    expect(privacy.hideFromPublic, isTrue);
  });
}
