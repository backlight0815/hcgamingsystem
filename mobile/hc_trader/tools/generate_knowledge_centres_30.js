#!/usr/bin/env node
"use strict";

const fs = require("fs");
const path = require("path");

const projectRoot = path.resolve(__dirname, "..");
const seedPath = path.join(projectRoot, "assets", "seed", "knowledge_centres_30.json");
const imageRoot = path.join(projectRoot, "assets", "knowledge_center", "images");
const assetPrefix = "assets/knowledge_center/images";
const seedContentVersion = "knowledge_centres_30_v2";

const modules = [
  moduleDef("KC_THEORY_001", "theory", "Forex and Trading Basics", "外汇与交易基础", [
    ["What is forex", "什么是外汇"],
    ["What is CFD", "什么是 CFD"],
    ["Buy and sell", "买入与卖出"],
    ["Long and short", "做多与做空"],
    ["Trading platform basics", "交易平台基础"],
    ["Basic trading terminology", "基础交易术语"],
  ], ["Forex pair concept", "Buy vs sell direction", "CFD trading concept"]),
  moduleDef("KC_THEORY_002", "theory", "Currency Pairs and Market Pricing", "货币对与市场报价", [
    ["Major pairs", "主要货币对"],
    ["Minor pairs", "次要货币对"],
    ["Exotic pairs", "交叉与小众货币对"],
    ["Base currency", "基础货币"],
    ["Quote currency", "报价货币"],
    ["Bid and ask price", "买价与卖价"],
    ["Price movement", "价格波动"],
  ], ["Base and quote currency", "Bid and ask price", "Price movement example"]),
  moduleDef("KC_THEORY_003", "theory", "Pip, Spread, Commission, and Trading Costs", "点数、点差、佣金与交易成本", [
    ["Pip", "点数"],
    ["Pipette", "小数点位"],
    ["Spread", "点差"],
    ["Commission", "佣金"],
    ["Swap", "隔夜利息"],
    ["Slippage", "滑点"],
    ["Trading cost awareness", "交易成本意识"],
  ], ["Pip movement", "Spread bid ask", "Trading cost diagram"]),
  moduleDef("KC_THEORY_004", "theory", "Lot Size, Leverage, Margin, and Equity", "手数、杠杆、保证金与净值", [
    ["Standard lot", "标准手"],
    ["Mini lot", "迷你手"],
    ["Micro lot", "微型手"],
    ["Leverage", "杠杆"],
    ["Margin", "保证金"],
    ["Free margin", "可用保证金"],
    ["Balance and equity", "余额与净值"],
    ["Floating profit or loss", "浮动盈亏"],
  ], ["Lot size comparison", "Margin and leverage", "Balance versus equity"]),
  moduleDef("KC_THEORY_005", "theory", "Order Types and Execution", "订单类型与执行", [
    ["Market order", "市价单"],
    ["Pending order", "挂单"],
    ["Buy limit", "Buy Limit"],
    ["Sell limit", "Sell Limit"],
    ["Buy stop", "Buy Stop"],
    ["Sell stop", "Sell Stop"],
    ["Stop loss", "止损"],
    ["Take profit", "止盈"],
    ["Order execution", "订单执行"],
  ], ["Buy limit versus buy stop", "Sell limit versus sell stop", "Stop loss and take profit placement"]),
  moduleDef("KC_THEORY_006", "theory", "Risk Reward and Trade Planning Basics", "风险回报与交易计划基础", [
    ["Risk reward ratio", "风险回报比"],
    ["Stop loss planning", "止损规划"],
    ["Take profit planning", "止盈规划"],
    ["Risk per trade", "单笔风险"],
    ["Position sizing basics", "仓位计算基础"],
    ["Why planning matters", "交易计划的重要性"],
  ], ["One to two risk reward", "Trade planning checklist", "Risk per trade concept"]),
  moduleDef("KC_THEORY_007", "theory", "Market Sessions and Liquidity", "市场交易时段与流动性", [
    ["Asian session", "亚洲盘"],
    ["London session", "伦敦盘"],
    ["New York session", "纽约盘"],
    ["Session overlap", "时段重叠"],
    ["Liquidity", "流动性"],
    ["Volatility", "波动性"],
    ["Best and worst trading conditions", "适合与不适合交易的环境"],
  ], ["Market session timeline", "Liquidity and volatility comparison", "Session overlap activity"]),
  moduleDef("KC_THEORY_008", "theory", "News and Fundamental Analysis", "新闻与基本面分析", [
    ["High impact news", "高影响新闻"],
    ["CPI", "CPI"],
    ["NFP", "NFP"],
    ["FOMC", "FOMC"],
    ["Interest rates", "利率"],
    ["Inflation", "通胀"],
    ["Central banks", "央行"],
    ["Fundamental market drivers", "基本面驱动因素"],
  ], ["High impact news volatility", "Interest rate impact concept", "Economic calendar event impact"]),
  moduleDef("KC_THEORY_009", "theory", "Broker, Platform, and Trading Environment", "经纪商、平台与交易环境", [
    ["Broker role", "经纪商角色"],
    ["Spread differences", "点差差异"],
    ["Execution quality", "执行质量"],
    ["Account types", "账户类型"],
    ["Demo versus live account", "模拟账户与真实账户"],
    ["Prop firm basics", "Prop Firm 基础"],
    ["Broker risk awareness", "经纪商风险意识"],
  ], ["Trader broker liquidity provider flow", "Demo versus live account comparison", "Execution quality and slippage"]),

  moduleDef("KC_TECHNICAL_001", "technical", "Support and Resistance", "支撑与阻力", [
    ["Support", "支撑"],
    ["Resistance", "阻力"],
    ["Key levels", "关键价位"],
    ["Zone concept", "区域概念"],
    ["Body and wick areas", "实体与影线区域"],
    ["Why SNR matters", "SNR 的作用"],
  ], ["Support zone", "Resistance zone", "Support becomes resistance", "Resistance becomes support", "Body and wick key level zone"]),
  moduleDef("KC_TECHNICAL_002", "technical", "SNR Zones and Price Rejection", "SNR 区域与价格拒绝", [
    ["SNR zone drawing", "SNR 区域绘制"],
    ["Wick rejection", "影线拒绝"],
    ["Body close", "实体收盘"],
    ["Multiple touches", "多次触碰"],
    ["Strong versus weak zones", "强弱区域"],
    ["Failed rejection", "失败拒绝"],
  ], ["Wick rejection at support", "Wick rejection at resistance", "Strong SNR zone with multiple reactions", "Failed SNR rejection example"]),
  moduleDef("KC_TECHNICAL_003", "technical", "Trendline and Channel Analysis", "趋势线与通道分析", [
    ["Trendline", "趋势线"],
    ["Uptrend line", "上升趋势线"],
    ["Downtrend line", "下降趋势线"],
    ["Channel", "通道"],
    ["Trendline break", "趋势线突破"],
    ["Trendline retest", "趋势线回踩"],
  ], ["Uptrend trendline", "Downtrend trendline", "Channel", "Trendline break and retest"]),
  moduleDef("KC_TECHNICAL_004", "technical", "Market Structure Basics", "市场结构基础", [
    ["Higher high", "更高高点"],
    ["Higher low", "更高低点"],
    ["Lower high", "更低高点"],
    ["Lower low", "更低低点"],
    ["Swing high", "摆动高点"],
    ["Swing low", "摆动低点"],
    ["Trend direction", "趋势方向"],
  ], ["HH HL uptrend structure", "LH LL downtrend structure", "Swing high and swing low", "Structure direction comparison"]),
  moduleDef("KC_TECHNICAL_005", "technical", "BOS and CHOCH", "BOS 与 CHOCH", [
    ["Break of Structure", "结构突破"],
    ["Change of Character", "结构性质改变"],
    ["Continuation structure", "延续结构"],
    ["Reversal structure", "反转结构"],
    ["Invalid structure break", "无效结构突破"],
    ["Structure confirmation", "结构确认"],
  ], ["Bullish BOS", "Bearish BOS", "CHOCH reversal", "Invalid break example"]),
  moduleDef("KC_TECHNICAL_006", "technical", "Multi-Timeframe Analysis", "多周期分析", [
    ["H4 major trend", "H4 主要趋势"],
    ["H1 internal structure", "H1 内部结构"],
    ["M15 execution", "M15 执行"],
    ["Higher timeframe bias", "高周期方向"],
    ["Lower timeframe confirmation", "低周期确认"],
    ["Avoiding timeframe conflict", "避免周期冲突"],
  ], ["H4 H1 M15 alignment", "Higher timeframe bias", "Lower timeframe entry confirmation", "Timeframe conflict warning"]),
  moduleDef("KC_TECHNICAL_007", "technical", "Supply, Demand, and Order Block", "供应、需求与订单块", [
    ["Supply zone", "供应区"],
    ["Demand zone", "需求区"],
    ["Order block", "订单块"],
    ["Mitigated order block", "已回补订单块"],
    ["Fresh zone", "新鲜区域"],
    ["Used zone", "已使用区域"],
    ["POI", "POI"],
  ], ["Supply zone", "Demand zone", "Order block", "Mitigated versus fresh order block"]),
  moduleDef("KC_TECHNICAL_008", "technical", "Fair Value Gap, Imbalance, and Liquidity", "公允价值缺口、失衡与流动性", [
    ["Fair value gap", "公允价值缺口"],
    ["Imbalance", "失衡"],
    ["Liquidity sweep", "流动性扫单"],
    ["Equal highs", "相等高点"],
    ["Equal lows", "相等低点"],
    ["Stop hunt concept", "扫止损概念"],
    ["Liquidity grab", "流动性抓取"],
  ], ["Fair value gap", "Imbalance candle movement", "Equal highs liquidity sweep", "Equal lows liquidity sweep"]),
  moduleDef("KC_TECHNICAL_009", "technical", "EMA and Moving Average Analysis", "EMA 与均线分析", [
    ["EMA", "EMA"],
    ["EMA 24", "EMA 24"],
    ["EMA 52", "EMA 52"],
    ["EMA 100", "EMA 100"],
    ["EMA 200", "EMA 200"],
    ["Dynamic support and resistance", "动态支撑与阻力"],
    ["Trend filter", "趋势过滤"],
  ], ["Price above EMA trend filter", "Price below EMA trend filter", "EMA dynamic support", "Multiple EMA alignment"]),
  moduleDef("KC_TECHNICAL_010", "technical", "RSI, MACD, and Indicator Confirmation", "RSI、MACD 与指标确认", [
    ["RSI", "RSI"],
    ["Overbought", "超买"],
    ["Oversold", "超卖"],
    ["MACD", "MACD"],
    ["Momentum", "动能"],
    ["Indicator confirmation", "指标确认"],
    ["Indicator limitations", "指标局限"],
  ], ["RSI overbought", "RSI oversold", "MACD momentum", "Indicator false signal warning"]),
  moduleDef("KC_TECHNICAL_011", "technical", "Candlestick Confirmation", "K线确认", [
    ["Pin bar", "Pin Bar"],
    ["Engulfing candle", "吞没K线"],
    ["Doji", "十字星"],
    ["Inside bar", "内包K线"],
    ["Strong close", "强收盘"],
    ["Rejection candle", "拒绝K线"],
    ["Candle confirmation traps", "K线确认陷阱"],
  ], ["Pin bar rejection", "Engulfing candle", "Doji indecision", "Strong close confirmation"]),
  moduleDef("KC_TECHNICAL_012", "technical", "Breakout, Retest, and False Breakout", "突破、回踩与假突破", [
    ["Breakout", "突破"],
    ["Retest", "回踩"],
    ["Breakout retest entry", "突破回踩进场"],
    ["False breakout", "假突破"],
    ["Failed breakout", "失败突破"],
    ["Confirmation after breakout", "突破后的确认"],
  ], ["Breakout", "Breakout retest", "False breakout", "Failed breakout warning"]),
  moduleDef("KC_TECHNICAL_013", "technical", "Ranging Market and Consolidation", "震荡市场与整理", [
    ["Ranging market", "震荡市场"],
    ["Consolidation", "整理"],
    ["Sideway condition", "横盘环境"],
    ["ADX concept", "ADX 概念"],
    ["Avoiding bad entries", "避免低质量进场"],
    ["Range high and range low", "区间高低点"],
    ["Trading or avoiding ranges", "交易或避开震荡"],
  ], ["Range high and range low", "Consolidation box", "ADX low strength concept", "No trade range condition"]),
  moduleDef("KC_TECHNICAL_014", "technical", "Trade Entry, Stop Loss, and Take Profit", "进场、止损与止盈", [
    ["Entry trigger", "进场触发"],
    ["Confirmation entry", "确认进场"],
    ["Stop loss placement", "止损位置"],
    ["Take profit placement", "止盈位置"],
    ["Invalid setup", "无效交易机会"],
    ["Risk reward execution", "风险回报执行"],
  ], ["Entry SL TP", "Invalid setup", "Confirmation entry", "Risk reward execution"]),
  moduleDef("KC_TECHNICAL_015", "technical", "Trade Management and Market Review", "交易管理与市场复盘", [
    ["Managing open trades", "管理持仓"],
    ["Partial close", "部分平仓"],
    ["Break even", "保本"],
    ["Trailing stop concept", "移动止损概念"],
    ["Reviewing trade outcome", "复盘交易结果"],
    ["Valid versus invalid trade idea", "有效与无效交易想法"],
    ["No trade condition", "不交易条件"],
  ], ["Break even management", "Partial close visual", "Trailing stop concept", "Trade review workflow"]),

  moduleDef("KC_PSYCHOLOGY_001", "psychology", "Discipline and Patience", "纪律与耐心", [
    ["Waiting for setup", "等待交易机会"],
    ["Following rules", "遵守规则"],
    ["Avoiding random entries", "避免随机进场"],
    ["Patience", "耐心"],
    ["Process over outcome", "过程优先于单次结果"],
  ], ["Trading discipline checklist", "Wait for setup flow", "Rule based decision process"]),
  moduleDef("KC_PSYCHOLOGY_002", "psychology", "FOMO and Revenge Trading", "FOMO 与报复性交易", [
    ["FOMO", "FOMO"],
    ["Chasing price", "追价"],
    ["Revenge trading", "报复性交易"],
    ["Increasing lot after loss", "亏损后加大手数"],
    ["Emotional entry", "情绪化进场"],
    ["Trade addiction warning signs", "交易成瘾警讯"],
  ], ["FOMO chase", "Revenge trading cycle", "Loss leading to over risk flow"]),
  moduleDef("KC_PSYCHOLOGY_003", "psychology", "Risk Discipline and Position Sizing", "风险纪律与仓位控制", [
    ["Risk per trade", "单笔风险"],
    ["Avoiding all in", "避免满仓"],
    ["Lot size discipline", "手数纪律"],
    ["Daily loss limit", "每日亏损限制"],
    ["Account protection", "账户保护"],
    ["Survival mindset", "长期生存思维"],
  ], ["Position sizing risk", "Account protection", "Daily loss limit flow"]),
  moduleDef("KC_PSYCHOLOGY_004", "psychology", "Handling Losses and Winning Streaks", "处理亏损与连胜", [
    ["Accepting losses", "接受亏损"],
    ["Losing streak", "连亏"],
    ["Winning streak", "连胜"],
    ["Overconfidence", "过度自信"],
    ["Fear after loss", "亏损后的恐惧"],
    ["Greed after win", "盈利后的贪婪"],
  ], ["Emotional cycle after loss", "Winning streak overconfidence", "Reset after loss workflow"]),
  moduleDef("KC_PSYCHOLOGY_005", "psychology", "Trading Journal and Self Review", "交易日志与自我复盘", [
    ["Journaling", "记录交易日志"],
    ["Screenshot review", "截图复盘"],
    ["Mistake tracking", "错误追踪"],
    ["Pattern recognition", "模式识别"],
    ["Rule improvement", "规则改进"],
    ["Emotional notes", "情绪记录"],
  ], ["Trading journal template", "Review workflow", "Mistake tracking loop"]),
  moduleDef("KC_PSYCHOLOGY_006", "psychology", "Prop Firm and Funded Account Psychology", "Prop Firm 与资金账户心理", [
    ["Passing challenge versus staying funded", "通过挑战与长期留在资金账户"],
    ["Drawdown discipline", "回撤纪律"],
    ["Consistency", "一致性"],
    ["Not overtrading funded account", "资金账户避免过度交易"],
    ["Pressure handling", "压力处理"],
    ["Long term survival", "长期生存"],
  ], ["Challenge phase versus funded phase", "Drawdown discipline", "Funded account survival checklist"]),
];

