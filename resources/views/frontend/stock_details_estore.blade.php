@extends('frontend.main_master')
@section('title', $stock->product_name.' | HC Gaming')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($stock->long_description ?? $stock->product_name), 155))
@section('main')
<div class="es-page">
    <div class="es-shell es-detail-breadcrumb"><nav class="es-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('store.home') }}">Home</a><span>/</span><a href="{{ route('home.product') }}">Shop</a><span>/</span><span>Product</span></nav></div>
    <section class="es-shell es-product-detail">
        <div class="es-detail-image">@if($stock->product_image && is_file(public_path($stock->product_image)))<img src="{{ asset($stock->product_image) }}" alt="{{ $stock->product_name }}" width="850" height="850">@else<span class="es-product-placeholder">HC</span>@endif</div>
        <div class="es-detail-copy"><span class="es-eyebrow">@if($stock->productcategory && !$stock->productcategory->trashed()){{ $stock->productcategory->product_category }}@else HC Gaming collection @endif</span><h1 @if(mb_strlen($stock->product_name ?? '') > 70) class="es-detail-title-long" @endif>{{ $stock->product_name }}</h1><p class="es-detail-price">RM {{ number_format($stock->product_price, 2) }}</p>
            <p class="es-detail-stock">{{ $stock->product_stock > 0 ? 'In stock · '.$stock->product_stock.' available' : 'Currently out of stock' }}</p>
            @if($errors->any())<div class="es-form-error" role="alert">{{ $errors->first() }}</div>@endif
            @if($stock->product_stock > 0)<form class="es-detail-cart" action="{{ auth()->check() ? route('cart.add') : route('guest.cart.add') }}" method="POST">@csrf<input type="hidden" name="product_id" value="{{ $stock->id }}">@auth<input type="hidden" name="submission_type" value="add-to-cart">@endauth<label for="quantity">Quantity</label><input class="es-field" id="quantity" type="number" name="quantity" value="1" min="1" max="{{ $stock->product_stock }}" required><button class="es-button" type="submit">Add to cart <span aria-hidden="true">↗</span></button></form>@endif
            <a class="es-text-link" href="{{ auth()->check() ? route('cart.summary') : route('guest.cart.summary') }}">View cart <span aria-hidden="true">↗</span></a>
            @if($stock->sku)<p class="es-detail-small">SKU: {{ $stock->sku }}</p>@endif
        </div>
    </section>
    @if($stock->long_description)<section class="es-section es-product-description"><div class="es-shell"><span class="es-eyebrow">Details</span><h2 class="es-title">About this product.</h2><div class="es-richtext">{!! \App\Support\StorefrontHtml::clean($stock->long_description) !!}</div></div></section>@endif
</div>
@endsection
