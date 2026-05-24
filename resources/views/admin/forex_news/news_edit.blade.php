@extends('admin.admin_master')
@section('admin')

<title>Edit Trading News | HC Gaming Studio</title>

@include('admin.forex_news._styles')

<div class="page-content news-admin">
    <div class="container-fluid">
        <div class="news-hero mb-4">
            <div>
                <div class="eyebrow">Trading News Desk</div>
                <h1>Edit Market Briefing</h1>
                <p>Update the timing, impact level, community, and image used for this trading news announcement.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('trading.news.show', $news->id) }}" class="btn btn-outline-light">
                    <i class="ri-eye-line"></i> View Details
                </a>
                <a href="{{ route('trading.news.index') }}" class="btn btn-light">
                    <i class="ri-list-check"></i> All News
                </a>
            </div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @include('admin.forex_news._form', [
            'mode' => 'edit',
            'action' => route('trading.news.update', $news->id),
            'cancelRoute' => route('trading.news.show', $news->id),
        ])
    </div>
</div>

@endsection
