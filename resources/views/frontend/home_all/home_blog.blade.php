@php
        $blogs = App\Models\Blog::latest()->limit(3)->get();
    $allMultiImage = App\Models\MultiImage::all();
@endphp
<!--

<section class="blog">
<div class="container">
<div class="row gx-0 justify-content-center">
    <div class="section__title text-center">
        <span class="sub-title">03 - 博客与教程</a>
                                </span>
    </div>
    @foreach($blogs as $item)
    <div class="col-lg-4 col-md-6 col-sm-9">
        <div class="blog__post__item">
            <div class="blog__post__thumb">

                <a href="{{ route('blog.details',$item->id) }}"><img src="{{ asset($item->blog_image) }}" alt=""></a>
                <div class="blog__post__tags">
                    <a href="{{ route('blog.details',$item->id) }}">         @if ($item['category'] && !$item['category']->trashed())
                        {{ $item['category']['blog_category'] }}
                    @else
                        {{-- Category Not Available --}}
                    @endif</a>
                </div>
            </div>
            <div class="blog__post__content">
                <span class="date">{{ Carbon\Carbon::parse($item->created_at)->diffForHumans() }}</span>
                <h3 class="title"><a href="{{ route('blog.details',$item->id) }}">{{ $item->blog_title }} </a></h3>
                <a href="{{ route('blog.details',$item->id) }}" class="read__more">View More</a>
            </div>
        </div>
    </div>

@endforeach
        </div>
        <div class="blog__button text-center">
            <a href="{{ route('home.blog') }}" class="btn">Others Blogs</a>
        </div>
    </div>
</section>
-->
