"use strict";

const {
  decodeFields,
  encodeFields,
  listFinalExamQuestionDocuments,
  resolveFirebaseAuth,
} = require("./firebase_rest_helpers");

const args = parseArgs(process.argv.slice(2));
const projectId = args.project || process.env.GCLOUD_PROJECT || "hcgaming-31d5d";
const apply = args.apply === true;
const statusFilter = text(args.status);

run().catch((error) => {
  console.error(error.message || error);
  process.exitCode = 1;
});

async function run() {
  const auth = await resolveFirebaseAuth(projectId);
  const sources = await listFinalExamQuestionDocuments({projectId, auth});
  const sourceIndex = buildSourceIndex(sources);
  const attempts = await listFinalExamAttempts({projectId, auth, statusFilter});
  const summary = {
    sourceQuestions: sources.length,
    attemptsScanned: attempts.length,
    writeMode: apply,
    statusFilter: statusFilter || null,
    attemptsToUpdate: 0,
    questionsScanned: 0,
    questionsMatched: 0,
    questionSidecarsAdded: 0,
    categorySidecarsAdded: 0,
    subcategorySidecarsAdded: 0,
    scenarioSidecarsAdded: 0,
    explanationSidecarsAdded: 0,
    optionSidecarsAdded: 0,
    invalidOptionCount: 0,
    invalidAnswerKey: 0,
    missingSourceIds: [],
    missingSourceTranslations: [],
  };
  const updates = [];

  for (const attempt of attempts) {
    const update = buildAttemptUpdate(attempt, sourceIndex);
    addCounts(summary, update.summary);
    if (update.fields.questions) {
      summary.attemptsToUpdate++;
      updates.push({attempt, fields: update.fields});
    }
  }

  summary.missingSourceIds = [...new Set(summary.missingSourceIds)].slice(0, 50);
  summary.missingSourceTranslations = [
    ...new Set(summary.missingSourceTranslations),
  ].slice(0, 50);

  console.log(JSON.stringify(summary, null, 2));
  if (!apply) {
    console.log("Dry run only. Re-run with --apply to update saved attempts.");
    return;
  }

  for (const {attempt, fields} of updates) {
    await patchDocument({projectId, auth, doc: attempt, fields});
    console.log(`[updated] ${attempt.path}`);
  }
}

function buildSourceIndex(docs) {
  const byId = new Map();
  const byQuestion = new Map();
  for (const doc of docs) {
    const data = doc.data;
    const source = {
      id: doc.id,
      questionText: text(data.question || data.question_text),
      categoryText: text(data.category),
      subcategoryText: text(data.subcategory),
      scenarioText: text(data.scenario_text),
      explanationText: text(data.explanation || data.answer_explanation),
      options: normalizeOptions(data.options),
      payload: sourcePayload(data),
    };
    for (const id of [doc.id, data.id, data.question_id]) {
      const normalized = text(id);
      if (normalized) byId.set(normalized, source);
    }
    const questionKey = normalizeLookup(source.questionText);
    if (questionKey) byQuestion.set(questionKey, source);
  }
  return {byId, byQuestion};
}

function sourcePayload(data) {
  const question = text(data.question || data.question_text);
  const category = text(data.category);
  const subcategory = text(data.subcategory);
  const scenarioText = text(data.scenario_text);
  const explanation = text(data.explanation || data.answer_explanation);
  return {
    category_i18n: localizedField(data, "category", null, category),
    subcategory_i18n: localizedField(data, "subcategory", null, subcategory),
    question_i18n: localizedField(data, "question", "question_text", question),
    scenario_text_i18n: localizedField(
        data,
        "scenario_text",
        null,
        scenarioText,
    ),
    explanation_i18n: localizedField(
        data,
        "explanation",
        "answer_explanation",
        explanation,
    ),
    options_i18n: sourceOptionsI18n(data.options, data.options_i18n),
  };
}

function sourceOptionsI18n(rawOptions, rawI18n) {
  const i18n = isPlainObject(rawI18n) ? rawI18n : {};
  const result = {};
  for (const option of normalizeOptions(rawOptions)) {
    const value = {
      ...localizedMap(option.raw),
      ...localizedMap(i18n[option.id]),
    };
    if (option.text && !value.en) value.en = option.text;
    if (hasUsableChinese(value, option.text)) result[option.id] = value;
  }
  return result;
}

