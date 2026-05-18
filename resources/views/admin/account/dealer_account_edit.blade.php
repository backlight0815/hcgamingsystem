@extends('admin.admin_master')
@section('admin')
@php
    $isProviderAccount = in_array((int) $agent_details->role_id, [201, 202, 502], true);
@endphp
@include('admin.account.partials.edit', [
    'account' => $agent_details,
    'pageTitle' => $isProviderAccount ? 'Edit Signal Provider' : 'Edit Agent',
    'pageEyebrow' => $isProviderAccount ? 'Provider Accounts' : 'Dealership E-Commerce Accounts',
    'pageDescription' => $isProviderAccount
        ? 'Update provider access, contact identity, and signal-related role assignment.'
        : 'Update dealership agent access, contact identity, and account role for operational use.',
    'formAction' => $isProviderAccount ? route('update.signal_provider') : route('update.agent'),
    'backRoute' => $isProviderAccount ? route('all.signal_provider') : route('all.agent.account'),
    'submitLabel' => $isProviderAccount ? 'Update Signal Provider' : 'Update Agent',
])
@endsection
