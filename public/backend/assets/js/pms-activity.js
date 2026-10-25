(() => {
    const nodes = [...document.querySelectorAll('[data-activity-time]')];
    if (!nodes.length) return;
    const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const format = () => {
        const now = Date.now();
        for (const node of nodes) {
            const date = new Date(node.dataset.activityTime);
            if (Number.isNaN(date.getTime())) continue;
            const minutes = Math.round((date.getTime() - now) / 60000);
            const [amount, unit] = Math.abs(minutes) < 60
                ? [minutes, 'minute']
                : Math.abs(minutes) < 1440
                    ? [Math.round(minutes / 60), 'hour']
                    : [Math.round(minutes / 1440), 'day'];
            node.textContent = formatter.format(amount, unit);
            node.title = date.toLocaleString();
        }
    };
    format();
    window.setInterval(format, 60000);
})();
