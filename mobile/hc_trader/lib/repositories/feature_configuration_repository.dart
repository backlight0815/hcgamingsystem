import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:cloud_functions/cloud_functions.dart';
import 'package:flutter/foundation.dart';

import '../models/feature_configuration.dart';

class FeatureConfigurationRepository {
  FeatureConfigurationRepository({
    required this.firebaseEnabled,
    FirebaseFirestore? firestore,
    FirebaseFunctions? functions,
  }) : _firestore = firestore ?? FirebaseFirestore.instance,
       _functions =
           functions ?? FirebaseFunctions.instanceFor(region: 'us-central1');

  final bool firebaseEnabled;
  final FirebaseFirestore _firestore;
  final FirebaseFunctions _functions;

  Stream<List<FeatureConfiguration>> configurations() async* {
    if (!firebaseEnabled) {
      yield const [];
      return;
    }
    try {
      await for (final snapshot
          in _firestore
              .collection('featureConfigurations')
              .orderBy('group')
              .snapshots()) {
        if (snapshot.docs.isEmpty) {
          yield await _legacyConfigurations();
        } else {
          yield snapshot.docs
              .map((doc) => FeatureConfiguration.fromMap(doc.id, doc.data()))
              .toList(growable: false);
        }
      }
    } catch (error) {
      if (kDebugMode) {
        final code = error is FirebaseException
            ? error.code
            : error.runtimeType.toString();
        debugPrint(
          '[FeatureConfiguration] using legacy configuration after '
          'versioned stream failure: $code',
        );
      }
      yield await _legacyConfigurations();
    }
  }

  Future<List<FeatureConfiguration>> _legacyConfigurations() async {
    final document = await _firestore
        .collection('app_configuration')
        .doc('modules')
        .get();
    final data = document.data() ?? const <String, dynamic>{};
    final now = DateTime.now();
    return [
      for (final definition in _legacyDefinitions)
        FeatureConfiguration(
          key: definition.key,
          name: definition.name,
          description: definition.description,
          group: definition.group,
          mode: data[definition.key] == false
              ? FeatureOperatingMode.disabled
              : FeatureOperatingMode.enabled,
          version: 0,
          updatedAt: now,
          platforms: const {
            FeaturePlatform.android,
            FeaturePlatform.ios,
            FeaturePlatform.web,
            FeaturePlatform.windows,
          },
          critical: definition.critical,
          dependencies: definition.dependencies,
        ),
    ];
  }

  Future<void> save(FeatureConfiguration configuration) async {
    if (!firebaseEnabled) throw StateError('Firebase is required.');
    await _functions.httpsCallable('updateFeatureConfiguration').call({
      ...configuration.toMap(expectedVersion: configuration.version),
      'feature_key': configuration.key,
    });
  }

  Future<void> rollback({
    required String featureKey,
    required int currentVersion,
  }) async {
    if (!firebaseEnabled) throw StateError('Firebase is required.');
    await _functions.httpsCallable('rollbackFeatureConfiguration').call({
      'feature_key': featureKey,
      'expected_version': currentVersion,
    });
  }
}

class _FeatureDefinition {
  const _FeatureDefinition(
    this.key,
    this.name,
    this.description,
    this.group, {
    this.critical = false,
    this.dependencies = const {},
  });

  final String key;
  final String name;
  final String description;
  final String group;
  final bool critical;
  final Set<String> dependencies;
}

const _legacyDefinitions = <_FeatureDefinition>[
  _FeatureDefinition(
    'trading_signals',
    'Trading signals',
    'Signal publishing, lifecycle updates and subscriber access.',
    'signals',
    critical: true,
  ),
  _FeatureDefinition(
    'push_notifications',
    'Push notifications',
    'Server delivery for authorized notifications.',
    'communication',
    critical: true,
  ),
  _FeatureDefinition(
    'stock_information',
    'Stock information',
    'Stock search, profiles, quotes, news and watchlists.',
    'content',
  ),
  _FeatureDefinition(
    'stock_report_publishing',
    'Stock report publishing',
    'Financial daily-report generation, review and publication.',
    'content',
    critical: true,
    dependencies: {'stock_information'},
  ),
  _FeatureDefinition(
    'content_publishing',
    'Content publishing',
    'Content authoring and publication workflows.',
    'content',
    critical: true,
  ),
  _FeatureDefinition(
    'announcements',
    'Announcements',
    'Operational announcements, inbox and acknowledgement.',
    'communication',
  ),
  _FeatureDefinition(
    'leaderboard',
    'Leaderboard',
    'Privacy-aware, risk-adjusted verified rankings.',
    'trading',
  ),
  _FeatureDefinition(
    'traders_performance',
    'Prop firm performance',
    'Challenge analytics, limits and performance calculations.',
    'prop_firm',
  ),
  _FeatureDefinition(
    'mt5_sync',
    'MT5 synchronization',
    'Imports and reconciles MT5 accounts, orders, deals and trades.',
    'trading',
    critical: true,
  ),
  _FeatureDefinition(
    'user_registrations',
    'User registrations',
    'New-account registration and onboarding.',
    'administration',
    critical: true,
  ),
];
