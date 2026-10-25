#!/usr/bin/env node
"use strict";

const fs = require("node:fs");
const path = require("node:path");
const {categories} = require("./curriculum");
const {SEED_VERSION, contentHash} = require("./generate_coursework_seed");

const root = __dirname;
const marks = [15, 15, 20, 25, 25];
const expectedTypes = [
  ["theory_single_choice"],
  ["theory_single_choice", "theory_multiple_choice", "theory_short_answer", "scenario_based_theory"],
  ["chart_interpretation"],
  ["self_analysis_kbat"],
  ["practical_demonstration"],
];
const allowedDifficulty = new Set(["beginner", "intermediate", "advanced"]);
const forbidden = /lorem ipsum|sample question|question coming soon|theme\s+\d+|fake[-_ ]?url|example\.com\/fake/i;

function readJson(file) {
  return JSON.parse(fs.readFileSync(file, "utf8"));
}

function loadSeed({from = "CW-001", to = "CW-100"} = {}) {
  const start = themeNumber(from);
  const end = themeNumber(to);
  if (start < 1 || end > 100 || start > end) throw new Error("Theme range must be within CW-001..CW-100.");
  const values = [];
  for (let number = start; number <= end; number++) {
    const id = `CW-${String(number).padStart(3, "0")}`;
    const publicData = readJson(path.join(root, "themes", `${id}.json`));
    const privateData = readJson(path.join(root, "answer_keys", `${id}.keys.json`));
    values.push({
      theme: publicData.theme,
      questions: publicData.questions,
      themeKey: privateData.themeKey,
      privateQuestions: privateData.questions,
    });
  }
  return values;
}

