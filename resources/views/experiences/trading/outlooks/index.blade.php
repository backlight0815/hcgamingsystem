@extends('experiences.trading.layout')
@section('title', 'Market Outlooks | HC Trading Hub')
@section('description', 'Published market analysis and outlooks from HC Trading Hub.')
@section('content')
<section class="th-page-hero"><div class="th-container">
    <nav class="th-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('trading.hub.home') }}">Trading Hub</a><span>/</span><span>Market Analyst</span></nav>
    <span class="th-kicker">Market Analyst</span><h1>Market <em>outlooks.</em></h1>
    <p>Published technical analysis and trading perspectives from our market analysts.</p>
</div></section>
<section class="th-section"><div class="th-container">
    @if($items->isNotEmpty())
        @php($featured = $items->first())
        <article class="th-featured-article">
            <div class="th-featured-media">
                @if($featured->outlook_image)<img src="{{ asset($featured->outlook_image) }}" alt="Chart for {{ $featured->market }}">@else<span>{{ $featured->market }}</span>@endif
            </div>
            <div class="th-featured-copy"><span class="th-kicker">Latest outlook · {{ $featured->market }}</span>
                <h2><a href="{{ route('trading.hub.outlooks.show', $featured->id) }}">{{ $featured->title }}</a></h2>
                <p>{{ \Illuminate\Support\Str::limit(strip_tags($featured->market_overview ?: $featured->analyst_view ?: ''), 270) }}</p>
                <div class="th-featured-meta"><time datetime="{{ $featured->published_at?->toIso8601String() }}">{{ $featured->published_at?->format('d M Y, H:i') }}</time>@if($featured->community)<span>{{ $featured->community->name }}</span>@endif</div>
                <a class="th-button" href="{{ route('trading.hub.outlooks.show', $featured->id) }}">Read outlook</a>
            </div>
        </article>
        @php($cards = $items instanceof \Illuminate\Contracts\Pagination\Paginator ? $items->getCollection()->skip(1) : $items->skip(1))
        @if($cards->isNotEmpty())
            <div class="th-section-head"><h2>More outlooks</h2></div>
            <div class="th-article-grid">@foreach($cards as $item)
                <article class="th-outlook-card"><span class="th-kicker">{{ $item->market }}</span>
                    <h3><a href="{{ route('trading.hub.outlooks.show', $item->id) }}">{{ $item->title }}</a></h3>
                    <p>{{ \Illuminate\Support\Str::limit(strip_tags($item->market_overview ?: $item->analyst_view ?: ''), 155) }}</p>
                    <time datetime="{{ $item->published_at?->toIso8601String() }}">{{ $item->published_at?->format('d M Y') }}</time>
                    <a class="th-text-link" href="{{ route('trading.hub.outlooks.show', $item->id) }}">Read outlook</a>
                </article>
            @endforeach</div>
        @endif
        @if($items instanceof \Illuminate\Contracts\Pagination\Paginator)<div class="th-outlook-pagination">{{ $items->links() }}</div>@endif
    @else
        <div class="th-empty"><h2>No published outlooks yet.</h2><p>Check back for the latest market analysis.</p></div>
    @endif
    @unless(app(\App\Services\MarketOutlookPublicationService::class)->hasHistoryAccess(auth()->user()))
        <aside class="th-outlook-access"><h2>Explore the full outlook archive</h2><p>Historical Market Outlooks are available to approved members.</p>
            @if(auth()->check() && (int) auth()->user()->role_id === 750)<a class="th-button" href="{{ route('trader.onboarding.show') }}">View trader approval</a>@else<a class="th-button" href="{{ route('login') }}">Member login</a>@endif
        </aside>
    @endunless
</div></section>
@endsection
