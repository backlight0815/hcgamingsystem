<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmTicketComment;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PmsProfileConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role_id' => 1, 'status' => 1, 'password' => Hash::make('CurrentPass123')]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'job_position' => 'Administrator']);
        return $user;
    }

    public function test_profile_updates_personal_data_without_changing_position_or_role(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('pms.my-profile.show'))->assertOk()->assertSee('My Profile');
        $this->actingAs($admin)->patch(route('pms.my-profile.update'), [
            'name' => 'Profile Owner', 'email' => 'profile-owner@example.com', 'phone' => '+65 1234 5678',
            'job_title' => 'Senior Platform Engineer',
            'job_position' => 'Developer', 'workspace_role' => 'Project Manager',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Profile Owner', 'email' => 'profile-owner@example.com']);
        $this->assertDatabaseHas('pms_profiles', ['user_id' => $admin->id, 'phone' => '+65 1234 5678',
            'job_title' => 'Senior Platform Engineer', 'job_position' => 'Administrator', 'workspace_role' => null]);
        $this->actingAs($admin)->get(route('pms.my-profile.show'))->assertOk()->assertSee('Senior Platform Engineer');
        $this->actingAs($admin)->patch(route('pms.my-profile.update'), [
            'name' => 'Profile Owner', 'email' => 'profile-owner@example.com',
        ])->assertRedirect();
        $this->assertDatabaseHas('pms_profiles', ['user_id' => $admin->id, 'phone' => '+65 1234 5678',
            'job_title' => 'Senior Platform Engineer']);
        $this->actingAs($admin)->put(route('pms.my-profile.password'), [
            'current_password' => 'CurrentPass123', 'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect();
        $this->assertTrue(Hash::check('NewPassword123', $admin->fresh()->password));
    }

    public function test_discussion_replies_are_ticket_scoped_and_persisted(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => 'COMM', 'name' => 'Conversation', 'priority' => 'P3'])->assertCreated();
        $project = PmProject::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id, 'summary' => 'Discuss scope', 'priority' => 'P3',
            'description' => 'Scope', 'business_justification' => 'Need it', 'acceptance_criteria' => 'Done',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.comments.store', $ticket), ['body' => '**First** comment'])->assertCreated();
        $root = PmTicketComment::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.comments.store', $ticket), [
            'parent_id' => $root->id, 'body' => 'A threaded reply',
        ])->assertCreated();
        $this->actingAs($admin)->get(route('pms.tickets.show', $ticket))->assertOk()
            ->assertSee('A threaded reply')->assertSee('data-pms-tab="activity"', false)
            ->assertSee('data-pms-tab="attachments"', false);
        $this->assertDatabaseHas('pm_ticket_comments', ['issue_id' => $ticket->id, 'parent_id' => $root->id, 'body' => 'A threaded reply']);
        $this->assertDatabaseHas('pm_ticket_activity', ['issue_id' => $ticket->id, 'event_type' => 'comments']);
    }

    public function test_my_work_only_shows_assigned_tickets_in_visible_projects(): void
    {
        $admin = $this->admin();
        $developer = User::factory()->create(['status' => 1]);
        PmsProfile::create(['user_id' => $developer->id, 'status' => 'active', 'job_position' => 'Developer']);
        $this->actingAs($admin)->postJson(route('pms.projects.store'), ['key' => 'WORK', 'name' => 'Work project', 'priority' => 'P3'])->assertCreated();
        $project = PmProject::firstOrFail();
        $project->members()->attach($developer->id, ['role' => 'Developer']);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id, 'summary' => 'My assigned requirement',
            'priority' => 'P3', 'description' => 'Scope', 'business_justification' => 'Need it',
            'acceptance_criteria' => 'Done', 'developer_id' => $developer->id,
        ])->assertCreated();
        $this->actingAs($developer)->get(route('pms.my-profile.show'))->assertOk()
            ->assertSee('My Work')->assertSee('My assigned requirement')->assertSee('Developer tickets');
        $this->actingAs($admin)->get(route('pms.my-profile.show'))->assertOk()->assertDontSee('My assigned requirement');
    }

    public function test_invitation_link_opens_when_public_registration_is_disabled(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'INVITE', 'name' => 'Invitation project', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::where('key', 'INVITE')->firstOrFail();
        DB::table('pms_workspace_settings')->update(['registration_policy' => 'disabled']);
        $response = $this->actingAs($admin)->postJson(route('pms.invitations.store'), [
            'email' => 'invited-dev@example.test', 'role' => 'Developer', 'project_id' => $project->id,
            'expires_at' => now()->addDays(2)->toDateTimeString(), 'max_uses' => 1,
        ])->assertCreated();
        $link = $response->json('link');
        $this->get($link)->assertOk()->assertSee('Join the delivery workspace');
        auth()->logout();
        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->post(route('pms.register.store'), [
            'invite' => $token, 'name' => 'Invited developer', 'email' => 'invited-dev@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertRedirect(route('pms.dashboard'));
        $this->assertDatabaseHas('project_management_users', ['email' => 'invited-dev@example.test']);
        $this->assertDatabaseHas('pms_invitations', ['used_count' => 1]);
        $this->get($link)->assertStatus(410);
        $this->get(route('pms.register', ['invite' => str_repeat('0', 64)]))->assertStatus(410)
            ->assertSee('Invitation unavailable');
    }
}