function moduleDef(id, category, titleEn, titleZh, coverPairs, imageTitles) {
  return {id, category, titleEn, titleZh, coverPairs, imageTitles};
}

function main() {
  fs.mkdirSync(path.dirname(seedPath), {recursive: true});
  fs.mkdirSync(imageRoot, {recursive: true});

  const docs = modules.map((module, index) => buildModule(module, index + 1));
  for (const doc of docs) {
    for (const image of doc.imageSections) {
      const filePath = path.join(projectRoot, image.assetPath);
      fs.mkdirSync(path.dirname(filePath), {recursive: true});
      fs.writeFileSync(filePath, buildSvg(doc, image), "utf8");
    }
  }

  fs.writeFileSync(seedPath, `${JSON.stringify(docs, null, 2)}\n`, "utf8");
  const counts = docs.reduce((map, doc) => {
    map[doc.category] = (map[doc.category] || 0) + 1;
    return map;
  }, {});
  const imageCount = docs.reduce((sum, doc) => sum + doc.imageSections.length, 0);
  console.log(`Generated ${docs.length} Knowledge Centre modules at ${seedPath}`);
  console.log(`Generated ${imageCount} SVG diagrams under ${imageRoot}`);
  console.log(JSON.stringify(counts, null, 2));
}

function buildModule(module, sequence) {
  const order = sequence * 10;
  const enCovers = module.coverPairs.map(([en]) => en);
  const zhCovers = module.coverPairs.map(([, zh]) => zh);
  const images = module.imageTitles.map((title, index) => imageSection(module, title, index + 1));
  const firstImage = images[0]?.assetPath || "";
  const enLesson = lessonContent(module.titleEn, enCovers, module.category, "en");
  const zhLesson = lessonContent(module.titleZh, zhCovers, module.category, "zh");
  const locales = {
    en: {
      title: module.titleEn,
      shortDescription: shortDescription(module.titleEn, enCovers, "en"),
      learningObjectives: enLesson.learningObjectives,
      beginnerExplanation: enLesson.beginnerExplanation,
      practicalExplanation: enLesson.practicalExplanation,
      fullContent: enLesson.fullContent,
      stepByStepGuide: enLesson.stepByStepGuide,
      keyPoints: enLesson.keyPoints,
      examples: enLesson.examples,
      commonMistakes: enLesson.commonMistakes,
      checklist: enLesson.checklist,
      relatedConcepts: enLesson.relatedConcepts,
    },
    zh: {
      title: module.titleZh,
      shortDescription: shortDescription(module.titleZh, zhCovers, "zh"),
      learningObjectives: zhLesson.learningObjectives,
      beginnerExplanation: zhLesson.beginnerExplanation,
      practicalExplanation: zhLesson.practicalExplanation,
      fullContent: zhLesson.fullContent,
      stepByStepGuide: zhLesson.stepByStepGuide,
      keyPoints: zhLesson.keyPoints,
      examples: zhLesson.examples,
      commonMistakes: zhLesson.commonMistakes,
      checklist: zhLesson.checklist,
      relatedConcepts: zhLesson.relatedConcepts,
    },
  };

  return {
    knowledgeCenterId: module.id,
    category: module.category,
    order,
    isActive: true,
    status: true,
    approval_status: "approved",
    contentVersion: 2,
    qualityStatus: "production_ready",
    relatedQuestionTarget: 0,
    futureFinalExamMappingReady: true,
    mappedQuestionIds: [],
    thumbnailAssetPath: firstImage,
    imageSections: images,
    locales,
    title: {en: locales.en.title, zh: locales.zh.title},
    description: {
      en: locales.en.shortDescription,
      zh: locales.zh.shortDescription,
    },
    title_i18n: {en: locales.en.title, zh: locales.zh.title},
    description_i18n: {
      en: locales.en.shortDescription,
      zh: locales.zh.shortDescription,
    },
    file_path: "",
    image_url: "",
    seedMeta: {
      contentVersion: seedContentVersion,
      generatedBy: "tools/generate_knowledge_centres_30.js",
    },
  };
}

function imageSection(module, titleEn, index) {
  const snakeId = module.id.toLowerCase();
  const concept = conceptForTitle(titleEn);
  const assetName = `${snakeId}_${assetSlugForTitle(titleEn, concept)}.svg`;
  const imageId = `${module.id}_IMG_${String(index).padStart(3, "0")}`;
  const titleZh = imageTitleZh(titleEn);
  return {
    imageId,
    type: diagramType(titleEn, module.category),
    assetPath: `${assetPrefix}/${assetName}`,
    storagePath: null,
    imageUrl: null,
    sortOrder: index,
    concept,
    qualityStatus: "production_ready",
    locales: {
      en: {
        title: `${titleEn} Diagram`,
        caption: caption(titleEn, module.titleEn, "en"),
        description: imageDescription(titleEn, module.titleEn, "en"),
        altText: `Educational trading diagram for ${titleEn.toLowerCase()} in ${module.titleEn}.`,
      },
      zh: {
        title: `${titleZh}图解`,
        caption: caption(titleZh, module.titleZh, "zh", titleEn),
        description: imageDescription(titleZh, module.titleZh, "zh", titleEn),
        altText: `${module.titleZh}课程中的${titleZh}教学图。`,
      },
    },
  };
}

function conceptForTitle(title) {
  const overrides = {
    "Base and quote currency": "base_quote_currency",
    "Bid and ask price": "bid_ask_price",
    "Trading cost diagram": "trading_costs",
    "Margin and leverage": "leverage_margin",
    "Balance versus equity": "balance_equity",
    "Buy limit versus buy stop": "buy_limit_buy_stop",
    "Sell limit versus sell stop": "sell_limit_sell_stop",
    "Stop loss and take profit placement": "stop_loss_take_profit",
    "One to two risk reward": "risk_reward_1_to_2",
    "Risk per trade concept": "risk_per_trade",
    "Session overlap activity": "session_overlap",
    "Liquidity and volatility comparison": "liquidity_volatility",
    "Interest rate impact concept": "interest_rate_impact",
    "Economic calendar event impact": "economic_calendar_impact",
    "Trader broker liquidity provider flow": "trader_broker_liquidity_provider",
    "Demo versus live account comparison": "demo_vs_live",
    "Execution quality and slippage": "execution_slippage",
    "Body and wick key level zone": "body_wick_key_level_zone",
    "Wick rejection at support": "wick_rejection_support",
    "Wick rejection at resistance": "wick_rejection_resistance",
    "Strong SNR zone with multiple reactions": "strong_snr_multiple_reactions",
    "Failed SNR rejection example": "failed_snr_rejection",
    "Channel": "channel_structure",
    "Trendline break and retest": "trendline_break_retest",
    "HH HL uptrend structure": "hh_hl_uptrend",
    "LH LL downtrend structure": "lh_ll_downtrend",
    "Swing high and swing low": "swing_high_swing_low",
    "Invalid break example": "invalid_structure_break",
    "Mitigated versus fresh order block": "fresh_vs_mitigated_order_block",
    "Imbalance candle movement": "imbalance_move",
    "Indicator false signal warning": "indicator_false_signal",
    "Range high and range low": "range_high_low",
    "ADX low strength concept": "adx_low_strength",
    "Partial close visual": "partial_close",
    "Trailing stop concept": "trailing_stop",
    "Loss leading to over risk flow": "loss_to_overrisk_flow",
    "Daily loss limit flow": "daily_loss_limit",
    "Challenge phase versus funded phase": "challenge_vs_funded_phase",
  };
  return overrides[title] || slug(title);
}

function assetSlugForTitle(title, concept) {
  const overrides = {
    base_quote_currency: "base_quote_currency",
    bid_ask_price: "bid_ask_price",
    trading_costs: "trading_costs",
    leverage_margin: "leverage_margin",
    balance_equity: "balance_equity",
    buy_limit_buy_stop: "buy_limit_buy_stop",
    sell_limit_sell_stop: "sell_limit_sell_stop",
    stop_loss_take_profit: "stop_loss_take_profit",
    risk_reward_1_to_2: "risk_reward_1_to_2",
    risk_per_trade: "risk_per_trade",
    session_overlap: "session_overlap",
    liquidity_volatility: "liquidity_volatility",
    interest_rate_impact: "interest_rate_impact",
    economic_calendar_impact: "economic_calendar_impact",
    trader_broker_liquidity_provider: "trader_broker_liquidity_provider",
    demo_vs_live: "demo_vs_live",
    execution_slippage: "execution_slippage",
    body_wick_key_level_zone: "body_wick_key_level_zone",
    wick_rejection_support: "wick_rejection_support",
    wick_rejection_resistance: "wick_rejection_resistance",
    strong_snr_multiple_reactions: "strong_snr_multiple_reactions",
    failed_snr_rejection: "failed_snr_rejection",
    channel_structure: "channel_structure",
    trendline_break_retest: "trendline_break_retest",
    hh_hl_uptrend: "hh_hl_uptrend",
    lh_ll_downtrend: "lh_ll_downtrend",
    swing_high_swing_low: "swing_high_swing_low",
    invalid_structure_break: "invalid_structure_break",
    fresh_vs_mitigated_order_block: "fresh_vs_mitigated_order_block",
    imbalance_move: "imbalance_move",
    indicator_false_signal: "indicator_false_signal",
    range_high_low: "range_high_low",
    adx_low_strength: "adx_low_strength",
    partial_close: "partial_close",
    trailing_stop: "trailing_stop",
    loss_to_overrisk_flow: "loss_to_overrisk_flow",
    daily_loss_limit: "daily_loss_limit",
    challenge_vs_funded_phase: "challenge_vs_funded_phase",
  };
  return overrides[concept] || slug(title);
}

function shortDescription(title, covers, language) {
  if (language === "zh") {
    return `学习${title}，理解${covers.slice(0, 4).join("、")}，并把概念转化为可复盘的交易流程。`;
  }
  return `Learn ${title.toLowerCase()} through ${covers.slice(0, 4).join(", ")} and turn the concept into a reviewable trading process.`;
}

function lessonContent(title, covers, category, language) {
  return language === "zh"
    ? zhLessonContent(title, covers, category)
    : enLessonContent(title, covers, category);
}

