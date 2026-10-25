(() => {
    const root = document.getElementById('pm-gantt-render');
    if (!root) return;
    const phases = JSON.parse(root.dataset.phases || '[]');
    const start = new Date(`${root.dataset.start}T00:00:00Z`);
    const end = new Date(`${root.dataset.end}T00:00:00Z`);
    const today = new Date(`${root.dataset.today}T00:00:00Z`);
    const day = 86400000;
    const days = Math.max(1, Math.round((end - start) / day) + 1);
    const formats = {
        daily: { step: 1, width: 38, label: d => d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', timeZone: 'UTC' }) },
        weekly: { step: 7, width: 14, label: d => `Week of ${d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', timeZone: 'UTC' })}` },
        monthly: { width: 6, label: d => d.toLocaleDateString(undefined, { month: 'short', year: 'numeric', timeZone: 'UTC' }) },
    };
    const date = value => new Date(`${value}T00:00:00Z`);
    const offset = value => Math.round((date(value) - start) / day);
    const make = (tag, className, content) => {
        const element = document.createElement(tag);
        element.className = className;
        if (content !== undefined) element.textContent = content;
        return element;
    };
    function render(view) {
        const config = formats[view] || formats.weekly;
        const width = Math.max(720, days * config.width);
        const grid = make('div', 'pm-gantt-grid');
        grid.style.setProperty('--pm-chart-width', `${width}px`);
        grid.style.setProperty('--pm-day-width', `${config.width}px`);
        const axis = make('div', 'pm-gantt-axis');
        axis.append(make('div', 'pm-gantt-label pm-gantt-axis-label', 'PHASE / OWNER'));
        const axisDates = make('div', 'pm-gantt-axis-dates');
        const ticks = [];
        if (view === 'monthly') {
            ticks.push(0);
            const month = new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + 1, 1));
            while (month <= end) {
                ticks.push(Math.round((month - start) / day));
                month.setUTCMonth(month.getUTCMonth() + 1);
            }
        } else {
            for (let n = 0; n < days; n += config.step) ticks.push(n);
        }
        for (const n of ticks) {
            const current = new Date(start.getTime() + n * day);
            const tick = make('span', 'pm-gantt-axis-tick', config.label(current));
            tick.style.left = `${n * config.width}px`;
            axisDates.append(tick);
        }
        axis.append(axisDates); grid.append(axis);
        for (const phase of phases) {
            const row = make('div', 'pm-gantt-row');
            const info = make('div', 'pm-gantt-label');
            const link = make('a', '', phase.name);
            link.href = `#phase-${phase.id}`;
            info.append(link, make('small', '', phase.owner ? `Cycle owner: ${phase.owner}` : 'Owner not assigned'));
            const badge = make('span', `pm-gantt-badge ${phase.delayed ? 'delayed' : ''}`,
                phase.signedOff || phase.progress === 100 ? 'Completed' : phase.delayed ? 'Delayed' : phase.actualStart ? 'In progress' : 'Upcoming');
            info.append(badge);
            const plot = make('div', 'pm-gantt-plot');
            const plannedLeft = Math.max(0, offset(phase.plannedStart)) * config.width;
            const plannedDays = Math.max(1, offset(phase.plannedEnd) - offset(phase.plannedStart) + 1);
            const bar = make('div', `pm-gantt-bar ${phase.delayed ? 'delayed' : ''}`);
            bar.style.left = `${plannedLeft}px`; bar.style.width = `${plannedDays * config.width}px`;
            bar.title = `${phase.name}: planned ${phase.plannedStart} to ${phase.plannedEnd}; ${phase.progress}% complete`;
            const fill = make('span', 'pm-gantt-bar-progress');
            fill.style.width = `${phase.progress}%`;
            bar.append(fill);
            if (plannedDays * config.width > 70) bar.append(make('strong', '', `${phase.progress}%`));
            plot.append(bar);
            if (phase.actualStart) {
                const actualLeft = Math.max(0, offset(phase.actualStart)) * config.width;
                const actualEnd = phase.actualEnd ? offset(phase.actualEnd) : Math.min(days - 1, Math.max(offset(phase.actualStart), Math.round((today - start) / day)));
                const actual = make('div', 'pm-gantt-actual');
                actual.style.left = `${actualLeft}px`;
                actual.style.width = `${Math.max(1, actualEnd - offset(phase.actualStart) + 1) * config.width}px`;
                actual.title = `Actual: ${phase.actualStart} to ${phase.actualEnd || 'ongoing'}`;
                plot.append(actual);
            }
            const milestone = make('span', 'pm-gantt-milestone');
            milestone.style.left = `${(offset(phase.plannedEnd) + 1) * config.width - 5}px`;
            milestone.title = `${phase.name} planned finish: ${phase.plannedEnd}`;
            plot.append(milestone);
            if (today >= start && today <= end) {
                const marker = make('span', 'pm-gantt-today');
                marker.style.left = `${Math.round((today - start) / day) * config.width}px`;
                marker.title = 'Today'; plot.append(marker);
            }
            row.append(info, plot); grid.append(row);
        }
        root.replaceChildren(grid);
        document.querySelectorAll('[data-timeline-view]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.dataset.timelineView === view));
        });
        try { localStorage.setItem('pms-timeline-view', view); } catch (_) {}
    }
    document.querySelectorAll('[data-timeline-view]').forEach(button =>
        button.addEventListener('click', () => render(button.dataset.timelineView)));
    let initial = 'weekly';
    try { initial = localStorage.getItem('pms-timeline-view') || initial; } catch (_) {}
    render(initial);
})();
