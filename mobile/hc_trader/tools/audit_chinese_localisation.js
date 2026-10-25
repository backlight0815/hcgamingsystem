"use strict";

const fs = require("fs");
const path = require("path");

const {
  COLLECTION_GROUPS,
  PROTECTED_TERMS,
  TRANSLATABLE_CONFIG,
} = require("./manual_localization_config");

const args = parseArgs(process.argv.slice(2));
const projectRoot = path.resolve(__dirname, "..");
const reportDir = path.join(projectRoot, "build", "reports");
const outputPath = path.resolve(
    projectRoot,
    args.output || "build/reports/chinese_localisation_audit.md",
);
const jsonOutputPath = outputPath.replace(/\.md$/i, ".json");
const limit = args.limit == null ? null : Number(args.limit);
const finalExamOnly = args["final-exam-only"] === true;
const staticOnly = args["static-only"] === true;
const selectedCollections = resolveCollections(args.collection || args.only);

const issues = [];
const stats = {
  startedAt: new Date().toISOString(),
  static: {
    keysChecked: 0,
    textCallKeysChecked: 0,
    issues: 0,
  },
  firestore: {
    status: "not_started",
    projectId: args.project || process.env.GCLOUD_PROJECT || "",
    collectionsChecked: [],
    docsChecked: 0,
    issues: 0,
    error: "",
  },
  finalExam: {
    attemptsChecked: 0,
    sourceQuestionsChecked: 0,
    bankQuestionsChecked: 0,
    questionFieldsChecked: 0,
    optionFieldsChecked: 0,
    missingQuestionZh: 0,
    missingOptionZh: 0,
    optionCountMismatches: 0,
    questionCountMismatches: 0,
    correctAnswerMismatches: 0,
    explanationFieldsChecked: 0,
    missingExplanationZh: 0,
  },
};

const ALLOW_SAME_TEXT = new Set([
  "English",
  "HC Gaming",
  "PDF",
  "API",
  "MT4",
  "MT5",
  "SL",
  "TP",
  "TP1",
  "TP2",
  "TP3",
  "RR",
  "BE",
  "EMA",
  "RSI",
  "MACD",
  "ATR",
  "VWAP",
  "FVG",
  "POI",
  "XAUUSD",
  "Forex",
  "N/A",
]);

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

async function run() {
  fs.mkdirSync(reportDir, {recursive: true});

  auditStaticLocalisation();

  if (!staticOnly) {
    await auditFirestore();
  }

  writeReports();
  const finalErrors = issues.filter((issue) =>
    ["critical", "error"].includes(issue.severity),
  ).length;
  console.log(`Chinese localisation audit written to ${outputPath}`);
  console.log(`JSON report written to ${jsonOutputPath}`);
  console.log(
      `Issues: total=${issues.length}, errorOrCritical=${finalErrors}`,
  );
  if (args["fail-on-issues"] === true && finalErrors > 0) {
    process.exitCode = 1;
  }
}

