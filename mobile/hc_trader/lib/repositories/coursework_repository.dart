import 'dart:async';

import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:crypto/crypto.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_storage/firebase_storage.dart';
import 'package:flutter/foundation.dart';

import '../core/config/firestore_collections.dart';
import '../core/storage/local_app_storage.dart';
import '../models/coursework_models.dart';
import '../models/firestore_helpers.dart';
import '../services/firebase_callable_service.dart';

class CourseworkRepositoryException implements Exception {
  const CourseworkRepositoryException(this.message, {this.code, this.cause});
  final String message;
  final String? code;
  final Object? cause;
  @override
  String toString() => message;
}

class CourseworkUploadController {
  const CourseworkUploadController({
    required this.progress,
    required this.result,
    required this.cancel,
  });

  final Stream<double> progress;
  final Future<CourseworkAttachment> result;
  final Future<void> Function() cancel;
}

class CourseworkDashboardData {
  const CourseworkDashboardData({
    required this.assignments,
    required this.attempts,
    required this.legacyAttemptCount,
  });
  final List<CourseworkAssignment> assignments;
  final List<CourseworkAttempt> attempts;
  final int legacyAttemptCount;

  CourseworkAssignment? get currentAssignment => assignments
      .where((item) => item.status != CourseworkStatus.cancelled)
      .firstOrNull;

  CourseworkAttempt? attemptFor(String assignmentId) =>
      attempts.where((item) => item.assignmentId == assignmentId).firstOrNull;
}

class CourseworkAdminMetrics {
  const CourseworkAdminMetrics({
    required this.totalThemes,
    required this.publishedThemes,
    required this.draftThemes,
    required this.assigned,
    required this.inProgress,
    required this.awaitingReview,
    required this.revisionRequested,
    required this.graded,
    required this.overdue,
    required this.averageScore,
  });

  final int totalThemes;
  final int publishedThemes;
  final int draftThemes;
  final int assigned;
  final int inProgress;
  final int awaitingReview;
  final int revisionRequested;
  final int graded;
  final int overdue;
  final double averageScore;
}

class CourseworkRepository {
  CourseworkRepository({
    required this.firebaseEnabled,
    this.localStorage,
    FirebaseFirestore? firestore,
    FirebaseAuth? auth,
    FirebaseStorage? storage,
    FirebaseCallableService? callableService,
  }) : _firestore = firestore,
       _auth = auth,
       _storage = storage,
       _callable = callableService ?? FirebaseCallableService();

  final bool firebaseEnabled;
  final LocalAppStorage? localStorage;
  final FirebaseFirestore? _firestore;
  final FirebaseAuth? _auth;
  final FirebaseStorage? _storage;
  final FirebaseCallableService _callable;

  FirebaseFirestore get firestore => _firestore ?? FirebaseFirestore.instance;
  FirebaseAuth get auth => _auth ?? FirebaseAuth.instance;
  FirebaseStorage get storage => _storage ?? FirebaseStorage.instance;

