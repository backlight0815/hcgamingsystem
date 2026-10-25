<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnsurePmsApiBearer
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();
        abort_unless($request->bearerToken() && $token instanceof PersonalAccessToken && $token->can('pms'), 401);
        abort_if($token->expires_at && $token->expires_at->isPast(), 401);

        return $next($request);
    }
}