function auditStaticLocalisation() {
  const l10nPath = path.join(
      projectRoot,
      "lib",
      "core",
      "localization",
      "app_localizations.dart",
  );
  const source = readUtf8(l10nPath);
  const zhMap = extractDartStringMap(source);
  const keys = Object.keys(zhMap);
  stats.static.keysChecked = keys.length;

  for (const key of keys) {
    const value = zhMap[key] || "";
    const keyPlaceholders = placeholders(key);
    const valuePlaceholders = placeholders(value);
    if (!value.trim()) {
      addIssue({
        module: "Static UI",
        collectionPath: "lib/core/localization/app_localizations.dart",
        documentId: "AppLocalizations._zh",
        fieldPath: key,
        englishValue: key,
        chineseValueStatus: "empty_zh",
        issueType: "empty_static_translation",
        severity: "error",
        suggestedFix: "Add a non-empty Simplified Chinese value.",
      });
    } else if (needsChinese(key) && !hasCjk(value)) {
      addIssue({
        module: "Static UI",
        collectionPath: "lib/core/localization/app_localizations.dart",
        documentId: "AppLocalizations._zh",
        fieldPath: key,
        englishValue: key,
        chineseValueStatus: "english_like_zh",
        issueType: "static_translation_still_english",
        severity: "warning",
        suggestedFix: "Translate the static UI value to Simplified Chinese.",
      });
    }
    if (!sameSet(keyPlaceholders, valuePlaceholders)) {
      addIssue({
        module: "Static UI",
        collectionPath: "lib/core/localization/app_localizations.dart",
        documentId: "AppLocalizations._zh",
        fieldPath: key,
        englishValue: key,
        chineseValueStatus: "placeholder_mismatch",
        issueType: "placeholder_mismatch",
        severity: "error",
        suggestedFix:
          "Keep exactly the same {placeholder} tokens in English and Chinese.",
      });
    }
  }

  const dartFiles = listFiles(path.join(projectRoot, "lib"), ".dart");
  const textKeys = new Set();
  for (const file of dartFiles) {
    const content = readUtf8(file);
    for (const key of extractL10nTextKeys(content)) {
      textKeys.add(key);
      if (!Object.prototype.hasOwnProperty.call(zhMap, key)) {
        addIssue({
          module: "Static UI",
          collectionPath: relativeProjectPath(file),
          documentId: "l10n.text",
          fieldPath: key,
          englishValue: key,
          chineseValueStatus: "missing_zh",
          issueType: "missing_static_key",
          severity: "warning",
          suggestedFix:
            "Add this l10n.text key to AppLocalizations._zh if it is user-facing.",
        });
      }
    }
  }
  stats.static.textCallKeysChecked = textKeys.size;
  stats.static.issues = issues.filter((issue) =>
    issue.module === "Static UI",
  ).length;
}

async function auditFirestore() {
  let admin;
  try {
    admin = require("firebase-admin");
  } catch (error) {
    stats.firestore.status = "skipped_missing_dependency";
    stats.firestore.error =
      "firebase-admin is not installed in mobile/hc_trader/tools.";
    addFirestoreSkippedIssue(stats.firestore.error);
    return;
  }

  const appOptions = {};
  if (stats.firestore.projectId) appOptions.projectId = stats.firestore.projectId;
  try {
    admin.initializeApp({
      credential: admin.credential.applicationDefault(),
      ...appOptions,
    });
  } catch (error) {
    stats.firestore.status = "skipped_auth_initialization_failed";
    stats.firestore.error = error.message || String(error);
    addFirestoreSkippedIssue(stats.firestore.error);
    return;
  }

  const db = admin.firestore();
  try {
    await firestoreQueryFor(db, selectedCollections[0]).limit(1).get();
  } catch (error) {
    stats.firestore.status = "skipped_auth_or_permission_failed";
    stats.firestore.error = error.message || String(error);
    addFirestoreSkippedIssue(stats.firestore.error);
    return;
  }

  for (const collection of selectedCollections) {
    try {
      await auditFirestoreCollection(db, collection);
    } catch (error) {
      stats.firestore.status = "partial";
      addIssue({
        module: moduleForCollection(collection),
        collectionPath: collectionPathFor(collection),
        documentId: "(collection)",
        fieldPath: "(query)",
        englishValue: "",
        chineseValueStatus: "scan_failed",
        issueType: "firestore_scan_failed",
        severity: "error",
        suggestedFix:
          "Check Firebase credentials, project ID, and Firestore permissions.",
        details: error.message || String(error),
      });
    }
  }

  if (stats.firestore.status !== "partial") {
    stats.firestore.status = "completed";
  }
  stats.firestore.issues = issues.filter((issue) =>
    issue.source === "firestore",
  ).length;
}

async function auditFirestoreCollection(db, collection) {
  let query = firestoreQueryFor(db, collection);
  if (Number.isFinite(limit) && limit > 0) {
    query = query.limit(limit);
  }
  const snapshot = await query.get();
  stats.firestore.collectionsChecked.push(collectionPathFor(collection));

  for (const doc of snapshot.docs) {
    stats.firestore.docsChecked++;
    const data = doc.data();
    auditDynamicFields(collection, doc.ref.path, doc.id, data);

    if (collection === "final_exam_attempts") {
      auditFinalExamAttempt(doc.ref.path, doc.id, data);
    } else if (collection === "final_exam_questions") {
      auditFinalExamSourceQuestion(doc.ref.path, doc.id, data);
    } else if (collection === "final_exam_question_banks") {
      auditFinalExamBank(doc.ref.path, doc.id, data);
    }
  }
}

