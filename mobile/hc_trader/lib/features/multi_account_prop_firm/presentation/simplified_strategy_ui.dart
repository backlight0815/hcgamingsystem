import 'dart:async';
import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../../../core/localization/app_locale_controller.dart';
import '../../../core/localization/app_localizations.dart';
import '../data/portfolio_repository_snapshot.dart';
import '../domain/account_daily_trade_limit_service.dart';
import '../domain/account_health_score_service.dart';
import '../domain/account_sequence_models.dart';
import '../domain/account_validation_service.dart';
import '../domain/prop_firm_models.dart';
import '../domain/portfolio_dashboard_service.dart';
import '../domain/session_stop_condition_service.dart';
import '../domain/trading_day_service.dart';
import 'prop_firm_planner_controller.dart';

class SimplifiedStrategyOnboarding extends StatefulWidget {
  const SimplifiedStrategyOnboarding({
    super.key,
    required this.controller,
    required this.showBackButton,
  });

  final PropFirmPlannerController controller;
  final bool showBackButton;

  @override
  State<SimplifiedStrategyOnboarding> createState() =>
      _SimplifiedStrategyOnboardingState();
}

class _SimplifiedStrategyOnboardingState
    extends State<SimplifiedStrategyOnboarding> {
  var _acknowledged = false;
  var _saving = false;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return Scaffold(
      appBar: AppBar(
        leading: widget.showBackButton ? const BackButton() : null,
        title: Text(l10n.text('Multiple Account Strategy')),
        actions: [_languageMenu(context)],
      ),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 760),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(28),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Icon(
                      Icons.account_tree_outlined,
                      size: 54,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(height: 18),
                    Text(
                      l10n.text('Configure once. Trade one account at a time.'),
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      l10n.text(
                        'Add funded or evaluation accounts, start with one button, record TP, SL, or BE, and receive the next account recommendation automatically.',
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 22),
                    const _SimpleStep(
                      number: 1,
                      icon: Icons.add_card_outlined,
                      title: 'Add your accounts',
                    ),
                    const _SimpleStep(
                      number: 2,
                      icon: Icons.play_circle_outline,
                      title: 'Start the recommended account',
                    ),
                    const _SimpleStep(
                      number: 3,
                      icon: Icons.fact_check_outlined,
                      title: 'Record the result and rotate safely',
                    ),
                    const SizedBox(height: 14),
                    CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _acknowledged,
                      onChanged: (value) =>
                          setState(() => _acknowledged = value ?? false),
                      controlAffinity: ListTileControlAffinity.leading,
                      title: Text(
                        l10n.text(
                          'I understand this is a decision-support tool, not a broker connection or profit guarantee.',
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    FilledButton.icon(
                      key: const Key('create-simplified-strategy'),
                      onPressed: !_acknowledged || _saving ? null : _create,
                      icon: _saving
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.arrow_forward),
                      label: Text(l10n.text('Create strategy')),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _create() async {
    setState(() => _saving = true);
    try {
      await widget.controller.createSimplifiedStrategy(
        name: context.l10n.text('Multiple Account Strategy'),
      );
    } on Object catch (error) {
      if (mounted) _showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _SimpleStep extends StatelessWidget {
  const _SimpleStep({
    required this.number,
    required this.icon,
    required this.title,
  });

  final int number;
  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: EdgeInsets.zero,
    leading: CircleAvatar(child: Icon(icon, size: 20)),
    title: Text('${context.l10n.text('Step')} $number'),
    subtitle: Text(context.l10n.text(title)),
  );
}

class SimplifiedStrategyWorkspace extends StatefulWidget {
  const SimplifiedStrategyWorkspace({
    super.key,
    required this.controller,
    required this.portfolio,
    required this.showBackButton,
    required this.cloudEnabled,
  });

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;
  final bool showBackButton;
  final bool cloudEnabled;

  @override
  State<SimplifiedStrategyWorkspace> createState() =>
      _SimplifiedStrategyWorkspaceState();
}

class _SimplifiedStrategyWorkspaceState
    extends State<SimplifiedStrategyWorkspace> {
  var _destination = 0;
  String? _selectedAccountId;

  @override
  void didUpdateWidget(covariant SimplifiedStrategyWorkspace oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_selectedAccountId != null &&
        !widget.portfolio.accounts.any(
          (account) => account.id == _selectedAccountId,
        )) {
      _selectedAccountId = null;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final destinations = [
      (Icons.dashboard_outlined, 'Overview'),
      (Icons.account_balance_wallet_outlined, 'Account details'),
      (Icons.menu_book_outlined, 'Tutorial & FAQ'),
    ];
    final pages = [
      SimplifiedOverviewPage(
        controller: widget.controller,
        portfolio: widget.portfolio,
        onOpenAccount: (accountId) => setState(() {
          _selectedAccountId = accountId;
          _destination = 1;
        }),
      ),
      SimplifiedAccountDetailsPage(
        controller: widget.controller,
        portfolio: widget.portfolio,
        selectedAccountId: _selectedAccountId,
        onSelected: (value) => setState(() => _selectedAccountId = value),
      ),
      const SimplifiedTutorialPage(),
    ];

    return Scaffold(
      appBar: AppBar(
        leading: widget.showBackButton ? const BackButton() : null,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.text('Multiple Account Strategy'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            Text(
              l10n.text(
                widget.cloudEnabled
                    ? 'Cloud synchronized'
                    : 'Local planning workspace',
              ),
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: l10n.text('Daily reset settings'),
            onPressed: () => showDailyResetSettings(
              context,
              controller: widget.controller,
              policy: widget.portfolio.riskPolicy,
            ),
            icon: const Icon(Icons.schedule_outlined),
          ),
          _languageMenu(context),
        ],
      ),
      body: LayoutBuilder(
        builder: (context, constraints) {
          final desktop = constraints.maxWidth >= 760;
          final page = pages[_destination];
          return Column(
            children: [
              SimplifiedSyncStatusBar(controller: widget.controller),
              Expanded(
                child: desktop
                    ? Row(
                        children: [
                          NavigationRail(
                            selectedIndex: _destination,
                            extended: constraints.maxWidth >= 1120,
                            labelType: constraints.maxWidth >= 1120
                                ? NavigationRailLabelType.none
                                : NavigationRailLabelType.all,
                            onDestinationSelected: (value) =>
                                setState(() => _destination = value),
                            destinations: [
                              for (final destination in destinations)
                                NavigationRailDestination(
                                  icon: Icon(destination.$1),
                                  selectedIcon: Icon(destination.$1, fill: 1),
                                  label: Text(l10n.text(destination.$2)),
                                ),
                            ],
                          ),
                          const VerticalDivider(width: 1),
                          Expanded(child: page),
                        ],
                      )
                    : page,
              ),
            ],
          );
        },
      ),
      bottomNavigationBar: MediaQuery.sizeOf(context).width < 760
          ? NavigationBar(
              selectedIndex: _destination,
              onDestinationSelected: (value) =>
                  setState(() => _destination = value),
              destinations: [
                for (final destination in destinations)
                  NavigationDestination(
                    icon: Icon(destination.$1),
                    label: l10n.text(destination.$2),
                  ),
              ],
            )
          : null,
    );
  }
}

class SimplifiedSyncStatusBar extends StatelessWidget {
  const SimplifiedSyncStatusBar({super.key, required this.controller});

  final PropFirmPlannerController controller;

  @override
  Widget build(BuildContext context) {
    final rawState = controller.syncState;
    final state = simplifiedVisibleSyncState(
      rawState,
      hasUnsynchronizedChanges: controller.hasUnsynchronizedChanges,
    );
    final reconnecting =
        rawState == PortfolioSyncState.failed &&
        state == PortfolioSyncState.pending;
    final error = state == PortfolioSyncState.failed;
    final label = switch (state) {
      PortfolioSyncState.synchronized => 'Saved and synchronized',
      PortfolioSyncState.saving => 'Saving',
      PortfolioSyncState.pending =>
        reconnecting
            ? 'Reconnecting to Firestore'
            : 'Waiting for Firestore; not yet saved',
      PortfolioSyncState.offline => 'Offline',
      PortfolioSyncState.failed => 'Failed to save',
    };
    return Material(
      color: error
          ? Theme.of(context).colorScheme.errorContainer
          : Theme.of(context).colorScheme.surfaceContainerLow,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 7),
        child: Row(
          children: [
            Icon(
              error
                  ? Icons.error_outline
                  : reconnecting
                  ? Icons.sync
                  : Icons.cloud_done_outlined,
              size: 18,
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    controller.lastSynchronizedAt == null
                        ? context.l10n.text(label)
                        : '${context.l10n.text(label)} · '
                              '${DateFormat.yMd().add_Hm().format(controller.lastSynchronizedAt!.toLocal())}',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (error && controller.error != null)
                    Text(
                      context.l10n.text(controller.error!),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                ],
              ),
            ),
            if (controller.canRetry && !reconnecting)
              TextButton(
                onPressed: () => _run(context, controller.retryLastSave),
                child: Text(context.l10n.text('Retry')),
              ),
          ],
        ),
      ),
    );
  }
}

@visibleForTesting
PortfolioSyncState simplifiedVisibleSyncState(
  PortfolioSyncState rawState, {
  required bool hasUnsynchronizedChanges,
}) {
  if (rawState == PortfolioSyncState.failed && !hasUnsynchronizedChanges) {
    return PortfolioSyncState.pending;
  }
  return rawState;
}

class SimplifiedOverviewPage extends StatelessWidget {
  const SimplifiedOverviewPage({
    super.key,
    required this.controller,
    required this.portfolio,
    required this.onOpenAccount,
  });

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;
  final ValueChanged<String> onOpenAccount;

  @override
  Widget build(BuildContext context) {
    final compact = MediaQuery.sizeOf(context).width < 600;
    final scroll = _content(context, compact: compact);
    if (!compact) return scroll;
    return Column(
      children: [
        Expanded(child: scroll),
        _PrimaryTradingAction(
          controller: controller,
          portfolio: portfolio,
          compact: true,
        ),
      ],
    );
  }

  Widget _content(BuildContext context, {required bool compact}) {
    final l10n = context.l10n;
    final scores = controller.simplifiedHealthScores;
    final dailyLimits = controller.accountDailyTradeLimits;
    final dashboard = controller.dashboardSnapshot;
    final active = portfolio.accounts
        .where((account) => account.id == portfolio.activeAccountId)
        .firstOrNull;
    final recommendedId = controller.simplifiedRecommendedAccountId;
    final recommended = portfolio.accounts
        .where((account) => account.id == recommendedId)
        .firstOrNull;
    final nonArchived = portfolio.accounts
        .where((account) => !account.isArchived)
        .toList(growable: false);
    final combinedScore = nonArchived.isEmpty
        ? 0.0
        : nonArchived.fold<double>(
                0,
                (sum, account) => sum + (scores[account.id]?.score ?? 0),
              ) /
              nonArchived.length;
    final currencyTotals = _currencyTotals(nonArchived, scores);
    final status = _strategyStatus(controller, portfolio, scores);

    return ListView(
      key: const Key('simplified-overview'),
      padding: const EdgeInsets.fromLTRB(18, 18, 18, 100),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1440),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _StrategyHeader(
                  status: status,
                  stopReason: portfolio.stopReason,
                  combinedScore: combinedScore,
                  activeAccount: active?.name,
                  recommendedAccount: recommended?.name,
                  accountCount: nonArchived.length,
                ),
                const SizedBox(height: 14),
                if (currencyTotals.length > 1)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _Notice(
                      icon: Icons.currency_exchange,
                      text: l10n.text(
                        'Totals are grouped by currency. Different currencies are never added together.',
                      ),
                    ),
                  ),
                for (final total in currencyTotals.values)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _CurrencySummary(total: total),
                  ),
                const SizedBox(height: 4),
                _HealthDistribution(
                  accounts: nonArchived,
                  scores: scores,
                  dashboard: dashboard,
                ),
                const SizedBox(height: 14),
                if (!compact)
                  _PrimaryTradingAction(
                    controller: controller,
                    portfolio: portfolio,
                    compact: false,
                  ),
                if (controller.hasOpenTradingSession &&
                    !portfolio.sessionActive) ...[
                  const SizedBox(height: 14),
                  _NextRecommendationCard(
                    controller: controller,
                    portfolio: portfolio,
                    recommended: recommended,
                  ),
                ],
                const SizedBox(height: 22),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        l10n.text('Your accounts'),
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ),
                    FilledButton.tonalIcon(
                      key: const Key('add-account'),
                      onPressed: () => _addAccount(context),
                      icon: const Icon(Icons.add),
                      label: Text(l10n.text('Add account')),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                if (nonArchived.isEmpty)
                  _EmptyAccounts(onAdd: () => _addAccount(context))
                else
                  _AccountCardGrid(
                    accounts: nonArchived,
                    scores: scores,
                    dailyLimits: dailyLimits,
                    sequence: controller.sequencePlan,
                    activeAccountId: portfolio.activeAccountId,
                    recommendedAccountId: recommendedId,
                    now: () => controller.currentTime,
                    onCooldownExpired: controller.refreshTimeSensitiveState,
                    onOpen: onOpenAccount,
                    onEdit: (account) => _editAccount(context, account),
                    onPause: (account) => _run(
                      context,
                      () => controller.toggleAccountPaused(account.id),
                    ),
                    onStart:
                        portfolio.sessionActive ||
                            controller.hasOpenTradingSession
                        ? null
                        : (account) => _setStartingAccount(context, account),
                  ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _addAccount(BuildContext context) async {
    final draft = controller.newAccountDraft(name: '', firmName: '');
    final result = await showSimplifiedAccountEditor(
      context,
      draft,
      policy: portfolio.riskPolicy,
    );
    if (result != null && context.mounted) {
      await _run(context, () => controller.saveAccount(result));
    }
  }

  Future<void> _editAccount(
    BuildContext context,
    PropFirmAccount account,
  ) async {
    final result = await showSimplifiedAccountEditor(
      context,
      account,
      policy: portfolio.riskPolicy,
    );
    if (result != null && context.mounted) {
      await _run(context, () => controller.saveAccount(result));
    }
  }

  Future<void> _setStartingAccount(
    BuildContext context,
    PropFirmAccount account,
  ) async {
    await _run(context, () => controller.setStartingAccount(account.id));
  }
}

class _StrategyHeader extends StatelessWidget {
  const _StrategyHeader({
    required this.status,
    required this.stopReason,
    required this.combinedScore,
    required this.activeAccount,
    required this.recommendedAccount,
    required this.accountCount,
  });

  final String status;
  final SessionStopReason? stopReason;
  final double combinedScore;
  final String? activeAccount;
  final String? recommendedAccount;
  final int accountCount;

  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.surfaceContainerHigh,
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final narrow = constraints.maxWidth < 700;
          final score = _HealthRing(score: combinedScore, size: 104);
          final details = Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.l10n.text('Strategy status'),
                style: Theme.of(context).textTheme.labelLarge,
              ),
              const SizedBox(height: 4),
              Text(
                stopReason == null
                    ? context.l10n.text(status)
                    : context.l10n.text('Stopped: {reason}', {
                        'reason': context.l10n.text(
                          sessionStopReasonTitle(stopReason!),
                        ),
                      }),
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 18,
                runSpacing: 10,
                children: [
                  _InlineValue(
                    label: 'Active account',
                    value: activeAccount ?? 'No active account',
                  ),
                  _InlineValue(
                    label: 'Recommended next',
                    value: recommendedAccount ?? 'No recommendation',
                  ),
                  _InlineValue(
                    label: 'Enabled accounts',
                    value: '$accountCount',
                  ),
                ],
              ),
            ],
          );
          return narrow
              ? Column(
                  children: [
                    score,
                    const SizedBox(height: 16),
                    Align(alignment: Alignment.centerLeft, child: details),
                  ],
                )
              : Row(
                  children: [
                    score,
                    const SizedBox(width: 22),
                    Expanded(child: details),
                  ],
                );
        },
      ),
    ),
  );
}

