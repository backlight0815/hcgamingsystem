@extends('admin.admin_master')
@section('admin')

<title>Edit Market Analysis | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        @include('admin.market_analyst._form', [
            'mode' => 'edit',
            'action' => route('market-analyst.update', $analysis->id),
            'allowAllCommunity' => false,
        ])
    </div>
</div>

@endsection
