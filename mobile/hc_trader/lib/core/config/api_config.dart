import 'package:flutter/foundation.dart';

class ApiConfig {
  const ApiConfig._();

  static const productionBaseUrl = 'https://www.hctradinghub.com/api/mobile/v1';
  static const androidEmulatorBaseUrl =
      'http://10.0.2.2/hcgaming/public/api/mobile/v1';
  static const localWebBaseUrl =
      'http://localhost/hcgaming/public/api/mobile/v1';

  static String get baseUrl {
    // Published builds always use the cPanel production API. Local development
    // can opt in to another endpoint with --dart-define=HC_API_BASE_URL=...
    if (kReleaseMode) {
      return productionBaseUrl;
    }

    const configured = String.fromEnvironment('HC_API_BASE_URL');
    if (configured.trim().isNotEmpty) {
      return configured.replaceFirst(RegExp(r'/+$'), '');
    }
    return productionBaseUrl;
  }
}
