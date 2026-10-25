"use strict";

const fs = require("fs");
const path = require("path");

const outputPath = path.resolve(
  __dirname,
  "..",
  "assets",
  "seed",
  "final_exam_questions_500.json",
);

const categories = {
  theory: {
    prefix: "final_exam_theory",
    en: "Theory / Basic Trading Knowledge",
    zh: "理论 / 基础交易知识",
  },
  technical: {
    prefix: "final_exam_technical",
    en: "Technical Analysis / Market Analysis",
    zh: "技术分析 / 市场分析",
  },
  psychology: {
    prefix: "final_exam_psychology",
    en: "Trading Psychology / Risk Discipline",
    zh: "交易心理 / 风险纪律",
  },
};

const difficultyCycle = [
  "beginner",
  "intermediate",
  "beginner",
  "intermediate",
  "advanced",
];
const categorySerials = {theory: 0, technical: 0, psychology: 0};

const theoryTopics = [
  t("forex_basics", "Forex basics", "外汇基础", "forex_basics", "exchanging one currency for another through a quoted currency pair", "通过报价货币对把一种货币兑换成另一种货币", "identify what market is being traded before considering spread, session, and risk", "在考虑点差、交易时段和风险前，先确认正在交易的市场", "treating forex as a guaranteed-income product instead of a leveraged market", "把外汇当成保证收入的产品，而不是杠杆市场", ["forex", "market_basic"]),
  t("currency_pairs", "Currency pairs", "货币对", "currency_pairs", "the base currency and quote currency used to price an exchange rate", "用于表示汇率的基础货币和报价货币", "know which currency is being bought or sold when a trade is opened", "开仓时清楚买入或卖出的是哪一种货币", "opening a position without knowing which side of the pair is exposed", "不知道货币对哪一边有风险暴露就开仓", ["forex", "pairs"]),
  t("major_pairs", "major currency pairs", "主要货币对", "currency_pairs", "highly traded pairs that include the US dollar and usually have deeper liquidity", "包含美元且交易量较大的货币对，通常流动性更深", "compare liquidity and spread before choosing a pair to trade", "选择交易品种前比较流动性和点差", "assuming every pair has the same spread, speed, and execution risk", "认为所有货币对都有相同点差、速度和执行风险", ["major_pairs", "liquidity"]),
  t("minor_pairs", "minor currency pairs", "交叉货币对", "currency_pairs", "pairs that do not include the US dollar but still involve major currencies", "不包含美元但由主要货币组成的货币对", "check spread and session activity because liquidity can differ from majors", "检查点差和交易时段活跃度，因为流动性可能不同于主要货币对", "using the same execution assumptions as EURUSD without checking conditions", "不检查条件就套用 EURUSD 的执行假设", ["minor_pairs", "spread"]),
  t("exotic_pairs", "exotic currency pairs", "冷门货币对", "currency_pairs", "pairs involving a major currency and a less liquid emerging-market currency", "由主要货币和流动性较低的新兴市场货币组成的货币对", "treat wider spreads and sharper gaps as execution risks", "把更宽点差和更剧烈跳空视为执行风险", "increasing leverage because the pair looks unfamiliar or exciting", "因为品种陌生或刺激就提高杠杆", ["exotic_pairs", "execution"]),
  t("bid_price", "bid price", "买价", "pricing", "the price at which the market or broker is willing to buy from the trader", "市场或经纪商愿意从交易者手中买入的价格", "use it when estimating sell execution and spread cost", "估算卖出执行和点差成本时使用它", "confusing bid with ask and calculating exits from the wrong side", "把买价和卖价混淆，用错误一边计算出场", ["bid", "spread"]),
  t("ask_price", "ask price", "卖价", "pricing", "the price at which the market or broker is willing to sell to the trader", "市场或经纪商愿意卖给交易者的价格", "use it when estimating buy execution and entry cost", "估算买入执行和入场成本时使用它", "ignoring ask price when planning buy entries and tight stops", "规划买入和紧止损时忽略卖价", ["ask", "spread"]),
  t("spread", "spread", "点差", "pricing", "the difference between bid and ask price", "买价和卖价之间的差额", "include it in entry, stop, and short-term trade planning", "在入场、止损和短线交易规划中纳入点差", "placing stops so tight that normal spread can trigger them", "把止损设得过紧，以至于正常点差也可能触发", ["spread", "execution"]),
  t("pip", "pip", "点", "pricing", "a standard unit used to measure price movement in many forex pairs", "许多外汇货币对用于衡量价格变动的标准单位", "convert stop distance and target distance into measurable risk", "把止损距离和目标距离转换成可衡量风险", "counting pips without checking the pair's quote format", "不检查报价格式就计算点数", ["pip", "risk"]),
  t("pipette", "pipette", "小数点", "pricing", "a fractional pip used by many brokers for more precise quoting", "许多经纪商用于更精细报价的分数点", "avoid mistaking fractional quote movement for a full pip", "避免把分数点报价变动误认为完整一点", "sizing a trade from pipettes as if they were full pips", "把小数点当成完整点来计算仓位", ["pipette", "pricing"]),
  t("lot_size", "lot size", "手数", "position_sizing", "the trade volume being opened", "开仓的交易量", "match exposure to stop distance and planned money risk", "让风险暴露匹配止损距离和计划资金风险", "choosing lot size from emotion before calculating maximum loss", "在计算最大亏损前凭情绪选择手数", ["lot_size", "risk"]),
  t("standard_lot", "standard lot", "标准手", "position_sizing", "a large contract size commonly equal to 100,000 units in forex", "外汇中通常等于 100,000 单位的大合约规模", "understand pip value before using it on a live account", "实盘使用前先理解每点价值", "using a standard lot because it sounds professional", "因为标准手听起来专业就使用它", ["standard_lot", "risk"]),
  t("mini_lot", "mini lot", "迷你手", "position_sizing", "a smaller contract size commonly equal to 10,000 units in forex", "外汇中通常等于 10,000 单位的较小合约规模", "use it only when the calculated risk fits the account", "只有当计算后的风险适合账户时才使用", "assuming smaller than standard means automatically safe", "认为比标准手小就一定安全", ["mini_lot", "risk"]),
  t("micro_lot", "micro lot", "微型手", "position_sizing", "a small contract size commonly equal to 1,000 units in forex", "外汇中通常等于 1,000 单位的小合约规模", "practice position sizing while keeping risk controlled", "在控制风险的同时练习仓位计算", "opening many micro lots until total risk becomes oversized", "开很多微型手直到总风险过大", ["micro_lot", "risk"]),
  t("leverage", "leverage", "杠杆", "margin", "borrowed exposure that allows smaller margin to control a larger position", "用较少保证金控制较大仓位的借入风险暴露", "remember that both profit and loss scale with exposure", "记住盈利和亏损都会随着风险暴露放大", "using maximum leverage because the margin requirement looks small", "因为保证金要求看起来小就使用最大杠杆", ["leverage", "margin"]),
  t("margin", "margin", "保证金", "margin", "capital held by the broker to support an open leveraged position", "经纪商为支持杠杆持仓而占用的资金", "check required margin before opening or adding positions", "开仓或加仓前检查所需保证金", "confusing required margin with the maximum acceptable loss", "把所需保证金和最大可接受亏损混淆", ["margin", "risk"]),
  t("free_margin", "free margin", "可用保证金", "margin", "equity not currently tied up as required margin", "未被占用为所需保证金的净值部分", "monitor whether the account has room for open-trade fluctuation", "监控账户是否有空间承受持仓波动", "adding exposure simply because some free margin remains", "只因为还有可用保证金就继续加仓", ["free_margin", "drawdown"]),
  t("equity", "equity", "净值", "account_metrics", "balance plus or minus current floating profit and loss", "余额加上或减去当前浮动盈亏", "track live account value while positions are open", "持仓期间跟踪实时账户价值", "judging account health from balance while ignoring floating loss", "忽略浮亏，只用余额判断账户健康", ["equity", "account"]),
  t("balance", "balance", "余额", "account_metrics", "closed account value excluding open floating profit or loss", "不包含未平仓浮动盈亏的已结算账户价值", "compare it with equity to understand open-position impact", "与净值比较以理解持仓影响", "assuming balance shows current risk while trades are still open", "在持仓未平时认为余额代表当前风险", ["balance", "account"]),
  t("floating_pl", "floating profit or loss", "浮动盈亏", "account_metrics", "unrealized gain or loss from open trades", "未平仓交易的未实现盈利或亏损", "avoid treating floating profit as final until the trade is closed", "交易平仓前不要把浮盈当成最终盈利", "increasing size because open profit temporarily looks large", "因为临时浮盈看起来大就加大仓位", ["floating_pl", "risk"]),
  t("swap", "swap", "隔夜利息", "costs", "overnight financing credit or charge for holding a position", "持仓过夜产生的融资收入或费用", "check rollover cost before planning overnight holds", "计划隔夜持仓前检查隔夜成本", "holding overnight without knowing whether swap affects net result", "不知道隔夜利息是否影响净结果就持仓过夜", ["swap", "cost"]),
  t("commission", "commission", "手续费", "costs", "a transaction fee charged by the broker or platform", "经纪商或平台收取的交易费用", "include it when reviewing net performance and breakeven level", "复盘净表现和保本位置时纳入手续费", "evaluating strategy results from gross profit while ignoring fees", "只看毛盈利而忽略费用来评估策略", ["commission", "cost"]),
  t("market_execution", "market execution", "市价执行", "orders", "an order process intended to fill at the best available current price", "按当前可获得最佳价格成交的订单执行方式", "use it only when immediate execution is part of the plan", "只有当计划需要立即执行时才使用", "expecting exact entry price during fast movement or news", "在快速行情或新闻期间期待精确入场价", ["market_execution", "orders"]),
  t("pending_orders", "pending orders", "挂单", "orders", "orders waiting for predefined price conditions before execution", "等待预设价格条件后才执行的订单", "define entry, stop, and target before the order is triggered", "订单触发前先定义入场、止损和目标", "placing a pending order without a cancellation or invalidation rule", "没有取消或失效规则就放置挂单", ["pending_order", "planning"]),
  t("buy_limit", "buy limit", "买入限价单", "orders", "a pending buy order placed below current market price", "低于当前市场价格设置的买入挂单", "use it for planned pullback entries near support or value", "用于支撑或价值区域附近的计划回调入场", "using it above current price when waiting for breakout momentum", "等待突破动能时把它放在现价上方", ["buy_limit", "orders"]),
  t("sell_limit", "sell limit", "卖出限价单", "orders", "a pending sell order placed above current market price", "高于当前市场价格设置的卖出挂单", "use it for planned pullback entries near resistance or supply", "用于阻力或供应区域附近的计划回调入场", "using it below current price when waiting for breakdown momentum", "等待跌破动能时把它放在现价下方", ["sell_limit", "orders"]),
  t("buy_stop", "buy stop", "买入止损单", "orders", "a pending buy order that triggers above current market price", "在当前市场价格上方触发的买入挂单", "use it when the plan requires price to break upward first", "当计划要求价格先向上突破时使用", "using it for a pullback entry below market support", "把它用于市场支撑下方的回调入场", ["buy_stop", "orders"]),
  t("sell_stop", "sell stop", "卖出止损单", "orders", "a pending sell order that triggers below current market price", "在当前市场价格下方触发的卖出挂单", "use it when the plan requires price to break downward first", "当计划要求价格先向下跌破时使用", "using it for a pullback entry above market resistance", "把它用于市场阻力上方的回调入场", ["sell_stop", "orders"]),
  t("stop_loss", "stop loss", "止损", "risk_management", "a planned exit that limits loss when the trade idea is invalidated", "当交易思路失效时限制亏损的计划出场", "place it where the setup logic is proven wrong", "把它放在交易逻辑被证明错误的位置", "moving it farther away because accepting the loss feels uncomfortable", "因为不愿接受亏损就把止损移远", ["stop_loss", "risk"]),
  t("take_profit", "take profit", "止盈", "trade_management", "a planned exit at a target area for profit taking", "在目标区域获利了结的计划出场", "align it with realistic structure, liquidity, or reward-to-risk", "让它匹配现实结构、流动性或风险回报", "moving it randomly farther after price gets close", "价格接近后随意把它移得更远", ["take_profit", "management"]),
  t("risk_reward_ratio", "risk-to-reward ratio", "风险回报比", "risk_management", "the relationship between potential loss and potential reward", "潜在亏损和潜在回报之间的关系", "skip trades where expected reward does not justify planned risk", "当预期回报不足以支持计划风险时跳过交易", "taking a setup only because direction looks right while reward is poor", "只因方向看起来对就交易，即使回报很差", ["risk_reward", "planning"]),
  t("news_trading_basics", "news trading basics", "新闻交易基础", "news", "understanding how scheduled events can change volatility and execution", "理解预定事件如何改变波动和执行", "decide before the event whether the plan allows trading", "事件前先决定计划是否允许交易", "entering simply because a news candle is moving fast", "只因为新闻K线移动很快就入场", ["news", "execution"]),
  t("high_impact_news", "high-impact news", "高影响新闻", "news", "economic releases or policy events that can sharply increase volatility", "可能显著增加波动的经济数据或政策事件", "reduce exposure or stand aside if the plan excludes news risk", "如果计划排除新闻风险，就降低暴露或观望", "assuming high volatility means easier profit", "认为高波动就代表更容易盈利", ["news", "volatility"]),
  t("fundamental_analysis", "fundamental analysis", "基本面分析", "fundamentals", "studying economic data, policy, and macro conditions behind price movement", "研究价格变动背后的经济数据、政策和宏观条件", "use it as context together with technical levels and risk controls", "把它与技术位置和风险控制结合为背景", "treating one headline as a complete trading plan", "把一条新闻标题当成完整交易计划", ["fundamentals", "macro"]),
  t("interest_rates", "interest rates", "利率", "fundamentals", "central-bank policy rates that influence currency value and capital flows", "影响货币价值和资金流向的央行政策利率", "understand why rate expectations can move currencies and gold", "理解利率预期为何能推动货币和黄金", "trading a rate event without considering spread and slippage", "交易利率事件时不考虑点差和滑点", ["rates", "central_bank"]),
  t("inflation", "inflation", "通胀", "fundamentals", "the rate at which prices for goods and services rise", "商品和服务价格上涨的速度", "watch how inflation data can affect rate expectations", "关注通胀数据如何影响利率预期", "assuming every inflation print creates the same market reaction", "认为每次通胀数据都会产生相同市场反应", ["inflation", "macro"]),
  t("nfp", "NFP", "非农就业数据", "fundamentals", "a US employment report that can strongly affect USD and gold volatility", "可能强烈影响美元和黄金波动的美国就业报告", "avoid fresh entries if the plan excludes high-volatility releases", "如果计划排除高波动数据，就避免新开仓", "placing oversized trades minutes before release to catch the spike", "公布前几分钟下重仓试图抓波动", ["nfp", "usd_news"]),
  t("cpi", "CPI", "消费者物价指数", "fundamentals", "an inflation report that can affect rate expectations and market repricing", "可能影响利率预期和市场重新定价的通胀报告", "check event timing before holding tight stops through the release", "公布前检查时间，避免用过紧止损硬扛数据", "assuming CPI removes the need for technical invalidation", "认为 CPI 可以取代技术失效条件", ["cpi", "inflation"]),
  t("fomc", "FOMC", "美联储议息会议", "fundamentals", "the Federal Reserve policy meeting and communication process", "美联储政策会议和沟通过程", "prepare for spread, volatility, and directional uncertainty around the event", "为事件附近的点差、波动和方向不确定性做准备", "entering before the statement without a defined risk plan", "声明前没有明确风险计划就进场", ["fomc", "central_bank"]),
  t("central_banks", "central banks", "央行", "fundamentals", "institutions that set monetary policy and influence currency conditions", "制定货币政策并影响货币环境的机构", "track policy expectations as one part of market context", "把政策预期作为市场背景的一部分跟踪", "believing a central-bank comment guarantees one-way movement", "相信央行一句话能保证单边行情", ["central_bank", "macro"]),
  t("market_sessions", "market sessions", "交易时段", "sessions", "periods such as Asia, London, and New York with different liquidity patterns", "亚洲、伦敦和纽约等具有不同流动性特征的时段", "adapt expectations because volatility and spreads can change by session", "根据时段变化调整对波动和点差的预期", "using the same scalping rules in every session without review", "不复盘就每个时段都使用相同剥头皮规则", ["sessions", "liquidity"]),
  t("liquidity", "liquidity", "流动性", "market_conditions", "how easily orders can be filled without large price disruption", "订单能否在不大幅扰动价格的情况下成交", "prefer cleaner execution conditions and respect thin-market risk", "优先选择更清晰的执行环境，并尊重低流动性风险", "ignoring low liquidity because the chart pattern looks clean", "因为图形看起来干净就忽略低流动性", ["liquidity", "execution"]),
  t("slippage", "slippage", "滑点", "execution", "the difference between expected order price and actual fill price", "预期成交价和实际成交价之间的差异", "plan for it during news, gaps, and fast market movement", "在新闻、跳空和快速行情中预先考虑它", "assuming stop loss always fills at the exact displayed price", "认为止损总会按屏幕显示价格精确成交", ["slippage", "execution"]),
  t("broker_basics", "broker basics", "经纪商基础", "broker", "understanding the firm, pricing, execution, and account conditions used for trading", "理解用于交易的公司、报价、执行和账户条件", "review costs, rules, and platform behavior before relying on execution", "依赖执行前复查成本、规则和平台行为", "choosing a broker only because someone shared a promotion", "只因为别人分享推广就选择经纪商", ["broker", "platform"]),
  t("cfd_basics", "CFD basics", "差价合约基础", "broker", "contracts that track price differences without owning the underlying asset", "追踪价格差额但不拥有标的资产的合约", "understand contract size, margin, and financing before trading", "交易前理解合约规模、保证金和融资成本", "assuming a CFD position means owning the underlying asset", "认为 CFD 持仓代表拥有标的资产", ["cfd", "contract"]),
  t("trading_platform", "trading platform basics", "交易平台基础", "platform", "the software used to place, manage, and review trades", "用于下单、管理和复盘交易的软件", "practice order entry, modification, and closure safely before live execution", "实盘前安全练习下单、修改和平仓", "learning platform buttons for the first time during a fast live setup", "在快速实盘形态中才第一次学习平台按钮", ["platform", "execution"]),
  t("account_currency", "account currency", "账户货币", "account_metrics", "the currency used to report account balance, equity, and profit or loss", "用于显示账户余额、净值和盈亏的货币", "convert risk correctly when the instrument is quoted in another currency", "当品种以另一种货币报价时正确换算风险", "assuming every pip value is identical across all account currencies", "认为所有账户货币下每点价值都一样", ["account_currency", "pip_value"]),
  t("pip_value", "pip value", "每点价值", "position_sizing", "the money value of one pip of movement for a specific position size", "特定仓位下价格每移动一点对应的资金价值", "calculate the potential money loss from entry to stop", "计算从入场到止损的潜在资金亏损", "assuming pip value stays the same for every symbol and lot size", "认为每个品种和手数的每点价值都一样", ["pip_value", "position_sizing"]),
  t("drawdown", "drawdown", "回撤", "risk_management", "a decline from an account high to a lower equity or balance level", "账户从高点下降到较低净值或余额的幅度", "reduce risk or pause when drawdown reaches the written limit", "当回撤达到书面限制时降低风险或暂停", "increasing lot size because the account is already in drawdown", "因为账户已经回撤就加大手数", ["drawdown", "discipline"]),
  t("break_even", "breakeven", "保本", "trade_management", "moving or closing near entry so remaining loss risk is limited or zero", "移动止损或在入场附近出场，使剩余亏损风险有限或为零", "use it only when the trade has earned protection according to the plan", "只有当交易按计划值得保护时才使用", "moving to breakeven immediately so normal noise stops the trade", "一进场就保本，让正常噪音扫出交易", ["breakeven", "management"]),
];

