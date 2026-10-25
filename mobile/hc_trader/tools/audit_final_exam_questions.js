"use strict";

const fs = require("fs");
const path = require("path");

const args = parseArgs(process.argv.slice(2));
const projectId = args.project || process.env.GCLOUD_PROJECT || "hcgaming-31d5d";
const projectRoot = path.resolve(__dirname, "..");
const outputPath = path.resolve(
    projectRoot,
    args.output || "build/reports/final_exam_questions_audit.md",
);
const jsonOutputPath = outputPath.replace(/\.md$/i, ".json");
const collection = "final_exam_questions";
const expectedCount = Number(args["expected-count"] || 50);
const expectedOptions = Number(args["expected-options"] || 4);

run().catch((error) => {
  console.error(error.message || error);
  process.exitCode = 1;
});

async function run() {
  const auth = await resolveAuth();
  let docs = await listDocuments(auth);
  if (args["seed-only"] === true) {
    docs = docs.filter(isSeedBankDocument);
  }
  const issues = [];
  const detected = detectSchema(docs.map((doc) => doc.data));
  const stats = {
    projectId,
    collection,
    generatedAt: new Date().toISOString(),
    authMode: auth.mode,
    totalDocuments: docs.length,
    expectedDocuments: expectedCount,
    questionFieldsChecked: 0,
    optionsChecked: 0,
    explanationFieldsChecked: 0,
    documentsMissingChineseQuestion: 0,
    optionsMissingChinese: 0,
    optionCountMismatches: 0,
    invalidCorrectAnswers: 0,
    missingChineseExplanations: 0,
    detectedSchema: detected,
  };

  if (docs.length !== expectedCount) {
    issues.push({
      severity: "critical",
      documentId: "(collection)",
      fieldPath: "(count)",
      issueType: "final_exam_question_count_mismatch",
      englishValue: `${docs.length}`,
      chineseStatus: "invalid_count",
      suggestedFix: `Expected exactly ${expectedCount} documents in ${collection}.`,
    });
  }

  for (const doc of docs) {
    auditDocument(doc, issues, stats);
  }

  const report = {stats, issueCount: issues.length, issues};
  writeReports(report);
  console.log(`Final exam audit written to ${outputPath}`);
  console.log(`JSON report written to ${jsonOutputPath}`);
  console.log(
      `Final exam docs=${stats.totalDocuments}, options=${stats.optionsChecked}, ` +
      `issues=${issues.length}`,
  );
  if (args["fail-on-issues"] === true && issues.length) {
    process.exitCode = 1;
  }
}

function isSeedBankDocument(doc) {
  const data = doc.data || {};
  const seedVersion = data.seedMeta?.contentVersion;
  if (seedVersion === "final_exam_500_v1") return true;
  return /^final_exam_(theory|technical|psychology)_\d{3}$/.test(
      String(data.questionId || doc.id || ""),
  );
}

async function resolveAuth() {
  try {
    const admin = require("firebase-admin");
    admin.initializeApp({
      credential: admin.credential.applicationDefault(),
      projectId,
    });
    const db = admin.firestore();
    await db.collection(collection).limit(1).get();
    return {mode: "firebase-admin", db};
  } catch (_) {
    const auth = require(process.env.APPDATA + "/npm/node_modules/firebase-tools/lib/auth");
    const account = auth.getGlobalDefaultAccount();
    if (!account?.tokens?.refresh_token) {
      throw new Error(
          "No Firebase Admin credentials or Firebase CLI login token available.",
      );
    }
    const token = await auth.getAccessToken(account.tokens.refresh_token, []);
    if (!token?.access_token) {
      throw new Error("Firebase CLI token refresh failed.");
    }
    return {mode: "firebase-cli-rest", accessToken: token.access_token};
  }
}

async function listDocuments(auth) {
  if (auth.db) {
    const snapshot = await auth.db.collection(collection).get();
    return snapshot.docs
        .map((doc) => ({id: doc.id, path: doc.ref.path, data: doc.data()}))
        .sort((left, right) => left.id.localeCompare(right.id));
  }

  const docs = [];
  let pageToken = "";
  do {
    const url = new URL(
        `https://firestore.googleapis.com/v1/projects/${projectId}` +
        `/databases/(default)/documents/${collection}`,
    );
    url.searchParams.set("pageSize", "100");
    if (pageToken) url.searchParams.set("pageToken", pageToken);
    const response = await fetch(url, {
      headers: {Authorization: `Bearer ${auth.accessToken}`},
    });
    if (!response.ok) {
      throw new Error(
          `Firestore REST read failed: ${response.status} ${await response.text()}`,
      );
    }
    const payload = await response.json();
    for (const doc of payload.documents || []) {
      docs.push({
        id: doc.name.split("/").pop(),
        path: `${collection}/${doc.name.split("/").pop()}`,
        data: decodeFields(doc.fields || {}),
      });
    }
    pageToken = payload.nextPageToken || "";
  } while (pageToken);
  return docs.sort((left, right) => left.id.localeCompare(right.id));
}

