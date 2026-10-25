<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmsProfile;
use App\Models\ProjectManagementUser;
use App\Models\SitePreference;
use App\Models\User;
use App\Support\FrontendExperience;
use App\Support\ModuleAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class ModuleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function toggle(bool $trading, bool $dealership, bool $pms): void
    {
        foreach ([FeatureToggle::MODULE_TRADING => $trading, FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE => $dealership,
            FeatureToggle::MODULE_PROJECT_MANAGEMENT => $pms] as $key => $enabled) {
            FeatureToggle::updateOrCreate(['feature_name' => $key], ['enabled' => $enabled]);
        }
        FrontendExperience::forgetCache();
    }

    private function pmsUser(string $email = 'qa@example.test'): User
    {
        $user = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now()]);
        ProjectManagementUser::create(['user_id' => $user->id, 'name' => $user->name,
            'email' => $email, 'password' => Hash::make('StrongPass123'), 'status' => 'active']);
        return $user;
    }

    public function test_pms_login_and_api_survive_every_enablement_and_visibility_combination(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $user = $this->pmsUser();
        foreach ([false, true] as $trading) foreach ([false, true] as $dealership) foreach ([false, true] as $pms) {
            $this->toggle($trading, $dealership, $pms);
            foreach ([false, true] as $visible) {
                DB::table('module_access_settings')->updateOrInsert(['module_key' => ModuleAccess::PMS],
                    ['public_visible' => $visible, 'registration_policy' => 'approval', 'updated_at' => now(), 'created_at' => now()]);
                $this->get(route('pms.login'))->assertStatus($pms ? 200 : 404);
                $this->get(route('tradinghub.login'))->assertStatus($trading ? 200 : 404);
                $this->get(route('dealership.login'))->assertStatus($dealership ? 200 : 404);
                if (! $pms) {
                    $this->post(route('pms.authenticate'), ['email' => 'qa@example.test', 'password' => 'StrongPass123'])->assertNotFound();
                    $this->postJson(route('api.pms.auth.login'), ['email' => 'qa@example.test', 'password' => 'StrongPass123'])->assertNotFound();
                    continue;
                }
                $this->post(route('pms.authenticate'), ['email' => 'qa@example.test', 'password' => 'StrongPass123'])
                    ->assertRedirect(route('pms.dashboard'));
                $this->assertAuthenticatedAs($user, 'pms');
                $this->get(route('pms.dashboard'))->assertOk();
                $token = $this->postJson(route('api.pms.auth.login'), ['email' => 'qa@example.test', 'password' => 'StrongPass123'])
                    ->assertOk()->json('token');
                $this->withToken($token)->getJson(route('api.pms.auth.profile'))->assertOk();
                $this->post(route('pms.logout'))->assertRedirect(route('pms.login'));
            }
        }
    }

    public function test_module_credentials_are_isolated_and_explicit_membership_controls_access(): void
    {
        $this->toggle(true, true, true);
        $pms = $this->pmsUser('shared@example.test');
        $trader = User::factory()->create(['role_id' => 201, 'status' => 1, 'email' => 'shared@example.test']);
        $dealer = User::factory()->create(['role_id' => 350, 'status' => 1]);

        $this->post(route('tradinghub.authenticate'), ['username' => 'shared@example.test', 'password' => 'StrongPass123'])
            ->assertSessionHasErrors('username');
        $this->post(route('dealership.authenticate'), ['username' => $dealer->email, 'password' => 'password'])
            ->assertRedirect(route('all.statistics'));
        $this->assertAuthenticatedAs($dealer, 'web');
        $this->get(route('trading.positions.index'))->assertForbidden();
        $this->post(route('logout'));

        $this->post(route('pms.authenticate'), ['email' => $trader->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->post(route('pms.authenticate'), ['email' => 'shared@example.test', 'password' => 'StrongPass123'])
            ->assertRedirect(route('pms.dashboard'));
        $this->assertAuthenticatedAs($pms, 'pms');
        $this->get(route('dealer.address'))->assertRedirect(route('dealership.login'));
        $this->post(route('pms.logout'));

        $this->post(route('tradinghub.authenticate'), ['username' => $trader->email, 'password' => 'password'])
            ->assertRedirect(route('all.statistics'));
        $this->assertAuthenticatedAs($trader, 'web');
        $this->assertFalse(ModuleAccess::allows($trader, ModuleAccess::DEALERSHIP));
        $this->get(route('dealer.address'))->assertForbidden();
        DB::table('module_memberships')->insert(['user_id' => $trader->id, 'module_key' => ModuleAccess::DEALERSHIP,
            'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertTrue(ModuleAccess::allows($trader, ModuleAccess::DEALERSHIP));
        $this->post(route('dealership.authenticate'), ['username' => $trader->email, 'password' => 'password'])
            ->assertRedirect(route('all.statistics'));
        DB::table('module_memberships')->where('user_id', $trader->id)->update(['status' => 'suspended']);
        $this->get(route('dealer.address'))->assertForbidden();
    }

    public function test_pms_deep_link_returns_to_pms_and_rejects_external_intended_url(): void
    {
        $this->toggle(true, true, true);
        $user = $this->pmsUser();
        $this->get(route('pms.projects.index'))->assertRedirect(route('pms.login'));
        $this->post(route('pms.authenticate'), ['email' => 'qa@example.test', 'password' => 'StrongPass123'])
            ->assertRedirect(route('pms.projects.index'));
        $this->assertAuthenticatedAs($user, 'pms');
        $this->get(route('pms.projects.index'))->assertOk();
        $this->post(route('pms.logout'));
        $this->withSession(['url.intended' => 'https://outside.example/steal'])->post(route('pms.authenticate'),
            ['email' => 'qa@example.test', 'password' => 'StrongPass123'])->assertRedirect(route('pms.dashboard'));
    }

    public function test_configuration_changes_visibility_without_changing_login_availability(): void
    {
        $this->toggle(true, true, true);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin)->post(route('admin.features.module_access', ModuleAccess::PMS),
            ['public_visible' => '1', 'registration_policy' => 'invitation'])->assertRedirect();
        $this->assertTrue(ModuleAccess::publicVisible(ModuleAccess::PMS));
        $this->post(route('logout'));
        $this->get(route('pms.login'))->assertOk();
        $this->actingAs($admin)->post(route('admin.features.module_access', ModuleAccess::PMS),
            ['public_visible' => '0', 'registration_policy' => 'invitation'])->assertRedirect();
        $this->assertFalse(ModuleAccess::publicVisible(ModuleAccess::PMS));
        $this->post(route('logout'));
        $this->get(route('pms.login'))->assertOk();
        $this->assertDatabaseHas('module_access_settings', ['module_key' => ModuleAccess::PMS, 'updated_by' => $admin->id]);
    }

    public function test_pms_status_and_membership_are_checked_before_session_creation(): void
    {
        $this->toggle(true, true, true);
        $pms = $this->pmsUser();
        $identity = $pms->projectManagementIdentity;
        $identity->update(['status' => 'suspended']);
        $this->post(route('pms.authenticate'), ['email' => $identity->email, 'password' => 'StrongPass123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('pms');
        $identity->update(['status' => 'active']);
        $pms->pmsProfile->update(['status' => 'suspended']);
        $this->post(route('pms.authenticate'), ['email' => $identity->email, 'password' => 'StrongPass123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('pms');
        $outsider = User::factory()->create(['role_id' => 350]);
        $this->post(route('pms.authenticate'), ['email' => $outsider->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->postJson(route('api.pms.auth.login'), ['email' => $outsider->email, 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_real_api_tokens_remain_scoped_to_their_module(): void
    {
        $this->toggle(true, true, true);
        $user = User::factory()->create(['role_id' => 201, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now()]);
        $pmsToken = $user->createToken('pms-only', ['pms'])->plainTextToken;
        $tradingToken = $user->createToken('trading-only', ['tradinghub'])->plainTextToken;
        $this->withToken($pmsToken)->getJson(route('api.pms.auth.profile'))->assertOk();
        Auth::forgetGuards();
        $this->withToken($pmsToken)->getJson(route('api.mobile.v1.auth.me'))->assertForbidden();
        Auth::forgetGuards();
        $this->withToken($tradingToken)->getJson(route('api.mobile.v1.auth.me'))->assertOk();
        Auth::forgetGuards();
        $this->withToken($tradingToken)->getJson(route('api.pms.auth.profile'))->assertForbidden();
    }

    public function test_pms_login_rate_limit_and_registration_policy_are_independent_of_visibility(): void
    {
        $this->toggle(true, true, true);
        DB::table('pms_workspace_settings')->update(['registration_policy' => 'invitation']);
        DB::table('module_access_settings')->insert(['module_key' => 'pms', 'public_visible' => false,
            'registration_policy' => 'invitation', 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('pms.login'))->assertOk();
        $this->get(route('pms.register'))->assertNotFound();
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post(route('pms.authenticate'), ['email' => 'unknown@example.test', 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }
        $this->post(route('pms.authenticate'), ['email' => 'unknown@example.test', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_super_admin_can_grant_and_suspend_a_cross_module_membership(): void
    {
        $this->toggle(true, true, true);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $trader = User::factory()->create(['role_id' => 201, 'status' => 1]);
        $this->actingAs($trader)->post(route('admin.features.module_membership'), [
            'user_id' => $trader->id, 'module_key' => 'dealership', 'status' => 'active',
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('admin.features.module_membership'), [
            'user_id' => $trader->id, 'module_key' => 'dealership', 'status' => 'active',
        ])->assertRedirect();
        $this->assertTrue(ModuleAccess::allows($trader, ModuleAccess::DEALERSHIP));
        $this->assertDatabaseHas('module_memberships', ['user_id' => $trader->id, 'module_key' => 'dealership', 'assigned_by' => $admin->id]);
        $this->post(route('admin.features.module_membership'), [
            'user_id' => $trader->id, 'module_key' => 'dealership', 'status' => 'suspended',
        ])->assertRedirect();
        $this->assertFalse(ModuleAccess::allows($trader, ModuleAccess::DEALERSHIP));
    }

    public function test_public_pms_entry_appears_only_when_visibility_is_enabled(): void
    {
        $this->toggle(true, true, true);
        Schema::create('product', function (Blueprint $table): void {
            $table->id();
            $table->string('product_name');
            $table->softDeletes();
            $table->timestamps();
        });
        SitePreference::updateOrCreate(['key' => FrontendExperience::PREFERENCE_KEY], ['value' => FrontendExperience::COMBINED]);
        DB::table('module_access_settings')->insert(['module_key' => 'pms', 'public_visible' => false,
            'registration_policy' => 'approval', 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('home'))->assertOk()->assertDontSee('03 / Project Management');
        DB::table('module_access_settings')->where('module_key', 'pms')->update(['public_visible' => true]);
        $this->get(route('home'))->assertOk()->assertSee('03 / Project Management');
    }

    public function test_login_navigation_is_independent_of_public_listing_and_registration(): void
    {
        $this->toggle(true, true, true);
        DB::table('pms_workspace_settings')->update(['registration_policy' => 'invitation']);
        DB::table('module_access_settings')->insert(['module_key' => 'pms', 'public_visible' => false,
            'show_login_entry' => true, 'registration_policy' => 'invitation', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['login', 'tradinghub.login', 'dealership.login'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Access Another Module')
                ->assertSee('data-module-login="pms"', false)->assertSee('Staff Sign In');
        }
        $this->get(route('tradinghub.login'))->assertSee('data-module-login="dealership"', false)
            ->assertDontSee('data-module-login="tradinghub"', false);
        $this->get(route('pms.login.alias'))->assertRedirect(route('pms.login'));
        $this->get(route('pms.login'))->assertOk()->assertDontSee('Request access')
            ->assertSee('data-module-login="tradinghub"', false);
        $this->get(route('pms.register'))->assertNotFound();
        $this->assertFalse(ModuleAccess::publicVisible('pms'));

        DB::table('module_access_settings')->where('module_key', 'pms')->update(['show_login_entry' => false, 'public_visible' => true]);
        $this->get(route('tradinghub.login'))->assertDontSee('data-module-login="pms"', false);
        $this->get(route('pms.login'))->assertOk();
        $this->pmsUser();
        $this->post(route('pms.authenticate'), ['email' => 'qa@example.test', 'password' => 'StrongPass123'])
            ->assertRedirect(route('pms.dashboard'));
    }

    public function test_login_navigation_respects_all_enablement_combinations(): void
    {
        foreach ([false, true] as $trading) foreach ([false, true] as $dealership) foreach ([false, true] as $pms) {
            $this->toggle($trading, $dealership, $pms);
            $response = $this->get(route('login'))->assertOk();
            foreach (['tradinghub' => $trading, 'dealership' => $dealership, 'pms' => $pms] as $key => $enabled) {
                $marker = 'data-module-login="'.$key.'"';
                $enabled ? $response->assertSee($marker, false) : $response->assertDontSee($marker, false);
            }
            if (! $trading && ! $dealership && ! $pms) $response->assertDontSee('Access Another Module');
        }
    }

    public function test_admin_can_change_login_discovery_without_changing_other_settings(): void
    {
        $this->toggle(true, true, true);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $values = ['public_visible' => '0', 'registration_policy' => 'invitation'];
        $this->actingAs($admin)->post(route('admin.features.module_access', 'pms'), $values + ['show_login_entry' => '0'])->assertRedirect();
        $this->assertDatabaseHas('module_access_settings', ['module_key' => 'pms', 'show_login_entry' => false, 'public_visible' => false]);
        $this->post(route('admin.features.module_access', 'pms'), $values)->assertRedirect();
        $this->assertFalse(ModuleAccess::loginEntryVisible('pms')); // Old forms preserve the saved choice.
        $this->post(route('admin.features.module_access', 'pms'), $values + ['show_login_entry' => '1'])->assertRedirect();
        $this->assertTrue(ModuleAccess::loginEntryVisible('pms'));
        $this->assertFalse(ModuleAccess::publicVisible('pms'));
        $this->assertFalse(ModuleAccess::registrationAllowed('pms'));
        $this->post(route('admin.features.module_access', 'pms'), $values + ['show_login_entry' => 'invalid'])->assertSessionHasErrors('show_login_entry');
        $this->post(route('logout'));
        $this->actingAs(User::factory()->create(['role_id' => 201]))
            ->post(route('admin.features.module_access', 'pms'), $values + ['show_login_entry' => '0'])->assertForbidden();
        $this->assertTrue(ModuleAccess::loginEntryVisible('pms'));
    }

    public function test_ticket_deep_link_preserves_project_authorization_after_login(): void
    {
        $this->toggle(true, true, true);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $staff = $this->pmsUser();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => 'AUTH', 'name' => 'Login QA', 'priority' => 'P2'])->assertCreated();
        $project = \App\Models\PmProject::where('key', 'AUTH')->firstOrFail();
        $this->postJson(route('pms.projects.members.store', $project), ['user_id' => $staff->id, 'role' => 'Developer'])->assertOk();
        $this->postJson(route('pms.issues.store'), ['project_id' => $project->id, 'summary' => 'Authorized ticket', 'type' => 'Bug', 'priority' => 'P2'])->assertCreated();
        $ticket = \App\Models\PmWorkItem::firstOrFail();
        $this->post(route('logout'));
        $destination = url('/backoffice/project-management/tickets/'.$ticket->id);
        $this->get($destination)->assertRedirect(route('pms.login'));
        $this->post(route('pms.authenticate'), ['email' => 'qa@example.test', 'password' => 'StrongPass123'])->assertRedirect($destination);
        $this->get($destination)->assertRedirect(route('pms.tickets.show', $ticket));
        $this->get(route('pms.tickets.show', $ticket))->assertOk()->assertSee('Authorized ticket');
        $this->assertDatabaseHas('pm_project_members', ['project_id' => $project->id, 'user_id' => $staff->id, 'role' => 'Developer']);
        $project->members()->detach($staff->id);
        $this->get($destination)->assertNotFound();
    }
}
