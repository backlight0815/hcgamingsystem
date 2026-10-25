<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use App\Services\PmsCycleProgress;
use App\Services\PmsCycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmsAutomaticActivityAndCycleProgressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function user(bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? 1 : null, 'status' => 1]);
        if (! $admin) PmsProfile::create(['user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function beat(string $tab, bool $visible, bool $interacted): void
    {
        $this->postJson(route('pms.time-tracking.heartbeat'), [
            'tab_id' => $tab, 'visible' => $visible, 'focused' => $visible,
            'interacted' => $interacted,
        ])->assertOk();
    }

    public function test_foreground_activity_idles_resumes_and_deduplicates_tabs(): void
    {
        $user = $this->user();
        $this->actingAs($user)->getJson(route('pms.time-tracking.session'))->assertOk();
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();
        $this->beat($first, true, true);
        $this->beat($second, true, true);
        $this->travel(30)->seconds();
        $this->beat($first, true, false);
        $this->beat($second, true, false);
        $this->assertSame(30, (int) DB::table('pm_workspace_activity_intervals')->where('activity_status', 'active')->sum('duration_seconds'));
        for ($i = 0; $i < 10; $i++) {
            $this->travel(30)->seconds();
            $this->beat($first, true, false);
        }
        $active = (int) DB::table('pm_workspace_activity_intervals')->where('activity_status', 'active')->sum('duration_seconds');
        $this->assertSame(300, $active);
        $this->assertGreaterThan(0, (int) DB::table('pm_workspace_activity_intervals')->where('activity_status', 'idle')->sum('duration_seconds'));
        $this->beat($first, true, true);
        $this->travel(30)->seconds();
        $this->beat($first, true, false);
        $this->assertSame(330, (int) DB::table('pm_workspace_activity_intervals')->where('activity_status', 'active')->sum('duration_seconds'));
        $this->beat($first, false, false);
        $this->travel(10)->minutes();
        $this->beat($first, false, false);
        $this->assertSame(330, (int) DB::table('pm_workspace_activity_intervals')->where('activity_status', 'active')->sum('duration_seconds'));
    }

    public function test_report_is_restricted_but_collection_remains_available_to_staff(): void
    {
        $staff = $this->user();
        $this->actingAs($staff)->getJson(route('pms.time-tracking.session'))->assertOk();
        $this->beat((string) Str::uuid(), true, true);
        $this->get(route('pms.time-tracking.index'))->assertForbidden();
        $this->get(route('pms.time-tracking.export'))->assertForbidden();
    }

    public function test_stale_heartbeat_does_not_backfill_network_gap_and_local_midnight_is_split(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 15:59:40 UTC'));
        $user = $this->user();
        $this->actingAs($user)->getJson(route('pms.time-tracking.session'))->assertOk();
        $tab = (string) Str::uuid();
        $this->beat($tab, true, true);
        $this->travel(30)->seconds();
        $this->beat($tab, true, true);
        $daily = DB::table('pm_workspace_activity_intervals')->where('activity_status', 'active')
            ->orderBy('activity_date')->pluck('duration_seconds', 'activity_date')->all();
        $this->assertSame(['2026-10-25' => 20, '2026-10-26' => 10], $daily);
        $this->travel(2)->minutes();
        $this->beat($tab, true, true);
        $this->assertSame(30, (int) DB::table('pm_workspace_activity_intervals')->where('activity_status', 'active')->sum('duration_seconds'));
    }

    public function test_idle_presence_heartbeats_do_not_extend_one_hour_authentication_lease(): void
    {
        $user = $this->user();
        $this->actingAs($user)->getJson(route('pms.time-tracking.session'))->assertOk();
        $tab = (string) Str::uuid();
        $this->beat($tab, true, true);
        $originalEligibleAt = DB::table('pm_workspace_sessions')->where('user_id', $user->id)->value('last_eligible_at');
        $this->travel(6)->minutes();
        $this->beat($tab, true, false);
        $this->assertSame($originalEligibleAt, DB::table('pm_workspace_sessions')->where('user_id', $user->id)->value('last_eligible_at'));
        $this->travel(54)->minutes();
        $this->postJson(route('pms.time-tracking.heartbeat'), [
            'tab_id' => $tab, 'visible' => true, 'focused' => true, 'interacted' => false,
        ])->assertUnauthorized();
    }

    public function test_active_second_tab_reconciles_idle_overlap_from_first_tab(): void
    {
        $user = $this->user();
        $this->actingAs($user)->getJson(route('pms.time-tracking.session'))->assertOk();
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();
        $this->beat($first, true, true);
        $this->beat($second, true, true);
        for ($i = 0; $i < 11; $i++) {
            $this->travel(30)->seconds();
            $this->beat($first, true, false);
            $this->beat($second, true, true);
        }
        $this->assertSame(330, (int) DB::table('pm_workspace_activity_intervals')->where('activity_status', 'active')->sum('duration_seconds'));
        $this->assertSame(0, (int) DB::table('pm_workspace_activity_intervals')->where('activity_status', 'idle')->sum('duration_seconds'));
    }

    public function test_project_owner_alone_cannot_read_reports_but_product_owner_member_can(): void
    {
        $admin = $this->user(true);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'OWNER', 'name' => 'Owner project', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::where('key', 'OWNER')->firstOrFail();
        $owner = $this->user();
        DB::table('pm_projects')->where('id', $project->id)->update(['owner_id' => $owner->id]);
        $this->actingAs($owner)->get(route('pms.time-tracking.index'))->assertForbidden();
        $project->members()->attach($owner->id, ['role' => 'Viewer']);
        $this->get(route('pms.time-tracking.index'))->assertForbidden();
        DB::table('pms_profiles')->where('user_id', $owner->id)->update(['job_position' => 'Product Owner']);
        $owner->unsetRelation('pmsProfile');
        $this->get(route('pms.time-tracking.index'))->assertOk();
    }

    public function test_manager_report_and_csv_are_limited_to_managed_projects(): void
    {
        $admin = $this->user(true);
        foreach (['NORTH', 'SOUTH'] as $key) {
            $this->actingAs($admin)->postJson(route('pms.projects.store'), [
                'key' => $key, 'name' => $key.' project', 'priority' => 'P3',
            ])->assertCreated();
        }
        $north = PmProject::where('key', 'NORTH')->firstOrFail();
        $south = PmProject::where('key', 'SOUTH')->firstOrFail();
        $manager = $this->user();
        $staff = $this->user();
        $north->members()->attach($manager->id, ['role' => 'Project Manager']);
        $this->actingAs($staff)->getJson(route('pms.time-tracking.session'))->assertOk();
        $sessionId = DB::table('pm_workspace_sessions')->where('user_id', $staff->id)->value('id');
        foreach ([$north, $south] as $project) DB::table('pm_workspace_activity_intervals')->insert([
            'session_id' => $sessionId, 'user_id' => $staff->id,
            'project_id' => $project->id, 'activity_status' => 'active',
            'activity_classification' => 'Project Activity',
            'activity_date' => now('Asia/Kuala_Lumpur')->toDateString(),
            'started_at' => now('UTC')->subMinute(), 'ended_at' => now('UTC'),
            'duration_seconds' => 60,
        ]);
        $this->actingAs($manager)->get(route('pms.time-tracking.index', ['range' => 'today']))
            ->assertOk()->assertSee('NORTH project')->assertDontSee('SOUTH project');
        $this->getJson(route('pms.time-tracking.index', ['range' => 'today']))
            ->assertOk()->assertJsonPath('totals.active_seconds', 60)->assertJsonPath('totals.projects', 1);
        $this->getJson(route('pms.time-tracking.employee', ['employee' => $staff->id, 'range' => 'today']))
            ->assertOk()->assertJsonCount(1, 'projects');
        $staff->update(['name' => '=EXPLOIT()']);
        $response = $this->get(route('pms.time-tracking.export', ['range' => 'today']));
        $response->assertOk();
        ob_start();
        $response->baseResponse->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('NORTH project', $csv);
        $this->assertStringNotContainsString('SOUTH project', $csv);
        $this->assertStringContainsString("'=EXPLOIT()", $csv);
        $this->get(route('pms.time-tracking.export', ['range' => 'today', 'format' => 'xlsx']))->assertOk();
        $this->actingAs($admin)->get(route('pms.time-tracking.index', ['range' => 'today']))
            ->assertOk()->assertSee('SOUTH project');
    }

    public function test_cycle_stages_are_independent_and_reopen_removes_completion(): void
    {
        $tickets = collect([
            (new PmWorkItem())->forceFill(['classification_key' => 'REQ', 'status' => 'In Progress']),
            (new PmWorkItem())->forceFill(['classification_key' => 'REQ', 'status' => 'Ready for QAT']),
            (new PmWorkItem())->forceFill(['classification_key' => 'ENH', 'status' => 'QAT in Progress']),
            (new PmWorkItem())->forceFill(['classification_key' => 'PRD', 'status' => 'QAT Done']),
            (new PmWorkItem())->forceFill(['classification_key' => 'REQ', 'status' => 'Production Done']),
        ]);
        $cycle = new PmCycle();
        $cycle->setRelation('issues', $tickets);
        $progress = app(PmsCycleProgress::class)->forCycle($cycle);
        $this->assertSame(80, $progress['development']['percent']);
        $this->assertSame(40, $progress['testing']['percent']);
        $this->assertSame(20, $progress['production']['percent']);
        $tickets[4]->status = 'Reopen';
        $progress = app(PmsCycleProgress::class)->forCycle($cycle);
        $this->assertSame(60, $progress['development']['percent']);
        $this->assertSame(20, $progress['testing']['percent']);
        $this->assertSame(0, $progress['production']['percent']);
        $cycle->setRelation('issues', collect([
            (new PmWorkItem())->forceFill(['classification_key' => 'QAT', 'status' => 'QAT Done']),
            (new PmWorkItem())->forceFill(['classification_key' => 'TSK', 'status' => 'Open']),
        ]));
        $progress = app(PmsCycleProgress::class)->forCycle($cycle);
        $this->assertSame(0, $progress['development']['percent']);
        $this->assertSame(100, $progress['testing']['percent']);
        $this->assertNull($progress['production']['percent']);
        $cycle->setRelation('issues', collect([
            (new PmWorkItem())->forceFill(['classification_key' => 'REQ', 'status' => 'Completed']),
            (new PmWorkItem())->forceFill(['classification_key' => 'QAT', 'status' => 'Verified']),
        ]));
        $progress = app(PmsCycleProgress::class)->forCycle($cycle);
        $this->assertSame(100, $progress['development']['percent']);
        $this->assertSame(100, $progress['testing']['percent']);
        $this->assertSame(100, $progress['production']['percent']);
    }

    public function test_cycle_progress_report_reads_current_ticket_state_and_exports_it(): void
    {
        $admin = $this->user(true);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'FLOW', 'name' => 'Flow project', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::where('key', 'FLOW')->firstOrFail();
        $this->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Flow cycle',
            'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $this->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id, 'cycle_id' => $cycle->id,
            'summary' => 'Flow requirement', 'priority' => 'P3', 'description' => 'Build the flow.',
            'business_justification' => 'Reduce errors.', 'acceptance_criteria' => 'The flow completes.',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        DB::table('pm_work_items')->where('id', $ticket->id)->update(['status' => 'Ready for QAT']);
        $this->get(route('pms.cycles.progress.index'))->assertOk()
            ->assertSee('Flow cycle')->assertSee('100%')->assertSee('0%');
        $this->get(route('pms.cycles.show', $cycle))->assertOk()->assertSee('Development progress');
        $response = $this->get(route('pms.cycles.progress.export'));
        $response->assertOk();
        ob_start();
        $response->baseResponse->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Flow requirement', $csv);
        $this->assertStringContainsString('Ready for QAT', $csv);
        $blockerId = DB::table('pm_ticket_blockers')->insertGetId([
            'issue_id' => $ticket->id, 'category' => 'Dependency', 'reason' => 'Blocked for review',
            'reported_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(0, app(PmsCycleProgress::class)->forCycle($cycle->fresh()->load('issues'))['development']['percent']);
        $this->get(route('pms.cycles.progress.index'))->assertOk()->assertSee('<b>1</b> currently blocked', false);
        DB::table('pm_ticket_blockers')->where('id', $blockerId)->update(['resolved_at' => now()]);
        $this->assertSame(100, app(PmsCycleProgress::class)->forCycle($cycle->fresh()->load('issues'))['development']['percent']);
        DB::table('pm_cycles')->where('id', $cycle->id)->update(['status' => 'Closed']);
        $this->assertSame('Attention Required', app(PmsCycleService::class)->analytics($cycle->fresh()->load('issues'))['health']);
        DB::table('pm_work_items')->where('id', $ticket->id)->update(['status' => 'Production Done']);
        $this->assertSame('Completed', app(PmsCycleService::class)->analytics($cycle->fresh()->load('issues'))['health']);
        DB::table('pm_work_items')->where('id', $ticket->id)->update(['status' => 'Ready for QAT']);
        $this->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Next cycle',
            'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-11-01',
        ])->assertCreated();
        $next = PmCycle::where('name', 'Next cycle')->firstOrFail();
        DB::table('pm_work_items')->where('id', $ticket->id)->update(['cycle_id' => $next->id]);
        $this->assertNull(app(PmsCycleProgress::class)->forCycle($cycle->fresh()->load('issues'))['development']['percent']);
        $this->assertSame(100, app(PmsCycleProgress::class)->forCycle($next->fresh()->load('issues'))['development']['percent']);
    }

    public function test_cycle_report_ticket_filters_use_the_same_ticket_for_display_and_export(): void
    {
        $admin = $this->user(true);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'FILTER', 'name' => 'Filter project', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::where('key', 'FILTER')->firstOrFail();
        $this->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Filter cycle',
            'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        foreach (['First requirement', 'Second requirement'] as $summary) {
            $this->postJson(route('pms.tickets.store'), [
                'classification_key' => 'REQ', 'project_id' => $project->id, 'cycle_id' => $cycle->id,
                'summary' => $summary, 'priority' => 'P3', 'description' => 'Implement the flow.',
                'business_justification' => 'Reduce errors.', 'acceptance_criteria' => 'The flow completes.',
            ])->assertCreated();
        }
        DB::table('pm_work_items')->where('summary', 'First requirement')->update(['status' => 'Ready for QAT']);
        $filtered = ['ticket_status' => 'Ready for QAT', 'ticket_type' => 'REQ'];
        $this->get(route('pms.cycles.progress.index', $filtered))->assertOk()
            ->assertSee('1 tickets')->assertSee('100%');
        $response = $this->get(route('pms.cycles.progress.export', $filtered))->assertOk();
        ob_start();
        $response->baseResponse->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('First requirement', $csv);
        $this->assertStringNotContainsString('Second requirement', $csv);
        $this->get(route('pms.cycles.progress.index', ['ticket_status' => 'Ready for QAT', 'ticket_type' => 'QAT']))
            ->assertOk()->assertDontSee('Filter cycle');
    }
}
