import 'package:cloud_firestore/cloud_firestore.dart';

import '../models/personality_assessment.dart';
import '../models/personality_question_configuration.dart';

class PersonalityQuestionAdminRepository {
  PersonalityQuestionAdminRepository({FirebaseFirestore? firestore})
    : _firestore = firestore;

  final FirebaseFirestore? _firestore;

  FirebaseFirestore get _database => _firestore ?? FirebaseFirestore.instance;

  CollectionReference<Map<String, dynamic>> get _sets =>
      _database.collection('personality_question_sets');

  Stream<List<PersonalityQuestionSetConfiguration>> watchSets() => _sets
      .orderBy('created_at', descending: true)
      .snapshots()
      .map(
        (snapshot) => snapshot.docs
            .map(
              (document) => PersonalityQuestionSetConfiguration.fromJson(
                document.id,
                document.data(),
              ),
            )
            .toList(),
      );

  Stream<List<PersonalityQuestionConfiguration>> watchQuestions(
    String version,
  ) => _sets
      .doc(version)
      .collection('questions')
      .orderBy('display_order')
      .snapshots()
      .map(
        (snapshot) => snapshot.docs
            .map(
              (document) => PersonalityQuestionConfiguration.fromJson(
                document.id,
                document.data(),
              ),
            )
            .toList(),
      );

  Future<void> createVersion({
    required String version,
    required String title,
    required String actorUid,
    required Map<String, String> chineseTranslations,
    bool seedBuiltInQuestions = true,
  }) async {
    final normalized = _normalizedVersion(version);
    final reference = _sets.doc(normalized);
    if ((await reference.get()).exists) {
      throw StateError('That test version already exists.');
    }
    final batch = _database.batch();
    final now = FieldValue.serverTimestamp();
    batch.set(reference, {
      'version': normalized,
      'title': title.trim(),
      'status': 'draft',
      'schema_version': PersonalityQuestionBank.schemaVersion,
      'scoring_version': PersonalityQuestionBank.scoringVersion,
      'guidance_version': PersonalityQuestionBank.guidanceVersion,
      'answer_scale_version': 'likert-7-v1',
      'question_count': seedBuiltInQuestions
          ? PersonalityQuestionBank.questions.length
          : 0,
      'created_at': now,
      'created_by': actorUid,
      'updated_at': now,
      'updated_by': actorUid,
    });
    if (seedBuiltInQuestions) {
      for (final question in PersonalityQuestionBank.questions) {
        final configured = PersonalityQuestionConfiguration.fromBuiltIn(
          question,
          textZh: chineseTranslations[question.textKey] ?? question.textKey,
        );
        batch.set(reference.collection('questions').doc(question.id), {
          ...configured.toJson(),
          'test_version': normalized,
        });
      }
    }
    await batch.commit();
  }

  Future<void> saveQuestion({
    required String version,
    required PersonalityQuestionConfiguration question,
    required String actorUid,
    bool isNew = false,
  }) async {
    final setReference = _sets.doc(version);
    final set = await setReference.get();
    if (!set.exists || set.data()?['status'] == 'published') {
      throw StateError('Published or missing versions cannot be edited.');
    }
    if (question.testVersion != version) {
      throw ArgumentError('Question version must match its question set.');
    }
    final questionReference = setReference
        .collection('questions')
        .doc(question.id);
    final batch = _database.batch();
    batch.set(questionReference, question.toJson());
    batch.update(setReference, {
      'updated_at': FieldValue.serverTimestamp(),
      'updated_by': actorUid,
      if (isNew) 'question_count': FieldValue.increment(1),
    });
    await batch.commit();
  }

  Future<void> deleteQuestion({
    required String version,
    required String questionId,
    required String actorUid,
  }) async {
    final setReference = _sets.doc(version);
    final set = await setReference.get();
    if (!set.exists || set.data()?['status'] == 'published') {
      throw StateError('Published or missing versions cannot be edited.');
    }
    final batch = _database.batch();
    batch.delete(setReference.collection('questions').doc(questionId));
    batch.update(setReference, {
      'updated_at': FieldValue.serverTimestamp(),
      'updated_by': actorUid,
      'question_count': FieldValue.increment(-1),
    });
    await batch.commit();
  }

  Future<void> publishVersion({
    required String version,
    required String actorUid,
  }) async {
    final setReference = _sets.doc(version);
    final questions = await setReference.collection('questions').get();
    final configured = questions.docs
        .map(
          (document) => PersonalityQuestionConfiguration.fromJson(
            document.id,
            document.data(),
          ),
        )
        .toList();
    final errors = PersonalityQuestionSetValidator.validate(
      version,
      configured,
    );
    if (errors.isNotEmpty) throw StateError(errors.join('\n'));
    await _database.runTransaction((transaction) async {
      final current = await transaction.get(setReference);
      if (!current.exists || current.data()?['status'] == 'published') {
        throw StateError('Only an existing draft version can be published.');
      }
      transaction.update(setReference, {
        'status': 'published',
        'question_count': configured.length,
        'published_at': FieldValue.serverTimestamp(),
        'published_by': actorUid,
        'updated_at': FieldValue.serverTimestamp(),
        'updated_by': actorUid,
      });
    });
  }

  String _normalizedVersion(String value) {
    final normalized = value.trim().toLowerCase();
    if (!RegExp(r'^[a-z0-9][a-z0-9._-]{2,63}$').hasMatch(normalized)) {
      throw ArgumentError(
        'Use 3–64 lowercase letters, numbers, dots, hyphens, or underscores.',
      );
    }
    return normalized;
  }
}
