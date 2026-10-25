"use strict";

const {onCall, HttpsError} = require("firebase-functions/v2/https");
const {
  getFirestore,
  FieldValue,
  Timestamp,
} = require("firebase-admin/firestore");

const REGION = "us-central1";
const QUESTION_TYPES = [
  "singleChoice",
  "multipleChoice",
  "trueFalse",
  "shortAnswer",
  "chartZoneSelection",
  "marketAnalysis",
  "practicalDemonstration",
  "orderManagementDemonstration",
];
const EDITABLE = ["notStarted", "inProgress", "draft", "revisionRequested"];
const THEORY = ["singleChoice", "multipleChoice", "trueFalse", "shortAnswer"];

const rolePermissions = {
  "1": ["*"],
  "2": [
    "coursework.viewAssignedSubmissions",
    "coursework.grade",
    "coursework.requestRevision",
    "coursework.finaliseGrade",
    "coursework.assignThemes",
    "coursework.viewReports",
  ],
  "3": [
    "coursework.viewAssignedSubmissions",
    "coursework.grade",
    "coursework.requestRevision",
    "coursework.finaliseGrade",
    "coursework.manageThemes",
    "coursework.publishThemes",
    "coursework.assignThemes",
    "coursework.viewReports",
    "coursework.manageSettings",
  ],
  "501": [
    "coursework.viewAssignedSubmissions",
    "coursework.grade",
    "coursework.requestRevision",
    "coursework.finaliseGrade",
  ],
};

function text(value, fallback = "") {
  const result = value == null ? "" : String(value).trim();
  return result || fallback;
}

function finite(value, fallback = 0) {
  const result = Number(value);
  return Number.isFinite(result) ? result : fallback;
}

function roleIds(user) {
  const values = [user.role_id, ...(Array.isArray(user.role_ids) ? user.role_ids : [])];
  return [...new Set(values.filter((value) => value != null).map(String))];
}

function can(user, permission) {
  const overrides = user.permission_overrides || user.permissionOverrides || {};
  if (overrides[permission] === false) return false;
  if (overrides[permission] === true) return true;
  return roleIds(user).some((role) => {
    const values = rolePermissions[role] || [];
    return values.includes("*") || values.includes(permission);
  });
}

async function actor(request, permission) {
  const uid = request.auth?.uid;
  if (!uid) throw new HttpsError("unauthenticated", "Sign in first.");
  const snapshot = await getFirestore().collection("users").doc(uid).get();
  if (!snapshot.exists || !can(snapshot.data() || {}, permission)) {
    throw new HttpsError("permission-denied", `Missing permission: ${permission}.`);
  }
  return {uid, data: snapshot.data() || {}};
}

function validateTheme(theme, questions) {
  const errors = [];
  if (questions.length !== 5) errors.push("A theme must contain exactly five questions.");
  const numbers = questions.map((item) => Number(item.question_number)).sort();
  if (numbers.join(",") !== "1,2,3,4,5") {
    errors.push("Question numbers must run from 1 to 5.");
  }
  if (questions.filter((item) => THEORY.includes(item.question_type)).length !== 2) {
    errors.push("A theme needs exactly two theory questions.");
  }
  const ordered = [...questions].sort(
    (left, right) => Number(left.question_number) - Number(right.question_number),
  );
  const expectedTypes = [
    THEORY,
    THEORY,
    ["chartZoneSelection"],
    ["marketAnalysis"],
    ["practicalDemonstration", "orderManagementDemonstration"],
  ];
  if (
    ordered.length === 5 &&
    ordered.some((item, index) => !expectedTypes[index].includes(item.question_type))
  ) {
    errors.push("Question order must be theory, theory, chart, analysis, then order management.");
  }
  for (const type of [
    "chartZoneSelection",
    "marketAnalysis",
  ]) {
    if (questions.filter((item) => item.question_type === type).length !== 1) {
      errors.push(`A theme needs exactly one ${type} question.`);
    }
  }
  if (questions.filter((item) => ["practicalDemonstration", "orderManagementDemonstration"].includes(item.question_type)).length !== 1) {
    errors.push("A theme needs exactly one practical demonstration question.");
  }
  const total = questions.reduce((sum, item) => sum + finite(item.marks), 0);
  if (Math.abs(total - 100) > 0.000001 || Math.abs(finite(theme.total_marks) - 100) > 0.000001) {
    errors.push("Coursework marks must total exactly 100.");
  }
  if (!text(theme.theme_code) || !text(theme.title) || !Array.isArray(theme.learning_objectives)) {
    errors.push("The theme code, title, and learning objectives are required.");
  }
  const orderedMarks = ordered.map((item) => finite(item.marks));
  if (ordered.length === 5 && orderedMarks.join(",") !== "15,15,20,25,25") {
    errors.push("Question marks must be 15, 15, 20, 25, and 25.");
  }
  for (const question of questions) {
    if (!QUESTION_TYPES.includes(question.question_type) || !text(question.question_text)) {
      errors.push("Every question requires a supported type and text.");
      break;
    }
    if (
      THEORY.includes(question.question_type) &&
      (!Array.isArray(question.answer_options) ||
        question.answer_options.length < 2 ||
        !question.answer_options.some((option) => option.is_correct === true))
    ) {
      errors.push("Published objective questions need at least two options and a verified answer.");
      break;
    }
  }
  return errors;
}

