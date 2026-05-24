@if(isset($breadcrumbData) && count($breadcrumbData))
    <nav class="commerce-breadcrumb" aria-label="Breadcrumb">
        @foreach ($breadcrumbData as $breadcrumb)
            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
            @if (!$loop->last)
                <span>/</span>
            @endif
        @endforeach
    </nav>
@endif
