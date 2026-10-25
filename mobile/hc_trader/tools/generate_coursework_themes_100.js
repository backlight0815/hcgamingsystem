#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..");
const output = path.join(root, "assets", "seed", "coursework_themes_100.json");

const catalogue = [
  category("SR", "Support and Resistance", "支撑与阻力", [
    ["First-Touch Support Validation", "首次触及支撑验证"],
    ["Repeated-Test Resistance Quality", "多次测试阻力质量"],
    ["Support-to-Resistance Role Reversal", "支撑转阻力角色互换"],
    ["Resistance-to-Support Role Reversal", "阻力转支撑角色互换"],
    ["Fresh Versus Mitigated Price Zones", "新鲜与已缓解价格区域"],
    ["Higher-Timeframe Support Confluence", "高周期支撑共振"],
    ["Higher-Timeframe Resistance Confluence", "高周期阻力共振"],
    ["Liquidity Sweep Below Support", "支撑下方流动性扫荡"],
    ["Liquidity Sweep Above Resistance", "阻力上方流动性扫荡"],
    ["Dynamic Support in an Uptrend", "上升趋势中的动态支撑"],
    ["Dynamic Resistance in a Downtrend", "下降趋势中的动态阻力"],
    ["Zone Width and Invalidation", "区域宽度与失效条件"],
  ]),
  category("MS", "Market Structure", "市场结构", [
    ["Major Swing High Identification", "主要摆动高点识别"],
    ["Major Swing Low Identification", "主要摆动低点识别"],
    ["Bullish Break of Structure", "看涨结构突破"],
    ["Bearish Break of Structure", "看跌结构突破"],
    ["Bullish Change of Character", "看涨结构转变"],
    ["Bearish Change of Character", "看跌结构转变"],
    ["Internal Versus External Structure", "内部与外部结构"],
    ["Protected Highs and Protected Lows", "受保护高点与低点"],
    ["Structure Continuation After Pullback", "回调后的结构延续"],
    ["Failed Structure Break", "结构突破失败"],
    ["Liquidity Grab Before Structure Shift", "结构转变前的流动性获取"],
    ["Nested Multi-Level Structure", "嵌套多层级结构"],
  ]),
  category("TR", "Trend and Range Conditions", "趋势与区间环境", [
    ["Healthy Bullish Trend Conditions", "健康看涨趋势条件"],
    ["Healthy Bearish Trend Conditions", "健康看跌趋势条件"],
    ["Early Trend Exhaustion", "趋势早期衰竭"],
    ["Range Boundary Validation", "区间边界验证"],
    ["Mid-Range No-Trade Conditions", "区间中部禁交易条件"],
    ["Range Expansion Breakout", "区间扩张突破"],
    ["False Break From Consolidation", "盘整假突破"],
    ["Trend-to-Range Transition", "趋势转区间"],
    ["Range-to-Trend Transition", "区间转趋势"],
    ["Volatility Compression and Expansion", "波动收缩与扩张"],
  ]),
  category("PA", "Candlestick and Price Action", "K线与价格行为", [
    ["Bullish Rejection Candle Context", "看涨拒绝K线语境"],
    ["Bearish Rejection Candle Context", "看跌拒绝K线语境"],
    ["Engulfing Candle Confirmation", "吞没K线确认"],
    ["Inside Bar Compression", "内包线收缩"],
    ["Momentum Candle Breakout", "动量K线突破"],
    ["Failed Pin-Bar Signal", "针形K线信号失败"],
    ["Three-Candle Reversal Sequence", "三K线反转序列"],
    ["Closing Price Confirmation", "收盘价确认"],
    ["Wick Rejection Versus Acceptance", "影线拒绝与接受"],
    ["Price-Action Confirmation at a Key Zone", "关键区域价格行为确认"],
  ]),
  category("RM", "Risk Management", "风险管理", [
    ["Fixed Percentage Risk Per Trade", "每笔固定百分比风险"],
    ["Position Sizing From Stop Distance", "依据止损距离计算仓位"],
    ["Maximum Daily Loss Control", "最大单日亏损控制"],
    ["Maximum Weekly Drawdown Control", "最大周回撤控制"],
    ["Correlated Position Exposure", "相关持仓风险暴露"],
    ["Risk Reduction After Drawdown", "回撤后的风险降低"],
    ["Risk Adjustment for Volatility", "按波动调整风险"],
    ["Portfolio Heat Management", "组合总风险管理"],
    ["Risk of Ruin Prevention", "破产风险预防"],
    ["Consecutive-Loss Stop Rule", "连续亏损停止规则"],
    ["News-Event Risk Reduction", "新闻事件风险降低"],
    ["Asymmetric Reward Planning", "非对称回报规划"],
  ]),
  category("OM", "Order Management", "订单管理", [
    ["Market Order Execution", "市价单执行"],
    ["Limit Order Placement", "限价单设置"],
    ["Stop Order Breakout Entry", "止损单突破入场"],
    ["Pending Order Expiry", "挂单到期管理"],
    ["Break-Even Stop Management", "保本止损管理"],
    ["Partial Profit Taking", "分批止盈"],
    ["Trailing Stop Structure Logic", "结构化移动止损逻辑"],
    ["Scaling Into a Valid Setup", "有效形态中的分批建仓"],
    ["Scaling Out at Objectives", "目标位分批减仓"],
    ["Order Cancellation After Invalidation", "失效后撤销订单"],
    ["Slippage-Aware Execution", "考虑滑点的执行"],
    ["Managing a Trade Around News", "新闻前后交易管理"],
  ]),
  category("ST", "Stop Loss and Take Profit", "止损与止盈", [
    ["Stop Loss Beyond Market Structure", "结构外止损"],
    ["Stop Loss Beyond a Price Zone", "价格区域外止损"],
    ["ATR-Informed Stop Distance", "ATR辅助止损距离"],
    ["Invalid Tight Stop Placement", "无效过紧止损"],
    ["Take Profit at Opposing Structure", "对向结构止盈"],
    ["Take Profit at Liquidity", "流动性位置止盈"],
    ["Multiple Take-Profit Objectives", "多级止盈目标"],
    ["Stop-to-Target Feasibility", "止损至目标可行性"],
    ["Time-Based Trade Exit", "基于时间的离场"],
    ["Invalidation Before Stop Loss", "止损前的逻辑失效"],
  ]),
  category("TP", "Trade Planning", "交易计划", [
    ["Pre-Session Market Preparation", "盘前市场准备"],
    ["A-Grade Setup Definition", "A级形态定义"],
    ["Entry Confirmation Checklist", "入场确认清单"],
    ["No-Trade Condition Planning", "禁交易条件规划"],
    ["Primary and Alternative Scenarios", "主要与备选情景"],
    ["Session-Specific Trade Plan", "特定时段交易计划"],
    ["Economic Calendar Integration", "经济日历整合"],
    ["Post-Trade Review Plan", "交易后复盘计划"],
  ]),
  category("PSY", "Trading Psychology and Discipline", "交易心理与纪律", [
    ["Revenge-Trading Prevention", "报复性交易预防"],
    ["Overtrading Prevention", "过度交易预防"],
    ["Fear of Missing Out Control", "错失恐惧控制"],
    ["Loss Acceptance and Reset", "接受亏损与重置"],
    ["Confidence After a Winning Streak", "连胜后的信心管理"],
    ["Process Over Outcome Thinking", "重过程轻结果思维"],
    ["Rule-Breach Recovery Protocol", "违规后的恢复流程"],
    ["Consistent Trading Routine", "一致交易习惯"],
  ]),
  category("MT", "Multi-Timeframe Analysis", "多周期分析", [
    ["Weekly-to-Daily Directional Alignment", "周线至日线方向一致"],
    ["Daily-to-Hourly Setup Alignment", "日线至小时线形态一致"],
    ["Higher-Timeframe Zone With Lower-Timeframe Entry", "高周期区域与低周期入场"],
    ["Conflicting Timeframe Signals", "周期信号冲突"],
    ["Top-Down Structure Mapping", "自上而下结构映射"],
    ["Execution-Timeframe Confirmation", "执行周期确认"],
  ]),
].flat();

