import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/localization/app_locale_controller.dart';
import '../../../core/localization/app_localizations.dart';
import '../../../core/storage/local_app_storage.dart';
import '../data/prop_firm_portfolio_repository.dart';
import '../data/portfolio_repository_snapshot.dart';
import '../domain/prop_firm_models.dart';
import '../domain/scenario_simulation_service.dart';
import '../domain/session_stop_condition_service.dart';
import 'account_health_ui.dart';
import 'account_sequence_ui.dart';
import 'prop_firm_planner_controller.dart';
import 'prop_firm_planner_dialogs.dart';
import 'simplified_strategy_ui.dart';

class PropFirmPlannerScreen extends StatefulWidget {
  const PropFirmPlannerScreen({
    super.key,
    required this.storage,
    required this.ownerId,
    this.showBackButton = true,
    this.firebaseEnabled = false,
    this.now,
  });

  static const routeName = '/tools/funded-account-rotation-planner';

  final LocalAppStorage storage;
  final String ownerId;
  final bool showBackButton;
  final bool firebaseEnabled;
  final DateTime Function()? now;

  @override
  State<PropFirmPlannerScreen> createState() => _PropFirmPlannerScreenState();
}

class _PropFirmPlannerScreenState extends State<PropFirmPlannerScreen> {
  late final PropFirmPlannerController _controller;
  var _section = 0;

  @override
  void initState() {
    super.initState();
    _controller = PropFirmPlannerController(
      repository: widget.firebaseEnabled && widget.ownerId != 'guest'
          ? PropFirmPortfolioRepository.cloud(ownerId: widget.ownerId)
          : PropFirmPortfolioRepository(
              storage: widget.storage,
              ownerId: widget.ownerId,
            ),
      now: widget.now,
    );
    _controller.initialize();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, _) {
        if (_controller.busy) {
          return Scaffold(
            appBar: AppBar(
              leading: widget.showBackButton ? const BackButton() : null,
              title: Text(
                context.l10n.text('Funded Account Rotation & Payout Planner'),
              ),
            ),
            body: const Center(child: CircularProgressIndicator()),
          );
        }
        final portfolio = _controller.portfolio;
        if (portfolio == null) {
          return SimplifiedStrategyOnboarding(
            controller: _controller,
            showBackButton: widget.showBackButton,
          );
        }
        return SimplifiedStrategyWorkspace(
          controller: _controller,
          portfolio: portfolio,
          showBackButton: widget.showBackButton,
          cloudEnabled: widget.firebaseEnabled && widget.ownerId != 'guest',
        );
      },
    );
  }

  // Retained temporarily for source compatibility with the legacy private
  // widgets below; the public route now renders SimplifiedStrategyWorkspace.
  // ignore: unused_element
  Widget _workspace(PropFirmPortfolio portfolio) {
    final l10n = context.l10n;
    final pages = [
      _OverviewPage(portfolio: portfolio),
      _PortfolioPage(controller: _controller, portfolio: portfolio),
      _ControlRoom(controller: _controller, portfolio: portfolio),
      _PlanningPage(controller: _controller, portfolio: portfolio),
      _EducationPage(portfolio: portfolio),
      AccountHealthPage(controller: _controller, portfolio: portfolio),
      AccountSequencePlannerPage(controller: _controller, portfolio: portfolio),
    ];
    final destinations = [
      (Icons.route_outlined, 'Overview'),
      (Icons.account_tree_outlined, 'Portfolio'),
      (Icons.tune_outlined, 'Control room'),
      (Icons.calculate_outlined, 'Planners'),
      (Icons.school_outlined, 'Learn'),
      (Icons.monitor_heart_outlined, 'Health'),
      (Icons.format_list_numbered, 'Sequence'),
    ];

    return Scaffold(
      appBar: AppBar(
        leading: widget.showBackButton ? const BackButton() : null,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.text('Multi-Account Prop Firm Strategy'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
            Text(
              l10n.text(
                widget.firebaseEnabled && widget.ownerId != 'guest'
                    ? 'Cloud-synchronized planning workspace'
                    : 'Local-first planning workspace',
              ),
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            tooltip: l10n.text('Import and export'),
            onPressed: () => _showDataActions(portfolio),
            icon: const Icon(Icons.import_export_outlined),
          ),
          PopupMenuButton<String>(
            tooltip: l10n.text('Language'),
            icon: const Icon(Icons.language),
            onSelected: (value) =>
                AppLocaleController.of(context).setLanguage(value),
            itemBuilder: (_) => [
              PopupMenuItem(value: 'en', child: Text(l10n.text('English'))),
              PopupMenuItem(
                value: 'zh',
                child: Text(l10n.text('Simplified Chinese')),
              ),
            ],
          ),
        ],
      ),
      body: LayoutBuilder(
        builder: (context, constraints) {
          final desktop = constraints.maxWidth >= 760;
          final content = !desktop
              ? pages[_section]
              : Row(
                  children: [
                    NavigationRail(
                      extended: constraints.maxWidth >= 1180,
                      selectedIndex: _section,
                      onDestinationSelected: (value) =>
                          setState(() => _section = value),
                      labelType: constraints.maxWidth >= 1180
                          ? NavigationRailLabelType.none
                          : NavigationRailLabelType.all,
                      destinations: [
                        for (final item in destinations)
                          NavigationRailDestination(
                            icon: Icon(item.$1),
                            selectedIcon: Icon(item.$1, fill: 1),
                            label: Text(l10n.text(item.$2)),
                          ),
                      ],
                    ),
                    const VerticalDivider(width: 1),
                    Expanded(child: pages[_section]),
                  ],
                );
          return Column(
            children: [
              _SyncStatusBar(controller: _controller),
              Expanded(child: content),
            ],
          );
        },
      ),
      bottomNavigationBar: MediaQuery.sizeOf(context).width < 760
          ? NavigationBar(
              selectedIndex: _section,
              onDestinationSelected: (value) =>
                  setState(() => _section = value),
              destinations: [
                for (final item in destinations)
                  NavigationDestination(
                    icon: Icon(item.$1),
                    label: l10n.text(item.$2),
                  ),
              ],
            )
          : null,
    );
  }

  Future<void> _showDataActions(PropFirmPortfolio portfolio) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.data_object_outlined),
              title: Text(context.l10n.text('Copy portfolio JSON')),
              subtitle: Text(
                context.l10n.text('Portable backup without credentials'),
              ),
              onTap: () => Navigator.pop(context, 'json'),
            ),
            ListTile(
              leading: const Icon(Icons.table_view_outlined),
              title: Text(context.l10n.text('Copy trade history CSV')),
              onTap: () => Navigator.pop(context, 'csv'),
            ),
            ListTile(
              leading: const Icon(Icons.upload_file_outlined),
              title: Text(context.l10n.text('Import portfolio JSON')),
              onTap: () => Navigator.pop(context, 'import'),
            ),
            ListTile(
              leading: const Icon(Icons.restart_alt),
              title: Text(context.l10n.text("Reset today's session")),
              onTap: () => Navigator.pop(context, 'reset'),
            ),
            ListTile(
              leading: Icon(
                Icons.delete_forever_outlined,
                color: Theme.of(context).colorScheme.error,
              ),
              title: Text(context.l10n.text('Delete portfolio')),
              onTap: () => Navigator.pop(context, 'delete'),
            ),
          ],
        ),
      ),
    );
    if (!mounted || action == null) return;
    try {
      switch (action) {
        case 'json':
          await Clipboard.setData(
            ClipboardData(text: _controller.exportJson()),
          );
          _message('Portfolio JSON copied.');
        case 'csv':
          await Clipboard.setData(ClipboardData(text: _controller.exportCsv()));
          _message('Trade history CSV copied.');
        case 'import':
          await _importJson();
        case 'reset':
          if (await _confirm(
            'Reset today\'s session?',
            'Open plans will be closed and daily planner locks will be cleared. Recorded history remains.',
          )) {
            await _controller.resetToday();
            _message("Today's session was reset.");
          }
        case 'delete':
          if (await _confirm(
            'Delete this portfolio?',
            'This archives the Firestore portfolio and its accounts for every signed-in device. Export a backup first if needed.',
          )) {
            await _controller.deletePortfolio();
          }
      }
    } on Object catch (error) {
      _message('$error', error: true);
    }
  }

  Future<void> _importJson() async {
    final input = TextEditingController();
    final source = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Import portfolio JSON')),
        content: SizedBox(
          width: 640,
          child: TextField(
            controller: input,
            minLines: 8,
            maxLines: 16,
            decoration: InputDecoration(
              hintText: context.l10n.text('Paste a planner JSON backup here'),
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, input.text),
            child: Text(context.l10n.text('Validate and import')),
          ),
        ],
      ),
    );
    input.dispose();
    if (source == null || source.trim().isEmpty) return;
    await _controller.importJson(source);
    _message('Portfolio imported.');
  }

  Future<bool> _confirm(String title, String message) async =>
      await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(context.l10n.text(title)),
          content: Text(context.l10n.text(message)),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(context.l10n.text('Cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(context.l10n.text('Confirm')),
            ),
          ],
        ),
      ) ??
      false;

  void _message(String message, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(context.l10n.text(message)),
        backgroundColor: error ? Theme.of(context).colorScheme.error : null,
      ),
    );
  }
}