function buildAttemptUpdate(attempt, sourceIndex) {
  const questions = Array.isArray(attempt.data.questions) ?
    attempt.data.questions :
    [];
  const summary = {
    questionsScanned: questions.length,
    questionsMatched: 0,
    questionSidecarsAdded: 0,
    categorySidecarsAdded: 0,
    subcategorySidecarsAdded: 0,
    scenarioSidecarsAdded: 0,
    explanationSidecarsAdded: 0,
    optionSidecarsAdded: 0,
    invalidOptionCount: 0,
    invalidAnswerKey: 0,
    missingSourceIds: [],
    missingSourceTranslations: [],
  };
  let changed = false;
  const enrichedQuestions = questions.map((rawQuestion, index) => {
    if (!isPlainObject(rawQuestion)) return rawQuestion;
    const question = {...rawQuestion};
    const source = findSource(question, sourceIndex);
    const options = normalizeOptions(question.options);
    const answerKey = correctAnswer(question);
    if (options.length && options.length !== 4) summary.invalidOptionCount++;
    if (answerKey && !options.some((option) => option.id === answerKey)) {
      summary.invalidAnswerKey++;
    }
    if (!source) {
      summary.missingSourceIds.push(
          `${attempt.path} question ${index + 1}: ${sourceLookupText(question)}`,
      );
      return question;
    }

    summary.questionsMatched++;
    for (const field of [
      ["category_i18n", "categoryText", "categorySidecarsAdded"],
      ["subcategory_i18n", "subcategoryText", "subcategorySidecarsAdded"],
      ["question_i18n", "questionText", "questionSidecarsAdded"],
      ["scenario_text_i18n", "scenarioText", "scenarioSidecarsAdded"],
      ["explanation_i18n", "explanationText", "explanationSidecarsAdded"],
    ]) {
      const [fieldName, sourceTextKey, counter] = field;
      const incoming = source.payload[fieldName];
      if (!hasUsableChinese(incoming, source[sourceTextKey])) continue;
      if (hasUsableChinese(question[fieldName], source[sourceTextKey])) {
        continue;
      }
      question[fieldName] = mergeLocalizedMap(
          question[fieldName],
          incoming,
          source[sourceTextKey],
      );
      summary[counter]++;
      changed = true;
    }

    const optionResult = mergeOptionSidecars(question, source, attempt.path);
    if (optionResult.changed) {
      question.options_i18n = optionResult.optionsI18n;
      summary.optionSidecarsAdded += optionResult.optionSidecarsAdded;
      summary.missingSourceTranslations.push(
          ...optionResult.missingSourceTranslations,
      );
      changed = true;
    }
    return question;
  });

  return {
    fields: changed ? {questions: enrichedQuestions} : {},
    summary,
  };
}

function mergeOptionSidecars(question, source, attemptPath) {
  const existing = isPlainObject(question.options_i18n) ?
    {...question.options_i18n} :
    {};
  const missingSourceTranslations = [];
  let changed = false;
  let optionSidecarsAdded = 0;
  for (const option of normalizeOptions(question.options)) {
    const incoming = source.payload.options_i18n[option.id];
    if (!hasUsableChinese(incoming, option.text)) {
      missingSourceTranslations.push(
          `${attemptPath} ${source.id} option ${option.id}: ${option.text}`,
      );
      continue;
    }
    if (hasUsableChinese(existing[option.id], option.text)) continue;
    existing[option.id] = mergeLocalizedMap(existing[option.id], incoming, option.text);
    changed = true;
    optionSidecarsAdded++;
  }
  return {
    changed,
    optionsI18n: existing,
    optionSidecarsAdded,
    missingSourceTranslations,
  };
}

function findSource(question, sourceIndex) {
  const directIds = [
    question.question_id,
    question.source_question_id,
    question.attempt_question_id,
    question.id,
  ].map(text);
  for (const id of directIds) {
    if (id && sourceIndex.byId.has(id)) return sourceIndex.byId.get(id);
  }
  const questionKey = normalizeLookup(text(question.question || question.question_text));
  return questionKey ? sourceIndex.byQuestion.get(questionKey) : null;
}

function sourceLookupText(question) {
  return text(
      question.question_id ||
      question.source_question_id ||
      question.attempt_question_id ||
      question.question ||
      question.question_text,
  );
}

