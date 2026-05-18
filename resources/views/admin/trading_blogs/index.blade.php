@extends('admin.admin_master')
@section('admin')

<title>Trading Blog Management | HC Gaming Studio</title>

<style>
    .tb-stat {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #ffffff;
        padding: 18px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.04);
    }

    .tb-stat span {
        display: block;
        color: #64748b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .tb-stat strong {
        display: block;
        margin-top: 6px;
        color: #0f172a;
        font-size: 28px;
    }

    .tb-cover {
        width: 72px;
        height: 52px;
        border-radius: 8px;
        object-fit: cover;
        background: #e5e7eb;
    }

    .tb-cover-fallback {
        width: 72px;
        height: 52px;
        border-radius: 8px;
        background: #0f172a;
        color: #ffffff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
</style>

<div class="page-content">
    <div class="container-fluid">

        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Trading Blog Management</h4>
                        <p class="text-muted mb-0 mt-1">Trading sharing, knowledge, psychology, and prop firm articles.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('trading.blogs.index') }}" class="btn btn-outline-secondary" target="_blank">
                            <i class="fas fa-external-link-alt"></i> Reader View
                        </a>
                        <a href="{{ route('admin.trading.blogs.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Post
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="breadcrumb mb-3">
            @foreach ($breadcrumbData as $breadcrumb)
                <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                @if (!$loop->last)
                    <span class="mx-1">/</span>
                @endif
            @endforeach
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="tb-stat">
                    <span>Total Posts</span>
                    <strong>{{ $totalBlogs }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="tb-stat">
                    <span>Published</span>
                    <strong>{{ $publishedBlogs }}</strong>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="tb-stat">
                    <span>Drafts</span>
                    <strong>{{ $draftBlogs }}</strong>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.trading.blogs.index') }}" class="row g-2 align-items-end">
                    <div class="col-lg-4">
                        <label class="form-label" for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" value="{{ request('search') }}" placeholder="Title, excerpt, or tags">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="category">Category</label>
                        <select name="category" id="category" class="form-control">
                            <option value="">All Categories</option>
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="trading_blog_status_filter">Status</label>
                        <select name="status" id="trading_blog_status_filter" class="form-control">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                        <a href="{{ route('admin.trading.blogs.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Cover</th>
                                <th>Post</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Featured</th>
                                <th>Views</th>
                                <th>Published</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($blogs as $blog)
                                <tr>
                                    <td>
                                        @if($blog->cover_image)
                                            <img src="{{ asset($blog->cover_image) }}" alt="{{ $blog->title }}" class="tb-cover">
                                        @else
                                            <span class="tb-cover-fallback"><i class="fas fa-chart-line"></i></span>
                                        @endif
                                    </td>
                                    <td>
                                        <strong>{{ $blog->title }}</strong>
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($blog->excerpt, 90) }}</div>
                                        @if($blog->tags)
                                            <div class="small text-muted">Tags: {{ $blog->tags }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $blog->category_label }}</td>
                                    <td>
                                        @if($blog->status === \App\Models\TradingBlog::STATUS_PUBLISHED)
                                            <span class="badge bg-success">Published</span>
                                        @else
                                            <span class="badge bg-secondary">Draft</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($blog->is_featured)
                                            <span class="badge bg-warning text-dark">Featured</span>
                                        @else
                                            <span class="text-muted">No</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($blog->views) }}</td>
                                    <td>{{ $blog->published_at ? $blog->published_at->format('Y-m-d H:i') : '-' }}</td>
                                    <td class="text-nowrap">
                                        @if($blog->status === \App\Models\TradingBlog::STATUS_PUBLISHED)
                                            <a href="{{ route('trading.blogs.show', $blog->slug) }}" class="btn btn-secondary btn-sm" target="_blank" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.trading.blogs.edit', $blog->id) }}" class="btn btn-info btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.trading.blogs.destroy', $blog->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this trading blog post?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No trading blog posts found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $blogs->links('vendor.pagination.bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
