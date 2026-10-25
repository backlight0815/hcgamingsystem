import 'package:flutter/material.dart';

import '../../core/config/mt5_sync_config.dart';
import '../../core/localization/app_localizations.dart';
import '../../core/network/api_client.dart';
import '../../core/security/access_policy.dart';
import '../../core/storage/local_app_storage.dart';
import '../../models/app_user.dart';
import '../../models/capital_transaction.dart';
import '../../models/trading_data_source.dart';
import '../../models/trading_journal_entry.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/prop_firm_challenge.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_back_button.dart';
import '../../shared/app_feedback.dart';
import '../../shared/formatters.dart';
import '../../shared/metric_tile.dart';
import '../../shared/trading_data_builder.dart';
import '../../shared/trading_data_source_selector.dart';
import '../../services/trading_report_service.dart';
import '../../services/trading_data_source_service.dart';
import '../access/access_required_view.dart';
import 'domain/prop_firm_calculator.dart';

class PropFirmScreen extends StatefulWidget {
  const PropFirmScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.storage,
  });

  final AppUser user;
  final TradingRepository repository;
  final LocalAppStorage storage;

  @override
  State<PropFirmScreen> createState() => _PropFirmScreenState();
}

class _PropFirmScreenState extends State<PropFirmScreen> {
  final _calculator = const PropFirmCalculator();
  String? _selectedChallengeId;

