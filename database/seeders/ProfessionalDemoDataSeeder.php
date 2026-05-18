<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProfessionalDemoDataSeeder extends Seeder
{
    private array $columns = [];
    private array $usersByRole = [];
    private array $communities = [];
    private array $productIds = [];
    private array $dealerStockIds = [];
    private array $tradingPairs = ['XAUUSD', 'EURUSD', 'GBPUSD', 'USDJPY', 'US30', 'NAS100', 'BTCUSD'];
    private Carbon $startDate;
    private Carbon $endDate;
    private string $passwordHash;

    public function run(): void
    {
        DB::disableQueryLog();

        $this->startDate = now()->subMonths(6)->startOfDay();
        $this->endDate = now();
        $this->passwordHash = Hash::make('Password123!');

        $this->ensureAssets();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            DB::transaction(function (): void {
                $this->cleanupDemoData();
                $this->seedRoles();
                $this->seedFeatureToggles();
                $this->seedCommunities();
                $this->seedStaticContent();
                $this->seedUsers();
                $this->seedReferralNetwork();
                $this->seedWalletsAndAddresses();
                $this->seedEcommerce();
                $this->seedTradingCore();
                $this->seedSignalsAndPerformance();
                $this->seedEducationContent();
                $this->seedApplicationsAndCertificates();
                $this->seedOperationalTables();
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->command?->info('Professional demo data seeded: 143 users, six months of trading/ecommerce activity, PDFs, images, recordings, commissions, and leaderboard data.');
    }

    private function seedRoles(): void
    {
        $roles = [
            1 => ['admin', 'Administrator'],
            2 => ['subadmin', 'Sub administrator'],
            201 => ['Signal Provider', 'Trading signal provider'],
            202 => ['Senior Signal Provider', 'Senior trading signal provider'],
            350 => ['dealer', 'Dealership e-commerce agent'],
            501 => ['Market Analyst', 'Trading market analyst'],
            502 => ['Signal Provider Management', 'Signal provider management'],
            700 => ['customer', 'Customer'],
            750 => ['Traders', 'Trading member'],
            760 => ['Leadership', 'Trading Leadership'],
            770 => ['Recruiter', 'Trading Recruiter'],
        ];

        foreach ($roles as $id => [$name, $description]) {
            $this->updateOrInsert('roles', ['id' => $id], [
                'name' => $name,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function cleanupDemoData(): void
    {
        $demoUserIds = Schema::hasTable('users')
            ? DB::table('users')->where('email', 'like', '%@demo.hc')->pluck('id')->all()
            : [];

        $demoCommunityIds = Schema::hasTable('communities')
            ? DB::table('communities')->where('name', 'like', 'HC Demo %')->pluck('id')->all()
            : [];

        $demoSignalIds = Schema::hasTable('trading_signals')
            ? DB::table('trading_signals')->where('signal_code', 'like', 'DEMO-%')->pluck('id')->all()
            : [];

        $demoBackupSignalIds = Schema::hasTable('trading_signals_backup')
            ? DB::table('trading_signals_backup')->where('signal_code', 'like', 'DEMO-%')->pluck('id')->all()
            : [];

        $demoKnowledgeIds = Schema::hasTable('knowledge_centres')
            ? DB::table('knowledge_centres')->where('title', 'like', '[Demo]%')->pluck('id')->all()
            : [];

        $demoRecordingIds = Schema::hasTable('trading_recordings')
            ? DB::table('trading_recordings')->where('title', 'like', '[Demo]%')->pluck('id')->all()
            : [];

        $demoAnalysisIds = Schema::hasTable('market_analyses')
            ? DB::table('market_analyses')->where('Outlook_Code', 'like', 'DMO-%')->pluck('id')->all()
            : [];

        $demoNewsIds = Schema::hasTable('news')
            ? DB::table('news')->where('content', 'like', '[DEMO-SEED]%')->pluck('id')->all()
            : [];

        $demoOrderIds = Schema::hasTable('orders')
            ? DB::table('orders')->where(function ($query) use ($demoUserIds) {
                $query->where('payment_proof', 'like', 'upload/demo/%');
                if (! empty($demoUserIds)) {
                    $query->orWhereIn('user_id', $demoUserIds);
                }
            })->pluck('id')->all()
            : [];

        $demoDealerOrderIds = Schema::hasTable('dealers_order')
            ? DB::table('dealers_order')->where(function ($query) use ($demoUserIds) {
                $query->where('payment_proof', 'like', 'upload/demo/%');
                if (! empty($demoUserIds)) {
                    $query->orWhereIn('user_id', $demoUserIds);
                }
            })->pluck('id')->all()
            : [];

        $demoProductIds = Schema::hasTable('product')
            ? DB::table('product')->where('product_name', 'like', '[Demo]%')->pluck('id')->all()
            : [];

        $demoDealerStockIds = Schema::hasTable('dealer_stock')
            ? DB::table('dealer_stock')->where('product_name', 'like', '[Demo]%')->pluck('id')->all()
            : [];

        $this->deleteWhereIn('signal_performances', 'signal_id', $demoSignalIds);
        $this->deleteWhereIn('signal_performances_backup', 'signal_id', $demoBackupSignalIds);
        $this->deleteWhereIn('trading_signal_discord', 'trading_signal_id', $demoSignalIds);
        $this->deleteWhereIn('trading_signals', 'id', $demoSignalIds);
        $this->deleteWhereIn('trading_signals_backup', 'id', $demoBackupSignalIds);

        $this->deleteWhereIn('knowledge_centre_discord', 'knowledge_centre_id', $demoKnowledgeIds);
        $this->deleteWhereIn('knowledge_images', 'knowledge_centre_id', $demoKnowledgeIds);
        $this->deleteWhereIn('knowledge_centres', 'id', $demoKnowledgeIds);

        $this->deleteWhereIn('trading_recording_materials', 'trading_recording_id', $demoRecordingIds);
        $this->deleteWhereIn('trading_recordings', 'id', $demoRecordingIds);

        $this->deleteWhereIn('market_outlook_discord', 'outlook_id', $demoAnalysisIds);
        $this->deleteWhereIn('market_analyses', 'id', $demoAnalysisIds);
        $this->deleteWhereIn('news_discord', 'news_id', $demoNewsIds);
        $this->deleteWhereIn('news', 'id', $demoNewsIds);

        $this->deleteWhereIn('order_items', 'order_id', $demoOrderIds);
        $this->deleteWhereIn('transactions', 'order_id', $demoOrderIds);
        $this->deleteWhereIn('commissions', 'order_id', $demoOrderIds);
        $this->deleteWhereIn('orders', 'id', $demoOrderIds);

        $this->deleteWhereIn('dealer_order_items', 'order_id', $demoDealerOrderIds);
        $this->deleteWhereIn('dealers_order', 'id', $demoDealerOrderIds);
        $this->deleteWhereIn('carts', 'product_id', $demoProductIds);
        $this->deleteWhereIn('dealer_carts', 'dealer_stock_id', $demoDealerStockIds);
        $this->deleteWhereIn('dealer_stock', 'id', $demoDealerStockIds);
        $this->deleteWhereIn('product', 'id', $demoProductIds);

        $this->deleteWhereIn('community_tp_settings', 'community_id', $demoCommunityIds);
        $this->deleteWhereIn('community_documents', 'community_id', $demoCommunityIds);
        $this->deleteWhereIn('communities', 'id', $demoCommunityIds);

        foreach ([
            ['trading_journals', 'user_id'],
            ['trading_journals_backup', 'user_id'],
            ['capitals', 'user_id'],
            ['addresses', 'user_id'],
            ['wallets', 'user_id'],
            ['ewallet_requests', 'user_id'],
            ['ewallet_transactions', 'user_id'],
            ['referral', 'user_id'],
            ['referral_links', 'user_id'],
            ['role_user', 'user_id'],
            ['trader_onboarding_applications', 'user_id'],
            ['trading_position_applications', 'user_id'],
            ['prop_firm_evaluation_questions', 'user_id'],
            ['signal_provider_certificates', 'user_id'],
            ['downline_transactions', 'user_id'],
        ] as [$table, $column]) {
            $this->deleteWhereIn($table, $column, $demoUserIds);
        }

        $this->deleteWhereIn('downline_transactions', 'downline_user_id', $demoUserIds);
        $this->deleteWhereIn('networks', 'user_id', array_map('strval', $demoUserIds));
        $this->deleteWhereIn('networks', 'parent_user_id', array_map('strval', $demoUserIds));
        $this->deleteWhereIn('users', 'id', $demoUserIds);

        $this->deleteLike('abouts', 'title', '[Demo]%');
        $this->deleteLike('acknowledgement', 'title', '[Demo]%');
        $this->deleteLike('blog_categories', 'blog_category', '[Demo]%');
        $this->deleteLike('blogs', 'blog_title', '[Demo]%');
        $this->deleteLike('community_showcase_pages', 'slug', 'demo-%');
        $this->deleteLike('contacts', 'subject', '[DEMO-SEED]%');
        $this->deleteLike('dealer_product_category', 'product_category', '[Demo]%');
        $this->deleteLike('education', 'title', '[Demo]%');
        $this->deleteLike('events', 'title', '[Demo]%');
        $this->deleteLike('footers', 'email', 'support@hcdemo.local');
        $this->deleteLike('home_slides', 'title', '[Demo]%');
        $this->deleteLike('keywords', 'keyword', 'demo %');
        $this->deleteLike('multi_images', 'multi_image', 'upload/demo/%');
        $this->deleteLike('payment_methods', 'name', '[Demo]%');
        $this->deleteLike('portfolios', 'portfolio_name', '[Demo]%');
        $this->deleteLike('product_categories', 'product_category', '[Demo]%');
        $this->deleteLike('services', 'service_title', '[Demo]%');
        $this->deleteLike('skills', 'skill', '[Demo]%');
        $this->deleteLike('tng_qr_codes', 'qr_code', 'upload/demo/%');
        $this->deleteLike('trading_blogs', 'slug', 'demo-%');
        $this->deleteLike('trading_reason', 'name', '[Demo]%');

        $this->usersByRole = [];
        $this->communities = [];
        $this->productIds = [];
        $this->dealerStockIds = [];
        $this->columns = [];
    }

    private function seedFeatureToggles(): void
    {
        $features = [
            'module_trading',
            'module_dealership_ecommerce',
            'DiscordIntegration',
            'DiscordIntegration_Everyone',
            'TradingJournal',
            'TradingSignals',
            'MarketAnalysis',
            'TradingRecordings',
            'TradingLeaderboard',
            'CommissionCentre',
        ];

        foreach ($features as $feature) {
            $this->updateOrInsert('feature_toggles', ['feature_name' => $feature], [
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedCommunities(): void
    {
        $communities = [
            ['HC Demo Public Market', 'public'],
            ['HC Demo Gold Strategy Desk', 'vip'],
            ['HC Demo Prop Firm Lab', 'prop_firm'],
            ['HC Demo Leader Alpha Clients', 'leader_clients'],
            ['HC Demo Momentum Traders', 'trading'],
            ['HC Demo Education Vault', 'education'],
        ];

        foreach ($communities as $index => [$name, $category]) {
            $id = $this->updateOrInsert('communities', ['name' => $name], [
                'status' => true,
                'category' => $category,
                'community_tag' => '#demo-' . Str::slug($category),
                'discord_webhook' => 'https://discord.com/api/webhooks/demo/' . Str::slug($name),
                'discord_webhook_signal' => 'https://discord.com/api/webhooks/demo/signal-' . ($index + 1),
                'discord_webhook_outlook' => 'https://discord.com/api/webhooks/demo/outlook-' . ($index + 1),
                'discord_webhook_knowledge' => 'https://discord.com/api/webhooks/demo/knowledge-' . ($index + 1),
                'discord_webhook_images' => 'https://discord.com/api/webhooks/demo/images-' . ($index + 1),
                'discord_webhook_news' => 'https://discord.com/api/webhooks/demo/news-' . ($index + 1),
                'discord_webhook_weeklys_signal' => 'https://discord.com/api/webhooks/demo/weekly-' . ($index + 1),
                'discord_everyone_enabled' => $index < 3,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->communities[] = $id;

            for ($tp = 1; $tp <= 10; $tp++) {
                $this->updateOrInsert('community_tp_settings', [
                    'community_id' => $id,
                    'tp_level' => $tp,
                ], [
                    'enabled' => $tp <= 6 || $index % 2 === 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedStaticContent(): void
    {
        $this->updateOrInsert('abouts', ['title' => '[Demo] HC Traders Club'], [
            'short_title' => 'Professional trading and dealership ecosystem',
            'short_description' => 'A demo-ready platform combining trading operations, education, ecommerce, and recruitment workflows.',
            'long_description' => 'This demo profile is designed to showcase six months of realistic business activity across traders, leaders, recruiters, customers, and dealership agents.',
            'about_image' => 'upload/demo/general/about-hc-traders.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->updateOrInsert('acknowledgement', ['title' => '[Demo] Risk Acknowledgement'], [
            'long_description' => 'Trading carries risk. Demo records are for system testing, reporting, and workflow review only.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->updateOrInsert('footers', ['email' => 'support@hcdemo.local'], [
            'number' => '+60 12-000 8800',
            'short_description' => 'Professional test footer for HC demo environment.',
            'address' => 'Demo Operations Centre, Kuala Lumpur',
            'facebook' => 'https://facebook.com/hcdemo',
            'twitter' => 'https://x.com/hcdemo',
            'copyright' => '2026 HC Demo Data',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $homeSlides = [
            ['HC Traders Club Demo', 'Trading, education, and ecommerce workflows', 'upload/demo/general/slide-traders.png'],
            ['Leader Workspace', 'Invite, teach, and manage client communities', 'upload/demo/general/slide-leaders.png'],
            ['Dealership E-Commerce', 'Products, stock, orders, and commissions', 'upload/demo/general/slide-ecommerce.png'],
        ];

        foreach ($homeSlides as [$title, $shortTitle, $image]) {
            $this->updateOrInsert('home_slides', ['title' => '[Demo] ' . $title], [
                'short_title' => $shortTitle,
                'home_slide' => $image,
                'video_url' => 'https://demo.hc.local/intro',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['Trading Systems', 'Risk Desk', 'Signal Operations', 'Dealership Logistics'] as $index => $service) {
            $this->updateOrInsert('services', ['service_title' => '[Demo] ' . $service], [
                'short_description' => 'Professional demo service for ' . strtolower($service) . ' workflows.',
                'service_image' => 'upload/demo/general/service-' . ($index + 1) . '.png',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['Performance Review', 'Trade Journaling', 'Community Leadership', 'Recruitment Pipeline'] as $index => $portfolio) {
            $this->updateOrInsert('portfolios', ['portfolio_name' => '[Demo] ' . $portfolio], [
                'portfolio_title' => 'Six-month demo case study',
                'portfolio_image' => 'upload/demo/general/portfolio-' . ($index + 1) . '.png',
                'portfolio_description' => 'A professional sample portfolio item used for visual and workflow testing.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['Risk Management', 'Trade Review', 'Position Sizing', 'Client Support'] as $index => $skill) {
            $this->updateOrInsert('skills', ['skill' => '[Demo] ' . $skill], [
                'level' => 72 + ($index * 6),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['Risk checklist', 'Broker UID', 'Discord username', 'Deposit proof', 'Leaderboard'] as $keyword) {
            $this->updateOrInsert('keywords', ['keyword' => 'demo ' . Str::slug($keyword)], [
                'response' => 'Demo response for ' . $keyword . ' workflow verification.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['Bank Transfer', 'Touch n Go', 'FPX', 'Credit Card'] as $method) {
            $this->updateOrInsert('payment_methods', ['name' => '[Demo] ' . $method], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->updateOrInsert('tng_qr_codes', ['qr_code' => 'upload/demo/general/demo-tng-qr.png'], [
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedUsers(): void
    {
        $plans = [
            ['admin', 1, 2, 'Admin'],
            ['subadmin', 2, 3, 'Sub Admin'],
            ['dealer', 350, 20, 'Dealer'],
            ['leader', 760, 8, 'Leader'],
            ['recruiter', 770, 12, 'Recruiter'],
            ['trader', 750, 70, 'Trader'],
            ['customer', 700, 22, 'Customer'],
            ['signal', 201, 2, 'Signal Provider', 1],
            ['signal', 202, 2, 'Senior Signal Provider', 3],
            ['analyst', 501, 2, 'Market Analyst'],
        ];

        foreach ($plans as $plan) {
            [$key, $roleId, $count, $label] = array_slice($plan, 0, 4);
            $offset = $plan[4] ?? 1;

            for ($i = 1; $i <= $count; $i++) {
                $sequence = $offset + $i - 1;
                $code = strtoupper($key) . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
                $username = 'demo_' . $key . '_' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
                $email = $username . '@demo.hc';
                $createdAt = $this->dateByIndex($i + $roleId, max(12, $count));

                $id = $this->updateOrInsert('users', ['email' => $email], [
                    'name' => $label . ' ' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                    'username' => $username,
                    'email_verified_at' => $createdAt,
                    'password' => $this->passwordHash,
                    'status' => '1',
                    'profile_image' => 'upload/demo/avatars/avatar-' . (($i % 12) + 1) . '.png',
                    'referral_code' => 'HC' . $code,
                    'customer_referral_code' => 'CUST' . $code,
                    'signal_provider_referral_code' => 'SIG' . $code,
                    'role_id' => $roleId,
                    'funded_status' => in_array($roleId, [750, 760], true) && $i % 5 === 0 ? 1 : 0,
                    'prop_firm_phase' => in_array($roleId, [750, 760], true) ? (($i % 3) + 1) : null,
                    'prop_firm_review_status' => in_array($roleId, [750, 760], true) ? ['none', 'requested', 'approved'][$i % 3] : 'none',
                    'prop_firm_review_phase' => in_array($roleId, [750, 760], true) ? (($i % 3) + 1) : null,
                    'prop_firm_trade_locked' => false,
                    'discord_connected_at' => $createdAt->copy()->addDays(2),
                    'total_score' => in_array($roleId, [750, 760, 770], true) ? 45 + (($i * 7) % 52) : 0,
                    'certificate_level' => in_array($roleId, [750, 760, 201, 202], true) ? ['junior', 'senior', 'expert'][$i % 3] : null,
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ]);

                $this->usersByRole[$roleId][] = $id;

                DB::table('role_user')
                    ->where('user_id', $id)
                    ->where('role_id', '!=', $roleId)
                    ->delete();

                $this->updateOrInsert('role_user', ['user_id' => $id, 'role_id' => $roleId], [
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ]);
            }
        }

        $this->assignUplines();
    }

    private function assignUplines(): void
    {
        $admins = $this->usersByRole[1] ?? [];
        $subadmins = $this->usersByRole[2] ?? [];
        $dealers = $this->usersByRole[350] ?? [];
        $leaders = $this->usersByRole[760] ?? [];
        $recruiters = $this->usersByRole[770] ?? [];
        $traders = $this->usersByRole[750] ?? [];
        $customers = $this->usersByRole[700] ?? [];

        foreach ($subadmins as $index => $id) {
            $this->setInviter($id, $admins[$index % max(1, count($admins))] ?? null);
        }

        foreach ($dealers as $index => $id) {
            $this->setInviter($id, $subadmins[$index % max(1, count($subadmins))] ?? $admins[0] ?? null);
        }

        foreach ($leaders as $index => $id) {
            $this->setInviter($id, $index % 2 === 0 ? ($admins[0] ?? null) : ($subadmins[$index % max(1, count($subadmins))] ?? null));
        }

        foreach ($recruiters as $index => $id) {
            $uplinePool = $index % 3 === 0 ? $subadmins : $leaders;
            $this->setInviter($id, $uplinePool[$index % max(1, count($uplinePool))] ?? $leaders[0] ?? null);
        }

        foreach ($traders as $index => $id) {
            $uplinePool = $index % 4 === 0 ? $leaders : $recruiters;
            $this->setInviter($id, $uplinePool[$index % max(1, count($uplinePool))] ?? $leaders[0] ?? null);
        }

        foreach ($customers as $index => $id) {
            $this->setInviter($id, $dealers[$index % max(1, count($dealers))] ?? null);
        }
    }

    private function seedReferralNetwork(): void
    {
        $demoUsers = $this->demoUsers();

        foreach ($demoUsers as $user) {
            $this->updateOrInsert('referral', ['user_id' => $user->id], [
                'upline_user_id' => $user->invited_by,
                'referral_code' => $user->referral_code,
                'created_at' => $user->created_at,
                'updated_at' => now(),
            ]);

            $this->updateOrInsert('referral_links', ['referral_code' => $user->referral_code], [
                'role_id' => $user->role_id ?: 700,
                'user_id' => $user->id,
                'created_at' => $user->created_at,
                'updated_at' => now(),
            ]);

            if ($user->invited_by) {
                $this->updateOrInsert('networks', ['referral_code' => $user->referral_code], [
                    'user_id' => (string) $user->id,
                    'parent_user_id' => (string) $user->invited_by,
                    'created_at' => $user->created_at,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function seedWalletsAndAddresses(): void
    {
        foreach ($this->demoUsers() as $index => $user) {
            $walletId = $this->updateOrInsert('wallets', ['user_id' => $user->id], [
                'amount' => 350 + (($index * 37) % 2400),
                'receipt' => 'upload/demo/payments/wallet-receipt-' . (($index % 8) + 1) . '.png',
                'status' => '1',
                'created_at' => $user->created_at,
                'updated_at' => now(),
            ]);

            $this->updateOrInsert('addresses', ['user_id' => $user->id], [
                'name' => $user->name,
                'street' => ($index + 11) . ' Demo Commerce Avenue',
                'street_2' => 'Unit ' . (($index % 20) + 1) . '-A',
                'zipcode' => '50' . str_pad((string) (($index * 13) % 999), 3, '0', STR_PAD_LEFT),
                'city' => ['Kuala Lumpur', 'Petaling Jaya', 'Johor Bahru', 'Penang', 'Melaka'][$index % 5],
                'state' => ['Selangor', 'Kuala Lumpur', 'Johor', 'Penang', 'Melaka'][$index % 5],
                'phone_no' => '+6012' . str_pad((string) (7000000 + $index), 7, '0', STR_PAD_LEFT),
                'created_at' => $user->created_at,
                'updated_at' => now(),
            ]);

            if ($walletId && $index % 2 === 0) {
                $this->insertIfMissing('ewallet_transactions', [
                    'user_id' => $user->id,
                    'amount' => 120 + (($index * 17) % 700),
                    'type' => 'demo_credit',
                    'remarks' => '[DEMO-SEED] wallet credit for onboarding activity',
                    'created_at' => $this->dateByIndex($index, 143),
                    'updated_at' => now(),
                ], ['user_id', 'remarks']);

                $this->insertIfMissing('ewallet_requests', [
                    'user_id' => $user->id,
                    'wallet_id' => $walletId,
                    'amount' => 80 + (($index * 11) % 500),
                    'type' => $index % 3 === 0 ? 'withdrawal' : 'topup',
                    'remarks' => '[DEMO-SEED] e-wallet request',
                    'status' => ['0', '1', '-1'][$index % 3],
                    'created_at' => $this->dateByIndex($index + 5, 143),
                    'updated_at' => now(),
                ], ['user_id', 'remarks', 'amount']);
            }
        }
    }

    private function seedEcommerce(): void
    {
        $categories = ['Gaming Console', 'Trading Desk', 'Streaming Gear', 'Learning Kit', 'Accessories', 'Merchandise'];
        $dealers = $this->usersByRole[350] ?? [];
        $customers = array_merge($this->usersByRole[700] ?? [], $this->usersByRole[750] ?? []);

        foreach ($categories as $index => $category) {
            $categoryId = $this->updateOrInsert('product_categories', ['product_category' => '[Demo] ' . $category], [
                'user_id' => $dealers[$index % max(1, count($dealers))] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->updateOrInsert('dealer_product_category', ['product_category' => '[Demo] ' . $category], [
                'name' => '[Demo] ' . $category,
                'user_id' => $dealers[$index % max(1, count($dealers))] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            for ($p = 1; $p <= 4; $p++) {
                $productIndex = ($index * 4) + $p;
                $sku = 'HCD-' . str_pad((string) $productIndex, 4, '0', STR_PAD_LEFT);
                $price = 89 + ($productIndex * 19);

                $productId = $this->updateOrInsert('product', ['sku' => $sku], [
                    'user_id' => $dealers[$productIndex % max(1, count($dealers))] ?? null,
                    'publish_status' => 1,
                    'product_name' => '[Demo] ' . $category . ' Pro Kit ' . $p,
                    'product_category_id' => (string) $categoryId,
                    'product_stock' => 35 + ($productIndex * 3),
                    'long_description' => 'Professional demo product for dealership ecommerce testing: stock, order, wallet, and commission flows.',
                    'product_price' => $price,
                    'customer_price' => $price + 30,
                    'product_image' => 'upload/demo/products/product-' . (($productIndex % 12) + 1) . '.png',
                    'weight' => 0.8 + ($p * 0.25),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->productIds[] = $productId;

                $stockId = $this->updateOrInsert('dealer_stock', ['sku' => 'DST-' . $sku], [
                    'product_id' => $productId,
                    'user_id' => $dealers[$productIndex % max(1, count($dealers))] ?? null,
                    'product_name' => '[Demo] ' . $category . ' Pro Kit ' . $p,
                    'dealer_product_name' => '[Demo Dealer] ' . $category . ' Pro Kit ' . $p,
                    'product_category_id' => (string) $categoryId,
                    'product_stock' => 18 + (($productIndex * 5) % 70),
                    'weight' => 0.8 + ($p * 0.25),
                    'long_description' => 'Dealer stock test item with active publish status.',
                    'product_price' => $price,
                    'customer_price' => $price + 38,
                    'product_image' => 'upload/demo/products/product-' . (($productIndex % 12) + 1) . '.png',
                    'dealer_product_image' => 'upload/demo/products/product-' . (($productIndex % 12) + 1) . '.png',
                    'publish_status' => 1,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->dealerStockIds[] = $stockId;
            }
        }

        $this->updateOrInsert('commission_settings', ['id' => 1], [
            'percentage' => 8.00,
            'extra_percentage' => 2.50,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        for ($i = 1; $i <= 150; $i++) {
            $userId = $customers[$i % max(1, count($customers))] ?? null;
            $productId = $this->productIds[$i % max(1, count($this->productIds))] ?? null;
            $quantity = ($i % 3) + 1;
            $total = 120 + (($i * 23) % 1400);
            $date = $this->dateByIndex($i, 150);

            if (! $userId || ! $productId) {
                continue;
            }

            $orderId = $this->insertIfMissing('orders', [
                'user_id' => $userId,
                'total_amount' => $total,
                'payment_proof' => 'upload/demo/payments/payment-proof-' . (($i % 8) + 1) . '.png',
                'status' => ['0', '1', '2', 'completed'][$i % 4],
                'created_at' => $date,
                'updated_at' => $date,
            ], ['user_id', 'total_amount', 'created_at']);

            if (! $orderId) {
                continue;
            }

            $this->insertIfMissing('order_items', [
                'product_id' => $productId,
                'order_id' => $orderId,
                'user_id' => $userId,
                'quantity' => $quantity,
                'created_at' => $date,
                'updated_at' => $date,
            ], ['order_id', 'product_id']);

            $this->insertIfMissing('transactions', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'payment_proof' => 'upload/demo/payments/payment-proof-' . (($i % 8) + 1) . '.png',
                'created_at' => $date,
                'updated_at' => $date,
            ], ['order_id', 'user_id']);

            $uplineId = DB::table('users')->where('id', $userId)->value('invited_by');
            if ($uplineId) {
                $commission = round($total * 0.08, 2);
                $this->insertIfMissing('commissions', [
                    'upline_user_id' => $uplineId,
                    'downline_user_id' => $userId,
                    'order_id' => $orderId,
                    'commission_amount' => $commission,
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['order_id', 'upline_user_id']);

                $this->insertIfMissing('downline_transactions', [
                    'user_id' => $uplineId,
                    'downline_user_id' => $userId,
                    'amount' => $commission,
                    'type' => 'commission',
                    'remarks' => '[DEMO-SEED] Sales commission from order #' . $orderId,
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['user_id', 'downline_user_id', 'remarks']);
            }

            if ($i <= 45) {
                $this->insertIfMissing('carts', [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'dealer_stock_id' => $this->dealerStockIds[$i % max(1, count($this->dealerStockIds))] ?? null,
                    'quantity' => $quantity,
                    'guest_id' => 'demo-guest-' . $i,
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['user_id', 'product_id', 'guest_id']);
            }
        }

        for ($i = 1; $i <= 80; $i++) {
            $dealerId = $dealers[$i % max(1, count($dealers))] ?? null;
            $stockId = $this->dealerStockIds[$i % max(1, count($this->dealerStockIds))] ?? null;
            $productId = $this->productIds[$i % max(1, count($this->productIds))] ?? null;
            $date = $this->dateByIndex($i + 10, 80);

            if (! $dealerId || ! $stockId || ! $productId) {
                continue;
            }

            $dealerOrderId = $this->insertIfMissing('dealers_order', [
                'user_id' => $dealerId,
                'total_amount' => 450 + (($i * 41) % 2600),
                'payment_proof' => 'upload/demo/payments/payment-proof-' . (($i % 8) + 1) . '.png',
                'status' => ['pending', 'approved', 'delivery', 'completed'][$i % 4],
                'created_at' => $date,
                'updated_at' => $date,
            ], ['user_id', 'total_amount', 'created_at']);

            if ($dealerOrderId) {
                $this->insertIfMissing('dealer_order_items', [
                    'dealer_product_id' => $stockId,
                    'order_id' => $dealerOrderId,
                    'user_id' => $dealerId,
                    'quantity' => ($i % 5) + 1,
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['dealer_product_id', 'order_id']);
            }

            if ($i <= 35) {
                $this->insertIfMissing('dealer_carts', [
                    'user_id' => $dealerId,
                    'guest_id' => 'demo-dealer-guest-' . $i,
                    'dealer_stock_id' => $stockId,
                    'product_id' => $productId,
                    'quantity' => ($i % 4) + 1,
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['user_id', 'dealer_stock_id', 'guest_id']);
            }
        }
    }

    private function seedTradingCore(): void
    {
        foreach ($this->tradingPairs as $pair) {
            $this->updateOrInsert('trading_pairs', ['symbol' => $pair], [
                'pip_factor' => str_contains($pair, 'JPY') ? 0.010000 : (str_contains($pair, 'XAU') ? 0.100000 : 0.000100),
                'pip_decimal' => str_contains($pair, 'JPY') || str_contains($pair, 'XAU') ? 2 : 4,
                'description' => '[Demo] ' . $pair . ' professional test pair',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $tradingUsers = array_merge($this->usersByRole[750] ?? [], $this->usersByRole[760] ?? [], $this->usersByRole[770] ?? []);

        foreach ($tradingUsers as $userIndex => $userId) {
            $depositDate = $this->dateByIndex($userIndex, max(1, count($tradingUsers)))->copy()->subDays(7);
            $depositAmount = 1000 + (($userIndex * 325) % 14000);

            $this->insertIfMissing('capitals', [
                'type' => 1,
                'user_id' => $userId,
                'deposit_date' => $depositDate->toDateString(),
                'amount' => $depositAmount,
                'notes' => '[DEMO-SEED] Initial trading deposit',
                'created_at' => $depositDate,
                'updated_at' => $depositDate,
            ], ['user_id', 'notes']);

            if ($userIndex % 4 === 0) {
                $this->insertIfMissing('capitals', [
                    'type' => 2,
                    'user_id' => $userId,
                    'deposit_date' => $depositDate->copy()->addMonths(3)->toDateString(),
                    'amount' => round($depositAmount * 0.12, 2),
                    'notes' => '[DEMO-SEED] Partial withdrawal',
                    'created_at' => $depositDate->copy()->addMonths(3),
                    'updated_at' => $depositDate->copy()->addMonths(3),
                ], ['user_id', 'notes']);
            }

            $tradeCount = 10 + ($userIndex % 13);
            for ($t = 1; $t <= $tradeCount; $t++) {
                $pair = $this->tradingPairs[($userIndex + $t) % count($this->tradingPairs)];
                $direction = ($t + $userIndex) % 2 === 0 ? 1 : 2;
                $isWin = (($t + $userIndex) % 5) !== 0;
                $pips = $isWin ? 12 + (($t * 7) % 86) : -1 * (8 + (($t * 5) % 46));
                $lotSize = [0.03, 0.05, 0.08, 0.10, 0.15][($t + $userIndex) % 5];
                $profitLoss = round($pips * $lotSize * 10, 2);
                $entry = $this->basePrice($pair) + (($t % 9) * $this->pipFactor($pair) * 10);
                $exit = $entry + ($direction === 1 ? 1 : -1) * ($pips * $this->pipFactor($pair));
                $openDate = $this->dateByIndex(($userIndex * 20) + $t, count($tradingUsers) * 22);
                $closeDate = $openDate->copy()->addHours(2 + ($t % 8));

                $row = [
                    'user_id' => $userId,
                    'type' => 'trade',
                    'open_date' => $openDate,
                    'close_date' => $closeDate,
                    'capital' => $depositAmount,
                    'trade_date' => $openDate,
                    'pair' => $pair,
                    'direction' => $direction,
                    'entry_price' => round($entry, 2),
                    'exit_price' => round($exit, 2),
                    'lot_size' => $lotSize,
                    'pips' => $pips,
                    'profit_loss' => $profitLoss,
                    'result' => $profitLoss > 0 ? 1 : 2,
                    'notes' => '[DEMO-SEED] Six-month professional journal sample: ' . ($isWin ? 'planned execution' : 'controlled loss'),
                    'created_at' => $openDate,
                    'updated_at' => $closeDate,
                ];

                $this->insertIfMissing('trading_journals', $row, ['user_id', 'open_date', 'pair']);
                $this->insertIfMissing('trading_journals_backup', $row, ['user_id', 'open_date', 'pair']);
            }
        }
    }

    private function seedSignalsAndPerformance(): void
    {
        $reasons = [
            ['Liquidity sweep', 'Price swept liquidity before returning to value.'],
            ['Order block reaction', 'Institutional zone reaction with clear invalidation.'],
            ['Trend continuation', 'Aligned with higher timeframe market structure.'],
            ['News volatility plan', 'Managed exposure around scheduled economic release.'],
            ['Risk reduction', 'Position protected after partial target hit.'],
        ];

        $reasonIds = [];
        foreach ($reasons as [$name, $description]) {
            $reasonIds[] = $this->updateOrInsert('trading_reason', ['name' => '[Demo] ' . $name], [
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $providers = array_merge($this->usersByRole[201] ?? [], $this->usersByRole[202] ?? [], $this->usersByRole[760] ?? []);

        for ($i = 1; $i <= 90; $i++) {
            $pair = $this->tradingPairs[$i % count($this->tradingPairs)];
            $date = $this->dateByIndex($i, 90);
            $status = [0, 1, 2, 3, 4, 5, 12, 13, 14, 15][$i % 10];
            $entry = $this->basePrice($pair) + (($i % 20) * $this->pipFactor($pair) * 10);
            $targets = [];
            for ($tp = 1; $tp <= 10; $tp++) {
                $targets['target_' . $tp] = (string) round($entry + ($tp * 10 * $this->pipFactor($pair)), 5);
            }

            $signalCode = 'DEMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $communityId = $this->communities[$i % max(1, count($this->communities))] ?? null;
            $userId = $providers[$i % max(1, count($providers))] ?? null;
            $isDone = $status === 14 ? 1 : 0;
            $isBE = $status === 15 ? 1 : 0;
            $isSetBE = $isBE || $i % 7 === 0 ? 1 : 0;

            $signalRow = array_merge([
                'community_id' => $communityId,
                'signal_code' => $signalCode,
                'user_id' => $userId,
                'trading_reasons' => json_encode(array_values(array_filter([$reasonIds[$i % count($reasonIds)] ?? null]))),
                'discord_message_id' => 'demo-msg-' . $i,
                'discord_channel_id' => 'demo-channel-' . (($i % 6) + 1),
                'status' => $status,
                'is_done' => $isDone,
                'IsDone' => $isDone,
                'IsBE' => $isBE,
                'IsSetBE' => $isSetBE,
                'cancel_reason' => $status === 12 ? 'Demo cancellation after volatility invalidated the setup.' : null,
                'trigger_time' => $date->copy()->addHours(3),
                'link' => 'https://www.tradingview.com/x/demo' . $i,
                'pips_result' => in_array($status, [12, 13, 14, 15], true) ? (($i % 2 === 0 ? 1 : -1) * (10 + ($i % 80))) : null,
                'trading_pair' => $pair,
                'signal_title' => '[Demo] ' . $pair . ' Institutional Setup ' . $i,
                'immediate_action' => $i % 2 === 0 ? 'BUY' : 'SELL',
                'entry_price' => (string) round($entry, 5),
                'stop_loss' => (string) round($entry - (18 * $this->pipFactor($pair)), 5),
                'disclaimer' => 'Demo signal for workflow testing only.',
                'risk_level' => ['Low', 'Medium', 'High'][$i % 3],
                'community_target' => (string) $communityId,
                'community_category' => 'demo',
                'category' => 'demo',
                'signal_image' => 'upload/demo/signals/signal-' . (($i % 10) + 1) . '.png',
                'created_at' => $date,
                'updated_at' => $date,
            ], $targets);

            $signalId = $this->insertIfMissing('trading_signals', $signalRow, ['signal_code']);
            $backupRow = $signalRow;
            unset($backupRow['discord_message_id'], $backupRow['discord_channel_id'], $backupRow['pips_result'], $backupRow['trading_reasons'], $backupRow['signal_title']);
            $backupId = $this->insertIfMissing('trading_signals_backup', $backupRow, ['signal_code']);

            if ($signalId && $communityId) {
                $communityName = DB::table('communities')->where('id', $communityId)->value('name') ?: 'Demo Community';
                $this->insertIfMissing('trading_signal_discord', [
                    'trading_signal_id' => $signalId,
                    'community_id' => $communityId,
                    'community' => $communityName,
                    'message_id' => 'demo-signal-message-' . $i,
                    'channel_id' => 'demo-channel-' . (($i % 6) + 1),
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['trading_signal_id', 'community_id']);
            }

            if ($signalId && in_array($status, [2, 3, 4, 5, 13, 14, 15], true)) {
                $profitPips = $status === 13 ? -1 * (15 + ($i % 30)) : (18 + ($i % 95));
                $performance = [
                    'community_id' => $communityId,
                    'signal_id' => $signalId,
                    'tp_hit' => $status >= 2 && $status <= 11 ? $status - 1 : null,
                    'is_sl' => $status === 13,
                    'is_cancelled' => $status === 12,
                    'profit_pips' => $profitPips,
                    'profit_usd' => round($profitPips * 0.1, 2),
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
                $this->insertIfMissing('signal_performances', $performance, ['signal_id', 'community_id']);

                if ($backupId) {
                    $performance['signal_id'] = $backupId;
                    $this->insertIfMissing('signal_performances_backup', $performance, ['signal_id', 'community_id']);
                }
            }
        }
    }

    private function seedEducationContent(): void
    {
        $admins = array_merge($this->usersByRole[1] ?? [], $this->usersByRole[2] ?? []);
        $leaders = $this->usersByRole[760] ?? [];
        $analysts = $this->usersByRole[501] ?? [];
        $authors = array_merge($admins, $leaders, $analysts);

        $blogCategories = ['Market Outlook', 'Risk Management', 'Trading Psychology', 'Dealership Growth'];
        $blogCategoryIds = [];
        foreach ($blogCategories as $category) {
            $blogCategoryIds[] = $this->updateOrInsert('blog_categories', ['blog_category' => '[Demo] ' . $category], [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        for ($i = 1; $i <= 24; $i++) {
            $date = $this->dateByIndex($i, 24);
            $title = '[Demo] Professional Trading Operations Brief ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            $this->updateOrInsert('blogs', ['blog_title' => $title], [
                'blog_category_id' => (string) ($blogCategoryIds[$i % count($blogCategoryIds)] ?? ''),
                'blog_image' => 'upload/demo/blog/blog-cover-' . (($i % 12) + 1) . '.png',
                'blog_tags' => 'demo,trading,professional',
                'blog_description' => 'Professional demo article covering risk process, execution quality, and operational reporting for HC workflows.',
                'page_views' => 120 + ($i * 17),
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $this->updateOrInsert('trading_blogs', ['slug' => 'demo-trading-operations-brief-' . $i], [
                'author_id' => $authors[$i % max(1, count($authors))] ?? null,
                'title' => $title,
                'category' => ['trading_sharing', 'knowledge_sharing', 'psychology_sharing', 'risk_management', 'market_outlook'][$i % 5],
                'excerpt' => 'A polished demo article for testing blog listing, author, category, and professional cover images.',
                'content' => str_repeat('This professional demo article explains market context, risk planning, review discipline, client communication, and execution quality. ', 10),
                'cover_image' => 'upload/demo/blog/blog-cover-' . (($i % 12) + 1) . '.png',
                'tags' => 'demo,trading,operations',
                'status' => 'published',
                'is_featured' => $i <= 4,
                'published_at' => $date,
                'views' => 220 + ($i * 31),
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        for ($i = 1; $i <= 36; $i++) {
            $date = $this->dateByIndex($i, 36);
            $communityId = $this->communities[$i % max(1, count($this->communities))] ?? null;
            $outlookCode = 'DMO-' . $date->format('ym') . '-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $analysisId = $this->updateOrInsert('market_analyses', ['Outlook_Code' => $outlookCode], [
                'community_id' => $communityId,
                'title' => '[Demo] Weekly ' . $this->tradingPairs[$i % count($this->tradingPairs)] . ' Market Outlook',
                'market' => $this->tradingPairs[$i % count($this->tradingPairs)],
                'analysis_date' => $date->toDateString(),
                'market_overview' => 'Six-month professional test market overview with liquidity context and macro notes.',
                'trend_structure' => 'Higher timeframe structure remains balanced with clear invalidation levels.',
                'key_zones' => 'Premium zone, discount zone, liquidity pool, and mitigation block documented.',
                'entry_zones_description' => 'Entry areas are defined only after confirmation candle and risk compression.',
                'analyst_view' => 'Wait for confirmation before committing exposure.',
                'strategy' => 'Intraday continuation with reduced risk around news.',
                'trading_plan' => 'Plan A continuation, Plan B rejection, Plan C no-trade if spread expands.',
                'chart_signals' => 'Momentum divergence, order block reaction, volume compression.',
                'rsi_level' => (string) (42 + ($i % 22)),
                'order_block' => 'H4 demand / M15 execution zone',
                'discord_sent' => $i % 2 === 0,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            if ($analysisId && $communityId) {
                $this->insertIfMissing('market_outlook_discord', [
                    'community_id' => $communityId,
                    'outlook_id' => $analysisId,
                    'message_id' => 'demo-outlook-' . $i,
                    'channel_id' => 'demo-market-' . (($i % 6) + 1),
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['community_id', 'outlook_id']);
            }
        }

        for ($i = 1; $i <= 30; $i++) {
            $date = $this->dateByIndex($i, 30);
            $communityId = $this->communities[$i % max(1, count($this->communities))] ?? null;
            $newsId = $this->insertIfMissing('news', [
                'community_id' => $communityId,
                'content' => '[DEMO-SEED] Economic calendar alert: CPI, NFP, FOMC commentary, or liquidity event requiring reduced exposure.',
                'impact' => ($i % 3) + 1,
                'news_date' => $date->toDateString(),
                'image' => 'upload/demo/news/news-' . (($i % 8) + 1) . '.png',
                'created_at' => $date,
                'updated_at' => $date,
            ], ['community_id', 'news_date', 'impact']);

            if ($newsId && $communityId) {
                $this->insertIfMissing('news_discord', [
                    'community_id' => $communityId,
                    'news_id' => $newsId,
                    'message_id' => 'demo-news-' . $i,
                    'channel_id' => 'demo-news-channel-' . (($i % 6) + 1),
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['community_id', 'news_id']);
            }
        }

        $this->seedDocumentsRecordingsAndKnowledge($authors, $leaders);
        $this->seedCommunityShowcases();
    }

    private function seedDocumentsRecordingsAndKnowledge(array $authors, array $leaders): void
    {
        foreach ($this->communities as $index => $communityId) {
            for ($i = 1; $i <= 3; $i++) {
                $docName = 'community-doc-' . ($index + 1) . '-' . $i . '.pdf';
                $filePath = 'community_documents/' . $docName;
                $fullPath = storage_path('app/' . $filePath);
                $this->createPdf($fullPath, 'HC Demo Community Documentation', [
                    'Community document version ' . $i,
                    'Purpose: operational review, onboarding checklist, and compliance verification.',
                    'Generated for professional demo testing.',
                ]);

                $this->updateOrInsert('community_documents', [
                    'community_id' => $communityId,
                    'title' => '[Demo] Community Operating Manual ' . $i,
                ], [
                    'uploaded_by' => $authors[($index + $i) % max(1, count($authors))] ?? null,
                    'description' => 'PDF documentation for community review, founder/partner access, and download testing.',
                    'file_path' => $filePath,
                    'original_filename' => $docName,
                    'mime_type' => 'application/pdf',
                    'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                    'download_count' => ($index + 1) * $i,
                    'created_at' => $this->dateByIndex(($index * 3) + $i, 18),
                    'updated_at' => now(),
                ]);
            }
        }

        for ($i = 1; $i <= 20; $i++) {
            $date = $this->dateByIndex($i, 20);
            $uploaderId = $i % 4 === 0 ? ($leaders[$i % max(1, count($leaders))] ?? null) : ($authors[$i % max(1, count($authors))] ?? null);
            $recordingId = $this->updateOrInsert('trading_recordings', ['title' => '[Demo] Class Recording ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)], [
                'description' => 'Demo class recording covering structure, liquidity, risk, journal review, and client communication.',
                'video_url' => 'https://demo.hc.local/classes/class-' . $i,
                'download_url' => 'https://demo.hc.local/classes/class-' . $i . '/download',
                'source_name' => ['Zoom', 'Google Drive', 'Vimeo Demo', 'Internal Academy'][$i % 4],
                'status' => true,
                'approval_status' => $i % 5 === 0 ? 'pending' : 'approved',
                'approved_by' => $authors[0] ?? null,
                'approved_at' => $i % 5 === 0 ? null : $date->copy()->addDay(),
                'approval_note' => 'Demo review note for recording class approval workflow.',
                'uploaded_by' => $uploaderId,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            if ($recordingId) {
                $pdfName = 'recording-material-' . $i . '.pdf';
                $filePath = 'trading_recording_materials/' . $pdfName;
                $fullPath = storage_path('app/' . $filePath);
                $this->createPdf($fullPath, 'Class Material ' . $i, [
                    'Topic: market structure and risk planning.',
                    'Contains demo checklist and practical exercises.',
                    'Linked to recording class #' . $i,
                ]);

                $this->updateOrInsert('trading_recording_materials', [
                    'trading_recording_id' => $recordingId,
                    'title' => '[Demo] Workbook ' . $i,
                ], [
                    'uploaded_by' => $uploaderId,
                    'file_path' => $filePath,
                    'original_filename' => $pdfName,
                    'mime_type' => 'application/pdf',
                    'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                    'download_count' => $i * 2,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
        }

        for ($i = 1; $i <= 24; $i++) {
            $date = $this->dateByIndex($i, 24);
            $communityId = $this->communities[$i % max(1, count($this->communities))] ?? null;
            $publicPdf = 'upload/knowledge/demo-knowledge-' . $i . '.pdf';
            $this->createPdf(public_path($publicPdf), 'Knowledge Centre Material ' . $i, [
                'Professional PDF material for knowledge centre testing.',
                'Covers execution, psychology, risk, review, and community support.',
            ]);

            $knowledgeId = $this->updateOrInsert('knowledge_centres', ['title' => '[Demo] Knowledge Playbook ' . $i], [
                'community_id' => $i % 4 === 0 ? null : $communityId,
                'description' => 'PDF learning document for trader and leader knowledge centre workflows.',
                'file_path' => $publicPdf,
                'status' => true,
                'uploaded_by' => $i % 5 === 0 ? ($leaders[$i % max(1, count($leaders))] ?? null) : ($authors[$i % max(1, count($authors))] ?? null),
                'approval_status' => $i % 5 === 0 ? 'pending' : 'approved',
                'approved_by' => $authors[0] ?? null,
                'approved_at' => $i % 5 === 0 ? null : $date->copy()->addDay(),
                'approval_note' => 'Demo approval note for knowledge centre item.',
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            if ($knowledgeId && $communityId) {
                $this->insertIfMissing('knowledge_centre_discord', [
                    'community_id' => $communityId,
                    'knowledge_centre_id' => $knowledgeId,
                    'message_id' => 'demo-knowledge-' . $i,
                    'channel_id' => 'demo-knowledge-channel-' . (($i % 6) + 1),
                    'created_at' => $date,
                    'updated_at' => $date,
                ], ['community_id', 'knowledge_centre_id']);

                $this->updateOrInsert('knowledge_images', [
                    'knowledge_centre_id' => $knowledgeId,
                    'image_path' => 'upload/demo/knowledge/knowledge-' . (($i % 8) + 1) . '.png',
                ], [
                    'community_id' => $communityId,
                    'message_id' => 'demo-knowledge-image-' . $i,
                    'channel_id' => 'demo-image-channel-' . (($i % 6) + 1),
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
        }
    }

    private function seedCommunityShowcases(): void
    {
        $pages = [
            'demo-public-traders' => 'HC Demo Public Traders',
            'demo-leader-alpha' => 'HC Demo Leader Alpha',
            'demo-prop-firm-lab' => 'HC Demo Prop Firm Lab',
        ];

        foreach ($pages as $slug => $title) {
            $this->updateOrInsert('community_showcase_pages', ['slug' => $slug], [
                'hero_kicker' => 'Professional Demo Community',
                'hero_title' => $title,
                'hero_subtitle' => 'Trading education, signals, review process, and client support.',
                'hero_intro' => 'A polished showcase page for community positioning, recruitment, and education review.',
                'poster_image' => 'upload/demo/general/showcase-' . Str::slug($title) . '.png',
                'primary_cta_label' => 'Join Demo Community',
                'primary_cta_url' => '/register',
                'secondary_cta_label' => 'View Guidelines',
                'secondary_cta_url' => '/community-showcase/' . $slug,
                'entry_requirements' => json_encode(['Verified account', 'Approved onboarding', 'Risk acknowledgement']),
                'core_services' => json_encode(['Market outlook', 'Signal education', 'Weekly review']),
                'secondary_services' => json_encode(['Documentation', 'Class recordings', 'Leaderboard tracking']),
                'service_principle' => 'Keep every client workflow clear, documented, and reviewable.',
                'risk_disclaimer' => 'Demo content only. Trading remains risky.',
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedApplicationsAndCertificates(): void
    {
        $admins = $this->usersByRole[1] ?? [];
        $traders = $this->usersByRole[750] ?? [];
        $leaders = $this->usersByRole[760] ?? [];
        $recruiters = $this->usersByRole[770] ?? [];
        $providers = $this->usersByRole[201] ?? [];

        foreach (array_merge($traders, $leaders, $recruiters) as $index => $userId) {
            $date = $this->dateByIndex($index, 90);
            $docPath = 'upload/demo/documents/onboarding-' . (($index % 6) + 1) . '.pdf';
            $this->createPdf(public_path($docPath), 'Trader Onboarding Verification', [
                'Broker UID and deposit proof demo packet.',
                'Discord username and client verification details.',
            ]);

            $this->updateOrInsert('trader_onboarding_applications', ['user_id' => $userId], [
                'status' => ['approved', 'pending', 'rejected_resubmittable', 'rejected_new_application'][$index % 4],
                'is_client' => $index % 5 !== 0,
                'has_deposit' => $index % 4 !== 1,
                'deposit_amount' => 500 + (($index * 125) % 8500),
                'discord_username' => 'demo_trader_' . $index . '#2026',
                'broker_uid' => 'BRK-DEMO-' . str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                'broker_email' => 'broker.demo.' . $index . '@demo.hc',
                'document_path' => $docPath,
                'trader_note' => 'Demo onboarding application with broker, deposit, and Discord verification.',
                'reviewed_by' => $admins[0] ?? null,
                'rejection_reason' => $index % 4 >= 2 ? 'document_mismatch' : null,
                'rejection_note' => $index % 4 >= 2 ? 'Demo rejection note for resubmission workflow.' : null,
                'allow_resubmission' => $index % 4 !== 3,
                'submitted_at' => $date,
                'reviewed_at' => $index % 4 === 1 ? null : $date->copy()->addDays(2),
                'created_at' => $date,
                'updated_at' => now(),
            ]);

            if ($index < 36) {
                $position = $index % 2 === 0 ? 'leadership' : 'recruiter';
                $this->updateOrInsert('trading_position_applications', [
                    'user_id' => $userId,
                    'requested_position' => $position,
                ], [
                    'requested_role_id' => $position === 'leadership' ? 760 : 770,
                    'status' => ['pending', 'approved', 'rejected'][$index % 3],
                    'first_trade_date' => $date->copy()->subMonth()->toDateString(),
                    'trade_count_snapshot' => 18 + ($index % 40),
                    'strategy_summary' => 'Demo strategy evaluation: structured risk, trend context, and repeatable execution.',
                    'trade_history_summary' => 'Six-month history reviewed with drawdown, win rate, and consistency snapshot.',
                    'personality_summary' => 'Shows patience, client communication, and willingness to follow process.',
                    'marketing_plan' => 'Recruitment plan focuses on education-first onboarding and transparent follow-up.',
                    'client_support_plan' => 'Weekly review, Discord check-ins, and documentation support.',
                    'supporting_document_path' => $docPath,
                    'reviewed_by' => $admins[0] ?? null,
                    'review_note' => 'Demo review note for position application.',
                    'submitted_at' => $date,
                    'reviewed_at' => $index % 3 === 0 ? null : $date->copy()->addDays(3),
                    'created_at' => $date,
                    'updated_at' => now(),
                ]);
            }

            if ($index < 24) {
                $this->insertIfMissing('prop_firm_evaluation_questions', [
                    'user_id' => $userId,
                    'asked_by' => $admins[0] ?? null,
                    'phase' => ($index % 3) + 1,
                    'status' => ['open', 'answered', 'resolved'][$index % 3],
                    'title' => '[Demo] Risk Rule Clarification ' . ($index + 1),
                    'question' => 'Explain how you will manage max daily loss and news exposure for this challenge.',
                    'answer' => $index % 3 === 0 ? null : 'I will reduce lot size, stop trading after daily limit, and avoid revenge trades.',
                    'answered_at' => $index % 3 === 0 ? null : $date->copy()->addDay(),
                    'resolved_at' => $index % 3 === 2 ? $date->copy()->addDays(3) : null,
                    'created_at' => $date,
                    'updated_at' => now(),
                ], ['user_id', 'title']);
            }
        }

        foreach (array_merge($providers, array_slice($traders, 0, 16), $leaders) as $index => $userId) {
            $date = $this->dateByIndex($index, 28);
            $certificatePath = 'upload/demo/certificates/certificate-' . ($index + 1) . '.png';
            $this->updateOrInsert('signal_provider_certificates', ['verification_code' => 'HC-DEMO-CERT-' . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)], [
                'user_id' => $userId,
                'recipient_name' => DB::table('users')->where('id', $userId)->value('name'),
                'level' => ['junior', 'senior', 'expert'][$index % 3],
                'certificate_title' => 'HC Traders Club Demo Certificate of Trading Completion',
                'certificate_type' => 'trading_class_completion',
                'status' => ['eligible', 'approved', 'published'][$index % 3],
                'certificate_path' => $certificatePath,
                'discipline_summary' => 'Maintained review discipline across demo trading history.',
                'strategy_summary' => 'Completed strategy evaluation and market structure assessment.',
                'founder_name' => 'Sua Kai Young',
                'founder_title' => 'HC Founder',
                'issued_by' => $admins[0] ?? null,
                'eligible_at' => $date,
                'approved_at' => $date->copy()->addDay(),
                'published_at' => $index % 3 === 2 ? $date->copy()->addDays(2) : null,
                'view_count' => 20 + ($index * 4),
                'download_count' => 3 + $index,
                'created_at' => $date,
                'updated_at' => now(),
            ]);
        }
    }

    private function seedOperationalTables(): void
    {
        for ($i = 1; $i <= 18; $i++) {
            $date = $this->dateByIndex($i, 18);
            $this->insertIfMissing('contacts', [
                'name' => 'Demo Prospect ' . $i,
                'email' => 'prospect.' . $i . '@demo.hc',
                'subject' => '[DEMO-SEED] Enquiry ' . $i,
                'phone' => '+6018' . str_pad((string) (8800000 + $i), 7, '0', STR_PAD_LEFT),
                'message' => 'Demo enquiry for trading class, dealership product, or community onboarding.',
                'created_at' => $date,
                'updated_at' => $date,
            ], ['email', 'subject']);
        }

        for ($i = 1; $i <= 12; $i++) {
            $date = $this->dateByIndex($i, 12);
            $this->updateOrInsert('events', ['title' => '[Demo] Trading Review Session ' . $i], [
                'description' => 'Monthly demo review for signal performance, journals, and client support quality.',
                'type' => ['webinar', 'workshop', 'review'][$i % 3],
                'start_time' => $date->copy()->addHours(20),
                'end_time' => $date->copy()->addHours(22),
                'location' => 'Online',
                'platform' => ['Zoom', 'Google Meet', 'Discord Stage'][$i % 3],
                'organizer_name' => 'HC Demo Operations',
                'status' => $i % 4,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $this->updateOrInsert('education', ['title' => '[Demo] Education Module ' . $i], [
                'long_description' => 'Structured module for professional trading education and client progression.',
                'period' => 'Week ' . $i,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $this->updateOrInsert('multi_images', ['multi_image' => 'upload/demo/general/gallery-' . (($i % 8) + 1) . '.png'], [
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }

    private function ensureAssets(): void
    {
        $this->makeImage('upload/demo/general/about-hc-traders.png', 'HC Traders Club', 'Professional demo operations', [15, 23, 42], [20, 184, 166]);
        $this->makeImage('upload/demo/general/slide-traders.png', 'Trading Workspace', 'Journals, signals, analysis', [17, 24, 39], [37, 99, 235]);
        $this->makeImage('upload/demo/general/slide-leaders.png', 'Leader Workspace', 'Education and client growth', [30, 41, 59], [34, 197, 94]);
        $this->makeImage('upload/demo/general/slide-ecommerce.png', 'Dealership E-Commerce', 'Stock, orders, commissions', [24, 24, 27], [245, 158, 11]);
        $this->makeImage('upload/demo/general/demo-tng-qr.png', 'TNG QR', 'Demo payment QR', [255, 255, 255], [14, 165, 233], 640, 640);

        for ($i = 1; $i <= 12; $i++) {
            $this->makeImage('upload/demo/blog/blog-cover-' . $i . '.png', 'Market Brief ' . $i, 'Risk, execution, review', [15 + $i, 23, 42], [20, 184 - ($i * 4), 166]);
            $this->makeImage('upload/demo/products/product-' . $i . '.png', 'HC Product ' . $i, 'Professional demo catalogue', [248, 250, 252], [37, 99, 235], 900, 900);
            $this->makeImage('upload/demo/avatars/avatar-' . $i . '.png', 'HC', 'Demo User ' . $i, [30, 41, 59], [14, 165, 233], 512, 512);
        }

        for ($i = 1; $i <= 10; $i++) {
            $this->makeImage('upload/demo/signals/signal-' . $i . '.png', 'Signal Chart ' . $i, 'Entry, SL, TP zones', [17, 24, 39], [34, 197, 94]);
        }

        for ($i = 1; $i <= 8; $i++) {
            $this->makeImage('upload/demo/news/news-' . $i . '.png', 'Economic News ' . $i, 'Impact and volatility plan', [30, 41, 59], [249, 115, 22]);
            $this->makeImage('upload/demo/knowledge/knowledge-' . $i . '.png', 'Knowledge ' . $i, 'Learning material', [239, 246, 255], [37, 99, 235]);
            $this->makeImage('upload/demo/payments/payment-proof-' . $i . '.png', 'Payment Proof', 'Demo receipt ' . $i, [248, 250, 252], [22, 163, 74], 900, 640);
            $this->makeImage('upload/demo/payments/wallet-receipt-' . $i . '.png', 'Wallet Receipt', 'Demo top-up ' . $i, [248, 250, 252], [14, 165, 233], 900, 640);
            $this->makeImage('upload/demo/general/gallery-' . $i . '.png', 'HC Gallery ' . $i, 'Professional community asset', [15, 23, 42], [168, 85, 247]);
        }

        for ($i = 1; $i <= 4; $i++) {
            $this->makeImage('upload/demo/general/service-' . $i . '.png', 'Service ' . $i, 'Demo operations', [248, 250, 252], [20, 184, 166]);
            $this->makeImage('upload/demo/general/portfolio-' . $i . '.png', 'Portfolio ' . $i, 'Performance case study', [17, 24, 39], [245, 158, 11]);
        }

        foreach (['HC Demo Public Traders', 'HC Demo Leader Alpha', 'HC Demo Prop Firm Lab'] as $title) {
            $this->makeImage('upload/demo/general/showcase-' . Str::slug($title) . '.png', $title, 'Community showcase poster', [15, 23, 42], [37, 99, 235]);
        }

        for ($i = 1; $i <= 8; $i++) {
            $this->createPdf(public_path('upload/demo/documents/onboarding-' . $i . '.pdf'), 'Onboarding Document ' . $i, [
                'Demo broker UID, deposit confirmation, and identity review checklist.',
                'Prepared for trader onboarding workflow testing.',
            ]);
        }
    }

    private function makeImage(string $relativePath, string $title, string $subtitle, array $bg, array $accent, int $width = 1200, int $height = 675): void
    {
        $path = public_path($relativePath);
        if (file_exists($path)) {
            return;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, $bg[0], $bg[1], $bg[2]);
        $accentColor = imagecolorallocate($image, $accent[0], $accent[1], $accent[2]);
        $white = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 203, 213, 225);
        $line = imagecolorallocatealpha($image, 148, 163, 184, 70);

        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
        imagefilledrectangle($image, 0, 0, $width, 18, $accentColor);

        for ($x = 0; $x < $width; $x += 80) {
            imageline($image, $x, 0, $x + 180, $height, $line);
        }

        imagefilledrectangle($image, 56, 70, $width - 56, $height - 70, imagecolorallocatealpha($image, 255, 255, 255, 110));
        imagefilledrectangle($image, 86, 100, 240, 108, $accentColor);
        imagestring($image, 5, 86, 145, strtoupper(substr($title, 0, 58)), $white);
        imagestring($image, 4, 88, 190, substr($subtitle, 0, 70), $muted);
        imagestring($image, 3, 88, $height - 128, 'HC GAMING STUDIO / PROFESSIONAL DEMO ASSET', $muted);

        imagepng($image, $path);
        imagedestroy($image);
    }

    private function createPdf(string $path, string $title, array $lines): void
    {
        if (file_exists($path)) {
            return;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $text = "BT\n/F1 18 Tf\n72 760 Td\n(" . $this->pdfText($title) . ") Tj\n/F1 11 Tf\n0 -34 Td\n";
        foreach ($lines as $line) {
            $text .= '(' . $this->pdfText($line) . ") Tj\n0 -18 Td\n";
        }
        $text .= "(Generated demo PDF for HC Gaming Studio testing.) Tj\nET";

        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[] = "<< /Length " . strlen($text) . " >>\nstream\n{$text}\nendstream";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        file_put_contents($path, $pdf);
    }

    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function demoUsers()
    {
        return DB::table('users')
            ->where('email', 'like', '%@demo.hc')
            ->orderBy('id')
            ->get();
    }

    private function setInviter(int $userId, ?int $inviterId): void
    {
        if (! $inviterId || $userId === $inviterId) {
            return;
        }

        DB::table('users')->where('id', $userId)->update([
            'invited_by' => $inviterId,
            'updated_at' => now(),
        ]);
    }

    private function updateOrInsert(string $table, array $keys, array $values): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $keys = $this->filterRow($table, $keys);
        $values = $this->filterRow($table, $values);

        if (empty($keys)) {
            return null;
        }

        DB::table($table)->updateOrInsert($keys, $values);

        return DB::table($table)->where($keys)->value('id');
    }

    private function deleteWhereIn(string $table, string $column, array $values): void
    {
        $values = array_values(array_filter($values, fn ($value) => $value !== null && $value !== ''));

        if (empty($values) || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $values)->delete();
    }

    private function deleteLike(string $table, string $column, string $pattern): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->where($column, 'like', $pattern)->delete();
    }

    private function insertIfMissing(string $table, array $row, array $uniqueColumns): ?int
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        $row = $this->filterRow($table, $row);
        $lookup = [];

        foreach ($uniqueColumns as $column) {
            if (array_key_exists($column, $row)) {
                $lookup[$column] = $row[$column];
            }
        }

        if (! empty($lookup)) {
            $existing = DB::table($table)->where($lookup)->value('id');
            if ($existing) {
                return (int) $existing;
            }
        }

        DB::table($table)->insert($row);

        return DB::getPdo()->lastInsertId() ? (int) DB::getPdo()->lastInsertId() : null;
    }

    private function filterRow(string $table, array $row): array
    {
        $columns = $this->columns[$table] ??= Schema::getColumnListing($table);

        return array_intersect_key($row, array_flip($columns));
    }

    private function dateByIndex(int $index, int $total): Carbon
    {
        $total = max(1, $total);
        $seconds = max(1, $this->startDate->diffInSeconds($this->endDate));
        $offset = (int) floor(($seconds / $total) * ($index % $total));

        return $this->startDate->copy()->addSeconds($offset)->addHours(($index * 3) % 24);
    }

    private function basePrice(string $pair): float
    {
        return match ($pair) {
            'XAUUSD' => 2350.00,
            'USDJPY' => 155.00,
            'US30' => 39200.00,
            'NAS100' => 18100.00,
            'BTCUSD' => 68000.00,
            'GBPUSD' => 1.2700,
            default => 1.0850,
        };
    }

    private function pipFactor(string $pair): float
    {
        if (str_contains($pair, 'XAU')) {
            return 0.10;
        }

        if (str_contains($pair, 'JPY')) {
            return 0.01;
        }

        if (in_array($pair, ['US30', 'NAS100', 'BTCUSD'], true)) {
            return 1.00;
        }

        return 0.0001;
    }
}
