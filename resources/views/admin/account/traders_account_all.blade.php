@extends('admin.admin_master')
@section('admin')
@include('admin.account.partials.list', [
    'accounts' => $traders,
    'pageTitle' => 'Traders Management',
    'pageEyebrow' => 'Trading Industry Accounts',
    'pageDescription' => 'Monitor traders, recruiters, and leadership accounts with their trading position, upline, status, and referral identity.',
    'tableTitle' => 'Trading Member Directory',
    'editRouteName' => 'edit.traders.account',
    'inviteTitle' => 'Invite New Trader',
    'inviteButtonLabel' => 'Invite Trader',
    'inviteReferralCode' => Auth::user()->referral_code ?? Auth::user()->customer_referral_code ?? '',
    'showCommission' => false,
    'showRoleColumn' => false,
    'showTradingPosition' => true,
    'stats' => [
        ['label' => 'Trading Members', 'value' => $tradersCount ?? 0],
        ['label' => 'Traders', 'value' => $regularTraderCount ?? 0],
        ['label' => 'Recruiters', 'value' => $recruiterCount ?? 0],
        ['label' => 'Leadership', 'value' => $leaderCount ?? 0],
        ['label' => 'Active', 'value' => $activeCount ?? 0],
        ['label' => 'Suspended', 'value' => $suspendedCount ?? 0],
    ],
])
@endsection
