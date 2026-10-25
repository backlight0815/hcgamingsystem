<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmTicketAttachment;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class PmsTestCaseEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
        Storage::fake('local');
    }

    private function member(string $role = 'QA', bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? 1 : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'workspace_role' => $role]);
        return $user;
    }

    private function project(User $admin, string $key = 'CASE'): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => $key, 'name' => $key.' project', 'priority' => 'P2',
        ])->assertCreated();
        return PmProject::where('key', $key)->firstOrFail();
    }

    private function assign(User $admin, PmProject $project, User $user, string $role): void
    {
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $user->id, 'role' => $role,
        ])->assertOk();
    }

    private function ticket(User $admin, PmProject $project, string $title = 'Test requirements'): PmWorkItem
    {
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id, 'summary' => $title,
            'description' => 'Detailed requirements', 'business_justification' => 'Needed for testing',
            'acceptance_criteria' => 'The flow completes', 'priority' => 'P3',
            'creation_token' => (string) Str::uuid(),
        ])->assertCreated();
        return PmWorkItem::latest('id')->firstOrFail();
    }

    private function workbook(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'case_xlsx_');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>');
        $zip->close();
        $file = UploadedFile::fake()->createWithContent('cases.xlsx', file_get_contents($path));
        unlink($path);
        return $file;
    }

    public function test_optional_creation_and_private_test_case_uploads_for_ticket_types(): void
    {
        $admin = $this->member(admin: true);
        $qa = $this->member();
        $outsider = $this->member();
        $project = $this->project($admin);
        $this->assign($admin, $project, $qa, 'QA');
        $ticket = $this->ticket($admin, $project);
        $this->assertDatabaseMissing('pm_ticket_attachments', ['issue_id' => $ticket->id]);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'ENH', 'project_id' => $project->id,
            'summary' => 'Enhancement without reviewer', 'priority' => 'P3',
            'current_behavior' => 'Manual', 'proposed_behavior' => 'Automated',
            'acceptance_criteria' => 'The process works', 'creation_token' => (string) Str::uuid(),
        ])->assertCreated();
        $this->assertDatabaseMissing('pm_test_case_reviews', ['issue_id' => PmWorkItem::latest('id')->value('id')]);

        $ticket->forceFill(['classification_key' => 'QAT'])->save();
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [$this->workbook()],
        ])->assertCreated();
        $xlsx = PmTicketAttachment::where('issue_id', $ticket->id)->where('category', 'test_case')->firstOrFail();
        Storage::disk('local')->assertExists($xlsx->storage_path);
        $this->assertSame(1, $xlsx->version);
        $this->actingAs($qa)->get(route('pms.tickets.test-cases.show', [$ticket, $xlsx]))->assertOk();
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'replaces_attachment_id' => $xlsx->id,
            'test_cases' => [UploadedFile::fake()->createWithContent('revised.csv', "case,result\n1,pass")],
        ])->assertCreated();
        $replacement = PmTicketAttachment::where('replaces_attachment_id', $xlsx->id)->firstOrFail();
        $this->assertSame(2, $replacement->version);
        $this->assertNotNull($xlsx->fresh()->removed_at);
        Storage::disk('local')->assertExists($xlsx->storage_path);
        $this->actingAs($outsider)->get(route('pms.tickets.test-cases.show', [$ticket, $xlsx]))->assertNotFound();
        $this->actingAs($outsider)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('other.csv', "case,result\n1,pass")],
        ])->assertNotFound();

        $ticket->forceFill(['classification_key' => 'PRD'])->save();
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('cases.csv', "case,result\n1,pass")],
        ])->assertCreated();
        $ticket->forceFill(['classification_key' => 'REQ'])->save();
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('a.txt', 'First case'),
                UploadedFile::fake()->createWithContent('b.csv', "case,result\n2,pass")],
        ])->assertCreated();
        $this->assertSame(5, PmTicketAttachment::where('issue_id', $ticket->id)->where('category', 'test_case')->count());
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('unsafe.php', '<?php echo 1;')],
        ])->assertUnprocessable();
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('huge.txt', str_repeat('a', 20 * 1024 * 1024 + 1))],
        ])->assertUnprocessable()->assertJsonValidationErrors('test_cases');
    }

    public function test_review_decisions_revision_and_approval_history_are_independent(): void
    {
        $admin = $this->member(admin: true);
        $qa = $this->member();
        $reviewer = $this->member('QA Lead');
        $other = $this->member();
        $project = $this->project($admin);
        foreach ([[$qa, 'QA'], [$reviewer, 'QA Lead'], [$other, 'QA']] as [$user, $role]) {
            $this->assign($admin, $project, $user, $role);
        }
        $ticket = $this->ticket($admin, $project);
        $ticketStatus = $ticket->status;
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('review.csv', "case,result\n1,pass")],
        ])->assertCreated();
        $this->actingAs($qa)->putJson(route('pms.tickets.test-case-review.assign', $ticket), [
            'test_case_reviewer_id' => $qa->id,
        ])->assertOk();
        $this->actingAs($qa)->postJson(route('pms.tickets.test-case-review.decide', $ticket), [
            'action' => 'approve',
        ])->assertForbidden();
        $this->actingAs($qa)->putJson(route('pms.tickets.test-case-review.assign', $ticket), [
            'test_case_reviewer_id' => $reviewer->id,
        ])->assertOk()->assertJsonPath('status', 'pending_review');
        $this->actingAs($other)->postJson(route('pms.tickets.test-case-review.decide', $ticket), [
            'action' => 'approve',
        ])->assertForbidden();
        $this->actingAs($reviewer)->postJson(route('pms.tickets.test-case-review.decide', $ticket), [
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('status', 'approved');
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('more.txt', 'Additional case')],
        ])->assertCreated();
        $this->assertDatabaseHas('pm_test_case_reviews', ['issue_id' => $ticket->id, 'status' => 'pending_review']);
        $this->assertDatabaseHas('pm_test_case_review_events', ['action' => 'approve', 'new_status' => 'approved']);
        $this->actingAs($reviewer)->postJson(route('pms.tickets.test-case-review.decide', $ticket), [
            'action' => 'request_changes',
        ])->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->actingAs($reviewer)->postJson(route('pms.tickets.test-case-review.decide', $ticket), [
            'action' => 'request_changes', 'comment' => 'Add a boundary case for invalid input.',
        ])->assertOk()->assertJsonPath('status', 'changes_requested');
        $this->actingAs($qa)->postJson(route('pms.tickets.test-case-review.decide', $ticket), [
            'action' => 'submit_revision',
        ])->assertStatus(409);
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('boundary.txt', 'Invalid input case')],
        ])->assertCreated();
        $this->actingAs($qa)->postJson(route('pms.tickets.test-case-review.decide', $ticket), [
            'action' => 'submit_revision',
        ])->assertOk()->assertJsonPath('status', 'pending_review');
        $this->assertSame($ticketStatus, $ticket->fresh()->status);
    }

    public function test_csv_table_sorting_and_malaysia_time_use_real_ticket_values(): void
    {
        $admin = $this->member(admin: true);
        $qa = $this->member();
        $project = $this->project($admin);
        $otherProject = $this->project($admin, 'OTH');
        $this->assign($admin, $project, $qa, 'QA');
        $ticket = $this->ticket($admin, $project, '=特殊, “title”');
        $ticket->forceFill(['classification_key' => 'QAT', 'description' => "第一行, quoted \"value\"\n=second line"])->save();
        $outside = $this->ticket($admin, $otherProject, 'Outside ticket');
        $this->assertSame('09:15 PM MYT', \App\Support\PmsDateTime::time(\Carbon\Carbon::parse('2026-10-24 13:15:00', 'UTC')));
        // The existing app stores Eloquent timestamps in APP_TIMEZONE (MYT).
        DB::table('pm_work_items')->where('id', $ticket->id)->update(['updated_at' => '2026-10-24 21:15:00']);
        DB::table('pm_work_items')->where('id', $outside->id)->update(['updated_at' => '2026-10-24 22:00:00']);
        $html = $this->actingAs($qa)->get(route('pms.tickets.index', ['category' => 'QAT']))->assertOk()->getContent();
        preg_match_all('/\d\d:\d\d [AP]M MYT/', $html, $times);
        $this->assertSame('09:15 PM MYT', $times[0][0] ?? 'none');
        $this->assertStringContainsString('=特殊, “title”', $html);
        $csv = $this->actingAs($qa)->get(route('pms.tickets.export', ['category' => 'QAT']))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=特殊", $csv);
        $this->assertStringContainsString('第一行', $csv);
        $this->assertStringContainsString('09:15 PM MYT', $csv);
        $this->assertStringNotContainsString('Outside ticket', $csv);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $columns = fgetcsv($stream);
        $values = fgetcsv($stream);
        $this->assertFalse(fgetcsv($stream));
        fclose($stream);
        $row = array_combine($columns, $values);
        $this->assertSame($ticket->reference, $row['Ticket ID']);
        $this->assertSame("第一行, quoted \"value\"\n=second line", $row['Description']);
        $this->actingAs($admin)->getJson(route('pms.tickets.index', ['sort_by' => 'updated_at', 'sort_order' => 'asc']))
            ->assertOk()->assertJsonPath('data.0.id', $ticket->id);
    }

    public function test_delete_permissions_confirmation_and_restore_keep_history(): void
    {
        $admin = $this->member(admin: true);
        $pm = $this->member('Project Manager');
        $unrelatedPm = $this->member('Project Manager');
        $qa = $this->member();
        $developer = $this->member('Developer');
        $ba = $this->member('Business Analyst');
        $owner = $this->member('Viewer');
        $project = $this->project($admin);
        $this->assign($admin, $project, $pm, 'Project Manager');
        foreach ([[$qa, 'QA'], [$developer, 'Developer'], [$ba, 'Business Analyst']] as [$user, $role]) {
            $this->assign($admin, $project, $user, $role);
        }
        $ticket = $this->ticket($admin, $project);
        $project->forceFill(['owner_id' => $owner->id])->save();
        $this->actingAs($qa)->postJson(route('pms.tickets.test-cases.store', $ticket), [
            'test_cases' => [UploadedFile::fake()->createWithContent('retained.txt', 'Retained test case')],
        ])->assertCreated();
        $file = PmTicketAttachment::where('issue_id', $ticket->id)->where('category', 'test_case')->firstOrFail();
        $this->actingAs($qa)->putJson(route('pms.tickets.test-case-review.assign', $ticket), [
            'test_case_reviewer_id' => $qa->id,
        ])->assertOk();
        $this->actingAs($owner)->get(route('pms.tickets.index'))->assertOk()->assertSee($ticket->reference);
        $this->actingAs($qa)->get(route('pms.tickets.show', $ticket))->assertOk()->assertDontSee('data-delete-open');
        $this->actingAs($ba)->get(route('pms.issues.show', $ticket))->assertOk()->assertDontSee('data-delete-open');
        $this->actingAs($pm)->get(route('pms.issues.show', $ticket))->assertOk()->assertSee('data-delete-open');
        $this->actingAs($owner)->get(route('pms.tickets.show', $ticket))->assertOk()->assertSee('data-delete-open');
        foreach ([$qa, $developer, $ba, $unrelatedPm] as $user) {
            $this->actingAs($user)->deleteJson(route('pms.tickets.destroy', $ticket), [
                'confirmation' => $ticket->reference,
            ])->assertForbidden();
        }
        $this->actingAs($ba)->deleteJson(route('pms.issues.destroy', $ticket), [
            'confirmation' => $ticket->reference,
        ])->assertForbidden();
        $this->actingAs($pm)->deleteJson(route('pms.tickets.destroy', $ticket), [
            'confirmation' => 'wrong-id',
        ])->assertUnprocessable();
        $this->actingAs($owner)->deleteJson(route('pms.tickets.destroy', $ticket), [
            'confirmation' => $ticket->reference,
        ])->assertOk();
        $this->assertSoftDeleted('pm_work_items', ['id' => $ticket->id, 'deleted_by' => $owner->id]);
        $this->actingAs($owner)->get(route('pms.tickets.index'))->assertOk()->assertDontSee($ticket->reference);
        $this->actingAs($owner)->get(route('pms.tickets.deleted'))->assertOk()->assertSee($ticket->reference);
        $this->actingAs($qa)->postJson(route('pms.tickets.restore', $ticket->id))->assertForbidden();
        $this->actingAs($pm)->postJson(route('pms.tickets.restore', $ticket->id))->assertOk();
        $this->actingAs($qa)->get(route('pms.tickets.test-cases.show', [$ticket, $file]))->assertOk();
        $this->assertDatabaseHas('pm_test_case_reviews', ['issue_id' => $ticket->id, 'reviewer_id' => $qa->id]);
        Storage::disk('local')->assertExists($file->storage_path);
        $this->assertDatabaseHas('pm_audit_logs', ['subject_id' => $ticket->id, 'action' => 'ticket.restored']);
    }
}