class _InlineValue extends StatelessWidget {
  const _InlineValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        context.l10n.text(label),
        style: Theme.of(context).textTheme.labelSmall,
      ),
      Text(
        context.l10n.text(value),
        style: const TextStyle(fontWeight: FontWeight.w700),
      ),
    ],
  );
}

class _CurrencyTotal {
  const _CurrencyTotal({
    required this.currency,
    required this.capital,
    required this.balance,
    required this.equity,
    required this.dailyRemaining,
    required this.maximumRemaining,
  });

  final String currency;
  final double capital;
  final double balance;
  final double equity;
  final double dailyRemaining;
  final double maximumRemaining;
}

Map<String, _CurrencyTotal> _currencyTotals(
  List<PropFirmAccount> accounts,
  Map<String, AccountHealthScore> scores,
) {
  final grouped = <String, List<PropFirmAccount>>{};
  for (final account in accounts) {
    grouped.putIfAbsent(account.currency.toUpperCase(), () => []).add(account);
  }
  return {
    for (final entry in grouped.entries)
      entry.key: _CurrencyTotal(
        currency: entry.key,
        capital: entry.value.fold(0, (sum, item) => sum + item.accountSize),
        balance: entry.value.fold(0, (sum, item) => sum + item.currentBalance),
        equity: entry.value.fold(0, (sum, item) => sum + item.referenceEquity),
        dailyRemaining: entry.value.fold(
          0,
          (sum, item) =>
              sum + (scores[item.id]?.lossLimits.dailyLossRemaining ?? 0),
        ),
        maximumRemaining: entry.value.fold(
          0,
          (sum, item) =>
              sum + (scores[item.id]?.lossLimits.maximumLossRemaining ?? 0),
        ),
      ),
  };
}

class _CurrencySummary extends StatelessWidget {
  const _CurrencySummary({required this.total});

  final _CurrencyTotal total;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Wrap(
        spacing: 24,
        runSpacing: 14,
        children: [
          _Metric('Capital', _money(total.capital, total.currency)),
          _Metric('Combined balance', _money(total.balance, total.currency)),
          _Metric('Combined equity', _money(total.equity, total.currency)),
          _Metric(
            'Daily capacity',
            _money(total.dailyRemaining, total.currency),
          ),
          _Metric(
            'Maximum-loss capacity',
            _money(total.maximumRemaining, total.currency),
          ),
        ],
      ),
    ),
  );
}

class _Metric extends StatelessWidget {
  const _Metric(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 180,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text(label),
          style: Theme.of(context).textTheme.labelMedium,
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 17),
        ),
      ],
    ),
  );
}

class _HealthDistribution extends StatelessWidget {
  const _HealthDistribution({
    required this.accounts,
    required this.scores,
    required this.dashboard,
  });

  final List<PropFirmAccount> accounts;
  final Map<String, AccountHealthScore> scores;
  final PortfolioDashboardSnapshot? dashboard;

  @override
  Widget build(BuildContext context) {
    final values = <(String, IconData, int)>[
      (
        'Healthy',
        Icons.check_circle_outline,
        _count(StrategyHealthBand.healthy),
      ),
      ('Moderate', Icons.info_outline, _count(StrategyHealthBand.moderate)),
      ('Warning', Icons.warning_amber, _count(StrategyHealthBand.warning)),
      ('Critical', Icons.error_outline, _count(StrategyHealthBand.critical)),
      (
        'Paused',
        Icons.pause_circle_outline,
        accounts
            .where((account) => account.status == AccountStatus.paused)
            .length,
      ),
      (
        'Accounts available today',
        Icons.play_circle_outline,
        dashboard?.accountsAvailableToday ?? 0,
      ),
      (
        'Daily limits reached',
        Icons.timer_off_outlined,
        dashboard?.dailyLimitsReached ?? 0,
      ),
      (
        'Risk-blocked accounts',
        Icons.gpp_bad_outlined,
        dashboard?.riskBlockedAccounts ?? 0,
      ),
      (
        'Payout-protected accounts',
        Icons.savings_outlined,
        dashboard?.protectedAccounts ?? 0,
      ),
    ];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final value in values)
              Chip(
                avatar: Icon(value.$2, size: 18),
                label: Text('${context.l10n.text(value.$1)} · ${value.$3}'),
              ),
          ],
        ),
      ),
    );
  }

  int _count(StrategyHealthBand band) =>
      accounts.where((account) => scores[account.id]?.band == band).length;
}

class _PrimaryTradingAction extends StatelessWidget {
  const _PrimaryTradingAction({
    required this.controller,
    required this.portfolio,
    required this.compact,
  });

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final active = portfolio.accounts
        .where((account) => account.id == portfolio.activeAccountId)
        .firstOrNull;
    final recommended = portfolio.accounts
        .where(
          (account) => account.id == controller.simplifiedRecommendedAccountId,
        )
        .firstOrNull;
    final noAccounts = portfolio.activeAccounts.isEmpty;
    final dashboard = controller.dashboardSnapshot;
    final stopped =
        dashboard?.session.isStopped == true ||
        portfolio.sessionStatus == PortfolioSessionStatus.stopped ||
        controller.simplifiedStartPlan?.portfolioStopped == true;
    final canStart = controller.canStartSimplifiedSession;
    final limits = controller.accountDailyTradeLimits;
    final allDailyLimitsReached =
        portfolio.activeAccounts.isNotEmpty &&
        portfolio.activeAccounts.every(
          (account) => limits[account.id]?.hasReachedLimit == true,
        );
    final nextReset = limits.values
        .where((result) => result.enabled)
        .map((result) => result.nextResetAt)
        .firstOrNull;
    final label = noAccounts
        ? 'Add your first account'
        : portfolio.sessionActive
        ? 'Record Trade Result'
        : allDailyLimitsReached
        ? 'All eligible accounts have reached their daily trade limits.'
        : stopped
        ? 'Trading stopped for today'
        : !canStart
        ? 'No eligible account'
        : controller.hasOpenTradingSession && recommended != null
        ? 'Start {account}'
        : 'Start Trading';
    final values = <String, Object?>{
      if (recommended != null) 'account': recommended.name,
    };
    final button = FilledButton.icon(
      key: const Key('primary-trading-action'),
      onPressed: noAccounts
          ? null
          : portfolio.sessionActive
          ? () => _record(context, active!)
          : !canStart
          ? null
          : () => showStartTradingDialog(
              context,
              controller: controller,
              portfolio: portfolio,
              preferredAccountId: recommended?.id,
            ),
      icon: Icon(
        portfolio.sessionActive ? Icons.fact_check_outlined : Icons.play_arrow,
      ),
      label: Text(context.l10n.text(label, values)),
    );
    final end = controller.hasOpenTradingSession
        ? OutlinedButton.icon(
            onPressed: () => _endSession(context),
            icon: const Icon(Icons.stop_circle_outlined),
            label: Text(context.l10n.text('End Trading Session')),
          )
        : const SizedBox.shrink();
    final actions = compact
        ? Row(
            children: [
              if (controller.hasOpenTradingSession) ...[
                end,
                const SizedBox(width: 8),
              ],
              Expanded(child: button),
            ],
          )
        : Wrap(spacing: 10, runSpacing: 10, children: [button, end]);
    return Material(
      color: compact
          ? Theme.of(context).colorScheme.surfaceContainer
          : Colors.transparent,
      elevation: compact ? 6 : 0,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: compact
              ? const EdgeInsets.fromLTRB(14, 10, 14, 10)
              : EdgeInsets.zero,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              actions,
              if (allDailyLimitsReached && nextReset != null) ...[
                const SizedBox(height: 6),
                Text(
                  context.l10n.text(
                    'Trading becomes available after the next daily reset: {time}',
                    {
                      'time': DateFormat.yMd().add_Hm().format(
                        nextReset.toLocal(),
                      ),
                    },
                  ),
                  style: Theme.of(context).textTheme.labelSmall,
                  textAlign: compact ? TextAlign.center : TextAlign.start,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _record(BuildContext context, PropFirmAccount account) async {
    final preview = await showRecordTradeResultSheet(
      context,
      controller: controller,
      account: account,
    );
    if (preview != null && context.mounted) {
      await showNextRecommendationDialog(
        context,
        controller: controller,
        previous: preview,
      );
    }
  }

  Future<void> _endSession(BuildContext context) async {
    final results = controller.currentSessionResults;
    final confirmed = await _confirm(
      context,
      title: 'End Trading Session?',
      message: 'The session will be completed and its result history retained.',
      confirmLabel: 'End session',
    );
    if (!confirmed || !context.mounted) return;
    await _run(context, controller.completeTradingSession);
    if (context.mounted) {
      await showTradingSessionSummary(
        context,
        controller: controller,
        results: results,
      );
    }
  }
}

class _NextRecommendationCard extends StatelessWidget {
  const _NextRecommendationCard({
    required this.controller,
    required this.portfolio,
    required this.recommended,
  });

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;
  final PropFirmAccount? recommended;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            context.l10n.text('Recommended next account'),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 6),
          Text(
            recommended?.name ?? context.l10n.text('No eligible account'),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 4),
          Text(
            context.l10n.text(
              'This account has the strongest health score and remaining loss capacity among eligible accounts.',
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            children: [
              if (recommended != null)
                FilledButton(
                  onPressed: () => _run(
                    context,
                    () => controller.startSessionWithAccount(recommended!.id),
                  ),
                  child: Text(
                    context.l10n.text('Start {account}', {
                      'account': recommended!.name,
                    }),
                  ),
                ),
              TextButton(
                onPressed: controller.canStartSimplifiedSession
                    ? () => showStartTradingDialog(
                        context,
                        controller: controller,
                        portfolio: portfolio,
                      )
                    : null,
                child: Text(context.l10n.text('Choose Another Account')),
              ),
            ],
          ),
        ],
      ),
    ),
  );
}

class _EmptyAccounts extends StatelessWidget {
  const _EmptyAccounts({required this.onAdd});

  final VoidCallback onAdd;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        children: [
          const Icon(Icons.account_balance_wallet_outlined, size: 46),
          const SizedBox(height: 12),
          Text(
            context.l10n.text('No accounts configured'),
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: 6),
          Text(
            context.l10n.text('Add a funded or evaluation account to begin.'),
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: onAdd,
            icon: const Icon(Icons.add),
            label: Text(context.l10n.text('Add account')),
          ),
        ],
      ),
    ),
  );
}

class _AccountCardGrid extends StatelessWidget {
  const _AccountCardGrid({
    required this.accounts,
    required this.scores,
    required this.dailyLimits,
    required this.sequence,
    required this.activeAccountId,
    required this.recommendedAccountId,
    required this.now,
    required this.onCooldownExpired,
    required this.onOpen,
    required this.onEdit,
    required this.onPause,
    required this.onStart,
  });

  final List<PropFirmAccount> accounts;
  final Map<String, AccountHealthScore> scores;
  final Map<String, AccountDailyTradeLimitResult> dailyLimits;
  final AccountSequencePlan? sequence;
  final String? activeAccountId;
  final String? recommendedAccountId;
  final DateTime Function() now;
  final VoidCallback onCooldownExpired;
  final ValueChanged<String> onOpen;
  final ValueChanged<PropFirmAccount> onEdit;
  final ValueChanged<PropFirmAccount> onPause;
  final ValueChanged<PropFirmAccount>? onStart;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final columns = constraints.maxWidth >= 1180
          ? 3
          : constraints.maxWidth >= 700
          ? 2
          : 1;
      final width = (constraints.maxWidth - (columns - 1) * 12) / columns;
      return Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          for (final account in accounts)
            SizedBox(
              width: width,
              child: _SimplifiedAccountCard(
                account: account,
                score: scores[account.id]!,
                dailyTradeLimit: dailyLimits[account.id]!,
                sequencePosition: sequence?.steps
                    .where((step) => step.accountId == account.id)
                    .firstOrNull
                    ?.position,
                isActive: activeAccountId == account.id,
                isRecommended: recommendedAccountId == account.id,
                now: now,
                onCooldownExpired: onCooldownExpired,
                onOpen: () => onOpen(account.id),
                onEdit: () => onEdit(account),
                onPause: () => onPause(account),
                onStart:
                    sequence?.evaluations[account.id]?.isEligible == true &&
                        onStart != null
                    ? () => onStart!(account)
                    : null,
              ),
            ),
        ],
      );
    },
  );
}

