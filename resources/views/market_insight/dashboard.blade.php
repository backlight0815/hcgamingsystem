@extends('admin.admin_master')

@section('admin')
<style>
    .mi-page { color:#eaf3f2; max-width:1440px; margin:auto; }
    .mi-page .mi-card { background:#15232b; border:1px solid #35505a; border-radius:16px; padding:20px; height:100%; }
    .mi-page .mi-muted { color:#b7c8ca; }
    .mi-page .mi-tag { display:inline-block; background:#184f48; color:#d4fff4; font-weight:700; border-radius:20px; padding:5px 12px; letter-spacing:.04em; }
    .mi-page .mi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr)); gap:16px; }
    .mi-page .mi-metrics { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; }
    .mi-page .mi-metric { padding:14px; border:1px solid #40616a; border-radius:12px; }
    .mi-page .mi-value { font-size:1.35rem; font-weight:700; overflow-wrap:anywhere; }
    .mi-page .mi-label { font-size:.8rem; color:#bfd1d2; text-transform:uppercase; letter-spacing:.05em; }
    .mi-page h1,.mi-page h2,.mi-page h3 { color:#f2fcfa; }
    .mi-page h2 { font-size:1.18rem; }
    .mi-page .form-control { background:#fff; color:#17242a; }
    .mi-page .table { color:#eaf3f2; }
    .mi-page .table td,.mi-page .table th { border-color:#36515a; }
    .mi-page a { color:#93e9d7; }
    .mi-page .mi-list { margin:0; padding-left:20px; }
    .mi-page .mi-list li { margin:5px 0; }
    .mi-page .mi-empty { padding:28px; border:1px dashed #71918e; border-radius:14px; }
    @media(max-width:600px) { .mi-page .mi-card { padding:16px; } .mi-page h1 { font-size:1.6rem; } }
</style>
<div class="page-content"><div class="container-fluid py-4 mi-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3" style="gap:12px">
        <div><h1 class="mb-1">AI Market Insight</h1><div class="mi-muted">{{ $source === 'REAL' ? 'XAUUSD · Gold Spot / US Dollar' : 'Rule-Based Indicator Analysis · Demo' }}</div></div>
        @if($isAdmin)<a href="{{ route('admin.market_insight.management') }}" class="btn btn-outline-info">Management</a>@endif
    </div>
    @if($source === 'MOCK')
        <div class="alert alert-info" role="status"><strong>DEMO / SIMULATED DATA</strong> — Prices and signals are generated for education and testing.</div>
    @else
        <div class="alert {{ ($providerStatus['status'] ?? '') === 'CONNECTED' ? 'alert-success' : 'alert-warning' }}" role="status"><strong>REAL DATA · Twelve Data</strong> — {{ $providerStatus['status'] ?? 'NOT_SYNCHRONIZED' }}. {{ $providerStatus['message'] ?? '' }} Rule-based scores are not win probabilities.</div>
        <section class="mi-card mb-3" aria-label="XAUUSD market price">
            <h2>XAUUSD · Gold Spot / US Dollar</h2>
            <div class="mi-metrics">
                <div class="mi-metric"><div class="mi-label">Latest available price</div><div class="mi-value">{{ $quote ? number_format((float)$quote['price'], 5) : 'Unavailable' }} {{ $quote ? 'USD / troy oz' : '' }}</div></div>
                <div class="mi-metric"><div class="mi-label">Quote status</div><div class="mi-value">{{ $quote['status'] ?? 'UNAVAILABLE' }}</div></div>
                <div class="mi-metric"><div class="mi-label">Provider quote time</div><div class="mi-value">{{ $quote['quote_at'] ?? 'Not provided' }}</div></div>
                <div class="mi-metric"><div class="mi-label">Last verified refresh</div><div class="mi-value">{{ $quote['retrieved_at'] ?? 'Never' }}</div></div>
            </div>
            <p class="mi-muted mt-2 mb-0">Provider: Twelve Data · {{ ($quote['is_market_open'] ?? null) === false ? 'Market closed' : 'Market state unverified or open' }} · Current signal uses completed candles only.</p>
        </section>
    @endif
    @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if(!empty($historical))<div class="alert alert-secondary">Viewing a saved historical snapshot. <a href="{{ route('market_insight.dashboard', ['symbol' => $symbol, 'timeframe' => $timeframe]) }}">Return to latest</a></div>@endif
    <section class="mi-card mb-3" aria-labelledby="mi-select-heading">
        <h2 id="mi-select-heading">Market selection</h2>
        <form method="GET" action="{{ route('market_insight.dashboard') }}" class="row align-items-end" style="row-gap:12px">
            <div class="col-md-4"><label for="mi-symbol">Symbol</label><select id="mi-symbol" name="symbol" class="form-control">@foreach($instruments as $instrument)<option value="{{ $instrument->symbol }}" @selected($symbol === $instrument->symbol)>{{ $instrument->symbol }} — {{ $instrument->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label for="mi-timeframe">Timeframe</label><select id="mi-timeframe" name="timeframe" class="form-control">@foreach($timeframes as $code => $minutes)<option value="{{ $code }}" @selected($timeframe === $code)>{{ $code }} · {{ $minutes }} minutes</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-info btn-block" type="submit">View insight</button></div>
        </form>
        <form method="POST" action="{{ route('market_insight.refresh') }}" class="mt-3">@csrf<input type="hidden" name="symbol" value="{{ $symbol }}"><input type="hidden" name="timeframe" value="{{ $timeframe }}"><button class="btn btn-outline-info" type="submit">Refresh saved analysis</button></form>
        <div class="mi-muted mt-2">Source: {{ $source }} · {{ $symbol }} · {{ $timeframe }} · Refresh recalculates saved completed candles; external data is synchronized by the backend.</div>
    </section>

    @if(!$analysis || ($analysis['status'] ?? '') !== 'available')
        <div class="mi-empty mb-3" role="status"><h2>Analysis unavailable</h2><p class="mb-0">{{ $analysis['reason'] ?? ($source === 'REAL' ? 'No verified real analysis has been synchronized for this timeframe.' : 'No saved simulated analysis exists for this symbol and timeframe. An administrator can generate a demo dataset.') }}</p></div>
    @else
        @php
            $indicators = $analysis['indicators'];
        @endphp
        <section class="mi-card mb-3" aria-labelledby="mi-signal-heading">
            <h2 id="mi-signal-heading">Combined market signal</h2>
            <div class="d-flex align-items-center flex-wrap mb-3" style="gap:12px"><span class="mi-tag">{{ $analysis['signal'] }}</span><span class="mi-muted">{{ $analysis['method'] }}</span></div>
            <div class="mi-metrics">
                @foreach(['Directional score' => ($analysis['directional_score'].' / 100'), 'Signal strength' => ($analysis['signal_strength'].' / 100'), 'Weighted agreement' => ($analysis['agreement_score'].'%'), 'Risk' => $analysis['risk'], 'Data quality' => $analysis['data_quality']] as $label => $value)
                    <div class="mi-metric"><div class="mi-label">{{ $label }}</div><div class="mi-value">{{ $value }}</div></div>
                @endforeach
            </div>
            <p class="mt-3 mb-0 mi-muted">Latest completed candle: {{ $analysis['market_data_at'] }} UTC · Analysis: {{ $analysis['analyzed_at'] }} · Dataset v{{ $analysis['dataset_version'] }} / revision {{ $analysis['dataset_revision'] ?? 1 }} · Configuration v{{ $analysis['configuration_version'] }} · {{ $analysis['data_status'] ?? $source }}</p>
        </section>
        @if($source === 'REAL' && count($candles) > 1)
            @php
                $chartLow = min(array_map(fn($c) => (float)$c['low'], $candles));
                $chartHigh = max(array_map(fn($c) => (float)$c['high'], $candles));
                $chartSpan = max(0.00001, $chartHigh - $chartLow);
                $chartStep = 960 / count($candles);
                $lastCandle = $candles[count($candles) - 1];
            @endphp
            <section class="mi-card mb-3" aria-label="Verified XAUUSD candlestick chart"><h2>Completed XAUUSD candles · {{ $timeframe }}</h2>
                <svg viewBox="0 0 1000 300" role="img" aria-label="OHLC candles from {{ $candles[0]['open_at'] }} to {{ $lastCandle['close_at'] }}" style="width:100%;height:auto;background:#101d23;border-radius:8px">
                    @foreach($candles as $i => $candle)
                        @php
                            $x = 20 + ($i + .5) * $chartStep;
                            $yHigh = 270 - ((float)$candle['high'] - $chartLow) / $chartSpan * 250;
                            $yLow = 270 - ((float)$candle['low'] - $chartLow) / $chartSpan * 250;
                            $yOpen = 270 - ((float)$candle['open'] - $chartLow) / $chartSpan * 250;
                            $yClose = 270 - ((float)$candle['close'] - $chartLow) / $chartSpan * 250;
                            $up = (float)$candle['close'] >= (float)$candle['open'];
                        @endphp
                        <g><title>{{ $candle['open_at'] }} UTC · O {{ $candle['open'] }} H {{ $candle['high'] }} L {{ $candle['low'] }} C {{ $candle['close'] }}</title>
                            <line x1="{{ $x }}" x2="{{ $x }}" y1="{{ $yHigh }}" y2="{{ $yLow }}" stroke="{{ $up ? '#76dbc2' : '#f48d91' }}" stroke-width="1.2"/>
                            <rect x="{{ $x - $chartStep * .3 }}" y="{{ min($yOpen,$yClose) }}" width="{{ max(2,$chartStep * .6) }}" height="{{ max(1,abs($yOpen-$yClose)) }}" fill="{{ $up ? '#76dbc2' : '#f48d91' }}"/>
                        </g>
                    @endforeach
                    <text x="22" y="16" fill="#cbd9dc" font-size="13">{{ number_format($chartHigh, 2) }}</text>
                    <text x="22" y="291" fill="#cbd9dc" font-size="13">{{ number_format($chartLow, 2) }} · {{ $candles[0]['open_at'] }} to {{ $lastCandle['close_at'] }} UTC</text>
                </svg>
            </section>
        @endif
        <section class="mb-3" aria-labelledby="mi-indicators-heading"><h2 id="mi-indicators-heading">Six indicator assessments</h2><div class="mi-grid">
            @foreach(['bollinger' => 'Bollinger Bands', 'ema' => 'Exponential Moving Averages', 'macd' => 'MACD', 'rsi' => 'Relative Strength Index', 'atr' => 'Average True Range', 'adr' => 'Average Daily Range'] as $key => $name)
                @php($item = $indicators[$key])
                <article class="mi-card"><h3 class="h5">{{ $name }}</h3><div class="mi-tag mb-2">{{ $item['classification'] }}</div><p>{{ $item['explanation'] }}</p><div class="mi-muted mb-2">{{ in_array($key, ['atr','adr']) ? 'Risk context · no directional vote' : 'Directional contribution: '.($analysis['contributions'][$key]['points'] ?? 0).' points' }}</div><dl class="row mb-0">
                    @foreach($item['values'] as $label => $value)
                        <dt class="col-6 text-capitalize mi-muted">{{ str_replace('_', ' ', $label) }}</dt><dd class="col-6 text-right" style="overflow-wrap:anywhere">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : (is_numeric($value) ? number_format((float)$value, 4) : ($value ?? 'N/A')) }}</dd>
                    @endforeach
                </dl><div class="mi-muted small">Configuration: {{ collect($item['configuration'])->except(['enabled','weight'])->map(fn($value,$label) => str_replace('_',' ',$label).': '.$value)->implode(' · ') }}</div></article>
            @endforeach
        </div></section>
        <section class="mi-grid mb-3" aria-label="Multi-factor assessment">
            @foreach(['bullish' => 'Bullish factors', 'bearish' => 'Bearish factors', 'neutral' => 'Neutral factors'] as $key => $heading)
                <div class="mi-card"><h2>{{ $heading }} ({{ $analysis['factor_counts'][$key] }})</h2><ul class="mi-list">@forelse($analysis['factors'][$key] as $factor)<li>{{ $factor }}</li>@empty<li>None</li>@endforelse</ul></div>
            @endforeach
            <div class="mi-card"><h2>Risk warnings</h2><ul class="mi-list">@forelse($analysis['warnings'] as $warning)<li>{{ $warning }}</li>@empty<li>No added risk warning</li>@endforelse</ul><div class="mi-muted mt-2">{{ $analysis['volatility'] }} · {{ $analysis['daily_range_status'] }}</div></div>
        </section>
        <section class="mi-card mb-3"><h2>Indicator-generated explanation</h2><p class="mb-0">{{ $analysis['explanation'] }}</p></section>
    @endif
    <section class="mi-card" aria-labelledby="mi-history-heading"><h2 id="mi-history-heading">Saved insight history</h2>
        @if(!$history)<p class="mi-muted mb-0">No previous snapshots for this selection.</p>@else
        <div class="table-responsive"><table class="table"><thead><tr><th>Symbol</th><th>Timeframe</th><th>Analysis time</th><th>Signal</th><th>Score</th><th>Risk</th><th>Inspect</th></tr></thead><tbody>
            @foreach($history as $entry)<tr><td>{{ $entry['symbol'] }}</td><td>{{ $entry['timeframe'] }}</td><td>{{ $entry['analyzed_at'] }}</td><td>{{ $entry['signal'] }}</td><td>{{ $entry['directional_score'] }}</td><td>{{ $entry['risk'] }}</td><td><a href="{{ route('market_insight.show', $entry['id']) }}">Open snapshot</a></td></tr>@endforeach
        </tbody></table></div>@endif
    </section>
</div></div>
@endsection
