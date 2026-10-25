<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use App\Support\PmsPosition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function admin(): User
    {
        $user = User::factory()->create(['role_id' => 1, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'approved_at' => now(), 'can_create_all_tickets' => true]);
        return $user;
    }

    private function ticket(User $admin): PmWorkItem
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'CYCLE', 'name' => 'Cycle project', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::where('key', 'CYCLE')->firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id,
            'summary' => 'Requirement', 'priority' => 'P3',
            'description' => 'A requirement to deliver.', 'business_justification' => 'Needed for launch.',
            'acceptance_criteria' => 'Reviewed and released.',
        ])->assertCreated();
        return PmWorkItem::where('project_id', $project->id)->firstOrFail();
    }

    private function invitationProject(User $admin): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'INVITE', 'name' => 'Invitation project', 'priority' => 'P3',
        ])->assertCreated();

        return PmProject::where('key', 'INVITE')->firstOrFail();
    }

    public function test_blockers_keep_workflow_status_and_record_resolution_history(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $original = $ticket->status;
        $this->actingAs($admin)->postJson(route('pms.tickets.blockers.store', $ticket), [
            'category' => 'Requirement Clarification',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $created = $this->actingAs($admin)->postJson(route('pms.tickets.blockers.store', $ticket), [
            'category' => 'Requirement Clarification', 'reason' => 'Waiting for approved specification',
        ])->assertCreated();
        $id = $created->json('id');
        $this->assertSame($original, $ticket->fresh()->status);
        $this->actingAs($admin)->postJson(route('pms.tickets.blockers.resolve', [$ticket, $id]), [
            'resolution_note' => 'Specification approved',
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.tickets.blockers.reopen', [$ticket, $id]), [
            'reason' => 'Approval withdrawn',
        ])->assertOk();
        $this->assertDatabaseCount('pm_ticket_blocker_history', 3);
        $this->assertNull(DB::table('pm_ticket_blockers')->find($id)->resolved_at);
    }

    public function test_blocker_related_ticket_and_owner_must_be_visible_and_project_scoped(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $ticket->project_id,
            'summary' => 'Upstream requirement', 'priority' => 'P3',
            'description' => 'Required dependency.', 'business_justification' => 'Needed for launch.',
            'acceptance_criteria' => 'Dependency delivered.',
        ])->assertCreated();
        $related = PmWorkItem::where('project_id', $ticket->project_id)->where('id', '!=', $ticket->id)->firstOrFail();
        $outsider = User::factory()->create(['status' => 1]);
        PmsProfile::create(['user_id' => $outsider->id, 'status' => 'active']);
        $this->actingAs($admin)->postJson(route('pms.tickets.blockers.store', $ticket), [
            'category' => 'Development Dependency', 'reason' => 'Waiting on upstream work',
            'related_issue_key' => $related->issue_key, 'owner_id' => $outsider->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('owner_id');
        $this->actingAs($admin)->postJson(route('pms.tickets.blockers.store', $ticket), [
            'category' => 'Development Dependency', 'reason' => 'Waiting on upstream work',
            'related_issue_key' => $related->issue_key,
        ])->assertCreated();
        $this->assertDatabaseHas('pm_ticket_blockers', ['issue_id' => $ticket->id, 'related_issue_id' => $related->id]);
    }

    public function test_master_data_requires_valid_department_position_pair_and_does_not_grant_roles(): void
    {
        $admin = $this->admin();
        $departments = DB::table('pms_departments')->pluck('id', 'name');
        $positions = DB::table('pms_job_positions')->pluck('id', 'name');
        $candidate = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $candidate->id, 'status' => 'active']);
        $this->actingAs($candidate)->postJson(route('pms.organization.departments.store'), [
            'code' => 'OPS', 'name' => 'Operations', 'active' => 1, 'position_ids' => [],
        ])->assertForbidden();
        $this->actingAs($admin)->post(route('pms.organization.departments.store'), [
            'code' => 'OPS', 'name' => 'Operations', 'active' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('pms_departments', ['code' => 'OPS', 'name' => 'Operations']);
        $this->actingAs($admin)->putJson(route('pms.organization.profiles.update', $candidate), [
            'department_id' => $departments['Quality Assurance'], 'job_position_id' => $positions['Developer'],
        ])->assertUnprocessable();
        $this->actingAs($admin)->putJson(route('pms.organization.profiles.update', $candidate), [
            'department_id' => $departments['Development / Engineering'], 'job_position_id' => $positions['Developer'],
        ])->assertOk();
        $profile = $candidate->pmsProfile()->first();
        $this->assertSame('Developer', $profile->job_position);
        $this->assertNull($profile->workspace_role);
        $developmentId = $departments['Development / Engineering'];
        $developerId = $positions['Developer'];
        $this->actingAs($admin)->putJson(route('pms.organization.departments.update', $developmentId), [
            'code' => 'DEV', 'name' => 'Engineering', 'active' => 1, 'position_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('position_ids');
        $this->actingAs($admin)->putJson(route('pms.organization.departments.update', $developmentId), [
            'code' => 'DEV', 'name' => 'Engineering', 'active' => 1, 'position_ids' => [$developerId],
        ])->assertOk();
        $this->assertSame('Engineering', $candidate->pmsProfile()->first()->department);
        $this->actingAs($admin)->putJson(route('pms.organization.positions.update', $developerId), [
            'code' => 'DEV', 'name' => 'Software Developer', 'active' => 1,
            'department_ids' => [$developmentId],
        ])->assertOk();
        $this->assertSame('Software Developer', $candidate->pmsProfile()->first()->job_position);
        $this->assertTrue(PmsPosition::eligible($candidate->fresh()->load('pmsProfile'), 'developer'));
        $this->actingAs($admin)->putJson(route('pms.organization.positions.update', $developerId), [
            'code' => 'ENGINEER', 'name' => 'Software Developer', 'active' => 1,
            'department_ids' => [$developmentId],
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $ticket = $this->ticket($admin);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $ticket->project_id), [
            'user_id' => $candidate->id, 'role' => 'Developer',
        ])->assertOk();
        $this->actingAs($admin)->get(route('pms.tickets.create'))
            ->assertOk()->assertSee($candidate->name.' - Software Developer');
        $this->actingAs($admin)->get(route('pms.tickets.show', $ticket))
            ->assertOk()->assertSee($candidate->name.' - Software Developer');
    }

    public function test_release_assignment_uses_existing_organization_cycle_and_updates_ticket_history(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'November delivery', 'start_date' => '2026-11-01', 'end_date' => '2026-12-15',
        ])->assertCreated();
        $cycleId = DB::table('pm_global_cycles')->value('id');
        $this->actingAs($admin)->postJson(route('pms.releases.cycle-assign'), [
            'cycle_id' => $cycleId, 'issue_key' => $ticket->issue_key,
        ])->assertOk();
        $this->assertSame($cycleId, $ticket->fresh()->global_cycle_id);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'organization_cycle_changed']);
        $this->actingAs($admin)->get(route('pms.global-cycles.show', [$cycleId, 'tab' => 'tickets']))
            ->assertOk()->assertSee($ticket->reference)->assertSee('CYCLE');
        $this->actingAs($admin)->get(route('pms.releases.index'))->assertOk()
            ->assertSee('Completion rate')->assertSee('November delivery');
    }

    public function test_ticket_stage_is_explicit_and_clears_when_moved_to_another_cycle(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        foreach ([['November', '2026-11-01', '2026-12-15'], ['January', '2027-01-01', '2027-02-14']] as [$name, $start, $end]) {
            $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
                'name' => $name, 'start_date' => $start, 'end_date' => $end,
            ])->assertCreated();
        }
        [$first, $second] = DB::table('pm_global_cycles')->orderBy('id')->pluck('id')->all();
        $stage = DB::table('pm_global_cycle_stages')->where('cycle_id', $first)->where('stage_key', 'development')->first();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.store', $first), [
            'issue_key' => $ticket->issue_key,
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.stage', [$first, $ticket]), [
            'stage_id' => $stage->id,
        ])->assertOk();
        $this->assertSame($stage->id, $ticket->fresh()->global_cycle_stage_id);
        $this->actingAs($admin)->get(route('pms.global-cycles.show', [$first, 'tab' => 'tickets']))
            ->assertOk()->assertSee('Development');
        $departmentId = DB::table('pms_departments')->where('code', 'DEV')->value('id');
        $this->actingAs($admin)->get(route('pms.global-cycles.show', [
            $first, 'tab' => 'tickets', 'stage' => $stage->id, 'department' => $departmentId,
            'created_from' => '2026-01-01', 'target_to' => '2027-12-31', 'sort' => 'target',
        ]))->assertOk();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.store', $second), [
            'issue_key' => $ticket->issue_key, 'reason' => 'Carry forward to next delivery',
        ])->assertOk();
        $this->assertNull($ticket->fresh()->global_cycle_stage_id);
        $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.stage', [$second, $ticket]), [
            'stage_id' => $stage->id,
        ])->assertStatus(422);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'organization_stage_changed']);
    }

    public function test_administrator_reconciles_historical_plan_only_after_project_association(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $ticket->project_id, 'name' => 'Legacy execution plan',
            'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-11-01',
        ])->assertCreated();
        $planId = DB::table('pm_cycles')->value('id');
        $this->actingAs($admin)->postJson(route('pms.cycles.issues.store', $planId), [
            'issue_id' => $ticket->id,
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'November delivery', 'start_date' => '2026-11-01', 'end_date' => '2026-12-15',
        ])->assertCreated();
        $masterId = DB::table('pm_global_cycles')->value('id');
        $payload = ['global_cycle_id' => $masterId, 'reason' => 'Same project and planned ticket scope'];
        $this->actingAs($admin)->get(route('pms.organization.index'))->assertOk()
            ->assertSee('Legacy execution plan');
        $this->actingAs($admin)->postJson(route('pms.organization.legacy-cycles.reconcile', $planId), $payload)
            ->assertUnprocessable();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.projects.store', $masterId), [
            'project_ids' => [$ticket->project_id],
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.organization.legacy-cycles.reconcile', $planId), $payload)
            ->assertOk()->assertJsonPath('global_cycle_id', $masterId);
        $this->assertDatabaseHas('pm_cycles', ['id' => $planId, 'global_cycle_id' => $masterId]);
        $this->assertSame($masterId, $ticket->fresh()->global_cycle_id);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'organization_cycle_reconciled']);
        $this->actingAs($admin)->get(route('pms.cycles.show', $planId))->assertOk()
            ->assertSee('Organization cycle:')->assertSee('November delivery');
    }

    public function test_reconciliation_does_not_overwrite_conflicting_ticket_cycle(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), [
            'project_id' => $ticket->project_id, 'name' => 'Legacy execution plan',
            'week_mode' => 'calendar', 'template_weeks' => 4, 'start_date' => '2026-11-01',
        ])->assertCreated();
        $planId = DB::table('pm_cycles')->value('id');
        $this->actingAs($admin)->postJson(route('pms.cycles.issues.store', $planId), [
            'issue_id' => $ticket->id,
        ])->assertOk();
        foreach ([['First delivery', '2026-11-01', '2026-12-15'],
            ['Second delivery', '2027-01-01', '2027-02-14']] as [$name, $start, $end]) {
            $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
                'name' => $name, 'start_date' => $start, 'end_date' => $end,
            ])->assertCreated();
        }
        [$first, $second] = DB::table('pm_global_cycles')->orderBy('id')->pluck('id')->all();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.projects.store', $first), [
            'project_ids' => [$ticket->project_id],
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.global-cycles.tickets.store', $second), [
            'issue_key' => $ticket->issue_key,
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.organization.legacy-cycles.reconcile', $planId), [
            'global_cycle_id' => $first, 'reason' => 'Attempted historical match',
        ])->assertUnprocessable()->assertJsonValidationErrors('global_cycle_id');
        $this->assertNull(DB::table('pm_cycles')->where('id', $planId)->value('global_cycle_id'));
        $this->assertSame($second, $ticket->fresh()->global_cycle_id);
    }

    public function test_legacy_api_can_create_execution_plan_under_existing_master_cycle(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $this->actingAs($admin)->postJson(route('pms.global-cycles.store'), [
            'name' => 'November delivery', 'start_date' => '2026-11-01', 'end_date' => '2026-12-15',
        ])->assertCreated();
        $masterId = DB::table('pm_global_cycles')->value('id');
        $payload = [
            'project_id' => $ticket->project_id, 'global_cycle_id' => $masterId,
            'name' => 'Project execution plan', 'week_mode' => 'calendar',
            'template_weeks' => 4, 'start_date' => '2026-11-01',
        ];
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('global_cycle_id');
        $this->actingAs($admin)->postJson(route('pms.global-cycles.projects.store', $masterId), [
            'project_ids' => [$ticket->project_id],
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('pms.cycles.store'), $payload)->assertCreated();
        $planId = DB::table('pm_cycles')->value('id');
        $this->actingAs($admin)->postJson(route('pms.cycles.issues.store', $planId), [
            'issue_id' => $ticket->id,
        ])->assertOk();
        $this->assertDatabaseHas('pm_cycles', ['id' => $planId, 'global_cycle_id' => $masterId]);
        $this->assertSame($masterId, $ticket->fresh()->global_cycle_id);
        $this->assertDatabaseCount('pm_global_cycles', 1);
    }

    public function test_personal_invitation_uses_path_token_and_server_assigned_organization_details(): void
    {
        $admin = $this->admin();
        $project = $this->invitationProject($admin);
        $department = DB::table('pms_departments')->where('name', 'Quality Assurance')->value('id');
        $position = DB::table('pms_job_positions')->where('name', 'Quality Assurance Engineer')->value('id');
        $response = $this->actingAs($admin)->postJson(route('pms.invitations.store'), [
            'email' => 'qa@example.test', 'invited_name' => 'QA Member', 'role' => 'QA', 'project_id' => $project->id,
            'department_id' => $department, 'job_position_id' => $position,
            'expires_at' => now()->addDay()->toDateTimeString(), 'max_uses' => 1,
        ])->assertCreated();
        $link = $response->json('link');
        $token = basename(parse_url($link, PHP_URL_PATH));
        $this->assertSame(64, strlen($token));
        auth()->logout();
        $this->get($link)->assertOk()->assertSee('Quality Assurance Engineer')->assertSee('QA Member')
            ->assertSee('Create account and accept invitation');
        $this->post(route('pms.register.store'), [
            'invite' => $token, 'name' => 'Changed Name', 'email' => 'qa@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            'role' => 'Project Manager',
        ])->assertRedirect(route('pms.dashboard'));
        $profile = DB::table('pms_profiles')->where('department_id', $department)->first();
        $this->assertSame($position, $profile->job_position_id);
        $this->assertSame('Changed Name', DB::table('users')->where('id', $profile->user_id)->value('name'));
        $this->get($link)->assertStatus(410)->assertSee('already been accepted');
    }

    public function test_accepted_invitation_history_does_not_offer_dead_link_actions(): void
    {
        $admin = $this->admin();
        $project = $this->invitationProject($admin);
        $this->actingAs($admin)->postJson(route('pms.invitations.store'), [
            'email' => 'accepted@example.test', 'role' => 'Viewer', 'project_id' => $project->id,
            'expires_at' => now()->addDay()->toDateTimeString(), 'max_uses' => 1,
        ])->assertCreated();
        DB::table('pms_invitations')->update(['used_count' => 1]);
        $this->actingAs($admin)->get(route('pms.invitations.index'))
            ->assertOk()->assertSee('Accepted')
            ->assertDontSee('data-copy-target="pm-invite-1"', false)
            ->assertDontSee('>Reissue</button>', false);
    }

    public function test_batch_invitation_creates_distinct_one_use_tokens(): void
    {
        $admin = $this->admin();
        $project = $this->invitationProject($admin);
        $response = $this->actingAs($admin)->postJson(route('pms.invitations.batch'), [
            'members' => [['email' => 'one@example.test', 'name' => 'One'], ['email' => 'two@example.test', 'name' => 'Two']],
            'role' => 'Developer', 'project_id' => $project->id, 'expires_at' => now()->addDay()->toDateTimeString(),
        ])->assertCreated();
        $links = $response->json('invitations');
        $this->assertCount(2, $links);
        $this->assertNotSame($links[0]['link'], $links[1]['link']);
        $this->assertDatabaseCount('pms_invitations', 2);
        $this->assertSame([1, 1], DB::table('pms_invitations')->orderBy('id')->pluck('max_uses')->all());
    }

    public function test_bulk_ticket_contributors_are_eligible_and_not_duplicated(): void
    {
        $admin = $this->admin();
        $ticket = $this->ticket($admin);
        $developers = User::factory()->count(2)->create(['role_id' => null, 'status' => 1]);
        foreach ($developers as $developer) {
            PmsProfile::create(['user_id' => $developer->id, 'status' => 'active', 'job_position' => 'Developer']);
            $this->actingAs($admin)->postJson(route('pms.projects.members.store', $ticket->project_id), [
                'user_id' => $developer->id, 'role' => 'Developer',
            ])->assertOk();
        }
        $payload = ['assignment_type' => 'developer', 'user_ids' => $developers->pluck('id')->all()];
        $this->actingAs($admin)->postJson(route('pms.tickets.contributors.store', $ticket), $payload)->assertOk()->assertJsonPath('added', 2);
        $this->actingAs($admin)->postJson(route('pms.tickets.contributors.store', $ticket), $payload)->assertOk()->assertJsonPath('added', 0);
        $this->assertDatabaseCount('pm_ticket_contributors', 2);
        $this->actingAs($admin)->postJson(route('pms.tickets.contributors.store', $ticket), [
            'assignment_type' => 'qa', 'user_ids' => [$developers[0]->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('user_ids');
    }
}
