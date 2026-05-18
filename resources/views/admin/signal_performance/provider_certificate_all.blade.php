@extends('admin.admin_master')
@section('admin')

<title>My Trading Certificates | HC Gaming Studio</title>

<style>
    .my-cert-header {
        background: #111827;
        color: #ffffff;
        border-radius: 8px;
        padding: 26px;
        margin-bottom: 20px;
    }

    .my-cert-header h4 {
        color: #ffffff;
        font-weight: 800;
        margin: 0;
    }

    .my-cert-header p {
        color: rgba(255, 255, 255, .78);
        margin: 8px 0 0;
    }

    .my-cert-card {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .cert-access-panel {
        display: none;
        border-top: 1px solid #e5e7eb;
        background: #f8fafc;
        padding: 14px 16px;
    }

    .cert-access-panel.is-open {
        display: block;
    }

    .cert-access-form {
        display: grid;
        grid-template-columns: minmax(220px, 360px) auto auto;
        gap: 10px;
        align-items: end;
    }

    .cert-empty {
        background: #ffffff;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        padding: 44px 20px;
        text-align: center;
        color: #64748b;
    }

    @media (max-width: 767px) {
        .cert-access-form {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content">
    <div class="container-fluid">
        <div class="my-cert-header">
            <h4>My HC Traders Club Certificates</h4>
            <p>Published certificates are available here after admin approval. Confirm your login password before viewing or downloading.</p>
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

        @forelse($certificates as $cert)
            <div class="my-cert-card mb-3">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Certificate</th>
                                <th>Published Date</th>
                                <th>Verification Code</th>
                                <th>Founder</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>{{ $cert->certificate_title }}</strong>
                                    <div class="text-muted small">{{ $cert->certificate_type_label }}</div>
                                    <div class="text-muted small">Certified to {{ $cert->recipient_display_name }}</div>
                                </td>
                                <td>{{ $cert->published_at?->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $cert->verification_code ?? '-' }}</td>
                                <td>{{ $cert->founder_title }} - {{ $cert->founder_name }}</td>
                                <td class="text-nowrap">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary cert-access-toggle"
                                            data-panel="cert-panel-{{ $cert->id }}"
                                            data-action="{{ route('certificate.view', $cert->id) }}"
                                            data-label="View Certificate"
                                            data-button-class="btn btn-primary">
                                        View
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-success cert-access-toggle"
                                            data-panel="cert-panel-{{ $cert->id }}"
                                            data-action="{{ route('certificate.download', $cert->id) }}"
                                            data-label="Download Certificate"
                                            data-button-class="btn btn-success">
                                        Download
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5" class="p-0">
                                    <div class="cert-access-panel" id="cert-panel-{{ $cert->id }}">
                                        <form method="POST" class="cert-access-form">
                                            @csrf
                                            <div>
                                                <label class="form-label mb-1" for="password-{{ $cert->id }}">Confirm Login Password</label>
                                                <input type="password" name="password" id="password-{{ $cert->id }}" class="form-control" autocomplete="current-password" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary cert-access-submit">Continue</button>
                                            <button type="button" class="btn btn-light cert-access-cancel" data-panel="cert-panel-{{ $cert->id }}">Cancel</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="cert-empty">
                <h5>No published certificate yet</h5>
                <p class="mb-0">Your certificate will appear after admin approves and publishes it.</p>
            </div>
        @endforelse
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function closePanels() {
        document.querySelectorAll('.cert-access-panel').forEach(function (panel) {
            panel.classList.remove('is-open');
        });
    }

    document.querySelectorAll('.cert-access-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(this.dataset.panel);
            const form = panel.querySelector('form');
            const submit = panel.querySelector('.cert-access-submit');
            const password = panel.querySelector('input[name="password"]');

            closePanels();
            form.action = this.dataset.action;
            submit.textContent = this.dataset.label;
            submit.className = this.dataset.buttonClass + ' cert-access-submit';
            password.value = '';
            panel.classList.add('is-open');
            setTimeout(function () { password.focus(); }, 100);
        });
    });

    document.querySelectorAll('.cert-access-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById(this.dataset.panel).classList.remove('is-open');
        });
    });
});
</script>

@endsection
