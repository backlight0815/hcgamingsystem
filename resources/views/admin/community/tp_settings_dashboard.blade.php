@extends('admin.admin_master')
@section('admin')
@php
    $totalCommunities = $communities->count();
    $activeCommunities = $communities->where('status', 1)->count();
    $inactiveCommunities = $communities->where('status', 0)->count();
    $totalEnabledTp = $communities->sum(function ($community) {
        return $community->tpSettings->where('enabled', 1)->count();
    });
    $totalPossibleTp = max(1, $totalCommunities * 10);
    $coveragePercent = round(($totalEnabledTp / $totalPossibleTp) * 100, 1);
    $fullyEnabled = $communities->filter(fn ($community) => $community->tpSettings->where('enabled', 1)->count() === 10)->count();
    $emptyConfigured = $communities->filter(fn ($community) => $community->tpSettings->where('enabled', 1)->count() === 0)->count();

    $categoryClass = fn ($category) => match ($category) {
        'public' => 'category-public',
        'executive' => 'category-executive',
        'test' => 'category-test',
        default => 'category-default',
    };

    $categoryLabel = fn ($category) => match ($category) {
        'public' => 'Public',
        'executive' => 'Executive',
        'test' => 'Test',
        default => 'General',
    };
@endphp

<title>TP Notification Dashboard | HC Gaming Studio</title>

