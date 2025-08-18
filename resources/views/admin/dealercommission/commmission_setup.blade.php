@extends('admin.admin_master')
@section('admin')
<style>
    @media screen and (max-width: 768px) {
        .table-responsive {
            overflow-x: auto;
        }
    }

    .modal-lg {
        max-width: 90%;
    }

    .img-enlarge {
        cursor: pointer;
    }

    .img-enlarge.enlarged {
        max-width: 100%;
        max-height: 100%;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        margin: auto;
        z-index: 1050;
        background-color: rgba(0, 0, 0, 0.5);
    }
</style>
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.min.js"></script>
    <title>HC Gaming | Setup Commission</title>
</head>

<div class="page-content">
    <div class="container-fluid">
        <div class="container">
            <h1>Setup Commission</h1>

            @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
            @endif

            @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form action="{{ route('admin.commission.save') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label for="commission_percentage">Commission Percentage</label>
                    <input type="text" name="commission_percentage" id="commission_percentage" class="form-control" value="{{ old('commission_percentage', $commissionPercentage) }}">
                </div>

                <div class="form-group">
                    <label for="extra_percentage">Extra Commission Percentage</label>
                    <input type="text" name="extra_percentage" id="extra_percentage" class="form-control" value="{{ old('extra_percentage', $extra_percentage) }}">
                </div>
                <br>
                <br>
                <button type="submit" class="btn btn-primary">Save Commission</button>
            </form>

        </div>
    </div>
</div>
@endsection
