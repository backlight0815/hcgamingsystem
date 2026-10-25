<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PmsCycleTicketRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function member(PmProject $project, string $role, ?string $position = null, ?string $workspaceRole = null): User
    {
        $user = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(),
            'job_position' => $position, 'workspace_role' => $workspaceRole]);
        DB::table('pm_project_members')->insert(['project_id' => $project->id, 'user_id' => $user->id,
            'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
        return $user;
    }

    private function project(): array
    {
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'RULES', 'name' => 'Permission rules', 'priority' => 'P3',
        ])->assertCreated();
        return [$admin, PmProject::where('key', 'RULES')->firstOrFail()];
    }

    public function test_only_project_manager_product_owner_and_super_admin_can_mutate_cycles(): void
    {
        [$admin, $project] = $this->project();
        $manager = $this->member($project, 'Project Manager', 'Project Manager');
        $owner = $this->member($project, 'Viewer', 'Product Owner');
        $payload = ['project_id' => $project->id, 'name' => 'October plan',
            'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-10-01'];
        foreach ([['QA', 'QA Engineer'], ['Business Analyst', 'Business Analyst'],
            ['Developer', 'Developer'], ['Development Lead', 'Team Leader']] as [$role, $position]) {
            $actor = $this->member($project, $role, $position);
            $this->actingAs($actor)->postJson(route('pms.cycles.store'), $payload)
                ->assertForbidden()->assertJsonPath('code', 'PERMISSION_DENIED')
                ->assertJsonPath('message', 'Only Project Managers and Product Owners can manage planning cycles.');
            $this->get(route('pms.cycles.index'))->assertOk()->assertDontSee('Edit cycle');
            $this->postJson(route('pms.global-cycles.store'), [
                'name' => 'Unauthorized', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
            ])->assertForbidden();
        }
        $this->actingAs($manager)->postJson(route('pms.cycles.store'), $payload)->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $developer = $this->member($project, 'Developer', 'Developer');
        $this->actingAs($developer)->putJson(route('pms.cycles.update', $cycle), [])->assertForbidden();
        $this->deleteJson(route('pms.cycles.destroy', $cycle), [])->assertForbidden();
        $this->actingAs($owner)->postJson(route('pms.global-cycles.store'), [
            'name' => 'Owner cycle', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
        ])->assertCreated();
        $this->putJson(route('pms.cycles.update', $cycle), [
            'project_id' => $project->id, 'name' => 'Owner updated plan',
            'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-10-01',
            'target_end_date' => $cycle->target_end_date->toDateString(),
            'lock_version' => $cycle->lock_version,
        ])->assertOk();
        $this->deleteJson(route('pms.cycles.destroy', $cycle), ['confirmation' => $cycle->cycle_key])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), $payload)->assertCreated();
        Sanctum::actingAs($developer, ['pms']);
        $this->postJson(route('api.pms.cycles.store'), $payload)
            ->assertForbidden()->assertJsonPath('code', 'PERMISSION_DENIED')
            ->assertJsonPath('message', 'Only Project Managers and Product Owners can manage planning cycles.');
    }

    public function test_qa_created_qat_requires_eligible_developer_and_assigns_creator_as_qa(): void
    {
        [$admin, $project] = $this->project();
        $qa = $this->member($project, 'QA', 'QA Engineer', 'QA');
        $developer = $this->member($project, 'Developer', 'Developer');
        $manager = $this->member($project, 'Project Manager', 'Project Manager');
        $base = ['classification_key' => 'QAT', 'project_id' => $project->id,
            'summary' => 'Checkout defect', 'priority' => 'P3', 'defect_type' => 'Functional bug',
            'reproduction_steps' => 'Open checkout.', 'expected_result' => 'Checkout succeeds.',
            'actual_result' => 'Checkout fails.'];
        $this->actingAs($qa)->get(route('pms.tickets.create', ['category' => 'QAT']))->assertOk()
            ->assertSee('Assigned QA')->assertSee('name="developer_id"', false)
            ->assertDontSee('name="preconditions"', false)
            ->assertDontSee('name="testing_environment"', false)
            ->assertDontSee('name="affected_platform"', false)
            ->assertDontSee('name="browser_device"', false)
            ->assertDontSee('name="build_number"', false);
        $this->postJson(route('pms.tickets.store'), $base)->assertUnprocessable()->assertJsonValidationErrors('developer_id');
        $this->postJson(route('pms.tickets.store'), $base + ['developer_id' => $manager->id])
            ->assertUnprocessable()->assertJsonValidationErrors('developer_id');
        $this->postJson(route('pms.tickets.store'), $base + ['developer_id' => $developer->id,
            'qa_id' => $manager->id])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->assertSame($qa->id, $ticket->qa_id);
        $this->assertSame($developer->id, $ticket->developer_id);
        $this->assertSame($qa->id, $ticket->reporter_id);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'summary' => '', 'priority' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors(['summary', 'project_id', 'priority']);
    }

    public function test_assigned_qa_completes_qat_without_build_or_environment_and_keeps_history(): void
    {
        [$admin, $project] = $this->project();
        $qa = $this->member($project, 'QA', 'QA Engineer', 'QA');
        $developer = $this->member($project, 'Developer', 'Developer');
        $this->actingAs($qa)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'project_id' => $project->id, 'developer_id' => $developer->id,
            'summary' => 'QAT completion', 'priority' => 'P3', 'defect_type' => 'Functional bug',
            'reproduction_steps' => 'Reproduce.', 'expected_result' => 'Pass.', 'actual_result' => 'Fail.',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        foreach (['In Progress', 'Code Review', 'Ready for QAT', 'QAT in Progress'] as $status) {
            $this->actingAs($admin)->postJson(route('pms.tickets.transition', $ticket), [
                'status' => $status, 'lock_version' => $ticket->fresh()->lock_version,
            ])->assertOk();
        }
        $this->actingAs($developer)->postJson(route('pms.tickets.transition', $ticket), [
            'status' => 'QAT Done', 'lock_version' => $ticket->fresh()->lock_version,
        ])->assertForbidden();
        $this->actingAs($qa)->postJson(route('pms.tickets.transition', $ticket), [
            'status' => 'QAT Done', 'lock_version' => $ticket->fresh()->lock_version,
        ])->assertOk()->assertJsonPath('status', 'QAT Done');
        $this->assertSame($qa->id, $ticket->fresh()->qa_id);
        $this->assertSame($developer->id, $ticket->fresh()->developer_id);
        $this->assertDatabaseHas('pm_qa_verifications', ['issue_id' => $ticket->id,
            'build' => null, 'environment' => null, 'result' => 'Passed']);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id,
            'from_status' => 'QAT in Progress', 'to_status' => 'QAT Done']);
    }

    public function test_qa_metadata_migration_preserves_existing_verifications(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') $this->markTestSkipped('SQLite migration compatibility test.');
        [$admin, $project] = $this->project();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'project_id' => $project->id,
            'summary' => 'Historical verification', 'priority' => 'P3', 'defect_type' => 'Functional bug',
            'reproduction_steps' => 'Reproduce.', 'expected_result' => 'Pass.', 'actual_result' => 'Fail.',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        Schema::drop('pm_qa_verifications');
        Schema::create('pm_qa_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('tested_by')->constrained('users');
            $table->string('result', 16);
            $table->string('build', 100);
            $table->string('environment', 100);
            $table->text('remarks');
            $table->string('regression_result', 20)->nullable();
            $table->timestamp('tested_at');
            $table->timestamps();
        });
        DB::table('pm_qa_verifications')->insert(['issue_id' => $ticket->id, 'tested_by' => $admin->id,
            'result' => 'Passed', 'build' => '2.9.0', 'environment' => 'Staging',
            'remarks' => 'Original result', 'tested_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $migration = require database_path('migrations/2026_10_24_000001_make_pms_qa_verification_metadata_optional.php');
        $migration->up();
        $this->assertDatabaseHas('pm_qa_verifications', ['issue_id' => $ticket->id,
            'build' => '2.9.0', 'environment' => 'Staging', 'remarks' => 'Original result']);
        DB::table('pm_qa_verifications')->insert(['issue_id' => $ticket->id, 'tested_by' => $admin->id,
            'result' => 'Passed', 'build' => null, 'environment' => null,
            'remarks' => 'New result', 'tested_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $this->assertDatabaseCount('pm_qa_verifications', 2);
    }
}