class _SyncStatusBar extends StatelessWidget {
  const _SyncStatusBar({required this.controller});

  final PropFirmPlannerController controller;

  @override
  Widget build(BuildContext context) {
    final state = controller.syncState;
    final lastUpdated = controller.lastSynchronizedAt;
    final label = switch (state) {
      PortfolioSyncState.synchronized => 'Saved and synchronized',
      PortfolioSyncState.saving => 'Saving',
      PortfolioSyncState.pending => 'Waiting for Firestore; not yet saved',
      PortfolioSyncState.offline => 'Offline',
      PortfolioSyncState.failed => 'Failed to save',
    };
    final icon = switch (state) {
      PortfolioSyncState.synchronized => Icons.cloud_done_outlined,
      PortfolioSyncState.saving => Icons.sync,
      PortfolioSyncState.pending => Icons.cloud_upload_outlined,
      PortfolioSyncState.offline => Icons.cloud_off_outlined,
      PortfolioSyncState.failed => Icons.error_outline,
    };
    final warning = {
      PortfolioSyncState.offline,
      PortfolioSyncState.failed,
    }.contains(state);
    return Material(
      color: warning
          ? Theme.of(context).colorScheme.errorContainer
          : Theme.of(context).colorScheme.surfaceContainerLow,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
        child: Row(
          children: [
            Icon(icon, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                lastUpdated == null
                    ? context.l10n.text(label)
                    : '${context.l10n.text(label)} · '
                          '${context.l10n.text('Last updated')}: '
                          '${_syncDateTime(lastUpdated)}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            if (controller.canRetry)
              TextButton(
                onPressed: () async {
                  try {
                    await controller.retryLastSave();
                  } on Object catch (error) {
                    if (context.mounted) _showError(context, error);
                  }
                },
                child: Text(context.l10n.text('Retry')),
              ),
          ],
        ),
      ),
    );
  }
}

class _Onboarding extends StatefulWidget {
  const _Onboarding({required this.controller, required this.showBackButton});

  final PropFirmPlannerController controller;
  final bool showBackButton;

  @override
  State<_Onboarding> createState() => _OnboardingState();
}

class _OnboardingState extends State<_Onboarding> {
  final _budget = TextEditingController(text: '3000');
  var _accounts = 3;
  var _acknowledged = false;
  var _saving = false;

