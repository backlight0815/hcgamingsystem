(() => {
    const script = document.currentScript;
    if (!script?.dataset.url) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const tabId = globalThis.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, letter => {
        const n = Math.floor(Math.random() * 16);
        return (letter === 'x' ? n : (n & 3) | 8).toString(16);
    });
    let interacted = true; // Opening a PMS page is an eligible navigation interaction.
    let lastMotion = 0;
    let sending = false;
    let queued = false;
    const foreground = () => !document.hidden && document.hasFocus();

    const payload = () => ({
        tab_id: tabId, visible: !document.hidden, focused: document.hasFocus(),
        interacted: foreground() && interacted,
        project_id: script.dataset.projectId || null,
        ticket_id: script.dataset.ticketId || null,
        context_signature: script.dataset.signature || null,
    });
    async function heartbeat(final = false) {
        if (sending && !final) { queued = true; return; }
        const data = payload();
        if (final) { data.visible = false; data.focused = false; }
        interacted = false;
        sending = true;
        try {
            await fetch(script.dataset.url, {
                method: 'POST', credentials: 'same-origin', keepalive: final,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify(data),
            });
        } catch (_) { /* Only confirmed intervals are counted after a network loss. */ }
        finally {
            sending = false;
            if (queued && !final) { queued = false; heartbeat(); }
        }
    }
    const interact = () => { if (foreground()) interacted = true; };
    for (const name of ['pointerdown', 'keydown', 'touchstart', 'scroll']) {
        document.addEventListener(name, interact, {passive: true});
    }
    document.addEventListener('mousemove', () => {
        const now = Date.now();
        if (now - lastMotion >= 1000) { lastMotion = now; interact(); }
    }, {passive: true});
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) interacted = true;
        heartbeat();
    });
    window.addEventListener('focus', () => { interacted = true; heartbeat(); });
    window.addEventListener('blur', () => heartbeat());
    window.addEventListener('pagehide', () => heartbeat(true));
    setInterval(() => heartbeat(), Number(script.dataset.interval || 30) * 1000);
    heartbeat();
})();
