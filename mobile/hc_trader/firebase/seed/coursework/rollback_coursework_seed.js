#!/usr/bin/env node
"use strict";

const admin = require("firebase-admin");
const {SEED_VERSION} = require("./generate_coursework_seed");
const {themeNumber} = require("./validate_coursework_seed");

async function main() {
  const options = parseArgs(process.argv.slice(2));
  if (!options.projectId) throw new Error("An explicit --project=<project-id> is required.");
  enforceSafety(options);
  initialise(options);
  const db = admin.firestore();
  const themeSnapshots = await db.collection("coursework_themes")
      .where("managedBySeed", "==", true)
      .where("seedVersion", "==", SEED_VERSION).get();
  const selected = themeSnapshots.docs.filter((doc) => {
    const number = themeNumber(doc.id);
    return number >= themeNumber(options.from) && number <= themeNumber(options.to);
  });
  const deletions = [];
  for (const theme of selected) {
    const [questions, privateTheme, privateQuestions] = await Promise.all([
      theme.ref.collection("questions").get(),
      db.collection("coursework_theme_keys").doc(theme.id).get(),
      db.collection("coursework_theme_keys").doc(theme.id).collection("questions").get(),
    ]);
    questions.docs.filter(isManagedSeed).forEach((doc) => deletions.push(doc.ref));
    privateQuestions.docs.filter(isManagedSeed).forEach((doc) => deletions.push(doc.ref));
    if (privateTheme.exists && isManagedSeed(privateTheme)) deletions.push(privateTheme.ref);
    deletions.push(theme.ref);
  }
  if (!options.write) {
    console.log(JSON.stringify({mode: "rollback-dry-run", projectId: options.projectId, themes: selected.length, documents: deletions.length}, null, 2));
    return;
  }
  const writer = db.bulkWriter();
  writer.onWriteError((error) => error.failedAttempts < 4);
  await Promise.all(deletions.map((reference) => writer.delete(reference)));
  await writer.close();
  console.log(JSON.stringify({mode: options.emulator ? "rollback-emulator" : "rollback-production", projectId: options.projectId, themesDeleted: selected.length, documentsDeleted: deletions.length}, null, 2));
}

function isManagedSeed(snapshot) {
  const data = snapshot.data() || {};
  return data.managedBySeed === true && data.seedVersion === SEED_VERSION;
}

function parseArgs(args) {
  const value = (name, fallback) => {
    const inline = args.find((item) => item.startsWith(`${name}=`));
    if (inline) return inline.slice(name.length + 1);
    const index = args.indexOf(name);
    return index >= 0 ? args[index + 1] : fallback;
  };
  return {
    projectId: value("--project", process.env.GCLOUD_PROJECT || process.env.GOOGLE_CLOUD_PROJECT),
    from: String(value("--from", "CW-001")).toUpperCase(),
    to: String(value("--to", "CW-100")).toUpperCase(),
    write: args.includes("--write"),
    emulator: args.includes("--emulator"),
    production: args.includes("--production"),
    confirmProduction: args.includes("--confirm-production-rollback"),
  };
}

function enforceSafety(options) {
  if (Number.isNaN(themeNumber(options.from)) || Number.isNaN(themeNumber(options.to))) throw new Error("Rollback range must use CW-001..CW-100.");
  if (!options.write) return;
  if (options.emulator === options.production) throw new Error("A rollback write requires exactly one of --emulator or --production.");
  if (options.emulator && !process.env.FIRESTORE_EMULATOR_HOST) throw new Error("Emulator rollback requires FIRESTORE_EMULATOR_HOST.");
  if (options.production && !options.confirmProduction) throw new Error("Production rollback blocked. Add --confirm-production-rollback.");
  if (options.production && process.env.FIRESTORE_EMULATOR_HOST) throw new Error("Production rollback cannot use the emulator host.");
}

function initialise(options) {
  admin.initializeApp(options.emulator ? {projectId: options.projectId} : {
    projectId: options.projectId,
    credential: admin.credential.applicationDefault(),
  });
}

main().catch((error) => {
  console.error(error.stack || error);
  process.exitCode = 1;
});
