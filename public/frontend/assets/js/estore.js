(() => {
  const toggle = document.querySelector('.es-menu-toggle');
  const menu = document.querySelector('#es-mobile-nav');
  if (toggle && menu) {
    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      menu.hidden = !open;
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !menu.hidden) {
        menu.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }
  const progress = document.querySelector('.es-progress');
  const article = document.querySelector('.es-article-body');
  if (progress && article) {
    let scheduled = false;
    const update = () => {
      const start = article.getBoundingClientRect().top + scrollY;
      const end = start + article.offsetHeight - innerHeight;
      progress.style.width = `${Math.max(0, Math.min(100, ((scrollY - start) / Math.max(1, end - start)) * 100))}%`;
      scheduled = false;
    };
    const queue = () => { if (!scheduled) { scheduled = true; requestAnimationFrame(update); } };
    addEventListener('scroll', queue, { passive: true });
    addEventListener('resize', queue);
    queue();
  }
  const copy = document.querySelector('[data-copy-link]');
  if (copy) copy.addEventListener('click', async () => {
    try {
      if (navigator.share) await navigator.share({ title: document.title, url: location.href });
      else { await navigator.clipboard.writeText(location.href); copy.textContent = 'Link copied'; }
    } catch (error) { if (error.name !== 'AbortError') copy.textContent = 'Unable to share'; }
  });
})();
