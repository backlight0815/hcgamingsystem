"use strict";

const crypto = require("crypto");

const {
  decodeFields,
  encodeFields,
  listFinalExamQuestionDocuments,
  resolveFirebaseAuth,
} = require("./firebase_rest_helpers");

const args = parseArgs(process.argv.slice(2));
const projectId = args.project || process.env.GCLOUD_PROJECT || "hcgaming-31d5d";
const apply = args.apply === true;
const expectedCount = Number(args["expected-count"] || 50);
const expectedOptions = Number(args["expected-options"] || 4);

run().catch((error) => {
  console.error(error.message || error);
  process.exitCode = 1;
});

async function run() {
  const auth = await resolveFirebaseAuth(projectId);
  const docs = await listFinalExamQuestionDocuments({projectId, auth});
  if (docs.length !== expectedCount) {
    throw new Error(
        `Expected ${expectedCount} final_exam_questions documents, found ${docs.length}.`,
    );
  }

  const summary = {
    scanned: docs.length,
    writeMode: apply,
    questionsToUpdate: 0,
    questionTranslations: 0,
    optionTranslations: 0,
    explanationTranslations: 0,
    skippedExistingChinese: 0,
    invalidOptionCount: 0,
    invalidCorrectAnswer: 0,
    missingTranslation: [],
  };

  const updates = [];
  for (const doc of docs) {
    const update = buildUpdate(doc);
    summary.invalidOptionCount += update.invalidOptionCount;
    summary.invalidCorrectAnswer += update.invalidCorrectAnswer;
    summary.skippedExistingChinese += update.skippedExistingChinese;
    summary.questionTranslations += update.questionTranslations;
    summary.optionTranslations += update.optionTranslations;
    summary.explanationTranslations += update.explanationTranslations;
    summary.missingTranslation.push(...update.missingTranslation);
    if (Object.keys(update.fields).length) {
      summary.questionsToUpdate++;
      updates.push({doc, update});
    }
  }

  if (summary.invalidOptionCount || summary.invalidCorrectAnswer) {
    throw new Error(
        `Refusing to write: invalidOptionCount=${summary.invalidOptionCount}, ` +
        `invalidCorrectAnswer=${summary.invalidCorrectAnswer}.`,
    );
  }
  if (summary.missingTranslation.length) {
    throw new Error(
        "Refusing to write because translations are missing for: " +
        summary.missingTranslation.join("; "),
    );
  }

  console.log(JSON.stringify(summary, null, 2));
  if (!apply) {
    console.log("Dry run only. Re-run with --apply to write sidecar Chinese fields.");
    return;
  }

  for (const {doc, update} of updates) {
    await patchDocument({projectId, auth, doc, fields: update.fields});
    console.log(`[updated] ${doc.path}`);
  }
}

function buildUpdate(doc) {
  const data = doc.data;
  const question = text(data.question || data.question_text);
  const options = normalizeOptions(data.options);
  const correct = correctAnswer(data, options);
  const update = {
    fields: {},
    questionTranslations: 0,
    optionTranslations: 0,
    explanationTranslations: 0,
    skippedExistingChinese: 0,
    invalidOptionCount: options.length === expectedOptions ? 0 : 1,
    invalidCorrectAnswer: correct && options.some((option) => option.id === correct) ? 0 : 1,
    missingTranslation: [],
  };

  const questionZh = translate(question);
  if (question && !questionZh) {
    update.missingTranslation.push(`${doc.path} question: ${question}`);
  } else if (question && !hasUsableChinese(data.question_i18n, question)) {
    update.fields.question_i18n = {
      ...localizedMap(data.question_i18n),
      en: question,
      zh: questionZh,
    };
    update.questionTranslations++;
  } else {
    update.skippedExistingChinese++;
  }

  const optionsI18n = isPlainObject(data.options_i18n) ?
    {...data.options_i18n} :
    {};
  for (const option of options) {
    const zh = translate(option.text);
    if (!zh) {
      update.missingTranslation.push(`${doc.path} option ${option.id}: ${option.text}`);
      continue;
    }
    if (hasUsableChinese(optionsI18n[option.id] || option.raw, option.text)) {
      update.skippedExistingChinese++;
      continue;
    }
    optionsI18n[option.id] = {
      ...localizedMap(optionsI18n[option.id]),
      en: option.text,
      zh,
    };
    update.optionTranslations++;
  }
  if (update.optionTranslations) {
    update.fields.options_i18n = optionsI18n;
  }

  const explanation = text(data.explanation || data.answer_explanation);
  if (explanation) {
    const explanationZh = translate(explanation);
    if (!explanationZh) {
      update.missingTranslation.push(`${doc.path} explanation: ${explanation}`);
    } else if (!hasUsableChinese(data.explanation_i18n, explanation)) {
      update.fields.explanation_i18n = {
        ...localizedMap(data.explanation_i18n),
        en: explanation,
        zh: explanationZh,
      };
      update.explanationTranslations++;
    } else {
      update.skippedExistingChinese++;
    }
  }

  const sourceHash = crypto
      .createHash("sha256")
      .update(JSON.stringify({
        question,
        options: options.map((option) => [option.id, option.text]),
        explanation,
      }))
      .digest("hex");
  if (Object.keys(update.fields).length) {
    update.fields.translationMeta = {
      ...(isPlainObject(data.translationMeta) ? data.translationMeta : {}),
      zh: {
        status: "completed",
        provider: "manual_codex_static_map",
        translatedAt: new Date().toISOString(),
        sourceHash,
        autoGenerated: false,
        manualOverride: true,
        translatedQuestionCount: update.questionTranslations,
        translatedOptionCount: update.optionTranslations,
        translatedExplanationCount: update.explanationTranslations,
        missingFields: [],
        failedFields: [],
      },
    };
  }

  return update;
}

