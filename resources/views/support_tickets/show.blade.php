@extends('admin.admin_master')
@section('admin')

<title>{{ $ticket->ticket_number }} | Support Ticket</title>

<style>
    .ticket-message {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 14px;
        background: #fff;
    }
    .ticket-message.admin-reply {
        border-color: #bfdbfe;
        background: #eff6ff;
    }
    .message-meta {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        gap: 8px;
        color: #64748b;
        font-size: 12px;
        margin-bottom: 10px;
    }
    .attachment-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #dbe3ec;
        border-radius: 999px;
        padding: 5px 10px;
        margin: 4px 4px 0 0;
        background: #fff;
        font-size: 12px;
    }
</style>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">{{ $ticket->ticket_number }}</h4>
                        <p class="text-muted mb-0">{{ $ticket->subject }}</p>
                    </div>
                    <a href="{{ route('support.tickets.index') }}" class="btn btn-secondary">Back to Tickets</a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Conversation</h5>

                        @foreach($ticket->messages as $message)
                            <div class="ticket-message {{ $message->is_admin_reply ? 'admin-reply' : '' }}">
                                <div class="message-meta">
                                    <span>
                                        <strong>{{ $message->is_admin_reply ? 'Administration' : ($message->user?->name ?? $message->user?->username ?? 'User') }}</strong>
                                        @if($message->is_admin_reply)
                                            replied
                                        @else
                                            wrote
                                        @endif
                                    </span>
                                    <span>{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                                </div>
                                <div>{!! nl2br(e($message->message)) !!}</div>

                                @if($message->attachments->isNotEmpty())
                                    <div class="mt-3">
                                        @foreach($message->attachments as $attachment)
                                            <a class="attachment-pill" href="{{ route('support.tickets.attachments.download', $attachment->id) }}">
                                                <i class="fas fa-paperclip"></i>
                                                {{ $attachment->original_filename }}
                                                <span class="text-muted">({{ $attachment->file_size_label }})</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                @if(! $ticket->isClosed())
                    <div class="card mt-4">
                        <div class="card-body">
                            <h5 class="mb-3">{{ $isAdmin ? 'Reply as Administration' : 'Add More Details' }}</h5>
                            <form action="{{ route('support.tickets.reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea name="message" id="message" rows="5" class="form-control" required>{{ old('message') }}</textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="attachments" class="form-label">Attach Images / Files</label>
                                    <input type="file"
                                           name="attachments[]"
                                           id="attachments"
                                           class="form-control"
                                           accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.zip"
                                           multiple>
                                    <div class="form-text">Optional. Add up to 5 screenshots or files, 20 MB each.</div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-reply"></i> Send Reply
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Ticket Details</h5>
                        <dl class="row mb-0">
                            <dt class="col-5">Status</dt>
                            <dd class="col-7"><span class="badge bg-{{ $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span></dd>
                            <dt class="col-5">Priority</dt>
                            <dd class="col-7"><span class="badge bg-{{ $ticket->priorityTone() }}">{{ $ticket->priorityLabel() }}</span></dd>
                            <dt class="col-5">Category</dt>
                            <dd class="col-7">{{ ucfirst($ticket->category) }}</dd>
                            <dt class="col-5">Requester</dt>
                            <dd class="col-7">{{ $ticket->requester?->name ?? $ticket->requester?->username ?? '-' }}</dd>
                            <dt class="col-5">Opened</dt>
                            <dd class="col-7">{{ $ticket->created_at?->format('Y-m-d H:i') }}</dd>
                            @if($ticket->assignedAdmin)
                                <dt class="col-5">Admin</dt>
                                <dd class="col-7">{{ $ticket->assignedAdmin?->name ?? $ticket->assignedAdmin?->username }}</dd>
                            @endif
                            @if($ticket->isClosed())
                                <dt class="col-5">Closed</dt>
                                <dd class="col-7">{{ $ticket->closed_at?->format('Y-m-d H:i') }}</dd>
                            @endif
                        </dl>

                        @if(! $ticket->isClosed())
                            <hr>
                            <form action="{{ route('support.tickets.close', $ticket->id) }}" method="POST" onsubmit="return confirm('Close this ticket? You will not be able to add more replies after closing it.');">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-lock"></i> Close Ticket
                                </button>
                            </form>
                        @else
                            <hr>
                            <div class="alert alert-secondary mb-0">This ticket is closed. It cannot be deleted or replied to.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
