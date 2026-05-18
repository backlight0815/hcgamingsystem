@extends('admin.admin_master')
@section('admin')

<title>Recording Classes | HC Gaming Studio</title>

<style>
    .recording-shell {
        color: #1f2937;
    }

    .recording-header {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 22px 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .recording-header h4 {
        margin: 0;
        font-weight: 700;
        color: #0f172a;
    }

    .recording-header p {
        margin: 6px 0 0;
        color: #64748b;
    }

    .recording-metric {
        min-width: 150px;
        border-left: 1px solid #e5e7eb;
        padding-left: 20px;
        text-align: right;
    }

    .recording-metric span {
        display: block;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .recording-metric strong {
        display: block;
        margin-top: 4px;
        color: #0f172a;
        font-size: 26px;
    }

    .security-strip {
        margin: 18px 0;
        border: 1px solid #bfd7ea;
        background: #eef7ff;
        color: #075985;
        border-radius: 8px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .recording-list {
        display: grid;
        gap: 14px;
    }

    .recording-item {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
        overflow: hidden;
    }

    .recording-main {
        padding: 18px 20px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 18px;
        align-items: center;
    }

    .recording-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }

    .recording-icon {
        width: 42px;
        height: 42px;
        border-radius: 8px;
        background: #0f172a;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }

    .recording-title h5 {
        margin: 0;
        color: #0f172a;
        font-weight: 700;
    }

    .recording-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 8px;
    }

    .meta-pill {
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        color: #475569;
        border-radius: 999px;
        padding: 4px 10px;
        font-size: 12px;
    }

    .recording-description {
        margin: 0;
        color: #64748b;
        max-width: 900px;
    }

    .recording-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .access-panel {
        display: none;
        border-top: 1px solid #e5e7eb;
        background: #f8fafc;
        padding: 16px 20px;
    }

    .access-panel.is-open {
        display: block;
    }

    .access-form {
        display: grid;
        grid-template-columns: minmax(220px, 360px) auto auto;
        gap: 10px;
        align-items: end;
    }

    .access-copy {
        margin: 0 0 12px;
        color: #475569;
        font-weight: 600;
    }

    .empty-state {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        padding: 48px 24px;
        text-align: center;
        color: #64748b;
    }

    .empty-state h5 {
        color: #0f172a;
        font-weight: 700;
        margin-bottom: 8px;
    }

    @media (max-width: 991px) {
        .recording-main {
            grid-template-columns: 1fr;
        }

        .recording-actions {
            justify-content: flex-start;
        }

        .recording-metric {
            border-left: 0;
            border-top: 1px solid #e5e7eb;
            padding-left: 0;
            padding-top: 14px;
            text-align: left;
            width: 100%;
        }

        .recording-header {
            align-items: flex-start;
            flex-direction: column;
        }
    }

    @media (max-width: 640px) {
        .access-form {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-content recording-shell">
    <div class="container-fluid">

        <div class="recording-header">
            <div>
                <h4>Recording Classes</h4>
                <p>Access uploaded trading class recordings with password confirmation for each view or download.</p>
            </div>
            <div class="recording-metric">
                <span>Available Classes</span>
                <strong>{{ $recordings->total() }}</strong>
            </div>
        </div>

        <div class="breadcrumb my-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="security-strip">
            <i class="fas fa-lock"></i>
            <span>Your login password is required before opening any recording website or download website.</span>
        </div>

        <div class="recording-list">
            @forelse($recordings as $recording)
                <div class="recording-item">
                    <div class="recording-main">
                        <div>
                            <div class="recording-title">
                                <span class="recording-icon"><i class="fas fa-play"></i></span>
                                <div>
                                    <h5>{{ $recording->title }}</h5>
                                    <div class="recording-meta">
                                        <span class="meta-pill">{{ $recording->source_name ?? 'Video website' }}</span>
                                        <span class="meta-pill">Uploaded {{ $recording->created_at?->format('M d, Y') }}</span>
                                        @if($recording->materials_count > 0)
                                            <span class="meta-pill">{{ $recording->materials_count }} material{{ (int) $recording->materials_count === 1 ? '' : 's' }}</span>
                                        @endif
                                        <span class="meta-pill">Password required</span>
                                    </div>
                                </div>
                            </div>
                            <p class="recording-description">
                                {{ $recording->description ? \Illuminate\Support\Str::limit($recording->description, 180) : 'No description added for this recording.' }}
                            </p>
                        </div>

                        <div class="recording-actions">
                            <button type="button"
                                    class="btn btn-outline-primary access-toggle"
                                    data-panel="access-panel-{{ $recording->id }}"
                                    data-action="{{ route('trading.recordings.view', $recording->id) }}"
                                    data-label="View Recording"
                                    data-button-class="btn btn-primary">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button type="button"
                                    class="btn btn-outline-success access-toggle"
                                    data-panel="access-panel-{{ $recording->id }}"
                                    data-action="{{ route('trading.recordings.download', $recording->id) }}"
                                    data-label="Download Recording"
                                    data-button-class="btn btn-success">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>

                    <div class="access-panel" id="access-panel-{{ $recording->id }}">
                        <p class="access-copy">Confirm your password to continue.</p>
                        <form method="POST" class="access-form">
                            @csrf
                            <div>
                                <label class="form-label" for="password-{{ $recording->id }}">Login Password</label>
                                <input type="password"
                                       name="password"
                                       id="password-{{ $recording->id }}"
                                       class="form-control"
                                       autocomplete="current-password"
                                       required>
                            </div>
                            <button type="submit" class="btn btn-primary access-submit">Continue</button>
                            <button type="button" class="btn btn-light access-cancel" data-panel="access-panel-{{ $recording->id }}">Cancel</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <h5>No active recording classes</h5>
                    <p class="mb-0">Uploaded recordings will appear here after admin sets Trader Access to Active.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-3">
            {{ $recordings->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function closePanels() {
        document.querySelectorAll('.access-panel').forEach(function (panel) {
            panel.classList.remove('is-open');
        });
    }

    document.querySelectorAll('.access-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const panel = document.getElementById(this.dataset.panel);
            const form = panel.querySelector('form');
            const submit = panel.querySelector('.access-submit');
            const password = panel.querySelector('input[name="password"]');

            closePanels();

            form.action = this.dataset.action;
            submit.textContent = this.dataset.label;
            submit.className = this.dataset.buttonClass + ' access-submit';
            password.value = '';
            panel.classList.add('is-open');

            setTimeout(function () {
                password.focus();
            }, 100);
        });
    });

    document.querySelectorAll('.access-cancel').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById(this.dataset.panel).classList.remove('is-open');
        });
    });
});
</script>

@endsection
