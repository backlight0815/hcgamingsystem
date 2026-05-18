@extends('admin.admin_master')
@section('admin')

<title>Add Trading Journal | HC Gaming Studio</title>

<div class="page-content journal-page">
    <div class="container-fluid">
        <div class="page-title-box">
            <div>
                <h4>Record New Trade</h4>
                <ol class="breadcrumb m-0 mt-2">
                    <li class="breadcrumb-item"><a href="{{ route('all.trading.journals') }}">Trading Journal</a></li>
                    <li class="breadcrumb-item active">Record New Trade</li>
                </ol>
            </div>
            <a href="{{ route('all.trading.journals') }}" class="btn btn-light">
                <i class="mdi mdi-arrow-left me-1"></i> Back to Journal
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @include('admin.trading_journals._form', [
            'action' => route('store.trading.journal'),
            'tradingPairs' => $tradingPairs,
        ])
    </div>
</div>

@endsection
