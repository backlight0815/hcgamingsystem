@extends('admin.admin_master')
@section('admin')

<title>Edit Trading Exam Question | HC Gaming Studio</title>

@php
    $existingOptions = old('options', $question->options->pluck('option_text')->all());
    $optionCount = max(4, count($existingOptions));
    $correctIndex = old('correct_option', max(0, ((int) optional($question->options->firstWhere('is_correct', true))->position) - 1));
@endphp

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="mb-sm-0">Edit Trading Exam Question</h4>
                        <p class="text-muted mb-0">{{ $isAdmin ? 'Admin edits are published immediately.' : 'Leader edits return to administration review.' }}</p>
                    </div>
                    <a href="{{ route('admin.trading.exams.index') }}" class="btn btn-secondary">Back</a>
                </div>
            </div>
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
                <form method="POST" action="{{ route('admin.trading.exams.questions.update', $question->id) }}">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" class="form-control" value="{{ old('category', $question->category) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Difficulty / Topic</label>
                            <select name="difficulty" class="form-select" required>
                                @foreach($difficulties as $value => $label)
                                    <option value="{{ $value }}" {{ old('difficulty', $question->difficulty) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Question</label>
                        <textarea name="question_text" rows="4" class="form-control" required>{{ old('question_text', $question->question_text) }}</textarea>
                    </div>
                    <div class="row">
                        @for($i = 0; $i < $optionCount; $i++)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Option {{ chr(65 + $i) }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <input type="radio" name="correct_option" value="{{ $i }}" {{ (string) $correctIndex === (string) $i ? 'checked' : '' }} title="Correct answer">
                                    </span>
                                    <input type="text" name="options[]" class="form-control" value="{{ $existingOptions[$i] ?? '' }}" {{ $i < 2 ? 'required' : '' }}>
                                </div>
                            </div>
                        @endfor
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Answer Explanation</label>
                        <textarea name="explanation" rows="4" class="form-control">{{ old('explanation', $question->explanation) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-info">Update Question</button>
                    <a href="{{ route('admin.trading.exams.index') }}" class="btn btn-light">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
