"use strict";

const crypto = require("crypto");
const {getFirestore, FieldValue} = require("firebase-admin/firestore");
const {getAuth} = require("firebase-admin/auth");
const {getStorage} = require("firebase-admin/storage");
const {HttpsError, onCall} = require("firebase-functions/v2/https");
const {onDocumentUpdated} = require("firebase-functions/v2/firestore");
const {logger} = require("firebase-functions");
const {
  ROLE_IDS,
  adminPermissionsForRole,
  canAssignRole,
  effectiveRoleIds,
  hasPermission,
  isAdminRole,
  isPrivilegedRole,
  isPublicRegistrationRole,
  publicRegistrationRoleOrDefault,
  roleName,
} = require("./role_permissions");

const REGION = "us-central1";
const NORMAL_REFERRAL_PURPOSE = "upline_downline";
const WEEKLY_REFERRAL_TYPE = "weekly_recruitment";
const ACCESS_STATUS = Object.freeze({
  NOT_SUBMITTED: "notSubmitted",
  PENDING: "pendingReview",
  APPROVED: "approved",
  REJECTED: "rejected",
  MORE_INFO_REQUIRED: "moreInfoRequired",
  REVOKED: "revoked",
  WITHDRAWN: "withdrawn",
  CANCELLED: "cancelled",
});

function normalizeAccessStatus(value) {
  const normalized = text(value).replace(/[-_\s]/g, "").toLowerCase();
  if (["pending", "pendingreview"].includes(normalized)) {
    return ACCESS_STATUS.PENDING;
  }
  if (normalized === "approved") return ACCESS_STATUS.APPROVED;
  if (["rejected", "denied"].includes(normalized)) return ACCESS_STATUS.REJECTED;
  if (["moreinforequired", "requestmoreinfo", "changesrequested"]
      .includes(normalized)) {
    return ACCESS_STATUS.MORE_INFO_REQUIRED;
  }
  if (normalized === "revoked") return ACCESS_STATUS.REVOKED;
  if (["withdraw", "withdrawn"].includes(normalized)) return ACCESS_STATUS.WITHDRAWN;
  if (["cancelled", "canceled"].includes(normalized)) return ACCESS_STATUS.CANCELLED;
  return ACCESS_STATUS.NOT_SUBMITTED;
}

function accessDecisionPlan(decision) {
  return {
    approve: {
      status: ACCESS_STATUS.APPROVED,
      allowedStatuses: [ACCESS_STATUS.PENDING],
      permission: "accessApplication.approve",
      recruitmentStatus: "approved",
      assignRequestedRole: true,
    },
    reject: {
      status: ACCESS_STATUS.REJECTED,
      allowedStatuses: [ACCESS_STATUS.PENDING],
      permission: "accessApplication.reject",
      recruitmentStatus: "rejected",
      assignRequestedRole: false,
    },
    request_info: {
      status: ACCESS_STATUS.MORE_INFO_REQUIRED,
      allowedStatuses: [ACCESS_STATUS.PENDING],
      permission: "accessApplication.reject",
      recruitmentStatus: "more_info_required",
      assignRequestedRole: false,
    },
    revoke: {
      status: ACCESS_STATUS.REVOKED,
      allowedStatuses: [ACCESS_STATUS.APPROVED],
      permission: "accessApplication.approve",
      recruitmentStatus: "revoked",
      assignRequestedRole: false,
    },
    restore: {
      status: ACCESS_STATUS.APPROVED,
      allowedStatuses: [ACCESS_STATUS.REVOKED],
      permission: "accessApplication.approve",
      recruitmentStatus: "approved",
      assignRequestedRole: false,
    },
  }[decision] || null;
}

exports.registerUserProfileAfterAuth = onCall(
    {region: REGION},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) {
        throw new HttpsError(
            "unauthenticated",
            "Sign in before completing registration.",
        );
      }

      const db = getFirestore();
      await assertFeatureWritable(db, "user_registrations");

      const payload = normalizeRegistrationPayload(request.data || {});
      const authEmail = text(request.auth?.token?.email).toLowerCase();
      if (authEmail && authEmail !== payload.email) {
        throw new HttpsError(
            "permission-denied",
            "Registration email does not match the authenticated account.",
        );
      }
      if (!/^[a-z0-9_]+$/.test(payload.username)) {
        throw new HttpsError(
            "invalid-argument",
            "Enter a valid lowercase username.",
        );
      }

      const resolution = await resolveReferral(db, payload, uid);
      const roleId = provisionalRoleId(resolution.requestedRoleId);
      const generatedReferralCode = await uniqueReferralCode(db);
      const now = FieldValue.serverTimestamp();
      const userRef = db.collection("users").doc(uid);
      const usernameRef = db.collection("usernames").doc(payload.username);
      const referralRef = db.collection("referral_codes")
          .doc(generatedReferralCode.toLowerCase());
      const teamRef = resolution.teamId ?
        db.collection("teams").doc(resolution.teamId) : null;

      await db.runTransaction(async (transaction) => {
        const snapshots = await Promise.all([
            transaction.get(userRef),
            transaction.get(usernameRef),
            transaction.get(referralRef),
            resolution.referralDocumentId ?
              transaction.get(db.collection("referral_codes")
                  .doc(resolution.referralDocumentId)) : Promise.resolve(null),
            resolution.referrerId ?
              transaction.get(db.collection("users")
                  .doc(resolution.referrerId)) : Promise.resolve(null),
            teamRef ? transaction.get(teamRef) : Promise.resolve(null),
          ]);
        const [userSnapshot, usernameSnapshot, generatedReferralSnapshot,
          usedReferralSnapshot, referrerSnapshot, teamSnapshot] = snapshots;
        if (userSnapshot.exists) {
          throw new HttpsError(
              "already-exists",
              "This Firebase account already has a profile.",
          );
        }
        if (usernameSnapshot.exists) {
          throw new HttpsError(
              "already-exists",
              "This username is already registered.",
          );
        }
        if (generatedReferralSnapshot.exists) {
          throw new HttpsError(
              "aborted",
              "Referral code collision. Please try again.",
          );
        }
        if (resolution.referralDocumentId) {
          const used = usedReferralSnapshot?.data() || {};
          if (!usedReferralSnapshot?.exists) {
            throw new HttpsError(
                "invalid-argument",
                "Please enter a valid referral code.",
            );
          }
          assertReferralAvailable(used);
          assertEligibleReferrer(referrerSnapshot);
          assertEligibleTeam(teamSnapshot, resolution.teamId);
          transaction.update(usedReferralSnapshot.ref, {
            usage_count: FieldValue.increment(1),
            current_uses: FieldValue.increment(1),
            last_used_at: now,
            updated_at: now,
          });
        }

        transaction.set(usernameRef, {
          uid,
          email: payload.email,
          created_at: now,
        });
        transaction.set(referralRef, {
          code: generatedReferralCode,
          code_normalized: generatedReferralCode.toLowerCase(),
          user_id: uid,
          owner_user_id: uid,
          code_type: "normal",
          allowed_purpose: NORMAL_REFERRAL_PURPOSE,
          registration_role_id: ROLE_IDS.trader,
          is_active: true,
          created_at: now,
        });
        transaction.set(userRef, {
          name: payload.name,
          name_lower: payload.name.toLowerCase(),
          username: payload.username,
          username_lowercase: payload.username,
          email: payload.email,
          email_lowercase: payload.email,
          role_id: roleId,
          role_ids: [roleId],
          role_name: roleName(roleId),
          requested_role_id: resolution.requestedRoleId,
          requested_role_key: roleKey(resolution.requestedRoleId),
          requested_role: roleName(resolution.requestedRoleId),
          approved_role_id: null,
          approved_role: null,
          permissions: adminPermissionsForRole(roleId),
          permission_overrides: {},
          status: 1,
          account_status: "active",
          is_suspended: false,
          is_admin: isAdminRole(roleId),
          accessStatus: ACCESS_STATUS.NOT_SUBMITTED,
          access_status: ACCESS_STATUS.NOT_SUBMITTED,
          requires_onboarding: roleId === ROLE_IDS.trader,
          final_exam_passed: false,
          requires_final_exam: false,
          invited_by: resolution.referrerId,
          referrer_id: resolution.referrerId,
          referred_by_user_id: resolution.referrerId,
          upline_id: resolution.uplineId || resolution.referrerId,
          leader_id: resolution.leaderId,
          recruiter_id: resolution.recruiterId,
          team_ids: resolution.teamId ? [resolution.teamId] : [],
          organisation_id: resolution.organisationId,
          campaign_id: resolution.campaignId,
          registration_campaign_id: payload.campaignId || null,
          registration_source: payload.registrationSource || "direct",
          registration_entry_point: payload.referralCode ? "referral" : "direct",
          referral_code_used: resolution.referralCodeUsed,
          referral_code_type: resolution.referralCodeType,
          created_by_referral_type: resolution.createdByReferralType,
          referral_code: generatedReferralCode,
          verification_status: "not_submitted",
          mt5_status: "none",
          has_pending_application: false,
          has_unresolved_ticket: false,
          admin_search_terms: buildAdminSearchTerms({
            id: uid,
            name: payload.name,
            username: payload.username,
            email: payload.email,
            referral_code: generatedReferralCode,
            role_name: roleName(roleId),
          }),
          referral_code_type_generated: "normal",
          referral_registered_at: resolution.referralCodeUsed ? now : null,
          created_at: now,
          updated_at: now,
        });

        if (resolution.referrerId) {
          const referrer = referrerSnapshot.data() || {};
          const referrerName = text(
              referrer.name || referrer.display_name || referrer.displayName,
          );
          transaction.set(db.collection("referral_relationships").doc(uid), {
            user_id: uid,
            referrer_id: resolution.referrerId,
            upline_id: resolution.uplineId || resolution.referrerId,
            leader_id: resolution.leaderId || null,
            recruiter_id: resolution.recruiterId || null,
            team_id: resolution.teamId || null,
            organisation_id: resolution.organisationId || null,
            upline_name: referrerName || null,
            referrer_name: referrerName || null,
            referral_code: resolution.referralCodeUsed,
            referral_code_type: "normal",
            created_at: now,
          });
          transaction.set(
              db.collection("users")
                  .doc(resolution.referrerId)
                  .collection("downlines")
                  .doc(uid),
              {
                user_id: uid,
                user_name: payload.name,
                referral_code: resolution.referralCodeUsed,
                created_at: now,
              },
          );
        }

        if (resolution.referralCodeUsed) {
          transaction.set(db.collection("admin_audit_logs").doc(), {
            actorUserId: resolution.referrerId,
            targetUserId: uid,
            action: "referral_code_used",
            resourceType: "referral_code",
            resourceId: resolution.referralDocumentId,
            before: null,
            after: {
              referralCode: resolution.referralCodeUsed,
              requestedRoleId: resolution.requestedRoleId,
              teamId: resolution.teamId,
              organisationId: resolution.organisationId,
            },
            reason: "public_registration",
            source: "cloud_function",
            createdAt: now,
            created_at: now,
          });
        }

      });

      logger.info("Registered Firebase profile", {
        uid,
        roleId,
        referralCodeType: resolution.referralCodeType || "none",
      });

      return {
        userId: uid,
        roleId,
        roleName: roleName(roleId),
        referralCodeType: resolution.referralCodeType || "none",
      };
    },
);

