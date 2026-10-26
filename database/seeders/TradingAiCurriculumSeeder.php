<?php

namespace Database\Seeders;

use App\Models\AiKnowledgeBase;
use Illuminate\Database\Seeder;

class TradingAiCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->topics() as [$module, $assets, $enQuestion, $enAnswer, $zhQuestion, $zhAnswer, $msQuestion, $msAnswer]) {
            foreach (['en' => [$enQuestion, $enAnswer], 'zh' => [$zhQuestion, $zhAnswer], 'ms' => [$msQuestion, $msAnswer]] as $locale => [$question, $answer]) {
                AiKnowledgeBase::firstOrCreate([
                    'entry_type' => 'faq', 'language' => $locale,
                    'normalized_question' => AiKnowledgeBase::normalizeQuestion($question),
                ], [
                    'module' => $module, 'title' => $question, 'content' => $answer,
                    'status' => AiKnowledgeBase::STATUS_PUBLISHED, 'approved_at' => now(),
                    'published_at' => now(), 'source' => 'HC TradingHub owner curriculum, 2026-10-26',
                    'source_type' => 'owner_specification', 'content_owner' => 'HC TradingHub',
                    'last_verified_at' => now(), 'asset_classes' => $assets,
                    'difficulty' => 'beginner', 'version' => 1, 'reviewed_at' => '2026-10-26',
                ]);
            }
        }
    }

    private function topics(): array
    {
        $general = AiKnowledgeBase::MODULE_GENERAL;
        $structure = AiKnowledgeBase::MODULE_MARKET_STRUCTURE;
        $risk = AiKnowledgeBase::MODULE_RISK_MANAGEMENT;
        $management = AiKnowledgeBase::MODULE_TRADE_MANAGEMENT;
        return [
            [$general, ['forex', 'stocks', 'futures', 'crypto'],
                'What are bid ask spread and slippage?',
                'Bid is the price at which you can usually sell and ask is the price at which you can usually buy; the spread is their difference. Slippage is a fill at a different price than requested, especially when liquidity is thin or markets gap. Example: a 100 bid and 101 ask imply a 1-unit spread before fees. A stop order does not guarantee a fill at its stop price.',
                '什么是买卖价差和滑点？',
                '买价通常是可卖出的报价，卖价通常是可买入的报价，两者之差是点差。滑点是实际成交价偏离预期，流动性不足或跳空时更常见。例如买价100、卖价101，点差为1，尚未计算费用。止损单不保证按止损价成交。',
                'Apakah bid ask spread dan gelinciran harga?',
                'Bid biasanya harga untuk menjual dan ask harga untuk membeli; beza kedua-duanya ialah spread. Gelinciran berlaku apabila harga pelaksanaan berbeza daripada harga diminta, terutama ketika kecairan rendah atau jurang harga. Contoh: bid 100 dan ask 101 memberi spread 1 sebelum fi. Pesanan henti rugi tidak menjamin harga pelaksanaan.' ],
            [$general, ['forex', 'metals', 'stocks', 'futures', 'crypto', 'cfd'],
                'How do contract specifications differ across asset classes?',
                'Forex, gold, shares, futures, crypto and CFDs can use different tick sizes, contract sizes, quote currencies, margin, expiry and funding rules. Verify the exact broker or exchange specification before calculating money risk or lot size. For example, a gold CFD lot must not be assumed to equal a futures contract; using the wrong tick value can understate loss.',
                '不同资产的合约规格有什么差异？',
                '外汇、黄金、股票、期货、加密资产和差价合约的最小跳动、合约规模、报价货币、保证金及到期或隔夜费用可能不同。计算金额风险或手数前须核对具体经纪商或交易所规格。黄金差价合约的一手不能直接当作期货一张。',
                'Bagaimana spesifikasi kontrak berbeza mengikut aset?',
                'Forex, emas, saham, niaga hadapan, kripto dan CFD boleh mempunyai saiz tick, saiz kontrak, mata wang sebut harga, margin dan caj pegangan yang berbeza. Sahkan spesifikasi broker atau bursa sebelum mengira risiko wang atau saiz posisi. Satu lot CFD emas tidak semestinya sama dengan satu kontrak niaga hadapan.' ],
            [$structure, ['forex', 'metals', 'indices'],
                'What are BOS CHOCH and a liquidity sweep?',
                "In HC TradingHub's framework, BOS is a confirmed close beyond a relevant swing in the trend direction; CHOCH is the first confirmed break against that timeframe's trend. A liquidity sweep briefly crosses a level but closes back, so a wick alone is not a confirmed BOS. Example: an M15 bearish CHOCH may be a correction while H1 remains bullish. Definitions vary by method; state the timeframe and invalidation.",
                '什么是BOS、CHOCH和流动性扫荡？',
                '按HC TradingHub框架，BOS是顺当前周期趋势突破相关摆动位并确认收盘；CHOCH是首次逆该周期趋势的确认突破。流动性扫荡可能短暂越位后收回，因此影线不等于确认突破。例如M15转弱时H1仍可能看涨。须说明周期及失效条件。',
                'Apakah BOS CHOCH dan sapuan kecairan?',
                'Dalam rangka HC TradingHub, BOS ialah penutupan disahkan melepasi swing searah trend; CHOCH ialah pecahan pertama melawan trend pada rangka masa itu. Sapuan kecairan boleh menembusi aras seketika lalu tutup semula; sumbu sahaja bukan BOS. CHOCH M15 boleh menjadi pembetulan ketika H1 masih menaik. Nyatakan rangka masa dan pembatalan.' ],
            [$structure, ['forex', 'metals', 'indices'],
                'How do fresh and retested support and resistance zones work?',
                'Support and resistance are zones, not guaranteed exact prices. A fresh zone has not yet been retested; later tests can change its quality. A confirmed break and retest can flip support into resistance or the reverse. Example: a wick through a zone followed by a close back inside may be a failed break, not a valid breakout; require structure and invalidation before considering a setup.',
                '新鲜与回测的支撑阻力区有什么区别？',
                '支撑和阻力是区域而非保证反转的单一价格。新鲜区域尚未回测，重复触及可能改变其质量；确认突破并回测后可能发生支阻转换。例如影线穿越后收回区内，可能是假突破，须结合结构和失效条件。',
                'Apa beza zon sokongan rintangan baharu dan diuji semula?',
                'Sokongan dan rintangan ialah zon, bukan harga pembalikan yang terjamin. Zon baharu belum diuji semula; ujian berulang boleh mengubah kualitinya. Pecahan dan ujian semula yang disahkan boleh menukar peranan zon. Sumbu yang menembusi lalu tutup semula mungkin pecahan palsu; semak struktur dan pembatalan.' ],
            [$structure, ['forex', 'metals', 'indices'],
                'How should I use trendlines and chart patterns?',
                'Anchor a trendline to clear swing highs or lows and check the timeframe. A touch, candlestick shape, double top, flag or triangle is not a stand-alone entry signal. Example: a line break without a confirmed close or retest can fail; compare horizontal zones, broader structure, costs and reachable reward before acting. Pattern definitions are partly subjective.',
                '趋势线和图形形态应如何使用？',
                '趋势线应连接清晰的摆动高点或低点，并标明周期。触线、K线形态、双顶、旗形或三角形都不是独立入场信号。例如未确认收盘或回测的破线可能失败，还须检查水平区域、上级结构、成本及目标空间。',
                'Bagaimana menggunakan garisan trend dan corak carta?',
                'Lukis garisan trend pada swing tinggi atau rendah yang jelas dan nyatakan rangka masa. Sentuhan, bentuk lilin, double top, flag atau triangle bukan isyarat masuk tersendiri. Pecahan tanpa penutupan atau ujian semula boleh gagal; bandingkan zon mendatar, struktur besar, kos dan ruang ganjaran.' ],
            [$structure, ['forex', 'metals', 'indices', 'stocks'],
                'What do EMA RSI MACD Bollinger Bands ATR and ADR measure?',
                'EMA 24/52/100/200 smooths price and lags; RSI describes momentum, MACD compares moving-average momentum, Bollinger Bands show dispersion, ATR estimates recent range and ADR average daily range. Their readings require actual OHLCV data and defined parameters. Example: an EMA crossover in a range can whipsaw; no indicator alone confirms a trade, and this chat cannot calculate a current reading without data.',
                'EMA、RSI、MACD、布林带、ATR与ADR是什么？',
                'EMA 24/52/100/200平滑价格但有滞后；RSI衡量动量，MACD比较均线动量，布林带描述波动分布，ATR是近期真实波幅，ADR是平均日波幅。读数需要真实OHLCV和参数。例如震荡区的均线交叉可能反复失效；没有数据不能声称当前指标数值。',
                'Apakah EMA RSI MACD Bollinger Bands ATR dan ADR?',
                'EMA 24/52/100/200 melicinkan harga tetapi tertinggal; RSI mengukur momentum, MACD membandingkan momentum purata bergerak, Bollinger Bands menunjukkan sebaran, ATR julat benar terkini dan ADR julat harian purata. Bacaan memerlukan OHLCV sebenar dan parameter. Persilangan EMA dalam julat boleh berulang kali gagal; tanpa data tiada bacaan semasa.' ],
            [$structure, ['forex', 'metals', 'indices'],
                'How do H4 H1 and M15 work together?',
                "HC TradingHub's educational sequence uses H4 for major context, H1 for internal structure and M15 for execution. If H4/H1 are bullish but M15 turns bearish, that can be a pullback rather than a full reversal. Example: wait for a meaningful H1 zone and M15 confirmation; if H1 protected structure fails, reassess. When frames conflict or data is missing, no trade is reasonable.",
                'H4、H1和M15如何配合？',
                'HC TradingHub教学框架以H4看主要背景、H1看内部结构、M15看执行触发。H4/H1看涨而M15转弱可能只是回调，不代表全面反转。例如等待H1关键区域及M15确认；若H1受保护结构失效须重新评估。周期冲突或数据不足时可以不交易。',
                'Bagaimana H4 H1 dan M15 digunakan bersama?',
                'Rangka pendidikan HC TradingHub menggunakan H4 untuk konteks utama, H1 untuk struktur dalaman dan M15 untuk pencetus. M15 menurun ketika H4/H1 menaik mungkin hanya pembetulan. Tunggu zon H1 penting dan pengesahan M15; jika struktur H1 terbatal, nilai semula. Apabila rangka masa bercanggah atau data tiada, tidak berdagang munasabah.' ],
            [$risk, ['forex', 'metals', 'indices', 'stocks'],
                'How do I calculate reward risk and total idea exposure?',
                'Price-distance R:R is potential move to target divided by distance to structural stop, before spread, fees and slippage. Example: buy 4300, stop 4290, target 4325 gives 25/10 = 2.5R; this does not establish dollar risk without verified instrument specs. HC TradingHub uses about 0.5% default and at most 1% planned equity risk per idea, shared across related positions. If the nearest resistance gives only 1.5R against a 2R filter, wait or say no trade rather than invent a farther target.',
                '如何计算盈亏比和同一交易思路的总风险？',
                '价格盈亏比=到目标的距离÷到结构止损的距离，尚未计入点差和费用。例如4300买入、4290止损、4325目标为25/10=2.5R；没有合约规格不能推算美元风险。HC TradingHub以每个思路约0.5%为默认、计划风险最高1%，相关仓位合计共享。若最近阻力仅容许1.5R而规则需2R，应等待或不交易。',
                'Bagaimana mengira nisbah ganjaran risiko dan pendedahan satu idea?',
                'R:R jarak harga ialah jarak ke sasaran dibahagi jarak ke henti struktur, sebelum spread dan fi. Contoh beli 4300, henti 4290, sasaran 4325 memberi 25/10=2.5R; risiko dolar masih memerlukan spesifikasi instrumen. HC TradingHub mengajar sekitar 0.5% lalai dan paling tinggi 1% risiko ekuiti dirancang bagi satu idea termasuk posisi berkaitan. Jika rintangan terdekat hanya memberi 1.5R, tunggu atau jangan berdagang.' ],
            [$management, ['forex', 'metals', 'indices'],
                'How should I plan entry stop loss take profit and break even?',
                'Plan a trigger and a structural invalidation before entry. Put a stop beyond the relevant swing or zone with broker distance, volatility and costs considered; choose a reachable opposing level for take profit. Consider break-even around +1R or an opposing level only if the tested plan and structure support it. Example: moving to entry too early can stop a valid pullback, and entry-price BE can still lose fees or slip. Five related positions share one idea risk cap.',
                '如何规划入场、止损、止盈与保本？',
                '入场前先定义触发条件和结构失效位。止损应在相关摆动或区域之外，并考虑经纪商最小距离、波动和成本；止盈选可达的对向区域。约+1R或重要对向位时可考虑保本，但须符合已测试计划和结构。过早移到入场价可能被正常回调扫出，费用和滑点仍可造成亏损；相关多仓共享同一风险上限。',
                'Bagaimana merancang masuk henti rugi ambil untung dan pulang modal?',
                'Sebelum masuk, tetapkan pencetus dan pembatalan struktur. Letak henti di luar swing atau zon berkaitan sambil mengambil kira jarak minimum broker, turun naik dan kos; pilih sasaran lawan yang boleh dicapai. Pulang modal sekitar +1R atau zon lawan hanya pilihan jika pelan diuji menyokongnya. Alih terlalu awal boleh terkena pembetulan biasa; fi dan gelinciran masih boleh rugi. Posisi berkaitan berkongsi satu had risiko idea.' ],
            [AiKnowledgeBase::MODULE_TRADING_PSYCHOLOGY, ['all'],
                'What should I do after FOMO or revenge trading?',
                'Pause trading, check your daily loss limit and compare the attempted setup with a written checklist. Record the trigger, emotion, planned risk and actual outcome in a journal; focus on process, not quick recovery. Example: doubling lot size after a loss compounds drawdown and is not a disciplined recovery method. Resume only when a new independent setup and risk limit are valid.',
                '遇到FOMO或报复性交易怎么办？',
                '先暂停，检查当日亏损限制，并用书面清单核对交易计划。把触发原因、情绪、计划风险和结果写入日志，关注过程而非快速回本。例如亏损后加倍手数会扩大回撤，不是纪律性恢复方法。只有新机会独立有效且风险合规才考虑继续。',
                'Apa patut dibuat selepas FOMO atau dagangan balas dendam?',
                'Berhenti seketika, semak had rugi harian dan bandingkan idea dengan senarai semak bertulis. Catat pencetus, emosi, risiko dirancang dan hasil dalam jurnal; tumpu pada proses, bukan pulih cepat. Menggandakan lot selepas rugi membesarkan susut nilai dan bukan cara disiplin. Sambung hanya dengan setup baharu yang sah dan had risiko dipatuhi.' ],
            [AiKnowledgeBase::MODULE_TRADING_STYLES, ['all'],
                'How do scalping day swing and position trading differ?',
                'Scalping seeks very short holds and is sensitive to spread and execution; day trading closes within the session; swing trading may hold days with overnight risk; position trading can hold longer and depends more on broad context. Compare time available, instrument, costs, volatility and tolerated loss. Example: a wide-spread instrument can make a tiny scalping target unattractive. No style is reliably profitable for everyone.',
                '剥头皮、日内、波段和长线交易有何不同？',
                '剥头皮持仓很短，对点差和执行敏感；日内通常当日平仓；波段可持有数日并承担隔夜风险；长线依赖更广背景。应比较时间、品种、成本、波动和可承受损失。例如高点差会侵蚀很小的剥头皮目标。没有适合所有人的稳赚风格。',
                'Apa beza scalping harian swing dan posisi?',
                'Scalping memegang sangat singkat dan sensitif kepada spread serta pelaksanaan; dagangan harian ditutup dalam sesi; swing boleh dipegang beberapa hari dengan risiko semalaman; posisi lebih lama dan bergantung pada konteks luas. Bandingkan masa, instrumen, kos, turun naik dan toleransi rugi. Spread besar boleh menghakis sasaran scalping kecil. Tiada gaya yang pasti untung.' ],
            [$general, ['forex', 'metals', 'stocks', 'indices'],
                'How do interest rates inflation and economic releases affect markets?',
                'Interest rates, inflation and official releases can affect yields, currencies, financing costs and risk appetite, but reaction depends on expectations and market context. Check the original central bank or statistics agency for the release, date and revision. Example: a rate increase does not guarantee a currency rise if markets expected more. This assistant has no verified live news calendar and cannot state today’s outcome.',
                '利率、通胀和经济数据如何影响市场？',
                '利率、通胀及官方数据可能影响收益率、货币、融资成本和风险偏好，但反应取决于预期与背景。应从央行或统计机构核对发布日期和修订。例如加息不保证货币上涨，若市场原本预期更高则可能相反。本助手没有已核验实时新闻日历，不能声称今日结果。',
                'Bagaimana kadar faedah inflasi dan data ekonomi mempengaruhi pasaran?',
                'Kadar faedah, inflasi dan keluaran rasmi boleh mempengaruhi hasil bon, mata wang, kos pembiayaan dan selera risiko, namun reaksi bergantung pada jangkaan. Semak tarikh serta semakan pada bank pusat atau agensi statistik asal. Kenaikan kadar tidak menjamin mata wang naik jika pasaran menjangka lebih tinggi. Pembantu ini tiada kalendar berita langsung yang disahkan.' ],
            [AiKnowledgeBase::MODULE_TRADING_JOURNAL, ['all'],
                'How should I interpret win rate expectancy and drawdown in my journal?',
                'Win rate is wins divided by completed wins plus losses; expectancy combines average win and loss with their frequencies; drawdown measures decline from a prior peak. A high win rate can still lose money when average losses are much larger than wins. Use actual authorized journal records, include fees and sample size, and separate measured facts from possible behavior patterns. This general explanation is not your personal result.',
                '如何解读日志里的胜率、期望值和回撤？',
                '胜率是盈利笔数÷已完成盈利与亏损笔数；期望值结合平均盈利、平均亏损及各自频率；回撤是从此前高点下跌的幅度。高胜率若平均亏损远大于盈利仍可能总体亏损。个人结论须使用本人获授权的实际记录、费用及样本数。此处只是通用解释。',
                'Bagaimana memahami kadar menang jangkaan dan susut nilai dalam jurnal?',
                'Kadar menang ialah bilangan menang dibahagi menang tambah kalah yang selesai; jangkaan menggabungkan purata menang dan kalah dengan kekerapan; susut nilai ialah penurunan daripada puncak terdahulu. Kadar menang tinggi masih boleh rugi jika purata kalah jauh lebih besar. Kesimpulan peribadi memerlukan rekod sendiri yang dibenarkan, fi dan saiz sampel. Ini penerangan umum.' ],
            [$general, ['all'],
                'How can I spot trading scams and unsafe guarantees?',
                'Guaranteed profit, pressure to deposit quickly, undisclosed conflicts, unverifiable performance and requests for account credentials are warning signs. Verify the broker or platform with the relevant regulator for your jurisdiction and date; regulatory rules differ and can change. Example: a screenshot of winning trades is not an audited track record. Never share passwords or API keys with this assistant.',
                '如何识别交易骗局和不安全的收益保证？',
                '保证收益、催促入金、利益冲突不透明、业绩无法核实或索取账户密码都是警讯。须按所在司法辖区及当时日期向相关监管机构核对平台资格；法规会变化。例如盈利截图不是审计业绩。不要向本助手提供密码或API密钥。',
                'Bagaimana mengenal pasti penipuan dagangan dan janji untung?',
                'Jaminan untung, tekanan mendeposit cepat, konflik tersembunyi, prestasi tidak boleh disahkan dan permintaan kata laluan ialah tanda amaran. Sahkan broker atau platform dengan pengawal selia berkaitan mengikut bidang kuasa serta tarikh; peraturan boleh berubah. Tangkapan layar kemenangan bukan rekod diaudit. Jangan kongsi kata laluan atau kunci API.' ],
            [$general, ['all'],
                'What TradingHub features can this assistant actually access?',
                'This assistant can explain approved TradingHub knowledge, use saved published Market Analyst outlooks as historical context when your membership permits, and discuss your own Trading Journal through its read-only authorized context. The structure, risk and break-even worksheets use your supplied inputs. It cannot place orders, change accounts, inspect chart uploads or verify live quotes in this chat. Feature visibility depends on TradingHub module access; ask support about current subscriptions or prices.',
                '这个助手实际可使用哪些TradingHub功能？',
                '本助手可解释已批准的TradingHub知识，并在会员权限允许时把已发布Market Analyst展望作为历史背景；Trading Journal仅通过获授权的只读上下文讨论本人记录。结构、风险及保本工具使用您输入的数据。它不能下单、修改账户、读取图像或核验实时行情。功能取决于模块权限；订阅价格请咨询客服。',
                'Apakah ciri TradingHub yang benar-benar boleh digunakan pembantu ini?',
                'Pembantu ini menerangkan pengetahuan TradingHub yang diluluskan, menggunakan tinjauan Market Analyst terbitan sebagai konteks sejarah jika akses ahli membenarkan, dan membincangkan Jurnal Dagangan anda melalui konteks baca sahaja. Lembaran struktur, risiko dan pulang modal menggunakan input anda. Ia tidak boleh membuat pesanan, menukar akaun, membaca imej atau mengesahkan sebut harga langsung. Akses ciri bergantung pada modul; tanya sokongan tentang harga langganan.' ],
        ];
    }
}
