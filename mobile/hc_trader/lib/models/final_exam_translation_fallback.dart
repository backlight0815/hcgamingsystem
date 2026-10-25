String finalExamFallbackZh(String english) {
  final normalized = english.trim().replaceAll(RegExp(r'\s+'), ' ');
  if (normalized.isEmpty) return '';

  final exact = _exactZh[normalized];
  if (exact != null) return exact;
  final exactByLower = _exactZhByLower[normalized.toLowerCase()];
  if (exactByLower != null) return exactByLower;

  final bestPractice = RegExp(
    r'^What is best practice for (.+)\?$',
    caseSensitive: false,
  ).firstMatch(normalized);
  if (bestPractice != null) {
    return '关于${_termZh(bestPractice.group(1)!)}，最佳做法是什么？';
  }

  final bestDescribe = RegExp(
    r'^What best describes (.+)\?$',
    caseSensitive: false,
  ).firstMatch(normalized);
  if (bestDescribe != null) {
    return '以下哪项最能描述${_termZh(bestDescribe.group(1)!)}？';
  }

  final inTrading = RegExp(
    r'^In trading, what does "(.+)" mean\?$',
    caseSensitive: false,
  ).firstMatch(normalized);
  if (inTrading != null) {
    return '在交易中，“${_termZh(inTrading.group(1)!)}”是什么意思？';
  }

  final foundationDefinition = RegExp(
    r'^Foundation definition: what does (.+) mean in trading\?$',
  ).firstMatch(normalized);
  if (foundationDefinition != null) {
    return '基础定义：${_termZh(foundationDefinition.group(1)!)}在交易中是什么意思？';
  }

  final foundationApplication = RegExp(
    r'^Foundation application: how should a trader use (.+)\?$',
  ).firstMatch(normalized);
  if (foundationApplication != null) {
    return '基础应用：交易者应该如何使用${_termZh(foundationApplication.group(1)!)}？';
  }

  final foundationRisk = RegExp(
    r'^Foundation risk check: what is the common mistake when handling (.+)\?$',
  ).firstMatch(normalized);
  if (foundationRisk != null) {
    return '基础风险检查：处理${_termZh(foundationRisk.group(1)!)}时常见错误是什么？';
  }

  final techniqueDefinition = RegExp(
    r'^Technique definition: what does (.+) mean\?$',
  ).firstMatch(normalized);
  if (techniqueDefinition != null) {
    return '技术定义：${_termZh(techniqueDefinition.group(1)!)}是什么意思？';
  }

  final techniqueApplication = RegExp(
    r'^Technique application: how should a trader apply (.+)\?$',
  ).firstMatch(normalized);
  if (techniqueApplication != null) {
    return '技术应用：交易者应该如何运用${_termZh(techniqueApplication.group(1)!)}？';
  }

  final techniqueInvalidation = RegExp(
    r'^Technique invalidation: what should confirm that a (.+) idea is wrong\?$',
  ).firstMatch(normalized);
  if (techniqueInvalidation != null) {
    return '技术失效：什么情况可以确认${_termZh(techniqueInvalidation.group(1)!)}的思路是错误的？';
  }

  final techniqueChart = RegExp(
    r'^Technique chart case (\d+): based on the chart, what is the most accurate technical read for (.+)\?$',
  ).firstMatch(normalized);
  if (techniqueChart != null) {
    return '技术图表案例 ${techniqueChart.group(1)}：根据图表，关于${_termZh(techniqueChart.group(2)!)}最准确的技术解读是什么？';
  }

  final psychologyChart = RegExp(
    r'^Psychology chart case (\d+): after seeing this chart, what is the best trader behavior\?$',
  ).firstMatch(normalized);
  if (psychologyChart != null) {
    return '交易心理图表案例 ${psychologyChart.group(1)}：看到这张图后，最佳交易行为是什么？';
  }

  final they = RegExp(r'^They (.+)\.$').firstMatch(normalized);
  if (they != null) {
    final application = _applicationZh[they.group(1)!];
    if (application != null) return '他们$application。';
  }

  final readWithContext = RegExp(
    r'^(.+) must be read with context, not as an isolated signal\.$',
  ).firstMatch(normalized);
  if (readWithContext != null) {
    return '${_termZh(readWithContext.group(1)!)}必须结合上下文解读，不能当作孤立信号。';
  }

  final basicConcept = RegExp(
    r'^(.+) is a basic trading concept that affects planning, execution, or review\.$',
  ).firstMatch(normalized);
  if (basicConcept != null) {
    return '${_termZh(basicConcept.group(1)!)}是影响规划、执行或复盘的基础交易概念。';
  }

  final practicalUse = RegExp(
    r'^The practical use of (.+) should improve decision quality before risk is committed\.$',
  ).firstMatch(normalized);
  if (practicalUse != null) {
    return '${_termZh(practicalUse.group(1)!)}的实际用途，应是在承担风险前提高决策质量。';
  }

  return '';
}

String _termZh(String english) {
  final normalized = english.trim().replaceAll(RegExp(r'\s+'), ' ');
  return _termZhByLower[normalized.toLowerCase()] ?? normalized;
}

final Map<String, String> _termZhByLower = {
  for (final entry in _termZhExact.entries)
    entry.key.toLowerCase(): entry.value,
};

