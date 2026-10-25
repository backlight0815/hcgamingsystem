#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const admin = require("firebase-admin");

const migrationVersion = 1;
const categories = [
  ["market_foundations", "Trading Market Foundations", "交易市场基础"],
  ["price_action", "Candlestick and Price Action", "蜡烛图与价格行为"],
  ["key_levels", "Support, Resistance and Key Levels", "支撑、阻力与关键水平"],
  ["market_structure", "Market Structure", "市场结构"],
  ["trend_indicators", "Trend, Momentum and Indicators", "趋势、动量与指标"],
  ["multi_timeframe", "Multi-Timeframe Analysis", "多时间周期分析"],
  ["risk_management", "Risk and Money Management", "风险与资金管理"],
  ["trade_execution", "Trade Planning and Execution", "交易计划与执行"],
  ["trading_psychology", "Trading Psychology and Discipline", "交易心理与纪律"],
  ["journal_review", "Trading Journal and Performance Review", "交易日志与绩效复盘"],
];

function argument(name) {
  const prefix = `${name}=`;
  const inline = process.argv.find((value) => value.startsWith(prefix));
  if (inline) return inline.slice(prefix.length);
  const index = process.argv.indexOf(name);
  return index >= 0 ? process.argv[index + 1] : undefined;
}

function token(value) {
  return String(value ?? "").trim().toLowerCase()
      .replaceAll(/[_–—]+/g, " ").replaceAll(/\s+/g, " ");
}

function localized(value) {
  if (value && typeof value === "object" && !(value instanceof admin.firestore.DocumentReference)) {
    return String(value.en || value.zh || value.text || value.value || "").trim();
  }
  return String(value ?? "").trim();
}

function documentId(value) {
  if (value instanceof admin.firestore.DocumentReference) return value.id;
  const raw = String(value ?? "").trim();
  return raw.includes("/") ? raw.split("/").filter(Boolean).at(-1) : raw;
}

function canonicalCategory(value, label) {
  const values = new Set([token(value), token(label)]);
  for (const [id, en, zh] of categories) {
    if ([id, en, zh].map(token).some((alias) => values.has(alias))) return id;
  }
  return "";
}

function canonicalDifficulty(value) {
  const normalized = token(value);
  if (["beginner", "basic", "foundation", "初级", "基础"].includes(normalized)) return "beginner";
  if (["intermediate", "medium", "中级"].includes(normalized)) return "intermediate";
  if (["advanced", "expert", "高级", "进阶"].includes(normalized)) return "advanced";
  return "";
}

function canonicalStatus(value) {
  if (value === true) return "published";
  if (value === false) return "draft";
  const normalized = token(value);
  if (["published", "publish", "live", "已发布"].includes(normalized)) return "published";
  if (["archived", "archive", "inactive", "已归档"].includes(normalized)) return "archived";
  if (["draft", "unpublished", "草稿"].includes(normalized)) return "draft";
  return "";
}

function unique(map, key) {
  const values = map.get(token(key)) || [];
  return values.length === 1 ? values[0] : "";
}

function addLookup(map, key, id) {
  const normalized = token(key);
  if (!normalized) return;
  map.set(normalized, [...(map.get(normalized) || []), id]);
}

function serializable(value) {
  if (value instanceof admin.firestore.DocumentReference) return {reference: value.path};
  if (value?.toDate instanceof Function) return value.toDate().toISOString();
  if (Array.isArray(value)) return value.map(serializable);
  if (value && typeof value === "object") {
    return Object.fromEntries(Object.entries(value).map(([key, item]) => [key, serializable(item)]));
  }
  return value;
}