  @override
  void dispose() {
    _budget.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final steps = [
      'Create accounts',
      'Set a virtual portfolio risk budget',
      'Allocate risk across accounts',
      'Choose a rotation policy',
      'Use the recommended account',
      'Record TP, SL, breakeven, or cancellation',
      'Apply a cooldown or lock',
      'Rotate only when permitted',
      'Protect accounts approaching payout',
      'Review discipline and payout readiness',
    ];
    return Scaffold(
      appBar: AppBar(
        leading: widget.showBackButton ? const BackButton() : null,
        title: Text(l10n.text('Funded Account Rotation & Payout Planner')),
        actions: [
          PopupMenuButton<String>(
            tooltip: l10n.text('Language'),
            icon: const Icon(Icons.language),
            onSelected: (value) =>
                AppLocaleController.of(context).setLanguage(value),
            itemBuilder: (_) => [
              PopupMenuItem(value: 'en', child: Text(l10n.text('English'))),
              PopupMenuItem(
                value: 'zh',
                child: Text(l10n.text('Simplified Chinese')),
              ),
            ],
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 10, 20, 40),
        children: [
          Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 1080),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _HeroCard(
                    icon: Icons.account_tree_outlined,
                    eyebrow: l10n.text('PUBLIC · LOCAL-FIRST · PLANNING ONLY'),
                    title: l10n.text(
                      'Treat several accounts as one risk system',
                    ),
                    description: l10n.text(
                      'Plan one recommended active account at a time, protect daily limits, isolate recovery, and prepare payouts without connecting to a broker.',
                    ),
                  ),
                  const SizedBox(height: 16),
                  _WarningCard(
                    text: l10n.text(
                      'Having more accounts does not create more risk capacity. Without portfolio-level limits, multiple accounts can multiply losses and emotional pressure.',
                    ),
                  ),
                  const SizedBox(height: 22),
                  Text(
                    l10n.text('How the planner works'),
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 12),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final columns = constraints.maxWidth >= 900
                          ? 5
                          : constraints.maxWidth >= 560
                          ? 2
                          : 1;
                      final width =
                          (constraints.maxWidth - (columns - 1) * 10) / columns;
                      return Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          for (var index = 0; index < steps.length; index++)
                            SizedBox(
                              width: width,
                              child: Card(
                                child: Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Row(
                                    children: [
                                      CircleAvatar(
                                        radius: 16,
                                        child: Text('${index + 1}'),
                                      ),
                                      const SizedBox(width: 10),
                                      Expanded(
                                        child: Text(l10n.text(steps[index])),
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: 22),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            l10n.text('Create your starter portfolio'),
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            l10n.text(
                              'Three active accounts are a simple starting example, not a universal recommendation. Everything remains editable.',
                            ),
                          ),
                          const SizedBox(height: 16),
                          TextField(
                            controller: _budget,
                            keyboardType: const TextInputType.numberWithOptions(
                              decimal: true,
                            ),
                            decoration: InputDecoration(
                              labelText: l10n.text(
                                'Virtual portfolio risk budget (USD)',
                              ),
                              helperText: l10n.text(
                                'A planning allocation only. Legal account balances and limits remain separate.',
                              ),
                            ),
                          ),
                          const SizedBox(height: 14),
                          SegmentedButton<int>(
                            segments: [
                              for (final value in const [1, 2, 3, 4])
                                ButtonSegment(
                                  value: value,
                                  label: Text(value == 4 ? '4+' : '$value'),
                                ),
                            ],
                            selected: {_accounts},
                            onSelectionChanged: (value) =>
                                setState(() => _accounts = value.first),
                          ),
                          const SizedBox(height: 16),
                          CheckboxListTile(
                            contentPadding: EdgeInsets.zero,
                            value: _acknowledged,
                            onChanged: (value) =>
                                setState(() => _acknowledged = value ?? false),
                            controlAffinity: ListTileControlAffinity.leading,
                            title: Text(
                              l10n.text(
                                'I understand this is an educational planning tool.',
                              ),
                            ),
                            subtitle: Text(
                              l10n.text(
                                'It is not financial advice, does not guarantee a payout, and uses hypothetical results. I will verify current firm rules independently.',
                              ),
                            ),
                          ),
                          const SizedBox(height: 12),
                          FilledButton.icon(
                            onPressed: !_acknowledged || _saving
                                ? null
                                : _create,
                            icon: _saving
                                ? const SizedBox.square(
                                    dimension: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.rocket_launch_outlined),
                            label: Text(
                              l10n.text('Create editable starter portfolio'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  _PlainNotice(
                    icon: Icons.shield_outlined,
                    text: l10n.text(
                      'No MT4, MT5, broker, trade execution, signal, password, or prop-firm login connection is used.',
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _create() async {
    final budget = double.tryParse(_budget.text.trim());
    if (budget == null || budget <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.l10n.text('Enter a valid risk budget.')),
        ),
      );
      return;
    }
    setState(() => _saving = true);
    try {
      await widget.controller.createStarterPortfolio(
        virtualRiskBudget: budget,
        accountCount: _accounts,
        accountNamePrefix: context.l10n.text('Account'),
        portfolioName: context.l10n.text('Funded Account Rotation Plan'),
      );
    } on Object catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }
}

class _OverviewPage extends StatelessWidget {
  const _OverviewPage({required this.portfolio});

  final PropFirmPortfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return _PageScroll(
      children: [
        _HeroCard(
          icon: Icons.route_outlined,
          eyebrow: l10n.text('STRUCTURED ROTATION'),
          title: l10n.text('One portfolio budget. Independent account rules.'),
          description: l10n.text(
            'The planner recommends one eligible account by default, applies cooldowns after results, and stops all recommendations when your global policy is reached.',
          ),
        ),
        const SizedBox(height: 16),
        _AccountFlow(accounts: portfolio.activeAccounts),
        const SizedBox(height: 18),
        _ResponsiveColumns(
          children: [
            _InfoCard(
              icon: Icons.check_circle_outline,
              title: l10n.text('Healthy rotation example'),
              lines: [
                'Account A reaches TP and cools down.',
                'Account B becomes active and later reaches SL.',
                'Account B is locked according to the strategy.',
                'Account C is considered only if global and emotional limits permit.',
                'The session stops when the portfolio limit is reached.',
              ],
            ),
            _InfoCard(
              icon: Icons.warning_amber_outlined,
              title: l10n.text('Destructive pattern to avoid'),
              lines: [
                'Account A reaches SL and is traded again immediately.',
                'The same emotional idea is copied to Accounts B and C.',
                'One decision damages three independent accounts.',
                'Buying another challenge does not resolve a discipline problem.',
              ],
              warning: true,
            ),
          ],
        ),
        const SizedBox(height: 18),
        _ResponsiveColumns(
          children: [
            _InfoCard(
              icon: Icons.account_balance_wallet_outlined,
              title: l10n.text('Account-level risk'),
              lines: [
                'Each firm account keeps its own legal balance.',
                'Daily and maximum drawdown formulas remain independent.',
                'Payout, consistency, and trading-day rules cannot be transferred.',
              ],
            ),
            _InfoCard(
              icon: Icons.hub_outlined,
              title: l10n.text('Portfolio-level planning'),
              lines: [
                'Your virtual budget caps combined planned behaviour.',
                'Global stops prevent switching accounts to bypass a loss limit.',
                'Recommendations explain exclusions and rotation priority.',
              ],
            ),
          ],
        ),
        const SizedBox(height: 18),
        _WarningCard(
          text: l10n.text(
            'Separate prop-firm limits cannot be combined, transferred, or treated as one legal balance. The virtual portfolio budget is only a discipline-planning concept.',
          ),
        ),
      ],
    );
  }
}

class _PortfolioPage extends StatelessWidget {
  const _PortfolioPage({required this.controller, required this.portfolio});

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final recommendation = controller.recommendation;
    final dashboard = controller.dashboardSnapshot!;
    return _PageScroll(
      children: [
        _SectionHeading(
          title: context.l10n.text('Portfolio dashboard'),
          subtitle: context.l10n.text(
            'Today’s risk, account health, discipline, and next action',
          ),
          trailing: FilledButton.tonalIcon(
            onPressed: () => _addAccount(context),
            icon: const Icon(Icons.add),
            label: Text(context.l10n.text('Add account')),
          ),
        ),
        const SizedBox(height: 14),
        _RiskHero(
          portfolio: portfolio,
          used: dashboard.session.combinedLossUsed,
          remaining: dashboard.session.combinedLossRemaining,
          recommendation: recommendation,
          session: dashboard.session,
        ),
        const SizedBox(height: 14),
        _MetricGrid(
          items: [
            _Metric(
              'Accounts',
              '${dashboard.totalAccounts}',
              Icons.wallet_outlined,
            ),
            _Metric(
              'Protected',
              '${dashboard.protectedAccounts}',
              Icons.shield_outlined,
            ),
            _Metric(
              'Locked',
              '${dashboard.lockedAccounts}',
              Icons.lock_outline,
            ),
            _Metric(
              'Trades today',
              '${dashboard.tradesToday}',
              Icons.receipt_long_outlined,
            ),
            _Metric(
              'Realised today',
              _money(dashboard.realisedResultToday, portfolio.currency),
              Icons.payments_outlined,
            ),
            _Metric(
              'R result today',
              '${_compact(dashboard.resultR)}R',
              Icons.stacked_line_chart,
            ),
            _Metric(
              'Consecutive losses',
              '${dashboard.consecutiveLosses}',
              Icons.trending_down,
            ),
            _Metric(
              'Discipline status',
              dashboard.discipline.score == null
                  ? context.l10n.text('Not enough data')
                  : '${dashboard.discipline.score!.toStringAsFixed(0)}/100',
              Icons.verified_outlined,
            ),
          ],
        ),
        const SizedBox(height: 18),
        _AccountFlow(accounts: portfolio.activeAccounts),
        const SizedBox(height: 18),
        _SectionHeading(
          title: context.l10n.text('Accounts'),
          subtitle: context.l10n.text(
            'Balances and equity are entered manually.',
          ),
        ),
        const SizedBox(height: 10),
        _AccountsGrid(
          accounts: portfolio.accounts,
          onEdit: (account) => _editAccount(context, account),
          onArchive: (account) => controller.archiveAccount(account.id),
        ),
        const SizedBox(height: 20),
        _StrategyBuilder(controller: controller, portfolio: portfolio),
        const SizedBox(height: 20),
        _SafeguardsCard(controller: controller, portfolio: portfolio),
        const SizedBox(height: 20),
        _AnalyticsCard(controller: controller, portfolio: portfolio),
      ],
    );
  }

  Future<void> _addAccount(BuildContext context) async {
    final account = await showPropFirmAccountEditor(
      context,
      controller.newAccountDraft(
        name: context.l10n.text('Account {number}', {
          'number': portfolio.accounts.length + 1,
        }),
        firmName: context.l10n.text('Custom firm'),
      ),
    );
    if (account != null && context.mounted) {
      await _saveAccountDraft(context, account);
    }
  }

  Future<void> _editAccount(
    BuildContext context,
    PropFirmAccount source,
  ) async {
    final account = await showPropFirmAccountEditor(context, source);
    if (account != null && context.mounted) {
      await _saveAccountDraft(context, account);
    }
  }

  Future<void> _saveAccountDraft(
    BuildContext context,
    PropFirmAccount initialDraft,
  ) async {
    var draft = initialDraft;
    while (context.mounted) {
      try {
        await controller.saveAccount(draft);
        if (context.mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(context.l10n.text('Account saved.'))),
          );
        }
        return;
      } on Object catch (error) {
        if (!context.mounted) return;
        _showError(context, error);
        final retryDraft = await showPropFirmAccountEditor(context, draft);
        if (retryDraft == null) return;
        draft = retryDraft;
      }
    }
  }
}

class _ControlRoom extends StatelessWidget {
  const _ControlRoom({required this.controller, required this.portfolio});

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final recommendation = controller.recommendation;
    final dashboard = controller.dashboardSnapshot!;
    final session = dashboard.session;
    final stopped = session.isStopped;
    final sessionStateLabel = stopped
        ? l10n.text('Session stopped')
        : portfolio.sessionActive
        ? l10n.text('Session active · planner guidance only')
        : switch (portfolio.sessionStatus) {
            PortfolioSessionStatus.ready => l10n.text('Session ready'),
            PortfolioSessionStatus.completed => l10n.text('Session completed'),
            PortfolioSessionStatus.cancelled => l10n.text('Session cancelled'),
            PortfolioSessionStatus.stopped => l10n.text('Session stopped'),
            _ => l10n.text('Session not started'),
          };
    final recommended = portfolio.accounts
        .where((account) => account.id == recommendation.accountId)
        .firstOrNull;

    return _PageScroll(
      children: [
        _SectionHeading(
          title: l10n.text('Daily control room'),
          subtitle: l10n.text(
            'Operate the manual planner from one deliberate workspace',
          ),
        ),
        const SizedBox(height: 14),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      radius: 26,
                      backgroundColor: stopped
                          ? Theme.of(context).colorScheme.errorContainer
                          : Theme.of(context).colorScheme.primaryContainer,
                      child: Icon(
                        stopped
                            ? Icons.pan_tool_alt_outlined
                            : Icons.play_arrow_rounded,
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            stopped
                                ? l10n.text('Stopped: {reason}', {
                                    'reason': l10n.text(
                                      sessionStopReasonTitle(
                                        session.stopReason!,
                                      ),
                                    ),
                                  })
                                : recommended?.name ??
                                      l10n.text('No eligible account'),
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          Text(sessionStateLabel),
                        ],
                      ),
                    ),
                    _StatusChip(
                      label: stopped ? 'Stop' : 'Recommended',
                      warning: stopped,
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                for (final reason in recommendation.reasons)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Padding(
                          padding: EdgeInsets.only(top: 3),
                          child: Icon(Icons.info_outline, size: 17),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            l10n.text(reason.messageKey, reason.values),
                          ),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 14),
                _MetricGrid(
                  compact: true,
                  items: [
                    _Metric(
                      'Risk remaining',
                      _money(session.combinedLossRemaining, portfolio.currency),
                      Icons.shield_outlined,
                    ),
                    _Metric(
                      'Losses remaining',
                      '${dashboard.lossesRemaining}',
                      Icons.trending_down,
                    ),
                    _Metric(
                      'Trades remaining',
                      '${dashboard.tradesRemaining}',
                      Icons.format_list_numbered,
                    ),
                    _Metric(
                      'Emotional risk',
                      portfolio.emotionalStop
                          ? 'Stop active'
                          : 'No stop flagged',
                      Icons.psychology_outlined,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 14),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            FilledButton.icon(
              onPressed: stopped || portfolio.sessionActive
                  ? null
                  : () => _run(context, controller.startSession),
              icon: const Icon(Icons.play_arrow),
              label: Text(l10n.text('Start session')),
            ),
            FilledButton.tonalIcon(
              onPressed: portfolio.sessionActive && !stopped
                  ? () => _planTrade(context)
                  : null,
              icon: const Icon(Icons.fact_check_outlined),
              label: Text(l10n.text('Plan trade')),
            ),
            FilledButton.tonalIcon(
              onPressed: portfolio.activeAccounts.isEmpty
                  ? null
                  : () => _recordResult(context),
              icon: const Icon(Icons.edit_note_outlined),
              label: Text(l10n.text('Record result')),
            ),
            OutlinedButton.icon(
              onPressed: portfolio.sessionActive
                  ? () => _run(context, controller.stopSession)
                  : null,
              icon: const Icon(Icons.stop_circle_outlined),
              label: Text(l10n.text('Stop session')),
            ),
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: Theme.of(context).colorScheme.error,
              ),
              onPressed: () => _emergencyLock(context),
              icon: const Icon(Icons.lock_outline),
              label: Text(l10n.text('Emergency lock all accounts')),
            ),
          ],
        ),
        if (stopped) ...[
          const SizedBox(height: 14),
          _WarningCard(
            text: l10n.text(
              'This lock affects only the application planning workflow. It does not interact with any prop-firm or broker system.',
            ),
            action: TextButton(
              onPressed: () => _overrideStop(context),
              child: Text(l10n.text('Record manual override')),
            ),
          ),
        ],
        const SizedBox(height: 20),
        _SectionHeading(
          title: l10n.text('Account availability'),
          subtitle: l10n.text(
            'Why each account is available, cooling, locked, or protected',
          ),
        ),
        const SizedBox(height: 10),
        _AccountsGrid(
          accounts: portfolio.accounts,
          exclusions: recommendation.exclusions,
        ),
        if (portfolio.openPlans.isNotEmpty) ...[
          const SizedBox(height: 20),
          _SectionHeading(
            title: l10n.text('Open planned positions'),
            subtitle: l10n.text('Manually entered risk; no live position data'),
          ),
          const SizedBox(height: 10),
          for (final plan in portfolio.openPlans)
            Card(
              child: ListTile(
                leading: const Icon(Icons.pending_actions_outlined),
                title: Text(
                  '${plan.instrument} · ${l10n.text(plan.direction.name)}',
                ),
                subtitle: Text(
                  l10n.text('{account} · {risk} risk · {rr}R expected', {
                    'account':
                        portfolio.accounts
                            .where((account) => account.id == plan.accountId)
                            .firstOrNull
                            ?.name ??
                        '',
                    'risk': _money(plan.riskAmount, portfolio.currency),
                    'rr': _compact(plan.rewardRiskRatio),
                  }),
                ),
              ),
            ),
        ],
      ],
    );
  }

