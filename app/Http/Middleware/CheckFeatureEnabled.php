<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFeatureEnabled
{
    public function handle(Request $request, Closure $next, $featureName)
    {
        $enabled = str_starts_with($featureName, 'module_')
            ? module_enabled($featureName)
            : feature_enabled($featureName);

        if (!$enabled) {
            if (!$request->expectsJson()) {
                abort(403, 'This feature is currently disabled.');
            }

            return response()->json([
                'message' => 'This feature is currently disabled.'
            ], 403);
        }

        return $next($request);
    }
}
