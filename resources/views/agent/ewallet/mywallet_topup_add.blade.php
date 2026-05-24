@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>E-Wallet Top Up | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Wallet Centre</div>
                <h1>Top Up E-Wallet</h1>
                <p>Submit your payment amount and receipt. Administration will verify the proof before the balance is credited.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('My.Wallet') }}" class="btn btn-outline-light">
                    <i class="fas fa-wallet"></i>
                    Back to Wallet
                </a>
            </div>
        </section>

        @if(!$featureEnabled)
            <div class="alert alert-warning commerce-alert">
                E-Wallet top up is currently disabled. You can prepare the request, but submission will be blocked until administration enables this feature.
            </div>
        @endif

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Top-Up Request</h2>
                    <p class="commerce-panel__subtitle">Use a clear receipt image so the finance team can review faster.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('store.wallet') }}" id="topupForm" enctype="multipart/form-data" class="commerce-form-grid">
                @csrf

                <div class="commerce-form-section">
                    <div>
                        <label for="amount">Top-Up Amount</label>
                        <input name="amount" class="form-control" type="number" min="0" step="0.01" id="amount" value="{{ old('amount') }}" required>
                        @error('amount')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="receipt">Payment Proof</label>
                        <input name="receipt" class="form-control" type="file" id="receipt" accept="image/*" required>
                        @error('receipt')
                            <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-info" id="submitButton">
                            <i class="fas fa-paper-plane"></i>
                            Submit Top-Up
                        </button>
                    </div>
                </div>

                <aside class="commerce-preview">
                    <img id="receiptPreview" class="commerce-preview__image" src="{{ asset('upload/default.jpg') }}" alt="Receipt preview">
                    <div class="commerce-preview__body">
                        <strong>Receipt Preview</strong>
                        <p class="commerce-muted mb-0">Accepted image upload: JPG, PNG, or GIF depending on platform validation.</p>
                    </div>
                </aside>
            </form>
        </section>
    </div>
</div>

<script>
    (function () {
        var featureEnabled = @json($featureEnabled);
        var formSubmitted = false;
        var form = document.getElementById('topupForm');
        var submitButton = document.getElementById('submitButton');
        var receipt = document.getElementById('receipt');
        var preview = document.getElementById('receiptPreview');

        if (receipt && preview) {
            receipt.addEventListener('change', function (event) {
                var file = event.target.files[0];
                if (!file) return;

                var reader = new FileReader();
                reader.onload = function (readerEvent) {
                    preview.src = readerEvent.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (!featureEnabled) {
                    event.preventDefault();
                    alert('E-Wallet Top Up feature is currently disabled.');
                    return;
                }

                if (formSubmitted) {
                    event.preventDefault();
                    return;
                }

                formSubmitted = true;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            });
        }
    })();
</script>
@endsection
