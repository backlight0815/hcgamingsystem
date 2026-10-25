import 'dart:async';

import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_auth/firebase_auth.dart';

import 'app/hc_trader_app.dart';
import 'core/localization/app_locale_controller.dart';
import 'core/config/api_config.dart';
import 'core/network/api_client.dart';
import 'core/storage/local_app_storage.dart';
import 'firebase_options.dart';
import 'repositories/auth_repository.dart';
import 'repositories/trading_repository.dart';
import 'services/push_notification_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);

  final storage = await LocalAppStorage.create();
  final localeController = AppLocaleController.create(storage);
  final apiClient = ApiClient(
    baseUrl: ApiConfig.baseUrl,
    authTokenProvider: () async {
      final user = FirebaseAuth.instance.currentUser;
      return await user?.getIdToken();
    },
  );

  runApp(
    HCTraderApp(
      authRepository: AuthRepository(),
      tradingRepository: TradingRepository(
        firebaseEnabled: true,
        apiClient: apiClient,
        storage: storage,
      ),
      firebaseEnabled: true,
      storage: storage,
      localeController: localeController,
    ),
  );

  // Notifications are optional startup work. A slow native notification
  // plugin must never prevent the first app frame from being rendered.
  unawaited(
    PushNotificationService.instance.initialize(storage).catchError((
      Object error,
      StackTrace stackTrace,
    ) {
      debugPrint('Push notification initialization failed: $error');
    }),
  );
}