const _termZhExact = <String, String>{
  'Foundation': '基础知识',
  'Foundation and Knowledge': '基础知识',
  'Technique and Analysis': '技术与分析',
  'Technique and Analysis Skills': '技术与分析技能',
  'Psychology': '交易心理',
  'Trading Psychology': '交易心理',
  'Market Basics': '市场基础',
  'Risk First': '风险优先',
  'Position Sizing': '仓位管理',
  'Position size': '仓位大小',
  'Margin': '保证金',
  'Account Metrics': '账户指标',
  'Execution': '执行',
  'Analysis Types': '分析类型',
  'Risk Management': '风险管理',
  'Capital Management': '资金管理',
  'Performance Metrics': '表现指标',
  'Gold Trading': '黄金交易',
  'Orders': '订单',
  'Trade Management': '交易管理',
  'Trade Discipline': '交易纪律',
  'Review': '复盘',
  'Risk Filters': '风险过滤',
  'Prop Firm Rules': 'Prop Firm 规则',
  'Market Structure': '市场结构',
  'SNR / Key Level': '支撑阻力/关键位',
  'Support / Resistance': '支撑/阻力',
  'Entry Model': '入场模型',
  'Indicators and Tools': '指标与工具',
  'Gold / XAUUSD': '黄金/XAUUSD',
  'Multi-Timeframe': '多周期分析',
  'Session Analysis': '交易时段分析',
  'Confirmation': '确认',
  'Discipline': '纪律',
  'Journaling': '交易日志',
  'Process Review': '过程复盘',
  'Revenge Trading': '报复性交易',
  'FOMO': '错失恐惧',
  'Cutting Profit Early': '过早止盈',
  'Fear After Loss': '亏损后的恐惧',
  'Accepting SL': '接受止损',
  'Greed': '贪婪',
  'Daily Loss Limit Discipline': '每日亏损限制纪律',
  'Daily loss limit': '每日亏损限制',
  'Overconfidence': '过度自信',
  'Consistency': '一致性',
  'All-In Behavior': '梭哈行为',
  'Greed After Profit': '盈利后的贪婪',
  'Impulse Entry': '冲动入场',
  'Not Following Plan': '未遵守计划',
  'Moving SL Emotionally': '情绪化移动止损',
  'Boredom Trading': '无聊交易',
  'News Discipline': '新闻纪律',
  'Knowing When To Stop': '知道何时停止',
  'Emotional Control': '情绪控制',
  'Patience': '耐心',
  'Following Others': '跟随他人',
  'Impulse Control': '冲动控制',
  'Trade Journaling': '交易日志',
  'Risk Discipline': '风险纪律',
  'Prop Firm Rule Discipline': 'Prop Firm 规则纪律',
  'Forex Trading': '外汇交易',
  'Lot Size': '手数',
  'Lot size': '手数',
  'Leverage': '杠杆',
  'Equity': '净值',
  'Balance': '余额',
  'Floating Profit/Loss': '浮动盈亏',
  'Spread': '点差',
  'Swap': '隔夜利息',
  'Slippage': '滑点',
  'Fundamental Analysis': '基本面分析',
  'Technical Analysis': '技术分析',
  'Market Session': '交易时段',
  'Liquidity': '流动性',
  'Volatility': '波动性',
  'Drawdown': '回撤',
  'Win Rate': '胜率',
  'Risk-to-Reward Ratio': '风险回报比',
  'Reward-to-risk ratio': '回报风险比',
  'Risk-to-Reward': '风险回报比',
  'Risk-to-reward': '风险回报比',
  'Risk Per Trade': '单笔风险',
  'Risk per trade': '单笔风险',
  'Gold Pips': '黄金点数',
  'Gold Profit/Loss': '黄金盈亏',
  'Take Profit': '止盈',
  'Take-profit order': '止盈单',
  'Stop Loss': '止损',
  'Stop-loss order': '止损单',
  'Breakeven': '保本',
  'Market Order': '市价单',
  'Market order': '市价单',
  'Limit Order': '限价单',
  'Limit order': '限价单',
  'Stop Order': '止损/突破挂单',
  'Stop order': '止损/突破挂单',
  'Buy Limit': '买入限价单',
  'Sell Limit': '卖出限价单',
  'Buy Stop': '买入止损单',
  'Sell Stop': '卖出止损单',
  'Pending Order': '挂单',
  'Partial Close': '部分平仓',
  'Manual Close': '手动平仓',
  'Preset TP/SL': '预设止盈/止损',
  'Free Margin': '可用保证金',
  'Margin Call': '追加保证金通知',
  'Commission': '手续费',
  'Account Currency': '账户币种',
  'Liquidity Sweep': '流动性扫荡',
  'Liquidity sweep': '流动性扫荡',
  'Break Even Trade': '保本交易',
  'Trading Journal': '交易日志',
  'Trade journal': '交易日志',
  'High Impact News': '高影响新闻',
  'High-impact news': '高影响新闻',
  'No-Trade Condition': '不交易条件',
  'Prop Firm Daily Loss': 'Prop Firm 每日亏损限制',
  'Prop Firm Max Loss': 'Prop Firm 最大亏损限制',
  'Following Trading Plan': '遵守交易计划',
  'Pip': '点',
  'Bid price': '买价',
  'Ask price': '卖价',
  'Expectancy': '期望值',
  'Support': '支撑',
  'Resistance': '阻力',
  'Pullback': '回调',
  'Consolidation': '盘整',
  'Higher high and higher low': '更高高点与更高低点',
  'Lower high and lower low': '更低高点与更低低点',
  'Moving average': '移动平均线',
  'MACD': 'MACD',
  'ATR': 'ATR',
  'Volume': '成交量',
  'Position sizing': '仓位计算',
  'Correlation': '相关性',
  'Divergence': '背离',
  'Candlestick body': 'K线实体',
  'Candlestick wick': 'K线影线',
  'Doji candle': '十字星K线',
  'Engulfing pattern': '吞没形态',
  'Pin bar': 'Pin Bar',
  'Economic calendar': '财经日历',
  'Backtesting': '回测',
  'Demo account': '模拟账户',
  'Overtrading': '过度交易',
  'Revenge trading': '报复性交易',
  'Trailing stop': '移动止损',
  'Breakeven stop': '保本止损',
  'Trading plan': '交易计划',
  'Risk of ruin': '爆仓风险',
  'Higher High': '更高高点',
  'Higher Low': '更高低点',
  'Higher low': '更高低点',
  'Lower High': '更低高点',
  'Lower Low': '更低低点',
  'BOS': '结构突破',
  'CHOCH': '结构转变',
  'Protected High': '受保护高点',
  'Protected Low': '受保护低点',
  'Internal Structure': '内部结构',
  'Major Structure': '主要结构',
  'Support Zone': '支撑区',
  'Resistance Zone': '阻力区',
  'Body and Wick Zone': '实体与影线区',
  'Breakout': '突破',
  'Retest': '回踩测试',
  'Fakeout': '假突破',
  'Pullback Entry': '回调入场',
  'Breakout Retest Entry': '突破回踩入场',
  'Momentum Entry': '动能入场',
  'Reversal Entry': '反转入场',
  'Confirmation Candle': '确认K线',
  'Confirmation candle': '确认K线',
  'Rejection Wick': '拒绝影线',
  'Engulfing Candle': '吞没K线',
  'SL Placement': '止损设置',
  'TP Placement': '止盈设置',
  'Breakeven Movement': '保本移动',
  'Layering': '分层加仓',
  'Scaling Out': '分批止盈',
  'Scaling out': '分批止盈',
  'EMA': 'EMA 指数移动平均线',
  'RSI': 'RSI 相对强弱指标',
  'Trendline': '趋势线',
  'VWAP': 'VWAP 成交量加权均价',
  'Volume Profile': '成交量分布',
  'Opening Range': '开盘区间',
  'FVG': '公允价值缺口',
  'Order Block': '订单块',
  'Mitigated Order Block': '已缓解订单块',
  'POI': '兴趣点',
  'Gold Volatility': '黄金波动性',
  'Gold Spread Impact': '黄金点差影响',
  'Gold News Behavior': '黄金新闻行情',
  'H4 Bias': 'H4 偏向',
  'H1 Internal Structure': 'H1 内部结构',
  'M15 Entry Structure': 'M15 入场结构',
  'Timeframe Conflict': '周期冲突',
  'HTF Direction vs LTF Pullback': '高周期方向与低周期回调',
  'News Filter': '新闻过滤',
  'Bullish BOS': '看涨结构突破',
  'Bearish BOS': '看跌结构突破',
  'Bullish CHOCH': '看涨结构转变',
  'Bearish CHOCH': '看跌结构转变',
  'Higher high and higher low continuation': '更高高点与更高低点延续',
  'Lower low and lower high continuation': '更低低点与更低高点延续',
  'Support rejection': '支撑拒绝',
  'Resistance rejection': '阻力拒绝',
  'Breakout retest buy': '突破回踩买入',
  'Breakout retest sell': '突破回踩卖出',
  'Fake breakout above resistance': '阻力上方假突破',
  'Fake breakdown below support': '支撑下方假跌破',
  'Liquidity sweep buy setup': '流动性扫荡买入设定',
  'Liquidity sweep sell setup': '流动性扫荡卖出设定',
  'Trendline rejection buy': '趋势线拒绝买入',
  'Trendline rejection sell': '趋势线拒绝卖出',
  'EMA bullish continuation': 'EMA 看涨延续',
  'EMA bearish continuation': 'EMA 看跌延续',
  'RSI overbought reversal': 'RSI 超买反转',
  'RSI oversold reversal': 'RSI 超卖反转',
  'Opening range breakout': '开盘区间突破',
  'Opening range fakeout': '开盘区间假突破',
  'FVG continuation': 'FVG 延续',
  'Order block reaction': '订单块反应',
  'Mitigated order block invalidation': '已缓解订单块失效',
  'POI reaction': 'POI 反应',
  'HTF bullish but LTF pullback': '高周期看涨但低周期回调',
  'HTF bearish but LTF pullback': '高周期看跌但低周期回调',
  'Timeframe conflict no-trade': '周期冲突不交易',
  'Unclear structure no-trade': '结构不清晰不交易',
  'Poor risk-to-reward setup': '风险回报差的设定',
  'Valid SL below protected low': '有效止损在受保护低点下方',
  'Valid SL above protected high': '有效止损在受保护高点上方',
  'TP at next liquidity area': '止盈在下一个流动性区域',
  'BE movement after price reaches 1R': '价格到达 1R 后保本移动',
  'Layering after breakout retest': '突破回踩后的分层加仓',
  'Invalid layer because price is too extended': '价格过度延伸导致加仓无效',
  'Gold high-volatility candle': '黄金高波动K线',
  'News spike invalid setup': '新闻尖峰导致设定无效',
  'Session-based breakout': '时段突破',
};

