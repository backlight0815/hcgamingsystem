#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const admin = require("firebase-admin");
const {validateQuestionBank} = require("./validate_final_exam_question_bank");

const projectRoot = path.resolve(__dirname, "..");
const defaultInput = path.join(
  projectRoot,
  "assets",
  "seed",
  "final_exam_questions_500.json",
);
const collection = "final_exam_questions";
const batchSize = 450;

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const apply = args.apply === true;
  const inputPath = path.resolve(projectRoot, args.input || defaultInput);
  const questions = JSON.parse(fs.readFileSync(inputPath, "utf8"));
  const validation = validateQuestionBank(questions);
  if (!validation.passed) {
    throw new Error(
      `Refusing to seed invalid final exam bank: ${validation.issues.length} issue(s).`,
    );
  }

  const projectId =
    args.project ||
    process.env.GCLOUD_PROJECT ||
    process.env.GOOGLE_CLOUD_PROJECT ||
    undefined;

  if (!apply) {
    console.log("Dry run only. Re-run with --apply to write to Firestore.");
    console.log(`Would upsert ${questions.length} documents into ${collection}.`);
    console.log(JSON.stringify(validation.counts, null, 2));
    return;
  }

  try {
    await seedWithAdmin(questions, projectId);
  } catch (error) {
    if (!isCredentialError(error)) throw error;
    console.warn("Firebase Admin credentials unavailable; trying Firebase CLI REST auth.");
    await seedWithRest(questions, projectId);
  }

  console.log(`Seed complete: ${questions.length} documents in ${collection}.`);
  console.log(JSON.stringify(validation.counts, null, 2));
}

async function seedWithAdmin(questions, projectId) {
  admin.initializeApp({
    credential: admin.credential.applicationDefault(),
    projectId,
  });
  const db = admin.firestore();
  const timestamp = admin.firestore.FieldValue.serverTimestamp();
  for (let index = 0; index < questions.length; index += batchSize) {
    const batch = db.batch();
    const chunk = questions.slice(index, index + batchSize);
    for (const question of chunk) {
      const reference = db.collection(collection).doc(question.questionId);
      batch.set(
        reference,
        {
          ...question,
          createdAt: timestamp,
          updatedAt: timestamp,
          created_at: timestamp,
          updated_at: timestamp,
        },
        {merge: true},
      );
    }
    await batch.commit();
    console.log(
      `Upserted ${Math.min(index + chunk.length, questions.length)}/${questions.length} questions.`,
    );
  }
}

async function seedWithRest(questions, projectId) {
  if (!projectId) {
    throw new Error("A Firebase project id is required for REST seeding.");
  }
  const accessToken = await firebaseCliAccessToken();
  const restBatchSize = 100;
  for (let index = 0; index < questions.length; index += restBatchSize) {
    const chunk = questions.slice(index, index + restBatchSize);
    const writes = chunk.map((question) => {
      const fields = encodeFields(question);
      const name =
        `projects/${projectId}/databases/(default)/documents/` +
        `${collection}/${question.questionId}`;
      return {
        update: {name, fields},
        updateMask: {fieldPaths: Object.keys(question)},
        updateTransforms: [
          {fieldPath: "createdAt", setToServerValue: "REQUEST_TIME"},
          {fieldPath: "updatedAt", setToServerValue: "REQUEST_TIME"},
          {fieldPath: "created_at", setToServerValue: "REQUEST_TIME"},
          {fieldPath: "updated_at", setToServerValue: "REQUEST_TIME"},
        ],
      };
    });
    const response = await fetch(
      `https://firestore.googleapis.com/v1/projects/${projectId}` +
        "/databases/(default)/documents:commit",
      {
        method: "POST",
        headers: {
          Authorization: `Bearer ${accessToken}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({writes}),
      },
    );
    if (!response.ok) {
      throw new Error(
        `Firestore REST seed failed: ${response.status} ${await response.text()}`,
      );
    }
    console.log(
      `Upserted ${Math.min(index + chunk.length, questions.length)}/${questions.length} questions.`,
    );
  }
}

async function firebaseCliAccessToken() {
  const authPath = path.join(
    process.env.APPDATA || "",
    "npm",
    "node_modules",
    "firebase-tools",
    "lib",
    "auth",
  );
  const auth = require(authPath);
  const account = auth.getGlobalDefaultAccount();
  if (!account?.tokens?.refresh_token) {
    throw new Error(
      "No Firebase Admin credentials or Firebase CLI login token available.",
    );
  }
  const token = await auth.getAccessToken(account.tokens.refresh_token, []);
  if (!token?.access_token) {
    throw new Error("Firebase CLI token refresh failed.");
  }
  return token.access_token;
}

function encodeFields(data) {
  return Object.fromEntries(
    Object.entries(data).map(([key, value]) => [key, encodeValue(value)]),
  );
}

function encodeValue(value) {
  if (value == null) return {nullValue: null};
  if (typeof value === "string") return {stringValue: value};
  if (typeof value === "boolean") return {booleanValue: value};
  if (typeof value === "number") {
    return Number.isInteger(value) ?
      {integerValue: String(value)} :
      {doubleValue: value};
  }
  if (Array.isArray(value)) {
    return {arrayValue: {values: value.map(encodeValue)}};
  }
  if (typeof value === "object") {
    return {mapValue: {fields: encodeFields(value)}};
  }
  return {stringValue: String(value)};
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
