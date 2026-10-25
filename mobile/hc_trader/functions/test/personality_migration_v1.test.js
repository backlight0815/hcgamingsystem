"use strict";

const assert = require("node:assert/strict");
const {
  CURRENT_TEST_VERSION,
  parseArgs,
  planMigration,
} = require("../scripts/migrate_personality_assessments_v1");

assert.deepEqual(planMigration({result: {type: "analytical"}}), {
  schema_version: 1,
  test_version: "legacy-personality-result",
  question_version: "legacy-personality-result",
  scoring_version: "legacy-unknown",
  guidance_version: "legacy-personality-guidance",
  legacy_preserved: true,
});
assert.deepEqual(planMigration({test_version: CURRENT_TEST_VERSION}), {
  schema_version: 2,
  legacy_preserved: false,
});
assert.equal(planMigration({
  test_version: CURRENT_TEST_VERSION,
  schema_version: 2,
}), null);
assert.equal(parseArgs([]).apply, false);
assert.throws(
    () => parseArgs(["--apply", "--project=test-project"]),
    /confirm-project/,
);
assert.equal(parseArgs([
  "--apply",
  "--project=test-project",
  "--confirm-project=test-project",
  "--limit=25",
]).limit, 25);

console.log("Personality migration v1 tests passed.");
