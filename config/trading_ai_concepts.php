<?php

/*
 * Canonical IDs and aliases are code-reviewed data. Git history is the audit trail;
 * aliases never create another teaching article or change the published prompt.
 */
return [
    'version' => 1,
    'concepts' => [
        'break_even' => ['aliases' => ['BE', 'break even', 'breakeven', 'move stop to entry', '保本', '移动止损至成本', 'pulang modal'], 'related' => ['stop_loss'], 'not_equivalent' => ['guaranteed_zero_loss']],
        'stop_loss' => ['aliases' => ['SL', 'stop loss', 'cut loss', '止损', '止蝕', 'henti rugi'], 'related' => ['break_even'], 'not_equivalent' => ['stop_limit', 'guaranteed_execution']],
        'take_profit' => ['aliases' => ['TP', 'take profit', 'profit target', '止盈', '止赚', 'ambil untung'], 'related' => ['risk_reward'], 'not_equivalent' => []],
        'risk_reward' => ['aliases' => ['RR', 'R:R', 'reward risk', 'risk to reward', 'R multiple', '风险回报比', '盈亏比', 'nisbah ganjaran risiko'], 'related' => ['stop_loss', 'take_profit'], 'not_equivalent' => ['win_probability']],
        'bos' => ['aliases' => ['BOS', 'break of structure', 'structure break', '结构突破', 'pecah struktur'], 'related' => ['choch'], 'not_equivalent' => ['liquidity_sweep']],
        'choch' => ['aliases' => ['CHOCH', 'change of character', 'market structure shift', '结构转变', 'perubahan karakter'], 'related' => ['bos'], 'not_equivalent' => ['confirmed_major_reversal']],
        'liquidity_sweep' => ['aliases' => ['liquidity sweep', 'stop hunt', '流动性扫荡', 'sapuan kecairan'], 'related' => ['bos'], 'not_equivalent' => ['bos']],
        'support_resistance' => ['aliases' => ['S/R', 'SNR', 'support and resistance', 'support zone', 'resistance zone', '支撑阻力', 'sokongan rintangan'], 'related' => ['bos'], 'not_equivalent' => ['guaranteed_reversal']],
        'liquidation' => ['aliases' => ['liquidation', 'liquidated', '爆仓', 'likuidasi'], 'related' => ['margin_call'], 'not_equivalent' => ['margin_call']],
        'margin_call' => ['aliases' => ['margin call', '追缴保证金', 'panggilan margin'], 'related' => ['liquidation'], 'not_equivalent' => ['liquidation']],
        'xauusd_contract' => ['aliases' => ['XAUUSD lot', 'gold lot', 'gold pip', '黄金手数', 'lot emas'], 'related' => ['tick_value'], 'not_equivalent' => ['futures_contract']],
        'stock_split' => ['aliases' => ['stock split', 'reverse split', 'split adjusted', '拆股', '股票分割', 'pecahan saham'], 'related' => ['total_return'], 'not_equivalent' => ['market_crash']],
        'index_products' => ['aliases' => ['index CFD', 'index future', 'index ETF', '指数差价合约', '指数期货', '指数ETF'], 'related' => [], 'not_equivalent' => ['same_contract']],
        'option_assignment' => ['aliases' => ['option assignment', 'short option', 'exercise obligation', '期权指派', 'opsyen jual'], 'related' => ['option_greeks'], 'not_equivalent' => ['long_option_right']],
        'option_greeks' => ['aliases' => ['Greeks', 'Delta', 'Gamma', 'Theta', 'Vega', 'Rho', '希腊值'], 'related' => ['option_assignment'], 'not_equivalent' => ['guaranteed_price_change']],
        'zero_dte' => ['aliases' => ['0DTE', 'zero days to expiration', '当日到期期权'], 'related' => ['option_greeks'], 'not_equivalent' => ['spot_stop_loss']],
        'futures_roll' => ['aliases' => ['futures rollover', 'contract roll', 'continuous futures chart', 'back adjusted futures', '期货换月', 'gulung kontrak niaga hadapan'], 'related' => [], 'not_equivalent' => ['tradable_contract_quote']],
        'crypto_mark_price' => ['aliases' => ['mark price', 'index price', 'last price', '标记价格', 'harga tanda'], 'related' => ['liquidation'], 'not_equivalent' => ['last_trade_price']],
        'backtest_bias' => ['aliases' => ['look ahead bias', 'survivorship bias', 'overfitting', 'data leakage', '回测偏差', 'bias ujian balik'], 'related' => [], 'not_equivalent' => ['proven_future_profit']],
        'total_return' => ['aliases' => ['total return', 'price return', 'dividend adjusted', '总回报', 'pulangan keseluruhan'], 'related' => ['stock_split'], 'not_equivalent' => ['price_only_return']],
        'forex_pair_rollover' => ['aliases' => ['base currency', 'quote currency', 'cross rate', 'forex rollover', 'swap charge', '货币对隔夜费', 'swap forex'], 'related' => ['xauusd_contract'], 'not_equivalent' => ['universal_swap_rate']],
        'cfd_underlying' => ['aliases' => ['gold CFD', 'CFD ownership', 'physical gold ownership', '差价合约黄金', 'CFD emas'], 'related' => ['xauusd_contract'], 'not_equivalent' => ['physical_ownership']],
        'futures_margin' => ['aliases' => ['futures margin', 'initial margin', 'maintenance margin', 'variation margin', '期货保证金'], 'related' => ['futures_roll'], 'not_equivalent' => ['maximum_loss']],
        'portfolio_risk' => ['aliases' => ['portfolio correlation', 'concentration risk', 'portfolio drawdown', '组合相关性', 'korelasi portfolio'], 'related' => ['risk_reward'], 'not_equivalent' => ['single_trade_risk']],
        'macro_releases' => ['aliases' => ['economic release', 'earnings guidance', 'interest rate surprise', '经济数据发布', 'data ekonomi'], 'related' => [], 'not_equivalent' => ['deterministic_price_direction']],
        'corporate_actions' => ['aliases' => ['rights offering', 'delisting', 'corporate action', 'ex dividend', '供股', '除息'], 'related' => ['stock_split', 'total_return'], 'not_equivalent' => ['ordinary_market_crash']],
        'equity_short_sale' => ['aliases' => ['short selling', 'borrow fee', 'stock halt', '卖空', 'pinjaman saham'], 'related' => ['corporate_actions'], 'not_equivalent' => ['long_share_purchase']],
        'option_value' => ['aliases' => ['intrinsic value', 'extrinsic value', 'moneyness', 'implied volatility', '期权内在价值'], 'related' => ['option_greeks'], 'not_equivalent' => ['guaranteed_fair_value']],
        'option_settlement' => ['aliases' => ['cash settled option', 'physical settlement', 'pin risk', 'multi leg option', '现金结算期权'], 'related' => ['option_assignment'], 'not_equivalent' => ['automatic_risk_cap']],
        'futures_curve' => ['aliases' => ['contango', 'backwardation', 'futures basis', 'calendar spread', '期货升水', '期货贴水'], 'related' => ['futures_roll'], 'not_equivalent' => ['guaranteed_roll_profit']],
        'crypto_custody' => ['aliases' => ['crypto custody', 'withdrawal risk', 'dated crypto future', 'spot versus perpetual', '加密托管风险'], 'related' => ['crypto_mark_price'], 'not_equivalent' => ['insured_bank_deposit']],
        'crypto_yield' => ['aliases' => ['DeFi yield', 'staking yield', 'crypto lending', '质押收益', 'hasil staking'], 'related' => ['crypto_custody'], 'not_equivalent' => ['risk_free_yield']],
        'quant_sample_size' => ['aliases' => ['sample size', 'confidence interval', 'strategy variance', 'calibrated probability', '样本量', 'selang keyakinan'], 'related' => ['backtest_bias'], 'not_equivalent' => ['future_win_probability']],
        'seasonality' => ['aliases' => ['market seasonality', 'market anomaly', 'seasonal edge', '季节性交易'], 'related' => ['backtest_bias'], 'not_equivalent' => ['proven_predictive_rule']],
    ],
    'ambiguous' => [
        'margin' => ['initial margin', 'free margin', 'margin level', 'profit margin'],
        'volume' => ['traded volume', 'contract size', 'order quantity'],
        'spread' => ['bid-ask spread', 'option spread', 'futures calendar spread'],
        'stop' => ['stop-loss order', 'stop-market order', 'stop-limit order'],
        'contract' => ['futures contract', 'option contract', 'CFD contract'],
    ],
];
