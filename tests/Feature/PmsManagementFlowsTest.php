<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmCycle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\ProjectManagementUser;
use App\Models\User;
use App\Support\FrontendExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class PmsManagementFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function member(?string $role = null, bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? 1 : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'workspace_role' => $role]);
        return $user;
    }

    private function project(User $admin, string $key = 'APP'): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => $key, 'name' => $key.' project', 'priority' => 'P2',
        ])->assertCreated();
        return PmProject::where('key', $key)->firstOrFail();
    }

    private function assign(User $admin, PmProject $project, User $member, string $role): void
    {
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $member->id, 'role' => $role,
        ])->assertOk();
    }

    private function req(int $projectId, ?string $token = null): array
    {
        return ['classification_key' => 'REQ', 'project_id' => $projectId, 'summary' => 'Requirements review',
            'priority' => 'P3', 'description' => 'The system needs a review flow.',
            'business_justification' => 'Improve approvals.', 'acceptance_criteria' => 'Reviewers can decide.',
            'creation_token' => $token ?? (string) Str::uuid()];
    }

    public function test_requirement_creation_is_idempotent_and_validates_missing_fields(): void
    {
        $admin = $this->member(admin: true);
        $ba = $this->member('Business Analyst');
        $project = $this->project($admin);
        $this->assign($admin, $project, $ba, 'Business Analyst');
        $payload = $this->req($project->id);
        $this->actingAs($ba)->postJson(route('pms.tickets.store'), $payload)->assertCreated()->assertJsonPath('issue_key', 'REQ-00001');
        $this->actingAs($ba)->postJson(route('pms.tickets.store'), $payload)->assertOk()->assertJsonPath('issue_key', 'REQ-00001');
        $this->assertDatabaseCount('pm_work_items', 1);
        $this->actingAs($ba)->postJson(route('pms.tickets.store'), array_replace($payload, [
            'creation_token' => (string) Str::uuid(), 'business_justification' => '',
        ]))->assertUnprocessable()->assertJsonValidationErrors('business_justification');
    }

    public function test_review_requires_complete_requirements_and_separates_submitting_from_approval(): void
    {
        $admin = $this->member(admin: true);
        $ba = $this->member('Business Analyst');
        $pm = $this->member('Project Manager');
        $project = $this->project($admin);
        $this->assign($admin, $project, $ba, 'Business Analyst');
        $this->assign($admin, $project, $pm, 'Project Manager');
        $this->actingAs($ba)->postJson(route('pms.tickets.store'), $this->req($project->id))->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($ba)->postJson(route('pms.tickets.review.submit', $ticket))->assertOk()->assertJsonPath('status', 'pending');
        $this->actingAs($ba)->postJson(route('pms.tickets.review.decide', $ticket), [
            'decision' => 'approved', 'feedback' => 'Looks good.',
        ])->assertForbidden();
        $this->actingAs($pm)->postJson(route('pms.tickets.review.decide', $ticket), [
            'decision' => 'changes_requested', 'feedback' => 'Clarify the criteria.',
        ])->assertOk();
        $change = $this->actingAs($ba)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->fresh()->lock_version, 'acceptance_criteria' => 'Clarified: reviewer decision is saved.',
        ])->assertStatus(202);
        $this->assertNotSame('Clarified: reviewer decision is saved.', $ticket->fresh()->acceptance_criteria);
        $this->actingAs($pm)->postJson(route('pms.change-reviews.decide', $change->json('review_id')), [
            'decision' => 'approved', 'comment' => 'Clarification accepted.',
        ])->assertOk();
        $this->assertSame('Clarified: reviewer decision is saved.', $ticket->fresh()->acceptance_criteria);
        $this->actingAs($ba)->postJson(route('pms.tickets.review.submit', $ticket))->assertOk();
        $this->actingAs($pm)->postJson(route('pms.tickets.review.decide', $ticket), [
            'decision' => 'approved', 'feedback' => 'Accepted after revisions.',
        ])->assertOk();
        $this->assertSame('approved', DB::table('pm_ticket_reviews')->where('issue_id', $ticket->id)->orderByDesc('revision')->value('status'));
        $this->assertDatabaseCount('pm_ticket_reviews', 2);
        $this->actingAs($ba)->get(route('pms.tickets.show', $ticket))->assertOk()->assertSee('Accepted after revisions.');
        $this->actingAs($ba)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'ENH', 'project_id' => $project->id,
            'summary' => 'Improve review flow', 'priority' => 'P3',
            'current_behavior' => 'Manual handoff', 'proposed_behavior' => 'Tracked handoff',
            'acceptance_criteria' => 'Reviewer can respond', 'creation_token' => (string) Str::uuid(),
        ])->assertCreated();
        $enhancement = PmWorkItem::where('classification_key', 'ENH')->firstOrFail();
        $this->actingAs($ba)->postJson(route('pms.tickets.review.submit', $enhancement))
            ->assertOk()->assertJsonPath('status', 'pending');
        $this->actingAs($ba)->postJson(route('pms.tickets.store'), $this->req($project->id))->assertCreated();
        $incomplete = PmWorkItem::where('classification_key', 'REQ')->latest('id')->firstOrFail();
        $incomplete->forceFill(['acceptance_criteria' => null])->save();
        $this->actingAs($ba)->postJson(route('pms.tickets.review.submit', $incomplete))
            ->assertUnprocessable()->assertJsonValidationErrors('acceptance_criteria');
        $this->actingAs($pm)->postJson(route('pms.tickets.store'), $this->req($project->id))->assertCreated();
        $ownTicket = PmWorkItem::where('classification_key', 'REQ')->latest('id')->firstOrFail();
        $this->actingAs($pm)->postJson(route('pms.tickets.review.submit', $ownTicket))->assertOk();
        $this->actingAs($pm)->postJson(route('pms.tickets.review.decide', $ownTicket), [
            'decision' => 'approved', 'feedback' => 'Self approval is prohibited.',
        ])->assertForbidden();
    }

    public function test_project_manager_soft_deletes_ticket_release_and_cycle_with_scope_enforcement(): void
    {
        $admin = $this->member(admin: true);
        $pm = $this->member('Project Manager');
        $qa = $this->member('QA');
        $developer = $this->member('Developer');
        $project = $this->project($admin);
        $other = $this->project($admin, 'OTH');
        $this->assign($admin, $project, $pm, 'Project Manager');
        $this->assign($admin, $project, $qa, 'QA');
        $this->assign($admin, $project, $developer, 'Developer');
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->req($project->id))->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($qa)->deleteJson(route('pms.tickets.destroy', $ticket), ['confirmation' => $ticket->issue_key])->assertForbidden();
        $this->actingAs($pm)->deleteJson(route('pms.tickets.destroy', $ticket), ['confirmation' => $ticket->issue_key])->assertOk();
        $this->assertSoftDeleted('pm_work_items', ['id' => $ticket->id, 'deleted_by' => $pm->id]);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'deleted', 'actor_id' => $pm->id]);
        $this->assertDatabaseHas('pm_audit_logs', ['subject_id' => $ticket->id, 'action' => 'ticket.deleted', 'actor_id' => $pm->id]);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->req($other->id))->assertCreated();
        $outsideTicket = PmWorkItem::where('project_id', $other->id)->firstOrFail();
        $this->actingAs($pm)->deleteJson(route('pms.tickets.destroy', $outsideTicket), ['confirmation' => $outsideTicket->issue_key])->assertForbidden();
        $this->actingAs($pm)->deleteJson(route('pms.projects.trash', $other), ['confirmation' => $other->key])->assertForbidden();
        $this->actingAs($developer)->deleteJson(route('pms.projects.trash', $project), ['confirmation' => $project->key])->assertForbidden();
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Delivery cycle', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $this->actingAs($pm)->deleteJson(route('pms.cycles.destroy', $cycle), ['confirmation' => $cycle->cycle_key])->assertOk();
        $this->assertSoftDeleted('pm_cycles', ['id' => $cycle->id, 'deleted_by' => $pm->id]);
    }

    public function test_valid_invitation_registers_qa_with_role_and_rejects_reuse_or_escalation(): void
    {
        $admin = $this->member(admin: true);
        $pm = $this->member('Project Manager');
        $project = $this->project($admin);
        $other = $this->project($admin, 'OTH');
        $this->assign($admin, $project, $pm, 'Project Manager');
        $this->actingAs($pm)->postJson(route('pms.invitations.store'), [
            'email' => 'newqa@example.test', 'role' => 'Project Manager', 'project_id' => $project->id,
            'expires_at' => now()->addDays(3)->toDateTimeString(), 'max_uses' => 1,
        ])->assertUnprocessable();
        $this->actingAs($pm)->postJson(route('pms.invitations.store'), [
            'email' => 'newqa@example.test', 'role' => 'QA', 'project_id' => $other->id,
            'expires_at' => now()->addDays(3)->toDateTimeString(), 'max_uses' => 1,
        ])->assertForbidden();
        $response = $this->actingAs($pm)->postJson(route('pms.invitations.store'), [
            'email' => 'newqa@example.test', 'role' => 'QA', 'project_id' => $project->id,
            'expires_at' => now()->addDays(3)->toDateTimeString(), 'max_uses' => 1,
        ])->assertCreated();
        $link = $response->json('link');
        $invite = DB::table('pms_invitations')->first();
        $this->assertStringNotContainsString(basename(parse_url($link, PHP_URL_PATH)), $invite->token_hash);
        auth()->logout();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => true]);
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => true]);
        FrontendExperience::forgetCache();
        $this->get($link)->assertOk()->assertSee('PROJECT INVITATION');
        $this->post(route('pms.register.store'), [
            'invite' => basename(parse_url($link, PHP_URL_PATH)),
            'name' => 'New QA', 'email' => 'newqa@example.test', 'role' => 'Project Manager',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertRedirect(route('pms.dashboard'));
        $identity = ProjectManagementUser::where('email', 'newqa@example.test')->firstOrFail();
        $this->assertDatabaseHas('pm_project_members', ['project_id' => $project->id, 'user_id' => $identity->user_id, 'role' => 'QA']);
        $this->assertDatabaseHas('pms_registrations', ['user_id' => $identity->user_id, 'referral_code' => $invite->referral_code]);
        $this->getJson($link)->assertStatus(410);
        Auth::guard('pms')->logout();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->req($project->id))->assertCreated();
        $ticket = PmWorkItem::where('project_id', $project->id)->firstOrFail();
        $this->actingAs($identity->user)->deleteJson(route('pms.tickets.destroy', $ticket), [
            'confirmation' => $ticket->issue_key,
        ])->assertForbidden();
        $this->actingAs($identity->user)->deleteJson(route('pms.projects.trash', $project), ['confirmation' => $project->key])->assertForbidden();
    }

    public function test_deleting_release_detaches_tickets_and_deleting_cycle_detaches_releases(): void
    {
        $admin = $this->member(admin: true);
        $pm = $this->member('Project Manager');
        $ba = $this->member('Business Analyst');
        $project = $this->project($admin);
        $this->assign($admin, $project, $pm, 'Project Manager');
        $this->assign($admin, $project, $ba, 'Business Analyst');
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'Release cycle', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.releases.store'), [
            'project_id' => $project->id, 'cycle_id' => $cycle->id,
            'name' => 'Release one', 'version' => '1.0', 'planned_deploy_date' => '2026-10-28',
        ])->assertCreated();
        $release = DB::table('pm_releases')->first();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->req($project->id) + [
            'release_id' => $release->id,
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($ba)->deleteJson(route('pms.releases.destroy', $release->id), ['confirmation' => $release->release_key])->assertForbidden();
        $this->actingAs($pm)->deleteJson(route('pms.releases.destroy', $release->id), ['confirmation' => $release->release_key])->assertOk();
        $this->assertNull($ticket->fresh()->release_id);
        $this->assertDatabaseHas('pm_releases', ['id' => $release->id, 'deleted_by' => $pm->id]);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'release_changed']);
        $this->actingAs($admin)->postJson(route('pms.releases.store'), [
            'project_id' => $project->id, 'cycle_id' => $cycle->id,
            'name' => 'Release two', 'version' => '2.0', 'planned_deploy_date' => '2026-10-29',
        ])->assertCreated();
        $activeRelease = DB::table('pm_releases')->where('version', '2.0')->first();
        $this->assertNotNull($activeRelease);
        $this->actingAs($pm)->deleteJson(route('pms.cycles.destroy', $cycle), ['confirmation' => $cycle->cycle_key])->assertOk();
        $this->assertDatabaseHas('pm_releases', ['id' => $activeRelease->id, 'cycle_id' => null, 'deleted_at' => null]);
        $this->assertNotNull($ticket->fresh());
        $this->assertNotNull($project->fresh());
    }

    public function test_invitation_registration_works_with_all_other_module_combinations(): void
    {
        $admin = $this->member(admin: true);
        $project = $this->project($admin);
        $response = $this->actingAs($admin)->postJson(route('pms.invitations.batch'), [
            'members' => collect(range(0, 3))->map(fn ($index) => ['email' => 'qa'.$index.'@example.test'])->all(),
            'role' => 'QA', 'project_id' => $project->id,
            'expires_at' => now()->addDays(2)->toDateTimeString(),
        ])->assertCreated();
        $links = $response->json('invitations');
        foreach ([[false,false],[true,false],[false,true],[true,true]] as $index => [$trading,$ecommerce]) {
            $token = basename(parse_url($links[$index]['link'], PHP_URL_PATH));
            FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_TRADING], ['enabled' => $trading]);
            FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_DEALERSHIP_ECOMMERCE], ['enabled' => $ecommerce]);
            FrontendExperience::forgetCache();
            Auth::guard('pms')->logout(); Auth::guard('web')->logout();
            $this->get(route('pms.register', ['invite' => $token]))->assertOk();
            $this->post(route('pms.register.store'), [
                'invite' => $token, 'name' => 'Member '.$index, 'email' => 'qa'.$index.'@example.test',
                'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            ])->assertRedirect(route('pms.dashboard'));
        }
        $this->assertSame(4, (int) DB::table('pms_invitations')->sum('used_count'));
        $this->getJson(route('pms.register', ['invite' => $token]))->assertStatus(410);
        $this->getJson(route('pms.register'))->assertOk();
    }

    public function test_expired_revoked_and_email_mismatched_invitations_are_rejected(): void
    {
        $admin = $this->member(admin: true);
        $project = $this->project($admin);
        $payload = ['email' => 'right@example.test', 'email_restricted' => true, 'role' => 'QA', 'project_id' => $project->id,
            'expires_at' => now()->addDays(2)->toDateTimeString(), 'max_uses' => 1];
        $first = $this->actingAs($admin)->postJson(route('pms.invitations.store'), $payload)->assertCreated();
        $token = basename(parse_url($first->json('link'), PHP_URL_PATH));
        Auth::guard('web')->logout();
        $this->postJson(route('pms.register.store'), [
            'invite' => $token, 'name' => 'Wrong email', 'email' => 'wrong@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseMissing('project_management_users', ['email' => 'wrong@example.test']);
        DB::table('pms_invitations')->where('id', 1)->update(['expires_at' => now()->subMinute()]);
        $this->getJson(route('pms.register', ['invite' => $token]))->assertStatus(410);
        $second = $this->actingAs($admin)->postJson(route('pms.invitations.store'), $payload)->assertCreated();
        $token2 = basename(parse_url($second->json('link'), PHP_URL_PATH));
        $id = DB::table('pms_invitations')->max('id');
        $this->actingAs($admin)->deleteJson(route('pms.invitations.revoke', $id))->assertOk();
        $this->getJson(route('pms.register', ['invite' => $token2]))->assertStatus(410);
        $this->getJson(route('pms.register', ['invite' => str_repeat('a', 64)]))->assertStatus(410);
    }

    public function test_existing_account_signs_in_and_accepts_without_duplicate_identity(): void
    {
        $admin = $this->member(admin: true);
        $project = $this->project($admin);
        $existing = User::factory()->create(['email' => 'existing@example.test', 'password' => Hash::make('SharedPass123'), 'status' => 1]);
        $response = $this->actingAs($admin)->postJson(route('pms.invitations.store'), [
            'email' => 'existing@example.test', 'role' => 'Developer', 'project_id' => $project->id,
            'expires_at' => now()->addDay()->toDateTimeString(), 'max_uses' => 1,
        ])->assertCreated();
        $token = basename(parse_url($response->json('link'), PHP_URL_PATH));
        Auth::guard('web')->logout();
        $this->get(route('pms.register', ['invite' => $token]))->assertOk()->assertSee('Sign in and accept');
        $this->post(route('pms.authenticate'), [
            'invite' => $token, 'email' => 'existing@example.test', 'password' => 'SharedPass123',
        ])->assertRedirect(route('pms.register', ['invite' => $token]));
        $this->post(route('pms.invitations.accept'), ['invite' => $token])->assertRedirect(route('pms.dashboard'));
        $identity = ProjectManagementUser::where('email', 'existing@example.test')->firstOrFail();
        $this->assertSame($existing->id, $identity->user_id);
        $this->assertDatabaseHas('pm_project_members', ['user_id' => $existing->id, 'project_id' => $project->id, 'role' => 'Developer']);
        $this->assertDatabaseCount('project_management_users', 1);
    }

    public function test_project_manager_cannot_escalate_or_replace_management_roles(): void
    {
        $admin = $this->member(admin: true);
        $manager = $this->member('Project Manager');
        $candidate = $this->member('QA');
        $project = $this->project($admin);
        $this->assign($admin, $project, $manager, 'Project Manager');

        $this->actingAs($manager)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $candidate->id, 'role' => 'Project Manager',
        ])->assertUnprocessable();
        $this->actingAs($manager)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $admin->id, 'role' => 'QA',
        ])->assertForbidden();
        $this->actingAs($manager)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $manager->id, 'role' => 'QA',
        ])->assertForbidden();
        $this->actingAs($manager)->deleteJson(route('pms.projects.members.destroy', [$project, $manager->id]))->assertForbidden();
        $this->actingAs($manager)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $candidate->id, 'role' => 'QA',
        ])->assertOk();
        $this->assertDatabaseHas('pm_project_members', [
            'project_id' => $project->id, 'user_id' => $candidate->id, 'role' => 'QA',
        ]);
    }

    public function test_timeline_and_team_directory_render_scoped_real_data(): void
    {
        $admin = $this->member(admin: true);
        $manager = $this->member('Project Manager');
        $qa = $this->member('QA');
        $project = $this->project($admin);
        $this->assign($admin, $project, $manager, 'Project Manager');
        $this->assign($admin, $project, $qa, 'QA');
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $project->id, 'name' => 'October delivery', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-10-01',
        ])->assertCreated();
        $cycle = PmCycle::firstOrFail();
        $this->actingAs($manager)->get(route('pms.cycles.show', $cycle))
            ->assertOk()->assertSee('Development timeline')->assertSee('data-timeline-view="monthly"', false)
            ->assertSee('data-start="2026-10-01"', false)->assertSee('data-phases=', false)
            ->assertSee('pm-gantt-render');
        $this->actingAs($manager)->getJson(route('pms.team.index', ['role' => 'QA']))
            ->assertOk()->assertJsonPath('profiles.total', 1)
            ->assertJsonPath('profiles.data.0.user_id', $qa->id);
        $this->actingAs($manager)->getJson(route('pms.team.index', ['q' => $qa->email, 'status' => 'active']))
            ->assertOk()->assertJsonPath('profiles.total', 1);
        $this->actingAs($qa)->get(route('pms.dashboard'))->assertOk()
            ->assertSee('href="'.route('pms.dashboard').'" class="active"', false)
            ->assertDontSee(route('pms.invitations.index'));
        $this->actingAs($qa)->getJson(route('pms.team.index'))->assertForbidden();
    }

    public function test_reissued_invitation_revokes_old_link_and_exposes_a_new_copy_link(): void
    {
        $admin = $this->member(admin: true);
        $project = $this->project($admin);
        $created = $this->actingAs($admin)->postJson(route('pms.invitations.store'), [
            'email' => 'reissue@example.test', 'role' => 'QA', 'project_id' => $project->id,
            'expires_at' => now()->addDays(2)->toDateTimeString(), 'max_uses' => 1,
        ])->assertCreated();
        $original = DB::table('pms_invitations')->first();
        $this->assertNotNull($original);
        $reissued = $this->actingAs($admin)->postJson(route('pms.invitations.reissue', $original->id))
            ->assertCreated();
        $this->getJson($created->json('link'))->assertStatus(410);
        $this->get($reissued->json('link'))->assertOk();
        $this->actingAs($admin)->get(route('pms.invitations.index'))->assertOk()
            ->assertSee('data-copy-target=', false)->assertSee($reissued->json('code'));
        $this->assertNotNull(DB::table('pms_invitations')->where('id', $original->id)->value('revoked_at'));
    }
}
