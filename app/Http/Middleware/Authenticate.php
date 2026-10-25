<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Closure;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        // Unqualified legacy "auth" routes belong to the primary website guard.
        // PMS routes request their guards explicitly and never make a PMS-only
        // session valid for a Dealership or TradingHub route.
        return parent::handle($request, $next, ...($guards ?: ['web']));
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) return null;
        if ($request->routeIs('pms.*')) return route('pms.login');
        $name = $request->route()?->getName() ?? '';
        if (preg_match('/^(dealer\.|customer\.|all\.customer|all\.agent|stock\.|product\.|cart\.|checkout)/', $name)) return route('dealership.login');
        if (preg_match('/^(trading\.|trader\.|admin\.trading|admin\.trader|all\.trading|all\.traders|add\.trading|store\.trading|edit\.trading|delete\.trading)/', $name)) return route('tradinghub.login');
        return route('login');
    }
}
