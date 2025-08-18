@php
    $events = App\Models\Event::latest()->get();
@endphp

<style>
    .description {
        font-size: 16px;
        font-family: 'Times New Roman', Times, serif;
        color: black;
    }

    /* Adjust the image size */
    .services__thumb img {
        width: 100%; /* Ensures the image takes full width of the container */
        height: 400px; /* You can adjust the height as per your design */
        object-fit: cover; Ensures the image covers the container without distortion
    }

    /* Adjust the container column size */
    .col-xl-3 {
        width: 120%; /* To make the columns take more space */
        padding:15px;
        margin-bottom: 15px; /* Adds some spacing between the columns */
    }
</style>

<section class="services">
    <div class="container">
        <div class="services__title__wrap">
            <div class="row align-items-center justify-content-between">
                <div class="col-xl-5 col-lg-6 col-md-8">
                    <div class="section__title">
                        <span class="sub-title">02 - Upcoming Event</span>
                        <h2 class="title">Upcoming Events</h2>

                    </div>
                </div>

                <div class="col-xl-5 col-lg-6 col-md-4">
                    <div class="services__arrow"></div>
                </div>
            </div>
        </div>

        <div class="row gx-0 services__active">
            @foreach($events as $item)

            <div class="col-xl-3">
                <div class="services__item">
                    <div class="services__thumb">
                        <!-- Adjust image size here -->
                        <a href="services-details.html">
                            <img src="{{ asset($item->event_image) }}" alt="Event Image">
                        </a>
                    </div>
                    <div class="services__content">
                        <div class="services__icon">
                         {{-- //   <img src="{{ asset($item->event_image) }}" alt="Event Image"> --}}
                        </div>
                        <h3 class="title"><a href="services-details.html">{{ $item->title }}</a></h3>

                            <p1 class="type"><strong>Type:</strong>{{ $item->type }}</a></p1>
                            <br>
                            <p1 class="location"><strong>Location:</strong>{{ $item->location }}</a></p1>
                            <br>

                            <p1 class="organizer_name"><strong>Organizer:</strong>{{ $item->organizer_name }}</a></p1>
                            <br>

                        <a href="" class="btn border-btn">Read more</a>
                    </div>
                </div>
            </div>

            @endforeach
        </div>

    </div>
</section>
