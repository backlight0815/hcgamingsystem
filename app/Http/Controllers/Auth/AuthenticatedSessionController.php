<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\FinalExamService;
use App\Support\ModuleAccess;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login', ['module' => null]);
    }

    public function moduleLogin(string $module): View
    {
        abort_unless(ModuleAccess::enabled($module), 404);
        return view('auth.login', ['module' => $module]);
    }

    public function tradingLogin(): View { return $this->moduleLogin(ModuleAccess::TRADINGHUB); }
    public function dealershipLogin(): View { return $this->moduleLogin(ModuleAccess::DEALERSHIP); }
    public function tradingAuthenticate(Request $request): RedirectResponse { return $this->moduleAuthenticate($request, ModuleAccess::TRADINGHUB); }
    public function dealershipAuthenticate(Request $request): RedirectResponse { return $this->moduleAuthenticate($request, ModuleAccess::DEALERSHIP); }

    public function moduleAuthenticate(Request $request, string $module): RedirectResponse
    {
        abort_unless(ModuleAccess::enabled($module), 404);
        $data = $request->validate([
            'username' => ['required', 'string'], 'password' => ['required', 'string'],
        ]);
        $field = filter_var($data['username'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::where($field, $data['username'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! ModuleAccess::allows($user, $module)) {
            return back()->withInput($request->only('username'))
                ->withErrors(['username' => 'Unable to sign in to this module. Please verify your credentials and access permissions.']);
        }

        Auth::guard('pms')->logout();
        Auth::guard('web')->login($user, $request->boolean('remember'));
        Auth::shouldUse('web');
        $request->session()->regenerate();
        if ($user->isGlobalSuperAdmin()) {
            \App\Support\AdminAudit::record($user, 'auth.login', 'user', $user->id, ['guard' => 'web', 'source' => $module]);
        }
        $destination = ModuleAccess::safeIntended($request, $module) ?? route('all.statistics');
        $request->session()->forget('url.intended');
        return redirect($destination);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $login = $request->input('username', $request->input('email'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $login,
            'password' => $request->input('password'),
        ];

        if (! Auth::guard('web')->attempt($credentials)) {
            $request->session()->regenerate();

            $notification = [
                'message' => 'Invalid username or password',
                'alert-type' => 'error',
            ];

            return redirect()->back()->withInput()->with($notification);

        }
        Auth::shouldUse('web');
        $user = Auth::guard('web')->user();
        if ((string) $user->status !== '1' || (! $user->isBackofficeAdministrator()
            && ! ModuleAccess::allows($user, ModuleAccess::TRADINGHUB)
            && ! ModuleAccess::allows($user, ModuleAccess::DEALERSHIP))) {
            Auth::guard('web')->logout();
            $request->session()->regenerate();
            return back()->withInput($request->only('username', 'email'))
                ->withErrors(['username' => 'Unable to sign in. Please verify your credentials and access permissions.']);
        }
        // Only one browser identity should own the shared session at a time.
        Auth::guard('pms')->logout();
        Auth::shouldUse('web');
        // $request->authenticate();

        $request->session()->regenerate();

        if (Auth::user()->isGlobalSuperAdmin()) {
            \App\Support\AdminAudit::record(Auth::user(), 'auth.login', 'user', Auth::id(), ['guard' => 'web']);
        }

$notification = [
    'message' => 'User Login Successfully',
    'alert-type' => 'success',
];

if (app(FinalExamService::class)->requiresExam(Auth::user())) {
    return redirect()
        ->route('final_exam.intro')
        ->with($notification);
}

$intended = $request->session()->get('url.intended');
$local = is_string($intended) && str_starts_with($intended, $request->getSchemeAndHttpHost().'/')
    && ! str_starts_with((string) parse_url($intended, PHP_URL_PATH), '/backoffice/project-management');
$request->session()->forget('url.intended');
return redirect($local ? $intended : RouteServiceProvider::HOME)->with($notification);

    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if (Auth::guard('web')->user()?->isGlobalSuperAdmin()) {
            \App\Support\AdminAudit::record(Auth::guard('web')->user(), 'auth.logout', 'user', Auth::guard('web')->id());
        }
        Auth::guard('web')->logout();
        Auth::guard('pms')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
