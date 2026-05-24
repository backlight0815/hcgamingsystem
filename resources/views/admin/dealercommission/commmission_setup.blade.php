@extends('admin.admin_master')
@section('admin')
@include('admin.ecommerce._styles')
<title>Commission Setup | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid commerce-page">
        <section class="commerce-hero">
            <div>
                <div class="commerce-hero__label">Commission Centre</div>
                <h1>Commission Setup</h1>
                <p>Control the base dealership commission and extra incentive percentage used by the ecommerce network.</p>
            </div>
            <div class="commerce-hero__actions">
                <a href="{{ route('all.dealer.commission') }}" class="btn btn-outline-light">
                    <i class="fas fa-chart-line"></i>
                    View Ledger
                </a>
            </div>
        </section>

        @if (session('success'))
            <div class="alert alert-success commerce-alert">{{ session('success') }}</div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger commerce-alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <section class="commerce-panel">
            <div class="commerce-panel__header">
                <div>
                    <h2 class="commerce-panel__title">Active Commission Rules</h2>
                    <p class="commerce-panel__subtitle">Use percentage values only. Changes affect future commission calculations handled by the system.</p>
                </div>
            </div>

            <form action="{{ route('admin.commission.save') }}" method="POST" class="commerce-form-grid">
                @csrf

                <div class="commerce-form-section">
                    <div>
                        <label for="commission_percentage">Base Commission Percentage</label>
                        <input type="number" step="0.01" min="0" name="commission_percentage" id="commission_percentage" class="form-control" value="{{ old('commission_percentage', $commissionPercentage) }}" required>
                        <div class="commerce-muted mt-1">Primary commission percentage for qualified downline orders.</div>
                    </div>

                    <div>
                        <label for="extra_percentage">Extra Commission Percentage</label>
                        <input type="number" step="0.01" min="0" name="extra_percentage" id="extra_percentage" class="form-control" value="{{ old('extra_percentage', $extra_percentage) }}" required>
                        <div class="commerce-muted mt-1">Additional percentage for eligible special conditions.</div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save"></i>
                            Save Commission Rules
                        </button>
                    </div>
                </div>

                <aside class="commerce-preview">
                    <div class="commerce-preview__body">
                        <strong>Calculation Preview</strong>
                        <p class="commerce-muted">For a RM 1,000 order:</p>
                        <div class="commerce-stat mb-2">
                            <span>Base Commission</span>
                            <strong id="basePreview">RM 0.00</strong>
                        </div>
                        <div class="commerce-stat mb-0">
                            <span>Extra Commission</span>
                            <strong id="extraPreview">RM 0.00</strong>
                        </div>
                    </div>
                </aside>
            </form>
        </section>
    </div>
</div>

<script>
    (function () {
        var baseInput = document.getElementById('commission_percentage');
        var extraInput = document.getElementById('extra_percentage');
        var basePreview = document.getElementById('basePreview');
        var extraPreview = document.getElementById('extraPreview');
        var sampleAmount = 1000;

        function renderPreview() {
            var base = parseFloat(baseInput.value || 0);
            var extra = parseFloat(extraInput.value || 0);
            basePreview.textContent = 'RM ' + ((sampleAmount * base) / 100).toFixed(2);
            extraPreview.textContent = 'RM ' + ((sampleAmount * extra) / 100).toFixed(2);
        }

        if (baseInput && extraInput) {
            baseInput.addEventListener('input', renderPreview);
            extraInput.addEventListener('input', renderPreview);
            renderPreview();
        }
    })();
</script>
@endsection
