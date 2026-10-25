#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");

const defaultInput = path.resolve(
    __dirname, "..", "assets", "seed", "coursework_themes_100.json");
const expected = {
  "Support and Resistance": 12,
  "Market Structure": 12,
  "Trend and Range Conditions": 10,
  "Candlestick and Price Action": 10,
  "Risk Management": 12,
  "Order Management": 12,
  "Stop Loss and Take Profit": 10,
  "Trade Planning": 8,
  "Trading Psychology and Discipline": 8,
  "Multi-Timeframe Analysis": 6,
};

function validateCourseworkBank(themes) {
  const issues = [];
  const counts = {themes: 0, questions: 0, categories: {}, bilingual: 0, charts: 0};
  const ids = new Set();
  const english = new Set();
  const chinese = new Set();
  if (!Array.isArray(themes)) return {passed: false, counts, issues: ["Root must be an array."]};
  counts.themes = themes.length;
  if (themes.length !== 100) issues.push(`Expected 100 themes, found ${themes.length}.`);
  for (const theme of themes) {
    const label = theme.theme_code || "unknown theme";
    if (ids.has(theme.theme_id)) issues.push(`${label}: duplicate theme id.`);
    ids.add(theme.theme_id);
    counts.categories[theme.category] = (counts.categories[theme.category] || 0) + 1;
    const questions = theme.questions || [];
    counts.questions += questions.length;
    if (questions.length !== 5) issues.push(`${label}: expected five questions.`);
    const typeCounts = questions.reduce((value, question) => {
      const type = ["singleChoice", "multipleChoice", "trueFalse", "shortAnswer"].includes(question.question_type) ?
        "theory" : question.question_type;
      value[type] = (value[type] || 0) + 1;
      return value;
    }, {});
    if (typeCounts.theory !== 2 || typeCounts.chartZoneSelection !== 1 ||
        typeCounts.marketAnalysis !== 1 || typeCounts.orderManagementDemonstration !== 1) {
      issues.push(`${label}: invalid five-question type structure.`);
    }
    const marks = questions.reduce((sum, question) => sum + Number(question.marks || 0), 0);
    if (marks !== 100 || Number(theme.total_marks) !== 100) issues.push(`${label}: marks do not total 100.`);
    if (!theme.title_i18n?.en || !theme.title_i18n?.zh ||
        !theme.instructions_i18n?.en || !theme.instructions_i18n?.zh) {
      issues.push(`${label}: theme localization is incomplete.`);
    } else {
      counts.bilingual++;
    }
    for (const question of questions) {
      if (ids.has(question.question_id)) issues.push(`${label}: duplicate question id ${question.question_id}.`);
      ids.add(question.question_id);
      const en = String(question.question_text_i18n?.en || "").trim();
      const zh = String(question.question_text_i18n?.zh || "").trim();
      if (!en || !zh) issues.push(`${question.question_id}: both translations are required.`);
      if (english.has(en) || chinese.has(zh)) issues.push(`${question.question_id}: question text is not unique.`);
      english.add(en);
      chinese.add(zh);
      if (question.question_type === "chartZoneSelection") {
        if (!question.seed_meta && !theme.seed_meta?.chart_source_url) issues.push(`${question.question_id}: chart source is missing.`);
        if (!Array.isArray(question.chart_candles) || question.chart_candles.length < 8) issues.push(`${question.question_id}: OHLC data is incomplete.`);
        if (!question.symbol || !question.timeframe || !question.market_date) issues.push(`${question.question_id}: chart metadata is incomplete.`);
        if (!(question.answer_zones || []).some((zone) => zone.is_correct === true)) issues.push(`${question.question_id}: correct zone is missing.`);
        counts.charts++;
      }
      if (["marketAnalysis", "orderManagementDemonstration"].includes(question.question_type)) {
        const rubric = question.grading_rubric || [];
        const total = rubric.reduce((sum, item) => sum + Number(item.maximum_mark || 0), 0);
        if (rubric.length !== 5 || total !== 30) issues.push(`${question.question_id}: rubric must contain five criteria totalling 30.`);
        if (question.requires_image !== true) issues.push(`${question.question_id}: evidence image must be required.`);
      }
    }
  }
  if (counts.questions !== 500) issues.push(`Expected 500 questions, found ${counts.questions}.`);
  for (const [category, count] of Object.entries(expected)) {
    if (counts.categories[category] !== count) issues.push(`${category}: expected ${count}, found ${counts.categories[category] || 0}.`);
  }
  return {passed: issues.length === 0, counts, issues};
}

if (require.main === module) {
  const input = path.resolve(process.argv[2] || defaultInput);
  const result = validateCourseworkBank(JSON.parse(fs.readFileSync(input, "utf8")));
  console.log(JSON.stringify(result, null, 2));
  if (!result.passed) process.exitCode = 1;
}

module.exports = {validateCourseworkBank};
