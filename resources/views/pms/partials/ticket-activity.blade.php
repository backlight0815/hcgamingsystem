<section class="pm-panel" id="activity">
    <div class="pm-panel-head"><h2>Activity</h2><span class="pm-muted">{{ $activity->total() }} recorded events</span></div>
    <nav class="pm-activity-filters" aria-label="Activity filters">
        @foreach(['all'=>'All activity','comments'=>'Comments','attachments'=>'Attachments','status'=>'Status','assignment'=>'Assignments','priority'=>'Priority','relationships'=>'Relationships','system'=>'System'] as $filter=>$label)
            <a href="{{ route('pms.tickets.show',[$ticket,'activity'=>$filter,'activity_order'=>request('activity_order')]) }}#activity" @if($activityFilter===$filter) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <div class="pm-activity-order"><a href="{{ route('pms.tickets.show',[$ticket,'activity'=>$activityFilter,'activity_order'=>'newest']) }}#activity" @if(request('activity_order')!=='oldest') aria-current="true" @endif>Newest first</a> · <a href="{{ route('pms.tickets.show',[$ticket,'activity'=>$activityFilter,'activity_order'=>'oldest']) }}#activity" @if(request('activity_order')==='oldest') aria-current="true" @endif>Oldest first</a></div>
    <div class="pm-timeline">
        @forelse($activity as $event)
            @php
                $meta = json_decode($event->metadata ?? '{}', true) ?: [];
                $time = \Carbon\Carbon::parse($event->created_at, config('app.timezone', 'UTC'));
                $presenter = \App\Support\PmsActivityPresenter::class;
            @endphp
            <div class="pm-activity-event"><span class="pm-activity-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($event->actor_name ?: 'S', 0, 1)) }}</span><div>
                <strong>{{ $event->actor_name ?: 'System' }}</strong> {{ $presenter::description($event) }}
                <small title="{{ $time->toIso8601String() }}">{{ \App\Support\PmsDateTime::format($time, true) }}</small>
                @if($event->field_name && ($event->old_value !== null || $event->new_value !== null))
                    <span class="pm-activity-values"><b>{{ \Illuminate\Support\Str::limit($presenter::value($event->field_name, $event->old_value, $meta, 'old', $activityLookups), 100) }}</b> → <b>{{ \Illuminate\Support\Str::limit($presenter::value($event->field_name, $event->new_value, $meta, 'new', $activityLookups), 100) }}</b></span>
                @endif
                @if($event->event_type==='relationships' && isset($linkedTickets[$meta['other_ticket_id'] ?? 0]))<a href="{{ route('pms.tickets.show', $linkedTickets[$meta['other_ticket_id']]) }}">{{ $linkedTickets[$meta['other_ticket_id']]->reference }}</a>@endif
                @if($event->event_type==='comments' && ($meta['action'] ?? '')!=='removed')<a href="#comment-{{ $meta['comment_id'] ?? 0 }}">View comment</a>@endif
                @if($event->event_type==='attachments' && ($meta['action'] ?? '')==='added')<a href="{{ route('pms.tickets.attachments.show',[$ticket,$meta['attachment_id'] ?? 0]) }}">Open attachment</a>@endif
            </div></div>
        @empty<p class="pm-muted">No recorded activity matches this filter.</p>@endforelse
    </div>
    {{ $activity->links() }}
</section>
