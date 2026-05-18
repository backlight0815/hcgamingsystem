@extends('admin.admin_master')
@section('admin')

<title>Generate Trading Certificate | HC Gaming Studio</title>

<style>
    .cert-panel {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .cert-panel-header {
        padding: 18px 20px;
        border-bottom: 1px solid #e5e7eb;
    }

    .cert-panel-header h5 {
        color: #0f172a;
        font-weight: 700;
        margin: 0;
    }

    .cert-panel-header p {
        color: #64748b;
        margin: 6px 0 0;
    }

    .cert-panel-body {
        padding: 20px;
    }

    .template-note {
        border: 1px solid #e2c779;
        background: #fff8e5;
        color: #775d13;
        border-radius: 8px;
        padding: 14px 16px;
        margin-bottom: 18px;
    }

    .cert-thumb {
        height: 42px;
        width: 58px;
        border-radius: 6px;
        background: #111827;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #e9c36a;
        font-weight: 800;
    }
</style>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Generate Trading Certificate</h4>
                <a href="{{ route('certificate.index') }}" class="btn btn-light">
                    <i class="fas fa-arrow-left"></i> All Certificates
                </a>
            </div>
        </div>

        <div class="breadcrumb mb-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="cert-panel mb-4">
            <div class="cert-panel-header">
                <h5>HC Traders Club Certificate Generator</h5>
                <p>Issue a consistent certificate template. The generated image changes only by recipient name and publish date.</p>
            </div>
            <div class="cert-panel-body">
                <div class="template-note">
                    Certificate wording includes completion of trading classes, demonstrated discipline, passed evaluation, and strategy presentation. The HC logo, HC Founder signature, founder name, and HC Traders Club branding are included automatically.
                </div>

                <form action="{{ route('certificate.add') }}" method="POST">
                    @csrf
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label for="user_id" class="form-label fw-semibold">Recipient Account</label>
                            <select name="user_id" id="user_id" class="form-control" required>
                                <option value="">Select recipient</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}"
                                            data-name="{{ $user->name ?: $user->username }}"
                                            {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name ?: $user->username }} - {{ $user->email }} (Role {{ $user->role_id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-lg-4">
                            <label for="recipient_name" class="form-label fw-semibold">Certificate Name</label>
                            <input type="text"
                                   name="recipient_name"
                                   id="recipient_name"
                                   class="form-control"
                                   value="{{ old('recipient_name') }}"
                                   placeholder="Example: Sua Kai Young"
                                   required>
                        </div>

                        <div class="col-lg-4">
                            <label for="founder_name" class="form-label fw-semibold">HC Founder Name</label>
                            <input type="text"
                                   name="founder_name"
                                   id="founder_name"
                                   class="form-control"
                                   value="{{ old('founder_name', 'Sua Kai Young') }}"
                                   required>
                        </div>

                        <div class="col-md-4">
                            <label for="level" class="form-label fw-semibold">Certificate Level</label>
                            <select name="level" id="level" class="form-control" required>
                                @foreach($levels as $value => $label)
                                    <option value="{{ $value }}" {{ old('level', 'completion') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="certificate_type" class="form-label fw-semibold">Certificate Track</label>
                            <select name="certificate_type" id="certificate_type" class="form-control" required>
                                @foreach($types as $value => $label)
                                    <option value="{{ $value }}" {{ old('certificate_type', 'trading_class_completion') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="status" class="form-label fw-semibold">Issue Status</label>
                            <select name="status" id="status" class="form-control" required>
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', 'published') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="discipline_summary" class="form-label fw-semibold">Discipline Notes</label>
                            <textarea name="discipline_summary"
                                      id="discipline_summary"
                                      class="form-control"
                                      rows="4"
                                      placeholder="Internal note for admin review.">{{ old('discipline_summary') }}</textarea>
                        </div>

                        <div class="col-md-6">
                            <label for="strategy_summary" class="form-label fw-semibold">Strategy Evaluation Notes</label>
                            <textarea name="strategy_summary"
                                      id="strategy_summary"
                                      class="form-control"
                                      rows="4"
                                      placeholder="Internal note for strategy review or evaluation context.">{{ old('strategy_summary') }}</textarea>
                        </div>

                        <div class="col-md-6">
                            <label for="password" class="form-label fw-semibold">Login Password Confirmation</label>
                            <input type="password" name="password" id="password" class="form-control" autocomplete="current-password" required>
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-award"></i> Generate Certificate
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="cert-panel">
            <div class="cert-panel-header">
                <h5>Recent Certificates</h5>
                <p>Latest generated records for quick review.</p>
            </div>
            <div class="cert-panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Certificate</th>
                                <th>Recipient</th>
                                <th>Track</th>
                                <th>Status</th>
                                <th>Published Date</th>
                                <th>Verification</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($certificates as $cert)
                                <tr>
                                    <td><span class="cert-thumb">HC</span></td>
                                    <td>
                                        <strong>{{ $cert->recipient_display_name }}</strong>
                                        <div class="text-muted small">{{ $cert->user?->email ?? '-' }}</div>
                                    </td>
                                    <td>{{ $cert->certificate_type_label }}</td>
                                    <td>{{ $cert->status_label }}</td>
                                    <td>{{ $cert->published_at?->format('Y-m-d') ?? '-' }}</td>
                                    <td>{{ $cert->verification_code ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No certificates generated yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const userSelect = document.getElementById('user_id');
    const nameInput = document.getElementById('recipient_name');

    function fillRecipientName() {
        const selected = userSelect.options[userSelect.selectedIndex];
        if (selected && selected.dataset.name && nameInput.value.trim() === '') {
            nameInput.value = selected.dataset.name;
        }
    }

    userSelect.addEventListener('change', function () {
        const selected = userSelect.options[userSelect.selectedIndex];
        nameInput.value = selected && selected.dataset.name ? selected.dataset.name : '';
    });

    fillRecipientName();
});
</script>

@endsection
