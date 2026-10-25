import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/app_user.dart';
import '../../models/prop_firm_challenge.dart';
import '../../models/trade_call_statistics.dart';
import '../../repositories/trading_repository.dart';
import '../../services/trade_call_statistics_service.dart';
import '../../shared/analytics/analytics_widgets.dart';
import '../../shared/formatters.dart';

class PerformancePropFirmSection extends StatefulWidget {
  const PerformancePropFirmSection({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  State<PerformancePropFirmSection> createState() =>
      _PerformancePropFirmSectionState();
}

class _PerformancePropFirmSectionState
    extends State<PerformancePropFirmSection> {
  String _selectedId = '';

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<List<PropFirmChallenge>>(
      stream: widget.repository.propFirmChallengesFor(widget.user.uid),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ErrorSurface(
            title: 'Unable to load Prop Firm progress',
            message: 'Check your connection and permission, then try again.',
          );
        }
        if (!snapshot.hasData) return const AnalyticsSkeleton();
        final challenges = snapshot.data!;
        if (challenges.isEmpty) {
          return AnalyticsSurface(
            child: AnalyticsEmptyState(
              icon: Icons.flag_outlined,
              title: context.l10n.text('No Prop Firm account'),
              message: context.l10n.text(
                'Create a Prop Firm challenge to monitor real targets and loss limits.',
              ),
            ),
          );
        }
        final selected = challenges.any((item) => item.id == _selectedId)
            ? challenges.firstWhere((item) => item.id == _selectedId)
            : challenges.first;
        return Column(
          children: [
            AnalyticsSurface(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: selected.id,
                  isExpanded: true,
                  items: [
                    for (final challenge in challenges)
                      DropdownMenuItem(
                        value: challenge.id,
                        child: Text(challenge.displayName),
                      ),
                  ],
                  onChanged: (value) => setState(() {
                    _selectedId = value ?? selected.id;
                  }),
                ),
              ),
            ),
            const SizedBox(height: 14),
            StreamBuilder<PropFirmMetrics?>(
              stream: widget.repository.propFirmMetricsForChallenge(
                uid: widget.user.uid,
                challengeId: selected.id,
              ),
              builder: (context, metricSnapshot) {
                if (metricSnapshot.hasError) {
                  return _ErrorSurface(
                    title: 'Unable to load challenge metrics',
                    message:
                        'The saved challenge is available, but its metrics could not be loaded.',
                  );
                }
                if (!metricSnapshot.hasData) return const AnalyticsSkeleton();
                final metrics = metricSnapshot.data;
                if (metrics == null) {
                  return AnalyticsSurface(
                    child: AnalyticsEmptyState(
                      icon: Icons.sync_problem_outlined,
                      title: context.l10n.text('Challenge data unavailable'),
                      message: context.l10n.text(
                        'Synchronize the linked MT5 account to calculate challenge progress.',
                      ),
                    ),
                  );
                }
                return _PropFirmProgress(challenge: selected, metrics: metrics);
              },
            ),
          ],
        );
      },
    );
  }
}

class _PropFirmProgress extends StatelessWidget {
  const _PropFirmProgress({required this.challenge, required this.metrics});

  final PropFirmChallenge challenge;
  final PropFirmMetrics metrics;

