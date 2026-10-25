<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\SitePreference;
use App\Models\TradingBlog;
use App\Models\TradingSignal;
use App\Models\User;
use App\Support\FrontendExperience;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FrontendExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // These legacy storefront tables are not represented by repository migrations.
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
                $table->timestamps();
            });
        }
        FrontendExperience::forgetCache();
    }

    public function test_homepage_resolves_all_module_combinations_and_preferences(): void
    {
        $this->configure(true, false, 'ecommerce');
        $this->assertSame('trading', FrontendExperience::homepage());
        $this->get('/')->assertOk()->assertSee('Navigate markets with');
        $this->get('/product')->assertNotFound();

        $this->configure(false, true, 'trading');
        $this->assertSame('ecommerce', FrontendExperience::homepage());
        $this->get('/')->assertOk()->assertSee('The HC Gaming edit');
        $this->get('/trading-hub')->assertNotFound();

        $this->configure(true, true, 'trading');
        $this->get('/')->assertOk()->assertSee('Navigate markets with');
        $this->configure(true, true, 'ecommerce');
        $this->get('/')->assertOk()->assertSee('The HC Gaming edit');
        $this->configure(true, true, 'combined');
        $this->get('/')->assertOk()->assertSee('One platform / Two experiences');
        $this->get('/trading-hub')->assertOk();
        $this->get('/store')->assertOk();

        $this->configure(true, true, 'unexpected');
        $this->assertSame('ecommerce', FrontendExperience::homepage());

        $this->configure(false, false, 'combined');
        $this->assertSame('neutral', FrontendExperience::homepage());
        $this->get('/')->assertOk()->assertSee('We will be back soon');
        $this->get('/store')->assertNotFound();
        $this->get('/trading-hub/insights')->assertNotFound();
    }

    public function test_admin_can_change_homepage_and_reenable_modules_when_both_are_disabled(): void
    {
        $this->configure(false, false, 'combined');
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin)->get(route('admin.features.index'))->assertOk();
        $this->post(route('admin.features.homepage'), ['homepage_experience' => 'trading'])->assertRedirect();
        $this->assertSame('trading', SitePreference::where('key', FrontendExperience::PREFERENCE_KEY)->value('value'));

        $trading = FeatureToggle::where('feature_name', FeatureToggle::MODULE_TRADING)->firstOrFail();
        $this->post(route('admin.features.update', $trading->id), ['enabled' => '1'])->assertRedirect();
        $this->get('/')->assertOk()->assertSee('Navigate markets with');
    }

    public function test_non_admin_cannot_update_module_settings(): void
    {
        $user = User::factory()->create(['role_id' => 760, 'status' => 1]);
        $this->actingAs($user)->post(route('admin.features.homepage'), ['homepage_experience' => 'combined'])->assertForbidden();
    }

    public function test_website_research_hides_explicit_demo_posts_without_changing_cms_records(): void
    {
        $author = User::factory()->create(['role_id' => 1, 'status' => 1]);
        foreach (['[Demo] Sample research', 'Real market note'] as $title) {
            TradingBlog::create([
                'title' => $title,
                'slug' => \Illuminate\Support\Str::slug($title),
                'category' => 'market_outlook',
                'excerpt' => 'Published research summary.',
                'content' => '<p>Published research body.</p>',
                'status' => TradingBlog::STATUS_PUBLISHED,
                'published_at' => now()->subMinute(),
                'author_id' => $author->id,
            ]);
        }

        $this->get('/trading-hub/insights')->assertOk()
            ->assertSee('Real market note')->assertDontSee('[Demo] Sample research');
        $this->actingAs($author)->get('/trading/blogs')->assertOk()
            ->assertSee('Real market note')->assertDontSee('[Demo] Sample research');
        $this->assertSame(2, TradingBlog::count());
    }

    public function test_signal_pages_show_only_published_records_to_authorized_members(): void
    {
        $member = User::factory()->create(['role_id' => 760, 'status' => 1]);
        $published = TradingSignal::create([
            'signal_title' => 'Published setup', 'trading_pair' => 'EURUSD',
            'immediate_action' => 'BUY', 'target_1' => '1.1500', 'target_2' => '1.1600',
            'status' => 1, 'workflow_status' => TradingSignal::WORKFLOW_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);
        $draft = TradingSignal::create([
            'signal_title' => 'Draft setup', 'trading_pair' => 'PRIVATEPAIR',
            'immediate_action' => 'SELL', 'target_1' => '1.0000', 'target_2' => '0.9000',
            'status' => 1, 'workflow_status' => TradingSignal::WORKFLOW_DRAFT,
        ]);

        $this->get('/trading-hub/signals')->assertOk()->assertDontSee('EURUSD');
        $this->actingAs($member)->get('/trading-hub/signals')->assertOk()
            ->assertSee('EURUSD')->assertDontSee('PRIVATEPAIR');
        $this->get(route('member.signals.view', $published->id))->assertOk()->assertSee('EURUSD');
        $this->get(route('member.signals.view', $draft->id))->assertForbidden();
    }

    public function test_trading_submodule_switch_keeps_ecommerce_store_available(): void
    {
        $this->configure(true, true, 'combined');
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin)->post(route('admin.trading-modules.update', 'market_analyst'), ['enabled' => '0'])
            ->assertRedirect();
        $this->get(route('store.home'))->assertOk();
        $this->get(route('trading.hub.outlooks.index'))->assertStatus(503);
        $this->get(route('home'))->assertOk()->assertSee('One platform / Two experiences');
    }

    private function configure(bool $trading, bool $ecommerce, string $preference): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => $trading]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => $ecommerce]);
        SitePreference::updateOrCreate(['key' => FrontendExperience::PREFERENCE_KEY], ['value' => $preference]);
    }
}
