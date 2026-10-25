(() => {
    const brand = document.body.dataset.backofficeMode === 'dealership' ? 'HC Dealership' : 'HC Trading Hub';
    const defaultTitle = document.body.dataset.backofficeMode === 'dealership'
        ? 'Dealership Overview | HC Dealership' : 'Trading Hub Control Center | HC Trading Hub';
    const pageTitles = [...document.querySelectorAll('title')];
    if (pageTitles.length > 1) {
        document.title = pageTitles.at(-1).textContent.trim().replace(/HC Gaming Studio|HC Gaming/gi, brand);
    } else if (document.title === defaultTitle) {
        const heading = document.querySelector('.commerce-hero h1, .signal-hero h1, .form-hero h3, .detail-hero h3, .page-title-box h4, .bo-title')?.textContent.trim();
        if (heading) document.title = `${heading} | ${brand}`;
    }
    document.querySelectorAll('.breadcrumb a, .breadcrumb-item a, .commerce-breadcrumb a').forEach(link => {
        if (/^HC Gaming$/i.test(link.textContent.trim())) link.textContent = brand;
    });

    const menuButton = document.getElementById('vertical-menu-btn');
    const backdrop = document.querySelector('.bo-drawer-backdrop');
    if (!menuButton || !backdrop) return;

    const sync = () => menuButton.setAttribute('aria-expanded', String(document.body.classList.contains('sidebar-enable')));
    const close = () => {
        document.body.classList.remove('sidebar-enable');
        sync();
        menuButton.focus();
    };
    menuButton.setAttribute('aria-controls', 'sidebar-menu');
    menuButton.setAttribute('aria-label', 'Toggle navigation');
    menuButton.addEventListener('click', () => requestAnimationFrame(sync));
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && document.body.classList.contains('sidebar-enable')) close();
    });
    sync();
})();
