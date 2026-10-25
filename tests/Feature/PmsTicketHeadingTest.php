<?php

namespace Tests\Feature;

use App\Models\FeatureToggle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmsTicketHeadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_heading_follows_each_supported_category_and_survives_filters(): void
    {
        FeatureToggle::updateOrCreate(['feature_name' => FeatureToggle::MODULE_PROJECT_MANAGEMENT], ['enabled' => true]);
        $admin = User::factory()->create(['role_id' => 1, 'status' => 1]);
        foreach (['' => 'All Tickets', 'REQ' => 'REQ Tickets', 'ENH' => 'ENH Tickets', 'PRD' => 'PRD Tickets', 'QAT' => 'QAT Tickets', 'TSK' => 'TSK Tickets'] as $key => $heading) {
            $params = $key ? ['category' => $key, 'q' => 'nothing'] : [];
            $this->actingAs($admin)->get(route('pms.tickets.index', $params))
                ->assertOk()->assertSee('<h1>'.$heading.'</h1>', false)
                ->assertSee('<title>'.$heading, false)
                ->assertSee($key ? 'No '.$heading.' Found' : 'No Tickets Found');
        }
    }
}
