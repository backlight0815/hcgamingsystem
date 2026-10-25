@extends('frontend.main_master')
@section('title', 'Shop | HC Gaming')
@section('meta_description', 'Explore products from HC Gaming.')
@section('main')
<div class="es-page">
    <header class="es-page-hero es-shop-hero"><div class="es-shell"><nav class="es-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('store.home') }}">Home</a><span>/</span><span>Shop</span></nav><span class="es-eyebrow">The collection</span><h1 class="es-title">Explore the shop.</h1><p class="es-lead">Browse the available products from HC Gaming.</p></div></header>
    <section class="es-section es-product-section es-shop-section"><div class="es-shell">
        <form class="es-shop-controls" method="GET" action="{{ route('home.product') }}">
            <label><span>Category</span><select class="es-field" name="category"><option value="">All products</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->product_category }}</option>@endforeach</select></label>
            <label><span>Search</span><input class="es-field" name="q" type="search" maxlength="80" value="{{ $search }}" placeholder="Product name"></label>
            <label><span>Sort by</span><select class="es-field" name="sort"><option value="" @selected(!$sort)>Newest</option><option value="price_asc" @selected($sort === 'price_asc')>Price: low to high</option><option value="price_desc" @selected($sort === 'price_desc')>Price: high to low</option></select></label>
            <button class="es-button" type="submit">Apply filters</button>
        </form>
        <div class="es-archive-head"><h2>Products</h2><span>{{ $product->total() }} {{ \Illuminate\Support\Str::plural('item', $product->total()) }}</span></div>
        @if($product->isNotEmpty())<div class="es-product-grid">@foreach($product as $item)@include('frontend.partials.product_card')@endforeach</div><div class="es-pagination">{{ $product->links('pagination::bootstrap-5') }}</div>
        @else<div class="es-empty"><h2>No products found.</h2><p>Try another search or browse the full collection.</p><a class="es-button" href="{{ route('home.product') }}">All products</a></div>@endif
    </div></section>
</div>
@endsection
