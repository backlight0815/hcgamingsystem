<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ProductCategoryController extends Controller
{
    public function AllProductCategory(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Product Category Management', 'url' => route('all.product.category')],

        ];
        $productcategory = ProductCategory::latest()->get();
        return view('admin.product_category.product_category_all',compact('productcategory','breadcrumbData'));

    }//End Method

    public function AddProductCategory(){
        return view('admin.product_category.product_category_add');

    }//End Method

    public function StoreProductCategory(Request $request){
        $userId = Auth::user()->id;

        $request -> validate([
            'product_category' => 'required',



        ],[
            'product_category.required' =>'Product Category Name is Required',

        ]);




        ProductCategory::insert([
            'product_category'=> $request ->product_category,
            'user_id'=>$userId,
            'created_at' => Carbon::now(),



        ]);

        $notification = array(
            'message' =>'Product Category Inserted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.product.category')->with($notification);

    }//End Method

    public function EditProductCategory($id){
        $productcategory = ProductCategory::findOrFail($id);
        return view('admin.product_category.product_category_edit',compact('productcategory'));

    }//End Method

    public function UpdateProductCategory(Request $request,$id){
        $request -> validate([
            'product_category' => 'required',



        ],[
            'product_category.required' =>'Product Category Name is Required',

        ]);

        ProductCategory::findOrFail($id)->update([
            'product_category'=> $request ->product_category,



        ]);

        $notification = array(
            'message' =>'Product Category Updated Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.product.category')->with($notification);

    }//End Method

    public function DeleteProductCategory($id){

        ProductCategory::findOrFail($id)->delete();


        $notification = array(
            'message' =>'Product Category Deleted Successfully',
            'alert-type' => 'success'
        );
        return redirect()->back()->with($notification);
    }//End Method
}
