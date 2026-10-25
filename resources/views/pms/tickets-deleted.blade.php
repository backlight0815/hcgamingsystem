@extends('pms.layout')
@section('title', 'Recently Deleted Tickets')
@section('content')
<div class="pm-heading"><div><p class="pm-eyebrow">PROJECT MANAGEMENT / TICKETS</p><h1>Recently Deleted</h1><p>Restore tickets to their project with their attachments, test cases, and review history.</p></div><a class="pm-button secondary" href="{{ route('pms.tickets.index') }}">Back to tickets</a></div>
<section class="pm-panel"><div class="pm-panel-head"><h2>Recoverable tickets</h2><span class="pm-muted">{{ $tickets->total() }} tickets</span></div>
    @forelse($tickets as $ticket)
        <article class="pm-deleted-ticket"><div><strong>{{ $ticket->reference }} · {{ $ticket->summary }}</strong><small>{{ $ticket->project?->project_reference }} · {{ $ticket->project?->name }} · Deleted {{ \App\Support\PmsDateTime::format($ticket->deleted_at, true) }}</small></div><form method="post" action="{{ route('pms.tickets.restore', $ticket->id) }}">@csrf<button class="pm-button secondary" type="submit">Restore ticket</button></form></article>
    @empty<div class="pm-empty">No tickets are waiting to be restored.</div>@endforelse
    {{ $tickets->links() }}
</section>
@endsection
