import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/core/network/api_client.dart';
import 'package:hc_trader/repositories/trading_repository.dart';
import 'package:hc_trader/services/firebase_callable_service.dart';
import 'package:http/http.dart' as http;

void main() {
  test(
    'desktop callable transport sends Firebase auth and data envelope',
    () async {
      Uri? requestedUrl;
      Map<String, String>? requestedHeaders;
      Object? requestedBody;
      final service = FirebaseCallableService(
        forceHttpTransport: true,
        accessTokenProvider: () async => 'test-token',
        projectIdProvider: () => 'test-project',
        httpPost: (url, {headers, body}) async {
          requestedUrl = url;
          requestedHeaders = headers;
          requestedBody = body;
          return http.Response(
            jsonEncode({
              'result': {'code': 'ABC123', 'week': '2026-W28'},
            }),
            200,
          );
        },
      );

      final result = await service.call('ensureWeeklyRecruitmentReferralCode', {
        'recruitmentRoleId': 750,
      });

      expect(
        requestedUrl.toString(),
        'https://us-central1-test-project.cloudfunctions.net/'
        'ensureWeeklyRecruitmentReferralCode',
      );
      expect(requestedHeaders?['Authorization'], 'Bearer test-token');
      expect(jsonDecode('$requestedBody'), {
        'data': {'recruitmentRoleId': 750},
      });
      expect(result['code'], 'ABC123');
    },
  );

  test(
    'explicit recent-auth token overrides the cached desktop token',
    () async {
      var providerCalls = 0;
      String? authorization;
      final service = FirebaseCallableService(
        forceHttpTransport: true,
        accessTokenProvider: () async {
          providerCalls += 1;
          return 'cached-token';
        },
        projectIdProvider: () => 'test-project',
        httpPost: (url, {headers, body}) async {
          authorization = headers?['Authorization'];
          return http.Response(
            jsonEncode({
              'result': {'deleted': true},
            }),
            200,
          );
        },
      );

      final result = await service.callWithAccessToken(
        'deleteCurrentUserAccount',
        const {'reason': 'privacy'},
        accessToken: 'fresh-reauth-token',
      );

      expect(providerCalls, 0);
      expect(authorization, 'Bearer fresh-reauth-token');
      expect(result['deleted'], isTrue);
    },
  );

  test('desktop callable transport maps structured Firebase errors', () async {
    final service = FirebaseCallableService(
      forceHttpTransport: true,
      accessTokenProvider: () async => 'test-token',
      projectIdProvider: () => 'test-project',
      httpPost: (url, {headers, body}) async => http.Response(
        jsonEncode({
          'error': {
            'status': 'PERMISSION_DENIED',
            'message': 'Your role cannot recruit this user role.',
          },
        }),
        403,
      ),
    );

    expect(
      () => service.call('ensureWeeklyRecruitmentReferralCode', const {}),
      throwsA(
        isA<FirebaseCallableFailure>()
            .having((error) => error.code, 'code', 'permission-denied')
            .having(
              (error) => error.message,
              'message',
              'Your role cannot recruit this user role.',
            ),
      ),
    );
  });

  test('desktop callable transport requires an authenticated user', () async {
    final service = FirebaseCallableService(
      forceHttpTransport: true,
      accessTokenProvider: () async => null,
      projectIdProvider: () => 'test-project',
      httpPost: (url, {headers, body}) async => http.Response('{}', 200),
    );

    expect(
      () => service.call('ensureWeeklyRecruitmentReferralCode', const {}),
      throwsA(
        isA<FirebaseCallableFailure>().having(
          (error) => error.code,
          'code',
          'unauthenticated',
        ),
      ),
    );
  });

  test('deleted users use the cross-platform callable transport', () async {
    late String requestedFunction;
    late Map<String, dynamic> requestedData;
    final service = _FakeCallableService((functionName, data) async {
      requestedFunction = functionName;
      requestedData = data;
      return {
        'users': [
          {'id': 'deleted-user-1', 'email': 'deleted@example.com'},
        ],
      };
    });
    final repository = TradingRepository(
      firebaseEnabled: true,
      callableService: service,
    );

    final users = await repository.adminDeletedUsers(limit: 500);

    expect(requestedFunction, 'adminListDeletedUsers');
    expect(requestedData, {'limit': 200});
    expect(users, [
      {'id': 'deleted-user-1', 'email': 'deleted@example.com'},
    ]);
  });

  test('deleted users expose a readable callable permission error', () async {
    final repository = TradingRepository(
      firebaseEnabled: true,
      callableService: _FakeCallableService((_, _) async {
        throw const FirebaseCallableFailure(
          'permission-denied',
          'Your account cannot view deleted users.',
        );
      }),
    );

    expect(
      () => repository.adminDeletedUsers(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          'Your account cannot view deleted users.',
        ),
      ),
    );
  });

  test('access approval uses the cross-platform trusted callable', () async {
    late String requestedFunction;
    late Map<String, dynamic> requestedData;
    final repository = TradingRepository(
      firebaseEnabled: true,
      callableService: _FakeCallableService((functionName, data) async {
        requestedFunction = functionName;
        requestedData = data;
        return {'status': 'approved'};
      }),
    );

    await repository.adminReviewAccessRequest(
      'request-1',
      decision: 'approve',
      note: ' verified ',
    );

    expect(requestedFunction, 'adminReviewAccessApplication');
    expect(requestedData, {
      'requestId': 'request-1',
      'decision': 'approve',
      'note': 'verified',
    });
  });

  test('access approval preserves a trusted stale-state error', () async {
    final repository = TradingRepository(
      firebaseEnabled: true,
      callableService: _FakeCallableService((_, _) async {
        throw const FirebaseCallableFailure(
          'aborted',
          'This application has already been processed.',
        );
      }),
    );

    expect(
      () =>
          repository.adminReviewAccessRequest('request-1', decision: 'approve'),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          'This application has already been processed.',
        ),
      ),
    );
  });

  test('trade-call statistics use the trusted role-scoped callable', () async {
    final requests = <(String, Map<String, dynamic>)>[];
    final repository = TradingRepository(
      firebaseEnabled: true,
      callableService: _FakeCallableService((functionName, data) async {
        requests.add((functionName, data));
        if (data['pageToken'] == null) {
          return {
            'calls': [
              {
                'id': 'call-1',
                'trading_pair': 'EURUSD',
                'provider_id': 'provider-1',
                'status': 2,
                'target_1': '1.1200',
                'closed_at': '2026-07-13T10:00:00.000Z',
              },
            ],
            'nextPageToken': 'call-1',
          };
        }
        return {
          'calls': [
            {
              'id': 'call-2',
              'trading_pair': 'GBPUSD',
              'provider_id': 'provider-1',
              'status': 13,
              'closed_at': '2026-07-12T10:00:00.000Z',
            },
          ],
        };
      }),
    );

    final calls = await repository.loadTradeCallStatisticsSignals();

    expect(requests, hasLength(2));
    expect(requests[0].$1, 'loadTradeCallStatistics');
    expect(requests[0].$2, {'pageSize': 500});
    expect(requests[1].$1, 'loadTradeCallStatistics');
    expect(requests[1].$2, {'pageSize': 500, 'pageToken': 'call-1'});
    expect(calls.map((call) => call.id), ['call-1', 'call-2']);
    expect(calls.first.providerId, 'provider-1');
    expect(calls.first.targets, ['1.1200']);
  });
}

class _FakeCallableService extends FirebaseCallableService {
  _FakeCallableService(this.handler);

  final Future<Map<String, dynamic>> Function(
    String functionName,
    Map<String, dynamic> data,
  )
  handler;

  @override
  Future<Map<String, dynamic>> call(
    String functionName,
    Map<String, dynamic> data,
  ) => handler(functionName, data);
}
