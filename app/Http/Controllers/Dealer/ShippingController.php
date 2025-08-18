<?php

namespace App\Http\Controllers\Dealer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\orders;
use App\Models\CommissionSetting;
use App\Models\Commission;
use App\Models\order_items;
use App\Models\transactions;
use App\Models\Product;
use App\Models\Referral;
use App\Models\DealerStock;
use Log;
class ShippingController extends Controller
{
    public function MyShippingOrders(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Shipping Order', 'url' => route('my.shipping.order')],

        ];
        $userId = Auth::user()->id;

        $ProcessingCount = orders::where('user_id', $userId)->where('status', '0')->count();
        $ApproveCount = orders::where('user_id', $userId)->where('status', '1')->count();
        $DelivertCount = orders::where('user_id', $userId)->where('status', '2')->count();
        $ConmpleteCount = orders::where('user_id', $userId)->where('status', '3')->count();


        $shippingData = orders::where('user_id',$userId)->with('orderItems')->withSum('orderItems', 'quantity')->latest()->get();
        $orderData = orders::where('user_id',$userId)->with('orderItems')->get();
        $orderCount = $orderData->count();


        // $ordersCount = $shippingData->count();

        return view("agent.shipping_order.shipping_all",compact('shippingData','orderCount','ProcessingCount','ApproveCount','DelivertCount','ConmpleteCount','breadcrumbData'));

            }//end method

            public function AllDealerShippingOrders()
            {
                $breadcrumbData = [
                    ['label' => 'HC Gaming', 'url' => route('all.statistics')],
                    ['label' => 'Shipping Order', 'url' => route('all.shipping.order')],
                ];

                // Get the authenticated user's ID (seller's ID)
                $userId = Auth::user()->id;

                // Fetch products owned by the authenticated user (seller)
                $sellerProducts = Product::where('user_id', $userId)->pluck('id');

                // Fetch order items associated with products owned by the seller
                $orderItems = order_items::whereIn('product_id', $sellerProducts)->get();

                // Extract order IDs from the order items
                $orderIds = $orderItems->pluck('order_id')->unique()->toArray();

                // Fetch shipping data for the extracted order IDs
                $shippingData = orders::whereIn('id', $orderIds)
                    ->with('orderItems.product') // Ensure order items and related product are loaded
                    ->withSum('orderItems', 'quantity')
                    ->latest()
                    ->get();

                // Extract the unique product IDs from the order items
                $uniqueProductIds = $orderItems->pluck('product_id')->unique()->toArray();

                // Initialize an array to store unique product names
                $uniqueProductNames = [];

                // Loop through the unique product IDs and fetch their corresponding names
                foreach ($uniqueProductIds as $productId) {
                    // Find the first dealer stock matching the product ID and user ID
                    $dealerStock = DealerStock::where('product_id', $productId)
                        ->where('user_id', $userId)
                        ->first();

                    // If a dealer stock is found, get the product name
                    if ($dealerStock) {
                        $product = $dealerStock->product;
                        // Check if the product name is not already in the array
                        if (!in_array($product->product_name, $uniqueProductNames)) {
                            $uniqueProductNames[] = $product->product_name;
                        }
                    }
                }

                // Now $uniqueProductNames array contains unique product names based on the order items

                // Initialize counts for different statuses
                $ProcessingCount = 0;
                $ApproveCount = 0;
                $DeliveryCount = 0;
                $CompleteCount = 0;

                // Calculate the total order count
                $orderCount = $shippingData->count();


    // Calculate counts based on the status of each order
    foreach ($shippingData as $order) {
        switch ($order->status) {
            case '0':
                $ProcessingCount++;
                break;
            case '1':
                $ApproveCount++;
                break;
            case '2':
                $DeliveryCount++;
                break;
            case '3':
                $CompleteCount++;
                break;
            default:
                break;
        }
    }

                // Load dealer_stock data associated with the product_id
                $dealerStocks = DealerStock::whereIn('product_id', $sellerProducts)->get();

                return view("agent.shipping_order.dealers_shipping_all", compact(
                    'ProcessingCount', 'shippingData', 'ApproveCount', 'DeliveryCount', 'CompleteCount', 'orderCount', 'dealerStocks', 'breadcrumbData'
                ));
            }


            public function AllShippingOrders()
            {
                // Get the authenticated user's ID
                $sellerId = Auth::id();

                // Get the product IDs associated with the seller from order items
                $sellerProductIds = order_items::whereHas('product', function ($query) use ($sellerId) {
                    $query->where('user_id', $sellerId);
                })->pluck('product_id')->unique();

                $breadcrumbData = [
                    ['label' => 'HC Gaming', 'url' => route('all.statistics')],
                    ['label' => 'Shipping Order', 'url' => route('all.shipping.order')],
                ];

                // Count shipping orders in different statuses for the seller's products
                $ProcessingCount = orders::whereIn('id', function ($query) use ($sellerProductIds) {
                    $query->select('order_id')->from('order_items')->whereIn('product_id', $sellerProductIds);
                })->where('status', '0')->count();

                $ApproveCount = orders::whereIn('id', function ($query) use ($sellerProductIds) {
                    $query->select('order_id')->from('order_items')->whereIn('product_id', $sellerProductIds);
                })->where('status', '1')->count();

                $DeliveryCount = orders::whereIn('id', function ($query) use ($sellerProductIds) {
                    $query->select('order_id')->from('order_items')->whereIn('product_id', $sellerProductIds);
                })->where('status', '2')->count();

                $CompleteCount = orders::whereIn('id', function ($query) use ($sellerProductIds) {
                    $query->select('order_id')->from('order_items')->whereIn('product_id', $sellerProductIds);
                })->where('status', '3')->count();

                // Get shipping orders for the seller's products with order items and product details
                $shippingorders = orders::whereIn('id', function ($query) use ($sellerProductIds) {
                    $query->select('order_id')->from('order_items')->whereIn('product_id', $sellerProductIds);
                })->with('orderItems.product')->latest()->get();

                $shippingordersCount = $shippingorders->count();

                return view('admin.shipping_order.shipping_all', compact('shippingordersCount', 'shippingorders', 'ProcessingCount', 'ApproveCount', 'DeliveryCount', 'CompleteCount', 'breadcrumbData'));
            }

       // ShippingController.php

       public function getOrderItems($orderId)
       {
           Log::info('Fetching order details for Order ID: ' . $orderId);
           try {
               $order = orders::with(['orderItems.product'])->findOrFail($orderId);
               return response()->json($order);
           } catch (\Exception $e) {
               Log::error('Error fetching order details: ' . $e->getMessage());
               return response()->json(['error' => 'An error occurred while fetching order details.'], 500);
           }
       }

            public function UpdateShippingRejectStatus($id){
                // Find the order by its ID
                $order = orders::find($id);

                if (!$order) {
                    // If the order doesn't exist, handle the error (e.g., show an error message or redirect)
                    return redirect()->back()->with('error', 'Order not found.');
                }

                // Update the shipping status here (assuming you have a "shipping_status" column in the "orders" table)
                // For example, you can set the shipping status to "approved":
                $order->status = '-1';
                $order->save();

   // Update the status in the DealerStock table based on the order items
   foreach ($order->orderItems as $orderItem) {
    $dealerStock = DealerStock::where('product_id', $orderItem->product_id)
        ->where('user_id', $order->user_id)
        ->first();

    if ($dealerStock) {
        // Update the status column in the DealerStock table
        $dealerStock->status = '-1'; // Set dealer stock status to completed
        $dealerStock->save();
    }
}

 // Add back the stock quantity of the products
 foreach ($order->orderItems as $orderItem) {
    $product = $orderItem->product;
    $product->product_stock += $orderItem->quantity; // Add back the ordered quantity
    $product->save();
}
                $notification = array(
                    'message' => 'Shipping  Order status updated successfully',
                    'alert-type' => 'success'
                );

                // Redirect back with a success message
                return redirect()->back()->with($notification);
            }//End Methods
            public function UpdateShippingApprovedStatus($id) {
                // Find the order by its ID
                $order = orders::find($id);

                if (!$order) {
                    // If the order doesn't exist, handle the error (e.g., show an error message or redirect)
                    return redirect()->back()->with('error', 'Order not found.');
                }

                // Update the shipping status to "approved"
                $order->status = '1'; // Assuming '1' represents the status for "approved"
                $order->save();

                // Check if the order status is "approved" before inserting commission
                if ($order->status == '1') {
                    // Fetch the commission percentage from the commission_settings table
                    $commissionPercentage = CommissionSetting::value('percentage'); // Assuming 'percentage' is the column name
                    $commissionExtraPercentage =CommissionSetting::value('extra_percentage');//Assuming 'extra_percentage' is the column name
                    // Calculate commission amount
                    $commissionAmount = $order->total_amount * ($commissionPercentage / 100);

                    // Fetch upline user ID from the referral system (Replace this with your actual implementation)
                    $uplineUserId = Referral::where('user_id', $order->user_id)->value('upline_user_id');

                    // Insert commission data into the commissions table
                    $commission = new Commission();
                    $commission->upline_user_id = $uplineUserId ?? 1; // Default value if upline user ID is not found
                    $commission->downline_user_id = $order->user_id;
                    $commission->order_id = $order->id;
                    $commission->commission_amount = $commissionAmount;
                    $commission->save();
                }

                $notification = [
                    'message' => 'Shipping Order status updated successfully',
                    'alert-type' => 'success'
                ];

                // Redirect back with a success message
                return redirect()->back()->with($notification);
            }




            public function UpdateShippingDeliveryStatus($id){
                // Find the order by its ID
                $order = orders::find($id);

                if (!$order) {
                    // If the order doesn't exist, handle the error (e.g., show an error message or redirect)
                    return redirect()->back()->with('error', 'Order not found.');
                }

                // Update the shipping status here (assuming you have a "shipping_status" column in the "orders" table)
                // For example, you can set the shipping status to "approved":
                $order->status = '2';
                $order->save();


                $notification = array(
                    'message' => 'Shipping  Order status updated successfully',
                    'alert-type' => 'success'
                );

                // Redirect back with a success message
                return redirect()->back()->with($notification);
            }//End Methods

            public function UpdateShippingCompleteStatus($id)
            {
                // Find the order by its ID
                $order = orders::find($id);

                if (!$order) {
                    // If the order doesn't exist, handle the error
                    return redirect()->back()->with('error', 'Order not found.');
                }

                // Update the shipping status of the order
                $order->status = '3';
                $order->save();
    // Update the status in the DealerStock table based on the order items
    foreach ($order->orderItems as $orderItem) {
        $dealerStock = DealerStock::where('product_id', $orderItem->product_id)
            ->where('user_id', $order->user_id)
            ->first();

        if ($dealerStock) {
            // Increase the dealer's product stock by the order item quantity
            $dealerStock->product_stock += $orderItem->quantity;
            $dealerStock->save();
        }
    }

                // Update the status in the DealerStock table based on the order ID
                $dealerStocks = DealerStock::where('order_id', $order->id)
                                            ->where('user_id', $order->user_id)
                                            ->get();

                foreach ($dealerStocks as $dealerStock) {
                    // Update the status column in the DealerStock table
                    $dealerStock->status = '3'; // Set dealer stock status to completed
                    $dealerStock->save();
                }

                $notification = [
                    'message' => 'Shipping order status updated successfully',
                    'alert-type' => 'success'
                ];

                // Redirect back with a success message
                return redirect()->back()->with($notification);
            }


            public function UpdateDealerShippingApprovedStatus($id)
            {
                // Find the order by its ID
                $order = orders::find($id);

                if (!$order) {
                    // If the order doesn't exist, handle the error
                    return redirect()->back()->with('error', 'Order not found.');
                }

                // Update the shipping status to "approved"
                $order->status = '1'; // Assuming '1' represents the status for "approved"
                $order->save();

                // Fetch upline user ID from the referral system
                $uplineUserId = Referral::where('user_id', $order->user_id)->value('upline_user_id');

                // Update the status in the DealerStock table based on the order items
                foreach ($order->orderItems as $orderItem) {
                    // Find the corresponding dealer stock for the product and user
                    $dealerStock = DealerStock::where('product_id', $orderItem->product_id)
                        ->where('user_id', $order->user_id)
                        ->where('order_id', $order->id) // Match the order_id with the dealer stock
                        ->first();

                    if ($dealerStock) {
                        // Update the status column in the DealerStock table
                        $dealerStock->status = '1'; // Set dealer stock status to approved

                        // Subtract the ordered quantity from the product_stock
                        $dealerStock->product_stock -= $orderItem->quantity;

                        $dealerStock->save();
                    }
                }

                // Fetch the commission percentage from the commission_settings table
                $commissionPercentage = CommissionSetting::value('percentage');
                $commissionExtraPercentage = CommissionSetting::value('extra_percentage');

                // Calculate commission amounts
                $totalOrderAmount = $order->total_amount;
                $baseCommissionAmount = $totalOrderAmount * ($commissionPercentage / 100); // Base commission based on percentage
                $extraCommissionAmount = $totalOrderAmount * ($commissionExtraPercentage/100); // Extra commission is 2%

                // Check if the order status is "approved" before inserting commission
                if ($order->status == '1') {
                    // Check if there is an upline user ID from the referral system
                    if ($uplineUserId) {
                        // If an upline user ID is found, insert the commission for the upline user
                        $commission = new Commission();
                        $commission->upline_user_id = $uplineUserId;
                        $commission->downline_user_id = $order->user_id; // Downline user (buyer)
                        $commission->order_id = $order->id;
                        $commission->commission_amount = $baseCommissionAmount; // Base commission for referring user
                        $commission->save();
                    } else {
                        // If no upline user ID is found, the base commission amount is 0
                        $baseCommissionAmount = 0;
                    }

                    // Check if the order items have associated products
                    foreach ($order->orderItems as $orderItem) {
                        // Fetch the seller's user ID from the associated product
                        $sellerUserId = $orderItem->product->user_id;

                        // Check if the buyer is the same as the seller
                        if ($sellerUserId == $order->user_id) {
                            // If the buyer is the seller's downline, add both base and extra commission
                            $totalCommissionAmount = $baseCommissionAmount + $extraCommissionAmount;
                        } else {
                            // If the buyer is not the seller, only add the extra commission
                            $totalCommissionAmount = $extraCommissionAmount;
                        }

                        // Insert commission data for the seller (user_id associated with the product)
                        $sellerCommission = new Commission();
                        $sellerCommission->upline_user_id = $sellerUserId; // Seller's user ID
                        $sellerCommission->downline_user_id = $order->user_id; // Downline user (buyer)
                        $sellerCommission->order_id = $order->id;
                        $sellerCommission->commission_amount = $totalCommissionAmount; // Total commission for seller
                        $sellerCommission->save();
                    }
                } else {
                    // If the order status is not "approved", set commission amounts to 0
                    $baseCommissionAmount = 0;
                    // Extra commission remains 2%
                }

                $notification = [
                    'message' => 'Shipping Order status updated successfully',
                    'alert-type' => 'success'
                ];

                // Redirect back with a success message
                return redirect()->back()->with($notification);
            }


            public function UpdateDealerShippingRejectStatus($id)
            {
                // Find the order by its ID
                $order = orders::find($id);

                if (!$order) {
                    // If the order doesn't exist, handle the error (e.g., show an error message or redirect)
                    return redirect()->back()->with('error', 'Order not found.');
                }

                // Update the shipping status of the order
                $order->status = '-1';
                $order->save();

                // Update the status in the DealerStock table based on the order items
                foreach ($order->orderItems as $orderItem) {
                    // Find the corresponding dealer stock for the product and user
                    $dealerStock = DealerStock::where('product_id', $orderItem->product_id)
                        ->where('user_id', $order->user_id)
                        ->first();

                    if ($dealerStock) {
                        // Update the status column in the DealerStock table
                        $dealerStock->status = '-1'; // Set dealer stock status to rejected
                        $dealerStock->save();
                    }
                }

                $notification = [
                    'message' => 'Shipping Order status updated successfully',
                    'alert-type' => 'success'
                ];

                // Redirect back with a success message
                return redirect()->back()->with($notification);
            }


