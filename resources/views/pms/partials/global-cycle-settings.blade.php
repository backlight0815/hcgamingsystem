@php($defaults = json_decode($settings->default_stage_durations ?? '{}', true) ?: ['planning'=>3,'development'=>14,'internal_testing'=>7,'qat'=>14,'preparation'=>6,'closure'=>1])
<section class="pm-panel" id="cycle-settings"><div class="pm-panel-head"><div><p class="pm-eyebrow">AUTOMATION</p><h2>Cycle settings</h2></div></div>
<form method="post" action="{{ route('pms.global-cycles.settings') }}" class="pm-form-grid">@csrf @method('PUT')
    <label>Automatic generation<select name="auto_enabled"><option value="1" @selected($settings->auto_enabled)>Enabled</option><option value="0" @selected(!$settings->auto_enabled)>Paused</option></select></label>
    <label>Interval in calendar days<input type="number" name="interval_days" min="6" max="365" required value="{{ $settings->interval_days }}"></label>
    <label>Anchor date<input type="date" name="anchor_date" required value="{{ $settings->anchor_date }}"></label>
    <label>Organization timezone<input name="timezone" required value="{{ $settings->timezone }}"></label>
    <div class="wide"><strong>Default phase weights</strong><p class="pm-muted">New cycles scale these weights to fit their selected dates. Existing cycle schedules stay unchanged.</p></div>
    @foreach(\App\Services\PmsGlobalCycleService::STAGES as $key=>$label)<label>{{ $label }}<input type="number" name="default_stage_durations[{{ $key }}]" min="1" max="365" required value="{{ $defaults[$key] ?? 1 }}"></label>@endforeach
    <button class="pm-button secondary">Save settings</button>
</form><p class="pm-muted">The scheduler checks hourly and catches up after missed runs. New cycles start without project or ticket assignments.</p></section>
