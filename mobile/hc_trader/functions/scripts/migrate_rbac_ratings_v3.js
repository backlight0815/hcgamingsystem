"use strict";

const {isDeepStrictEqual} = require("node:util");

const {
  commitWrites,
  encodeFields,
  listCollectionDocuments,
  resolveFirebaseAuth,
} = require("../../tools/firebase_rest_helpers");
const {
  ROLE_IDS,
  ROLE_PERMISSIONS,
  roleName,
} = require("../role_permissions");
const {DEFAULT_CONFIG} = require("../performance_rating_engine");

const MIGRATION_VERSION = 3;
const PROJECT_ID = process.env.GCLOUD_PROJECT || "hcgaming-31d5d";

function needsUpdate(current = {}, values = {}) {
  if (Number(current.migration_version || 0) !== MIGRATION_VERSION) return true;
  return Object.entries(values).some(([key, value]) =>
    !isDeepStrictEqual(current[key], value));
}

function normalizedAccessStatus(value) {
  const status = String(value || "").replace(/[-_\s]/g, "").toLowerCase();
  if (["approved", "verified", "activeclient", "approveduser"]
      .includes(status)) return "approved";
  if (["pending", "pendingreview"].includes(status)) return "pendingReview";
  if (["rejected", "denied"].includes(status)) return "rejected";
  if (["withdraw", "withdrawn"].includes(status)) return "withdrawn";
  if (["suspended", "revoked", "deleted"].includes(status)) return status;
  return "unapproved";
}

function userMigrationUpdates(user = {}) {
  const legacyRole = Number(user.role_id || ROLE_IDS.potentialClient);
  const existingRoles = Array.isArray(user.role_ids) ?
    user.role_ids.map(Number).filter(Number.isFinite) : [];
  const roleIds = [...new Set([...existingRoles, legacyRole])];
  const accessStatus = normalizedAccessStatus(
      user.access_status || user.accessStatus);
  return {
    role_ids: roleIds,
    accessStatus,
    access_status: accessStatus,
    permission_overrides: user.permission_overrides || {},
    account_status: user.account_status || "active",
    team_ids: Array.isArray(user.team_ids) ? user.team_ids : [],
    assigned_client_ids: Array.isArray(user.assigned_client_ids) ?
      user.assigned_client_ids : [],
    assigned_evaluation_ids: Array.isArray(user.assigned_evaluation_ids) ?
      user.assigned_evaluation_ids : [],
    organisation_id: user.organisation_id || null,
    claims_version: Number(user.claims_version || 0),
    timezone: user.timezone || "Asia/Singapore",
    timezone_offset_minutes: Number(user.timezone_offset_minutes || 480),
  };
}

function referralMigrationUpdates(referral = {}) {
  const defaultRole = Number(
      referral.registration_role_id || referral.recruitment_role_id ||
      ROLE_IDS.potentialClient);
  return {
    default_role_ids: Array.isArray(referral.default_role_ids) ?
      referral.default_role_ids.map(Number) : [defaultRole],
    default_access_status: referral.default_access_status || "unapproved",
    requires_approval: referral.requires_approval !== false,
    allowed_registration_types: Array.isArray(referral.allowed_registration_types) ?
      referral.allowed_registration_types : ["registered_user"],
    current_uses: Number(referral.current_uses ?? referral.usage_count ?? 0),
    max_uses: Number(referral.max_uses ?? referral.maximum_usage ?? 0),
    status: referral.status || (referral.is_active === false ? "disabled" : "active"),
    issuer_user_id: referral.issuer_user_id || referral.owner_user_id || null,
    referrer_user_id: referral.referrer_user_id || referral.owner_user_id ||
      referral.user_id || null,
    upline_user_id: referral.upline_user_id || null,
    leader_user_id: referral.leader_user_id || null,
    recruiter_user_id: referral.recruiter_user_id || null,
    team_id: referral.team_id || null,
    organisation_id: referral.organisation_id || null,
    campaign_id: referral.campaign_id || null,
  };
}

