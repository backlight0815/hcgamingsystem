"use strict";

const {initializeApp} = require("firebase-admin/app");
const {FieldValue, getFirestore} = require("firebase-admin/firestore");

const DEFAULT_PROJECT = process.env.GCLOUD_PROJECT || "hcgaming-31d5d";
const CURRENT_TEST_VERSION = "personality-16-v1";

function parseArgs(argv) {
  const projectId = valueOf(argv, "--project") || DEFAULT_PROJECT;
  const limit = Number(valueOf(argv, "--limit") || 500);
  if (!Number.isInteger(limit) || limit < 1 || limit > 5000) {
    throw new Error("--limit must be an integer from 1 to 5000.");
  }
  const apply = argv.includes("--apply");
  if (apply && valueOf(argv, "--confirm-project") !== projectId) {
    throw new Error(`Apply requires --confirm-project=${projectId}.`);
  }
  return {apply, projectId, limit};
}

function valueOf(argv, key) {
  const prefix = `${key}=`;
  const inline = argv.find((item) => item.startsWith(prefix));
  if (inline) return inline.slice(prefix.length).trim();
  const index = argv.indexOf(key);
  return index >= 0 ? String(argv[index + 1] || "").trim() : "";
}

function planMigration(data = {}) {
  if (!data.test_version) {
    return {
      schema_version: 1,
      test_version: "legacy-personality-result",
      question_version: "legacy-personality-result",
      scoring_version: "legacy-unknown",
      guidance_version: "legacy-personality-guidance",
      legacy_preserved: true,
    };
  }
  if (Number(data.schema_version || 0) > 0) return null;
  return {
    schema_version: data.test_version === CURRENT_TEST_VERSION ? 2 : 1,
    legacy_preserved: data.test_version !== CURRENT_TEST_VERSION,
  };
}

async function run(options) {
  initializeApp({projectId: options.projectId});
  const db = getFirestore();
  const snapshot = await db.collectionGroup("personality_assessments")
      .limit(options.limit).get();
  const planned = snapshot.docs
      .map((document) => ({document, update: planMigration(document.data())}))
      .filter((entry) => entry.update);
  const report = {
    mode: options.apply ? "apply" : "dry-run",
    projectId: options.projectId,
    scanned: snapshot.size,
    affected: planned.length,
    paths: planned.map((entry) => entry.document.ref.path),
  };
  if (options.apply) {
    for (let offset = 0; offset < planned.length; offset += 400) {
      const batch = db.batch();
      for (const entry of planned.slice(offset, offset + 400)) {
        batch.set(entry.document.ref, {
          ...entry.update,
          migration_version: "personality-assessment-v1",
          migrated_at: FieldValue.serverTimestamp(),
        }, {merge: true});
      }
      await batch.commit();
    }
  }
  console.log(JSON.stringify(report, null, 2));
  return report;
}

if (require.main === module) {
  run(parseArgs(process.argv.slice(2))).catch((error) => {
    console.error(error);
    process.exitCode = 1;
  });
}

module.exports = {CURRENT_TEST_VERSION, parseArgs, planMigration, run};