bool scoreEligible(AccountHealthScore? score) => score?.isEligible == true;

class _SimplifiedAccountCard extends StatelessWidget {
  const _SimplifiedAccountCard({
    required this.account,
    required this.score,
    required this.dailyTradeLimit,
    required this.sequencePosition,
    required this.isActive,
    required this.isRecommended,
    required this.now,
    required this.onCooldownExpired,
    required this.onOpen,
    required this.onEdit,
    required this.onPause,
    required this.onStart,
  });

  final PropFirmAccount account;
  final AccountHealthScore score;
  final AccountDailyTradeLimitResult dailyTradeLimit;
  final int? sequencePosition;
  final bool isActive;
  final bool isRecommended;
  final DateTime Function() now;
  final VoidCallback onCooldownExpired;
  final VoidCallback onOpen;
  final VoidCallback onEdit;
  final VoidCallback onPause;
  final VoidCallback? onStart;

  @override
  Widget build(BuildContext context) {
    final status = isActive
        ? 'Trading Now'
        : isRecommended
        ? 'Recommended Next'
        : account.status == AccountStatus.paused
        ? 'Paused'
        : score.unavailableReasonKey != null
        ? _unavailableLabel(score.unavailableReasonKey!)
        : dailyTradeLimit.hasReachedLimit
        ? 'Daily trade limit reached ({used}/{limit})'
        : _bandLabel(score.band);
    final progress = account.isEvaluationAccount
        ? score.targetProgress
        : score.payoutProgress;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  _HealthRing(score: score.score, size: 66),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          account.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        Text(
                          '${_maskAccount(account.identifier)} · ${context.l10n.text(_accountTypeLabel(account.accountType))}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        if (account.propFirmName.trim().isNotEmpty)
                          Text(
                            account.propFirmName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                      ],
                    ),
                  ),
                  if (sequencePosition != null)
                    CircleAvatar(radius: 15, child: Text('$sequencePosition')),
                ],
              ),
              const SizedBox(height: 12),
              Align(
                alignment: Alignment.centerLeft,
                child: Chip(
                  avatar: Icon(_bandIcon(score.band), size: 16),
                  label: Text(
                    context.l10n.text(status, {
                      'used': dailyTradeLimit.currentTrades,
                      'limit': dailyTradeLimit.limit,
                    }),
                  ),
                ),
              ),
              if ({
                AccountStatus.coolingAfterWin,
                AccountStatus.coolingAfterLoss,
              }.contains(account.status)) ...[
                const SizedBox(height: 8),
                _CoolingPeriodRemaining(
                  cooldownUntil: account.cooldownUntil,
                  now: now,
                  onExpired: onCooldownExpired,
                ),
              ],
              const SizedBox(height: 8),
              _DailyTradeProgress(result: dailyTradeLimit),
              const SizedBox(height: 8),
              _ValueRow(
                'Balance',
                _money(account.currentBalance, account.currency),
              ),
              _ValueRow(
                'Equity',
                _money(account.referenceEquity, account.currency),
              ),
              _ValueRow(
                "Today's P/L",
                _money(
                  account.situation.todayRealisedProfitLoss,
                  account.currency,
                ),
              ),
              _ValueRow(
                'Total P/L',
                _money(account.currentProfit, account.currency),
              ),
              const SizedBox(height: 8),
              _CapacityBar(
                label: 'Daily loss remaining',
                remaining: score.lossLimits.dailyLossRemaining,
                limit: score.lossLimits.dailyLossAmount,
                currency: account.currency,
              ),
              const SizedBox(height: 8),
              _CapacityBar(
                label: 'Maximum loss remaining',
                remaining: score.lossLimits.maximumLossRemaining,
                limit: score.lossLimits.maximumLossAmount,
                currency: account.currency,
              ),
              if ((account.isEvaluationAccount && account.profitTarget > 0) ||
                  (account.isFundedAccount && account.minimumPayout > 0)) ...[
                const SizedBox(height: 8),
                Text(
                  context.l10n.text(
                    account.isEvaluationAccount
                        ? 'Profit target progress'
                        : 'Payout progress',
                  ),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
                LinearProgressIndicator(value: progress),
                if (account.isFundedAccount && account.nextPayoutDate != null)
                  Text(
                    '${context.l10n.text('Next payout date')}: '
                    '${DateFormat.yMMMd().format(account.nextPayoutDate!)}',
                    style: Theme.of(context).textTheme.labelSmall,
                  ),
              ],
              const SizedBox(height: 12),
              Wrap(
                spacing: 4,
                runSpacing: 4,
                children: [
                  TextButton(
                    onPressed: onOpen,
                    child: Text(context.l10n.text('View Details')),
                  ),
                  TextButton(
                    onPressed: onEdit,
                    child: Text(context.l10n.text('Edit Account')),
                  ),
                  TextButton(
                    onPressed: onPause,
                    child: Text(
                      context.l10n.text(
                        account.status == AccountStatus.paused
                            ? 'Resume'
                            : 'Pause',
                      ),
                    ),
                  ),
                  if (onStart != null)
                    TextButton(
                      key: Key('set-starting-${account.id}'),
                      onPressed: onStart,
                      child: Text(context.l10n.text('Set as Starting Account')),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DailyTradeProgress extends StatelessWidget {
  const _DailyTradeProgress({required this.result});

  final AccountDailyTradeLimitResult result;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    if (!result.enabled) {
      return Row(
        children: [
          const Icon(Icons.all_inclusive, size: 18),
          const SizedBox(width: 8),
          Expanded(child: Text(l10n.text('Daily trade limit: Disabled'))),
        ],
      );
    }
    final reached = result.hasReachedLimit;
    return Semantics(
      label: l10n.text('Daily trades: {current}/{limit}', {
        'current': result.currentTrades,
        'limit': result.limit,
      }),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                reached ? Icons.timer_off_outlined : Icons.timer_outlined,
                size: 18,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  l10n.text('Daily trades: {current}/{limit}', {
                    'current': result.currentTrades,
                    'limit': result.limit,
                  }),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
              Text(
                reached
                    ? l10n.text('Limit reached')
                    : l10n.text('{count} remaining', {
                        'count': result.remainingTrades ?? 0,
                      }),
              ),
            ],
          ),
          const SizedBox(height: 6),
          LinearProgressIndicator(value: result.usagePercentage),
          if (reached) ...[
            const SizedBox(height: 4),
            Text(
              l10n.text('Available again after the daily reset.'),
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ],
        ],
      ),
    );
  }
}

@visibleForTesting
String formatCoolingDuration(Duration duration) {
  final totalSeconds = math.max(0, duration.inSeconds);
  final days = totalSeconds ~/ Duration.secondsPerDay;
  final hours =
      (totalSeconds % Duration.secondsPerDay) ~/ Duration.secondsPerHour;
  final minutes =
      (totalSeconds % Duration.secondsPerHour) ~/ Duration.secondsPerMinute;
  final seconds = totalSeconds % Duration.secondsPerMinute;
  if (days > 0) return '${days}d ${hours}h ${minutes}m';
  if (hours > 0) return '${hours}h ${minutes}m ${seconds}s';
  return '${minutes}m ${seconds.toString().padLeft(2, '0')}s';
}

class _CoolingPeriodRemaining extends StatefulWidget {
  const _CoolingPeriodRemaining({
    required this.cooldownUntil,
    required this.now,
    required this.onExpired,
  });

  final DateTime? cooldownUntil;
  final DateTime Function() now;
  final VoidCallback onExpired;

  @override
  State<_CoolingPeriodRemaining> createState() =>
      _CoolingPeriodRemainingState();
}

class _CoolingPeriodRemainingState extends State<_CoolingPeriodRemaining> {
  Timer? _timer;
  Duration _remaining = Duration.zero;
  bool _reportedExpiration = false;

  @override
  void initState() {
    super.initState();
    _restartTimer();
  }

  @override
  void didUpdateWidget(covariant _CoolingPeriodRemaining oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.cooldownUntil != widget.cooldownUntil ||
        oldWidget.now != widget.now) {
      _restartTimer();
    }
  }

  void _restartTimer() {
    _timer?.cancel();
    _reportedExpiration = false;
    _remaining = _calculateRemaining();
    if (_remaining > Duration.zero) {
      _timer = Timer.periodic(const Duration(seconds: 1), _tick);
    }
  }

  Duration _calculateRemaining() {
    final until = widget.cooldownUntil;
    if (until == null) return Duration.zero;
    final remaining = until.difference(widget.now());
    return remaining.isNegative ? Duration.zero : remaining;
  }

  void _tick(Timer timer) {
    final remaining = _calculateRemaining();
    if (!mounted) return;
    setState(() => _remaining = remaining);
    if (remaining <= Duration.zero) {
      timer.cancel();
      if (!_reportedExpiration) {
        _reportedExpiration = true;
        widget.onExpired();
      }
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final until = widget.cooldownUntil;
    if (until != null && _remaining <= Duration.zero) {
      return const SizedBox.shrink();
    }
    return DecoratedBox(
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.secondaryContainer,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        child: Row(
          children: [
            const Icon(Icons.timer_outlined, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${context.l10n.text('Cooling period remaining')}: '
                    '${until == null ? context.l10n.text('Not set') : formatCoolingDuration(_remaining)}',
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  if (until != null)
                    Text(
                      '${context.l10n.text('Cooling until')}: '
                      '${DateFormat.yMd().add_Hms().format(until.toLocal())}',
                      style: Theme.of(context).textTheme.labelSmall,
                    ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ValueRow extends StatelessWidget {
  const _ValueRow(this.label, this.value);
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 2),
    child: Row(
      children: [
        Expanded(child: Text(context.l10n.text(label))),
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
      ],
    ),
  );
}

class _CapacityBar extends StatelessWidget {
  const _CapacityBar({
    required this.label,
    required this.remaining,
    required this.limit,
    required this.currency,
  });
  final String label;
  final double remaining;
  final double limit;
  final String currency;
  @override
  Widget build(BuildContext context) {
    final ratio = limit <= 0 ? 0.0 : (remaining / limit).clamp(0, 1).toDouble();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                context.l10n.text(label),
                style: Theme.of(context).textTheme.labelSmall,
              ),
            ),
            Text(
              _money(remaining, currency),
              style: Theme.of(context).textTheme.labelSmall,
            ),
          ],
        ),
        const SizedBox(height: 3),
        LinearProgressIndicator(value: ratio),
      ],
    );
  }
}

class _HealthRing extends StatelessWidget {
  const _HealthRing({required this.score, required this.size});
  final double score;
  final double size;
  @override
  Widget build(BuildContext context) => SizedBox.square(
    dimension: size,
    child: Stack(
      alignment: Alignment.center,
      children: [
        SizedBox.square(
          dimension: size,
          child: CircularProgressIndicator(
            value: (score / 100).clamp(0, 1),
            strokeWidth: math.max(5, size / 11),
            backgroundColor: Theme.of(
              context,
            ).colorScheme.surfaceContainerHighest,
          ),
        ),
        Text(
          score.toStringAsFixed(0),
          style: TextStyle(fontSize: size / 4.2, fontWeight: FontWeight.w900),
        ),
      ],
    ),
  );
}

class SimplifiedAccountDetailsPage extends StatelessWidget {
  const SimplifiedAccountDetailsPage({
    super.key,
    required this.controller,
    required this.portfolio,
    required this.selectedAccountId,
    required this.onSelected,
  });

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;
  final String? selectedAccountId;
  final ValueChanged<String?> onSelected;

