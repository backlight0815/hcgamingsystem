import '../../models/mt5_import_models.dart';

class Mt5ParsedVolume {
  const Mt5ParsedVolume({required this.requested, required this.executed});

  final Mt5Decimal requested;
  final Mt5Decimal executed;
}

class Mt5ValueParser {
  const Mt5ValueParser();

  String normalizeLabel(Object? value) => '${value ?? ''}'
      .replaceAll('\u00a0', ' ')
      .trim()
      .toLowerCase()
      .replaceAll('&', 'and')
      .replaceAll(RegExp(r'[\s_\-/:：()\[\].]+'), '');

  String cleanText(Object? value) => '${value ?? ''}'
      .replaceAll('\u00a0', ' ')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim();

  Mt5Decimal decimal(Object? value, {bool required = false}) {
    final text = cleanText(value);
    if (text.isEmpty) {
      if (required) throw const FormatException('A numeric value is required.');
      return Mt5Decimal.zero;
    }
    return Mt5Decimal.parse(text);
  }

  Mt5Decimal? nullableDecimal(Object? value) {
    final text = cleanText(value);
    return text.isEmpty ? null : Mt5Decimal.parse(text);
  }

  Mt5ParsedVolume volumePair(Object? value) {
    final text = cleanText(value);
    if (text.isEmpty) {
      throw const FormatException('Order volume is required.');
    }
    final parts = text.split(RegExp(r'\s*/\s*'));
    final requested = Mt5Decimal.parse(parts.first);
    final executed = parts.length > 1 ? Mt5Decimal.parse(parts[1]) : requested;
    return Mt5ParsedVolume(requested: requested, executed: executed);
  }

  DateTime dateTime(Object? value, {required bool date1904}) {
    final text = cleanText(value);
    if (text.isEmpty) {
      throw const FormatException('Date and time are required.');
    }
    final serial = double.tryParse(text);
    if (serial != null && serial > 1) {
      final wholeDays = serial.floor();
      final milliseconds = ((serial - wholeDays) * Duration.millisecondsPerDay)
          .round();
      final base = date1904 ? DateTime(1904) : DateTime(1899, 12, 30);
      return base.add(Duration(days: wholeDays, milliseconds: milliseconds));
    }
    final iso = DateTime.tryParse(text);
    if (iso != null) return iso;
    final match = RegExp(
      r'^(\d{4})[.\-/](\d{1,2})[.\-/](\d{1,2})(?:\s+|T)(\d{1,2}):(\d{2})(?::(\d{2}))?$',
    ).firstMatch(text);
    if (match != null) {
      return DateTime(
        int.parse(match.group(1)!),
        int.parse(match.group(2)!),
        int.parse(match.group(3)!),
        int.parse(match.group(4)!),
        int.parse(match.group(5)!),
        int.tryParse(match.group(6) ?? '') ?? 0,
      );
    }
    final localized = RegExp(
      r'^(\d{1,2})[.\-/](\d{1,2})[.\-/](\d{4})(?:\s+|T)(\d{1,2}):(\d{2})(?::(\d{2}))?$',
    ).firstMatch(text);
    if (localized != null) {
      return DateTime(
        int.parse(localized.group(3)!),
        int.parse(localized.group(2)!),
        int.parse(localized.group(1)!),
        int.parse(localized.group(4)!),
        int.parse(localized.group(5)!),
        int.tryParse(localized.group(6) ?? '') ?? 0,
      );
    }
    throw FormatException('Unsupported MT5 date: $text');
  }

  String orderType(Object? value) {
    final normalized = normalizeLabel(value);
    return switch (normalized) {
      'buy' || '买入' || '買入' => 'buy',
      'sell' || '卖出' || '賣出' => 'sell',
      'buylimit' || '买入限价' || '買入限價' => 'buy_limit',
      'selllimit' || '卖出限价' || '賣出限價' => 'sell_limit',
      'buystop' || '买入止损' || '買入止損' => 'buy_stop',
      'sellstop' || '卖出止损' || '賣出止損' => 'sell_stop',
      'buystoplimit' => 'buy_stop_limit',
      'sellstoplimit' => 'sell_stop_limit',
      _ => normalized,
    };
  }