<style>
    .tp-page {
        color: #172033;
    }

    .tp-hero {
        background: #111827;
        border: 1px solid #243047;
        border-radius: 10px;
        color: #f8fafc;
        padding: 28px;
        position: relative;
        overflow: hidden;
    }

    .tp-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        top: -130px;
        width: 300px;
        height: 300px;
        background: rgba(45, 212, 191, 0.15);
        border-radius: 50%;
    }

    .tp-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 4px 0 8px;
    }

    .tp-hero p,
    .tp-hero .eyebrow {
        color: #cbd5e1;
        margin: 0;
    }

    .tp-hero .eyebrow {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .tp-stat,
    .tp-panel,
    .tp-toolbar {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .tp-stat {
        height: 100%;
        padding: 18px;
    }

    .tp-stat span,
    .tp-muted {
        color: #617188;
        font-size: 13px;
    }

    .tp-stat strong {
        color: #101827;
        display: block;
        font-size: 25px;
        line-height: 1.15;
        margin-top: 6px;
    }

    .tp-progress {
        background: #e5e7eb;
        border-radius: 999px;
        height: 8px;
        margin-top: 12px;
        overflow: hidden;
    }

    .tp-progress span {
        background: #0f766e;
        display: block;
        height: 100%;
    }

    .tp-toolbar,
    .tp-panel {
        padding: 22px;
    }

    .tp-toolbar .form-control {
        border-color: #cbd5e1;
        color: #111827;
        min-height: 42px;
    }

    .tp-table {
        border-collapse: separate;
        border-spacing: 0;
        color: #172033;
        width: 100%;
    }

    .tp-table thead th {
        background: #0f172a;
        border-color: #27344d;
        color: #fff;
        font-size: 12px;
        letter-spacing: 0;
        padding: 14px 12px;
        position: sticky;
        top: 0;
        text-transform: uppercase;
        white-space: nowrap;
        z-index: 2;
    }

    .tp-table tbody td {
        border-color: #e2e8f0;
        padding: 14px 12px;
        vertical-align: middle;
    }

    .community-cell {
        min-width: 250px;
    }

    .community-cell strong {
        color: #111827;
        display: block;
        font-size: 15px;
    }

    .category-pill,
    .status-pill,
    .coverage-pill {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .category-public {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .category-executive {
        background: #ede9fe;
        color: #5b21b6;
    }

    .category-test {
        background: #fef3c7;
        color: #92400e;
    }

    .category-default {
        background: #f1f5f9;
        color: #334155;
    }

    .status-active {
        background: #dcfce7;
        color: #166534;
    }

    .status-inactive {
        background: #fee2e2;
        color: #991b1b;
    }

    .coverage-pill {
        background: #ecfeff;
        color: #0e7490;
    }

    .tp-switch {
        display: inline-flex;
        justify-content: center;
        min-width: 42px;
    }

    .tp-switch input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .tp-slider {
        background: #cbd5e1;
        border-radius: 999px;
        cursor: pointer;
        display: inline-block;
        height: 22px;
        position: relative;
        transition: background .15s ease;
        width: 42px;
    }

    .tp-slider::after {
        background: #fff;
        border-radius: 50%;
        box-shadow: 0 1px 4px rgba(15, 23, 42, .25);
        content: "";
        height: 18px;
        left: 2px;
        position: absolute;
        top: 2px;
        transition: transform .15s ease;
        width: 18px;
    }

    .tp-switch input:checked + .tp-slider {
        background: #0f766e;
    }

    .tp-switch input:checked + .tp-slider::after {
        transform: translateX(20px);
    }

    .row-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-width: 230px;
    }

    .empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #617188;
        padding: 34px;
        text-align: center;
    }

    @media (max-width: 575px) {
        .tp-hero,
        .tp-toolbar,
        .tp-panel {
            padding: 18px;
        }

        .tp-hero h1 {
            font-size: 24px;
        }
    }
</style>

<div class="page-content tp-page">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">TP Notification Dashboard</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Gaming</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('communities.index') }}">Community Management</a></li>
                            <li class="breadcrumb-item active">TP Notifications</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <section class="tp-hero mb-4">
            <div class="row align-items-center position-relative" style="z-index: 1;">
                <div class="col-xl-8">
                    <div class="eyebrow">Take Profit Publishing Controls</div>
                    <h1>TP Notification Dashboard</h1>
                    <p>Configure which TP levels are announced for each Discord community. Every row is included when saving, so switch changes persist without extra row selection.</p>
                </div>
                <div class="col-xl-4 mt-4 mt-xl-0 text-xl-right">
                    <button type="submit" form="tpSettingsForm" class="btn btn-info mb-2">
                        <i class="ri-save-3-line mr-1"></i> Save Settings
                    </button>
                    <a href="{{ route('communities.index') }}" class="btn btn-light mb-2">
                        <i class="ri-arrow-left-line mr-1"></i> Communities
                    </a>
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="tp-stat">
                    <span>Communities</span>
                    <strong>{{ number_format($totalCommunities) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="tp-stat">
                    <span>Active / Inactive</span>
                    <strong>{{ number_format($activeCommunities) }} / {{ number_format($inactiveCommunities) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="tp-stat">
                    <span>Enabled TP Slots</span>
                    <strong>{{ number_format($totalEnabledTp) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="tp-stat">
                    <span>Coverage</span>
                    <strong>{{ $coveragePercent }}%</strong>
                    <div class="tp-progress"><span style="width: {{ min(100, max(0, $coveragePercent)) }}%;"></span></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="tp-stat">
                    <span>Fully Enabled</span>
                    <strong>{{ number_format($fullyEnabled) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="tp-stat">
                    <span>No TP Enabled</span>
                    <strong>{{ number_format($emptyConfigured) }}</strong>
                </div>
            </div>
        </div>

        <div class="tp-toolbar mb-4">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-3 mb-lg-0">
                    <input type="search" id="searchCommunity" class="form-control" placeholder="Search community, tag, category, or status">
                </div>
                <div class="col-lg-6 text-lg-right">
                    <button type="button" class="btn btn-outline-primary mb-2" id="enableVisible">
                        <i class="ri-check-double-line mr-1"></i> Enable Visible
                    </button>
                    <button type="button" class="btn btn-outline-secondary mb-2" id="disableVisible">
                        <i class="ri-close-line mr-1"></i> Disable Visible
                    </button>
                    <button type="button" class="btn btn-outline-success mb-2" id="presetPrimary">
                        TP1-TP3 Preset
                    </button>
                </div>
            </div>
        </div>

        <form id="tpSettingsForm" method="POST" action="{{ route('communities.tp_settings_dashboard.update') }}">
            @csrf
            <div class="tp-panel">
                <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
                    <div>
                        <h5 class="mb-1">Community TP Matrix</h5>
                        <div class="tp-muted">Use row presets for a single community or toolbar presets for the filtered set currently visible.</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="tp-table table table-hover align-middle text-center mb-0">
                        <thead>
                            <tr>
                                <th class="text-left">Community</th>
                                <th>Category</th>
                                @for($i = 1; $i <= 10; $i++)
                                    <th>TP{{ $i }}</th>
                                @endfor
                                <th>Enabled</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="communityTableBody">
                            @forelse($communities as $community)
                                @php
                                    $enabledCount = $community->tpSettings->where('enabled', 1)->count();
                                    $searchText = strtolower(trim($community->name . ' ' . ($community->community_tag ?? '') . ' ' . ($community->category ?? '') . ' ' . ((int) $community->status === 1 ? 'active' : 'inactive')));
                                @endphp
                                <tr class="community-row" data-search="{{ $searchText }}" data-community="{{ $community->id }}">
                                    <td class="text-left community-cell">
                                        <input type="hidden" name="selected_communities[]" value="{{ $community->id }}">
                                        <strong>{{ $community->name }}</strong>
                                        <div class="tp-muted">{{ $community->community_tag ?: 'No tag configured' }}</div>
                                    </td>
                                    <td>
                                        <span class="category-pill {{ $categoryClass($community->category) }}">{{ $categoryLabel($community->category) }}</span>
                                    </td>
                                    @for($i = 1; $i <= 10; $i++)
                                        @php
                                            $setting = $community->tpSettings->firstWhere('tp_level', $i);
                                            $enabled = $setting ? (bool) $setting->enabled : false;
                                        @endphp
                                        <td>
                                            <label class="tp-switch" title="TP{{ $i }}">
                                                <input class="tp-checkbox tp-checkbox-{{ $community->id }}" type="checkbox"
                                                       name="tp[{{ $community->id }}][{{ $i }}]"
                                                       value="1" {{ $enabled ? 'checked' : '' }}>
                                                <span class="tp-slider"></span>
                                            </label>
                                        </td>
                                    @endfor
                                    <td>
                                        <span class="coverage-pill row-enabled-count">{{ $enabledCount }}/10</span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ (int) $community->status === 1 ? 'status-active' : 'status-inactive' }}">
                                            {{ (int) $community->status === 1 ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <button type="button" class="btn btn-sm btn-outline-primary community-enable-all" data-community="{{ $community->id }}">
                                                Enable All
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary community-disable-all" data-community="{{ $community->id }}">
                                                Disable All
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success community-preset-primary" data-community="{{ $community->id }}">
                                                TP1-TP3
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15">
                                        <div class="empty-state">No communities available for TP notification configuration.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div id="emptySearchState" class="empty-state mt-3 d-none">No communities match your search.</div>

                <div class="d-flex justify-content-between align-items-center flex-wrap mt-4">
                    <div class="tp-muted mb-2">Saving updates all communities in this dashboard.</div>
                    <div>
                        <a href="{{ route('communities.index') }}" class="btn btn-light mb-2">Back</a>
                        <button type="submit" class="btn btn-success mb-2">
                            <i class="ri-save-3-line mr-1"></i> Save TP Settings
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $(function () {
        function updateRowCount(row) {
            const checked = row.find('.tp-checkbox:checked').length;
            row.find('.row-enabled-count').text(checked + '/10');
        }

        function setRow(communityId, mode) {
            const checkboxes = $('.tp-checkbox-' + communityId);
            checkboxes.each(function (index) {
                if (mode === 'all') {
                    this.checked = true;
                }

                if (mode === 'none') {
                    this.checked = false;
                }

                if (mode === 'primary') {
                    this.checked = index < 3;
                }
            });

            updateRowCount($('tr[data-community="' + communityId + '"]'));
        }

        function visibleRows() {
            return $('.community-row').filter(function () {
                return $(this).is(':visible');
            });
        }

        $('.tp-checkbox').on('change', function () {
            updateRowCount($(this).closest('tr'));
        });

        $('.community-enable-all').on('click', function () {
            setRow($(this).data('community'), 'all');
        });

        $('.community-disable-all').on('click', function () {
            setRow($(this).data('community'), 'none');
        });

        $('.community-preset-primary').on('click', function () {
            setRow($(this).data('community'), 'primary');
        });

        $('#enableVisible').on('click', function () {
            visibleRows().each(function () {
                setRow($(this).data('community'), 'all');
            });
        });

        $('#disableVisible').on('click', function () {
            visibleRows().each(function () {
                setRow($(this).data('community'), 'none');
            });
        });

        $('#presetPrimary').on('click', function () {
            visibleRows().each(function () {
                setRow($(this).data('community'), 'primary');
            });
        });

        $('#searchCommunity').on('input', function () {
            const filter = this.value.trim().toLowerCase();
            let visible = 0;

            $('.community-row').each(function () {
                const match = $(this).data('search').includes(filter);
                $(this).toggle(match);

                if (match) {
                    visible++;
                }
            });

            $('#emptySearchState').toggleClass('d-none', visible !== 0 || $('.community-row').length === 0);
        });
    });
</script>
@endsection
