<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFeatureEnabled
{
    public function handle(Request $request, Closure $next, $featureName)
    {
        if (!feature_enabled($featureName)) {
            return response()->json([
                'message' => 'This feature is currently disabled.'
            ], 403);
        }

        return $next($request);
    }
}