const technicalTopics = [
  tech("support_resistance", "support and resistance", "支撑与阻力", "snr_zones", "areas where price has previously reacted with buying or selling interest", "价格曾因买盘或卖盘兴趣而发生反应的区域", "mark zones, wait for reaction, and define invalidation", "标记区域，等待反应，并定义失效条件", "a clean close through the planned zone with acceptance", "价格干净收在计划区域外并被市场接受", "reaction at the zone plus structure and risk alignment", "区域反应加上结构与风险一致", "when price is in the middle of a messy range", "当价格处于混乱区间中部时"),
  tech("snr_zones", "SNR zones", "支撑阻力区", "snr_zones", "support and resistance areas treated as zones rather than single ticks", "把支撑阻力当作区域而非单一价位", "allow for wick noise, spread, and retest variation", "允许影线噪音、点差和回测偏差", "price repeatedly accepts beyond the zone instead of rejecting it", "价格反复接受在区域外而不是拒绝", "body close and rejection behaviour around the zone", "区域附近的实体收盘和拒绝行为", "when the zone has no recent reaction or context", "当区域没有近期反应或背景时"),
  tech("key_levels", "key levels", "关键价位", "snr_zones", "important market prices where decisions or liquidity are likely concentrated", "市场决策或流动性可能集中的重要价格", "plan entries and exits around them without assuming they always hold", "围绕它们规划进出场，但不假设一定守住", "the level fails to attract the expected reaction", "价位未能吸引预期反应", "confluence with higher timeframe structure and candle close", "与高周期结构和K线收盘形成共振", "when nearby levels conflict and the bias is unclear", "附近价位互相冲突且偏向不清时"),
  tech("wick_rejection", "wick rejection", "影线拒绝", "price_action", "a candle wick showing price failed to hold beyond an area", "K线影线显示价格未能站稳某个区域外", "read it only at meaningful levels or liquidity areas", "只在有意义价位或流动性区域解读", "a later body close accepts beyond the rejected area", "后续实体收盘接受在被拒绝区域之外", "long wick with close back inside a planned level", "长影线且收回计划价位内", "when the wick appears in random chart noise", "影线出现在随机图表噪音中时"),
  tech("body_close", "body close", "实体收盘", "price_action", "where the candle body finishes relative to a level or zone", "K线实体相对价位或区域的收盘位置", "use close quality to reduce false-breakout decisions", "利用收盘质量减少假突破判断", "the body closes against the planned setup logic", "实体收盘与计划交易逻辑相反", "clear close plus follow-through or retest behaviour", "清晰收盘加后续延续或回测行为", "when the candle has not closed and the plan requires confirmation", "K线尚未收盘且计划要求确认时"),
  tech("trendline", "trendline", "趋势线", "trend_tools", "a line connecting meaningful swing highs or lows", "连接有意义摆动高点或低点的线", "combine it with structure, zones, and reaction quality", "结合结构、区域和反应质量使用", "price closes through it and the structure also shifts", "价格收破趋势线且结构也发生转变", "third-touch reaction with supportive market structure", "第三次触碰反应并有结构支持", "when it is forced through random candles", "趋势线被强行画过随机K线时"),
  tech("channel", "channel", "通道", "trend_tools", "parallel boundaries containing trending or corrective price movement", "包含趋势或修正走势的平行边界", "watch reactions at channel edges and avoid entries in the middle", "观察通道边缘反应，避免在中部入场", "price accepts outside the channel and fails to retest", "价格接受在通道外且回测失败", "edge reaction aligned with broader trend and risk plan", "边缘反应与大级别趋势和风险计划一致", "when the channel is too subjective or constantly redrawn", "通道过于主观或不断重画时"),
  tech("market_structure", "market structure", "市场结构", "market_structure", "the sequence of swings that defines trend, range, or transition", "定义趋势、区间或转换的摆动序列", "read higher highs, higher lows, lower highs, and lower lows before entry", "入场前解读更高高点、更高低点、更低高点和更低低点", "a protected swing breaks and price accepts beyond it", "受保护摆动点被突破且价格接受在其外", "clear swing sequence plus candle close confirmation", "清晰摆动序列加K线收盘确认", "when swings are overlapping and direction is unclear", "摆动互相重叠且方向不清时"),
  tech("higher_high", "higher high", "更高高点", "market_structure", "a swing high above the previous meaningful high", "高于前一个有意义高点的摆动高点", "confirm bullish progression only with supporting higher lows", "只有在更高低点支持下确认多头推进", "price fails to hold above the breakout and returns below structure", "价格未能守住突破并回到结构下方", "break above prior high followed by acceptance", "突破前高后被市场接受", "when it occurs directly into major resistance without confirmation", "它直接出现在主要阻力且无确认时"),
  tech("higher_low", "higher low", "更高低点", "market_structure", "a pullback low above the previous meaningful low", "高于前一个有意义低点的回调低点", "use it as a bullish invalidation reference when context supports", "在背景支持时把它作为多头失效参考", "price breaks and accepts below that higher-low zone", "价格跌破并接受在更高低点区域下方", "reaction from the zone with renewed buying pressure", "区域反应并出现新的买盘压力", "when the higher timeframe bias is strongly bearish", "高周期偏向强烈看空时"),
  tech("lower_high", "lower high", "更低高点", "market_structure", "a rally high below the previous meaningful high", "低于前一个有意义高点的反弹高点", "use it as bearish structure only when lower lows also support", "只有更低低点也支持时才作为空头结构", "price breaks above and accepts beyond the lower high", "价格突破并接受在更低高点上方", "failed rally at resistance with bearish close", "阻力位反弹失败并空头收盘", "when broader structure still prints higher highs", "大结构仍在形成更高高点时"),
  tech("lower_low", "lower low", "更低低点", "market_structure", "a swing low below the previous meaningful low", "低于前一个有意义低点的摆动低点", "confirm bearish progression only with supporting lower highs", "只有在更低高点支持下确认空头推进", "price reclaims the broken low and breaks the protected high", "价格收复被破低点并突破受保护高点", "close below prior low plus retest failure", "收在前低下方加回测失败", "when the move is only a thin-liquidity wick", "走势只是低流动性影线时"),
  tech("bos", "BOS", "结构突破", "market_structure", "a break of structure in the direction of the current trend", "顺当前趋势方向的结构突破", "wait for a meaningful close and plan the retest or continuation risk", "等待有意义收盘，并规划回测或延续风险", "price returns through the broken structure and accepts against the break", "价格回到被破结构内并接受在突破反方向", "body close beyond a protected swing with follow-through", "实体收在受保护摆动点外并有延续", "when it is only a wick through an obvious level", "它只是刺破明显价位的影线时"),
  tech("choch", "CHOCH", "结构转变", "market_structure", "a change of character that warns momentum may be shifting", "提示动能可能转变的结构变化", "treat it as an early warning that still needs confirmation", "把它视为仍需确认的早期警讯", "the market immediately rebuilds the prior trend structure", "市场立即重建原有趋势结构", "break of internal structure near a key level", "关键价位附近的内部结构突破", "when one countertrend candle is the only evidence", "唯一证据只是一根逆势K线时"),
  tech("trend_continuation", "trend continuation", "趋势延续", "market_structure", "price action that supports the existing directional bias", "支持现有方向偏向的价格行为", "look for pullbacks, retests, and risk areas aligned with the trend", "寻找与趋势一致的回调、回测和风险区域", "the protected trend swing breaks with acceptance", "受保护趋势摆动点被接受性突破", "higher timeframe bias plus lower timeframe trigger", "高周期偏向加低周期触发", "when price is overextended into opposing liquidity", "价格过度延伸进入对向流动性时"),
  tech("trend_reversal", "trend reversal", "趋势反转", "market_structure", "evidence that a prior directional move may be changing direction", "原有方向走势可能改变方向的证据", "require stronger proof than a single candle against the trend", "需要比单根逆势K线更强的证据", "price fails to break the protected swing and resumes the trend", "价格未能突破受保护摆动点并恢复趋势", "liquidity sweep, structure shift, and retest confirmation", "流动性扫荡、结构转变和回测确认", "when reversal is based only on hoping price has moved enough", "反转依据只是希望价格已经走够时"),
  tech("pullback", "pullback", "回调", "entry_model", "a temporary move against the current trend or impulse", "与当前趋势或推动方向相反的临时走势", "wait for a planned zone and confirmation before entry", "等待计划区域和确认后再入场", "the pullback breaks the structure that should hold", "回调打破了本应守住的结构", "reaction from value area in line with higher timeframe bias", "在顺高周期偏向的价值区域反应", "when it becomes a full structure break", "当它变成完整结构突破时"),
  tech("breakout", "breakout", "突破", "entry_model", "price moving and closing beyond a meaningful range or level", "价格移动并收在有意义区间或价位之外", "check close quality, follow-through, and retest risk", "检查收盘质量、延续和回测风险", "price quickly returns inside the range and accepts there", "价格快速回到区间内并接受在那里", "body close outside the level with expanding participation", "实体收在价位外并参与度扩大", "when spread is wide and the candle is already exhausted", "点差很宽且K线已经衰竭时"),
  tech("breakout_retest", "breakout retest", "突破回测", "entry_model", "price returning to test a level after breaking beyond it", "价格突破后返回测试该价位", "look for former resistance acting as support or support as resistance", "观察前阻力转支撑或前支撑转阻力", "the retest fails and price accepts back inside the old range", "回测失败且价格接受回旧区间内", "retest rejection with risk placed beyond invalidation", "回测拒绝且风险放在失效位之外", "when price never retests and entry would require chasing", "价格没有回测而入场需要追单时"),
  tech("false_breakout", "false breakout", "假突破", "entry_model", "a breakout attempt that fails and returns inside the prior range", "突破尝试失败并回到原区间内", "avoid chasing the first break and wait for acceptance or reclaim", "避免追第一下突破，等待接受或收复", "price later closes and holds outside the range", "价格随后收在并守住区间外", "failed break plus strong close back inside the range", "突破失败加有力收回区间内", "when volume and close quality confirm a valid breakout", "成交量和收盘质量确认有效突破时"),
  tech("liquidity_sweep", "liquidity sweep", "流动性扫荡", "liquidity", "a move through obvious highs or lows before returning", "刺穿明显高点或低点后返回的走势", "wait for reclaim and confirmation instead of entering on the spike", "等待收复和确认，而不是在尖峰上入场", "price continues accepting beyond the swept level", "价格继续接受在被扫价位之外", "wick through liquidity followed by close back inside", "刺穿流动性的影线后收回内部", "when no obvious liquidity pool exists", "不存在明显流动性池时"),
  tech("equal_highs_lows", "equal highs and equal lows", "等高与等低", "liquidity", "similar swing points where stops and breakout orders may cluster", "相近摆动点，止损和突破单可能集中", "treat them as possible liquidity, not automatic entry signals", "把它们视为潜在流动性，而非自动入场信号", "price accepts beyond them without rejection", "价格无拒绝地接受在其外", "sweep and reclaim near a planned zone", "计划区域附近的扫荡与收复", "when they are too minor to matter on the chosen timeframe", "在所选周期上过于细小而不重要时"),
  tech("supply_demand", "supply and demand", "供应与需求", "zones", "areas where selling or buying pressure previously caused displacement", "过去卖压或买压造成位移的区域", "combine zones with structure and confirmation before entry", "入场前把区域与结构和确认结合", "price trades through the zone with no reaction", "价格穿过区域而没有反应", "fresh zone reaction with supportive order flow", "新鲜区域反应并有订单流支持", "when every old candle is marked as a zone", "把每根旧K线都标成区域时"),
  tech("order_block", "order block", "订单块", "zones", "a prior candle zone associated with displacement or institutional activity", "与位移或机构活动相关的先前K线区域", "use it as context that still requires confirmation and invalidation", "把它作为仍需确认和失效条件的背景", "the zone is fully traded through and no longer reacts", "区域被完全穿过且不再反应", "reaction from a fresh block aligned with structure", "与结构一致的新鲜订单块反应", "when the label is used to justify any entry", "标签被用来为任何入场找理由时"),
  tech("mitigated_order_block", "mitigated order block", "已缓解订单块", "zones", "an order-block area already revisited by price", "价格已经回访过的订单块区域", "reduce confidence after repeated mitigation or weak reaction", "多次缓解或反应弱时降低信心", "price has consumed the zone and accepts beyond it", "价格已经消耗该区域并接受在其外", "first mitigation with clear rejection and structure support", "首次缓解且有清晰拒绝和结构支持", "when the zone has been tapped many times", "区域已被多次触碰时"),
  tech("fair_value_gap", "fair value gap", "公允价值缺口", "imbalance", "an imbalance left by displacement where price moved inefficiently", "位移留下的价格非有效移动不平衡", "use it only when aligned with bias, structure, and risk", "只有与偏向、结构和风险一致时才使用", "price fills the gap and accepts beyond the setup level", "价格填补缺口并接受在设定位之外", "reaction from gap in line with higher timeframe bias", "顺高周期偏向的缺口反应", "when every small candle gap is treated as high probability", "把每个小K线缺口都当成高概率时"),
  tech("imbalance", "imbalance", "不平衡", "imbalance", "aggressive price movement showing one side temporarily dominated", "一方暂时主导的强势价格移动", "look for whether price later respects or fills the area", "观察价格之后是否尊重或填补该区域", "price fills and rejects the original directional idea", "价格填补并否定原方向思路", "displacement with structure break and clean delivery", "位移伴随结构突破和清晰推进", "when imbalance is isolated from context", "不平衡脱离背景时"),
  tech("poi", "POI", "兴趣点", "poi", "a point of interest where a trader expects a meaningful reaction", "交易者预期会发生重要反应的兴趣点", "define why the area matters before planning an entry", "规划入场前说明该区域为何重要", "price reaches the area and shows no planned reaction", "价格到达区域却没有计划内反应", "reaction at POI with confluence and defined risk", "POI 反应加共振和明确风险", "when POI is chosen after the trade has already moved", "交易已经走出后才选择 POI 时"),
  tech("multi_timeframe", "multi-timeframe analysis", "多周期分析", "timeframes", "using higher and lower timeframes for context and execution timing", "使用高低周期分别处理背景和执行时机", "separate higher timeframe bias from lower timeframe trigger", "区分高周期偏向和低周期触发", "the lower timeframe trigger breaks against the higher timeframe plan", "低周期触发打破高周期计划", "HTF direction, LTF structure, and risk all align", "高周期方向、低周期结构和风险一致", "when timeframes conflict and no priority rule exists", "周期冲突且没有优先规则时"),
  tech("htf_bias", "higher timeframe bias", "高周期偏向", "timeframes", "the broader directional context from a larger chart period", "来自更大图表周期的整体方向背景", "avoid fighting it without strong reversal evidence", "没有强反转证据时避免逆它交易", "major structure on the higher timeframe changes", "高周期主要结构发生改变", "HTF structure supports the planned LTF entry direction", "高周期结构支持计划中的低周期入场方向", "when the bias is unclear or trapped between major levels", "偏向不清或夹在主要价位之间时"),
  tech("ltf_entry", "lower timeframe entry", "低周期入场", "timeframes", "using a smaller timeframe to time entry after broader context is set", "在大背景确定后用较小周期把握入场时机", "use it for triggers, not for overriding the whole market bias", "把它用于触发，而不是推翻整体市场偏向", "the LTF entry breaks structure against the trade immediately", "低周期入场立即打破交易方向结构", "clear trigger at planned HTF zone", "计划高周期区域的清晰触发", "when lower timeframe noise is the only reason to trade", "低周期噪音是唯一交易理由时"),
  tech("m15_structure", "M15 entry structure", "M15 入场结构", "timeframes", "lower timeframe swings used for precise execution timing", "用于精确执行时机的低周期摆动", "wait for trigger and invalidation at the planned zone", "在计划区域等待触发和失效条件", "M15 breaks the entry swing against the setup", "M15 打破入场摆动并反向于设定", "M15 shift in line with H1 or H4 context", "M15 转变与 H1 或 H4 背景一致", "when H1 and H4 context are unresolved", "H1 和 H4 背景未解决时"),
  tech("h1_structure", "H1 internal structure", "H1 内部结构", "timeframes", "medium timeframe swings that connect entry timing with higher context", "连接入场时机与高周期背景的中周期摆动", "map pullbacks and continuation before selecting M15 triggers", "选择 M15 触发前标记回调和延续", "H1 breaks the swing that supported the trade idea", "H1 打破支持交易思路的摆动点", "H1 pullback holds inside H4 bias", "H1 回调守在 H4 偏向内", "when H1 structure is chopping sideways", "H1 结构横向杂乱时"),
  tech("h4_structure", "H4 structure logic", "H4 结构逻辑", "timeframes", "higher timeframe swing logic used for broader bias", "用于整体偏向的高周期摆动逻辑", "define whether the market is trending, ranging, or transitioning", "定义市场是趋势、区间还是转换", "H4 breaks the protected swing behind the bias", "H4 打破偏向背后的受保护摆动点", "H4 bias aligns with lower timeframe confirmation", "H4 偏向与低周期确认一致", "when H4 is inside compression with no clean level", "H4 处于压缩且无清晰价位时"),
  tech("ema", "EMA", "EMA 指数移动平均线", "indicators", "a moving average that weights recent prices more strongly", "更重视近期价格的移动平均线", "read slope and location as context, not a standalone signal", "把斜率和位置作为背景，而非独立信号", "price structure contradicts the EMA-based idea", "价格结构否定基于 EMA 的思路", "EMA alignment with structure and clean pullback reaction", "EMA 与结构一致并有清晰回调反应", "when price is tangled around flat EMAs", "价格缠绕在走平 EMA 周围时"),
  tech("ema_24_52", "EMA 24 and EMA 52", "EMA 24 与 EMA 52", "indicators", "shorter and medium EMAs often used to read near-term trend rhythm", "常用于读取短中期趋势节奏的均线", "compare their slope and relationship with structure", "比较它们的斜率及其与结构的关系", "both EMAs flatten and price breaks the planned structure", "两条均线走平且价格打破计划结构", "clean separation with pullback holding the average area", "均线清晰分离且回调守住均线区域", "when crossover signals conflict with major support or resistance", "交叉信号与主要支撑阻力冲突时"),
  tech("ema_100_200", "EMA 100 and EMA 200", "EMA 100 与 EMA 200", "indicators", "longer EMAs often used to frame broader trend context", "常用于界定整体趋势背景的较长期均线", "use them as context while still respecting price structure", "把它们作为背景，同时仍尊重价格结构", "major structure shifts against the EMA bias", "主要结构转向并反对 EMA 偏向", "price respects the long EMA area with confirming structure", "价格尊重长期 EMA 区域并有结构确认", "when the market ranges through the averages repeatedly", "市场反复穿越均线区间时"),
  tech("rsi", "RSI", "RSI 相对强弱指标", "indicators", "a momentum oscillator used for overbought, oversold, and divergence context", "用于超买、超卖和背离背景的动能震荡指标", "combine it with level, structure, and candle confirmation", "与价位、结构和K线确认结合", "price structure continues strongly despite the oscillator signal", "尽管指标有信号，价格结构仍强势延续", "RSI signal appears at a meaningful level with structure shift", "RSI 信号出现在有意义价位并伴随结构转变", "when RSI alone is the only reason for entry", "RSI 是唯一入场理由时"),
  tech("macd", "MACD", "MACD 指标", "indicators", "a momentum tool comparing moving averages and signal line changes", "比较移动平均线与信号线变化的动能工具", "use it to support, not replace, structure analysis", "用它支持而不是取代结构分析", "price action invalidates the momentum reading", "价格行为使动能解读失效", "MACD momentum agrees with breakout and retest evidence", "MACD 动能与突破和回测证据一致", "when indicator signals lag after price is extended", "指标在价格延伸后滞后发出信号时"),
  tech("volume", "volume", "成交量", "indicators", "the amount of trading activity recorded for a market or instrument", "市场或品种记录到的交易活动量", "look for whether participation supports breakout or rejection", "观察参与度是否支持突破或拒绝", "price moves against the idea while participation expands", "价格反向于思路且参与度扩大", "volume expansion with clean close at the planned level", "计划价位处成交量扩大并清晰收盘", "when volume data is unreliable for that instrument", "该品种成交量数据不可靠时"),
  tech("volume_profile", "volume profile", "成交量分布", "indicators", "volume shown by price area rather than only by time", "按价格区域而非仅按时间显示的成交量", "identify value areas, low-volume zones, and reaction points", "识别价值区域、低量区域和反应点", "price accepts beyond the value area against the plan", "价格接受在价值区域外并反对计划", "reaction around high-volume or low-volume area with structure support", "高量或低量区域反应并有结构支持", "when profile levels are used without current price action", "分布价位脱离当前价格行为使用时"),
  tech("candlestick_confirmation", "candlestick confirmation", "K线确认", "price_action", "a candle close or pattern that supports the planned setup", "支持计划设定的K线收盘或形态", "wait for close if the trading plan requires it", "如果交易计划要求，就等待收盘", "the candle closes against the setup direction", "K线收在设定方向反面", "confirmation appears at a planned level with valid risk", "确认出现在计划价位且风险有效", "when entering before the candle is complete would break rules", "收盘前入场会违反规则时"),
  tech("pin_bar", "pin bar", "Pin Bar", "candles", "a candle with a long rejection wick and smaller body", "具有长拒绝影线和较小实体的K线", "read it at meaningful levels, not randomly in the chart", "在有意义价位解读，而不是在图表任意位置", "price closes beyond the wick direction and cancels rejection", "价格收向影线方向之外并取消拒绝", "pin bar rejects a planned zone with structure alignment", "Pin Bar 拒绝计划区域并与结构一致", "when it forms in the middle of a noisy range", "它形成在噪音区间中部时"),
  tech("engulfing_candle", "engulfing candle", "吞没K线", "candles", "a candle body that covers the previous candle body", "实体覆盖前一根K线实体的K线", "confirm whether it appears at a meaningful level and with context", "确认它是否出现在有意义价位并有背景", "follow-through fails and price closes back through the setup level", "延续失败且价格收回设定位内", "engulfing close from a key zone with clear invalidation", "关键区域吞没收盘且失效条件清晰", "when it appears after an already overextended move", "它出现在已经过度延伸的走势后时"),
  tech("inside_bar", "inside bar", "内包K线", "candles", "a candle contained within the high and low of the previous candle", "高低点都包含在前一根K线范围内的K线", "treat it as compression that needs breakout context", "把它视为需要突破背景的压缩", "breakout from the inside range fails and reverses", "内包区间突破失败并反转", "inside bar break aligns with trend and key level", "内包突破与趋势和关键价位一致", "when there is no directional bias or range boundary", "没有方向偏向或区间边界时"),
  tech("doji", "Doji", "十字星", "candles", "a candle where open and close are close together, showing indecision", "开盘与收盘接近、显示犹豫的K线", "use it as context only, especially near important levels", "只把它作为背景，尤其是在重要价位附近", "the next candle accepts beyond the planned invalidation level", "下一根K线接受在计划失效位之外", "doji at key level followed by confirmation close", "关键价位十字星后出现确认收盘", "when a doji alone is used as an entry trigger", "单独用十字星作为入场触发时"),
  tech("ranging_market", "ranging market condition", "震荡市场状态", "market_conditions", "price rotating between boundaries without clear trend continuation", "价格在边界之间轮动且没有清晰趋势延续", "trade only if the plan has range rules or stand aside", "只有计划有区间规则才交易，否则观望", "price breaks and accepts outside the range", "价格突破并接受在区间外", "clear boundary reactions and defined no-trade middle area", "边界反应清晰且定义了中部不交易区", "when the plan is designed only for trending markets", "计划只适合趋势市场时"),
  tech("consolidation", "consolidation", "盘整", "market_conditions", "a period of compressed price movement before expansion or continuation", "扩张或延续前的价格压缩阶段", "wait for clear break, retest, or boundary rejection", "等待清晰突破、回测或边界拒绝", "price expands against the planned direction with acceptance", "价格向计划反方向扩张并被接受", "compression resolves at a planned level with follow-through", "压缩在计划价位解决并有延续", "when the middle of consolidation offers poor reward-to-risk", "盘整中部风险回报差时"),
  tech("adx", "ADX concept", "ADX 概念", "indicators", "a tool used to estimate trend strength rather than direction alone", "用于估计趋势强度而不仅是方向的工具", "use it to decide whether trend or range tactics fit better", "用它判断趋势或区间策略哪种更适合", "price structure contradicts the trend-strength assumption", "价格结构否定趋势强度假设", "ADX context agrees with structure and volatility behaviour", "ADX 背景与结构和波动行为一致", "when the reading is used without price-action confirmation", "读数脱离价格行为确认使用时"),
  tech("market_analysis_process", "market analysis process", "市场分析流程", "analysis_process", "a structured sequence for reading context, levels, bias, and risk", "按顺序解读背景、价位、偏向和风险的结构化流程", "start from higher timeframe context before choosing entries", "选择入场前先从高周期背景开始", "new information breaks the original bias or invalidation", "新信息打破原偏向或失效条件", "bias, level, trigger, and risk all connect logically", "偏向、价位、触发和风险逻辑相连", "when analysis is changed only to justify a desired trade", "分析只是为了证明想做的交易时"),
  tech("trading_plan", "trading plan creation", "交易计划制定", "analysis_process", "writing rules for setup, entry, invalidation, risk, and management", "写下设定、入场、失效、风险和管理规则", "define decisions before price creates emotional pressure", "在价格制造情绪压力前定义决策", "the market reaches conditions excluded by the plan", "市场进入计划排除的条件", "written trigger, stop, target, and no-trade rule are present", "具备书面触发、止损、目标和不交易规则", "when the plan is vague enough to allow any trade", "计划模糊到允许任何交易时"),
  tech("unclear_bias", "unclear bias avoidance", "偏向不清时避免交易", "analysis_process", "standing aside when evidence does not support a clean direction", "证据不支持清晰方向时选择观望", "accept no-trade conditions instead of forcing a setup", "接受不交易条件，而不是强行寻找设定", "new confluence creates a defined directional plan", "新的共振形成明确方向计划", "multiple timeframes and key levels stop conflicting", "多周期和关键价位不再冲突", "when direction is guessed because a trader wants action", "因为想交易而猜方向时"),
  tech("range_handling", "handling a ranging market", "处理震荡市场", "analysis_process", "adapting execution when price rotates instead of trends", "当价格轮动而非趋势时调整执行", "use boundary rules, smaller expectations, or no-trade filters", "使用边界规则、降低预期或不交易过滤", "range boundary breaks and accepts into trend conditions", "区间边界突破并接受进入趋势条件", "well-defined boundaries with reaction confirmation", "边界清晰且有反应确认", "when the middle offers no clear edge", "区间中部没有清晰优势时"),
  tech("confluence", "confluence validation", "共振验证", "analysis_process", "multiple independent reasons supporting the same trade idea", "多个独立理由支持同一交易思路", "require evidence that adds quality rather than duplicate labels", "要求证据真正提升质量，而不是重复标签", "one major factor invalidates the plan despite minor confirmations", "一个主要因素否定计划，即使有小确认", "structure, level, timing, and risk support the same idea", "结构、价位、时机和风险支持同一思路", "when confluence is just many indicators saying the same thing", "共振只是许多指标重复同一信息时"),
  tech("trade_invalidation", "trade idea invalidation", "交易思路失效", "analysis_process", "the condition proving the original setup logic is wrong", "证明原始设定逻辑错误的条件", "define it before entry and respect it after entry", "入场前定义它，入场后尊重它", "invalidation is reached or price accepts beyond it", "达到失效条件或价格接受在其外", "specific level or structure break written in the plan", "计划中写明的具体价位或结构突破", "when invalidation keeps moving after entry", "入场后失效条件不断移动时"),
  tech("entry_confirmation", "entry confirmation", "入场确认", "execution", "the final evidence required before executing a planned setup", "执行计划设定前需要的最后证据", "wait for the planned trigger instead of reacting early", "等待计划触发，而不是提前反应", "confirmation fails or closes against the trade idea", "确认失败或收盘反对交易思路", "trigger appears at planned area with valid risk", "触发出现在计划区域且风险有效", "when confirmation is not part of the written plan", "确认不在书面计划中时"),
  tech("stop_loss_placement", "stop-loss placement", "止损设置", "risk_management", "placing risk beyond the point where the setup is invalid", "把风险放在设定失效点之外", "base it on structure and volatility, not fear or hope", "基于结构和波动，而不是恐惧或希望", "price reaches the level that proves the idea wrong", "价格到达证明思路错误的位置", "stop sits beyond protected structure with acceptable risk", "止损在受保护结构之外且风险可接受", "when the stop is chosen only to fit a desired lot size", "止损只是为了配合想要的手数时"),
  tech("take_profit_planning", "take-profit planning", "止盈规划", "trade_management", "choosing realistic exit areas before or during trade management", "在交易前或管理中选择现实出场区域", "align targets with liquidity, structure, and reward-to-risk", "让目标匹配流动性、结构和风险回报", "opposing structure appears before target and plan says to manage", "目标前出现对向结构且计划要求管理", "target sits near next liquidity or opposing zone", "目标位于下一流动性或对向区域附近", "when the target is moved from greed after entry", "入场后因贪婪移动目标时"),
  tech("trade_management", "trade management", "交易管理", "trade_management", "the planned actions after entry, including partials, breakeven, and exits", "入场后的计划动作，包括部分平仓、保本和出场", "follow predefined rules instead of reacting to every candle", "遵循预设规则，而不是对每根K线反应", "management rules are broken or trade reason disappears", "管理规则被破坏或交易理由消失", "price reaches planned management zone with structure support", "价格到达计划管理区域且结构支持", "when decisions are made from fear after entry", "入场后凭恐惧做决定时"),
];