function localized(value, language = "en") {
  if (value && typeof value === "object" && !Array.isArray(value)) {
    return value[language] ?? value.en ?? value.zh ?? "";
  }
  return value ?? "";
}

function normalizedType(value) {
  const normalized = text(value).replaceAll("_", "").toLowerCase();
  const aliases = {
    theorysinglechoice: "singleChoice",
    theorymultiplechoice: "multipleChoice",
    theoryshortanswer: "shortAnswer",
    scenariobasedtheory: "singleChoice",
    chartinterpretation: "chartZoneSelection",
    selfanalysiskbat: "marketAnalysis",
    practicaldemonstration: "practicalDemonstration",
  };
  return aliases[normalized] || value;
}

function normalizedDifficulty(value) {
  const normalized = text(value).trim().toLowerCase();
  if (["beginner", "basic", "foundation", "初级", "基础"].includes(normalized)) {
    return "beginner";
  }
  if (["advanced", "expert", "高级", "进阶"].includes(normalized)) {
    return "advanced";
  }
  return "intermediate";
}

function normalizedPublicationStatus(value) {
  if (value === true) return "published";
  if (value === false) return "draft";
  const normalized = text(value).trim().toLowerCase();
  if (["published", "publish", "live", "已发布"].includes(normalized)) return "published";
  if (["archived", "archive", "inactive", "已归档"].includes(normalized)) return "archived";
  return "draft";
}

function normalizedTheme(raw, themeId) {
  const objectives = raw.learningObjectives || raw.learning_objectives || [];
  return {
    ...raw,
    theme_id: themeId,
    theme_code: text(raw.code || raw.theme_code, themeId),
    title: text(localized(raw.title)),
    title_i18n: raw.title && typeof raw.title === "object" ? raw.title : raw.title_i18n || {},
    short_description: text(localized(raw.description || raw.short_description)),
    short_description_i18n: raw.description && typeof raw.description === "object" ? raw.description : raw.short_description_i18n || {},
    category: text(localized(raw.categoryName || raw.category_name || raw.category)),
    category_i18n: raw.categoryName || raw.category_name || raw.category_i18n || {},
    category_id: text(raw.categoryId || raw.category_id),
    difficulty: normalizedDifficulty(raw.difficulty || raw.difficultyLevel || raw.level),
    status: normalizedPublicationStatus(
        raw.status ?? raw.publicationStatus ?? raw.publication_status ?? raw.isPublished),
    learning_objectives: Array.isArray(objectives) ? objectives : objectives.en || [],
    learning_objectives_i18n: objectives,
    estimated_completion_minutes: Number(raw.estimatedMinutes || raw.estimated_completion_minutes || 60),
    total_marks: Number(raw.totalMarks || raw.total_marks || 0),
    is_active: raw.isActive ?? raw.is_active ?? raw.active ?? true,
    is_assignable: raw.isAssignable ?? raw.is_assignable ?? true,
  };
}

function normalizedPublicQuestion(raw, questionId, themeId) {
  const chart = raw.chartData || {};
  const type = normalizedType(raw.type || raw.question_type);
  return {
    ...raw,
    question_id: questionId,
    theme_id: themeId,
    question_number: Number(raw.order || raw.question_number || 0),
    question_type: type,
    title: text(localized(raw.title)),
    title_i18n: raw.title && typeof raw.title === "object" ? raw.title : raw.title_i18n || {},
    question_text: text(localized(raw.prompt || raw.question_text)),
    question_text_i18n: raw.prompt && typeof raw.prompt === "object" ? raw.prompt : raw.question_text_i18n || {},
    instructions: text(localized(raw.instructions)),
    instructions_i18n: raw.instructions && typeof raw.instructions === "object" ? raw.instructions : raw.instructions_i18n || {},
    answer_options: (raw.options || raw.answer_options || []).map((option) => ({
      id: text(option.id),
      text: text(localized(option.text)),
      text_i18n: option.text && typeof option.text === "object" ? option.text : option.text_i18n || {},
    })),
    requires_written_explanation: raw.requiresText ?? raw.requires_written_explanation ?? false,
    requires_image: raw.requiresImage ?? raw.requires_image ?? false,
    minimum_characters: Number(raw.minimumCharacters || raw.minimum_characters || 0),
    maximum_characters: Number(raw.maximumCharacters || raw.maximum_characters || 0),
    minimum_images: Number(raw.minimumImages || raw.minimum_images || 0),
    maximum_images: Number(raw.maximumImages || raw.maximum_images || 0),
    allowed_file_types: raw.allowedFileTypes || raw.allowed_file_types || [],
    symbol: text(chart.symbol || raw.symbol),
    timeframe: text(chart.timeframe || raw.timeframe),
    chart_candles: (chart.candles || raw.chart_candles || []).map((candle) => ({
      ...candle,
      time: candle.time || new Date(Date.UTC(2026, 0, 1) + Number(candle.timeIndex || 0) * 3600000).toISOString(),
    })),
    answer_zones: (chart.zones || raw.answer_zones || []).map((zone) => ({
      id: text(zone.id),
      label: text(zone.label),
      minimum_price: finite(zone.minimumPrice ?? zone.minimum_price),
      maximum_price: finite(zone.maximumPrice ?? zone.maximum_price),
    })),
    grading_mode: ["singleChoice", "multipleChoice", "trueFalse", "chartZoneSelection"].includes(type) ? "automatic" : "manual",
  };
}

