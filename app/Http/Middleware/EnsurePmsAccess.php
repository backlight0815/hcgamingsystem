<?php

namespace App\Http\Middleware;

use App\Models\FeatureToggle;
use App\Support\PmsAccess;
use App\Services\PmsSessionLease;
use Illuminate\Support\Facades\Auth;
use Closure;
use Illuminate\Http\Request;

class EnsurePmsAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(FeatureToggle::isEnabled(FeatureToggle::MODULE_PROJECT_MANAGEMENT, false), 404);
        abort_unless(PmsAccess::workspace($request->user()), 403);
        if ($request->hasSession() && ! app(PmsSessionLease::class)->inspect($request, $request->user())) {
            $request->session()->put('pms_expired', true);
            $request->session()->put('pms_expired_user_id', $request->user()->id);
            Auth::guard('pms')->logout();
            return $request->expectsJson()
                ? response()->json(['message' => 'Your PMS session expired. Sign in again.'], 401)
                : redirect()->route('pms.login')->with('error', 'Your PMS session expired. Sign in again.');
        }

        return $next($request);
    }
}
