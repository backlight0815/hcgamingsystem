<?php

namespace App\Providers;

use App\Models\TradingJournal;
use App\Observers\TradingJournalSyncObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Services\Ai\AiProviderInterface::class, \App\Services\Ai\GeminiAiProvider::class);
        $this->app->bind(\App\Services\MarketInsight\MarketDataProvider::class,
            \App\Services\MarketInsight\MockMarketDataProvider::class);
        $this->app->bind(\App\Services\MarketInsight\RiskContextEvaluator::class,
            \App\Services\MarketInsight\RuleBasedRiskContextEvaluator::class);
        $this->app->bind(\App\Services\MarketInsight\InsightExplanationGenerator::class,
            \App\Services\MarketInsight\RuleBasedInsightExplanationGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TradingJournal::observe(TradingJournalSyncObserver::class);
    }
}