exports.recruitDownlineByReferralCode = onCall(
    {region: REGION},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) {
        throw new HttpsError(
            "unauthenticated",
            "Sign in before completing this request.",
        );
      }

      throw new HttpsError(
          "failed-precondition",
          "Referral codes can only be applied while the invited user creates a new account.",
      );

      const payload = request.data || {};
      if (text(payload.requesterId) && text(payload.requesterId) !== uid) {
        throw new HttpsError(
            "permission-denied",
            "Your account does not have permission to complete this request.",
        );
      }

      const referralCode = normalizeReferralCode(payload.referralCode);
      if (!referralCode) {
        throw new HttpsError("invalid-argument", "Referral code is required");
      }

      const db = getFirestore();
      const now = FieldValue.serverTimestamp();
      const actorRef = db.collection("users").doc(uid);
      const referralRef = db.collection("referral_codes").doc(referralCode);

      return db.runTransaction(async (transaction) => {
        const [actorSnapshot, referralSnapshot] = await Promise.all([
          transaction.get(actorRef),
          transaction.get(referralRef),
        ]);
        if (!actorSnapshot.exists) {
          throw new HttpsError(
              "failed-precondition",
              "Your account profile is incomplete. Please contact support.",
          );
        }
        if (!referralSnapshot.exists) {
          throw new HttpsError("not-found", "Invalid referral code");
        }

        const actor = actorSnapshot.data() || {};
        const referral = referralSnapshot.data() || {};
        if (referral.is_active === false || expired(referral.expires_at)) {
          throw new HttpsError("failed-precondition", "Invalid referral code");
        }
        const referralCodeType = text(referral.code_type, "normal");
        if (!["normal", WEEKLY_REFERRAL_TYPE].includes(referralCodeType) ||
            text(referral.allowed_purpose, NORMAL_REFERRAL_PURPOSE) !==
              NORMAL_REFERRAL_PURPOSE) {
          throw new HttpsError("permission-denied", "Invalid referral code");
        }

        const targetId = text(referral.owner_user_id || referral.user_id);
        if (!targetId) {
          throw new HttpsError("failed-precondition", "Invalid referral code");
        }
        if (targetId === uid) {
          throw new HttpsError(
              "failed-precondition",
              "You cannot use your own referral code",
          );
        }

        const targetRef = db.collection("users").doc(targetId);
        const relationshipRef = db.collection("referral_relationships")
            .doc(targetId);
        const downlineRef = actorRef.collection("downlines").doc(targetId);
        const [targetSnapshot, relationshipSnapshot, downlineSnapshot] =
          await Promise.all([
            transaction.get(targetRef),
            transaction.get(relationshipRef),
            transaction.get(downlineRef),
          ]);
        if (!targetSnapshot.exists) {
          throw new HttpsError("not-found", "Invalid referral code");
        }
        const target = targetSnapshot.data() || {};
        const actorRoleId = Number(actor.role_id || 0);
        const targetRoleId = Number(target.role_id || 0);
        if (!isPublicRegistrationRole(targetRoleId) ||
            !canAssignRole(actorRoleId, targetRoleId)) {
          throw new HttpsError("permission-denied", "Invalid referral code");
        }

        const existingUpline = text(
            target.upline_id || target.referrer_id || target.invited_by,
        );
        if (existingUpline || relationshipSnapshot.exists ||
            downlineSnapshot.exists) {
          throw new HttpsError(
              "already-exists",
              "This trader already has an upline",
          );
        }
        const actorUpline = text(
            actor.upline_id || actor.referrer_id || actor.invited_by,
        );
        if (actorUpline === targetId) {
          throw new HttpsError(
              "failed-precondition",
              "This referral would create a circular upline relationship.",
          );
        }

        transaction.set(targetRef, {
          upline_id: uid,
          referrer_id: uid,
          invited_by: uid,
          referral_code_used: referralCode,
          referral_code_type: referralCodeType,
          created_by_referral_type: referralCodeType,
          recruited_at: now,
          updated_at: now,
        }, {merge: true});
        transaction.set(relationshipRef, {
          user_id: targetId,
          referrer_id: uid,
          upline_id: uid,
          referral_code: referralCode,
          referral_code_type: referralCodeType,
          created_at: now,
        });
        transaction.set(downlineRef, {
          user_id: targetId,
          referral_code: referralCode,
          name: text(target.name),
          username: text(target.username),
          email: text(target.email),
          role_id: targetRoleId,
          role_name: text(target.role_name, roleName(targetRoleId)),
          created_at: now,
        });

        return {uplineId: uid, downlineId: targetId};
      });
    },
);

exports.getMyUplineRelationship = onCall(
    {region: REGION},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) {
        throw new HttpsError(
            "unauthenticated",
            "Sign in before viewing your upline.",
        );
      }
      const db = getFirestore();
      const [relationshipSnapshot, userSnapshot] = await Promise.all([
        db.collection("referral_relationships").doc(uid).get(),
        db.collection("users").doc(uid).get(),
      ]);
      const userData = userSnapshot.data() || {};
      const legacyIds = new Set([
        text(userData.upline_id),
        text(userData.referrer_id),
        text(userData.referred_by_user_id),
        text(userData.invited_by),
      ].filter(Boolean));
      if (!relationshipSnapshot.exists) {
        return {status: legacyIds.size ? "missingRecord" : "noUpline"};
      }
      const relationship = relationshipSnapshot.data() || {};
      if (text(relationship.relationship_status) === "upline_unavailable" ||
          relationship.upline_unavailable === true) {
        return {status: "unavailable"};
      }
      const referrerIds = new Set([
        text(relationship.referrer_id),
        text(relationship.upline_id),
      ].filter(Boolean));
      if (referrerIds.size !== 1) return {status: "conflict"};
      const referrerId = [...referrerIds][0];
      if (legacyIds.size &&
          (legacyIds.size !== 1 || !legacyIds.has(referrerId))) {
        return {status: "conflict"};
      }
      const referrerSnapshot = await db.collection("users").doc(referrerId).get();
      if (!referrerSnapshot.exists) return {status: "unavailable"};
      const referrer = referrerSnapshot.data() || {};
      const accountStatus = text(referrer.account_status, "active")
          .toLowerCase().replace(/[-_\s]/g, "");
      if (Number(referrer.status ?? 1) === 0 ||
          referrer.is_suspended === true ||
          ["deleted", "deletioninprogress", "inactive", "suspended"]
              .includes(accountStatus)) {
        return {status: "unavailable"};
      }
      const displayName = text(
          referrer.name || referrer.display_name || referrer.displayName,
      );
      return displayName ?
        {status: "available", displayName} : {status: "unavailable"};
    },
);

exports.deleteCurrentUserAccount = onCall(
    {region: REGION, timeoutSeconds: 540, memory: "512MiB"},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) {
        throw new HttpsError(
            "unauthenticated",
            "Sign in before deleting your account.",
        );
      }

      const db = getFirestore();
      const auth = getAuth();
      assertRecentAuthentication(request);
      const deletionReference = hashedDeletionReference(uid);
      const jobRef = db.collection("account_deletion_jobs")
          .doc(deletionReference);
      const auditRef = db.collection("deletedUsers").doc(deletionReference);
      const userRef = db.collection("users").doc(uid);
      const previousJob = await jobRef.get();
      const previous = previousJob.data() || {};
      if (text(previous.status) === "completed") {
        try {
          await auth.getUser(uid);
          // A previous invocation committed its non-PII completion record but
          // ended before the final Auth deletion. Resume that last operation.
        } catch (error) {
          if (error?.code === "auth/user-not-found") {
            return {deleted: true, status: "completed", resumed: true};
          }
          throw error;
        }
      }
      const completedSteps = new Set(
          Array.isArray(previous.completed_steps) ?
            previous.completed_steps.map(text) : [],
      );
      const now = FieldValue.serverTimestamp();
      await jobRef.set({
        job_id: deletionReference,
        uid_hash: deletionReference,
        status: "processing",
        current_step: "requested",
        completed_steps: [...completedSteps],
        failed_steps: [],
        retry_count: FieldValue.increment(previousJob.exists ? 1 : 0),
        schema_version: 3,
        requested_at: previous.requested_at || now,
        started_at: previous.started_at || now,
        updated_at: now,
      }, {merge: true});

      let phase = "requested";
      try {
        const userSnapshot = await userRef.get();
        await auditRef.set({
          deletion_reference: deletionReference,
          uid_hash: deletionReference,
          deletionSource: "mobile_self_service",
          deletion_source: "mobile_self_service",
          retention_category: "security_and_account_integrity",
          auditVersion: 3,
          deletionStatus: "processing",
          deletion_status: "processing",
          processingStartedAt: now,
          requested_at: previous.requested_at || now,
          updated_at: now,
        }, {merge: true});
        if (userSnapshot.exists) {
          await userRef.set({
            account_status: "deletionInProgress",
            deletion_status: "processing",
            deletion_requested_at: now,
            updated_at: now,
          }, {merge: true});
        }

        const run = async (step, operation) => {
          phase = step;
          if (completedSteps.has(step)) return;
          await jobRef.set({
            current_step: step,
            updated_at: FieldValue.serverTimestamp(),
          }, {merge: true});
          try {
            await operation();
            completedSteps.add(step);
            await jobRef.set({
              completed_steps: FieldValue.arrayUnion(step),
              failed_steps: FieldValue.arrayRemove(step),
              updated_at: FieldValue.serverTimestamp(),
            }, {merge: true});
          } catch (error) {
            await jobRef.set({
              failed_steps: FieldValue.arrayUnion(step),
              error_summary: safeDeletionError(error),
              updated_at: FieldValue.serverTimestamp(),
            }, {merge: true}).catch(() => undefined);
            throw error;
          }
        };

        await run("storage_cleanup", () => deleteUserStorage(uid));
        await run("referral_cleanup", () =>
          cleanupReferralParticipation(db, uid, deletionReference));
        await run("global_private_cleanup", () =>
          cleanupGlobalPrivateData(db, uid, deletionReference));
        await run("shared_record_anonymization", () =>
          anonymizeSharedUserData(db, uid));
        await run("private_user_tree_cleanup", async () => {
          if ((await userRef.get()).exists) await db.recursiveDelete(userRef);
        });

        // Commit the resumable, non-PII completion records before removing
        // Auth. Auth deletion is deliberately the final operation so there is
        // no post-deletion write that could fail and strand an unrepeatable
        // job. A retry verifies Auth existence above before returning early.
        phase = "authentication_cleanup";
        const completedAt = FieldValue.serverTimestamp();
        await Promise.all([
          jobRef.set({
            status: "completed",
            current_step: "completed",
            completed_steps: FieldValue.arrayUnion("authentication_cleanup"),
            completed_at: completedAt,
            error_summary: FieldValue.delete(),
            updated_at: completedAt,
          }, {merge: true}),
          auditRef.set({
            deletionStatus: "completed",
            deletion_status: "completed",
            completedAt,
            completed_at: completedAt,
            processing_summary: [...completedSteps],
            failurePhase: FieldValue.delete(),
            failureCode: FieldValue.delete(),
            updated_at: completedAt,
          }, {merge: true}),
          db.collection("admin_audit_logs")
              .doc(`self_delete_${deletionReference}`).set({
            action: "self_delete_account",
            module: "account_deletion",
            actor_reference: deletionReference,
            target_type: "deleted_user",
            target_reference: deletionReference,
            created_at: completedAt,
          }, {merge: true}),
        ]);

        await auth.revokeRefreshTokens(uid).catch(() => undefined);
        try {
          await auth.deleteUser(uid);
        } catch (error) {
          if (error?.code !== "auth/user-not-found") throw error;
        }
        return {deleted: true, status: "completed"};
      } catch (error) {
        logger.error("Account deletion partially failed", {
          deletionReference,
          phase,
          code: text(error?.code, "unknown"),
        });
        await auditRef.set({
          deletionStatus: "partiallyFailed",
          deletion_status: "partiallyFailed",
          failurePhase: phase,
          failureCode: safeDeletionError(error),
          updated_at: FieldValue.serverTimestamp(),
        }, {merge: true}).catch(() => undefined);
        await jobRef.set({
          status: "partiallyFailed",
          current_step: phase,
          failed_steps: FieldValue.arrayUnion(phase),
          ...(phase === "authentication_cleanup" ? {
            completed_steps: FieldValue.arrayRemove("authentication_cleanup"),
          } : {}),
          error_summary: safeDeletionError(error),
          updated_at: FieldValue.serverTimestamp(),
        }, {merge: true}).catch(() => undefined);
        throw new HttpsError(
            "internal",
            "Account deletion could not be completed. Please retry or contact support.",
        );
      }
    },
);

