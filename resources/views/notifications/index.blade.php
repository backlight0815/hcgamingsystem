@extends('admin.admin_master')
@section('admin')

<title>Notifications | HC Gaming Studio</title>

<style>
    .notification-shell { color: #1f2937; }
    .notification-item {
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        background: #fff;
        padding: 16px;
        margin-bottom: 12px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
    }
    .notification-item.unread {
        border-color: #93c5fd;
        background: #eff6ff;
    }
    .notification-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
    }
    .notification-head h5 { margin: 0; color: #0f172a; font-weight: 700; }
    .notification-message { margin: 8px 0 0; color: #475569; }
    .notification-meta { color: #64748b; font-size: 12px; margin-top: 8px; }
</style>

<div class="page-content notification-shell">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Notifications</h4>
                        <p class="text-muted mb-0">Role-based updates for trading, resources, verification, and support tickets.</p>
                    </div>
                    <form action="{{ route('notifications.read_all') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary">Mark All Read</button>
                    </form>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @forelse($notifications as $notification)
            @php($isRead = $notification->isReadBy(auth()->user()))
            <div class="notification-item {{ $isRead ? '' : 'unread' }}">
                <div class="notification-head">
                    <div>
                        <h5>{{ $notification->title }}</h5>
                        <div class="notification-meta">
                            {{ ucfirst(str_replace('_', ' ', $notification->type)) }}
                            <span class="mx-1">.</span>
                            {{ $notification->published_at?->diffForHumans() ?? $notification->created_at?->diffForHumans() }}
                            @if(! $isRead)
                                <span class="badge bg-primary ms-2">New</span>
                            @endif
                        </div>
                    </div>
                    <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-sm {{ $notification->action_url ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $notification->action_url ? 'Open' : 'Mark Read' }}
                        </button>
                    </form>
                </div>
                <p class="notification-message">{{ $notification->message }}</p>
            </div>
        @empty
            <div class="card">
                <div class="card-body text-center text-muted py-5">
                    <h5 class="text-dark">No notifications yet</h5>
                    <p class="mb-0">Updates for your role will appear here.</p>
                </div>
            </div>
        @endforelse

        <div class="mt-3">
            {{ $notifications->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>

@endsection