function enLessonContent(title, covers, category) {
  const primary = covers[0] || title;
  const secondary = covers[1] || title;
  const third = covers[2] || primary;
  const fourth = covers[3] || secondary;
  const context = categoryContext(category, "en");
  const coversText = covers.join(", ");
  const paragraphs = [
    `FOUNDATION\n${title} is a practical lesson about ${coversText}. The purpose is not to memorise terms, but to understand how these ideas change real trading decisions before money is at risk. A trader who understands ${primary.toLowerCase()} can describe what is happening, why it matters, and what must be checked before entry. A trader who only recognises the word may still enter randomly, oversize a position, or misunderstand normal market movement. This lesson therefore treats the topic as part of a complete process: read the environment, define the condition, wait for evidence, plan invalidation, size risk, execute only if the plan is clear, and review the outcome afterwards.`,
    `BEGINNER VIEW\nFor a beginner, ${primary.toLowerCase()} should be studied slowly and connected to simple chart observations. Ask what the market is showing, where the important prices are, and whether the situation is clear enough to act. ${secondary} is not a magic signal and it does not guarantee that the next candle will move in a desired direction. It is a piece of context. The safest learning habit is to describe the condition in plain language first, then compare it with the written trading rules. If the condition cannot be explained without confusion, it is usually better to observe, screenshot the chart, and review it later rather than trade immediately.`,
    `PRACTICAL TRADING USE\nIn practice, ${title.toLowerCase()} helps traders build a decision checklist. ${context} When ${primary.toLowerCase()} appears, the trader should check whether ${secondary.toLowerCase()} supports the idea, whether ${third.toLowerCase()} changes the risk, and whether ${fourth.toLowerCase()} affects timing. The goal is to separate a clean opportunity from a situation that only looks interesting because price is moving quickly. A professional process also considers spread, volatility, session behaviour, news risk, and emotional state. These filters do not remove uncertainty, but they can reduce impulsive entries and make the trade easier to review.`,
    `STEP 1: DEFINE THE MARKET CONDITION\nStart with the higher context. Mark the relevant area, structure, session, cost, or psychological condition before thinking about entry. If the lesson topic is technical, identify the zone, swing, candle behaviour, or indicator condition on a clean chart. If it is theory, connect the term to order execution, cost, margin, or platform behaviour. If it is psychology, identify the decision pressure and the rule that protects the account. This step matters because many weak trades happen when a trader sees one attractive detail and ignores the environment around it.`,
    `STEP 2: CONNECT THE CONCEPT TO RISK\nEvery part of ${title.toLowerCase()} must eventually connect back to risk control. Before entry, the trader should know where the idea is wrong, where the stop loss would logically sit, how much the trade can lose, and whether the potential reward justifies the risk. A setup can look clean and still lose. A news event can widen spreads. A strong level can break. A disciplined trader accepts that uncertainty and plans for it instead of increasing lot size or moving the stop after entry. The concept is useful only when it improves planning, not when it becomes an excuse to take more risk.`,
    `STEP 3: WAIT FOR CONFIRMATION\nConfirmation means evidence that matches the plan. It may be a candle close, a rejection pattern, a retest, a lower-timeframe structure shift, a session condition, or simply the absence of a warning sign. Confirmation should be defined before the trade, not invented after price has already moved. If ${secondary.toLowerCase()} conflicts with the plan, the trade should be skipped or reduced. Waiting is part of professional execution. It prevents the trader from buying only because price is rising, selling only because price is falling, or entering because of fear of missing out.`,
    `STEP 4: EXECUTE WITH A WRITTEN PLAN\nA written plan should include entry reason, invalidation, stop loss, take profit idea, risk percentage, position size, and management rules. For this module, the plan should specifically mention how ${primary.toLowerCase()} and ${secondary.toLowerCase()} are being used. If the concept does not change the entry, stop, target, or risk, it may only be background information. That is still valuable, but it should not be treated as a trade trigger. Good execution is not about predicting perfectly. It is about taking only the trades that fit the rule set and accepting the planned outcome.`,
    `STEP 5: REVIEW THE RESULT\nAfter the trade or study example is complete, review the process rather than only the profit or loss. Record whether the concept was identified correctly, whether confirmation appeared, whether risk was controlled, and whether emotions affected the decision. A losing trade can still be a valid execution if the rules were followed. A winning trade can still be poor execution if the trader ignored risk or entered randomly. Over many examples, this review turns ${title.toLowerCase()} from a lesson topic into a practical skill that can be measured and improved.`,
    `COMMON REAL-WORLD CONTEXT\nMarkets often look clean in hindsight and messy in real time. Spreads change, candles form and fail, sessions shift liquidity, and emotions become stronger when real money is involved. This is why the lesson should be practised on historical charts, replay tools, demo accounts, and journal screenshots before being used in live decisions. The trader should collect examples of clean conditions, unclear conditions, and failed conditions. That comparison teaches judgement. The aim is not to force every chart to match the lesson, but to recognise when the lesson genuinely applies.`,
    `RISK-AWARE CONCLUSION\n${title} can improve trading education when it is used as part of a complete framework. It can help identify context, organise observations, and create a repeatable review process. It cannot remove uncertainty, guarantee profit, or replace risk management. Before using this concept in a live account, a trader should be able to explain it, identify valid and invalid examples, define risk before entry, and accept that skipping unclear trades is a professional decision. The best outcome of this module is not confidence without limits; it is clearer judgement, better patience, and more consistent risk control.`,
  ];
  return {
    learningObjectives: [
      `Explain ${primary.toLowerCase()} and why it matters in a trading plan.`,
      `Connect ${secondary.toLowerCase()} to context, confirmation, and risk control.`,
      `Identify valid and weak examples of ${title.toLowerCase()} without assuming certainty.`,
      `Build a reviewable checklist for applying the lesson in demo or live conditions.`,
    ],
    beginnerExplanation: `${title} should be learned as a decision framework, not as a shortcut. A beginner should first describe ${primary.toLowerCase()} in simple words, then check how ${secondary.toLowerCase()} changes risk, timing, or interpretation. The concept may help organise a trade idea, but it must still be confirmed and managed with a predefined stop loss.`,
    practicalExplanation: `In practical trading, use this module to decide what must be seen before entry, what would invalidate the idea, and how the trade will be reviewed afterwards. Combine ${covers.slice(0, 3).join(", ")} with session context, spread cost, position sizing, and emotional discipline before committing risk.`,
    fullContent: paragraphs.join("\n\n"),
    stepByStepGuide: [
      `Mark the relevant context for ${primary.toLowerCase()} before thinking about entry.`,
      `Check whether ${secondary.toLowerCase()} supports or conflicts with the trade idea.`,
      "Define invalidation, stop loss, target logic, and maximum risk before execution.",
      "Wait for confirmation that was written into the plan before the trade.",
      "Skip the trade if cost, news, volatility, or emotions make the setup unclear.",
      "Journal the example with screenshots and review whether the rule was followed.",
    ],
    keyPoints: [
      `${primary} is useful only when it improves decision quality.`,
      `${secondary || title} should be confirmed by context instead of used alone.`,
      "A valid setup can still lose, so risk must be planned before entry.",
      "Clear invalidation is more important than hoping price will recover.",
      "Skipping unclear conditions is a professional trading decision.",
      "Journal review should measure rule-following, not only profit or loss.",
    ],
    examples: [
      `If a trader sees ${primary.toLowerCase()} during active market conditions, they can wait for confirmation and then decide whether the risk plan is acceptable.`,
      `If ${secondary.toLowerCase()} appears but the stop loss would be too wide, the trader can skip the setup instead of reducing discipline.`,
      `If price moves quickly during news and ${third.toLowerCase()} becomes unclear, the safer decision may be to wait until spreads and volatility normalise.`,
      `If a trade follows the plan but loses, the journal can still mark it as valid execution and focus on whether the risk was controlled.`,
    ],
    commonMistakes: [
      `Treating ${primary.toLowerCase()} as a guaranteed signal.`,
      "Entering before confirmation because price is moving quickly.",
      "Planning the stop loss after entry instead of before entry.",
      "Ignoring spread, session, news, or volatility conditions.",
      "Increasing lot size after a loss to recover faster.",
      "Judging the lesson only by one winning or losing trade.",
    ],
    checklist: [
      `I can explain ${title.toLowerCase()} in my own words.`,
      `I can identify where ${primary.toLowerCase()} is valid and where it is weak.`,
      "I have defined confirmation before entry.",
      "I know the invalidation level and stop-loss logic.",
      "My position size matches the planned account risk.",
      "I am willing to skip the trade if the condition is unclear.",
      "I will record the trade or study example in my journal.",
    ],
    relatedConcepts: relatedConcepts(covers, category, "en"),
  };
}

function zhLessonContent(title, covers, category) {
  const primary = covers[0] || title;
  const secondary = covers[1] || title;
  const third = covers[2] || primary;
  const fourth = covers[3] || secondary;
  const context = categoryContext(category, "zh");
  const coversText = covers.join("、");
  const paragraphs = [
    `基础理解\n《${title}》是一节关于${coversText}的实用课程。学习目标不是背诵术语，而是理解这些概念如何影响真实交易决策。交易者需要知道市场正在显示什么、为什么重要、哪些条件支持交易想法、哪些条件会让交易想法失效。只认识名词并不足够，因为真实市场会出现点差变化、滑点、假突破、情绪压力和不确定结果。本课程把概念放进完整流程：先理解环境，再定义条件，然后等待确认，最后用明确止损、合理仓位和复盘记录来管理风险。`,
    `新手视角\n对新手来说，${primary}应该用简单语言解释，而不是被当成快速获利技巧。你可以先问自己：这个现象在哪里出现？它和${secondary}有什么关系？它是否真的提高了决策质量？如果无法清楚解释，就不应该急着进场。交易学习的重点是建立判断力，而不是寻找永远有效的信号。任何概念都可能失败，所以每一次观察都必须配合风险计划、确认条件和复盘习惯。`,
    `实际交易用途\n在实际交易中，${title}可以帮助交易者建立检查清单。${context}当你看到${primary}时，需要同时检查${secondary}是否支持想法，${third}是否改变风险，${fourth}是否影响入场时机。专业交易者不会只因为图表看起来好看就进场，而是会检查点差、波动、新闻、交易时段、仓位大小和情绪状态。这些过滤条件不能消除不确定性，但可以减少冲动入场，让交易更容易被复盘。`,
    `第一步：定义市场条件\n先从整体环境开始，不要直接寻找入场点。如果是技术分析主题，就标记区域、摆动点、K线反应、结构或指标状态；如果是理论主题，就把概念连接到报价、成本、保证金、订单执行或平台操作；如果是心理主题，就识别当下的情绪压力和保护账户的规则。很多低质量交易都来自只看到一个细节，却忽略了更大的背景。清楚定义环境，是避免随机交易的第一步。`,
    `第二步：连接风险管理\n${title}最终必须回到风险控制。进场前，交易者需要知道哪里代表判断错误，止损应该放在哪里，单笔最多亏损多少，潜在回报是否值得承担风险。再清晰的机会也可能失败，再强的区域也可能被突破，新闻也可能让点差扩大。成熟交易者会接受不确定性，并提前计划，而不是亏损后移动止损、加大手数或用情绪弥补错误。概念只有帮助风险规划时才真正有价值。`,
    `第三步：等待确认\n确认是指市场出现与计划一致的证据，可能是K线收盘、拒绝形态、回测、低周期结构变化、时段环境，或没有明显警讯。确认条件必须在进场前写清楚，不能等价格已经走远后才临时解释。如果${secondary}与计划冲突，交易者应该跳过或降低风险。等待确认不是犹豫，而是专业执行的一部分。它能减少追涨杀跌、害怕错过和情绪化入场。`,
    `第四步：按计划执行\n交易计划应包括入场理由、失效条件、止损位置、止盈逻辑、风险比例、仓位大小和管理规则。对于本课程，计划中还应说明${primary}和${secondary}如何被使用。如果某个概念没有改变入场、止损、目标或风险，它可能只是背景信息，而不是交易触发。背景信息仍然有价值，但不能被误用为进场理由。好的执行不是完美预测，而是只执行符合规则的机会。`,
    `第五步：复盘结果\n交易结束后，应复盘过程，而不是只看盈亏。记录概念是否识别正确，确认是否真的出现，风险是否按计划控制，情绪是否影响了决策。亏损交易也可能是有效执行，只要规则被遵守；盈利交易也可能是低质量交易，如果它来自随机入场或忽略风险。长期复盘可以把${title}从课堂知识变成可衡量、可改进的交易技能。`,
    `真实市场背景\n历史图表常常看起来清楚，但实时市场更复杂。价格会反复测试区域，K线会形成又失败，流动性会随时段改变，真实资金会放大情绪。交易者应该用历史图表、回放工具、模拟账户和日志截图来练习，把清晰案例、模糊案例和失败案例都收集起来。对比这些案例才能培养判断力。学习目标不是把每张图都套进课程，而是知道什么时候课程真的适用，什么时候应该放弃交易。`,
    `风险意识总结\n${title}能帮助交易者整理观察、建立流程和提高复盘质量，但它不能保证盈利，也不能代替风险管理。使用它之前，交易者应该能够解释概念、识别有效与无效案例、提前定义风险，并接受跳过不清晰机会是专业选择。课程真正的价值不是让交易者盲目自信，而是让判断更清楚、等待更有耐心、风险控制更一致。`,
  ];
  return {
    learningObjectives: [
      `理解${primary}的含义，以及它为什么会影响交易计划。`,
      `把${secondary}连接到市场环境、确认条件和风险控制。`,
      `识别${title}中的有效案例和较弱案例，而不是假设一定成功。`,
      `建立可执行、可复盘的检查清单。`,
    ],
    beginnerExplanation: `${title}应该被当成决策框架来学习，而不是捷径。新手需要先用自己的话解释${primary}，再检查${secondary}如何影响风险、时机或判断。这个概念可以帮助整理交易想法，但仍然需要确认、止损和仓位控制。`,
    practicalExplanation: `实际交易中，请用本课程决定进场前必须看到什么、什么情况代表想法失效，以及交易后如何复盘。把${covers.slice(0, 3).join("、")}与交易时段、点差成本、仓位计算和情绪纪律结合后，才考虑承担风险。`,
    fullContent: paragraphs.join("\n\n"),
    stepByStepGuide: [
      `先标记与${primary}相关的市场环境。`,
      `检查${secondary}是否支持或否定交易想法。`,
      "进场前定义失效条件、止损、目标逻辑和最大风险。",
      "等待计划中写好的确认条件出现。",
      "如果成本、新闻、波动或情绪让机会不清晰，就跳过交易。",
      "用截图和日志复盘规则是否被执行。",
    ],
    keyPoints: [
      `${primary}只有在提高决策质量时才有价值。`,
      `${secondary || title}需要结合环境确认，不能单独使用。`,
      "有效机会也可能亏损，所以风险必须提前规划。",
      "清楚的失效条件比希望价格反转更重要。",
      "跳过模糊机会是专业交易决定。",
      "复盘应评估是否遵守规则，而不只是看盈亏。",
    ],
    examples: [
      `如果交易者在活跃市场中看到${primary}，可以先等待确认，再判断风险计划是否合理。`,
      `如果${secondary}出现，但止损距离过大，交易者可以跳过机会，而不是强行降低纪律。`,
      `如果新闻期间价格快速波动且${third}变得不清晰，更稳健的选择是等待点差和波动恢复正常。`,
      `如果交易按计划执行但最终亏损，日志仍可标记为有效执行，并检查风险是否受控。`,
    ],
    commonMistakes: [
      `把${primary}当成保证结果的信号。`,
      "因为价格快速移动就在确认前进场。",
      "进场后才临时决定止损位置。",
      "忽略点差、交易时段、新闻或波动环境。",
      "亏损后加大手数试图快速追回。",
      "只用单笔盈亏判断课程是否有效。",
    ],
    checklist: [
      `我能用自己的话解释${title}。`,
      `我能分辨${primary}什么时候有效、什么时候较弱。`,
      "我已经在进场前定义确认条件。",
      "我知道失效位置和止损逻辑。",
      "我的仓位大小符合账户风险计划。",
      "如果条件不清晰，我愿意跳过交易。",
      "我会把交易或学习案例写入日志复盘。",
    ],
    relatedConcepts: relatedConcepts(covers, category, "zh"),
  };
}

