<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\ProjectManagementUser;
use App\Models\User;
use App\Support\PmsAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PmsAdminRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function person(?string $role = null, bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? User::ROLE_SUPER_ADMIN : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active', 'workspace_role' => $role,
            'job_position' => $role === 'Project Manager' ? 'Developer' : $role]);
        return $user;
    }

    private function project(User $admin, string $key = 'WORK'): PmProject
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => $key, 'name' => $key.' delivery', 'priority' => 'P3',
        ])->assertCreated();
        return PmProject::where('key', $key)->firstOrFail();
    }

    private function member(PmProject $project, User $user, string $role): void
    {
        DB::table('pm_project_members')->insert(['project_id' => $project->id, 'user_id' => $user->id,
            'role' => $role, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function requirement(PmProject $project): array
    {
        return ['classification_key' => 'REQ', 'project_id' => $project->id, 'summary' => 'Review billing',
            'priority' => 'P3', 'description' => 'Describe the billing flow.',
            'business_justification' => 'Reduce errors.', 'acceptance_criteria' => 'Billing works.'];
    }

    public function test_ticket_manager_assignment_is_project_scoped_and_keeps_other_assignees(): void
    {
        $admin = $this->person(admin: true);
        $pm = $this->person('Project Manager');
        $second = $this->person('Project Manager');
        $developer = $this->person('Developer');
        $project = $this->project($admin);
        $other = $this->project($admin, 'OTHER');
        $this->member($project, $pm, 'Project Manager');
        $this->member($project, $second, 'Project Manager');
        $this->member($project, $developer, 'Developer');
        $this->member($other, $this->person('Project Manager'), 'Project Manager');
        $this->actingAs($admin)->get(route('pms.tickets.create', ['category' => 'REQ']))
            ->assertOk()->assertSee('name="project_manager_id"', false);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->requirement($project) + [
            'project_manager_id' => $pm->id, 'developer_id' => $developer->id,
        ])->assertCreated()->assertJsonPath('project_manager_id', $pm->id);
        $ticket = PmWorkItem::firstOrFail();
        $this->assertSame($developer->id, $ticket->developer_id);
        $this->actingAs($admin)->get(route('pms.tickets.show', $ticket))->assertOk()
            ->assertSee('Ticket ownership')->assertSee($pm->name);
        $this->actingAs($admin)->get(route('pms.tickets.index'))->assertOk()->assertSee($pm->name);
        $this->actingAs($admin)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'project_manager_id' => $second->id,
        ])->assertOk()->assertJsonPath('project_manager_id', $second->id);
        $ticket->refresh();
        $this->assertSame($developer->id, $ticket->developer_id);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'project_manager_changed']);
        $this->actingAs($admin)->get(route('pms.tickets.show', $ticket).'#activity')
            ->assertOk()->assertSee($pm->name)->assertSee($second->name);
        $this->actingAs($admin)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'project_manager_id' => $developer->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('project_manager_id');
        $this->assertSame($second->id, $ticket->fresh()->project_manager_id);
        $outside = $this->person('Project Manager');
        $this->member($other, $outside, 'Project Manager');
        $this->actingAs($admin)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'project_manager_id' => $outside->id,
        ])->assertUnprocessable();
        $second->pmsProfile->update(['status' => 'suspended']);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->requirement($project) + [
            'summary' => 'Another billing request', 'project_manager_id' => $second->id,
        ])->assertUnprocessable();
        $this->actingAs($admin)->getJson(route('pms.assignable-users', [
            'assignment_type' => 'manager', 'project_id' => $project->id,
        ]))->assertOk()->assertSee($pm->name)->assertDontSee($developer->name)->assertDontSee($second->name);
    }

    public function test_stage_restrictions_and_permission_denials_have_distinct_responses(): void
    {
        $admin = $this->person(admin: true);
        $pm = $this->person('Project Manager');
        $outsider = $this->person('Viewer');
        $project = $this->project($admin);
        $this->member($project, $pm, 'Project Manager');
        $this->member($project, $outsider, 'Viewer');
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), $this->requirement($project))->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($pm)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'description' => 'Updated before QA.',
        ])->assertOk();
        $ticket->refresh();
        $this->assertSame('Updated before QA.', $ticket->description);
        $this->actingAs($pm)->postJson(route('pms.tickets.transition', $ticket), [
            'lock_version' => $ticket->lock_version, 'status' => 'QAT Testing',
        ])->assertStatus(409)->assertJsonPath('error', 'WORKFLOW_STAGE_RESTRICTION')
            ->assertJsonPath('details.current_stage', 'Planned');
        $this->assertSame('Planned', $ticket->fresh()->status);
        $this->actingAs($outsider)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'description' => 'Unauthorized edit',
        ])->assertForbidden()->assertJsonPath('error', 'PERMISSION_DENIED');
        $this->actingAs($outsider)->put(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'description' => 'Unauthorized edit',
        ])->assertForbidden()->assertSee('Permission required');
        $this->assertSame('Updated before QA.', $ticket->fresh()->description);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'summary' => 'Unassigned intake', 'priority' => 'P3',
            'description' => 'Intake details.', 'business_justification' => 'Reduce errors.',
            'acceptance_criteria' => 'Reviewed.', 'due_date' => now()->addDay()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('due_date');
        $this->assertDatabaseCount('pm_work_items', 1);
    }

    public function test_account_directory_and_status_updates_use_real_scoped_data(): void
    {
        $admin = $this->person(admin: true);
        $pm = $this->person('Project Manager');
        $member = $this->person('Developer');
        $project = $this->project($admin);
        $this->member($project, $pm, 'Project Manager');
        $this->member($project, $member, 'Developer');
        ProjectManagementUser::create(['user_id' => $member->id, 'name' => $member->name,
            'email' => $member->email, 'password' => $member->password, 'status' => 'active']);
        $this->actingAs($admin)->get(route('pms.team.index', ['q' => $member->name, 'status' => 'active',
            'role' => 'Developer', 'project_id' => $project->id, 'sort' => 'name']))
            ->assertOk()->assertSee($member->email)->assertSee('Total users')->assertSee('Active users');
        $profile = $member->pmsProfile;
        $this->actingAs($admin)->putJson(route('pms.team.update', $profile), [
            'status' => 'suspended', 'name' => 'Renamed Developer', 'email' => 'renamed@example.test',
        ])->assertOk();
        $this->assertSame('suspended', $profile->fresh()->status);
        $this->assertSame('suspended', $member->projectManagementIdentity->fresh()->status);
        $this->assertSame('Renamed Developer', $member->fresh()->name);
        $this->assertFalse(PmsAccess::eligible($member->fresh()));
        $this->assertDatabaseHas('pm_audit_logs', ['subject_type' => 'membership', 'subject_id' => $profile->id,
            'action' => 'membership.suspended']);
        $this->actingAs($admin)->get(route('pms.team.index', ['status' => 'suspended']))
            ->assertOk()->assertSee('Renamed Developer');
        $this->actingAs($admin)->putJson(route('pms.team.update', $profile), ['status' => 'active'])
            ->assertOk();
        $this->assertTrue(PmsAccess::eligible($member->fresh()));
        $this->actingAs($admin)->putJson(route('pms.team.update', $profile), [
            'status' => 'active', 'workspace_role' => null,
        ])->assertOk();
        $this->assertDatabaseHas('pm_audit_logs', ['subject_type' => 'membership', 'subject_id' => $profile->id,
            'action' => 'membership.role_changed']);
        $profileAuditCount = DB::table('pm_audit_logs')->where('subject_type', 'membership')
            ->where('subject_id', $profile->id)->where('action', 'membership.profile_updated')->count();
        $this->actingAs($admin)->putJson(route('pms.team.update', $profile), ['status' => 'active'])
            ->assertOk();
        $this->assertSame($profileAuditCount, DB::table('pm_audit_logs')->where('subject_type', 'membership')
            ->where('subject_id', $profile->id)->where('action', 'membership.profile_updated')->count());
        $this->actingAs($admin)->putJson(route('pms.team.update', $profile), [
            'status' => 'active', 'email' => $pm->email,
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->actingAs($pm)->putJson(route('pms.team.update', $profile), [
            'status' => 'suspended',
        ])->assertForbidden();
    }

    public function test_default_manager_and_analyst_reassignment_wait_for_review(): void
    {
        $admin = $this->person(admin: true);
        $pm = $this->person('Project Manager');
        $replacement = $this->person('Project Manager');
        $analyst = $this->person('Business Analyst');
        $project = $this->project($admin);
        $this->member($project, $pm, 'Project Manager');
        $this->member($project, $replacement, 'Project Manager');
        $this->member($project, $analyst, 'Business Analyst');
        $project->update(['manager_id' => $pm->id]);
        $this->actingAs($analyst)->postJson(route('pms.tickets.store'), $this->requirement($project))
            ->assertCreated()->assertJsonPath('project_manager_id', $pm->id);
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($analyst)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'project_manager_id' => $replacement->id,
        ])->assertStatus(202)->assertJsonPath('status', 'pending');
        $this->assertSame($pm->id, $ticket->fresh()->project_manager_id);
        $reviewId = DB::table('pm_change_requests')->where('subject_type', 'ticket')
            ->where('subject_id', $ticket->id)->value('id');
        $this->actingAs($pm)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Approved reassignment.',
        ])->assertOk();
        $this->assertSame($replacement->id, $ticket->fresh()->project_manager_id);
        $this->assertSame($pm->id, $project->fresh()->manager_id);
    }

    public function test_qa_verification_before_testing_stage_is_atomic_and_structured(): void
    {
        $admin = $this->person(admin: true);
        $project = $this->project($admin);
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'QAT', 'project_id' => $project->id, 'summary' => 'Checkout fails',
            'priority' => 'P3', 'defect_type' => 'Functional bug', 'reproduction_steps' => 'Open checkout.',
            'expected_result' => 'Purchase succeeds.', 'actual_result' => 'Checkout fails.',
            'testing_environment' => 'Staging',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.verify', $ticket), [
            'lock_version' => $ticket->lock_version, 'result' => 'Passed', 'build' => 'test',
            'environment' => 'Staging', 'remarks' => 'Premature verification',
        ])->assertStatus(409)->assertJsonPath('error', 'WORKFLOW_STAGE_RESTRICTION')
            ->assertJsonPath('details.required_stage', 'Retesting or QAT in Progress');
        $this->assertDatabaseCount('pm_qa_verifications', 0);
        $this->assertSame('Open', $ticket->fresh()->status);
    }

    public function test_manager_assignment_is_exposed_by_automation_api_without_other_roles(): void
    {
        $admin = $this->person(admin: true);
        $pm = $this->person('Project Manager');
        $developer = $this->person('Developer');
        $project = $this->project($admin);
        $this->member($project, $pm, 'Project Manager');
        $this->member($project, $developer, 'Developer');
        Auth::guard('web')->logout();
        $this->withToken($admin->createToken('pms-test', ['pms'], now()->addHour())->plainTextToken);
        $this->getJson(route('api.pms.v1.assignableUsers', ['assignment_type' => 'manager',
            'project_id' => $project->id]))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $pm->id);
        $this->postJson(route('api.pms.v1.tickets.updateInfo'), [
            'action' => 'insert', 'project_id' => $project->id, 'ticket_type' => 'REQ',
            'title' => 'API manager assignment', 'description' => 'Describe the flow.', 'priority' => 'normal',
            'business_justification' => 'Reduce errors.', 'acceptance_criteria' => 'Pass.',
            'project_manager_id' => $pm->id,
        ])->assertCreated()->assertJsonPath('data.project_manager.user_id', $pm->id);
        $ticket = PmWorkItem::firstOrFail();
        $this->postJson(route('api.pms.v1.tickets.updateInfo'), [
            'action' => 'update', 'ticket_id' => $ticket->id, 'project_manager_id' => $developer->id,
        ])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->assertSame($pm->id, $ticket->fresh()->project_manager_id);
    }

    public function test_manager_directory_is_project_scoped_and_paginated(): void
    {
        $admin = $this->person(admin: true);
        $pm = $this->person('Project Manager');
        $project = $this->project($admin);
        $other = $this->project($admin, 'OTHER');
        $this->member($project, $pm, 'Project Manager');
        $outside = $this->person('Developer');
        $this->member($other, $outside, 'Developer');
        for ($i = 0; $i < 22; $i++) $this->member($project, $this->person('Developer'), 'Developer');
        $this->actingAs($pm)->getJson(route('pms.team.index'))->assertOk()
            ->assertJsonPath('profiles.per_page', 20)->assertJsonPath('profiles.total', 24);
        $this->actingAs($pm)->get(route('pms.team.index', ['page' => 2]))->assertOk()
            ->assertDontSee($outside->email);
        $this->actingAs($pm)->get(route('pms.team.show', $outside->pmsProfile))->assertForbidden();
    }
}
