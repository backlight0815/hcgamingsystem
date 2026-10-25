document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('.th-menu-button');
    const navigation = document.getElementById('th-mobile-nav');
    if (button && navigation) {
        button.addEventListener('click', () => {
            const expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', String(!expanded));
            button.setAttribute('aria-label', expanded ? 'Open navigation' : 'Close navigation');
            navigation.hidden = expanded;
        });
    }
    const progress = document.querySelector('.th-reading-progress');
    if (progress) {
        let scheduled = false;
        const update = () => {
            const remaining = document.documentElement.scrollHeight - window.innerHeight;
            progress.style.width = `${remaining > 0 ? Math.min(100, Math.max(0, window.scrollY / remaining * 100)) : 100}%`;
            scheduled = false;
        };
        window.addEventListener('scroll', () => {
            if (!scheduled) {
                scheduled = true;
                requestAnimationFrame(update);
            }
        }, { passive: true });
        update();
    }
    const share = document.querySelector('[data-share-article]');
    if (share) share.addEventListener('click', async () => {
        if (navigator.share) {
            try { await navigator.share({ title: document.title, url: location.href }); return; } catch (_) {}
        }
        try { await navigator.clipboard.writeText(location.href); share.textContent = 'Link copied'; }
        catch (_) { share.textContent = location.href; }
    });
});
