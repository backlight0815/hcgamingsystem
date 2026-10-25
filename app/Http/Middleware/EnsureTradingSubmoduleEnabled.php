<?php

namespace App\Http\Middleware;

use App\Models\FeatureToggle;
use App\Support\TradingSubmodules;
use Closure;
use Illuminate\Http\Request;

class EnsureTradingSubmoduleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        $key = TradingSubmodules::keyForRoute($request->route()?->getName());
        if (! $key) {
        return $next($request);
        }

        // Preserve the existing top-level TradingHub visibility policy.
        if (! FeatureToggle::isEnabled(FeatureToggle::MODULE_TRADING)) {
        abort(404);
        }
        if (TradingSubmodules::enabled($key)) {
        return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['code' => 'MODULE_DISABLED', 'message' => 'This feature is currently unavailable.'], 503);
        }

        return response()->view('errors.module-disabled', [], 503);
    }
}
