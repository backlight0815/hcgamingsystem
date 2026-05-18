@php
    $account = $account ?? null;
    $pageTitle = $pageTitle ?? 'Edit Account';
    $pageEyebrow = $pageEyebrow ?? 'Account Management';
    $pageDescription = $pageDescription ?? 'Review profile details, access status, and role placement before saving account changes.';
    $formAction = $formAction ?? route('update.account');
    $backRoute = $backRoute ?? route('all.account');
    $submitLabel = $submitLabel ?? 'Update Account';
    $notice = $notice ?? null;
    $errors = $errors ?? session()->get('errors', new \Illuminate\Support\ViewErrorBag);

    $roleOptions = $roleOptions ?? [
        1 => 'Super Admin',
        2 => 'Sub Admin',
        201 => 'Signal Provider',
        202 => 'Senior Signal Provider',
        350 => 'Agent',
        501 => 'Market Analyst',
        502 => 'Signal Provider Management',
        700 => 'Customer',
        750 => 'Trader',
        760 => 'Leadership',
        770 => 'Recruiter',
    ];

    $roleLabels = $roleOptions;

    $avatarUrl = function ($account) {
        $path = $account->profile_image ?? null;

        if ($path && file_exists(public_path($path))) {
            return asset($path);
        }

        if ($path && file_exists(public_path('upload/admin_images/' . $path))) {
            return asset('upload/admin_images/' . $path);
        }

        return asset('upload/default.jpg');
    };
@endphp

<title>{{ $pageTitle }} | HC Gaming Studio</title>

