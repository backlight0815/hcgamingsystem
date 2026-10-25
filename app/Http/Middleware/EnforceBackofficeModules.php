<?php

namespace App\Http\Middleware;

use App\Support\FrontendExperience;
use App\Support\ModuleAccess;
use Closure;
use Illuminate\Http\Request;

class EnforceBackofficeModules
{
    private const TRADING_CONTROLLERS = [
        'App\\Http\\Controllers\\Trading\\',
        'App\\Http\\Controllers\\Home\\CommunityShowcaseController@',
    ];

    private const ECOMMERCE_CONTROLLERS = [
        'App\\Http\\Controllers\\Dealer\\',
        'App\\Http\\Controllers\\Product\\',
        'App\\Http\\Controllers\\Stock\\',
        'App\\Http\\Controllers\\Commission\\',
        'App\\Http\\Controllers\\Sales_Performances\\',
        'App\\Http\\Controllers\\Event\\',
        'App\\Http\\Controllers\\Home\\About_Page\\',
        'App\\Http\\Controllers\\Home\\BlogController@',
        'App\\Http\\Controllers\\Home\\BlogCategoryController@',
        'App\\Http\\Controllers\\Home\\ServiceController@',
        'App\\Http\\Controllers\\Home\\HomeSliderController@',
        'App\\Http\\Controllers\\Home\\PortfolioController@',
        'App\\Http\\Controllers\\Home\\RecruitmentController@',
        'App\\Http\\Controllers\\Home\\ContactController@',
    ];

    private const TRADING_ACCOUNT_ACTIONS = [
        'AllSignalProvider', 'EditSignalProviderAccount', 'UpdateSignalProvider',
        'AllTraders', 'EditTradersAccount', 'UpdateTraders', 'updateTradersStatus',
    ];

    private const ECOMMERCE_ACCOUNT_ACTIONS = [
        'AllAgent', 'EditAgentAccount', 'UpdateAgent', 'updateAgentStatus',
        'AllCustomer', 'EditCustomerAccount', 'UpdateCustomer', 'updateCustomerStatus',
    ];

    public function handle(Request $request, Closure $next)
    {
        $action = $request->route()?->getActionName() ?? '';
        $name = $request->route()?->getName() ?? '';
        $protected = collect($request->route()?->gatherMiddleware() ?? [])
            ->contains(fn ($middleware) => str_starts_with($middleware, 'auth') || str_contains($middleware, 'Authenticate'));
        $trading = $this->matches($action, self::TRADING_CONTROLLERS)
            || $this->accountAction($action, self::TRADING_ACCOUNT_ACTIONS)
            || (bool) preg_match('/^(trading\.|trader\.|admin\.trading|admin\.trader|all\.trading|all\.traders|add\.trading|store\.trading|edit\.trading|delete\.trading|activate\.trading|close\.trading|cancel\.trading)/', $name);
        $commerce = $this->matches($action, self::ECOMMERCE_CONTROLLERS)
            || $this->accountAction($action, self::ECOMMERCE_ACCOUNT_ACTIONS)
            || (bool) preg_match('/^(dealer\.|customer\.|all\.customer|all\.agent|stock\.|product\.|order\.|checkout|cart\.)/', $name);

        if ($trading && ! FrontendExperience::enabled(FrontendExperience::TRADING)) {
            abort(404);
        }

        if ($commerce && ! FrontendExperience::enabled(FrontendExperience::ECOMMERCE)) {
            abort(404);
        }

        if ($protected && auth('web')->check()) {
            if ($trading && ! ModuleAccess::allows(auth('web')->user(), ModuleAccess::TRADINGHUB)) abort(403);
            if ($commerce && ! ModuleAccess::allows(auth('web')->user(), ModuleAccess::DEALERSHIP)) abort(403);
        }

        return $next($request);
    }

    private function matches(string $action, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($action, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function accountAction(string $action, array $methods): bool
    {
        if (! str_starts_with($action, 'App\\Http\\Controllers\\Home\\AccountController@')) {
            return false;
        }

        return in_array(substr($action, strrpos($action, '@') + 1), $methods, true);
    }
}
