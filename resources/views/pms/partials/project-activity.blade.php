@forelse($activity as $event)
@php
    $details = json_decode($event->details ?: '{}', true) ?: [];
    $changes = collect($details['changed'] ?? [])->filter(fn ($field) => is_string($field));
    $action = $event->action === 'project.owner_changed' ? 'changed the Project Owner' : str_replace(['project.', '_'], ['', ' '], $event->action);
@endphp
<div class="pm-event"><strong>{{ $event->actor_name ?? ($event->actor_id ? 'Former member (ID: '.$event->actor_id.')' : 'System') }} {{ $action }}</strong><small>{{ \App\Support\PmsDateTime::format($event->created_at, true) }}</small>
@if($changes->isNotEmpty())<ul class="pm-activity-changes">@foreach($changes as $field)<li><span>{{ \App\Support\PmsActivityPresenter::label($field) }}</span>@if($field !== 'description')<small>{{ \Illuminate\Support\Str::limit($field === 'owner_id' ? ($details['labels']['old'] ?? \App\Support\PmsActivityPresenter::value($field, $details['before'][$field] ?? null)) : \App\Support\PmsActivityPresenter::value($field, $details['before'][$field] ?? null), 80) }} → {{ \Illuminate\Support\Str::limit($field === 'owner_id' ? ($details['labels']['new'] ?? \App\Support\PmsActivityPresenter::value($field, $details['after'][$field] ?? null)) : \App\Support\PmsActivityPresenter::value($field, $details['after'][$field] ?? null), 80) }}</small>@else<small>Updated</small>@endif</li>@endforeach</ul>@endif
</div>
@empty<p class="pm-muted">No project activity yet.</p>@endforelse
