"use strict";

const fs = require("node:fs");
const path = require("node:path");

const {
  commitWrites,
  encodeFields,
  listCollectionDocuments,
  resolveFirebaseAuth,
} = require("../../tools/firebase_rest_helpers");

const DEFAULT_PROJECT = process.env.GCLOUD_PROJECT || "hcgaming-31d5d";
const CONFIRMATION = "REMOVE_OBSOLETE_METADATA";
const CANDIDATES = Object.freeze({
  users: Object.freeze({
    role_names: "Derived duplicate of canonical role_ids; no runtime reader remains.",
  }),
  referral_codes: Object.freeze({}),
  roles: Object.freeze({}),
  trading_exam_questions: Object.freeze({}),
  market_analyses: Object.freeze({}),
});

function parseArgs(argv) {
  const options = {
    apply: argv.includes("--apply"),
    projectId: valueOf(argv, "--project") || DEFAULT_PROJECT,
    collection: valueOf(argv, "--collection") || "users",
    confirmProject: valueOf(argv, "--confirm-project"),
    confirmation: valueOf(argv, "--confirm-production-cleanup"),
    backupDir: valueOf(argv, "--backup-dir"),
    checkpoint: valueOf(argv, "--checkpoint"),
    restore: valueOf(argv, "--restore"),
    limit: numberOf(argv, "--limit", 100),
  };
  if (!Number.isInteger(options.limit) || options.limit < 1 || options.limit > 5000) {
    throw new Error("--limit must be an integer from 1 to 5000.");
  }
  if (!CANDIDATES[options.collection]) {
    throw new Error(
        `Unsupported --collection. Choose: ${Object.keys(CANDIDATES).join(", ")}.`,
    );
  }
  return options;
}

function valueOf(argv, key) {
  const prefix = `${key}=`;
  const inline = argv.find((item) => item.startsWith(prefix));
  if (inline) return inline.slice(prefix.length).trim();
  const index = argv.indexOf(key);
  return index >= 0 ? String(argv[index + 1] || "").trim() : "";
}

function numberOf(argv, key, fallback) {
  const value = valueOf(argv, key);
  return value ? Number(value) : fallback;
}

function assertApplySafety(options) {
  if (!options.apply) return;
  if (options.confirmProject !== options.projectId) {
    throw new Error(
        `Apply requires --confirm-project=${options.projectId}.`,
    );
  }
  if (options.confirmation !== CONFIRMATION) {
    throw new Error(
        `Apply requires --confirm-production-cleanup=${CONFIRMATION}.`,
    );
  }
  if (!options.backupDir) {
    throw new Error("Apply requires --backup-dir=<path>.");
  }
  if (!process.argv.some((item) => item.startsWith("--limit"))) {
    throw new Error("Apply requires an explicit --limit=<1..5000>.");
  }
}

function inventory(documents, collection) {
  const fieldCounts = {};
  for (const document of documents) {
    for (const field of Object.keys(document.data || {})) {
      fieldCounts[field] = (fieldCounts[field] || 0) + 1;
    }
  }
  const candidates = CANDIDATES[collection];
  const affected = documents.filter((document) =>
    Object.keys(candidates).some((field) => field in document.data));
  return {
    documentsScanned: documents.length,
    documentsWithCandidates: affected.length,
    candidateCounts: Object.fromEntries(
        Object.keys(candidates).map((field) => [
          field,
          documents.filter((document) => field in document.data).length,
        ]),
    ),
    mostCommonFields: Object.entries(fieldCounts)
        .sort((left, right) => right[1] - left[1] || left[0].localeCompare(right[0]))
        .slice(0, 40)
        .map(([field, count]) => ({field, count})),
    affected,
  };
}

function deleteCandidateWrite(projectId, document, fields) {
  return {
    update: {
      name: `projects/${projectId}/databases/(default)/documents/${document.path}`,
      fields: {},
    },
    updateMask: {fieldPaths: fields},
    currentDocument: {exists: true},
  };
}

function restoreWrite(projectId, entry) {
  const fields = entry.fields || {};
  return {
    update: {
      name: `projects/${projectId}/databases/(default)/documents/${entry.path}`,
      fields: encodeFields(fields),
    },
    updateMask: {fieldPaths: Object.keys(fields)},
    currentDocument: {exists: true},
  };
}

