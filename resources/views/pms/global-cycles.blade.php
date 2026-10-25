@extends('pms.layout')
@section('title', 'Development cycles')
@section('content')
<div class="pm-heading"><div><p class="pm-eyebrow">ORGANIZATION DELIVERY</p><h1>Development cycles</h1><p>Plan, track and manage your organization's development and release lifecycle.</p></div><div class="pm-heading-actions">@if($canManage)<a class="pm-button" href="#create-cycle">Create cycle</a>@endif @if(\App\Support\PmsAccess::administrator(auth()->user()))<a class="pm-button secondary" href="#cycle-settings">Cycle settings</a>@endif</div></div>
<div class="pm-global-kpis">
@foreach(['Active projects' => $summary['active_projects'], 'Open tickets' => $summary['open_tickets'], 'QAT' => $summary['qat'], 'REQ' => $summary['req'], 'ENH' => $summary['enh'], 'PRD' => $summary['prd'], 'Critical open' => $summary['critical'], 'Overdue' => $summary['overdue']] as $label => $value)
<div class="pm-global-kpi"><span>{{ $label }}</span><strong>{{ number_format($value) }}</strong></div>
@endforeach
</div>
@if($current)
@php($currentStage = $current->stages->first(fn($stage) => $stage->start_date->toDateString() <= $today->toDateString() && $stage->end_date->toDateString() >= $today->toDateString()))
@php($scheduleDays = max(1,$current->start_date->diffInDays($current->end_date)+1))
@php($elapsedDays = max(0,min($scheduleDays,$current->start_date->diffInDays(today())+1)))
<section class="pm-panel pm-current-cycle"><div><p class="pm-eyebrow">CURRENT ORGANIZATION CYCLE</p><h2><a href="{{ route('pms.global-cycles.show', $current) }}">{{ $current->cycle_key }} · {{ $current->name }}</a></h2><p>{{ $current->start_date->format('d M Y') }} – {{ $current->end_date->format('d M Y') }} · {{ $current->owner?->name ?? 'No owner assigned' }}</p></div><div class="pm-current-side"><span class="pm-pill">{{ $current->status }}</span><strong>{{ max(0,$today->diffInDays($current->end_date,false)) }} days remaining</strong><small>{{ $currentStage?->name ?? 'Outside stage window' }}</small></div><div class="pm-progress-track"><span style="width:{{ round($elapsedDays/$scheduleDays*100) }}%"></span></div><small>Schedule elapsed: {{ $elapsedDays }} of {{ $scheduleDays }} calendar days. Ticket progress is shown on the cycle page.</small></section>
@else<div class="pm-panel pm-empty">No current cycle. The scheduled job creates one when automatic generation is enabled and its anchor date arrives.</div>@endif
<section class="pm-panel"><div class="pm-panel-head"><h2>Cycle directory</h2><span class="pm-muted">{{ $cycles->total() }} cycles</span></div><nav class="pm-cycle-tabs" aria-label="Cycle views">@foreach(['current' => 'Current', 'upcoming' => 'Upcoming', 'completed' => 'Completed', 'all' => 'All cycles'] as $view => $label)<a href="{{ route('pms.global-cycles.index', $view === 'all' ? [] : ['view' => $view]) }}" @if(request('view','all') === $view) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
@if($cycles->isEmpty())<div class="pm-empty">No cycles in this view.</div>@else<div class="pm-global-cycle-list">@foreach($cycles as $cycle)<a href="{{ route('pms.global-cycles.show',$cycle) }}" class="pm-global-cycle-row"><div><strong>{{ $cycle->cycle_key }}</strong><h3>{{ $cycle->name }}</h3><small>{{ $cycle->start_date->format('d M Y') }} – {{ $cycle->end_date->format('d M Y') }} · {{ $cycle->owner?->name ?? 'Unassigned owner' }}</small></div><div><span class="pm-pill">{{ $cycle->status }}</span><small>{{ $cycle->projects_count }} projects · {{ $cycle->tickets_count }} tickets</small></div></a>@endforeach</div><div class="pm-pagination">{{ $cycles->links() }}</div>@endif</section>
@if($canManage)@include('pms.partials.global-cycle-create')@endif
@if(\App\Support\PmsAccess::administrator(auth()->user()))@include('pms.partials.global-cycle-settings')@endif
@endsection
