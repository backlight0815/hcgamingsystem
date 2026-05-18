@extends('admin.admin_master')
@section('admin')

<style>
    .btn {
        float: right;
        padding: 10px;
    }

    #datatable {
        table-layout: fixed;
    }

    .long {
        overflow-x: hidden;
        width: 150px;
    }
    .padding{
        padding: 10px;
    }

    .long:hover {
        position: absolute;
        z-index: 10;
        top: 0;
        left: 0;
        width: 200px;
        background-color: #c0c0c0;
        border: 1px solid #000000;
        overflow-x: visible;
    }
</style>

<title>Trading Pairs | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <!-- Page Title & Buttons -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">All Trading Pairs</h4>
  <div class='padding'>
    <button class="btn btn-success waves-effect waves-light"  type="button" onclick="redirectToAddPair()" style="margin-right:10px;">
        Add New Pair
    </button>
    
    <button class="btn btn-primary waves-effect waves-light" type="button" data-bs-toggle="modal" data-bs-target="#importModal">
        Import Pairs
    </button>
</div>

                </div>
            </div>
        </div>

        <!-- Statistics Row -->
        <div class="row text-center mb-4">
            <div class="col-md-3">
                <div class="border border-dark p-3">
                    <h4>{{ $totalpairs }}</h4>
                    <p class="text-muted mb-0">Number of Pairs</p>
                </div>
            </div>
        </div>

        <!-- Breadcrumb -->
        <div class="breadcrumb">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span> / </span>
                @endif
            @endforeach
        </div>

        <!-- Trading Pairs Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title mb-4">Trading Pair List</h4>

                        <table id="datatable" class="table table-bordered dt-responsive nowrap w-100">
    <thead>
        <tr>
            <th>SI</th>
            <th>Symbol</th>
            <th>Description</th>
            <th>Pip Factor</th>
            <th>Pip Decimal</th>
            <th>Created At</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        @php($i = 1)
        @foreach($pairs as $pair)
            <tr>
                <td>{{ $i++ }}</td>
                <td>{{ strtoupper($pair->symbol) }}</td>
                <td>{{ $pair->description }}</td>
                <td>{{ $pair->pip_factor }}</td>
                <td>{{ $pair->pip_decimal }}</td>
                <td>{{ \Carbon\Carbon::parse($pair->created_at)->format('Y-m-d H:i') }}</td>
                <td>
                    <a href="{{ route('edit.trading.pair', $pair->id) }}" class="btn btn-sm btn-info">Edit</a>
                    <a href="{{ route('delete.trading.pair', $pair->id) }}" class="btn btn-sm btn-danger" id="delete">Delete</a>
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

<!-- ✅ Import Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="importModalLabel">Import Trading Pairs</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('import.trading.pairs') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-body">
          <div class="d-flex align-items-center">
            <input type="file" name="file" class="form-control me-2 @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
            <a href="{{ route('download.trading.pairs.template') }}" class="btn btn-info">
              Template
            </a>
          </div>

          {{-- Show validation errors --}}
          @if ($errors->has('file'))
              <div class="mt-3 alert alert-danger">
                  @if(is_array($errors->get('file')))
                      <ul class="mb-0">
                          @foreach ($errors->get('file') as $error)
                              <li>{{ $error }}</li>
                          @endforeach
                      </ul>
                  @else
                      <p>{{ $errors->first('file') }}</p>
                  @endif
              </div>
          @endif
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ✅ Auto reopen modal if validation errors -->
@if ($errors->has('file'))
<script>
    var importModal = new bootstrap.Modal(document.getElementById('importModal'));
    importModal.show();
</script>
@endif

<script>
    function redirectToAddPair() {
        window.location.href = "{{ route('add.trading.pair') }}";
    }
</script>

@endsection
