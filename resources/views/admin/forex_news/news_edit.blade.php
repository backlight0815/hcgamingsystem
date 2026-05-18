@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body">

                        <h4 class="card-title mb-4">Edit News</h4>

                        <form method="POST" action="{{ route('trading.news.update', $news->id) }}" enctype="multipart/form-data">
                            @csrf

                            <!-- News Date -->
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">News Date</label>
                                <div class="col-sm-10">
                                    <input type="date" name="news_date" class="form-control" value="{{ old('news_date', $news->news_date) }}">
                                    @error('news_date')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Impact -->
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Impact</label>
                                <div class="col-sm-10">
                                    <select name="impact" id="impact" class="form-select">
                                        <option value="">Select Impact</option>
                                        <option value="1" {{ old('impact', $news->impact) == 1 ? 'selected' : '' }}>Low</option>
                                        <option value="2" {{ old('impact', $news->impact) == 2 ? 'selected' : '' }}>Medium</option>
                                        <option value="3" {{ old('impact', $news->impact) == 3 ? 'selected' : '' }}>High</option>
                                    </select>
                                    @error('impact')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Image Upload -->
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Image</label>
                                <div class="col-sm-10">
                                    <input type="file" name="image" id="image" class="form-control">
                                    <img id="showImages" src="{{ $news->image ? asset($news->image) : asset('upload/default.jpg') }}" 
                                         alt="News Image Preview" style="width:100px; height:100px; margin-top:10px; border-radius:6px;">
                                    @error('image')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Auto-generated Content -->
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Content</label>
                                <div class="col-sm-10">
                                    <textarea id="content" name="content" class="form-control" readonly>{{ old('content', $news->content) }}</textarea>
                                    <small class="text-muted">Content will auto-update based on selected impact.</small>
                                </div>
                            </div>

                            <input type="submit" class="btn btn-info waves-effect waves-light" value="Update News">

                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    // Image preview
    $('#image').change(function(e){
        var reader = new FileReader();
        reader.onload = function(e){
            $('#showImages').attr('src', e.target.result);
        }
        reader.readAsDataURL(e.target.files[0]);
    });

    // Auto-update content based on impact
    $('#impact').change(function(){
        var impactText = $('#impact option:selected').text();
        if(impactText) {
            $('#content').val("Hi guys, today have " + impactText + " impact news for USD.");
        } else {
            $('#content').val('');
        }
    });
});
</script>

@endsection