const psychologyTopics = [
  psy("fomo", "FOMO", "错失恐惧", "fear of missing a move that pushes traders into late entries", "担心错过行情而推动交易者追在后面入场", "pause and wait for a fresh planned setup", "暂停并等待新的计划内设定", "late entry after missing the original trigger", "错过原触发后追在后面入场", "maximum distance from planned entry and no-chase rule", "距离计划入场过远和不追单规则"),
  psy("revenge_trading", "revenge trading", "报复性交易", "trading mainly to recover a recent loss emotionally", "主要为了情绪化追回近期亏损而交易", "stop, review, and keep risk fixed before considering another trade", "停止、复盘，并在考虑下一笔前保持风险固定", "larger or faster trades after a loss", "亏损后更大或更快地交易", "cooldown rule after losses and fixed risk per trade", "亏损后的冷静规则和固定单笔风险"),
  psy("overtrading", "overtrading", "过度交易", "taking too many trades beyond the plan or market quality", "超出计划或市场质量地交易过多", "reduce activity and require full setup criteria", "减少交易活动并要求完整设定条件", "many marginal entries with weak reasons", "大量理由薄弱的边缘入场", "daily trade limit and quality checklist", "每日交易次数限制和质量清单"),
  psy("gambling_behavior", "gambling behavior", "赌博式行为", "risking money from excitement or hope instead of a tested process", "因刺激或希望而冒险，而不是依据经过检验的流程", "return to written rules or stop trading", "回到书面规则或停止交易", "all-or-nothing decisions and oversized risk", "孤注一掷和超大风险", "maximum risk limit and no-all-in rule", "最大风险限制和禁止梭哈规则"),
  psy("fear_after_losses", "fear after losses", "亏损后的恐惧", "hesitation or panic caused by recent losing outcomes", "近期亏损结果导致的犹豫或恐慌", "separate valid setup quality from emotional memory", "把有效设定质量与情绪记忆区分开", "skipping valid planned setups only from fear", "只因害怕而跳过有效计划设定", "post-loss review and smaller controlled execution if allowed", "亏损后复盘，并在允许时较小受控执行"),
  psy("greed_after_wins", "greed after wins", "盈利后的贪婪", "increasing expectations or risk after profitable outcomes", "盈利后提高预期或风险", "keep the same criteria and protect consistency", "保持相同标准并保护一致性", "doubling size because the last trade won", "因为上一笔盈利就加倍手数", "fixed sizing rule after wins", "盈利后的固定仓位规则"),
  psy("discipline", "discipline", "纪律", "executing the plan even when emotions suggest shortcuts", "即使情绪想走捷径也执行计划", "measure success by rule compliance as well as results", "用规则遵守度和结果共同衡量成功", "breaking rules after price starts moving quickly", "价格快速移动后破坏规则", "pre-trade checklist and rule-based review", "交易前清单和基于规则的复盘"),
  psy("patience", "patience", "耐心", "waiting for valid conditions instead of forcing trades", "等待有效条件，而不是强行交易", "accept no-trade periods as part of the process", "接受无交易时期是流程的一部分", "entering because the chart feels boring", "因为图表无聊而入场", "minimum setup criteria and no-trade acceptance", "最低设定标准和接受不交易"),
  psy("waiting_for_setup", "waiting for setup", "等待设定", "allowing price to reach planned conditions before acting", "让价格到达计划条件后再行动", "prepare levels in advance and execute only on trigger", "提前准备价位，只在触发时执行", "entering before the market reaches the planned area", "市场到达计划区域前就入场", "trigger checklist and alert-based execution", "触发清单和提醒式执行"),
  psy("following_plan", "following a trading plan", "遵守交易计划", "using written rules for entry, risk, and management", "用书面规则处理入场、风险和管理", "follow the plan unless a predefined invalidation appears", "除非预设失效出现，否则遵守计划", "changing rules during an open trade from emotion", "持仓中因情绪改变规则", "written plan signed before the session", "交易时段前确认书面计划"),
  psy("risk_management", "risk management", "风险管理", "controlling how much capital can be lost on an idea", "控制一个交易思路可能亏损的资金", "define loss first and size the position from that amount", "先定义亏损，再根据该金额计算仓位", "choosing profit target before knowing maximum loss", "先选盈利目标却不知道最大亏损", "fixed risk percentage and stop-based sizing", "固定风险百分比和基于止损的仓位计算"),
  psy("position_sizing_discipline", "position sizing discipline", "仓位纪律", "keeping trade size aligned with account risk and stop distance", "让交易规模匹配账户风险和止损距离", "calculate size before entry and avoid emotional changes", "入场前计算仓位并避免情绪化改变", "increasing lot size after frustration or excitement", "因沮丧或兴奋而增加手数", "position-size calculator and maximum lot rule", "仓位计算器和最大手数规则"),
  psy("accepting_losses", "accepting losses", "接受亏损", "treating planned losses as part of controlled trading", "把计划内亏损视为受控交易的一部分", "take the planned stop and review process quality", "接受计划止损并复盘过程质量", "moving stop loss to avoid being wrong", "为了避免承认错误而移动止损", "predefined invalidation and loss acceptance note", "预设失效位和亏损接受备注"),
  psy("emotional_control", "emotional control", "情绪控制", "not allowing fear, anger, or excitement to control execution", "不让恐惧、愤怒或兴奋控制执行", "pause when emotion rises and return to objective rules", "情绪升高时暂停，并回到客观规则", "executing because emotion feels urgent", "因为情绪感觉紧急就执行", "breathing pause and checklist before order placement", "下单前呼吸暂停和清单"),
  psy("journaling", "journaling", "交易日志", "recording setup, execution, emotions, and lessons", "记录设定、执行、情绪和教训", "review patterns and adjust rules from evidence", "复盘模式，并依据证据调整规则", "writing only profit or loss with no process notes", "只写盈亏，不写过程备注", "mandatory screenshot and emotion tag", "强制截图和情绪标签"),
  psy("reviewing_mistakes", "reviewing mistakes", "复盘错误", "studying repeated execution errors to improve behaviour", "研究重复执行错误以改善行为", "identify the trigger and change the routine", "识别触发因素并改变流程", "hiding losing trades from the journal", "把亏损交易从日志中隐藏", "weekly review and repeated-mistake action item", "每周复盘和重复错误行动项"),
  psy("not_moving_stop", "not moving stop loss emotionally", "不情绪化移动止损", "keeping invalidation fixed unless the plan allows adjustment", "除非计划允许，否则保持失效位固定", "respect the original invalidation and planned risk", "尊重原始失效位和计划风险", "widening the stop because price is close", "因为价格接近就放宽止损", "stop-change rule requiring objective structure", "要求客观结构的止损修改规则"),
  psy("not_all_in", "not all-in trading", "不梭哈交易", "avoiding single trades that can severely damage or destroy the account", "避免单笔交易严重伤害或摧毁账户", "cap risk and prioritize account survival", "限制风险并优先保护账户生存", "risking most of the account on one idea", "把账户大部分资金押在一个思路上", "hard maximum risk per trade", "单笔交易硬性最大风险"),
  psy("not_increasing_after_losses", "not increasing lot size after losses", "亏损后不加大手数", "avoiding size increases driven by recovery pressure", "避免因回本压力而增加仓位", "keep size fixed or pause until calm", "保持仓位固定或暂停到冷静", "larger next trade because two losses happened", "因为连续两笔亏损所以下一笔更大", "loss-streak cooldown and fixed lot policy", "连亏冷静期和固定手数政策"),
  psy("prop_firm_psychology", "prop firm psychology", "Prop Firm 心理", "staying disciplined under evaluation rules and drawdown limits", "在评估规则和回撤限制下保持纪律", "protect rule compliance over one attractive setup", "优先保护规则合规，而不是一笔诱人设定", "trading near max loss because the setup looks clean", "接近最大亏损时因为设定看起来干净仍交易", "daily and max loss stop rules", "每日和最大亏损停止规则"),
  psy("funded_account_discipline", "funded account discipline", "资助账户纪律", "maintaining consistent process after account access is earned", "获得账户权限后仍保持一致流程", "trade smaller and steadier if pressure increases", "压力升高时更小更稳定地交易", "oversizing because the account is funded", "因为账户已资助就超大仓位", "consistency checklist and withdrawal-protection rules", "一致性清单和保护出金规则"),
  psy("consistency", "consistency", "一致性", "repeating a controlled process across many trades", "在多笔交易中重复受控流程", "focus on repeatable behaviour rather than one perfect trade", "关注可重复行为，而不是一笔完美交易", "changing strategy every time a trade loses", "每亏一笔就换策略", "same setup criteria and measured review cycle", "相同设定标准和可衡量复盘周期"),
  psy("confidence_overconfidence", "confidence versus overconfidence", "自信与过度自信", "trusting a tested process without assuming the next trade cannot fail", "相信经过检验的流程，但不假设下一笔不会失败", "keep normal risk even after strong performance", "即使表现强劲也保持正常风险", "believing a winning streak means the next trade is safe", "相信连胜代表下一笔安全", "risk cap that does not change after wins", "盈利后也不改变的风险上限"),
  psy("losing_streak", "handling a losing streak", "处理连亏", "responding to multiple losses without panic or revenge", "面对多笔亏损时不恐慌也不报复", "pause, review whether rules were followed, and reduce pressure", "暂停，复盘是否遵守规则，并降低压力", "forcing one bigger trade to recover the streak", "强行用一笔更大交易追回连亏", "loss-streak pause and review threshold", "连亏暂停和复盘阈值"),
  psy("winning_streak", "handling a winning streak", "处理连胜", "staying disciplined after several profitable trades", "多笔盈利后仍保持纪律", "protect standards and avoid increasing risk from excitement", "保护标准，避免因兴奋增加风险", "lowering setup quality because confidence is high", "因信心高而降低设定质量", "same checklist after wins as after losses", "盈利后与亏损后使用相同清单"),
];

