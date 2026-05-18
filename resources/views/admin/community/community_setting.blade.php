@extends('admin.admin_master')
@section('admin')

<div class="page-content">
    <div class="container-fluid">

        <div class="container-fluid py-4">
            <div class="row mb-3">
                <div class="col-12">
                    <h4 class="fw-bold">@everyone Notification Dashboard</h4>
                    <p class="text-muted">
                        Manage "@everyone" tagging for communities. Toggle on/off per community.
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Notification Settings</span>
                        <div>
                            <input type="text" id="searchCommunity" class="form-control form-control-sm" placeholder="Search Community">
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <form method="POST" action="{{ route('communities.everyone_toggle.update') }}">
                            @csrf
                            <div class="table-responsive">
                                <table class="table table-hover table-bordered align-middle text-center mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width:5%">
                                                <input type="checkbox" id="select_all" title="Select All">
                                            </th>
                                            <th style="width:70%">Community</th>
                                            <th>Everyone</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>

                                    <tbody id="communityTableBody">
                                        @foreach($communities as $community)
                                        <tr>
                                            {{-- Select Community --}}
                                            <td>
                                                <input type="checkbox" name="selected_communities[]" value="{{ $community->id }}" class="select_community">
                                            </td>

                                            {{-- Community Name --}}
                                            <td class="text-start">{{ $community->name }}</td>

                                            {{-- Everyone toggle --}}
                                            <td>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input everyone-toggle-{{ $community->id }}" type="checkbox"
                                                           name="everyone[{{ $community->id }}]" value="1"
                                                           {{ $community->discord_everyone_enabled ? 'checked' : '' }}>
                                                </div>
                                            </td>

                                            {{-- Status --}}
                                            <td>
                                                @if ($community->status == 1)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger">Inactive</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="card-footer d-flex justify-content-between mt-2">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Save Everyone Settings
                                </button>
                                <a href="{{ route('communities.index') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-1"></i> Back
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- JS for functionality --}}
<script>
    // 🔹 Select All Communities
    document.getElementById('select_all').addEventListener('change', function() {
        let communityCheckboxes = document.querySelectorAll('.select_community');
        communityCheckboxes.forEach(cb => cb.checked = this.checked);
    });

    // 🔹 Simple search filter
    document.getElementById('searchCommunity').addEventListener('keyup', function() {
        let filter = this.value.toLowerCase();
        document.querySelectorAll('#communityTableBody tr').forEach(row => {
            let name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            row.style.display = name.includes(filter) ? '' : 'none';
        });
    });
</script>

{{-- Styling --}}
<style>
    .form-switch .form-check-input {
        cursor: pointer;
        width: 2em;
        height: 1em;
    }
    .table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .table tbody tr:hover {
        background-color: #f8f9fa;
    }
    @media (max-width: 768px) {
        .table-responsive {
            overflow-x: auto;
        }
        .table td, .table th {
            font-size: 0.85rem;
            padding: 0.35rem;
        }
    }
</style>

@endsection
