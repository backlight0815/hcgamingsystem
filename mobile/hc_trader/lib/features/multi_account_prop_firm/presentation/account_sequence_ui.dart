import 'package:flutter/material.dart';

import '../../../core/localization/app_localizations.dart';
import '../domain/account_sequence_models.dart';
import '../domain/prop_firm_models.dart';
import '../domain/session_stop_condition_service.dart';
import 'account_health_ui.dart';
import 'prop_firm_planner_controller.dart';
import 'prop_firm_planner_dialogs.dart';

/// Additive decision-support surface for deciding which funded account should
/// be traded first and what may follow. It deliberately reuses the planner's
/// existing account, health, risk, trade-plan, and result-recording workflows.
class AccountSequencePlannerPage extends StatefulWidget {
  const AccountSequencePlannerPage({
    super.key,
    required this.controller,
    required this.portfolio,
  });

  final PropFirmPlannerController controller;
  final PropFirmPortfolio portfolio;

  @override
  State<AccountSequencePlannerPage> createState() =>
      _AccountSequencePlannerPageState();
}

class _AccountSequencePlannerPageState
    extends State<AccountSequencePlannerPage> {
  var _working = false;

  PropFirmPlannerController get controller => widget.controller;
  PropFirmPortfolio get portfolio => controller.portfolio ?? widget.portfolio;

  @override
  Widget build(BuildContext context) {
    final plan = controller.sequencePlan;
    if (plan == null) {
      return Center(child: Text(context.l10n.text('No portfolio available.')));
    }
    final settings = controller.sequenceState.settings;
    final accounts = {
      for (final item in portfolio.activeAccounts) item.id: item,
    };
    return RefreshIndicator(
      onRefresh: controller.recalculateSequence,
      child: ListView(
        key: const Key('account-sequence-planner'),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
        children: [
          _header(plan, settings),
          const SizedBox(height: 12),
          if (portfolio.portfolioLocked || plan.portfolioStopped)
            _stopBanner(plan),
          _sectionTitle(
            'Choose the starting account',
            'Your eligible choice stays first. The system recommends a replacement only when it is unsafe or unavailable.',
          ),
          _startingAccountCards(plan, accounts),
          const SizedBox(height: 16),
          _selectionMessage(plan, accounts),
          const SizedBox(height: 16),
          _sequenceCard(plan, accounts),
          const SizedBox(height: 12),
          _actionBar(plan),
          const SizedBox(height: 16),
          if (portfolio.sessionActive) _sessionCard(plan, accounts),
          _settingsCard(settings),
          const SizedBox(height: 12),
          _comparisonCard(plan, accounts),
          const SizedBox(height: 12),
          _explanationsCard(plan, accounts),
          const SizedBox(height: 12),
          _decisionTreeCard(plan, accounts),
          const SizedBox(height: 12),
          _stopConditionsCard(plan),
          const SizedBox(height: 12),
          _dataQualityCard(plan),
          const SizedBox(height: 12),
          _templatesCard(),
          const SizedBox(height: 12),
          _disclaimer(),
        ],
      ),
    );
  }

  Widget _header(AccountSequencePlan plan, AccountSequenceSettings settings) {
    final confirmed = plan.confirmedAt != null;
    final active = portfolio.sessionActive;
    final status = plan.portfolioStopped
        ? 'Stopped'
        : active
        ? 'Session active'
        : confirmed
        ? 'Confirmed'
        : 'Draft';
    return Card(
      color: Theme.of(context).colorScheme.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(
                  context.l10n.text('Dynamic Account Trading Sequence'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                _pill(status, _statusColor(status)),
                _pill(
                  _modeLabel(settings.mode),
                  Theme.of(context).colorScheme.primary,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text(
                'A rule-based plan for which account to trade first, which account may follow, and when trading must stop.',
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 18,
              runSpacing: 6,
              children: [
                _meta('Trading date', _date(plan.generatedAt)),
                _meta('Last recalculated', _dateTime(plan.generatedAt)),
                _meta('Data status', _dataLabel(plan.dataCompleteness)),
                if (plan.updateReason != null)
                  _meta('Update', _reason(plan.updateReason!)),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _stopBanner(AccountSequencePlan plan) => Card(
    color: Theme.of(context).colorScheme.errorContainer,
    child: ListTile(
      leading: const Icon(Icons.gpp_bad_outlined),
      title: Text(
        portfolio.stopReason == null
            ? context.l10n.text('Portfolio stop is active')
            : context.l10n.text('Stopped: {reason}', {
                'reason': context.l10n.text(
                  sessionStopReasonTitle(portfolio.stopReason!),
                ),
              }),
      ),
      subtitle: Text(
        portfolio.stopReason == null
            ? context.l10n.text(
                'No account may be started until the existing Control Room or account-health stop is reviewed and safely cleared.',
              )
            : context.l10n.text(
                sessionStopReasonDescription(portfolio.stopReason!),
              ),
      ),
    ),
  );

  Widget _startingAccountCards(
    AccountSequencePlan plan,
    Map<String, PropFirmAccount> accounts,
  ) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth >= 1100
            ? (constraints.maxWidth - 24) / 3
            : constraints.maxWidth >= 680
            ? (constraints.maxWidth - 12) / 2
            : constraints.maxWidth;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ActionChip(
              avatar: const Icon(Icons.auto_awesome, size: 18),
              label: Text(context.l10n.text('Use system recommendation')),
              onPressed: () =>
                  _run(() => controller.selectSequenceStartingAccount(null)),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                for (final account in accounts.values)
                  SizedBox(
                    width: width,
                    child: _StartingAccountCard(
                      account: account,
                      evaluation: plan.evaluations[account.id]!,
                      selected:
                          controller
                              .sequenceState
                              .settings
                              .selectedStartingAccountId ==
                          account.id,
                      recommended: plan.selectedStartingAccountId == account.id,
                      onSelect: () => _selectAccount(account, plan),
                      onReview: () => _reviewAccount(account),
                    ),
                  ),
              ],
            ),
          ],
        );
      },
    );
  }

  Widget _selectionMessage(
    AccountSequencePlan plan,
    Map<String, PropFirmAccount> accounts,
  ) {
    final requested = accounts[plan.requestedStartingAccountId];
    final selected = accounts[plan.selectedStartingAccountId];
    final evaluation = plan.evaluations[plan.selectedStartingAccountId];
    final replaced = plan.startingAccountWasReplaced && requested != null;
    final text = replaced
        ? 'Your selected account is not currently eligible. {account} is the safest eligible replacement.'
        : controller.sequenceState.settings.selectedStartingAccountId.isEmpty
        ? 'System recommendation: start with {account} after checking all hard limits.'
        : evaluation?.isEligibleWithReducedRisk == true
        ? '{account} remains first, but only with reduced risk and a fresh pre-trade check.'
        : 'Your eligible selection, {account}, remains first.';
    return Card(
      child: ListTile(
        leading: Icon(replaced ? Icons.swap_horiz : Icons.verified_outlined),
        title: Text(
          context.l10n.text(text, {'account': selected?.name ?? '—'}),
        ),
        subtitle: selected == null
            ? Text(context.l10n.text('No eligible account is available.'))
            : Text(
                context.l10n.text(
                  'This is decision support only. Reassessment is required before every sequence step.',
                ),
              ),
        trailing: requested == null || !replaced
            ? null
            : TextButton(
                onPressed: () => _reviewAccount(requested),
                child: Text(context.l10n.text('Review selected account')),
              ),
      ),
    );
  }

  Widget _sequenceCard(
    AccountSequencePlan plan,
    Map<String, PropFirmAccount> accounts,
  ) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Generated account sequence'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                IconButton(
                  key: const Key('sequence-recalculate'),
                  tooltip: context.l10n.text('Recalculate now'),
                  onPressed: _working
                      ? null
                      : () => _run(controller.recalculateSequence),
                  icon: const Icon(Icons.refresh),
                ),
              ],
            ),
            Text(
              context.l10n.text(
                'A sequence is a conditional plan, not permission to trade every account. Every step must pass the account-health, portfolio-risk, payout-protection, and psychology checks again.',
              ),
            ),
            const SizedBox(height: 16),
            if (plan.steps.isEmpty)
              _empty(
                'No eligible account sequence can be produced. Review the exclusions and stop conditions below.',
              )
            else
              LayoutBuilder(
                builder: (context, constraints) {
                  final mobile = constraints.maxWidth < 600;
                  final children = <Widget>[
                    for (var index = 0; index < plan.steps.length; index++) ...[
                      _step(plan.steps[index], accounts),
                      if (index < plan.steps.length - 1)
                        Icon(
                          mobile ? Icons.arrow_downward : Icons.arrow_forward,
                        ),
                    ],
                  ];
                  return mobile
                      ? Column(children: children)
                      : Wrap(
                          spacing: 8,
                          runSpacing: 10,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: children,
                        );
                },
              ),
            if (plan.exclusions.isNotEmpty) ...[
              const Divider(height: 28),
              Text(
                context.l10n.text('Excluded from this sequence'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              for (final exclusion in plan.exclusions)
                ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.block, size: 20),
                  title: Text(
                    accounts[exclusion.accountId]?.name ?? exclusion.accountId,
                  ),
                  subtitle: Text(exclusion.reasons.map(_reason).join(' • ')),
                ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _step(
    AccountSequenceStep step,
    Map<String, PropFirmAccount> accounts,
  ) {
    final account = accounts[step.accountId];
    final alternatives = step.alternativeAccountIds
        .map((id) => accounts[id]?.name ?? id)
        .join(', ');
    return Semantics(
      label: '${step.position}. ${account?.name ?? step.accountId}',
      child: Container(
        constraints: const BoxConstraints(minWidth: 190, maxWidth: 260),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: step.status == SequenceStepStatus.active
                ? Theme.of(context).colorScheme.primary
                : Theme.of(context).colorScheme.outlineVariant,
            width: step.status == SequenceStepStatus.active ? 2 : 1,
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '${step.position}. ${account?.name ?? step.accountId}',
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            Text(
              '${_roleLabel(step.role)} • ${step.healthScoreAtGeneration.toStringAsFixed(0)}/100',
            ),
            Text(_statusLabel(step.status)),
            if (alternatives.isNotEmpty)
              Text(
                context.l10n.text('Alternative at similar health: {accounts}', {
                  'accounts': alternatives,
                }),
              ),
          ],
        ),
      ),
    );
  }

  Widget _actionBar(AccountSequencePlan plan) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        FilledButton.icon(
          key: const Key('sequence-confirm'),
          onPressed: _working || plan.steps.isEmpty || plan.portfolioStopped
              ? null
              : () => _run(controller.confirmSequence),
          icon: const Icon(Icons.check_circle_outline),
          label: Text(context.l10n.text('Confirm sequence')),
        ),
        FilledButton.tonalIcon(
          key: const Key('sequence-start'),
          onPressed:
              _working || plan.confirmedAt == null || portfolio.sessionActive
              ? null
              : () => _run(controller.startSequenceSession),
          icon: const Icon(Icons.play_arrow),
          label: Text(context.l10n.text('Start sequence session')),
        ),
        OutlinedButton.icon(
          key: const Key('sequence-customize'),
          onPressed: _working ? null : () => _showCustomEditor(plan),
          icon: const Icon(Icons.reorder),
          label: Text(context.l10n.text('Customise order')),
        ),
        OutlinedButton.icon(
          onPressed: _working ? null : _saveTemplate,
          icon: const Icon(Icons.bookmark_add_outlined),
          label: Text(context.l10n.text('Save as template')),
        ),
      ],
    );
  }

  Widget _sessionCard(
    AccountSequencePlan plan,
    Map<String, PropFirmAccount> accounts,
  ) {
    final currentId = portfolio.activeAccountId;
    final current = accounts[currentId];
    final activeIndex = plan.steps.indexWhere(
      (item) => item.accountId == currentId,
    );
    final next = activeIndex >= 0 && activeIndex + 1 < plan.steps.length
        ? accounts[plan.steps[activeIndex + 1].accountId]
        : null;
    return Card(
      color: Theme.of(context).colorScheme.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Sequence session'),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 8),
            Text(
              context.l10n.text('Current account: {account}', {
                'account': current?.name ?? '—',
              }),
            ),
            Text(
              context.l10n.text('Conditional next account: {account}', {
                'account': next?.name ?? 'Stop and reassess',
              }),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                FilledButton.tonalIcon(
                  onPressed: current == null ? null : () => _planTrade(current),
                  icon: const Icon(Icons.fact_check_outlined),
                  label: Text(context.l10n.text('Plan trade')),
                ),
                OutlinedButton.icon(
                  onPressed: current == null
                      ? null
                      : () => _recordResult(current),
                  icon: const Icon(Icons.edit_note_outlined),
                  label: Text(context.l10n.text('Record result')),
                ),
                OutlinedButton.icon(
                  onPressed: current == null ? null : () => _skip(current),
                  icon: const Icon(Icons.skip_next),
                  label: Text(context.l10n.text('Skip account')),
                ),
                OutlinedButton.icon(
                  onPressed: current == null ? null : () => _pause(current),
                  icon: const Icon(Icons.pause),
                  label: Text(context.l10n.text('Pause account')),
                ),
                OutlinedButton.icon(
                  onPressed: current == null
                      ? null
                      : () => _reviewAccount(current),
                  icon: const Icon(Icons.health_and_safety_outlined),
                  label: Text(context.l10n.text('Reassess account')),
                ),
                TextButton.icon(
                  onPressed: () => _run(controller.stopSequence),
                  icon: const Icon(Icons.stop_circle_outlined),
                  label: Text(context.l10n.text('Stop sequence')),
                ),
                TextButton.icon(
                  onPressed: () => _run(controller.emergencyLock),
                  icon: const Icon(Icons.lock_outline),
                  label: Text(context.l10n.text('Lock portfolio')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _settingsCard(AccountSequenceSettings settings) => Card(
    child: ExpansionTile(
      leading: const Icon(Icons.tune),
      title: Text(context.l10n.text('Sequence rules and result actions')),
      subtitle: Text(
        context.l10n.text(
          'Change ranking mode, tie handling, and what the planner evaluates after TP, SL, or break-even.',
        ),
      ),
      childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: [
        DropdownButtonFormField<SequenceMode>(
          initialValue: settings.mode,
          decoration: InputDecoration(
            labelText: context.l10n.text('Sequence mode'),
          ),
          items: [
            for (final mode in SequenceMode.values)
              DropdownMenuItem(value: mode, child: Text(_modeLabel(mode))),
          ],
          onChanged: (value) {
            if (value != null) {
              _run(
                () => controller.updateSequenceSettings(
                  settings.copyWith(mode: value, oneAccountOnly: false),
                ),
              );
            }
          },
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<SequenceTiePreference>(
          initialValue: settings.tiePreference,
          decoration: InputDecoration(
            labelText: context.l10n.text('Similar-health tie rule'),
          ),
          items: [
            for (final rule in SequenceTiePreference.values)
              DropdownMenuItem(value: rule, child: Text(_tieLabel(rule))),
          ],
          onChanged: (value) {
            if (value != null) {
              _run(
                () => controller.updateSequenceSettings(
                  settings.copyWith(tiePreference: value),
                ),
              );
            }
          },
        ),
        const SizedBox(height: 12),
        _resultActionRows(settings),
      ],
    ),
  );

  Widget _resultActionRows(AccountSequenceSettings settings) => Column(
    children: [
      DropdownButtonFormField<SequenceAfterTpAction>(
        initialValue: settings.afterTpAction,
        decoration: InputDecoration(
          labelText: context.l10n.text('After take profit'),
        ),
        items: [
          for (final item in SequenceAfterTpAction.values)
            DropdownMenuItem(value: item, child: Text(_afterTpLabel(item))),
        ],
        onChanged: (value) {
          if (value != null) {
            _run(
              () => controller.updateSequenceSettings(
                settings.copyWith(afterTpAction: value),
              ),
            );
          }
        },
      ),
      const SizedBox(height: 12),
      DropdownButtonFormField<SequenceAfterSlAction>(
        initialValue: settings.afterSlAction,
        decoration: InputDecoration(
          labelText: context.l10n.text('After stop loss'),
        ),
        items: [
          for (final item in SequenceAfterSlAction.values)
            DropdownMenuItem(value: item, child: Text(_afterSlLabel(item))),
        ],
        onChanged: (value) {
          if (value != null) {
            _run(
              () => controller.updateSequenceSettings(
                settings.copyWith(afterSlAction: value),
              ),
            );
          }
        },
      ),
      const SizedBox(height: 12),
      DropdownButtonFormField<SequenceAfterBreakevenAction>(
        initialValue: settings.afterBreakevenAction,
        decoration: InputDecoration(
          labelText: context.l10n.text('After break-even'),
        ),
        items: [
          for (final item in SequenceAfterBreakevenAction.values)
            DropdownMenuItem(value: item, child: Text(_afterBeLabel(item))),
        ],
        onChanged: (value) {
          if (value != null) {
            _run(
              () => controller.updateSequenceSettings(
                settings.copyWith(afterBreakevenAction: value),
              ),
            );
          }
        },
      ),
    ],
  );

  Widget _comparisonCard(
    AccountSequencePlan plan,
    Map<String, PropFirmAccount> accounts,
  ) => Card(
    child: ExpansionTile(
      leading: const Icon(Icons.compare_arrows),
      title: Text(context.l10n.text('Account health comparison')),
      subtitle: Text(
        context.l10n.text(
          'Scores are explicit decision components, not a hidden ranking.',
        ),
      ),
      childrenPadding: const EdgeInsets.fromLTRB(12, 0, 12, 16),
      children: [
        for (final evaluation in plan.evaluations.values)
          _evaluationTile(evaluation, accounts[evaluation.accountId]),
      ],
    ),
  );

  Widget _evaluationTile(
    AccountSequenceEvaluation evaluation,
    PropFirmAccount? account,
  ) => ExpansionTile(
    key: Key('sequence-score-${evaluation.accountId}'),
    title: Text(account?.name ?? evaluation.accountId),
    subtitle: Text(
      '${evaluation.healthScore.toStringAsFixed(0)}/100 • ${_eligibility(evaluation)} • ${_dataLabel(evaluation.dataCompleteness)}',
    ),
    children: [
      for (final component in evaluation.scoreComponents)
        ListTile(
          dense: true,
          title: Text(_reason(SequenceReason(component.messageKey))),
          trailing: Text(
            '${component.points.toStringAsFixed(1)} / ${component.maximumPoints.toStringAsFixed(0)}',
          ),
        ),
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
        child: Wrap(
          spacing: 12,
          runSpacing: 6,
          children: [
            Text(
              '${context.l10n.text('Daily limit used')}: ${evaluation.dailyLossUsagePercentage.toStringAsFixed(1)}%',
            ),
            Text(
              '${context.l10n.text('Overall limit used')}: ${evaluation.overallLossUsagePercentage.toStringAsFixed(1)}%',
            ),
            Text(
              '${context.l10n.text('Daily room')}: ${_money(evaluation.remainingDailyLossRoom, account?.currency ?? portfolio.currency)}',
            ),
            Text(
              '${context.l10n.text('Overall room')}: ${_money(evaluation.remainingOverallLossRoom, account?.currency ?? portfolio.currency)}',
            ),
          ],
        ),
      ),
    ],
  );

  Widget _explanationsCard(
    AccountSequencePlan plan,
    Map<String, PropFirmAccount> accounts,
  ) => Card(
    child: ExpansionTile(
      leading: const Icon(Icons.lightbulb_outline),
      title: Text(context.l10n.text('Why this sequence?')),
      childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: [
        _explanationSection('Why this account is first', plan.reasons),
        _explanationSection(
          'Why the others follow',
          plan.steps.skip(1).expand((item) => item.reasons).toList(),
        ),
        _explanationSection('What could change the order', [
          const SequenceReason('sequence.branch.reassessPortfolio'),
          const SequenceReason('sequence.update.eligibilityChanged'),
        ]),
        _explanationSection(
          'Why some accounts are excluded',
          plan.exclusions.expand((item) => item.reasons).toList(),
        ),
      ],
    ),
  );

  Widget _explanationSection(String title, List<SequenceReason> reasons) =>
      Padding(
        padding: const EdgeInsets.only(top: 12),
        child: Align(
          alignment: Alignment.centerLeft,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.l10n.text(title),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              if (reasons.isEmpty)
                Text(context.l10n.text('No additional reason recorded.')),
              for (final reason in reasons) Text('• ${_reason(reason)}'),
            ],
          ),
        ),
      );

  Widget _decisionTreeCard(
    AccountSequencePlan plan,
    Map<String, PropFirmAccount> accounts,
  ) => Card(
    child: ExpansionTile(
      leading: const Icon(Icons.account_tree_outlined),
      title: Text(context.l10n.text('Conditional decision tree')),
      subtitle: Text(
        context.l10n.text(
          'Every result returns to safety checks before another account can become active.',
        ),
      ),
      childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: [
        for (final branch in plan.branches)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(
              branch.stopsPortfolio ? Icons.stop_circle : Icons.alt_route,
            ),
            title: Text(_reason(SequenceReason(branch.resultKey))),
            subtitle: Text(
              [
                ...branch.actions.map(_reason),
                if (branch.nextCandidateId != null)
                  context.l10n.text('Conditional candidate: {account}', {
                    'account':
                        accounts[branch.nextCandidateId]?.name ??
                        branch.nextCandidateId,
                  }),
              ].join(' → '),
            ),
          ),
      ],
    ),
  );

  Widget _stopConditionsCard(AccountSequencePlan plan) => Card(
    child: ExpansionTile(
      leading: const Icon(Icons.front_hand_outlined),
      title: Text(context.l10n.text('Portfolio stop conditions')),
      childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      children: [
        for (final condition in plan.stopConditions)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.shield_outlined),
            title: Text(_reason(SequenceReason(condition.messageKey))),
          ),
      ],
    ),
  );

  Widget _dataQualityCard(AccountSequencePlan plan) => Card(
    child: ListTile(
      leading: Icon(
        plan.dataCompleteness == DataCompleteness.completeAccountInformation
            ? Icons.verified_outlined
            : Icons.warning_amber,
      ),
      title: Text(context.l10n.text(_dataLabel(plan.dataCompleteness))),
      subtitle: Text(
        context.l10n.text(
          'Missing non-critical values reduce confidence. Missing critical balance, loss-limit, or risk values block that account.',
        ),
      ),
    ),
  );

  Widget _templatesCard() {
    final templates = controller.sequenceState.templates;
    return Card(
      child: ExpansionTile(
        leading: const Icon(Icons.bookmarks_outlined),
        title: Text(context.l10n.text('Sequence templates')),
        subtitle: Text(
          context.l10n.text('Quick modes and your Firestore-saved sequences.'),
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _modeTemplate('Balanced', SequenceMode.healthiestNext),
              _modeTemplate('Standard A-B-C', SequenceMode.fixedRotation),
              _modeTemplate('Payout protection', SequenceMode.payoutProtection),
              _modeTemplate('Recovery last', SequenceMode.recoveryLast),
              _modeTemplate(
                'Least recently used',
                SequenceMode.leastRecentlyUsed,
              ),
              ActionChip(
                label: Text(context.l10n.text('One account only')),
                onPressed: () => _run(
                  () => controller.updateSequenceSettings(
                    controller.sequenceState.settings.copyWith(
                      oneAccountOnly: true,
                    ),
                  ),
                ),
              ),
            ],
          ),
          if (templates.isNotEmpty) const Divider(height: 28),
          for (final template in templates)
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(template.name),
              subtitle: Text(_modeLabel(template.mode)),
              trailing: TextButton(
                onPressed: () =>
                    _run(() => controller.applySequenceTemplate(template.id)),
                child: Text(context.l10n.text('Apply')),
              ),
            ),
        ],
      ),
    );
  }

  Widget _modeTemplate(String label, SequenceMode mode) => ActionChip(
    label: Text(context.l10n.text(label)),
    onPressed: () => _run(
      () => controller.updateSequenceSettings(
        controller.sequenceState.settings.copyWith(
          mode: mode,
          oneAccountOnly: false,
        ),
      ),
    ),
  );

  Widget _disclaimer() => Card(
    child: ListTile(
      leading: const Icon(Icons.info_outline),
      title: Text(context.l10n.text('Decision support, not trade execution')),
      subtitle: Text(
        context.l10n.text(
          'The sequence is stored in Firestore for planning and review. It does not connect to MT4, MT5, a broker, or a prop firm, and it never places trades automatically.',
        ),
      ),
    ),
  );

  Future<void> _selectAccount(
    PropFirmAccount account,
    AccountSequencePlan plan,
  ) async {
    final evaluation = plan.evaluations[account.id];
    if (evaluation == null) return;
    final payoutOnly =
        evaluation.exclusionReasons.isNotEmpty &&
        evaluation.exclusionReasons.every(
          (item) => item.messageKey == 'sequence.exclusion.payoutProtected',
        );
    if (!evaluation.isEligible && payoutOnly) {
      final reason = await _payoutOverrideDialog(account);
      if (reason != null) {
        await _run(
          () => controller.overrideSequenceAccount(account.id, reason),
        );
      }
      return;
    }
    if (!evaluation.isEligible) {
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(context.l10n.text('Account is not eligible')),
          content: Text(evaluation.exclusionReasons.map(_reason).join('\n')),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(context.l10n.text('Close')),
            ),
            FilledButton(
              onPressed: () {
                Navigator.pop(context);
                _reviewAccount(account);
              },
              child: Text(context.l10n.text('Review account data')),
            ),
          ],
        ),
      );
      return;
    }
    await _run(() => controller.selectSequenceStartingAccount(account.id));
  }

  Future<void> _reviewAccount(PropFirmAccount account) async {
    var draft = await showAccountSituationEditor(
      context,
      account,
      detailed: true,
    );
    while (draft != null && mounted) {
      try {
        await controller.saveAccount(draft);
        return;
      } on Object catch (error) {
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.text('$error'))));
        draft = await showAccountSituationEditor(
          context,
          draft,
          detailed: true,
        );
      }
    }
  }

  Future<void> _planTrade(PropFirmAccount account) async {
    final trade = await showPreTradeAssistant(
      context,
      accounts: portfolio.activeAccounts,
      recommendedAccountId: account.id,
    );
    if (trade == null) return;
    final warnings = await controller.addPlannedTrade(trade);
    if (warnings.isEmpty || !mounted) return;
    final reason = await _textDialog(
      'Combined exposure warning',
      'Record why this plan remains acceptable before overriding the warning.',
    );
    if (reason != null) {
      await _run(
        () => controller.addPlannedTrade(
          trade.copyWith(overrideReason: reason),
          acceptWarnings: true,
        ),
      );
    }
  }

  Future<void> _recordResult(PropFirmAccount account) async {
    final result = await showResultRecorder(
      context,
      accounts: portfolio.activeAccounts,
      openPlans: portfolio.openPlans,
      defaultAccountId: account.id,
    );
    if (result != null) await _run(() => controller.recordResult(result));
  }

  Future<void> _skip(PropFirmAccount account) async {
    final reason = await _skipReasonDialog();
    if (reason != null) {
      await _run(() => controller.skipSequenceAccount(account.id, reason));
    }
  }

  Future<String?> _skipReasonDialog() async {
    var selected = 'No valid setup';
    final detail = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(context.l10n.text('Skip account')),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                initialValue: selected,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Skip reason'),
                ),
                items: [
                  for (final item in const [
                    'No valid setup',
                    'Market session unsuitable',
                    'Account close to daily limit',
                    'Protecting payout',
                    'Emotional condition',
                    'Technical issue',
                    'User preference',
                    'Other',
                  ])
                    DropdownMenuItem(
                      value: item,
                      child: Text(context.l10n.text(item)),
                    ),
                ],
                onChanged: (value) {
                  if (value != null) setDialogState(() => selected = value);
                },
              ),
              const SizedBox(height: 12),
              TextField(
                controller: detail,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Optional detail'),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(context.l10n.text('Cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(
                context,
                detail.text.trim().isEmpty
                    ? selected
                    : '$selected: ${detail.text.trim()}',
              ),
              child: Text(context.l10n.text('Skip account')),
            ),
          ],
        ),
      ),
    );
    detail.dispose();
    return result;
  }

  Future<void> _pause(PropFirmAccount account) async {
    final reason = await _textDialog(
      'Pause account',
      'Record why this account should remain paused until review.',
    );
    if (reason != null) {
      await _run(
        () => controller.overrideAccountHealthStatus(
          account.id,
          ManualAccountHealthStatus.paused,
          reason,
        ),
      );
    }
  }

  Future<void> _saveTemplate() async {
    final name = await _textDialog('Save sequence template', 'Template name');
    if (name != null) await _run(() => controller.saveSequenceTemplate(name));
  }

  Future<String?> _textDialog(String title, String hint) async {
    final textController = TextEditingController();
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text(title)),
        content: TextField(
          key: const Key('sequence-reason-field'),
          controller: textController,
          minLines: 2,
          maxLines: 4,
          decoration: InputDecoration(hintText: context.l10n.text(hint)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(context.l10n.text('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, textController.text.trim()),
            child: Text(context.l10n.text('Save')),
          ),
        ],
      ),
    );
    textController.dispose();
    return result?.trim().isNotEmpty == true ? result!.trim() : null;
  }

  Future<String?> _payoutOverrideDialog(PropFirmAccount account) async {
    final reason = TextEditingController();
    final estimatedAtRisk =
        account.situation.currentPayoutEligibleProfit ??
        account.currentProfit.clamp(0, double.infinity);
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.l10n.text('Override payout protection')),
        content: SizedBox(
          width: 480,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.l10n.text('Estimated payout amount at risk: {amount}', {
                  'amount': _money(estimatedAtRisk, account.currency),
                }),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Text(
                context.l10n.text(
                  'The payout warning remains visible after this override.',
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: reason,
                minLines: 2,
                maxLines: 4,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Override reason (required)'),
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
            onPressed: () => Navigator.pop(context, reason.text.trim()),
            child: Text(context.l10n.text('Confirm override')),
          ),
        ],
      ),
    );
    reason.dispose();
    return result;
  }

  Future<void> _showCustomEditor(AccountSequencePlan plan) async {
    final original = plan.steps.map((item) => item.accountId).toList();
    for (final exclusion in plan.exclusions) {
      if (!original.contains(exclusion.accountId)) {
        original.add(exclusion.accountId);
      }
    }
    final roles = {
      for (final id in original)
        id:
            controller.sequenceState.settings.roles[id] ??
            (id == original.first
                ? SequenceAccountRole.starting
                : SequenceAccountRole.firstBackup),
    };
    final result = await showModalBottomSheet<_CustomSequenceResult>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => _CustomSequenceEditor(
        accounts: {for (final item in portfolio.activeAccounts) item.id: item},
        initialOrder: original,
        initialRoles: roles,
        validate: controller.validateCustomSequence,
      ),
    );
    if (result == null) return;
    await _run(
      () => controller.saveCustomSequence(
        accountIds: result.order,
        roles: result.roles,
        overrideReason: result.overrideReason,
      ),
    );
  }

  Future<void> _run(Future<void> Function() action) async {
    if (_working) return;
    setState(() => _working = true);
    try {
      await action();
    } on Object catch (error) {
      if (mounted) {
        final message = error is FormatException ? error.message : '$error';
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(context.l10n.text(message))));
      }
    } finally {
      if (mounted) setState(() => _working = false);
    }
  }

  Widget _sectionTitle(String title, String subtitle) => Padding(
    padding: const EdgeInsets.fromLTRB(4, 12, 4, 10),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text(title),
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 4),
        Text(context.l10n.text(subtitle)),
      ],
    ),
  );

  Widget _empty(String text) => Padding(
    padding: const EdgeInsets.all(16),
    child: Text(context.l10n.text(text), textAlign: TextAlign.center),
  );

  Widget _pill(String label, Color color) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: .14),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      context.l10n.text(label),
      style: TextStyle(color: color, fontWeight: FontWeight.w700),
    ),
  );

  Widget _meta(String label, String value) =>
      Text('${context.l10n.text(label)}: ${context.l10n.text(value)}');

  Color _statusColor(String status) => status == 'Stopped'
      ? Theme.of(context).colorScheme.error
      : status == 'Session active'
      ? Theme.of(context).colorScheme.tertiary
      : Theme.of(context).colorScheme.primary;

  String _reason(SequenceReason reason) => context.l10n.text(
    _sequenceReasonEnglish[reason.messageKey] ?? reason.messageKey,
    reason.values,
  );

  String _modeLabel(SequenceMode value) => context.l10n.text(switch (value) {
    SequenceMode.healthiestNext => 'Healthiest next',
    SequenceMode.fixedRotation => 'Fixed rotation',
    SequenceMode.payoutProtection => 'Payout protection',
    SequenceMode.recoveryLast => 'Recovery last',
    SequenceMode.leastRecentlyUsed => 'Least recently used',
    SequenceMode.custom => 'Custom order',
  });

  String _tieLabel(SequenceTiePreference value) =>
      context.l10n.text(switch (value) {
        SequenceTiePreference.showAlternatives => 'Show equal alternatives',
        SequenceTiePreference.leastRecentlyUsed => 'Least recently used first',
        SequenceTiePreference.largestDailyLossBuffer =>
          'Largest daily-loss buffer',
        SequenceTiePreference.largestOverallLossBuffer =>
          'Largest overall-loss buffer',
        SequenceTiePreference.customOrder => 'Use custom order',
      });

  String _afterTpLabel(SequenceAfterTpAction value) =>
      _reason(SequenceReason('sequence.afterTp.${value.name}'));
  String _afterSlLabel(SequenceAfterSlAction value) =>
      _reason(SequenceReason('sequence.afterSl.${value.name}'));
  String _afterBeLabel(SequenceAfterBreakevenAction value) =>
      _reason(SequenceReason('sequence.afterBe.${value.name}'));
}