  @override
  Widget build(BuildContext context) {
    final accounts = portfolio.accounts
        .where((account) => !account.isArchived)
        .toList();
    if (accounts.isEmpty) {
      return Center(
        child: Text(
          context.l10n.text('Add an account from Overview to see details.'),
        ),
      );
    }
    final account =
        accounts.where((item) => item.id == selectedAccountId).firstOrNull ??
        accounts.first;
    final score = controller.simplifiedHealthScores[account.id]!;
    final dailyTradeLimit = controller.accountDailyTradeLimits[account.id]!;
    final results =
        portfolio.results
            .where((result) => result.accountId == account.id)
            .toList()
          ..sort((left, right) => right.resultTime.compareTo(left.resultTime));
    return ListView(
      key: const Key('simplified-account-details'),
      padding: const EdgeInsets.all(18),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1120),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: account.id,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Select account'),
                  ),
                  items: [
                    for (final item in accounts)
                      DropdownMenuItem(value: item.id, child: Text(item.name)),
                  ],
                  onChanged: onSelected,
                ),
                const SizedBox(height: 14),
                _AccountDetailsHeader(
                  account: account,
                  score: score,
                  now: () => controller.currentTime,
                  onCooldownExpired: controller.refreshTimeSensitiveState,
                ),
                const SizedBox(height: 14),
                _DetailsSection(
                  title: 'Financial Information',
                  children: [
                    _DetailMetric(
                      'Account capital',
                      _money(account.accountSize, account.currency),
                    ),
                    _DetailMetric(
                      'Balance',
                      _money(account.currentBalance, account.currency),
                    ),
                    _DetailMetric(
                      'Equity',
                      _money(account.referenceEquity, account.currency),
                    ),
                    _DetailMetric(
                      'Total P/L',
                      _money(account.currentProfit, account.currency),
                    ),
                    _DetailMetric(
                      "Today's P/L",
                      _money(
                        account.situation.todayRealisedProfitLoss,
                        account.currency,
                      ),
                    ),
                    _DetailMetric(
                      'Current drawdown',
                      _money(account.drawdown, account.currency),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _DetailsSection(
                  title: 'Risk Limits',
                  footer: Column(
                    children: [
                      _CapacityBar(
                        label: 'Daily loss remaining',
                        remaining: score.lossLimits.dailyLossRemaining,
                        limit: score.lossLimits.dailyLossAmount,
                        currency: account.currency,
                      ),
                      const SizedBox(height: 10),
                      _CapacityBar(
                        label: 'Maximum loss remaining',
                        remaining: score.lossLimits.maximumLossRemaining,
                        limit: score.lossLimits.maximumLossAmount,
                        currency: account.currency,
                      ),
                    ],
                  ),
                  children: [
                    _DetailMetric(
                      'Maximum loss limit',
                      _money(
                        score.lossLimits.maximumLossAmount,
                        account.currency,
                      ),
                    ),
                    _DetailMetric(
                      'Maximum loss used',
                      _money(
                        score.lossLimits.maximumLossUsed,
                        account.currency,
                      ),
                    ),
                    _DetailMetric(
                      'Maximum loss remaining',
                      _money(
                        score.lossLimits.maximumLossRemaining,
                        account.currency,
                      ),
                    ),
                    _DetailMetric(
                      'Daily loss limit',
                      _money(
                        score.lossLimits.dailyLossAmount,
                        account.currency,
                      ),
                    ),
                    _DetailMetric(
                      'Daily loss used',
                      _money(score.lossLimits.dailyLossUsed, account.currency),
                    ),
                    _DetailMetric(
                      'Daily loss remaining',
                      _money(
                        score.lossLimits.dailyLossRemaining,
                        account.currency,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _DetailsSection(
                  title: 'Daily Trading Controls',
                  footer: _DailyTradeProgress(result: dailyTradeLimit),
                  children: [
                    _DetailMetric(
                      'Daily trade limit',
                      dailyTradeLimit.enabled
                          ? '${dailyTradeLimit.limit}'
                          : context.l10n.text('Disabled'),
                    ),
                    _DetailMetric(
                      'Trades today',
                      '${dailyTradeLimit.currentTrades}',
                    ),
                    _DetailMetric(
                      'Remaining trades',
                      dailyTradeLimit.enabled
                          ? '${dailyTradeLimit.remainingTrades}'
                          : context.l10n.text('Unlimited'),
                    ),
                    _DetailMetric(
                      'Next daily reset',
                      DateFormat.yMd().add_Hm().format(
                        dailyTradeLimit.nextResetAt.toLocal(),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _ConditionalAccountSection(account: account, score: score),
                const SizedBox(height: 12),
                _HealthBreakdown(score: score),
                const SizedBox(height: 12),
                _TradingHistory(results: results, account: account),
                const SizedBox(height: 12),
                _AccountManagement(
                  controller: controller,
                  account: account,
                  policy: portfolio.riskPolicy,
                  canSetStarting: !controller.hasOpenTradingSession,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _AccountDetailsHeader extends StatelessWidget {
  const _AccountDetailsHeader({
    required this.account,
    required this.score,
    required this.now,
    required this.onCooldownExpired,
  });
  final PropFirmAccount account;
  final AccountHealthScore score;
  final DateTime Function() now;
  final VoidCallback onCooldownExpired;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Row(
        children: [
          _HealthRing(score: score.score, size: 86),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  account.name,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  '${_maskAccount(account.identifier)} · ${context.l10n.text(_accountTypeLabel(account.accountType))}',
                ),
                Text(account.propFirmName),
                const SizedBox(height: 6),
                Text(
                  '${context.l10n.text('Health status')}: ${context.l10n.text(_bandLabel(score.band))}',
                ),
                if ({
                  AccountStatus.coolingAfterWin,
                  AccountStatus.coolingAfterLoss,
                }.contains(account.status)) ...[
                  const SizedBox(height: 8),
                  _CoolingPeriodRemaining(
                    cooldownUntil: account.cooldownUntil,
                    now: now,
                    onExpired: onCooldownExpired,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _DetailsSection extends StatelessWidget {
  const _DetailsSection({
    required this.title,
    required this.children,
    this.footer,
  });
  final String title;
  final List<Widget> children;
  final Widget? footer;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            context.l10n.text(title),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          Wrap(spacing: 12, runSpacing: 12, children: children),
          if (footer != null) ...[const SizedBox(height: 16), footer!],
        ],
      ),
    ),
  );
}

class _DetailMetric extends StatelessWidget {
  const _DetailMetric(this.label, this.value);
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => SizedBox(
    width: 230,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text(label),
          style: Theme.of(context).textTheme.labelMedium,
        ),
        Text(
          value,
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
      ],
    ),
  );
}

class _ConditionalAccountSection extends StatelessWidget {
  const _ConditionalAccountSection({
    required this.account,
    required this.score,
  });
  final PropFirmAccount account;
  final AccountHealthScore score;
  @override
  Widget build(BuildContext context) {
    if (account.isEvaluationAccount) {
      return _DetailsSection(
        title: 'Evaluation Progress',
        children: [
          _DetailMetric(
            'Profit target',
            _money(account.profitTarget, account.currency),
          ),
          _DetailMetric(
            'Progress',
            '${(score.targetProgress * 100).toStringAsFixed(0)}%',
          ),
          _DetailMetric(
            'Remaining amount',
            _money(
              math.max(0, account.profitTarget - account.currentProfit),
              account.currency,
            ),
          ),
          _DetailMetric(
            'Pass status',
            score.isEvaluationPassed
                ? context.l10n.text('Evaluation Passed')
                : context.l10n.text('In progress'),
          ),
        ],
      );
    }
    return _DetailsSection(
      title: 'Funded Account Information',
      children: [
        _DetailMetric(
          'Minimum payout amount',
          _money(account.minimumPayout, account.currency),
        ),
        _DetailMetric(
          'Payout progress',
          '${(score.payoutProgress * 100).toStringAsFixed(0)}%',
        ),
        _DetailMetric(
          'Amount remaining',
          _money(
            math.max(0, account.minimumPayout - account.currentProfit),
            account.currency,
          ),
        ),
        _DetailMetric(
          'Next payout date',
          account.nextPayoutDate == null
              ? context.l10n.text('Not set')
              : DateFormat.yMMMd().format(account.nextPayoutDate!),
        ),
      ],
    );
  }
}

class _HealthBreakdown extends StatelessWidget {
  const _HealthBreakdown({required this.score});
  final AccountHealthScore score;
  @override
  Widget build(BuildContext context) => _DetailsSection(
    title: 'Health Score Breakdown',
    children: [
      for (final factor in score.factors)
        _DetailMetric(
          factor.key,
          factor.maximumPoints <= 0
              ? factor.points.toStringAsFixed(0)
              : '${factor.points.toStringAsFixed(0)} / ${factor.maximumPoints.toStringAsFixed(0)}',
        ),
    ],
  );
}

class _TradingHistory extends StatelessWidget {
  const _TradingHistory({required this.results, required this.account});
  final List<RecordedTradeResult> results;
  final PropFirmAccount account;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            context.l10n.text('Trading History'),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 10),
          if (results.isEmpty)
            Text(
              context.l10n.text(
                'No strategy results recorded for this account.',
              ),
            )
          else
            for (final result in results.take(20))
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Icon(_resultIcon(result.type)),
                title: Text(
                  '${context.l10n.text(_resultLabel(result.type))} · ${_money(result.amount, account.currency)}',
                ),
                subtitle: Text(
                  '${DateFormat.yMd().add_Hm().format(result.resultTime.toLocal())}'
                  '${result.balanceAfter == null ? '' : ' · ${context.l10n.text('Balance after')}: ${_money(result.balanceAfter!, account.currency)}'}'
                  '${result.healthScoreAfter == null ? '' : ' · ${context.l10n.text('Health')}: ${result.healthScoreAfter!.toStringAsFixed(0)}'}',
                ),
              ),
        ],
      ),
    ),
  );
}

class _AccountManagement extends StatelessWidget {
  const _AccountManagement({
    required this.controller,
    required this.account,
    required this.policy,
    required this.canSetStarting,
  });
  final PropFirmPlannerController controller;
  final PropFirmAccount account;
  final PortfolioRiskPolicy policy;
  final bool canSetStarting;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            context.l10n.text('Account Management'),
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              FilledButton.tonalIcon(
                onPressed: () => _edit(context),
                icon: const Icon(Icons.edit_outlined),
                label: Text(context.l10n.text('Edit Account')),
              ),
              OutlinedButton.icon(
                onPressed: () => _run(
                  context,
                  () => controller.toggleAccountPaused(account.id),
                ),
                icon: Icon(
                  account.status == AccountStatus.paused
                      ? Icons.play_arrow
                      : Icons.pause,
                ),
                label: Text(
                  context.l10n.text(
                    account.status == AccountStatus.paused ? 'Resume' : 'Pause',
                  ),
                ),
              ),
              OutlinedButton.icon(
                onPressed: () => _reset(context),
                icon: const Icon(Icons.restart_alt),
                label: Text(context.l10n.text('Reset daily statistics')),
              ),
              if (canSetStarting)
                OutlinedButton.icon(
                  onPressed: () => _run(
                    context,
                    () => controller.setStartingAccount(account.id),
                  ),
                  icon: const Icon(Icons.flag_outlined),
                  label: Text(context.l10n.text('Set as Starting Account')),
                ),
              OutlinedButton.icon(
                style: OutlinedButton.styleFrom(
                  foregroundColor: Theme.of(context).colorScheme.error,
                ),
                onPressed: () => _remove(context),
                icon: const Icon(Icons.archive_outlined),
                label: Text(context.l10n.text('Archive or delete')),
              ),
            ],
          ),
        ],
      ),
    ),
  );

  Future<void> _edit(BuildContext context) async {
    final updated = await showSimplifiedAccountEditor(
      context,
      account,
      policy: policy,
    );
    if (updated != null && context.mounted) {
      await _run(context, () => controller.saveAccount(updated));
    }
  }

  Future<void> _reset(BuildContext context) async {
    final confirmed = await _confirm(
      context,
      title: 'Reset daily statistics?',
      message:
          'Use this only after the configured prop-firm trading day has reset. Historical results remain.',
      confirmLabel: 'Reset daily statistics',
    );
    if (confirmed && context.mounted) {
      await _run(context, () => controller.resetAccountToday(account.id));
    }
  }

  Future<void> _remove(BuildContext context) async {
    final confirmed = await _confirm(
      context,
      title: 'Archive or delete this account?',
      message:
          'Accounts with result history are archived. Accounts without history can be removed safely.',
      confirmLabel: 'Continue',
    );
    if (confirmed && context.mounted) {
      await _run(context, () => controller.removeOrArchiveAccount(account.id));
    }
  }
}

Future<PropFirmAccount?> showSimplifiedAccountEditor(
  BuildContext context,
  PropFirmAccount account, {
  PortfolioRiskPolicy? policy,
}) => Navigator.of(context).push<PropFirmAccount>(
  MaterialPageRoute(
    fullscreenDialog: true,
    builder: (_) => _SimplifiedAccountEditor(account: account, policy: policy),
  ),
);

class _SimplifiedAccountEditor extends StatefulWidget {
  const _SimplifiedAccountEditor({required this.account, this.policy});
  final PropFirmAccount account;
  final PortfolioRiskPolicy? policy;
  @override
  State<_SimplifiedAccountEditor> createState() =>
      _SimplifiedAccountEditorState();
}

class _SimplifiedAccountEditorState extends State<_SimplifiedAccountEditor> {
  final _formKey = GlobalKey<FormState>();
  late final Map<String, TextEditingController> _fields;
  late PropFirmAccountType _type;
  late LossLimitType _dailyType;
  late LossLimitType _maximumType;
  late bool _enabled;
  late bool _dailyTradeLimitEnabled;
  DateTime? _payoutDate;

  @override
  void initState() {
    super.initState();
    final account = widget.account;
    _type = account.accountType;
    _dailyType = account.dailyLossType;
    _maximumType = account.maximumLossType;
    _enabled = account.status != AccountStatus.paused;
    _dailyTradeLimitEnabled = account.dailyTradeLimitEnabled;
    _payoutDate = account.nextPayoutDate;
    _fields = {
      'name': TextEditingController(text: account.name),
      'number': TextEditingController(text: account.identifier),
      'firm': TextEditingController(text: account.propFirmName),
      'currency': TextEditingController(text: account.currency),
      'capital': _controller(account.accountSize),
      'balance': _controller(account.currentBalance),
      'equity': _controller(account.referenceEquity),
      'daily': _controller(account.dailyLossLimit),
      'maximum': _controller(account.maximumLossLimit),
      'payout': _controller(account.minimumPayout),
      'target': _controller(account.profitTarget),
      'dailyTradeLimit': TextEditingController(
        text: '${account.maxTradesPerDay}',
      ),
      'notes': TextEditingController(text: account.notes),
    };
  }

