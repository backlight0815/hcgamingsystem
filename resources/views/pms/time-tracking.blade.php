@extends('pms.layout')
@section('title', 'Time Tracking')
@section('content')
@php
    $duration = static fn ($seconds) => sprintf('%02d:%02d:%02d', intdiv((int) $seconds, 3600), intdiv((int) $seconds % 3600, 60), (int) $seconds % 60);
    $timerStatus = $timer?->status ?? 'ready';
    $timerProject = $timer ? $projects->firstWhere('id', $timer->project_id) : null;
    $timerTicket = $timer?->ticket_id ? $tickets->firstWhere('id', $timer->ticket_id) : null;
@endphp
<div class="pm-time-page">
    <header class="pm-time-heading">
        <div><p class="pm-time-eyebrow">Planning &amp; delivery</p><h1>Time Tracking</h1><p>Track your work across projects and tickets.</p></div>
        <a class="pm-time-export" href="{{ route('pms.time-tracking.export', ['range' => $range]) }}" download data-time-export aria-label="Export time entries as CSV"><i class="mdi mdi-download" aria-hidden="true"></i><span>Export CSV</span></a>
    </header>

    <section class="pm-time-card pm-time-timer" aria-labelledby="pm-timer-title" data-timer-started="{{ $timer->started_at ?? '' }}" data-timer-status="{{ $timer->status ?? '' }}">
        <div class="pm-time-card-top"><h2 id="pm-timer-title">Work timer</h2><span class="pm-time-status is-{{ $timerStatus }}"><span class="pm-time-status-dot" aria-hidden="true"></span>{{ ucfirst($timerStatus) }}</span></div>
        <div class="pm-time-hero"><span class="pm-time-clock-icon" aria-hidden="true"><i class="mdi mdi-clock-outline"></i></span><div class="pm-time-hero-copy"><output class="pm-time-clock" role="timer" aria-label="Elapsed work time" data-duration="{{ $timer->duration_seconds ?? 0 }}" data-resumed="{{ $timer && $timer->status === 'running' ? \Carbon\Carbon::parse($timer->resumed_at, 'UTC')->timestamp : '' }}">{{ $duration($timer->duration_seconds ?? 0) }}</output><p>{{ $timerStatus === 'running' ? 'Tracking your work' : ($timerStatus === 'paused' ? 'Timer paused' : 'Ready to start tracking') }}</p></div></div>
        @if($timer)
            <div class="pm-time-current"><span>Current session</span><strong>{{ $timerProject?->project_reference }} · {{ $timerProject?->name ?? 'Project' }}</strong><p>{{ $timerTicket?->reference ? $timerTicket->reference.' · ' : '' }}{{ $timer->work_type }}</p><p class="pm-time-current-description">{{ $timer->description }}</p></div>
            <div class="pm-time-controls">
                @foreach($timer->status === 'running' ? ['pause' => 'Pause', 'stop' => 'Stop & save'] : ['resume' => 'Resume', 'stop' => 'Stop & save'] as $action => $label)
                    <form method="post" action="{{ route('pms.time-tracking.control') }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button type="submit" class="pm-time-action {{ $action === 'stop' ? 'secondary' : 'primary' }}"><i class="mdi mdi-{{ $action === 'pause' ? 'pause' : ($action === 'resume' ? 'play' : 'stop') }}" aria-hidden="true"></i>{{ $label }}</button></form>
                @endforeach
            </div>
        @else
            <form method="post" action="{{ route('pms.time-tracking.control') }}" class="pm-time-form" id="pm-work-timer-form">@csrf<input type="hidden" name="action" value="start">
                <label class="pm-time-field">Project<span class="pm-time-select"><select name="project_id" required data-time-project><option value="">Choose a project</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->project_reference }} · {{ $project->name }}</option>@endforeach</select></span></label>
                <label class="pm-time-field">Ticket <small>(optional)</small><span class="pm-time-select"><select name="ticket_id" data-time-ticket><option value="">Project-level work</option>@foreach($tickets as $ticket)<option value="{{ $ticket->id }}" data-project="{{ $ticket->project_id }}" @selected(old('ticket_id') == $ticket->id) hidden>{{ $ticket->reference }} · {{ \Illuminate\Support\Str::limit($ticket->summary, 70) }}</option>@endforeach</select></span></label>
                <label class="pm-time-field">Work type<span class="pm-time-select"><select name="work_type" required>@foreach(['Development','QA Testing','Business Analysis','Project Management','Documentation','Meeting','Investigation','Other'] as $type)<option @selected(old('work_type', 'Development') === $type)>{{ $type }}</option>@endforeach</select></span></label>
                <label class="pm-time-field">Work description<textarea name="description" rows="2" maxlength="2000" required placeholder="What are you working on?">{{ old('description') }}</textarea></label>
                <button type="submit" class="pm-time-action primary pm-time-start"><i class="mdi mdi-play" aria-hidden="true"></i>Start timer</button>
            </form>
        @endif
    </section>

    <section class="pm-time-card pm-time-activity" aria-labelledby="pm-activity-title">
        <div class="pm-time-section-head"><div><p class="pm-time-eyebrow">Your recorded work</p><h2 id="pm-activity-title">Time activity</h2></div><span class="pm-time-entry-count">{{ $entries->total() }} {{ \Illuminate\Support\Str::plural('entry', $entries->total()) }}</span></div>
        <nav class="pm-time-ranges" aria-label="Report range">@foreach(['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $key => $label)<a href="{{ route('pms.time-tracking.index', ['range' => $key]) }}" @if($range === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
        @if($entries->isEmpty())
            <div class="pm-time-empty"><i class="mdi mdi-clock-outline" aria-hidden="true"></i><strong>No completed work yet</strong><p>Saved timer sessions for this range will appear here.</p></div>
        @else
            <div class="pm-time-table-wrap"><table class="pm-time-table"><thead><tr><th>Date</th><th>Project</th><th>Ticket</th><th>Work type</th><th>Duration</th><th>Activity</th><th><span class="pm-sr-only">Details</span></th></tr></thead><tbody>
                @foreach($entries as $entry)
                    <tr><td>{{ \App\Support\PmsDateTime::format($entry->ended_at, false, 'UTC') }}</td><td><strong>{{ $entry->project_reference }} · {{ $entry->project_name }}</strong></td><td>{{ $entry->ticket_reference ?: 'Project-level work' }}</td><td>{{ $entry->work_type }}</td><td class="pm-time-duration">{{ $duration($entry->duration_seconds) }}</td><td class="pm-time-description">{{ $entry->description }}</td><td><button type="button" class="pm-time-detail-button" aria-label="View entry for {{ $entry->project_name }}" data-time-detail data-date="{{ \App\Support\PmsDateTime::format($entry->ended_at, false, 'UTC') }}" data-project="{{ $entry->project_reference }} · {{ $entry->project_name }}" data-ticket="{{ $entry->ticket_reference ?: 'Project-level work' }}" data-type="{{ $entry->work_type }}" data-duration="{{ $duration($entry->duration_seconds) }}" data-description="{{ $entry->description }}"><i class="mdi mdi-dots-horizontal" aria-hidden="true"></i></button></td></tr>
                @endforeach
            </tbody></table></div>
            <div class="pm-time-mobile-entries">@foreach($entries as $entry)
                <button type="button" class="pm-time-mobile-entry" data-time-detail data-date="{{ \App\Support\PmsDateTime::format($entry->ended_at, false, 'UTC') }}" data-project="{{ $entry->project_reference }} · {{ $entry->project_name }}" data-ticket="{{ $entry->ticket_reference ?: 'Project-level work' }}" data-type="{{ $entry->work_type }}" data-duration="{{ $duration($entry->duration_seconds) }}" data-description="{{ $entry->description }}"><span class="pm-time-row-icon"><i class="mdi mdi-clock-outline" aria-hidden="true"></i></span><span class="pm-time-row-copy"><strong>{{ $entry->project_reference }} · {{ $entry->project_name }}</strong><span>{{ $entry->ticket_reference ?: 'Project-level work' }} · {{ $entry->work_type }}</span><small>{{ \App\Support\PmsDateTime::format($entry->ended_at, false, 'UTC') }}</small></span><span class="pm-time-row-end"><strong>{{ $duration($entry->duration_seconds) }}</strong><i class="mdi mdi-chevron-right" aria-hidden="true"></i></span></button>
            @endforeach</div>
            {{ $entries->links() }}
        @endif
    </section>

    <section class="pm-time-insights" aria-labelledby="pm-insights-title"><div class="pm-time-section-head"><div><p class="pm-time-eyebrow">Overview</p><h2 id="pm-insights-title">Time insights</h2></div><span class="pm-time-range-label">{{ ['today' => 'Today', 'week' => 'This week', 'month' => 'This month'][$range] ?? 'Today' }}</span></div><div class="pm-time-metrics">
        <article><span>Tracked work</span><strong>{{ $duration($tracked) }}</strong><small>Today {{ $duration($trackedTotals['today']) }} · Week {{ $duration($trackedTotals['week']) }} · Month {{ $duration($trackedTotals['month']) }}</small></article>
        <article><span>Active workspace</span><strong>{{ $duration($workspace) }}</strong><small>Visible, focused interaction</small></article>
        <article><span>Idle / untracked</span><strong>{{ $duration($idle) }}</strong><small>Session time without recorded activity</small></article>
        <article><span>Sessions</span><strong>{{ $sessions->count() }}</strong><small>Recent PMS sessions</small></article>
    </div></section>

    <div class="pm-time-report-grid">
        <section class="pm-time-card" aria-labelledby="pm-allocation-title"><div class="pm-time-section-head"><h2 id="pm-allocation-title">Work allocation by project</h2></div>@forelse($allocation as $row)<div class="pm-time-allocation"><div><strong>{{ $row->project_name }}</strong><span>{{ $duration($row->seconds) }}</span></div><div class="pm-time-bar" role="img" aria-label="{{ $row->project_name }}: {{ $duration($row->seconds) }}"><i style="width:{{ $tracked ? round(100 * $row->seconds / $tracked) : 0 }}%"></i></div></div>@empty<p class="pm-time-note">Complete a work timer to see your allocation.</p>@endforelse
            @if($ticketAllocation->isNotEmpty())<h3 class="pm-time-subtitle">Top tickets</h3><div class="pm-time-table-wrap report"><table class="pm-time-table"><thead><tr><th>Ticket</th><th>Tracked work</th></tr></thead><tbody>@foreach($ticketAllocation as $row)<tr><td>{{ $row->reference }}</td><td class="pm-time-duration">{{ $duration($row->seconds) }}</td></tr>@endforeach</tbody></table></div>@endif
        </section>
        <section class="pm-time-card" aria-labelledby="pm-session-title"><div class="pm-time-section-head"><h2 id="pm-session-title">Session history</h2></div><div class="pm-time-table-wrap report"><table class="pm-time-table"><thead><tr><th>Started</th><th>Last interaction</th><th>Ended</th><th>Reason</th><th>Active workspace</th></tr></thead><tbody>@forelse($sessions as $item)<tr><td>{{ \App\Support\PmsDateTime::format($item->started_at, false, 'UTC') }}</td><td>{{ \App\Support\PmsDateTime::format($item->last_eligible_at, false, 'UTC') }}</td><td>{{ \App\Support\PmsDateTime::format($item->ended_at, false, 'UTC') }}</td><td>{{ $item->end_reason ?: 'Current session' }}</td><td class="pm-time-duration">{{ $duration($item->active_seconds) }}</td></tr>@empty<tr><td colspan="5">No sessions yet.</td></tr>@endforelse</tbody></table></div></section>
    </div>

    @if($team->isNotEmpty())<section class="pm-time-card"><div class="pm-time-section-head"><h2>Authorized team allocation</h2></div><div class="pm-time-table-wrap report"><table class="pm-time-table"><thead><tr><th>Project</th><th>Team member</th><th>Tracked work</th></tr></thead><tbody>@foreach($team as $row)<tr><td>{{ $row->project_name }}</td><td>{{ $row->user_name }}</td><td class="pm-time-duration">{{ $duration($row->seconds) }}</td></tr>@endforeach</tbody></table></div></section>@endif
    @if($teamEntries->isNotEmpty())<section class="pm-time-card"><div class="pm-time-section-head"><div><h2>Time entry corrections</h2><p class="pm-time-note">Super Administrator · reason and audit required</p></div></div>@foreach($teamEntries as $entry)<details class="pm-time-correction"><summary>{{ $entry->user_name }} · {{ $entry->project_name }} · {{ $duration($entry->duration_seconds) }}</summary><form method="post" action="{{ route('pms.time-tracking.adjust', $entry->id) }}" class="pm-form-grid">@csrf @method('PUT')<label>Duration in seconds<input name="duration_seconds" type="number" min="0" max="86400" value="{{ $entry->duration_seconds }}" required></label><label class="wide">Work description<textarea name="description" rows="2" required>{{ $entry->description }}</textarea></label><label class="wide">Correction reason<textarea name="reason" rows="2" minlength="10" required></textarea></label><button class="pm-button secondary">Record correction</button></form></details>@endforeach</section>@endif
</div>
<dialog class="pm-time-details" id="pm-time-details" aria-labelledby="pm-time-details-title"><div class="pm-time-details-head"><div><p class="pm-time-eyebrow">Recorded work</p><h2 id="pm-time-details-title">Session details</h2></div><button type="button" class="pm-time-detail-button" data-time-close aria-label="Close session details"><i class="mdi mdi-close" aria-hidden="true"></i></button></div><dl><div><dt>Date</dt><dd data-detail-value="date"></dd></div><div><dt>Project</dt><dd data-detail-value="project"></dd></div><div><dt>Ticket</dt><dd data-detail-value="ticket"></dd></div><div><dt>Work type</dt><dd data-detail-value="type"></dd></div><div><dt>Duration</dt><dd data-detail-value="duration"></dd></div><div><dt>Work description</dt><dd data-detail-value="description"></dd></div></dl></dialog>
@endsection
@section('scripts')<script src="{{ asset('backend/assets/js/pms-time-tracking.js') }}?v=20261024a"></script>@endsection
