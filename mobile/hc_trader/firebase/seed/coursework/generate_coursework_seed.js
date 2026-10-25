#!/usr/bin/env node
"use strict";

const crypto = require("node:crypto");
const fs = require("node:fs");
const path = require("node:path");
const {categories} = require("./curriculum");

const SEED_VERSION = "coursework_100_themes_v1";
const GENERATED_AT = "2026-07-15T00:00:00.000Z";
const root = __dirname;
const themeDir = path.join(root, "themes");
const keyDir = path.join(root, "answer_keys");

const instruments = [
  {symbol: "XAUUSD", timeframe: "H1", base: 2328, volatility: 4.2, decimals: 2},
  {symbol: "EURUSD", timeframe: "H1", base: 1.084, volatility: .0032, decimals: 5},
  {symbol: "GBPUSD", timeframe: "H1", base: 1.272, volatility: .0041, decimals: 5},
  {symbol: "USDJPY", timeframe: "H1", base: 151.2, volatility: .42, decimals: 3},
  {symbol: "AUDUSD", timeframe: "H1", base: .662, volatility: .0028, decimals: 5},
  {symbol: "US30", timeframe: "H1", base: 39200, volatility: 105, decimals: 1},
  {symbol: "NAS100", timeframe: "H1", base: 19400, volatility: 78, decimals: 1},
];

const q1Templates = [
  ["Which evidence most reliably shows that {focus} is being applied correctly?", "哪项证据最可靠地表明已正确应用{focusZh}？"],
  ["Which decision rule is most consistent with disciplined use of {focus}?", "哪项决策规则最符合有纪律地运用{focusZh}？"],
  ["What should a learner verify first when evaluating {focus}?", "评估{focusZh}时，学习者首先应核实什么？"],
  ["Which statement best describes a risk-aware approach to {focus}?", "哪项陈述最能说明对{focusZh}采取风险意识的方法？"],
  ["Which observation provides the strongest objective basis for {focus}?", "哪项观察为{focusZh}提供最强的客观依据？"],
  ["How should {focus} influence a planned trading decision?", "{focusZh}应如何影响有计划的交易决策？"],
  ["Which practice prevents {focus} from becoming a subjective guess?", "哪项做法可防止{focusZh}变成主观猜测？"],
  ["Which method produces the most auditable conclusion about {focus}?", "哪种方法能够对{focusZh}得出最可审核的结论？"],
  ["Which combination of evidence is most appropriate for assessing {focus}?", "评估{focusZh}时，哪种证据组合最合适？"],
  ["What distinguishes professional analysis of {focus} from signal chasing?", "对{focusZh}的专业分析与追逐信号有何区别？"],
];

const q2Templates = [
  ["A trader finds evidence for {focus}, but the planned invalidation has already been breached. What is the best response?", "交易者发现支持{focusZh}的证据，但计划失效条件已经被突破。最佳做法是什么？"],
  ["Two signals related to {focus} conflict before entry. Which response best protects decision quality?", "入场前与{focusZh}相关的两个信号互相冲突。哪种做法最能保护决策质量？"],
  ["A setup involving {focus} offers poor risk-to-reward after costs. What should the learner do?", "涉及{focusZh}的形态在计入成本后风险回报不佳。学习者应怎么做？"],
  ["The market moves quickly before confirmation of {focus}. Which action follows a professional plan?", "在{focusZh}获得确认前市场快速波动。哪项行动符合专业计划？"],
  ["A previous example of {focus} was profitable, but current evidence is incomplete. What is the sound conclusion?", "之前的{focusZh}案例曾盈利，但当前证据不完整。合理结论是什么？"],
  ["A learner wants to increase size because {focus} appears convincing. What must happen first?", "学习者因{focusZh}看似有说服力而想增加仓位。首先必须做什么？"],
  ["Price invalidates the original interpretation of {focus} immediately after entry. What preserves process discipline?", "价格在入场后立即使原有{focusZh}解读失效。怎样做才能保持流程纪律？"],
  ["Higher- and lower-timeframe evidence disagree about {focus}. What is the most defensible response?", "高低时间周期对{focusZh}的证据不一致。哪种反应最合理？"],
  ["A chart resembles a textbook example of {focus}, but the location is poor. How should it be treated?", "图表看似{focusZh}的教科书案例，但位置不佳。应如何处理？"],
  ["A trader cannot explain the risk boundary for {focus}. What does this imply about readiness to enter?", "交易者无法解释{focusZh}的风险边界。这说明入场准备程度如何？"],
];

