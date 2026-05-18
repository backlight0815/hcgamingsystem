<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trader_readiness_checklist_items')) {
            Schema::create('trader_readiness_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->string('item_key')->unique();
                $table->string('category');
                $table->string('title');
                $table->text('description');
                $table->text('why_it_matters')->nullable();
                $table->text('suggested_action')->nullable();
                $table->string('resource_route')->nullable();
                $table->string('resource_label')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_core')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trader_readiness_checklist_progress')) {
            Schema::create('trader_readiness_checklist_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('trader_readiness_checklist_items')->cascadeOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->unsignedTinyInteger('self_rating')->nullable();
                $table->boolean('demo_practiced')->default(false);
                $table->text('reflection_note')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'item_id'], 'trader_readiness_user_item_unique');
            });
        }

        $this->seedDefaultItems();
    }

    public function down(): void
    {
        Schema::dropIfExists('trader_readiness_checklist_progress');
        Schema::dropIfExists('trader_readiness_checklist_items');
    }

    private function seedDefaultItems(): void
    {
        if (! Schema::hasTable('trader_readiness_checklist_items')) {
            return;
        }

        $now = now();
        $items = [
            [
                'item_key' => 'verification_profile_broker',
                'category' => 'Account Verification',
                'title' => 'Profile, broker UID, email, and Discord identity are correct',
                'description' => 'Confirm your personal details, broker UID, broker email, Discord username, and submitted documents match before using any live account.',
                'why_it_matters' => 'Incorrect identity or broker details can delay support, deposits, withdrawals, and community access.',
                'suggested_action' => 'Review your trader verification page and correct anything that does not match your real broker account.',
                'resource_route' => 'trader.onboarding.show',
                'resource_label' => 'Open Verification',
                'sort_order' => 10,
                'is_core' => true,
            ],
            [
                'item_key' => 'risk_disclaimer_platform_rules',
                'category' => 'Account Verification',
                'title' => 'Risk disclaimer and HC trading rules are understood',
                'description' => 'Acknowledge that signals, classes, and analysis are education support. You are still responsible for your own position size and risk.',
                'why_it_matters' => 'New traders often treat deposit approval as trading readiness. It is not the same thing.',
                'suggested_action' => 'Write down your maximum risk per trade and maximum daily loss before your first live trade.',
                'resource_route' => 'trading.knowledge.centre.index',
                'resource_label' => 'Knowledge Centre',
                'sort_order' => 20,
                'is_core' => true,
            ],
            [
                'item_key' => 'demo_first_plan',
                'category' => 'Demo Practice',
                'title' => 'Demo account practice plan is completed before live trading',
                'description' => 'Complete a focused demo phase first, especially if you already deposited. The recommended baseline is at least 20 planned demo trades with screenshots and journal notes.',
                'why_it_matters' => 'Demo practice helps you test execution habits without emotional pressure from real money.',
                'suggested_action' => 'Use the journal to record demo entries, exits, reasons, screenshots, and mistakes.',
                'resource_route' => 'all.trading.journals',
                'resource_label' => 'Trading Journal',
                'sort_order' => 30,
                'is_core' => true,
            ],
            [
                'item_key' => 'learning_materials_reviewed',
                'category' => 'Learning Foundation',
                'title' => 'Technical and knowledge materials have been reviewed',
                'description' => 'Review the core knowledge centre resources and beginner recording classes before taking live entries.',
                'why_it_matters' => 'A trader should know the language and process used by the community before following analysis or signals.',
                'suggested_action' => 'Open the knowledge centre and recording classes, then list three rules you will follow.',
                'resource_route' => 'trading.recordings.index',
                'resource_label' => 'Recording Classes',
                'sort_order' => 40,
                'is_core' => true,
            ],
            [
                'item_key' => 'risk_reward_ratio',
                'category' => 'Risk Management',
                'title' => 'Risk reward ratio and break-even win rate are understood',
                'description' => 'Know how 1:1, 1:2, and 1:3 setups change the win rate needed to stay profitable.',
                'why_it_matters' => 'A high win rate can still lose money if losses are too large; a lower win rate can work if winners are larger.',
                'suggested_action' => 'Calculate the break-even win rate for 1:1, 1:2, and 1:3 before completing this item.',
                'resource_route' => 'trading.knowledge.centre.index',
                'resource_label' => 'Study Risk Materials',
                'sort_order' => 50,
                'is_core' => true,
            ],
            [
                'item_key' => 'position_sizing_lot_size',
                'category' => 'Risk Management',
                'title' => 'Position size, lot size, and stop-loss risk are calculated before entry',
                'description' => 'Understand how account balance, stop-loss distance, pip value, and lot size define the actual money at risk.',
                'why_it_matters' => 'Many beginner losses come from oversized trades, not from bad analysis.',
                'suggested_action' => 'Choose a fixed percentage risk rule such as 0.5% to 1% per trade for practice.',
                'resource_route' => 'all.trading.statistics',
                'resource_label' => 'Trading Statistics',
                'sort_order' => 60,
                'is_core' => true,
            ],
            [
                'item_key' => 'stop_loss_take_profit_rules',
                'category' => 'Risk Management',
                'title' => 'Stop loss, take profit, and no-chase rules are written',
                'description' => 'Define where a trade is invalid, where partial or full profit is taken, and when you must skip a late entry.',
                'why_it_matters' => 'Rules reduce emotional decisions after price starts moving.',
                'suggested_action' => 'Write a one-page live-trade rule sheet and keep it beside your platform.',
                'resource_route' => 'all.trading.journals',
                'resource_label' => 'Trading Journal',
                'sort_order' => 70,
                'is_core' => true,
            ],
            [
                'item_key' => 'support_resistance_levels',
                'category' => 'Technical Analysis',
                'title' => 'Support, resistance, supply, and demand areas can be identified',
                'description' => 'Mark obvious higher-timeframe levels, recent swing highs/lows, liquidity zones, and reaction areas before looking for entries.',
                'why_it_matters' => 'Levels help you avoid buying into resistance or selling into support.',
                'suggested_action' => 'Mark three clean levels on a chart and explain why each level matters.',
                'resource_route' => 'trading.market-analyst.index',
                'resource_label' => 'Market Analyst',
                'sort_order' => 80,
                'is_core' => true,
            ],
            [
                'item_key' => 'market_structure_trend_range',
                'category' => 'Technical Analysis',
                'title' => 'Trend, range, breakout, and reversal conditions can be separated',
                'description' => 'Identify whether the market is trending, ranging, breaking out, reversing, or moving in high-volatility conditions.',
                'why_it_matters' => 'Strategies that work in a trend can fail badly inside a range.',
                'suggested_action' => 'Label five recent charts by market type and write what strategy would fit each one.',
                'resource_route' => 'trading.market-analyst.index',
                'resource_label' => 'Review Market Outlook',
                'sort_order' => 90,
                'is_core' => true,
            ],
            [
                'item_key' => 'candles_timeframes_confirmation',
                'category' => 'Technical Analysis',
                'title' => 'Candlestick confirmation and timeframe alignment are understood',
                'description' => 'Use higher timeframe direction, execution timeframe confirmation, and candle close logic before entering.',
                'why_it_matters' => 'Entering before confirmation is one of the fastest ways for new traders to create unnecessary losses.',
                'suggested_action' => 'Document your preferred analysis timeframe and execution timeframe.',
                'resource_route' => 'trading.recordings.index',
                'resource_label' => 'Watch Class Recordings',
                'sort_order' => 100,
                'is_core' => true,
            ],
            [
                'item_key' => 'economic_news_basics',
                'category' => 'Market Awareness',
                'title' => 'Basic economic news impact is understood',
                'description' => 'Know common high-impact events such as CPI, NFP, FOMC, interest-rate decisions, unemployment data, and central-bank speeches.',
                'why_it_matters' => 'News can widen spreads, increase slippage, and invalidate normal technical setups.',
                'suggested_action' => 'Check the economic calendar before trading and mark no-trade windows around major events.',
                'resource_route' => 'trading.news.index',
                'resource_label' => 'Trading News',
                'sort_order' => 110,
                'is_core' => true,
            ],
            [
                'item_key' => 'session_liquidity_spread',
                'category' => 'Market Awareness',
                'title' => 'Trading sessions, liquidity, spread, and slippage are understood',
                'description' => 'Know the difference between Asian, London, and New York sessions, and how liquidity affects execution.',
                'why_it_matters' => 'A good setup can become poor if spread or liquidity conditions are bad.',
                'suggested_action' => 'Record the session and spread condition for your next ten demo trades.',
                'resource_route' => 'all.trading.journals',
                'resource_label' => 'Journal Practice',
                'sort_order' => 120,
                'is_core' => true,
            ],
            [
                'item_key' => 'order_types_execution',
                'category' => 'Execution',
                'title' => 'Market, limit, stop, stop-loss, take-profit, and trailing orders are understood',
                'description' => 'Know when each order type is used and how it affects entry price, risk, and execution.',
                'why_it_matters' => 'Order mistakes can create immediate losses even when the analysis is correct.',
                'suggested_action' => 'Place each order type on a demo account and cancel it safely after confirming how it works.',
                'resource_route' => 'trading.recordings.index',
                'resource_label' => 'Execution Classes',
                'sort_order' => 130,
                'is_core' => true,
            ],
            [
                'item_key' => 'journal_review_process',
                'category' => 'Execution',
                'title' => 'Every trade will be journaled and reviewed',
                'description' => 'Before live trading, commit to recording entry reason, risk, screenshot, result, mistake, and lesson.',
                'why_it_matters' => 'A trader without a journal cannot reliably improve because mistakes stay invisible.',
                'suggested_action' => 'Create at least five demo journal entries before marking this complete.',
                'resource_route' => 'all.trading.journals',
                'resource_label' => 'Open Journal',
                'sort_order' => 140,
                'is_core' => true,
            ],
            [
                'item_key' => 'signal_following_discipline',
                'category' => 'Execution',
                'title' => 'Signal-following discipline is clear',
                'description' => 'If you use signals, understand the entry zone, invalidation, stop loss, take-profit logic, and when not to chase price.',
                'why_it_matters' => 'Signals are not permission to enter late, increase lot size, or ignore risk.',
                'suggested_action' => 'Pick three past signals and explain where the trade was valid and where it became invalid.',
                'resource_route' => 'member.signals.dashboard',
                'resource_label' => 'Signal Dashboard',
                'sort_order' => 150,
                'is_core' => true,
            ],
            [
                'item_key' => 'emotional_control_rules',
                'category' => 'Mindset',
                'title' => 'Emotional control and stop-trading rules are prepared',
                'description' => 'Define what you will do after two losses, a daily drawdown hit, a missed trade, or a strong urge to revenge trade.',
                'why_it_matters' => 'Most live-account damage happens after a trader loses emotional discipline.',
                'suggested_action' => 'Write your pause rule, daily stop rule, and maximum trades per day.',
                'resource_route' => 'trading.blogs.index',
                'resource_label' => 'Trading Blog',
                'sort_order' => 160,
                'is_core' => true,
            ],
            [
                'item_key' => 'live_transition_plan',
                'category' => 'Live Account Readiness',
                'title' => 'Demo-to-live transition plan is conservative',
                'description' => 'Move to live only after demo discipline is stable. Start with smaller risk than planned and increase only after consistent execution.',
                'why_it_matters' => 'Live pressure changes behaviour. A smaller first phase protects capital while you adapt.',
                'suggested_action' => 'Define your first-live-account risk cap, lot size cap, and review period.',
                'resource_route' => 'all.trading.statistics',
                'resource_label' => 'Review Statistics',
                'sort_order' => 170,
                'is_core' => true,
            ],
            [
                'item_key' => 'deposit_not_readiness',
                'category' => 'Live Account Readiness',
                'title' => 'Deposit is not treated as permission to trade live immediately',
                'description' => 'Even after deposit, the recommended path is demo practice first, complete this checklist, then start live with reduced risk.',
                'why_it_matters' => 'Capital should be protected by preparation, not exposed because the account is available.',
                'suggested_action' => 'Only mark this complete if you accept that live trading can wait until your process is ready.',
                'resource_route' => 'trader.readiness.index',
                'resource_label' => 'Readiness Checklist',
                'sort_order' => 180,
                'is_core' => true,
            ],
        ];

        foreach ($items as $item) {
            DB::table('trader_readiness_checklist_items')->updateOrInsert(
                ['item_key' => $item['item_key']],
                array_merge($item, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
};
