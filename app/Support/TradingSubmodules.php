<?php

namespace App\Support;

use App\Models\FeatureToggle;
use Illuminate\Support\Str;

/** One registry for TradingHub settings, navigation and HTTP access. */
final class TradingSubmodules
{
    public const VERSION = 1;

    private const MODULES = [
        'marketing_resources' => ['Marketing Resources', ['admin.marketing.resources.*', 'marketing.resources.*', 'api.mobile.v1.marketing_resources.*']],
        'support_tickets' => ['Support Tickets', ['support.tickets.*']],
        'ai_assistant' => ['AI Assistant', ['ai.assistant.*', 'admin.ai_knowledge_base.*', 'admin.ai_settings.*']],
        'appointments' => ['Appointments', ['trading.appointments.*', 'admin.trading.appointments.*']],
        'trading_exams' => ['Trading Exams', ['trading.exams.*', 'admin.trading.exams.*', 'final_exam.*', 'admin.final_exam.*', 'api.mobile.v1.exam.*', 'api.mobile.v1.final_exam.*']],
        'notifications' => ['Notifications', ['notifications.*', 'admin.notifications.*']],
        'community' => ['Community', ['communities.*', 'admin.community.showcase.*', 'community.showcase']],
        'trading_news' => ['Trading News', ['trading.news.*']],
        'signal_provider_desk' => ['Signal Provider Desk', ['all.trading.signals', 'add.trading.signal', '*.trading.signal', 'all.trading.reason', 'add.trading.reason', '*.trading.reason', 'signal.performance.*']],
        'trading_certificates' => ['Trading Certificates', ['certificate.*']],
        'my_certificates' => ['My Certificates', ['provider.certificate.*', 'certificates.index']],
        'knowledge_centre' => ['Knowledge Centre', ['knowledge.centre.*', 'trading.knowledge.centre.*']],
        'market_analyst' => ['Market Analyst', ['market-analyst.*', 'trading.market-analyst.*', 'trading.hub.outlooks.*', 'api.market_outlooks.*']],
        'trading_signals' => ['Trading Signals', ['member.signals.*', 'trading.hub.signals', 'api.mobile.v1.signals.*']],
        'provider_accounts' => ['Provider Accounts', ['all.signal_provider', 'edit.signal_provider.account', 'update.signal_provider']],
        'leaderboard' => ['Leaderboard', ['trading.leaderboard.*']],
        'trading_journal' => ['Trading Journal', ['all.trading.journals', 'add.trading.journal', '*.trading.journal', 'trading.journal.*', 'trading-journal.*', 'store.trading.deposit', 'download.trades.template', 'api.mobile.v1.journals.*', 'api.mobile.v1.journal_sync.*', 'api.mobile.v1.trading_accounts.*', 'api.mobile.v1.trading_data']],
        'readiness_checklist' => ['Readiness Checklist', ['trader.readiness.*']],
        'trading_statistics' => ['Trading Statistics', ['all.trading.statistics*']],
        'ai_market_insight' => ['AI Market Insight', ['market_insight.*', 'admin.market_insight.*', 'api.mobile.v1.market_insights.*']],
        'prop_firm' => ['Prop Firm', ['propfirm.leaderboard.*', 'admin.funded_traders.*', 'trading.propfirm.*', 'admin.trader.journals.prop_firm_config.update']],
        'backtest_lab' => ['Backtest Lab', ['trading.backtest.*']],
        'position_centre' => ['Position Centre', ['trading.positions.*', 'admin.trading_positions.*', 'api.mobile.v1.positions.*', 'api.mobile.v1.admin.positions.*']],
        'recording_classes' => ['Recording Classes', ['trading.recordings.*', 'admin.trading.recordings.*', 'api.mobile.v1.recordings.*']],
        'trading_blog' => ['Trading Blog', ['trading.blogs.*', 'admin.trading.blogs.*', 'api.mobile.v1.blogs.*', 'trading.hub.insights']],
        'trading_pair' => ['Trading Pair', ['all.trading.pairs', 'add.trading.pair', '*.trading.pair', 'download.trading.pairs.template', 'import.trading.pairs']],
        'traders_performance' => ['Traders Performance', ['admin.trader.journals.index']],
    ];

    private const TRADER_CENTRE_CHILDREN = [
        'trading_journal', 'readiness_checklist', 'trading_statistics', 'ai_market_insight',
        'prop_firm', 'backtest_lab', 'position_centre', 'recording_classes',
        'trading_blog', 'trading_pair', 'traders_performance',
    ];

    // Snapshot of installed modules that were enabled before submodule switches existed.
    // A newly registered key defaults OFF until explicitly reviewed here or enabled by an administrator.
    private const EXISTING_ENABLED = [
        'marketing_resources', 'support_tickets', 'ai_assistant', 'appointments',
        'trading_exams', 'notifications', 'community', 'trading_news',
        'signal_provider_desk', 'trading_certificates', 'my_certificates',
        'knowledge_centre', 'market_analyst', 'trading_signals', 'provider_accounts',
        'leaderboard', 'trading_journal', 'readiness_checklist', 'trading_statistics',
        'ai_market_insight', 'prop_firm', 'backtest_lab', 'position_centre',
        'recording_classes', 'trading_blog', 'trading_pair', 'traders_performance',
    ];