  @override
  Widget build(BuildContext context) {
    if (ClientAccessPolicy.isRestricted(widget.user, AppFeatureArea.propFirm)) {
      return const AccessRequiredView(featureName: 'Prop Firm');
    }
    return Scaffold(
      appBar: AppBar(
        leading: const AppBackButton(),
        title: Text(context.l10n.text('Prop Firm')),
      ),
      body: TradingDataBuilder(
        user: widget.user,
        repository: widget.repository,
        builder: (context, journals, capitals, pairs, mode) {
          if (mode == TradingDataDisplayMode.manualOnly) {
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                TradingDataSourceSelector(
                  user: widget.user,
                  repository: widget.repository,
                  mode: mode,
                ),
                const SizedBox(height: 12),
                _ManualPropFirmSummary(journals: journals, capitals: capitals),
              ],
            );
          }

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: [
              TradingDataSourceSelector(
                user: widget.user,
                repository: widget.repository,
                mode: mode,
              ),
              const SizedBox(height: 12),
              if (!Mt5SyncConfig.propFirmMt5SyncEnabled)
                const _FeatureDisabled()
              else
                _Mt5PropFirmBody(
                  user: widget.user,
                  repository: widget.repository,
                  calculator: _calculator,
                  selectedChallengeId: _selectedChallengeId,
                  onSelectedChallenge: (value) =>
                      setState(() => _selectedChallengeId = value),
                  onOpenChallengeDialog: _openChallengeDialog,
                ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _openChallengeDialog({
    required List<Mt5Account> accounts,
    PropFirmChallenge? challenge,
  }) async {
    final saved = await showDialog<PropFirmChallenge>(
      context: context,
      builder: (_) => _ChallengeDialog(
        userId: widget.user.uid,
        accounts: accounts,
        challenge: challenge,
      ),
    );
    if (saved == null) return;
    try {
      final stored = await widget.repository.savePropFirmChallenge(saved);
      if (!mounted) return;
      setState(() => _selectedChallengeId = stored.id);
      AppFeedback.success(context, 'Prop Firm challenge saved.');
    } on ApiException catch (error) {
      if (mounted) AppFeedback.error(context, error.message);
    } catch (error) {
      if (mounted) AppFeedback.error(context, error.toString());
    }
  }
}

class _ManualPropFirmSummary extends StatelessWidget {
  const _ManualPropFirmSummary({
    required this.journals,
    required this.capitals,
  });

  final List<TradingJournalEntry> journals;
  final List<CapitalTransaction> capitals;

  @override
  Widget build(BuildContext context) {
    final report = TradingReportService().build(
      journals: journals,
      capitals: capitals,
    );
    final summary = report.summary;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text('Manual Data Only'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  context.l10n.text(
                    'Prop firm tracking below uses manual journal and Excel import records only.',
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final count = constraints.maxWidth >= 720 ? 4 : 2;
            return GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              crossAxisCount: count,
              crossAxisSpacing: 10,
              mainAxisSpacing: 10,
              childAspectRatio: count == 4 ? 1.55 : 1.08,
              children: [
                MetricTile(
                  label: context.l10n.text('Net P/L'),
                  value: formatMoney(summary.netProfitLoss),
                  icon: Icons.show_chart,
                ),
                MetricTile(
                  label: context.l10n.text('Daily Loss'),
                  value: formatPercent(summary.maxDailyLossPercent),
                  icon: Icons.warning_amber_outlined,
                  detail: formatMoney(summary.maxDailyLossAmount),
                ),
                MetricTile(
                  label: context.l10n.text('Maximum Loss'),
                  value: formatPercent(summary.maxDrawdownPercent),
                  icon: Icons.waterfall_chart,
                  detail: formatMoney(summary.maxDrawdownAmount),
                ),
                MetricTile(
                  label: context.l10n.text('Trading days'),
                  value: '${summary.tradingDays}',
                  icon: Icons.calendar_month_outlined,
                ),
                MetricTile(
                  label: context.l10n.text('Profit Target'),
                  value: formatPercent(summary.growthPercent),
                  icon: Icons.flag_outlined,
                  detail: context.l10n.text('Growth'),
                ),
                MetricTile(
                  label: context.l10n.text('Profit Factor'),
                  value: summary.profitFactorLabel,
                  icon: Icons.balance_outlined,
                ),
              ],
            );
          },
        ),
        const SizedBox(height: 12),
        Text(
          context.l10n.text(
            'Prop firm rules vary. Please configure the exact rules from your prop firm. These calculations are for tracking and should be verified with your prop firm dashboard.',
          ),
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }
}

class _Mt5PropFirmBody extends StatelessWidget {
  const _Mt5PropFirmBody({
    required this.user,
    required this.repository,
    required this.calculator,
    required this.selectedChallengeId,
    required this.onSelectedChallenge,
    required this.onOpenChallengeDialog,
  });

  final AppUser user;
  final TradingRepository repository;
  final PropFirmCalculator calculator;
  final String? selectedChallengeId;
  final ValueChanged<String?> onSelectedChallenge;
  final Future<void> Function({
    required List<Mt5Account> accounts,
    PropFirmChallenge? challenge,
  })
  onOpenChallengeDialog;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<Mt5Account?>(
      stream: repository.mainMt5AccountFor(user.uid),
      builder: (context, accountSnapshot) {
        if (accountSnapshot.hasError) {
          return _Notice(
            icon: Icons.sync_problem_outlined,
            title: 'Unable to load MT5 accounts.',
            message: accountSnapshot.error.toString(),
          );
        }
        if (!accountSnapshot.hasData &&
            accountSnapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        final mainAccount = accountSnapshot.data;
        final accounts = mainAccount == null
            ? const <Mt5Account>[]
            : <Mt5Account>[mainAccount];
        return StreamBuilder<List<PropFirmChallenge>>(
          stream: repository.propFirmChallengesFor(user.uid),
          builder: (context, challengeSnapshot) {
            if (challengeSnapshot.hasError) {
              return _Notice(
                icon: Icons.error_outline,
                title: 'Unable to load Prop Firm challenges.',
                message: challengeSnapshot.error.toString(),
              );
            }
            if (!challengeSnapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final challenges = mainAccount == null
                ? const <PropFirmChallenge>[]
                : challengeSnapshot.data!
                      .where(
                        (item) => item.mt5AccountKey == mainAccount.accountKey,
                      )
                      .toList(growable: false);
            final selectedId =
                challenges.any((item) => item.id == selectedChallengeId)
                ? selectedChallengeId
                : challenges.isEmpty
                ? null
                : challenges.first.id;
            final selected = selectedId == null
                ? null
                : challenges.firstWhere((item) => item.id == selectedId);
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _HeaderCard(
                  accounts: accounts,
                  challenges: challenges,
                  selectedChallenge: selected,
                  onSelected: onSelectedChallenge,
                  onCreate: accounts.isEmpty
                      ? null
                      : () => onOpenChallengeDialog(accounts: accounts),
                  onEdit: selected == null
                      ? null
                      : () => onOpenChallengeDialog(
                          accounts: accounts,
                          challenge: selected,
                        ),
                ),
                const SizedBox(height: 12),
                if (accounts.isEmpty)
                  const _Notice(
                    icon: Icons.account_balance_outlined,
                    title: 'No main MT5 account selected.',
                    message:
                        'Select a main MT5 synced account before creating a Prop Firm challenge.',
                  )
                else if (selected == null)
                  const _Notice(
                    icon: Icons.account_balance_outlined,
                    title:
                        'The selected main MT5 account is not linked to a Prop Firm account.',
                    message:
                        'Create a Prop Firm challenge for the selected main MT5 account before viewing progress.',
                  )
                else
                  _ChallengeDashboard(
                    userId: user.uid,
                    repository: repository,
                    calculator: calculator,
                    challenge: selected,
                    account: accounts.firstWhere(
                      (item) => item.accountKey == selected.mt5AccountKey,
                      orElse: () => accounts.first,
                    ),
                  ),
              ],
            );
          },
        );
      },
    );
  }
}

class _ChallengeDashboard extends StatelessWidget {
  const _ChallengeDashboard({
    required this.userId,
    required this.repository,
    required this.calculator,
    required this.challenge,
    required this.account,
  });

  final String userId;
  final TradingRepository repository;
  final PropFirmCalculator calculator;
  final PropFirmChallenge challenge;
  final Mt5Account account;

  @override
  Widget build(BuildContext context) {
    return StreamBuilder<Mt5SyncState?>(
      stream: repository.mt5SyncStateForAccount(
        uid: userId,
        accountKey: challenge.mt5AccountKey,
      ),
      builder: (context, syncSnapshot) {
        return StreamBuilder<List<Mt5TradeRecord>>(
          stream: repository.mt5RecordsForAccount(
            uid: userId,
            accountKey: challenge.mt5AccountKey,
            limit: 1000,
          ),
          builder: (context, recordSnapshot) {
            if (recordSnapshot.hasError) {
              return _Notice(
                icon: Icons.error_outline,
                title: 'Unable to load synced trades.',
                message: recordSnapshot.error.toString(),
              );
            }
            if (!recordSnapshot.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final localMetrics = calculator.evaluate(
              challenge: challenge,
              account: account,
              records: recordSnapshot.data!,
            );
            return StreamBuilder<PropFirmMetrics?>(
              stream: repository.propFirmMetricsForChallenge(
                uid: userId,
                challengeId: challenge.id,
              ),
              builder: (context, metricSnapshot) {
                final metrics = metricSnapshot.data ?? localMetrics;
                final syncState = syncSnapshot.data;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _TopStatusCard(challenge: challenge, metrics: metrics),
                    const SizedBox(height: 12),
                    _AccountCard(metrics: metrics),
                    const SizedBox(height: 12),
                    _ProgressCard(metrics: metrics),
                    const SizedBox(height: 12),
                    _LossCard(metrics: metrics),
                    const SizedBox(height: 12),
                    _PhaseCard(metrics: metrics),
                    const SizedBox(height: 12),
                    _SyncCard(account: account, syncState: syncState),
                    const SizedBox(height: 12),
                    Text(
                      context.l10n.text(
                        'Prop firm rules vary. Please configure the exact rules from your prop firm. These calculations are for tracking and should be verified with your prop firm dashboard.',
                      ),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                );
              },
            );
          },
        );
      },
    );
  }
}

class _HeaderCard extends StatelessWidget {
  const _HeaderCard({
    required this.accounts,
    required this.challenges,
    required this.selectedChallenge,
    required this.onSelected,
    required this.onCreate,
    required this.onEdit,
  });

  final List<Mt5Account> accounts;
  final List<PropFirmChallenge> challenges;
  final PropFirmChallenge? selectedChallenge;
  final ValueChanged<String?> onSelected;
  final VoidCallback? onCreate;
  final VoidCallback? onEdit;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(Icons.account_balance_outlined),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    context.l10n.text('2-Step Challenge'),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.text('Create Challenge'),
                  onPressed: onCreate,
                  icon: const Icon(Icons.add),
                ),
                IconButton(
                  tooltip: context.l10n.text('Edit Challenge Rules'),
                  onPressed: onEdit,
                  icon: const Icon(Icons.tune),
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (challenges.isEmpty)
              Text(context.l10n.text('No Prop Firm challenge created.'))
            else
              DropdownButtonFormField<String>(
                initialValue: selectedChallenge?.id,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Prop Firm Challenge'),
                ),
                items: [
                  for (final challenge in challenges)
                    DropdownMenuItem(
                      value: challenge.id,
                      child: Text(challenge.displayName),
                    ),
                ],
                onChanged: onSelected,
              ),
          ],
        ),
      ),
    );
  }
}