function localized(en, zh) {
  return {en, zh};
}

function substitute(template, focus, focusZh) {
  return template.replaceAll("{focus}", focus).replaceAll("{focusZh}", focusZh);
}

function rotate(values, amount) {
  const offset = amount % values.length;
  return [...values.slice(offset), ...values.slice(0, offset)];
}

function choiceOptions(topic, index, application = false) {
  const correct = application
    ? localized(
      "Pause the decision, re-check the written rules and accept the trade only if evidence, invalidation and risk remain valid.",
      "暂停决策，重新核对书面规则，只有在证据、失效条件与风险仍然有效时才接受交易。",
    )
    : localized(
      `Use observable market evidence, a written decision rule and a defined invalidation when assessing ${topic.title.en.toLowerCase()}.`,
      `评估${topic.title.zh}时，使用可观察的市场证据、书面决策规则与明确的失效条件。`,
    );
  const distractors = application
    ? [
      localized("Enter immediately so the opportunity is not missed, then decide the stop later.", "立即入场以免错失机会，之后再决定止损。"),
      localized("Increase risk because conflicting evidence creates a larger potential reward.", "由于证据冲突带来更大潜在回报，因此增加风险。"),
      localized("Ignore the invalidation if the most recent candle still supports the preferred direction.", "如果最新K线仍支持偏好方向，就忽略失效条件。"),
    ]
    : [
      localized("Treat the most recent candle as sufficient evidence and ignore the surrounding structure.", "将最新一根K线视为充分证据，并忽略周围结构。"),
      localized("Choose the interpretation that permits the largest position and shortest stop loss.", "选择能够使用最大仓位和最短止损的解读。"),
      localized("Assume a familiar-looking pattern will repeat without defining a failure condition.", "假设熟悉的形态会重复，而不定义失败条件。"),
    ];
  const correctAt = (index + (application ? 2 : 1)) % 4;
  const ordered = [...distractors];
  ordered.splice(correctAt, 0, correct);
  return {
    options: ordered.map((text, optionIndex) => ({
      id: String.fromCharCode(65 + optionIndex),
      text,
    })),
    correctOptionIds: [String.fromCharCode(65 + correctAt)],
  };
}

function round(value, decimals) {
  const factor = 10 ** decimals;
  return Math.round(value * factor) / factor;
}

function chartScenario(index) {
  const instrument = instruments[index % instruments.length];
  const candles = [];
  let previous = instrument.base * (1 + ((index % 9) - 4) * .003);
  const direction = index % 3 === 0 ? -.22 : index % 3 === 1 ? .24 : .03;
  for (let candleIndex = 0; candleIndex < 40; candleIndex++) {
    const open = previous;
    const wave = Math.sin((candleIndex + index * .37) * .63) * .52;
    const micro = Math.cos((candleIndex + index) * 1.17) * .17;
    const close = open + instrument.volatility * (wave + micro + direction);
    const upper = instrument.volatility * (.22 + Math.abs(Math.sin(candleIndex * .91 + index)) * .31);
    const lower = instrument.volatility * (.22 + Math.abs(Math.cos(candleIndex * .77 + index)) * .29);
    candles.push({
      timeIndex: candleIndex,
      open: round(open, instrument.decimals),
      high: round(Math.max(open, close) + upper, instrument.decimals),
      low: round(Math.min(open, close) - lower, instrument.decimals),
      close: round(close, instrument.decimals),
    });
    previous = close;
  }
  const lowest = Math.min(...candles.map((item) => item.low));
  const highest = Math.max(...candles.map((item) => item.high));
  const span = highest - lowest;
  const zones = [
    [lowest + span * .04, lowest + span * .18],
    [lowest + span * .28, lowest + span * .42],
    [lowest + span * .58, lowest + span * .72],
    [lowest + span * .82, lowest + span * .96],
  ].map(([minimumPrice, maximumPrice], zoneIndex) => ({
    id: `zone_${String.fromCharCode(97 + zoneIndex)}`,
    minimumPrice: round(minimumPrice, instrument.decimals),
    maximumPrice: round(maximumPrice, instrument.decimals),
    label: String.fromCharCode(65 + zoneIndex),
  }));
  return {
    publicData: {
      sourceType: "seeded_ohlc",
      symbol: instrument.symbol,
      timeframe: instrument.timeframe,
      displayTimezone: "UTC",
      isSyntheticEducationalData: true,
      candles,
      zones,
    },
    correctZoneIds: [zones[index % zones.length].id],
  };
}