function auditDocument(doc, issues, stats) {
  const data = doc.data;
  const question = firstText(data, [
    "question",
    "question_text",
    "locales.en.question",
    "text",
    "title",
  ]);
  const questionZh = firstZh(data, [
    "locales.zh.question",
    "question_i18n",
    "question_text_i18n",
    "question",
    "question_text",
    "translations.zh.question",
  ]);
  if (shouldTranslate(question)) {
    stats.questionFieldsChecked++;
    const status = chineseStatus(question, questionZh);
    if (status !== "present") {
      stats.documentsMissingChineseQuestion++;
      issues.push(issue(doc, "question.zh", "missing_chinese_question", status, question));
    }
  }

  const options = normalizeOptions(data.options, data.options_i18n, data.locales);
  if (options.length !== expectedOptions) {
    stats.optionCountMismatches++;
    issues.push(issue(
        doc,
        "options",
        "option_count_mismatch",
        "invalid_count",
        `${options.length}`,
        `Expected exactly ${expectedOptions} options.`,
        "critical",
    ));
  }
  for (const option of options) {
    if (!shouldTranslate(option.en)) continue;
    stats.optionsChecked++;
    const status = chineseStatus(option.en, option.zh);
    if (status !== "present") {
      stats.optionsMissingChinese++;
      issues.push(issue(
          doc,
          `options.${option.id}.zh`,
          "missing_chinese_option",
          status,
          option.en,
      ));
    }
  }

  const correct = correctAnswer(data);
  if (!correct || !options.some((option) => option.id === correct)) {
    stats.invalidCorrectAnswers++;
    issues.push(issue(
        doc,
        "correct_answer",
        "invalid_correct_answer",
        "invalid_or_missing",
        correct,
        "Correct answer must match one of the option IDs.",
        "critical",
    ));
  }

  const explanation = firstText(data, [
    "explanation",
    "answer_explanation",
    "locales.en.explanation",
  ]);
  if (shouldTranslate(explanation)) {
    stats.explanationFieldsChecked++;
    const explanationZh = firstZh(data, [
      "locales.zh.explanation",
      "explanation_i18n",
      "answer_explanation_i18n",
      "explanation",
      "answer_explanation",
      "translations.zh.explanation",
    ]);
    const status = chineseStatus(explanation, explanationZh);
    if (status !== "present") {
      stats.missingChineseExplanations++;
      issues.push(issue(
          doc,
          "explanation.zh",
          "missing_chinese_explanation",
          status,
          explanation,
          "Add explanation_i18n.zh or translations.zh.explanation.",
          "warning",
      ));
    }
  }
}

function detectSchema(items) {
  const fields = new Set();
  const optionShapes = new Set();
  const correctAnswerFields = new Set();
  for (const item of items) {
    Object.keys(item).forEach((field) => fields.add(field));
    if (Array.isArray(item.options)) optionShapes.add("options[]");
    if (isPlainObject(item.options)) optionShapes.add("options.*");
    for (const field of [
      "answer_key",
      "correct_answer",
      "correct_option",
      "correct_option_key",
    ]) {
      if (item[field] != null) correctAnswerFields.add(field);
    }
  }
  return {
    fields: [...fields].sort(),
    optionShapes: [...optionShapes].sort(),
    correctAnswerFields: [...correctAnswerFields].sort(),
  };
}

