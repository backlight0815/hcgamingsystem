import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../../core/localization/app_localizations.dart';
import '../../core/localization/localized_value.dart';
import '../../core/localization/publishing_localization.dart';
import '../../core/network/api_client.dart';
import '../../core/security/publishing_authorization.dart';
import '../../models/app_user.dart';
import '../../models/mt5_import_models.dart';
import '../../models/trading_signal_item.dart';
import '../../models/trading_signal_lifecycle.dart';
import '../../repositories/trading_repository.dart';
import '../../shared/app_feedback.dart';
import '../../shared/content_language_selector.dart';
import '../../shared/formatters.dart';

class TradingSignalManagementScreen extends StatelessWidget {
  const TradingSignalManagementScreen({
    super.key,
    required this.user,
    required this.repository,
    required this.signalId,
  });

  final AppUser user;
  final TradingRepository repository;
  final String signalId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.l10n.text('Signal operations'))),
      body: StreamBuilder<TradingSignalItem?>(
        stream: repository.tradingSignalStream(signalId),
        builder: (context, snapshot) {
          if (!snapshot.hasData && !snapshot.hasError) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return SignalManagementEmptyState(
              icon: Icons.cloud_off_outlined,
              title: context.l10n.text('Unable to load this signal'),
              message: context.l10n.text(
                'Check your connection and permissions, then try again.',
              ),
            );
          }
          final signal = snapshot.data;
          if (signal == null) {
            return SignalManagementEmptyState(
              icon: Icons.search_off_outlined,
              title: context.l10n.text('Signal not found'),
              message: context.l10n.text(
                'It may have been removed or you may no longer have access.',
              ),
            );
          }
          final authorization = PublishingAuthorization(user);
          final ownsSignal = signal.isOperableBy(user.uid);
          final canManage =
              authorization.canManageAllTradingSignals ||
              authorization.canUpdateOwnTradingSignals && ownsSignal;
          if (!canManage) {
            return SignalManagementEmptyState(
              icon: Icons.lock_outline,
              title: context.l10n.text('This signal is read-only'),
              message: context.l10n.text(
                'Signal Providers can update only signals created by or assigned to their account.',
              ),
            );
          }
          return LayoutBuilder(
            builder: (context, constraints) {
              final wide = constraints.maxWidth >= 980;
              final header = SignalOperationsHeader(signal: signal);
              final actions = SignalQuickActions(
                signal: signal,
                superAdmin: user.isSuperAdmin,
                onSelected: (type) => _openUpdate(context, signal, type),
              );
              final timeline = SignalUpdateTimeline(
                repository: repository,
                signalId: signal.id,
              );
              return RefreshIndicator(
                onRefresh: () async =>
                    Future<void>.delayed(const Duration(milliseconds: 250)),
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 36),
                  children: [
                    header,
                    const SizedBox(height: 16),
                    if (wide)
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(flex: 4, child: actions),
                          const SizedBox(width: 16),
                          Expanded(flex: 6, child: timeline),
                        ],
                      )
                    else ...[
                      actions,
                      const SizedBox(height: 16),
                      timeline,
                    ],
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }

  Future<void> _openUpdate(
    BuildContext context,
    TradingSignalItem signal,
    SignalUpdateType type,
  ) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => SignalUpdateDialog(
        user: user,
        repository: repository,
        signal: signal,
        updateType: type,
      ),
    );
    if (saved == true && context.mounted) {
      AppFeedback.success(
        context,
        context.l10n.text('Signal update recorded.'),
      );
    }
  }
}

class SignalOperationsHeader extends StatelessWidget {
  const SignalOperationsHeader({super.key, required this.signal});

  final TradingSignalItem signal;

