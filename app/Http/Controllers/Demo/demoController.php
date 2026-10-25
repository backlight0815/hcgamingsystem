<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\About;
use App\Models\Blog;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;

class demoController extends Controller
{

    public function HomeMain(){
        return view('frontend.index', [
            'slide' => HomeSlide::first(),
            'about' => About::first(),
            'categories' => ProductCategory::whereHas('products', fn ($query) => $query->storefront())->orderBy('product_category')->limit(5)->get(),
            'featuredProducts' => Product::storefront()->with('productcategory')->latest()->limit(6)->get(),
            'services' => Service::storefront()->latest()->limit(4)->get(),
            'latestBlogs' => Blog::storefront()->with('category')->latest()->limit(3)->get(),
        ]);
    }//End Method
    public function Index(){
        return view('about');
    }
public function ContactMethod(){
    return view('contact');
}

}
