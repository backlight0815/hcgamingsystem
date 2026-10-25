@extends('admin.admin_master')
@section('admin')

<title>AI Knowledge Base | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">AI Knowledge Base</h4>
                        <p class="text-muted mb-0">Manage the system knowledge used by the AI Assistant.</p>
                    </div>
                    <a href="{{ route('admin.ai_knowledge_base.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Record
                    </a>
                    <a href="{{ route('admin.ai_settings.index') }}" class="btn btn-outline-primary">AI settings & feedback</a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.ai_knowledge_base.index') }}" class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search title or content">
                    </div>
                    <div class="col-md-3">
                        <select name="module" class="form-control">
                            <option value="">All Modules</option>
                            @foreach($modules as $value => $label)
                                <option value="{{ $value }}" {{ request('module') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
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
                                <th>Module</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($knowledgeBases as $knowledgeBase)
                                <tr>
                                    <td>{{ $knowledgeBase->module_label }}</td>
                                    <td>
                                        <strong>{{ $knowledgeBase->title }}</strong>
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($knowledgeBase->content, 140) }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $knowledgeBase->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $knowledgeBase->status_label }}
                                        </span>
                                    </td>
                                    <td>{{ $knowledgeBase->updated_at?->format('Y-m-d H:i') }}</td>
                                    <td class="text-nowrap">
                                        <a href="{{ route('admin.ai_knowledge_base.edit', $knowledgeBase->id) }}" class="btn btn-info btn-sm">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.ai_knowledge_base.destroy', $knowledgeBase->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Unpublish this AI knowledge base record?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Unpublish">
                                                <i class="fas fa-eye-slash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No AI knowledge base records created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $knowledgeBases->links('vendor.pagination.bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
