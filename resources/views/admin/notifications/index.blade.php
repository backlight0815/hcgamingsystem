@extends('admin.admin_master')
@section('admin')

<title>Manage Notifications | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Manage Notifications</h4>
                        <p class="text-muted mb-0">Publish in-app updates to selected roles.</p>
                    </div>
                    <a href="{{ route('admin.notifications.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Notification
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Audience</th>
                                <th>Published</th>
                                <th>Created By</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($notifications as $notification)
                                <tr>
                                    <td>
                                        <strong>{{ $notification->title }}</strong>
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($notification->message, 100) }}</div>
                                    </td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $notification->type)) }}</td>
                                    <td>
                                        @if($notification->user_id)
                                            User #{{ $notification->user_id }}
                                        @elseif($notification->target_roles)
                                            Roles: {{ implode(', ', $notification->target_roles) }}
                                        @else
                                            All users
                                        @endif
                                    </td>
                                    <td>{{ $notification->published_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ $notification->creator?->username ?? $notification->creator?->name ?? '-' }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('admin.notifications.edit', $notification->id) }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.notifications.destroy', $notification->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this notification?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No notifications created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $notifications->links('vendor.pagination.bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