function buildQuestions() {
  categorySerials.theory = 0;
  categorySerials.technical = 0;
  categorySerials.psychology = 0;
  const questions = [];
  addTheory(questions);
  addTechnical(questions);
  addPsychology(questions);
  assertCounts(questions);
  return questions;
}

function addTheory(questions) {
  let index = 0;
  for (const topic of theoryTopics) {
    index++;
    addQuestion(questions, "theory", index, topic, 0, {
      qEn: `What does ${topic.en} represent in trading?`,
      qZh: `${topic.zh}在交易中代表什么？`,
      correct: {
        en: capitalize(topic.definitionEn) + ".",
        zh: topic.definitionZh + "。",
        key: "definition",
      },
      wrong: genericWrong("theory"),
      explanation: {
        en: `${capitalize(topic.en)} matters because it affects planning, execution, or risk review.`,
        zh: `${topic.zh}很重要，因为它会影响交易规划、执行或风险复盘。`,
      },
    });
    addQuestion(questions, "theory", index, topic, 1, {
      qEn: `In a practical trading plan, why should a trader check ${topic.en}?`,
      qZh: `在实际交易计划中，为什么交易者应检查${topic.zh}？`,
      correct: {
        en: `To ${topic.applicationEn}.`,
        zh: `为了${topic.applicationZh}。`,
        key: "application",
      },
      wrong: [
        opt("To guarantee that the next trade will make money.", "为了保证下一笔交易一定赚钱。", "guarantee_profit"),
        opt("To remove the need for a stop loss.", "为了不再需要止损。", "remove_stop"),
        opt("To justify entering before the setup is complete.", "为了证明在设定完成前入场是合理的。", "early_entry"),
      ],
      explanation: {
        en: `Checking ${topic.en} helps turn a trading idea into a controlled decision instead of an emotional reaction.`,
        zh: `检查${topic.zh}有助于把交易想法变成受控决策，而不是情绪反应。`,
      },
    });
    addQuestion(questions, "theory", index, topic, 2, {
      qEn: `Which mistake shows poor understanding of ${topic.en}?`,
      qZh: `哪一种错误显示交易者没有正确理解${topic.zh}？`,
      correct: {
        en: capitalize(topic.mistakeEn) + ".",
        zh: topic.mistakeZh + "。",
        key: "mistake",
      },
      wrong: [
        opt("Writing the risk and setup reason in the journal.", "把风险和设定理由写进交易日志。", "journal"),
        opt("Checking whether the condition fits the trading plan.", "检查该条件是否符合交易计划。", "plan_check"),
        opt("Defining invalidation before position size is chosen.", "选择仓位前先定义失效条件。", "invalidation"),
      ],
      explanation: {
        en: `The mistake is risky because ${topic.en} should support a written process, not override risk discipline.`,
        zh: `这个错误有风险，因为${topic.zh}应支持书面流程，而不是取代风险纪律。`,
      },
    });
  }
}

