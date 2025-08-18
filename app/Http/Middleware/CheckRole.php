<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next, $roleId)
    {
        if (auth()->check() && auth()->user()->hasRole($roleId)) {
            return $next($request);

        }

        abort(403, 'Unauthorized action.');    }
}
