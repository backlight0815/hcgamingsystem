enum TradingDateFilterType { allTime, specificDate, dateRange, month, year }

class TradingDateFilter {
  const TradingDateFilter._({
    required this.type,
    this.startDate,
    this.endDate,
    this.month,
    this.year,
    this.timezone = 'Asia/Kuala_Lumpur',
    this.timezoneOffsetMinutes = 480,
    this.dataSource = '',
    this.mt5AccountId = '',
  });

  const TradingDateFilter.allTime({
    String timezone = 'Asia/Kuala_Lumpur',
    int timezoneOffsetMinutes = 480,
    String dataSource = '',
    String mt5AccountId = '',
  }) : this._(
         type: TradingDateFilterType.allTime,
         timezone: timezone,
         timezoneOffsetMinutes: timezoneOffsetMinutes,
         dataSource: dataSource,
         mt5AccountId: mt5AccountId,
       );

  factory TradingDateFilter.specificDate(
    DateTime date, {
    String timezone = 'Asia/Kuala_Lumpur',
    int timezoneOffsetMinutes = 480,
    String dataSource = '',
    String mt5AccountId = '',
  }) => TradingDateFilter._(
    type: TradingDateFilterType.specificDate,
    startDate: _dateOnly(date),
    endDate: _dateOnly(date),
    timezone: timezone,
    timezoneOffsetMinutes: timezoneOffsetMinutes,
    dataSource: dataSource,
    mt5AccountId: mt5AccountId,
  );

  factory TradingDateFilter.dateRange(
    DateTime start,
    DateTime end, {
    String timezone = 'Asia/Kuala_Lumpur',
    int timezoneOffsetMinutes = 480,
    String dataSource = '',
    String mt5AccountId = '',
  }) {
    final normalizedStart = _dateOnly(start);
    final normalizedEnd = _dateOnly(end);
    if (normalizedEnd.isBefore(normalizedStart)) {
      throw ArgumentError.value(
        end,
        'end',
        'End date cannot be earlier than start date.',
      );
    }
    return TradingDateFilter._(
      type: TradingDateFilterType.dateRange,
      startDate: normalizedStart,
      endDate: normalizedEnd,
      timezone: timezone,
      timezoneOffsetMinutes: timezoneOffsetMinutes,
      dataSource: dataSource,
      mt5AccountId: mt5AccountId,
    );
  }

  factory TradingDateFilter.month(
    int year,
    int month, {
    String timezone = 'Asia/Kuala_Lumpur',
    int timezoneOffsetMinutes = 480,
    String dataSource = '',
    String mt5AccountId = '',
  }) {
    if (month < 1 || month > 12) {
      throw ArgumentError.value(month, 'month', 'Month must be from 1 to 12.');
    }
    return TradingDateFilter._(
      type: TradingDateFilterType.month,
      month: month,
      year: year,
      timezone: timezone,
      timezoneOffsetMinutes: timezoneOffsetMinutes,
      dataSource: dataSource,
      mt5AccountId: mt5AccountId,
    );
  }

  factory TradingDateFilter.year(
    int year, {
    String timezone = 'Asia/Kuala_Lumpur',
    int timezoneOffsetMinutes = 480,
    String dataSource = '',
    String mt5AccountId = '',
  }) => TradingDateFilter._(
    type: TradingDateFilterType.year,
    year: year,
    timezone: timezone,
    timezoneOffsetMinutes: timezoneOffsetMinutes,
    dataSource: dataSource,
    mt5AccountId: mt5AccountId,
  );

  final TradingDateFilterType type;
  final DateTime? startDate;
  final DateTime? endDate;
  final int? month;
  final int? year;
  final String timezone;
  final int timezoneOffsetMinutes;
  final String dataSource;
  final String mt5AccountId;

  bool get isActive => type != TradingDateFilterType.allTime;

  DateTime? get startInclusive => switch (type) {
    TradingDateFilterType.allTime => null,
    TradingDateFilterType.specificDate ||
    TradingDateFilterType.dateRange => startDate,
    TradingDateFilterType.month => DateTime(year!, month!),
    TradingDateFilterType.year => DateTime(year!),
  };

