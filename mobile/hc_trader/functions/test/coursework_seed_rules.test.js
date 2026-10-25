"use strict";

const fs = require("node:fs");
const path = require("node:path");
const {
  assertFails,
  assertSucceeds,
  initializeTestEnvironment,
} = require("@firebase/rules-unit-testing");
const {
  collection,
  doc,
  getDoc,
  getDocs,
  orderBy,
  query,
  setDoc,
  updateDoc,
  where,
} = require("firebase/firestore");

const projectId = process.env.GCLOUD_PROJECT || "hc-trader-coursework-seed-rules-test";

function user(roleId, overrides = {}) {
  return {
    role_id: roleId,
    role_ids: [roleId],
    is_admin: [1, 2, 3].includes(roleId),
    account_status: "active",
    accessStatus: "approved",
    access_status: "approved",
    is_suspended: false,
    ...overrides,
  };
}

async function main() {
  const rules = fs.readFileSync(path.resolve(__dirname, "../../firestore.rules"), "utf8");
  const testEnv = await initializeTestEnvironment({projectId, firestore: {rules}});
  try {
    await testEnv.withSecurityRulesDisabled(async (context) => {
      const db = context.firestore();
      await Promise.all([
        setDoc(doc(db, "users/student-a"), user(750)),
        setDoc(doc(db, "users/admin-a"), user(1)),
        setDoc(doc(db, "users/reviewer-a"), user(501)),
        setDoc(doc(db, "users/disabled-a"), user(750, {account_status: "disabled"})),
        setDoc(doc(db, "coursework_themes/CW-001"), {
          id: "CW-001",
          code: "CW-001",
          title: {en: "Published theme", zh: "已发布主题"},
          status: "published",
          isActive: true,
          isAssignable: true,
          sortOrder: 1,
          managedBySeed: true,
          seedVersion: "coursework_100_themes_v1",
          contentHash: "a".repeat(64),
          createdBy: "system_seed",
          updatedBy: "system_seed",
        }),
        setDoc(doc(db, "coursework_themes/CW-001/questions/Q1"), {
          id: "Q1",
          themeId: "CW-001",
          order: 1,
          status: "published",
          managedBySeed: true,
          seedVersion: "coursework_100_themes_v1",
          contentHash: "b".repeat(64),
          createdBy: "system_seed",
        }),
        setDoc(doc(db, "coursework_theme_keys/CW-001"), {
          themeId: "CW-001",
          seedVersion: "coursework_100_themes_v1",
        }),
        setDoc(doc(db, "coursework_theme_keys/CW-001/questions/Q1"), {
          themeId: "CW-001",
          questionId: "Q1",
          correctOptionIds: ["B"],
        }),
        setDoc(doc(db, "coursework_themes/CW-002"), {
          id: "CW-002",
          code: "CW-002",
          status: "draft",
          isActive: true,
          sortOrder: 2,
        }),
        setDoc(doc(db, "coursework_themes/CW-002/questions/Q1"), {
          id: "Q1",
          themeId: "CW-002",
          order: 1,
        }),
        setDoc(doc(db, "coursework_themes/CW-003"), {
          id: "CW-003",
          code: "CW-003",
          status: "published",
          isActive: false,
          sortOrder: 3,
        }),
      ]);
    });

    const student = testEnv.authenticatedContext("student-a").firestore();
    const admin = testEnv.authenticatedContext("admin-a").firestore();
    const reviewer = testEnv.authenticatedContext("reviewer-a").firestore();
    const disabled = testEnv.authenticatedContext("disabled-a").firestore();
    const unauthenticated = testEnv.unauthenticatedContext().firestore();

    await assertSucceeds(getDoc(doc(student, "coursework_themes/CW-001")));
    await assertSucceeds(getDoc(doc(student, "coursework_themes/CW-001/questions/Q1")));
    await assertSucceeds(getDocs(query(
        collection(student, "coursework_themes"),
        where("status", "==", "published"),
        where("isActive", "==", true),
        orderBy("sortOrder"),
    )));
    await assertFails(getDoc(doc(student, "coursework_themes/CW-002")));
    await assertFails(getDoc(doc(student, "coursework_themes/CW-002/questions/Q1")));
    await assertFails(getDoc(doc(student, "coursework_themes/CW-003")));
    await assertFails(getDoc(doc(disabled, "coursework_themes/CW-001")));
    await assertFails(getDoc(doc(unauthenticated, "coursework_themes/CW-001")));

    await assertFails(getDoc(doc(student, "coursework_theme_keys/CW-001")));
    await assertFails(getDoc(doc(student, "coursework_theme_keys/CW-001/questions/Q1")));
    await assertSucceeds(getDoc(doc(reviewer, "coursework_theme_keys/CW-001")));
    await assertSucceeds(getDoc(doc(reviewer, "coursework_theme_keys/CW-001/questions/Q1")));

    await assertFails(setDoc(doc(student, "coursework_themes/student-theme"), {
      status: "published",
      isActive: true,
    }));
    await assertFails(updateDoc(doc(student, "coursework_themes/CW-001"), {title: {en: "Forged"}}));
    await assertSucceeds(setDoc(doc(admin, "coursework_themes/manual-theme"), {
      status: "draft",
      isActive: false,
    }));
    await assertSucceeds(updateDoc(doc(admin, "coursework_themes/CW-001"), {
      title: {en: "Admin reviewed", zh: "管理员已审核"},
      updatedBy: "admin-a",
    }));
    await assertFails(updateDoc(doc(admin, "coursework_themes/CW-001"), {
      contentHash: "c".repeat(64),
    }));
    await assertFails(setDoc(doc(admin, "coursework_themes/manual-theme/questions/Q1"), {
      id: "Q1",
      order: 1,
      correctOptionIds: ["A"],
    }));
    await assertFails(setDoc(doc(admin, "coursework_theme_keys/CW-001/questions/Q2"), {
      correctOptionIds: ["A"],
    }));

    console.log("Coursework seed Firestore Rules tests passed.");
  } finally {
    await testEnv.cleanup();
  }
}

main().catch((error) => {
  console.error(error.stack || error);
  process.exitCode = 1;
});
