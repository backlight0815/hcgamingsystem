@if(session('pms_feedback'))
@php($feedback = session('pms_feedback'))
<section class="pm-action-feedback" role="alert" tabindex="-1" id="pm-action-feedback">
    <div><p class="pm-eyebrow">ACTION NEEDS ATTENTION</p><h2>{{ $feedback['heading'] }}</h2>
    <p>{{ $feedback['message'] }}</p>
    @if($feedback['details'])<dl>@foreach($feedback['details'] as $label => $value)<dt>{{ ucwords(str_replace('_',' ',$label)) }}</dt><dd>{{ $value }}</dd>@endforeach</dl>@endif
    <p class="pm-muted">{{ $feedback['next'] }}</p>@if(!empty($feedback['action_url']))<a class="pm-button secondary" href="{{ $feedback['action_url'] }}">{{ $feedback['action_label'] ?? 'Continue' }}</a>@endif</div>
    <button type="button" class="pm-link" aria-label="Dismiss message" onclick="this.closest('.pm-action-feedback').remove()">Dismiss</button>
</section>
@endif