  @override
  void dispose() {
    for (final field in _fields.values) {
      field.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        context.l10n.text(
          widget.account.name.trim().isEmpty ? 'Add account' : 'Edit Account',
        ),
      ),
      actions: [
        Padding(
          padding: const EdgeInsets.only(right: 12),
          child: FilledButton(
            key: const Key('save-simplified-account'),
            onPressed: _save,
            child: Text(context.l10n.text('Save account')),
          ),
        ),
      ],
    ),
    body: Form(
      key: _formKey,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          _Notice(
            icon: Icons.security_outlined,
            text: context.l10n.text(
              'Never enter a broker password or trading credential.',
            ),
          ),
          const SizedBox(height: 16),
          SegmentedButton<PropFirmAccountType>(
            segments: [
              ButtonSegment(
                value: PropFirmAccountType.funded,
                label: Text(context.l10n.text('Funded Account')),
                icon: const Icon(Icons.verified_outlined),
              ),
              ButtonSegment(
                value: PropFirmAccountType.evaluation,
                label: Text(context.l10n.text('Evaluation Account')),
                icon: const Icon(Icons.assignment_outlined),
              ),
            ],
            selected: {_type},
            onSelectionChanged: (value) => setState(() => _type = value.first),
          ),
          const SizedBox(height: 18),
          _EditorSection(
            title: 'Account configuration',
            children: [
              _textField('name', 'Account name', required: true),
              _textField('number', 'Account number', required: true),
              _textField('firm', 'Prop firm name'),
              _textField('currency', 'Currency', required: true),
              _numberField('capital', 'Account capital', positive: true),
              _numberField('balance', 'Current balance'),
              _numberField('equity', 'Current equity'),
            ],
          ),
          const SizedBox(height: 14),
          _EditorSection(
            title: 'Risk limits',
            children: [
              _LimitField(
                controller: _fields['maximum']!,
                label: 'Maximum loss limit',
                value: _maximumType,
                onChanged: (value) => setState(() => _maximumType = value),
              ),
              _LimitField(
                controller: _fields['daily']!,
                label: 'Daily loss limit',
                value: _dailyType,
                onChanged: (value) => setState(() => _dailyType = value),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _EditorSection(
            title: 'Daily Trading Controls',
            children: [
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _dailyTradeLimitEnabled,
                onChanged: (value) =>
                    setState(() => _dailyTradeLimitEnabled = value),
                title: Text(context.l10n.text('Enable daily trade limit')),
                subtitle: Text(
                  context.l10n.text(
                    'This limit applies only to this account. Other eligible accounts can continue trading.',
                  ),
                ),
              ),
              TextFormField(
                key: const Key('daily-trade-limit-field'),
                controller: _fields['dailyTradeLimit'],
                enabled: _dailyTradeLimitEnabled,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                decoration: InputDecoration(
                  labelText: context.l10n.text('Maximum trades per day'),
                  helperText: context.l10n.text(
                    'Enter a whole number from 1 to 100.',
                  ),
                ),
                validator: (value) {
                  if (!_dailyTradeLimitEnabled) return null;
                  final parsed = int.tryParse((value ?? '').trim());
                  if (parsed == null || parsed < 1 || parsed > 100) {
                    return context.l10n.text(
                      'Enter a whole number from 1 to 100.',
                    );
                  }
                  return null;
                },
              ),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.schedule_outlined),
                title: Text(context.l10n.text('Daily reset information')),
                subtitle: Text(_dailyResetDescription(context)),
              ),
            ],
          ),
          const SizedBox(height: 14),
          _EditorSection(
            title: _type == PropFirmAccountType.funded
                ? 'Funded Account Information'
                : 'Evaluation Progress',
            children: _type == PropFirmAccountType.funded
                ? [
                    _numberField(
                      'payout',
                      'Minimum payout amount',
                      allowZero: true,
                    ),
                    OutlinedButton.icon(
                      onPressed: _pickPayoutDate,
                      icon: const Icon(Icons.event_outlined),
                      label: Text(
                        _payoutDate == null
                            ? context.l10n.text('Set next payout date')
                            : '${context.l10n.text('Next payout date')}: ${DateFormat.yMMMd().format(_payoutDate!)}',
                      ),
                    ),
                  ]
                : [_numberField('target', 'Profit target', positive: true)],
          ),
          const SizedBox(height: 14),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: _enabled,
            onChanged: (value) => setState(() => _enabled = value),
            title: Text(context.l10n.text('Account enabled')),
            subtitle: Text(
              context.l10n.text(
                'Paused accounts are excluded from recommendations.',
              ),
            ),
          ),
          TextFormField(
            controller: _fields['notes'],
            maxLines: 4,
            decoration: InputDecoration(labelText: context.l10n.text('Notes')),
          ),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: _save,
            child: Text(context.l10n.text('Save account')),
          ),
        ],
      ),
    ),
  );

  Widget _textField(String key, String label, {bool required = false}) =>
      TextFormField(
        controller: _fields[key],
        decoration: InputDecoration(labelText: context.l10n.text(label)),
        validator: required
            ? (value) => (value ?? '').trim().isEmpty
                  ? context.l10n.text('This field is required')
                  : null
            : null,
      );

  Widget _numberField(
    String key,
    String label, {
    bool positive = false,
    bool allowZero = false,
  }) => TextFormField(
    controller: _fields[key],
    keyboardType: const TextInputType.numberWithOptions(
      decimal: true,
      signed: true,
    ),
    inputFormatters: [
      FilteringTextInputFormatter.allow(RegExp(r'^-?\d*\.?\d{0,2}')),
    ],
    decoration: InputDecoration(labelText: context.l10n.text(label)),
    validator: (value) {
      final parsed = double.tryParse((value ?? '').trim());
      if (parsed == null ||
          !parsed.isFinite ||
          positive && parsed <= 0 ||
          allowZero && parsed < 0) {
        return context.l10n.text('Enter a valid number');
      }
      return null;
    },
  );

  Future<void> _pickPayoutDate() async {
    final value = await showDatePicker(
      context: context,
      initialDate: _payoutDate ?? DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (value != null) setState(() => _payoutDate = value);
  }

  String _dailyResetDescription(BuildContext context) {
    final policy = widget.policy;
    if (policy == null) {
      return context.l10n.text(
        'Reset follows the configured portfolio trading day.',
      );
    }
    final reset = const TradingDayService().nextResetAt(DateTime.now(), policy);
    return context.l10n.text('Next configured reset: {time}', {
      'time': DateFormat.yMd().add_Hm().format(reset.toLocal()),
    });
  }

  void _save() {
    if (!_formKey.currentState!.validate()) return;
    final source = widget.account;
    final capital = _value('capital');
    final daily = _value('daily');
    final maximum = _value('maximum');
    final dailyAmount = _dailyType == LossLimitType.percentage
        ? capital * daily / 100
        : daily;
    final maximumAmount = _maximumType == LossLimitType.percentage
        ? capital * maximum / 100
        : maximum;
    final status = _enabled
        ? source.status == AccountStatus.paused
              ? AccountStatus.available
              : source.status
        : AccountStatus.paused;
    final result = source.copyWith(
      name: _fields['name']!.text.trim(),
      identifier: _fields['number']!.text.trim(),
      propFirmName: _fields['firm']!.text.trim(),
      currency: _fields['currency']!.text.trim().toUpperCase(),
      accountType: _type,
      accountSize: capital,
      startingBalance: capital,
      currentBalance: _value('balance'),
      manuallyEnteredEquity: _value('equity'),
      dailyLossLimit: daily,
      maximumLossLimit: maximum,
      dailyLossType: _dailyType,
      maximumLossType: _maximumType,
      dailyTradeLimitEnabled: _dailyTradeLimitEnabled,
      maxTradesPerDay:
          int.tryParse(_fields['dailyTradeLimit']!.text.trim()) ?? 3,
      phase: _type == PropFirmAccountType.funded ? 'Funded' : 'Evaluation',
      fundedStatus: _type == PropFirmAccountType.funded
          ? 'Funded'
          : 'Evaluation',
      minimumPayout: _type == PropFirmAccountType.funded ? _value('payout') : 0,
      profitTarget: _type == PropFirmAccountType.evaluation
          ? _value('target')
          : 0,
      nextPayoutDate: _type == PropFirmAccountType.funded ? _payoutDate : null,
      clearNextPayoutDate: _type == PropFirmAccountType.evaluation,
      status: status,
      isAvailableToday: _enabled,
      notes: _fields['notes']!.text.trim(),
      situation: source.situation.copyWith(
        maximumLossAmount: maximumAmount,
        dailyLossAmount: dailyAmount,
        dailyStartingBalance:
            source.situation.dailyStartingBalance ?? _value('balance'),
        dailyStartingEquity:
            source.situation.dailyStartingEquity ?? _value('equity'),
        updatedAt: DateTime.now(),
      ),
    );
    try {
      const AccountValidationService().validate(result);
      Navigator.pop(context, result);
    } on Object catch (error) {
      _showError(context, error);
    }
  }

  double _value(String key) => double.tryParse(_fields[key]!.text.trim()) ?? 0;
}

class _EditorSection extends StatelessWidget {
  const _EditorSection({required this.title, required this.children});
  final String title;
  final List<Widget> children;
  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            context.l10n.text(title),
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) => constraints.maxWidth < 680
                ? Column(
                    children: [
                      for (final child in children)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: child,
                        ),
                    ],
                  )
                : Wrap(
                    spacing: 12,
                    runSpacing: 12,
                    children: [
                      for (final child in children)
                        SizedBox(
                          width: (constraints.maxWidth - 12) / 2,
                          child: child,
                        ),
                    ],
                  ),
          ),
        ],
      ),
    ),
  );
}

class _LimitField extends StatelessWidget {
  const _LimitField({
    required this.controller,
    required this.label,
    required this.value,
    required this.onChanged,
  });
  final TextEditingController controller;
  final String label;
  final LossLimitType value;
  final ValueChanged<LossLimitType> onChanged;
  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Expanded(
        child: TextFormField(
          controller: controller,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(labelText: context.l10n.text(label)),
          validator: (text) {
            final number = double.tryParse((text ?? '').trim());
            if (number == null ||
                !number.isFinite ||
                number <= 0 ||
                value == LossLimitType.percentage && number > 100) {
              return context.l10n.text('Enter a valid number');
            }
            return null;
          },
        ),
      ),
      const SizedBox(width: 8),
      SizedBox(
        width: 145,
        child: DropdownButtonFormField<LossLimitType>(
          initialValue: value,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: context.l10n.text('Limit type'),
          ),
          items: [
            DropdownMenuItem(
              value: LossLimitType.fixedAmount,
              child: Text(context.l10n.text('Fixed amount')),
            ),
            DropdownMenuItem(
              value: LossLimitType.percentage,
              child: Text(context.l10n.text('Percentage')),
            ),
          ],
          onChanged: (next) {
            if (next != null) onChanged(next);
          },
        ),
      ),
    ],
  );
}

