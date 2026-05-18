@extends('admin.admin_master')
@section('admin')

<head>
    <title>Trading Signals | HC Gaming Studio</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Lightbox CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.3/css/lightbox.min.css">

    <style>
        /* Custom card styling */
        .summary-card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .summary-card:hover {
            transform: translateY(-5px);
        }

        /* Table word wrapping */
        #datatable {
            table-layout: fixed;
        }
        #datatable td:nth-child(2),
        #datatable td:nth-child(3) {
            word-wrap: break-word;
            overflow-wrap: break-word;
            max-width: 120px;
        }

        /* Responsive table */
        @media screen and (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }
        }

        .badge-status {
            font-size: 0.85rem;
            padding: 0.35em 0.55em;
            border-radius: 0.35rem;
        }
    </style>
</head>

<div class="page-content">
    <div class="container-fluid">

        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">All Trading Signals</h4>
            <a href="{{ route('add.trading.signal') }}" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Signal
            </a>
        </div>

        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                @foreach ($breadcrumbData as $breadcrumb)
                    <li class="breadcrumb-item">
                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                    </li>
                @endforeach
            </ol>
        </nav>

        {{-- Summary Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card summary-card text-center bg-primary text-white p-3">
                    <h6 class="mb-1">Total Signals</h6>
                    <h3>{{ $totalSignals }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card text-center bg-success text-white p-3">
                    <h6 class="mb-1">Total TP (TP1 – TP10)</h6>
                    <h3>{{ $totalTP }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card text-center bg-danger text-white p-3">
                    <h6 class="mb-1">Total SL</h6>
                    <h3>{{ $totalSL }}</h3>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card summary-card text-center bg-warning text-white p-3">
                    <h6 class="mb-1">Total Cancel</h6>
                    <h3>{{ $totalCancel }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card text-center bg-info text-white p-3">
                    <h6 class="mb-1">Total BE</h6>
                    <h3>{{ $totalBE }}</h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card text-center bg-dark text-white p-3">
                    <h6 class="mb-1">Total Done</h6>
                    <h3>{{ $totalDone }}</h3>
                </div>
            </div>
        </div>

        {{-- Filter Form --}}
        <form method="GET" action="{{ route('all.trading.signals') }}" class="mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="quick_range" class="form-label">Quick Range</label>
                    <select id="quick_range" name="quick_range" class="form-select">
                        <option value="">-- Select Range --</option>
                        <option value="today" {{ request('quick_range') == 'today' ? 'selected' : '' }}>Today</option>
                        <option value="7days" {{ request('quick_range') == '7days' ? 'selected' : '' }}>Last 7 Days</option>
                        <option value="30days" {{ request('quick_range') == '30days' ? 'selected' : '' }}>Last 30 Days</option>
                        <option value="90days" {{ request('quick_range') == '90days' ? 'selected' : '' }}>Last 90 Days</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="from_date" class="form-label">From</label>
                    <input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date', $from_date ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label for="to_date" class="form-label">To</label>
                    <input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date', $to_date ?? '') }}">
                </div>
                <div class="col-md-3 d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                    <a href="{{ route('all.trading.signals') }}" class="btn btn-secondary w-100">Reset</a>
                </div>
            </div>
        </form>

        {{-- Trading Signals Table --}}
        <div class="table-responsive">
            <table class="table table-bordered table-hover dt-responsive nowrap" id="datatable">
                <thead class="table-light">
                    <tr>
                        <th>SI</th>
                        <th>Code</th>
                        @if(auth()->user()->role_id <= 2)
                            <th>Username</th>
                        @endif
                        <th>Pair</th>
                        <th>Price</th>
                        <th>SL</th>
                        <th>TP1</th>
                        <th>TP2</th>
                        <th>TP3</th>
                        <th>TP4</th>
                        <th>Progress</th>
                        <th>Risk Level</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php($i = 1)
                    @foreach($signals as $item)
                        <tr>
                            <td>{{ $i++ }}</td>
                            <td>{{ $item->signal_code }}</td>
                            @if(auth()->user()->role_id <= 2)
                                <td>{{ $item->user?->username ?? '-' }}</td>
                            @endif
                            <td>{{ $item->trading_pair }}</td>
                            <td>{{ $item->entry_price }}</td>
                            <td>{{ $item->stop_loss }}</td>
                            <td>{{ $item->target_1 ?? '-' }}</td>
                            <td>{{ $item->target_2 ?? '-' }}</td>
                            <td>{{ $item->target_3 ?? '-' }}</td>
                            <td>{{ $item->target_4 ?? '-' }}</td>
                            <td>
                                @if($item->IsDone)
                                    <span class="badge badge-status bg-dark">Done</span>
                                @elseif($item->IsBE)
                                    <span class="badge badge-status bg-warning text-dark">⚖️ BE Hitted</span>
                                @else
                                    <span class="badge badge-status
                                        @if($item->status == 0) bg-secondary
                                        @elseif($item->status == 1) bg-primary
                                        @elseif($item->status >= 2 && $item->status <= 11) bg-success
                                        @elseif($item->status == 12) bg-warning
                                        @elseif($item->status == 13) bg-danger
                                        @endif">
                                        {{ $statusMap[$item->status] ?? '-' }}
                                    </span>
                                @endif
                            </td>
                            <td>{{ $item->risk_level ?? '-' }}</td>
                            <td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-nowrap">
                                {{-- Action buttons --}}
                                <a href="{{ route('view.trading.signal', $item->id) }}" class="btn btn-sm btn-secondary mb-1">
                                    <i class="fas fa-eye"></i>
                                </a>

                                @if(!$item->IsDone)
                                    <a href="{{ route('edit.trading.signal', $item->id) }}" class="btn btn-sm btn-info mb-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="{{ route('delete.trading.signal', $item->id) }}" class="btn btn-sm btn-danger mb-1">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>

                                    @if($item->status == 0)
                                        <button type="button" class="btn btn-sm btn-warning cancel-btn mb-1" data-id="{{ $item->id }}">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-success activate-btn mb-1" data-id="{{ $item->id }}">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Cancel Modal --}}
<div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form id="cancelForm" method="POST">@csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Trading Signal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="cancel_reason_input" class="form-label">Reason for cancellation</label>
                        <textarea class="form-control" id="cancel_reason_input" name="reason" rows="3" required></textarea>
                    </div>
                    <p>Are you sure you want to cancel this signal?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-warning">Cancel Signal</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Activate Modal --}}
<div class="modal fade" id="activateModal" tabindex="-1" aria-labelledby="activateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form id="activateForm" method="POST">@csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Activate Signal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="trigger_time" class="form-label">Trigger Time (Optional)</label>
                        <input type="datetime-local" class="form-control" id="trigger_time" name="trigger_time">
                    </div>
                    <p>Are you sure you want to activate this signal?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Activate</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Scripts --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function () {
        $('#datatable').DataTable({
            responsive: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
        });

        const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
        const activateModal = new bootstrap.Modal(document.getElementById('activateModal'));

        // Cancel
        $('#datatable').on('click', '.cancel-btn', function () {
            const id = $(this).data('id');
            $('#cancel_reason_input').val('');
            $('#cancelForm').attr('action', `/cancel/trading/signal/${id}`);
            cancelModal.show();
        });

        // Activate
        $('#datatable').on('click', '.activate-btn', function () {
            const id = $(this).data('id');
            $('#trigger_time').val('');
            $('#activateForm').attr('action', `/activate/trading/signal/${id}`);
            activateModal.show();
        });
    });
</script>

@endsection