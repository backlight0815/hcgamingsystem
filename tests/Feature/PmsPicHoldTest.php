<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmWorkItem;
use App\Models\PmsProfile;
use App\Models\User;
use App\Support\PmsStatusRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsPicHoldTest extends TestCase
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

    private function ticket(User $admin): PmWorkItem
    {
        $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'PICHOLD', 'name' => 'PIC and hold', 'priority' => 'P3',
        ])->assertCreated();
        $project = PmProject::where('key', 'PICHOLD')->firstOrFail();
        $this->actingAs($admin)->postJson(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id,
            'summary' => 'Requirement', 'priority' => 'P3', 'description' => 'Deliver it',
            'business_justification' => 'Needed', 'acceptance_criteria' => 'Reviewed',
        ])->assertCreated();
        return PmWorkItem::where('project_id', $project->id)->firstOrFail();
    }

    public function test_pic_set_is_atomic_scoped_and_independent_of_developer_owner(): void
    {
        $admin = $this->actor(true);
        $ticket = $this->ticket($admin);
        $first = $this->actor();
        $second = $this->actor();
        $outsider = $this->actor();
        foreach ([$first, $second] as $member) {
            $this->actingAs($admin)->postJson(route('pms.projects.members.store', $ticket->project_id), [
                'user_id' => $member->id, 'role' => 'Developer',
            ])->assertOk();
        }
        $this->actingAs($admin)->putJson(route('pms.tickets.pics.update', $ticket), [
            'assignee_ids' => [$first->id, $second->id], 'lock_version' => $ticket->lock_version,
        ])->assertOk()->assertJsonPath('data.ticket_id', $ticket->id);
        $ticket->refresh();
        $this->assertNull($ticket->developer_id);
        $this->assertSame(2, DB::table('pm_ticket_contributors')->where('issue_id', $ticket->id)->where('assignment_type', 'pic')->count());
        $this->actingAs($admin)->putJson(route('pms.tickets.pics.update', $ticket), [
            'assignee_ids' => [$first->id, $outsider->id], 'lock_version' => $ticket->lock_version,
        ])->assertUnprocessable()->assertJsonValidationErrors('assignee_ids');
        $this->assertSame(2, DB::table('pm_ticket_contributors')->where('issue_id', $ticket->id)->where('assignment_type', 'pic')->count());
        $this->actingAs($admin)->deleteJson(route('pms.tickets.pics.destroy', [$ticket, $first->id]))->assertOk();
        $this->assertDatabaseHas('pm_ticket_contributors', ['issue_id' => $ticket->id, 'user_id' => $second->id, 'assignment_type' => 'pic']);
        $this->assertDatabaseMissing('pm_ticket_contributors', ['issue_id' => $ticket->id, 'user_id' => $first->id, 'assignment_type' => 'pic']);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'pic_assignment']);
        $this->actingAs($admin)->get(route('pms.tickets.show', $ticket))->assertOk()
            ->assertSee('Person-in-Charge (PIC)')->assertSee('Save assignment')->assertSee($second->name);
    }

    public function test_blocker_only_pauses_on_request_and_resume_requires_reason_for_override(): void
    {
        $admin = $this->actor(true);
        $ticket = $this->ticket($admin);
        $created = $this->actingAs($admin)->postJson(route('pms.tickets.blockers.store', $ticket), [
            'category' => 'Development Dependency', 'reason' => 'API pending',
        ])->assertCreated();
        $this->assertSame('Planned', $ticket->fresh()->status);
        $this->actingAs($admin)->postJson(route('pms.tickets.hold', $ticket), [
            'lock_version' => $ticket->lock_version, 'reason' => 'Cannot proceed', 'blocker_id' => $created->json('id'),
        ])->assertOk();
        $ticket->refresh();
        $this->assertSame('On Hold', $ticket->status);
        $this->assertSame(33, $ticket->status_code);
        $this->assertSame(PmsStatusRegistry::code('ticket', 'Planned'), (int) $ticket->hold_previous_status_code);
        $this->actingAs($admin)->postJson(route('pms.tickets.resume', $ticket), [
            'lock_version' => $ticket->lock_version, 'status' => 'Planned',
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->actingAs($admin)->postJson(route('pms.tickets.resume', $ticket), [
            'lock_version' => $ticket->lock_version, 'status' => 'Planned',
            'override_unresolved' => true, 'reason' => 'Other work can continue',
        ])->assertOk();
        $this->assertSame('Planned', $ticket->fresh()->status);
        $this->assertNull(DB::table('pm_ticket_blockers')->find($created->json('id'))->resolved_at);
        $this->assertDatabaseHas('pm_issue_history', ['issue_id' => $ticket->id, 'event' => 'resume']);
        $this->actingAs($admin)->putJson(route('pms.tickets.blockers.update', [$ticket, $created->json('id')]), [
            'category' => 'Development Dependency', 'reason' => 'API pending', 'status_code' => 2,
        ])->assertOk()->assertJsonPath('status_code', 2);
        $this->actingAs($admin)->postJson(route('pms.tickets.blockers.cancel', [$ticket, $created->json('id')]), [
            'reason' => 'Dependency no longer applies',
        ])->assertOk();
        $this->assertDatabaseHas('pm_ticket_blockers', ['id' => $created->json('id'), 'status_code' => 4]);
    }

    public function test_business_analyst_pic_and_hold_changes_wait_for_manager_review(): void
    {
        $admin = $this->actor(true);
        $ticket = $this->ticket($admin);
        $analyst = $this->actor();
        $manager = $this->actor();
        $pic = $this->actor();
        foreach ([$analyst->id => 'Business Analyst', $manager->id => 'Project Manager', $pic->id => 'Developer'] as $id => $role) {
            $this->actingAs($admin)->postJson(route('pms.projects.members.store', $ticket->project_id), [
                'user_id' => $id, 'role' => $role,
            ])->assertOk();
        }
        $picReview = $this->actingAs($analyst)->putJson(route('pms.tickets.pics.update', $ticket), [
            'assignee_ids' => [$pic->id], 'lock_version' => $ticket->lock_version,
        ])->assertStatus(202)->json('review_id');
        $this->assertDatabaseMissing('pm_ticket_contributors', ['issue_id' => $ticket->id, 'user_id' => $pic->id, 'assignment_type' => 'pic']);
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $picReview), [
            'decision' => 'approved', 'comment' => 'Assign this PIC',
        ])->assertOk();
        $this->assertDatabaseHas('pm_ticket_contributors', ['issue_id' => $ticket->id, 'user_id' => $pic->id, 'assignment_type' => 'pic']);
        $ticket->refresh();
        $holdReview = $this->actingAs($analyst)->postJson(route('pms.tickets.hold', $ticket), [
            'lock_version' => $ticket->lock_version, 'reason' => 'Waiting for business input',
        ])->assertStatus(202)->json('review_id');
        $this->assertSame('Planned', $ticket->fresh()->status);
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $holdReview), [
            'decision' => 'approved', 'comment' => 'Pause approved',
        ])->assertOk();
        $this->assertSame('On Hold', $ticket->fresh()->status);
        $ticket->refresh();
        $resumeReview = $this->actingAs($analyst)->postJson(route('pms.tickets.resume', $ticket), [
            'lock_version' => $ticket->lock_version, 'status' => 'Planned',
        ])->assertStatus(202)->json('review_id');
        $this->actingAs($manager)->postJson(route('pms.change-reviews.decide', $resumeReview), [
            'decision' => 'approved', 'comment' => 'Resume approved',
        ])->assertOk();
        $this->assertSame('Planned', $ticket->fresh()->status);
    }
}