exports.createDesktopCustomSignInToken = onCall(
    {region: REGION, timeoutSeconds: 30},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) {
        throw new HttpsError(
            "unauthenticated",
            "Verify your password before completing Windows sign-in.",
        );
      }
      const provider = text(
          request.auth?.token?.firebase?.sign_in_provider,
      );
      if (provider !== "password") {
        throw new HttpsError(
            "permission-denied",
            "Windows password sign-in requires a verified password token.",
        );
      }

      const auth = getAuth();
      const user = await auth.getUser(uid);
      if (user.disabled) {
        throw new HttpsError(
            "permission-denied",
            "This account is disabled. Please contact administration.",
        );
      }
      const customToken = await auth.createCustomToken(uid);
      return {customToken};
    },
);

exports.adminCreateUser = onCall(
    {region: REGION, timeoutSeconds: 120},
    async (request) => {
      const actorId = request.auth?.uid;
      if (!actorId) {
        throw new HttpsError(
            "unauthenticated",
            "Sign in before creating a user.",
        );
      }
      const db = getFirestore();
      const actorSnapshot = await db.collection("users").doc(actorId).get();
      const actor = actorSnapshot.data() || {};
      const actorRoleId = Number(actor.role_id || 0);
      if (!actorSnapshot.exists ||
          !hasPermission(actor, "users.updateBasic")) {
        throw new HttpsError(
            "permission-denied",
            "Your account cannot create users.",
        );
      }

      const payload = normalizeAdminCreateUserPayload(request.data || {});
      if (!payload.name || !payload.email || !payload.username) {
        throw new HttpsError(
            "invalid-argument",
            "Name, email, and username are required.",
        );
      }
      if (!/^[a-z0-9_]+$/.test(payload.username)) {
        throw new HttpsError(
            "invalid-argument",
            "Enter a valid lowercase username.",
        );
      }
      if (![...effectiveRoleIds(actor)].some((roleId) =>
        canAssignRole(roleId, payload.roleId))) {
        throw new HttpsError(
            "permission-denied",
            "You are not allowed to assign this role.",
        );
      }

      let authUser;
      const now = FieldValue.serverTimestamp();
      const generatedReferralCode = await uniqueReferralCode(db);
      try {
        authUser = await getAuth().createUser({
          email: payload.email,
          password: payload.password,
          displayName: payload.name,
          disabled: payload.status !== 1,
          emailVerified: false,
        });

        await db.runTransaction(async (transaction) => {
          const userRef = db.collection("users").doc(authUser.uid);
          const usernameRef = db.collection("usernames")
              .doc(payload.username);
          const generatedReferralRef = db.collection("referral_codes")
              .doc(generatedReferralCode.toLowerCase());
          const [userSnapshot, usernameSnapshot, referralSnapshot] =
            await Promise.all([
              transaction.get(userRef),
              transaction.get(usernameRef),
              transaction.get(generatedReferralRef),
            ]);
          if (userSnapshot.exists) {
            throw new HttpsError("already-exists", "User already exists.");
          }
          if (usernameSnapshot.exists) {
            throw new HttpsError(
                "already-exists",
                "This username is already registered.",
            );
          }
          if (referralSnapshot.exists) {
            throw new HttpsError(
                "aborted",
                "Referral code collision. Please try again.",
            );
          }
          transaction.set(usernameRef, {
            uid: authUser.uid,
            email: payload.email,
            created_by: actorId,
            created_at: now,
          });
          transaction.set(generatedReferralRef, {
            code: generatedReferralCode,
            code_normalized: generatedReferralCode.toLowerCase(),
            user_id: authUser.uid,
            owner_user_id: authUser.uid,
            code_type: "normal",
            allowed_purpose: NORMAL_REFERRAL_PURPOSE,
            registration_role_id: ROLE_IDS.trader,
            is_active: true,
            created_by: actorId,
            created_at: now,
          });
          transaction.set(userRef, {
            name: payload.name,
            name_lower: payload.name.toLowerCase(),
            username: payload.username,
            username_lowercase: payload.username,
            email: payload.email,
            email_lowercase: payload.email,
            role_id: provisionalRoleId(payload.roleId),
            role_ids: [provisionalRoleId(payload.roleId)],
            role_name: roleName(provisionalRoleId(payload.roleId)),
            requested_role_id: payload.roleId,
            requested_role_key: roleKey(payload.roleId),
            requested_role: roleName(payload.roleId),
            approved_role_id: null,
            approved_role: null,
            permissions: adminPermissionsForRole(provisionalRoleId(payload.roleId)),
            status: payload.status,
            account_status: payload.status === 1 ? "active" : "suspended",
            is_suspended: payload.status !== 1,
            is_admin: false,
            accessStatus: ACCESS_STATUS.NOT_SUBMITTED,
            access_status: ACCESS_STATUS.NOT_SUBMITTED,
            recruitmentStatus: "invited",
            recruitment_status: "invited",
            requires_onboarding: true,
            final_exam_passed: false,
            requires_final_exam: false,
            referral_code: generatedReferralCode,
            verification_status: "not_submitted",
            mt5_status: "none",
            has_pending_application: false,
            has_unresolved_ticket: false,
            admin_search_terms: buildAdminSearchTerms({
              id: authUser.uid,
              name: payload.name,
              username: payload.username,
              email: payload.email,
              referral_code: generatedReferralCode,
              role_name: roleName(provisionalRoleId(payload.roleId)),
            }),
            referral_code_type_generated: "normal",
            created_by: actorId,
            created_via: "admin_create_user",
            registrationSource: "admin_assisted",
            registration_source: "admin_assisted",
            createdByAdminUid: actorId,
            created_by_admin_uid: actorId,
            createdByAdminRole: actorRoleId,
            created_by_admin_role: actorRoleId,
            created_at: now,
            updated_at: now,
          });
        });

        await db.collection("admin_audit_logs").add({
          action: "admin_create_user",
          module: "account_management",
          actor_id: actorId,
          target_type: "user",
          target_id: authUser.uid,
          email: payload.email,
          requested_role_id: payload.roleId,
          provisional_role_id: provisionalRoleId(payload.roleId),
          access_status: ACCESS_STATUS.NOT_SUBMITTED,
          created_at: now,
        });
        await db.collection("recruitments").doc(authUser.uid).set({
          recruitmentId: authUser.uid,
          recruitedEmail: payload.email,
          recruitedUserId: authUser.uid,
          requestedRole: roleKey(payload.roleId),
          requestedRoleId: payload.roleId,
          recruitedByUid: actorId,
          recruitedByRole: actorRoleId,
          referralCode: null,
          referralWeekKey: null,
          status: "accessPending",
          accessApplicationStatus: ACCESS_STATUS.NOT_SUBMITTED,
          approvedRole: null,
          approvedBy: null,
          approvedAt: null,
          createdAt: now,
          updatedAt: now,
        }, {merge: true});

        return {
          userId: authUser.uid,
          email: payload.email,
          username: payload.username,
          roleId: payload.roleId,
        };
      } catch (error) {
        if (authUser?.uid) {
          await getAuth().deleteUser(authUser.uid).catch(() => {});
        }
        if (error instanceof HttpsError) throw error;
        if (error?.code === "auth/email-already-exists") {
          throw new HttpsError(
              "already-exists",
              "This email address is already registered.",
          );
        }
        logger.error("adminCreateUser failed", {actorId, error});
        throw new HttpsError(
            "internal",
            "Unable to create this user. Please try again.",
        );
      }
    },
);

exports.adminListDeletedUsers = onCall(
    {region: REGION, timeoutSeconds: 60},
    async (request) => {
      const actorId = request.auth?.uid;
      if (!actorId) {
        throw new HttpsError(
            "unauthenticated",
            "Sign in before loading deleted users.",
        );
      }
      const db = getFirestore();
      const actorSnapshot = await db.collection("users").doc(actorId).get();
      const actor = actorSnapshot.data() || {};
      const actorRoleId = Number(actor.role_id || 0);
      if (actor.is_admin !== true && !isAdminRole(actorRoleId)) {
        throw new HttpsError(
            "permission-denied",
            "Your account cannot view deleted users.",
        );
      }

      const limit = Math.min(
          Math.max(Number(request.data?.limit || 100), 1),
          200,
      );
      const snapshot = await db.collection("deletedUsers")
          .orderBy("deleted_at", "desc")
          .limit(limit)
          .get();
      return {
        users: snapshot.docs.map((doc) => serializeDeletedUser(doc)),
      };
    },
);