function normalizedAnswerKey(question, rawKey) {
  const correctOptions = new Set(rawKey.correctOptionIds || []);
  const correctZones = new Set(rawKey.correctZoneIds || []);
  return {
    ...question,
    answer_options: (question.answer_options || []).map((option) => ({
      ...option,
      is_correct: correctOptions.has(option.id),
    })),
    answer_zones: (question.answer_zones || []).map((zone) => ({
      ...zone,
      is_correct: correctZones.has(zone.id),
      partial_mark_ratio: 0,
    })),
    grading_rubric: (rawKey.rubric || []).map((criterion) => ({
      id: criterion.id,
      title: text(localized(criterion.criterion)),
      title_i18n: criterion.criterion || {},
      maximum_mark: finite(criterion.maximumMarks),
    })),
    sample_answer: text(localized(rawKey.modelAnswer)),
    reviewer_guidance: text(localized(rawKey.answerExplanation)),
  };
}

function publicQuestion(question) {
  const result = {...question};
  delete result.correct_answer;
  delete result.accepted_answers;
  delete result.sample_answer;
  delete result.reviewer_guidance;
  result.answer_options = (result.answer_options || []).map((option) => {
    const value = {...option};
    delete value.is_correct;
    return value;
  });
  result.answer_zones = (result.answer_zones || []).map((zone) => {
    const value = {...zone};
    delete value.is_correct;
    delete value.partial_mark_ratio;
    return value;
  });
  return result;
}

function answerKey(question) {
  return {
    question_id: question.question_id,
    question_number: question.question_number,
    question_type: question.question_type,
    marks: question.marks,
    answer_options: question.answer_options || [],
    accepted_answers: question.accepted_answers || [],
    answer_zones: question.answer_zones || [],
    grading_rubric: question.grading_rubric || [],
    sample_answer: question.sample_answer || "",
    reviewer_guidance: question.reviewer_guidance || "",
    version: question.version || 1,
  };
}

function currentSettings(data) {
  const value = data || {};
  const courseworkWeight = finite(value.coursework_weight, 0.7);
  const finalExamWeight = finite(value.final_exam_weight, 0.3);
  if (Math.abs(courseworkWeight + finalExamWeight - 1) > 0.000001) {
    throw new HttpsError("failed-precondition", "Assessment weights must total 100%.");
  }
  return {
    courseworkWeight,
    finalExamWeight,
    passMark: finite(value.pass_mark, 60),
    boundaries: value.grade_boundaries || {A: 80, B: 70, C: 60, D: 50, F: 0},
    version: Math.max(1, Number(value.calculation_version || 1)),
  };
}

function gradeFor(score, boundaries) {
  const entries = Object.entries(boundaries)
      .map(([grade, minimum]) => [grade, finite(minimum)])
      .sort((left, right) => right[1] - left[1]);
  return (entries.find((entry) => score >= entry[1]) || ["F"])[0];
}

