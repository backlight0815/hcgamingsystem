@extends('admin.admin_master')
@section('admin')

<title>Final Exam Settings | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="mb-1 contrast-dark-title">Final Exam Settings</h4>
                <p class="text-muted mb-0">Configure pass mark, retake rules, and result review visibility.</p>
            </div>
            <a href="{{ route('admin.final_exam.questions.index') }}" class="btn btn-light border">
                <i class="ri-arrow-left-line me-1"></i> Question Bank
            </a>
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

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.final_exam.settings.update') }}">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Passing Percentage</label>
                                    <input type="number" name="passing_percentage" min="1" max="100" class="form-control" value="{{ old('passing_percentage', $setting->passing_percentage) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Retake Cooldown Hours</label>
                                    <input type="number" name="retake_cooldown_hours" min="1" max="720" class="form-control" value="{{ old('retake_cooldown_hours', $setting->retake_cooldown_hours) }}" required>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" {{ old('is_active', $setting->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">Final Exam Active</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="allow_immediate_retake" value="1" class="form-check-input" id="allow_immediate_retake" {{ old('allow_immediate_retake', $setting->allow_immediate_retake) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="allow_immediate_retake">Allow Immediate Retake</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="show_review_after_submission" value="1" class="form-check-input" id="show_review_after_submission" {{ old('show_review_after_submission', $setting->show_review_after_submission) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="show_review_after_submission">Show Review After Submission</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="existing_users_passed_by_default" value="1" class="form-check-input" id="existing_users_passed_by_default" {{ old('existing_users_passed_by_default', $setting->existing_users_passed_by_default) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="existing_users_passed_by_default">Existing Users Passed By Default</label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ri-save-line me-1"></i> Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Exam Structure</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered mb-0">
                                <tbody>
                                    @foreach($requiredCounts as $category => $count)
                                        <tr>
                                            <td>{{ $category }}</td>
                                            <td class="text-end">{{ $count }}</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <th>Total</th>
                                        <th class="text-end">{{ array_sum($requiredCounts) }}</th>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            At 60%, the default passing score is 24/40.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
