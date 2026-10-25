@if($changeReviews->isNotEmpty())
<section class="pm-panel pm-review-history"><div class="pm-panel-head"><h2>Change review history</h2><a href="{{ route('pms.change-reviews.index') }}">Open approvals</a></div>
@foreach($changeReviews as $changeReview)<div class="pm-event"><strong>Revision #{{ $changeReview->revision }} · {{ ucfirst($changeReview->request_type) }} · {{ \App\Support\PmsReviewStatus::label((int)$changeReview->status_code) }}</strong><small>Requested by {{ $changeReview->submitter_name }} · Reviewer {{ $changeReview->reviewer_name }} · {{ \App\Support\PmsDateTime::format($changeReview->submitted_at) }}</small>@if($changeReview->review_comment)<p>{{ $changeReview->review_comment }}</p>@endif</div>@endforeach
</section>
@endif
