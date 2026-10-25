<?php

namespace App\Http\Middleware;

use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnsurePmsApiAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        abort_unless(ModuleAccess::allows($user, ModuleAccess::PMS)
            && (! $token instanceof PersonalAccessToken || ! $token->exists || $token->can(ModuleAccess::PMS)), 403);

        return $next($request);
    }
}
