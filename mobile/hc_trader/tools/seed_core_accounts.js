#!/usr/bin/env node
"use strict";

const crypto = require("crypto");
const fs = require("fs");
const path = require("path");

const {
  ROLE_IDS,
  adminPermissionsForRole,
  isAdminRole,
  roleName,
} = require("../functions/role_permissions");

const PROJECT_ID = process.env.GCLOUD_PROJECT || "hcgaming-31d5d";
const ANDROID_CONFIG = path.resolve(
  __dirname,
  "../android/app/google-services.json",
);
const API_KEY = JSON.parse(fs.readFileSync(ANDROID_CONFIG, "utf8"))
  .client[0].api_key[0].current_key;

const ACCOUNTS = [
  {
    name: "Super Administrator",
    username: "superadmin",
    email: "superadmin@hcgaming.com",
    roleId: ROLE_IDS.superAdmin,
  },
  {
    name: "Sub Administrator",
    username: "subadmin",
    email: "subadmin@hcgaming.com",
    roleId: ROLE_IDS.subAdmin,
  },
  {
    name: "Trader",
    username: "trader",
    email: "trader@hcgaming.com",
    roleId: ROLE_IDS.trader,
  },
];

main().catch((error) => {
  console.error(error.stack || error.message || error);
  process.exitCode = 1;
});

async function main() {
  const accessToken = await firebaseCliAccessToken();
  const existingUsers = await listAuthUsers(accessToken);
  const now = new Date();
  const seeded = [];

  await seedRegistrationRoles(accessToken, now);

  for (const account of ACCOUNTS) {
    const password = temporaryPassword();
    const authUser = await createOrUpdateAuthUser({
      accessToken,
      existingUsers,
      account,
      password,
    });
    const referralCode = await uniqueReferralCode(accessToken);
    await seedUserDocuments({
      accessToken,
      account,
      uid: authUser.uid,
      referralCode,
      now,
    });
    seeded.push({
      role: roleName(account.roleId),
      email: account.email,
      username: account.username,
      password,
      uid: authUser.uid,
      authAction: authUser.action,
      referralCode,
    });
  }

  console.log(JSON.stringify({projectId: PROJECT_ID, seeded}, null, 2));
}

async function firebaseCliAccessToken() {
  const auth = require(
    process.env.APPDATA + "/npm/node_modules/firebase-tools/lib/auth",
  );
  const account = auth.getGlobalDefaultAccount();
  if (!account?.tokens?.refresh_token) {
    throw new Error("No Firebase CLI login token available.");
  }
  const token = await auth.getAccessToken(account.tokens.refresh_token, []);
  if (!token?.access_token) {
    throw new Error("Firebase CLI token refresh failed.");
  }
  return token.access_token;
}

async function listAuthUsers(accessToken) {
  const users = new Map();
  let nextPageToken = "";
  do {
    const body = {returnUserInfo: true, limit: 500};
    if (nextPageToken) body.pageToken = nextPageToken;
    const response = await authAdminRequest(
      accessToken,
      "accounts:query",
      body,
    );
    for (const user of response.userInfo || []) {
      if (user.email) users.set(String(user.email).toLowerCase(), user);
    }
    nextPageToken = response.nextPageToken || "";
  } while (nextPageToken);
  return users;
}

async function createOrUpdateAuthUser({
  accessToken,
  existingUsers,
  account,
  password,
}) {
  const email = account.email.toLowerCase();
  const existing = existingUsers.get(email);
  if (existing?.localId) {
    await authAdminRequest(accessToken, "accounts:update", {
      localId: existing.localId,
      email,
      displayName: account.name,
      emailVerified: true,
      disabled: false,
      password,
    });
    return {uid: existing.localId, action: "updated"};
  }

  try {
    const created = await authAdminRequest(accessToken, "accounts", {
      email,
      password,
      displayName: account.name,
      emailVerified: true,
      disabled: false,
    });
    return {uid: created.localId, action: "created"};
  } catch (error) {
    if (!/404|not found|unsupported/i.test(error.message)) throw error;
    const created = await publicSignUp(account, password);
    await authAdminRequest(accessToken, "accounts:update", {
      localId: created.localId,
      email,
      displayName: account.name,
      emailVerified: true,
      disabled: false,
    }).catch(() => {});
    return {uid: created.localId, action: "created-public-signup"};
  }
}

async function publicSignUp(account, password) {
  const url = new URL("https://identitytoolkit.googleapis.com/v1/accounts:signUp");
  url.searchParams.set("key", API_KEY);
  const response = await fetch(url, {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({
      email: account.email.toLowerCase(),
      password,
      displayName: account.name,
      returnSecureToken: true,
    }),
  });
  const text = await response.text();
  if (!response.ok) {
    throw new Error(`Auth public signup failed ${response.status}: ${text}`);
  }
  return JSON.parse(text);
}

async function authAdminRequest(accessToken, method, body) {
  const response = await fetch(
    `https://identitytoolkit.googleapis.com/v1/projects/${PROJECT_ID}/${method}`,
    {
      method: "POST",
      headers: {
        Authorization: `Bearer ${accessToken}`,
        "Content-Type": "application/json",
      },
      body: JSON.stringify(body),
    },
  );
  const text = await response.text();
  if (!response.ok) {
    throw new Error(`Auth admin ${method} failed ${response.status}: ${text}`);
  }
  return text ? JSON.parse(text) : {};
}

