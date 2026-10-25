#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const admin = require("firebase-admin");

const version = 1;

async function main() {
  const apply = process.argv.includes("--apply");
  const projectIndex = process.argv.indexOf("--project");
  const projectId = projectIndex >= 0 ? process.argv[projectIndex + 1] :
    process.env.GCLOUD_PROJECT || process.env.GOOGLE_CLOUD_PROJECT;
  admin.initializeApp({credential: admin.credential.applicationDefault(), projectId});
  const db = admin.firestore();
  const snapshot = await db.collectionGroup("trading_exam_attempts").get();
  const records = snapshot.docs.map((doc) => ({path: doc.ref.path, data: serializable(doc.data())}));
  const pending = snapshot.docs.filter((doc) =>
    doc.data().assessment_type !== "legacyDailyExam" ||
    Number(doc.data().migration_version || 0) < version);
  const report = {
    migration: "daily_exam_to_legacy_coursework_history",
    version,
    projectId,
    mode: apply ? "apply" : "dry-run",
    scanned: snapshot.size,
    alreadyMigrated: snapshot.size - pending.length,
    pending: pending.length,
    generatedAt: new Date().toISOString(),
  };
  console.log(JSON.stringify(report, null, 2));
  if (!apply) {
    console.log("Dry run only. Re-run with --apply after reviewing the counts.");
    return;
  }
  const root = path.resolve(__dirname, "..", "..");
  const backupDirectory = path.join(root, "backups", "coursework_migration");
  fs.mkdirSync(backupDirectory, {recursive: true});
  const stamp = new Date().toISOString().replace(/[:.]/g, "-");
  const backupPath = path.join(backupDirectory, `legacy-daily-exam-backup-${stamp}.json`);
  fs.writeFileSync(backupPath, `${JSON.stringify(records, null, 2)}\n`);
  let batch = db.batch();
  let count = 0;
  for (const doc of pending) {
    batch.update(doc.ref, {
      assessment_type: "legacyDailyExam",
      migration_version: version,
      migrated_at: admin.firestore.FieldValue.serverTimestamp(),
    });
    count++;
    if (count % 450 === 0) {
      await batch.commit();
      batch = db.batch();
    }
  }
  if (count % 450) await batch.commit();
  const reportPath = path.join(backupDirectory, `migration-report-${stamp}.json`);
  fs.writeFileSync(reportPath, `${JSON.stringify({...report, backupPath, updated: count}, null, 2)}\n`);
  console.log(`Updated ${count} legacy records. Backup: ${backupPath}`);
  console.log(`Report: ${reportPath}`);
}

function serializable(value) {
  if (value?.toDate instanceof Function) return value.toDate().toISOString();
  if (Array.isArray(value)) return value.map(serializable);
  if (value && typeof value === "object") {
    return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, serializable(item)]));
  }
  return value;
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