function categoryContext(category, language) {
  if (language === "zh") {
    if (category === "technical") {
      return "目标是读懂结构、区域、动能和确认，而不是假设某个形态一定会成功。";
    }
    if (category === "psychology") {
      return "目标是在压力、恐惧、贪婪或兴奋出现时保护决策质量。";
    }
    return "目标是在承担风险前理解交易环境、成本和执行条件。";
  }
  if (category === "technical") {
    return "The goal is to read structure, zones, momentum, and confirmation without assuming that a pattern must work.";
  }
  if (category === "psychology") {
    return "The goal is to protect decision quality when pressure, fear, greed, or excitement appears.";
  }
  return "The goal is to understand the trading environment, cost, and execution conditions before risk is committed.";
}

function relatedConcepts(covers, category, language) {
  const extras = language === "zh"
    ? category === "technical"
      ? ["市场结构", "确认入场", "风险回报"]
      : category === "psychology"
        ? ["交易纪律", "风险控制", "交易日志"]
        : ["点差成本", "仓位管理", "交易计划"]
    : category === "technical"
      ? ["Market structure", "Confirmation entry", "Risk reward"]
      : category === "psychology"
        ? ["Trading discipline", "Risk control", "Trading journal"]
        : ["Spread cost", "Position sizing", "Trade planning"];
  return [...covers.slice(0, 3), ...extras].slice(0, 6);
}

function fullContent(title, covers, category, language) {
  if (language === "zh") {
    return `《${title}》帮助交易者建立清晰、可执行、可复盘的判断框架。本课重点包括${covers.join("、")}。这些内容不是盈利承诺，也不能替代风险管理；它们的作用是帮助你先理解市场环境，再等待确认，最后用明确的止损和合理仓位执行交易。学习时请把每个概念写成规则，例如什么条件代表有效、什么情况代表无效、什么时候应该放弃交易。完成课程后，建议用历史图表或模拟账户练习，并把观察、进场原因、情绪状态和复盘结论记录下来。`;
  }
  const categoryLine = category === "psychology"
    ? "The goal is to protect decision quality when pressure, fear, or excitement appears."
    : category === "technical"
      ? "The goal is to read structure and confirmation without assuming any setup must work."
      : "The goal is to understand the trading environment before risk is placed in the market.";
  return `${title} gives traders a clear, practical, and reviewable framework. This lesson covers ${covers.join(", ")}. ${categoryLine} None of these ideas is a profit promise or financial advice. Use the lesson to define context, wait for confirmation, plan invalidation, and size risk before entry. After studying, practise on historical charts or demo conditions and record what you observed, why you acted, how you felt, and what should improve next.`;
}

function keyPoints(title, covers, language) {
  if (language === "zh") {
    return [
      `${covers[0]}必须放在整体市场环境中理解。`,
      `${covers[1] || title}需要配合确认，而不是单独作为进场理由。`,
      `任何交易想法都要先定义失效条件、止损位置和单笔风险。`,
      `复盘时要记录规则是否被执行，而不只记录单次盈亏。`,
    ];
  }
  return [
    `${covers[0]} should be understood inside the wider market context.`,
    `${covers[1] || title} should support confirmation, not become a standalone entry reason.`,
    "Every trade idea needs invalidation, stop placement, and risk size before execution.",
    "Review whether the rule was followed, not only whether the individual trade won or lost.",
  ];
}

function examples(title, covers, language) {
  if (language === "zh") {
    return [
      `如果图表或交易计划出现${covers[0]}，交易者可以先观察环境，再等待确认，而不是马上进场。`,
      `当${covers[1] || title}与风险计划不一致时，更稳健的选择通常是跳过交易并记录原因。`,
    ];
  }
  return [
    `If a chart or trade plan shows ${covers[0].toLowerCase()}, a trader can observe the context and wait for confirmation instead of entering immediately.`,
    `When ${covers[1]?.toLowerCase() || title.toLowerCase()} conflicts with the risk plan, the more disciplined choice is often to skip the trade and record why.`,
  ];
}

function commonMistakes(title, covers, language) {
  if (language === "zh") {
    return [
      `把${covers[0]}当成保证结果的信号。`,
      `没有等待确认就提前进场，或在不清楚止损位置时进场。`,
      `忽略更高周期、新闻环境、点差成本或当天情绪状态。`,
    ];
  }
  return [
    `Treating ${covers[0].toLowerCase()} as a guaranteed signal.`,
    "Entering before confirmation or before the stop-loss location is clear.",
    "Ignoring higher timeframe context, news risk, spread cost, or the trader's emotional state.",
  ];
}

function checklist(title, covers, language) {
  if (language === "zh") {
    return [
      `我能用自己的话解释${title}。`,
      `我知道哪些条件支持${covers[0]}，哪些条件会让它失效。`,
      `我会在进场前确认止损、目标、仓位和最大风险。`,
      `我会在交易后把执行过程写入日志并复盘。`,
    ];
  }
  return [
    `I can explain ${title.toLowerCase()} in my own words.`,
    `I know what supports ${covers[0].toLowerCase()} and what would invalidate it.`,
    "I confirm stop loss, target, position size, and maximum risk before entry.",
    "I record the execution and review it after the trade.",
  ];
}

function caption(title, moduleTitle, language, lookupTitle = title) {
  const copy = imageCopy(lookupTitle, language);
  if (copy?.caption) return copy.caption;
  if (language === "zh") {
    return `此图帮助你在${moduleTitle}中识别${title}，并把观察转化为有规则的判断。`;
  }
  return `This diagram helps identify ${title.toLowerCase()} inside ${moduleTitle.toLowerCase()} and turn observation into rule-based judgement.`;
}

function imageDescription(title, moduleTitle, language, lookupTitle = title) {
  const copy = imageCopy(lookupTitle, language);
  if (copy?.description) return copy.description;
  if (language === "zh") {
    return `图中使用价格路径、区域、箭头或流程形状展示${title}。它用于学习和复盘，不代表任何市场一定会按图运行。`;
  }
  return `The visual uses price paths, zones, arrows, or workflow shapes to show ${title.toLowerCase()}. It is for learning and review, not a promise that a market will behave this way.`;
}

function imageCopy(title, language) {
  const en = {
    "Spread bid ask": {
      caption: "The spread is the gap between BID 1.1000 and ASK 1.1002.",
      description: "The diagram separates the bid and ask quotes and marks the 2-pip spread as a trading cost before price movement is considered.",
    },
    "Pip movement": {
      caption: "A move from 1.1000 to 1.1001 is shown as one pip.",
      description: "The price ladder highlights the exact decimal movement used when calculating pip value, stop distance, and trade cost.",
    },
    "Buy limit versus buy stop": {
      caption: "Buy Limit sits below current price; Buy Stop sits above current price.",
      description: "The ladder shows where each buy pending order belongs so traders do not confuse pullback entries with breakout entries.",
    },
    "Sell limit versus sell stop": {
      caption: "Sell Limit sits above current price; Sell Stop sits below current price.",
      description: "The ladder shows how sell pending orders are placed relative to the current market price.",
    },
    "One to two risk reward": {
      caption: "The reward distance is twice the risk distance.",
      description: "Entry, SL, TP, Risk 1R, and Reward 2R are separated so the trade plan can be reviewed before execution.",
    },
    "Support zone": {
      caption: "Price touches the highlighted support area and bounces away from it.",
      description: "The support zone is drawn as an area, not a single line, and the candles show a reaction from that area.",
    },
    "Resistance zone": {
      caption: "Price touches the highlighted resistance area and rejects downward.",
      description: "The resistance zone is drawn above price, with rejection candles showing selling pressure near the area.",
    },
    "Support becomes resistance": {
      caption: "Old support breaks, then price retests the same zone as resistance.",
      description: "The diagram shows the role reversal sequence: support, break below, retest from underneath, then continuation lower.",
    },
    "Resistance becomes support": {
      caption: "Old resistance breaks, then price retests the same zone as support.",
      description: "The diagram shows the role reversal sequence: resistance, break above, retest from above, then continuation higher.",
    },
    "HH HL uptrend structure": {
      caption: "An uptrend is shown through higher highs and higher lows.",
      description: "HH and HL labels mark the swing structure that defines bullish market structure.",
    },
    "LH LL downtrend structure": {
      caption: "A downtrend is shown through lower highs and lower lows.",
      description: "LH and LL labels mark the swing structure that defines bearish market structure.",
    },
    "Bullish BOS": {
      caption: "Price closes beyond the previous swing high to show bullish BOS.",
      description: "The previous high is marked before price breaks above it, separating a continuation break from a random candle move.",
    },
    "Bearish BOS": {
      caption: "Price closes beyond the previous swing low to show bearish BOS.",
      description: "The previous low is marked before price breaks below it, separating a continuation break from a random candle move.",
    },
    "CHOCH reversal": {
      caption: "CHOCH is shown as a structural shift after a protected swing is broken.",
      description: "The diagram marks the broken swing and shows price changing character instead of continuing the prior structure.",
    },
    "Range high and range low": {
      caption: "The market is moving between a clear range high and range low.",
      description: "The range boundaries show why price in the middle of the box often has weaker directional quality.",
    },
  };
  const zh = {
    "Spread bid ask": {
      caption: "点差是 BID 1.1000 与 ASK 1.1002 之间的差距。",
      description: "图中把买价和卖价分开，并标出 2 点点差，说明价格真正移动前已经存在交易成本。",
    },
    "Pip movement": {
      caption: "价格从 1.1000 到 1.1001 的变化代表 1 点。",
      description: "价格阶梯突出小数位变化，帮助计算点值、止损距离和交易成本。",
    },
    "Buy limit versus buy stop": {
      caption: "Buy Limit 在当前价下方，Buy Stop 在当前价上方。",
      description: "价格阶梯展示两种买入挂单的位置，避免把回调入场和突破入场混淆。",
    },
    "Sell limit versus sell stop": {
      caption: "Sell Limit 在当前价上方，Sell Stop 在当前价下方。",
      description: "价格阶梯展示两种卖出挂单相对于当前市场价格的位置。",
    },
    "One to two risk reward": {
      caption: "回报距离是风险距离的两倍。",
      description: "图中分开显示进场、SL、TP、Risk 1R 和 Reward 2R，帮助在执行前检查交易计划。",
    },
    "Support zone": {
      caption: "价格触及高亮支撑区域后向上反弹。",
      description: "支撑被画成区域而不是单一线条，K 线展示价格在该区域出现反应。",
    },
    "Resistance zone": {
      caption: "价格触及高亮阻力区域后向下回落。",
      description: "阻力区域位于价格上方，拒绝K线显示该区域附近的卖压。",
    },
    "Support becomes resistance": {
      caption: "旧支撑被跌破后，价格回测同一区域并把它当作阻力。",
      description: "图中展示角色转换顺序：支撑、跌破、从下方回测，然后继续向下。",
    },
    "Resistance becomes support": {
      caption: "旧阻力被突破后，价格回测同一区域并把它当作支撑。",
      description: "图中展示角色转换顺序：阻力、突破、从上方回测，然后继续向上。",
    },
    "HH HL uptrend structure": {
      caption: "上升结构由 HH 和 HL 组成。",
      description: "HH 与 HL 标签标出摆动结构，用来识别看涨市场结构。",
    },
    "LH LL downtrend structure": {
      caption: "下降结构由 LH 和 LL 组成。",
      description: "LH 与 LL 标签标出摆动结构，用来识别看跌市场结构。",
    },
    "Bullish BOS": {
      caption: "价格有效突破前高，形成看涨 BOS。",
      description: "图中先标出前高，再显示价格向上突破，避免把随机波动误认为结构延续。",
    },
    "Bearish BOS": {
      caption: "价格有效跌破前低，形成看跌 BOS。",
      description: "图中先标出前低，再显示价格向下跌破，避免把随机波动误认为结构延续。",
    },
    "CHOCH reversal": {
      caption: "CHOCH 表示关键摆动被破坏后，结构性质发生改变。",
      description: "图中标出被破坏的摆动点，并显示价格不再延续原本结构。",
    },
    "Range high and range low": {
      caption: "价格在清晰的区间高点和区间低点之间波动。",
      description: "区间边界说明为什么价格位于箱体中间时，方向质量通常较弱。",
    },
  };
  return (language === "zh" ? zh : en)[title];
}

