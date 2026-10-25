#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const admin = require("firebase-admin");
const {
  commitWrites,
  listCollectionDocuments,
  resolveFirebaseAuth,
} = require("./firebase_rest_helpers");

const projectRoot = path.resolve(__dirname, "..");
const collections = ["knowledge_centres", "knowledgeCentres"];
const confirmPhrase = "DELETE_DUMMY_KNOWLEDGE_CENTRES";

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const apply = args.apply === true;
  const projectId =
    args.project ||
    process.env.GCLOUD_PROJECT ||
    process.env.GOOGLE_CLOUD_PROJECT ||
    "hcgaming-31d5d";
  const reportDir = path.join(projectRoot, "build", "reports");
  const backupDir = path.join(projectRoot, "build", "backups");
  fs.mkdirSync(reportDir, {recursive: true});
  fs.mkdirSync(backupDir, {recursive: true});

  const firestore = await resolveFirestore(projectId);
  const docs = await listKnowledgeDocuments(firestore, projectId);
  const dummy = [];
  const suspicious = [];

  for (const doc of docs) {
    const result = classifyKnowledgeDoc(doc);
    if (result.action === "delete") {
      dummy.push({...doc, reasons: result.reasons});
    } else if (result.action === "suspicious") {
      suspicious.push({...doc, reasons: result.reasons});
    }
  }

  const timestamp = new Date().toISOString().replace(/[:.]/g, "-");
  const backupPath = path.join(backupDir, `dummy_knowledge_centres_${timestamp}.json`);
  const reportPath = path.join(reportDir, `dummy_knowledge_centres_cleanup_${timestamp}.json`);
  fs.writeFileSync(
      backupPath,
      `${JSON.stringify({dummy, suspicious}, null, 2)}\n`,
      "utf8",
  );

  const report = {
    generatedAt: new Date().toISOString(),
    projectId,
    mode: apply ? "apply" : "dry-run",
    scannedCollections: collections,
    scannedDocuments: docs.length,
    dummyCount: dummy.length,
    suspiciousPreservedCount: suspicious.length,
    deleted: [],
    dummy: dummy.map(summary),
    suspiciousPreserved: suspicious.map(summary),
    backupPath,
    finalExamCollectionsTouched: false,
  };

  if (!apply) {
    console.log("Dry run only. Re-run with --apply --confirm=DELETE_DUMMY_KNOWLEDGE_CENTRES to delete safe dummy Knowledge Centre records.");
  } else if (args.confirm !== confirmPhrase) {
    throw new Error(`Refusing to delete without --confirm=${confirmPhrase}`);
  } else {
    report.deleted = await deleteDummyDocs(firestore, projectId, dummy);
  }

  fs.writeFileSync(reportPath, `${JSON.stringify(report, null, 2)}\n`, "utf8");
  console.log(`Scanned ${docs.length} Knowledge Centre documents.`);
  console.log(`Safe dummy records: ${dummy.length}`);
  console.log(`Suspicious preserved records: ${suspicious.length}`);
  console.log(`Deleted records: ${report.deleted.length}`);
  console.log(`Backup written to ${backupPath}`);
  console.log(`Cleanup report written to ${reportPath}`);
  for (const deleted of report.deleted) {
    console.log(`Deleted ${deleted.collection}/${deleted.id}: ${deleted.reasons.join("; ")}`);
  }
  for (const item of suspicious.slice(0, 20)) {
    console.log(`Preserved suspicious ${item.collection}/${item.id}: ${item.reasons.join("; ")}`);
  }
}

async function resolveFirestore(projectId) {
  try {
    admin.initializeApp({
      credential: admin.credential.applicationDefault(),
      projectId,
    });
    const db = admin.firestore();
    await db.collection("knowledge_centres").limit(1).get();
    return {mode: "firebase-admin", db};
  } catch (error) {
    if (!/credential|auth|Could not load the default credentials/i.test(String(error?.message || error))) {
      throw error;
    }
    console.warn("Firebase Admin credentials unavailable; trying Firebase CLI REST auth.");
    const auth = await resolveFirebaseAuth(projectId);
    return {mode: "firebase-cli-rest", auth};
  }
}

