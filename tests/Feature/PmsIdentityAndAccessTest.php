<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\ProjectManagementUser;
use App\Models\User;
use App\Support\FrontendExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmsIdentityAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function staff(?string $role = null, bool $administrator = false, bool $grant = false): User
    {
        $user = User::factory()->create(['role_id' => $administrator ? 1 : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(),
            'job_position' => $role === 'QA' ? 'QA Engineer' : null,
            'workspace_role' => $role, 'can_create_all_tickets' => $grant]);

        return $user;
    }

    private function project(User $admin, string $key = 'APP'): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => $key, 'name' => $key.' workspace', 'priority' => 'P2',
        ])->assertCreated();

        return PmProject::where('key', $key)->firstOrFail();
    }

    private function payload(string $category, int $projectId): array
    {
        $common = ['classification_key' => $category, 'project_id' => $projectId,
            'summary' => $category.' example', 'priority' => 'P3'];
        return $common + match ($category) {
            'REQ' => ['description' => 'Requirement detail', 'business_justification' => 'Business need', 'acceptance_criteria' => 'Pass criteria'],
            'ENH' => ['current_behavior' => 'Current flow', 'proposed_behavior' => 'Better flow', 'acceptance_criteria' => 'Pass criteria'],
            'PRD' => ['detected_at' => now()->toDateTimeString(), 'business_impact' => 'Production affected',
                'production_environment' => 'Production', 'actual_result' => 'Failed'],
            'QAT' => ['defect_type' => 'Functional bug', 'reproduction_steps' => 'Open and submit',
                'expected_result' => 'Success', 'actual_result' => 'Failure', 'testing_environment' => 'Staging'],
        };
    }

    public function test_pms_registration_uses_its_own_email_and_password_scope(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        FrontendExperience::forgetCache();
        $otherModuleUser = User::factory()->create(['email' => 'shared@example.test']);

        $this->post(route('pms.register.store'), [
            'name' => 'PMS Member', 'email' => 'shared@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertRedirect(route('pms.home'));
        $identity = ProjectManagementUser::where('email', 'shared@example.test')->firstOrFail();
        $this->assertNotSame($otherModuleUser->id, $identity->user_id);
        $this->assertTrue(Hash::check('StrongPass123', $identity->password));
        $this->assertFalse(Hash::check('StrongPass123', $otherModuleUser->password));
        $this->assertNull($identity->user->role_id);
        $this->assertNull($identity->user->referral_code);
        $this->assertNull($identity->user->customer_referral_code);
        $this->assertNull($identity->user->signal_provider_referral_code);
        $this->assertSame('pending', $identity->user->pmsProfile->status);
        $this->post(route('pms.logout'))->assertRedirect(route('pms.login'));
        $this->post(route('pms.login'), ['email' => 'shared@example.test', 'password' => 'StrongPass123'])
            ->assertRedirect(route('pms.home'));
        $this->assertAuthenticatedAs($identity->user, 'pms');
        $this->post(route('pms.register.store'), [
            'name' => 'Duplicate', 'email' => 'SHARED@EXAMPLE.TEST',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertSessionHasErrors('email');
        $this->post(route('pms.register.store'), [
            'name' => 'Weak', 'email' => 'weak@example.test', 'password' => 'weak', 'password_confirmation' => 'weak',
        ])->assertSessionHasErrors('password');
    }

    public function test_registration_uses_active_master_data_and_keeps_title_independent(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        FrontendExperience::forgetCache();
        DB::table('pms_workspace_settings')->update(['registration_policy' => 'approval']);
        $development = DB::table('pms_departments')->where('code', 'DEV')->value('id');
        $quality = DB::table('pms_departments')->where('code', 'QA')->value('id');
        $position = DB::table('pms_job_positions')->where('code', 'DEV')->value('id');
        DB::table('pms_department_positions')->insert(['department_id' => $quality, 'position_id' => $position]);
        $this->get(route('pms.register'))->assertOk()
            ->assertSee('data-departments="'.$development.','.$quality.'"', false)
            ->assertSee('Search department')->assertSee('Search job position');
        $this->post(route('pms.register.store'), [
            'name' => 'Professional Member', 'email' => 'professional@example.test',
            'department_id' => $quality, 'job_position_id' => $position,
            'job_title' => 'Principal Platform Engineer',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertRedirect(route('pms.home'));
        $member = ProjectManagementUser::where('email', 'professional@example.test')->firstOrFail()->user;
        $this->assertDatabaseHas('pms_profiles', ['user_id' => $member->id,
            'department_id' => $quality, 'job_position_id' => $position,
            'job_title' => 'Principal Platform Engineer', 'job_position' => 'Developer']);
    }

    public function test_pms_password_reset_updates_its_own_credential(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        FrontendExperience::forgetCache();
        $user = $this->staff();
        $identity = ProjectManagementUser::create(['user_id' => $user->id, 'name' => $user->name,
            'email' => 'reset@example.test', 'password' => Hash::make('StrongPass123'), 'status' => 'active']);
        $token = Password::broker('project_management_users')->createToken($identity);
        $this->post(route('pms.password.update'), ['token' => $token, 'email' => $identity->email,
            'password' => 'NewStrongPass123', 'password_confirmation' => 'NewStrongPass123'])
            ->assertRedirect(route('pms.login'));
        $this->assertTrue(Hash::check('NewStrongPass123', $identity->fresh()->password));
        $this->assertFalse(Hash::check('NewStrongPass123', $user->fresh()->password));
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => true]);
        FrontendExperience::forgetCache();
        $combinedToken = Password::broker('project_management_users')->createToken($identity->fresh());
        $this->get(route('pms.password.reset', ['token' => $combinedToken, 'email' => $identity->email]))->assertOk();
        $this->post(route('pms.password.update'), ['token' => $combinedToken, 'email' => $identity->email,
            'password' => 'CombinedPass123', 'password_confirmation' => 'CombinedPass123'])
            ->assertRedirect(route('pms.login'));
    }

    public function test_known_legacy_shared_account_keeps_its_shared_password_login(): void
    {
        $user = $this->staff();
        $identity = ProjectManagementUser::create(['user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'password' => $user->password, 'status' => 'active', 'legacy_shared_auth' => true]);
        $user->forceFill(['password' => Hash::make('ChangedMainPass123')])->save();
        $this->postJson(route('api.pms.auth.login'), ['email' => $identity->email, 'password' => 'ChangedMainPass123'])
            ->assertOk();
        $this->assertTrue(Hash::check('ChangedMainPass123', $identity->fresh()->password));
        $this->postJson(route('api.pms.auth.login'), ['email' => $identity->email, 'password' => 'password'])
            ->assertUnprocessable();
    }

    public function test_shared_trading_and_dealership_registration_still_creates_referral_codes(): void
    {
        foreach ([350 => 'tradingmember', 700 => 'dealershipmember'] as $role => $username) {
            $email = $username.'@gmail.com';
            $this->post(route('register'), ['name' => ucfirst($username), 'username' => $username,
                'email' => $email, 'password' => 'StrongPass123',
                'password_confirmation' => 'StrongPass123', 'user_role' => $role])->assertRedirect();
            $user = User::where('email', $email)->firstOrFail();
            $this->assertSame($role, (int) $user->role_id);
            $this->assertNotNull($user->referral_code);
            $this->assertNull($user->projectManagementIdentity);
            $this->post(route('logout'))->assertRedirect();
        }
    }

    public function test_module_management_is_recoverable_in_every_toggle_combination(): void
    {
        $admin = $this->staff(administrator: true);
        $other = $this->staff();
        foreach ([false, true] as $trading) {
            foreach ([false, true] as $commerce) {
                foreach ([false, true] as $pms) {
                    foreach ([FeatureToggle::MODULE_TRADING => $trading,
                        FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE => $commerce,
                        FeatureToggle::MODULE_PROJECT_MANAGEMENT => $pms] as $key => $enabled) {
                        FeatureToggle::updateOrCreate(['feature_name' => $key], ['enabled' => $enabled]);
                    }
                    FrontendExperience::forgetCache();
                    $this->actingAs($admin)->get(route('admin.features.index'))->assertOk();
                    $this->actingAs($other)->get(route('admin.features.index'))->assertForbidden();
                }
            }
        }
        $tradingFeature = FeatureToggle::where('feature_name', FeatureToggle::MODULE_TRADING)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.features.update', $tradingFeature), ['enabled' => '1'])->assertRedirect();
        $this->assertTrue($tradingFeature->fresh()->enabled);
        $this->assertSame($admin->id, $tradingFeature->fresh()->updated_by);
        $this->actingAs($other)->post(route('admin.features.update', $tradingFeature), ['enabled' => '0'])->assertForbidden();
    }

    public function test_web_admin_can_log_in_and_reenable_a_module_when_all_are_off(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        foreach ([FeatureToggle::MODULE_TRADING, FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE,
            FeatureToggle::MODULE_PROJECT_MANAGEMENT] as $key) {
            FeatureToggle::updateOrCreate(['feature_name' => $key], ['enabled' => false]);
        }
        FrontendExperience::forgetCache();
        $this->get(route('login'))->assertOk();
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('admin.features.index'))->assertOk();
        $pmsFeature = FeatureToggle::where('feature_name', FeatureToggle::MODULE_PROJECT_MANAGEMENT)->firstOrFail();
        $this->post(route('admin.features.update', $pmsFeature), ['enabled' => '1'])->assertRedirect();
        $this->assertTrue($pmsFeature->fresh()->enabled);
    }

    public function test_pms_session_administrator_can_open_module_management(): void
    {
        $admin = $this->staff(administrator: true);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        FrontendExperience::forgetCache();
        Auth::guard('pms')->login($admin);
        $this->get(route('pms.dashboard'))->assertOk()->assertSee('Backoffice module management');
        $this->get(route('admin.features.index'))->assertOk();
    }

    public function test_ticket_creation_matrix_context_resolution_and_assigned_qa_estimate(): void
    {
        $admin = $this->staff(administrator: true);
        $ba = $this->staff('Business Analyst');
        $pm = $this->staff('Project Manager');
        $qa = $this->staff('QA');
        $developer = $this->staff();
        $developer->pmsProfile->update(['job_position' => 'Developer']);
        $viewer = $this->staff();
        $project = $this->project($admin);
        foreach ([$ba->id => 'Business Analyst', $pm->id => 'Project Manager', $qa->id => 'QA', $viewer->id => 'Viewer'] as $id => $role) {
            $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
                'user_id' => $id, 'role' => $role,
            ])->assertOk();
        }
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $developer->id, 'role' => 'Developer',
        ])->assertOk();
        foreach ([$ba, $pm] as $actor) {
            foreach (['REQ', 'ENH', 'PRD', 'QAT'] as $category) {
                $this->actingAs($actor)->postJson(route('pms.tickets.store'), $this->payload($category, $project->id))->assertCreated();
            }
        }
        foreach (['REQ', 'ENH', 'PRD'] as $category) {
            $this->actingAs($ba)->postJson(route('pms.projects.tickets.store', [$project, $category]),
                $this->payload($category, $project->id))->assertCreated()
                ->assertJsonPath('classification_key', $category)->assertJsonPath('project_id', $project->id);
        }
        $this->actingAs($qa)->get(route('pms.projects.tickets.create', [$project, 'QAT']))
            ->assertOk()->assertSee('Related project')->assertSee($project->name);
        $this->actingAs($qa)->get(route('pms.projects.tickets.create', [$project, 'REQ']))->assertForbidden();
        $otherProject = $this->project($admin, 'OTH');
        $this->actingAs($qa)->postJson(route('pms.projects.tickets.store', [$project, 'QAT']),
            array_merge($this->payload('QAT', $otherProject->id), ['classification_key' => 'REQ', 'developer_id' => $developer->id]))->assertCreated()->assertJsonPath('project_id', $project->id)
            ->assertJsonPath('classification_key', 'QAT')->assertJsonPath('status', 'Open')->assertJsonPath('qat_resolution', null);
        $ticket = PmWorkItem::latest('id')->firstOrFail();
        $this->assertNull($ticket->due_date);
        foreach (['REQ', 'ENH', 'PRD'] as $category) {
            $this->actingAs($qa)->postJson(route('pms.tickets.store'), $this->payload($category, $project->id))->assertForbidden();
        }
        $this->actingAs($viewer)->postJson(route('pms.tickets.store'), $this->payload('QAT', $project->id))->assertForbidden();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->payload('QAT', $project->id))->assertCreated();
        $this->actingAs($admin)->putJson(route('pms.team.update', $qa->pmsProfile), [
            'status' => 'active', 'workspace_role' => 'Business Analyst',
        ])->assertOk();
        $this->actingAs($qa)->postJson(route('pms.tickets.store'), $this->payload('REQ', $project->id))->assertCreated();
        $this->actingAs($admin)->putJson(route('pms.team.update', $qa->pmsProfile), [
            'status' => 'active', 'workspace_role' => 'QA',
        ])->assertOk();
        $this->actingAs($qa)->postJson(route('pms.tickets.store'), $this->payload('REQ', $project->id))->assertForbidden();
        $this->actingAs($admin)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'qa_id' => $qa->id,
        ])->assertOk();
        $this->actingAs($qa)->putJson(route('pms.tickets.qa-estimate', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'qa_estimated_completion_at' => '2026-10-20T17:00',
        ])->assertOk();
        $this->assertNotNull($ticket->fresh()->qa_estimated_completion_at);
        $this->assertNull($ticket->fresh()->due_date);
        $this->actingAs($qa)->putJson(route('pms.tickets.qat-resolution', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'qat_resolution' => 'Environment Issue',
        ])->assertOk()->assertJsonPath('qat_resolution', 'Environment Issue');
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'qa_estimate_updated']);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'resolution_updated']);
        $this->assertDatabaseHas('pm_work_items', ['id' => $ticket->id, 'project_id' => $project->id]);
        Sanctum::actingAs($qa, ['pms']);
        $this->postJson(route('api.pms.tickets.store'), $this->payload('REQ', $project->id))->assertForbidden();
        $this->postJson(route('api.pms.tickets.store'), $this->payload('ENH', $project->id))->assertForbidden();
        $this->postJson(route('api.pms.tickets.store'), $this->payload('PRD', $project->id))->assertForbidden();
    }

    public function test_qat_verification_reopen_and_reassignment_keep_estimate_with_current_qa(): void
    {
        $admin = $this->staff(administrator: true, grant: true);
        $developer = $this->staff();
        $developer->pmsProfile->update(['job_position' => 'Developer']);
        $qa = $this->staff('QA');
        $nextQa = $this->staff('QA');
        $project = $this->project($admin);
        foreach ([$developer->id => 'Developer', $qa->id => 'QA', $nextQa->id => 'QA'] as $id => $role) {
            $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
                'user_id' => $id, 'role' => $role,
            ])->assertOk();
        }
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->payload('QAT', $project->id) + [
            'developer_id' => $developer->id, 'qa_id' => $qa->id,
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($qa)->putJson(route('pms.tickets.qa-estimate', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'qa_estimated_completion_at' => '2026-10-20T17:00',
        ])->assertOk();
        $this->actingAs($admin)->putJson(route('pms.tickets.qat-resolution', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'qat_resolution' => 'Fixed',
        ])->assertUnprocessable();
        foreach (['In Progress', 'Code Review', 'Ready for QAT', 'QAT in Progress'] as $status) {
            $this->actingAs($admin)->postJson(route('pms.tickets.transition', $ticket), [
                'status' => $status, 'lock_version' => $ticket->fresh()->lock_version,
            ])->assertOk();
        }
        $this->actingAs($developer)->postJson(route('pms.tickets.transition', $ticket), [
            'status' => 'QAT Done', 'lock_version' => $ticket->fresh()->lock_version,
        ])->assertForbidden();
        $this->actingAs($qa)->postJson(route('pms.tickets.verify', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'result' => 'Failed',
            'build' => '2.1.0', 'environment' => 'Staging', 'remarks' => 'Regression persists.',
        ])->assertOk()->assertJsonPath('status', 'Reopen');
        $this->assertSame(1, $ticket->fresh()->reopen_count);
        $this->actingAs($admin)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'qa_id' => $nextQa->id,
        ])->assertOk();
        $this->assertNull($ticket->fresh()->qa_estimated_completion_at);
        $this->assertNull($ticket->fresh()->qa_estimated_by);
        $this->actingAs($qa)->putJson(route('pms.tickets.qa-estimate', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'qa_estimated_completion_at' => '2026-10-21T12:00',
        ])->assertForbidden();
        $this->actingAs($nextQa)->putJson(route('pms.tickets.qa-estimate', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'qa_estimated_completion_at' => '2026-10-21T12:00',
        ])->assertOk();
        $this->assertSame($nextQa->id, (int) $ticket->fresh()->qa_estimated_by);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'transition', 'to_status' => 'Reopen']);
    }
}
