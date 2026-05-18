@extends('admin.admin_master')
@section('admin')

<title>Open Support Ticket | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Open Support Ticket</h4>
                    <a href="{{ route('support.tickets.index') }}" class="btn btn-secondary">Back</a>
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

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <form action="{{ route('support.tickets.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                <input type="text" name="subject" id="subject" class="form-control" value="{{ old('subject') }}" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                    <select name="category" id="category" class="form-select" required>
                                        <option value="trading" {{ old('category') === 'trading' ? 'selected' : '' }}>Trading Matter</option>
                                        <option value="signal" {{ old('category') === 'signal' ? 'selected' : '' }}>Signal / Market Analysis</option>
                                        <option value="website" {{ old('category') === 'website' ? 'selected' : '' }}>Website System Issue</option>
                                        <option value="account" {{ old('category') === 'account' ? 'selected' : '' }}>Account / Verification</option>
                                        <option value="other" {{ old('category') === 'other' ? 'selected' : '' }}>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select name="priority" id="priority" class="form-select" required>
                                        @foreach($priorities as $value => $label)
                                            <option value="{{ $value }}" {{ old('priority', 'medium') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label">Question / Issue <span class="text-danger">*</span></label>
                                <textarea name="message" id="message" rows="7" class="form-control" required>{{ old('message') }}</textarea>
                            </div>

                            <div class="mb-4">
                                <label for="attachments" class="form-label">Images / Files</label>
                                <input type="file"
                                       name="attachments[]"
                                       id="attachments"
                                       class="form-control"
                                       accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.zip"
                                       multiple>
                                <div class="form-text">Optional. Add up to 5 screenshots or files, 20 MB each.</div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">Open Ticket</button>
                                <a href="{{ route('support.tickets.index') }}" class="btn btn-light">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