Future<void> showStartTradingDialog(
  BuildContext context, {
  required PropFirmPlannerController controller,
  required PropFirmPortfolio portfolio,
  String? preferredAccountId,
}) async {
  final plan = controller.simplifiedStartPlan;
  final scores = controller.simplifiedHealthScores;
  final eligible = portfolio.accounts
      .where((account) => plan?.evaluations[account.id]?.isEligible == true)
      .toList(growable: false);
  if (eligible.isEmpty) {
    final stopReason =
        controller.dashboardSnapshot?.session.stopReason ??
        portfolio.stopReason;
    _showError(
      context,
      StateError(
        stopReason == null
            ? 'No account is eligible to start this session.'
            : sessionStopReasonDescription(stopReason),
      ),
    );
    return;
  }
  var selected =
      eligible
          .where((account) => account.id == preferredAccountId)
          .firstOrNull ??
      eligible.first;
  final chosen = await showDialog<PropFirmAccount>(
    context: context,
    builder: (dialogContext) => StatefulBuilder(
      builder: (context, setState) => AlertDialog(
        title: Text(context.l10n.text('Start Trading')),
        content: SizedBox(
          width: 520,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  context.l10n.text(
                    'Choose the first eligible account or accept the recommendation.',
                  ),
                ),
                const SizedBox(height: 12),
                RadioGroup<String>(
                  groupValue: selected.id,
                  onChanged: (value) {
                    final account = eligible
                        .where((item) => item.id == value)
                        .firstOrNull;
                    if (account != null) setState(() => selected = account);
                  },
                  child: Column(
                    children: [
                      for (final account in eligible)
                        RadioListTile<String>(
                          value: account.id,
                          title: Text(account.name),
                          subtitle: Text(
                            '${context.l10n.text('Health score')}: ${scores[account.id]!.score.toStringAsFixed(0)} · ${_money(scores[account.id]!.lossLimits.dailyLossRemaining, account.currency)} ${context.l10n.text('daily remaining')}',
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            key: const Key('confirm-start-trading'),
            onPressed: () => Navigator.pop(dialogContext, selected),
            child: Text(
              context.l10n.text('Start {account}', {'account': selected.name}),
            ),
          ),
        ],
      ),
    ),
  );
  if (chosen != null && context.mounted) {
    await _run(context, () => controller.startSessionWithAccount(chosen.id));
  }
}

Future<SimplifiedTradeResultPreview?> showRecordTradeResultSheet(
  BuildContext context, {
  required PropFirmPlannerController controller,
  required PropFirmAccount account,
}) => showModalBottomSheet<SimplifiedTradeResultPreview>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (_) =>
      _RecordTradeResultSheet(controller: controller, account: account),
);

class _RecordTradeResultSheet extends StatefulWidget {
  const _RecordTradeResultSheet({
    required this.controller,
    required this.account,
  });
  final PropFirmPlannerController controller;
  final PropFirmAccount account;
  @override
  State<_RecordTradeResultSheet> createState() =>
      _RecordTradeResultSheetState();
}

class _RecordTradeResultSheetState extends State<_RecordTradeResultSheet> {
  final _amount = TextEditingController();
  final _notes = TextEditingController();
  final _reference = TextEditingController();
  TradeResultType _type = TradeResultType.fullTp;
  SimplifiedTradeResultPreview? _preview;
  var _saving = false;

  @override
  void dispose() {
    _amount.dispose();
    _notes.dispose();
    _reference.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SafeArea(
    child: Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        0,
        20,
        MediaQuery.viewInsetsOf(context).bottom + 20,
      ),
      child: SingleChildScrollView(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 680),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                context.l10n.text('Record Trade Result'),
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(widget.account.name),
              const SizedBox(height: 16),
              SegmentedButton<TradeResultType>(
                segments: [
                  ButtonSegment(
                    value: TradeResultType.fullTp,
                    label: Text(context.l10n.text('Take Profit')),
                    icon: const Icon(Icons.trending_up),
                  ),
                  ButtonSegment(
                    value: TradeResultType.fullSl,
                    label: Text(context.l10n.text('Stop Loss')),
                    icon: const Icon(Icons.trending_down),
                  ),
                  ButtonSegment(
                    value: TradeResultType.breakeven,
                    label: Text(context.l10n.text('Break Even')),
                    icon: const Icon(Icons.horizontal_rule),
                  ),
                ],
                selected: {_type},
                onSelectionChanged: _preview == null
                    ? (value) => setState(() => _type = value.first)
                    : null,
              ),
              const SizedBox(height: 14),
              if (_preview == null) ...[
                TextField(
                  key: const Key('result-amount'),
                  controller: _amount,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                    signed: true,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.allow(
                      RegExp(r'^-?\d*\.?\d{0,2}'),
                    ),
                  ],
                  decoration: InputDecoration(
                    labelText: context.l10n.text(
                      _type == TradeResultType.fullSl
                          ? 'Loss amount (enter a positive number)'
                          : 'Result amount',
                    ),
                    prefixText: '${widget.account.currency} ',
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _reference,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Trade reference (optional)'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: _notes,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Notes (optional)'),
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  key: const Key('review-result'),
                  onPressed: _review,
                  child: Text(context.l10n.text('Review Result')),
                ),
              ] else ...[
                _ResultPreviewCard(preview: _preview!),
                const SizedBox(height: 12),
                Row(
                  children: [
                    TextButton(
                      onPressed: _saving
                          ? null
                          : () => setState(() => _preview = null),
                      child: Text(context.l10n.text('Back')),
                    ),
                    const Spacer(),
                    FilledButton.icon(
                      key: const Key('confirm-result'),
                      onPressed: _saving ? null : _confirmResult,
                      icon: _saving
                          ? const SizedBox.square(
                              dimension: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.check),
                      label: Text(context.l10n.text('Confirm Result')),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    ),
  );

  void _review() {
    final amount = double.tryParse(_amount.text.trim());
    if (amount == null || !amount.isFinite) {
      _showError(
        context,
        const FormatException('Enter a valid result amount.'),
      );
      return;
    }
    try {
      setState(() {
        _preview = widget.controller.previewSimplifiedResult(
          type: _type,
          enteredAmount: amount,
          notes: _notes.text,
          tradeReference: _reference.text,
        );
      });
    } on Object catch (error) {
      _showError(context, error);
    }
  }

  Future<void> _confirmResult() async {
    setState(() => _saving = true);
    try {
      await widget.controller.recordSimplifiedResult(_preview!);
      if (mounted) Navigator.pop(context, _preview);
    } on Object catch (error) {
      if (mounted) _showError(context, error);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _ResultPreviewCard extends StatelessWidget {
  const _ResultPreviewCard({required this.preview});
  final SimplifiedTradeResultPreview preview;
  @override
  Widget build(BuildContext context) => Card(
    color: Theme.of(context).colorScheme.surfaceContainerHigh,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        children: [
          _ValueRow('Account', preview.account.name),
          _ValueRow(
            'Result type',
            context.l10n.text(_resultLabel(preview.type)),
          ),
          _ValueRow(
            'Entered amount',
            _money(preview.enteredAmount, preview.account.currency),
          ),
          _ValueRow(
            'Stored result',
            _money(preview.normalizedAmount, preview.account.currency),
          ),
          _ValueRow(
            'Balance before',
            _money(preview.account.currentBalance, preview.account.currency),
          ),
          _ValueRow(
            'Expected balance after',
            _money(preview.balanceAfter, preview.account.currency),
          ),
          _ValueRow(
            'Equity before',
            _money(preview.account.referenceEquity, preview.account.currency),
          ),
          _ValueRow(
            'Expected equity after',
            _money(preview.equityAfter, preview.account.currency),
          ),
          _ValueRow(
            'Estimated health after',
            preview.healthAfter.toStringAsFixed(0),
          ),
        ],
      ),
    ),
  );
}

Future<void> showNextRecommendationDialog(
  BuildContext context, {
  required PropFirmPlannerController controller,
  required SimplifiedTradeResultPreview previous,
}) async {
  final portfolio = controller.portfolio!;
  final recommended = portfolio.accounts
      .where(
        (account) => account.id == controller.simplifiedRecommendedAccountId,
      )
      .firstOrNull;
  await showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(context.l10n.text('Result saved')),
      content: SizedBox(
        width: 520,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              '${previous.account.name}: ${context.l10n.text(_resultLabel(previous.type))} · ${_money(previous.normalizedAmount, previous.account.currency)}',
            ),
            Text(
              '${context.l10n.text('Updated health score')}: ${previous.healthAfter.toStringAsFixed(0)}',
            ),
            const Divider(height: 28),
            Text(
              context.l10n.text('Recommended next account'),
              style: Theme.of(context).textTheme.labelLarge,
            ),
            Text(
              recommended?.name ?? context.l10n.text('No eligible account'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 6),
            Text(
              context.l10n.text(
                'The recommendation uses health score, daily capacity, maximum-loss capacity, recent use, and stable account order.',
              ),
            ),
          ],
        ),
      ),
      actions: [
        if (controller.canStartSimplifiedSession)
          TextButton(
            onPressed: () async {
              Navigator.pop(dialogContext);
              await showStartTradingDialog(
                context,
                controller: controller,
                portfolio: portfolio,
              );
            },
            child: Text(context.l10n.text('Choose Another Account')),
          ),
        if (recommended != null)
          FilledButton(
            onPressed: () async {
              Navigator.pop(dialogContext);
              await _run(
                context,
                () => controller.startSessionWithAccount(recommended.id),
              );
            },
            child: Text(
              context.l10n.text('Start {account}', {
                'account': recommended.name,
              }),
            ),
          )
        else
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(context.l10n.text('Done')),
          ),
      ],
    ),
  );
}

Future<void> showTradingSessionSummary(
  BuildContext context, {
  required PropFirmPlannerController controller,
  required List<RecordedTradeResult> results,
}) async {
  final portfolio = controller.portfolio!;
  final accountsById = {
    for (final account in portfolio.accounts) account.id: account,
  };
  final totalsByCurrency = <String, double>{};
  final returnsByAccount = <String, double>{};
  for (final result in results) {
    final account = accountsById[result.accountId];
    if (account == null) continue;
    totalsByCurrency.update(
      account.currency,
      (value) => value + result.amount,
      ifAbsent: () => result.amount,
    );
    returnsByAccount.update(
      result.accountId,
      (value) => value + result.amount / account.accountSize,
      ifAbsent: () => result.amount / account.accountSize,
    );
  }
  final tp = results
      .where((result) => result.type == TradeResultType.fullTp)
      .length;
  final sl = results
      .where((result) => result.type == TradeResultType.fullSl)
      .length;
  final be = results
      .where((result) => result.type == TradeResultType.breakeven)
      .length;
  final scores = controller.simplifiedHealthScores;
  final usableScores = scores.values.toList(growable: false);
  final combinedHealth = usableScores.isEmpty
      ? 0.0
      : usableScores.fold<double>(0, (sum, score) => sum + score.score) /
            usableScores.length;
  final bestAccountId = returnsByAccount.entries.isEmpty
      ? null
      : (returnsByAccount.entries.toList()
              ..sort((left, right) => right.value.compareTo(left.value)))
            .first
            .key;
  final highestRisk = usableScores.isEmpty
      ? null
      : (usableScores.toList()
              ..sort((left, right) => left.score.compareTo(right.score)))
            .first;
  final unavailable = usableScores
      .where((score) => !score.isEligible)
      .map((score) => accountsById[score.accountId]?.name)
      .whereType<String>()
      .toList(growable: false);
  final accountNames = results
      .map((result) => accountsById[result.accountId]?.name)
      .whereType<String>()
      .toSet()
      .join(', ');
  final sessionStart =
      controller.sequencePlan?.confirmedAt ??
      (results.isEmpty ? portfolio.updatedAt : results.first.resultTime);
  const tradingDays = TradingDayService();
  final displayStart = tradingDays.configuredTime(
    sessionStart,
    portfolio.riskPolicy,
  );
  final displayEnd = tradingDays.configuredTime(
    portfolio.updatedAt,
    portfolio.riskPolicy,
  );
  final recommended = accountsById[controller.simplifiedRecommendedAccountId];
  await showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.l10n.text('Trading Session Summary'),
              style: Theme.of(
                context,
              ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 20,
              runSpacing: 12,
              children: [
                _Metric('Trades recorded', '${results.length}'),
                _Metric('Take Profit', '$tp'),
                _Metric('Stop Loss', '$sl'),
                _Metric('Break Even', '$be'),
                for (final entry in totalsByCurrency.entries)
                  _Metric(
                    'Total P/L',
                    '${entry.key} ${entry.value.toStringAsFixed(2)}',
                  ),
                _Metric(
                  'Final combined health score',
                  combinedHealth.toStringAsFixed(0),
                ),
              ],
            ),
            const Divider(height: 28),
            _ValueRow(
              'Session start',
              DateFormat.yMd().add_Hm().format(displayStart),
            ),
            _ValueRow(
              'Session end',
              DateFormat.yMd().add_Hm().format(displayEnd),
            ),
            _ValueRow(
              'Accounts used',
              accountNames.isEmpty ? context.l10n.text('None') : accountNames,
            ),
            _ValueRow(
              'Best-performing account',
              bestAccountId == null
                  ? context.l10n.text('None')
                  : accountsById[bestAccountId]?.name ??
                        context.l10n.text('None'),
            ),
            _ValueRow(
              'Highest-risk account',
              highestRisk == null
                  ? context.l10n.text('None')
                  : accountsById[highestRisk.accountId]?.name ??
                        context.l10n.text('None'),
            ),
            _ValueRow(
              'Unavailable for this trading day',
              unavailable.isEmpty
                  ? context.l10n.text('None')
                  : unavailable.join(', '),
            ),
            _ValueRow(
              'Recommended next action',
              recommended == null
                  ? context.l10n.text(
                      'Review account health before the next session.',
                    )
                  : context.l10n.text('Start {account}', {
                      'account': recommended.name,
                    }),
            ),
            const SizedBox(height: 14),
            Text(
              context.l10n.text(
                'Review warning or critical accounts before the next session.',
              ),
            ),
            const SizedBox(height: 14),
            FilledButton(
              onPressed: () => Navigator.pop(context),
              child: Text(context.l10n.text('Done')),
            ),
          ],
        ),
      ),
    ),
  );
}

class SimplifiedTutorialPage extends StatelessWidget {
  const SimplifiedTutorialPage({super.key});