class _TopStatusCard extends StatelessWidget {
  const _TopStatusCard({required this.challenge, required this.metrics});

  final PropFirmChallenge challenge;
  final PropFirmMetrics metrics;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: challenge.displayName,
      icon: Icons.flag_outlined,
      trailing: _StatusChip(
        label: _statusLabel(metrics.riskStatus),
        color: _riskColor(metrics.riskStatus),
      ),
      children: [
        _MetricWrap(
          items: [
            ('Account Number', '${challenge.mt5Login}'),
            ('Broker Server', challenge.brokerServer),
            ('Current Phase', _phaseLabel(challenge.currentPhase)),
            ('Status', _statusLabel(metrics.riskStatus)),
          ],
        ),
      ],
    );
  }
}

class _AccountCard extends StatelessWidget {
  const _AccountCard({required this.metrics});

  final PropFirmMetrics metrics;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Account Status',
      icon: Icons.account_balance_wallet_outlined,
      children: [
        _MetricWrap(
          items: [
            ('Starting Balance', formatMoney(metrics.startingBalance)),
            ('Current Balance', formatMoney(metrics.currentBalance)),
            ('Current Equity', formatMoney(metrics.currentEquity)),
            ('Closed P/L', formatMoney(metrics.closedProfitLoss)),
            ('Floating P/L', formatMoney(metrics.floatingProfitLoss)),
            ('Profit %', '${metrics.accountProfitPercent.toStringAsFixed(2)}%'),
          ],
        ),
      ],
    );
  }
}

