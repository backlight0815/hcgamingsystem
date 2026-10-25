<section class="pm-panel"><div class="pm-panel-head"><h2>Configure stages</h2></div>
    <form method="post" action="{{ route('pms.global-cycles.stages',$cycle) }}" class="pm-stage-form">@csrf @method('PUT')
        <p class="pm-muted">Set order and duration for every stage. Durations must total {{ $cycle->start_date->diffInDays($cycle->end_date)+1 }} calendar days. The stage end date is its deadline.</p>
        @foreach($cycle->stages as $stage)
            <div><input type="hidden" name="stages[{{ $loop->index }}][stage_key]" value="{{ $stage->stage_key }}"><strong>{{ $stage->name }}</strong>
                <label>Order<input type="number" min="1" max="6" name="stages[{{ $loop->index }}][sort_order]" value="{{ $stage->sort_order }}" required></label>
                <label>Days<input type="number" min="1" name="stages[{{ $loop->index }}][duration_days]" value="{{ $stage->start_date->diffInDays($stage->end_date)+1 }}" required></label>
                <label>Progress<input type="number" min="0" max="100" name="stages[{{ $loop->index }}][completion_percent]" value="{{ $stage->completion_percent }}" required></label>
                <label>Owner<select name="stages[{{ $loop->index }}][owner_id]"><option value="">Unassigned</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected($stage->owner_id==$owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
                <label>Notes<input name="stages[{{ $loop->index }}][notes]" value="{{ $stage->notes }}"></label>
            </div>
        @endforeach
        <label>Reason for change<textarea name="reason" required rows="2"></textarea></label><button class="pm-button secondary">Save stage plan</button>
    </form>
</section>
