<?php
namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Transaction;
use App\Models\Commission;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Facades\Image;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;

        try {
            DB::beginTransaction(); // Start the database transaction

            $cartItems = Cart::where('user_id', $user_id)->with('product')->get();

            // Calculate total quantity and amount based on the current frontend values
            $productquantities = $request->input('quantity_', []);
            $productsubtotals = $request->input('subtotals', []);

            $totalQuantity = array_sum($productquantities);
            $totalAmount = array_sum($productsubtotals);

            //Calculate commission amount
            $commissionAmount = $totalAmount*0.05;
            $uplineUserId =$user->upline_user_id;

              // Insert commission data into the commission table
              $commission = new Commission();
              $commission->upline_user_id = $uplineUserId;
              $commission->downline_user_id = $user_id;
              $commission->commission_amount = $commissionAmount;
              $commission->save();



            foreach ($cartItems as $item) {
                $productId = $item->product_id;
                $cartItemId = $item->id;
                $quantity = (int) $request->input('quantity_' . $cartItemId, $item->quantity);

                $subtotal = $item->product->product_price * $quantity;

            //      // Update the quantity of the cart item
            // $item->cart->quantity = $quantity;
            // $item->cart->save();

                // Update the total quantity and total amount based on the frontend form data
                $totalQuantity += $quantity;
                $totalAmount += $subtotal;



                // Update the quantity of the cart item
                $item->product->product_stock -= $quantity; // Subtract $quantity from product_stock
                $item->product->save();


                   // Update the quantity of the cart item
            // $item->quantity = $quantity;
            // $item->save();


                // Create a transaction for the product
                $transaction = new Transaction();
                $transaction->product_id = $productId;
                $transaction->user_id = $user_id;
                $transaction->quantity = $quantity;
                $transaction->total_amount = $subtotal;

                // Handle the uploaded payment proof for the transaction
                if ($request->hasFile('payment_proof')) {
                    $file = $request->file('payment_proof');
                    $fileName = $file->getClientOriginalName();
                    $file->storeAs('payment_proofs', $fileName);
                    $transaction->payment_proof = 'upload/payment_proof/' . $fileName;
                }

                $transaction->save();
            }

            // Clear the cart items
            Cart::where('user_id', $user_id)->delete();

            DB::commit(); // Commit the database transaction if all operations are successful

            $notification = array(
                'message' => 'Make payment successfully',
                'alert-type' => 'success'
            );

            // Proceed with any other necessary steps

            return redirect()->back()->with($notification);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback the database transaction if an error occurs

            $notification = array(
                'message' => 'An error occurred. Please try again later.',
                'alert-type' => 'error'
            );

            return redirect()->back()->with($notification);
        }
    }//End Methods


    public function update(Request $request)
    {
        $user = Auth::user();
        $user_id = $user->id;

        $product_id = $request->input('product_id');
        $new_quantity = (int) $request->input('new_quantity');

        try {
            DB::beginTransaction(); // Start the database transaction

            // Find the cart item for the given product_id and authenticated user
            $cartItem = Cart::where('user_id', $user_id)
                ->where('product_id', $product_id)
                ->first();

            if ($cartItem) {
                // Update the quantity of the cart item
                $cartItem->quantity = $new_quantity;
                $cartItem->save();

                // Update the total amount and quantity in the transaction table (if needed)

                DB::commit(); // Commit the database transaction if all operations are successful

                // Return a JSON response indicating success
                return response()->json(['success' => true]);
            } else {
                // The cart item for the given product_id and user_id was not found
                // Return a JSON response indicating failure
                return response()->json(['success' => false, 'message' => 'Cart item not found']);
            }
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback the database transaction if an error occurs

            // Return a JSON response indicating failure
            return response()->json(['success' => false, 'message' => 'An error occurred while updating cart quantity']);
        }

}
}
