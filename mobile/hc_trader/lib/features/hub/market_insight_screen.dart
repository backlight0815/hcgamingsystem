import 'package:flutter/material.dart';

import '../../repositories/trading_repository.dart';
import '../../services/market_insight_api_service.dart';

class MarketInsightScreen extends StatefulWidget {
  const MarketInsightScreen({super.key, required this.repository});

  final TradingRepository repository;

  @override
  State<MarketInsightScreen> createState() => _MarketInsightScreenState();
}

class _MarketInsightScreenState extends State<MarketInsightScreen> {
  MarketInsightApiService? get _api => widget.repository.apiClient == null
      ? null
      : MarketInsightApiService(widget.repository.apiClient!);

  List<Map<String, dynamic>> _instruments = [];
  List<Map<String, dynamic>> _history = [];
  Map<String, dynamic>? _analysis;
  Map<String, dynamic>? _quote;
  Map<String, dynamic>? _providerStatus;
  String _source = 'MOCK';
  String? _symbol;
  String _timeframe = 'H1';
  String? _error;
  bool _loading = true;
  bool _refreshing = false;

  static const _timeframes = ['M15', 'M30', 'H1', 'H4', 'D1'];
  static const _names = {
    'bollinger': 'Bollinger Bands',
    'ema': 'Exponential Moving Averages',
    'macd': 'MACD',
    'rsi': 'Relative Strength Index',
    'atr': 'Average True Range',
    'adr': 'Average Daily Range',
  };

  @override
  void initState() {
    super.initState();
    _load(initial: true);
  }