<style>
    .account-edit-page {
        color: #172033;
    }

    .account-edit-hero {
        background: #111827;
        border: 1px solid #243047;
        border-radius: 10px;
        color: #f8fafc;
        padding: 28px;
        position: relative;
        overflow: hidden;
    }

    .account-edit-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        top: -130px;
        width: 300px;
        height: 300px;
        background: rgba(16, 185, 129, 0.14);
        border-radius: 50%;
    }

    .account-edit-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 4px 0 8px;
    }

    .account-edit-hero p,
    .account-edit-hero .eyebrow {
        color: #cbd5e1;
        margin: 0;
    }

    .account-edit-hero .eyebrow {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .account-edit-panel,
    .account-profile-panel {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .account-edit-panel {
        padding: 24px;
    }

    .account-profile-panel {
        padding: 22px;
    }

    .account-edit-muted {
        color: #617188;
        font-size: 13px;
    }

    .account-edit-avatar {
        border: 4px solid #eef2f7;
        border-radius: 50%;
        height: 96px;
        object-fit: cover;
        width: 96px;
    }

    .account-detail-row {
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        gap: 18px;
        padding: 14px 0;
    }

    .account-detail-row:first-of-type {
        border-top: 0;
    }

    .status-pill,
    .role-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 10px;
    }

    .status-active {
        background: #dcfce7;
        color: #166534;
    }

    .status-suspended {
        background: #fee2e2;
        color: #991b1b;
    }

    .role-pill {
        background: #eef2ff;
        color: #3730a3;
    }

    .account-form-label {
        color: #344256;
        font-size: 12px;
        font-weight: 800;
        margin-bottom: 7px;
        text-transform: uppercase;
    }

    .account-edit-panel .form-control,
    .account-edit-panel .form-select {
        border-color: #cbd5e1;
        color: #111827;
        min-height: 42px;
    }

    .account-edit-panel .form-select {
        background-color: #fff;
        display: block;
        padding: .47rem .75rem;
        width: 100%;
    }

    .referral-box {
        background: #f8fafc;
        border: 1px solid #d8e0ea;
        border-radius: 8px;
        padding: 14px;
    }

    @media (max-width: 575px) {
        .account-edit-hero,
        .account-edit-panel,
        .account-profile-panel {
            padding: 18px;
        }

        .account-edit-hero h1 {
            font-size: 24px;
        }

        .account-detail-row {
            display: block;
        }
    }
</style>

<div class="page-content account-edit-page">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">{{ $pageTitle }}</h4>
                    <a href="{{ $backRoute }}" class="btn btn-light">
                        <i class="ri-arrow-left-line mr-1"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <section class="account-edit-hero mb-4">
            <div class="position-relative" style="z-index: 1;">
                <div class="eyebrow">{{ $pageEyebrow }}</div>
                <h1>{{ $pageTitle }}</h1>
                <p>{{ $pageDescription }}</p>
            </div>
        </section>

        @if ($notice)
            <div class="alert alert-info">{{ $notice }}</div>
        @endif

        <div class="row">
            <div class="col-xl-4 mb-4 mb-xl-0">
                <div class="account-profile-panel h-100">
                    <div class="text-center mb-4">
                        <img src="{{ $avatarUrl($account) }}" class="account-edit-avatar" alt="{{ $account->name }}">
                        <h4 class="mt-3 mb-1">{{ $account->name }}</h4>
                        <div class="account-edit-muted">{{ $account->username }}</div>
                    </div>

                    <div class="account-detail-row">
                        <span class="account-edit-muted">Current Status</span>
                        <span class="status-pill {{ (int) $account->status === 1 ? 'status-active' : 'status-suspended' }}">
                            {{ (int) $account->status === 1 ? 'Active' : 'Suspended' }}
                        </span>
                    </div>
                    <div class="account-detail-row">
                        <span class="account-edit-muted">Current Role</span>
                        <span class="role-pill">{{ $roleLabels[(int) $account->role_id] ?? 'Role ' . $account->role_id }}</span>
                    </div>
                    <div class="account-detail-row">
                        <span class="account-edit-muted">Registered</span>
                        <strong>{{ optional($account->created_at)->format('Y-m-d') ?? '-' }}</strong>
                    </div>
                    <div class="account-detail-row">
                        <span class="account-edit-muted">Email</span>
                        <strong class="text-right">{{ $account->email }}</strong>
                    </div>

                    <div class="referral-box mt-3">
                        <div class="account-edit-muted mb-2">Referral Codes</div>
                        <div><strong>General:</strong> <code>{{ $account->referral_code ?: '-' }}</code></div>
                        <div><strong>Customer:</strong> <code>{{ $account->customer_referral_code ?: '-' }}</code></div>
                        <div><strong>Signal:</strong> <code>{{ $account->signal_provider_referral_code ?: '-' }}</code></div>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="account-edit-panel">
                    <h5 class="mb-1">Access Profile</h5>
                    <div class="account-edit-muted mb-4">Changes here affect login identity, account availability, and role-based navigation.</div>

                    <form method="POST" action="{{ $formAction }}">
                        @csrf
                        <input type="hidden" name="id" value="{{ $account->id }}">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="account-form-label" for="account_username">Username</label>
                                <input name="account_username" class="form-control" type="text" id="account_username" value="{{ old('account_username', $account->username) }}">
                                @error('account_username')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="account-form-label" for="account_name">Name</label>
                                <input name="account_name" class="form-control" type="text" id="account_name" value="{{ old('account_name', $account->name) }}">
                                @error('account_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="account-form-label" for="account_email">Email</label>
                                <input name="account_email" class="form-control" type="email" id="account_email" value="{{ old('account_email', $account->email) }}">
                                @error('account_email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="account-form-label" for="account_status">Status</label>
                                <select class="form-select" id="account_status" name="account_status">
                                    <option value="1" {{ (string) old('account_status', $account->status) === '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ (string) old('account_status', $account->status) === '0' ? 'selected' : '' }}>Suspended</option>
                                </select>
                                @error('account_status')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="account-form-label" for="account_role">Role</label>
                                <select class="form-select" id="account_role" name="account_role">
                                    @foreach ($roleOptions as $roleId => $label)
                                        <option value="{{ $roleId }}" {{ (string) old('account_role', $account->role_id) === (string) $roleId ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('account_role')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="d-flex justify-content-end flex-wrap mt-3">
                            <a href="{{ $backRoute }}" class="btn btn-light mr-2 mb-2">Cancel</a>
                            <button type="submit" class="btn btn-primary mb-2">
                                <i class="ri-save-3-line mr-1"></i> {{ $submitLabel }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
