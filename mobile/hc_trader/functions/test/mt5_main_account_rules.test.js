"use strict";

const assert = require("assert");
const fs = require("fs");
const path = require("path");
const {
  assertFails,
  assertSucceeds,
  initializeTestEnvironment,
} = require("@firebase/rules-unit-testing");
const {
  doc,
  getDoc,
  serverTimestamp,
  setDoc,
  writeBatch,
} = require("firebase/firestore");

const projectId = "hc-trader-mt5-rules-test";
const accountA = "Broker-Demo_10001";
const accountB = "Broker-Demo_10002";

function account(uid, id, login, extra = {}) {
  return {
    ownerUid: uid,
    userId: uid,
    user_id: uid,
    mt5AccountId: id,
    mt5_account_id: id,
    account_key: id,
    mt5_login: login,
    broker_server: "Broker-Demo",
    display_name: `Account ${login}`,
    sync_machine_type: "vps",
    is_active: true,
    is_primary: false,
    isPrimary: false,
    sync_status: "connected",
    display_status: "Connected",
    last_error: "",
    sync_error_code: "",
    balance: 10000,
    equity: 10000,
    currency: "USD",
    ...extra,
  };
}

function user() {
  return {
    name: "Rules Test",
    email: "rules@example.test",
    email_lowercase: "rules@example.test",
    is_admin: false,
    role_id: 750,
    status: 1,
    account_status: "active",
    is_suspended: false,
    accessStatus: "approved",
    access_status: "approved",
  };
}

async function seed(testEnv) {
  await testEnv.withSecurityRulesDisabled(async (context) => {
    const db = context.firestore();
    await setDoc(doc(db, "users/user-a"), user());
    await setDoc(doc(db, "users/user-b"), user());
    await setDoc(doc(db, "users/user-c"), user());
    await setDoc(doc(db, "users/user-d"), {
      ...user(),
      email: "pending@example.test",
      email_lowercase: "pending@example.test",
      accessStatus: "pendingReview",
      access_status: "pendingReview",
    });
    await setDoc(doc(db, "users/admin-1"), {
      ...user(),
      email: "admin@example.test",
      email_lowercase: "admin@example.test",
      role_id: 1,
      is_admin: true,
    });
    await setDoc(doc(db, "users/consultant-1"), {
      ...user(),
      email: "consultant@example.test",
      email_lowercase: "consultant@example.test",
      role_id: 790,
      role_ids: [790],
      assigned_client_ids: ["user-a"],
      accessStatus: "unapproved",
      access_status: "unapproved",
    });
    await setDoc(doc(db, "accessRequests/user-d"), {
      userId: "user-d",
      user_id: "user-d",
      name: "Pending User",
      email: "pending@example.test",
      phoneNumber: "123",
      phone_number: "123",
      status: "pendingReview",
      accessStatus: "pendingReview",
      access_status: "pendingReview",
    });
    await setDoc(
      doc(db, `users/user-a/mt5_accounts/${accountA}`),
      account("user-a", accountA, 10001),
    );
    await setDoc(
      doc(db, `users/user-a/mt5_accounts/${accountB}`),
      account("user-a", accountB, 10002),
    );
    await setDoc(
      doc(db, "users/user-b/mt5_accounts/Other_20001"),
      account("user-b", "Other_20001", 20001),
    );
    await setDoc(
      doc(db, "users/user-a/mt5_accounts/Inactive_30001"),
      account("user-a", "Inactive_30001", 30001, {is_active: false}),
    );
    await setDoc(
      doc(db, "users/user-a/mt5_accounts/Archived_30002"),
      account("user-a", "Archived_30002", 30002, {is_archived: true}),
    );
    await setDoc(
      doc(db, "users/user-a/mt5_accounts/WrongOwner_30003"),
      account("user-b", "WrongOwner_30003", 30003),
    );
    await setDoc(
      doc(db, `users/user-a/mt5_accounts/${accountA}/trades/trade-1`),
      {user_id: "user-a", account_key: accountA, status: "closed"},
    );
    await setDoc(doc(
        db,
        `users/user-a/mt5_accounts/${accountA}/import_batches/import-hash`,
    ), {
      ownerUid: "user-a",
      user_id: "user-a",
      account_key: accountA,
      status: "completed",
      file_hash: "a".repeat(64),
    });
    await setDoc(doc(
        db,
        `users/user-a/mt5_accounts/${accountA}/deals/mt5d-1`,
    ), {user_id: "user-a", account_key: accountA, source_deal_ticket: "1"});
    await setDoc(doc(
        db,
        `users/user-a/mt5_accounts/${accountA}/balance_transactions/mt5b-1`,
    ), {user_id: "user-a", account_key: accountA, amount: 1000});
  });
}

