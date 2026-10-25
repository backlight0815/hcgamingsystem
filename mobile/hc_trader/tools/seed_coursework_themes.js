#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const admin = require("firebase-admin");
const {validateCourseworkBank} = require("./validate_coursework_question_bank");

const root = path.resolve(__dirname, "..");
const input = path.join(root, "assets", "seed", "coursework_themes_100.json");

async function main() {
  const args = new Set(process.argv.slice(2));
  const themes = JSON.parse(fs.readFileSync(input, "utf8"));
  const validation = validateCourseworkBank(themes);
  if (!validation.passed) throw new Error(`Invalid Coursework bank:\n${validation.issues.join("\n")}`);
  if (!args.has("--apply")) {
    console.log("Dry run only. Re-run with --apply to upsert Firestore.");
    console.log(JSON.stringify(validation.counts, null, 2));
    return;
  }
  const projectId = valueAfter("--project") || process.env.GCLOUD_PROJECT || process.env.GOOGLE_CLOUD_PROJECT;
  admin.initializeApp({credential: admin.credential.applicationDefault(), projectId});
  const db = admin.firestore();
  let batch = db.batch();
  let operations = 0;
  async function commitIfNeeded(force = false) {
    if (operations >= 450 || (force && operations)) {
      await batch.commit();
      batch = db.batch();
      operations = 0;
    }
  }
  for (const theme of themes) {
    const {questions, ...record} = theme;
    const reference = db.collection("coursework_themes").doc(theme.theme_id);
    batch.set(reference, {
      ...record,
      updated_at: admin.firestore.FieldValue.serverTimestamp(),
    }, {merge: true});
    operations++;
    for (const question of questions) {
      batch.set(reference.collection("questions").doc(question.question_id), {
        ...question,
        updated_at: admin.firestore.FieldValue.serverTimestamp(),
      }, {merge: true});
      operations++;
      await commitIfNeeded();
    }
  }
  batch.set(db.collection("assessment_settings").doc("current"), {
    coursework_weight: 0.7,
    final_exam_weight: 0.3,
    pass_mark: 60,
    grade_boundaries: {A: 80, B: 70, C: 60, D: 50, F: 0},
    calculation_version: 1,
    show_correct_answers: true,
    updated_at: admin.firestore.FieldValue.serverTimestamp(),
  }, {merge: true});
  operations++;
  await commitIfNeeded(true);
  console.log(`Upserted ${themes.length} themes and ${validation.counts.questions} questions.`);
}

function valueAfter(name) {
  const index = process.argv.indexOf(name);
  return index >= 0 ? process.argv[index + 1] : undefined;
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
