document.querySelectorAll('[data-people-form]').forEach(form => {
    const search = form.querySelector('[data-people-search]');
    const department = form.querySelector('[data-people-department]');
    const position = form.querySelector('[data-people-position]');
    const select = form.querySelector('[data-people-select]');
    const role = form.querySelector('[data-people-role]');
    const selected = form.querySelector('[data-people-selected]');
    if (!search || !department || !select || !selected) return;

    const options = [...select.options];
    const empty = document.createElement('p');
    empty.className = 'pm-muted';
    empty.setAttribute('role', 'status');
    empty.hidden = true;
    select.after(empty);

    const update = () => {
        const term = search.value.trim().toLowerCase();
        let matches = 0;
        for (const option of options) {
            const visible = (!term || option.dataset.search.includes(term)) &&
                (!department.value || option.dataset.department === department.value) &&
                (!position?.value || option.dataset.position === position.value);
            option.hidden = !visible;
            option.disabled = !visible && !option.selected;
            if (visible) matches++;
        }
        empty.hidden = matches > 0;
        empty.textContent = 'No people match these filters.';

        const chosen = options.filter(option => option.selected);
        selected.replaceChildren();
        if (!chosen.length) {
            selected.textContent = 'No people selected';
            return;
        }
        const count = document.createElement('strong');
        count.textContent = chosen.length + (chosen.length === 1 ? ' person selected' : ' people selected');
        selected.appendChild(count);
        for (const option of chosen) {
            const chip = document.createElement('span');
            chip.className = 'pm-people-chip';
            chip.append(document.createTextNode(option.textContent.trim() + ' '));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'pm-people-remove';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'Remove ' + option.textContent.trim());
            remove.addEventListener('click', () => {
                option.selected = false;
                update();
                select.focus();
            });
            chip.appendChild(remove);
            selected.appendChild(chip);
        }
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'pm-link';
        clear.textContent = 'Clear selections';
        clear.addEventListener('click', () => {
            for (const option of chosen) option.selected = false;
            update();
            select.focus();
        });
        selected.appendChild(clear);
    };

    [search, department, position, select, role].filter(Boolean).forEach(control => {
        control.addEventListener('input', update);
        control.addEventListener('change', update);
    });
    form.addEventListener('submit', event => {
        const count = options.filter(option => option.selected).length;
        if (count === 0 || (count > 1 && !window.confirm('Assign ' + count + ' selected people?'))) {
            event.preventDefault();
        }
    });
    update();
});
