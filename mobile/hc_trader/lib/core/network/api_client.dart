import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class ApiClient {
  ApiClient({
    required this.baseUrl,
    http.Client? httpClient,
    this.authTokenProvider,
    this.requestTimeout = const Duration(seconds: 20),
    this.authTokenTimeout = const Duration(seconds: 8),
  }) : _http = httpClient ?? http.Client();

  static const _tokenKey = 'hc_mobile_api_token';
  static const _storage = FlutterSecureStorage();

  final String baseUrl;
  final http.Client _http;
  final Future<String?> Function()? authTokenProvider;
  final Duration requestTimeout;
  final Duration authTokenTimeout;
  String? _token;
  void Function()? onUnauthorized;

  String resolveUrl(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) return '';
    final uri = Uri.tryParse(trimmed);
    if (uri?.hasScheme == true) return trimmed;

    final apiUri = Uri.parse(baseUrl);
    final rootRelative = '/${trimmed.replaceFirst(RegExp(r'^/+'), '')}';
    return apiUri
        .replace(path: rootRelative, query: null, fragment: null)
        .toString();
  }

  Future<bool> restoreToken() async {
    _token = await _storage.read(key: _tokenKey);
    return _token?.isNotEmpty == true;
  }

  Future<void> setToken(String token) async {
    _token = token;
    await _storage.write(key: _tokenKey, value: token);
  }

  Future<void> clearToken() async {
    _token = null;
    await _storage.delete(key: _tokenKey);
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, String> headers = const {},
    bool authenticate = true,
  }) => _send(
    'GET',
    path,
    additionalHeaders: headers,
    authenticate: authenticate,
  );

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, Object?> body = const {},
    Map<String, String> headers = const {},
  }) => _send('POST', path, body: body, additionalHeaders: headers);

  Future<Map<String, dynamic>> put(
    String path, {
    Map<String, Object?> body = const {},
    Map<String, String> headers = const {},
  }) => _send('PUT', path, body: body, additionalHeaders: headers);

  Future<Map<String, dynamic>> patch(
    String path, {
    Map<String, Object?> body = const {},
    Map<String, String> headers = const {},
  }) => _send('PATCH', path, body: body, additionalHeaders: headers);

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, Object?> body = const {},
    Map<String, String> headers = const {},
  }) => _send('DELETE', path, body: body, additionalHeaders: headers);

  Future<Map<String, dynamic>> postMultipart(
    String path, {
    Map<String, String> fields = const {},
    List<ApiUploadFile> files = const [],
  }) async {
    final authorization = await _authorizationHeader();
    final uri = Uri.parse('$baseUrl/${path.replaceFirst(RegExp(r'^/+'), '')}');
    final requestHeaders = <String, String>{'Accept': 'application/json'};
    if (authorization != null) {
      requestHeaders['Authorization'] = authorization;
    }
    final request = http.MultipartRequest('POST', uri)
      ..headers.addAll(requestHeaders)
      ..fields.addAll(fields);
    for (final file in files) {
      request.files.add(
        file.bytes != null
            ? http.MultipartFile.fromBytes(
                file.field,
                file.bytes!,
                filename: file.filename,
              )
            : await http.MultipartFile.fromPath(
                file.field,
                file.path!,
                filename: file.filename,
              ),
      );
    }
    try {
      _debugRequest('POST', uri);
      return _handleResponse(
        await http.Response.fromStream(
          await request.send().timeout(requestTimeout),
        ),
        method: 'POST',
        uri: uri,
      );
    } on TimeoutException catch (error) {
      throw ApiException(
        'The HC Trading Hub request timed out. Please try again.',
        errorCode: 'NETWORK_TIMEOUT',
        cause: error,
      );
    } on ApiException {
      rethrow;
    } on Exception catch (error) {
      throw _transportError(error, uri);
    }
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, Object?>? body,
    Map<String, String> additionalHeaders = const {},
    bool authenticate = true,
  }) async {
    final authorization = authenticate ? await _authorizationHeader() : null;
    final uri = Uri.parse('$baseUrl/${path.replaceFirst(RegExp(r'^/+'), '')}');
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      ...additionalHeaders,
    };
    if (authorization != null) {
      headers['Authorization'] = authorization;
    }

    late http.Response response;
    final encodedBody = body == null ? null : jsonEncode(body);
    try {
      _debugRequest(method, uri);
      response = await (switch (method) {
        'GET' => _http.get(uri, headers: headers),
        'POST' => _http.post(uri, headers: headers, body: encodedBody),
        'PUT' => _http.put(uri, headers: headers, body: encodedBody),
        'PATCH' => _http.patch(uri, headers: headers, body: encodedBody),
        'DELETE' => _http.delete(uri, headers: headers, body: encodedBody),
        _ => throw ArgumentError('Unsupported HTTP method: $method'),
      }).timeout(requestTimeout);
    } on TimeoutException catch (error) {
      throw ApiException(
        'The HC Trading Hub request timed out. Please try again.',
        errorCode: 'NETWORK_TIMEOUT',
        cause: error,
      );
    } on Exception catch (error) {
      throw _transportError(error, uri);
    }

    return _handleResponse(
      response,
      method: method,
      uri: uri,
      authenticatedRequest: authenticate,
    );
  }

  Stream<Map<String, dynamic>> eventStream(
    String path, {
    bool authenticate = true,
  }) async* {
    final authorization = authenticate ? await _authorizationHeader() : null;
    final uri = Uri.parse('$baseUrl/${path.replaceFirst(RegExp(r'^/+'), '')}');
    final requestHeaders = <String, String>{
      'Accept': 'text/event-stream',
      'Cache-Control': 'no-cache',
    };
    if (authorization != null) {
      requestHeaders['Authorization'] = authorization;
    }
    final request = http.Request('GET', uri)..headers.addAll(requestHeaders);

    late http.StreamedResponse response;
    try {
      _debugRequest('GET (stream)', uri);
      response = await _http.send(request).timeout(requestTimeout);
      _debugStatus('GET (stream)', uri, response.statusCode);
    } on TimeoutException catch (error) {
      throw ApiException(
        'The trading signal update stream timed out while connecting.',
        cause: error,
      );
    } on Exception catch (error) {
      throw ApiException(
        'Unable to connect to the trading signal update stream.',
        cause: error,
      );
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        response.statusCode == 401
            ? 'Your trading signal session has expired.'
            : 'The trading signal update stream is unavailable.',
        statusCode: response.statusCode,
      );
    }

    await for (final line
        in response.stream
            .transform(utf8.decoder)
            .transform(const LineSplitter())) {
      if (!line.startsWith('data:')) continue;
      final value = line.substring(5).trim();
      if (value.isEmpty) continue;
      try {
        final decoded = jsonDecode(value);
        if (decoded is Map) {
          yield Map<String, dynamic>.from(decoded);
        }
      } on FormatException {
        // Ignore a malformed individual SSE frame; the REST sync remains authoritative.
      }
    }
  }

  Future<String?> _authorizationHeader() async {
    String? provided;
    try {
      provided = await authTokenProvider?.call().timeout(authTokenTimeout);
    } on TimeoutException catch (error) {
      if (_token?.isNotEmpty != true) {
        throw ApiException(
          'Authentication took too long. Please try again.',
          cause: error,
        );
      }
    } on Exception catch (error) {
      if (_token?.isNotEmpty != true) {
        throw ApiException(
          'Unable to refresh the mobile authentication token.',
          cause: error,
        );
      }
    }
    final token = provided?.trim().isNotEmpty == true
        ? provided!.trim()
        : _token;
    return token?.isNotEmpty == true ? 'Bearer $token' : null;
  }

  Future<Map<String, dynamic>> _handleResponse(
    http.Response response, {
    String method = 'HTTP',
    Uri? uri,
    bool authenticatedRequest = true,
  }) async {
    _debugStatus(method, uri, response.statusCode);
    Map<String, dynamic> payload = {};
    var invalidJson = false;
    try {
      if (response.body.trim().isEmpty) {
        payload = <String, dynamic>{};
      } else {
        final decoded = jsonDecode(response.body);
        if (decoded is! Map) throw const FormatException();
        payload = Map<String, dynamic>.from(decoded);
      }
    } on FormatException {
      invalidJson = true;
    }

    if (response.statusCode == 401 && authenticatedRequest) {
      try {
        await clearToken();
      } on Object catch (error) {
        if (kDebugMode) {
          debugPrint(
            '[HC API] Unable to clear the expired local token: $error',
          );
        }
      }
      onUnauthorized?.call();
    }
    if (response.statusCode < 200 || response.statusCode >= 300) {
      final (fallback, code) = switch (response.statusCode) {
        401 => ('Your session has expired. Sign in again.', 'UNAUTHENTICATED'),
        403 => ('Your account cannot access this feature.', 'FORBIDDEN'),
        404 => (
          'The requested API resource is unavailable. Please update the app or contact support.',
          'API_NOT_FOUND',
        ),
        409 => (
          'This record changed on another device. Review it before syncing.',
          'SYNC_CONFLICT',
        ),
        422 => ('Some record fields need correction.', 'VALIDATION_FAILED'),
        429 => (
          'Too many requests. Wait a minute and try again.',
          'RATE_LIMITED',
        ),
        >= 500 => (
          'The server is temporarily unavailable. Your saved records are safe. Please retry.',
          'SERVER_ERROR',
        ),
        _ => ('The request could not be completed.', 'HTTP_ERROR'),
      };
      throw ApiException(
        (response.statusCode >= 500 ? fallback : payload['message'] ?? fallback)
            .toString(),
        statusCode: response.statusCode,
        errorCode: payload['error_code']?.toString() ?? code,
        errors: _readErrors(payload['errors']),
      );
    }

    if (invalidJson || (payload.isEmpty && response.statusCode != 204)) {
      throw ApiException(
        'The server returned an unreadable response.',
        statusCode: response.statusCode,
        errorCode: 'INVALID_API_RESPONSE',
      );
    }

    return payload;
  }

  void _debugRequest(String method, Uri uri) {
    if (!kDebugMode) return;
    debugPrint(
      '[HC API] $method ${uri.scheme}://${uri.host}${uri.hasPort ? ':${uri.port}' : ''}${uri.path}',
    );
  }

  ApiException _transportError(Exception error, Uri uri) {
    final (description, code) = switch (error) {
      HandshakeException _ => (
        'The secure connection to ${uri.host} failed. Check your phone date and time, then retry.',
        'NETWORK_TLS_FAILURE',
      ),
      SocketException _ => (
        'The app could not connect to ${uri.host}. Check your connection or switch networks, then retry.',
        'NETWORK_SOCKET_FAILURE',
      ),
      http.ClientException _ => (
        'The app\'s HTTP connection to ${uri.host} failed. Please retry or switch networks.',
        'NETWORK_HTTP_CLIENT_FAILURE',
      ),
      _ => (
        'The app could not connect to ${uri.host}. Please retry.',
        'NETWORK_TRANSPORT_FAILURE',
      ),
    };
    if (kDebugMode) {
      debugPrint('[HC API] $code ${uri.path}: $error');
    }
    return ApiException(description, errorCode: code, cause: error);
  }

  void _debugStatus(String method, Uri? uri, int statusCode) {
    if (!kDebugMode) return;
    final path = uri == null ? '' : ' ${uri.path}';
    debugPrint('[HC API] $method$path -> HTTP $statusCode');
  }

  Map<String, List<String>> _readErrors(Object? raw) {
    if (raw is! Map) return const {};
    return raw.map((key, value) {
      final messages = value is List
          ? value.map((item) => item.toString()).toList()
          : [value.toString()];
      return MapEntry(key.toString(), messages);
    });
  }
}

class ApiUploadFile {
  const ApiUploadFile({
    required this.field,
    required this.filename,
    this.path,
    this.bytes,
  }) : assert(path != null || bytes != null);

  final String field;
  final String filename;
  final String? path;
  final List<int>? bytes;
}

class ApiException implements Exception {
  const ApiException(
    this.message, {
    this.statusCode,
    this.errorCode,
    this.errors = const {},
    this.cause,
  });

  final String message;
  final int? statusCode;
  final String? errorCode;
  final Map<String, List<String>> errors;
  final Object? cause;

  @override
  String toString() => message;
}