exports.adminReviewAccessApplication = onCall(
    {region: REGION},
    async (request) => {
      const actorId = request.auth?.uid;
      if (!actorId) throw new HttpsError("unauthenticated", "Sign in first.");
      const requestId = text(request.data?.requestId);
      const decision = text(request.data?.decision).toLowerCase();
      const note = text(request.data?.note).slice(0, 1000);
      const decisionPlan = accessDecisionPlan(decision);
      if (!requestId || !decisionPlan) {
        throw new HttpsError("invalid-argument", "Invalid access decision.");
      }
      if (decision !== "approve" && !note) {
        throw new HttpsError(
            "invalid-argument",
            "A reason is required for this access decision.",
        );
      }
      const db = getFirestore();
      const accessRef = db.collection("accessRequests").doc(requestId);
      const auditRef = db.collection("admin_audit_logs").doc();
      const initialAccessSnapshot = await accessRef.get();
      if (!initialAccessSnapshot.exists) {
        throw new HttpsError("not-found", "Access application not found.");
      }
      const initialAccess = initialAccessSnapshot.data() || {};
      const uid = text(
          initialAccess.user_id || initialAccess.userId || requestId,
      );
      if (!uid) {
        throw new HttpsError(
            "failed-precondition",
            "The access application is missing its user account.",
        );
      }
      const recruitments = await db.collection("recruitments")
          .where("recruitedUserId", "==", uid).limit(20).get();
      if (uid === actorId) {
        throw new HttpsError(
            "permission-denied",
            "Administrators cannot review their own access.",
        );
      }
      const actorRef = db.collection("users").doc(actorId);
      const userRef = db.collection("users").doc(uid);
      const status = decisionPlan.status;
      const result = await db.runTransaction(async (transaction) => {
        const [actorSnapshot, userSnapshot, accessSnapshot] =
          await Promise.all([
            transaction.get(actorRef),
            transaction.get(userRef),
            transaction.get(accessRef),
          ]);
        if (!actorSnapshot.exists || !userSnapshot.exists ||
            !accessSnapshot.exists) {
          throw new HttpsError("not-found", "Access application not found.");
        }
        const actor = actorSnapshot.data() || {};
        const user = userSnapshot.data() || {};
        const access = accessSnapshot.data() || {};
        const applicationUserId = text(access.user_id || access.userId || requestId);
        if (applicationUserId !== uid) {
          throw new HttpsError(
              "failed-precondition",
              "The access application does not match this user.",
          );
        }
        if (!hasPermission(actor, decisionPlan.permission)) {
          throw new HttpsError("permission-denied", "Access denied.");
        }
        const actorRoles = effectiveRoleIds(actor);
        const currentAccessStatus = normalizeAccessStatus(
            access.status || access.access_status || access.accessStatus,
        );
        if (!decisionPlan.allowedStatuses.includes(currentAccessStatus)) {
          throw new HttpsError(
              "aborted",
              "This application changed before the action was completed.",
          );
        }
        const requestedRoleId = Number(
            user.requested_role_id || user.role_id || ROLE_IDS.trader,
        );
        const targetRoleIds = [...effectiveRoleIds(user)];
        if (decisionPlan.assignRequestedRole &&
            ![...actorRoles].some((roleId) =>
              canAssignRole(roleId, requestedRoleId))) {
          throw new HttpsError(
              "permission-denied",
              "You cannot approve the requested role.",
          );
        }
        if (["revoke", "restore"].includes(decision) &&
            targetRoleIds.some((targetRoleId) =>
              ![...actorRoles].some((actorRoleId) =>
                canAssignRole(actorRoleId, targetRoleId)))) {
          throw new HttpsError(
              "permission-denied",
              "You cannot change access for this role.",
          );
        }
        const now = FieldValue.serverTimestamp();
        const claimsVersion = Number(user.claims_version || 0) + 1;
        transaction.set(accessRef, {
          status,
          accessStatus: status,
          access_status: status,
          reviewedBy: actorId,
          reviewed_by: actorId,
          reviewedAt: now,
          reviewed_at: now,
          adminNote: note || null,
          admin_note: note || null,
          reviewOutcome: decision,
          review_outcome: decision,
          moreInfoRequested: decision === "request_info",
          more_info_requested: decision === "request_info",
          updatedAt: now,
          updated_at: now,
        }, {merge: true});
        transaction.set(userRef, {
          accessStatus: status,
          access_status: status,
          accessUpdatedAt: now,
          access_updated_at: now,
          accessReviewNote: note || null,
          access_review_note: note || null,
          ...(decisionPlan.assignRequestedRole ? {
            role_id: requestedRoleId,
            role_ids: [requestedRoleId],
            role_name: roleName(requestedRoleId),
            approved_role_id: requestedRoleId,
            approved_role: roleName(requestedRoleId),
            approved_by: actorId,
            approved_at: now,
            is_admin: isAdminRole(requestedRoleId),
            permissions: adminPermissionsForRole(requestedRoleId),
            recruitmentStatus: "approved",
            recruitment_status: "approved",
          } : decision === "reject" ? {
            approved_role_id: null,
            approved_role: null,
            recruitmentStatus: "rejected",
            recruitment_status: "rejected",
          } : {
            recruitmentStatus: decisionPlan.recruitmentStatus,
            recruitment_status: decisionPlan.recruitmentStatus,
          }),
          ...(decision === "request_info" ? {
            more_info_requested_by: actorId,
            more_info_requested_at: now,
          } : {}),
          ...(decision === "revoke" ? {
            access_revoked_by: actorId,
            access_revoked_at: now,
          } : {}),
          ...(decision === "restore" ? {
            access_restored_by: actorId,
            access_restored_at: now,
            access_revoked_by: FieldValue.delete(),
            access_revoked_at: FieldValue.delete(),
          } : {}),
          has_pending_application: false,
          claims_version: claimsVersion,
          claims_sync_pending: true,
          admin_search_terms: buildAdminSearchTerms({
            ...user,
            id: uid,
            role_id: decisionPlan.assignRequestedRole ?
              requestedRoleId : user.role_id,
            role_ids: decisionPlan.assignRequestedRole ?
              [requestedRoleId] : user.role_ids,
            access_status: status,
          }),
          updated_at: now,
        }, {merge: true});
        transaction.create(auditRef, {
          action: `access_${decision}`,
          module: "client_access",
          actorUserId: actorId,
          actorRoleIds: [...actorRoles],
          targetUserId: uid,
          resourceType: "access_request",
          resourceId: requestId,
          before: {
            accessStatus: normalizeAccessStatus(
                access.status || access.access_status || access.accessStatus,
            ),
            roleId: Number(user.role_id || 0),
          },
          after: {
            accessStatus: status,
            roleId: decisionPlan.assignRequestedRole ?
              requestedRoleId : Number(user.role_id || 0),
          },
          reason: note || decision,
          source: "cloud_function",
          relatedDocumentReference: accessRef.path,
          createdAt: now,
          created_at: now,
        });
        for (const recruitment of recruitments.docs) {
          transaction.set(recruitment.ref, {
            status: decisionPlan.recruitmentStatus,
            accessApplicationStatus: status,
            approvedRole: decisionPlan.assignRequestedRole ?
              requestedRoleId : recruitment.data()?.approvedRole || null,
            approvedBy: actorId,
            approvedAt: now,
            updatedAt: now,
          }, {merge: true});
        }
        return {requestedRoleId, claimsVersion};
      });
      return {userId: uid, status, claimsVersion: result.claimsVersion};
    },
);

exports.withdrawAccessApplication = onCall(
    {region: REGION},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) throw new HttpsError("unauthenticated", "Sign in first.");
      const db = getFirestore();
      const userRef = db.collection("users").doc(uid);
      const accessRef = db.collection("accessRequests").doc(uid);
      await db.runTransaction(async (transaction) => {
        const [userSnapshot, accessSnapshot] = await Promise.all([
          transaction.get(userRef),
          transaction.get(accessRef),
        ]);
        if (!userSnapshot.exists || !accessSnapshot.exists) {
          throw new HttpsError("not-found", "Access application not found.");
        }
        const access = accessSnapshot.data() || {};
        const ownerId = text(access.user_id || access.userId || accessSnapshot.id);
        if (ownerId !== uid) {
          throw new HttpsError("permission-denied", "Access denied.");
        }
        assertPendingAccessApplication(access);
        const now = FieldValue.serverTimestamp();
        transaction.set(accessRef, {
          status: ACCESS_STATUS.WITHDRAWN,
          accessStatus: ACCESS_STATUS.WITHDRAWN,
          access_status: ACCESS_STATUS.WITHDRAWN,
          withdrawnBy: uid,
          withdrawn_by: uid,
          withdrawnAt: now,
          withdrawn_at: now,
          updatedAt: now,
          updated_at: now,
        }, {merge: true});
        transaction.set(userRef, {
          accessStatus: ACCESS_STATUS.WITHDRAWN,
          access_status: ACCESS_STATUS.WITHDRAWN,
          accessUpdatedAt: now,
          access_updated_at: now,
          updated_at: now,
        }, {merge: true});
      });
      await db.collection("admin_audit_logs").add({
        action: "access_withdraw",
        module: "client_access",
        actor_id: uid,
        target_id: uid,
        created_at: FieldValue.serverTimestamp(),
      });
      logger.info("Access application withdrawn", {uid});
      return {userId: uid, status: ACCESS_STATUS.WITHDRAWN};
    },
);

exports.ensureWeeklyRecruitmentReferralCode = onCall(
    {region: REGION},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) {
        throw new HttpsError(
            "unauthenticated",
            "Sign in before loading your referral code.",
        );
      }
      const db = getFirestore();
      const userSnapshot = await db.collection("users").doc(uid).get();
      if (!userSnapshot.exists) {
        throw new HttpsError("failed-precondition", "User profile not found.");
      }
      const user = userSnapshot.data() || {};
      const roleId = Number(user.role_id || 0);
      const recruitmentRoleId = Number(
          request.data?.recruitmentRoleId || ROLE_IDS.trader,
      );
      if (!canAssignRole(roleId, recruitmentRoleId)) {
        throw new HttpsError(
            "permission-denied",
            "Your role cannot recruit this user role.",
        );
      }

      const window = weeklyWindow(new Date());
      const code = await uniqueReferralCode(db);
      const key = roleKey(recruitmentRoleId);
      return db.runTransaction(async (transaction) => {
        const userRef = db.collection("users").doc(uid);
        const freshUserSnapshot = await transaction.get(userRef);
        const freshUser = freshUserSnapshot.data() || {};
        const current = freshUser.weekly_referral_codes?.[key] || {};
        const existingCode = text(current.code);
        if (text(current.week_key) === window.key && existingCode) {
          const existingRef = db.collection("referral_codes")
              .doc(existingCode.toLowerCase());
          const existingSnapshot = await transaction.get(existingRef);
          if (existingSnapshot.exists &&
              existingSnapshot.data()?.is_active !== false) {
            return {
              code: existingCode,
              week: window.key,
              expiresAt: window.expiresAt.toISOString(),
              recruitmentRoleId,
            };
          }
        }

        const newRef = db.collection("referral_codes")
            .doc(code.toLowerCase());
        const newSnapshot = await transaction.get(newRef);
        if (newSnapshot.exists) {
          throw new HttpsError("aborted", "Referral code collision. Retry.");
        }
        if (existingCode) {
          transaction.set(
              db.collection("referral_codes").doc(existingCode.toLowerCase()),
              {
                is_active: false,
                status: "expired",
                replaced_by_code_id: code.toLowerCase(),
                updated_at: FieldValue.serverTimestamp(),
              },
              {merge: true},
          );
        }
        const now = FieldValue.serverTimestamp();
        transaction.create(newRef, {
          code,
          code_normalized: code.toLowerCase(),
          user_id: uid,
          ownerUid: uid,
          owner_user_id: uid,
          owner_role: roleId,
          recruitment_role_id: recruitmentRoleId,
          recruitment_role: key,
          default_role_ids: [recruitmentRoleId],
          allowed_registration_role_ids: [recruitmentRoleId],
          allowed_registration_types: ["registered_user"],
          requires_approval: !isPublicRegistrationRole(recruitmentRoleId),
          system_generated: true,
          created_by_uid: uid,
          code_type: WEEKLY_REFERRAL_TYPE,
          allowed_purpose: NORMAL_REFERRAL_PURPOSE,
          registration_role_id: recruitmentRoleId,
          is_active: true,
          status: "active",
          week_key: window.key,
          valid_from: now,
          valid_until: window.expiresAt,
          expires_at: window.expiresAt,
          usage_count: 0,
          maximum_usage: 1,
          created_at: now,
          updated_at: now,
        });
        transaction.update(userRef, {
          [`weekly_referral_codes.${key}`]: {
            code,
            week_key: window.key,
            expires_at: window.expiresAt,
            recruitment_role_id: recruitmentRoleId,
          },
          ...(recruitmentRoleId === ROLE_IDS.trader ? {
            weekly_referral_code: code,
            weekly_referral_week: window.key,
            weekly_referral_expires_at: window.expiresAt,
          } : {}),
          updated_at: now,
        });
        return {
          code,
          week: window.key,
          expiresAt: window.expiresAt.toISOString(),
          recruitmentRoleId,
        };
      });
    },
);

