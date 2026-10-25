<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') | Project Management</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('backend/assets/images/hc-backoffice-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-auth.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/module-auth.css') }}">
</head>
<body class="pm-auth-page">
<main class="pm-auth-shell">
    <aside class="pm-auth-aside" aria-label="About the project management workspace">
        <a class="pm-auth-brand" href="{{ route('pms.login') }}" aria-label="Project Management home">
            <span class="pm-auth-brand-mark" aria-hidden="true">HC<span></span></span>
            <span class="pm-auth-brand-name">Project Management<small>Delivery workspace</small></span>
        </a>

        <div class="pm-auth-aside-content">
            <span class="pm-auth-overline"><span class="pm-auth-overline-line"></span> BUILT FOR DELIVERY TEAMS</span>
            <h2>@yield('story-title')</h2>
            <p>@yield('story-description')</p>

            <div class="pm-auth-visual" aria-hidden="true">
                <div class="pm-auth-visual-top"><span class="pm-auth-visual-dots"><i></i><i></i><i></i></span><span>DELIVERY WORKFLOW</span><span class="pm-auth-visual-menu">•••</span></div>
                <div class="pm-auth-visual-progress"><span></span><span></span><span></span><span></span></div>
                <div class="pm-auth-visual-list">
                    <div><span class="pm-auth-visual-icon pm-auth-visual-icon-plan">01</span><span><b>Plan the work</b><small>Scope and priorities</small></span><i></i></div>
                    <div><span class="pm-auth-visual-icon pm-auth-visual-icon-build">02</span><span><b>Build with clarity</b><small>Ownership and progress</small></span><i></i></div>
                    <div><span class="pm-auth-visual-icon pm-auth-visual-icon-qa">03</span><span><b>Validate quality</b><small>Testing and evidence</small></span><i></i></div>
                    <div><span class="pm-auth-visual-icon pm-auth-visual-icon-release">04</span><span><b>Release confidently</b><small>Readiness and history</small></span><i></i></div>
                </div>
            </div>
        </div>

        <p class="pm-auth-aside-footer"><span class="pm-auth-lock" aria-hidden="true">◆</span> A private workspace for approved members</p>
    </aside>

    <section class="pm-auth-content" aria-labelledby="pm-auth-title">
        <div class="pm-auth-mobile-brand"><span class="pm-auth-brand-mark" aria-hidden="true">HC<span></span></span><span>Project Management<small>Delivery workspace</small></span></div>
        <div class="pm-auth-panel">
            @yield('panel')
        </div>
        <p class="pm-auth-content-footer">Project Management Portal <span aria-hidden="true">·</span> Secure team access</p>
    </section>
</main>
<script>
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input) return;
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${input.dataset.label || 'password'}`);
    });
});
</script>
</body>
</html>
