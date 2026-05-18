@php
    $accounts = collect($accounts ?? []);
    $stats = $stats ?? [];
    $pageTitle = $pageTitle ?? 'Account Management';
    $pageEyebrow = $pageEyebrow ?? 'Account Management';
    $pageDescription = $pageDescription ?? 'Monitor account status, role placement, uplines, and referral access from one operational view.';
    $tableTitle = $tableTitle ?? 'Account Directory';
    $editRouteName = $editRouteName ?? 'edit.account';
    $inviteEnabled = $inviteEnabled ?? true;
    $inviteTitle = $inviteTitle ?? 'Invite New Member';
    $inviteButtonLabel = $inviteButtonLabel ?? 'Invite Member';
    $inviteReferralCode = $inviteReferralCode ?? (Auth::user()->referral_code ?? '');
    $showCommission = $showCommission ?? false;
    $showTradingPosition = $showTradingPosition ?? false;
    $showRoleColumn = $showRoleColumn ?? true;
    $emptyMessage = $emptyMessage ?? 'No account records found.';

    $roleLabels = [
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

    $roleClass = function ($roleId) {
        return match ((int) $roleId) {
            1, 2 => 'role-admin',
            201, 202, 502 => 'role-signal',
            350 => 'role-agent',
            501 => 'role-analyst',
            700 => 'role-customer',
            760 => 'role-leader',
            770 => 'role-recruiter',
            750 => 'role-trader',
            default => 'role-default',
        };
    };

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

    $initials = function ($name) {
        $parts = preg_split('/\s+/', trim((string) $name));
        $letters = '';

        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= strtoupper(substr($part, 0, 1));
            }

            if (strlen($letters) >= 2) {
                break;
            }
        }

        return $letters ?: 'HC';
    };

    $commissionTotal = function ($account) {
        if (! method_exists($account, 'commissions')) {
            return 0;
        }

        return $account->relationLoaded('commissions')
            ? $account->commissions->sum('commission_amount')
            : $account->commissions()->sum('commission_amount');
    };

    $statColumn = match (count($stats)) {
        1 => '12',
        2 => '6',
        3 => '4',
        4 => '3',
        default => '2',
    };
@endphp

<title>{{ $pageTitle }} | HC Gaming Studio</title>

<style>
    .account-page {
        color: #162033;
    }

    .account-hero {
        background: #111827;
        border: 1px solid #243047;
        border-radius: 10px;
        color: #f8fafc;
        padding: 28px;
        position: relative;
        overflow: hidden;
    }

    .account-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        top: -130px;
        width: 300px;
        height: 300px;
        background: rgba(56, 189, 248, 0.15);
        border-radius: 50%;
    }

    .account-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 4px 0 8px;
    }

    .account-hero p,
    .account-hero .eyebrow {
        color: #cbd5e1;
        margin: 0;
    }

    .account-hero .eyebrow {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .account-panel,
    .account-stat {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .account-stat {
        height: 100%;
        padding: 18px;
    }

    .account-stat span,
    .account-muted {
        color: #617188;
        font-size: 13px;
    }

    .account-stat strong {
        color: #101827;
        display: block;
        font-size: 25px;
        line-height: 1.15;
        margin-top: 6px;
    }

    .account-panel {
        padding: 22px;
    }

    .account-table {
        border-collapse: separate;
        border-spacing: 0;
        color: #172033;
        width: 100%;
    }

    .account-table thead th {
        background: #0f172a;
        border-color: #27344d;
        color: #fff;
        font-size: 12px;
        letter-spacing: 0;
        padding: 14px 12px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .account-table tbody td {
        border-color: #e2e8f0;
        padding: 14px 12px;
        vertical-align: middle;
    }

    .account-profile {
        align-items: center;
        display: flex;
        gap: 12px;
        min-width: 240px;
    }

    .account-avatar {
        align-items: center;
        background: #dbeafe;
        border: 1px solid #bfdbfe;
        border-radius: 50%;
        color: #1d4ed8;
        display: inline-flex;
        flex: 0 0 auto;
        font-size: 13px;
        font-weight: 800;
        height: 42px;
        justify-content: center;
        object-fit: cover;
        width: 42px;
    }

    .role-pill,
    .status-pill,
    .action-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .role-admin { background: #e0f2fe; color: #075985; }
    .role-signal { background: #ede9fe; color: #5b21b6; }
    .role-agent { background: #ecfdf5; color: #047857; }
    .role-analyst { background: #fff7ed; color: #9a3412; }
    .role-customer { background: #f1f5f9; color: #334155; }
    .role-trader { background: #dbeafe; color: #1d4ed8; }
    .role-leader { background: #dcfce7; color: #166534; }
    .role-recruiter { background: #fef3c7; color: #92400e; }
    .role-default { background: #f1f5f9; color: #334155; }

    .status-active {
        background: #dcfce7;
        color: #166534;
    }

    .status-suspended {
        background: #fee2e2;
        color: #991b1b;
    }

    .action-pill {
        align-items: center;
        background: #eff6ff;
        color: #1d4ed8;
        gap: 6px;
    }

    .account-copy-box {
        background: #f8fafc;
        border: 1px solid #d8e0ea;
        border-radius: 8px;
        padding: 14px;
    }

    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        color: #111827;
        margin-left: 8px;
        min-height: 34px;
        padding: 6px 10px;
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_length label {
        color: #475569;
    }

    .empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #617188;
        padding: 34px;
        text-align: center;
    }

    @media (max-width: 575px) {
        .account-hero,
        .account-panel {
            padding: 18px;
        }

        .account-hero h1 {
            font-size: 24px;
        }
    }
</style>

<div class="page-content account-page">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">{{ $pageTitle }}</h4>
                    @if (!empty($breadcrumbData))
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                @foreach ($breadcrumbData as $breadcrumb)
                                    <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                                        @if ($loop->last)
                                            {{ $breadcrumb['label'] }}
                                        @else
                                            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </nav>
                    @endif
                </div>
            </div>
        </div>

        <section class="account-hero mb-4">
            <div class="row align-items-center position-relative" style="z-index: 1;">
                <div class="col-xl-8">
                    <div class="eyebrow">{{ $pageEyebrow }}</div>
                    <h1>{{ $pageTitle }}</h1>
                    <p>{{ $pageDescription }}</p>
                </div>
                <div class="col-xl-4 mt-4 mt-xl-0 text-xl-right">
                    @if ($inviteEnabled)
                        <button type="button" class="btn btn-info" id="openInviteModal">
                            <i class="ri-user-add-line mr-1"></i> {{ $inviteButtonLabel }}
                        </button>
                    @endif
                </div>
            </div>
        </section>

        @if (!empty($stats))
            <div class="row">
                @foreach ($stats as $stat)
                    <div class="col-md-6 col-xl-{{ $statColumn }} mb-4">
                        <div class="account-stat">
                            <span>{{ $stat['label'] }}</span>
                            <strong>{{ number_format((float) ($stat['value'] ?? 0)) }}</strong>
                            @if (!empty($stat['hint']))
                                <div class="account-muted mt-1">{{ $stat['hint'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="account-panel">
            <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
                <div>
                    <h5 class="mb-1">{{ $tableTitle }}</h5>
                    <div class="account-muted">Use search, sorting, and status badges to review operational access quickly.</div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="accountManagementTable" class="account-table table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Member</th>
                            <th>Email</th>
                            @if ($showRoleColumn)
                                <th>Role</th>
                            @endif
                            @if ($showTradingPosition)
                                <th>Trading Position</th>
                            @endif
                            <th>Upline</th>
                            @if ($showCommission)
                                <th>Commission</th>
                            @endif
                            <th>Referral Code</th>
                            <th>Registered</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $index => $item)
                            @php
                                $name = $item->name ?: $item->username;
                                $roleLabel = $roleLabels[(int) $item->role_id] ?? 'Role ' . $item->role_id;
                                $upline = $item->upline->username ?? 'HC Gaming';
                                $statusActive = (int) $item->status === 1;
                                $referralCode = $item->referral_code ?: ($item->customer_referral_code ?: ($item->signal_provider_referral_code ?: '-'));
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <div class="account-profile">
                                        <img src="{{ $avatarUrl($item) }}" class="account-avatar" alt="{{ $name }}" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';">
                                        <span class="account-avatar" style="display: none;">{{ $initials($name) }}</span>
                                        <div>
                                            <strong>{{ $name }}</strong>
                                            <div class="account-muted">{{ $item->username }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $item->email }}</td>
                                @if ($showRoleColumn)
                                    <td><span class="role-pill {{ $roleClass($item->role_id) }}">{{ $roleLabel }}</span></td>
                                @endif
                                @if ($showTradingPosition)
                                    <td><span class="role-pill {{ $roleClass($item->role_id) }}">{{ $roleLabel }}</span></td>
                                @endif
                                <td>{{ $upline }}</td>
                                @if ($showCommission)
                                    <td>RM {{ number_format((float) $commissionTotal($item), 2) }}</td>
                                @endif
                                <td><code>{{ $referralCode }}</code></td>
                                <td data-order="{{ optional($item->created_at)->timestamp ?? 0 }}">{{ optional($item->created_at)->format('Y-m-d') ?? '-' }}</td>
                                <td>
                                    <span class="status-pill {{ $statusActive ? 'status-active' : 'status-suspended' }}">
                                        {{ $statusActive ? 'Active' : 'Suspended' }}
                                    </span>
                                </td>
                                <td>
                                    @auth
                                        @if (Auth::id() !== $item->id)
                                            <a href="{{ route($editRouteName, $item->id) }}" class="action-pill">
                                                <i class="ri-edit-line"></i> Edit
                                            </a>
                                        @else
                                            <span class="account-muted">Current user</span>
                                        @endif
                                    @endauth
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11">
                                    <div class="empty-state">{{ $emptyMessage }}</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if ($inviteEnabled)
    <div class="modal fade" id="inviteModal" tabindex="-1" role="dialog" aria-labelledby="inviteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="inviteModalLabel">{{ $inviteTitle }}</h5>
                    <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="account-copy-box mb-3">
                        <div class="account-muted mb-1">Referral code</div>
                        <strong>{{ $inviteReferralCode ?: '-' }}</strong>
                    </div>
                    <label class="account-muted" for="registrationLink">Registration link</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="registrationLink" readonly>
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" id="copyLinkBtn">
                                <i class="ri-file-copy-line mr-1"></i> Copy
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif

<script>
    $(function () {
        const table = $('#accountManagementTable');

        if (table.find('tbody tr').length && table.find('tbody .empty-state').length === 0) {
            table.DataTable({
                order: [[0, 'asc']],
                pageLength: 25,
                responsive: false,
                language: {
                    search: 'Search accounts:',
                    lengthMenu: 'Show _MENU_ accounts',
                    emptyTable: 'No accounts found'
                }
            });
        }

        $('#openInviteModal').on('click', function () {
            const referralCode = @json($inviteReferralCode);
            const registerUrl = @json(route('register'));
            const separator = registerUrl.includes('?') ? '&' : '?';
            $('#registrationLink').val(registerUrl + separator + 'referral_code=' + encodeURIComponent(referralCode || ''));
            $('#inviteModal').modal('show');
        });

        $('#copyLinkBtn').on('click', function () {
            const input = document.getElementById('registrationLink');
            input.select();

            const success = function () {
                if (window.toastr) {
                    toastr.success('Invitation link copied.');
                }
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(success);
            } else {
                document.execCommand('copy');
                success();
            }
        });
    });
</script>
