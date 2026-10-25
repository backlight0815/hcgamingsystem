@php($loginEntries = \App\Support\ModuleAccess::loginEntries($currentModule ?? null))
@if($loginEntries)
<nav class="module-signins" aria-labelledby="module-signins-title">
    <h2 id="module-signins-title">Access Another Module</h2>
    <ul>
        @foreach($loginEntries as $key => $entry)
        <li><a href="{{ route($entry['login']) }}" data-module-login="{{ $key }}">
            <span class="module-signins-icon" aria-hidden="true">{{ ['tradinghub' => 'TH', 'dealership' => 'DE', 'pms' => 'PM'][$key] ?? 'HC' }}</span>
            <span class="module-signins-label"><strong>{{ $entry['name'] }}</strong><small>{{ $key === 'pms' ? 'Staff Sign In' : 'Member Sign In' }}</small></span>
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </a></li>
        @endforeach
    </ul>
</nav>
@endif