public function UpdateDealerShippingDeliveryStatus($id)
            {
                // Find the order by its ID
                $order = orders::find($id);

                if (!$order) {
                    // If the order doesn't exist, handle the error (e.g., show an error message or redirect)
                    return redirect()->back()->with('error', 'Order not found.');
                }

                // Update the shipping status of the order
                $order->status = '2';
                $order->save();

                // Update the status in the DealerStock table based on the order items
                foreach ($order->orderItems as $orderItem) {
                    // Find the corresponding dealer stock for the product and user
                    $dealerStock = DealerStock::where('product_id', $orderItem->product_id)
                        ->where('user_id', $order->user_id)
                        ->first();

                    if ($dealerStock) {
                        // Update the status column in the DealerStock table
                        $dealerStock->status = '2'; // Set dealer stock status to rejected
                        $dealerStock->save();
                    }
                }

                $notification = [
                    'message' => 'Shipping Order status updated successfully',
                    'alert-type' => 'success'
                ];

                // Redirect back with a success message
                return redirect()->back()->with($notification);
            }



            public function UpdateDealerShippingCompleteStatus($id)
            {
                // Find the order by its ID
                $order = orders::find($id);

                if (!$order) {
                    // If the order doesn't exist, handle the error (e.g., show an error message or redirect)
                    return redirect()->back()->with('error', 'Order not found.');
                }

                // Update the shipping status of the order
                $order->status = '3';
                $order->save();

                // Update the status in the DealerStock table based on the order items
                foreach ($order->orderItems as $orderItem) {
                    // Find the corresponding dealer stock for the product and user
                    $dealerStock = DealerStock::where('product_id', $orderItem->product_id)
                        ->where('user_id', $order->user_id)
                        ->first();

                    if ($dealerStock) {
                        // Update the status column in the DealerStock table
                        $dealerStock->status = '3'; // Set dealer stock status to rejected
                        $dealerStock->save();
                    }
                }

                $notification = [
                    'message' => 'Shipping Order status updated successfully',
                    'alert-type' => 'success'
                ];

                // Redirect back with a success message
                return redirect()->back()->with($notification);
            }






        }

