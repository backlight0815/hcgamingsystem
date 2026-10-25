"use strict";

const {getFirestore, FieldValue, Timestamp} = require("firebase-admin/firestore");
const {getDownloadURL, getStorage} = require("firebase-admin/storage");
const {HttpsError, onCall} = require("firebase-functions/v2/https");
const {effectiveRoleIds, isAdminRole} = require("./role_permissions");

const REGION = "us-central1";
const MAX_QUESTIONS = 10;
const SCREENSHOT_TYPE = "chart_screenshot_analysis";

exports.submitDailyQuizAttempt = onCall(
    {region: REGION, timeoutSeconds: 120, memory: "256MiB"},
    async (request) => {
      const uid = request.auth?.uid;
      if (!uid) throw new HttpsError("unauthenticated", "Sign in first.");
      const payload = request.data || {};
      const offset = boundedNumber(payload.timezoneOffsetMinutes, -840, 840, 0);
      const expectedAttemptId = dailyAttemptId(new Date(), offset);
      const attemptId = text(payload.attemptId);
      if (attemptId !== expectedAttemptId) {
        throw new HttpsError(
            "invalid-argument",
            "The daily quiz attempt identifier is invalid.",
        );
      }
      const submitted = Array.isArray(payload.questions) ? payload.questions : [];
      if (!submitted.length || submitted.length > MAX_QUESTIONS) {
        throw new HttpsError("invalid-argument", "Submit 1 to 10 quiz answers.");
      }
      const ids = submitted.map((item) => text(item?.questionId));
      if (ids.some((id) => !id) || new Set(ids).size !== ids.length) {
        throw new HttpsError("invalid-argument", "Quiz questions are invalid.");
      }

      const db = getFirestore();
      const snapshots = await Promise.all(
          ids.map((id) => db.collection("trading_exam_questions").doc(id).get()),
      );
      const results = [];
      const answers = {};
      let score = 0;
      let needsReview = false;

      for (let index = 0; index < snapshots.length; index++) {
        const snapshot = snapshots[index];
        const answer = submitted[index] || {};
        if (!snapshot.exists) {
          throw new HttpsError("not-found", "A quiz question is unavailable.");
        }
        const question = snapshot.data() || {};
        if (!["approved", "published"].includes(text(question.status, "approved"))) {
          throw new HttpsError("failed-precondition", "A quiz question is closed.");
        }
        const version = positiveInteger(question.version, 1);
        if (positiveInteger(answer.questionVersion, 1) !== version) {
          throw new HttpsError(
              "failed-precondition",
              "A quiz question changed. Refresh before submitting.",
          );
        }
        const deadline = question.deadline;
        if (deadline instanceof Timestamp && deadline.toDate() < new Date()) {
          throw new HttpsError("deadline-exceeded", "This quiz has closed.");
        }
        const type = text(question.question_type || question.type, "multiple_choice");
        const options = normalizedOptions(question.options);
        let selectedIndex = -1;
        let correctIndex = -1;
        let isCorrect = false;
        let writtenExplanation = "";
        let screenshotUrl = "";
        let screenshotStoragePath = "";
        let reviewStatus = "not_required";

        const choiceRequired = type !== SCREENSHOT_TYPE;
        const screenshotRequired = type === SCREENSHOT_TYPE ||
          question.requires_screenshot === true;
        const writtenRequired = type === SCREENSHOT_TYPE ||
          question.requires_written_explanation === true;
        if (choiceRequired) {
          selectedIndex = Number(answer.selectedIndex);
          if (!Number.isInteger(selectedIndex) ||
              selectedIndex < 0 || selectedIndex >= options.length) {
            throw new HttpsError("invalid-argument", "Answer every question.");
          }
          correctIndex = options.findIndex((option) => option.isCorrect);
          isCorrect = correctIndex >= 0 && selectedIndex === correctIndex;
          if (isCorrect) score++;
          answers[snapshot.id] = selectedIndex;
        }
        if (screenshotRequired || writtenRequired) {
          needsReview = true;
          reviewStatus = "pending_review";
        }
        if (writtenRequired) {
          writtenExplanation = text(answer.writtenExplanation);
          const minimum = boundedNumber(
              question.minimum_explanation_characters,
              20,
              1000,
              40,
          );
          const maximum = boundedNumber(
              question.maximum_explanation_characters,
              minimum,
              5000,
              2000,
          );
          if (writtenExplanation.length < minimum ||
              writtenExplanation.length > maximum) {
            throw new HttpsError(
                "invalid-argument",
                `The chart explanation must contain ${minimum}-${maximum} characters.`,
            );
          }
        }
        if (screenshotRequired) {
          screenshotStoragePath = text(answer.screenshotStoragePath);
          const prefix = `quiz_submissions/${snapshot.id}/${uid}/${attemptId}/`;
          if (!screenshotStoragePath.startsWith(prefix)) {
            throw new HttpsError(
                "permission-denied",
                "The quiz screenshot does not belong to this attempt.",
            );
          }
          screenshotUrl = await verifiedScreenshotUrl({
            path: screenshotStoragePath,
            uid,
            quizId: snapshot.id,
            attemptId,
          });
        }

        results.push({
          question_id: snapshot.id,
          question_text: question.question_text || "",
          question_text_i18n: question.question_text_i18n || {},
          options: options.map((option) => option.text),
          options_i18n: options.map((option) => option.i18n),
          selected_index: selectedIndex,
          correct_index: correctIndex,
          is_correct: isCorrect,
          explanation: question.explanation || "",
          explanation_i18n: question.explanation_i18n || {},
          difficulty: text(question.difficulty, "foundation"),
          question_type: type,
          question_version: version,
          written_explanation: writtenExplanation,
          screenshot_url: screenshotUrl,
          screenshot_storage_path: screenshotStoragePath,
          review_status: reviewStatus,
        });
      }

      const now = FieldValue.serverTimestamp();
      const reference = db.collection("users").doc(uid)
          .collection("trading_exam_attempts").doc(attemptId);
      await db.runTransaction(async (transaction) => {
        const existing = await transaction.get(reference);
        if (existing.exists) {
          throw new HttpsError(
              "already-exists",
              "Today's quiz has already been submitted.",
          );
        }
        transaction.create(reference, {
          user_id: uid,
          exam_date: Timestamp.fromDate(localDate(new Date(), offset)),
          status: needsReview ? "pending_review" : "completed",
          total_questions: results.length,
          score,
          answers,
          question_results: results,
          quiz_version: Math.max(...results.map((item) => item.question_version)),
          submitted_at: now,
          completed_at: needsReview ? null : now,
          created_at: now,
          updated_at: now,
        });
      });
      return {attemptId, status: needsReview ? "pending_review" : "completed"};
    },
);

