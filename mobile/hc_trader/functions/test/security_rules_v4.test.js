"use strict";

const fs = require("node:fs");
const path = require("node:path");
const {
  assertFails,
  assertSucceeds,
  initializeTestEnvironment,
} = require("@firebase/rules-unit-testing");
const {collection, deleteDoc, doc, getDoc, getDocs, query, serverTimestamp, setDoc, updateDoc, where, writeBatch} =
  require("firebase/firestore");

const projectId = "hc-trader-v4-security-rules-test";

function user(roleId = 750) {
  return {
    name: "Rules User",
    email: `role-${roleId}@example.test`,
    email_lowercase: `role-${roleId}@example.test`,
    role_id: roleId,
    role_ids: [roleId],
    role_name: roleId === 501 ? "Market Analyst" : "Trader",
    is_admin: [1, 2, 3].includes(roleId),
    status: 1,
    account_status: "active",
    is_suspended: false,
    accessStatus: "approved",
    access_status: "approved",
  };
}

function signalDocument(createdBy, overrides = {}) {
  return {
    created_by: createdBy,
    published_by: createdBy,
    trading_pair: "XAUUSD",
    immediate_action: "BUY",
    entry_price: "2300",
    stop_loss: "2290",
    target_1: "2310",
    signal_title: "Gold setup",
    signal_title_i18n: {en: "Gold setup"},
    disclaimer: "Educational content only.",
    disclaimer_i18n: {en: "Educational content only."},
    primary_language: "en",
    available_languages: ["en"],
    status: 1,
    lifecycle_status: "active",
    version: 0,
    last_update_id: "publication-v0",
    last_audit_id: "publication-v0",
    ...overrides,
  };
}

async function publishSignal(db, signalId, data) {
  const batch = writeBatch(db);
  batch.set(doc(db, `trading_signals/${signalId}`), {
    ...data,
    created_at: serverTimestamp(),
    updated_at: serverTimestamp(),
    published_at: serverTimestamp(),
  });
  batch.set(
      doc(db, `trading_signals/${signalId}/updates/publication-v0`),
      {
        update_id: "publication-v0",
        signal_id: signalId,
        update_type: "published",
        lifecycle_from: "draft",
        lifecycle_to: data.lifecycle_status,
        version_from: -1,
        version_to: 0,
        primary_language: data.primary_language,
        available_languages: data.available_languages,
        created_by: data.created_by,
        notification_requested: false,
        notification_sent: false,
        is_correction: false,
        effective_at: serverTimestamp(),
        created_at: serverTimestamp(),
      },
  );
  batch.set(
      doc(db, `trading_signals/${signalId}/audit_logs/publication-v0`),
      {
        audit_id: "publication-v0",
        update_id: "publication-v0",
        signal_id: signalId,
        action: "published",
        actor_id: data.created_by,
        lifecycle_from: "draft",
        lifecycle_to: data.lifecycle_status,
        version_from: -1,
        version_to: 0,
        created_at: serverTimestamp(),
      },
  );
  return batch.commit();
}

