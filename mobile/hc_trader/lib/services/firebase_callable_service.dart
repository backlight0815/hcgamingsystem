import 'dart:convert';

import 'package:cloud_functions/cloud_functions.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;

typedef FirebaseAccessTokenProvider = Future<String?> Function();
typedef FirebaseProjectIdProvider = String Function();
typedef FirebaseCallableHttpPost =
    Future<http.Response> Function(
      Uri url, {
      Map<String, String>? headers,
      Object? body,
    });

class FirebaseCallableFailure implements Exception {
  const FirebaseCallableFailure(this.code, this.message);

  final String code;
  final String message;

  @override
  String toString() => message;
}

/// Calls Firebase callable functions on every Flutter target supported by the
/// app. FlutterFire does not currently register a Cloud Functions plugin in
/// the Windows/Linux runners, so desktop uses the documented callable HTTPS
/// envelope with the signed-in user's Firebase ID token.
class FirebaseCallableService {
  FirebaseCallableService({
    this.region = 'us-central1',
    FirebaseAccessTokenProvider? accessTokenProvider,
    FirebaseProjectIdProvider? projectIdProvider,
    FirebaseCallableHttpPost? httpPost,
    bool? forceHttpTransport,
  }) : _accessTokenProvider = accessTokenProvider ?? _firebaseAccessToken,
       _projectIdProvider = projectIdProvider ?? _firebaseProjectId,
       _httpPost = httpPost ?? http.post,
       _forceHttpTransport = forceHttpTransport;

  final String region;
  final FirebaseAccessTokenProvider _accessTokenProvider;
  final FirebaseProjectIdProvider _projectIdProvider;
  final FirebaseCallableHttpPost _httpPost;
  final bool? _forceHttpTransport;

  Future<Map<String, dynamic>> call(
    String functionName,
    Map<String, dynamic> data,
  ) => _call(functionName, data);

  Future<Map<String, dynamic>> callWithAccessToken(
    String functionName,
    Map<String, dynamic> data, {
    required String accessToken,
  }) => _call(functionName, data, accessToken: accessToken);

  Future<Map<String, dynamic>> _call(
    String functionName,
    Map<String, dynamic> data, {
    String? accessToken,
  }) async {
    if (!_useHttpTransport && accessToken == null) {
      try {
        final result = await FirebaseFunctions.instanceFor(
          region: region,
        ).httpsCallable(functionName).call<Map<String, dynamic>>(data);
        return Map<String, dynamic>.from(result.data);
      } on FirebaseFunctionsException catch (error) {
        throw FirebaseCallableFailure(
          error.code,
          error.message?.trim().isNotEmpty == true
              ? error.message!.trim()
              : 'The server could not complete this request.',
        );
      } on MissingPluginException {
        // A stale/unsupported native runner can still use the HTTPS transport.
      } on PlatformException catch (error) {
        if (!error.message.toString().contains('channel')) rethrow;
      }
    }
    return _callOverHttp(functionName, data, accessToken: accessToken);
  }

  bool get _useHttpTransport {
    if (_forceHttpTransport != null) return _forceHttpTransport;
    if (kIsWeb) return false;
    return defaultTargetPlatform == TargetPlatform.windows ||
        defaultTargetPlatform == TargetPlatform.linux;
  }

  Future<Map<String, dynamic>> _callOverHttp(
    String functionName,
    Map<String, dynamic> data, {
    String? accessToken,
  }) async {
    final token = accessToken ?? await _accessTokenProvider();
    if (token == null || token.trim().isEmpty) {
      throw const FirebaseCallableFailure(
        'unauthenticated',
        'Sign in before completing this request.',
      );
    }
    final projectId = _projectIdProvider().trim();
    if (projectId.isEmpty) {
      throw const FirebaseCallableFailure(
        'failed-precondition',
        'Firebase project configuration is unavailable.',
      );
    }
    final safeName = Uri.encodeComponent(functionName.trim());
    final endpoint = Uri.https(
      '$region-$projectId.cloudfunctions.net',
      '/$safeName',
    );
    final response = await _httpPost(
      endpoint,
      headers: {
        'Authorization': 'Bearer ${token.trim()}',
        'Content-Type': 'application/json; charset=utf-8',
      },
      body: jsonEncode({'data': data}),
    );
    final body = _decodeBody(response.body);
    final error = body['error'];
    if (error is Map) {
      final details = Map<String, dynamic>.from(error);
      throw FirebaseCallableFailure(
        _normalizeErrorCode(details['status'] ?? details['code']),
        '${details['message'] ?? 'The server could not complete this request.'}',
      );
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw FirebaseCallableFailure(
        'unavailable',
        'The requested service is temporarily unavailable.',
      );
    }
    final result = body['result'] ?? body['data'];
    if (result == null) return const <String, dynamic>{};
    if (result is! Map) {
      throw const FirebaseCallableFailure(
        'internal',
        'The requested service returned an invalid response.',
      );
    }
    return Map<String, dynamic>.from(result);
  }

  static Map<String, dynamic> _decodeBody(String body) {
    try {
      final decoded = jsonDecode(body);
      return decoded is Map
          ? Map<String, dynamic>.from(decoded)
          : const <String, dynamic>{};
    } on FormatException {
      return const <String, dynamic>{};
    }
  }

  static String _normalizeErrorCode(Object? value) {
    final normalized = '${value ?? 'unknown'}'.trim().toLowerCase().replaceAll(
      '_',
      '-',
    );
    return normalized.isEmpty ? 'unknown' : normalized;
  }

  static Future<String?> _firebaseAccessToken() async =>
      FirebaseAuth.instance.currentUser?.getIdToken();

  static String _firebaseProjectId() => Firebase.app().options.projectId;
}
