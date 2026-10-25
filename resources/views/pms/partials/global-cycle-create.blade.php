<section class="pm-panel" id="create-cycle">
    <div class="pm-panel-head"><div><p class="pm-eyebrow">CYCLE MANAGEMENT</p><h2>Create organization cycle</h2></div></div>
    <form method="post" action="{{ route('pms.global-cycles.store') }}" class="pm-form-grid">@csrf
        <label>Cycle name<input name="name" required maxlength="255" value="{{ old('name') }}" placeholder="November delivery"></label>
        <label>Cycle key <small>Optional; generated when blank</small><input name="cycle_key" maxlength="32" pattern="[A-Z][A-Z0-9_-]{2,31}" value="{{ old('cycle_key') }}" placeholder="CYCLE_001"></label>
        <label>Owner<select name="owner_id"><option value="">Choose later</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected(old('owner_id')==$owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
        <label>Start date<input type="date" name="start_date" required value="{{ old('start_date',$today->toDateString()) }}"></label>
        <label>End date<input type="date" name="end_date" required value="{{ old('end_date',$today->copy()->addDays(($settings->interval_days ?? 45)-1)->toDateString()) }}"></label>
        <label>Production target <small>Defaults to the cycle end</small><input type="date" name="production_target_date" value="{{ old('production_target_date') }}"></label>
        <label class="wide">Description and goal<textarea name="goal" rows="3">{{ old('goal') }}</textarea></label>
        <label class="wide">Planning notes<textarea name="notes" rows="3">{{ old('notes') }}</textarea></label>
        <details class="wide" data-custom-stages><summary>Customize phase durations</summary><p class="pm-muted">All six durations must add up to the inclusive number of days between the start and end dates.</p><label><input type="checkbox" data-enable-stages> Set custom durations</label><div class="pm-form-grid">@foreach(\App\Services\PmsGlobalCycleService::STAGES as $key=>$label)<label>{{ $label }} days<input type="number" name="stage_durations[{{ $key }}]" min="1" max="365" value="{{ old('stage_durations.'.$key) }}" disabled data-stage-duration></label>@endforeach</div></details>
        <button class="pm-button">Create cycle</button>
    </form>
    <p class="pm-muted">Projects and tickets are assigned explicitly after creation. Development and Release Management use this same cycle ID.</p>
</section>
<script>document.querySelectorAll('[data-custom-stages]').forEach(section => { const toggle=section.querySelector('[data-enable-stages]'); const fields=section.querySelectorAll('[data-stage-duration]'); toggle.addEventListener('change',()=>fields.forEach(field=>{field.disabled=!toggle.checked;field.required=toggle.checked;})); });</script>
