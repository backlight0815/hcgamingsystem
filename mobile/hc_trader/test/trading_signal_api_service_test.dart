import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/config/api_config.dart';
import 'package:hc_trader/core/network/api_client.dart';
import 'package:hc_trader/models/trading_signal_item.dart';
import 'package:hc_trader/services/trading_signal_api_service.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  test('uses the HC Trading Hub production and local API endpoints', () {
    expect(
      ApiConfig.productionBaseUrl,
      'https://www.hctradinghub.com/api/mobile/v1',
    );
    expect(ApiConfig.baseUrl, ApiConfig.productionBaseUrl);
    expect(
      ApiConfig.androidEmulatorBaseUrl,
      'http://10.0.2.2/hcgaming/public/api/mobile/v1',
    );
    expect(
      ApiConfig.localWebBaseUrl,
      'http://localhost/hcgaming/public/api/mobile/v1',
    );
  });

  test('deserializes authoritative Laravel trading signal response', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'firebase-token',
      httpClient: MockClient((request) async {
        expect(request.headers['authorization'], 'Bearer firebase-token');
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'signals': [
                {
                  'id': '1001',
                  'signal_uuid': 'SIG-20261007-1001',
                  'trading_pair': 'XAUUSD',
                  'immediate_action': 'BUY',
                  'entry_price': '2650.50',
                  'stop_loss': '2635.00',
                  'targets': ['2665.00', '2680.00'],
                  'risk_level': 'MEDIUM',
                  'timeframe': 'H1',
                  'workflow_status': 'PUBLISHED',
                  'lifecycle_status': 'partially_closed',
                  'order_type': 'buy_limit',
                  'current_stop_loss': '2650.50',
                  'break_even_price': '2650.50',
                  'targets_hit': [1],
                  'current_target_index': 1,
                  'percentage_remaining': 50,
                  'last_update_type': 'partial_profit_taken',
                  'published_at': '2026-10-07T12:30:00Z',
                  'source': 'WEBSITE',
                },
              ],
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final rows = await TradingSignalApiService(client).fetchSignals();
    final signal = TradingSignalItem.fromJson(rows.single);

    expect(signal.id, '1001');
    expect(signal.tradingPair, 'XAUUSD');
    expect(signal.targets, ['2665.00', '2680.00']);
    expect(signal.signalSource, 'WEBSITE');
    expect(signal.lifecycleStatus, 'partially_closed');
    expect(signal.orderType, 'buy_limit');
    expect(signal.currentStopLoss, '2650.50');
    expect(signal.breakEvenPrice, '2650.50');
    expect(signal.targetsHit, {1});
    expect(signal.currentTargetIndex, 1);
    expect(signal.percentageRemaining, 50);
    expect(signal.lastUpdateType, 'partial_profit_taken');
    expect(signal.publishedAt, DateTime.parse('2026-10-07T12:30:00Z'));
  });

  test('sends authenticated signal with one stable idempotency key', () async {
    var requests = 0;
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'firebase-token',
      httpClient: MockClient((request) async {
        requests++;
        expect(request.method, 'POST');
        expect(request.headers['authorization'], 'Bearer firebase-token');
        expect(request.headers['x-idempotency-key'], 'device-request-1');
        final body = jsonDecode(request.body) as Map<String, dynamic>;
        expect(body['symbol'], 'XAUUSD');
        expect(body.containsKey('source_user_id'), isFalse);
        expect(body.containsKey('source'), isFalse);
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'signal': {'id': '1002'},
              'idempotent_replay': false,
            },
          }),
          201,
        );
      }),
    );

    await TradingSignalApiService(client).submitSignal(
      idempotencyKey: 'device-request-1',
      payload: const {
        'symbol': 'XAUUSD',
        'signal_type': 'BUY',
        'entry_price': '2650.50',
        'stop_loss': '2635.00',
        'take_profit_1': '2665.00',
      },
    );

    expect(requests, 1);
  });

  test('surfaces structured server validation errors', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient(
        (_) async => http.Response(
          jsonEncode({
            'success': false,
            'message': 'Validation failed.',
            'errors': {
              'symbol': ['Symbol is required.'],
            },
          }),
          422,
        ),
      ),
    );

    expect(
      () => TradingSignalApiService(
        client,
      ).submitSignal(idempotencyKey: 'invalid-1', payload: const {}),
      throwsA(
        isA<ApiException>()
            .having((error) => error.statusCode, 'statusCode', 422)
            .having((error) => error.errors['symbol'], 'symbol errors', [
              'Symbol is required.',
            ]),
      ),
    );
  });

  test(
    'retrieves every page to recover signals missed while offline',
    () async {
      final requestedPages = <String?>[];
      final client = ApiClient(
        baseUrl: 'https://example.test/api/mobile/v1',
        httpClient: MockClient((request) async {
          final page = request.url.queryParameters['page'];
          requestedPages.add(page);
          final id = page == '1' ? '1001' : '1002';
          return http.Response(
            jsonEncode({
              'success': true,
              'data': {
                'signals': [
                  {
                    'id': id,
                    'trading_pair': 'XAUUSD',
                    'immediate_action': 'BUY',
                  },
                ],
                'pagination': {
                  'current_page': int.parse(page!),
                  'last_page': 2,
                },
              },
            }),
            200,
          );
        }),
      );

      final signals = await TradingSignalApiService(client).fetchSignals();

      expect(requestedPages, ['1', '2']);
      expect(signals.map((signal) => signal['id']), ['1001', '1002']);
    },
  );

  test('retrieves one signal for the detail view', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((request) async {
        expect(request.url.path, '/api/mobile/v1/signals/1001');
        return http.Response(
          jsonEncode({
            'success': true,
            'data': {
              'signal': {
                'id': '1001',
                'trading_pair': 'XAUUSD',
                'immediate_action': 'BUY',
              },
            },
          }),
          200,
        );
      }),
    );

    final signal = await TradingSignalApiService(client).fetchSignal('1001');

    expect(signal?['id'], '1001');
    expect(signal?['trading_pair'], 'XAUUSD');
  });

  test('surfaces non-validation HTTP failures', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient(
        (_) async => http.Response(
          jsonEncode({
            'success': false,
            'message': 'Too many signal requests.',
            'errors': <String, Object?>{},
          }),
          429,
        ),
      ),
    );

    expect(
      () => TradingSignalApiService(client).fetchSignals(),
      throwsA(
        isA<ApiException>()
            .having((error) => error.statusCode, 'statusCode', 429)
            .having(
              (error) => error.message,
              'message',
              'Too many signal requests.',
            ),
      ),
    );
  });

  for (final statusCode in const [401, 403, 500]) {
    test('surfaces HTTP $statusCode as an API failure, not an empty list', () {
      final client = ApiClient(
        baseUrl: 'https://example.test/api/mobile/v1',
        httpClient: MockClient(
          (_) async => http.Response(
            jsonEncode({
              'success': false,
              'message': 'Request failed with HTTP $statusCode.',
            }),
            statusCode,
          ),
        ),
      );

      expect(
        () => TradingSignalApiService(client).fetchSignals(),
        throwsA(
          isA<ApiException>().having(
            (error) => error.statusCode,
            'statusCode',
            statusCode,
          ),
        ),
      );
    });
  }

  test('parses a valid empty signal response as an empty list', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient(
        (_) async => http.Response(
          jsonEncode({
            'success': true,
            'data': {'signals': <Object?>[]},
          }),
          200,
        ),
      ),
    );

    expect(await TradingSignalApiService(client).fetchSignals(), isEmpty);
  });

  test('rejects an invalid success hierarchy instead of returning empty', () {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient(
        (_) async => http.Response(
          jsonEncode({
            'success': true,
            'data': {'items': <Object?>[]},
          }),
          200,
        ),
      ),
    );

    expect(
      () => TradingSignalApiService(client).fetchSignals(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          'The server returned an invalid trading signal response.',
        ),
      ),
    );
  });

  test('rejects a top-level JSON list as an unreadable API response', () {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient(
        (_) async => http.Response(jsonEncode(<Object?>[]), 200),
      ),
    );

    expect(
      () => TradingSignalApiService(client).fetchSignals(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          'The server returned an unreadable response.',
        ),
      ),
    );
  });

  test('surfaces network timeouts instead of returning empty', () {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      requestTimeout: const Duration(milliseconds: 5),
      httpClient: MockClient((_) async {
        await Future<void>.delayed(const Duration(milliseconds: 50));
        return http.Response('{}', 200);
      }),
    );

    expect(
      () => TradingSignalApiService(client).fetchSignals(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          'The HC Trading Hub request timed out. Please try again.',
        ),
      ),
    );
  });

  test('surfaces transport failures instead of returning empty', () {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient(
        (_) async => throw http.ClientException('connection refused'),
      ),
    );

    expect(
      () => TradingSignalApiService(client).fetchSignals(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          'The app\'s HTTP connection to example.test failed. Please retry or switch networks.',
        ),
      ),
    );
  });

  test('parses API aliases, numeric prices, and nullable fields safely', () {
    final signal = TradingSignalItem.fromJson({
      'id': 182,
      'symbol': 'XAUUSD',
      'signal_type': 'BUY',
      'entry_price': 4023,
      'stop_loss': 4005.5,
      'targets': [4010, '4020.00'],
      'strategy': null,
      'description': null,
      'risk_level': null,
      'published_at': null,
      'closed_at': null,
      'source': 'WEBSITE',
    });

    expect(signal.id, '182');
    expect(signal.tradingPair, 'XAUUSD');
    expect(signal.immediateAction, 'BUY');
    expect(signal.entryPrice, '4023');
    expect(signal.stopLoss, '4005.5');
    expect(signal.targets, ['4010', '4020.00']);
    expect(signal.signalSource, 'WEBSITE');
    expect(signal.publishedAt, isNull);
    expect(signal.closedAt, isNull);
  });

  test(
    'receives a realtime publication hint from the authenticated SSE stream',
    () async {
      final client = ApiClient(
        baseUrl: 'https://example.test/api/mobile/v1',
        authTokenProvider: () async => 'firebase-token',
        httpClient: MockClient((request) async {
          expect(request.headers['authorization'], 'Bearer firebase-token');
          expect(request.headers['accept'], 'text/event-stream');
          return http.Response(
            'event: trading.signal.published\n'
            'data: {"event":"trading.signal.published","signal_id":1001}\n\n',
            200,
            headers: {'content-type': 'text/event-stream'},
          );
        }),
      );

      final event = await TradingSignalApiService(client).watchChanges().first;

      expect(event['event'], 'trading.signal.published');
      expect(event['signal_id'], 1001);
    },
  );
}