function imageTitleZh(title) {
  const dictionary = {
    "Forex pair concept": "货币对概念",
    "Buy vs sell direction": "买入与卖出方向",
    "CFD trading concept": "CFD 交易概念",
    "Base and quote currency": "基础货币与报价货币",
    "Bid and ask price": "买价与卖价",
    "Price movement example": "价格波动示例",
    "Pip movement": "点数波动",
    "Spread bid ask": "买价卖价点差",
    "Trading cost diagram": "交易成本图解",
    "Lot size comparison": "手数比较",
    "Margin and leverage": "保证金与杠杆",
    "Balance versus equity": "余额与净值",
    "Buy limit versus buy stop": "Buy Limit 与 Buy Stop",
    "Sell limit versus sell stop": "Sell Limit 与 Sell Stop",
    "Stop loss and take profit placement": "止损与止盈位置",
    "One to two risk reward": "一比二风险回报",
    "Trade planning checklist": "交易计划清单",
    "Risk per trade concept": "单笔风险概念",
    "Market session timeline": "市场时段时间线",
    "Liquidity and volatility comparison": "流动性与波动性比较",
    "Session overlap activity": "时段重叠活跃度",
    "High impact news volatility": "高影响新闻波动",
    "Interest rate impact concept": "利率影响概念",
    "Economic calendar event impact": "经济日历事件影响",
    "Trader broker liquidity provider flow": "交易者经纪商流动性流程",
    "Demo versus live account comparison": "模拟与真实账户比较",
    "Execution quality and slippage": "执行质量与滑点",
    "Support zone": "支撑区",
    "Resistance zone": "阻力区",
    "Support becomes resistance": "支撑转阻力",
    "Resistance becomes support": "阻力转支撑",
    "Body and wick key level zone": "实体影线关键区域",
    "Wick rejection at support": "支撑位影线拒绝",
    "Wick rejection at resistance": "阻力位影线拒绝",
    "Strong SNR zone with multiple reactions": "多次反应的强 SNR 区域",
    "Failed SNR rejection example": "失败 SNR 拒绝示例",
    "Uptrend trendline": "上升趋势线",
    "Downtrend trendline": "下降趋势线",
    "Channel": "通道",
    "Trendline break and retest": "趋势线突破回踩",
    "HH HL uptrend structure": "HH/HL 上升结构",
    "LH LL downtrend structure": "LH/LL 下降结构",
    "Swing high and swing low": "摆动高低点",
    "Structure direction comparison": "结构方向比较",
    "Bullish BOS": "看涨 BOS",
    "Bearish BOS": "看跌 BOS",
    "CHOCH reversal": "CHOCH 反转",
    "Invalid break example": "无效突破示例",
    "H4 H1 M15 alignment": "H4/H1/M15 共振",
    "Higher timeframe bias": "高周期方向",
    "Lower timeframe entry confirmation": "低周期进场确认",
    "Timeframe conflict warning": "周期冲突提醒",
    "Supply zone": "供应区",
    "Demand zone": "需求区",
    "Order block": "订单块",
    "Mitigated versus fresh order block": "已回补与新鲜订单块",
    "Fair value gap": "公允价值缺口",
    "Imbalance candle movement": "失衡K线移动",
    "Equal highs liquidity sweep": "相等高点流动性扫单",
    "Equal lows liquidity sweep": "相等低点流动性扫单",
    "Price above EMA trend filter": "价格位于 EMA 上方趋势过滤",
    "Price below EMA trend filter": "价格位于 EMA 下方趋势过滤",
    "EMA dynamic support": "EMA 动态支撑",
    "Multiple EMA alignment": "多条 EMA 排列",
    "RSI overbought": "RSI 超买",
    "RSI oversold": "RSI 超卖",
    "MACD momentum": "MACD 动能",
    "Indicator false signal warning": "指标假信号提醒",
    "Pin bar rejection": "Pin Bar 拒绝",
    "Engulfing candle": "吞没K线",
    "Doji indecision": "十字星犹豫",
    "Strong close confirmation": "强收盘确认",
    "Breakout": "突破",
    "Breakout retest": "突破回踩",
    "False breakout": "假突破",
    "Failed breakout warning": "失败突破提醒",
    "Range high and range low": "区间高低点",
    "Consolidation box": "整理箱体",
    "ADX low strength concept": "ADX 低强度概念",
    "No trade range condition": "不交易震荡环境",
    "Entry SL TP": "进场 SL TP",
    "Invalid setup": "无效交易机会",
    "Confirmation entry": "确认进场",
    "Risk reward execution": "风险回报执行",
    "Break even management": "保本管理",
    "Partial close visual": "部分平仓",
    "Trailing stop concept": "移动止损概念",
    "Trade review workflow": "交易复盘流程",
    "Trading discipline checklist": "交易纪律清单",
    "Wait for setup flow": "等待机会流程",
    "Rule based decision process": "规则化决策流程",
    "FOMO chase": "FOMO 追价",
    "Revenge trading cycle": "报复性交易循环",
    "Loss leading to over risk flow": "亏损导致过度风险流程",
    "Position sizing risk": "仓位风险",
    "Account protection": "账户保护",
    "Daily loss limit flow": "每日亏损限制流程",
    "Emotional cycle after loss": "亏损后的情绪循环",
    "Winning streak overconfidence": "连胜后的过度自信",
    "Reset after loss workflow": "亏损后重置流程",
    "Trading journal template": "交易日志模板",
    "Review workflow": "复盘流程",
    "Mistake tracking loop": "错误追踪循环",
    "Challenge phase versus funded phase": "挑战阶段与资金阶段",
    "Drawdown discipline": "回撤纪律",
    "Funded account survival checklist": "资金账户生存清单",
  };
  return dictionary[title] || title;
}

function diagramType(title, category) {
  const lower = title.toLowerCase();
  if (category === "psychology" || lower.includes("checklist") || lower.includes("workflow") || lower.includes("flow")) return "workflow";
  if (lower.includes("timeline") || lower.includes("session")) return "timeline";
  if (lower.includes("rsi") || lower.includes("macd") || lower.includes("ema") || lower.includes("adx")) return "indicator";
  if (lower.includes("comparison") || lower.includes("versus")) return "comparison";
  return "diagram";
}

function buildSvg(doc, image) {
  const title = image.locales.en.title.replace(/\s+Diagram$/, "");
  const concept = image.concept || slug(title);
  const tone = palette(doc.category);
  const body = buildDiagramBody(concept, title, doc.category, tone);
  return svgShell(image.locales.en.title, concept, tone, body);
}

function palette(category) {
  if (category === "technical") return ["#0F766E", "#14B8A6", "#F59E0B", "#DC2626"];
  if (category === "psychology") return ["#6D28D9", "#22C55E", "#F97316", "#334155"];
  return ["#2563EB", "#10B981", "#F59E0B", "#334155"];
}

