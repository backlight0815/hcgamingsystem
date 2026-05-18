@extends('admin.admin_master')
@section('admin')

<title>Support Tickets | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">{{ $isAdmin ? 'Support Ticket Desk' : 'My Support Tickets' }}</h4>
                        <p class="text-muted mb-0">Ask trading questions, report website issues, and keep the conversation in one place.</p>
                    </div>
                    <a href="{{ route('support.tickets.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Open Ticket
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card bg-warning text-dark text-center p-3">
                    <h6 class="mb-1">Open</h6>
                    <h3 class="mb-0">{{ $metrics['open'] }}</h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-success text-white text-center p-3">
                    <h6 class="mb-1">Admin Replied</h6>
                    <h3 class="mb-0">{{ $metrics['answered'] }}</h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-secondary text-white text-center p-3">
                    <h6 class="mb-1">Closed</h6>
                    <h3 class="mb-0">{{ $metrics['closed'] }}</h3>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card bg-danger text-white text-center p-3">
                    <h6 class="mb-1">Urgent Active</h6>
                    <h3 class="mb-0">{{ $metrics['urgent'] }}</h3>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('support.tickets.index') }}" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label" for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" value="{{ $search }}" placeholder="Ticket number, subject, or category">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All statuses</option>
                            <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                            <option value="answered" {{ request('status') === 'answered' ? 'selected' : '' }}>Admin Replied</option>
                            <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="priority">Priority</label>
                        <select name="priority" id="priority" class="form-select">
                            <option value="">All priorities</option>
                            @foreach($priorities as $value => $label)
                                <option value="{{ $value }}" {{ request('priority') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ticket</th>
                                @if($isAdmin)
                                    <th>Requester</th>
                                @endif
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Files</th>
                                <th>Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tickets as $ticket)
                                <tr>
                                    <td><strong>{{ $ticket->ticket_number }}</strong></td>
                                    @if($isAdmin)
                                        <td>
                                            {{ $ticket->requester?->name ?? $ticket->requester?->username ?? '-' }}
                                            <div class="text-muted small">{{ $ticket->requester?->email }}</div>
                                        </td>
                                    @endif
                                    <td>
                                        <strong>{{ $ticket->subject }}</strong>
                                        <div class="text-muted small">{{ ucfirst($ticket->category) }}</div>
                                        @if($ticket->latestMessage)
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($ticket->latestMessage->message, 90) }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-{{ $ticket->priorityTone() }}">{{ $ticket->priorityLabel() }}</span></td>
                                    <td><span class="badge bg-{{ $ticket->statusTone() }}">{{ $ticket->statusLabel() }}</span></td>
                                    <td>{{ $ticket->attachments_count }}</td>
                                    <td>{{ $ticket->updated_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <a href="{{ route('support.tickets.show', $ticket->id) }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-comments"></i> Open
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isAdmin ? 8 : 7 }}" class="text-center text-muted py-4">No tickets found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $tickets->links('vendor.pagination.bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