function normalizeOptions(rawOptions, rawI18n, rawLocales) {
  if (rawOptions == null) {
    const enOptions = localeOptions(rawLocales, "en");
    const zhOptions = localeOptions(rawLocales, "zh");
    if (enOptions.length || zhOptions.length) {
      const length = Math.max(enOptions.length, zhOptions.length);
      return Array.from({length}, (_, index) => ({
        id: String.fromCharCode(65 + index),
        en: text(enOptions[index]),
        zh: text(zhOptions[index]),
      }));
    }
  }
  if (Array.isArray(rawOptions)) {
    return rawOptions.map((value, index) => {
      const id = optionId(value, index);
      const i18n = Array.isArray(rawI18n) ? rawI18n[index] : null;
      return {
        id,
        en: optionText(value),
        zh: localizedZh(i18n) || optionZh(value),
      };
    });
  }
  if (isPlainObject(rawOptions)) {
    return Object.entries(rawOptions)
        .sort((left, right) => String(left[0]).localeCompare(String(right[0])))
        .map(([id, value]) => {
          const i18n = isPlainObject(rawI18n) ? rawI18n[id] : null;
          return {
            id: String(id),
            en: optionText(value),
            zh: localizedZh(i18n) || optionZh(value),
          };
        });
  }
  return [];
}

function optionId(value, index) {
  if (isPlainObject(value)) {
    return String(value.id || value.label || String.fromCharCode(65 + index));
  }
  return String.fromCharCode(65 + index);
}

function optionText(value) {
  if (isPlainObject(value)) {
    return firstText(value, ["option_text", "text", "en", "english", "value", "label"]);
  }
  return text(value);
}

function optionZh(value) {
  if (!isPlainObject(value)) return "";
  return localizedZh(value.option_text_i18n) ||
    localizedZh(value.text_i18n) ||
    localizedZh(value.option_text) ||
    localizedZh(value.text) ||
    localizedZh(value);
}

function correctAnswer(data) {
  const direct =
    data.answer_key ||
    data.correct_answer ||
    data.correct_option ||
    data.correct_option_key;
  if (direct != null && String(direct).trim()) {
    return String(direct).trim().toUpperCase();
  }
  const correctOptionIndex = data.correctOptionIndex ?? data.correct_option_index;
  if (
    Number.isInteger(correctOptionIndex) &&
    correctOptionIndex >= 0 &&
    correctOptionIndex <= 3
  ) {
    return String.fromCharCode(65 + correctOptionIndex);
  }
  const options = data.options;
  if (Array.isArray(options)) {
    const index = options.findIndex((option) => option?.is_correct === true);
    return index >= 0 ? String.fromCharCode(65 + index) : "";
  }
  if (isPlainObject(options)) {
    for (const [id, option] of Object.entries(options)) {
      if (option?.is_correct === true) return String(id).toUpperCase();
    }
  }
  return "";
}

function localeOptions(locales, language) {
  if (!isPlainObject(locales) || !isPlainObject(locales[language])) return [];
  const options = locales[language].options;
  return Array.isArray(options) ? options : [];
}

function firstText(data, paths) {
  for (const pathSpec of paths) {
    const value = readPath(data, pathSpec);
    const resolved = text(value);
    if (resolved) return resolved;
  }
  return "";
}

function firstZh(data, paths) {
  for (const pathSpec of paths) {
    const value = readPath(data, pathSpec);
    const resolved = localizedZh(value) || text(value);
    if (resolved) return resolved;
  }
  return "";
}

function localizedZh(value) {
  if (!isPlainObject(value)) return "";
  return text(value.zh || value.chinese);
}

function text(value) {
  if (value == null) return "";
  if (typeof value === "string") return value.trim();
  if (typeof value === "number" || typeof value === "boolean") {
    return String(value).trim();
  }
  if (isPlainObject(value)) {
    for (const key of ["en", "english", "text", "option_text", "value", "label"]) {
      const child = text(value[key]);
      if (child) return child;
    }
  }
  return "";
}

function readPath(data, pathSpec) {
  return pathSpec.split(".").reduce((current, key) => {
    if (current == null) return undefined;
    return current[key];
  }, data);
}

