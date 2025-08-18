<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\DealerStock;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Portfolio;
use App\Models\Product;
use App\Models\orders;
use App\Models\Referral;
use App\Models\order_items;
use Illuminate\Support\Facades\Auth;



class DashboardController extends Controller
{
    public function AllStatistics(){
        $userId = Auth::user()->id;

        $users =User::count();

        $products =Product::count();
        $portfolio = Portfolio::count();
        $service = Service::count();
        $orders = orders::count();
// Fetch order IDs placed by downlines with completed orders
$downlineOrderIds = orders::whereIn('user_id', function ($query) use ($userId) {
    $query->select('user_id')
        ->from('referral')
        ->where('upline_user_id', $userId); // Fetch orders placed by downlines
})
->where('status', 3) // Filter completed orders
->pluck('id');

// Get the order IDs with status "3" for the current logged-in user
$downlineOrderIds = orders::where('user_id', $userId)
    ->where('status', '3')
    ->pluck('id');

// Get the distinct product IDs associated with the dealer stock for the current logged-in user
$dealerStockProductIds = DealerStock::where('user_id', $userId)
    ->pluck('product_id');

// Count the total number of distinct products purchased by downlines
$downlinePurchasedProductsCount = order_items::whereIn('order_id', $downlineOrderIds)
    ->whereIn('product_id', $dealerStockProductIds)
    ->distinct('product_id')
    ->count('product_id');


        $salesPerformances = orders::with('user')
        ->where('status', '>=', 1)
        ->whereHas('user', function ($query) {
            $query->where('role_id', '!=', 700); // Exclude users with role ID 700
        })

        // Filter by status greater than or equal to 1
        ->selectRaw('user_id, SUM(total_amount) as total_sales')
        ->groupBy('user_id')
        ->orderByDesc('total_sales') // Order by total_amount in descending order
        ->get();

            // Calculate total sales performance for the logged-in user
    $agentTotalSales = $salesPerformances->where('user_id', Auth::user()->id)->sum('total_sales');
    $userTotalSales = $salesPerformances->where('user_id')->sum('total_sales');


        // $orderData = orders::where('user_id',$userId)->with('orderItems')->get();
        // $orderCount = $orderData->count();


        $ProcessingCount = orders::where('status', '0')->count();
        $ApproveCount = orders::where('status', '1')->count();
        $DeliveryCount = orders::where('status', '2')->count();
        $CompleteCount = orders::where('status', '3')->count();
        $shippingordersCount =orders::count();
        // Check if the currently logged-in user is an agent


    // Check if the currently logged-in user is an agent
    if (Auth::user()->role_id == '1'||Auth::user()->role_id=='2') {
        // Admin user - show total users
        $userstatistics = [
            'total' => $users,
            'label' => 'Total Users',
        ];
    } else {
        // Agent user - show total downlines
        $agent = Auth::user();
        $id = $agent->id; // Use the ID of the logged-in user
         // Replace this with the agent's ID you want to retrieve downlines for

        $downlines = Referral::where('upline_user_id', $id)->with('agent')->get();
        $AgentCount = $downlines->count();

        $userstatistics = [
            'total' => $AgentCount,
            'label' => 'Total Downlines',
        ];

           // Update $servicestatistics with the total sales of the logged-in user
   $salestatistics = [
    'total' => $agentTotalSales,
    'label' => 'Total Sales',
];

    }

    if (Auth::user()->role_id == '700') {
         $userId = Auth::user()->id;
         $orderCount = orders::where('user_id', $userId)->count(); // Count orders placed by the current user

    $orderstatistics = [
        'total' => $orderCount,
        'label' => 'Total Order',
    ];
    } else {
        // Agent user - show total orders received
        $userId = Auth::user()->id;
        // Fetch order items where the product belongs to the seller's products
        $orderItems = order_items::whereIn('product_id', function($query) use ($userId) {
                            $query->select('id')
                                  ->from('product')
                                  ->where('user_id', $userId); // Assuming the column name for the seller's ID is 'user_id' in the products table
                        })->get();

        // Count the unique orders
        $orderCount = $orderItems->pluck('order_id')->unique()->count();

        $orderstatistics = [
            'total' => $orderCount,
            'label' => 'Total Orders Received',
        ];
    }



//Total Product
$productstatistics = [
    'total' => $products,
    'label' => 'Total Product',
];

   // Update $servicestatistics with the total sales of the logged-in user
   $salestatistics = [
    'total' => $userTotalSales,
    'label' => 'Total Sales',
];

if (Auth::user()->role_id == '700') {
    $shippingorders = orders::where('user_id',$userId)->with('orderItems')->get();


}else{

// Fetch products owned by the authenticated user (seller)
$sellerProducts = Product::where('user_id', $userId)->pluck('id');

// Fetch order items associated with products owned by the seller
$orderItems = order_items::whereIn('product_id', $sellerProducts)->get();

// Extract order IDs from the order items
$orderIds = $orderItems->pluck('order_id')->unique()->toArray();

// Fetch shipping data for the extracted order IDs
$shippingorders = orders::whereIn('id', $orderIds)
                       ->with('orderItems')
                       ->withSum('orderItems', 'quantity')
                       ->latest()
                       ->get();
                    }

        return view('admin.dashboard.dashboard',compact('shippingordersCount','shippingorders','ProcessingCount','ApproveCount','DeliveryCount','CompleteCount','users','products','portfolio','service','userstatistics','orderstatistics',
    'productstatistics','salestatistics','salesPerformances','downlinePurchasedProductsCount'));










}
}
