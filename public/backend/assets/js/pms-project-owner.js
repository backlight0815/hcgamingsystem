(() => {
    document.querySelectorAll('[data-owner-search]').forEach(search => {
        const select = search.closest('label')?.querySelector('[data-owner-select]');
        if (!select) return;
        search.addEventListener('input', () => {
            const query = search.value.trim().toLocaleLowerCase();
            Array.from(select.options).forEach(option => {
                option.hidden = !!option.value && !!query && !option.textContent.toLocaleLowerCase().includes(query);
            });
        });
    });
})();