function addTechnical(questions) {
  let index = 0;
  for (const topic of technicalTopics) {
    index++;
    addQuestion(questions, "technical", index, topic, 0, {
      qEn: `What does ${topic.en} describe in market analysis?`,
      qZh: `${topic.zh}在市场分析中描述什么？`,
      correct: {
        en: capitalize(topic.definitionEn) + ".",
        zh: topic.definitionZh + "。",
        key: "definition",
      },
      wrong: genericWrong("technical"),
      explanation: {
        en: `${capitalize(topic.en)} is useful only when it is read with context, structure, and defined risk.`,
        zh: `${topic.zh}只有结合背景、结构和明确风险时才有价值。`,
      },
    });
    addQuestion(questions, "technical", index, topic, 1, {
      qEn: `How should a trader apply ${topic.en} before taking an entry?`,
      qZh: `交易者在入场前应如何运用${topic.zh}？`,
      correct: {
        en: `They should ${topic.applicationEn}.`,
        zh: `应${topic.applicationZh}。`,
        key: "application",
      },
      wrong: [
        opt("Enter immediately with the largest available lot size.", "立即使用可用的最大手数入场。", "max_lot"),
        opt("Ignore higher timeframe context and react to one candle.", "忽略高周期背景，只对一根K线反应。", "ignore_htf"),
        opt("Move the stop farther whenever price hesitates.", "只要价格犹豫就把止损移远。", "move_stop"),
      ],
      explanation: {
        en: `Applying ${topic.en} well means connecting evidence to entry, invalidation, and trade management.`,
        zh: `良好运用${topic.zh}意味着把证据连接到入场、失效条件和交易管理。`,
      },
    });
    addQuestion(questions, "technical", index, topic, 2, {
      qEn: `What would most clearly invalidate a trade idea based on ${topic.en}?`,
      qZh: `什么情况最能使基于${topic.zh}的交易思路失效？`,
      correct: {
        en: capitalize(topic.invalidationEn) + ".",
        zh: topic.invalidationZh + "。",
        key: "invalidation",
      },
      wrong: [
        opt("The trader feels impatient after waiting.", "交易者等待后感到不耐烦。", "impatient"),
        opt("The previous trade was a loss.", "上一笔交易亏损了。", "previous_loss"),
        opt("The profit target looks attractive.", "盈利目标看起来很诱人。", "attractive_target"),
      ],
      explanation: {
        en: `Invalidation must come from the setup logic, not from emotion or the desire for a specific outcome.`,
        zh: `失效条件必须来自设定逻辑，而不是情绪或对特定结果的期待。`,
      },
    });
    addQuestion(questions, "technical", index, topic, 3, {
      qEn: `Which evidence gives stronger confirmation for ${topic.en}?`,
      qZh: `哪一种证据能更强地确认${topic.zh}？`,
      correct: {
        en: capitalize(topic.confirmationEn) + ".",
        zh: topic.confirmationZh + "。",
        key: "confirmation",
      },
      wrong: [
        opt("A social-media post with no chart validation.", "没有图表验证的社交媒体帖子。", "social_signal"),
        opt("A desire to recover the previous loss quickly.", "想快速追回上一笔亏损的欲望。", "recover"),
        opt("A random indicator color change without structure.", "没有结构支持的随机指标颜色变化。", "random_indicator"),
      ],
      explanation: {
        en: `Confirmation should improve decision quality and make the risk location clearer.`,
        zh: `确认应提高决策质量，并让风险位置更清楚。`,
      },
    });
    if (index <= 14) {
      addQuestion(questions, "technical", index, topic, 4, {
        qEn: `When should a trader avoid using ${topic.en} as the main entry reason?`,
        qZh: `什么时候交易者应避免把${topic.zh}作为主要入场理由？`,
        correct: {
          en: capitalize(topic.noTradeEn) + ".",
          zh: topic.noTradeZh + "。",
          key: "avoid",
        },
        wrong: [
          opt("When the setup has clear invalidation and acceptable risk.", "当设定有清晰失效条件且风险可接受时。", "valid_risk"),
          opt("When it aligns with the written trading plan.", "当它符合书面交易计划时。", "plan_aligned"),
          opt("When higher timeframe and lower timeframe evidence agree.", "当高周期与低周期证据一致时。", "timeframe_agree"),
        ],
        explanation: {
          en: `A no-trade decision is valid when evidence is weak, conflicting, or outside the written plan.`,
          zh: `当证据薄弱、冲突或超出书面计划时，不交易是有效决策。`,
        },
      });
    }
  }
}

