#!/usr/bin/env node

const admin = require('firebase-admin');

const args = new Set(process.argv.slice(2));
const apply = args.has('--apply');
const projectArg = process.argv.find((arg) => arg.startsWith('--project='));
const projectId =
  projectArg?.slice('--project='.length) ||
  process.env.GCLOUD_PROJECT ||
  process.env.GOOGLE_CLOUD_PROJECT ||
  undefined;

admin.initializeApp({
  credential: admin.credential.applicationDefault(),
  projectId,
});

const db = admin.firestore();
const now = admin.firestore.FieldValue.serverTimestamp();

const dailyQuestions = [
  {
    id: 'bilingual_daily_risk_001',
    category: { en: 'Risk Management', zh: '风险管理' },
    difficulty: 'risk',
    question_text: {
      en: 'A trader has reached the daily loss limit but sees a clean setup. What is the best action?',
      zh: '交易员已经触及每日亏损限制，但又看到一个很清晰的形态。最佳行动是什么？',
    },
    options: [
      {
        id: 'A',
        option_text: {
          en: 'Stop trading and record the setup for review.',
          zh: '停止交易，并把该形态记录下来用于复盘。',
        },
        is_correct: true,
        position: 1,
      },
      {
        id: 'B',
        option_text: {
          en: 'Increase position size to recover faster.',
          zh: '加大仓位以更快追回亏损。',
        },
        is_correct: false,
        position: 2,
      },
      {
        id: 'C',
        option_text: {
          en: 'Enter only if the market moves quickly.',
          zh: '只要市场快速波动就进场。',
        },
        is_correct: false,
        position: 3,
      },
      {
        id: 'D',
        option_text: {
          en: 'Ignore the rule because the setup looks strong.',
          zh: '因为形态看起来强，所以忽略规则。',
        },
        is_correct: false,
        position: 4,
      },
    ],
    explanation: {
      en: 'A daily loss rule exists to prevent one session from becoming a major drawdown. The correct response is to stop execution and review later.',
      zh: '每日亏损规则的目的，是防止一个交易时段演变成严重回撤。正确做法是停止执行，并在之后进行复盘。',
    },
  },
  {
    id: 'bilingual_daily_plan_002',
    category: { en: 'Trading Plan', zh: '交易计划' },
    difficulty: 'foundation',
    question_text: {
      en: 'Why should entry criteria be written before the trading session starts?',
      zh: '为什么入场条件应该在交易时段开始前写清楚？',
    },
    options: [
      {
        id: 'A',
        option_text: {
          en: 'It reduces impulsive decisions during fast price movement.',
          zh: '它能减少快速行情中的冲动决策。',
        },
        is_correct: true,
        position: 1,
      },
      {
        id: 'B',
        option_text: {
          en: 'It guarantees the setup will be profitable.',
          zh: '它能保证形态一定盈利。',
        },
        is_correct: false,
        position: 2,
      },
      {
        id: 'C',
        option_text: {
          en: 'It removes the need for a stop loss.',
          zh: '它可以不再需要止损。',
        },
        is_correct: false,
        position: 3,
      },
      {
        id: 'D',
        option_text: {
          en: 'It allows every signal to be traded.',
          zh: '它允许交易所有信号。',
        },
        is_correct: false,
        position: 4,
      },
    ],
    explanation: {
      en: 'Written criteria create a decision filter. They do not guarantee profit, but they reduce emotional entries.',
      zh: '书面条件是一套决策过滤器。它不能保证盈利，但能减少情绪化入场。',
    },
  },
  {
    id: 'bilingual_daily_structure_003',
    category: { en: 'Market Structure', zh: '市场结构' },
    difficulty: 'technical',
    question_text: {
      en: 'What does a higher high followed by a higher low usually suggest?',
      zh: '一个更高高点之后出现更高低点，通常说明什么？',
    },
    options: [
      {
        id: 'A',
        option_text: {
          en: 'Buyers may still be defending the trend structure.',
          zh: '买方可能仍在守住趋势结构。',
        },
        is_correct: true,
        position: 1,
      },
      {
        id: 'B',
        option_text: {
          en: 'The market must reverse immediately.',
          zh: '市场一定会立刻反转。',
        },
        is_correct: false,
        position: 2,
      },
      {
        id: 'C',
        option_text: {
          en: 'Risk management is no longer needed.',
          zh: '已经不需要风险管理。',
        },
        is_correct: false,
        position: 3,
      },
      {
        id: 'D',
        option_text: {
          en: 'Every pullback should be entered blindly.',
          zh: '每一次回调都应盲目进场。',
        },
        is_correct: false,
        position: 4,
      },
    ],
    explanation: {
      en: 'A higher-high/higher-low sequence can indicate bullish structure, but entries still need confirmation and defined risk.',
      zh: '更高高点与更高低点的序列可能代表偏多结构，但入场仍需要确认与明确风险。',
    },
  },
  {
    id: 'bilingual_daily_journal_004',
    category: { en: 'Journaling', zh: '交易日志' },
    difficulty: 'foundation',
    question_text: {
      en: 'Which journal note is most useful for future review?',
      zh: '哪一种交易日志笔记最有利于未来复盘？',
    },
    options: [
      {
        id: 'A',
        option_text: {
          en: 'The setup, emotion, rule followed or broken, and screenshot context.',
          zh: '形态、情绪、遵守或违反的规则，以及截图背景。',
        },
        is_correct: true,
        position: 1,
      },
      {
        id: 'B',
        option_text: {
          en: 'Only whether the trade won or lost.',
          zh: '只记录这笔交易盈利还是亏损。',
        },
        is_correct: false,
        position: 2,
      },
      {
        id: 'C',
        option_text: {
          en: 'A random comment after the week ends.',
          zh: '一周结束后随便写一句评论。',
        },
        is_correct: false,
        position: 3,
      },
      {
        id: 'D',
        option_text: {
          en: 'Nothing, because results explain everything.',
          zh: '什么都不用写，因为结果说明一切。',
        },
        is_correct: false,
        position: 4,
      },
    ],
    explanation: {
      en: 'A useful journal records decision quality, emotional state, rule discipline, and chart context, not only outcome.',
      zh: '有价值的日志记录决策质量、情绪状态、规则纪律与图表背景，而不只是结果。',
    },
  },
  {
    id: 'bilingual_daily_psychology_005',
    category: { en: 'Trading Psychology', zh: '交易心理' },
    difficulty: 'psychology',
    question_text: {
      en: 'A trader feels rushed after missing a move. What should they do first?',
      zh: '交易员错过一段行情后感到急躁。第一步应该做什么？',
    },
    options: [
      {
        id: 'A',
        option_text: {
          en: 'Pause and check whether the next trade still matches the written plan.',
          zh: '先暂停，并检查下一笔交易是否仍符合书面计划。',
        },
        is_correct: true,
        position: 1,
      },
      {
        id: 'B',
        option_text: {
          en: 'Enter immediately to avoid missing more movement.',
          zh: '马上进场，避免错过更多行情。',
        },
        is_correct: false,
        position: 2,
      },
      {
        id: 'C',
        option_text: {
          en: 'Double risk because confidence is high.',
          zh: '因为很有信心，所以把风险加倍。',
        },
        is_correct: false,
        position: 3,
      },
      {
        id: 'D',
        option_text: {
          en: 'Ignore emotions because they do not affect trading.',
          zh: '忽略情绪，因为情绪不会影响交易。',
        },
        is_correct: false,
        position: 4,
      },
    ],
    explanation: {
      en: 'Fear of missing out often pushes traders into late entries. A pause helps restore plan-based decision-making.',
      zh: '错失恐惧常让交易员追在后面入场。暂停有助于回到以计划为基础的决策。',
    },
  },
];

