@php
    $duration = $cycle->start_date->diffInDays($cycle->target_end_date) + 1;
    $daysRemaining = today()->diffInDays($cycle->target_end_date, false);
    $completedPhases = $cycle->phases->filter(fn ($phase) => $phase->completion_percent >= 100)->count();
    $delayedPhases = $cycle->phases->filter(fn ($phase) => $phase->completion_percent < 100 && $phase->planned_end_date->isBefore(today()))->count();
    $timelinePhases = $cycle->phases->map(fn ($phase) => [
        'id' => $phase->id, 'name' => $phase->name,
        'plannedStart' => $phase->planned_start_date->toDateString(),
        'plannedEnd' => $phase->planned_end_date->toDateString(),
        'actualStart' => $phase->actual_start_date?->toDateString(),
        'actualEnd' => $phase->actual_end_date?->toDateString(),
        'progress' => (int) $phase->completion_percent,
        'signedOff' => (bool) $phase->signed_off,
        'owner' => $cycle->owner?->name,
        'delayed' => $phase->completion_percent < 100 && $phase->planned_end_date->isBefore(today()),
    ])->values();
@endphp
<section class="pm-panel pm-gantt" aria-labelledby="pm-timeline-title">
    <div class="pm-panel-head pm-gantt-head"><div><p class="pm-eyebrow">DELIVERY PLAN</p><h2 id="pm-timeline-title">Development timeline</h2><p class="pm-muted">{{ $cycle->name }} · {{ $cycle->start_date->format('d M Y') }} – {{ $cycle->target_end_date->format('d M Y') }}</p></div><div class="pm-gantt-controls" role="group" aria-label="Timeline scale"><button type="button" data-timeline-view="daily" aria-pressed="false">Day</button><button type="button" data-timeline-view="weekly" aria-pressed="true">Week</button><button type="button" data-timeline-view="monthly" aria-pressed="false">Month</button></div></div>
    <div class="pm-gantt-metrics"><div><span>Duration</span><strong>{{ $duration }} days</strong></div><div><span>Time remaining</span><strong>{{ $daysRemaining < 0 ? abs($daysRemaining).' days overdue' : $daysRemaining.' days' }}</strong></div><div><span>Overall work</span><strong>{{ $analytics['total'] > 0 ? $analytics['work'].'%' : 'No work data' }}</strong></div><div><span>Phases complete</span><strong>{{ $completedPhases }} / {{ $cycle->phases->count() }}</strong></div><div><span>Delayed phases</span><strong>{{ $delayedPhases }}</strong></div><div><span>Linked tickets</span><strong>{{ $cycle->issues->count() }}</strong></div></div>
    @if($cycle->phases->isEmpty())<div class="pm-empty">No phases are configured for this cycle. A project manager can add or revise the cycle schedule.</div>@else
    <div class="pm-gantt-legend"><span><i class="pm-legend-planned"></i> Planned</span><span><i class="pm-legend-actual"></i> Actual</span><span><i class="pm-legend-today"></i> Today</span><span><i class="pm-legend-milestone"></i> Milestone</span></div>
    <div class="pm-gantt-scroll" tabindex="0" aria-label="Scroll development timeline horizontally"><div id="pm-gantt-render" class="pm-gantt-render" data-start="{{ $cycle->start_date->toDateString() }}" data-end="{{ $cycle->target_end_date->toDateString() }}" data-today="{{ today()->toDateString() }}" data-phases='@json($timelinePhases)'></div></div>
    <p class="pm-muted pm-gantt-foot">Bars show the planned window; a dashed line shows recorded actual dates. End diamonds mark planned milestones. Select a phase to edit its record below.</p>
    @endif
</section>
