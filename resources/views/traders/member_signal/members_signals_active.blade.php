@extends('admin.admin_master')
@section('admin')

<title>Active Signals Dashboard | HC Gaming Studio</title>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    /* ===================== Dashboard Cards ===================== */
    .summary-card {
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        transition: all 0.2s ease;
        padding: 1.25rem 0.75rem;
        margin-bottom: 10px;
        border: none;
    }
    .summary-card h3 { font-size: 1.8rem; margin: 0; font-weight: 600; }
    .summary-card h6 { font-size: 0.9rem; margin-bottom: 5px; font-weight: 500; }

    /* Custom Colors to match original UI */
    .bg-custom-primary { background-color: #0d6efd !important; }
    .bg-custom-success { background-color: #198754 !important; }
    .bg-custom-info    { background-color: #0dcaf0 !important; }
    .bg-custom-warning { background-color: #ffc107 !important; }
    .bg-custom-danger  { background-color: #dc3545 !important; }
    .bg-custom-dark    { background-color: #212529 !important; }

    /* Force text colors to override any admin template styles */
    .text-force-white, .text-force-white h6, .text-force-white h3 { color: #ffffff !important; }
    .text-force-dark, .text-force-dark h6, .text-force-dark h3 { color: #212529 !important; }

    /* ===================== Table ===================== */
    #activeSignalsTable { table-layout: fixed; width: 100%; }
    #activeSignalsTable td:nth-child(2),
    #activeSignalsTable td:nth-child(3) {
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-width: 120px;
    }
    .badge-status { font-size: 0.75rem; padding: 0.35em 0.65em; border-radius: 0.35rem; font-weight: 600; }

    /* ===================== Chart Cards ===================== */
    .chart-card {
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        background: #fff;
        padding: 20px;
        margin-bottom: 15px;
        height: 100%;
        border: 1px solid rgba(0,0,0,.125);
    }
    .chart-card h6 { margin-bottom: 15px; font-weight: 600; color: #333; }

    /* ===================== Responsive ===================== */
    @media screen and (max-width: 768px) {
        .table-responsive { overflow-x: auto; }
    }
</style>

<div class="page-content">
    <div class="container-fluid">

        {{-- Dashboard Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">Active Signals Dashboard</h4>
        </div>

        {{-- Summary Cards --}}
        <div class="row mb-3">
            <div class="col-md-4 col-sm-6">
                <div class="card summary-card text-center bg-custom-primary text-force-white">
                    <h6>Total Active Signals</h6>
                    <h3>{{ $totalSignals }}</h3>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card summary-card text-center bg-custom-success text-force-white">
                    <h6>TP Achieved</h6>
                    <h3>{{ $totalTP }}</h3>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card summary-card text-center bg-custom-info text-force-white">
                    <h6>Break-Even Hit</h6>
                    <h3>{{ $totalBE }}</h3>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card summary-card text-center bg-custom-warning text-force-dark">
                    <h6>Cancelled</h6>
                    <h3>{{ $totalCancel }}</h3>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card summary-card text-center bg-custom-danger text-force-white">
                    <h6>SL Hit</h6>
                    <h3>{{ $totalSL }}</h3>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="card summary-card text-center bg-custom-dark text-force-white">
                    <h6>Completed</h6>
                    <h3>{{ $totalDone }}</h3>
                </div>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="row mb-4">
            <div class="col-lg-6 col-md-12 mb-3 mb-lg-0">
                <div class="chart-card">
                    <h6>Signal Status Distribution</h6>
                    <div style="position: relative; height:250px; width:100%">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="chart-card">
                    <h6>TP Achievement Progress</h6>
                    <div style="position: relative; height:250px; width:100%">
                        <canvas id="tpChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Active Signals Table --}}
        <div class="card" style="border-radius: 8px; border: 1px solid rgba(0,0,0,.125); box-shadow: 0 2px 6px rgba(0,0,0,0.05);">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover dt-responsive nowrap" id="activeSignalsTable">
                        <thead class="table-light">
                            <tr>
                                <th>SI</th>
                                <th>Code</th>
                                <th>Pair</th>
                                <th>Entry</th>
                                <th>SL</th>
                                <th>TP1</th>
                                <th>TP2</th>
                                <th>TP3</th>
                                <th>Progress</th>
                                <th>Risk</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $i = 1; @endphp
                            @foreach($signals as $signal)
                            <tr>
                                <td>{{ $i++ }}</td>
                                <td>{{ $signal->signal_code }}</td>
                                <td>{{ $signal->trading_pair }}</td>
                                <td>{{ $signal->entry_price }}</td>
                                <td>{{ $signal->stop_loss }}</td>
                                <td>{{ $signal->target_1 ?? '-' }}</td>
                                <td>{{ $signal->target_2 ?? '-' }}</td>
                                <td>{{ $signal->target_3 ?? '-' }}</td>
                                <td>
                                    @php $status = $signal->status; @endphp
                                    @if($signal->IsDone || $status == 14)
                                        <span class="badge badge-status bg-dark">Done</span>
                                    @elseif($signal->IsBE || $status == 15)
                                        <span class="badge badge-status bg-warning text-dark">⚖️ BE</span>
                                    @elseif($status == 13)
                                        <span class="badge badge-status bg-danger">SL</span>
                                    @elseif($status == 12)
                                        <span class="badge badge-status bg-warning text-dark">Cancelled</span>
                                    @elseif($status >= 2 && $status <= 11)
                                        <span class="badge badge-status bg-success">{{ $statusLabels[$status] ?? 'TP' }}</span>
                                    @elseif($status == 1)
                                        <span class="badge badge-status bg-primary">{{ $statusLabels[$status] ?? 'Active' }}</span>
                                    @elseif($status == 0)
                                        <span class="badge badge-status bg-secondary">{{ $statusLabels[$status] ?? 'Pending' }}</span>
                                    @else
                                        <span class="badge badge-status bg-light text-dark">-</span>
                                    @endif
                                </td>
                                <td>{{ $signal->risk_level ?? '-' }}</td>
                                <td>{{ $signal->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- Scripts --}}
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Check if jQuery exists before initializing DataTables
    if (typeof jQuery !== 'undefined') {
        $('#activeSignalsTable').DataTable({ responsive: true, pageLength: 25 });
    }

    // Status Chart
    const statusCtx = document.getElementById('statusChart');
    if(statusCtx) {
        new Chart(statusCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Pending','Active','TP Achieved','Cancelled','SL','Done','BE'],
                datasets: [{
                    data: [
                        {{ $signals->where('status',0)->count() }},
                        {{ $signals->where('status',1)->count() }},
                        {{ $signals->whereIn('status', range(2,11))->count() }},
                        {{ $signals->where('status',12)->count() }},
                        {{ $signals->where('status',13)->count() }},
                        {{ $signals->where('status',14)->count() }},
                        {{ $signals->where('status',15)->count() }}
                    ],
                    backgroundColor: ['#6c757d','#0d6efd','#198754','#ffc107','#dc3545','#212529','#0dcaf0'],
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins:{ legend:{ position:'bottom' } } 
            }
        });
    }

    // TP Progress Chart
    const tpCtx = document.getElementById('tpChart');
    if(tpCtx) {
        new Chart(tpCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['TP1','TP2','TP3','TP4','TP5','TP6','TP7','TP8','TP9','TP10'],
                datasets: [{
                    label: 'Achieved',
                    data: [
                        {{ $signals->where('status',2)->count() }},
                        {{ $signals->where('status',3)->count() }},
                        {{ $signals->where('status',4)->count() }},
                        {{ $signals->where('status',5)->count() }},
                        {{ $signals->where('status',6)->count() }},
                        {{ $signals->where('status',7)->count() }},
                        {{ $signals->where('status',8)->count() }},
                        {{ $signals->where('status',9)->count() }},
                        {{ $signals->where('status',10)->count() }},
                        {{ $signals->where('status',11)->count() }}
                    ],
                    backgroundColor:'#0d6efd'
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins:{ legend:{ display:false } } 
            }
        });
    }
});
</script>

@endsection