<?php

namespace App\Http\Middleware;

use App\Models\FeatureToggle;
use Closure;
use Illuminate\Http\Request;

class EnsureTradingModuleEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(FeatureToggle::isEnabled(FeatureToggle::MODULE_TRADING), 404);

        return $next($request);
    }
}
