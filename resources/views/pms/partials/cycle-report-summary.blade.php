@php
    $ticketStatuses = $cycle->issues->countBy('status');
    $blockedCount = $cycle->issues->filter(fn ($ticket) => $blockedIds->has($ticket->id))->count();
    $reopenedCount = $cycle->issues->filter(fn ($ticket) => in_array($ticket->status, ['Reopen', 'Reopened'], true))->count();
    $productionOutstanding = $stages[$cycle->id]['production']['total'] - $stages[$cycle->id]['production']['completed'];
@endphp
<div class="pm-cycle-report-summary">
    <div>
        <strong>Current ticket statuses</strong>
        <div class="pm-cycle-statuses">
            @forelse($ticketStatuses as $status => $count)
                <span>{{ $status }} <b>{{ $count }}</b></span>
            @empty
                <span>No tickets in this selection</span>
            @endforelse
        </div>
    </div>
    <div class="pm-cycle-report-summary-counts">
        <span><b>{{ $productionOutstanding }}</b> production items outstanding</span>
        <span><b>{{ $blockedCount }}</b> currently blocked</span>
        <span><b>{{ $reopenedCount }}</b> currently reopened</span>
        <span><b>{{ $cycle->issues->sum('reopen_count') }}</b> reopen events</span>
    </div>
</div>