  Stream<CourseworkDashboardData> dashboardFor(String userId) {
    if (!firebaseEnabled) {
      return Stream.value(
        const CourseworkDashboardData(
          assignments: [],
          attempts: [],
          legacyAttemptCount: 0,
        ),
      );
    }
    late StreamController<CourseworkDashboardData> controller;
    StreamSubscription<QuerySnapshot<Map<String, dynamic>>>? assignmentSub;
    StreamSubscription<QuerySnapshot<Map<String, dynamic>>>? attemptSub;
    StreamSubscription<QuerySnapshot<Map<String, dynamic>>>? legacySub;
    var assignments = <CourseworkAssignment>[];
    var attempts = <CourseworkAttempt>[];
    var legacyCount = 0;
    var readyAssignments = false;
    var readyAttempts = false;
    var readyLegacy = false;

    void emit() {
      if (!readyAssignments ||
          !readyAttempts ||
          !readyLegacy ||
          controller.isClosed) {
        return;
      }
      controller.add(
        CourseworkDashboardData(
          assignments: assignments,
          attempts: attempts,
          legacyAttemptCount: legacyCount,
        ),
      );
    }

    controller = StreamController<CourseworkDashboardData>(
      onListen: () {
        assignmentSub = firestore
            .collection(FirestoreCollections.courseworkAssignments)
            .where('assigned_to_user_id', isEqualTo: userId)
            .snapshots()
            .listen((snapshot) {
              assignments =
                  snapshot.docs.map(CourseworkAssignment.fromFirestore).toList()
                    ..sort(
                      (left, right) => (right.assignedAt ?? DateTime(1970))
                          .compareTo(left.assignedAt ?? DateTime(1970)),
                    );
              readyAssignments = true;
              emit();
            }, onError: controller.addError);
        attemptSub = firestore
            .collection(FirestoreCollections.courseworkAttempts)
            .where('user_id', isEqualTo: userId)
            .snapshots()
            .listen((snapshot) {
              attempts =
                  snapshot.docs.map(CourseworkAttempt.fromFirestore).toList()
                    ..sort(
                      (left, right) => (right.startedAt ?? DateTime(1970))
                          .compareTo(left.startedAt ?? DateTime(1970)),
                    );
              readyAttempts = true;
              emit();
            }, onError: controller.addError);
        legacySub = firestore
            .collection(FirestoreCollections.users)
            .doc(userId)
            .collection('trading_exam_attempts')
            .snapshots()
            .listen((snapshot) {
              legacyCount = snapshot.size;
              readyLegacy = true;
              emit();
            }, onError: controller.addError);
      },
      onCancel: () async {
        await assignmentSub?.cancel();
        await attemptSub?.cancel();
        await legacySub?.cancel();
      },
    );
    return controller.stream;
  }

  Stream<CourseworkAttempt> watchAttempt(String attemptId) {
    if (!firebaseEnabled) {
      return Stream.error(
        const CourseworkRepositoryException('Coursework requires Firebase.'),
      );
    }
    late StreamController<CourseworkAttempt> controller;
    StreamSubscription<DocumentSnapshot<Map<String, dynamic>>>? attemptSub;
    StreamSubscription<QuerySnapshot<Map<String, dynamic>>>? answerSub;
    CourseworkAttempt? attempt;
    var answers = <String, CourseworkAnswer>{};

    void emit() {
      final current = attempt;
      if (current == null || controller.isClosed) return;
      controller.add(
        CourseworkAttempt(
          id: current.id,
          assignmentId: current.assignmentId,
          themeId: current.themeId,
          themeVersion: current.themeVersion,
          userId: current.userId,
          status: current.status,
          themeSnapshot: current.themeSnapshot,
          answers: answers,
          startedAt: current.startedAt,
          lastSavedAt: current.lastSavedAt,
          submittedAt: current.submittedAt,
          resubmittedAt: current.resubmittedAt,
          gradedAt: current.gradedAt,
          rawScore: current.rawScore,
          weightedScore: current.weightedScore,
          automaticScore: current.automaticScore,
          manualScore: current.manualScore,
          revisionNumber: current.revisionNumber,
          reviewerId: current.reviewerId,
          gradingStatus: current.gradingStatus,
          declarationAccepted: current.declarationAccepted,
          reviewerFeedback: current.reviewerFeedback,
          calculationVersion: current.calculationVersion,
        ),
      );
    }

    controller = StreamController<CourseworkAttempt>(
      onListen: () {
        final ref = firestore
            .collection(FirestoreCollections.courseworkAttempts)
            .doc(attemptId);
        attemptSub = ref.snapshots().listen((snapshot) {
          if (!snapshot.exists) {
            controller.addError(
              const CourseworkRepositoryException(
                'Coursework attempt not found.',
              ),
            );
            return;
          }
          attempt = CourseworkAttempt.fromFirestore(snapshot);
          emit();
        }, onError: controller.addError);
        answerSub = ref.collection('answers').snapshots().listen((snapshot) {
          answers = {
            for (final doc in snapshot.docs)
              doc.id: CourseworkAnswer.fromMap({
                ...doc.data(),
                'question_id': doc.id,
              }),
          };
          emit();
        }, onError: controller.addError);
      },
      onCancel: () async {
        await attemptSub?.cancel();
        await answerSub?.cancel();
      },
    );
    return controller.stream;
  }

  Future<String> startAttempt({
    required String userId,
    required String assignmentId,
  }) async {
    _verifyUser(userId);
    try {
      final response = await _callable.call('startCourseworkAttempt', {
        'assignmentId': assignmentId,
        'timezoneOffsetMinutes': DateTime.now().timeZoneOffset.inMinutes,
      });
      final id = '${response['attemptId'] ?? ''}';
      if (id.isEmpty) {
        throw const CourseworkRepositoryException('Attempt was not created.');
      }
      return id;
    } on FirebaseCallableFailure catch (error) {
      throw CourseworkRepositoryException(
        error.message,
        code: error.code,
        cause: error,
      );
    }
  }