  Future<void> _planTrade(BuildContext context) async {
    final trade = await showPreTradeAssistant(
      context,
      accounts: portfolio.activeAccounts,
      recommendedAccountId: controller.recommendation.accountId,
    );
    if (trade == null || !context.mounted) return;
    try {
      final warnings = await controller.addPlannedTrade(trade);
      if (warnings.isNotEmpty && context.mounted) {
        final override = TextEditingController(text: trade.overrideReason);
        final reason = await showDialog<String>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(context.l10n.text('Combined exposure warning')),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (final warning in warnings)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(
                      '• ${context.l10n.text(warning.messageKey, warning.values)}',
                    ),
                  ),
                const SizedBox(height: 8),
                TextField(
                  controller: override,
                  minLines: 2,
                  maxLines: 4,
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Override reason'),
                    helperText: context.l10n.text(
                      'A typed reason is required and will be stored in discipline history.',
                    ),
                  ),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(context.l10n.text('Cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, override.text),
                child: Text(context.l10n.text('Record with override')),
              ),
            ],
          ),
        );
        override.dispose();
        if (reason != null) {
          await controller.addPlannedTrade(
            trade.copyWith(overrideReason: reason),
            acceptWarnings: true,
          );
        }
      }
    } on Object catch (error) {
      if (context.mounted) _showError(context, error);
    }
  }

  Future<void> _recordResult(BuildContext context) async {
    final result = await showResultRecorder(
      context,
      accounts: portfolio.activeAccounts,
      openPlans: portfolio.openPlans,
      defaultAccountId:
          portfolio.activeAccountId ?? controller.recommendation.accountId,
    );
    if (result == null || !context.mounted) return;
    await _run(context, () => controller.recordResult(result));
    if (!context.mounted) return;
    final next = controller.recommendation;
    await showDialog<void>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Planner updated')),
        content: Text(
          next.shouldStop
              ? context.l10n.text(
                  'The portfolio stop condition is active. No account is recommended.',
                )
              : context.l10n.text('The next eligible account is {account}.', {
                  'account':
                      controller.portfolio!.accounts
                          .where((account) => account.id == next.accountId)
                          .firstOrNull
                          ?.name ??
                      '',
                }),
        ),
        actions: [
          FilledButton(
            onPressed: () => Navigator.pop(context),
            child: Text(context.l10n.text('Done')),
          ),
        ],
      ),
    );
  }

  Future<void> _emergencyLock(BuildContext context) async {
    final confirmed = await _confirmInContext(
      context,
      'Lock the planner portfolio?',
      'This stops planner recommendations only. It cannot close or control external trades.',
    );
    if (confirmed && context.mounted) {
      await _run(context, controller.emergencyLock);
    }
  }

  Future<void> _overrideStop(BuildContext context) async {
    final input = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Manual override')),
        content: TextField(
          controller: input,
          minLines: 3,
          maxLines: 5,
          decoration: InputDecoration(
            labelText: context.l10n.text('Why are you continuing?'),
            helperText: context.l10n.text(
              'This reason is stored in discipline history.',
            ),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, input.text),
            child: Text(context.l10n.text('Record override')),
          ),
        ],
      ),
    );
    input.dispose();
    if (reason != null && context.mounted) {
      await _run(context, () => controller.recordOverride(reason));
    }
  }

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
}

class _PlanningPage extends StatefulWidget {
  const _PlanningPage({required this.controller, required this.portfolio});

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;

  @override
  State<_PlanningPage> createState() => _PlanningPageState();
}

class _PlanningPageState extends State<_PlanningPage> {
  String? _accountId;
  var _recoveryMode = RecoveryMode.conservative;
  final _simulation = const ScenarioSimulationService();
  final _winRate = TextEditingController(text: '45');
  final _risk = TextEditingController(text: '100');
  final _winningR = TextEditingController(text: '2');
  final _losingR = TextEditingController(text: '1');
  final _tradeCount = TextEditingController(text: '100');
  final _payout = TextEditingController(text: '500');
  SimulationResult? _result;
  var _simulating = false;

  @override
  void initState() {
    super.initState();
    _accountId = widget.portfolio.activeAccounts.firstOrNull?.id;
  }

