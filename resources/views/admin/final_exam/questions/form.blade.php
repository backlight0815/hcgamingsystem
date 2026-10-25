@extends('admin.admin_master')
@section('admin')

<title>{{ $mode === 'create' ? 'Add' : 'Edit' }} Final Exam Question | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="mb-1 contrast-dark-title">{{ $mode === 'create' ? 'Add' : 'Edit' }} Final Exam Question</h4>
                <p class="text-muted mb-0">Questions are used only for the onboarding Final Examination.</p>
            </div>
            <a href="{{ route('admin.final_exam.questions.index') }}" class="btn btn-light border">
                <i class="ri-arrow-left-line me-1"></i> Question Bank
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" action="{{ $mode === 'create' ? route('admin.final_exam.questions.store') : route('admin.final_exam.questions.update', $question) }}">
                    @csrf
                    @if($mode !== 'create')
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                @foreach($categories as $category)
                                    <option value="{{ $category }}" {{ old('category', $question->category) === $category ? 'selected' : '' }}>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Subcategory</label>
                            <input name="subcategory" class="form-control" value="{{ old('subcategory', $question->subcategory) }}">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Question Type</label>
                            <select name="question_type" class="form-select" required>
                                @foreach($questionTypes as $value => $label)
                                    <option value="{{ $value }}" {{ old('question_type', $question->question_type ?: 'text_mcq') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Difficulty</label>
                            <select name="difficulty" class="form-select" required>
                                @foreach($difficulties as $value => $label)
                                    <option value="{{ $value }}" {{ old('difficulty', $question->difficulty ?: 'beginner') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                @foreach($statuses as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', $question->status ?: 'active') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Question</label>
                            <textarea name="question" class="form-control" rows="4" required>{{ old('question', $question->question) }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Scenario Text</label>
                            <textarea name="scenario_text" class="form-control" rows="3">{{ old('scenario_text', $question->scenario_text) }}</textarea>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Chart Timeframe</label>
                            <input name="chart_timeframe" class="form-control" value="{{ old('chart_timeframe', $question->chart_timeframe) }}" placeholder="M5, M15, H1">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Chart Template Key</label>
                            <input name="chart_template_key" class="form-control" value="{{ old('chart_template_key', $question->chart_template_key) }}">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Chart Image</label>
                            <input type="file" name="chart_image" class="form-control" accept="image/*">
                            @if($question->chart_image_path)
                                <div class="small text-muted mt-1">Current: {{ $question->chart_image_path }}</div>
                                <input type="hidden" name="chart_image_path" value="{{ $question->chart_image_path }}">
                            @endif
                        </div>
                        <div class="col-12">
                            <label class="form-label">Chart Data JSON</label>
                            <textarea name="chart_data_json" class="form-control" rows="6" placeholder='{"symbol":"XAUUSD","candles":[...],"annotations":[...]}'>{{
                                old('chart_data_json', $question->chart_data_json ? json_encode($question->chart_data_json, JSON_PRETTY_PRINT) : '')
                            }}</textarea>
                            <div class="text-muted small mt-1">Required for chart questions unless a chart image is uploaded.</div>
                        </div>
                        @foreach(['a', 'b', 'c', 'd'] as $letter)
                            <div class="col-lg-6">
                                <label class="form-label">Option {{ strtoupper($letter) }}</label>
                                <textarea name="option_{{ $letter }}" class="form-control" rows="2" required>{{ old('option_' . $letter, $question->{'option_' . $letter}) }}</textarea>
                            </div>
                        @endforeach
                        <div class="col-lg-3">
                            <label class="form-label">Correct Answer</label>
                            <select name="correct_answer" class="form-select" required>
                                @foreach(['A', 'B', 'C', 'D'] as $letter)
                                    <option value="{{ $letter }}" {{ old('correct_answer', $question->correct_answer) === $letter ? 'selected' : '' }}>{{ $letter }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Explanation</label>
                            <textarea name="explanation" class="form-control" rows="4">{{ old('explanation', $question->explanation) }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('admin.final_exam.questions.index') }}" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ri-save-line me-1"></i> Save Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
