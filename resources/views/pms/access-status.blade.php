@extends('pms.auth-layout')
@section('title', 'Membership status')
@section('story-title', 'Your workspace is ready when you are.')
@section('story-description', 'Private project information stays available only to people with approved access.')

@section('panel')
    <div class="pm-auth-heading">
        <span class="pm-auth-kicker">MEMBERSHIP STATUS</span>
        <h1 id="pm-auth-title">{{ ucfirst($profile->status) }}</h1>
        <p>
            @if($profile->status === 'pending')
                Your registration is waiting for administrator approval. Private project information will be available when your membership is active.
            @elseif($profile->status === 'inactive')
                Your PMS membership is inactive. Contact a workspace administrator if you still need access.
            @else
                Your request was not approved. Contact a workspace administrator for details.
            @endif
        </p>
    </div>
    <form method="post" action="{{ route('pms.logout') }}">
        @csrf
        <button class="pm-auth-submit" type="submit">Sign out <span aria-hidden="true">→</span></button>
    </form>
@endsection