  @override
  Widget build(BuildContext context) {
    final sections = <(String, IconData, List<String>)>[
      (
        'What is Multiple Account Strategy?',
        Icons.account_tree_outlined,
        [
          'It is a risk-management workspace for traders managing more than one funded or evaluation account.',
          'It rotates between eligible accounts using loss capacity, drawdown, payout condition, evaluation progress, and recent use.',
          'Its purpose is to distribute risk and reduce emotional overuse of one account—not to increase trading frequency.',
        ],
      ),
      (
        'Why use it?',
        Icons.shield_outlined,
        [
          'It helps prevent repeated trading after losses, forgotten daily limits, unnecessary payout-account risk, and decisions based only on emotion.',
        ],
      ),
      (
        'Important warning',
        Icons.warning_amber,
        [
          'This feature does not guarantee profit, prevent every breach, or replace the prop firm’s official dashboard and rules.',
          'Verify whether your firm uses balance, equity, highest balance, end-of-day equity, or trailing drawdown. You remain responsible for accurate inputs.',
        ],
      ),
      (
        'Set up your accounts',
        Icons.add_card_outlined,
        [
          'Add the account name, number, capital, balance, equity, maximum loss, daily loss, currency, and account type.',
          'Funded accounts use minimum payout and next payout date. Evaluation accounts use a profit target.',
        ],
      ),
      (
        'Start a trading session',
        Icons.play_circle_outline,
        [
          'Tap Start Trading, review the recommended eligible account, confirm it, and then place the trade on your external platform.',
        ],
      ),
      (
        'Record a trade result',
        Icons.fact_check_outlined,
        [
          'Tap Record Trade Result, choose Take Profit, Stop Loss, or Break Even, enter the amount, review projected values, and confirm.',
          'Stop Loss input is entered as a positive number and stored as a negative result.',
        ],
      ),
      (
        'Continue with the next account',
        Icons.swap_horiz,
        [
          'After each result, all accounts are recalculated. Unavailable accounts are excluded and the healthiest suitable account is recommended.',
        ],
      ),
      (
        'End the session',
        Icons.stop_circle_outlined,
        [
          'Tap End Trading Session, review the session summary, and check warning or critical accounts before trading again.',
        ],
      ),
      (
        'Practical example',
        Icons.lightbulb_outline,
        [
          'Account A scores 58 (Warning), Account B scores 91 (Healthy), and Account C scores 79 (Moderate). Account B is recommended first.',
          'After a Stop Loss on Account B, the system recalculates and may recommend Account C while Account B cools down.',
        ],
      ),
      (
        'Common mistakes',
        Icons.rule_outlined,
        [
          'Common mistakes include incorrect balances, ignored floating equity loss, wrong reset rules, positive loss entries, repeated use after losses, and treating health score as a profit guarantee.',
        ],
      ),
    ];
    final faqs = <({String question, String answer, IconData icon, _FaqVisual visual})>[
      (
        question: 'What is the health score?',
        answer:
            'A 0–100 summary of loss capacity, equity drawdown, target or payout progress, availability, and data quality.',
        icon: Icons.donut_large,
        visual: _FaqVisual.score,
      ),
      (
        question: 'How does a profitable recovery improve my score?',
        answer:
            'Positive results improve balance, equity and remaining maximum-loss capacity. The score rises immediately when those financial inputs improve.',
        icon: Icons.trending_up,
        visual: _FaqVisual.recovery,
      ),
      (
        question: 'Why does a cooling account still show a health score?',
        answer:
            'Health and eligibility are different. Cooling blocks a new trade temporarily, but it does not erase the account’s current financial strength.',
        icon: Icons.timer_outlined,
        visual: _FaqVisual.cooldown,
      ),
      (
        question: 'When can I trade a cooling account again?',
        answer:
            'The account becomes eligible when its visible countdown reaches zero, provided no other daily, maximum-loss, payout, or data restriction applies.',
        icon: Icons.lock_clock_outlined,
        visual: _FaqVisual.cooldown,
      ),
      (
        question: 'Does a high health score guarantee profit?',
        answer:
            'No. It indicates available risk capacity and fewer current warnings, not future performance.',
        icon: Icons.warning_amber,
        visual: _FaqVisual.none,
      ),
      (
        question: 'Can I choose an account manually?',
        answer:
            'Yes. You may select another eligible account. Breached or unavailable accounts cannot be started.',
        icon: Icons.touch_app_outlined,
        visual: _FaqVisual.none,
      ),
      (
        question: 'What happens after a Stop Loss?',
        answer:
            'Balance, equity, daily result, loss capacity, and health are recalculated before another account is recommended.',
        icon: Icons.trending_down,
        visual: _FaqVisual.resultFlow,
      ),
      (
        question: 'What happens after Take Profit?',
        answer:
            'Balance and target or payout progress update, then another healthy account is normally recommended to distribute exposure.',
        icon: Icons.trending_up,
        visual: _FaqVisual.resultFlow,
      ),
      (
        question: 'What is Break Even?',
        answer:
            'A near-zero result. You may enter zero or a small positive or negative commission or slippage amount.',
        icon: Icons.horizontal_rule,
        visual: _FaqVisual.none,
      ),
      (
        question: 'Why is my account unavailable?',
        answer:
            'It may be paused, breached, daily-locked, archived, incomplete, cooling down, evaluation-complete, or protected for payout.',
        icon: Icons.block_outlined,
        visual: _FaqVisual.states,
      ),
      (
        question: 'Does the system connect directly to MT5?',
        answer:
            'No. Results are entered manually. The domain layer can accept a future data source without rewriting this workflow.',
        icon: Icons.link_off,
        visual: _FaqVisual.none,
      ),
      (
        question: 'When does daily loss reset?',
        answer:
            'At the configured prop-firm timezone and reset time, not automatically at the phone’s local midnight.',
        icon: Icons.schedule_outlined,
        visual: _FaqVisual.none,
      ),
      (
        question: 'Can I use different currencies?',
        answer:
            'Yes. Overview totals are grouped by currency and never silently added as though currencies were equal.',
        icon: Icons.currency_exchange,
        visual: _FaqVisual.none,
      ),
    ];
    return ListView(
      key: const Key('simplified-tutorial'),
      padding: const EdgeInsets.all(18),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1180),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const _TutorialHero(),
                const SizedBox(height: 18),
                const _HealthScoreVisual(),
                const SizedBox(height: 18),
                const _RecoveryJourneyVisual(),
                const SizedBox(height: 18),
                const _TradingWorkflowVisual(),
                const SizedBox(height: 26),
                const _SectionHeading(
                  icon: Icons.menu_book_outlined,
                  title: 'Guided learning',
                  subtitle:
                      'Open each topic for practical setup and risk-management guidance.',
                ),
                const SizedBox(height: 10),
                for (var index = 0; index < sections.length; index++)
                  _ProfessionalTutorialTopic(
                    index: index + 1,
                    title: sections[index].$1,
                    icon: sections[index].$2,
                    paragraphs: sections[index].$3,
                    initiallyExpanded: index == 0,
                  ),
                const SizedBox(height: 26),
                const _SectionHeading(
                  icon: Icons.quiz_outlined,
                  title: 'Frequently Asked Questions',
                  subtitle:
                      'Visual answers explain the score, cooldowns, recovery and account eligibility.',
                ),
                const SizedBox(height: 10),
                Column(
                  key: const Key('professional-faq-list'),
                  children: [
                    for (var index = 0; index < faqs.length; index++)
                      _ProfessionalFaqCard(
                        question: faqs[index].question,
                        answer: faqs[index].answer,
                        icon: faqs[index].icon,
                        visual: faqs[index].visual,
                        initiallyExpanded: index == 0,
                      ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

enum _FaqVisual { none, score, recovery, cooldown, resultFlow, states }

class _TutorialHero extends StatelessWidget {
  const _TutorialHero();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: [colors.primaryContainer, colors.surfaceContainerHigh],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
        ),
        child: LayoutBuilder(
          builder: (context, constraints) {
            final compact = constraints.maxWidth < 640;
            final illustration = Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: colors.primary.withValues(alpha: .12),
                border: Border.all(
                  color: colors.primary.withValues(alpha: .35),
                  width: 2,
                ),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  Icon(Icons.shield_outlined, size: 82, color: colors.primary),
                  Positioned(
                    right: 21,
                    bottom: 23,
                    child: CircleAvatar(
                      backgroundColor: colors.tertiaryContainer,
                      child: Icon(
                        Icons.insights_outlined,
                        color: colors.onTertiaryContainer,
                      ),
                    ),
                  ),
                ],
              ),
            );
            final copy = Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  context.l10n.text('Tutorial, Guidelines & FAQ'),
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  context.l10n.text('Trade with a system, not emotion'),
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: colors.primary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  context.l10n.text(
                    'Visual guidance for health scoring, recovery, cooldowns, and safer account rotation.',
                  ),
                ),
                const SizedBox(height: 16),
                const Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _HeroTag(Icons.donut_large, '0–100 score'),
                    _HeroTag(Icons.timer_outlined, 'Live cooldown'),
                    _HeroTag(Icons.account_tree_outlined, 'Safer rotation'),
                  ],
                ),
              ],
            );
            if (compact) {
              return Column(
                children: [
                  illustration,
                  const SizedBox(height: 18),
                  Align(alignment: Alignment.centerLeft, child: copy),
                ],
              );
            }
            return Row(
              children: [
                Expanded(child: copy),
                const SizedBox(width: 28),
                illustration,
              ],
            );
          },
        ),
      ),
    );
  }
}

class _HeroTag extends StatelessWidget {
  const _HeroTag(this.icon, this.label);
  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Chip(
    avatar: Icon(icon, size: 17),
    label: Text(context.l10n.text(label)),
    side: BorderSide.none,
  );
}

class _HealthScoreVisual extends StatelessWidget {
  const _HealthScoreVisual({this.compact = false});

  final bool compact;

  static const factors = <(String, double, Color)>[
    ('Maximum-loss room', 35, Color(0xFF00897B)),
    ('Daily-loss room', 35, Color(0xFF3973C4)),
    ('Equity and drawdown', 15, Color(0xFFE09A2D)),
    ('Target or payout', 10, Color(0xFF8E63CE)),
    ('Data and availability', 5, Color(0xFF4F9D69)),
  ];

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: EdgeInsets.all(compact ? 16 : 22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (!compact)
            const _SectionHeading(
              icon: Icons.donut_large,
              title: 'Understanding the 0–100 Health Score',
              subtitle:
                  'Five weighted components form one transparent score. The chart shows the maximum contribution from each component.',
            ),
          if (!compact) const SizedBox(height: 20),
          LayoutBuilder(
            builder: (context, constraints) {
              final narrow = constraints.maxWidth < 700;
              final chart = SizedBox(
                key: const Key('health-score-donut'),
                width: compact ? 190 : 240,
                height: compact ? 190 : 240,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    PieChart(
                      PieChartData(
                        startDegreeOffset: -90,
                        centerSpaceRadius: compact ? 46 : 62,
                        sectionsSpace: 3,
                        borderData: FlBorderData(show: false),
                        sections: [
                          for (final factor in factors)
                            PieChartSectionData(
                              value: factor.$2,
                              color: factor.$3,
                              radius: compact ? 34 : 44,
                              title: factor.$2.toStringAsFixed(0),
                              titleStyle: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 13,
                              ),
                            ),
                        ],
                      ),
                      duration: const Duration(milliseconds: 350),
                    ),
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '100',
                          style: Theme.of(context).textTheme.headlineMedium
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        Text(
                          context.l10n.text('points'),
                          style: Theme.of(context).textTheme.labelSmall,
                        ),
                      ],
                    ),
                  ],
                ),
              );
              final legend = Column(
                children: [
                  for (final factor in factors)
                    _ScoreFactorLegend(
                      label: factor.$1,
                      points: factor.$2,
                      color: factor.$3,
                    ),
                ],
              );
              return narrow
                  ? Column(
                      children: [chart, const SizedBox(height: 12), legend],
                    )
                  : Row(
                      children: [
                        chart,
                        const SizedBox(width: 28),
                        Expanded(child: legend),
                      ],
                    );
            },
          ),
          const SizedBox(height: 18),
          const _ScoreBandsVisual(),
        ],
      ),
    ),
  );
}

class _ScoreFactorLegend extends StatelessWidget {
  const _ScoreFactorLegend({
    required this.label,
    required this.points,
    required this.color,
  });
  final String label;
  final double points;
  final Color color;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      children: [
        Container(
          width: 12,
          height: 32,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(6),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            context.l10n.text(label),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        Text(
          '${points.toStringAsFixed(0)} ${context.l10n.text('points')}',
          style: TextStyle(color: color, fontWeight: FontWeight.w900),
        ),
      ],
    ),
  );
}

class _ScoreBandsVisual extends StatelessWidget {
  const _ScoreBandsVisual();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final bands = <(String, String, Color, IconData)>[
      ('Critical', '1–39', colors.error, Icons.error_outline),
      ('Warning', '40–59', const Color(0xFFD88A16), Icons.warning_amber),
      ('Moderate', '60–79', const Color(0xFF3973C4), Icons.info_outline),
      (
        'Healthy',
        '80–100',
        const Color(0xFF00897B),
        Icons.check_circle_outline,
      ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          context.l10n.text('Score bands'),
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: SizedBox(
            height: 12,
            child: Row(
              children: [
                for (final band in bands)
                  Expanded(
                    flex: switch (band.$2) {
                      '1–39' => 39,
                      '40–59' => 20,
                      '60–79' => 20,
                      _ => 21,
                    },
                    child: ColoredBox(color: band.$3),
                  ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 10),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final band in bands)
              _BandBadge(
                label: band.$1,
                range: band.$2,
                color: band.$3,
                icon: band.$4,
              ),
            _BandBadge(
              label: 'Unavailable',
              range: '0',
              color: colors.outline,
              icon: Icons.block_outlined,
            ),
          ],
        ),
      ],
    );
  }
}

class _BandBadge extends StatelessWidget {
  const _BandBadge({
    required this.label,
    required this.range,
    required this.color,
    required this.icon,
  });
  final String label;
  final String range;
  final Color color;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .10),
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: color.withValues(alpha: .45)),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 17, color: color),
        const SizedBox(width: 6),
        Text(
          '${context.l10n.text(label)} · $range',
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );
}

class _RecoveryJourneyVisual extends StatelessWidget {
  const _RecoveryJourneyVisual({this.compact = false});
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final nodes = <(double, String, String, IconData, Color)>[
      (
        62,
        'Yesterday after loss',
        'Moderate',
        Icons.trending_down,
        const Color(0xFF3973C4),
      ),
      (
        73,
        'Today after +USD 208.98',
        'Cooling—trade blocked',
        Icons.timer_outlined,
        const Color(0xFFE09A2D),
      ),
      (
        78,
        'After cooldown',
        'Eligible again',
        Icons.lock_open_outlined,
        const Color(0xFF00897B),
      ),
    ];
    final journey = LayoutBuilder(
      builder: (context, constraints) {
        final narrow = constraints.maxWidth < 720;
        final children = <Widget>[];
        for (var index = 0; index < nodes.length; index++) {
          final node = nodes[index];
          final card = _RecoveryNode(
            score: node.$1,
            title: node.$2,
            status: node.$3,
            icon: node.$4,
            color: node.$5,
          );
          children.add(narrow ? card : Expanded(child: card));
          if (index < nodes.length - 1) {
            children.add(
              Padding(
                padding: const EdgeInsets.all(8),
                child: Icon(
                  narrow ? Icons.arrow_downward : Icons.arrow_forward,
                  color: colors.primary,
                ),
              ),
            );
          }
        }
        return narrow ? Column(children: children) : Row(children: children);
      },
    );
    final content = Column(
      key: const Key('recovery-journey'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (!compact)
          const _SectionHeading(
            icon: Icons.auto_graph,
            title: 'Recovery example',
            subtitle:
                'A positive result improves health immediately; cooldown controls trade eligibility separately.',
          ),
        if (!compact) const SizedBox(height: 18),
        journey,
        const SizedBox(height: 14),
        const _VisualCallout(
          icon: Icons.info_outline,
          text:
              'Example values depend on account limits and balances. Profit improves the score, but never guarantees the next trade.',
        ),
      ],
    );
    if (compact) return content;
    return Card(
      child: Padding(padding: const EdgeInsets.all(22), child: content),
    );
  }
}

