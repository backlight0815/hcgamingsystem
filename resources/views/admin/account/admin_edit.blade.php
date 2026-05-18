@extends('admin.admin_master')
@section('admin')
@include('admin.account.partials.edit', [
    'account' => $admin_details,
    'pageTitle' => 'Edit Sub Admin',
    'pageEyebrow' => 'Administration Accounts',
    'pageDescription' => 'Adjust sub-admin identity and access status while keeping role responsibility visible.',
    'formAction' => route('update.admin'),
    'backRoute' => route('all.admin.account'),
    'submitLabel' => 'Update Sub Admin',
])
@endsection
