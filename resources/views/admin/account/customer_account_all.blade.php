@extends('admin.admin_master')
@section('admin')
@include('admin.account.partials.list', [
    'accounts' => $customers,
    'pageTitle' => 'Customer Management',
    'pageEyebrow' => 'Customer Accounts',
    'pageDescription' => 'Review customer account status, upline ownership, referral identity, and registration history.',
    'tableTitle' => 'Customer Directory',
    'editRouteName' => 'edit.customer.account',
    'inviteTitle' => 'Invite New Customer',
    'inviteButtonLabel' => 'Invite Customer',
    'inviteReferralCode' => Auth::user()->customer_referral_code ?? Auth::user()->referral_code ?? '',
    'showCommission' => false,
    'showRoleColumn' => true,
    'stats' => [
        ['label' => 'Customers', 'value' => $customerCount ?? 0],
        ['label' => 'Active', 'value' => $activeCount ?? 0],
        ['label' => 'Suspended', 'value' => $suspendedCount ?? 0],
    ],
])
@endsection
