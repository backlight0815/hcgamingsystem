@php
    $analysis = $analysis ?? null;
    $allowAllCommunity = $allowAllCommunity ?? false;
    $selectedTrendValue = old('trend_structure', $selectedTrend ?? '');
    $selectedStrengthValue = old('trend_strength', $selectedStrength ?? ($analysis->trend_strength ?? ''));
    $selectedStrengthValue = $selectedStrengthValue === 'strong_up' ? 'strong' : $selectedStrengthValue;
    $selectedCommunityValue = old('community_id', $analysis->community_id ?? '');
    $dateValue = old('analysis_date', $analysis && $analysis->analysis_date ? $analysis->analysis_date->format('Y-m-d') : now()->format('Y-m-d'));
    $errors = $errors ?? session()->get('errors', new \Illuminate\Support\ViewErrorBag);
@endphp

@once
<style>
    .ma-form-shell {
        color: #1f2937;
    }

    .ma-topbar,
    .ma-panel,
    .ma-side-panel {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .ma-topbar {
        padding: 20px 22px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .ma-kicker {
        color: #0f766e;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .ma-topbar h4,
    .ma-panel-title {
        color: #0f172a;
        font-weight: 800;
        margin: 0;
    }

    .ma-topbar p,
    .ma-panel-subtitle {
        color: #64748b;
        margin: 4px 0 0;
    }

    .ma-panel {
        padding: 20px;
        margin-bottom: 16px;
    }

    .ma-panel-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 18px;
    }

    .ma-section-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        background: #0f172a;
        flex: 0 0 auto;
    }

    .ma-field-label {
        color: #334155;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 6px;
    }

    .ma-field-label.required::after {
        content: "*";
        color: #dc2626;
        margin-left: 3px;
    }

    .ma-form-shell .form-control {
        border-color: #cbd5e1;
        border-radius: 8px;
        min-height: 42px;
    }

    .ma-form-shell textarea.form-control {
        min-height: 130px;
        line-height: 1.55;
        resize: vertical;
    }

    .ma-form-shell textarea.mono {
        font-family: Consolas, Monaco, "Courier New", monospace;
        font-size: 13px;
    }

    .ma-plan-editor {
        min-height: 260px !important;
        background: #0f172a;
        border-color: #0f172a !important;
        color: #e2e8f0;
        font-family: Consolas, Monaco, "Courier New", monospace;
        font-size: 13px;
    }

    .ma-side-panel {
        padding: 18px;
        position: sticky;
        top: 90px;
    }

    .ma-checklist {
        display: grid;
        gap: 10px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .ma-checklist li {
        display: flex;
        gap: 10px;
        color: #475569;
        font-size: 13px;
        line-height: 1.45;
    }

    .ma-checklist i {
        color: #0f766e;
        margin-top: 3px;
    }

    .ma-preview-image {
        width: 100%;
        max-height: 220px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        background: #f8fafc;
    }

    .ma-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        flex-wrap: wrap;
    }

    @media (max-width: 991px) {
        .ma-topbar {
            align-items: flex-start;
            flex-direction: column;
        }

        .ma-side-panel {
            position: static;
        }
    }
</style>
@endonce

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="ma-form-shell">
    @csrf

    <div class="ma-topbar mb-3">
        <div>
            <div class="ma-kicker">{{ $mode === 'edit' ? '更新市场展望' : '新增市场展望' }}</div>
            <h4>{{ $mode === 'edit' ? '编辑市场分析' : '添加市场分析' }}</h4>
            <p>根据趋势结构、关键区间、动能与技术区域，建立专业中文分析报告。</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary" id="maDraftButton">
                <i class="fas fa-magic"></i> 根据结构自动生成
            </button>
            <a href="{{ route('market-analyst.index') }}" class="btn btn-light">
                <i class="fas fa-arrow-left"></i> 返回
            </a>
        </div>
    </div>

    <div class="breadcrumb mb-3">
        @foreach ($breadcrumbData as $breadcrumb)
            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
            @if (!$loop->last)
                <span class="mx-1">/</span>
            @endif
        @endforeach
    </div>

    @if(isset($errors) && $errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="row">
        <div class="col-xl-8">
            <div class="ma-panel">
                <div class="ma-panel-head">
                    <div class="d-flex gap-3">
                        <span class="ma-section-icon"><i class="fas fa-layer-group"></i></span>
                        <div>
                            <h5 class="ma-panel-title">报告设定</h5>
                            <p class="ma-panel-subtitle">选择发布社区、交易品种与分析日期。</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="community_id" class="ma-field-label required">社区</label>
                        <select name="community_id" id="community_id" class="form-control @error('community_id') is-invalid @enderror" required>
                            <option value="">请选择社区</option>
                            @if($allowAllCommunity)
                                <option value="all" {{ $selectedCommunityValue === 'all' ? 'selected' : '' }}>所有启用社区</option>
                            @endif
                            @foreach($communities as $community)
                                <option value="{{ $community->id }}" {{ (string) $selectedCommunityValue === (string) $community->id ? 'selected' : '' }}>
                                    {{ $community->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('community_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="analysis_date" class="ma-field-label required">分析日期</label>
                        <input type="date" name="analysis_date" id="analysis_date" class="form-control @error('analysis_date') is-invalid @enderror" value="{{ $dateValue }}" required>
                        @error('analysis_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-8 mb-3">
                        <label for="title" class="ma-field-label required">标题</label>
                        <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $analysis->title ?? '') }}" placeholder="例如：XAUUSD 市场方向策略" required>
                        @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="market" class="ma-field-label required">交易品种</label>
                        <input type="text" name="market" id="market" class="form-control @error('market') is-invalid @enderror" value="{{ old('market', $analysis->market ?? '') }}" placeholder="XAUUSD" required>
                        @error('market') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="ma-panel">
                <div class="ma-panel-head">
                    <div class="d-flex gap-3">
                        <span class="ma-section-icon"><i class="fas fa-chart-line"></i></span>
                        <div>
                            <h5 class="ma-panel-title">市场结构</h5>
                            <p class="ma-panel-subtitle">选择趋势、强度、动能与关键技术区域后，系统会自动生成中文分析内容。</p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="trend_structure" class="ma-field-label">趋势结构</label>
                        <select name="trend_structure" id="trend_structure" class="form-control @error('trend_structure') is-invalid @enderror">
                            <option value="">请选择趋势结构</option>
                            <option value="uptrend" {{ $selectedTrendValue === 'uptrend' ? 'selected' : '' }}>多头趋势 Uptrend</option>
                            <option value="downtrend" {{ $selectedTrendValue === 'downtrend' ? 'selected' : '' }}>空头趋势 Downtrend</option>
                            <option value="ranging" {{ $selectedTrendValue === 'ranging' ? 'selected' : '' }}>震荡区间 Ranging</option>
                        </select>
                        @error('trend_structure') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="trend_strength" class="ma-field-label">趋势强度</label>
                        <select name="trend_strength" id="trend_strength" class="form-control @error('trend_strength') is-invalid @enderror">
                            <option value="">请选择趋势强度</option>
                            <option value="strong" {{ $selectedStrengthValue === 'strong' ? 'selected' : '' }}>强势 Strong</option>
                            <option value="medium" {{ $selectedStrengthValue === 'medium' ? 'selected' : '' }}>中等 Medium</option>
                            <option value="weak" {{ $selectedStrengthValue === 'weak' ? 'selected' : '' }}>偏弱 Weak</option>
                        </select>
                        @error('trend_strength') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="rsi_level" class="ma-field-label">RSI / 动能</label>
                        <input type="text" name="rsi_level" id="rsi_level" list="rsi_momentum_options" class="form-control @error('rsi_level') is-invalid @enderror" value="{{ old('rsi_level', $analysis->rsi_level ?? '') }}" placeholder="请选择或输入 RSI / 动能描述">
                        <datalist id="rsi_momentum_options">
                            <option value="RSI 位于中位偏上，多头动能占优。"></option>
                            <option value="RSI 位于中位偏下，空头动能占优。"></option>
                            <option value="RSI 处于中性区域，市场动能暂时平衡。"></option>
                            <option value="RSI 接近超买区域，需留意高位回调风险。"></option>
                            <option value="RSI 接近超卖区域，需留意低位反弹风险。"></option>
                        </datalist>
                        @error('rsi_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="order_block" class="ma-field-label">Order Block / FVG</label>
                        <input type="text" name="order_block" id="order_block" list="order_block_options" class="form-control @error('order_block') is-invalid @enderror" value="{{ old('order_block', $analysis->order_block ?? '') }}" placeholder="请选择或输入 OB / FVG 描述">
                        <datalist id="order_block_options">
                            <option value="下方支撑区存在多头 Order Block / FVG，价格回踩时需观察买盘反应。"></option>
                            <option value="上方阻力区存在空头 Order Block / FVG，价格反弹时需观察承压反应。"></option>
                            <option value="当前结构中 OB / FVG 位置不明显，需等待价格重新建立有效区域。"></option>
                            <option value="价格正在回补 FVG，需等待收线确认后再判断方向。"></option>
                        </datalist>
                        @error('order_block') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="ma-panel">
                <div class="ma-panel-head">
                    <div class="d-flex gap-3">
                        <span class="ma-section-icon"><i class="fas fa-file-alt"></i></span>
                        <div>
                            <h5 class="ma-panel-title">中文分析内容</h5>
                            <p class="ma-panel-subtitle">根据趋势、动能、OB/FVG 与支撑阻力区自动生成，可保留手动修改内容。</p>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="market_overview" class="ma-field-label">市场概况</label>
                    <textarea name="market_overview" id="market_overview" class="form-control @error('market_overview') is-invalid @enderror">{{ old('market_overview', $analysis->market_overview ?? '') }}</textarea>
                    @error('market_overview') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="support_zones_helper" class="ma-field-label">支撑区 Supports</label>
                            <input type="text" id="support_zones_helper" class="form-control" placeholder="例如：4461, 4410, 4361">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="resistance_zones_helper" class="ma-field-label">阻力区 Resistances</label>
                            <input type="text" id="resistance_zones_helper" class="form-control" placeholder="例如：4600, 4670, 4750">
                        </div>
                    </div>
                    <label for="key_zones" class="ma-field-label">关键区间</label>
                    <textarea name="key_zones" id="key_zones" class="form-control mono @error('key_zones') is-invalid @enderror" placeholder="关键阻力区：4600, 4670, 4750&#10;关键支撑区：4461, 4410, 4361">{{ old('key_zones', $analysis->key_zones ?? '') }}</textarea>
                    @error('key_zones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="entry_zones_description" class="ma-field-label">进场区 / 风险区</label>
                    <textarea name="entry_zones_description" id="entry_zones_description" class="form-control mono @error('entry_zones_description') is-invalid @enderror" placeholder="🏹 进场区 / 风险区 (Entry Zones)&#10;⬆️ 做多进场区（Buy Zones）">{{ old('entry_zones_description', $analysis->entry_zones_description ?? '') }}</textarea>
                    @error('entry_zones_description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="analyst_view" class="ma-field-label">分析师观点</label>
                    <textarea name="analyst_view" id="analyst_view" class="form-control @error('analyst_view') is-invalid @enderror">{{ old('analyst_view', $analysis->analyst_view ?? '') }}</textarea>
                    @error('analyst_view') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="strategy" class="ma-field-label">策略 / 操作建议</label>
                    <textarea name="strategy" id="strategy" class="form-control @error('strategy') is-invalid @enderror">{{ old('strategy', $analysis->strategy ?? '') }}</textarea>
                    @error('strategy') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="chart_signals" class="ma-field-label">图表信号总结</label>
                    <textarea name="chart_signals" id="chart_signals" class="form-control @error('chart_signals') is-invalid @enderror">{{ old('chart_signals', $analysis->chart_signals ?? '') }}</textarea>
                    @error('chart_signals') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            </div>

            <div class="ma-panel">
                <div class="ma-panel-head">
                    <div class="d-flex gap-3">
                        <span class="ma-section-icon"><i class="fas fa-tasks"></i></span>
                        <div>
                            <h5 class="ma-panel-title">交易执行计划</h5>
                            <p class="ma-panel-subtitle">进场条件、止损、止盈、失效条件与风险控制。</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="maPlanTemplateButton">
                        加载模板
                    </button>
                </div>

                <textarea name="trading_plan" id="trading_plan" class="form-control ma-plan-editor @error('trading_plan') is-invalid @enderror">{{ old('trading_plan', $analysis->trading_plan ?? '') }}</textarea>
                @error('trading_plan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </div>

        <div class="col-xl-4">
            <div class="ma-side-panel mb-3">
                <div class="ma-kicker">发布设定</div>
                <h5 class="ma-panel-title mt-1">市场展望图片</h5>

                @if($analysis && $analysis->outlook_image && file_exists(public_path($analysis->outlook_image)))
                    <img src="{{ asset($analysis->outlook_image) }}" alt="{{ $analysis->title }}" class="ma-preview-image my-3">
                @endif

                <div class="mb-3 mt-3">
                        <label for="outlook_image" class="ma-field-label">图表图片</label>
                    <input type="file" name="outlook_image" id="outlook_image" class="form-control @error('outlook_image') is-invalid @enderror" accept="image/*">
                    @error('outlook_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                @if($analysis)
                    <div class="border-top pt-3 mt-3">
                        <div class="small text-muted">Outlook Code</div>
                        <div class="fw-bold">{{ $analysis->Outlook_Code ?? '-' }}</div>
                    </div>
                @endif

                <div class="border-top pt-3 mt-3">
                    <ul class="ma-checklist">
                        <li><i class="fas fa-check-circle"></i><span>关键区间需清楚、简洁、方便会员阅读。</span></li>
                        <li><i class="fas fa-check-circle"></i><span>发布前请确认进场区、风险区与失效条件。</span></li>
                        <li><i class="fas fa-check-circle"></i><span>最终审核后再同步到 Discord。</span></li>
                    </ul>
                </div>
            </div>

            <div class="ma-side-panel">
                <div class="ma-kicker">保存状态</div>
                <h5 class="ma-panel-title mt-1">{{ $mode === 'edit' ? '保存更新' : '创建报告' }}</h5>
                <p class="ma-panel-subtitle">更新后报告会重新标记为待发送，方便再次同步 Discord。</p>
                <div class="ma-actions mt-3">
                    <a href="{{ route('market-analyst.index') }}" class="btn btn-light">取消</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> {{ $mode === 'edit' ? '更新分析' : '创建分析' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const trendInput = document.getElementById('trend_structure');
    const strengthInput = document.getElementById('trend_strength');
    const marketInput = document.getElementById('market');
    const titleInput = document.getElementById('title');
    const overviewInput = document.getElementById('market_overview');
    const keyZonesInput = document.getElementById('key_zones');
    const supportZonesInput = document.getElementById('support_zones_helper');
    const resistanceZonesInput = document.getElementById('resistance_zones_helper');
    const entryZonesInput = document.getElementById('entry_zones_description');
    const analystViewInput = document.getElementById('analyst_view');
    const strategyInput = document.getElementById('strategy');
    const chartSignalsInput = document.getElementById('chart_signals');
    const rsiInput = document.getElementById('rsi_level');
    const orderBlockInput = document.getElementById('order_block');
    const planInput = document.getElementById('trading_plan');
    const smartFillFields = [
        titleInput,
        overviewInput,
        keyZonesInput,
        entryZonesInput,
        analystViewInput,
        strategyInput,
        chartSignalsInput,
        rsiInput,
        orderBlockInput,
        planInput
    ];

    const structureCopy = {
        uptrend: '市场目前维持多头结构，高点与低点持续抬升，买盘仍然占据主导。只要关键支撑区没有被有效跌破，整体思路以顺势做多为主。',
        downtrend: '市场目前维持空头结构，高点不断下移，低点同步走低，卖压仍然占据主导。只要关键阻力区没有被有效突破，整体思路以顺势做空为主。',
        ranging: '市场目前处于震荡区间运行，价格在支撑与阻力之间来回测试，方向暂时不明确。操作上以区间边缘确认信号为主，等待突破后再跟随新方向。'
    };

    const strengthCopy = {
        strong: '趋势强度偏强，动能延续概率较高，可优先关注顺势延续机会。',
        medium: '趋势强度中等，市场节奏仍可操作，但需要等待明确确认后再执行。',
        weak: '趋势强度偏弱，动能不足，容易出现假突破或反复震荡，需降低仓位并严格控制风险。'
    };

    const planTemplate = [
        '交易执行计划',
        '',
        '方向 Bias：',
        '主要结构：',
        '失效条件：',
        '',
        '计划 A - 顺势延续',
        '进场：',
        '止损：',
        '止盈：',
        '理由：',
        '',
        '计划 B - 回调进场',
        '进场：',
        '止损：',
        '止盈：',
        '理由：',
        '',
        '计划 C - 拒绝进场 / 不交易',
        '条件：',
        '处理方式：',
        '',
        '风险管理',
        '单笔最大风险：',
        '必须确认信号：',
        '交易时段备注：'
    ].join('\n');

    function splitZoneLevels(value) {
        return (value || '')
            .replace(/[：;]/g, ':')
            .split(/[,\n，、\s]+/)
            .map(function (level) { return level.trim(); })
            .filter(function (level) { return /^-?\d+(\.\d+)?$/.test(level); });
    }

    function parseZoneLine(text, labels) {
        const lines = (text || '').split(/\n/);
        const matchedLine = lines.find(function (line) {
            return labels.some(function (label) {
                return line.toLowerCase().includes(label.toLowerCase());
            });
        });

        if (!matchedLine) {
            return [];
        }

        return splitZoneLevels(matchedLine.replace(/^.*?[:：]/, ''));
    }

    function zoneLevels() {
        const fallbackSupports = ['4461', '4410', '4361'];
        const fallbackResistances = ['4600', '4670', '4750'];
        const keyZoneText = keyZonesInput.value;

        const supports = splitZoneLevels(supportZonesInput.value);
        const resistances = splitZoneLevels(resistanceZonesInput.value);

        const parsedSupports = parseZoneLine(keyZoneText, ['支撑', 'support']);
        const parsedResistances = parseZoneLine(keyZoneText, ['阻力', 'resistance']);

        return {
            supports: supports.length ? supports : (parsedSupports.length ? parsedSupports : fallbackSupports),
            resistances: resistances.length ? resistances : (parsedResistances.length ? parsedResistances : fallbackResistances)
        };
    }

    function levelAt(levels, index) {
        return levels[index] || levels[levels.length - 1] || '-';
    }

    function zoneTemplate() {
        const levels = zoneLevels();
        const supports = levels.supports.length ? levels.supports : ['4461', '4410', '4361'];
        const resistances = levels.resistances.length ? levels.resistances : ['4600', '4670', '4750'];

        return {
            key: '关键阻力区：' + resistances.join(', ') + '\n关键支撑区：' + supports.join(', '),
            entry: [
                '🏹 进场区 / 风险区 (Entry Zones)',
                '⬆️ 做多进场区（Buy Zones）',
                '🔴 高风险：' + levelAt(supports, 0),
                '🟡 中风险：' + levelAt(supports, 1),
                '🟢 低风险：' + levelAt(supports, 2),
                '',
                '🔻 做空进场区（Sell Zones）',
                '🔴 高风险：' + levelAt(resistances, 0),
                '🟡 中风险：' + levelAt(resistances, 1),
                '🟢 低风险：' + levelAt(resistances, 2)
            ].join('\n')
        };
    }

    function buildDraftValues() {
        const trend = trendInput.value || 'ranging';
        const strength = strengthInput.value || 'medium';
        const market = (marketInput.value || 'MARKET').toUpperCase();
        const zones = zoneTemplate();
        const trendLabel = {
            uptrend: '多头',
            downtrend: '空头',
            ranging: '震荡'
        }[trend];
        const defaultRsi = {
            uptrend: 'RSI 位于中位偏上，多头动能占优。',
            downtrend: 'RSI 位于中位偏下，空头动能占优。',
            ranging: 'RSI 处于中性区域，市场动能暂时平衡。'
        }[trend];
        const defaultOrderBlock = {
            uptrend: '下方支撑区存在多头 Order Block / FVG，价格回踩时需观察买盘反应。',
            downtrend: '上方阻力区存在空头 Order Block / FVG，价格反弹时需观察承压反应。',
            ranging: '当前结构中 OB / FVG 位置不明显，需等待价格重新建立有效区域。'
        }[trend];
        const rsiText = rsiInput.value.trim() || defaultRsi;
        const orderBlockText = orderBlockInput.value.trim() || defaultOrderBlock;

        return {
            title: market + ' 市场方向策略',
            overview: structureCopy[trend] + ' ' + strengthCopy[strength] + '\n\n动能观察：' + rsiText + '\n技术区域：' + orderBlockText,
            keyZones: zones.key,
            entryZones: zones.entry,
            analystView: '当前分析师观点偏向' + trendLabel + '结构。操作上需要等待价格回到关键区间后，再根据收线、动能与反应确认是否执行，避免在区间中间追单。',
            strategy: '操作建议：\n1. 优先观察关键支撑与阻力区的价格反应。\n2. 若价格到达进场区，需等待确认信号后再执行。\n3. 若价格突破失效条件，则放弃原计划并重新评估市场结构。\n4. 每笔交易必须严格控制风险，不建议无确认追价。',
            chartSignals: '图表信号总结：' + rsiText + ' ' + orderBlockText + ' 重点关注价格在关键支撑 / 阻力区的反应、突破后的回踩确认，以及是否出现假突破。',
            rsi: rsiText,
            orderBlock: orderBlockText,
            plan: planTemplate
        };
    }

    function smartFill(input, value) {
        if (!input || !value) {
            return false;
        }

        const currentValue = input.value;
        const previousAutoValue = input.dataset.maAutoValue || '';
        const adminTouched = input.dataset.maTouched === '1';

        if (!adminTouched && (currentValue.trim() === '' || currentValue === previousAutoValue)) {
            input.value = value;
            input.dataset.maAutoValue = value;
            return true;
        }

        return false;
    }

    function syncZoneHelpersFromKeyZones(force = false) {
        const supports = parseZoneLine(keyZonesInput.value, ['支撑', 'support']);
        const resistances = parseZoneLine(keyZonesInput.value, ['阻力', 'resistance']);

        if (supports.length && (force || !supportZonesInput.value.trim())) {
            supportZonesInput.value = supports.join(', ');
        }

        if (resistances.length && (force || !resistanceZonesInput.value.trim())) {
            resistanceZonesInput.value = resistances.join(', ');
        }
    }

    function applyTrendAutofill() {
        if (!trendInput.value) {
            return;
        }

        const draft = buildDraftValues();

        smartFill(titleInput, draft.title);
        smartFill(overviewInput, draft.overview);
        if (smartFill(keyZonesInput, draft.keyZones)) {
            syncZoneHelpersFromKeyZones();
        }
        smartFill(entryZonesInput, draft.entryZones);
        smartFill(analystViewInput, draft.analystView);
        smartFill(strategyInput, draft.strategy);
        smartFill(chartSignalsInput, draft.chartSignals);
        smartFill(rsiInput, draft.rsi);
        smartFill(orderBlockInput, draft.orderBlock);
    }

    function draftReport() {
        applyTrendAutofill();
        smartFill(planInput, buildDraftValues().plan);
    }

    smartFillFields.forEach(function (field) {
        if (!field) {
            return;
        }

        field.addEventListener('input', function () {
            this.dataset.maTouched = '1';
        });
    });

    syncZoneHelpersFromKeyZones();

    trendInput.addEventListener('change', applyTrendAutofill);
    strengthInput.addEventListener('change', applyTrendAutofill);
    marketInput.addEventListener('input', applyTrendAutofill);
    rsiInput.addEventListener('input', applyTrendAutofill);
    orderBlockInput.addEventListener('input', applyTrendAutofill);
    supportZonesInput.addEventListener('input', applyTrendAutofill);
    resistanceZonesInput.addEventListener('input', applyTrendAutofill);
    keyZonesInput.addEventListener('input', function () {
        syncZoneHelpersFromKeyZones(true);
        applyTrendAutofill();
    });

    document.getElementById('maDraftButton').addEventListener('click', draftReport);
    document.getElementById('maPlanTemplateButton').addEventListener('click', function () {
        smartFill(planInput, planTemplate);
        planInput.focus();
    });
});
</script>