function analysisDocument(createdBy, overrides = {}) {
  return {
    created_by: createdBy,
    published_by: createdBy,
    title: "Gold outlook",
    title_i18n: {en: "Gold outlook"},
    market: "XAUUSD",
    market_overview: "Price remains constructive.",
    market_overview_i18n: {en: "Price remains constructive."},
    trading_plan: "Wait for confirmation.",
    trading_plan_i18n: {en: "Wait for confirmation."},
    primary_language: "en",
    available_languages: ["en"],
    status: "published",
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
      await setDoc(doc(db, "users/admin-a"), user(1));
      await setDoc(doc(db, "users/admin-with-overrides"), {
        ...user(1),
        permission_overrides: {"trading_exam.manage": true},
      });
      await setDoc(doc(db, "users/subadmin-with-overrides"), {
        ...user(2),
        permission_overrides: {"trading_exam.manage": true},
      });
      await setDoc(doc(db, "users/subadmin-report-denied"), {
        ...user(2),
        permission_overrides: {"coursework.viewReports": false},
      });
      await setDoc(doc(db, "users/analyst-a"), user(501));
      await setDoc(doc(db, "users/analyst-applicant"), {
        ...user(501),
        email: "analyst-applicant@example.test",
        email_lowercase: "analyst-applicant@example.test",
        accessStatus: "unapproved",
        access_status: "unapproved",
      });
      await setDoc(doc(db, "accessRequests/analyst-applicant"), {
        userId: "analyst-applicant",
        user_id: "analyst-applicant",
        name: "Analyst Applicant",
        email: "analyst-applicant@example.test",
        phoneNumber: "+60123456789",
        phone_number: "+60123456789",
        status: "moreInfoRequired",
        accessStatus: "moreInfoRequired",
        access_status: "moreInfoRequired",
        createdAt: "original-created-at",
        created_at: "original-created-at",
        updatedAt: "original-updated-at",
        updated_at: "original-updated-at",
        reviewedBy: "admin-a",
        reviewed_by: "admin-a",
        reviewedAt: "original-reviewed-at",
        reviewed_at: "original-reviewed-at",
        adminNote: "Please provide an updated phone number.",
        admin_note: "Please provide an updated phone number.",
      });
      await setDoc(doc(db, "users/provider-a"), user(201));
      await setDoc(doc(db, "users/missing-role"), {
        name: "Missing Role",
        email: "missing-role@example.test",
        account_status: "active",
        is_suspended: false,
      });
      await setDoc(doc(db, "users/subadmin-publisher"), {
        ...user(2),
        permission_overrides: {"signals.publishOwn": true},
      });
      await setDoc(doc(db, "referral_relationships/user-a"), {
        user_id: "user-a",
        referrer_id: "upline-a",
        upline_id: "upline-a",
      });
      await setDoc(doc(db, "account_deletion_jobs/hash-a"), {
        status: "failed",
      });
      await setDoc(doc(db, "personality_question_sets/v1"), {
        status: "published",
      });
      await setDoc(doc(db, "personality_question_sets/v1/questions/q1"), {
        text: "Published question",
      });
      await setDoc(doc(db, "coursework_assignments/assignment-a"), {
        assigned_to_user_id: "user-a",
        status: "assigned",
      });
      await setDoc(doc(db, "coursework_assignments/assignment-other"), {
        assigned_to_user_id: "other-user",
        status: "assigned",
      });
      await setDoc(doc(db, "coursework_attempts/attempt-a"), {
        attempt_id: "attempt-a",
        assignment_id: "assignment-a",
        user_id: "user-a",
        status: "draft",
      });
      await setDoc(doc(db, "coursework_attempts/attempt-other"), {
        attempt_id: "attempt-other",
        assignment_id: "assignment-other",
        user_id: "other-user",
        status: "submitted",
      });
      await setDoc(doc(db, "coursework_attempt_keys/attempt-a"), {
        correct_answers: {q1: "a"},
      });
      await setDoc(doc(db, "coursework_themes/theme-a"), {
        theme_code: "CW-001",
        status: "published",
      });
      await setDoc(doc(db, "assessment_settings/current"), {
        coursework_weight: 70,
        final_exam_weight: 30,
      });
      await setDoc(doc(db, "users/user-a/trading_exam_attempts/legacy-a"), {
        user_id: "user-a",
        status: "completed",
      });
    });

    const ownerDb = testEnv.authenticatedContext("user-a").firestore();
    const adminWithOverridesDb = testEnv
        .authenticatedContext("admin-with-overrides").firestore();
    const subadminWithOverridesDb = testEnv
        .authenticatedContext("subadmin-with-overrides").firestore();
    const subadminReportDeniedDb = testEnv
        .authenticatedContext("subadmin-report-denied").firestore();
    await assertSucceeds(getDocs(collection(
        adminWithOverridesDb,
        "coursework_themes",
    )));
    await assertSucceeds(getDocs(collection(
        subadminWithOverridesDb,
        "coursework_themes",
    )));
    await assertSucceeds(getDocs(collection(
        subadminWithOverridesDb,
        "coursework_assignments",
    )));
    await assertSucceeds(getDocs(collection(
        subadminWithOverridesDb,
        "coursework_attempts",
    )));
    await assertFails(getDocs(collection(
        subadminReportDeniedDb,
        "coursework_themes",
    )));
    await assertFails(updateDoc(doc(ownerDb, "users/user-a"), {
      referrer_id: "attacker",
    }));
    for (const field of [
      "referrerId",
      "uplineId",
      "sponsorId",
      "sponsor_id",
      "referralCodeUsed",
      "referredBy",
      "referralRelationshipId",
      "referral_relationship_id",
    ]) {
      await assertFails(updateDoc(doc(ownerDb, "users/user-a"), {
        [field]: "attacker",
      }));
    }
    const newUserDb = testEnv.authenticatedContext("user-new").firestore();
    await assertFails(setDoc(doc(newUserDb, "users/user-new"), {
      ...user(),
      referrer_id: "attacker",
    }));
    await assertFails(setDoc(doc(newUserDb, "users/user-new"), {
      ...user(),
      referralRelationshipId: "forged-relationship",
    }));
    await assertSucceeds(getDoc(
        doc(ownerDb, "referral_relationships/user-a"),
    ));
    await assertFails(setDoc(
        doc(ownerDb, "referral_relationships/user-a"),
        {user_id: "user-a", referrer_id: "attacker"},
    ));
    await assertSucceeds(getDoc(doc(ownerDb, "coursework_assignments/assignment-a")));
    await assertFails(getDoc(doc(ownerDb, "coursework_assignments/assignment-other")));
    await assertSucceeds(getDoc(doc(ownerDb, "coursework_attempts/attempt-a")));
    await assertFails(getDoc(doc(ownerDb, "coursework_attempts/attempt-other")));
    // Mirror the four reads performed by CourseworkRepository.dashboardFor.
    await assertSucceeds(getDocs(query(
        collection(ownerDb, "coursework_assignments"),
        where("assigned_to_user_id", "==", "user-a"),
    )));
    await assertSucceeds(getDocs(query(
        collection(ownerDb, "coursework_attempts"),
        where("user_id", "==", "user-a"),
    )));
    await assertSucceeds(getDocs(collection(
        ownerDb,
        "users/user-a/trading_exam_attempts",
    )));
    await assertSucceeds(getDoc(doc(ownerDb, "assessment_settings/current")));
    await assertFails(getDocs(collection(ownerDb, "coursework_assignments")));
    await assertFails(getDocs(query(
        collection(ownerDb, "coursework_assignments"),
        where("assigned_to_user_id", "==", "other-user"),
    )));
    await assertFails(getDoc(doc(ownerDb, "coursework_attempt_keys/attempt-a")));
    await assertSucceeds(updateDoc(doc(ownerDb, "coursework_attempts/attempt-a"), {
      status: "draft",
      current_question_number: 2,
      last_saved_at: new Date(),
      updated_at: new Date(),
    }));
    await assertFails(updateDoc(doc(ownerDb, "coursework_attempts/attempt-a"), {
      manual_score: 100,
    }));
    await assertSucceeds(setDoc(doc(ownerDb, "coursework_attempts/attempt-a/answers/q1"), {
      question_id: "q1",
      attempt_id: "attempt-a",
      question_number: 1,
      question_type: "singleChoice",
      selected_option_ids: ["a"],
      text_answer: "",
      selected_chart_zone: "",
      market_analysis_data: {},
      order_management_data: {},
      attachments: [],
      is_completed: true,
      is_flagged: false,
      maximum_mark: 10,
      client_version: 1,
      saved_at: new Date(),
      updated_at: new Date(),
    }));
    await assertFails(updateDoc(doc(ownerDb, "coursework_attempts/attempt-a/answers/q1"), {
      manual_mark: 10,
    }));
    await assertFails(deleteDoc(
        doc(ownerDb, "referral_relationships/user-a"),
    ));
    await assertFails(getDoc(doc(ownerDb, "account_deletion_jobs/hash-a")));
    await assertSucceeds(getDoc(
        doc(ownerDb, "personality_question_sets/v1/questions/q1"),
    ));
    await assertSucceeds(setDoc(doc(
        ownerDb,
        "users/user-a/personality_assessments/attempt-a",
    ), {
      user_id: "user-a",
      attempt_id: "attempt-a",
      status: "inProgress",
      answers: {ei_01: 7},
    }));
    await assertFails(setDoc(doc(
        ownerDb,
        "users/admin-a/personality_assessments/forged",
    ), {
      user_id: "admin-a",
      attempt_id: "forged",
      status: "inProgress",
    }));
    await assertSucceeds(updateDoc(doc(
        ownerDb,
        "users/user-a/personality_assessments/attempt-a",
    ), {
      status: "completed",
      result: {type: "ISTP"},
    }));
    await assertFails(updateDoc(doc(
        ownerDb,
        "users/user-a/personality_assessments/attempt-a",
    ), {
      result: {type: "ENTJ"},
    }));
    await assertFails(deleteDoc(doc(
        ownerDb,
        "users/user-a/personality_assessments/attempt-a",
    )));
    await assertFails(setDoc(
        doc(ownerDb, "users/user-a/trading_exam_attempts/exam-2026-07-13"),
        {
          user_id: "user-a",
          status: "completed",
          total_questions: 5,
          score: 5,
        },
    ));
    await assertFails(setDoc(
        doc(ownerDb, "market_analysts/analyst-a/followers/user-a"),
        {analyst_id: "analyst-a", follower_id: "user-a"},
    ));

    const adminDb = testEnv.authenticatedContext("admin-a").firestore();
    await assertSucceeds(getDoc(
        doc(adminDb, "account_deletion_jobs/hash-a"),
    ));
    await assertFails(updateDoc(
        doc(adminDb, "personality_question_sets/v1"),
        {status: "draft"},
    ));
    await assertFails(updateDoc(
        doc(adminDb, "personality_question_sets/v1/questions/q1"),
        {text: "Changed after publication"},
    ));
    await assertFails(setDoc(doc(adminDb, "trading_exam_questions/no-chart"), {
      question_text: "No chart",
      question_type: "multiple_choice",
      version: 1,
      status: "published",
    }));
    await assertSucceeds(setDoc(doc(adminDb, "trading_exam_questions/chart"), {
      question_text: "Chart question",
      question_type: "chart_multiple_choice",
      version: 1,
      status: "published",
      chart_image_url: "https://example.test/chart.png",
    }));
    await assertSucceeds(setDoc(doc(adminDb, "coursework_themes/theme-a"), {
      theme_code: "CW-TEST-001",
      title: "Rules theme",
      status: "draft",
    }));

    await assertSucceeds(publishSignal(
        adminDb,
        "admin-signal",
        signalDocument("admin-a", {
          signal_title: "黄金交易计划",
          signal_title_i18n: {zh: "黄金交易计划"},
          disclaimer: "仅供教育用途。",
          disclaimer_i18n: {zh: "仅供教育用途。"},
          primary_language: "zh",
          available_languages: ["zh"],
        }),
    ));
    await assertSucceeds(setDoc(
        doc(adminDb, "market_analyses/admin-analysis"),
        analysisDocument("admin-a", {
          title: "黄金市场展望",
          title_i18n: {zh: "黄金市场展望"},
          market_overview: "价格结构保持强势。",
          market_overview_i18n: {zh: "价格结构保持强势。"},
          trading_plan: "等待确认后入场。",
          trading_plan_i18n: {zh: "等待确认后入场。"},
          primary_language: "zh",
          available_languages: ["zh"],
        }),
    ));
    await assertFails(setDoc(doc(adminDb, "audit_logs/client-forged"), {
      actor_id: "admin-a",
      action: "create",
      module: "trading_signals",
    }));

    const providerDb = testEnv.authenticatedContext("provider-a").firestore();
    await assertSucceeds(publishSignal(
        providerDb,
        "provider-signal",
        signalDocument("provider-a"),
    ));
    await assertSucceeds(setDoc(
        doc(providerDb, "trading_signals/provider-draft"),
        {
          created_by: "provider-a",
          primary_language: "en",
          available_languages: ["en"],
          lifecycle_status: "draft",
          status: 0,
        },
    ));
    await assertFails(setDoc(
        doc(providerDb, "trading_signals/incomplete-active"),
        {
          created_by: "provider-a",
          published_by: "provider-a",
          primary_language: "en",
          available_languages: ["en"],
          lifecycle_status: "active",
          status: 1,
        },
    ));
    await assertFails(setDoc(
        doc(providerDb, "market_analyses/provider-analysis"),
        analysisDocument("provider-a"),
    ));
    await assertFails(setDoc(
        doc(providerDb, "trading_signals/forged-owner"),
        signalDocument("admin-a"),
    ));
    await assertFails(updateDoc(
        doc(providerDb, "trading_signals/provider-signal"),
        {created_by: "admin-a", updated_by: "provider-a"},
    ));

    await assertFails(setDoc(
        doc(ownerDb, "trading_signals/trader-signal"),
        signalDocument("user-a"),
    ));
    await assertFails(setDoc(
        doc(ownerDb, "market_analyses/trader-analysis"),
        analysisDocument("user-a"),
    ));

    const missingRoleDb = testEnv
        .authenticatedContext("missing-role").firestore();
    await assertFails(setDoc(
        doc(missingRoleDb, "trading_signals/missing-role-signal"),
        signalDocument("missing-role"),
    ));

    const subadminPublisherDb = testEnv
        .authenticatedContext("subadmin-publisher").firestore();
    await assertSucceeds(publishSignal(
        subadminPublisherDb,
        "subadmin-signal",
        signalDocument("subadmin-publisher"),
    ));

    const analystDb = testEnv.authenticatedContext("analyst-a").firestore();
    const analystApplicantDb = testEnv
        .authenticatedContext("analyst-applicant").firestore();
    const reapplication = writeBatch(analystApplicantDb);
    const reappliedAt = serverTimestamp();
    reapplication.update(doc(analystApplicantDb, "users/analyst-applicant"), {
      name: "Analyst Applicant",
      email: "analyst-applicant@example.test",
      email_lowercase: "analyst-applicant@example.test",
      phoneNumber: "+60111222333",
      phone_number: "+60111222333",
      accessStatus: "pendingReview",
      access_status: "pendingReview",
      accessRequestedAt: reappliedAt,
      access_requested_at: reappliedAt,
      accessUpdatedAt: reappliedAt,
      access_updated_at: reappliedAt,
      updated_at: reappliedAt,
    });
    reapplication.set(doc(
        analystApplicantDb,
        "users/analyst-applicant/access_application_history/previous",
    ), {
      userId: "analyst-applicant",
      user_id: "analyst-applicant",
      status: "moreInfoRequired",
      accessStatus: "moreInfoRequired",
      access_status: "moreInfoRequired",
      archivedAt: reappliedAt,
      archived_at: reappliedAt,
    });
    reapplication.set(doc(
        analystApplicantDb,
        "accessRequests/analyst-applicant",
    ), {
      userId: "analyst-applicant",
      user_id: "analyst-applicant",
      name: "Analyst Applicant",
      email: "analyst-applicant@example.test",
      phoneNumber: "+60111222333",
      phone_number: "+60111222333",
      status: "pendingReview",
      accessStatus: "pendingReview",
      access_status: "pendingReview",
      createdAt: "original-created-at",
      created_at: "original-created-at",
      updatedAt: reappliedAt,
      updated_at: reappliedAt,
      reviewedBy: null,
      reviewed_by: null,
      reviewedAt: null,
      reviewed_at: null,
      adminNote: null,
      admin_note: null,
    }, {merge: true});
    await assertSucceeds(reapplication.commit());
    await assertFails(updateDoc(doc(analystDb, "users/analyst-a"), {
      accessStatus: "pendingReview",
      access_status: "pendingReview",
    }));
    await assertSucceeds(setDoc(
        doc(analystDb, "market_analyses/analyst-analysis"),
        analysisDocument("analyst-a"),
    ));
    await assertFails(setDoc(
        doc(analystDb, "trading_signals/analyst-signal"),
        signalDocument("analyst-a"),
    ));
    await assertFails(setDoc(doc(
        analystDb,
        "market_analyst_profiles/analyst-a",
    ), {
      display_name: "Analyst",
      verified: false,
      follower_count: 20,
    }));
    await assertSucceeds(setDoc(doc(
        analystDb,
        "market_analyst_profiles/analyst-a",
    ), {
      display_name: "Analyst",
      verified: false,
      follower_count: 0,
    }));
    await assertSucceeds(getDoc(doc(analystDb, "coursework_attempts/attempt-a")));
    await assertFails(setDoc(doc(analystDb, "coursework_themes/analyst-theme"), {
      theme_code: "CW-ANALYST-001",
      title: "Unauthorised theme",
    }));

    console.log("Security rules v4 tests passed.");
  } finally {
    await testEnv.cleanup();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
