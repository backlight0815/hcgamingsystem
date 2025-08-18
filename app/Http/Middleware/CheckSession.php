<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            // User is not authenticated (session expired)
            // You can customize the redirection logic here
            return redirect()->route('login')->with('message', 'Your session has expired. Please log in again.');
        }

        return $next($request);    }
}
