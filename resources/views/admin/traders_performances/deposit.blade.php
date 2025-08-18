@extends('admin.admin_master')
@section('admin')

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<title>E-Wallet Transaction | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title">Balance Transaction</h4>
                        <form method="POST" action="{{ route('capital.store') }}" id="walletForm" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="type" value="{{ $type }}">

                            <div class="row mb-3">
                                <label for="depositAmount" class="col-sm-2 col-form-label">Amount</label>
                                <div class="col-sm-10">
                                    <input name="depositAmount" class="form-control" type="number" min="0.01" step="0.01" id="depositAmount" required>
                                    @error('depositAmount')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="deposit_date" class="col-sm-2 col-form-label">Transaction Date</label>
                                <div class="col-sm-10">
                                    <input name="deposit_date" class="form-control" type="datetime-local"  id="deposit_date" required>
                                    @error('deposit_date')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row mb-3">
                                <label for="notes" class="col-sm-2 col-form-label">Notes (optional)</label>
                                <div class="col-sm-10">
                                    <textarea name="notes" class="form-control" rows="3" placeholder="Enter any remarks..."></textarea>
                                    @error('notes')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <input type="submit" class="btn btn-info waves-effect waves-light" id="submitButton" value="Submit Transaction" onclick="disableButton()">
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    var formSubmitted = false;
    function disableButton() {
        if (formSubmitted) return false;
        let submitButton = document.getElementById('submitButton');
        submitButton.disabled = true;
        submitButton.value = 'Submitting...';
        setTimeout(() => {
            document.getElementById('walletForm').submit();
        }, 500);
        formSubmitted = true;
        return true;
    }
</script>

@endsection
