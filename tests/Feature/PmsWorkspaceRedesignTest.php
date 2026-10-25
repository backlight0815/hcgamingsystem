<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmTicketAttachment;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use App\Support\PmsProjectDescription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmsWorkspaceRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function actor(?string $role = null, bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? 1 : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'workspace_role' => $role]);
        return $user;
    }

    private function project(User $admin, string $key = 'DES'): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => $key, 'name' => 'Design project', 'priority' => 'P2'])->assertCreated();
        return PmProject::where('key', $key)->firstOrFail();
    }

    private function assign(User $admin, PmProject $project, User $actor, string $role): void
    {
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $actor->id, 'role' => $role])->assertOk();
    }

    public function test_markdown_and_rich_paste_render_safely(): void
    {
        $markdown = PmsProjectDescription::render("## Summary\n\nOne paragraph.\n\n- First\n- Second\n\n1. Numbered\n\n[Safe](https://example.com)");
        $this->assertStringContainsString('<h2>Summary</h2>', $markdown);
        $this->assertStringContainsString('<ul>', $markdown);
        $this->assertStringContainsString('<ol>', $markdown);
        $this->assertStringContainsString('<p>One paragraph.</p>', $markdown);
        $pasted = PmsProjectDescription::canonicalize('<h2>Paste</h2><p><strong>Bold</strong> text</p><ul><li>Item</li></ul><script>alert(1)</script>');
        $this->assertStringContainsString('## Paste', $pasted);
        $this->assertStringContainsString('**Bold**', $pasted);
        $html = PmsProjectDescription::render($pasted.' [Bad](javascript:alert(1))');
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }

    public function test_scoped_project_edits_and_dashboard_navigation(): void
    {
        $admin = $this->actor(admin: true);
        $ba = $this->actor('Business Analyst');
        $pm = $this->actor('Project Manager');
        $qa = $this->actor('QA');
        $project = $this->project($admin);
        $other = $this->project($admin, 'OTH');
        $this->assign($admin, $project, $ba, 'Business Analyst');
        $this->assign($admin, $project, $pm, 'Project Manager');
        $this->assign($admin, $project, $qa, 'QA');
        $reviewId = $this->actingAs($ba)->putJson(route('pms.projects.update', $project), [
            'lock_version' => $project->lock_version, 'name' => 'Edited project', 'status' => 'Active',
            'priority' => 'P1', 'description' => "## Updated\n\n- one",
        ])->assertStatus(202)->json('review_id');
        $this->assertSame('Design project', $project->fresh()->name);
        $this->actingAs($pm)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Approved project edits',
        ])->assertOk();
        $this->actingAs($ba)->get(route('pms.projects.show', $project))->assertOk()
            ->assertSee('<h2>Updated</h2>', false)->assertSee('Create Ticket')->assertSee('project-panel-attachments');
        $this->actingAs($qa)->putJson(route('pms.projects.update', $project), ['lock_version' => 1, 'name' => 'No', 'status' => 'Active', 'priority' => 'P2'])->assertForbidden();
        $this->actingAs($ba)->putJson(route('pms.projects.update', $other), ['lock_version' => 0, 'name' => 'No', 'status' => 'Active', 'priority' => 'P2'])->assertForbidden();
        $deleteId = $this->actingAs($ba)->deleteJson(route('pms.projects.trash', $project), ['confirmation' => $project->key])->assertStatus(202)->json('review_id');
        $this->actingAs($pm)->postJson(route('pms.change-reviews.decide', $deleteId), [
            'decision' => 'rejected', 'comment' => 'Keep this project',
        ])->assertOk();
        $this->assertNull($project->fresh()->deleted_at);
        $this->assertDatabaseHas('pm_audit_logs', ['actor_id' => $ba->id, 'action' => 'review.submitted', 'subject_id' => $project->id]);
        $this->assertDatabaseHas('pm_audit_logs', ['actor_id' => $pm->id, 'action' => 'project.updated', 'subject_id' => $project->id]);
    }

    public function test_analyst_updates_ticket_cycle_and_release_but_cannot_approve_or_delete(): void
    {
        $admin = $this->actor(admin: true);
        $pm = $this->actor('Project Manager');
        $ba = $this->actor('Business Analyst');
        $project = $this->project($admin);
        $this->assign($admin, $project, $pm, 'Project Manager');
        $this->assign($admin, $project, $ba, 'Business Analyst');
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), ['project_id' => $project->id, 'name' => 'Original cycle', 'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-10-01'])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $cycleUpdate = [
            'lock_version' => $cycle->lock_version, 'project_id' => $project->id, 'name' => 'Updated cycle',
            'week_mode' => 'calendar', 'start_date' => '2026-10-01', 'target_end_date' => '2026-10-28',
            'development_days' => 14, 'internal_testing_days' => 7, 'qat_days' => 7, 'buffer_days' => 0,
        ];
        $this->actingAs($ba)->putJson(route('pms.cycles.update', $cycle), $cycleUpdate)->assertForbidden();
        $this->actingAs($admin)->putJson(route('pms.cycles.update', $cycle), $cycleUpdate)->assertOk();
        $this->actingAs($admin)->postJson(route('pms.releases.store'), [
            'project_id' => $project->id, 'cycle_id' => $cycle->id, 'name' => 'Original release',
            'version' => '1.0', 'planned_deploy_date' => '2026-10-28',
        ])->assertCreated();
        $release = PmRelease::firstOrFail();
        $this->actingAs($ba)->putJson(route('pms.releases.update', $release), ['name' => 'Updated release'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id, 'summary' => 'Original ticket',
            'priority' => 'P3', 'description' => 'Draft', 'business_justification' => 'Need it',
            'acceptance_criteria' => 'Works', 'creation_token' => (string) Str::uuid(),
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $reviewId = $this->actingAs($ba)->putJson(route('pms.tickets.update', $ticket), ['lock_version' => $ticket->lock_version, 'summary' => 'Updated ticket', 'priority' => 'P2'])->assertStatus(202)->json('review_id');
        $this->actingAs($pm)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Approved requirement update',
        ])->assertOk();
        $this->actingAs($ba)->deleteJson(route('pms.tickets.destroy', $ticket), ['confirmation' => $ticket->issue_key])->assertForbidden();
        $this->assertNull($ticket->fresh()->deleted_at);
        $this->actingAs($ba)->postJson(route('pms.releases.advance', $release), ['status' => 'Development'])->assertForbidden();
        $this->assertSame('Updated cycle', $cycle->fresh()->name);
        $this->assertSame('Updated release', $release->fresh()->name);
        $this->assertSame('Updated ticket', $ticket->fresh()->summary);
    }

    public function test_cycle_directory_search_and_view_modes_use_stored_records(): void
    {
        $admin = $this->actor(admin: true);
        $project = $this->project($admin);
        foreach (['Alpha planning', 'Beta delivery'] as $name) {
            $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
                'project_id' => $project->id, 'name' => $name, 'week_mode' => 'calendar',
                'template_weeks' => 4, 'start_date' => $name === 'Alpha planning' ? '2026-10-01' : '2026-11-01',
            ])->assertCreated();
        }
        $this->actingAs($admin)->get(route('pms.cycles.index', ['q' => 'Alpha', 'view' => 'table']))->assertOk()
            ->assertSee('Alpha planning')->assertDontSee('Beta delivery')->assertSee('Total cycles');
        $this->actingAs($admin)->get(route('pms.cycles.index', ['view' => 'cards']))->assertOk()
            ->assertSee('Alpha planning')->assertSee('Beta delivery')->assertSee('pm-cycle-dashboard-card');
        $this->actingAs($admin)->get(route('pms.cycles.index', ['sort' => 'progress', 'date_from' => '2026-10-01', 'date_to' => '2026-12-01']))
            ->assertOk()->assertSee('Alpha planning')->assertSee('Beta delivery');
    }

    public function test_analyst_and_manager_edit_all_ticket_categories_and_scoped_project_relationships(): void
    {
        $admin = $this->actor(admin: true);
        $pm = $this->actor('Project Manager');
        $ba = $this->actor('Business Analyst');
        $qa = $this->actor('QA');
        $source = $this->project($admin);
        $target = $this->project($admin, 'DST');
        foreach ([$source, $target] as $project) {
            $this->assign($admin, $project, $pm, 'Project Manager');
            $this->assign($admin, $project, $ba, 'Business Analyst');
        }
        $this->assign($admin, $source, $qa, 'QA');
        $extra = [
            'REQ' => ['description' => 'Initial requirement', 'business_justification' => 'Business need', 'acceptance_criteria' => 'Accepted'],
            'ENH' => ['current_behavior' => 'Old', 'proposed_behavior' => 'New', 'acceptance_criteria' => 'Accepted'],
            'PRD' => ['detected_at' => '2026-10-18 10:00:00', 'business_impact' => 'Outage', 'production_environment' => 'Production', 'actual_result' => 'Failure'],
            'QAT' => ['defect_type' => 'Functional bug', 'reproduction_steps' => 'Reproduce', 'expected_result' => 'Works', 'actual_result' => 'Fails', 'testing_environment' => 'Staging'],
        ];
        foreach ($extra as $category => $fields) {
            $this->actingAs($admin)->postJson(route('pms.tickets.store'), array_merge([
                'classification_key' => $category, 'project_id' => $source->id,
                'summary' => $category.' original', 'priority' => 'P3', 'creation_token' => (string) Str::uuid(),
            ], $fields))->assertCreated();
            $ticket = PmWorkItem::where('classification_key', $category)->firstOrFail();
            $analystResponse = $this->actingAs($ba)->putJson(route('pms.tickets.update', $ticket), [
                'lock_version' => $ticket->lock_version, 'summary' => $category.' analyst update',
                'priority' => 'P2', 'estimate_minutes' => 90,
            ]);
            if (in_array($category, ['REQ', 'ENH'], true)) {
                $analystResponse->assertStatus(202);
                $this->actingAs($pm)->postJson(route('pms.change-reviews.decide', $analystResponse->json('review_id')), [
                    'decision' => 'approved', 'comment' => 'Approved analyst edits',
                ])->assertOk();
            } else {
                $analystResponse->assertOk();
            }
            $ticket->refresh();
            $this->actingAs($pm)->putJson(route('pms.tickets.update', $ticket), [
                'lock_version' => $ticket->lock_version, 'summary' => $category.' manager update',
            ])->assertOk();
            $this->actingAs($qa)->putJson(route('pms.tickets.update', $ticket), [
                'lock_version' => $ticket->fresh()->lock_version, 'summary' => 'Unauthorized',
            ])->assertForbidden();
        }
        $qat = PmWorkItem::where('classification_key', 'QAT')->firstOrFail();
        $this->actingAs($ba)->putJson(route('pms.tickets.update', $qat), [
            'lock_version' => $qat->lock_version, 'defect_type' => 'Functional bug',
            'reproduction_steps' => 'Updated steps', 'expected_result' => 'Updated expectation',
        ])->assertOk();
        $this->actingAs($ba)->putJson(route('pms.tickets.update', $qat->fresh()), [
            'lock_version' => $qat->fresh()->lock_version, 'project_id' => $target->id,
            'reason' => 'Moved into the delivery project',
        ])->assertOk();
        $this->assertSame($target->id, $qat->fresh()->project_id);
        $this->assertSame('Updated steps', $qat->fresh()->reproduction_steps);
    }

    public function test_private_image_thumbnails_and_pdf_preview_keep_authorization(): void
    {
        Storage::fake('local');
        $admin = $this->actor(admin: true);
        $viewer = $this->actor('Viewer');
        $outsider = $this->actor('Viewer');
        $project = $this->project($admin);
        $this->assign($admin, $project, $viewer, 'Viewer');
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id, 'summary' => 'Evidence ticket',
            'priority' => 'P3', 'description' => 'Draft', 'business_justification' => 'Need it',
            'acceptance_criteria' => 'Works', 'creation_token' => (string) Str::uuid(),
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.attachments.store', $ticket), [
            'attachments' => [UploadedFile::fake()->image('evidence.png', 40, 30)],
        ])->assertCreated();
        $image = PmTicketAttachment::firstOrFail();
        $this->actingAs($viewer)->get(route('pms.tickets.attachments.thumbnail', [$ticket, $image]))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs($outsider)->getJson(route('pms.tickets.attachments.thumbnail', [$ticket, $image]))->assertNotFound();
        $this->actingAs($viewer)->get(route('pms.tickets.attachments.show', [$ticket, $image]))->assertOk()->assertDownload('evidence.png');
        $this->actingAs($admin)->postJson(route('pms.projects.attachments.store', $project), [
            'ticket_key' => $ticket->issue_key, 'attachments' => [UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n1 0 obj\n")],
        ])->assertCreated();
        $pdf = PmTicketAttachment::latest('id')->firstOrFail();
        $this->actingAs($viewer)->get(route('pms.tickets.attachments.show', [$ticket, $pdf, 'preview' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($viewer)->get(route('pms.projects.show', $project))->assertOk()
            ->assertSee('report.pdf')->assertSee('pm-gallery-icon pdf');
    }

    public function test_manager_edits_project_cycle_and_release_while_developer_is_denied_release_edit(): void
    {
        $admin = $this->actor(admin: true);
        $manager = $this->actor('Project Manager');
        $developer = $this->actor('Developer');
        $project = $this->project($admin);
        $this->assign($admin, $project, $manager, 'Project Manager');
        $this->assign($admin, $project, $developer, 'Developer');
        $this->actingAs($manager)->putJson(route('pms.projects.update', $project), [
            'lock_version' => $project->lock_version, 'name' => 'Manager planned',
            'status' => 'Active', 'priority' => 'P2',
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Manager cycle', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $this->actingAs($manager)->putJson(route('pms.cycles.update', $cycle), [
            'lock_version' => $cycle->lock_version, 'project_id' => $project->id,
            'name' => 'Revised manager cycle', 'week_mode' => 'calendar',
            'start_date' => '2026-10-01', 'target_end_date' => '2026-10-28',
            'development_days' => 14, 'internal_testing_days' => 7, 'qat_days' => 7, 'buffer_days' => 0,
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.releases.store'), [
            'project_id' => $project->id, 'cycle_id' => $cycle->id,
            'name' => 'Manager release', 'version' => '1.0', 'planned_deploy_date' => '2026-10-28',
        ])->assertCreated();
        $release = PmRelease::firstOrFail();
        $this->actingAs($manager)->putJson(route('pms.releases.update', $release), ['name' => 'Revised manager release'])->assertOk();
        $this->actingAs($developer)->putJson(route('pms.releases.update', $release), ['name' => 'Unauthorized'])->assertForbidden();
        $this->assertSame('Manager planned', $project->fresh()->name);
        $this->assertSame('Revised manager cycle', $cycle->fresh()->name);
        $this->assertSame('Revised manager release', $release->fresh()->name);
    }

    public function test_jpeg_thumbnail_and_cycle_without_phases(): void
    {
        Storage::fake('local');
        $admin = $this->actor(admin: true);
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Sparse cycle', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $cycle->phases()->delete();
        $this->actingAs($admin)->get(route('pms.cycles.show', $cycle))->assertOk()
            ->assertSee('No phases are configured')->assertSee('No work data');
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id, 'summary' => 'JPEG evidence',
            'priority' => 'P3', 'description' => 'Draft', 'business_justification' => 'Need it',
            'acceptance_criteria' => 'Works', 'creation_token' => (string) Str::uuid(),
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.attachments.store', $ticket), [
            'attachments' => [UploadedFile::fake()->image('photo.jpg', 48, 32)],
        ])->assertCreated();
        $image = PmTicketAttachment::firstOrFail();
        $this->actingAs($admin)->get(route('pms.tickets.attachments.thumbnail', [$ticket, $image]))
            ->assertOk()->assertHeader('Content-Type', 'image/webp');
    }
}
