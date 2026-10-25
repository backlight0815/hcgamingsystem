<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsOverviewRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
        $this->travelTo(Carbon::parse('2026-10-21 10:00:00'));
    }

    private function person(bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? 1 : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'workspace_role' => $admin ? null : 'Developer']);

        return $user;
    }

    private function project(User $owner, string $key, string $status = 'Active'): PmProject
    {
        return PmProject::create([
            'key' => $key, 'name' => $key.' project', 'status' => $status, 'priority' => 'P2',
            'owner_id' => $owner->id, 'created_by' => $owner->id,
        ]);
    }

    private function ticket(PmProject $project, User $reporter, string $key, string $status, ?string $completed = null, ?string $due = null): PmWorkItem
    {
        return PmWorkItem::forceCreate([
            'project_id' => $project->id, 'issue_key' => $key, 'reference' => strtolower($key),
            'classification_key' => 'REQ', 'summary' => $key.' summary', 'priority' => 'P2',
            'reporter_id' => $reporter->id, 'status' => $status, 'completed_at' => $completed,
            'due_date' => $due,
        ]);
    }

    public function test_command_center_metrics_charts_blockers_and_releases_use_scoped_live_data(): void
    {
        $admin = $this->person(admin: true);
        $member = $this->person();
        $visible = $this->project($admin, 'VISIBLE');
        $private = $this->project($admin, 'PRIVATE');
        $visible->members()->attach($member->id, ['role' => 'Developer']);

        $this->ticket($visible, $admin, 'SFREQ0001', 'Production Done', '2026-10-20 09:00:00', '2026-10-20');
        $open = $this->ticket($visible, $admin, 'SFREQ0002', 'In Progress', null, '2026-10-23');
        $open->forceFill(['developer_id' => $member->id, 'qa_id' => $member->id, 'priority' => 'P1', 'classification_key' => 'PRD'])->save();
        DB::table('pm_ticket_contributors')->insert([
            'issue_id' => $open->id, 'user_id' => $member->id, 'assignment_type' => 'developer',
            'assigned_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->ticket($private, $admin, 'SFREQ0003', 'Completed', '2026-10-20 09:00:00');
        $this->ticket($private, $admin, 'SFREQ0004', 'In Progress', null, '2026-10-23');
        DB::table('pm_ticket_blockers')->insert([
            'issue_id' => $open->id, 'category' => 'Dependency', 'reason' => 'External API blocked',
            'reported_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $release = PmRelease::forceCreate([
            'project_id' => $visible->id, 'release_key' => 'VISIBLE-REL-001', 'name' => 'Visible release',
            'version' => '1.0', 'planned_start_date' => '2026-10-01', 'planned_deploy_date' => '2026-10-28',
            'created_by' => $admin->id, 'status' => 'Development',
        ]);

        $this->actingAs($member)->get(route('pms.dashboard', ['period' => 7]))
            ->assertOk()
            ->assertViewHas('counts', fn ($counts) => $counts['active_projects'] === 1
                && $counts['production_done'] === 1 && $counts['due_week'] === 1
                && $counts['open'] === 1 && $counts['production_incidents'] === 1
                && $counts['critical_blockers'] === 1 && $counts['on_time_percent'] === 100)
            ->assertViewHas('classifications', fn ($rows) => collect($rows)->firstWhere('label', 'Production issues')['count'] === 1)
            ->assertViewHas('health', fn ($health) => $health['active_percent'] === 100)
            ->assertViewHas('releases', fn ($rows) => $rows->count() === 1 && $rows->first()['release']->id === $release->id)
            ->assertSee('VISIBLE project')
            ->assertSee('Ticket classification')
            ->assertSee('Upcoming release readiness')
            ->assertSee(route('pms.tickets.show', $open->reference), false)
            ->assertDontSee('PRIVATE project')
            ->assertDontSee('New project');

        $this->actingAs($member)->get(route('pms.dashboard', ['project' => $private->id]))->assertForbidden();
        $this->actingAs($member)->get(route('pms.dashboard', ['project' => $visible->id, 'period' => 30]))
            ->assertOk()->assertViewHas('counts', fn ($counts) => $counts['production_done'] === 1 && $counts['open'] === 1);
        $this->actingAs($admin)->get(route('pms.dashboard'))->assertOk()
            ->assertSee(route('pms.cycles.index'), false);
    }

    public function test_command_center_excludes_archived_deleted_and_reopened_work_and_handles_empty_scopes(): void
    {
        $admin = $this->person(admin: true);
        $member = $this->person();
        $active = $this->project($admin, 'ACTIVE');
        $archived = $this->project($admin, 'ARCHIVED');
        $archived->forceFill(['archived_at' => now()])->save();
        $active->members()->attach($member->id, ['role' => 'Viewer']);

        $reopened = $this->ticket($active, $admin, 'SFREQ0010', 'Reopen', '2026-10-20 09:00:00');
        $deleted = $this->ticket($active, $admin, 'SFREQ0011', 'Completed', '2026-10-20 09:00:00');
        $deleted->delete();
        $this->ticket($archived, $admin, 'SFREQ0012', 'Completed', '2026-10-20 09:00:00');

        $this->actingAs($member)->get(route('pms.dashboard'))
            ->assertOk()
            ->assertViewHas('counts', fn ($counts) => $counts['production_done'] === 0 && $counts['open'] === 1)
            ->assertSee('No active or upcoming releases in this view')
            ->assertSee('No critical-priority tickets are blocked')
            ->assertDontSee('ARCHIVED project');
        $this->actingAs($member)->get(route('pms.dashboard', ['period' => 8]))
            ->assertSessionHasErrors('period');
        $this->assertNotNull($reopened->fresh()->completed_at);
    }

    public function test_operational_report_renders_live_responsive_summary_and_scoped_csv_export(): void
    {
        $admin = $this->person(admin: true);
        $member = $this->person();
        $visible = $this->project($admin, 'VISIBLE');
        $private = $this->project($admin, 'PRIVATE');
        $visible->members()->attach($member->id, ['role' => 'Viewer']);
        $this->ticket($visible, $admin, 'SFREQ0101', 'Production Done', '2026-10-20 09:00:00');
        $this->ticket($visible, $admin, 'SFREQ0102', 'In Progress');
        $this->ticket($private, $admin, 'SFREQ0103', 'Production Done', '2026-10-20 09:00:00');

        $this->actingAs($member)->get(route('pms.reports', ['project' => $visible->id, 'period' => 30]))
            ->assertOk()
            ->assertSee('pms-report-design.css')
            ->assertSee('Performance snapshot')
            ->assertSee('50%')
            ->assertSee('Detailed analysis')
            ->assertSee(route('pms.reports.export', ['project' => $visible->id, 'period' => 30]))
            ->assertDontSee('PRIVATE project');

        $export = $this->actingAs($member)->get(route('pms.reports.export', ['project' => $visible->id, 'period' => 30]));
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $export->streamedContent();
        $this->assertStringContainsString('VISIBLE project', $csv);
        $this->assertStringContainsString('"Delivery rate",50%', $csv);
        $this->assertStringNotContainsString('PRIVATE project', $csv);
        $this->actingAs($member)->get(route('pms.reports.export', ['project' => $private->id]))->assertForbidden();
    }
}