// Fixed CoinGecko BTC/USD OHLC response captured on 2026-07-15. The fixture is
// deliberately stored as values rather than chart images, so the app can render
// accessible light/dark charts. Source endpoint and capture date are retained.
const realOhlc = [
  [1782892800000, 58995, 59290, 58497, 58662],
  [1782907200000, 58679, 58933, 58480, 58542],
  [1782921600000, 58467, 60359, 58279, 60091],
  [1782936000000, 60091, 60215, 59754, 59960],
  [1782950400000, 59887, 60978, 59839, 59980],
  [1782964800000, 59876, 60957, 59540, 60957],
  [1782979200000, 60926, 60926, 60055, 60055],
  [1782993600000, 60117, 61253, 60117, 61221],
  [1783008000000, 61225, 62060, 61140, 61552],
  [1783022400000, 61490, 61819, 61120, 61427],
  [1783036800000, 61389, 61508, 61286, 61487],
  [1783051200000, 61415, 61611, 61218, 61353],
  [1783065600000, 61364, 61760, 61296, 61696],
  [1783080000000, 61695, 61958, 61514, 61954],
  [1783094400000, 62064, 62284, 61734, 61869],
  [1783108800000, 61868, 62215, 61868, 62180],
  [1783123200000, 62158, 62764, 62145, 62537],
  [1783137600000, 62554, 62821, 62386, 62588],
  [1783152000000, 62561, 62662, 62305, 62532],
  [1783166400000, 62483, 62586, 62395, 62453],
  [1783180800000, 62493, 62930, 62493, 62881],
  [1783195200000, 62878, 63334, 62785, 63259],
  [1783209600000, 63308, 63383, 62975, 63094],
  [1783224000000, 63025, 63063, 62580, 62734],
  [1783238400000, 62684, 62978, 62626, 62978],
  [1783252800000, 62981, 62981, 62462, 62616],
  [1783267200000, 62615, 62837, 62530, 62692],
  [1783281600000, 62687, 62797, 62563, 62714],
  [1783296000000, 62724, 63857, 62576, 63546],
  [1783310400000, 63590, 63775, 63113, 63214],
  [1783324800000, 63282, 63286, 62844, 63036],
  [1783339200000, 63082, 63100, 62586, 62630],
  [1783353600000, 61945, 63437, 61339, 63437],
  [1783368000000, 63437, 63879, 63416, 63712],
  [1783382400000, 63577, 64387, 63564, 64072],
  [1783396800000, 63995, 64222, 63125, 63125],
  [1783411200000, 63128, 63345, 62856, 63044],
  [1783425600000, 63044, 63381, 62994, 63338],
  [1783440000000, 63397, 64012, 62815, 63865],
  [1783454400000, 63887, 64134, 63406, 63766],
];

