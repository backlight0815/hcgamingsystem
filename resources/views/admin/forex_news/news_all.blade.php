@extends('admin.admin_master')
@section('admin')

<head>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.0/css/bootstrap.min.css">

    <style>
        /* Professional table design */
        .table thead th {
            background-color: #343a40;
            color: #fff;
            font-weight: 600;
            text-align: center;
        }

        .table tbody td {
            vertical-align: middle;
            text-align: center;
        }

        .badge-impact {
            padding: 0.5em 0.75em;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .breadcrumb a {
            text-decoration: none;
            color: #0d6efd;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: #0a58ca;
        }

        .card-header-custom {
            background-color: #0d6efd;
            color: #fff;
            font-weight: 600;
        }

        .img-thumb {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .action-btns .btn {
            margin: 2px;
        }
    </style>
</head>

<div class="page-content">
    <div class="container-fluid">

        <!-- Page Title -->
        <div class="row mb-3">
            <div class="col-md-6">
                <h3 class="fw-bold">News Management</h3>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="{{ route('trading.news.create') }}" class="btn btn-success btn-lg">
                    <i class="fas fa-plus"></i> Add News
                </a>
            </div>
        </div>

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-light p-2 rounded">
                @foreach ($breadcrumbData as $breadcrumb)
                    <li class="breadcrumb-item">
                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                    </li>
                @endforeach
            </ol>
        </nav>
<div class="row">
    <!-- Row 1: Signals, TP, SL -->
    <div class="col-md-4 mb-3">
        <div class="card bg-primary text-white p-3 text-center">
            <h5>No News</h5>
            <h2>{{ $totalNews }}</h2>
        </div>
    </div>
        <!-- Table Card -->
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header card-header-custom">
                <h5 class="mb-0">All News</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Impact</th>
                                <th>Content</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($news as $key => $item)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ \Carbon\Carbon::parse($item->news_date)->format('d M Y') }}</td>
                                    <td>
                                        @php
                                            $impactLabels = [1 => 'Low', 2 => 'Medium', 3 => 'High'];
                                            $impactColors = [1 => 'success', 2 => 'warning', 3 => 'danger'];
                                        @endphp
                                        <span class="badge badge-impact bg-{{ $impactColors[$item->impact] ?? 'secondary' }}">
                                            {{ $impactLabels[$item->impact] ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="text-start">{{ $item->content }}</td>
                                    <td>
                                        @if($item->image)
                                            <img src="{{ asset($item->image) }}" alt="News Image" class="img-thumb">
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="action-btns">
                                        <a href="{{ route('trading.news.edit', $item->id) }}" class="btn btn-info btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('trading.news.destroy', $item->id) }}" method="POST" style="display:inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete" onclick="return confirm('Are you sure you want to delete this news?')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>

                                        <form action="{{ route('trading.news.sendDiscord', $item->id) }}" method="POST" style="display:inline-block;">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm" title="Send to Discord">
                                                <i class="fab fa-discord"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-2 text-end">
                    <strong>Total News: {{ $totalNews }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
