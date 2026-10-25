import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:cloud_functions/cloud_functions.dart';

import '../models/leaderboard.dart';

class LeaderboardRepository {
  LeaderboardRepository({
    required this.firebaseEnabled,
    FirebaseFirestore? firestore,
    FirebaseAuth? auth,
  }) : _firestore = firestore ?? FirebaseFirestore.instance,
       _auth = auth ?? FirebaseAuth.instance;

  final bool firebaseEnabled;
  final FirebaseFirestore _firestore;
  final FirebaseAuth _auth;

  Stream<List<LeaderboardPeriod>> activePeriods() {
    if (!firebaseEnabled) return Stream.value(const []);
    return _firestore
        .collection('leaderboardPeriods')
        .where('status', isEqualTo: 'active')
        .orderBy('ends_at', descending: true)
        .limit(40)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) => LeaderboardPeriod.fromMap(doc.id, doc.data()))
              .toList(growable: false),
        );
  }

  Stream<List<LeaderboardEntry>> entries(String periodId, {int limit = 100}) {
    if (!firebaseEnabled || periodId.isEmpty) return Stream.value(const []);
    return _firestore
        .collection('leaderboardPeriods')
        .doc(periodId)
        .collection('entries')
        .orderBy('rank')
        .limit(limit)
        .snapshots()
        .map(
          (snapshot) => snapshot.docs
              .map((doc) => LeaderboardEntry.fromMap(doc.id, doc.data()))
              .toList(growable: false),
        );
  }

  Stream<LeaderboardPrivacy> privacy(String userId) {
    if (!firebaseEnabled) return Stream.value(const LeaderboardPrivacy());
    return _firestore
        .collection('users')
        .doc(userId)
        .collection('preferences')
        .doc('leaderboard')
        .snapshots()
        .map((document) => LeaderboardPrivacy.fromMap(document.data()));
  }

  Future<void> updatePrivacy(String userId, LeaderboardPrivacy privacy) async {
    if (!firebaseEnabled || _auth.currentUser?.uid != userId) {
      throw StateError('You can update only your own leaderboard privacy.');
    }
    await _firestore
        .collection('users')
        .doc(userId)
        .collection('preferences')
        .doc('leaderboard')
        .set({
          ...privacy.toMap(),
          'updated_at': FieldValue.serverTimestamp(),
        }, SetOptions(merge: true));
  }

  Future<void> recalculatePeriod(String periodId) async {
    if (!firebaseEnabled) throw StateError('Firebase is required.');
    await FirebaseFunctions.instanceFor(
      region: 'us-central1',
    ).httpsCallable('calculateLeaderboardPeriod').call({'period_id': periodId});
  }
}