function category(prefix, categoryEn, categoryZh, items) {
  return items.map(([title, titleZh], index) => ({
    prefix,
    categoryEn,
    categoryZh,
    title,
    titleZh,
    categoryIndex: index + 1,
  }));
}

function localized(en, zh) {
  return {en, zh};
}

function rubric(kind) {
  const self = [
    ["structure", "Market structure and directional bias", "市场结构与方向偏向"],
    ["zones", "Identification of important price zones", "重要价格区域识别"],
    ["evidence", "Evidence and chart relevance", "证据与图表相关性"],
    ["scenario", "Scenario and invalidation planning", "情景与失效规划"],
    ["risk", "Risk awareness and reasoning", "风险意识与推理"],
  ];
  const order = [
    ["entry", "Entry logic", "入场逻辑"],
    ["stop", "Stop-loss logic", "止损逻辑"],
    ["target", "Take-profit logic", "止盈逻辑"],
    ["risk", "Risk-to-reward and risk control", "盈亏比与风险控制"],
    ["evidence", "Screenshot evidence and explanation", "截图证据与说明"],
  ];
  return (kind === "self" ? self : order).map(([id, en, zh]) => ({
    id,
    title: en,
    title_i18n: localized(en, zh),
    maximum_mark: 6,
    guidance: `Award marks for specific, internally consistent ${en.toLowerCase()}.`,
    guidance_i18n: localized(
      `Award marks for specific, internally consistent ${en.toLowerCase()}.`,
      `根据具体且逻辑一致的${zh}评分。`,
    ),
  }));
}

function chartFor(index) {
  const start = index % (realOhlc.length - 18);
  const rows = realOhlc.slice(start, start + 18);
  const lows = rows.map((item) => item[3]);
  const highs = rows.map((item) => item[2]);
  const low = Math.min(...lows);
  const high = Math.max(...highs);
  const span = high - low;
  const zones = [
    ["A", low, low + span * 0.18],
    ["B", low + span * 0.27, low + span * 0.43],
    ["C", low + span * 0.57, low + span * 0.73],
    ["D", low + span * 0.82, high],
  ];
  const correct = index % zones.length;
  return {
    rows: rows.map(([time, open, highValue, lowValue, close]) => ({
      time: new Date(time).toISOString(), open, high: highValue, low: lowValue, close,
    })),
    zones: zones.map(([label, minimum, maximum], zoneIndex) => ({
      id: label.toLowerCase(),
      label,
      minimum_price: Math.round(minimum * 100) / 100,
      maximum_price: Math.round(maximum * 100) / 100,
      is_correct: zoneIndex === correct,
      partial_mark_ratio: Math.abs(zoneIndex - correct) === 1 ? 0.25 : 0,
    })),
    correctLabel: zones[correct][0],
    marketDate: new Date(rows[0][0]).toISOString(),
  };
}