function rubric(criteria) {
  return criteria.map(([id, en, zh, maximumMarks]) => ({
    id,
    criterion: localized(en, zh),
    maximumMarks,
  }));
}

function selectionRubric(maximumMarks) {
  return rubric([["correct_selection", "Selects the correct answer using the stated decision rule", "按照规定的决策规则选择正确答案", maximumMarks]]);
}

function questionBase(themeId, order, type, title, prompt, instructions, marks) {
  return {
    id: `Q${order}`,
    themeId,
    order,
    type,
    title,
    prompt,
    instructions,
    marks,
    answerMode: type.includes("single_choice") ? "single_choice" : type === "chart_interpretation" ? "zone_selection" : "long_text",
    options: [],
    requiresText: false,
    requiresImage: false,
    requiresChart: type === "chart_interpretation",
    minimumCharacters: 0,
    maximumCharacters: 0,
    minimumImages: 0,
    maximumImages: 0,
    allowedFileTypes: [],
    isRequired: true,
    status: "published",
    version: 1,
    seedVersion: SEED_VERSION,
    managedBySeed: true,
  };
}

function makeTheme(topic, categoryInfo, globalIndex, categoryIndex) {
  const number = globalIndex + 1;
  const id = `CW-${String(number).padStart(3, "0")}`;
  const difficulty = number <= 30 ? "beginner" : number <= 75 ? "intermediate" : "advanced";
  const difficultyOrder = difficulty === "beginner" ? 1 : difficulty === "intermediate" ? 2 : 3;
  const focus = topic.title.en.toLowerCase();
  const chart = chartScenario(globalIndex);
  const q1Choice = choiceOptions(topic, globalIndex, false);
  const q2Choice = choiceOptions(topic, globalIndex, true);
  const objectiveEn = [
    `Explain the core decision rules behind ${focus}.`,
    `Apply ${focus} to chart evidence without promising a trading outcome.`,
    `Document invalidation and risk controls before demonstrating the skill.`,
  ];
  const objectiveZh = [
    `解释${topic.title.zh}背后的核心决策规则。`,
    `将${topic.title.zh}应用于图表证据，同时不承诺交易结果。`,
    `在展示技能前记录失效条件与风险控制。`,
  ];
  const keywords = [...new Set([...topic.keywords, chart.publicData.symbol, categoryInfo.name.en])];
  const searchTokens = [...new Set([
    id.toLowerCase(), id, categoryInfo.id, categoryInfo.name.en.toLowerCase(), categoryInfo.name.zh,
    ...topic.title.en.toLowerCase().replace(/[^a-z0-9]+/g, " ").split(" ").filter(Boolean),
    topic.title.zh, ...keywords.flatMap((value) => String(value).toLowerCase().split(/\s+/)),
  ])];
  const description = localized(
    `This coursework evaluates how a learner explains, analyses and demonstrates ${focus} using objective chart evidence, explicit invalidation and controlled risk.`,
    `本课程作业评估学习者如何运用客观图表证据、明确失效条件与受控风险来解释、分析并展示${topic.title.zh}。`,
  );
  const themeDocument = {
    id,
    code: id,
    title: topic.title,
    shortTitle: localized(topic.keywords[0], topic.title.zh),
    description,
    categoryId: categoryInfo.id,
    categoryName: categoryInfo.name,
    difficulty,
    learningObjectives: localized(objectiveEn, objectiveZh),
    keywords,
    searchTokens,
    difficultyOrder,
    estimatedMinutes: difficulty === "advanced" ? 90 : difficulty === "intermediate" ? 70 : 55,
    questionCount: 5,
    totalMarks: 100,
    courseworkWeight: .70,
    status: "published",
    isActive: true,
    isAssignable: true,
    version: 1,
    seedVersion: SEED_VERSION,
    sortOrder: number,
    managedBySeed: true,
    createdBy: "system_seed",
    updatedBy: "system_seed",
  };

  const q1Prompt = localized(
    substitute(q1Templates[categoryIndex][0], focus, topic.title.zh),
    substitute(q1Templates[categoryIndex][1], focus, topic.title.zh),
  );
  const q2Prompt = localized(
    substitute(q2Templates[categoryIndex][0], focus, topic.title.zh),
    substitute(q2Templates[categoryIndex][1], focus, topic.title.zh),
  );
  const publicQuestions = [
    {
      ...questionBase(id, 1, "theory_single_choice",
        localized(`Core Decision Rule for ${topic.keywords[0]}`, `${topic.title.zh}的核心决策规则`),
        q1Prompt, localized("Select the most accurate answer.", "请选择最准确的答案。"), 15),
      options: q1Choice.options,
    },
    {
      ...questionBase(id, 2, "theory_single_choice",
        localized(`Applying ${topic.keywords[0]} Under Uncertainty`, `在不确定性下应用${topic.title.zh}`),
        q2Prompt, localized("Evaluate the scenario and select the response that best protects process discipline.", "评估情境并选择最能保护流程纪律的做法。"), 15),
      options: q2Choice.options,
    },
    {
      ...questionBase(id, 3, "chart_interpretation",
        localized(`${chart.publicData.symbol} Chart Interpretation for ${topic.keywords[0]}`, `${chart.publicData.symbol}图表中的${topic.title.zh}解读`),
        localized(
          `Study the synthetic educational ${chart.publicData.symbol} ${chart.publicData.timeframe} chart. Which labelled zone is the strongest decision area for evaluating ${focus} while keeping invalidation visible?`,
          `研究合成教学用${chart.publicData.symbol} ${chart.publicData.timeframe}图表。哪个标记区域最适合评估${topic.title.zh}，同时能清楚保留失效条件？`,
        ),
        localized("Select one chart zone. The candles are synthetic educational data, not a live-market feed.", "选择一个图表区域。K线为合成教学数据，并非实时行情。"), 20),
      chartData: chart.publicData,
      marketContextMode: "seeded_synthetic_scenario",
    },
    {
      ...questionBase(id, 4, "self_analysis_kbat",
        localized(`Independent Scenario Analysis: ${topic.keywords[0]}`, `独立情境分析：${topic.title.zh}`),
        localized(
          `Analyse both a valid and an invalid interpretation of ${focus} using the assigned chart. Compare the evidence, explain the risk boundary, identify what would change your conclusion and state when no trade is preferable.`,
          `使用指定图表分析${topic.title.zh}的有效与无效解读。比较证据，解释风险边界，说明哪些变化会改变结论，并指出何时不交易更合适。`,
        ),
        localized("Write an original evidence-based analysis. Do not present the response as personal financial advice.", "撰写原创且以证据为基础的分析。不得将回答表述为个人财务建议。"), 25),
      requiresText: true,
      minimumCharacters: 150,
      maximumCharacters: 2000,
      marketContextMode: "seeded_synthetic_scenario",
    },
    {
      ...questionBase(id, 5, "practical_demonstration",
        localized(`Practical Demonstration: ${topic.keywords[0]}`, `实践展示：${topic.title.zh}`),
        localized(
          `Using a demo account, TradingView, FXReplay, MT5 demo or another approved charting platform, demonstrate ${focus}. Annotate the evidence, entry condition, invalidation, stop loss, take profit and risk control, then explain why the demonstration follows the written rules.`,
          `使用模拟账户、TradingView、FXReplay、MT5模拟版或其他获准图表平台展示${topic.title.zh}。标注证据、入场条件、失效条件、止损、止盈与风险控制，并解释该展示为何符合书面规则。`,
        ),
        localized("Upload one to three clear screenshots and provide an original explanation.", "上传一至三张清晰截图并提供原创说明。"), 25),
      requiresText: true,
      requiresImage: true,
      minimumCharacters: 80,
      maximumCharacters: 1500,
      minimumImages: 1,
      maximumImages: 3,
      allowedFileTypes: ["image/jpeg", "image/png", "image/webp"],
      marketContextMode: "student_current_market",
    },
  ];

  const commonMistakes = localized(
    ["Treating one signal as certainty", "Ignoring invalidation or transaction cost", "Increasing risk to recover a prior loss"],
    ["将单一信号视为确定结果", "忽略失效条件或交易成本", "为弥补之前亏损而增加风险"],
  );
  const privateQuestions = [
    {
      themeId: id, questionId: "Q1", questionType: "theory_single_choice", maximumMarks: 15,
      correctOptionIds: q1Choice.correctOptionIds,
      answerExplanation: localized(`The correct response combines observable evidence, a written rule and invalidation when assessing ${focus}.`, `正确回答在评估${topic.title.zh}时结合可观察证据、书面规则与失效条件。`),
      modelAnswer: localized(null, null), rubric: selectionRubric(15), commonMistakes,
    },
    {
      themeId: id, questionId: "Q2", questionType: "theory_single_choice", maximumMarks: 15,
      correctOptionIds: q2Choice.correctOptionIds,
      answerExplanation: localized("Pausing and rechecking evidence, invalidation and risk protects the process when uncertainty appears.", "出现不确定性时，暂停并重新核对证据、失效条件与风险能够保护交易流程。"),
      modelAnswer: localized(null, null), rubric: selectionRubric(15), commonMistakes,
    },
    {
      themeId: id, questionId: "Q3", questionType: "chart_interpretation", maximumMarks: 20,
      correctOptionIds: [], correctZoneIds: chart.correctZoneIds,
      answerExplanation: localized("The configured zone best combines the visible reaction area with a measurable invalidation boundary in this synthetic scenario.", "在该合成情境中，设定区域最能结合可见反应区与可衡量的失效边界。"),
      modelAnswer: localized(null, null),
      rubric: rubric([["correct_zone", "Selects the configured decision zone", "选择设定的决策区域", 20]]), commonMistakes,
    },
    {
      themeId: id, questionId: "Q4", questionType: "self_analysis_kbat", maximumMarks: 25,
      correctOptionIds: [],
      answerExplanation: localized("Responses are evaluated for evidence, comparison, invalidation, risk awareness and logical clarity rather than for predicting one direction.", "评分依据是证据、比较、失效条件、风险意识与逻辑清晰度，而不是预测单一方向。"),
      modelAnswer: localized(
        `A strong analysis of ${focus} identifies market context, tests competing scenarios, justifies key levels, defines invalidation and concludes whether action or no trade is appropriate.`,
        `高质量的${topic.title.zh}分析应识别市场语境、检验相互竞争的情境、论证关键价位、定义失效条件，并判断采取行动或不交易是否合适。`,
      ),
      rubric: rubric([
        ["market_context", "Market-context identification", "市场语境识别", 5],
        ["structure_analysis", "Structure and evidence analysis", "结构与证据分析", 5],
        ["key_level_justification", "Key-level justification", "关键价位论证", 5],
        ["risk_invalidation", "Risk and invalidation explanation", "风险与失效条件说明", 5],
        ["conclusion_clarity", "Logical conclusion and clarity", "逻辑结论与清晰度", 5],
      ]), commonMistakes,
    },
    {
      themeId: id, questionId: "Q5", questionType: "practical_demonstration", maximumMarks: 25,
      correctOptionIds: [],
      answerExplanation: localized("The evidence must demonstrate the assigned skill on an approved platform and show coherent entry, invalidation, target and risk logic.", "证据必须在获准平台上展示指定技能，并呈现连贯的入场、失效、目标与风险逻辑。"),
      modelAnswer: localized(
        `A complete demonstration clearly annotates ${focus}, identifies the entry condition, places the stop beyond logical invalidation, plans a feasible target and explains controlled position risk.`,
        `完整展示应清晰标注${topic.title.zh}，识别入场条件，将止损置于逻辑失效范围之外，规划可行目标并解释受控仓位风险。`,
      ),
      rubric: rubric([
        ["chart_annotation", "Correct and readable chart annotation", "正确且清晰的图表标注", 5],
        ["entry_justification", "Entry condition and justification", "入场条件与理由", 5],
        ["stop_placement", "Logical stop-loss and invalidation placement", "合理的止损与失效位置", 5],
        ["target_planning", "Feasible take-profit planning", "可行的止盈规划", 5],
        ["risk_explanation", "Risk-management explanation", "风险管理说明", 5],
      ]), commonMistakes,
    },
  ].map((item) => ({
    ...item,
    seedVersion: SEED_VERSION,
    version: 1,
    managedBySeed: true,
    createdBy: "system_seed",
    updatedBy: "system_seed",
  }));

  for (const question of publicQuestions) question.contentHash = contentHash(question);
  for (const key of privateQuestions) key.contentHash = contentHash(key);
  themeDocument.contentHash = contentHash(themeDocument);
  const privateTheme = {
    themeId: id,
    code: id,
    questionCount: 5,
    totalMarks: 100,
    version: 1,
    seedVersion: SEED_VERSION,
    managedBySeed: true,
    createdBy: "system_seed",
    updatedBy: "system_seed",
  };
  privateTheme.contentHash = contentHash(privateTheme);
  return {
    theme: themeDocument,
    questions: publicQuestions,
    privateTheme,
    privateQuestions,
  };
}

