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
    writePlanReport({args, projectId, collection, docs, currentDocs: [], validation});
    throw new Error(`Refusing to update Firestore: validation failed with ${validation.issues.length} issue(s).`);
  }

  const auth = await resolveFirebaseAuth(projectId);
  const currentDocs = await listCollectionDocuments({projectId, auth, collection});
  const plan = buildPlan({docs, currentDocs, createMissing});
  writePlanReport({args, projectId, collection, docs, currentDocs, validation, plan});
  printPlan({plan, collection, apply});

  if (!apply) {
    console.log("Dry run only. Re-run with --confirm --create-missing to write Firestore.");
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

  const writes = plan.items.map((item) => buildWrite({projectId, collection, item}));
  for (let index = 0; index < writes.length; index += 100) {
    await commitWrites({projectId, auth, writes: writes.slice(index, index + 100)});
  }

  for (const item of plan.items) {
    console.log(
        `${item.mode === "create" ? "Created" : "Updated"} ${collection}/${item.id}: ` +
        `${item.proposedCount} imageSections`,
    );
  }
  console.log(`Knowledge Centre imageSections update complete: ${plan.items.length} module(s).`);
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
      currentCount: Array.isArray(current?.data?.imageSections)
        ? current.data.imageSections.length
        : 0,
      proposedCount: images.length,
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
  };
}

function buildWrite({projectId, collection, item}) {
  const name =
    `projects/${projectId}/databases/(default)/documents/` +
    `${collection}/${item.id}`;
  if (item.mode === "create") {
    return {
      update: {name, fields: encodeFields(item.doc)},
      updateMask: {fieldPaths: Object.keys(item.doc)},
      updateTransforms: timestampTransforms(true),
    };
  }
  return {
    update: {name, fields: encodeFields({imageSections: item.doc.imageSections})},
    updateMask: {fieldPaths: ["imageSections"]},
    updateTransforms: timestampTransforms(false),
  };
}

function timestampTransforms(includeCreated) {
  const fields = includeCreated
    ? ["createdAt", "created_at", "updatedAt", "updated_at", "approved_at"]
    : ["updatedAt", "updated_at"];
  return fields.map((fieldPath) => ({fieldPath, setToServerValue: "REQUEST_TIME"}));
}

function printPlan({plan, collection, apply}) {
  console.log(`${apply ? "Confirm" : "Dry-run"} plan for ${collection}`);
  console.log(
      `Existing docs=${plan.collectionDocuments}, updates=${plan.updateCount}, ` +
      `creates=${plan.createCount}, missing=${plan.missing.length}, missingAssets=${plan.missingAssets.length}`,
  );
  for (const item of plan.items) {
    console.log(
        `${item.id}: current=${item.currentCount}, proposed=${item.proposedCount}, ` +
        `mode=${item.mode}`,
    );
    console.log(`  concepts: ${item.concepts.join(", ")}`);
    console.log(`  paths: ${item.assetPaths.join(", ")}`);
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
      args.output || "build/reports/knowledge_centres_image_sections_update_plan.json",
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
  console.log(`Update plan report written to ${outputPath}`);
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