function theoryOptions(focus, focusZh, second) {
  const values = second ? [
    ["a", `Define the invalidation first and reject the idea when ${focus} no longer holds.`, `先定义失效条件，并在${focusZh}不再成立时放弃交易。`, true],
    ["b", "Widen the stop after entry so the original idea cannot fail.", "入场后扩大止损，使原计划不会失败。", false],
    ["c", "Ignore the higher timeframe whenever the lower timeframe moves quickly.", "低周期快速波动时忽略高周期。", false],
    ["d", "Increase position size after a loss to recover faster.", "亏损后增加仓位以更快回本。", false],
  ] : [
    ["a", `Use objective structure, repeated price response, and a defined invalidation to evaluate ${focus}.`, `使用客观结构、重复价格反应及明确失效条件评估${focusZh}。`, true],
    ["b", "Use only the colour of the most recent candle.", "只使用最近一根K线的颜色。", false],
    ["c", "Assume every first touch will reverse without confirmation.", "假设每次首次触及都会无条件反转。", false],
    ["d", "Choose the conclusion that allows the largest position size.", "选择能使用最大仓位的结论。", false],
  ];
  return values.map(([id, en, zh, correct]) => ({
    id, text: en, text_i18n: localized(en, zh), is_correct: correct,
  }));
}