function validateCourseworkSeed(values, {requireFullSet = true} = {}) {
  const issues = [];
  const counts = {
    themes: values.length,
    publicQuestions: 0,
    privateThemes: 0,
    privateQuestions: 0,
    categories: {},
    difficulties: {beginner: 0, intermediate: 0, advanced: 0},
  };
  const themeIds = new Set();
  const codes = new Set();
  const sortOrders = new Set();
  const titles = new Set();
  const prompts = new Set();
  const validCategoryIds = new Set(categories.map((item) => item.id));

  if (requireFullSet && values.length !== 100) issue(issues, "Expected exactly 100 themes.");
  values.forEach((value, index) => {
    const theme = value.theme || {};
    const expectedId = `CW-${String((requireFullSet ? index : themeNumber(theme.id)) + 1).padStart(3, "0")}`;
    const label = theme.id || `theme at index ${index}`;
    if (requireFullSet && theme.id !== expectedId) issue(issues, `${label}: expected deterministic ID ${expectedId}.`);
    if (!/^CW-(00[1-9]|0[1-9]\d|100)$/.test(theme.id || "")) issue(issues, `${label}: invalid theme ID.`);
    duplicate(themeIds, theme.id, issues, "theme ID");
    duplicate(codes, theme.code, issues, "theme code");
    duplicate(sortOrders, theme.sortOrder, issues, "sort order");
    duplicate(titles, text(theme.title?.en).toLowerCase(), issues, "English theme title");
    bilingual(theme.title, `${label} title`, issues);
    bilingual(theme.shortTitle, `${label} shortTitle`, issues);
    bilingual(theme.description, `${label} description`, issues);
    bilingual(theme.categoryName, `${label} categoryName`, issues);
    bilingualLists(theme.learningObjectives, `${label} learningObjectives`, issues);
    if (!validCategoryIds.has(theme.categoryId)) issue(issues, `${label}: unknown categoryId.`);
    counts.categories[theme.categoryId] = (counts.categories[theme.categoryId] || 0) + 1;
    if (!allowedDifficulty.has(theme.difficulty)) issue(issues, `${label}: invalid difficulty.`);
    else counts.difficulties[theme.difficulty]++;
    const expectedDifficulty = themeNumber(theme.id) <= 30 ? "beginner" : themeNumber(theme.id) <= 75 ? "intermediate" : "advanced";
    if (theme.difficulty !== expectedDifficulty) issue(issues, `${label}: expected ${expectedDifficulty} difficulty.`);
    if (!Array.isArray(theme.searchTokens) || theme.searchTokens.length < 8) issue(issues, `${label}: searchTokens are incomplete.`);
    if (!Array.isArray(theme.keywords) || theme.keywords.length < 3) issue(issues, `${label}: keywords are incomplete.`);
    if (theme.questionCount !== 5 || theme.totalMarks !== 100 || theme.courseworkWeight !== .7) issue(issues, `${label}: theme totals/weight are invalid.`);
    if (theme.status !== "published" || theme.isActive !== true || theme.isAssignable !== true) issue(issues, `${label}: theme must be published, active and assignable.`);
    seedMetadata(theme, `${label} theme`, issues);
    hashMatches(theme, `${label} theme`, issues);
    scanText(theme, `${label} theme`, issues);

    const questions = value.questions || [];
    const privateQuestions = value.privateQuestions || [];
    const privateTheme = value.themeKey || {};
    counts.publicQuestions += questions.length;
    counts.privateQuestions += privateQuestions.length;
    if (Object.keys(privateTheme).length) counts.privateThemes++;
    if (questions.length !== 5) issue(issues, `${label}: expected five public questions.`);
    if (privateQuestions.length !== 5) issue(issues, `${label}: expected five private question keys.`);
    if (privateTheme.themeId !== theme.id || privateTheme.questionCount !== 5) issue(issues, `${label}: invalid private theme key.`);
    seedMetadata(privateTheme, `${label} private theme`, issues);
    hashMatches(privateTheme, `${label} private theme`, issues);

    questions.forEach((question, questionIndex) => {
      const qLabel = `${label}/${question.id || "unknown"}`;
      const expectedIdForOrder = `Q${questionIndex + 1}`;
      if (question.id !== expectedIdForOrder || question.order !== questionIndex + 1) issue(issues, `${qLabel}: expected ${expectedIdForOrder} and matching order.`);
      if (question.themeId !== theme.id) issue(issues, `${qLabel}: themeId mismatch.`);
      if (!expectedTypes[questionIndex].includes(question.type)) issue(issues, `${qLabel}: invalid question type/order.`);
      if (question.marks !== marks[questionIndex]) issue(issues, `${qLabel}: expected ${marks[questionIndex]} marks.`);
      bilingual(question.title, `${qLabel} title`, issues);
      bilingual(question.prompt, `${qLabel} prompt`, issues);
      bilingual(question.instructions, `${qLabel} instructions`, issues);
      duplicate(prompts, text(question.prompt?.en).toLowerCase(), issues, "English question prompt");
      seedMetadata(question, qLabel, issues);
      hashMatches(question, qLabel, issues);
      scanText(question, qLabel, issues);
      const key = privateQuestions.find((item) => item.questionId === question.id);
      if (!key) {
        issue(issues, `${qLabel}: private answer key is missing.`);
        return;
      }
      validateKey(key, question, qLabel, issues);
      if (question.type.includes("choice")) validateChoices(question, key, qLabel, issues);
      if (question.type === "chart_interpretation") validateChart(question, key, qLabel, issues);
      if (question.type === "self_analysis_kbat" && (question.requiresText !== true || question.minimumCharacters < 150 || question.maximumCharacters > 2000)) {
        issue(issues, `${qLabel}: KBAT text limits are invalid.`);
      }
      if (question.type === "practical_demonstration") {
        if (question.requiresText !== true || question.requiresImage !== true || question.minimumCharacters < 80 || question.maximumCharacters > 1500 || question.minimumImages < 1 || question.maximumImages > 3) {
          issue(issues, `${qLabel}: practical evidence requirements are invalid.`);
        }
        const allowed = question.allowedFileTypes || [];
        if (!["image/jpeg", "image/png", "image/webp"].every((item) => allowed.includes(item))) issue(issues, `${qLabel}: allowed file types are incomplete.`);
      }
    });
    const total = questions.reduce((sum, item) => sum + Number(item.marks || 0), 0);
    if (total !== 100) issue(issues, `${label}: public question marks total ${total}.`);
  });

  if (requireFullSet) {
    if (counts.publicQuestions !== 500) issue(issues, `Expected 500 public questions, found ${counts.publicQuestions}.`);
    if (counts.privateThemes !== 100) issue(issues, `Expected 100 private theme keys, found ${counts.privateThemes}.`);
    if (counts.privateQuestions !== 500) issue(issues, `Expected 500 private question keys, found ${counts.privateQuestions}.`);
    if (Object.keys(counts.categories).length !== 10) issue(issues, "Expected exactly ten categories.");
    for (const category of categories) if (counts.categories[category.id] !== 10) issue(issues, `${category.id}: expected ten themes.`);
    if (counts.difficulties.beginner !== 30 || counts.difficulties.intermediate !== 45 || counts.difficulties.advanced !== 25) issue(issues, "Difficulty distribution must be 30 beginner, 45 intermediate and 25 advanced.");
  }
  return {passed: issues.length === 0, counts, issues};
}

function validateChoices(question, key, label, issues) {
  const options = question.options || [];
  const ids = new Set(options.map((item) => item.id));
  if (options.length !== 4 || ids.size !== 4) issue(issues, `${label}: choice question requires four unique options.`);
  options.forEach((option) => bilingual(option.text, `${label} option ${option.id}`, issues));
  const correct = key.correctOptionIds || [];
  if (question.type === "theory_single_choice" && correct.length !== 1) issue(issues, `${label}: single choice needs exactly one correct option.`);
  if (!correct.length || correct.some((id) => !ids.has(id))) issue(issues, `${label}: correct option does not exist in public options.`);
}