  @override
  Widget build(BuildContext context) {
    final currency = challenge.accountCurrency;
    final warning =
        metrics.dailyLossUsedPercent >= 80 && !metrics.dailyLossBreach;
    return Column(
      children: [
        PerformanceHero(
          label: challenge.displayName,
          value: context.l10n.text(_phaseLabel(challenge.currentPhase)),
          caption: context.l10n.text(
            'Limits are calculated from the saved challenge rules and synchronized account state.',
          ),
          statusLabel: context.l10n.text(_riskLabel(metrics.riskStatus)),
          statusIcon: Icons.flag_outlined,
          tone: _riskColor(metrics.riskStatus),
          metrics: [
            AnalyticsHeroMetric(
              label: context.l10n.text('Account size'),
              value: formatCurrencyMoney(challenge.startingBalance, currency),
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Current balance'),
              value: formatCurrencyMoney(metrics.currentBalance, currency),
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Current equity'),
              value: formatCurrencyMoney(metrics.currentEquity, currency),
            ),
            AnalyticsHeroMetric(
              label: context.l10n.text('Trading days'),
              value:
                  '${metrics.tradingDaysCount} / ${metrics.phaseMinimumTradingDaysRequired}',
            ),
          ],
        ),
        if (warning) ...[
          const SizedBox(height: 14),
          InsightBanner(
            title: context.l10n.text('Daily loss warning'),
            message: context.l10n.text(
              'Daily loss usage has reached {percent}. Consider stopping for the day.',
              {
                'percent':
                    '${metrics.dailyLossUsedPercent.toStringAsFixed(0)}%',
              },
            ),
            tone: analyticsWarning,
            icon: Icons.warning_amber_outlined,
          ),
        ],
        const SizedBox(height: 14),
        LayoutBuilder(
          builder: (context, constraints) {
            final width = constraints.maxWidth >= 850
                ? (constraints.maxWidth - 12) / 2
                : constraints.maxWidth;
            return Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                SizedBox(
                  width: width,
                  child: _ProgressSurface(
                    title: 'Profit target',
                    value:
                        '${metrics.profitTargetProgressPercent.toStringAsFixed(1)}%',
                    progress: metrics.profitTargetProgressPercent / 100,
                    tone: analyticsPositive,
                    details: [
                      (
                        'Target amount',
                        formatCurrencyMoney(
                          metrics.phaseProfitTargetAmount,
                          currency,
                        ),
                      ),
                      (
                        'Current profit',
                        formatSignedCurrencyMoney(
                          metrics.currentProfitAmount,
                          currency,
                        ),
                      ),
                      (
                        'Remaining target',
                        formatCurrencyMoney(
                          metrics.remainingProfitRequired,
                          currency,
                        ),
                      ),
                    ],
                  ),
                ),
                SizedBox(
                  width: width,
                  child: _ProgressSurface(
                    title: 'Daily loss limit',
                    value:
                        '${metrics.dailyLossUsedPercent.toStringAsFixed(1)}%',
                    progress: metrics.dailyLossUsedPercent / 100,
                    tone: _usageColor(metrics.dailyLossUsedPercent),
                    details: [
                      (
                        'Limit',
                        formatCurrencyMoney(
                          metrics.dailyLossLimitAmount,
                          currency,
                        ),
                      ),
                      (
                        'Used',
                        formatCurrencyMoney(
                          metrics.dailyLossUsedAmount,
                          currency,
                        ),
                      ),
                      (
                        'Remaining',
                        formatCurrencyMoney(
                          metrics.dailyLossRemainingAmount,
                          currency,
                        ),
                      ),
                    ],
                  ),
                ),
                SizedBox(
                  width: width,
                  child: _ProgressSurface(
                    title: 'Maximum loss limit',
                    value:
                        '${metrics.maximumLossUsedPercent.toStringAsFixed(1)}%',
                    progress: metrics.maximumLossUsedPercent / 100,
                    tone: _usageColor(metrics.maximumLossUsedPercent),
                    details: [
                      (
                        'Maximum loss',
                        formatCurrencyMoney(
                          metrics.maximumLossLimitAmount,
                          currency,
                        ),
                      ),
                      (
                        'Current drawdown',
                        formatCurrencyMoney(
                          metrics.currentDrawdownAmount,
                          currency,
                        ),
                      ),
                      (
                        'Remaining',
                        formatCurrencyMoney(
                          metrics.maximumLossRemainingAmount,
                          currency,
                        ),
                      ),
                    ],
                  ),
                ),
                SizedBox(
                  width: width,
                  child: _ProgressSurface(
                    title: 'Trading days',
                    value:
                        '${metrics.tradingDaysCount} / ${metrics.phaseMinimumTradingDaysRequired}',
                    progress: metrics.phaseMinimumTradingDaysRequired == 0
                        ? 0
                        : metrics.tradingDaysCount /
                              metrics.phaseMinimumTradingDaysRequired,
                    tone: analyticsInformation,
                    details: [
                      (
                        'Phase',
                        context.l10n.text(_phaseLabel(challenge.currentPhase)),
                      ),
                      (
                        'Status',
                        context.l10n.text(_riskLabel(metrics.riskStatus)),
                      ),
                      ('Next action', context.l10n.text(_nextAction(metrics))),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ],
    );
  }
}

class PerformanceSignalsSection extends StatelessWidget {
  const PerformanceSignalsSection({
    super.key,
    required this.user,
    required this.repository,
  });

  final AppUser user;
  final TradingRepository repository;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder(
      stream: repository.tradingSignalsStream(),
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return _ErrorSurface(
            title: 'Signal performance unavailable',
            message: 'Signal statistics could not be loaded for this role.',
          );
        }
        if (!snapshot.hasData) return const AnalyticsSkeleton();
        try {
          final statistics = const TradeCallStatisticsService().calculate(
            calls: snapshot.data!,
            viewer: user,
          );
          return _SignalStatistics(statistics: statistics);
        } catch (_) {
          return _ErrorSurface(
            title: 'Signal performance restricted',
            message:
                'Only authorized administrators and signal providers can view these statistics.',
          );
        }
      },
    );
  }
}

class _SignalStatistics extends StatelessWidget {
  const _SignalStatistics({required this.statistics});

  final TradeCallStatistics statistics;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _MetricWrap(
          values: [
            ('Total signals', '${statistics.totalCalls}'),
            ('Active signals', '${statistics.activeCalls}'),
            ('Closed signals', '${statistics.closedCalls}'),
            ('Winning signals', '${statistics.winningCalls}'),
            ('Losing signals', '${statistics.losingCalls}'),
            ('Break-even signals', '${statistics.breakEvenCalls}'),
            ('Cancelled signals', '${statistics.cancelledCalls}'),
            ('Signal win rate', '${statistics.winRate.toStringAsFixed(1)}%'),
            ('Total pips', statistics.totalPips.toStringAsFixed(1)),
            (
              'Average risk-to-reward',
              statistics.averagePlannedRiskReward <= 0
                  ? context.l10n.text('N/A')
                  : '1:${statistics.averagePlannedRiskReward.toStringAsFixed(1)}',
            ),
          ],
        ),
        const SizedBox(height: 14),
        AnalyticsSurface(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AnalyticsSectionHeader(
                title: context.l10n.text('Performance by symbol'),
                subtitle: context.l10n.text(
                  'Cancelled, test, deleted, and never-activated expired signals are excluded from outcome rates.',
                ),
              ),
              const SizedBox(height: 8),
              for (final row in statistics.byInstrument.take(10))
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(row.label),
                  subtitle: Text(
                    context.l10n.text('{count} evaluated signal(s)', {
                      'count': row.evaluated,
                    }),
                  ),
                  trailing: Text(
                    '${row.winRate.toStringAsFixed(1)}%',
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class PerformanceRankingsSection extends StatefulWidget {
  const PerformanceRankingsSection({super.key, required this.repository});

  final TradingRepository repository;

  @override
  State<PerformanceRankingsSection> createState() =>
      _PerformanceRankingsSectionState();
}

class _PerformanceRankingsSectionState
    extends State<PerformanceRankingsSection> {
  late Future<List<Map<String, dynamic>>> _future = widget.repository
      .adminTraderPerformance();
  var _query = '';
  var _sort = _RankingSort.profit;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        AnalyticsSurface(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  decoration: InputDecoration(
                    hintText: context.l10n.text('Search trader'),
                    prefixIcon: const Icon(Icons.search),
                  ),
                  onChanged: (value) => setState(() => _query = value.trim()),
                ),
              ),
              const SizedBox(width: 10),
              PopupMenuButton<_RankingSort>(
                initialValue: _sort,
                onSelected: (value) => setState(() => _sort = value),
                itemBuilder: (context) => [
                  for (final value in _RankingSort.values)
                    PopupMenuItem(
                      value: value,
                      child: Text(context.l10n.text(value.label)),
                    ),
                ],
                icon: const Icon(Icons.sort),
              ),
              IconButton(
                tooltip: context.l10n.text('Refresh'),
                onPressed: () => setState(() {
                  _future = widget.repository.adminTraderPerformance();
                }),
                icon: const Icon(Icons.refresh),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        FutureBuilder<List<Map<String, dynamic>>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return _ErrorSurface(
                title: 'Trader ranking unavailable',
                message:
                    'Your role may not have access to multi-trader financial performance.',
              );
            }
            if (!snapshot.hasData) return const AnalyticsSkeleton();
            final rows = snapshot.data!.where((row) {
              final user = _map(row['user']);
              final label = '${user['name'] ?? user['display_name'] ?? ''}'
                  .toLowerCase();
              return _query.isEmpty || label.contains(_query.toLowerCase());
            }).toList()..sort(_sort.compare);
            if (rows.isEmpty) {
              return AnalyticsSurface(
                child: AnalyticsEmptyState(
                  title: context.l10n.text('No trader performance data'),
                  message: context.l10n.text(
                    'No permitted trader matches the current search.',
                  ),
                ),
              );
            }
            return AnalyticsSurface(
              child: Column(
                children: [
                  for (final indexed in rows.indexed)
                    _RankingRow(rank: indexed.$1 + 1, row: indexed.$2),
                ],
              ),
            );
          },
        ),
      ],
    );
  }
}