function localizedField(data, key, aliasKey, englishFallback) {
  const map = {
    ...localizedMap(data[`${key}_i18n`]),
  };
  if (aliasKey) {
    Object.assign(map, localizedMap(data[`${aliasKey}_i18n`]));
  }
  const en = text(data[`${key}_en`] || (aliasKey && data[`${aliasKey}_en`]));
  const zh = text(data[`${key}_zh`] || (aliasKey && data[`${aliasKey}_zh`]));
  if (englishFallback && !map.en) map.en = englishFallback;
  if (en) map.en = en;
  if (zh) map.zh = zh;
  return map;
}

function mergeLocalizedMap(existing, incoming, englishFallback) {
  const map = {
    ...localizedMap(existing),
    ...localizedMap(incoming),
  };
  if (englishFallback && !map.en) map.en = englishFallback;
  return map;
}

async function listFinalExamAttempts({projectId, auth, statusFilter}) {
  const query = {
    from: [{collectionId: "final_exam_attempts", allDescendants: true}],
  };
  if (statusFilter) {
    query.where = {
      fieldFilter: {
        field: {fieldPath: "status"},
        op: "EQUAL",
        value: {stringValue: statusFilter},
      },
    };
  }
  const url =
    `https://firestore.googleapis.com/v1/projects/${projectId}` +
    "/databases/(default)/documents:runQuery";
  const response = await fetch(url, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${auth.accessToken}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({structuredQuery: query}),
  });
  if (!response.ok) {
    throw new Error(
        `Firestore REST collection-group read failed: ` +
        `${response.status} ${await response.text()}`,
    );
  }
  const payload = await response.json();
  return payload
      .filter((item) => item.document)
      .map((item) => {
        const path = documentPathFromName(item.document.name);
        return {
          name: item.document.name,
          path,
          data: decodeFields(item.document.fields || {}),
        };
      })
      .sort((left, right) => left.path.localeCompare(right.path));
}

async function patchDocument({projectId, auth, doc, fields}) {
  const documentPath = documentPathFromName(doc.name)
      .split("/")
      .map(encodeURIComponent)
      .join("/");
  const url = new URL(
      `https://firestore.googleapis.com/v1/projects/${projectId}` +
      `/databases/(default)/documents/${documentPath}`,
  );
  for (const field of Object.keys(fields)) {
    url.searchParams.append("updateMask.fieldPaths", field);
  }
  const response = await fetch(url, {
    method: "PATCH",
    headers: {
      Authorization: `Bearer ${auth.accessToken}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({fields: encodeFields(fields)}),
  });
  if (!response.ok) {
    throw new Error(
        `Failed to update ${doc.path}: ${response.status} ${await response.text()}`,
    );
  }
}

function documentPathFromName(name) {
  return String(name).split("/documents/").pop();
}

function normalizeOptions(rawOptions) {
  if (!isPlainObject(rawOptions)) return [];
  return Object.entries(rawOptions)
      .sort((left, right) => String(left[0]).localeCompare(String(right[0])))
      .map(([id, raw]) => ({
        id: String(id).trim().toUpperCase(),
        raw,
        text: isPlainObject(raw) ?
          text(raw.option_text || raw.text || raw.en || raw.value) :
          text(raw),
      }))
      .filter((option) => option.id && option.text);
}

function correctAnswer(data) {
  const direct =
    data.answer_key ||
    data.correct_answer ||
    data.correct_option ||
    data.correct_option_key;
  return text(direct).toUpperCase();
}

function hasUsableChinese(value, english) {
  const zh = localizedZh(value);
  return Boolean(zh && hasCjk(zh) && zh !== text(english));
}

function localizedMap(value) {
  if (!isPlainObject(value)) return {};
  const map = {};
  const en = text(value.en || value.english);
  const zh = text(value.zh || value.chinese);
  if (en) map.en = en;
  if (zh) map.zh = zh;
  return map;
}

function localizedZh(value) {
  if (!isPlainObject(value)) return "";
  return text(value.zh || value.chinese);
}

function normalizeLookup(value) {
  return text(value).replace(/\s+/g, " ").toLowerCase();
}

function text(value) {
  return value == null ? "" : String(value).trim();
}

function hasCjk(value) {
  return /[\u3400-\u9fff]/.test(String(value || ""));
}

function isPlainObject(value) {
  return !!value && typeof value === "object" && !Array.isArray(value);
}

function addCounts(target, source) {
  for (const [key, value] of Object.entries(source)) {
    if (Array.isArray(value)) {
      target[key].push(...value);
    } else if (typeof value === "number") {
      target[key] += value;
    }
  }
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
