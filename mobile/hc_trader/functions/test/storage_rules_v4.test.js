"use strict";

const fs = require("node:fs");
const path = require("node:path");
const {
  assertFails,
  assertSucceeds,
  initializeTestEnvironment,
} = require("@firebase/rules-unit-testing");
const {doc, setDoc} = require("firebase/firestore");
const {ref, uploadBytes} = require("firebase/storage");

const projectId = process.env.GCLOUD_PROJECT ||
  "hc-trader-v4-storage-rules-test";

async function main() {
  const firestoreRules = fs.readFileSync(
      path.resolve(__dirname, "../../firestore.rules"),
      "utf8",
  );
  const storageRules = fs.readFileSync(
      path.resolve(__dirname, "../../storage.rules"),
      "utf8",
  );
  const testEnv = await initializeTestEnvironment({
    projectId,
    firestore: {rules: firestoreRules},
    storage: {rules: storageRules},
  });
  try {
    await testEnv.withSecurityRulesDisabled(async (context) => {
      await setDoc(doc(context.firestore(), "users/user-a"), {
        role_id: 750,
        role_ids: [750],
        is_admin: false,
        account_status: "active",
        is_suspended: false,
      });
      for (const [id, roleId] of [
        ["admin-a", 1],
        ["provider-a", 201],
        ["analyst-a", 501],
      ]) {
        await setDoc(doc(context.firestore(), `users/${id}`), {
          role_id: roleId,
          role_ids: [roleId],
          is_admin: roleId === 1,
          account_status: "active",
          is_suspended: false,
        });
      }
      await setDoc(doc(context.firestore(), "users/subadmin-publisher"), {
        role_id: 2,
        role_ids: [2],
        is_admin: true,
        account_status: "active",
        is_suspended: false,
        permission_overrides: {"signals.publishOwn": true},
      });
      await setDoc(doc(context.firestore(), "users/suspended-provider"), {
        role_id: 201,
        role_ids: [201],
        is_admin: false,
        account_status: "suspended",
        is_suspended: true,
      });
      await setDoc(doc(context.firestore(), "users/suspended-admin"), {
        role_id: 1,
        role_ids: [1],
        is_admin: true,
        account_status: "suspended",
        is_suspended: true,
      });
      await setDoc(doc(context.firestore(), "coursework_attempts/attempt-a"), {
        user_id: "user-a",
        status: "draft",
      });
      await setDoc(doc(context.firestore(), "coursework_attempts/attempt-locked"), {
        user_id: "user-a",
        status: "submitted",
      });
    });
    const storage = testEnv.authenticatedContext("user-a").storage();
    const data = new Uint8Array([137, 80, 78, 71]);
    const validMetadata = {
      contentType: "image/png",
      customMetadata: {
        ownerUid: "user-a",
        quizId: "quiz-a",
        attemptId: "exam-2026-07-13",
        source: "daily_quiz",
      },
    };
    await assertSucceeds(uploadBytes(
        ref(storage, "quiz_submissions/quiz-a/user-a/exam-2026-07-13/a.png"),
        data,
        validMetadata,
    ));
    await assertFails(uploadBytes(
        ref(storage, "quiz_submissions/quiz-a/user-a/exam-2026-07-13/b.png"),
        data,
        {
          ...validMetadata,
          customMetadata: {...validMetadata.customMetadata, ownerUid: "other"},
        },
    ));
    const courseworkMetadata = {
      contentType: "image/png",
      customMetadata: {
        ownerUid: "user-a",
        attemptId: "attempt-a",
        questionId: "q4",
        checksum: "a".repeat(64),
        source: "coursework",
      },
    };
    await assertSucceeds(uploadBytes(
        ref(storage, "coursework_submissions/user-a/attempt-a/q4/evidence.png"),
        data,
        courseworkMetadata,
    ));
    await assertFails(uploadBytes(
        ref(storage, "coursework_submissions/user-a/attempt-a/q4/bad-owner.png"),
        data,
        {...courseworkMetadata, customMetadata: {...courseworkMetadata.customMetadata, ownerUid: "other"}},
    ));
    await assertFails(uploadBytes(
        ref(storage, "coursework_submissions/user-a/attempt-locked/q4/locked.png"),
        data,
        {...courseworkMetadata, customMetadata: {...courseworkMetadata.customMetadata, attemptId: "attempt-locked"}},
    ));
    await assertFails(uploadBytes(
        ref(storage, "quiz_submissions/quiz-a/user-a/exam-2026-07-13/c.pdf"),
        data,
        {...validMetadata, contentType: "application/pdf"},
    ));

    const imageMetadata = {contentType: "image/png"};
    const suspendedProviderStorage = testEnv
        .authenticatedContext("suspended-provider").storage();
    await assertFails(uploadBytes(
        ref(
            suspendedProviderStorage,
            "trading_signals/suspended-provider/chart.png",
        ),
        data,
        imageMetadata,
    ));
    const suspendedAdminStorage = testEnv
        .authenticatedContext("suspended-admin").storage();
    await assertFails(uploadBytes(
        ref(
            suspendedAdminStorage,
            "trading_signals/provider-a/suspended-admin-chart.png",
        ),
        data,
        imageMetadata,
    ));
    const providerStorage = testEnv
        .authenticatedContext("provider-a").storage();
    await assertSucceeds(uploadBytes(
        ref(providerStorage, "trading_signals/provider-a/chart.png"),
        data,
        imageMetadata,
    ));
    await assertFails(uploadBytes(
        ref(providerStorage, "trading_signals/user-a/chart.png"),
        data,
        imageMetadata,
    ));
    await assertFails(uploadBytes(
        ref(providerStorage, "market_analyses/provider-a/chart.png"),
        data,
        imageMetadata,
    ));

    const analystStorage = testEnv
        .authenticatedContext("analyst-a").storage();
    await assertSucceeds(uploadBytes(
        ref(analystStorage, "market_analyses/analyst-a/chart.png"),
        data,
        imageMetadata,
    ));
    await assertFails(uploadBytes(
        ref(analystStorage, "trading_signals/analyst-a/chart.png"),
        data,
        imageMetadata,
    ));

    await assertFails(uploadBytes(
        ref(storage, "trading_signals/user-a/chart.png"),
        data,
        imageMetadata,
    ));

    const adminStorage = testEnv.authenticatedContext("admin-a").storage();
    await assertSucceeds(uploadBytes(
        ref(adminStorage, "trading_signals/provider-a/admin-chart.png"),
        data,
        imageMetadata,
    ));
    await assertSucceeds(uploadBytes(
        ref(adminStorage, "market_analyses/analyst-a/admin-chart.png"),
        data,
        imageMetadata,
    ));

    const delegatedStorage = testEnv
        .authenticatedContext("subadmin-publisher").storage();
    await assertSucceeds(uploadBytes(
        ref(
            delegatedStorage,
            "trading_signals/subadmin-publisher/chart.png",
        ),
        data,
        imageMetadata,
    ));
    console.log("Storage rules v4 tests passed.");
  } finally {
    await testEnv.cleanup();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
