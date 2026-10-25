"use strict";

const fs = require("fs");
const path = require("path");

const projectRoot = path.resolve(__dirname, "..");
const defaultInput = path.join(
  projectRoot,
  "assets",
  "seed",
  "final_exam_questions_500.json",
);
const expectedCounts = {
  theory: 150,
  technical: 250,
  psychology: 100,
};
const expectedDifficulties = new Set(["beginner", "intermediate", "advanced"]);

function validateQuestionBank(questions) {
  const issues = [];
  const seenIds = new Set();
  const seenEnglish = new Set();
  const seenChinese = new Set();
  const counts = {
    total: Array.isArray(questions) ? questions.length : 0,
    categories: {theory: 0, technical: 0, psychology: 0},
    difficulties: {beginner: 0, intermediate: 0, advanced: 0},
    englishLocales: 0,
    chineseLocales: 0,
    englishOptions: 0,
    chineseOptions: 0,
    optionOrderPairs: 0,
  };

  if (!Array.isArray(questions)) {
    return {
      passed: false,
      counts,
      issues: [issue("(file)", "root", "Question bank must be a JSON array.")],
    };
  }

  if (questions.length !== 500) {
    issues.push(issue("(file)", "count", `Expected 500 questions, found ${questions.length}.`));
  }

  for (const [index, question] of questions.entries()) {
    const pathLabel = `questions[${index}]`;
    const id = text(question.questionId);
    if (!id) {
      issues.push(issue(pathLabel, "questionId", "questionId is required."));
    } else if (seenIds.has(id)) {
      issues.push(issue(id, "questionId", "Duplicate questionId."));
    } else {
      seenIds.add(id);
    }

    if (question.examType !== "final") {
      issues.push(issue(id || pathLabel, "examType", "examType must be final."));
    }
    if (question.questionType !== "single_choice") {
      issues.push(issue(id || pathLabel, "questionType", "questionType must be single_choice."));
    }
    if (question.isActive !== true) {
      issues.push(issue(id || pathLabel, "isActive", "isActive must be true."));
    }

    const category = text(question.category);
    if (!(category in expectedCounts)) {
      issues.push(issue(id || pathLabel, "category", "category must be theory, technical, or psychology."));
    } else {
      counts.categories[category]++;
      const idPattern = new RegExp(`^final_exam_${category}_\\d{3}$`);
      if (id && !idPattern.test(id)) {
        issues.push(issue(id, "questionId", `questionId must match final_exam_${category}_001 style.`));
      }
    }

    if (!text(question.subcategory)) {
      issues.push(issue(id || pathLabel, "subcategory", "subcategory is required."));
    }

    const difficulty = text(question.difficulty);
    if (!expectedDifficulties.has(difficulty)) {
      issues.push(issue(id || pathLabel, "difficulty", "difficulty must be beginner, intermediate, or advanced."));
    } else {
      counts.difficulties[difficulty]++;
    }

    if (!Array.isArray(question.tags) || question.tags.length === 0) {
      issues.push(issue(id || pathLabel, "tags", "tags must be a non-empty array."));
    } else if (question.tags.some((tag) => !text(tag))) {
      issues.push(issue(id || pathLabel, "tags", "tags must not contain empty values."));
    }

    const correctOptionIndex = question.correctOptionIndex;
    if (!Number.isInteger(correctOptionIndex) || correctOptionIndex < 0 || correctOptionIndex > 3) {
      issues.push(issue(id || pathLabel, "correctOptionIndex", "correctOptionIndex must be 0, 1, 2, or 3."));
    }

    const en = locale(question, "en");
    const zh = locale(question, "zh");
    if (en) counts.englishLocales++;
    if (zh) counts.chineseLocales++;
    auditLocale(en, "en", id || pathLabel, issues, seenEnglish);
    auditLocale(zh, "zh", id || pathLabel, issues, seenChinese);

    if (Array.isArray(en?.options)) counts.englishOptions += en.options.length;
    if (Array.isArray(zh?.options)) counts.chineseOptions += zh.options.length;
    if (Array.isArray(en?.options) && en.options.length !== 4) {
      issues.push(issue(id || pathLabel, "locales.en.options", "English options must contain exactly 4 options."));
    }
    if (Array.isArray(zh?.options) && zh.options.length !== 4) {
      issues.push(issue(id || pathLabel, "locales.zh.options", "Chinese options must contain exactly 4 options."));
    }

    const pairKeys = question.seedMeta?.optionPairKeys;
    if (Array.isArray(pairKeys) && pairKeys.length === 4 && pairKeys.every(text)) {
      counts.optionOrderPairs++;
    } else {
      issues.push(issue(
        id || pathLabel,
        "seedMeta.optionPairKeys",
        "Option order pair keys are required to verify English/Chinese option order by meaning.",
      ));
    }

    const compatibleCorrect = countCompatibleCorrectOptions(question.options);
    if (compatibleCorrect != null && compatibleCorrect !== 1) {
      issues.push(issue(id || pathLabel, "options.*.is_correct", "Compatibility options must contain exactly one correct option."));
    }
  }

  for (const [category, expected] of Object.entries(expectedCounts)) {
    if (counts.categories[category] !== expected) {
      issues.push(issue("(file)", `category.${category}`, `Expected ${expected} ${category} questions, found ${counts.categories[category]}.`));
    }
  }

  return {
    passed: issues.length === 0,
    counts,
    issues,
  };
}

