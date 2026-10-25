<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Demo\demoController;
use App\Models\Product;
use App\Models\TradingBlog;
use App\Models\TradingSignal;
use App\Support\FrontendExperience;
use App\Support\TradingSubmodules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FrontendExperienceController extends Controller
{
    public function home()
    {
        if (FrontendExperience::homepage() === FrontendExperience::NEUTRAL && \App\Support\PmsModule::publicAuthMode()) {
            return redirect()->route('pms.home');
        }
        return match (FrontendExperience::homepage()) {
            FrontendExperience::TRADING => $this->tradingHome(),
            FrontendExperience::ECOMMERCE => app(demoController::class)->HomeMain(),
            FrontendExperience::COMBINED => $this->combined(),
            default => view('experiences.neutral'),
        };
    }

    public function tradingHome()
    {
        $blogs = TradingSubmodules::enabled('trading_blog') ? TradingBlog::websitePublished()->latest('published_at')->limit(3)->get() : collect();
        $signals = TradingSubmodules::enabled('trading_signals') ? $this->visibleSignals()->limit(3)->get() : collect();

        return view('experiences.trading.home', compact('blogs', 'signals'));
    }

    public function combined()
    {
        $blogs = TradingSubmodules::enabled('trading_blog') ? TradingBlog::websitePublished()->latest('published_at')->limit(2)->get() : collect();
        $products = Product::storefront()->latest()->limit(3)->get();

        return view('experiences.combined', compact('blogs', 'products'));
    }

    public function insights(Request $request)
    {
        $publishedCategories = TradingBlog::websitePublished()->distinct()->pluck('category')->all();
        $categories = array_intersect_key(TradingBlog::categories(), array_flip($publishedCategories));
        $category = (string) $request->query('category', '');
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 80);
        $query = TradingBlog::websitePublished()->latest('published_at');
        if (array_key_exists($category, $categories)) {
            $query->where('category', $category);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('excerpt', 'like', '%'.$search.'%');
            });
        }
        $blogs = $query->paginate(9)->withQueryString();

        return view('experiences.trading.insights', compact('blogs', 'categories', 'category', 'search'));
    }

    public function signals(Request $request)
    {
        $query = $this->visibleSignals();
        $status = strtoupper((string) $request->query('status', ''));
        $direction = strtoupper((string) $request->query('direction', ''));
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 60);
        if (in_array($status, [TradingSignal::WORKFLOW_PUBLISHED, TradingSignal::WORKFLOW_CLOSED, TradingSignal::WORKFLOW_CANCELLED, TradingSignal::WORKFLOW_EXPIRED], true)) {
            $query->where('workflow_status', $status);
        }
        if (in_array($direction, ['BUY', 'SELL'], true)) {
            $query->where('immediate_action', $direction);
        }
        if ($search !== '') {
            $query->where('trading_pair', 'like', '%'.$search.'%');
        }
        $signals = $query->orderByDesc('published_at')->paginate(12)->withQueryString();

        return view('experiences.trading.signals', compact('signals', 'status', 'direction', 'search'));
    }

    public function about()
    {
        return view('experiences.trading.about');
    }

    public function services()
    {
        return view('experiences.trading.services');
    }

    public function contact()
    {
        return view('experiences.trading.contact');
    }

    private function visibleSignals()
    {
        // Existing signal policy requires an authenticated member for signal data.
        if (! auth()->check() || ! Gate::allows('viewAny', TradingSignal::class)) {
            return TradingSignal::query()->whereRaw('1 = 0');
        }

        return TradingSignal::published()
            ->where('status', '>=', 1)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(function ($query): void {
                $query->whereNull('archived')->orWhere('archived', false);
            });
    }
}
