@extends('admin.admin_master')
@section('admin')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>

<title>News Management - Add | HC Gaming Studio</title>

<div class="page-content">
    <div class="container-fluid">

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body">

                        <h4 class="card-title mb-4">Add News</h4>

                        <form method="POST" id="submitNews" action="{{ route('trading.news.store') }}" enctype="multipart/form-data">
                            @csrf

                            <!-- Community Selection -->
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Community</label>
                                <div class="col-sm-10">
                                    <select name="community_id" class="form-select">
                                        <option value="">Select Community</option>
                                        @foreach($communities as $community)
                                            <option value="{{ $community->id }}" {{ old('community_id') == $community->id ? 'selected' : '' }}>
                                                {{ $community->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('community_id')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- News Date -->
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">News Date</label>
                                <div class="col-sm-10">
                                    <input type="date" name="news_date" class="form-control" value="{{ old('news_date') }}">
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
                                        <option value="1" {{ old('impact') == 1 ? 'selected' : '' }}>Low</option>
                                        <option value="2" {{ old('impact') == 2 ? 'selected' : '' }}>Medium</option>
                                        <option value="3" {{ old('impact') == 3 ? 'selected' : '' }}>High</option>
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
                                    <img id="showImages" src="{{ asset('upload/default.jpg') }}" alt="News Image Preview" style="width:100px; height:100px; margin-top:10px; border-radius:6px;">
                                    @error('image')
                                    <span class="text-danger">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>

                            <!-- Auto-generated Content -->
                            <div class="row mb-3">
                                <label class="col-sm-2 col-form-label">Content</label>
                                <div class="col-sm-10">
                                    <textarea id="content" class="form-control" >{{ old('content') }}</textarea>
                                    <small class="text-muted">Content will auto-generate based on selected impact.</small>
                                </div>
                            </div>

                            <input type="submit" class="btn btn-info waves-effect waves-light" id="submitButton" value="Add News" onclick="disableButton()">

                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<script type="text/javascript">
$(document).ready(function(){
    // Disable button after submit
    var formSubmitted = false;
    $('#submitButton').click(function(e){
        if(formSubmitted) return false;
        formSubmitted = true;
        $(this).prop('disabled', true).val('Submitting...');
        setTimeout(function(){
            $('#submitNews').submit();
        }, 500);
    });

    // Image preview
    $('#image').change(function(e){
        var reader = new FileReader();
        reader.onload = function(e){
            $('#showImages').attr('src', e.target.result);
        }
        reader.readAsDataURL(e.target.files[0]);
    });

    // Auto-generate content in Chinese based on impact
    function generateContent(){
        var impact = $('#impact').val();
        var content = '';

        if(impact){
            switch(impact){
                case '1': // Low
                    content = "📰 **新闻影响力:** 低\n\n" +
                              "💡 **交易建议:** 市场波动较小，建议小仓位操作，避免过度杠杆。\n\n" +
                              "📈 **预期行情动量:** 行情动量有限，价格波动较缓慢。\n\n" +
                              "⚠️ **温馨提醒:** 做交易之前，请谨慎评估当天新闻，别因为新闻破坏交易计划，务必做好风险管理。";
                    break;
                case '2': // Medium
                    content = "📰 **新闻影响力:** 中\n\n" +
                              "💡 **交易建议:** 预期中等波动，关注关键支撑/阻力位，设置合理止损。\n\n" +
                              "📈 **预期行情动量:** 可能出现中等动量，需密切关注趋势方向。\n\n" +
                              "⚠️ **温馨提醒:** 做交易之前，请谨慎评估当天新闻，别因为新闻破坏交易计划，务必做好风险管理。";
                    break;
                case '3': // High
                    content = "📰 **新闻影响力:** 高\n\n" +
                              "💡 **交易建议:** 市场可能剧烈波动，重点关注重要价位，严格风险管理。\n\n" +
                              "📈 **预期行情动量:** 强劲动量可能出现，价格快速变动风险高。\n\n" +
                              "⚠️ **温馨提醒:** 做交易之前，请谨慎评估当天新闻，别因为新闻破坏交易计划，务必做好风险管理。";
                    break;
                default:
                    content = '';
            }
        }
        $('#content').val(content);
    }

    // Trigger generation when impact changes
    $('#impact').change(generateContent);

    // Trigger on page load (edit page)
    generateContent();
});
</script>



@endsection
