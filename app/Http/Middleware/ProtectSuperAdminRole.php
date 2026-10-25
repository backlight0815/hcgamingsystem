<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class ProtectSuperAdminRole
{
    public function handle(Request $request, Closure $next)
    {
        $actor = $request->user();
        $targetId = $request->route('id') ?? $request->input('id');
        $target = $targetId ? User::find($targetId) : null;
        $requestedRole = $request->input('account_role', $request->input('role_id'));

        if (! $actor->isGlobalSuperAdmin()) {
            abort_if($target && (int) $target->role_id === User::ROLE_SUPER_ADMIN, 403);
            abort_if($requestedRole !== null && (int) $requestedRole === User::ROLE_SUPER_ADMIN, 403);
        }

        if ($target && (int) $target->role_id === User::ROLE_SUPER_ADMIN
            && User::where('role_id', User::ROLE_SUPER_ADMIN)->where('status', 1)->count() <= 1
            && (($requestedRole !== null && (int) $requestedRole !== User::ROLE_SUPER_ADMIN)
                || ($request->exists('account_status') && (string) $request->input('account_status') !== '1')
                || ($request->exists('status') && (string) $request->input('status') !== '1')
                || $request->routeIs('delete.account'))) {
            abort(422, 'The final active super administrator cannot be removed.');
        }

        return $next($request);
    }
}
