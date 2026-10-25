import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';

import '../models/app_notification.dart';

class NotificationRepository {
  NotificationRepository({
    required this.firebaseEnabled,
    FirebaseFirestore? firestore,
    FirebaseAuth? auth,
  }) : _firestore = firestore ?? FirebaseFirestore.instance,
       _auth = auth ?? FirebaseAuth.instance;

  final bool firebaseEnabled;
  final FirebaseFirestore _firestore;
  final FirebaseAuth _auth;

  Stream<List<AppNotificationRecord>> notifications({
    required String userId,
    AppNotificationCategory? category,
    bool unreadOnly = false,
    int limit = 50,
  }) {
    if (!firebaseEnabled) return Stream.value(const []);
    Query<Map<String, dynamic>> query = _firestore
        .collection('users')
        .doc(userId)
        .collection('notifications')
        .where('archived_at', isNull: true);
    if (category != null) {
      query = query.where('category', isEqualTo: category.storageValue);
    }
    if (unreadOnly) query = query.where('read_at', isNull: true);
    return query
        .orderBy('created_at', descending: true)
        .limit(limit)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) => AppNotificationRecord.fromMap(doc.id, doc.data()))
              .toList(growable: false),
        );
  }

  Stream<int> unreadCount(String userId) {
    if (!firebaseEnabled) return Stream.value(0);
    return _firestore
        .collection('users')
        .doc(userId)
        .collection('notifications')
        .where('read_at', isNull: true)
        .where('archived_at', isNull: true)
        .snapshots()
        .map((snapshot) => snapshot.size);
  }

  Future<void> markRead({
    required String userId,
    required String notificationId,
  }) async {
    _requireOwner(userId);
    await _firestore
        .collection('users')
        .doc(userId)
        .collection('notifications')
        .doc(notificationId)
        .update({'read_at': FieldValue.serverTimestamp()});
  }

  Future<void> markAllRead(String userId) async {
    _requireOwner(userId);
    final snapshot = await _firestore
        .collection('users')
        .doc(userId)
        .collection('notifications')
        .where('read_at', isNull: true)
        .limit(400)
        .get();
    final batch = _firestore.batch();
    for (final document in snapshot.docs) {
      batch.update(document.reference, {
        'read_at': FieldValue.serverTimestamp(),
      });
    }
    await batch.commit();
  }

  Future<void> archive({
    required String userId,
    required String notificationId,
  }) async {
    _requireOwner(userId);
    await _firestore
        .collection('users')
        .doc(userId)
        .collection('notifications')
        .doc(notificationId)
        .update({'archived_at': FieldValue.serverTimestamp()});
  }

  void _requireOwner(String userId) {
    if (!firebaseEnabled || _auth.currentUser?.uid != userId) {
      throw StateError('You can manage only your own notifications.');
    }
  }
}