  String _draftKey(String attemptId, String questionId) =>
      'coursework_${attemptId}_$questionId';

  CourseworkAnswer? restoreLocalDraft({
    required String userId,
    required String attemptId,
    required String questionId,
  }) {
    final raw = localStorage?.readMap(userId, _draftKey(attemptId, questionId));
    return raw == null ? null : CourseworkAnswer.fromMap(raw);
  }

  Future<void> saveDraft({
    required String userId,
    required String attemptId,
    required CourseworkAnswer answer,
  }) async {
    _verifyUser(userId);
    final now = DateTime.now().toUtc();
    final next = answer.copyWith(
      clientVersion: answer.clientVersion + 1,
      savedAt: now,
    );
    await localStorage?.writeMap(
      userId,
      _draftKey(attemptId, answer.questionId),
      next.toMap(),
    );
    if (!firebaseEnabled) return;
    final attemptRef = firestore
        .collection(FirestoreCollections.courseworkAttempts)
        .doc(attemptId);
    final answerRef = attemptRef.collection('answers').doc(answer.questionId);
    await firestore.runTransaction((transaction) async {
      final attemptSnapshot = await transaction.get(attemptRef);
      if (!attemptSnapshot.exists ||
          attemptSnapshot.data()?['user_id'] != userId) {
        throw const CourseworkRepositoryException(
          'This attempt is not available.',
        );
      }
      final status = CourseworkStatus.fromStorage(
        attemptSnapshot.data()?['status'],
      );
      if (!status.canEdit) {
        throw const CourseworkRepositoryException(
          'Submitted answers are locked unless revision is requested.',
        );
      }
      final server = await transaction.get(answerRef);
      final serverVersion = firestoreInt(server.data()?['client_version']);
      // Accept another write at the same client version (for example, two
      // debounced edits made before the stream echoes the first write). A
      // strictly newer server version still signals a genuine stale draft.
      if (server.exists && serverVersion > next.clientVersion) {
        throw const CourseworkRepositoryException(
          'A newer draft exists on another device. Refresh before editing.',
        );
      }
      transaction.set(answerRef, {
        ...next.toMap(),
        'attempt_id': attemptId,
        'saved_at': FieldValue.serverTimestamp(),
        'updated_at': FieldValue.serverTimestamp(),
      });
      transaction.update(attemptRef, {
        'status': CourseworkStatus.draft.name,
        'current_question_number': answer.questionNumber,
        'last_saved_at': FieldValue.serverTimestamp(),
        'updated_at': FieldValue.serverTimestamp(),
      });
    });
  }

  Future<void> clearLocalDrafts({
    required String userId,
    required String attemptId,
    required Iterable<String> questionIds,
  }) async {
    for (final id in questionIds) {
      await localStorage?.remove(userId, _draftKey(attemptId, id));
    }
  }