function firestoreQueryFor(db, collection) {
  return COLLECTION_GROUPS[collection] != null ?
    db.collectionGroup(collection) :
    db.collection(collection);
}

function auditDynamicFields(collection, docPath, documentId, data) {
  const paths = TRANSLATABLE_CONFIG[collection] || [];
  for (const entry of collectEntries(data, paths)) {
    const english = sourceForEntry(entry);
    if (!shouldTranslateText(english)) continue;
    const status = chineseStatus(english, chineseForEntry(entry));
    if (status === "present") continue;
    addIssue({
      source: "firestore",
      module: moduleForCollection(collection),
      collectionPath: collectionPathFor(collection),
      documentId,
      documentPath: docPath,
      fieldPath: entry.path,
      questionIndex: questionIndexFromPath(entry.path),
      optionIndex: optionIndexFromPath(entry.path),
      englishValue: english,
      chineseValueStatus: status,
      issueType: `dynamic_${status}`,
      severity: collection.includes("final_exam") ? "error" : "warning",
      suggestedFix: suggestedFirestoreFix(collection),
    });
  }
}

function auditFinalExamAttempt(docPath, documentId, data) {
  const questions = Array.isArray(data.questions) ? data.questions : [];
  stats.finalExam.attemptsChecked++;
  if (questions.length !== 50) {
    stats.finalExam.questionCountMismatches++;
    addIssue({
      source: "firestore",
      module: "Final Examination",
      collectionPath: COLLECTION_GROUPS.final_exam_attempts,
      documentId,
      documentPath: docPath,
      fieldPath: "questions",
      englishValue: `${questions.length}`,
      chineseValueStatus: "invalid_count",
      issueType: "final_exam_question_count_not_50",
      severity: "critical",
      suggestedFix:
        "Ensure active Final Examination attempts contain exactly 50 question snapshots.",
    });
  }

  questions.forEach((question, index) => {
    if (!isPlainObject(question)) return;
    auditFinalExamQuestionShape(
        "Final Examination",
        COLLECTION_GROUPS.final_exam_attempts,
        documentId,
        docPath,
        question,
        `questions.${index}`,
        index,
        true,
    );
  });
}

function auditFinalExamSourceQuestion(docPath, documentId, data) {
  stats.finalExam.sourceQuestionsChecked++;
  auditFinalExamQuestionShape(
      "Final Examination Source",
      "final_exam_questions",
      documentId,
      docPath,
      data,
      "",
      null,
      false,
  );
}

function auditFinalExamBank(docPath, documentId, data) {
  const questions = Array.isArray(data.questions) ? data.questions : [];
  questions.forEach((question, index) => {
    if (!isPlainObject(question)) return;
    stats.finalExam.bankQuestionsChecked++;
    auditFinalExamQuestionShape(
        "Final Examination Bank",
        "final_exam_question_banks",
        documentId,
        docPath,
        question,
        `questions.${index}`,
        index,
        false,
    );
  });
}

