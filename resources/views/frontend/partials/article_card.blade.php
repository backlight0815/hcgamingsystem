<article class="es-article-card">
    <a class="es-article-image" href="{{ route('blog.details', $item->id) }}" aria-label="Read {{ $item->blog_title }}">
        @if($item->blog_image && is_file(public_path($item->blog_image)))<img src="{{ asset($item->blog_image) }}" alt="" loading="lazy" width="850" height="550">@else<span class="es-article-fallback" aria-hidden="true">HC / Journal</span>@endif
    </a>
    <div class="es-article-meta">@if($item->category && !$item->category->trashed() && !in_array($item->category->blog_category, ['?', '??', ''])){{ $item->category->blog_category }} · @endif<time datetime="{{ $item->created_at?->toDateString() }}">{{ $item->created_at?->format('d M Y') }}</time></div>
    <h3><a href="{{ route('blog.details', $item->id) }}">{{ $item->blog_title }}</a></h3>
    <p>{{ \Illuminate\Support\Str::limit(trim(strip_tags($item->blog_description ?? '')), 150) }}</p>
    <a class="es-text-link" href="{{ route('blog.details', $item->id) }}">Read article <span aria-hidden="true">↗</span></a>
</article>
