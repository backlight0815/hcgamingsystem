document.querySelectorAll('[data-person-picker]').forEach(form => {
    const search = form.querySelector('[data-picker-search]');
    const options = [...form.querySelectorAll('[data-picker-option]')];
    const boxes = options.map(option => option.querySelector('input[type="checkbox"]'));
    const count = form.querySelector('[data-picker-count]');
    const empty = form.querySelector('[data-picker-empty]');
    const update = () => {
        const term = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        options.forEach(option => {
            option.hidden = !option.dataset.search.includes(term);
            if (!option.hidden) visible++;
        });
        empty.hidden = visible > 0;
        const selected = boxes.filter(box => box.checked).length;
        count.textContent = `${selected} member${selected === 1 ? '' : 's'} selected`;
    };
    search.addEventListener('input', update);
    boxes.forEach(box => box.addEventListener('change', update));
    form.querySelector('[data-picker-clear]').addEventListener('click', () => {
        boxes.forEach(box => { box.checked = false; });
        update();
        search.focus();
    });
    form.querySelector('[data-picker-cancel]')?.addEventListener('click', () => {
        form.reset();
        search.value = '';
        update();
        form.closest('details').open = false;
    });
    update();
});
