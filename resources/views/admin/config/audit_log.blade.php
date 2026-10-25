@extends('admin.admin_master')
@section('admin')
<div class="page-content">
    <div class="container-fluid">
        <h4>Administrative audit log</h4>
        <p>Recent sign-ins, role changes, and module configuration changes.</p>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Target</th><th>Result</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->created_at }}</td>
                            <td>{{ $log->actor_name ?? 'Deleted user' }} #{{ $log->actor_id }}</td>
                            <td>{{ $log->action }}</td>
                            <td>{{ $log->target_type }} #{{ $log->target_id }}</td>
                            <td>{{ $log->result }}</td>
                            <td>{{ $log->details }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No administrative events yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
</div>
@endsection