  @override
  void dispose() {
    for (final controller in [
      _winRate,
      _risk,
      _winningR,
      _losingR,
      _tradeCount,
      _payout,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final portfolio = widget.portfolio;
    if (portfolio.activeAccounts.isEmpty) {
      return _PageScroll(
        children: [
          _SectionHeading(
            title: context.l10n.text('Planning tools'),
            subtitle: context.l10n.text(
              'Add or restore an active account to use payout, recovery, and simulation tools.',
            ),
          ),
          const SizedBox(height: 14),
          _PlainNotice(
            icon: Icons.account_balance_wallet_outlined,
            text: context.l10n.text('No active account is available.'),
          ),
        ],
      );
    }
    final account =
        portfolio.activeAccounts
            .where((item) => item.id == _accountId)
            .firstOrNull ??
        portfolio.activeAccounts.first;
    final now = DateTime.now();
    final payout = widget.controller.calculations.payoutSummary(
      account,
      now: now,
    );
    final recovery = widget.controller.calculations.recoverySummary(
      account,
      mode: _recoveryMode,
    );
    return _PageScroll(
      children: [
        _SectionHeading(
          title: context.l10n.text('Planning tools'),
          subtitle: context.l10n.text(
            'Conservative recovery, payout preparation, and hypothetical scenarios',
          ),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: account.id,
          decoration: InputDecoration(labelText: context.l10n.text('Account')),
          items: [
            for (final item in portfolio.activeAccounts)
              DropdownMenuItem(value: item.id, child: Text(item.name)),
          ],
          onChanged: (value) => setState(() => _accountId = value!),
        ),
        const SizedBox(height: 18),
        _ResponsiveColumns(
          children: [
            _PayoutCard(
              account: account,
              summary: payout,
              portfolio: portfolio,
              onUpdatePreset: _updatePreset,
            ),
            _RecoveryCard(
              account: account,
              summary: recovery,
              mode: _recoveryMode,
              onModeChanged: (value) => setState(() => _recoveryMode = value),
            ),
          ],
        ),
        const SizedBox(height: 20),
        _SimulatorCard(
          portfolio: portfolio,
          winRate: _winRate,
          risk: _risk,
          winningR: _winningR,
          losingR: _losingR,
          tradeCount: _tradeCount,
          payout: _payout,
          result: _result,
          running: _simulating,
          onRun: _runSimulation,
        ),
      ],
    );
  }

  Future<void> _updatePreset() async {
    final portfolio = widget.controller.portfolio!;
    final presets = portfolio.firmPresets.isEmpty
        ? [
            PropFirmRulePreset(
              id: 'custom',
              firmName: context.l10n.text('Custom firm'),
              minimumPayout: 0,
              updatedAt: DateTime.now(),
            ),
          ]
        : portfolio.firmPresets;
    final preset = await showDialog<PropFirmRulePreset>(
      context: context,
      builder: (context) => _PresetDialog(presets: presets),
    );
    if (preset != null) await widget.controller.updateFirmPreset(preset);
  }

  Future<void> _runSimulation() async {
    setState(() => _simulating = true);
    try {
      await Future<void>.delayed(const Duration(milliseconds: 20));
      final result = _simulation.run(
        SimulationConfiguration(
          accountCount: widget.portfolio.activeAccounts.length,
          startingBalance:
              widget.portfolio.activeAccounts.first.startingBalance,
          riskPerTrade: double.parse(_risk.text.trim()),
          winRate: double.parse(_winRate.text.trim()) / 100,
          averageWinningR: double.parse(_winningR.text.trim()),
          averageLosingR: double.parse(_losingR.text.trim()),
          maximumTradesPerDay: widget.portfolio.riskPolicy.maximumTradesPerDay,
          maximumPortfolioLossesPerDay:
              widget.portfolio.riskPolicy.maximumConsecutiveLosses,
          payoutThreshold: double.parse(_payout.text.trim()),
          tradeCount: int.parse(_tradeCount.text.trim()),
        ),
      );
      if (mounted) setState(() => _result = result);
    } on Object catch (error) {
      if (mounted) _showError(context, error);
    } finally {
      if (mounted) setState(() => _simulating = false);
    }
  }
}

class _EducationPage extends StatelessWidget {
  const _EducationPage({required this.portfolio});

  final PropFirmPortfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final articles = <(IconData, String, String)>[
      (
        Icons.hub_outlined,
        'Portfolio risk is not extra buying power',
        'Three accounts can triple exposure when the same idea and risk are copied. A global budget limits the behaviour, but legal firm limits remain separate.',
      ),
      (
        Icons.waterfall_chart_outlined,
        'Static and trailing drawdown',
        'Static drawdown uses a fixed reference. Trailing drawdown can move with balance or equity. Configure the model your firm states and verify the exact formula.',
      ),
      (
        Icons.payments_outlined,
        'Payout thresholds and profit split',
        'Minimum payout, eligible profit, trading days, consistency rules, and split all affect a planning estimate. Firm rules can change.',
      ),
      (
        Icons.health_and_safety_outlined,
        'Recovery without martingale',
        'Keep risk fixed or reduced. Recovery required divided by planned risk gives required net R before costs; it does not predict how many trades will succeed.',
      ),
      (
        Icons.psychology_outlined,
        'Emotional account switching',
        'A loss on Account A does not justify trading Account B. Pause, review the setup, and treat a manual override as a discipline event.',
      ),
      (
        Icons.pan_tool_alt_outlined,
        'When to stop trading',
        'Stop when global realised loss plus planned risk reaches the limit, loss or trade caps are reached, or emotional control is compromised.',
      ),
      (
        Icons.copy_all_outlined,
        'Duplicate and correlated exposure',
        'The planner checks manually entered symbols and directions. Add your own correlation awareness because no live market correlation feed is used.',
      ),
      (
        Icons.add_card_outlined,
        'A new challenge is not a reset button',
        'Review the cause of a breach, ignored limits, increased risk, revenge trading, and repeated mistakes before considering another account.',
      ),
    ];
    return _PageScroll(
      children: [
        _SectionHeading(
          title: context.l10n.text('Education centre'),
          subtitle: context.l10n.text(
            'Concise, practical guidance for multi-account discipline',
          ),
        ),
        const SizedBox(height: 14),
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth >= 900 ? 2 : 1;
            final width = (constraints.maxWidth - (columns - 1) * 12) / columns;
            return Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                for (final article in articles)
                  SizedBox(
                    width: width,
                    child: Card(
                      child: ExpansionTile(
                        leading: Icon(article.$1),
                        title: Text(
                          context.l10n.text(article.$2),
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        children: [
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                            child: Text(context.l10n.text(article.$3)),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
        const SizedBox(height: 18),
        _PlainNotice(
          icon: Icons.gavel_outlined,
          text: context.l10n.text(
            'Verify the latest payout, drawdown, consistency, trading-day, prohibited-strategy, inactivity, and reset-time rules directly with each firm.',
          ),
        ),
      ],
    );
  }
}

class _RiskHero extends StatelessWidget {
  const _RiskHero({
    required this.portfolio,
    required this.used,
    required this.remaining,
    required this.recommendation,
    required this.session,
  });

  final PropFirmPortfolio portfolio;
  final double used;
  final double remaining;
  final RecommendationResult recommendation;
  final SessionStatusEvaluation session;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final limit = portfolio.riskPolicy.maximumDailyLoss;
    final progress = limit <= 0 ? 0.0 : (used / limit).clamp(0, 1).toDouble();
    final account = portfolio.accounts
        .where((item) => item.id == recommendation.accountId)
        .firstOrNull;
    final stopped = session.isStopped;
    final statusLabel = stopped
        ? context.l10n.text('Stopped: {reason}', {
            'reason': context.l10n.text(
              sessionStopReasonTitle(session.stopReason!),
            ),
          })
        : context.l10n.text(_sessionStatusLabel(session.status));
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        context.l10n.text('Global daily risk'),
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          color: scheme.onSurfaceVariant,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        _money(used, portfolio.currency),
                        style: Theme.of(context).textTheme.displaySmall
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        context.l10n.text('{remaining} remaining of {limit}', {
                          'remaining': _money(remaining, portfolio.currency),
                          'limit': _money(limit, portfolio.currency),
                        }),
                      ),
                    ],
                  ),
                ),
                _StatusChip(
                  label: statusLabel,
                  warning:
                      stopped ||
                      session.status == PortfolioSessionStatus.warning,
                ),
              ],
            ),
            const SizedBox(height: 16),
            Semantics(
              label: context.l10n.text(
                'Global daily risk used: {percent} percent',
                {'percent': (progress * 100).toStringAsFixed(0)},
              ),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 10,
                borderRadius: BorderRadius.circular(10),
                color: progress >= .8 ? scheme.error : scheme.primary,
              ),
            ),
            const SizedBox(height: 12),
            Text(
              stopped
                  ? context.l10n.text(
                      sessionStopReasonDescription(session.stopReason!),
                    )
                  : account == null
                  ? context.l10n.text('No eligible account is available.')
                  : context.l10n.text('Recommended account: {account}', {
                      'account': account.name,
                    }),
            ),
          ],
        ),
      ),
    );
  }

  String _sessionStatusLabel(PortfolioSessionStatus status) => switch (status) {
    PortfolioSessionStatus.draft => 'Draft',
    PortfolioSessionStatus.ready => 'Ready',
    PortfolioSessionStatus.active => 'Active',
    PortfolioSessionStatus.warning => 'Warning',
    PortfolioSessionStatus.stopped => 'Stopped',
    PortfolioSessionStatus.completed => 'Completed',
    PortfolioSessionStatus.cancelled => 'Cancelled',
  };
}