function setMainBatch(db, uid, id, login, previousId) {
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
  batch.set(
    doc(db, `users/${uid}`),
    {
      mainMt5AccountId: id,
      main_mt5_account_id: id,
      mainMt5AccountKey: id,
      main_mt5_account_key: id,
      mainMt5AccountLogin: login,
      main_mt5_account_login: login,
      mainMt5AccountUpdatedAt: serverTimestamp(),
      main_mt5_account_updated_at: serverTimestamp(),
      updated_at: serverTimestamp(),
    },
    {merge: true},
  );
  return batch.commit();
}

function linkFirstAccountBatch(db, uid, id, login) {
  const batch = writeBatch(db);
  batch.set(
    doc(db, `users/${uid}/mt5_accounts/${id}`),
    account(uid, id, login, {is_primary: true, isPrimary: true}),
  );
  batch.set(
    doc(db, `users/${uid}`),
    {
      mainMt5AccountId: id,
      main_mt5_account_id: id,
      mainMt5AccountKey: id,
      main_mt5_account_key: id,
      mainMt5AccountLogin: login,
      main_mt5_account_login: login,
      mainMt5AccountUpdatedAt: serverTimestamp(),
      main_mt5_account_updated_at: serverTimestamp(),
      updated_at: serverTimestamp(),
    },
    {merge: true},
  );
  return batch.commit();
}

