@extends('frontend.main_master')
@section('title', 'Your cart | HC Gaming')
@section('main')
<div class="es-page"><header class="es-page-hero"><div class="es-shell"><nav class="es-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('store.home') }}">Home</a><span>/</span><span>Cart</span></nav><span class="es-eyebrow">Your selection</span><h1 class="es-title">Your cart.</h1></div></header>
<section class="es-section"><div class="es-shell">@if($allCartItems->isNotEmpty())
    <div class="es-cart-items">@php($total = 0)@foreach($allCartItems as $item)@if($item->product)@php($total += $item->product->product_price * $item->quantity)<article class="es-cart-item">@if($item->product->product_image)<img src="{{ asset($item->product->product_image) }}" alt="" width="110" height="110">@endif<div><h2>{{ $item->product->product_name }}</h2><p>Quantity: {{ $item->quantity }}</p><a class="es-text-link" href="{{ route('stock.details', $item->product->id) }}">View product</a></div><strong>RM {{ number_format($item->product->product_price * $item->quantity, 2) }}</strong></article>@endif @endforeach</div>
    <div class="es-cart-total"><span>Subtotal</span><strong>RM {{ number_format($total, 2) }}</strong></div><p class="es-cart-help">Sign in to continue with checkout and manage your order.</p><a class="es-button" href="{{ route('login') }}">Sign in to continue ↗</a>
@else<div class="es-empty"><h2>Your cart is empty.</h2><p>Explore the collection to find something you like.</p><a class="es-button" href="{{ route('home.product') }}">Explore products</a></div>@endif</div></section></div>
@endsection
