<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmsAutomationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function member(?string $position = null, bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? 1 : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'job_position' => $position]);
        return $user;
    }

    private function token(User $user): string
    {
        return $user->createToken('pms-test', ['pms'], now()->addHour())->plainTextToken;
    }

    public function test_project_api_persists_and_reads_updates_then_soft_deletes(): void
    {
        $admin = $this->member(admin: true);
        $this->withToken($this->token($admin));
        $url = route('api.pms.v1.projects.updateInfo');
        $this->postJson($url, ['action' => 'insert', 'project_key' => 'AUTO', 'name' => 'Automation project',
            'description' => 'Original text', 'priority' => 'high'])->assertCreated()
            ->assertJsonPath('code', 'PROJECT_CREATED')->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status', 'Planned')->assertJsonPath('data.status_code', 2);
        $project = PmProject::where('key', 'AUTO')->firstOrFail();
        $this->getJson(route('api.pms.v1.projects.getInfo', ['project_key' => 'AUTO']))->assertOk()
            ->assertJsonPath('data.name', 'Automation project');
        $this->postJson($url, ['action' => 'update', 'project_id' => $project->id,
            'expected_version' => $project->lock_version, 'name' => 'Updated through API',
            'priority' => 'critical', 'status_code' => 3])
            ->assertOk()->assertJsonPath('data.name', 'Updated through API')
            ->assertJsonPath('data.status', 'Active')->assertJsonPath('data.status_code', 3);
        $this->getJson(route('api.pms.v1.projects.getInfo', ['project_id' => $project->id]))->assertOk()
            ->assertJsonPath('data.description', 'Original text')->assertJsonPath('data.priority', 'critical');
        $this->postJson($url, ['action' => 'update', 'project_id' => $project->id,
            'expected_version' => 0, 'name' => 'Stale'])->assertStatus(409)->assertJsonPath('code', 'CONFLICT');
        $this->postJson($url, ['action' => 'update', 'project_id' => $project->id,
            'status_code' => 999, 'name' => 'Invalid'])->assertUnprocessable();
        $this->postJson($url, ['action' => 'delete', 'project_id' => $project->id])->assertOk()
            ->assertJsonPath('code', 'PROJECT_DELETED');
        $this->assertSoftDeleted('pm_projects', ['id' => $project->id]);
        $this->getJson(route('api.pms.v1.projects.getInfo', ['project_id' => $project->id]))->assertNotFound();
    }

    public function test_ticket_api_and_position_scoped_assignment(): void
    {
        $admin = $this->member(admin: true);
        $developer = $this->member('Developer');
        $qa = $this->member('QA Engineer');
        $wrong = $this->member('Business Analyst');
        $this->withToken($this->token($admin));
        $this->postJson(route('api.pms.v1.projects.updateInfo'), ['action' => 'insert', 'project_key' => 'TAPI',
            'name' => 'Ticket API', 'priority' => 'normal'])->assertCreated();
        $project = PmProject::where('key', 'TAPI')->firstOrFail();
        $project->members()->attach([$developer->id => ['role' => 'Developer'],
            $qa->id => ['role' => 'QA'], $wrong->id => ['role' => 'Business Analyst']]);
        $this->getJson(route('api.pms.v1.assignableUsers', ['assignment_type' => 'developer', 'project_id' => $project->id]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $developer->id);
        $this->getJson(route('api.pms.v1.assignableUsers', ['assignment_type' => 'qa', 'project_id' => $project->id]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $qa->id);
        $this->getJson(route('api.pms.v1.assignableUsers', ['assignment_type' => 'qa', 'project_id' => $project->id,
            'search' => 'QA Engineer']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $qa->id);
        $url = route('api.pms.v1.tickets.updateInfo');
        $payload = ['action' => 'insert', 'project_id' => $project->id, 'ticket_type' => 'REQ',
            'title' => 'API requirement', 'description' => 'A real requirement', 'priority' => 'normal',
            'business_justification' => 'Business value', 'acceptance_criteria' => 'Accepted',
            'developer_assignee_id' => $developer->id, 'qa_assignee_id' => $qa->id];
        $this->postJson($url, $payload)->assertCreated()->assertJsonPath('code', 'TICKET_CREATED');
        $ticket = PmWorkItem::firstOrFail();
        $this->postJson($url, ['action' => 'update', 'ticket_id' => $ticket->id,
            'developer_assignee_id' => $wrong->id])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->postJson($url, ['action' => 'update', 'ticket_id' => $ticket->id,
            'title' => 'Updated requirement', 'priority' => 'high'])->assertOk()->assertJsonPath('data.title', 'Updated requirement');
        $this->getJson(route('api.pms.v1.projects.tickets', ['project_id' => $project->id]))
            ->assertOk()->assertJsonPath('data.items.0.title', 'Updated requirement')
            ->assertJsonPath('data.items.0.status_code', 2);
        $this->getJson(route('api.pms.v1.projects.tickets', ['project_id' => $project->id, 'status_code' => 2]))
            ->assertOk()->assertJsonCount(1, 'data.items');
        $this->postJson($url, ['action' => 'delete', 'ticket_id' => $ticket->id])->assertOk();
        $this->assertSoftDeleted('pm_work_items', ['id' => $ticket->id]);
    }

    public function test_bearer_auth_and_permissions_are_required(): void
    {
        $member = $this->member('Developer');
        $url = route('api.pms.v1.projects.updateInfo');
        $this->postJson($url, ['action' => 'insert'])->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->withToken($this->token($member))->postJson($url, ['action' => 'insert',
            'project_key' => 'NOPE', 'name' => 'Forbidden', 'priority' => 'high'])->assertForbidden();
    }

    public function test_expired_bearer_token_is_rejected(): void
    {
        $admin = $this->member(admin: true);
        $expired = $admin->createToken('expired', ['pms'], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson(route('api.pms.v1.projects.getInfo', ['project_id' => 1]))->assertUnauthorized();
    }
}
