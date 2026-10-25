(() => {
    const article = document.getElementById('pm-kb-article-content');
    const toc = document.getElementById('pm-kb-toc-list');
    if (!article || !toc) return;

    const headings = [...article.querySelectorAll('h1, h2, h3')];
    if (!headings.length) {
        toc.closest('.pm-kb-toc')?.remove();
        return;
    }

    headings.forEach((heading, index) => {
        if (!heading.id) heading.id = `pm-kb-section-${index + 1}`;
        const link = document.createElement('a');
        link.href = `#${heading.id}`;
        link.textContent = heading.textContent;
        if (heading.tagName === 'H3') link.classList.add('sub');
        toc.append(link);
    });

    if (!('IntersectionObserver' in window)) return;
    const links = [...toc.querySelectorAll('a')];
    const observer = new IntersectionObserver(entries => {
        for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            links.forEach(link => link.classList.toggle('active', link.hash === `#${entry.target.id}`));
        }
    }, { rootMargin: '-10% 0px -70% 0px' });
    headings.forEach(heading => observer.observe(heading));
})();
