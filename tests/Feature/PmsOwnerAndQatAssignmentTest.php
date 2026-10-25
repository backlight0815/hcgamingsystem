<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmsProfile;
use App\Models\PmWorkItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmsOwnerAndQatAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function qa(): User
    {
        $user = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(), 'workspace_role' => 'QA']);

        return $user;
    }

    private function createProject(User $admin, string $key, ?User $owner = null): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => $key, 'name' => $key.' workspace', 'priority' => 'P2', 'owner_id' => $owner?->id,
        ])->assertCreated();

        return PmProject::where('key', $key)->firstOrFail();
    }

    public function test_project_owner_can_be_selected_and_changed_without_granting_manager_role(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $firstOwner = $this->qa();
        $secondOwner = $this->qa();
        $project = $this->createProject($admin, 'OWN', $firstOwner);
        $this->assertSame($firstOwner->id, $project->owner_id);
        $this->assertNull($project->manager_id);
        $this->assertDatabaseHas('pm_project_members', ['project_id' => $project->id, 'user_id' => $firstOwner->id, 'role' => 'Viewer']);
        $this->actingAs($admin)->putJson(route('pms.projects.update', $project), [
            'lock_version' => $project->lock_version, 'name' => $project->name, 'status' => $project->status,
            'priority' => $project->priority, 'owner_id' => $secondOwner->id,
        ])->assertOk()->assertJsonPath('owner_id', $secondOwner->id);
        $this->assertDatabaseHas('pm_project_members', ['project_id' => $project->id, 'user_id' => $secondOwner->id, 'role' => 'Viewer']);
        $this->actingAs($admin)->get(route('pms.projects.show', $project))->assertOk()->assertSee($secondOwner->name);
    }

    public function test_inactive_existing_owner_remains_visible_but_cannot_be_selected_for_new_ownership(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $owner = $this->qa();
        $project = $this->createProject($admin, 'HIST', $owner);
        $owner->update(['status' => 0]);

        $this->actingAs($admin)->get(route('pms.projects.show', $project))->assertOk()
            ->assertSee('Current owner (inactive)');
        $this->putJson(route('pms.projects.update', $project), [
            'lock_version' => $project->lock_version, 'name' => 'Renamed workspace',
            'status' => $project->status, 'priority' => $project->priority, 'owner_id' => $owner->id,
        ])->assertOk();

        $other = $this->createProject($admin, 'NEW');
        $this->putJson(route('pms.projects.update', $other), [
            'lock_version' => $other->lock_version, 'name' => $other->name,
            'status' => $other->status, 'priority' => $other->priority, 'owner_id' => $owner->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    }

    public function test_qa_single_project_autofills_release_cycle_and_optional_environment_fields(): void
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $qa = $this->qa();
        $project = $this->createProject($admin, 'AUTO');
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $qa->id, 'role' => 'QA',
        ])->assertOk();
        $developer = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $developer->id, 'status' => 'active', 'job_position' => 'Developer']);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $developer->id, 'role' => 'Developer',
        ])->assertOk();
        $this->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'October delivery', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $this->postJson(route('pms.releases.store'), [
            'project_id' => $project->id, 'cycle_id' => $cycle->id, 'name' => 'October release',
            'version' => '1.0', 'planned_deploy_date' => '2026-10-28',
        ])->assertCreated();
        $release = PmRelease::firstOrFail();

        $this->actingAs($qa)->get(route('pms.tickets.create', ['category' => 'QAT']))->assertOk()
            ->assertSee('Automatically assigned from your single eligible project.')
            ->assertSee("Automatically selected from this project's current release.", false);
        $this->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'project_id' => $project->id, 'developer_id' => $developer->id,
            'summary' => 'Payment button fails', 'priority' => 'P3', 'defect_type' => 'Functional bug',
            'reproduction_steps' => 'Open payment and submit.', 'expected_result' => 'Payment proceeds.',
            'actual_result' => 'The form stays open.',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->assertSame($release->id, $ticket->release_id);
        $this->assertSame($cycle->id, $ticket->cycle_id);
        $this->assertSame($qa->id, $ticket->qa_id);
        $this->assertSame($developer->id, $ticket->developer_id);
        foreach (['testing_environment', 'affected_platform', 'browser_device', 'build_number'] as $field) {
            $this->assertNull($ticket->{$field});
        }
        $this->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'affected_platform' => 'Web',
        ])->assertOk();
        $this->assertSame('Web', $ticket->fresh()->affected_platform);
        $this->assertNull($ticket->fresh()->browser_device);
        $this->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version,
            'testing_environment' => 'Staging', 'browser_device' => 'Chrome', 'build_number' => '2.9.0',
        ])->assertOk();
        $this->assertSame('Chrome', $ticket->fresh()->browser_device);
        $this->assertSame('Staging', $ticket->fresh()->testing_environment);
        $this->assertSame('2.9.0', $ticket->fresh()->build_number);
        $this->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version,
            'testing_environment' => null, 'affected_platform' => null,
            'browser_device' => null, 'build_number' => null,
        ])->assertOk();
        foreach (['testing_environment', 'affected_platform', 'browser_device', 'build_number'] as $field) {
            $this->assertNull($ticket->fresh()->{$field});
        }

        $other = $this->createProject($admin, 'OTHER');
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $other->id, 'name' => 'Other cycle', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $otherCycle = PmCycle::where('project_id', $other->id)->firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.releases.store'), [
            'project_id' => $other->id, 'cycle_id' => $otherCycle->id, 'name' => 'Other release', 'version' => '1.0',
            'planned_deploy_date' => '2026-10-29',
        ])->assertCreated();
        $otherRelease = PmRelease::where('project_id', $other->id)->firstOrFail();
        $this->actingAs($qa)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'project_id' => $other->id,
            'summary' => 'Unauthorized project', 'priority' => 'P3', 'defect_type' => 'Functional bug',
            'reproduction_steps' => 'Open.', 'expected_result' => 'Success.', 'actual_result' => 'Failure.',
        ])->assertForbidden();
        $this->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'project_id' => $project->id, 'release_id' => $otherRelease->id,
            'developer_id' => $developer->id,
            'summary' => 'Wrong release', 'priority' => 'P3', 'defect_type' => 'Functional bug',
            'reproduction_steps' => 'Open.', 'expected_result' => 'Success.', 'actual_result' => 'Failure.',
        ])->assertUnprocessable()->assertJsonValidationErrors('release_id');
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $other), [
            'user_id' => $qa->id, 'role' => 'QA',
        ])->assertOk();
        $this->actingAs($qa)->get(route('pms.tickets.create', ['category' => 'QAT']))->assertOk()
            ->assertDontSee('Automatically assigned from your single eligible project.');
    }
}
