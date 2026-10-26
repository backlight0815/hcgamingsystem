<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'PMS Workspace') · HC</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('backend/assets/images/hc-backoffice-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-delivery.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-evidence.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-delivery-summary.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-release-board.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-global-cycles.css') }}">
    @if(request()->routeIs('pms.projects.*', 'pms.global-cycles.*'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-planning.css') }}?v=20261026a">@endif
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-wiki.css') }}?v=20261021d">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-editor.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-management.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-workspace.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-cycle-dashboard.css') }}">
    @if(request()->routeIs('pms.cycles.*'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-cycle-progress.css') }}?v=20261025a">@endif
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-workspace-theme.css') }}">
    <script>try{if(localStorage.getItem('pms-theme')==='dark')document.documentElement.dataset.theme='dark'}catch(e){}</script>
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-conversation.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-enhancements.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-pic-hold.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-admin-refresh.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/assets/css/pms-time-tracking.css') }}?v=20261024a">
    @if(request()->routeIs('pms.time-tracking.*'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-activity-report.css') }}?v=20261025a">@endif
    @if(request()->routeIs('pms.time-tracking.*', 'pms.tickets.show', 'pms.tickets.create', 'pms.issues.show'))<link rel="stylesheet" href="{{ asset('backend/assets/css/icons.css') }}">@endif
    @if(request()->routeIs('pms.tickets.show', 'pms.tickets.create', 'pms.tickets.deleted', 'pms.issues.show'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-ticket-enhancements.css') }}?v=20261024a">@endif
    @if(request()->routeIs('pms.tickets.create', 'pms.projects.tickets.create'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-ticket-create-design.css') }}?v=20261025a">@endif
    @if(request()->routeIs('pms.dashboard'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-overview.css') }}">@endif
    @if(request()->routeIs('pms.reports'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-report-design.css') }}?v=20261025b">@endif
    @if(request()->routeIs('pms.wiki.*'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-wiki-redesign.css') }}?v=20261021a">@endif
    @if(request()->routeIs('pms.tickets.index', 'pms.tickets.deleted'))<link rel="stylesheet" href="{{ asset('backend/assets/css/pms-tickets.css') }}?v=20261024a">@endif
</head>
@php
    $pmActor = auth()->user();
    $pmAdministrator = \App\Support\PmsAccess::administrator($pmActor);
    $pmCanManageTeam = $pmAdministrator || \Illuminate\Support\Facades\DB::table('pm_project_members')
        ->where('user_id', $pmActor->id)->whereIn('role', ['Project Manager','Project Administrator'])->exists();
    $pmCanViewTracking = \App\Support\PmsAccess::timeTracking($pmActor);
    $pmRouteTicket = request()->route('ticket');
    $pmRouteProject = request()->route('project');
    $pmActivityTicket = $pmRouteTicket instanceof \App\Models\PmWorkItem ? $pmRouteTicket : null;
    $pmActivityProject = $pmActivityTicket?->project ?: ($pmRouteProject instanceof \App\Models\PmProject ? $pmRouteProject : null);
    $pmActivitySignature = hash_hmac('sha256', $pmActor->id.'|'.request()->session()->getId().'|'.($pmActivityProject?->id ?: '').'|'.($pmActivityTicket?->id ?: ''), config('app.key'));
@endphp
<body @class(['pm-app', 'pmo-shell' => request()->routeIs('pms.dashboard'), 'pm-report-shell' => request()->routeIs('pms.reports'), 'pm-ticket-shell' => request()->routeIs('pms.tickets.index', 'pms.tickets.deleted'), 'pm-create-page' => request()->routeIs('pms.tickets.create', 'pms.projects.tickets.create'), 'pm-time-shell' => request()->routeIs('pms.time-tracking.*'), 'pm-kb-shell' => request()->routeIs('pms.wiki.*'), 'pm-kb-dark' => request()->routeIs('pms.wiki.index', 'pms.wiki.pages.show'), 'pm-kb-studio-shell' => request()->routeIs('pms.wiki.pages.create', 'pms.wiki.pages.edit')])>
<button type="button" class="pm-sidebar-backdrop" id="pm-sidebar-backdrop" aria-label="Close navigation"></button>
<aside class="pm-sidebar" id="pm-sidebar" aria-label="Project management navigation">
    <a class="pm-brand" href="{{ route('pms.dashboard') }}"><span class="pm-brand-mark">HC</span><span class="pm-brand-copy">Project Management<br><small>Delivery Workspace</small></span></a>
    <nav class="pm-nav" aria-label="Workspace sections">
        <details class="pm-nav-group" @if(request()->routeIs('pms.dashboard','pms.reports') || request('mine')) open @endif><summary><span class="pm-nav-icon" aria-hidden="true">◫</span><span class="pm-nav-text">Overview</span></summary><div class="pm-nav-children"><a href="{{ route('pms.dashboard') }}" @class(['active'=>request()->routeIs('pms.dashboard')])>Dashboard</a><a href="{{ route('pms.tickets.index',['mine'=>'all']) }}" @class(['active'=>request()->routeIs('pms.tickets.index') && request('mine')==='all'])>My work</a><a href="{{ route('pms.tickets.index',['mine'=>'development']) }}" @class(['active'=>request()->routeIs('pms.tickets.index') && request('mine')==='development'])>Assigned to me</a><a href="{{ route('pms.reports') }}" @class(['active'=>request()->routeIs('pms.reports')])>Reports</a></div></details>
        <details class="pm-nav-group" @if(request()->routeIs('pms.projects.*','pms.tickets.*')) open @endif><summary><span class="pm-nav-icon" aria-hidden="true">▤</span><span class="pm-nav-text">Project management</span></summary><div class="pm-nav-children"><a href="{{ route('pms.projects.index') }}" @class(['active'=>request()->routeIs('pms.projects.*')])>All projects</a><a href="{{ route('pms.tickets.index') }}" @class(['active'=>request()->routeIs('pms.tickets.index') && !request('category') && !request('mine')])>All tickets</a>@foreach(['REQ'=>'Requirements','ENH'=>'Enhancements','PRD'=>'Product tickets','QAT'=>'QAT / bugs'] as $key=>$label)<a href="{{ route('pms.tickets.index',['category'=>$key]) }}" @class(['active'=>request()->routeIs('pms.tickets.index') && request('category')===$key])>{{ $label }} <small>{{ $key }}</small></a>@endforeach</div></details>
        <details class="pm-nav-group" @if(request()->routeIs('pms.cycles.*','pms.global-cycles.*','pms.releases.*','pms.time-tracking.*')) open @endif><summary><span class="pm-nav-icon" aria-hidden="true">▥</span><span class="pm-nav-text">Planning &amp; delivery</span></summary><div class="pm-nav-children"><a href="{{ route('pms.global-cycles.index') }}" @class(['active'=>request()->routeIs('pms.global-cycles.*')])>Cycle Management</a><a href="{{ route('pms.cycles.index') }}" @class(['active'=>request()->routeIs('pms.cycles.*')])>Development</a><a href="{{ route('pms.releases.index') }}" @class(['active'=>request()->routeIs('pms.releases.*')])>Release Management</a>@if($pmCanViewTracking)<a href="{{ route('pms.time-tracking.index') }}" @class(['active'=>request()->routeIs('pms.time-tracking.*')])>Time Tracking</a>@endif</div></details>
        <details class="pm-nav-group" @if(request()->routeIs('pms.wiki.*','pms.change-reviews.*')) open @endif><summary><span class="pm-nav-icon" aria-hidden="true">◉</span><span class="pm-nav-text">Collaboration</span></summary><div class="pm-nav-children"><a href="{{ route('pms.change-reviews.index') }}" @class(['active'=>request()->routeIs('pms.change-reviews.*')])>Change reviews</a><a href="{{ route('pms.wiki.index') }}" @class(['active'=>request()->routeIs('pms.wiki.*')])>Knowledge base</a></div></details>
        @if($pmCanManageTeam)<details class="pm-nav-group" @if(request()->routeIs('pms.team.*','pms.invitations.*','pms.organization.*','pms.settings*','pms.audit')) open @endif><summary><span class="pm-nav-icon" aria-hidden="true">⚙</span><span class="pm-nav-text">Administration</span></summary><div class="pm-nav-children"><a href="{{ route('pms.team.index') }}" @class(['active'=>request()->routeIs('pms.team.*')])>Account Management</a><a href="{{ route('pms.invitations.index') }}" @class(['active'=>request()->routeIs('pms.invitations.*')])>Invitations</a>@if($pmAdministrator)<a href="{{ route('pms.organization.index') }}" @class(['active'=>request()->routeIs('pms.organization.*')])>Organization Settings</a><a href="{{ route('pms.settings') }}" @class(['active'=>request()->routeIs('pms.settings*')])>Settings</a><a href="{{ route('pms.audit') }}" @class(['active'=>request()->routeIs('pms.audit')])>System log</a><a href="{{ route('admin.features.index') }}">Backoffice module management</a>@if($pmActor->isGlobalSuperAdmin())<a href="{{ route('admin.system_audit') }}">Administrative audit</a>@endif @endif</div></details>@endif
    </nav>
    <div class="pm-side-bottom"><span class="pm-dot"></span> Private workspace<br><small>{{ $pmActor->name }}</small><span class="pm-tracking-notice">Active PMS tab usage is recorded automatically. Idle and background time are excluded.</span><a href="{{ route('pms.my-profile.show') }}">My Profile</a>@if(\App\Support\ModuleAccess::allows($pmActor, 'tradinghub'))<a href="{{ route('tradinghub.login') }}">TradingHub sign in</a>@endif @if(\App\Support\ModuleAccess::allows($pmActor, 'dealership'))<a href="{{ route('dealership.login') }}">Dealership sign in</a>@endif</div>
</aside>
<div class="pm-main">
    <header class="pm-top"><button class="pm-icon-button" type="button" id="pm-nav-toggle" aria-label="Toggle navigation" aria-controls="pm-sidebar" aria-expanded="false">☰</button><form action="{{ route('pms.tickets.index') }}" class="pm-global-search"><input name="q" placeholder="Search tickets…" aria-label="Search tickets"></form><div class="pm-top-actions"><a class="pm-link" href="{{ route('pms.my-profile.show') }}">My Profile</a><button class="pm-icon-button" type="button" id="pm-theme" aria-label="Toggle color theme">◐</button><form method="post" action="{{ route('pms.logout') }}">@csrf<button class="pm-link" type="submit">Sign out</button></form></div></header>
    <main class="pm-content">@if(session('success'))<div class="pm-alert success" role="status">{{ session('success') }}</div>@endif @include('pms.partials.action-feedback') @if($errors->any())<div class="pm-alert error" role="alert"><strong>Please fix:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @yield('content')</main>
</div>
<dialog class="pm-feedback-dialog" id="pm-feedback-dialog" aria-labelledby="pm-feedback-title"><div class="pm-feedback-dialog-head"><p class="pm-eyebrow">PROJECT MANAGEMENT</p><h2 id="pm-feedback-title">Action unavailable</h2></div><p id="pm-feedback-message"></p><dl id="pm-feedback-details"></dl><div class="pm-feedback-actions"><a class="pm-button secondary" id="pm-feedback-action" href="{{ route('pms.change-reviews.index') }}" hidden>View reviews</a><button type="button" class="pm-button" id="pm-feedback-close">Close</button></div></dialog>
<dialog class="pm-session-warning" id="pm-session-warning" aria-labelledby="pm-session-warning-title"><h2 id="pm-session-warning-title">Your session is about to expire</h2><p>You've been inactive. Your PMS session will expire in <strong id="pm-session-countdown">5 minutes</strong>.</p><div class="pm-session-warning-actions"><button type="button" class="pm-button" id="pm-session-continue">Continue session</button><form method="post" action="{{ route('pms.logout') }}">@csrf<button type="submit" class="pm-button secondary">Log out</button></form></div></dialog>
<script src="{{ asset('backend/assets/js/pms-session.js') }}" data-status-url="{{ route('pms.time-tracking.session') }}" data-activity-url="{{ route('pms.time-tracking.activity') }}" data-login-url="{{ route('pms.login') }}"></script>
<script src="{{ asset('backend/assets/js/pms-automatic-activity.js') }}?v=20261025a" data-url="{{ route('pms.time-tracking.heartbeat') }}" data-interval="{{ max(15, min(60, (int) config('pms.activity_heartbeat_seconds', 30))) }}" data-project-id="{{ $pmActivityProject?->id }}" data-ticket-id="{{ $pmActivityTicket?->id }}" data-signature="{{ $pmActivitySignature }}"></script>
<script src="{{ asset('backend/assets/js/pms-feedback.js') }}"></script>
<script>
document.getElementById('pm-theme')?.addEventListener('click',()=>{const d=document.documentElement;d.dataset.theme=d.dataset.theme==='dark'?'light':'dark';try{localStorage.setItem('pms-theme',d.dataset.theme)}catch(_){}});
const pmNavToggle=document.getElementById('pm-nav-toggle');
function pmSetNav(open){const mobile=matchMedia('(max-width:760px)').matches;document.body.classList.toggle(mobile?'pm-sidebar-open':'pm-sidebar-collapsed',open);pmNavToggle?.setAttribute('aria-expanded',String(mobile&&open));if(!mobile)try{localStorage.setItem('pms-nav-collapsed',open?'1':'0')}catch(_){}}
try{if(localStorage.getItem('pms-nav-collapsed')==='1'&&!matchMedia('(max-width:760px)').matches)pmSetNav(true)}catch(_){}
pmNavToggle?.addEventListener('click',()=>pmSetNav(!document.body.classList.contains(matchMedia('(max-width:760px)').matches?'pm-sidebar-open':'pm-sidebar-collapsed')));
document.getElementById('pm-sidebar-backdrop')?.addEventListener('click',()=>pmSetNav(false));
document.getElementById('pm-action-feedback')?.focus();
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.body.classList.contains('pm-sidebar-open'))pmSetNav(false)});
document.querySelectorAll('.pm-nav-group summary').forEach(summary=>summary.addEventListener('click',()=>{if(document.body.classList.contains('pm-sidebar-collapsed'))pmSetNav(false)}));
@if($errors->any())
const pmFieldErrors=@json($errors->messages());
for(const [name,messages] of Object.entries(pmFieldErrors)){const field=[...document.querySelectorAll('[name]')].find(el=>el.name===name&&!el.disabled&&el.type!=='hidden');if(!field)continue;field.setAttribute('aria-invalid','true');const hint=document.createElement('small');hint.className='pm-field-error';hint.setAttribute('role','alert');hint.textContent=messages[0];field.insertAdjacentElement('afterend',hint)}
@endif
</script>
@yield('scripts')
</body>
</html>
