"use strict";

const assert = require("node:assert/strict");
const {_test} = require("../daily_quiz_functions");

assert.equal(
    _test.dailyAttemptId(new Date("2026-07-13T16:30:00.000Z"), 480),
    "exam-2026-07-14",
);
assert.equal(
    _test.localDate(new Date("2026-07-13T16:30:00.000Z"), 480).toISOString(),
    "2026-07-14T00:00:00.000Z",
);

const options = _test.normalizedOptions({
  A: {option_text: "Buy", is_correct: true},
  B: {option_text: "Sell", is_correct: false},
});
assert.equal(options.length, 2);
assert.equal(options[0].text, "Buy");
assert.equal(options[0].isCorrect, true);

console.log("Daily quiz function tests passed.");