async function patchDocument({projectId, auth, doc, fields}) {
  const documentId = encodeURIComponent(doc.id);
  const url = new URL(
      `https://firestore.googleapis.com/v1/projects/${projectId}` +
      `/databases/(default)/documents/final_exam_questions/${documentId}`,
  );
  for (const field of Object.keys(fields)) {
    url.searchParams.append("updateMask.fieldPaths", field);
  }
  const response = await fetch(url, {
    method: "PATCH",
    headers: {
      Authorization: `Bearer ${auth.accessToken}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify({fields: encodeFields(fields)}),
  });
  if (!response.ok) {
    throw new Error(
        `Failed to update ${doc.path}: ${response.status} ${await response.text()}`,
    );
  }
}

function normalizeOptions(rawOptions) {
  if (!isPlainObject(rawOptions)) return [];
  return Object.entries(rawOptions)
      .sort((left, right) => String(left[0]).localeCompare(String(right[0])))
      .map(([id, raw]) => ({
        id: String(id).toUpperCase(),
        raw,
        text: isPlainObject(raw) ? text(raw.option_text || raw.text || raw.en || raw.value) : text(raw),
      }));
}

function correctAnswer(data, options) {
  const direct =
    data.answer_key ||
    data.correct_answer ||
    data.correct_option ||
    data.correct_option_key;
  if (direct != null && String(direct).trim()) {
    return String(direct).trim().toUpperCase();
  }
  for (const option of options) {
    if (option.raw?.is_correct === true) return option.id;
  }
  return "";
}

function hasUsableChinese(value, english) {
  const zh = localizedZh(value);
  return Boolean(zh && hasCjk(zh) && zh !== english);
}

function localizedMap(value) {
  if (!isPlainObject(value)) return {};
  const map = {};
  const en = text(value.en || value.english);
  const zh = text(value.zh || value.chinese);
  if (en) map.en = en;
  if (zh) map.zh = zh;
  return map;
}

function localizedZh(value) {
  if (!isPlainObject(value)) return "";
  return text(value.zh || value.chinese);
}

function translate(english) {
  const normalized = text(english).replace(/\s+/g, " ");
  if (!normalized) return "";
  if (EXACT_ZH[normalized]) return EXACT_ZH[normalized];

  const bestDescribe = /^What best describes (.+)\?$/i.exec(normalized);
  if (bestDescribe) {
    return `以下哪项最能描述${termZh(bestDescribe[1])}？`;
  }

  const bestPractice = /^What is best practice for (.+)\?$/i.exec(normalized);
  if (bestPractice) {
    return `关于${termZh(bestPractice[1])}，最佳做法是什么？`;
  }

  return "";
}

function termZh(english) {
  return TERM_ZH[english.toLowerCase()] || english;
}

function text(value) {
  return value == null ? "" : String(value).trim();
}

function hasCjk(value) {
  return /[\u3400-\u9fff]/.test(String(value || ""));
}

function isPlainObject(value) {
  return !!value && typeof value === "object" && !Array.isArray(value);
}

function parseArgs(rawArgs) {
  const parsed = {};
  for (const arg of rawArgs) {
    if (!arg.startsWith("--")) continue;
    const withoutPrefix = arg.slice(2);
    const equals = withoutPrefix.indexOf("=");
    if (equals === -1) {
      parsed[withoutPrefix] = true;
    } else {
      parsed[withoutPrefix.slice(0, equals)] = withoutPrefix.slice(equals + 1);
    }
  }
  return parsed;
}

const TERM_ZH = {
  "position size": "仓位大小",
  "leverage": "杠杆",
  "margin": "保证金",
  "drawdown": "回撤",
  "risk-to-reward": "风险回报比",
  "trading journal": "交易日志",
  "spread": "点差",
  "slippage": "滑点",
  "high-impact news": "高影响新闻",
  "support": "支撑",
  "resistance": "阻力",
  "breakout": "突破",
  "liquidity sweep": "流动性扫荡",
  "higher low": "更高低点",
  "lower high": "更低高点",
  "confirmation candle": "确认K线",
  "breakeven": "保本",
  "scaling out": "分批止盈",
  "no-trade condition": "不交易条件",
  "revenge trading": "报复性交易",
  "fomo": "错失恐惧",
  "overconfidence": "过度自信",
  "daily loss limit": "每日亏损限制",
  "consistency": "一致性",
};

const EXACT_ZH = {
  "What is the primary purpose of a stop loss?": "止损的主要目的是什么？",
  "To guarantee profit": "保证盈利",
  "To limit loss when the setup is invalidated": "在交易设定失效时限制亏损",
  "To increase leverage": "增加杠杆",
  "To avoid planning": "避免做计划",
  "A stop loss defines the maximum planned loss when the trade idea is wrong.":
    "止损用于在交易思路错误时定义最大计划亏损。",
  "What is disciplined trading?": "什么是有纪律的交易？",
  "Following a plan": "遵循计划",
  "Increasing risk": "增加风险",
  "Removing stops": "移除止损",
  "Chasing losses": "追亏",
  "controls trade exposure": "控制交易敞口",
  "increase risk without a plan": "无计划地增加风险",
  "ignore setup invalidation": "忽视交易设定的失效条件",
  "decide from emotion only": "只凭情绪决定",
  "calculate it from risk and stop distance": "根据风险和止损距离计算仓位",
  "increases market exposure": "增加市场敞口",
  "use it without increasing planned risk": "在不增加计划风险的情况下使用杠杆",
  "supports an open leveraged position": "支撑已开立的杠杆仓位",
  "keep sufficient free margin": "保持充足的可用保证金",
  "measures decline from an account peak": "衡量账户从峰值回落的幅度",
  "reduce risk when limits are approached": "接近限制时降低风险",
  "compares potential reward with loss": "比较潜在回报与潜在亏损",
  "require adequate reward for the risk": "要求风险对应足够的回报",
  "records decisions and outcomes": "记录决策和结果",
  "review it for repeated behavior": "复盘其中的重复行为",
  "is the gap between bid and ask": "是买价和卖价之间的差距",
  "include it in entry and stop planning": "在入场和止损计划中计入点差",
  "is the difference between expected and filled price": "是预期价格与实际成交价格之间的差异",
  "expect it in fast markets": "在快速行情中预期会出现滑点",
  "can sharply increase volatility": "会大幅提高波动性",
  "check the calendar before entry": "入场前查看财经日历",
  "is an area where buyers reacted": "是买方曾经反应的区域",
  "wait for confirmation at the zone": "在该区域等待确认",
  "is an area where sellers reacted": "是卖方曾经反应的区域",
  "is a decisive move beyond a level": "是价格明确突破某一价位的走势",
  "confirm the close and possible retest": "确认收盘质量和可能的回踩",
  "runs beyond obvious highs or lows": "扫过明显高点或低点",
  "wait for reclaim before reversal": "等待价格重新收回后再考虑反转",
  "supports bullish structure": "支撑看涨结构",
  "invalidate the idea if it breaks": "若它被打破，则该思路失效",
  "supports bearish structure": "支撑看跌结构",
  "closes in favor of a setup": "按照交易设定方向收盘",
  "wait for its close when required": "需要确认时等待K线收盘",
  "protects a trade near entry": "在入场附近保护交易",
  "move the stop only after price earns it": "只有价格走出足够空间后才移动止损",
  "closes part of a position": "平掉部分仓位",
  "take partials only by plan": "只按计划分批止盈",
  "is a planned reason to stay out": "是计划中不进场的理由",
  "protect capital when evidence is weak": "证据较弱时保护资金",
  "is emotional trading after a loss": "是亏损后的情绪化交易",
  "pause and keep normal risk": "暂停交易并保持正常风险",
  "is fear of missing a move": "是害怕错过行情",
  "wait for the next valid setup": "等待下一个有效交易设定",
  "can reduce discipline after wins": "盈利后可能削弱纪律",
  "keep normal risk and full confirmation": "保持正常风险并等待完整确认",
  "caps loss for one trading day": "限制单个交易日的亏损",
  "stop before breaching the rule": "在触犯规则前停止交易",
  "means repeating a sound process": "意味着重复良好的交易流程",
  "Why should a trader follow a written plan?": "交易者为什么应该遵循书面交易计划？",
  "To improve consistency": "提高一致性",
  "To chase losses": "追回亏损",
  "To remove stops": "移除止损",
  "To increase emotional decisions": "增加情绪化决策",
};
