@extends('pms.auth-layout')
@section('title', 'Sign in')
@section('story-title', 'A clearer way to move work forward.')
@section('story-description', 'Bring projects, delivery cycles, quality checks, and release decisions together in one focused workspace.')

@section('panel')
    <div class="pm-auth-heading">
        <span class="pm-auth-kicker">WELCOME BACK</span>
        <h1 id="pm-auth-title">Sign in to your workspace</h1>
        <p>Use your approved work account to continue.</p>
    </div>

    @if(session('success'))
        <div class="pm-auth-notice pm-auth-notice-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="pm-auth-notice pm-auth-notice-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form class="pm-auth-form" method="post" action="{{ route('pms.authenticate') }}">
        @csrf
        @if($inviteToken)<input type="hidden" name="invite" value="{{ $inviteToken }}">@endif
        <div class="pm-auth-field">
            <label for="pms-email">Work email</label>
            <input id="pms-email" type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" autocomplete="email" inputmode="email" required autofocus @if($errors->has('email')) aria-invalid="true" @endif>
        </div>
        <div class="pm-auth-field">
            <label for="pms-password">Password</label>
            <div class="pm-auth-password">
                <input id="pms-password" type="password" name="password" data-label="password" placeholder="Enter your password" autocomplete="current-password" required @if($errors->has('password')) aria-invalid="true" @endif>
                <button type="button" data-password-toggle="pms-password" aria-label="Show password" aria-pressed="false">Show</button>
            </div>
        </div>
        <div class="pm-auth-options">
            <label class="pm-auth-remember" for="pms-remember"><input id="pms-remember" type="checkbox" name="remember" value="1" @checked(old('remember'))><span>Remember me</span></label>
            <a href="{{ route('pms.password.request') }}">Forgot password?</a>
        </div>
        <button class="pm-auth-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
    </form>

    @if($registrationAllowed)
        <p class="pm-auth-secondary">New to the workspace? <a href="{{ route('pms.register') }}">Request access</a></p>
    @endif
    @include('auth.partials.module-navigation', ['currentModule' => 'pms'])
@endsection
