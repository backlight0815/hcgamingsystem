"use strict";

const {isDeepStrictEqual} = require("node:util");
const {
  commitWrites,
  encodeFields,
  listCollectionDocuments,
  resolveFirebaseAuth,
} = require("../../tools/firebase_rest_helpers");
const {roleName} = require("../role_permissions");
const {buildAdminSearchTerms} = require("../registration_functions")._test;

const PROJECT_ID = process.env.GCLOUD_PROJECT || "hcgaming-31d5d";
const SEARCH_VERSION = 1;

function normalizedAccess(value) {
  const compact = String(value || "").replace(/[-_\s]/g, "").toLowerCase();
  if (["pending", "pendingreview", "submitted"].includes(compact)) {
    return "pendingReview";
  }
  if (["approved", "active"].includes(compact)) return "approved";
  if (["rejected", "denied"].includes(compact)) return "rejected";
  if (["revoked", "suspended", "deleted"].includes(compact)) return compact;
  return "notSubmitted";
}

function migrationValues(id, user = {}, unresolvedTicketIds = new Set()) {
  const accessStatus = normalizedAccess(user.access_status || user.accessStatus);
  const roleId = Number(user.role_id || 0);
  const accountStatus = user.is_suspended === true || Number(user.status) === 0 ?
    "suspended" : String(user.account_status || "active");
  const verificationStatus = String(
      user.verification_status || user.broker_verification_status ||
      user.onboarding_status || "not_submitted",
  );
  const mainMt5Login = String(
      user.main_mt5_login || user.mt5_login || user.mainMt5Login || "",
  );
  const hasMt5 = Boolean(
      mainMt5Login || user.main_mt5_account_id || user.mainMt5AccountId,
  );
  const profile = {
    ...user,
    id,
    role_name: user.role_name || roleName(roleId),
    main_mt5_login: mainMt5Login,
  };
  return {
    admin_user_search_version: SEARCH_VERSION,
    name_lower: String(user.name || user.display_name || user.username || "")
        .trim().toLowerCase(),
    email_lowercase: String(user.email || "").trim().toLowerCase(),
    account_status: accountStatus,
    accessStatus: accessStatus,
    access_status: accessStatus,
    verification_status: verificationStatus,
    mt5_status: String(user.mt5_status || (hasMt5 ? "connected" : "none")),
    main_mt5_login: mainMt5Login,
    has_pending_application: accessStatus === "pendingReview",
    has_unresolved_ticket: unresolvedTicketIds.has(id),
    admin_search_terms: buildAdminSearchTerms(profile),
  };
}

function needsUpdate(user = {}, values = {}) {
  return Object.entries(values).some(([key, value]) =>
    !isDeepStrictEqual(user[key], value));
}

function updateWrite(path, values) {
  return {
    update: {
      name: `projects/${PROJECT_ID}/databases/(default)/documents/${path}`,
      fields: encodeFields(values),
    },
    updateMask: {fieldPaths: Object.keys(values)},
    updateTransforms: [{
      fieldPath: "admin_search_updated_at",
      setToServerValue: "REQUEST_TIME",
    }],
  };
}

async function migrate({apply = false} = {}) {
  const auth = await resolveFirebaseAuth(PROJECT_ID);
  const [users, tickets] = await Promise.all([
    listCollectionDocuments({
      projectId: PROJECT_ID,
      auth,
      collection: "users",
    }),
    listCollectionDocuments({
      projectId: PROJECT_ID,
      auth,
      collection: "support_tickets",
    }),
  ]);
  const unresolvedTicketIds = new Set(tickets
      .filter((ticket) => !["closed", "resolved", "cancelled"]
          .includes(String(ticket.data.status || "").toLowerCase()))
      .map((ticket) => String(ticket.data.user_id || ""))
      .filter(Boolean));
  const changes = users.map((document) => {
    const id = document.path.split("/").pop();
    return {
      document,
      values: migrationValues(id, document.data, unresolvedTicketIds),
    };
  }).filter(({document, values}) => needsUpdate(document.data, values));
  if (apply) {
    const writes = changes.map(({document, values}) =>
      updateWrite(document.path, values));
    for (let index = 0; index < writes.length; index += 400) {
      await commitWrites({
        projectId: PROJECT_ID,
        auth,
        writes: writes.slice(index, index + 400),
      });
    }
  }
  return {
    mode: apply ? "apply" : "dry-run",
    usersScanned: users.length,
    usersUpdated: changes.length,
    unresolvedTicketUsers: unresolvedTicketIds.size,
  };
}

if (require.main === module) {
  migrate({apply: process.argv.includes("--apply")})
      .then((summary) => console.log(JSON.stringify(summary)))
      .catch((error) => {
        console.error(error.message || error);
        process.exitCode = 1;
      });
}

module.exports = {migrationValues, needsUpdate, normalizedAccess, migrate};