function updateWrite(path, values, timestampField = "migrated_at") {
  const fields = {...values, migration_version: MIGRATION_VERSION};
  return {
    update: {
      name: `projects/${PROJECT_ID}/databases/(default)/documents/${path}`,
      fields: encodeFields(fields),
    },
    updateMask: {fieldPaths: Object.keys(fields)},
    updateTransforms: [{
      fieldPath: timestampField,
      setToServerValue: "REQUEST_TIME",
    }],
  };
}

async function commitInBatches(auth, writes) {
  for (let index = 0; index < writes.length; index += 400) {
    await commitWrites({
      projectId: PROJECT_ID,
      auth,
      writes: writes.slice(index, index + 400),
    });
  }
}

async function migrate({apply = false} = {}) {
  const auth = await resolveFirebaseAuth(PROJECT_ID);
  const [users, referrals, roles, configs] = await Promise.all([
    listCollectionDocuments({
      projectId: PROJECT_ID, auth, collection: "users",
    }),
    listCollectionDocuments({
      projectId: PROJECT_ID, auth, collection: "referral_codes",
    }),
    listCollectionDocuments({
      projectId: PROJECT_ID, auth, collection: "roles",
    }),
    listCollectionDocuments({
      projectId: PROJECT_ID, auth, collection: "performance_rating_config",
    }),
  ]);
  const userChanges = users.map((document) => ({
    document,
    values: userMigrationUpdates(document.data),
  })).filter(({document, values}) => needsUpdate(document.data, values));
  const referralChanges = referrals.map((document) => ({
    document,
    values: referralMigrationUpdates(document.data),
  })).filter(({document, values}) => needsUpdate(document.data, values));
  const currentRoles = new Map(roles.map((document) => [
    document.path.split("/").pop(), document.data,
  ]));
  const roleChanges = [...new Set(Object.values(ROLE_IDS))].map((roleId) => ({
    roleId,
    values: {
      name: roleName(roleId),
      display_name_key: roleName(roleId),
      is_privileged: [1, 2, 3, 780].includes(roleId),
      is_system_role: true,
      status: "active",
      permission_keys: ROLE_PERMISSIONS[roleId] || [],
    },
  })).filter(({roleId, values}) =>
    needsUpdate(currentRoles.get(String(roleId)) || {}, values));
  const currentConfig = configs.find((document) =>
    document.path.endsWith("/current"));
  const configChanges = needsUpdate(currentConfig?.data || {}, DEFAULT_CONFIG) ?
    1 : 0;
  const summary = {
    usersScanned: users.length,
    usersUpdated: userChanges.length,
    referralsScanned: referrals.length,
    referralsUpdated: referralChanges.length,
    rolesSeeded: roleChanges.length,
    configSeeded: configChanges,
    failed: 0,
  };
  if (apply) {
    const writes = [
      ...userChanges.map(({document, values}) => updateWrite(
          document.path, values)),
      ...referralChanges.map(({document, values}) => updateWrite(
          document.path, values)),
      ...roleChanges.map(({roleId, values}) => updateWrite(
          `roles/${roleId}`,
          values,
          "updated_at",
      )),
      ...(configChanges ? [updateWrite(
          "performance_rating_config/current",
          DEFAULT_CONFIG,
          "updated_at",
      )] : []),
    ];
    await commitInBatches(auth, writes);
  }
  return {mode: apply ? "apply" : "dry-run", ...summary};
}

if (require.main === module) {
  migrate({apply: process.argv.includes("--apply")})
      .then((summary) => console.log(JSON.stringify(summary)))
      .catch((error) => {
        console.error(error.message || error);
        process.exitCode = 1;
      });
}

module.exports = {
  normalizedAccessStatus,
  referralMigrationUpdates,
  userMigrationUpdates,
  needsUpdate,
  migrate,
};
