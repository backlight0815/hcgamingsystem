#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const admin = require("firebase-admin");
const {
  listCollectionDocuments,
  resolveFirebaseAuth,
  runCollectionGroupQuery,
} = require("./firebase_rest_helpers");

const projectRoot = path.resolve(__dirname, "..");
const topLevelCollections = [
  "finalExamQuestions",
  "finalExamAttempts",
  "finalExamResults",
  "examAttempts",
  "examResults",
  "userExamResults",
  "userAnswers",
  "userProgress",
  "final_exam_questions",
  "final_exam_results",
];
const collectionGroups = [
  "final_exam_attempts",
  "final_exam_results",
  "finalExamAttempts",
  "finalExamResults",
  "examAttempts",
  "examResults",
  "userExamResults",
  "userAnswers",
  "userProgress",
];

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const projectId =
    args.project ||
    process.env.GCLOUD_PROJECT ||
    process.env.GOOGLE_CLOUD_PROJECT ||
    "hcgaming-31d5d";
  const outputPath = path.resolve(
      projectRoot,
      args.output || "build/reports/final_exam_protection_audit.json",
  );
  fs.mkdirSync(path.dirname(outputPath), {recursive: true});

  const firestore = await resolveFirestore(projectId);
  const snapshot = await collectSnapshot(firestore, projectId);
  let comparison = null;
  if (args.compare) {
    const baselinePath = path.resolve(projectRoot, args.compare);
    const baseline = JSON.parse(fs.readFileSync(baselinePath, "utf8"));
    comparison = compareSnapshots(baseline, snapshot);
  }

  const report = {
    generatedAt: new Date().toISOString(),
    projectId,
    authMode: firestore.mode,
    protectedCollectionsReadOnly: true,
    topLevelCollections,
    collectionGroups,
    counts: snapshot,
    comparison,
  };
  fs.writeFileSync(outputPath, `${JSON.stringify(report, null, 2)}\n`, "utf8");

  if (args.save) {
    const savePath = path.resolve(projectRoot, args.save);
    fs.mkdirSync(path.dirname(savePath), {recursive: true});
    fs.writeFileSync(savePath, `${JSON.stringify(snapshot, null, 2)}\n`, "utf8");
    console.log(`Baseline snapshot written to ${savePath}`);
  }

  console.log(`Final exam protection audit written to ${outputPath}`);
  console.log(JSON.stringify(snapshot, null, 2));
  if (comparison) {
    console.log(`Comparison passed: ${comparison.passed}`);
    if (!comparison.passed) {
      for (const diff of comparison.differences) {
        console.error(`${diff.key}: before=${diff.before}, after=${diff.after}`);
      }
      if (args["fail-on-diff"] === true) {
        process.exitCode = 1;
      }
    }
  }
}

async function resolveFirestore(projectId) {
  try {
    admin.initializeApp({
      credential: admin.credential.applicationDefault(),
      projectId,
    });
    const db = admin.firestore();
    await db.collection("final_exam_questions").limit(1).get();
    return {mode: "firebase-admin", db};
  } catch (error) {
    if (!/credential|auth|Could not load the default credentials/i.test(String(error?.message || error))) {
      throw error;
    }
    const auth = await resolveFirebaseAuth(projectId);
    return {mode: "firebase-cli-rest", auth};
  }
}

async function collectSnapshot(firestore, projectId) {
  const counts = {topLevel: {}, collectionGroups: {}};
  if (firestore.db) {
    for (const collection of topLevelCollections) {
      const snapshot = await firestore.db.collection(collection).get();
      counts.topLevel[collection] = snapshot.docs.length;
    }
    for (const collectionId of collectionGroups) {
      const snapshot = await firestore.db.collectionGroup(collectionId).get();
      counts.collectionGroups[collectionId] = snapshot.docs.length;
    }
    return counts;
  }

  for (const collection of topLevelCollections) {
    const docs = await listCollectionDocuments({
      projectId,
      auth: firestore.auth,
      collection,
    });
    counts.topLevel[collection] = docs.length;
  }
  for (const collectionId of collectionGroups) {
    const docs = await runCollectionGroupQuery({
      projectId,
      auth: firestore.auth,
      collectionId,
    });
    counts.collectionGroups[collectionId] = docs.length;
  }
  return counts;
}

function compareSnapshots(before, after) {
  const differences = [];
  for (const section of ["topLevel", "collectionGroups"]) {
    const keys = new Set([
      ...Object.keys(before[section] || {}),
      ...Object.keys(after[section] || {}),
    ]);
    for (const key of keys) {
      const beforeValue = Number(before[section]?.[key] || 0);
      const afterValue = Number(after[section]?.[key] || 0);
      if (beforeValue !== afterValue) {
        differences.push({
          key: `${section}.${key}`,
          before: beforeValue,
          after: afterValue,
        });
      }
    }
  }
  return {passed: differences.length === 0, differences};
}

function parseArgs(rawArgs) {
  const args = {};
  for (const arg of rawArgs) {
    if (!arg.startsWith("--")) continue;
    const body = arg.slice(2);
    const equals = body.indexOf("=");
    if (equals === -1) {
      args[body] = true;
    } else {
      args[body.slice(0, equals)] = body.slice(equals + 1);
    }
  }
  return args;
}

main().catch((error) => {
  console.error(error.message || error);
  process.exitCode = 1;
});
