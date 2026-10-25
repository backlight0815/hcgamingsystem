@extends('admin.admin_master')

@section('admin')
@php($s = $configuration['settings'])
<style>
    .mi-admin { max-width:1440px; margin:auto; color:#e9f4f3; }
    .mi-admin .mi-card { background:#15232b; border:1px solid #36515a; border-radius:16px; padding:20px; margin-bottom:16px; }
    .mi-admin h1,.mi-admin h2,.mi-admin h3 { color:#f3fbfa; }
    .mi-admin h2 { font-size:1.18rem; }
    .mi-admin .table { color:#e9f4f3; }
    .mi-admin .table td,.mi-admin .table th { border-color:#36515a; vertical-align:middle; }
    .mi-admin .form-control { color:#17242a; background:#fff; min-width:90px; }
    .mi-admin a { color:#90e5d1; }
    .mi-admin .mi-fields { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr)); gap:12px; }
    .mi-admin .mi-fields label { display:block; font-size:.88rem; }
    .mi-admin .mi-fields input,.mi-admin .mi-fields select { width:100%; }
    .mi-admin .mi-muted { color:#b8cacb; }
    @media(max-width:600px) { .mi-admin .mi-card { padding:15px; } .mi-admin h1 { font-size:1.6rem; } }
</style>
<div class="page-content"><div class="container-fluid py-4 mi-admin">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3" style="gap:12px"><div><h1 class="mb-1">AI Market Insight Management</h1><div class="mi-muted">Rule-Based Indicator Analysis · Configuration v{{ $configuration['version'] }}</div></div><a class="btn btn-outline-info" href="{{ route('market_insight.dashboard') }}">View dashboard</a></div>
    @if($source === 'MOCK')
        <div class="alert alert-info"><strong>DEMO / SIMULATED DATA</strong> — Generated prices are for educational testing only.</div>
    @else
        <div class="alert alert-warning"><strong>REAL XAU/USD · Twelve Data</strong> — The credential is held only in the backend environment. {{ $distributionAllowed ? 'Multi-user display is enabled.' : 'Multi-user display remains disabled until provider rights are confirmed.' }}</div>
        <section class="mi-card"><h2>Market data provider</h2>
            <div class="mi-fields mb-3">
                <div><strong>Connection</strong><div class="mi-muted">{{ $providerStatus['status'] ?? 'NOT_SYNCHRONIZED' }}</div></div>
                <div><strong>Credential</strong><div class="mi-muted">{{ $providerConfigured ? 'Configured' : 'Missing' }}</div></div>
                <div><strong>Symbol</strong><div class="mi-muted">XAU/USD</div></div>
                <div><strong>Intervals</strong><div class="mi-muted">M15 · M30 · H1 · H4 · D1</div></div>
                <div><strong>Last success</strong><div class="mi-muted">{{ $providerStatus['last_success_at'] ?? 'Never' }}</div></div>
                <div><strong>Last failure</strong><div class="mi-muted">{{ $providerStatus['last_failure_at'] ?? 'Never' }}</div></div>
                <div><strong>Credits left</strong><div class="mi-muted">{{ $providerStatus['credits_left'] ?? 'Unknown' }}</div></div>
            </div>
            <p class="mi-muted">{{ $providerStatus['message'] ?? 'No connection test has run.' }} Backend cron runs <code>php artisan schedule:run</code> every minute; candle calls occur only when due.</p>
            <form method="POST" action="{{ route('admin.market_insight.sync') }}">@csrf
                <label for="sync-timeframe">Historical synchronization</label>
                <select id="sync-timeframe" class="form-control mb-2" style="max-width:220px" name="timeframe"><option value="">All five intervals</option>@foreach($timeframes as $code => $minutes)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select>
                <button class="btn btn-info" type="submit">Test connection and synchronize</button>
            </form>
        </section>
    @endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Settings were not saved.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="mi-card"><h2>Feature visibility, indicators and scoring</h2><p class="mi-muted">Saving creates a new configuration version. Existing snapshots retain their original settings and results.</p>
        <form method="POST" action="{{ route('admin.market_insight.settings') }}">@csrf
            <input type="hidden" name="settings[enabled_for_users]" value="0"><label class="mb-3"><input type="checkbox" name="settings[enabled_for_users]" value="1" @checked($s['enabled_for_users'])> Enable AI Market Insight for standard users</label>
            <div class="table-responsive"><table class="table"><thead><tr><th>Indicator</th><th>Enabled</th><th>Directional weight</th><th>Calculation settings</th></tr></thead><tbody>
                @foreach($s['indicators'] as $key => $rule)
                    <tr><th class="text-uppercase">{{ $key }}</th><td><input type="hidden" name="settings[indicators][{{ $key }}][enabled]" value="0"><input aria-label="Enable {{ $key }}" type="checkbox" name="settings[indicators][{{ $key }}][enabled]" value="1" @checked($rule['enabled'])></td>
                    <td><input class="form-control" aria-label="{{ $key }} directional weight" type="number" min="0" max="100" step="0.1" name="settings[indicators][{{ $key }}][weight]" value="{{ $rule['weight'] }}" @readonly(in_array($key, ['atr','adr']))>@if(in_array($key, ['atr','adr']))<input type="hidden" name="settings[indicators][{{ $key }}][weight]" value="0"><small class="mi-muted">Risk context only</small>@endif</td>
                    <td><div class="mi-fields">@foreach($rule as $field => $value)@if(!in_array($field, ['enabled','weight']))<label>{{ str_replace('_',' ',ucfirst($field)) }}<input class="form-control" type="number" step="{{ in_array($field, ['multiplier','low_ratio','high_ratio','extended_ratio','oversold','overbought']) ? '0.01' : '1' }}" name="settings[indicators][{{ $key }}][{{ $field }}]" value="{{ $value }}"></label>@endif @endforeach</div></td></tr>
                @endforeach
            </tbody></table></div>
            <h3 class="h5">Signal thresholds</h3><div class="mi-fields mb-3">@foreach($s['thresholds'] as $key => $value)<label>{{ str_replace('_',' ',ucwords($key,'_')) }}<input class="form-control" type="number" min="-100" max="100" step="1" name="settings[thresholds][{{ $key }}]" value="{{ $value }}"></label>@endforeach</div>
            <button class="btn btn-info" type="submit">Save new configuration version</button>
        </form>
    </section>
    <section class="mi-card"><h2>Supported demo instruments</h2><div class="table-responsive"><table class="table"><thead><tr><th>Symbol</th><th>Name</th><th>Category</th><th>Demo base price</th><th>Calendar</th><th>Enabled</th><th></th></tr></thead><tbody>
        @foreach($instruments as $instrument)<tr><td><form id="mi-instrument-{{ $instrument->id }}" method="POST" action="{{ route('admin.market_insight.instruments.update', $instrument->id) }}">@csrf</form><input form="mi-instrument-{{ $instrument->id }}" class="form-control" name="symbol" value="{{ $instrument->symbol }}" required></td><td><input form="mi-instrument-{{ $instrument->id }}" class="form-control" name="name" value="{{ $instrument->name }}" required></td><td><select form="mi-instrument-{{ $instrument->id }}" class="form-control" name="category">@foreach(['forex','metal','crypto','index','other'] as $category)<option value="{{ $category }}" @selected($instrument->category === $category)>{{ ucfirst($category) }}</option>@endforeach</select></td><td><input form="mi-instrument-{{ $instrument->id }}" class="form-control" type="number" step="any" min="0.00000001" name="demo_base_price" value="{{ $instrument->demo_base_price }}" required></td><td><select form="mi-instrument-{{ $instrument->id }}" class="form-control" name="session_calendar"><option value="weekdays" @selected($instrument->session_calendar === 'weekdays')>Weekdays</option><option value="all_days" @selected($instrument->session_calendar === 'all_days')>All days</option></select></td><td><select form="mi-instrument-{{ $instrument->id }}" class="form-control" name="is_enabled"><option value="1" @selected($instrument->is_enabled)>Yes</option><option value="0" @selected(!$instrument->is_enabled)>No</option></select></td><td><button form="mi-instrument-{{ $instrument->id }}" type="submit" class="btn btn-outline-info btn-sm">Save</button></td></tr>@endforeach
    </tbody></table></div><h3 class="h5 mt-3">Add instrument</h3><form method="POST" action="{{ route('admin.market_insight.instruments.store') }}">@csrf<div class="mi-fields mb-3"><label>Symbol<input class="form-control" name="symbol" required maxlength="24"></label><label>Name<input class="form-control" name="name" required></label><label>Category<select class="form-control" name="category">@foreach(['forex','metal','crypto','index','other'] as $category)<option value="{{ $category }}">{{ ucfirst($category) }}</option>@endforeach</select></label><label>Demo base price<input class="form-control" type="number" step="any" min="0.00000001" name="demo_base_price" required></label><label>Calendar<select class="form-control" name="session_calendar"><option value="weekdays">Weekdays</option><option value="all_days">All days</option></select></label><label>Enabled<select class="form-control" name="is_enabled"><option value="1">Yes</option><option value="0">No</option></select></label></div><button class="btn btn-info">Add demo instrument</button></form></section>
    <section class="mi-card"><h2>Mock dataset generation</h2><p class="mi-muted">QA only. Demo candles remain isolated from REAL mode. Regeneration creates a new version; previous candles and snapshots remain inspectable.</p><form method="POST" action="{{ route('admin.market_insight.generate') }}">@csrf<div class="mi-fields mb-3"><label>Symbol<select class="form-control" name="symbol">@foreach($instruments as $instrument)@if($instrument->is_enabled)<option value="{{ $instrument->symbol }}">{{ $instrument->symbol }}</option>@endif @endforeach</select></label><label>Timeframe<select class="form-control" name="timeframe">@foreach($timeframes as $code => $minutes)<option value="{{ $code }}">{{ $code }} · {{ $minutes }} min</option>@endforeach</select></label><label>Scenario<select class="form-control" name="scenario">@foreach($scenarios as $scenario)<option value="{{ $scenario }}">{{ ucfirst($scenario) }}</option>@endforeach</select></label><label>Seed<input class="form-control" type="number" name="seed" min="1" max="2147483646" value="41821" required></label><label>Anchor UTC (optional)<input class="form-control" type="datetime-local" name="anchor_at"></label></div><button class="btn btn-info" type="submit">Generate and analyze demo data</button></form>
        <h3 class="h5 mt-4">Recalculate existing dataset</h3><form method="POST" action="{{ route('admin.market_insight.recalculate') }}">@csrf<div class="mi-fields mb-3"><label>Symbol<select class="form-control" name="symbol">@foreach($instruments as $instrument)@if($instrument->is_enabled)<option value="{{ $instrument->symbol }}">{{ $instrument->symbol }}</option>@endif @endforeach</select></label><label>Timeframe<select class="form-control" name="timeframe">@foreach($timeframes as $code => $minutes)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select></label></div><button class="btn btn-outline-info" type="submit">Calculate or reuse snapshot</button></form>
        <h3 class="h5 mt-4">Recent dataset versions</h3><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Symbol</th><th>Timeframe</th><th>Version</th><th>Scenario</th><th>Seed</th><th>Anchor UTC</th></tr></thead><tbody>@forelse($datasets as $dataset)<tr><td>{{ $dataset->id }}</td><td>{{ $dataset->symbol }}</td><td>{{ $dataset->timeframe }}</td><td>{{ $dataset->version }}</td><td>{{ $dataset->scenario }}</td><td>{{ $dataset->seed }}</td><td>{{ $dataset->anchor_at }}</td></tr>@empty<tr><td colspan="7">No datasets generated yet.</td></tr>@endforelse</tbody></table></div></section>
    <section class="mi-card"><h2>Calculation execution logs</h2><div class="table-responsive"><table class="table"><thead><tr><th>Time</th><th>Type</th><th>Status</th><th>Dataset</th><th>Details / error</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->completed_at }}</td><td>{{ $log->type }}</td><td>{{ $log->status }}</td><td>{{ $log->dataset_id ?? '—' }}</td><td>{{ $log->details }}</td></tr>@empty<tr><td colspan="5">No calculations yet.</td></tr>@endforelse</tbody></table></div></section>
    <section class="mi-card"><h2>Insight snapshot history</h2><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Symbol</th><th>Timeframe</th><th>Calculated</th><th>Signal</th><th>Score</th><th>Config</th><th>Status</th><th>Inspect / manage</th></tr></thead><tbody>@forelse($snapshots as $snapshot)<tr><td>{{ $snapshot->id }}</td><td>{{ $snapshot->symbol }}</td><td>{{ $snapshot->timeframe }}</td><td>{{ $snapshot->calculated_at }}</td><td>{{ $snapshot->signal }}</td><td>{{ $snapshot->directional_score }}</td><td>v{{ $snapshot->configuration_version }}</td><td>{{ $snapshot->archived_at ? 'Archived' : 'Visible' }}</td><td><a href="{{ route('market_insight.show', $snapshot->id) }}">Inspect values</a><form class="d-inline ml-2" method="POST" action="{{ route('admin.market_insight.archive', $snapshot->id) }}">@csrf<input type="hidden" name="archived" value="{{ $snapshot->archived_at ? 0 : 1 }}"><button class="btn btn-sm btn-outline-info" type="submit">{{ $snapshot->archived_at ? 'Restore' : 'Archive' }}</button></form></td></tr>@empty<tr><td colspan="9">No analysis snapshots yet.</td></tr>@endforelse</tbody></table></div></section>
    <section class="mi-card"><h2>System Log · AI Market Insight</h2><div class="table-responsive"><table class="table"><thead><tr><th>Version</th><th>Release date</th><th>Category</th><th>Module</th><th>Description</th></tr></thead><tbody>@foreach($releases as $release)<tr><td>{{ $release['version'] }}</td><td>{{ $release['release_date'] }}</td><td>{{ $release['category'] }}</td><td>{{ $release['module'] }}</td><td>{{ $release['description'] }}</td></tr>@endforeach</tbody></table></div></section>
</div></div>
@endsection
