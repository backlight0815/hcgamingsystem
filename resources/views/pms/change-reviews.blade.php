@extends('pms.layout')
@section('title', 'Change reviews')
@section('content')
<header class="pm-page-hero"><div><p class="pm-eyebrow">APPROVALS</p><h1>Change reviews</h1><p>Review proposed project and requirement changes before they affect published work.</p></div></header>
<form method="get" class="pm-toolbar"><label>Status<select name="status" onchange="this.form.submit()"><option value="">All</option>@foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled'] as $code=>$label)<option value="{{ $code }}" @selected(request('status')===$code)>{{ $label }}</option>@endforeach</select></label></form>
@forelse($reviews as $review)
@php($before = json_decode($review->original_snapshot ?: '{}', true) ?: [])
@php($changes = json_decode($review->proposed_changes ?: '{}', true) ?: [])
<section class="pm-panel pm-review-card">
    <div class="pm-panel-head"><div><p class="pm-eyebrow">{{ strtoupper($review->subject_type) }} · {{ strtoupper($review->request_type) }} · Revision #{{ $review->revision }}</p><h2>@if((int)$review->status_code !== \App\Support\PmsReviewStatus::APPROVED || $review->request_type !== 'delete')<a href="{{ $review->subject_type === 'project' ? route('pms.projects.show', $review->subject_id) : route('pms.tickets.show', $review->subject_id) }}">{{ $review->project_key }} · {{ $review->subject_type }} #{{ $review->subject_id }}</a>@else{{ $review->project_key }} · {{ $review->subject_type }} #{{ $review->subject_id }}@endif</h2></div><span class="pm-pill">{{ \App\Support\PmsReviewStatus::label((int)$review->status_code) }}</span></div>
    <p class="pm-muted">Requested by {{ $review->submitter_name }} · Reviewer {{ $review->reviewer_name }} · {{ \App\Support\PmsDateTime::format($review->submitted_at) }}</p>
    @if($review->request_type === 'delete')<p>Requested deletion of {{ $before['key'] ?? $review->subject_type }}. The record remains available until approval.</p>@else
    <div class="pm-table-wrap"><table class="pm-table"><thead><tr><th>Field</th><th>Published</th><th>Proposed</th></tr></thead><tbody>@foreach($changes as $field=>$value)<tr><th>{{ str_replace('_',' ',ucwords($field,'_')) }}</th><td>{{ is_scalar($before[$field] ?? null) ? ($before[$field] ?? '—') : json_encode($before[$field] ?? null) }}</td><td>{{ is_scalar($value) ? ($value ?? '—') : json_encode($value) }}</td></tr>@endforeach</tbody></table></div>@endif
    @if($review->review_comment)<p><strong>Decision note:</strong> {{ $review->review_comment }}</p>@endif
    @if((int)$review->status_code === \App\Support\PmsReviewStatus::PENDING)
        @if((int)$review->submitted_by === auth()->id())<form method="post" action="{{ route('pms.change-reviews.cancel', $review->id) }}">@csrf<button class="pm-link danger">Cancel request</button></form>@endif
        @if((int)$review->submitted_by !== auth()->id() && (\App\Support\PmsAccess::administrator(auth()->user()) || (int)$review->reviewer_id === auth()->id()))<form method="post" action="{{ route('pms.change-reviews.decide', $review->id) }}" class="pm-form-grid pm-section-form">@csrf<label>Decision<select name="decision" required><option value="approved">Approve</option><option value="rejected">Reject</option></select></label><label class="wide">Review comment<textarea name="comment" required minlength="3" rows="2"></textarea></label><button class="pm-button secondary">Record decision</button></form>@endif
    @endif
</section>
@empty<div class="pm-panel pm-empty">No change requests match this view.</div>@endforelse
{{ $reviews->links() }}
@endsection