function svgShell(title, concept, tone, body) {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="900" viewBox="0 0 1600 900" role="img" aria-labelledby="title">
  <title id="title">${escapeXml(title)}</title>
  <rect width="1600" height="900" rx="0" fill="#F8FAFC"/>
  <rect x="54" y="44" width="1492" height="772" rx="36" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="4"/>
  ${body}
  <rect x="84" y="842" width="290" height="18" rx="9" fill="${tone[0]}" opacity=".22"/>
  <rect x="412" y="842" width="404" height="18" rx="9" fill="${tone[1]}" opacity=".20"/>
  <rect x="852" y="842" width="664" height="18" rx="9" fill="#334155" opacity=".10"/>
</svg>
`;
}

function buildDiagramBody(concept, title, category, tone) {
  if (category === "psychology") return psychologyDiagram(concept, title, tone);
  if (category === "theory") return theoryDiagram(concept, title, tone);
  return technicalDiagram(concept, title, tone);
}

function theoryDiagram(concept, title, tone) {
  switch (concept) {
    case "forex_pair_concept":
    case "base_and_quote_currency":
    case "base_quote_currency":
      return pairDiagram(tone);
    case "buy_vs_sell_direction":
      return splitDirectionDiagram(tone);
    case "cfd_trading_concept":
      return flowDiagram(["Trader", "CFD", "Price +/-"], tone, {footer: "No ownership of underlying asset"});
    case "bid_and_ask_price":
    case "bid_ask_price":
    case "spread_bid_ask":
      return spreadDiagram(tone);
    case "price_movement_example":
      return priceMovementDiagram(tone);
    case "pip_movement":
      return pipDiagram(tone);
    case "trading_cost_diagram":
    case "trading_costs":
      return formulaDiagram(["Spread", "Commission", "Swap"], "Total Cost", tone);
    case "lot_size_comparison":
      return barComparisonDiagram([
        ["Standard", "100,000"],
        ["Mini", "10,000"],
        ["Micro", "1,000"],
      ], tone);
    case "margin_and_leverage":
    case "leverage_margin":
      return leverageDiagram(tone);
    case "balance_versus_equity":
    case "balance_equity":
      return formulaDiagram(["Balance", "Floating P/L"], "Equity", tone);
    case "buy_limit_versus_buy_stop":
    case "buy_limit_buy_stop":
      return orderLadderDiagram("buy", tone);
    case "sell_limit_versus_sell_stop":
    case "sell_limit_sell_stop":
      return orderLadderDiagram("sell", tone);
    case "stop_loss_and_take_profit_placement":
    case "stop_loss_take_profit":
      return entrySlTpDiagram(tone, "long");
    case "one_to_two_risk_reward":
    case "risk_reward_1_to_2":
      return riskRewardDiagram(tone);
    case "trade_planning_checklist":
      return checklistDiagram(["Context", "Entry", "SL", "TP", "Risk"], tone);
    case "risk_per_trade_concept":
    case "risk_per_trade":
      return riskPerTradeDiagram(tone);
    case "market_session_timeline":
      return sessionTimelineDiagram(tone);
    case "liquidity_and_volatility_comparison":
    case "liquidity_volatility":
      return liquidityVolatilityDiagram(tone);
    case "session_overlap_activity":
    case "session_overlap":
      return sessionOverlapDiagram(tone);
    case "high_impact_news_volatility":
      return newsVolatilityDiagram(tone);
    case "interest_rate_impact_concept":
    case "interest_rate_impact":
      return flowDiagram(["Rate Decision", "Currency Bias", "Market Reaction"], tone, {footer: "Context, not a signal"});
    case "economic_calendar_event_impact":
    case "economic_calendar_impact":
      return calendarImpactDiagram(tone);
    case "trader_broker_liquidity_provider_flow":
    case "trader_broker_liquidity_provider":
      return flowDiagram(["Trader", "Broker", "Liquidity Provider"], tone, {footer: "Order routing flow"});
    case "demo_versus_live_account_comparison":
    case "demo_vs_live":
      return twoPanelComparison("Demo", "Live", tone);
    case "execution_quality_and_slippage":
    case "execution_slippage":
      return slippageDiagram(tone);
    default:
      return conceptCardDiagram(title, tone);
  }
}

function technicalDiagram(concept, title, tone) {
  switch (concept) {
    case "support_zone":
      return chartDiagram({
        tone,
        points: [[160, 270], [290, 370], [420, 520], [550, 640], [690, 590], [830, 450], [990, 350], [1160, 280], [1370, 230]],
        zones: [{y: 600, h: 78, color: tone[1], label: "S/R"}],
        arrows: [[620, 610, 850, 420, tone[1]]],
      });
    case "resistance_zone":
      return chartDiagram({
        tone,
        points: [[160, 650], [310, 520], [450, 390], [610, 235], [760, 285], [910, 410], [1080, 520], [1260, 600], [1390, 640]],
        zones: [{y: 185, h: 78, color: tone[2], label: "S/R"}],
        arrows: [[720, 260, 960, 455, tone[3]]],
      });
    case "support_becomes_resistance":
      return chartDiagram({
        tone,
        points: [[150, 330], [320, 250], [500, 380], [650, 430], [800, 590], [960, 455], [1120, 500], [1300, 660], [1410, 700]],
        zones: [{y: 405, h: 70, color: tone[2], label: "S -> R"}],
        arrows: [[760, 445, 880, 570, tone[3]], [980, 455, 1135, 510, tone[2]]],
      });
    case "resistance_becomes_support":
      return chartDiagram({
        tone,
        points: [[150, 600], [320, 520], [500, 470], [650, 415], [820, 255], [990, 395], [1160, 350], [1320, 240], [1420, 205]],
        zones: [{y: 385, h: 70, color: tone[1], label: "R -> S"}],
        arrows: [[760, 405, 875, 275, tone[1]], [970, 395, 1140, 350, tone[1]]],
      });
    case "body_and_wick_key_level_zone":
    case "body_wick_key_level_zone":
      return chartDiagram({
        tone,
        points: [[160, 390], [320, 315], [480, 350], [640, 300], [800, 365], [960, 330], [1120, 375], [1280, 340]],
        zones: [{y: 295, h: 105, color: tone[2], label: "BODY/WICK ZONE"}],
        arrows: [[430, 295, 500, 250, tone[2]], [910, 398, 975, 445, tone[2]]],
      });
    case "wick_rejection_at_support":
    case "wick_rejection_support":
      return wickRejectionDiagram("support", tone);
    case "wick_rejection_at_resistance":
    case "wick_rejection_resistance":
      return wickRejectionDiagram("resistance", tone);
    case "strong_snr_zone_with_multiple_reactions":
    case "strong_snr_multiple_reactions":
      return chartDiagram({
        tone,
        points: [[150, 500], [300, 405], [450, 500], [600, 405], [750, 498], [900, 405], [1050, 500], [1220, 430], [1400, 510]],
        zones: [{y: 390, h: 60, color: tone[2], label: "SNR"}],
        arrows: [[440, 450, 440, 515, tone[1]], [595, 390, 595, 330, tone[3]], [900, 390, 900, 330, tone[3]]],
      });
    case "failed_snr_rejection_example":
    case "failed_snr_rejection":
      return chartDiagram({
        tone,
        points: [[150, 510], [310, 410], [470, 500], [630, 420], [790, 500], [960, 575], [1130, 650], [1320, 700]],
        zones: [{y: 390, h: 65, color: tone[2], label: "SNR"}],
        arrows: [[830, 455, 1010, 590, tone[3]]],
        labels: [[1040, 585, "FAILED", tone[3]]],
      });
    case "uptrend_trendline":
      return trendlineDiagram("up", tone);
    case "downtrend_trendline":
      return trendlineDiagram("down", tone);
    case "channel":
    case "channel_structure":
      return channelDiagram(tone);
    case "trendline_break_and_retest":
    case "trendline_break_retest":
      return trendlineBreakDiagram(tone);
    case "hh_hl_uptrend_structure":
    case "hh_hl_uptrend":
      return structureDiagram("up", tone);
    case "lh_ll_downtrend_structure":
    case "lh_ll_downtrend":
      return structureDiagram("down", tone);
    case "swing_high_and_swing_low":
    case "swing_high_swing_low":
      return swingDiagram(tone);
    case "structure_direction_comparison":
      return structureComparisonDiagram(tone);
    case "bullish_bos":
      return bosDiagram("bullish", tone);
    case "bearish_bos":
      return bosDiagram("bearish", tone);
    case "choch_reversal":
      return chochDiagram(tone);
    case "invalid_break_example":
    case "invalid_structure_break":
      return invalidBreakDiagram(tone);
    case "h4_h1_m15_alignment":
      return multiTimeframeDiagram(["H4", "H1", "M15"], "aligned", tone);
    case "higher_timeframe_bias":
      return multiTimeframeDiagram(["HTF", "Bias", "Entry"], "bias", tone);
    case "lower_timeframe_entry_confirmation":
      return entryConfirmationDiagram(tone);
    case "timeframe_conflict_warning":
      return multiTimeframeDiagram(["H4 ↑", "H1 ↓", "M15 ?"], "conflict", tone);
    case "supply_zone":
      return supplyDemandDiagram("supply", tone);
    case "demand_zone":
      return supplyDemandDiagram("demand", tone);
    case "order_block":
      return orderBlockDiagram(tone);
    case "mitigated_versus_fresh_order_block":
    case "fresh_vs_mitigated_order_block":
      return freshMitigatedDiagram(tone);
    case "fair_value_gap":
      return fairValueGapDiagram(tone);
    case "imbalance_candle_movement":
    case "imbalance_move":
      return imbalanceDiagram(tone);
    case "equal_highs_liquidity_sweep":
      return liquiditySweepDiagram("highs", tone);
    case "equal_lows_liquidity_sweep":
      return liquiditySweepDiagram("lows", tone);
    case "price_above_ema_trend_filter":
      return emaDiagram("above", tone);
    case "price_below_ema_trend_filter":
      return emaDiagram("below", tone);
    case "ema_dynamic_support":
      return emaDynamicSupportDiagram(tone);
    case "multiple_ema_alignment":
      return multipleEmaDiagram(tone);
    case "rsi_overbought":
      return rsiDiagram("overbought", tone);
    case "rsi_oversold":
      return rsiDiagram("oversold", tone);
    case "macd_momentum":
      return macdDiagram(tone);
    case "indicator_false_signal_warning":
    case "indicator_false_signal":
      return indicatorFalseSignalDiagram(tone);
    case "pin_bar_rejection":
      return candlePatternDiagram("pin", tone);
    case "engulfing_candle":
      return candlePatternDiagram("engulfing", tone);
    case "doji_indecision":
      return candlePatternDiagram("doji", tone);
    case "strong_close_confirmation":
      return candlePatternDiagram("strong_close", tone);
    case "breakout":
      return breakoutDiagram("breakout", tone);
    case "breakout_retest":
      return breakoutDiagram("retest", tone);
    case "false_breakout":
      return breakoutDiagram("false", tone);
    case "failed_breakout_warning":
      return breakoutDiagram("failed", tone);
    case "range_high_and_range_low":
    case "range_high_low":
      return rangeDiagram("highlow", tone);
    case "consolidation_box":
      return rangeDiagram("box", tone);
    case "adx_low_strength_concept":
    case "adx_low_strength":
      return adxDiagram(tone);
    case "no_trade_range_condition":
      return rangeDiagram("notrade", tone);
    case "entry_sl_tp":
      return entrySlTpDiagram(tone, "long");
    case "invalid_setup":
      return invalidSetupDiagram(tone);
    case "confirmation_entry":
      return entryConfirmationDiagram(tone);
    case "risk_reward_execution":
      return riskRewardDiagram(tone);
    case "break_even_management":
      return breakEvenDiagram(tone);
    case "partial_close_visual":
    case "partial_close":
      return partialCloseDiagram(tone);
    case "trailing_stop_concept":
    case "trailing_stop":
      return trailingStopDiagram(tone);
    case "trade_review_workflow":
      return flowDiagram(["Plan", "Execute", "Journal", "Review"], tone, {footer: "Improve rules"});
    default:
      return chartDiagram({tone, points: defaultPoints("up"), labels: [[1060, 220, title, tone[0]]]});
  }
}

function psychologyDiagram(concept, title, tone) {
  const steps = {
    trading_discipline_checklist: ["Plan", "Rules", "Risk", "Journal"],
    wait_for_setup_flow: ["Observe", "Setup", "Confirm", "Execute"],
    rule_based_decision_process: ["Context", "Rule", "Risk", "Action"],
    fomo_chase: ["Price Spike", "Late Entry", "High Risk", "Skip"],
    revenge_trading_cycle: ["Loss", "Emotion", "Bigger Lot", "Pause"],
    loss_leading_to_over_risk_flow: ["Loss", "Frustration", "Over-risk", "Stop"],
    position_sizing_risk: ["Account", "1% Risk", "Stop Size", "Lot"],
    account_protection: ["Risk Cap", "Daily Limit", "No Overtrade", "Survive"],
    daily_loss_limit_flow: ["Trade 1", "Trade 2", "Limit Hit", "Stop"],
    emotional_cycle_after_loss: ["Loss", "Pause", "Review", "Reset"],
    winning_streak_overconfidence: ["Win Streak", "Confidence", "Larger Lot", "Reduce Risk"],
    reset_after_loss_workflow: ["Accept", "Journal", "Rule Check", "Next Plan"],
    trading_journal_template: ["Entry", "SL/TP", "Emotion", "Review"],
    review_workflow: ["Screenshot", "Reason", "Outcome", "Lesson"],
    mistake_tracking_loop: ["Mistake", "Pattern", "Rule Fix", "Retest"],
    challenge_phase_versus_funded_phase: ["Challenge", "Pass Rules", "Funded", "Protect"],
    challenge_vs_funded_phase: ["Challenge", "Pass Rules", "Funded", "Protect"],
    drawdown_discipline: ["Equity", "Drawdown", "Cut Risk", "Reset"],
    funded_account_survival_checklist: ["Rules", "Max Loss", "Consistency", "Longevity"],
    loss_to_overrisk_flow: ["Loss", "Frustration", "Over-risk", "Stop"],
    daily_loss_limit: ["Trade 1", "Trade 2", "Limit Hit", "Stop"],
  }[concept] || [title, "Rule", "Risk", "Review"];

  if (concept === "fomo_chase") {
    return `${chartDiagram({
      tone,
      points: [[160, 650], [340, 620], [510, 590], [690, 380], [850, 190], [1010, 240], [1180, 360], [1380, 430]],
      arrows: [[700, 390, 870, 205, tone[2]], [950, 230, 1090, 345, tone[3]]],
      labels: [[885, 185, "FOMO", tone[3]], [1110, 360, "WAIT", tone[0]]],
    })}`;
  }
  return flowDiagram(steps, tone, {footer: title});
}

function pairDiagram(tone) {
  return `${panelHeader("EUR/USD", "BASE / QUOTE", tone)}
  ${box(220, 265, 420, 180, tone[0], "BASE", "EUR")}
  ${box(960, 265, 420, 180, tone[1], "QUOTE", "USD")}
  ${arrow(650, 355, 940, 355, tone[2])}
  ${label(650, 535, "1 EUR = 1.1000 USD", "#334155", 36)}`;
}

function splitDirectionDiagram(tone) {
  return `${miniChart(110, 145, 650, 480, [[150, 390], [260, 340], [380, 285], [520, 220], [630, 160]], tone[1], "BUY")}
  ${miniChart(840, 145, 650, 480, [[880, 160], [1000, 230], [1130, 300], [1260, 360], [1420, 420]], tone[3], "SELL")}
  ${label(310, 700, "Expectation, not guarantee", "#64748B", 30)}
  ${label(985, 700, "Plan invalidation first", "#64748B", 30)}`;
}

function spreadDiagram(tone) {
  return `${panelHeader("BID 1.1000", "ASK 1.1002", tone)}
  ${box(185, 265, 420, 210, "#2563EB", "BID", "1.1000")}
  ${box(995, 265, 420, 210, "#10B981", "ASK", "1.1002")}
  ${arrow(620, 370, 980, 370, "#F59E0B")}
  ${label(705, 320, "Spread = 2 pips", "#B45309", 36)}
  <rect x="675" y="395" width="250" height="24" rx="12" fill="#F59E0B" opacity=".28"/>`;
}

function pipDiagram(tone) {
  return `${panelHeader("1 pip movement", "EUR/USD example", tone)}
  ${priceLadder(360, 210, ["1.1002", "1.1001", "1.1000", "1.0999"], tone)}
  ${arrow(820, 482, 820, 400, tone[1])}
  ${label(880, 450, "1.1000 -> 1.1001", "#334155", 34)}
  ${label(880, 500, "1 pip", tone[2], 42)}`;
}

function priceMovementDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 560], [310, 515], [455, 540], [620, 430], [790, 390], [940, 300], [1120, 340], [1320, 250], [1420, 210]],
    labels: [[180, 585, "1.1000", "#64748B"], [1250, 245, "1.1015", "#64748B"]],
    arrows: [[620, 430, 920, 315, tone[1]]],
  });
}

function formulaDiagram(parts, result, tone) {
  const width = 310;
  const gap = 74;
  const start = 145;
  const partMarkup = parts.map((part, index) => {
    const x = start + index * (width + gap);
    return `${box(x, 260, width, 160, tone[index % 3], part, index === 1 ? "+" : "")}${index < parts.length - 1 ? label(x + width + 24, 350, "+", "#64748B", 48) : ""}`;
  }).join("");
  return `${partMarkup}${label(1175, 350, "=", "#64748B", 48)}${box(1270, 260, 210, 160, tone[1], result, "")}`;
}

function barComparisonDiagram(items, tone) {
  const max = 1020;
  return `${items.map(([name, value], index) => {
    const y = 230 + index * 145;
    const w = index === 0 ? max : index === 1 ? 610 : 300;
    return `${label(170, y + 54, name, "#334155", 32)}<rect x="390" y="${y}" width="${w}" height="74" rx="20" fill="${tone[index]}" opacity=".82"/>${label(420 + w, y + 50, value, "#334155", 32)}`;
  }).join("")}`;
}

function leverageDiagram(tone) {
  return `${box(180, 290, 360, 180, tone[1], "Margin", "$1,000")}
  ${arrow(560, 380, 850, 380, tone[2])}
  ${box(900, 220, 520, 320, tone[0], "Exposure", "$100,000")}
  ${label(650, 322, "Leverage", "#334155", 36)}
  ${label(650, 438, "Risk scales too", "#DC2626", 30)}`;
}

function orderLadderDiagram(side, tone) {
  const buy = side === "buy";
  return `${priceLadder(650, 150, buy ? ["Buy Stop", "Current", "Buy Limit"] : ["Sell Limit", "Current", "Sell Stop"], tone)}
  ${arrow(980, buy ? 430 : 270, 1220, buy ? 280 : 570, buy ? tone[1] : tone[3])}
  ${label(1040, 625, buy ? "Limit below, Stop above" : "Limit above, Stop below", "#334155", 34)}`;
}

function entrySlTpDiagram(tone, direction) {
  const long = direction !== "short";
  return chartDiagram({
    tone,
    points: long ? [[160, 620], [330, 560], [500, 500], [690, 420], [860, 350], [1050, 290], [1280, 210]]
      : [[160, 220], [330, 280], [500, 350], [690, 430], [860, 500], [1050, 565], [1280, 650]],
    lines: [
      [140, long ? 500 : 350, 1440, long ? 500 : 350, "#334155", "Entry"],
      [140, long ? 635 : 215, 1440, long ? 635 : 215, "#DC2626", "SL"],
      [140, long ? 230 : 650, 1440, long ? 230 : 650, "#16A34A", "TP"],
    ],
  });
}

function riskRewardDiagram(tone) {
  return `${entrySlTpDiagram(tone, "long")}
  ${bracket(1240, 500, 635, "#DC2626", "Risk 1R")}
  ${bracket(1330, 230, 500, "#16A34A", "Reward 2R")}`;
}

function riskPerTradeDiagram(tone) {
  return `${box(155, 250, 350, 180, tone[0], "Account", "$10,000")}
  ${arrow(520, 340, 760, 340, tone[2])}
  ${box(800, 250, 350, 180, tone[1], "Risk 1%", "$100")}
  ${arrow(1165, 340, 1325, 340, tone[2])}
  ${box(1260, 250, 220, 180, tone[3], "SL", "Defines lot")}`;
}

function sessionTimelineDiagram(tone) {
  return `${timelineBase()}
  ${sessionBlock(160, 270, 360, "Asia", tone[0])}
  ${sessionBlock(510, 270, 410, "London", tone[1])}
  ${sessionBlock(850, 270, 440, "New York", tone[2])}
  <rect x="850" y="250" width="70" height="180" rx="18" fill="#F59E0B" opacity=".45"/>
  ${label(790, 500, "Overlap", "#B45309", 34)}`;
}

function liquidityVolatilityDiagram(tone) {
  return `${barComparisonDiagram([["Asia", "Lower"], ["London", "Higher"], ["NY", "Active"]], tone)}
  ${label(530, 685, "Liquidity and volatility change by session", "#64748B", 30)}`;
}

function sessionOverlapDiagram(tone) {
  return `${sessionTimelineDiagram(tone)}
  <path d="M840 650 C900 580 970 585 1020 520 C1080 455 1160 500 1215 430" fill="none" stroke="${tone[2]}" stroke-width="12" stroke-linecap="round"/>
  ${label(860, 690, "Higher activity", tone[2], 34)}`;
}

function newsVolatilityDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 455], [300, 445], [440, 460], [580, 450], [720, 455], [850, 280], [960, 650], [1080, 330], [1220, 610], [1400, 390]],
    lines: [[760, 150, 760, 700, "#F59E0B", "NEWS"]],
    labels: [[805, 205, "Volatility expands", "#B45309"]],
  });
}

function calendarImpactDiagram(tone) {
  return `${box(160, 250, 360, 220, tone[0], "Calendar", "CPI / NFP / FOMC")}
  ${arrow(545, 360, 785, 360, tone[2])}
  ${box(825, 250, 300, 220, tone[2], "Event", "High impact")}
  ${arrow(1148, 360, 1325, 360, tone[2])}
  ${miniChart(1180, 180, 320, 310, [[1210, 250], [1270, 245], [1320, 190], [1370, 390], [1440, 230]], tone[3], "Volatility")}`;
}

function twoPanelComparison(left, right, tone) {
  return `${box(170, 190, 560, 380, tone[0], left, "Practice conditions")}
  ${box(870, 190, 560, 380, tone[2], right, "Real fills + emotion")}
  ${label(650, 660, "Same rules, different pressure", "#334155", 34)}`;
}

function slippageDiagram(tone) {
  return `${priceLadder(390, 170, ["1.1004", "Filled 1.1003", "Requested 1.1002", "1.1001"], tone)}
  ${arrow(855, 470, 855, 390, tone[3])}
  ${label(920, 438, "Slippage", "#DC2626", 42)}
  ${label(895, 520, "Requested vs filled price", "#334155", 32)}`;
}

function conceptCardDiagram(title, tone) {
  return `${box(220, 220, 1160, 270, tone[0], title, "Educational trading diagram")}
  ${label(360, 600, "Use with risk management and review", "#64748B", 34)}`;
}

function chartDiagram({tone, points, zones = [], arrows = [], labels = [], lines = [], extra = ""}) {
  const pathPoints = points || defaultPoints("up");
  return `${chartBase()}
  ${zones.map((zone) => zoneRect(zone.y, zone.h, zone.color, zone.label)).join("")}
  ${lines.map(([x1, y1, x2, y2, color, text]) => priceLine(x1, y1, x2, y2, color, text)).join("")}
  ${candlesForPoints(pathPoints)}
  <polyline points="${pathPoints.map(([x, y]) => `${x},${y}`).join(" ")}" fill="none" stroke="${tone[0]}" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>
  ${arrows.map(([x1, y1, x2, y2, color]) => arrow(x1, y1, x2, y2, color)).join("")}
  ${labels.map(([x, y, textValue, color]) => label(x, y, textValue, color || "#334155", 32)).join("")}
  ${extra}`;
}

function chartBase() {
  const horizontals = [190, 290, 390, 490, 590, 690].map((y) => `<line x1="120" y1="${y}" x2="1480" y2="${y}" stroke="#E2E8F0" stroke-width="2"/>`).join("");
  const verticals = [300, 500, 700, 900, 1100, 1300].map((x) => `<line x1="${x}" y1="130" x2="${x}" y2="720" stroke="#E2E8F0" stroke-width="2"/>`).join("");
  return `<rect x="110" y="120" width="1380" height="610" rx="24" fill="#FDFEFF" stroke="#CBD5E1" stroke-width="3"/>${horizontals}${verticals}`;
}

function candlesForPoints(points) {
  return points.map(([x, close], index) => {
    const open = index === 0 ? close + 36 : points[index - 1][1] + (index % 2 === 0 ? 18 : -18);
    const high = Math.min(open, close) - (index % 3 === 0 ? 70 : 40);
    const low = Math.max(open, close) + (index % 4 === 0 ? 70 : 42);
    return candle(x, open, close, high, low);
  }).join("");
}

function candle(x, open, close, high, low, width = 34) {
  const up = close < open;
  const color = up ? "#16A34A" : "#DC2626";
  const top = Math.min(open, close);
  const height = Math.max(18, Math.abs(close - open));
  return `<line x1="${x}" y1="${high}" x2="${x}" y2="${low}" stroke="${color}" stroke-width="7" stroke-linecap="round"/><rect x="${x - width / 2}" y="${top}" width="${width}" height="${height}" rx="5" fill="${color}" opacity=".92"/>`;
}

function zoneRect(y, h, color, textValue) {
  return `<rect x="130" y="${y}" width="1340" height="${h}" rx="18" fill="${color}" opacity=".18" stroke="${color}" stroke-width="3" stroke-dasharray="14 12"/>${textValue ? label(1180, y + h / 2 + 11, textValue, color, 30) : ""}`;
}

function priceLine(x1, y1, x2, y2, color, textValue) {
  return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${color}" stroke-width="5" stroke-dasharray="18 12"/>${textValue ? label(x2 - 150, y2 - 12, textValue, color, 30) : ""}`;
}

