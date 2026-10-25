@extends('pms.layout')
@section('title', $ticketHeading)
@section('content')
@php
    $activeFilters = collect(['q', 'project', 'category', 'status', 'priority', 'updated', 'release', 'assignee', 'project_manager', 'blockers', 'resumed', 'date_from', 'date_to', 'sort', 'unassigned'])
        ->filter(fn ($key) => request()->filled($key) && !($key === 'sort' && request('sort') === 'updated_at'));
    $scope = request('assignment') === 'unassigned' ? 'unassigned' : (request('mine') ? 'mine' : 'all');
    $canCreate = collect(\App\Http\Controllers\Pms\TicketController::CATEGORIES)->keys()
        ->contains(fn ($category) => \App\Support\PmsAccess::canCreateTicket(auth()->user(), $category));
    $category = array_key_exists((string) request('category'), \App\Http\Controllers\Pms\TicketController::CATEGORIES) ? request('category') : null;
    $clearUrl = route('pms.tickets.index', $category ? ['category' => $category] : []);
@endphp
<div class="pm-ticket-page">
    <div class="pm-heading pm-ticket-heading">
        <div><p class="pm-eyebrow">DELIVERY / {{ strtoupper($ticketHeading) }}</p><h1>{{ $ticketHeading }}</h1><p>Track requirements, enhancements, production issues, QA findings, and development tasks.</p></div>
        <div class="pm-ticket-header-actions">@if($canRestoreTickets)<a class="pm-ticket-deleted-link" href="{{ route('pms.tickets.deleted') }}">Recently Deleted</a>@endif<a class="pm-ticket-export" href="{{ route('pms.tickets.export', request()->query()) }}" download title="Export all filtered results"><span aria-hidden="true">⇩</span> Export CSV</a>@if($canCreate)<a class="pm-button pm-ticket-create" href="{{ route('pms.tickets.create', request('category') === 'QAT' ? ['category' => 'QAT'] : []) }}"><span aria-hidden="true">＋</span> Create ticket</a>@endif</div>
    </div>

    <nav class="pm-ticket-scopes" aria-label="Ticket scope">
        <a @class(['active' => $scope === 'all']) @if($scope === 'all') aria-current="page" @endif href="{{ route('pms.tickets.index', request()->except('mine', 'assignment', 'page')) }}">All</a>
        <a @class(['active' => $scope === 'mine']) @if($scope === 'mine') aria-current="page" @endif href="{{ route('pms.tickets.index', array_merge(request()->except('mine', 'assignment', 'page'), ['mine' => 'all'])) }}">Assigned to me</a>
        <a @class(['active' => $scope === 'unassigned']) @if($scope === 'unassigned') aria-current="page" @endif href="{{ route('pms.tickets.index', array_merge(request()->except('mine', 'assignment', 'page'), ['assignment' => 'unassigned'])) }}">Unassigned</a>
    </nav>

    <form method="get" action="{{ route('pms.tickets.index') }}" class="pm-ticket-filter-form" id="pm-ticket-filter-form">
        @if(request('sort_by'))<input type="hidden" name="sort_by" value="{{ request('sort_by') }}"><input type="hidden" name="sort_order" value="{{ request('sort_order', 'asc') }}">@endif
        @if(request('mine'))<input type="hidden" name="mine" value="{{ request('mine') }}">@endif
        @if(request('assignment'))<input type="hidden" name="assignment" value="{{ request('assignment') }}">@endif
        <div class="pm-ticket-filter-grid">
            <div class="pm-ticket-search"><span class="pm-ticket-search-icon" aria-hidden="true">⌕</span><label class="sr-only" for="pm-ticket-q">Search {{ $ticketHeading }} by ID or title</label><input id="pm-ticket-q" name="q" value="{{ request('q') }}" placeholder="Search {{ $category ?: 'all' }} tickets…"><button type="submit" aria-label="Search {{ $ticketHeading }}">Search</button></div>
            <label class="pm-ticket-select"><span>Project</span><select name="project"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>@endforeach</select></label>
            <label class="pm-ticket-select"><span>Type</span><select name="category"><option value="">All types</option>@foreach(\App\Http\Controllers\Pms\TicketController::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(request('category') === $key)>{{ $key }} · {{ $label }}</option>@endforeach</select></label>
            <label class="pm-ticket-select"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach(\App\Support\PmsStatusRegistry::definitions('ticket') as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select></label>
            <details class="pm-ticket-more"><summary><span aria-hidden="true">⚑</span> More filters <span class="pm-ticket-caret" aria-hidden="true">⌄</span></summary>
                <div class="pm-ticket-more-panel">
                    <h2>More filters</h2>
                    <label>Priority<select name="priority"><option value="">All priorities</option><option value="high_critical" @selected(request('priority') === 'high_critical')>High &amp; Critical</option>@foreach(['Critical', 'High', 'Normal', 'Low'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $priority }}</option>@endforeach</select></label>
                    <label>Updated<select name="updated"><option value="">Any time</option><option value="7" @selected(request('updated') === '7')>Last 7 days</option><option value="30" @selected(request('updated') === '30')>Last 30 days</option></select></label>
                    <label>Release<select name="release"><option value="">All releases</option>@foreach($releases as $release)<option value="{{ $release->id }}" @selected(request('release') == $release->id)>{{ $release->release_key }} · {{ $release->version }}</option>@endforeach</select></label>
                    <label>Developer<select name="assignee"><option value="">Any developer</option>@foreach($members as $member)<option value="{{ $member->id }}" @selected(request('assignee') == $member->id)>{{ $member->name }}</option>@endforeach</select></label>
                    @isset($managerFilterOptions)
                    <label>Project Manager<select name="project_manager"><option value="">Any Project Manager</option>@foreach($managerFilterOptions as $member)<option value="{{ $member->id }}" @selected(request('project_manager') == $member->id)>{{ $member->name }}</option>@endforeach</select></label>
                    @endisset
                    <label>Blockers<select name="blockers"><option value="">Any blocker state</option><option value="active" @selected(request('blockers') === 'active')>With active blockers</option><option value="overdue" @selected(request('blockers') === 'overdue')>With overdue blockers</option></select></label>
                    <label>Created from<input type="date" name="date_from" value="{{ request('date_from') }}"></label>
                    <label>Created to<input type="date" name="date_to" value="{{ request('date_to') }}"></label>
                    <label>Sort by<select name="sort"><option value="updated_at" @selected(request('sort', 'updated_at') === 'updated_at')>Recently updated</option><option value="created_at" @selected(request('sort') === 'created_at')>Created date</option><option value="due_date" @selected(request('sort') === 'due_date')>Due date</option><option value="priority" @selected(request('sort') === 'priority')>Priority</option></select></label>
                    <label class="pm-ticket-checkbox"><input type="checkbox" name="resumed" value="1" @checked(request()->boolean('resumed'))> Resumed work</label>
                    <label class="pm-ticket-checkbox"><input type="checkbox" name="unassigned" value="1" @checked(request()->boolean('unassigned'))> Unassigned project intake</label>
                    <div class="pm-ticket-more-actions"><button type="submit" class="pm-button">Apply filters</button><a href="{{ $clearUrl }}">Clear filters</a></div>
                </div>
            </details>
        </div>
    </form>

    @if($activeFilters->isNotEmpty())<div class="pm-ticket-chips" aria-label="Active filters">
        @if(request('updated'))<a class="pm-ticket-chip" href="{{ route('pms.tickets.index', request()->except('updated', 'page')) }}">Updated: Last {{ request('updated') }} days <span aria-hidden="true">×</span></a>@endif
        @if(request('priority'))<a class="pm-ticket-chip" href="{{ route('pms.tickets.index', request()->except('priority', 'page')) }}">Priority: {{ request('priority') === 'high_critical' ? 'High & Critical' : request('priority') }} <span aria-hidden="true">×</span></a>@endif
        <a class="pm-ticket-clear" href="{{ $clearUrl }}">Clear filters</a>
    </div>@endif

    @if($tickets->isEmpty())
        <div class="pm-ticket-empty"><div class="pm-ticket-empty-icon" aria-hidden="true">⌕</div><h2>{{ $category ? 'No '.$ticketHeading.' Found' : 'No Tickets Found' }}</h2><p>Try removing a filter or searching for a different ticket ID or title.</p><a class="pm-button" href="{{ $clearUrl }}">Clear filters</a><a class="pm-ticket-clear" href="{{ route('pms.tickets.index') }}">Show all tickets</a></div>
    @else
        @php
            $sortColumn = request('sort_by', request('sort', 'updated_at'));
            $sortDirection = request('sort_order', request('direction', 'desc'));
        @endphp
        <div class="pm-ticket-table-wrap"><table class="pm-ticket-table"><thead><tr>
        <th aria-sort="{{ in_array($sortColumn, ['reference','summary'], true) ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ route('pms.tickets.index', array_merge(request()->except('page','sort','direction'), ['sort_by'=>'reference','sort_order'=>$sortColumn === 'reference' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}">ID {{ $sortColumn === 'reference' ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</a> / <a href="{{ route('pms.tickets.index', array_merge(request()->except('page','sort','direction'), ['sort_by'=>'summary','sort_order'=>$sortColumn === 'summary' && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}">TITLE {{ $sortColumn === 'summary' ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</a></th>
        @foreach(['classification_key'=>'TYPE','project'=>'PROJECT','priority'=>'PRIORITY','status'=>'STATUS','assignee'=>'ASSIGNEE','updated_at'=>'UPDATED'] as $column=>$label)<th aria-sort="{{ $sortColumn === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a href="{{ route('pms.tickets.index', array_merge(request()->except('page','sort','direction'), ['sort_by'=>$column,'sort_order'=>$sortColumn === $column && $sortDirection === 'asc' ? 'desc' : 'asc'])) }}">{{ $label }} <span aria-hidden="true">{{ $sortColumn === $column ? ($sortDirection === 'asc' ? '↑' : '↓') : '↕' }}</span></a></th>@endforeach</tr></thead><tbody>
            @foreach($tickets as $ticket)
                @php $category = $ticket->classification_key === 'EHN' ? 'ENH' : $ticket->classification_key; $priorityLabel = \App\Support\PmsPriority::label($ticket->priority); @endphp
                <tr><td><a class="pm-ticket-id" href="{{ route('pms.tickets.show', $ticket) }}">{{ $ticket->reference }}</a><a class="pm-ticket-title" href="{{ route('pms.tickets.show', $ticket) }}" title="{{ $ticket->summary }}">{{ $ticket->summary }}</a></td>
                    <td><span class="pm-ticket-type pm-ticket-type-{{ strtolower($category) }}">{{ $category }}</span></td>
                    <td>{{ $ticket->project?->name ?? 'Unassigned intake' }}@if($ticket->project)<small>{{ $ticket->project->project_reference }}</small>@endif @if($ticket->release)<small>{{ $ticket->release->release_key }}</small>@endif</td>
                    <td><span class="pm-ticket-pill pm-ticket-priority-{{ strtolower($priorityLabel) }}"><i></i>{{ $priorityLabel }}</span></td>
                    <td><span class="pm-ticket-pill pm-ticket-status-{{ \Illuminate\Support\Str::slug($ticket->status) }}"><i></i>{{ $ticket->status }}</span></td>
                    <td>{{ $ticket->developer?->name ?? $ticket->qa?->name ?? 'Unassigned' }}@if($ticket->developer && $ticket->qa && $ticket->qa_id !== $ticket->developer_id)<small>QA: {{ $ticket->qa->name }}</small>@endif</td>
                    <td><time class="pm-ticket-updated" datetime="{{ $ticket->updated_at?->toIso8601String() }}" title="{{ \App\Support\PmsDateTime::format($ticket->updated_at, true) }}"><span>{{ \App\Support\PmsDateTime::date($ticket->updated_at) }}</span><small>{{ \App\Support\PmsDateTime::time($ticket->updated_at) }}</small></time></td>
                </tr>
            @endforeach
        </tbody></table></div>
        <div class="pm-ticket-mobile-list">@foreach($tickets as $ticket)<article class="pm-ticket-mobile-card"><div class="pm-ticket-mobile-top"><a href="{{ route('pms.tickets.show', $ticket) }}">{{ $ticket->reference }}</a><span class="pm-ticket-pill pm-ticket-status-{{ \Illuminate\Support\Str::slug($ticket->status) }}">{{ $ticket->status }}</span></div><a class="pm-ticket-mobile-title" href="{{ route('pms.tickets.show', $ticket) }}">{{ $ticket->summary }}</a><div class="pm-ticket-mobile-meta"><span>{{ $ticket->project?->name ?? 'Unassigned intake' }}</span><time datetime="{{ $ticket->updated_at?->toIso8601String() }}">{{ \App\Support\PmsDateTime::date($ticket->updated_at) }} · {{ \App\Support\PmsDateTime::time($ticket->updated_at) }}</time></div></article>@endforeach</div>
        <div class="pm-ticket-results"><span>{{ $tickets->firstItem() }}–{{ $tickets->lastItem() }} of {{ $tickets->total() }} {{ $ticketHeading }}</span><span>Filtered by {{ $activeFilters->count() }} {{ \Illuminate\Support\Str::plural('filter', $activeFilters->count()) }} · Updated {{ now()->format('j M Y') }}</span></div>
        <div class="pm-ticket-bottom"><div class="pm-pagination">{{ $tickets->links() }}</div><small class="pm-ticket-export-note">CSV exports all results matching the current filters.</small></div>
    @endif
</div>
@endsection
@section('scripts')
<script>document.querySelectorAll('#pm-ticket-filter-form .pm-ticket-select select').forEach(select => select.addEventListener('change', () => select.form.requestSubmit()));document.querySelectorAll('.pm-ticket-header-actions .pm-ticket-export').forEach(link=>link.addEventListener('click',event=>{if(link.getAttribute('aria-busy')==='true'){event.preventDefault();return;}const label=link.innerHTML;link.setAttribute('aria-busy','true');link.setAttribute('aria-disabled','true');link.textContent='Preparing CSV…';setTimeout(()=>{link.innerHTML=label;link.removeAttribute('aria-busy');link.removeAttribute('aria-disabled')},3000)}));</script>
@endsection
