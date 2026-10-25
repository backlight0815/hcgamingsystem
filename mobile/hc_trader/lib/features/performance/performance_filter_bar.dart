import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/localization/app_localizations.dart';
import '../../models/mt5_sync_models.dart';
import '../../models/trading_data_source.dart';
import '../../models/trading_date_filter.dart';
import '../../shared/trading_date_filter_bar.dart';

class PerformanceFilterBar extends StatelessWidget {
  const PerformanceFilterBar({
    super.key,
    required this.mode,
    required this.dateFilter,
    required this.accounts,
    required this.selectedAccountId,
    required this.canUseMt5,
    required this.onModeChanged,
    required this.onDateChanged,
    required this.onAccountChanged,
  });

  final TradingDataDisplayMode mode;
  final TradingDateFilter dateFilter;
  final List<Mt5Account> accounts;
  final String selectedAccountId;
  final bool canUseMt5;
  final ValueChanged<TradingDataDisplayMode> onModeChanged;
  final ValueChanged<TradingDateFilter> onDateChanged;
  final ValueChanged<String> onAccountChanged;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        if (constraints.maxWidth < 720) {
          return _MobileFilterButton(
            mode: mode,
            dateFilter: dateFilter,
            accounts: accounts,
            selectedAccountId: selectedAccountId,
            canUseMt5: canUseMt5,
            onModeChanged: onModeChanged,
            onDateChanged: onDateChanged,
            onAccountChanged: onAccountChanged,
          );
        }
        return _DesktopFilterToolbar(
          mode: mode,
          dateFilter: dateFilter,
          accounts: accounts,
          selectedAccountId: selectedAccountId,
          canUseMt5: canUseMt5,
          onModeChanged: onModeChanged,
          onDateChanged: onDateChanged,
          onAccountChanged: onAccountChanged,
        );
      },
    );
  }
}

class _MobileFilterButton extends StatelessWidget {
  const _MobileFilterButton({
    required this.mode,
    required this.dateFilter,
    required this.accounts,
    required this.selectedAccountId,
    required this.canUseMt5,
    required this.onModeChanged,
    required this.onDateChanged,
    required this.onAccountChanged,
  });

  final TradingDataDisplayMode mode;
  final TradingDateFilter dateFilter;
  final List<Mt5Account> accounts;
  final String selectedAccountId;
  final bool canUseMt5;
  final ValueChanged<TradingDataDisplayMode> onModeChanged;
  final ValueChanged<TradingDateFilter> onDateChanged;
  final ValueChanged<String> onAccountChanged;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: scheme.surfaceContainerLow,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: scheme.outlineVariant),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: () => _openSheet(context),
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
                      context.l10n.text('Performance filters'),
                      style: Theme.of(context).textTheme.labelMedium,
                    ),
                    Text(
                      '${tradingDateFilterLabel(context, dateFilter)} · ${context.l10n.text(_sourceLabel(mode))}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              Badge(
                label: Text(
                  '${_activeFilterCount(mode, dateFilter, selectedAccountId)}',
                ),
                child: const Icon(Icons.chevron_right),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _openSheet(BuildContext context) {
    return showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      builder: (context) => _PerformanceFilterSheet(
        mode: mode,
        dateFilter: dateFilter,
        accounts: accounts,
        selectedAccountId: selectedAccountId,
        canUseMt5: canUseMt5,
        onModeChanged: onModeChanged,
        onDateChanged: onDateChanged,
        onAccountChanged: onAccountChanged,
      ),
    );
  }
}

class _DesktopFilterToolbar extends StatelessWidget {
  const _DesktopFilterToolbar({
    required this.mode,
    required this.dateFilter,
    required this.accounts,
    required this.selectedAccountId,
    required this.canUseMt5,
    required this.onModeChanged,
    required this.onDateChanged,
    required this.onAccountChanged,
  });

