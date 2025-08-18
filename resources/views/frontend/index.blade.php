@extends('frontend.main_master')
@section('main')
@section('title')
Home | HC_Gaming Studio Websites

@endsection
<!-- banner-area -->
@include('frontend.home_all.home_slide')
<!-- banner-area-end -->

@include('frontend.home_all.home_about')
<!-- services-area -->
@include('frontend.home_all.home_service')
<!-- services-area-end -->

@include('frontend.home_all.portfolio')


@include('frontend.home_all.home_blog')
<!-- blog-area-end -->

<!-- contact-area-end -->

@endsection