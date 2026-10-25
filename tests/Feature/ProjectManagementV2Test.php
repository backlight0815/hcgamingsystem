<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmsProfile;
use App\Models\PmWorkItem;
use App\Models\ProjectManagementUser;
use App\Models\User;
use App\Support\FrontendExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectManagementV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function staff(int $role = 1): User
    {
        $user = User::factory()->create(['role_id' => $role, 'status' => 1]);
        if ($role !== 700) {
            PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(),
                'can_create_all_tickets' => in_array($role, [1, 2], true)]);
        }

        return $user;
    }

    private function project(User $admin, string $key = 'PMS'): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => $key, 'name' => 'PMS 2.0', 'priority' => 'P2'])->assertCreated();

        return PmProject::where('key', $key)->firstOrFail();
    }

    public function test_four_and_six_week_templates_create_valid_phase_schedules(): void
    {
        $admin = $this->staff();
        $project = $this->project($admin);

        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Four week calendar cycle', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated()->assertJsonPath('cycle_key', 'PMS-CYCLE-001');
        $four = PmCycle::where('cycle_key', 'PMS-CYCLE-001')->firstOrFail();
        $this->assertSame('2026-10-28', $four->target_end_date->toDateString());
        $this->assertSame([14, 7, 7], $four->phases->pluck('planned_start_date')->map(fn ($date, $index) => $four->phases[$index]->planned_start_date->diffInDays($four->phases[$index]->planned_end_date) + 1)->all());
        $this->actingAs($admin)->getJson(route('pms.cycles.show', $four))->assertOk()->assertJsonPath('analytics.basis', 'issue count');

        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Six week working cycle', 'week_mode' => 'working',
            'template_weeks' => 6, 'start_date' => '2026-11-02',
        ])->assertCreated()->assertJsonPath('cycle_key', 'PMS-CYCLE-002');
        $six = PmCycle::where('cycle_key', 'PMS-CYCLE-002')->firstOrFail();
        $this->assertSame('2026-12-11', $six->target_end_date->toDateString());
        $this->assertSame(['development', 'internal_testing', 'qat', 'buffer'], $six->phases->pluck('phase_key')->all());
        $this->assertSame('2026-11-13', $six->phases->first()->planned_end_date->toDateString());
    }

    public function test_impossible_cycle_schedule_is_rejected_without_partial_data(): void
    {
        $admin = $this->staff();
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Impossible', 'week_mode' => 'calendar',
            'start_date' => '2026-10-01', 'target_end_date' => '2026-10-28',
            'development_days' => 20, 'internal_testing_days' => 7, 'qat_days' => 7, 'buffer_days' => 0,
        ])->assertUnprocessable()->assertJsonValidationErrors('target_end_date');
        $this->assertDatabaseCount('pm_cycles', 0);
    }

    public function test_working_week_templates_follow_the_configured_weekend(): void
    {
        $admin = $this->staff();
        $project = $this->project($admin);
        DB::table('pms_workspace_settings')->update(['weekend_days' => json_encode([0]), 'holidays' => json_encode([])]);

        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Six day working week', 'week_mode' => 'working',
            'template_weeks' => 4, 'start_date' => '2026-10-05',
        ])->assertCreated();

        $cycle = PmCycle::firstOrFail();
        $this->assertSame('2026-10-31', $cycle->target_end_date->toDateString());
        $this->assertSame(12, $cycle->development_days);
        $this->assertSame(6, $cycle->internal_testing_days);
        $this->assertSame(6, $cycle->qat_days);
    }

    public function test_new_classifications_generate_stable_global_project_sequences_and_report_counts(): void
    {
        $admin = $this->staff();
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.issues.store'), [
            'project_id' => $project->id, 'classification_key' => 'FTR', 'summary' => 'Cycle planning', 'priority' => 'P2',
            'business_objective' => 'Plan reliable releases', 'acceptance_criteria' => 'Managers can create a cycle',
        ])->assertCreated()->assertJsonPath('issue_key', 'PMS-FTR-0001');
        $this->actingAs($admin)->postJson(route('pms.issues.store'), [
            'project_id' => $project->id, 'classification_key' => 'PDI', 'summary' => 'Production login failure', 'priority' => 'P0',
            'detected_at' => '2026-10-16 10:00:00', 'production_environment' => 'Production',
            'business_impact' => 'Staff cannot sign in', 'actual_result' => 'The login request returns 500',
            'fast_track' => true, 'fast_track_reason' => 'Critical production outage',
        ])->assertCreated()->assertJsonPath('issue_key', 'PMS-PDI-0002');
        $incident = PmWorkItem::where('classification_key', 'PDI')->firstOrFail();
        $this->assertSame($admin->id, $incident->fast_track_approved_by);
        $feature = PmWorkItem::where('classification_key', 'FTR')->firstOrFail();
        $feature->forceFill(['status' => 'QAT Done'])->save();
        $incident->forceFill(['status' => 'Production Done', 'completed_at' => now()])->save();
        DB::table('pm_issue_history')->insert([
            'issue_id' => $feature->id, 'actor_id' => $admin->id, 'event' => 'transition',
            'from_status' => 'QAT In Progress', 'to_status' => 'Reopen', 'reason' => 'Regression found', 'created_at' => now(),
        ]);
        $response = $this->actingAs($admin)->getJson(route('pms.dashboard', ['period' => 90]))
            ->assertOk()->assertJsonPath('classifications.FTR', 1)->assertJsonPath('classifications.PDI', 1)
            ->assertJsonPath('releaseReadiness.qat_done', 1)
            ->assertJsonPath('releaseReadiness.awaiting_production_evidence', 1)
            ->assertJsonStructure(['qaWorkload', 'flowTrend' => [['label', 'completed', 'reopened']]]);
        $currentFlow = collect($response->json('flowTrend'))->last();
        $this->assertSame(1, $currentFlow['completed']);
        $this->assertSame(1, $currentFlow['reopened']);
    }

    public function test_project_archive_trash_and_permanent_delete_are_distinct_and_typed(): void
    {
        $admin = $this->staff();
        $project = $this->project($admin, 'DEL');
        $this->actingAs($admin)->postJson(route('pms.projects.archive', $project))->assertOk();
        $this->assertNotNull($project->fresh()->archived_at);
        $this->assertNull($project->fresh()->deleted_at);
        $this->actingAs($admin)->deleteJson(route('pms.projects.trash', $project), ['confirmation' => 'WRONG'])->assertUnprocessable();
        $this->actingAs($admin)->deleteJson(route('pms.projects.trash', $project), ['confirmation' => 'DEL'])->assertOk();
        $this->actingAs($admin)->deleteJson(route('pms.projects.purge', $project->id), ['confirmation' => 'WRONG'])->assertUnprocessable();
        $this->actingAs($admin)->deleteJson(route('pms.projects.purge', $project->id), ['confirmation' => 'DEL'])->assertOk();
        $this->assertDatabaseMissing('pm_projects', ['id' => $project->id]);
        $this->assertDatabaseHas('pm_audit_logs', ['action' => 'project.deleted', 'subject_id' => $project->id]);
    }

    public function test_pms_only_registration_is_pending_and_assignment_requires_activation(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        FrontendExperience::forgetCache();
        $this->get(route('pms.register'))->assertOk()->assertSee('MEMBERSHIP REQUEST');
        $this->post(route('pms.register.store'), [
            'name' => 'New Engineer', 'email' => 'engineer@example.test', 'department' => 'Engineering',
            'job_title' => 'Developer', 'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertRedirect(route('pms.home'));
        $member = ProjectManagementUser::where('email', 'engineer@example.test')->firstOrFail()->user;
        $this->assertNull($member->role_id);
        $this->assertSame('pending', $member->pmsProfile->status);
        $this->get(route('pms.dashboard'))->assertForbidden();

        $admin = $this->staff();
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $member->id, 'role' => 'Developer'])->assertUnprocessable();
        $this->actingAs($admin)->putJson(route('pms.team.update', $member->pmsProfile), ['status' => 'active'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $member->id, 'role' => 'Developer'])->assertOk();
        $this->assertDatabaseHas('pm_project_members', ['project_id' => $project->id, 'user_id' => $member->id]);
    }

    public function test_combined_mode_keeps_private_pms_authentication_available(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => true]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => true]);
        FrontendExperience::forgetCache();
        $this->getJson(route('pms.home'))->assertRedirect(route('pms.login'));
        $this->getJson(route('pms.login'))->assertOk();
        $this->getJson(route('pms.register'))->assertOk();
    }

    public function test_pms_api_authentication_requires_active_membership_and_revokes_its_token(): void
    {
        $active = User::factory()->create(['role_id' => null]);
        PmsProfile::create(['user_id' => $active->id, 'status' => 'active', 'approved_at' => now()]);
        $pending = User::factory()->create(['role_id' => null]);
        PmsProfile::create(['user_id' => $pending->id, 'status' => 'pending']);

        $this->postJson(route('api.pms.auth.login'), ['email' => $pending->email, 'password' => 'password'])
            ->assertForbidden()->assertJsonPath('membership_status', 'pending');
        $login = $this->postJson(route('api.pms.auth.login'), [
            'email' => $active->email, 'password' => 'password', 'device_name' => 'PMS test client',
        ])->assertOk()->assertJsonPath('user.membership.status', 'active')->assertJsonStructure(['token', 'token_type', 'user']);

        $token = $login->json('token');
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson(route('api.pms.auth.profile'))
            ->assertOk()->assertJsonPath('user.email', $active->email);
        $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson(route('api.pms.auth.logout'))
            ->assertOk()->assertJsonPath('message', 'PMS API token revoked.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson(route('api.pms.auth.profile'))->assertUnauthorized();
    }

    public function test_pms_api_registration_honours_workspace_approval_policy(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        FrontendExperience::forgetCache();

        $this->postJson(route('api.pms.auth.register'), [
            'name' => 'API Engineer', 'email' => 'api-engineer@example.test', 'department' => 'Engineering',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123', 'device_name' => 'Mobile',
        ])->assertCreated()->assertJsonPath('membership_status', 'pending')->assertJsonMissingPath('token');
        $identity = ProjectManagementUser::where('email', 'api-engineer@example.test')->firstOrFail();
        $member = $identity->user;
        $this->assertDatabaseHas('pms_profiles', ['user_id' => $member->id, 'status' => 'pending']);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $admin = $this->staff();
        $this->actingAs($admin)->putJson(route('api.pms.memberships.update', $member->pmsProfile), ['status' => 'active'])
            ->assertOk()->assertJsonPath('status', 'active');
        $this->postJson(route('api.pms.auth.login'), ['email' => $identity->email, 'password' => 'StrongPass123'])
            ->assertOk()->assertJsonPath('user.membership.status', 'active')->assertJsonStructure(['token']);
    }

    public function test_cycle_carryover_moves_the_same_issue_and_private_screens_render(): void
    {
        $admin = $this->staff();
        $project = $this->project($admin);
        foreach ([['Cycle A', '2026-10-01'], ['Cycle B', '2026-11-01']] as [$name, $start]) {
            $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
                'project_id' => $project->id, 'name' => $name, 'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => $start,
            ])->assertCreated();
        }
        [$first, $second] = PmCycle::orderBy('id')->get();
        $this->actingAs($admin)->postJson(route('pms.issues.store'), [
            'project_id' => $project->id, 'classification_key' => 'FTR', 'summary' => 'Carry this scope', 'priority' => 'P2',
            'business_objective' => 'Retain issue identity', 'acceptance_criteria' => 'The same issue moves between cycles', 'cycle_id' => $first->id,
        ])->assertCreated();
        $issue = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.cycles.start', $first))->assertOk();
        $this->actingAs($admin)->postJson(route('pms.cycles.close', $first), [
            'unfinished_action' => 'carry', 'target_cycle_id' => $second->id, 'reason' => 'Approved carryover to the next planning window',
        ])->assertOk();
        $this->assertDatabaseCount('pm_work_items', 1);
        $this->assertSame($second->id, $issue->fresh()->cycle_id);
        $this->assertSame($first->id, $issue->fresh()->original_cycle_id);
        $this->assertSame(1, $issue->fresh()->carryover_count);

        $this->actingAs($admin)->get(route('pms.dashboard'))->assertOk()->assertSee('Delivery command center');
        $this->get(route('pms.reports'))->assertOk()->assertSee('Operational analytics');
        $this->get(route('pms.cycles.show', $first))->assertOk()->assertSee('Cycle health reasons');
        $this->get(route('pms.team.index'))->assertOk()->assertSee('Account Management');
        $this->get(route('pms.settings'))->assertOk()->assertSee('Workspace settings');
    }
}
