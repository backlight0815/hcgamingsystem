(() => {
    const tabs = document.querySelector('[data-pm-tabs]');
    if (!tabs) return;
    const links = [...tabs.querySelectorAll('[data-pm-tab-link]')];
    const panels = [...document.querySelectorAll('[data-pm-tab-panel]')];
    const names = new Set(panels.map(panel => panel.dataset.pmTabPanel));
    const targetForHash = () => {
        const id = decodeURIComponent(location.hash.slice(1));
        const target = id ? document.getElementById(id) : null;
        return target?.closest('[data-pm-tab-panel]')?.dataset.pmTabPanel || (names.has(id) ? id : 'overview');
    };
    const activate = (name, scroll = false) => {
        if (!names.has(name)) name = 'overview';
        for (const panel of panels) panel.hidden = panel.dataset.pmTabPanel !== name;
        for (const link of links) {
            const selected = link.dataset.pmTabLink === name;
            link.setAttribute('aria-selected', String(selected));
            link.tabIndex = selected ? 0 : -1;
            link.classList.toggle('active', selected);
        }
        if (scroll) tabs.scrollIntoView({ block: 'start', behavior: 'smooth' });
    };
    for (const link of links) link.addEventListener('click', event => {
        event.preventDefault();
        const name = link.dataset.pmTabLink;
        history.replaceState(null, '', `#${name}`);
        activate(name, true);
    });
    tabs.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const current = links.indexOf(document.activeElement);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? links.length - 1
            : (current + (event.key === 'ArrowRight' ? 1 : -1) + links.length) % links.length;
        links[next]?.focus();
        links[next]?.click();
    });
    document.querySelectorAll('[data-pm-go]').forEach(link => link.addEventListener('click', event => {
        const name = link.dataset.pmGo;
        if (!names.has(name)) return;
        event.preventDefault();
        history.replaceState(null, '', `#${name}`);
        activate(name, true);
    }));
    window.addEventListener('hashchange', () => activate(targetForHash()));
    activate(targetForHash());
    if (location.hash) requestAnimationFrame(() => document.getElementById(decodeURIComponent(location.hash.slice(1)))?.scrollIntoView({ block: 'start' }));
})();
