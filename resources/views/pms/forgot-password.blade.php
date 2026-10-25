@extends('pms.auth-layout')
@section('title', 'Password recovery')
@section('story-title', 'Get back to the work that matters.')
@section('story-description', 'Secure account recovery keeps your team connected to its plans, work, and release decisions.')

@section('panel')
    <div class="pm-auth-heading">
        <span class="pm-auth-kicker">ACCOUNT RECOVERY</span>
        <h1 id="pm-auth-title">Reset your password</h1>
        <p>Enter your work email and we’ll send a secure reset link if your account exists.</p>
    </div>

    @if(session('success'))
        <div class="pm-auth-notice pm-auth-notice-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="pm-auth-notice pm-auth-notice-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form class="pm-auth-form" method="post" action="{{ route('pms.password.email') }}">
        @csrf
        <div class="pm-auth-field">
            <label for="pms-email">Work email</label>
            <input id="pms-email" type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email" inputmode="email" required autofocus @if($errors->has('email')) aria-invalid="true" @endif>
        </div>
        <button class="pm-auth-submit" type="submit">Send reset link <span aria-hidden="true">→</span></button>
    </form>

    <p class="pm-auth-secondary"><a href="{{ route('pms.login') }}">Back to sign in</a></p>
@endsection
