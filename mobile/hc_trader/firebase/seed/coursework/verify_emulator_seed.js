#!/usr/bin/env node
"use strict";

const assert = require("node:assert/strict");
const admin = require("firebase-admin");

async function main() {
  if (!process.env.FIRESTORE_EMULATOR_HOST) {
    throw new Error("FIRESTORE_EMULATOR_HOST is required; refusing to contact production.");
  }
  const projectId = process.argv.find((value) => value.startsWith("--project="))?.split("=")[1];
  if (!projectId) throw new Error("Use --project=<emulator-project-id>.");
  admin.initializeApp({projectId});
  const db = admin.firestore();
  const [themes, keys] = await Promise.all([
    db.collection("coursework_themes").orderBy("sortOrder").get(),
    db.collection("coursework_theme_keys").get(),
  ]);
  if (process.argv.includes("--expect-empty")) {
    assert.equal(themes.size, 0, "Expected no public seed themes after rollback.");
    assert.equal(keys.size, 0, "Expected no private seed roots after rollback.");
    console.log(JSON.stringify({themes: 0, privateThemeRoots: 0, rollbackVerified: true}, null, 2));
    return;
  }
  assert.equal(themes.size, 100, "Expected exactly 100 public themes.");
  assert.equal(keys.size, 100, "Expected exactly 100 private key roots.");
  let publicQuestions = 0;
  let privateQuestions = 0;
  for (const theme of themes.docs) {
    const [questions, answerKeys] = await Promise.all([
      theme.ref.collection("questions").get(),
      db.collection("coursework_theme_keys").doc(theme.id).collection("questions").get(),
    ]);
    assert.equal(questions.size, 5, `${theme.id} must have five public questions.`);
    assert.equal(answerKeys.size, 5, `${theme.id} must have five private question keys.`);
    publicQuestions += questions.size;
    privateQuestions += answerKeys.size;
    for (const question of questions.docs) {
      const data = question.data();
      assert.equal("correctOptionIds" in data, false, `${question.ref.path} leaked a private key.`);
      assert.equal("modelAnswer" in data, false, `${question.ref.path} leaked a model answer.`);
    }
  }
  assert.equal(publicQuestions, 500);
  assert.equal(privateQuestions, 500);
  console.log(JSON.stringify({themes: themes.size, publicQuestions, privateThemeRoots: keys.size, privateQuestions}, null, 2));
}

main().catch((error) => {
  console.error(error.stack || error);
  process.exitCode = 1;
});
