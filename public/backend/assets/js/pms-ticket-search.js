(() => {
    const dialog = document.getElementById('pm-ticket-search');
    const query = document.getElementById('pm-ticket-search-query');
    const results = document.getElementById('pm-ticket-search-results');
    const target = document.getElementById('pm-link-target');
    if (!dialog || !query || !results || !target) return;
    document.querySelector('[data-ticket-search-open]')?.addEventListener('click', () => { dialog.showModal(); query.focus(); });
    let timer;
    query.addEventListener('input', () => {
        clearTimeout(timer);
        const term = query.value.trim();
        results.replaceChildren();
        if (term.length < 2) return;
        timer = setTimeout(async () => {
            const response = await fetch(`${dialog.dataset.searchUrl}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const payload = await response.json();
            if (query.value.trim() !== term) return;
            results.replaceChildren();
            (payload.data || []).forEach(ticket => {
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'pm-ticket-search-result';
                button.textContent = `${ticket.issue_key} · ${ticket.summary} · ${ticket.status}`;
                button.addEventListener('click', () => { target.value = ticket.issue_key; dialog.close(); target.focus(); });
                results.append(button);
            });
            if (!results.childNodes.length) results.textContent = 'No visible tickets match.';
        }, 250);
    });
})();