  final TradingDataDisplayMode mode;
  final TradingDateFilter dateFilter;
  final List<Mt5Account> accounts;
  final String selectedAccountId;
  final bool canUseMt5;
  final ValueChanged<TradingDataDisplayMode> onModeChanged;
  final ValueChanged<TradingDateFilter> onDateChanged;
  final ValueChanged<String> onAccountChanged;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: scheme.surfaceContainerLow,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: scheme.outlineVariant),
      ),
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          if (canUseMt5)
            SegmentedButton<TradingDataDisplayMode>(
              showSelectedIcon: false,
              segments: [
                ButtonSegment(
                  value: TradingDataDisplayMode.manualOnly,
                  icon: const Icon(Icons.edit_note_outlined),
                  label: Text(context.l10n.text('Manual / Excel')),
                ),
                ButtonSegment(
                  value: TradingDataDisplayMode.mt5Only,
                  icon: const Icon(Icons.sync_alt_rounded),
                  label: Text(context.l10n.text('MT5')),
                ),
              ],
              selected: {mode},
              onSelectionChanged: (value) => onModeChanged(value.first),
            ),
          if (mode == TradingDataDisplayMode.mt5Only)
            SizedBox(
              width: 240,
              child: _AccountDropdown(
                accounts: accounts,
                selectedAccountId: selectedAccountId,
                onChanged: onAccountChanged,
              ),
            ),
          OutlinedButton.icon(
            onPressed: () => _openDateFilter(context),
            icon: const Icon(Icons.calendar_month_outlined),
            label: Text(tradingDateFilterLabel(context, dateFilter)),
          ),
          PopupMenuButton<_DatePreset>(
            tooltip: context.l10n.text('Quick date range'),
            onSelected: (preset) =>
                onDateChanged(_filterForPreset(preset, dateFilter)),
            itemBuilder: (context) => [
              for (final preset in _DatePreset.values)
                PopupMenuItem(
                  value: preset,
                  child: Text(context.l10n.text(_presetLabel(preset))),
                ),
            ],
            child: Chip(
              avatar: const Icon(Icons.bolt_outlined, size: 18),
              label: Text(context.l10n.text('Quick range')),
            ),
          ),
          if (dateFilter.isActive || selectedAccountId.isNotEmpty)
            TextButton.icon(
              onPressed: () {
                onDateChanged(_allTime(dateFilter));
                onAccountChanged('');
              },
              icon: const Icon(Icons.restart_alt),
              label: Text(context.l10n.text('Reset filters')),
            ),
        ],
      ),
    );
  }

  Future<void> _openDateFilter(BuildContext context) async {
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 1)),
      initialDateRange:
          dateFilter.startDate != null && dateFilter.endDate != null
          ? DateTimeRange(
              start: dateFilter.startDate!,
              end: dateFilter.endDate!,
            )
          : null,
    );
    if (range == null) return;
    onDateChanged(_range(dateFilter, range.start, range.end));
  }
}

class _PerformanceFilterSheet extends StatefulWidget {
  const _PerformanceFilterSheet({
    required this.mode,
    required this.dateFilter,
    required this.accounts,
    required this.selectedAccountId,
    required this.canUseMt5,
    required this.onModeChanged,
    required this.onDateChanged,
    required this.onAccountChanged,
  });

  final TradingDataDisplayMode mode;
  final TradingDateFilter dateFilter;
  final List<Mt5Account> accounts;
  final String selectedAccountId;
  final bool canUseMt5;
  final ValueChanged<TradingDataDisplayMode> onModeChanged;
  final ValueChanged<TradingDateFilter> onDateChanged;
  final ValueChanged<String> onAccountChanged;

  @override
  State<_PerformanceFilterSheet> createState() =>
      _PerformanceFilterSheetState();
}

