@extends('admin.admin_master')
@section('admin')

<title>Add Market Analysis | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        @include('admin.market_analyst._form', [
            'mode' => 'create',
            'action' => route('market-analyst.store'),
            'allowAllCommunity' => true,
        ])
    </div>
</div>

@endsection
