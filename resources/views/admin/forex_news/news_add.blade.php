@extends('admin.admin_master')
@section('admin')

<title>Add Trading News | HC Gaming Studio</title>

@include('admin.forex_news._styles')

<div class="page-content news-admin">
    <div class="container-fluid">
        <div class="news-hero mb-4">
            <div>
                <div class="eyebrow">Trading News Desk</div>
                <h1>Create Market Briefing</h1>
                <p>Prepare a clean trader-facing announcement for scheduled market news and optional Discord delivery.</p>
            </div>
            <a href="{{ route('trading.news.index') }}" class="btn btn-outline-light">
                <i class="ri-arrow-left-line"></i> All News
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if($communities->isEmpty())
            <div class="alert alert-warning">Create an active community before publishing trading news.</div>
        @endif

        @include('admin.forex_news._form', [
            'mode' => 'create',
            'action' => route('trading.news.store'),
            'cancelRoute' => route('trading.news.index'),
        ])
    </div>
</div>

@endsection
