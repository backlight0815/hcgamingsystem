@extends('admin.admin_master')
@section('admin')
@include('admin.account.partials.list', [
    'accounts' => $admins,
    'pageTitle' => 'Sub Admin Management',
    'pageEyebrow' => 'Administration Accounts',
    'pageDescription' => 'Manage sub-admin identities, access status, and accountability for operational administration.',
    'tableTitle' => 'Sub Admin Directory',
    'editRouteName' => 'edit.admin.account',
    'inviteTitle' => 'Invite New Sub Admin',
    'inviteButtonLabel' => 'Invite Sub Admin',
    'inviteReferralCode' => Auth::user()->referral_code ?? '',
    'showCommission' => false,
    'showRoleColumn' => true,
    'stats' => [
        ['label' => 'Sub Admins', 'value' => $adminCount ?? 0],
        ['label' => 'Active', 'value' => $activeCount ?? 0],
        ['label' => 'Suspended', 'value' => $suspendedCount ?? 0],
    ],
])
@endsection
