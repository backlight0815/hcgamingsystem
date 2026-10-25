<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileSignalRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');
        if (! $user) {
            $user = $this->firebaseUser($request);
        }

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error_code' => 'UNAUTHENTICATED',
                'errors' => (object) [],
            ], 401);
        }

        if ((int) $user->status === 0) {
            return response()->json([
                'success' => false,
                'message' => 'This account is suspended.',
                'error_code' => 'ACCOUNT_SUSPENDED',
                'errors' => (object) [],
            ], 403);
        }

        $token = $request->user('sanctum')?->currentAccessToken();
        if (! ModuleAccess::allows($user, ModuleAccess::TRADINGHUB)
            || ($token instanceof \Laravel\Sanctum\PersonalAccessToken && $token->exists && ! $token->can(ModuleAccess::TRADINGHUB))) {
            return response()->json(['success' => false, 'message' => 'TradingHub access is required.', 'error_code' => 'MODULE_ACCESS_DENIED'], 403);
        }

        Auth::setUser($user);
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function firebaseUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        $apiKey = (string) config('trading_signals.firebase_web_api_key');
        if (blank($token) || blank($apiKey)) {
            return null;
        }

        $cacheKey = 'firebase_signal_auth:'.hash('sha256', $token);
        $cachedIdentity = Cache::get($cacheKey);
        if (is_array($cachedIdentity) && isset($cachedIdentity['user_id'])) {
            $request->attributes->set('firebase_uid', $cachedIdentity['firebase_uid'] ?? null);

            return User::find($cachedIdentity['user_id']);
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(max(1, (int) config('trading_signals.firebase_auth_timeout_seconds', 5)))
                ->post('https://identitytoolkit.googleapis.com/v1/accounts:lookup?key='.urlencode($apiKey), [
                    'idToken' => $token,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $email = data_get($response->json(), 'users.0.email');
        $firebaseUid = data_get($response->json(), 'users.0.localId');
        if (! is_string($email) || blank($email)) {
            return null;
        }

        $user = User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
        if ($user) {
            $request->attributes->set('firebase_uid', $firebaseUid);
            Cache::put($cacheKey, [
                'user_id' => $user->id,
                'firebase_uid' => $firebaseUid,
            ], now()->addMinutes(5));
        }

        return $user;
    }
}
