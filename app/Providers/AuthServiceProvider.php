<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\TradingBlog;
use App\Models\TradingSignal;
use App\Policies\TradingBlogPolicy;
use App\Policies\TradingSignalPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        TradingBlog::class => TradingBlogPolicy::class,
        TradingSignal::class => TradingSignalPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Role 1 is the existing server-side platform administrator assignment.
        // Module enablement, authentication, and workflow safety checks remain outside Gate.
        Gate::before(fn ($user) => $user->isGlobalSuperAdmin() ? true : null);

        Gate::define('admin', function ($user) {
            return $user->isBackofficeAdministrator();
        });

        Gate::define('agent', function ($user) {
            return $user->hasRole('agent');
        });
    }
}
