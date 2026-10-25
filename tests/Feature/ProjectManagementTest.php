<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmsProfile;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\FrontendExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function enabled(bool $value = true): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => $value]);
    }

    private function staff(int $role = 1): User
    {
        $user = User::factory()->create(['role_id' => $role, 'status' => 1]);
        if ($role !== 700) {
            PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(),
                'job_position' => match ($role) { 900 => 'Developer', 901 => 'QA Engineer', default => null },
                'can_create_all_tickets' => in_array($role, [1, 2], true)]);
        }

        return $user;
    }

    private function project(User $admin, string $key = 'CPR'): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => $key, 'name' => 'Customer Portal Revamp', 'priority' => 'P2'])->assertCreated();

        return PmProject::where('key', $key)->firstOrFail();
    }

    public function test_disabled_and_customer_access_are_blocked(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin)->getJson(route('pms.dashboard'))->assertNotFound();
        $this->enabled();
        $this->actingAs($this->staff(700))->getJson(route('pms.dashboard'))->assertForbidden();
        $this->actingAs($admin)->get(route('pms.dashboard'))->assertOk();
        $this->enabled(false);
        $this->actingAs($admin)->getJson(route('pms.dashboard'))->assertNotFound();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => 'NO', 'name' => 'No', 'priority' => 'P2'])->assertNotFound();
    }

    public function test_project_and_issue_crud_with_membership_and_unique_keys(): void
    {
        $this->enabled();
        $admin = $this->staff();
        $developer = $this->staff(900);
        $outsider = $this->staff(901);
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => 'CPR', 'name' => 'Duplicate', 'priority' => 'P2'])->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $developer->id, 'role' => 'Developer'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.issues.store'), ['project_id' => $project->id, 'summary' => 'Fix login validation', 'type' => 'Bug', 'priority' => 'P1', 'developer_id' => $developer->id])->assertCreated();
        $issue = PmWorkItem::firstOrFail();
        $this->assertSame('CPR-PDI-0001', $issue->issue_key);
        $this->actingAs($developer)->get(route('pms.issues.show', $issue))->assertOk();
        $this->actingAs($outsider)->getJson(route('pms.issues.show', $issue))->assertNotFound();
        $this->actingAs($outsider)->putJson(route('pms.issues.update', $issue), ['summary' => 'Tamper', 'lock_version' => 0])->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('pms.issues.destroy', $issue), ['confirmation' => $issue->reference])->assertOk();
        $this->assertSoftDeleted('pm_work_items', ['id' => $issue->id]);
        $this->actingAs($admin)->get(route('pms.issues.index', ['archived' => 1]))->assertOk()->assertSee('Restore '.$issue->reference);
        $this->actingAs($admin)->postJson(route('pms.issues.restore', $issue->id))->assertOk();
        $this->actingAs($admin)->postJson(route('pms.projects.archive', $project))->assertOk();
        $this->assertDatabaseHas('pm_projects', ['id' => $project->id]);
        $this->assertNotNull($project->fresh()->archived_at);
        $this->actingAs($admin)->postJson(route('pms.projects.unarchive', $project))->assertOk();
        $this->actingAs($admin)->deleteJson(route('pms.projects.trash', $project), ['confirmation' => 'CPR'])->assertOk();
        $this->assertSoftDeleted('pm_projects', ['id' => $project->id]);
        $this->actingAs($admin)->postJson(route('pms.projects.restore', $project->id))->assertOk();
        $this->actingAs($admin)->deleteJson(route('pms.projects.trash', $project), ['confirmation' => 'CPR'])->assertOk();
        $this->actingAs($admin)->deleteJson(route('pms.projects.purge', $project->id), ['confirmation' => 'CPR'])->assertOk();
        $this->assertDatabaseMissing('pm_projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('pm_work_items', ['id' => $issue->id]);
    }

    public function test_workflow_requires_evidence_and_records_reopen_and_dependency_history(): void
    {
        $this->enabled();
        $admin = $this->staff();
        $dev = $this->staff(900);
        $qa = $this->staff(901);
        $release = $this->staff(902);
        $project = $this->project($admin);
        foreach ([[$dev, 'Developer'], [$qa, 'QA Engineer'], [$release, 'Release Manager']] as [$person, $role]) {
            $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $person->id, 'role' => $role])->assertOk();
        }
        $this->actingAs($admin)->postJson(route('pms.issues.store'), ['project_id' => $project->id, 'summary' => 'Fix login validation', 'type' => 'Bug', 'priority' => 'P1', 'developer_id' => $dev->id, 'qa_id' => $qa->id])->assertCreated();
        $issue = PmWorkItem::firstOrFail();
        $this->assertDatabaseHas('app_notifications', ['user_id' => $dev->id, 'title' => 'Assigned to '.$issue->reference, 'type' => 'project_management']);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $qa->id, 'title' => 'Assigned to '.$issue->reference, 'type' => 'project_management']);
        $move = function (User $actor, string $status, array $extra = []) use ($issue): void {
            $this->actingAs($actor)->postJson(route('pms.issues.transition', $issue), array_merge(['status' => $status, 'lock_version' => $issue->fresh()->lock_version], $extra))->assertOk();
        };
        $move($admin, 'Open');
        $move($dev, 'Development In Progress');
        $move($dev, 'Deployment');
        $this->actingAs($qa)->postJson(route('pms.issues.transition', $issue), ['status' => 'QAT In Progress', 'lock_version' => $issue->fresh()->lock_version])->assertUnprocessable();
        $this->actingAs($dev)->putJson(route('pms.issues.update', $issue), ['lock_version' => $issue->fresh()->lock_version, 'qat_build' => 'build-1', 'qat_deployed_at' => now()->toDateTimeString(), 'qat_deployment_evidence' => 'Successful QAT deployment log'])->assertOk();
        $move($qa, 'QAT In Progress');
        $this->actingAs($qa)->postJson(route('pms.issues.transition', $issue), ['status' => 'Reopen', 'lock_version' => $issue->fresh()->lock_version])->assertUnprocessable();
        $move($qa, 'QAT Dependency', ['reason' => 'Missing test data', 'dependency_owner_id' => $dev->id]);
        $move($qa, 'QAT In Progress');
        $move($qa, 'Reopen', ['reason' => 'Login still fails']);
        $this->assertSame(1, $issue->fresh()->reopen_count);
        $this->assertDatabaseHas('app_notifications', ['user_id' => $dev->id, 'title' => $issue->reference.' reopened by QA']);
        $move($dev, 'Development In Progress');
        $move($dev, 'Deployment');
        $move($qa, 'QAT In Progress');
        $this->actingAs($qa)->postJson(route('pms.issues.transition', $issue), ['status' => 'QAT Done', 'lock_version' => $issue->fresh()->lock_version])->assertUnprocessable();
        $this->actingAs($qa)->putJson(route('pms.issues.update', $issue), ['lock_version' => $issue->fresh()->lock_version, 'qa_result' => 'Passed regression suite'])->assertOk();
        $move($qa, 'QAT Done');
        $this->actingAs($release)->postJson(route('pms.issues.transition', $issue), ['status' => 'Production Done', 'lock_version' => $issue->fresh()->lock_version])->assertUnprocessable();
        $this->actingAs($admin)->putJson(route('pms.issues.update', $issue), ['lock_version' => $issue->fresh()->lock_version, 'production_build' => 'release-1', 'production_deployed_at' => now()->toDateTimeString(), 'production_evidence' => 'Healthy production checks'])->assertOk();
        $move($release, 'Production Done');
        $this->assertSame('Production Done', $issue->fresh()->status);
        $this->assertSame(12, \Illuminate\Support\Facades\DB::table('pm_issue_history')->where('issue_id', $issue->id)->where('event', 'transition')->count());
    }

    public function test_pms_only_landing_and_combined_storefront_isolation(): void
    {
        $this->enabled();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => false]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => false]);
        $this->get('/')->assertOk();
        $this->get(route('pms.home'))->assertRedirect(route('pms.login'));
        $this->get(route('pms.login'))->assertOk()->assertSee('Project Management');
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => true]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => true]);
        $this->assertSame(FrontendExperience::ECOMMERCE, FrontendExperience::homepage());
    }

    public function test_private_screens_render_and_use_filtered_records(): void
    {
        $this->enabled();
        $admin = $this->staff();
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.issues.store'), ['project_id' => $project->id, 'summary' => 'Visible issue', 'type' => 'Task', 'priority' => 'P3'])->assertCreated();
        $issue = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->get(route('pms.projects.index'))->assertOk()->assertSee('Customer Portal Revamp');
        $this->get(route('pms.projects.show', $project))->assertOk()->assertSee('Visible issue');
        $this->get(route('pms.issues.index'))->assertOk()->assertSee($issue->reference);
        $this->get(route('pms.issues.show', $issue))->assertOk()->assertSee('Status history');
        $this->get(route('pms.board', ['project' => $project->id]))->assertOk()->assertSee('Visible issue');
        $this->get(route('pms.audit'))->assertOk()->assertSee('issue.created');
    }

    public function test_ticket_directory_filters_and_csv_share_live_ticket_scope(): void
    {
        $this->enabled();
        $admin = $this->staff();
        $project = $this->project($admin);
        foreach (['Recent critical', 'Recent low', 'Old assigned'] as $summary) {
            $this->actingAs($admin)->postJson(route('pms.issues.store'), [
                'project_id' => $project->id, 'summary' => $summary, 'type' => 'Task', 'priority' => 'P3',
            ])->assertCreated();
        }
        DB::table('pm_work_items')->where('summary', 'Recent critical')->update(['priority' => 'P1']);
        DB::table('pm_work_items')->where('summary', 'Recent low')->update(['priority' => 'P4']);
        DB::table('pm_work_items')->where('summary', 'Old assigned')->update([
            'priority' => 'P2', 'developer_id' => $admin->id, 'updated_at' => now()->subDays(40),
        ]);

        $filters = ['priority' => 'high_critical', 'updated' => '7', 'assignment' => 'unassigned'];
        $this->actingAs($admin)->get(route('pms.tickets.index', $filters))->assertOk()
            ->assertSee('pm-ticket-page')->assertSee('Recent critical')
            ->assertDontSee('Recent low')->assertDontSee('Old assigned');
        $csv = $this->get(route('pms.tickets.export', $filters))->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString('Recent critical', $csv);
        $this->assertStringNotContainsString('Recent low', $csv);
        $this->assertStringNotContainsString('Old assigned', $csv);
    }

    public function test_sanctum_api_uses_the_same_feature_and_project_access_rules(): void
    {
        $this->enabled();
        $admin = $this->staff();
        Sanctum::actingAs($admin);
        $this->postJson(route('api.pms.projects.store'), ['key' => 'API', 'name' => 'API Project', 'priority' => 'P3'])->assertCreated();
        $project = PmProject::where('key', 'API')->firstOrFail();
        $this->postJson(route('api.pms.issues.store'), ['project_id' => $project->id, 'summary' => 'API issue', 'type' => 'Task', 'priority' => 'P3'])->assertCreated();
        $this->getJson(route('api.pms.issues.index', ['project' => $project->id]))->assertOk()->assertJsonPath('data.0.issue_key', 'API-FTR-0001');

        Sanctum::actingAs($this->staff(700));
        $this->getJson(route('api.pms.projects.index'))->assertForbidden();
        $this->enabled(false);
        Sanctum::actingAs($admin);
        $this->getJson(route('api.pms.projects.index'))->assertNotFound();
    }
}