function shouldTranslate(value) {
  const source = text(value);
  if (!source || hasCjk(source)) return false;
  if (!/[A-Za-z]/.test(source)) return false;
  if (/^https?:\/\//i.test(source)) return false;
  if (/^[A-Z0-9_./:%+\-\s]+$/.test(source) && source.length <= 24) return false;
  return true;
}

function chineseStatus(source, zh) {
  const value = text(zh);
  if (!value) return "missing_zh";
  if (shouldTranslate(source) && !hasCjk(value)) return "english_like_zh";
  return "present";
}

function issue(
    doc,
    fieldPath,
    issueType,
    chineseStatusValue,
    englishValue,
    suggestedFix = "Add a Simplified Chinese translation without changing English fields.",
    severity = "error",
) {
  return {
    severity,
    documentId: doc.id,
    documentPath: doc.path,
    fieldPath,
    issueType,
    englishValue: truncate(englishValue),
    chineseStatus: chineseStatusValue,
    suggestedFix,
  };
}

function decodeFields(fields) {
  const result = {};
  for (const [key, value] of Object.entries(fields)) {
    result[key] = decodeValue(value);
  }
  return result;
}

function decodeValue(value) {
  if ("stringValue" in value) return value.stringValue;
  if ("integerValue" in value) return Number(value.integerValue);
  if ("doubleValue" in value) return Number(value.doubleValue);
  if ("booleanValue" in value) return value.booleanValue;
  if ("nullValue" in value) return null;
  if ("timestampValue" in value) return value.timestampValue;
  if ("arrayValue" in value) {
    return (value.arrayValue.values || []).map(decodeValue);
  }
  if ("mapValue" in value) {
    return decodeFields(value.mapValue.fields || {});
  }
  return null;
}

function writeReports(report) {
  fs.mkdirSync(path.dirname(outputPath), {recursive: true});
  fs.writeFileSync(jsonOutputPath, `${JSON.stringify(report, null, 2)}\n`);
  fs.writeFileSync(outputPath, markdown(report));
}

function markdown(report) {
  const lines = [
    "# Final Exam Questions Audit",
    "",
    `Generated: ${report.stats.generatedAt}`,
    `Project: ${report.stats.projectId}`,
    `Collection: ${report.stats.collection}`,
    `Auth mode: ${report.stats.authMode}`,
    "",
    "## Summary",
    "",
    `- Total documents: ${report.stats.totalDocuments}`,
    `- Expected documents: ${report.stats.expectedDocuments}`,
    `- Options checked: ${report.stats.optionsChecked}`,
    `- Missing Chinese question fields: ${report.stats.documentsMissingChineseQuestion}`,
    `- Missing Chinese option fields: ${report.stats.optionsMissingChinese}`,
    `- Option count mismatches: ${report.stats.optionCountMismatches}`,
    `- Invalid correct answers: ${report.stats.invalidCorrectAnswers}`,
    `- Explanation fields checked: ${report.stats.explanationFieldsChecked}`,
    `- Missing Chinese explanations: ${report.stats.missingChineseExplanations}`,
    "",
    "## Detected Schema",
    "",
    `- Fields: ${report.stats.detectedSchema.fields.join(", ") || "(none)"}`,
    `- Option shapes: ${report.stats.detectedSchema.optionShapes.join(", ") || "(none)"}`,
    `- Correct answer fields: ${report.stats.detectedSchema.correctAnswerFields.join(", ") || "(none)"}`,
    "",
    "## Issues",
    "",
  ];
  if (!report.issues.length) {
    lines.push("No issues found.", "");
    return `${lines.join("\n")}\n`;
  }
  lines.push(
      "| Severity | Document | Field | Status | Issue | English | Suggested Fix |",
      "| --- | --- | --- | --- | --- | --- | --- |",
  );
  for (const item of report.issues) {
    lines.push([
      item.severity,
      item.documentPath,
      item.fieldPath,
      item.chineseStatus,
      item.issueType,
      item.englishValue,
      item.suggestedFix,
    ].map(markdownCell).join(" | ").replace(/^/, "| ").replace(/$/, " |"));
  }
  lines.push("");
  return `${lines.join("\n")}\n`;
}

function markdownCell(value) {
  return String(value == null ? "" : value)
      .replace(/\|/g, "\\|")
      .replace(/\r?\n/g, " ")
      .trim();
}

function truncate(value) {
  const source = String(value == null ? "" : value);
  return source.length > 200 ? `${source.slice(0, 200)}...` : source;
}

function hasCjk(value) {
  return /[\u3400-\u9fff]/.test(String(value || ""));
}

function isPlainObject(value) {
  return !!value &&
    typeof value === "object" &&
    !Array.isArray(value) &&
    typeof value.toDate !== "function" &&
    typeof value.toMillis !== "function";
}

function parseArgs(rawArgs) {
  const parsed = {};
  for (const arg of rawArgs) {
    if (!arg.startsWith("--")) continue;
    const withoutPrefix = arg.slice(2);
    const equals = withoutPrefix.indexOf("=");
    if (equals === -1) {
      parsed[withoutPrefix] = true;
    } else {
      parsed[withoutPrefix.slice(0, equals)] = withoutPrefix.slice(equals + 1);
    }
  }
  return parsed;
}
