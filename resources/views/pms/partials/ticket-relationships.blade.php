<section class="pm-panel" id="relationships">
    <div class="pm-panel-head"><h2>Linked tickets &amp; relationships</h2><span class="pm-muted">{{ $links->count() }} links</span></div>
    @if($blockedBy->isNotEmpty())<div class="pm-alert error">Blocked by @foreach($blockedBy as $dependency)<a href="{{ route('pms.tickets.show',$dependency) }}">{{ $dependency->reference }}</a> ({{ $dependency->status }})@unless($loop->last), @endunless @endforeach</div>@endif
    @foreach(['qat_dependency' => 'QAT dependencies', 'sub_issue' => 'Sub issues', 'related_to' => 'Related tickets', 'other' => 'Other links'] as $group => $heading)
        @php($groupLinks = $links->filter(fn ($link) => $group === 'other' ? !in_array($link->relation,['qat_dependency','sub_issue','related_to','relates_to']) : ($group === 'related_to' ? in_array($link->relation,['related_to','relates_to']) : $link->relation === $group)))
        @if($groupLinks->isNotEmpty())
            <h3>{{ $heading }}</h3>
            <div class="pm-table-wrap"><table class="pm-table pm-related-tickets-table"><thead><tr><th>Relationship</th><th>Ticket</th><th>Title</th><th>Type</th><th>Status</th><th>Priority</th><th>Assignee</th><th>Updated</th><th></th></tr></thead><tbody>
            @foreach($groupLinks as $link)
                @php($outgoing = (int)$link->source_issue_id === $ticket->id)
                @php($otherId = $outgoing ? $link->target_issue_id : $link->source_issue_id)
                @if(isset($linkedTickets[$otherId]))
                    @php($other = $linkedTickets[$otherId])
                    <tr><td>{{ match($link->relation) { 'qat_dependency' => $outgoing ? 'Depends on' : 'Blocks', 'sub_issue' => $outgoing ? 'Child' : 'Parent', 'related_to','relates_to' => 'Related to', default => str_replace('_',' ',$link->relation) } }}</td><td><a href="{{ route('pms.tickets.show',$other) }}">{{ $other->reference }}</a></td><td class="pm-related-ticket-title" title="{{ $other->summary }}">{{ $other->summary }}</td><td>{{ $other->classification_key }}</td><td>{{ $other->status }}</td><td>{{ \App\Support\PmsPriority::label($other->priority) }}</td><td>{{ $other->developer?->name ?? 'Unassigned' }}</td><td><time class="pm-related-ticket-updated" datetime="{{ $other->updated_at?->toIso8601String() }}"><span>{{ \App\Support\PmsDateTime::date($other->updated_at) }}</span><small>{{ \App\Support\PmsDateTime::time($other->updated_at) }}</small></time></td><td>@if(\App\Support\PmsTicketAccess::manage(auth()->user(),$ticket) && \App\Support\PmsTicketAccess::manage(auth()->user(),$other))<form method="post" action="{{ route('pms.tickets.links.destroy',[$ticket,$link->id]) }}">@csrf @method('DELETE')<button class="pm-link danger" aria-label="Remove relationship with {{ $other->reference }}">Remove</button></form>@endif</td></tr>
                @endif
            @endforeach
            </tbody></table></div>
        @endif
    @endforeach
    @if($links->isEmpty())<p class="pm-muted">No linked tickets yet.</p>@endif
    @if(\App\Support\PmsTicketAccess::comment(auth()->user(),$ticket))
        <details class="pm-inline-editor"><summary>Link an existing ticket</summary>
            <button type="button" class="pm-button secondary" data-ticket-search-open>Search tickets</button>
            <form method="post" action="{{ route('pms.tickets.links.store',$ticket) }}" class="pm-form-grid">@csrf
                <label>Ticket reference <input id="pm-link-target" name="target_issue_key" required placeholder="sfqat0001" aria-label="Linked ticket reference"></label>
                <label>Relationship <select name="relation"><option value="qat_dependency">This ticket depends on</option><option value="sub_issue">Add as child issue</option><option value="related_to">Related to</option><option value="blocks">Blocks (legacy)</option></select></label>
                <button class="pm-button secondary">Add relationship</button>
            </form>
            <p class="pm-muted">Use ticket search to find an existing key. A dependency points from this ticket to the ticket it needs first.</p>
        </details>
        <dialog id="pm-ticket-search" class="pm-ticket-search-dialog" data-search-url="{{ route('pms.tickets.index') }}" aria-label="Search tickets"><form method="dialog"><button class="pm-link" aria-label="Close search">Close</button></form><h3>Find a ticket</h3><label>Search by key or title<input id="pm-ticket-search-query" placeholder="Type at least two characters"></label><div id="pm-ticket-search-results" role="list"></div></dialog>
    @endif
</section>