  CourseworkUploadController uploadEvidence({
    required String userId,
    required String attemptId,
    required String questionId,
    required String fileName,
    required String mimeType,
    required Uint8List bytes,
  }) {
    _verifyUser(userId);
    final normalizedMime = mimeType.trim().toLowerCase();
    if (!const {
      'image/jpeg',
      'image/png',
      'image/webp',
    }.contains(normalizedMime)) {
      throw const CourseworkRepositoryException(
        'Use a JPEG, PNG, or WEBP image.',
      );
    }
    if (bytes.isEmpty || bytes.length > 8 * 1024 * 1024) {
      throw const CourseworkRepositoryException(
        'Images must be smaller than 8 MB.',
      );
    }
    final checksum = sha256.convert(bytes).toString();
    final extension = switch (normalizedMime) {
      'image/jpeg' => 'jpg',
      'image/png' => 'png',
      _ => 'webp',
    };
    final attachmentId = '${DateTime.now().microsecondsSinceEpoch}-$checksum'
        .substring(0, 32);
    final safeName = fileName.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');
    final path =
        'coursework_submissions/$userId/$attemptId/$questionId/'
        '${attachmentId}_${safeName.isEmpty ? 'evidence.$extension' : safeName}';
    final reference = storage.ref(path);
    final task = reference.putData(
      bytes,
      SettableMetadata(
        contentType: normalizedMime,
        customMetadata: {
          'ownerUid': userId,
          'attemptId': attemptId,
          'questionId': questionId,
          'checksum': checksum,
          'source': 'coursework',
        },
      ),
    );
    final progress = task.snapshotEvents.map(
      (snapshot) => snapshot.totalBytes == 0
          ? 0.0
          : snapshot.bytesTransferred / snapshot.totalBytes,
    );
    final result = task.then((_) async {
      final attachment = CourseworkAttachment(
        id: attachmentId,
        ownerUserId: userId,
        attemptId: attemptId,
        questionId: questionId,
        storagePath: path,
        fileName: fileName,
        mimeType: normalizedMime,
        sizeBytes: bytes.length,
        checksum: checksum,
        uploadedAt: DateTime.now().toUtc(),
      );
      await firestore
          .collection(FirestoreCollections.courseworkAttempts)
          .doc(attemptId)
          .collection('attachments')
          .doc(attachmentId)
          .set({
            ...attachment.toMap(),
            'uploaded_at': FieldValue.serverTimestamp(),
          });
      return attachment;
    });
    return CourseworkUploadController(
      progress: progress,
      result: result,
      cancel: task.cancel,
    );
  }

  Future<void> submitAttempt({
    required String userId,
    required String attemptId,
    required bool declarationAccepted,
  }) async {
    _verifyUser(userId);
    if (!declarationAccepted) {
      throw const CourseworkRepositoryException(
        'Accept the academic declaration.',
      );
    }
    try {
      await _callable.call('submitCourseworkAttempt', {
        'attemptId': attemptId,
        'declarationAccepted': declarationAccepted,
      });
    } on FirebaseCallableFailure catch (error) {
      throw CourseworkRepositoryException(error.message);
    }
  }

  Stream<AssessmentSettings> assessmentSettings() {
    if (!firebaseEnabled) return Stream.value(const AssessmentSettings());
    return firestore
        .collection('assessment_settings')
        .doc('current')
        .snapshots()
        .map(
          (snapshot) => snapshot.exists
              ? AssessmentSettings.fromMap(snapshot.data()!)
              : const AssessmentSettings(),
        );
  }

  Future<double?> latestFinalExamScore(String userId) async {
    if (!firebaseEnabled) return null;
    final snapshot = await firestore
        .collection(FirestoreCollections.users)
        .doc(userId)
        .collection('final_exam_attempts')
        .get();
    final attempts =
        snapshot.docs.where((item) {
          final status = '${item.data()['status'] ?? ''}'.toLowerCase();
          return status == 'completed' || status == 'passed';
        }).toList()..sort(
          (left, right) =>
              (firestoreDate(right.data()['completed_at']) ?? DateTime(1970))
                  .compareTo(
                    firestoreDate(left.data()['completed_at']) ??
                        DateTime(1970),
                  ),
        );
    if (attempts.isEmpty) return null;
    final data = attempts.first.data();
    final storedPercentage = data['score_percentage'];
    if (storedPercentage != null) return _finite(storedPercentage);
    final total = firestoreInt(data['total_questions']);
    if (total <= 0) return null;
    return firestoreInt(data['correct_answers']) / total * 100;
  }

  /// Single authoritative theme stream for every administrative surface.
  ///
  /// The root query intentionally has no compound predicates or ordering. It
  /// therefore includes legacy documents with missing fields and cannot fail
  /// because a composite index is absent. Canonical filtering is performed by
  /// [CourseworkThemeFilter] after tolerant deserialization.
  Stream<List<CourseworkTheme>> watchThemes({
    String search = '',
    String? categoryId,
    Object? difficulty,
    Object? publicationStatus,
    bool includeInactive = true,
    bool assignableOnly = false,
  }) {
    if (!firebaseEnabled) return Stream.value(const []);
    _debugLog(
      'watchThemes uid=${auth.currentUser?.uid ?? 'signed-out'} '
      'path=${FirestoreCollections.courseworkThemes} category=$categoryId '
      'difficulty=$difficulty status=$publicationStatus',
    );
    return firestore
        .collection(FirestoreCollections.courseworkThemes)
        .snapshots()
        .asyncMap((snapshot) async {
          _debugLog('watchThemes received=${snapshot.docs.length}');
          final parsed = await Future.wait(
            snapshot.docs.map(_loadThemeDocument),
          );
          final themes = parsed.whereType<CourseworkTheme>().toList();
          themes.sort(_compareThemes);
          final filtered = CourseworkThemeFilter.apply(
            themes,
            search: search,
            categoryId: categoryId,
            difficulty: difficulty,
            publicationStatus: publicationStatus,
            includeInactive: includeInactive,
            assignableOnly: assignableOnly,
          );
          _debugLog('watchThemes filtered=${filtered.length}');
          return filtered;
        })
        .handleError((Object error, StackTrace stackTrace) {
          _debugError('watchThemes', error, stackTrace);
          throw _repositoryError(error);
        });
  }

