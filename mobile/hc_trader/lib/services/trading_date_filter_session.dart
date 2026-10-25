import '../models/trading_date_filter.dart';

/// Keeps a temporary journal filter while the user moves between related
/// journal, statistics, rating, and report screens. It is intentionally not
/// persisted across app launches.
class TradingDateFilterSession {
  TradingDateFilterSession._();

  static final _filters = <String, TradingDateFilter>{};

  static TradingDateFilter read(
    String userId, {
    String timezone = 'Asia/Kuala_Lumpur',
    int timezoneOffsetMinutes = 480,
  }) =>
      _filters[userId] ??
      TradingDateFilter.allTime(
        timezone: timezone,
        timezoneOffsetMinutes: timezoneOffsetMinutes,
      );

  static void write(String userId, TradingDateFilter filter) {
    _filters[userId] = filter;
  }

  static void clear(String userId) => _filters.remove(userId);
}
