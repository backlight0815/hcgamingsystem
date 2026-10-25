<article class="pm-comment {{ $reply ? 'pm-comment-reply' : '' }}" id="comment-{{ $comment->id }}">
    <div class="pm-comment-head">
        @if($comment->author?->pmsProfile?->photo_path)<img class="pm-comment-avatar" src="{{ route('pms.my-profile.photo', $comment->author) }}" alt="">
        @else<span class="pm-comment-avatar pm-comment-initial" aria-hidden="true">{{ strtoupper(mb_substr($comment->author?->name ?? '?', 0, 1)) }}</span>@endif
        <div><strong>{{ $comment->author?->name ?? 'Former member' }}</strong><small>{{ $comment->author?->pmsProfile?->job_position ?: 'Team member' }}</small></div>
        <time datetime="{{ $comment->created_at?->toIso8601String() }}">{{ \App\Support\PmsDateTime::format($comment->created_at, true) }}</time>
    </div>
    <div class="pm-comment-body">{!! $comment->safe_html !!}</div>
    @if($comment->edited_at)<small class="pm-muted">Edited {{ \App\Support\PmsDateTime::format($comment->edited_at, true) }}</small>@endif
    @foreach($comment->attachments as $attachment)<p><a href="{{ route('pms.tickets.attachments.show', [$ticket, $attachment]) }}">{{ $attachment->original_name }}</a></p>@endforeach
    <div class="pm-comment-actions">
        @if(!$reply && \App\Support\PmsTicketAccess::comment(auth()->user(), $ticket))
            <details><summary>Reply</summary><form method="post" action="{{ route('pms.tickets.comments.store', $ticket) }}" enctype="multipart/form-data" class="pm-stack">@csrf<input type="hidden" name="parent_id" value="{{ $comment->id }}"><textarea name="body" rows="3" required maxlength="30000" aria-label="Reply to {{ $comment->author?->name ?? 'comment' }}"></textarea><input type="file" name="attachments[]" multiple aria-label="Reply attachments"><button class="pm-button secondary">Post reply</button></form></details>
        @endif
        @if((int) $comment->author_id === auth()->id() || \App\Support\PmsAccess::administrator(auth()->user()))
            <details><summary>Edit</summary><form method="post" action="{{ route('pms.tickets.comments.update', [$ticket, $comment]) }}" class="pm-stack">@csrf @method('PUT')<textarea name="body" rows="3" required>{{ $comment->body }}</textarea><button class="pm-button secondary">Save edit</button></form></details>
        @endif
        @if(\App\Support\PmsTicketAccess::manage(auth()->user(), $ticket))
            <form method="post" action="{{ route('pms.tickets.comments.destroy', [$ticket, $comment]) }}">@csrf @method('DELETE')<button class="pm-link danger" onclick="return confirm('Remove this comment?')">Remove</button></form>
        @endif
    </div>
</article>
