<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Blog;

class PageViewMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the blog post based on your route parameters
   // Get the blog post based on your route parameters
   $blogId = $request->route('id'); // Adjust this based on your route parameter name
   $blog = Blog::find($blogId);
        if ($blog) {
            // Increment the page view count
            $blog->increment('page_views');
        }

        // Continue with the request and return an empty response
        return $next($request) ?: new Response();
    }
}
