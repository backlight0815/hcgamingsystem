@extends('admin.admin_master')
@section('admin')

@php
    $impactLabels = [1 => 'Low Impact', 2 => 'Medium Impact', 3 => 'High Impact'];
    $impactClasses = [1 => 'impact-low', 2 => 'impact-medium', 3 => 'impact-high'];
    $impactLabel = $impactLabels[$news->impact] ?? 'Not Rated';
    $impactClass = $impactClasses[$news->impact] ?? 'status-draft';
@endphp

<title>Trading News Details | HC Gaming Studio</title>

@include('admin.forex_news._styles')

<div class="page-content news-admin">
    <div class="container-fluid">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="news-detail-hero mb-4">
            <div class="row g-0">
                <div class="col-lg-5">
                    <div class="news-detail-media h-100">
                        @if($news->image)
                            <img src="{{ asset($news->image) }}" alt="Trading news image">
                        @else
                            <div class="d-flex h-100 align-items-center justify-content-center text-muted">
                                <div class="text-center">
                                    <i class="ri-newspaper-line d-block mb-2" style="font-size: 42px;"></i>
                                    No image uploaded
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="news-detail-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="impact-pill {{ $impactClass }}">{{ $impactLabel }}</span>
                            @if($news->discordMessages->isNotEmpty())
                                <span class="status-pill status-live">Discord Sent</span>
                            @else
                                <span class="status-pill status-draft">Not Sent</span>
                            @endif
                        </div>
                        <h1>{{ $impactLabel }} Market News</h1>
                        <p class="mb-4">Scheduled for {{ $news->news_date?->format('D, d M Y') }} and assigned to {{ $news->community?->name ?? 'No community' }}.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('trading.news.edit', $news->id) }}" class="btn btn-light">
                                <i class="ri-edit-line"></i> Edit
                            </a>
                            <form action="{{ route('trading.news.sendDiscord', $news->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-light">
                                    <i class="ri-send-plane-line"></i> Send Discord
                                </button>
                            </form>
                            <a href="{{ route('trading.news.index') }}" class="btn btn-outline-light">
                                <i class="ri-arrow-left-line"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="news-panel h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="news-muted text-uppercase fw-bold">Published Copy</div>
                            <h5 class="mb-0">Briefing Content</h5>
                        </div>
                    </div>
                    <div class="news-preview-copy">{{ $news->content }}</div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="news-panel h-100">
                    <div class="news-muted text-uppercase fw-bold mb-2">Record Details</div>
                    <div class="mb-3">
                        <div class="news-muted">Community</div>
                        <strong>{{ $news->community?->name ?? 'No community' }}</strong>
                    </div>
                    <div class="mb-3">
                        <div class="news-muted">News Date</div>
                        <strong>{{ $news->news_date?->format('d M Y') }}</strong>
                    </div>
                    <div class="mb-3">
                        <div class="news-muted">Created</div>
                        <strong>{{ $news->created_at?->format('d M Y H:i') }}</strong>
                    </div>
                    <div class="mb-3">
                        <div class="news-muted">Last Updated</div>
                        <strong>{{ $news->updated_at?->format('d M Y H:i') }}</strong>
                    </div>
                    <hr>
                    <div class="news-muted text-uppercase fw-bold mb-2">Discord Delivery</div>
                    @forelse($news->discordMessages as $message)
                        <div class="border rounded p-2 mb-2">
                            <strong>{{ $message->community?->name ?? 'Community #' . $message->community_id }}</strong>
                            <div class="news-muted">Channel: {{ $message->channel_id ?: 'Pending' }}</div>
                            <div class="news-muted">Message: {{ $message->message_id ?: 'Pending' }}</div>
                        </div>
                    @empty
                        <div class="news-empty py-3">No Discord message record yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
