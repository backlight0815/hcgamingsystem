import 'package:timezone/data/latest.dart' as timezone_data;
import 'package:timezone/timezone.dart' as timezone;

import 'prop_firm_models.dart';

class TradingDayService {
  const TradingDayService();

  static bool _initialized = false;

  String tradingDayKey(DateTime instant, PortfolioRiskPolicy policy) {
    final local = _localTime(instant, policy);
    final resetMinutes = policy.resetHourUtc * 60 + policy.resetMinute;
    final currentMinutes = local.hour * 60 + local.minute;
    final tradingDate = currentMinutes < resetMinutes
        ? local.subtract(const Duration(days: 1))
        : local;
    return '${tradingDate.year.toString().padLeft(4, '0')}-'
        '${tradingDate.month.toString().padLeft(2, '0')}-'
        '${tradingDate.day.toString().padLeft(2, '0')}';
  }

  bool hasTradingDayChanged({
    required DateTime previousInstant,
    required DateTime currentInstant,
    required PortfolioRiskPolicy policy,
  }) =>
      tradingDayKey(previousInstant, policy) !=
      tradingDayKey(currentInstant, policy);

  /// Converts a stored instant to the configured prop-firm timezone for
  /// display. Firestore continues to store the original instant as UTC.
  DateTime configuredTime(DateTime instant, PortfolioRiskPolicy policy) =>
      _localTime(instant, policy);

  DateTime nextResetAt(DateTime instant, PortfolioRiskPolicy policy) {
    _ensureInitialized();
    try {
      if (policy.resetTimeZone != 'UTC') {
        final location = timezone.getLocation(policy.resetTimeZone);
        final localNow = timezone.TZDateTime.from(instant.toUtc(), location);
        var candidate = timezone.TZDateTime(
          location,
          localNow.year,
          localNow.month,
          localNow.day,
          policy.resetHourUtc,
          policy.resetMinute,
        );
        if (!candidate.isAfter(localNow)) {
          candidate = timezone.TZDateTime(
            location,
            localNow.year,
            localNow.month,
            localNow.day + 1,
            policy.resetHourUtc,
            policy.resetMinute,
          );
        }
        return candidate.toUtc();
      }
    } on Object {
      // Fall through to the configured fixed-offset calculation.
    }
    final offset = Duration(minutes: policy.resetUtcOffsetMinutes);
    final shiftedNow = instant.toUtc().add(offset);
    var shiftedCandidate = DateTime.utc(
      shiftedNow.year,
      shiftedNow.month,
      shiftedNow.day,
      policy.resetHourUtc,
      policy.resetMinute,
    );
    if (!shiftedCandidate.isAfter(shiftedNow)) {
      shiftedCandidate = shiftedCandidate.add(const Duration(days: 1));
    }
    return shiftedCandidate.subtract(offset);
  }

  DateTime _localTime(DateTime instant, PortfolioRiskPolicy policy) {
    _ensureInitialized();
    try {
      if (policy.resetTimeZone == 'UTC') {
        final utc = timezone.TZDateTime.from(instant.toUtc(), timezone.UTC);
        return policy.resetUtcOffsetMinutes == 0
            ? utc
            : utc.add(Duration(minutes: policy.resetUtcOffsetMinutes));
      }
      final location = timezone.getLocation(policy.resetTimeZone);
      return timezone.TZDateTime.from(instant.toUtc(), location);
    } on Object {
      return instant.toUtc().add(
        Duration(minutes: policy.resetUtcOffsetMinutes),
      );
    }
  }

  void _ensureInitialized() {
    if (_initialized) return;
    timezone_data.initializeTimeZones();
    _initialized = true;
  }
}
