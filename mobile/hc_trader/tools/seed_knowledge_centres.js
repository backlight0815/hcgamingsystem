#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const admin = require("firebase-admin");
const {validateKnowledgeCentres} = require("./validate_knowledge_centres");
const {
  commitWrites,
  encodeFields,
  resolveFirebaseAuth,
} = require("./firebase_rest_helpers");

const projectRoot = path.resolve(__dirname, "..");
const defaultInput = path.join(projectRoot, "assets", "seed", "knowledge_centres_30.json");
const collection = "knowledge_centres";
const batchSize = 450;

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const apply = args.apply === true;
  const inputPath = path.resolve(projectRoot, args.input || defaultInput);
  const docs = JSON.parse(fs.readFileSync(inputPath, "utf8"));
  const validation = validateKnowledgeCentres(docs);
  if (!validation.passed) {
    throw new Error(
        `Refusing to seed invalid Knowledge Centre data: ${validation.issues.length} issue(s).`,
    );
  }

  const projectId =
    args.project ||
    process.env.GCLOUD_PROJECT ||
    process.env.GOOGLE_CLOUD_PROJECT ||
    "hcgaming-31d5d";

  if (!apply) {
    console.log("Dry run only. Re-run with --apply to write to Firestore.");
    console.log(`Would upsert ${docs.length} documents into ${collection}.`);
    console.log(JSON.stringify(validation.counts, null, 2));
    return;
  }

  try {
    await seedWithAdmin(docs, projectId);
  } catch (error) {
    if (!isCredentialError(error)) throw error;
    console.warn("Firebase Admin credentials unavailable; trying Firebase CLI REST auth.");
    await seedWithRest(docs, projectId);
  }

  console.log(`Knowledge Centre seed complete: ${docs.length} documents in ${collection}.`);
  console.log(JSON.stringify(validation.counts, null, 2));
}

async function seedWithAdmin(docs, projectId) {
  admin.initializeApp({
    credential: admin.credential.applicationDefault(),
    projectId,
  });
  const db = admin.firestore();
  const timestamp = admin.firestore.FieldValue.serverTimestamp();
  for (let index = 0; index < docs.length; index += batchSize) {
    const batch = db.batch();
    const chunk = docs.slice(index, index + batchSize);
    for (const doc of chunk) {
      batch.set(
          db.collection(collection).doc(doc.knowledgeCenterId),
          {
            ...doc,
            createdAt: timestamp,
            updatedAt: timestamp,
            created_at: timestamp,
            updated_at: timestamp,
            approved_at: timestamp,
          },
          {merge: true},
      );
    }
    await batch.commit();
    console.log(`Upserted ${Math.min(index + chunk.length, docs.length)}/${docs.length} Knowledge Centre modules.`);
  }
}

async function seedWithRest(docs, projectId) {
  const auth = await resolveFirebaseAuth(projectId);
  const restBatchSize = 100;
  for (let index = 0; index < docs.length; index += restBatchSize) {
    const chunk = docs.slice(index, index + restBatchSize);
    const writes = chunk.map((doc) => {
      const name =
        `projects/${projectId}/databases/(default)/documents/` +
        `${collection}/${doc.knowledgeCenterId}`;
      return {
        update: {name, fields: encodeFields(doc)},
        updateMask: {fieldPaths: Object.keys(doc)},
        updateTransforms: [
          {fieldPath: "createdAt", setToServerValue: "REQUEST_TIME"},
          {fieldPath: "updatedAt", setToServerValue: "REQUEST_TIME"},
          {fieldPath: "created_at", setToServerValue: "REQUEST_TIME"},
          {fieldPath: "updated_at", setToServerValue: "REQUEST_TIME"},
          {fieldPath: "approved_at", setToServerValue: "REQUEST_TIME"},
        ],
      };
    });
    await commitWrites({projectId, auth, writes});
    console.log(`Upserted ${Math.min(index + chunk.length, docs.length)}/${docs.length} Knowledge Centre modules.`);
  }
}

function isCredentialError(error) {
  return /credential|auth|Could not load the default credentials/i.test(
      String(error?.message || error),
  );
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