class _PerformanceFilterSheetState extends State<_PerformanceFilterSheet> {
  late TradingDataDisplayMode _mode = widget.mode;
  late TradingDateFilter _filter = widget.dateFilter;
  late String _account = widget.selectedAccountId;

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .78,
      maxChildSize: .96,
      builder: (context, controller) => Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 12, 4),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Performance filters'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
          ),
          Expanded(
            child: ListView(
              controller: controller,
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
              children: [
                if (widget.canUseMt5) ...[
                  Text(
                    context.l10n.text('Data source'),
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  const SizedBox(height: 8),
                  SegmentedButton<TradingDataDisplayMode>(
                    showSelectedIcon: false,
                    segments: [
                      ButtonSegment(
                        value: TradingDataDisplayMode.manualOnly,
                        label: Text(context.l10n.text('Manual / Excel')),
                      ),
                      ButtonSegment(
                        value: TradingDataDisplayMode.mt5Only,
                        label: Text(context.l10n.text('MT5')),
                      ),
                    ],
                    selected: {_mode},
                    onSelectionChanged: (value) => setState(() {
                      _mode = value.first;
                    }),
                  ),
                  const SizedBox(height: 18),
                ],
                if (_mode == TradingDataDisplayMode.mt5Only) ...[
                  _AccountDropdown(
                    accounts: widget.accounts,
                    selectedAccountId: _account,
                    onChanged: (value) => setState(() => _account = value),
                  ),
                  const SizedBox(height: 18),
                ],
                Text(
                  context.l10n.text('Date range'),
                  style: Theme.of(context).textTheme.titleSmall,
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    for (final preset in _DatePreset.values)
                      ChoiceChip(
                        selected: _matchesPreset(_filter, preset),
                        label: Text(context.l10n.text(_presetLabel(preset))),
                        onSelected: (_) => setState(() {
                          _filter = _filterForPreset(preset, _filter);
                        }),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _pickCustomRange,
                  icon: const Icon(Icons.date_range_outlined),
                  label: Text(
                    _filter.type == TradingDateFilterType.dateRange
                        ? tradingDateFilterLabel(context, _filter)
                        : context.l10n.text('Custom date range'),
                  ),
                ),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 10, 20, 16),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => setState(() {
                        _filter = _allTime(widget.dateFilter);
                        _account = '';
                        _mode = TradingDataDisplayMode.manualOnly;
                      }),
                      child: Text(context.l10n.text('Reset')),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: FilledButton(
                      onPressed: () {
                        widget.onModeChanged(_mode);
                        widget.onDateChanged(_filter);
                        widget.onAccountChanged(_account);
                        Navigator.pop(context);
                      },
                      child: Text(context.l10n.text('Apply filters')),
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

  Future<void> _pickCustomRange() async {
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 1)),
      initialDateRange: _filter.startDate != null && _filter.endDate != null
          ? DateTimeRange(start: _filter.startDate!, end: _filter.endDate!)
          : null,
    );
    if (range != null) {
      setState(() => _filter = _range(_filter, range.start, range.end));
    }
  }
}

class _AccountDropdown extends StatelessWidget {
  const _AccountDropdown({
    required this.accounts,
    required this.selectedAccountId,
    required this.onChanged,
  });

  final List<Mt5Account> accounts;
  final String selectedAccountId;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final values = <String>{'', '*', ...accounts.map((account) => account.id)};
    final selected = values.contains(selectedAccountId)
        ? selectedAccountId
        : '';
    return DropdownButtonFormField<String>(
      initialValue: selected,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: context.l10n.text('Trading account'),
        prefixIcon: const Icon(Icons.account_balance_wallet_outlined),
      ),
      items: [
        DropdownMenuItem(
          value: '',
          child: Text(context.l10n.text('Main MT5 account')),
        ),
        DropdownMenuItem(
          value: '*',
          child: Text(context.l10n.text('All MT5 accounts')),
        ),
        for (final account in accounts)
          DropdownMenuItem(
            value: account.id,
            child: Text(
              account.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
      ],
      onChanged: (value) => onChanged(value ?? ''),
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
  lastThreeMonths,
  lastSixMonths,
  thisYear,
  allTime,
}

String _sourceLabel(TradingDataDisplayMode mode) =>
    mode == TradingDataDisplayMode.mt5Only ? 'MT5' : 'Manual / Excel';

String _presetLabel(_DatePreset preset) => switch (preset) {
  _DatePreset.today => 'Today',
  _DatePreset.yesterday => 'Yesterday',
  _DatePreset.thisWeek => 'This week',
  _DatePreset.lastWeek => 'Last week',
  _DatePreset.thisMonth => 'This month',
  _DatePreset.lastMonth => 'Last month',
  _DatePreset.lastThreeMonths => 'Last 3 months',
  _DatePreset.lastSixMonths => 'Last 6 months',
  _DatePreset.thisYear => 'This year',
  _DatePreset.allTime => 'All time',
};

TradingDateFilter _filterForPreset(
  _DatePreset preset,
  TradingDateFilter scope,
) {
  final now = DateTime.now().toUtc().add(
    Duration(minutes: scope.timezoneOffsetMinutes),
  );
  final today = DateTime(now.year, now.month, now.day);
  switch (preset) {
    case _DatePreset.today:
      return _range(scope, today, today);
    case _DatePreset.yesterday:
      final day = today.subtract(const Duration(days: 1));
      return _range(scope, day, day);
    case _DatePreset.thisWeek:
      return _range(
        scope,
        today.subtract(Duration(days: today.weekday - 1)),
        today,
      );
    case _DatePreset.lastWeek:
      final thisMonday = today.subtract(Duration(days: today.weekday - 1));
      return _range(
        scope,
        thisMonday.subtract(const Duration(days: 7)),
        thisMonday.subtract(const Duration(days: 1)),
      );
    case _DatePreset.thisMonth:
      return TradingDateFilter.month(
        today.year,
        today.month,
        timezone: scope.timezone,
        timezoneOffsetMinutes: scope.timezoneOffsetMinutes,
        dataSource: scope.dataSource,
        mt5AccountId: scope.mt5AccountId,
      );
    case _DatePreset.lastMonth:
      final month = DateTime(today.year, today.month - 1);
      return TradingDateFilter.month(
        month.year,
        month.month,
        timezone: scope.timezone,
        timezoneOffsetMinutes: scope.timezoneOffsetMinutes,
        dataSource: scope.dataSource,
        mt5AccountId: scope.mt5AccountId,
      );
    case _DatePreset.lastThreeMonths:
      return _range(scope, DateTime(today.year, today.month - 2, 1), today);
    case _DatePreset.lastSixMonths:
      return _range(scope, DateTime(today.year, today.month - 5, 1), today);
    case _DatePreset.thisYear:
      return TradingDateFilter.year(
        today.year,
        timezone: scope.timezone,
        timezoneOffsetMinutes: scope.timezoneOffsetMinutes,
        dataSource: scope.dataSource,
        mt5AccountId: scope.mt5AccountId,
      );
    case _DatePreset.allTime:
      return _allTime(scope);
  }
}

TradingDateFilter _range(
  TradingDateFilter scope,
  DateTime start,
  DateTime end,
) => TradingDateFilter.dateRange(
  start,
  end,
  timezone: scope.timezone,
  timezoneOffsetMinutes: scope.timezoneOffsetMinutes,
  dataSource: scope.dataSource,
  mt5AccountId: scope.mt5AccountId,
);

TradingDateFilter _allTime(TradingDateFilter scope) =>
    TradingDateFilter.allTime(
      timezone: scope.timezone,
      timezoneOffsetMinutes: scope.timezoneOffsetMinutes,
      dataSource: scope.dataSource,
      mt5AccountId: scope.mt5AccountId,
    );

bool _matchesPreset(TradingDateFilter filter, _DatePreset preset) {
  if (preset == _DatePreset.allTime) return !filter.isActive;
  final expected = _filterForPreset(preset, filter);
  return filter.type == expected.type &&
      filter.startInclusive == expected.startInclusive &&
      filter.endExclusive == expected.endExclusive;
}

int _activeFilterCount(
  TradingDataDisplayMode mode,
  TradingDateFilter date,
  String account,
) =>
    (date.isActive ? 1 : 0) +
    (mode == TradingDataDisplayMode.mt5Only ? 1 : 0) +
    (account.isNotEmpty ? 1 : 0);

String compactLastUpdated(BuildContext context, DateTime value) =>
    DateFormat.MMMd(
      Localizations.localeOf(context).toLanguageTag(),
    ).add_Hm().format(value);
