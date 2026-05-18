@extends('admin.admin_master')
@section('admin')
@php
    $publicCount = $communities->where('category', 'public')->count();
    $executiveCount = $communities->where('category', 'executive')->count();
    $testCount = $communities->where('category', 'test')->count();
    $everyoneEnabled = $communities->where('discord_everyone_enabled', true)->count();
    $webhookReady = $communities->filter(fn ($item) => filled($item->discord_webhook_signal) || filled($item->discord_webhook_knowledge) || filled($item->discord_webhook_news) || filled($item->discord_webhook_outlook))->count();

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
        default => 'Unknown',
    };
@endphp

<title>Community Management | HC Gaming Studio</title>

<style>
    .community-page {
        color: #172033;
    }

    .community-hero {
        background: #111827;
        border: 1px solid #243047;
        border-radius: 10px;
        color: #f8fafc;
        padding: 28px;
        position: relative;
        overflow: hidden;
    }

    .community-hero::after {
        content: "";
        position: absolute;
        right: -90px;
        top: -130px;
        width: 300px;
        height: 300px;
        background: rgba(45, 212, 191, 0.15);
        border-radius: 50%;
    }

    .community-hero h1 {
        color: #fff;
        font-size: 30px;
        font-weight: 800;
        letter-spacing: 0;
        margin: 4px 0 8px;
    }

    .community-hero p,
    .community-hero .eyebrow {
        color: #cbd5e1;
        margin: 0;
    }

    .community-hero .eyebrow {
        font-size: 12px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .community-stat,
    .community-panel {
        background: #fff;
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06);
    }

    .community-stat {
        height: 100%;
        padding: 18px;
    }

    .community-stat span,
    .community-muted {
        color: #617188;
        font-size: 13px;
    }

    .community-stat strong {
        color: #101827;
        display: block;
        font-size: 25px;
        line-height: 1.15;
        margin-top: 6px;
    }

    .community-panel {
        padding: 22px;
    }

    .community-table {
        border-collapse: separate;
        border-spacing: 0;
        color: #172033;
        width: 100%;
    }

    .community-table thead th {
        background: #0f172a;
        border-color: #27344d;
        color: #fff;
        font-size: 12px;
        letter-spacing: 0;
        padding: 14px 12px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .community-table tbody td {
        border-color: #e2e8f0;
        padding: 14px 12px;
        vertical-align: middle;
    }

    .community-name {
        min-width: 230px;
    }

    .community-name strong {
        color: #111827;
        display: block;
        font-size: 15px;
    }

    .webhook-cell {
        max-width: 360px;
        min-width: 260px;
    }

    .webhook-text {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        color: #475569;
        display: block;
        overflow: hidden;
        padding: 8px 10px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .category-pill,
    .status-pill,
    .webhook-pill {
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

    .webhook-pill {
        background: #ecfeff;
        color: #0e7490;
    }

    .action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-width: 320px;
    }

    .empty-state {
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
        color: #617188;
        padding: 34px;
        text-align: center;
    }

    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        color: #111827;
        margin-left: 8px;
        min-height: 34px;
        padding: 6px 10px;
    }

    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_filter label,
    .dataTables_wrapper .dataTables_length label {
        color: #475569;
    }

    @media (max-width: 575px) {
        .community-hero,
        .community-panel {
            padding: 18px;
        }

        .community-hero h1 {
            font-size: 24px;
        }
    }
</style>

<div class="page-content community-page">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Community Management</h4>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            @foreach ($breadcrumbData as $breadcrumb)
                                <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}">
                                    @if ($loop->last || empty($breadcrumb['url']))
                                        {{ $breadcrumb['label'] }}
                                    @else
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <section class="community-hero mb-4">
            <div class="row align-items-center position-relative" style="z-index: 1;">
                <div class="col-xl-8">
                    <div class="eyebrow">Discord Community Operations</div>
                    <h1>Community Management</h1>
                    <p>Control community routing, Discord webhook readiness, document access, TP notifications, and @everyone publishing behavior.</p>
                </div>
                <div class="col-xl-4 mt-4 mt-xl-0 text-xl-right">
                    <a href="{{ route('communities.documents.index') }}" class="btn btn-light mb-2">
                        <i class="ri-folder-open-line mr-1"></i> Documents
                    </a>
                    <a href="{{ route('communities.create') }}" class="btn btn-info mb-2">
                        <i class="ri-add-line mr-1"></i> Add Community
                    </a>
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="community-stat">
                    <span>Total</span>
                    <strong>{{ number_format($totalCommunity) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="community-stat">
                    <span>Active</span>
                    <strong>{{ number_format($totalActive) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="community-stat">
                    <span>Inactive</span>
                    <strong>{{ number_format($totalInactive) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="community-stat">
                    <span>Public / Executive</span>
                    <strong>{{ number_format($publicCount) }} / {{ number_format($executiveCount) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="community-stat">
                    <span>Webhook Ready</span>
                    <strong>{{ number_format($webhookReady) }}</strong>
                </div>
            </div>
            <div class="col-md-6 col-xl-2 mb-4">
                <div class="community-stat">
                    <span>@everyone On</span>
                    <strong>{{ number_format($everyoneEnabled) }}</strong>
                </div>
            </div>
        </div>

        <div class="community-panel">
            <div class="d-flex justify-content-between align-items-start flex-wrap mb-4">
                <div>
                    <h5 class="mb-1">Community Directory</h5>
                    <div class="community-muted">Review routing status, webhook coverage, document libraries, and notification controls.</div>
                </div>
                <div class="community-muted mt-2 mt-sm-0">Test communities: {{ number_format($testCount) }}</div>
            </div>

            <div class="table-responsive">
                <table id="communityTable" class="community-table table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Community</th>
                            <th>Primary Webhook</th>
                            <th>Channels</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>@everyone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($communities as $key => $item)
                            @php
                                $channelCount = collect([
                                    $item->discord_webhook_signal,
                                    $item->discord_webhook_outlook,
                                    $item->discord_webhook_knowledge,
                                    $item->discord_webhook_news,
                                    $item->discord_webhook_weeklys_signal,
                                ])->filter()->count();
                            @endphp
                            <tr>
                                <td>{{ $key + 1 }}</td>
                                <td>
                                    <div class="community-name">
                                        <strong>{{ $item->name }}</strong>
                                        <div class="community-muted">{{ $item->community_tag ?: 'No tag configured' }}</div>
                                    </div>
                                </td>
                                <td class="webhook-cell">
                                    <span class="webhook-text" title="{{ $item->discord_webhook ?: '-' }}">{{ $item->discord_webhook ?: 'No primary webhook' }}</span>
                                </td>
                                <td>
                                    <span class="webhook-pill">{{ $channelCount }} configured</span>
                                </td>
                                <td>
                                    <span class="category-pill {{ $categoryClass($item->category) }}">{{ $categoryLabel($item->category) }}</span>
                                </td>
                                <td>
                                    <span class="status-pill {{ (int) $item->status === 1 ? 'status-active' : 'status-inactive' }}">
                                        {{ (int) $item->status === 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="status-pill {{ $item->discord_everyone_enabled ? 'status-active' : 'status-inactive' }}">
                                        {{ $item->discord_everyone_enabled ? 'On' : 'Off' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="action-row">
                                        <a href="{{ route('communities.documents.index', ['community_id' => $item->id]) }}" class="btn btn-sm btn-outline-secondary">
                                            <i class="ri-folder-open-line"></i> Docs
                                        </a>
                                        <a href="{{ route('communities.edit', $item->id) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <a href="{{ route('communities.tp_settings') }}" class="btn btn-sm btn-outline-warning">
                                            <i class="ri-settings-3-line"></i> TP
                                        </a>
                                        <form id="everyone-toggle-form-{{ $item->id }}" action="{{ route('communities.everyone_toggle.update') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="community_id" value="{{ $item->id }}">
                                            <input type="hidden" name="discord_everyone_enabled" value="{{ $item->discord_everyone_enabled ? 0 : 1 }}">
                                            <button type="submit" class="btn btn-sm btn-outline-info">
                                                <i class="ri-group-line"></i> {{ $item->discord_everyone_enabled ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('communities.destroy', $item->id) }}" class="btn btn-sm btn-outline-danger" id="delete">
                                            <i class="ri-delete-bin-line"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state">No communities configured yet.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    $(function () {
        const table = $('#communityTable');

        if (table.find('tbody tr').length && table.find('.empty-state').length === 0) {
            table.DataTable({
                order: [[1, 'asc']],
                pageLength: 25,
                responsive: false,
                language: {
                    search: 'Search communities:',
                    lengthMenu: 'Show _MENU_ communities',
                    emptyTable: 'No communities found'
                }
            });
        }
    });
</script>
@endsection