function addPsychology(questions) {
  let index = 0;
  for (const topic of psychologyTopics) {
    index++;
    addQuestion(questions, "psychology", index, topic, 0, {
      qEn: `Which behaviour best reflects ${topic.en}?`,
      qZh: `哪种行为最能体现${topic.zh}？`,
      correct: {
        en: capitalize(topic.behaviourEn) + ".",
        zh: topic.behaviourZh + "。",
        key: "behaviour",
      },
      wrong: [
        opt("Following the written setup and risk rules.", "遵守书面设定和风险规则。", "follow_rules"),
        opt("Pausing when the market is unclear.", "市场不清晰时暂停。", "pause"),
        opt("Recording emotions honestly after the trade.", "交易后诚实记录情绪。", "journal"),
      ],
      explanation: {
        en: `${capitalize(topic.en)} is a behaviour pattern that can damage consistency if it is not controlled.`,
        zh: `${topic.zh}是一种行为模式，如果不控制，会伤害一致性。`,
      },
    });
    addQuestion(questions, "psychology", index, topic, 1, {
      qEn: `What is the most disciplined response when ${topic.en} pressure appears?`,
      qZh: `当${topic.zh}压力出现时，最有纪律的反应是什么？`,
      correct: {
        en: `The trader should ${topic.responseEn}.`,
        zh: `交易者应${topic.responseZh}。`,
        key: "response",
      },
      wrong: [
        opt("Increase lot size to solve the emotion quickly.", "增加手数来快速解决情绪。", "increase_lot"),
        opt("Enter immediately before the feeling disappears.", "在感觉消失前立即入场。", "immediate_entry"),
        opt("Ignore the plan because emotions are temporary.", "因为情绪是暂时的，所以忽略计划。", "ignore_plan"),
      ],
      explanation: {
        en: `The disciplined response reduces emotional urgency and brings execution back to the plan.`,
        zh: `有纪律的反应会降低情绪紧迫感，并让执行回到计划。`,
      },
    });
    addQuestion(questions, "psychology", index, topic, 2, {
      qEn: `Which journal note would help identify ${topic.en} in future reviews?`,
      qZh: `哪种交易日志备注有助于未来识别${topic.zh}？`,
      correct: {
        en: capitalize(topic.journalEn) + ", including the trigger and the rule affected.",
        zh: topic.journalZh + "，并记录触发因素和受影响的规则。",
        key: "journal",
      },
      wrong: [
        opt("Only writing whether the trade made or lost money.", "只写这笔交易盈利还是亏损。", "outcome_only"),
        opt("Deleting the screenshot after a bad decision.", "做出糟糕决策后删除截图。", "delete_screenshot"),
        opt("Writing that the market was unfair without details.", "只写市场不公平而没有细节。", "blame_market"),
      ],
      explanation: {
        en: `Useful journaling records behaviour, trigger, rule compliance, and chart context, not just outcome.`,
        zh: `有价值的日志记录行为、触发因素、规则遵守和图表背景，而不只是结果。`,
      },
    });
    addQuestion(questions, "psychology", index, topic, 3, {
      qEn: `Which risk rule helps prevent ${topic.en} from damaging an account?`,
      qZh: `哪条风险规则有助于防止${topic.zh}伤害账户？`,
      correct: {
        en: capitalize(topic.ruleEn) + ".",
        zh: topic.ruleZh + "。",
        key: "risk_rule",
      },
      wrong: [
        opt("Allowing risk to increase whenever confidence rises.", "只要信心上升就允许风险增加。", "confidence_risk"),
        opt("Removing the stop loss when the trade feels important.", "当交易感觉重要时移除止损。", "remove_stop"),
        opt("Taking one large trade to fix the whole week.", "用一笔大交易修复整周表现。", "one_large_trade"),
      ],
      explanation: {
        en: `A clear risk rule protects the account when psychology is weakest.`,
        zh: `清晰的风险规则能在心理最薄弱时保护账户。`,
      },
    });
  }
}

