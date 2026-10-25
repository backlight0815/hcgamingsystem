@php
    $chartData = is_array($question?->chart_data_json ?? null) ? $question->chart_data_json : [];
    $candles = collect($chartData['candles'] ?? [])->filter(fn ($c) => isset($c['open'], $c['high'], $c['low'], $c['close']))->values();
    $annotations = collect($chartData['annotations'] ?? []);
    $symbol = $chartData['symbol'] ?? 'XAUUSD';
    $timeframe = $chartData['timeframe'] ?? $question?->chart_timeframe;
    $width = 860;
    $height = 360;
    $left = 48;
    $right = 18;
    $top = 34;
    $bottom = 36;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $prices = $candles->flatMap(fn ($c) => [(float) $c['high'], (float) $c['low']]);
    $minPrice = $prices->min() ?? 0;
    $maxPrice = $prices->max() ?? 1;
    $range = max(0.01, $maxPrice - $minPrice);
    $pad = $range * 0.12;
    $minPrice -= $pad;
    $maxPrice += $pad;
    $range = max(0.01, $maxPrice - $minPrice);
    $xStep = $candles->count() > 1 ? $plotWidth / max(1, $candles->count() - 1) : $plotWidth;
    $yFor = fn ($price) => $top + (($maxPrice - (float) $price) / $range) * $plotHeight;
    $toneColor = fn ($tone) => match ($tone) {
        'danger' => '#ef4444',
        'success' => '#22c55e',
        'primary' => '#3b82f6',
        'warning' => '#f59e0b',
        default => '#64748b',
    };
@endphp

@if($question?->chart_image_path)
    <div class="mb-3">
        <img src="{{ asset($question->chart_image_path) }}" alt="Final exam chart" class="img-fluid rounded border">
    </div>
@elseif($candles->isNotEmpty())
    <div class="final-exam-chart border rounded mb-3 bg-white">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $symbol }} {{ $timeframe }} chart" style="width:100%;height:auto;display:block;">
            <rect x="0" y="0" width="{{ $width }}" height="{{ $height }}" fill="#ffffff" />
            <text x="{{ $left }}" y="22" fill="#0f172a" font-size="15" font-weight="700">{{ $symbol }} {{ $timeframe }}</text>
            <text x="{{ $width - 250 }}" y="22" fill="#64748b" font-size="12">{{ $chartData['template_label'] ?? $question?->chart_template_key }}</text>

            @for($i = 0; $i <= 4; $i++)
                @php
                    $gridY = $top + ($plotHeight / 4) * $i;
                    $price = $maxPrice - ($range / 4) * $i;
                @endphp
                <line x1="{{ $left }}" y1="{{ $gridY }}" x2="{{ $width - $right }}" y2="{{ $gridY }}" stroke="#e5e7eb" stroke-width="1" />
                <text x="6" y="{{ $gridY + 4 }}" fill="#64748b" font-size="11">{{ number_format($price, 2) }}</text>
            @endfor

            @foreach($annotations as $annotation)
                @php
                    $type = $annotation['type'] ?? 'line';
                    $color = $toneColor($annotation['tone'] ?? null);
                @endphp
                @if($type === 'zone' && isset($annotation['from'], $annotation['to']))
                    @php
                        $y1 = $yFor($annotation['to']);
                        $y2 = $yFor($annotation['from']);
                        $zoneY = min($y1, $y2);
                        $zoneHeight = max(4, abs($y2 - $y1));
                    @endphp
                    <rect x="{{ $left }}" y="{{ $zoneY }}" width="{{ $plotWidth }}" height="{{ $zoneHeight }}" fill="{{ $color }}" opacity="0.12" />
                    <text x="{{ $left + 8 }}" y="{{ $zoneY + 14 }}" fill="{{ $color }}" font-size="11" font-weight="700">{{ $annotation['label'] ?? 'Zone' }}</text>
                @elseif($type === 'line' && isset($annotation['price']))
                    @php $lineY = $yFor($annotation['price']); @endphp
                    <line x1="{{ $left }}" y1="{{ $lineY }}" x2="{{ $width - $right }}" y2="{{ $lineY }}" stroke="{{ $color }}" stroke-width="1.5" stroke-dasharray="6 5" />
                    <text x="{{ $width - 210 }}" y="{{ $lineY - 5 }}" fill="{{ $color }}" font-size="11" font-weight="700">{{ $annotation['label'] ?? 'Level' }}</text>
                @endif
            @endforeach

            @foreach($candles as $index => $candle)
                @php
                    $x = $left + ($xStep * $index);
                    $openY = $yFor($candle['open']);
                    $closeY = $yFor($candle['close']);
                    $highY = $yFor($candle['high']);
                    $lowY = $yFor($candle['low']);
                    $bullish = (float) $candle['close'] >= (float) $candle['open'];
                    $color = $bullish ? '#16a34a' : '#dc2626';
                    $bodyY = min($openY, $closeY);
                    $bodyHeight = max(2, abs($closeY - $openY));
                    $bodyWidth = max(5, min(16, $xStep * 0.55));
                @endphp
                <line x1="{{ $x }}" y1="{{ $highY }}" x2="{{ $x }}" y2="{{ $lowY }}" stroke="{{ $color }}" stroke-width="1.3" />
                <rect x="{{ $x - ($bodyWidth / 2) }}" y="{{ $bodyY }}" width="{{ $bodyWidth }}" height="{{ $bodyHeight }}" rx="1" fill="{{ $bullish ? '#22c55e' : '#ef4444' }}" opacity="0.95" />
            @endforeach

            @foreach($annotations->where('type', 'marker') as $annotation)
                @php
                    $index = min(max((int) ($annotation['index'] ?? 0), 0), max(0, $candles->count() - 1));
                    $x = $left + ($xStep * $index);
                    $y = $yFor($annotation['price'] ?? data_get($candles[$index] ?? [], 'close', $minPrice));
                    $color = $toneColor($annotation['tone'] ?? null);
                @endphp
                <circle cx="{{ $x }}" cy="{{ $y }}" r="4" fill="{{ $color }}" />
                <text x="{{ min($x + 8, $width - 210) }}" y="{{ $y - 8 }}" fill="{{ $color }}" font-size="11" font-weight="700">{{ $annotation['label'] ?? 'Signal' }}</text>
            @endforeach

            <line x1="{{ $left }}" y1="{{ $height - $bottom }}" x2="{{ $width - $right }}" y2="{{ $height - $bottom }}" stroke="#cbd5e1" />
        </svg>
    </div>
@endif