class _AccountFlow extends StatelessWidget {
  const _AccountFlow({required this.accounts});

  final List<PropFirmAccount> accounts;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              context.l10n.text('Account rotation flow'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 12),
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  for (var index = 0; index < accounts.length; index++) ...[
                    if (index > 0)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 8),
                        child: Icon(
                          Icons.arrow_forward,
                          color: Theme.of(context).colorScheme.outline,
                        ),
                      ),
                    Semantics(
                      label:
                          '${accounts[index].name}. ${context.l10n.text(accountStatusLabel(accounts[index].status))}',
                      child: Container(
                        constraints: const BoxConstraints(minWidth: 145),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Color(
                            accounts[index].colorValue,
                          ).withValues(alpha: .09),
                          border: Border.all(
                            color: Color(accounts[index].colorValue),
                          ),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              accounts[index].name,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              context.l10n.text(
                                accountStatusLabel(accounts[index].status),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                  if (accounts.isNotEmpty) ...[
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 8),
                      child: Icon(
                        Icons.arrow_forward,
                        color: Theme.of(context).colorScheme.outline,
                      ),
                    ),
                    Chip(
                      avatar: const Icon(Icons.stop_circle_outlined, size: 18),
                      label: Text(context.l10n.text('Stop / reset')),
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

class _AccountsGrid extends StatelessWidget {
  const _AccountsGrid({
    required this.accounts,
    this.onEdit,
    this.onArchive,
    this.exclusions = const {},
  });

  final List<PropFirmAccount> accounts;
  final ValueChanged<PropFirmAccount>? onEdit;
  final ValueChanged<PropFirmAccount>? onArchive;
  final Map<String, List<RecommendationReason>> exclusions;

  @override
  Widget build(BuildContext context) {
    if (accounts.isEmpty) {
      return _PlainNotice(
        icon: Icons.account_balance_wallet_outlined,
        text: context.l10n.text('No accounts have been added yet.'),
      );
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 1050
            ? 3
            : constraints.maxWidth >= 650
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
                child: _AccountCard(
                  account: account,
                  exclusions: exclusions[account.id] ?? const [],
                  onEdit: onEdit == null ? null : () => onEdit!(account),
                  onArchive: onArchive == null
                      ? null
                      : () => onArchive!(account),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _AccountCard extends StatelessWidget {
  const _AccountCard({
    required this.account,
    required this.exclusions,
    this.onEdit,
    this.onArchive,
  });

  final PropFirmAccount account;
  final List<RecommendationReason> exclusions;
  final VoidCallback? onEdit;
  final VoidCallback? onArchive;

  @override
  Widget build(BuildContext context) {
    final color = Color(account.colorValue);
    final buffer = account.maximumLossLimit <= 0
        ? 0.0
        : (account.remainingMaximumDrawdown / account.maximumLossLimit)
              .clamp(0, 1)
              .toDouble();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                CircleAvatar(
                  backgroundColor: color.withValues(alpha: .12),
                  foregroundColor: color,
                  child: const Icon(Icons.account_balance_outlined),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        account.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      Text(
                        account.propFirmName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                if (onEdit != null || onArchive != null)
                  PopupMenuButton<String>(
                    tooltip: context.l10n.text('Account actions'),
                    onSelected: (value) {
                      if (value == 'edit') onEdit?.call();
                      if (value == 'archive') onArchive?.call();
                    },
                    itemBuilder: (_) => [
                      if (onEdit != null)
                        PopupMenuItem(
                          value: 'edit',
                          child: Text(context.l10n.text('Edit account')),
                        ),
                      if (onArchive != null && !account.isArchived)
                        PopupMenuItem(
                          value: 'archive',
                          child: Text(context.l10n.text('Archive account')),
                        ),
                    ],
                  ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                _StatusChip(label: accountStatusLabel(account.status)),
                const Spacer(),
                Text(
                  _money(account.currentBalance, account.currency),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              context.l10n.text('Maximum drawdown buffer'),
              style: Theme.of(context).textTheme.labelMedium,
            ),
            const SizedBox(height: 5),
            LinearProgressIndicator(
              value: buffer,
              minHeight: 7,
              borderRadius: BorderRadius.circular(7),
              color: buffer < .3 ? Theme.of(context).colorScheme.error : color,
            ),
            const SizedBox(height: 5),
            Text(
              _money(account.remainingMaximumDrawdown, account.currency),
              style: Theme.of(context).textTheme.labelSmall,
            ),
            if (exclusions.isNotEmpty) ...[
              const Divider(height: 22),
              for (final reason in exclusions.take(2))
                Padding(
                  padding: const EdgeInsets.only(bottom: 4),
                  child: Text(
                    context.l10n.text(reason.messageKey, reason.values),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StrategyBuilder extends StatelessWidget {
  const _StrategyBuilder({required this.controller, required this.portfolio});

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final templates = <(RotationTemplate, String, String)>[
      (
        RotationTemplate.balanced,
        'Balanced three-account rotation',
        'One active account, equal allocation, rotate after TP, lock after SL, protect payout-ready accounts.',
      ),
      (
        RotationTemplate.payoutProtection,
        'Conservative payout protection',
        'Prioritise drawdown room, reduce near-payout exposure, and keep payout accounts out of recovery.',
      ),
      (
        RotationTemplate.recoveryIsolation,
        'Recovery isolation',
        'Fixed or reduced recovery risk, no martingale, and no automatic switching after a recovery loss.',
      ),
      (
        RotationTemplate.oneAccountPerSession,
        'One account per session',
        'Choose one account when the session starts; other accounts remain unavailable until reset.',
      ),
      (
        RotationTemplate.custom,
        'Custom',
        'Use editable safeguards and account states to build your own transparent policy.',
      ),
    ];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _SectionHeading(
              title: context.l10n.text('Rotation strategy builder'),
              subtitle: context.l10n.text(
                'Templates are editable starting examples, not universal settings.',
              ),
            ),
            const SizedBox(height: 12),
            RadioGroup<RotationTemplate>(
              groupValue: portfolio.strategy.template,
              onChanged: (value) {
                if (value != null) controller.selectStrategy(value);
              },
              child: Column(
                children: [
                  for (final item in templates)
                    RadioListTile<RotationTemplate>(
                      contentPadding: EdgeInsets.zero,
                      value: item.$1,
                      title: Text(
                        context.l10n.text(item.$2),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      subtitle: Text(context.l10n.text(item.$3)),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            _PlainNotice(
              icon: Icons.block_outlined,
              text: context.l10n.text(
                'Every template prohibits automatic risk doubling and martingale recovery.',
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SafeguardsCard extends StatelessWidget {
  const _SafeguardsCard({required this.controller, required this.portfolio});

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final policy = portfolio.riskPolicy;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _SectionHeading(
              title: context.l10n.text('Portfolio safeguards'),
              subtitle: context.l10n.text(
                'Hard stops apply across every account.',
              ),
              trailing: OutlinedButton.icon(
                onPressed: () => _edit(context),
                icon: const Icon(Icons.edit_outlined),
                label: Text(context.l10n.text('Edit safeguards')),
              ),
            ),
            const SizedBox(height: 12),
            _MetricGrid(
              compact: true,
              items: [
                _Metric(
                  'Daily stop',
                  _money(policy.maximumDailyLoss, portfolio.currency),
                  Icons.shield_outlined,
                ),
                _Metric(
                  'Risk per trade',
                  _money(policy.maximumRiskPerTrade, portfolio.currency),
                  Icons.percent,
                ),
                _Metric(
                  'Legacy default trades per account',
                  '${policy.maximumTradesPerDay}',
                  Icons.format_list_numbered,
                ),
                _Metric(
                  'Consecutive losses',
                  '${policy.maximumConsecutiveLosses}',
                  Icons.trending_down,
                ),
                _Metric(
                  'Open positions',
                  '${policy.maximumOpenPositions}',
                  Icons.layers_outlined,
                ),
                _Metric(
                  'Simultaneous trading',
                  policy.simultaneousTradingAllowed
                      ? 'Allowed with warnings'
                      : 'Disabled',
                  Icons.compare_arrows,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _edit(BuildContext context) async {
    final result = await showRiskPolicyEditor(context, portfolio.riskPolicy);
    if (result == null) return;
    try {
      await controller.updateRiskPolicy(result);
    } on Object catch (error) {
      if (context.mounted) _showError(context, error);
    }
  }
}

class _AnalyticsCard extends StatelessWidget {
  const _AnalyticsCard({required this.controller, required this.portfolio});

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;

  @override
  Widget build(BuildContext context) {
    final dashboard = controller.dashboardSnapshot!;
    final analytics = dashboard.disciplineAnalytics;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _SectionHeading(
              title: context.l10n.text('Discipline and analytics'),
              subtitle: context.l10n.text(
                'Transparent behaviour metrics; no fake AI accuracy score',
              ),
            ),
            const SizedBox(height: 12),
            _MetricGrid(
              items: [
                _Metric(
                  'Rotation compliance',
                  analytics.rotationCompliance == null
                      ? context.l10n.text('Not enough data')
                      : '${analytics.rotationCompliance!.toStringAsFixed(0)}%',
                  Icons.route_outlined,
                ),
                _Metric(
                  'Plan compliance',
                  analytics.planCompliance == null
                      ? context.l10n.text('Not enough data')
                      : '${analytics.planCompliance!.toStringAsFixed(0)}%',
                  Icons.fact_check_outlined,
                ),
                _Metric(
                  'Manual overrides',
                  '${analytics.manualOverrides}',
                  Icons.warning_amber_outlined,
                ),
                _Metric(
                  'Stop conditions respected',
                  '${analytics.respectedStops}',
                  Icons.pan_tool_alt_outlined,
                ),
                _Metric(
                  'Average risk',
                  _money(analytics.averageRisk, portfolio.currency),
                  Icons.horizontal_rule,
                ),
                _Metric(
                  'Maximum risk used',
                  _money(analytics.maximumRisk, portfolio.currency),
                  Icons.vertical_align_top,
                ),
                _Metric(
                  'Avoided overtrade count',
                  '${analytics.avoidedOvertrades}',
                  Icons.health_and_safety_outlined,
                ),
                _Metric(
                  'Portfolio survival',
                  '${dashboard.portfolioSurvivalDays} days',
                  Icons.calendar_month_outlined,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PayoutCard extends StatelessWidget {
  const _PayoutCard({
    required this.account,
    required this.summary,
    required this.portfolio,
    required this.onUpdatePreset,
  });

  final PropFirmAccount account;
  final PayoutPlanSummary summary;
  final PropFirmPortfolio portfolio;
  final VoidCallback onUpdatePreset;

  @override
  Widget build(BuildContext context) {
    final progress = summary.progress.clamp(0, 1).toDouble();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.payments_outlined),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    context.l10n.text('Payout planner'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.text('Update firm rules'),
                  onPressed: onUpdatePreset,
                  icon: const Icon(Icons.edit_outlined),
                ),
              ],
            ),
            const SizedBox(height: 14),
            Semantics(
              label: context.l10n.text('Payout progress: {percent} percent', {
                'percent': (progress * 100).toStringAsFixed(0),
              }),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 10,
                borderRadius: BorderRadius.circular(10),
              ),
            ),
            const SizedBox(height: 12),
            _KeyValue(
              'Eligible profit',
              _money(summary.eligibleProfit, account.currency),
            ),
            _KeyValue(
              'Required gross payout',
              _money(summary.requiredGrossProfit, account.currency),
            ),
            _KeyValue(
              'Remaining profit required',
              _money(summary.remainingProfit, account.currency),
            ),
            _KeyValue(
              'Estimated net payout',
              _money(summary.estimatedNetPayout, account.currency),
            ),
            _KeyValue(
              'Profit split',
              '${(account.profitSplit * 100).toStringAsFixed(0)}%',
            ),
            _KeyValue(
              'Trading days remaining',
              '${summary.tradingDaysRemaining}',
            ),
            _KeyValue(
              'Eligibility date status',
              summary.isDateEligible ? 'Date eligible' : 'Date not reached',
            ),
            _KeyValue(
              'Suggested protection buffer',
              _money(summary.suggestedProtectionBuffer, account.currency),
            ),
            const SizedBox(height: 12),
            _PlainNotice(
              icon: Icons.verified_user_outlined,
              text: context.l10n.text(
                'Prop-firm rules can change. Verify the latest payout, drawdown, consistency, trading-day, prohibited-strategy, and inactivity rules directly with the firm.',
              ),
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'Editable examples: The5ers USD 150 · Alpha Capital Group USD 100.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _RecoveryCard extends StatelessWidget {
  const _RecoveryCard({
    required this.account,
    required this.summary,
    required this.mode,
    required this.onModeChanged,
  });

  final PropFirmAccount account;
  final RecoveryPlanSummary summary;
  final RecoveryMode mode;
  final ValueChanged<RecoveryMode> onModeChanged;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.health_and_safety_outlined),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    context.l10n.text('Conservative recovery planner'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            SegmentedButton<RecoveryMode>(
              segments: [
                ButtonSegment(
                  value: RecoveryMode.conservative,
                  label: Text(context.l10n.text('Conservative')),
                ),
                ButtonSegment(
                  value: RecoveryMode.standard,
                  label: Text(context.l10n.text('Standard')),
                ),
                ButtonSegment(
                  value: RecoveryMode.pauseAndReview,
                  label: Text(context.l10n.text('Pause')),
                ),
              ],
              selected: {mode},
              onSelectionChanged: (value) => onModeChanged(value.first),
            ),
            const SizedBox(height: 14),
            _KeyValue(
              'Current drawdown',
              _money(account.drawdown, account.currency),
            ),
            _KeyValue(
              'Remaining drawdown buffer',
              _money(summary.remainingBuffer, account.currency),
            ),
            _KeyValue(
              'Amount required to breakeven',
              _money(summary.recoveryRequired, account.currency),
            ),
            _KeyValue(
              'Planned risk per trade',
              _money(summary.riskPerTrade, account.currency),
            ),
            _KeyValue(
              'Required net R before costs',
              '${_compact(summary.requiredNetR)}R',
            ),
            _KeyValue('Hypothetical full 2R wins', '${summary.fullWinsAtTwoR}'),
            const SizedBox(height: 12),
            _PlainNotice(
              icon: Icons.block_outlined,
              text: context.l10n.text(
                'Recovery never increases risk automatically. No martingale or risk doubling is used.',
              ),
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'Actual results may differ due to losses, partial closes, fees, slippage, and rule violations. This is a planning estimate, not a recovery prediction.',
              ),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _SimulatorCard extends StatelessWidget {
  const _SimulatorCard({
    required this.portfolio,
    required this.winRate,
    required this.risk,
    required this.winningR,
    required this.losingR,
    required this.tradeCount,
    required this.payout,
    required this.result,
    required this.running,
    required this.onRun,
  });

  final PropFirmPortfolio portfolio;
  final TextEditingController winRate;
  final TextEditingController risk;
  final TextEditingController winningR;
  final TextEditingController losingR;
  final TextEditingController tradeCount;
  final TextEditingController payout;
  final SimulationResult? result;
  final bool running;
  final VoidCallback onRun;

  @override
  Widget build(BuildContext context) {
    final fields = [
      (winRate, 'Estimated win rate (%)'),
      (risk, 'Risk per trade'),
      (winningR, 'Average winning R'),
      (losingR, 'Average losing R'),
      (tradeCount, 'Simulation trade count'),
      (payout, 'Payout threshold'),
    ];
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _SectionHeading(
              title: context.l10n.text('Hypothetical scenario simulator'),
              subtitle: context.l10n.text(
                '500 seeded Monte Carlo paths based entirely on your assumptions',
              ),
            ),
            const SizedBox(height: 14),
            LayoutBuilder(
              builder: (context, constraints) {
                final columns = constraints.maxWidth >= 900
                    ? 3
                    : constraints.maxWidth >= 550
                    ? 2
                    : 1;
                final width =
                    (constraints.maxWidth - (columns - 1) * 10) / columns;
                return Wrap(
                  spacing: 10,
                  runSpacing: 10,
                  children: [
                    for (final field in fields)
                      SizedBox(
                        width: width,
                        child: TextField(
                          controller: field.$1,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: InputDecoration(
                            labelText: context.l10n.text(field.$2),
                          ),
                        ),
                      ),
                  ],
                );
              },
            ),
            const SizedBox(height: 14),
            FilledButton.icon(
              onPressed: running ? null : onRun,
              icon: running
                  ? const SizedBox.square(
                      dimension: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.science_outlined),
              label: Text(context.l10n.text('Run hypothetical simulation')),
            ),
            if (result != null) ...[
              const SizedBox(height: 18),
              _MetricGrid(
                items: [
                  _Metric(
                    'Paths reaching payout',
                    _percent(result!.payoutFrequency),
                    Icons.flag_outlined,
                  ),
                  _Metric(
                    'Paths with a breach',
                    _percent(result!.breachFrequency),
                    Icons.warning_amber_outlined,
                  ),
                  _Metric(
                    'Median drawdown',
                    _money(result!.medianDrawdown, portfolio.currency),
                    Icons.waterfall_chart,
                  ),
                  _Metric(
                    'Worst simulated drawdown',
                    _money(result!.worstDrawdown, portfolio.currency),
                    Icons.trending_down,
                  ),
                  _Metric(
                    'Average trades to threshold',
                    result!.averageTradesToPayout?.toStringAsFixed(0) ??
                        'Not reached',
                    Icons.timelapse_outlined,
                  ),
                  _Metric(
                    'Reduced-risk median drawdown',
                    _money(
                      result!.reducedRiskMedianDrawdown,
                      portfolio.currency,
                    ),
                    Icons.health_and_safety_outlined,
                  ),
                ],
              ),
              const SizedBox(height: 14),
              _PlainNotice(
                icon: Icons.info_outline,
                text: context.l10n.text(
                  'This is hypothetical, based entirely on user-entered assumptions, and is not a prediction. An assumed or historical win rate does not guarantee future performance.',
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _PresetDialog extends StatefulWidget {
  const _PresetDialog({required this.presets});

  final List<PropFirmRulePreset> presets;

  @override
  State<_PresetDialog> createState() => _PresetDialogState();
}

class _PresetDialogState extends State<_PresetDialog> {
  late PropFirmRulePreset _selected;
  late final TextEditingController _firm;
  late final TextEditingController _minimum;

  @override
  void initState() {
    super.initState();
    _selected = widget.presets.first;
    _firm = TextEditingController(text: _selected.firmName);
    _minimum = TextEditingController(text: _compact(_selected.minimumPayout));
  }

  @override
  void dispose() {
    _firm.dispose();
    _minimum.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(context.l10n.text('Update firm rules')),
      content: SizedBox(
        width: 480,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _selected.id,
              decoration: InputDecoration(
                labelText: context.l10n.text('Editable preset'),
              ),
              items: [
                for (final preset in widget.presets)
                  DropdownMenuItem(
                    value: preset.id,
                    child: Text(preset.firmName),
                  ),
              ],
              onChanged: (id) {
                final selected = widget.presets.firstWhere(
                  (item) => item.id == id,
                );
                setState(() {
                  _selected = selected;
                  _firm.text = selected.firmName;
                  _minimum.text = _compact(selected.minimumPayout);
                });
              },
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _firm,
              decoration: InputDecoration(
                labelText: context.l10n.text('Firm name'),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _minimum,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: context.l10n.text('Minimum payout'),
              ),
            ),
            const SizedBox(height: 12),
            _PlainNotice(
              icon: Icons.update_outlined,
              text: context.l10n.text(
                'Presets are user-editable examples and are not permanently correct.',
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(context.l10n.text('Cancel')),
        ),
        FilledButton(
          onPressed: () {
            final value = double.tryParse(_minimum.text.trim());
            if (value == null || value < 0 || _firm.text.trim().isEmpty) return;
            Navigator.pop(
              context,
              _selected.copyWith(
                firmName: _firm.text.trim(),
                minimumPayout: value,
                updatedAt: DateTime.now(),
              ),
            );
          },
          child: Text(context.l10n.text('Save preset')),
        ),
      ],
    );
  }
}

class _Metric {
  const _Metric(this.label, this.value, this.icon);

  final String label;
  final String value;
  final IconData icon;
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.items, this.compact = false});

  final List<_Metric> items;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final minWidth = compact ? 190.0 : 210.0;
        final columns = (constraints.maxWidth / minWidth).floor().clamp(
          1,
          items.length,
        );
        final width = (constraints.maxWidth - (columns - 1) * 10) / columns;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final item in items)
              SizedBox(
                width: width,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.surfaceContainerHighest
                        .withValues(alpha: .45),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(13),
                    child: Row(
                      children: [
                        Icon(item.icon, size: 21),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                context.l10n.text(item.label),
                                style: Theme.of(context).textTheme.labelMedium,
                              ),
                              const SizedBox(height: 2),
                              Text(
                                context.l10n.text(item.value),
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _KeyValue extends StatelessWidget {
  const _KeyValue(this.label, this.value);

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Expanded(child: Text(context.l10n.text(label))),
          const SizedBox(width: 12),
          Text(
            context.l10n.text(value),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, this.subtitle, this.trailing});

  final String title;
  final String? subtitle;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 2),
                Text(
                  subtitle!,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ],
            ],
          ),
        ),
        if (trailing != null) ...[const SizedBox(width: 12), trailing!],
      ],
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label, this.warning = false});

  final String label;
  final bool warning;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Chip(
      avatar: Icon(
        warning ? Icons.warning_amber_outlined : Icons.circle,
        size: warning ? 17 : 10,
      ),
      label: Text(context.l10n.text(label)),
      backgroundColor: warning
          ? scheme.errorContainer
          : scheme.primaryContainer,
      side: BorderSide.none,
    );
  }
}

class _PageScroll extends StatelessWidget {
  const _PageScroll({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(18, 16, 18, 42),
      children: [
        Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 1220),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: children,
            ),
          ),
        ),
      ],
    );
  }
}

class _ResponsiveColumns extends StatelessWidget {
  const _ResponsiveColumns({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 760) {
          return Column(
            children: [
              for (var index = 0; index < children.length; index++) ...[
                children[index],
                if (index < children.length - 1) const SizedBox(height: 12),
              ],
            ],
          );
        }
        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (var index = 0; index < children.length; index++) ...[
              Expanded(child: children[index]),
              if (index < children.length - 1) const SizedBox(width: 12),
            ],
          ],
        );
      },
    );
  }
}

class _HeroCard extends StatelessWidget {
  const _HeroCard({
    required this.icon,
    required this.eyebrow,
    required this.title,
    required this.description,
  });

  final IconData icon;
  final String eyebrow;
  final String title;
  final String description;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.primaryContainer.withValues(alpha: .42),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: scheme.primary.withValues(alpha: .3)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: scheme.primary,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Icon(icon, color: scheme.onPrimary, size: 30),
            ),
            const SizedBox(width: 18),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    eyebrow,
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: scheme.primary,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.1,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    title,
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(description),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({
    required this.icon,
    required this.title,
    required this.lines,
    this.warning = false,
  });

  final IconData icon;
  final String title;
  final List<String> lines;
  final bool warning;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Icon(icon, color: warning ? scheme.error : scheme.primary),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            for (final line in lines)
              Padding(
                padding: const EdgeInsets.only(bottom: 7),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Icon(
                        Icons.circle,
                        size: 7,
                        color: warning ? scheme.error : scheme.primary,
                      ),
                    ),
                    const SizedBox(width: 9),
                    Expanded(child: Text(context.l10n.text(line))),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _WarningCard extends StatelessWidget {
  const _WarningCard({required this.text, this.action});

  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.errorContainer.withValues(alpha: .42),
        border: Border.all(color: scheme.error.withValues(alpha: .35)),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.warning_amber_outlined, color: scheme.error),
            const SizedBox(width: 10),
            Expanded(child: Text(text)),
            if (action != null) ...[const SizedBox(width: 8), action!],
          ],
        ),
      ),
    );
  }
}

class _PlainNotice extends StatelessWidget {
  const _PlainNotice({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: scheme.surfaceContainerHighest.withValues(alpha: .45),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 21),
            const SizedBox(width: 10),
            Expanded(child: Text(text)),
          ],
        ),
      ),
    );
  }
}

Future<bool> _confirmInContext(
  BuildContext context,
  String title,
  String message,
) async =>
    await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text(title)),
        content: Text(context.l10n.text(message)),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(context.l10n.text('Confirm')),
          ),
        ],
      ),
    ) ??
    false;

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(context.l10n.text('$error')),
      backgroundColor: Theme.of(context).colorScheme.error,
    ),
  );
}

String _money(double value, String currency) =>
    '$currency ${value.toStringAsFixed(2)}';

String _syncDateTime(DateTime value) {
  final local = value.toLocal();
  String two(int number) => number.toString().padLeft(2, '0');
  return '${local.year}-${two(local.month)}-${two(local.day)} '
      '${two(local.hour)}:${two(local.minute)}';
}

String _compact(double value) => value == value.roundToDouble()
    ? value.toStringAsFixed(0)
    : value.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

String _percent(double value) => '${(value * 100).toStringAsFixed(1)}%';
