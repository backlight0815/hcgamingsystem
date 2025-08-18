<?php

namespace App\Http\Controllers\Home;
use Illuminate\Support\Facades\Validator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\BlogCategory;

use Image;
use Illuminate\Support\Carbon;

class BlogController extends Controller
{
    public function AllBlog(){
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Blog Management', 'url' => route('all.blog')],

        ];
        $blogs = Blog::latest()->get();
        return view('admin.blogs.blogs_all',compact('blogs','breadcrumbData'));

            }//end method

            public function AddBlog(){
                $categories = BlogCategory::orderBy('blog_category','ASC')->get();
                return view('admin.blogs.blogs_add',compact('categories'));

            }//End Method

            public function StoreBlog(Request $request)
{
    $validator = Validator::make($request->all(), [
        'blog_category_id' => 'required|not_in:--Open this select menu--',
        'blog_title' => 'required',
        'blog_tags' => 'required',
        'blog_description' => 'required',
        'blog_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
    ], [
        'blog_category_id.required' =>'Blog Category is required',
        'blog_title.required' =>'Blog Title is required',
        'blog_tags.required' =>'Blog Tags is required',
        'blog_description.required' =>'Blog Description is required',
        'blog_image.required' =>'Blog Image is required',

    ]);

    if ($validator->fails()) {
        return redirect()->back()->withErrors($validator)->withInput();
    }

    $image = $request->file('blog_image');
    $name_gen = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
    Image::make($image)->resize(430, 327)->save('upload/blog/' . $name_gen);
    $save_url = 'upload/blog/' . $name_gen;

    $validatedData = $validator->validated();

    Blog::insert([
        'blog_category_id'=>$request->blog_category_id,
        'blog_title'=> $request ->blog_title,
        'blog_tags'=> $request ->blog_tags,
        'blog_description'=> $request ->blog_description,
        'blog_image'=> $save_url,
        'created_at' => Carbon::now(),


    ]);



    $notification = array(
        'message' => 'Blog Inserted Successfully',
        'alert-type' => 'success'
    );

    return redirect()->route('all.blog')->with($notification);
}

public function EditBlog($id){
    $blogs = Blog::findOrFail($id);
    $categories = BlogCategory::orderBy('blog_category','ASC')->get();
    return view('admin.blogs.blogs_edit',compact('blogs','categories'));

}//End Method

public function UpdateBlog(Request $request)
{
    $request -> validate([
        'blog_category_id' => 'required|not_in:--Open this select menu--',
        'blog_title' => 'required',
        'blog_tags' => 'required',
        'blog_description' => 'required',
        'blog_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',

    ],[

    ]);

    $blog_id = $request->id;

    if($request->file('blog_image')){
        $image = $request->file('blog_image');
        $name_gen = hexdec(uniqid()).'.'.$image->getClientOriginalExtension();//34343443.jpg

        Image::make($image)->resize(1020,519)->save('upload/blog/'.$name_gen);
        $save_url = 'upload/blog/'.$name_gen;


        Blog::findOrFail($blog_id)->update([
            'blog_category_id'=>$request->blog_category_id,
            'blog_title'=> $request ->blog_title,
            'blog_tags'=> $request ->blogh_tags,
            'blog_description'=> $request ->blog_description,
            'blog_image'=> $save_url,


        ]);

        $notification = array(
            'message' =>'Blog Updated with Image Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.blog')->with($notification);
    }else{


        Blog::findOrFail($blog_id)->update([
            'blog_category_id'=> $request ->blog_category_id,
            'blog_title'=> $request ->blog_title,
            'blog_tags'=> $request ->blog_tags,
            'blog_description' =>$request->blog_description,


        ]);

        $notification = array(
            'message' =>'Blog without Image Successfully',
            'alert-type' => 'success'
        );
        return redirect()->route('all.blog')->with($notification);





    }//end Else
}

            public function DeleteBlog($id){



            $blog = Blog::findOrFail($id);
            $img = $blog->blog_image;
            unlink($img);

            Blog::findOrFail($id)->delete();


            $notification = array(
                'message' =>'Blog Deleted Successfully',
                'alert-type' => 'success'
            );
            return redirect()->back()->with($notification);
        }//End Method


public function BlogDetails($id){

$allblogs = Blog::latest()->limit(5)->get();
    $blogs = Blog::findOrFail($id);
    $categories = BlogCategory::orderBy('blog_category','ASC')->get();


   // Create a request instance with the route parameters
   $request = Request::create('/blog/details/' . $id, 'GET');

   // Dispatch a request to the route, letting the middleware handle the request
   // Resolve the middleware and apply it to the request
    // Resolve the middleware and apply it to the request
    $middleware = app()->make(\App\Http\Middleware\PageViewMiddleware::class);
    $middleware->handle($request, function ($request) use ($blogs) {
        // This closure is needed to simulate the middleware behavior
    });

    return view('frontend.blog_details',compact('blogs','allblogs','categories'));

}//End Methods

private function handlePageView($blog)
{
    app(\App\Http\Middleware\PageViewMiddleware::class)->handle(request(), function ($request) use ($blog) {
        // This closure is needed to simulate the middleware behavior
    });
}

public function CategoryBlog($id){
    $blogpost = Blog::where('blog_category_id',$id)->orderBy('id','DESC')->get();
    $allblogs = Blog::latest()->limit(5)->get();
    $categories = BlogCategory::orderBy('blog_category','ASC')->get();
    $categoryname = BlogCategory::findOrFail($id);
    return view('frontend.cat_blog_details',compact('blogpost','allblogs','categories','categoryname'));


}//End Method

public function HomeBlog(){

    $categories = BlogCategory::orderBy('blog_category','ASC')->get();
    $allblogs = Blog::latest()->paginate(2);
    return view('frontend.blog',compact('allblogs','categories'));
}


}