function addQuestion(questions, category, categoryIndex, topic, templateIndex, spec) {
  const categoryInfo = categories[category];
  categorySerials[category]++;
  const questionId = `${categoryInfo.prefix}_${String(categorySerials[category]).padStart(3, "0")}`;
  const order = questions.length + 1;
  const correctOptionIndex = hashSlot(questionId);
  const optionPairs = arrangeOptions(spec.correct, spec.wrong, correctOptionIndex);
  const optionsEn = optionPairs.map((item) => item.en);
  const optionsZh = optionPairs.map((item) => item.zh);
  const optionPairKeys = optionPairs.map((item) => item.key);
  const answerKey = String.fromCharCode(65 + correctOptionIndex);
  const difficulty = difficultyCycle[(order - 1) % difficultyCycle.length];
  const tags = unique([
    category,
    topic.key,
    topic.subcategory,
    difficulty,
    ...topic.tags,
  ]);

  questions.push({
    questionId,
    examType: "final",
    category,
    categoryLabel: {
      en: categoryInfo.en,
      zh: categoryInfo.zh,
    },
    subcategory: topic.subcategory,
    subcategoryLabel: {
      en: topic.subcategoryEn || titleFromKey(topic.subcategory),
      zh: topic.subcategoryZh || topic.subcategory,
    },
    difficulty,
    questionType: "single_choice",
    question_type: "single_choice",
    correctOptionIndex,
    correct_answer: answerKey,
    isActive: true,
    status: "approved",
    order,
    tags,
    locales: {
      en: {
        question: spec.qEn,
        options: optionsEn,
        explanation: spec.explanation.en,
      },
      zh: {
        question: spec.qZh,
        options: optionsZh,
        explanation: spec.explanation.zh,
      },
    },
    question: spec.qEn,
    explanation: {
      en: spec.explanation.en,
      zh: spec.explanation.zh,
    },
    options: Object.fromEntries(
      optionPairs.map((item, index) => [
        String.fromCharCode(65 + index),
        {
          text: {
            en: item.en,
            zh: item.zh,
          },
          is_correct: index === correctOptionIndex,
        },
      ]),
    ),
    seedMeta: {
      contentVersion: "final_exam_500_v1",
      optionPairKeys,
      generatedBy: "tools/generate_final_exam_questions_500.js",
    },
  });
}

