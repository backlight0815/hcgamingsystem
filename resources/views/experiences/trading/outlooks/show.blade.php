@extends('experiences.trading.layout')
@section('title', $item->title . ' | HC Trading Hub')
@section('description', \Illuminate\Support\Str::limit(strip_tags($item->market_overview ?: $item->analyst_view ?: ''), 155))
@section('content')
<article>
    <header class="th-article-hero"><div class="th-container th-article-hero-inner">
        <nav class="th-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('trading.hub.home') }}">Trading Hub</a><span>/</span><a href="{{ route('trading.hub.outlooks.index') }}">Market Analyst</a></nav>
        <span class="th-kicker">Market outlook · {{ $item->market }}</span><h1>{{ $item->title }}</h1>
        <div class="th-article-meta"><time datetime="{{ $item->published_at?->toIso8601String() }}">Published {{ $item->published_at?->format('d M Y, H:i') }}</time>@if($item->community)<span>{{ $item->community->name }}</span>@endif</div>
        @if($item->outlook_image)<figure class="th-article-cover"><img src="{{ asset($item->outlook_image) }}" alt="Chart supporting {{ $item->market }} outlook"></figure>@endif
    </div></header>
    <div class="th-container th-outlook-reading th-richtext">
        @foreach(['Market overview' => $item->market_overview, 'Trend and structure' => $item->trend_structure, 'Key zones' => $item->key_zones, 'Entry zones' => $item->entry_zones_description, 'Analyst view' => $item->analyst_view, 'Strategy' => $item->strategy, 'Chart signals' => $item->chart_signals, 'Trading plan' => $item->trading_plan] as $heading => $content)
            @if($content)<section><h2>{{ $heading }}</h2><p>{!! nl2br(e($content)) !!}</p></section>@endif
        @endforeach
        @if($item->rsi_level || $item->order_block)<section><h2>Technical notes</h2>@if($item->rsi_level)<p>RSI: {{ $item->rsi_level }}</p>@endif @if($item->order_block)<p>Order Block / FVG: {{ $item->order_block }}</p>@endif</section>@endif
        <a class="th-text-link" href="{{ route('trading.hub.outlooks.index') }}">All market outlooks</a>
    </div>
    @if($related->isNotEmpty())<section class="th-section th-section-border"><div class="th-container"><div class="th-section-head"><h2>Related outlooks</h2></div><div class="th-article-grid">@foreach($related as $other)<article class="th-outlook-card"><span class="th-kicker">{{ $other->market }}</span><h3><a href="{{ route('trading.hub.outlooks.show', $other->id) }}">{{ $other->title }}</a></h3><time datetime="{{ $other->published_at?->toIso8601String() }}">{{ $other->published_at?->format('d M Y') }}</time></article>@endforeach</div></div></section>@endif
</article>
@endsection