    public static function definitions(): array
    {
        $definitions = ['trader_centre' => ['label' => 'Trader Centre', 'description' => 'Controls the Trader Centre and its child tools.', 'default' => true, 'category' => 'tradinghub', 'is_core' => false]];
        foreach (self::MODULES as $key => [$label]) {
            $definitions[$key] = [
                'label' => $label,
                'description' => 'Controls '.$label.' pages and endpoints.',
                'default' => in_array($key, self::EXISTING_ENABLED, true),
                'category' => 'tradinghub', 'is_core' => false,
                'parent' => in_array($key, self::TRADER_CENTRE_CHILDREN, true) ? 'trader_centre'
                    : ($key === 'signal_provider_desk' ? 'trading_signals'
                    : ($key === 'my_certificates' ? 'trading_certificates' : null)),
            ];
        }

        return $definitions;
    }

    /** Presentation metadata for the management page; keys remain the access-control registry keys. */
    public static function managementDomains(): array
    {
        return [
            'workspace' => [
                'label' => 'Trading Workspace', 'description' => 'Core trading tools and workflow for traders.',
                'icon' => 'ri-bar-chart-box-line',
                'modules' => ['trader_centre', 'trading_exams', 'signal_provider_desk', 'market_analyst',
                    'trading_signals', 'provider_accounts', 'leaderboard', 'trading_journal',
                    'readiness_checklist', 'trading_statistics', 'ai_market_insight', 'prop_firm',
                    'backtest_lab', 'position_centre', 'trading_pair', 'traders_performance'],
            ],
            'content' => [
                'label' => 'Content & Community', 'description' => 'Educational content and community engagement.',
                'icon' => 'ri-team-line',
                'modules' => ['marketing_resources', 'trading_news', 'community', 'trading_certificates',
                    'my_certificates', 'knowledge_centre', 'recording_classes', 'trading_blog'],
            ],
            'service' => [
                'label' => 'Service & Automation', 'description' => 'User support and automated assistance.',
                'icon' => 'ri-customer-service-2-line',
                'modules' => ['support_tickets', 'ai_assistant', 'appointments'],
            ],
            'alerts' => [
                'label' => 'Alerts & Communication', 'description' => 'System notifications and user updates.',
                'icon' => 'ri-notification-3-line',
                'modules' => ['notifications'],
            ],
        ];
    }

    public static function featureName(string $key): string
    {
        return 'tradinghub_'.$key;
    }

    public static function keyForRoute(?string $name): ?string
    {
        if (! $name) {
        return null;
        }
        foreach (self::MODULES as $key => [, $patterns]) {
            if (Str::is($patterns, $name)) {
            return $key;
            }
        }

        return null;
    }

    public static function states(): array
    {
        // Console workers may outlive a settings change; cache only within an HTTP request.
        $request = ! app()->runningInConsole() && app()->bound('request') ? request() : null;
        if ($request?->attributes->has('_trading_submodule_states')) {
            return $request->attributes->get('_trading_submodule_states');
        }
        $definitions = self::definitions();
        $values = FeatureToggle::whereIn('feature_name', array_map(self::featureName(...), array_keys($definitions)))
            ->pluck('enabled', 'feature_name');
        $states = [];
        foreach ($definitions as $key => $definition) {
            $states[$key] = (bool) ($values[self::featureName($key)] ?? $definition['default']);
        }
        foreach ($definitions as $key => $definition) {
            if (! empty($definition['parent']) && ! $states[$definition['parent']]) {
            $states[$key] = false;
            }
        }
        $request?->attributes->set('_trading_submodule_states', $states);

        return $states;
    }

    public static function forgetCache(): void
    {
        if (app()->bound('request')) {
        request()->attributes->remove('_trading_submodule_states');
        }
    }

    public static function enabled(string $key): bool
    {
        return FeatureToggle::isEnabled(FeatureToggle::MODULE_TRADING) && (self::states()[$key] ?? false);
    }

    public static function filterMenus(array $menus): array
    {
        $states = self::states();
        $labels = ['Upload Recording Classes' => 'recording_classes', 'AI Market Insight Management' => 'ai_market_insight', 'Prop Firm Leaderboard' => 'prop_firm', 'Prop Firm Config' => 'prop_firm', 'Funded Traders' => 'prop_firm', 'Trading Position Reviews' => 'position_centre'];
        foreach (self::definitions() as $key => $definition) {
        $labels[$definition['label']] = $key;
        }
        $filter = function (array $items) use (&$filter, $states, $labels): array {
            $visible = [];
            foreach ($items as $item) {
                $key = $labels[$item['label'] ?? ''] ?? null;
                if ($key && ! $states[$key]) {
                continue;
                }
                if (isset($item['children'])) {
                    $item['children'] = $filter($item['children']);
                    if (! $item['children']) {
                    continue;
                    }
                }
                $visible[] = $item;
            }

            return $visible;
        };

        return $filter($menus);
    }
}
