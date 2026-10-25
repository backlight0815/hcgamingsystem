<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\FeatureToggle;
use App\Models\MarketAnalysis;
use App\Models\TraderOnboardingApplication;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketOutlookPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Legacy storefront tables are not covered by the repository migrations.
        foreach (['home_slides', 'abouts', 'product_categories', 'product', 'services', 'blogs', 'footers'] as $name) {
            if (Schema::hasTable($name)) {
                continue;
            }
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                if ($name === 'product') {
                    $table->unsignedBigInteger('product_category_id')->nullable();
                    $table->string('product_name')->nullable();
                    $table->softDeletes();
                }
                if ($name === 'product_categories') {
                    $table->softDeletes();
                }
                if ($name === 'blogs') {
                    $table->string('blog_title')->nullable();
                }
                if ($name === 'services') {
                    $table->string('service_title')->nullable();
                }
                if ($name === 'footers') {
                    $table->string('email')->nullable();
                    $table->string('number')->nullable();
                    $table->text('address')->nullable();
                    $table->text('short_description')->nullable();
                    $table->string('copyright')->nullable();
                }
                $table->timestamps();
            });
        }
    }

    public function test_admin_create_remains_draft_until_publish_then_appears_in_trading_hub(): void
    {
        $community = Community::create(['name' => 'Research', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin)->post(route('market-analyst.store'), [
            'community_id' => $community->id, 'title' => 'Session outlook',
            'market' => 'XAUUSD', 'analysis_date' => now()->toDateString(),
            'market_overview' => 'Market structure report.',
        ])->assertRedirect(route('market-analyst.index'));
        $item = MarketAnalysis::sole();
        $this->assertSame('draft', $item->publication_status);
        $this->getJson(route('trading.hub.outlooks.index'))->assertJsonCount(0, 'data');
        $customer = User::factory()->create(['role_id' => 700, 'status' => 1]);
        $this->actingAs($customer)->post(route('market-analyst.publish', $item->id))->assertForbidden();
        $this->actingAs($admin);
        $this->post(route('market-analyst.publish', $item->id))->assertRedirect();
        $this->assertSame('published', $item->fresh()->publication_status);
        $this->app['auth']->logout();
        $this->getJson(route('trading.hub.outlooks.index'))->assertJsonCount(1, 'data');
    }

    public function test_public_limit_detail_authorization_and_unpublish_follow_the_same_publication_order(): void
    {
        $community = Community::create(['name' => 'Research', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $items = collect(range(1, 8))->map(fn ($number) => MarketAnalysis::create([
            'community_id' => $community->id,
            'title' => 'Outlook '.$number,
            'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(),
            'market_overview' => 'Analysis '.$number,
            'publication_status' => 'published',
            'published_at' => now()->subDays(8 - $number),
        ]));
        $draft = MarketAnalysis::create([
            'community_id' => $community->id, 'title' => 'Private draft', 'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(), 'market_overview' => 'Secret',
        ]);
        $scheduled = MarketAnalysis::create([
            'community_id' => $community->id, 'title' => 'Future outlook', 'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(), 'market_overview' => 'Not yet public',
            'publication_status' => 'published', 'published_at' => now()->addDay(),
        ]);

        $this->get(route('trading.hub.outlooks.index'))->assertOk()
            ->assertDontSee('Outlook 1')->assertDontSee('Outlook 2')->assertDontSee('Private draft')->assertDontSee('Future outlook')
            ->assertSee('Outlook 3')->assertSee('Outlook 8');
        $this->getJson(route('trading.hub.outlooks.index'))->assertOk()
            ->assertJsonCount(6, 'data')->assertJsonPath('data.0.title', 'Outlook 8');
        $this->getJson(route('api.market_outlooks.index'))->assertOk()
            ->assertJsonCount(6, 'data')->assertDontSee('example.test/hook');
        $this->getJson(route('trading.hub.outlooks.index', ['q' => 'Outlook 1', 'page' => 2]))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('trading.hub.outlooks.show', $items[0]->id))->assertNotFound()
            ->assertDontSee('Analysis 1');
        $this->getJson(route('api.market_outlooks.show', $items[0]->id))->assertNotFound();
        $this->get(route('trading.hub.outlooks.show', $items[0]->id))->assertForbidden()
            ->assertSee('Historical Market Outlooks');
        $this->getJson(route('trading.hub.outlooks.show', $draft->id))->assertNotFound();
        $this->getJson(route('trading.hub.outlooks.show', $scheduled->id))->assertNotFound();

        $this->actingAs($admin)->post(route('market-analyst.unpublish', $items[7]->id))->assertRedirect();
        $this->assertSame('draft', $items[7]->fresh()->publication_status);
        $this->app['auth']->logout();
        $this->get(route('trading.hub.outlooks.index'))->assertOk()
            ->assertSee('Outlook 2')->assertDontSee('Outlook 8');
        $this->getJson(route('trading.hub.outlooks.show', $items[7]->id))->assertNotFound();

        $this->actingAs($admin)->post(route('market-analyst.publish', $draft->id))->assertRedirect();
        $this->assertNotNull($draft->fresh()->published_at);
        $this->app['auth']->logout();
        $this->getJson(route('trading.hub.outlooks.show', $draft->id))->assertOk()
            ->assertJsonPath('data.title', 'Private draft');
        $this->getJson(route('trading.hub.outlooks.show', $items[1]->id))->assertNotFound();
    }

    public function test_latest_approved_trader_onboarding_controls_history_and_private_response_cache(): void
    {
        $community = Community::create(['name' => 'Research', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        $old = MarketAnalysis::create([
            'community_id' => $community->id, 'title' => 'Old outlook', 'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(), 'publication_status' => 'published',
            'published_at' => now()->subDays(20),
        ]);
        foreach (range(1, 6) as $number) {
            MarketAnalysis::create([
                'community_id' => $community->id, 'title' => 'Recent '.$number, 'market' => 'XAUUSD',
                'analysis_date' => now()->toDateString(), 'publication_status' => 'published',
                'published_at' => now()->subDays(7 - $number),
            ]);
        }
        $trader = User::factory()->create(['role_id' => 750, 'status' => 1]);
        $this->actingAs($trader)->getJson(route('trading.hub.outlooks.show', $old->id))->assertNotFound();
        $approval = TraderOnboardingApplication::create([
            'user_id' => $trader->id, 'status' => TraderOnboardingApplication::STATUS_APPROVED,
            'discord_username' => 'approved-trader', 'broker_uid' => '12345',
            'broker_email' => 'trader@example.test',
        ]);
        $this->getJson(route('trading.hub.outlooks.show', $old->id))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.title', 'Old outlook');
        $this->getJson(route('trading.hub.outlooks.index'))->assertOk()->assertJsonCount(7, 'data');
        $approval->update(['status' => TraderOnboardingApplication::STATUS_PENDING]);
        $this->getJson(route('trading.hub.outlooks.show', $old->id))->assertNotFound();
        Sanctum::actingAs($trader);
        $this->getJson(route('api.market_outlooks.index'))->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/mobile/v1/content')->assertOk()->assertJsonCount(6, 'data.market_analyses');
        $approval->update(['status' => TraderOnboardingApplication::STATUS_APPROVED]);
        $this->getJson(route('api.market_outlooks.index'))->assertOk()->assertJsonCount(7, 'data');
        $this->getJson(route('api.market_outlooks.show', $old->id))->assertOk()->assertJsonPath('data.title', 'Old outlook');
        $this->getJson('/api/mobile/v1/content')->assertOk()->assertJsonCount(7, 'data.market_analyses');
    }

    public function test_trader_view_market_filter_orders_only_by_selected_market(): void
    {
        $community = Community::create(['name' => 'Research', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        MarketAnalysis::create([
            'community_id' => $community->id,
            'title' => 'Gold outlook',
            'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(),
            'publication_status' => 'published',
            'published_at' => now(),
        ]);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($admin)->get(route('trading.market-analyst.index'))
            ->assertOk()->assertSee('XAUUSD');

        $marketQuery = collect($queries)->first(fn ($sql) => str_contains(strtolower($sql), 'select distinct')
            && str_contains(strtolower($sql), 'market_analyses'));
        $this->assertNotNull($marketQuery);
        $orderBy = substr($marketQuery, stripos($marketQuery, 'order by'));
        $this->assertMatchesRegularExpression('/order by\s+["`]?market["`]?\s+asc/i', $orderBy);
        $this->assertStringNotContainsString('published_at', $orderBy);
    }

    public function test_market_analyst_is_owned_by_trading_in_every_module_combination(): void
    {
        $community = Community::create(['name' => 'Research', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        $outlook = MarketAnalysis::create([
            'community_id' => $community->id, 'title' => 'Trading only outlook', 'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(), 'publication_status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $customer = User::factory()->create(['role_id' => 700, 'status' => 1]);

        foreach ([[true, false], [false, true], [true, true], [false, false]] as [$trading, $ecommerce]) {
            FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => $trading]);
            FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => $ecommerce]);

            $this->get('/store/market-insights')->assertNotFound();
            $this->get('/store/market-insights/'.$outlook->id)->assertNotFound();

            if ($ecommerce) {
                $this->get(route('store.home'))->assertOk()
                    ->assertDontSee('Market Insights')->assertDontSee('Trading only outlook');
            } else {
                $this->get(route('store.home'))->assertNotFound();
            }

            if ($trading) {
                $this->get(route('trading.hub.outlooks.index'))->assertOk()
                    ->assertSee('Trading only outlook')->assertSee('class="th-body"', false);
                $this->get(route('trading.hub.outlooks.show', $outlook->id))->assertOk()
                    ->assertSee('Trading only outlook')->assertSee('class="th-body"', false);
                $this->getJson(route('api.market_outlooks.index'))->assertOk()->assertJsonCount(1, 'data');
                $this->actingAs($admin)->get(route('market-analyst.index'))->assertOk();
                $this->actingAs($customer)->get(route('market-analyst.edit', $outlook->id))->assertForbidden();
                $this->post(route('market-analyst.publish', $outlook->id))->assertForbidden();
                $this->actingAs($customer)->post(route('market-analyst.unpublish', $outlook->id))->assertForbidden();
            } else {
                $this->get(route('trading.hub.outlooks.index'))->assertNotFound();
                $this->get(route('trading.hub.outlooks.show', $outlook->id))->assertNotFound();
                $this->getJson(route('api.market_outlooks.index'))->assertNotFound();
                $this->getJson(route('api.market_outlooks.show', $outlook->id))->assertNotFound();
                $this->getJson('/api/mobile/v1/market-insights/latest')->assertNotFound();
                $this->actingAs($admin)->get(route('market-analyst.index'))->assertNotFound();
                $this->post(route('market-analyst.publish', $outlook->id))->assertNotFound();
                $this->get(route('market_insight.dashboard'))->assertNotFound();
            }
            $this->app['auth']->logout();
        }

        $this->assertSame('published', $outlook->fresh()->publication_status);
    }

    public function test_editing_published_trading_outlook_preserves_publication_and_chart(): void
    {
        $community = Community::create(['name' => 'Research', 'status' => 1, 'discord_webhook' => 'https://example.test/hook']);
        $publishedAt = now()->subDay()->startOfMinute();
        $outlook = MarketAnalysis::create([
            'community_id' => $community->id, 'title' => 'Original outlook', 'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(), 'market_overview' => 'Original analysis',
            'outlook_image' => 'upload/outlook/existing-chart.png',
            'publication_status' => 'published', 'published_at' => $publishedAt,
        ]);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);

        $this->actingAs($admin)->post(route('market-analyst.update', $outlook->id), [
            'community_id' => $community->id, 'title' => 'Updated outlook', 'market' => 'XAUUSD',
            'analysis_date' => now()->toDateString(), 'market_overview' => 'Updated analysis',
        ])->assertRedirect(route('market-analyst.index'));

        $outlook->refresh();
        $this->assertSame('published', $outlook->publication_status);
        $this->assertTrue($outlook->published_at->equalTo($publishedAt));
        $this->assertSame('upload/outlook/existing-chart.png', $outlook->outlook_image);
        $this->get(route('trading.hub.outlooks.show', $outlook->id))->assertOk()
            ->assertSee('Updated outlook')->assertSee('Updated analysis');
    }
}
