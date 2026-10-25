#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");

const projectRoot = path.resolve(__dirname, "..");
const defaultInput = path.join(projectRoot, "assets", "seed", "knowledge_centres_30.json");
const allowedCategories = new Set(["theory", "technical", "psychology"]);
const expectedCounts = {theory: 9, technical: 15, psychology: 6};
const expectedModuleIds = new Set([
  "KC_THEORY_001", "KC_THEORY_002", "KC_THEORY_003",
  "KC_THEORY_004", "KC_THEORY_005", "KC_THEORY_006",
  "KC_THEORY_007", "KC_THEORY_008", "KC_THEORY_009",
  "KC_TECHNICAL_001", "KC_TECHNICAL_002", "KC_TECHNICAL_003",
  "KC_TECHNICAL_004", "KC_TECHNICAL_005", "KC_TECHNICAL_006",
  "KC_TECHNICAL_007", "KC_TECHNICAL_008", "KC_TECHNICAL_009",
  "KC_TECHNICAL_010", "KC_TECHNICAL_011", "KC_TECHNICAL_012",
  "KC_TECHNICAL_013", "KC_TECHNICAL_014", "KC_TECHNICAL_015",
  "KC_PSYCHOLOGY_001", "KC_PSYCHOLOGY_002", "KC_PSYCHOLOGY_003",
  "KC_PSYCHOLOGY_004", "KC_PSYCHOLOGY_005", "KC_PSYCHOLOGY_006",
]);
const cjk = /[\u3400-\u9fff]/;
const criticalSvgChecks = {
  spread_bid_ask: ["BID", "ASK", "Spread", "2 pips"],
  support_zone: ["S/R"],
  resistance_zone: ["S/R"],
  support_becomes_resistance: ["S -&gt; R"],
  resistance_becomes_support: ["R -&gt; S"],
  body_wick_key_level_zone: ["BODY/WICK ZONE"],
  buy_limit_versus_buy_stop: ["Buy Limit", "Current", "Buy Stop"],
  buy_limit_buy_stop: ["Buy Limit", "Current", "Buy Stop"],
  sell_limit_versus_sell_stop: ["Sell Limit", "Current", "Sell Stop"],
  sell_limit_sell_stop: ["Sell Limit", "Current", "Sell Stop"],
  one_to_two_risk_reward: ["Risk 1R", "Reward 2R", "SL", "TP"],
  risk_reward_1_to_2: ["Risk 1R", "Reward 2R", "SL", "TP"],
  risk_reward_execution: ["Risk 1R", "Reward 2R", "SL", "TP"],
  bullish_bos: ["BOS", "Prev High"],
  bearish_bos: ["BOS", "Prev Low"],
  choch_reversal: ["CHOCH"],
  range_high_and_range_low: ["Range High", "Range Low"],
  range_high_low: ["Range High", "Range Low"],
};
const allowedEnglishTerms = new Set([
  "Forex", "CFD", "EMA", "RSI", "MACD", "FOMC", "CPI", "NFP", "BOS",
  "CHOCH", "SNR", "POI", "H4", "H1", "M15", "SL", "TP", "ADX", "FOMO",
  "Prop Firm", "Buy Limit", "Sell Limit", "Buy Stop", "Sell Stop",
]);

