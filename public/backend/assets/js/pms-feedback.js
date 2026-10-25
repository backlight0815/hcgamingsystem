(() => {
    const dialog = document.getElementById('pm-feedback-dialog');
    if (!dialog) return;
    const title = document.getElementById('pm-feedback-title');
    const message = document.getElementById('pm-feedback-message');
    const details = document.getElementById('pm-feedback-details');
    const action = document.getElementById('pm-feedback-action');
    const close = document.getElementById('pm-feedback-close');
    close.addEventListener('click', () => dialog.close());
    window.PmsFeedback = {
        show(payload = {}) {
            const permission = payload.error === 'PERMISSION_DENIED' || payload.code === 'FORBIDDEN';
            title.textContent = payload.heading || (permission ? 'Permission required' : 'Action not available');
            message.textContent = payload.message || 'The request could not be completed. Please try again.';
            details.replaceChildren();
            for (const [key, value] of Object.entries(payload.details || {})) {
                if (value == null || value === '') continue;
                const term = document.createElement('dt');
                term.textContent = key.replaceAll('_', ' ');
                const description = document.createElement('dd');
                description.textContent = String(value);
                details.append(term, description);
            }
            action.hidden = !payload.reviewUrl;
            if (payload.reviewUrl) action.href = payload.reviewUrl;
            dialog.showModal();
            close.focus();
        },
    };
})();