function arrangeOptions(correct, wrong, correctOptionIndex) {
  const distractors = wrong.slice(0, 3);
  if (distractors.length !== 3) {
    throw new Error("Every question needs exactly three distractors.");
  }
  const options = [...distractors];
  options.splice(correctOptionIndex, 0, correct);
  return options;
}

function genericWrong(area) {
  if (area === "technical") {
    return [
      opt("A guarantee that the next candle will reverse.", "保证下一根K线一定反转。", "guarantee_reversal"),
      opt("A broker setting that removes execution risk.", "一种能消除执行风险的经纪商设置。", "broker_setting"),
      opt("A reason to trade without invalidation.", "一个无需失效条件也能交易的理由。", "no_invalidation"),
    ];
  }
  return [
    opt("A guarantee that the next trade will be profitable.", "保证下一笔交易会盈利。", "guarantee_profit"),
    opt("A platform password or login setting.", "平台密码或登录设置。", "password"),
    opt("A chart decoration that has no effect on planning.", "对规划没有影响的图表装饰。", "decoration"),
  ];
}

function t(key, en, zh, subcategory, definitionEn, definitionZh, applicationEn, applicationZh, mistakeEn, mistakeZh, tags) {
  return {
    key,
    en,
    zh,
    subcategory,
    subcategoryEn: titleFromKey(subcategory),
    subcategoryZh: zhSubcategory(subcategory),
    definitionEn,
    definitionZh,
    applicationEn,
    applicationZh,
    mistakeEn,
    mistakeZh,
    tags,
  };
}

function tech(key, en, zh, subcategory, definitionEn, definitionZh, applicationEn, applicationZh, invalidationEn, invalidationZh, confirmationEn, confirmationZh, noTradeEn, noTradeZh) {
  return {
    key,
    en,
    zh,
    subcategory,
    subcategoryEn: titleFromKey(subcategory),
    subcategoryZh: zhSubcategory(subcategory),
    definitionEn,
    definitionZh,
    applicationEn,
    applicationZh,
    invalidationEn,
    invalidationZh,
    confirmationEn,
    confirmationZh,
    noTradeEn,
    noTradeZh,
    tags: [subcategory, key],
  };
}

function psy(key, en, zh, behaviourEn, behaviourZh, responseEn, responseZh, journalEn, journalZh, ruleEn, ruleZh) {
  return {
    key,
    en,
    zh,
    subcategory: key,
    subcategoryEn: titleFromKey(key),
    subcategoryZh: zh,
    behaviourEn,
    behaviourZh,
    responseEn,
    responseZh,
    journalEn,
    journalZh,
    ruleEn,
    ruleZh,
    tags: ["risk_discipline", key],
  };
}

function opt(en, zh, key) {
  return {en, zh, key};
}

function hashSlot(value) {
  let hash = 0;
  for (const char of value) {
    hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
  }
  return hash % 4;
}

function capitalize(value) {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function titleFromKey(value) {
  return value
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function zhSubcategory(value) {
  return {
    forex_basics: "外汇基础",
    currency_pairs: "货币对",
    pricing: "报价",
    position_sizing: "仓位计算",
    margin: "保证金",
    account_metrics: "账户指标",
    costs: "交易成本",
    orders: "订单",
    risk_management: "风险管理",
    trade_management: "交易管理",
    news: "新闻",
    fundamentals: "基本面",
    sessions: "交易时段",
    market_conditions: "市场状态",
    execution: "执行",
    broker: "经纪商",
    platform: "平台",
    snr_zones: "支撑阻力区",
    price_action: "价格行为",
    trend_tools: "趋势工具",
    market_structure: "市场结构",
    entry_model: "入场模型",
    liquidity: "流动性",
    zones: "区域",
    imbalance: "不平衡",
    poi: "兴趣点",
    timeframes: "多周期",
    indicators: "指标",
    candles: "K线",
    analysis_process: "分析流程",
  }[value] || value;
}

function unique(values) {
  return [...new Set(values.filter(Boolean))];
}

function assertCounts(questions) {
  const counts = questions.reduce((map, question) => {
    map[question.category] = (map[question.category] || 0) + 1;
    return map;
  }, {});
  const expected = {theory: 150, technical: 250, psychology: 100};
  for (const [category, count] of Object.entries(expected)) {
    if (counts[category] !== count) {
      throw new Error(`Expected ${count} ${category} questions, got ${counts[category] || 0}.`);
    }
  }
  if (questions.length !== 500) {
    throw new Error(`Expected 500 questions, got ${questions.length}.`);
  }
}

function main() {
  const questions = buildQuestions();
  fs.mkdirSync(path.dirname(outputPath), {recursive: true});
  fs.writeFileSync(outputPath, `${JSON.stringify(questions, null, 2)}\n`, "utf8");
  console.log(`Generated ${questions.length} questions at ${outputPath}`);
  console.log("Category counts:", {
    theory: questions.filter((q) => q.category === "theory").length,
    technical: questions.filter((q) => q.category === "technical").length,
    psychology: questions.filter((q) => q.category === "psychology").length,
  });
}

if (require.main === module) {
  main();
}

module.exports = {buildQuestions};
