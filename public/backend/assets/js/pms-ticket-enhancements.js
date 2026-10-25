(() => {
    document.querySelectorAll('[data-reviewer-search]').forEach(search => {
        const select = search.closest('label')?.querySelector('[data-reviewer-select]');
        if (!select) return;
        search.addEventListener('input', () => {
            const term = search.value.trim().toLocaleLowerCase();
            for (const option of select.options) {
                if (!option.value) continue;
                const matches = option.textContent.toLocaleLowerCase().includes(term);
                option.hidden = option.disabled || !matches;
            }
        });
    });

    document.querySelectorAll('.pm-test-dropzone').forEach(zone => {
        const input = zone.querySelector('input[type=file]');
        const selected = zone.closest('form, section')?.querySelector('[data-test-selected]');
        const render = () => {
            if (selected) selected.textContent = Array.from(input?.files || []).map(file => file.name).join(' · ');
        };
        input?.addEventListener('change', render);
        zone.addEventListener('dragover', event => { event.preventDefault(); zone.classList.add('is-dragging'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('is-dragging'));
        zone.addEventListener('drop', event => {
            event.preventDefault(); zone.classList.remove('is-dragging');
            if (input && event.dataTransfer?.files) { input.files = event.dataTransfer.files; render(); }
        });
    });
    document.querySelectorAll('[data-test-case-remove]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm('Remove this file from current test cases? Its version and review history will be retained.')) event.preventDefault();
    }));

    const dialog = document.getElementById('pm-ticket-delete-dialog');
    const form = dialog?.querySelector('[data-delete-form]');
    const input = dialog?.querySelector('[data-delete-confirm]');
    const submit = dialog?.querySelector('[data-delete-submit]');
    const opener = document.querySelector('[data-delete-open]');
    if (!dialog || !form || !input || !submit || !opener) return;
    let processing = false;
    const sync = () => { submit.disabled = processing || input.value !== form.dataset.reference; };
    opener.addEventListener('click', () => { input.value = ''; sync(); dialog.showModal(); input.focus(); });
    dialog.querySelector('[data-delete-cancel]')?.addEventListener('click', () => { if (!processing) dialog.close(); });
    dialog.addEventListener('cancel', event => { if (processing) event.preventDefault(); });
    dialog.addEventListener('close', () => opener.focus());
    dialog.addEventListener('click', event => { if (event.target === dialog && !processing) dialog.close(); });
    input.addEventListener('input', sync);
    form.addEventListener('submit', event => {
        if (processing || input.value !== form.dataset.reference) { event.preventDefault(); sync(); return; }
        processing = true; sync(); submit.textContent = 'Moving ticket…';
    });
})();
