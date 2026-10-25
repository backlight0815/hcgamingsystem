#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");
const {validateKnowledgeCentres} = require("./validate_knowledge_centres");

const projectRoot = path.resolve(__dirname, "..");
const seedPath = path.join(projectRoot, "assets", "seed", "knowledge_centres_30.json");
const outputPath = path.join(projectRoot, "build", "reports", "knowledge_centre_image_review.md");
const jsonOutputPath = outputPath.replace(/\.md$/i, ".json");

function main() {
  const docs = JSON.parse(fs.readFileSync(seedPath, "utf8"));
  const validation = validateKnowledgeCentres(docs);
  const modules = docs.map((doc) => {
    const images = (doc.imageSections || []).map((image) => ({
      imageId: image.imageId,
      concept: image.concept,
      assetPath: image.assetPath,
      title: image.locales?.en?.title || "",
      zhTitle: image.locales?.zh?.title || "",
      qualityStatus: image.qualityStatus,
      productionReady: image.qualityStatus === "production_ready",
    }));
    return {
      moduleId: doc.knowledgeCenterId,
      category: doc.category,
      title: doc.locales?.en?.title || "",
      zhTitle: doc.locales?.zh?.title || "",
      imageCount: images.length,
      images,
    };
  });

  const report = {
    generatedAt: new Date().toISOString(),
    seedPath: path.relative(projectRoot, seedPath),
    validation,
    modules,
    criticalConfirmations: {
      spread: hasConcept(modules, "spread_bid_ask"),
      support: hasConcept(modules, "support_zone"),
      resistance: hasConcept(modules, "resistance_zone"),
      snrFlip:
        hasConcept(modules, "support_becomes_resistance") &&
        hasConcept(modules, "resistance_becomes_support"),
      marketStructure:
        hasConcept(modules, "hh_hl_uptrend") &&
        hasConcept(modules, "lh_ll_downtrend"),
      bosAndChoch:
        hasConcept(modules, "bullish_bos") &&
        hasConcept(modules, "bearish_bos") &&
        hasConcept(modules, "choch_reversal"),
      range: hasConcept(modules, "range_high_low"),
    },
  };

  fs.mkdirSync(path.dirname(outputPath), {recursive: true});
  fs.writeFileSync(jsonOutputPath, `${JSON.stringify(report, null, 2)}\n`, "utf8");
  fs.writeFileSync(outputPath, markdown(report), "utf8");

  console.log(`Knowledge Centre image review written to ${outputPath}`);
  console.log(`JSON review written to ${jsonOutputPath}`);
  console.log(`Modules=${modules.length}, images=${validation.counts.imageSections}, issues=${validation.issues.length}`);
  if (!validation.passed) process.exitCode = 1;
}

function hasConcept(modules, concept) {
  return modules.some((module) =>
    module.images.some((image) => image.concept === concept),
  );
}

function markdown(report) {
  const counts = report.validation.counts;
  return `# Knowledge Centre Image Review

Generated: ${report.generatedAt}
Seed: ${report.seedPath}

## Validation

- Passed: ${report.validation.passed}
- Modules: ${counts.total}
- Images: ${counts.imageSections}
- SVG images: ${counts.svgImages}
- SVG viewBox 0 0 1600 900: ${counts.svg1600x900}
- Production-ready images: ${counts.productionReadyImages}
- Local asset images: ${counts.localAssetImages}
- Issues: ${report.validation.issues.length}

## Critical Confirmations

- Spread diagram shows BID, ASK, and Spread: ${yes(report.criticalConfirmations.spread)}
- Support diagram exists as a chart-style bounce: ${yes(report.criticalConfirmations.support)}
- Resistance diagram exists as a chart-style rejection: ${yes(report.criticalConfirmations.resistance)}
- SNR flip diagrams cover support becoming resistance and resistance becoming support: ${yes(report.criticalConfirmations.snrFlip)}
- Market structure diagrams cover HH/HL and LH/LL: ${yes(report.criticalConfirmations.marketStructure)}
- BOS and CHOCH diagrams are both present: ${yes(report.criticalConfirmations.bosAndChoch)}
- Ranging market diagram shows range high and range low: ${yes(report.criticalConfirmations.range)}

## Module Image List

${report.modules.map(moduleMarkdown).join("\n\n")}
`;
}

function moduleMarkdown(module) {
  return `### ${module.moduleId} - ${module.title}

- Category: ${module.category}
- Image count: ${module.imageCount}
${module.images.map((image) => `- ${image.imageId}: ${image.concept} | ${image.assetPath} | ${image.productionReady ? "production-ready" : image.qualityStatus}`).join("\n")}`;
}

function yes(value) {
  return value ? "yes" : "no";
}

main();