class _ProgressCard extends StatelessWidget {
  const _ProgressCard({required this.metrics});

  final PropFirmMetrics metrics;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Profit Target',
      icon: Icons.trending_up,
      children: [
        LinearProgressIndicator(
          value: (metrics.profitTargetProgressPercent / 100).clamp(0, 1),
          minHeight: 10,
          borderRadius: BorderRadius.circular(20),
        ),
        const SizedBox(height: 12),
        _MetricWrap(
          items: [
            ('Target amount', formatMoney(metrics.phaseProfitTargetAmount)),
            ('Current profit', formatMoney(metrics.currentProfitAmount)),
            ('Remaining Target', formatMoney(metrics.remainingProfitRequired)),
            (
              'Target reached',
              context.l10n.text(metrics.targetReached ? 'Yes' : 'No'),
            ),
          ],
        ),
      ],
    );
  }
}

class _LossCard extends StatelessWidget {
  const _LossCard({required this.metrics});

  final PropFirmMetrics metrics;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Risk Limits',
      icon: Icons.health_and_safety_outlined,
      children: [
        _MetricWrap(
          items: [
            ('Daily loss limit', formatMoney(metrics.dailyLossLimitAmount)),
            ('Today P/L', formatMoney(metrics.todayTotalProfitLoss)),
            ('Daily loss used', formatMoney(metrics.dailyLossUsedAmount)),
            (
              'Remaining Daily Loss',
              formatMoney(metrics.dailyLossRemainingAmount),
            ),
            ('Maximum Loss', formatMoney(metrics.maximumLossLimitAmount)),
            (
              'Minimum allowed balance/equity',
              formatMoney(metrics.minimumAllowedBalanceEquity),
            ),
            ('Current drawdown', formatMoney(metrics.currentDrawdownAmount)),
            (
              'Remaining Max Loss',
              formatMoney(metrics.maximumLossRemainingAmount),
            ),
          ],
        ),
      ],
    );
  }
}

