(() => {
    const script = document.currentScript;
    const warning = document.getElementById('pm-session-warning');
    const continueButton = document.getElementById('pm-session-continue');
    const countdown = document.getElementById('pm-session-countdown');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel('pms-session-state') : null;
    let expiresAt = 0;
    let lastPulseAt = 0;
    let sending = false;
    let nonce = null;

    const expired = () => {
        channel?.postMessage({type: 'expired'});
        window.location.assign(script.dataset.loginUrl);
    };
    const update = (data, renewed = false) => {
        if (!data?.expires_at) return;
        expiresAt = Number.isFinite(Number(data.seconds_remaining))
            ? Date.now() + Number(data.seconds_remaining) * 1000 : Date.parse(data.expires_at);
        if (data.activity_nonce) nonce = data.activity_nonce;
        channel?.postMessage({type: 'renewed', expires_at: data.expires_at,
            seconds_remaining: data.seconds_remaining, interaction: renewed});
        if (warning?.open && (renewed || Number(data.seconds_remaining) > 300)) warning.close();
    };
    const fetchSession = async (url, options = {}, retried = false) => {
        try {
            const response = await fetch(url, {credentials: 'same-origin', headers: {
                'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                ...(options.method === 'POST' ? {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf} : {}),
            }, ...options});
            if (response.status === 401 || response.status === 419) return expired();
            if (response.status === 409 && options.method === 'POST' && !retried) {
                const fresh = await fetch(script.dataset.statusUrl, {credentials: 'same-origin',
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
                if (fresh.status === 401) return expired();
                if (!fresh.ok) return;
                update(await fresh.json());
                return fetchSession(url, {...options, body: JSON.stringify({
                    ...JSON.parse(options.body), nonce,
                })}, true);
            }
            if (!response.ok) return;
            update(await response.json(), options.method === 'POST');
        } catch (_) { /* Network loss does not renew the server lease. */ }
    };
    const pulse = (workspace = false, force = false) => {
        if (document.hidden || !document.hasFocus() || sending) return;
        if (!nonce) return fetchSession(script.dataset.statusUrl).then(() => { if (nonce) pulse(workspace, true); });
        if (!force && Date.now() - lastPulseAt < 30000) return;
        lastPulseAt = Date.now();
        sending = true;
        fetchSession(script.dataset.activityUrl, {method: 'POST', body: JSON.stringify({workspace, nonce})})
            .finally(() => { sending = false; });
    };
    ['keydown', 'pointerdown', 'touchstart', 'scroll'].forEach(event => document.addEventListener(event, () => pulse(false), {passive: true}));
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) fetchSession(script.dataset.statusUrl);
    });
    window.addEventListener('focus', () => fetchSession(script.dataset.statusUrl));
    continueButton?.addEventListener('click', () => {
        continueButton.disabled = true;
        fetchSession(script.dataset.activityUrl, {method: 'POST', body: JSON.stringify({workspace: false, nonce})})
            .finally(() => { continueButton.disabled = false; });
    });
    channel?.addEventListener('message', event => {
        if (event.data?.type === 'expired') return window.location.assign(script.dataset.loginUrl);
        if (event.data?.type === 'renewed') {
            expiresAt = Number.isFinite(Number(event.data.seconds_remaining))
                ? Date.now() + Number(event.data.seconds_remaining) * 1000 : Date.parse(event.data.expires_at);
            if (warning?.open && (event.data.interaction || Number(event.data.seconds_remaining) > 300)) warning.close();
        }
    });
    setInterval(() => {
        if (!expiresAt) return;
        const remaining = Math.max(0, expiresAt - Date.now());
        if (!remaining) return fetchSession(script.dataset.statusUrl);
        if (remaining <= 300000 && !document.hidden) {
            if (!warning?.open) warning.showModal();
            countdown.textContent = `${Math.ceil(remaining / 60000)} minute${remaining > 60000 ? 's' : ''}`;
        }
    }, 15000);
    setInterval(() => fetchSession(script.dataset.statusUrl), 60000);
    fetchSession(script.dataset.statusUrl).then(() => pulse(false, true));
})();