function auditFinalExamQuestionShape(
    module,
    collectionPath,
    documentId,
    docPath,
    question,
    prefix,
    questionIndex,
    requireAttemptFields,
) {
  const questionText = textValue(
      question.question || question.question_text || question.text,
  );
  const questionZh = localizedChineseValue(
      question.question_i18n ||
      question.question_text_i18n ||
      question.question ||
      question.question_text,
  );
  if (shouldTranslateText(questionText)) {
    stats.finalExam.questionFieldsChecked++;
    const status = chineseStatus(questionText, questionZh);
    if (status !== "present") {
      stats.finalExam.missingQuestionZh++;
      addIssue({
        source: "firestore",
        module,
        collectionPath,
        documentId,
        documentPath: docPath,
        fieldPath: joinPath(prefix, "question.zh"),
        questionIndex,
        englishValue: questionText,
        chineseValueStatus: status,
        issueType: "final_exam_question_missing_chinese",
        severity: "error",
        suggestedFix: suggestedFirestoreFix("final_exam_attempts"),
      });
    }
  }

  const explanation = textValue(question.explanation);
  if (shouldTranslateText(explanation)) {
    stats.finalExam.explanationFieldsChecked++;
    const status = chineseStatus(
        explanation,
        localizedChineseValue(question.explanation_i18n || question.explanation),
    );
    if (status !== "present") {
      stats.finalExam.missingExplanationZh++;
      addIssue({
        source: "firestore",
        module,
        collectionPath,
        documentId,
        documentPath: docPath,
        fieldPath: joinPath(prefix, "explanation.zh"),
        questionIndex,
        englishValue: explanation,
        chineseValueStatus: status,
        issueType: "final_exam_explanation_missing_chinese",
        severity: requireAttemptFields ? "error" : "warning",
        suggestedFix: suggestedFirestoreFix("final_exam_attempts"),
      });
    }
  }

  const options = normalizedOptions(question.options, question.options_i18n);
  if (options.length !== 4) {
    stats.finalExam.optionCountMismatches++;
    addIssue({
      source: "firestore",
      module,
      collectionPath,
      documentId,
      documentPath: docPath,
      fieldPath: joinPath(prefix, "options"),
      questionIndex,
      englishValue: `${options.length}`,
      chineseValueStatus: "invalid_count",
      issueType: "final_exam_option_count_not_4",
      severity: "critical",
      suggestedFix: "Ensure every Final Examination question has exactly 4 options.",
    });
  }

  const optionKeys = options.map((option) => option.id);
  const expectedKeys = ["A", "B", "C", "D"];
  if (options.length === 4 && !sameOrderedList(optionKeys, expectedKeys)) {
    addIssue({
      source: "firestore",
      module,
      collectionPath,
      documentId,
      documentPath: docPath,
      fieldPath: joinPath(prefix, "options"),
      questionIndex,
      englishValue: optionKeys.join(","),
      chineseValueStatus: "order_or_id_mismatch",
      issueType: "final_exam_option_order_or_ids_changed",
      severity: "error",
      suggestedFix:
        "Preserve option IDs/order as A, B, C, D so answer mapping remains stable.",
    });
  }

  for (const option of options) {
    if (!shouldTranslateText(option.text)) continue;
    stats.finalExam.optionFieldsChecked++;
    const status = chineseStatus(option.text, option.zh);
    if (status !== "present") {
      stats.finalExam.missingOptionZh++;
      addIssue({
        source: "firestore",
        module,
        collectionPath,
        documentId,
        documentPath: docPath,
        fieldPath: joinPath(prefix, `options.${option.id}.zh`),
        questionIndex,
        optionIndex: option.id,
        englishValue: option.text,
        chineseValueStatus: status,
        issueType: "final_exam_option_missing_chinese",
        severity: "error",
        suggestedFix: suggestedFirestoreFix("final_exam_attempts"),
      });
    }
  }

  const correct = correctAnswerKey(question);
  if (correct && !optionKeys.includes(correct)) {
    stats.finalExam.correctAnswerMismatches++;
    addIssue({
      source: "firestore",
      module,
      collectionPath,
      documentId,
      documentPath: docPath,
      fieldPath: joinPath(prefix, "answer_key"),
      questionIndex,
      englishValue: correct,
      chineseValueStatus: "answer_key_mismatch",
      issueType: "final_exam_correct_answer_missing_from_options",
      severity: "critical",
      suggestedFix:
        "Keep the correct answer ID unchanged and matching one of A, B, C, or D.",
    });
  }
}

function collectEntries(data, paths) {
  const entries = [];
  for (const pathSpec of paths) {
    walkPath(data, parsePath(pathSpec), 0, [], entries);
  }
  return entries;
}

function parsePath(pathSpec) {
  return pathSpec.split(".").map((segment) => {
    if (segment === "*") return {wildcard: true};
    if (segment.endsWith("[]")) {
      return {key: segment.slice(0, -2), array: true};
    }
    return {key: segment};
  });
}

