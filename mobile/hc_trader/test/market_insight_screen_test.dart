import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/network/api_client.dart';
import 'package:hc_trader/features/hub/market_insight_screen.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  testWidgets(
    'mobile insight screen labels mock data and renders the backend signal',
    (tester) async {
      tester.view.physicalSize = const Size(360, 800);
      tester.view.devicePixelRatio = 1;
      addTearDown(() {
        tester.view.resetPhysicalSize();
        tester.view.resetDevicePixelRatio();
      });
      final keys = ['bollinger', 'ema', 'macd', 'rsi', 'atr', 'adr'];
      final indicators = {
        for (final key in keys)
          key: {
            'classification': key == 'atr' ? 'Normal Volatility' : 'Bullish',
            'explanation': '$key calculated from completed candles.',
            'values': {'value': 12.5},
            'configuration': {'period': 14},
          },
      };
      final analysis = {
        'status': 'available',
        'signal': 'BUY',
        'directional_score': 60,
        'signal_strength': 60,
        'agreement_score': 60,
        'risk': 'Normal',
        'data_quality': 'validated_completed_mock',
        'market_data_at': '2026-10-14T00:00:00Z',
        'analyzed_at': '2026-10-14T00:01:00Z',
        'dataset_version': 1,
        'configuration_version': 1,
        'indicators': indicators,
        'contributions': {
          for (final key in keys)
            key: {'points': key == 'atr' || key == 'adr' ? 0 : 20},
        },
        'factors': {
          'bullish': ['EMA', 'MACD'],
          'bearish': [],
          'neutral': ['RSI'],
        },
        'warnings': [],
        'volatility': 'Normal Volatility',
        'daily_range_status': 'Normal Daily Range',
        'explanation': 'EMA and MACD support BUY; ATR is normal.',
      };
      final client = ApiClient(
        baseUrl: 'https://example.test/api/mobile/v1',
        authTokenProvider: () async => 'qa-token',
        httpClient: MockClient((request) async {
          final path = request.url.path;
          final data = path.endsWith('/instruments')
              ? {
                  'instruments': [
                    {'symbol': 'XAUUSD', 'name': 'Gold'},
                  ],
                }
              : path.endsWith('/history')
              ? {'history': []}
              : {'analysis': analysis};
          return http.Response(
            jsonEncode({'success': true, 'data': data}),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );
      final repository = TradingRepository(
        firebaseEnabled: true,
        apiClient: client,
      );
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(body: MarketInsightScreen(repository: repository)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('AI Market Insight'), findsOneWidget);
      expect(find.textContaining('DEMO / SIMULATED DATA'), findsOneWidget);
      expect(find.text('BUY'), findsOneWidget);
      expect(find.textContaining('Directional score 60 / 100'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('mobile insight screen labels verified real quote and provider',
      (tester) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(() {
      tester.view.resetPhysicalSize();
      tester.view.resetDevicePixelRatio();
    });
    final indicators = {
      for (final key in ['bollinger', 'ema', 'macd', 'rsi', 'atr', 'adr'])
        key: {
          'classification': 'Neutral',
          'explanation': 'Verified completed candles.',
          'values': {'value': 1.0},
          'configuration': {'period': 14},
        },
    };
    final analysis = {
      'status': 'available',
      'source': 'REAL',
      'provider': 'twelvedata',
      'signal': 'NEUTRAL',
      'directional_score': 0,
      'signal_strength': 0,
      'agreement_score': 0,
      'risk': 'Normal',
      'data_quality': 'validated_completed_real',
      'market_data_at': '2026-10-14 07:00:00',
      'analyzed_at': '2026-10-14T07:01:00Z',
      'dataset_version': 1,
      'dataset_revision': 1,
      'configuration_version': 1,
      'indicators': indicators,
      'contributions': <String, dynamic>{},
      'factors': {'bullish': [], 'bearish': [], 'neutral': []},
      'warnings': <String>[],
      'volatility': 'Normal Volatility',
      'daily_range_status': 'Normal Daily Range',
      'explanation': 'No directional agreement.',
    };
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'qa-token',
      httpClient: MockClient((request) async {
        final path = request.url.path;
        final data = path.endsWith('/instruments')
            ? {
                'source': 'REAL',
                'instruments': [
                  {'symbol': 'XAUUSD', 'name': 'Gold Spot / US Dollar'},
                ],
              }
            : path.endsWith('/history')
                ? {'history': []}
                : {
                    'source': 'REAL',
                    'analysis': analysis,
                    'quote': {
                      'price': '4150.25',
                      'status': 'LATEST_AVAILABLE',
                      'quote_at': '2026-10-14 07:00:00',
                      'retrieved_at': '2026-10-14 07:01:00',
                    },
                    'provider_status': {'status': 'CONNECTED'},
                  };
        return http.Response(jsonEncode({'success': true, 'data': data}), 200,
            headers: {'content-type': 'application/json'});
      }),
    );
    await tester.pumpWidget(MaterialApp(
      home: Scaffold(
          body: MarketInsightScreen(
              repository: TradingRepository(firebaseEnabled: true, apiClient: client))),
    ));
    await tester.pumpAndSettle();
    expect(find.textContaining('REAL DATA · Twelve Data'), findsOneWidget);
    expect(find.textContaining('4150.25 USD / troy oz'), findsOneWidget);
    expect(find.textContaining('LATEST_AVAILABLE'), findsOneWidget);
    expect(find.text('NEUTRAL'), findsOneWidget);
    expect(find.textContaining('DEMO / SIMULATED DATA'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
