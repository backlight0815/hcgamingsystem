<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmsProfile;
use App\Models\ProjectManagementUser;
use App\Models\TradingBlog;
use App\Models\User;
use App\Support\FrontendExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GlobalSuperAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role_id' => User::ROLE_SUPER_ADMIN, 'status' => 1]);
    }

    public function test_primary_login_opens_pms_without_registration_and_survives_module_switch_and_refresh(): void
    {
        $admin = $this->superAdmin();
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertNull($admin->pmsProfile);
        $this->assertNull($admin->projectManagementIdentity);

        $this->get(route('pms.dashboard'))->assertOk()->assertSee('Account Management')->assertSee('Settings');
        $this->get(route('admin.features.index'))->assertOk();
        $this->get(route('admin.system_audit'))->assertOk()->assertSee('auth.login');
        $this->get(route('pms.settings'))->assertOk();
        $this->get(route('pms.team.index'))->assertOk();
        $this->get(route('pms.dashboard'))->assertOk();
        $this->post(route('pms.logout'))->assertRedirect();
        $this->assertGuest('web');
        $this->assertGuest('pms');
        $this->get(route('pms.dashboard'))->assertRedirect(route('pms.login'));
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('pms.dashboard'))->assertOk();
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $admin->id, 'action' => 'auth.login']);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $admin->id, 'action' => 'auth.logout']);
    }

    public function test_pms_login_uses_global_credential_even_when_another_pms_identity_has_same_email(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        FrontendExperience::forgetCache();
        $admin = $this->superAdmin();
        $other = User::factory()->create(['role_id' => null, 'status' => 1]);
        ProjectManagementUser::create(['user_id' => $other->id, 'name' => $other->name,
            'email' => $admin->email, 'password' => Hash::make('OtherPassword123'), 'status' => 'active']);
        PmsProfile::create(['user_id' => $other->id, 'status' => 'active', 'approved_at' => now()]);

        $this->post(route('pms.authenticate'), ['email' => $admin->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');
        $this->post(route('pms.authenticate'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('pms.dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get(route('pms.dashboard'))->assertOk();
        $this->get(route('admin.features.index'))->assertOk();
    }

    public function test_super_admin_can_write_all_core_ticket_types_without_a_pms_profile_via_web_and_api(): void
    {
        $admin = $this->superAdmin();
        $project = $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'CORE', 'name' => 'Core delivery', 'priority' => 'P2',
        ])->assertCreated();
        $projectId = $project->json('id');
        $this->assertNotNull($projectId);

        foreach (['REQ', 'ENH', 'PRD', 'QAT'] as $category) {
            $payload = ['project_id' => $projectId, 'classification_key' => $category,
                'summary' => $category.' super admin', 'priority' => 'P3'] + match ($category) {
                    'REQ' => ['description' => 'Requirement', 'business_justification' => 'Needed', 'acceptance_criteria' => 'Accepted'],
                    'ENH' => ['current_behavior' => 'Current', 'proposed_behavior' => 'Proposed', 'acceptance_criteria' => 'Accepted'],
                    'PRD' => ['detected_at' => now()->toDateTimeString(), 'business_impact' => 'Affected',
                        'production_environment' => 'Production', 'actual_result' => 'Failed'],
                    'QAT' => ['defect_type' => 'Functional bug', 'reproduction_steps' => 'Open the screen',
                        'expected_result' => 'Success', 'actual_result' => 'Failure', 'testing_environment' => 'Staging'],
                };
            $this->postJson(route('pms.tickets.store'), $payload)->assertCreated();
        }
        $this->get(route('pms.tickets.create'))->assertOk()->assertSee('New Requirement');
        $this->get(route('pms.projects.index'))->assertOk();
        $this->get(route('pms.releases.index'))->assertOk();
        $this->get(route('pms.global-cycles.index'))->assertOk();

        $this->post(route('logout'))->assertRedirect();
        $this->postJson(route('api.pms.projects.store'), ['key' => 'API', 'name' => 'API delivery', 'priority' => 'P2'])
            ->assertUnauthorized();
        $token = $this->postJson(route('api.pms.auth.login'), ['email' => $admin->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.workspace_role', 'System Administrator')->json('token');
        $this->withToken($token)->postJson(route('api.pms.projects.store'),
            ['key' => 'API', 'name' => 'API delivery', 'priority' => 'P2'])->assertCreated();
        $this->assertSame(2, PmProject::count());
        $this->withToken($token)->deleteJson(route('api.pms.auth.logout'))->assertOk();
        $this->assertNull(\Laravel\Sanctum\PersonalAccessToken::findToken($token));
        Auth::forgetGuards();
        $this->withToken($token)->getJson(route('api.pms.auth.profile'))->assertUnauthorized();
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $admin->id, 'action' => 'auth.logout']);
    }

    public function test_module_disable_is_recoverable_and_does_not_grant_normal_users_admin_access(): void
    {
        $admin = $this->superAdmin();
        $member = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $member->id, 'status' => 'active', 'approved_at' => now()]);
        $feature = FeatureToggle::where('feature_name', FeatureToggle::MODULE_PROJECT_MANAGEMENT)->firstOrFail();

        $this->actingAs($admin)->post(route('admin.features.update', $feature), ['enabled' => '0'])->assertRedirect();
        $this->get(route('admin.features.index'))->assertOk();
        $this->get(route('admin.system_audit'))->assertOk();
        $this->getJson(route('pms.dashboard'))->assertNotFound();
        $this->actingAs($member)->getJson(route('pms.dashboard'))->assertNotFound();
        $this->getJson(route('admin.features.index'))->assertForbidden();
        $tradingFeature = FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FrontendExperience::forgetCache();
        $this->getJson('/api/mobile/v1/auth/roles')->assertNotFound();
        $this->actingAs($admin)->getJson(route('all.traders.account'))->assertNotFound();
        $commerceFeature = FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        FrontendExperience::forgetCache();
        $this->getJson(route('all.agent.account'))->assertNotFound();
        $this->get(route('admin.features.index'))->assertOk();
        $this->get(route('admin.system_audit'))->assertOk();
        $this->post(route('admin.features.update', $tradingFeature), ['enabled' => '1'])->assertRedirect();
        $this->post(route('admin.features.update', $commerceFeature), ['enabled' => '1'])->assertRedirect();
        $this->getJson('/api/mobile/v1/auth/roles')->assertOk();
        $this->actingAs($admin)->post(route('admin.features.update', $feature), ['enabled' => '1'])->assertRedirect();
        $this->get(route('pms.dashboard'))->assertOk();
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $admin->id, 'action' => 'module.disabled']);
        $this->assertDatabaseHas('admin_audit_logs', ['actor_id' => $admin->id, 'action' => 'module.enabled']);
    }

    public function test_direct_account_routes_reject_self_elevation_and_role_state_is_not_cached(): void
    {
        $admin = $this->superAdmin();
        $companyAdmin = User::factory()->create(['role_id' => User::ROLE_COMPANY_ADMIN, 'status' => 1]);
        $member = User::factory()->create(['role_id' => null, 'status' => 1]);
        $this->post(route('update.account'), ['id' => $member->id, 'account_role' => User::ROLE_SUPER_ADMIN])
            ->assertRedirect(route('login'));
        $this->actingAs($member)->post(route('update.account'), ['id' => $member->id, 'account_role' => User::ROLE_SUPER_ADMIN])
            ->assertForbidden();
        $this->postJson(route('store.role'), ['id' => 9001, 'name' => 'Unauthorized'])->assertForbidden();
        $this->actingAs($companyAdmin)->post(route('update.account'), ['id' => $companyAdmin->id,
            'account_role' => User::ROLE_SUPER_ADMIN])->assertForbidden();
        $this->getJson(route('admin.system_audit'))->assertForbidden();
        $this->post(route('update.account'), ['id' => $admin->id, 'account_role' => User::ROLE_COMPANY_ADMIN])
            ->assertForbidden();
        $this->assertSame(User::ROLE_SUPER_ADMIN, (int) $admin->fresh()->role_id);

        config()->set('trading_blogs.roles.create', []);
        $this->assertTrue(Gate::forUser($admin)->allows('create', TradingBlog::class));
        $this->assertFalse(Gate::forUser($member)->allows('create', TradingBlog::class));
        $this->actingAs($admin)->get(route('admin.features.index'))->assertOk();
        $this->post(route('store.role'), ['id' => 9001, 'name' => 'Temporary reviewer'])->assertRedirect(route('all.roles'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.created', 'target_id' => 9001]);
        $this->delete(route('delete.role', 9001))->assertRedirect(route('all.roles'));
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'role.deleted', 'target_id' => 9001]);
        $admin->role_id = null;
        $admin->save();
        $this->get(route('admin.features.index'))->assertForbidden();
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'user.role_changed', 'target_id' => $admin->id]);
    }
}
