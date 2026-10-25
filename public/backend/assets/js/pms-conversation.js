(() => {
    const root = document.querySelector('[data-pms-tabs]');
    if (!root) return;
    const tabs = [...root.querySelectorAll('[data-pms-tab]')];
    const panels = [...root.querySelectorAll('[data-pms-tab-panel]')];
    function activate(id) {
        if (!tabs.some(tab => tab.dataset.pmsTab === id)) id = 'discussion';
        for (const tab of tabs) {
            const active = tab.dataset.pmsTab === id;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        }
        for (const panel of panels) panel.hidden = panel.dataset.pmsTabPanel !== id;
    }
    function fromHash() {
        const hash = location.hash.slice(1);
        activate(hash === 'activity' || hash === 'attachments' ? hash : 'discussion');
        if (hash.startsWith('comment-')) document.getElementById(hash)?.scrollIntoView();
    }
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => { location.hash = tab.dataset.pmsTab; activate(tab.dataset.pmsTab); });
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const next = tabs[(index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
            next.focus(); next.click();
        });
    });
    window.addEventListener('hashchange', fromHash);
    fromHash();
})();
