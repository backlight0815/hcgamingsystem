import 'dart:async';
import 'dart:convert';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../core/storage/local_app_storage.dart';
import '../firebase_options.dart';
import '../models/app_user.dart';

const _notificationChannel = AndroidNotificationChannel(
  'hc_trader_updates',
  'Trading updates',
  description: 'Signals, analysis, learning resources, and appointments.',
  importance: Importance.high,
);

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp(
      options: DefaultFirebaseOptions.currentPlatform,
    );
  }
}

class NotificationPreferences {
  const NotificationPreferences({
    this.signals = true,
    this.newSignals = true,
    this.entryActivation = true,
    this.stopLossUpdates = true,
    this.breakevenUpdates = true,
    this.takeProfitEvents = true,
    this.signalCancellation = true,
    this.signalClosure = true,
    this.importantSignalNotes = true,
    this.marketAnalysis = true,
    this.recordings = true,
    this.knowledgeCentre = true,
    this.appointments = true,
    this.announcements = true,
    this.financialDailyReports = true,
    this.stockWatchlistUpdates = true,
    this.sound = true,
    this.vibration = true,
    this.quietHoursEnabled = false,
    this.quietHoursStartMinutes = 1320,
    this.quietHoursEndMinutes = 420,
    this.timezone = 'Asia/Singapore',
  });

  final bool signals;
  final bool newSignals;
  final bool entryActivation;
  final bool stopLossUpdates;
  final bool breakevenUpdates;
  final bool takeProfitEvents;
  final bool signalCancellation;
  final bool signalClosure;
  final bool importantSignalNotes;
  final bool marketAnalysis;
  final bool recordings;
  final bool knowledgeCentre;
  final bool appointments;
  final bool announcements;
  final bool financialDailyReports;
  final bool stockWatchlistUpdates;
  final bool sound;
  final bool vibration;
  final bool quietHoursEnabled;
  final int quietHoursStartMinutes;
  final int quietHoursEndMinutes;
  final String timezone;

  factory NotificationPreferences.fromMap(Map<String, dynamic>? data) {
    bool enabled(String key) => data?[key] != false;
    int minutes(String key, int fallback) =>
        int.tryParse('${data?[key] ?? ''}') ?? fallback;
    return NotificationPreferences(
      signals: enabled('signals'),
      newSignals: enabled('new_signals'),
      entryActivation: enabled('entry_activation'),
      stopLossUpdates: enabled('stop_loss_updates'),
      breakevenUpdates: enabled('breakeven_updates'),
      takeProfitEvents: enabled('take_profit_events'),
      signalCancellation: enabled('signal_cancellation'),
      signalClosure: enabled('signal_closure'),
      importantSignalNotes: enabled('important_signal_notes'),
      marketAnalysis: enabled('market_analysis'),
      recordings: enabled('recordings'),
      knowledgeCentre: enabled('knowledge_centre'),
      appointments: enabled('appointments'),
      announcements: enabled('announcements'),
      financialDailyReports: enabled('financial_daily_reports'),
      stockWatchlistUpdates: enabled('stock_watchlist_updates'),
      sound: enabled('sound'),
      vibration: enabled('vibration'),
      quietHoursEnabled: data?['quiet_hours_enabled'] == true,
      quietHoursStartMinutes: minutes('quiet_hours_start_minutes', 1320),
      quietHoursEndMinutes: minutes('quiet_hours_end_minutes', 420),
      timezone: '${data?['timezone'] ?? 'Asia/Singapore'}',
    );
  }