function round2(value) {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

async function notification(transaction, userId, id, data) {
  const ref = getFirestore().collection("users").doc(userId)
      .collection("notifications").doc(id);
  transaction.set(ref, {
    user_id: userId,
    is_read: false,
    created_at: FieldValue.serverTimestamp(),
    ...data,
  }, {merge: true});
}

function audit(transaction, actorId, action, targetId, detail = {}) {
  transaction.create(getFirestore().collection("assessment_audit_logs").doc(), {
    actor_id: actorId,
    action,
    module: "coursework",
    target_id: targetId,
    detail,
    created_at: FieldValue.serverTimestamp(),
  });
}

async function assignmentThemeSnapshots(themeId) {
  const db = getFirestore();
  const themeRef = db.collection("coursework_themes").doc(themeId);
  const keyThemeRef = db.collection("coursework_theme_keys").doc(themeId);
  const [themeSnapshot, questionSnapshot, keyThemeSnapshot, keyQuestionSnapshot] = await Promise.all([
    themeRef.get(),
    themeRef.collection("questions").get(),
    keyThemeRef.get(),
    keyThemeRef.collection("questions").get(),
  ]);
  if (!themeSnapshot.exists) throw new HttpsError("not-found", "Theme not found.");
  if (!keyThemeSnapshot.exists || keyQuestionSnapshot.size !== 5) {
    throw new HttpsError("failed-precondition", "The private marking key is incomplete.");
  }
  const theme = normalizedTheme(themeSnapshot.data() || {}, themeId);
  const publicQuestions = questionSnapshot.docs
      .map((doc) => normalizedPublicQuestion(doc.data(), doc.id, themeId))
      .sort((left, right) => finite(left.question_number) - finite(right.question_number));
  const privateById = new Map(keyQuestionSnapshot.docs.map((doc) => [doc.id, doc.data()]));
  const questions = publicQuestions.map((question) =>
    normalizedAnswerKey(question, privateById.get(question.question_id) || {}));
  const errors = validateTheme(theme, questions);
  if (text(theme.status).toLowerCase() !== "published" || theme.is_active !== true ||
      theme.is_assignable !== true || errors.length) {
    throw new HttpsError(
        "failed-precondition",
        errors.join(" ") || "Only active, published, assignable themes can be assigned.");
  }
  return {
    theme,
    publicSnapshot: {
      ...theme,
      theme_id: themeId,
      question_snapshot: publicQuestions.map(publicQuestion),
    },
    keySnapshot: {questions: questions.map(answerKey)},
  };
}

exports.assignCourseworkTheme = onCall({region: REGION}, async (request) => {
  const admin = await actor(request, "coursework.assignThemes");
  const data = request.data || {};
  const themeId = text(data.themeId);
  const userIds = Array.isArray(data.userIds) ? [...new Set(data.userIds.map(text).filter(Boolean))] : [];
  const dueAt = new Date(text(data.dueAt));
  if (!themeId || !userIds.length || Number.isNaN(dueAt.getTime())) {
    throw new HttpsError("invalid-argument", "Theme, users, and due date are required.");
  }
  const db = getFirestore();
  const {theme, publicSnapshot, keySnapshot} = await assignmentThemeSnapshots(themeId);
  const now = Date.now();
  const batch = db.batch();
  for (const [index, userId] of userIds.entries()) {
    const user = await db.collection("users").doc(userId).get();
    if (!user.exists) throw new HttpsError("not-found", `User ${userId} not found.`);
    const assignmentId = `${themeId}-${userId}-${now + index}`;
    const ref = db.collection("coursework_assignments").doc(assignmentId);
    batch.create(ref, {
      assignment_id: assignmentId,
      theme_id: themeId,
      theme_version: Number(theme.version || 1),
      assigned_to_user_id: userId,
      assigned_by_user_id: admin.uid,
      assigned_at: FieldValue.serverTimestamp(),
      available_from: FieldValue.serverTimestamp(),
      due_at: Timestamp.fromDate(dueAt),
      status: "assigned",
      custom_instructions: text(data.customInstructions).slice(0, 4000),
      allow_revision: data.allowRevision !== false,
      maximum_revisions: Math.max(0, Math.min(10, Number(data.maximumRevisions || 1))),
      selected_question_snapshot: publicSnapshot,
      created_at: FieldValue.serverTimestamp(),
      updated_at: FieldValue.serverTimestamp(),
    });
    batch.create(db.collection("coursework_assignment_keys").doc(assignmentId), {
      assignment_id: assignmentId,
      theme_id: themeId,
      theme_version: Number(theme.version || 1),
      ...keySnapshot,
      created_at: FieldValue.serverTimestamp(),
    });
    batch.set(db.collection("users").doc(userId).collection("notifications")
        .doc(`coursework-assigned-${assignmentId}`), {
      user_id: userId,
      category: "coursework_assigned",
      title: "Coursework assigned",
      message: text(theme.title, "A Coursework assignment is ready."),
      assignment_id: assignmentId,
      is_read: false,
      created_at: FieldValue.serverTimestamp(),
    });
  }
  batch.create(db.collection("assessment_audit_logs").doc(), {
    actor_id: admin.uid,
    action: "assign",
    module: "coursework",
    target_id: themeId,
    detail: {user_count: userIds.length},
    created_at: FieldValue.serverTimestamp(),
  });
  await batch.commit();
  return {assigned: userIds.length};
});

exports.updateCourseworkAssignment = onCall({region: REGION}, async (request) => {
  const admin = await actor(request, "coursework.assignThemes");
  const data = request.data || {};
  const assignmentId = text(data.assignmentId);
  const themeId = text(data.themeId);
  const dueAt = new Date(text(data.dueAt));
  if (!assignmentId || !themeId || Number.isNaN(dueAt.getTime())) {
    throw new HttpsError("invalid-argument", "Assignment, theme, and due date are required.");
  }
  const db = getFirestore();
  const assignmentRef = db.collection("coursework_assignments").doc(assignmentId);
  const assignmentSnapshot = await assignmentRef.get();
  if (!assignmentSnapshot.exists) {
    throw new HttpsError("not-found", "Coursework assignment not found.");
  }
  const assignment = assignmentSnapshot.data() || {};
  if (["completed", "cancelled"].includes(text(assignment.status).toLowerCase())) {
    throw new HttpsError("failed-precondition", "Completed or cancelled assignments cannot be edited.");
  }
  const previousThemeId = text(assignment.theme_id || assignment.themeId);
  const changingTheme = previousThemeId !== themeId;
  const userId = text(assignment.assigned_to_user_id || assignment.assignedToUserId);
  if (!userId) {
    throw new HttpsError("failed-precondition", "The assignment candidate is missing.");
  }
  const attemptRef = db.collection("coursework_attempts").doc(`${assignmentId}-${userId}`);
  if (changingTheme && (await attemptRef.get()).exists) {
    throw new HttpsError(
        "failed-precondition",
        "The theme cannot be changed after the candidate starts the assignment.");
  }
  const {theme, publicSnapshot, keySnapshot} = await assignmentThemeSnapshots(themeId);
  const batch = db.batch();
  batch.update(assignmentRef, {
    theme_id: themeId,
    theme_version: Number(theme.version || 1),
    theme_code: text(theme.theme_code || theme.code),
    theme_title: text(theme.title),
    due_at: Timestamp.fromDate(dueAt),
    custom_instructions: text(data.customInstructions).slice(0, 4000),
    allow_revision: data.allowRevision !== false,
    selected_question_snapshot: publicSnapshot,
    updated_at: FieldValue.serverTimestamp(),
    updated_by_user_id: admin.uid,
  });
  batch.set(db.collection("coursework_assignment_keys").doc(assignmentId), {
    assignment_id: assignmentId,
    theme_id: themeId,
    theme_version: Number(theme.version || 1),
    ...keySnapshot,
    updated_at: FieldValue.serverTimestamp(),
  }, {merge: false});
  batch.create(db.collection("assessment_audit_logs").doc(), {
    actor_id: admin.uid,
    action: "update_assignment",
    module: "coursework",
    target_id: assignmentId,
    detail: {previous_theme_id: previousThemeId, theme_id: themeId},
    created_at: FieldValue.serverTimestamp(),
  });
  await batch.commit();
  return {assignmentId, themeId};
});

exports.startCourseworkAttempt = onCall({region: REGION}, async (request) => {
  const uid = request.auth?.uid;
  if (!uid) throw new HttpsError("unauthenticated", "Sign in first.");
  const assignmentId = text(request.data?.assignmentId);
  if (!assignmentId) throw new HttpsError("invalid-argument", "Assignment is required.");
  const db = getFirestore();
  const assignmentRef = db.collection("coursework_assignments").doc(assignmentId);
  const keyRef = db.collection("coursework_assignment_keys").doc(assignmentId);
  const attemptId = `${assignmentId}-${uid}`;
  const attemptRef = db.collection("coursework_attempts").doc(attemptId);
  const attemptKeyRef = db.collection("coursework_attempt_keys").doc(attemptId);
  await db.runTransaction(async (transaction) => {
    const [assignmentSnapshot, keySnapshot, attemptSnapshot] = await Promise.all([
      transaction.get(assignmentRef),
      transaction.get(keyRef),
      transaction.get(attemptRef),
    ]);
    if (!assignmentSnapshot.exists || assignmentSnapshot.data().assigned_to_user_id !== uid) {
      throw new HttpsError("permission-denied", "This Coursework is not assigned to you.");
    }
    if (attemptSnapshot.exists) return;
    if (!keySnapshot.exists) throw new HttpsError("failed-precondition", "Assignment marking snapshot is missing.");
    const assignment = assignmentSnapshot.data();
    const now = new Date();
    if (assignment.available_from instanceof Timestamp && assignment.available_from.toDate() > now) {
      throw new HttpsError("failed-precondition", "This Coursework is not available yet.");
    }
    if (assignment.expires_at instanceof Timestamp && assignment.expires_at.toDate() < now) {
      throw new HttpsError("deadline-exceeded", "This Coursework has expired.");
    }
    transaction.create(attemptRef, {
      attempt_id: attemptId,
      assignment_id: assignmentId,
      theme_id: assignment.theme_id,
      theme_version: assignment.theme_version,
      user_id: uid,
      status: "inProgress",
      started_at: FieldValue.serverTimestamp(),
      last_saved_at: FieldValue.serverTimestamp(),
      current_question_number: 1,
      completed_question_count: 0,
      automatic_score: 0,
      manual_score: 0,
      revision_number: 0,
      grading_status: "notStarted",
      declaration_accepted: false,
      calculation_version: 1,
      theme_snapshot: assignment.selected_question_snapshot,
      created_at: FieldValue.serverTimestamp(),
      updated_at: FieldValue.serverTimestamp(),
    });
    transaction.create(attemptKeyRef, {
      ...keySnapshot.data(),
      attempt_id: attemptId,
      assignment_id: assignmentId,
      created_at: FieldValue.serverTimestamp(),
    });
    transaction.update(assignmentRef, {
      status: "inProgress",
      attempt_id: attemptId,
      updated_at: FieldValue.serverTimestamp(),
    });
  });
  return {attemptId};
});

function validateOrderManagement(answer) {
  const data = answer.order_management_data || {};
  const direction = text(data.direction).toLowerCase();
  const entry = finite(data.entry_price, NaN);
  const stop = finite(data.stop_loss_price, NaN);
  const take = finite(data.take_profit_price, NaN);
  if (![entry, stop, take].every(Number.isFinite) || !["buy", "sell"].includes(direction)) return false;
  if (direction === "buy") return stop < entry && take > entry;
  return stop > entry && take < entry;
}

function completeAnswer(question, answer) {
  if (!answer || answer.is_completed !== true) return false;
  const minimumCharacters = Math.max(0, Number(question.minimum_characters || 0));
  const minimumImages = Math.max(0, Number(question.minimum_images || 0));
  const textComplete = text(answer.text_answer).length >= minimumCharacters;
  const imageComplete = question.requires_image !== true ||
    (Array.isArray(answer.attachments) && answer.attachments.length >= Math.max(1, minimumImages));
  switch (question.question_type) {
    case "singleChoice":
    case "multipleChoice":
    case "trueFalse":
      return Array.isArray(answer.selected_option_ids) && answer.selected_option_ids.length > 0;
    case "shortAnswer":
      return text(answer.text_answer).length >= Math.max(10, minimumCharacters);
    case "chartZoneSelection":
      return !!text(answer.selected_chart_zone) ||
        (Number.isFinite(Number(answer.selected_minimum_price)) &&
         Number.isFinite(Number(answer.selected_maximum_price)));
    case "marketAnalysis":
      return textComplete && imageComplete;
    case "practicalDemonstration":
      return textComplete && imageComplete;
    case "orderManagementDemonstration":
      return validateOrderManagement(answer) && text(answer.text_answer).length >= 20 &&
        Array.isArray(answer.attachments) && answer.attachments.length > 0;
    default:
      return false;
  }
}

function automaticMark(question, answer) {
  const maximum = finite(question.marks);
  if (["singleChoice", "trueFalse"].includes(question.question_type)) {
    const correct = (question.answer_options || []).find((item) => item.is_correct === true);
    return correct && answer.selected_option_ids?.includes(text(correct.id)) ? maximum : 0;
  }
  if (question.question_type === "multipleChoice") {
    const expected = (question.answer_options || []).filter((item) => item.is_correct === true).map((item) => text(item.id)).sort();
    const selected = [...(answer.selected_option_ids || [])].map(text).sort();
    return JSON.stringify(expected) === JSON.stringify(selected) ? maximum : 0;
  }
  if (question.question_type === "chartZoneSelection") {
    const selected = text(answer.selected_chart_zone);
    const zone = (question.answer_zones || []).find((item) => text(item.id) === selected);
    if (zone?.is_correct === true) return maximum;
    return round2(maximum * Math.max(0, Math.min(1, finite(zone?.partial_mark_ratio))));
  }
  return null;
}

exports.submitCourseworkAttempt = onCall({region: REGION}, async (request) => {
  const uid = request.auth?.uid;
  if (!uid) throw new HttpsError("unauthenticated", "Sign in first.");
  const attemptId = text(request.data?.attemptId);
  if (!attemptId || request.data?.declarationAccepted !== true) {
    throw new HttpsError("invalid-argument", "Attempt and academic declaration are required.");
  }
  const db = getFirestore();
  const attemptRef = db.collection("coursework_attempts").doc(attemptId);
  const keyRef = db.collection("coursework_attempt_keys").doc(attemptId);
  const [attemptSnapshot, keySnapshot, answerSnapshot] = await Promise.all([
    attemptRef.get(),
    keyRef.get(),
    attemptRef.collection("answers").get(),
  ]);
  if (!attemptSnapshot.exists || attemptSnapshot.data().user_id !== uid) {
    throw new HttpsError("permission-denied", "This attempt does not belong to you.");
  }
  const attempt = attemptSnapshot.data();
  if (!EDITABLE.includes(text(attempt.status))) {
    throw new HttpsError("already-exists", "This Coursework has already been submitted.");
  }
  const questions = keySnapshot.data()?.questions || [];
  if (questions.length !== 5) throw new HttpsError("failed-precondition", "The marking snapshot is invalid.");
  const answers = new Map(answerSnapshot.docs.map((doc) => [doc.id, doc.data()]));
  for (const question of questions) {
    if (!completeAnswer(question, answers.get(text(question.question_id)))) {
      throw new HttpsError("failed-precondition", `Question ${question.question_number} is incomplete.`);
    }
  }
  const settingsSnapshot = await db.collection("assessment_settings").doc("current").get();
  const settings = currentSettings(settingsSnapshot.data());
  const automatic = questions.reduce((total, question) => {
    return total + (automaticMark(question, answers.get(text(question.question_id))) || 0);
  }, 0);
  const assignmentRef = db.collection("coursework_assignments").doc(attempt.assignment_id);
  await db.runTransaction(async (transaction) => {
    const fresh = await transaction.get(attemptRef);
    if (!fresh.exists || !EDITABLE.includes(text(fresh.data().status))) {
      throw new HttpsError("already-exists", "This Coursework has already been submitted.");
    }
    for (const question of questions) {
      const mark = automaticMark(question, answers.get(text(question.question_id)));
      if (mark != null) {
        transaction.update(attemptRef.collection("answers").doc(text(question.question_id)), {
          automatic_mark: mark,
          submitted_at: FieldValue.serverTimestamp(),
          updated_at: FieldValue.serverTimestamp(),
        });
      }
    }
    const resubmission = text(fresh.data().status) === "revisionRequested";
    transaction.update(attemptRef, {
      status: resubmission ? "resubmitted" : "submitted",
      declaration_accepted: true,
      automatic_score: round2(automatic),
      grading_status: "awaitingReview",
      submitted_at: fresh.data().submitted_at || FieldValue.serverTimestamp(),
      ...(resubmission ? {resubmitted_at: FieldValue.serverTimestamp()} : {}),
      calculation_version: settings.version,
      updated_at: FieldValue.serverTimestamp(),
    });
    transaction.update(assignmentRef, {
      status: resubmission ? "resubmitted" : "submitted",
      updated_at: FieldValue.serverTimestamp(),
    });
    audit(transaction, uid, resubmission ? "resubmit" : "submit", attemptId);
  });
  return {attemptId, status: text(attempt.status) === "revisionRequested" ? "resubmitted" : "submitted"};
});

exports.gradeCourseworkAttempt = onCall({region: REGION}, async (request) => {
  const admin = await actor(request, request.data?.requestRevision === true ?
    "coursework.requestRevision" : "coursework.finaliseGrade");
  const data = request.data || {};
  const attemptId = text(data.attemptId);
  if (!attemptId) throw new HttpsError("invalid-argument", "Attempt is required.");
  const db = getFirestore();
  const attemptRef = db.collection("coursework_attempts").doc(attemptId);
  const keyRef = db.collection("coursework_attempt_keys").doc(attemptId);
  const [attemptSnapshot, keySnapshot, answerSnapshot, settingsSnapshot] = await Promise.all([
    attemptRef.get(),
    keyRef.get(),
    attemptRef.collection("answers").get(),
    db.collection("assessment_settings").doc("current").get(),
  ]);
  if (!attemptSnapshot.exists || !keySnapshot.exists) throw new HttpsError("not-found", "Attempt not found.");
  const attempt = attemptSnapshot.data();
  if (!["submitted", "underReview", "resubmitted"].includes(text(attempt.status))) {
    throw new HttpsError("failed-precondition", "This attempt is not awaiting review.");
  }
  const assignmentSnapshot = await db.collection("coursework_assignments").doc(attempt.assignment_id).get();
  const assignment = assignmentSnapshot.data() || {};
  if (data.requestRevision === true) {
    if (assignment.allow_revision === false || Number(attempt.revision_number || 0) >= Number(assignment.maximum_revisions || 1)) {
      throw new HttpsError("failed-precondition", "No Coursework revisions remain.");
    }
    const reason = text(data.reason || data.overallFeedback).slice(0, 3000);
    if (!reason) throw new HttpsError("invalid-argument", "A revision reason is required.");
    await db.runTransaction(async (transaction) => {
      transaction.update(attemptRef, {
        status: "revisionRequested",
        grading_status: "revisionRequested",
        revision_number: FieldValue.increment(1),
        reviewer_id: admin.uid,
        reviewer_feedback: reason,
        updated_at: FieldValue.serverTimestamp(),
      });
      transaction.update(assignmentSnapshot.ref, {
        status: "revisionRequested",
        updated_at: FieldValue.serverTimestamp(),
      });
      notification(transaction, attempt.user_id, `coursework-revision-${attemptId}-${Number(attempt.revision_number || 0) + 1}`, {
        category: "coursework_revision_requested",
        title: "Coursework revision required",
        message: reason,
        attempt_id: attemptId,
      });
      audit(transaction, admin.uid, "request_revision", attemptId, {reason});
    });
    return {attemptId, status: "revisionRequested"};
  }

  const rubricMarks = data.rubricMarks && typeof data.rubricMarks === "object" ? data.rubricMarks : {};
  const feedback = data.feedback && typeof data.feedback === "object" ? data.feedback : {};
  const keys = keySnapshot.data().questions || [];
  let manualScore = 0;
  const answerUpdates = [];
  for (const question of keys) {
    const type = text(question.question_type);
    if (!["shortAnswer", "marketAnalysis", "practicalDemonstration", "orderManagementDemonstration"].includes(type)) continue;
    const criteria = question.grading_rubric || [];
    const awarded = rubricMarks[text(question.question_id)] || {};
    let questionScore = 0;
    if (criteria.length) {
      for (const criterion of criteria) {
        const mark = finite(awarded[text(criterion.id)], NaN);
        const maximum = finite(criterion.maximum_mark);
        if (!Number.isFinite(mark) || mark < 0 || mark > maximum) {
          throw new HttpsError("invalid-argument", `Complete every rubric criterion for question ${question.question_number}.`);
        }
        questionScore += mark;
      }
    } else {
      const mark = finite(awarded.total, NaN);
      if (!Number.isFinite(mark) || mark < 0 || mark > finite(question.marks)) {
        throw new HttpsError("invalid-argument", `Enter a valid mark for question ${question.question_number}.`);
      }
      questionScore = mark;
    }
    if (questionScore > finite(question.marks)) {
      throw new HttpsError("invalid-argument", "Manual marks cannot exceed the question maximum.");
    }
    manualScore += questionScore;
    answerUpdates.push({
      id: text(question.question_id),
      manual_mark: round2(questionScore),
      rubric_marks: awarded,
      reviewer_feedback: text(feedback[text(question.question_id)]).slice(0, 3000),
    });
  }
  const automaticScore = finite(attempt.automatic_score);
  const rawScore = round2(Math.min(100, automaticScore + manualScore));
  const settings = currentSettings(settingsSnapshot.data());
  const weightedScore = round2(rawScore * settings.courseworkWeight);
  const overallFeedback = text(data.overallFeedback).slice(0, 5000);
  await db.runTransaction(async (transaction) => {
    for (const update of answerUpdates) {
      transaction.update(attemptRef.collection("answers").doc(update.id), {
        ...update,
        reviewed_by: admin.uid,
        reviewed_at: FieldValue.serverTimestamp(),
        updated_at: FieldValue.serverTimestamp(),
      });
    }
    transaction.update(attemptRef, {
      status: "completed",
      grading_status: "finalised",
      manual_score: round2(manualScore),
      raw_score: rawScore,
      weighted_score: weightedScore,
      reviewer_id: admin.uid,
      reviewer_feedback: overallFeedback,
      graded_at: FieldValue.serverTimestamp(),
      completed_at: FieldValue.serverTimestamp(),
      calculation_version: settings.version,
      updated_at: FieldValue.serverTimestamp(),
    });
    transaction.update(assignmentSnapshot.ref, {
      status: "completed",
      updated_at: FieldValue.serverTimestamp(),
    });
    transaction.set(db.collection("coursework_results").doc(attempt.user_id), {
      user_id: attempt.user_id,
      coursework_attempt_id: attemptId,
      coursework_raw_score: rawScore,
      coursework_weight: settings.courseworkWeight,
      coursework_weighted_score: weightedScore,
      grading_status: "finalised",
      reviewer_feedback: overallFeedback,
      calculation_version: settings.version,
      calculated_at: FieldValue.serverTimestamp(),
      updated_at: FieldValue.serverTimestamp(),
    }, {merge: true});
    notification(transaction, attempt.user_id, `coursework-graded-${attemptId}`, {
      category: "coursework_graded",
      title: "Coursework graded",
      message: `Your Coursework result is ${rawScore}/100.`,
      attempt_id: attemptId,
    });
    audit(transaction, admin.uid, "finalise_grade", attemptId, {raw_score: rawScore});
  });
  await recalculateOverallResult(attempt.user_id, "coursework_grade_finalised");
  return {attemptId, status: "completed", rawScore, weightedScore};
});

async function recalculateOverallResult(userId, reason) {
  const db = getFirestore();
  const [courseworkSnapshot, settingsSnapshot, finalSnapshot] = await Promise.all([
    db.collection("coursework_results").doc(userId).get(),
    db.collection("assessment_settings").doc("current").get(),
    db.collection("users").doc(userId).collection("final_exam_attempts").get(),
  ]);
  if (!courseworkSnapshot.exists) return null;
  const finalAttempts = finalSnapshot.docs
      .map((doc) => ({id: doc.id, ...doc.data()}))
      .filter((item) => ["completed", "passed"].includes(text(item.status)))
      .sort((left, right) => finite(right.completed_at?.toMillis?.()) - finite(left.completed_at?.toMillis?.()));
  if (!finalAttempts.length) return null;
  const finalAttempt = finalAttempts[0];
  const finalRaw = Number.isFinite(Number(finalAttempt.score_percentage)) ?
    finite(finalAttempt.score_percentage) :
    finite(finalAttempt.correct_answers) / Math.max(1, finite(finalAttempt.total_questions)) * 100;
  const coursework = courseworkSnapshot.data();
  const settings = currentSettings(settingsSnapshot.data());
  const courseworkRaw = finite(coursework.coursework_raw_score);
  const courseworkWeighted = round2(courseworkRaw * settings.courseworkWeight);
  const finalWeighted = round2(finalRaw * settings.finalExamWeight);
  const overall = round2(courseworkWeighted + finalWeighted);
  const resultRef = db.collection("assessment_results").doc(userId);
  const previous = await resultRef.get();
  const batch = db.batch();
  if (previous.exists) {
    batch.create(resultRef.collection("history").doc(), {
      ...previous.data(),
      recalculation_reason: reason,
      archived_at: FieldValue.serverTimestamp(),
    });
  }
  batch.set(resultRef, {
    user_id: userId,
    coursework_attempt_id: coursework.coursework_attempt_id,
    final_exam_attempt_id: finalAttempt.id,
    coursework_raw_score: courseworkRaw,
    coursework_weight: settings.courseworkWeight,
    coursework_weighted_score: courseworkWeighted,
    final_exam_raw_score: round2(finalRaw),
    final_exam_weight: settings.finalExamWeight,
    final_exam_weighted_score: finalWeighted,
    overall_score: overall,
    grade: gradeFor(overall, settings.boundaries),
    pass_status: overall >= settings.passMark ? "passed" : "failed",
    calculation_version: settings.version,
    recalculation_reason: reason,
    calculated_at: FieldValue.serverTimestamp(),
    updated_at: FieldValue.serverTimestamp(),
  });
  batch.set(db.collection("users").doc(userId).collection("notifications")
      .doc(`overall-result-v${settings.version}`), {
    user_id: userId,
    category: "overall_result_available",
    title: "Overall result available",
    message: `Your overall academic result is ${overall}/100.`,
    is_read: false,
    created_at: FieldValue.serverTimestamp(),
  }, {merge: true});
  await batch.commit();
  return overall;
}

exports.recalculateOverallResult = onCall({region: REGION}, async (request) => {
  const admin = await actor(request, "coursework.manageSettings");
  const userId = text(request.data?.userId);
  const reason = text(request.data?.reason);
  if (!userId || !reason) throw new HttpsError("invalid-argument", "User and recalculation reason are required.");
  const overall = await recalculateOverallResult(userId, reason);
  return {userId, overall, actorId: admin.uid};
});

exports._test = {
  validateTheme,
  normalizedTheme,
  normalizedPublicQuestion,
  normalizedAnswerKey,
  publicQuestion,
  automaticMark,
  completeAnswer,
  validateOrderManagement,
  currentSettings,
  gradeFor,
  round2,
  normalizedDifficulty,
  normalizedPublicationStatus,
};
