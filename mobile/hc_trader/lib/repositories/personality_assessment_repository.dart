import 'dart:math';

import 'package:cloud_firestore/cloud_firestore.dart';

import '../core/storage/local_app_storage.dart';
import '../models/personality_assessment.dart';

typedef PersonalityRemoteAttemptLoader =
    Future<List<PersonalityAssessmentAttempt>> Function(String userId);
typedef PersonalityRemoteAttemptWriter =
    Future<void> Function(PersonalityAssessmentAttempt attempt);

class PersonalityAssessmentRepository {
  PersonalityAssessmentRepository({
    required this.firebaseEnabled,
    required this.storage,
    FirebaseFirestore? firestore,
    DateTime Function()? now,
    PersonalityRemoteAttemptLoader? remoteLoader,
    PersonalityRemoteAttemptWriter? remoteProgressWriter,
    PersonalityRemoteAttemptWriter? remoteCompletionWriter,
  }) : _firestore = firestore,
       _now = now ?? DateTime.now,
       _remoteLoader = remoteLoader,
       _remoteProgressWriter = remoteProgressWriter,
       _remoteCompletionWriter = remoteCompletionWriter;

  static const _storageKey = 'personality_assessment_attempts';
  static final _random = Random.secure();

  final bool firebaseEnabled;
  final LocalAppStorage storage;
  final FirebaseFirestore? _firestore;
  final DateTime Function() _now;
  final PersonalityRemoteAttemptLoader? _remoteLoader;
  final PersonalityRemoteAttemptWriter? _remoteProgressWriter;
  final PersonalityRemoteAttemptWriter? _remoteCompletionWriter;
  Object? _lastSyncError;

  FirebaseFirestore get _database => _firestore ?? FirebaseFirestore.instance;

  /// Returns and clears the most recent non-fatal cloud synchronization error.
  /// Local persistence errors are still thrown to the caller.
  Object? takeLastSyncError() {
    final error = _lastSyncError;
    _lastSyncError = null;
    return error;
  }

  PersonalityAssessmentAttempt createAttempt({
    required String userId,
    String? retakeOf,
  }) {
    final now = _now();
    return PersonalityAssessmentAttempt(
      id: 'personality_${now.microsecondsSinceEpoch}_${_random.nextInt(1 << 20)}',
      userId: userId,
      testVersion: PersonalityQuestionBank.testVersion,
      startedAt: now,
      updatedAt: now,
      retakeOf: retakeOf,
    );
  }

  Future<List<PersonalityAssessmentAttempt>> loadAttempts(String userId) async {
    final local = await _localAttempts(userId);
    if (!firebaseEnabled) return _sorted(local);

    _lastSyncError = null;
    late final List<PersonalityAssessmentAttempt> remote;
    try {
      remote = await _loadRemoteAttempts(userId);
    } catch (error) {
      // The assessment and its drafts are bundled/local-first. A temporary
      // network or Firestore-rules problem must not make the test unavailable.
      _lastSyncError = error;
      return _sorted(local);
    }

    final remoteById = <String, PersonalityAssessmentAttempt>{
      for (final attempt in remote) attempt.id: attempt,
    };
    final byId = <String, PersonalityAssessmentAttempt>{...remoteById};
    final pendingSync = <PersonalityAssessmentAttempt>[];
    for (final localAttempt in local) {
      final remoteAttempt = remoteById[localAttempt.id];
      if (remoteAttempt == null || _localWins(localAttempt, remoteAttempt)) {
        byId[localAttempt.id] = localAttempt;
        pendingSync.add(localAttempt);
      }
    }
    final merged = _sorted(byId.values);
    await _writeLocal(userId, merged);

    for (final attempt in pendingSync) {
      try {
        if (attempt.isCompleted) {
          await _writeRemoteCompletion(attempt);
        } else {
          await _writeRemoteProgress(attempt);
        }
      } catch (error) {
        _lastSyncError ??= error;
      }
    }
    return merged;
  }
//Done review 
  Future<void> saveProgress(PersonalityAssessmentAttempt attempt) async {
    if (attempt.userId.isEmpty || attempt.isCompleted) {
      throw StateError(
        'Only an owned incomplete assessment can save progress.',
      );
    }
    final attempts = await _localAttempts(attempt.userId);
    final existing = attempts
        .where((value) => value.id == attempt.id)
        .firstOrNull;
    if (existing?.isCompleted == true) {
      throw StateError('A completed personality result is immutable.');
    }
    final normalized = attempt.copyWith(updatedAt: _now());
    await _upsertLocal(normalized);
    if (firebaseEnabled) {
      _lastSyncError = null;
      try {
        await _writeRemoteProgress(normalized);
      } catch (error) {
        _lastSyncError = error;
      }
    }
  }

