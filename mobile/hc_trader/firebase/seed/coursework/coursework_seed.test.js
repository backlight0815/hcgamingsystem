"use strict";

const assert = require("node:assert/strict");
const {loadSeed, validateCourseworkSeed} = require("./validate_coursework_seed");
const {chooseAction, enforceWriteSafety, records} = require("./seed_coursework");

function clone(value) {
  return structuredClone(value);
}

function hasChinese(value) {
  return /[\u3400-\u9fff]/u.test(String(value ?? ""));
}

const seed = loadSeed();
const validation = validateCourseworkSeed(seed);
assert.equal(validation.passed, true, validation.issues.join("\n"));
assert.equal(seed.length, 100);
assert.equal(records(seed).length, 1200);
assert.equal(seed.flatMap((item) => item.questions).length, 500);
assert.equal(seed.flatMap((item) => item.privateQuestions).length, 500);

for (const item of seed) {
  assert.ok(hasChinese(item.theme.title.zh), `${item.theme.id} Chinese title`);
  assert.ok(hasChinese(item.theme.description.zh), `${item.theme.id} Chinese description`);
  assert.ok(item.theme.learningObjectives.zh.every(hasChinese), `${item.theme.id} Chinese objectives`);
  for (const question of item.questions) {
    const label = `${item.theme.id}/${question.id}`;
    assert.ok(hasChinese(question.title.zh), `${label} Chinese title`);
    assert.ok(hasChinese(question.prompt.zh), `${label} Chinese prompt`);
    assert.ok(hasChinese(question.instructions.zh), `${label} Chinese instructions`);
    assert.ok(question.options.every((option) => hasChinese(option.text.zh)), `${label} Chinese options`);
  }
  for (const key of item.privateQuestions) {
    const label = `${item.theme.id}/${key.questionId}`;
    assert.ok(hasChinese(key.answerExplanation.zh), `${label} Chinese explanation`);
    assert.ok(key.commonMistakes.zh.every(hasChinese), `${label} Chinese common mistakes`);
    assert.ok(key.rubric.every((criterion) => hasChinese(criterion.criterion.zh)), `${label} Chinese rubric`);
  }
}

const first = seed[0];
assert.equal(first.theme.id, "CW-001");
assert.ok(first.theme.title.en && first.theme.title.zh);
assert.ok(first.questions.every((question) => question.title.en && question.title.zh));
assert.deepEqual(first.questions.map((question) => question.marks), [15, 15, 20, 25, 25]);
assert.equal(first.questions.reduce((sum, question) => sum + question.marks, 0), 100);

assert.equal(seed.filter((item) => item.theme.categoryId === "market_structure").length, 10);
assert.equal(seed.filter((item) => item.theme.difficulty === "beginner").length, 30);
assert.equal(seed.filter((item) => item.theme.difficulty === "intermediate").length, 45);
assert.equal(seed.filter((item) => item.theme.difficulty === "advanced").length, 25);
assert.equal(seed.filter((item) => item.theme.code.toLowerCase().includes("cw-001")).length, 1);
assert.ok(seed.some((item) => item.theme.searchTokens.includes("xauusd")));
assert.ok(seed.some((item) => item.theme.searchTokens.some((token) => token.includes("心理"))));

const unchangedSnapshot = {
  exists: true,
  data: () => ({contentHash: first.theme.contentHash}),
};
assert.equal(chooseAction(unchangedSnapshot, first.theme, false), "skipped");
assert.equal(chooseAction({exists: false}, first.theme, false), "created");
assert.equal(chooseAction({
  exists: true,
  data: () => ({managedBySeed: true, seedVersion: first.theme.seedVersion, updatedBy: "system_seed"}),
}, first.theme, false), "updated");
assert.equal(chooseAction({
  exists: true,
  data: () => ({managedBySeed: true, seedVersion: "coursework_previous_version", updatedBy: "system_seed"}),
}, first.theme, false), "updated");
assert.equal(chooseAction({
  exists: true,
  data: () => ({managedBySeed: true, seedVersion: first.theme.seedVersion, updatedBy: "admin-user"}),
}, first.theme, false), "protected");
assert.equal(chooseAction({
  exists: true,
  data: () => ({managedBySeed: true, seedVersion: first.theme.seedVersion, updatedBy: "admin-user"}),
}, first.theme, true), "updated");

assert.throws(
    () => enforceWriteSafety({write: true, emulator: false, production: true, confirmProduction: false}),
    /confirm-production/,
);
assert.throws(
    () => enforceWriteSafety({write: true, emulator: false, production: false}),
    /exactly one/,
);

const duplicate = clone(seed);
duplicate[1].theme.id = duplicate[0].theme.id;
const duplicateResult = validateCourseworkSeed(duplicate);
assert.equal(duplicateResult.passed, false);
assert.ok(duplicateResult.issues.some((issue) => issue.includes("Duplicate theme ID")));

const missingTranslation = clone(seed);
missingTranslation[0].questions[0].prompt.zh = "";
const translationResult = validateCourseworkSeed(missingTranslation);
assert.equal(translationResult.passed, false);
assert.ok(translationResult.issues.some((issue) => issue.includes("English and Chinese")));

const invalidCandle = clone(seed);
const candle = invalidCandle[0].questions[2].chartData.candles[0];
candle.high = Math.min(candle.open, candle.close) - 1;
const candleResult = validateCourseworkSeed(invalidCandle);
assert.equal(candleResult.passed, false);
assert.ok(candleResult.issues.some((issue) => issue.includes("invalid candle")));

console.log("Coursework deterministic seed tests passed.");