class _RankingRow extends StatelessWidget {
  const _RankingRow({required this.rank, required this.row});

  final int rank;
  final Map<String, dynamic> row;

  @override
  Widget build(BuildContext context) {
    final user = _map(row['user']);
    final name =
        '${user['name'] ?? user['display_name'] ?? context.l10n.text('Trader')}';
    final initial = name.trim().isEmpty
        ? 'T'
        : name.characters.first.toUpperCase();
    final profit = _number(row['net_profit_loss']);
    final winRate = _number(row['win_rate']);
    final drawdown = _number(row['max_drawdown_percent']);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: CircleAvatar(child: Text(rank <= 3 ? '#$rank' : initial)),
      title: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        context.l10n.text('{rate} win rate · {drawdown} drawdown', {
          'rate': '${winRate.toStringAsFixed(1)}%',
          'drawdown': '${drawdown.toStringAsFixed(1)}%',
        }),
      ),
      trailing: Text(
        formatSignedMoney(profit),
        style: TextStyle(
          color: profit >= 0 ? analyticsPositive : analyticsNegative,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _ProgressSurface extends StatelessWidget {
  const _ProgressSurface({
    required this.title,
    required this.value,
    required this.progress,
    required this.tone,
    required this.details,
  });

  final String title;
  final String value;
  final double progress;
  final Color tone;
  final List<(String, String)> details;

  @override
  Widget build(BuildContext context) {
    return AnalyticsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AnalyticsProgressRow(
            label: context.l10n.text(title),
            valueLabel: value,
            progress: progress.clamp(0, 1),
            tone: tone,
          ),
          const SizedBox(height: 14),
          for (final detail in details)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Row(
                children: [
                  Expanded(child: Text(context.l10n.text(detail.$1))),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Text(
                      detail.$2,
                      textAlign: TextAlign.end,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _MetricWrap extends StatelessWidget {
  const _MetricWrap({required this.values});

  final List<(String, String)> values;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 900
            ? 4
            : constraints.maxWidth >= 560
            ? 3
            : 2;
        final width = (constraints.maxWidth - (columns - 1) * 10) / columns;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final value in values)
              SizedBox(
                width: width,
                child: AnalyticsSurface(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.text(value.$1),
                        style: Theme.of(context).textTheme.labelMedium,
                      ),
                      const SizedBox(height: 5),
                      FittedBox(
                        child: Text(
                          value.$2,
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _ErrorSurface extends StatelessWidget {
  const _ErrorSurface({required this.title, required this.message});

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => AnalyticsSurface(
    child: AnalyticsEmptyState(
      icon: Icons.cloud_off_outlined,
      title: context.l10n.text(title),
      message: context.l10n.text(message),
    ),
  );
}

enum _RankingSort { profit, winRate, drawdown }

extension on _RankingSort {
  String get label => switch (this) {
    _RankingSort.profit => 'Highest profit',
    _RankingSort.winRate => 'Highest win rate',
    _RankingSort.drawdown => 'Lowest drawdown',
  };

  int compare(Map<String, dynamic> left, Map<String, dynamic> right) =>
      switch (this) {
        _RankingSort.profit => _number(
          right['net_profit_loss'],
        ).compareTo(_number(left['net_profit_loss'])),
        _RankingSort.winRate => _number(
          right['win_rate'],
        ).compareTo(_number(left['win_rate'])),
        _RankingSort.drawdown => _number(
          left['max_drawdown_percent'],
        ).compareTo(_number(right['max_drawdown_percent'])),
      };
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

double _number(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

String _phaseLabel(String value) => switch (value.toLowerCase()) {
  'phase_1' => 'Phase 1',
  'phase_2' => 'Phase 2',
  'verification' => 'Verification',
  'funded' => 'Funded',
  _ => value,
};

String _riskLabel(String value) => switch (value.toLowerCase()) {
  'healthy' || 'safe' => 'Safe',
  'warning' || 'caution' => 'Caution',
  'danger' || 'high_risk' => 'High Risk',
  'breached' || 'critical' => 'Critical',
  _ => value,
};

String _nextAction(PropFirmMetrics metrics) {
  if (metrics.failed || metrics.dailyLossBreach || metrics.maximumLossBreach) {
    return 'Review rule breach';
  }
  if (metrics.targetReached && metrics.minimumDaysSatisfied) {
    return 'Await phase review';
  }
  if (!metrics.minimumDaysSatisfied) return 'Complete minimum trading days';
  return 'Protect risk limits';
}

Color _riskColor(String value) => switch (_riskLabel(value)) {
  'Safe' => analyticsPositive,
  'Caution' => analyticsWarning,
  'High Risk' || 'Critical' => analyticsNegative,
  _ => analyticsInformation,
};

Color _usageColor(double value) {
  if (value >= 100) return analyticsNegative;
  if (value >= 80) return analyticsWarning;
  return analyticsPositive;
}
