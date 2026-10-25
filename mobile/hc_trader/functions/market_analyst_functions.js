"use strict";

const {getFirestore, FieldValue} = require("firebase-admin/firestore");
const {HttpsError, onCall} = require("firebase-functions/v2/https");

const REGION = "us-central1";

exports.toggleMarketAnalystFollow = onCall(
    {region: REGION},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) throw new HttpsError("unauthenticated", "Sign in first.");
      const analystId = text(request.data?.analystId);
      const follow = request.data?.follow === true;
      if (!analystId || analystId === uid) {
        throw new HttpsError("invalid-argument", "Choose another market analyst.");
      }
      const db = getFirestore();
      const analystRef = db.collection("users").doc(analystId);
      const profileRef = db.collection("market_analyst_profiles").doc(analystId);
      const followerRef = db.collection("market_analysts").doc(analystId)
          .collection("followers").doc(uid);
      const now = FieldValue.serverTimestamp();
      let following = false;
      await db.runTransaction(async (transaction) => {
        const [analystSnapshot, profileSnapshot, followerSnapshot] =
          await Promise.all([
            transaction.get(analystRef),
            transaction.get(profileRef),
            transaction.get(followerRef),
          ]);
        if (!analystSnapshot.exists ||
            !effectiveRoles(analystSnapshot.data()).has(501)) {
          throw new HttpsError("not-found", "Market analyst not found.");
        }
        const currentCount = Number(profileSnapshot.data()?.follower_count || 0);
        following = follow;
        if (follow && !followerSnapshot.exists) {
          transaction.create(followerRef, {
            analyst_id: analystId,
            follower_id: uid,
            created_at: now,
          });
          transaction.set(profileRef, {
            follower_count: currentCount + 1,
            updated_at: now,
          }, {merge: true});
          transaction.create(
              analystRef.collection("notifications").doc(),
              {
                user_id: analystId,
                category: "analyst_follow",
                title: "New follower",
                message: "A trader followed your market outlooks.",
                follower_id: uid,
                is_read: false,
                created_at: now,
              },
          );
        } else if (!follow && followerSnapshot.exists) {
          transaction.delete(followerRef);
          transaction.set(profileRef, {
            follower_count: Math.max(0, currentCount - 1),
            updated_at: now,
          }, {merge: true});
        } else {
          following = followerSnapshot.exists;
        }
      });
      return {following};
    },
);

function effectiveRoles(user = {}) {
  const roles = new Set(
      (Array.isArray(user.role_ids) ? user.role_ids : []).map(Number),
  );
  roles.add(Number(user.role_id || 0));
  return roles;
}

function text(value) {
  return value == null ? "" : String(value).trim();
}
