"use strict";

const assert = require("assert");
const {
  ROLE_IDS,
  adminPermissionsForRole,
  canAssignRole,
  publicRegistrationRoleOrDefault,
} = require("../role_permissions");
const registrationFunctions = require("../registration_functions");
const {_test} = registrationFunctions;

async function run() {
  assert.equal(canAssignRole(ROLE_IDS.superAdmin, ROLE_IDS.superAdmin), true);
  assert.equal(canAssignRole(ROLE_IDS.superAdmin, ROLE_IDS.assistant), true);
  assert.equal(canAssignRole(ROLE_IDS.subAdmin, ROLE_IDS.superAdmin), false);
  assert.equal(canAssignRole(ROLE_IDS.subAdmin, ROLE_IDS.marketAnalyst), true);
  assert.equal(canAssignRole(ROLE_IDS.leader, ROLE_IDS.recruiter), true);
  assert.equal(canAssignRole(ROLE_IDS.marketAnalyst, ROLE_IDS.trader), true);
  assert.equal(canAssignRole(ROLE_IDS.recruiter, ROLE_IDS.subAdmin), false);

  const permissions = adminPermissionsForRole(ROLE_IDS.subAdmin);
  assert.equal(permissions.canAccessAdminDashboard, true);
  assert.equal(permissions.canInviteSuperAdmin, false);
  assert.equal(permissions.canInviteMarketAnalyst, true);

  assert.equal(
      publicRegistrationRoleOrDefault(ROLE_IDS.superAdmin),
      ROLE_IDS.trader,
  );
  assert.equal(
      publicRegistrationRoleOrDefault(ROLE_IDS.leadClient),
      ROLE_IDS.leadClient,
  );

  const payload = _test.normalizeRegistrationPayload({
    name: " Test User ",
    username: "Test_User",
    email: "TEST@EXAMPLE.COM",
    requestedRoleId: ROLE_IDS.superAdmin,
    referralCode: " ABC123 ",
    campaignId: " QR-July ",
    registrationSource: " qr ",
    deviceInfo: {platform: "android", long: "x".repeat(400)},
  });
  assert.equal(payload.username, "test_user");
  assert.equal(payload.email, "test@example.com");
  assert.equal(payload.referralCode, "abc123");
  assert.equal(payload.campaignId, "QR-July");
  assert.equal(payload.registrationSource, "qr");
  assert.equal(payload.deviceInfo.long.length, 300);
  assert.equal(_test.normalizeAccessStatus("pending_review"), "pendingReview");
  assert.equal(_test.normalizeAccessStatus("withdrawn"), "withdrawn");
  assert.equal(
      _test.normalizeAccessStatus("more_info_required"),
      "moreInfoRequired",
  );
  assert.equal(_test.normalizeAccessStatus("revoked"), "revoked");
  assert.deepEqual(_test.accessDecisionPlan("request_info"), {
    status: "moreInfoRequired",
    allowedStatuses: ["pendingReview"],
    permission: "accessApplication.reject",
    recruitmentStatus: "more_info_required",
    assignRequestedRole: false,
  });
  assert.deepEqual(_test.accessDecisionPlan("revoke").allowedStatuses, [
    "approved",
  ]);
  assert.deepEqual(_test.accessDecisionPlan("restore").allowedStatuses, [
    "revoked",
  ]);
  assert.equal(_test.accessDecisionPlan("unknown"), null);
  assert.doesNotThrow(() => _test.assertPendingAccessApplication({
    status: "pending_review",
  }));
  for (const status of [
    "approved", "rejected", "moreInfoRequired", "revoked", "withdrawn",
    "cancelled",
  ]) {
    assert.throws(
        () => _test.assertPendingAccessApplication({status}),
        /already been processed/i,
    );
  }

  assert.equal(_test.referralAvailabilityError({is_active: false}),
      "This referral code is no longer active.");
  assert.equal(_test.referralAvailabilityError({
    expires_at: "2020-01-01T00:00:00Z",
  }), "This referral code has expired.");
  assert.equal(_test.referralAvailabilityError({
    usage_count: 3,
    maximum_usage: 3,
  }), "This referral code has reached its usage limit.");
  assert.equal(_test.referralAvailabilityError({
    is_active: true,
    usage_count: 2,
    maximum_usage: 3,
  }), "");
  assert.equal(_test.allowsPublicRegistration({
    allowed_purpose: "public_registration",
  }), true);
  assert.doesNotThrow(() => _test.assertEligibleReferrer({
    exists: true,
    data: () => ({status: 1, account_status: "active"}),
  }));
  assert.throws(() => _test.assertEligibleReferrer({
    exists: true,
    data: () => ({status: 1, is_suspended: true}),
  }), /referral account is unavailable/i);

  const designated = await _test.resolveReferral(
      fakeReferralDb({
        id: "campaign-document-id",
        code: "DESIGNATED-01",
        code_type: "designated",
        allowed_purpose: "public_registration",
        owner_user_id: "leader-1",
        is_active: true,
      }),
      {
        requestedRoleId: ROLE_IDS.trader,
        referralCode: " designated-01 ",
      },
      "new-user",
  );
  assert.equal(designated.referrerId, "leader-1");
  assert.equal(designated.referralDocumentId, "campaign-document-id");

  const mapped = await _test.resolveReferral(
      fakeReferralDb({
        id: "mapped-code-id",
        code: "MAPPED-01",
        code_type: "campaign",
        allowed_purpose: "public_registration",
        owner_user_id: "leader-1",
        default_role_ids: [ROLE_IDS.signalProvider],
        requires_approval: true,
        created_by: "admin-1",
        approved_by: "super-admin-1",
        team_id: "team-a",
        organisation_id: "org-a",
        recruiter_user_id: "recruiter-a",
      }),
      {
        requestedRoleId: ROLE_IDS.trader,
        registrationType: "registered_user",
        referralCode: "mapped-01",
      },
      "new-user",
  );
  assert.equal(mapped.requestedRoleId, ROLE_IDS.signalProvider);
  assert.equal(mapped.teamId, "team-a");
  assert.equal(mapped.organisationId, "org-a");
  assert.equal(mapped.recruiterId, "recruiter-a");

  const weeklyRoleMapped = await _test.resolveReferral(
      fakeReferralDb({
        id: "99559lxy",
        code: "99559LXY",
        code_type: "weekly_recruitment",
        allowed_purpose: "upline_downline",
        owner_user_id: "leader-1",
        created_by_uid: "leader-1",
        owner_role: ROLE_IDS.superAdmin,
        recruitment_role_id: ROLE_IDS.signalProvider,
        registration_role_id: ROLE_IDS.signalProvider,
        default_role_ids: [ROLE_IDS.signalProvider],
        requires_approval: true,
        is_active: true,
      }),
      {
        requestedRoleId: ROLE_IDS.trader,
        registrationType: "registered_user",
        referralCode: "99559lxy",
      },
      "new-user",
  );
  assert.equal(weeklyRoleMapped.requestedRoleId, ROLE_IDS.signalProvider);
  assert.equal(
      _test.provisionalRoleId(weeklyRoleMapped.requestedRoleId),
      ROLE_IDS.trader,
  );

  await assert.rejects(
      _test.resolveReferral(
          fakeReferralDb({
            id: "forged-weekly",
            code: "FORGED-WEEKLY",
            code_type: "weekly_recruitment",
            allowed_purpose: "upline_downline",
            owner_user_id: "leader-1",
            created_by_uid: "different-user",
            recruitment_role_id: ROLE_IDS.signalProvider,
            default_role_ids: [ROLE_IDS.signalProvider],
            requires_approval: true,
            is_active: true,
          }),
          {requestedRoleId: ROLE_IDS.trader, referralCode: "forged-weekly"},
          "new-user",
      ),
      /requires administrator approval/i,
  );

  await assert.rejects(
      _test.resolveReferral(
          fakeReferralDb({
            id: "privileged-code-id",
            code: "PRIVILEGED-01",
            code_type: "campaign",
            allowed_purpose: "public_registration",
            owner_user_id: "leader-1",
            default_role_ids: [ROLE_IDS.superAdmin],
          }),
          {requestedRoleId: ROLE_IDS.trader, referralCode: "privileged-01"},
          "new-user",
      ),
      /cannot assign a privileged role/i,
  );

  const noReferral = await _test.resolveReferral(null, {
    requestedRoleId: ROLE_IDS.trader,
    referralCode: "",
  });
  assert.equal(noReferral.requestedRoleId, ROLE_IDS.trader);
  assert.equal(_test.provisionalRoleId(ROLE_IDS.marketAnalyst), ROLE_IDS.trader);
  assert.equal(_test.roleKey(ROLE_IDS.assistant), "assistant");

  const weekly = _test.weeklyWindow(new Date("2026-07-11T12:00:00Z"));
  assert.equal(weekly.key, "2026-W28");
  assert.equal(weekly.expiresAt.toISOString(), "2026-07-13T00:00:00.000Z");

  const code = _test.randomReferralCode();
  assert.match(code, /^[A-HJ-NP-Z2-9]{8}$/);

  await assert.rejects(
      _test.resolveReferral(
          fakeReferralDb({
            id: "self-referral",
            code: "SELF-01",
            code_type: "normal",
            allowed_purpose: "upline_downline",
            owner_user_id: "new-user",
            is_active: true,
          }),
          {requestedRoleId: ROLE_IDS.trader, referralCode: "self-01"},
          "new-user",
      ),
      /own referral code/i,
  );

  const deletionHash = _test.hashedDeletionReference("private-user-id");
  assert.match(deletionHash, /^[a-f0-9]{64}$/);
  assert.equal(deletionHash.includes("private-user-id"), false);
  assert.equal(
      deletionHash,
      _test.hashedDeletionReference("private-user-id"),
      "deletion retries must resolve the same resumable job",
  );
  assert.notEqual(
      deletionHash,
      _test.hashedDeletionReference("different-user-id"),
  );
  assert.equal(
      _test.safeDeletionError({code: "storage/permission-denied: secret"}),
      "storage/permission-deniedsecret",
  );
  assert.equal(_test.safeDeletionError(new Error("PII must not leak")),
      "unknown");

  await assert.rejects(
      registrationFunctions.recruitDownlineByReferralCode.run({
        auth: null,
        data: {referralCode: "ANY-CODE"},
      }),
      (error) => error.code === "unauthenticated",
  );
  await assert.rejects(
      registrationFunctions.recruitDownlineByReferralCode.run({
        auth: {uid: "existing-user"},
        data: {referralCode: "ANY-CODE"},
      }),
      (error) => error.code === "failed-precondition" &&
        /new account/i.test(error.message),
  );
  console.log("Referral registration tests passed.");
}

function fakeReferralDb(data) {
  const missing = {
    exists: false,
    id: "designated-01",
    data: () => undefined,
  };
  const found = {
    exists: true,
    id: data.id,
    data: () => data,
  };
  return {
    collection: (collectionName) => ({
      doc: () => ({
        get: async () => collectionName === "users" ? {
          exists: true,
          id: "leader-1",
          data: () => ({
            status: 1,
            account_status: "active",
            role_id: ROLE_IDS.superAdmin,
            role_ids: [ROLE_IDS.superAdmin],
          }),
        } : missing,
      }),
      where: (field, operator, value) => ({
        limit: () => ({
          get: async () => {
            const matches =
              operator === "==" &&
              ((field === "code" && value === data.code) ||
                value === String(data.code).toLowerCase());
            return {
              empty: !matches,
              size: matches ? 1 : 0,
              docs: matches ? [found] : [],
            };
          },
        }),
      }),
    }),
  };
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