exports.adminUpdateUserAccessControl = onCall(
    {region: REGION},
    async (request) => {
      const actorId = request.auth?.uid;
      if (!actorId) throw new HttpsError("unauthenticated", "Sign in first.");
      const targetUserId = text(request.data?.targetUserId);
      const reason = text(request.data?.reason);
      const displayName = text(request.data?.name);
      const requestedStatus = Number(request.data?.status ?? 1) === 1 ? 1 : 0;
      const requestedRoleIds = [...new Set(
          (Array.isArray(request.data?.roleIds) ? request.data.roleIds : [])
              .map(Number).filter((value) => Number.isInteger(value) && value > 0),
      )];
      if (!targetUserId || !reason || !requestedRoleIds.length) {
        throw new HttpsError(
            "invalid-argument",
            "Target user, roles, and an audit reason are required.",
        );
      }
      const overrides = request.data?.permissionOverrides;
      if (overrides != null &&
          (typeof overrides !== "object" || Array.isArray(overrides) ||
            Object.values(overrides).some((value) => typeof value !== "boolean"))) {
        throw new HttpsError("invalid-argument", "Invalid permission overrides.");
      }

      const db = getFirestore();
      const actorRef = db.collection("users").doc(actorId);
      const targetRef = db.collection("users").doc(targetUserId);
      const auditRef = db.collection("admin_audit_logs").doc();
      const result = await db.runTransaction(async (transaction) => {
        const [actorSnapshot, targetSnapshot] = await Promise.all([
          transaction.get(actorRef), transaction.get(targetRef),
        ]);
        if (!actorSnapshot.exists || !targetSnapshot.exists) {
          throw new HttpsError("not-found", "User account not found.");
        }
        const actor = actorSnapshot.data() || {};
        const target = targetSnapshot.data() || {};
        const actorRoles = effectiveRoleIds(actor);
        if (!hasPermission(actor, "users.updateBasic")) {
          throw new HttpsError(
              "permission-denied",
              "User administration permission denied.",
          );
        }
        const targetRoles = effectiveRoleIds(target);
        if ([...targetRoles].some(isPrivilegedRole) &&
            !actorRoles.has(ROLE_IDS.superAdmin)) {
          throw new HttpsError(
              "permission-denied",
              "Only a Super Administrator can manage a privileged account.",
          );
        }
        const containsPrivileged = requestedRoleIds.some(isPrivilegedRole);
        if (containsPrivileged && !actorRoles.has(ROLE_IDS.superAdmin)) {
          throw new HttpsError(
              "permission-denied",
              "Only a Super Administrator can assign privileged roles.",
          );
        }
        if (!containsPrivileged &&
            !hasPermission(actor, "users.assignNonPrivilegedRole")) {
          throw new HttpsError("permission-denied", "Role assignment denied.");
        }
        if (requestedRoleIds.some((roleId) =>
          ![...actorRoles].some((actorRole) => canAssignRole(actorRole, roleId)))) {
          throw new HttpsError("permission-denied", "Role assignment denied.");
        }
        const previousRoles = [...targetRoles];
        if (displayName && !hasPermission(actor, "users.updateBasic")) {
          throw new HttpsError("permission-denied", "Basic profile update denied.");
        }
        if (requestedStatus !== Number(target.status ?? 1) &&
            !hasPermission(actor, "users.suspend")) {
          throw new HttpsError("permission-denied", "Account status update denied.");
        }
        const claimsVersion = Number(target.claims_version || 0) + 1;
        const primaryRoleId = requestedRoleIds[0];
        const nextName = displayName || text(target.name);
        transaction.set(targetRef, {
          role_id: primaryRoleId,
          role_ids: requestedRoleIds,
          role_name: roleName(primaryRoleId),
          is_admin: requestedRoleIds.some(isAdminRole),
          permission_overrides: overrides || {},
          ...(displayName ? {
            name: displayName,
            name_lower: displayName.toLowerCase(),
          } : {}),
          status: requestedStatus,
          account_status: requestedStatus === 1 ? "active" : "suspended",
          is_suspended: requestedStatus !== 1,
          ...(requestedStatus !== 1 ? {
            suspended_by: actorId,
            suspended_at: FieldValue.serverTimestamp(),
          } : {
            suspended_by: FieldValue.delete(),
            suspended_at: FieldValue.delete(),
          }),
          claims_version: claimsVersion,
          claims_sync_pending: true,
          admin_search_terms: buildAdminSearchTerms({
            ...target,
            id: targetUserId,
            name: nextName,
            role_id: primaryRoleId,
            role_ids: requestedRoleIds,
            role_name: roleName(primaryRoleId),
          }),
          updated_at: FieldValue.serverTimestamp(),
        }, {merge: true});
        transaction.set(auditRef, {
          actorUserId: actorId,
          targetUserId,
          action: "roles_and_permissions_updated",
          resourceType: "user_access_control",
          resourceId: targetUserId,
          before: {
            roleIds: previousRoles,
            permissionOverrides: target.permission_overrides || {},
            name: text(target.name),
            status: Number(target.status ?? 1),
            accountStatus: text(target.account_status, "active"),
          },
          after: {
            roleIds: requestedRoleIds,
            permissionOverrides: overrides || {},
            name: displayName || target.name,
            status: requestedStatus,
          },
          reason,
          actorRoleIds: [...actorRoles],
          actorRole: roleName([...actorRoles][0]),
          source: "cloud_function",
          createdAt: FieldValue.serverTimestamp(),
          created_at: FieldValue.serverTimestamp(),
        });
        return {
          primaryRoleId,
          claimsVersion,
          accessStatus: normalizeAccessStatus(
              target.access_status || target.accessStatus,
          ),
          organisationId: text(target.organisation_id),
        };
      });
      let claimsSyncPending = false;
      try {
        await getAuth().setCustomUserClaims(targetUserId, {
          roleIds: requestedRoleIds,
          accessStatus: result.accessStatus,
          organisationId: result.organisationId,
          claimsVersion: result.claimsVersion,
        });
        try {
          await targetRef.set({
            claims_sync_pending: false,
            claims_synced_version: result.claimsVersion,
            claims_synced_at: FieldValue.serverTimestamp(),
            claims_sync_error_code: FieldValue.delete(),
          }, {merge: true});
        } catch (error) {
          // Claims are already authoritative in Auth. A stale pending marker is
          // safe and can be reconciled without reporting the user update as a
          // failure after its Firestore transaction has committed.
          console.error("Unable to acknowledge custom-claims sync", error);
        }
      } catch (error) {
        claimsSyncPending = true;
        console.error("Custom-claims sync deferred", error);
        try {
          await targetRef.set({
            claims_sync_pending: true,
            claims_sync_error_code: text(error?.code, "unknown").slice(0, 100),
            claims_sync_last_attempt_at: FieldValue.serverTimestamp(),
          }, {merge: true});
        } catch (markerError) {
          console.error("Unable to persist deferred claims state", markerError);
        }
      }
      return {
        success: true,
        ...result,
        roleIds: requestedRoleIds,
        claimsSyncPending,
      };
    },
);

exports.adminAddUserNote = onCall(
    {region: REGION},
    async (request) => {
      const actorId = request.auth?.uid;
      if (!actorId) throw new HttpsError("unauthenticated", "Sign in first.");
      const targetUserId = text(request.data?.targetUserId);
      const note = text(request.data?.note).slice(0, 2000);
      if (!targetUserId || !note) {
        throw new HttpsError(
            "invalid-argument",
            "Target user and note are required.",
        );
      }
      const db = getFirestore();
      const actorRef = db.collection("users").doc(actorId);
      const targetRef = db.collection("users").doc(targetUserId);
      const noteRef = targetRef.collection("admin_notes").doc();
      const auditRef = db.collection("admin_audit_logs").doc();
      await db.runTransaction(async (transaction) => {
        const [actorSnapshot, targetSnapshot] = await Promise.all([
          transaction.get(actorRef),
          transaction.get(targetRef),
        ]);
        if (!actorSnapshot.exists || !targetSnapshot.exists) {
          throw new HttpsError("not-found", "User account not found.");
        }
        const actor = actorSnapshot.data() || {};
        if (!hasPermission(actor, "users.updateBasic")) {
          throw new HttpsError(
              "permission-denied",
              "Administrative note permission denied.",
          );
        }
        const payload = {
          note,
          created_by: actorId,
          created_by_name: text(actor.name || actor.email),
          created_by_role_ids: [...effectiveRoleIds(actor)],
          source: "user_management",
          created_at: FieldValue.serverTimestamp(),
        };
        transaction.create(noteRef, payload);
        transaction.create(auditRef, {
          actorUserId: actorId,
          actorName: text(actor.name || actor.email),
          targetUserId,
          action: "internal_note_added",
          resourceType: "user",
          resourceId: targetUserId,
          reason: note.slice(0, 300),
          source: "cloud_function",
          relatedDocumentReference: noteRef.path,
          createdAt: FieldValue.serverTimestamp(),
          created_at: FieldValue.serverTimestamp(),
        });
      });
      return {success: true, noteId: noteRef.id};
    },
);

