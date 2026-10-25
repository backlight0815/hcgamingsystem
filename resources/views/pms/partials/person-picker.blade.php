<div class="pm-person-picker">
    <label for="pm-person-search">Person-in-Charge (PIC)</label>
    <input id="pm-person-search" type="search" data-picker-search placeholder="Select one or more team members" autocomplete="off" aria-controls="pm-person-options">
    <div id="pm-person-options" class="pm-person-options" role="group" aria-label="Eligible project team members">
        @foreach($pickerMembers as $member)
            <label class="pm-person-option" data-picker-option data-search="{{ mb_strtolower($member->name.' '.$member->job_position.' '.$member->department) }}">
                <input type="checkbox" name="{{ $pickerName }}[]" value="{{ $member->id }}" @checked(in_array((int) $member->id, $pickerSelected, true))>
                <span class="pm-avatar" aria-hidden="true">{{ strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                <span><strong>{{ $member->name }}</strong><small>{{ $member->job_position ?: 'Team member' }}@if($member->department) · {{ $member->department }}@endif @if($member->inactive ?? false) · Existing inactive assignment @endif</small></span>
            </label>
        @endforeach
    </div>
    <p class="pm-muted" data-picker-empty hidden>No team members match your search.</p>
    <div class="pm-person-picker-footer"><strong data-picker-count aria-live="polite"></strong><button type="button" class="pm-link" data-picker-clear>Clear selection</button></div>
</div>
