<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\DealerStock;
use App\Models\ProductCategory;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;
use App\Models\order_items;

use Image;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;


class ProductController extends Controller
{
    public function AllProduct() {
        // Get the currently logged-in user's ID
        $userId = Auth::id();

        // Fetch products owned by the authenticated user
        $product = Product::where('user_id', $userId)->latest()->get();

        // Count the number of products
        $product_index  = $product->count();

        // Create a breadcrumb for navigation
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Product Management', 'url' => route('all.product')],
        ];

        // Pass the products, product count, and breadcrumb data to the view
        return view('admin.product_management.product_all', compact('product', 'product_index', 'breadcrumbData'));
    }


            public function AddProduct(){
                $categories = ProductCategory::orderBy('product_category','ASC')->get();
                return view('admin.product_management.product_add',compact('categories'));

            }//End Method



           public function StoreProduct(Request $request)
            {
    // Get the authenticated user's ID
    $userId = Auth::id();
                $validator = Validator::make($request->all(), [
                    'product_name'  => 'required',
                    'product_category_id' => 'required|not_in:--Open this select menu--',
                    'product_stock' => 'required|integer|min:0',
                    'product_price' => 'required|numeric|min:0',
                    'customer_price' => 'required|numeric|min:0',

                    'product_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
                    'sku' => 'required',
                    'weight' => 'required',
                    'long_description' => 'required',
                ], [
                    'product_name.required' => 'Product Name is required',
                    'product_category_id.required' => 'Product Category is required',
                    'product_stock.required' => 'Product Stock is required',
                    'product_price.required' => 'Product Price is required',
                    'customer_price.required' => 'Customer Price is required',

                    'product_image.required' => 'Product Image is required',
                    'sku.required' => 'SKU is required',
                    'weight.required' => 'Weight is required',
                    'long_description.required' => 'Product Description is required',
                ]);

                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $image = $request->file('product_image');
                $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
                Image::make($image)->resize(1000, 1000)->save('upload/product/' . $name_gen);
                $save_url = 'upload/product/' . $name_gen;

                $validatedData = $validator->validated();

                Product::create([
                    'product_name' => $validatedData['product_name'],
                    'dealer_stock_id'=> $userId,
                    'sku' => $validatedData['sku'],
                    'weight' => $validatedData['weight'],
                    'product_category_id' => $validatedData['product_category_id'],
                    'product_stock' => $validatedData['product_stock'],
                    'long_description' => $validatedData['long_description'],
                    'product_price' => $validatedData['product_price'],
                    'customer_price' => $validatedData['customer_price'],
                    'user_id' => $userId, // Assign the user ID to the product

                    'product_image' => $save_url,
                    'created_at' => Carbon::now(),
                ]);

                // Store a flag in the session to indicate successful form submission
                // $sessionExpiration = now()->addSeconds(1); // Change the number of seconds as needed
                // Session::put('product_submitted', $sessionExpiration);

                $notification = [
                    'message' => 'Product Inserted Successfully',
                    'alert-type' => 'success'
                ];
                return redirect()->route('all.product')->with($notification);
            }
            public function EditProduct($id){
                $product = Product::findOrFail($id);
                $categories = ProductCategory::orderBy('product_category','ASC')->get();

                return view('admin.product_management.product_edit',compact('product','categories'));


            }//End Method

                    public function UpdateProduct(Request $request)
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

                        $productData = $validator->validated();

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

                        Product::updateOrCreate(['id' => $product_id], $productData);

                        $notification = [
                            'message' => $message,
                            'alert-type' => 'success'
                        ];
                        return redirect()->route('all.product')->with($notification);
                    }



                    public function DeleteProduct($id){

                        Product::findOrFail($id)->delete();


                        $notification = array(
                            'message' =>'Product  Deleted Successfully',
                            'alert-type' => 'success'
                        );
                        return redirect()->back()->with($notification);
                    }//End Method




                    public function ProductDetails($id)
                    {
                        // Attempt to find the product in the Product table
                        $product = Product::find($id);

                        // If product not found in Product table, attempt to find it in the DealerStock table
                        if (!$product) {
                            $product = DealerStock::find($id);
                        }

                        // If neither found, redirect to an error page or show a 404 error
                        if (!$product) {
                            return redirect()->route('my.stock')->with('error', 'Product not found.');
                        }

                        // Normalize the properties to a standard structure
                        $normalizedStock = [
                            'type' => $product instanceof Product ? 'product' : 'dealer',
                            'id' => $product->id,
                            'name' => $product instanceof Product ? $product->product_name : $product->product_name,
                            'image' => $product instanceof Product ? $product->product_image : $product->product_image,
                            'price' => $product instanceof Product ? $product->product_price : $product->product_price,
                            'stock' => $product instanceof Product ? $product->product_stock : $product->product_stock,
                            'weight' => $product instanceof Product ? $product->weight : $product->weight,
                            'sku' => $product instanceof Product ? $product->sku : $product->sku,
                            'long_description' => $product instanceof Product ? $product->long_description : $product->long_description,
                            'user' => $product->user->username ?? 'Unknown'
                        ];

                        // Pass the normalized data to the view
                        return view('admin.stock.stock_details', compact('normalizedStock'));
                    }




}
