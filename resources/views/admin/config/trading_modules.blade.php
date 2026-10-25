@extends('admin.admin_master')
@section('admin')
<link rel="stylesheet" href="{{ asset('backend/assets/css/trading-module-management.css') }}?v={{ filemtime(public_path('backend/assets/css/trading-module-management.css')) }}">
<div class="page-content"><div class="container-fluid">
    <main class="thm" id="trading-module-management" data-index-url="{{ route('admin.trading-modules.index') }}" data-update-url="{{ route('admin.trading-modules.update', ['key' => '__module__']) }}">
        <script type="application/json" id="thm-initial-data">@json($payload)</script>
        <input type="hidden" id="thm-csrf" value="{{ csrf_token() }}">
        <div class="thm-heading">
            <div>
                <p class="thm-eyebrow">TradingHub settings</p>
                <h1>TradingHub Module Management</h1>
                <p class="thm-lead">Manage TradingHub modules by domain. Control which features are available to your users. Changes take effect on pages, navigation, and APIs. Existing data is retained.</p>
            </div>
            <a class="thm-feature-link" href="{{ route('admin.features.index') }}"><i class="ri-settings-3-line" aria-hidden="true"></i> Manage feature settings</a>
        </div>

        <section class="thm-toolbar" aria-label="Module overview and filters">
            <div class="thm-summary">
                <span class="thm-summary-icon" id="thm-summary-icon" aria-hidden="true"><i class="ri-check-line"></i></span>
                <div><strong id="thm-summary-title">Loading modules…</strong><span id="thm-summary-description">Retrieving current TradingHub settings.</span></div>
            </div>
            <div class="thm-tools">
                <div class="thm-search-wrap">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <input id="thm-search" type="search" autocomplete="off" placeholder="Search modules..." aria-label="Search modules">
                    <button type="button" id="thm-clear-search" class="thm-clear-search" aria-label="Clear search" hidden><i class="ri-close-line" aria-hidden="true"></i></button>
                </div>
                <div class="thm-filters" role="group" aria-label="Filter modules by status">
                    <button type="button" class="is-active" data-filter="all" aria-pressed="true">All</button>
                    <button type="button" data-filter="active" aria-pressed="false">Active</button>
                    <button type="button" data-filter="inactive" aria-pressed="false">Inactive</button>
                </div>
            </div>
        </section>

        <div class="thm-workspace" id="thm-workspace">
            <div class="thm-domains" id="thm-domains" aria-live="polite"></div>
            <aside class="thm-detail" id="thm-detail" aria-label="Module details" hidden></aside>
        </div>
        <div class="thm-drawer-backdrop" id="thm-drawer-backdrop" hidden></div>
        <div class="thm-modal-backdrop" id="thm-modal-backdrop" hidden>
            <div class="thm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="thm-dialog-title" aria-describedby="thm-dialog-description">
                <div class="thm-dialog-heading">
                    <span class="thm-warning-icon" aria-hidden="true"><i class="ri-error-warning-fill"></i></span>
                    <h2 id="thm-dialog-title"></h2>
                    <button type="button" class="thm-icon-button" id="thm-dialog-close" aria-label="Close confirmation"><i class="ri-close-line" aria-hidden="true"></i></button>
                </div>
                <p id="thm-dialog-description">Users will no longer be able to access this module in TradingHub. It will be hidden from navigation. All existing data and settings will be retained.</p>
                <div class="thm-dialog-actions">
                    <button type="button" class="thm-button thm-button-secondary" id="thm-dialog-cancel">Cancel</button>
                    <button type="button" class="thm-button thm-button-danger" id="thm-dialog-confirm">Disable module</button>
                </div>
            </div>
        </div>
        <p class="thm-screen-reader" id="thm-announcement" role="status" aria-live="polite"></p>
    </main>
</div></div>
<script defer src="{{ asset('backend/assets/js/trading-module-filters.js') }}"></script>
<script defer src="{{ asset('backend/assets/js/trading-module-management.js') }}"></script>
@endsection
