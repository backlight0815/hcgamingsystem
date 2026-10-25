<article class="es-product-card">
    <a class="es-product-media" href="{{ route('stock.details', $item->id) }}" aria-label="View {{ $item->product_name }}">
        @if($item->product_image && is_file(public_path($item->product_image)))<img src="{{ asset($item->product_image) }}" alt="{{ $item->product_name }}" loading="lazy" width="520" height="520">@else<span class="es-product-placeholder" aria-hidden="true">HC</span>@endif
        @if($item->product_stock !== null && $item->product_stock <= 0)<span class="es-product-status">Sold out</span>@endif
    </a>
    <div class="es-product-info">
        @if($item->productcategory && !$item->productcategory->trashed())<span class="es-product-category">{{ $item->productcategory->product_category }}</span>@endif
        <h3 class="es-product-name"><a href="{{ route('stock.details', $item->id) }}">{{ $item->product_name }}</a></h3>
        @if($item->product_price !== null)<div class="es-product-price">RM {{ number_format($item->product_price, 2) }}</div>@endif
        <a class="es-text-link" href="{{ route('stock.details', $item->id) }}">View product <span aria-hidden="true">↗</span></a>
    </div>
</article>
