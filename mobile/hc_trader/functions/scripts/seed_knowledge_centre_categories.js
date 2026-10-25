"use strict";

const {initializeApp} = require("firebase-admin/app");
const {getFirestore, FieldValue} = require("firebase-admin/firestore");

const categories = [
  ["theory", "Theory", "\u7406\u8bba", 10],
  ["technical", "Technical", "\u6280\u672f\u5206\u6790", 20],
  ["psychology", "Psychology", "\u4ea4\u6613\u5fc3\u7406", 30],
  ["stocks", "Stocks", "\u80a1\u7968", 40],
  ["futures-trading", "Futures Trading", "\u671f\u8d27\u4ea4\u6613", 50],
  ["investment", "Investment", "\u6295\u8d44", 60],
  ["cfd-trading", "CFD Trading", "\u5dee\u4ef7\u5408\u7ea6\u4ea4\u6613", 70],
];

function normalizeCategory(value) {
  const normalized = String(value || "").trim().toLowerCase()
      .replace(/[\s_]+/g, "-").replace(/-+/g, "-");
  if (["stock", "stocks"].includes(normalized)) return "stocks";
  if (["future", "futures", "trading-future", "future-trading",
    "futures-trading"].includes(normalized)) return "futures-trading";
  if (["investment", "investments"].includes(normalized)) return "investment";
  if (["cfd", "trading-cfd", "cfd-trading"].includes(normalized)) {
    return "cfd-trading";
  }
  return normalized;
}

async function run() {
  const apply = process.argv.includes("--apply");
  initializeApp();
  const db = getFirestore();
  const articleSnapshot = await db.collection("knowledge_centres").get();
  const aliases = articleSnapshot.docs
      .map((doc) => ({doc, normalized: normalizeCategory(doc.data().category)}))
      .filter(({doc, normalized}) =>
        normalized && normalized !== String(doc.data().category || ""));
  console.log(JSON.stringify({
    mode: apply ? "apply" : "dry-run",
    categories: categories.map(([slug]) => slug),
    articleCategoryAliasesToNormalize: aliases.length,
  }, null, 2));
  if (!apply) return;

  const operations = [];
  for (const [slug, en, zh, sortOrder] of categories) {
    operations.push({
      ref: db.collection("knowledge_centre_categories").doc(slug),
      data: {
      slug,
      labels: {en, zh},
      is_active: true,
      sort_order: sortOrder,
      updated_at: FieldValue.serverTimestamp(),
      },
    });
  }
  for (const {doc, normalized} of aliases) {
    operations.push({
      ref: doc.ref,
      data: {
        category: normalized,
        category_migrated_at: FieldValue.serverTimestamp(),
        updated_at: FieldValue.serverTimestamp(),
      },
    });
  }
  for (let offset = 0; offset < operations.length; offset += 400) {
    const batch = db.batch();
    for (const operation of operations.slice(offset, offset + 400)) {
      batch.set(operation.ref, operation.data, {merge: true});
    }
    await batch.commit();
  }
  console.log("Knowledge Centre categories initialized safely.");
}

run().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
