@extends('admin.admin_master')
@section('admin')

<title>Edit Trading Pair | HC Gaming Studio</title>

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

                        <h4 class="card-title">Edit Trading Pair</h4><br><br>

                        <form method="POST" action="{{ route('update.trading.pair', $pair->id) }}">
                            @csrf

                            {{-- Symbol --}}
                            <div class="row mb-3">
                                <label for="symbol" class="col-sm-2 col-form-label">Symbol</label>
                                <div class="col-sm-10">
                                    <input name="symbol" class="form-control" type="text" id="symbol" value="{{ old('symbol', $pair->symbol) }}">
                                    @error('symbol')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Description --}}
                            <div class="row mb-3">
                                <label for="description" class="col-sm-2 col-form-label">Description</label>
                                <div class="col-sm-10">
                                    <textarea name="description" class="form-control" id="description">{{ old('description', $pair->description) }}</textarea>
                                    @error('description')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            {{-- Submit Button --}}
                            <input type="submit" class="btn btn-info waves-effect waves-light" value="Update Pair">

                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection
