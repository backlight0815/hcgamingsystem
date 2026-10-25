"use strict";

const fs = require("fs");
const path = require("path");
const crypto = require("crypto");
const admin = require("firebase-admin");
const {initializeApp, deleteApp} = require("firebase/app");
const {getAuth, signInWithEmailAndPassword, signOut} = require("firebase/auth");
const {
  doc,
  getDoc,
  getFirestore,
  serverTimestamp,
  writeBatch,
} = require("firebase/firestore");

function envFile(file) {
  const result = {};
  if (!fs.existsSync(file)) return result;
  for (const line of fs.readFileSync(file, "utf8").split(/\r?\n/)) {
    const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
    if (!match || match[1].startsWith("#")) continue;
    result[match[1]] = match[2].replace(/^['"]|['"]$/g, "");
  }
  return result;
}

function mainPayload(id, login) {
  return {
    mainMt5AccountId: id,
    main_mt5_account_id: id,
    mainMt5AccountKey: id,
    main_mt5_account_key: id,
    mainMt5AccountLogin: login,
    main_mt5_account_login: login,
    mainMt5AccountUpdatedAt: serverTimestamp(),
    main_mt5_account_updated_at: serverTimestamp(),
    updated_at: serverTimestamp(),
  };
}

function account(uid, id, login, active = true) {
  return {
    ownerUid: uid,
    userId: uid,
    user_id: uid,
    mt5AccountId: id,
    mt5_account_id: id,
    account_key: id,
    mt5_login: login,
    broker_server: "Smoke-Demo",
    display_name: `Smoke ${login}`,
    sync_machine_type: "vps",
    is_active: active,
    is_primary: false,
    isPrimary: false,
    sync_status: "connected",
    display_status: "Connected",
    last_error: "",
    sync_error_code: "",
    balance: 10000,
    equity: 10000,
    currency: "USD",
  };
}

async function setMain(db, uid, id, login, previousId) {
  const batch = writeBatch(db);
  if (previousId) {
    batch.set(
      doc(db, `users/${uid}/mt5_accounts/${previousId}`),
      {is_primary: false, isPrimary: false, updated_at: serverTimestamp()},
      {merge: true},
    );
  }
  batch.set(
    doc(db, `users/${uid}/mt5_accounts/${id}`),
    {is_primary: true, isPrimary: true, updated_at: serverTimestamp()},
    {merge: true},
  );
  batch.set(doc(db, `users/${uid}`), mainPayload(id, login), {merge: true});
  await batch.commit();
}

async function run() {
  if (!process.argv.includes("--confirm-production")) {
    throw new Error("Pass --confirm-production to run the isolated live smoke test.");
  }
  const root = path.join(__dirname, "..", "..");
  const googleServices = JSON.parse(
    fs.readFileSync(path.join(root, "android", "app", "google-services.json"), "utf8"),
  );
  const env = envFile(path.join(root, "..", "..", "mt5_sync", ".env"));
  const serviceAccountPath =
    env.FIREBASE_SERVICE_ACCOUNT_PATH || env.GOOGLE_APPLICATION_CREDENTIALS;
  const projectId = googleServices.project_info.project_id;
  const credentialJson = serviceAccountPath
    ? JSON.parse(fs.readFileSync(serviceAccountPath, "utf8"))
    : null;
  if (serviceAccountPath && credentialJson.type !== "service_account") {
    process.env.GOOGLE_APPLICATION_CREDENTIALS = serviceAccountPath;
  }
  const credential = credentialJson && credentialJson.type === "service_account"
    ? admin.credential.cert(credentialJson)
    : admin.credential.applicationDefault();
  const adminApp = admin.initializeApp({credential, projectId}, `smoke-${Date.now()}`);
  const adminDb = adminApp.firestore();
  const suffix = crypto.randomBytes(8).toString("hex");
  const email = `mt5-main-smoke-${suffix}@example.test`;
  const password = `S!${crypto.randomBytes(20).toString("base64url")}`;
  const otherUid = `smoke-other-${suffix}`;
  let uid;
  let clientApp;
  try {
    const created = await adminApp.auth().createUser({email, password});
    uid = created.uid;
    const userRef = adminDb.collection("users").doc(uid);
    await userRef.set({
      name: "MT5 Smoke Test",
      email,
      email_lowercase: email,
      is_admin: false,
      role_id: 750,
      status: 1,
    });
    const accountA = "Smoke-Demo_91001";
    const accountB = "Smoke-Demo_91002";
    await Promise.all([
      userRef.collection("mt5_accounts").doc(accountA).set(account(uid, accountA, 91001)),
      userRef.collection("mt5_accounts").doc(accountB).set(account(uid, accountB, 91002)),
      adminDb.collection("users").doc(otherUid).set({
        name: "Other Smoke User",
        is_admin: false,
        role_id: 750,
        status: 1,
      }),
      adminDb
        .collection("users")
        .doc(otherUid)
        .collection("mt5_accounts")
        .doc("Other_92001")
        .set(account(otherUid, "Other_92001", 92001)),
    ]);

    const apiKey = googleServices.client[0].api_key[0].current_key;
    clientApp = initializeApp(
      {apiKey, projectId, appId: googleServices.client[0].client_info.mobilesdk_app_id},
      `smoke-client-${suffix}`,
    );
    const auth = getAuth(clientApp);
    await signInWithEmailAndPassword(auth, email, password);
    const clientDb = getFirestore(clientApp);

    await setMain(clientDb, uid, accountA, 91001);
    let saved = await getDoc(doc(clientDb, `users/${uid}`));
    if (saved.data().mainMt5AccountId !== accountA) throw new Error("Account A was not saved.");

    await setMain(clientDb, uid, accountB, 91002, accountA);
    saved = await getDoc(doc(clientDb, `users/${uid}`));
    if (saved.data().mainMt5AccountId !== accountB) throw new Error("Account B was not saved.");

    let denied = false;
    try {
      await setMain(clientDb, otherUid, "Other_92001", 92001);
    } catch (error) {
      denied = error && error.code === "permission-denied";
    }
    if (!denied) throw new Error("Cross-user main-account selection was not denied.");

    await signOut(auth);
    await signInWithEmailAndPassword(auth, email, password);
    saved = await getDoc(doc(clientDb, `users/${uid}`));
    if (saved.data().mainMt5AccountId !== accountB) {
      throw new Error("Main account did not persist after sign-out/sign-in.");
    }
    console.log("Production MT5 main-account smoke test passed.");
  } finally {
    if (clientApp) await deleteApp(clientApp).catch(() => undefined);
    if (uid) {
      await adminDb.recursiveDelete(adminDb.collection("users").doc(uid)).catch(() => undefined);
      await adminApp.auth().deleteUser(uid).catch(() => undefined);
    }
    await adminDb
      .recursiveDelete(adminDb.collection("users").doc(otherUid))
      .catch(() => undefined);
    await adminApp.delete().catch(() => undefined);
  }
}

run().catch((error) => {
  console.error(error.message || error);
  process.exitCode = 1;
});