function wickRejectionDiagram(type, tone) {
  const support = type === "support";
  const points = support
    ? [[160, 480], [330, 565], [500, 630], [680, 540], [850, 430], [1040, 340], [1260, 270], [1410, 230]]
    : [[160, 340], [330, 270], [500, 205], [680, 300], [850, 410], [1040, 510], [1260, 590], [1410, 645]];
  return chartDiagram({
    tone,
    points,
    zones: [{y: support ? 610 : 180, h: 74, color: support ? tone[1] : tone[2], label: "SNR"}],
    arrows: [[support ? 540 : 530, support ? 610 : 250, support ? 760 : 760, support ? 470 : 380, support ? tone[1] : tone[3]]],
    labels: [[support ? 520 : 500, support ? 700 : 160, "WICK", "#334155"]],
  });
}

function trendlineDiagram(direction, tone) {
  const up = direction === "up";
  const points = up
    ? [[160, 630], [330, 560], [500, 590], [680, 470], [850, 500], [1040, 360], [1260, 385], [1410, 260]]
    : [[160, 230], [330, 310], [500, 270], [680, 405], [850, 365], [1040, 520], [1260, 500], [1410, 650]];
  const line = up ? [150, 675, 1450, 275, tone[1], "Trendline"] : [150, 185, 1450, 630, tone[3], "Trendline"];
  return chartDiagram({tone, points, lines: [line]});
}

function channelDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 640], [310, 520], [480, 585], [650, 430], [820, 500], [990, 350], [1160, 420], [1370, 270]],
    lines: [[140, 690, 1450, 310, tone[1], "Channel"], [140, 520, 1450, 145, tone[2], "Channel"]],
  });
}

function trendlineBreakDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 620], [330, 540], [500, 570], [680, 455], [840, 490], [1000, 610], [1170, 545], [1370, 675]],
    lines: [[130, 660, 1050, 400, tone[1], "Trendline"]],
    arrows: [[930, 495, 1045, 610, tone[3]], [1110, 548, 1240, 635, tone[2]]],
    labels: [[980, 635, "Break", tone[3]], [1135, 520, "Retest", tone[2]]],
  });
}

function structureDiagram(direction, tone) {
  const up = direction === "up";
  const points = up
    ? [[160, 640], [330, 465], [500, 565], [680, 370], [850, 480], [1040, 280], [1260, 405], [1410, 215]]
    : [[160, 235], [330, 410], [500, 310], [680, 505], [850, 390], [1040, 610], [1260, 500], [1410, 690]];
  const labels = up
    ? [[330, 435, "HH", tone[1]], [500, 600, "HL", tone[1]], [680, 340, "HH", tone[1]], [850, 515, "HL", tone[1]], [1040, 250, "HH", tone[1]]]
    : [[330, 445, "LL", tone[3]], [500, 280, "LH", tone[3]], [680, 540, "LL", tone[3]], [850, 360, "LH", tone[3]], [1040, 645, "LL", tone[3]]];
  return chartDiagram({tone, points, labels});
}

function swingDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 590], [340, 300], [520, 610], [700, 290], [880, 600], [1060, 315], [1260, 575], [1420, 360]],
    labels: [[340, 270, "Swing High", tone[2]], [520, 650, "Swing Low", tone[1]], [700, 260, "Swing High", tone[2]], [880, 640, "Swing Low", tone[1]]],
  });
}

function structureComparisonDiagram(tone) {
  return `${miniChart(110, 145, 650, 480, [[150, 560], [300, 420], [455, 500], [610, 330], [735, 230]], tone[1], "HH / HL")}
  ${miniChart(840, 145, 650, 480, [[880, 230], [1030, 360], [1180, 300], [1320, 510], [1450, 640]], tone[3], "LH / LL")}`;
}

function bosDiagram(direction, tone) {
  const bullish = direction === "bullish";
  return chartDiagram({
    tone,
    points: bullish
      ? [[160, 610], [330, 470], [500, 560], [680, 420], [850, 510], [1030, 310], [1220, 230], [1410, 190]]
      : [[160, 230], [330, 380], [500, 300], [680, 460], [850, 370], [1030, 560], [1220, 650], [1410, 700]],
    lines: [[140, bullish ? 420 : 560, 1460, bullish ? 420 : 560, "#64748B", bullish ? "Prev High" : "Prev Low"]],
    arrows: [[980, bullish ? 420 : 560, 1190, bullish ? 250 : 640, bullish ? tone[1] : tone[3]]],
    labels: [[1110, bullish ? 290 : 625, "BOS", bullish ? tone[1] : tone[3]]],
  });
}

function chochDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 250], [330, 390], [500, 310], [680, 470], [850, 370], [1030, 560], [1210, 650], [1410, 690]],
    lines: [[140, 470, 1460, 470, "#64748B", "HL broken"]],
    arrows: [[900, 400, 1080, 560, tone[3]]],
    labels: [[1060, 600, "CHOCH", tone[3]], [400, 280, "LH", "#64748B"], [680, 505, "HL", "#64748B"]],
  });
}

function invalidBreakDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 520], [330, 420], [500, 455], [680, 360], [850, 415], [1030, 360], [1210, 455], [1410, 520]],
    lines: [[140, 365, 1460, 365, "#64748B", "Level"]],
    extra: `${candle(1030, 430, 390, 325, 475)}${label(960, 315, "Wick only", "#DC2626", 30)}`,
    labels: [[1120, 420, "No close", tone[3]]],
  });
}

function multiTimeframeDiagram(labels, mode, tone) {
  const colors = mode === "conflict" ? [tone[1], tone[3], tone[2]] : [tone[0], tone[1], tone[2]];
  return labels.map((item, index) => {
    const x = 110 + index * 495;
    const points = mode === "conflict" && index === 1
      ? [[x + 40, 190], [x + 160, 275], [x + 280, 250], [x + 420, 390]]
      : [[x + 40, 390], [x + 160, 320], [x + 280, 350], [x + 420, 220]];
    return miniChart(x, 170, 450, 360, points, colors[index], item);
  }).join("") + label(520, 650, mode === "conflict" ? "Conflict: stand aside" : "Top-down context", "#334155", 36);
}

function entryConfirmationDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 620], [330, 580], [500, 560], [660, 455], [830, 500], [1000, 390], [1180, 315], [1400, 260]],
    zones: [{y: 490, h: 72, color: tone[1], label: "POI"}],
    arrows: [[820, 500, 1010, 390, tone[1]]],
    labels: [[1015, 375, "Confirm", tone[1]]],
  });
}

function supplyDemandDiagram(type, tone) {
  const supply = type === "supply";
  return chartDiagram({
    tone,
    points: supply
      ? [[160, 620], [330, 520], [500, 380], [680, 215], [850, 310], [1030, 455], [1220, 570], [1410, 640]]
      : [[160, 250], [330, 355], [500, 500], [680, 645], [850, 560], [1030, 420], [1220, 305], [1410, 240]],
    zones: [{y: supply ? 180 : 610, h: 78, color: supply ? tone[2] : tone[1], label: supply ? "Supply" : "Demand"}],
    arrows: [[supply ? 780 : 780, supply ? 250 : 620, supply ? 980 : 980, supply ? 440 : 440, supply ? tone[3] : tone[1]]],
  });
}

function orderBlockDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 610], [330, 560], [500, 590], [650, 510], [820, 300], [980, 230], [1160, 260], [1400, 210]],
    zones: [{y: 500, h: 95, color: tone[1], label: "OB"}],
    arrows: [[650, 510, 830, 305, tone[1]]],
  });
}

function freshMitigatedDiagram(tone) {
  return `${miniChart(110, 145, 650, 480, [[150, 580], [310, 520], [480, 440], [650, 260], [735, 220]], tone[1], "Fresh OB")}
  ${miniChart(840, 145, 650, 480, [[880, 580], [1040, 500], [1190, 420], [1320, 500], [1450, 350]], tone[2], "Mitigated OB")}
  ${label(560, 700, "Fresh = untouched; mitigated = revisited", "#334155", 32)}`;
}

function fairValueGapDiagram(tone) {
  return `${chartBase()}
  ${candle(560, 540, 475, 445, 575, 42)}
  ${candle(720, 470, 255, 230, 500, 58)}
  ${candle(880, 235, 190, 160, 260, 42)}
  <rect x="610" y="260" width="340" height="185" rx="18" fill="${tone[2]}" opacity=".20" stroke="${tone[2]}" stroke-width="4" stroke-dasharray="16 12"/>
  ${label(700, 360, "FVG", tone[2], 48)}
  ${arrow(960, 345, 1180, 345, tone[2])}`;
}

function imbalanceDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 620], [320, 580], [500, 540], [680, 250], [860, 210], [1040, 240], [1220, 220], [1400, 200]],
    extra: `${candle(680, 580, 250, 230, 610, 72)}${label(735, 405, "Imbalance", tone[2], 34)}`,
  });
}

function liquiditySweepDiagram(side, tone) {
  const highs = side === "highs";
  return chartDiagram({
    tone,
    points: highs
      ? [[160, 460], [330, 290], [500, 455], [680, 285], [850, 460], [1030, 235], [1210, 410], [1410, 520]]
      : [[160, 410], [330, 585], [500, 420], [680, 600], [850, 430], [1030, 660], [1210, 485], [1410, 380]],
    lines: [[140, highs ? 285 : 600, 1460, highs ? 285 : 600, "#64748B", highs ? "Equal Highs" : "Equal Lows"]],
    arrows: [[930, highs ? 285 : 600, 1030, highs ? 235 : 660, tone[2]], [1040, highs ? 250 : 650, 1220, highs ? 405 : 485, tone[3]]],
    labels: [[1010, highs ? 210 : 700, "Sweep", tone[2]]],
  });
}

function emaDiagram(position, tone) {
  const above = position === "above";
  const points = above
    ? [[160, 560], [330, 500], [500, 530], [680, 430], [850, 390], [1030, 300], [1210, 320], [1410, 240]]
    : [[160, 250], [330, 310], [500, 285], [680, 390], [850, 420], [1030, 510], [1210, 500], [1410, 605]];
  return chartDiagram({
    tone,
    points,
    extra: `<path d="M150 ${above ? 620 : 200} C430 ${above ? 560 : 260} 760 ${above ? 490 : 360} 1450 ${above ? 310 : 560}" fill="none" stroke="${tone[2]}" stroke-width="10" stroke-linecap="round"/><text x="1210" y="${above ? 350 : 520}" font-family="Arial" font-size="34" font-weight="800" fill="${tone[2]}">EMA</text>`,
  });
}

function emaDynamicSupportDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 610], [330, 520], [500, 560], [680, 445], [850, 490], [1030, 360], [1210, 385], [1410, 280]],
    arrows: [[500, 560, 690, 450, tone[1]], [850, 490, 1040, 365, tone[1]]],
    extra: `<path d="M150 650 C430 590 760 515 1450 335" fill="none" stroke="${tone[2]}" stroke-width="10" stroke-linecap="round"/><text x="1220" y="380" font-family="Arial" font-size="34" font-weight="800" fill="${tone[2]}">EMA</text>`,
  });
}

function multipleEmaDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 600], [330, 540], [500, 560], [680, 460], [850, 410], [1030, 350], [1210, 300], [1410, 245]],
    extra: `<path d="M150 630 C430 575 760 500 1450 330" fill="none" stroke="#22C55E" stroke-width="8"/><path d="M150 670 C430 620 760 555 1450 410" fill="none" stroke="#F59E0B" stroke-width="8"/><path d="M150 705 C430 660 760 610 1450 505" fill="none" stroke="#64748B" stroke-width="8"/><text x="1160" y="555" font-family="Arial" font-size="32" font-weight="800" fill="#334155">EMA 24 / 52 / 200</text>`,
  });
}

function rsiDiagram(mode, tone) {
  const overbought = mode === "overbought";
  return `${chartDiagram({tone, points: overbought ? defaultPoints("up") : defaultPoints("down")})}
  <rect x="160" y="600" width="1280" height="140" rx="24" fill="#EEF2FF" stroke="#CBD5E1" stroke-width="3"/>
  <line x1="190" y1="${overbought ? 635 : 705}" x2="1410" y2="${overbought ? 635 : 705}" stroke="${overbought ? "#DC2626" : "#16A34A"}" stroke-width="5" stroke-dasharray="18 12"/>
  <path d="${overbought ? "M190 690 C360 650 480 620 620 625 C780 625 900 610 1040 625 C1210 645 1310 620 1410 630" : "M190 650 C360 690 480 710 620 705 C780 705 900 720 1040 705 C1210 685 1310 710 1410 700"}" fill="none" stroke="#6366F1" stroke-width="8"/>
  ${label(1260, overbought ? 625 : 725, overbought ? "RSI > 70" : "RSI < 30", overbought ? "#DC2626" : "#16A34A", 34)}`;
}

function macdDiagram(tone) {
  return `${chartDiagram({tone, points: defaultPoints("up")})}
  <rect x="170" y="592" width="1260" height="145" rx="24" fill="#F8FAFC" stroke="#CBD5E1" stroke-width="3"/>
  ${[260, 340, 420, 500, 580, 660, 740, 820, 900, 980, 1060, 1140, 1220, 1300].map((x, index) => {
    const h = index < 5 ? 30 + index * 8 : 82 - (index - 5) * 4;
    return `<rect x="${x}" y="${665 - h}" width="38" height="${h}" rx="8" fill="${index < 5 ? "#DC2626" : "#16A34A"}" opacity=".78"/>`;
  }).join("")}
  <path d="M220 678 C430 620 650 645 830 610 C1020 575 1180 610 1380 565" fill="none" stroke="#2563EB" stroke-width="7"/>
  ${label(1110, 610, "MACD", "#2563EB", 34)}`;
}

function indicatorFalseSignalDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 430], [330, 340], [500, 385], [680, 310], [850, 405], [1030, 460], [1210, 520], [1410, 590]],
    labels: [[760, 300, "Signal?", tone[2]], [1050, 490, "Structure disagrees", tone[3]]],
    arrows: [[780, 320, 930, 405, tone[3]]],
  });
}

function candlePatternDiagram(pattern, tone) {
  const base = chartBase();
  const candles = {
    pin: `${candle(600, 430, 360, 250, 650, 60)}${label(660, 330, "Pin Bar", tone[2], 42)}${zoneRect(585, 76, tone[1], "S/R")}`,
    engulfing: `${candle(580, 430, 500, 390, 535, 58)}${candle(700, 520, 340, 300, 550, 78)}${label(780, 380, "Engulf", tone[1], 42)}`,
    doji: `${candle(650, 430, 428, 310, 550, 78)}${label(740, 440, "Doji", tone[2], 42)}`,
    strong_close: `${candle(560, 540, 440, 410, 575, 58)}${candle(700, 455, 280, 250, 470, 74)}${candle(850, 300, 185, 160, 325, 74)}${label(930, 235, "Strong close", tone[1], 38)}`,
  }[pattern];
  return `${base}${candles}`;
}

function breakoutDiagram(mode, tone) {
  const base = {
    breakout: {points: [[160, 470], [330, 450], [500, 470], [680, 445], [850, 420], [1030, 275], [1210, 225], [1410, 190]], label: "Breakout"},
    retest: {points: [[160, 470], [330, 450], [500, 470], [680, 445], [850, 300], [1030, 420], [1210, 320], [1410, 250]], label: "Retest"},
    false: {points: [[160, 470], [330, 450], [500, 470], [680, 445], [850, 300], [1030, 485], [1210, 520], [1410, 560]], label: "False"},
    failed: {points: [[160, 470], [330, 450], [500, 470], [680, 445], [850, 330], [1030, 450], [1210, 500], [1410, 610]], label: "Failed"},
  }[mode];
  return chartDiagram({
    tone,
    points: base.points,
    zones: [{y: 430, h: 58, color: tone[2], label: "Level"}],
    labels: [[980, mode === "breakout" ? 275 : 405, base.label, mode === "breakout" || mode === "retest" ? tone[1] : tone[3]]],
    arrows: mode === "retest" ? [[1030, 420, 1210, 320, tone[1]]] : [[850, 430, 1030, mode === "breakout" ? 275 : 485, mode === "breakout" ? tone[1] : tone[3]]],
  });
}

function rangeDiagram(mode, tone) {
  return chartDiagram({
    tone,
    points: [[160, 520], [330, 330], [500, 525], [680, 340], [850, 510], [1030, 350], [1210, 520], [1410, 355]],
    lines: [[140, 325, 1460, 325, tone[2], mode === "highlow" ? "Range High" : "High"], [140, 530, 1460, 530, tone[1], mode === "highlow" ? "Range Low" : "Low"]],
    labels: mode === "notrade" ? [[670, 445, "NO TRADE", tone[3]]] : mode === "box" ? [[640, 445, "Consolidation", tone[0]]] : [],
    extra: mode === "box" || mode === "notrade" ? `<rect x="150" y="325" width="1300" height="205" rx="16" fill="${tone[0]}" opacity=".07" stroke="${tone[0]}" stroke-width="4" stroke-dasharray="16 12"/>` : "",
  });
}

function adxDiagram(tone) {
  return `${rangeDiagram("box", tone)}
  <rect x="180" y="612" width="1200" height="90" rx="22" fill="#EEF2FF" stroke="#CBD5E1" stroke-width="3"/>
  <path d="M220 660 C380 655 520 665 680 660 C860 654 1040 668 1320 660" fill="none" stroke="#6366F1" stroke-width="8"/>
  ${label(1090, 650, "ADX low", "#6366F1", 34)}`;
}

function invalidSetupDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 550], [330, 440], [500, 515], [680, 430], [850, 500], [1030, 610], [1210, 650], [1410, 690]],
    zones: [{y: 410, h: 80, color: tone[2], label: "POI"}],
    labels: [[1000, 620, "Invalid", tone[3]]],
    arrows: [[860, 500, 1030, 610, tone[3]]],
  });
}

function breakEvenDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 610], [330, 540], [500, 500], [680, 410], [850, 335], [1030, 290], [1210, 330], [1410, 260]],
    lines: [[140, 500, 1460, 500, "#334155", "Entry / BE"], [140, 635, 1460, 635, "#DC2626", "Old SL"]],
    arrows: [[900, 635, 900, 500, tone[1]]],
    labels: [[925, 565, "Move SL", tone[1]]],
  });
}

function partialCloseDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 620], [330, 560], [500, 500], [680, 420], [850, 350], [1030, 300], [1210, 260], [1410, 230]],
    lines: [[140, 350, 1460, 350, tone[2], "Partial"], [140, 240, 1460, 240, tone[1], "Runner"]],
    labels: [[875, 330, "Close 50%", tone[2]], [1220, 220, "Trail rest", tone[1]]],
  });
}

function trailingStopDiagram(tone) {
  return chartDiagram({
    tone,
    points: [[160, 650], [330, 570], [500, 600], [680, 480], [850, 510], [1030, 390], [1210, 420], [1410, 300]],
    lines: [[180, 690, 1450, 430, tone[3], "Trailing SL"]],
    arrows: [[450, 610, 450, 565, tone[3]], [880, 520, 880, 475, tone[3]], [1230, 430, 1230, 385, tone[3]]],
  });
}

function flowDiagram(steps, tone, options = {}) {
  const start = 130;
  const gap = 36;
  const width = Math.min(300, Math.floor((1340 - gap * (steps.length - 1)) / steps.length));
  const y = 285;
  return `${steps.map((step, index) => {
    const x = start + index * (width + gap);
    return `${box(x, y + (index % 2) * 35, width, 150, tone[index % tone.length], step, "")}${index < steps.length - 1 ? arrow(x + width + 4, y + 76 + (index % 2) * 35, x + width + gap - 8, y + 76 + ((index + 1) % 2) * 35, "#64748B") : ""}`;
  }).join("")}
  ${options.footer ? label(430, 640, options.footer, "#64748B", 34) : ""}`;
}

function checklistDiagram(items, tone) {
  return `<rect x="330" y="155" width="940" height="520" rx="34" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="4"/>
  ${items.map((item, index) => {
    const y = 245 + index * 78;
    return `<circle cx="430" cy="${y}" r="24" fill="${tone[1]}" opacity=".9"/><path d="M418 ${y} l12 14 l28 -32" fill="none" stroke="#FFFFFF" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>${label(500, y + 12, item, "#334155", 36)}`;
  }).join("")}`;
}

function timelineBase() {
  return `<line x1="150" y1="360" x2="1440" y2="360" stroke="#334155" stroke-width="10" stroke-linecap="round"/>
  ${[150, 365, 580, 795, 1010, 1225, 1440].map((x) => `<circle cx="${x}" cy="360" r="10" fill="#334155"/>`).join("")}`;
}

function sessionBlock(x, y, w, textValue, color) {
  return `<rect x="${x}" y="${y}" width="${w}" height="120" rx="24" fill="${color}" opacity=".22" stroke="${color}" stroke-width="4"/>${label(x + 45, y + 75, textValue, color, 34)}`;
}

function miniChart(x, y, w, h, points, color, title) {
  const scaled = points.map(([px, py]) => [px, py]);
  return `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="26" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="4"/>
  <line x1="${x + 35}" y1="${y + h - 60}" x2="${x + w - 35}" y2="${y + h - 60}" stroke="#E2E8F0" stroke-width="3"/>
  ${candlesForPoints(scaled)}
  <polyline points="${scaled.map(([px, py]) => `${px},${py}`).join(" ")}" fill="none" stroke="${color}" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
  ${label(x + 38, y + 55, title, color, 34)}`;
}

function priceLadder(x, y, values, tone) {
  return `<rect x="${x}" y="${y}" width="560" height="${values.length * 88 + 42}" rx="30" fill="#FFFFFF" stroke="#CBD5E1" stroke-width="4"/>
  ${values.map((value, index) => {
    const yy = y + 58 + index * 88;
    return `<line x1="${x + 40}" y1="${yy}" x2="${x + 520}" y2="${yy}" stroke="#E2E8F0" stroke-width="4"/>${label(x + 70, yy - 14, value, index === 1 ? tone[2] : "#334155", 34)}`;
  }).join("")}`;
}

function panelHeader(left, right, tone) {
  return `<rect x="160" y="120" width="1280" height="86" rx="28" fill="${tone[0]}" opacity=".10" stroke="${tone[0]}" stroke-width="3"/>
  ${label(230, 176, left, tone[0], 38)}
  ${label(1000, 176, right, tone[1], 38)}`;
}

function box(x, y, w, h, color, top, bottom) {
  return `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="30" fill="${color}" opacity=".15" stroke="${color}" stroke-width="5"/>
  ${label(x + 34, y + 68, top, color, 34)}
  ${bottom ? label(x + 34, y + 128, bottom, "#334155", 32) : ""}`;
}

function label(x, y, value, color = "#334155", size = 32) {
  return `<text x="${x}" y="${y}" font-family="Arial, Helvetica, sans-serif" font-size="${size}" font-weight="800" fill="${color}">${escapeXml(value)}</text>`;
}

function arrow(x1, y1, x2, y2, color) {
  const angle = Math.atan2(y2 - y1, x2 - x1);
  const head = 22;
  const left = `${x2 - head * Math.cos(angle - Math.PI / 6)},${y2 - head * Math.sin(angle - Math.PI / 6)}`;
  const right = `${x2 - head * Math.cos(angle + Math.PI / 6)},${y2 - head * Math.sin(angle + Math.PI / 6)}`;
  return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${color}" stroke-width="9" stroke-linecap="round"/><polygon points="${x2},${y2} ${left} ${right}" fill="${color}"/>`;
}

function bracket(x, y1, y2, color, textValue) {
  return `<path d="M${x} ${y1} h36 M${x} ${y1} v${y2 - y1} M${x} ${y2} h36" fill="none" stroke="${color}" stroke-width="7" stroke-linecap="round"/>${label(x + 48, (y1 + y2) / 2 + 12, textValue, color, 30)}`;
}

function defaultPoints(direction) {
  return direction === "down"
    ? [[160, 225], [330, 325], [500, 280], [680, 410], [850, 380], [1030, 510], [1210, 470], [1410, 620]]
    : [[160, 610], [330, 520], [500, 560], [680, 430], [850, 465], [1030, 345], [1210, 365], [1410, 240]];
}

function candleSetUnused() {}

function slug(value) {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, "_").replace(/^_|_$/g, "");
}

function hash(value) {
  let result = 0;
  for (let index = 0; index < value.length; index++) {
    result = ((result << 5) - result + value.charCodeAt(index)) | 0;
  }
  return Math.abs(result);
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function escapeXml(value) {
  return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
}

main();
