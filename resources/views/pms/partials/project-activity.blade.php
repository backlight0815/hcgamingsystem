@forelse($activity as $event)
@php
    $details = json_decode($event->details ?: '{}', true) ?: [];
    $changes = collect($details['changed'] ?? [])->filter(fn ($field) => is_string($field));
    $action = match ($event->action) {
        'project.owner_changed' => 'changed the Project Owner',
        'project.cycle_changed' => 'changed the Development Cycle',
        'project.cycle_assigned' => 'assigned the project to a Development Cycle',
        'project.cycle_removed' => 'removed the project from a Development Cycle',
        'project.release_changed' => 'changed the Release',
        default => str_replace(['project.', '_'], ['', ' '], $event->action),
    };
@endphp
<div class="pm-event"><strong>{{ $event->actor_name ?? ($event->actor_id ? 'Former member (ID: '.$event->actor_id.')' : 'System') }} {{ $action }}</strong><small>{{ \App\Support\PmsDateTime::format($event->created_at, true) }}</small>
@if($changes->isNotEmpty())<ul class="pm-activity-changes">@foreach($changes as $field)<li><span>{{ \App\Support\PmsActivityPresenter::label($field) }}</span>@if($field !== 'description')<small>{{ \Illuminate\Support\Str::limit($field === 'owner_id' ? ($details['labels']['old'] ?? \App\Support\PmsActivityPresenter::value($field, $details['before'][$field] ?? null)) : \App\Support\PmsActivityPresenter::value($field, $details['before'][$field] ?? null), 80) }} → {{ \Illuminate\Support\Str::limit($field === 'owner_id' ? ($details['labels']['new'] ?? \App\Support\PmsActivityPresenter::value($field, $details['after'][$field] ?? null)) : \App\Support\PmsActivityPresenter::value($field, $details['after'][$field] ?? null), 80) }}</small>@else<small>Updated</small>@endif</li>@endforeach</ul>@endif
@if($event->action === 'project.cycle_changed')<small>Development Cycle: {{ $details['from_cycle'] ?? 'Unassigned' }} → {{ $details['to_cycle'] ?? 'Unassigned' }}</small>@endif
@if($event->action === 'project.release_changed')<small>Release: {{ $details['from_release'] ?? 'Unassigned' }} → {{ $details['to_release'] ?? 'Unassigned' }}</small>@endif
@if(in_array($event->action, ['project.cycle_assigned', 'project.cycle_removed'], true))<small>{{ $details['cycle_key'] ?? 'Former cycle' }}</small>@endif
</div>
@empty<p class="pm-muted">No project activity yet.</p>@endforelse