function canonical(value) {
  if (Array.isArray(value)) return value.map(canonical);
  if (value && typeof value === "object") {
    return Object.fromEntries(Object.keys(value).sort().map((key) => [key, canonical(value[key])]));
  }
  return value;
}

function contentHash(value) {
  const copy = {...value};
  delete copy.contentHash;
  return crypto.createHash("sha256").update(JSON.stringify(canonical(copy))).digest("hex");
}

function writeJson(file, value) {
  fs.mkdirSync(path.dirname(file), {recursive: true});
  fs.writeFileSync(file, `${JSON.stringify(value, null, 2)}\n`, "utf8");
}

function generate() {
  if (categories.length !== 10 || categories.some((item) => item.themes.length !== 10)) {
    throw new Error("Curriculum must contain ten categories with ten themes each.");
  }
  const generated = [];
  let globalIndex = 0;
  categories.forEach((categoryInfo) => {
    categoryInfo.themes.forEach((topic, categoryIndex) => {
      generated.push(makeTheme(topic, categoryInfo, globalIndex, categoryIndex));
      globalIndex++;
    });
  });
  writeJson(path.join(root, "categories.json"), categories.map((item, index) => ({
    id: item.id,
    name: item.name,
    sortOrder: index + 1,
    themeCount: item.themes.length,
  })));
  for (const item of generated) {
    const id = item.theme.id;
    writeJson(path.join(themeDir, `${id}.json`), {
      theme: item.theme,
      questions: item.questions,
    });
    writeJson(path.join(keyDir, `${id}.keys.json`), {
      themeKey: item.privateTheme,
      questions: item.privateQuestions,
    });
  }
  const manifest = {
    seedVersion: SEED_VERSION,
    generatedAt: GENERATED_AT,
    themeCount: generated.length,
    publicQuestionCount: generated.reduce((sum, item) => sum + item.questions.length, 0),
    privateThemeCount: generated.length,
    privateQuestionCount: generated.reduce((sum, item) => sum + item.privateQuestions.length, 0),
    categoryCount: categories.length,
    contentHash: contentHash(generated),
  };
  writeJson(path.join(root, "manifest.json"), manifest);
  return {generated, manifest};
}

if (require.main === module) {
  const {manifest} = generate();
  console.log(JSON.stringify(manifest, null, 2));
}

module.exports = {SEED_VERSION, contentHash, generate, makeTheme};