const finalQuestions = [
  {
    id: 'bilingual_final_foundation_001',
    category: { en: 'Foundation', zh: '基础' },
    subcategory: { en: 'Risk First', zh: '风险优先' },
    difficulty: 'foundation',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'Before entering a trade, what must be defined first?',
      zh: '在进场前，首先必须定义什么？',
    },
    scenario_text: {
      en: 'A trader has identified a possible setup but has not calculated risk.',
      zh: '交易员发现了一个可能的形态，但还没有计算风险。',
    },
    options: {
      A: {
        text: { en: 'Invalidation point and risk amount.', zh: '失效位置与风险金额。' },
        is_correct: true,
      },
      B: {
        text: { en: 'How much profit is desired.', zh: '想赚多少钱。' },
        is_correct: false,
      },
      C: {
        text: { en: 'The next news headline.', zh: '下一条新闻标题。' },
        is_correct: false,
      },
      D: {
        text: { en: 'The number of trades taken yesterday.', zh: '昨天交易了多少笔。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'Professional execution starts with invalidation and position risk. Profit targets come after risk is controlled.',
      zh: '专业执行从失效位置和仓位风险开始。只有风险受控后，才考虑盈利目标。',
    },
  },
  {
    id: 'bilingual_final_technical_002',
    category: { en: 'Technique and Analysis', zh: '技术与分析' },
    subcategory: { en: 'Confirmation', zh: '确认' },
    difficulty: 'technical',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'Why should key levels be treated as zones instead of exact points?',
      zh: '为什么关键价位应被视为区域，而不是精确单点？',
    },
    options: {
      A: {
        text: {
          en: 'Liquidity, spread, and execution can create slight variation around a level.',
          zh: '流动性、点差与执行可能让价格在关键位附近出现细微偏差。',
        },
        is_correct: true,
      },
      B: {
        text: { en: 'Zones remove the need for stop losses.', zh: '区域可以取消止损的必要性。' },
        is_correct: false,
      },
      C: {
        text: { en: 'Every zone must be traded.', zh: '每一个区域都必须交易。' },
        is_correct: false,
      },
      D: {
        text: { en: 'Exact prices are never useful.', zh: '精确价格完全没有用。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'Market reaction areas are rarely perfect single ticks. Treating them as zones improves planning while still requiring invalidation.',
      zh: '市场反应区域很少是完美单点。把关键位当作区域有助于规划，但仍必须定义失效条件。',
    },
  },
  {
    id: 'bilingual_final_psychology_003',
    category: { en: 'Psychology', zh: '心理' },
    subcategory: { en: 'Discipline', zh: '纪律' },
    difficulty: 'psychology',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'What is the clearest sign of revenge trading?',
      zh: '最明显的报复性交易迹象是什么？',
    },
    options: {
      A: {
        text: {
          en: 'Increasing size or frequency mainly to recover a previous loss.',
          zh: '主要为了追回上一笔亏损而提高仓位或交易频率。',
        },
        is_correct: true,
      },
      B: {
        text: { en: 'Reducing risk after a loss.', zh: '亏损后降低风险。' },
        is_correct: false,
      },
      C: {
        text: { en: 'Stopping after daily limit is reached.', zh: '达到每日限制后停止交易。' },
        is_correct: false,
      },
      D: {
        text: { en: 'Reviewing screenshots calmly.', zh: '冷静复盘截图。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'Revenge trading is driven by emotional recovery, not by plan quality. Size and frequency changes are important warning signs.',
      zh: '报复性交易的驱动力是情绪化回血，而不是计划质量。仓位和频率变化是重要警讯。',
    },
  },
  {
    id: 'bilingual_final_journal_004',
    category: { en: 'Foundation', zh: '基础' },
    subcategory: { en: 'Journaling', zh: '日志复盘' },
    difficulty: 'foundation',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'What should be reviewed after a profitable trade?',
      zh: '盈利交易之后应该复盘什么？',
    },
    options: {
      A: {
        text: {
          en: 'Whether the plan was followed and risk was appropriate.',
          zh: '是否遵守计划，以及风险是否合适。',
        },
        is_correct: true,
      },
      B: {
        text: { en: 'Nothing, because profit means the process was perfect.', zh: '不需要复盘，因为盈利代表过程完美。' },
        is_correct: false,
      },
      C: {
        text: { en: 'Only how to increase size next time.', zh: '只研究下次如何加仓。' },
        is_correct: false,
      },
      D: {
        text: { en: 'Only the broker spread.', zh: '只看经纪商点差。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'Profitable trades can still contain poor process. Journaling should evaluate decision quality, not only outcome.',
      zh: '盈利交易也可能包含糟糕过程。日志应评估决策质量，而不只是结果。',
    },
  },
  {
    id: 'bilingual_final_structure_005',
    category: { en: 'Technique and Analysis', zh: '技术与分析' },
    subcategory: { en: 'Market Structure', zh: '市场结构' },
    difficulty: 'technical',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'What invalidates a bullish continuation idea most clearly?',
      zh: '什么最明确地使偏多延续思路失效？',
    },
    options: {
      A: {
        text: { en: 'A break and acceptance below the planned higher-low zone.', zh: '跌破并接受在计划中的更高低点区域下方。' },
        is_correct: true,
      },
      B: {
        text: { en: 'A small pullback after a rally.', zh: '上涨后的轻微回调。' },
        is_correct: false,
      },
      C: {
        text: { en: 'Lower volume during lunchtime.', zh: '午间成交量降低。' },
        is_correct: false,
      },
      D: {
        text: { en: 'A candle with a long wick.', zh: '一根长影线K线。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'A continuation plan must define where the structure is wrong. Acceptance below the defended higher-low zone is a clearer invalidation than normal noise.',
      zh: '延续计划必须定义结构在哪里出错。价格接受在被防守的更高低点区域下方，比普通噪音更能说明失效。',
    },
  },
  {
    id: 'bilingual_final_risk_006',
    category: { en: 'Foundation', zh: '基础' },
    subcategory: { en: 'Position Sizing', zh: '仓位管理' },
    difficulty: 'risk',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'If stop distance increases, what usually happens to position size when risk amount stays fixed?',
      zh: '如果止损距离变大，而风险金额固定，仓位通常会怎样？',
    },
    options: {
      A: {
        text: { en: 'Position size should decrease.', zh: '仓位应减少。' },
        is_correct: true,
      },
      B: {
        text: { en: 'Position size should increase.', zh: '仓位应增加。' },
        is_correct: false,
      },
      C: {
        text: { en: 'Position size is unrelated to stop distance.', zh: '仓位与止损距离无关。' },
        is_correct: false,
      },
      D: {
        text: { en: 'Risk becomes zero.', zh: '风险变为零。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'With a fixed money risk, a wider stop requires a smaller position to keep the same loss if invalidated.',
      zh: '在固定金额风险下，止损越宽，仓位就应越小，才能在失效时保持相同亏损。',
    },
  },
  {
    id: 'bilingual_final_news_007',
    category: { en: 'Technique and Analysis', zh: '技术与分析' },
    subcategory: { en: 'News Risk', zh: '新闻风险' },
    difficulty: 'risk',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'Why should traders check high-impact news before executing?',
      zh: '为什么交易员执行前应检查高影响新闻？',
    },
    options: {
      A: {
        text: { en: 'Volatility and spread can change execution risk.', zh: '波动与点差可能改变执行风险。' },
        is_correct: true,
      },
      B: {
        text: { en: 'News always creates a safe entry.', zh: '新闻总会创造安全入场点。' },
        is_correct: false,
      },
      C: {
        text: { en: 'It guarantees the direction.', zh: '它能保证方向。' },
        is_correct: false,
      },
      D: {
        text: { en: 'It replaces market structure analysis.', zh: '它可以替代市场结构分析。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'High-impact events can widen spreads and trigger slippage. Planning around news is risk control, not prediction.',
      zh: '高影响事件可能扩大点差并引发滑点。围绕新闻做计划是风险控制，而不是预测方向。',
    },
  },
  {
    id: 'bilingual_final_execution_008',
    category: { en: 'Technique and Analysis', zh: '技术与分析' },
    subcategory: { en: 'Execution', zh: '执行' },
    difficulty: 'technical',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'What makes a trade setup actionable rather than just interesting?',
      zh: '什么让一个交易形态变成可执行，而不只是“看起来有趣”？',
    },
    options: {
      A: {
        text: { en: 'Clear trigger, invalidation, target logic, and risk size.', zh: '清晰触发、失效、目标逻辑与风险仓位。' },
        is_correct: true,
      },
      B: {
        text: { en: 'A strong opinion only.', zh: '只有强烈观点。' },
        is_correct: false,
      },
      C: {
        text: { en: 'A screenshot from social media.', zh: '一张社交媒体截图。' },
        is_correct: false,
      },
      D: {
        text: { en: 'A desire to trade today.', zh: '今天想交易的欲望。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'Actionability requires objective execution conditions and risk definition. Interest alone is not a trading plan.',
      zh: '可执行性需要客观执行条件与风险定义。单纯觉得有趣并不是交易计划。',
    },
  },
  {
    id: 'bilingual_final_prop_009',
    category: { en: 'Foundation', zh: '基础' },
    subcategory: { en: 'Prop Firm Rules', zh: 'Prop Firm 规则' },
    difficulty: 'risk',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'In a prop-firm challenge, why can one oversized loss be more damaging than several small planned losses?',
      zh: '在 Prop Firm 挑战中，为什么一笔超大亏损可能比几笔小的计划内亏损更严重？',
    },
    options: {
      A: {
        text: { en: 'It can breach drawdown rules and show poor risk discipline.', zh: '它可能违反回撤规则，并显示风险纪律不足。' },
        is_correct: true,
      },
      B: {
        text: { en: 'Small losses are always unacceptable.', zh: '小亏损永远不可接受。' },
        is_correct: false,
      },
      C: {
        text: { en: 'Oversized losses improve consistency.', zh: '超大亏损能提升一致性。' },
        is_correct: false,
      },
      D: {
        text: { en: 'Drawdown rules do not matter.', zh: '回撤规则并不重要。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'Evaluation accounts measure both profit and risk discipline. One oversized loss can damage rule compliance and consistency.',
      zh: '评估账户同时衡量盈利与风险纪律。一笔超大亏损可能破坏规则合规与一致性。',
    },
  },
  {
    id: 'bilingual_final_process_010',
    category: { en: 'Psychology', zh: '心理' },
    subcategory: { en: 'Process Review', zh: '过程复盘' },
    difficulty: 'psychology',
    status: 'approved',
    question_type: 'multiple_choice',
    question: {
      en: 'What is the healthiest response after three consecutive losses?',
      zh: '连续三笔亏损后，最健康的反应是什么？',
    },
    options: {
      A: {
        text: { en: 'Pause, review whether losses followed the plan, and reduce pressure.', zh: '暂停，复盘亏损是否遵守计划，并降低心理压力。' },
        is_correct: true,
      },
      B: {
        text: { en: 'Trade faster to recover before the day ends.', zh: '加快交易，在当天结束前追回来。' },
        is_correct: false,
      },
      C: {
        text: { en: 'Increase lot size on the next valid setup.', zh: '下一笔有效形态提高仓位。' },
        is_correct: false,
      },
      D: {
        text: { en: 'Delete the journal records.', zh: '删除交易日志记录。' },
        is_correct: false,
      },
    },
    explanation: {
      en: 'A loss streak can create emotional pressure. A pause and process review helps separate normal variance from execution problems.',
      zh: '连亏会带来情绪压力。暂停与过程复盘有助于区分正常波动和执行问题。',
    },
  },
];

const marketOutlooks = [
  {
    id: 'bilingual_outlook_xauusd_weekly_plan',
    Outlook_Code: 'BIL-XAUUSD-EDU-001',
    status: 'published',
    is_published: true,
    market: 'XAUUSD',
    trend_strength: 'Moderate',
    title: {
      en: 'XAUUSD Scenario-Based Weekly Market Outlook',
      zh: 'XAUUSD 场景化每周市场展望',
    },
    market_overview: {
      en: 'Gold remains sensitive to dollar direction, yields, and risk sentiment. The current structure should be reviewed as scenarios rather than a prediction.',
      zh: '黄金仍对美元方向、收益率与风险情绪敏感。当前结构应以场景规划方式复盘，而不是单一预测。',
    },
    trend_structure: {
      en: 'Price is rotating near a prior reaction zone. A sustained hold above the zone keeps the short-term structure constructive, while acceptance below it signals caution.',
      zh: '价格正在前期反应区域附近轮动。若持续守在该区域上方，短线结构仍较积极；若接受在下方，则需要谨慎。',
    },
    key_zones: {
      en: 'Resistance: prior swing high and liquidity above it. Support: recent higher-low area and the lower edge of the rotation range.',
      zh: '阻力：前高及其上方流动性。支撑：近期更高低点区域与震荡区间下沿。',
    },
    analyst_view: {
      en: 'The preferred process is to wait for confirmation at planned zones, define invalidation first, and avoid chasing after extended candles.',
      zh: '更稳健的流程是在计划区域等待确认，先定义失效位置，并避免在延伸K线后追单。',
    },
    strategy: {
      en: 'Scenario A: structure holds and price forms confirmation near support. Scenario B: structure fails below support and traders switch to defensive review mode.',
      zh: '场景A：结构守住，价格在支撑附近形成确认。场景B：结构跌破支撑，交易员转入防守复盘模式。',
    },
    trading_plan: {
      en: 'No directional recommendation is implied. Use a written trigger, fixed risk, and journal screenshots before and after execution.',
      zh: '此内容不构成方向建议。执行前请使用书面触发条件、固定风险，并在交易前后记录截图。',
    },
    chart_signals: {
      en: 'Watch reaction quality, candle close location, spread conditions, and whether momentum confirms or rejects the planned zone.',
      zh: '观察反应质量、K线收盘位置、点差情况，以及动能是否确认或否定计划区域。',
    },
    order_block: {
      en: 'Treat order-block/FVG references as context only. They require confirmation and invalidation before any execution decision.',
      zh: '订单块/FVG 仅作为背景参考。任何执行决定前都需要确认与失效条件。',
    },
    rsi_level: 'Neutral / context only',
    analysis_date: admin.firestore.Timestamp.fromDate(new Date('2026-06-26T00:00:00Z')),
  },
];

function withAudit(doc) {
  return {
    ...doc,
    bilingual_seed: true,
    updated_at: now,
    created_at: doc.created_at || now,
  };
}

async function upsert(path, id, data) {
  if (!apply) {
    console.log(`[dry-run] would merge ${path}/${id}`);
    return;
  }
  await db.collection(path).doc(id).set(withAudit(data), { merge: true });
  console.log(`[applied] merged ${path}/${id}`);
}

async function main() {
  console.log(
    apply
      ? 'Applying bilingual Firestore seed data...'
      : 'Dry run only. Re-run with --apply to write to Firestore.',
  );

  for (const question of dailyQuestions) {
    const { id, ...data } = question;
    await upsert('trading_exam_questions', id, {
      ...data,
      status: 'approved',
    });
  }

  for (const question of finalQuestions) {
    const { id, ...data } = question;
    await upsert('final_exam_questions', id, data);
  }

  for (const outlook of marketOutlooks) {
    const { id, ...data } = outlook;
    await upsert('market_analyses', id, data);
  }

  console.log('Done.');
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
