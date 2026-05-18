<?php

namespace App\Http\Middleware;

use App\Models\TraderOnboardingApplication;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureTraderOnboardingApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user
            || (int) $user->role_id !== 750
            || $this->isAllowedRoute($request)
            || ! Schema::hasTable('trader_onboarding_applications')
        ) {
            return $next($request);
        }

        $latestApplication = TraderOnboardingApplication::where('user_id', $user->id)
            ->latest('id')
            ->first();

        if ($latestApplication && $latestApplication->isApproved()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Trader verification is required before this account can access the platform.',
            ], 423);
        }

        return redirect()
            ->route('trader.onboarding.show')
            ->with([
                'message' => 'Trader verification is required before your account can access the platform.',
                'alert-type' => 'warning',
            ]);
    }

    private function isAllowedRoute(Request $request): bool
    {
        return $request->routeIs(
            'trader.onboarding.*',
            'trader.readiness.*',
            'logout',
            'admin.logout',
            'verification.*',
            'password.confirm',
            'password.update'
        );
    }
}
