<?php

namespace App\Http\Controllers\Stock;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DealerStock;
use App\Models\DealerProductCategory;
use App\Models\Product;
use Image;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
class DealerStockController extends Controller
{
    public function AllDealerProduct($id = null)
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'My Stock', 'url' => route('all.dealer.products')],
        ];

        $userId = Auth::id(); // Get the ID of the logged-in user

        // Retrieve the total number of unique products for the logged-in user
        $product_index = DealerStock::where('user_id', $userId)->distinct('product_id')->count();

        // Get all dealer products for the logged-in user
        $dealerProducts = DealerStock::where('user_id', $userId)->latest()->get();

         // Get all dealer products for the logged-in user with status "3" (completed)
    $dealerProducts = DealerStock::where('user_id', $userId)
    ->where('status', '3')
    ->latest()
    ->get();


        // Check if the dealer has purchased each product
        foreach ($dealerProducts as $product) {
            $purchasedProduct = DealerStock::where('user_id', $userId) ->first();

            // If the dealer has purchased the product, update the product data
            if ($purchasedProduct) {
                $product->is_purchased = true;
                $product->purchased_id = $purchasedProduct->id;
            } else {
                $product->is_purchased = false;
            }
        }
        $productcategory = DealerProductCategory::latest()->get();

        return view('agent.dealerstock.dealer_product_all', compact('dealerProducts', 'productcategory','product_index', 'breadcrumbData'));
    }
    public function UpdateShippingPublishStatus($id)
    {
        $user_id = Auth::id(); // Get the authenticated user's ID

        // Find the dealer stock by its ID
        $dealerStock = DealerStock::find($id);

        if (!$dealerStock) {
            // If the dealer stock doesn't exist, handle the error
            return redirect()->back()->with('error', 'Product not found.');
        }

        // Create a new product entry using the dealer stock details
        Product::create([
            'product_name' => $dealerStock->product_name,
            'sku' => $dealerStock->sku,
            'weight' => $dealerStock->weight,
            'product_category_id' => $dealerStock->product_category_id,
            'user_id' => $user_id, // Assign the authenticated user's ID
            'product_stock' => $dealerStock->product_stock,
            'long_description' => $dealerStock->long_description,
            'product_price' => $dealerStock->product_price,
            'customer_price' => $dealerStock->customer_price,
            'product_image' => $dealerStock->product_image,
            'dealer_stock_id' => $dealerStock->id, // Add the dealer_stock_id
            'created_at' => now(),
        ]);

        // Update the publish status of the dealer stock
        $dealerStock->publish_status = 1; // Assuming '1' represents the published status
        $dealerStock->save();

        $notification = [
            'message' => 'Your product has been published successfully',
            'alert-type' => 'success'
        ];

        // Redirect back with a success message
        return redirect()->back()->with($notification);
    }



    public function EditDealerProduct($id){
        $product = DealerStock::findOrFail($id);
        $categories = DealerProductCategory::orderBy('name','ASC')->get();

        return view('agent.dealerstock.dealer_product_edit',compact('product','categories'));


    }//End Method

    public function UpdateDealerProduct(Request $request)
    {
        $product_id = $request->id;

        $validator = Validator::make($request->all(), [
            'product_name' => 'required',
            'product_category_id' => 'required|not_in:--Open this select menu--',
            'product_stock' => 'required|integer|min:0',
            'product_price' => 'required|numeric|min:0',
            'customer_price' =>'required|numeric|min:0',
            'product_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'sku' => 'required',
            'weight' => 'required',
        ], [
            'product_name.required'=> 'Product Name is required',
            'product_category_id.required' => 'Product Category is required',
            'product_stock.required' => 'Product Stock is required',
            'product_price.required' => 'Product Price is required',
            'customer_price.required' => 'Customer Price is required',

            'product_image.required' => 'Product Image is required',
            'sku.required' => 'SKU is required',
            'weight.required' => 'weight is required',
            'long_description.required' => 'Product Description is required',                        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $user_id = Auth::user()->id;

        $productData = $validator->validated();
        $productData['user_id'] = $user_id; // Add user_id to the data

        if ($request->hasFile('product_image')) {
            $image = $request->file('product_image');
            $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
            Image::make($image)->resize(1000, 1000)->save('upload/product/' . $name_gen);
            $save_url = 'upload/product/' . $name_gen;
            $productData['product_image'] = $save_url;
            $message = 'Product Updated with Image Successfully';
        } else {
            $message = 'Product Updated Successfully';
        }

        DealerStock::updateOrCreate(['id' => $product_id], $productData);
  // Update or create entry in the Product table
//   Product::updateOrCreate(['id' => $product_id], $productData);

        $notification = [
            'message' => $message,
            'alert-type' => 'success'
        ];
        return redirect()->route('all.dealer.products')->with($notification);
    }

    public function DeleteDealerProduct($id){

        DealerStock::findOrFail($id)->delete();


        $notification = array(
            'message' =>'Product  Deleted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }//End Method

    public function ProductDetails($id)
{
    $product = DealerStock::find($id);

    return view('admin.stock.stock_details',compact('product'));
}
}
