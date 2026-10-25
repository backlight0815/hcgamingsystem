@extends('admin.admin_master')
@section('admin')

<title>Final Exam Question Bank | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="mb-1 contrast-dark-title">Final Exam Question Bank</h4>
                <p class="text-muted mb-0">Manage the onboarding Final Examination separately from the daily exam.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.final_exam.attempts.index') }}" class="btn btn-light border">
                    <i class="ri-file-list-3-line me-1"></i> Attempts
                </a>
                <a href="{{ route('admin.final_exam.settings') }}" class="btn btn-light border">
                    <i class="ri-settings-3-line me-1"></i> Settings
                </a>
                <a href="{{ route('admin.final_exam.questions.export') }}" class="btn btn-light border">
                    <i class="ri-download-line me-1"></i> Export
                </a>
                <a href="{{ route('admin.final_exam.questions.create') }}" class="btn btn-primary">
                    <i class="ri-add-line me-1"></i> Add Question
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-2"><div class="card h-100"><div class="card-body"><span class="text-muted">Total</span><h4 class="mb-0">{{ $metrics['total'] }}</h4></div></div></div>
            <div class="col-md-2"><div class="card h-100"><div class="card-body"><span class="text-muted">Active</span><h4 class="mb-0">{{ $metrics['active'] }}</h4></div></div></div>
            <div class="col-md-2"><div class="card h-100"><div class="card-body"><span class="text-muted">Inactive</span><h4 class="mb-0">{{ $metrics['inactive'] }}</h4></div></div></div>
            <div class="col-md-2"><div class="card h-100"><div class="card-body"><span class="text-muted">Attempts</span><h4 class="mb-0">{{ $metrics['attempts'] }}</h4></div></div></div>
            <div class="col-md-2"><div class="card h-100"><div class="card-body"><span class="text-muted">Passed</span><h4 class="mb-0">{{ $metrics['passed'] }}</h4></div></div></div>
            <div class="col-md-2"><div class="card h-100"><div class="card-body"><span class="text-muted">Failed</span><h4 class="mb-0">{{ $metrics['failed'] }}</h4></div></div></div>
            <div class="col-md-2"><div class="card h-100"><div class="card-body"><span class="text-muted">Chart Qs</span><h4 class="mb-0">{{ $metrics['chart_questions'] }}</h4></div></div></div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.final_exam.questions.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category }}" {{ ($filters['category'] ?? '') === $category ? 'selected' : '' }}>{{ $category }} ({{ $categoryCounts[$category] ?? 0 }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Difficulty</label>
                        <select name="difficulty" class="form-select">
                            <option value="">All</option>
                            @foreach($difficulties as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['difficulty'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Question Type</label>
                        <select name="question_type" class="form-select">
                            <option value="">All</option>
                            @foreach($questionTypes as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['question_type'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label">Subcategory</label>
                        <input name="subcategory" class="form-control" value="{{ $filters['subcategory'] ?? '' }}">
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input name="search" class="form-control" value="{{ $filters['search'] ?? '' }}">
                            <button class="btn btn-primary" type="submit"><i class="ri-search-line"></i></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.final_exam.questions.import') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                    @csrf
                    <div class="col-lg-8">
                        <label class="form-label">Import CSV or JSON</label>
                        <input type="file" name="questions_file" class="form-control" accept=".csv,.txt,.json" required>
                        <div class="text-muted small mt-1">Headers support category, subcategory, question_type, scenario_text, chart_data_json, chart_template_key, chart_timeframe, options A-D, answer, explanation, difficulty, status.</div>
                    </div>
                    <div class="col-lg-4">
                        <button type="submit" class="btn btn-secondary w-100">
                            <i class="ri-upload-cloud-line me-1"></i> Import Questions
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 32%;">Question</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Difficulty</th>
                                <th>Answer</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($questions as $question)
                                <tr>
                                    <td>
                                        <strong>{{ \Illuminate\Support\Str::limit($question->question, 140) }}</strong>
                                        @if($question->subcategory)
                                            <div class="small text-muted">{{ $question->subcategory }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $question->category }}
                                        @if($question->chart_timeframe)
                                            <div class="small text-muted">{{ $question->chart_timeframe }} / {{ $question->chart_template_key }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $question->questionTypeLabel() }}</td>
                                    <td>{{ $question->difficultyLabel() }}</td>
                                    <td>{{ $question->correct_answer }}</td>
                                    <td><span class="badge bg-{{ $question->status === 'active' ? 'success' : 'secondary' }}">{{ $question->statusLabel() }}</span></td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="{{ route('admin.final_exam.questions.edit', $question) }}" class="btn btn-sm btn-info">Edit</a>
                                            <form method="POST" action="{{ route('admin.final_exam.questions.toggle_status', $question) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-warning">{{ $question->status === 'active' ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.final_exam.questions.destroy', $question) }}" onsubmit="return confirm('Delete or deactivate this question?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No questions found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $questions->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
</div>

@endsection
