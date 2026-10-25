@extends('pms.layout')
@section('title', 'Overview')
@section('content')
@php
    $projectFilter = $projectId ? ['project' => $projectId] : [];
    $statusMax = max(1, max(array_column($statuses, 'count') ?: [0]));
    $classMax = max(1, max(array_column($classifications, 'count') ?: [0]));
    $healthActive = $health['active_percent'] ?? 0;
    $healthPlanned = $health['total'] ? round(100 * $health['planned'] / $health['total']) : 0;
@endphp
<div class="pmo pmo-command">
    <section class="pmo-command-heading">
        <div>
            <p class="pmo-eyebrow">Portfolio operations</p>
            <h1>Delivery command center</h1>
            <p>Operational health across projects, delivery flow, risks, and upcoming releases.</p>
        </div>
        <div class="pmo-heading-actions">
            <span class="pmo-range" aria-label="Reporting period">{{ $periodStart->format('M j') }} – {{ today()->format('M j, Y') }}</span>
            <a class="pmo-primary" href="{{ route('pms.cycles.index', $projectFilter) }}">＋ Plan a cycle</a>
        </div>
    </section>

    <form class="pmo-filter-bar" method="get" action="{{ route('pms.dashboard') }}" aria-label="Dashboard filters">
        <label><span>Project scope</span><select name="project"><option value="">All accessible projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->key }} · {{ $project->name }}</option>@endforeach</select></label>
        <label><span>Reporting period</span><select name="period">@foreach([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $days => $label)<option value="{{ $days }}" @selected($periodDays === $days)>{{ $label }}</option>@endforeach</select></label>
        <button type="submit">Update view</button>
        <span class="pmo-scope-note"><i aria-hidden="true"></i> {{ number_format($counts['projects']) }} projects in view</span>
        @if($projectId)<a class="pmo-clear" href="{{ route('pms.dashboard', ['period' => $periodDays]) }}">Clear project</a>@endif
    </form>

    <section class="pmo-command-metrics" aria-label="Delivery metrics">
        <article class="pmo-command-metric"><span class="pmo-metric-icon cobalt" aria-hidden="true">▣</span><div><span>Active projects</span><strong>{{ number_format($counts['active_projects']) }}</strong><small>of {{ number_format($counts['projects']) }} visible projects</small></div></article>
        <article class="pmo-command-metric"><span class="pmo-metric-icon violet" aria-hidden="true">▤</span><div><span>Open work items</span><strong>{{ number_format($counts['open']) }}</strong><small>{{ number_format($counts['due_week']) }} due this week</small></div></article>
        <article class="pmo-command-metric"><span class="pmo-metric-icon green" aria-hidden="true">◎</span><div><span>Production done</span><strong>{{ number_format($counts['production_done']) }}</strong><small>Completed in {{ $periodDays }} days</small></div></article>
        <article class="pmo-command-metric"><span class="pmo-metric-icon amber" aria-hidden="true">⌁</span><div><span>Production incidents</span><strong>{{ number_format($counts['production_incidents']) }}</strong><small>Currently open</small></div></article>
        <article class="pmo-command-metric"><span class="pmo-metric-icon red" aria-hidden="true">◷</span><div><span>Critical blockers</span><strong>{{ number_format($counts['critical_blockers']) }}</strong><small>Critical tickets blocked</small></div></article>
        <article class="pmo-command-metric"><span class="pmo-metric-icon cyan" aria-hidden="true">↗</span><div><span>On-time delivery</span><strong>{{ $counts['on_time_percent'] === null ? 'N/A' : $counts['on_time_percent'].'%' }}</strong><small>{{ $counts['on_time_basis'] }}</small></div></article>
    </section>

    @if($counts['blocked'])
        <aside class="pmo-attention" aria-label="Items needing attention">
            <span class="pmo-attention-icon" aria-hidden="true">!</span>
            <p><strong>{{ number_format($counts['blocked']) }} blocked {{ \Illuminate\Support\Str::plural('ticket', $counts['blocked']) }} need attention</strong><small>Unresolved blockers or dependencies in the current project scope.</small></p>
            <a href="{{ route('pms.tickets.index', array_merge($projectFilter, ['blockers' => 'active'])) }}">Review blocked tickets →</a>
        </aside>
    @endif

    <div class="pmo-command-grid">
        <section class="pmo-panel pmo-health" aria-labelledby="pmo-health-title">
            <div class="pmo-panel-head"><div><span class="pmo-kicker">Portfolio health</span><h2 id="pmo-health-title">Project status</h2><p>Current status of {{ number_format($health['total']) }} visible projects</p></div><a href="{{ route('pms.projects.index') }}">View projects →</a></div>
            @if($health['total'])
                <div class="pmo-health-content">
                    <div class="pmo-health-ring" style="--active:{{ $healthActive }}%;--planned:{{ $healthActive + $healthPlanned }}%" role="img" aria-label="{{ $health['active'] }} active, {{ $health['planned'] }} planned or approved, {{ $health['other'] }} in other statuses">
                        <div><strong>{{ $healthActive }}%</strong><span>active</span></div>
                    </div>
                    <div class="pmo-health-legend">
                        <div><span><i class="green"></i>Active</span><strong>{{ $health['active'] }}</strong><em>{{ $healthActive }}%</em></div>
                        <div><span><i class="violet"></i>Planned / approved</span><strong>{{ $health['planned'] }}</strong><em>{{ $healthPlanned }}%</em></div>
                        <div><span><i class="slate"></i>Other statuses</span><strong>{{ $health['other'] }}</strong><em>{{ max(0, 100 - $healthActive - $healthPlanned) }}%</em></div>
                    </div>
                    <div class="pmo-health-target"><span>Overdue project targets</span><strong>{{ $health['overdue'] }}</strong><div><i style="width:{{ $health['overdue_percent'] }}%"></i></div><small>{{ $health['overdue'] }} of {{ $health['total'] }} projects past target date</small></div>
                </div>
            @else
                <div class="pmo-empty">No projects are available in this view.</div>
            @endif
        </section>

        <section class="pmo-panel pmo-risk" aria-labelledby="pmo-risk-title">
            <div class="pmo-panel-head"><div><span class="pmo-kicker">Risk</span><h2 id="pmo-risk-title">Critical blockers</h2><p>Critical-priority tickets with active blockers</p></div><span class="pmo-count-badge">{{ $counts['critical_blockers'] }} open</span></div>
            @if($blockedTickets->isEmpty())<div class="pmo-empty">No critical-priority tickets are blocked.</div>
            @else
                <ol class="pmo-risk-list">@foreach($blockedTickets as $ticket)
                    <li><i aria-hidden="true"></i><div><a href="{{ route('pms.tickets.show', $ticket->reference ?: $ticket->id) }}">{{ $ticket->summary }}</a><small>{{ $ticket->project?->name }} · {{ $ticket->reference ?: $ticket->issue_key }}</small></div><time datetime="{{ $ticket->updated_at?->toIso8601String() }}">{{ $ticket->updated_at?->diffForHumans() }}</time></li>
                @endforeach</ol>
            @endif
            <a class="pmo-panel-footer" href="{{ route('pms.tickets.index', array_merge($projectFilter, ['blockers' => 'active', 'priority' => 'Critical'])) }}">View critical blocked tickets →</a>
        </section>

        <section class="pmo-panel pmo-classification" aria-labelledby="pmo-classification-title">
            <div class="pmo-panel-head"><div><span class="pmo-kicker">Demand mix</span><h2 id="pmo-classification-title">Ticket classification</h2><p>{{ number_format($counts['open']) }} open work items by type</p></div><a href="{{ route('pms.tickets.index', $projectFilter) }}">Details →</a></div>
            @if($counts['open'])
                <div class="pmo-bar-chart">@foreach($classifications as $row)<div class="pmo-bar-row"><span>{{ $row['label'] }}</span><div class="pmo-bar-track"><i class="{{ $row['tone'] }}" style="width:{{ round(100 * $row['count'] / $classMax) }}%"></i></div><strong>{{ $row['count'] }}</strong></div>@endforeach</div>
            @else<div class="pmo-empty">No open tickets in this view.</div>@endif
        </section>

        <section class="pmo-panel pmo-flow" aria-labelledby="pmo-flow-title">
            <div class="pmo-panel-head"><div><span class="pmo-kicker">Flow</span><h2 id="pmo-flow-title">Issue status distribution</h2><p>Most common current ticket statuses</p></div><span class="pmo-meta-note">{{ count($statuses) }} shown</span></div>
            @if($statuses)
                <div class="pmo-flow-bars">@foreach($statuses as $row)<a href="{{ route('pms.tickets.index', array_merge($projectFilter, ['status' => $row['label']])) }}" title="{{ $row['count'] }} tickets in {{ $row['label'] }}"><span>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong><i class="tone-{{ $loop->index % 5 }}" style="height:{{ max(20, round(100 * $row['count'] / $statusMax)) }}px"></i></a>@endforeach</div>
            @else<div class="pmo-empty">No ticket status data yet.</div>@endif
        </section>

        <section class="pmo-panel pmo-priority" aria-labelledby="pmo-priority-title">
            <div class="pmo-panel-head"><div><span class="pmo-kicker">Risk mix</span><h2 id="pmo-priority-title">Priority distribution</h2><p>All current tickets in this view</p></div></div>
            @if(array_sum(array_column($priorities, 'count')))
                <div class="pmo-priority-stack" role="img" aria-label="{{ collect($priorities)->map(fn ($row) => $row['label'].': '.$row['count'])->implode(', ') }}">@foreach($priorities as $row)<i class="{{ $row['tone'] }}" style="width:{{ $row['percent'] }}%"></i>@endforeach</div>
                <div class="pmo-priority-list">@foreach($priorities as $row)<div><span><i class="{{ $row['tone'] }}"></i>{{ $row['label'] }}</span><strong>{{ $row['count'] }}</strong><small>{{ $row['percent'] }}%</small></div>@endforeach</div>
            @else<div class="pmo-empty">No priority data yet.</div>@endif
        </section>
    </div>

    <section class="pmo-delivery" aria-labelledby="pmo-delivery-title">
        <div class="pmo-delivery-head"><div><p>Software delivery</p><h2 id="pmo-delivery-title">Tracks, quality, and releases</h2><span>Quality and schedule across visible projects</span></div><a href="{{ route('pms.releases.index', $projectFilter) }}">Open release management →</a></div>
        <div class="pmo-delivery-metrics">
            <article><span>Open QA defects</span><strong>{{ number_format($counts['qat_open']) }}</strong><small>QAT tickets still open</small></article>
            <article><span>Verified QA</span><strong>{{ number_format($counts['qat_verified']) }}</strong><small>Current verified or done</small></article>
            <article><span>Active releases</span><strong>{{ number_format($counts['active_releases']) }}</strong><small>Not yet completed</small></article>
            <article><span>Overdue tickets</span><strong>{{ number_format($counts['overdue']) }}</strong><small>Open past due date</small></article>
            <article><span>Due releases</span><strong>{{ number_format($counts['releases_due_30']) }}</strong><small>Next 30 days</small></article>
            <article><span>QA pass rate</span><strong>{{ $counts['qa_pass_rate'] === null ? 'N/A' : $counts['qa_pass_rate'].'%' }}</strong><small>{{ $counts['qa_pass_basis'] }} in {{ $periodDays }} days</small></article>
        </div>
    </section>

    <section class="pmo-panel pmo-releases" aria-labelledby="pmo-releases-title">
        <div class="pmo-panel-head"><div><span class="pmo-kicker">Release portfolio</span><h2 id="pmo-releases-title">Upcoming release readiness</h2><p>Milestones and readiness checks from the release workflow</p></div><a class="pmo-secondary" href="{{ route('pms.releases.index', $projectFilter) }}">Manage releases</a></div>
        @if($releases->isEmpty())<div class="pmo-empty">No active or upcoming releases in this view.</div>
        @else
            <div class="pmo-release-table-wrap"><table class="pmo-release-table"><thead><tr><th>Release</th><th>Owner</th><th>Current stage</th><th>Readiness checks</th><th>Target date</th><th>Risk</th><th><span class="pm-sr-only">Open</span></th></tr></thead><tbody>
                @foreach($releases as $card)
                    @php($release = $card['release'])
                    <tr>
                        <td data-label="Release"><a class="pmo-release-name" href="{{ route('pms.releases.show', $release) }}"><span class="pmo-release-symbol" aria-hidden="true">✦</span><span><strong>{{ $release->name }}</strong><small>{{ $release->release_key }} · {{ $release->project?->name }}</small></span></a></td>
                        <td data-label="Owner">{{ $release->owner?->name ?? 'Unassigned' }}</td>
                        <td data-label="Current stage">{{ $release->status }}</td>
                        <td data-label="Readiness checks"><div class="pmo-readiness"><span><i style="width:{{ $card['percent'] }}%"></i></span><b>{{ $card['passed'] }}/{{ $card['total'] }}</b></div></td>
                        <td data-label="Target date">{{ $release->planned_deploy_date?->format('M j, Y') ?? 'Not set' }}</td>
                        <td data-label="Risk"><span @class(['pmo-risk-state', 'ready' => $card['state'] === 'Ready', 'blocked' => $card['state'] === 'Blocked', 'at-risk' => $card['state'] === 'At Risk'])><i></i>{{ $card['state'] }}</span></td>
                        <td class="pmo-release-arrow"><a href="{{ route('pms.releases.show', $release) }}" aria-label="Open {{ $release->name }}">›</a></td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
</div>
@endsection
