"use strict";

const assert = require("node:assert/strict");
const {readFileSync} = require("node:fs");
const {resolve} = require("node:path");
const coursework = require("../coursework_functions")._test;

const publicSeed = JSON.parse(readFileSync(
    resolve(__dirname, "../../firebase/seed/coursework/themes/CW-001.json"), "utf8"));
const privateSeed = JSON.parse(readFileSync(
    resolve(__dirname, "../../firebase/seed/coursework/answer_keys/CW-001.keys.json"), "utf8"));
const theme = coursework.normalizedTheme(publicSeed.theme, "CW-001");
const questions = publicSeed.questions.map((question) => {
  const normalized = coursework.normalizedPublicQuestion(question, question.id, "CW-001");
  const key = privateSeed.questions.find((item) => item.questionId === question.id);
  return coursework.normalizedAnswerKey(normalized, key);
});

assert.deepEqual(coursework.validateTheme(theme, questions), []);
assert.equal(coursework.publicQuestion(questions[0]).answer_options[0].is_correct, undefined);
assert.equal(coursework.publicQuestion(questions[0]).sample_answer, undefined);

const theory = questions[0];
const correctOption = theory.answer_options.find((item) => item.is_correct).id;
const wrongOption = theory.answer_options.find((item) => !item.is_correct).id;
assert.equal(coursework.automaticMark(theory, {selected_option_ids: [correctOption]}), 15);
assert.equal(coursework.automaticMark(theory, {selected_option_ids: [wrongOption]}), 0);

assert.equal(coursework.completeAnswer(questions[3], {
  is_completed: true,
  text_answer: "A sufficiently detailed independent market analysis should compare both directional scenarios, explain the relevant structure and key levels, define invalidation, and conclude whether the available evidence justifies taking risk at this time.".repeat(2),
  attachments: [],
}), true);

assert.equal(coursework.completeAnswer(questions[4], {
  is_completed: true,
  text_answer: "The attached chart demonstrates the required skill, with a clear explanation of the annotations, validation conditions, risk controls, and the reason the setup would be rejected if invalidated.",
  attachments: [{storage_path: "protected"}],
}), true);

assert.equal(coursework.validateOrderManagement({
  order_management_data: {
    direction: "buy",
    entry_price: 100,
    stop_loss_price: 95,
    take_profit_price: 110,
  },
}), true);
assert.equal(coursework.validateOrderManagement({
  order_management_data: {
    direction: "buy",
    entry_price: 100,
    stop_loss_price: 105,
    take_profit_price: 110,
  },
}), false);

assert.throws(() => coursework.currentSettings({
  coursework_weight: .8,
  final_exam_weight: .3,
}));
assert.equal(coursework.gradeFor(77, {A: 80, B: 70, C: 60, F: 0}), "B");
assert.equal(coursework.round2(56.8645 + 21.7035), 78.57);
assert.equal(coursework.normalizedDifficulty("初级"), "beginner");
assert.equal(coursework.normalizedDifficulty("ADVANCED"), "advanced");
assert.equal(coursework.normalizedPublicationStatus("已发布"), "published");
assert.equal(coursework.normalizedPublicationStatus(true), "published");

console.log("Coursework function tests passed.");
process.exit(0);