class _PhaseCard extends StatelessWidget {
  const _PhaseCard({required this.metrics});

  final PropFirmMetrics metrics;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Phase Progress',
      icon: Icons.timeline_outlined,
      children: [
        _MetricWrap(
          items: [
            (
              'Phase 1',
              context.l10n.text(
                metrics.phase1Passed ? 'Passed' : 'In progress',
              ),
            ),
            (
              'Phase 2',
              context.l10n.text(
                metrics.phase2Passed ? 'Passed' : 'In progress',
              ),
            ),
            (
              'Minimum Trading Days',
              '${metrics.tradingDaysCount} / ${metrics.phaseMinimumTradingDaysRequired}',
            ),
            ('Remaining Trading Days', '${metrics.remainingTradingDays}'),
          ],
        ),
      ],
    );
  }
}

class _SyncCard extends StatelessWidget {
  const _SyncCard({required this.account, required this.syncState});

  final Mt5Account account;
  final Mt5SyncState? syncState;

  @override
  Widget build(BuildContext context) {
    final state = syncState;
    final errorMessage = state?.publicErrorMessage.trim() ?? '';
    final isHealthy = state?.isLive == true || state?.labelKey == 'Completed';
    return _Panel(
      title: 'MT5 Sync Status',
      icon: Icons.sync_alt_outlined,
      trailing: _StatusChip(
        label: state == null ? 'Not connected' : state.labelKey,
        color: isHealthy
            ? Colors.green
            : state?.hasActiveAccountMismatch == true ||
                  state?.labelKey == 'Login Failed' ||
                  state?.labelKey == 'Temporary Error' ||
                  state?.labelKey == 'Failed'
            ? Colors.red
            : Colors.orange,
      ),
      children: [
        _MetricWrap(
          items: [
            ('Account Number', '${account.login}'),
            ('Broker Server', account.brokerServer),
            (
              'Realtime Mode',
              context.l10n.text(state?.realtimeMode == true ? 'On' : 'Off'),
            ),
            (
              'Sync Interval',
              state == null ? '-' : '${state.syncIntervalSeconds}s',
            ),
            (
              'Last Sync Time',
              state?.lastSuccessfulSyncAt == null
                  ? '-'
                  : formatDateTime(state!.lastSuccessfulSyncAt!),
            ),
            if (errorMessage.isNotEmpty) ('Sync issue', errorMessage),
            if (state?.lastErrorCode.trim().isNotEmpty == true)
              ('Error code', state!.lastErrorCode.trim()),
          ],
        ),
      ],
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({
    required this.title,
    required this.icon,
    required this.children,
    this.trailing,
  });

  final String title;
  final IconData icon;
  final List<Widget> children;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    context.l10n.text(title),
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                ?trailing,
              ],
            ),
            const SizedBox(height: 12),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _MetricWrap extends StatelessWidget {
  const _MetricWrap({required this.items});

  final List<(String, String)> items;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        for (final item in items)
          SizedBox(
            width: 160,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.l10n.text(item.$1),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  context.l10n.text(item.$2),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Chip(
      label: Text(context.l10n.text(label)),
      avatar: Icon(Icons.circle, size: 12, color: color),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            Icon(icon, size: 36),
            const SizedBox(height: 10),
            Text(
              context.l10n.text(title),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(context.l10n.text(message), textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

class _FeatureDisabled extends StatelessWidget {
  const _FeatureDisabled();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: const [
        _Notice(
          icon: Icons.visibility_off_outlined,
          title: 'Prop Firm MT5 sync is disabled.',
          message: 'Existing app functions continue to work normally.',
        ),
      ],
    );
  }
}

class _ChallengeDialog extends StatefulWidget {
  const _ChallengeDialog({
    required this.userId,
    required this.accounts,
    this.challenge,
  });

  final String userId;
  final List<Mt5Account> accounts;
  final PropFirmChallenge? challenge;

  @override
  State<_ChallengeDialog> createState() => _ChallengeDialogState();
}

class _ChallengeDialogState extends State<_ChallengeDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _firm;
  late final TextEditingController _name;
  late final TextEditingController _balance;
  late final TextEditingController _currency;
  late final TextEditingController _phase1Target;
  late final TextEditingController _phase2Target;
  late final TextEditingController _dailyLoss;
  late final TextEditingController _maxLoss;
  late final TextEditingController _minDays1;
  late final TextEditingController _minDays2;
  late final TextEditingController _maxDays1;
  late final TextEditingController _maxDays2;
  late String _accountKey;
  late String _phase;
  late String _dailyType;
  late String _maxType;
  late String _timezone;
  late DateTime _startDate;
  late bool _allowWeekendHolding;
  late bool _allowNewsTrading;

  @override
  void initState() {
    super.initState();
    final challenge = widget.challenge;
    final account = widget.accounts.first;
    _accountKey = challenge?.mt5AccountKey ?? account.accountKey;
    _phase = challenge?.currentPhase ?? 'phase_1';
    _dailyType = challenge?.dailyLossCalculationType ?? 'balance_based';
    _maxType =
        challenge?.maximumLossCalculationType ?? 'static_initial_balance';
    _timezone = challenge?.timezoneForDailyReset ?? 'Asia/Singapore';
    _startDate = challenge?.challengeStartDate ?? DateTime.now();
    _allowWeekendHolding = challenge?.allowWeekendHolding ?? true;
    _allowNewsTrading = challenge?.allowNewsTrading ?? true;
    _firm = TextEditingController(text: challenge?.propFirmName ?? '');
    _name = TextEditingController(
      text: challenge?.challengeName ?? '2-Step Challenge',
    );
    _balance = TextEditingController(
      text:
          (challenge?.startingBalance ??
                  (mt5AccountCapitalBalance(account) > 0
                      ? mt5AccountCapitalBalance(account)
                      : 10000))
              .toStringAsFixed(2),
    );
    _currency = TextEditingController(
      text: challenge?.accountCurrency ?? account.currency.ifEmpty('USD'),
    );
    _phase1Target = TextEditingController(
      text: (challenge?.phase1ProfitTargetPercent ?? 8).toString(),
    );
    _phase2Target = TextEditingController(
      text: (challenge?.phase2ProfitTargetPercent ?? 5).toString(),
    );
    _dailyLoss = TextEditingController(
      text: (challenge?.dailyLossLimitPercent ?? 5).toString(),
    );
    _maxLoss = TextEditingController(
      text: (challenge?.maximumLossLimitPercent ?? 10).toString(),
    );
    _minDays1 = TextEditingController(
      text: (challenge?.minimumTradingDaysPhase1 ?? 3).toString(),
    );
    _minDays2 = TextEditingController(
      text: (challenge?.minimumTradingDaysPhase2 ?? 3).toString(),
    );
    _maxDays1 = TextEditingController(
      text: challenge?.maxTradingDaysPhase1?.toString() ?? '',
    );
    _maxDays2 = TextEditingController(
      text: challenge?.maxTradingDaysPhase2?.toString() ?? '',
    );
  }

  @override
  void dispose() {
    for (final controller in [
      _firm,
      _name,
      _balance,
      _currency,
      _phase1Target,
      _phase2Target,
      _dailyLoss,
      _maxLoss,
      _minDays1,
      _minDays2,
      _maxDays1,
      _maxDays2,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(
        context.l10n.text(
          widget.challenge == null
              ? 'Create Challenge'
              : 'Edit Challenge Rules',
        ),
      ),
      content: Form(
        key: _formKey,
        child: SizedBox(
          width: 520,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: _accountKey,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Link MT5 Account'),
                  ),
                  items: [
                    for (final account in widget.accounts)
                      DropdownMenuItem(
                        value: account.accountKey,
                        child: Text(account.label),
                      ),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _accountKey = value);
                  },
                ),
                _field(_firm, 'Prop firm name'),
                _field(_name, 'Challenge name'),
                _field(_balance, 'Starting Balance', number: true),
                _field(_currency, 'Currency'),
                _field(_phase1Target, 'Phase 1 target %', number: true),
                _field(_phase2Target, 'Phase 2 target %', number: true),
                _field(_dailyLoss, 'Daily loss limit %', number: true),
                _field(_maxLoss, 'Maximum loss limit %', number: true),
                _field(
                  _minDays1,
                  'Minimum Trading Days Phase 1',
                  integer: true,
                ),
                _field(
                  _minDays2,
                  'Minimum Trading Days Phase 2',
                  integer: true,
                ),
                _field(
                  _maxDays1,
                  'Max Trading Days Phase 1',
                  integer: true,
                  requiredField: false,
                ),
                _field(
                  _maxDays2,
                  'Max Trading Days Phase 2',
                  integer: true,
                  requiredField: false,
                ),
                _dropdown(
                  label: 'Current Phase',
                  value: _phase,
                  items: const [
                    'phase_1',
                    'phase_2',
                    'funded',
                    'passed',
                    'failed',
                  ],
                  onChanged: (value) => setState(() => _phase = value),
                ),
                _dropdown(
                  label: 'Daily loss calculation type',
                  value: _dailyType,
                  items: const [
                    'balance_based',
                    'equity_based',
                    'balance_or_equity',
                  ],
                  onChanged: (value) => setState(() => _dailyType = value),
                ),
                _dropdown(
                  label: 'Maximum loss calculation type',
                  value: _maxType,
                  items: const [
                    'static_initial_balance',
                    'trailing_equity',
                    'trailing_balance',
                  ],
                  onChanged: (value) => setState(() => _maxType = value),
                ),
                _dropdown(
                  label: 'Timezone for daily reset',
                  value: _timezone,
                  items: const [
                    'Asia/Singapore',
                    'Asia/Kuala_Lumpur',
                    'UTC',
                    'America/New_York',
                    'Europe/London',
                  ],
                  onChanged: (value) => setState(() => _timezone = value),
                ),
                const SizedBox(height: 12),
                InputDecorator(
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Challenge start date'),
                  ),
                  child: Row(
                    children: [
                      Expanded(child: Text(formatDate(_startDate))),
                      IconButton(
                        tooltip: context.l10n.text('Select date'),
                        onPressed: _pickStartDate,
                        icon: const Icon(Icons.calendar_month_outlined),
                      ),
                    ],
                  ),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text('Allow weekend holding')),
                  value: _allowWeekendHolding,
                  onChanged: (value) =>
                      setState(() => _allowWeekendHolding = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(context.l10n.text('Allow news trading')),
                  value: _allowNewsTrading,
                  onChanged: (value) =>
                      setState(() => _allowNewsTrading = value),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: _submit,
          child: Text(context.l10n.text('Save')),
        ),
      ],
    );
  }

  Widget _field(
    TextEditingController controller,
    String label, {
    bool number = false,
    bool integer = false,
    bool requiredField = true,
  }) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: TextFormField(
        controller: controller,
        keyboardType: integer
            ? TextInputType.number
            : number
            ? const TextInputType.numberWithOptions(decimal: true)
            : TextInputType.text,
        decoration: InputDecoration(labelText: context.l10n.text(label)),
        validator: (value) {
          final text = (value ?? '').trim();
          if (text.isEmpty && requiredField) {
            return context.l10n.text('Required');
          }
          if (text.isEmpty) return null;
          if (integer && int.tryParse(text) == null) {
            return context.l10n.text('Enter a valid number.');
          }
          if (number && double.tryParse(text) == null) {
            return context.l10n.text('Enter a valid number.');
          }
          return null;
        },
      ),
    );
  }

  Widget _dropdown({
    required String label,
    required String value,
    required List<String> items,
    required ValueChanged<String> onChanged,
  }) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: DropdownButtonFormField<String>(
        initialValue: value,
        decoration: InputDecoration(labelText: context.l10n.text(label)),
        items: [
          for (final item in items)
            DropdownMenuItem(
              value: item,
              child: Text(context.l10n.text(_optionLabel(item))),
            ),
        ],
        onChanged: (value) {
          if (value != null) onChanged(value);
        },
      ),
    );
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    final account = widget.accounts.firstWhere(
      (item) => item.accountKey == _accountKey,
      orElse: () => widget.accounts.first,
    );
    final existing = widget.challenge;
    Navigator.of(context).pop(
      PropFirmChallenge(
        id: existing?.id ?? '',
        userId: widget.userId,
        mt5AccountKey: account.accountKey,
        mt5Login: account.login,
        brokerServer: account.brokerServer,
        propFirmName: _firm.text.trim(),
        challengeName: _name.text.trim(),
        currentPhase: _phase,
        startingBalance: double.parse(_balance.text.trim()),
        accountCurrency: _currency.text.trim().ifEmpty(
          account.currency.ifEmpty('USD'),
        ),
        phase1ProfitTargetPercent: double.parse(_phase1Target.text.trim()),
        phase2ProfitTargetPercent: double.parse(_phase2Target.text.trim()),
        dailyLossLimitPercent: double.parse(_dailyLoss.text.trim()),
        maximumLossLimitPercent: double.parse(_maxLoss.text.trim()),
        dailyLossCalculationType: _dailyType,
        maximumLossCalculationType: _maxType,
        minimumTradingDaysPhase1: int.parse(_minDays1.text.trim()),
        minimumTradingDaysPhase2: int.parse(_minDays2.text.trim()),
        maxTradingDaysPhase1: _optionalInt(_maxDays1.text),
        maxTradingDaysPhase2: _optionalInt(_maxDays2.text),
        allowWeekendHolding: _allowWeekendHolding,
        allowNewsTrading: _allowNewsTrading,
        timezoneForDailyReset: _timezone,
        challengeStartDate: _startDate,
        phase1StartedAt: existing?.phase1StartedAt ?? DateTime.now(),
        status: _phase == 'failed'
            ? 'failed'
            : _phase == 'passed'
            ? 'passed'
            : 'active',
        createdAt: existing?.createdAt,
      ),
    );
  }

  Future<void> _pickStartDate() async {
    final value = await showDatePicker(
      context: context,
      initialDate: _startDate,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (value != null) {
      setState(() => _startDate = value);
    }
  }
}

