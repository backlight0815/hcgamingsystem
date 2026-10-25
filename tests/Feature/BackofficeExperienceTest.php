<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\User;
use App\Support\BackofficeExperience;
use App\Support\BackofficeNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class BackofficeExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_backoffice_mode_follows_persisted_module_matrix_and_refreshes_after_toggle(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin);

        $this->configure(true, false);
        $this->assertSame(BackofficeExperience::DEALERSHIP, BackofficeExperience::mode());
        $this->get(route('admin.features.index'))->assertOk()
            ->assertSee('data-backoffice-mode="dealership"', false)
            ->assertSee('backoffice-experiences.css')
            ->assertDontSee('backoffice-contrast.css')
            ->assertSee('HC / DEALERSHIP')
            ->assertSee('hc-backoffice-mark.svg')
            ->assertDontSee('navigator.serviceWorker.register')
            ->assertSee('Inventory &amp; Dealership', false)
            ->assertDontSee('Trading Signals');

        $this->configure(false, true);
        $this->assertSame(BackofficeExperience::TRADING, BackofficeExperience::mode());
        $this->get(route('admin.features.index'))->assertOk()
            ->assertSee('data-backoffice-mode="trading"', false)
            ->assertSee('backoffice-experiences.css')
            ->assertDontSee('backoffice-contrast.css')
            ->assertSee('HC / TRADING HUB')
            ->assertSee('hc-backoffice-mark.svg')
            ->assertDontSee('navigator.serviceWorker.register')
            ->assertSee('Signal Operations')
            ->assertDontSee('Product Centre');

        $this->configure(true, true);
        $this->assertSame(BackofficeExperience::EXISTING, BackofficeExperience::mode());
        $this->get(route('admin.features.index'))->assertOk()
            ->assertDontSee('data-backoffice-mode=')
            ->assertDontSee('backoffice-experiences.css')
            ->assertSee('backoffice-contrast.css')
            ->assertDontSee('hc-backoffice-mark.svg')
            ->assertSee('navigator.serviceWorker.register')
            ->assertSee('Company Setting');

        $this->configure(false, false);
        $this->assertSame(BackofficeExperience::EXISTING, BackofficeExperience::mode());
        $this->get(route('admin.features.index'))->assertOk()
            ->assertSee('No Module Enabled')
            ->assertDontSee('backoffice-experiences.css')
            ->assertSee('backoffice-contrast.css');
    }

    public function test_disabled_module_routes_are_not_available_but_shared_settings_remain_available(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin);

        $this->configure(true, false);
        $this->assertUnavailable(route('admin.trading.blogs.index'));
        $this->assertUnavailable(route('all.trading.signals'));
        $this->get(route('admin.features.index'))->assertOk();

        $this->configure(false, true);
        $this->assertUnavailable(route('all.product'));
        $this->assertUnavailable(route('all.shipping.order'));
        $this->get(route('admin.features.index'))->assertOk();
    }

    public function test_navigation_reorders_existing_authorized_items_without_creating_links(): void
    {
        $menus = [
            ['heading' => 'Menu'],
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => '/dashboard'],
            ['label' => 'Company Setting', 'icon' => 'settings', 'url' => '/settings'],
            ['label' => 'Product Centre', 'icon' => 'product', 'children' => [['label' => 'Products', 'url' => '/products']]],
            ['label' => 'Custom Tool', 'icon' => 'custom', 'url' => '/custom'],
        ];

        $organized = BackofficeNavigation::organize($menus, BackofficeExperience::DEALERSHIP);
        $this->assertSame(['Overview', 'Inventory & Dealership', 'Administration', 'More tools'],
            array_column(array_filter($organized, fn (array $item): bool => isset($item['heading'])), 'heading'));
        $this->assertSame(['/dashboard', '/settings', '/custom'],
            array_values(array_column(array_filter($organized, fn (array $item): bool => isset($item['url'])), 'url')));
        $this->assertSame($menus, BackofficeNavigation::organize($menus, BackofficeExperience::EXISTING));
    }

    public function test_module_combinations_keep_the_correct_shell_and_pms_navigation(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin);

        foreach ([true, false] as $trading) {
            foreach ([true, false] as $ecommerce) {
                foreach ([true, false] as $pms) {
                    $this->configure($ecommerce, $trading);
                    FeatureToggle::updateOrCreate(
                        ['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT],
                        ['enabled' => $pms]
                    );

                    $expected = BackofficeExperience::forModules($ecommerce, $trading);
                    $this->assertSame($expected, BackofficeExperience::mode());
                    $response = $this->get(route('admin.features.index'))->assertOk();
                    if ($expected === BackofficeExperience::EXISTING) {
                        $response->assertDontSee('data-backoffice-mode=');
                    } else {
                        $response->assertSee('data-backoffice-mode="'.$expected.'"', false);
                    }
                    if ($pms) {
                        $response->assertSee('ri-folder-chart-line');
                    } else {
                        $response->assertDontSee('ri-folder-chart-line');
                    }
                }
            }
        }

        FeatureToggle::updateOrCreate(
            ['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT],
            ['enabled' => true]
        );
        $this->app['auth']->logout();
        $this->get(route('pms.login'))->assertOk()->assertSee('hc-backoffice-mark.svg');
        $this->actingAs($admin)->get(route('pms.dashboard'))->assertOk()->assertSee('hc-backoffice-mark.svg');
    }

    private function configure(bool $ecommerce, bool $trading): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => $ecommerce]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => $trading]);
    }

    private function assertUnavailable(string $url): void
    {
        $this->withoutExceptionHandling();
        try {
            $this->get($url);
            $this->fail("Expected a disabled module route to be unavailable: {$url}");
        } catch (NotFoundHttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        } finally {
            $this->withExceptionHandling();
        }
    }
}