async function run() {
  const testEnv = await initializeTestEnvironment({
    projectId,
    firestore: {
      rules: fs.readFileSync(
        path.join(__dirname, "..", "..", "firestore.rules"),
        "utf8",
      ),
    },
  });
  try {
    await seed(testEnv);

    const signedOut = testEnv.unauthenticatedContext().firestore();
    await assertFails(setMainBatch(signedOut, "user-a", accountA, 10001));

    const selfRegistrant = testEnv
      .authenticatedContext("self-registrant")
      .firestore();
    await assertFails(setDoc(doc(selfRegistrant, "users/self-registrant"), {
      ...user(),
      email: "self-registrant@example.test",
      email_lowercase: "self-registrant@example.test",
    }));
    await assertSucceeds(setDoc(doc(selfRegistrant, "users/self-registrant"), {
      ...user(),
      email: "self-registrant@example.test",
      email_lowercase: "self-registrant@example.test",
      accessStatus: "notSubmitted",
      access_status: "notSubmitted",
    }));
    await assertSucceeds(setDoc(
        doc(selfRegistrant, "accessRequests/self-registrant"),
        {
          userId: "self-registrant",
          user_id: "self-registrant",
          name: "Self Registrant",
          email: "self-registrant@example.test",
          phoneNumber: "123",
          phone_number: "123",
          status: "pendingReview",
          accessStatus: "pendingReview",
          access_status: "pendingReview",
          createdAt: serverTimestamp(),
          created_at: serverTimestamp(),
          updatedAt: serverTimestamp(),
          updated_at: serverTimestamp(),
          reviewedBy: null,
          reviewed_by: null,
          reviewedAt: null,
          reviewed_at: null,
          adminNote: null,
          admin_note: null,
        },
    ));

    const userC = testEnv.authenticatedContext("user-c").firestore();
    await assertSucceeds(
      linkFirstAccountBatch(userC, "user-c", "First_40001", 40001),
    );

    const userA = testEnv.authenticatedContext("user-a").firestore();
    await assertSucceeds(setDoc(doc(userA, "users/user-a"), {
      name: "Updated Rules Test",
      phoneNumber: "456",
      phone_number: "456",
      pending_email: "new@example.test",
      pending_email_requested_at: serverTimestamp(),
      photo_url: "https://example.test/avatar.png",
      avatar_url: "https://example.test/avatar.png",
      updated_at: serverTimestamp(),
    }, {merge: true}));
    await assertFails(setDoc(doc(userA, "users/user-a"), {
      role_id: 1,
      is_admin: true,
    }, {merge: true}));
    await assertSucceeds(setMainBatch(userA, "user-a", accountA, 10001));
    const saved = await assertSucceeds(getDoc(doc(userA, "users/user-a")));
    assert.equal(saved.data().mainMt5AccountId, accountA);

    await assertSucceeds(
      setMainBatch(userA, "user-a", accountB, 10002, accountA),
    );
    const oldAccount = await getDoc(
      doc(userA, `users/user-a/mt5_accounts/${accountA}`),
    );
    const newAccount = await getDoc(
      doc(userA, `users/user-a/mt5_accounts/${accountB}`),
    );
    assert.equal(oldAccount.data().is_primary, false);
    assert.equal(newAccount.data().is_primary, true);

    await assertSucceeds(setDoc(
      doc(userA, "users/user-a/trading_position_applications/application-a"),
      {
        user_id: "user-a",
        requested_position: "recruiter",
        requested_role_id: 770,
        status: "pending",
        mt5_account_id: accountB,
      },
    ));
    await assertFails(setDoc(
      doc(userA, "users/user-a/trading_position_applications/application-b"),
      {
        user_id: "user-a",
        requested_position: "recruiter",
        requested_role_id: 770,
        status: "pending",
        mt5_account_id: accountA,
      },
    ));

    const onboarding = {
      user_id: "user-a",
      status: "pending",
      is_client: true,
      has_deposit: true,
      deposit_amount: 100,
      discord_username: "rules-user",
      broker_uid: "broker-uid",
      broker_email: "broker@example.test",
      trader_note: "review me",
      allow_resubmission: true,
      submitted_at: serverTimestamp(),
      updated_at: serverTimestamp(),
    };
    await assertSucceeds(setDoc(
      doc(userA, "users/user-a/trader_onboarding/profile"),
      onboarding,
    ));
    await assertFails(setDoc(
      doc(userA, "users/user-a/trader_onboarding/profile"),
      {...onboarding, status: "approved"},
      {merge: true},
    ));

    const pendingUser = testEnv.authenticatedContext("user-d").firestore();
    const publicJournal = {
      user_id: "user-d",
      type: "trade",
      source: "manual",
      status: "closed",
      open_date: serverTimestamp(),
      close_date: serverTimestamp(),
      pair: "EURUSD",
      profit_loss: 10,
    };
    await assertSucceeds(setDoc(
        doc(pendingUser, "users/user-d/trading_journals/public-trade"),
        publicJournal,
    ));
    await assertFails(setDoc(
        doc(pendingUser, "users/user-a/trading_journals/other-trade"),
        {...publicJournal, user_id: "user-a"},
    ));
    await assertSucceeds(setDoc(
        doc(pendingUser, "users/user-d/trading_plans/public-plan"),
        {user_id: "user-d", plan_name: "Public personal plan"},
    ));
    await assertSucceeds(setDoc(
        doc(pendingUser, "users/user-d/trading_strategies/public-strategy"),
        {user_id: "user-d", name: "Public personal strategy"},
    ));
    await assertSucceeds(setDoc(
        doc(pendingUser, "users/user-d/psychology_checks/2026-07-13"),
        {
          user_id: "user-d",
          emotion: "Calm",
          confidence: 8,
          discipline: 8,
          completed_at: serverTimestamp(),
        },
    ));
    await assertFails(setDoc(
        doc(pendingUser, "users/user-d/performance_ratings/fake-rating"),
        {user_id: "user-d", overall_score: 100},
    ));
    await assertFails(setDoc(
        doc(pendingUser, "audit_logs/fake-audit"),
        {actor_id: "user-d", action: "pretend_admin_action"},
    ));
    await assertFails(
      linkFirstAccountBatch(pendingUser, "user-d", "Denied_50001", 50001),
    );
    const consultant = testEnv.authenticatedContext("consultant-1").firestore();
    await assertSucceeds(getDoc(doc(consultant, "users/user-a")));
    await assertFails(getDoc(doc(consultant, "users/user-b")));
    await assertFails(setDoc(doc(pendingUser, "users/user-d"), {
      accessStatus: "approved",
      access_status: "approved",
    }, {merge: true}));
    await assertFails(setDoc(doc(pendingUser, "accessRequests/user-d"), {
      status: "withdrawn",
      accessStatus: "withdrawn",
      access_status: "withdrawn",
    }, {merge: true}));
    const admin = testEnv.authenticatedContext("admin-1").firestore();
    await assertFails(setDoc(doc(admin, "accessRequests/user-d"), {
      status: "approved",
      accessStatus: "approved",
      access_status: "approved",
      reviewed_by: "admin-1",
      reviewed_at: serverTimestamp(),
    }, {merge: true}));

    await assertFails(
      setMainBatch(userA, "user-b", "Other_20001", 20001),
    );
    await assertFails(setMainBatch(userA, "user-a", "Missing_999", 999));
    await assertFails(
      setMainBatch(userA, "user-a", "Inactive_30001", 30001),
    );
    await assertFails(
      setMainBatch(userA, "user-a", "Archived_30002", 30002),
    );
    await assertFails(
      setMainBatch(userA, "user-a", "WrongOwner_30003", 30003),
    );

    await assertFails(
      setDoc(
        doc(userA, `users/user-a/mt5_accounts/${accountA}`),
        {mt5_login: 77777},
        {merge: true},
      ),
    );
    const importBase = `users/user-a/mt5_accounts/${accountA}`;
    await assertSucceeds(getDoc(doc(userA, `${importBase}/import_batches/import-hash`)));
    await assertSucceeds(getDoc(doc(userA, `${importBase}/deals/mt5d-1`)));
    await assertSucceeds(getDoc(doc(userA, `${importBase}/balance_transactions/mt5b-1`)));
    await assertFails(setDoc(doc(userA, `${importBase}/import_batches/forged`), {
      status: "completed",
      file_hash: "b".repeat(64),
    }));
    await assertFails(setDoc(doc(userA, `${importBase}/deals/forged`), {
      source_deal_ticket: "forged",
    }));
    await assertFails(setDoc(doc(userA, `${importBase}/balance_transactions/forged`), {
      amount: 999999,
    }));
    const annotationPath =
      `users/user-a/mt5_accounts/${accountA}/journal_annotations/note-1`;
    await assertSucceeds(setDoc(doc(userA, annotationPath), {
      ownerUid: "user-a",
      user_id: "user-a",
      mt5_account_id: accountA,
      account_key: accountA,
      lifecycle_key: "position_10001*Broker-Demo*77",
      trade_id: "trade-1",
      mood: "calm",
      emotion: "focused",
      psychology_notes: "Waited for confirmation",
      confidence: 8,
      discipline: 9,
      followed_trading_plan: true,
      mistake_reason: null,
      lesson_learned: "Keep risk fixed",
      entry_reason: "Retest",
      exit_review: "Plan followed",
      strategy_id: null,
      trading_plan_id: null,
      trading_plan_name: null,
      setup: "Breakout retest",
      notes: "Owner editable",
      screenshot_url: null,
      attachment_urls: [],
      custom_tags: ["disciplined"],
      annotation_version: 1,
      created_at: serverTimestamp(),
      updated_at: serverTimestamp(),
    }));
    await assertFails(setDoc(doc(userA, annotationPath), {
      symbol: "HACKED",
    }, {merge: true}));
    await assertSucceeds(
      getDoc(doc(userA, `users/user-a/mt5_accounts/${accountA}/trades/trade-1`)),
    );
    await assertFails(
      setDoc(
        doc(userA, `users/user-a/mt5_accounts/${accountA}/trades/trade-2`),
        {status: "closed"},
      ),
    );

    const userB = testEnv.authenticatedContext("user-b").firestore();
    await assertFails(
      getDoc(doc(userB, `users/user-a/mt5_accounts/${accountA}/trades/trade-1`)),
    );
    await assertFails(getDoc(doc(userB, `${importBase}/import_batches/import-hash`)));
    await assertFails(getDoc(doc(userB, `${importBase}/deals/mt5d-1`)));
    console.log("MT5 main-account Firestore rule tests passed.");
  } finally {
    await testEnv.cleanup();
  }
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
