@extends('admin.admin_master')
@section('admin')
@include('admin.account.partials.edit', [
    'account' => $traders_details,
    'pageTitle' => 'Edit Trading Member',
    'pageEyebrow' => 'Trading Industry Accounts',
    'pageDescription' => 'Review trader account status and trading-position role assignment before saving operational access changes.',
    'formAction' => route('update.traders'),
    'backRoute' => route('all.traders.account'),
    'submitLabel' => 'Update Trading Member',
    'notice' => 'Leadership and Recruiter positions should normally be upgraded through Trading Position Reviews after the member passes administration evaluation.',
])
@endsection