  String orderState(Object? value) {
    final normalized = normalizeLabel(value);
    return switch (normalized) {
      'filled' || 'executed' || '成交' || '已成交' => 'filled',
      'partiallyfilled' || 'partialfill' || '部分成交' => 'partially_filled',
      'placed' || 'pending' || '挂单' || '待处理' => 'pending',
      'cancelled' ||
      'canceled' ||
      'deleted' ||
      'removed' ||
      '已取消' => 'cancelled',
      'expired' || '已过期' || '已過期' => 'expired',
      'rejected' || 'invalid' || '已拒绝' || '已拒絕' => 'rejected',
      _ => normalized,
    };
  }

  String dealDirection(Object? value) {
    final normalized = normalizeLabel(value);
    return switch (normalized) {
      'in' || 'entryin' || '进入' || '進入' => 'in',
      'out' || 'entryout' || '退出' => 'out',
      'inout' || 'reversal' || '反转' || '反轉' => 'in_out',
      'outby' || 'closeby' || '对冲平仓' || '對沖平倉' => 'out_by',
      _ => normalized,
    };
  }

  String ticket(Object? value) {
    final text = cleanText(value);
    if (text.isEmpty) return '';
    if (RegExp(r'^\d+\.0+$').hasMatch(text)) return text.split('.').first;
    return text;
  }
}

class Mt5AliasRegistry {
  Mt5AliasRegistry(this.values);

  final Mt5ValueParser values;

  static const sectionAliases = <Mt5ImportSection, Set<String>>{
    Mt5ImportSection.positions: {
      'positions',
      'positionhistory',
      '仓位',
      '持仓',
      '持倉',
    },
    Mt5ImportSection.orders: {'orders', 'orderhistory', '订单', '訂單'},
    Mt5ImportSection.deals: {'deals', 'dealhistory', '成交', '成交历史', '成交歷史'},
    Mt5ImportSection.results: {'results', 'summaryresults', '结果', '結果'},
  };

  static const headerAliases = <String, Set<String>>{
    'time': {'time', 'datetime', 'dateandtime', '时间', '時間'},
    'position_ticket': {
      'position',
      'positionid',
      'positionticket',
      '持仓',
      '持倉',
      '仓位',
    },
    'order_ticket': {'order', 'orderid', 'orderticket', '订单', '訂單'},
    'deal_ticket': {'deal', 'dealid', 'dealticket', '成交'},
    'symbol': {'symbol', 'instrument', 'market', '品种', '品種', '交易品种', '交易品種'},
    'type': {'type', 'ordertype', 'dealtype', '类型', '類型'},
    'direction': {'direction', 'entry', 'dealentry', '方向', '进出', '進出'},
    'volume': {'volume', 'lots', 'lotsize', 'size', '交易量', '手数', '手數'},
    'price': {'price', '价格', '價格'},
    'stop_loss': {'sl', 'stoploss', '止损', '止損'},
    'take_profit': {'tp', 'takeprofit', '止盈'},
    'commission': {'commission', 'commissions', '佣金', '手续费', '手續費'},
    'fee': {'fee', 'fees', '费用', '費用'},
    'swap': {'swap', 'swaps', 'storage', '库存费', '庫存費', '隔夜利息'},
    'profit': {'profit', 'pnl', 'profitloss', '获利', '獲利', '盈亏', '盈虧'},
    'balance': {'balance', '余额', '餘額'},
    'state': {'state', 'status', '状态', '狀態'},
    'comment': {'comment', 'comments', '备注', '備註', '注释', '註釋'},
  };

  Mt5ImportSection? sectionFor(String value) {
    final normalized = values.normalizeLabel(value);
    for (final entry in sectionAliases.entries) {
      if (entry.value.map(values.normalizeLabel).contains(normalized)) {
        return entry.key;
      }
    }
    return null;
  }

  String? headerMeaning(String value) {
    final normalized = values.normalizeLabel(value);
    for (final entry in headerAliases.entries) {
      if (entry.value.map(values.normalizeLabel).contains(normalized)) {
        return entry.key;
      }
    }
    return null;
  }
}
