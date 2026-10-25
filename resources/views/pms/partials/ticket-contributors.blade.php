<section class="pm-panel" id="contributors">
    @php($pics = $contributors->where('assignment_type', 'pic'))
    @php($pickerMembers = $members->concat($pics->filter(fn ($pic) => !$members->contains('id', $pic->user_id))->map(fn ($pic) => (object) ['id' => $pic->user_id, 'name' => $pic->name, 'job_position' => $pic->job_position, 'department' => $pic->department, 'inactive' => true]))->sortBy('name'))
    <div class="pm-panel-head"><div><p class="pm-eyebrow">RESPONSIBILITY</p><h2>Person-in-Charge (PIC)</h2></div><span class="pm-count">{{ $pics->count() }}</span></div>
    <p class="pm-muted">PICs share responsibility for this ticket. Developer and QA owner fields remain separate.</p>
    <div class="pm-pic-list" aria-label="Assigned PICs">
        @forelse($pics as $pic)
            <div class="pm-pic-person"><span class="pm-avatar" aria-hidden="true">{{ strtoupper(mb_substr($pic->name, 0, 1)) }}</span><div><strong>{{ $pic->name }}</strong><small>{{ $pic->job_position ?: 'Team member' }}@if($pic->department) · {{ $pic->department }}@endif</small></div>
                @if($canManageContributors)<form method="post" action="{{ route('pms.tickets.pics.destroy', [$ticket, $pic->user_id]) }}">@csrf @method('DELETE')<button class="pm-link danger" aria-label="Remove {{ $pic->name }} from PICs">Remove</button></form>@endif
            </div>
        @empty <p class="pm-muted">No PICs assigned yet.</p> @endforelse
    </div>
    @if($canManageContributors)
        <details class="pm-section-form" data-pic-details><summary>Manage PIC assignment</summary>
            <form method="post" action="{{ route('pms.tickets.pics.update', $ticket) }}" data-person-picker>
                @csrf @method('PUT')<input type="hidden" name="lock_version" value="{{ $ticket->lock_version }}">
                @include('pms.partials.person-picker', ['pickerMembers' => $pickerMembers, 'pickerSelected' => $pics->pluck('user_id')->map(fn ($id) => (int) $id)->all(), 'pickerName' => 'assignee_ids'])
                <div class="pm-form-actions"><button class="pm-button">Save assignment</button><button type="button" class="pm-button secondary" data-picker-cancel>Cancel</button></div>
            </form>
        </details>
    @endif
    @if($contributors->where('assignment_type', '!=', 'pic')->isNotEmpty())
        <details class="pm-section-form"><summary>Developer and QA contributors</summary>
            @foreach(['developer' => 'Developers', 'qa' => 'QA engineers'] as $assignmentType => $label)
                <h3>{{ $label }}</h3><div class="pm-member-list">
                    @forelse($contributors->where('assignment_type', $assignmentType) as $contributor)
                        <div><div><strong>{{ $contributor->name }}</strong><small>{{ $contributor->job_position }} · {{ $contributor->department ?: 'No department' }}</small></div>
                        @if($canManageContributors)<form method="post" action="{{ route('pms.tickets.contributors.destroy', [$ticket, $contributor->user_id]) }}">@csrf @method('DELETE')<input type="hidden" name="assignment_type" value="{{ $assignmentType }}"><button class="pm-link danger">Remove</button></form>@endif</div>
                    @empty <p class="pm-muted">No {{ strtolower($label) }} assigned.</p> @endforelse
                </div>
            @endforeach
        </details>
    @endif
</section>
