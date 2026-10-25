import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/localization/app_localizations.dart';
import '../models/trading_date_filter.dart';

class TradingDateFilterBar extends StatelessWidget {
  const TradingDateFilterBar({
    super.key,
    required this.filter,
    required this.onChanged,
    this.availableYears = const [],
  });

  final TradingDateFilter filter;
  final ValueChanged<TradingDateFilter> onChanged;
  final List<int> availableYears;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 10, 16, 6),
      child: Semantics(
        button: true,
        label: context.l10n.text('Trading period filter'),
        value: tradingDateFilterLabel(context, filter),
        child: Material(
          color: filter.isActive
              ? colors.secondaryContainer
              : colors.surfaceContainerLow,
          borderRadius: BorderRadius.circular(16),
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: () => _openFilter(context),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              child: Row(
                children: [
                  Icon(
                    filter.isActive
                        ? Icons.filter_alt
                        : Icons.filter_alt_outlined,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          context.l10n.text('Trading period'),
                          style: Theme.of(context).textTheme.labelMedium,
                        ),
                        Text(
                          tradingDateFilterLabel(context, filter),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                  ),
                  if (filter.isActive)
                    IconButton(
                      tooltip: context.l10n.text('Reset to all time'),
                      onPressed: () => onChanged(
                        TradingDateFilter.allTime(
                          timezone: filter.timezone,
                          timezoneOffsetMinutes: filter.timezoneOffsetMinutes,
                          dataSource: filter.dataSource,
                          mt5AccountId: filter.mt5AccountId,
                        ),
                      ),
                      icon: const Icon(Icons.close),
                    ),
                  const Icon(Icons.chevron_right),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openFilter(BuildContext context) async {
    final result = await showModalBottomSheet<TradingDateFilter>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _TradingDateFilterSheet(
        initial: filter,
        availableYears: availableYears,
      ),
    );
    if (result != null) onChanged(result);
  }
}

String tradingDateFilterLabel(BuildContext context, TradingDateFilter filter) {
  final locale = Localizations.localeOf(context).toLanguageTag();
  return switch (filter.type) {
    TradingDateFilterType.allTime => context.l10n.text('All time'),
    TradingDateFilterType.specificDate => DateFormat.yMMMd(
      locale,
    ).format(filter.startDate!),
    TradingDateFilterType.dateRange =>
      '${DateFormat.yMMMd(locale).format(filter.startDate!)} – '
          '${DateFormat.yMMMd(locale).format(filter.endDate!)}',
    TradingDateFilterType.month => DateFormat.yMMMM(
      locale,
    ).format(DateTime(filter.year!, filter.month!)),
    TradingDateFilterType.year => context.l10n.text('Year {year}', {
      'year': filter.year!,
    }),
  };
}

class _TradingDateFilterSheet extends StatefulWidget {
  const _TradingDateFilterSheet({
    required this.initial,
    required this.availableYears,
  });

  final TradingDateFilter initial;
  final List<int> availableYears;

  @override
  State<_TradingDateFilterSheet> createState() =>
      _TradingDateFilterSheetState();
}

class _TradingDateFilterSheetState extends State<_TradingDateFilterSheet> {
  late TradingDateFilterType _type;
  late DateTime _specificDate;
  late DateTimeRange _range;
  late int _month;
  late int _year;
  String? _error;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _type = widget.initial.type;
    _specificDate = widget.initial.startDate ?? now;
    _range = DateTimeRange(
      start: widget.initial.startDate ?? now.subtract(const Duration(days: 29)),
      end: widget.initial.endDate ?? now,
    );
    _month = widget.initial.month ?? now.month;
    _year = widget.initial.year ?? now.year;
  }

  List<int> get _years {
    final now = DateTime.now().year;
    return <int>{
      ...widget.availableYears,
      for (var year = now + 1; year >= now - 12; year--) year,
      _year,
    }.toList()..sort((left, right) => right.compareTo(left));
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        14,
        20,
        20 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    context.l10n.text('Filter trading period'),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: MaterialLocalizations.of(context).closeButtonTooltip,
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final type in TradingDateFilterType.values)
                  ChoiceChip(
                    selected: _type == type,
                    label: Text(
                      context.l10n.text(switch (type) {
                        TradingDateFilterType.allTime => 'All time',
                        TradingDateFilterType.specificDate => 'Specific date',
                        TradingDateFilterType.dateRange => 'Date range',
                        TradingDateFilterType.month => 'Month',
                        TradingDateFilterType.year => 'Year',
                      }),
                    ),
                    onSelected: (_) => setState(() {
                      _type = type;
                      _error = null;
                    }),
                  ),
              ],
            ),
            const SizedBox(height: 18),
            _selectionControl(),
            if (_error != null) ...[
              const SizedBox(height: 10),
              Text(
                context.l10n.text(_error!),
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(
                      context,
                      TradingDateFilter.allTime(
                        timezone: widget.initial.timezone,
                        timezoneOffsetMinutes:
                            widget.initial.timezoneOffsetMinutes,
                        dataSource: widget.initial.dataSource,
                        mt5AccountId: widget.initial.mt5AccountId,
                      ),
                    ),
                    child: Text(context.l10n.text('Reset')),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton(
                    onPressed: _apply,
                    child: Text(context.l10n.text('Apply filter')),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _selectionControl() {
    switch (_type) {
      case TradingDateFilterType.allTime:
        return ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.all_inclusive),
          title: Text(
            context.l10n.text('Include all eligible trading history'),
          ),
        );
      case TradingDateFilterType.specificDate:
        return ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.today_outlined),
          title: Text(DateFormat.yMMMMd().format(_specificDate)),
          trailing: const Icon(Icons.edit_calendar_outlined),
          onTap: _pickSpecificDate,
        );
      case TradingDateFilterType.dateRange:
        return ListTile(
          contentPadding: EdgeInsets.zero,
          leading: const Icon(Icons.date_range_outlined),
          title: Text(
            '${DateFormat.yMMMd().format(_range.start)} – '
            '${DateFormat.yMMMd().format(_range.end)}',
          ),
          trailing: const Icon(Icons.edit_calendar_outlined),
          onTap: _pickRange,
        );
      case TradingDateFilterType.month:
        return Row(
          children: [
            Expanded(
              child: DropdownButtonFormField<int>(
                initialValue: _month,
                decoration: InputDecoration(
                  labelText: context.l10n.text('Month'),
                ),
                items: [
                  for (var month = 1; month <= 12; month++)
                    DropdownMenuItem(
                      value: month,
                      child: Text(
                        DateFormat.MMMM().format(DateTime(2024, month)),
                      ),
                    ),
                ],
                onChanged: (value) => setState(() => _month = value ?? _month),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(child: _yearField()),
          ],
        );
      case TradingDateFilterType.year:
        return _yearField();
    }
  }

  Widget _yearField() => DropdownButtonFormField<int>(
    initialValue: _years.contains(_year) ? _year : _years.first,
    decoration: InputDecoration(labelText: context.l10n.text('Year')),
    items: [
      for (final year in _years)
        DropdownMenuItem(value: year, child: Text('$year')),
    ],
    onChanged: (value) => setState(() => _year = value ?? _year),
  );

  Future<void> _pickSpecificDate() async {
    final selected = await showDatePicker(
      context: context,
      initialDate: _specificDate,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 366)),
    );
    if (selected != null) setState(() => _specificDate = selected);
  }

  Future<void> _pickRange() async {
    final selected = await showDateRangePicker(
      context: context,
      initialDateRange: _range,
      firstDate: DateTime(2000),
      lastDate: DateTime.now().add(const Duration(days: 366)),
    );
    if (selected != null) setState(() => _range = selected);
  }

  void _apply() {
    try {
      final result = switch (_type) {
        TradingDateFilterType.allTime => TradingDateFilter.allTime(
          timezone: widget.initial.timezone,
          timezoneOffsetMinutes: widget.initial.timezoneOffsetMinutes,
          dataSource: widget.initial.dataSource,
          mt5AccountId: widget.initial.mt5AccountId,
        ),
        TradingDateFilterType.specificDate => TradingDateFilter.specificDate(
          _specificDate,
          timezone: widget.initial.timezone,
          timezoneOffsetMinutes: widget.initial.timezoneOffsetMinutes,
          dataSource: widget.initial.dataSource,
          mt5AccountId: widget.initial.mt5AccountId,
        ),
        TradingDateFilterType.dateRange => TradingDateFilter.dateRange(
          _range.start,
          _range.end,
          timezone: widget.initial.timezone,
          timezoneOffsetMinutes: widget.initial.timezoneOffsetMinutes,
          dataSource: widget.initial.dataSource,
          mt5AccountId: widget.initial.mt5AccountId,
        ),
        TradingDateFilterType.month => TradingDateFilter.month(
          _year,
          _month,
          timezone: widget.initial.timezone,
          timezoneOffsetMinutes: widget.initial.timezoneOffsetMinutes,
          dataSource: widget.initial.dataSource,
          mt5AccountId: widget.initial.mt5AccountId,
        ),
        TradingDateFilterType.year => TradingDateFilter.year(
          _year,
          timezone: widget.initial.timezone,
          timezoneOffsetMinutes: widget.initial.timezoneOffsetMinutes,
          dataSource: widget.initial.dataSource,
          mt5AccountId: widget.initial.mt5AccountId,
        ),
      };
      Navigator.pop(context, result);
    } on ArgumentError {
      setState(() => _error = 'End date cannot be earlier than start date.');
    }
  }
}