const _applicationZh = <String, String>{
  'check pair direction, spread, session, and risk before entering':
      '在进场前检查货币对方向、点差、交易时段和风险',
  'respect gold volatility and calculate lot size before entry':
      '尊重黄金波动性，并在进场前计算手数',
  'match position volume to risk percentage and stop distance':
      '让仓位规模匹配风险百分比和止损距离',
  'keep risk small because profit and loss scale with exposure':
      '保持较小风险，因为盈亏会随敞口放大',
  'keep enough free margin before adding positions': '在加仓前保留足够可用保证金',
  'monitor live account value while positions are open': '持仓期间监控实时账户净值',
  'compare it with equity to see open-trade impact': '将其与净值对比，查看持仓影响',
  'avoid treating open profit as final before closing': '避免在平仓前把浮动盈利当成最终盈利',
  'include it when planning entries, stops, and scalps': '在规划入场、止损和剥头皮交易时计入它',
  'check rollover cost before holding overnight': '隔夜持仓前检查展期成本',
  'expect worse fills during news and fast markets': '在新闻和快速行情中预期更差成交',
  'check high-impact news before technical entries': '技术入场前检查高影响新闻',
  'combine chart evidence with invalidation and risk': '把图表证据与失效条件和风险结合',
  'adjust expectations because volatility changes by session': '根据不同时段波动变化调整预期',
  'prefer cleaner execution around liquid sessions and levels':
      '优先在流动性较好的时段和价位附近执行',
  'reduce lot size or widen plans when ranges expand': '当波幅扩大时减少手数或放宽计划',
  'define max loss before entry': '进场前定义最大亏损',
  'protect capital before chasing returns': '先保护资金，再追求收益',
  'reduce risk when drawdown breaches the plan': '当回撤超出计划时降低风险',
  'judge it together with reward-to-risk': '结合回报风险比一起评估',
  'skip trades where target does not justify risk': '跳过目标无法证明风险合理的交易',
  'calculate maximum planned loss before lot size': '在确定手数前计算最大计划亏损',
  'verify point value before sizing XAUUSD': '给 XAUUSD 定仓前确认点值',
  'estimate loss from entry to stop before trading': '交易前估算从入场到止损的亏损',
  'place targets near realistic liquidity or structure': '把目标放在现实的流动性或结构附近',
  'place stops beyond invalidation, not randomly': '把止损放在失效点之外，而不是随机放置',
  'move BE only when the setup has earned protection': '只有当交易设定已经值得保护时才移动到保本',
  'use it only when immediate entry is part of the plan': '只有当立即入场属于计划时才使用它',
  'use it for planned pullback entries': '用于计划中的回调入场',
  'use it for confirmed momentum entries': '用于确认后的动能入场',
  'use it when waiting for support pullback': '等待支撑回调时使用它',
  'use it when waiting for resistance pullback': '等待阻力回调时使用它',
  'use it when buying only after breakout confirmation': '只在突破确认后买入时使用它',
  'use it when selling only after breakdown confirmation': '只在跌破确认后卖出时使用它',
  'predefine entry, stop, and target before placing it': '下单前预先定义入场、止损和目标',
  'lock some profit while allowing continuation': '锁定部分利润，同时保留延续机会',
  'use it only when the trade reason becomes invalid': '只有当交易理由失效时才使用它',
  'avoid emotional changes after entry': '避免进场后情绪化修改',
  'check it before adding exposure': '增加敞口前先检查它',
  'reduce exposure before the account is forced': '在账户被强制处理前降低敞口',
  'include it when reviewing net performance': '复盘净表现时计入它',
  'convert risk correctly when instruments quote differently': '当品种报价不同时正确换算风险',
  'wait for confirmation instead of chasing the wick': '等待确认，而不是追逐影线',
  'record it separately from normal wins and losses': '与普通盈利和亏损分开记录',
  'use it to find repeated mistakes': '用它找出重复错误',
  'avoid fresh trades if the plan excludes news risk': '如果计划排除新闻风险，就避免新开仓',
  'protect discipline by accepting no-trade decisions': '接受不交易决定以保护纪律',
  'stop trading before breaching the rule': '在触犯规则前停止交易',
  'size positions so one idea cannot breach the account': '控制仓位，使单个想法不能突破账户规则',
  'measure success by process, not only money': '用过程衡量成功，而不只看金钱',
  'bullish structure is progressing if higher lows also hold':
      '如果更高低点也守住，看涨结构正在推进',
  'bullish continuation remains valid while it holds': '只要它守住，看涨延续仍然有效',
  'bearish continuation remains valid while it holds': '只要它守住，看跌延续仍然有效',
  'bearish structure is progressing if lower highs also hold':
      '如果更低高点也守住，看跌结构正在推进',
  'continuation is more likely when the break closes cleanly': '突破干净收盘时，延续更有可能',
  'confirmation is needed before calling full reversal': '确认之后才能判断为完整反转',
  'a break above it can invalidate shorts': '突破其上方可能使空头思路失效',
  'a break below it can invalidate longs': '跌破其下方可能使多头思路失效',
  'avoid confusing small noise with major direction': '避免把小级别噪音误认为主要方向',
  'avoid entries directly into major opposing levels': '避免直接进场撞向主要反向价位',
  'watch candle reaction before buying': '买入前观察K线反应',
  'watch candle reaction before selling': '卖出前观察K线反应',
  'mark the reaction area rather than one exact line': '标记反应区域，而不是单一精确线',
  'confirm close quality and avoid wick-only breaks': '确认收盘质量，避免只看影线突破',
  'look for former resistance as support or support as resistance':
      '观察原阻力变支撑或原支撑变阻力',
  'avoid chasing and wait for confirmation': '避免追单，等待确认',
  'wait for reclaim before reversal entry': '等待重新收回价位后再反转入场',
  'wait for reaction instead of chasing impulse': '等待反应，而不是追逐冲动走势',
  'avoid entering before breakout is confirmed': '避免在突破确认前入场',
  'manage slippage and avoid late exhaustion': '管理滑点并避免追在衰竭末端',
  'require stronger proof than one countertrend candle': '需要比一根逆势K线更强的证据',
  'wait for close if the plan requires confirmation': '如果计划要求确认，就等待收盘',
  'read it only at meaningful structure': '只在有意义的结构位置解读它',
  'use it with level and structure context': '结合价位和结构背景使用它',
  'protect capital when evidence is unclear': '证据不清晰时保护资金',
  'place it where the idea is proven wrong': '把它放在思路被证明错误的位置',
  'avoid unrealistic targets beyond strong opposing zones':
      '避免把目标放到强反向区域之外的不现实位置',
  'avoid moving too early without structure': '避免没有结构支持就过早移动',
  'calculate combined risk before adding': '加仓前计算总风险',
  'reduce exposure without abandoning the setup': '在不放弃交易设定的情况下降低敞口',
  'skip setups with poor RR even if direction looks right':
      '即使方向看起来正确，也跳过 RR 差的设定',
  'use slope and location as context': '把斜率和位置作为背景信息',
  'do not use it as a standalone entry': '不要把它当作独立入场信号',
  'combine it with structure and levels': '将它与结构和价位结合',
  'read it with session context': '结合交易时段背景解读',
  'identify value areas and low-volume rejection': '识别价值区域和低成交量拒绝',
  'watch breakout and fakeout behavior': '观察突破和假突破行为',
  'use it only when aligned with structure': '只在与结构一致时使用它',
  'avoid using it without confirmation': '避免在没有确认时使用它',
  'reduce confidence after repeated mitigation': '多次缓解后降低信心',
  'define why the area matters before entry': '进场前定义该区域为何重要',
  'adapt stop distance and lot size': '调整止损距离和手数',
  'include spread in stop and entry planning': '在止损和入场计划中计入点差',
  'avoid oversized trades during high-impact events': '高影响事件期间避免过大仓位',
  'do not fight HTF without reversal evidence': '没有反转证据时不要对抗高周期',
  'map pullback and internal trend': '标记回调和内部趋势',
  'use it for trigger, not overall bias alone': '用它做触发，不要单独作为整体偏向',
  'stand aside when confluence is weak': '共振较弱时观望',
  'treat it as pullback until major structure breaks': '在主要结构被打破前，将其视为回调',
  'protect execution and prop-firm limits': '保护执行质量和 Prop Firm 限制',
};

