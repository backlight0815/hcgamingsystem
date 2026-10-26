@extends('admin.admin_master')
@section('admin')
<title>AI System Prompt | HC TradingHub</title>
<div class="page-content"><div class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h3>AI System Prompt</h3><p class="text-muted mb-0">Server-side instructions are versioned. New drafts require review and approval before publication.</p></div><a class="btn btn-outline-secondary" href="{{ route('admin.ai_training.index') }}">Back to AI Training</a></div>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <div class="row g-3"><div class="col-xl-8"><div class="card"><div class="card-body">
        <h4>Current published policy · v{{ $current['version'] }}</h4>
        <p class="text-muted small">Create a revised copy below. Client messages and retrieved text cannot edit these instructions.</p>
        <form method="POST" action="{{ route('admin.ai_prompts.store') }}">@csrf
            <label for="promptContent" class="form-label">New draft content</label>
            <textarea id="promptContent" name="content" class="form-control font-monospace" rows="24" minlength="1000" maxlength="20000" required>{{ old('content', $current['content']) }}</textarea>
            <div class="form-text">Keep the safety, provenance, risk and data freshness rules. Changes do not reach customers until publication.</div>
            <button class="btn btn-primary mt-3" type="submit">Save new draft</button>
        </form>
    </div></div></div><div class="col-xl-4"><div class="card"><div class="card-body"><h4>Version history</h4>
        @forelse($versions as $item)<div class="border rounded p-3 mb-2"><strong>v{{ $item->version }}</strong> <span class="badge {{ $item->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ str_replace('_',' ',ucfirst($item->status)) }}</span><div class="small text-muted">{{ \Carbon\Carbon::parse($item->updated_at)->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i') }} MYT</div>
            <details class="mt-2"><summary>Inspect instructions</summary><pre class="small p-2 bg-light mt-2" style="white-space:pre-wrap;max-height:300px;overflow:auto">{{ $item->content }}</pre></details>
            @if(in_array($item->status,['draft','pending_review','approved']))
                <form method="POST" action="{{ route('admin.ai_prompts.transition',$item->id) }}" class="mt-2">@csrf
                    <input type="hidden" name="action" value="{{ ['draft'=>'submit','pending_review'=>'approve','approved'=>'publish'][$item->status] }}">
                    <button class="btn btn-sm btn-outline-primary">{{ ['draft'=>'Submit for review','pending_review'=>'Approve','approved'=>'Publish'][$item->status] }}</button>
                </form>
            @endif
        </div>@empty<p class="text-muted">No database revisions yet. Seed the provided baseline prompt before launch.</p>@endforelse
        {{ $versions->links('vendor.pagination.bootstrap-4') }}
    </div></div></div></div>
</div></div>
@endsection
