@extends('admin.admin_master')
@section('admin')

@php
    $impactLabels = [1 => 'Low Impact', 2 => 'Medium Impact', 3 => 'High Impact'];
    $impactClasses = [1 => 'impact-low', 2 => 'impact-medium', 3 => 'impact-high'];
@endphp

<title>Trading News | HC Gaming Studio</title>

@include('admin.forex_news._styles')

<div class="page-content news-admin">
    <div class="container-fluid">
        <div class="news-hero mb-4">
            <div>
                <div class="eyebrow">Trading News Desk</div>
                <h1>Market News Briefings</h1>
                <p>Publish structured trading news notices for community channels with clear impact levels and risk guidance.</p>
            </div>
            <a href="{{ route('trading.news.create') }}" class="btn btn-light">
                <i class="ri-add-line"></i> Add News
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="news-stat">
                    <span>Total Briefings</span>
                    <strong>{{ $metrics['total'] ?? $totalNews }}</strong>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="news-stat">
                    <span>High Impact</span>
                    <strong>{{ $metrics['high'] ?? 0 }}</strong>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="news-stat">
                    <span>Medium / Low</span>
                    <strong>{{ ($metrics['medium'] ?? 0) + ($metrics['low'] ?? 0) }}</strong>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="news-stat">
                    <span>Discord Records</span>
                    <strong>{{ $metrics['discord'] ?? 0 }}</strong>
                </div>
            </div>
        </div>

        <div class="news-panel">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="mb-1">News Library</h5>
                    <div class="news-muted">Latest market briefings are shown first.</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table news-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Community</th>
                            <th>Impact</th>
                            <th>Briefing</th>
                            <th>Image</th>
                            <th>Discord</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($news as $item)
                            @php
                                $impactLabel = $impactLabels[$item->impact] ?? 'Not Rated';
                                $impactClass = $impactClasses[$item->impact] ?? 'status-draft';
                                $plainContent = trim(preg_replace('/\s+/', ' ', (string) $item->content));
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $item->news_date?->format('d M Y') }}</strong>
                                    <div class="news-muted">{{ $item->news_date?->format('D') }}</div>
                                </td>
                                <td>
                                    <strong>{{ $item->community?->name ?? 'No Community' }}</strong>
                                    <div class="news-muted">Trading channel</div>
                                </td>
                                <td>
                                    <span class="impact-pill {{ $impactClass }}">{{ $impactLabel }}</span>
                                </td>
                                <td class="news-brief">
                                    <strong>{{ $impactLabel }} Market News</strong>
                                    <p>{{ \Illuminate\Support\Str::limit($plainContent, 165) }}</p>
                                </td>
                                <td>
                                    <span class="news-thumb">
                                        @if($item->image)
                                            <img src="{{ asset($item->image) }}" alt="Trading news image">
                                        @else
                                            <i class="ri-image-line"></i>
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    @if($item->discordMessages->isNotEmpty())
                                        <span class="status-pill status-live">Sent</span>
                                    @else
                                        <span class="status-pill status-draft">Not Sent</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="news-actions">
                                        <a href="{{ route('trading.news.show', $item->id) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="ri-eye-line"></i> View
                                        </a>
                                        <a href="{{ route('trading.news.edit', $item->id) }}" class="btn btn-outline-secondary btn-sm">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <form action="{{ route('trading.news.sendDiscord', $item->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-info btn-sm">
                                                <i class="ri-send-plane-line"></i> Discord
                                            </button>
                                        </form>
                                        <form action="{{ route('trading.news.destroy', $item->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this trading news briefing?');">
                                                <i class="ri-delete-bin-line"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="news-empty">
                                        <h5 class="mb-1">No trading news yet</h5>
                                        <div>Create the first market briefing for your community.</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection
