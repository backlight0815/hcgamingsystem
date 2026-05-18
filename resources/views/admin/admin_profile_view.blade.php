@extends('admin.admin_master')
@section('admin')

<title>My Profile | HC Gaming Studio</title>

@php
    $roleLabels = [
        1 => 'Super Admin',
        2 => 'Admin',
        201 => 'Junior Signal Provider',
        202 => 'Senior Signal Provider',
        350 => 'Agent',
        501 => 'Market Analyst',
        502 => 'Signal Provider Management',
        700 => 'Customer',
        750 => 'Trader',
        760 => 'Leadership',
        770 => 'Recruiter',
    ];
    $displayRoleLabel = $accountLevelLabel ?? ($roleLabels[(int) $adminData->role_id] ?? 'Role ' . $adminData->role_id);

    $profileImage = $adminData->profile_image
        ? url('upload/admin_images/' . $adminData->profile_image)
        : url('upload/default.jpg');

    $discordConnectedAt = $adminData->discord_connected_at
        ? \Carbon\Carbon::parse($adminData->discord_connected_at)->format('d M Y H:i')
        : null;
@endphp

<style>
    .profile-shell {
        margin: 0 auto;
        max-width: 1120px;
    }

    .profile-hero {
        background: #111827;
        border-radius: 12px;
        color: #fff;
        display: grid;
        gap: 22px;
        grid-template-columns: auto minmax(0, 1fr) auto;
        margin-bottom: 18px;
        padding: 28px;
    }

    .profile-avatar {
        border: 4px solid rgba(255, 255, 255, .18);
        border-radius: 50%;
        height: 108px;
        object-fit: cover;
        width: 108px;
    }

    .profile-kicker {
        color: #5eead4;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .profile-hero h3 {
        color: #fff;
        font-size: 30px;
        font-weight: 900;
        margin: 7px 0 5px;
    }

    .profile-hero p {
        color: #aab7ca;
        margin: 0;
    }

    .profile-panel {
        background: #fff;
        border: 1px solid #d9e3ef;
        border-radius: 12px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
        margin-bottom: 18px;
        padding: 22px;
    }

    .profile-grid {
        display: grid;
        gap: 14px;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .profile-item {
        background: #f8fafc;
        border: 1px solid #dbe4ef;
        border-radius: 10px;
        padding: 15px;
    }

    .profile-item span {
        color: #64748b;
        display: block;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .05em;
        margin-bottom: 7px;
        text-transform: uppercase;
    }

    .profile-item strong {
        color: #0f172a;
        display: block;
        font-size: 17px;
        font-weight: 900;
        word-break: break-word;
    }

    .profile-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    .status-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 11px;
        font-weight: 900;
        line-height: 1;
        padding: 8px 11px;
        text-transform: uppercase;
    }

    .status-pill.connected {
        background: #dcfce7;
        color: #15803d;
    }

    .status-pill.pending {
        background: #fef3c7;
        color: #b45309;
    }

    .status-pill.qualified {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .profile-note {
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
        margin-top: 8px;
    }

    @@media (max-width: 768px) {
        .profile-hero,
        .profile-grid {
            grid-template-columns: 1fr;
        }

        .profile-actions {
            justify-content: flex-start;
        }
    }
</style>

<div class="page-content">
    <div class="container-fluid">
        <div class="profile-shell">
            <div class="profile-hero">
                <img class="profile-avatar" src="{{ $profileImage }}" onerror="this.src='{{ url('upload/default.jpg') }}'" alt="Personal Profile">

                <div>
                    <div class="profile-kicker">My Profile</div>
                    <h3>{{ $adminData->name }}</h3>
                    <p>{{ $displayRoleLabel }} | {{ $adminData->email }}</p>
                </div>

                <div class="profile-actions">
                    <a href="{{ route('edit.profile') }}" class="btn btn-info btn-rounded waves-effect waves-light">
                        Edit Profile
                    </a>
                </div>
            </div>

            <div class="profile-panel">
                <div class="profile-grid">
                    <div class="profile-item">
                        <span>Name</span>
                        <strong>{{ $adminData->name }}</strong>
                    </div>
                    <div class="profile-item">
                        <span>Username</span>
                        <strong>{{ $adminData->username }}</strong>
                    </div>
                    <div class="profile-item">
                        <span>Email</span>
                        <strong>{{ $adminData->email }}</strong>
                    </div>
                    <div class="profile-item">
                        <span>Account Level</span>
                        <strong>{{ $displayRoleLabel }}</strong>
                    </div>
                    <div class="profile-item">
                        <span>Strategy Certification Status</span>
                        <strong>
                            <span class="status-pill {{ $certificateReadiness['tone'] ?? 'pending' }}">
                                {{ $certificateReadiness['label'] ?? 'Not Evaluated Yet' }}
                            </span>
                        </strong>
                        <div class="profile-note">{{ $certificateReadiness['note'] ?? 'Complete evaluation to unlock certificate review.' }}</div>
                    </div>

                    @if($adminData->role_id == 1 || $adminData->role_id == 2)
                        <div class="profile-item">
                            <span>Admin Referral Code</span>
                            <strong>{{ $adminData->referral_code ?: '-' }}</strong>
                        </div>
                        <div class="profile-item">
                            <span>Customer Referral Code</span>
                            <strong>{{ $adminData->customer_referral_code ?: '-' }}</strong>
                        </div>
                    @elseif($adminData->role_id == 350 && feature_enabled('referral_dealer'))
                        <div class="profile-item">
                            <span>Agent Referral Code</span>
                            <strong>{{ $adminData->referral_code ?: '-' }}</strong>
                        </div>
                        <div class="profile-item">
                            <span>Customer Referral Code</span>
                            <strong>{{ $adminData->customer_referral_code ?: '-' }}</strong>
                        </div>
                    @elseif($adminData->role_id == 202)
                        <div class="profile-item">
                            <span>Signal Provider Referral Code</span>
                            <strong>{{ $adminData->signal_provider_referral_code ?: '-' }}</strong>
                        </div>
                    @endif

                    @if($adminData->role_id == 350)
                        <div class="profile-item">
                            <span>Commission Earned</span>
                            <strong>{{ number_format((float) $commissionAmount, 2) }}</strong>
                        </div>
                    @endif

                    @if($adminData->role_id == 750)
                        <div class="profile-item">
                            <span>Prop Firm Phase</span>
                            <strong>{{ $propFirmPhaseText }}</strong>
                        </div>
                    @endif

                    <div class="profile-item">
                        <span>Invited By</span>
                        <strong>{{ $adminData->upline?->username ?? 'HC Gaming Sdn Bhd' }}</strong>
                    </div>
                    <div class="profile-item">
                        <span>Discord Status</span>
                        @if($discordConnectedAt)
                            <strong><span class="status-pill connected">Connected</span></strong>
                            <div class="text-muted mt-2">Connected at: {{ $discordConnectedAt }}</div>
                        @else
                            <strong><span class="status-pill pending">Not Connected</span></strong>
                        @endif
                    </div>
                </div>
            </div>

            <div class="profile-panel">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-1">Discord Connection</h5>
                        <p class="text-muted mb-0">Connect your Discord account for community and trading signal workflows.</p>
                    </div>
                    <a href="{{ route('discord.connect') }}" class="btn btn-dark">
                        Connect to Discord
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