  @override
  Widget build(BuildContext context) {
    final published =
        signal.publishedAt ?? signal.triggerTime ?? signal.createdAt;
    final age = published == null ? null : DateTime.now().difference(published);
    final currentStop = signal.currentStopLoss.trim().isNotEmpty
        ? signal.currentStopLoss
        : signal.stopLoss;
    return Card(
      key: const Key('signal-operations-header'),
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Wrap(
              spacing: 10,
              runSpacing: 10,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(
                  signal.tradingPair,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                _StatusBadge(label: signal.direction),
                _StatusBadge(
                  label: signal.orderType.isEmpty
                      ? SignalOrderType.marketExecution.label
                      : SignalOrderType.parse(signal.orderType).label,
                ),
                _StatusBadge(
                  label: signal.lifecycle.label,
                  highlighted: signal.lifecycle.isOpen,
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              '${signal.signalCode.isEmpty ? signal.id : signal.signalCode} · '
              '${signal.analyst.isEmpty ? context.l10n.text('Signal provider') : signal.analyst}',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const Divider(height: 26),
            Wrap(
              spacing: 24,
              runSpacing: 16,
              children: [
                _Metric(label: 'Entry', value: signal.entryPrice),
                _Metric(
                  label: 'Original SL',
                  value: signal.originalStopLoss.isEmpty
                      ? signal.stopLoss
                      : signal.originalStopLoss,
                ),
                _Metric(label: 'Current SL', value: currentStop),
                _Metric(
                  label: 'Break even',
                  value: signal.breakEvenPrice.isEmpty
                      ? '—'
                      : signal.breakEvenPrice,
                ),
                _Metric(
                  label: 'Target progress',
                  value: '${signal.targetsHit.length}/${signal.targets.length}',
                ),
                _Metric(
                  label: 'Position remaining',
                  value: '${signal.percentageRemaining.toStringAsFixed(0)}%',
                ),
                _Metric(
                  label: 'Risk',
                  value: signal.riskLevel.isEmpty ? '—' : signal.riskLevel,
                ),
                _Metric(
                  label: 'Published',
                  value: published == null ? '—' : formatDateTime(published),
                ),
                _Metric(
                  label: 'Age',
                  value: age == null ? '—' : _ageLabel(age),
                ),
                _Metric(label: 'Version', value: 'v${signal.version}'),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class SignalQuickActions extends StatelessWidget {
  const SignalQuickActions({
    super.key,
    required this.signal,
    required this.superAdmin,
    required this.onSelected,
  });

  final TradingSignalItem signal;
  final bool superAdmin;
  final ValueChanged<SignalUpdateType> onSelected;

  @override
  Widget build(BuildContext context) {
    final actions = SignalLifecyclePolicy.allowedActions(
      signal.lifecycleSnapshot,
      superAdmin: superAdmin,
    );
    return Card(
      key: const Key('signal-quick-actions'),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                context.l10n.text('Available actions'),
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 4),
              Text(
                context.l10n.text(
                  'Actions are limited by the current lifecycle state.',
                ),
              ),
              const SizedBox(height: 14),
              if (actions.isEmpty)
                Text(context.l10n.text('No updates are available.'))
              else
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final action in actions)
                      ActionChip(
                        key: Key('signal-action-${action.value}'),
                        avatar: Icon(_actionIcon(action), size: 18),
                        label: Text(
                          context.l10n.text(
                            _contextActionLabel(signal, action),
                          ),
                        ),
                        onPressed: () => onSelected(action),
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

class SignalUpdateTimeline extends StatelessWidget {
  const SignalUpdateTimeline({
    super.key,
    required this.repository,
    required this.signalId,
  });

  final TradingRepository repository;
  final String signalId;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
    return Card(
      key: const Key('signal-update-timeline'),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              context.l10n.text('Update timeline'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),
            StreamBuilder<List<TradingSignalUpdate>>(
              stream: repository.tradingSignalUpdatesStream(signalId),
              builder: (context, snapshot) {
                if (!snapshot.hasData && !snapshot.hasError) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return Text(
                    context.l10n.text(
                      'Signal updates are temporarily unavailable.',
                    ),
                  );
                }
                final updates = snapshot.data ?? const [];
                if (updates.isEmpty) {
                  return Text(context.l10n.text('No updates recorded yet.'));
                }
                return Column(
                  children: [
                    for (var index = 0; index < updates.length; index++)
                      SignalTimelineEntry(
                        update: updates[index],
                        language: language,
                        last: index == updates.length - 1,
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class SignalUpdateDialog extends StatefulWidget {
  const SignalUpdateDialog({
    super.key,
    required this.user,
    required this.repository,
    required this.signal,
    required this.updateType,
  });

  final AppUser user;
  final TradingRepository repository;
  final TradingSignalItem signal;
  final SignalUpdateType updateType;

  @override
  State<SignalUpdateDialog> createState() => _SignalUpdateDialogState();
}

class _SignalUpdateDialogState extends State<SignalUpdateDialog> {
  final _formKey = GlobalKey<FormState>();
  final _reason = TextEditingController();
  final _message = TextEditingController();
  final _messageSecondary = TextEditingController();
  final _price = TextEditingController();
  final _price2 = TextEditingController();
  final _percentageClosed = TextEditingController();
  final _percentageRemaining = TextEditingController();
  final _pips = TextEditingController();
  final _return = TextEditingController();
  final _offset = TextEditingController();
  final _correctedUpdateId = TextEditingController();
  final _timeframe = TextEditingController();
  late final String _idempotencyKey;
  late PublishingLanguage _language;
  SignalLifecycleStatus? _override;
  int? _targetNumber;
  PlatformFile? _attachment;
  String? _uploadedUrl;
  bool _notify = true;
  bool _addTranslation = false;
  bool _entryWasTriggered = false;
  late bool _isCorrection;
  DateTime _effectiveAt = DateTime.now();
  DateTime? _expiryAt;
  String? _riskLevel;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _idempotencyKey =
        '${widget.signal.id}-${widget.signal.version + 1}-'
        '${widget.updateType.value}-${DateTime.now().microsecondsSinceEpoch}';
    _language = widget.signal.primaryLanguage.toLowerCase().startsWith('zh')
        ? PublishingLanguage.simplifiedChinese
        : PublishingLanguage.english;
    _isCorrection = widget.updateType == SignalUpdateType.correction;
    if (widget.updateType == SignalUpdateType.detailsUpdated) {
      _message.text = widget.signal.title;
      _timeframe.text = widget.signal.timeframe;
      _riskLevel = widget.signal.riskLevel.isEmpty
          ? null
          : widget.signal.riskLevel;
      _expiryAt = widget.signal.expiryTime;
    }
    _targetNumber =
        widget.signal.lifecycleSnapshot.nextTargetNumber <=
            widget.signal.targets.length
        ? widget.signal.lifecycleSnapshot.nextTargetNumber
        : null;
  }

  @override
  void dispose() {
    _reason.dispose();
    _message.dispose();
    _messageSecondary.dispose();
    _price.dispose();
    _price2.dispose();
    _percentageClosed.dispose();
    _percentageRemaining.dispose();
    _pips.dispose();
    _return.dispose();
    _offset.dispose();
    _correctedUpdateId.dispose();
    _timeframe.dispose();
    super.dispose();
  }

  bool get _needsStop => const {
    SignalUpdateType.updateStopLoss,
    SignalUpdateType.trailingStopLoss,
    SignalUpdateType.lockProfit,
    SignalUpdateType.reduceRisk,
  }.contains(widget.updateType);

  bool get _needsBreakEven => const {
    SignalUpdateType.moveStopLossToBreakEven,
    SignalUpdateType.updateBreakEven,
    SignalUpdateType.trailingBreakEven,
  }.contains(widget.updateType);

  bool get _needsEntry => const {
    SignalUpdateType.entryTriggered,
    SignalUpdateType.entryPriceUpdated,
  }.contains(widget.updateType);

  bool get _isTarget => widget.updateType == SignalUpdateType.targetHit;
  bool get _isPartial => const {
    SignalUpdateType.partialClose,
    SignalUpdateType.partialProfitTaken,
  }.contains(widget.updateType);
  bool get _needsEntryRange =>
      widget.updateType == SignalUpdateType.entryRangeUpdated;
  bool get _isDetails => widget.updateType == SignalUpdateType.detailsUpdated;
  bool get _isCancellation => const {
    SignalUpdateType.cancelled,
    SignalUpdateType.removePendingOrder,
    SignalUpdateType.invalidated,
  }.contains(widget.updateType);

  @override
  Widget build(BuildContext context) {
    return Dialog(
      insetPadding: const EdgeInsets.all(16),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 680, maxHeight: 820),
        child: Form(
          key: _formKey,
          child: Column(
            children: [
              ListTile(
                leading: CircleAvatar(
                  child: Icon(_actionIcon(widget.updateType)),
                ),
                title: Text(
                  context.l10n.text(
                    _contextActionLabel(widget.signal, widget.updateType),
                  ),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                subtitle: Text(
                  '${widget.signal.tradingPair} · v${widget.signal.version}',
                ),
                trailing: IconButton(
                  onPressed: _saving ? null : () => Navigator.pop(context),
                  icon: const Icon(Icons.close),
                ),
              ),
              const Divider(height: 1),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(18),
                  children: [
                    if (widget.updateType.isCommentary || _isDetails) ...[
                      ContentLanguageSelector(
                        primaryLanguage: _language,
                        onChanged: (value) => setState(() => _language = value),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _message,
                        minLines: 4,
                        maxLines: 8,
                        decoration: InputDecoration(
                          labelText: context.l10n.text(
                            _isDetails
                                ? _language ==
                                          PublishingLanguage.simplifiedChinese
                                      ? 'Signal title (Simplified Chinese)'
                                      : 'Signal title (English)'
                                : _language ==
                                      PublishingLanguage.simplifiedChinese
                                ? 'Update (Simplified Chinese)'
                                : 'Update (English)',
                          ),
                          helperText: context.l10n.text(
                            'The app displays this language with its normal fallback behavior.',
                          ),
                        ),
                        validator: _isDetails
                            ? null
                            : (value) => value?.trim().isEmpty == true
                                  ? context.l10n.text(
                                      'Enter the update message.',
                                    )
                                  : null,
                      ),
                      const SizedBox(height: 8),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: _addTranslation,
                        onChanged: _saving
                            ? null
                            : (value) => setState(
                                () => _addTranslation = value ?? false,
                              ),
                        title: Text(
                          context.l10n.text(
                            _language == PublishingLanguage.english
                                ? 'Add Simplified Chinese translation'
                                : 'Add English translation',
                          ),
                        ),
                      ),
                      if (_addTranslation)
                        TextFormField(
                          controller: _messageSecondary,
                          minLines: 3,
                          maxLines: 7,
                          decoration: InputDecoration(
                            labelText: context.l10n.text(
                              _isDetails
                                  ? _language == PublishingLanguage.english
                                        ? 'Signal title (Simplified Chinese, optional)'
                                        : 'Signal title (English, optional)'
                                  : _language == PublishingLanguage.english
                                  ? 'Update (Simplified Chinese, optional)'
                                  : 'Update (English, optional)',
                            ),
                          ),
                        ),
                    ],
                    if (_isDetails) ...[
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _timeframe,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Timeframe'),
                        ),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: _riskLevel,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Risk level'),
                        ),
                        items: [
                          for (final risk in const ['Low', 'Medium', 'High'])
                            DropdownMenuItem(
                              value: risk,
                              child: Text(context.l10n.text(risk)),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => _riskLevel = value),
                      ),
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.event_busy_outlined),
                        title: Text(context.l10n.text('Signal expiry')),
                        subtitle: Text(
                          _expiryAt == null
                              ? context.l10n.text('No expiry selected')
                              : formatDateTime(_expiryAt!),
                        ),
                        trailing: const Icon(Icons.edit_calendar_outlined),
                        onTap: _saving ? null : _pickExpiry,
                      ),
                    ],
                    if (_needsStop || _needsBreakEven || _needsEntry) ...[
                      TextFormField(
                        initialValue: _needsStop
                            ? (widget.signal.currentStopLoss.isEmpty
                                  ? widget.signal.stopLoss
                                  : widget.signal.currentStopLoss)
                            : widget.signal.entryPrice,
                        readOnly: true,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Previous value'),
                        ),
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _price,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: InputDecoration(
                          labelText: context.l10n.text(
                            _needsStop
                                ? 'New stop loss'
                                : _needsBreakEven
                                ? 'Break-even price'
                                : 'New entry price',
                          ),
                        ),
                        validator: _positiveNumber,
                      ),
                    ],
                    if (_needsEntryRange) ...[
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: _price,
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                    decimal: true,
                                  ),
                              decoration: InputDecoration(
                                labelText: context.l10n.text('Entry minimum'),
                              ),
                              validator: _positiveNumber,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextFormField(
                              controller: _price2,
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                    decimal: true,
                                  ),
                              decoration: InputDecoration(
                                labelText: context.l10n.text('Entry maximum'),
                              ),
                              validator: _positiveNumber,
                            ),
                          ),
                        ],
                      ),
                    ],
                    if (_needsBreakEven) ...[
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _offset,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: InputDecoration(
                          labelText: context.l10n.text(
                            'Profit-lock offset (optional)',
                          ),
                        ),
                        validator: (value) => value?.trim().isEmpty == true
                            ? null
                            : _positiveNumber(value),
                      ),
                    ],
                    if (_isTarget) ...[
                      DropdownButtonFormField<int>(
                        initialValue: _targetNumber,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Target reached'),
                        ),
                        items: [
                          for (
                            var index = 1;
                            index <= widget.signal.targets.length;
                            index++
                          )
                            if (!widget.signal.targetsHit.contains(index))
                              DropdownMenuItem(
                                value: index,
                                child: Text(
                                  'TP$index · ${widget.signal.targets[index - 1]}',
                                ),
                              ),
                        ],
                        onChanged: (value) =>
                            setState(() => _targetNumber = value),
                        validator: (value) => value == null
                            ? context.l10n.text('Choose a target.')
                            : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _percentageRemaining,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Position remaining %'),
                          hintText: '50',
                        ),
                        validator: _optionalPercentage,
                      ),
                    ],
                    if (_isPartial) ...[
                      TextFormField(
                        controller: _percentageClosed,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Position closed %'),
                        ),
                        validator: (value) {
                          final optional = _optionalPercentage(value);
                          if (optional != null) return optional;
                          return value?.trim().isEmpty == true
                              ? context.l10n.text(
                                  'Enter the closed percentage.',
                                )
                              : null;
                        },
                      ),
                    ],
                    if (_isTarget || _isPartial) ...[
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              controller: _pips,
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                    decimal: true,
                                    signed: true,
                                  ),
                              decoration: InputDecoration(
                                labelText: context.l10n.text('Realised pips'),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: TextFormField(
                              controller: _return,
                              keyboardType:
                                  const TextInputType.numberWithOptions(
                                    decimal: true,
                                    signed: true,
                                  ),
                              decoration: InputDecoration(
                                labelText: context.l10n.text(
                                  'Realised return %',
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                    if (widget.updateType == SignalUpdateType.correction) ...[
                      DropdownButtonFormField<SignalLifecycleStatus>(
                        initialValue: _override,
                        decoration: InputDecoration(
                          labelText: context.l10n.text('Corrected status'),
                        ),
                        items: [
                          for (final status in SignalLifecycleStatus.values)
                            DropdownMenuItem(
                              value: status,
                              child: Text(context.l10n.text(status.label)),
                            ),
                        ],
                        onChanged: (value) => setState(() => _override = value),
                        validator: (value) => value == null
                            ? context.l10n.text('Choose the corrected status.')
                            : null,
                      ),
                    ],
                    if (widget.user.isSuperAdmin &&
                        widget.updateType != SignalUpdateType.correction) ...[
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: _isCorrection,
                        onChanged: _saving
                            ? null
                            : (value) => setState(
                                () => _isCorrection = value ?? false,
                              ),
                        title: Text(
                          context.l10n.text('Super Admin correction'),
                        ),
                        subtitle: Text(
                          context.l10n.text(
                            'Use only to correct an inaccurate prior value; the audit trail remains immutable.',
                          ),
                        ),
                      ),
                      if (_isCorrection)
                        TextFormField(
                          controller: _correctedUpdateId,
                          decoration: InputDecoration(
                            labelText: context.l10n.text(
                              'Corrected update ID (optional)',
                            ),
                          ),
                        ),
                    ],
                    if (widget.updateType.requiresReason ||
                        !widget.updateType.isCommentary) ...[
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _reason,
                        minLines: 2,
                        maxLines: 4,
                        decoration: InputDecoration(
                          labelText: context.l10n.text(
                            widget.updateType.requiresReason
                                ? 'Reason (required)'
                                : 'Comment (optional)',
                          ),
                        ),
                        validator: widget.updateType.requiresReason
                            ? (value) => value?.trim().isEmpty == true
                                  ? context.l10n.text('Enter a reason.')
                                  : null
                            : null,
                      ),
                    ],
                    if (_isCancellation)
                      SwitchListTile.adaptive(
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          context.l10n.text('Entry was already triggered'),
                        ),
                        value: _entryWasTriggered,
                        onChanged: _saving
                            ? null
                            : (value) =>
                                  setState(() => _entryWasTriggered = value),
                      ),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.schedule_outlined),
                      title: Text(context.l10n.text('Effective date and time')),
                      subtitle: Text(formatDateTime(_effectiveAt)),
                      trailing: const Icon(Icons.edit_calendar_outlined),
                      onTap: _saving ? null : _pickEffectiveAt,
                    ),
                    const SizedBox(height: 14),
                    OutlinedButton.icon(
                      onPressed: _saving ? null : _pickAttachment,
                      icon: const Icon(Icons.add_photo_alternate_outlined),
                      label: Text(
                        _attachment == null
                            ? context.l10n.text('Attach chart image (optional)')
                            : _attachment!.name,
                      ),
                    ),
                    SwitchListTile.adaptive(
                      contentPadding: EdgeInsets.zero,
                      title: Text(context.l10n.text('Notify signal followers')),
                      subtitle: Text(
                        context.l10n.text(
                          'A localized notification is sent once after this update is committed.',
                        ),
                      ),
                      value: _notify,
                      onChanged: _saving
                          ? null
                          : (value) => setState(() => _notify = value),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              Padding(
                padding: const EdgeInsets.all(14),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton(
                      onPressed: _saving ? null : () => Navigator.pop(context),
                      child: Text(context.l10n.text('Cancel')),
                    ),
                    const SizedBox(width: 8),
                    FilledButton.icon(
                      key: const Key('submit-signal-update'),
                      onPressed: _saving ? null : _submit,
                      icon: _saving
                          ? const SizedBox.square(
                              dimension: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.check),
                      label: Text(context.l10n.text('Record update')),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  String? _positiveNumber(String? value) {
    try {
      final parsed = Mt5Decimal.parse(value?.trim());
      return parsed.compareTo(Mt5Decimal.zero) <= 0
          ? context.l10n.text('Enter a positive price.')
          : null;
    } on FormatException {
      return context.l10n.text('Enter a positive price.');
    }
  }

  String? _optionalPercentage(String? value) {
    if (value == null || value.trim().isEmpty) return null;
    try {
      SignalPercentage.parse(value.trim());
      return null;
    } on FormatException {
      return context.l10n.text('Enter a percentage from 0 to 100.');
    }
  }

  Future<void> _pickAttachment() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    if (result != null && result.files.isNotEmpty && mounted) {
      setState(() => _attachment = result.files.single);
    }
  }

  Future<void> _submit() async {
    if (_formKey.currentState?.validate() != true) return;
    setState(() => _saving = true);
    try {
      if (_attachment != null && _uploadedUrl == null) {
        final file = _attachment!;
        _uploadedUrl = await widget.repository.uploadContentImage(
          area: 'trading_signals',
          file: ApiUploadFile(
            field: 'file',
            filename: file.name,
            path: file.path,
            bytes: file.bytes,
          ),
        );
      }
      final message = _message.text.trim();
      final secondaryMessage = _messageSecondary.text.trim();
      final language = _language.code;
      final secondaryLanguage = _language.secondary.code;
      final draft = SignalUpdateDraft(
        idempotencyKey: _idempotencyKey,
        updateType: widget.updateType,
        expectedVersion: widget.signal.version,
        titleI18n: _isDetails
            ? {
                if (message.isNotEmpty) language: message,
                if (_addTranslation && secondaryMessage.isNotEmpty)
                  secondaryLanguage: secondaryMessage,
              }
            : const {},
        messageI18n: _isDetails
            ? const {}
            : {
                if (message.isNotEmpty) language: message,
                if (_addTranslation && secondaryMessage.isNotEmpty)
                  secondaryLanguage: secondaryMessage,
              },
        reason: _reason.text.trim(),
        entryPrice: _needsEntry ? _price.text.trim() : null,
        entryMin: _needsEntryRange ? _price.text.trim() : null,
        entryMax: _needsEntryRange ? _price2.text.trim() : null,
        newStopLoss: _needsStop ? _price.text.trim() : null,
        breakEvenPrice: _needsBreakEven ? _price.text.trim() : null,
        targetNumber: _isTarget ? _targetNumber : null,
        targetPrice: _isTarget && _targetNumber != null
            ? widget.signal.targets[_targetNumber! - 1]
            : null,
        percentageClosed: _percentageClosed.text.trim().isEmpty
            ? null
            : SignalPercentage.parse(_percentageClosed.text),
        percentageRemaining: _percentageRemaining.text.trim().isEmpty
            ? null
            : SignalPercentage.parse(_percentageRemaining.text),
        realisedPips: _pips.text.trim().isEmpty ? null : _pips.text.trim(),
        realisedPercentage: _return.text.trim().isEmpty
            ? null
            : _return.text.trim(),
        profitLockOffset: _offset.text.trim().isEmpty
            ? null
            : _offset.text.trim(),
        entryWasTriggered: _isCancellation ? _entryWasTriggered : null,
        attachmentUrls: _uploadedUrl == null ? const [] : [_uploadedUrl!],
        notificationRequested: _notify,
        isCorrection: _isCorrection,
        correctedUpdateId: _correctedUpdateId.text.trim(),
        lifecycleOverride: _override,
        primaryLanguage: language,
        effectiveAt: _effectiveAt,
        timeframe: _isDetails ? _timeframe.text.trim() : null,
        riskLevel: _isDetails ? _riskLevel : null,
        expiryAt: _isDetails ? _expiryAt : null,
      );
      await widget.repository.updateTradingSignal(
        signalId: widget.signal.id,
        publisherId: widget.user.uid,
        update: draft,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        AppFeedback.error(context, error, operation: 'record signal update');
        setState(() => _saving = false);
      }
    }
  }

  Future<void> _pickEffectiveAt() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _effectiveAt,
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_effectiveAt),
    );
    if (time == null || !mounted) return;
    setState(() {
      _effectiveAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
    });
  }

  Future<void> _pickExpiry() async {
    final initial = _expiryAt ?? DateTime.now().add(const Duration(days: 1));
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null || !mounted) return;
    setState(() {
      _expiryAt = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
    });
  }
}

class SignalManagementEmptyState extends StatelessWidget {
  const SignalManagementEmptyState({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 520),
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 56, color: Theme.of(context).colorScheme.primary),
            const SizedBox(height: 14),
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
          ],
        ),
      ),
    ),
  );
}

