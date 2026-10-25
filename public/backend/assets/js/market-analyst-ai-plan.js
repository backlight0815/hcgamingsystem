(function () {
    'use strict';

    const sections = [
        { key: 'bias', title: '交易方向', fields: [['summary', '方向 Bias'], ['primaryStructure', '主要结构'], ['invalidationCondition', '失效条件']] },
        { key: 'planA', title: '计划 A - 顺势延续', fields: [['entry', '进场'], ['stopLoss', '止损'], ['takeProfit', '止盈'], ['reason', '理由']] },
        { key: 'planB', title: '计划 B - 回调进场', fields: [['entry', '进场'], ['stopLoss', '止损'], ['takeProfit', '止盈'], ['reason', '理由']] },
        { key: 'planC', title: '计划 C - 拒绝进场 / 不交易', fields: [['conditions', '条件'], ['action', '处理方式']] },
        { key: 'riskManagement', title: '风险管理', fields: [['maximumRisk', '单笔最大风险'], ['confirmationSignals', '必须确认信号'], ['tradingSessionNotes', '交易时段备注']] }
    ];
    const sourceIds = ['market', 'trend_structure', 'trend_strength', 'rsi_level', 'order_block', 'market_overview', 'support_zones_helper', 'resistance_zones_helper', 'key_zones', 'entry_zones_description', 'analyst_view', 'strategy', 'chart_signals'];

    function emptyPlan() {
        const plan = {};
        sections.forEach(section => {
            plan[section.key] = {};
            section.fields.forEach(([key]) => { plan[section.key][key] = ''; });
        });
        return plan;
    }

    function parsePlan(text) {
        if (!text.trim()) return emptyPlan();
        if (!text.includes('【AI交易执行计划】') && !text.startsWith('交易执行计划')) return null;
        const plan = emptyPlan();
        let section = null;
        let recognized = 0;
        let unknownContent = false;
        text.split(/\r?\n/).forEach(line => {
            const trimmed = line.trim();
            if (!trimmed || trimmed === '【AI交易执行计划】') return;
            if (trimmed === '交易执行计划') { section = sections[0]; return; }
            const heading = sections.find(item => line.trim() === '【' + item.title + '】' || line.trim() === item.title);
            if (heading) { section = heading; recognized++; return; }
            if (!section) { unknownContent = true; return; }
            const separator = line.indexOf('：');
            if (separator < 0) { unknownContent = true; return; }
            const label = line.slice(0, separator).trim();
            const field = section.fields.find(([, title]) => title === label);
            if (field) { plan[section.key][field[0]] = line.slice(separator + 1).trim(); recognized++; }
            else unknownContent = true;
        });
        return recognized && !unknownContent ? plan : null;
    }

    function mergePlan(original, generated, mode, selectedSection) {
        const candidate = JSON.parse(JSON.stringify(original || emptyPlan()));
        sections.forEach(item => {
            if (selectedSection && item.key !== selectedSection) return;
            item.fields.forEach(([key]) => {
                const proposed = generated?.[item.key]?.[key];
                if (typeof proposed !== 'string') return;
                if (mode === 'empty' && candidate[item.key][key].trim()) return;
                candidate[item.key][key] = proposed;
            });
        });
        return candidate;
    }

    function serializePlan(plan) {
        const lines = ['【AI交易执行计划】', ''];
        sections.forEach(section => {
            lines.push('【' + section.title + '】');
            section.fields.forEach(([key, label]) => {
                lines.push(label + '：' + String(plan[section.key][key] || '').replace(/\s+/g, ' ').trim());
            });
            lines.push('');
        });
        return lines.join('\n').trim();
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { parsePlan, serializePlan, mergePlan };
        return;
    }
    window.MarketAnalystPlan = { parsePlan, serializePlan, mergePlan };

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('.ma-form-shell[data-ai-plan-endpoint]');
        if (!form) return;
        const planInput = document.getElementById('trading_plan');
        const status = document.getElementById('maAiStatus');
        const preview = document.getElementById('maAiPreview');
        const previewGrid = document.getElementById('maAiPreviewGrid');
        const originalPreview = document.getElementById('maAiOriginal');
        const originalPreviewText = document.getElementById('maAiOriginalText');
        const warnings = document.getElementById('maAiWarnings');
        const previewButton = document.getElementById('maAiPreviewButton');
        const applyButton = document.getElementById('maAiApplyButton');
        const cancelButton = document.getElementById('maAiCancelButton');
        const sectionSelect = document.getElementById('maAiSection');
        const modeButtons = Array.from(form.querySelectorAll('[data-ai-mode]'));
        let pending = null;
        let requestNumber = 0;
        let busy = false;

        function source() {
            const value = id => (document.getElementById(id)?.value || '').trim();
            return {
                market: value('market'), trend_structure: value('trend_structure'), trend_strength: value('trend_strength'),
                rsi_level: value('rsi_level'), order_block: value('order_block'), market_overview: value('market_overview'),
                supports: value('support_zones_helper'), resistances: value('resistance_zones_helper'), key_zones: value('key_zones'),
                entry_zones_description: value('entry_zones_description'), analyst_view: value('analyst_view'),
                strategy: value('strategy'), chart_signals: value('chart_signals')
            };
        }

        function sourceSnapshot() { return JSON.stringify(sourceIds.map(id => document.getElementById(id)?.value || '')); }
        function showStatus(message, isError) {
            status.textContent = message;
            status.style.color = isError ? '#b91c1c' : '#334155';
        }
        function setBusy(value) {
            busy = value;
            modeButtons.forEach(button => { button.disabled = value; });
            sectionSelect.disabled = value;
            applyButton.disabled = value;
        }

        function renderPreview() {
            if (!pending) return;
            if (pending.snapshot !== sourceSnapshot() || pending.originalText !== planInput.value) {
                pending = null;
                preview.hidden = true;
                previewButton.disabled = true;
                showStatus('源数据或交易计划已改变。请重新生成后再预览。', true);
                return;
            }
            previewGrid.replaceChildren();
            warnings.replaceChildren();
            originalPreview.hidden = !pending.originalText.trim();
            originalPreviewText.textContent = pending.originalText;
            pending.warnings.forEach(message => {
                const item = document.createElement('li'); item.textContent = message; warnings.appendChild(item);
            });
            let changed = 0;
            sections.forEach(section => {
                section.fields.forEach(([key, label]) => {
                    const field = document.createElement('label');
                    const current = pending.originalPlan?.[section.key]?.[key] || '';
                    const proposed = pending.candidate[section.key][key] || '';
                    if (current !== proposed) { field.classList.add('ma-ai-changed'); changed++; }
                    field.textContent = section.title + ' · ' + label;
                    const textarea = document.createElement('textarea');
                    textarea.className = 'form-control';
                    textarea.dataset.section = section.key;
                    textarea.dataset.field = key;
                    textarea.value = proposed;
                    field.appendChild(textarea);
                    previewGrid.appendChild(field);
                });
            });
            applyButton.textContent = pending.originalText.trim() ? '确认并应用更改' : '应用到交易计划';
            preview.hidden = false;
            previewButton.disabled = false;
            showStatus(changed + ' 项拟更改。预览可编辑；应用前不会修改或保存报告。', false);
            preview.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        async function generate(mode) {
            if (busy) return;
            const originalText = planInput.value;
            const originalPlan = parsePlan(originalText);
            if (originalText.trim() && !originalPlan && mode !== 'all') {
                showStatus('现有交易计划不是结构化模板。请选择“AI 自动生成交易计划”预览完整替换；原文在确认前保持不变。', true);
                return;
            }
            const snapshot = sourceSnapshot();
            const payload = source();
            const section = mode === 'section' ? sectionSelect.value : null;
            if (section) payload.section = section;
            const currentRequest = ++requestNumber;
            pending = null;
            preview.hidden = true;
            previewButton.disabled = true;
            setBusy(true);
            showStatus('正在根据当前市场分析生成草稿，请稍候…', false);
            try {
                const response = await fetch(form.dataset.aiPlanEndpoint, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
                    body: JSON.stringify(payload)
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const validationMessage = result.errors && Object.values(result.errors).flat()[0];
                    throw new Error(validationMessage || result.message || 'AI 服务暂时不可用，请稍后重试。');
                }
                if (currentRequest !== requestNumber || snapshot !== sourceSnapshot() || originalText !== planInput.value) {
                    showStatus('生成期间表单内容已改变。请使用最新内容重新生成。', true);
                    return;
                }
                const candidate = mergePlan(originalPlan, result.plan, mode, section);
                pending = { candidate, originalPlan, originalText, snapshot, warnings: result.warnings || [] };
                renderPreview();
            } catch (error) {
                showStatus(error.message || 'AI 服务暂时不可用。表单内容未改变，请重试。', true);
            } finally {
                setBusy(false);
            }
        }

        modeButtons.forEach(button => button.addEventListener('click', () => generate(button.dataset.aiMode)));
        previewButton.addEventListener('click', renderPreview);
        cancelButton.addEventListener('click', () => {
            preview.hidden = true;
            showStatus('已取消应用。原有交易计划和市场分析保持不变。', false);
        });
        applyButton.addEventListener('click', () => {
            if (!pending) return;
            if (pending.snapshot !== sourceSnapshot() || pending.originalText !== planInput.value) {
                showStatus('源数据或交易计划已改变。请重新生成后再应用。', true);
                preview.hidden = true;
                pending = null;
                previewButton.disabled = true;
                return;
            }
            const edited = JSON.parse(JSON.stringify(pending.candidate));
            previewGrid.querySelectorAll('textarea[data-section][data-field]').forEach(field => {
                edited[field.dataset.section][field.dataset.field] = field.value.trim();
            });
            planInput.value = serializePlan(edited);
            planInput.dispatchEvent(new Event('input', { bubbles: true }));
            planInput.classList.add('ma-ai-generated');
            preview.hidden = true;
            pending = null;
            previewButton.disabled = true;
            showStatus('AI 草稿已应用到可编辑的交易计划。请审核后使用原有创建或更新按钮保存。', false);
            planInput.focus();
        });
    });
})();