  NotificationPreferences copyWith({
    bool? signals,
    bool? newSignals,
    bool? entryActivation,
    bool? stopLossUpdates,
    bool? breakevenUpdates,
    bool? takeProfitEvents,
    bool? signalCancellation,
    bool? signalClosure,
    bool? importantSignalNotes,
    bool? marketAnalysis,
    bool? recordings,
    bool? knowledgeCentre,
    bool? appointments,
    bool? announcements,
    bool? financialDailyReports,
    bool? stockWatchlistUpdates,
    bool? sound,
    bool? vibration,
    bool? quietHoursEnabled,
    int? quietHoursStartMinutes,
    int? quietHoursEndMinutes,
    String? timezone,
  }) {
    return NotificationPreferences(
      signals: signals ?? this.signals,
      newSignals: newSignals ?? this.newSignals,
      entryActivation: entryActivation ?? this.entryActivation,
      stopLossUpdates: stopLossUpdates ?? this.stopLossUpdates,
      breakevenUpdates: breakevenUpdates ?? this.breakevenUpdates,
      takeProfitEvents: takeProfitEvents ?? this.takeProfitEvents,
      signalCancellation: signalCancellation ?? this.signalCancellation,
      signalClosure: signalClosure ?? this.signalClosure,
      importantSignalNotes: importantSignalNotes ?? this.importantSignalNotes,
      marketAnalysis: marketAnalysis ?? this.marketAnalysis,
      recordings: recordings ?? this.recordings,
      knowledgeCentre: knowledgeCentre ?? this.knowledgeCentre,
      appointments: appointments ?? this.appointments,
      announcements: announcements ?? this.announcements,
      financialDailyReports:
          financialDailyReports ?? this.financialDailyReports,
      stockWatchlistUpdates:
          stockWatchlistUpdates ?? this.stockWatchlistUpdates,
      sound: sound ?? this.sound,
      vibration: vibration ?? this.vibration,
      quietHoursEnabled: quietHoursEnabled ?? this.quietHoursEnabled,
      quietHoursStartMinutes:
          quietHoursStartMinutes ?? this.quietHoursStartMinutes,
      quietHoursEndMinutes: quietHoursEndMinutes ?? this.quietHoursEndMinutes,
      timezone: timezone ?? this.timezone,
    );
  }

  Map<String, Object> toMap() => {
    'signals': signals,
    'new_signals': newSignals,
    'entry_activation': entryActivation,
    'stop_loss_updates': stopLossUpdates,
    'breakeven_updates': breakevenUpdates,
    'take_profit_events': takeProfitEvents,
    'signal_cancellation': signalCancellation,
    'signal_closure': signalClosure,
    'important_signal_notes': importantSignalNotes,
    'market_analysis': marketAnalysis,
    'recordings': recordings,
    'knowledge_centre': knowledgeCentre,
    'appointments': appointments,
    'announcements': announcements,
    'financial_daily_reports': financialDailyReports,
    'stock_watchlist_updates': stockWatchlistUpdates,
    'sound': sound,
    'vibration': vibration,
    'quiet_hours_enabled': quietHoursEnabled,
    'quiet_hours_start_minutes': quietHoursStartMinutes,
    'quiet_hours_end_minutes': quietHoursEndMinutes,
    'timezone': timezone,
  };
}

class PushNotificationService {
  PushNotificationService._();

  static final instance = PushNotificationService._();

  static const _preferencesKey = 'notification_preferences';
  static const _topics = <String, String>{
    'signals': 'hc_trading_signals',
    'market_analysis': 'hc_market_analysis',
    'recordings': 'hc_recordings',
    'knowledge_centre': 'hc_knowledge_centre',
  };

  final _messaging = FirebaseMessaging.instance;
  final _localNotifications = FlutterLocalNotificationsPlugin();
  final _firestore = FirebaseFirestore.instance;
  LocalAppStorage? _storage;
  AppUser? _user;
  String? _pendingAnnouncementId;
  String? _pendingSignalId;
  String? _pendingStockSymbol;
  StreamSubscription<RemoteMessage>? _messageSubscription;
  StreamSubscription<RemoteMessage>? _messageOpenedSubscription;
  StreamSubscription<String>? _tokenSubscription;
  final _announcementOpenController = StreamController<String?>.broadcast();
  final _signalOpenController = StreamController<String>.broadcast();
  final _stockOpenController = StreamController<String>.broadcast();
  bool _initialized = false;

  bool get isSupported =>
      !kIsWeb &&
      const {
        TargetPlatform.android,
        TargetPlatform.iOS,
        TargetPlatform.macOS,
      }.contains(defaultTargetPlatform);