class _StartingAccountCard extends StatelessWidget {
  const _StartingAccountCard({
    required this.account,
    required this.evaluation,
    required this.selected,
    required this.recommended,
    required this.onSelect,
    required this.onReview,
  });

  final PropFirmAccount account;
  final AccountSequenceEvaluation evaluation;
  final bool selected;
  final bool recommended;
  final VoidCallback onSelect;
  final VoidCallback onReview;

  @override
  Widget build(BuildContext context) {
    final color = evaluation.isEligible
        ? evaluation.isEligibleWithReducedRisk
              ? Colors.orange
              : Colors.green
        : Theme.of(context).colorScheme.error;
    return Card(
      key: Key('sequence-account-${account.id}'),
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: BorderSide(
          color: selected
              ? Theme.of(context).colorScheme.primary
              : Theme.of(context).colorScheme.outlineVariant,
          width: selected ? 2 : 1,
        ),
      ),
      child: InkWell(
        onTap: onSelect,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      account.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  if (recommended) const Icon(Icons.auto_awesome, size: 20),
                ],
              ),
              Text(account.propFirmName),
              if (account.identifier.isNotEmpty) Text(account.identifier),
              const Divider(),
              _two(
                'Balance',
                _money(account.currentBalance, account.currency),
                'Current P/L',
                _money(account.currentProfit, account.currency),
                context,
              ),
              _two(
                'P/L today',
                _money(
                  account.situation.todayRealisedProfitLoss,
                  account.currency,
                ),
                'Payout status',
                _payoutStatusLabel(account.situation.payoutRequestStatus),
                context,
              ),
              _two(
                'Daily used',
                '${evaluation.dailyLossUsagePercentage.toStringAsFixed(1)}%',
                'Overall used',
                '${evaluation.overallLossUsagePercentage.toStringAsFixed(1)}%',
                context,
              ),
              _two(
                'Daily room',
                _money(evaluation.remainingDailyLossRoom, account.currency),
                'Overall room',
                _money(evaluation.remainingOverallLossRoom, account.currency),
                context,
              ),
              _two(
                'Payout profit',
                _money(
                  account.situation.currentPayoutEligibleProfit ?? 0,
                  account.currency,
                ),
                'Health score',
                '${evaluation.healthScore.toStringAsFixed(0)}/100',
                context,
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 6,
                runSpacing: 6,
                children: [
                  Chip(
                    label: Text(
                      context.l10n.text(_healthLabel(evaluation.healthStatus)),
                    ),
                    visualDensity: VisualDensity.compact,
                  ),
                  Chip(
                    avatar: Icon(
                      evaluation.isEligible ? Icons.check : Icons.block,
                      size: 17,
                      color: color,
                    ),
                    label: Text(context.l10n.text(_eligibility(evaluation))),
                    visualDensity: VisualDensity.compact,
                  ),
                ],
              ),
              if (evaluation.exclusionReasons.isNotEmpty)
                TextButton.icon(
                  onPressed: onReview,
                  icon: const Icon(Icons.manage_search),
                  label: Text(context.l10n.text('Review restriction')),
                ),
            ],
          ),
        ),
      ),
    );
  }

  static Widget _two(
    String a,
    String av,
    String b,
    String bv,
    BuildContext context,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Expanded(child: Text('${context.l10n.text(a)}\n$av')),
        const SizedBox(width: 8),
        Expanded(child: Text('${context.l10n.text(b)}\n$bv')),
      ],
    ),
  );
}

