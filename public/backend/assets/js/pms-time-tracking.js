(() => {
    document.querySelectorAll('[data-time-export]').forEach(link => link.addEventListener('click', event => {
        if (link.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
        const label = link.innerHTML;
        link.setAttribute('aria-busy', 'true');
        link.setAttribute('aria-disabled', 'true');
        link.textContent = 'Preparing CSV…';
        setTimeout(() => {
            link.innerHTML = label;
            link.removeAttribute('aria-busy');
            link.removeAttribute('aria-disabled');
        }, 3000);
    }));
    const timerPanel = document.querySelector('.pm-time-timer');
    const sessionScript = document.querySelector('script[src*="pms-session.js"]');
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel('pms-work-timer') : null;
    const syncTimer = async () => {
        if (!timerPanel || !sessionScript) return;
        try {
            const response = await fetch(sessionScript.dataset.statusUrl, {
                credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            });
            if (!response.ok) return;
            const timer = (await response.json()).timer;
            if ((timer?.started_at || '') !== timerPanel.dataset.timerStarted ||
                (timer?.status || '') !== timerPanel.dataset.timerStatus) window.location.reload();
        } catch (_) { /* Recover on the next check after a network interruption. */ }
    };
    channel?.addEventListener('message', event => { if (event.data?.type === 'changed') setTimeout(syncTimer, 700); });
    document.querySelectorAll('form[action*="/time-tracking/timer"]').forEach(form => {
        form.addEventListener('submit', () => channel?.postMessage({type: 'changed'}));
    });
    setInterval(syncTimer, 20000);
    const project = document.querySelector('[data-time-project]');
    const ticket = document.querySelector('[data-time-ticket]');
    const syncTickets = () => {
        if (!project || !ticket) return;
        for (const option of ticket.options) {
            if (!option.value) continue;
            option.hidden = option.dataset.project !== project.value;
            option.disabled = option.hidden;
        }
        if (ticket.selectedOptions[0]?.disabled) ticket.value = '';
    };
    project?.addEventListener('change', syncTickets);
    syncTickets();
    const details = document.getElementById('pm-time-details');
    document.querySelectorAll('[data-time-detail]').forEach(button => {
        button.addEventListener('click', () => {
            if (!details) return;
            for (const key of ['date', 'project', 'ticket', 'type', 'duration', 'description']) {
                const value = details.querySelector(`[data-detail-value="${key}"]`);
                if (value) value.textContent = button.dataset[key] || (key === 'description' ? 'No description provided' : '—');
            }
            details.showModal();
        });
    });
    details?.querySelector('[data-time-close]')?.addEventListener('click', () => details.close());
    details?.addEventListener('click', event => { if (event.target === details) details.close(); });
    const clock = document.querySelector('.pm-time-clock');
    if (!clock) return;
    const render = () => {
        const base = Number(clock.dataset.duration || 0);
        const resumed = Number(clock.dataset.resumed || 0);
        const seconds = base + (resumed ? Math.max(0, Math.floor(Date.now() / 1000) - resumed) : 0);
        clock.textContent = [Math.floor(seconds / 3600), Math.floor(seconds % 3600 / 60), seconds % 60]
            .map(value => String(value).padStart(2, '0')).join(':');
    };
    render();
    setInterval(render, 1000);
})();
