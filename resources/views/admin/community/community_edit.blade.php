@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<!-- Bootstrap Tags Input CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-tagsinput/0.8.0/bootstrap-tagsinput.css">
<!-- Bootstrap Tags Input JS -->
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

<div class="page-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">

                        <h4 class="card-title mb-4">Edit Community</h4>

                        <form method="POST" action="{{ route('communities.update', $community->id) }}">
                            @csrf

                          {{-- Community Tag --}}
<div class="mb-3">
    <label for="community_tag" class="form-label fw-semibold">Community Tag (Optional)</label>
    <input 
        type="text" 
        name="community_tag" 
        id="community_tag" 
        class="form-control"
        placeholder="Enter tags" 
        value="{{ old('community_tag', $community->community_tag ?? '') }}"
    >
    @error('community_tag')
        <div class="text-danger mt-1">{{ $message }}</div>
    @enderror
</div>


                            {{-- Community Name --}}
                            <div class="mb-3">
                                <label for="name" class="form-label fw-semibold">Community Name</label>
                                <input 
                                    type="text" 
                                    name="name" 
                                    class="form-control" 
                                    value="{{ old('name', $community->name) }}"
                                >
                                @error('name')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Discord Webhooks --}}
                            @foreach(['discord_webhook','discord_webhook_signal','discord_webhook_outlook','discord_webhook_knowledge','discord_webhook_news'] as $webhook)
                                <div class="mb-3">
                                    <label for="{{ $webhook }}" class="form-label fw-semibold">{{ ucwords(str_replace('_',' ',$webhook)) }}</label>
                                    <input 
                                        type="text" 
                                        name="{{ $webhook }}" 
                                        class="form-control" 
                                        value="{{ old($webhook, $community->$webhook) }}"
                                    >
                                    @error($webhook)
                                        <div class="text-danger mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach

                            

                            {{-- Community Category --}}
                            <div class="mb-3">
                                <label for="category" class="form-label fw-semibold">Community Category</label>
                                <select name="category" class="form-select">
                                    <option value="public" {{ old('category', $community->category) == 'public' ? 'selected' : '' }}>Public Community</option>
                                    <option value="executive" {{ old('category', $community->category) == 'executive' ? 'selected' : '' }}>Executive Community</option>
                                    <option value="test" {{ old('category', $community->category) == 'test' ? 'selected' : '' }}>Testing Purpose Only</option>
                                </select>
                                @error('category')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Status --}}
                            <div class="mb-3">
                                <label for="status" class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="1" {{ old('status', $community->status) == 1 ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ old('status', $community->status) == 0 ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')
                                    <div class="text-danger mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-info">Update Community</button>
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
</script>

@endsection
