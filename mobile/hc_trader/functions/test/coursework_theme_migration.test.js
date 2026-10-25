"use strict";

const assert = require("node:assert/strict");
const migration = require("../scripts/migrate_coursework_theme_references")._test;

assert.equal(
    migration.canonicalCategory("", "Trading Market Foundations"),
    "market_foundations");
assert.equal(migration.canonicalCategory("市场结构", ""), "market_structure");
assert.equal(migration.canonicalDifficulty("Beginner"), "beginner");
assert.equal(migration.canonicalDifficulty("初级"), "beginner");
assert.equal(migration.canonicalStatus("已发布"), "published");
assert.equal(migration.canonicalStatus(true), "published");
assert.equal(migration.documentId("coursework_themes/theme-a"), "theme-a");

const lookup = new Map();
migration.addLookup(lookup, "CW-001", "theme-a");
assert.equal(migration.unique(lookup, "cw-001"), "theme-a");
migration.addLookup(lookup, "CW-001", "theme-b");
assert.equal(migration.unique(lookup, "CW-001"), "");

console.log("Coursework theme migration tests passed.");
process.exit(0);
