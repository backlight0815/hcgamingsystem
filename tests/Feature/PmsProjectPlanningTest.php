<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmGlobalCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsProjectPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function person(?string $position = null, bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? 1 : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(),
            'job_position' => $position]);
        return $user;
    }

    private function project(User $admin, string $key, array $extra = []): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => $key, 'name' => $key.' workspace', 'priority' => 'P3',
        ] + $extra)->assertCreated();
        return PmProject::where('key', $key)->firstOrFail();
    }

    private function cycle(User $admin, string $name, int $offset): PmGlobalCycle
    {
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => $name, 'start_date' => today()->addDays($offset)->toDateString(),
            'end_date' => today()->addDays($offset + 29)->toDateString(),
        ])->assertCreated();
        return PmGlobalCycle::orderByDesc('id')->firstOrFail();
    }

    public function test_project_creation_selects_existing_cycle_and_release_without_moving_tickets(): void
    {
        $admin = $this->person(admin: true);
        $source = $this->project($admin, 'SOURCE');
        $cycle = $this->cycle($admin, 'Development cycle two', 30);
        $this->postJson(route('pms.global-cycles.projects.store', $cycle), ['project_ids' => [$source->id]])->assertOk();
        $this->postJson(route('pms.releases.store'), [
            'project_id' => $source->id, 'global_cycle_id' => $cycle->id, 'name' => 'Second release',
            'version' => '2.0', 'planned_deploy_date' => today()->addDays(55)->toDateString(),
        ])->assertCreated();
        $release = PmRelease::firstOrFail();
        $new = $this->project($admin, 'NEWAI', [
            'current_global_cycle_id' => $cycle->id, 'current_release_id' => $release->id,
        ]);
        $this->assertSame($cycle->id, $new->current_global_cycle_id);
        $this->assertSame($release->id, $new->current_release_id);
        $this->assertDatabaseHas('pm_global_cycle_projects', ['cycle_id' => $cycle->id, 'project_id' => $new->id]);
        $this->assertDatabaseHas('pm_project_releases', ['release_id' => $release->id, 'project_id' => $new->id]);
        $this->assertSame($source->id, $release->fresh()->project_id);
        $this->get(route('pms.projects.show', $new))->assertOk()->assertSee($cycle->cycle_key)->assertSee($release->release_key);
        $this->get(route('pms.releases.index', ['project' => $new->id]))->assertOk()->assertSee($release->release_key);
        $otherCycle = $this->cycle($admin, 'Development cycle three', 60);
        $this->putJson(route('pms.projects.planning.update', $new), [
            'lock_version' => $new->lock_version, 'current_global_cycle_id' => $otherCycle->id,
            'current_release_id' => $release->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('current_release_id');
        $this->assertSame($cycle->id, $new->fresh()->current_global_cycle_id);
    }

    public function test_cycle_change_keeps_history_and_requires_deliberate_release_correction(): void
    {
        $admin = $this->person(admin: true);
        $first = $this->cycle($admin, 'First', 0);
        $second = $this->cycle($admin, 'Second', 30);
        $project = $this->project($admin, 'MOVE', ['current_global_cycle_id' => $first->id]);
        $this->putJson(route('pms.projects.planning.update', $project), [
            'lock_version' => $project->lock_version, 'current_global_cycle_id' => $second->id,
        ])->assertOk()->assertJsonPath('current_global_cycle_id', $second->id);
        $this->assertDatabaseHas('pm_global_cycle_projects', ['cycle_id' => $first->id, 'project_id' => $project->id]);
        $this->assertDatabaseHas('pm_global_cycle_projects', ['cycle_id' => $second->id, 'project_id' => $project->id]);
        $this->assertSame(2, $project->globalCycles()->count());
        $this->putJson(route('pms.projects.planning.update', $project), [
            'lock_version' => $project->fresh()->lock_version, 'current_global_cycle_id' => $second->id,
        ])->assertOk();
        $this->assertSame(2, $project->fresh()->globalCycles()->count());
        $this->assertSame(2, DB::table('pm_audit_logs')->where('action', 'project.cycle_changed')
            ->where('subject_id', $project->id)->count());
    }

    public function test_batch_assignment_is_unique_and_removal_preserves_projects(): void
    {
        $admin = $this->person(admin: true);
        $cycle = $this->cycle($admin, 'Shared', 0);
        $projects = collect(['ONE', 'TWO', 'THREE'])->map(fn ($key) => $this->project($admin, $key));
        $ids = $projects->pluck('id')->all();
        $this->postJson(route('pms.global-cycles.projects.store', $cycle), ['project_ids' => $ids])->assertOk();
        $this->postJson(route('pms.global-cycles.projects.store', $cycle), ['project_ids' => [$ids[0], $ids[0]]])->assertOk();
        $this->assertSame(3, $cycle->projects()->count());
        foreach ($projects as $project) $this->assertSame($cycle->id, $project->fresh()->current_global_cycle_id);
        $this->deleteJson(route('pms.global-cycles.projects.destroy', [$cycle, $projects[1]]))->assertOk();
        $this->assertSame(2, $cycle->projects()->count());
        $this->assertNotNull($projects[1]->fresh());
        $this->assertNull($projects[1]->fresh()->current_global_cycle_id);
    }

    public function test_cycle_authorization_and_candidate_search_use_project_scope(): void
    {
        $admin = $this->person(admin: true);
        $pm = $this->person('Project Manager');
        $po = $this->person('Product Owner');
        $ba = $this->person('Business Analyst');
        $qa = $this->person('QA Engineer');
        $developer = $this->person('Developer');
        $cycle = $this->cycle($admin, 'Access cycle', 0);
        $project = $this->project($admin, 'ACCESS');
        foreach ([$pm, $po, $ba, $qa, $developer] as $user) {
            $project->members()->attach($user->id, ['role' => $user === $pm ? 'Project Manager' : 'Viewer']);
        }
        $this->actingAs($ba)->postJson(route('pms.global-cycles.projects.store', $cycle), ['project_ids' => [$project->id]])->assertForbidden();
        $this->putJson(route('pms.projects.planning.update', $project), [
            'lock_version' => $project->lock_version, 'current_global_cycle_id' => $cycle->id,
        ])->assertForbidden();
        $this->actingAs($qa)->getJson(route('pms.global-cycles.project-candidates', $cycle))->assertForbidden();
        $this->actingAs($developer)->postJson(route('pms.global-cycles.projects.store', $cycle), ['project_ids' => [$project->id]])->assertForbidden();
        $this->actingAs($pm)->getJson(route('pms.global-cycles.project-candidates', [$cycle, 'q' => 'ACCESS']))
            ->assertOk()->assertJsonPath('data.0.id', $project->id);
        $this->postJson(route('pms.global-cycles.projects.store', $cycle), ['project_ids' => [$project->id]])->assertOk();
        $this->actingAs($po)->postJson(route('pms.global-cycles.projects.store', $cycle), ['project_ids' => [$project->id]])->assertOk();
        $this->actingAs($pm)->putJson(route('pms.projects.update', $project), [
            'lock_version' => $project->fresh()->lock_version, 'name' => $project->name,
            'status' => $project->status, 'priority' => $project->priority, 'owner_id' => $qa->id,
        ])->assertOk()->assertJsonPath('owner_id', $qa->id);
    }

    public function test_project_owner_candidate_codes_are_enforced_at_submission(): void
    {
        $admin = $this->person(admin: true);
        $pm = $this->person('Project Manager');
        $ownerPosition = DB::table('pms_job_positions')->insertGetId([
            'code' => 'PJO', 'name' => 'Project Owner', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $prohibited = $this->person('Project Owner');
        $prohibited->pmsProfile->update(['job_position_id' => $ownerPosition]);
        $eligible = $this->person('QA Engineer');
        $candidates = app(\App\Services\PmsProjectOwnerService::class)->candidates();
        $this->assertFalse($candidates->contains('id', $pm->id));
        $this->assertFalse($candidates->contains('id', $prohibited->id));
        $this->assertTrue($candidates->contains('id', $eligible->id));
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => 'REJECT', 'name' => 'Rejected',
            'priority' => 'P3', 'owner_id' => $prohibited->id])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $project = $this->project($admin, 'VALID', ['owner_id' => $eligible->id]);
        $this->putJson(route('pms.projects.update', $project), ['lock_version' => $project->lock_version,
            'name' => $project->name, 'status' => $project->status, 'priority' => $project->priority,
            'owner_id' => $pm->id])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $eligible->pmsProfile->update(['job_position_id' => $ownerPosition]);
        $other = $this->project($admin, 'OTHER');
        $this->putJson(route('pms.projects.update', $other), ['lock_version' => $other->lock_version,
            'name' => $other->name, 'status' => $other->status, 'priority' => $other->priority,
            'owner_id' => $eligible->id])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
    }

    public function test_invalid_release_cycle_pair_and_unknown_cycle_do_not_create_project(): void
    {
        $admin = $this->person(admin: true);
        $source = $this->project($admin, 'SOURCE');
        $first = $this->cycle($admin, 'First', 0);
        $second = $this->cycle($admin, 'Second', 30);
        $this->postJson(route('pms.global-cycles.projects.store', $first), ['project_ids' => [$source->id]])->assertOk();
        $this->postJson(route('pms.releases.store'), [
            'project_id' => $source->id, 'global_cycle_id' => $first->id, 'name' => 'First release',
            'version' => '1.0', 'planned_deploy_date' => today()->addDays(25)->toDateString(),
        ])->assertCreated();
        $release = PmRelease::firstOrFail();
        $this->postJson(route('pms.projects.store'), ['key' => 'BADPAIR', 'name' => 'Bad pair',
            'priority' => 'P3', 'current_global_cycle_id' => $second->id,
            'current_release_id' => $release->id])->assertUnprocessable()->assertJsonValidationErrors('current_release_id');
        $this->postJson(route('pms.projects.store'), ['key' => 'BADCYCLE', 'name' => 'Bad cycle',
            'priority' => 'P3', 'current_global_cycle_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('current_global_cycle_id');
        $this->assertDatabaseMissing('pm_projects', ['key' => 'BADPAIR']);
        $this->assertDatabaseMissing('pm_projects', ['key' => 'BADCYCLE']);
        $plain = $this->project($admin, 'PLAIN');
        $this->assertNull($plain->current_global_cycle_id);
        $this->assertNull($plain->current_release_id);
        $this->get(route('pms.projects.index'))->assertOk()->assertSee('No Cycle / Unassigned')
            ->assertSee('No Release / Unassigned');
    }

    public function test_api_project_info_round_trips_planning_fields_and_owner_candidates(): void
    {
        $admin = $this->person(admin: true);
        $cycle = $this->cycle($admin, 'API cycle', 0);
        $token = $admin->createToken('planning-api', ['pms'], now()->addHour())->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson(route('api.pms.v1.projects.updateInfo'), [
            'action' => 'insert', 'project_key' => 'APIPLAN', 'name' => 'API planning',
            'priority' => 'normal', 'current_global_cycle_id' => $cycle->id,
        ])->assertCreated()->assertJsonPath('data.development_cycle.id', $cycle->id);
        $project = PmProject::where('key', 'APIPLAN')->firstOrFail();
        $this->getJson(route('api.pms.v1.projects.getInfo', ['project_id' => $project->id]))
            ->assertOk()->assertJsonPath('data.current_global_cycle_id', $cycle->id)
            ->assertJsonPath('data.related_cycles.0.current', true);
        $this->postJson(route('api.pms.v1.projects.updateInfo'), [
            'action' => 'update', 'project_id' => $project->id, 'current_global_cycle_id' => null,
        ])->assertOk()->assertJsonPath('data.current_global_cycle_id', null);
        $this->assertSame(1, $project->globalCycles()->count());
        $this->getJson(route('api.pms.v1.assignableUsers', ['assignment_type' => 'project_owner', 'project_id' => $project->id]))
            ->assertOk()->assertJsonPath('code', 'ASSIGNABLE_USERS_RETRIEVED');
    }
}
