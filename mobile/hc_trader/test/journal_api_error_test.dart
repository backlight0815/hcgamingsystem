import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/network/api_client.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  for (final status in [401, 403, 404, 429, 500, 503]) {
    test('HTML HTTP $status keeps the HTTP error category', () async {
      final client = ApiClient(
        baseUrl: 'https://example.test',
        httpClient: MockClient(
          (_) async => http.Response('<html>proxy error</html>', status),
        ),
      );
      await expectLater(
        client.get('journal-sync/changes', authenticate: false),
        throwsA(
          isA<ApiException>()
              .having((e) => e.statusCode, 'status', status)
              .having(
                (e) => e.errorCode,
                'code',
                isNot('INVALID_API_RESPONSE'),
              ),
        ),
      );
    });
  }
  test(
    'HTML success is a response error, never a connectivity error',
    () async {
      final client = ApiClient(
        baseUrl: 'https://example.test',
        httpClient: MockClient(
          (_) async => http.Response('<html>login</html>', 200),
        ),
      );
      await expectLater(
        client.get('journal-sync/changes', authenticate: false),
        throwsA(
          isA<ApiException>().having(
            (e) => e.errorCode,
            'code',
            'INVALID_API_RESPONSE',
          ),
        ),
      );
    },
  );
  test('journal mapping failure retains its server error code', () async {
    final client = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      authTokenProvider: () async => 'test-token',
      httpClient: MockClient((request) async {
        expect(request.headers['Authorization'], 'Bearer test-token');
        return http.Response(
          '{"success":false,"message":"Account link unavailable.",'
          '"error_code":"JOURNAL_ACCOUNT_MAPPING_UNAVAILABLE"}',
          409,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    await expectLater(
      client.get('journal-sync/changes'),
      throwsA(
        isA<ApiException>()
            .having((error) => error.statusCode, 'status', 409)
            .having(
              (error) => error.errorCode,
              'code',
              'JOURNAL_ACCOUNT_MAPPING_UNAVAILABLE',
            ),
      ),
    );
  });

  test('journal network errors distinguish TLS from socket failures', () async {
    final tlsClient = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient((_) async => throw HandshakeException('failed')),
    );
    final socketClient = ApiClient(
      baseUrl: 'https://example.test/api/mobile/v1',
      httpClient: MockClient(
        (_) async => throw const SocketException('failed'),
      ),
    );

    await expectLater(
      tlsClient.get('journal-sync/changes', authenticate: false),
      throwsA(
        isA<ApiException>()
            .having((error) => error.errorCode, 'code', 'NETWORK_TLS_FAILURE')
            .having(
              (error) => error.message,
              'message',
              contains('secure connection'),
            ),
      ),
    );
    await expectLater(
      socketClient.get('journal-sync/changes', authenticate: false),
      throwsA(
        isA<ApiException>()
            .having(
              (error) => error.errorCode,
              'code',
              'NETWORK_SOCKET_FAILURE',
            )
            .having(
              (error) => error.message,
              'message',
              contains('switch networks'),
            ),
      ),
    );
  });
}