exports.syncPendingUserClaims = onDocumentUpdated(
    {
      document: "users/{userId}",
      region: REGION,
      retry: true,
    },
    async (event) => {
      const profile = event.data?.after.data();
      if (!profile || profile.claims_sync_pending !== true) return;
      const claimsVersion = Number(profile.claims_version || 0);
      if (Number(profile.claims_synced_version || 0) >= claimsVersion) {
        await event.data.after.ref.set({
          claims_sync_pending: false,
          claims_sync_error_code: FieldValue.delete(),
        }, {merge: true});
        return;
      }
      const roleIds = [...effectiveRoleIds(profile)].sort((a, b) => a - b);
      try {
        await getAuth().setCustomUserClaims(event.params.userId, {
          roleIds,
          accessStatus: normalizeAccessStatus(
              profile.access_status || profile.accessStatus,
          ),
          organisationId: text(profile.organisation_id),
          claimsVersion,
        });
        await event.data.after.ref.set({
          claims_sync_pending: false,
          claims_synced_version: claimsVersion,
          claims_synced_at: FieldValue.serverTimestamp(),
          claims_sync_error_code: FieldValue.delete(),
        }, {merge: true});
      } catch (error) {
        if (error?.code === "auth/user-not-found") {
          await event.data.after.ref.set({
            claims_sync_pending: false,
            claims_sync_error_code: "auth/user-not-found",
            claims_sync_last_attempt_at: FieldValue.serverTimestamp(),
          }, {merge: true});
          return;
        }
        logger.error("Deferred custom-claims sync failed", {
          userId: event.params.userId,
          code: text(error?.code, "unknown"),
        });
        throw error;
      }
    },
);

function assertRecentAuthentication(request) {
  const authTime = Number(request.auth?.token?.auth_time || 0);
  const ageSeconds = Math.floor(Date.now() / 1000) - authTime;
  if (!authTime || ageSeconds > 5 * 60) {
    throw new HttpsError(
        "failed-precondition",
        "Please verify your password again before deleting your account.",
    );
  }
}

async function deleteUserStorage(uid) {
  const bucket = getStorage().bucket();
  for (const prefix of [
    `users/${uid}/`,
    `profile_images/${uid}/`,
    `support_tickets/${uid}/`,
    `market_analyses/${uid}/`,
    `knowledge_centre/${uid}/`,
    `community_documents/${uid}/`,
    `recording_materials/${uid}/`,
    `admin_assets/${uid}/`,
  ]) {
    await bucket.deleteFiles({prefix, force: true});
  }

  // Legacy quiz screenshots place the UID in the middle of the path. Remove
  // both path-owned and metadata-owned objects without assuming a quiz ID.
  const [quizFiles] = await bucket.getFiles({prefix: "quiz_submissions/"});
  await Promise.all(quizFiles
      .filter((file) => {
        const parts = file.name.split("/");
        return parts[2] === uid || text(file.metadata?.metadata?.ownerUid) === uid;
      })
      .map((file) => file.delete({ignoreNotFound: true})));

  // A staff member can upload into another user's support folder. Inspect
  // managed areas for custom uploader metadata so those files are not missed
  // merely because the path owner differs from the uploader.
  for (const prefix of [
    "profile_images/",
    "support_tickets/",
    "market_analyses/",
    "knowledge_centre/",
    "community_documents/",
    "recording_materials/",
    "admin_assets/",
  ]) {
    const [files] = await bucket.getFiles({prefix});
    await Promise.all(files
        .filter((file) => {
          const metadata = file.metadata?.metadata || {};
          return text(metadata.uploaded_by) === uid ||
            text(metadata.owner_uid) === uid;
        })
        .map((file) => file.delete({ignoreNotFound: true})));
  }
}

function hashedDeletionReference(uid) {
  const salt = text(
      process.env.DELETION_AUDIT_PEPPER ||
      process.env.GCLOUD_PROJECT ||
      process.env.GOOGLE_CLOUD_PROJECT,
      "hc-trader-account-deletion",
  );
  return crypto.createHash("sha256").update(`${salt}:${uid}`)
      .digest("hex");
}

function safeDeletionError(error) {
  return text(error?.code, "unknown").replace(/[^a-zA-Z0-9_\-/]/g, "")
      .slice(0, 120) || "unknown";
}

async function cleanupReferralParticipation(db, uid, deletionReference) {
  const usernameSnapshots = await Promise.all([
    db.collection("usernames").where("uid", "==", uid).get(),
    db.collection("usernames").where("user_id", "==", uid).get(),
  ]);
  for (const snapshot of usernameSnapshots) {
    for (const document of snapshot.docs) await document.ref.delete();
  }

  await disableReferralCodes(db, uid, deletionReference);

  // Delete the user's own parent relationship and every denormalized downline
  // membership. Relationships belonging to their downlines are retained but
  // explicitly marked unavailable so the referral tree is never reassigned.
  await deleteOwnedQuery(
      db,
      db.collectionGroup("downlines").where("user_id", "==", uid),
  );
  await db.collection("referral_relationships").doc(uid).delete();

  const affected = new Map();
  for (const field of ["referrer_id", "upline_id"]) {
    const snapshot = await db.collection("referral_relationships")
        .where(field, "==", uid).get();
    for (const document of snapshot.docs) affected.set(document.id, document);
  }
  for (const document of affected.values()) {
    await document.ref.set({
      relationship_status: "upline_unavailable",
      upline_unavailable: true,
      deleted_upline_reference: deletionReference,
      referrer_id: FieldValue.delete(),
      upline_id: FieldValue.delete(),
      upline_name: FieldValue.delete(),
      referrer_name: FieldValue.delete(),
      updated_at: FieldValue.serverTimestamp(),
    }, {merge: true});
  }
}

async function cleanupGlobalPrivateData(db, uid, deletionReference) {
  await db.collection("accessRequests").doc(uid).delete();
  for (const [collection, field, recursive] of [
    ["mt5_sync_jobs", "user_id", false],
    ["support_tickets", "user_id", true],
    ["delete_requests", "requester_id", false],
    ["recruitments", "recruitedUserId", false],
  ]) {
    await deleteOwnedQuery(
        db,
        db.collection(collection).where(field, "==", uid),
        {recursive},
    );
  }

  await cleanupAnalystFollows(db, uid);
  await deleteOwnedQuery(
      db,
      db.collectionGroup("notifications").where("follower_id", "==", uid),
  );
  await deleteOwnedQuery(
      db,
      db.collectionGroup("notifications").where("user_id", "==", uid),
  );

  const directAnnouncements = await db.collection("announcements")
      .where("target_user_ids", "array-contains", uid).get();
  await Promise.all(directAnnouncements.docs.map((announcement) =>
    announcement.ref.set({
      target_user_ids: FieldValue.arrayRemove(uid),
      updated_at: FieldValue.serverTimestamp(),
    }, {merge: true})));

  await anonymizeSupportMessages(db, uid, deletionReference);
  await anonymizeAppointmentSlots(db, uid, deletionReference);
  await anonymizeAuditHistory(db, uid, deletionReference);

  for (const path of [
    db.collection("market_analyst_profiles").doc(uid),
    db.collection("market_analysts").doc(uid),
    db.collection("appointment_hosts").doc(uid),
  ]) {
    if ((await path.get()).exists) await db.recursiveDelete(path);
  }

  // Announcement read markers use the UID as a document ID and have no owner
  // field, so remove them by their known deterministic path.
  const announcements = await db.collection("announcements").get();
  await Promise.all(announcements.docs.map((announcement) =>
    announcement.ref.collection("reads").doc(uid).delete()));
}

async function anonymizeSharedUserData(db, uid) {
  await anonymizeSharedQuery(db, db.collection("trading_appointments")
      .where("participant_ids", "array-contains", uid), uid);
  for (const collection of [
    "trading_signals",
    "market_analyses",
    "trading_blogs",
    "trading_resources",
    "trading_recordings",
    "community_documents",
    "trading_news",
    "trading_competitions",
    "announcements",
    "knowledge_centres",
    "knowledgeCentres",
    "final_exam_questions",
    "final_exam_question_banks",
    "trading_exam_questions",
    "prop_firm_models",
  ]) {
    await anonymizeSharedQuery(
        db,
        db.collection(collection).where("created_by", "==", uid),
        uid,
    );
  }
  await anonymizeSharedQuery(
      db,
      db.collection("recruitments").where("recruitedByUid", "==", uid),
      uid,
  );
}

async function deleteOwnedQuery(db, query, {recursive = false} = {}) {
  const snapshot = await query.get();
  for (const document of snapshot.docs) {
    if (recursive) {
      await db.recursiveDelete(document.ref);
    } else {
      await document.ref.delete();
    }
  }
}

async function disableReferralCodes(db, uid, deletionReference) {
  const snapshots = await Promise.all([
    db.collection("referral_codes").where("owner_user_id", "==", uid).get(),
    db.collection("referral_codes").where("user_id", "==", uid).get(),
  ]);
  const documents = new Map();
  for (const snapshot of snapshots) {
    for (const document of snapshot.docs) documents.set(document.id, document);
  }
  for (const document of documents.values()) {
    await document.ref.set({
      is_active: false,
      status: "disabled",
      deleted_owner: true,
      deleted_owner_reference: deletionReference,
      owner_user_id: FieldValue.delete(),
      user_id: FieldValue.delete(),
      owner_display_name: FieldValue.delete(),
      owner_email: FieldValue.delete(),
      deleted_at: FieldValue.serverTimestamp(),
      updated_at: FieldValue.serverTimestamp(),
    }, {merge: true});
  }
}

async function anonymizeSharedQuery(db, query, uid) {
  const snapshot = await query.get();
  for (const document of snapshot.docs) {
    const data = document.data() || {};
    const update = {
      owner_deleted: true,
      deleted_user_reference: "deleted-user",
      updated_at: FieldValue.serverTimestamp(),
    };
    if (Array.isArray(data.participant_ids)) {
      update.participant_ids = FieldValue.arrayRemove(uid);
      update.deleted_participant_count = FieldValue.increment(1);
    }
    if (text(data.requester_id) === uid) update.requester_id = "deleted-user";
    if (text(data.created_by) === uid) update.created_by = "deleted-user";
    if (text(data.recruitedByUid) === uid) {
      update.recruitedByUid = "deleted-user";
      update.recruitedByRole = FieldValue.delete();
    }
    for (const key of [
      "host_user_id", "published_by", "updated_by", "reviewed_by",
      "cancelled_by", "rejected_by", "booked_by", "assigned_admin_id",
      "closed_by", "reopened_by",
    ]) {
      if (text(data[key]) === uid) update[key] = "deleted-user";
    }
    for (const key of [
      "creator_name", "created_by_name", "owner_name", "owner_email",
      "requester_name", "requester_email", "host_name", "recruitedByName",
      "publisher_name", "author_name", "actor_name", "actor_email",
    ]) {
      if (key in data) update[key] = FieldValue.delete();
    }
    await document.ref.set(update, {merge: true});
  }
}

