document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('pm-cycle-project-search');
    const select = document.getElementById('pm-cycle-project-options');
    if (!search || !select) return;

    const options = [...select.options].map(option => ({
        value: option.value,
        label: option.textContent,
    }));
    const selected = new Set();

    select.addEventListener('change', () => {
        for (const option of select.options) {
            if (option.selected) selected.add(option.value);
            else selected.delete(option.value);
        }
    });

    search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase();
        select.replaceChildren(...options
            .filter(option => selected.has(option.value) || option.label.toLocaleLowerCase().includes(term))
            .map(option => {
                const item = new Option(option.label, option.value);
                item.selected = selected.has(option.value);
                return item;
            }));
    });
});
