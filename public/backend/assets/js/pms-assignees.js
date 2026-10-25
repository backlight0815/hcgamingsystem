(() => {
    const url = document.currentScript?.dataset.url;
    if (!url) return;
    const fields = [...document.querySelectorAll('select[data-assignment-type]')];
    const timers = new WeakMap();
    const controllers = new WeakMap();
    const statuses = new WeakMap();
    function projectId(select) {
        const form = select.closest('form');
        return form?.querySelector('[name="project_id"]')?.value || select.dataset.projectId || '';
    }
    async function refresh(select) {
        const project = projectId(select);
        const search = select.closest('label')?.querySelector('.pm-assignee-search')?.value.trim() || '';
        const selected = select.value;
        const legacy = [...select.options].find(option => option.value === selected && /legacy assignment|existing assignment/.test(option.textContent));
        const status = statuses.get(select);
        controllers.get(select)?.abort();
        if (!project) {
            select.replaceChildren(new Option('Choose a project first', ''));
            status.textContent = 'Choose a project to find eligible members.';
            return;
        }
        status.textContent = 'Loading eligible members…';
        select.setAttribute('aria-busy', 'true');
        const controller = new AbortController();
        controllers.set(select, controller);
        const params = new URLSearchParams({ assignment_type: select.dataset.assignmentType, project_id: project, search });
        try {
            const response = await fetch(`${url}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal });
            if (!response.ok) throw new Error('Could not load eligible members.');
            const payload = await response.json();
            if (controllers.get(select) !== controller) return;
            select.replaceChildren(new Option('Unassigned', ''));
            for (const user of payload.data || []) {
                const option = new Option(user.display_name, user.user_id);
                option.dataset.project = project;
                select.add(option);
            }
            if (legacy && ![...select.options].some(option => option.value === legacy.value)) select.add(legacy);
            select.value = [...select.options].some(option => option.value === selected) ? selected : '';
            if ((payload.data || []).length === 0 && !legacy) select.options[0].textContent = 'No eligible members';
            status.textContent = (payload.data || []).length ? `${payload.data.length} eligible member${payload.data.length === 1 ? '' : 's'} found.` : 'No eligible members found.';
        } catch (error) {
            if (error.name !== 'AbortError') status.textContent = 'Could not load eligible members. Try the search again.';
        } finally {
            if (controllers.get(select) === controller) select.removeAttribute('aria-busy');
        }
    }
    for (const select of fields) {
        const search = select.closest('label')?.querySelector('.pm-assignee-search');
        const status = document.createElement('small');
        status.className = 'pm-assignee-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        select.insertAdjacentElement('afterend', status);
        statuses.set(select, status);
        search?.addEventListener('input', () => {
            clearTimeout(timers.get(select));
            timers.set(select, setTimeout(() => refresh(select), 250));
        });
        const project = select.closest('form')?.querySelector('select[name="project_id"]');
        project?.addEventListener('change', () => {
            select.value = select.dataset.assignmentType === 'manager'
                ? project.selectedOptions[0]?.dataset.manager || '' : '';
            refresh(select);
        });
        if (projectId(select)) refresh(select);
    }
})();
