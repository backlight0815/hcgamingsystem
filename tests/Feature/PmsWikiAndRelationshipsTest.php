<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmWikiPage;
use App\Models\PmWikiSpace;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PmsWikiAndRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function member(int $role = 3): User
    {
        $user = User::factory()->create(['role_id' => $role, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(),
            'can_create_all_tickets' => in_array($role, [1, 2], true)]);
        return $user;
    }

    public function test_wiki_pages_are_versioned_searchable_and_sanitized(): void
    {
        $author = $this->member(1);
        $this->actingAs($author)->postJson(route('pms.wiki.spaces.store'), [
            'key' => 'ENG', 'name' => 'Engineering', 'visibility' => 'workspace',
        ])->assertCreated();
        $space = PmWikiSpace::firstOrFail();
        $this->actingAs($author)->postJson(route('pms.wiki.pages.store'), [
            'space_id' => $space->id, 'title' => 'Deployment guide', 'content' => '# Safe\n<script>alert(1)</script>',
            'tags' => 'release, production', 'status' => 'published',
        ])->assertCreated();
        $page = PmWikiPage::firstOrFail();
        $this->actingAs($author)->get(route('pms.wiki.spaces.show', $space))->assertOk()->assertSee('Deployment guide');
        $this->actingAs($author)->get(route('pms.wiki.pages.create'))->assertOk();
        $this->actingAs($author)->get(route('pms.wiki.pages.edit', $page))->assertOk();
        $preview = $this->actingAs($author)->postJson(route('pms.wiki.pages.preview'), [
            'content' => '++Urgent++ <script>alert(1)</script> [unsafe](javascript:alert(1))',
        ])->assertOk()->json('html');
        $this->assertStringContainsString('<u>Urgent</u>', $preview);
        $this->assertStringNotContainsString('<script>', $preview);
        $this->assertStringNotContainsString('href="javascript:', $preview);
        $this->assertDatabaseHas('pm_wiki_revisions', ['page_id' => $page->id, 'version' => 1]);
        $this->assertStringNotContainsString('<script>', $page->safe_html);
        $this->actingAs($author)->get(route('pms.wiki.index', ['q' => 'Deployment']))->assertOk()->assertSee('Deployment guide');
        $this->actingAs($author)->putJson(route('pms.wiki.pages.update', $page), [
            'space_id' => $space->id, 'title' => 'Deployment guide v2', 'content' => 'Second revision',
            'tags' => 'release', 'status' => 'published', 'lock_version' => 1,
        ])->assertOk();
        $this->actingAs($author)->putJson(route('pms.wiki.pages.update', $page), [
            'space_id' => $space->id, 'title' => 'Stale edit', 'content' => 'Lost',
            'status' => 'published', 'lock_version' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('lock_version');
        $this->actingAs($author)->postJson(route('pms.wiki.pages.restore', [$page, 1]), ['lock_version' => 2])->assertOk();
        $this->assertSame('Deployment guide', $page->fresh()->title);
        $this->assertDatabaseHas('pm_wiki_revisions', ['page_id' => $page->id, 'version' => 3]);
    }

    public function test_restricted_space_is_hidden_from_nonmembers(): void
    {
        $owner = $this->member(1);
        $outsider = $this->member();
        $this->actingAs($owner)->postJson(route('pms.wiki.spaces.store'), [
            'key' => 'OPS', 'name' => 'Operations', 'visibility' => 'restricted',
        ])->assertCreated();
        $space = PmWikiSpace::firstOrFail();
        $this->actingAs($owner)->postJson(route('pms.wiki.pages.store'), [
            'space_id' => $space->id, 'title' => 'Private runbook', 'content' => 'Sensitive steps', 'status' => 'published',
        ])->assertCreated();
        $page = PmWikiPage::firstOrFail();
        $this->actingAs($outsider)->get(route('pms.wiki.index', ['q' => 'Private']))->assertOk()->assertDontSee('Private runbook');
        $this->actingAs($outsider)->getJson(route('pms.wiki.pages.show', $page))->assertNotFound();
        $this->actingAs($owner)->postJson(route('pms.wiki.spaces.members.store', $space), [
            'user_id' => $outsider->id, 'role' => 'viewer',
        ])->assertOk();
        $this->actingAs($outsider)->get(route('pms.wiki.pages.show', $page))->assertOk();
        $this->actingAs($outsider)->getJson(route('pms.wiki.pages.edit', $page))->assertForbidden();
    }

    public function test_wiki_attachment_is_private_and_download_requires_page_access(): void
    {
        Storage::fake('local');
        $owner = $this->member(1);
        $outsider = $this->member();
        $this->actingAs($owner)->postJson(route('pms.wiki.spaces.store'), [
            'key' => 'SAFE', 'name' => 'Private docs', 'visibility' => 'restricted',
        ])->assertCreated();
        $space = PmWikiSpace::firstOrFail();
        $this->actingAs($owner)->postJson(route('pms.wiki.pages.store'), [
            'space_id' => $space->id, 'title' => 'Runbook', 'content' => 'Details', 'status' => 'published',
        ])->assertCreated();
        $page = PmWikiPage::firstOrFail();
        $this->actingAs($owner)->postJson(route('pms.wiki.pages.attachments.store', $page), [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'safe notes'),
        ])->assertCreated();
        $file = DB::table('pm_wiki_attachments')->first();
        Storage::disk('local')->assertExists($file->storage_path);
        $this->actingAs($outsider)->getJson(route('pms.wiki.pages.attachments.show', [$page, $file->id]))->assertNotFound();
        $this->actingAs($owner)->get(route('pms.wiki.pages.attachments.show', [$page, $file->id]))->assertOk();
    }

    public function test_ticket_relationship_direction_cycles_and_activity(): void
    {
        $admin = $this->member(1);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'LINKS', 'name' => 'Relationships project', 'priority' => 'P3',
        ])->assertCreated();
        $projectId = DB::table('pm_projects')->value('id');
        foreach (['First', 'Second', 'Third'] as $summary) {
            $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
                'classification_key' => 'TSK', 'project_id' => $projectId, 'summary' => $summary, 'description' => 'A task', 'priority' => 'P3',
            ])->assertCreated();
        }
        [$a, $b, $c] = PmWorkItem::orderBy('id')->get()->all();
        $this->assertDatabaseHas('pm_ticket_activity', ['issue_id' => $a->id, 'event_type' => 'created', 'actor_id' => $admin->id]);
        $this->actingAs($admin)->putJson(route('pms.tickets.update', $a), [
            'lock_version' => $a->lock_version, 'priority' => 'Critical',
        ])->assertOk();
        $this->assertDatabaseHas('pm_ticket_activity', ['issue_id' => $a->id, 'event_type' => 'priority', 'old_value' => 'P3', 'new_value' => 'P1']);
        $this->actingAs($admin)->postJson(route('pms.tickets.links.store', $a), [
            'target_issue_key' => $b->issue_key, 'relation' => 'qat_dependency',
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.links.store', $b), [
            'target_issue_key' => $c->issue_key, 'relation' => 'qat_dependency',
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.links.store', $c), [
            'target_issue_key' => $a->issue_key, 'relation' => 'qat_dependency',
        ])->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('pms.tickets.links.store', $a), [
            'target_issue_key' => $b->issue_key, 'relation' => 'sub_issue',
        ])->assertOk();
        $this->assertSame($a->id, $b->fresh()->parent_id);
        $this->actingAs($admin)->postJson(route('pms.tickets.links.store', $b), [
            'target_issue_key' => $a->issue_key, 'relation' => 'sub_issue',
        ])->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('pms.tickets.links.store', $b), [
            'target_issue_key' => $c->issue_key, 'relation' => 'related_to',
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.links.store', $c), [
            'target_issue_key' => $b->issue_key, 'relation' => 'related_to',
        ])->assertUnprocessable();
        $linkId = DB::table('pm_ticket_links')->where('relation', 'sub_issue')->value('id');
        $this->actingAs($admin)->deleteJson(route('pms.tickets.links.destroy', [$a, $linkId]))->assertOk();
        $this->assertNull($b->fresh()->parent_id);
        $this->assertGreaterThan(0, DB::table('pm_ticket_activity')->where('issue_id', $a->id)->where('event_type', 'relationships')->count());
        $this->actingAs($admin)->get(route('pms.tickets.show', [$a, 'activity' => 'relationships']))->assertOk()->assertSee('QAT dependencies');
    }

    public function test_unresolved_dependency_blocks_qat_retest(): void
    {
        $admin = $this->member(1);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'GATE', 'name' => 'QA gate', 'priority' => 'P3',
        ])->assertCreated();
        $projectId = DB::table('pm_projects')->value('id');
        $developer = $this->member();
        $developer->pmsProfile->update(['job_position' => 'Developer']);
        $qa = $this->member();
        $qa->pmsProfile->update(['job_position' => 'QA Engineer']);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $projectId), ['user_id' => $developer->id, 'role' => 'Developer'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $projectId), ['user_id' => $qa->id, 'role' => 'QA Engineer'])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'TSK', 'project_id' => $projectId, 'summary' => 'Backend fix', 'description' => 'Implement fix', 'priority' => 'P3',
        ])->assertCreated();
        $blocker = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'project_id' => $projectId, 'summary' => 'Verify fix', 'priority' => 'P3',
            'reproduction_steps' => 'Run a test', 'expected_result' => 'Pass', 'actual_result' => 'Fail',
            'testing_environment' => 'QAT', 'defect_type' => 'Functional bug',
            'developer_id' => $developer->id, 'qa_id' => $qa->id,
        ])->assertCreated();
        $qat = PmWorkItem::orderByDesc('id')->firstOrFail();
        $qat->status = 'Fixed'; $qat->lock_version++;
        $qat->save();
        $this->actingAs($admin)->postJson(route('pms.tickets.links.store', $qat), [
            'target_issue_key' => $blocker->issue_key, 'relation' => 'qat_dependency',
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.transition', $qat), [
            'status' => 'Ready for Retest', 'lock_version' => $qat->lock_version,
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
        $blocker->status = 'Completed'; $blocker->save();
        $this->actingAs($admin)->postJson(route('pms.tickets.transition', $qat), [
            'status' => 'Ready for Retest', 'lock_version' => $qat->lock_version,
        ])->assertOk();
    }
}