async function main() {
  const apply = process.argv.includes("--apply");
  const projectId = argument("--project") || process.env.GCLOUD_PROJECT || process.env.GOOGLE_CLOUD_PROJECT;
  if (!projectId) throw new Error("Pass --project=<firebase-project-id>.");
  const emulator = Boolean(process.env.FIRESTORE_EMULATOR_HOST);
  if (apply && !emulator && argument("--confirm-production") !== projectId) {
    throw new Error(`Production apply requires --confirm-production=${projectId}.`);
  }
  admin.initializeApp({
    projectId,
    ...(emulator ? {} : {credential: admin.credential.applicationDefault()}),
  });
  const db = admin.firestore();
  const [themeSnapshot, assignmentSnapshot] = await Promise.all([
    db.collection("coursework_themes").get(),
    db.collection("coursework_assignments").get(),
  ]);
  const themeIds = new Set(themeSnapshot.docs.map((doc) => doc.id));
  const byCode = new Map();
  const byTitle = new Map();
  const themeUpdates = [];
  for (const doc of themeSnapshot.docs) {
    const data = doc.data();
    addLookup(byCode, data.code || data.theme_code || data.themeCode, doc.id);
    for (const title of [data.title, data.name, data.title_i18n, data.titleI18n]) {
      addLookup(byTitle, localized(title), doc.id);
      if (title && typeof title === "object") {
        Object.values(title).forEach((value) => addLookup(byTitle, value, doc.id));
      }
    }
    const patch = {};
    const categoryId = canonicalCategory(
        data.categoryId || data.category_id,
        data.categoryName || data.category_name || data.category);
    const difficulty = canonicalDifficulty(data.difficulty || data.difficultyLevel || data.level);
    const status = canonicalStatus(
        data.status ?? data.publicationStatus ?? data.publication_status ?? data.isPublished);
    if (categoryId && data.categoryId !== categoryId) patch.categoryId = categoryId;
    if (difficulty && data.difficulty !== difficulty) patch.difficulty = difficulty;
    if (status && data.status !== status) patch.status = status;
    if (data.isActive == null && data.active != null) patch.isActive = Boolean(data.active);
    if (Object.keys(patch).length) themeUpdates.push({ref: doc.ref, id: doc.id, patch});
  }

  const assignmentUpdates = [];
  const unresolved = [];
  for (const doc of assignmentSnapshot.docs) {
    const data = doc.data();
    const candidates = [
      data.theme_id,
      data.themeId,
      data.courseworkThemeId,
      data.coursework_theme_id,
      data.assignmentThemeId,
      data.assignment_theme_id,
      data.theme instanceof admin.firestore.DocumentReference ? data.theme : null,
    ].map(documentId).filter(Boolean);
    let resolved = candidates.find((id) => themeIds.has(id)) || "";
    if (!resolved) resolved = unique(byCode, data.theme_code || data.themeCode);
    if (!resolved) {
      resolved = unique(
          byTitle,
          data.theme_title || data.themeTitle || data.themeName || data.theme_name ||
          (typeof data.theme === "string" ? data.theme : ""));
    }
    if (!resolved) {
      unresolved.push(doc.id);
      continue;
    }
    if (!data.theme_id || !themeIds.has(documentId(data.theme_id))) {
      assignmentUpdates.push({ref: doc.ref, id: doc.id, patch: {theme_id: resolved}});
    }
  }

  const report = {
    migration: "coursework_theme_schema_and_assignment_references",
    version: migrationVersion,
    projectId,
    mode: apply ? "apply" : "dry-run",
    themesScanned: themeSnapshot.size,
    themesPending: themeUpdates.length,
    assignmentsScanned: assignmentSnapshot.size,
    assignmentsPending: assignmentUpdates.length,
    unresolvedAssignments: unresolved,
    generatedAt: new Date().toISOString(),
  };
  console.log(JSON.stringify(report, null, 2));
  if (!apply) {
    console.log("Dry run only. No Firestore documents were changed.");
    return;
  }

  const backupDirectory = path.resolve(__dirname, "..", "backups", "coursework_theme_migration");
  fs.mkdirSync(backupDirectory, {recursive: true});
  const stamp = new Date().toISOString().replace(/[:.]/g, "-");
  const backupPath = path.join(backupDirectory, `backup-${stamp}.json`);
  fs.writeFileSync(backupPath, `${JSON.stringify({
    themes: themeSnapshot.docs.map((doc) => ({path: doc.ref.path, data: serializable(doc.data())})),
    assignments: assignmentSnapshot.docs.map((doc) => ({path: doc.ref.path, data: serializable(doc.data())})),
  }, null, 2)}\n`);

  let batch = db.batch();
  let writes = 0;
  for (const update of [...themeUpdates, ...assignmentUpdates]) {
    batch.update(update.ref, {
      ...update.patch,
      courseworkMigrationVersion: migrationVersion,
      courseworkMigratedAt: admin.firestore.FieldValue.serverTimestamp(),
    });
    writes++;
    if (writes % 400 === 0) {
      await batch.commit();
      batch = db.batch();
    }
  }
  if (writes % 400) await batch.commit();
  console.log(JSON.stringify({...report, writes, backupPath}, null, 2));
}

if (require.main === module) {
  main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
  });
}

exports._test = {
  canonicalCategory,
  canonicalDifficulty,
  canonicalStatus,
  documentId,
  unique,
  addLookup,
};
