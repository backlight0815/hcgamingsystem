@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<title>Add Trading Pair | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        {{-- Flash Message --}}
        @if(session('message'))
        <div class="alert alert-{{ session('alert-type') }} alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Add New Trading Pair</h4><br><br>

                        <form method="POST" action="{{ route('store.trading.pair') }}">
                            @csrf

                            {{-- Symbol --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Symbol</label>
                                <div class="col-sm-9">
                                    <input name="symbol" type="text" class="form-control" placeholder="e.g. XAUUSD" value="{{ old('symbol') }}">
                                    @error('symbol')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Description --}}
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label">Description</label>
                                <div class="col-sm-9">
                                    <input name="description" type="text" class="form-control" placeholder="e.g. Gold vs USD" value="{{ old('description') }}">
                                    @error('description')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Submit Button --}}
                            <div class="text-end">
                                <button type="submit" class="btn btn-info waves-effect waves-light">Add Pair</button>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection
