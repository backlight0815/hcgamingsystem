<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmGlobalCycle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsGlobalCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role_id' => 1, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(), 'can_create_all_tickets' => true]);

        return $user;
    }

    public function test_scheduler_creates_independent_consecutive_cycles_and_recovers_missed_runs(): void
    {
        $admin = $this->admin();
        $anchor = today()->subDays(92)->toDateString();
        $this->actingAs($admin)->putJson(route('pms.global-cycles.settings'), [
            'auto_enabled' => 1, 'interval_days' => 45, 'anchor_date' => $anchor,
            'timezone' => config('app.timezone'),
        ])->assertOk();
        $this->artisan('pms:generate-cycles')->assertExitCode(0);
        $this->artisan('pms:generate-cycles')->assertExitCode(0);
        $cycles = PmGlobalCycle::orderBy('start_date')->get();
        $this->assertCount(3, $cycles);
        $this->assertSame('Completed', $cycles[0]->status);
        $this->assertSame('Active', $cycles[2]->status);
        $this->assertSame($anchor, $cycles[0]->start_date->toDateString());
        $this->assertSame($cycles[0]->end_date->copy()->addDay()->toDateString(), $cycles[1]->start_date->toDateString());
        $this->assertSame($cycles[1]->end_date->copy()->addDay()->toDateString(), $cycles[2]->start_date->toDateString());
        $this->assertSame(45, $cycles[0]->start_date->diffInDays($cycles[0]->end_date) + 1);
        $this->assertSame(0, DB::table('pm_global_cycle_projects')->count());
        $this->assertSame(6, $cycles[0]->stages()->count());
        $this->actingAs($admin)->get(route('pms.global-cycles.index'))->assertOk()->assertSee($cycles[2]->cycle_key);
    }

    public function test_manual_cycle_project_assignment_and_ticket_carry_forward_preserve_identity(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'CYCLE', 'name' => 'Cycle project', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::firstOrFail();
        $start = today()->toDateString();
        $end = today()->addDays(44)->toDateString();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'First organization cycle', 'start_date' => $start, 'end_date' => $end,
        ])->assertCreated();
        $first = PmGlobalCycle::firstOrFail();
        $this->assertNull($first->projects()->first());
        $this->actingAs($admin)->postJson(route('pms.global-cycles.projects.store', $first), [
            'project_ids' => [$project->id],
        ])->assertOk();
        $this->assertSame(1, $first->projects()->count());
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'TSK', 'project_id' => $project->id, 'summary' => 'Build a dashboard', 'priority' => 'P3',
            'description' => 'Build and verify the dashboard.',
        ])->assertCreated()->assertJsonPath('priority', 'P3');
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.store', $first), [
            'issue_key' => $ticket->issue_key,
        ])->assertOk();
        $this->assertSame($first->id, $ticket->fresh()->global_cycle_id);
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'Second organization cycle', 'start_date' => today()->addDays(45)->toDateString(),
            'end_date' => today()->addDays(89)->toDateString(),
        ])->assertCreated();
        $second = PmGlobalCycle::orderByDesc('id')->firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.store', $second), [
            'issue_key' => $ticket->issue_key,
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.store', $second), [
            'issue_key' => $ticket->issue_key, 'reason' => 'Carry forward unfinished work.',
        ])->assertOk();
        $this->assertSame($first->id, $ticket->fresh()->original_global_cycle_id);
        $this->assertSame($second->id, $ticket->fresh()->global_cycle_id);
        $this->assertDatabaseCount('pm_work_items', 1);
        $this->actingAs($admin)->deleteJson(route('pms.global-cycles.projects.destroy', [$first, $project]))->assertOk();
        $this->assertNotNull($project->fresh());
        $this->assertSame($second->id, $ticket->fresh()->global_cycle_id);
        $this->actingAs($admin)->get(route('pms.global-cycles.show', [$second, 'tab' => 'tickets']))->assertOk()->assertSee($ticket->reference);
    }

    public function test_cycle_overlap_and_unexplained_extension_are_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'Primary cycle', 'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(44)->toDateString(),
        ])->assertCreated();
        $cycle = PmGlobalCycle::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'Overlapping cycle', 'start_date' => today()->addDays(30)->toDateString(),
            'end_date' => today()->addDays(74)->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $change = [
            'name' => $cycle->name, 'start_date' => $cycle->start_date->toDateString(),
            'end_date' => today()->addDays(49)->toDateString(), 'lock_version' => 0,
        ];
        $this->actingAs($admin)->putJson(route('pms.global-cycles.update', $cycle), $change)
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->actingAs($admin)->putJson(route('pms.global-cycles.update', $cycle), $change + ['reason' => 'QAT needs five more days.'])
            ->assertOk();
        $this->assertSame(50, $cycle->fresh()->start_date->diffInDays($cycle->fresh()->end_date) + 1);
        $this->assertSame(6, $cycle->stages()->count());
    }

    public function test_release_can_use_organization_cycle_without_legacy_project_cycle(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'GLOBAL', 'name' => 'Global release project', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'Organization release cycle', 'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(44)->toDateString(),
        ])->assertCreated();
        $cycle = PmGlobalCycle::firstOrFail();
        $releaseData = [
            'project_id' => $project->id, 'global_cycle_id' => $cycle->id,
            'name' => 'Cross-team delivery', 'version' => '3.0.0',
            'planned_deploy_date' => today()->addDays(40)->toDateString(),
        ];
        $this->actingAs($admin)->postJson(route('pms.releases.store'), $releaseData)
            ->assertUnprocessable()->assertJsonValidationErrors('global_cycle_id');
        $this->actingAs($admin)->postJson(route('pms.global-cycles.projects.store', $cycle), [
            'project_ids' => [$project->id],
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.releases.store'), $releaseData)->assertCreated();
        $release = \App\Models\PmRelease::firstOrFail();
        $this->assertNull($release->cycle_id);
        $this->assertSame($cycle->id, $release->global_cycle_id);
        $this->actingAs($admin)->get(route('pms.releases.index'))->assertOk()->assertSee($cycle->cycle_key);
        $this->actingAs($admin)->get(route('pms.releases.show', $release))->assertOk()->assertSee($cycle->cycle_key);
        $this->actingAs($admin)->get(route('pms.releases.summary', $release))->assertOk();
    }

    public function test_cycle_directory_counts_only_projects_and_tickets_visible_to_member(): void
    {
        $admin = $this->admin();
        $member = User::factory()->create(['role_id' => 3, 'status' => 1]);
        PmsProfile::create(['user_id' => $member->id, 'status' => 'active', 'approved_at' => now()]);
        foreach (['VISIBLE', 'PRIVATE'] as $key) {
            $this->actingAs($admin)->postJson(route('pms.projects.store'), [
                'key' => $key, 'name' => $key.' project', 'priority' => 'P3',
            ])->assertCreated();
        }
        $visible = PmProject::where('key', 'VISIBLE')->firstOrFail();
        $private = PmProject::where('key', 'PRIVATE')->firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $visible), [
            'user_id' => $member->id, 'role' => 'Developer',
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'Shared cycle', 'start_date' => today()->toDateString(),
            'end_date' => today()->addDays(44)->toDateString(),
        ])->assertCreated();
        $cycle = PmGlobalCycle::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.projects.store', $cycle), [
            'project_ids' => [$visible->id, $private->id],
        ])->assertOk();
        foreach ([$visible, $private] as $project) {
            $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
                'classification_key' => 'TSK', 'project_id' => $project->id,
                'summary' => 'Work for '.$project->key, 'description' => 'Scoped work item.', 'priority' => 'P3',
            ])->assertCreated();
            $ticket = PmWorkItem::where('project_id', $project->id)->firstOrFail();
            $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.store', $cycle), [
                'issue_key' => $ticket->issue_key,
            ])->assertOk();
        }
        $this->actingAs($member)->getJson(route('pms.global-cycles.index'))
            ->assertOk()->assertJsonPath('cycles.data.0.projects_count', 1)
            ->assertJsonPath('cycles.data.0.tickets_count', 1);
        $this->actingAs($member)->get(route('pms.global-cycles.show', $cycle))
            ->assertOk()->assertDontSee('Work for PRIVATE');
    }
}