exports.reviewDailyQuizSubmission = onCall(
    {region: REGION},
    async (request) => {
      const actorId = request.auth?.uid;
      if (!actorId) throw new HttpsError("unauthenticated", "Sign in first.");
      const db = getFirestore();
      const actorSnapshot = await db.collection("users").doc(actorId).get();
      const actor = actorSnapshot.data() || {};
      if (!actorSnapshot.exists ||
          ![...effectiveRoleIds(actor)].some((roleId) => isAdminRole(roleId))) {
        throw new HttpsError("permission-denied", "Administrator access required.");
      }
      const payload = request.data || {};
      const userId = text(payload.userId);
      const attemptId = text(payload.attemptId);
      const decisions = payload.decisions && typeof payload.decisions === "object" ?
        payload.decisions : {};
      if (!userId || !attemptId) {
        throw new HttpsError("invalid-argument", "Quiz review target is required.");
      }
      const attemptRef = db.collection("users").doc(userId)
          .collection("trading_exam_attempts").doc(attemptId);
      const notificationRef = db.collection("users").doc(userId)
          .collection("notifications").doc();
      const auditRef = db.collection("admin_audit_logs").doc();
      const now = FieldValue.serverTimestamp();

      await db.runTransaction(async (transaction) => {
        const snapshot = await transaction.get(attemptRef);
        if (!snapshot.exists) throw new HttpsError("not-found", "Quiz not found.");
        const attempt = snapshot.data() || {};
        if (text(attempt.status) !== "pending_review") {
          throw new HttpsError("failed-precondition", "This quiz is not pending review.");
        }
        let reviewedPoints = 0;
        let reviewCount = 0;
        const questionResults = (attempt.question_results || []).map((item) => {
          if (text(item.review_status) !== "pending_review") return item;
          const decision = decisions[text(item.question_id)] || {};
          const reviewScore = boundedNumber(decision.score, 0, 1, -1);
          if (reviewScore < 0) {
            throw new HttpsError("invalid-argument", "Score every written response.");
          }
          reviewedPoints += reviewScore;
          reviewCount++;
          return {
            ...item,
            review_status: "reviewed",
            review_score: reviewScore,
            review_feedback: text(decision.feedback).slice(0, 2000),
            reviewed_by: actorId,
            reviewed_at: now,
          };
        });
        if (!reviewCount) {
          throw new HttpsError("failed-precondition", "No written responses require review.");
        }
        const autoScore = Number(attempt.score || 0);
        const finalScore = autoScore + Math.round(reviewedPoints);
        const reviewPercentage = Math.round((reviewedPoints / reviewCount) * 100);
        transaction.update(attemptRef, {
          status: "reviewed",
          score: finalScore,
          review_score: reviewPercentage,
          review_note: text(payload.reviewNote).slice(0, 2000),
          reviewed_by: actorId,
          reviewed_at: now,
          completed_at: now,
          question_results: questionResults,
          updated_at: now,
        });
        transaction.create(notificationRef, {
          user_id: userId,
          category: "daily_quiz_review",
          title: "Daily quiz reviewed",
          message: `Your chart analysis was reviewed (${reviewPercentage}%).`,
          attempt_id: attemptId,
          is_read: false,
          created_at: now,
        });
        transaction.create(auditRef, {
          actor_id: actorId,
          action: "review",
          module: "daily_quiz",
          target_type: "trading_exam_attempt",
          target_id: `${userId}/${attemptId}`,
          created_at: now,
        });
      });
      return {attemptId, status: "reviewed"};
    },
);