function generateTheme(item, index) {
  const number = String(item.categoryIndex).padStart(3, "0");
  const code = `CW-${item.prefix}-${number}`;
  const id = code.toLowerCase();
  const chart = chartFor(index);
  const difficulty = index % 5 === 0 ? "advanced" : index % 3 === 0 ? "foundation" : "intermediate";
  const common = {
    theme_id: id,
    theme_code: code,
    title: item.title,
    title_i18n: localized(item.title, item.titleZh),
    short_description: `Apply ${item.title.toLowerCase()} to objective market analysis, chart interpretation, and risk-controlled execution.`,
    short_description_i18n: localized(
      `Apply ${item.title.toLowerCase()} to objective market analysis, chart interpretation, and risk-controlled execution.`,
      `将${item.titleZh}应用于客观市场分析、图表判断及风险受控的执行。`,
    ),
    category: item.categoryEn,
    category_i18n: localized(item.categoryEn, item.categoryZh),
    difficulty,
    learning_objectives: [
      `Explain the decision rules for ${item.title}.`,
      `Identify valid and invalid evidence for ${item.title}.`,
      `Build a risk-controlled order plan using ${item.title}.`,
    ],
    learning_objectives_i18n: [
      `说明${item.titleZh}的决策规则。`,
      `识别${item.titleZh}的有效与无效证据。`,
      `运用${item.titleZh}制定风险受控的订单计划。`,
    ],
    instructions: "Complete all five questions. Questions 4 and 5 require original written analysis and screenshot evidence from a demo, replay, or historical environment.",
    instructions_i18n: localized(
      "Complete all five questions. Questions 4 and 5 require original written analysis and screenshot evidence from a demo, replay, or historical environment.",
      "完成全部五道题。第4和第5题须提交原创书面分析，以及来自模拟、回放或历史环境的截图证据。",
    ),
    estimated_completion_minutes: difficulty === "advanced" ? 110 : 90,
    total_marks: 100,
    status: "published",
    version: 1,
    is_active: true,
    language_availability: ["en", "zh"],
    seed_meta: {
      content_version: "coursework_100_v1",
      generated_at: "2026-07-15T00:00:00.000Z",
      chart_source: "CoinGecko BTC/USD OHLC public endpoint",
      chart_source_url: "https://api.coingecko.com/api/v3/coins/bitcoin/ohlc?vs_currency=usd&days=14",
      chart_captured_at: "2026-07-15T00:00:00.000Z",
    },
  };
  const q = (numberValue, type, marks, en, zh, extra = {}) => ({
    question_id: `${id}-q${numberValue}`,
    theme_id: id,
    question_number: numberValue,
    question_type: type,
    title: `Question ${numberValue}`,
    title_i18n: localized(`Question ${numberValue}`, `问题 ${numberValue}`),
    question_text: en,
    question_text_i18n: localized(en, zh),
    instructions: numberValue < 3 ? "Select the best answer." : "Use only the frozen market snapshot assigned to this attempt.",
    instructions_i18n: localized(
      numberValue < 3 ? "Select the best answer." : "Use only the frozen market snapshot assigned to this attempt.",
      numberValue < 3 ? "选择最佳答案。" : "仅使用本次作业分配时冻结的市场快照。",
    ),
    marks,
    difficulty,
    learning_objective: common.learning_objectives[Math.min(numberValue - 1, 2)],
    status: "published",
    version: 1,
    ...extra,
  });
  const questions = [
    q(1, "singleChoice", 10,
      `Which method most reliably evaluates ${item.title.toLowerCase()}?`,
      `哪一种方法最可靠地评估${item.titleZh}？`,
      {answer_options: theoryOptions(item.title, item.titleZh, false), grading_mode: "automatic"}),
    q(2, "singleChoice", 10,
      `Which response best protects the trader when a ${item.title.toLowerCase()} thesis is invalidated?`,
      `当${item.titleZh}的交易论点失效时，哪一种做法最能保护交易者？`,
      {answer_options: theoryOptions(item.title, item.titleZh, true), grading_mode: "automatic"}),
    q(3, "chartZoneSelection", 20,
      `Examine the frozen BTC/USD chart in the context of ${item.title.toLowerCase()}. Which labelled zone is the configured decision area?`,
      `结合${item.titleZh}分析冻结的 BTC/USD 图表。哪一个标记区域是设定的决策区域？`, {
        requires_written_explanation: true,
        chart_asset: `ohlc://${code}/btc-usd-4h`,
        symbol: "BTCUSD",
        timeframe: "4H",
        market_date: chart.marketDate,
        chart_candles: chart.rows,
        answer_zones: chart.zones,
        grading_mode: "automatic_with_partial_marks",
        reviewer_guidance: `Configured answer: zone ${chart.correctLabel}. Accept adjacent configured zones only for the stored partial mark.`,
      }),
    q(4, "marketAnalysis", 30,
      `Using the assigned BTC/USD market snapshot, produce an independent analysis that applies ${item.title.toLowerCase()}.`,
      `使用分配的 BTC/USD 市场快照，运用${item.titleZh}完成独立分析。`, {
        requires_written_explanation: true,
        requires_image: true,
        maximum_images: 3,
        symbol: "BTCUSD",
        timeframe: "4H",
        market_date: chart.marketDate,
        grading_mode: "manual",
        grading_rubric: rubric("self"),
        sample_answer: `A complete response identifies structure and bias, key zones, entry and invalidation scenarios, an alternative scenario, and risk controls relevant to ${item.title}.`,
        reviewer_guidance: "Reward evidence-based reasoning. Do not reward a directional claim without an invalidation condition.",
      }),
    q(5, "orderManagementDemonstration", 30,
      `Demonstrate a demo or replay order plan for the same snapshot using ${item.title.toLowerCase()}. Define entry, stop loss, take profit, risk, reward, and invalidation.`,
      `针对同一市场快照，运用${item.titleZh}展示模拟或回放订单计划。定义入场、止损、止盈、风险、回报及失效条件。`, {
        requires_written_explanation: true,
        requires_image: true,
        maximum_images: 3,
        symbol: "BTCUSD",
        timeframe: "4H",
        market_date: chart.marketDate,
        grading_mode: "manual",
        grading_rubric: rubric("order"),
        sample_answer: "A valid response uses logically ordered prices, finite risk calculations, a defensible reward target, and evidence from a demo, replay, or historical chart.",
        reviewer_guidance: "Reject real-money-trade encouragement, invalid price geometry, zero stop distance, NaN, or infinite calculations.",
      }),
  ];
  return {...common, question_ids: questions.map((item) => item.question_id), questions};
}

if (catalogue.length !== 100) throw new Error(`Catalogue has ${catalogue.length} themes.`);
const themes = catalogue.map(generateTheme);
fs.mkdirSync(path.dirname(output), {recursive: true});
fs.writeFileSync(output, `${JSON.stringify(themes, null, 2)}\n`);
console.log(`Generated ${themes.length} themes and ${themes.reduce((sum, item) => sum + item.questions.length, 0)} questions.`);
console.log(`Output: ${output}`);

module.exports = {catalogue, generateTheme, realOhlc};
