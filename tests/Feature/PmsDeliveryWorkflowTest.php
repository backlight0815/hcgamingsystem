<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmRelease;
use App\Models\PmsProfile;
use App\Models\PmTicketAttachment;
use App\Models\PmTicketComment;
use App\Models\PmWorkItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PmsDeliveryWorkflowTest extends TestCase
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
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(),
            'job_position' => match ($role) { 900 => 'Developer', 901 => 'QA Engineer', default => null },
            'can_create_all_tickets' => in_array($role, [1, 2], true),
            'workspace_role' => in_array($role, [1, 2], true) ? null : 'QA']);

        return $user;
    }

    private function project(User $admin): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => 'QAT', 'name' => 'Quality workspace', 'priority' => 'P2'])->assertCreated();

        return PmProject::where('key', 'QAT')->firstOrFail();
    }

    private function qat(array $attributes = []): array
    {
        return array_merge([
            'classification_key' => 'QAT', 'summary' => 'Checkout fails on mobile', 'priority' => 'P1',
            'defect_type' => 'Functional bug', 'reproduction_steps' => 'Open checkout and submit.',
            'expected_result' => 'Order accepted.', 'actual_result' => 'Server returns 500.',
            'testing_environment' => 'Staging',
        ], $attributes);
    }

    public function test_qat_requires_an_assigned_project_and_remains_private(): void
    {
        $admin = $this->staff();
        $reporter = $this->staff(900);
        $outsider = $this->staff(901);
        $project = $this->project($admin);
        $this->actingAs($admin)->get(route('pms.tickets.create'))->assertOk()->assertSee('Create a ticket');
        $this->actingAs($reporter)->postJson(route('pms.tickets.store'), $this->qat())
            ->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $reporter->id, 'role' => 'QA',
        ])->assertOk();
        $developer = $this->staff(900);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $developer->id, 'role' => 'Developer',
        ])->assertOk();
        $this->actingAs($reporter)->postJson(route('pms.tickets.store'), $this->qat([
            'project_id' => $project->id, 'developer_id' => $developer->id,
        ]))
            ->assertCreated()->assertJsonPath('reference', 'sfqat0001');
        $ticket = PmWorkItem::where('issue_key', 'QAT-00001')->firstOrFail();
        $this->assertSame($project->id, $ticket->project_id);
        $this->actingAs($outsider)->getJson(route('pms.tickets.show', $ticket))->assertNotFound();
        $this->actingAs($reporter)->get(route('pms.tickets.show', $ticket))->assertOk()->assertSee('Checkout fails on mobile');
        $this->actingAs($outsider)->getJson(route('pms.tickets.show', $ticket))->assertNotFound();
        $this->actingAs($admin)->get(route('pms.tickets.index', ['category' => 'QAT']))->assertOk()->assertSee('sfqat0001');
    }

    public function test_private_evidence_comments_and_qa_verification_are_recorded(): void
    {
        Storage::fake('local');
        $admin = $this->staff();
        $developer = $this->staff(900);
        $qa = $this->staff(901);
        $outsider = $this->staff(902);
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $developer->id, 'role' => 'Developer'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $qa->id, 'role' => 'QA Engineer'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->qat([
            'project_id' => $project->id, 'developer_id' => $developer->id, 'qa_id' => $qa->id,
            'attachments' => [UploadedFile::fake()->image('failure.png')],
        ]))->assertCreated()->assertJsonPath('issue_key', 'QAT-00001');
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($qa)->get(route('pms.tickets.index', ['mine' => 'all']))->assertOk()->assertSee('sfqat0001');
        $this->actingAs($developer)->get(route('pms.tickets.index', ['mine' => 'all']))->assertOk()->assertSee('sfqat0001');
        $attachment = PmTicketAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->actingAs($outsider)->getJson(route('pms.tickets.attachments.show', [$ticket, $attachment]))->assertNotFound();
        $this->actingAs($qa)->get(route('pms.tickets.attachments.show', [$ticket, $attachment]))->assertOk();
        $this->actingAs($qa)->get(route('pms.tickets.attachments.show', [$ticket, $attachment, 'preview' => 1]))->assertOk()->assertHeader('content-type', 'image/png');
        $this->actingAs($qa)->postJson(route('pms.tickets.comments.store', $ticket), [
            'body' => 'Reproduced. <script>alert(1)</script>',
            'attachments' => [
                UploadedFile::fake()->createWithContent('cases.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
                UploadedFile::fake()->image('reproduced.png'),
            ],
        ])->assertCreated();
        $this->assertDatabaseHas('pm_ticket_attachments', ['issue_id' => $ticket->id, 'original_name' => 'cases.pdf']);
        $pdf = PmTicketAttachment::where('original_name', 'cases.pdf')->firstOrFail();
        $this->actingAs($qa)->get(route('pms.tickets.attachments.show', [$ticket, $pdf, 'preview' => 1]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin)->get(route('pms.tickets.show', $ticket))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('Comment #')->assertSee('cases.pdf');
        $this->actingAs($admin)->get(route('pms.tickets.show', [$ticket, 'activity' => 'comments']))
            ->assertOk()->assertSee('Comment added')->assertDontSee('Evidence attached');
        $this->actingAs($admin)->get(route('pms.tickets.show', [$ticket, 'activity' => 'attachments']))
            ->assertOk()->assertSee('Evidence attached')->assertDontSee('Comment added');
        $comment = PmTicketComment::firstOrFail();
        $this->actingAs($qa)->putJson(route('pms.tickets.comments.update', [$ticket, $comment]), [
            'body' => 'Reproduced. @'.$outsider->username.' should not see this.',
        ])->assertOk();
        $this->assertDatabaseHas('pm_comment_revisions', ['comment_id' => $comment->id, 'event' => 'edited']);
        $this->actingAs($qa)->postJson(route('pms.tickets.comments.store', $ticket), [
            'body' => 'Private QA update for @'.$outsider->username,
        ])->assertCreated();
        $this->assertDatabaseMissing('app_notifications', ['user_id' => $outsider->id]);

        foreach (['In Progress', 'Code Review', 'Ready for QAT', 'QAT in Progress'] as $status) {
            $this->actingAs($admin)->postJson(route('pms.tickets.transition', $ticket), [
                'status' => $status, 'lock_version' => $ticket->fresh()->lock_version,
            ])->assertOk();
        }
        $this->assertSame('QAT in Progress', $ticket->fresh()->status);
        $this->actingAs($developer)->postJson(route('pms.tickets.verify', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'result' => 'Passed', 'build' => '2.1.0',
            'environment' => 'Staging', 'remarks' => 'Works now.',
        ])->assertForbidden();
        $this->actingAs($qa)->postJson(route('pms.tickets.verify', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'result' => 'Failed', 'build' => '2.1.0',
            'environment' => 'Staging', 'remarks' => 'Still fails.',
        ])->assertOk()->assertJsonPath('status', 'Reopen');
        $this->assertSame(1, $ticket->fresh()->reopen_count);
        $this->actingAs($admin)->postJson(route('pms.tickets.transition', $ticket), ['status' => 'In Progress', 'lock_version' => $ticket->fresh()->lock_version])->assertOk();
        foreach (['Code Review', 'Ready for QAT', 'QAT in Progress'] as $status) {
            $this->actingAs($admin)->postJson(route('pms.tickets.transition', $ticket), ['status' => $status, 'lock_version' => $ticket->fresh()->lock_version])->assertOk();
        }
        $this->actingAs($qa)->postJson(route('pms.tickets.verify', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'result' => 'Passed', 'build' => '2.1.1',
            'environment' => 'Staging', 'remarks' => 'Verified and regression checked.',
            'attachments' => [UploadedFile::fake()->image('verified.png')],
        ])->assertOk()->assertJsonPath('status', 'QAT Done');
        $this->assertDatabaseCount('pm_qa_verifications', 2);
        $this->assertDatabaseCount('pm_ticket_attachments', 4);
        $this->actingAs($admin)->postJson(route('pms.tickets.transition', $ticket), [
            'status' => 'Production Done', 'lock_version' => $ticket->fresh()->lock_version,
        ])->assertOk()->assertJsonPath('status', 'Production Done');
    }

    public function test_office_evidence_is_validated_and_removed_from_private_storage(): void
    {
        Storage::fake('local');
        $admin = $this->staff();
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->qat(['project_id' => $project->id]))->assertCreated();
        $ticket = PmWorkItem::firstOrFail();

        config()->set('pms.attachments.other_max_kb', 1);
        $this->actingAs($admin)->postJson(route('pms.tickets.attachments.store', $ticket), [
            'attachments' => [UploadedFile::fake()->createWithContent('oversized.txt', str_repeat('x', 2048))],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->actingAs($admin)->postJson(route('pms.tickets.attachments.store', $ticket), [
            'attachments' => [UploadedFile::fake()->createWithContent('broken.docx', 'not a real archive')],
        ])->assertUnprocessable()->assertJsonValidationErrors('attachments');

        $path = tempnam(sys_get_temp_dir(), 'pms-office-');
        $archive = new \ZipArchive();
        $this->assertTrue($archive->open($path, \ZipArchive::OVERWRITE) === true);
        $archive->addFromString('[Content_Types].xml', '<Types/>');
        $archive->addFromString('word/document.xml', '<document/>');
        $archive->close();
        try {
            $this->actingAs($admin)->postJson(route('pms.tickets.attachments.store', $ticket), [
                'attachments' => [
                    new UploadedFile($path, 'retest.docx', null, null, true),
                    new UploadedFile($path, 'bundle.zip', null, null, true),
                    UploadedFile::fake()->image('first.png'),
                    UploadedFile::fake()->image('second.png'),
                ],
            ])->assertCreated();
        } finally {
            unlink($path);
        }
        $attachment = PmTicketAttachment::firstOrFail();
        $this->assertDatabaseCount('pm_ticket_attachments', 4);
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->actingAs($admin)->deleteJson(route('pms.tickets.attachments.destroy', [$ticket, $attachment]))->assertOk();
        Storage::disk('local')->assertMissing($attachment->storage_path);
        $this->assertSoftDeleted($attachment);
    }

    public function test_release_requires_gates_and_records_approval_and_deployment(): void
    {
        $admin = $this->staff();
        $developer = $this->staff(900);
        $qa = $this->staff(901);
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $developer->id, 'role' => 'Developer'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $qa->id, 'role' => 'QA Engineer'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'October delivery', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $this->actingAs($admin)->get(route('pms.releases.index'))->assertOk()->assertSee('Release owner');
        $this->actingAs($admin)->postJson(route('pms.releases.store'), [
            'project_id' => $project->id, 'cycle_id' => $cycle->id, 'name' => 'October launch',
            'version' => '2.1.0', 'planned_deploy_date' => today()->toDateString(),
        ])->assertCreated()->assertJsonPath('release_key', 'QAT-REL-001');
        $release = PmRelease::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id, 'release_id' => $release->id,
            'summary' => 'Add trading analytics', 'priority' => 'P2', 'description' => 'New analytics panel.',
            'business_justification' => 'Support user decisions.', 'acceptance_criteria' => 'Charts render correctly.',
            'developer_id' => $developer->id, 'qa_id' => $qa->id, 'tags' => 'Trading Journal, Analytics',
            'due_date' => today()->addMonth()->toDateString(),
        ])->assertCreated()->assertJsonPath('issue_key', 'REQ-00001');
        $requirement = PmWorkItem::where('issue_key', 'REQ-00001')->firstOrFail();
        $this->assertSame(['Trading Journal', 'Analytics'], $requirement->tags);
        $this->assertSame($cycle->id, $requirement->cycle_id);
        foreach (['Open', 'In Progress', 'Ready for Internal Testing', 'Internal Testing', 'QAT Testing', 'Completed'] as $status) {
            $this->actingAs($admin)->postJson(route('pms.tickets.transition', $requirement), [
                'status' => $status, 'lock_version' => $requirement->fresh()->lock_version,
                'reason' => $status === 'Completed' ? 'Internal and QAT checks passed.' : null,
            ])->assertOk();
        }
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->qat([
            'project_id' => $project->id, 'release_id' => $release->id, 'developer_id' => $developer->id,
            'qa_id' => $qa->id, 'linked_requirement_key' => 'REQ-00001',
        ]))->assertCreated();
        $defect = PmWorkItem::where('issue_key', 'QAT-00001')->firstOrFail();
        $this->assertDatabaseHas('pm_ticket_links', ['source_issue_id' => $defect->id, 'target_issue_id' => $requirement->id, 'relation' => 'tests_requirement']);
        foreach (['In Progress', 'Code Review', 'Ready for QAT', 'QAT in Progress'] as $status) {
            $this->actingAs($admin)->postJson(route('pms.tickets.transition', $defect), ['status' => $status, 'lock_version' => $defect->fresh()->lock_version])->assertOk();
        }
        $this->travel(2)->hours();
        $this->actingAs($qa)->postJson(route('pms.tickets.verify', $defect), [
            'lock_version' => $defect->fresh()->lock_version, 'result' => 'Passed', 'build' => '2.1.0',
            'environment' => 'QAT', 'remarks' => 'Regression passed.',
        ])->assertOk();
        $this->actingAs($admin)->get(route('pms.releases.show', $release))->assertOk()->assertSee('Readiness checks');
        $this->actingAs($admin)->get(route('pms.releases.summary', $release))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($admin)->postJson(route('pms.releases.advance', $release), ['status' => 'Development'])->assertOk();
        foreach (['Internal Testing', 'QAT Testing', 'Stabilization'] as $stage) {
            $this->actingAs($admin)->postJson(route('pms.releases.advance', $release), ['status' => $stage])->assertOk();
            $release->refresh();
        }
        $this->actingAs($admin)->postJson(route('pms.releases.advance', $release), ['status' => 'Ready for Release'])->assertUnprocessable();
        DB::table('pm_cycle_phases')->where('cycle_id', $cycle->id)->whereIn('phase_key', ['development', 'internal_testing', 'qat'])->update(['signed_off' => true]);
        $this->actingAs($admin)->putJson(route('pms.releases.update', $release), ['release_notes' => 'Deployment notes'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.releases.advance', $release), ['status' => 'Ready for Release'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.releases.approve', $release))->assertOk();
        $this->actingAs($admin)->postJson(route('pms.releases.deploy', $release), [
            'deployed_build' => '2.1.0+8', 'actual_deployed_at' => now()->toDateTimeString(),
        ])->assertOk()->assertJsonPath('status', 'Released');
        $this->actingAs($admin)->postJson(route('pms.releases.complete', $release))->assertOk()->assertJsonPath('status', 'Completed');
        $this->assertDatabaseHas('pm_release_history', ['release_id' => $release->id, 'event' => 'deployed']);
        $snapshot = json_decode(DB::table('pm_release_history')->where('release_id', $release->id)->where('event', 'deployed')->value('details'), true);
        $this->assertSame(1, $snapshot['readiness']['metrics']['qat_verified']);
        $this->actingAs($admin)->putJson(route('pms.tickets.update', $defect), [
            'lock_version' => $defect->fresh()->lock_version, 'release_id' => null, 'reason' => 'Move after deployment',
        ])->assertUnprocessable()->assertJsonValidationErrors('release_id');
        $this->actingAs($admin)->get(route('pms.releases.notes.download', $release))->assertOk();
        $this->actingAs($admin)->getJson(route('pms.dashboard'))
            ->assertOk()->assertJsonPath('delivery.qat_verified', 1)
            ->assertJsonPath('delivery.releases_completed', 1)
            ->assertJsonPath('delivery.tickets_by_category.REQ', 1)
            ->assertJsonPath('statistics.0.value', '100%')
            ->assertJsonPath('statistics.2.value', '100%')
            ->assertJsonPath('statistics.3.value', '100%')
            ->assertJsonPath('statistics.4.value', '2 h')
            ->assertJsonPath('statistics.5.value', '2 h')
            ->assertJsonPath('statistics.6.value', '100%');
        $this->travelBack();
    }

    public function test_ticket_csv_respects_membership_and_filters(): void
    {
        $admin = $this->staff();
        $reporter = $this->staff(900);
        $outsider = $this->staff(901);
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $reporter->id, 'role' => 'QA Engineer'])->assertOk();
        $developer = $this->staff(900);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $developer->id, 'role' => 'Developer'])->assertOk();
        $this->actingAs($reporter)->postJson(route('pms.tickets.store'), $this->qat([
            'project_id' => $project->id, 'developer_id' => $developer->id,
        ]))->assertCreated();
        $export = $this->actingAs($admin)->get(route('pms.tickets.export', ['category' => 'QAT']))->assertOk();
        $this->assertStringContainsString('sfqat0001', $export->streamedContent());
        $privateExport = $this->actingAs($outsider)->get(route('pms.tickets.export', ['category' => 'QAT']))->assertOk();
        $this->assertStringNotContainsString('sfqat0001', $privateExport->streamedContent());
    }

    public function test_defect_types_are_admin_managed_and_dangerous_evidence_is_rejected(): void
    {
        $admin = $this->staff();
        $member = $this->staff(900);
        $project = $this->project($admin);
        $this->actingAs($member)->postJson(route('pms.settings.defects.store'), ['name' => 'Accessibility issue'])->assertForbidden();
        $this->actingAs($admin)->postJson(route('pms.settings.defects.store'), ['name' => 'Accessibility issue'])->assertOk();
        $this->actingAs($admin)->get(route('pms.settings'))->assertOk()->assertSee('Accessibility issue');
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->qat([
            'project_id' => $project->id, 'defect_type' => 'Accessibility issue',
            'attachments' => [UploadedFile::fake()->createWithContent('payload.exe', 'MZ fake')],
        ]))->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->assertDatabaseCount('pm_work_items', 0);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->qat(['project_id' => $project->id, 'defect_type' => 'Accessibility issue']))->assertCreated();
        $typeId = DB::table('pm_defect_types')->where('name', 'Accessibility issue')->value('id');
        $this->actingAs($admin)->deleteJson(route('pms.settings.defects.destroy', $typeId))->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->qat(['project_id' => $project->id, 'defect_type' => 'Accessibility issue']))->assertUnprocessable()->assertJsonValidationErrors('defect_type');
    }

    public function test_release_moves_keep_ticket_identity_and_require_reason(): void
    {
        $admin = $this->staff();
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Hotfix cycle', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        foreach (['2.1.0', '2.1.1'] as $version) {
            $this->actingAs($admin)->postJson(route('pms.releases.store'), [
                'project_id' => $project->id, 'cycle_id' => $cycle->id, 'name' => $version,
                'version' => $version, 'release_type' => 'hotfix', 'planned_deploy_date' => '2026-11-01',
            ])->assertCreated();
        }
        $first = PmRelease::where('version', '2.1.0')->firstOrFail();
        $second = PmRelease::where('version', '2.1.1')->firstOrFail();
        $this->assertSame('hotfix', $first->release_type);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->qat(['project_id' => $project->id, 'release_id' => $first->id]))->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.releases.tickets.store', $second), ['ticket_id' => $ticket->id])->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('pms.releases.tickets.store', $second), ['ticket_id' => $ticket->id, 'reason' => 'Carry over unresolved defect.'])->assertOk();
        $this->assertSame($second->id, $ticket->fresh()->release_id);
        $this->assertSame(1, $ticket->fresh()->lock_version);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'release_changed']);
        $this->assertDatabaseCount('pm_work_items', 1);
    }

    public function test_release_deadline_reminders_are_private_and_idempotent(): void
    {
        $admin = $this->staff();
        $manager = $this->staff(900);
        $outsider = $this->staff(901);
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), ['user_id' => $manager->id, 'role' => 'Release Manager'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Upcoming cycle', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => today()->toDateString(),
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.releases.store'), [
            'project_id' => $project->id, 'cycle_id' => $cycle->id, 'name' => 'Upcoming release',
            'version' => '3.0.0', 'owner_id' => $manager->id,
            'planned_deploy_date' => today()->addDays(3)->toDateString(),
        ])->assertCreated();
        $this->artisan('pms:release-reminders')->assertExitCode(0);
        $this->artisan('pms:release-reminders')->assertExitCode(0);
        $this->assertDatabaseHas('pm_release_reminders', ['user_id' => $manager->id]);
        $this->assertDatabaseMissing('pm_release_reminders', ['user_id' => $outsider->id]);
        $this->assertDatabaseCount('pm_release_reminders', 2);
    }
}
