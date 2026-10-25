class TradingTimeline {
  const TradingTimeline({
    required this.openDate,
    required this.closeDate,
    required this.closeRolledForward,
    required this.isValid,
  });

  final DateTime? openDate;
  final DateTime? closeDate;
  final bool closeRolledForward;
  final bool isValid;
}

class TradingTimeService {
  static const malaysia = 'malaysia';
  static const mt5 = 'mt5';
  static const malaysiaOffsetMinutes = 480;
  static const defaultMt5OffsetMinutes = 180;

  static const mt5Offsets = {120: 'UTC+2', 180: 'UTC+3'};

  String normalizeMode(String? mode) {
    return mode == mt5 ? mt5 : malaysia;
  }

  int normalizeOffset(int? offsetMinutes, String? mode) {
    if (normalizeMode(mode) == malaysia) {
      return malaysiaOffsetMinutes;
    }

    return mt5Offsets.containsKey(offsetMinutes)
        ? offsetMinutes!
        : defaultMt5OffsetMinutes;
  }

  String shortLabel(String? mode, int? offsetMinutes) {
    final normalizedMode = normalizeMode(mode);
    if (normalizedMode == malaysia) {
      return 'MYT';
    }

    return 'MT5 ${mt5Offsets[normalizeOffset(offsetMinutes, normalizedMode)]}';
  }

  TradingTimeline toMalaysiaTimeline({
    required DateTime? openValue,
    required DateTime? closeValue,
    required String sourceMode,
    required int sourceOffsetMinutes,
  }) {
    var sourceClose = closeValue;
    var closeRolledForward = false;

    if (openValue != null &&
        sourceClose != null &&
        !sourceClose.isAfter(openValue) &&
        _isSameDate(openValue, sourceClose)) {
      sourceClose = sourceClose.add(const Duration(days: 1));
      closeRolledForward = true;
    }

    final adjustment =
        malaysiaOffsetMinutes - _offsetMinutes(sourceMode, sourceOffsetMinutes);
    final open = openValue?.add(Duration(minutes: adjustment));
    final close = sourceClose?.add(Duration(minutes: adjustment));

    return TradingTimeline(
      openDate: open,
      closeDate: close,
      closeRolledForward: closeRolledForward,
      isValid: open != null && close != null && close.isAfter(open),
    );
  }

  DateTime fromMalaysia({
    required DateTime value,
    required String targetMode,
    required int targetOffsetMinutes,
  }) {
    return value.add(
      Duration(
        minutes:
            _offsetMinutes(targetMode, targetOffsetMinutes) -
            malaysiaOffsetMinutes,
      ),
    );
  }

  DateTime convertInputBetween({
    required DateTime value,
    required String sourceMode,
    required int sourceOffsetMinutes,
    required String targetMode,
    required int targetOffsetMinutes,
  }) {
    return value.add(
      Duration(
        minutes:
            _offsetMinutes(targetMode, targetOffsetMinutes) -
            _offsetMinutes(sourceMode, sourceOffsetMinutes),
      ),
    );
  }

  int _offsetMinutes(String? mode, int? offsetMinutes) {
    return normalizeMode(mode) == mt5
        ? normalizeOffset(offsetMinutes, mode)
        : malaysiaOffsetMinutes;
  }

  bool _isSameDate(DateTime left, DateTime right) {
    return left.year == right.year &&
        left.month == right.month &&
        left.day == right.day;
  }
}
