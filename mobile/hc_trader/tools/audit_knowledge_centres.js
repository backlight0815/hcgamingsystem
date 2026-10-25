#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const admin = require("firebase-admin");
const {
  listCollectionDocuments,
  resolveFirebaseAuth,
} = require("./firebase_rest_helpers");
const {validateKnowledgeCentres} = require("./validate_knowledge_centres");

const projectRoot = path.resolve(__dirname, "..");
const defaultCollection = "knowledge_centres";

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const projectId =
    args.project ||
    process.env.GCLOUD_PROJECT ||
    process.env.GOOGLE_CLOUD_PROJECT ||
    "hcgaming-31d5d";
  const collection = args.collection || defaultCollection;
  const outputPath = path.resolve(
      projectRoot,
      args.output || "build/reports/knowledge_centres_audit.md",
  );
  const jsonOutputPath = outputPath.replace(/\.md$/i, ".json");
  fs.mkdirSync(path.dirname(outputPath), {recursive: true});

  const firestore = await resolveFirestore(projectId, collection);
  let docs = await listDocs(firestore, projectId, collection);
  const totalCollectionDocuments = docs.length;
  if (args["seed-only"] === true) {
    docs = docs.filter(isSeedDoc);
  }
  const validation = validateKnowledgeCentres(
      docs.map((doc) => ({...doc.data, knowledgeCenterId: doc.data.knowledgeCenterId || doc.id})),
  );
  const report = {
    generatedAt: new Date().toISOString(),
    projectId,
    collection,
    authMode: firestore.mode,
    seedOnly: args["seed-only"] === true,
    totalCollectionDocuments,
    checkedDocuments: docs.length,
    validation,
  };
  fs.writeFileSync(jsonOutputPath, `${JSON.stringify(report, null, 2)}\n`, "utf8");
  fs.writeFileSync(outputPath, markdownReport(report), "utf8");

  console.log(`Knowledge Centre audit written to ${outputPath}`);
  console.log(`JSON report written to ${jsonOutputPath}`);
  console.log(
      `Knowledge Centre docs=${docs.length}, images=${validation.counts.imageSections}, issues=${validation.issues.length}`,
  );
  if (args["fail-on-issues"] === true && !validation.passed) {
    process.exitCode = 1;
  }
}

async function resolveFirestore(projectId, collection) {
  try {
    admin.initializeApp({
      credential: admin.credential.applicationDefault(),
      projectId,
    });
    const db = admin.firestore();
    await db.collection(collection).limit(1).get();
    return {mode: "firebase-admin", db};
  } catch (error) {
    if (!/credential|auth|Could not load the default credentials/i.test(String(error?.message || error))) {
      throw error;
    }
    const auth = await resolveFirebaseAuth(projectId);
    return {mode: "firebase-cli-rest", auth};
  }
}

async function listDocs(firestore, projectId, collection) {
  if (firestore.db) {
    const snapshot = await firestore.db.collection(collection).get();
    return snapshot.docs
        .map((doc) => ({id: doc.id, path: doc.ref.path, data: doc.data()}))
        .sort((left, right) => left.id.localeCompare(right.id));
  }
  return listCollectionDocuments({projectId, auth: firestore.auth, collection});
}

function isSeedDoc(doc) {
  if (doc.data?.seedMeta?.contentVersion === "knowledge_centres_30_v1") return true;
  return /^KC_(THEORY|TECHNICAL|PSYCHOLOGY)_\d{3}$/.test(
      String(doc.data?.knowledgeCenterId || doc.id || ""),
  );
}

function markdownReport(report) {
  const counts = report.validation.counts;
  const issues = report.validation.issues;
  return `# Knowledge Centre Audit

Generated: ${report.generatedAt}
Project: ${report.projectId}
Collection: ${report.collection}
Auth mode: ${report.authMode}
Seed only: ${report.seedOnly}

## Summary

- Total collection documents: ${report.totalCollectionDocuments}
- Checked documents: ${report.checkedDocuments}
- Theory modules: ${counts.categories.theory}
- Technical modules: ${counts.categories.technical}
- Psychology modules: ${counts.categories.psychology}
- English locales: ${counts.modulesWithEnglish}
- Chinese locales: ${counts.modulesWithChinese}
- Content version 2 modules: ${counts.contentVersion2Modules}
- Production-ready modules: ${counts.productionReadyModules}
- Image sections: ${counts.imageSections}
- Local asset images: ${counts.localAssetImages}
- Technical modules with 3-5 images: ${counts.technicalModulesWithPreferredImages}
- Issues: ${issues.length}

## Issues

${issues.length ? issues.map((item) => `- ${item.owner} ${item.field}: ${item.message}`).join("\n") : "No issues found."}
`;
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
