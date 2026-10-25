(() => {
    const dialog = document.getElementById('pm-cycle-create-dialog');
    const open = () => dialog?.showModal();
    document.querySelector('[data-cycle-create-open]')?.addEventListener('click', open);
    document.querySelectorAll('[data-cycle-create-close]').forEach(button => button.addEventListener('click', () => dialog?.close()));
    dialog?.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    const template = document.getElementById('cycle-template');
    const mode = document.getElementById('cycle-mode');
    const applyTemplate = () => {
        const weeks = Number(template?.value);
        if (!weeks) return;
        const unit = mode?.value === 'working' ? 5 : 7;
        document.getElementById('dev-days').value = 2 * unit;
        document.getElementById('test-days').value = unit;
        document.getElementById('qat-days').value = (weeks >= 5 ? 2 : 1) * unit;
        document.getElementById('buffer-days').value = weeks === 6 ? unit : 0;
    };
    template?.addEventListener('change', applyTemplate);
    mode?.addEventListener('change', applyTemplate);
    if (dialog && document.querySelector('#cycle-create [aria-invalid="true"]')) open();
})();
