#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const {validateKnowledgeCentres} = require("./validate_knowledge_centres");
const {
  commitWrites,
  encodeFields,
  listCollectionDocuments,
  resolveFirebaseAuth,
} = require("./firebase_rest_helpers");

const projectRoot = path.resolve(__dirname, "..");
const defaultInput = path.join(projectRoot, "assets", "seed", "knowledge_centres_30.json");
const defaultCollection = "knowledgeCentres";
const requiredTextFields = [
  "title",
  "shortDescription",
  "beginnerExplanation",
  "practicalExplanation",
  "fullContent",
];
const requiredListFields = [
  "learningObjectives",
  "stepByStepGuide",
  "keyPoints",
  "examples",
  "commonMistakes",
  "checklist",
  "relatedConcepts",
];

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const projectId =
    args.project ||
    process.env.GCLOUD_PROJECT ||
    process.env.GOOGLE_CLOUD_PROJECT ||
    "hcgaming-31d5d";
  const collection = args.collection || defaultCollection;
  if (collection !== defaultCollection) {
    throw new Error(`Refusing to update ${collection}; this tool only targets ${defaultCollection}.`);
  }

  const apply = args.apply === true || args.confirm === true;
  const createMissing = args["create-missing"] === true;
  const inputPath = path.resolve(projectRoot, args.input || defaultInput);
  const docs = JSON.parse(fs.readFileSync(inputPath, "utf8"));
  const validation = validateKnowledgeCentres(docs);
  if (!validation.passed) {
    writePlanReport({args, projectId, collection, currentDocs: [], validation});
    throw new Error(`Refusing to update Firestore: validation failed with ${validation.issues.length} issue(s).`);
  }

  const auth = await resolveFirebaseAuth(projectId);
  const currentDocs = await listCollectionDocuments({projectId, auth, collection});
  const plan = buildPlan({docs, currentDocs, createMissing});
  writePlanReport({args, projectId, collection, currentDocs, validation, plan});
  printPlan({plan, collection, apply});

  if (!apply) {
    console.log("Dry run only. Re-run with --confirm to write Firestore.");
    return;
  }

  if (plan.missing.length > 0 && !createMissing) {
    throw new Error(
        `${collection} is missing ${plan.missing.length} module(s). ` +
        "Re-run with --create-missing to create full module documents safely.",
    );
  }
  if (plan.missingAssets.length > 0) {
    throw new Error(`Refusing to update Firestore: ${plan.missingAssets.length} asset file(s) are missing.`);
  }

  const writes = plan.items
      .filter((item) => item.mode !== "missing")
      .map((item) => buildWrite({projectId, collection, item}));
  for (let index = 0; index < writes.length; index += 100) {
    await commitWrites({projectId, auth, writes: writes.slice(index, index + 100)});
  }

  for (const item of plan.items.filter((entry) => entry.mode !== "missing")) {
    console.log(
        `${item.mode === "create" ? "Created" : "Updated"} ${collection}/${item.id}: ` +
        `version=${item.proposed.contentVersion}, quality=${item.proposed.qualityStatus}, ` +
        `enWords=${item.proposed.lengths.enFullContentWords}, zhChars=${item.proposed.lengths.zhFullContentCjk}, ` +
        `${item.proposed.imageSections} imageSections`,
    );
  }
  console.log(`Knowledge Centre comprehensive content update complete: ${writes.length} module(s).`);
}

function buildPlan({docs, currentDocs, createMissing}) {
  const currentById = new Map(currentDocs.map((doc) => [doc.id, doc]));
  const items = [];
  const missing = [];
  const missingAssets = [];

  for (const doc of docs) {
    const id = doc.knowledgeCenterId;
    const current = currentById.get(id);
    if (!current) missing.push(id);
    const images = Array.isArray(doc.imageSections) ? doc.imageSections : [];
    for (const image of images) {
      const assetPath = image.assetPath || "";
      if (assetPath && !fs.existsSync(path.resolve(projectRoot, assetPath))) {
        missingAssets.push({id, imageId: image.imageId, assetPath});
      }
    }
    items.push({
      id,
      mode: current ? "update" : createMissing ? "create" : "missing",
      current: summarizeDoc(current?.data || {}),
      proposed: summarizeDoc(doc),
      currentMissingFields: missingContentFields(current?.data || {}),
      proposedMissingFields: missingContentFields(doc),
      concepts: images.map((image) => image.concept),
      assetPaths: images.map((image) => image.assetPath),
      doc,
    });
  }

  return {
    collectionDocuments: currentDocs.length,
    items,
    missing,
    missingAssets,
    updateCount: items.filter((item) => item.mode === "update").length,
    createCount: items.filter((item) => item.mode === "create").length,
    productionReadyCount: items.filter((item) => item.proposed.qualityStatus === "production_ready").length,
    contentVersion2Count: items.filter((item) => item.proposed.contentVersion === 2).length,
  };
}

function summarizeDoc(doc) {
  const en = doc.locales?.en || {};
  const zh = doc.locales?.zh || {};
  return {
    contentVersion: doc.contentVersion ?? null,
    qualityStatus: doc.qualityStatus || "",
    titleEn: en.title || localizedText(doc.title, "en"),
    titleZh: zh.title || localizedText(doc.title, "zh"),
    imageSections: Array.isArray(doc.imageSections) ? doc.imageSections.length : 0,
    lengths: {
      enFullContentWords: wordCount(en.fullContent),
      zhFullContentCjk: cjkCount(zh.fullContent),
      enLearningObjectives: Array.isArray(en.learningObjectives) ? en.learningObjectives.length : 0,
      zhLearningObjectives: Array.isArray(zh.learningObjectives) ? zh.learningObjectives.length : 0,
      enStepByStepGuide: Array.isArray(en.stepByStepGuide) ? en.stepByStepGuide.length : 0,
      zhStepByStepGuide: Array.isArray(zh.stepByStepGuide) ? zh.stepByStepGuide.length : 0,
    },
  };
}

