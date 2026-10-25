#!/usr/bin/env node
"use strict";

const admin = require("firebase-admin");
const {loadSeed, validateCourseworkSeed} = require("./validate_coursework_seed");
const {SEED_VERSION} = require("./generate_coursework_seed");

const serverTimestamp = admin.firestore.FieldValue.serverTimestamp;

async function main() {
  const options = parseArgs(process.argv.slice(2));
  const values = loadSeed({from: options.from, to: options.to});
  const validation = validateCourseworkSeed(values, {
    requireFullSet: options.from === "CW-001" && options.to === "CW-100",
  });
  if (!validation.passed) {
    throw new Error(`Seed validation failed before any write:\n${validation.issues.join("\n")}`);
  }
  if (options.validateOnly) {
    console.log(JSON.stringify({mode: "validation-only", ...validation}, null, 2));
    return;
  }
  if (!options.projectId) throw new Error("An explicit --project=<project-id> is required.");
  enforceWriteSafety(options);
  if (!options.write) {
    console.log(JSON.stringify({
      mode: "dry-run",
      projectId: options.projectId,
      range: `${options.from}..${options.to}`,
      force: options.force,
      validation: validation.counts,
      plannedDocuments: records(values).length + 1,
      note: "No Firebase connection or write was performed.",
    }, null, 2));
    return;
  }

  initialiseAdmin(options);
  const db = admin.firestore();
  const allRecords = records(values);
  const existing = await loadExisting(db, allRecords);
  const summary = emptySummary();
  const plans = [];
  allRecords.forEach((record, index) => {
    const snapshot = existing[index];
    const action = chooseAction(snapshot, record.data, options.force);
    summary[record.kind][action]++;
    if (["created", "updated"].includes(action)) plans.push({...record, action});
  });

  const writer = db.bulkWriter();
  writer.onWriteError((error) => {
    if (error.failedAttempts < 4) return true;
    console.error(`Permanent write failure: ${error.documentRef.path}: ${error.message}`);
    return false;
  });
  let completed = 0;
  const writes = plans.map((plan) => {
    const reference = db.doc(plan.path);
    const payload = {
      ...plan.data,
      updatedAt: serverTimestamp(),
      updatedBy: "system_seed",
      ...(plan.action === "created" ? {createdAt: serverTimestamp(), createdBy: "system_seed"} : {}),
    };
    return writer.set(reference, payload, {merge: true}).then(() => {
      completed++;
      if (completed % 100 === 0 || completed === plans.length) {
        console.log(`Coursework seed progress: ${completed}/${plans.length} writes completed.`);
      }
    }).catch((error) => {
      summary[plan.kind][plan.action]--;
      summary[plan.kind].failed++;
      summary.failures.push({path: plan.path, message: error.message});
    });
  });
  await Promise.all(writes);
  await writer.close();
  await db.collection("assessment_settings").doc("current").set({
    coursework_weight: .70,
    final_exam_weight: .30,
    pass_mark: 60,
    grade_boundaries: {A: 80, B: 70, C: 60, D: 50, F: 0},
    calculation_version: 2,
    coursework_seed_version: SEED_VERSION,
    updated_at: serverTimestamp(),
  }, {merge: true});
  console.log(JSON.stringify({
    mode: options.emulator ? "emulator-write" : "production-write",
    projectId: options.projectId,
    range: `${options.from}..${options.to}`,
    summary,
  }, null, 2));
  if (summary.failures.length) process.exitCode = 1;
}

function records(values) {
  return values.flatMap((value) => {
    const id = value.theme.id;
    return [
      {kind: "themes", path: `coursework_themes/${id}`, data: value.theme},
      ...value.questions.map((data) => ({kind: "publicQuestions", path: `coursework_themes/${id}/questions/${data.id}`, data})),
      {kind: "privateThemes", path: `coursework_theme_keys/${id}`, data: value.themeKey},
      ...value.privateQuestions.map((data) => ({kind: "privateQuestions", path: `coursework_theme_keys/${id}/questions/${data.questionId}`, data})),
    ];
  });
}

async function loadExisting(db, allRecords) {
  const snapshots = [];
  for (let offset = 0; offset < allRecords.length; offset += 300) {
    const chunk = allRecords.slice(offset, offset + 300).map((item) => db.doc(item.path));
    snapshots.push(...await db.getAll(...chunk));
  }
  return snapshots;
}

function chooseAction(snapshot, source, force) {
  if (!snapshot.exists) return "created";
  const current = snapshot.data() || {};
  if (current.contentHash === source.contentHash) return "skipped";
  if (!force && (
    current.managedBySeed !== true ||
    (current.updatedBy && current.updatedBy !== "system_seed")
  )) return "protected";
  return "updated";
}

function emptySummary() {
  const row = () => ({created: 0, updated: 0, skipped: 0, protected: 0, failed: 0});
  return {
    themes: row(),
    publicQuestions: row(),
    privateThemes: row(),
    privateQuestions: row(),
    failures: [],
  };
}

function parseArgs(args) {
  const option = (name, fallback) => {
    const inline = args.find((value) => value.startsWith(`${name}=`));
    if (inline) return inline.slice(name.length + 1);
    const index = args.indexOf(name);
    return index >= 0 ? args[index + 1] : fallback;
  };
  return {
    projectId: option("--project", process.env.GCLOUD_PROJECT || process.env.GOOGLE_CLOUD_PROJECT),
    from: String(option("--from", "CW-001")).toUpperCase(),
    to: String(option("--to", "CW-100")).toUpperCase(),
    validateOnly: args.includes("--validate-only"),
    write: args.includes("--write"),
    force: args.includes("--force"),
    emulator: args.includes("--emulator"),
    production: args.includes("--production"),
    confirmProduction: args.includes("--confirm-production"),
  };
}

function enforceWriteSafety(options) {
  if (!options.write) return;
  if (options.emulator === options.production) {
    throw new Error("A write requires exactly one of --emulator or --production.");
  }
  if (options.emulator && !process.env.FIRESTORE_EMULATOR_HOST) {
    throw new Error("--emulator requires FIRESTORE_EMULATOR_HOST; refusing to contact production.");
  }
  if (options.production && !options.confirmProduction) {
    throw new Error("Production write blocked. Add --confirm-production after reviewing project and validation output.");
  }
  if (options.production && process.env.FIRESTORE_EMULATOR_HOST) {
    throw new Error("Production mode cannot run while FIRESTORE_EMULATOR_HOST is set.");
  }
}

function initialiseAdmin(options) {
  if (admin.apps.length) return;
  admin.initializeApp(options.emulator ? {projectId: options.projectId} : {
    projectId: options.projectId,
    credential: admin.credential.applicationDefault(),
  });
}

if (require.main === module) {
  main().catch((error) => {
    console.error(error.stack || error);
    process.exitCode = 1;
  });
}

module.exports = {chooseAction, emptySummary, enforceWriteSafety, parseArgs, records};
