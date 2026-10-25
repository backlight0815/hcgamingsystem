#!/usr/bin/env node
"use strict";

const assert = require("node:assert/strict");
const path = require("node:path");
const {spawnSync} = require("node:child_process");

const projectArg = process.argv.find((value) => value.startsWith("--project="));
if (!projectArg) throw new Error("Use --project=<emulator-project-id>.");
if (!process.env.FIRESTORE_EMULATOR_HOST) {
  throw new Error("FIRESTORE_EMULATOR_HOST is required; refusing to contact production.");
}

const packageRoot = path.resolve(__dirname, "../..");
const appRoot = path.resolve(packageRoot, "..");

function run(label, script, args, cwd = packageRoot) {
  console.log(`\n[${label}]`);
  const result = spawnSync(process.execPath, [script, ...args], {
    cwd,
    env: process.env,
    encoding: "utf8",
  });
  if (result.stdout) process.stdout.write(result.stdout);
  if (result.stderr) process.stderr.write(result.stderr);
  if (result.status !== 0) {
    throw new Error(`${label} failed with exit code ${result.status}.`);
  }
  return result.stdout;
}

const seeder = path.join(packageRoot, "seed/coursework/seed_coursework.js");
const commonArgs = ["--emulator", "--write", projectArg];
const first = run("first idempotent seed", seeder, commonArgs);
assert.match(first, /"themes"\s*:\s*\{\s*"created"\s*:\s*100/);
assert.match(first, /"publicQuestions"\s*:\s*\{\s*"created"\s*:\s*500/);
assert.match(first, /"privateQuestions"\s*:\s*\{\s*"created"\s*:\s*500/);

const second = run("second idempotent seed", seeder, commonArgs);
assert.match(second, /"themes"\s*:\s*\{[^}]*"skipped"\s*:\s*100/s);
assert.match(second, /"publicQuestions"\s*:\s*\{[^}]*"skipped"\s*:\s*500/s);
assert.match(second, /"privateQuestions"\s*:\s*\{[^}]*"skipped"\s*:\s*500/s);

run(
    "emulator count and privacy verification",
    path.join(packageRoot, "seed/coursework/verify_emulator_seed.js"),
    [projectArg],
);
const rollback = run(
    "seed-managed rollback",
    path.join(packageRoot, "seed/coursework/rollback_coursework_seed.js"),
    ["--emulator", "--write", projectArg],
);
assert.match(rollback, /"themesDeleted"\s*:\s*100/);
assert.match(rollback, /"documentsDeleted"\s*:\s*1200/);
run(
    "rollback scope verification",
    path.join(packageRoot, "seed/coursework/verify_emulator_seed.js"),
    [projectArg, "--expect-empty"],
);
run(
    "Firestore Rules verification",
    path.join(appRoot, "functions/test/coursework_seed_rules.test.js"),
    [],
    path.join(appRoot, "functions"),
);

console.log("\nCoursework emulator integration passed.");