int? _optionalInt(String value) {
  final text = value.trim();
  return text.isEmpty ? null : int.tryParse(text);
}

String _statusLabel(String value) {
  return switch (value) {
    'healthy' => 'Healthy',
    'warning' => 'Warning',
    'near_breach' => 'Near Breach',
    'breached' => 'Breached',
    'passed' => 'Passed',
    _ => value,
  };
}

String _phaseLabel(String value) {
  return switch (value) {
    'phase_1' => 'Phase 1',
    'phase_2' => 'Phase 2',
    'funded' => 'Funded',
    'passed' => 'Passed',
    'failed' => 'Failed',
    _ => value,
  };
}

String _optionLabel(String value) {
  return switch (value) {
    'phase_1' => 'Phase 1',
    'phase_2' => 'Phase 2',
    'funded' => 'Funded',
    'passed' => 'Passed',
    'failed' => 'Failed',
    'balance_based' => 'Balance based',
    'equity_based' => 'Equity based',
    'balance_or_equity' => 'Balance or equity',
    'static_initial_balance' => 'Static initial balance',
    'trailing_equity' => 'Trailing equity',
    'trailing_balance' => 'Trailing balance',
    _ => value,
  };
}

Color _riskColor(String value) {
  return switch (value) {
    'healthy' => Colors.green,
    'warning' => Colors.orange,
    'near_breach' => Colors.deepOrange,
    'breached' => Colors.red,
    'passed' => Colors.blue,
    _ => Colors.grey,
  };
}

extension _TextFallback on String {
  String ifEmpty(String fallback) => trim().isEmpty ? fallback : this;
}
