import 'package:cloud_firestore/cloud_firestore.dart';

class TradingPair {
  const TradingPair({
    required this.id,
    required this.symbol,
    this.description = '',
    this.pipFactor = 0.0001,
    this.pipDecimal = 1,
  });

  final String id;
  final String symbol;
  final String description;
  final double pipFactor;
  final int pipDecimal;

  String get displayName {
    final text = description.trim();
    return text.isEmpty
        ? symbol.toUpperCase()
        : '${symbol.toUpperCase()} - $text';
  }

  TradingPair copyWith({
    String? id,
    String? symbol,
    String? description,
    double? pipFactor,
    int? pipDecimal,
  }) {
    return TradingPair(
      id: id ?? this.id,
      symbol: symbol ?? this.symbol,
      description: description ?? this.description,
      pipFactor: pipFactor ?? this.pipFactor,
      pipDecimal: pipDecimal ?? this.pipDecimal,
    );
  }

  factory TradingPair.fromFirestore(
    DocumentSnapshot<Map<String, dynamic>> doc,
  ) {
    final data = doc.data() ?? const <String, dynamic>{};

    final symbol = (data['symbol'] ?? doc.id).toString().toUpperCase();

    return TradingPair(
      id: doc.id,
      symbol: symbol,
      description: (data['description'] ?? '').toString(),
      pipFactor: _normalizePipFactor(
        symbol,
        _asDouble(data['pip_factor'], fallback: 0.0001),
      ),
      pipDecimal: _asInt(data['pip_decimal'], fallback: 1),
    );
  }

  factory TradingPair.fromJson(Map<String, dynamic> data) {
    final symbol = (data['symbol'] ?? '').toString().toUpperCase();
    return TradingPair(
      id: (data['id'] ?? symbol).toString(),
      symbol: symbol,
      description: (data['description'] ?? '').toString(),
      pipFactor: _normalizePipFactor(
        symbol,
        _asDouble(data['pip_factor'], fallback: 0.0001),
      ),
      pipDecimal: _asInt(data['pip_decimal'], fallback: 1),
    );
  }

  Map<String, Object?> toFirestore() {
    return {
      'symbol': symbol.toUpperCase(),
      'description': description,
      'pip_factor': pipFactor,
      'pip_decimal': pipDecimal,
    };
  }

  Map<String, Object?> toJson() => {
    'id': id,
    'symbol': symbol.toUpperCase(),
    'description': description,
    'pip_factor': pipFactor,
    'pip_decimal': pipDecimal,
  };

  static List<TradingPair> get defaults {
    return const [
      TradingPair(
        id: 'xauusd',
        symbol: 'XAUUSD',
        description: 'Gold',
        pipFactor: 0.1,
        pipDecimal: 1,
      ),
      TradingPair(
        id: 'eurusd',
        symbol: 'EURUSD',
        description: 'Euro / US Dollar',
        pipFactor: 0.0001,
        pipDecimal: 1,
      ),
      TradingPair(
        id: 'gbpusd',
        symbol: 'GBPUSD',
        description: 'British Pound / US Dollar',
        pipFactor: 0.0001,
        pipDecimal: 1,
      ),
      TradingPair(
        id: 'usdjpy',
        symbol: 'USDJPY',
        description: 'US Dollar / Japanese Yen',
        pipFactor: 0.01,
        pipDecimal: 1,
      ),
      TradingPair(
        id: 'btc-usd',
        symbol: 'BTCUSD',
        description: 'Bitcoin / US Dollar',
        pipFactor: 1,
        pipDecimal: 0,
      ),
    ];
  }
}

double _asDouble(Object? value, {required double fallback}) {
  if (value is num) {
    return value.toDouble();
  }

  return double.tryParse(value?.toString() ?? '') ?? fallback;
}

int _asInt(Object? value, {required int fallback}) {
  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value?.toString() ?? '') ?? fallback;
}

double _normalizePipFactor(String symbol, double pipFactor) {
  if (symbol.toUpperCase() == 'XAUUSD' && pipFactor <= 0.01) {
    return 0.1;
  }

  return pipFactor;
}