async function cleanupAnalystFollows(db, uid) {
  const snapshot = await db.collectionGroup("followers")
      .where("follower_id", "==", uid).get();
  const analysts = new Set();
  for (const document of snapshot.docs) {
    const analystId = text(document.data()?.analyst_id);
    if (analystId) analysts.add(analystId);
    await document.ref.delete();
  }
  for (const analystId of analysts) {
    const profileRef = db.collection("market_analyst_profiles").doc(analystId);
    await db.runTransaction(async (transaction) => {
      const profile = await transaction.get(profileRef);
      if (!profile.exists) return;
      transaction.set(profileRef, {
        follower_count: Math.max(
            0,
            Number(profile.data()?.follower_count || 0) - 1,
        ),
        updated_at: FieldValue.serverTimestamp(),
      }, {merge: true});
    });
  }
}

async function anonymizeSupportMessages(db, uid, deletionReference) {
  const snapshot = await db.collectionGroup("messages")
      .where("user_id", "==", uid).get();
  for (const document of snapshot.docs) {
    await document.ref.set({
      user_id: "deleted-user",
      sender_name: FieldValue.delete(),
      message: "[Removed after account deletion]",
      attachments: [],
      deleted_user_reference: deletionReference,
      owner_deleted: true,
      updated_at: FieldValue.serverTimestamp(),
    }, {merge: true});
  }
}

async function anonymizeAppointmentSlots(db, uid, deletionReference) {
  const documents = new Map();
  for (const field of ["host_user_id", "created_by", "booked_by"]) {
    const snapshot = await db.collection("trading_appointment_slots")
        .where(field, "==", uid).get();
    for (const document of snapshot.docs) documents.set(document.id, document);
  }
  for (const document of documents.values()) {
    const data = document.data() || {};
    const update = {
      owner_deleted: true,
      deleted_user_reference: deletionReference,
      updated_at: FieldValue.serverTimestamp(),
    };
    if (text(data.host_user_id) === uid) {
      update.host_user_id = "deleted-user";
      update.host_name = FieldValue.delete();
      update.status = "inactive";
    }
    if (text(data.created_by) === uid) update.created_by = "deleted-user";
    if (text(data.booked_by) === uid) {
      update.booked_by = FieldValue.delete();
      update.appointment_id = FieldValue.delete();
      update.status = "inactive";
    }
    await document.ref.set(update, {merge: true});
  }
}

async function anonymizeAuditHistory(db, uid, deletionReference) {
  for (const collectionName of ["audit_logs", "admin_audit_logs"]) {
    const documents = new Map();
    for (const field of ["actor_id", "target_id"]) {
      const snapshot = await db.collection(collectionName)
          .where(field, "==", uid).get();
      for (const document of snapshot.docs) {
        documents.set(document.id, document);
      }
    }
    for (const document of documents.values()) {
      const data = document.data() || {};
      const update = {
        anonymized_for_account_deletion: true,
        updated_at: FieldValue.serverTimestamp(),
      };
      if (text(data.actor_id) === uid) {
        update.actor_id = deletionReference;
        update.actor_reference = deletionReference;
        update.actor_name = FieldValue.delete();
        update.actor_email = FieldValue.delete();
      }
      if (text(data.target_id) === uid) {
        update.target_id = deletionReference;
        update.target_reference = deletionReference;
        update.target_name = FieldValue.delete();
        update.email = FieldValue.delete();
        update.old_values = FieldValue.delete();
        update.new_values = FieldValue.delete();
      }
      await document.ref.set(update, {merge: true});
    }
  }
}

function provisionalRoleId(requestedRoleId) {
  return Number(requestedRoleId) === ROLE_IDS.leadClient ?
    ROLE_IDS.leadClient : ROLE_IDS.trader;
}

function roleKey(roleId) {
  return {
    [ROLE_IDS.marketAnalyst]: "market_analyst",
    [ROLE_IDS.signalProvider]: "signal_provider",
    [ROLE_IDS.leader]: "leader",
    [ROLE_IDS.recruiter]: "recruiter",
    [ROLE_IDS.trader]: "trader",
    [ROLE_IDS.leadClient]: "lead_client",
    [ROLE_IDS.assistant]: "assistant",
  }[Number(roleId)] || "member";
}

async function resolveReferral(db, payload, registeringUid = "") {
  const referral = normalizeReferralCode(payload.referralCode);
  if (!referral) {
    return {
      requestedRoleId: publicRegistrationRoleOrDefault(payload.requestedRoleId),
      referrerId: null,
      referralCodeUsed: null,
      referralCodeType: null,
      createdByReferralType: null,
      uplineId: null,
      leaderId: null,
      recruiterId: null,
      teamId: null,
      organisationId: null,
      campaignId: null,
    };
  }

  const referralSnapshot = await findReferralSnapshot(db, referral);
  if (!referralSnapshot.exists) {
    throw new HttpsError(
        "invalid-argument",
        "Please enter a valid referral code.",
    );
  }
  const referralData = referralSnapshot.data() || {};
  assertReferralAvailable(referralData);
  const referralCodeType = text(referralData.code_type, "normal");
  const supportedTypes = [
    "normal",
    WEEKLY_REFERRAL_TYPE,
    "designated",
    "campaign",
    "partner",
    "leader",
    "admin",
  ];
  if (!supportedTypes.includes(referralCodeType)) {
    throw new HttpsError(
        "permission-denied",
        "This referral code cannot be used for public registration.",
    );
  }
  if (!allowsPublicRegistration(referralData)) {
    throw new HttpsError(
        "permission-denied",
        "This referral code cannot be used for public registration.",
    );
  }
  const allowedRegistrationTypes = Array.isArray(
      referralData.allowed_registration_types) ?
    referralData.allowed_registration_types.map((item) => text(item)) : [];
  if (allowedRegistrationTypes.length &&
      !allowedRegistrationTypes.includes(payload.registrationType)) {
    throw new HttpsError(
        "failed-precondition",
        "This referral code is not valid for this registration type.",
    );
  }
  const referrerId = text(
      referralData.owner_user_id ||
      referralData.user_id ||
      referralData.referrer_id ||
      referralData.owner_uid,
  );
  if (!referrerId) {
    throw new HttpsError(
        "failed-precondition",
        "This referral code is missing its owner.",
    );
  }
  if (registeringUid && referrerId === registeringUid) {
    throw new HttpsError(
        "failed-precondition",
        "You cannot use your own referral code.",
    );
  }
  const referrerSnapshot = await db.collection("users").doc(referrerId).get();
  assertEligibleReferrer(referrerSnapshot);
  const referrerData = referrerSnapshot.data() || {};
  const allowedRoleIds = Array.isArray(referralData.allowed_registration_role_ids) ?
    referralData.allowed_registration_role_ids.map(Number) : [];
  if (allowedRoleIds.length > 0 &&
      !allowedRoleIds.includes(Number(payload.requestedRoleId))) {
    throw new HttpsError(
        "failed-precondition",
        "This referral code is not eligible for the selected registration role.",
    );
  }
  const defaultRoleIds = Array.isArray(referralData.default_role_ids) ?
    referralData.default_role_ids.map(Number).filter(Number.isFinite) : [];
  if (defaultRoleIds.some(isPrivilegedRole)) {
    throw new HttpsError(
        "permission-denied",
        "This referral code cannot assign a privileged role.",
    );
  }
  const requestedRoleId = Number(
        referralData.recruitment_role_id ||
        referralData.registration_role_id ||
        defaultRoleIds[0] ||
        publicRegistrationRoleOrDefault(payload.requestedRoleId));
  if (isPrivilegedRole(requestedRoleId)) {
    throw new HttpsError(
        "permission-denied",
        "This referral code cannot assign a privileged role.",
    );
  }
  if (!isPublicRegistrationRole(requestedRoleId)) {
    const authorisedMapping = text(
        referralData.approved_by || referralData.approvedBy,
    ) && text(referralData.created_by || referralData.createdBy) &&
      referralData.requires_approval !== false &&
      referralData.requiresApproval !== false;
    const trustedWeeklyMapping = isTrustedWeeklyRoleMapping({
      referralData,
      referralCodeType,
      requestedRoleId,
      referrerId,
      referrerData,
    });
    if (!authorisedMapping && !trustedWeeklyMapping) {
      throw new HttpsError(
          "permission-denied",
          "This referral role mapping requires administrator approval.",
      );
    }
  }
  return {
    requestedRoleId,
    referrerId,
    referralCodeUsed: referral.toLowerCase(),
    referralDocumentId: referralSnapshot.id,
    referralCodeType,
    createdByReferralType: referralCodeType,
    uplineId: text(referralData.upline_user_id || referralData.uplineUserId),
    leaderId: text(referralData.leader_user_id || referralData.leaderUserId),
    recruiterId: text(
        referralData.recruiter_user_id || referralData.recruiterUserId,
    ),
    teamId: text(referralData.team_id || referralData.teamId),
    organisationId: text(
        referralData.organisation_id || referralData.organisationId,
    ),
    campaignId: text(referralData.campaign_id || referralData.campaignId),
  };
}

function isTrustedWeeklyRoleMapping({
  referralData = {},
  referralCodeType = "",
  requestedRoleId = 0,
  referrerId = "",
  referrerData = {},
}) {
  const mappedRoleId = Number(
      referralData.recruitment_role_id || referralData.registration_role_id,
  );
  const generatedBy = text(referralData.created_by_uid);
  return referralCodeType === WEEKLY_REFERRAL_TYPE &&
    generatedBy && generatedBy === referrerId &&
    mappedRoleId === Number(requestedRoleId) &&
    !isPrivilegedRole(requestedRoleId) &&
    [...effectiveRoleIds(referrerData)].some((roleId) =>
      canAssignRole(roleId, requestedRoleId));
}

async function findReferralSnapshot(db, normalizedCode) {
  const collection = db.collection("referral_codes");
  const direct = await collection.doc(normalizedCode).get();
  if (direct.exists) return direct;
  const candidates = [
    ["code_normalized", normalizedCode],
    ["normalized_code", normalizedCode],
    ["code_lowercase", normalizedCode],
    ["code", normalizedCode],
    ["code", normalizedCode.toUpperCase()],
  ];
  for (const [field, value] of candidates) {
    const snapshot = await collection.where(field, "==", value).limit(2).get();
    if (!snapshot.empty) {
      if (snapshot.size > 1) {
        logger.warn("Duplicate normalized referral code", {
          codeHash: crypto.createHash("sha256").update(normalizedCode)
              .digest("hex").slice(0, 12),
          field,
          count: snapshot.size,
        });
      }
      return snapshot.docs[0];
    }
  }
  return direct;
}

function allowsPublicRegistration(data) {
  const purpose = text(data.allowed_purpose, NORMAL_REFERRAL_PURPOSE);
  if ([NORMAL_REFERRAL_PURPOSE, "public_registration", "registration"]
      .includes(purpose)) return true;
  const purposes = Array.isArray(data.allowed_purposes) ?
    data.allowed_purposes.map((item) => text(item)) : [];
  return purposes.some((item) => [
    NORMAL_REFERRAL_PURPOSE,
    "public_registration",
    "registration",
  ].includes(item));
}

