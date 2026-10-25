@php($canRead = auth()->check() && in_array((int) auth()->user()->role_id, [1, 2, 750, 760, 770], true))
<article class="th-article-card">
    <div class="th-article-media">@if($article->cover_image && is_file(public_path($article->cover_image)))<img src="{{ asset($article->cover_image) }}" alt="" loading="lazy" width="600" height="360">@else<span aria-hidden="true">HC / RESEARCH</span>@endif</div>
    <div class="th-article-copy"><span class="th-kicker">{{ $article->category_label }} <span aria-hidden="true">/</span> {{ $article->published_at?->format('d M Y') }}</span><h3>{{ $article->title }}</h3><p>{{ \Illuminate\Support\Str::limit($article->excerpt ?: strip_tags($article->content), 145) }}</p>
    @if($canRead)<a class="th-text-link" href="{{ route('trading.blogs.show', $article->slug) }}">Read member article <span aria-hidden="true">↗</span></a>@else<span class="th-access-note">Full article for eligible members</span>@endif</div>
</article>