class _CustomSequenceResult {
  const _CustomSequenceResult(this.order, this.roles, this.overrideReason);
  final List<String> order;
  final Map<String, SequenceAccountRole> roles;
  final String overrideReason;
}

class _CustomSequenceEditor extends StatefulWidget {
  const _CustomSequenceEditor({
    required this.accounts,
    required this.initialOrder,
    required this.initialRoles,
    required this.validate,
  });

  final Map<String, PropFirmAccount> accounts;
  final List<String> initialOrder;
  final Map<String, SequenceAccountRole> initialRoles;
  final SequenceValidationResult Function(List<String>) validate;

  @override
  State<_CustomSequenceEditor> createState() => _CustomSequenceEditorState();
}

class _CustomSequenceEditorState extends State<_CustomSequenceEditor> {
  late final List<String> order = [...widget.initialOrder];
  late final Map<String, SequenceAccountRole> roles = {...widget.initialRoles};
  final reason = TextEditingController();

  @override
  void dispose() {
    reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final validation = widget.validate(order);
    return SafeArea(
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * .86,
        child: Column(
          children: [
            ListTile(
              title: Text(
                context.l10n.text('Custom account order'),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              subtitle: Text(
                context.l10n.text(
                  'Drag accounts, assign roles, then review every safety warning.',
                ),
              ),
            ),
            Expanded(
              child: ReorderableListView.builder(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                itemCount: order.length,
                onReorder: (oldIndex, newIndex) {
                  setState(() {
                    if (newIndex > oldIndex) newIndex--;
                    final item = order.removeAt(oldIndex);
                    order.insert(newIndex, item);
                  });
                },
                itemBuilder: (context, index) {
                  final id = order[index];
                  final account = widget.accounts[id];
                  return Card(
                    key: ValueKey(id),
                    child: ListTile(
                      leading: CircleAvatar(child: Text('${index + 1}')),
                      title: Text(account?.name ?? id),
                      subtitle: DropdownButton<SequenceAccountRole>(
                        value: roles[id] ?? SequenceAccountRole.unavailable,
                        isExpanded: true,
                        items: [
                          for (final role in SequenceAccountRole.values)
                            DropdownMenuItem(
                              value: role,
                              child: Text(context.l10n.text(_roleLabel(role))),
                            ),
                        ],
                        onChanged: (value) {
                          if (value != null) setState(() => roles[id] = value);
                        },
                      ),
                      trailing: const Icon(Icons.drag_handle),
                    ),
                  );
                },
              ),
            ),
            if (validation.blockingReasons.isNotEmpty ||
                validation.warnings.isNotEmpty)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                color: validation.blockingReasons.isNotEmpty
                    ? Theme.of(context).colorScheme.errorContainer
                    : Theme.of(context).colorScheme.tertiaryContainer,
                child: Text(
                  [
                    ...validation.blockingReasons.map(
                      (item) => _reasonText(context, item),
                    ),
                    ...validation.warnings.map(
                      (item) => _reasonText(context, item),
                    ),
                  ].join('\n'),
                ),
              ),
            if (validation.requiresOverride)
              Padding(
                padding: const EdgeInsets.all(12),
                child: TextField(
                  key: const Key('custom-sequence-override-reason'),
                  controller: reason,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    labelText: context.l10n.text('Required override reason'),
                  ),
                ),
              ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: Text(context.l10n.text('Cancel')),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    key: const Key('save-custom-sequence'),
                    onPressed:
                        !validation.canSave ||
                            (validation.requiresOverride &&
                                reason.text.trim().length < 5)
                        ? null
                        : () => Navigator.pop(
                            context,
                            _CustomSequenceResult(
                              [...order],
                              {...roles},
                              reason.text.trim(),
                            ),
                          ),
                    child: Text(context.l10n.text('Save custom sequence')),
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

String _reasonText(BuildContext context, SequenceReason reason) =>
    context.l10n.text(
      _sequenceReasonEnglish[reason.messageKey] ?? reason.messageKey,
      reason.values,
    );

String _money(double value, String currency) =>
    '$currency ${value.toStringAsFixed(2)}';
String _date(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}-${value.month.toString().padLeft(2, '0')}-${value.year}';
String _dateTime(DateTime value) =>
    '${_date(value)} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';

String _eligibility(AccountSequenceEvaluation value) => value.isEligible
    ? value.isEligibleWithReducedRisk
          ? 'Eligible with reduced risk'
          : 'Eligible'
    : 'Ineligible';

String _dataLabel(DataCompleteness value) => switch (value) {
  DataCompleteness.completeAccountInformation => 'Complete account information',
  DataCompleteness.partiallyCompleteAccountInformation =>
    'Partially complete account information',
  DataCompleteness.insufficientAccountInformation =>
    'Insufficient account information',
};

String _roleLabel(SequenceAccountRole value) => switch (value) {
  SequenceAccountRole.starting => 'Starting account',
  SequenceAccountRole.firstBackup => 'First backup',
  SequenceAccountRole.secondBackup => 'Second backup',
  SequenceAccountRole.recovery => 'Recovery account',
  SequenceAccountRole.payoutProtected => 'Payout protected',
  SequenceAccountRole.emergencyOnly => 'Emergency only',
  SequenceAccountRole.unavailable => 'Unavailable',
};

String _statusLabel(SequenceStepStatus value) => switch (value) {
  SequenceStepStatus.pending => 'Pending reassessment',
  SequenceStepStatus.active => 'Active now',
  SequenceStepStatus.completed => 'Completed',
  SequenceStepStatus.skipped => 'Skipped',
  SequenceStepStatus.blocked => 'Blocked',
  SequenceStepStatus.stopped => 'Stopped',
};

String _healthLabel(AccountHealthStatus value) => switch (value) {
  AccountHealthStatus.healthy => 'Healthy',
  AccountHealthStatus.caution => 'Caution',
  AccountHealthStatus.highRisk => 'High risk',
  AccountHealthStatus.critical => 'Critical',
  AccountHealthStatus.nearDailyLossLimit => 'Near daily loss limit',
  AccountHealthStatus.nearMaximumLossLimit => 'Near maximum loss limit',
  AccountHealthStatus.dailyLocked => 'Daily locked',
  AccountHealthStatus.recovery => 'Recovery',
  AccountHealthStatus.payoutProgress => 'Payout progress',
  AccountHealthStatus.payoutEligible => 'Payout eligible',
  AccountHealthStatus.waitingForPayoutDate => 'Waiting for payout date',
  AccountHealthStatus.payoutProtection => 'Payout protection',
  AccountHealthStatus.paused => 'Paused',
  AccountHealthStatus.breached => 'Breached',
  AccountHealthStatus.archived => 'Archived',
};

String _payoutStatusLabel(PayoutRequestStatus? value) => switch (value) {
  PayoutRequestStatus.profitIncomplete => 'Profit incomplete',
  PayoutRequestStatus.tradingDaysIncomplete => 'Trading days incomplete',
  PayoutRequestStatus.waitingForDate => 'Waiting for payout date',
  PayoutRequestStatus.eligibleToday => 'Payout eligible today',
  PayoutRequestStatus.protected => 'Payout protected',
  PayoutRequestStatus.requestSubmitted => 'Payout request submitted',
  PayoutRequestStatus.paid => 'Paid',
  PayoutRequestStatus.rejectedOrDelayed => 'Payout rejected or delayed',
  PayoutRequestStatus.userCancelled => 'Payout request cancelled',
  null => 'Not set',
};

const _sequenceReasonEnglish = <String, String>{
  'sequence.reason.userStartingAccount':
      'The eligible account selected by the user remains first.',
  'sequence.reason.systemStartingAccount':
      'The system selected the healthiest eligible starting account.',
  'sequence.reason.alternativeStartingAccount':
      '{account} is the safest eligible alternative starting account.',
  'sequence.reason.reducedRiskStarting':
      '{account} is eligible only with reduced risk after using {percent}% of its daily limit.',
  'sequence.reason.requestedUnavailable':
      'Requested {requested} is unavailable; {alternative} is the safest eligible replacement.',
  'sequence.reason.healthiestRemaining':
      '{account} has the strongest eligible health score.',
  'sequence.reason.normalizedCurrencyComparison':
      'Accounts with different sizes or currencies are compared by normalized limit percentages.',
  'sequence.reason.rankedByHealth':
      'Health score {score} determines this position after hard safety rules.',
  'sequence.reason.similarHealthAlternative':
      'One or more accounts have a similar health score and are shown as alternatives.',
  'sequence.reason.recoveryPlacedLast':
      'Recovery accounts are placed after healthier eligible accounts.',
  'sequence.reason.noEligibleAccount': 'No account is currently eligible.',
  'sequence.reason.portfolioStopped':
      'An existing portfolio stop prevents a sequence.',
  'sequence.positive.dailyBuffer':
      '{percent}% of the daily loss limit remains available.',
  'sequence.positive.overallBuffer':
      '{percent}% of the maximum loss limit remains available.',
  'sequence.positive.requirementDays': '{count} required trading days remain.',
  'sequence.risk.dailyUsage':
      '{percent}% of the daily loss limit is already used.',
  'sequence.risk.overallUsage':
      '{percent}% of the maximum loss limit is already used.',
  'sequence.risk.lossStreak': 'The account has {count} consecutive losses.',
  'sequence.risk.emotionalState':
      'A psychology or discipline flag requires review.',
  'sequence.risk.incompleteData':
      'Confidence is reduced because account information is incomplete.',
  'sequence.risk.recoveryAccount':
      'This account is in recovery and should not be used to chase losses.',
  'sequence.risk.payoutOverride':
      'Payout protection was overridden with a recorded reason.',
  'sequence.exclusion.portfolioStop': 'Portfolio stop is active.',
  'sequence.exclusion.breached': 'Account is breached.',
  'sequence.exclusion.critical':
      'Account is critical and excluded by the safety threshold.',
  'sequence.exclusion.archived': 'Account is archived.',
  'sequence.exclusion.dailyLocked': 'Daily loss lock is active.',
  'sequence.exclusion.accountPortfolioLocked':
      'Account is locked by the portfolio.',
  'sequence.exclusion.paused': 'Account is manually paused.',
  'sequence.exclusion.cooldown': 'Cooldown has not ended.',
  'sequence.exclusion.tradeLimitReached':
      'Daily trade limit reached ({current}/{limit}).',
  'sequence.exclusion.dailyRoomTooSmall':
      'Remaining daily-loss room ({amount}) is below planned risk.',
  'sequence.exclusion.overallRoomTooSmall':
      'Remaining maximum-loss room ({amount}) is below planned risk.',
  'sequence.exclusion.openPortfolioExposure':
      'Another open position prevents simultaneous trading.',
  'sequence.exclusion.skipped': 'Skipped for this session: {reason}',
  'sequence.exclusion.manualUnavailable':
      'Account is marked not available today.',
  'sequence.exclusion.manualExcluded':
      'Account is manually excluded from the sequence.',
  'sequence.exclusion.payoutProtected': 'Account is protected for payout.',
  'sequence.exclusion.invalidAccountSize':
      'Account size is missing or invalid.',
  'sequence.exclusion.invalidStartingBalance':
      'Starting balance is missing or invalid.',
  'sequence.exclusion.invalidCurrentBalance':
      'Current balance is missing or invalid.',
  'sequence.exclusion.missingDailyLimit': 'Daily loss limit is missing.',
  'sequence.exclusion.missingMaximumLimit': 'Maximum loss limit is missing.',
  'sequence.exclusion.invalidRisk':
      'Maximum risk per trade is missing or invalid.',
  'sequence.exclusion.missingRequiredEquity':
      'Required equity information is missing.',
  'sequence.score.dailyBuffer': 'Daily-loss buffer',
  'sequence.score.overallBuffer': 'Maximum-loss buffer',
  'sequence.score.payoutCondition': 'Payout condition',
  'sequence.score.lossStreak': 'Recent loss streak',
  'sequence.score.tradeCount': 'Trades already taken',
  'sequence.score.psychologyCooldown': 'Psychology and cooldown',
  'sequence.validation.uniqueAccountsRequired':
      'Every account may appear only once.',
  'sequence.validation.unknownAccount':
      'The order contains an unknown account.',
  'sequence.validation.payoutProtectedIncluded':
      '{account} is payout-protected and requires an override reason.',
  'sequence.validation.higherRiskBeforeHealthy':
      '{higherRisk} is placed before the materially healthier {healthier}.',
  'sequence.validation.recoveryBeforeHealthy':
      'Recovery account {recovery} is placed before healthier {healthier}.',
  'sequence.update.initialGeneration': 'Initial sequence generated.',
  'sequence.update.recheckedNoOrderChange':
      'Safety checks were refreshed; the order did not change.',
  'sequence.update.healthChanged':
      '{account} moved because daily-loss usage changed from {before}% to {after}%.',
  'sequence.update.eligibilityChanged':
      'Eligibility changed, so the sequence was recalculated.',
  'sequence.branch.tp': 'After take profit',
  'sequence.branch.sl': 'After stop loss',
  'sequence.branch.breakeven': 'After break-even',
  'sequence.branch.dailyTarget': 'After daily target',
  'sequence.branch.portfolioStop': 'If a portfolio stop is triggered',
  'sequence.branch.reassessNext': 'Reassess the next candidate before trading.',
  'sequence.branch.reassessPortfolio':
      'Reassess all portfolio loss and discipline limits.',
  'sequence.branch.nextOnlyIfAllowed':
      'Continue only when every hard safety rule allows it.',
  'sequence.branch.protectCurrent': 'Protect the current account for the day.',
  'sequence.branch.stopAll': 'Stop all account trading.',
  'sequence.afterTp.moveToNext': 'Move to the next account after reassessment.',
  'sequence.afterTp.keepAfterCooldown': 'Keep the account after its cooldown.',
  'sequence.afterTp.protectForDay': 'Protect the winning account for the day.',
  'sequence.afterTp.stopAfterDailyTarget':
      'Stop when the daily target is reached.',
  'sequence.afterTp.recalculate': 'Recalculate the remaining sequence.',
  'sequence.afterSl.lockForDay': 'Lock the losing account for the day.',
  'sequence.afterSl.startCooldown': 'Start the loss cooldown.',
  'sequence.afterSl.psychologyCheckThenNext':
      'Complete a psychology check, then reassess the next account.',
  'sequence.afterSl.stopPortfolio': 'Stop the whole portfolio.',
  'sequence.afterSl.recalculateRemainingRisk':
      'Recalculate remaining portfolio risk.',
  'sequence.afterBe.keepCurrent': 'Keep the current account if still eligible.',
  'sequence.afterBe.moveToNext': 'Move to the next account after reassessment.',
  'sequence.afterBe.doNotCountStep':
      'Do not count this as a completed sequence step.',
  'sequence.afterBe.recalculate': 'Recalculate the remaining sequence.',
  'sequence.stop.portfolioDailyLoss':
      'Portfolio daily loss limit has been reached.',
  'sequence.stop.maximumTrades': 'Maximum portfolio trades have been reached.',
  'sequence.stop.consecutiveLosses':
      'Maximum consecutive losses have been reached.',
  'sequence.stop.emotionalRisk': 'An emotional-risk stop has been triggered.',
  'sequence.stop.repeatedInvalidSetup':
      'Repeated invalid setups require the session to stop.',
  'sequence.stop.payoutProtectedOnly': 'Only payout-protected accounts remain.',
  'sequence.stop.noEligibleAccount': 'No eligible account remains.',
  'sequence.stop.plannedRiskTooLarge':
      'Planned risk is larger than every remaining safe buffer.',
  'sequence.stop.manualSessionStop':
      'The user may stop the session at any time.',
};
