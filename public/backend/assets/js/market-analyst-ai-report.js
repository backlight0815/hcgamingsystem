(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.querySelector('.ma-form-shell[data-ai-report-endpoint]');
        if (!form) return;
        const fields = {
            title: true, market_overview: true, rsi_level: true, order_block: false,
            key_zones: true, entry_zones_description: true, analyst_view: true,
            strategy: true, chart_signals: true, trading_plan: true
        };
        const contextIds = ['market', 'analysis_date', 'trend_structure', 'trend_strength',
            'support_zones_helper', 'resistance_zones_helper', ...Object.keys(fields)];
        const status = document.getElementById('maReportStatus');
        const stale = document.getElementById('maReportStale');
        const preview = document.getElementById('maReportPreview');
        const diffs = document.getElementById('maReportDiffs');
        const warnings = document.getElementById('maReportWarnings');
        const previewButton = document.getElementById('maReportPreviewButton');
        let pending = null;
        let lastApplied = null;
        let busy = false;
        let lastAction = null;

        const input = id => document.getElementById(id);
        const value = id => (input(id)?.value || '').trim();
        const snapshot = () => JSON.stringify(contextIds.map(value));
        function payload(action) {
            const data = { mode: action.mode, section: action.section || null,
                field: action.field || null, improve_style: action.mode === 'improve' ? document.getElementById('maImproveStyle').value : null,
                market: value('market'), analysis_date: value('analysis_date'),
                trend_structure: value('trend_structure'), trend_strength: value('trend_strength'),
                supports: value('support_zones_helper'), resistances: value('resistance_zones_helper') };
            Object.keys(fields).forEach(key => { data[key] = value(key); });
            return data;
        }
        function message(text, error) {
            status.textContent = text;
            status.style.color = error ? '#b91c1c' : '#334155';
        }
        function addFieldControls() {
            Object.entries(fields).forEach(([id, canGenerate]) => {
                const label = form.querySelector('label[for="' + id + '"]');
                if (!label) return;
                const group = document.createElement('span');
                group.className = 'ma-field-ai';
                if (canGenerate) {
                    const generate = document.createElement('button');
                    generate.type = 'button'; generate.className = 'btn btn-outline-primary';
                    generate.textContent = '✨ AI Generate';
                    generate.setAttribute('aria-label', 'Generate ' + label.textContent.trim());
                    generate.addEventListener('click', () => request({ mode: 'field', field: id }));
                    group.append(generate);
                }
                const improve = document.createElement('button');
                improve.type = 'button'; improve.className = 'btn btn-outline-secondary';
                improve.textContent = 'Improve';
                improve.setAttribute('aria-label', 'Improve ' + label.textContent.trim());
                improve.addEventListener('click', () => request({ mode: 'improve', field: id }));
                group.append(improve);
                label.after(group);
            });
        }
        function renderPreview(values, original) {
            diffs.replaceChildren();
            Object.entries(values).forEach(([id, proposed]) => {
                const item = document.createElement('div'); item.className = 'ma-report-diff';
                const heading = document.createElement('label'); heading.className = 'd-flex align-items-center gap-2 fw-bold';
                const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = true;
                checkbox.dataset.applyField = id;
                heading.append(checkbox, document.createTextNode(form.querySelector('label[for="' + id + '"]')?.firstChild?.textContent?.trim() || id));
                item.append(heading);
                const before = document.createElement('pre'); before.textContent = 'Original: ' + (original[id] || '(empty)');
                const after = document.createElement('pre'); after.textContent = 'AI suggestion: ' + proposed;
                item.append(before, after); diffs.append(item);
            });
            preview.hidden = false;
            previewButton.disabled = false;
            preview.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
        async function request(action) {
            if (busy) return;
            const data = payload(action);
            if (action.mode === 'improve' && !data[action.field]) {
                message('Enter content in this field before improving it.', true); return;
            }
            lastAction = action;
            const before = snapshot();
            const original = Object.fromEntries(Object.keys(fields).map(id => [id, value(id)]));
            busy = true; pending = null; preview.hidden = true; previewButton.disabled = true;
            form.querySelectorAll('[data-report-mode], .ma-field-ai button').forEach(button => { button.disabled = true; });
            message('Generating a reviewable AI draft…', false);
            try {
                const response = await fetch(form.dataset.aiReportEndpoint, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || Object.values(result.errors || {})[0]?.[0] || 'Generation failed.');
                if (before !== snapshot()) throw new Error('The form changed while AI was generating. Generate again using the latest values.');
                const values = { ...result.fields };
                if (result.plan) {
                    if (!window.MarketAnalystPlan) throw new Error('Plan formatter unavailable. Reload and retry.');
                    values.trading_plan = window.MarketAnalystPlan.serializePlan(result.plan);
                }
                pending = { values, original, snapshot: before };
                warnings.replaceChildren();
                (result.warnings || []).forEach(text => {
                    const li = document.createElement('li'); li.textContent = text; warnings.append(li);
                });
                renderPreview(values, original);
                message('AI draft ready. Select and review the fields before applying.', false);
            } catch (error) { message(error.message || 'Generation failed.', true); }
            finally {
                busy = false;
                form.querySelectorAll('[data-report-mode], .ma-field-ai button').forEach(button => { button.disabled = false; });
            }
        }
        function apply(selectedOnly) {
            if (!pending) return;
            if (pending.snapshot !== snapshot()) {
                message('The form changed since this preview. Generate a fresh draft.', true); return;
            }
            Object.entries(pending.values).forEach(([id, text]) => {
                if (selectedOnly && !diffs.querySelector('[data-apply-field="' + id + '"]')?.checked) return;
                const target = input(id); if (!target) return;
                target.value = text; target.classList.add('ma-ai-generated');
                target.dispatchEvent(new Event('input', { bubbles: true }));
            });
            lastApplied = snapshot(); stale.hidden = true;
            pending = null; preview.hidden = true; previewButton.disabled = true;
            message('Selected suggestions applied to the form. Save only after reviewing the final report.', false);
        }
        addFieldControls();
        form.querySelectorAll('[data-report-mode]').forEach(button => button.addEventListener('click', () =>
            request({ mode: button.dataset.reportMode,
                section: button.dataset.reportMode === 'section' ? document.getElementById('maReportSection').value : null })));
        previewButton.addEventListener('click', () => { if (pending) preview.hidden = false; });
        document.getElementById('maReportApplyAll').addEventListener('click', () => apply(false));
        document.getElementById('maReportApplySelected').addEventListener('click', () => apply(true));
        document.getElementById('maReportKeep').addEventListener('click', () => {
            pending = null; preview.hidden = true; previewButton.disabled = true;
            message('Original form values kept.', false);
        });
        document.getElementById('maReportRegenerate').addEventListener('click', () => lastAction && request(lastAction));
        contextIds.forEach(id => input(id)?.addEventListener('input', () => {
            if (lastApplied && snapshot() !== lastApplied) stale.hidden = false;
        }));
    });
})();
