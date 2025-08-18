<div>
    <p><strong>Username:</strong> {{ $order->user->username }}</p>
    <p><strong>Total Amount:</strong> RM {{ $order->total_amount }}</p>
    <p><strong>Stocks:</strong> {{ $order->orderItems->sum('quantity') }}</p>
    <p><strong>Status:</strong>
        @if($order->status == 0)
            Processing
        @elseif($order->status == 1)
            Confirmed
        @elseif($order->status == 2)
            Delivery
        @elseif($order->status == 3)
            Completed
        @elseif($order->status == -1)
            Rejected
        @endif
    </p>
    <p><strong>Transaction Date:</strong> {{ $order->created_at }}</p>

    <h6>Order Items:</h6>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Product Name</th>
                <th>Quantity</th>
                <th>Price</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->orderItems as $item)
            <tr>
                <td>{{ $item->product->product_name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>RM {{ $item->product_price }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
