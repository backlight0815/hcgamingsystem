@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>Trading Reason Management - Add | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <h4 class="text-primary fw-bold">✏️ Add Trading Reason</h4>
                <p class="text-muted">Create a new reason that can be assigned to trading signals.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="{{ route('all.trading.reason') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <!-- Form Card -->
        <div class="row">
            <div class="col-lg-6 col-md-8 mx-auto">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-4">

                        <form method="POST" id="submitTradingReason" action="{{ route('store.trading.reason') }}">
                            @csrf

                            <!-- Reason Name -->
                            <div class="mb-4">
                                <label for="reasonName" class="form-label fw-semibold">Reason Name <span class="text-danger">*</span></label>
                                <input name="name" class="form-control form-control-lg" type="text" id="reasonName" placeholder="Enter reason name" value="{{ old('name') }}">
                                @error('name')
                                    <span class="text-danger small">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <label for="reasonDescription" class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control form-control-lg" id="reasonDescription" rows="4" placeholder="Optional description for the reason">{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="text-danger small">{{ $message }}</span>
                                @enderror
                            </div>

                            <!-- Submit Button -->
                            <div class="d-flex justify-content-between">
                                <a href="{{ route('all.trading.reason') }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg" id="submitButton" onclick="disableButton()">
                                    <span class="me-2"><i class="fas fa-plus"></i></span> Add Reason
                                </button>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
        </div>
        <!-- end form card -->

    </div> <!-- container-fluid -->
</div> <!-- page-content -->

<script type="text/javascript">
var formSubmitted = false;
function disableButton() {
    if (formSubmitted) return false;
    var submitButton = document.getElementById('submitButton');
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    formSubmitted = true;
    setTimeout(function () {
        document.getElementById('submitTradingReason').submit();
    }, 300);
}
</script>

@endsection
