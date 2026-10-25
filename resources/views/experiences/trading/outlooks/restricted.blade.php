@extends('experiences.trading.layout')
@section('title', 'Historical Market Outlooks | HC Trading Hub')
@section('robots', 'noindex,nofollow')
@section('content')
<section class="th-section"><div class="th-container th-outlook-restricted"><span class="th-kicker">Member archive</span>
    <h1>Historical Market Outlooks are available to approved members.</h1>
    <p>Sign in with an approved trading membership to read the full archive.</p>
    @if(auth()->check() && (int) auth()->user()->role_id === 750)<a class="th-button" href="{{ route('trader.onboarding.show') }}">View trader approval</a>@else<a class="th-button" href="{{ route('login') }}">Member login</a>@endif
    <a class="th-text-link" href="{{ route('trading.hub.outlooks.index') }}">Latest outlooks</a>
</div></section>
@endsection
