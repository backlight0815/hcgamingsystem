import 'dart:math' as math;

import 'package:intl/intl.dart';

final _money = NumberFormat('#,##0.00');
final _compactMoney = NumberFormat.compactCurrency(
  symbol: '',
  decimalDigits: 1,
);
final _date = DateFormat('d MMM yyyy');
final _dateTime = DateFormat('d MMM yyyy, HH:mm');
final _time = DateFormat('HH:mm');

String formatMoney(double value) {
  return '${_money.format(value)}u';
}

String formatCurrencyMoney(double value, String currency) {
  final normalizedCurrency = currency.trim().toUpperCase();
  if (normalizedCurrency.isEmpty) return formatMoney(value);
  return '${_money.format(value)} $normalizedCurrency';
}

String formatSignedMoney(double value) {
  final sign = value > 0
      ? '+'
      : value < 0
      ? '-'
      : '';
  return '$sign${_money.format(value.abs())}u';
}

String formatSignedCurrencyMoney(double value, String currency) {
  final normalizedCurrency = currency.trim().toUpperCase();
  final sign = value > 0
      ? '+'
      : value < 0
      ? '-'
      : '';
  final amount = '$sign${_money.format(value.abs())}';
  return normalizedCurrency.isEmpty
      ? '${amount}u'
      : '$amount $normalizedCurrency';
}

String maskAccountIdentifier(Object? value) {
  final raw = value?.toString().trim() ?? '';
  if (raw.isEmpty) return '----';
  if (raw.length <= 4) return raw;
  return '${List.filled(math.max(4, raw.length - 4), '•').join()}${raw.substring(raw.length - 4)}';
}

String formatCompactMoney(double value) {
  return '${_compactMoney.format(value)}u';
}

String formatPercent(double value) {
  return '${value.toStringAsFixed(2)}%';
}

String formatDate(DateTime value) {
  return _date.format(value);
}

String formatDateTime(DateTime value) {
  return _dateTime.format(value);
}

String formatTime(DateTime value) {
  return _time.format(value);
}