  Future<PersonalityAssessmentAttempt> complete(
    PersonalityAssessmentAttempt attempt,
    PersonalityAssessmentResult result,
  ) async {
    if (attempt.userId.isEmpty || attempt.id != result.attemptId) {
      throw StateError('Invalid personality assessment ownership.');
    }
    final local = await _localAttempts(attempt.userId);
    final existing = local.where((value) => value.id == attempt.id).firstOrNull;
    if (existing?.isCompleted == true) {
      if (existing!.result?.type == result.type) {
        if (firebaseEnabled) {
          _lastSyncError = null;
          try {
            await _writeRemoteCompletion(existing);
          } catch (error) {
            _lastSyncError = error;
          }
        }
        return existing;
      }
      throw StateError('This assessment was already submitted.');
    }
    final completed = attempt.copyWith(
      status: PersonalityAttemptStatus.completed,
      result: result,
      updatedAt: result.submittedAt,
    );

    await _upsertLocal(completed);
    if (firebaseEnabled) {
      _lastSyncError = null;
      try {
        await _writeRemoteCompletion(completed);
      } catch (error) {
        // A locally completed result remains usable and will be reconciled on
        // the next load. Never turn cloud unavailability into a lost result.
        _lastSyncError = error;
      }
    }
    return completed;
  }

  Future<List<PersonalityAssessmentAttempt>> _localAttempts(
    String userId,
  ) async {
    final attempts = <PersonalityAssessmentAttempt>[];
    for (final value in storage.readList(userId, _storageKey)) {
      try {
        final attempt = PersonalityAssessmentAttempt.fromJson(value);
        if (attempt.userId == userId && attempt.id.isNotEmpty) {
          attempts.add(attempt);
        }
      } on Object {
        // Ignore one malformed legacy/cache entry instead of blocking every
        // valid assessment stored for the account.
      }
    }
    return attempts;
  }

  Future<List<PersonalityAssessmentAttempt>> _loadRemoteAttempts(
    String userId,
  ) async {
    final loader = _remoteLoader;
    if (loader != null) return loader(userId);
    final snapshot = await _database
        .collection('users')
        .doc(userId)
        .collection('personality_assessments')
        .get();
    final attempts = <PersonalityAssessmentAttempt>[];
    for (final document in snapshot.docs) {
      try {
        final attempt = PersonalityAssessmentAttempt.fromJson({
          'id': document.id,
          ...document.data(),
        });
        if (attempt.userId == userId && attempt.id.isNotEmpty) {
          attempts.add(attempt);
        }
      } on Object {
        // A malformed legacy document must not block the assessment screen.
      }
    }
    return attempts;
  }

  Future<void> _writeRemoteProgress(
    PersonalityAssessmentAttempt attempt,
  ) async {
    final writer = _remoteProgressWriter;
    if (writer != null) return writer(attempt);
    await _database
        .collection('users')
        .doc(attempt.userId)
        .collection('personality_assessments')
        .doc(attempt.id)
        .set(attempt.toJson(), SetOptions(merge: true));
  }

  Future<void> _writeRemoteCompletion(
    PersonalityAssessmentAttempt attempt,
  ) async {
    final writer = _remoteCompletionWriter;
    if (writer != null) return writer(attempt);
    final result = attempt.result;
    if (!attempt.isCompleted || result == null) {
      throw StateError('A completed assessment result is required.');
    }
    final attemptRef = _database
        .collection('users')
        .doc(attempt.userId)
        .collection('personality_assessments')
        .doc(attempt.id);
    final userRef = _database.collection('users').doc(attempt.userId);
    await _database.runTransaction((transaction) async {
      final snapshot = await transaction.get(attemptRef);
      if (snapshot.exists && snapshot.data()?['status'] == 'completed') {
        final remote = PersonalityAssessmentAttempt.fromJson({
          'id': snapshot.id,
          ...?snapshot.data(),
        });
        if (remote.result?.type != result.type) {
          throw StateError('This assessment was already submitted.');
        }
        return;
      }
      transaction.set(attemptRef, attempt.toJson());
      transaction.set(userRef, {
        'personality_active_attempt_id': attempt.id,
        'personality_active_version': result.testVersion,
        'personality_active_updated_at': FieldValue.serverTimestamp(),
        'updated_at': FieldValue.serverTimestamp(),
      }, SetOptions(merge: true));
    });
  }

  bool _localWins(
    PersonalityAssessmentAttempt local,
    PersonalityAssessmentAttempt remote,
  ) {
    if (local.isCompleted != remote.isCompleted) return local.isCompleted;
    if (local.isCompleted && remote.isCompleted) return false;
    return local.updatedAt.isAfter(remote.updatedAt);
  }

  Future<void> _upsertLocal(PersonalityAssessmentAttempt attempt) async {
    final attempts = await _localAttempts(attempt.userId);
    final next = <PersonalityAssessmentAttempt>[
      attempt,
      for (final value in attempts)
        if (value.id != attempt.id) value,
    ];
    await _writeLocal(attempt.userId, _sorted(next));
  }

  Future<void> _writeLocal(
    String userId,
    Iterable<PersonalityAssessmentAttempt> attempts,
  ) => storage.writeList(
    userId,
    _storageKey,
    attempts.map((attempt) => attempt.toJson()).toList(growable: false),
  );

  List<PersonalityAssessmentAttempt> _sorted(
    Iterable<PersonalityAssessmentAttempt> attempts,
  ) =>
      attempts.toList()
        ..sort((left, right) => right.updatedAt.compareTo(left.updatedAt));
}
