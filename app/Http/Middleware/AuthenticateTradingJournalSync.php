<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTradingJournalSync
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');
        if (! $user) {
            $user = $this->resolveFirebaseUser($request);
            if ($user instanceof Response) {
                return $user;
            }
        }
        if ((int) $user->status === 0) {
            return $this->error('Account suspended.', 'ACCOUNT_SUSPENDED', 403);
        }
        $token = $request->user('sanctum')?->currentAccessToken();
        if (! ModuleAccess::allows($user, ModuleAccess::TRADINGHUB)
            || ($token instanceof \Laravel\Sanctum\PersonalAccessToken && $token->exists && ! $token->can(ModuleAccess::TRADINGHUB))) {
            return $this->error('TradingHub access is required.', 'MODULE_ACCESS_DENIED', 403);
        }
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }

    private function resolveFirebaseUser(Request $request): User|Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return $this->error('Sign in to synchronize your journal.', 'UNAUTHENTICATED', 401);
        }
        $key = (string) config('trading_signals.firebase_web_api_key');
        $emulator = config('trading_journal.firebase_auth_emulator_host');
        $localEmulator = app()->environment(['local', 'testing']) && is_string($emulator)
            && preg_match('/^(127\.0\.0\.1|localhost):[0-9]{2,5}$/', $emulator);
        if (! $key && ! $localEmulator) {
            return $this->error('Journal authentication is temporarily unavailable.', 'AUTH_CONFIGURATION_UNAVAILABLE', 503);
        }
        try {
            $response = Http::acceptJson()->asJson()
                ->timeout(max(1, (int) config('trading_signals.firebase_auth_timeout_seconds', 5)))
                ->post(($localEmulator ? 'http://'.$emulator.'/identitytoolkit.googleapis.com' : 'https://identitytoolkit.googleapis.com')
                    .'/v1/accounts:lookup?key='.urlencode($localEmulator ? 'demo-journal' : $key), ['idToken' => $token]);
        } catch (\Throwable) {
            return $this->error('The authentication provider is temporarily unavailable.', 'AUTH_PROVIDER_UNAVAILABLE', 503);
        }
        if ($response->serverError() || $response->status() === 429) {
            return $this->error('The authentication provider is temporarily unavailable.', 'AUTH_PROVIDER_UNAVAILABLE', 503);
        }
        if (! $response->successful()) {
            return $this->error('Your session has expired. Sign in again.', 'INVALID_TOKEN', 401);
        }
        $identity = data_get($response->json(), 'users.0');
        $uid = data_get($identity, 'localId');
        if (! is_string($uid) || $uid === '') {
            return $this->error('Your session has expired. Sign in again.', 'INVALID_TOKEN', 401);
        }
        // A previously linked Firebase UID remains the canonical identity if
        // either account later changes its email address.
        $linked = DB::table('trading_journal_identities')->where('firebase_uid', $uid)->first();
        if ($linked) {
            $user = User::find($linked->user_id);
            return $user ?? $this->error('The linked website account is unavailable.', 'JOURNAL_ACCOUNT_MAPPING_UNAVAILABLE', 409);
        }
        $email = data_get($identity, 'email');
        if (! is_string($email) || trim($email) === '' || data_get($identity, 'emailVerified') !== true) {
            return $this->error('Verify your mobile account email before linking the journal.', 'EMAIL_VERIFICATION_REQUIRED', 403);
        }
        $normalized = mb_strtolower(trim($email));
        $users = User::whereRaw('LOWER(email) = ?', [$normalized])->limit(2)->get();
        if ($users->count() !== 1) {
            return $this->error('No unique website account matches this verified email.', 'JOURNAL_ACCOUNT_MAPPING_UNAVAILABLE', 409);
        }
        $user = $users->first();
        if (DB::table('trading_journal_identities')->where('user_id', $user->id)->exists()) {
            return $this->error('This website account is linked to a different mobile identity.', 'JOURNAL_ACCOUNT_MAPPING_CONFLICT', 409);
        }
        try {
            DB::table('trading_journal_identities')->insert([
                'user_id' => $user->id,
                'firebase_uid' => $uid,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            return $this->error('The account link could not be completed. Please retry.', 'JOURNAL_ACCOUNT_MAPPING_CONFLICT', 409);
        }

        return $user;
    }

    private function error(string $message, string $code, int $status): Response
    {
        return response()->json(['success' => false, 'message' => $message, 'error_code' => $code], $status);
    }
}