class SignalTimelineEntry extends StatelessWidget {
  const SignalTimelineEntry({
    super.key,
    required this.update,
    required this.language,
    required this.last,
  });

  final TradingSignalUpdate update;
  final String language;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final message = localizedValue(update.messageI18n, language);
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 38,
            child: Column(
              children: [
                CircleAvatar(
                  radius: 16,
                  child: Icon(_actionIcon(update.updateType), size: 17),
                ),
                if (!last) Expanded(child: VerticalDivider(width: 1)),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 8,
                    runSpacing: 4,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Text(
                        localizedValue(
                          update.titleI18n,
                          language,
                          fallback: context.l10n.text(update.updateType.label),
                        ),
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      _StatusBadge(
                        label:
                            '${update.lifecycleFrom.label} → ${update.lifecycleTo.label}',
                      ),
                      if (update.isCorrection)
                        const _StatusBadge(
                          label: 'Correction',
                          highlighted: true,
                        ),
                    ],
                  ),
                  if (message.isNotEmpty) ...[
                    const SizedBox(height: 5),
                    Text(message),
                  ],
                  if (update.reason.isNotEmpty) ...[
                    const SizedBox(height: 5),
                    Text(update.reason),
                  ],
                  const SizedBox(height: 5),
                  Text(
                    '${update.createdByName.isEmpty ? update.createdBy : update.createdByName}'
                    '${update.createdByRole.isEmpty ? '' : ' · ${update.createdByRole}'}'
                    '${update.createdAt == null ? '' : ' · ${formatDateTime(update.createdAt!)}'}',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  const SizedBox(height: 4),
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: [
                      _StatusBadge(label: 'ID ${update.id}'),
                      if (update.effectiveAt != null)
                        _StatusBadge(
                          label:
                              'Effective ${formatDateTime(update.effectiveAt!)}',
                        ),
                      if (update.notificationRequested)
                        _StatusBadge(
                          label: update.notificationSent
                              ? 'Notification sent'
                              : 'Notification queued',
                          highlighted: update.notificationSent,
                        ),
                    ],
                  ),
                  if (update.previousValues.isNotEmpty ||
                      update.newValues.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 7),
                      child: Text(
                        _valueSummary(update),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ),
                  if (update.attachmentUrls.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 7),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Chip(
                            avatar: const Icon(Icons.image_outlined, size: 18),
                            label: Text(context.l10n.text('Chart attached')),
                          ),
                          const SizedBox(height: 6),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Image.network(
                              update.attachmentUrls.first,
                              height: 112,
                              width: 188,
                              fit: BoxFit.cover,
                              errorBuilder: (_, _, _) => Container(
                                height: 72,
                                width: 188,
                                alignment: Alignment.center,
                                color: Theme.of(
                                  context,
                                ).colorScheme.surfaceContainerHighest,
                                child: const Icon(Icons.broken_image_outlined),
                              ),
                            ),
                          ),
                        ],
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
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, this.highlighted = false});

  final String label;
  final bool highlighted;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: highlighted
          ? Theme.of(context).colorScheme.primaryContainer
          : Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(999),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      child: Text(
        context.l10n.text(label),
        style: Theme.of(context).textTheme.labelMedium,
      ),
    ),
  );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 150,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.l10n.text(label),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        const SizedBox(height: 2),
        Text(
          value.isEmpty ? '—' : value,
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ],
    ),
  );
}