function walkPath(current, tokens, index, chain, entries) {
  if (!isPlainObject(current) && !Array.isArray(current)) return;
  const token = tokens[index];
  if (!token) return;

  if (token.wildcard) {
    if (Array.isArray(current)) {
      current.forEach((child, childIndex) => walkPath(
          child,
          tokens,
          index + 1,
          [...chain, childIndex],
          entries,
      ));
      return;
    }
    for (const [key, child] of Object.entries(current)) {
      walkPath(child, tokens, index + 1, [...chain, key], entries);
    }
    return;
  }

  if (!isPlainObject(current) || !(token.key in current)) return;

  const nextToken = tokens[index + 1];
  if (
    nextToken?.wildcard === true &&
    index + 1 === tokens.length - 1 &&
    isPlainObject(current[token.key])
  ) {
    for (const key of Object.keys(current[token.key])) {
      entries.push({
        kind: "mapItem",
        parent: current,
        fieldKey: token.key,
        entryKey: key,
        path: [...chain, token.key, key].join("."),
        sidecarKey: `${token.key}_i18n`,
      });
    }
    return;
  }

  if (token.array) {
    const value = current[token.key];
    if (!Array.isArray(value)) return;
    if (index === tokens.length - 1) {
      value.forEach((child, childIndex) => {
        entries.push({
          kind: "arrayItem",
          parent: current,
          fieldKey: token.key,
          index: childIndex,
          path: [...chain, token.key, childIndex].join("."),
          sidecarKey: `${token.key}_i18n`,
        });
      });
      return;
    }
    value.forEach((child, childIndex) => walkPath(
        child,
        tokens,
        index + 1,
        [...chain, token.key, childIndex],
        entries,
    ));
    return;
  }

  if (index === tokens.length - 1) {
    entries.push({
      kind: "field",
      parent: current,
      key: token.key,
      path: [...chain, token.key].join("."),
      sidecarKey: `${token.key}_i18n`,
    });
    return;
  }

  walkPath(current[token.key], tokens, index + 1, [...chain, token.key], entries);
}

function sourceForEntry(entry) {
  if (entry.kind === "mapItem") {
    const source = entry.parent[entry.fieldKey]?.[entry.entryKey];
    const sidecar = entry.parent[entry.sidecarKey]?.[entry.entryKey];
    return textValue(source) || textValue(sidecar);
  }
  if (entry.kind === "arrayItem") {
    const source = entry.parent[entry.fieldKey]?.[entry.index];
    const sidecar = entry.parent[entry.sidecarKey]?.[entry.index];
    return textValue(source) || textValue(sidecar);
  }
  return textValue(entry.parent[entry.key]) ||
    textValue(entry.parent[entry.sidecarKey]);
}

function chineseForEntry(entry) {
  if (entry.kind === "mapItem") {
    const source = entry.parent[entry.fieldKey]?.[entry.entryKey];
    const sidecar = entry.parent[entry.sidecarKey]?.[entry.entryKey];
    return localizedChineseValue(sidecar) || localizedChineseValue(source);
  }
  if (entry.kind === "arrayItem") {
    const source = entry.parent[entry.fieldKey]?.[entry.index];
    const sidecar = entry.parent[entry.sidecarKey]?.[entry.index];
    return localizedChineseValue(sidecar) || localizedChineseValue(source);
  }
  return localizedChineseValue(entry.parent[entry.sidecarKey]) ||
    localizedChineseValue(entry.parent[entry.key]);
}

function localizedChineseValue(value) {
  if (!isPlainObject(value)) return "";
  return textValue(value.zh || value.chinese);
}

function textValue(value) {
  if (value == null) return "";
  if (typeof value === "string") return value.trim();
  if (typeof value === "number" || typeof value === "boolean") {
    return String(value).trim();
  }
  if (isPlainObject(value)) {
    for (const key of ["en", "english", "text", "option_text", "value", "label"]) {
      const text = textValue(value[key]);
      if (text) return text;
    }
  }
  return "";
}