function referralAvailabilityError(data) {
  const status = text(data.status, "active").toLowerCase();
  if (data.is_active === false ||
      ["disabled", "inactive", "suspended", "deleted", "expired"]
          .includes(status)) return "This referral code is no longer active.";
  const validFrom = data.valid_from || data.validFrom;
  if (validFrom && timestampMillis(validFrom) > Date.now()) {
    return "This referral code is not active yet.";
  }
  if (expired(data.valid_until || data.expires_at)) {
    return "This referral code has expired.";
  }
  const usageCount = Number(data.current_uses ?? data.usage_count ?? 0);
  const maximumUsage = Number(data.max_uses ?? data.maximum_usage ?? 0);
  if (maximumUsage > 0 && usageCount >= maximumUsage) {
    return "This referral code has reached its usage limit.";
  }
  return "";
}

function assertReferralAvailable(data) {
  const message = referralAvailabilityError(data);
  if (message) throw new HttpsError("failed-precondition", message);
}

function assertPendingAccessApplication(data) {
  const status = normalizeAccessStatus(
      data?.status || data?.access_status || data?.accessStatus,
  );
  if (status !== ACCESS_STATUS.PENDING) {
    throw new HttpsError(
        "aborted",
        "This application has already been processed.",
    );
  }
}

function assertEligibleReferrer(snapshot) {
  if (!snapshot?.exists) {
    throw new HttpsError(
        "failed-precondition",
        "This referral account is unavailable.",
    );
  }
  const data = snapshot.data() || {};
  const accountStatus = text(data.account_status, "active").toLowerCase();
  if (Number(data.status ?? 1) === 0 || data.is_suspended === true ||
      ["inactive", "suspended", "deleted", "deletioninprogress"]
          .includes(accountStatus.replace(/[-_\s]/g, ""))) {
    throw new HttpsError(
        "failed-precondition",
        "This referral account is unavailable.",
    );
  }
}

function assertEligibleTeam(snapshot, teamId) {
  if (!teamId) return;
  if (!snapshot?.exists) {
    throw new HttpsError("failed-precondition", "This referral team is unavailable.");
  }
  const data = snapshot.data() || {};
  const status = text(data.status, "active").toLowerCase();
  if (data.is_suspended === true || ["suspended", "deleted", "inactive"]
      .includes(status)) {
    throw new HttpsError("failed-precondition", "This referral team is unavailable.");
  }
}

function normalizeRegistrationPayload(data) {
  return {
    name: text(data.name),
    username: text(data.username).toLowerCase(),
    email: text(data.email).toLowerCase(),
    requestedRoleId: Number(data.requestedRoleId || ROLE_IDS.trader),
    referralCode: normalizeReferralCode(data.referralCode),
    registrationType: text(
        data.registrationType || data.registration_type,
        "registered_user",
    ),
    campaignId: text(data.campaignId || data.campaign_id).slice(0, 120),
    registrationSource: text(
        data.registrationSource || data.registration_source,
        "direct",
    ).slice(0, 80),
    deviceInfo: sanitizeDeviceInfo(data.deviceInfo),
  };
}

async function assertFeatureWritable(db, key, now = Date.now()) {
  const snapshot = await db.collection("featureConfigurations").doc(key).get();
  if (snapshot.exists) {
    const configuration = snapshot.data() || {};
    const mode = text(configuration.mode, "enabled");
    const startsAt = timestampMillis(configuration.starts_at);
    const endsAt = timestampMillis(configuration.ends_at);
    const unavailable = ["disabled", "hidden", "maintenance", "readOnly"]
        .includes(mode) || Number(configuration.rollout_percentage ?? 100) <= 0 ||
      (startsAt > 0 && startsAt > now) || (endsAt > 0 && endsAt <= now);
    if (unavailable) {
      throw new HttpsError(
          "failed-precondition",
          "New registrations are temporarily unavailable.",
      );
    }
    return;
  }
  const legacy = await db.collection("app_configuration").doc("modules").get();
  if (legacy.data()?.[key] === false) {
    throw new HttpsError(
        "failed-precondition",
        "New registrations are temporarily unavailable.",
    );
  }
}

function normalizeAdminCreateUserPayload(data) {
  const email = text(data.email).toLowerCase();
  const username = text(
      data.username || email.split("@")[0],
  ).toLowerCase().replace(/[^a-z0-9_]/g, "_").replace(/_+/g, "_");
  const password = text(data.password);
  if (password.length < 8) {
    throw new HttpsError(
        "invalid-argument",
        "Use 8+ characters for the temporary password.",
    );
  }
  return {
    name: text(data.name),
    username,
    email,
    password,
    roleId: Number(data.roleId || data.role_id || ROLE_IDS.trader),
    status: Number(data.status || 1) === 1 ? 1 : 0,
    accessStatus: ACCESS_STATUS.NOT_SUBMITTED,
  };
}

function normalizeReferralCode(value) {
  return text(value).normalize("NFKC").replace(/\s+/g, "").toLowerCase();
}

function sanitizeDeviceInfo(value) {
  if (!value || typeof value !== "object" || Array.isArray(value)) return {};
  return Object.fromEntries(
      Object.entries(value)
          .slice(0, 20)
          .map(([key, item]) => [String(key).slice(0, 64), text(item).slice(0, 300)]),
  );
}

async function uniqueReferralCode(db) {
  for (let attempt = 0; attempt < 8; attempt++) {
    const code = randomReferralCode();
    const snapshot = await db.collection("referral_codes")
        .doc(code.toLowerCase())
        .get();
    if (!snapshot.exists) {
      return code;
    }
  }
  throw new HttpsError(
      "resource-exhausted",
      "Unable to allocate a referral code. Please try again.",
  );
}

function randomReferralCode() {
  const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  const bytes = crypto.randomBytes(8);
  return Array.from(bytes)
      .map((byte) => alphabet[byte % alphabet.length])
      .join("");
}

function weeklyWindow(now) {
  const start = new Date(Date.UTC(
      now.getUTCFullYear(),
      now.getUTCMonth(),
      now.getUTCDate(),
  ));
  const day = start.getUTCDay() || 7;
  start.setUTCDate(start.getUTCDate() - day + 1);
  const expiresAt = new Date(start);
  expiresAt.setUTCDate(expiresAt.getUTCDate() + 7);
  const thursday = new Date(start);
  thursday.setUTCDate(thursday.getUTCDate() + 3);
  const year = thursday.getUTCFullYear();
  const yearStart = new Date(Date.UTC(year, 0, 1));
  const week = Math.ceil(
      (((thursday - yearStart) / 86400000) + 1) / 7,
  );
  return {
    key: `${year}-W${String(week).padStart(2, "0")}`,
    startsAt: start,
    expiresAt,
  };
}

function serializeDeletedUser(doc) {
  const data = doc.data() || {};
  return {
    id: doc.id,
    user_id: text(data.user_id || doc.id),
    email: text(data.email),
    username: text(data.username),
    name: text(data.name || data.displayName || data.display_name),
    role_id: Number(data.role_id || data.previousRole || data.previous_role || 0),
    role_name: text(data.role_name || roleName(data.previousRole || data.previous_role)),
    previous_role: Number(
        data.previousRole || data.previous_role || data.role_id || 0,
    ),
    previous_access_status: text(
        data.previousAccessStatus || data.previous_access_status,
    ),
    deleted_by: text(data.deleted_by),
    deleted_by_type: text(data.deleted_by_type),
    deletion_source: text(data.deletionSource || data.deletion_source),
    deletion_status: text(
        data.deletionStatus || data.deletion_status,
        "completed",
    ),
    reason: text(data.reason),
    previous_account_status: text(data.previous_account_status),
    created_at: serializeDate(data.created_at),
    deleted_at: serializeDate(data.deleted_at || data.deletedAt),
  };
}

function serializeDate(value) {
  if (!value) return "";
  if (typeof value.toDate === "function") {
    return value.toDate().toISOString();
  }
  if (value instanceof Date) return value.toISOString();
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? "" : date.toISOString();
}

function text(value, fallback = "") {
  const resolved = value == null ? "" : String(value).trim();
  return resolved || fallback;
}

function buildAdminSearchTerms(profile = {}) {
  const values = [
    profile.id,
    profile.uid,
    profile.name,
    profile.display_name,
    profile.username,
    profile.email,
    profile.phone_number,
    profile.referral_code,
    profile.upline_name,
    profile.role_name,
    profile.main_mt5_login,
    profile.mt5_login,
    profile.verification_reference,
  ];
  const components = new Set();
  for (const raw of values) {
    const normalized = text(raw).toLowerCase()
        .normalize("NFKC").replace(/\s+/g, " ");
    if (!normalized) continue;
    components.add(normalized);
    normalized.split(/[^\p{L}\p{N}@.+_-]+/u)
        .filter(Boolean).forEach((component) => components.add(component));
  }
  // Seed every complete searchable value before generating partial tokens.
  // This prevents a long UID/name from exhausting the bounded token budget
  // before later fields such as referral code or MT5 login are indexed.
  const terms = new Set(components);
  const bounded = [...components].map((component) =>
    component.slice(0, 36));
  const maxTerms = 2000;
  for (let length = 2; length <= 15 && terms.size < maxTerms; length++) {
    const maxStarts = Math.max(0, ...bounded.map((value) =>
      value.length - length));
    for (let start = 0; start <= maxStarts && terms.size < maxTerms; start++) {
      for (const component of bounded) {
        if (start + length <= component.length) {
          terms.add(component.slice(start, start + length));
          if (terms.size >= maxTerms) break;
        }
      }
    }
  }
  return [...terms];
}

function expired(value) {
  if (!value) return false;
  if (typeof value.toMillis === "function") {
    return value.toMillis() < Date.now();
  }
  const date = new Date(value);
  return !Number.isNaN(date.getTime()) && date.getTime() < Date.now();
}

function timestampMillis(value) {
  if (value && typeof value.toMillis === "function") return value.toMillis();
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 0 : date.getTime();
}

module.exports._test = {
  ACCESS_STATUS,
  NORMAL_REFERRAL_PURPOSE,
  allowsPublicRegistration,
  accessDecisionPlan,
  findReferralSnapshot,
  normalizeAccessStatus,
  referralAvailabilityError,
  assertEligibleReferrer,
  assertEligibleTeam,
  assertPendingAccessApplication,
  normalizeRegistrationPayload,
  assertFeatureWritable,
  normalizeReferralCode,
  randomReferralCode,
  resolveReferral,
  isTrustedWeeklyRoleMapping,
  sanitizeDeviceInfo,
  serializeDate,
  serializeDeletedUser,
  expired,
  weeklyWindow,
  provisionalRoleId,
  roleKey,
  hashedDeletionReference,
  safeDeletionError,
  buildAdminSearchTerms,
};