  Stream<List<CourseworkTheme>> adminThemes() => watchThemes();

  Stream<List<CourseworkTheme>> watchAssignableThemes() => watchThemes(
    publicationStatus: CourseworkPublicationStatus.published,
    includeInactive: false,
    assignableOnly: true,
  );

  Future<CourseworkTheme?> _loadThemeDocument(
    QueryDocumentSnapshot<Map<String, dynamic>> doc,
  ) async {
    try {
      final questionSnapshot = await doc.reference
          .collection('questions')
          .get();
      final questions = questionSnapshot.docs
          .map((item) => {...item.data(), 'id': item.id})
          .toList(growable: false);
      questions.sort(
        (left, right) =>
            firestoreInt(
              left['order'] ?? left['question_number'] ?? left['number'],
            ).compareTo(
              firestoreInt(
                right['order'] ?? right['question_number'] ?? right['number'],
              ),
            ),
      );
      return CourseworkTheme.fromMap({
        ...doc.data(),
        'id': doc.id,
        'questions': questions,
      });
    } on FirebaseException {
      rethrow;
    } catch (error, stackTrace) {
      _debugError('theme document ${doc.id}', error, stackTrace);
      return null;
    }
  }

  int _compareThemes(CourseworkTheme left, CourseworkTheme right) {
    final leftUpdated = left.updatedAt ?? left.createdAt;
    final rightUpdated = right.updatedAt ?? right.createdAt;
    if (leftUpdated != null || rightUpdated != null) {
      final byUpdated = (rightUpdated ?? DateTime(1970)).compareTo(
        leftUpdated ?? DateTime(1970),
      );
      if (byUpdated != 0) return byUpdated;
    }
    final byOrder = left.sortOrder.compareTo(right.sortOrder);
    if (byOrder != 0) return byOrder;
    final byCode = left.code.compareTo(right.code);
    return byCode == 0 ? left.title.compareTo(right.title) : byCode;
  }

  /// Published catalogue used by candidate-facing discovery surfaces.
  ///
  /// Keeping the status and active predicates in the query is important:
  /// Firestore rules evaluate queries against their potential result set.
  Stream<List<CourseworkTheme>> watchPublishedThemes({
    String? categoryId,
    String? difficulty,
  }) {
    if (!firebaseEnabled) return Stream.value(const []);
    Query<Map<String, dynamic>> query = firestore
        .collection(FirestoreCollections.courseworkThemes)
        .where('status', isEqualTo: 'published')
        .where('isActive', isEqualTo: true);
    if (categoryId?.trim().isNotEmpty == true) {
      query = query.where('categoryId', isEqualTo: categoryId!.trim());
    }
    if (difficulty?.trim().isNotEmpty == true) {
      query = query.where('difficulty', isEqualTo: difficulty!.trim());
    }
    return query.orderBy('sortOrder').snapshots().asyncMap((snapshot) async {
      final themes = await Future.wait(
        snapshot.docs.map((doc) async {
          final questions = await doc.reference
              .collection('questions')
              .orderBy('order')
              .get();
          return CourseworkTheme.fromMap({
            ...doc.data(),
            'id': doc.id,
            'questions': questions.docs
                .map((item) => {...item.data(), 'id': item.id})
                .toList(growable: false),
          });
        }),
      );
      return themes;
    });
  }

