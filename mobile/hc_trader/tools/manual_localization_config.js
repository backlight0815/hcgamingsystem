"use strict";

const TARGET_LANGUAGE = "zh";
const TARGET_LANGUAGE_CODE = "zh-CN";
const META_FIELD = "translationMeta";

// Keep trading abbreviations, symbols, platform names, and risk notation intact.
const PROTECTED_TERMS = [
  "SNR",
  "BOS",
  "CHOCH",
  "FVG",
  "OB",
  "POI",
  "Risk Reward",
  "Prop Firm",
  "XAUUSD",
  "EURUSD",
  "GBPUSD",
  "USDJPY",
  "BTCUSD",
  "USDT",
  "Forex",
  "MT4",
  "MT5",
  "SL",
  "TP",
  "TP1",
  "TP2",
  "TP3",
  "RR",
];

// Paths are intentionally limited to user-facing admin content. IDs, prices,
// dates, URLs, files, statuses, permissions, and private user data stay out.
const TOP_LEVEL_COLLECTIONS = [
  "trading_resources",
  "trading_news",
  "knowledge_centres",
  "trading_signals",
  "market_analyses",
  "trading_recordings",
  "trading_blogs",
  "trading_exam_questions",
  "final_exam_questions",
  "final_exam_question_banks",
  "prop_firm_models",
];

const COLLECTION_GROUPS = {
  final_exam_attempts: "users/{userId}/final_exam_attempts/{attemptId}",
  trading_exam_attempts: "users/{userId}/trading_exam_attempts/{attemptId}",
};

const TRANSLATABLE_CONFIG = {
  trading_resources: [
    "title",
    "description",
  ],
  trading_news: [
    "title",
    "content",
    "tags[]",
  ],
  knowledge_centres: [
    "title",
    "description",
  ],
  trading_signals: [
    "title",
    "signal_title",
    "immediate_action",
    "analysis",
    "disclaimer",
    "risk_level",
  ],
  market_analyses: [
    "title",
    "market_overview",
    "trend_structure",
    "key_zones",
    "analyst_view",
    "strategy",
    "trading_plan",
    "chart_signals",
    "order_block",
    "disclaimer",
  ],
  trading_recordings: [
    "title",
    "description",
    "materials[].title",
  ],
  trading_blogs: [
    "title",
    "excerpt",
    "content",
    "tags[]",
  ],
  trading_exam_questions: [
    "question_text",
    "category",
    "section",
    "explanation",
    "options.*.option_text",
    "options.*.text",
    "options[].option_text",
    "options[].text",
  ],
  final_exam_questions: [
    "question",
    "question_text",
    "category",
    "subcategory",
    "scenario_text",
    "explanation",
    "options.*.option_text",
    "options.*.text",
    "options[].option_text",
    "options[].text",
  ],
  final_exam_question_banks: [
    "title",
    "description",
    "questions[].question",
    "questions[].question_text",
    "questions[].category",
    "questions[].subcategory",
    "questions[].scenario_text",
    "questions[].explanation",
    "questions[].options.*.option_text",
    "questions[].options.*.text",
    "questions[].options[].option_text",
    "questions[].options[].text",
  ],
  prop_firm_models: [
    "prop_firm_name",
    "name",
    "description",
    "lot_size_rules",
    "consistency_rule",
  ],
  final_exam_attempts: [
    "questions[].category",
    "questions[].subcategory",
    "questions[].question",
    "questions[].scenario_text",
    "questions[].explanation",
    "questions[].options.*",
  ],
  trading_exam_attempts: [
    "question_results[].question_text",
    "question_results[].options[]",
    "question_results[].explanation",
  ],
};

const COLLECTIONS = Object.keys(TRANSLATABLE_CONFIG);

module.exports = {
  COLLECTIONS,
  COLLECTION_GROUPS,
  META_FIELD,
  PROTECTED_TERMS,
  TARGET_LANGUAGE,
  TARGET_LANGUAGE_CODE,
  TOP_LEVEL_COLLECTIONS,
  TRANSLATABLE_CONFIG,
};
