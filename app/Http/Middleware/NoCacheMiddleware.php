<?php

namespace App\Http\Middleware;

use Closure;

class NoCacheMiddleware
{
    public function handle($request, Closure $next)
    {
        // $response = $next($request);
        // $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        // $response->header('Pragma', 'no-cache');
        // $response->header('Expires', '0');
        // return $response;
    }
}