function auditLocale(value, language, owner, issues, seenQuestions) {
  if (!value || typeof value !== "object") {
    issues.push(issue(owner, `locales.${language}`, `${language} locale is required.`));
    return;
  }
  const question = text(value.question);
  if (!question) {
    issues.push(issue(owner, `locales.${language}.question`, "Question text must not be empty."));
  } else if (seenQuestions.has(question.toLowerCase())) {
    issues.push(issue(owner, `locales.${language}.question`, `Duplicate ${language} question text.`));
  } else {
    seenQuestions.add(question.toLowerCase());
  }

  const options = value.options;
  if (!Array.isArray(options)) {
    issues.push(issue(owner, `locales.${language}.options`, "Options must be an array."));
  } else {
    for (let index = 0; index < options.length; index++) {
      if (!text(options[index])) {
        issues.push(issue(owner, `locales.${language}.options[${index}]`, "Option text must not be empty."));
      }
    }
  }

  if (!text(value.explanation)) {
    issues.push(issue(owner, `locales.${language}.explanation`, "Explanation must not be empty."));
  }
}

function countCompatibleCorrectOptions(options) {
  if (!options || typeof options !== "object" || Array.isArray(options)) return null;
  let count = 0;
  for (const value of Object.values(options)) {
    if (value && typeof value === "object" && value.is_correct === true) {
      count++;
    }
  }
  return count;
}

function locale(question, language) {
  const locales = question?.locales;
  if (!locales || typeof locales !== "object") return null;
  const value = locales[language];
  return value && typeof value === "object" ? value : null;
}

function issue(owner, field, message) {
  return {owner, field, message};
}

function text(value) {
  return value == null ? "" : String(value).trim();
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

function main() {
  const args = parseArgs(process.argv.slice(2));
  const inputPath = path.resolve(projectRoot, args.input || defaultInput);
  const raw = fs.readFileSync(inputPath, "utf8");
  const questions = JSON.parse(raw);
  const result = validateQuestionBank(questions);

  console.log(`Validated ${inputPath}`);
  console.log(JSON.stringify(result.counts, null, 2));
  if (result.issues.length) {
    console.error(`Validation failed with ${result.issues.length} issue(s).`);
    for (const item of result.issues.slice(0, 50)) {
      console.error(`- ${item.owner} ${item.field}: ${item.message}`);
    }
    if (result.issues.length > 50) {
      console.error(`... ${result.issues.length - 50} more issue(s) omitted.`);
    }
    process.exitCode = 1;
    return;
  }

  console.log("Final exam question bank validation passed.");
}

if (require.main === module) {
  main();
}

module.exports = {validateQuestionBank};
