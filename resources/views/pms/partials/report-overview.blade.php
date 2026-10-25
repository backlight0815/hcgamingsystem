@php
    $reportRange = \Carbon\Carbon::parse($periodStartDate)->format('d M').' – '.\Carbon\Carbon::parse($periodEndDate)->format('d M Y');
    $reportFilters = array_filter(['project' => $projectId, 'period' => $periodDays]);
    $ticketFilters = array_filter(['project' => $projectId, 'date_from' => $periodStartDate, 'date_to' => $periodEndDate]);
    $scopeFilters = array_filter(['project' => $projectId]);
    $metricColors = ['amber', 'teal', 'subtle', 'teal', 'blue', 'blue', 'subtle'];
    $metricNames = ['Delivery rate', 'Reopen rate', 'On-time delivery', 'QA pass rate', 'Avg. defect resolution', 'Avg. QAT retest', 'Release completion'];
    $classOrder = ['QAT', 'REQ', 'ENH', 'PRD', 'FTR', 'PDI'];
    $classKeys = array_unique(array_merge($classOrder, $classifications->keys()->all()));
    $priorityColors = ['P4' => 'subtle', 'P3' => 'blue', 'P2' => 'amber', 'P1' => 'red'];
    $topStatuses = $statusDistribution->sortDesc()->take(3);
    $statusColors = ['teal', 'blue', 'amber'];
    $activePercent = $counts['projects'] ? round($counts['active'] / $counts['projects'] * 100) : 0;