function normalizedOptions(rawOptions, rawI18n) {
  if (isPlainObject(rawOptions)) {
    return Object.entries(rawOptions)
        .sort((left, right) => String(left[0]).localeCompare(String(right[0])))
        .map(([key, value]) => {
          const i18n = isPlainObject(rawI18n) ? rawI18n[key] : null;
          return {
            id: String(key),
            text: optionText(value),
            zh: localizedChineseValue(i18n) || optionZh(value),
          };
        });
  }
  if (Array.isArray(rawOptions)) {
    return rawOptions.map((value, index) => {
      const id = optionId(value, index);
      const i18n = Array.isArray(rawI18n) ? rawI18n[index] : null;
      return {
        id,
        text: optionText(value),
        zh: localizedChineseValue(i18n) || optionZh(value),
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
    return textValue(
        value.option_text ||
        value.text ||
        value.value ||
        value.label ||
        value,
    );
  }
  return textValue(value);
}

function optionZh(value) {
  if (!isPlainObject(value)) return "";
  return localizedChineseValue(value.option_text_i18n) ||
    localizedChineseValue(value.text_i18n) ||
    localizedChineseValue(value.option_text) ||
    localizedChineseValue(value.text) ||
    localizedChineseValue(value);
}

function correctAnswerKey(question) {
  const direct =
    question.answer_key ||
    question.correct_answer ||
    question.correct_option ||
    question.correct_option_key;
  if (direct != null && String(direct).trim()) {
    return String(direct).trim().toUpperCase();
  }
  const options = question.options;
  if (isPlainObject(options)) {
    for (const [key, option] of Object.entries(options)) {
      if (isPlainObject(option) && option.is_correct === true) {
        return String(key).toUpperCase();
      }
    }
  } else if (Array.isArray(options)) {
    for (let index = 0; index < options.length; index++) {
      const option = options[index];
      if (isPlainObject(option) && option.is_correct === true) {
        return String.fromCharCode(65 + index);
      }
    }
  }
  return "";
}

function chineseStatus(source, zh) {
  const value = String(zh || "").trim();
  if (!value) return "missing_zh";
  if (needsChinese(source) && !hasCjk(value)) return "english_like_zh";
  return "present";
}

function shouldTranslateText(text) {
  const value = String(text || "").trim();
  if (!value) return false;
  if (hasCjk(value)) return false;
  if (/^https?:\/\//i.test(value)) return false;
  if (/^[\w.-]+@[\w.-]+\.\w+$/.test(value)) return false;
  if (/^\d+(?:\.\d+)?%?$/.test(value)) return false;
  if (!/[A-Za-z]/.test(value)) return false;
  return needsChinese(value);
}

function needsChinese(text) {
  const value = String(text || "").trim();
  if (!value || hasCjk(value)) return false;
  if (ALLOW_SAME_TEXT.has(value)) return false;
  if (/^[A-Z0-9_./:%+\-\s]+$/.test(value) && value.length <= 24) {
    return false;
  }
  if (PROTECTED_TERMS.includes(value)) return false;
  return /[A-Za-z]/.test(value);
}

function hasCjk(value) {
  return /[\u3400-\u9fff]/.test(String(value || ""));
}

function placeholders(value) {
  return new Set([...String(value || "").matchAll(/\{([A-Za-z0-9_]+)\}/g)]
      .map((match) => match[1]));
}

function sameSet(left, right) {
  if (left.size !== right.size) return false;
  for (const value of left) {
    if (!right.has(value)) return false;
  }
  return true;
}

function sameOrderedList(left, right) {
  if (left.length !== right.length) return false;
  return left.every((value, index) => value === right[index]);
}

function extractDartStringMap(source) {
  const map = {};
  for (const mapName of ["_zh", "_zhOverrides"]) {
    const match = source.match(
        new RegExp(`static const ${mapName} = <String, String>\\{([\\s\\S]*?)\\n\\s*\\};`),
    );
    if (!match) continue;
    const body = match[1];
    const pairPattern = /'((?:\\.|[^'\\])*)'\s*:\s*'((?:\\.|[^'\\])*)'/g;
    for (const pair of body.matchAll(pairPattern)) {
      map[unescapeDartString(pair[1])] = unescapeDartString(pair[2]);
    }
  }
  return map;
}

function extractL10nTextKeys(content) {
  const keys = [];
  const directPattern = /\b(?:context\.)?l10n\.text\(\s*'((?:\\.|[^'\\])*)'/g;
  for (const match of content.matchAll(directPattern)) {
    const key = unescapeDartString(match[1]);
    if (!key.includes("$")) keys.push(key);
  }
  const ternaryPattern =
    /\b(?:context\.)?l10n\.text\(\s*[^(){};,\n]+\?\s*'((?:\\.|[^'\\])*)'\s*:\s*'((?:\\.|[^'\\])*)'/g;
  for (const match of content.matchAll(ternaryPattern)) {
    const left = unescapeDartString(match[1]);
    const right = unescapeDartString(match[2]);
    if (!left.includes("$")) keys.push(left);
    if (!right.includes("$")) keys.push(right);
  }
  return keys;
}

function unescapeDartString(value) {
  return value
      .replace(/\\u([0-9a-fA-F]{4})/g, (_, code) =>
        String.fromCharCode(Number.parseInt(code, 16)))
      .replace(/\\'/g, "'")
      .replace(/\\n/g, "\n")
      .replace(/\\\\/g, "\\");
}

function listFiles(root, extension) {
  const results = [];
  if (!fs.existsSync(root)) return results;
  for (const entry of fs.readdirSync(root, {withFileTypes: true})) {
    const fullPath = path.join(root, entry.name);
    if (entry.isDirectory()) {
      results.push(...listFiles(fullPath, extension));
    } else if (entry.isFile() && entry.name.endsWith(extension)) {
      results.push(fullPath);
    }
  }
  return results;
}

function addIssue(issue) {
  issues.push({
    source: "static",
    questionIndex: null,
    optionIndex: null,
    documentPath: "",
    details: "",
    ...issue,
    englishValue: truncate(issue.englishValue || ""),
  });
}

function addFirestoreSkippedIssue(details) {
  addIssue({
    source: "firestore",
    module: "Firebase dynamic content",
    collectionPath: "(all configured collections)",
    documentId: "(not scanned)",
    fieldPath: "(authentication)",
    englishValue: "",
    chineseValueStatus: "not_scanned",
    issueType: "firestore_audit_skipped",
    severity: args["require-firestore"] === true ? "error" : "warning",
    suggestedFix:
      "Set GOOGLE_APPLICATION_CREDENTIALS to a service account JSON and rerun the audit.",
    details,
  });
}

function moduleForCollection(collection) {
  if (collection.startsWith("final_exam")) return "Final Examination";
  if (collection.startsWith("trading_exam")) return "Daily Examination";
  if (collection === "market_analyses") return "Market Outlook";
  if (
    [
      "trading_resources",
      "knowledge_centres",
      "trading_signals",
      "trading_recordings",
      "trading_blogs",
      "trading_news",
    ].includes(collection)
  ) {
    return "Trading Hub";
  }
  if (collection === "prop_firm_models") return "Trading Toolkit";
  return collection;
}

function collectionPathFor(collection) {
  return COLLECTION_GROUPS[collection] || collection;
}

function suggestedFirestoreFix(collection) {
  return `Add reviewed English and Simplified Chinese values manually for ${collection}, then rerun npm run audit:zh.`;
}

function questionIndexFromPath(fieldPath) {
  const match = String(fieldPath).match(/(?:questions|question_results)\.(\d+)/);
  return match ? Number(match[1]) : null;
}

function optionIndexFromPath(fieldPath) {
  const match = String(fieldPath).match(/options\.([^.]+)/);
  return match ? match[1] : null;
}

function joinPath(prefix, suffix) {
  return prefix ? `${prefix}.${suffix}` : suffix;
}

function truncate(value) {
  const text = String(value || "");
  return text.length > 180 ? `${text.slice(0, 180)}...` : text;
}

function isPlainObject(value) {
  return !!value &&
    typeof value === "object" &&
    !Array.isArray(value) &&
    typeof value.toDate !== "function" &&
    typeof value.toMillis !== "function";
}

function relativeProjectPath(file) {
  return path.relative(projectRoot, file).replace(/\\/g, "/");
}

function readUtf8(file) {
  return fs.readFileSync(file, "utf8");
}

function writeReports() {
  const report = {
    stats,
    issueCount: issues.length,
    issues,
  };
  fs.mkdirSync(path.dirname(outputPath), {recursive: true});
  fs.writeFileSync(jsonOutputPath, `${JSON.stringify(report, null, 2)}\n`);
  fs.writeFileSync(outputPath, markdownReport(report));
}

function markdownReport(report) {
  const bySeverity = countBy(issues, "severity");
  const lines = [
    "# Chinese Localisation Audit",
    "",
    `Generated: ${report.stats.startedAt}`,
    `Firestore status: ${report.stats.firestore.status}`,
    `Firestore project: ${report.stats.firestore.projectId || "(application default)"}`,
    "",
    "## Summary",
    "",
    `- Total issues: ${issues.length}`,
    `- Critical: ${bySeverity.critical || 0}`,
    `- Errors: ${bySeverity.error || 0}`,
    `- Warnings: ${bySeverity.warning || 0}`,
    `- Static keys checked: ${report.stats.static.keysChecked}`,
    `- Static l10n.text keys checked: ${report.stats.static.textCallKeysChecked}`,
    `- Firestore docs checked: ${report.stats.firestore.docsChecked}`,
    `- Firestore collections checked: ${report.stats.firestore.collectionsChecked.join(", ") || "(none)"}`,
    "",
    "## Final Examination Strict Validation",
    "",
    `- Attempts checked: ${report.stats.finalExam.attemptsChecked}`,
    `- Source questions checked: ${report.stats.finalExam.sourceQuestionsChecked}`,
    `- Bank questions checked: ${report.stats.finalExam.bankQuestionsChecked}`,
    `- Question fields checked: ${report.stats.finalExam.questionFieldsChecked}`,
    `- Option fields checked: ${report.stats.finalExam.optionFieldsChecked}`,
    `- Missing Chinese question fields: ${report.stats.finalExam.missingQuestionZh}`,
    `- Missing Chinese option fields: ${report.stats.finalExam.missingOptionZh}`,
    `- Explanation fields checked: ${report.stats.finalExam.explanationFieldsChecked}`,
    `- Missing Chinese explanations: ${report.stats.finalExam.missingExplanationZh}`,
    `- Question count mismatches: ${report.stats.finalExam.questionCountMismatches}`,
    `- Option count mismatches: ${report.stats.finalExam.optionCountMismatches}`,
    `- Correct answer mismatches: ${report.stats.finalExam.correctAnswerMismatches}`,
    "",
  ];

  if (report.stats.firestore.error) {
    lines.push("## Firestore Note", "", report.stats.firestore.error, "");
  }

  lines.push("## Issues", "");
  if (!issues.length) {
    lines.push("No issues found by this audit.", "");
    return `${lines.join("\n")}\n`;
  }

  lines.push(
      "| Severity | Module | Collection | Document | Field | Question | Option | Status | Issue | English | Suggested Fix |",
      "| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |",
  );
  for (const issue of issues.slice(0, 500)) {
    lines.push([
      issue.severity,
      issue.module,
      issue.collectionPath,
      issue.documentPath || issue.documentId,
      issue.fieldPath,
      issue.questionIndex == null ? "" : issue.questionIndex,
      issue.optionIndex == null ? "" : issue.optionIndex,
      issue.chineseValueStatus,
      issue.issueType,
      issue.englishValue,
      issue.suggestedFix,
    ].map(markdownCell).join(" | ").replace(/^/, "| ").replace(/$/, " |"));
  }
  if (issues.length > 500) {
    lines.push("", `Only the first 500 issues are shown. See ${path.basename(jsonOutputPath)} for all issues.`);
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

function countBy(items, key) {
  const counts = {};
  for (const item of items) {
    counts[item[key]] = (counts[item[key]] || 0) + 1;
  }
  return counts;
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

function resolveCollections(value) {
  const allCollections = Object.keys(TRANSLATABLE_CONFIG);
  const collections = finalExamOnly ?
    ["final_exam_questions", "final_exam_question_banks", "final_exam_attempts"] :
    allCollections;
  if (!value) return collections;
  const selected = String(value)
      .split(",")
      .map((item) => item.trim())
      .filter(Boolean);
  const unsupported = selected.filter(
      (collection) => !TRANSLATABLE_CONFIG[collection],
  );
  if (unsupported.length) {
    throw new Error(`Unsupported collection(s): ${unsupported.join(", ")}`);
  }
  return selected;
}
