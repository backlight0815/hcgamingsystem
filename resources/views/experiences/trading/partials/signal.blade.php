<article class="th-signal-card">
    <div class="th-signal-top"><span class="th-kicker">{{ $signal->market ?: 'Trading signal' }}</span><span class="th-status">{{ ucfirst(strtolower($signal->workflow_status)) }}</span></div>
    <h3>{{ $signal->trading_pair ?: ($signal->signal_title ?: 'Instrument unavailable') }}</h3>
    <div class="th-signal-direction">{{ strtoupper($signal->immediate_action ?: 'Direction unavailable') }}</div>
    <dl class="th-signal-values"><div><dt>Entry</dt><dd>{{ ($signal->entry_price_value ?? $signal->entry_price) ?: '—' }}</dd></div><div><dt>Stop loss</dt><dd>{{ ($signal->stop_loss_value ?? $signal->stop_loss) ?: '—' }}</dd></div><div><dt>Target 1</dt><dd>{{ ($signal->target_1_value ?? $signal->target_1) ?: '—' }}</dd></div></dl>
    <div class="th-signal-bottom"><time datetime="{{ $signal->published_at?->toIso8601String() }}">{{ $signal->published_at?->format('d M Y, H:i') }}</time><a href="{{ route('member.signals.view', $signal->id) }}">View details ↗</a></div>
</article>
