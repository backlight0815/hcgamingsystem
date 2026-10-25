<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\ProjectManagementUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsRequestedEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role_id' => User::ROLE_SUPER_ADMIN, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function project(User $admin, string $key = 'PMS'): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => $key, 'name' => $key.' project', 'priority' => 'P3',
        ])->assertCreated();
        return PmProject::where('key', $key)->firstOrFail();
    }

    private function requirement(User $admin, PmProject $project, array $extra = []): PmWorkItem
    {
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $extra + [
            'classification_key' => 'REQ', 'project_id' => $project->id,
            'summary' => 'Project requirement', 'priority' => 'Normal',
            'description' => 'A test requirement.', 'business_justification' => 'Needed for delivery.',
            'acceptance_criteria' => 'Reviewed and accepted.',
        ])->assertCreated();

        return PmWorkItem::latest('id')->firstOrFail();
    }

    public function test_open_invitation_accepts_own_email_once_and_grants_only_the_selected_project(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin);
        $other = $this->project($admin, 'OTHER');
        $this->actingAs($admin)->postJson(route('pms.invitations.store'), [
            'role' => 'QA', 'expires_at' => now()->addDay()->toDateTimeString(), 'max_uses' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $response = $this->actingAs($admin)->postJson(route('pms.invitations.store'), [
            'role' => 'QA', 'project_id' => $project->id,
            'expires_at' => now()->addDay()->toDateTimeString(), 'max_uses' => 1,
        ])->assertCreated()->assertJsonPath('email', null);
        $token = basename(parse_url($response->json('link'), PHP_URL_PATH));
        $this->assertDatabaseHas('pms_invitations', ['email' => null, 'email_restricted' => 0]);
        Auth::guard('web')->logout();
        $this->post(route('pms.register.store'), [
            'invite' => $token, 'name' => 'Own Email QA', 'email' => 'own.qa@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            'project_id' => $other->id, 'role' => 'Project Manager',
        ])->assertRedirect(route('pms.dashboard'));
        $identity = ProjectManagementUser::where('email', 'own.qa@example.test')->firstOrFail();
        $this->assertDatabaseHas('pm_project_members', ['user_id' => $identity->user_id, 'project_id' => $project->id, 'role' => 'QA']);
        $this->assertDatabaseMissing('pm_project_members', ['user_id' => $identity->user_id, 'project_id' => $other->id]);
        $this->get(route('pms.register', ['invite' => $token]))->assertStatus(410);
    }

    public function test_reference_urls_redirect_only_after_authorization_and_priority_is_outside_header(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin);
        $ticket = $this->requirement($admin, $project);
        $this->assertSame('sfreq0001', $ticket->reference);
        $canonical = route('pms.tickets.show', $ticket);
        $this->assertStringEndsWith('/sfreq0001', $canonical);
        $this->actingAs($admin)->get(route('pms.tickets.show', $ticket->id))->assertRedirect($canonical);
        $this->actingAs($admin)->get($canonical)->assertOk()->assertSee('sfreq0001')
            ->assertDontSee('Target completion');

        $outsider = User::factory()->create(['status' => 1]);
        PmsProfile::create(['user_id' => $outsider->id, 'status' => 'active']);
        $this->actingAs($outsider)->getJson(route('pms.tickets.show', $ticket->id))->assertNotFound();
        $this->actingAs($outsider)->getJson($canonical)->assertNotFound();
    }

    public function test_existing_requirement_intake_without_a_project_still_works(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('pms.tickets.create'))->assertOk()
            ->assertSee('Unassigned intake')->assertDontSee('A project assignment is required');
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'summary' => 'Unassigned intake', 'priority' => 'P3',
            'description' => 'Review this request.', 'business_justification' => 'Needed by operations.',
            'acceptance_criteria' => 'A project manager can triage it.',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->assertNull($ticket->project_id);
        $this->assertSame('sfreq0001', $ticket->reference);
        $this->assertSame('P3', $ticket->priority);
    }

    public function test_invited_single_project_locks_selector_and_membership_change_unlocks_it(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin);
        $second = $this->project($admin, 'SECOND');
        $response = $this->actingAs($admin)->postJson(route('pms.invitations.store'), [
            'role' => 'QA', 'project_id' => $project->id,
            'expires_at' => now()->addDay()->toDateTimeString(), 'max_uses' => 1,
        ])->assertCreated();
        Auth::guard('web')->logout();
        $token = basename(parse_url($response->json('link'), PHP_URL_PATH));
        $this->post(route('pms.register.store'), [
            'invite' => $token, 'name' => 'Invited QA', 'email' => 'invited.qa@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertRedirect(route('pms.dashboard'));
        $qa = User::findOrFail(ProjectManagementUser::where('email', 'invited.qa@example.test')->value('user_id'));
        $this->actingAs($qa)->get(route('pms.tickets.create'))->assertOk()
            ->assertSee('disabled aria-describedby="pm-project-lock"', false);
        $this->actingAs($qa)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'project_id' => $second->id,
            'summary' => 'Unauthorized defect', 'priority' => 'Normal',
            'reproduction_steps' => 'Open the page.', 'expected_result' => 'Loads.',
            'actual_result' => 'Fails.', 'testing_environment' => 'Staging',
            'defect_type' => DB::table('pm_defect_types')->value('name'),
        ])->assertForbidden();
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $second), [
            'user_id' => $qa->id, 'role' => 'QA',
        ])->assertOk();
        $this->actingAs($qa)->get(route('pms.tickets.create'))->assertOk()
            ->assertDontSee('disabled aria-describedby="pm-project-lock"', false);
    }

    public function test_suspension_rejects_access_and_reactivation_preserves_account(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['status' => 1]);
        $profile = PmsProfile::create(['user_id' => $member->id, 'status' => 'active']);
        ProjectManagementUser::create([
            'user_id' => $member->id, 'name' => $member->name, 'email' => 'member@example.test',
            'password' => $member->password, 'status' => 'active', 'legacy_shared_auth' => true,
        ]);
        $member->createToken('pms-workspace', ['pms']);
        $this->actingAs($admin)->putJson(route('pms.team.update', $profile), ['status' => 'suspended'])->assertOk();
        $this->assertDatabaseHas('pms_profiles', ['id' => $profile->id, 'status' => 'suspended']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->actingAs($member)->get(route('pms.dashboard'))->assertForbidden();
        $this->actingAs($admin)->putJson(route('pms.team.update', $profile), ['status' => 'active'])->assertOk();
        $this->actingAs($member)->get(route('pms.dashboard'))->assertOk();
    }

    public function test_account_status_requires_super_admin_and_protects_the_last_one(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['status' => 1]);
        $profile = PmsProfile::create(['user_id' => $member->id, 'status' => 'active']);
        $this->actingAs($member)->putJson(route('pms.team.update', $profile), [
            'status' => 'suspended',
        ])->assertForbidden();
        $this->actingAs($admin)->putJson(route('pms.team.update', $admin->pmsProfile), [
            'status' => 'inactive',
        ])->assertUnprocessable();
        $this->assertSame('active', $admin->pmsProfile->fresh()->status);
        $secondAdmin = $this->admin();
        $this->actingAs($admin)->putJson(route('pms.team.update', $secondAdmin->pmsProfile), [
            'status' => 'suspended',
        ])->assertOk();
        $this->actingAs($secondAdmin)->get(route('pms.dashboard'))->assertForbidden();
    }

    public function test_normal_qa_with_one_project_has_locked_selector_and_zero_membership_blocks_creation(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin);
        $normal = User::factory()->create(['status' => 1]);
        PmsProfile::create(['user_id' => $normal->id, 'status' => 'active', 'workspace_role' => 'QA']);
        DB::table('pms_registrations')->insert([
            'user_id' => $normal->id, 'source' => 'direct', 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($normal)->get(route('pms.tickets.create'))->assertOk()
            ->assertSee('A project assignment is required');
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $normal->id, 'role' => 'QA',
        ])->assertOk();
        $this->actingAs($normal)->get(route('pms.tickets.create'))->assertOk()
            ->assertSee('value="'.$project->id.'" selected', false)
            ->assertSee('disabled aria-describedby="pm-project-lock"', false)
            ->assertSee('Automatically assigned from your single eligible project.');
    }

    public function test_cycle_status_is_audited_and_does_not_change_ticket_status(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Delivery cycle',
            'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-11-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $ticket = $this->requirement($admin, $project, ['cycle_id' => $cycle->id]);
        $originalStatus = $ticket->status;
        $this->actingAs($admin)->postJson(route('pms.cycles.status', $cycle), [
            'status' => 'Active', 'lock_version' => $cycle->lock_version,
        ])->assertOk();
        $cycle->refresh();
        $this->actingAs($admin)->postJson(route('pms.cycles.status', $cycle), [
            'status' => 'Closed', 'lock_version' => $cycle->lock_version, 'reason' => 'Done',
        ])->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('pms.cycles.status', $cycle), [
            'status' => 'Internal Testing', 'lock_version' => $cycle->lock_version,
        ])->assertOk();
        $this->assertSame($originalStatus, $ticket->fresh()->status);
        $this->assertDatabaseHas('pm_cycle_history', ['cycle_id' => $cycle->id, 'event' => 'status_changed']);
        $this->actingAs($admin)->get(route('pms.cycles.show', $cycle))->assertOk()
            ->assertSee('Unresolved tickets:')->assertSee($admin->name);
    }
}