async function verifiedScreenshotUrl({path, uid, quizId, attemptId}) {
  const file = getStorage().bucket().file(path);
  let metadata;
  try {
    [metadata] = await file.getMetadata();
  } catch (error) {
    throw new HttpsError("failed-precondition", "The quiz screenshot was not uploaded.");
  }
  const custom = metadata.metadata || {};
  if (custom.ownerUid !== uid || custom.quizId !== quizId ||
      custom.attemptId !== attemptId ||
      !/^image\/(jpeg|png|webp)$/.test(text(metadata.contentType)) ||
      Number(metadata.size || 0) > 8 * 1024 * 1024) {
    throw new HttpsError("permission-denied", "The quiz screenshot is invalid.");
  }
  return getDownloadURL(file);
}

function normalizedOptions(raw) {
  const values = Array.isArray(raw) ? raw :
    raw && typeof raw === "object" ? Object.values(raw) : [];
  return values.map((item) => {
    if (!item || typeof item !== "object") {
      return {text: text(item), i18n: {}, isCorrect: false};
    }
    return {
      text: item.option_text || item.text || "",
      i18n: item.option_text_i18n || item.text_i18n || {},
      isCorrect: item.is_correct === true,
    };
  });
}

function dailyAttemptId(date, offsetMinutes) {
  const local = new Date(date.getTime() + offsetMinutes * 60000);
  return `exam-${local.toISOString().slice(0, 10)}`;
}

function localDate(date, offsetMinutes) {
  const value = new Date(date.getTime() + offsetMinutes * 60000);
  return new Date(Date.UTC(value.getUTCFullYear(), value.getUTCMonth(), value.getUTCDate()));
}

function boundedNumber(value, minimum, maximum, fallback) {
  const number = Number(value);
  return Number.isFinite(number) && number >= minimum && number <= maximum ?
    number : fallback;
}

function positiveInteger(value, fallback) {
  const number = Number(value);
  return Number.isInteger(number) && number > 0 ? number : fallback;
}

function text(value, fallback = "") {
  const resolved = value == null ? "" : String(value).trim();
  return resolved || fallback;
}

exports._test = {
  dailyAttemptId,
  localDate,
  normalizedOptions,
};
