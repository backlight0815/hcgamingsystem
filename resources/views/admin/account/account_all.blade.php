@extends('admin.admin_master')
@section('admin')
@include('admin.account.partials.list', [
    'accounts' => $user,
    'pageTitle' => 'All Account Management',
    'pageEyebrow' => 'Account Management',
    'pageDescription' => 'Central account directory for reviewing every registered member, their role, status, upline, and referral identity.',
    'tableTitle' => 'All Registered Accounts',
    'editRouteName' => 'edit.account',
    'inviteTitle' => 'Invite New Member',
    'inviteButtonLabel' => 'Invite Member',
    'inviteReferralCode' => Auth::user()->referral_code ?? '',
    'showCommission' => true,
    'showRoleColumn' => true,
    'stats' => [
        ['label' => 'Total Accounts', 'value' => $users ?? 0],
        ['label' => 'Active Accounts', 'value' => $activeCount ?? 0],
        ['label' => 'Suspended Accounts', 'value' => $suspendedCount ?? 0],
    ],
])
@endsection
