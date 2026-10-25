import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/network/api_client.dart';
import 'package:hc_trader/services/market_insight_api_service.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test(
    'uses the shared insight contract and preserves backend calculations',
    () async {
      final paths = <String>[];
      final client = ApiClient(
        baseUrl: 'https://example.test/api/mobile/v1',
        authTokenProvider: () async => 'qa-token',
        httpClient: MockClient((request) async {
          expect(request.headers['authorization'], 'Bearer qa-token');
          paths.add(
            '${request.method} ${request.url.path}?${request.url.query}',
          );
          final path = request.url.path;
          final data = path.endsWith('/instruments')
              ? {
                  'instruments': [
                    {'symbol': 'XAUUSD'},
                  ],
                  'is_mock_data': true,
                }
              : path.endsWith('/history')
              ? {
                  'history': [
                    {'id': 8, 'signal': 'BUY'},
                  ],
                }
              : {
                  'analysis': {
                    'id': 8,
                    'contract_version': 1,
                    'method': 'Rule-Based Indicator Analysis',
                    'source': 'MOCK',
                    'is_mock_data': true,
                    'signal': 'BUY',
                    'directional_score': 60,
                    'indicators': {
                      'atr': {'direction': 0},
                      'adr': {'direction': 0},
                    },
                  },
                };
          return http.Response(
            jsonEncode({'success': true, 'data': data}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );
      final service = MarketInsightApiService(client);
      expect((await service.instruments())['is_mock_data'], true);
      expect(
        (await service.latest('XAUUSD', 'H1'))['analysis']['directional_score'],
        60,
      );
      expect((await service.history('XAUUSD', 'H1'))['history'], hasLength(1));
      expect((await service.snapshot(8))['analysis']['contract_version'], 1);
      expect(
        (await service.refresh('XAUUSD', 'H1'))['analysis']['source'],
        'MOCK',
      );
      expect(paths, [
        'GET /api/mobile/v1/market-insights/instruments?',
        'GET /api/mobile/v1/market-insights/latest?symbol=XAUUSD&timeframe=H1',
        'GET /api/mobile/v1/market-insights/history?symbol=XAUUSD&timeframe=H1',
        'GET /api/mobile/v1/market-insights/8?',
        'POST /api/mobile/v1/market-insights/refresh?',
      ]);
    },
  );

  test('surfaces API errors instead of fabricating insights', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'qa-token',
      httpClient: MockClient(
        (request) async => http.Response(
          jsonEncode({'success': false, 'message': 'Provider unavailable'}),
          503,
          headers: {'content-type': 'application/json'},
        ),
      ),
    );
    expect(
      () => MarketInsightApiService(client).latest('XAUUSD', 'H1'),
      throwsA(isA<ApiException>()),
    );
  });
}
