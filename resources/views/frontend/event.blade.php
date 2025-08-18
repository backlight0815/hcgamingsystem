@extends('frontend.main_master')
@section('main')

@section('title')
Event Listing | HC_Gaming Studio Websites
@endsection

<!-- main-area -->
<main>

    <!-- breadcrumb-area -->
    <section class="breadcrumb__wrap">
        <div class="container custom-container">
            <div class="row justify-content-center">
                <div class="col-xl-6 col-lg-8 col-md-10">
                    <div class="breadcrumb__wrap__content">
                        <h2 class="title">Event Listing</h2>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Upcoming Events</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <div class="breadcrumb__wrap__icon">
            <ul>
                <li><img src="{{ asset('frontend/assets/img/icons/breadcrumb_icon01.png') }}" alt=""></li>
                <li><img src="{{ asset('frontend/assets/img/icons/breadcrumb_icon02.png') }}" alt=""></li>
                <li><img src="{{ asset('frontend/assets/img/icons/breadcrumb_icon03.png') }}" alt=""></li>
                <li><img src="{{ asset('frontend/assets/img/icons/breadcrumb_icon04.png') }}" alt=""></li>
                <li><img src="{{ asset('frontend/assets/img/icons/breadcrumb_icon05.png') }}" alt=""></li>
                <li><img src="{{ asset('frontend/assets/img/icons/breadcrumb_icon06.png') }}" alt=""></li>
            </ul>
        </div>
    </section>
    <!-- breadcrumb-area-end -->

    <!-- portfolio-area -->
    <section class="portfolio__inner">
        <div class="container">

            <div class="portfolio__inner__active">

                @foreach($events as $key => $event)
                <div class="portfolio__inner__item grid-item">
                    <div class="row gx-0 align-items-center">

                        <!-- Layout with image and content alternating -->
                        @if($key % 2 == 0)
                            <!-- Image on the left, content on the right (default order) -->
                            <div class="col-lg-6 col-md-8">
                                <div class="portfolio__inner__thumb">
                                    <a href="">
                                        <img src="{{ asset($event->event_image) }}" style="height:450px" width="500px" alt="Event Image">
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-10">
                                <div class="portfolio__inner__content">
                                    <h2 class="title"><a href="">{{ $event->title }}</a></h2>

                                    <!-- All fields display label first, then the value -->
                                    <p><strong>Event Details:</strong> {{ $event->description }}</p>
                                    <p><strong>Organizer:</strong> {{ $event->organizer_name }}</p>
                                    <p><strong>Start Date:</strong> {{ \Carbon\Carbon::parse($event->start_time)->format('F j, Y') }}</p>
                                    <p><strong>End Date:</strong> {{ \Carbon\Carbon::parse($event->end_time)->format('F j, Y') }}</p>
                                    <p><strong>Mode:</strong> {{ ucfirst($event->type) }} Event</p>
                                    <a href="" class="link">View Details</a>
                                </div>
                            </div>
                        @else
                            <!-- Content on the left, image on the right (reverse order) -->
                            <div class="col-lg-6 col-md-10 order-lg-2">
                                <div class="portfolio__inner__thumb">
                                    <a href="">
                                        <img src="{{ asset($event->event_image) }}" style="height:450px" width="500px" alt="Event Image">
                                    </a>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-10 order-lg-1">
                                <div class="portfolio__inner__content">
                                    <h2 class="title"><a href="">{{ $event->title }}</a></h2>

                                    <!-- All fields reverse: value first, then the label -->
                                    <p>{{ $event->description }} : <strong>Event Details</strong></p>
                                    <p>{{ $event->organizer_name }} : <strong>Organizer</strong></p>
                                    <p>{{ \Carbon\Carbon::parse($event->start_time)->format('F j, Y') }} : <strong>Start Date</strong></p>
                                    <p>{{ \Carbon\Carbon::parse($event->end_time)->format('F j, Y') }} : <strong>End Date</strong></p>
                                    <p>{{ ucfirst($event->type) }} : <strong>Mode</strong></p>
                                    <a href="" class="link">View Details</a>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
                @endforeach

            </div>
        </div>
    </section>
    <!-- portfolio-area-end -->

</main>
<!-- main-area-end -->

@endsection
