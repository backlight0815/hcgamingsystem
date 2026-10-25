import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:hc_trader/services/firebase_password_reauthentication_service.dart';
import 'package:http/http.dart' as http;

void main() {
  test(
    'desktop password verification returns a fresh Firebase ID token',
    () async {
      Uri? requestedUrl;
      Object? requestedBody;
      final service = FirebasePasswordReauthenticationService(
        apiKeyProvider: () => 'test-api-key',
        httpPost: (url, {headers, body}) async {
          requestedUrl = url;
          requestedBody = body;
          return http.Response(
            jsonEncode({
              'localId': 'user-1',
              'idToken': 'fresh-id-token',
              'refreshToken': 'unused-refresh-token',
            }),
            200,
          );
        },
      );

      final token = await service.reauthenticate(
        email: 'USER@EXAMPLE.COM',
        password: 'correct-password',
        expectedUserId: 'user-1',
      );

      expect(token, 'fresh-id-token');
      expect(
        requestedUrl.toString(),
        'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=test-api-key',
      );
      expect(jsonDecode('$requestedBody'), {
        'email': 'user@example.com',
        'password': 'correct-password',
        'returnSecureToken': true,
      });
    },
  );

  test(
    'desktop password verification maps an invalid password safely',
    () async {
      final service = FirebasePasswordReauthenticationService(
        apiKeyProvider: () => 'test-api-key',
        httpPost: (url, {headers, body}) async => http.Response(
          jsonEncode({
            'error': {'code': 400, 'message': 'INVALID_LOGIN_CREDENTIALS'},
          }),
          400,
        ),
      );

      expect(
        () => service.reauthenticate(
          email: 'user@example.com',
          password: 'wrong-password',
          expectedUserId: 'user-1',
        ),
        throwsA(
          isA<FirebasePasswordReauthenticationFailure>()
              .having(
                (error) => error.code,
                'code',
                'invalid-login-credentials',
              )
              .having(
                (error) => error.message,
                'message',
                'The current password is incorrect.',
              ),
        ),
      );
    },
  );

  test(
    'desktop password verification rejects a different Firebase user',
    () async {
      final service = FirebasePasswordReauthenticationService(
        apiKeyProvider: () => 'test-api-key',
        httpPost: (url, {headers, body}) async => http.Response(
          jsonEncode({'localId': 'other-user', 'idToken': 'other-token'}),
          200,
        ),
      );

      expect(
        () => service.reauthenticate(
          email: 'user@example.com',
          password: 'correct-password',
          expectedUserId: 'user-1',
        ),
        throwsA(
          isA<FirebasePasswordReauthenticationFailure>().having(
            (error) => error.code,
            'code',
            'invalid-credential',
          ),
        ),
      );
    },
  );

  test('desktop password reset uses Firebase Identity Toolkit HTTPS', () async {
    Uri? requestedUrl;
    Object? requestedBody;
    final service = FirebasePasswordReauthenticationService(
      apiKeyProvider: () => 'test-api-key',
      httpPost: (url, {headers, body}) async {
        requestedUrl = url;
        requestedBody = body;
        return http.Response(jsonEncode({'email': 'user@example.com'}), 200);
      },
    );

    await service.sendPasswordResetEmail(email: 'USER@EXAMPLE.COM');

    expect(
      requestedUrl.toString(),
      'https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=test-api-key',
    );
    expect(jsonDecode('$requestedBody'), {
      'requestType': 'PASSWORD_RESET',
      'email': 'user@example.com',
    });
  });

  test('desktop password reset preserves a safe Firebase error code', () async {
    final service = FirebasePasswordReauthenticationService(
      apiKeyProvider: () => 'test-api-key',
      httpPost: (url, {headers, body}) async => http.Response(
        jsonEncode({
          'error': {'code': 400, 'message': 'EMAIL_NOT_FOUND'},
        }),
        400,
      ),
    );

    expect(
      () => service.sendPasswordResetEmail(email: 'missing@example.com'),
      throwsA(
        isA<FirebasePasswordReauthenticationFailure>().having(
          (error) => error.code,
          'code',
          'email-not-found',
        ),
      ),
    );
  });
}
