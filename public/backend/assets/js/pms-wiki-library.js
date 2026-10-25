(() => {
    const preview = document.querySelector('.pm-kb-preview');
    const rows = [...document.querySelectorAll('[data-kb-preview]')];
    if (preview) {
        const fields = {
            title: preview.querySelector('[data-kb-preview-title]'),
            category: preview.querySelector('[data-kb-preview-category]'),
            author: preview.querySelector('[data-kb-preview-author]'),
            date: preview.querySelector('[data-kb-preview-date]'),
            summary: preview.querySelector('[data-kb-preview-summary]'),
            link: preview.querySelector('[data-kb-preview-link]'),
        };
        rows.forEach(row => {
            const select = () => {
                rows.forEach(item => item.classList.toggle('selected', item === row));
                for (const key of ['title', 'category', 'author', 'date', 'summary']) {
                    fields[key].textContent = row.dataset[`preview${key[0].toUpperCase()}${key.slice(1)}`] || '';
                }
                fields.link.href = row.href;
            };
            row.addEventListener('mouseenter', select);
            row.addEventListener('focus', select);
        });
    }

    const form = document.querySelector('.pm-kb-search');
    const input = document.getElementById('pm-kb-search-input');
    const suggestions = document.getElementById('pm-kb-suggestions');
    if (!form || !input || !suggestions) return;
    let timer;
    let pending;

    const close = () => {
        suggestions.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    };
    const search = async () => {
        pending?.abort();
        const query = input.value.trim();
        if (!query) { close(); return; }
        pending = new AbortController();
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form)).toString();
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: pending.signal });
            if (!response.ok) throw new Error('Search unavailable');
            const result = await response.json();
            const pages = result.pages?.data || [];
            suggestions.replaceChildren();
            const heading = document.createElement('small');
            heading.textContent = 'SUGGESTED RESULTS';
            suggestions.append(heading);
            if (!pages.length) {
                const empty = document.createElement('p');
                empty.textContent = 'No matching articles. Try another keyword.';
                suggestions.append(empty);
            }
            for (const page of pages.slice(0, 5)) {
                const link = document.createElement('a');
                link.href = form.dataset.pageUrlTemplate.replace('__id__', encodeURIComponent(page.id));
                link.setAttribute('role', 'option');
                link.setAttribute('aria-selected', 'false');
                const title = document.createElement('strong');
                title.textContent = page.title;
                const detail = document.createElement('span');
                detail.textContent = [page.category, page.status].filter(Boolean).join(' · ');
                link.append(title, detail);
                suggestions.append(link);
            }
            suggestions.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        } catch (error) {
            if (error.name !== 'AbortError') close();
        }
    };
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 180); });
    input.addEventListener('focus', () => { if (input.value.trim()) search(); });
    input.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
        if (event.key === 'ArrowDown' && !suggestions.hidden) {
            event.preventDefault();
            suggestions.querySelector('a')?.focus();
        }
    });
    suggestions.addEventListener('keydown', event => {
        const links = [...suggestions.querySelectorAll('a')];
        const index = links.indexOf(document.activeElement);
        if (event.key === 'Escape') { close(); input.focus(); }
        if (event.key === 'ArrowDown' && index < links.length - 1) { event.preventDefault(); links[index + 1].focus(); }
        if (event.key === 'ArrowUp') { event.preventDefault(); (links[index - 1] || input).focus(); }
    });
    document.addEventListener('click', event => { if (!form.contains(event.target)) close(); });
})();
