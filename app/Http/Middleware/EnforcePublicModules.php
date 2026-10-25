<?php

namespace App\Http\Middleware;

use App\Support\FrontendExperience;
use Closure;
use Illuminate\Http\Request;

class EnforcePublicModules
{
    private const COMMERCE_ROUTES = [
        'store.home', 'home.product', 'stock.details', 'home.about',
        'home.service', 'service.details', 'home.blog', 'category.blog',
        'blog.details', 'contact.me', 'contact.page', 'cart.add',
        'guest.cart.add', 'cart.total', 'guest.cart.total', 'cart.summary',
        'guest.cart.summary', 'remove.cart', 'cart.update', 'checkout',
        'payment', 'success.checkout', 'cart.empty',
    ];

    private const TRADING_ROUTES = [
        'community.showcase',
    ];

    private const TRADING_PREFIXES = [
        'trading.hub.', 'trading.blogs.', 'member.signals.',
        'trading.knowledge.centre.', 'trading.market-analyst.',
        'trading.recordings.', 'trading.appointments.', 'trading.exams.',
        'trading.positions.', 'trader.readiness.', 'trading.leaderboard.',
        'trading.backtest.',
    ];

    public function handle(Request $request, Closure $next)
    {
        $name = $request->route()?->getName() ?? '';
        if (in_array($name, self::COMMERCE_ROUTES, true)
            && ! FrontendExperience::enabled(FrontendExperience::ECOMMERCE)) {
            abort(404);
        }
        if ((in_array($name, self::TRADING_ROUTES, true)
            || collect(self::TRADING_PREFIXES)->contains(fn ($prefix) => str_starts_with($name, $prefix)))
            && ! FrontendExperience::enabled(FrontendExperience::TRADING)) {
            abort(404);
        }

        return $next($request);
    }
}
