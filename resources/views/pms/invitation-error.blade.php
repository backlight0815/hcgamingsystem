@extends('pms.auth-layout')
@section('title', 'Invitation unavailable')
@section('story-title', 'Your workspace invitation needs a new link.')
@section('story-description', 'Invitation links are time limited and can only be used as configured by the inviter.')
@section('panel')
@php($message = match($status ?? 'unavailable') { 'invalid' => 'This invitation link is invalid. Check the full link or ask for a new one.', 'expired' => 'This invitation has expired. Ask the inviter to send a new link.', 'accepted' => 'This invitation has already been accepted. Sign in with your PMS account.', 'revoked' => 'This invitation was revoked. Contact the inviter for a new link.', default => 'This invitation is currently unavailable. Contact the inviter or an administrator.' })
<div class="pm-auth-heading"><span class="pm-auth-kicker">PROJECT INVITATION</span><h1>Invitation unavailable</h1><p>{{ $message }}</p></div>
<a class="pm-auth-submit" href="{{ route('pms.login', ['member' => 1]) }}">Go to sign in</a>
@endsection
