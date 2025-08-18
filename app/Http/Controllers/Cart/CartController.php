<?php

namespace App\Http\Controllers\Cart;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Stock\DealerStockController;
use App\Models\EWallet;
use App\Models\CommissionSetting;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\DealerStock;
use App\Models\DealerCart;
use App\Models\EWalletTransaction;
use App\Models\Commission;
use App\Models\Referral;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\orders;
use App\Models\DealerOrder;
use App\Models\order_items;
use App\Models\transactions;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log; // Import Log facade
use Image;
use Illuminate\Support\Carbon;
class CartController extends Controller
{


    public function addToCart(Request $request)
    {
        // Validate inputs
        $validator = Validator::make($request->all(), [
            'dealer_stock_id' => 'nullable|exists:dealer_stock,id',
            'product_id' => 'nullable|exists:product,id',
            'quantity' => 'required|numeric|min:1',
            'submission_type' => 'required|in:buy-now-details,add-to-cart',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Retrieve validated inputs
        $dealerStockId = $request->input('dealer_stock_id');
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity');
        $submissionType = $request->input('submission_type');

        // Begin database transaction
        \DB::beginTransaction();

        try {
            // Handle dealer stock item
            if ($dealerStockId) {
                $dealerStock = DealerStock::find($dealerStockId);
                if (!$dealerStock) {
                    throw new \Exception('Dealer stock not found.');
                }

                // Check if the user already has this dealer stock in their cart
                $existingDealerCartItem = DealerCart::where('user_id', $request->user()->id)
                    ->where('dealer_stock_id', $dealerStockId)
                    ->first();

                if ($existingDealerCartItem) {
                    // Update quantity if item already exists in dealer cart
                    $newQuantity = $existingDealerCartItem->quantity + $quantity;
                    $existingDealerCartItem->update(['quantity' => $newQuantity]);
                } else {
                    // Create new entry in dealer cart if item doesn't exist
                    DealerCart::create([
                        'dealer_stock_id' => $dealerStockId,
                        'user_id' => $request->user()->id,
                        'quantity' => $quantity,
                    ]);
                }
            }

            // Handle product item
            if ($productId) {
                $product = Product::find($productId);
                if (!$product) {
                    throw new \Exception('Product not found.');
                }

                // Check if the user already has this product in their cart
                $existingCartItem = Cart::where('user_id', $request->user()->id)
                    ->where('product_id', $productId)
                    ->first();

                if ($existingCartItem) {
                    // Update quantity if item already exists in cart
                    $newQuantity = $existingCartItem->quantity + $quantity;
                    $existingCartItem->update(['quantity' => $newQuantity]);
                } else {
                    // Create new entry in cart if item doesn't exist
                    Cart::create([
                        'product_id' => $productId,
                        'user_id' => $request->user()->id,
                        'quantity' => $quantity,
                    ]);
                }
            }

            // Commit database transaction
            \DB::commit();

        } catch (\Exception $e) {
            // Rollback transaction on error
            \DB::rollback();
            return redirect()->back()->with('error', $e->getMessage());
        }

        // Update total quantity in session
        $totalCartItems = Cart::where('user_id', $request->user()->id)->sum('quantity');
        $totalDealerItems = DealerCart::where('user_id', $request->user()->id)->sum('quantity');
        session()->put('cartTotal', $totalCartItems + $totalDealerItems);

        // Notification message
        $notification = [
            'message' => 'Item added to cart successfully',
            'alert-type' => 'success'
        ];

        // Redirect based on submission type
        if ($submissionType === 'add-to-cart') {
            // Redirect to the same page or any other route as needed
            return redirect()->back()->with($notification);
        } else if ($submissionType === 'buy-now-details') {
            // Redirect to cart.summary for buy now
            return redirect()->route('cart.summary')->with($notification);
        }
    }

    public function guestAddToCart(Request $request)
{
    $productId = $request->input('product_id');
    $quantity = $request->input('quantity');

    $product = Product::findOrFail($productId);
    $availableStock = $product->product_stock;

    // Validate the quantity input against the available stock
    $validator = Validator::make($request->all(), [
        'quantity' => "required|numeric|max:$availableStock",
    ], [
        'quantity.max' => 'The quantity cannot exceed the available stock.',
    ]);



    if ($validator->fails()) {
        return redirect()->back()->withErrors($validator)->withInput();
    }

    $cart = new Cart();
    $cart->product_id = $productId;
    $cart->user_id = null; // Set user_id as null for guest users
    $cart->quantity = $quantity;
    $cart->save();

    $cartItems = session()->get('cart', []);
    $cartItem = [
        'product_id' => $productId,
        'quantity' => $quantity,
    ];
    $cartItems[] = $cartItem;

    // Store the updated cart items in the session
    session()->put('cart', $cartItems);

    // Generate a unique guest ID if it doesn't exist in the session
    if (!session()->has('guest_id')) {
        $guest_id = 'GUEST_' . Str::uuid()->toString();
        session()->put('guest_id', $guest_id);
    }

    // Debugging information
    $guest_id = session()->get('guest_id');
    // dd($guest_id, $cartItems);

    // Calculate the total quantity
    $total = 0;
    foreach ($cartItems as $item) {
        $total += $item['quantity'];
    }
    session()->put('GuestCartTotal', $total);

    $notification = [
        'message' => 'Item added to cart successfully',
        'alert-type' => 'success'
    ];
    return redirect()->back()->with($notification);
}


    public function getCartTotal(){
        $total = Cart::where('user_id',auth()->id())->sum('quantity');
        return response()->json(['total'=>$total]);
    }//End Methods

    public function GuestGetCartTotal(){
        $cartItems = session()->get('cart', []);
        $total = 0;

        foreach ($cartItems as $item) {
            $total += $item['quantity'];
        }

        return response()->json(['total' => $total]);
    }


    //Current Logged User
    // Step 1: This method will be called when a user logs in to merge their cart with the guest cart.
    protected function mergeCarts()
    {
        $guestCartItems = session()->get('cart', []);
        $user = Auth::user();

        if (!$user) {
            return; // If the user is not logged in, do nothing.
        }

        foreach ($guestCartItems as $item) {
            // Check if the item already exists in the user's cart.
            $existingItem = Cart::where('user_id', $user->id)
                ->where('product_id', $item['product_id'])
                ->first();

            if ($existingItem) {
                // If the item exists, update its quantity in the user's cart.
                $existingItem->update([
                    'quantity' => $existingItem->quantity + $item['quantity'],
                ]);
            } else {
                // If the item does not exist, create a new record in the user's cart.
                Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        // Clear the guest cart after merging.
        session()->forget('cart');

    }

    // Step 2: Override the default login method to call the "mergeCarts" method after a successful login.
    public function login(Request $request)
    {
        $this->validateLogin($request);

        if ($this->attemptLogin($request)) {
            $user = $this->guard()->user();
            $this->mergeCarts(); // Call the method to merge the carts after a successful login.
            return $this->sendLoginResponse($request);
        }

        return $this->sendFailedLoginResponse($request);
    }


    public function getCart()
    {
        if (auth()->check()) {
            // User is authenticated
            $user_id = auth()->id();

            // Retrieve cart items along with associated products (including soft-deleted)
            $cartItems = Cart::where('user_id', $user_id)
                ->with(['product' => function ($query) {
                    $query->withTrashed(); // Include soft-deleted products
                }])
                ->get();

            // Retrieve dealer cart items along with associated dealer stocks
            $dealerCartItems = DealerCart::where('user_id', $user_id)
                ->with('dealerStock')
                ->get();

            // Merge regular cart items and dealer cart items
            $allCartItems = $cartItems->map(function ($item) {
                $item->type = 'product';
                return $item;
            })->merge($dealerCartItems->map(function ($item) {
                $item->type = 'dealer_stock';
                return $item;
            }));

            // Debugging: Log the merged cart items
            Log::info($allCartItems);

            return view('admin.cart.cart_summary', compact('allCartItems'));
        } else {
            // Guest user
            if (!session()->has('guest_id')) {
                session()->put('guest_id', 'GUEST_' . uniqid());
            }
            $guest_id = session()->get('guest_id');

            // Retrieve cart items for guest
            $cartItems = Cart::where('guest_id', $guest_id)
                ->with('product')
                ->get();

            // Retrieve dealer cart items for guest
            $dealerCartItems = DealerCart::where('guest_id', $guest_id)
                ->with('dealerStock')
                ->get();

            // Merge regular cart items and dealer cart items
            $allCartItems = $cartItems->map(function ($item) {
                $item->type = 'product';
                return $item;
            })->merge($dealerCartItems->map(function ($item) {
                $item->type = 'dealer_stock';
                return $item;
            }));

            // Debugging: Log the merged cart items
            Log::info($allCartItems);

            return view('frontend.guest_cart_summary', compact('allCartItems'));
        }
    }




    public function updateCartItem(Request $request, $cartItemId)
    {
        try {
            $cartItem = Cart::findOrFail($cartItemId);
            $newQuantity = (int) $request->input('quantity');

            // Check if the new quantity is within the product stock limit
            if ($newQuantity <= $cartItem->product->product_stock) {
                // Update the quantity of the cart item
                $cartItem->quantity = $newQuantity;
                $cartItem->save();

                $notification = array(
                    'message' => 'Product Quantity is updated Successfully',
                    'alert-type' => 'success'
                );
            } else {
                $notification = array(
                    'message' => 'Product quantity exceeds available stock',
                    'alert-type' => 'error'
                );
            }

            return redirect()->back()->with($notification);
        } catch (\Exception $e) {
            $notification = array(
                'message' => 'An error occurred while updating product quantity',
                'alert-type' => 'error'
            );
            return redirect()->back()->with($notification);
        }
    }


    public function RemoveCart($id) {
        $cartItem = Cart::findOrFail($id);

        $cartTotalBefore = Cart::where('user_id', auth()->user()->id)->sum('quantity');

        $cartItem->delete();

        $cartTotalAfter = Cart::where('user_id', auth()->user()->id)->sum('quantity');
        $updatedCartTotal = max($cartTotalAfter, 0); // Ensure the cart total is not negative
        session()->put('cartTotal', $updatedCartTotal);

        $notification = array(
            'message' => 'Product Removed Successfully from the cart',
            'alert-type' => 'success'
        );

        return redirect()->back()->with($notification);
    }

    public function payment(Request $request)
    {
        $userId = Auth::id();

        // Get the authenticated user ID
        $cartItems = Cart::where('user_id', $userId)->get();
        $dealerCartItems = DealerCart::where('user_id', $userId)->get();
    // Merge both collections of cart items
    $allCartItems = $cartItems->merge($dealerCartItems);
   // Check if there are any cart items
   if ($allCartItems->isEmpty()) {
    $notification = [
        'message' => 'No items in the shopping cart.',
        'alert-type' => 'error'
    ];
    return redirect()->back()->with($notification);
}
        // Get the total amount from the form submission
        $totalAmount = $request->input('total_amount');

        // Ensure that the 'total_amount' is not null or empty
        if (!$totalAmount || !is_numeric($totalAmount)) {
            $notification = [
                'message' => 'Invalid total amount value.',
                'alert-type' => 'error'
            ];
            return redirect()->back()->with($notification);
        }

        // Retrieve the user's e-wallet record
     // Retrieve the user's e-wallet record
$userWallet = EWallet::where('user_id', $userId)->first();

// Check if the e-wallet record exists
if (!$userWallet) {
    $notification = [
        'message' => 'E-wallet record not found.',
        'alert-type' => 'error'
    ];
    return redirect()->back()->with($notification);
}

// Calculate total income
$totalIncome = EWalletTransaction::where('user_id', $userId)
    ->where('type', 'credit') // Consider only credit transactions as income
    ->sum('amount');

// Calculate total expenses
$totalExpenses = EWalletTransaction::where('user_id', $userId)
    ->where('type', 'debit') // Consider only debit transactions as expenses
    ->sum('amount');

// Calculate the current balance
$currentBalance = $totalIncome - $totalExpenses;

// Check if the current balance is sufficient for the payment
if ($currentBalance < $totalAmount) {
    $notification = [
        'message' => 'Insufficient balance in your e-wallet.',
        'alert-type' => 'error'
    ];
    return redirect()->back()->with($notification);
}


// Step 1: Create an order
$order = new orders();
$order->user_id = $userId;
$order->total_amount = $totalAmount;

if ($request->hasFile('receipt')) {
    // Image was uploaded
    $image = $request->file('receipt');
    $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
    Image::make($image)->resize(1000, 1000)->save('upload/transactions/' . $name_gen);
    $save_url = 'upload/transactions/' . $name_gen;

    $order->payment_proof = $save_url;
} else {
    // No image uploaded, set default image path
    $order->payment_proof = 'upload/default.jpg'; // Replace 'default_image_path.jpg' with the actual default image path
}
$order->status = 1;
$order->created_at = Carbon::now('Asia/Kuala_Lumpur');


$order->save();
// return response()->json(['success'=>$file_name]);



// Create a new e-wallet transaction record to track the deduction and include order ID in remarks
EWalletTransaction::create([
    'user_id' => $userId,
    'amount' => $totalAmount,
    'type' => 'debit', // Indicate deduction
    'remarks' => 'Purchase made [Order ID:' . $order->id . ']', // Add remarks with order ID
]);
  // Loop through all cart items and create order items
  foreach ($allCartItems as $cartItem) {
    $orderItem = new order_items();
    $orderItem->order_id = $order->id;
    $orderItem->user_id = $userId;
    $orderItem->quantity = $cartItem->quantity;

    // Retrieve the product details
    $product = Product::find($cartItem->product_id);
    if ($product) {
        // Deduct product stock for the ordered product
        $product->product_stock -= $cartItem->quantity;
        $product->save();

        // Create a new entry in the dealer stock for this order
        $dealerStock = new DealerStock();
        $dealerStock->user_id = $userId;
        $dealerStock->product_id = $cartItem->product_id;
        $dealerStock->order_id = $order->id; // Assign the order ID here
        $dealerStock->sku = $product->sku;
        $dealerStock->product_name = $product->product_name;
        $dealerStock->product_category_id = $product->product_category_id;
        $dealerStock->long_description = $product->long_description;
        $dealerStock->grand_total = $product->product_price * $cartItem->quantity; // Calculate total price
        $dealerStock->product_price = $product->product_price;
        $dealerStock->customer_price = $product->customer_price;
        $dealerStock->product_stock = $cartItem->quantity;
        $dealerStock->weight = $product->weight;
        $dealerStock->status = 0;
        $dealerStock->publish_status = 0;

        // Assign the product image path
        if (!empty($product->product_image)) {
            $dealerStock->product_image = $product->product_image;
        }

        // Check if weight is available in the cart item
        if (!empty($cartItem->weight)) {
            $dealerStock->weight = $cartItem->weight;
        } else {
            // If weight is not available, fetch it from the product table
            $dealerStock->weight = $product->weight;
        }

        // Check if customer_price is available in the cart item
        if (!empty($cartItem->customer_price)) {
            $dealerStock->customer_price = $cartItem->customer_price;
        } else {
            // If customer_price is not available, fetch it from the product table
            $dealerStock->customer_price = $product->customer_price;
        }

        // Check if the cart item has a SKU
        if (!empty($cartItem->sku)) {
            $dealerStock->sku = $cartItem->sku;
        } else {
            // If the cart item does not have a SKU, fetch it from the products table
            $dealerStock->sku = $product->sku;
        }

        // Save the new dealer stock entry
        $dealerStock->save();
    }

    if ($cartItem instanceof Cart) {
        // For regular Product items
        $orderItem->product_id = $cartItem->product_id;
    } elseif ($cartItem instanceof DealerCart) {
        // For DealerStock items
        // $orderItem->dealer_stock_id = $cartItem->dealer_stock_id;
        $orderItem->product_id = $dealerStock->product_id;
    }

    // Save the order_item
    $orderItem->save();
}

// Step 3: Store the transaction (payment receipt)
$transaction = new transactions();
$transaction->order_id = $order->id;
$transaction->user_id = $userId;


// Check if the order status is "confirmed" after admin updates
$orderStatus = orders::find($order->id)->status;

if ($orderStatus == '1') {
  // Fetch the commission percentage from the commission_settings table
  $commissionSetting = CommissionSetting::first();
  $commissionPercentage = $commissionSetting ? $commissionSetting->percentage : 0;
  $extraPercentage = $commissionSetting ? $commissionSetting->extra_percentage : 2; // Default to 2 if not found

  // Log the fetched commission percentage
  Log::info('Fetched commission percentage: ' . $commissionPercentage);

    // Calculate commission amounts
    $baseCommissionAmount = $totalAmount * ($commissionPercentage / 100);
    $extraCommissionAmount = $totalAmount * ($extraPercentage / 100);


    // Fetch upline user ID from the referral system
    $uplineUserId = Referral::where('user_id', $userId)->value('upline_user_id');

  // Insert base commission data into the commissions table
  $commission = new Commission();
  $commission->upline_user_id = $uplineUserId;
  $commission->downline_user_id = $userId;
  $commission->order_id = $order->id;
  $commission->commission_amount = $baseCommissionAmount;
  $commission->save();

      // Insert extra commission data into the commissions table
      $extraCommission = new Commission();
      $extraCommission->upline_user_id = $uplineUserId;
      $extraCommission->downline_user_id = $userId;
      $extraCommission->order_id = $order->id;
      $extraCommission->commission_amount = $extraCommissionAmount;
      $extraCommission->save();
}










// Image upload logic


if ($request->hasFile('receipt')) {
    $image = $request->file('receipt');
    $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
    Image::make($image)->resize(1000, 1000)->save('upload/payment_proof/' . $name_gen);

    $image->move(public_path('upload/payment_proof/'), $name_gen);
    $save_url = 'upload/payment_proof/' . $name_gen;

    $transaction->payment_proof = $save_url;
}
// Handle the receipt file upload and store its path in the 'payment_proof' field.
// Example: $transaction->payment_proof = $request->file('receipt')->store('receipts');

  // Image upload logic

$transaction->save();


//Step 4 Insert dealerstock

foreach ($cartItems as $cartItem) {

    // Check if the user's role is 350
    if (Auth::user()->role_id != 350) {
        // If the user's role is not 350, do not insert into the database
        continue;
    }

// Retrieve the product details
$product = Product::findOrFail($cartItem->product_id);

// Create a new entry in the dealer stock
$dealerStock = new DealerStock();
$dealerStock->user_id = $userId;
$dealerStock->product_id = $cartItem->product_id;
$dealerStock->order_id = $order->id; // Assign the order ID here
$dealerStock->sku =$product->sku;
$dealerStock->product_name = $product->product_name;
$dealerStock->product_category_id = $product->product_category_id;
$dealerStock->long_description = $product->long_description;
$dealerStock->grand_total = $product->product_price * $cartItem->quantity; // Calculate total price
$dealerStock->product_price = $product->product_price;
$dealerStock->customer_price =$product->customer_price;
$dealerStock->product_stock = $cartItem->quantity;
$dealerStock->weight=$product->weight;
$dealerStock->status = 0;
$dealerStock->publish_status = 0;

// Assign the product image path
if (!empty($product->product_image)) {
    $imagePath = $this->storeImage($product->product_image); // Store image and get path
    $dealerStock->product_image = $imagePath;
}


// Assign the product image path
$dealerStock->product_image = $product->product_image;

// Check if weight is available in the cart item
if (!empty($cartItem->weight)) {
    $dealerStock->weight = $cartItem->weight;
} else {
    // If weight is not available, fetch it from the product table
    $dealerStock->weight = $product->weight;
}

// Check if customer_price is available in the cart item
if (!empty($cartItem->customer_price)) {
    $dealerStock->customer_price = $cartItem->customer_price;
} else {
    // If customer_price is not available, fetch it from the product table
    $dealerStock->customer_price = $product->customer_price;
}

// Check if the cart item has a SKU
if (!empty($cartItem->sku)) {
    $dealerStock->sku = $cartItem->sku;
} else {
    // If the cart item does not have a SKU, fetch it from the products table
    $dealerStock->sku = $product->sku;
}

$dealerStock->save();
}






// Clear the cart or perform any other required actions.
// Clear the cart items
Cart::where('user_id', $userId)->delete();
session()->put('cartTotal', 0);

// Redirect to a success page after successful checkout
$notification = array(
    'message' => 'Make payment successfully',
    'alert-type' => 'success'
);

// Proceed with any other necessary steps

return redirect()->back()->with($notification);
    }

    public function checkout(Request $request)
{
    // Get the authenticated user's ID
    $userId = Auth::id();

    // Fetch cart items from both carts and dealer_carts
    $cartItems = Cart::where('user_id', $userId)->get();
    $dealerCartItems = DealerCart::where('user_id', $userId)->get();

    // Merge both collections of cart items
    $allCartItems = $cartItems->merge($dealerCartItems);

    // Check if there are any cart items
    if ($allCartItems->isEmpty()) {
        $notification = [
            'message' => 'No items in the shopping cart.',
            'alert-type' => 'error'
        ];
        return redirect()->back()->with($notification);
    }

    // Validate the receipt file upload
    $request->validate([
        'receipt' => 'file|mimes:jpeg,png,pdf|max:2048',
    ], [
        'receipt.file' => 'Please upload a valid file.',
        'receipt.mimes' => 'The receipt file must be in JPEG, PNG, or PDF format.',
        'receipt.max' => 'The receipt file size must not exceed 2048 KB.',
    ]);

    // Calculate the total amount
    $totalAmount = $request->input('total_amount');

    // Create an order
    $order = new orders();
    $order->user_id = $userId;
    $order->total_amount = $totalAmount;

    // Handle receipt file upload
    if ($request->hasFile('receipt')) {
        $image = $request->file('receipt');
        $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
        Image::make($image)->resize(1000, 1000)->save('upload/transactions/' . $name_gen);
        $order->payment_proof = 'upload/transactions/' . $name_gen;
    }

    // Set order status and timestamp
    $order->status = 0; // Assuming status '0' means pending
    $order->created_at = now();

    // Save the order
    $order->save();

    // Loop through all cart items and create order items
    foreach ($allCartItems as $cartItem) {
        $orderItem = new order_items();
        $orderItem->order_id = $order->id;
        $orderItem->user_id = $userId;
        $orderItem->quantity = $cartItem->quantity;

        // Retrieve the product details
        $product = Product::find($cartItem->product_id);
        if ($product) {
            // Deduct product stock for the ordered product
            $product->product_stock -= $cartItem->quantity;
            $product->save();

            // Create a new entry in the dealer stock for this order
            $dealerStock = new DealerStock();
            $dealerStock->user_id = $userId;
            $dealerStock->product_id = $cartItem->product_id;
            $dealerStock->order_id = $order->id; // Assign the order ID here
            $dealerStock->sku = $product->sku;
            $dealerStock->product_name = $product->product_name;
            $dealerStock->product_category_id = $product->product_category_id;
            $dealerStock->long_description = $product->long_description;
            $dealerStock->grand_total = $product->product_price * $cartItem->quantity; // Calculate total price
            $dealerStock->product_price = $product->product_price;
            $dealerStock->customer_price = $product->customer_price;
            $dealerStock->product_stock = $cartItem->quantity;
            $dealerStock->weight = $product->weight;
            $dealerStock->status = 0;
            $dealerStock->publish_status = 0;

            // Assign the product image path
            if (!empty($product->product_image)) {
                $dealerStock->product_image = $product->product_image;
            }

            // Check if weight is available in the cart item
            if (!empty($cartItem->weight)) {
                $dealerStock->weight = $cartItem->weight;
            } else {
                // If weight is not available, fetch it from the product table
                $dealerStock->weight = $product->weight;
            }

            // Check if customer_price is available in the cart item
            if (!empty($cartItem->customer_price)) {
                $dealerStock->customer_price = $cartItem->customer_price;
            } else {
                // If customer_price is not available, fetch it from the product table
                $dealerStock->customer_price = $product->customer_price;
            }

            // Check if the cart item has a SKU
            if (!empty($cartItem->sku)) {
                $dealerStock->sku = $cartItem->sku;
            } else {
                // If the cart item does not have a SKU, fetch it from the products table
                $dealerStock->sku = $product->sku;
            }

            // Save the new dealer stock entry
            $dealerStock->save();
        }

        if ($cartItem instanceof Cart) {
            // For regular Product items
            $orderItem->product_id = $cartItem->product_id;
        } elseif ($cartItem instanceof DealerCart) {
            // For DealerStock items
            // $orderItem->dealer_stock_id = $cartItem->dealer_stock_id;
            $orderItem->product_id = $dealerStock->product_id;
        }

        // Save the order_item
        $orderItem->save();
    }

    // Store the transaction (payment receipt)
    $transaction = new transactions();
    $transaction->order_id = $order->id;
    $transaction->user_id = $userId;

    // Handle receipt file upload for transaction
    if ($request->hasFile('receipt')) {
        $image = $request->file('receipt');
        $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
        Image::make($image)->resize(1000, 1000)->save('upload/payment_proof/' . $name_gen);
        $transaction->payment_proof = 'upload/payment_proof/' . $name_gen;
    }

    // Save transaction
    $transaction->save();

    // Clear both cart types after successful checkout
    Cart::where('user_id', $userId)->delete();
    DealerCart::where('user_id', $userId)->delete();

    // Set session variable for cart total
    session()->put('cartTotal', 0);

    // Redirect to a success page after successful checkout
    $notification = [
        'message' => 'Payment and order created successfully.',
        'alert-type' => 'success'
    ];
    return redirect()->back()->with($notification);
}


    public function emptyCart()
    {
        // Check if the user is logged in
        if (Auth::check()) {
            // Retrieve the authenticated user
            $user = Auth::user();

            // Check if the user has any cart items
            if ($user->cartItems->isEmpty()) {
                $notification = [
                    'message' => 'The cart is already empty.',
                    'alert-type' => 'error'
                ];
            } else {
                // Delete all cart items for the current user
                $user->cartItems()->delete();
                $user->DealerCartItems()->delete();
                session()->put('cartTotal', 0);


                $notification = [
                    'message' => 'Shopping cart has been emptied',
                    'alert-type' => 'success'
                ];
            }

            // Redirect back to the cart page with the appropriate message
            return redirect()->route('cart.summary')->with($notification);
        }

        // If the user is not logged in, you may choose to handle this differently
        // For example, you can redirect them to the login page or show a warning message.

        // Redirect back to the cart page with an error message
        return redirect()->route('cart')->with('error', 'You must be logged in to empty the cart.');
    }



/**
 * Store image file and return the path.
 *
 * @param \Illuminate\Http\UploadedFile|string $image
 * @return string
 */


private function storeImage($image)
{
    if ($image instanceof UploadedFile) {
        $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
        Image::make($image)->resize(1000, 1000)->save('upload/product/' . $name_gen);
        return 'upload/product/' . $name_gen;
    } else {
        return $image; // If image is already a path, return it directly
    }
}
}
