@extends('admin.admin_master')
@section('admin')

<title>Feature Toggles | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title d-flex justify-content-between align-items-center">
                            Feature Toggles
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFeatureModal">
                                + Add Feature
                            </button>
                        </h4>

                        @if(session('success'))
                            <div class="alert alert-success mt-2">{{ session('success') }}</div>
                        @endif

                      <table class="table table-striped table-hover table-bordered mt-3">
                            <thead class="table-dark">
                                <tr>
                                    <th>Feature Name</th>
                                    <th style="width: 120px; text-align: center;">Status</th>
                                    <th style="width: 130px; text-align: center;">Toggle</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($features as $feature)
                                <tr>
                                    <td class="align-middle">{{ $feature->feature_name }}</td>
                                    <td class="align-middle text-center">
                                        @if($feature->enabled)
                                            <span class="badge bg-success">Enabled</span>
                                        @else
                                            <span class="badge bg-danger">Disabled</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center">
                                        <form action="{{ route('admin.features.update', $feature->id) }}" method="POST" style="display:inline-block;">
                                            @csrf
                                            <input type="hidden" name="enabled" value="{{ $feature->enabled ? 0 : 1 }}">
                                            <button 
                                                type="submit" 
                                                class="btn btn-sm btn-{{ $feature->enabled ? 'danger' : 'success' }}" 
                                                title="{{ $feature->enabled ? 'Disable this feature' : 'Enable this feature' }}">
                                                {{ $feature->enabled ? 'Disable' : 'Enable' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Feature Modal -->
<div class="modal fade" id="addFeatureModal" tabindex="-1" aria-labelledby="addFeatureModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route('store.feature') }}">
        <input type="hidden" name="redirect_to" value="{{ url()->current() }}">

        @csrf
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addFeatureModalLabel">Add New Feature</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="feature_name" class="form-label">Feature Name</label>
                    <input type="text" class="form-control" id="feature_name" name="feature_name" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Feature</button>
            </div>
        </div>
    </form>
  </div>
</div>

@endsection
