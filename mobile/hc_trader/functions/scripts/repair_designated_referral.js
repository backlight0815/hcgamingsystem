#!/usr/bin/env node
"use strict";

const {
  commitWrites,
  encodeFields,
  listCollectionDocuments,
  resolveFirebaseAuth,
} = require("../../tools/firebase_rest_helpers");

const PROJECT_ID = process.env.GCLOUD_PROJECT || "hcgaming-31d5d";
const DESIGNATED_CODE = normalize(
    process.env.DESIGNATED_REFERRAL_CODE || "ESTHKY 9908",
);
const APPLY = process.argv.includes("--apply");

main().catch((error) => {
  console.error(error.message || error);
  process.exitCode = 1;
});

async function main() {
  const auth = await resolveFirebaseAuth(PROJECT_ID);
  const [users, referrals] = await Promise.all([
    listCollectionDocuments({
      projectId: PROJECT_ID,
      auth,
      collection: "users",
    }),
    listCollectionDocuments({
      projectId: PROJECT_ID,
      auth,
      collection: "referral_codes",
    }),
  ]);
  const existing = referrals.find((item) =>
    normalize(item.id) === DESIGNATED_CODE ||
      normalize(item.data.code) === DESIGNATED_CODE ||
      normalize(item.data.code_normalized) === DESIGNATED_CODE,
  );
  if (existing) {
    console.log(JSON.stringify({
      mode: APPLY ? "apply" : "dry-run",
      status: "already-present",
      documentId: existing.id,
    }));
    return;
  }

  const explicitOwner = String(process.env.DESIGNATED_REFERRER_UID || "").trim();
  const candidates = users.filter((item) => {
    if (explicitOwner) return item.id === explicitOwner;
    return Number(item.data.role_id || 0) === 1 &&
      Number(item.data.status ?? 1) !== 0 &&
      item.data.is_suspended !== true &&
      String(item.data.account_status || "active").toLowerCase() === "active";
  });
  if (candidates.length !== 1) {
    throw new Error(
        "Set DESIGNATED_REFERRER_UID to exactly one active eligible account.",
    );
  }
  const owner = candidates[0];
  const summary = {
    mode: APPLY ? "apply" : "dry-run",
    status: APPLY ? "created" : "would-create",
    documentId: DESIGNATED_CODE,
    ownerUserId: owner.id,
  };
  if (!APPLY) {
    console.log(JSON.stringify(summary));
    return;
  }

  const documentName =
    `projects/${PROJECT_ID}/databases/(default)/documents/` +
    `referral_codes/${DESIGNATED_CODE}`;
  await commitWrites({
    projectId: PROJECT_ID,
    auth,
    writes: [{
      update: {
        name: documentName,
        fields: encodeFields({
          code: DESIGNATED_CODE.toUpperCase(),
          code_normalized: DESIGNATED_CODE,
          user_id: owner.id,
          owner_user_id: owner.id,
          code_type: "designated",
          allowed_purpose: "public_registration",
          registration_role_id: 750,
          is_active: true,
          status: "active",
          usage_count: 0,
        }),
      },
      updateTransforms: [
        {fieldPath: "created_at", setToServerValue: "REQUEST_TIME"},
        {fieldPath: "updated_at", setToServerValue: "REQUEST_TIME"},
      ],
      currentDocument: {exists: false},
    }],
  });
  console.log(JSON.stringify(summary));
}

function normalize(value) {
  return String(value || "")
      .normalize("NFKC")
      .replace(/\s+/g, "")
      .toLowerCase();
}
