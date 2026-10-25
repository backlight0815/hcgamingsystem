document.querySelectorAll('[data-org-picker]').forEach(picker => {
    const search = picker.querySelector('[data-org-search]');
    const select = picker.querySelector('[data-org-select]');
    const status = picker.querySelector('[data-org-status]');
    if (!search || !select || !status) return;
    const options = [...select.options].filter(option => option.value);
    const dependency = select.dataset.orgDepends ? document.getElementById(select.dataset.orgDepends) : null;
    let touched = false;
    const update = () => {
        picker.setAttribute('aria-busy', 'true');
        const term = search.value.trim().toLocaleLowerCase();
        const department = dependency?.value || '';
        let visible = 0;
        for (const option of options) {
            const matches = (!dependency || (department && (option.dataset.departments || '').split(',').includes(department))) &&
                (!term || option.textContent.toLocaleLowerCase().includes(term));
            option.hidden = !matches;
            option.disabled = !matches;
            if (matches) visible++;
        }
        if (select.selectedOptions[0]?.disabled) select.value = '';
        status.textContent = !touched && picker.dataset.error ? picker.dataset.error : dependency && !department
            ? 'Select a department first.' : visible ? `${visible} option${visible === 1 ? '' : 's'} available.` : 'No matching options.';
        picker.setAttribute('aria-busy', 'false');
    };
    search.addEventListener('input', () => { touched = true; update(); });
    search.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'Enter') {
            event.preventDefault();
            select.focus();
            if (event.key === 'Enter' && !select.value) {
                const first = options.find(option => !option.disabled);
                if (first) {
                    select.value = first.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }
    });
    dependency?.addEventListener('change', () => { touched = true; update(); });
    select.addEventListener('change', () => {
        touched = true;
        if (select.value) status.textContent = `${select.selectedOptions[0].textContent} selected.`;
    });
    update();
});