@endphp
<div class="pm-report-page">
    <header class="pm-report-heading">
        <div>
            <p class="pm-report-breadcrumb">REPORTS / OPERATIONAL ANALYTICS</p>
            <h1>Operational analytics</h1>
            <p>Measure delivery, testing quality, and release health across your projects.</p>
        </div>
        <a class="pm-report-button pm-report-export" href="{{ route('pms.reports.export', $reportFilters) }}" download>↓ Export report</a>
    </header>

    <div class="pm-report-mobile-actions">
        <span class="pm-report-range-pill">{{ $reportRange }}</span>
        <a class="pm-report-button" href="{{ route('pms.reports.export', $reportFilters) }}" download>↓ Export</a>
    </div>

    <form class="pm-report-filter" action="{{ route('pms.reports') }}" method="get">
        <label><span>Project</span><select name="project">
            <option value="">All accessible projects</option>
            @foreach($projects as $project)<option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->key }} · {{ $project->name }}</option>@endforeach
        </select></label>
        <label><span>Period</span><select name="period">
            @foreach([30, 90, 180, 365] as $days)<option value="{{ $days }}" @selected($periodDays === $days)>Last {{ $days }} days</option>@endforeach
        </select></label>
        <button class="pm-report-button" type="submit">Apply</button>
        <span class="pm-report-filter-range">{{ $reportRange }}</span>
    </form>

    <div class="pm-report-section-top"><h2>Performance snapshot</h2><span>Based on selected period</span></div>
    <div class="pm-report-kpis" aria-label="Primary metrics">
        @foreach(array_slice($statistics, 0, 4) as $index => $stat)
            <article class="pm-report-kpi">
                <div class="pm-report-kpi-label">{{ $metricNames[$index] }} <i class="pm-report-dot {{ $metricColors[$index] }}" aria-hidden="true"></i></div>
                <strong class="pm-report-kpi-value {{ $metricColors[$index] }}">{{ $stat['value'] }}</strong>
                <p>{{ $stat['basis'] }}</p>
            </article>
        @endforeach
    </div>
    <div class="pm-report-kpis pm-report-kpis-secondary" aria-label="Secondary metrics">
        @foreach(array_slice($statistics, 4, 3) as $offset => $stat)
            @php($index = $offset + 4)
            <article class="pm-report-kpi">
                <div class="pm-report-kpi-label">{{ $metricNames[$index] }} <i class="pm-report-dot {{ $metricColors[$index] }}" aria-hidden="true"></i></div>
                <strong class="pm-report-kpi-value {{ $metricColors[$index] }}">{{ $stat['value'] }}</strong>
                <p>{{ $stat['basis'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="pm-report-grid">
        <div class="pm-report-stack">
            <section class="pm-report-panel">
                <p class="pm-report-eyebrow">Portfolio</p>
                <div class="pm-report-panel-title"><h2>Project status</h2><small>{{ number_format($counts['projects']) }} total</small></div>
                <div class="pm-report-status-line"><span>Active</span><strong class="teal">{{ number_format($counts['active']) }}</strong></div>
                <div class="pm-report-track" role="img" aria-label="{{ $activePercent }}% of projects active"><i style="width: {{ $activePercent }}%"></i></div>
                @foreach($projectStatus as $status => $count)
                    @if($status !== 'Active')<a class="pm-report-data-line" href="{{ route('pms.projects.index', ['status' => $status]) }}"><span>{{ $status ?: 'Unspecified' }}</span><strong>{{ number_format($count) }}</strong></a>@endif
                @endforeach
            </section>
            <section class="pm-report-panel">
                <p class="pm-report-eyebrow">Demand mix</p>
                <div class="pm-report-panel-title"><h2>Ticket classification</h2><small>Created in period</small></div>
                <div class="pm-report-chips">
                    @foreach($classKeys as $key)
                        <a class="pm-report-chip" href="{{ in_array($key, ['FTR', 'PDI'], true) ? route('pms.issues.index', array_merge($ticketFilters, ['classification' => $key])) : route('pms.tickets.index', array_merge($ticketFilters, ['category' => $key === 'EHN' ? 'ENH' : $key])) }}">{{ $key ?: 'Other' }} <strong>{{ number_format((int) ($classifications[$key] ?? 0)) }}</strong></a>
                    @endforeach
                </div>
            </section>
        </div>
        <div class="pm-report-stack">
            <section class="pm-report-panel">
                <p class="pm-report-eyebrow">Flow</p>
                <div class="pm-report-panel-title"><h2>Issue status distribution</h2><a href="#pm-detailed-analysis" onclick="document.getElementById('pm-detailed-analysis').open = true">Full report →</a></div>
                @forelse($topStatuses as $status => $count)
                    <a class="pm-report-flow-row" href="{{ route('pms.tickets.index', array_merge($scopeFilters, ['status' => $status])) }}"><i class="pm-report-dot {{ $statusColors[$loop->index] }}" aria-hidden="true"></i><span>{{ $status ?: 'Unspecified' }}</span><strong>{{ number_format($count) }}</strong></a>
                @empty<p class="pm-report-empty">No issue data for this project.</p>@endforelse
            </section>
            <section class="pm-report-panel">
                <p class="pm-report-eyebrow">Risk</p>
                <div class="pm-report-panel-title"><h2>Priority distribution</h2><small>Open and completed</small></div>
                <div class="pm-report-chips">
                    @foreach(\App\Support\PmsPriority::OPTIONS as $code => $label)
                        @php($value = $code === 'P1' ? (int) ($priorityDistribution['P0'] ?? 0) + (int) ($priorityDistribution['P1'] ?? 0) : (int) ($priorityDistribution[$code] ?? 0))
                        <a class="pm-report-chip pm-report-priority {{ $priorityColors[$code] }}" href="{{ route('pms.issues.index', array_filter(['project' => $projectId, 'priority' => $code])) }}">{{ $label }} <strong>{{ number_format($value) }}</strong></a>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <section class="pm-report-panel pm-report-delivery">
        <p class="pm-report-eyebrow">Software delivery</p>
        <div class="pm-report-panel-title"><h2>Tickets, quality, and releases</h2><a href="{{ route('pms.releases.index', array_filter(['project' => $projectId])) }}">Open release management →</a></div>
        <div class="pm-report-summary">
            @foreach([
                ['Open QAT defects', $delivery['qat_open'], route('pms.tickets.index', array_filter(['project' => $projectId, 'category' => 'QAT']))],
                ['Verified QAT', $delivery['qat_verified'], route('pms.tickets.index', array_filter(['project' => $projectId, 'category' => 'QAT', 'status' => 'Verified']))],
                ['Active releases', $delivery['releases_active'], route('pms.releases.index', array_filter(['project' => $projectId]))],
                ['Overdue tickets', $delivery['tickets_overdue'], route('pms.tickets.index', array_filter(['project' => $projectId, 'sort' => 'due_date']))],
            ] as [$label, $count, $url])
                <div class="pm-report-summary-card"><span>{{ $label }}</span><strong>{{ number_format($count) }}</strong><a href="{{ $url }}">Open records →</a></div>
            @endforeach
        </div>
        <div class="pm-report-details-grid">
            <div><h3>Ticket categories</h3>
                @forelse($delivery['tickets_by_category'] as $category => $count)<div class="pm-report-data-line"><span>{{ $category ?: 'Other' }}</span><strong>{{ number_format($count) }}</strong></div>@empty<p class="pm-report-empty">No tickets yet.</p>@endforelse
            </div>
            <div><h3>Quality &amp; schedule</h3>
                <div class="pm-report-data-line"><span>QA passes / failures</span><strong>{{ $delivery['qa_passed'] }} / {{ $delivery['qa_failed'] }}</strong></div>
                <div class="pm-report-data-line"><span>Defects reopened</span><strong>{{ number_format($delivery['qat_reopened']) }}</strong></div>
                <div class="pm-report-data-line"><span>Intake without project</span><strong>{{ number_format($delivery['qat_intake']) }}</strong></div>
                <div class="pm-report-data-line"><span>Releases due in 30 days</span><strong>{{ number_format($delivery['releases_upcoming']) }}</strong></div>
                <div class="pm-report-data-line"><span>Completed releases</span><strong>{{ number_format($delivery['releases_completed']) }}</strong></div>
            </div>
        </div>
    </section>
    <p class="pm-report-note">N/A means the selected period has no eligible records for that rate. Counts accompany each metric to show its calculation basis.</p>
</div>