function validateKnowledgeCentres(docs, {assetRoot = projectRoot} = {}) {
  const issues = [];
  const ids = new Set();
  const counts = {
    total: docs.length,
    categories: {theory: 0, technical: 0, psychology: 0},
    modulesWithEnglish: 0,
    modulesWithChinese: 0,
    productionReadyModules: 0,
    contentVersion2Modules: 0,
    imageSections: 0,
    localAssetImages: 0,
    productionReadyImages: 0,
    svgImages: 0,
    svg1600x900: 0,
    technicalModulesWithPreferredImages: 0,
  };

  if (docs.length !== 30) {
    issue(issues, "(root)", "count", `Expected exactly 30 modules, found ${docs.length}.`);
  }

  for (const [index, doc] of docs.entries()) {
    const owner = doc.knowledgeCenterId || doc.id || `index:${index}`;
    requiredString(issues, owner, "knowledgeCenterId", doc.knowledgeCenterId);
    if (doc.knowledgeCenterId && !ids.add(doc.knowledgeCenterId)) {
      issue(issues, owner, "knowledgeCenterId", "Duplicate knowledgeCenterId.");
    }
    if (doc.knowledgeCenterId && !expectedModuleIds.has(doc.knowledgeCenterId)) {
      issue(issues, owner, "knowledgeCenterId", "Unexpected Knowledge Centre module ID.");
    }
    if (!allowedCategories.has(doc.category)) {
      issue(issues, owner, "category", "Category must be theory, technical, or psychology.");
    } else {
      counts.categories[doc.category] += 1;
    }
    if (!Number.isFinite(Number(doc.order))) {
      issue(issues, owner, "order", "Order must be a number.");
    }
    if (doc.contentVersion === 2) {
      counts.contentVersion2Modules += 1;
    } else {
      issue(issues, owner, "contentVersion", "contentVersion must be 2.");
    }
    if (doc.qualityStatus === "production_ready") {
      counts.productionReadyModules += 1;
    } else {
      issue(issues, owner, "qualityStatus", "qualityStatus must be production_ready.");
    }
    if (doc.futureFinalExamMappingReady !== true) {
      issue(issues, owner, "futureFinalExamMappingReady", "Future final exam mapping flag must be true.");
    }
    if (!Array.isArray(doc.mappedQuestionIds)) {
      issue(issues, owner, "mappedQuestionIds", "mappedQuestionIds must be an array.");
    }
    if (!Number.isFinite(Number(doc.relatedQuestionTarget))) {
      issue(issues, owner, "relatedQuestionTarget", "relatedQuestionTarget must be numeric.");
    }

    const enOk = validateLocale(issues, owner, doc.locales?.en, "en");
    const zhOk = validateLocale(issues, owner, doc.locales?.zh, "zh");
    if (enOk) counts.modulesWithEnglish += 1;
    if (zhOk) counts.modulesWithChinese += 1;

    const images = Array.isArray(doc.imageSections) ? doc.imageSections : [];
    if (!Array.isArray(doc.imageSections)) {
      issue(issues, owner, "imageSections", "imageSections must be an array.");
    }
    if (images.length < 2) {
      issue(issues, owner, "imageSections", "Every module must have at least 2 images.");
    }
    if (doc.category === "technical") {
      if (images.length >= 3 && images.length <= 5) {
        counts.technicalModulesWithPreferredImages += 1;
      } else {
        issue(issues, owner, "imageSections", "Technical modules should have 3 to 5 images.");
      }
    }
    counts.imageSections += images.length;
    const imageIds = new Set();
    const concepts = new Set();
    for (const [imageIndex, image] of images.entries()) {
      if (image.imageId && !imageIds.add(image.imageId)) {
        issue(issues, owner, "imageSections", `Duplicate imageId: ${image.imageId}`);
      }
      if (image.concept && !concepts.add(image.concept)) {
        issue(issues, owner, "imageSections", `Duplicate concept: ${image.concept}`);
      }
      validateImage(issues, `${owner}.imageSections[${imageIndex}]`, image, assetRoot, counts);
    }
  }

  for (const expectedId of expectedModuleIds) {
    if (!ids.has(expectedId)) {
      issue(issues, "(root)", "knowledgeCenterId", `Missing module ${expectedId}.`);
    }
  }

  for (const [category, expected] of Object.entries(expectedCounts)) {
    if (counts.categories[category] !== expected) {
      issue(
          issues,
          "(root)",
          `categories.${category}`,
          `Expected ${expected} ${category} modules, found ${counts.categories[category]}.`,
      );
    }
  }

  return {passed: issues.length === 0, issues, counts};
}