  DateTime? get endExclusive => switch (type) {
    TradingDateFilterType.allTime => null,
    TradingDateFilterType.specificDate ||
    TradingDateFilterType.dateRange => endDate?.add(const Duration(days: 1)),
    TradingDateFilterType.month =>
      month == 12 ? DateTime(year! + 1) : DateTime(year!, month! + 1),
    TradingDateFilterType.year => DateTime(year! + 1),
  };

  bool matches(DateTime value) {
    if (!isActive) return true;
    final zoned = value.isUtc
        ? value.add(Duration(minutes: timezoneOffsetMinutes))
        : value;
    final comparable = _dateOnly(zoned);
    final start = startInclusive;
    final end = endExclusive;
    return (start == null || !comparable.isBefore(start)) &&
        (end == null || comparable.isBefore(end));
  }

  TradingDateFilter withScope({String? dataSource, String? mt5AccountId}) {
    return TradingDateFilter._(
      type: type,
      startDate: startDate,
      endDate: endDate,
      month: month,
      year: year,
      timezone: timezone,
      timezoneOffsetMinutes: timezoneOffsetMinutes,
      dataSource: dataSource ?? this.dataSource,
      mt5AccountId: mt5AccountId ?? this.mt5AccountId,
    );
  }

  Map<String, Object?> toJson() => {
    'type': type.name,
    'start_date': startDate?.toIso8601String(),
    'end_date': endDate?.toIso8601String(),
    'month': month,
    'year': year,
    'timezone': timezone,
    'timezone_offset_minutes': timezoneOffsetMinutes,
    'data_source': dataSource,
    'mt5_account_id': mt5AccountId,
  };

  factory TradingDateFilter.fromJson(Map<String, dynamic>? json) {
    if (json == null) return const TradingDateFilter.allTime();
    final typeName = '${json['type'] ?? ''}';
    final type = TradingDateFilterType.values.where((item) {
      return item.name == typeName;
    }).firstOrNull;
    final timezone = '${json['timezone'] ?? 'Asia/Kuala_Lumpur'}';
    final offset = _integer(json['timezone_offset_minutes'], 480);
    final source = '${json['data_source'] ?? ''}';
    final account = '${json['mt5_account_id'] ?? ''}';
    switch (type) {
      case TradingDateFilterType.specificDate:
        final date = DateTime.tryParse('${json['start_date'] ?? ''}');
        return date == null
            ? const TradingDateFilter.allTime()
            : TradingDateFilter.specificDate(
                date,
                timezone: timezone,
                timezoneOffsetMinutes: offset,
                dataSource: source,
                mt5AccountId: account,
              );
      case TradingDateFilterType.dateRange:
        final start = DateTime.tryParse('${json['start_date'] ?? ''}');
        final end = DateTime.tryParse('${json['end_date'] ?? ''}');
        if (start == null || end == null || end.isBefore(start)) {
          return const TradingDateFilter.allTime();
        }
        return TradingDateFilter.dateRange(
          start,
          end,
          timezone: timezone,
          timezoneOffsetMinutes: offset,
          dataSource: source,
          mt5AccountId: account,
        );
      case TradingDateFilterType.month:
        final month = _integer(json['month'], 0);
        final year = _integer(json['year'], 0);
        return month < 1 || month > 12 || year < 1
            ? const TradingDateFilter.allTime()
            : TradingDateFilter.month(
                year,
                month,
                timezone: timezone,
                timezoneOffsetMinutes: offset,
                dataSource: source,
                mt5AccountId: account,
              );
      case TradingDateFilterType.year:
        final year = _integer(json['year'], 0);
        return year < 1
            ? const TradingDateFilter.allTime()
            : TradingDateFilter.year(
                year,
                timezone: timezone,
                timezoneOffsetMinutes: offset,
                dataSource: source,
                mt5AccountId: account,
              );
      case TradingDateFilterType.allTime:
      case null:
        return TradingDateFilter.allTime(
          timezone: timezone,
          timezoneOffsetMinutes: offset,
          dataSource: source,
          mt5AccountId: account,
        );
    }
  }

  String get cacheKey => <Object?>[
    type.name,
    startDate?.toIso8601String(),
    endDate?.toIso8601String(),
    month,
    year,
    timezone,
    timezoneOffsetMinutes,
    dataSource,
    mt5AccountId,
  ].join('|');
}

DateTime _dateOnly(DateTime value) =>
    DateTime(value.year, value.month, value.day);

int _integer(Object? value, int fallback) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
