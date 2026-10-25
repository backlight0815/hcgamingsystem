import 'dart:async';

import '../core/network/api_client.dart';

class TradingSignalApiService {
  const TradingSignalApiService(this._client);

  final ApiClient _client;

  Future<List<Map<String, dynamic>>> fetchSignals({int perPage = 100}) async {
    final signals = <Map<String, dynamic>>[];
    var page = 1;
    var lastPage = 1;
    do {
      final response = await _client.get(
        'signals?per_page=$perPage&page=$page',
      );
      if (response['success'] != true) {
        throw ApiException(
          (response['message'] ?? 'The signal request was not successful.')
              .toString(),
        );
      }
      final data = response['data'];
      if (data is! Map || data['signals'] is! List) {
        throw const ApiException(
          'The server returned an invalid trading signal response.',
        );
      }
      signals.addAll(
        (data['signals'] as List).whereType<Map>().map(
          (item) => Map<String, dynamic>.from(item),
        ),
      );
      final pagination = data['pagination'];
      lastPage = pagination is Map
          ? _positiveInt(pagination['last_page'], fallback: page)
          : page;
      page++;
    } while (page <= lastPage);

    return List<Map<String, dynamic>>.unmodifiable(signals);
  }

  Future<Map<String, dynamic>?> fetchSignal(String signalId) async {
    final response = await _client.get(
      'signals/${Uri.encodeComponent(signalId.trim())}',
    );
    final data = response['data'];
    if (data is! Map || data['signal'] is! Map) {
      throw const ApiException(
        'The server returned an invalid trading signal detail response.',
      );
    }
    return Map<String, dynamic>.from(data['signal'] as Map);
  }

  Future<Map<String, dynamic>> submitSignal({
    required Map<String, Object?> payload,
    required String idempotencyKey,
  }) async {
    return _client.post(
      'signals',
      body: payload,
      headers: {'X-Idempotency-Key': idempotencyKey},
    );
  }

  Future<Map<String, dynamic>> updateLifecycle({
    required String signalId,
    required Map<String, Object?> payload,
  }) {
    return _client.post('signals/$signalId/lifecycle', body: payload);
  }

  Stream<Map<String, dynamic>> watchChanges() async* {
    while (true) {
      try {
        await for (final event in _client.eventStream('signals/stream')) {
          yield event;
        }
      } on ApiException {
        // The caller keeps cached signals visible while this reconnects.
      }
      await Future<void>.delayed(const Duration(seconds: 3));
    }
  }

  static int _positiveInt(Object? value, {required int fallback}) {
    final parsed = value is num ? value.toInt() : int.tryParse('$value');
    return parsed != null && parsed > 0 ? parsed : fallback;
  }
}
