document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach(form => {
        const cycle = form.querySelector('[data-planning-select="cycle"]');
        const release = form.querySelector('[data-planning-select="release"]');
        if (!cycle || !release) return;
        const options = {
            cycle: [...cycle.options].map(option => ({ value: option.value, label: option.textContent })),
            release: [...release.options].map(option => ({ value: option.value, label: option.textContent, cycle: option.dataset.cycle || '' })),
        };
        const refresh = () => {
            [...release.options].forEach(option => {
                const compatible = !option.dataset.cycle || option.dataset.cycle === cycle.value;
                option.disabled = !compatible && !option.selected;
                option.title = compatible ? '' : 'Choose this release’s Development Cycle first';
            });
        };
        ['cycle', 'release'].forEach(kind => {
            const input = form.querySelector(`[data-planning-search="${kind}"]`);
            const target = kind === 'cycle' ? cycle : release;
            input?.addEventListener('input', () => {
                const chosen = target.value;
                const term = input.value.trim().toLocaleLowerCase();
                target.replaceChildren(...options[kind].filter(option => !term || !option.value || option.label.toLocaleLowerCase().includes(term) || option.value === chosen)
                    .map(option => {
                        const element = new Option(option.label, option.value);
                        if (kind === 'release') element.dataset.cycle = option.cycle;
                        element.selected = option.value === chosen;
                        return element;
                    }));
                refresh();
            });
        });
        cycle.addEventListener('change', refresh);
        refresh();
    });
});