function validateLocale(issues, owner, locale, code) {
  if (!locale || typeof locale !== "object" || Array.isArray(locale)) {
    issue(issues, owner, `locales.${code}`, `${code} locale is required.`);
    return false;
  }
  let ok = true;
  for (const field of [
    "title",
    "shortDescription",
    "beginnerExplanation",
    "practicalExplanation",
    "fullContent",
  ]) {
    ok = requiredString(issues, owner, `locales.${code}.${field}`, locale[field]) && ok;
    if (code === "zh") {
      ok = requireChinese(issues, owner, `locales.${code}.${field}`, locale[field]) && ok;
    }
  }
  if (code === "en" && wordCount(locale.fullContent) < 900) {
    issue(
        issues,
        owner,
        "locales.en.fullContent",
        `English fullContent must be at least 900 words, found ${wordCount(locale.fullContent)}.`,
    );
    ok = false;
  }
  if (code === "zh" && cjkCount(locale.fullContent) < 650) {
    issue(
        issues,
        owner,
        "locales.zh.fullContent",
        `Chinese fullContent must be detailed, found ${cjkCount(locale.fullContent)} CJK characters.`,
    );
    ok = false;
  }
  const listMinimums = {
    learningObjectives: 3,
    stepByStepGuide: 4,
    keyPoints: 5,
    examples: 3,
    commonMistakes: 4,
    checklist: 5,
    relatedConcepts: 3,
  };
  for (const [field, minimum] of Object.entries(listMinimums)) {
    if (!Array.isArray(locale[field]) || locale[field].length < minimum) {
      issue(issues, owner, `locales.${code}.${field}`, `${field} must have at least ${minimum} item(s).`);
      ok = false;
      continue;
    }
    for (const [index, value] of locale[field].entries()) {
      ok = requiredString(issues, owner, `locales.${code}.${field}[${index}]`, value) && ok;
      if (code === "zh") {
        ok = requireChinese(issues, owner, `locales.${code}.${field}[${index}]`, value) && ok;
      }
    }
  }
  return ok;
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

function validateImage(issues, owner, image, assetRoot, counts) {
  requiredString(issues, owner, "imageId", image.imageId);
  requiredString(issues, owner, "type", image.type);
  requiredString(issues, owner, "concept", image.concept);
  requiredString(issues, owner, "qualityStatus", image.qualityStatus);
  if (image.qualityStatus === "production_ready") {
    counts.productionReadyImages += 1;
  } else {
    issue(issues, owner, "qualityStatus", "qualityStatus must be production_ready.");
  }
  if (!Number.isFinite(Number(image.sortOrder)) || Number(image.sortOrder) <= 0) {
    issue(issues, owner, "sortOrder", "sortOrder must be a positive number.");
  }
  if (!image.assetPath && !image.storagePath) {
    issue(issues, owner, "assetPath", "Each image must have assetPath or storagePath.");
  }
  if (image.assetPath) {
    const basename = path.basename(String(image.assetPath));
    if (/^(image|chart|trading|sample|dummy)\d*\./i.test(basename)) {
      issue(issues, owner, "assetPath", "Image file name must be descriptive.");
    }
    const assetPath = path.resolve(assetRoot, image.assetPath);
    if (!fs.existsSync(assetPath)) {
      issue(issues, owner, "assetPath", `Local asset does not exist: ${image.assetPath}`);
    } else {
      counts.localAssetImages += 1;
      validateAssetFile(issues, owner, image, assetPath, counts);
    }
  }
  for (const code of ["en", "zh"]) {
    const locale = image.locales?.[code];
    if (!locale || typeof locale !== "object") {
      issue(issues, owner, `locales.${code}`, `${code} image locale is required.`);
      continue;
    }
    for (const field of ["title", "caption", "description", "altText"]) {
      requiredString(issues, owner, `locales.${code}.${field}`, locale[field]);
      if (code === "zh") {
        requireChinese(issues, owner, `locales.${code}.${field}`, locale[field]);
      }
    }
  }
}

function validateAssetFile(issues, owner, image, assetPath, counts) {
  const extension = path.extname(assetPath).toLowerCase();
  if (extension !== ".svg" && extension !== ".png") {
    issue(issues, owner, "assetPath", "Image must be SVG or PNG.");
    return;
  }

  if (extension === ".svg") {
    counts.svgImages += 1;
    const svg = fs.readFileSync(assetPath, "utf8");
    if (!/viewBox="0 0 1600 900"/.test(svg)) {
      issue(issues, owner, "assetPath", "SVG must use viewBox 0 0 1600 900.");
    } else {
      counts.svg1600x900 += 1;
    }
    if (!/<text\b/.test(svg)) {
      issue(issues, owner, "assetPath", "SVG must include readable labels.");
    }
    if (!/<(rect|path|polyline|line)\b/.test(svg)) {
      issue(issues, owner, "assetPath", "SVG must include educational diagram shapes.");
    }
    const concept = String(image.concept || "");
    const requiredSnippets = criticalSvgChecks[concept] || [];
    for (const snippet of requiredSnippets) {
      if (!svg.includes(snippet)) {
        issue(
            issues,
            owner,
            "assetPath",
            `Critical diagram for ${concept} is missing visible marker: ${snippet}`,
        );
      }
    }
    if (concept === "spread_bid_ask") {
      const metadata = [
        image.locales?.en?.title,
        image.locales?.en?.caption,
        image.locales?.en?.description,
        image.locales?.en?.altText,
      ].join(" ");
      for (const term of ["BID", "ASK", "Spread"]) {
        if (!metadata.toLowerCase().includes(term.toLowerCase())) {
          issue(issues, owner, "locales.en", `Spread metadata must mention ${term}.`);
        }
      }
    }
  }
}

function requiredString(issues, owner, field, value) {
  if (typeof value !== "string" || value.trim().length === 0) {
    issue(issues, owner, field, "Required text field is empty.");
    return false;
  }
  return true;
}

function requireChinese(issues, owner, field, value) {
  const text = String(value || "").trim();
  if (!text) return false;
  if (cjk.test(text)) return true;
  const stripped = [...allowedEnglishTerms].reduce(
      (result, term) => result.replaceAll(term, ""),
      text,
  );
  if (!/[A-Za-z]{4,}/.test(stripped)) return true;
  issue(issues, owner, field, "Chinese field appears to be English-only.");
  return false;
}

function issue(issues, owner, field, message) {
  issues.push({owner, field, message});
}

function loadQuestions(inputPath) {
  return JSON.parse(fs.readFileSync(inputPath, "utf8"));
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

if (require.main === module) {
  const args = parseArgs(process.argv.slice(2));
  const inputPath = path.resolve(projectRoot, args.input || defaultInput);
  const docs = loadQuestions(inputPath);
  const validation = validateKnowledgeCentres(docs);
  console.log(`Validated ${inputPath}`);
  console.log(JSON.stringify(validation.counts, null, 2));
  if (!validation.passed) {
    for (const item of validation.issues.slice(0, 50)) {
      console.error(`${item.owner} ${item.field}: ${item.message}`);
    }
    if (validation.issues.length > 50) {
      console.error(`...and ${validation.issues.length - 50} more issue(s).`);
    }
    process.exitCode = 1;
  } else {
    console.log("Knowledge Centre validation passed.");
  }
}

module.exports = {
  validateKnowledgeCentres,
};
