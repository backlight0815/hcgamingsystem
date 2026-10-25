@php
    $galleryFiles = $galleryFiles ?? $ticket->attachments;
    $galleryTicket = $ticket ?? null;
    $galleryProject = $project ?? null;
@endphp
<section class="pm-panel pm-gallery" id="attachments">
    <div class="pm-panel-head"><div><p class="pm-eyebrow">PRIVATE EVIDENCE</p><h2>Attachments <span class="pm-count">{{ $galleryFiles->count() }}</span></h2></div></div>
    @if($galleryTicket ? \App\Support\PmsTicketAccess::comment(auth()->user(), $galleryTicket) : ($galleryProject && !$galleryProject->archived_at && \App\Support\PmsAccess::allows(auth()->user(), $galleryProject, 'edit_issue') && $attachmentTargets->isNotEmpty()))
    <form class="pm-gallery-upload" method="post" enctype="multipart/form-data" action="{{ $galleryTicket ? route('pms.tickets.attachments.store', $galleryTicket) : route('pms.projects.attachments.store', $galleryProject) }}">
        @csrf
        @if($galleryProject)
        <label>Associate with ticket <input name="ticket_key" list="pm-attachment-ticket-keys" placeholder="Enter a ticket reference, e.g. sfreq0001" required><datalist id="pm-attachment-ticket-keys">@foreach($attachmentTargets as $issue)<option value="{{ $issue->reference }}">{{ $issue->summary }}</option>@endforeach</datalist></label>
        @endif
        <label class="pm-gallery-drop">Drop files here or <span>browse files</span><input type="file" name="attachments[]" multiple required aria-label="Select evidence files"></label>
        <div class="pm-gallery-upload-actions"><small>Images, PDF, Office, text, ZIP, and supported video. Up to 10 files per upload.</small><button class="pm-button secondary">Upload files</button></div>
        <progress max="100" value="0" hidden aria-label="Upload progress"></progress><p class="pm-gallery-error" role="alert" hidden></p>
    </form>
    @elseif($galleryProject && $attachmentTargets->isEmpty())<p class="pm-muted">Create a project ticket before adding evidence.</p>
    @endif
    @if($galleryFiles->isEmpty())<div class="pm-empty">No evidence has been uploaded yet.</div>@else
    <div class="pm-gallery-grid">
        @foreach($galleryFiles as $attachment)
        @php
            $fileTicket = $galleryTicket ?? $attachment->issue;
            $ext = strtolower($attachment->extension);
            $isImage = in_array($ext, ['png','jpg','jpeg','webp','gif'], true) && str_starts_with($attachment->mime_type, 'image/');
            $kind = $isImage ? 'image' : (in_array($ext, ['pdf','mp4','webm'], true) ? ($ext === 'pdf' ? 'pdf' : 'video') : 'file');
            $fileUrl = route('pms.tickets.attachments.show', [$fileTicket, $attachment]);
            $previewUrl = route('pms.tickets.attachments.show', [$fileTicket, $attachment, 'preview' => 1]);
        @endphp
        <article class="pm-gallery-card">
            @if($kind !== 'file')<button class="pm-gallery-open pm-gallery-visual" type="button" data-kind="{{ $kind }}" data-preview="{{ $previewUrl }}" data-download="{{ $fileUrl }}" data-name="{{ $attachment->original_name }}" aria-label="Preview {{ $attachment->original_name }}">
                @if($isImage)<img src="{{ route('pms.tickets.attachments.thumbnail', [$fileTicket, $attachment]) }}" loading="lazy" alt="Thumbnail of {{ $attachment->original_name }}" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="pm-gallery-icon" hidden>IMAGE</span>
                @elseif($kind === 'pdf')<span class="pm-gallery-icon pdf" aria-hidden="true">▤<small>PDF</small></span>
                @else<span class="pm-gallery-icon video" aria-hidden="true">▶<small>VIDEO</small></span>@endif
            </button>@else<div class="pm-gallery-visual"><span class="pm-gallery-icon {{ $ext }}" aria-hidden="true">{{ in_array($ext, ['doc','docx']) ? '▤' : (in_array($ext, ['xls','xlsx','csv']) ? '▦' : ($ext === 'zip' ? '▣' : '▤')) }}<small>{{ strtoupper($ext) }}</small></span></div>@endif
            <div class="pm-gallery-body"><strong title="{{ $attachment->original_name }}">{{ $attachment->original_name }}</strong><small>{{ strtoupper($ext) }} · {{ number_format($attachment->size_bytes / 1024, 1) }} KB · {{ $attachment->created_at?->format('d M Y') }}</small><small>Uploaded by {{ $attachment->uploader?->name ?? 'Former member' }}@if($attachment->comment_id) · Comment #{{ $attachment->comment_id }}@endif @if($galleryProject) · <a href="{{ route('pms.tickets.show', $fileTicket) }}">{{ $fileTicket->reference }}</a>@endif</small>
                <div class="pm-gallery-actions">@if($kind !== 'file')<button type="button" class="pm-link pm-gallery-open" data-kind="{{ $kind }}" data-preview="{{ $previewUrl }}" data-download="{{ $fileUrl }}" data-name="{{ $attachment->original_name }}">Preview</button>@endif<a href="{{ $fileUrl }}">Download</a><details><summary aria-label="More actions for {{ $attachment->original_name }}">More</summary><div><button class="pm-gallery-copy" type="button" data-url="{{ $fileUrl }}">Copy link</button>@if(\App\Support\PmsTicketAccess::manage(auth()->user(), $fileTicket))<form method="post" action="{{ route('pms.tickets.attachments.destroy', [$fileTicket, $attachment]) }}" onsubmit="return confirm('Remove this attachment?')">@csrf @method('DELETE')<button class="danger">Delete</button></form>@endif</div></details></div>
            </div>
        </article>
        @endforeach
    </div>
    @endif
</section>
<dialog class="pm-gallery-dialog" aria-label="Attachment preview"><div class="pm-gallery-dialog-head"><strong data-gallery-name></strong><div><button type="button" data-gallery-zoom>Zoom</button><a data-gallery-download href="#">Download</a><button type="button" data-gallery-close>Close</button></div></div><div class="pm-gallery-stage" data-gallery-stage></div><div class="pm-gallery-nav"><button type="button" data-gallery-prev>Previous image</button><button type="button" data-gallery-next>Next image</button></div></dialog>
