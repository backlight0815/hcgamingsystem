import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:http/http.dart' as http;

typedef FirebaseApiKeyProvider = String Function();
typedef FirebaseIdentityHttpPost =
    Future<http.Response> Function(
      Uri url, {
      Map<String, String>? headers,
      Object? body,
    });

class FirebasePasswordReauthenticationFailure implements Exception {
  const FirebasePasswordReauthenticationFailure(this.code, this.message);

  final String code;
  final String message;

  @override
  String toString() => message;
}

class FirebasePasswordSignInResult {
  const FirebasePasswordSignInResult({
    required this.userId,
    required this.idToken,
  });

  final String userId;
  final String idToken;
}

/// Verifies an email/password credential without relying on a desktop native
/// Firebase Auth method channel. The returned ID token contains a fresh
/// `auth_time` and can be supplied directly to a security-sensitive callable.
class FirebasePasswordReauthenticationService {
  FirebasePasswordReauthenticationService({
    FirebaseApiKeyProvider? apiKeyProvider,
    FirebaseIdentityHttpPost? httpPost,
    this.timeout = const Duration(seconds: 30),
  }) : _apiKeyProvider = apiKeyProvider ?? _firebaseApiKey,
       _httpPost = httpPost ?? http.post;

  final FirebaseApiKeyProvider _apiKeyProvider;
  final FirebaseIdentityHttpPost _httpPost;
  final Duration timeout;

  Future<String> reauthenticate({
    required String email,
    required String password,
    required String expectedUserId,
  }) async {
    final result = await signIn(email: email, password: password);
    if (result.userId != expectedUserId) {
      throw const FirebasePasswordReauthenticationFailure(
        'invalid-credential',
        'Firebase could not verify this account. Please sign in again.',
      );
    }
    return result.idToken;
  }

  Future<FirebasePasswordSignInResult> signIn({
    required String email,
    required String password,
  }) async {
    final apiKey = _apiKeyProvider().trim();
    if (apiKey.isEmpty) {
      throw const FirebasePasswordReauthenticationFailure(
        'failed-precondition',
        'Firebase authentication is not configured.',
      );
    }

    try {
      final response = await _httpPost(
        Uri.https(
          'identitytoolkit.googleapis.com',
          '/v1/accounts:signInWithPassword',
          {'key': apiKey},
        ),
        headers: const {'Content-Type': 'application/json; charset=utf-8'},
        body: jsonEncode({
          'email': email.trim().toLowerCase(),
          'password': password,
          'returnSecureToken': true,
        }),
      ).timeout(timeout);
      final body = _decodeBody(response.body);
      if (response.statusCode < 200 || response.statusCode >= 300) {
        final error = body['error'];
        final details = error is Map
            ? Map<String, dynamic>.from(error)
            : const <String, dynamic>{};
        throw FirebasePasswordReauthenticationFailure(
          _normalizeErrorCode(details['message']),
          _friendlyError(details['message']),
        );
      }

      final userId = '${body['localId'] ?? ''}'.trim();
      final idToken = '${body['idToken'] ?? ''}'.trim();
      if (userId.isEmpty || idToken.isEmpty) {
        throw const FirebasePasswordReauthenticationFailure(
          'invalid-credential',
          'Firebase returned an incomplete authentication response.',
        );
      }
      return FirebasePasswordSignInResult(userId: userId, idToken: idToken);
    } on FirebasePasswordReauthenticationFailure {
      rethrow;
    } on TimeoutException {
      throw const FirebasePasswordReauthenticationFailure(
        'deadline-exceeded',
        'Password verification took too long. Please try again.',
      );
    } on SocketException {
      throw const FirebasePasswordReauthenticationFailure(
        'unavailable',
        'Unable to reach Firebase. Check your connection.',
      );
    } on http.ClientException {
      throw const FirebasePasswordReauthenticationFailure(
        'unavailable',
        'Unable to reach Firebase. Check your connection.',
      );
    } on FormatException {
      throw const FirebasePasswordReauthenticationFailure(
        'internal',
        'Firebase returned an invalid authentication response.',
      );
    }
  }

  Future<void> sendPasswordResetEmail({required String email}) async {
    final apiKey = _apiKeyProvider().trim();
    if (apiKey.isEmpty) {
      throw const FirebasePasswordReauthenticationFailure(
        'failed-precondition',
        'Firebase authentication is not configured.',
      );
    }

    try {
      final response = await _httpPost(
        Uri.https(
          'identitytoolkit.googleapis.com',
          '/v1/accounts:sendOobCode',
          {'key': apiKey},
        ),
        headers: const {'Content-Type': 'application/json; charset=utf-8'},
        body: jsonEncode({
          'requestType': 'PASSWORD_RESET',
          'email': email.trim().toLowerCase(),
        }),
      ).timeout(timeout);
      if (response.statusCode >= 200 && response.statusCode < 300) return;

      final body = _decodeBody(response.body);
      final error = body['error'];
      final details = error is Map
          ? Map<String, dynamic>.from(error)
          : const <String, dynamic>{};
      final rawCode = details['message'];
      throw FirebasePasswordReauthenticationFailure(
        _normalizeErrorCode(rawCode),
        _friendlyPasswordResetError(rawCode),
      );
    } on FirebasePasswordReauthenticationFailure {
      rethrow;
    } on TimeoutException {
      throw const FirebasePasswordReauthenticationFailure(
        'deadline-exceeded',
        'Password reset took too long. Please try again.',
      );
    } on SocketException {
      throw const FirebasePasswordReauthenticationFailure(
        'unavailable',
        'Unable to reach Firebase. Check your connection.',
      );
    } on http.ClientException {
      throw const FirebasePasswordReauthenticationFailure(
        'unavailable',
        'Unable to reach Firebase. Check your connection.',
      );
    } on FormatException {
      throw const FirebasePasswordReauthenticationFailure(
        'internal',
        'Firebase returned an invalid password reset response.',
      );
    }
  }

  static Map<String, dynamic> _decodeBody(String value) {
    final decoded = jsonDecode(value);
    if (decoded is! Map) throw const FormatException('Expected JSON object.');
    return Map<String, dynamic>.from(decoded);
  }

  static String _normalizeErrorCode(Object? value) =>
      '${value ?? 'unknown'}'.trim().toLowerCase().replaceAll('_', '-');

  static String _friendlyError(Object? value) {
    final code = '${value ?? ''}'.trim().toUpperCase();
    return switch (code) {
      'INVALID_LOGIN_CREDENTIALS' ||
      'INVALID_PASSWORD' ||
      'EMAIL_NOT_FOUND' => 'The current password is incorrect.',
      'USER_DISABLED' =>
        'This account is disabled. Please contact administration.',
      'TOO_MANY_ATTEMPTS_TRY_LATER' ||
      'TOO_MANY_REQUESTS' => 'Too many attempts. Please wait and try again.',
      'OPERATION_NOT_ALLOWED' =>
        'Email and password sign-in is not enabled for this Firebase project.',
      _ => 'Firebase could not verify your password. Please try again.',
    };
  }

  static String _friendlyPasswordResetError(Object? value) {
    final code = '${value ?? ''}'.trim().toUpperCase();
    return switch (code) {
      'INVALID_EMAIL' => 'Enter a valid email address.',
      'TOO_MANY_ATTEMPTS_TRY_LATER' ||
      'TOO_MANY_REQUESTS' => 'Too many attempts. Please wait and try again.',
      _ => 'Unable to send the password reset email. Please try again.',
    };
  }

  static String _firebaseApiKey() => Firebase.app().options.apiKey;
}
