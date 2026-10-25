<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use App\Support\PmsReviewStatus;
use App\Support\PmsStatusRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsChangeReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
    }

    private function actor(bool $admin = false): User
    {
        $user = User::factory()->create(['role_id' => $admin ? 1 : null, 'status' => 1]);
        PmsProfile::create(['user_id' => $user->id, 'status' => 'active']);
        return $user;
    }

    private function projectWithTeam(): array
    {
        $admin = $this->actor(true);
        $analyst = $this->actor();
        $manager = $this->actor();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'REVIEW', 'name' => 'Published project', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::where('key', 'REVIEW')->firstOrFail();
        foreach ([$analyst->id => 'Business Analyst', $manager->id => 'Project Manager'] as $id => $role) {
            $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
                'user_id' => $id, 'role' => $role,
            ])->assertOk();
        }
        return [$admin, $analyst, $manager, $project];
    }

    private function requirement(User $admin, PmProject $project): PmWorkItem
    {
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'project_id' => $project->id, 'classification_key' => 'REQ', 'priority' => 'P3',
            'summary' => 'Published requirement', 'description' => 'Current description',
            'business_justification' => 'Needed', 'acceptance_criteria' => 'Reviewed',
        ])->assertCreated();
        return PmWorkItem::where('project_id', $project->id)->firstOrFail();
    }

    public function test_analyst_project_update_waits_for_manager_approval(): void
    {
        [, $analyst, $manager, $project] = $this->projectWithTeam();
        $response = $this->actingAs($analyst)->putJson(route('pms.projects.update', $project), [
            'name' => 'Proposed project', 'status' => 'Active', 'priority' => 'P3',
            'lock_version' => $project->lock_version,
        ])->assertStatus(202)->assertJsonPath('status', 'pending');
        $this->assertSame('Published project', $project->fresh()->name);
        $reviewId = $response->json('review_id');
        $this->assertDatabaseHas('pm_change_requests', ['id' => $reviewId, 'reviewer_id' => $manager->id,
            'status_code' => PmsReviewStatus::PENDING]);
        $this->actingAs($manager)->get(route('pms.change-reviews.index'))->assertOk()
            ->assertSee('Revision #1')->assertSee('Published project')->assertSee('Proposed project');
        $this->actingAs($analyst)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Self approval attempt',
        ])->assertForbidden();
        $this->actingAs($this->actor())->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Outsider approval attempt',
        ])->assertForbidden();
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Approved for delivery',
        ])->assertOk();
        $this->assertSame('Proposed project', $project->fresh()->name);
        $this->assertDatabaseHas('pm_change_requests', ['id' => $reviewId,
            'status_code' => PmsReviewStatus::APPROVED]);
    }

    public function test_analyst_ticket_changes_require_review_and_only_manager_can_delete(): void
    {
        [$admin, $analyst, $manager, $project] = $this->projectWithTeam();
        $ticket = $this->requirement($admin, $project);
        $reviewId = $this->actingAs($analyst)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'summary' => 'Proposed requirement',
        ])->assertStatus(202)->json('review_id');
        $this->assertSame('Published requirement', $ticket->fresh()->summary);
        $this->actingAs($analyst)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'summary' => 'Another proposal',
        ])->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'rejected', 'comment' => 'Needs clearer wording',
        ])->assertOk();
        $this->assertSame('Published requirement', $ticket->fresh()->summary);
        $reviewId = $this->actingAs($analyst)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'summary' => 'Revised requirement',
        ])->assertStatus(202)->json('review_id');
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Clear now',
        ])->assertOk();
        $this->assertSame('Revised requirement', $ticket->fresh()->summary);
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Duplicate decision',
        ])->assertStatus(409);
        $this->actingAs($analyst)->deleteJson(route('pms.tickets.destroy', $ticket), [
            'confirmation' => $ticket->issue_key, 'lock_version' => $ticket->fresh()->lock_version,
        ])->assertForbidden();
        $this->assertNull($ticket->fresh()->deleted_at);
        $this->actingAs($manager)->deleteJson(route('pms.tickets.destroy', $ticket), [
            'confirmation' => $ticket->reference,
        ])->assertOk();
        $this->assertNotNull(PmWorkItem::withTrashed()->findOrFail($ticket->id)->deleted_at);
        $this->assertDatabaseHas('pm_audit_logs', ['subject_id' => $ticket->id, 'action' => 'ticket.deleted']);
        $this->assertGreaterThan(0, DB::table('pm_issue_history')->where('issue_id', $ticket->id)->count());
    }

    public function test_submission_requires_a_project_manager_reviewer(): void
    {
        $admin = $this->actor(true);
        $analyst = $this->actor();
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'NOPM', 'name' => 'No manager yet', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::where('key', 'NOPM')->firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $analyst->id, 'role' => 'Business Analyst',
        ])->assertOk();
        $this->actingAs($analyst)->putJson(route('pms.projects.update', $project), [
            'lock_version' => $project->lock_version, 'name' => 'Pending manager',
            'status' => 'Planned', 'priority' => 'P3',
        ])->assertUnprocessable()->assertJsonValidationErrors('reviewer');
        $this->assertSame('No manager yet', $project->fresh()->name);
    }

    public function test_project_deletion_can_be_rejected_and_new_revision_can_be_approved(): void
    {
        [, $analyst, $manager, $project] = $this->projectWithTeam();
        $reviewId = $this->actingAs($analyst)->deleteJson(route('pms.projects.trash', $project), [
            'confirmation' => $project->key, 'lock_version' => $project->lock_version,
        ])->assertStatus(202)->json('review_id');
        $this->assertNull($project->fresh()->deleted_at);
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'rejected', 'comment' => 'Keep this project',
        ])->assertOk();
        $this->assertNull($project->fresh()->deleted_at);
        $nextId = $this->actingAs($analyst)->deleteJson(route('pms.projects.trash', $project), [
            'confirmation' => $project->key, 'lock_version' => $project->lock_version,
        ])->assertStatus(202)->json('review_id');
        $this->assertDatabaseHas('pm_change_requests', ['id' => $nextId, 'revision' => 2]);
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $nextId), [
            'decision' => 'approved', 'comment' => 'Project can be removed',
        ])->assertOk();
        $this->assertNotNull(PmProject::withTrashed()->findOrFail($project->id)->deleted_at);
    }

    public function test_enhancement_review_detects_stale_record_and_status_codes_are_preserved(): void
    {
        [$admin, $analyst, $manager, $project] = $this->projectWithTeam();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'project_id' => $project->id, 'classification_key' => 'ENH', 'priority' => 'P3',
            'summary' => 'Published enhancement', 'current_behavior' => 'Manual',
            'proposed_behavior' => 'Automated', 'acceptance_criteria' => 'Works',
        ])->assertCreated();
        $ticket = PmWorkItem::firstOrFail();
        $this->assertSame(PmsStatusRegistry::code('ticket', 'Planned'), $ticket->status_code);
        $reviewId = $this->actingAs($analyst)->putJson(route('pms.tickets.update', $ticket), [
            'lock_version' => $ticket->lock_version, 'proposed_behavior' => 'Automated with audit',
        ])->assertStatus(202)->json('review_id');
        $ticket->forceFill(['summary' => 'Manager changed it', 'lock_version' => $ticket->lock_version + 1])->save();
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $reviewId), [
            'decision' => 'approved', 'comment' => 'Looks good',
        ])->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->assertSame('Automated', $ticket->fresh()->proposed_behavior);
        $this->assertDatabaseHas('pm_change_requests', ['id' => $reviewId,
            'status_code' => PmsReviewStatus::PENDING]);
    }

    public function test_status_registry_dual_writes_and_reports_unknown_legacy_values(): void
    {
        [, , , $project] = $this->projectWithTeam();
        $this->assertSame(2, $project->fresh()->status_code);
        $project->forceFill(['status_code' => PmsStatusRegistry::code('project', 'Active')])->save();
        $this->assertDatabaseHas('pm_projects', ['id' => $project->id,
            'status' => 'Active', 'status_code' => 3]);
        DB::table('pm_projects')->where('id', $project->id)->update(['status_code' => null]);
        $this->artisan('pms:backfill-status-codes')->assertExitCode(0);
        $this->assertDatabaseHas('pm_projects', ['id' => $project->id, 'status_code' => null]);
        $this->artisan('pms:backfill-status-codes --apply')->assertExitCode(0);
        $this->artisan('pms:backfill-status-codes --apply')->assertExitCode(0);
        $this->assertDatabaseHas('pm_projects', ['id' => $project->id, 'status_code' => 3]);
        DB::table('pm_projects')->where('id', $project->id)->update(['status' => 'Legacy Unknown', 'status_code' => null]);
        $project->refresh()->forceFill(['name' => 'Historical project'])->save();
        $this->assertDatabaseHas('pm_projects', ['id' => $project->id,
            'status' => 'Legacy Unknown', 'status_code' => null]);
        $this->artisan('pms:audit-statuses --strict')->assertExitCode(1);
    }

    public function test_all_declared_legacy_statuses_round_trip_without_collapsing_domains(): void
    {
        foreach (PmsStatusRegistry::DEFINITIONS as $domain => $definitions) {
            foreach ($definitions as $code => $legacy) {
                $this->assertSame($code, PmsStatusRegistry::code($domain, $legacy));
                $this->assertSame($legacy, PmsStatusRegistry::key($domain, $code));
            }
        }
        $this->assertSame(6, PmsStatusRegistry::code('ticket', 'QAT in Progress'));
        $this->assertNull(PmsStatusRegistry::code('project', 'Unknown'));
        $this->assertNull(PmsStatusRegistry::code('project', ''));
    }

    public function test_analyst_automation_api_cannot_publish_protected_edits_or_deletion(): void
    {
        [$admin, $analyst, , $project] = $this->projectWithTeam();
        $ticket = $this->requirement($admin, $project);
        auth('web')->logout();
        auth('pms')->logout();
        $this->withToken($analyst->createToken('review-test', ['pms'], now()->addHour())->plainTextToken);
        $projectReview = $this->postJson(route('api.pms.v1.projects.updateInfo'), [
            'action' => 'update', 'project_id' => $project->id, 'name' => 'API proposed project',
        ])->assertStatus(202)->assertJsonPath('code', 'PROJECT_REVIEW_PENDING')->json('data.review_id');
        $this->assertSame('Published project', $project->fresh()->name);
        $this->postJson(route('api.pms.v1.tickets.updateInfo'), [
            'action' => 'update', 'ticket_id' => $ticket->id, 'title' => 'API proposed ticket',
        ])->assertStatus(202)->assertJsonPath('code', 'TICKET_REVIEW_PENDING');
        $this->assertSame('Published requirement', $ticket->fresh()->summary);
        $this->postJson(route('api.pms.v1.tickets.updateInfo'), [
            'action' => 'update', 'ticket_id' => $ticket->id, 'status' => 'Open',
        ])->assertForbidden();
        $this->postJson(route('api.pms.v1.projects.updateInfo'), [
            'action' => 'delete', 'project_id' => $project->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('review');
        $this->postJson(route('api.pms.change-reviews.cancel', $projectReview))->assertOk();
        $this->postJson(route('api.pms.v1.projects.updateInfo'), [
            'action' => 'delete', 'project_id' => $project->id,
        ])->assertStatus(202)->assertJsonPath('code', 'PROJECT_REVIEW_PENDING');
        $this->assertNull($project->fresh()->deleted_at);
    }
}