  Stream<CourseworkTheme> watchTheme(String themeId) {
    if (!firebaseEnabled) {
      return Stream.error(
        const CourseworkRepositoryException('Coursework requires Firebase.'),
      );
    }
    return firestore
        .collection(FirestoreCollections.courseworkThemes)
        .doc(themeId)
        .snapshots()
        .asyncMap((snapshot) async {
          if (!snapshot.exists) {
            throw const CourseworkRepositoryException(
              'Coursework theme not found.',
            );
          }
          final questions = await snapshot.reference
              .collection('questions')
              .orderBy('order')
              .get();
          return CourseworkTheme.fromMap({
            ...?snapshot.data(),
            'id': snapshot.id,
            'questions': questions.docs
                .map((item) => {...item.data(), 'id': item.id})
                .toList(growable: false),
          });
        });
  }

  Stream<List<CourseworkQuestion>> watchThemeQuestions(String themeId) {
    if (!firebaseEnabled) return Stream.value(const []);
    return firestore
        .collection(FirestoreCollections.courseworkThemes)
        .doc(themeId)
        .collection('questions')
        .orderBy('order')
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map(CourseworkQuestion.fromFirestore)
              .toList(growable: false),
        );
  }

  Future<List<CourseworkTheme>> searchThemes({
    required String query,
    String? categoryId,
    String? difficulty,
  }) async {
    if (!firebaseEnabled) return const [];
    Query<Map<String, dynamic>> reference = firestore
        .collection(FirestoreCollections.courseworkThemes)
        .where('status', isEqualTo: 'published')
        .where('isActive', isEqualTo: true);
    if (categoryId?.trim().isNotEmpty == true) {
      reference = reference.where('categoryId', isEqualTo: categoryId!.trim());
    }
    if (difficulty?.trim().isNotEmpty == true) {
      reference = reference.where('difficulty', isEqualTo: difficulty!.trim());
    }
    final snapshot = await reference.orderBy('sortOrder').get();
    final searchForms = _courseworkSearchForms(query);
    return snapshot.docs
        .map((doc) => CourseworkTheme.fromMap({...doc.data(), 'id': doc.id}))
        .where((theme) {
          if (searchForms.isEmpty) return true;
          final haystack = _courseworkSearchForms(
            [
              theme.code,
              theme.title,
              ...theme.titleI18n.values,
              theme.shortDescription,
              ...theme.shortDescriptionI18n.values,
              theme.categoryId,
              theme.category,
              ...theme.categoryI18n.values,
              ...theme.learningObjectives,
              ...theme.learningObjectivesZh,
              ...theme.keywords,
              ...theme.searchTokens,
            ].join(' '),
          );
          return searchForms.every(
            (needle) => haystack.any((value) => value.contains(needle)),
          );
        })
        .toList(growable: false);
  }

  Stream<Map<String, int>> adminAssignmentCounts() => firestore
      .collection(FirestoreCollections.courseworkAssignments)
      .snapshots()
      .map((snapshot) {
        final counts = <String, int>{};
        for (final doc in snapshot.docs) {
          final themeId = CourseworkAssignment.fromMap({
            ...doc.data(),
            'id': doc.id,
          }).themeId;
          if (themeId.isNotEmpty) {
            counts.update(themeId, (value) => value + 1, ifAbsent: () => 1);
          }
        }
        return counts;
      });

  Stream<List<CourseworkAssignment>> adminAssignments() => firestore
      .collection(FirestoreCollections.courseworkAssignments)
      .snapshots()
      .map((snapshot) {
        final assignments = <CourseworkAssignment>[];
        for (final doc in snapshot.docs) {
          try {
            assignments.add(CourseworkAssignment.fromFirestore(doc));
          } catch (error, stackTrace) {
            _debugError('assignment document ${doc.id}', error, stackTrace);
          }
        }
        assignments.sort(
          (left, right) => (right.assignedAt ?? DateTime(1970)).compareTo(
            left.assignedAt ?? DateTime(1970),
          ),
        );
        return assignments;
      })
      .handleError((Object error, StackTrace stackTrace) {
        _debugError('adminAssignments', error, stackTrace);
        throw _repositoryError(error);
      });

  Stream<List<CourseworkAttempt>> adminReviewQueue() => firestore
      .collection(FirestoreCollections.courseworkAttempts)
      .snapshots()
      .map(
        (snapshot) => snapshot.docs
            .map(CourseworkAttempt.fromFirestore)
            .where(
              (item) => const {
                CourseworkStatus.submitted,
                CourseworkStatus.underReview,
                CourseworkStatus.resubmitted,
                CourseworkStatus.revisionRequested,
                CourseworkStatus.graded,
              }.contains(item.status),
            )
            .toList(growable: false),
      );

  Stream<List<Map<String, dynamic>>> adminAssignableUsers() => firestore
      .collection(FirestoreCollections.users)
      .limit(250)
      .snapshots()
      .map(
        (snapshot) => snapshot.docs
            .map((doc) => {...doc.data(), 'id': doc.id})
            .where((item) {
              final status = '${item['account_status'] ?? 'active'}'
                  .toLowerCase();
              return !const {
                'deleted',
                'disabled',
                'suspended',
              }.contains(status);
            })
            .toList(growable: false),
      );

  Future<CourseworkAdminMetrics> adminMetrics() async {
    final results = await Future.wait([
      firestore.collection(FirestoreCollections.courseworkThemes).get(),
      firestore.collection(FirestoreCollections.courseworkAssignments).get(),
      firestore.collection(FirestoreCollections.courseworkAttempts).get(),
    ]);
    final themes = results[0].docs;
    final assignments = results[1].docs;
    final attempts = results[2].docs
        .map(CourseworkAttempt.fromFirestore)
        .toList();
    final scores = attempts
        .map((item) => item.rawScore)
        .whereType<double>()
        .toList();
    final parsedThemes = themes
        .map((item) => CourseworkTheme.fromMap({...item.data(), 'id': item.id}))
        .toList(growable: false);
    return CourseworkAdminMetrics(
      totalThemes: themes.length,
      publishedThemes: parsedThemes
          .where(
            (item) =>
                item.publicationStatus == CourseworkPublicationStatus.published,
          )
          .length,
      draftThemes: parsedThemes
          .where(
            (item) =>
                item.publicationStatus == CourseworkPublicationStatus.draft,
          )
          .length,
      assigned: assignments
          .where((item) => item.data()['status'] == 'assigned')
          .length,
      inProgress: attempts.where((item) => item.status.canEdit).length,
      awaitingReview: attempts
          .where(
            (item) => const {
              CourseworkStatus.submitted,
              CourseworkStatus.underReview,
              CourseworkStatus.resubmitted,
            }.contains(item.status),
          )
          .length,
      revisionRequested: attempts
          .where((item) => item.status == CourseworkStatus.revisionRequested)
          .length,
      graded: attempts
          .where(
            (item) =>
                item.status == CourseworkStatus.graded ||
                item.status == CourseworkStatus.completed,
          )
          .length,
      overdue: assignments.where((item) {
        final due = firestoreDate(item.data()['due_at']);
        return due != null &&
            due.isBefore(DateTime.now()) &&
            !const {'completed', 'cancelled'}.contains(item.data()['status']);
      }).length,
      averageScore: scores.isEmpty
          ? 0
          : scores.reduce((a, b) => a + b) / scores.length,
    );
  }

  Future<void> saveTheme(CourseworkTheme theme, {required bool publish}) async {
    final errors = CourseworkThemeValidator.validate(
      theme,
      forPublishing: publish,
    );
    if (errors.isNotEmpty) {
      throw CourseworkRepositoryException(errors.join('\n'));
    }
    final reference = firestore
        .collection(FirestoreCollections.courseworkThemes)
        .doc(theme.id);
    final batch = firestore.batch();
    final actorId = auth.currentUser?.uid ?? '';
    batch.set(reference, {
      ...theme.toMap(includeQuestions: false),
      'status': publish ? 'published' : theme.status,
      if (publish) 'publishedAt': FieldValue.serverTimestamp(),
      'updatedAt': FieldValue.serverTimestamp(),
      if (actorId.isNotEmpty) 'updatedBy': actorId,
    }, SetOptions(merge: true));
    for (final question in theme.questions) {
      batch.set(
        reference.collection('questions').doc(question.id),
        {
          ...question.toMap(includeAnswers: false),
          'updatedAt': FieldValue.serverTimestamp(),
          if (actorId.isNotEmpty) 'updatedBy': actorId,
        },
        SetOptions(merge: true),
      );
    }
    await batch.commit();
  }

  Future<void> createTheme(CourseworkTheme theme, {bool publish = false}) =>
      saveTheme(theme, publish: publish);

  Future<void> updateTheme(CourseworkTheme theme, {bool publish = false}) =>
      saveTheme(theme, publish: publish);

  Future<void> archiveTheme(String themeId) async {
    if (themeId.trim().isEmpty) return;
    final actorId = auth.currentUser?.uid ?? '';
    await firestore
        .collection(FirestoreCollections.courseworkThemes)
        .doc(themeId)
        .update({
          'status': 'archived',
          'isActive': false,
          'isAssignable': false,
          'updatedAt': FieldValue.serverTimestamp(),
          if (actorId.isNotEmpty) 'updatedBy': actorId,
        });
  }

  Future<void> assignTheme({
    required String themeId,
    required List<String> userIds,
    required DateTime dueAt,
    String instructions = '',
    bool allowRevision = true,
  }) async {
    try {
      await _callable.call('assignCourseworkTheme', {
        'themeId': themeId,
        'userIds': userIds,
        'dueAt': dueAt.toUtc().toIso8601String(),
        'customInstructions': instructions,
        'allowRevision': allowRevision,
      });
      _debugLog(
        'assignTheme themeId=$themeId candidateCount=${userIds.length} success',
      );
    } on FirebaseCallableFailure catch (error) {
      throw CourseworkRepositoryException(
        error.message,
        code: error.code,
        cause: error,
      );
    }
  }

  Future<void> updateAssignment({
    required String assignmentId,
    required String themeId,
    required DateTime dueAt,
    String instructions = '',
    bool allowRevision = true,
  }) async {
    try {
      await _callable.call('updateCourseworkAssignment', {
        'assignmentId': assignmentId,
        'themeId': themeId,
        'dueAt': dueAt.toUtc().toIso8601String(),
        'customInstructions': instructions,
        'allowRevision': allowRevision,
      });
      _debugLog(
        'updateAssignment assignmentId=$assignmentId themeId=$themeId success',
      );
    } on FirebaseCallableFailure catch (error) {
      throw CourseworkRepositoryException(
        error.message,
        code: error.code,
        cause: error,
      );
    }
  }

  Future<void> gradeAttempt({
    required String attemptId,
    required Map<String, Map<String, double>> rubricMarks,
    required Map<String, String> feedback,
    required String overallFeedback,
    bool requestRevision = false,
    String reason = '',
  }) async {
    try {
      await _callable.call('gradeCourseworkAttempt', {
        'attemptId': attemptId,
        'rubricMarks': rubricMarks,
        'feedback': feedback,
        'overallFeedback': overallFeedback,
        'requestRevision': requestRevision,
        'reason': reason,
      });
    } on FirebaseCallableFailure catch (error) {
      throw CourseworkRepositoryException(error.message);
    }
  }

  void _verifyUser(String userId) {
    if (!firebaseEnabled || auth.currentUser?.uid != userId) {
      throw const CourseworkRepositoryException(
        'Sign in again before changing Coursework.',
      );
    }
  }

  CourseworkRepositoryException _repositoryError(Object error) {
    if (error is CourseworkRepositoryException) return error;
    if (error is FirebaseException) {
      return CourseworkRepositoryException(
        error.message ?? 'Firebase request failed.',
        code: error.code,
        cause: error,
      );
    }
    return CourseworkRepositoryException('$error', cause: error);
  }

  void _debugLog(String message) {
    if (kDebugMode) debugPrint('[CourseworkRepository] $message');
  }

  void _debugError(String operation, Object error, StackTrace stackTrace) {
    if (!kDebugMode) return;
    final code = error is FirebaseException ? error.code : 'unknown';
    debugPrint(
      '[CourseworkRepository] operation=$operation code=$code error=$error',
    );
    debugPrintStack(stackTrace: stackTrace);
  }
}

double _finite(Object? value) {
  final parsed = value is num ? value.toDouble() : double.tryParse('$value');
  return parsed?.isFinite == true ? parsed! : 0;
}

Set<String> _courseworkSearchForms(String value) {
  final normalized = value
      .toLowerCase()
      .trim()
      .replaceAll(RegExp(r'[_–—]+'), ' ')
      .replaceAll(RegExp(r'\s+'), ' ');
  if (normalized.isEmpty) return const {};
  final compact = normalized.replaceAll(RegExp(r'[\s-]+'), '');
  return {
    normalized,
    compact,
    ...normalized.split(' ').where((item) => item.isNotEmpty),
  };
}
