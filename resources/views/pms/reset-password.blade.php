@extends('pms.auth-layout')
@section('title', 'Choose a new password')
@section('story-title', 'Get back to the work that matters.')
@section('story-description', 'Restore access to your private delivery workspace.')
@section('panel')
<div class="pm-auth-heading"><span class="pm-auth-kicker">ACCOUNT RECOVERY</span><h1 id="pm-auth-title">Choose a new password</h1><p>Use a strong password for your Project Management account.</p></div>
@if($errors->any())<div class="pm-auth-notice pm-auth-notice-error" role="alert">{{ $errors->first() }}</div>@endif
<form class="pm-auth-form" method="post" action="{{ route('pms.password.update') }}">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="pm-auth-field"><label for="pms-email">Work email</label><input id="pms-email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></div>
    <div class="pm-auth-field"><label for="pms-password">New password</label><input id="pms-password" type="password" name="password" minlength="10" required autocomplete="new-password"></div>
    <div class="pm-auth-field"><label for="pms-confirm">Confirm password</label><input id="pms-confirm" type="password" name="password_confirmation" minlength="10" required autocomplete="new-password"></div>
    <p class="pm-auth-password-hint">Use at least 10 characters, including upper and lower case letters and a number.</p>
    <button class="pm-auth-submit" type="submit">Reset password</button>
</form>
@endsection