async function listKnowledgeDocuments(firestore, projectId) {
  if (firestore.db) {
    const snapshots = await Promise.all(
        collections.map((collection) => firestore.db.collection(collection).get()),
    );
    return snapshots.flatMap((snapshot, index) =>
      snapshot.docs.map((doc) => ({
        id: doc.id,
        collection: collections[index],
        path: doc.ref.path,
        data: doc.data(),
      })),
    );
  }
  const groups = await Promise.all(
      collections.map((collection) =>
        listCollectionDocuments({projectId, auth: firestore.auth, collection}),
      ),
  );
  return groups.flatMap((docs, index) =>
    docs.map((doc) => ({
      ...doc,
      collection: collections[index],
      path: `${collections[index]}/${doc.id}`,
    })),
  );
}

function classifyKnowledgeDoc(doc) {
  const data = doc.data || {};
  const reasons = [];
  if (data.seedMeta?.contentVersion === "knowledge_centres_30_v1") {
    return {action: "keep", reasons};
  }

  if (data.isDummy === true) reasons.push("isDummy == true");
  if (data.isPlaceholder === true) reasons.push("isPlaceholder == true");
  const source = stringValue(data.source).toLowerCase();
  if (source === "dummy") reasons.push('source == "dummy"');
  if (source === "test") reasons.push('source == "test"');

  const title = collectLikelyTitle(data).toLowerCase();
  const allText = collectText(data).toLowerCase();
  const strongPatterns = [
    /\blorem ipsum\b/,
    /\bdummy data\b/,
    /\bdummy content\b/,
    /\btest data\b/,
    /\btest content\b/,
    /\bplaceholder content\b/,
    /\bexample only\b/,
    /\bold test data\b/,
    /\bcoming soon\b/,
  ];
  for (const pattern of strongPatterns) {
    if (pattern.test(allText)) reasons.push(`content matches ${pattern}`);
  }
  if (/^(test|testing|dummy|sample|placeholder|temporary|untitled|coming soon|example only)(\b|$)/.test(title)) {
    reasons.push("title clearly indicates dummy/test/placeholder content");
  }

  if (reasons.length) {
    return {action: "delete", reasons};
  }

  const suspiciousReasons = [];
  const suspiciousPatterns = [
    /\bdemo\b/,
    /\bsample\b/,
    /\btemporary\b/,
    /\bplaceholder\b/,
    /\btest\b/,
    /\buntitled\b/,
  ];
  for (const pattern of suspiciousPatterns) {
    if (pattern.test(allText)) suspiciousReasons.push(`contains ${pattern}`);
  }
  if (suspiciousReasons.length) {
    return {action: "suspicious", reasons: suspiciousReasons};
  }
  return {action: "keep", reasons: []};
}

async function deleteDummyDocs(firestore, projectId, docs) {
  if (!docs.length) return [];
  if (firestore.db) {
    for (let index = 0; index < docs.length; index += 450) {
      const batch = firestore.db.batch();
      for (const doc of docs.slice(index, index + 450)) {
        batch.delete(firestore.db.collection(doc.collection).doc(doc.id));
      }
      await batch.commit();
    }
  } else {
    for (let index = 0; index < docs.length; index += 100) {
      const writes = docs.slice(index, index + 100).map((doc) => ({
        delete: `projects/${projectId}/databases/(default)/documents/${doc.collection}/${doc.id}`,
      }));
      await commitWrites({projectId, auth: firestore.auth, writes});
    }
  }
  return docs.map(summary);
}

function collectLikelyTitle(data) {
  return [
    stringValue(data.title),
    stringValue(data.title_en),
    stringValue(data.locales?.en?.title),
    stringValue(data.locales?.zh?.title),
  ].find((value) => value.trim().isNotEmpty) || "";
}

function collectText(value) {
  if (value == null) return "";
  if (Array.isArray(value)) return value.map(collectText).join(" ");
  if (typeof value === "object") {
    return Object.values(value).map(collectText).join(" ");
  }
  return stringValue(value);
}

function stringValue(value) {
  return value == null ? "" : String(value).trim();
}

function summary(doc) {
  return {
    collection: doc.collection,
    id: doc.id,
    path: doc.path,
    reasons: doc.reasons,
    title: collectLikelyTitle(doc.data || {}),
  };
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
