@extends('admin.admin_master')
@section('admin')

<title>Edit Trading Journal | HC Gaming Studio</title>

<div class="page-content journal-page">
    <div class="container-fluid">
        <div class="page-title-box">
            <div>
                <h4>Edit Trade</h4>
                <ol class="breadcrumb m-0 mt-2">
                    <li class="breadcrumb-item"><a href="{{ route('all.trading.journals') }}">Trading Journal</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('trading.journal.details', $journal->id) }}">Trade Details</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
            <a href="{{ route('trading.journal.details', $journal->id) }}" class="btn btn-light">
                <i class="mdi mdi-eye-outline me-1"></i> View Trade
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
            'action' => route('update.trading.journal', $journal->id),
            'journal' => $journal,
            'tradingPairs' => $tradingPairs,
        ])
    </div>
</div>

@endsection
