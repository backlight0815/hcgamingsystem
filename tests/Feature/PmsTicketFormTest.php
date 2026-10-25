<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\PmProject;
use App\Models\PmsProfile;
use App\Models\PmWorkItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmsTicketFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_analyst_can_submit_a_requirement_from_the_html_form(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        $project = $this->actingAs($admin)->postJson(route('pms.projects.store'), [
            'key' => 'REQ', 'name' => 'Requirement project', 'priority' => 'P2',
        ])->assertCreated();
        $project = PmProject::firstOrFail();
        $analyst = User::factory()->create(['role_id' => null, 'status' => 1]);
        PmsProfile::create(['user_id' => $analyst->id, 'status' => 'active', 'workspace_role' => 'Business Analyst']);
        $this->actingAs($admin)->postJson(route('pms.projects.members.store', $project), [
            'user_id' => $analyst->id, 'role' => 'Business Analyst',
        ])->assertOk();

        $this->actingAs($analyst)->get(route('pms.tickets.create', ['category' => 'REQ']))
            ->assertOk()->assertSee('value="REQ"', false);
        $this->actingAs($analyst)->post(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id,
            'summary' => 'A complete requirement', 'priority' => 'P3',
            'description' => 'The team needs a reliable workflow.',
            'business_justification' => 'Reduce manual work.',
            'acceptance_criteria' => 'The workflow completes.',
        ])->assertRedirect();
        $ticket = PmWorkItem::where('classification_key', 'REQ')->firstOrFail();
        $this->assertSame('REQ-00001', $ticket->issue_key);
        $this->assertSame('sfreq0001', $ticket->reference);
        $this->actingAs($analyst)->get(route('pms.tickets.show', $ticket))->assertOk()
            ->assertSee('A complete requirement');
        $this->actingAs($analyst)->get(route('pms.tickets.index'))->assertOk()
            ->assertSee($ticket->reference);
        $this->actingAs($analyst)->post(route('pms.tickets.store'), [
            'classification_key' => 'REQ', 'project_id' => $project->id,
            'summary' => 'Incomplete requirement', 'priority' => 'P3',
            'description' => 'The entered description should survive validation.',
            'business_justification' => '', 'acceptance_criteria' => 'The workflow completes.',
        ])->assertRedirect()->assertSessionHasErrors('business_justification');
        $this->actingAs($analyst)->get(route('pms.tickets.create', ['category' => 'REQ']))
            ->assertOk()->assertSee('Incomplete requirement')->assertSee('Please fix:');
    }
}
