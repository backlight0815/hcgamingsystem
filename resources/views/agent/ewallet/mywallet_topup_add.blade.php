@extends('admin.admin_master')
@section('admin')

<head>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
</head>

<title>E-Wallet Top Up | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">My E-wallet Top Up</h4>

                        <form method="POST" action="{{ route('store.wallet') }}" id="topupForm" enctype="multipart/form-data">
                            @csrf

                            <div class="row mb-3">
                                <label for="amount" class="col-sm-2 col-form-label">Top-up Amount</label>
                                <div class="col-sm-10">
                                    <input name="amount" class="form-control" type="number" min="0" step="0.01" id="amount" required>
                                    @error('amount')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="receipt" class="col-sm-2 col-form-label">Payment Proof</label>
                                <div class="col-sm-10">
                                    <input name="receipt" class="form-control" type="file" id="receipt" accept="image/*" required>
                                    @error('receipt')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <input type="submit" class="btn btn-info waves-effect waves-light" id="submitButton" value="Top-up E-Wallet" onclick="return disableButton()">
                        </form>

                    </div>
                </div>
            </div> <!-- end col -->
        </div>
    </div>
</div>
<script>
    // Pass PHP boolean to JS
    var featureEnabled = @json($featureEnabled);

    var formSubmitted = false;

    function disableButton() {
        if (!featureEnabled) {
            alert('E-Wallet Top Up feature is currently disabled.');
            return false; // Prevent form submission
        }

        if (formSubmitted) {
            return false; // Prevent multiple submits
        }

        var submitButton = document.getElementById('submitButton');
        submitButton.disabled = true;
        submitButton.value = 'Submitting...'; // Show loading text

        // Delay submit to show disabled effect
        setTimeout(function () {
            document.getElementById('topupForm').submit();
        }, 500);

        formSubmitted = true;
        return true;
    }
</script>


@endsection
