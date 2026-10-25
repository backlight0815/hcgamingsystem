(() => {
    'use strict';

    const root = document.getElementById('trading-module-management');
    if (!root) return;

    const byId = (id) => document.getElementById(id);
    const initial = JSON.parse(byId('thm-initial-data').textContent);
    const state = {
        modules: initial.modules,
        domains: initial.domains,
        selectedKey: window.matchMedia('(max-width: 900px)').matches ? null : (initial.modules.some((module) => module.key === 'market_analyst') ? 'market_analyst' : (initial.modules[0]?.key ?? null)),
        draftEnabled: null,
        filter: 'all',
        query: '',
        saving: false,
        confirming: false,
        lastFocus: null,
    };

    const icons = {
        trader_centre: 'ri-bar-chart-grouped-line', trading_exams: 'ri-question-answer-line',
        signal_provider_desk: 'ri-broadcast-line', market_analyst: 'ri-line-chart-line',
        marketing_resources: 'ri-file-text-line', trading_news: 'ri-newspaper-line',
        community: 'ri-group-line', support_tickets: 'ri-customer-service-2-line',
        ai_assistant: 'ri-robot-line', appointments: 'ri-calendar-check-line',
        notifications: 'ri-notification-3-line', trading_signals: 'ri-signal-tower-line',
        trading_certificates: 'ri-award-line', my_certificates: 'ri-medal-line',
        knowledge_centre: 'ri-book-open-line', leaderboard: 'ri-trophy-line',
        trading_journal: 'ri-booklet-line', trading_statistics: 'ri-pie-chart-line',
        ai_market_insight: 'ri-lightbulb-line', prop_firm: 'ri-building-line',
        backtest_lab: 'ri-test-tube-line', position_centre: 'ri-stack-line',
        recording_classes: 'ri-video-line', trading_blog: 'ri-article-line',
        trading_pair: 'ri-exchange-line', readiness_checklist: 'ri-task-line',
        traders_performance: 'ri-funds-line', provider_accounts: 'ri-user-settings-line',
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);
    const selected = () => state.modules.find((module) => module.key === state.selectedKey) ?? null;
    const displayedEnabled = (module) => module.key === state.selectedKey && state.draftEnabled !== null ? state.draftEnabled : module.enabled;
    const dirty = () => selected() && state.draftEnabled !== null && state.draftEnabled !== selected().enabled;
    const badge = (enabled) => `<span class="thm-badge${enabled ? '' : ' is-inactive'}">${enabled ? 'Active' : 'Inactive'}</span>`;
    const icon = (module, detail = false) => `<span class="${detail ? 'thm-detail-icon' : 'thm-module-icon'}" aria-hidden="true"><i class="${icons[module.key] || 'ri-apps-line'}"></i></span>`;

    function announce(message, type) {
        byId('thm-announcement').textContent = message;
        if (window.toastr && type) window.toastr[type](message);
    }

    function renderSummary() {
        const active = state.modules.filter((module) => module.enabled).length;
        const total = state.modules.length;
        const title = byId('thm-summary-title');
        const description = byId('thm-summary-description');
        const indicator = byId('thm-summary-icon');
        title.textContent = total && active === total ? 'All modules active' : active ? 'Modules active' : 'No active modules';
        description.textContent = `${active} of ${total} TradingHub modules are currently active.`;
        indicator.className = `thm-summary-icon${active === total && total ? '' : active ? ' is-partial' : ' is-empty'}`;
        indicator.innerHTML = `<i class="${active === total && total ? 'ri-check-line' : active ? 'ri-information-line' : 'ri-subtract-line'}"></i>`;
    }

    function matches(module) {
        return window.TradingModuleFilters.matches(module, state.query, state.filter, state.domains, displayedEnabled(module));
    }

    function renderDomains() {
        const container = byId('thm-domains');
        let matched = 0;
        container.innerHTML = Object.entries(state.domains).map(([domainKey, domain]) => {
            const modules = state.modules.filter((module) => module.domain === domainKey && matches(module))
                .sort((first, second) => first.order - second.order);
            if (!modules.length) return '';
            matched += modules.length;
            return `<section class="thm-domain-card" aria-label="${escapeHtml(domain.label)}">
                <div class="thm-domain-head"><span class="thm-domain-icon" aria-hidden="true"><i class="${escapeHtml(domain.icon)}"></i></span><div><h2>${escapeHtml(domain.label)}</h2><p>${escapeHtml(domain.description)}</p></div></div>
                ${modules.map((module) => {
                    const enabled = displayedEnabled(module);
                    const isSelected = module.key === state.selectedKey;
                    return `<div class="thm-module-row${isSelected ? ' is-selected' : ''}" data-module-row="${escapeHtml(module.key)}">
                        <button type="button" class="thm-module-select" data-select="${escapeHtml(module.key)}" aria-label="View ${escapeHtml(module.label)} settings" ${isSelected ? 'aria-current="true"' : ''}>
                            ${icon(module)}<span class="thm-module-copy"><strong>${escapeHtml(module.label)}</strong><small>${escapeHtml(module.description)}</small></span>
                        </button>
                        <div class="thm-module-trailing">${isSelected && dirty() ? '<span class="thm-pending">Unsaved</span>' : ''}${badge(enabled)}
                            <button type="button" class="thm-switch" role="switch" aria-checked="${enabled}" aria-label="Enable ${escapeHtml(module.label)}" data-toggle="${escapeHtml(module.key)}" ${state.saving ? 'disabled' : ''}></button>
                        </div>
                    </div>`;
                }).join('')}
            </section>`;
        }).join('');
        if (!matched) {
            container.innerHTML = `<div class="thm-empty"><i class="ri-search-line" aria-hidden="true"></i><h2>No modules found</h2><p>Try adjusting your search or filters.</p><button type="button" class="thm-button thm-button-secondary" id="thm-clear-filters">Clear filters</button></div>`;
        }
    }

    function renderDetail() {
        const module = selected();
        const detail = byId('thm-detail');
        byId('thm-workspace').classList.toggle('no-detail', !module);
        detail.hidden = !module;
        if (!module) {
            byId('thm-drawer-backdrop').hidden = true;
            return;
        }
        const enabled = displayedEnabled(module);
        const parent = module.parent ? state.modules.find((item) => item.key === module.parent) : null;
        const parentNotice = parent && !parent.enabled ? `<p class="thm-parent-notice">${escapeHtml(parent.label)} is disabled, so this module is currently unavailable even if its own setting is enabled.</p>` : '';
        detail.innerHTML = `<div class="thm-detail-top">${icon(module, true)}<button type="button" class="thm-icon-button" id="thm-close-detail" aria-label="Close module details"><i class="ri-close-line" aria-hidden="true"></i></button></div>
            <div class="thm-detail-title"><h2>${escapeHtml(module.label)}</h2>${badge(enabled)}${dirty() ? '<span class="thm-pending">Unsaved changes</span>' : ''}</div>
            <p class="thm-detail-description">${escapeHtml(module.description)}</p>
            <div class="thm-detail-info"><dl><dt>Domain</dt><dd>${escapeHtml(state.domains[module.domain]?.label || 'Trading Workspace')}</dd><dt>Scope</dt><dd>TradingHub only</dd><dt>Purpose</dt><dd>${escapeHtml(module.purpose)}</dd></dl></div>
            <div class="thm-settings"><h3>Module settings</h3><div class="thm-setting-row"><strong>Enable module</strong><button type="button" class="thm-switch" role="switch" aria-checked="${enabled}" aria-label="Enable ${escapeHtml(module.label)}" id="thm-detail-toggle" ${state.saving ? 'disabled' : ''}></button></div>
            <p class="thm-setting-help">Allow users to access this module in TradingHub.</p>${parentNotice}</div>
            <div class="thm-notice"><i class="ri-information-line" aria-hidden="true"></i><span>Disabling this module will hide it from navigation and prevent user access. Existing data and settings will be retained.</span></div>
            <div class="thm-detail-actions"><button type="button" class="thm-button thm-button-secondary" id="thm-cancel" ${state.saving ? 'disabled' : ''}>Cancel</button><button type="button" class="thm-button thm-button-primary" id="thm-save" ${!dirty() || state.saving ? 'disabled' : ''}>${state.saving ? 'Saving…' : 'Save changes'}</button></div>`;
        syncDrawer();
    }

    function syncDrawer() {
        byId('thm-drawer-backdrop').hidden = !selected() || !window.matchMedia('(max-width: 900px)').matches;
    }

    function render() {
        renderSummary();
        renderDomains();
        renderDetail();
        byId('thm-clear-search').hidden = !state.query;
        root.querySelectorAll('[data-filter]').forEach((button) => {
            const active = button.dataset.filter === state.filter;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', String(active));
        });
    }

    function leaveDraft() {
        if (!dirty()) return true;
        return window.confirm(`Discard unsaved changes to ${selected().label}?`);
    }

    function selectModule(key) {
        if (state.saving || key === state.selectedKey) return;
        if (!leaveDraft()) return;
        state.selectedKey = key;
        state.draftEnabled = null;
        render();
        if (window.matchMedia('(max-width: 900px)').matches) byId('thm-close-detail')?.focus();
    }

    function toggleModule(key) {
        if (state.saving) return;
        if (key !== state.selectedKey) {
            if (!leaveDraft()) return;
            state.selectedKey = key;
            state.draftEnabled = null;
        }
        const module = selected();
        state.draftEnabled = !displayedEnabled(module);
        render();
        byId('thm-detail-toggle')?.focus();
    }

    function closeDetail() {
        if (state.saving || !leaveDraft()) return;
        state.selectedKey = null;
        state.draftEnabled = null;
        render();
    }

    function openConfirmation() {
        const module = selected();
        if (!module || !dirty() || state.saving) return;
        if (state.draftEnabled) {
            persist();
            return;
        }
        state.lastFocus = document.activeElement;
        state.confirming = true;
        byId('thm-dialog-title').textContent = `Disable ${module.label}?`;
        byId('thm-modal-backdrop').hidden = false;
        byId('thm-dialog-cancel').focus();
    }

    function closeConfirmation() {
        if (state.saving) return;
        state.confirming = false;
        byId('thm-modal-backdrop').hidden = true;
        state.lastFocus?.focus();
    }

    async function persist() {
        const module = selected();
        if (!module || !dirty() || state.saving) return;
        const nextEnabled = state.draftEnabled;
        state.saving = true;
        render();
        byId('thm-dialog-confirm').disabled = true;
        byId('thm-dialog-confirm').textContent = 'Saving…';
        try {
            const response = await fetch(root.dataset.updateUrl.replace('__module__', encodeURIComponent(module.key)), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': byId('thm-csrf').value },
                body: JSON.stringify({
                    enabled: nextEnabled ? '1' : '0',
                    expected_enabled: module.enabled ? '1' : '0',
                    expected_updated_at: module.updated_at,
                }),
            });
            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                throw new Error(response.status === 409 ? data.message : 'Unable to update module settings. Please try again.');
            }
            const saved = await response.json();
            module.enabled = saved.enabled;
            module.updated_at = saved.updated_at;
            state.draftEnabled = null;
            state.confirming = false;
            byId('thm-modal-backdrop').hidden = true;
            const refresh = await fetch(root.dataset.indexUrl, { headers: { 'Accept': 'application/json' } });
            if (!refresh.ok) throw new Error('The change was saved, but the latest settings could not be loaded. Please refresh this page.');
            const latest = await refresh.json();
            state.modules = latest.modules;
            state.domains = latest.domains;
            announce(`${module.label} has been ${nextEnabled ? 'enabled' : 'disabled'} successfully.`, 'success');
        } catch (error) {
            announce(error.message || 'Unable to update module settings. Please try again.', 'error');
        } finally {
            state.saving = false;
            byId('thm-dialog-confirm').disabled = false;
            byId('thm-dialog-confirm').textContent = 'Disable module';
            render();
            if (state.confirming) byId('thm-dialog-cancel').focus();
            else byId('thm-detail-toggle')?.focus();
        }
    }

    root.addEventListener('click', (event) => {
        const filter = event.target.closest('[data-filter]');
        if (filter) { state.filter = filter.dataset.filter; render(); return; }
        const select = event.target.closest('[data-select]');
        if (select) { selectModule(select.dataset.select); return; }
        const toggle = event.target.closest('[data-toggle]');
        if (toggle) { toggleModule(toggle.dataset.toggle); return; }
        if (event.target.closest('#thm-detail-toggle')) { toggleModule(state.selectedKey); return; }
        if (event.target.closest('#thm-close-detail') || event.target.closest('#thm-drawer-backdrop')) { closeDetail(); return; }
        if (event.target.closest('#thm-cancel')) { state.draftEnabled = null; closeDetail(); return; }
        if (event.target.closest('#thm-save')) { openConfirmation(); return; }
        if (event.target.closest('#thm-dialog-cancel') || event.target.closest('#thm-dialog-close')) { closeConfirmation(); return; }
        if (event.target.closest('#thm-dialog-confirm')) { persist(); return; }
        if (event.target.closest('#thm-clear-search')) {
            clearTimeout(searchTimer);
            state.query = '';
            byId('thm-search').value = '';
            render();
            byId('thm-search').focus();
            return;
        }
        if (event.target.closest('#thm-clear-filters')) {
            clearTimeout(searchTimer);
            state.query = '';
            state.filter = 'all';
            byId('thm-search').value = '';
            render();
            byId('thm-search').focus();
        }
    });

    let searchTimer;
    byId('thm-search').addEventListener('input', (event) => {
        const value = event.target.value;
        byId('thm-clear-search').hidden = !value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { state.query = value; render(); }, 120);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (state.confirming) closeConfirmation();
            else if (window.matchMedia('(max-width: 900px)').matches && selected()) closeDetail();
        }
        if (event.key === 'Tab' && state.confirming) {
            const focusable = [...byId('thm-modal-backdrop').querySelectorAll('button:not(:disabled)')];
            if (event.shiftKey && document.activeElement === focusable[0]) { event.preventDefault(); focusable.at(-1).focus(); }
            else if (!event.shiftKey && document.activeElement === focusable.at(-1)) { event.preventDefault(); focusable[0].focus(); }
        }
    });
    window.addEventListener('resize', syncDrawer);
    window.addEventListener('beforeunload', (event) => {
        if (dirty() && !state.saving) { event.preventDefault(); event.returnValue = ''; }
    });
    render();
})();