async function run(options) {
  assertApplySafety(options);
  const auth = await resolveFirebaseAuth(options.projectId);
  if (options.restore) return restoreBackup(options, auth);
  const documents = await listCollectionDocuments({
    projectId: options.projectId,
    auth,
    collection: options.collection,
  });
  const report = inventory(documents, options.collection);
  const selected = report.affected.slice(0, options.limit);
  const summary = {
    mode: options.apply ? "apply" : "dry-run",
    projectId: options.projectId,
    collection: options.collection,
    documentsScanned: report.documentsScanned,
    documentsWithCandidates: report.documentsWithCandidates,
    candidateCounts: report.candidateCounts,
    documentLimit: options.limit,
    selected: selected.length,
    processed: 0,
    failed: 0,
    errors: [],
    mostCommonFields: report.mostCommonFields,
  };
  if (!options.apply || !selected.length) return summary;

  fs.mkdirSync(options.backupDir, {recursive: true});
  const stamp = new Date().toISOString().replace(/[:.]/g, "-");
  const backupPath = path.join(
      options.backupDir,
      `firestore-cleanup-v4-${options.collection}-${stamp}.json`,
  );
  const backup = selected.map((document) => ({
    path: document.path,
    fields: Object.fromEntries(
        Object.keys(CANDIDATES[options.collection])
            .filter((field) => field in document.data)
            .map((field) => [field, document.data[field]]),
    ),
  }));
  fs.writeFileSync(backupPath, JSON.stringify({
    version: 4,
    projectId: options.projectId,
    collection: options.collection,
    createdAt: new Date().toISOString(),
    entries: backup,
  }, null, 2));
  summary.backupPath = backupPath;

  const checkpointPath = options.checkpoint || `${backupPath}.checkpoint.json`;
  const completed = new Set(readCheckpoint(checkpointPath).completed || []);
  for (let index = 0; index < selected.length; index += 100) {
    const batch = selected.slice(index, index + 100)
        .filter((document) => !completed.has(document.path));
    if (!batch.length) continue;
    try {
      await commitWrites({
        projectId: options.projectId,
        auth,
        writes: batch.map((document) => deleteCandidateWrite(
            options.projectId,
            document,
            Object.keys(CANDIDATES[options.collection])
                .filter((field) => field in document.data),
        )),
      });
      for (const document of batch) completed.add(document.path);
      summary.processed += batch.length;
    } catch (error) {
      summary.failed += batch.length;
      summary.errors.push({
        paths: batch.map((document) => document.path),
        message: error.message || String(error),
      });
    }
    fs.writeFileSync(checkpointPath, JSON.stringify({
      projectId: options.projectId,
      collection: options.collection,
      backupPath,
      completed: [...completed],
      errors: summary.errors,
      updatedAt: new Date().toISOString(),
    }, null, 2));
    console.error(
        `cleanup progress: ${completed.size}/${selected.length}, failed=${summary.failed}`,
    );
  }
  summary.checkpointPath = checkpointPath;
  summary.processed = completed.size;
  return summary;
}

async function restoreBackup(options, auth) {
  if (!options.apply) {
    throw new Error("Restore requires --apply and all production confirmations.");
  }
  const payload = JSON.parse(fs.readFileSync(options.restore, "utf8"));
  if (payload.projectId !== options.projectId || !Array.isArray(payload.entries)) {
    throw new Error("Backup project or format does not match.");
  }
  const entries = payload.entries.slice(0, options.limit);
  let restored = 0;
  for (let index = 0; index < entries.length; index += 100) {
    const batch = entries.slice(index, index + 100);
    await commitWrites({
      projectId: options.projectId,
      auth,
      writes: batch.map((entry) => restoreWrite(options.projectId, entry)),
    });
    restored += batch.length;
  }
  return {
    mode: "restore",
    projectId: options.projectId,
    backupPath: options.restore,
    restored,
  };
}

function readCheckpoint(checkpointPath) {
  if (!checkpointPath || !fs.existsSync(checkpointPath)) return {};
  try {
    return JSON.parse(fs.readFileSync(checkpointPath, "utf8"));
  } catch (_) {
    throw new Error(`Checkpoint is not valid JSON: ${checkpointPath}`);
  }
}

if (require.main === module) {
  Promise.resolve()
      .then(() => run(parseArgs(process.argv.slice(2))))
      .then((summary) => console.log(JSON.stringify(summary, null, 2)))
      .catch((error) => {
        console.error(error.message || error);
        process.exitCode = 1;
      });
}

module.exports = {
  CANDIDATES,
  CONFIRMATION,
  inventory,
  parseArgs,
  run,
};
