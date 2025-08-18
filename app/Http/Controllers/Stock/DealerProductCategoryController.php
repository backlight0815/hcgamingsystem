<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DealerProductCategory;
use Illuminate\Support\Carbon;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
class DealerProductCategoryController extends Controller
{


    public function AllDealerProductCategory(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Product Category Management', 'url' => route('all.product.category')],

        ];
  // Get the currently logged-in user
  $user = Auth::user();

   // Retrieve product categories associated with the logged-in user along with related data
   $productcategory = $user->dealerProductCategories()
   ->with(['user']) // Assuming 'user' is the relationship between DealerProductCategory and User
   ->latest() // You can use latest() to retrieve the categories in descending order of creation
   ->get();



      return view('agent.dealerstock_product_category.dealer_product_category_all',compact('productcategory','breadcrumbData'));

    }//End Method
    public function AddDealerProductCategory(){
        return view('agent.dealerstock_product_category.dealer_product_category_add');

    }//End Method

    public function StoreDealerProductCategory(Request $request){
        $userId = Auth::user()->id;
        $request -> validate([
            'name' => 'required',



        ],[
            'name.required' =>'Product Category Name is Required',

        ]);




        DealerProductCategory::insert([
            'name'=> $request ->name,
            'user_id'=>$userId,

            'created_at' => Carbon::now(),



        ]);

        ProductCategory::insert([
            'product_category'=>$request->name,
            'created_at' => Carbon::now(),
            'user_id'=>$userId,
        ]);

        $notification = array(
            'message' =>'Product Category Inserted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.dealer.product.category')->with($notification);

    }//End Method

}
