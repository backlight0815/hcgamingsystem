document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('pm-cycle-project-search');
    const select = document.getElementById('pm-cycle-project-options');
    const more = document.getElementById('pm-cycle-project-more');
    const help = document.getElementById('pm-cycle-project-help');
    if (!search || !select) return;

    const selected = new Map([...select.selectedOptions].map(option => [option.value, option.textContent]));
    const initial = [...select.options].map(option => ({ id: option.value, label: option.textContent }));
    let page = 1;
    let lastPage = 1;
    let timer;
    let controller;
    let sequence = 0;

    const render = rows => {
        const choices = new Map([...selected].map(([id, label]) => [id, label]));
        rows.forEach(row => choices.set(String(row.id), row.label));
        select.replaceChildren(...[...choices].map(([id, label]) => {
            const option = new Option(label, id);
            option.selected = selected.has(id);
            return option;
        }));
    };
    const load = async (nextPage = 1) => {
        controller?.abort();
        controller = new AbortController();
        const requestNumber = ++sequence;
        help.textContent = 'Loading eligible projects…';
        more.hidden = true;
        try {
            const url = new URL(search.dataset.url, window.location.href);
            url.searchParams.set('q', search.value.trim());
            url.searchParams.set('page', String(nextPage));
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) throw new Error('Project search failed. Refresh and retry.');
            const result = await response.json();
            if (requestNumber !== sequence) return;
            page = result.current_page;
            lastPage = result.last_page;
            const rows = result.data.map(project => ({ id: project.id,
                label: `${project.project_reference} · ${project.name} · ${project.status}${project.assigned_here_count ? ' · Already assigned' : ''}${project.current_global_cycle ? ' · Current: ' + project.current_global_cycle.cycle_key : ''}` }));
            render(nextPage === 1 ? rows : [...[...select.options].filter(option => !selected.has(option.value)).map(option => ({ id: option.value, label: option.textContent })), ...rows]);
            more.hidden = page >= lastPage;
            help.textContent = rows.length ? `Page ${page} of ${lastPage}. Selected projects remain available while searching.` : 'No eligible projects match this search.';
        } catch (error) {
            if (error.name !== 'AbortError') help.textContent = error.message;
        }
    };
    select.addEventListener('change', () => {
        [...select.options].forEach(option => {
            if (option.selected) selected.set(option.value, option.textContent);
            else selected.delete(option.value);
        });
    });
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => search.value.trim() ? load(1) : (render(initial), help.textContent = 'Select more projects to add. Existing selections remain assigned.', more.hidden = true), 250);
    });
    more?.addEventListener('click', () => { if (page < lastPage) load(page + 1); });
});