  Future<void> initialize(LocalAppStorage storage) async {
    _storage = storage;
    if (_initialized || !isSupported) return;
    _initialized = true;

    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

    const initializationSettings = InitializationSettings(
      android: AndroidInitializationSettings('ic_launcher'),
      iOS: DarwinInitializationSettings(
        requestAlertPermission: false,
        requestBadgePermission: false,
        requestSoundPermission: false,
      ),
      macOS: DarwinInitializationSettings(
        requestAlertPermission: false,
        requestBadgePermission: false,
        requestSoundPermission: false,
      ),
    );
    await _localNotifications.initialize(
      settings: initializationSettings,
      onDidReceiveNotificationResponse: (response) {
        _handleNotificationPayload(response.payload);
      },
    );
    await _localNotifications
        .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin
        >()
        ?.createNotificationChannel(_notificationChannel);

    await _messaging.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );
    _messageSubscription = FirebaseMessaging.onMessage.listen(
      _showForegroundNotification,
    );
    _messageOpenedSubscription = FirebaseMessaging.onMessageOpenedApp.listen(
      _handleNotificationOpen,
    );
    unawaited(
      _messaging.getInitialMessage().then((message) {
        if (message != null) _handleNotificationOpen(message);
      }),
    );
    _tokenSubscription = _messaging.onTokenRefresh.listen((token) async {
      final user = _user;
      if (user != null) await _saveToken(user, token);
    });
  }

  Stream<String?> get announcementOpenRequests =>
      _announcementOpenController.stream;

  Stream<String> get signalOpenRequests => _signalOpenController.stream;

  Stream<String> get stockOpenRequests => _stockOpenController.stream;

  String? takePendingAnnouncementId() {
    final value = _pendingAnnouncementId;
    _pendingAnnouncementId = null;
    return value;
  }

  String? takePendingSignalId() {
    final value = _pendingSignalId;
    _pendingSignalId = null;
    return value;
  }

  String? takePendingStockSymbol() {
    final value = _pendingStockSymbol;
    _pendingStockSymbol = null;
    return value;
  }

  NotificationPreferences preferencesFor(String userId) {
    return NotificationPreferences.fromMap(
      _storage?.readMap(userId, _preferencesKey),
    );
  }

  Future<NotificationSettings?> configureUser(AppUser user) async {
    _user = user;
    if (!isSupported) return null;
    await loadPreferences(user);
    final settings = await requestPermission();
    await _syncTopics(user.uid);
    final token = await _messaging.getToken();
    if (token != null && token.isNotEmpty) await _saveToken(user, token);
    return settings;
  }

  Future<NotificationPreferences> loadPreferences(AppUser user) async {
    final local = preferencesFor(user.uid);
    try {
      final document = await _firestore
          .collection('users')
          .doc(user.uid)
          .collection('notificationPreferences')
          .doc('default')
          .get();
      final data = document.data();
      if (data == null) return local;
      final preferences = NotificationPreferences.fromMap(data);
      await _storage?.writeMap(user.uid, _preferencesKey, preferences.toMap());
      return preferences;
    } catch (_) {
      return local;
    }
  }

  Future<NotificationSettings> requestPermission() {
    return _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );
  }

  Future<NotificationSettings?> currentSettings() async {
    if (!isSupported) return null;
    return _messaging.getNotificationSettings();
  }

  Future<void> updatePreferences(
    AppUser user,
    NotificationPreferences preferences,
  ) async {
    await _storage?.writeMap(user.uid, _preferencesKey, preferences.toMap());
    _user = user;
    final batch = _firestore.batch();
    final userReference = _firestore.collection('users').doc(user.uid);
    batch.set(userReference, {
      'announcementNotificationsEnabled': preferences.announcements,
      'announcement_notifications_enabled': preferences.announcements,
      'signal_notifications_enabled': preferences.signals,
      'financial_daily_report_notifications_enabled':
          preferences.financialDailyReports,
      'updated_at': FieldValue.serverTimestamp(),
    }, SetOptions(merge: true));
    batch.set(
      userReference.collection('notificationPreferences').doc('default'),
      {...preferences.toMap(), 'updated_at': FieldValue.serverTimestamp()},
      SetOptions(merge: true),
    );
    await batch.commit();
    if (!isSupported) return;
    await _syncTopics(user.uid);
    final token = await _messaging.getToken();
    if (token != null && token.isNotEmpty) await _saveToken(user, token);
  }

  Future<void> unregisterUser(String userId) async {
    if (!isSupported) return;
    String? token;
    try {
      token = await _messaging.getToken();
      if (token != null && token.isNotEmpty) {
        final userReference = _firestore.collection('users').doc(userId);
        final batch = _firestore.batch();
        batch.delete(
          userReference
              .collection('notification_tokens')
              .doc(_tokenDocumentId(token)),
        );
        batch.delete(
          userReference.collection('devices').doc(_tokenDocumentId(token)),
        );
        await batch.commit();
      }
    } catch (_) {
      // Signing out should never be blocked by notification cleanup.
    }
    for (final topic in _topics.values) {
      try {
        await _messaging.unsubscribeFromTopic(topic);
      } catch (_) {
        // Continue cleaning up the remaining subscriptions.
      }
    }
    if (_user?.uid == userId) _user = null;
  }

  Future<void> _syncTopics(String userId) async {
    final preferences = preferencesFor(userId).toMap();
    for (final entry in _topics.entries) {
      if (preferences[entry.key] != false) {
        await _messaging.subscribeToTopic(entry.value);
      } else {
        await _messaging.unsubscribeFromTopic(entry.value);
      }
    }
  }

  Future<void> _saveToken(AppUser user, String token) async {
    final preferences = preferencesFor(user.uid);
    final documentId = _tokenDocumentId(token);
    final userReference = _firestore.collection('users').doc(user.uid);
    final values = <String, Object?>{
      'token': token,
      'device_id': documentId,
      'platform': defaultTargetPlatform.name,
      'language': PlatformDispatcher.instance.locale.languageCode,
      'timezone': user.timezone,
      'timezone_offset_minutes': user.timezoneOffsetMinutes,
      'preferences': preferences.toMap(),
      'notifications_enabled': true,
      'appointments_enabled': preferences.appointments,
      'announcements_enabled': preferences.announcements,
      'signals_enabled': preferences.signals,
      'financial_daily_reports_enabled': preferences.financialDailyReports,
      'stock_watchlist_updates_enabled': preferences.stockWatchlistUpdates,
      'updated_at': FieldValue.serverTimestamp(),
      'last_seen_at': FieldValue.serverTimestamp(),
    };
    final batch = _firestore.batch();
    batch.set(
      userReference.collection('notification_tokens').doc(documentId),
      values,
      SetOptions(merge: true),
    );
    batch.set(
      userReference.collection('devices').doc(documentId),
      values,
      SetOptions(merge: true),
    );
    await batch.commit();
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    if (defaultTargetPlatform != TargetPlatform.android) return;
    final notification = message.notification;
    if (notification == null) return;
    final user = _user;
    final preferences = user == null
        ? const NotificationPreferences()
        : preferencesFor(user.uid);
    await _localNotifications.show(
      id: message.messageId?.hashCode ?? DateTime.now().millisecondsSinceEpoch,
      title: notification.title,
      body: notification.body,
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          'hc_trader_updates',
          'Trading updates',
          channelDescription:
              'Signals, analysis, learning resources, and appointments.',
          importance: Importance.high,
          priority: Priority.high,
          icon: 'ic_launcher',
          playSound: preferences.sound,
          enableVibration: preferences.vibration,
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  void _handleNotificationOpen(RemoteMessage message) {
    _handleNotificationData(message.data);
  }

  void _handleNotificationPayload(String? payload) {
    if (payload?.trim().isEmpty != false) return;
    try {
      final decoded = jsonDecode(payload!) as Map;
      _handleNotificationData(Map<String, dynamic>.from(decoded));
    } catch (_) {
      // Malformed notification payloads should simply open the app normally.
    }
  }

  void _handleNotificationData(Map<String, dynamic> data) {
    if (data['category'] == 'signals') {
      final id = data['document_id']?.toString().trim();
      if (id?.isNotEmpty == true) {
        _pendingSignalId = id;
        _signalOpenController.add(id!);
      }
      return;
    }
    if (const {'stock_reports', 'stock_watchlist'}.contains(data['category'])) {
      final symbol = data['symbol']?.toString().trim().toUpperCase();
      if (symbol?.isNotEmpty == true) {
        _pendingStockSymbol = symbol;
        _stockOpenController.add(symbol!);
      }
      return;
    }
    if (data['category'] != 'announcements') return;
    final id = data['document_id']?.toString();
    _pendingAnnouncementId = id;
    _announcementOpenController.add(id);
  }

  String _tokenDocumentId(String token) =>
      base64Url.encode(utf8.encode(token)).replaceAll('=', '');

  Future<void> dispose() async {
    await _messageSubscription?.cancel();
    await _messageOpenedSubscription?.cancel();
    await _tokenSubscription?.cancel();
    await _announcementOpenController.close();
    await _signalOpenController.close();
    await _stockOpenController.close();
  }
}
