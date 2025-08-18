@php
    $aboutpage = App\Models\About::find(1);
    $allMultiImage = App\Models\MultiImage::all();
@endphp

<!-- about-area -->
<section id="aboutSection" class="about">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                @if(!$allMultiImage->isEmpty())
                    <ul class="about__icons__wrap">
                        @foreach($allMultiImage as $item)
                            <li>
                                <img class="light" src="{{ asset($item->multi_image) }}" alt="XD">
                            </li>
                        @endforeach
                    </ul>
                @else
                    <!-- Show a default image when the MultiImage table is empty -->
                    <img class="light" src="{{ asset('upload/default.jpg')}}" alt="Default Image">
                @endif
            </div>
            <div class="col-lg-6">
                <div class="about__content">
                    <div class="section__title">
                        <span class="sub-title">01 - About Me</span>
                        @if($aboutpage)
                            <h2 class="title">{{ $aboutpage->title }}</h2>
                        @else
                            <!-- Show a default title when the About table is empty -->
                            <h2 class="title">Pending to setup from admin</h2>
                        @endif
                    </div>
                    @if($aboutpage)
                        <div class="about__exp">
                            <div class="about__exp__icon">
                                <img src="{{ asset('frontend/assets/img/icons/about_icon.png') }}" alt="">
                            </div>
                            <div class="about__exp__content">
                                <p>{{ $aboutpage->short_title }}</p>
                            </div>
                        </div>
                        <p class="desc">{{ $aboutpage->short_description }}</p>
                        <a href="{{ route('home.about') }}" class="btn">Explore more about me</a>
                    @else
                        <!-- Show a default description and link when the About table is empty -->
                        <p class="desc">Pending to setup from admin</p>
                        <a href="{{ route('home.about') }}" class="btn">Explore more about me</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
<!-- about-area-end -->
