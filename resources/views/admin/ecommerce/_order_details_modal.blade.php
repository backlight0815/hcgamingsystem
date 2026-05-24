<div id="orderDetailsModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailsModalLabel">Order Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <div class="commerce-empty">Loading order items...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('load', function () {
        var detailUrlTemplate = @json(route('admin.order.items', ['orderId' => '__ORDER_ID__']));

        $(document).on('click', '.view-order-details', function () {
            var orderId = $(this).data('id');

            $('#orderDetailsContent').html('<div class="commerce-empty">Loading order items...</div>');
            $('#orderDetailsModal').modal('show');

            $.ajax({
                url: detailUrlTemplate.replace('__ORDER_ID__', orderId),
                method: 'GET',
                success: function (response) {
                    var items = response.order_items || [];
                    var htmlContent = '<div class="mb-3"><strong>Order #' + response.id + '</strong></div>';

                    if (!items.length) {
                        htmlContent += '<div class="commerce-empty">No product items found for this order.</div>';
                    } else {
                        htmlContent += '<div class="commerce-order-items">';
                        items.forEach(function (orderItem) {
                            var product = orderItem.product || {};
                            var productImage = product.product_image ? @json(asset('')) + product.product_image : @json(asset('upload/default.jpg'));
                            var productName = product.product_name || 'Product unavailable';
                            htmlContent += '<div class="commerce-order-item">';
                            htmlContent += '<img src="' + productImage + '" alt="' + productName.replace(/"/g, '&quot;') + '">';
                            htmlContent += '<div><strong>' + productName + '</strong><div class="commerce-muted">Quantity: ' + orderItem.quantity + '</div></div>';
                            htmlContent += '</div>';
                        });
                        htmlContent += '</div>';
                    }

                    $('#orderDetailsContent').html(htmlContent);
                },
                error: function () {
                    $('#orderDetailsContent').html('<div class="alert alert-danger mb-0">Unable to load order details right now.</div>');
                }
            });
        });
    });
</script>