async function seedRegistrationRoles(accessToken, now) {
  const roles = [
    {
      id: ROLE_IDS.trader,
      name: roleName(ROLE_IDS.trader),
      description: "Trading journal, education, and prop-firm tools",
      enabled: true,
      public_registration: true,
    },
    {
      id: ROLE_IDS.leadClient,
      name: roleName(ROLE_IDS.leadClient),
      description: "Client account access",
      enabled: true,
      public_registration: true,
    },
  ];

  for (const role of roles) {
    await setFirestoreDocument(
      accessToken,
      `registration_roles/${role.id}`,
      {
        role_id: role.id,
        name: role.name,
        description: role.description,
        enabled: role.enabled,
        public_registration: role.public_registration,
        updated_at: now,
      },
    );
  }
}

async function seedUserDocuments({
  accessToken,
  account,
  uid,
  referralCode,
  now,
}) {
  const roleId = account.roleId;
  const normalizedEmail = account.email.toLowerCase();
  const normalizedUsername = account.username.toLowerCase();
  const isAdmin = isAdminRole(roleId);

  await setFirestoreDocument(accessToken, `users/${uid}`, {
    name: account.name,
    username: normalizedUsername,
    username_lowercase: normalizedUsername,
    email: normalizedEmail,
    email_lowercase: normalizedEmail,
    role_id: roleId,
    role_name: roleName(roleId),
    permissions: adminPermissionsForRole(roleId),
    status: 1,
    account_status: "active",
    is_suspended: false,
    is_admin: isAdmin,
    requires_onboarding: roleId === ROLE_IDS.trader,
    final_exam_passed: false,
    requires_final_exam: false,
    invited_by: null,
    referrer_id: null,
    upline_id: null,
    referral_code_used: null,
    referral_code_type: null,
    created_by_referral_type: "manual_seed",
    referral_code: referralCode,
    referral_code_type_generated: "normal",
    created_at: now,
    updated_at: now,
  });

  await setFirestoreDocument(accessToken, `usernames/${normalizedUsername}`, {
    uid,
    email: normalizedEmail,
    created_at: now,
  });

  await setFirestoreDocument(
    accessToken,
    `referral_codes/${referralCode.toLowerCase()}`,
    {
      code: referralCode,
      user_id: uid,
      owner_user_id: uid,
      code_type: "normal",
      allowed_purpose: "upline_downline",
      registration_role_id: ROLE_IDS.trader,
      is_active: true,
      created_at: now,
    },
  );
}

async function setFirestoreDocument(accessToken, documentPath, data) {
  const url =
    `https://firestore.googleapis.com/v1/projects/${PROJECT_ID}` +
    `/databases/(default)/documents/${documentPath}`;
  const response = await fetch(url, {
    method: "PATCH",
    headers: {
      Authorization: `Bearer ${accessToken}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({fields: encodeFields(data)}),
  });
  const text = await response.text();
  if (!response.ok) {
    throw new Error(`Firestore write ${documentPath} failed ${response.status}: ${text}`);
  }
  return text ? JSON.parse(text) : {};
}

async function getFirestoreDocument(accessToken, documentPath) {
  const url =
    `https://firestore.googleapis.com/v1/projects/${PROJECT_ID}` +
    `/databases/(default)/documents/${documentPath}`;
  const response = await fetch(url, {
    headers: {Authorization: `Bearer ${accessToken}`},
  });
  if (response.status === 404) return null;
  const text = await response.text();
  if (!response.ok) {
    throw new Error(`Firestore read ${documentPath} failed ${response.status}: ${text}`);
  }
  return JSON.parse(text);
}

async function uniqueReferralCode(accessToken) {
  for (let attempt = 0; attempt < 10; attempt++) {
    const code = randomReferralCode();
    const existing = await getFirestoreDocument(
      accessToken,
      `referral_codes/${code.toLowerCase()}`,
    );
    if (!existing) return code;
  }
  throw new Error("Unable to allocate a unique referral code.");
}

function randomReferralCode() {
  const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  return Array.from(crypto.randomBytes(8))
    .map((byte) => alphabet[byte % alphabet.length])
    .join("");
}

function temporaryPassword() {
  const alphabet =
    "ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789";
  const body = Array.from(crypto.randomBytes(14))
    .map((byte) => alphabet[byte % alphabet.length])
    .join("");
  return `HCt-${body}!7`;
}

function encodeFields(fields) {
  return Object.fromEntries(
    Object.entries(fields).map(([key, value]) => [key, encodeValue(value)]),
  );
}

function encodeValue(value) {
  if (value == null) return {nullValue: null};
  if (value instanceof Date) return {timestampValue: value.toISOString()};
  if (typeof value === "string") return {stringValue: value};
  if (typeof value === "boolean") return {booleanValue: value};
  if (typeof value === "number") {
    return Number.isInteger(value) ?
      {integerValue: String(value)} :
      {doubleValue: value};
  }
  if (Array.isArray(value)) {
    return {arrayValue: {values: value.map(encodeValue)}};
  }
  if (typeof value === "object") {
    return {mapValue: {fields: encodeFields(value)}};
  }
  return {stringValue: String(value)};
}
