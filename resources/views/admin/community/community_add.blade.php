@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
<!-- Bootstrap Tags Input -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.min.js"></script>

<style>
    .bootstrap-tagsinput {
        width: 100% !important;
        min-height: 50px;
    }
    .bootstrap-tagsinput .tag {
        margin-right: 2px;
        color: #b70000;
        font-weight: 700;
    }
</style>

<title>Add Community | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb bg-light p-2 rounded">
                @foreach ($breadcrumbData ?? [] as $breadcrumb)
                    <li class="breadcrumb-item">
                        @if($breadcrumb['url'])
                            <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['label'] }}</a>
                        @else
                            {{ $breadcrumb['label'] }}
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Add New Community</h4>
                    </div>
                    <div class="card-body p-4">

                        <form method="POST" id="submitCommunity" action="{{ route('communities.store') }}">
                            @csrf

                            {{-- Community Name --}}
                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold">Community Name</label>
                                <input name="name" id="name" class="form-control form-control-lg" type="text" 
                                       placeholder="Enter community name" value="{{ old('name') }}">
                                @error('name')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

  <div class="mb-3">
    <label for="community_tag" class="form-label fw-semibold">Community Tag (Optional)</label>
    <input type="text" name="community_tag" id="community_tag" class="form-control"
       value="{{ request('community_tag') ?? '' }}" placeholder="Enter tags">
    @error('community_tag')
        <div class="text-danger mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- Community Category --}}
<div class="mb-3">
    <label for="category" class="form-label fw-semibold">Community Category</label>
    <select name="category" id="category" class="form-control form-control-lg">
        <option value="" disabled selected>Select category</option>
        <option value="public" {{ old('category') == 'public' ? 'selected' : '' }}>Public Community</option>
        <option value="executive" {{ old('category') == 'executive' ? 'selected' : '' }}>Executive Community</option>
                <option value="test" {{ old('category') == 'test' ? 'selected' : '' }}>Testing Purpose Only</option>

    </select>
    @error('category')
        <div class="text-danger mt-1">{{ $message }}</div>
    @enderror
</div>

                            {{-- Discord Webhook --}}
                            <div class="mb-3">
                                <label for="discord_webhook" class="form-label fw-semibold">Discord Trade Call API</label>
                                <input name="discord_webhook" id="discord_webhook" class="form-control form-control-lg" 
                                       type="text" placeholder="https://discord.com/api/webhooks/..." 
                                       value="{{ old('discord_webhook') }}">
                                @error('discord_webhook')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                         
  {{-- Discord Webhook Signal --}}
                            <div class="mb-3">
                                <label for="discord_webhook_signal" class="form-label fw-semibold">Discord Signal Result API</label>
                                <input name="discord_webhook_signal" id="discord_webhook_signal" class="form-control form-control-lg" 
                                       type="text" placeholder="https://discord.com/api/webhooks/..." 
                                       value="{{ old('discord_webhook_signal') }}">
                                @error('discord_webhook_signal')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
  {{-- Discord Webhook Outlook --}}
                            <div class="mb-3">
                                <label for="discord_webhook_outlook" class="form-label fw-semibold">Discord Webhook Outlook API</label>
                                <input name="discord_webhook_outlook" id="discord_webhook_outlook" class="form-control form-control-lg" 
                                       type="text" placeholder="https://discord.com/api/webhooks/..." 
                                       value="{{ old('discord_webhook_outlook') }}">
                                @error('discord_webhook_outlook')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>
  {{-- Discord Webhook Knowledge --}}
                            <div class="mb-3">
                                <label for="discord_webhook_knowledge" class="form-label fw-semibold">Discord Knowlegde API</label>
                                <input name="discord_webhook_knowledge" id="discord_webhook_outlook" class="form-control form-control-lg" 
                                       type="text" placeholder="https://discord.com/api/webhooks/..." 
                                       value="{{ old('discord_webhook_knowledge') }}">
                                @error('discord_webhook_knowledge')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

  {{-- Discord Webhook News --}}
                            <div class="mb-3">
                                <label for="discord_webhook_news" class="form-label fw-semibold">Discord News API</label>
                                <input name="discord_webhook_news" id="discord_webhook_outlook" class="form-control form-control-lg" 
                                       type="text" placeholder="https://discord.com/api/webhooks/..." 
                                       value="{{ old('discord_webhook_news') }}">
                                @error('discord_webhook_news')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Submit Button --}}
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitButton" onclick="disableButton()">
                                    <i class="bi bi-plus-circle me-2"></i> Add Community
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    $(document).ready(function() {
    $('#community_tag').tagsinput(); // Initialize the plugin
});
var formSubmitted = false;

function disableButton() {
    if (formSubmitted) return false;

    var submitButton = document.getElementById('submitButton');
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Submitting...';

    setTimeout(function () {
        document.getElementById('submitCommunity').submit();
    }, 500);

    formSubmitted = true;
    return true;
}
</script>

{{-- Bootstrap Icons CDN --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

@endsection
