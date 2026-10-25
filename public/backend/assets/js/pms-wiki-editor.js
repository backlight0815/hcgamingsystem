(() => {
    const field = document.getElementById('pm-wiki-body');
    const panel = document.getElementById('pm-wiki-preview');
    const toggle = document.getElementById('pm-wiki-preview-toggle');
    if (!field || !panel || !toggle) return;

    const status = document.getElementById('pm-kb-status');
    status?.addEventListener('change', () => {
        document.querySelectorAll('.pm-kb-workflow > div').forEach(step => {
            step.classList.toggle('active', step.querySelector('strong')?.textContent.toLowerCase() === status.value);
        });
    });

    let timer;
    let pending;
    const render = async () => {
        pending?.abort();
        pending = new AbortController();
        const target = panel.querySelector('.pm-wiki-article');
        target.textContent = 'Rendering preview…';
        try {
            const response = await fetch(panel.dataset.previewUrl, {
                method: 'POST',
                credentials: 'same-origin',
                signal: pending.signal,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ content: field.value }),
            });
            if (!response.ok) throw new Error('Preview could not be rendered.');
            target.innerHTML = (await response.json()).html;
        } catch (error) {
            if (error.name !== 'AbortError') target.textContent = error.message;
        }
    };
    const schedule = () => {
        if (panel.hidden) return;
        clearTimeout(timer);
        timer = setTimeout(render, 250);
    };

    document.querySelectorAll('.pm-wiki-toolbar button[data-wiki-insert], .pm-wiki-toolbar button[data-wiki-wrap], .pm-wiki-toolbar button[data-wiki-before]').forEach(button => {
        button.addEventListener('click', () => {
            const start = field.selectionStart;
            const end = field.selectionEnd;
            const selection = field.value.slice(start, end);
            const wrap = button.dataset.wikiWrap;
            const insert = button.dataset.wikiInsert;
            const before = button.dataset.wikiBefore;
            const after = button.dataset.wikiAfter;
            field.setRangeText(wrap ? wrap + selection + wrap : before !== undefined
                ? before + (selection || 'text') + after : insert + selection, start, end, 'end');
            field.focus();
            schedule();
        });
    });
    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
        if (panel.hidden) pending?.abort();
        else render();
    });
    field.addEventListener('input', schedule);
})();
