"use strict";

const assert = require("node:assert/strict");
const {
  CANDIDATES,
  CONFIRMATION,
  inventory,
  parseArgs,
} = require("../scripts/cleanup_obsolete_metadata_v4");

assert.deepEqual(Object.keys(CANDIDATES), [
  "users",
  "referral_codes",
  "roles",
  "trading_exam_questions",
  "market_analyses",
]);
assert.equal(CONFIRMATION, "REMOVE_OBSOLETE_METADATA");

const report = inventory([
  {path: "users/a", data: {role_id: 750, role_ids: [750], role_names: ["Trader"]}},
  {path: "users/b", data: {role_id: 750, role_ids: [750]}},
], "users");
assert.equal(report.documentsScanned, 2);
assert.equal(report.documentsWithCandidates, 1);
assert.equal(report.candidateCounts.role_names, 1);
assert.equal(report.affected[0].path, "users/a");

const dryRun = parseArgs(["--collection=users", "--limit=25"]);
assert.equal(dryRun.apply, false);
assert.equal(dryRun.limit, 25);
assert.equal(dryRun.collection, "users");

assert.throws(
    () => parseArgs(["--collection=unknown"]),
    /Unsupported --collection/,
);
assert.throws(
    () => parseArgs(["--limit=5001"]),
    /--limit must be/,
);

console.log("Cleanup metadata v4 tests passed.");