IconData _actionIcon(SignalUpdateType type) => switch (type) {
  SignalUpdateType.published => Icons.publish_outlined,
  SignalUpdateType.detailsUpdated => Icons.edit_note_outlined,
  SignalUpdateType.entryTriggered => Icons.play_arrow_rounded,
  SignalUpdateType.entryPriceUpdated ||
  SignalUpdateType.entryRangeUpdated => Icons.edit_location_alt_outlined,
  SignalUpdateType.updateStopLoss ||
  SignalUpdateType.trailingStopLoss ||
  SignalUpdateType.reduceRisk => Icons.shield_outlined,
  SignalUpdateType.moveStopLossToBreakEven ||
  SignalUpdateType.updateBreakEven ||
  SignalUpdateType.trailingBreakEven => Icons.balance_outlined,
  SignalUpdateType.lockProfit => Icons.lock_outline,
  SignalUpdateType.partialClose ||
  SignalUpdateType.partialProfitTaken => Icons.pie_chart_outline,
  SignalUpdateType.targetHit => Icons.flag_outlined,
  SignalUpdateType.stopLossHit ||
  SignalUpdateType.breakEvenHit => Icons.report_outlined,
  SignalUpdateType.manuallyClosed ||
  SignalUpdateType.closedInProfit ||
  SignalUpdateType.closedInLoss ||
  SignalUpdateType.cancelled ||
  SignalUpdateType.removePendingOrder ||
  SignalUpdateType.expired ||
  SignalUpdateType.invalidated => Icons.block_outlined,
  SignalUpdateType.completed => Icons.task_alt_outlined,
  SignalUpdateType.commentary ||
  SignalUpdateType.marketCommentary => Icons.forum_outlined,
  SignalUpdateType.warning => Icons.warning_amber_outlined,
  SignalUpdateType.chart => Icons.add_chart_outlined,
  SignalUpdateType.analysis => Icons.analytics_outlined,
  SignalUpdateType.archived => Icons.archive_outlined,
  SignalUpdateType.restored => Icons.unarchive_outlined,
  SignalUpdateType.correction => Icons.rule_outlined,
};

