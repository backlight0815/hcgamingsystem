<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use App\Support\PmsDateTime;
use App\Support\PmsStatusRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsTimeAndReferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => 1, 'status' => 1]);
    }

    private function project(User $admin, string $key): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => $key, 'name' => $key.' project', 'priority' => 'P3',
        ])->assertCreated();
        return PmProject::where('key', $key)->firstOrFail();
    }

    private function pulse(bool $workspace): \Illuminate\Testing\TestResponse
    {
        $nonce = $this->getJson(route('pms.time-tracking.session'))->assertOk()->json('activity_nonce');
        return $this->postJson(route('pms.time-tracking.activity'), [
            'workspace' => $workspace, 'nonce' => $nonce,
        ]);
    }

    public function test_references_are_allocated_in_order_and_remain_immutable(): void
    {
        $admin = $this->admin();
        $first = $this->project($admin, 'ONE');
        $second = $this->project($admin, 'TWO');
        $this->assertSame('PJ1', $first->project_reference);
        $this->assertSame('PJ2', $second->project_reference);
        $this->deleteJson(route('pms.projects.trash', $first), ['confirmation' => 'ONE'])->assertOk();
        $third = $this->project($admin, 'THREE');
        $this->assertSame('PJ3', $third->project_reference);
        $this->get(route('pms.projects.index', ['q' => 'PJ3']))->assertOk()->assertSee('PJ3');
        $this->getJson(route('pms.projects.show', $third))->assertOk()->assertJsonPath('project_reference', 'PJ3');
        $this->putJson(route('pms.projects.update', $third), [
            'lock_version' => $third->lock_version, 'name' => 'Renamed', 'status' => $third->status,
            'priority' => $third->priority, 'project_reference' => 'PJ100',
        ])->assertOk();
        $this->assertSame('PJ3', $third->fresh()->project_reference);
    }

    public function test_work_timer_excludes_pause_and_saves_actual_seconds(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin, 'TIME');
        $this->get(route('pms.time-tracking.index'))->assertOk()->assertSee('Time Tracking');
        $this->postJson(route('pms.time-tracking.control'), [
            'action' => 'start', 'project_id' => $project->id, 'work_type' => 'Development',
            'description' => 'Review validation',
        ])->assertOk()->assertJsonPath('timer.status', 'running');
        $this->postJson(route('pms.time-tracking.control'), [
            'action' => 'start', 'project_id' => $project->id, 'work_type' => 'Development',
            'description' => 'Duplicate',
        ])->assertUnprocessable();
        $this->travel(2)->minutes();
        $this->postJson(route('pms.time-tracking.control'), ['action' => 'pause'])
            ->assertOk()->assertJsonPath('timer.duration_seconds', 120);
        $this->travel(2)->minutes();
        $this->postJson(route('pms.time-tracking.control'), ['action' => 'resume'])->assertOk();
        $this->travel(1)->minutes();
        $this->postJson(route('pms.time-tracking.control'), ['action' => 'stop'])->assertOk();
        $this->assertDatabaseHas('pm_time_entries', ['user_id' => $admin->id, 'project_id' => $project->id, 'duration_seconds' => 180]);
        $this->assertDatabaseCount('pm_work_timers', 0);
    }

    public function test_session_renews_only_on_eligible_interaction_and_expires_after_idle_hour(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson(route('pms.time-tracking.session'))->assertOk();
        $this->travel(55)->minutes();
        $this->getJson(route('pms.time-tracking.session'))->assertOk();
        $this->pulse(false)->assertOk();
        $this->travel(59)->minutes();
        $this->getJson(route('pms.time-tracking.session'))->assertOk();
        $this->travel(2)->minutes();
        $this->assertSame(1, DB::table('pm_workspace_sessions')->count());
        $this->assertGreaterThanOrEqual(3600, now()->timestamp - \Carbon\Carbon::parse(DB::table('pm_workspace_sessions')->first()->last_eligible_at, 'UTC')->timestamp);
        $this->getJson(route('pms.time-tracking.session'))->assertUnauthorized();
        $this->assertDatabaseHas('pm_workspace_sessions', ['user_id' => $admin->id, 'end_reason' => 'IDLE_TIMEOUT']);
    }

    public function test_activity_labels_and_malaysia_time_are_readable(): void
    {
        $this->assertSame('24 Oct 2026, 2:03 PM MYT', PmsDateTime::format('2026-10-24T06:03:00Z', true));
        $this->assertSame('In Progress', PmsStatusRegistry::key('ticket', 11));
        $this->assertSame('Ready for Internal Testing', PmsStatusRegistry::key('ticket', 13));
        $admin = $this->admin();
        $project = $this->project($admin, 'ACT');
        $this->postJson(route('pms.issues.store'), [
            'project_id' => $project->id, 'summary' => 'Audit ticket', 'type' => 'Task', 'priority' => 'P3',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        DB::table('pm_ticket_activity')->insert([
            'issue_id' => $ticket->id, 'actor_id' => $admin->id, 'actor_name' => $admin->name,
            'event_type' => 'status', 'field_name' => 'status_code', 'old_value' => '11', 'new_value' => '13',
            'metadata' => '{}', 'created_at' => now(),
        ]);
        $this->get(route('pms.tickets.show', [$ticket, 'activity' => 'status']))->assertOk()
            ->assertSee('In Progress')->assertSee('Ready for Internal Testing')
            ->assertDontSee('changed status code');
        DB::table('pm_ticket_activity')->insert([
            'issue_id' => $ticket->id, 'actor_id' => $admin->id, 'actor_name' => $admin->name,
            'event_type' => 'assignment', 'field_name' => 'developer_id', 'old_value' => null,
            'new_value' => '999999', 'metadata' => '{}', 'created_at' => now(),
        ]);
        $this->get(route('pms.tickets.show', [$ticket, 'activity' => 'assignment']))->assertOk()
            ->assertSee('Former user (ID: 999999)');
    }

    public function test_project_manager_and_product_owner_can_assign_owner_from_ticket_but_qa_cannot(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin, 'OWN');
        $this->postJson(route('pms.issues.store'), [
            'project_id' => $project->id, 'summary' => 'Project ticket', 'type' => 'Task', 'priority' => 'P3',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $manager = User::factory()->create(['role_id' => null, 'status' => 1]);
        $qa = User::factory()->create(['role_id' => null, 'status' => 1]);
        $owner = User::factory()->create(['role_id' => null, 'status' => 1]);
        $po = User::factory()->create(['role_id' => null, 'status' => 1]);
        foreach ([$manager, $qa, $owner, $po] as $user) {
            PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(),
                'job_position' => $user->id === $po->id ? 'Product Owner' : null]);
        }
        $project->members()->attach($manager->id, ['role' => 'Project Manager']);
        $project->members()->attach($qa->id, ['role' => 'QA']);
        $project->members()->attach($po->id, ['role' => 'Viewer']);
        $this->actingAs($qa)->postJson(route('pms.tickets.project-owner', $ticket), ['owner_id' => $owner->id])->assertForbidden();
        $this->actingAs($manager)->postJson(route('pms.tickets.project-owner', $ticket), ['owner_id' => $owner->id])
            ->assertOk()->assertJsonPath('owner_name', $owner->name);
        $this->assertSame($owner->id, $project->fresh()->owner_id);
        $this->assertDatabaseHas('pm_ticket_activity', ['issue_id' => $ticket->id, 'field_name' => 'project_owner_id']);
        $this->actingAs($po)->postJson(route('pms.tickets.project-owner', $ticket), ['owner_id' => $admin->id])->assertOk();
        $this->assertSame('PJ1', $project->fresh()->project_reference);
        $this->actingAs($manager)->get(route('pms.tickets.show', $ticket))->assertOk()->assertSee('changed the Project Owner');
    }

    public function test_idle_work_timer_pauses_at_five_minutes_and_outsider_cannot_track_project(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin, 'IDLE');
        $outsider = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $outsider->id, 'status' => 'active', 'approved_at' => now()]);
        $this->actingAs($outsider)->postJson(route('pms.time-tracking.control'), [
            'action' => 'start', 'project_id' => $project->id,
            'work_type' => 'Development', 'description' => 'Unauthorized',
        ])->assertForbidden();
        $this->actingAs($admin)->postJson(route('pms.time-tracking.control'), [
            'action' => 'start', 'project_id' => $project->id,
            'work_type' => 'Development', 'description' => 'Review tests',
        ])->assertOk();
        $this->travel(8)->minutes();
        $this->getJson(route('pms.time-tracking.session'))->assertOk()
            ->assertJsonPath('timer.status', 'paused')
            ->assertJsonPath('timer.duration_seconds', 300);
    }

    public function test_ticket_export_uses_selected_sort_and_escapes_formula_text(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin, 'CSV');
        foreach (['Alpha ticket', '=SUM(1,2)'] as $summary) {
            $this->postJson(route('pms.issues.store'), [
                'project_id' => $project->id, 'summary' => $summary,
                'type' => 'Task', 'priority' => 'P3',
            ])->assertCreated();
        }
        $this->get(route('pms.tickets.index', ['sort_by' => 'summary', 'sort_order' => 'desc']))
            ->assertOk()->assertSee('aria-sort="descending"', false)->assertSee('Export CSV');
        $response = $this->get(route('pms.tickets.export', ['sort_by' => 'summary', 'sort_order' => 'desc']));
        $response->assertOk();
        ob_start();
        $response->baseResponse->sendContent();
        $csv = ob_get_clean();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertTrue(strpos($csv, 'Alpha ticket') < strpos($csv, "'=SUM(1,2)"));
    }

    public function test_workspace_activity_intervals_do_not_double_count_back_to_back_tabs(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson(route('pms.time-tracking.session'))->assertOk();
        $this->pulse(true)->assertOk();
        $this->travel(30)->seconds();
        $this->pulse(true)->assertOk();
        $this->pulse(true)->assertOk();
        $this->assertSame(30, (int) DB::table('pm_workspace_activity_intervals')->sum('duration_seconds'));
        $this->get(route('pms.time-tracking.index'))->assertOk()->assertSee('00:00:30');
    }

    public function test_replayed_activity_nonce_cannot_extend_a_session(): void
    {
        $admin = $this->admin();
        $nonce = $this->actingAs($admin)->getJson(route('pms.time-tracking.session'))
            ->assertOk()->json('activity_nonce');
        $this->postJson(route('pms.time-tracking.activity'), [
            'workspace' => false, 'nonce' => $nonce,
        ])->assertOk();
        $this->postJson(route('pms.time-tracking.activity'), [
            'workspace' => false, 'nonce' => $nonce,
        ])->assertStatus(409);
    }

    public function test_time_entry_corrections_require_super_admin_and_keep_an_audit_record(): void
    {
        $admin = $this->admin();
        $project = $this->project($admin, 'EDIT');
        $entry = DB::table('pm_time_entries')->insertGetId([
            'user_id' => $admin->id, 'project_id' => $project->id, 'ticket_id' => null,
            'work_type' => 'Documentation', 'description' => 'Draft notes',
            'started_at' => now('UTC')->subMinutes(20), 'ended_at' => now('UTC'),
            'duration_seconds' => 1200, 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        $member = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $member->id, 'status' => 'active', 'approved_at' => now()]);
        $this->actingAs($member)->putJson(route('pms.time-tracking.adjust', $entry), [
            'duration_seconds' => 900, 'description' => 'Revised notes', 'reason' => 'Corrected overcount',
        ])->assertForbidden();
        $this->actingAs($admin)->putJson(route('pms.time-tracking.adjust', $entry), [
            'duration_seconds' => 900, 'description' => 'Revised notes', 'reason' => 'Corrected overcount',
        ])->assertOk()->assertJsonPath('duration_seconds', 900);
        $this->assertDatabaseHas('pm_time_entry_adjustments', [
            'entry_id' => $entry, 'actor_id' => $admin->id,
            'old_duration_seconds' => 1200, 'new_duration_seconds' => 900,
        ]);
        $this->get(route('pms.time-tracking.index'))->assertOk()->assertSee('Time entry corrections');
        $this->deleteJson(route('pms.projects.trash', $project), ['confirmation' => 'EDIT'])->assertOk();
        $this->deleteJson(route('pms.projects.purge', $project->id), ['confirmation' => 'EDIT'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirmation');
    }
}
