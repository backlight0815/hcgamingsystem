import 'dart:typed_data';

import 'package:firebase_storage/firebase_storage.dart';

import '../core/network/api_client.dart' show ApiException;

class DailyQuizUploadResult {
  const DailyQuizUploadResult({
    required this.downloadUrl,
    required this.storagePath,
  });

  final String downloadUrl;
  final String storagePath;
}

class DailyQuizUploadTask {
  DailyQuizUploadTask({required UploadTask task, required Reference reference})
    : _task = task,
      _reference = reference;

  final UploadTask _task;
  final Reference _reference;

  Stream<double> get progress => _task.snapshotEvents.map((snapshot) {
    if (snapshot.totalBytes <= 0) return 0;
    return snapshot.bytesTransferred / snapshot.totalBytes;
  });

  Future<DailyQuizUploadResult> get result async {
    await _task;
    return DailyQuizUploadResult(
      downloadUrl: await _reference.getDownloadURL(),
      storagePath: _reference.fullPath,
    );
  }

  Future<bool> cancel() => _task.cancel();
  Future<void> pause() => _task.pause();
  Future<void> resume() => _task.resume();
}

class DailyQuizUploadService {
  const DailyQuizUploadService();

  static const maximumBytes = 8 * 1024 * 1024;

  DailyQuizUploadTask start({
    required String userId,
    required String quizId,
    required String attemptId,
    required String filename,
    required Uint8List bytes,
  }) {
    if (userId.trim().isEmpty ||
        quizId.trim().isEmpty ||
        attemptId.trim().isEmpty) {
      throw const ApiException('The quiz upload destination is incomplete.');
    }
    if (bytes.isEmpty || bytes.length > maximumBytes) {
      throw const ApiException('Choose an image smaller than 8 MB.');
    }
    final contentType = _contentType(filename);
    if (contentType == null) {
      throw const ApiException('Choose a JPG, PNG, or WEBP image.');
    }
    final safeName = filename.trim().replaceAll(
      RegExp(r'[^A-Za-z0-9._-]'),
      '_',
    );
    final path =
        'quiz_submissions/${_segment(quizId)}/${_segment(userId)}/${_segment(attemptId)}/${DateTime.now().microsecondsSinceEpoch}_$safeName';
    final reference = FirebaseStorage.instance.ref(path);
    final task = reference.putData(
      bytes,
      SettableMetadata(
        contentType: contentType,
        customMetadata: {
          'ownerUid': userId,
          'quizId': quizId,
          'attemptId': attemptId,
          'source': 'daily_quiz',
        },
      ),
    );
    return DailyQuizUploadTask(task: task, reference: reference);
  }

  String _segment(String value) {
    final safe = value.trim().replaceAll(RegExp(r'[^A-Za-z0-9_-]'), '_');
    return safe.length <= 120 ? safe : safe.substring(0, 120);
  }

  String? _contentType(String filename) {
    final extension = filename.split('.').last.toLowerCase();
    return switch (extension) {
      'jpg' || 'jpeg' => 'image/jpeg',
      'png' => 'image/png',
      'webp' => 'image/webp',
      _ => null,
    };
  }
}
