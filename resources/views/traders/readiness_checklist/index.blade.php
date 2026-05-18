@extends('admin.admin_master')
@section('admin')

<title>New Trader Readiness Checklist | HC Gaming Studio</title>

<style>
    .readiness-shell {
        color: #172033;
    }

    .readiness-hero,
    .readiness-panel,
    .readiness-item,
    .readiness-stat {
        background: #ffffff;
        border: 1px solid #dfe5ec;
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }

    .readiness-hero {
        padding: 24px;
    }

    .readiness-kicker {
        color: #0f766e;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .07em;
        text-transform: uppercase;
    }

    .readiness-hero h3,
    .readiness-panel h4,
    .readiness-item h5 {
        color: #0f172a;
        font-weight: 800;
    }

    .readiness-muted {
        color: #64748b;
    }

    .readiness-progress {
        --progress: 0deg;
        width: 132px;
        height: 132px;
        border-radius: 50%;
        background: conic-gradient(#0f766e var(--progress), #e2e8f0 0);
        display: grid;
        place-items: center;
        margin-left: auto;
    }

    .readiness-progress-inner {
        width: 102px;
        height: 102px;
        border-radius: 50%;
        background: #ffffff;
        display: grid;
        place-items: center;
        text-align: center;
    }

    .readiness-progress-inner strong {
        color: #0f172a;
        display: block;
        font-size: 28px;
        line-height: 1;
    }

    .readiness-stage {
        border-radius: 999px;
        display: inline-flex;
        font-size: 12px;
        font-weight: 800;
        padding: 7px 12px;
    }

    .readiness-stage.success { background: #dcfce7; color: #166534; }
    .readiness-stage.primary { background: #dbeafe; color: #1d4ed8; }
    .readiness-stage.warning { background: #fef3c7; color: #92400e; }
    .readiness-stage.danger { background: #fee2e2; color: #991b1b; }
    .readiness-stage.secondary { background: #e5e7eb; color: #374151; }

    .readiness-stat {
        padding: 16px;
        height: 100%;
    }

    .readiness-stat span {
        color: #64748b;
        display: block;
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .readiness-stat strong {
        color: #0f172a;
        display: block;
        font-size: 24px;
        margin-top: 4px;
    }

    .readiness-panel {
        padding: 20px;
    }

    .readiness-category {
        border-top: 1px solid #e5e7eb;
        padding: 14px 0;
    }

    .readiness-category:first-of-type {
        border-top: 0;
        padding-top: 0;
    }

    .readiness-category-bar {
        background: #e2e8f0;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
    }

    .readiness-category-bar span {
        background: #0f766e;
        display: block;
        height: 100%;
    }

    .readiness-item {
        margin-bottom: 14px;
        overflow: hidden;
    }

    .readiness-item-main {
        display: grid;
        gap: 18px;
        grid-template-columns: 1fr minmax(280px, 380px);
        padding: 18px 20px;
    }

    .readiness-item-head {
        align-items: flex-start;
        display: flex;
        gap: 12px;
    }

    .readiness-icon {
        align-items: center;
        background: #e0f2fe;
        border-radius: 8px;
        color: #0369a1;
        display: inline-flex;
        flex: 0 0 auto;
        height: 42px;
        justify-content: center;
        width: 42px;
    }

    .readiness-item.is-complete .readiness-icon {
        background: #dcfce7;
        color: #166534;
    }

    .readiness-category-label {
        color: #0f766e;
        font-size: 12px;
        font-weight: 900;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .readiness-item h5 {
        margin: 3px 0 8px;
    }

    .readiness-note {
        background: #f8fafc;
        border-top: 1px solid #e5e7eb;
        color: #475569;
        padding: 14px 20px;
    }

    .readiness-actions {
        border-left: 1px solid #e5e7eb;
        padding-left: 18px;
    }

    .readiness-actions .form-control,
    .readiness-actions .form-select {
        border-color: #cbd5e1;
        border-radius: 8px;
    }

    .readiness-live-warning {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 8px;
        color: #9a3412;
        padding: 14px 16px;
    }

    @media (max-width: 991px) {
        .readiness-progress {
            margin-left: 0;
        }

        .readiness-item-main {
            grid-template-columns: 1fr;
        }

        .readiness-actions {
            border-left: 0;
            border-top: 1px solid #e5e7eb;
            padding-left: 0;
            padding-top: 16px;
        }
    }
</style>

<div class="page-content readiness-shell">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">New Trader Readiness Checklist</h4>
                        <p class="readiness-muted mb-0">Self-check your preparation before using a real account.</p>
                    </div>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ route('all.statistics') }}">HC Trading</a></li>
                            <li class="breadcrumb-item active">Readiness Checklist</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        @if(isset($errors) && $errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="readiness-hero mb-4">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="readiness-kicker">Demo first, live later</div>
                    <h3 class="mt-2 mb-2">Complete the foundations before risking real capital.</h3>
                    <p class="readiness-muted mb-3">
                        This checklist is for your own readiness only. It does not require administration approval and it does not replace trader verification.
                    </p>
                    <span class="readiness-stage {{ $stage['tone'] }}">{{ $stage['label'] }}</span>
                    <p class="readiness-muted mt-3 mb-0">{{ $stage['message'] }}</p>
                </div>
                <div class="col-lg-4">
                    <div class="readiness-progress" style="--progress: {{ min(100, max(0, $percent)) * 3.6 }}deg;">
                        <div class="readiness-progress-inner">
                            <div>
                                <strong>{{ $percent }}%</strong>
                                <span class="readiness-muted">{{ $completed }}/{{ $total }} done</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($context['has_deposit'] && $percent < 100)
            <div class="readiness-live-warning mb-4">
                <strong>Deposit detected.</strong>
                The recommended path is still demo practice first, checklist completion second, then reduced-risk live trading only after your process is stable.
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
                <div class="readiness-stat">
                    <span>Verification</span>
                    <strong>{{ $context['verification_label'] }}</strong>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="readiness-stat">
                    <span>Journal Trades</span>
                    <strong>{{ $context['trade_count'] }}</strong>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="readiness-stat">
                    <span>Knowledge Resources</span>
                    <strong>{{ $context['knowledge_count'] }}</strong>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="readiness-stat">
                    <span>Class Recordings</span>
                    <strong>{{ $context['recording_count'] }}</strong>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-4 mb-4">
                <div class="readiness-panel mb-4">
                    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                        <div>
                            <h4 class="mb-1">Category Progress</h4>
                            <p class="readiness-muted mb-0">Core readiness: {{ $corePercent }}%</p>
                        </div>
                        @if($completed > 0)
                            <form method="POST" action="{{ route('trader.readiness.reset') }}" onsubmit="return confirm('Reset your checklist progress?')">
                                @csrf
                                <button type="submit" class="btn btn-light btn-sm">
                                    <i class="ri-refresh-line"></i> Reset
                                </button>
                            </form>
                        @endif
                    </div>

                    @foreach($categorySummaries as $category => $summary)
                        <div class="readiness-category">
                            <div class="d-flex justify-content-between gap-3 mb-2">
                                <strong>{{ $category }}</strong>
                                <span class="readiness-muted">{{ $summary['completed'] }}/{{ $summary['total'] }}</span>
                            </div>
                            <div class="readiness-category-bar">
                                <span style="width: {{ $summary['percent'] }}%"></span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="readiness-panel">
                    <h4 class="mb-2">Recommended Before Live</h4>
                    <p class="readiness-muted mb-3">Use a demo account until your rules, journal habit, and risk sizing are consistent.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('trading.knowledge.centre.index') }}" class="btn btn-outline-primary">
                            <i class="ri-book-open-line"></i> Knowledge Centre
                        </a>
                        <a href="{{ route('trading.recordings.index') }}" class="btn btn-outline-primary">
                            <i class="ri-video-line"></i> Recording Classes
                        </a>
                        <a href="{{ route('all.trading.journals') }}" class="btn btn-outline-primary">
                            <i class="ri-file-list-3-line"></i> Trading Journal
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-xl-8 mb-4">
                @foreach($items->groupBy('category') as $category => $categoryItems)
                    <div class="readiness-kicker mb-2">{{ $category }}</div>

                    @foreach($categoryItems as $item)
                        @php
                            $record = $progress->get($item->id);
                            $isComplete = $record && $record->completed_at;
                            $resourceUrl = $routeResolver($item->resource_route);
                        @endphp

                        <div class="readiness-item {{ $isComplete ? 'is-complete' : '' }}">
                            <div class="readiness-item-main">
                                <div>
                                    <div class="readiness-item-head">
                                        <span class="readiness-icon">
                                            <i class="{{ $isComplete ? 'ri-check-line' : 'ri-checkbox-blank-circle-line' }}"></i>
                                        </span>
                                        <div>
                                            <div class="readiness-category-label">{{ $item->category }}</div>
                                            <h5>{{ $item->title }}</h5>
                                            <p class="readiness-muted mb-2">{{ $item->description }}</p>
                                            @if($item->why_it_matters)
                                                <p class="mb-0"><strong>Why it matters:</strong> {{ $item->why_it_matters }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="readiness-actions">
                                    @if($resourceUrl)
                                        <a href="{{ $resourceUrl }}" class="btn btn-outline-secondary btn-sm mb-3">
                                            <i class="ri-external-link-line"></i> {{ $item->resource_label ?: 'Open Resource' }}
                                        </a>
                                    @endif

                                    @if($isComplete)
                                        <div class="mb-3">
                                            <span class="readiness-stage success">Completed {{ \Carbon\Carbon::parse($record->completed_at)->format('M d, Y') }}</span>
                                        </div>
                                        @if($record->self_rating)
                                            <div class="readiness-muted mb-2">Confidence: {{ $record->self_rating }}/5</div>
                                        @endif
                                        @if($record->demo_practiced)
                                            <div class="readiness-muted mb-2">Demo practiced: Yes</div>
                                        @endif
                                        @if($record->reflection_note)
                                            <div class="readiness-muted mb-3">{{ $record->reflection_note }}</div>
                                        @endif
                                        <form method="POST" action="{{ route('trader.readiness.update', $item->id) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="reopen">
                                            <button type="submit" class="btn btn-light btn-sm">
                                                <i class="ri-arrow-go-back-line"></i> Reopen
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('trader.readiness.update', $item->id) }}">
                                            @csrf
                                            <input type="hidden" name="status" value="complete">

                                            <div class="mb-2">
                                                <label class="form-label">Confidence</label>
                                                <select name="self_rating" class="form-select form-select-sm">
                                                    <option value="">Select</option>
                                                    @for($rating = 1; $rating <= 5; $rating++)
                                                        <option value="{{ $rating }}">{{ $rating }}/5</option>
                                                    @endfor
                                                </select>
                                            </div>

                                            <div class="form-check mb-2">
                                                <input type="checkbox" name="demo_practiced" value="1" class="form-check-input" id="demo_practiced_{{ $item->id }}">
                                                <label class="form-check-label" for="demo_practiced_{{ $item->id }}">Practiced on demo</label>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Reflection note</label>
                                                <textarea name="reflection_note" class="form-control" rows="3" placeholder="Your rule, example, or lesson"></textarea>
                                            </div>

                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="ri-check-line"></i> Mark Complete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            @if($item->suggested_action)
                                <div class="readiness-note">
                                    <strong>Suggested action:</strong> {{ $item->suggested_action }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</div>

@endsection