class _RecoveryNode extends StatelessWidget {
  const _RecoveryNode({
    required this.score,
    required this.title,
    required this.status,
    required this.icon,
    required this.color,
  });
  final double score;
  final String title;
  final String status;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .08),
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: color.withValues(alpha: .35)),
    ),
    child: Column(
      children: [
        SizedBox.square(
          dimension: 72,
          child: Stack(
            alignment: Alignment.center,
            children: [
              CircularProgressIndicator(
                value: score / 100,
                strokeWidth: 8,
                color: color,
                backgroundColor: color.withValues(alpha: .15),
              ),
              Text(
                score.toStringAsFixed(0),
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 10),
        Text(
          context.l10n.text(title),
          textAlign: TextAlign.center,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 5),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 16, color: color),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                context.l10n.text(status),
                textAlign: TextAlign.center,
                style: TextStyle(color: color, fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      ],
    ),
  );
}

class _TradingWorkflowVisual extends StatelessWidget {
  const _TradingWorkflowVisual();

  @override
  Widget build(BuildContext context) {
    final steps = <(IconData, String, String)>[
      (Icons.add_card_outlined, '1', 'Configure once'),
      (Icons.play_circle_outline, '2', 'Start one account'),
      (Icons.fact_check_outlined, '3', 'Record TP, SL or BE'),
      (Icons.recommend_outlined, '4', 'Follow the next recommendation'),
    ];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const _SectionHeading(
              icon: Icons.route_outlined,
              title: 'One simple trading workflow',
              subtitle:
                  'Configure once, record each result, and let the system recalculate the safer next action.',
            ),
            const SizedBox(height: 18),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (var index = 0; index < steps.length; index++) ...[
                    _WorkflowStep(
                      icon: steps[index].$1,
                      number: steps[index].$2,
                      label: steps[index].$3,
                    ),
                    if (index < steps.length - 1)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 10),
                        child: Icon(
                          Icons.arrow_forward,
                          color: Theme.of(context).colorScheme.primary,
                        ),
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WorkflowStep extends StatelessWidget {
  const _WorkflowStep({
    required this.icon,
    required this.number,
    required this.label,
  });
  final IconData icon;
  final String number;
  final String label;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 190,
    child: Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHigh,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        children: [
          CircleAvatar(
            child: Text(
              number,
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
          const SizedBox(height: 9),
          Icon(icon, size: 28),
          const SizedBox(height: 7),
          Text(
            context.l10n.text(label),
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
        ],
      ),
    ),
  );
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({
    required this.icon,
    required this.title,
    required this.subtitle,
  });
  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        padding: const EdgeInsets.all(9),
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primaryContainer,
          borderRadius: BorderRadius.circular(11),
        ),
        child: Icon(icon, color: Theme.of(context).colorScheme.primary),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text(title),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 3),
            Text(context.l10n.text(subtitle)),
          ],
        ),
      ),
    ],
  );
}

class _ProfessionalTutorialTopic extends StatelessWidget {
  const _ProfessionalTutorialTopic({
    required this.index,
    required this.title,
    required this.icon,
    required this.paragraphs,
    required this.initiallyExpanded,
  });
  final int index;
  final String title;
  final IconData icon;
  final List<String> paragraphs;
  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 8),
    clipBehavior: Clip.antiAlias,
    child: ExpansionTile(
      initiallyExpanded: initiallyExpanded,
      leading: CircleAvatar(
        child: Text(
          '$index',
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      title: Row(
        children: [
          Icon(icon, size: 20),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              context.l10n.text(title),
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
      childrenPadding: const EdgeInsets.fromLTRB(20, 0, 20, 18),
      children: [
        for (final paragraph in paragraphs)
          Padding(
            padding: const EdgeInsets.only(top: 9),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.check_circle_outline,
                  size: 18,
                  color: Theme.of(context).colorScheme.primary,
                ),
                const SizedBox(width: 9),
                Expanded(child: Text(context.l10n.text(paragraph))),
              ],
            ),
          ),
      ],
    ),
  );
}

class _ProfessionalFaqCard extends StatelessWidget {
  const _ProfessionalFaqCard({
    required this.question,
    required this.answer,
    required this.icon,
    required this.visual,
    required this.initiallyExpanded,
  });
  final String question;
  final String answer;
  final IconData icon;
  final _FaqVisual visual;
  final bool initiallyExpanded;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      clipBehavior: Clip.antiAlias,
      child: ExpansionTile(
        maintainState: true,
        initiallyExpanded: initiallyExpanded,
        leading: CircleAvatar(
          backgroundColor: colors.secondaryContainer,
          child: Icon(icon, color: colors.onSecondaryContainer),
        ),
        title: Text(
          context.l10n.text(question),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        subtitle: Text(context.l10n.text('Tap to explore the visual answer')),
        childrenPadding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
        children: [
          const Divider(),
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              context.l10n.text(answer),
              style: Theme.of(context).textTheme.bodyLarge,
            ),
          ),
          if (visual != _FaqVisual.none) ...[
            const SizedBox(height: 16),
            _faqVisual(visual),
          ],
        ],
      ),
    );
  }

  Widget _faqVisual(_FaqVisual visual) => switch (visual) {
    _FaqVisual.score => const _HealthScoreVisual(compact: true),
    _FaqVisual.recovery => const _RecoveryJourneyVisual(compact: true),
    _FaqVisual.cooldown => const _CooldownConceptVisual(),
    _FaqVisual.resultFlow => const _ResultFlowVisual(),
    _FaqVisual.states => const _UnavailableStatesVisual(),
    _FaqVisual.none => const SizedBox.shrink(),
  };
}

class _VisualCallout extends StatelessWidget {
  const _VisualCallout({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: Theme.of(
        context,
      ).colorScheme.primaryContainer.withValues(alpha: .55),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Icon(icon, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: 10),
        Expanded(child: Text(context.l10n.text(text))),
      ],
    ),
  );
}

class _CooldownConceptVisual extends StatelessWidget {
  const _CooldownConceptVisual();

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('cooldown-faq-visual'),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHigh,
      borderRadius: BorderRadius.circular(14),
    ),
    child: const SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _ConceptPill(Icons.favorite_outline, 'Health score', '73 / 100'),
          _ConceptArrow(),
          _ConceptPill(
            Icons.lock_clock_outlined,
            'Trade eligibility',
            'Blocked',
          ),
          _ConceptArrow(),
          _ConceptPill(Icons.timer_outlined, 'Countdown', '30m 00s'),
          _ConceptArrow(),
          _ConceptPill(Icons.lock_open_outlined, 'At zero', 'Eligible'),
        ],
      ),
    ),
  );
}

class _ResultFlowVisual extends StatelessWidget {
  const _ResultFlowVisual();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHigh,
      borderRadius: BorderRadius.circular(14),
    ),
    child: const SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _ConceptPill(Icons.fact_check_outlined, 'Result', 'TP / SL / BE'),
          _ConceptArrow(),
          _ConceptPill(Icons.calculate_outlined, 'Recalculate', 'All accounts'),
          _ConceptArrow(),
          _ConceptPill(Icons.timer_outlined, 'Safeguard', 'Cooldown or lock'),
          _ConceptArrow(),
          _ConceptPill(
            Icons.recommend_outlined,
            'Next action',
            'Healthiest eligible',
          ),
        ],
      ),
    ),
  );
}

class _UnavailableStatesVisual extends StatelessWidget {
  const _UnavailableStatesVisual();

  @override
  Widget build(BuildContext context) {
    final states = <(IconData, String)>[
      (Icons.pause_circle_outline, 'Paused'),
      (Icons.timer_outlined, 'Cooling down'),
      (Icons.lock_outline, 'Daily Limit Reached'),
      (Icons.shield_outlined, 'Payout protected'),
      (Icons.warning_amber, 'Maximum Loss Risk'),
      (Icons.description_outlined, 'Incomplete account data'),
    ];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final state in states)
          Chip(
            avatar: Icon(state.$1, size: 17),
            label: Text(context.l10n.text(state.$2)),
          ),
      ],
    );
  }
}

class _ConceptPill extends StatelessWidget {
  const _ConceptPill(this.icon, this.label, this.value);
  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 150,
    child: Column(
      children: [
        Icon(icon, size: 28, color: Theme.of(context).colorScheme.primary),
        const SizedBox(height: 6),
        Text(context.l10n.text(label), textAlign: TextAlign.center),
        Text(
          context.l10n.text(value),
          textAlign: TextAlign.center,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ],
    ),
  );
}

class _ConceptArrow extends StatelessWidget {
  const _ConceptArrow();

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 5),
    child: Icon(
      Icons.arrow_forward,
      color: Theme.of(context).colorScheme.outline,
    ),
  );
}

Future<void> showDailyResetSettings(
  BuildContext context, {
  required PropFirmPlannerController controller,
  required PortfolioRiskPolicy policy,
}) async {
  const zones = [
    'UTC',
    'America/New_York',
    'Europe/London',
    'Asia/Singapore',
    'Australia/Sydney',
  ];
  var zone = zones.contains(policy.resetTimeZone)
      ? policy.resetTimeZone
      : 'UTC';
  var time = TimeOfDay(hour: policy.resetHourUtc, minute: policy.resetMinute);
  final saved = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => StatefulBuilder(
      builder: (context, setState) => AlertDialog(
        title: Text(context.l10n.text('Daily reset settings')),
        content: SizedBox(
          width: 440,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                initialValue: zone,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Prop-firm reset timezone'),
                ),
                items: [
                  for (final item in zones)
                    DropdownMenuItem(value: item, child: Text(item)),
                ],
                onChanged: (value) => setState(() => zone = value ?? zone),
              ),
              const SizedBox(height: 12),
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(context.l10n.text('Reset time')),
                subtitle: Text(time.format(context)),
                trailing: const Icon(Icons.schedule),
                onTap: () async {
                  final value = await showTimePicker(
                    context: context,
                    initialTime: time,
                  );
                  if (value != null) setState(() => time = value);
                },
              ),
              _Notice(
                icon: Icons.info_outline,
                text: context.l10n.text(
                  'Daily tracking follows this timezone, including daylight-saving changes where applicable.',
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(context.l10n.text('Save')),
          ),
        ],
      ),
    ),
  );
  if (saved == true && context.mounted) {
    await _run(
      context,
      () => controller.updateRiskPolicy(
        policy.copyWith(
          resetTimeZone: zone,
          resetHourUtc: time.hour,
          resetMinute: time.minute,
        ),
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          Icon(icon),
          const SizedBox(width: 10),
          Expanded(child: Text(text)),
        ],
      ),
    ),
  );
}

Widget _languageMenu(BuildContext context) => PopupMenuButton<String>(
  tooltip: context.l10n.text('Language'),
  icon: const Icon(Icons.language),
  onSelected: (value) => AppLocaleController.of(context).setLanguage(value),
  itemBuilder: (_) => [
    PopupMenuItem(value: 'en', child: Text(context.l10n.text('English'))),
    PopupMenuItem(
      value: 'zh',
      child: Text(context.l10n.text('Simplified Chinese')),
    ),
  ],
);

String _strategyStatus(
  PropFirmPlannerController controller,
  PropFirmPortfolio portfolio,
  Map<String, AccountHealthScore> scores,
) {
  if (portfolio.sessionStatus == PortfolioSessionStatus.completed) {
    return 'Trading Completed for Today';
  }
  if (portfolio.stopReason != null ||
      portfolio.sessionStatus == PortfolioSessionStatus.stopped) {
    return 'Daily Risk Limit Reached';
  }
  if (portfolio.sessionActive) return 'Waiting for Result';
  if (controller.hasOpenTradingSession) return 'Account Rotation Required';
  if (portfolio.activeAccounts.isNotEmpty &&
      portfolio.activeAccounts.every(
        (account) => scores[account.id]?.isEligible != true,
      )) {
    return 'All Accounts Paused';
  }
  return 'Ready to Trade';
}

String _bandLabel(StrategyHealthBand value) => switch (value) {
  StrategyHealthBand.healthy => 'Healthy',
  StrategyHealthBand.moderate => 'Moderate',
  StrategyHealthBand.warning => 'Warning',
  StrategyHealthBand.critical => 'Critical',
  StrategyHealthBand.unavailable => 'Unavailable',
};

String _unavailableLabel(String key) => switch (key) {
  'account.unavailable.incomplete' => 'Incomplete account data',
  'account.unavailable.archived' => 'Archived',
  'account.unavailable.paused' => 'Paused',
  'account.unavailable.maximumLoss' => 'Maximum Loss Risk',
  'account.unavailable.dailyLoss' => 'Daily Limit Reached',
  'account.unavailable.payoutProtected' => 'Payout protected',
  'account.unavailable.cooldown' => 'Cooling down',
  _ => 'Unavailable',
};

IconData _bandIcon(StrategyHealthBand value) => switch (value) {
  StrategyHealthBand.healthy => Icons.check_circle_outline,
  StrategyHealthBand.moderate => Icons.info_outline,
  StrategyHealthBand.warning => Icons.warning_amber,
  StrategyHealthBand.critical => Icons.error_outline,
  StrategyHealthBand.unavailable => Icons.block,
};

String _accountTypeLabel(PropFirmAccountType value) => switch (value) {
  PropFirmAccountType.funded => 'Funded Account',
  PropFirmAccountType.evaluation => 'Evaluation Account',
};

String _resultLabel(TradeResultType value) => switch (value) {
  TradeResultType.fullTp => 'Take Profit',
  TradeResultType.fullSl => 'Stop Loss',
  TradeResultType.breakeven => 'Break Even',
  _ => value.name,
};

IconData _resultIcon(TradeResultType value) => switch (value) {
  TradeResultType.fullTp => Icons.trending_up,
  TradeResultType.fullSl => Icons.trending_down,
  TradeResultType.breakeven => Icons.horizontal_rule,
  _ => Icons.receipt_long_outlined,
};

String _maskAccount(String value) {
  final text = value.trim();
  if (text.length <= 4) return text;
  return '•••• ${text.substring(text.length - 4)}';
}

String _money(double value, String currency) =>
    '$currency ${NumberFormat('#,##0.00', 'en').format(value)}';

TextEditingController _controller(double value) => TextEditingController(
  text: value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(2),
);

Future<void> _run(
  BuildContext context,
  Future<void> Function() operation,
) async {
  try {
    await operation();
  } on Object catch (error) {
    if (context.mounted) _showError(context, error);
  }
}

Future<bool> _confirm(
  BuildContext context, {
  required String title,
  Map<String, Object?>? titleValues,
  required String message,
  required String confirmLabel,
}) async =>
    await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text(title, titleValues ?? const {})),
        content: Text(context.l10n.text(message)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.l10n.text(confirmLabel)),
          ),
        ],
      ),
    ) ??
    false;

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(context.l10n.text(simplifiedErrorMessage(error))),
      backgroundColor: Theme.of(context).colorScheme.error,
    ),
  );
}

@visibleForTesting
String simplifiedErrorMessage(Object error) {
  if (error is StateError) return error.message.toString();
  if (error is FormatException) return error.message.toString();
  return '$error';
}
