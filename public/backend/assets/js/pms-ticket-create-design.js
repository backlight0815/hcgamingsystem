(() => {
    const form = document.getElementById('pm-ticket-create');
    if (!form) return;

    const summary = {
        type: document.getElementById('pm-create-summary-type'),
        project: document.getElementById('pm-create-summary-project'),
        priority: document.getElementById('pm-create-summary-priority'),
        owner: document.getElementById('pm-create-summary-owner'),
    };
    const selectedText = (selector, fallback) => {
        const control = form.querySelector(selector);
        return control?.selectedOptions?.[0]?.textContent?.trim() || fallback;
    };
    const shortProject = value => value?.split(' · ')[0]?.trim() || value;

    function updateSummary() {
        summary.type.textContent = form.querySelector('[name="classification_key"]')?.value || '—';
        const project = form.querySelector('#pm-project');
        const contextProject = form.querySelector('.pm-context-project p');
        summary.project.textContent = shortProject(project?.value
            ? selectedText('#pm-project', '—')
            : contextProject?.textContent?.trim() || (project?.selectedOptions?.[0]?.textContent?.trim() || '—'));
        summary.priority.textContent = selectedText('[name="priority"]', '—');
        const developer = form.querySelector('[name="developer_id"]');
        const qa = form.querySelector('[name="qa_id"]');
        const selfQa = form.querySelector('.pm-qat-self-qa:not([hidden]) p');
        summary.owner.textContent = developer?.value
            ? selectedText('[name="developer_id"]', 'Unassigned').split(/\s[—-]\s/)[0]
            : selfQa?.textContent?.trim()?.split(/\s[—-]\s/)[0]
                || (qa?.value ? selectedText('[name="qa_id"]', 'Unassigned').split(/\s[—-]\s/)[0] : 'Unassigned');
    }

    form.addEventListener('change', () => requestAnimationFrame(updateSummary));
    updateSummary();
})();
