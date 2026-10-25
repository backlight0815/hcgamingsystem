<?php

namespace App\Http\Middleware;

use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;

class EnsureTradingApiAccess
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();
        abort_unless(ModuleAccess::allows($request->user(), ModuleAccess::TRADINGHUB)
            && (! $token instanceof \Laravel\Sanctum\PersonalAccessToken || ! $token->exists || $token->can(ModuleAccess::TRADINGHUB)), 403);

        return $next($request);
    }
}
