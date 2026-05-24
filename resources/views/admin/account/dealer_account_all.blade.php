@extends('admin.admin_master')
@section('admin')
@php
    $accounts = collect($agents ?? $provider ?? []);
    $isProviderPage = isset($provider);
@endphp

@include('admin.account.partials.list', [
    'accounts' => $accounts,
    'pageTitle' => $isProviderPage ? 'Signal Provider Management' : 'Agent Management',
    'pageEyebrow' => $isProviderPage ? 'Provider Accounts' : 'Dealership E-Commerce Accounts',
    'pageDescription' => $isProviderPage
        ? 'Review signal-provider management accounts, access status, referral identity, and operational ownership.'
        : 'Monitor dealership agents, referral ownership, commission activity, and access availability.',
    'tableTitle' => $isProviderPage ? 'Signal Provider Directory' : 'Agent Directory',
    'editRouteName' => $isProviderPage ? 'edit.signal_provider.account' : 'edit.agent.account',
    'inviteTitle' => $isProviderPage ? 'Invite New Provider' : 'Invite New Dealer',
    'inviteButtonLabel' => $isProviderPage ? 'Invite Provider' : 'Invite Dealer',
    'inviteReferralCode' => $isProviderPage ? (Auth::user()->signal_provider_referral_code ?? Auth::user()->referral_code ?? '') : (Auth::user()->referral_code ?? ''),
    'showCommission' => ! $isProviderPage,
    'showRoleColumn' => true,
    'stats' => $isProviderPage ? [
        ['label' => 'Providers', 'value' => $signalProviderCount ?? $accounts->count()],
        ['label' => 'Active', 'value' => $activeCount ?? 0],
        ['label' => 'Suspended', 'value' => $suspendedCount ?? 0],
    ] : [
        ['label' => 'Agents', 'value' => $agentCount ?? 0],
        ['label' => 'Active', 'value' => $activeCount ?? 0],
        ['label' => 'Suspended', 'value' => $suspendedCount ?? 0],
    ],
])
@endsection
