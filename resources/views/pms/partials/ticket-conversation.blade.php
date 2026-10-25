<div class="pm-conversation" data-pms-tabs>
    <nav class="pm-conversation-tabs" role="tablist" aria-label="Ticket collaboration">
        <button type="button" role="tab" data-pms-tab="discussion" aria-controls="discussion">Discussion <span class="pm-count">{{ $ticket->comments()->count() }}</span></button>
        <button type="button" role="tab" data-pms-tab="activity" aria-controls="activity">Activity <span class="pm-count">{{ $activity->total() }}</span></button>
        <button type="button" role="tab" data-pms-tab="attachments" aria-controls="attachments">Attachments <span class="pm-count">{{ $ticket->attachments->where('category', 'evidence')->count() }}</span></button>
    </nav>
    <div data-pms-tab-panel="discussion">
        <section class="pm-panel" id="discussion"><div class="pm-panel-head"><div><p class="pm-eyebrow">TEAM CONVERSATION</p><h2>Discussion</h2></div><div><a href="{{ route('pms.tickets.show', [$ticket, 'comments' => 'oldest']) }}#discussion">Oldest first</a> · <a href="{{ route('pms.tickets.show', [$ticket, 'comments' => 'newest']) }}#discussion">Newest first</a></div></div>
            @forelse($comments as $comment)
                @include('pms.partials.ticket-comment', ['comment' => $comment, 'reply' => false])
                @foreach($comment->replies as $answer)
                    @include('pms.partials.ticket-comment', ['comment' => $answer, 'reply' => true])
                @endforeach
            @empty<div class="pm-empty">No discussion yet. Start with a question or update for the team.</div>@endforelse
            {{ $comments->links() }}
            @if(\App\Support\PmsTicketAccess::comment(auth()->user(), $ticket))
                <form method="post" action="{{ route('pms.tickets.comments.store', $ticket) }}" enctype="multipart/form-data" class="pm-stack pm-comment-composer">@csrf
                    <label>Add a comment <textarea name="body" rows="5" required maxlength="30000" placeholder="Share an update. Markdown is supported; mention a teammate with @username."></textarea></label>
                    <label>Attach evidence <input type="file" name="attachments[]" multiple></label>
                    <button class="pm-button">Post comment</button>
                </form>
            @endif
        </section>
    </div>
    <div data-pms-tab-panel="activity" hidden>@include('pms.partials.ticket-activity')</div>
    <div data-pms-tab-panel="attachments" hidden>@include('pms.attachment-preview', ['galleryFiles' => $ticket->attachments->where('category', 'evidence')])</div>
</div>
