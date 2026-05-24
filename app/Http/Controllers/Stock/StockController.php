<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\DealerStock;

class StockController extends Controller
{


    public function MyStock() {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Product Catalogue', 'url' => route('my.stock')],
        ];

        // Fetch products with their associated user
        $products = Product::with('user')->latest()->get();

        // Fetch dealer stock data with their associated user where publish_status is 1
        $dealerStocks = DealerStock::with('user')
            ->where('publish_status', 1)
            ->latest()
            ->get();

        // Merge product data with dealer stock data
        $mergedData = $products->map(function($product) {
            $product->type = 'product';
            $product->product_id = $product->id; // Ensure product_id is set for products
            $product->stock = $product->product_stock;
            $product->dealer_stock_id = null; // Set dealer_stock_id to null for products
            return $product;
        })->merge($dealerStocks->map(function($dealerStock) {
            $dealerStock->type = 'dealer_stock';
            $dealerStock->product_id = null; // Set product_id to null for dealer stocks
            $dealerStock->dealer_stock_id = $dealerStock->id; // Set dealer_stock_id for dealer stocks
            return $dealerStock;
        }));

        // Check stock status of each item
        $allOutOfStock = $mergedData->every(function($item) {
            return $item->stock <= 0;
        });

        return view('admin.stock.stock_all', compact('mergedData', 'breadcrumbData', 'allOutOfStock'));
    }


            public function HomeProduct(){


                $product = Product::latest()->get();
                return view('frontend.stock',compact('product'));


                }//End Methods



                public function StockDetails($id) {
                    $stock = Product::with('productcategory')->find($id);

                    if (!$stock) {
                        return redirect()->route('home.product')->with('error', 'Stock not found.');
                    }

                    return view('frontend.stock_details_estore', compact('stock'));
                }


}
