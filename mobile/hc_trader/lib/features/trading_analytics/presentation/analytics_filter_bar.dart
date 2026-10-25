import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/localization/app_localizations.dart';
import '../domain/analytics_filter.dart';
import '../domain/analytics_trade.dart';

class AnalyticsAccountOption {
  const AnalyticsAccountOption({
    required this.id,
    required this.label,
    required this.currency,
    required this.source,
    this.isMain = false,
  });

  final String id;
  final String label;
  final String currency;
  final AnalyticsTradeDataSource source;
  final bool isMain;
}

class AnalyticsFilterBar extends StatelessWidget {
  const AnalyticsFilterBar({
    super.key,
    required this.filter,
    required this.accounts,
    required this.trades,
    required this.canUseMt5,
    required this.timezoneOffsetMinutes,
    required this.onChanged,
  });

  final AnalyticsFilter filter;
  final List<AnalyticsAccountOption> accounts;
  final List<AnalyticsTrade> trades;
  final bool canUseMt5;
  final int timezoneOffsetMinutes;
  final ValueChanged<AnalyticsFilter> onChanged;

  @override
  Widget build(BuildContext context) {
    final sourceCountCorrection =
        _allAvailableSourcesSelected(filter.sources, canUseMt5) &&
            filter.sources.length != AnalyticsTradeDataSource.values.length
        ? 1
        : 0;
    final activeCount = filter.activeCount - sourceCountCorrection;
    return Material(
      color: Theme.of(context).colorScheme.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => _open(context),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              const Icon(Icons.tune_rounded),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      context.l10n.text('Global analytics filters'),
                      style: Theme.of(context).textTheme.labelMedium,
                    ),
                    Text(
                      '${_sourceLabel(context)} · ${_dateLabel(context)} · ${_accountLabel(context)}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              if (activeCount > 0)
                Badge(
                  label: Text('$activeCount'),
                  child: const Icon(Icons.chevron_right),
                )
              else
                const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _open(BuildContext context) {
    return showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      builder: (context) => _FilterSheet(
        filter: filter,
        accounts: accounts,
        trades: trades,
        canUseMt5: canUseMt5,
        timezoneOffsetMinutes: timezoneOffsetMinutes,
        onChanged: onChanged,
      ),
    );
  }

  String _sourceLabel(BuildContext context) {
    if (_allAvailableSourcesSelected(filter.sources, canUseMt5)) {
      return context.l10n.text('All sources');
    }
    return filter.sources
        .map((source) => context.l10n.text(_sourceName(source)))
        .join(', ');
  }

  String _dateLabel(BuildContext context) {
    if (filter.startUtc == null && filter.endUtcExclusive == null) {
      return context.l10n.text('All time');
    }
    final locale = Localizations.localeOf(context).toLanguageTag();
    final start = filter.startUtc?.add(
      Duration(minutes: timezoneOffsetMinutes),
    );
    final exclusive = filter.endUtcExclusive?.add(
      Duration(minutes: timezoneOffsetMinutes),
    );
    final end = exclusive?.subtract(const Duration(days: 1));
    if (start == null || end == null) return context.l10n.text('Custom range');
    return '${DateFormat.yMMMd(locale).format(start)} – ${DateFormat.yMMMd(locale).format(end)}';
  }

  String _accountLabel(BuildContext context) {
    if (filter.accountId == '*') return context.l10n.text('All accounts');
    for (final account in accounts) {
      if (account.id == filter.accountId) {
        return context.l10n.text(account.label);
      }
    }
    return context.l10n.text('Unassigned account');
  }
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({
    required this.filter,
    required this.accounts,
    required this.trades,
    required this.canUseMt5,
    required this.timezoneOffsetMinutes,
    required this.onChanged,
  });

  final AnalyticsFilter filter;
  final List<AnalyticsAccountOption> accounts;
  final List<AnalyticsTrade> trades;
  final bool canUseMt5;
  final int timezoneOffsetMinutes;
  final ValueChanged<AnalyticsFilter> onChanged;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late AnalyticsFilter _filter = widget.filter;
  late _DatePreset _preset = _matchingPreset(widget.filter);

  @override
  Widget build(BuildContext context) {
    final symbols = _options(widget.trades.map((trade) => trade.symbol));
    final strategies = _options(
      widget.trades.map((trade) => trade.strategyName),
    );
    final setups = _options(widget.trades.map((trade) => trade.setupName));
    final sessions = _options(widget.trades.map((trade) => trade.session));
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .9,
      maxChildSize: .97,
      builder: (context, controller) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 12, 6),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Global analytics filters'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: context.l10n.text('Close'),
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
          ),
          Expanded(
            child: ListView(
              controller: controller,
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
              children: [
                _label(context, 'Data source'),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    ChoiceChip(
                      selected: _allAvailableSourcesSelected(
                        _filter.sources,
                        widget.canUseMt5,
                      ),
                      label: Text(context.l10n.text('All sources')),
                      onSelected: (_) => setState(() {
                        _filter = _filter.copyWith(
                          sources: {
                            for (final source
                                in AnalyticsTradeDataSource.values)
                              if (widget.canUseMt5 ||
                                  source != AnalyticsTradeDataSource.mt5)
                                source,
                          },
                        );
                      }),
                    ),
                    for (final source in AnalyticsTradeDataSource.values)
                      if (widget.canUseMt5 ||
                          source != AnalyticsTradeDataSource.mt5)
                        FilterChip(
                          selected:
                              _filter.sources.length == 1 &&
                              _filter.sources.contains(source),
                          label: Text(context.l10n.text(_sourceName(source))),
                          onSelected: (_) => setState(() {
                            _filter = _filter.copyWith(sources: {source});
                          }),
                        ),
                  ],
                ),
                const SizedBox(height: 18),
                _label(context, 'Account'),
                DropdownButtonFormField<String>(
                  initialValue:
                      {
                        '*',
                        ...widget.accounts.map((account) => account.id),
                      }.contains(_filter.accountId)
                      ? _filter.accountId
                      : '*',
                  decoration: InputDecoration(
                    prefixIcon: const Icon(
                      Icons.account_balance_wallet_outlined,
                    ),
                    labelText: context.l10n.text('Trading account'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: '*',
                      child: Text(context.l10n.text('All accounts')),
                    ),
                    for (final account in widget.accounts)
                      DropdownMenuItem(
                        value: account.id,
                        child: Text(
                          account.isMain
                              ? '${context.l10n.text('Main account')} · ${context.l10n.text(account.label)}'
                              : '${context.l10n.text(account.label)}${account.currency.isEmpty ? '' : ' · ${account.currency}'}',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                  ],
                  onChanged: (value) => setState(() {
                    _filter = _filter.copyWith(accountId: value ?? '*');
                  }),
                ),
                const SizedBox(height: 18),
                _label(context, 'Date range'),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final preset in _DatePreset.values)
                      ChoiceChip(
                        selected: _preset == preset,
                        label: Text(context.l10n.text(_presetLabel(preset))),
                        onSelected: (_) => _selectPreset(preset),
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                _label(context, 'Trade filters'),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final width = constraints.maxWidth >= 680
                        ? (constraints.maxWidth - 12) / 2
                        : constraints.maxWidth;
                    return Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: [
                        SizedBox(
                          width: width,
                          child: _stringDropdown(
                            context,
                            label: 'Symbol',
                            value: _filter.symbol,
                            values: symbols,
                            onChanged: (value) => setState(() {
                              _filter = _filter.copyWith(
                                symbol: value,
                                clearSymbol: value == null,
                              );
                            }),
                          ),
                        ),
                        SizedBox(
                          width: width,
                          child:
                              DropdownButtonFormField<AnalyticsTradeDirection?>(
                                initialValue: _filter.direction,
                                decoration: InputDecoration(
                                  labelText: context.l10n.text('Direction'),
                                ),
                                items: [
                                  DropdownMenuItem(
                                    value: null,
                                    child: Text(context.l10n.text('All')),
                                  ),
                                  DropdownMenuItem(
                                    value: AnalyticsTradeDirection.buy,
                                    child: Text(context.l10n.text('Buy')),
                                  ),
                                  DropdownMenuItem(
                                    value: AnalyticsTradeDirection.sell,
                                    child: Text(context.l10n.text('Sell')),
                                  ),
                                ],
                                onChanged: (value) => setState(() {
                                  _filter = _filter.copyWith(
                                    direction: value,
                                    clearDirection: value == null,
                                  );
                                }),
                              ),
                        ),
                        SizedBox(
                          width: width,
                          child: _stringDropdown(
                            context,
                            label: 'Strategy',
                            value: _filter.strategy,
                            values: strategies,
                            onChanged: (value) => setState(() {
                              _filter = _filter.copyWith(
                                strategy: value,
                                clearStrategy: value == null,
                              );
                            }),
                          ),
                        ),
                        SizedBox(
                          width: width,
                          child: _stringDropdown(
                            context,
                            label: 'Setup',
                            value: _filter.setup,
                            values: setups,
                            onChanged: (value) => setState(() {
                              _filter = _filter.copyWith(
                                setup: value,
                                clearSetup: value == null,
                              );
                            }),
                          ),
                        ),
                        SizedBox(
                          width: width,
                          child: _stringDropdown(
                            context,
                            label: 'Trading session',
                            value: _filter.session,
                            values: sessions,
                            onChanged: (value) => setState(() {
                              _filter = _filter.copyWith(
                                session: value,
                                clearSession: value == null,
                              );
                            }),
                          ),
                        ),
                        SizedBox(
                          width: width,
                          child:
                              DropdownButtonFormField<AnalyticsOutcomeFilter>(
                                initialValue: _filter.outcome,
                                decoration: InputDecoration(
                                  labelText: context.l10n.text(
                                    'Outcome / status',
                                  ),
                                ),
                                items: [
                                  for (final outcome
                                      in AnalyticsOutcomeFilter.values)
                                    DropdownMenuItem(
                                      value: outcome,
                                      child: Text(
                                        context.l10n.text(
                                          _outcomeLabel(outcome),
                                        ),
                                      ),
                                    ),
                                ],
                                onChanged: (value) => setState(() {
                                  _filter = _filter.copyWith(
                                    outcome:
                                        value ?? AnalyticsOutcomeFilter.all,
                                  );
                                }),
                              ),
                        ),
                      ],
                    );
                  },
                ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final reset = OutlinedButton.icon(
                    onPressed: () => setState(() {
                      _filter = AnalyticsFilter(
                        sources: {
                          AnalyticsTradeDataSource.manual,
                          AnalyticsTradeDataSource.excel,
                          if (widget.canUseMt5) AnalyticsTradeDataSource.mt5,
                        },
                      );
                      _preset = _DatePreset.allTime;
                    }),
                    icon: const Icon(Icons.restart_alt),
                    label: Text(context.l10n.text('Reset filters')),
                  );
                  final apply = FilledButton.icon(
                    onPressed: () {
                      widget.onChanged(_filter);
                      Navigator.pop(context);
                    },
                    icon: const Icon(Icons.check),
                    label: Text(context.l10n.text('Apply filters')),
                  );
                  if (constraints.maxWidth < 390) {
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [reset, const SizedBox(height: 8), apply],
                    );
                  }
                  return Row(
                    children: [
                      Expanded(child: reset),
                      const SizedBox(width: 12),
                      Expanded(child: apply),
                    ],
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _selectPreset(_DatePreset preset) async {
    if (preset == _DatePreset.custom) {
      final localStart = _filter.startUtc?.add(
        Duration(minutes: widget.timezoneOffsetMinutes),
      );
      final localEnd = _filter.endUtcExclusive
          ?.add(Duration(minutes: widget.timezoneOffsetMinutes))
          .subtract(const Duration(days: 1));
      final range = await showDateRangePicker(
        context: context,
        firstDate: DateTime(2000),
        lastDate: DateTime.now().add(const Duration(days: 1)),
        initialDateRange: localStart != null && localEnd != null
            ? DateTimeRange(start: localStart, end: localEnd)
            : null,
      );
      if (range == null) return;
      setState(() {
        _preset = preset;
        _filter = _withLocalRange(_filter, range.start, range.end);
      });
      return;
    }
    setState(() {
      _preset = preset;
      _filter = _presetFilter(_filter, preset);
    });
  }

  AnalyticsFilter _presetFilter(AnalyticsFilter base, _DatePreset preset) {
    final now = DateTime.now().toUtc().add(
      Duration(minutes: widget.timezoneOffsetMinutes),
    );
    final today = DateTime(now.year, now.month, now.day);
    DateTime start;
    DateTime end;
    switch (preset) {
      case _DatePreset.today:
        start = today;
        end = today;
      case _DatePreset.yesterday:
        start = today.subtract(const Duration(days: 1));
        end = start;
      case _DatePreset.thisWeek:
        start = today.subtract(Duration(days: today.weekday - 1));
        end = today;
      case _DatePreset.lastWeek:
        final thisMonday = today.subtract(Duration(days: today.weekday - 1));
        start = thisMonday.subtract(const Duration(days: 7));
        end = thisMonday.subtract(const Duration(days: 1));
      case _DatePreset.thisMonth:
        start = DateTime(today.year, today.month);
        end = today;
      case _DatePreset.lastMonth:
        start = DateTime(today.year, today.month - 1);
        end = DateTime(
          today.year,
          today.month,
        ).subtract(const Duration(days: 1));
      case _DatePreset.last30Days:
        start = today.subtract(const Duration(days: 29));
        end = today;
      case _DatePreset.last90Days:
        start = today.subtract(const Duration(days: 89));
        end = today;
      case _DatePreset.thisYear:
        start = DateTime(today.year);
        end = today;
      case _DatePreset.previousYear:
        start = DateTime(today.year - 1);
        end = DateTime(today.year, 1, 1).subtract(const Duration(days: 1));
      case _DatePreset.allTime:
        return base.copyWith(clearDates: true);
      case _DatePreset.custom:
        return base;
    }
    return _withLocalRange(base, start, end);
  }

  AnalyticsFilter _withLocalRange(
    AnalyticsFilter base,
    DateTime start,
    DateTime end,
  ) {
    final offset = Duration(minutes: widget.timezoneOffsetMinutes);
    final localStart = DateTime(start.year, start.month, start.day);
    final localEndExclusive = DateTime(
      end.year,
      end.month,
      end.day,
    ).add(const Duration(days: 1));
    return base.copyWith(
      startUtc: localStart.subtract(offset).toUtc(),
      endUtcExclusive: localEndExclusive.subtract(offset).toUtc(),
    );
  }
}

enum _DatePreset {
  today,
  yesterday,
  thisWeek,
  lastWeek,
  thisMonth,
  lastMonth,
  last30Days,
  last90Days,
  thisYear,
  previousYear,
  allTime,
  custom,
}

String _presetLabel(_DatePreset preset) => switch (preset) {
  _DatePreset.today => 'Today',
  _DatePreset.yesterday => 'Yesterday',
  _DatePreset.thisWeek => 'This week',
  _DatePreset.lastWeek => 'Last week',
  _DatePreset.thisMonth => 'This month',
  _DatePreset.lastMonth => 'Last month',
  _DatePreset.last30Days => 'Last 30 days',
  _DatePreset.last90Days => 'Last 90 days',
  _DatePreset.thisYear => 'This year',
  _DatePreset.previousYear => 'Previous year',
  _DatePreset.allTime => 'All time',
  _DatePreset.custom => 'Custom range',
};

_DatePreset _matchingPreset(AnalyticsFilter filter) =>
    filter.startUtc == null && filter.endUtcExclusive == null
    ? _DatePreset.allTime
    : _DatePreset.custom;

String _sourceName(AnalyticsTradeDataSource source) => switch (source) {
  AnalyticsTradeDataSource.manual => 'Manual',
  AnalyticsTradeDataSource.excel => 'Excel',
  AnalyticsTradeDataSource.mt5 => 'MT5',
};

String _outcomeLabel(AnalyticsOutcomeFilter value) => switch (value) {
  AnalyticsOutcomeFilter.all => 'All trades',
  AnalyticsOutcomeFilter.winning => 'Winning trades',
  AnalyticsOutcomeFilter.losing => 'Losing trades',
  AnalyticsOutcomeFilter.breakeven => 'Breakeven trades',
  AnalyticsOutcomeFilter.open => 'Open trades',
  AnalyticsOutcomeFilter.closed => 'Closed trades',
};

Widget _label(BuildContext context, String value) => Padding(
  padding: const EdgeInsets.only(bottom: 8),
  child: Text(
    context.l10n.text(value),
    style: Theme.of(
      context,
    ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
  ),
);

List<String> _options(Iterable<String?> input) {
  final result =
      input
          .map((value) => value?.trim() ?? '')
          .where((value) => value.isNotEmpty)
          .toSet()
          .toList()
        ..sort();
  return result;
}

bool _allAvailableSourcesSelected(
  Set<AnalyticsTradeDataSource> sources,
  bool canUseMt5,
) =>
    sources.contains(AnalyticsTradeDataSource.manual) &&
    sources.contains(AnalyticsTradeDataSource.excel) &&
    (!canUseMt5 || sources.contains(AnalyticsTradeDataSource.mt5));

Widget _stringDropdown(
  BuildContext context, {
  required String label,
  required String? value,
  required List<String> values,
  required ValueChanged<String?> onChanged,
}) {
  final selected = values.contains(value) ? value : null;
  return DropdownButtonFormField<String?>(
    initialValue: selected,
    decoration: InputDecoration(labelText: context.l10n.text(label)),
    items: [
      DropdownMenuItem(value: null, child: Text(context.l10n.text('All'))),
      for (final option in values)
        DropdownMenuItem(value: option, child: Text(option)),
    ],
    onChanged: onChanged,
  );
}
