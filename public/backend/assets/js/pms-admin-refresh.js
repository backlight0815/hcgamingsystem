(() => {
    for (const form of document.querySelectorAll('.pm-account-form, .pm-account-edit-form')) {
        const department = form.querySelector('[data-account-department]');
        const position = form.querySelector('[data-account-position]');
        if (!department || !position) continue;
        const refresh = () => {
            const id = department.selectedOptions[0]?.dataset.id;
            for (const option of position.options) {
                if (!option.value) continue;
                option.disabled = Boolean(id) && !option.dataset.departments?.split(',').includes(id);
                option.hidden = option.disabled;
            }
            if (position.selectedOptions[0]?.disabled) position.value = '';
        };
        department.addEventListener('change', refresh);
        refresh();
    }

    const search = document.querySelector('.pm-account-filters input[name="q"]');
    if (search) {
        let timer;
        let composing = false;
        search.addEventListener('compositionstart', () => { composing = true; });
        search.addEventListener('compositionend', () => { composing = false; search.dispatchEvent(new Event('input')); });
        search.addEventListener('input', () => {
            clearTimeout(timer);
            if (!composing) timer = setTimeout(() => search.form.requestSubmit(), 500);
        });
    }

    for (const form of document.querySelectorAll('.pm-account-form:not([data-confirm])')) {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type=submit],button:not([type])');
            if (button) button.disabled = true;
        }, { once: true });
    }

    const forms = [...document.querySelectorAll('form[data-confirm]')];
    if (!forms.length) return;
    const dialog = document.createElement('dialog');
    dialog.className = 'pm-feedback-dialog';
    dialog.setAttribute('aria-labelledby', 'pm-confirm-title');
    dialog.innerHTML = '<h2 id="pm-confirm-title">Confirm account action</h2><p id="pm-confirm-message"></p><div class="pm-feedback-actions"><button type="button" class="pm-button secondary" id="pm-confirm-cancel">Cancel</button><button type="button" class="pm-button" id="pm-confirm-accept">Confirm</button></div>';
    document.body.append(dialog);
    const cancel = dialog.querySelector('#pm-confirm-cancel');
    const accept = dialog.querySelector('#pm-confirm-accept');
    let pending = null;
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { pending = null; });
    accept.addEventListener('click', () => {
        if (!pending) return;
        const form = pending;
        dialog.close();
        form.dataset.confirmed = 'true';
        form.requestSubmit();
    });
    forms.forEach(form => form.addEventListener('submit', event => {
        if (form.dataset.confirmed === 'true') {
            const button = form.querySelector('button[type=submit],button:not([type])');
            if (button) button.disabled = true;
            return;
        }
        event.preventDefault();
        pending = form;
        dialog.querySelector('#pm-confirm-message').textContent = form.dataset.confirm;
        dialog.showModal();
        cancel.focus();
    }));
})();
