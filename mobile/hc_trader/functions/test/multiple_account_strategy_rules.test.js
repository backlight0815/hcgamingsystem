"use strict";

const fs = require("node:fs");
const path = require("node:path");
const {
  assertFails,
  assertSucceeds,
  initializeTestEnvironment,
} = require("@firebase/rules-unit-testing");
const {
  doc,
  deleteDoc,
  getDoc,
  serverTimestamp,
  setDoc,
  updateDoc,
} = require("firebase/firestore");

const projectId = "hc-trader-multiple-account-strategy-rules-test";
const workspace = "users/user-a/multiple_account_strategy/workspace";

function user(isAdmin = false) {
  return {
    name: "Rules Test",
    email: "rules@example.test",
    role_id: isAdmin ? 1 : 750,
    is_admin: isAdmin,
    account_status: "active",
    is_suspended: false,
  };
}

function account(ownerUid = "user-a", id = "account-a", overrides = {}) {
  return {
    id,
    owner_uid: ownerUid,
    schema_version: 4,
    account_size: 10000,
    starting_balance: 10000,
    current_balance: 10000,
    daily_loss_limit: 500,
    maximum_loss_limit: 1000,
    daily_trade_limit_enabled: true,
    daily_trade_limit: 3,
    daily_trade_count: 0,
    updated_at: serverTimestamp(),
    ...overrides,
  };
}

function result(ownerUid = "user-a", id = "result-a", overrides = {}) {
  return {
    id,
    owner_uid: ownerUid,
    created_by: ownerUid,
    strategy_id: "strategy-a",
    session_id: "session-a",
    account_id: "account-a",
    schema_version: 4,
    entered_amount: 100,
    normalized_amount: -100,
    balance_before: 10000,
    balance_after: 9900,
    ...overrides,
  };
}

async function main() {
  const rules = fs.readFileSync(
      path.resolve(__dirname, "../../firestore.rules"),
      "utf8",
  );
  const testEnv = await initializeTestEnvironment({
    projectId,
    firestore: {rules},
  });
  try {
    await testEnv.withSecurityRulesDisabled(async (context) => {
      const db = context.firestore();
      await setDoc(doc(db, "users/user-a"), user());
      await setDoc(doc(db, "users/user-b"), user());
      await setDoc(doc(db, "users/admin-a"), user(true));
    });

    const userA = testEnv.authenticatedContext("user-a").firestore();
    const userB = testEnv.authenticatedContext("user-b").firestore();
    const admin = testEnv.authenticatedContext("admin-a").firestore();
    const anonymous = testEnv.unauthenticatedContext().firestore();

    await assertSucceeds(setDoc(doc(userA, workspace), {
      owner_uid: "user-a",
      schema_version: 4,
      updated_at: serverTimestamp(),
    }));
    await assertSucceeds(setDoc(
        doc(userA, `${workspace}/accounts/account-a`),
        account(),
    ));
    await assertSucceeds(updateDoc(
        doc(userA, `${workspace}/accounts/account-a`),
        {current_balance: 9900, updated_at: serverTimestamp()},
    ));
    await assertSucceeds(getDoc(doc(userA, workspace)));
    await assertSucceeds(setDoc(
        doc(userA, `${workspace}/configuration/sequence`),
        {
          owner_uid: "user-a",
          schema_version: 1,
          firestore_schema_version: 4,
          plan: null,
          settings: {},
          templates: [],
          updated_at: serverTimestamp(),
        },
    ));

    await assertFails(getDoc(doc(userB, workspace)));
    await assertFails(getDoc(doc(anonymous, workspace)));
    await assertFails(setDoc(
        doc(userB, `${workspace}/accounts/forged`),
        account("user-b", "forged"),
    ));
    await assertFails(setDoc(
        doc(userA, `${workspace}/accounts/wrong-owner`),
        account("user-b", "wrong-owner"),
    ));
    await assertFails(setDoc(
        doc(userA, `${workspace}/accounts/string-values`),
        account("user-a", "string-values", {account_size: "10000"}),
    ));
    await assertSucceeds(setDoc(
        doc(userA, `${workspace}/accounts/different-limit`),
        account("user-a", "different-limit", {daily_trade_limit: 5}),
    ));
    await assertFails(setDoc(
        doc(userA, `${workspace}/accounts/zero-limit`),
        account("user-a", "zero-limit", {daily_trade_limit: 0}),
    ));
    await assertFails(setDoc(
        doc(userA, `${workspace}/accounts/decimal-limit`),
        account("user-a", "decimal-limit", {daily_trade_limit: 2.5}),
    ));
    await assertFails(setDoc(
        doc(userA, `${workspace}/accounts/unsafe-limit`),
        account("user-a", "unsafe-limit", {daily_trade_limit: 101}),
    ));
    await assertFails(setDoc(
        doc(userA, `${workspace}/accounts/invalid-enabled`),
        account("user-a", "invalid-enabled", {
          daily_trade_limit_enabled: "yes",
        }),
    ));
    await assertFails(deleteDoc(
        doc(userA, `${workspace}/accounts/account-a`),
    ));

    const sessionPath = `${workspace}/sessions/session-a`;
    await assertSucceeds(setDoc(doc(userA, sessionPath), {
      id: "session-a",
      owner_uid: "user-a",
      schema_version: 4,
      status: "ready",
      updated_at: serverTimestamp(),
    }));
    const snapshotPath = `${sessionPath}/account_snapshots/account-a`;
    await assertSucceeds(setDoc(doc(userA, snapshotPath), {
      id: "account-a",
      owner_uid: "user-a",
      rule_configuration_version: 4,
      raw_account: {current_balance: 9900},
      created_at: serverTimestamp(),
    }));
    await assertFails(updateDoc(doc(userA, snapshotPath), {
      raw_account: {current_balance: 1},
    }));

    const resultPath = `${sessionPath}/results/result-a`;
    await assertSucceeds(setDoc(doc(userA, resultPath), result()));
    await assertSucceeds(getDoc(doc(userA, resultPath)));
    await assertFails(getDoc(doc(userB, resultPath)));
    await assertFails(setDoc(
        doc(userA, `${sessionPath}/results/wrong-id`),
        result("user-a", "result-a"),
    ));
    await assertFails(setDoc(
        doc(userA, `${sessionPath}/results/wrong-session`),
        result("user-a", "wrong-session", {session_id: "session-b"}),
    ));
    await assertFails(setDoc(
        doc(userA, `${sessionPath}/results/wrong-owner`),
        result("user-b", "wrong-owner"),
    ));
    await assertFails(setDoc(
        doc(userA, `${sessionPath}/results/string-amount`),
        result("user-a", "string-amount", {normalized_amount: "-100"}),
    ));
    await assertFails(deleteDoc(doc(userA, resultPath)));

    await assertSucceeds(getDoc(doc(admin, workspace)));
    await assertSucceeds(setDoc(
        doc(admin, `${workspace}/accounts/admin-created`),
        account("user-a", "admin-created"),
    ));
    await assertFails(deleteDoc(
        doc(admin, `${workspace}/accounts/admin-created`),
    ));
    console.log("Multiple Account Strategy Firestore rule tests passed.");
  } finally {
    await testEnv.cleanup();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
