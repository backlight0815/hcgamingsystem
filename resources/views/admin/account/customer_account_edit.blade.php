@extends('admin.admin_master')
@section('admin')
@include('admin.account.partials.edit', [
    'account' => $customer_details,
    'pageTitle' => 'Edit Customer',
    'pageEyebrow' => 'Customer Accounts',
    'pageDescription' => 'Maintain customer login identity, account status, and role placement from a focused edit workspace.',
    'formAction' => route('update.customer'),
    'backRoute' => route('all.customer.account'),
    'submitLabel' => 'Update Customer',
])
@endsection