function missingContentFields(doc) {
  const missing = [];
  for (const language of ["en", "zh"]) {
    const locale = doc.locales?.[language] || {};
    for (const field of requiredTextFields) {
      if (!String(locale[field] || "").trim()) {
        missing.push(`locales.${language}.${field}`);
      }
    }
    for (const field of requiredListFields) {
      if (!Array.isArray(locale[field]) || locale[field].length === 0) {
        missing.push(`locales.${language}.${field}`);
      }
    }
  }
  if (doc.contentVersion !== 2) missing.push("contentVersion");
  if (doc.qualityStatus !== "production_ready") missing.push("qualityStatus");
  return missing;
}

function buildWrite({projectId, collection, item}) {
  const name =
    `projects/${projectId}/databases/(default)/documents/` +
    `${collection}/${item.id}`;
  const payload = item.mode === "create" ? item.doc : contentPayload(item.doc);
  return {
    update: {name, fields: encodeFields(payload)},
    updateMask: {fieldPaths: Object.keys(payload)},
    updateTransforms: timestampTransforms(item.mode === "create"),
  };
}

function contentPayload(doc) {
  return {
    knowledgeCenterId: doc.knowledgeCenterId,
    category: doc.category,
    order: doc.order,
    isActive: doc.isActive,
    status: doc.status,
    approval_status: doc.approval_status,
    contentVersion: doc.contentVersion,
    qualityStatus: doc.qualityStatus,
    relatedQuestionTarget: doc.relatedQuestionTarget,
    futureFinalExamMappingReady: doc.futureFinalExamMappingReady,
    mappedQuestionIds: doc.mappedQuestionIds,
    thumbnailAssetPath: doc.thumbnailAssetPath,
    imageSections: doc.imageSections,
    locales: doc.locales,
    title: doc.title,
    description: doc.description,
    title_i18n: doc.title_i18n,
    description_i18n: doc.description_i18n,
    file_path: doc.file_path,
    image_url: doc.image_url,
    seedMeta: doc.seedMeta,
  };
}

function timestampTransforms(includeCreated) {
  const fields = includeCreated
    ? ["createdAt", "created_at", "updatedAt", "updated_at", "approved_at"]
    : ["updatedAt", "updated_at"];
  return fields.map((fieldPath) => ({fieldPath, setToServerValue: "REQUEST_TIME"}));
}

function printPlan({plan, collection, apply}) {
  console.log(`${apply ? "Confirm" : "Dry-run"} comprehensive content plan for ${collection}`);
  console.log(
      `Existing docs=${plan.collectionDocuments}, updates=${plan.updateCount}, ` +
      `creates=${plan.createCount}, missing=${plan.missing.length}, missingAssets=${plan.missingAssets.length}, ` +
      `v2=${plan.contentVersion2Count}, productionReady=${plan.productionReadyCount}`,
  );
  for (const item of plan.items) {
    console.log(
        `${item.id}: mode=${item.mode}, currentVersion=${item.current.contentVersion}, ` +
        `proposedVersion=${item.proposed.contentVersion}, currentEnWords=${item.current.lengths.enFullContentWords}, ` +
        `proposedEnWords=${item.proposed.lengths.enFullContentWords}, currentZhChars=${item.current.lengths.zhFullContentCjk}, ` +
        `proposedZhChars=${item.proposed.lengths.zhFullContentCjk}, currentImages=${item.current.imageSections}, ` +
        `proposedImages=${item.proposed.imageSections}`,
    );
    if (item.currentMissingFields.length > 0) {
      console.log(`  current missing: ${item.currentMissingFields.join(", ")}`);
    }
    console.log(`  concepts: ${item.concepts.join(", ")}`);
  }
  if (plan.missing.length > 0) {
    console.log(`Missing docs: ${plan.missing.join(", ")}`);
  }
  if (plan.missingAssets.length > 0) {
    console.log("Missing assets:");
    for (const item of plan.missingAssets) {
      console.log(`  ${item.id} ${item.imageId}: ${item.assetPath}`);
    }
  }
}

function writePlanReport({args, projectId, collection, currentDocs, validation, plan}) {
  const outputPath = path.resolve(
      projectRoot,
      args.output || "build/reports/knowledge_centres_comprehensive_content_update_plan.json",
  );
  fs.mkdirSync(path.dirname(outputPath), {recursive: true});
  const report = {
    generatedAt: new Date().toISOString(),
    projectId,
    collection,
    mode: args.apply || args.confirm ? "confirm" : "dry-run",
    currentCollectionDocuments: currentDocs.length,
    validation,
    plan,
  };
  fs.writeFileSync(outputPath, `${JSON.stringify(report, null, 2)}\n`, "utf8");
  console.log(`Comprehensive content update plan report written to ${outputPath}`);
}

function localizedText(value, language) {
  if (value && typeof value === "object" && !Array.isArray(value)) {
    return String(value[language] || value.en || value.zh || "");
  }
  return String(value || "");
}

function wordCount(value) {
  return String(value || "")
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .length;
}

function cjkCount(value) {
  const matches = String(value || "").match(/[\u3400-\u9fff]/g);
  return matches ? matches.length : 0;
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
