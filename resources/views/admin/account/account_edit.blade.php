@extends('admin.admin_master')
@section('admin')
@include('admin.account.partials.edit', [
    'account' => $account_details,
    'pageTitle' => 'Edit Account',
    'pageEyebrow' => 'Account Management',
    'pageDescription' => 'Update the member profile, account status, and role assignment from the central account directory.',
    'formAction' => route('update.account'),
    'backRoute' => route('all.account'),
    'submitLabel' => 'Update Account',
])
@endsection
