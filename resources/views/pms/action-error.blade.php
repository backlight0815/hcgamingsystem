<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $heading }} · Project Management</title><link rel="icon" type="image/svg+xml" href="{{ asset('backend/assets/images/hc-backoffice-mark.svg') }}"><link rel="stylesheet" href="{{ asset('backend/assets/css/pms.css') }}"><link rel="stylesheet" href="{{ asset('backend/assets/css/pms-admin-refresh.css') }}"></head>
<body class="pm-action-error-page"><main class="pm-action-error pm-panel" role="alert">
<p class="pm-eyebrow">PROJECT MANAGEMENT / {{ $status }}</p><h1>{{ $heading }}</h1><p>{{ $message }}</p>
<div class="pm-feedback-actions"><a class="pm-button" href="{{ url()->previous() }}">Go back</a><a class="pm-button secondary" href="{{ route('pms.dashboard') }}">Open dashboard</a></div>
</main></body></html>