function validateChart(question, key, label, issues) {
  const chart = question.chartData || {};
  const candles = chart.candles || [];
  const zones = chart.zones || [];
  if (chart.sourceType !== "seeded_ohlc" || chart.isSyntheticEducationalData !== true) issue(issues, `${label}: chart source disclosure is invalid.`);
  if (!chart.symbol || !chart.timeframe || candles.length < 30 || candles.length > 60) issue(issues, `${label}: chart metadata/candle count is invalid.`);
  candles.forEach((candle, index) => {
    const values = [candle.open, candle.high, candle.low, candle.close];
    if (!values.every(Number.isFinite) || candle.high < Math.max(candle.open, candle.close) || candle.low > Math.min(candle.open, candle.close) || candle.high < candle.low) issue(issues, `${label}: invalid candle ${index}.`);
  });
  const zoneIds = new Set(zones.map((item) => item.id));
  if (zones.length < 2 || zones.some((zone) => !Number.isFinite(zone.minimumPrice) || !Number.isFinite(zone.maximumPrice) || zone.minimumPrice >= zone.maximumPrice)) issue(issues, `${label}: chart zones are invalid.`);
  const correctZones = key.correctZoneIds || [];
  if (correctZones.length !== 1 || correctZones.some((id) => !zoneIds.has(id))) issue(issues, `${label}: private correct chart zone is invalid.`);
}

function validateKey(key, question, label, issues) {
  if (key.themeId !== question.themeId || key.questionId !== question.id || key.maximumMarks !== question.marks) issue(issues, `${label}: private key identity/marks mismatch.`);
  bilingual(key.answerExplanation, `${label} answerExplanation`, issues);
  bilingualNullable(key.modelAnswer, `${label} modelAnswer`, issues);
  bilingualLists(key.commonMistakes, `${label} commonMistakes`, issues);
  const rubric = key.rubric || [];
  if (!rubric.length) issue(issues, `${label}: rubric is empty.`);
  const rubricTotal = rubric.reduce((sum, item) => sum + Number(item.maximumMarks || 0), 0);
  if (rubricTotal !== question.marks) issue(issues, `${label}: rubric totals ${rubricTotal}, expected ${question.marks}.`);
  rubric.forEach((item) => bilingual(item.criterion, `${label} rubric ${item.id}`, issues));
  seedMetadata(key, `${label} private key`, issues);
  hashMatches(key, `${label} private key`, issues);
  scanText(key, `${label} private key`, issues);
}

function seedMetadata(value, label, issues) {
  if (value.seedVersion !== SEED_VERSION || value.managedBySeed !== true || value.version !== 1) issue(issues, `${label}: seed metadata is invalid.`);
  if (!/^[a-f0-9]{64}$/.test(value.contentHash || "")) issue(issues, `${label}: contentHash is invalid.`);
}

function hashMatches(value, label, issues) {
  if (value.contentHash !== contentHash(value)) issue(issues, `${label}: contentHash does not match content.`);
}

function bilingual(value, label, issues) {
  if (!text(value?.en) || !text(value?.zh)) issue(issues, `${label}: English and Chinese values are required.`);
}

function bilingualNullable(value, label, issues) {
  if (!value || !("en" in value) || !("zh" in value)) issue(issues, `${label}: English and Chinese keys are required.`);
}

function bilingualLists(value, label, issues) {
  if (!Array.isArray(value?.en) || !value.en.length || !Array.isArray(value?.zh) || value.zh.length !== value.en.length || value.zh.some((item) => !text(item))) issue(issues, `${label}: equivalent non-empty English and Chinese lists are required.`);
}

function scanText(value, label, issues) {
  const serialized = JSON.stringify(value);
  if (forbidden.test(serialized)) issue(issues, `${label}: placeholder or fake URL text detected.`);
  if (/https?:\/\//i.test(serialized)) issue(issues, `${label}: URL detected; chart seed must use structured data.`);
}

function duplicate(set, value, issues, label) {
  if (set.has(value)) issue(issues, `Duplicate ${label}: ${value}.`);
  set.add(value);
}

function text(value) {
  return value == null ? "" : String(value).trim();
}

function themeNumber(value) {
  const match = /^CW-(\d{3})$/.exec(String(value || "").toUpperCase());
  return match ? Number(match[1]) : NaN;
}

function issue(issues, message) {
  if (issues.length < 500) issues.push(message);
}

if (require.main === module) {
  try {
    const values = loadSeed();
    const result = validateCourseworkSeed(values);
    console.log(JSON.stringify(result, null, 2));
    if (!result.passed) process.exitCode = 1;
  } catch (error) {
    console.error(error.stack || error);
    process.exitCode = 1;
  }
}

module.exports = {loadSeed, themeNumber, validateCourseworkSeed};
