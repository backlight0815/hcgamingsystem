import '../core/network/api_client.dart';

/// Client for the versioned Laravel rule-based insight contract.
/// All indicator calculations remain on the backend.
class MarketInsightApiService {
  const MarketInsightApiService(this.client);

  final ApiClient client;

  Future<Map<String, dynamic>> instruments() =>
      _data(client.get('market-insights/instruments'));

  Future<Map<String, dynamic>> latest(String symbol, String timeframe) => _data(
    client.get(
      'market-insights/latest?symbol=${Uri.encodeQueryComponent(symbol)}&timeframe=${Uri.encodeQueryComponent(timeframe)}',
    ),
  );

  Future<Map<String, dynamic>> history(
    String symbol,
    String timeframe,
  ) => _data(
    client.get(
      'market-insights/history?symbol=${Uri.encodeQueryComponent(symbol)}&timeframe=${Uri.encodeQueryComponent(timeframe)}',
    ),
  );

  Future<Map<String, dynamic>> snapshot(int id) =>
      _data(client.get('market-insights/$id'));

  Future<Map<String, dynamic>> refresh(String symbol, String timeframe) =>
      _data(
        client.post(
          'market-insights/refresh',
          body: {'symbol': symbol, 'timeframe': timeframe},
        ),
      );

  Future<Map<String, dynamic>> _data(
    Future<Map<String, dynamic>> request,
  ) async {
    final response = await request;
    if (response['success'] != true || response['data'] is! Map) {
      throw ApiException(
        (response['message'] ?? 'Market insight response is unavailable.')
            .toString(),
      );
    }
    return Map<String, dynamic>.from(response['data'] as Map);
  }
}