const _exactZh = <String, String>{
  ..._termZhExact,
  ..._applicationZh,
  'What is the primary purpose of a stop loss?': '止损的主要目的是什么？',
  'What is disciplined trading?': '什么是有纪律的交易？',
  'Why should a trader follow a written plan?': '交易者为什么应该遵循书面交易计划？',
  'To guarantee profit': '保证盈利',
  'To limit loss when the setup is invalidated': '在交易设定失效时限制亏损',
  'To increase leverage': '增加杠杆',
  'To avoid planning': '避免做计划',
  'A stop loss defines the maximum planned loss when the trade idea is wrong.':
      '止损用于在交易思路错误时定义最大计划亏损。',
  'Following a plan': '遵循计划',
  'Increasing risk': '增加风险',
  'Removing stops': '移除止损',
  'Chasing losses': '追亏',
  'To improve consistency': '提高一致性',
  'To chase losses': '追回亏损',
  'To remove stops': '移除止损',
  'To increase emotional decisions': '增加情绪化决策',
  'What is best practice for Scaling out?': '分批止盈的最佳做法是什么？',
  'What best describes Support?': '以下哪项最能描述支撑？',
  'What best describes Resistance?': '以下哪项最能描述阻力？',
  'controls trade exposure': '控制交易敞口',
  'calculate it from risk and stop distance': '根据风险和止损距离计算仓位',
  'increases market exposure': '增加市场敞口',
  'use it without increasing planned risk': '在不增加计划风险的情况下使用杠杆',
  'supports an open leveraged position': '支撑已开立的杠杆仓位',
  'keep sufficient free margin': '保持充足的可用保证金',
  'measures decline from an account peak': '衡量账户从峰值回落的幅度',
  'reduce risk when limits are approached': '接近限制时降低风险',
  'compares potential reward with loss': '比较潜在回报与潜在亏损',
  'require adequate reward for the risk': '要求风险对应足够的回报',
  'records decisions and outcomes': '记录决策和结果',
  'review it for repeated behavior': '复盘其中的重复行为',
  'is the gap between bid and ask': '是买价和卖价之间的差距',
  'include it in entry and stop planning': '在入场和止损计划中计入点差',
  'is the difference between expected and filled price': '是预期价格与实际成交价格之间的差异',
  'expect it in fast markets': '在快速行情中预期会出现滑点',
  'can sharply increase volatility': '会大幅提高波动性',
  'check the calendar before entry': '入场前查看财经日历',
  'wait for confirmation at the zone': '在该区域等待确认',
  'is a decisive move beyond a level': '是价格明确突破某一价位的走势',
  'confirm the close and possible retest': '确认收盘质量和可能的回踩',
  'runs beyond obvious highs or lows': '扫过明显高点或低点',
  'wait for reclaim before reversal': '等待价格重新收回后再考虑反转',
  'invalidate the idea if it breaks': '若它被打破，则该思路失效',
  'closes in favor of a setup': '按照交易设定方向收盘',
  'wait for its close when required': '需要确认时等待K线收盘',
  'protects a trade near entry': '在入场附近保护交易',
  'move the stop only after price earns it': '只有价格走出足够空间后才移动止损',
  'closes part of a position': '平掉部分仓位',
  'is a planned reason to stay out': '是计划中不进场的理由',
  'protect capital when evidence is weak': '证据较弱时保护资金',
  'is emotional trading after a loss': '是亏损后的情绪化交易',
  'pause and keep normal risk': '暂停交易并保持正常风险',
  'is fear of missing a move': '是害怕错过行情',
  'wait for the next valid setup': '等待下一个有效交易设定',
  'can reduce discipline after wins': '盈利后可能削弱纪律',
  'keep normal risk and full confirmation': '保持正常风险并等待完整确认',
  'caps loss for one trading day': '限制单个交易日的亏损',
  'stop before breaching the rule': '在触犯规则前停止交易',
  'means repeating a sound process': '意味着重复良好的交易流程',
  'take partials only by plan': '只按计划分批止盈',
  'increase risk without a plan': '无计划地增加风险',
  'ignore setup invalidation': '忽视交易设定的失效条件',
  'decide from emotion only': '只凭情绪决定',
  'supports bullish structure': '支撑看涨结构',
  'supports bearish structure': '支撑看跌结构',
  'confirms continuation only with context': '只有结合上下文才确认延续',
  'confirms reversal only with context': '只有结合上下文才确认反转',
  'is an area where buyers reacted': '是买方曾经反应的区域',
  'is an area where sellers reacted': '是卖方曾经反应的区域',
  'is a price area where buying interest may slow or reverse a decline':
      '是买盘兴趣可能减缓或反转下跌的价格区域',
  'is a price area where selling interest may slow or reverse an advance':
      '是卖盘兴趣可能减缓或反转上涨的价格区域',
  'is a swing high above the previous meaningful high': '是高于前一个重要高点的摆动高点',
  'is a pullback low above the previous meaningful low': '是高于前一个重要低点的回调低点',
  'is a rally high below the previous meaningful high': '是低于前一个重要高点的反弹高点',
  'is a swing low below the previous meaningful low': '是低于前一个重要低点的摆动低点',
  'is a break of structure in the trend direction': '是顺着趋势方向发生的结构突破',
  'is a change of character that warns momentum may be shifting':
      '是提示动能可能转变的结构转变',
  'is an area where buying interest has reacted before': '是过去出现买盘反应的区域',
  'is an area where selling interest has reacted before': '是过去出现卖盘反应的区域',
  'price moves beyond a level': '价格突破某个价位',
  'price returns to a broken level': '价格回到已突破价位测试',
  'price fails a breakout and returns inside range': '价格突破失败并回到区间内',
  'wait for confirmation': '等待确认',
  'use with market structure context': '结合市场结构背景使用',
  'avoid trading without invalidation': '避免在没有失效条件时交易',
  'not enough information to make that conclusion': '没有足够信息得出该结论',
  'Not enough information to make that conclusion.': '没有足够信息得出该结论。',
  'A guaranteed signal that the next trade will win.': '保证下一笔交易会盈利的信号。',
  'A broker-only setting that does not affect risk.': '不影响风险的经纪商设置。',
  'A chart decoration that should be ignored.': '应该忽略的图表装饰。',
  'They increase lot size first and decide risk later.': '他们先增加手数，之后才决定风险。',
  'They ignore it because only final profit matters.': '他们忽略它，因为只看最终利润。',
  'They remove the stop loss to give the trade room.': '他们移除止损，给交易更多空间。',
  'Treating it emotionally instead of using it inside a written risk plan.':
      '情绪化处理它，而不是把它纳入书面风险计划。',
  'Recording it in the trading journal after the trade.': '交易后把它记录在交易日志中。',
  'Checking it before choosing position size.': '在选择仓位前检查它。',
  'Using it to decide whether the trade is valid.': '用它判断交易是否有效。',
  'Foundation knowledge becomes useful only when it supports a consistent process.':
      '基础知识只有在支持一致流程时才真正有用。',
  'Buying one currency while selling another to profit from exchange-rate movement.':
      '买入一种货币并同时卖出另一种货币，以从汇率变动中获利。',
  'Spot gold priced against the US dollar.': '以美元计价的现货黄金。',
  'The volume of a trade position.': '交易仓位的成交量大小。',
  'Borrowed exposure that lets smaller margin control a larger position.':
      '通过借来的敞口，让较小保证金控制更大仓位。',
  'Capital held by the broker to support an open leveraged position.':
      '经纪商为支持已开杠杆仓位而占用的资金。',
  'Balance plus or minus current floating profit and loss.':
      '余额加上或减去当前浮动盈亏后的账户价值。',
  'Closed account value excluding floating profit and loss.':
      '不包含浮动盈亏的已结算账户价值。',
  'Unrealized gain or loss from open trades.': '未平仓交易产生的未实现盈利或亏损。',
  'The difference between bid and ask price.': '买价和卖价之间的差额。',
  'Overnight financing charge or credit for holding a position.':
      '隔夜持仓产生的融资费用或利息收入。',
  'The difference between expected price and actual fill price.':
      '预期成交价与实际成交价之间的差异。',
  'Studying economic data, central banks, and macro drivers.':
      '研究经济数据、央行政策和宏观驱动因素。',
  'Reading charts, structure, levels, indicators, and price action.':
      '解读图表、结构、价位、指标和价格行为。',
  'A trading period such as Asian, London, or New York hours.':
      '亚洲、伦敦或纽约等交易时段。',
  'How easily price can trade size without major disruption.':
      '价格在不被大幅扰动的情况下承接交易量的难易程度。',
  'The speed and size of price movement.': '价格移动的速度和幅度。',
  'Rules controlling how much capital is exposed to loss.':
      '控制有多少资金暴露在亏损风险中的规则。',
  'Managing account survival, allocation, and drawdown.': '管理账户生存、资金分配和回撤。',
  'A decline from account peak to a lower value.': '账户从峰值下降到较低数值的幅度。',
  'The percentage of trades that close profitably.': '以盈利结束的交易所占百分比。',
  'Potential reward divided by potential loss.': '潜在回报除以潜在亏损。',
  'The amount or percentage intentionally exposed on one idea.':
      '单个交易想法中有意承担的金额或百分比风险。',
  'Gold price movement units based on broker quote format.':
      '根据经纪商报价格式计算的黄金价格移动单位。',
  'Money gained or lost from XAUUSD movement, lot size, and contract value.':
      '由 XAUUSD 波动、手数和合约价值决定的盈利或亏损。',
  'A planned exit at the profit target.': '在盈利目标处计划好的出场。',
  'A planned exit that limits loss when the idea is invalidated.':
      '当交易思路失效时限制亏损的计划出场。',
  'Moving stop near entry so remaining loss risk is small or zero.':
      '把止损移到入场附近，使剩余亏损风险很小或为零。',
  'An order intended to execute immediately.': '旨在立即成交的订单。',
  'An order to buy lower or sell higher than current price.':
      '在低于当前价买入或高于当前价卖出的订单。',
  'An order that triggers after price trades through a level.':
      '价格穿越某个价位后触发的订单。',
  'A pending buy order below current price.': '低于当前价格的买入挂单。',
  'A pending sell order above current price.': '高于当前价格的卖出挂单。',
  'A pending buy order above current price.': '高于当前价格的买入挂单。',
  'A pending sell order below current price.': '低于当前价格的卖出挂单。',
  'An order waiting for price conditions before execution.': '等待价格条件满足后才执行的订单。',
  'Closing part of a position while leaving the rest open.': '平掉部分仓位，同时保留剩余仓位。',
  'Closing directly before TP or SL executes.': '在止盈或止损执行前直接平仓。',
  'Target and stop levels planned before or at entry.': '进场前或进场时计划好的目标位和止损位。',
  'Equity not tied up as required margin.': '未被所需保证金占用的净值。',
  'A broker warning or action when equity is too low for margin.':
      '当净值不足以维持保证金时，经纪商发出的警告或采取的行动。',
  'A transaction fee charged by the broker.': '经纪商收取的交易费用。',
  'The currency used for account balance and P/L.': '用于账户余额和盈亏计算的币种。',
  'A move through obvious highs/lows before returning into range.':
      '价格扫过明显高点/低点后又回到区间内的走势。',
  'A trade closed near entry with little or no gain/loss.':
      '在入场附近平仓、几乎没有盈亏的交易。',
  'A structured record of setup, execution, emotion, and lesson.':
      '对交易设定、执行、情绪和经验教训的结构化记录。',
  'Scheduled events that can increase volatility and slippage.':
      '可能增加波动和滑点的预定事件。',
  'A market state where the plan says not to enter.': '交易计划规定不应入场的市场状态。',
  'The maximum loss allowed for a trading day.': '一个交易日允许的最大亏损。',
  'The maximum total drawdown allowed by the account rules.': '账户规则允许的最大总回撤。',
  'Executing only the setups, risk, and exits defined beforehand.':
      '只执行事先定义好的交易设定、风险和出场。',
  'A guarantee that the next candle will reverse.': '保证下一根K线会反转。',
  'A broker setting that removes execution risk.': '一种消除执行风险的经纪商设置。',
  'A reason to trade without invalidation.': '一个不设失效条件也能交易的理由。',
  'They enter immediately with maximum lot size.': '他们立即用最大手数进场。',
  'They ignore the higher timeframe and trade the first candle.':
      '他们忽视更高周期，只交易第一根K线。',
  'They move the stop farther whenever price hesitates.': '价格一犹豫，他们就把止损移得更远。',
  'The price action or level that invalidates the original setup logic.':
      '使原始交易设定逻辑失效的价格行为或价位。',
  'The trader feeling uncomfortable.': '交易者感觉不舒服。',
  'The profit target being far away.': '止盈目标很远。',
  'The previous trade outcome.': '上一笔交易的结果。',
  'Professional technical analysis connects the concept to entry, invalidation, and risk.':
      '专业技术分析会把概念与入场、失效条件和风险连接起来。',
  'Invalidation must come from the setup idea, not emotion or desired profit.':
      '失效条件必须来自交易设定本身，而不是情绪或想要的利润。',
  'Closing partial position at planned areas.': '在计划区域平掉部分仓位。',
  'A swing high above the previous meaningful high.': '高于前一个重要高点的摆动高点。',
  'A pullback low above the previous meaningful low.': '高于前一个重要低点的回调低点。',
  'A rally high below the previous meaningful high.': '低于前一个重要高点的反弹高点。',
  'A swing low below the previous meaningful low.': '低于前一个重要低点的摆动低点。',
  'A break of structure in the trend direction.': '顺着趋势方向发生的结构突破。',
  'A change of character that warns momentum may be shifting.':
      '提示动能可能正在转变的结构转变。',
  'A high that should hold if the bearish idea is valid.': '如果看跌思路有效，应当守住的高点。',
  'A low that should hold if the bullish idea is valid.': '如果看涨思路有效，应当守住的低点。',
  'Smaller swings inside a larger structure leg.': '大结构腿内部的小级别摆动。',
  'Higher timeframe swings defining broader context.': '定义更大背景的高周期摆动。',
  'An area where buying interest has reacted before.': '过去出现买盘反应的区域。',
  'An area where selling interest has reacted before.': '过去出现卖盘反应的区域。',
  'A zone using candle bodies and rejection wicks.': '用K线实体和拒绝影线划出的区域。',
  'Price closing beyond a meaningful range or level.': '价格收盘突破有意义的区间或价位。',
  'Price returning to test a broken level.': '价格回到已突破价位进行测试。',
  'A failed breakout that returns inside the range.': '突破失败并回到区间内部。',
  'A stop run through obvious highs/lows before reversal.':
      '反转前扫过明显高点/低点的止损扫荡。',
  'Entering after retracement into a planned zone.': '价格回撤到计划区域后入场。',
  'Entering after breakout and successful retest.': '突破并成功回踩后入场。',
  'Entering after strong directional confirmation.': '强方向确认后入场。',
  'Entering after evidence direction may change.': '出现方向可能转变的证据后入场。',
  'A candle that closes in favor of the setup.': '收盘方向支持交易设定的K线。',
  'A wick showing price failed to hold beyond a level.': '显示价格无法站稳某价位之外的影线。',
  'A candle body that covers the previous body.': '实体覆盖前一根K线实体的K线。',
  'A situation where rules say to stand aside.': '规则要求观望的情况。',
  'Stop placement beyond setup invalidation.': '把止损放在交易设定失效点之外。',
  'Target placement near liquidity or structure.': '把目标放在流动性或结构附近。',
  'Moving stop near entry after price earns protection.':
      '当价格已经值得保护后，把止损移动到入场附近。',
  'Adding positions in stages by rule.': '按规则分阶段增加仓位。',
  'Reward potential compared with risk.': '潜在回报与风险的比较。',
  'A moving average that weights recent price more strongly.': '更重视近期价格的移动平均线。',
  'A momentum oscillator for overbought/oversold and divergence clues.':
      '用于超买、超卖和背离线索的动能震荡指标。',
  'A line connecting meaningful swing highs or lows.': '连接有意义摆动高点或低点的线。',
  'Volume-weighted average price used as intraday value.': '作为日内价值参考的成交量加权均价。',
  'Volume by price area.': '按价格区域统计的成交量。',
  'The high/low of an early session window.': '交易时段早期窗口的高低点。',
  'A fair value gap left by displacement.': '由位移行情留下的公允价值缺口。',
  'A prior candle zone associated with displacement.': '与位移行情相关的先前K线区域。',
  'An order block already revisited by price.': '已经被价格回访过的订单块。',
  'Point of interest where reaction is expected.': '预期会出现反应的兴趣点。',
  'Gold often moves faster and wider than many FX pairs.':
      '黄金通常比许多外汇货币对移动更快、波幅更大。',
  'XAUUSD spread can affect tight entries.': 'XAUUSD 点差会影响紧密入场。',
  'Gold can spike sharply around USD news.': '美元新闻前后黄金可能剧烈尖峰波动。',
  'Higher timeframe directional context.': '更高周期的方向背景。',
  'Medium timeframe swing context.': '中周期摆动背景。',
  'Lower timeframe structure for timing.': '用于把握时机的低周期结构。',
  'Higher and lower timeframes disagree.': '高周期与低周期方向不一致。',
  'Lower timeframe moves against higher timeframe trend.': '低周期走势与高周期趋势相反。',
  'Avoiding setups around scheduled high-impact news.': '避开计划内高影响新闻附近的交易设定。',
  'No valid information can be taken from the chart.': '无法从图表中获得有效信息。',
  'The trader should ignore invalidation and enter immediately.':
      '交易者应忽略失效条件并立即进场。',
  'The opposite signal is confirmed without waiting for structure.':
      '无需等待结构即可确认相反信号。',
  'A bullish break of structure is shown.': '图表显示看涨结构突破。',
  'A bearish break of structure is shown.': '图表显示看跌结构突破。',
  'A bullish change of character is shown.': '图表显示看涨结构转变。',
  'A bearish change of character is shown.': '图表显示看跌结构转变。',
  'Bullish continuation is valid while the higher low holds.':
      '只要更高低点守住，看涨延续有效。',
  'Bearish continuation is valid while the lower high holds.':
      '只要更低高点守住，看跌延续有效。',
  'Support rejection is visible.': '图表可见支撑拒绝。',
  'Resistance rejection is visible.': '图表可见阻力拒绝。',
  'A bullish breakout retest entry is shown.': '图表显示看涨突破回踩入场。',
  'A bearish breakout retest entry is shown.': '图表显示看跌突破回踩入场。',
  'A fake breakout above resistance is shown.': '图表显示阻力上方假突破。',
  'A fake breakdown below support is shown.': '图表显示支撑下方假跌破。',
  'A buy-side reversal after sell-side liquidity sweep is shown.':
      '图表显示扫卖方流动性后的买方反转。',
  'A sell-side reversal after buy-side liquidity sweep is shown.':
      '图表显示扫买方流动性后的卖方反转。',
  'Bullish trendline rejection is shown.': '图表显示看涨趋势线拒绝。',
  'Bearish trendline rejection is shown.': '图表显示看跌趋势线拒绝。',
  'EMA bullish continuation is shown.': '图表显示 EMA 看涨延续。',
  'EMA bearish continuation is shown.': '图表显示 EMA 看跌延续。',
  'An overbought reversal warning is shown.': '图表显示超买反转警示。',
  'An oversold reversal warning is shown.': '图表显示超卖反转警示。',
  'A valid opening range breakout is shown.': '图表显示有效开盘区间突破。',
  'An opening range fakeout is shown.': '图表显示开盘区间假突破。',
  'A fair value gap continuation setup is shown.': '图表显示 FVG 延续设定。',
  'An order block reaction is shown.': '图表显示订单块反应。',
  'The order block is weakened or invalidated.': '订单块已经减弱或失效。',
  'A reaction from a point of interest is shown.': '图表显示兴趣点反应。',
  'The lower timeframe move is a pullback inside HTF bullish bias.':
      '低周期走势是在高周期看涨偏向中的回调。',
  'The lower timeframe move is a pullback inside HTF bearish bias.':
      '低周期走势是在高周期看跌偏向中的回调。',
  'No trade is preferred because timeframe signals conflict.':
      '因为周期信号冲突，优先不交易。',
  'No trade is preferred because structure is unclear.': '因为结构不清晰，优先不交易。',
  'The setup is invalid because reward-to-risk is poor.': '因为回报风险比差，该设定无效。',
  'The valid long stop is below the protected low.': '有效多单止损在受保护低点下方。',
  'The valid short stop is above the protected high.': '有效空单止损在受保护高点上方。',
  'The logical TP is the next liquidity area.': '合理止盈位是下一个流动性区域。',
  'Moving to breakeven is reasonable after price reaches 1R and structure supports it.':
      '价格到达 1R 且结构支持时，移动到保本是合理的。',
  'A controlled layer after breakout retest is valid.': '突破回踩确认后的受控加仓是有效的。',
  'The layer is invalid because price is extended far from structure.':
      '价格远离结构并过度延伸，因此加仓无效。',
  'Gold volatility risk is high.': '黄金波动风险很高。',
  'The setup is invalid because the move is a news spike.': '因为走势是新闻尖峰，该设定无效。',
  'A session breakout is shown.': '图表显示时段突破。',
  'Enter immediately with larger lot to maximize or recover quickly.':
      '立即用更大手数进场，以最大化收益或快速追回亏损。',
  'Remove or move the stop loss because the trader feels strongly.':
      '因为交易者感觉强烈，就移除或移动止损。',
  'Ignore the trading plan and decide based on the last candle only.':
      '忽略交易计划，只根据最后一根K线决定。',
  'Wait for a fresh valid setup and keep the same risk plan.':
      '等待新的有效设定，并保持相同风险计划。',
  'Wait for a retest or another valid setup.': '等待回踩或另一个有效设定。',
  'Follow the preset management plan or planned partial rule.':
      '遵循预设管理计划或计划内分批规则。',
  'Hold only if risk and invalidation remain inside the plan.':
      '只有当风险和失效条件仍在计划内时才持有。',
  'Keep the SL and accept the planned risk.': '保留止损并接受计划内风险。',
  'Follow the preset TP or planned trailing rule.': '遵循预设止盈或计划内移动规则。',
  'Stop trading because the daily loss limit has already been reached.':
      '停止交易，因为每日亏损限制已经达到。',
  'Skip the weak setup and keep normal criteria.': '跳过弱设定，并保持正常标准。',
  'Pause and only trade a valid planned setup later.': '暂停，之后只交易有效且计划内的设定。',
  'Keep risk capped or stop trading to reset.': '保持风险上限，或停止交易重置状态。',
  'Keep the same risk plan unless the strategy rules say otherwise.':
      '除非策略规则另有说明，否则保持相同风险计划。',
  'Wait for the candle to close if the plan requires confirmation.':
      '如果计划要求确认，就等待K线收盘。',
  'Wait for retest or another valid entry model.': '等待回踩或另一个有效入场模型。',
  'Only close manually if the trade reason is invalidated or rules allow it.':
      '只有当交易理由失效或规则允许时才手动平仓。',
  'Do not widen SL emotionally; accept the planned invalidation.':
      '不要情绪化放宽止损；接受计划内失效条件。',
  'Respect TP or use only the planned trailing rule.': '尊重止盈，或只使用计划内移动规则。',
  'Do not add late layers if risk-to-reward and combined risk are invalid.':
      '如果回报风险比和总风险无效，就不要在后段加仓。',
  'Accept the no-trade condition.': '接受不交易条件。',
  'Avoid trading if the plan excludes news risk.': '如果计划排除新闻风险，就避免交易。',
  'Stop or switch to review mode according to the plan.': '按计划停止交易或切换到复盘模式。',
  'Follow the management plan instead of reacting to frustration.':
      '遵循管理计划，而不是因沮丧而反应。',
  'Do not trade until a valid setup appears.': '在有效设定出现前不要交易。',
  'Validate the setup with the trader\'s own plan or skip it.':
      '用交易者自己的计划验证设定，否则跳过。',
  'Wait for a fresh valid setup.': '等待新的有效设定。',
  'Accept the no-trade day.': '接受无交易日。',
  'Review the repeated mistake and adjust the routine.': '复盘重复错误，并调整日常流程。',
  'Keep risk fixed or stop trading for review.': '保持固定风险，或停止交易复盘。',
  'Keep normal risk and require full setup confirmation.': '保持正常风险，并要求完整设定确认。',
  'Close or manage according to the written plan.': '按照书面计划平仓或管理。',
  'Stop trading and protect the account from rule breach.': '停止交易，保护账户避免触犯规则。',
  'The trader was stopped out, then price later reached the original TP area. The trader feels angry and wants to re-enter with double lot.':
      '交易者被止损出场，随后价格到达原本的止盈区域。交易者感到生气，想用双倍手数重新进场。',
  'Price broke out and ran without retesting. The trader missed the planned entry and fears missing profit.':
      '价格突破后没有回踩就快速运行。交易者错过计划入场，担心错过利润。',
  'The trade is in profit but has not reached the planned management area. The trader wants to close because of fear.':
      '交易已有浮盈，但尚未到达计划管理区域。交易者因为害怕而想平仓。',
  'Price pulls back against the trade but has not broken invalidation. The trader wants to panic close.':
      '价格向不利方向回调，但尚未打破失效条件。交易者想恐慌平仓。',
  'Price is close to SL. The trader wants to remove the stop to avoid being wrong.':
      '价格接近止损。交易者想移除止损，以避免承认错误。',
  'Price is near TP. The trader feels the move is strong and wants to move TP much further.':
      '价格接近止盈。交易者觉得走势很强，想把止盈大幅移远。',
  'The trader has reached daily loss limit. A clean-looking setup appears later.':
      '交易者已经达到每日亏损限制。之后又出现一个看起来干净的交易设定。',
  'After three wins, the trader sees a weak setup and feels unusually confident.':
      '连续三笔盈利后，交易者看到一个较弱设定，却异常自信。',
  'After two losses, a fast candle appears and the trader wants to recover quickly.':
      '连续两笔亏损后，一根快速K线出现，交易者想快速追回亏损。',
  'After a losing trade, the trader wants to risk heavily on the next setup.':
      '一笔亏损后，交易者想在下一个设定中承担重仓风险。',
  'After a strong winner, the trader wants to double lot on the next signal.':
      '一笔强势盈利后，交易者想在下一个信号上加倍手数。',
  'A confirmation candle is still forming. The trader wants to enter before it closes.':
      '确认K线仍在形成中。交易者想在收盘前提前入场。',
  'Price breaks out strongly without a retest. The trader wants to chase immediately.':
      '价格强势突破且没有回踩。交易者想立即追单。',
  'The trade is still valid, but the trader wants to manually close from discomfort.':
      '交易仍然有效，但交易者因不舒服而想手动平仓。',
  'Price approaches SL and the trader wants to move it wider.':
      '价格接近止损，交易者想把止损放宽。',
  'Price is almost at TP and the trader wants to cancel TP because the candle looks strong.':
      '价格几乎到达止盈，交易者因为K线看起来很强而想取消止盈。',
  'Price is far from the original entry and the trader wants to add more layers.':
      '价格已经远离原始入场点，交易者还想继续加仓。',
  'The market is choppy, but the trader wants action because nothing happened today.':
      '市场震荡杂乱，但交易者因为今天没什么行情而想找事做。',
  'High-impact news is minutes away and the trader feels excited by volatility.':
      '高影响新闻还有几分钟公布，交易者因波动而感到兴奋。',
  'The daily target is reached, but the trader wants one more trade.':
      '每日目标已经达成，但交易者还想再做一笔。',
  'Price nearly hit TP, pulled back, and the trader panics.':
      '价格差一点到达止盈后回调，交易者开始恐慌。',
  'The chart has no clean setup, but the trader is bored and wants to enter.':
      '图表没有干净设定，但交易者感到无聊并想进场。',
  'Someone posts a signal, but the trader has not validated the chart.':
      '有人发布信号，但交易者还没有验证图表。',
  'The trader manually closed, then immediately wants to re-enter from regret.':
      '交易者手动平仓后，因后悔而立即想重新进场。',
  'No clean setups appear all day, but the trader feels a day without trades is wasted.':
      '一整天都没有干净设定，但交易者觉得没有交易的一天是浪费。',
  'The journal shows repeated FOMO tags, but the trader wants to ignore them.':
      '交易日志显示重复的 FOMO 标签，但交易者想忽略它们。',
  'After two losses, the trader wants to increase lot size to recover.':
      '连续两笔亏损后，交易者想增加手数来追回。',
  'After three wins, the trader believes the next setup cannot fail.':
      '连续三笔盈利后，交易者认为下一个设定不可能失败。',
  'The trade has not hit TP, and the trader wants to hold overnight although the plan forbids it.':
      '交易尚未到达止盈，虽然计划禁止隔夜持仓，交易者仍想持有过夜。',
  'The account is near max loss. The trader sees a setup and wants to take it anyway.':
      '账户接近最大亏损限制。交易者看到一个设定，仍然想交易。',
};

final Map<String, String> _exactZhByLower = {
  for (final entry in _exactZh.entries) entry.key.toLowerCase(): entry.value,
};
