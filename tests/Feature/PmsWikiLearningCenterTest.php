<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmWikiPage;
use App\Models\PmWikiSpace;
use App\Models\PmsProfile;
use App\Models\User;
use Database\Seeders\PmsQaLearningCenterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsWikiLearningCenterTest extends TestCase
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

    public function test_articles_are_idempotent_and_workspace_readable_without_touching_existing_pages(): void
    {
        $admin = $this->member(1);
        $qa = $this->member();
        $outsider = $this->member();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'PGOS', 'name' => 'PGOS Personal Goal Operating System', 'priority' => 'P3',
        ])->assertCreated();
        $projectId = DB::table('pm_projects')->value('id');
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $projectId), [
            'user_id' => $qa->id, 'role' => 'QA Engineer',
        ])->assertOk();
        $space = PmWikiSpace::create(['key' => 'PGOS', 'name' => 'PGOS Knowledge Sharing',
            'visibility' => 'workspace', 'owner_id' => $admin->id]);
        $existing = PmWikiPage::create(['space_id' => $space->id, 'slug' => 'existing-notes',
            'title' => 'Existing team notes', 'content' => 'Keep this text', 'status' => 'published',
            'author_id' => $admin->id, 'last_editor_id' => $admin->id, 'published_at' => now()]);

        $this->seed(PmsQaLearningCenterSeeder::class);
        PmWikiPage::where('system_key', 'qa-learning-pms-collaboration')->update(['summary' => 'Reviewed team wording']);
        $this->seed(PmsQaLearningCenterSeeder::class);

        $this->assertSame(10, PmWikiPage::whereNotNull('system_key')->count());
        $this->assertSame('Keep this text', $existing->fresh()->content);
        $this->assertDatabaseHas('pm_wiki_pages', ['system_key' => 'qa-learning-pms-collaboration', 'summary' => 'Reviewed team wording']);
        $page = PmWikiPage::where('system_key', 'qa-learning-pgos-overview')->firstOrFail();
        $this->assertSame($projectId, $page->project_id);
        $this->assertSame('workspace', $page->access_scope);
        $this->assertSame('PGOS Product Knowledge', $page->category);
        $this->assertGreaterThan(600, str_word_count($page->content));
        $this->assertDatabaseHas('pm_wiki_revisions', ['page_id' => $page->id, 'project_id' => $projectId]);

        $this->actingAs($qa)->get(route('pms.wiki.index', ['category' => 'PGOS Product Knowledge']))
            ->assertOk()->assertSee('Understanding PGOS')->assertDontSee('How to Test PGOS');
        $this->actingAs($qa)->get(route('pms.wiki.pages.show', $page))->assertOk()->assertSee('Related articles');
        $this->actingAs($qa)->get(route('pms.projects.show', $projectId))->assertOk()->assertSee('Understanding PGOS');
        $this->actingAs($outsider)->get(route('pms.wiki.index', ['q' => 'Understanding PGOS']))
            ->assertOk()->assertSee('Understanding PGOS');
        $this->actingAs($outsider)->get(route('pms.wiki.spaces.show', $space))
            ->assertOk()->assertSee('Understanding PGOS');
        $this->actingAs($outsider)->get(route('pms.wiki.pages.show', $page))->assertOk()->assertSee('Related articles');
        $this->actingAs($outsider)->getJson(route('pms.wiki.pages.revisions', $page))->assertOk();
        $this->actingAs($outsider)->getJson(route('pms.projects.show', $projectId))->assertForbidden();

        $private = PmWikiPage::create(['space_id' => $space->id, 'project_id' => $projectId,
            'slug' => 'private-project-note', 'title' => 'Private project note', 'content' => 'Project-only information',
            'status' => 'published', 'author_id' => $admin->id, 'last_editor_id' => $admin->id,
            'published_at' => now()]);
        $this->assertSame('project', $private->fresh()->access_scope);
        $this->actingAs($qa)->getJson(route('pms.wiki.pages.show', $private))->assertOk();
        $this->actingAs($outsider)->get(route('pms.wiki.index', ['q' => 'Private project note']))
            ->assertOk()->assertSee('0 pages')->assertDontSee('<strong>Private project note', false);
        $this->actingAs($outsider)->getJson(route('pms.wiki.index', ['q' => 'Private project note']))
            ->assertOk()->assertJsonPath('pages.total', 0)->assertJsonMissing(['title' => 'Private project note']);
        $this->actingAs($outsider)->getJson(route('pms.wiki.pages.show', $private))->assertNotFound();
    }

    public function test_project_page_cannot_be_moved_to_workspace_visibility(): void
    {
        $admin = $this->member(1);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'PGOS', 'name' => 'Personal Goal Operating System', 'priority' => 'P3',
        ])->assertCreated();
        $this->seed(PmsQaLearningCenterSeeder::class);
        $page = PmWikiPage::whereNotNull('system_key')->firstOrFail();

        $this->actingAs($admin)->putJson(route('pms.wiki.pages.update', $page), [
            'space_id' => $page->space_id, 'project_id' => null, 'title' => $page->title,
            'content' => $page->content, 'status' => 'published', 'lock_version' => $page->lock_version,
        ])->assertUnprocessable()->assertJsonValidationErrors('project_id');
        $this->assertNotNull($page->fresh()->project_id);
    }
}