String _ageLabel(Duration duration) {
  if (duration.inDays > 0) return '${duration.inDays}d';
  if (duration.inHours > 0) return '${duration.inHours}h';
  return '${duration.inMinutes.clamp(0, 59)}m';
}

String _valueSummary(TradingSignalUpdate update) {
  final keys = <String>{
    ...update.previousValues.keys,
    ...update.newValues.keys,
  }.where((key) => key != 'version' && key != 'lifecycle_status');
  final parts = <String>[];
  for (final key in keys.take(4)) {
    final before = update.previousValues[key];
    final after = update.newValues[key];
    if ('$before' == '$after') continue;
    parts.add(
      '${key.replaceAll('_', ' ')}: ${before ?? '—'} → ${after ?? '—'}',
    );
  }
  return parts.join(' · ');
}

String _contextActionLabel(TradingSignalItem signal, SignalUpdateType action) {
  if (action != SignalUpdateType.entryTriggered) return action.label;
  return switch (SignalOrderType.parse(signal.orderType)) {
    SignalOrderType.buyLimit => 'Buy Limit activated',
    SignalOrderType.sellLimit => 'Sell Limit activated',
    SignalOrderType.buyStop => 'Buy Stop activated',
    SignalOrderType.sellStop => 'Sell Stop activated',
    SignalOrderType.pendingOrder => 'Pending order activated',
    SignalOrderType.marketExecution => action.label,
  };
}
