@extends('admin.admin_master')
@section('admin')

<title>Trading Certificates | HC Gaming Studio</title>

<style>
    .cert-shell .cert-stat {
        border: 1px solid #dfe5ec;
        background: #ffffff;
        border-radius: 8px;
        padding: 18px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .cert-shell .cert-stat span {
        display: block;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .cert-shell .cert-stat strong {
        display: block;
        color: #0f172a;
        font-size: 28px;
        margin-top: 6px;
    }

    .cert-card {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
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

    .cert-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 700;
    }

    .cert-badge-published { background: #dcfce7; color: #166534; }
    .cert-badge-approved { background: #dbeafe; color: #1e40af; }
    .cert-badge-draft { background: #f3f4f6; color: #374151; }
    .cert-badge-revoked { background: #fee2e2; color: #991b1b; }

    @media (max-width: 767px) {
        .cert-access-form {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content cert-shell">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12 d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Trading Certificates</h4>
                @if(in_array((int) auth()->user()->role_id, [1, 2], true))
                    <a href="{{ route('certificate.create') }}" class="btn btn-success">
                        <i class="fas fa-plus"></i> Generate Certificate
                    </a>
                @endif
            </div>
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

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="cert-stat">
                    <span>Total Certificates</span>
                    <strong>{{ $certificates->count() }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="cert-stat">
                    <span>Published</span>
                    <strong>{{ $certificates->where('status', \App\Models\SignalProviderCertificate::STATUS_PUBLISHED)->count() }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="cert-stat">
                    <span>Approved</span>
                    <strong>{{ $certificates->where('status', \App\Models\SignalProviderCertificate::STATUS_APPROVED)->count() }}</strong>
                </div>
            </div>
        </div>

        @if(in_array((int) auth()->user()->role_id, [1, 2], true))
            <form method="GET" class="row mb-3">
                <div class="col-md-3 mb-2">
                    <label for="user_id" class="form-label">Recipient</label>
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">All Recipients</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                {{ $user->name ?: $user->username }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="level" class="form-label">Level</label>
                    <select name="level" id="level" class="form-select">
                        <option value="">All Levels</option>
                        @foreach($levels as $value => $label)
                            <option value="{{ $value }}" {{ request('level') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" {{ request('status') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('certificate.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        @endif

        <div class="cert-card">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Recipient</th>
                            <th>Certificate</th>
                            <th>Status</th>
                            <th>Published</th>
                            <th>Verification</th>
                            <th>Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($certificates as $cert)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <strong>{{ $cert->recipient_display_name }}</strong>
                                    <div class="text-muted small">{{ $cert->user?->email ?? '-' }}</div>
                                    <div class="text-muted small">Role {{ $cert->user?->role_id ?? '-' }}</div>
                                </td>
                                <td>
                                    <strong>{{ $cert->certificate_title }}</strong>
                                    <div class="text-muted small">{{ $cert->certificate_type_label }}</div>
                                    <div class="text-muted small">{{ $cert->level_label }}</div>
                                </td>
                                <td>
                                    <span class="cert-badge cert-badge-{{ $cert->status }}">
                                        {{ $cert->status_label }}
                                    </span>
                                </td>
                                <td>{{ $cert->published_at?->format('Y-m-d') ?? '-' }}</td>
                                <td>{{ $cert->verification_code ?? '-' }}</td>
                                <td>
                                    <div class="small text-muted">Views: {{ $cert->view_count }}</div>
                                    <div class="small text-muted">Downloads: {{ $cert->download_count }}</div>
                                </td>
                                <td class="text-nowrap">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary cert-access-toggle"
                                            data-panel="cert-panel-{{ $cert->id }}"
                                            data-action="{{ route('certificate.view', $cert->id) }}"
                                            data-method="POST"
                                            data-label="View Certificate"
                                            data-button-class="btn btn-primary">
                                        View
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-success cert-access-toggle"
                                            data-panel="cert-panel-{{ $cert->id }}"
                                            data-action="{{ route('certificate.download', $cert->id) }}"
                                            data-method="POST"
                                            data-label="Download Certificate"
                                            data-button-class="btn btn-success">
                                        Download
                                    </button>
                                    @if(in_array((int) auth()->user()->role_id, [1, 2], true))
                                        <button type="button"
                                                class="btn btn-sm btn-outline-secondary cert-access-toggle"
                                                data-panel="cert-panel-{{ $cert->id }}"
                                                data-action="{{ route('certificate.regenerate', $cert->id) }}"
                                                data-method="POST"
                                                data-label="Refresh Certificate"
                                                data-button-class="btn btn-secondary">
                                            Refresh
                                        </button>
                                        @if($cert->status !== \App\Models\SignalProviderCertificate::STATUS_PUBLISHED)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-dark cert-access-toggle"
                                                    data-panel="cert-panel-{{ $cert->id }}"
                                                    data-action="{{ route('certificate.publish', $cert->id) }}"
                                                    data-method="POST"
                                                    data-label="Publish Certificate"
                                                    data-button-class="btn btn-dark">
                                                Publish
                                            </button>
                                        @endif
                                        @if($cert->status === \App\Models\SignalProviderCertificate::STATUS_DRAFT)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-info cert-access-toggle"
                                                    data-panel="cert-panel-{{ $cert->id }}"
                                                    data-action="{{ route('certificate.approve', $cert->id) }}"
                                                    data-method="POST"
                                                    data-label="Approve Certificate"
                                                    data-button-class="btn btn-info">
                                                Approve
                                            </button>
                                        @endif
                                        @if($cert->status !== \App\Models\SignalProviderCertificate::STATUS_REVOKED)
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-warning cert-access-toggle"
                                                    data-panel="cert-panel-{{ $cert->id }}"
                                                    data-action="{{ route('certificate.revoke', $cert->id) }}"
                                                    data-method="POST"
                                                    data-label="Revoke Certificate"
                                                    data-button-class="btn btn-warning">
                                                Revoke
                                            </button>
                                        @endif
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger cert-access-toggle"
                                                data-panel="cert-panel-{{ $cert->id }}"
                                                data-action="{{ route('certificate.destroy', $cert->id) }}"
                                                data-method="DELETE"
                                                data-label="Delete Certificate"
                                                data-button-class="btn btn-danger">
                                            Delete
                                        </button>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td colspan="8" class="p-0">
                                    <div class="cert-access-panel" id="cert-panel-{{ $cert->id }}">
                                        <form method="POST" class="cert-access-form">
                                            @csrf
                                            <input type="hidden" name="_method" value="" disabled>
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
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No certificates found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
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
            const methodInput = panel.querySelector('input[name="_method"]');

            closePanels();
            form.action = this.dataset.action;
            submit.textContent = this.dataset.label;
            submit.className = this.dataset.buttonClass + ' cert-access-submit';
            password.value = '';

            if (this.dataset.method === 'DELETE') {
                methodInput.disabled = false;
                methodInput.value = 'DELETE';
            } else {
                methodInput.disabled = true;
                methodInput.value = '';
            }

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