  Future<void> _load({bool initial = false}) async {
    final api = _api;
    if (api == null) {
      setState(() {
        _loading = false;
        _error =
            'Market Insight requires a connected HC Trading Hub API account.';
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
      _analysis = null;
      _quote = null;
      _history = [];
    });
    try {
      if (initial) {
        final choices = await api.instruments();
        _source = (choices['source'] ?? 'MOCK').toString();
        _instruments = ((choices['instruments'] as List?) ?? [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
        _symbol = _instruments.isEmpty
            ? null
            : (_instruments.firstWhere(
                    (item) => item['symbol'] == 'XAUUSD',
                    orElse: () => _instruments.first,
                  )['symbol']
                  as String?);
      }
      final symbol = _symbol;
      if (symbol != null) {
        final latest = await api.latest(symbol, _timeframe);
        _source = (latest['source'] ?? _source).toString();
        _quote = latest['quote'] is Map
            ? Map<String, dynamic>.from(latest['quote'] as Map)
            : null;
        _providerStatus = latest['provider_status'] is Map
            ? Map<String, dynamic>.from(latest['provider_status'] as Map)
            : null;
        final history = await api.history(symbol, _timeframe);
        _analysis = latest['analysis'] is Map
            ? Map<String, dynamic>.from(latest['analysis'] as Map)
            : null;
        _history = ((history['history'] as List?) ?? [])
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
      }
    } catch (error) {
      _error = error.toString();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _refresh() async {
    final api = _api;
    final symbol = _symbol;
    if (api == null || symbol == null) return;
    setState(() {
      _refreshing = true;
      _error = null;
    });
    try {
      await api.refresh(symbol, _timeframe);
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  Future<void> _openSnapshot(int id) async {
    try {
      final data = await _api!.snapshot(id);
      final item = data['analysis'];
      if (!mounted || item is! Map) return;
      final analysis = Map<String, dynamic>.from(item);
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        builder: (context) => SafeArea(
          child: FractionallySizedBox(
            heightFactor: .85,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Saved insight #$id',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 8),
                  _analysisBody(analysis),
                ],
              ),
            ),
          ),
        ),
      );
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Unable to open snapshot: $error')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () => _load(initial: _instruments.isEmpty),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'AI Market Insight',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 4),
          Text(_source == 'REAL'
              ? 'XAUUSD · Gold Spot / US Dollar · Twelve Data'
              : 'Rule-Based Indicator Analysis · Demo'),
          const SizedBox(height: 12),
          Card(
            color: _source == 'REAL'
                ? const Color(0xFF244337)
                : const Color(0xFF154A43),
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Text(
                _source == 'REAL'
                    ? 'REAL DATA · Twelve Data\n${_providerStatus?['status'] ?? 'NOT_SYNCHRONIZED'}. Scores are indicator agreement, not win probabilities.'
                    : 'DEMO / SIMULATED DATA\nPrices and signals are generated for education and testing. Scores are not win probabilities.',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (_source == 'REAL')
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('XAUUSD · Latest available price',
                        style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 6),
                    Text(_quote == null
                        ? 'Unavailable'
                        : '${_quote!['price']} USD / troy oz',
                        style: Theme.of(context).textTheme.headlineSmall),
                    Text('Status: ${_quote?['status'] ?? 'UNAVAILABLE'}'),
                    Text('Provider quote time: ${_quote?['quote_at'] ?? 'Not provided'}'),
                    Text('Last verified refresh: ${_quote?['retrieved_at'] ?? 'Never'}'),
                  ],
                ),
              ),
            ),
          if (_instruments.isNotEmpty)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Wrap(
                  spacing: 12,
                  runSpacing: 8,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    DropdownButton<String>(
                      value: _symbol,
                      items: _instruments
                          .map(
                            (item) => DropdownMenuItem(
                              value: item['symbol'] as String,
                              child: Text(item['symbol'].toString()),
                            ),
                          )
                          .toList(),
                      onChanged: (value) {
                        setState(() => _symbol = value);
                        _load();
                      },
                    ),
                    DropdownButton<String>(
                      value: _timeframe,
                      items: _timeframes
                          .map(
                            (item) => DropdownMenuItem(
                              value: item,
                              child: Text(item),
                            ),
                          )
                          .toList(),
                      onChanged: (value) {
                        if (value == null) return;
                        setState(() => _timeframe = value);
                        _load();
                      },
                    ),
                    OutlinedButton.icon(
                      onPressed: _refreshing ? null : _refresh,
                      icon: const Icon(Icons.refresh),
                      label: const Text('Refresh saved data'),
                    ),
                  ],
                ),
              ),
            ),
          if (_loading || _refreshing) const LinearProgressIndicator(),
          if (_error != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Text(
                  'Market Insight unavailable: $_error',
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            ),
          if (!_loading && _error == null && _analysis == null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Text(
                  _source == 'REAL'
                      ? 'No verified real analysis is available for this timeframe.'
                      : 'No simulated insight is saved for this selection. An administrator can generate a demo dataset.',
                ),
              ),
            ),
          if (_analysis != null) _analysisBody(_analysis!),
          const SizedBox(height: 12),
          Text('Saved insights', style: Theme.of(context).textTheme.titleLarge),
          if (_history.isEmpty)
            const Padding(
              padding: EdgeInsets.all(12),
              child: Text('No saved snapshots yet.'),
            ),
          for (final item in _history)
            Card(
              child: ListTile(
                title: Text(
                  '${item['signal']} · ${item['directional_score']} / 100',
                ),
                subtitle: Text(
                  '${item['symbol']} ${item['timeframe']} · ${item['analyzed_at']} · ${item['risk']} risk',
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => _openSnapshot((item['id'] as num).toInt()),
              ),
            ),
        ],
      ),
    );
  }

  Widget _analysisBody(Map<String, dynamic> analysis) {
    if (analysis['status'] != 'available') {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Text(
            'Analysis unavailable: ${analysis['reason'] ?? 'Unknown reason'}',
          ),
        ),
      );
    }
    final indicators = analysis['indicators'] as Map? ?? {};
    final contributions = analysis['contributions'] as Map? ?? {};
    final factors = analysis['factors'] as Map? ?? {};
    final warnings = analysis['warnings'] as List? ?? [];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${analysis['signal']}',
                  style: Theme.of(context).textTheme.headlineMedium,
                ),
                Text(
                  'Directional score ${analysis['directional_score']} / 100 · Strength ${analysis['signal_strength']} / 100',
                ),
                Text(
                  'Weighted agreement ${analysis['agreement_score']}% · Risk ${analysis['risk']}',
                ),
                Text('Data quality ${analysis['data_quality']}'),
                const SizedBox(height: 8),
                Text('Candle: ${analysis['market_data_at']} UTC'),
                Text('Analysis: ${analysis['analyzed_at']}'),
                Text(
                  '${analysis['source'] ?? 'MOCK'} · ${analysis['provider'] ?? 'Demo generator'} · Dataset v${analysis['dataset_version']} / revision ${analysis['dataset_revision'] ?? 1} · Configuration v${analysis['configuration_version']}',
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        Text('Six indicators', style: Theme.of(context).textTheme.titleLarge),
        for (final key in _names.keys)
          _indicatorCard(key, indicators[key], contributions[key]),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Multi-factor analysis',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                for (final type in ['bullish', 'bearish', 'neutral'])
                  Text(
                    '${type[0].toUpperCase()}${type.substring(1)}: ${((factors[type] as List?) ?? []).join(', ').isEmpty ? 'None' : ((factors[type] as List?) ?? []).join(', ')}',
                  ),
                Text(
                  'Risk warnings: ${warnings.isEmpty ? 'None' : warnings.join(' ')}',
                ),
                Text(
                  'Volatility: ${analysis['volatility']} · Daily range: ${analysis['daily_range_status']}',
                ),
              ],
            ),
          ),
        ),
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Indicator-generated explanation',
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: 6),
                Text('${analysis['explanation']}'),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _indicatorCard(String key, Object? raw, Object? contribution) {
    final item = raw is Map ? raw : <String, dynamic>{};
    final values = item['values'] is Map ? item['values'] as Map : {};
    final config = item['configuration'] is Map
        ? item['configuration'] as Map
        : {};
    final vote = contribution is Map ? contribution['points'] : null;
    return Card(
      child: ExpansionTile(
        title: Text(_names[key]!),
        subtitle: Text(
          '${item['classification'] ?? 'Unavailable'} · ${key == 'atr' || key == 'adr' ? 'Risk context only' : '${vote ?? 0} points'}',
        ),
        childrenPadding: const EdgeInsets.all(16),
        expandedCrossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${item['explanation'] ?? ''}'),
          const SizedBox(height: 8),
          for (final entry in values.entries)
            Text(
              '${entry.key.toString().replaceAll('_', ' ')}: ${entry.value ?? 'N/A'}',
            ),
          const SizedBox(height: 8),
          Text(
            'Configuration: ${config.entries.where((entry) => entry.key != 'enabled' && entry.key != 'weight').map((entry) => '${entry.key}: ${entry.value}').join(' · ')}',
          ),
        ],
      ),
    );
  }
}
